<?php

namespace App\Console\Commands;

use App\Services\PlaceNames;
use App\Services\SecondaryNetworkParser;
use App\Services\SecondaryPlaceGeocoder;
use App\Services\WikimediaClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Dates des RÉSEAUX SECONDAIRES (intérêt local, chemins de fer départementaux,
 * tramways départementaux, voie métrique) depuis Wikipédia FR → line_events,
 * source = 'wikipedia-reseaux'.
 *
 * Pourquoi : ~1 300 lignes VFF (surtout les tracés archeoferroviaire du réseau
 * secondaire, sans code SNCF) n'ont aucune date d'ouverture ; le moteur
 * décennal ne peut donc pas les placer dans les ères historiques et le réseau
 * secondaire des années 1920-1930 n'apparaît qu'au tiers de sa longueur. Les
 * articles Wikipédia par RÉSEAU (« Chemins de fer départementaux de la
 * Somme », « Tramways de l'Ain », « Réseau breton »…) et par ligne d'intérêt
 * local (« Tramway d'Annecy à Thônes ») listent ces lignes avec leurs dates.
 * (Les dates et textes d'archeoferroviaire ne sont PAS utilisés ici : seuls ses
 * tracés, cf. database/data/source_rights.php.)
 *
 * Étapes :
 *   1. découverte des articles (cache storage/app/sources/secondary-networks) :
 *      catégories Wikipédia (anciennes compagnies de chemin de fer secondaire,
 *      anciens tramways par département, anciennes lignes à voie métrique,
 *      anciennes lignes ferroviaires par région…), liens des pages « Liste
 *      des chemins de fer à voie métrique de France »…, recherches par titre ;
 *      téléchargement groupé (50 titres par requête, quelques requêtes en
 *      parallèle, maxlag) ;
 *   2. extraction des « fiches » datées (App\Services\SecondaryNetworkParser) :
 *      itinéraire A - … - B + ouverture / fermeture aux voyageurs / aux
 *      marchandises / totale / déclassement / dépose ;
 *   3. géocodage des lieux (App\Services\SecondaryPlaceGeocoder : cible du
 *      lien wiki, gares, communes, contexte de l'article contre les homonymes) ;
 *   4. rapprochement avec les lignes VFF : les lieux de la fiche proches du
 *      tracé de référence (portion : ≤ 1,5 km d'une gare, ≤ 3 km d'un centre
 *      de commune), les EXTRÉMITÉS du tracé proches de deux lieux de la fiche
 *      (≤ 3 / 4 km, l'une à moins de 2,5 km), ou les noms (extrémités du nom
 *      VFF, commune la plus proche de chaque extrémité du tracé) ; contrôles de
 *      longueur (km de la fiche) et de sinuosité. Résultat par couple
 *      (fiche, ligne) : « full » (toute la ligne), « covers » (la ligne est
 *      une partie de l'itinéraire de la fiche : toute la ligne), « section »
 *      (la fiche est une portion de la ligne : section_from / section_to,
 *      résolue ensuite par vff:resolve-event-sections). Garde-fous : deux
 *      lignes DISTINCTES (tracés qui ne se recouvrent pas) également
 *      plausibles → aucun rattachement ; lignes hors réseau secondaire
 *      seulement en correspondance complète avec longueur concordante, jamais
 *      une ligne du RFN ; une fiche fille (section d'une ligne décrite) ne va
 *      qu'aux lignes de sa fiche parente, et nulle part si la parente est une
 *      ligne du RFN ;
 *   5. écriture, SEULEMENT pour les lignes sans ouverture exploitable d'une
 *      autre source ; pour une ligne déjà datée ailleurs mais SANS AUCUNE fin
 *      d'exploitation (ni fermeture totale, ni déclassement, ni dépose, ni
 *      voyageurs + marchandises), seules les fins des fiches sont écrites
 *      (fermetures, déclassement, dépose, à défaut la fin du réseau) : sans elles
 *      la ligne resterait en service jusqu'en 2020 puis disparaîtrait en 2030. Les phrases d'ensemble (« l'ensemble des lignes a été
 *      ouvert en 1904 », « ces lignes ferment le 31 décembre 1950 ») valent
 *      pour chaque ligne rattachée du réseau qui n'a pas sa propre date. Chaque
 *      portion ouverte doit aussi avoir une FIN (fermeture totale, déclassement,
 *      dépose, ou voyageurs + marchandises) : sinon la fin du réseau (infobox ou
 *      dernière fermeture citée, au plus tard, jamais au-delà de 1955 ni si elle
 *      est contredite par l'article), sinon la portion est écartée (rapport) :
 *      une ouverture sans fin laisserait la ligne en service jusqu'en 2020.
 *      Ouverture d'une ligne secondaire avant 1865 (avant la loi sur l'intérêt
 *      local) : à vérifier, non écrite. section_resolution = 'pending' ; une
 *      correspondance complète porte « toute la ligne » dans sa description
 *      (la source parle bien de la ligne entière). Licence CC BY-SA :
 *      source_url = permalien (oldid), source_quote ≤ 300 caractères ;
 *   6. ESTIMATIONS (source 'estimation-reseau', jamais des dates sûres) pour les
 *      lignes du réseau secondaire restées sans ouverture ou sans fin : dates de
 *      début / fin du RÉSEAU auquel la ligne appartient (réseau de ses fiches,
 *      sinon article de réseau dont les lieux géocodés longent le tracé), à
 *      l'année, confiance « low », section_resolution 'whole_line',
 *      description « Estimation … à confirmer ». Fin du réseau = fin du TRAFIC
 *      (phrase d'ensemble, champ « fermeture », introduction, dernière fermeture
 *      citée), jamais la dissolution de la compagnie ; pas d'estimation au-delà
 *      de 1960, pour une ligne inachevée (« inachevée », « projet » dans le nom
 *      du tracé archeoferroviaire : garde-fou, aucune donnée reprise), en
 *      chantier dans OHM, ni exploitée par une association (--no-estimate pour
 *      s'en passer) ;
 *   7. rapports storage/app/reports/reseaux-secondaires-*.csv : événements
 *      (écrits ou non, avec la raison et la source), fiches non rattachées /
 *      ambiguës / écartées, lignes déjà datées (écart avec la date connue),
 *      estimations (faites ou non, avec la raison) ;
 *      reseaux-secondaires-import.json : chiffres de la passe.
 *
 * --discover-undated : avant tout, une recherche Wikipédia par ligne secondaire
 * encore sans ouverture (« "A" "B" tramway OR "chemin de fer" », en cache,
 * une requête à la fois) ; les articles nouveaux rejoignent le cache
 * (titles-undated.json, repris par --refresh).
 *
 * Rejouable : fingerprint stable (ligne, type, date, portion) ; les
 * événements 'wikipedia-reseaux' et 'estimation-reseau' qui ne sont plus
 * produits sont supprimés (passe complète uniquement). Ensuite :
 *   php artisan vff:resolve-event-sections --source=wikipedia-reseaux --retry
 *
 *   php artisan vff:import-secondary-network-dates                 # cache s'il existe
 *   php artisan vff:import-secondary-network-dates --refresh       # relit Wikipédia
 *   php artisan vff:import-secondary-network-dates --dry-run --article="Tramways de l'Ain" -v
 */
class ImportSecondaryNetworkDates extends Command
{
    protected $signature = 'vff:import-secondary-network-dates
        {--refresh : Redécouvre et retélécharge les articles (sinon cache local)}
        {--from-cache : N\'utilise que le cache local, aucune requête réseau}
        {--article=* : Limite aux articles donnés (tests) - ni nettoyage}
        {--dry-run : N\'écrit rien en base, produit les rapports}
        {--parallel=4 : Requêtes Wikipédia simultanées}
        {--discover-undated : Cherche aussi des articles pour chaque ligne secondaire encore sans ouverture (recherche Wikipédia par extrémités, en cache) et les ajoute au cache}
        {--no-estimate : N\'écrit pas les estimations par réseau (source estimation-reseau)}';

    protected $description = 'Dates des réseaux secondaires (articles Wikipédia par réseau et par ligne) → line_events (source wikipedia-reseaux).';

    public const SOURCE = 'wikipedia-reseaux';

    /**
     * ESTIMATIONS par réseau (jamais des dates sûres) : ligne du réseau secondaire qu'aucune source ne
     * date, rattachée à un réseau par sa position ou par une fiche ; dates de début / fin de ce réseau,
     * à l'année, confiance « low ». Source inconnue d'EraTimeline::SOURCE_PRIORITY : rang le plus
     * faible, toute date sourcée l'emporte.
     */
    public const ESTIMATE_SOURCE = 'estimation-reseau';

    /** Catégories explorées (profondeur 3). */
    private const CATEGORIES = [
        'Catégorie:Ancienne compagnie de chemin de fer secondaire en France',
        'Catégorie:Ancien tramway en France par département',
        'Catégorie:Ancienne ligne à écartement métrique en France',
        'Catégorie:Ancienne ligne ferroviaire en France par région',
        'Catégorie:Compagnie de chemins de fer départementaux',
    ];

    /** Régions (anciennes et actuelles) : un article retenu doit être catégorisé en France. */
    private const FRENCH_CONTEXT = '/France|Alsace|Lorraine|Bretagne|Normandie|Picardie|Champagne|Ardenne|Limousin|Aquitaine|Occitanie|Midi-Pyrénées|Languedoc|Roussillon|Poitou|Franche-Comté|Bourgogne|Auvergne|Rhône-Alpes|Provence|Côte d.Azur|Corse|Île-de-France|Pays de la Loire|Centre-Val de Loire|Hauts-de-France|Grand Est|Nord-Pas-de-Calais|Savoie/u';

    /** Pages-listes dont tous les liens sont candidats. */
    private const LIST_PAGES = [
        'Liste des chemins de fer à voie métrique de France',
        "Liste d'anciennes lignes de chemin de fer et de tramway dans le Pas-de-Calais",
        'Liste des voies ferrées de la Manche',
        'Liste des lignes ferroviaires de Meurthe-et-Moselle',
        'Liste des tramways en France',
    ];

    /** Recherches par titre (articles hors catégories). */
    private const SEARCHES = [
        'intitle:"chemins de fer départementaux"', 'intitle:"chemins de fer économiques"', 'intitle:"voies ferrées"',
        'intitle:"tramways de"', 'intitle:"chemins de fer vicinaux"', 'intitle:"tramways à vapeur"',
        'intitle:"chemin de fer d\'intérêt local"', 'intitle:"réseau" intitle:"CFD"', 'intitle:"chemins de fer du"',
        'intitle:"chemins de fer de la"', 'intitle:"tramway de" intitle:"à"', 'intitle:"chemin de fer de" intitle:"à"',
        'intitle:"compagnie des tramways"', 'intitle:"société des chemins de fer"', 'intitle:"compagnie des chemins de fer"',
    ];

    /** Titres écartés (gares, ouvrages, matériel, personnes…). */
    private const EXCLUDE_TITLES = '/^(Gare|Halte|Station|Arrêt|Dépôt|Viaduc|Pont|Passerelle|Tunnel|Matériel|Locomotive|Automotrice|Voiture|Wagon|Liste des gares|Schéma|Musée|Association|Atelier|Remise|Ouvrage|Accident|Catastrophe|Parc|Jardins?|Voie verte|Petite Ceinture du|Funiculaire|Métro|Autobus|Bus|Trolleybus)\b|^(X|Z|AMG|ANF|SACM|Corpet|Pinguely|Renault|Type|Weidknecht|La Métallurgique|Léon|BB|CC|Ligne T\d|Ligne \d+ du)\b|\b(Belgique|Suisse|Algérie|Tunisie|Maroc|Indochine|Congo|Cochinchine|Réunion|Guadeloupe|Martinique|Luxembourg|Genève|Lausanne|Bâle|Bruxelles)\b/u';

    /** Titres retenus (doivent parler d'une ligne ou d'un réseau). */
    private const INCLUDE_TITLES = '/\b(chemins? de fer|tramways?|ligne|lignes|réseau|voies? ferrées?|compagnie|société|régie|CFD|CEN|VFD|VFDM|SE\b|tacot|train|transcorrézien|PO-Corrèze|petit anjou|raccordement|embranchement|voie-mère|électrique|meusien|argonnenbahn)/iu';

    /** Lieu de la fiche « sur » le tracé (m). */
    private const ON_LINE_M = 3500;

    /** Extrémité du tracé « à » un lieu de la fiche (m). */
    private const END_M = 4000;

    private string $cacheDir;

    private array $report = ['review' => []];

    private array $stats = [];

    public function __construct(private WikimediaClient $wiki, private SecondaryNetworkParser $parser, private SecondaryPlaceGeocoder $geo)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ini_set('memory_limit', '2G');
        // État d'exécution remis à zéro : un 2e appel dans le même processus doublait les fiches « à vérifier »
        // et les compteurs du bilan, et gardait des recouvrements calculés sur des tracés qui ont pu changer.
        $this->report = ['review' => []];
        $this->stats = [];
        $this->nameIndex = [];
        $this->recordErrors = [];
        $this->overlapCache = [];
        $this->cacheDir = storage_path('app/sources/secondary-networks');
        @mkdir($this->cacheDir, 0775, true);

        // 1. Articles
        $pages = $this->pages();
        if ($this->option('discover-undated') && ! $this->option('from-cache')) {
            $pages = $this->discoverUndated($pages);
        }
        if ($only = $this->option('article')) {
            $pages = array_intersect_key($pages, array_flip($only));
        }
        $this->info('Articles : '.count($pages));

        // 2-3. Fiches + géocodage
        $this->geo->load();
        $records = [];
        $articles = [];
        $foreign = 0;
        foreach ($pages as $title => $page) {
            // Articles hors de France (tramways étrangers remontés par les recherches) : écartés.
            preg_match_all('/\[\[\s*Catégorie\s*:([^\]|]+)/u', $page['content'], $cats);
            $catText = implode(' ; ', $cats[1]);
            $lead = mb_substr($page['content'], 0, 4000);
            if (! preg_match(self::FRENCH_CONTEXT, $catText) && ! SecondaryPlaceGeocoder::departementsIn($catText, true)
                && ! preg_match('/\b(pays\s*=\s*(?:\{\{)?France|SNCF|département|Compagnie des chemins de fer (?:du Nord|de l\'Est|de l\'Ouest|du Midi|de Paris à)|intérêt local)\b/u', $lead)
                && ! SecondaryPlaceGeocoder::departementsIn($lead, true)) {
                $foreign++;

                continue;
            }
            $parsed = $this->parser->parse($page['content'], $page['title']);
            $ctx = $this->articleContext($page['title'], $parsed);
            $articles[$page['title']] = ['url' => WikimediaClient::permalink($page['title'], $page['revid']), 'network' => $parsed['network'], 'ctx' => $ctx, 'single' => $parsed['single'] !== null];
            foreach ($parsed['records'] as $r) {
                $r['article'] = $page['title'];
                $r['key'] = $page['title'].'#'.$r['id'];
                $r['parent_key'] = $r['parent'] !== null ? $page['title'].'#'.$r['parent'] : null;
                foreach ($r['places'] as &$p) {
                    $p['geo'] = $this->geo->geocode($p['label'], $p['target'], $ctx['center'], $ctx['deps']);
                }
                unset($p);
                // Homonymes restés ambigus : départagés par les autres lieux de la même fiche.
                $known = array_filter(array_column($r['places'], 'geo'));
                if ($known && count($known) < count($r['places'])) {
                    $local = ['lon' => array_sum(array_column($known, 'lon')) / count($known), 'lat' => array_sum(array_column($known, 'lat')) / count($known)];
                    foreach ($r['places'] as &$p) {
                        if (! $p['geo']) {
                            $g = $this->geo->geocode($p['label'], $p['target'], $local, []);
                            if ($g && $this->geo->meters([$g['lon'], $g['lat']], [$local['lon'], $local['lat']]) < 60000) {
                                $p['geo'] = $g;
                            }
                        }
                    }
                    unset($p);
                }
                $records[$r['key']] = $r;
            }
        }
        $this->stats['articles_foreign_skipped'] = $foreign;
        $withEvents = array_filter($records, fn ($r) => $r['events']);
        $this->stats['records'] = count($records);
        $this->stats['records_with_events'] = count($withEvents);
        $this->stats['articles_with_events'] = count(array_unique(array_column($withEvents, 'article')));
        $this->line(sprintf('  %d fiches, dont %d datées (%d articles)', count($records), count($withEvents), $this->stats['articles_with_events']));

        // Fiches extraites (diagnostic, rejouable sans réseau).
        file_put_contents($this->cacheDir.'/records.json', json_encode(array_values(array_map(fn ($r) => [
            'key' => $r['key'], 'parent' => $r['parent_key'], 'origin' => $r['origin'], 'km' => $r['km'],
            'places' => array_map(fn ($p) => [$p['label'], $p['target'], $p['geo'] ? [$p['geo']['kind'], $p['geo']['name'], $p['geo']['dep'], round($p['geo']['lon'], 5), round($p['geo']['lat'], 5)] : null], $r['places']),
            'events' => array_map(fn ($e) => [$e['type'], $e['date'], $e['precision']], $r['events']),
        ], $records)), JSON_UNESCAPED_UNICODE));

        // 4. Rapprochement
        $lines = $this->loadLines();
        $this->line('  Lignes VFF avec tracé de référence : '.count($lines).' (dont '.count(array_filter($lines, fn ($l) => ! $l->dated)).' sans ouverture exploitable)');
        $matches = $this->matchAll($withEvents, $records, $lines, $articles);

        // 5. Événements par ligne
        $plan = $this->plan($matches, $records, $lines, $articles);
        // 6. Estimations par réseau pour les lignes secondaires restées sans date (jamais en passe partielle).
        if (! $this->option('no-estimate') && ! $this->option('article')) {
            $this->estimate($plan, $records, $lines, $articles);
        }
        $this->writeReports($plan);
        if ($this->option('dry-run')) {
            $this->summary($plan, true);

            return self::SUCCESS;
        }
        $this->write($plan);
        $this->summary($plan, false);

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // 1. Découverte et téléchargement
    // ------------------------------------------------------------------

    /** @return array<string, array{title: string, revid: int, content: string}> */
    private function pages(): array
    {
        $file = $this->cacheDir.'/pages.json';
        if (is_file($file) && ! $this->option('refresh')) {
            return json_decode(file_get_contents($file), true);
        }
        if ($this->option('from-cache')) {
            $this->error('Pas de cache : '.$file);

            return [];
        }
        $titles = $this->discover();
        $this->line('  Titres retenus : '.count($titles));
        $pages = [];
        $chunks = array_chunk($titles, 50);
        $parallel = max(1, (int) $this->option('parallel'));
        foreach (array_chunk($chunks, $parallel) as $group) {
            $responses = Http::pool(fn (Pool $pool) => array_map(fn ($chunk) => $pool->withUserAgent(WikimediaClient::USER_AGENT)->timeout(90)
                ->asForm()->post(WikimediaClient::FRWIKI_API, [
                    'action' => 'query', 'prop' => 'revisions', 'rvprop' => 'content|ids', 'rvslots' => 'main',
                    'redirects' => 1, 'titles' => implode('|', $chunk), 'format' => 'json', 'formatversion' => 2, 'maxlag' => 5,
                ]), $group));
            foreach ($group as $i => $chunk) {
                $res = $responses[$i] ?? null;
                $json = $res instanceof Response && $res->ok() ? $res->json() : null;
                if (! is_array($json) || isset($json['error']) || isset($json['continue'])) {
                    // Retard des serveurs ou réponse partielle : reprise séquentielle polie.
                    foreach ($this->wiki->fetchPages($chunk) as $t => $p) {
                        $pages[$p['title']] = $p;
                    }

                    continue;
                }
                foreach ($json['query']['pages'] ?? [] as $p) {
                    $content = $p['revisions'][0]['slots']['main']['content'] ?? null;
                    if (! empty($p['missing']) || ! $content) {
                        continue;
                    }
                    $pages[$p['title']] = ['title' => $p['title'], 'revid' => (int) $p['revisions'][0]['revid'], 'content' => (string) $content];
                }
            }
        }
        ksort($pages);
        file_put_contents($file, json_encode($pages, JSON_UNESCAPED_UNICODE));
        $this->line('  Articles téléchargés : '.count($pages));

        return $pages;
    }

    /** @return list<string> */
    private function discover(): array
    {
        $found = [];
        // Catégories, niveau par niveau, requêtes groupées en parallèle.
        $seen = [];
        $level = self::CATEGORIES;
        for ($depth = 0; $depth <= 3 && $level; $depth++) {
            $next = [];
            foreach (array_chunk($level, max(1, (int) $this->option('parallel'))) as $group) {
                $results = [];
                foreach ($group as $cat) {
                    $seen[$cat] = true;
                }
                $responses = Http::pool(fn (Pool $pool) => array_map(fn ($cat) => $pool->withUserAgent(WikimediaClient::USER_AGENT)->timeout(60)
                    ->get(WikimediaClient::FRWIKI_API, ['action' => 'query', 'list' => 'categorymembers', 'cmtitle' => $cat, 'cmlimit' => 500,
                        'format' => 'json', 'formatversion' => 2, 'maxlag' => 5]), $group));
                foreach ($group as $i => $cat) {
                    $res = $responses[$i] ?? null;
                    $json = $res instanceof Response && $res->ok() ? $res->json() : null;
                    if (! is_array($json) || isset($json['error']) || isset($json['continue'])) {
                        $json = ['query' => ['categorymembers' => $this->categoryMembers($cat)]];
                    }
                    foreach ($json['query']['categorymembers'] ?? [] as $m) {
                        if ((int) $m['ns'] === 14 && ! isset($seen[$m['title']])) {
                            $next[$m['title']] = true;
                        } elseif ((int) $m['ns'] === 0) {
                            $found[$m['title']] = true;
                        }
                    }
                }
            }
            $level = array_keys($next);
        }
        // Liens des pages-listes.
        foreach (self::LIST_PAGES as $page) {
            $continue = [];
            do {
                $json = $this->wiki->api(['action' => 'query', 'prop' => 'links', 'titles' => $page, 'plnamespace' => 0, 'pllimit' => 'max'] + $continue);
                foreach ($json['query']['pages'] ?? [] as $p) {
                    foreach ($p['links'] ?? [] as $l) {
                        $found[$l['title']] = true;
                    }
                }
                $continue = $json['continue'] ?? [];
            } while ($continue);
            $found[$page] = true;
        }
        // Recherches par titre.
        foreach (self::SEARCHES as $q) {
            foreach ($this->wiki->search($q, 100) as $t) {
                $found[$t] = true;
            }
        }
        // Articles trouvés par la recherche ciblée (--discover-undated) : gardés d'une passe à l'autre.
        if (is_file($this->cacheDir.'/titles-undated.json')) {
            foreach (json_decode(file_get_contents($this->cacheDir.'/titles-undated.json'), true) ?: [] as $t) {
                $found[$t] = true;
            }
        }
        $titles = array_values(array_filter(array_keys($found), fn ($t) => preg_match(self::INCLUDE_TITLES, $t) && ! preg_match(self::EXCLUDE_TITLES, $t)));
        sort($titles);
        file_put_contents($this->cacheDir.'/titles.json', json_encode($titles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $titles;
    }

    /**
     * Recherche ciblée pour les lignes du réseau secondaire encore sans ouverture : une requête
     * Wikipédia par ligne (« "A" "B" tramway OR "chemin de fer" », extrémités du nom VFF), mise
     * en cache 7 jours, une requête à la fois (pause de 0,3 s). Les articles trouvés qui passent
     * les filtres de titre et ne sont pas encore en cache sont téléchargés et ajoutés à pages.json.
     *
     * @param  array<string, array{title: string, revid: int, content: string}>  $pages
     * @return array<string, array{title: string, revid: int, content: string}>
     */
    private function discoverUndated(array $pages): array
    {
        $rows = DB::select("SELECT l.name FROM lines l JOIN line_reference_geometries r ON r.line_id = l.id
            WHERE l.network = 'secondaire' AND NOT EXISTS (SELECT 1 FROM line_events e WHERE e.line_id = l.id
                AND e.source <> ? AND (e.type LIKE 'opening%' OR e.type LIKE 'reopening%'))", [self::ESTIMATE_SOURCE]);
        $found = [];
        $queries = 0;
        foreach ($rows as $r) {
            $parsed = PlaceNames::parseLineName((string) $r->name);
            if (! $parsed || count($parsed['places']) < 2) {
                continue;
            }
            $a = trim(preg_replace('/\s*\(.*?\)/u', '', $parsed['places'][0]));
            $b = trim(preg_replace('/\s*\(.*?\)/u', '', $parsed['places'][count($parsed['places']) - 1]));
            if (mb_strlen($a) < 3 || mb_strlen($b) < 3) {
                continue;
            }
            $titles = $this->wiki->search('"'.$a.'" "'.$b.'" tramway OR "chemin de fer"', 10);
            $queries++;
            Sleep::usleep(300000);
            foreach ($titles as $t) {
                if (! isset($pages[$t]) && self::isRailTitle($t)) {
                    $found[$t] = true;
                }
            }
        }
        $this->line(sprintf('  Recherche ciblée : %d lignes sans ouverture, %d nouveaux articles candidats', $queries, count($found)));
        if ($found) {
            foreach ($this->wiki->fetchPages(array_keys($found)) as $p) {
                $pages[$p['title']] = $p;
            }
            ksort($pages);
            file_put_contents($this->cacheDir.'/pages.json', json_encode($pages, JSON_UNESCAPED_UNICODE));
            $known = is_file($this->cacheDir.'/titles-undated.json') ? (json_decode(file_get_contents($this->cacheDir.'/titles-undated.json'), true) ?: []) : [];
            $all = array_values(array_unique(array_merge($known, array_keys($found))));
            sort($all);
            file_put_contents($this->cacheDir.'/titles-undated.json', json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->line('  Articles en cache : '.count($pages));
        }

        return $pages;
    }

    /**
     * Titre retenu par la recherche ciblée : une ligne ou un réseau FERRÉ (tramway compris), jamais
     * un réseau d'autocars, une chronologie annuelle, un constructeur ou une association.
     */
    public static function isRailTitle(string $t): bool
    {
        return preg_match(self::INCLUDE_TITLES, $t) && ! preg_match(self::EXCLUDE_TITLES, $t)
            && preg_match('/\b(chemins? de fer|tramways?|ligne (?:de |d[\'’]|du |des )|lignes? secondaires?|voies? ferrées?|CFD|tacot|funiculaire|crémaillère|réseau breton|compagnie|société|régie)/iu', $t)
            && ! preg_match('/^(Liste|Gare|Ligne \d|Ligne [A-Z]\b|Transports? |Route|Autoroute|Chronologie|Canton)|\b(bus|autobus|autocars?|cars\b|routi\w+|Zou|navettes?|maritimes?|interurbain|dans les chemins de fer|mines?\b|locomotives?|constructions?|musées?|touristiques?|Réseau express|RER|métro)\b/iu', $t);
    }

    private function categoryMembers(string $cat): array
    {
        $out = [];
        $continue = [];
        do {
            $json = $this->wiki->api(['action' => 'query', 'list' => 'categorymembers', 'cmtitle' => $cat, 'cmlimit' => 500] + $continue);
            array_push($out, ...($json['query']['categorymembers'] ?? []));
            $continue = $json['continue'] ?? [];
        } while ($continue);

        return $out;
    }

    // ------------------------------------------------------------------
    // 3. Contexte géographique d'un article
    // ------------------------------------------------------------------

    /** @return array{center: ?array{lon: float, lat: float}, deps: list<string>} */
    private function articleContext(string $title, array $parsed): array
    {
        $deps = SecondaryPlaceGeocoder::departementsIn($title, true);
        if (preg_match('/breton|armoricain|Bretagne/u', $title)) {
            $deps = array_merge($deps, ['22', '29', '35', '56']);
        }
        if (preg_match('/Charentes/u', $title)) {
            $deps = array_merge($deps, ['16', '17']);
        }
        // Toponymes non ambigus de l'article (un seul lieu en France).
        $pts = [];
        foreach ($parsed['records'] as $r) {
            foreach ($r['places'] as $p) {
                $c = $this->geo->candidates($p['label'], $p['target']);
                if (! $c) {
                    continue;
                }
                $ok = true;
                foreach ($c as $x) {
                    if ($this->geo->meters([$x['lon'], $x['lat']], [$c[0]['lon'], $c[0]['lat']]) > 6000) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $pts[] = [$c[0]['lon'], $c[0]['lat'], $c[0]['dep']];
                }
            }
        }
        $center = null;
        if ($deps) {
            $cs = array_filter(array_map(fn ($d) => $this->geo->departementCentroid($d), $deps));
            if ($cs) {
                $center = ['lon' => array_sum(array_column($cs, 0)) / count($cs), 'lat' => array_sum(array_column($cs, 1)) / count($cs)];
            }
        }
        if (! $center && count($pts) >= 2) {
            $lons = array_column($pts, 0);
            $lats = array_column($pts, 1);
            sort($lons);
            sort($lats);
            $center = ['lon' => $lons[intdiv(count($lons), 2)], 'lat' => $lats[intdiv(count($lats), 2)]];
            // Départements majoritaires des toponymes non ambigus.
            $count = array_count_values(array_filter(array_column($pts, 2)));
            arsort($count);
            foreach ($count as $d => $n) {
                if ($n >= max(2, 0.25 * count($pts))) {
                    $deps[] = (string) $d;
                }
            }
        }

        return ['center' => $center, 'deps' => array_values(array_unique($deps))];
    }

    // ------------------------------------------------------------------
    // 4. Rapprochement fiches ↔ lignes VFF
    // ------------------------------------------------------------------

    /** @return array<int, object> line_id → ligne (extrémités, noms, datée ou non) */
    private function loadLines(): array
    {
        DB::statement('DROP TABLE IF EXISTS tmp_sec_lines');
        DB::statement('CREATE TEMP TABLE tmp_sec_lines AS
            SELECT r.line_id, ST_Transform(r.geom, 2154) AS g, r.length_m FROM line_reference_geometries r');
        DB::statement('CREATE INDEX ON tmp_sec_lines USING GIST (g)');
        DB::statement('ANALYZE tmp_sec_lines');
        $src = self::SOURCE;
        $est = self::ESTIMATE_SOURCE;
        $rows = DB::select("
            SELECT l.id, l.name, l.network, l.slug, l.code, l.alt_names, t.length_m,
                   EXISTS (SELECT 1 FROM line_events e WHERE e.line_id = l.id AND e.source NOT IN ('{$src}', '{$est}')
                       AND (e.type LIKE 'opening%' OR e.type LIKE 'reopening%' OR e.type = 'heritage_start')
                       AND (e.section_resolution IN ('pk', 'places', 'trace', 'whole_line')
                            OR (e.section_resolution IS NULL AND e.section_from IS NULL AND e.section_to IS NULL AND e.section_pk_start_m IS NULL))) AS dated,
                   (SELECT MIN(e.event_date)::text FROM line_events e WHERE e.line_id = l.id AND e.source NOT IN ('{$src}', '{$est}')
                       AND (e.type LIKE 'opening%' OR e.type LIKE 'reopening%')) AS first_opening,
                   (SELECT string_agg(DISTINCT e.type, ',') FROM line_events e WHERE e.line_id = l.id AND e.source NOT IN ('{$src}', '{$est}')
                       AND e.type IN ('closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling', 'conversion')
                       AND (e.section_resolution IN ('pk', 'places', 'trace', 'whole_line')
                            OR (e.section_resolution IS NULL AND e.section_from IS NULL AND e.section_pk_start_m IS NULL))) AS other_closures,
                   (SELECT MAX(e.event_date)::text FROM line_events e WHERE e.line_id = l.id AND e.source NOT IN ('{$src}', '{$est}')
                       AND e.type IN ('closure', 'declassification', 'dismantling', 'conversion')) AS last_closure,
                   (SELECT MAX(e.event_date)::text FROM line_events e WHERE e.line_id = l.id AND e.source NOT IN ('{$src}', '{$est}')
                       AND (e.type LIKE 'opening%' OR e.type LIKE 'reopening%')
                       AND (e.section_resolution IN ('pk', 'places', 'trace', 'whole_line')
                            OR (e.section_resolution IS NULL AND e.section_from IS NULL AND e.section_pk_start_m IS NULL))) AS last_opening
            FROM lines l JOIN tmp_sec_lines t ON t.line_id = l.id ORDER BY l.id");
        $lines = [];
        foreach ($rows as $r) {
            $r->dated = (bool) $r->dated;
            $r->parts = [];
            $lines[(int) $r->id] = $r;
        }
        // Extrémités : bouts des morceaux fusionnés ; on garde la paire la plus éloignée.
        foreach (DB::select('SELECT line_id, ST_X(ST_StartPoint(d.geom)) AS x1, ST_Y(ST_StartPoint(d.geom)) AS y1,
                ST_X(ST_EndPoint(d.geom)) AS x2, ST_Y(ST_EndPoint(d.geom)) AS y2
            FROM tmp_sec_lines t, LATERAL ST_Dump(ST_LineMerge(t.g)) d') as $p) {
            if (isset($lines[(int) $p->line_id])) {
                $lines[(int) $p->line_id]->parts[] = [[(float) $p->x1, (float) $p->y1], [(float) $p->x2, (float) $p->y2]];
            }
        }
        foreach ($lines as $l) {
            $pts = [];
            foreach ($l->parts as $pp) {
                $pts[] = $pp[0];
                $pts[] = $pp[1];
            }
            $best = [0, null, null];
            $n = count($pts);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $d = hypot($pts[$i][0] - $pts[$j][0], $pts[$i][1] - $pts[$j][1]);
                    if ($d > $best[0]) {
                        $best = [$d, $pts[$i], $pts[$j]];
                    }
                }
            }
            $l->ends = $best[1] ? [$best[1], $best[2]] : null;
            $l->single = count($l->parts) === 1;
            $parsed = PlaceNames::parseLineName($l->name);
            $l->nameEnds = $parsed ? array_slice($parsed['places'], 0, 2) : [];
            unset($l->parts);
        }
        $this->nameIndex = [];
        // Communes les plus proches des extrémités (rapprochement par les noms).
        $ends = [];
        foreach ($lines as $l) {
            if ($l->ends) {
                $ends[] = $l->id.','.$l->ends[0][0].','.$l->ends[0][1].','.$l->ends[1][0].','.$l->ends[1][1];
            }
        }
        foreach (array_chunk($ends, 500) as $chunk) {
            $values = implode(',', array_map(fn ($e) => '('.$e.')', $chunk));
            foreach (DB::select("SELECT v.id,
                    (SELECT c.nom FROM communes c ORDER BY c.geom <-> ST_Transform(ST_SetSRID(ST_MakePoint(v.x1, v.y1), 2154), 4326) LIMIT 1) AS c1,
                    (SELECT c.nom FROM communes c ORDER BY c.geom <-> ST_Transform(ST_SetSRID(ST_MakePoint(v.x2, v.y2), 2154), 4326) LIMIT 1) AS c2
                FROM (VALUES {$values}) AS v(id, x1, y1, x2, y2)") as $r) {
                $lines[(int) $r->id]->endCommunes = [$r->c1, $r->c2];
            }
        }
        // Index « 5 premiers caractères du nom normalisé » → lignes (rapprochement par les noms).
        foreach ($lines as $l) {
            $l->nameKeys = [];
            foreach ([$l->nameEnds, $l->endCommunes ?? []] as $ends) {
                if (count($ends) >= 2) {
                    $l->nameKeys[] = [$this->nameKey($ends[0]), $this->nameKey($ends[1])];
                    foreach ([$ends[0], $ends[1]] as $e) {
                        $this->nameIndex[substr($this->nameKey($e), 0, 5)][$l->id] = true;
                    }
                }
            }
        }

        return $lines;
    }

    private array $nameIndex = [];

    /** @var array<string, string> fiche → raison du non-rattachement */
    private array $recordErrors = [];

    private function nameKey(?string $s): string
    {
        return PlaceNames::normalize(preg_replace('/\s*\(.*?\)/u', '', (string) $s));
    }

    /**
     * @return list<array{record: string, line: int, kind: string, from: ?string, to: ?string, score: float, detail: string}>
     */
    /**
     * Une fiche fille (section d'une ligne décrite par un article ou une section d'article)
     * ne peut être rattachée qu'aux lignes VFF de sa fiche parente ; si la ligne parente
     * n'a aucune ligne VFF (ligne du RFN, autre tracé…), ses sections ne sont pas
     * rattachées ailleurs.
     */
    private function matchAll(array $withEvents, array $records, array $lines, array $articles): array
    {
        // Fiches à traiter : datées + leurs ascendants (dans l'ordre du document : parents d'abord).
        $needed = [];
        // Fiches sans date d'un article qui a des phrases d'ensemble (« toutes les lignes … ») : à rattacher aussi.
        foreach ($records as $key => $r) {
            if (! isset($withEvents[$key]) && ! empty($articles[$r['article']]['network']['statements'])) {
                $withEvents[$key] = $r;
            }
        }
        foreach ($withEvents as $key => $r) {
            for ($k = $key, $d = 0; $k !== null && $d < 6; $k = $records[$k]['parent_key'] ?? null, $d++) {
                $needed[$k] = true;
            }
        }
        $keys = array_keys($needed);
        usort($keys, fn ($a, $b) => [$records[$a]['article'], $records[$a]['id']] <=> [$records[$b]['article'], $records[$b]['id']]);
        $results = [];
        $matches = [];
        $bar = $this->output->createProgressBar(count($keys));
        foreach ($keys as $key) {
            $bar->advance();
            $r = $records[$key];
            $allowed = null;
            $blocked = null;
            for ($k = $r['parent_key'], $d = 0; $k !== null && $d < 6; $k = $records[$k]['parent_key'] ?? null, $d++) {
                $pr = $results[$k] ?? null;
                if (isset($pr['matches'])) {
                    $allowed = array_flip(array_column($pr['matches'], 'line'));
                    break;
                }
                if (isset($pr['error']) && in_array($records[$k]['origin'], ['article', 'titre'], true) && str_contains($pr['error'], 'ligne du RFN (code SNCF)')) {
                    $blocked = 'ligne parente « '.implode(' - ', array_map(fn ($p) => $p['label'], $records[$k]['places'])).' » non retenue : '.$pr['error'];
                    break;
                }
            }
            $res = $blocked ? ['error' => $blocked] : $this->matchRecord($r, $lines, $articles[$r['article']]['ctx'], $allowed);
            $results[$key] = $res;
            if (isset($res['error'])) {
                $this->recordErrors[$key] = $res['error'];
            }
            if (! isset($withEvents[$key])) {
                continue;
            }
            if (isset($res['error'])) {
                if ($r['events']) {
                    $this->review($r, null, $res['error']);
                }

                continue;
            }
            foreach ($res['matches'] as $m) {
                $matches[] = $m + ['record' => $key];
            }
        }
        $bar->finish();
        $this->newLine();

        return $matches;
    }

    /** @return array{matches?: list<array>, error?: string} */
    private function matchRecord(array $r, array $lines, array $ctx, ?array $allowed = null): array
    {
        if (count($r['places']) < 2) {
            return ['error' => 'itinéraire incomplet'];
        }
        $places = $r['places'];
        $n = count($places);
        $geo = array_map(fn ($p) => $p['geo'], $places);
        $known = array_filter($geo);
        // Candidats géographiques : lignes à moins de ON_LINE_M d'au moins deux lieux géocodés.
        $cands = [];
        if (count($known) >= 2) {
            $pts = [];
            foreach ($geo as $i => $g) {
                if ($g) {
                    $pts[$i] = $g;
                }
            }
            $values = implode(',', array_map(fn ($i, $g) => sprintf('(%d, %.7F, %.7F)', $i, $g['lon'], $g['lat']), array_keys($pts), $pts));
            $rows = DB::select("WITH p AS (SELECT v.i, ST_Transform(ST_SetSRID(ST_MakePoint(v.lon, v.lat), 4326), 2154) AS g FROM (VALUES {$values}) AS v(i, lon, lat))
                SELECT t.line_id, p.i, ST_Distance(t.g, p.g) AS d, ST_X(p.g) AS px, ST_Y(p.g) AS py,
                       CASE WHEN ST_GeometryType(ST_LineMerge(t.g)) = 'ST_LineString' THEN ST_LineLocatePoint(ST_LineMerge(t.g), p.g) END AS f
                FROM p JOIN tmp_sec_lines t ON ST_DWithin(t.g, p.g, ?)", [7000]);
            $xy = [];
            foreach ($rows as $row) {
                $cands[(int) $row->line_id][(int) $row->i] = ['d' => (float) $row->d, 'f' => $row->f !== null ? (float) $row->f : null];
                $xy[(int) $row->i] = [(float) $row->px, (float) $row->py];
            }
            $near1 = $cands;
            foreach ($cands as $lid => $c) {
                if (count($c) < 2 || ($allowed !== null && ! isset($allowed[$lid]))) {
                    unset($cands[$lid]);
                }
            }
        } else {
            $xy = [];
        }
        // Candidats par les noms (extrémités du nom VFF ou communes des extrémités du tracé). Quand
        // le géocodage a abouti, seulement si une EXTRÉMITÉ de la fiche est à moins de 7 km du tracé :
        // l'autre a pu être prise pour un homonyme (« Saint-Georges », « Blanzac ») ; la géographie
        // garde la main pour tout le reste (les deux lieux près du tracé).
        $near1 ??= [];
        $first = $this->nameKey($places[0]['label']);
        $last = $this->nameKey($places[$n - 1]['label']);
        $pool = array_keys(($this->nameIndex[substr($first, 0, 5)] ?? []) + ($this->nameIndex[substr($last, 0, 5)] ?? []));
        foreach ($pool as $lid) {
            if ($allowed !== null && ! isset($allowed[$lid])) {
                continue;
            }
            $l = $lines[$lid];
            if (isset($cands[$l->id]) || ! $l->ends) {
                continue;
            }
            if (count($known) >= 2 && ! isset($near1[$lid][0]) && ! isset($near1[$lid][$n - 1])) {
                continue;
            }
            foreach ($l->nameKeys as $ends) {
                if (($this->sameName($first, $ends[0]) && $this->sameName($last, $ends[1])) || ($this->sameName($first, $ends[1]) && $this->sameName($last, $ends[0]))) {
                    if (count($known) >= 2) {
                        // Une extrémité géocodée près du tracé, l'autre loin (homonyme) : la distance à vol
                        // d'oiseau entre les deux lieux géocodés ne vaut rien.
                        $cands[$l->id] = ['byName' => true, 'far' => true];
                        break;
                    }
                    // Garde-fou géographique : la ligne doit être dans la zone de l'article.
                    if ($ctx['center']) {
                        $c = DB::selectOne('SELECT ST_Distance(t.g, ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 2154)) AS d FROM tmp_sec_lines t WHERE t.line_id = ?',
                            [$ctx['center']['lon'], $ctx['center']['lat'], $l->id]);
                        if (! $c || $c->d > 120000) {
                            continue;
                        }
                    } elseif (! $known) {
                        continue;
                    }
                    $cands[$l->id] = ['byName' => true];
                    break;
                }
            }
        }
        if (! $cands) {
            return ['error' => count($known) < 2 ? 'lieux non géocodés ('.implode(', ', array_map(fn ($p) => $p['label'].($p['geo'] ? '' : ' ?'), $places)).')' : 'aucune ligne VFF à proximité'];
        }

        $found = [];
        $rejects = [];
        foreach ($cands as $lid => $c) {
            $l = $lines[$lid];
            $m = $this->classify($r, $l, $c, $xy);
            if (isset($m['reject'])) {
                $rejects[] = $lid.' '.$l->name.' : '.$m['reject'];
            } else {
                $found[] = $m + ['line' => $lid];
            }
        }
        if (! $found) {
            return ['error' => 'lignes proches mais itinéraire non concordant ('.implode(' ; ', array_slice($rejects, 0, 4)).')'];
        }
        // Article (ou titre de section) qui décrit une ligne du RFN dont il suit le tracé de bout en bout :
        // c'est d'elle qu'il parle, jamais du tramway ou de la ligne secondaire du couloir voisin
        // (« Ligne de Cannes-la-Bocca à Grasse » ≠ « Tramway de Cannes à Grasse »).
        if (in_array($r['origin'], ['article', 'titre'], true)) {
            foreach ($rejects as $rej) {
                if (str_ends_with($rej, ': ligne du RFN (code SNCF)')) {
                    return ['error' => 'la fiche désigne une ligne du RFN (code SNCF) : '.$rej];
                }
            }
        }

        return $this->arbitrate($r, $found, $lines);
    }

    /**
     * Couple (fiche, ligne) : full / covers / section, ou null.
     *
     * @param  array<int|string, mixed>  $c  distances des lieux (index de lieu → [d, f]) ou ['byName' => true]
     */
    private function classify(array $r, object $l, array $c, array $xy): array
    {
        $places = $r['places'];
        $n = count($places);
        $lenKm = $l->length_m / 1000;
        $byName = isset($c['byName']);
        // Tolérances : un centre de commune peut être à quelques km de la gare ; une gare, non.
        $isStation = fn (int $i) => str_starts_with((string) ($places[$i]['geo']['kind'] ?? ''), 'gare');
        $onTol = fn (int $i) => $isStation($i) ? 1500 : 3000;
        $endTol = fn (int $i) => $isStation($i) ? 3000 : self::END_M;
        $near = [];
        if (! $byName) {
            foreach ($c as $i => $v) {
                if ($v['d'] <= $onTol($i)) {
                    $near[$i] = $v;
                }
            }
        }
        // Extrémités du tracé ↔ lieux de la fiche.
        $endAt = [null, null];
        if ($l->ends && ! $byName) {
            foreach ([0, 1] as $e) {
                $best = null;
                foreach ($xy as $i => $p) {
                    $d = hypot($p[0] - $l->ends[$e][0], $p[1] - $l->ends[$e][1]);
                    if ($d <= $endTol($i) && ($best === null || $d < $best[1])) {
                        $best = [$i, $d];
                    }
                }
                $endAt[$e] = $best;
            }
        }
        // Noms concordants (extrémités du nom VFF ou communes des extrémités du tracé = extrémités de la fiche).
        $first = $this->nameKey($places[0]['label']);
        $last = $this->nameKey($places[$n - 1]['label']);
        $sameEnds = false;
        foreach ($l->nameKeys ?? [] as $ends) {
            if (($this->sameName($first, $ends[0]) && $this->sameName($last, $ends[1])) || ($this->sameName($first, $ends[1]) && $this->sameName($last, $ends[0]))) {
                $sameEnds = true;
            }
        }
        if (! $byName && $sameEnds && ! ($endAt[0] && $endAt[1] && $endAt[0][0] !== $endAt[1][0])
            && isset($c[0], $c[$n - 1]) && $c[0]['d'] <= 7000 && $c[$n - 1]['d'] <= 7000) {
            // Mêmes noms d'extrémités et lieux à moins de 7 km du tracé : c'est la ligne.
            $byName = true;
        }
        if ($byName) {
            $kind = 'full';
            $i = 0;
            $j = $n - 1;
            $detail = 'noms des extrémités';
            $score = 1.0;
        } elseif ($endAt[0] && $endAt[1] && $endAt[0][0] !== $endAt[1][0] && min($endAt[0][1], $endAt[1][1]) <= 2500) {
            $i = min($endAt[0][0], $endAt[1][0]);
            $j = max($endAt[0][0], $endAt[1][0]);
            $kind = ($i === 0 && $j === $n - 1) ? 'full' : 'covers';
            $detail = sprintf('extrémités à %d m / %d m de « %s » et « %s »', $endAt[0][1], $endAt[1][1], $places[$endAt[0][0]]['label'], $places[$endAt[1][0]]['label']);
            $score = ($endAt[0][1] + $endAt[1][1]) / 2;
        } elseif (count($near) >= 2) {
            $idx = array_keys($near);
            $i = min($idx);
            $j = max($idx);
            $kind = 'section';
            $detail = sprintf('« %s » à %d m et « %s » à %d m du tracé', $places[$i]['label'], $near[$i]['d'], $places[$j]['label'], $near[$j]['d']);
            $score = ($near[$i]['d'] + $near[$j]['d']) / 2 + 1000;
        } else {
            return ['reject' => 'ni extrémités ni deux lieux sur le tracé'];
        }
        // Intermédiaires de la fiche entre i et j : doivent longer le tracé.
        if (! $byName) {
            for ($k = $i + 1; $k < $j; $k++) {
                if (isset($c[$k]) && $c[$k]['d'] > 6000) {
                    return ['reject' => 'lieu intermédiaire loin du tracé'];
                }
                if (! isset($c[$k]) && $places[$k]['geo']) {
                    return ['reject' => 'lieu intermédiaire loin du tracé'];
                }
            }
        }
        // Longueurs.
        $straight = isset($xy[$i], $xy[$j]) && ! isset($c['far']) ? hypot($xy[$i][0] - $xy[$j][0], $xy[$i][1] - $xy[$j][1]) / 1000 : null;
        if ($kind === 'section') {
            if ($straight !== null && $straight < 1.5) {
                return ['reject' => 'portion trop courte (lieux voisins)'];
            }
            $fi = $c[$i]['f'] ?? null;
            $fj = $c[$j]['f'] ?? null;
            $portion = ($fi !== null && $fj !== null) ? abs($fi - $fj) * $lenKm : null;
            if ($portion === null && isset($xy[$i], $xy[$j])) {
                // Tracé en plusieurs morceaux : longueur du tracé dans le couloir entre les deux lieux.
                $row = DB::selectOne('SELECT ST_Length(ST_Intersection(t.g, ST_Buffer(ST_MakeLine(ST_SetSRID(ST_MakePoint(?, ?), 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154)), ?))) / 1000 AS km
                    FROM tmp_sec_lines t WHERE t.line_id = ?', [$xy[$i][0], $xy[$i][1], $xy[$j][0], $xy[$j][1], max(2500, 0.3 * ($straight ?? 0) * 1000), $l->id]);
                $portion = $row ? (float) $row->km : null;
            }
            if ($portion !== null && $portion >= 0.85 * $lenKm) {
                $kind = 'full';
            }
            if ($portion !== null && $portion < 1.0) {
                return ['reject' => 'portion < 1 km'];
            }
            if ($straight && $portion !== null && ($portion < 0.75 * $straight || $portion > 2.6 * $straight + 2)) {
                return ['reject' => sprintf('portion %.1f km pour %.1f km à vol d\'oiseau', $portion, $straight)];
            }
            if ($r['km'] && $i === 0 && $j === $n - 1 && $portion !== null && ($portion < 0.55 * $r['km'] || $portion > 1.7 * $r['km'] + 2)) {
                return ['reject' => sprintf('portion %.1f km pour une fiche de %.1f km', $portion, $r['km'])];
            }
            $detail .= $portion !== null ? sprintf(', portion ≈ %.1f km', $portion) : '';
        }
        if ($kind === 'full' && $r['km'] && ($lenKm < 0.55 * $r['km'] - 1 || $lenKm > 1.7 * $r['km'] + 3)) {
            return ['reject' => sprintf('ligne de %.1f km pour une fiche de %.1f km', $lenKm, $r['km'])];
        }
        if ($kind === 'full' && $straight && ($lenKm < 0.7 * $straight || $lenKm > 3 * $straight + 3)) {
            return ['reject' => sprintf('ligne de %.1f km pour %.1f km à vol d\'oiseau', $lenKm, $straight)];
        }
        if ($kind === 'covers' && $r['km'] && $lenKm > 1.25 * $r['km'] + 2) {
            return ['reject' => 'ligne plus longue que la fiche'];
        }
        // Lignes hors réseau secondaire : correspondance complète et longueur concordante seulement.
        if ($l->network !== 'secondaire') {
            if ($l->network === 'principal' && $l->code) {
                return ['reject' => in_array($kind, ['full', 'covers'], true) ? 'ligne du RFN (code SNCF)' : 'section d\'une ligne du RFN'];
            }
            $ref = $r['km'] ?? $straight;
            if ($kind !== 'full' || ! $ref || $lenKm < 0.7 * $ref || $lenKm > 1.5 * $ref + 1) {
                return ['reject' => 'ligne hors réseau secondaire sans correspondance complète'];
            }
        }

        return ['kind' => $kind, 'i' => $i, 'j' => $j,
            'from' => $kind === 'section' ? $this->sectionName($places[$i]) : null,
            'to' => $kind === 'section' ? $this->sectionName($places[$j]) : null,
            'score' => $score, 'detail' => $detail.sprintf(' ; ligne %.1f km%s', $lenKm, $r['km'] ? sprintf(', fiche %.1f km', $r['km']) : '')];
    }

    /** Nom transmis au résolveur de portions : le libellé, ou la commune géocodée si le libellé n'en est pas une. */
    private function sectionName(array $p): string
    {
        $g = $p['geo'];
        if ($g && in_array($g['kind'], ['commune (lien)', 'commune (lien gare)', 'commune (préfixe)'], true)
            && PlaceNames::normalize($g['name']) !== PlaceNames::normalize($p['label'])) {
            return $g['name'];
        }

        return $p['label'];
    }

    /** Plusieurs lignes possibles : on garde les doublons d'un même tracé, on écarte les choix ambigus. */
    private function arbitrate(array $r, array $found, array $lines): array
    {
        $rank = ['full' => 0, 'covers' => 1, 'section' => 2];
        usort($found, fn ($a, $b) => [$rank[$a['kind']], $a['score']] <=> [$rank[$b['kind']], $b['score']]);
        $best = $found[0];
        $kept = [$best];
        foreach (array_slice($found, 1) as $m) {
            if ($m['kind'] === 'covers' && $best['kind'] === 'covers' && ($m['i'] !== $best['i'] || $m['j'] !== $best['j'])) {
                $kept[] = $m; // une autre partie de l'itinéraire de la fiche

                continue;
            }
            $overlap = $this->overlap($best['line'], $m['line']);
            if ($overlap >= 0.5) {
                if ($rank[$m['kind']] <= $rank['covers'] || $m['kind'] === $best['kind']) {
                    $kept[] = $m; // même tracé (doublon de ligne) : même histoire
                }

                continue;
            }
            if ($rank[$m['kind']] > $rank[$best['kind']]) {
                continue; // une ligne correspond entièrement, l'autre seulement en passant : on garde la première
            }
            // Deux lignes distinctes aussi plausibles l'une que l'autre.
            if ($m['score'] < 1.6 * $best['score'] + 300) {
                return ['error' => sprintf('ambigu : %s (%s) ou %s (%s)', $lines[$best['line']]->name, $best['kind'], $lines[$m['line']]->name, $m['kind'])];
            }
        }

        return ['matches' => $kept];
    }

    private array $overlapCache = [];

    private function overlap(int $a, int $b): float
    {
        $k = min($a, $b).'-'.max($a, $b);
        if (! isset($this->overlapCache[$k])) {
            $row = DB::selectOne('SELECT ST_Length(ST_Intersection(ST_Buffer(x.g, 60), y.g)) / NULLIF(LEAST(ST_Length(x.g), ST_Length(y.g)), 0) AS o
                FROM tmp_sec_lines x, tmp_sec_lines y WHERE x.line_id = ? AND y.line_id = ?', [$a, $b]);
            $this->overlapCache[$k] = (float) ($row->o ?? 0);
        }

        return $this->overlapCache[$k];
    }

    /** Deux noms normalisés désignent-ils le même lieu (égalité ou préfixe ≥ 5 lettres) ? */
    private function sameName(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        // « Carhaix » ↔ « Carhaix-Plouguer », « Bourg-en-Bresse ESE » ↔ « Bourg-en-Bresse ».
        [$s, $l] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];

        return strlen($s) >= 5 && str_starts_with($l.' ', $s.' ') && ! in_array($s, ['saint', 'sainte', 'notre dame'], true);
    }

    // ------------------------------------------------------------------
    // 5. Événements par ligne
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{line: object, events: list<array>, status: string, reason: ?string}>
     */
    private function plan(array $matches, array $records, array $lines, array $articles): array
    {
        $originRank = ['tableau' => 1, 'liste' => 2, 'titre' => 3, 'texte' => 3, 'article' => 3, 'infobox' => 5, 'reseau' => 6];
        $kindRank = ['full' => 0, 'covers' => 0, 'section' => 1];
        $byLine = [];
        $lineArticles = [];
        // Même itinéraire (mêmes extrémités, au nom normalisé près) décrit par un AUTRE article, dont la
        // fiche n'a pu être rattachée seule (lieux trop éloignés du tracé VFF) : « Morlaix - Primel-Trégastel »
        // du tableau des CFD du Finistère complète « Morlaix - Plouezoc'h - Primel Tregastel » des CF armoricains.
        $routeKey = function (array $r): ?string {
            if (count($r['places']) < 2) {
                return null;
            }
            $ends = [PlaceNames::normalize($r['places'][0]['label']), PlaceNames::normalize($r['places'][count($r['places']) - 1]['label'])];
            sort($ends);

            return $ends[0] !== $ends[1] ? implode('|', $ends) : null;
        };
        $byRoute = [];
        foreach ($records as $k => $r) {
            if ($r['events'] && ($rk = $routeKey($r))) {
                $byRoute[$rk][] = $k;
            }
        }
        $matchedTo = [];
        foreach ($matches as $m) {
            $matchedTo[$m['record']][$m['line']] = true;
        }
        foreach ($matches as $m) {
            $r = $records[$m['record']];
            $art = $articles[$r['article']];
            $route = implode(' - ', array_map(fn ($p) => $p['label'], $r['places']));
            // Rattachement de la ligne à l'article (phrases d'ensemble) : « toute la ligne » de préférence
            // à une portion (sinon « toutes ces lignes ferment en 1939 » ne vaudrait que pour Lezay - Melle).
            $prev = $lineArticles[$m['line']][$r['article']] ?? null;
            if ($prev === null || ($prev['kind'] === 'section' && $m['kind'] !== 'section')) {
                $lineArticles[$m['line']][$r['article']] = $m + ['route' => $route];
            }
            foreach ($r['events'] as $e) {
                $whole = $m['kind'] !== 'section';
                $byLine[$m['line']][] = $e + [
                    'record' => $m['record'], 'article' => $r['article'], 'url' => $art['url'], 'route' => $route,
                    'kind' => $m['kind'], 'detail' => $m['detail'], 'whole' => $whole,
                    'from' => $whole ? null : $m['from'], 'to' => $whole ? null : $m['to'],
                    'rank' => ($originRank[$e['origin'] ?? $r['origin']] ?? 4) * 10 + $kindRank[$m['kind']] * 3 + ($e['range'] ? 1 : 0),
                ];
            }
            foreach (($rk = $routeKey($r)) ? $byRoute[$rk] ?? [] : [] as $sk) {
                $s = $records[$sk];
                if ($sk === $m['record'] || $s['article'] === $r['article'] || isset($matchedTo[$sk])
                    || str_contains($this->recordErrors[$sk] ?? '', 'ligne du RFN')
                    || ($s['km'] && $r['km'] && ($s['km'] < 0.7 * $r['km'] || $s['km'] > 1.4 * $r['km']))) {
                    continue;
                }
                $sRoute = implode(' - ', array_map(fn ($p) => $p['label'], $s['places']));
                foreach ($s['events'] as $e) {
                    $whole = $m['kind'] !== 'section';
                    $byLine[$m['line']][] = $e + [
                        'record' => $sk, 'article' => $s['article'], 'url' => $articles[$s['article']]['url'], 'route' => $sRoute,
                        'kind' => $m['kind'], 'detail' => $m['detail'].' ; même itinéraire « '.$sRoute.' » dans l\'article « '.$s['article'].' »', 'whole' => $whole,
                        'from' => $whole ? null : $m['from'], 'to' => $whole ? null : $m['to'],
                        'rank' => ($originRank[$e['origin'] ?? $s['origin']] ?? 4) * 10 + $kindRank[$m['kind']] * 3 + ($e['range'] ? 1 : 0) + 2,
                    ];
                }
            }
            // Fermeture héritée de la fiche parente (« fermée en 1936 » vaut pour ses sections, au plus tard).
            $this->inheritClosures($byLine, $m, $r, $records, $art, $route);
        }

        // Phrases d'ensemble du réseau (« l'ensemble des lignes a été ouvert en 1904 », « ces lignes
        // ferment le 31 décembre 1950 ») : valent pour chaque ligne du réseau qui n'a pas sa propre date
        // de la même famille.
        $family = fn (string $t) => match (true) {
            str_starts_with($t, 'opening') => 'opening', str_starts_with($t, 'reopening') => 'reopening',
            str_starts_with($t, 'closure') => 'closure', default => $t,
        };
        // Seul un événement « toute la ligne » de la même famille l'en dispense : une fermeture de PORTION
        // (« Seule la ligne de Lezay à Melle fonctionne jusqu'en 1947 ») ne date pas le reste de la ligne,
        // et le moteur d'ères préfère de toute façon l'événement de portion sur sa portion.
        $wholeFamilies = fn (array $evs) => array_map(fn ($e) => $family($e['type']), array_filter($evs, fn ($e) => $e['whole']));
        foreach ($lineArticles as $lid => $arts) {
            $have = array_flip($wholeFamilies($byLine[$lid] ?? []));
            foreach ($arts as $artTitle => $m) {
                foreach ($articles[$artTitle]['network']['statements'] ?? [] as $st) {
                    if (isset($have[$family($st['type'])])) {
                        continue;
                    }
                    $whole = $m['kind'] !== 'section';
                    $byLine[$lid][] = [
                        'type' => $st['type'], 'date' => $st['date'], 'precision' => $st['precision'], 'confidence' => 'low', 'range' => $st['range'],
                        'origin' => 'reseau', 'quote' => $st['value'], 'record' => $m['record'], 'article' => $artTitle, 'url' => $articles[$artTitle]['url'],
                        'route' => $m['route'], 'kind' => $m['kind'], 'detail' => $m['detail'].' ; phrase d\'ensemble sur les lignes du réseau',
                        'whole' => $whole, 'from' => $whole ? null : $m['from'], 'to' => $whole ? null : $m['to'], 'rank' => 55, 'statement' => true,
                    ];
                }
                foreach ($wholeFamilies($byLine[$lid] ?? []) as $f) {
                    $have[$f] = true;
                }
            }
        }

        $plan = [];
        foreach ($byLine as $lid => $events) {
            $l = $lines[$lid];
            // Une seule occurrence par (type, portion) : la mieux classée, puis la plus ancienne.
            usort($events, fn ($a, $b) => [$a['rank'], $a['date']] <=> [$b['rank'], $b['date']]);
            $kept = [];
            foreach ($events as $e) {
                $k = $e['type'].'|'.PlaceNames::normalize((string) $e['from']).'|'.PlaceNames::normalize((string) $e['to']);
                if (isset($kept[$k])) {
                    if (abs((int) substr($kept[$k]['date'], 0, 4) - (int) substr($e['date'], 0, 4)) > 2) {
                        $kept[$k]['conflicts'][] = substr($e['date'], 0, 4).' ('.$e['article'].')';
                    }

                    continue;
                }
                $kept[$k] = $e;
            }
            $events = array_values($kept);
            $opens = array_filter($events, fn ($e) => str_starts_with($e['type'], 'opening') || str_starts_with($e['type'], 'reopening'));
            $status = 'write';
            $reason = null;
            if ($l->dated) {
                [$events, $status, $reason] = $this->endsOfDatedLine($events, $l, $articles);
            } elseif (! $opens) {
                $status = 'skip';
                $reason = 'aucune ouverture dans les fiches rattachées';
            } else {
                // Cohérence : fermeture totale avant la première ouverture → incohérent.
                $firstOpen = min(array_map(fn ($e) => $e['date'], $opens));
                $events = array_values(array_filter($events, function ($e) use ($firstOpen) {
                    return ! (in_array($e['type'], ['closure', 'declassification', 'dismantling', 'closure_passenger', 'closure_freight'], true) && $e['whole'] && $e['date'] <= $firstOpen);
                }));
                // Réouverture sans fermeture ultérieure connue (« rouverte temporairement… ») : écartée,
                // elle laisserait la portion en service jusqu'à aujourd'hui.
                $lastOther = (string) $l->last_closure;
                $events = array_values(array_filter($events, function ($e) use ($events, $lastOther) {
                    if (! str_starts_with($e['type'], 'reopening')) {
                        return true;
                    }
                    foreach ($events as $x) {
                        if (in_array($x['type'], ['closure', 'declassification', 'dismantling'], true) && $x['date'] > $e['date']
                            && ($x['whole'] || ($x['from'] === $e['from'] && $x['to'] === $e['to']))) {
                            return true;
                        }
                    }
                    if ($lastOther > $e['date']) {
                        return true;
                    }
                    $this->review(['article' => $e['article'], 'places' => [], 'km' => null, 'events' => [$e], 'quote' => $e['quote']], null,
                        'réouverture sans fermeture ultérieure connue : écartée');

                    return false;
                }));
                if ($l->network === 'secondaire' && (int) substr($firstOpen, 0, 4) < 1865) {
                    $status = 'skip';
                    $reason = 'ouverture avant 1865 pour une ligne du réseau secondaire (antérieure à la loi sur l\'intérêt local) : à vérifier';
                } else {
                    [$events, $status, $reason] = $this->ensureEnds($events, $l, $articles, $firstOpen);
                }
            }
            $plan[$lid] = ['line' => $l, 'events' => $events, 'status' => $status, 'reason' => $reason];
        }

        return $plan;
    }

    /** Types qui mettent fin (en tout ou en partie) à l'exploitation d'une portion. */
    public const END_TYPES = ['closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling', 'conversion'];

    /**
     * Ligne déjà datée par une autre source (ses ouvertures priment) : si aucune source ne
     * dit qu'elle a cessé d'être exploitée (fermeture totale, déclassement, dépose, ou
     * voyageurs ET marchandises), le moteur la laisse en service de son ouverture jusqu'en
     * 2020 (puis elle disparaît en 2030, voie disparue). On écrit alors les FINS de nos
     * fiches (pas leurs ouvertures), et à défaut la fin du réseau, au plus tard (cf.
     * networkEnd). Sinon la ligne reste « déjà datée » (rapport lignes-deja-datees).
     *
     * @return array{0: list<array>, 1: string, 2: string}
     */
    private function endsOfDatedLine(array $events, object $l, array $articles): array
    {
        $dated = [$events, 'dated', 'ligne déjà datée par une autre source (1re ouverture '.($l->first_opening ?? '?').')'];
        $others = array_values(array_filter(explode(',', (string) $l->other_closures)));
        // Fin connue ailleurs, sauf si une ouverture lui succède (Lagny - Mortcerf : « fermée en
        // 1900 », puis Villeneuve-le-Comte - Mortcerf ouverte en 1902) : la ligne vit encore après.
        $lastOpening = $l->last_opening ?? null;
        if (self::hasFinalEnd($others) && ($l->last_closure === null || $lastOpening === null || $l->last_closure > $lastOpening)) {
            return $dated;
        }
        $openDates = array_map(fn ($e) => $e['date'], array_filter($events, fn ($e) => str_starts_with($e['type'], 'opening')));
        $firstOpen = min([...$openDates, ...array_filter([$l->first_opening])] ?: ['9999-12-31']);
        // Fin « toute la ligne » antérieure à la première ouverture connue : incohérente.
        $ends = array_values(array_filter($events, fn ($e) => in_array($e['type'], self::END_TYPES, true)
            && ! ($e['whole'] && $e['date'] <= $firstOpen)));
        $masked = (bool) array_intersect($others, ['closure', 'closure_passenger', 'closure_freight']);
        if (! $masked && ! self::hasFinalEnd(array_map(fn ($e) => $e['type'], array_filter($ends, fn ($e) => $e['whole'])))
            && ($end = $this->networkEnd($events, $articles, $firstOpen))) {
            $ends[] = $end;
        }
        if (! $ends) {
            return $dated;
        }

        return [$ends, 'write', 'ligne déjà datée par une autre source mais sans fin d\'exploitation : seules les fins sont écrites'];
    }

    /** Une fin d'exploitation complète parmi ces types (totale, déclassement, dépose, ou voyageurs + fret) ? */
    public static function hasFinalEnd(array $types): bool
    {
        return (bool) array_intersect($types, ['closure', 'declassification', 'dismantling', 'conversion'])
            || (in_array('closure_passenger', $types, true) && in_array('closure_freight', $types, true));
    }

    private function inheritClosures(array &$byLine, array $m, array $r, array $records, array $art, string $route): void
    {
        $own = array_filter($r['events'], fn ($e) => in_array($e['type'], ['closure', 'declassification', 'dismantling'], true));
        if ($own) {
            return;
        }
        $pk = $r['parent_key'];
        for ($depth = 0; $pk !== null && $depth < 3; $depth++) {
            $p = $records[$pk] ?? null;
            if (! $p) {
                return;
            }
            foreach ($p['events'] as $e) {
                if (in_array($e['type'], ['closure', 'declassification', 'dismantling'], true)) {
                    $whole = $m['kind'] !== 'section';
                    $parentRoute = implode(' - ', array_map(fn ($x) => $x['label'], $p['places']));
                    $byLine[$m['line']][] = array_merge($e, [
                        'record' => $m['record'], 'article' => $r['article'], 'url' => $art['url'], 'route' => $route,
                        'kind' => $m['kind'], 'detail' => $m['detail'].' ; fermeture de la ligne parente '.$parentRoute, 'whole' => $whole,
                        'from' => $whole ? null : $m['from'], 'to' => $whole ? null : $m['to'], 'confidence' => 'low',
                        'inherited' => $parentRoute, 'rank' => 40, 'origin' => 'parent',
                    ]);
                }
            }
            $pk = $p['parent_key'];
        }
    }

    /**
     * Chaque portion ouverte doit aussi avoir une FIN (fermeture totale, déclassement, dépose,
     * ou fermeture aux voyageurs ET aux marchandises), sinon le moteur la laisserait en service
     * jusqu'à aujourd'hui. Dans l'ordre :
     *   1. une autre source (prioritaire) ferme déjà la ligne : rien à ajouter ;
     *   2. une fin pour TOUTE la ligne dans nos fiches, sinon la fin du réseau (au plus tard) ;
     *   3. à défaut, seules les portions dont la fin est connue sont gardées.
     * Le moteur ne retient, par famille d'événements (fermetures, ouvertures…), que la source la
     * plus fiable pour une portion : si une autre source y a déjà une fermeture partielle (aux
     * voyageurs seulement), nos fermetures « toute la ligne » de la même famille sont masquées.
     *
     * @return array{0: list<array>, 1: string, 2: ?string}
     */
    private function ensureEnds(array $events, object $l, array $articles, string $firstOpen): array
    {
        $others = array_values(array_filter(explode(',', (string) $l->other_closures)));
        $othersFamily = array_intersect($others, ['closure', 'closure_passenger', 'closure_freight']);
        if (in_array('closure', $others, true) || array_intersect($others, ['declassification', 'dismantling', 'conversion'])
            || (in_array('closure_passenger', $others, true) && in_array('closure_freight', $others, true))) {
            return [$events, 'write', null];
        }
        $masked = (bool) $othersFamily;
        $ends = function (array $evs, callable $where) use ($masked): bool {
            $types = [];
            foreach ($evs as $e) {
                if ($where($e) && ! ($masked && $e['whole'] && str_starts_with($e['type'], 'closure'))) {
                    $types[$e['type']] = true;
                }
            }

            return isset($types['closure']) || isset($types['declassification']) || isset($types['dismantling'])
                || (isset($types['closure_passenger']) && isset($types['closure_freight']));
        };
        if ($ends($events, fn ($e) => $e['whole'])) {
            return [$events, 'write', null];
        }
        if (! $masked && ($end = $this->networkEnd($events, $articles, $firstOpen))) {
            $events[] = $end;

            return [$events, 'write', null];
        }
        // Portions dont la fin est connue seulement.
        $key = fn ($e) => PlaceNames::normalize((string) $e['from']).'|'.PlaceNames::normalize((string) $e['to']);
        $kept = [];
        $dropped = [];
        foreach ($events as $e) {
            $k = $key($e);
            if (! $e['whole'] && $ends($events, fn ($x) => ! $x['whole'] && $key($x) === $k)) {
                $kept[] = $e;
            } else {
                $dropped[] = $e;
            }
        }
        $why = $masked ? 'fermeture totale masquée par une fermeture partielle (voyageurs) d\'une source prioritaire'
            : 'ouverture connue mais aucune date de fin d\'exploitation (la portion resterait en service jusqu\'en 2020)';
        if (! array_filter($kept, fn ($e) => str_starts_with($e['type'], 'opening'))) {
            return [$events, 'skip', $why];
        }
        foreach ($dropped as $e) {
            $this->review(['article' => $e['article'], 'places' => [], 'km' => null, 'events' => [$e], 'quote' => $e['quote']], $l->id,
                'portion écartée : '.$why.' ('.($e['whole'] ? 'toute la ligne' : $e['from'].' - '.$e['to']).')');
        }

        return [$kept, 'write', 'portions sans fin connue écartées'];
    }

    /** Fin du réseau (infobox de l'article) : la ligne a fermé au plus tard à cette date. */
    private function networkEnd(array $events, array $articles, string $firstOpen): ?array
    {
        $best = null;
        foreach ($events as $e) {
            $a = $articles[$e['article']] ?? null;
            if (! $a || $a['single']) {
                continue;
            }
            // Fin du réseau : phrase d'ensemble de l'article (« ces lignes ferment le … »), sinon la plus
            // tardive de l'infobox (fermeture, fin…) et de la dernière fermeture totale citée.
            $end = $a['network']['statement'] ?? null;
            if (! $end) {
                // Borne trop lâche (fermetures étalées sur plus de 15 ans) : inutilisable. Seul un champ de
                // FERMETURE (ou l'introduction) vaut : « date de disparition », « dissolution » datent la
                // compagnie (absorption, rachat), pas la fin du trafic (ex. Bouches-du-Rhône : 1881 / 1954).
                $end = $a['network']['closed'] ?? null;
                if ($end && ($a['network']['spread'] ?? 0) > 15) {
                    $end = null;
                }
                $lastCited = $a['network']['last_closure'] ?? null;
                // Infobox contredite par l'article (fermetures citées plus tardives) : ce n'est pas la fin du réseau.
                if ($end && $lastCited && $end['year'] < $lastCited['year'] - 1) {
                    $end = null;
                }
                if ($lastCited && ($a['network']['closure_spread'] ?? 0) <= 15 && (! $end || $lastCited['date'] > $end['date'])) {
                    $end = $lastCited;
                }
            }
            // Au-delà de 1955, une borne de réseau n'apprend rien sur une ligne secondaire (presque toutes
            // fermées avant) : on n'en déduit rien.
            if (! $end || $end['date'] <= $firstOpen || $end['year'] > 1955) {
                continue;
            }
            $last = max(array_map(fn ($x) => $x['date'], $events));
            if ($end['date'] < $last) {
                continue;
            }
            if ($best === null || $end['date'] < $best['date']) {
                $best = ['type' => 'closure', 'date' => $end['date'], 'precision' => $end['precision'], 'confidence' => 'low', 'range' => false,
                    'origin' => 'reseau', 'quote' => match ($end['field']) {
                        'dernière fermeture citée' => 'Dernière fermeture du réseau citée par l\'article : '.$end['value'],
                        'fermeture du réseau', 'phrase d\'ensemble' => $end['value'],
                        'introduction' => 'Période d\'exploitation du réseau donnée par l\'introduction : '.$end['value'],
                        default => 'Infobox du réseau : '.$end['field'].' = '.$end['value'],
                    },
                    'record' => $e['record'], 'article' => $e['article'], 'url' => $a['url'], 'route' => $e['route'], 'kind' => 'full',
                    'detail' => 'fin d\'exploitation du réseau « '.$e['article'].' » (au plus tard)', 'whole' => true, 'from' => null, 'to' => null, 'rank' => 60, 'network_end' => true];
            }
        }

        return $best;
    }

    // ------------------------------------------------------------------
    // 6. Estimations par réseau
    // ------------------------------------------------------------------

    /** Grandes compagnies et réseaux principaux : jamais un « réseau » d'estimation. */
    private const MAIN_NETWORKS = '/\b(Paris à Lyon et à la Méditerranée|Paris-Lyon-Méditerranée|PLM|Paris à Orléans|Paris-Orléans|chemins de fer du Midi|Compagnie du Midi|chemins de fer du Nord\b|chemins de fer de l[\'’]Est\b|chemins de fer de l[\'’]Ouest\b|chemins de fer de l[\'’]État|Administration des chemins de fer|SNCF|Société nationale|Réseau ferré|métro|Liste)\b/iu';

    /** Lignes inachevées ou jamais ouvertes (nom du tracé archeoferroviaire, garde-fou seulement). */
    private const UNFINISHED_TRACE = '(inachev|non achev|projet|jamais|non ouvert|non réalisé|hypothétique)';

    /**
     * Lignes du réseau SECONDAIRE qu'aucune source ne date (ou dont la fiche n'a donné qu'une
     * ouverture ou qu'une fin) : dates de début / fin du RÉSEAU auquel elles appartiennent, à
     * l'année, confiance « low », source ESTIMATE_SOURCE, description « Estimation … à confirmer ».
     *
     * Réseau d'une ligne : celui des fiches rattachées (plan), sinon l'article de réseau dont les
     * lieux géocodés sont les plus nombreux le long du tracé (≥ 2 à moins de 3 km, dont un près
     * d'une extrémité) ; deux réseaux aussi proches aux dates différentes → aucune estimation.
     * Bornes du réseau : infobox ou introduction (« a fonctionné de 1913 à 1933 »), sinon
     * première ouverture / dernière fermeture citées ; fin après 1960 ou bornes incohérentes →
     * aucune estimation. Jamais pour une ligne inachevée (tracé « inachevé », « projet »), en
     * chantier dans OHM (railway=construction / proposed), ni exploitée par une association.
     *
     * @param  array<array{line: mixed, events: list<array>, status: string, reason: ?string, estimated?: int}>  $plan
     *
     * @param-out array<array{line: mixed, events: list<array>, status: string, reason: ?string, estimated?: int}>  $plan
     */
    private function estimate(array &$plan, array $records, array $lines, array $articles): void
    {
        $this->report['estimates'] = [];
        // Bornes de chaque réseau.
        $spans = [];
        foreach ($articles as $title => $a) {
            if (! $a['single'] && ! preg_match(self::MAIN_NETWORKS, $title) && ($span = $this->networkSpan($title, $a, $records))) {
                $spans[$title] = $span;
            }
        }
        // Lignes candidates : secondaires, sans ouverture d'une autre source, non datées par ce plan.
        $cands = [];
        foreach ($lines as $lid => $l) {
            if ($l->network === 'secondaire' && ! $l->dated && (($plan[$lid]['status'] ?? 'skip') === 'skip')) {
                $cands[$lid] = true;
            }
        }
        if (! $cands) {
            return;
        }
        $ids = implode(',', array_keys($cands));
        $unfinished = array_flip(array_map(fn ($r) => (int) $r->line_id, DB::select("SELECT DISTINCT stl.line_id FROM source_trace_lines stl
            JOIN source_traces st ON st.id = stl.source_trace_id
            WHERE stl.role = 'primary' AND stl.line_id IN ({$ids}) AND st.source = 'archeoferroviaire' AND st.name ~* '".self::UNFINISHED_TRACE."'")));
        $building = [];
        foreach (DB::select("SELECT t.line_id, SUM(ST_Length(ST_Intersection(ST_Transform(st.geom, 2154), ST_Buffer(t.g, 40)))) / NULLIF(MAX(t.length_m), 0) AS share
            FROM tmp_sec_lines t JOIN line_reference_geometries r ON r.line_id = t.line_id
            JOIN source_traces st ON st.source = 'ohm' AND st.properties->>'railway' IN ('construction', 'proposed')
                AND st.geom && ST_Expand(r.geom, 0.001)
            WHERE t.line_id IN ({$ids}) GROUP BY t.line_id") as $r) {
            if ((float) $r->share >= 0.3) {
                $building[(int) $r->line_id] = true;
            }
        }
        $heritage = array_flip(array_map(fn ($r) => (int) $r->line_id, DB::select("SELECT DISTINCT line_id FROM line_events
            WHERE line_id IN ({$ids}) AND type IN ('heritage_start', 'heritage_end')")));
        // Réseau par la position : lieux géocodés des articles de réseau le long du tracé.
        $geo = $this->networksAlong(array_keys($cands), $records, $spans, $lines);

        foreach (array_keys($cands) as $lid) {
            $l = $lines[$lid];
            $why = match (true) {
                str_contains((string) ($plan[$lid]['reason'] ?? ''), 'avant 1865') => 'ouverture avant 1865 à vérifier : pas d\'estimation',
                isset($unfinished[$lid]) => 'ligne inachevée ou projet (nom du tracé) : jamais ouverte',
                isset($building[$lid]) => 'en chantier dans OpenHistoricalMap (railway=construction / proposed) : jamais ouverte ?',
                isset($heritage[$lid]) => 'exploitation associative connue : chronologie particulière, pas d\'estimation',
                default => null,
            };
            $p = $plan[$lid] ?? null;
            // Réseau : celui des fiches rattachées (le plus cité), sinon la position.
            $network = null;
            $how = null;
            $noSpan = null;
            if (! $why && $p) {
                $count = array_count_values(array_column($p['events'], 'article'));
                arsort($count);
                foreach (array_keys($count) as $t) {
                    if (isset($spans[$t])) {
                        $network = $t;
                        $how = 'fiche « '.($p['events'][array_search($t, array_column($p['events'], 'article'), true)]['route'] ?? '').' » de l\'article';
                        break;
                    }
                    $noSpan ??= $t;
                }
            }
            if (! $why && ! $network) {
                [$network, $how, $ambiguous] = $geo[$lid] ?? [null, null, null];
                if ($ambiguous) {
                    $why = $ambiguous;
                }
            }
            if (! $why && ! $network) {
                $why = $noSpan ? 'réseau « '.$noSpan.' » (fiches rattachées) sans bornes de dates exploitables' : 'aucun réseau daté reconnu le long du tracé';
            }
            if ($why) {
                $this->report['estimates'][] = ['line_id' => $lid, 'ligne' => $l->name, 'km' => round($l->length_m / 1000, 1), 'statut' => 'non estimée', 'raison' => $why];

                continue;
            }
            $span = $spans[$network];
            $events = $p['events'] ?? [];
            $hasOpen = (bool) array_filter($events, fn ($e) => str_starts_with($e['type'], 'opening') || str_starts_with($e['type'], 'reopening'));
            $others = array_values(array_filter(explode(',', (string) $l->other_closures)));
            $hasEnd = $others || array_filter($events, fn ($e) => $e['whole'] && in_array($e['type'], ['closure', 'declassification', 'dismantling'], true));
            $firstEnd = min(array_filter(array_merge([(string) $l->last_closure ?: '9999'], array_map(fn ($e) => $e['date'], array_filter($events,
                fn ($e) => in_array($e['type'], ['closure', 'declassification', 'dismantling', 'closure_passenger'], true))))));
            $new = [];
            if (! $hasOpen && $span['start']['date'] < $firstEnd) {
                $new[] = $this->estimateEvent('opening', $span['start'], $span, $network, $how);
            }
            $lastOpen = max(array_merge([$span['start']['date']], array_map(fn ($e) => $e['date'], array_filter($events,
                fn ($e) => str_starts_with($e['type'], 'opening') || str_starts_with($e['type'], 'reopening')))));
            if (! $hasEnd && $span['end']['date'] > $lastOpen) {
                $new[] = $this->estimateEvent('closure', $span['end'], $span, $network, $how);
            }
            $opens = $hasOpen || array_filter($new, fn ($e) => $e['type'] === 'opening');
            $ends = $hasEnd || array_filter($new, fn ($e) => $e['type'] === 'closure');
            if (! $new || ! $opens || ! $ends) {
                $this->report['estimates'][] = ['line_id' => $lid, 'ligne' => $l->name, 'km' => round($l->length_m / 1000, 1), 'statut' => 'non estimée',
                    'reseau' => $network, 'raison' => 'bornes du réseau incompatibles avec les dates connues de la ligne'];

                continue;
            }
            $plan[$lid] = ['line' => $l, 'events' => array_merge($events, $new), 'status' => 'write', 'estimated' => count($new),
                'reason' => ($p['reason'] ?? null) ? $p['reason'].' ; complété par estimation' : 'estimation par réseau'];
            foreach ($new as $e) {
                $this->report['estimates'][] = ['line_id' => $lid, 'ligne' => $l->name, 'km' => round($l->length_m / 1000, 1), 'statut' => 'estimée',
                    'reseau' => $network, 'rattachement' => $how, 'type' => $e['type'], 'annee' => substr($e['date'], 0, 4),
                    'dates_sourcees' => $hasOpen || $hasEnd ? 'oui' : 'non', 'raison' => $e['quote']];
            }
        }
    }

    /** @return array{start: array, end: array, url: string}|null bornes [date, precision, quote] d'un article de réseau */
    private function networkSpan(string $title, array $a, array $records): ?array
    {
        $net = $a['network'];
        $start = $net['start'] ? ['date' => $net['start']['date'], 'quote' => SecondaryNetworkParser::fieldQuote($net['start'])] : null;
        // Fin du RÉSEAU = la plus tardive des fins de trafic connues : phrase d'ensemble, champ
        // « fermeture » de l'infobox ou introduction, dernière fermeture citée. Jamais la fin de la
        // compagnie (« disparition », « dissolution » : absorption, rachat, le réseau continue).
        // Une phrase d'ensemble (« toutes ces lignes ferment en 1939 ») prime sur l'infobox ; seule une
        // fermeture citée plus tardive (« Seule la ligne de Lezay à Melle fonctionne jusqu'en 1947 ») la repousse.
        $end = null;
        foreach ([
            ! empty($net['statement']) ? ['date' => $net['statement']['date'], 'quote' => $net['statement']['value'], 'explicit' => true] : null,
            ! empty($net['closed']) && empty($net['statement']) ? ['date' => $net['closed']['date'], 'quote' => SecondaryNetworkParser::fieldQuote($net['closed']), 'explicit' => true] : null,
            ! empty($net['last_closure']) ? ['date' => $net['last_closure']['date'], 'quote' => 'Dernière fermeture citée par l\'article : '.$net['last_closure']['value'], 'explicit' => false] : null,
        ] as $cand) {
            if ($cand && ($end === null || $cand['date'] > $end['date'])) {
                $end = $cand;
            }
        }
        // Sinon : première ouverture et dernière fermeture totale citées par l'article.
        foreach ($records as $r) {
            if ($r['article'] !== $title) {
                continue;
            }
            foreach ($r['events'] as $e) {
                if (! $net['start'] && str_starts_with($e['type'], 'opening') && ($start === null || $e['date'] < $start['date'])) {
                    $start = ['date' => $e['date'], 'quote' => 'Première ouverture citée par l\'article : '.($e['quote'] ?? '')];
                }
            }
        }
        if (! $start || ! $end) {
            return null;
        }
        $y0 = (int) substr($start['date'], 0, 4);
        $y1 = (int) substr($end['date'], 0, 4);
        // Fin après 1960 seulement si elle est explicite pour tout le réseau (phrase d'ensemble, champ
        // « fermeture », introduction), ou si les fermetures citées sont groupées (≤ 20 ans : Vivarais,
        // 1952-1968) ; sinon elle laisserait en service jusque-là des lignes fermées bien avant.
        if ($y0 < 1865 || $y1 > 1970 || $y1 - $y0 < 3
            || ($y1 > 1960 && ! $end['explicit'] && ($net['closure_spread'] ?? 99) > 20)) {
            return null;
        }

        return ['start' => ['date' => $y0.'-01-01', 'quote' => $start['quote']], 'end' => ['date' => $y1.'-01-01', 'quote' => $end['quote']], 'url' => $a['url']];
    }

    /**
     * Réseau de chaque ligne d'après les lieux géocodés des articles de réseau le long du tracé.
     *
     * @param  list<int>  $ids
     * @return array<int, array{0: ?string, 1: ?string, 2: ?string}> line_id → [article, rattachement, raison d'ambiguïté]
     */
    private function networksAlong(array $ids, array $records, array $spans, array $lines): array
    {
        $values = [];
        $seen = [];
        foreach ($records as $r) {
            if (! isset($spans[$r['article']])) {
                continue;
            }
            foreach ($r['places'] as $p) {
                $g = $p['geo'];
                $k = $r['article'].'|'.PlaceNames::normalize($p['label']);
                if ($g && ! isset($seen[$k])) {
                    $seen[$k] = true;
                    $values[] = sprintf('(%s, %s, %.6F, %.6F)', DB::getPdo()->quote($r['article']), DB::getPdo()->quote($p['label']), $g['lon'], $g['lat']);
                }
            }
        }
        if (! $values || ! $ids) {
            return [];
        }
        DB::statement('DROP TABLE IF EXISTS tmp_net_places');
        DB::statement('CREATE TEMP TABLE tmp_net_places (article text, label text, g geometry)');
        foreach (array_chunk($values, 500) as $chunk) {
            DB::statement('INSERT INTO tmp_net_places SELECT a, l, ST_Transform(ST_SetSRID(ST_MakePoint(x, y), 4326), 2154) FROM (VALUES '.implode(',', $chunk).') AS v(a, l, x, y)');
        }
        DB::statement('CREATE INDEX ON tmp_net_places USING GIST (g)');
        $by = [];
        foreach (DB::select('SELECT t.line_id, p.article, p.label, ST_X(p.g) AS x, ST_Y(p.g) AS y, ST_Distance(t.g, p.g) AS d
            FROM tmp_sec_lines t JOIN tmp_net_places p ON ST_DWithin(t.g, p.g, 3000) WHERE t.line_id IN ('.implode(',', $ids).')') as $r) {
            $by[(int) $r->line_id][$r->article][] = [(float) $r->x, (float) $r->y, (float) $r->d, $r->label];
        }
        $out = [];
        foreach ($by as $lid => $arts) {
            $ends = $lines[$lid]->ends ?? null;
            $scored = [];
            foreach ($arts as $art => $pts) {
                $nearEnd = false;
                foreach ($pts as $pt) {
                    foreach ($ends ?? [] as $e) {
                        if (hypot($pt[0] - $e[0], $pt[1] - $e[1]) <= 3000) {
                            $nearEnd = true;
                        }
                    }
                }
                if (count($pts) >= 2 && $nearEnd) {
                    $scored[] = [count($pts), -array_sum(array_column($pts, 2)) / count($pts), $art, $pts];

                    continue;
                }
                // Réseau urbain nommé d'après la ville d'où part la ligne (« Ancien tramway de Nancy »,
                // « Tramway de Toulon ») : un seul lieu suffit s'il est cette ville, à une extrémité.
                foreach ($pts as $pt) {
                    $city = PlaceNames::normalize($pt[3]);
                    $near = false;
                    foreach ($ends ?? [] as $e) {
                        $near = $near || hypot($pt[0] - $e[0], $pt[1] - $e[1]) <= 2000;
                    }
                    if ($near && strlen($city) >= 4 && preg_match('/^(?:ancien\s+)?(?:reseau\s+de\s+)?tramways?\s+(?:electriques?\s+)?(?:de|d|du)\s+'.preg_quote($city, '/').'(?:\s|$)/u', PlaceNames::normalize($art))) {
                        $scored[] = [1, -$pt[2], $art, [$pt]];
                        break;
                    }
                }
            }
            if (! $scored) {
                continue;
            }
            rsort($scored);
            [$n, , $art, $pts] = $scored[0];
            if (isset($scored[1]) && $scored[1][0] === $n) {
                $o = $spans[$scored[1][2]];
                $s = $spans[$art];
                if (abs((int) substr($o['start']['date'], 0, 4) - (int) substr($s['start']['date'], 0, 4)) > 5
                    || abs((int) substr($o['end']['date'], 0, 4) - (int) substr($s['end']['date'], 0, 4)) > 5) {
                    $out[$lid] = [null, null, sprintf('deux réseaux aussi proches aux dates différentes : « %s » et « %s »', $art, $scored[1][2])];

                    continue;
                }
            }
            $labels = array_values(array_unique(array_column($pts, 3)));
            $out[$lid] = [$art, 'position : '.implode(', ', array_slice($labels, 0, 4)).' cités par l\'article, le long du tracé', null];
        }

        return $out;
    }

    private function estimateEvent(string $type, array $bound, array $span, string $network, string $how): array
    {
        $year = substr($bound['date'], 0, 4);
        $what = $type === 'opening'
            ? "ouverture supposée à la mise en service du réseau « {$network} » ({$year})"
            : "fermeture supposée au plus tard à la fin de l'exploitation du réseau « {$network} » ({$year})";

        return ['type' => $type, 'date' => $bound['date'], 'precision' => 'year', 'confidence' => 'low', 'range' => false,
            'origin' => 'estimation', 'quote' => $bound['quote'], 'record' => null, 'article' => $network, 'url' => $span['url'],
            'route' => '', 'kind' => 'full', 'detail' => 'estimation par réseau ; rattachement : '.$how, 'whole' => true, 'from' => null, 'to' => null,
            'rank' => 90, 'source' => self::ESTIMATE_SOURCE,
            'description' => "Estimation (date non sourcée pour cette ligne) : {$what}, réseau reconnu par {$how} ; date à confirmer, jamais une date sûre"];
    }

    // ------------------------------------------------------------------
    // Écriture
    // ------------------------------------------------------------------

    private function write(array $plan): void
    {
        $fps = [];
        $n = 0;
        DB::transaction(function () use ($plan, &$fps, &$n) {
            foreach ($plan as $lid => $p) {
                if ($p['status'] !== 'write') {
                    continue;
                }
                foreach ($p['events'] as $e) {
                    $estimate = ($e['source'] ?? self::SOURCE) === self::ESTIMATE_SOURCE;
                    $fp = ($estimate ? 'estres:' : 'wpres:').sha1(implode('|', [$lid, $e['type'], $e['date'], $e['precision'],
                        PlaceNames::normalize((string) $e['from']), PlaceNames::normalize((string) $e['to'])]));
                    if (isset($fps[$fp])) {
                        continue;
                    }
                    $fps[$fp] = true;
                    // Estimation : toujours toute la ligne, déjà « résolue » (rien à localiser).
                    DB::statement(
                        'INSERT INTO line_events (line_id, type, event_date, date_precision, description, source, source_url, source_quote,
                            confidence, section_from, section_to, section_resolution, section_resolution_note, fingerprint, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                         ON CONFLICT (fingerprint) DO UPDATE SET description = EXCLUDED.description, source_url = EXCLUDED.source_url,
                            source_quote = EXCLUDED.source_quote, confidence = EXCLUDED.confidence, updated_at = NOW()',
                        [$lid, $e['type'], $e['date'], $e['precision'], $e['description'] ?? $this->description($e), $estimate ? self::ESTIMATE_SOURCE : self::SOURCE, $e['url'],
                            mb_substr((string) $e['quote'], 0, 300), $e['confidence'], $e['from'], $e['to'],
                            $estimate ? 'whole_line' : 'pending', $estimate ? 'estimation par réseau (date non sourcée pour cette ligne)' : null, $fp],
                    );
                    $n++;
                }
            }
            if (! $this->option('article')) {
                $this->stats['deleted'] = DB::table('line_events')->whereIn('source', [self::SOURCE, self::ESTIMATE_SOURCE])
                    ->when($fps, fn ($q) => $q->whereNotIn('fingerprint', array_keys($fps)))->delete();
            }
        });
        $this->stats['events_written'] = $n;
    }

    private function description(array $e): string
    {
        $label = SecondaryNetworkParser::TYPE_LABELS[$e['type']] ?? $e['type'];
        $art = 'article Wikipédia « '.$e['article'].' »';
        if (! empty($e['statement'])) {
            $where = $e['whole'] ? 'sur toute la ligne' : 'de la section '.$e['from'].' - '.$e['to'];

            return "{$label} {$where}, d'après une phrase d'ensemble sur les lignes du réseau ({$e['route']}, {$art})";
        }
        if (! empty($e['network_end'])) {
            return "{$label} au plus tard à la fin de l'exploitation du réseau, sur toute la ligne ({$art})";
        }
        if (! empty($e['inherited'])) {
            $where = $e['whole'] ? 'sur toute la ligne' : 'de la section '.$e['from'].' - '.$e['to'];

            return "{$label} {$where} au plus tard avec la ligne {$e['inherited']} ({$art})";
        }
        $when = $e['range'] ? ' (date tirée d\'une période)' : '';
        if ($e['whole']) {
            return "{$label} sur toute la ligne{$when} ({$e['route']}, {$art})";
        }

        return "{$label} de la section {$e['from']} - {$e['to']}{$when} ({$e['route']}, {$art})";
    }

    // ------------------------------------------------------------------
    // Rapports
    // ------------------------------------------------------------------

    private function review(array $r, ?int $lineId, string $why): void
    {
        $this->report['review'][] = [
            'article' => $r['article'], 'fiche' => implode(' - ', array_map(fn ($p) => $p['label'].($p['geo'] ? '' : ' (?)'), $r['places'])) ?: ($r['events'][0]['route'] ?? ''),
            'km' => $r['km'], 'evenements' => implode(' ; ', array_map(fn ($e) => $e['type'].' '.$e['date'], $r['events'])),
            'ligne_vff' => $lineId, 'raison' => $why, 'citation' => $r['quote'],
        ];
    }

    private function writeReports(array $plan): void
    {
        $dir = storage_path('app/reports');
        @mkdir($dir, 0775, true);
        $written = [];
        $dated = [];
        foreach ($plan as $lid => $p) {
            foreach ($p['events'] as $e) {
                $row = [
                    'line_id' => $lid, 'ligne' => $p['line']->name, 'reseau_vff' => $p['line']->network, 'statut' => $p['status'],
                    'source' => $e['source'] ?? self::SOURCE,
                    'type' => $e['type'], 'date' => $e['date'], 'precision' => $e['precision'], 'confiance' => $e['confidence'],
                    'portion' => $e['whole'] ? 'toute la ligne' : $e['from'].' - '.$e['to'], 'rapprochement' => $e['kind'].' : '.$e['detail'],
                    'fiche' => $e['route'], 'article' => $e['article'], 'conflits' => implode(' ; ', $e['conflicts'] ?? []),
                    'raison' => $p['reason'], 'citation' => $e['quote'],
                ];
                if ($p['status'] === 'dated') {
                    $row['deja_connu'] = $p['line']->first_opening;
                    $dated[] = $row;
                } else {
                    $written[] = $row;
                }
            }
        }
        $this->csv($dir.'/reseaux-secondaires-evenements.csv', $written);
        $this->csv($dir.'/reseaux-secondaires-lignes-deja-datees.csv', $dated);
        $this->csv($dir.'/reseaux-secondaires-a-verifier.csv', $this->report['review']);
        if (isset($this->report['estimates'])) {
            $this->csv($dir.'/reseaux-secondaires-estimations.csv', $this->report['estimates']);
        }
    }

    private function csv(string $file, array $rows): void
    {
        $fh = fopen($file, 'w');
        if ($rows) {
            $keys = [];
            foreach ($rows as $r) {
                foreach (array_keys($r) as $k) {
                    $keys[$k] = true;
                }
            }
            $keys = array_keys($keys);
            fputcsv($fh, $keys, escape: '');
            foreach ($rows as $r) {
                fputcsv($fh, array_map(fn ($k) => $r[$k] ?? '', $keys), escape: '');
            }
        }
        fclose($fh);
    }

    private function summary(array $plan, bool $dry): void
    {
        $count = ['write' => 0, 'dated' => 0, 'skip' => 0];
        $events = 0;
        $byType = [];
        $agree = [0, 0];
        $articles = [];
        foreach ($plan as $p) {
            $count[$p['status']]++;
            if ($p['status'] === 'write') {
                $events += count($p['events']);
                foreach ($p['events'] as $e) {
                    $byType[$e['type']] = ($byType[$e['type']] ?? 0) + 1;
                    $articles[$e['article']] = true;
                }
            }
            if ($p['status'] === 'dated' && $p['line']->first_opening) {
                foreach ($p['events'] as $e) {
                    if (str_starts_with($e['type'], 'opening') && $e['whole']) {
                        $agree[1]++;
                        if (abs((int) substr($e['date'], 0, 4) - (int) substr($p['line']->first_opening, 0, 4)) <= 2) {
                            $agree[0]++;
                        }
                        break;
                    }
                }
            }
        }
        $km = array_sum(array_map(fn ($p) => $p['status'] === 'write' ? $p['line']->length_m : 0, $plan)) / 1000;
        // Lignes dont TOUTES les dates sont estimées / dont une partie l'est (jamais comptées comme datées sûrement).
        $estAll = array_filter($plan, fn ($p) => $p['status'] === 'write' && ! empty($p['estimated']) && $p['estimated'] === count($p['events']));
        $estPart = array_filter($plan, fn ($p) => $p['status'] === 'write' && ! empty($p['estimated']) && $p['estimated'] < count($p['events']));
        $this->stats['lines_estimated_only'] = count($estAll);
        $this->stats['km_estimated_only'] = round(array_sum(array_map(fn ($p) => $p['line']->length_m, $estAll)) / 1000);
        $this->stats['lines_partly_estimated'] = count($estPart);
        $this->stats['km_partly_estimated'] = round(array_sum(array_map(fn ($p) => $p['line']->length_m, $estPart)) / 1000);
        $this->newLine();
        $this->info(sprintf('%s %d lignes datées (%s km) à partir de %d articles · %d événements%s',
            $dry ? '[dry-run]' : '✓', $count['write'], number_format($km, 0, ',', ' '), count($articles), $events,
            $dry ? '' : ' écrits · '.($this->stats['deleted'] ?? 0).' obsolètes supprimés'));
        $this->line(sprintf('  dont estimations par réseau (%s, confiance low) : %d lignes entièrement estimées (%s km), %d complétées par estimation (%s km)',
            self::ESTIMATE_SOURCE, $this->stats['lines_estimated_only'], number_format($this->stats['km_estimated_only'], 0, ',', ' '),
            $this->stats['lines_partly_estimated'], number_format($this->stats['km_partly_estimated'], 0, ',', ' ')));
        $this->line(sprintf('  Lignes rapprochées déjà datées par ailleurs : %d (ouverture concordante à ±2 ans : %d/%d)', $count['dated'], $agree[0], $agree[1]));
        $this->line(sprintf('  Lignes rapprochées non écrites : %d · fiches datées non rattachées : %d', $count['skip'], count($this->report['review'])));
        arsort($byType);
        $this->table(['type', 'événements'], array_map(fn ($k, $v) => [$k, $v], array_keys($byType), $byType));
        $this->line('  Rapports : storage/app/reports/reseaux-secondaires-{evenements,a-verifier,lignes-deja-datees}.csv');
        file_put_contents(storage_path('app/reports/reseaux-secondaires-import.json'), json_encode($this->stats + [
            'lines_written' => $count['write'], 'lines_dated_elsewhere' => $count['dated'], 'lines_skipped' => $count['skip'],
            'km_written' => round($km), 'events' => $events, 'by_type' => $byType, 'opening_agreement' => $agree, 'dry_run' => $dry,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
