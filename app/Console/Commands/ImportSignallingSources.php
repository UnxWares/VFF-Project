<?php

namespace App\Console\Commands;

use App\Services\Signalling\SignallingCorrectionsFile;
use App\Services\Signalling\SignallingHistoryMap;
use App\Services\Signalling\SignallingSectionLocator;
use App\Services\Signalling\SignallingSourcesFile;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Mises en service de signalisation datées par des documents (SNCF Réseau, BEA-TT, EPSF, Journal officiel,
 * presse, ouvrages) plutôt que par l'article Wikipédia de la ligne : `database/data/signalling_history_sources.php`
 * (SignallingSourcesFile), rangées dans `line_signalling_events` avec `source = 'manual'` et
 * `origin = 'document'`, à côté des faits de `vff:import-signalling-history`, que cette commande ne touche pas.
 *
 * Chaque portion est placée sur la ligne comme les faits de Wikipédia (SignallingSectionLocator : gares
 * d'extrémité et leurs PK calés, puis tracé sur les formes SNCF Réseau), puis la carte par ère est recalculée
 * (`line_signalling_history_map`). Aucune requête vers l'extérieur.
 *
 * Corrections sourcées (`database/data/signalling_history_corrections.php`, SignallingCorrectionsFile) : un fait
 * des documents qu'une correction vise n'est plus montré (`confidence = 'hidden'`, colonne `correction`), et le
 * fait juste d'une correction `replace` est rangé ici avec les faits du fichier, quelle que soit la source du
 * fait corrigé. Un fait visé introuvable est une erreur.
 *
 * Rejouable : empreinte stable (relevé, ligne, système, date, portion) ; un fait retiré du fichier disparaît
 * de la table (faits `manual` seulement).
 *
 *   php artisan vff:import-signalling-sources --dry-run -v
 *   php artisan vff:import-signalling-sources
 */
class ImportSignallingSources extends Command
{
    protected $signature = 'vff:import-signalling-sources
        {--file= : fichier des faits (défaut : database/data/signalling_history_sources.php)}
        {--corrections= : fichier des corrections (défaut : database/data/signalling_history_corrections.php)}
        {--dry-run : n\'écrit rien en base, affiche le bilan (et les faits avec -v)}';

    protected $description = 'Range les mises en service de signalisation datées par des documents (SNCF Réseau, BEA-TT, EPSF, presse), les place sur le tracé et recalcule la carte par ère.';

    public function handle(SignallingSectionLocator $locator, SignallingHistoryMap $map): int
    {
        $path = $this->option('file') ?: SignallingSourcesFile::defaultPath();
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }
        $entries = require $path;
        if (! is_array($entries)) {
            $this->error('Le fichier doit renvoyer une liste de faits.');

            return self::FAILURE;
        }
        $corrections = $this->corrections();
        if ($corrections === null) {
            return self::FAILURE;
        }
        // Le fait juste d'une correction `replace` rejoint les faits du fichier.
        $replacements = array_values(array_filter(array_map(fn (array $c): ?array => $c['with'], $corrections)));
        ['facts' => $facts, 'errors' => $errors] = SignallingSourcesFile::parse([...array_values($entries), ...$replacements]);
        foreach ($errors as $error) {
            $this->warn('  Refusé : '.$error);
        }
        if ($errors !== []) {
            $this->error(count($errors).' fait(s) refusé(s) : corrigez le fichier, rien n\'est écrit.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $lines = $this->lines(array_values(array_unique(array_column($facts, 'line'))));
        $rows = [];
        $unknown = [];
        $stats = ['location' => [], 'system' => [], 'verification' => []];
        $now = now();
        $applied = [];
        foreach ($facts as $f) {
            $line = $lines[$f['line']] ?? null;
            if ($line === null) {
                $unknown[] = $f['line'];

                continue;
            }
            $fingerprint = sha1(implode('|', ['manual', $line['id'], $f['system'], $f['date'], $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']]));
            $place = $locator->locate($line['id'], $f['scope'], $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']);
            $geometry = ! $dry && $place['pk_start_m'] !== null ? $locator->geometry($line['code'], $place['pk_start_m'], (int) $place['pk_end_m']) : null;
            $first = $f['documents'][0];
            $correction = null;
            foreach ($corrections as $i => $c) {
                if (SignallingCorrectionsFile::matches($c, 'manual', [$f['line'], (string) $line['code']], $f['system'], $f['date'], $f['section_from'], $f['section_to'])) {
                    $correction = $c;
                    $applied[$i] = true;
                }
            }
            $rows[$fingerprint] = [
                'line_id' => $line['id'],
                'system' => $f['system'],
                'role' => $f['role'],
                'event_date' => $f['date'],
                'date_precision' => $f['precision'],
                'scope' => $f['scope'],
                'section_from' => $f['section_from'],
                'section_to' => $f['section_to'],
                'pk_start_m' => $f['pk_start_m'],
                'pk_end_m' => $f['pk_end_m'],
                'section_location' => $place['location'],
                'from_station_id' => $place['from_station_id'],
                'to_station_id' => $place['to_station_id'],
                'located_pk_start_m' => $place['pk_start_m'],
                'located_pk_end_m' => $place['pk_end_m'],
                'section_geom' => $geometry,
                'confidence' => $correction === null ? $f['confidence'] : 'hidden',
                'correction' => $correction === null ? null : SignallingCorrectionsFile::stored($correction),
                'origin' => 'document',
                'source' => 'manual',
                'source_title' => mb_substr($first['title'], 0, 255),
                'source_revision' => null,
                'source_url' => mb_substr($first['url'], 0, 600),
                'source_excerpt' => $f['summary'],
                'documents' => json_encode($f['documents'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'verification' => $f['verification'],
                'fingerprint' => $fingerprint,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $location = $place['location'] ?? $f['scope'];
            $stats['location'][$location] = ($stats['location'][$location] ?? 0) + 1;
            $stats['system'][$f['system']] = ($stats['system'][$f['system']] ?? 0) + 1;
            $stats['verification'][$f['verification']] = ($stats['verification'][$f['verification']] ?? 0) + 1;
            if ($dry && $this->output->isVerbose()) {
                $this->line(sprintf('  [%s] %-8s %s %-5s %s', $f['line'], $f['system'], $f['date'], $f['precision'],
                    $f['scope'] === 'section' ? ($f['section_from'] ?? '?').' -> '.($f['section_to'] ?? '?').' ['.$place['location'].']' : $f['scope']));
            }
        }
        foreach (array_unique($unknown) as $code) {
            $this->warn("  Ligne inconnue : {$code} (faits ignorés)");
        }

        $unused = [];
        $known = $this->lines(array_column($corrections, 'line'));
        foreach ($corrections as $i => $c) {
            // Une ligne absente de la base (copie partielle) n'est pas une erreur : il n'y a rien à corriger.
            if ($c['target']['source'] === 'manual' && ! isset($applied[$i]) && isset($known[$c['line']])) {
                $unused[] = SignallingCorrectionsFile::describe($c);
            }
        }

        if ($unused !== []) {
            foreach ($unused as $label) {
                $this->error("Correction sans fait visé : {$label} (le fichier des documents a changé ?)");
            }
            $this->error('Rien n\'est écrit.');

            return self::FAILURE;
        }

        $removed = DB::table('line_signalling_events')->where('source', 'manual')->whereNotIn('fingerprint', array_keys($rows) ?: ['']);
        $removedCount = $dry ? $removed->count() : $this->store(array_values($rows), $removed);

        $this->info(sprintf('%s%d faits datés par des documents, sur %d lignes (%d retirés du fichier).',
            $dry ? '[simulation] ' : '', count($rows), count(array_unique(array_column($rows, 'line_id'))), $removedCount));
        foreach (['system' => 'Système', 'location' => 'Portion', 'verification' => 'Vérification'] as $key => $label) {
            ksort($stats[$key]);
            $this->table([$label, 'Faits'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats[$key]), $stats[$key]));
        }
        if (! $dry) {
            $this->info('Carte par ère : '.$map->rebuild().' tracés (ères passées ; l\'ère courante garde l\'état de SNCF Réseau).');
        }

        return $unknown === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Corrections du fichier, ou null (erreur affichée) s'il est illisible ou contient une correction refusée.
     *
     * @return list<array<string, mixed>>|null
     */
    private function corrections(): ?array
    {
        $path = $this->option('corrections') ?: SignallingCorrectionsFile::defaultPath();
        if (! is_file($path)) {
            $this->error("Fichier de corrections introuvable : {$path}");

            return null;
        }
        $entries = require $path;
        if (! is_array($entries)) {
            $this->error('Le fichier de corrections doit renvoyer une liste.');

            return null;
        }
        ['corrections' => $corrections, 'errors' => $errors] = SignallingCorrectionsFile::parse(array_values($entries));
        foreach ($errors as $error) {
            $this->warn('  Refusée : '.$error);
        }
        if ($errors !== []) {
            $this->error(count($errors).' correction(s) refusée(s) : corrigez le fichier, rien n\'est écrit.');

            return null;
        }

        return $corrections;
    }

    /**
     * Lignes citées, par code ou slug.
     *
     * @param  list<string>  $refs
     * @return array<string, array{id: int, code: ?string}>
     */
    private function lines(array $refs): array
    {
        $found = [];
        foreach (DB::table('lines')->whereIn('code', $refs)->orWhereIn('slug', $refs)->get(['id', 'code', 'slug']) as $l) {
            $line = ['id' => (int) $l->id, 'code' => $l->code];
            foreach ([$l->code, $l->slug] as $ref) {
                if ($ref !== null && in_array($ref, $refs, true)) {
                    $found[$ref] = $line;
                }
            }
        }

        return $found;
    }

    /**
     * Écrit les faits et retire ceux qui ont quitté le fichier.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return int nombre de faits retirés
     */
    private function store(array $rows, Builder $removed): int
    {
        return DB::transaction(function () use ($rows, $removed): int {
            if ($rows !== []) {
                $geometries = array_column($rows, 'section_geom', 'fingerprint');
                $plain = array_map(fn (array $r): array => array_diff_key($r, ['section_geom' => true]), $rows);
                DB::table('line_signalling_events')->upsert($plain, ['fingerprint'], [
                    'role', 'date_precision', 'scope', 'section_location', 'from_station_id', 'to_station_id', 'located_pk_start_m', 'located_pk_end_m',
                    'confidence', 'correction', 'origin', 'source', 'source_title', 'source_revision', 'source_url', 'source_excerpt', 'documents', 'verification', 'updated_at',
                ]);
                foreach ($geometries as $fingerprint => $geometry) {
                    DB::update("UPDATE line_signalling_events SET section_geom = CASE WHEN ?::text IS NULL THEN NULL ELSE ST_GeomFromEWKB(decode(?, 'hex')) END WHERE fingerprint = ?",
                        [$geometry, $geometry, $fingerprint]);
                }
            }

            return $removed->delete();
        });
    }
}
