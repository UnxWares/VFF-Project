<?php

namespace App\Console\Commands;

use App\Support\CsvSafe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Wikidata (CC0) → fiche ligne enrichie : rattache les items « ligne de chemin
 * de fer » situés en France aux lignes VFF et en importe caractéristiques et
 * dates.
 *
 * Pourquoi : Wikidata recense ~1 400 lignes françaises, dont ~860 portent leur
 * code RFN (P1671) et ~900 une date d'ouverture (P1619). C'est la source la
 * MOINS fiable de la cascade (cf. EraTimeline::SOURCE_PRIORITY : dates souvent
 * à l'année, fusions d'articles, ouverture confondue avec inauguration), mais
 * la plus large et la seule STRUCTURÉE pour les exploitants historiques datés,
 * l'électrification par portion et les terminus géolocalisés. On ne fait donc
 * que COMPLÉTER : un champ déjà rempli (SNCF, lignes-oubliees, saisie
 * manuelle, Wikipédia…) n'est jamais écrasé, l'écart est signalé.
 *
 * Étapes :
 *   1. collecte, en cache dans storage/app/sources/wikidata (--refresh pour
 *      relire) : liste SPARQL des items (P31/P279* Q728937, P17 Q142) ; entités
 *      complètes par lots de 50 (wbgetentities : rangs, qualificatifs,
 *      précision des dates, références) ; puis requêtes SPARQL GROUPÉES
 *      (VALUES) pour les entités citées (libellés, coordonnées, tension,
 *      écartement), les gares rattachées (P81 inverse) et la résolution
 *      administrative (P131* → département INSEE / région actuelle) ;
 *   2. rattachement :
 *        0. QID déclaré à la création de la ligne (alt_names.wikidata_qid :
 *           vff:link-traces, vff:seed-off-network-lines) : lien direct ;
 *        a. code RFN normalisé (« 956 000 » → 956000). Un item à plusieurs
 *           codes (LGV à branches) ou à code qualifié P518 (portion) n'est
 *           lié qu'en « code_part » : QID parent + champs non dimensionnels,
 *           ni longueur ni dates. Deux items pour un même code : le meilleur
 *           nom gagne, l'autre est « duplicate » (signalé) ;
 *        b. sinon libellé (extrémités « X à Y » normalisées) + proximité des
 *           terminus / gares Wikidata avec le tracé VFF (segments, géométries
 *           d'ère, tracés bruts, tracés SNCF). Garde-fous : homonyme loin du
 *           tracé rejeté, deux candidats proches → aucun lien (« ambiguous »),
 *           jamais de lien entre un code Wikidata et une ligne VFF portant un
 *           AUTRE code ; sans géométrie, seul un nom identique et unique passe
 *           (« name », confiance basse) ;
 *        c. nom sans extrémités (« ligne de la côte orientale corse ») : gares
 *           et terminus Wikidata (≥ 3) à moins de 1 km du tracé d'UNE seule
 *           ligne VFF pour 80 % d'entre eux (« stations_geo ») ;
 *   3. écriture, champs VIDES seulement : alt_names.wikidata (QID), longueur,
 *      écartement, voies (P31 « voie unique »), vitesse, exploitants et
 *      propriétaires datés, régions / départements, article frwiki ;
 *      électrification seulement pour une ligne SANS segment (sinon c'est
 *      l'agrégat calculé par LineClassifier) ; line_events source='wikidata'
 *      (ouverture, réouverture, fermeture, électrification, événements clés
 *      P793), section_resolution 'whole_line' sauf qualificatif de portion
 *      (→ 'pending' pour le résolveur de sections), année seule → confiance
 *      'low', fingerprint stable (GUID de la déclaration) ; line_sources
 *      (line_id, 'wikidata') ; staging complet dans `wikidata_lines` ;
 *   4. rapports dans storage/app/reports/ :
 *        - wikidata-conflits.csv : écarts de champs et de dates avec les
 *          autres sources (ex. ligne de Vincennes ouverte en 1859 selon
 *          Wikidata, 1853 ailleurs) - jamais écrasés ;
 *        - wikidata-lignes-absentes.csv : items sans ligne VFF (ni code, ni
 *          nom + position), pour l'agent de couverture (on ne crée rien) ;
 *        - wikidata-import.json : chiffres de la passe.
 *
 * Rejouable : upsert par fingerprint, puis suppression des seuls événements
 * source='wikidata' qui ne sont plus produits. --report-only recalcule les
 * conflits et les absentes depuis `wikidata_lines` (à relancer quand les
 * autres importeurs ont fini).
 */
class ImportWikidataLines extends Command
{
    protected $signature = 'vff:import-wikidata-lines
        {--refresh : Re-télécharge Wikidata au lieu d\'utiliser le cache local}
        {--from-cache : N\'utilise que le cache local, aucune requête réseau}
        {--qid=* : Limite l\'écriture aux items donnés (tests, ex. --qid=Q3239771), sans nettoyage ni CSV}
        {--dry-run : Rattache et calcule les rapports sans écrire en base}
        {--report-only : Recalcule seulement conflits + lignes absentes depuis wikidata_lines}';

    protected $description = 'Wikidata → lignes FR : rattachement (QID), caractéristiques, dates (line_events), conflits, lignes absentes';

    public const SOURCE = 'wikidata';

    private const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    private const SPARQL_URL = 'https://query.wikidata.org/sparql';

    private const API_URL = 'https://www.wikidata.org/w/api.php';

    /** Délai minimal entre deux requêtes (politesse). */
    private const DELAY_S = 1.0;

    private const U_KM = 'Q828224';

    private const U_M = 'Q11573';

    private const U_MILE = 'Q253276';

    private const U_KMH = 'Q180154';

    private const U_MPH = 'Q211256';

    private const ELEC_NONE = 'Q11663772';

    private const SINGLE_TRACK = 'Q1326900';

    /** État d'usage (P5817) : ligne encore (au moins en partie) en service. */
    private const STATE_ACTIVE = ['Q55654238', 'Q109551035'];

    /** État d'usage : projet, en construction, abandonné. */
    private const STATE_PROJECT = ['Q12377751', 'Q30108381', 'Q811683'];

    /** Classement des items par nature (P31) ; ce qui ne tombe nulle part est « ferroviaire ». */
    private const CATEGORIES = [
        'urbain' => ['Q15079663', 'Q107343049', 'Q105967897', 'Q1199344', 'Q15145537', 'Q24335959', 'Q15145593',
            'Q27997185', 'Q639030', 'Q1144784', 'Q1268865', 'Q12162186', 'Q385653', 'Q145179', 'Q1067164'],
        'funiculaire' => ['Q142031', 'Q2550729', 'Q109708740', 'Q20820102', 'Q1144661', 'Q797802'],
        'service' => ['Q124130104', 'Q125209036', 'Q15141321', 'Q106978510', 'Q1192191', 'Q18325841', 'Q249556',
            'Q924286', 'Q104666804', 'Q520381', 'Q2125658', 'Q163740', 'Q18704634', 'Q33506', 'Q1614140'],
        'projet' => ['Q28043022', 'Q21514702', 'Q124834336'],
    ];

    /** Les 18 régions actuelles (P131* d'une entité → région). */
    private const REGIONS = [
        'Q18338206' => 'Auvergne-Rhône-Alpes', 'Q18578267' => 'Bourgogne-Franche-Comté', 'Q12130' => 'Bretagne',
        'Q13947' => 'Centre-Val de Loire', 'Q14112' => 'Corse', 'Q18677983' => 'Grand Est',
        'Q18677767' => 'Hauts-de-France', 'Q13917' => 'Île-de-France', 'Q18677875' => 'Normandie',
        'Q18678082' => 'Nouvelle-Aquitaine', 'Q18678265' => 'Occitanie', 'Q16994' => 'Pays de la Loire',
        'Q15104' => 'Provence-Alpes-Côte d\'Azur', 'Q17012' => 'Guadeloupe', 'Q17054' => 'Martinique',
        'Q3769' => 'Guyane', 'Q17070' => 'La Réunion', 'Q17063' => 'Mayotte',
    ];

    /** Événements clés (P793) → [type VFF, libellé]. */
    private const KEY_EVENTS = [
        'Q5135520' => ['closure', 'Fermeture'],
        'Q29933828' => ['opening', 'Mise en service'],
        'Q1417098' => ['other', 'Inauguration'],
        'Q2140650' => ['other', 'Déclaration d\'utilité publique'],
        'Q3422045' => ['other', 'Déclaration d\'utilité publique'],
        'Q2144402' => ['modernization', 'Rénovation'],
        'Q113001376' => ['modernization', 'Automatisation'],
        'Q2872621' => ['modernization', 'Automatisation'],
        'Q17174450' => ['modernization', 'Automatisation'],
        'Q171558' => ['accident', 'Accident'],
        'Q1331380' => ['accident', 'Déraillement'],
        'Q3875186' => ['other', 'Début des travaux de construction'],
    ];

    private const PROP_LABELS = [
        'P1619' => 'date d\'ouverture officielle', 'P729' => 'date de mise en service',
        'P3999' => 'date de fermeture', 'P576' => 'date de dissolution ou d\'abolition',
        'P930' => 'type d\'électrification', 'P793' => 'événement clé',
    ];

    private const PRECISION_FR = ['day' => 'jour', 'month' => 'mois', 'year' => 'année'];

    /** Familles d'événements comparées entre sources pour les conflits de dates. */
    private const FAMILIES = [
        'ouverture' => ['opening', 'opening_passenger', 'opening_freight'],
        'fermeture' => ['closure', 'closure_passenger', 'closure_freight', 'declassification'],
        'electrification' => ['electrification'],
    ];

    /** Méthodes de rattachement qui autorisent l'import des dates et dimensions. */
    private const FULL_METHODS = ['code', 'name_geo', 'name', 'declared', 'stations_geo'];

    /** Rapprochement par les gares (étape c) : points minimum, part à moins de STATIONS_MAX_M, part max de la suivante. */
    private const STATIONS_MIN_POINTS = 3;

    private const STATIONS_MAX_M = 1000;

    private const STATIONS_MIN_SHARE = 0.8;

    private const STATIONS_RIVAL_SHARE = 0.5;

    private float $lastRequestAt = 0.0;

    private ?string $fetchedAt = null;

    /** @var array<string, list<string>> QID → natures P31 */
    private array $types = [];

    /** @var array<string, array> QID → entité complète (wbgetentities) */
    private array $entities = [];

    /** @var array<string, array> QID cité → {fr, en, lat, lon, volt, width, adm} */
    private array $refs = [];

    /** @var array<string, list<array>> QID ligne → gares P81 {qid, lat, lon, adm} */
    private array $stations = [];

    /** @var array<string, array> QID administratif → {deps: {code: nom}, regs: list<QID>} */
    private array $admin = [];

    /** @var array<string, int|array> */
    private array $stats = [];

    public function handle(): int
    {
        @mkdir($this->cacheDir(), 0775, true);
        @mkdir($this->reportDir(), 0775, true);

        if ($this->option('report-only')) {
            $list = $this->cacheDir().'/items.json';
            $this->fetchedAt = is_file($list) ? (json_decode((string) file_get_contents($list), true)['fetched_at'] ?? null) : null;
            $rows = $this->stagingRows();
            $this->info(count($rows).' item(s) dans wikidata_lines');
            $this->reports($rows, true, true, '');

            return self::SUCCESS;
        }

        if (! $this->collect()) {
            return self::FAILURE;
        }

        $this->info('→ Normalisation des '.count($this->entities).' item(s)…');
        $items = [];
        foreach ($this->entities as $qid => $e) {
            $items[$qid] = $this->buildFacts($qid, $e);
        }

        $this->info('→ Rattachement aux lignes VFF…');
        $links = $this->match($items);

        $only = array_values(array_filter((array) $this->option('qid')));
        $scope = $only !== [] ? array_intersect_key($items, array_flip($only)) : $items;
        $dry = (bool) $this->option('dry-run');

        if (! $dry) {
            $prevLinks = DB::table('wikidata_lines')->whereNotNull('line_id')->pluck('line_id', 'qid')->all();
            $prevWritten = $this->previousWrites();
            $this->writeStaging($scope, $links);
            $produced = $this->apply($scope, $links, $prevWritten);
            if ($only === []) {
                $this->cleanup($produced, $links, $prevLinks);
            }
            $rows = $this->stagingRows($only !== [] ? $only : null);
        } else {
            $rows = $this->memoryRows($scope, $links);
        }

        $this->reports($rows, ! $dry, $only === [], $dry ? '-dryrun' : '');
        if ($only !== []) {
            foreach ($only as $q) {
                $this->detail($q, $items[$q] ?? null, $links[$q] ?? null);
            }
        }

        return self::SUCCESS;
    }

    // ───────────────────────────── 1. Collecte ─────────────────────────────

    private function collect(): bool
    {
        $listPath = $this->cacheDir().'/items.json';
        if ($this->option('refresh') || ! is_file($listPath)) {
            if ($this->option('from-cache')) {
                $this->error('Cache absent : '.$listPath);

                return false;
            }
            $this->info('→ Liste SPARQL des lignes ferroviaires françaises…');
            $rows = $this->sparql('SELECT ?item (GROUP_CONCAT(DISTINCT STRAFTER(STR(?t), "entity/"); separator="|") AS ?types)
                WHERE { ?item wdt:P31/wdt:P279* wd:Q728937 ; wdt:P17 wd:Q142 ; wdt:P31 ?t . } GROUP BY ?item');
            $list = [];
            foreach ($rows as $b) {
                $list[$this->qidOf($b['item']['value'])] = explode('|', $b['types']['value']);
            }
            ksort($list, SORT_NATURAL);
            $this->saveJson($listPath, ['fetched_at' => now()->toDateTimeString(), 'items' => $list]);
        }
        $cached = json_decode((string) file_get_contents($listPath), true);
        $this->types = $cached['items'] ?? [];
        $this->fetchedAt = $cached['fetched_at'] ?? now()->toDateTimeString();
        $this->info(count($this->types).' item(s) Wikidata (liste du '.$this->fetchedAt.')');

        $this->entities = $this->cachedMap('entities.json', array_keys($this->types), function (array $ids) {
            $this->info('→ Entités complètes ('.count($ids).', lots de 50)…');
            $out = [];
            foreach (array_chunk($ids, 50) as $chunk) {
                $d = $this->api(['action' => 'wbgetentities', 'ids' => implode('|', $chunk),
                    'props' => 'labels|aliases|claims|sitelinks', 'languages' => 'fr|en', 'sitefilter' => 'frwiki', 'format' => 'json']);
                foreach ($d['entities'] ?? [] as $q => $e) {
                    if (! isset($e['missing'])) {
                        $out[$q] = $e;
                    }
                }
                $this->output->write('.');
            }
            $this->newLine();

            return $out;
        });
        $this->entities = array_filter(array_intersect_key($this->entities, $this->types));
        if ($this->entities === []) {
            $this->error('Aucune entité en cache.');

            return false;
        }

        $this->refs = $this->cachedMap('refs.json', $this->referencedIds(), fn (array $ids) => $this->fetchRefs($ids));
        $this->stations = $this->cachedMap('stations.json', array_keys($this->entities), fn (array $ids) => $this->fetchStations($ids));

        $adm = [];
        foreach ($this->entities as $e) {
            foreach ($this->statements($e, 'P131') as $st) {
                if ($a = $this->entityId($st['mainsnak'])) {
                    $adm[$a] = true;
                }
            }
        }
        foreach ($this->refs as $r) {
            foreach ($r['adm'] ?? [] as $a) {
                $adm[$a] = true;
            }
        }
        foreach ($this->stations as $list) {
            foreach ($list as $s) {
                foreach ($s['adm'] ?? [] as $a) {
                    $adm[$a] = true;
                }
            }
        }
        $this->admin = $this->cachedMap('admin.json', array_keys($adm), fn (array $ids) => $this->fetchAdmin($ids));

        return true;
    }

    /** QID cités par les déclarations utiles (exploitants, terminus, électrification…). */
    private function referencedIds(): array
    {
        $main = ['P137', 'P127', 'P559', 'P609', 'P1427', 'P1444', 'P930', 'P1064', 'P5817', 'P793', 'P1366', 'P2283'];
        $quals = ['P559', 'P1427', 'P1444', 'P518', 'P828', 'P2283'];
        $ids = [];
        foreach ($this->entities as $e) {
            foreach ($e['claims'] ?? [] as $p => $sts) {
                foreach ($sts as $st) {
                    if (in_array($p, $main, true) && ($id = $this->entityId($st['mainsnak']))) {
                        $ids[$id] = true;
                    }
                    foreach ($quals as $qp) {
                        foreach ($st['qualifiers'][$qp] ?? [] as $s) {
                            if ($id = $this->entityId($s)) {
                                $ids[$id] = true;
                            }
                        }
                    }
                    foreach ($st['references'] ?? [] as $ref) {
                        foreach (['P248', 'P143'] as $rp) {
                            foreach ($ref['snaks'][$rp] ?? [] as $s) {
                                if ($id = $this->entityId($s)) {
                                    $ids[$id] = true;
                                }
                            }
                        }
                    }
                }
            }
        }
        $ids = array_keys(array_diff_key($ids, $this->entities));
        sort($ids, SORT_NATURAL);

        return $ids;
    }

    /** Libellés, coordonnées, tension (V), largeur de voie (m) et P131 des entités citées. */
    private function fetchRefs(array $ids): array
    {
        $this->info('→ Entités citées ('.count($ids).')…');
        $out = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $rows = $this->sparql('SELECT ?e (SAMPLE(?fr) AS ?lfr) (SAMPLE(?en) AS ?len) (SAMPLE(?c) AS ?coord)
                (SAMPLE(?v) AS ?volt) (SAMPLE(?w) AS ?width)
                (GROUP_CONCAT(DISTINCT STRAFTER(STR(?adm), "entity/"); separator="|") AS ?adms)
                WHERE {
                  VALUES ?e { '.$this->values($chunk).' }
                  OPTIONAL { ?e rdfs:label ?fr . FILTER(LANG(?fr) = "fr") }
                  OPTIONAL { ?e rdfs:label ?en . FILTER(LANG(?en) = "en") }
                  OPTIONAL { ?e wdt:P625 ?c }
                  OPTIONAL { ?e p:P2436/psn:P2436/wikibase:quantityAmount ?v }
                  OPTIONAL { ?e p:P2049/psn:P2049/wikibase:quantityAmount ?w }
                  OPTIONAL { ?e wdt:P131 ?adm }
                } GROUP BY ?e');
            foreach ($rows as $b) {
                $pt = $this->parsePoint($b['coord']['value'] ?? null);
                $out[$this->qidOf($b['e']['value'])] = [
                    'fr' => $b['lfr']['value'] ?? null,
                    'en' => $b['len']['value'] ?? null,
                    'lon' => $pt[0] ?? null,
                    'lat' => $pt[1] ?? null,
                    'volt' => isset($b['volt']) ? (float) $b['volt']['value'] : null,
                    'width' => isset($b['width']) ? (float) $b['width']['value'] : null,
                    'adm' => array_values(array_filter(explode('|', $b['adms']['value'] ?? ''))),
                ];
            }
            $this->output->write('.');
        }
        $this->newLine();

        return $out;
    }

    /** Gares dont « ligne desservie » (P81) pointe vers l'item : points de rapprochement + communes. */
    private function fetchStations(array $ids): array
    {
        $this->info('→ Gares rattachées par P81 ('.count($ids).' lignes)…');
        $out = [];
        foreach (array_chunk($ids, 150) as $chunk) {
            $rows = $this->sparql('SELECT ?line ?st (SAMPLE(?c) AS ?coord)
                (GROUP_CONCAT(DISTINCT STRAFTER(STR(?adm), "entity/"); separator="|") AS ?adms)
                WHERE {
                  VALUES ?line { '.$this->values($chunk).' }
                  ?st wdt:P81 ?line .
                  OPTIONAL { ?st wdt:P625 ?c }
                  OPTIONAL { ?st wdt:P131 ?adm }
                } GROUP BY ?line ?st');
            foreach ($rows as $b) {
                $pt = $this->parsePoint($b['coord']['value'] ?? null);
                $out[$this->qidOf($b['line']['value'])][] = [
                    'qid' => $this->qidOf($b['st']['value']),
                    'lon' => $pt[0] ?? null,
                    'lat' => $pt[1] ?? null,
                    'adm' => array_values(array_filter(explode('|', $b['adms']['value'] ?? ''))),
                ];
            }
            $this->output->write('.');
        }
        $this->newLine();

        return $out;
    }

    /** P131* → départements (code INSEE P2586) et régions actuelles. */
    private function fetchAdmin(array $ids): array
    {
        $this->info('→ Résolution administrative ('.count($ids).' entités)…');
        $regions = $this->values(array_keys(self::REGIONS));
        $out = [];
        foreach (array_chunk($ids, 150) as $chunk) {
            $rows = $this->sparql('SELECT ?e ?d ?code ?dl ?r WHERE {
                  VALUES ?e { '.$this->values($chunk).' }
                  { ?e wdt:P131* ?d . ?d wdt:P2586 ?code . FILTER NOT EXISTS { ?d wdt:P576 [] }
                    OPTIONAL { ?d rdfs:label ?dl . FILTER(LANG(?dl) = "fr") } }
                  UNION
                  { VALUES ?r { '.$regions.' } ?e wdt:P131* ?r . }
                }');
            foreach ($rows as $b) {
                $e = $this->qidOf($b['e']['value']);
                $out[$e] ??= ['deps' => [], 'regs' => []];
                if (isset($b['code'], $b['dl'])) {
                    $out[$e]['deps'][$b['code']['value']] = $b['dl']['value'];
                }
                if (isset($b['r'])) {
                    $out[$e]['regs'][] = $this->qidOf($b['r']['value']);
                }
            }
            $this->output->write('.');
        }
        $this->newLine();
        foreach ($out as &$v) {
            $v['regs'] = array_values(array_unique($v['regs']));
        }

        return $out;
    }

    // ───────────────────────────── 2. Faits normalisés ─────────────────────────────

    private function buildFacts(string $qid, array $e): array
    {
        $types = $this->types[$qid] ?? [];
        $stateSt = $this->best($e, 'P5817');
        $state = $stateSt ? $this->entityId($stateSt[0]['mainsnak']) : null;
        $replaced = ($r = $this->best($e, 'P1366')) ? $this->entityId($r[0]['mainsnak']) : null;

        $names = array_values(array_unique(array_filter(array_merge(
            [$e['labels']['fr']['value'] ?? null, $e['labels']['en']['value'] ?? null, $e['sitelinks']['frwiki']['title'] ?? null],
            array_map(fn ($a) => $a['value'], $e['aliases']['fr'] ?? []),
        ))));

        // Codes RFN (P1671) : « 956 000 », « 956000 », « 001 000 »… ; P518 = portion concernée.
        $codes = [];
        foreach ($this->statements($e, 'P1671') as $st) {
            $raw = $this->dv($st['mainsnak']);
            if (! is_string($raw)) {
                continue;
            }
            $norm = preg_replace('/[\s\x{00A0}\x{202F}.]+/u', '', $raw);
            $part = array_values(array_diff($this->qualIds($st, 'P518'), ['Q142']));
            $codes[] = ['raw' => $raw, 'code' => preg_match('/^\d{6}$/', $norm) ? $norm : null, 'part' => $part];
        }

        // Longueur (P2043) : valeur actuelle (sans P582) et non restreinte à une portion.
        $lens = [];
        foreach ($this->best($e, 'P2043') as $st) {
            if ($this->qualTime($st, 'P582') || $this->qualIds($st, 'P518')) {
                continue;
            }
            $q = $this->quantity($this->dv($st['mainsnak']));
            $m = $q === null ? null : match ($q['unit']) {
                self::U_KM => $q['amount'] * 1000, self::U_M => $q['amount'], self::U_MILE => $q['amount'] * 1609.344, default => null,
            };
            if ($m !== null && $m > 0) {
                $lens[] = (int) round($m);
            }
        }
        $lens = array_values(array_unique($lens));

        // Écartement (P1064) : l'item d'écartement porte sa largeur (P2049, normalisée en m).
        $gauges = [];
        foreach ($this->best($e, 'P1064') as $st) {
            $g = $this->entityId($st['mainsnak']);
            if ($g && ! $this->qualTime($st, 'P582') && ($w = $this->refs[$g]['width'] ?? null)) {
                $gauges[] = (int) round($w * 1000);
            }
        }
        $gauges = array_values(array_unique($gauges));

        // Vitesse limite (P3086).
        $speeds = [];
        foreach ($this->best($e, 'P3086') as $st) {
            $q = $this->quantity($this->dv($st['mainsnak']));
            if ($q && ! $this->qualTime($st, 'P582')) {
                $v = match ($q['unit']) {
                    self::U_KMH => $q['amount'], self::U_MPH => $q['amount'] * 1.609344, default => null
                };
                if ($v) {
                    $speeds[] = (int) round($v);
                }
            }
        }
        $speeds = array_values(array_unique($speeds));

        // Électrification (P930) : type, tension, portion (P559 / P518), dates (P580 / P582).
        $elec = [];
        foreach ($this->statements($e, 'P930') as $st) {
            $t = $this->entityId($st['mainsnak']);
            if (! $t) {
                continue;
            }
            [$from, $to] = $this->section($st);
            $volt = $this->refs[$t]['volt'] ?? null;
            $elec[] = ['type' => $t, 'label' => $this->label($t), 'none' => $t === self::ELEC_NONE,
                'voltage_v' => $volt ? (int) round($volt) : null, 'from' => $from, 'to' => $to,
                'since' => $this->qualTime($st, 'P580')['date'] ?? null, 'until' => $this->qualTime($st, 'P582')['date'] ?? null];
        }
        $current = array_values(array_filter($elec, fn ($x) => $x['until'] === null));
        $elecSummary = null;
        if ($current !== []) {
            $kinds = array_values(array_unique(array_column($current, 'type')));
            $partial = (bool) array_filter($current, fn ($x) => $x['from'] !== null || $x['to'] !== null);
            if (count($kinds) === 1) {
                $x = $current[0];
                $elecSummary = ['electrified' => ! $x['none'], 'voltage_v' => $x['none'] ? null : $x['voltage_v'], 'label' => $x['label'], 'partial' => $partial];
            } else {
                $elecSummary = ['electrified' => (bool) array_filter($current, fn ($x) => ! $x['none']), 'voltage_v' => null, 'label' => 'mixte', 'partial' => true];
            }
        }

        // Exploitants (P137) et propriétaires (P127), avec P580 / P582.
        $ops = [];
        foreach (['P137' => 'exploitant', 'P127' => 'propriétaire'] as $p => $role) {
            foreach ($this->statements($e, $p) as $st) {
                $o = $this->entityId($st['mainsnak']);
                $name = $this->label($o);
                if (! $name) {
                    continue;
                }
                $from = $this->qualTime($st, 'P580')['year'] ?? null;
                $to = $this->qualTime($st, 'P582')['year'] ?? null;
                $k = $o.'|'.$from.'|'.$to;
                if (isset($ops[$k])) {
                    $ops[$k]['role'] .= ', '.$role;

                    continue;
                }
                $ops[$k] = ['operator' => $name, 'from_year' => $from, 'to_year' => $to, 'role' => $role, 'wikidata' => $o, 'source' => self::SOURCE];
            }
        }
        $ops = array_values($ops);
        usort($ops, fn ($a, $b) => [$a['from_year'] === null, $a['from_year'], $a['to_year'] ?? 9999] <=> [$b['from_year'] === null, $b['from_year'], $b['to_year'] ?? 9999]);

        // Terminus (P559, P609, point de départ / destination).
        $termini = [];
        foreach (['P559', 'P609', 'P1427', 'P1444'] as $p) {
            foreach ($this->statements($e, $p) as $st) {
                $t = $this->entityId($st['mainsnak']);
                if (! $t || isset($termini[$t])) {
                    continue;
                }
                $r = $this->refs[$t] ?? [];
                $termini[$t] = ['qid' => $t, 'label' => $this->label($t), 'lat' => $r['lat'] ?? null, 'lon' => $r['lon'] ?? null,
                    'prop' => $p, 'historique' => $this->qualTime($st, 'P582') !== null];
            }
        }
        $termini = array_values($termini);

        // Régions / départements : P131 de la ligne ; à défaut, communes des gares et terminus.
        $admLine = [];
        foreach ($this->statements($e, 'P131') as $st) {
            if (! $this->qualTime($st, 'P582') && ($a = $this->entityId($st['mainsnak']))) {
                $admLine[] = $a;
            }
        }
        $admDerived = [];
        foreach ($this->stations[$qid] ?? [] as $s) {
            array_push($admDerived, ...($s['adm'] ?? []));
        }
        foreach ($termini as $t) {
            array_push($admDerived, ...($this->refs[$t['qid']]['adm'] ?? []));
        }
        [$depsL, $regsL] = $this->resolveAdmin($admLine);
        [$depsD, $regsD] = $this->resolveAdmin($admDerived);
        $deps = $depsL !== [] ? $depsL : $depsD;
        $regs = $regsL !== [] ? $regsL : $regsD;

        // Points de rapprochement géographique : terminus, gares P81, coordonnées propres.
        $points = [];
        foreach ($termini as $t) {
            if ($t['lat'] !== null && ! $t['historique']) {
                $points[] = ['terminus', $t['lon'], $t['lat']];
            }
        }
        foreach ($this->stations[$qid] ?? [] as $s) {
            if ($s['lat'] !== null) {
                $points[] = ['gare', $s['lon'], $s['lat']];
            }
        }
        foreach ($this->best($e, 'P625') as $st) {
            $v = $this->dv($st['mainsnak']);
            if (is_array($v) && isset($v['latitude']) && str_ends_with((string) ($v['globe'] ?? 'Q2'), 'Q2')) {
                $points[] = ['coord', (float) $v['longitude'], (float) $v['latitude']];
            }
        }

        $osm = ($o = $this->best($e, 'P402')) ? $this->dv($o[0]['mainsnak']) : null;

        return [
            'qid' => $qid,
            'label' => $e['labels']['fr']['value'] ?? $e['labels']['en']['value'] ?? null,
            'names' => $names,
            'frwiki' => $e['sitelinks']['frwiki']['title'] ?? null,
            'kinds' => $types,
            'category' => $this->category($types, $state),
            'state' => $state ? $this->label($state) : null,
            'state_qid' => $state,
            'replaced_by' => $replaced ? $this->label($replaced) : null,
            'codes' => $codes,
            'length_m' => count($lens) === 1 ? $lens[0] : null,
            'lengths_m' => $lens,
            'gauge_mm' => count($gauges) === 1 ? $gauges[0] : null,
            'gauges_mm' => $gauges,
            'tracks' => in_array(self::SINGLE_TRACK, $types, true) ? 1 : null,
            'max_speed_kmh' => $speeds !== [] ? max($speeds) : null,
            'electrification' => $elec,
            'electrification_summary' => $elecSummary,
            'operators' => $ops,
            'termini' => $termini,
            'departements' => array_values($deps),
            'departement_codes' => array_keys($deps),
            'regions' => $regs,
            'admin_source' => $depsL !== [] || $regsL !== [] ? 'P131' : ($deps !== [] ? 'gares' : null),
            'station_count' => count($this->stations[$qid] ?? []),
            'points' => $points,
            'osm_relation' => is_string($osm) ? $osm : null,
            'events' => $this->buildEvents($qid, $e, $state, $replaced),
        ];
    }

    /** @return array{0: array<string,string>, 1: list<string>} départements (code → nom) et régions */
    private function resolveAdmin(array $adm): array
    {
        $deps = [];
        $regs = [];
        foreach (array_unique($adm) as $a) {
            foreach ($this->admin[$a]['deps'] ?? [] as $code => $name) {
                $deps[(string) $code] = $name;
            }
            foreach ($this->admin[$a]['regs'] ?? [] as $r) {
                $regs[$r] = self::REGIONS[$r] ?? $r;
            }
        }
        ksort($deps, SORT_NATURAL);

        return [$deps, array_values($regs)];
    }

    private function category(array $types, ?string $state): string
    {
        foreach (self::CATEGORIES as $cat => $qids) {
            if (array_intersect($types, $qids) !== []) {
                return $cat;
            }
        }

        return in_array($state, self::STATE_PROJECT, true) ? 'projet' : 'ferroviaire';
    }

    /**
     * Dates → événements VFF. Ouvertures successives : la première est
     * l'ouverture, une suivante après une fermeture est une réouverture, une
     * suivante SANS fermeture intermédiaire est une ouverture par étapes dont
     * on ignore la portion (→ 'unresolved'). Une fermeture sans portion sur une
     * ligne que Wikidata dit encore en service est forcément partielle
     * (→ 'unresolved', ignorée par la carte mais visible dans la chronologie).
     */
    private function buildEvents(string $qid, array $e, ?string $state, ?string $replaced): array
    {
        $raw = [];
        $opens = $this->statements($e, 'P1619');
        $openPid = 'P1619';
        if ($opens === []) {
            $opens = $this->statements($e, 'P729');
            $openPid = 'P729';
        }
        foreach ($opens as $st) {
            if ($t = $this->time($this->dv($st['mainsnak']))) {
                $raw[] = ['kind' => 'open', 'pid' => $openPid, 'st' => $st, 't' => $t, 'label' => null];
            }
        }
        $closes = $this->statements($e, 'P3999');
        foreach ($closes as $st) {
            if ($t = $this->time($this->dv($st['mainsnak']))) {
                $raw[] = ['kind' => 'close', 'pid' => 'P3999', 'st' => $st, 't' => $t, 'label' => null];
            }
        }
        foreach ($this->statements($e, 'P576') as $st) {
            if ($t = $this->time($this->dv($st['mainsnak']))) {
                if ($replaced) {
                    $raw[] = ['kind' => 'replaced', 'pid' => 'P576', 'st' => $st, 't' => $t, 'label' => null];
                } elseif ($closes === []) {
                    $raw[] = ['kind' => 'close576', 'pid' => 'P576', 'st' => $st, 't' => $t, 'label' => null];
                }
            }
        }
        foreach ($this->statements($e, 'P930') as $st) {
            $type = $this->entityId($st['mainsnak']);
            if ($type && $type !== self::ELEC_NONE && ($t = $this->qualTime($st, 'P580'))) {
                $raw[] = ['kind' => 'elec', 'pid' => 'P930', 'st' => $st, 't' => $t, 'label' => $this->label($type)];
            }
        }
        foreach ($this->statements($e, 'P793') as $st) {
            $k = $this->entityId($st['mainsnak']);
            $t = $this->qualTime($st, 'P585') ?? $this->qualTime($st, 'P580');
            if ($k && $t) {
                [$type, $lbl] = self::KEY_EVENTS[$k] ?? ['other', Str::ucfirst((string) ($this->label($k) ?? $k))];
                $kind = match ($type) {
                    'opening' => 'open', 'closure' => 'close', default => 'key'
                };
                $raw[] = ['kind' => $kind, 'pid' => 'P793', 'st' => $st, 't' => $t, 'label' => $lbl, 'type' => $type];
            }
        }
        usort($raw, fn ($a, $b) => [$a['t']['date'], $a['kind'] === 'open' ? 0 : 1] <=> [$b['t']['date'], $b['kind'] === 'open' ? 0 : 1]);

        $active = in_array($state, self::STATE_ACTIVE, true);
        $events = [];
        $opened = false;
        $closed = false;
        foreach ($raw as $i => $r) {
            $st = $r['st'];
            $t = $r['t'];
            [$from, $to] = $this->section($st);
            $sectioned = $from !== null || $to !== null;
            $resolution = $sectioned ? 'pending' : 'whole_line';
            $confidence = $this->confidence($t, $st);
            $portion = $sectioned ? ' '.($from ?? '…').' - '.($to ?? '…') : '';
            switch ($r['kind']) {
                case 'open':
                    if (! $opened) {
                        $type = 'opening';
                        $desc = $sectioned ? 'Ouverture de la section'.$portion : 'Ouverture de la ligne';
                    } elseif ($closed) {
                        $type = 'reopening';
                        $desc = $sectioned ? 'Réouverture de la section'.$portion : 'Réouverture de la ligne';
                    } else {
                        $type = 'opening';
                        if ($sectioned) {
                            $desc = 'Ouverture de la section'.$portion;
                        } else {
                            $resolution = 'unresolved';
                            $confidence = 'low';
                            $desc = 'Ouverture d\'une autre section (ouverture par étapes, portion non précisée par Wikidata)';
                        }
                    }
                    if ($r['pid'] === 'P793') {
                        $desc .= ' ('.Str::lower((string) $r['label']).')';
                    }
                    $opened = true;
                    $closed = false;
                    break;
                case 'close':
                case 'close576':
                    $type = 'closure';
                    $laterOpen = (bool) array_filter(array_slice($raw, $i + 1), fn ($x) => $x['kind'] === 'open');
                    if ($sectioned) {
                        $desc = 'Fermeture de la section'.$portion;
                    } elseif ($active && ! $laterOpen) {
                        $resolution = 'unresolved';
                        $confidence = 'low';
                        $desc = 'Fermeture d\'une partie de la ligne (portion non précisée ; la ligne est encore en service selon Wikidata)';
                    } else {
                        $desc = 'Fermeture de la ligne (trafic non précisé par Wikidata)';
                        $closed = true;
                    }
                    if ($r['kind'] === 'close576') {
                        $confidence = 'low';
                        $desc .= ' - d\'après P576 « dissolution ou abolition »';
                    }
                    break;
                case 'replaced':
                    $type = 'other';
                    $confidence = 'low';
                    $desc = 'Fin de la ligne sous cette forme, remplacée par '.($this->label($replaced) ?? $replaced);
                    break;
                case 'elec':
                    $type = 'electrification';
                    $desc = 'Électrification'.($sectioned ? ' de la section'.$portion : ' de la ligne')
                        .' en '.preg_replace('/^électrification ferroviaire\s*/u', '', (string) $r['label']);
                    break;
                default:
                    $type = $r['type'];
                    $desc = (string) $r['label'];
                    if ($sectioned) {
                        $desc .= ' - section'.$portion;
                    }
            }
            if ($causes = $this->qualIds($st, 'P828')) {
                $desc .= ' - cause : '.implode(', ', array_map(fn ($c) => $this->label($c) ?? $c, $causes));
            }
            $events[] = [
                'type' => $type,
                'date' => $t['date'],
                'precision' => $t['precision'],
                'year' => $t['year'],
                'section_from' => $from !== null ? Str::limit($from, 250, '') : null,
                'section_to' => $to !== null ? Str::limit($to, 250, '') : null,
                'resolution' => $resolution,
                'confidence' => $confidence,
                'description' => $desc,
                'pid' => $r['pid'],
                'guid' => (string) ($st['id'] ?? ($qid.'$'.$r['pid'].'$'.$t['date'])),
                'quote' => $this->quote($qid, $r['pid'], $st, $t),
                'source_url' => 'https://www.wikidata.org/wiki/'.$qid.'#'.$r['pid'],
            ];
        }

        // Doublons internes (P1619 + P793 « mise en service » la même année) : on garde le plus précis.
        $rank = ['day' => 3, 'month' => 2, 'year' => 1];
        $kept = [];
        foreach ($events as $ev) {
            $k = $ev['type'].'|'.$ev['year'].'|'.$ev['section_from'].'|'.$ev['section_to'];
            if (! isset($kept[$k]) || $rank[$ev['precision']] > $rank[$kept[$k]['precision']]) {
                $kept[$k] = $ev;
            }
        }

        return array_values($kept);
    }

    /** Portion décrite par les qualificatifs : P1427 (départ), P1444 (arrivée), P559 (extrémités), P518 (partie). */
    private function section(array $st): array
    {
        $from = $this->qualIds($st, 'P1427')[0] ?? null;
        $to = $this->qualIds($st, 'P1444')[0] ?? null;
        $ends = $this->qualIds($st, 'P559');
        if ($from === null && $to === null && count($ends) === 1) {
            $to = $ends[0];
        } else {
            foreach ($ends as $x) {
                if ($from === null && $x !== $to) {
                    $from = $x;
                } elseif ($to === null && $x !== $from) {
                    $to = $x;
                }
            }
        }
        $fromL = $from ? ($this->label($from) ?? $from) : null;
        $toL = $to ? ($this->label($to) ?? $to) : null;
        if ($fromL === null && $toL === null && ($part = $this->qualIds($st, 'P518'))) {
            $fromL = $this->label($part[0]) ?? $part[0];
        }

        return [$fromL, $toL];
    }

    /** Année seule → low ; jour + référence externe (URL, « affirmé dans ») → high ; sinon medium. */
    private function confidence(array $t, array $st): string
    {
        if ($t['precision'] === 'year') {
            return 'low';
        }
        if ($t['precision'] === 'day') {
            foreach ($st['references'] ?? [] as $ref) {
                $snaks = $ref['snaks'] ?? [];
                if (isset($snaks['P143']) || isset($snaks['P4656']) || isset($snaks['P3452'])) {
                    continue; // simple import depuis un projet Wikimedia
                }
                if (isset($snaks['P854']) || isset($snaks['P248'])) {
                    return 'high';
                }
            }
        }

        return 'medium';
    }

    private function quote(string $qid, string $pid, array $st, array $t): string
    {
        $main = $this->dv($st['mainsnak']);
        $value = is_array($main) && isset($main['id']) ? ($this->label($main['id']) ?? $main['id'])
            : (is_array($main) && isset($main['time']) ? ltrim(substr($main['time'], 0, 11), '+') : (string) json_encode($main));
        $q = "Wikidata {$qid} · {$pid} (".(self::PROP_LABELS[$pid] ?? $pid).") = {$value} - date retenue {$t['date']} (précision : ".self::PRECISION_FR[$t['precision']].')';
        $quals = [];
        foreach ($st['qualifiers'] ?? [] as $qp => $snaks) {
            $vals = [];
            foreach ($snaks as $s) {
                $v = $this->dv($s);
                if (is_array($v) && isset($v['id'])) {
                    $vals[] = $this->label($v['id']) ?? $v['id'];
                } elseif (is_array($v) && isset($v['time'])) {
                    $vals[] = ltrim(substr($v['time'], 0, 11), '+');
                } elseif (is_string($v)) {
                    $vals[] = $v;
                }
            }
            if ($vals !== []) {
                $quals[] = $qp.' = '.implode(', ', $vals);
            }
        }
        if ($quals !== []) {
            $q .= ' ; qualificatifs : '.implode(' ; ', $quals);
        }
        $refs = [];
        foreach ($st['references'] ?? [] as $ref) {
            foreach ($ref['snaks']['P854'] ?? [] as $s) {
                if (is_string($u = $this->dv($s))) {
                    $refs[] = $u;
                }
            }
            foreach ($ref['snaks']['P248'] ?? [] as $s) {
                if ($id = $this->entityId($s)) {
                    $refs[] = $this->label($id) ?? $id;
                }
            }
            foreach ($ref['snaks']['P143'] ?? [] as $s) {
                if ($id = $this->entityId($s)) {
                    $refs[] = 'importé de '.($this->label($id) ?? $id);
                }
            }
        }
        if ($refs !== []) {
            $q .= ' ; références : '.implode(' ; ', array_unique($refs));
        }

        return $q.' (Wikidata, CC0)';
    }

    // ───────────────────────────── 3. Rattachement ─────────────────────────────

    /**
     * @return array<string, array{line_id: ?int, method: string, score: ?float, note: ?string, parts: list<int>, best: ?array}>
     */
    private function match(array $items): array
    {
        $lines = [];
        foreach (DB::select('SELECT id, code, name, alt_names::text AS alt FROM lines') as $l) {
            $alt = json_decode((string) $l->alt, true);
            $names = [$l->name];
            if (is_array($alt) && is_string($alt['lignes_oubliees']['name'] ?? null)) {
                $names[] = $alt['lignes_oubliees']['name'];
            }
            $lines[(int) $l->id] = ['id' => (int) $l->id, 'code' => $l->code, 'name' => $l->name,
                'keys' => array_map(fn ($n) => $this->nameKey($n), array_unique($names))];
            // QID posé à la création de la ligne (cf. étape 0) : la première ligne qui le déclare.
            if (is_array($alt) && is_string($alt['wikidata_qid'] ?? null) && ! isset($declared[$alt['wikidata_qid']])) {
                $declared[$alt['wikidata_qid']] = (int) $l->id;
            }
        }
        $declared ??= [];
        $byCode = [];
        foreach ($lines as $l) {
            if ($l['code'] !== null) {
                $byCode[preg_replace('/\s+/', '', $l['code'])] = $l['id'];
            }
        }
        $itemKeys = [];
        foreach ($items as $qid => $f) {
            $itemKeys[$qid] = array_map(fn ($n) => $this->nameKey($n), $f['names']);
        }

        $links = [];
        foreach ($items as $qid => $f) {
            $links[$qid] = ['line_id' => null, 'method' => 'none', 'score' => null, 'note' => null, 'parts' => [], 'best' => null];
        }

        // 0. QID DÉCLARÉ à la création de la ligne (alt_names.wikidata_qid) : ligne créée depuis un
        //    tracé « lignes exploitées » d'après l'item qui porte son code (vff:link-traces), ligne
        //    hors RFN déclarée avec ses sources (vff:seed-off-network-lines). Lien direct, avant le
        //    code : la ligne n'est plus candidate pour un autre item (doublon).
        $declaredLines = [];
        foreach ($declared as $qid => $lid) {
            if (isset($links[$qid])) {
                $links[$qid] = ['line_id' => $lid, 'method' => 'declared', 'score' => 1.0,
                    'note' => 'QID déclaré sur la ligne (alt_names.wikidata_qid)', 'parts' => [], 'best' => null];
                $declaredLines[$lid] = $qid;
            }
        }

        // a. Code RFN.
        $cands = [];
        foreach ($items as $qid => $f) {
            if ($links[$qid]['method'] === 'declared') {
                continue;
            }
            $six = [];
            foreach ($f['codes'] as $c) {
                if ($c['code'] !== null) {
                    $six[$c['code']] = ($six[$c['code']] ?? false) || $c['part'] !== [];
                }
            }
            foreach ($six as $code => $isPart) {
                if (! isset($byCode[$code])) {
                    continue;
                }
                $lid = $byCode[$code];
                if (isset($declaredLines[$lid])) {
                    // Ligne liée à son QID déclaré : un autre item portant son code en est un doublon.
                    if ($links[$qid]['method'] === 'none') {
                        $links[$qid]['method'] = 'duplicate';
                        $links[$qid]['note'] = "code {$code} : ligne liée à son QID déclaré {$declaredLines[$lid]}";
                    }

                    continue;
                }
                $cands[$lid][] = ['qid' => $qid, 'code' => (string) $code, 'method' => count($six) > 1 || $isPart ? 'code_part' : 'code',
                    'name' => $this->bestNameScore($itemKeys[$qid], $lines[$lid]['keys']), 'rail' => $f['category'] === 'ferroviaire'];
            }
        }
        $taken = $declaredLines;
        foreach ($cands as $lid => $list) {
            usort($list, fn ($a, $b) => [$b['method'] === 'code', $b['rail'], $b['name']] <=> [$a['method'] === 'code', $a['rail'], $a['name']]);
            $win = $list[0];
            $taken[$lid] = $win['qid'];
            $l = &$links[$win['qid']];
            if ($win['method'] === 'code') {
                $l = ['line_id' => $lid, 'method' => 'code', 'score' => 1.0, 'note' => null, 'parts' => [], 'best' => null];
            } else {
                if ($l['method'] !== 'code_part') {
                    $l = ['line_id' => $lid, 'method' => 'code_part', 'score' => 0.8, 'note' => 'item à plusieurs codes ou code de portion (P518)', 'parts' => [], 'best' => null];
                }
                $l['parts'][] = $lid;
            }
            unset($l);
            foreach (array_slice($list, 1) as $c) {
                $o = &$links[$c['qid']];
                if ($c['method'] === 'code' && $o['method'] === 'none') {
                    $o['method'] = 'duplicate';
                    $o['note'] = "code {$c['code']} déjà rattaché à {$win['qid']} ({$lines[$lid]['name']})";
                    $o['best'] = ['line_id' => $lid, 'name' => $lines[$lid]['name'], 'score' => $c['name']];
                } elseif ($c['method'] === 'code_part' && $o['method'] === 'none') {
                    $o['method'] = 'duplicate';
                    $o['note'] = "portion de code {$c['code']} : ligne déjà rattachée à {$win['qid']}";
                    $o['best'] = ['line_id' => $lid, 'name' => $lines[$lid]['name'], 'score' => $c['name']];
                }
                unset($o);
            }
        }
        $this->stats['code_items'] = count(array_filter($items, fn ($f) => array_filter($f['codes'], fn ($c) => $c['code'] !== null)));

        // b. Nom + position, pour les items restants (lignes ferroviaires, projets).
        //    Les lignes déjà prises par un code servent à repérer les doublons
        //    (item sans code décrivant une ligne déjà rattachée) : pas de lien,
        //    mais l'item n'est pas compté comme « absent de VFF ».
        $pending = array_filter($items, fn ($f, $q) => $links[$q]['method'] === 'none' && in_array($f['category'], ['ferroviaire', 'projet'], true), ARRAY_FILTER_USE_BOTH);
        [$geo, $hasGeom] = $this->geoProximity($pending);

        $accepted = [];   // qid → [lid, method, score]
        foreach ($pending as $qid => $f) {
            $hasCode = (bool) array_filter($f['codes'], fn ($c) => $c['code'] !== null);
            $n = count($f['points']);
            $termIdx = array_keys(array_filter($f['points'], fn ($p) => $p[0] === 'terminus'));
            $ok = [];
            $best = null;
            foreach ($lines as $lid => $l) {
                if ($hasCode && $l['code'] !== null) {
                    continue; // jamais un code Wikidata sur une ligne VFF portant un autre code
                }
                $s = $this->bestNameScore($itemKeys[$qid], $l['keys']);
                if ($s < 0.6) {
                    continue;
                }
                $geoOk = null;
                $frac = null;
                if ($n > 0 && isset($hasGeom[$lid])) {
                    $d = $geo[$qid][$lid] ?? [];
                    $near = count(array_filter($d, fn ($m) => $m <= 2000));
                    $frac = $near / $n;
                    $termOk = true;
                    foreach ($termIdx as $i) {
                        if (($d[$i] ?? INF) > 3000) {
                            $termOk = false;
                        }
                    }
                    // Nom identique : 60 % des points sur le tracé suffisent (un terminus peut être
                    // hors du tracé VFF, ex. ligne transfrontalière) ; sinon terminus à < 3 km exigés.
                    $geoOk = $frac >= 0.6 && ($termOk || $s >= 0.99);
                }
                if ($best === null || $s > $best['score']) {
                    $best = ['line_id' => $lid, 'name' => $l['name'], 'score' => round($s, 2), 'geo' => $frac === null ? null : round($frac, 2)];
                }
                if ($geoOk === true) {
                    $ok[] = [$lid, 'name_geo', round(0.5 * $s + 0.5 * $frac, 3)];
                } elseif ($geoOk === null && $s >= 0.99) {
                    $ok[] = [$lid, 'name', 0.6];
                }
            }
            $links[$qid]['best'] = $best;
            if ($ok === []) {
                if ($best !== null && $best['score'] >= 0.99 && $best['geo'] !== null) {
                    $links[$qid]['note'] = 'homonyme rejeté : terminus / gares loin du tracé de « '.$best['name'].' »';
                }

                continue;
            }
            usort($ok, fn ($a, $b) => $b[2] <=> $a[2]);
            if (count($ok) > 1 && $ok[0][2] - $ok[1][2] < 0.15) {
                $links[$qid]['method'] = 'ambiguous';
                $links[$qid]['note'] = 'candidats trop proches : '.implode(', ', array_map(fn ($c) => $lines[$c[0]]['name'].' ('.$c[2].')', array_slice($ok, 0, 3)));

                continue;
            }
            if (isset($taken[$ok[0][0]])) {
                $lid = $ok[0][0];
                $links[$qid]['method'] = 'duplicate';
                $links[$qid]['note'] = 'décrit « '.$lines[$lid]['name'].' » (nom + position), déjà rattachée à '.$taken[$lid];
                $links[$qid]['best'] = ['line_id' => $lid, 'name' => $lines[$lid]['name'], 'score' => $ok[0][2], 'geo' => null];

                continue;
            }
            $accepted[$qid] = $ok[0];
        }
        // c. Gares + position, pour les items restants dont le NOM ne donne pas d'extrémités
        //    (« ligne de la côte orientale corse », « ligne de la Rive droite ») : la comparaison
        //    « X à Y » de l'étape b ne peut pas les rapprocher. Au moins STATIONS_MIN_POINTS points
        //    (gares P81, terminus), dont STATIONS_MIN_SHARE à moins de STATIONS_MAX_M du tracé
        //    d'UNE ligne VFF, la suivante en couvrant moins de STATIONS_RIVAL_SHARE (sinon rien) ;
        //    jamais une ligne VFF portant un autre code que l'item. Ex. Q3240071 (7 gares de
        //    Casamozza à Porto-Vecchio) → « Ligne de Casamozza à Porto-Vecchio ».
        foreach ($pending as $qid => $f) {
            if (isset($accepted[$qid]) || $links[$qid]['method'] !== 'none' || $f['category'] !== 'ferroviaire'
                || count($f['points']) < self::STATIONS_MIN_POINTS
                || array_filter($itemKeys[$qid], fn ($k) => $k['ends'] !== null)) {
                continue;
            }
            $codes = array_filter(array_column($f['codes'], 'code'));
            $cover = [];
            foreach ($geo[$qid] ?? [] as $lid => $d) {
                if (! isset($lines[$lid]) || ($lines[$lid]['code'] !== null && ! in_array($lines[$lid]['code'], $codes, true) && $codes !== [])) {
                    continue;
                }
                $cover[$lid] = count(array_filter($d, fn ($m) => $m <= self::STATIONS_MAX_M)) / count($f['points']);
            }
            arsort($cover);
            $shares = array_values($cover);
            $lid = array_key_first($cover);
            if ($lid === null || $shares[0] < self::STATIONS_MIN_SHARE || ($shares[1] ?? 0) >= self::STATIONS_RIVAL_SHARE) {
                continue;
            }
            if (isset($taken[$lid])) {
                $links[$qid]['method'] = 'duplicate';
                $links[$qid]['note'] = 'gares le long de « '.$lines[$lid]['name'].' », déjà rattachée à '.$taken[$lid];

                continue;
            }
            $accepted[$qid] = [$lid, 'stations_geo', round(0.8 * $shares[0], 3)];
        }
        // Une ligne VFF ne reçoit qu'un item : le meilleur score, s'il se détache.
        $byLine = [];
        foreach ($accepted as $qid => $a) {
            $byLine[$a[0]][] = [$qid, $a];
        }
        foreach ($byLine as $lid => $list) {
            usort($list, fn ($x, $y) => $y[1][2] <=> $x[1][2]);
            if (count($list) > 1 && $list[0][1][2] - $list[1][1][2] < 0.15) {
                foreach ($list as [$qid]) {
                    $links[$qid]['method'] = 'ambiguous';
                    $links[$qid]['note'] = 'plusieurs items Wikidata pour « '.$lines[$lid]['name'].' »';
                }

                continue;
            }
            [$qid, $a] = $list[0];
            $links[$qid] = ['line_id' => $lid, 'method' => $a[1], 'score' => $a[2], 'note' => null, 'parts' => [], 'best' => $links[$qid]['best']];
            foreach (array_slice($list, 1) as [$other]) {
                $links[$other]['method'] = 'duplicate';
                $links[$other]['note'] = 'ligne « '.$lines[$lid]['name'].' » attribuée à '.$qid;
            }
        }

        $counts = array_count_values(array_column($links, 'method'));
        ksort($counts);
        $this->stats['methods'] = $counts;
        foreach ($counts as $m => $c) {
            $this->line(sprintf('  %-10s %5d', $m, $c));
        }

        return $links;
    }

    /**
     * Distances (m) entre chaque point Wikidata et le tracé VFF des lignes à
     * moins de ~4 km. @return array{0: array<string, array<int, array<int, float>>>, 1: array<int, true>}
     */
    private function geoProximity(array $items): array
    {
        $rows = [];
        foreach ($items as $qid => $f) {
            foreach ($f['points'] as $i => [$kind, $lon, $lat]) {
                $rows[] = [$qid, $i, $lon, $lat];
            }
        }
        DB::statement('DROP TABLE IF EXISTS wd_pts');
        DB::statement('CREATE TEMP TABLE wd_pts (qid text, idx int, geom geometry(Point, 4326))');
        foreach (array_chunk($rows, 1000) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '(?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326))'));
            DB::insert("INSERT INTO wd_pts (qid, idx, geom) VALUES {$ph}", array_merge(...$chunk));
        }
        DB::statement('CREATE INDEX ON wd_pts USING GIST (geom)');
        DB::statement('DROP TABLE IF EXISTS vff_lg');
        DB::statement('CREATE TEMP TABLE vff_lg AS
            SELECT line_id, geom::geometry AS geom FROM line_segments
            UNION ALL SELECT line_id, geom::geometry FROM line_geometries
            UNION ALL SELECT line_id, geom::geometry FROM source_traces WHERE line_id IS NOT NULL
            UNION ALL SELECT l.id, s.geom::geometry FROM sncf_line_geometries s JOIN lines l ON l.code = s.code_ligne');
        DB::statement('CREATE INDEX ON vff_lg USING GIST (geom)');
        $hasGeom = array_fill_keys(array_map('intval', DB::table('vff_lg')->distinct()->pluck('line_id')->all()), true);

        $geo = [];
        foreach (DB::select('SELECT p.qid, p.idx, g.line_id, MIN(ST_Distance(p.geom::geography, g.geom::geography)) AS d
                FROM wd_pts p JOIN vff_lg g ON ST_DWithin(p.geom, g.geom, 0.045)
                GROUP BY p.qid, p.idx, g.line_id') as $r) {
            $geo[$r->qid][(int) $r->line_id][(int) $r->idx] = (float) $r->d;
        }
        DB::statement('DROP TABLE IF EXISTS wd_pts');
        DB::statement('DROP TABLE IF EXISTS vff_lg');
        $this->line('  '.count($rows).' point(s) Wikidata, '.count($hasGeom).' ligne(s) VFF avec tracé');

        return [$geo, $hasGeom];
    }

    /** « Ligne de Paris-Bastille à Marles-en-Brie » → {full, ends: [paris bastille, marles brie]}. */
    private function nameKey(string $name): array
    {
        $s = mb_strtolower(Str::ascii(str_replace(['’', '`', '–', '—'], ["'", "'", '-', '-'], $name)));
        $s = preg_replace('/\([^)]*\)/', ' ', $s);
        $s = preg_replace("/^\s*(?:ancienne?\s+)?(?:ligne a grande vitesse|ligne nouvelle|ligne|chemin de fer|voie ferree|tramway|raccordement|embranchement|antenne|branche|lgv)\b\s*(?:de la\s+|de l'\s*|des\s+|du\s+|de\s+|d'\s*)?/", '', $s);
        $s = preg_replace('/\s+(?:par|via)\s+.*$/', '', $s);
        $parts = preg_split('/\s+(?:a|au|aux)\s+/', $s);
        if (count($parts) < 2) {
            $parts = preg_split('/\s+-\s+/', $s);
        }
        $ends = array_values(array_filter(array_map(fn ($p) => $this->normPlace($p), $parts)));

        return ['full' => $ends !== [] ? implode(' ', $ends) : $this->normPlace($s), 'ends' => count($ends) === 2 ? $ends : null];
    }

    private function normPlace(string $p): string
    {
        $p = preg_replace('/[^a-z0-9]+/', ' ', $p);
        $p = preg_replace('/\bsaint\b/', 'st', $p);
        $p = preg_replace('/\bsainte\b/', 'ste', $p);
        $p = preg_replace('/\b(?:gare|ville|halte|le|la|les|l|d|de|du|des|sur|en|et|sous)\b/', ' ', $p);

        return trim(preg_replace('/\s+/', ' ', $p));
    }

    private function bestNameScore(array $keysA, array $keysB): float
    {
        $best = 0.0;
        foreach ($keysA as $a) {
            foreach ($keysB as $b) {
                $best = max($best, $this->nameScore($a, $b));
            }
        }

        return $best;
    }

    private function nameScore(array $a, array $b): float
    {
        if ($a['full'] !== '' && $a['full'] === $b['full']) {
            return 1.0;
        }
        if ($a['ends'] && $b['ends']) {
            [$a1, $a2] = $a['ends'];
            [$b1, $b2] = $b['ends'];

            return max($this->placeSim($a1, $b1) + $this->placeSim($a2, $b2), $this->placeSim($a1, $b2) + $this->placeSim($a2, $b1)) / 2;
        }

        return 0.7 * $this->jaccard($a['full'], $b['full']);
    }

    private function placeSim(string $x, string $y): float
    {
        if ($x === $y) {
            return 1.0;
        }
        $tx = array_unique(explode(' ', $x));
        $ty = array_unique(explode(' ', $y));
        $inter = count(array_intersect($tx, $ty));
        if ($inter > 0 && ($inter === count($tx) || $inter === count($ty))) {
            return 0.85; // l'un contient l'autre : « Paris » / « Paris Bastille »
        }

        return 0.7 * $this->jaccard($x, $y);
    }

    private function jaccard(string $x, string $y): float
    {
        $tx = array_unique(array_filter(explode(' ', $x)));
        $ty = array_unique(array_filter(explode(' ', $y)));
        $union = count(array_unique(array_merge($tx, $ty)));

        return $union === 0 ? 0.0 : count(array_intersect($tx, $ty)) / $union;
    }

    // ───────────────────────────── 4. Écriture ─────────────────────────────

    private function writeStaging(array $items, array $links): void
    {
        $now = now();
        $rows = [];
        $wkt = [];
        foreach ($items as $qid => $f) {
            $l = $links[$qid];
            $facts = $f;
            unset($facts['termini']);
            $facts['best_candidate'] = $l['best'];
            $facts['part_lines'] = $l['parts'];
            $rows[] = [
                'qid' => $qid,
                'label' => $f['label'] !== null ? Str::limit($f['label'], 250, '') : null,
                'frwiki_title' => $f['frwiki'],
                'category' => $f['category'],
                'kinds' => json_encode($f['kinds']),
                'codes' => json_encode($f['codes'], JSON_UNESCAPED_UNICODE),
                'line_id' => $l['line_id'],
                'match_method' => $l['method'],
                'match_score' => $l['score'],
                'match_note' => $l['note'],
                'facts' => json_encode($facts, JSON_UNESCAPED_UNICODE),
                'termini' => json_encode($f['termini'], JSON_UNESCAPED_UNICODE),
                'station_count' => $f['station_count'],
                'fetched_at' => $this->fetchedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($f['points'] !== []) {
                $wkt[] = [$qid, 'MULTIPOINT('.implode(',', array_map(fn ($p) => sprintf('(%.7F %.7F)', $p[1], $p[2]), $f['points'])).')'];
            }
        }
        DB::transaction(function () use ($rows, $wkt, $items) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('wikidata_lines')->upsert($chunk, ['qid'], ['label', 'frwiki_title', 'category', 'kinds', 'codes', 'line_id',
                    'match_method', 'match_score', 'match_note', 'facts', 'termini', 'station_count', 'fetched_at', 'updated_at']);
            }
            DB::table('wikidata_lines')->whereIn('qid', array_keys($items))->update(['points' => null]);
            foreach (array_chunk($wkt, 300) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '(?, ?)'));
                DB::update("UPDATE wikidata_lines w SET points = ST_GeomFromText(v.wkt, 4326)
                    FROM (VALUES {$ph}) AS v(qid, wkt) WHERE w.qid = v.qid", array_merge(...$chunk));
            }
            if (! $this->option('qid')) {
                DB::table('wikidata_lines')->whereNotIn('qid', array_keys($items))->delete();
            }
        });
    }

    /**
     * Remplit les champs vides des lignes rattachées, écrit événements et
     * line_sources. @return list<string> fingerprints produits
     */
    private function apply(array $items, array $links, array $prevWritten): array
    {
        $now = now();
        $fp = [];
        $written = [];
        $mine = [];
        $evCount = [];
        $linked = array_filter($links, fn ($l, $q) => isset($items[$q]) && $l['line_id'] !== null, ARRAY_FILTER_USE_BOTH);
        $lineIds = [];
        foreach ($linked as $l) {
            foreach (array_merge([$l['line_id']], $l['parts']) as $lid) {
                $lineIds[$lid] = true;
            }
        }
        $current = DB::table('lines')->whereIn('id', array_keys($lineIds))
            ->get(['id', 'alt_names', 'length_official_m', 'gauge_mm_default', 'nb_voies_default', 'max_speed_kmh_official',
                'historical_operators', 'regions', 'departements', 'wikipedia_title', 'electrified', 'voltage_v'])->keyBy('id');
        $withSegments = array_fill_keys(DB::table('line_segments')->whereIn('line_id', array_keys($lineIds))->distinct()->pluck('line_id')->all(), true);

        DB::transaction(function () use ($items, $linked, $current, $withSegments, $prevWritten, $now, &$fp, &$written, &$evCount, &$mine) {
            foreach ($linked as $qid => $l) {
                $f = $items[$qid];
                $full = in_array($l['method'], self::FULL_METHODS, true);
                $targets = $l['method'] === 'code_part' ? $l['parts'] : [$l['line_id']];
                foreach ($targets as $lid) {
                    $line = $current[$lid] ?? null;
                    if (! $line) {
                        continue;
                    }
                    $fields = [];
                    $update = [];
                    // QID (ou QID de l'item parent pour une portion).
                    $key = $full ? 'wikidata' : 'wikidata_parent';
                    $alt = json_decode((string) $line->alt_names, true);
                    if ($line->alt_names === null || str_starts_with(ltrim((string) $line->alt_names), '{')) {
                        if (! isset($alt[$key])) {
                            DB::update("UPDATE lines SET alt_names = COALESCE(alt_names, '{}'::jsonb) || jsonb_build_object(?::text, ?::text) WHERE id = ?", [$key, $qid, $lid]);
                            $written[$key] = ($written[$key] ?? 0) + 1;
                        }
                        $fields[] = 'alt_names.'.$key;
                    }
                    // Champ vide → on remplit ; champ qu'une passe précédente de CETTE
                    // commande a rempli et que personne n'a modifié depuis → on le
                    // rafraîchit ; toute autre valeur (autre source) est intouchable.
                    $prevW = $prevWritten[$qid][$lid] ?? [];
                    $candidates = $this->fieldValues($f, $full);
                    $es = $f['electrification_summary'];
                    if ($full && $es !== null && ! $es['partial']) {
                        $fields[] = 'electrified';
                    }
                    if (isset($withSegments[$lid])) {
                        unset($candidates['electrified'], $candidates['voltage_v']); // agrégat LineClassifier
                    }
                    foreach ($candidates as $col => $val) {
                        if ($val === null) {
                            continue;
                        }
                        if ($col !== 'electrified' && $col !== 'voltage_v') {
                            $fields[] = $col;
                        }
                        $cur = $line->{$col};
                        $empty = $cur === null
                            || ($col === 'electrified' && ! $cur && $line->voltage_v === null)
                            || (in_array($col, self::JSON_FIELDS, true) && in_array(trim((string) $cur), ['[]', 'null', ''], true));
                        $ours = array_key_exists($col, $prevW) && $this->sameValue($col, $cur, $prevW[$col])
                            && ! (in_array($col, self::LO_FIELDS, true) && ($alt['name_source'] ?? null) === 'lignes-oubliees');
                        if ($empty || ($ours && ! $this->sameValue($col, $cur, $val))) {
                            $update[$col] = $val;
                        }
                        if ($empty || $ours) {
                            $mine[$qid][$lid][$col] = $val;
                        }
                    }
                    if ($update !== []) {
                        $update['encyclopedia_updated_at'] = $now;
                        $update['updated_at'] = $now;
                        DB::table('lines')->where('id', $lid)->update($update);
                        foreach (array_keys($update) as $c) {
                            if (! in_array($c, ['encyclopedia_updated_at', 'updated_at'], true)) {
                                $written[$c] = ($written[$c] ?? 0) + 1;
                            }
                        }
                    }

                    // Événements datés (rattachement complet uniquement).
                    $evRows = [];
                    if ($full) {
                        foreach ($f['events'] as $ev) {
                            $print = hash('sha256', implode('|', [self::SOURCE, $lid, $ev['guid'], $ev['type'], $ev['section_from'], $ev['section_to']]));
                            $fp[] = $print;
                            $evRows[] = [
                                'line_id' => $lid,
                                'type' => $ev['type'],
                                'event_date' => $ev['date'],
                                'date_precision' => $ev['precision'],
                                'description' => $ev['description'],
                                'source' => self::SOURCE,
                                'source_url' => $ev['source_url'],
                                'source_quote' => $ev['quote'],
                                'confidence' => $l['method'] === 'name' ? 'low' : $ev['confidence'],
                                'section_from' => $ev['section_from'],
                                'section_to' => $ev['section_to'],
                                'section_resolution' => $ev['resolution'],
                                'fingerprint' => $print,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                            $evCount[$ev['type']] = ($evCount[$ev['type']] ?? 0) + 1;
                        }
                        if ($evRows !== []) {
                            $fields[] = 'line_events';
                            // section_* non mis à jour : le résolveur a pu les compléter.
                            DB::table('line_events')->upsert($evRows, ['fingerprint'], ['type', 'event_date', 'date_precision',
                                'description', 'source_url', 'source_quote', 'confidence', 'updated_at']);
                        }
                    }

                    DB::table('line_sources')->updateOrInsert(
                        ['line_id' => $lid, 'source_type' => self::SOURCE],
                        [
                            'source_url' => 'https://www.wikidata.org/wiki/'.$qid,
                            'fetched_at' => $this->fetchedAt,
                            'fields_provided' => json_encode(array_values(array_unique($fields))),
                            'confidence' => match ($l['method']) {
                                'code' => 'high', 'name_geo', 'code_part' => 'medium', default => 'low'
                            },
                            'updated_at' => $now,
                            'created_at' => $now,
                        ],
                    );
                }
            }
        });

        // Trace de ce que Wikidata a écrit (pour rafraîchir sans écraser les autres sources).
        DB::transaction(function () use ($items, $mine) {
            foreach (array_keys($items) as $qid) {
                DB::update("UPDATE wikidata_lines SET facts = jsonb_set(COALESCE(facts, '{}'::jsonb), '{written}', ?::jsonb) WHERE qid = ?",
                    [json_encode((object) ($mine[$qid] ?? []), JSON_UNESCAPED_UNICODE), $qid]);
            }
        });
        ksort($written);
        ksort($evCount);
        $this->stats['fields_written'] = $written;
        $this->stats['events'] = $evCount;
        $this->stats['events_total'] = array_sum($evCount);
        $this->info('→ Champs remplis : '.json_encode($written, JSON_UNESCAPED_UNICODE));
        $this->info('→ Événements écrits : '.array_sum($evCount).' '.json_encode($evCount));

        return $fp;
    }

    /** Supprime ce que Wikidata ne produit plus (événements, sources, QID d'un ancien rattachement). */
    private function cleanup(array $produced, array $links, array $prevLinks): void
    {
        $deleted = 0;
        DB::transaction(function () use ($produced, $links, $prevLinks, &$deleted) {
            // ON COMMIT DROP ne joue qu'au COMMIT final : dans une transaction englobante
            // (pipeline, tests), un second passage retrouverait la table.
            DB::statement('DROP TABLE IF EXISTS wd_fp');
            DB::statement('CREATE TEMP TABLE wd_fp (fp varchar(64) PRIMARY KEY) ON COMMIT DROP');
            foreach (array_chunk(array_values(array_unique($produced)), 1000) as $chunk) {
                DB::insert('INSERT INTO wd_fp (fp) VALUES '.implode(',', array_fill(0, count($chunk), '(?)')), $chunk);
            }
            $deleted = DB::delete('DELETE FROM line_events e WHERE e.source = ? AND NOT EXISTS (SELECT 1 FROM wd_fp WHERE fp = e.fingerprint)', [self::SOURCE]);
            $lineIds = [];
            foreach ($links as $l) {
                if ($l['line_id'] !== null) {
                    $lineIds[] = $l['line_id'];
                    array_push($lineIds, ...$l['parts']);
                }
            }
            DB::table('line_sources')->where('source_type', self::SOURCE)->whereNotIn('line_id', $lineIds ?: [0])->delete();
            foreach ($prevLinks as $qid => $lid) {
                if (($links[$qid]['line_id'] ?? null) !== (int) $lid) {
                    DB::update("UPDATE lines SET alt_names = alt_names - 'wikidata' WHERE id = ? AND jsonb_typeof(alt_names) = 'object' AND alt_names->>'wikidata' = ?", [$lid, $qid]);
                }
            }
        });
        $this->line("  nettoyage : {$deleted} événement(s) wikidata obsolète(s) supprimé(s)");
    }

    // ───────────────────────────── 5. Rapports ─────────────────────────────

    private function stagingRows(?array $only = null): array
    {
        $q = DB::table('wikidata_lines')->orderBy('qid');
        if ($only !== null) {
            $q->whereIn('qid', $only);
        }

        return $q->get(['qid', 'label', 'category', 'codes', 'line_id', 'match_method', 'match_score', 'match_note', 'facts', 'termini', 'station_count'])
            ->map(fn ($r) => [
                'qid' => $r->qid, 'label' => $r->label, 'category' => $r->category, 'codes' => json_decode((string) $r->codes, true) ?? [],
                'line_id' => $r->line_id !== null ? (int) $r->line_id : null, 'method' => $r->match_method, 'score' => $r->match_score,
                'note' => $r->match_note, 'facts' => json_decode((string) $r->facts, true) ?? [], 'termini' => json_decode((string) $r->termini, true) ?? [],
                'station_count' => (int) $r->station_count,
            ])->all();
    }

    private function memoryRows(array $items, array $links): array
    {
        $rows = [];
        foreach ($items as $qid => $f) {
            $l = $links[$qid];
            $facts = $f;
            $facts['best_candidate'] = $l['best'];
            $facts['part_lines'] = $l['parts'];
            $rows[] = ['qid' => $qid, 'label' => $f['label'], 'category' => $f['category'], 'codes' => $f['codes'], 'line_id' => $l['line_id'],
                'method' => $l['method'], 'score' => $l['score'], 'note' => $l['note'], 'facts' => $facts, 'termini' => $f['termini'],
                'station_count' => $f['station_count']];
        }

        return $rows;
    }

    /** Conflits (champs + dates) et lignes absentes ; CSV + JSON si $write. */
    private function reports(array $rows, bool $saveConflicts, bool $writeFiles, string $suffix): void
    {
        $linked = array_filter($rows, fn ($r) => $r['line_id'] !== null);
        $lineIds = [];
        foreach ($linked as $r) {
            $lineIds[$r['line_id']] = true;
            foreach ($r['facts']['part_lines'] ?? [] as $p) {
                $lineIds[(int) $p] = true;
            }
        }
        $ids = array_keys($lineIds) ?: [0];
        $lines = DB::table('lines')->whereIn('id', $ids)->get(['id', 'code', 'name', 'alt_names', 'length_official_m', 'gauge_mm_default',
            'nb_voies_default', 'max_speed_kmh_official', 'wikipedia_title', 'electrified', 'voltage_v'])->keyBy('id');
        // Électrification des segments : comparée seulement si la donnée existe (sinon tout est « non électrifié »).
        $withSegments = DB::table('line_segments')->where('electrified', true)->exists()
            ? array_fill_keys(DB::table('line_segments')->whereIn('line_id', $ids)->distinct()->pluck('line_id')->all(), true)
            : [];

        // Dates des autres sources : line_events, archeo_lines, source_traces (hors Wikidata).
        $others = [];
        foreach (DB::table('line_events')->whereIn('line_id', $ids)->where(fn ($q) => $q->whereNull('source')->orWhere('source', '<>', self::SOURCE))
            ->get(['line_id', 'type', 'event_date', 'date_precision', 'source', 'section_from', 'section_to']) as $e) {
            foreach (self::FAMILIES as $fam => $types) {
                if (in_array($e->type, $types, true)) {
                    $others[$e->line_id][$fam][$e->source ?? 'inconnue'][] = ['date' => $e->event_date, 'year' => (int) substr($e->event_date, 0, 4),
                        'precision' => $e->date_precision, 'type' => $e->type, 'section' => trim(($e->section_from ?? '').' - '.($e->section_to ?? ''), ' -')];
                }
            }
        }
        foreach (DB::table('archeo_lines')->whereIn('matched_line_id', $ids)->get(['matched_line_id', 'opening_date', 'closure_date']) as $a) {
            if ($a->opening_date) {
                $others[$a->matched_line_id]['ouverture']['archeoferroviaire'][] = ['date' => $a->opening_date, 'year' => (int) substr($a->opening_date, 0, 4), 'precision' => 'day', 'type' => 'opening', 'section' => ''];
            }
            if ($a->closure_date) {
                $others[$a->matched_line_id]['fermeture']['archeoferroviaire'][] = ['date' => $a->closure_date, 'year' => (int) substr($a->closure_date, 0, 4), 'precision' => 'day', 'type' => 'closure', 'section' => ''];
            }
        }
        foreach (DB::table('source_traces')->whereIn('line_id', $ids)->where(fn ($q) => $q->whereNull('dates_source')->orWhere('dates_source', '<>', self::SOURCE))
            ->where(fn ($q) => $q->whereNotNull('opened_year')->orWhereNotNull('closed_year'))->get(['line_id', 'source', 'opened_year', 'closed_year']) as $t) {
            if ($t->opened_year) {
                $others[$t->line_id]['ouverture'][$t->source.' (tracé)'][] = ['date' => $t->opened_year.'-01-01', 'year' => (int) $t->opened_year, 'precision' => 'year', 'type' => 'opening', 'section' => 'tracé'];
            }
            if ($t->closed_year) {
                $others[$t->line_id]['fermeture'][$t->source.' (tracé)'][] = ['date' => $t->closed_year.'-01-01', 'year' => (int) $t->closed_year, 'precision' => 'year', 'type' => 'closure', 'section' => 'tracé'];
            }
        }

        $conflicts = [];
        $add = function (array $r, ?object $line, string $kind, string $field, $wd, $vff, string $other, ?int $gap, string $severity, string $note) use (&$conflicts) {
            $conflicts[] = [
                'qid' => $r['qid'], 'line_id' => $line?->id, 'code' => $line?->code, 'ligne' => $line?->name, 'wikidata_label' => $r['label'],
                'type' => $kind, 'champ' => $field, 'valeur_wikidata' => is_scalar($wd) || $wd === null ? $wd : json_encode($wd, JSON_UNESCAPED_UNICODE),
                'valeur_vff' => is_scalar($vff) || $vff === null ? $vff : json_encode($vff, JSON_UNESCAPED_UNICODE),
                'autre_source' => $other, 'ecart_annees' => $gap, 'gravite' => $severity, 'note' => $note,
                'wikidata_url' => 'https://www.wikidata.org/wiki/'.$r['qid'],
            ];
        };

        $perItem = [];
        foreach ($rows as $r) {
            $before = count($conflicts);
            $f = $r['facts'];
            if ($r['method'] === 'duplicate') {
                $b = $f['best_candidate'] ?? null;
                $add($r, $b ? (object) ['id' => $b['line_id'], 'code' => null, 'name' => $b['name']] : null, 'rattachement', 'code', implode(' / ', array_column($r['codes'], 'raw')), null, 'wikidata', null, 'mineur', (string) $r['note']);
            }
            if ($r['line_id'] === null) {
                $perItem[$r['qid']] = array_slice($conflicts, $before);

                continue;
            }
            $full = in_array($r['method'], self::FULL_METHODS, true);
            foreach (array_merge([$r['line_id']], $r['method'] === 'code_part' ? array_map('intval', $f['part_lines'] ?? []) : []) as $lid) {
                $line = $lines[$lid] ?? null;
                if (! $line) {
                    continue;
                }
                $alt = json_decode((string) $line->alt_names, true);
                if ($full && is_array($alt) && isset($alt['wikidata']) && $alt['wikidata'] !== $r['qid']) {
                    $add($r, $line, 'champ', 'alt_names.wikidata', $r['qid'], $alt['wikidata'], 'lines', null, 'majeur', 'la ligne porte déjà un autre QID');
                }
                $sixCodes = array_values(array_filter(array_column($r['codes'], 'code')));
                if ($line->code === null && $sixCodes !== []) {
                    $add($r, $line, 'rattachement', 'code', implode(' / ', $sixCodes), null, 'lines', null, 'info', 'ligne VFF sans code : code RFN suggéré par Wikidata (non écrit)');
                }
                if ($full && $f['length_m'] && $line->length_official_m && abs($f['length_m'] - $line->length_official_m) > max(1000, 0.05 * $f['length_m'])) {
                    $ratio = abs($f['length_m'] - $line->length_official_m) / max($f['length_m'], $line->length_official_m);
                    $add($r, $line, 'champ', 'length_official_m', $f['length_m'], $line->length_official_m, 'lines', null, $ratio > 0.2 ? 'majeur' : 'mineur', 'longueur différente (non écrasée)');
                }
                if ($f['gauge_mm'] && $line->gauge_mm_default && (int) $f['gauge_mm'] !== (int) $line->gauge_mm_default) {
                    $add($r, $line, 'champ', 'gauge_mm_default', $f['gauge_mm'], $line->gauge_mm_default, 'lines', null, 'majeur', 'écartement différent');
                }
                if ($full && $f['tracks'] && $line->nb_voies_default && (int) $f['tracks'] !== (int) $line->nb_voies_default) {
                    $add($r, $line, 'champ', 'nb_voies_default', $f['tracks'], $line->nb_voies_default, 'lines', null, 'mineur', 'nombre de voies différent');
                }
                if ($full && $f['max_speed_kmh'] && $line->max_speed_kmh_official && (int) $f['max_speed_kmh'] !== (int) $line->max_speed_kmh_official) {
                    $add($r, $line, 'champ', 'max_speed_kmh_official', $f['max_speed_kmh'], $line->max_speed_kmh_official, 'lines', null, 'mineur', 'vitesse maximale différente');
                }
                if ($f['frwiki'] && $line->wikipedia_title && $this->wpKey($f['frwiki']) !== $this->wpKey($line->wikipedia_title)) {
                    $add($r, $line, 'champ', 'wikipedia_title', $f['frwiki'], $line->wikipedia_title, 'lines', null, 'mineur', 'article frwiki différent (non écrasé)');
                }
                $es = $f['electrification_summary'] ?? null;
                if ($full && $es && ! $es['partial'] && isset($withSegments[$lid])) {
                    if ($es['electrified'] !== (bool) $line->electrified) {
                        $add($r, $line, 'champ', 'electrified', $es['label'], $line->electrified ? 'électrifiée' : 'non électrifiée', 'segments (LineClassifier)', null, 'mineur', 'électrification différente des segments');
                    } elseif ($es['voltage_v'] && $line->voltage_v && (int) $es['voltage_v'] !== (int) $line->voltage_v) {
                        $add($r, $line, 'champ', 'voltage_v', $es['voltage_v'], $line->voltage_v, 'segments (LineClassifier)', null, 'mineur', 'tension différente des segments');
                    }
                }

                // Dates : comparées source par source, par famille d'événements.
                if (! $full) {
                    continue;
                }
                $wdFirst = [];
                foreach ($f['events'] as $ev) {
                    if ($ev['resolution'] === 'unresolved') {
                        continue;
                    }
                    $fam = $ev['type'] === 'opening' || $ev['type'] === 'reopening' ? 'ouverture' : ($ev['type'] === 'closure' ? 'fermeture' : ($ev['type'] === 'electrification' ? 'electrification' : null));
                    if ($fam !== null) {
                        $wdFirst[$fam][] = $ev;
                    }
                }
                foreach ($wdFirst as $fam => $evs) {
                    foreach ($others[$lid][$fam] ?? [] as $src => $list) {
                        $years = array_column($list, 'year');
                        if ($fam === 'ouverture') {
                            // Première ouverture Wikidata vs première ouverture de l'autre source.
                            $first = $evs[0];
                            if ($first['type'] !== 'opening') {
                                continue;
                            }
                            usort($list, fn ($a, $b) => $a['date'] <=> $b['date']);
                            $o = $list[0];
                            $gap = abs($first['year'] - $o['year']);
                            $dayDiff = $gap === 0 && $first['precision'] === 'day' && $o['precision'] === 'day' && $first['date'] !== $o['date'];
                            if ($gap > 0 || $dayDiff) {
                                $add($r, $line, 'evenement', 'date_ouverture', $first['date'].' ('.$first['precision'].')', $o['date'].' ('.$o['precision'].($o['section'] !== '' ? ', '.$o['section'] : '').')', $src, $gap,
                                    $gap > 3 ? 'majeur' : 'mineur', $gap > 3 ? 'écart > 3 ans : EraTimeline garde les deux ouvertures' : 'écart ≤ 3 ans : dédoublonné par EraTimeline au profit de la source la plus fiable');
                            }

                            continue;
                        }
                        foreach ($evs as $ev) {
                            $near = array_filter($years, fn ($y) => abs($y - $ev['year']) <= 1);
                            if ($near === []) {
                                $gap = min(array_map(fn ($y) => abs($y - $ev['year']), $years));
                                $add($r, $line, 'evenement', 'date_'.$fam, $ev['date'].' ('.$ev['precision'].($ev['section_from'] || $ev['section_to'] ? ', '.$ev['section_from'].' - '.$ev['section_to'] : '').')',
                                    implode(' ; ', array_map(fn ($x) => $x['date'].' '.$x['type'].($x['section'] !== '' ? ' ('.$x['section'].')' : ''), array_slice($list, 0, 6))),
                                    $src, $gap, $gap > 3 ? 'majeur' : 'mineur', 'aucune date '.$fam.' de cette source à ±1 an');
                            }
                        }
                    }
                }
            }
            $perItem[$r['qid']] = array_slice($conflicts, $before);
        }

        // Lignes absentes : items ni rattachés, ni doublons d'un code connu.
        $missing = [];
        foreach ($rows as $r) {
            if ($r['line_id'] !== null || $r['method'] === 'duplicate') {
                continue;
            }
            $f = $r['facts'];
            $six = array_values(array_filter(array_column($r['codes'], 'code')));
            $open = null;
            $close = null;
            foreach ($f['events'] ?? [] as $ev) {
                if ($ev['type'] === 'opening' && $open === null) {
                    $open = $ev['date'];
                }
                if ($ev['type'] === 'closure') {
                    $close = $ev['date'];
                }
            }
            $terms = array_values(array_filter($r['termini'], fn ($t) => ! ($t['historique'] ?? false)));
            $inFrance = null;
            foreach ($f['points'] ?? [] as [, $lon, $lat]) {
                $inFrance = ($inFrance ?? false) || ($lon >= -5.5 && $lon <= 9.8 && $lat >= 41.2 && $lat <= 51.2);
            }
            $best = $f['best_candidate'] ?? null;
            $missing[] = [
                'qid' => $r['qid'],
                'libelle' => $r['label'],
                'categorie' => $r['category'],
                'codes' => implode(' / ', array_column($r['codes'], 'raw')),
                'raison' => $r['method'] === 'ambiguous' ? 'ambigu' : ($six !== [] ? 'code_absent_vff' : 'sans_code_ni_nom_position'),
                'ouverture' => $open,
                'fermeture' => $close,
                'etat' => $f['state'] ?? null,
                'longueur_km' => isset($f['length_m']) && $f['length_m'] ? round($f['length_m'] / 1000, 1) : null,
                'ecartement_mm' => $f['gauge_mm'] ?? null,
                'terminus' => implode(' | ', array_map(fn ($t) => $t['label'] ?? $t['qid'], $terms)),
                'terminus_coords' => implode(' | ', array_map(fn ($t) => $t['lat'] !== null ? sprintf('%.5f,%.5f', $t['lat'], $t['lon']) : '', $terms)),
                'nb_gares_wikidata' => $r['station_count'],
                'departements' => implode(', ', $f['departements'] ?? []),
                'en_france_metropolitaine' => $inFrance === null ? '' : ($inFrance ? 'oui' : 'non'),
                'frwiki' => $f['frwiki'] ?? null,
                'osm_relation' => $f['osm_relation'] ?? null,
                'candidat_vff' => $best ? $best['name'].' (#'.$best['line_id'].', nom '.$best['score'].($best['geo'] ?? null ? ', géo '.$best['geo'] : '').')' : null,
                'note' => $r['note'],
                'wikidata_url' => 'https://www.wikidata.org/wiki/'.$r['qid'],
            ];
        }
        $catOrder = ['ferroviaire' => 0, 'projet' => 1, 'service' => 2, 'funiculaire' => 3, 'urbain' => 4];
        usort($missing, fn ($a, $b) => [$catOrder[$a['categorie']] ?? 9, $a['raison'], (string) $a['libelle']] <=> [$catOrder[$b['categorie']] ?? 9, $b['raison'], (string) $b['libelle']]);

        $sev = array_count_values(array_column($conflicts, 'gravite'));
        $byField = array_count_values(array_column($conflicts, 'champ'));
        arsort($byField);
        $missCat = array_count_values(array_column($missing, 'categorie'));
        $missRail = array_filter($missing, fn ($m) => $m['categorie'] === 'ferroviaire');
        $this->info('→ Conflits : '.count($conflicts).' '.json_encode($sev, JSON_UNESCAPED_UNICODE).' - '.json_encode($byField, JSON_UNESCAPED_UNICODE));
        $this->info('→ Lignes Wikidata absentes de VFF : '.count($missing).' '.json_encode($missCat, JSON_UNESCAPED_UNICODE)
            .' - ferroviaires : '.json_encode(array_count_values(array_column($missRail, 'raison')), JSON_UNESCAPED_UNICODE));

        if ($saveConflicts) {
            DB::transaction(function () use ($rows, $perItem) {
                foreach ($rows as $r) {
                    DB::table('wikidata_lines')->where('qid', $r['qid'])->update(['conflicts' => ($perItem[$r['qid']] ?? []) !== [] ? json_encode($perItem[$r['qid']], JSON_UNESCAPED_UNICODE) : null]);
                }
            });
        }
        if (! $writeFiles) {
            return;
        }
        $this->csv($this->reportDir()."/wikidata-conflits{$suffix}.csv", $conflicts);
        $this->csv($this->reportDir()."/wikidata-lignes-absentes{$suffix}.csv", $missing);
        $names = DB::table('lines')->get(['id', 'code', 'name'])->keyBy('id');
        $this->csv($this->reportDir()."/wikidata-rattachements{$suffix}.csv", array_map(function ($r) use ($names) {
            $l = $r['line_id'] !== null ? ($names[$r['line_id']] ?? null) : null;
            $b = $r['facts']['best_candidate'] ?? null;

            return ['qid' => $r['qid'], 'libelle' => $r['label'], 'categorie' => $r['category'], 'codes' => implode(' / ', array_column($r['codes'], 'raw')),
                'methode' => $r['method'], 'score' => $r['score'], 'line_id' => $r['line_id'], 'code_vff' => $l?->code, 'ligne_vff' => $l?->name,
                'portions' => implode(',', $r['facts']['part_lines'] ?? []), 'candidat' => $b ? $b['name'].' ('.$b['score'].')' : null, 'note' => $r['note']];
        }, $rows));
        if ($suffix !== '') {
            $this->info('→ Rapports (à blanc) : '.$this->reportDir()."/wikidata-*{$suffix}.csv");

            return;
        }
        $summary = [
            'generated_at' => now()->toDateTimeString(),
            'wikidata_fetched_at' => $this->fetchedAt,
            'items' => count($rows),
            'categories' => array_count_values(array_column($rows, 'category')),
            'match_methods' => array_count_values(array_column($rows, 'method')),
            'lines_linked' => DB::table('line_sources')->where('source_type', self::SOURCE)->count(),
            'fields_held_from_wikidata' => $this->heldFields($rows),
            'events_in_db' => DB::table('line_events')->where('source', self::SOURCE)->selectRaw('type, count(*) AS n')->groupBy('type')->pluck('n', 'type'),
            'conflicts' => ['total' => count($conflicts), 'by_severity' => $sev, 'by_field' => $byField],
            'missing' => ['total' => count($missing), 'by_category' => $missCat, 'ferroviaire_by_reason' => array_count_values(array_column($missRail, 'raison'))],
        ] + $this->stats;
        $this->saveJson($this->reportDir().'/wikidata-import.json', $summary);
        $this->info('→ Rapports : '.$this->reportDir().'/wikidata-{conflits,lignes-absentes,rattachements}.csv + wikidata-import.json');
    }

    /** Champs de `lines` qui portent encore la valeur écrite par Wikidata (suivi facts.written). */
    private function heldFields(array $rows): array
    {
        $count = [];
        foreach ($rows as $r) {
            foreach ((array) ($r['facts']['written'] ?? []) as $cols) {
                foreach (array_keys((array) $cols) as $c) {
                    $count[$c] = ($count[$c] ?? 0) + 1;
                }
            }
        }
        ksort($count);

        return $count;
    }

    /** Affichage détaillé d'un item (vérification ponctuelle, --qid). */
    private function detail(string $qid, ?array $f, ?array $l): void
    {
        $this->newLine();
        if ($f === null) {
            $this->warn("{$qid} : absent de la liste Wikidata des lignes françaises");

            return;
        }
        $this->info("{$qid} - {$f['label']} [{$f['category']}] codes : ".implode(' / ', array_column($f['codes'], 'raw')));
        $line = $l['line_id'] ? DB::table('lines')->where('id', $l['line_id'])->first() : null;
        $this->line('  rattachement : '.$l['method'].($line ? " → #{$line->id} {$line->code} {$line->name}" : '').($l['note'] ? ' - '.$l['note'] : ''));
        if ($line) {
            $this->line('  lines : alt_names='.$line->alt_names.' longueur='.$line->length_official_m.' écartement='.$line->gauge_mm_default
                .' voies='.$line->nb_voies_default.' vmax='.$line->max_speed_kmh_official.' wp='.$line->wikipedia_title);
            $this->line('          régions='.$line->regions.' départements='.$line->departements);
            $this->line('          exploitants='.Str::limit((string) $line->historical_operators, 300));
            foreach (DB::table('line_events')->where('line_id', $line->id)->orderBy('event_date')->get() as $e) {
                $this->line(sprintf('  event %-10s %s %-5s %-15s %-12s %-6s %s', $e->source, $e->event_date, $e->date_precision, $e->type,
                    $e->section_resolution, $e->confidence, Str::limit($e->description, 90)));
            }
        }
        $this->line('  terminus : '.implode(' | ', array_map(fn ($t) => ($t['label'] ?? $t['qid']).($t['lat'] !== null ? sprintf(' (%.4f, %.4f)', $t['lat'], $t['lon']) : ''), $f['termini'])));
        $c = DB::table('wikidata_lines')->where('qid', $qid)->value('conflicts');
        foreach (json_decode((string) $c, true) ?? [] as $x) {
            $this->line("  conflit [{$x['gravite']}] {$x['champ']} : wikidata={$x['valeur_wikidata']} / {$x['autre_source']}={$x['valeur_vff']}");
        }
    }

    // ───────────────────────────── Utilitaires ─────────────────────────────

    private const JSON_FIELDS = ['regions', 'departements', 'historical_operators'];

    /** Champs que vff:import-lignes-oubliees réécrit sur ses lignes : jamais rafraîchis par Wikidata. */
    private const LO_FIELDS = ['length_official_m', 'regions', 'gauge_mm_default', 'nb_voies_default'];

    /** Valeurs que Wikidata propose pour `lines` (JSON déjà encodé), selon le type de rattachement. */
    private function fieldValues(array $f, bool $full): array
    {
        $v = [
            'gauge_mm_default' => $f['gauge_mm'],
            'historical_operators' => $f['operators'] !== [] ? json_encode($f['operators'], JSON_UNESCAPED_UNICODE) : null,
            'wikipedia_title' => $f['frwiki'],
        ];
        if (! $full) {
            return $v;
        }
        $es = $f['electrification_summary'];
        $elec = $es !== null && ! $es['partial'] && $es['electrified'];

        return $v + [
            'length_official_m' => $f['length_m'],
            'nb_voies_default' => $f['tracks'],
            'max_speed_kmh_official' => $f['max_speed_kmh'],
            'regions' => $f['regions'] !== [] ? json_encode($f['regions'], JSON_UNESCAPED_UNICODE) : null,
            'departements' => $f['departements'] !== [] ? json_encode($f['departements'], JSON_UNESCAPED_UNICODE) : null,
            'electrified' => $elec ? true : null,
            'voltage_v' => $elec ? $es['voltage_v'] : null,
        ];
    }

    private function sameValue(string $col, mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        if (in_array($col, self::JSON_FIELDS, true)) {
            return json_decode((string) $a, true) == json_decode((string) $b, true);
        }
        if ($col === 'electrified') {
            return (bool) $a === (bool) $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * Valeurs écrites par la passe précédente, par item et par ligne. Amorçage
     * (staging antérieur au suivi `written`) : on reprend les valeurs que
     * l'ancienne passe proposait - une ligne qui les porte encore les tient de
     * Wikidata.
     */
    private function previousWrites(): array
    {
        $out = [];
        foreach (DB::table('wikidata_lines')->whereNotNull('line_id')->get(['qid', 'line_id', 'match_method', 'facts']) as $r) {
            $facts = json_decode((string) $r->facts, true) ?? [];
            if (array_key_exists('written', $facts)) {
                foreach ((array) $facts['written'] as $lid => $cols) {
                    $out[$r->qid][(int) $lid] = (array) $cols;
                }

                continue;
            }
            if (! isset($facts['events'], $facts['operators'])) {
                continue;
            }
            $vals = array_filter($this->fieldValues($facts, in_array($r->match_method, self::FULL_METHODS, true)), fn ($v) => $v !== null);
            foreach (array_merge([(int) $r->line_id], array_map('intval', $facts['part_lines'] ?? [])) as $lid) {
                $out[$r->qid][$lid] = $vals;
            }
        }

        return $out;
    }

    private function wpKey(string $t): string
    {
        return mb_strtolower(str_replace('_', ' ', trim($t)));
    }

    private function statements(array $e, string $p): array
    {
        return array_values(array_filter($e['claims'][$p] ?? [], fn ($s) => ($s['rank'] ?? 'normal') !== 'deprecated'));
    }

    /** Meilleur rang (comme wdt:) : les « préférées » si elles existent. */
    private function best(array $e, string $p): array
    {
        $all = $this->statements($e, $p);
        $pref = array_values(array_filter($all, fn ($s) => $s['rank'] === 'preferred'));

        return $pref !== [] ? $pref : $all;
    }

    private function dv(array $snak): mixed
    {
        return ($snak['snaktype'] ?? '') === 'value' ? ($snak['datavalue']['value'] ?? null) : null;
    }

    private function entityId(array $snak): ?string
    {
        $v = $this->dv($snak);

        return is_array($v) && isset($v['id']) ? (string) $v['id'] : null;
    }

    private function qualIds(array $st, string $p): array
    {
        return array_values(array_filter(array_map(fn ($s) => $this->entityId($s), $st['qualifiers'][$p] ?? [])));
    }

    private function qualTime(array $st, string $p): ?array
    {
        foreach ($st['qualifiers'][$p] ?? [] as $s) {
            if ($t = $this->time($this->dv($s))) {
                return $t;
            }
        }

        return null;
    }

    /** Date Wikidata → date SQL + précision VFF ; null si plus grossière que l'année. */
    private function time(mixed $v): ?array
    {
        if (! is_array($v) || ! isset($v['time'], $v['precision'])) {
            return null;
        }
        if (! preg_match('/^\+(\d{1,4})-(\d{2})-(\d{2})T/', $v['time'], $m) || (int) $v['precision'] < 9) {
            return null;
        }
        $p = (int) $v['precision'];
        $precision = $p >= 11 ? 'day' : ($p === 10 ? 'month' : 'year');
        $y = (int) $m[1];
        $mo = $precision === 'year' ? 1 : max(1, (int) $m[2]);
        $d = $precision === 'day' ? max(1, (int) $m[3]) : 1;
        if ($y < 1800 || ! checkdate($mo, $d, $y)) {
            return null;
        }

        return ['date' => sprintf('%04d-%02d-%02d', $y, $mo, $d), 'precision' => $precision, 'year' => $y, 'raw' => $v['time']];
    }

    private function quantity(mixed $v): ?array
    {
        if (! is_array($v) || ! isset($v['amount'])) {
            return null;
        }

        return ['amount' => (float) $v['amount'], 'unit' => $this->qidOf((string) ($v['unit'] ?? ''))];
    }

    private function label(?string $qid): ?string
    {
        if ($qid === null) {
            return null;
        }
        if (isset($this->entities[$qid])) {
            $e = $this->entities[$qid];

            return $e['labels']['fr']['value'] ?? $e['labels']['en']['value'] ?? null;
        }

        return $this->refs[$qid]['fr'] ?? $this->refs[$qid]['en'] ?? null;
    }

    private function qidOf(string $uri): string
    {
        return substr($uri, strrpos($uri, '/') === false ? 0 : strrpos($uri, '/') + 1);
    }

    private function values(array $qids): string
    {
        return implode(' ', array_map(fn ($q) => 'wd:'.$q, array_filter($qids, fn ($q) => preg_match('/^Q\d+$/', $q))));
    }

    /** « Point(2.35 48.85) » → [lon, lat] ; null pour un autre globe. */
    private function parsePoint(?string $wkt): ?array
    {
        if ($wkt === null || str_starts_with($wkt, '<') || ! preg_match('/Point\(\s*(-?[\d.eE+-]+)\s+(-?[\d.eE+-]+)\s*\)/', $wkt, $m)) {
            return null;
        }

        return [(float) $m[1], (float) $m[2]];
    }

    private function sparql(string $query): array
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->throttle();
            try {
                $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'application/sparql-results+json'])
                    ->asForm()->timeout(300)->post(self::SPARQL_URL, ['query' => $query]);
            } catch (\Throwable $e) {
                $this->warn("  SPARQL : erreur réseau ({$attempt}) ".$e->getMessage());
                sleep(5 * $attempt);

                continue;
            }
            if ($resp->ok()) {
                return $resp->json('results.bindings') ?? [];
            }
            $this->warn("  SPARQL : HTTP {$resp->status()} ({$attempt})");
            if (! in_array($resp->status(), [429, 500, 502, 503, 504], true)) {
                break;
            }
            sleep(min(120, (int) ($resp->header('Retry-After') ?: 10 * $attempt)));
        }
        throw new \RuntimeException('Requête SPARQL en échec');
    }

    private function api(array $params): array
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->throttle();
            try {
                $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(120)->get(self::API_URL, $params + ['maxlag' => 5]);
            } catch (\Throwable $e) {
                $this->warn("  API : erreur réseau ({$attempt}) ".$e->getMessage());
                sleep(5 * $attempt);

                continue;
            }
            $json = $resp->json();
            if ($resp->ok() && is_array($json) && ! isset($json['error'])) {
                return $json;
            }
            $this->warn("  API : HTTP {$resp->status()} ".($json['error']['code'] ?? '')." ({$attempt})");
            sleep(min(60, (int) ($resp->header('Retry-After') ?: 5 * $attempt)));
        }
        throw new \RuntimeException('Requête API Wikidata en échec');
    }

    private function throttle(): void
    {
        $wait = self::DELAY_S - (microtime(true) - $this->lastRequestAt);
        if ($wait > 0) {
            usleep((int) ($wait * 1e6));
        }
        $this->lastRequestAt = microtime(true);
    }

    /** Cache clé → valeur : ne télécharge que les clés absentes (ou tout, avec --refresh). */
    private function cachedMap(string $file, array $ids, callable $fetch): array
    {
        $path = $this->cacheDir().'/'.$file;
        $map = (! $this->option('refresh') && is_file($path)) ? (json_decode((string) file_get_contents($path), true) ?? []) : [];
        $missing = array_values(array_diff($ids, array_keys($map)));
        if ($missing !== [] && ! $this->option('from-cache')) {
            $got = $fetch($missing);
            foreach ($missing as $id) {
                $map[$id] = $got[$id] ?? [];
            }
            $this->saveJson($path, $map);
        } elseif ($missing !== []) {
            $this->warn('  '.count($missing)." clé(s) absente(s) du cache {$file}");
        }

        return $map;
    }

    private function saveJson(string $path, array $data): void
    {
        file_put_contents($path.'.part', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        rename($path.'.part', $path);
    }

    private function csv(string $path, array $rows): void
    {
        $h = fopen($path.'.part', 'w');
        if ($rows !== []) {
            fputcsv($h, array_keys($rows[0]), ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($h, CsvSafe::row(array_map(fn ($v) => is_bool($v) ? ($v ? 'oui' : 'non') : $v, $r)), ',', '"', '');
            }
        }
        fclose($h);
        rename($path.'.part', $path);
    }

    private function cacheDir(): string
    {
        return storage_path('app/sources/wikidata');
    }

    private function reportDir(): string
    {
        return storage_path('app/reports');
    }
}
