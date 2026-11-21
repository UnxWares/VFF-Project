<?php

namespace App\Console\Commands;

use App\Services\StationAudit\DateComparison;
use App\Services\StationAudit\SourceRank;
use App\Services\StationAudit\StationScope;
use App\Services\StationAudit\WikidataStationDates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dates candidates des gares lues dans Wikidata (CC0) : mise en service (P1619) et fermeture
 * (P3999), avec précision, rang, qualificatifs (s'applique à, circonstances, au plus tôt, au plus
 * tard) et références. Éléments : stations.wikidata_id et les éléments Wikidata rattachés à la gare
 * dans station_sources.
 *
 * Chaque date est comparée à celle que VFF publie (DateComparison) et rangée dans
 * `station_date_candidates` : AUCUNE date du moteur n'est modifiée. Le bilan donne, avec la règle de
 * rang proposée (SourceRank), le nombre de gares dont la date changerait.
 *
 * Date de VFF comparée : opening → stations.opened_at ; closure → stations.closed_at ;
 * closure_passenger → événement closure_passenger (sourcé d'abord), sinon stations.closed_at ;
 * closure_freight, reopening → événement de même type. Gare fret seul : closure se compare à la fin des
 * voyageurs. Gare ouverte aux voyageurs (active, tourist) : une fermeture n'est comparée à rien
 * (`station_open`).
 *
 *   php artisan vff:import-station-wikidata-dates --line=405000 --dry-run
 *   php artisan vff:import-station-wikidata-dates --from-cache
 */
class ImportStationWikidataDates extends Command
{
    protected $signature = 'vff:import-station-wikidata-dates
        {--station=* : Gare (slug ou identifiant)}
        {--line=* : Ligne (code, slug ou identifiant) : toutes ses gares}
        {--from-cache : Aucune requête : seulement les éléments déjà en cache}
        {--refresh : Redemande tous les éléments à Wikidata}
        {--dry-run : Lit et compare sans rien écrire}';

    /** Statuts d'une gare ouverte aux voyageurs (stations.status). */
    private const OPEN = ['active', 'tourist'];

    protected $description = 'Dates de mise en service et de fermeture des gares dans Wikidata (P1619, P3999) → station_date_candidates, comparées aux dates de VFF';

    public function handle(WikidataStationDates $reader): int
    {
        try {
            $ids = StationScope::resolve(array_map('strval', (array) $this->option('station')), array_map('strval', (array) $this->option('line')), null);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }
        $restricted = $this->option('station') !== [] || $this->option('line') !== [];
        $pairs = $this->pairs($restricted ? $ids : null);
        $qids = array_values(array_unique(array_column($pairs, 'qid')));
        $this->line(sprintf('%d élément(s) Wikidata pour %d gare(s)', count($qids), count(array_unique(array_column($pairs, 'station_id')))));

        $claims = $reader->claims($qids, (bool) $this->option('from-cache'), (bool) $this->option('refresh'));
        // Cache vide ou absent, ou Wikidata injoignable : ne jamais remplacer les candidats par un ensemble vide.
        if ($qids !== [] && $claims === []) {
            $this->error('aucun élément Wikidata relu (cache vide ou absent, ou Wikidata injoignable) : station_date_candidates n\'est pas modifiée');

            return self::FAILURE;
        }
        // Gares dont un élément n'a pas été relu : leurs candidats actuels sont gardés tels quels.
        $unread = [];
        foreach ($pairs as $pair) {
            if (! is_array($claims[$pair['qid']] ?? null)) {
                $unread[$pair['station_id']] = true;
            }
        }
        $keep = $unread;
        $unread = array_keys($unread);

        $vff = $this->vffDates(array_values(array_unique(array_column($pairs, 'station_id'))));
        $now = now();
        $rows = [];
        foreach ($pairs as $pair) {
            $entity = $claims[$pair['qid']] ?? null;
            // Gare dont un autre élément n'a pas été relu : rien n'est réécrit pour elle.
            if (! is_array($entity) || isset($keep[$pair['station_id']])) {
                continue;
            }
            foreach (WikidataStationDates::parse($pair['qid'], $entity) as $c) {
                $current = $vff[$pair['station_id']][$c['kind']] ?? null;
                // Gare ouverte aux voyageurs aujourd'hui : une « fermeture » Wikidata y est une coupure,
                // une fermeture d'un bâtiment ou une erreur ; elle ne date rien (comparaison `station_open`).
                $cmp = str_starts_with($c['kind'], 'closure') && in_array($vff[$pair['station_id']]['status'] ?? null, self::OPEN, true)
                    ? ['comparison' => 'station_open', 'delta_days' => null]
                    : DateComparison::compare($c['date'], $c['precision'], $current['date'] ?? null, $current['precision'] ?? null);
                $rows[$pair['station_id'].'|'.$c['kind'].'|'.$c['source_ref']] = [
                    'station_id' => $pair['station_id'],
                    'kind' => $c['kind'],
                    'date' => $c['date'],
                    'precision' => $c['precision'],
                    'source' => 'wikidata',
                    'source_ref' => $c['source_ref'],
                    'source_url' => $c['source_url'],
                    'rank' => $c['rank'],
                    'qualifiers' => json_encode((object) $c['qualifiers'], JSON_UNESCAPED_UNICODE),
                    'references' => json_encode($c['references'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'vff_date' => $current['date'] ?? null,
                    'vff_precision' => $current['precision'] ?? null,
                    'vff_source' => $current['source'] ?? null,
                    'comparison' => $cmp['comparison'],
                    'delta_days' => $cmp['delta_days'],
                    'fetched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $rows = array_values($rows);
        $this->summary($rows);

        if ($this->option('dry-run')) {
            $this->warn('--dry-run : rien n\'est écrit dans station_date_candidates');

            return self::SUCCESS;
        }
        if ($unread !== []) {
            $this->warn(sprintf('%d gare(s) dont un élément n\'a pas été relu : leurs dates candidates sont gardées', count($unread)));
        }
        DB::transaction(function () use ($rows, $restricted, $ids, $unread): void {
            $delete = DB::table('station_date_candidates')->where('source', 'wikidata');
            if ($restricted) {
                $delete->whereIn('station_id', $ids);
            }
            if ($unread !== []) {
                $delete->whereNotIn('station_id', $unread);
            }
            $delete->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('station_date_candidates')->insert($chunk);
            }
        });
        $this->info(sprintf('%d date(s) candidate(s) enregistrée(s)', count($rows)));

        return self::SUCCESS;
    }

    /**
     * Couples gare × élément Wikidata (identifiant de la gare, puis éléments de ses sources).
     *
     * @param  list<int>|null  $ids
     * @return list<array{station_id: int, qid: string}>
     */
    private function pairs(?array $ids): array
    {
        $in = $ids === null ? null : '{'.implode(',', $ids).'}';
        $where = $in === null ? '' : 'AND station_id = ANY(?::bigint[])';
        $rows = DB::select(<<<SQL
            SELECT DISTINCT station_id, qid FROM (
              SELECT id AS station_id, wikidata_id AS qid FROM stations WHERE wikidata_id ~ '^Q[0-9]+$'
              UNION
              SELECT station_id, source_ref FROM station_sources WHERE source = 'wikidata' AND station_id IS NOT NULL AND source_ref ~ '^Q[0-9]+$'
            ) p WHERE true {$where} ORDER BY station_id, qid
            SQL, $in === null ? [] : [$in]);

        return array_map(fn (object $r): array => ['station_id' => (int) $r->station_id, 'qid' => (string) $r->qid], $rows);
    }

    /**
     * Dates publiées par VFF, par gare et par type.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>> par gare : status, et par type {date, precision, source}
     */
    private function vffDates(array $ids): array
    {
        $out = [];
        $freightOnly = [];
        foreach (array_chunk($ids, 2000) as $chunk) {
            $in = '{'.implode(',', $chunk).'}';
            foreach (DB::select(<<<'SQL'
                SELECT id, status, opened_at::text AS opened_at, opened_precision, provenance->>'opened_at' AS opened_source,
                       closed_at::text AS closed_at, closed_precision, provenance->>'closed_at' AS closed_source
                FROM stations WHERE id = ANY(?::bigint[])
                SQL, [$in]) as $s) {
                $out[(int) $s->id]['status'] = $s->status;
                if ($s->opened_at !== null) {
                    $out[(int) $s->id]['opening'] = ['date' => $s->opened_at, 'precision' => $s->opened_precision, 'source' => $s->opened_source];
                }
                if ($s->closed_at !== null) {
                    $out[(int) $s->id]['closure'] = ['date' => $s->closed_at, 'precision' => $s->closed_precision, 'source' => $s->closed_source];
                    $out[(int) $s->id]['closure_passenger'] = $out[(int) $s->id]['closure'];
                }
                $freightOnly[(int) $s->id] = $s->status === 'no_commercial';
            }
            foreach (DB::select(<<<'SQL'
                SELECT DISTINCT ON (station_id, type) station_id, type, event_date::text AS date, date_precision, source
                FROM station_events
                WHERE station_id = ANY(?::bigint[]) AND type IN ('closure_passenger', 'closure_freight', 'reopening')
                ORDER BY station_id, type, is_inferred, event_date
                SQL, [$in]) as $e) {
                $out[(int) $e->station_id][$e->type] = ['date' => $e->date, 'precision' => $e->date_precision, 'source' => $e->source];
                // Gare fret seul : sa « fermeture » Wikidata se compare à la fin des voyageurs.
                if ($e->type === 'closure_passenger' && ($freightOnly[(int) $e->station_id] ?? false)) {
                    $out[(int) $e->station_id]['closure'] = $out[(int) $e->station_id][$e->type];
                }
            }
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $rows */
    private function summary(array $rows): void
    {
        $by = [];
        $stations = [];
        foreach ($rows as $r) {
            $by[$r['kind']][$r['comparison']] = ($by[$r['kind']][$r['comparison']] ?? 0) + 1;
            if (SourceRank::wouldReplace($r['comparison'], 'wikidata', $r['vff_source'])) {
                $stations[$r['station_id']] = true;
            }
        }
        $kinds = ['same', 'more_precise', 'less_precise', 'differs', 'new', 'station_open'];
        $this->table(['Type', ...$kinds], array_map(fn (string $k): array => [$k, ...array_map(fn (string $c): int => $by[$k][$c] ?? 0, $kinds)], array_keys($by)));
        $this->line(sprintf('Règle de rang proposée : %d gare(s) changeraient au moins une date (rien n\'est remplacé).', count($stations)));
    }
}
