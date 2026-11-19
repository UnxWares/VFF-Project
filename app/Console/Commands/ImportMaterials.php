<?php

namespace App\Console\Commands;

use App\Services\Materials\AttestedSeriesFinder;
use App\Services\Materials\LineMaterialLinker;
use App\Services\Materials\MaterialImporter;
use App\Services\Materials\SourceCache;
use App\Services\Materials\WikidataMaterialMapper;
use App\Services\Materials\WikidataMaterialRules;
use App\Services\Materials\WikidataMaterialSource;
use App\Services\Materials\WikipediaArticleSource;
use App\Services\Media\CommonsClient;
use App\Services\WikimediaClient;
use Illuminate\Console\Command;

/**
 * Matériel roulant (phase 13) : fiches des séries ayant circulé en France, lues dans Wikidata
 * (CC0) avec leur image Commons (licence par fichier), puis liens matériel ↔ ligne attestés par
 * Wikidata ou par les articles Wikipédia (sections « Matériel roulant », « Utilisation »…).
 *
 * Séries que Wikidata ne rattache pas à la France (ICE, AVE, locomotives belges…) : ajoutées seulement
 * si une source atteste leur circulation sur une ligne française (AttestedSeriesFinder), avec cette
 * source en provenance (`scope` = attested).
 *
 * Idempotente : une relance met à jour les fiches et les liens importés, sans jamais écraser une
 * valeur saisie dans VFF ni toucher un lien saisi à la main. Cache local des réponses dans
 * storage/app/data/materiel/ : une relance ne redemande aux serveurs que ce qui manque.
 *
 *   vff:import-materials                 # fiches puis liens
 *   vff:import-materials --refresh       # redemande tout aux sources
 *   vff:import-materials --links-only    # liens seulement (fiches déjà importées)
 *   vff:import-materials --no-attested   # sans les séries non rattachées à la France
 *   vff:import-materials --dry-run       # lit et compte, sans écrire en base
 */
class ImportMaterials extends Command
{
    protected $signature = 'vff:import-materials
        {--refresh : Ignore le cache local et redemande tout aux sources}
        {--links-only : Ne recalcule que les liens matériel ↔ ligne}
        {--no-links : N\'importe que les fiches}
        {--no-attested : N\'ajoute pas les séries que Wikidata ne rattache pas à la France}
        {--dry-run : Lit les sources et compte, sans écrire en base}';

    /** Une seconde au moins entre deux appels aux serveurs Wikimedia. */
    private const MIN_INTERVAL_MS = 1000;

    protected $description = 'Matériel roulant : fiches Wikidata (CC0) et images Commons, liens avec les lignes attestés par Wikidata ou Wikipédia';

    public function handle(): int
    {
        $refresh = (bool) $this->option('refresh');
        $dryRun = (bool) $this->option('dry-run');
        $cache = $this->laravel->bound(SourceCache::class) ? $this->laravel->make(SourceCache::class) : SourceCache::default();
        $rules = WikidataMaterialRules::default();
        $source = new WikidataMaterialSource(new CommonsClient(minIntervalMs: self::MIN_INTERVAL_MS), $cache, $rules);
        $wikipedia = new WikipediaArticleSource(app(WikimediaClient::class), $cache);

        if (! $this->option('links-only')) {
            $this->importMaterials($source, $wikipedia, $rules, $cache, $refresh, $dryRun);
        }
        if (! $this->option('no-links')) {
            $linker = new LineMaterialLinker($source, $wikipedia);
            $links = $linker->collect($refresh);
            $byKind = [];
            foreach ($links as $link) {
                foreach (array_unique(array_column($link['evidence'], 'kind')) as $kind) {
                    $byKind[$kind] = ($byKind[$kind] ?? 0) + 1;
                }
            }
            $this->info(count($links).' lien(s) matériel ↔ ligne attesté(s).');
            $this->table(['preuve', 'liens'], collect($byKind)->map(fn (int $n, string $k): array => [$k, $n])->values()->all());
            if (! $dryRun) {
                $stats = $linker->write($links);
                $this->info("Liens : {$stats['created']} créé(s), {$stats['updated']} mis à jour, {$stats['removed']} retiré(s).");
            }
        }

        return self::SUCCESS;
    }

    private function importMaterials(WikidataMaterialSource $source, WikipediaArticleSource $wikipedia, WikidataMaterialRules $rules, SourceCache $cache, bool $refresh, bool $dryRun): void
    {
        $qids = $source->candidates($refresh);
        $this->info(count($qids).' série(s) candidate(s) dans Wikidata.');
        $attested = [];
        if (! $this->option('no-attested')) {
            $attested = (new AttestedSeriesFinder($source, $wikipedia))->find($qids, $refresh);
            $this->info(count($attested).' série(s) non rattachée(s) à la France mais attestée(s) sur une ligne française.');
            $qids = array_values(array_unique([...$qids, ...array_keys($attested)]));
        }
        $ancestors = $source->ancestors($qids, $refresh);
        $entities = $source->entities($qids, $refresh);

        $referenced = [];
        $gauges = [];
        $files = [];
        foreach ($entities as $entity) {
            if ($entity === null) {
                continue;
            }
            $referenced = [...$referenced, ...WikidataMaterialMapper::referencedIds($entity)];
            $gauges = [...$gauges, ...WikidataMaterialMapper::gaugeIds($entity)];
            if (($file = WikidataMaterialMapper::imageFile($entity)) !== null) {
                $files[] = $file;
            }
        }
        $labels = $source->labels(array_values(array_unique($referenced)), $refresh);
        $widths = $source->gaugeWidths(array_values(array_unique($gauges)), $refresh);
        $images = $source->commonsFiles(array_values(array_unique($files)), $refresh);

        // Séries que Wikidata ne range dans aucune classe : catégories de leur article Wikipédia.
        $unranked = [];
        foreach ($qids as $qid) {
            $title = $entities[$qid]['sitelinks']['frwiki'] ?? null;
            if (is_string($title) && $rules->typeFor($ancestors[$qid] ?? []) === null) {
                $unranked[$qid] = $title;
            }
        }
        $categories = $wikipedia->categories('wikipedia_categories', array_values($unranked), $refresh);
        // Infobox des articles (catégorie « bimode », effectif en service).
        $articles = [];
        foreach ($qids as $qid) {
            if (is_string($title = $entities[$qid]['sitelinks']['frwiki'] ?? null)) {
                $articles[$qid] = $title;
            }
        }
        $infoboxes = $wikipedia->infoboxes('wikipedia_infoboxes', array_values($articles), $refresh);
        // Un article partagé par plusieurs séries (Z 55500 et Z 56300 redirigent vers « Regio 2N ») donne
        // l'effectif de la famille, pas celui de la série : on compte par titre final, redirections suivies.
        $finalTitles = array_count_values(array_filter(array_map(fn (string $t): ?string => $infoboxes[$t]['title'] ?? null, $articles)));
        foreach ($infoboxes as $requested => $infobox) {
            if ($infobox !== null && ($finalTitles[$infobox['title']] ?? 0) > 1) {
                $infoboxes[$requested]['shared'] = true;
            }
        }

        $mapper = new WikidataMaterialMapper($rules);
        $records = [];
        $skipped = [];
        foreach ($qids as $qid) {
            $result = $mapper->map($qid, $entities[$qid] ?? null, $ancestors[$qid] ?? [], $labels, $widths, $images,
                isset($unranked[$qid]) ? ($categories[$unranked[$qid]] ?? []) : [],
                isset($articles[$qid]) ? ($infoboxes[$articles[$qid]] ?? null) : null, $attested[$qid] ?? null);
            if (is_string($result)) {
                $skipped[$result][] = $qid.' '.($entities[$qid]['labels']['fr'] ?? $entities[$qid]['labels']['en'] ?? '');

                continue;
            }
            $records[] = $result;
        }
        // Séries écartées, par motif : à relire (catégorie à ajouter dans materiel_wikidata.php ?).
        $cache->save('skipped', $skipped);

        $this->info(count($records).' fiche(s) retenue(s).');
        $this->table(['motif d\'exclusion', 'séries'], collect($skipped)->map(fn (array $q, string $k): array => [$k, count($q)])->values()->all());
        $this->table(['catégorie', 'fiches'], collect($records)->countBy('type')->map(fn (int $n, string $t): array => [$t, $n])->values()->all());

        if (! $dryRun) {
            $stats = (new MaterialImporter)->import($records);
            $this->info("Fiches : {$stats['created']} créée(s), {$stats['updated']} mise(s) à jour, {$stats['unchanged']} inchangée(s).");
        }
    }
}
