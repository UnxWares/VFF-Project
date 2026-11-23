<?php

namespace App\Console\Commands;

use App\Services\StationArticleCache;
use App\Services\StationNames\RenameExtractor;
use App\Services\StationNames\StationNameMerger;
use App\Services\StationNames\StationNameTimeline;
use App\Services\StationNames\WikidataNames;
use App\Services\WikimediaClient;
use App\Support\CsvSafe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Noms successifs des gares, chacun avec sa source, dans `stations.alt_names` (la fiche, la chronologie de la
 * gare et la recherche les lisent déjà ; aucune migration) :
 *
 *   - Wikipédia : phrases de l'article de la gare qui disent explicitement « renommée », « rebaptisée », « prend le
 *     nom de », « change de nom » avec l'année (RenameExtractor, source `wikipedia_history`, permalien oldid) ;
 *   - Wikidata : nom officiel (P1448) et nom (P2561) avec début (P580) et fin (P582) (source `wikidata_names`),
 *     lus dans le cache de vff:import-station-sources, sans requête ;
 *   - gares fermées dont le nom venait d'OpenStreetMap et désigne le commerce ou l'association qui occupe le bâtiment
 *     (La Recyclerie) : le nom ferroviaire sourcé devient le nom de la gare, le nom du lieu reste en autre nom
 *     (source `osm_place`, fichier database/data/station_place_names.php).
 *
 * Les entrées des autres sources d'alt_names ne sont jamais modifiées ; celles des trois sources ci-dessus sont
 * remplacées à chaque exécution. Les articles absents du cache sont demandés à Wikipédia (5 s entre deux requêtes,
 * 50 titres par requête, User-Agent du projet) sauf avec --from-cache.
 *
 *   php artisan vff:import-station-names --dry-run --csv=storage/app/data/gares-noms/bilan.csv
 *   php artisan vff:import-station-names --from-cache
 */
class ImportStationNames extends Command
{
    protected $signature = 'vff:import-station-names
        {--station=* : Gare (slug)}
        {--from-cache : Aucune requête : seulement les articles déjà en cache}
        {--refresh : Redemande les articles à Wikipédia}
        {--csv= : Écrit le détail (un nom par ligne) dans ce fichier}
        {--dry-run : Lit et compte sans rien écrire}';

    protected $description = 'Noms successifs des gares, datés quand une source le dit (Wikipédia, Wikidata) → stations.alt_names ; nom ferroviaire des gares fermées nommées d\'après leur commerce';

    public function handle(WikimediaClient $wiki): int
    {
        $slugs = array_map('strval', (array) $this->option('station'));
        $stations = $this->stations($slugs);
        $this->line(sprintf('%d gare(s) lue(s)', count($stations)));
        $places = $this->places();

        $byTitle = $this->titles(array_keys($stations));
        $cache = new StationArticleCache($wiki, 5_000_000);
        $res = $cache->pages(array_keys($byTitle), (bool) $this->option('from-cache'), (bool) $this->option('refresh'));
        $this->line(sprintf('%d article(s) liés, %d lus, %d téléchargés, %d absents du cache', count($byTitle), count($res['pages']), $res['fetched'], $res['uncached']));
        $claims = $this->wikidataClaims($stations);

        $stats = ['wikipedia' => 0, 'wikidata' => 0, 'places' => 0, 'changed' => 0, 'dated' => 0, 'with_past' => 0];
        $updates = [];
        $csv = [];
        foreach ($stations as $id => $st) {
            $candidates = [];
            $ownName = $st['name'];
            if (isset($places[$st['slug']])) {
                $p = $places[$st['slug']];
                // Seulement tant que le nom de la fiche est celui du lieu (ou déjà le nom ferroviaire).
                if (in_array(RenameExtractor::key($ownName), [RenameExtractor::key($p['place']), RenameExtractor::key($p['name'])], true)) {
                    $ownName = $p['name'];
                    $candidates[] = ['name' => $p['place'], 'from' => null, 'to' => null, 'source' => 'osm_place', 'source_url' => '',
                        'note' => 'Lieu qui occupe aujourd\'hui le bâtiment de la gare (nom OpenStreetMap)'];
                    $stats['places']++;
                }
            }
            $openedYear = $st['opened_at'] !== null ? (int) substr($st['opened_at'], 0, 4) : null;
            foreach ($byTitle as $title => $ids) {
                if (isset($res['pages'][$title]) && in_array($id, $ids, true)) {
                    $page = $res['pages'][$title];
                    $url = 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $page['title'])).'&oldid='.$page['revid'];
                    $entries = StationNameTimeline::fromEvents(RenameExtractor::extract($page['content']), $ownName, $openedYear, $url);
                    $stats['wikipedia'] += count($entries);
                    $candidates = array_merge($candidates, $entries);
                }
            }
            foreach ($st['qids'] as $qid) {
                if (isset($claims[$qid])) {
                    $entries = WikidataNames::parse($qid, $claims[$qid], $ownName);
                    $stats['wikidata'] += count($entries);
                    $candidates = array_merge($candidates, $entries);
                }
            }
            $merged = StationNameMerger::merge($st['alt_names'], $candidates, $ownName);
            $nameChanged = $ownName !== $st['name'];
            if ($merged !== $st['alt_names'] || $nameChanged) {
                $stats['changed']++;
                $updates[$id] = ['name' => $ownName, 'alt_names' => $merged, 'name_changed' => $nameChanged, 'source' => $places[$st['slug']]['source'] ?? null];
            }
            $own = array_filter($merged, fn ($a): bool => in_array($a['source'] ?? null, StationNameMerger::SOURCES, true));
            $datedOwn = array_filter($own, fn ($a): bool => ($a['from'] ?? null) !== null || ($a['to'] ?? null) !== null);
            $stats['dated'] += $datedOwn !== [] ? 1 : 0;
            foreach ($own as $a) {
                $csv[] = [$st['slug'], $ownName, $a['name'], $a['from'] ?? '', $a['to'] ?? '', $a['lang'] ?? '', $a['source'], $a['source_url'] ?? '', $a['quote'] ?? ($a['note'] ?? '')];
            }
        }
        // Gares dont un nom daté (de ces sources ou d'OHM) existe, pour la couverture.
        foreach ($stations as $id => $st) {
            $alt = $updates[$id]['alt_names'] ?? $st['alt_names'];
            $stats['with_past'] += array_filter($alt, fn ($a): bool => is_array($a) && (is_numeric($a['from'] ?? null) || is_numeric($a['to'] ?? null))) !== [] ? 1 : 0;
        }
        $this->table(['mesure', 'valeur'], [
            ['noms datés lus dans Wikipédia', $stats['wikipedia']], ['noms lus dans Wikidata (P1448, P2561)', $stats['wikidata']],
            ['gares dont le nom du lieu est remplacé par le nom ferroviaire', $stats['places']],
            ['gares dont alt_names change', $stats['changed']], ['gares avec un nom daté de ces sources', $stats['dated']],
            ['gares avec au moins un autre nom daté (toutes sources, après import)', $stats['with_past']],
        ]);
        if (is_string($this->option('csv')) && $this->option('csv') !== '') {
            File::ensureDirectoryExists(dirname((string) $this->option('csv')));
            $fh = fopen((string) $this->option('csv'), 'w');
            fputcsv($fh, ['slug', 'nom_fiche', 'autre_nom', 'de', 'a', 'langue', 'source', 'url', 'extrait'], ',', '"', '');
            foreach ($csv as $row) {
                fputcsv($fh, array_map(fn ($v) => CsvSafe::cell((string) $v), $row), ',', '"', '');
            }
            fclose($fh);
        }
        if ($this->option('dry-run')) {
            $this->warn('--dry-run : rien n\'est écrit dans stations');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($updates): void {
            foreach ($updates as $id => $u) {
                DB::table('stations')->where('id', $id)->update(['alt_names' => json_encode($u['alt_names'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now()]
                    + ($u['name_changed'] ? ['name' => $u['name']] : []));
                if ($u['name_changed']) {
                    DB::update("UPDATE stations SET provenance = jsonb_set(COALESCE(provenance, '{}'::jsonb), '{name}', to_jsonb(?::text)) WHERE id = ?", [(string) ($u['source'] ?? 'manual'), $id]);
                }
            }
        });
        $this->info(sprintf('%d gare(s) mise(s) à jour', count($updates)));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<int, array{slug: string, name: string, opened_at: ?string, alt_names: list<mixed>, qids: list<string>}>
     */
    private function stations(array $slugs): array
    {
        $rows = DB::table('stations')->when($slugs !== [], fn ($q) => $q->whereIn('slug', $slugs))->orderBy('id')
            ->get(['id', 'slug', 'name', 'opened_at', 'alt_names', 'wikidata_id']);
        $qids = DB::table('station_sources')->where('source', 'wikidata')->whereNotNull('station_id')
            ->get(['station_id', 'source_ref'])->groupBy('station_id');
        $out = [];
        foreach ($rows as $r) {
            $alt = json_decode((string) $r->alt_names, true);
            $q = array_values(array_unique(array_filter([$r->wikidata_id, ...($qids[$r->id] ?? collect())->pluck('source_ref')->all()],
                fn ($v): bool => is_string($v) && preg_match('/^Q\d+$/', $v) === 1)));
            $out[(int) $r->id] = ['slug' => $r->slug, 'name' => $r->name, 'opened_at' => $r->opened_at, 'alt_names' => is_array($alt) ? array_values($alt) : [], 'qids' => $q];
        }

        return $out;
    }

    /** @return array<string, array{slug: string, name: string, place: string, source: string, source_url: string}> */
    private function places(): array
    {
        $out = [];
        foreach ((array) require database_path('data/station_place_names.php') as $p) {
            $out[$p['slug']] = $p;
        }

        return $out;
    }

    /**
     * Articles frwiki liés aux gares (élément Wikidata, jamais par le nom) : titre → identifiants. Un article lié à
     * plusieurs gares n'est gardé que pour celle dont c'est l'élément principal.
     *
     * @param  list<int>  $ids
     * @return array<string, list<int>>
     */
    private function titles(array $ids): array
    {
        $rows = DB::table('station_sources as ss')->join('stations as s', 's.id', '=', 'ss.station_id')
            ->where('ss.source', 'wikidata')->whereNotNull('ss.properties->frwiki')->whereIn('s.id', $ids)
            ->get(['s.id', 'ss.properties', 'ss.source_ref', 's.wikidata_id']);
        $byTitle = [];
        foreach ($rows as $r) {
            $title = json_decode((string) $r->properties, true)['frwiki'] ?? null;
            if (is_string($title) && $title !== '') {
                $byTitle[$title][(int) $r->id] = $r->source_ref === $r->wikidata_id;
            }
        }
        $out = [];
        foreach ($byTitle as $title => $stations) {
            $main = array_keys(array_filter($stations));
            $out[$title] = count($stations) === 1 ? array_keys($stations) : (count($main) === 1 ? $main : []);
        }

        return array_filter($out);
    }

    /**
     * Déclarations P1448 et P2561 des éléments de ces gares, lues dans le cache disque de vff:import-station-sources.
     *
     * @param  array<int, array{qids: list<string>}>  $stations
     * @return array<string, array<string, mixed>>
     */
    private function wikidataClaims(array $stations): array
    {
        $need = [];
        foreach ($stations as $s) {
            foreach ($s['qids'] as $q) {
                $need[$q] = true;
            }
        }
        $out = [];
        foreach (glob(storage_path('app/sources/stations/wikidata/entities-*.json')) ?: [] as $file) {
            $json = json_decode((string) file_get_contents($file), true);
            foreach ((array) ($json['entities'] ?? []) as $qid => $entity) {
                if (isset($need[$qid]) && is_array($entity)) {
                    $c = array_intersect_key((array) ($entity['claims'] ?? []), ['P1448' => 1, 'P2561' => 1]);
                    if ($c !== []) {
                        $out[(string) $qid] = $c;
                    }
                }
            }
        }

        return $out;
    }
}
