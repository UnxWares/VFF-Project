<?php

namespace App\Console\Commands;

use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\CatalogueSruClient;
use App\Services\Archives\LinePlaceIndex;
use App\Services\Archives\LinePlaces;
use App\Services\Bibliography\BibliographyImporter;
use App\Services\Bibliography\LineAliases;
use App\Services\Bibliography\StationNames;
use App\Services\Bibliography\WikidataBooks;
use App\Services\StationArticleCache;
use App\Services\WikimediaClient;
use App\Support\CsvSafe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliographie des lignes et des gares → tables bibliography_entries et bibliography_links (section « Pour aller plus
 * loin » des fiches, GET /api/v1/lines/{slug}/bibliography et /api/v1/stations/{slug}/bibliography).
 *
 * Trois sources, au choix (--source) : `bnf` (livres du catalogue général de la BnF dont le titre nomme la ligne :
 * métadonnées sous Licence Ouverte, API SRU documentée), `wikipedia` (références {{Ouvrage}} et {{Article}} et
 * section « Bibliographie » de l'article de la ligne ou de la gare, wikitexte lu par l'API MediaWiki) et `wikidata`
 * (« décrit par » P1343 et « sujet principal » P921) ; les références de Wikipédia et de Wikidata qui ont un ISBN sont
 * ensuite cherchées dans le catalogue de la BnF (une requête pour quinze ISBN) pour lier leur notice. Aucune page de livre n'est lue : seulement les notices (auteurs,
 * titre, éditeur, année, ISBN) et le lien vers la notice. Une requête toutes les 5 secondes au plus (--interval), le
 * User-Agent du projet, cache disque des réponses (storage/app/data/bibliographie, storage/app/data/gallica) : une
 * relance reprend sans redemander les pages déjà lues. N'écrit dans aucune table du moteur.
 *
 * Les liens de chaque source traitée sont remplacés pour les lignes et les gares traitées ; une ligne dont une requête
 * échoue garde ses liens (la commande la nomme et se termine en erreur). `--dry-run` : tout est calculé, rien n'est
 * écrit (même sans la migration), `--report` écrit les rattachements jugés en CSV.
 *
 *   vff:import-bibliography --dry-run --source=wikipedia --from-cache --report=storage/app/reports/bibliographie.csv
 *   vff:import-bibliography --source=bnf --line=rfn-610000 --line=rfn-722000
 *   vff:import-bibliography --source=bnf --bnf-stations --station=limoges-benedictins
 */
class ImportBibliography extends Command
{
    protected $signature = 'vff:import-bibliography
        {--source=* : Source : bnf, wikipedia ou wikidata (option répétable ; les trois par défaut)}
        {--line=* : Slug d\'une ligne traitée (option répétable) ; toutes les lignes par défaut}
        {--station=* : Slug d\'une gare traitée (option répétable) ; toutes les gares ayant un article ou un élément Wikidata par défaut}
        {--no-stations : Ne traite aucune gare}
        {--no-isbn-lookup : Ne cherche pas dans le catalogue de la BnF les ISBN des références de Wikipédia et de Wikidata}
        {--bnf-stations : Interroge aussi le catalogue de la BnF pour les gares (une requête par gare : long)}
        {--limit= : Nombre de lignes (et de gares avec --bnf-stations) interrogées à la BnF au plus, dans l\'ordre de priorité}
        {--threshold= : Score minimal d\'une notice de la BnF rattachée à une ligne (9 par défaut, sur 11)}
        {--interval=5000 : Millisecondes entre deux requêtes au même serveur (5000 au moins)}
        {--offline : N\'utilise que les caches locaux, aucune requête réseau (la BnF et Wikidata)}
        {--from-cache : Pour Wikipédia, n\'utilise que les articles en cache, aucun téléchargement}
        {--refresh : Ignore les caches et relit les sources (les caches sont réécrits)}
        {--dry-run : Calcule tout sans écrire en base}
        {--report= : Fichier CSV de tous les rattachements retenus}';

    protected $description = 'Rattache aux lignes et aux gares les ouvrages et articles qui en traitent (notices de la BnF, références de Wikipédia, Wikidata)';

    private const SOURCES = ['bnf', 'wikipedia', 'wikidata'];

    public function handle(): int
    {
        $sources = array_values(array_filter((array) $this->option('source')));
        $sources = $sources === [] ? self::SOURCES : $sources;
        if (array_diff($sources, self::SOURCES) !== []) {
            $this->error('Source inconnue : '.implode(', ', array_diff($sources, self::SOURCES)).' (bnf, wikipedia, wikidata).');

            return self::INVALID;
        }
        $threshold = $this->option('threshold') === null ? ArchiveMatcher::THRESHOLD : (int) $this->option('threshold');
        if ($threshold < 1 || $threshold > ArchiveMatcher::MAX_SCORE) {
            $this->error('Seuil invalide : de 1 à 11.');

            return self::INVALID;
        }
        $interval = (int) $this->option('interval');
        if ($interval < 5000) {
            $this->error('Intervalle invalide : 5000 millisecondes au moins entre deux requêtes.');

            return self::INVALID;
        }
        $dry = (bool) $this->option('dry-run');
        if (! $dry && ! Schema::hasTable('bibliography_links')) {
            $this->error('Tables absentes : appliquez la migration 2026_12_17_000000_create_bibliography_tables (php artisan migrate --path=database/migrations/2026_12_17_000000_create_bibliography_tables.php).');

            return self::FAILURE;
        }

        $index = LinePlaceIndex::load();
        $aliases = LineAliases::load($index->keys());
        $stations = StationNames::load();
        $titles = BibliographyImporter::articleTitles();

        $lineSlugs = array_values(array_filter((array) $this->option('line')));
        $lines = $this->selectLines($index, $aliases, $titles['lines'], $lineSlugs);
        if ($lines === null) {
            return self::INVALID;
        }
        $stationIds = $this->selectStations(array_values(array_filter((array) $this->option('station'))), $titles['stations']);
        if ($stationIds === null) {
            return self::INVALID;
        }
        if ($this->option('no-stations')) {
            $stationIds = [];
        }
        $lineIds = array_map(fn (LinePlaces $l): int => $l->id, $lines);

        $importer = new BibliographyImporter;
        $candidates = [];
        $processed = [];
        $failures = 0;
        // Un seul client pour toute l'exécution : les requêtes au catalogue de la BnF restent espacées de 5 secondes au moins.
        $client = CatalogueSruClient::default((bool) $this->option('offline'), (bool) $this->option('refresh'), storage_path('app/data/bibliographie/bnf'), $interval);

        if (in_array('bnf', $sources, true)) {
            $limit = $this->option('limit') === null ? null : max(0, (int) $this->option('limit'));
            $queried = $limit === null ? $lines : array_slice($lines, 0, $limit);
            $bar = $this->output->createProgressBar(count($queried));
            $res = $importer->bnfLines($client, $index, $aliases, $queried, $threshold, fn () => $bar->advance());
            $bar->finish();
            $this->newLine();
            array_push($candidates, ...$res['candidates']);
            $done = array_values(array_diff(array_map(fn (LinePlaces $l): int => $l->id, $queried), array_values($res['failed'])));
            $processed['bnf'] = ['lines' => $done, 'stations' => []];
            $s = $res['stats'];
            $this->info(sprintf('BnF, lignes : %d ; requêtes : %d (%d au serveur, %d dans le cache, %d absentes du cache) ; notices lues : %d ; rattachements : %d ; coupées à 200 notices : %d ; erreurs : %d.',
                $s['lines'], $s['queries'], $client->networkRequests, $client->cacheHits, $client->cacheMisses, $s['records'], $s['accepted'], $s['truncated'], count($res['errors'])));
            foreach (array_slice($res['errors'], 0, 20) as $e) {
                $this->warn($e);
            }
            $failures += count($res['failed']);

            if ($this->option('bnf-stations')) {
                $queriedStations = $limit === null ? $stationIds : array_slice($stationIds, 0, $limit);
                $bar = $this->output->createProgressBar(count($queriedStations));
                $sres = $importer->bnfStations($client, $stations, $queriedStations, fn () => $bar->advance());
                $bar->finish();
                $this->newLine();
                array_push($candidates, ...$sres['candidates']);
                $processed['bnf']['stations'] = array_values(array_diff($queriedStations, $sres['failed']));
                $this->info(sprintf('BnF, gares : %d ; requêtes : %d ; rattachements : %d ; erreurs : %d.', $sres['stats']['stations'], $sres['stats']['queries'], $sres['stats']['accepted'], count($sres['errors'])));
                foreach (array_slice($sres['errors'], 0, 20) as $e) {
                    $this->warn($e);
                }
                $failures += count($sres['failed']);
            }
        }

        if (in_array('wikipedia', $sources, true)) {
            $selectedLines = array_intersect_key($titles['lines'], array_flip($lineIds));
            $selectedStations = array_intersect_key($titles['stations'], array_flip($stationIds));
            $cache = new StationArticleCache(new WikimediaClient, $interval * 1000);
            $res = $importer->wikipedia($cache, $index, $aliases, $stations, $selectedLines, $selectedStations,
                (bool) $this->option('from-cache') || (bool) $this->option('offline'), (bool) $this->option('refresh'));
            array_push($candidates, ...$res['candidates']);
            $processed['wikipedia'] = $res['read'];
            $this->info(sprintf('Wikipédia : articles lus : %d ; références : %d ; rattachements : %d ; titres téléchargés : %d ; absents du cache : %d.',
                $res['articles'], $res['references'], count($res['candidates']), $res['fetched'], $res['uncached']));
        }

        if (in_array('wikidata', $sources, true)) {
            $subjects = array_filter(BibliographyImporter::wikidataSubjects(), fn (array $s): bool => $s['subject'] === 'line'
                ? in_array($s['id'], $lineIds, true) : in_array($s['id'], $stationIds, true));
            $res = $importer->wikidata(WikidataBooks::default((bool) $this->option('offline'), (bool) $this->option('refresh'), $interval), $subjects);
            if ($res['error'] !== null) {
                $this->warn('Wikidata : '.$res['error']);
                $failures++;
            } else {
                array_push($candidates, ...$res['candidates']);
                $processed['wikidata'] = ['lines' => $lineIds, 'stations' => $stationIds];
            }
            $this->info(sprintf('Wikidata : éléments : %d ; œuvres : %d ; rattachements : %d.', $res['stats']['items'], $res['stats']['works'], $res['stats']['accepted']));
        }

        if (! $this->option('no-isbn-lookup') && array_intersect($sources, ['wikipedia', 'wikidata']) !== []) {
            $res = $importer->enrichWithBnf($client, $candidates);
            $candidates = $res['candidates'];
            $this->info(sprintf('ISBN cherchés dans le catalogue de la BnF : %d ; notices trouvées : %d ; requêtes en erreur : %d.', $res['isbns'], $res['found'], count($res['errors'])));
            foreach (array_slice($res['errors'], 0, 5) as $e) {
                $this->warn($e);
            }
        }

        $candidates = $importer->mergeSameWork($candidates);
        $this->coverage($candidates, $lines, $stationIds);
        if (($path = $this->option('report')) !== null) {
            $this->writeReport((string) $path, $candidates, $index);
            $this->line("Rattachements : {$path}");
        }

        if ($dry) {
            $this->line('Essai (--dry-run) : rien n\'est écrit.');

            return $failures === 0 ? self::SUCCESS : self::FAILURE;
        }
        $stored = $importer->store($candidates, $processed);
        $this->info("Liens enregistrés : {$stored['links']} ; références en base : {$stored['entries_before']} avant, {$stored['entries_after']} après ; liens en base : {$stored['links_after']}.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Lignes traitées : celles qui ont un article Wikipédia d'abord (les plus documentées), puis les autres, dans l'ordre
     * des identifiants ; une ligne sans couple d'extrémités ni nom d'usage n'a rien à chercher à la BnF mais garde son article.
     *
     * @param  array<int, list<string>>  $articleTitles
     * @param  list<string>  $slugs
     * @return list<LinePlaces>|null null si un slug est inconnu
     */
    private function selectLines(LinePlaceIndex $index, LineAliases $aliases, array $articleTitles, array $slugs): ?array
    {
        $all = array_values($index->lines);
        if ($slugs !== []) {
            $lines = array_values(array_filter($all, fn (LinePlaces $l): bool => in_array($l->slug, $slugs, true)));
            if (count($lines) !== count(array_unique($slugs))) {
                $this->error('Ligne inconnue : '.implode(', ', array_diff($slugs, array_map(fn (LinePlaces $l): string => $l->slug, $lines))));

                return null;
            }

            return $lines;
        }
        $useful = array_values(array_filter($all, fn (LinePlaces $l): bool => $l->queryPairs !== [] || $aliases->for($l->id) !== [] || isset($articleTitles[$l->id])));
        usort($useful, fn (LinePlaces $a, LinePlaces $b): int => [isset($articleTitles[$b->id]) ? 1 : 0, $a->id] <=> [isset($articleTitles[$a->id]) ? 1 : 0, $b->id]);

        return $useful;
    }

    /**
     * @param  list<string>  $slugs
     * @param  array<int, list<string>>  $articleTitles
     * @return list<int>|null
     */
    private function selectStations(array $slugs, array $articleTitles): ?array
    {
        if ($slugs !== []) {
            $ids = DB::table('stations')->whereIn('slug', $slugs)->pluck('id')->map(fn ($v): int => (int) $v)->all();
            if (count($ids) !== count(array_unique($slugs))) {
                $this->error('Gare inconnue parmi : '.implode(', ', $slugs));

                return null;
            }

            return $ids;
        }
        $withQid = DB::table('stations')->whereNotNull('wikidata_id')->pluck('id')->map(fn ($v): int => (int) $v)->all();
        $ids = array_values(array_unique([...array_keys($articleTitles), ...$withQid]));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<LinePlaces>  $lines
     * @param  list<int>  $stationIds
     */
    private function coverage(array $candidates, array $lines, array $stationIds): void
    {
        $withLine = [];
        $withStation = [];
        $entries = [];
        foreach ($candidates as $c) {
            if ($c['subject'] === 'line') {
                $withLine[$c['id']] = true;
            } else {
                $withStation[$c['id']] = true;
            }
            $entries[$c['record']->dedupKey()] = true;
        }
        $this->info(sprintf('Couverture : %d ligne(s) sur %d traitée(s) avec au moins une référence ; %d gare(s) sur %d ; %d référence(s) distincte(s), %d rattachement(s).',
            count($withLine), count($lines), count($withStation), count($stationIds), count($entries), count($candidates)));
    }

    /** @param  list<array<string, mixed>>  $candidates */
    private function writeReport(string $path, array $candidates, LinePlaceIndex $index): void
    {
        $stationSlugs = DB::table('stations')->pluck('slug', 'id')->all();
        File::ensureDirectoryExists(dirname($path));
        $out = fopen($path, 'w');
        if ($out === false) {
            $this->error("Impossible d'écrire {$path}");

            return;
        }
        fputcsv($out, ['subject', 'slug', 'provider', 'basis', 'score', 'year', 'kind', 'title', 'authors', 'publisher', 'isbn', 'journal', 'url'], escape: '');
        foreach ($candidates as $c) {
            $r = $c['record'];
            $slug = $c['subject'] === 'line' ? $index->line($c['id'])?->slug : ($stationSlugs[$c['id']] ?? null);
            fputcsv($out, CsvSafe::row([$c['subject'], $slug, $c['provider'], $c['basis'], $c['score'], $r->year, $r->kind, $r->title,
                implode(' ; ', $r->authors), $r->publisher, $r->isbn, $r->journal, $r->url]), escape: '');
        }
        fclose($out);
    }
}
