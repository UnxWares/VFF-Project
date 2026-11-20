<?php

namespace App\Services;

use App\Enums\ServiceYearChange;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Changements du rattachement gares-lignes (`station_lines`) au cours d'un calcul de
 * vff:attach-stations : la table est photographiée au début (table temporaire de la
 * session), puis comparée ligne à ligne à la fin, avant la validation ou l'annulation de
 * la transaction. Sert au passage à blanc (--dry-run) et au rapport CSV (--report).
 *
 * Chaque changement : rattachement ajouté, retiré ou gardé ; méthode et PK avant / après ;
 * années de desserte avant / après, classées par ServiceYearChange (ajoutée, retirée,
 * avancée, reculée).
 *
 * @phpstan-type LinkChange array{station_id: int, station: ?string, line_id: int, line_code: ?string, line: ?string, link: string,
 *     method_before: ?string, method_after: ?string, pk_before: ?int, pk_after: ?int,
 *     first_before: ?int, first_after: ?int, first_change: ?ServiceYearChange,
 *     last_before: ?int, last_after: ?int, last_change: ?ServiceYearChange,
 *     source_before: ?string, source_after: ?string}
 */
class StationLinkChanges
{
    private const SNAPSHOT = 'tmp_station_lines_before';

    public const LINK_ADDED = 'ajouté';

    public const LINK_REMOVED = 'retiré';

    public const LINK_KEPT = 'gardé';

    /** Années comparées (clé du changement → libellé du bilan). */
    private const YEARS = ['first_change' => 'année de début', 'last_change' => 'année de fin'];

    /** Photographie de station_lines (à prendre dans la même connexion que le calcul). */
    public function snapshot(): void
    {
        DB::statement('DROP TABLE IF EXISTS '.self::SNAPSHOT);
        DB::statement('CREATE TEMP TABLE '.self::SNAPSHOT.' AS
            SELECT station_id, line_id, method, pk_m, first_year, last_year, years_source FROM station_lines');
    }

    /**
     * Rattachements qui diffèrent de la photographie, dans l'ordre des lignes puis des PK.
     *
     * @return list<LinkChange>
     */
    public function compare(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT COALESCE(b.station_id, a.station_id) AS station_id, COALESCE(b.line_id, a.line_id) AS line_id,
                   s.name AS station, l.code AS line_code, l.name AS line,
                   a.station_id IS NULL AS added, b.station_id IS NULL AS removed,
                   a.method AS method_before, b.method AS method_after, a.pk_m AS pk_before, b.pk_m AS pk_after,
                   a.first_year AS first_before, b.first_year AS first_after, a.last_year AS last_before, b.last_year AS last_after,
                   a.years_source AS source_before, b.years_source AS source_after
            FROM tmp_station_lines_before a
            FULL JOIN station_lines b ON b.station_id = a.station_id AND b.line_id = a.line_id
            LEFT JOIN stations s ON s.id = COALESCE(b.station_id, a.station_id)
            LEFT JOIN lines l ON l.id = COALESCE(b.line_id, a.line_id)
            WHERE a.station_id IS NULL OR b.station_id IS NULL
               OR a.method IS DISTINCT FROM b.method OR a.pk_m IS DISTINCT FROM b.pk_m
               OR a.first_year IS DISTINCT FROM b.first_year OR a.last_year IS DISTINCT FROM b.last_year
               OR a.years_source IS DISTINCT FROM b.years_source
            ORDER BY l.code NULLS LAST, COALESCE(b.line_id, a.line_id), COALESCE(b.pk_m, a.pk_m) NULLS LAST, s.name, 1
        SQL);
        $int = fn ($v): ?int => $v === null ? null : (int) $v;

        return array_map(function ($r) use ($int) {
            $kept = ! $r->added && ! $r->removed;

            return [
                'station_id' => (int) $r->station_id, 'station' => $r->station, 'line_id' => (int) $r->line_id,
                'line_code' => $r->line_code, 'line' => $r->line,
                'link' => match (true) {
                    (bool) $r->added => self::LINK_ADDED,
                    (bool) $r->removed => self::LINK_REMOVED,
                    default => self::LINK_KEPT,
                },
                'method_before' => $r->method_before, 'method_after' => $r->method_after,
                'pk_before' => $int($r->pk_before), 'pk_after' => $int($r->pk_after),
                'first_before' => $int($r->first_before), 'first_after' => $int($r->first_after),
                // Un rattachement ajouté ou retiré n'a pas d'année « changée » : il est compté à part.
                'first_change' => $kept ? ServiceYearChange::between($int($r->first_before), $int($r->first_after)) : null,
                'last_before' => $int($r->last_before), 'last_after' => $int($r->last_after),
                'last_change' => $kept ? ServiceYearChange::between($int($r->last_before), $int($r->last_after)) : null,
                'source_before' => $r->source_before, 'source_after' => $r->source_after,
            ];
        }, $rows);
    }

    /**
     * Bilan par classe de changement (libellé → nombre), dans un ordre fixe.
     *
     * @param  list<LinkChange>  $changes  résultat de compare()
     * @return array<string, int>
     */
    public function summary(array $changes): array
    {
        $out = ['rattachements ajoutés' => 0, 'rattachements retirés' => 0, 'méthodes changées' => 0, 'PK changés' => 0];
        foreach (self::YEARS as $label) {
            foreach (ServiceYearChange::cases() as $case) {
                $out["{$label} {$case->label()}"] = 0;
            }
        }
        $out['source des années changée'] = 0;
        foreach ($changes as $c) {
            if ($c['link'] !== self::LINK_KEPT) {
                $out[$c['link'] === self::LINK_ADDED ? 'rattachements ajoutés' : 'rattachements retirés']++;

                continue;
            }
            $out['méthodes changées'] += (int) ($c['method_before'] !== $c['method_after']);
            $out['PK changés'] += (int) ($c['pk_before'] !== $c['pk_after']);
            foreach (self::YEARS as $key => $label) {
                $change = $c[$key];
                if ($change instanceof ServiceYearChange) {
                    $out["{$label} {$change->label()}"]++;
                }
            }
            $out['source des années changée'] += (int) ($c['source_before'] !== $c['source_after']);
        }

        return $out;
    }

    /**
     * Rapport CSV (une ligne par rattachement changé). Chemin relatif : depuis la racine du projet.
     *
     * @param  list<LinkChange>  $changes  résultat de compare()
     */
    public function writeCsv(array $changes, string $path): string
    {
        $path = str_starts_with($path, '/') ? $path : base_path($path);
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException("Dossier du rapport impossible à créer : {$path}");
        }
        $f = fopen($path, 'w');
        if ($f === false) {
            throw new RuntimeException("Rapport impossible à écrire : {$path}");
        }
        fputcsv($f, ['gare_id', 'gare', 'ligne_id', 'ligne_code', 'ligne', 'rattachement', 'methode_avant', 'methode_apres', 'pk_avant_m', 'pk_apres_m',
            'debut_avant', 'debut_apres', 'debut', 'fin_avant', 'fin_apres', 'fin', 'source_annees_avant', 'source_annees_apres'], escape: '');
        foreach ($changes as $c) {
            fputcsv($f, [$c['station_id'], $c['station'], $c['line_id'], $c['line_code'], $c['line'], $c['link'], $c['method_before'], $c['method_after'],
                $c['pk_before'], $c['pk_after'], $c['first_before'], $c['first_after'], $c['first_change']?->label(),
                $c['last_before'], $c['last_after'], $c['last_change']?->label(), $c['source_before'], $c['source_after']], escape: '');
        }
        fclose($f);

        return $path;
    }
}
