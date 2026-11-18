<?php

namespace App\Console\Commands;

use App\Services\HeritageText;
use App\Services\OsmImporter;
use App\Services\SectionResolver;
use App\Services\WikimediaClient;
use App\Services\WikipediaLineParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

/**
 * Exploitations ASSOCIATIVES des lignes (trains touristiques, vélorails,
 * musées roulants), actuelles et passées, sur la bonne PORTION et à la bonne
 * PÉRIODE → line_events `heritage_start` / `heritage_end` (+ heritage_kind).
 * Le moteur décennal (vff:build-era-segments) en déduit, ère par ère,
 * line_segments.is_heritage + heritage_kind là où il n'y a plus de trafic
 * commercial. Ex. : ligne fermée en 1969, dont 20 km sont exploités par une
 * association depuis 1982 → « exploitée par une association » sur ces 20 km
 * seulement, dans les ères 1990 à 2030.
 *
 * Sources, recoupées :
 *   1. IGN BD TOPO (source_traces bdtopo, Licence Ouverte 2.0) : tronçons
 *      nommés (cpx_toponyme « Train Touristique des Cévennes », « Vélo-Rail du
 *      Larzac »…) et usage « Vélo-rail » → PORTION exacte des exploitations
 *      actuelles, découpée en composantes spatiales (homonymes éloignés) ;
 *   2. OpenStreetMap (source_traces osm déjà importés, ODbL ; Overpass n'est
 *      pas réinterrogé) : usage=tourism, railway=preserved,
 *      railway:preserved=yes, exploitant touristique
 *      (OsmImporter::isHeritageOperator) ou nom de vélorail ; un tronçon sans
 *      nom ni exploitant (train ou vélorail ?) n'est pas retenu ;
 *   3. Wikipédia FR, « Liste des chemins de fer touristiques de France »
 *      (CC BY-SA) : catalogue des exploitations, en service ou disparues,
 *      ligne et section parcourues ; articles de chaque exploitation (infobox
 *      « date création » / « date fin », phrases datées) et des lignes ;
 *   4. Wikidata (CC0) : items « chemin de fer touristique » (Q420962 et
 *      sous-classes) situés en France + items des articles : début P580 / P571,
 *      fin P582 / P576, exploitant P137 ;
 *   5. lignes-oubliees.com (reproduction des informations autorisée avec
 *      mention d'origine) : rubrique « État actuel » des fiches en cache
 *      (« train touristique (depuis le 3 juin 1982) », « inexploitée
 *      (vélorail entre Gourfaleur et Condé-sur-Vire) ») et notes datées.
 *
 * Rapprochement :
 *   - portion d'une exploitation observée (IGN / OSM) = tracé de référence
 *     de la ligne (line_reference_geometries) à moins de BUFFER_M m des
 *     tronçons exploités, petits trous (< GAP_M) comblés → section_geom,
 *     section_resolution = 'trace' ; un vélorail posé sur la même voie
 *     qu'un train touristique est fusionné dans ce dernier (le train circule) ;
 *   - exploitation du catalogue sans tracé observé (passée, ou absente de
 *     l'IGN) : section nommée (« Anduze - Saint-Jean-du-Gard », PK) sur la
 *     ligne liée → section_from / section_to + 'pending', résolue aussitôt par
 *     App\Services\SectionResolver ;
 *   - catalogue ↔ observation, de même type : nom semblable
 *     (HeritageText::nameSimilarity), ou même ligne (départagée par les gares
 *     extrémités de la section) ; affectation gloutonne par score ;
 *   - lignes militaires exclues (alignements de 1914-1918 croisant une voie
 *     touristique actuelle) ; ligne seulement longée près d'une gare et en
 *     service commercial : écartée.
 *
 * Dates (de la plus fiable à la moins fiable) : lignes-oubliees « État
 * actuel » ; infobox de l'article ; Wikidata ; notes lignes-oubliees ;
 * phrases datées de l'article ; phrases de l'article de la ligne (date à
 * moins de 120 caractères d'une mention de l'exploitation, hors contexte de
 * fermeture / voie verte / projet). Interruption « interrompues en 2008 […]
 * reprises le 2 juillet 2013 » → heritage_end + nouveau heritage_start. Une
 * exploitation en service dont le début reste inconnu est « attestée » à la
 * date de sa source (mise à jour de la fiche, liste Wikipédia ou IGN de
 * l'année), confiance basse : elle n'apparaît que sur les cartes
 * postérieures. Une exploitation disparue sans date de fin, ou que l'IGN dit
 * « non exploitée » sans autre source, n'est pas écrite (rapport).
 *
 * Écriture : source = 'heritage', fingerprint « heritage:… » (upsert
 * idempotent) ; seuls les événements « heritage:% » non reproduits sont
 * supprimés, ainsi que ceux dont la portion nommée reste introuvable.
 * Description en français : nom de l'exploitation (et de l'exploitant
 * connu), type, portion, période, sources. Rapports :
 * storage/app/reports/heritage-exploitations.csv (une ligne par
 * exploitation × ligne, citations des dates, exploitations non écrites et
 * pourquoi) et heritage-import.json.
 *
 *   php artisan vff:import-heritage-lines
 *   php artisan vff:import-heritage-lines --from-cache --line=815000
 */
class ImportHeritageLines extends Command
{
    protected $signature = 'vff:import-heritage-lines
        {--from-cache : N\'utilise que le cache local (storage/app/sources/heritage), aucune requête réseau}
        {--refresh : Relit Wikipédia et Wikidata même si le cache existe}
        {--line=* : Limite l\'écriture aux lignes données (id ou code RFN) ; nettoyage limité à ces lignes}
        {--dry-run : Calcule et affiche les exploitations sans rien écrire}';

    protected $description = 'Exploitations associatives (trains touristiques, vélorails, musées) → line_events heritage_start / heritage_end par portion';

    public const SOURCE = 'heritage';

    public const FP_PREFIX = 'heritage:';

    private const DIR = 'sources/heritage';

    private const LIST_TITLE = 'Liste des chemins de fer touristiques de France';

    private const WIKIDATA_API = 'https://www.wikidata.org/w/api.php';

    private const LO_BASE = 'https://www.lignes-oubliees.com/';

    private const IGN_URL = 'https://geoservices.ign.fr/bdtopo';

    /** Tolérance (m) entre les tronçons exploités et le tracé de référence de la ligne. */
    private const BUFFER_M = 25;

    /** Trous (m) comblés entre deux morceaux exploités consécutifs (pont, tunnel mal calé…). */
    private const GAP_M = 600;

    private const MIN_PIECE_M = 300;

    private const MIN_LINE_M = 500;

    /** Distance (m) au-delà de laquelle deux tronçons homonymes sont deux exploitations distinctes. */
    private const CLUSTER_EPS_M = 2000;

    /** Noms de services COMMERCIAUX que l'IGN nomme comme des trains touristiques. */
    private const COMMERCIAL_RE = '/\b(?:mont blanc express|train jaune|train des pignes|tram \d+|ter|transilien|rer|lgv|ligne a grande vitesse|metro|funiculaire|ligne cote d opale|ligne des hirondelles|train des merveilles)\b/';

    /** Rang des sources de dates (plus petit = plus fiable). */
    private const DATE_RANK = ['lo-etat' => 0, 'infobox' => 1, 'wikidata' => 2, 'lo-note' => 3, 'article' => 4, 'line-article' => 5, 'attested' => 9];

    private int $year;

    /** @var list<int> */
    private array $lineFilter = [];

    /** @var array<string, array{title: string, revid: int, content: string}> */
    private array $articles = [];

    /** @var array<string, string> titre demandé → QID */
    private array $qids = [];

    /** @var array<string, array> QID → entité Wikidata (claims, labels, sitelinks) */
    private array $entities = [];

    private array $stats = [];

    /** @var list<array> exploitations non écrites (sans ligne, sans date…) */
    private array $skipped = [];

    private float $lastRequestAt = 0.0;

    public function __construct(private WikimediaClient $wiki, private WikipediaLineParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Une instance de commande sert à tous les appels du processus (vff:rebuild-history,
        // tests) : l'état d'un passage précédent ne doit pas fausser le suivant.
        [$this->articles, $this->qids, $this->entities, $this->stats, $this->skipped] = [[], [], [], [], []];
        [$this->lineTitlesAvailable, $this->wikidataOnly, $this->placesNear, $this->references, $this->linkIndex] = [[], [], [], null, null];
        $this->year = (int) now()->year;
        $this->lineFilter = $this->resolveLineFilter();
        if ($this->option('line') && ! $this->lineFilter) {
            $this->error('Aucune ligne ne correspond à --line.');

            return self::FAILURE;
        }

        $this->info('1/6 Catalogue Wikipédia (liste des chemins de fer touristiques)…');
        $catalog = $this->catalog();
        $this->line('    '.count($catalog).' exploitations au catalogue');

        $this->info('2/6 Articles Wikipédia et Wikidata…');
        $this->loadArticlesAndWikidata($catalog);

        $this->info('3/6 lignes-oubliees (État actuel)…');
        $lo = $this->lignesOubliees();
        $this->line('    '.count($lo).' mentions datées ou localisées');

        $this->info('4/6 Tracés exploités observés (IGN BD TOPO, OSM)…');
        $observations = $this->observations();
        $this->line('    '.count($observations).' exploitations observées sur une ligne VFF');

        $this->info('5/6 Rapprochement et dates…');
        $ops = $this->operations($catalog, $lo, $observations);
        // Lignes parcourues par plusieurs exploitations du même type : dans l'article de la ligne,
        // seul le NOM de l'exploitation la désigne (« un train touristique » peut être l'autre).
        $perLine = [];
        foreach ($ops as $op) {
            foreach (array_keys($op['portions']) as $lineId) {
                $perLine[$lineId.'|'.$op['kind']] = ($perLine[$lineId.'|'.$op['kind']] ?? 0) + 1;
            }
        }
        foreach ($ops as &$op) {
            $op['shared_line'] = (bool) array_filter(array_keys($op['portions']), fn ($l) => ($perLine[$l.'|'.$op['kind']] ?? 0) > 1);
            $this->chooseDates($op);
        }
        unset($op);
        // Même exploitation, deux activités (« Vélorail du Larzac » : vélorails et train touristique sur
        // la même voie) : une activité sans date connue prend celle de l'autre (même association, même ligne).
        foreach ($ops as $k => $op) {
            if (($op['start']['src'] ?? 'attested') !== 'attested' || ! str_contains($k, '|') || $op['status'] === 'ended') {
                continue;
            }
            foreach ($ops as $k2 => $sib) {
                if ($k2 !== $k && strtok($k2, '|') === strtok($k, '|') && ($sib['start']['src'] ?? 'attested') !== 'attested'
                    && array_intersect_key($sib['portions'], $op['portions'])) {
                    $ops[$k]['start'] = $sib['start'] + ['sibling' => HeritageText::kindLabel($sib['kind'])];
                    break;
                }
            }
        }

        $this->info('6/6 Écriture…');
        $written = $this->write($ops);
        $this->report($ops, $written);

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Lignes ciblées
    // ------------------------------------------------------------------

    private function resolveLineFilter(): array
    {
        $ids = [];
        foreach ((array) $this->option('line') as $opt) {
            $opt = trim((string) $opt);
            $q = DB::table('lines')->where('code', $opt);
            if (ctype_digit($opt) && strlen($opt) < 6) {
                $q->orWhere('id', (int) $opt);
            }
            $ids = array_merge($ids, $q->pluck('id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function inScope(int $lineId): bool
    {
        return ! $this->lineFilter || in_array($lineId, $this->lineFilter, true);
    }

    // ------------------------------------------------------------------
    // Cache et réseau
    // ------------------------------------------------------------------

    private function cacheGet(string $name): mixed
    {
        $path = self::DIR.'/'.$name;
        if ($this->option('refresh') && ! $this->option('from-cache')) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? json_decode(Storage::disk('local')->get($path), true) : null;
    }

    private function cachePut(string $name, mixed $data): void
    {
        Storage::disk('local')->put(self::DIR.'/'.$name, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function online(): bool
    {
        return ! $this->option('from-cache');
    }

    /** GET poli (1 requête / s) avec reprises. */
    private function httpJson(string $url, array $query): ?array
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $wait = 1.0 - (microtime(true) - $this->lastRequestAt);
            if ($wait > 0) {
                Sleep::usleep((int) ($wait * 1e6));
            }
            $this->lastRequestAt = microtime(true);
            try {
                $res = Http::withUserAgent(WikimediaClient::USER_AGENT)->timeout(90)->acceptJson()->get($url, $query);
                if ($res->ok() && is_array($res->json())) {
                    return $res->json();
                }
            } catch (\Throwable $e) {
                $this->warn('    '.$e->getMessage());
            }
            if ($attempt < 4) {
                Sleep::sleep(3 * $attempt);
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // 1. Catalogue Wikipédia
    // ------------------------------------------------------------------

    /** @return list<array> une entrée par exploitation (les lignes « rowspan » regroupées) */
    private function catalog(): array
    {
        $page = $this->cacheGet('liste-chemins-de-fer-touristiques.json');
        if (! $page && $this->online()) {
            $page = $this->wiki->fetchPages([self::LIST_TITLE])[self::LIST_TITLE] ?? null;
            if ($page) {
                $this->cachePut('liste-chemins-de-fer-touristiques.json', $page);
            }
        }
        if (! $page) {
            $this->warn('    liste Wikipédia indisponible (ni cache ni réseau) : catalogue vide');

            return [];
        }
        $url = WikimediaClient::permalink($page['title'], (int) $page['revid']);
        $entries = [];
        foreach (HeritageText::parseWikiList($page['content']) as $row) {
            $key = 'wp:'.HeritageText::norm($row['name']);
            if (! isset($entries[$key])) {
                $entries[$key] = $row + ['key' => $key, 'list_url' => $url, 'lines' => []];
                $entries[$key]['lines'] = [];
            }
            foreach ($row['lines'] as $l) {
                $entries[$key]['lines'][] = $l;
            }
            if ($row['status'] === 'ended' || $entries[$key]['status'] === null) {
                $entries[$key]['status'] = $row['status'] ?? $entries[$key]['status'];
            }
        }
        $out = [];
        foreach ($entries as $e) {
            $n = HeritageText::norm($e['name'].' '.implode(' ', array_column($e['lines'], 'label')));
            if (! $e['kinds'] || preg_match(self::COMMERCIAL_RE, $n)) {
                $this->stats['catalog_excluded'] = ($this->stats['catalog_excluded'] ?? 0) + 1;

                continue;
            }
            // Trains d'excursion sur le réseau national (locomotives préservées) : pas de portion propre.
            $e['lines'] = array_values(array_filter($e['lines'], fn ($l) => ! str_starts_with((string) $l['link'], 'Réseau ferré national')));
            $e['rfn_only'] = $e['lines'] === [];
            $out[] = $e;
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // 2. Articles (exploitations + lignes) et Wikidata
    // ------------------------------------------------------------------

    private function loadArticlesAndWikidata(array &$catalog): void
    {
        $titles = [];
        foreach ($catalog as $e) {
            if ($e['article']) {
                $titles[] = $e['article'];
            }
            foreach ($e['lines'] as $l) {
                if ($l['link']) {
                    $titles[] = $l['link'];
                }
            }
        }
        // Articles des lignes VFF (phrases datées « vélorail depuis 1999 »…).
        $lineTitles = DB::table('lines')->whereNotNull('wikipedia_title')->pluck('wikipedia_title')->all();

        // Items Wikidata « chemin de fer touristique » en France (+ leurs articles).
        $listQids = $this->cacheGet('wikidata-heritage-items.json');
        if ($listQids === null && $this->online()) {
            $json = $this->httpJson(WikimediaClient::WIKIDATA_SPARQL, ['format' => 'json', 'query' => <<<'SPARQL'
                SELECT DISTINCT ?item ?article WHERE {
                  ?item wdt:P31/wdt:P279* wd:Q420962 ; wdt:P17 wd:Q142 .
                  OPTIONAL { ?article schema:about ?item ; schema:isPartOf <https://fr.wikipedia.org/> }
                }
                SPARQL]);
            if ($json) {
                $listQids = [];
                foreach ($json['results']['bindings'] ?? [] as $b) {
                    $q = basename($b['item']['value']);
                    $listQids[$q] = isset($b['article']) ? urldecode(str_replace('_', ' ', basename($b['article']['value']))) : null;
                }
                $this->cachePut('wikidata-heritage-items.json', $listQids);
            }
        }
        $listQids ??= [];
        $titles = array_values(array_unique(array_merge($titles, array_filter(array_values($listQids)))));

        $cached = $this->cacheGet('articles.json') ?? [];
        $missing = array_values(array_diff($titles, array_keys($cached)));
        if ($missing && $this->online()) {
            $this->line('    téléchargement de '.count($missing).' articles…');
            $fetched = $this->wiki->fetchPages($missing);
            foreach ($missing as $t) {
                $cached[$t] = $fetched[$t] ?? ['title' => $t, 'revid' => 0, 'content' => '', 'missing' => true];
            }
            $this->cachePut('articles.json', $cached);
        }
        $this->articles = $cached;

        // Articles des lignes : cache séparé (gros), seulement ceux utiles plus tard.
        $lineCache = $this->cacheGet('line-articles.json') ?? [];
        $this->articles += $lineCache;
        $this->lineTitlesAvailable = array_flip($lineTitles);

        // QID des articles d'exploitation (pageprops).
        $props = $this->cacheGet('pageprops.json') ?? [];
        $opTitles = array_values(array_filter(array_column($catalog, 'article')));
        $needProps = array_values(array_diff($opTitles, array_keys($props)));
        if ($needProps && $this->online()) {
            foreach (array_chunk($needProps, 50) as $chunk) {
                $json = $this->wiki->api(['action' => 'query', 'prop' => 'pageprops', 'ppprop' => 'wikibase_item', 'redirects' => 1, 'titles' => implode('|', $chunk)]);
                $alias = [];
                foreach (['normalized', 'redirects'] as $k) {
                    foreach ($json['query'][$k] ?? [] as $n) {
                        $alias[$n['to']][] = $n['from'];
                    }
                }
                foreach ($json['query']['pages'] ?? [] as $p) {
                    $qid = $p['pageprops']['wikibase_item'] ?? null;
                    $names = [$p['title']];
                    foreach ($alias[$p['title']] ?? [] as $from) {
                        $names[] = $from;
                        foreach ($alias[$from] ?? [] as $from2) {
                            $names[] = $from2;
                        }
                    }
                    foreach ($names as $n) {
                        $props[$n] = $qid;
                    }
                }
                foreach ($chunk as $t) {
                    $props[$t] ??= null;
                }
            }
            $this->cachePut('pageprops.json', $props);
        }
        foreach ($opTitles as $t) {
            if (! empty($props[$t])) {
                $this->qids[$t] = $props[$t];
            }
        }
        foreach ($listQids as $q => $t) {
            if ($t) {
                $this->qids[$t] ??= $q;
            }
        }

        // Entités Wikidata (dates, exploitant).
        $ents = $this->cacheGet('wikidata-entities.json') ?? [];
        $need = array_values(array_diff(array_unique(array_merge(array_values($this->qids), array_keys($listQids))), array_keys($ents)));
        if ($need && $this->online()) {
            foreach (array_chunk($need, 50) as $chunk) {
                $json = $this->httpJson(self::WIKIDATA_API, ['action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'props' => 'claims|labels|sitelinks',
                    'languages' => 'fr|en', 'sitefilter' => 'frwiki', 'format' => 'json']);
                foreach ($json['entities'] ?? [] as $q => $ent) {
                    $ents[$q] = ['labels' => $ent['labels'] ?? [], 'sitelinks' => $ent['sitelinks'] ?? [],
                        'claims' => array_intersect_key($ent['claims'] ?? [], array_flip(['P571', 'P580', 'P576', 'P582', 'P1619', 'P3999', 'P137', 'P625']))];
                }
            }
            // Libellés des exploitants.
            $ops = [];
            foreach ($ents as $ent) {
                foreach ($ent['claims']['P137'] ?? [] as $c) {
                    if ($id = $c['mainsnak']['datavalue']['value']['id'] ?? null) {
                        $ops[$id] = true;
                    }
                }
            }
            $needOps = array_values(array_diff(array_keys($ops), array_keys($ents)));
            foreach (array_chunk($needOps, 50) as $chunk) {
                $json = $this->httpJson(self::WIKIDATA_API, ['action' => 'wbgetentities', 'ids' => implode('|', $chunk), 'props' => 'labels', 'languages' => 'fr|en', 'format' => 'json']);
                foreach ($json['entities'] ?? [] as $q => $ent) {
                    $ents[$q] = ['labels' => $ent['labels'] ?? [], 'sitelinks' => [], 'claims' => []];
                }
            }
            $this->cachePut('wikidata-entities.json', $ents);
        }
        $this->entities = $ents;
        $this->wikidataOnly = array_diff_key($listQids, array_flip(array_values($this->qids)));
        $this->line(sprintf('    %d articles, %d items Wikidata', count($this->articles), count($this->entities)));
    }

    /** @var array<string, true> titres d'articles de lignes VFF */
    private array $lineTitlesAvailable = [];

    /** @var array<string, ?string> items Wikidata hors catalogue (QID → article) */
    private array $wikidataOnly = [];

    /** Article de ligne (téléchargé à la demande, mis en cache). */
    private function lineArticle(string $title): ?array
    {
        if (isset($this->articles[$title])) {
            return $this->articles[$title]['content'] !== '' ? $this->articles[$title] : null;
        }
        if (! $this->online()) {
            return null;
        }
        $page = $this->wiki->fetchPages([$title])[$title] ?? ['title' => $title, 'revid' => 0, 'content' => '', 'missing' => true];
        $this->articles[$title] = $page;
        $cache = $this->cacheGet('line-articles.json') ?? [];
        $cache[$title] = $page;
        $this->cachePut('line-articles.json', $cache);

        return $page['content'] !== '' ? $page : null;
    }

    /** @return array{date: string, precision: string, year: int}|null */
    private function wdTime(array $ent, string $prop): ?array
    {
        foreach ($ent['claims'][$prop] ?? [] as $c) {
            $v = $c['mainsnak']['datavalue']['value'] ?? null;
            if (! is_array($v) || ! preg_match('/^\+(\d{4})-(\d\d)-(\d\d)/', $v['time'] ?? '', $m)) {
                continue;
            }
            $prec = (int) ($v['precision'] ?? 9);
            [$y, $mo, $d] = [(int) $m[1], max(1, (int) $m[2]), max(1, (int) $m[3])];

            return match (true) {
                $prec >= 11 => ['date' => sprintf('%04d-%02d-%02d', $y, $mo, $d), 'precision' => 'day', 'year' => $y],
                $prec === 10 => ['date' => sprintf('%04d-%02d-01', $y, $mo), 'precision' => 'month', 'year' => $y],
                default => ['date' => sprintf('%04d-01-01', $y), 'precision' => 'year', 'year' => $y],
            };
        }

        return null;
    }

    private function wdLabel(?array $ent): ?string
    {
        return $ent['labels']['fr']['value'] ?? $ent['labels']['en']['value'] ?? null;
    }

    // ------------------------------------------------------------------
    // 3. lignes-oubliees
    // ------------------------------------------------------------------

    /** @return list<array> mentions (ligne VFF, section, type, dates) */
    private function lignesOubliees(): array
    {
        $dir = storage_path('app/sources/lignes-oubliees/fiches');
        $files = glob($dir.'/*.html') ?: [];
        if (! $files) {
            return [];
        }
        // Lignes rattachées à chaque fiche par vff:import-lignes-oubliees.
        $lineByUrl = DB::table('line_events')->where('source', ImportLignesOubliees::SOURCE)
            ->where('source_url', 'like', '%index.php?page=%')->where('source_url', 'not like', '%#%')
            ->select('source_url', DB::raw('MIN(line_id) AS line_id'))->groupBy('source_url')->pluck('line_id', 'source_url')->all();
        $out = [];
        foreach ($files as $f) {
            $page = basename($f, '.html');
            $url = self::LO_BASE.'index.php?page='.$page;
            $html = (string) file_get_contents($f);
            $rows = HeritageText::parseLoFiche($html, $this->year);
            if (! $rows) {
                continue;
            }
            $lineId = $lineByUrl[$url] ?? null;
            $updated = preg_match('/Mise à jour\s*:\s*([^<]+)/u', $html, $m) ? HeritageText::parseDate($m[1]) : null;
            foreach ($rows as $r) {
                if (! $lineId) {
                    $this->skipped[] = ['name' => $r['name'] ?? $r['quote'], 'kind' => $r['kind'], 'reason' => 'fiche lignes-oubliees sans ligne VFF', 'url' => $url];

                    continue;
                }
                $out[] = $r + ['line_id' => (int) $lineId, 'url' => $url.'#etat-actuel', 'page' => $page, 'updated' => $updated];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // 4. Observations IGN / OSM
    // ------------------------------------------------------------------

    /** @return list<array> exploitations observées, avec leurs portions par ligne VFF */
    private function observations(): array
    {
        // Groupes de tronçons par nom (un toponyme IGN « A/B » relève des deux exploitations A et B).
        $groups = [];
        $add = function (string $origin, string $name, ?string $operator, string $kind, int $id, bool $active) use (&$groups) {
            $key = $origin.'|'.$kind.'|'.HeritageText::norm($name);
            $groups[$key] ??= ['origin' => $origin, 'name' => $name, 'operator' => $operator, 'kind' => $kind, 'ids' => [], 'active' => false];
            $groups[$key]['ids'][] = $id;
            $groups[$key]['active'] = $groups[$key]['active'] || $active;
        };

        $ign = DB::select("SELECT id, properties->>'cpx_toponyme' AS t, properties->>'usage' AS u, properties->>'etat_de_l_objet' AS e
                           FROM source_traces WHERE source = 'bdtopo'
                             AND (properties->>'usage' = 'Vélo-rail' OR COALESCE(properties->>'cpx_toponyme', '') <> '')");
        foreach ($ign as $r) {
            $active = $r->e === 'En service';
            $parts = array_values(array_filter(array_map('trim', explode('/', (string) $r->t))));
            $found = false;
            foreach ($parts as $p) {
                if (preg_match(self::COMMERCIAL_RE, HeritageText::norm($p))) {
                    continue;
                }
                $kinds = HeritageText::kindsOf($p);
                if (! $kinds) {
                    continue;
                }
                $add('ign', $p, null, HeritageText::mainKind($kinds), $r->id, $active);
                $found = true;
            }
            if (! $found && $r->u === 'Vélo-rail' && ! $parts) {
                $add('ign', '', null, 'velorail', $r->id, $active);
            }
        }

        $osm = DB::select(<<<'SQL'
            SELECT id, layer, properties->>'name' AS name, properties->>'operator' AS operator,
                   properties->>'usage' AS usage, properties->>'railway:preserved' AS pres
            FROM source_traces WHERE source = 'osm'
              AND (properties->>'usage' = 'tourism' OR layer = 'preserved' OR properties->>'railway:preserved' = 'yes'
                   OR properties->>'operator' ~* 'touristi|v[ée]lo.?rail|cyclo|draisine|p[ée]dalo|vapeur|agrivap|transvap|cft|cfbs|vfv|association|amicale'
                   OR properties->>'name' ~* 'v[ée]lo.?rail|cyclo.?rail|draisine|p[ée]dalo.?rail|train touristique|chemin de fer touristique')
            SQL);
        foreach ($osm as $r) {
            $label = null;
            foreach ([$r->operator, $r->name] as $cand) {
                if ($cand && (OsmImporter::isHeritageOperator($cand) || HeritageText::kindsOf($cand)) && ! preg_match('/^ligne\b|sncf|rfn|r[ée]seau/iu', $cand)) {
                    $label = $cand;
                    break;
                }
            }
            // Sans nom ni exploitant touristique, un tronçon usage=tourism ne dit pas QUELLE exploitation
            // (train ou vélorail) : il n'est pas retenu.
            if (! $label || preg_match(self::COMMERCIAL_RE, HeritageText::norm($label))) {
                continue;
            }
            $kind = HeritageText::mainKind(HeritageText::kindsOf($label)) ?? 'tourist_train';
            $operator = $r->operator && OsmImporter::isHeritageOperator($r->operator) ? $r->operator : null;
            $add('osm', (string) $label, $operator, $kind, $r->id, ! in_array($r->layer, ['abandoned', 'razed', 'dismantled'], true));
        }

        // Composantes spatiales de chaque groupe (deux « Pédalorail » éloignés = deux exploitations).
        DB::statement('DROP TABLE IF EXISTS hl_groups');
        DB::statement('CREATE TEMP TABLE hl_groups (grp int, trace_id bigint)');
        $keys = array_keys($groups);
        foreach (array_chunk(array_keys($keys), 200) as $chunk) {
            $values = [];
            foreach ($chunk as $gi) {
                foreach ($groups[$keys[$gi]]['ids'] as $id) {
                    $values[] = '('.(int) $gi.','.(int) $id.')';
                }
            }
            if ($values) {
                DB::statement('INSERT INTO hl_groups VALUES '.implode(',', $values));
            }
        }
        $comps = DB::select(<<<'SQL'
            SELECT grp, cid, array_agg(trace_id) AS ids, ROUND(SUM(len))::int AS len FROM (
                SELECT g.grp, g.trace_id, ST_Length(ST_Transform(st.geom, 2154)) AS len,
                       ST_ClusterDBSCAN(ST_Transform(st.geom, 2154), eps := :eps, minpoints := 1) OVER (PARTITION BY g.grp) AS cid
                FROM hl_groups g JOIN source_traces st ON st.id = g.trace_id
            ) x GROUP BY grp, cid
            SQL, ['eps' => self::CLUSTER_EPS_M]);

        $obs = [];
        $bar = $this->output->createProgressBar(count($comps));
        foreach ($comps as $c) {
            $bar->advance();
            $g = $groups[$keys[(int) $c->grp]];
            $ids = array_map('intval', explode(',', trim($c->ids, '{}')));
            $lines = array_filter($this->portionsAlong($ids), fn ($p, $id) => $this->hasReference((int) $id), ARRAY_FILTER_USE_BOTH);
            if (! $lines) {
                if ($g['active'] && $g['name'] !== '' && $c->len >= 1000) {
                    $this->skipped[] = ['name' => $g['name'], 'kind' => $g['kind'], 'reason' => 'tracé '.strtoupper($g['origin']).' sans ligne VFF ('.round($c->len / 1000, 1).' km)', 'url' => null];
                }

                continue;
            }
            $lines = $this->pruneSecondaryLines($lines, (int) $c->len);
            $obs[] = [
                'id' => $g['origin'].':'.$c->grp.':'.$c->cid,
                'origin' => $g['origin'], 'names' => $g['name'] !== '' ? [$g['name']] : [], 'operator' => $g['operator'],
                'kind' => $g['kind'], 'active' => $g['active'], 'len' => (int) $c->len, 'lines' => $lines,
            ];
        }
        $bar->finish();
        $this->newLine();

        return $this->mergeObservations($obs);
    }

    /**
     * Portion du tracé de référence de chaque ligne VFF longée par des tronçons :
     * morceaux à moins de BUFFER_M m, trous < GAP_M comblés, morceaux < MIN_PIECE_M
     * ignorés, lignes à moins de MIN_LINE_M ignorées.
     *
     * @param  list<int>  $traceIds
     * @return array<int, array{ewkb: string, len: int}> par line_id, plus longue d'abord
     */
    private function portionsAlong(array $traceIds): array
    {
        $rows = DB::select(<<<'SQL'
            WITH c AS (
                SELECT ST_Collect(geom) AS g4 FROM source_traces WHERE id = ANY(CAST(:ids AS bigint[]))
            ), cb AS (
                SELECT g4, ST_Buffer(ST_Transform(g4, 2154), :buf) AS b FROM c
            ), parts AS MATERIALIZED (
                SELECT r.line_id, d.path AS part, d.geom AS rg, ST_Length(d.geom) AS plen
                FROM cb, line_reference_geometries r,
                     LATERAL ST_Dump(ST_LineMerge(ST_Transform(r.geom, 2154))) d
                WHERE r.geom && ST_Expand(cb.g4, 0.002)
            ), pieces AS (
                SELECT p.line_id, p.part, p.rg, p.plen, (ST_Dump(ST_Intersection(p.rg, cb.b))).geom AS pc
                FROM parts p, cb WHERE ST_Intersects(p.rg, cb.b)
            ), iv AS (
                SELECT line_id, part, plen,
                       LEAST(ST_LineLocatePoint(rg, ST_StartPoint(pc)), ST_LineLocatePoint(rg, ST_EndPoint(pc))) AS a,
                       GREATEST(ST_LineLocatePoint(rg, ST_StartPoint(pc)), ST_LineLocatePoint(rg, ST_EndPoint(pc))) AS b
                FROM pieces WHERE ST_GeometryType(pc) = 'ST_LineString' AND ST_Length(pc) >= 20
            ), brk AS (
                SELECT *, CASE WHEN (a - MAX(b) OVER w) * plen > :gap THEN 1 ELSE 0 END AS nb
                FROM iv WINDOW w AS (PARTITION BY line_id, part ORDER BY a ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING)
            ), grp AS (
                SELECT *, SUM(nb) OVER (PARTITION BY line_id, part ORDER BY a ROWS UNBOUNDED PRECEDING) AS g FROM brk
            ), merged AS (
                SELECT line_id, part, MIN(a) AS a, MAX(b) AS b FROM grp GROUP BY line_id, part, g
            ), subs AS (
                SELECT m.line_id, ST_LineSubstring(p.rg, m.a, m.b) AS s
                FROM merged m JOIN parts p ON p.line_id = m.line_id AND p.part = m.part
                WHERE (m.b - m.a) * p.plen >= :minpiece
            )
            SELECT line_id, ROUND(SUM(ST_Length(s)))::int AS len,
                   encode(ST_AsEWKB(ST_Multi(ST_Transform(ST_Collect(s), 4326))), 'hex') AS ewkb
            FROM subs GROUP BY line_id HAVING SUM(ST_Length(s)) >= :minline
            ORDER BY len DESC
            SQL, ['ids' => '{'.implode(',', $traceIds).'}', 'buf' => self::BUFFER_M, 'gap' => self::GAP_M,
            'minpiece' => self::MIN_PIECE_M, 'minline' => self::MIN_LINE_M]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id] = ['ewkb' => $r->ewkb, 'len' => (int) $r->len];
        }

        return $out;
    }

    /**
     * Lignes longées accessoirement (voie touristique parallèle à une ligne
     * exploitée à l'approche d'une gare) : écartées si la portion est courte
     * par rapport à l'exploitation et en service commercial selon SNCF Réseau.
     */
    private function pruneSecondaryLines(array $lines, int $clusterLen): array
    {
        $main = array_key_first($lines);
        foreach ($lines as $lineId => $p) {
            if ($lineId === $main || $p['len'] >= max(2000, 0.3 * $clusterLen)) {
                continue;
            }
            $active = DB::selectOne(<<<'SQL'
                SELECT COALESCE(SUM(ST_Length(ST_Intersection(ST_Transform(ST_GeomFromEWKB(decode(:g, 'hex')), 2154),
                         ST_Buffer(ST_Transform(s.geom, 2154), 200)))), 0) AS m
                FROM sncf_line_geometries s
                WHERE s.code_ligne = (SELECT code FROM lines WHERE id = :l) AND s.libelle ILIKE 'Exploit%'
                SQL, ['g' => $p['ewkb'], 'l' => $lineId])->m;
            if ((float) $active > 0.5 * $p['len'] || $p['len'] < 0.15 * $clusterLen) {
                unset($lines[$lineId]);
            }
        }

        return $lines;
    }

    /** Recouvrement (m) de deux portions d'une même ligne. */
    private function overlap(string $a, string $b): float
    {
        return (float) DB::selectOne("SELECT ST_Length(ST_Intersection(ST_Transform(ST_GeomFromEWKB(decode(?, 'hex')), 2154),
            ST_Buffer(ST_Transform(ST_GeomFromEWKB(decode(?, 'hex')), 2154), 30))) AS m", [$a, $b])->m;
    }

    /** Union de deux portions d'une même ligne. */
    private function union(string $a, string $b): array
    {
        $r = DB::selectOne("SELECT encode(ST_AsEWKB(ST_Multi(ST_LineMerge(ST_Union(ST_GeomFromEWKB(decode(?, 'hex')), ST_GeomFromEWKB(decode(?, 'hex')))))), 'hex') AS g", [$a, $b]);

        return ['ewkb' => $r->g, 'len' => $this->length($r->g)];
    }

    /** Portion A privée de ce qui recouvre B (morceaux restants >= MIN_PIECE_M), ou null. */
    private function minus(string $a, string $b): ?array
    {
        $r = DB::selectOne(<<<'SQL'
            SELECT encode(ST_AsEWKB(ST_Multi(ST_Transform(ST_Collect(d.geom), 4326))), 'hex') AS g, ROUND(SUM(ST_Length(d.geom)))::int AS len
            FROM ST_Dump(ST_LineMerge(ST_Difference(ST_Transform(ST_GeomFromEWKB(decode(:a, 'hex')), 2154),
                         ST_Buffer(ST_Transform(ST_GeomFromEWKB(decode(:b, 'hex')), 2154), 30)))) d
            WHERE ST_Length(d.geom) >= :minpiece
            SQL, ['a' => $a, 'b' => $b, 'minpiece' => self::MIN_PIECE_M]);

        return $r && $r->g && (int) $r->len >= self::MIN_LINE_M ? ['ewkb' => $r->g, 'len' => (int) $r->len] : null;
    }

    private function length(string $ewkb): int
    {
        return (int) round((float) DB::selectOne("SELECT ST_Length(ST_Transform(ST_GeomFromEWKB(decode(?, 'hex')), 2154)) AS m", [$ewkb])->m);
    }

    /**
     * Observations d'une même exploitation fusionnées (même type, même ligne,
     * recouvrement >= 60 % de la plus courte) : IGN « Chemin de Fer Touristique
     * du Vivarais » + « le Mastrou » + OSM… → une seule, noms cumulés.
     */
    private function mergeObservations(array $obs): array
    {
        usort($obs, fn ($a, $b) => [$a['origin'] !== 'ign', -$a['len']] <=> [$b['origin'] !== 'ign', -$b['len']]);
        $out = [];
        foreach ($obs as $o) {
            $merged = false;
            foreach ($out as &$t) {
                if ($t['kind'] !== $o['kind']) {
                    continue;
                }
                $common = array_intersect_key($t['lines'], $o['lines']);
                $ov = 0.0;
                foreach ($common as $lineId => $_) {
                    $ov += $this->overlap($t['lines'][$lineId]['ewkb'], $o['lines'][$lineId]['ewkb']);
                }
                $lt = array_sum(array_column($t['lines'], 'len'));
                $lo = array_sum(array_column($o['lines'], 'len'));
                // Même voie sous deux noms (« Chemin de Fer Touristique du Vivarais » / « le Mastrou ») :
                // recouvrement de la PLUS LONGUE ; deux vélorails distincts qui se partagent quelques
                // kilomètres restent distincts, sauf noms proches ou sources différentes (IGN + OSM).
                $sameNames = HeritageText::nameSimilarity(implode(' ', $t['names']), implode(' ', $o['names'])) >= 0.5;
                $otherSource = ! isset($t['origins'][$o['origin']]);
                if ($common && ($ov >= 0.6 * max($lt, $lo) || (($sameNames || $otherSource) && $ov >= 0.6 * min($lt, $lo)))) {
                    foreach ($o['lines'] as $lineId => $p) {
                        $t['lines'][$lineId] = isset($t['lines'][$lineId]) ? $this->union($t['lines'][$lineId]['ewkb'], $p['ewkb']) : $p;
                    }
                    $t['names'] = array_values(array_unique(array_merge($t['names'], $o['names'])));
                    $t['operator'] ??= $o['operator'];
                    $t['active'] = $t['active'] || $o['active'];
                    $t['origins'][$o['origin']] = true;
                    $merged = true;
                    break;
                }
            }
            unset($t);
            if (! $merged) {
                $out[] = $o + ['origins' => [$o['origin'] => true]];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // 5. Exploitations
    // ------------------------------------------------------------------

    /** Lignes VFF d'un lien d'article de ligne (titre Wikipédia, Wikidata, nom). */
    /** @var array{title: array<string, list<int>>, name: array<string, list<int>>}|null titres / noms → lignes VFF */
    private ?array $linkIndex = null;

    private function linesForLink(?string $link): array
    {
        if (! $link) {
            return [];
        }
        // Index lu une fois par passage (avant : variable static de méthode, figée pour tout le processus :
        // un 2e passage ne voyait ni les lignes créées ni les titres posés entre-temps).
        if ($this->linkIndex === null) {
            $idx = ['title' => [], 'name' => []];
            foreach (DB::table('lines')->get(['id', 'name', 'wikipedia_title']) as $l) {
                if ($l->wikipedia_title) {
                    $idx['title'][$this->parser->norm($l->wikipedia_title)][] = (int) $l->id;
                }
                $idx['name'][$this->parser->norm($l->name)][] = (int) $l->id;
            }
            foreach (DB::table('wikidata_lines')->whereNotNull('line_id')->whereNotNull('frwiki_title')->get(['line_id', 'frwiki_title']) as $w) {
                $idx['title'][$this->parser->norm($w->frwiki_title)][] = (int) $w->line_id;
            }
            $this->linkIndex = $idx;
        }
        $idx = $this->linkIndex;
        $titles = [$link];
        if (! empty($this->articles[$link]['title'])) {
            $titles[] = $this->articles[$link]['title']; // titre canonique (redirection suivie)
        }
        $ids = [];
        foreach ($titles as $t) {
            $n = $this->parser->norm($t);
            $ids = array_merge($ids, $idx['title'][$n] ?? [], $idx['name'][$n] ?? []);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Ligne VFF pouvant porter une exploitation : tracé de référence présent, et
     * pas une ligne militaire (voies de 1914-1918 dont l'alignement croise par
     * endroits une voie touristique actuelle sans rien avoir de commun avec elle).
     */
    /** @var array<int, int>|null lignes à tracé de référence (hors militaires), lues une fois par passage */
    private ?array $references = null;

    private function hasReference(int $lineId): bool
    {
        // Propriété remise à zéro à chaque passage (avant : variable static de méthode, figée pour
        // tout le processus : un second passage ignorait les tracés de référence ajoutés entre-temps).
        $this->references ??= array_flip(DB::table('line_reference_geometries as r')->join('lines as l', 'l.id', '=', 'r.line_id')
            ->where(fn ($q) => $q->whereNull('l.network')->orWhere('l.network', '<>', 'militaire'))
            ->pluck('r.line_id')->map(fn ($v) => (int) $v)->all());

        return isset($this->references[$lineId]);
    }

    /**
     * Exploitations = catalogue (Wikipédia, Wikidata) + observations (IGN, OSM)
     * + lignes-oubliees, rapprochés.
     */
    private function operations(array $catalog, array $lo, array $observations): array
    {
        // Lignes VFF de chaque entrée du catalogue.
        foreach ($catalog as &$e) {
            // Nom lié à l'article de la LIGNE (« Ligne d'Arles à Salon-de-Provence ») et non de
            // l'exploitation : ni son titre ni son infobox ne décrivent l'exploitation.
            if ($e['article'] && ($this->linesForLink($e['article']) || preg_match('/^(?:Ligne|Tramway|Raccordement)\s+(?:de|d\'|du|des)\s/u', $e['article']))) {
                $e['lines'][] = ['link' => $e['article'], 'label' => $e['article'], 'section' => '', 'from' => null, 'to' => null, 'pk_from' => null, 'pk_to' => null];
                $e['article'] = null;
            }
            foreach ($e['lines'] as &$l) {
                $l['line_ids'] = array_values(array_filter($this->linesForLink($l['link']), fn ($id) => $this->hasReference($id)));
            }
            unset($l);
            $e['line_ids'] = array_values(array_unique(array_merge(...array_map(fn ($l) => $l['line_ids'], $e['lines']) ?: [[]])));
        }
        unset($e);

        // Items Wikidata hors catalogue : entrées supplémentaires (rapprochées par le nom seulement).
        foreach ($this->wikidataOnly as $q => $title) {
            $ent = $this->entities[$q] ?? null;
            $label = $this->wdLabel($ent);
            if (! $label || preg_match(self::COMMERCIAL_RE, HeritageText::norm($label))) {
                continue;
            }
            $catalog[] = ['key' => 'wd:'.$q, 'name' => $label, 'article' => $title, 'kinds' => HeritageText::kindsOf($label) ?: ['tourist_train'],
                'type' => '', 'depot' => '', 'lines' => [], 'line_ids' => [], 'gauge' => '', 'status' => null, 'status_raw' => '',
                'region' => null, 'list_url' => null, 'rfn_only' => false, 'qid' => $q];
        }

        // Catalogue ↔ observations, de même TYPE : nom proche (fort), ou même ligne (faible : un
        // tracé IGN au nom différent, ou sans nom, sur la ligne d'une exploitation du catalogue).
        // Affectation gloutonne par score décroissant ; une entrée déjà pourvue d'un tracé de ce
        // type n'accepte plus que des correspondances fortes (deux exploitations sur une même ligne).
        $pairs = [];
        foreach ($observations as $oi => $o) {
            foreach ($catalog as $ci => $e) {
                if ($e['rfn_only'] || ! in_array($o['kind'], $e['kinds'], true)) {
                    continue;
                }
                $sim = 0.0;
                foreach ($o['names'] as $n) {
                    $sim = max($sim, HeritageText::nameSimilarity($n, $e['name']), $e['article'] ? HeritageText::nameSimilarity($n, $e['article']) : 0.0);
                }
                $sameLine = (bool) array_intersect(array_keys($o['lines']), $e['line_ids']);
                if ($sim < 0.55 && ! $sameLine) {
                    continue;
                }
                // Entrée localisée sur une AUTRE ligne : un nom seulement voisin (« Vélorail du Bazois
                // Morvan » / « Vélo-rail du Morvan ») passe après un tracé de la bonne ligne.
                $score = 3 * $sim + match (true) {
                    $sameLine => 1,
                    $e['line_ids'] !== [] => -1.5,
                    default => 0,
                };
                if ($sameLine && $sim < 0.55) {
                    // Correspondance par la seule ligne : départage par les extrémités de la section du
                    // catalogue présentes le long du tracé (Pontivy - Lambel-Camors ≠ Auray - Pontivy).
                    $score += 0.5 * $this->endpointsAlong($o, $e);
                }
                $pairs[] = ['oi' => $oi, 'ci' => $ci, 'score' => $score, 'strong' => $sim >= 0.8];
            }
        }
        usort($pairs, fn ($a, $b) => $b['score'] <=> $a['score']);
        $assign = []; // obs index → catalogue index
        $taken = [];  // catalogue index|type → true
        foreach ($pairs as $p) {
            $tk = $p['ci'].'|'.$observations[$p['oi']]['kind'];
            if (isset($assign[$p['oi']]) || (isset($taken[$tk]) && ! $p['strong'])) {
                continue;
            }
            $assign[$p['oi']] = $p['ci'];
            $taken[$tk] = true;
            if ($this->output->isVerbose()) {
                $o = $observations[$p['oi']];
                $this->line(sprintf('    %s [%s] %s → %s (%.2f)', $o['id'], $o['kind'], implode(' / ', $o['names']) ?: '(sans nom)', $catalog[$p['ci']]['name'], $p['score']));
            }
        }

        $ops = [];
        $usedCatalog = [];
        // Exploitations observées.
        foreach ($observations as $oi => $o) {
            $e = isset($assign[$oi]) ? $catalog[$assign[$oi]] : null;
            $key = $e ? $e['key'].'|'.$o['kind'] : 'obs:'.$o['kind'].':'.(HeritageText::norm($o['names'][0] ?? '') ?: 'sans-nom').':'.array_key_first($o['lines']);
            if (isset($ops[$key])) {
                // Deux observations du même type pour une même exploitation : portions cumulées.
                foreach ($o['lines'] as $lineId => $p) {
                    $ops[$key]['portions'][$lineId] = isset($ops[$key]['portions'][$lineId])
                        ? $this->union($ops[$key]['portions'][$lineId]['ewkb'], $p['ewkb']) + ['method' => 'trace']
                        : $p + ['method' => 'trace'];
                }
                $ops[$key]['names'] = array_values(array_unique(array_merge($ops[$key]['names'], $o['names'])));
                $ops[$key]['observed_active'] = $ops[$key]['observed_active'] || $o['active'];

                continue;
            }
            if ($e) {
                $usedCatalog[$assign[$oi]] = true;
            }
            $ops[$key] = $this->newOp($key, $o['kind'], $e, $o);
            foreach ($o['lines'] as $lineId => $p) {
                $ops[$key]['portions'][$lineId] = $p + ['method' => 'trace'];
                // Bornes nommées par le catalogue sur cette ligne (affichage « De X à Y »).
                foreach ($e['lines'] ?? [] as $l) {
                    if (in_array($lineId, $l['line_ids'], true) && $l['from'] && $l['to']) {
                        $ops[$key]['portions'][$lineId] += ['from' => $l['from'], 'to' => $l['to']];
                    }
                }
            }
            // Lignes du catalogue sans tracé observé (Puisaye-Forterre : Fontenoy - Saint-Sauveur) : sections nommées.
            if ($e && in_array($o['kind'], $e['kinds'], true)) {
                foreach ($e['lines'] as $l) {
                    foreach ($l['line_ids'] as $lineId) {
                        if (! isset($ops[$key]['portions'][$lineId]) && $l['from'] && $l['to']) {
                            $ops[$key]['portions'][$lineId] = ['from' => $l['from'], 'to' => $l['to'], 'pk_from' => null, 'pk_to' => null, 'method' => 'places'];
                        }
                    }
                }
            }
        }

        // Exploitations du catalogue sans tracé observé : sections nommées.
        foreach ($catalog as $ci => $e) {
            if (isset($usedCatalog[$ci]) || $e['rfn_only'] || $e['status'] === 'project') {
                continue;
            }
            $kind = HeritageText::mainKind($e['kinds']);
            $portions = [];
            foreach ($e['lines'] as $l) {
                $hasNames = $l['from'] && $l['to'];
                $hasPk = $l['pk_from'] !== null && $l['pk_to'] !== null;
                foreach ($l['line_ids'] as $lineId) {
                    if ($hasNames || $hasPk) {
                        $portions[$lineId] = ['from' => $hasNames ? $l['from'] : null, 'to' => $hasNames ? $l['to'] : null,
                            'pk_from' => $hasPk ? $l['pk_from'] : null, 'pk_to' => $hasPk ? $l['pk_to'] : null, 'method' => 'places'];
                    }
                }
            }
            if (! $portions) {
                if ($e['line_ids'] || $e['lines']) {
                    $this->skipped[] = ['name' => $e['name'], 'kind' => $kind, 'reason' => $e['line_ids'] ? 'section non précisée par le catalogue' : 'ligne de la liste Wikipédia absente de VFF ('.($e['lines'][0]['label'] ?? '?').')', 'url' => $e['list_url']];
                }

                continue;
            }
            $key = $e['key'].'|'.$kind;
            $ops[$key] = $this->newOp($key, $kind, $e, null);
            $ops[$key]['portions'] = $portions;
        }

        // Un vélorail sur la même voie qu'un train touristique : le train circule, la voie est en service.
        foreach ($ops as $k => &$v) {
            if ($v['kind'] !== 'velorail') {
                continue;
            }
            foreach ($ops as $k2 => $t) {
                if ($t['kind'] !== 'tourist_train') {
                    continue;
                }
                foreach (array_intersect_key($v['portions'], $t['portions']) as $lineId => $p) {
                    if (empty($p['ewkb']) || empty($t['portions'][$lineId]['ewkb'])) {
                        continue;
                    }
                    $rest = $this->minus($p['ewkb'], $t['portions'][$lineId]['ewkb']);
                    if ($rest === null) {
                        unset($v['portions'][$lineId]);
                        $ops[$k2]['shared_with'][] = $v['name'];
                    } else {
                        $v['portions'][$lineId] = $rest + array_diff_key($p, ['ewkb' => 1, 'len' => 1]);
                    }
                }
            }
        }
        unset($v);
        $ops = array_filter($ops, fn ($op) => $op['portions'] !== []);

        // lignes-oubliees : dates (et portions) des exploitations de la ligne, ou nouvelles exploitations.
        $sameSection = function (?string $a1, ?string $b1, ?string $a2, ?string $b2): bool {
            $n = fn (?string $s) => $this->parser->norm(preg_replace('/^(?:gare|halte|station)\s+(?:de la |de l\'|du |des |de |d\')/iu', '', trim((string) $s)));
            if (! $a1 || ! $b1 || ! $a2 || ! $b2) {
                return false;
            }
            $x = [$n($a1), $n($b1)];
            $y = [$n($a2), $n($b2)];
            sort($x);
            sort($y);

            return $x === $y;
        };
        foreach ($lo as $r) {
            // Exploitation déjà connue : même type, et même ligne, ou même nom, ou même section.
            $cands = [];
            foreach ($ops as $k => $op) {
                if ($op['kind'] !== $r['kind']) {
                    continue;
                }
                $sameLine = isset($op['portions'][$r['line_id']]);
                $sim = 0.0;
                foreach ($r['name'] ? $op['names'] : [] as $n) {
                    $sim = max($sim, HeritageText::nameSimilarity($n, $r['name']));
                }
                $sec = false;
                foreach ($op['portions'] as $p) {
                    $sec = $sec || $sameSection($p['from'] ?? null, $p['to'] ?? null, $r['from'], $r['to']);
                }
                if ($sameLine || $sim >= 0.66 || $sec) {
                    $cands[$k] = ($sameLine ? 1 : 0) + 2 * $sim + ($sec ? 1 : 0) + ($op['portions'][$r['line_id']]['len'] ?? 0) / 1e7;
                }
            }
            arsort($cands);
            $key = array_key_first($cands);
            if ($key === null && $r['kind'] === 'velorail') {
                // Vélorail sur une ligne parcourue par un train touristique : même voie (cf. ci-dessus).
                foreach ($ops as $k => $op) {
                    if ($op['kind'] === 'tourist_train' && isset($op['portions'][$r['line_id']]) && ! $r['end']) {
                        $ops[$k]['shared_with'][] = $r['name'] ?: 'un vélorail (lignes-oubliees)';

                        continue 2;
                    }
                }
            }
            if ($key === null) {
                if ($r['start'] === null && $r['end'] === null && ! ($r['from'] && $r['to'])) {
                    continue;
                }
                if (! $r['from'] || ! $r['to']) {
                    $this->skipped[] = ['name' => $r['name'] ?? $r['quote'], 'kind' => $r['kind'], 'reason' => 'mention lignes-oubliees sans portion', 'url' => $r['url']];

                    continue;
                }
                // Entrée du catalogue sans ligne VFF mais de même section (« Train touristique de l'Albret », Nérac - Mézin).
                $entry = null;
                foreach ($catalog as $e) {
                    foreach ($e['lines'] as $l) {
                        if (in_array($r['kind'], $e['kinds'], true) && $sameSection($l['from'], $l['to'], $r['from'], $r['to'])) {
                            $entry = $e;
                            break 2;
                        }
                    }
                }
                $key = $entry ? $entry['key'].'|'.$r['kind'] : 'lo:'.$r['page'].':'.$r['kind'].':'.HeritageText::norm($r['from'].' '.$r['to']);
                $ops[$key] ??= $this->newOp($key, $r['kind'], $entry, null);
                if (! $entry) {
                    $ops[$key]['name'] = $r['name'] ?: ucfirst(HeritageText::kindLabel($r['kind'])).' '.$r['from'].' - '.$r['to'];
                    $ops[$key]['status'] = $r['end'] ? 'ended' : 'active';
                }
                $ops[$key]['portions'][$r['line_id']] = ['from' => $r['from'], 'to' => $r['to'], 'pk_from' => null, 'pk_to' => null, 'method' => 'places'];
            }
            $op = &$ops[$key];
            $op['sources']['lignes-oubliees'] = $r['url'];
            if ($r['name'] && ! in_array($r['name'], $op['names'], true)) {
                $op['names'][] = $r['name'];
            }
            $rank = $r['origin'] === 'etat' ? 'lo-etat' : 'lo-note';
            if ($r['start']) {
                $op['starts'][] = $r['start'] + ['src' => $rank, 'url' => $r['url'], 'quote' => $r['quote']];
            }
            if ($r['end']) {
                $op['ends'][] = $r['end'] + ['src' => $rank, 'url' => $r['url'], 'quote' => $r['quote']];
                $op['status'] = 'ended';
            }
            if ($r['origin'] === 'etat' && ! $r['end']) {
                // « État actuel » : l'exploitation existait à la date de mise à jour de la fiche.
                $op['attested'][] = ['year' => $r['updated']['year'] ?? $this->year, 'src' => 'lignes-oubliees', 'url' => $r['url'], 'quote' => $r['quote']];
                $op['status'] ??= 'active';
            }
            if (! isset($op['portions'][$r['line_id']]['from']) && $r['from'] && $r['to'] && ($op['portions'][$r['line_id']]['method'] ?? '') === 'trace') {
                $op['portions'][$r['line_id']] += ['from' => $r['from'], 'to' => $r['to']];
            }
            unset($op);
        }

        return $ops;
    }

    /** Nouvelle exploitation (catalogue et/ou observation). */
    private function newOp(string $key, string $kind, ?array $e, ?array $o): array
    {
        // Observation d'un autre type que l'entrée du catalogue (vélorail d'un réseau de train touristique) : son nom d'abord.
        $catalogFirst = $e && (! $o || in_array($kind, $e['kinds'], true));
        // Nom de l'article (« Vélorail du Larzac ») plutôt que le libellé de la liste (« velorail du larzac »).
        $catalogName = $e ? (($e['article'] ?? null) && ! empty($this->articles[$e['article']]['content'])
            ? trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $this->articles[$e['article']]['title'])) : $e['name']) : null;
        $names = array_values(array_unique(array_filter($catalogFirst
            ? array_merge([$catalogName, $e['name'] ?? null], $o['names'] ?? [])
            : array_merge($o['names'] ?? [], [$catalogName]))));
        $op = [
            'key' => $key, 'kind' => $kind, 'name' => $names[0] ?? ucfirst(HeritageText::kindLabel($kind)), 'names' => $names,
            'operator' => $o['operator'] ?? null, 'status' => $e['status'] ?? null, 'article' => $e['article'] ?? null,
            'qid' => $e['qid'] ?? ($e && $e['article'] ? ($this->qids[$e['article']] ?? null) : null),
            'observed_active' => (bool) ($o['active'] ?? false), 'interruptions' => [],
            'portions' => [], 'starts' => [], 'ends' => [], 'attested' => [], 'sources' => [], 'shared_with' => [], 'line_titles' => [],
        ];
        if ($e && $e['list_url']) {
            $op['sources']['Wikipédia (liste)'] = $e['list_url'];
        }
        if ($e) {
            foreach ($e['lines'] as $l) {
                if ($l['link']) {
                    $op['line_titles'][] = $l['link'];
                }
            }
        }
        if ($o) {
            if (isset($o['origins']['ign'])) {
                $op['sources']['IGN BD TOPO'] = self::IGN_URL;
                if ($o['active']) {
                    $op['attested'][] = ['year' => $this->year, 'src' => 'IGN BD TOPO', 'url' => self::IGN_URL, 'quote' => 'Tronçons IGN BD TOPO « '.implode(' / ', $o['names'] ?: ['Vélo-rail']).' » (édition '.$this->year.')'];
                }
            }
            if (isset($o['origins']['osm'])) {
                $op['sources']['OpenStreetMap'] = 'https://www.openstreetmap.org/';
            }
        }
        if ($e && $e['status'] === 'active' && $e['list_url']) {
            $op['attested'][] = ['year' => $this->year, 'src' => 'Wikipédia (liste)', 'url' => $e['list_url'], 'quote' => 'Liste des chemins de fer touristiques de France : « '.$e['name'].' », '.$e['status_raw']];
        }
        // Article de l'exploitation : infobox + phrases datées.
        if ($e && $e['article'] && ($page = $this->articles[$e['article']] ?? null) && $page['content'] !== '') {
            $url = 'https://fr.wikipedia.org/wiki/'.str_replace(' ', '_', $page['title']);
            $op['sources']['Wikipédia (article)'] = $url;
            $facts = HeritageText::infoboxFacts($this->parser->parseInfobox($page['content']), $this->year);
            foreach (['start' => 'starts', 'end' => 'ends'] as $k => $list) {
                if (isset($facts[$k])) {
                    $op[$list][] = $facts[$k] + ['src' => 'infobox', 'url' => $url, 'quote' => 'Infobox de l\'article « '.$page['title'].' »'];
                }
            }
            if (! empty($facts['operator']) && $this->isOperatorName($facts['operator'])) {
                $op['operator'] ??= mb_substr($facts['operator'], 0, 120);
            }
            $text = $this->plainText($page);
            $needles = array_merge(HeritageText::nameTokens($e['name']), ['train touristique', 'velorail', 'velo rail', 'cyclo draisine', 'draisines', 'association', 'chemin de fer touristique', 'exploitation touristique']);
            $d = HeritageText::datesInText($text, $needles, $this->year);
            foreach (['start' => 'starts', 'end' => 'ends'] as $k => $list) {
                if (isset($d[$k])) {
                    $op[$list][] = $d[$k] + ['src' => 'article', 'url' => $url, 'quote' => $d['quote_'.$k]];
                }
            }
            foreach (HeritageText::interruptionsInText($text, $this->year) as $i) {
                $op['interruptions'][] = $i + ['url' => $url];
            }
        }
        // Wikidata.
        if ($op['qid'] && ($ent = $this->entities[$op['qid']] ?? null)) {
            $url = 'https://www.wikidata.org/wiki/'.$op['qid'];
            $op['sources']['Wikidata'] = $url;
            $start = $this->wdTime($ent, 'P580') ?? $this->wdTime($ent, 'P571');
            $official = $this->wdTime($ent, 'P1619');
            if (! $start && $official && $official['year'] >= HeritageText::MIN_YEAR) {
                $start = $official;
            }
            if ($start && $start['year'] >= HeritageText::MIN_YEAR && $start['year'] <= $this->year) {
                $op['starts'][] = $start + ['src' => 'wikidata', 'url' => $url, 'quote' => 'Wikidata '.$op['qid'].' (début / création)'];
            }
            $end = $this->wdTime($ent, 'P582') ?? $this->wdTime($ent, 'P576');
            $closure = $this->wdTime($ent, 'P3999');
            if (! $end && $closure && $start && $closure['date'] > $start['date']) {
                $end = $closure;
            }
            if ($end && $end['year'] >= HeritageText::MIN_YEAR && $end['year'] <= $this->year && (! $start || $end['date'] > $start['date'])) {
                $op['ends'][] = $end + ['src' => 'wikidata', 'url' => $url, 'quote' => 'Wikidata '.$op['qid'].' (fin / dissolution)'];
            }
            foreach ($ent['claims']['P137'] ?? [] as $c) {
                $id = $c['mainsnak']['datavalue']['value']['id'] ?? null;
                if ($id && ($label = $this->wdLabel($this->entities[$id] ?? null))) {
                    $op['operator'] ??= $label;
                    break;
                }
            }
        }

        return $op;
    }

    /** @var array<string, list<string>> gares (noms normalisés) le long de chaque observation */
    private array $placesNear = [];

    /** Nombre d'extrémités de section du catalogue qui sont des gares le long du tracé observé. */
    private function endpointsAlong(array $o, array $e): int
    {
        $key = fn (string $n) => $this->parser->norm(preg_replace('/^(?:gare|halte|station)\s+(?:de la |de l\'|du |des |de |d\')/iu', '', trim($n)));
        $names = [];
        foreach ($e['lines'] as $l) {
            foreach ([$l['from'], $l['to']] as $n) {
                if ($n && ($k = $key($n)) !== '') {
                    $names[$k] = true;
                }
            }
        }
        if (! $names) {
            return 0;
        }
        if (! isset($this->placesNear[$o['id']])) {
            $geoms = implode(',', array_map(fn ($p) => "ST_GeomFromEWKB(decode('".preg_replace('/[^0-9a-f]/i', '', $p['ewkb'])."', 'hex'))", $o['lines']));
            $rows = DB::select(<<<SQL
                WITH g AS (SELECT ST_Collect(ARRAY[{$geoms}]) AS g)
                SELECT s.name AS n FROM stations s, g WHERE s.geom && ST_Expand(g.g, 0.02) AND ST_DWithin(s.geom::geography, g.g::geography, 1500)
                UNION SELECT sg.libelle FROM sncf_gares sg, g
                WHERE sg.lat IS NOT NULL AND ST_SetSRID(ST_MakePoint(sg.lon, sg.lat), 4326) && ST_Expand(g.g, 0.02)
                  AND ST_DWithin(ST_SetSRID(ST_MakePoint(sg.lon, sg.lat), 4326)::geography, g.g::geography, 1500)
                SQL);
            $this->placesNear[$o['id']] = array_map(fn ($r) => ' '.$key((string) $r->n).' ', $rows);
        }
        $count = 0;
        foreach (array_keys($names) as $n) {
            foreach ($this->placesNear[$o['id']] as $place) {
                if (str_contains($place, ' '.$n.' ')) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /** Texte brut d'un article, sans ses tableaux (matériel roulant « en service depuis 1986 »…). */
    private function plainText(array $page): string
    {
        $w = $page['content'];
        for ($i = 0; $i < 5 && str_contains($w, '{|'); $i++) {
            $w = preg_replace('/\{\|(?:(?!\{\|).)*?\|\}/su', ' ', $w) ?? $w;
        }

        return $this->parser->toPlainText($w, $page['title']);
    }

    /** Un vrai nom d'exploitant (pas « Association loi de 1901 », « à but non lucratif », SNCF Réseau…). */
    private function isOperatorName(string $s): bool
    {
        $n = HeritageText::norm($s);

        return $n !== '' && ! preg_match('/^(?:association\s*)?(?:loi|de loi|a but|but non|non lucrati|sans but)|loi (?:de )?1901|^association$|^sarl$|^sas$|sncf|^rff$|^oui$|^non$/', $n);
    }

    /**
     * Dates retenues : meilleure source (DATE_RANK) puis meilleure précision ;
     * phrases datées des articles des LIGNES en dernier recours ; sinon
     * attestation (début inconnu) pour une exploitation en service.
     */
    private function chooseDates(array &$op): void
    {
        if (! $op['starts']) {
            // Articles des lignes parcourues : phrases citant l'exploitation (nom distinctif, ou type).
            $titles = $op['line_titles'];
            foreach (array_keys($op['portions']) as $lineId) {
                if ($t = DB::table('lines')->where('id', $lineId)->value('wikipedia_title')) {
                    $titles[] = $t;
                }
            }
            // Article de LIGNE : nom complet de l'exploitation ou mot-clé de son type (pas les mots
            // isolés du nom : « Allier », « Cantal » y désignent tout autre chose).
            $needles = array_merge(array_map(fn ($n) => HeritageText::norm($n), $op['names']), $op['shared_line'] ? [] : match ($op['kind']) {
                'velorail' => ['velorail', 'velo rail', 'velorails', 'velo rails', 'cyclo draisine', 'cyclodraisine', 'draisines', 'pedalorail', 'cyclorail'],
                'museum' => ['musee'],
                default => ['train touristique', 'chemin de fer touristique', 'trains touristiques', 'autorail touristique', 'exploitation touristique'],
            });
            foreach (array_unique($titles) as $t) {
                if (! isset($this->lineTitlesAvailable[$t]) && ! isset($this->articles[$t])) {
                    continue;
                }
                $page = $this->lineArticle($t);
                if (! $page) {
                    continue;
                }
                $text = $this->plainText($page);
                $d = HeritageText::datesInText($text, $needles, $this->year);
                $url = 'https://fr.wikipedia.org/wiki/'.str_replace(' ', '_', $page['title']);
                foreach (['start' => 'starts', 'end' => 'ends'] as $k => $list) {
                    if (isset($d[$k])) {
                        $op[$list][] = $d[$k] + ['src' => 'line-article', 'url' => $url, 'quote' => $d['quote_'.$k]];
                    }
                }
                if ($op['starts']) {
                    break;
                }
            }
        }
        $prec = ['day' => 0, 'month' => 1, 'year' => 2];
        $best = function (array $cands) use ($prec): ?array {
            if (! $cands) {
                return null;
            }
            usort($cands, fn ($a, $b) => [self::DATE_RANK[$a['src']] ?? 8, $prec[$a['precision']]] <=> [self::DATE_RANK[$b['src']] ?? 8, $prec[$b['precision']]]);
            $b = $cands[0];
            // Même année (et même mois) ailleurs, plus précise : « juin 1969 » (infobox) → « 14 juin 1969 » (texte).
            foreach ($cands as $c) {
                if ($prec[$c['precision']] < $prec[$b['precision']] && $c['year'] === $b['year']
                    && ($b['precision'] === 'year' || substr($c['date'], 0, 7) === substr($b['date'], 0, 7))) {
                    $b = $c + ['refines' => $b['src']];
                }
            }

            return $b;
        };
        $op['start'] = $best($op['starts']);
        // En activité aujourd'hui (tronçons IGN / OSM, liste Wikipédia, « État actuel » lignes-oubliees) :
        // une date de « fin » isolée est une interruption ou une erreur, pas une fin.
        $activeNow = $op['observed_active'] || ($op['status'] !== 'ended' && $op['attested']);
        $ends = array_values(array_filter($op['ends'], fn ($e) => ! $op['start'] || $e['date'] > $op['start']['date']));
        $op['end'] = $activeNow ? null : $best($ends);
        $op['interruptions'] = array_values(array_filter($op['interruptions'],
            fn ($i) => $op['start'] && $i['end']['date'] > $op['start']['date'] && (! $op['end'] || $i['restart']['date'] < $op['end']['date'])));
        if ($op['end']) {
            $op['status'] = 'ended';
        } elseif ($activeNow) {
            $op['status'] = 'active';
        }
        if (! $op['start'] && $op['status'] !== 'ended' && $op['attested']) {
            usort($op['attested'], fn ($a, $b) => $a['year'] <=> $b['year']);
            $a = $op['attested'][0];
            $op['start'] = ['date' => sprintf('%04d-01-01', $a['year']), 'precision' => 'year', 'year' => $a['year'],
                'src' => 'attested', 'url' => $a['url'], 'quote' => $a['quote'], 'attested_by' => $a['src']];
        }
    }

    // ------------------------------------------------------------------
    // 6. Écriture
    // ------------------------------------------------------------------

    private function confidence(array $d): string
    {
        if (isset($d['refines'])) {
            // « 14 juin 1969 » (texte) précisant « juin 1969 » (infobox) : la confiance de l'infobox.
            $d['src'] = $d['refines'];
        }

        return match (true) {
            $d['src'] === 'attested', in_array($d['src'], ['article', 'line-article'], true) => 'low',
            in_array($d['src'], ['lo-etat', 'infobox'], true) && $d['precision'] !== 'year' => 'high',
            default => 'medium',
        };
    }

    private function dateText(array $d): string
    {
        $months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        [$y, $m, $day] = array_map('intval', explode('-', $d['date']));

        return match ($d['precision']) {
            'day' => ($day === 1 ? '1er' : $day).' '.$months[$m].' '.$y,
            'month' => $months[$m].' '.$y,
            default => (string) $y,
        };
    }

    private function sourceLabel(string $src): string
    {
        return match ($src) {
            'lo-etat', 'lo-note' => 'lignes-oubliees',
            'infobox', 'article' => 'article Wikipédia de l\'exploitation',
            'line-article' => 'article Wikipédia de la ligne',
            'wikidata' => 'Wikidata',
            default => $src,
        };
    }

    /** @return array{ops: int, events: int, lines: array<int, true>, km: array<string, float>, pending: int} */
    private function write(array $ops): array
    {
        $written = ['ops' => 0, 'events' => 0, 'lines' => [], 'km' => [], 'pending' => 0, 'by_kind' => [], 'by_status' => []];
        $fps = [];
        $rows = [];
        foreach ($ops as $op) {
            if ($op['status'] === 'ended' && ! $op['end']) {
                $this->skipped[] = ['name' => $op['name'], 'kind' => $op['kind'], 'reason' => 'exploitation disparue sans date de fin connue', 'url' => reset($op['sources']) ?: null];

                continue;
            }
            if ($op['status'] !== 'active' && ! $op['end']) {
                // Tronçons IGN « Non exploité » sans autre source : activité arrêtée, ou pas encore ouverte.
                $this->skipped[] = ['name' => $op['name'], 'kind' => $op['kind'], 'reason' => 'exploitation non attestée en activité (IGN « non exploité ») et sans date de fin', 'url' => reset($op['sources']) ?: null];

                continue;
            }
            if ($op['status'] !== 'active' && ! $op['end']) {
                // Tronçons IGN « Non exploité » sans autre source : activité arrêtée, ou pas encore ouverte.
                $this->skipped[] = ['name' => $op['name'], 'kind' => $op['kind'], 'reason' => 'exploitation non attestée en activité (IGN « non exploité ») et sans date de fin', 'url' => reset($op['sources']) ?: null];

                continue;
            }
            if (! $op['start']) {
                $this->skipped[] = ['name' => $op['name'], 'kind' => $op['kind'], 'reason' => 'aucune date de début ni attestation', 'url' => reset($op['sources']) ?: null];

                continue;
            }
            $kindLabel = HeritageText::kindLabel($op['kind']);
            $who = $op['name'];
            if ($op['operator'] && HeritageText::nameSimilarity($op['operator'], $op['name']) < 1.0 && HeritageText::norm($op['operator']) !== HeritageText::norm($op['name'])) {
                $who .= ' (exploitant : '.$op['operator'].')';
            }
            $others = array_values(array_diff($op['names'], [$op['name']]));
            $alias = $others ? ' ; aussi nommé '.implode(', ', array_map(fn ($n) => '« '.$n.' »', array_slice($others, 0, 3))) : '';
            $shared = $op['shared_with'] ? ' ; voie partagée avec '.implode(', ', array_unique($op['shared_with'])) : '';
            $sources = implode(', ', array_keys($op['sources']));
            $countedOp = false;
            foreach ($op['portions'] as $lineId => $p) {
                if (! $this->inScope($lineId)) {
                    continue;
                }
                // « Gare de Riom-ès-Montagnes » → « Riom-ès-Montagnes » (affichage ; le résolveur sait retirer le préfixe).
                foreach (['from', 'to'] as $b) {
                    if (! empty($p[$b])) {
                        // Article en minuscule seulement : « Gare de La Bastide » → « La Bastide » (l'article fait partie du nom).
                        $p[$b] = preg_replace('/^(?:[Gg]are|[Hh]alte|[Ss]tation)\s+(?:de la |de l\'|de l’|du |des |de |d\'|d’)/u', '', trim($p[$b]));
                    }
                }
                $between = isset($p['from'], $p['to']) && $p['from'] && $p['to'] ? ' entre '.$p['from'].' et '.$p['to'] : (isset($p['pk_from']) && $p['pk_from'] !== null ? sprintf(' du PK %s au PK %s', number_format($p['pk_from'] / 1000, 3, ',', ''), number_format($p['pk_to'] / 1000, 3, ',', '')) : '');
                $s = $op['start'];
                $since = $s['src'] === 'attested'
                    ? ', en activité en '.$s['year'].' (date de début non trouvée ; attestation : '.$s['attested_by'].')'
                    : ', depuis '.($s['precision'] === 'year' ? '' : 'le ').$this->dateText($s).' (d\'après '.$this->sourceLabel($s['src'])
                        .(isset($s['sibling']) ? ', date de l\'activité '.$s['sibling'].' de la même exploitation' : '').')';
                $events = [['heritage_start', $s, "Exploitation associative : {$who}, {$kindLabel}{$between}{$since}{$alias}{$shared}. Sources : {$sources}."]];
                foreach ($op['interruptions'] as $n => $i) {
                    $d = ['src' => 'article', 'url' => $i['url'], 'quote' => $i['quote']];
                    $events[] = ['heritage_end', $i['end'] + $d, "Interruption de l'exploitation associative : {$who}, {$kindLabel}{$between}, en ".$this->dateText($i['end']).' (d\'après l\'article Wikipédia de l\'exploitation).', $n];
                    $events[] = ['heritage_start', $i['restart'] + $d, "Reprise de l'exploitation associative : {$who}, {$kindLabel}{$between}, le ".$this->dateText($i['restart']).' (d\'après l\'article Wikipédia de l\'exploitation).', $n];
                }
                if ($op['end']) {
                    $e = $op['end'];
                    $events[0][2] = "Exploitation associative : {$who}, {$kindLabel}{$between}, de ".$this->dateText($s).' à '.$this->dateText($e)." (d'après ".$this->sourceLabel($s['src'])."){$alias}. Sources : {$sources}.";
                    $events[] = ['heritage_end', $e, "Fin de l'exploitation associative : {$who}, {$kindLabel}{$between}, en ".$this->dateText($e).' (d\'après '.$this->sourceLabel($e['src']).'). Sources : '.$sources.'.'];
                }
                foreach ($events as $ev) {
                    [$type, $d, $desc] = $ev;
                    $fp = self::FP_PREFIX.substr(hash('sha256', $op['key'].'|'.$lineId.'|'.$type.(isset($ev[3]) ? '|i'.$ev[3] : '')), 0, 54);
                    $fps[] = $fp;
                    $rows[] = [
                        'fp' => $fp, 'geom' => $p['ewkb'] ?? null,
                        'data' => [
                            'line_id' => $lineId, 'type' => $type, 'heritage_kind' => $op['kind'],
                            'event_date' => $d['date'], 'date_precision' => $d['precision'],
                            'description' => mb_substr(str_replace(['—', '–'], '-', $desc), 0, 2000),
                            'source' => self::SOURCE, 'source_url' => mb_substr((string) $d['url'], 0, 500),
                            'source_quote' => mb_substr((string) $d['quote'], 0, 1000), 'confidence' => $this->confidence($d),
                            'section_from' => isset($p['from']) ? mb_substr((string) $p['from'], 0, 255) : null,
                            'section_to' => isset($p['to']) ? mb_substr((string) $p['to'], 0, 255) : null,
                            'section_pk_start_m' => isset($p['ewkb']) ? null : ($p['pk_from'] ?? null),
                            'section_pk_end_m' => isset($p['ewkb']) ? null : ($p['pk_to'] ?? null),
                            'section_resolution' => isset($p['ewkb']) ? 'trace' : 'pending',
                            'section_resolution_note' => isset($p['ewkb'])
                                ? mb_substr('Tracé exploité observé ('.implode(', ', array_intersect(array_keys($op['sources']), ['IGN BD TOPO', 'OpenStreetMap'])).') : '.implode(' / ', array_slice($op['names'], 0, 3)), 0, 500)
                                : null,
                            'section_length_m' => $p['len'] ?? null,
                            'updated_at' => now(),
                        ],
                    ];
                    $written['events']++;
                }
                $written['lines'][$lineId] = true;
                $written['km'][$op['kind']] = ($written['km'][$op['kind']] ?? 0) + ($p['len'] ?? 0) / 1000;
                if (! isset($p['ewkb'])) {
                    $written['pending']++;
                }
                $countedOp = true;
            }
            if ($countedOp) {
                $written['ops']++;
                $written['by_kind'][$op['kind']] = ($written['by_kind'][$op['kind']] ?? 0) + 1;
                $written['by_status'][$op['end'] ? 'ended' : 'active'] = ($written['by_status'][$op['end'] ? 'ended' : 'active'] ?? 0) + 1;
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('  --dry-run : rien n\'est écrit');

            return $written;
        }

        $pendingLines = [];
        DB::transaction(function () use ($rows, $fps, &$pendingLines, &$written) {
            foreach ($rows as $r) {
                $exists = DB::table('line_events')->where('fingerprint', $r['fp'])->exists();
                if ($exists) {
                    DB::table('line_events')->where('fingerprint', $r['fp'])->update($r['data']);
                } else {
                    DB::table('line_events')->insert($r['data'] + ['fingerprint' => $r['fp'], 'created_at' => now()]);
                }
                if ($r['geom']) {
                    DB::update("UPDATE line_events SET section_geom = ST_GeomFromEWKB(decode(?, 'hex')) WHERE fingerprint = ?", [$r['geom'], $r['fp']]);
                } else {
                    DB::update('UPDATE line_events SET section_geom = NULL WHERE fingerprint = ?', [$r['fp']]);
                    $pendingLines[$r['data']['line_id']] = true;
                }
            }
            $stale = DB::table('line_events')->where('fingerprint', 'like', self::FP_PREFIX.'%')->whereNotIn('fingerprint', $fps ?: ['-'])
                ->when($this->lineFilter, fn ($q) => $q->whereIn('line_id', $this->lineFilter));
            $written['deleted'] = $stale->delete();
        });

        // Portions nommées : résolues sur le tracé de la ligne (seulement nos événements).
        $resolver = app(SectionResolver::class);
        $written['resolution'] = [];
        foreach (array_keys($pendingLines) as $lineId) {
            foreach ($resolver->resolveLine((int) $lineId, self::SOURCE, 'pending') as $k => $n) {
                $written['resolution'][$k] = ($written['resolution'][$k] ?? 0) + $n;
            }
        }
        // Portion nommée introuvable sur la ligne (lien de ligne erroné dans la liste, gare absente) :
        // l'événement n'est pas gardé (il s'afficherait sur la fiche d'une ligne qui n'est pas la sienne).
        $unresolved = DB::table('line_events')->where('source', self::SOURCE)->where('section_resolution', 'unresolved')
            ->when($this->lineFilter, fn ($q) => $q->whereIn('line_id', $this->lineFilter));
        foreach ((clone $unresolved)->get(['line_id', 'type', 'description', 'section_resolution_note', 'heritage_kind', 'source_url']) as $u) {
            if ($u->type === 'heritage_start') {
                $this->skipped[] = ['name' => mb_substr(preg_replace('/^Exploitation associative : /u', '', $u->description), 0, 120), 'kind' => $u->heritage_kind,
                    'reason' => 'portion nommée introuvable sur sa ligne VFF (ligne '.$u->line_id.' : '.$u->section_resolution_note.')', 'url' => $u->source_url];
            }
        }
        $written['unresolved_deleted'] = $unresolved->delete();

        return $written;
    }

    // ------------------------------------------------------------------
    // Rapports
    // ------------------------------------------------------------------

    private function report(array $ops, array $written): void
    {
        $this->newLine();
        $this->line(sprintf('  %d exploitations écrites (%s), %d événements, %d lignes ; %s',
            $written['ops'],
            implode(', ', array_map(fn ($k, $n) => HeritageText::kindLabel($k).' : '.$n, array_keys($written['by_kind']), $written['by_kind'])),
            $written['events'], count($written['lines']),
            implode(', ', array_map(fn ($k, $km) => HeritageText::kindLabel($k).' '.round($km).' km', array_keys($written['km']), $written['km']))));
        if (isset($written['deleted'])) {
            $this->line("  {$written['deleted']} événements heritage obsolètes supprimés ; portions nommées : ".json_encode($written['resolution'] ?? []));
        }
        if (! empty($written['unresolved_deleted'])) {
            $this->line("  dont {$written['unresolved_deleted']} événements retirés aussitôt : portion nommée introuvable sur la ligne");
        }
        $reasons = array_count_values(array_map(fn ($s) => preg_replace('/\s*\(.*$/u', '', $s['reason']), $this->skipped));
        arsort($reasons);
        foreach ($reasons as $r => $n) {
            $this->line("  non écrit : {$n} × {$r}");
        }

        // CSV des exploitations (une ligne par exploitation × ligne VFF) + exploitations non écrites.
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['exploitation', 'type', 'statut', 'debut', 'source_debut', 'fin', 'line_id', 'ligne', 'portion', 'km', 'methode', 'exploitant', 'sources',
            'citation_debut', 'citation_fin', 'interruptions', 'autres_dates'], escape: '\\');
        $names = DB::table('lines')->pluck('name', 'id')->all();
        foreach ($ops as $op) {
            $others = implode(' ; ', array_map(fn ($d) => $d['src'].' '.$d['date'], array_merge($op['starts'], array_map(fn ($d) => $d + ['date' => 'fin '.$d['date']], $op['ends']))));
            foreach ($op['portions'] as $lineId => $p) {
                fputcsv($csv, [$op['name'], $op['kind'], $op['end'] ? 'disparue' : ($op['status'] ?? ''), $op['start']['date'] ?? '', $op['start']['src'] ?? '',
                    $op['end']['date'] ?? '', $lineId, $names[$lineId] ?? '', trim(($p['from'] ?? '').' - '.($p['to'] ?? ''), ' -'),
                    isset($p['len']) ? round($p['len'] / 1000, 1) : '', $p['method'], $op['operator'] ?? '', implode(' ', $op['sources']),
                    $op['start']['quote'] ?? '', $op['end']['quote'] ?? '',
                    implode(' ; ', array_map(fn ($i) => $i['end']['date'].' → '.$i['restart']['date'], $op['interruptions'])), $others], escape: '\\');
            }
        }
        foreach ($this->skipped as $s) {
            fputcsv($csv, [$s['name'], $s['kind'], 'NON ÉCRITE : '.$s['reason'], '', '', '', '', '', '', '', '', '', $s['url'] ?? ''], escape: '\\');
        }
        rewind($csv);
        Storage::disk('local')->put('reports/heritage-exploitations.csv', stream_get_contents($csv));
        Storage::disk('local')->put('reports/heritage-import.json', json_encode([
            'at' => now()->toIso8601String(), 'written' => array_diff_key($written, ['lines' => 1]) + ['lines' => count($written['lines'])],
            'skipped' => $reasons, 'stats' => $this->stats,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('  rapports : storage/app/reports/heritage-exploitations.csv, heritage-import.json');
    }
}
