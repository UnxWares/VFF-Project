<?php

namespace App\Console\Commands;

use App\Services\Media\CommonsClient;
use App\Services\Media\CommonsCoverage;
use App\Services\Media\CommonsMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cherche, sur Wikimedia Commons et Wikidata, des images libres pour les fiches qui n'en ont aucune
 * (gares, lignes, séries de matériel), sans changer de source ni de licence : lecture seule en base,
 * rien n'est écrit dans `media`. Le résultat est un fichier de candidats (une ligne JSON par fiche)
 * que `vff:import-commons-candidates` lit pour écrire.
 *
 * Rattachements, du plus sûr au moins sûr :
 *   1. image de référence de l'élément Wikidata (P18) ;
 *   2. catégorie Commons de l'élément (P373, lien commonswiki) ; une catégorie sans mot de gare ne
 *      retient que les fichiers qui parlent de chemin de fer ;
 *   3. gares sans élément Wikidata : catégorie « Gare de X » (ou « Halte », « Ancienne gare ») dont
 *      la position (coordonnées de la catégorie, sinon fichiers géolocalisés) tombe à moins de 500 m
 *      de la gare ; un homonyme lointain est écarté ;
 *   4. lignes sans élément Wikidata connu : élément retrouvé par le code RFN (P1671, recherche de
 *      l'API MediaWiki de Wikidata, jamais le service SPARQL fermé aux robots).
 *
 * Chaque fichier doit avoir une licence CC BY, CC BY-SA, CC0 ou domaine public et un auteur (ou un
 * crédit) ; un fichier géolocalisé à plus de 500 m de la gare est écarté (2 km pour la P18).
 *
 * Politesse : User-Agent du projet, une requête à la fois, lots de 50, `maxlag`, reprise sur 429.
 * Reprenable : relancer la commande avec le même fichier saute les fiches déjà traitées.
 *
 *   vff:discover-commons-candidates station --out=storage/app/sources/commons-candidates/stations.jsonl
 *   vff:discover-commons-candidates line --limit=200
 */
class DiscoverCommonsCandidates extends Command
{
    protected $signature = 'vff:discover-commons-candidates
        {subject : station, line ou material}
        {--out= : Fichier JSONL de candidats (défaut : storage/app/sources/commons-candidates/<sujet>s.jsonl, stations-geo.jsonl avec --geo)}
        {--limit= : Nombre maximal de fiches traitées (après celles déjà faites)}
        {--min-images=0 : Couverture d\'abord : ne lit pas les catégories d\'une fiche dont la P18 donne déjà ce nombre d\'images acceptables}
        {--max-per-subject=10 : Fichiers max retenus par fiche}
        {--geo : Gares seulement : pour celles sans candidat, fichiers géolocalisés à moins de 250 m dont le titre parle de gare et nomme le lieu}
        {--skip-name-search : Gares sans Wikidata : ne cherche pas de catégorie par le nom}
        {--only= : Slug(s) à traiter, séparés par des virgules}';

    protected $description = 'Candidats Commons (P18, P373, catégorie par le nom) pour les fiches sans image : lecture seule, écrit un fichier JSONL';

    /** Fichiers lus par catégorie : l'extmetadata de Commons est lent à froid (environ 0,1 s par fichier). */
    private const CATEGORY_FILES = 8;

    private const SUBCATEGORY_FILES = 5;

    /** Rayon de la recherche géographique autour d'une gare (m). */
    private const GEO_RADIUS_M = 250;

    private CommonsClient $client;

    /** @var array<string, int> */
    private array $stats = [];

    private string $subject = 'station';

    private int $maxPer = 10;

    /** @var resource */
    private $out;

    public function handle(): int
    {
        $this->subject = (string) $this->argument('subject');
        $class = CommonsCoverage::modelClass($this->subject);
        if ($class === null) {
            $this->error('Sujet inconnu : station, line ou material.');

            return self::INVALID;
        }
        $this->maxPer = max(1, (int) $this->option('max-per-subject'));
        $path = (string) ($this->option('out') ?: storage_path("app/sources/commons-candidates/{$this->subject}".($this->option('geo') ? 's-geo' : 's').'.jsonl'));
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $done = $this->doneSlugs($path);
        if ($this->option('geo')) {
            if ($this->subject !== 'station') {
                $this->error('--geo ne vaut que pour les gares.');

                return self::INVALID;
            }
            // Gares déjà pourvues d'au moins un candidat par les autres méthodes : laissées de côté.
            $done += $this->doneSlugs(dirname($path).'/stations.jsonl', true);
        }
        $records = array_values(array_filter($this->subjects(), fn (array $r) => ! isset($done[$r['slug']])));
        if ($this->option('limit')) {
            $records = array_slice($records, 0, (int) $this->option('limit'));
        }
        $this->info(count($records).' fiche(s) à traiter ('.count($done).' déjà faites).');
        if ($records === []) {
            return self::SUCCESS;
        }

        $this->client = new CommonsClient;
        $this->out = fopen($path, 'ab');
        if ($this->option('geo')) {
            foreach ($records as $r) {
                $this->safely(fn () => $this->processGeoStation($r), [$r]);
            }
            fclose($this->out);
            ksort($this->stats);
            $this->info('Terminé : '.json_encode($this->stats).' ; '.$this->client->requestCount.' requête(s), '.$this->client->retryCount.' reprise(s).');

            return self::SUCCESS;
        }
        $withQid = array_values(array_filter($records, fn (array $r) => $r['qid'] !== null));
        $noQid = array_values(array_filter($records, fn (array $r) => $r['qid'] === null));

        foreach (array_chunk($withQid, 50) as $chunk) {
            $this->safely(fn () => $this->processWikidataChunk($chunk), $chunk);
        }
        if ($this->subject === 'line') {
            $noQid = $this->resolveLineQids($noQid);
        }
        if ($this->subject === 'station' && ! $this->option('skip-name-search')) {
            foreach (array_chunk($noQid, 12) as $chunk) {
                $this->safely(fn () => $this->processNameChunk($chunk), $chunk);
            }
        } elseif ($this->subject === 'line') {
            foreach (array_chunk(array_filter($noQid, fn (array $r) => $r['qid'] === null), 50) as $chunk) {
                $this->safely(fn () => $this->processLineNameChunk($chunk), $chunk);
            }
        }
        fclose($this->out);

        ksort($this->stats);
        $this->info('Terminé : '.json_encode($this->stats).' ; '.$this->client->requestCount.' requête(s), '.$this->client->retryCount.' reprise(s).');

        return self::SUCCESS;
    }

    /**
     * @param  callable(): void  $work
     * @param  list<array<string, mixed>>  $chunk
     */
    private function safely(callable $work, array $chunk): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            $this->stats['erreurs_lots'] = ($this->stats['erreurs_lots'] ?? 0) + 1;
            $this->warn('Lot de '.count($chunk).' fiche(s) en échec : '.mb_strimwidth($e->getMessage(), 0, 160, '…'));
        }
    }

    /** @return array<string, true> */
    private function doneSlugs(string $path, bool $withFilesOnly = false): array
    {
        $done = [];
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
                $j = json_decode($l, true);
                if (is_array($j) && isset($j['slug']) && (! $withFilesOnly || ($j['files'] ?? []) !== [])) {
                    $done[$j['slug']] = true;
                }
            }
        }

        return $done;
    }

    /** @return list<array{id: int, slug: string, name: string, type: string, qid: ?string, lat: ?float, lon: ?float, code: ?string}> */
    private function subjects(): array
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $mediable = CommonsCoverage::modelClass($this->subject);
        $noMedia = fn (string $table) => "NOT EXISTS (SELECT 1 FROM media m WHERE m.mediable_type = ? AND m.mediable_id = {$table}.id AND m.provider = 'commons')";
        $onlySql = $only === [] ? '' : ' AND slug IN ('.implode(',', array_fill(0, count($only), '?')).')';
        $bind = [$mediable, ...$only];

        $rows = match ($this->subject) {
            'station' => DB::select("SELECT id, slug, name, type, wikidata_id AS qid, ST_Y(geom) AS lat, ST_X(geom) AS lon, NULL AS code FROM stations WHERE {$noMedia('stations')}{$onlySql} ORDER BY (wikidata_id IS NULL), (status <> 'active'), id", $bind),
            'line' => DB::select("SELECT id, slug, name, 'line' AS type, alt_names->>'wikidata' AS qid, NULL::float AS lat, NULL::float AS lon, code FROM lines WHERE {$noMedia('lines')}{$onlySql} ORDER BY (alt_names->>'wikidata' IS NULL), id", $bind),
            default => DB::select("SELECT id, slug, name, type, wikidata_id AS qid, NULL::float AS lat, NULL::float AS lon, NULL AS code FROM materials WHERE {$noMedia('materials')}{$onlySql} ORDER BY id", $bind),
        };

        return array_map(fn ($r) => [
            'id' => (int) $r->id, 'slug' => (string) $r->slug, 'name' => (string) $r->name, 'type' => (string) $r->type,
            'qid' => is_string($r->qid) && preg_match('/^Q\d+$/', $r->qid) ? $r->qid : null,
            'lat' => $r->lat !== null ? (float) $r->lat : null, 'lon' => $r->lon !== null ? (float) $r->lon : null,
            'code' => $r->code !== null ? (string) $r->code : null,
        ], $rows);
    }

    /** @param  list<array<string, mixed>>  $chunk */
    private function processWikidataChunk(array $chunk): void
    {
        $data = $this->client->wikidata([
            'action' => 'wbgetentities', 'ids' => implode('|', array_column($chunk, 'qid')),
            'props' => 'claims|sitelinks', 'sitefilter' => 'commonswiki',
        ]);
        $entities = $data['entities'] ?? [];
        $wd = [];
        $titles = [];
        foreach ($chunk as $r) {
            $e = $entities[$r['qid']] ?? null;
            $p18 = [];
            foreach ($e['claims']['P18'] ?? [] as $claim) {
                if (($claim['rank'] ?? 'normal') !== 'deprecated' && is_string($v = $claim['mainsnak']['datavalue']['value'] ?? null)) {
                    $p18[] = 'File:'.str_replace('_', ' ', $v);
                }
            }
            $cats = [];
            foreach ([$e['claims']['P373'][0]['mainsnak']['datavalue']['value'] ?? null, $e['sitelinks']['commonswiki']['title'] ?? null] as $c) {
                if (is_string($c) && $c !== '') {
                    $c = str_starts_with($c, 'Category:') ? $c : (str_contains($c, ':') ? null : 'Category:'.$c);
                    if ($c !== null && ! in_array($c, $cats, true)) {
                        $cats[] = $c;
                    }
                }
            }
            $wd[$r['qid']] = ['p18' => $p18, 'cats' => array_slice($cats, 0, 2)];
            array_push($titles, ...$p18);
        }

        // Les images P18 du lot : une requête, puis les titres redirigés un à un.
        $byTitle = [];
        foreach ($this->client->fileInfoByTitles($titles) as $pageId => $page) {
            $byTitle[self::key($page['title'])] = [$pageId, $page];
        }
        $minImages = (int) $this->option('min-images');
        foreach ($chunk as $r) {
            $files = [];
            foreach ($wd[$r['qid']]['p18'] as $t) {
                $hit = $byTitle[self::key($t)] ?? null;
                if ($hit === null) {
                    $single = $this->client->fileInfoByTitle($t);
                    $hit = $single !== null ? [$single['pageid'], $single] : null;
                }
                if ($hit !== null) {
                    $files[$hit[0]] = ['page' => $hit[1], 'via' => 'p18', 'category' => null];
                }
            }
            $enough = false;
            if ($minImages > 0) {
                $good = 0;
                foreach ($files as $f) {
                    $good += $this->accepts(CommonsMetadata::normalize($f['page']), 'p18', $r) ? 1 : 0;
                }
                $enough = $good >= $minImages;
                $this->stats['categories_sautees'] = ($this->stats['categories_sautees'] ?? 0) + ($enough ? 1 : 0);
            }
            foreach ($enough ? [] : $wd[$r['qid']]['cats'] as $cat) {
                $this->addCategoryFiles($r, $cat, $files);
            }
            $this->emit($r, $r['qid'], $files, $wd[$r['qid']]['cats'][0] ?? null);
        }
    }

    /**
     * Ajoute les fichiers d'une catégorie (et d'au plus 3 sous-catégories de gare si elle est pauvre).
     *
     * @param  array<string, mixed>  $r
     * @param  array<int, array<string, mixed>>  $files
     */
    private function addCategoryFiles(array $r, string $cat, array &$files, ?string $forceVia = null): void
    {
        $res = $this->client->categoryFiles($cat, self::CATEGORY_FILES);
        $via = $forceVia ?? $this->categoryVia($cat);
        foreach ($res['files'] as $pageId => $page) {
            $files[$pageId] ??= ['page' => $page, 'via' => $via, 'category' => substr($cat, 9)];
        }
        if (count($res['files']) < 6) {
            $subs = array_slice(array_values(array_filter($res['subcats'], fn (string $c) => CommonsCoverage::isStationSubcategory($c))), 0, 3);
            foreach ($subs as $sub) {
                $deep = $this->client->categoryFiles($sub, self::SUBCATEGORY_FILES);
                foreach ($deep['files'] as $pageId => $page) {
                    $files[$pageId] ??= ['page' => $page, 'via' => $via, 'category' => substr($sub, 9)];
                }
            }
        }
    }

    /** `category` (propre à la fiche) ou `weak_category` (à filtrer sur le sujet des fichiers). */
    private function categoryVia(string $cat): string
    {
        if ($this->subject === 'material') {
            return 'category';
        }
        $name = substr($cat, 9);
        if ($this->subject === 'line') {
            return preg_match('/\b(lignes?|lines?|railways?|chemins? de fer|bahnstrecke)\b/iu', $name) ? 'category' : 'weak_category';
        }

        return CommonsCoverage::isStationCategory($cat) ? 'category' : 'weak_category';
    }

    /**
     * Gare sans candidat : fichiers géolocalisés à moins de 250 m dont le titre parle de gare, de halte
     * ou de voie ET nomme le lieu (nom de la gare ou commune).
     *
     * @param  array<string, mixed>  $r
     */
    private function processGeoStation(array $r): void
    {
        $city = (string) (DB::table('stations')->where('id', $r['id'])->value('city') ?? '');
        $hits = $this->client->geosearch((float) $r['lat'], (float) $r['lon'], self::GEO_RADIUS_M, 100);
        $ids = [];
        foreach ($hits as $h) {
            $d = CommonsCoverage::distanceM($h['lat'], $h['lon'], (float) $r['lat'], (float) $r['lon']);
            if ($d <= self::GEO_RADIUS_M && CommonsCoverage::titleIsRailway($h['title'])) {
                $ids[$h['pageid']] = $d;
            }
        }
        asort($ids);
        $files = [];
        if ($ids !== []) {
            foreach ($this->client->fileInfo(array_slice(array_keys($ids), 0, 12)) as $pageId => $page) {
                $meta = CommonsMetadata::normalize($page);
                if (CommonsCoverage::mentionsPlace($meta, $r['name'], $city)) {
                    $files[$pageId] = ['page' => $page, 'via' => 'geosearch', 'category' => null];
                }
            }
        }
        $this->emit($r, null, $files, null);
    }

    /**
     * Gares sans élément Wikidata : catégories d'après le nom, validées par la position.
     *
     * @param  list<array<string, mixed>>  $chunk
     */
    private function processNameChunk(array $chunk): void
    {
        $titles = [];
        foreach ($chunk as $r) {
            foreach (CommonsCoverage::stationCategoryNames($r['name'], $r['type']) as $t) {
                $titles[$t][] = $r['slug'];
            }
        }
        $found = [];
        if ($titles !== []) {
            $data = $this->client->commons([
                'action' => 'query', 'titles' => implode('|', array_keys($titles)),
                'prop' => 'categoryinfo|coordinates', 'colimit' => 'max',
            ]);
            $alias = [];
            foreach ($data['query']['normalized'] ?? [] as $n) {
                $alias[$n['to']] = $n['from'];
            }
            foreach ($data['query']['pages'] ?? [] as $p) {
                if (! empty($p['missing']) || ! isset($p['categoryinfo']) || ($p['categoryinfo']['files'] ?? 0) + ($p['categoryinfo']['subcats'] ?? 0) === 0) {
                    continue;
                }
                $found[$p['title']] = isset($p['coordinates'][0]) ? [(float) $p['coordinates'][0]['lat'], (float) $p['coordinates'][0]['lon']] : null;
            }
        }
        foreach ($chunk as $r) {
            $files = [];
            $chosen = null;
            foreach (CommonsCoverage::stationCategoryNames($r['name'], $r['type']) as $t) {
                if (! array_key_exists($t, $found)) {
                    continue;
                }
                $coords = $found[$t];
                if ($coords !== null && CommonsCoverage::distanceM($coords[0], $coords[1], (float) $r['lat'], (float) $r['lon']) > CommonsCoverage::MAX_FILE_DISTANCE_M) {
                    $this->stats['nom_categorie_loin'] = ($this->stats['nom_categorie_loin'] ?? 0) + 1;

                    continue;
                }
                $tmp = [];
                $this->addCategoryFiles($r, $t, $tmp, 'category');
                if (! $this->nameCategoryConfirmed($tmp, $coords !== null, $r)) {
                    $this->stats['nom_categorie_non_confirmee'] = ($this->stats['nom_categorie_non_confirmee'] ?? 0) + 1;

                    continue;
                }
                $files = $tmp;
                $chosen = $t;
                break;
            }
            $this->emit($r, null, $files, $chosen, $chosen !== null ? 'name_category' : null);
        }
    }

    /**
     * Une catégorie trouvée par le nom, sans coordonnées propres, n'est retenue que si un de ses
     * fichiers géolocalisés est à moins de 500 m de la gare et qu'aucun n'est à plus de 3 km.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @param  array<string, mixed>  $r
     */
    private function nameCategoryConfirmed(array $files, bool $categoryHasCoords, array $r): bool
    {
        if ($files === []) {
            return false;
        }
        $near = false;
        foreach ($files as $f) {
            $meta = CommonsMetadata::normalize($f['page']);
            if ($meta['lat'] === null || $meta['lon'] === null) {
                continue;
            }
            $d = CommonsCoverage::distanceM($meta['lat'], $meta['lon'], (float) $r['lat'], (float) $r['lon']);
            if ($d > 3000) {
                return false;
            }
            $near = $near || $d <= CommonsCoverage::MAX_FILE_DISTANCE_M;
        }

        return $categoryHasCoords || $near;
    }

    /**
     * Lignes sans élément connu : recherche Wikidata par code RFN (P1671), une requête par ligne.
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>> les lignes restées sans élément (traitées par leur nom)
     */
    private function resolveLineQids(array $records): array
    {
        $rest = [];
        $found = [];
        foreach ($records as $r) {
            if ($r['code'] === null || ! preg_match('/^\d{6}$/', $r['code'])) {
                $rest[] = $r;

                continue;
            }
            try {
                $data = $this->client->wikidata([
                    'action' => 'query', 'list' => 'search', 'srnamespace' => 0, 'srlimit' => 3,
                    'srsearch' => 'haswbstatement:P1671='.substr($r['code'], 0, 3).' '.substr($r['code'], 3),
                ]);
                $hits = $data['query']['search'] ?? [];
                if ($hits === []) {
                    $data = $this->client->wikidata([
                        'action' => 'query', 'list' => 'search', 'srnamespace' => 0, 'srlimit' => 3,
                        'srsearch' => 'haswbstatement:P1671='.$r['code'],
                    ]);
                    $hits = $data['query']['search'] ?? [];
                }
            } catch (Throwable) {
                $rest[] = $r;

                continue;
            }
            if (count($hits) === 1) {
                $r['qid'] = (string) $hits[0]['title'];
                $found[] = $r;
            } else {
                $rest[] = $r;
            }
        }
        $this->stats['lignes_qid_par_code_rfn'] = count($found);
        foreach (array_chunk($found, 50) as $chunk) {
            $this->safely(fn () => $this->processWikidataChunk($chunk), $chunk);
        }

        return $rest;
    }

    /**
     * Lignes sans élément : catégorie du même nom que la ligne (« Ligne de A à B »), correspondance exacte.
     *
     * @param  list<array<string, mixed>>  $chunk
     */
    private function processLineNameChunk(array $chunk): void
    {
        $titles = [];
        foreach ($chunk as $r) {
            if (preg_match('/^Ligne d/u', $r['name'])) {
                $titles['Category:'.mb_strtoupper(mb_substr($r['name'], 0, 1)).mb_substr($r['name'], 1)] = $r;
            } else {
                $this->emit($r, null, [], null);
            }
        }
        $info = $titles === [] ? [] : $this->client->categoryInfo(array_keys($titles));
        foreach ($titles as $t => $r) {
            $files = [];
            $chosen = null;
            if (($info[$t]['exists'] ?? false) && $info[$t]['files'] + $info[$t]['subcats'] > 0 && $info[$t]['files'] <= 3000) {
                $this->addCategoryFiles($r, $t, $files, 'category');
                $chosen = $t;
            }
            $this->emit($r, null, $files, $chosen, $chosen !== null ? 'name_category' : null);
        }
    }

    /**
     * Filtre les fichiers puis écrit la ligne JSONL de la fiche.
     *
     * @param  array<string, mixed>  $r
     * @param  array<int, array<string, mixed>>  $files
     */
    private function emit(array $r, ?string $qid, array $files, ?string $category, ?string $viaOverride = null): void
    {
        $kept = [];
        foreach ($files as $pageId => $f) {
            $meta = CommonsMetadata::normalize($f['page']);
            $via = (string) $f['via'];
            $reason = $this->reason($meta, $via, $r);
            if ($reason !== null) {
                $this->stats['rejet_'.$reason] = ($this->stats['rejet_'.$reason] ?? 0) + 1;

                continue;
            }
            $dist = ($meta['lat'] !== null && $r['lat'] !== null)
                ? (int) round(CommonsCoverage::distanceM($meta['lat'], $meta['lon'], $r['lat'], $r['lon'])) : null;
            $kept[] = [
                'pageid' => (int) $pageId,
                'via' => $viaOverride !== null && $via === 'category' ? $viaOverride : $via,
                'category' => $f['category'],
                'distance_m' => $dist,
                'meta' => $meta,
            ];
            if (count($kept) >= $this->maxPer) {
                break;
            }
        }
        // P18 d'abord (ordre d'insertion), la première image devient l'image à la une.
        $this->stats['fiches'] = ($this->stats['fiches'] ?? 0) + 1;
        if ($kept !== []) {
            $this->stats['fiches_avec_image'] = ($this->stats['fiches_avec_image'] ?? 0) + 1;
            $this->stats['images'] = ($this->stats['images'] ?? 0) + count($kept);
        }
        fwrite($this->out, json_encode([
            'rules' => CommonsCoverage::RULES_VERSION,
            'subject' => $this->subject, 'id' => $r['id'], 'slug' => $r['slug'], 'name' => $r['name'],
            'qid' => $qid, 'category' => $category, 'files' => $kept,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
        fflush($this->out);
        if ($this->stats['fiches'] % 100 === 0) {
            $this->line(sprintf('  %d fiche(s), %d avec image, %d image(s)', $this->stats['fiches'], $this->stats['fiches_avec_image'] ?? 0, $this->stats['images'] ?? 0));
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $r
     */
    private function reason(array $meta, string $via, array $r): ?string
    {
        return $this->subject === 'station'
            ? CommonsCoverage::stationReject($meta, $via, $r['lat'], $r['lon'])
            : CommonsCoverage::railReject($meta, $via);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $r
     */
    private function accepts(array $meta, string $via, array $r): bool
    {
        return $this->reason($meta, $via, $r) === null;
    }

    private static function key(string $title): string
    {
        return mb_strtolower(str_replace('_', ' ', preg_replace('/^(File|Fichier):/i', '', $title) ?? $title));
    }
}
