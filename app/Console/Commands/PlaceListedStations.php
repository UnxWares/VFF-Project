<?php

namespace App\Console\Commands;

use App\Services\StationPositioning\ListedPlacement;
use App\Services\StationPositioning\ListedStationPlacer;
use App\Support\CsvSafe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replace les gares des listes historiques (schémas Wikipédia, lignes-oubliees) posées
 * par un calage propre à la liste, par un calage robuste « PK = distance » sur les gares
 * de position fiable et les lieux-dits homonymes proches de la voie
 * (App\Services\StationPositioning\ListedStationPlacer).
 *
 * Après vff:consolidate-stations, avant vff:attach-stations (vff:stations-pipeline) :
 *   - station_sources : nouvelle position de la gare de liste (geocoding « pk »,
 *     precision_m, placement, calibration), l'ancienne gardée dans properties.recalage_pk ;
 *   - stations : position de la gare quand elle vient d'une liste (la plus précise de ses
 *     gares de liste, comme vff:consolidate-stations), écart aux sources
 *     (position_spread_m, position_flagged), détail dans provenance.geom_recalage.
 *
 * Idempotent : un second passage ne déplace plus rien (positions concordantes).
 *
 *   php artisan vff:place-listed-stations --dry-run
 *   php artisan vff:place-listed-stations --line=3836 -v
 */
class PlaceListedStations extends Command
{
    protected $signature = 'vff:place-listed-stations
        {--line=* : Limite aux lignes données (id)}
        {--no-places : Sans les lieux-dits IGN (aucune requête réseau)}
        {--report= : CSV des gares déplacées (défaut : storage/app/reports/gares-recalage-pk.csv)}
        {--validate : Validation croisée sur les gares d\'appui (lecture seule)}
        {--dry-run : Calcule et affiche, sans rien écrire}';

    protected $description = 'Replace les gares des listes par ligne (PK) par un calage « PK = distance » sur les gares fiables et les lieux-dits';

    public function handle(ListedStationPlacer $placer): int
    {
        if ($this->option('validate')) {
            return $this->validate($placer);
        }
        $lines = array_map('intval', array_filter((array) $this->option('line'))) ?: $placer->candidateLines();
        $all = [];
        $bar = $this->output->createProgressBar(count($lines));
        foreach ($lines as $lineId) {
            $bar->advance();
            array_push($all, ...$placer->placeLine($lineId, ! $this->option('no-places')));
        }
        $bar->finish();
        $this->newLine();

        $moved = array_values(array_filter($all, fn (ListedPlacement $p) => $p->moved()));
        $reasons = [];
        foreach ($all as $p) {
            $k = $p->moved() ? 'replacée' : preg_replace(['/^inchangée : /u', '/^(le lieu-dit) .* (contredit le calage)$/u', '/^(hors de sa commune) \(.*\)$/u', "/^(s'éloignerait de son lieu-dit) .*$/u"], ['', '$1 $2', '$1', '$1'], $p->note);
            $reasons[$k] = ($reasons[$k] ?? 0) + 1;
        }
        arsort($reasons);
        $this->line(sprintf('%d gare(s) de liste examinée(s) sur %d ligne(s)', count($all), count($lines)));
        foreach ($reasons as $k => $n) {
            $this->line("  {$k} : {$n}");
        }
        if ($this->output->isVerbose() || $this->option('dry-run')) {
            foreach ($all as $p) {
                $this->line(sprintf('  [%d] %s · PK %d · %s%s', $p->lineId, $p->name, $p->pk, $p->moved() ? "déplacée de {$p->movedM} m, précision {$p->precisionM} m · " : '', $p->note));
            }
        }
        if ($this->option('dry-run')) {
            $this->writeReport($this->proposals($moved));
            $this->warn('--dry-run : rien n\'est écrit dans la base');

            return self::SUCCESS;
        }

        $stations = DB::transaction(fn () => $this->write($moved));
        $this->writeReport($this->movedStations($moved, $stations));
        $this->info(sprintf('%d position(s) de liste mise(s) à jour, %d gare(s) déplacée(s)', count($moved), count($stations)));

        return self::SUCCESS;
    }

    /**
     * Validation croisée (ListedStationPlacer::crossValidate) : écart le long de la voie des
     * gares d'appui replacées sans elles, calage robuste contre interpolation naïve, sur les
     * gares que les deux placent.
     */
    private function validate(ListedStationPlacer $placer): int
    {
        $lines = array_map('intval', array_filter((array) $this->option('line')))
            ?: array_map('intval', DB::table('station_sources')->whereIn('source', ListedStationPlacer::LISTED)
                ->whereNotNull(DB::raw("properties->>'line_id'"))->distinct()->pluck(DB::raw("(properties->>'line_id')::bigint AS l"))->all());
        $rows = [];
        foreach ($lines as $lineId) {
            array_push($rows, ...$placer->crossValidate($lineId, ! $this->option('no-places')));
        }
        $both = array_values(array_filter($rows, fn ($r) => $r['robust'] !== null && $r['naive'] !== null));
        $stat = function (array $v): string {
            sort($v);
            $q = fn (float $f) => $v ? $v[min(count($v) - 1, (int) floor($f * count($v)))] : '-';

            return sprintf('médiane %s m, p75 %s m, p90 %s m, > 100 m : %d, > 300 m : %d, > 1 km : %d',
                $q(0.5), $q(0.75), $q(0.9), count(array_filter($v, fn ($x) => $x > 100)), count(array_filter($v, fn ($x) => $x > 300)), count(array_filter($v, fn ($x) => $x > 1000)));
        };
        $this->line(sprintf('Validation croisée : %d gares d\'appui, %d placées par les deux méthodes', count($rows), count($both)));
        $this->line('  calage robuste      : '.$stat(array_column($both, 'robust')));
        $this->line('  interpolation naïve : '.$stat(array_column($both, 'naive')));
        $this->line(sprintf('  placées par le calage robuste seulement : %d ; par l\'interpolation naïve seulement : %d',
            count(array_filter($rows, fn ($r) => $r['robust'] !== null && $r['naive'] === null)),
            count(array_filter($rows, fn ($r) => $r['robust'] === null && $r['naive'] !== null))));
        if ($path = $this->option('report')) {
            @mkdir(dirname($path), 0775, true);
            $f = fopen($path, 'w');
            if ($f !== false) {
                fputcsv($f, ['ligne_id', 'gare', 'pk_m', 'ecart_robuste_m', 'ecart_naif_m'], escape: '');
                foreach ($rows as $r) {
                    fputcsv($f, CsvSafe::row($r), escape: '');
                }
                fclose($f);
                $this->line("  CSV : {$path}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<ListedPlacement>  $moved
     * @return array<int, array{from: array{0: float, 1: float}, moved_m: int}> gares déplacées
     */
    private function write(array $moved): array
    {
        foreach ($moved as $p) {
            DB::update(<<<'SQL'
                UPDATE station_sources SET geom = ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326),
                       properties = COALESCE(properties, '{}'::jsonb) || jsonb_build_object(
                           'geocoding', 'pk', 'precision_m', ?::int, 'placement', ?::text, 'calibration', ?::text,
                           'recalage_pk', jsonb_build_object('from', jsonb_build_array(?::float, ?::float),
                               'precision_m', ?::int, 'placement', properties->>'placement', 'moved_m', ?::int)),
                       updated_at = NOW()
                WHERE id = ?
            SQL, [$p->x, $p->y, $p->precisionM, $p->note, $p->calibration, $p->oldLon, $p->oldLat, $p->oldPrecisionM, $p->movedM, $p->sourceId]);
        }

        // Position des gares dont la position vient d'une liste : la plus précise de leurs gares de liste.
        $ids = array_values(array_unique(array_map(fn (ListedPlacement $p) => $p->stationId, $moved)));
        if ($ids === []) {
            return [];
        }
        $rows = DB::select(<<<'SQL'
            SELECT DISTINCT ON (s.id) s.id, ss.id AS source_id, ss.source, ss.source_ref, (ss.properties->>'precision_m')::int AS precision_m,
                   ST_X(s.geom) AS old_lon, ST_Y(s.geom) AS old_lat, ST_X(ss.geom) AS lon, ST_Y(ss.geom) AS lat,
                   ROUND(ST_Distance(s.geom::geography, ss.geom::geography))::int AS moved, (ss.properties->>'line_id')::bigint AS line_id
            FROM stations s JOIN station_sources ss ON ss.station_id = s.id AND ss.source IN ('wikipedia_schema', 'lignes-oubliees')
            WHERE s.id = ANY(?::bigint[]) AND s.position_source IN ('wikipedia_schema', 'lignes-oubliees')
              AND (ss.properties->>'precision_m') ~ '^\d+$'
            ORDER BY s.id, (ss.properties->>'precision_m')::int, ss.id
        SQL, ['{'.implode(',', $ids).'}']);
        $out = [];
        foreach ($rows as $r) {
            if ($r->moved < 1) {
                continue;
            }
            DB::update(<<<'SQL'
                UPDATE stations s SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326), position_source = ?,
                       provenance = COALESCE(s.provenance, '{}'::jsonb) || jsonb_build_object('geom', ?::text, 'geom_method', 'pk',
                           'geom_precision_m', ?::int, 'geom_recalage', jsonb_build_object('mode', 'pk_consensus',
                               'from', jsonb_build_array(?::float, ?::float), 'from_source', s.position_source,
                               'source_ref', ?::text, 'line_id', ?::bigint, 'moved_m', ?::int)),
                       position_spread_m = (SELECT COALESCE(MAX(ROUND(ST_Distance(x.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography))), 0)::int
                                            FROM station_sources x WHERE x.station_id = s.id),
                       updated_at = NOW()
                WHERE id = ?
            SQL, [$r->lon, $r->lat, $r->source, $r->source, $r->precision_m, $r->old_lon, $r->old_lat, $r->source_ref, $r->line_id, $r->moved,
                $r->lon, $r->lat, $r->id]);
            DB::update('UPDATE stations SET position_flagged = position_spread_m > 100 WHERE id = ?', [$r->id]);
            $out[(int) $r->id] = ['from' => [(float) $r->old_lon, (float) $r->old_lat], 'moved_m' => (int) $r->moved];
        }

        return $out;
    }

    /**
     * Lignes du CSV d'une simulation : une par gare de liste replacée.
     *
     * @param  list<ListedPlacement>  $moved
     * @return list<list<scalar|null>>
     */
    private function proposals(array $moved): array
    {
        $rows = [];
        foreach ($moved as $p) {
            $g = DB::selectOne('SELECT ST_X(g) AS lon, ST_Y(g) AS lat FROM ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326) g', [$p->x, $p->y]);
            $rows[] = [$p->stationId, $p->name, $p->lineId, $p->pk, round($p->oldLon, 7), round($p->oldLat, 7), round((float) $g->lon, 7), round((float) $g->lat, 7),
                $p->movedM, $p->oldPrecisionM, $p->precisionM, $p->note, $p->calibration];
        }

        return $rows;
    }

    /**
     * Lignes du CSV après écriture : une par gare (stations) déplacée.
     *
     * @param  list<ListedPlacement>  $moved
     * @param  array<int, array{from: array{0: float, 1: float}, moved_m: int}>  $stations
     * @return list<list<scalar|null>>
     */
    private function movedStations(array $moved, array $stations): array
    {
        $byStation = [];
        foreach ($moved as $p) {
            $byStation[$p->stationId] ??= $p;
        }
        $new = $stations ? DB::table('stations')->whereIn('id', array_keys($stations))->get(['id', DB::raw('ST_X(geom) AS lon'), DB::raw('ST_Y(geom) AS lat')])->keyBy('id') : collect();
        $rows = [];
        foreach ($stations as $id => $s) {
            $p = $byStation[$id];
            $rows[] = [$id, $p->name, $p->lineId, $p->pk, round($s['from'][0], 7), round($s['from'][1], 7),
                round((float) $new[$id]->lon, 7), round((float) $new[$id]->lat, 7), $s['moved_m'], $p->oldPrecisionM, $p->precisionM,
                $p->note, $p->calibration];
        }

        return $rows;
    }

    /** @param  list<list<scalar|null>>  $rows */
    private function writeReport(array $rows): void
    {
        $path = $this->option('report') ?: storage_path('app/reports/gares-recalage-pk.csv');
        @mkdir(dirname($path), 0775, true);
        $f = fopen($path, 'w');
        if ($f === false) {
            return;
        }
        fputcsv($f, ['station_id', 'gare', 'ligne_id', 'pk_m', 'ancienne_lon', 'ancienne_lat', 'nouvelle_lon', 'nouvelle_lat', 'deplacement_m',
            'precision_avant_m', 'precision_m', 'placement', 'calage'], escape: '');
        foreach ($rows as $r) {
            fputcsv($f, CsvSafe::row($r), escape: '');
        }
        fclose($f);
        $this->line("  CSV : {$path}");
    }
}
