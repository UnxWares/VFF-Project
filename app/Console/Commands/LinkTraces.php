<?php

namespace App\Console\Commands;

use App\Services\LineMerger;
use App\Services\PlaceNames;
use App\Services\TraceSplitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rattache les tracés bruts (`source_traces`) aux lignes, crée les lignes
 * manquantes, les nomme et les catégorise.
 *
 * Pourquoi : la carte par décennie doit montrer CHAQUE portion de CHAQUE ligne,
 * y compris les lignes d'intérêt local, militaires et industrielles fermées
 * avant le RFN actuel. archeoferroviaire (et demain OHM…) fournit les tracés
 * précis ; il faut savoir à quelle ligne appartient chaque tracé, et créer
 * proprement (nom, catégorie, écartement) les lignes qui n'existent pas encore.
 *
 * Cascade, de la preuve la plus forte à la plus faible :
 *   a. RECOUVREMENT RFN (sncf_overlap) : part du tracé dans un tampon de
 *      --buffer m (40 par défaut, calibré : 92 % des points des tracés
 *      archeo « lignes exploitées » sont à < 30 m du tronçon SNCF généralisé,
 *      puis plateau) autour des tronçons SNCF d'un même code ≥ --overlap
 *      (60 %). Un tracé à cheval sur plusieurs codes (« Auray Saint-Brieuc »)
 *      va au code dominant si l'ensemble des codes couvre ≥ 60 % et le
 *      dominant ≥ 30 % ; toutes les lignes couvertes vont dans
 *      source_trace_lines. Les voies étroites annoncées (V60, 0m60, 0m70) ne
 *      se rattachent jamais ainsi : une voie de 60 longeant la ligne normale
 *      (transbordement) est une autre ligne.
 *   a'. RECOUVREMENT par les tracés d'AUTRES sources déjà rattachés
 *      (trace_overlap, tampon 25 m) : sert surtout à OHM/OSM une fois
 *      archeoferroviaire rattaché. Un tracé dont le NOM désigne une autre
 *      ligne (ses deux extrémités citées, garde-fous de l'étape b) n'est pas
 *      rattaché par recouvrement mais par son nom : le way OHM « Ligne de
 *      Montmédy à Écouviez » longe le tracé archeo « Velosnes Virton », il
 *      appartient pourtant à la ligne de Montmédy à Écouviez.
 *   b. NOM (name) : « Carhaix Morlaix » ↔ « Ligne de Carhaix à Morlaix » :
 *      comparaison de toponymes normalisés (accents, St/Saint, -Ville, Gare,
 *      articles…) : chaque extrémité de la ligne doit être citée par le
 *      libellé et réciproquement. Garde-fou spatial : au moins un toponyme du
 *      libellé situé (communes, gares) à ≤ 8 km du tracé, et, si la ligne a
 *      déjà une géométrie, tracé à ≤ 20 km de celle-ci. Variante partielle :
 *      tracé recouvert à ≥ 20 % par un code SNCF dont le nom ou les gares
 *      citent un de ses toponymes (« Montcornet Liart » → Laon-Liart) ; refusée
 *      si un autre toponyme du libellé, étranger à la ligne et localisé près du
 *      tracé, est à plus de 15 km de la ligne (« Angers Aubigné-Racan » n'est
 *      pas Aubigné-Racan-Sablé : le tracé déborde sur la ligne d'Angers à La
 *      Flèche ; « Briouze Couterne » reste la ligne de Briouze à La Ferté-Macé,
 *      Couterne étant à 8 km).
 *   c. CRÉATION (created) : tracés restants regroupés par (calque, nom
 *      normalisé) ET connexité spatiale (≤ 5 km) ; « Ligne de X à Y » si deux
 *      toponymes, sinon libellé nettoyé ; réseaux sous un seul libellé
 *      (« Bassin Minier Nord - Lignes minières ») découpés en composantes
 *      connexes puis en secteurs de ~40 km nommés d'après la commune
 *      principale ; projets jamais réalisés NON créés.
 *   d. CONTACT (touching) : tracés sans nom, au nom technique (horodatage) ou
 *      générique (« tracé initial », « raccordement »…) rattachés à la ligne
 *      de même catégorie qu'ils touchent par une extrémité (≤ --touch m),
 *      itérativement ; à défaut, ligne « Embranchement / Ligne militaire /
 *      Voie industrielle … près de <commune> » (nom dérivé).
 *   d'. MÊME VOIE (same_track) : un tracé des calques particulier / militaire
 *      dont ≥ 80 % de la longueur est à ≤ 20 m (et ≥ 50 % à ≤ 5 m) des tracés
 *      d'UNE ligne principale ou secondaire est cette ligne, dessinée une
 *      seconde fois dans un autre calque (archeo trace la ligne de Denain à
 *      Saint-Amand dans « lignes exploitées » ET dans « Bassin minier ») : il
 *      lui est rattaché au lieu de créer un doublon d'affichage. Preuves : les
 *      tracés de la ligne retenus par recouvrement RFN / nom dans cette
 *      exécution, ceux des autres sources (hors BD TOPO / OSM / recouvrement),
 *      les tronçons SNCF. Voies étroites (V60, 0m70) exclues ; une voie
 *      parallèle distincte (faisceau minier à 10-30 m) ne passe pas le seuil
 *      de 5 m et reste une ligne particulière.
 *   d''. DÉCOUPE (split) : un tracé « A B » des calques principal / secondaire
 *      (archeo) ou rail / disused (OHM) qui met BOUT À BOUT 2 ou 3 lignes
 *      connues (« Auray Saint-Brieuc » = Auray - Pontivy + Pontivy -
 *      Saint-Brieuc ; « Bram Lavelanet » = Bram - Belvèze + Belvèze -
 *      Moulin-Neuf, section de Pamiers - Limoux, + Moulin-Neuf - Lavelanet) est
 *      coupé aux jonctions, chaque morceau rattaché à sa ligne
 *      (App\Services\TraceSplitter). Preuves : extrémités et gares des lignes
 *      (nom, Wikipédia, lignes-oubliees, gares SNCF) ; aucune ligne ne contient
 *      à la fois A et B ; A, les jonctions et B localisés (gare, sinon commune)
 *      près du tracé et dans l'ordre ; longueur de chaque morceau compatible
 *      avec la longueur officielle de sa ligne ; là où la ligne a une géométrie
 *      connue (couloir de 2 km), le morceau la suit ; une seule chaîne retenue (sinon
 *      ambiguë, rien n'est coupé). Le tracé d'origine reste dans source_traces
 *      (line_id NULL, match_method « split ») ; les morceaux sont des tracés
 *      DÉRIVÉS de même source (source_ref « <ref>#<n> », properties.derived_from
 *      = id d'origine, properties.split = chaîne et bornes), recalculés à chaque
 *      exécution. Une ligne créée ici dont tous les tracés ont été découpés
 *      (« chimère ») est supprimée si rien d'autre ne la référence.
 *      Rapport : storage/app/reports/link-traces-<source>-decoupes.csv.
 *   e. Catégorie : lines.network = calque (principal | secondaire |
 *      militaire | particulier) ; écartement uniquement s'il est écrit dans le
 *      libellé (V60 → 600, 0m70 → 700, VM/métrique → 1000, VN → 1435).
 *      Le calque « exploitee » ne crée jamais de ligne.
 *   f. Réseaux secondaires HOMONYMES d'une ligne RFN (mêmes extrémités, autre
 *      tracé, autre compagnie, voie métrique) : nom explicite, écartement et
 *      exploitant vérifiés (self::HOMONYMS), pour qu'aucune source ne les
 *      confonde avec la ligne RFN.
 *
 * Rejouable : seuls les rattachements faits par cette commande (match_method
 * sncf_overlap | trace_overlap | name | touching | created) sont recalculés ;
 * ceux posés par d'autres (code…) sont laissés tels quels. Les lignes créées
 * sont retrouvées par alt_names.group_key (identifiants stables) ; celles qui
 * ne servent plus sont supprimées si rien d'autre ne les référence, ou
 * fusionnées (événements, tracés d'autres sources) dans la ligne qui a reçu
 * TOUS leurs tracés (App\Services\LineMerger).
 *
 *   php artisan vff:link-traces --source=sncf            # tronçons SNCF → source_traces (par code)
 *   php artisan vff:link-traces                          # archeoferroviaire
 *   php artisan vff:link-traces --source=ohm --dry-run
 */
class LinkTraces extends Command
{
    protected $signature = 'vff:link-traces
        {--source=archeoferroviaire : source à rattacher (archeoferroviaire, ohm, osm… ; « sncf » convertit les tronçons SNCF)}
        {--dry-run : calcule et affiche sans rien écrire}
        {--buffer=40 : tampon (m) autour des tronçons SNCF généralisés}
        {--overlap=0.6 : part minimale du tracé recouverte pour un rattachement par recouvrement}
        {--touch=50 : distance (m) d\'une extrémité pour toucher une ligne voisine}
        {--ref-sources=* : sources de référence pour trace_overlap (défaut : toutes les autres sauf bdtopo/osm)}';

    protected $description = 'Rattache les tracés bruts aux lignes (recouvrement RFN, nom, contact), crée et nomme les lignes manquantes.';

    /** Méthodes posées (et donc recalculées) par cette commande. */
    public const METHODS = ['sncf_overlap', 'trace_overlap', 'name', 'touching', 'created', 'same_track', 'split'];

    /** Clés de lines.alt_names recalculées ici pour les lignes créées (les autres sont conservées). */
    private const MANAGED_ALT_KEYS = ['name_source', 'created_by', 'source', 'group_key', 'labels', 'flags',
        'homonym_of', 'operator', 'proof'];

    /** Calques dont un tracé peut mettre plusieurs lignes bout à bout (étape d''), par source. */
    private const SPLIT_LAYERS = [
        'archeoferroviaire' => ['principal', 'secondaire'],
        'ohm' => ['rail', 'disused', 'abandoned', 'razed', 'narrow_gauge'],
    ];

    /** Portion continue minimale sur la voie d'une ligne principale / secondaire pour être découpée (étape d''', m). */
    private const PARTIAL_MIN_RUN_M = 1500;

    /** Tracé plus court : pas examiné pour une découpe (m). */
    private const SPLIT_MIN_TRACE_M = 5000;

    /** Gare / commune au plus à cette distance du tracé pour localiser un lieu de la chaîne (m). */
    private const SPLIT_STATION_M = 2500;

    private const SPLIT_COMMUNE_M = 5000;

    /** Géométrie connue d'une ligne dans le couloir (2 km) d'un morceau : part minimale à ≤ 150 m du morceau. */
    private const SPLIT_GEOM_SHARE = 0.5;

    /**
     * Tracés découpés (étape d'') : id du tracé d'origine → morceaux.
     *
     * @var array<int, array{label: string, chain: string, pieces: list<array{line_id: int, f0: float, f1: float, from: string, to: string, len: int, geom: ?float}>}>
     */
    private array $splits = [];

    /** @var list<array<string, mixed>> décisions de l'étape d'' (rapport) */
    private array $splitLog = [];

    /** Calques dont un tracé peut doubler une ligne principale / secondaire (étape d'). */
    private const SAME_TRACK_LAYERS = ['particulier', 'militaire'];

    /**
     * Réseaux secondaires distincts d'une ligne RFN de mêmes extrémités, vérifiés
     * le 2026-10-19 (tracé à ≥ 2,8 km de la ligne RFN, < 7 % de recouvrement à
     * 150 m ; compagnie et écartement d'après frwiki / OHM / OSM). Clé : calque |
     * libellé archeo normalisé du tracé principal du groupe.
     *
     * @var array<string, array{name: string, gauge: ?int, operator: string, homonym_of: string, proof: string}>
     */
    private const HOMONYMS = [
        'secondaire|besancon vesoul' => ['name' => 'Ligne de Besançon à Vesoul (CFV)', 'gauge' => 1000,
            'operator' => 'Compagnie générale des chemins de fer vicinaux (CFV)', 'homonym_of' => '856000',
            'proof' => 'frwiki « Chemins de fer vicinaux de la Haute-Saône » : Vesoul - Besançon (3e réseau 1910-1912, voie métrique, fermé en 1938) ; tracé par Étuz, Boult, Grandvelle, à 17 km de la ligne PLM par Montbozon'],
        'secondaire|cannes grasse' => ['name' => 'Tramway de Cannes à Grasse', 'gauge' => null,
            'operator' => 'Compagnie des tramways électriques de la Côte d\'Azur (TCA)', 'homonym_of' => '944000',
            'proof' => 'frwiki « Tramway de Cannes » / « Compagnie des tramways électriques de la Côte d\'Azur » : Cannes - Mouans-Sartoux - Grasse ouvert le 20 août 1915, exploitation reprise par les tramways de Cannes, fin le 1er octobre 1926 ; tracé par Le Cannet, à 2,8 km de la ligne PLM par Ranguin'],
        'secondaire|reims chalons' => ['name' => 'Ligne de Reims à Châlons-sur-Marne (CBR)', 'gauge' => 1000,
            'operator' => 'Chemins de fer de la Banlieue de Reims (CBR)', 'homonym_of' => '081000',
            'proof' => 'frwiki « Chemins de fer de la Banlieue de Reims » : Reims - Châlons-sur-Marne (58 km), voie métrique, par Verzy et Ambonnay ; tracé archeo de 58 km par Verzenay, Ambonnay, Condé-sur-Marne, à 10 km de la ligne de l\'Est par Sillery'],
        'secondaire|rennes guer redon' => ['name' => 'Tramway de Rennes à Redon par Guer (TIV)', 'gauge' => 1000,
            'operator' => 'Tramways d\'Ille-et-Vilaine (TIV)', 'homonym_of' => '468000',
            'proof' => 'frwiki « Tramways d\'Ille-et-Vilaine » (voie métrique) : Rennes - Plélan-le-Grand (1898), Plélan - Guer (1913-1948), Guer - Redon (1924-1937) ; OHM « Tramway Rennes Guer Redon » (opérateur TIV) ; aucun recouvrement avec la ligne RFN par Messac'],
        'secondaire|rennes maure redon' => ['name' => 'Tramway de Rennes à Redon par Maure (TIV)', 'gauge' => 1000,
            'operator' => 'Tramways d\'Ille-et-Vilaine (TIV)', 'homonym_of' => '468000',
            'proof' => 'frwiki « Tramways d\'Ille-et-Vilaine » (voie métrique) : Bréal-sous-Montfort - Redon (72 km, 1912-1937) par Maure et Pipriac ; OHM « Ligne Rennes-Maure-Redon » (opérateur TIV), OSM « Ancienne voie ferrée TIV Rennes - Maure - Redon »'],
        'secondaire|rennes saint malo' => ['name' => 'Tramway de Rennes à Saint-Malo (TIV)', 'gauge' => 1000,
            'operator' => 'Tramways d\'Ille-et-Vilaine (TIV)', 'homonym_of' => '441000',
            'proof' => 'frwiki « Tramways d\'Ille-et-Vilaine » (voie métrique) : Rennes (Croix-Mission) - La Mézière - Saint-Malo (79 km, 1901-1950) ; OSM « Ancienne ligne T.I.V Rennes - Saint-Malo » ; tracé par Tinténiac, à 12 km de la ligne de l\'Ouest par Dol'],
        'secondaire|riom volvic' => ['name' => 'Ligne de Riom à Volvic (CFL)', 'gauge' => 1000,
            'operator' => 'Compagnie des chemins de fer de la Limagne (CFL)', 'homonym_of' => '793606',
            'proof' => 'frwiki « Compagnie des chemins de fer de la Limagne » : Riom - Volvic (18 km, voie métrique, 1890-1936) ; tracé de 17,6 km par Marsat et Malauzat, distinct de la voie-mère de la ZI de Volvic (793606) et de la ligne de Lapeyrouse à Volvic (709000)'],
    ];

    /** Calque source → catégorie VFF (lines.network). */
    private const LAYER_NETWORK = [
        'principal' => 'principal', 'exploitee' => 'principal', 'rfn' => 'principal',
        'secondaire' => 'secondaire', 'militaire' => 'militaire', 'particulier' => 'particulier',
    ];

    /** Calques qui ne créent jamais de ligne (contexte uniquement). */
    private const NO_CREATE_LAYERS = ['exploitee'];

    private const SLUG_PREFIX = ['archeoferroviaire' => 'archeo', 'openhistoricalmap' => 'ohm'];

    private string $source;

    private bool $dry;

    /** @var array<int, array<string, mixed>> tracés de la source, par id */
    private array $traces = [];

    /** @var array<int, array{line_id: int, method: string, score: ?float}> */
    private array $assign = [];

    /** @var array<int, array<string, mixed>> lignes à créer, clé négative */
    private array $planned = [];

    private int $nextPlanned = -1;

    /** @var array<string, int> statistiques de rejets (garde-fous) */
    private array $rejects = [];

    /** @var array<int, array{places: list<list<string>>, all: list<string>}> lignes candidates (noms analysés) */
    private array $candLines = [];

    /** @var array<string, array<int, bool>> mot significatif → lignes */
    private array $lineIndex = [];

    /** @var array<int, ?string> */
    private array $lineNetworkMap = [];

    /** @var array<int, int> lignes ayant des tronçons SNCF */
    private array $hasSncf = [];

    public function handle(): int
    {
        // Même processus pour plusieurs sources (vff:rebuild-history enchaîne archeo puis ohm) :
        // rien ne doit passer d'un passage à l'autre.
        [$this->splits, $this->splitLog, $this->traces, $this->assign, $this->planned, $this->rejects,
            $this->candLines, $this->lineIndex, $this->lineNetworkMap, $this->hasSncf] = [[], [], [], [], [], [], [], [], [], []];
        $this->nextPlanned = -1;
        $this->source = (string) $this->option('source');
        $this->dry = (bool) $this->option('dry-run');
        DB::connection()->disableQueryLog();

        if ($this->source === 'sncf') {
            return $this->importSncf();
        }

        $this->loadTraces();
        if (! $this->traces) {
            $this->warn("Aucun tracé pour la source « {$this->source} ».");

            return self::SUCCESS;
        }
        $this->prepareTempTables();

        $this->info('→ a. Recouvrement des tronçons SNCF…');
        $this->overlapSncf();
        $this->info('→ a\'. Recouvrement par les tracés d\'autres sources…');
        $this->overlapOtherTraces();
        $this->info('→ b. Correspondance par nom…');
        $groups = $this->buildGroups();
        $this->matchByName($groups);
        $this->info('→ c. Création des lignes nommées et des réseaux…');
        $this->planNamedLines($groups);
        $this->planNetworkLabels();
        $this->info('→ d. Contact (tracés sans nom ou génériques)…');
        $this->touching();
        $this->planLeftovers();
        $this->planExploited();
        $this->info('→ d\'. Même voie qu\'une ligne principale ou secondaire (calques particulier, militaire)…');
        $this->sameTrack();
        $this->info('→ d\'\'\'. Portions longues sur la voie d\'une ligne principale ou secondaire (calques particulier, militaire)…');
        $this->sameTrackPartial();
        $this->info('→ d\'\'. Découpe des tracés qui mettent plusieurs lignes bout à bout…');
        $this->splitAcrossLines();
        $this->uniquifyPlannedNames();
        $this->matchWikidataCodes();

        $this->report();
        $this->writeCsv();
        if ($this->dry) {
            $this->warn('Simulation (--dry-run) : rien n\'a été écrit.');

            return self::SUCCESS;
        }
        DB::transaction(fn () => $this->apply(), 3); // rejoué en cas d'interblocage avec un traitement parallèle
        $this->info('✓ Rattachements enregistrés.');

        return self::SUCCESS;
    }

    // ───────────────────────────── chargement ─────────────────────────────

    private function loadTraces(): void
    {
        // Morceaux dérivés d'une découpe (étape d'') : régénérés à chaque exécution, jamais rattachés eux-mêmes.
        $rows = DB::select(
            "SELECT id, source_ref, layer, name, line_id, match_method, length_m FROM source_traces
             WHERE source = ? AND (properties->>'derived_from') IS NULL",
            [$this->source],
        );
        $parsedCache = [];
        foreach ($rows as $r) {
            $mine = $r->line_id === null || in_array($r->match_method, self::METHODS, true);
            $label = $r->name ?? '';
            $parsedCache[$label] ??= PlaceNames::parseLabel($label);
            $this->traces[$r->id] = [
                'id' => $r->id, 'ref' => $r->source_ref, 'layer' => $r->layer, 'name' => self::cleanSourceName($r->name),
                'network' => self::LAYER_NETWORK[$r->layer] ?? null, 'len' => (int) $r->length_m,
                'parsed' => $parsedCache[$label], 'mine' => $mine,
                'fixed_line' => $mine ? null : (int) $r->line_id,
            ];
        }
        $mine = count(array_filter($this->traces, fn ($t) => $t['mine']));
        $this->line(sprintf('  %d tracés « %s », dont %d à (re)calculer.', count($this->traces), $this->source, $mine));
    }

    private function prepareTempTables(): void
    {
        $buffer = (float) $this->option('buffer');
        DB::statement('DROP TABLE IF EXISTS lt_t, lt_sb, lt_cov, lt_tot, lt_gaz, lt_lgeom, lt_ref, lt_refcov');
        DB::statement("CREATE TEMP TABLE lt_t AS
            SELECT id, layer, ST_Transform(geom, 2154) AS g, NULL::text AS gkey FROM source_traces
            WHERE source = ? AND (properties->>'derived_from') IS NULL ORDER BY id", [$this->source]);
        DB::statement('CREATE INDEX ON lt_t USING gist (g)');
        DB::statement('CREATE UNIQUE INDEX ON lt_t (id)');
        DB::statement('ANALYZE lt_t');

        // Tampon des tronçons SNCF par code (union), avec la ligne VFF du code.
        DB::statement("CREATE TEMP TABLE lt_sb AS
            SELECT s.code_ligne, l.id AS line_id, ST_Union(ST_Buffer(ST_Transform(s.geom, 2154), ?, 'quad_segs=4')) AS g
            FROM sncf_line_geometries s JOIN lines l ON l.code = s.code_ligne
            GROUP BY s.code_ligne, l.id", [$buffer]);
        DB::statement('CREATE INDEX ON lt_sb USING gist (g)');

        // Géométrie connue de chaque ligne (hors tracés recalculés ici) : garde-fou spatial des noms.
        // Sans les tracés rattachés par recouvrement (trace_overlap) : ils suivent un rattachement de cette
        // source, à recalculer (un OHM « Angers - La Flèche » collé à tort à Aubigné-Racan-Sablé le confirmerait).
        DB::statement("CREATE TEMP TABLE lt_lgeom AS
            SELECT line_id, ST_Collect(g) AS g FROM (
                SELECT l.id AS line_id, ST_Transform(s.geom, 2154) AS g
                FROM sncf_line_geometries s JOIN lines l ON l.code = s.code_ligne
                UNION ALL
                SELECT t.line_id, ST_Transform(ST_Simplify(t.geom, 0.0002), 2154)
                FROM source_traces t
                WHERE t.line_id IS NOT NULL AND t.source NOT IN (?, 'sncf', 'bdtopo', 'osm')
                  AND COALESCE(t.match_method, '') <> 'trace_overlap'
            ) x GROUP BY line_id", [$this->source]);
        DB::statement('CREATE UNIQUE INDEX ON lt_lgeom (line_id)');

        // Gazetteer : communes (actuelles + anciennes) et gares SNCF, noms normalisés.
        DB::statement('CREATE TEMP TABLE lt_gaz (norm text, nom text, pop int, kind text, g geometry(Point, 2154))');
        DB::statement('INSERT INTO lt_gaz SELECT nom_norm, nom, population, type, ST_Transform(geom, 2154) FROM communes');
        $gares = DB::select('SELECT DISTINCT libelle, commune, lat, lon FROM sncf_gares WHERE lat IS NOT NULL AND libelle IS NOT NULL');
        foreach (array_chunk($gares, 500) as $chunk) {
            $values = [];
            $bind = [];
            foreach ($chunk as $g) {
                $values[] = "(?, ?, NULL, 'gare', ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 2154))";
                array_push($bind, PlaceNames::normalize($g->libelle), $g->libelle, $g->lon, $g->lat);
            }
            DB::statement('INSERT INTO lt_gaz VALUES '.implode(',', $values), $bind);
        }
        // Part de chaque tracé située en France (points tous les 1 km dans un contour de commune).
        DB::statement('DROP TABLE IF EXISTS lt_fr');
        if (DB::getSchemaBuilder()->hasTable('commune_contours') && DB::table('commune_contours')->exists()) {
            DB::statement('CREATE TEMP TABLE lt_fr AS
                SELECT d.id, AVG((EXISTS (SELECT 1 FROM commune_contours c WHERE ST_Intersects(c.geom, ST_Transform(d.p, 4326))))::int) AS share
                FROM (SELECT id, (ST_Dump(ST_LineInterpolatePoints(g, LEAST(1.0, 1000.0 / GREATEST(ST_Length(g), 1)), true))).geom AS p
                      FROM (SELECT id, (ST_Dump(g)).geom AS g FROM lt_t) x) d
                GROUP BY d.id');
        } else {
            DB::statement('CREATE TEMP TABLE lt_fr AS SELECT id, 1.0::float AS share FROM lt_t');
        }
        foreach (DB::select('SELECT id, share FROM lt_fr') as $r) {
            $this->traces[$r->id]['fr'] = (float) $r->share;
        }
        DB::statement('CREATE INDEX ON lt_gaz (norm text_pattern_ops)');
        DB::statement('CREATE INDEX ON lt_gaz USING gist (g)');
        DB::statement('ANALYZE lt_gaz');
    }

    // ───────────────────────────── a. recouvrement ─────────────────────────────

    private function overlapSncf(): void
    {
        DB::statement('CREATE TEMP TABLE lt_cov AS
            SELECT t.id, sb.line_id, ST_Length(ST_Intersection(t.g, sb.g)) AS overlap_m, ST_Length(t.g) AS len
            FROM lt_t t JOIN lt_sb sb ON ST_Intersects(t.g, sb.g)');
        DB::statement('DELETE FROM lt_cov WHERE overlap_m < 1 OR len = 0');
        DB::statement('CREATE INDEX ON lt_cov (id)');
        DB::statement('CREATE TEMP TABLE lt_tot AS
            SELECT t.id, ST_Length(ST_Intersection(t.g, ST_Union(sb.g))) / NULLIF(ST_Length(t.g), 0) AS share
            FROM lt_t t JOIN lt_sb sb ON ST_Intersects(t.g, sb.g)
            WHERE t.id IN (SELECT id FROM lt_cov GROUP BY id HAVING COUNT(*) > 1)
            GROUP BY t.id, t.g');

        $threshold = (float) $this->option('overlap');
        $cov = [];
        foreach (DB::select('SELECT id, line_id, overlap_m, overlap_m / len AS share FROM lt_cov ORDER BY id, overlap_m DESC') as $r) {
            $cov[$r->id][] = ['line_id' => (int) $r->line_id, 'share' => (float) $r->share, 'overlap_m' => (int) round($r->overlap_m)];
        }
        $tot = [];
        foreach (DB::select('SELECT id, share FROM lt_tot') as $r) {
            $tot[$r->id] = (float) $r->share;
        }

        foreach ($this->traces as $id => &$t) {
            $t['sncf'] = $cov[$id] ?? [];
            if (! $t['mine'] || ! $t['sncf']) {
                continue;
            }
            $best = $t['sncf'][0];
            $union = $tot[$id] ?? $best['share'];
            $narrow = in_array($t['parsed']['gauge'], [600, 700], true);
            if ($narrow) {
                $this->reject('voie étroite : pas de rattachement RFN par recouvrement');

                continue;
            }
            $anonymous = $t['parsed']['junk'] || $t['parsed']['generic'];
            if ($best['share'] >= $threshold
                || ($t['network'] === 'principal' && $union >= $threshold && $best['share'] >= 0.3)
                // Tracé anonyme ou générique (ancien tracé, raccordement) recouvrant en partie une ligne RFN :
                // c'est une portion ou une variante de cette ligne.
                || ($t['network'] === 'principal' && $anonymous && $best['share'] >= 0.3)) {
                $this->assign[$id] = ['line_id' => $best['line_id'], 'method' => 'sncf_overlap', 'score' => round($best['share'], 3)];
            }
        }
        unset($t);
        $this->line('  '.count($this->assign).' tracés rattachés par recouvrement RFN.');
    }

    private function overlapOtherTraces(): void
    {
        $refs = array_filter((array) $this->option('ref-sources'));
        $where = $refs
            ? 'r.source IN ('.implode(',', array_fill(0, count($refs), '?')).')'
            : "r.source NOT IN ('sncf', 'bdtopo', 'osm')";
        $bind = $refs ?: [];
        $bind[] = $this->source;
        DB::statement("CREATE TEMP TABLE lt_ref AS
            SELECT r.line_id, ST_Transform(r.geom, 2154) AS g FROM source_traces r
            WHERE {$where} AND r.source <> ? AND r.line_id IS NOT NULL
              AND COALESCE(r.match_method, '') <> 'trace_overlap'
              AND r.line_id NOT IN (SELECT id FROM lines WHERE alt_names->>'created_by' = 'vff:link-traces' AND alt_names->>'source' = ?)",
            array_merge($bind, [$this->source]));
        DB::statement('CREATE INDEX ON lt_ref USING gist (g)');
        $nRef = DB::selectOne('SELECT COUNT(*) n FROM lt_ref')->n;
        if (! $nRef) {
            $this->line('  aucun tracé de référence rattaché dans d\'autres sources.');

            return;
        }

        $todo = array_keys(array_filter($this->traces, fn ($t) => $t['mine'] && ! isset($this->assign[$t['id']])));
        if (! $todo) {
            return;
        }
        DB::statement('CREATE TEMP TABLE lt_refcov (id bigint, line_id bigint, share float)');
        foreach (array_chunk($todo, 500) as $chunk) {
            DB::statement("INSERT INTO lt_refcov
                SELECT t.id, r.line_id, ST_Length(ST_Intersection(t.g, ST_Union(ST_Buffer(r.g, 25, 'quad_segs=4')))) / NULLIF(ST_Length(t.g), 0)
                FROM lt_t t JOIN lt_ref r ON ST_DWithin(t.g, r.g, 25)
                WHERE t.id IN (".implode(',', $chunk).')
                GROUP BY t.id, t.g, r.line_id');
        }
        $threshold = (float) $this->option('overlap');
        $n = 0;
        $byName = 0;
        $this->prepareNameIndex();
        foreach (DB::select('SELECT DISTINCT ON (id) id, line_id, share FROM lt_refcov ORDER BY id, share DESC') as $r) {
            $t = $this->traces[$r->id];
            if ($r->share >= $threshold && ! in_array($t['parsed']['gauge'], [600, 700], true)) {
                // Le nom désigne une AUTRE ligne (ses deux extrémités) : rattachement par le nom (étape b).
                if ($this->namedOtherLine($t, (int) $r->line_id)) {
                    $this->reject('recouvrement : le nom désigne une autre ligne');
                    $byName++;

                    continue;
                }
                $this->assign[$r->id] = ['line_id' => (int) $r->line_id, 'method' => 'trace_overlap', 'score' => round($r->share, 3)];
                $n++;
            }
        }
        $this->line("  {$n} tracés rattachés par recouvrement de tracés d'autres sources ({$nRef} tracés de référence), {$byName} laissés à leur nom.");
    }

    // ───────────────────────────── b. noms ─────────────────────────────

    /**
     * Groupes de tracés au nom PROPRE : (calque, nom construit normalisé) ×
     * composante connexe à 5 km.
     *
     * @return array<string, array{key: string, ids: list<int>, network: ?string, layer: string, parsed: array, name: string}>
     */
    private function buildGroups(): array
    {
        $rows = [];
        foreach ($this->traces as $id => $t) {
            $p = $t['parsed'];
            if (! $t['mine'] || isset($this->assign[$id]) || $p['junk'] || $p['generic'] || $p['network_label']) {
                continue;
            }
            $net = $t['network'] ?? 'principal';
            $rows[] = [$id, $t['layer'].'|'.PlaceNames::normalize(PlaceNames::buildName($net, $p))];
        }
        $this->setGroupKeys($rows);
        $clusters = DB::select('SELECT id, gkey, ST_ClusterDBSCAN(g, 5000, 1) OVER (PARTITION BY gkey) AS cid FROM lt_t WHERE gkey IS NOT NULL');

        $groups = [];
        foreach ($clusters as $c) {
            $key = $c->gkey.'#'.$c->cid;
            $t = $this->traces[$c->id];
            $groups[$key] ??= ['key' => $key, 'ids' => [], 'layer' => $t['layer'], 'network' => $t['network'], 'parsed' => $t['parsed']];
            $groups[$key]['ids'][] = (int) $c->id;
        }
        foreach ($groups as &$g) {
            // Libellé représentatif : le plus long tracé du groupe.
            usort($g['ids'], fn ($a, $b) => $this->traces[$b]['len'] <=> $this->traces[$a]['len']);
            $g['parsed'] = $this->traces[$g['ids'][0]]['parsed'];
            $g['name'] = PlaceNames::buildName($g['network'] ?? 'principal', $g['parsed']);
        }
        unset($g);
        $this->line('  '.count($groups).' groupes de tracés nommés à rattacher ou créer.');

        return $groups;
    }

    /** @param  list<array{0: int, 1: string}>  $rows */
    private function setGroupKeys(array $rows): void
    {
        DB::statement('UPDATE lt_t SET gkey = NULL');
        foreach (array_chunk($rows, 500) as $chunk) {
            $values = [];
            $bind = [];
            foreach ($chunk as [$id, $key]) {
                $values[] = '(?::bigint, ?::text)';
                array_push($bind, $id, $key);
            }
            DB::statement('UPDATE lt_t SET gkey = v.k FROM (VALUES '.implode(',', $values).') v(id, k) WHERE lt_t.id = v.id', $bind);
        }
    }

    /** @param  array<string, array<string, mixed>>  $groups */
    private function matchByName(array &$groups): void
    {
        $this->prepareNameIndex();
        $lines = $this->candLines;

        $matched = 0;
        foreach ($groups as $key => &$g) {
            $best = $this->findByName($g['parsed']['places'], $g['ids'], $g['network']);
            if (! $best) {
                continue;
            }
            foreach ($g['ids'] as $id) {
                $this->assign[$id] = ['line_id' => $best[0], 'method' => 'name', 'score' => $best[1]];
            }
            $g['matched'] = $best[0];
            $matched++;
        }
        unset($g);

        // Variante partielle : tracé recouvert en partie par un code SNCF dont le nom ou les gares citent un de ses toponymes.
        $partial = 0;
        $stationWords = $this->stationWordsByLine();
        foreach ($groups as &$g) {
            if (isset($g['matched']) || ($g['network'] ?? null) !== 'principal') {
                continue;
            }
            $best = null;
            foreach ($g['ids'] as $id) {
                foreach ($this->traces[$id]['sncf'] ?? [] as $c) {
                    if ($c['share'] >= 0.2 && (! $best || $c['share'] > $best['share'])) {
                        $best = $c;
                    }
                }
            }
            if (! $best || ! isset($lines[$best['line_id']])) {
                continue;
            }
            // Preuve suffisante : un toponyme du libellé est une EXTRÉMITÉ de la ligne, ou tous ses
            // toponymes (≥ 2) sont des lieux de la ligne (nom, gares), ou recouvrement ≥ 50 %.
            $gPlaces = array_values(array_filter(array_map(fn ($p) => PlaceNames::significant($p), $g['parsed']['places'] ?: [$g['parsed']['clean']])));
            $l = $lines[$best['line_id']];
            $ends = array_merge($l['places'][0], $l['places'][1]);
            $known = array_merge($l['all'], $stationWords[$best['line_id']] ?? []);
            $endHit = (bool) array_filter($gPlaces, fn ($p) => array_intersect($p, $ends));
            $allKnown = count($gPlaces) >= 2 && count(array_filter($gPlaces, fn ($p) => array_intersect($p, $known))) === count($gPlaces);
            if (! $endHit && ! $allKnown && $best['share'] < 0.5) {
                continue;
            }
            if (! $allKnown && $best['share'] < 0.5 && ($far = $this->foreignPlace($g['parsed']['places'], $known, $best['line_id'], $g['ids']))) {
                $this->reject('nom partiel : toponyme étranger à la ligne et éloigné');
                $this->line("  rejet nom partiel : « {$this->traces[$g['ids'][0]]['name']} » ↛ ligne {$best['line_id']} ({$far})", null, 'v');

                continue;
            }
            foreach ($g['ids'] as $id) {
                $this->assign[$id] = ['line_id' => $best['line_id'], 'method' => 'name', 'score' => round($best['share'], 3)];
            }
            $g['matched'] = $best['line_id'];
            $partial++;
        }
        unset($g);
        $this->line("  {$matched} groupes rattachés par nom, {$partial} par nom + recouvrement partiel.");
    }

    /** Index des lignes candidates à la correspondance par nom (une fois par exécution). */
    private function prepareNameIndex(): void
    {
        if ($this->candLines) {
            return;
        }
        $this->candLines = $this->candidateLines();
        foreach ($this->candLines as $lid => $l) {
            foreach ($l['all'] as $w) {
                $this->lineIndex[$w][$lid] = true;
            }
        }
        $this->lineNetworkMap = DB::table('lines')->pluck('network', 'id')->all();
        $this->hasSncf = array_flip(DB::table('lt_sb')->pluck('line_id')->all());
    }

    /**
     * Ligne dont le nom cite les mêmes toponymes que $places (chaque extrémité
     * de la ligne citée, ≥ 2 toponymes du libellé retrouvés), sous garde-fous.
     *
     * @param  list<string>  $places
     * @param  list<int>  $ids
     * @return array{0: int, 1: float}|null
     */
    private function findByName(array $places, array $ids, ?string $network): ?array
    {
        if (count($places) < 2) {
            return null;
        }
        $gPlaces = array_map(fn ($p) => PlaceNames::significant($p), $places);
        $gAll = array_values(array_unique(array_merge(...$gPlaces)));
        $cands = [];
        foreach ($gAll as $w) {
            foreach (array_keys($this->lineIndex[$w] ?? []) as $lid) {
                $cands[$lid] = true;
            }
        }
        $scored = [];
        foreach (array_keys($cands) as $lid) {
            $l = $this->candLines[$lid];
            // Chaque extrémité de la ligne doit être citée par le libellé…
            $endsOk = count(array_filter([$l['places'][0], $l['places'][1]], fn ($e) => array_intersect($e, $gAll))) === 2;
            // …et au moins deux toponymes du libellé doivent se retrouver dans le nom de la ligne.
            $gHit = count(array_filter($gPlaces, fn ($p) => array_intersect($p, $l['all'])));
            if (! $endsOk || $gHit < 2) {
                continue;
            }
            $lHit = count(array_filter($l['places'], fn ($p) => array_intersect($p, $gAll)));
            $scored[$lid] = round(0.5 * $gHit / count($gPlaces) + 0.5 * $lHit / count($l['places']), 3);
        }
        if (! $scored) {
            return null;
        }
        arsort($scored);
        $g = ['ids' => $ids, 'parsed' => ['places' => $places]];
        foreach ($scored as $lid => $score) {
            // Autre catégorie qu'une ligne RFN dont on a le tracé : il faut aussi la recouvrir en partie
            // (« Rennes Guer Redon », voie métrique, n'est pas la ligne RFN de Rennes à Redon).
            if ($network !== null && $network !== ($this->lineNetworkMap[$lid] ?? null) && isset($this->hasSncf[$lid])
                && $this->groupShare($g, $lid) < 0.2) {
                $this->reject('nom : autre catégorie sans recouvrement');

                continue;
            }
            if ($this->spatialGuard($g, $lid)) {
                return [$lid, $score];
            }
            $this->reject('nom : garde-fou spatial');
        }

        return null;
    }

    /**
     * Le libellé du tracé désigne-t-il, par les deux extrémités de son nom,
     * une autre ligne que $lineId (correspondance de l'étape b, garde-fous
     * spatiaux compris) ?
     *
     * @param  array<string, mixed>  $t
     */
    private function namedOtherLine(array $t, int $lineId): bool
    {
        $p = $t['parsed'];
        // « Ligne de Pornic à Paimbœuf: Embranchement de la Plaine à Préfaille » : une partie désignée
        // d'après la ligne principale, pas cette ligne.
        if ($p['junk'] || $p['generic'] || $p['network_label'] || count($p['places']) < 2 || str_contains((string) $t['name'], ':')) {
            return false;
        }
        $hit = $this->findByName($p['places'], [$t['id']], $t['network']);
        if ($hit === null || $hit[0] === $lineId) {
            return false;
        }

        return self::sameQualifiers((string) $t['name'], (string) DB::table('lines')->where('id', $hit[0])->value('name'));
    }

    /**
     * Précisions entre parenthèses compatibles : « Raccordement de Badan (poste 3) à Chasse-sur-Rhône
     * (Poste 3) » n'est pas « Raccordement de Badan (poste 1) à Chasse-sur-Rhône (poste 3) ». Sans
     * précision d'un des deux côtés : compatibles.
     */
    public static function sameQualifiers(string $a, string $b): bool
    {
        $q = function (string $s): array {
            preg_match_all('/\(([^)]*)\)/u', $s, $m);

            return array_map(fn ($x) => PlaceNames::normalize($x), $m[1]);
        };
        [$qa, $qb] = [$q($a), $q($b)];

        return ! $qa || ! $qb || $qa === $qb;
    }

    private function reject(string $why): void
    {
        $this->rejects[$why] = ($this->rejects[$why] ?? 0) + 1;
    }

    /**
     * Meilleure part d'un tracé du groupe recouverte par les tronçons SNCF de la ligne.
     *
     * @param  array<string, mixed>  $g
     */
    private function groupShare(array $g, int $lineId): float
    {
        $best = 0.0;
        foreach ($g['ids'] as $id) {
            foreach ($this->traces[$id]['sncf'] ?? [] as $c) {
                if ($c['line_id'] === $lineId) {
                    $best = max($best, $c['share']);
                }
            }
        }

        return $best;
    }

    /**
     * Lignes candidates à la correspondance par nom (toutes, sauf celles créées
     * par cette commande pour la source en cours, recalculées plus loin).
     *
     * @return array<int, array{places: list<list<string>>, all: list<string>}>
     */
    private function candidateLines(): array
    {
        $out = [];
        $rows = DB::select("SELECT id, name, alt_names FROM lines
            WHERE NOT (COALESCE(alt_names->>'created_by', '') = 'vff:link-traces' AND COALESCE(alt_names->>'source', '') = ?)
              AND COALESCE(alt_names->>'name_source', '') NOT IN ('derived_nearest')", [$this->source]);
        foreach ($rows as $r) {
            $names = [$r->name];
            $alt = json_decode($r->alt_names ?? '{}', true) ?: [];
            foreach (['lignes_oubliees', 'wikipedia', 'wikidata'] as $k) {
                if (is_array($alt[$k] ?? null) && is_string($alt[$k]['name'] ?? null)) {
                    $names[] = $alt[$k]['name'];
                }
            }
            foreach ($names as $name) {
                $parsed = PlaceNames::parseLineName($name);
                if (! $parsed || count($parsed['places']) < 2) {
                    continue;
                }
                $places = array_map(fn ($p) => PlaceNames::significant($p), $parsed['places']);
                if (in_array([], array_slice($places, 0, 2), true)) {
                    continue;
                }
                $out[(int) $r->id] = ['places' => $places, 'all' => array_values(array_unique(array_merge(...$places)))];
                break;
            }
        }

        return $out;
    }

    /** @return array<int, list<string>> mots significatifs des gares de chaque ligne */
    private function stationWordsByLine(): array
    {
        $out = [];
        foreach (DB::select('SELECT l.id, g.libelle, g.commune FROM sncf_gares g JOIN lines l ON l.code = g.code_ligne') as $r) {
            foreach ([$r->libelle, $r->commune] as $n) {
                foreach (PlaceNames::significant($n) as $w) {
                    $out[$r->id][$w] = $w;
                }
            }
        }
        if (DB::getSchemaBuilder()->hasTable('source_stations')) {
            foreach (DB::select('SELECT line_id AS id, name FROM source_stations WHERE line_id IS NOT NULL') as $r) {
                foreach (PlaceNames::significant($r->name) as $w) {
                    $out[$r->id][$w] = $w;
                }
            }
        }

        return array_map('array_values', $out);
    }

    /**
     * Garde-fou spatial d'une correspondance par nom : le groupe doit passer
     * près d'au moins un des lieux qu'il nomme, et près de la ligne si elle a
     * déjà une géométrie.
     *
     * @param  array<string, mixed>  $g
     */
    private function spatialGuard(array $g, int $lineId): bool
    {
        $ids = implode(',', $g['ids']);
        $lineGeom = DB::selectOne("SELECT ST_Distance(l.g, (SELECT ST_Collect(g) FROM lt_t WHERE id IN ({$ids}))) AS d
            FROM lt_lgeom l WHERE l.line_id = ?", [$lineId]);
        if ($lineGeom && $lineGeom->d > 20000) {
            return false;
        }
        $near = $this->placesNear($g['parsed']['places'], $g['ids']);
        if ($near['resolved'] > 0) {
            return $near['near'] > 0 || ($lineGeom && $lineGeom->d < 2000);
        }

        // Aucun toponyme localisable : on n'accepte que si la ligne a une géométrie proche.
        return $lineGeom !== null && $lineGeom->d < 5000;
    }

    /**
     * @param  list<string>  $places
     * @param  list<int>  $ids
     * @return array{resolved: int, near: int}
     */
    private function placesNear(array $places, array $ids, float $radius = 8000): array
    {
        $resolved = 0;
        $near = 0;
        $idList = implode(',', $ids);
        foreach ($places as $place) {
            $alts = array_filter(array_map(
                fn ($p) => PlaceNames::normalize($p),
                array_merge([$place], preg_split('/\s*\/\s*/u', $place)),
            ));
            $alts = array_values(array_unique($alts));
            if (! $alts) {
                continue;
            }
            $conds = [];
            $bind = [];
            foreach ($alts as $a) {
                $conds[] = '(z.norm = ? OR z.norm LIKE ?)';
                array_push($bind, $a, $a.' %');
            }
            $d = DB::selectOne('SELECT MIN(ST_Distance(z.g, t.g)) AS d FROM lt_gaz z,
                (SELECT ST_Collect(g) AS g FROM lt_t WHERE id IN ('.$idList.')) t
                WHERE '.implode(' OR ', $conds), $bind);
            if ($d && $d->d !== null) {
                $resolved++;
                if ($d->d <= $radius) {
                    $near++;
                }
            }
        }

        return ['resolved' => $resolved, 'near' => $near];
    }

    /**
     * Toponyme du libellé étranger à la ligne (ni dans son nom ni dans ses
     * gares), localisé à ≤ 10 km du tracé (sinon homonyme lointain, ignoré) et
     * à plus de 15 km de la géométrie connue de la ligne.
     *
     * @param  list<string>  $places  toponymes bruts du libellé
     * @param  list<string>  $known  mots significatifs connus de la ligne
     * @param  list<int>  $ids  tracés du groupe
     * @return string|null description du toponyme éloigné
     */
    private function foreignPlace(array $places, array $known, int $lineId, array $ids): ?string
    {
        foreach ($places as $place) {
            $w = PlaceNames::significant($place);
            if (! $w || array_intersect($w, $known)) {
                continue;
            }
            $alts = array_values(array_unique(array_filter(array_map(fn ($p) => PlaceNames::normalize($p),
                array_merge([$place], preg_split('/\s*\/\s*/u', $place))))));
            $conds = [];
            $bind = [$lineId];
            foreach ($alts as $a) {
                $conds[] = '(z.norm = ? OR z.norm LIKE ?)';
                array_push($bind, $a, $a.' %');
            }
            if (! $conds) {
                continue;
            }
            $d = DB::selectOne('SELECT MIN(ST_Distance(z.g, l.g)) AS d FROM lt_gaz z, lt_lgeom l,
                    (SELECT ST_Collect(g) AS g FROM lt_t WHERE id IN ('.implode(',', $ids).')) t
                WHERE l.line_id = ? AND ST_DWithin(z.g, t.g, 10000) AND ('.implode(' OR ', $conds).')', $bind)?->d;
            if ($d !== null && $d > 15000) {
                return sprintf('%s à %.0f km', $place, $d / 1000);
            }
        }

        return null;
    }

    // ───────────────────────────── d'. même voie ─────────────────────────────

    /**
     * Tracés particuliers / militaires qui SONT une ligne principale ou
     * secondaire déjà tracée (même voie dessinée dans deux calques) : rattachés
     * à cette ligne (cf. en-tête, étape d').
     */
    private function sameTrack(): void
    {
        $lineNet = DB::table('lines')->pluck('network', 'id')->all();
        $netOf = fn (int $lid) => $lid < 0 ? ($this->planned[$lid]['network'] ?? null) : ($lineNet[$lid] ?? null);
        $public = ['principal', 'secondaire'];
        $periods = app(DatesFromTraceNames::class);
        $dated = 0;
        $cands = [];
        $evidence = [];
        foreach ($this->traces as $id => $t) {
            $a = $this->assign[$id] ?? null;
            $aNet = $a ? $netOf($a['line_id']) : null;
            if ($t['mine'] && in_array($t['layer'], self::SAME_TRACK_LAYERS, true)
                && ! in_array($t['parsed']['gauge'], [600, 700], true) && ! in_array($aNet, $public, true)) {
                // Libellé daté (« 14-18 », « Mil 30-40 ») : usage propre à une période (voie militaire posée ou
                // doublée le long d'une ligne) ; rattaché à la ligne, ses dates la fermeraient à tort
                // (vff:dates-from-trace-names). Gardé comme ligne à part.
                if ($periods->periods((string) $t['name'])) {
                    $dated++;

                    continue;
                }
                $cands[] = $id;
            } elseif ($a && in_array($aNet, $public, true)) {
                $evidence[] = [$id, $a['line_id']];
            } elseif (! $t['mine'] && $t['fixed_line'] && in_array($lineNet[$t['fixed_line']] ?? null, $public, true)) {
                $evidence[] = [$id, $t['fixed_line']];
            }
        }
        if (! $cands) {
            return;
        }
        DB::statement('DROP TABLE IF EXISTS lt_ev');
        DB::statement('CREATE TEMP TABLE lt_ev (line_id bigint, g geometry)');
        foreach (array_chunk($evidence, 1000) as $chunk) {
            $values = [];
            $bind = [];
            foreach ($chunk as [$id, $lid]) {
                $values[] = '(?::bigint, ?::bigint)';
                array_push($bind, $id, $lid);
            }
            DB::statement('INSERT INTO lt_ev SELECT v.lid, t.g FROM (VALUES '.implode(',', $values).') v(id, lid) JOIN lt_t t ON t.id = v.id', $bind);
        }
        DB::statement("INSERT INTO lt_ev SELECT t.line_id, ST_Transform(t.geom, 2154) FROM source_traces t
            JOIN lines l ON l.id = t.line_id AND l.network IN ('principal', 'secondaire')
            WHERE t.source NOT IN (?, 'bdtopo', 'osm') AND COALESCE(t.match_method, '') <> 'trace_overlap'", [$this->source]);
        DB::statement('CREATE INDEX ON lt_ev USING gist (g)');
        DB::statement('ANALYZE lt_ev');

        $best = [];
        foreach (array_chunk($cands, 200) as $chunk) {
            foreach (DB::select("SELECT c.id, e.line_id,
                    ST_Length(ST_Intersection(c.g, ST_Union(ST_Buffer(ST_ClipByBox2D(e.g, ST_Expand(Box2D(c.g), 40)), 20, 'quad_segs=2')))) / NULLIF(ST_Length(c.g), 0) AS s20,
                    ST_Length(ST_Intersection(c.g, ST_Union(ST_Buffer(ST_ClipByBox2D(e.g, ST_Expand(Box2D(c.g), 40)), 5, 'quad_segs=2')))) / NULLIF(ST_Length(c.g), 0) AS s5
                FROM lt_t c JOIN lt_ev e ON e.g && ST_Expand(c.g, 20) AND ST_DWithin(c.g, e.g, 20)
                WHERE c.id IN (".implode(',', $chunk).')
                GROUP BY c.id, c.g, e.line_id') as $r) {
                $b = $best[$r->id] ?? null;
                if (! $b || $r->s20 > $b->s20 || ($r->s20 == $b->s20 && $r->s5 > $b->s5)) {
                    $best[$r->id] = $r;
                }
            }
        }
        $n = 0;
        $km = 0;
        $byLine = [];
        foreach ($best as $id => $r) {
            if ($r->s20 < 0.8 || $r->s5 < 0.5) {
                continue;
            }
            $this->assign[$id] = ['line_id' => (int) $r->line_id, 'method' => 'same_track', 'score' => round((float) $r->s20, 3)];
            $n++;
            $km += $this->traces[$id]['len'] / 1000;
            $byLine[(int) $r->line_id][] = $id;
        }
        // Lignes planifiées qui n'ont plus aucun tracé : pas créées.
        $used = [];
        foreach ($this->assign as $a) {
            $used[$a['line_id']] = true;
        }
        $dropped = 0;
        foreach (array_keys($this->planned) as $pid) {
            if (! isset($used[$pid])) {
                unset($this->planned[$pid]);
                $dropped++;
            }
        }
        $this->line(sprintf('  %d tracés (%.0f km) rattachés à la ligne qu\'ils doublent, %d lignes planifiées devenues vides ; %d tracés datés (période propre) non examinés.', $n, $km, $dropped, $dated));
        if ($this->output->isVerbose()) {
            foreach ($byLine as $lid => $ids) {
                $name = $lid < 0 ? $this->planned[$lid]['name'] : DB::table('lines')->where('id', $lid)->value('name');
                $this->line('    '.$name.' ← '.implode(', ', array_map(fn ($id) => $id.' « '.($this->traces[$id]['name'] ?? '').' »', $ids)));
            }
        }
    }

    // ──────────────────────── d'''. portions sur une autre voie ────────────────────────

    /**
     * Tracés particuliers / militaires (non datés) dont une PORTION continue
     * d'au moins PARTIAL_MIN_RUN_M est la voie d'une ligne principale ou
     * secondaire (≤ 20 m de ses tracés sur toute la portion, et pour au moins
     * la moitié ≤ 5 m d'un tracé de même précision ou ≤ 8 m d'un tracé IGN / OSM) :
     * la portion est découpée et rattachée à cette ligne, le reste du tracé
     * garde son rattachement (morceaux dérivés, cf. étape d''). C'est l'étape
     * d' (même voie) quand la voie n'est commune que sur une partie du tracé :
     * secteur minier qui reprend la ligne de Lens à Corbehem, embranchement qui
     * emprunte 1,7 km de la ligne de Mantes. Libellé daté (« 14-18 ») : usage
     * propre à une période, gardé tel quel (comme à l'étape d').
     */
    private function sameTrackPartial(): void
    {
        $lineNet = DB::table('lines')->pluck('network', 'id')->all();
        $netOf = fn (int $lid) => $lid < 0 ? ($this->planned[$lid]['network'] ?? null) : ($lineNet[$lid] ?? null);
        $public = ['principal', 'secondaire'];
        $periods = app(DatesFromTraceNames::class);
        $cands = [];
        $evidence = [];
        foreach ($this->traces as $id => $t) {
            $a = $this->assign[$id] ?? null;
            $aNet = $a ? $netOf($a['line_id']) : null;
            if ($t['mine'] && $a && in_array($t['layer'], self::SAME_TRACK_LAYERS, true) && $t['len'] >= self::PARTIAL_MIN_RUN_M
                && ! in_array($t['parsed']['gauge'], [600, 700], true) && ! in_array($aNet, $public, true)
                && ! $periods->periods((string) $t['name'])) {
                $cands[] = $id;
            } elseif ($a && in_array($aNet, $public, true)) {
                $evidence[] = [$id, $a['line_id']];
            } elseif (! $t['mine'] && $t['fixed_line'] && in_array($lineNet[$t['fixed_line']] ?? null, $public, true)) {
                $evidence[] = [$id, $t['fixed_line']];
            }
        }
        if (! $cands) {
            return;
        }
        // Preuves : tracés des lignes publiques (cette exécution, autres sources), et voies IGN / OSM rattachées.
        DB::statement('DROP TABLE IF EXISTS lt_evp');
        DB::statement('CREATE TEMP TABLE lt_evp (line_id bigint, precise boolean, g geometry)');
        foreach (array_chunk($evidence, 1000) as $chunk) {
            $values = [];
            $bind = [];
            foreach ($chunk as [$id, $lid]) {
                $values[] = '(?::bigint, ?::bigint)';
                array_push($bind, $id, $lid);
            }
            DB::statement('INSERT INTO lt_evp SELECT v.lid, false, t.g FROM (VALUES '.implode(',', $values).') v(id, lid) JOIN lt_t t ON t.id = v.id', $bind);
        }
        $ids = implode(',', $cands);
        DB::statement('DROP TABLE IF EXISTS lt_cbox');
        DB::statement("CREATE TEMP TABLE lt_cbox AS SELECT id, ST_Transform(ST_Envelope(ST_Expand(g, 30)), 4326) AS box FROM lt_t WHERE id IN ({$ids})");
        DB::statement('CREATE INDEX ON lt_cbox USING gist (box)');
        DB::statement("INSERT INTO lt_evp SELECT t.line_id, t.source IN ('bdtopo', 'osm'), ST_Transform(t.geom, 2154) FROM source_traces t
            JOIN lines l ON l.id = t.line_id AND l.network IN ('principal', 'secondaire')
            WHERE t.source <> ? AND COALESCE(t.match_method, '') <> 'trace_overlap'
              AND (t.source NOT IN ('bdtopo', 'osm') OR t.layer IN ('Voie ferrée principale', 'LGV', 'rail'))
              AND EXISTS (SELECT 1 FROM lt_cbox b WHERE b.box && t.geom)", [$this->source]);
        DB::statement('CREATE INDEX ON lt_evp USING gist (g)');
        DB::statement('ANALYZE lt_evp');

        $runs = [];
        foreach (array_chunk($cands, 100) as $chunk) {
            $rows = DB::select('WITH c AS (SELECT id, ST_LineMerge(g) AS g FROM lt_t WHERE id IN ('.implode(',', $chunk).")),
                    pr AS (SELECT c.id, e.line_id,
                               ST_Union(ST_Buffer(ST_ClipByBox2D(e.g, ST_Expand(Box2D(c.g), 40)), 20, 'quad_segs=2')) AS b20,
                               ST_Union(ST_Buffer(ST_ClipByBox2D(e.g, ST_Expand(Box2D(c.g), 40)), CASE WHEN e.precise THEN 8 ELSE 5 END, 'quad_segs=2')) AS bn
                           FROM c JOIN lt_evp e ON e.g && ST_Expand(c.g, 20) AND ST_DWithin(c.g, e.g, 20)
                           WHERE GeometryType(c.g) = 'LINESTRING'
                           GROUP BY c.id, e.line_id),
                    r AS (SELECT pr.id, pr.line_id, pr.bn, c.g, (ST_Dump(ST_LineMerge(ST_Intersection(c.g, pr.b20)))).geom AS r
                          FROM pr JOIN c ON c.id = pr.id)
                SELECT id, line_id, ST_Length(g) AS len, ST_LineLocatePoint(g, ST_StartPoint(r)) AS f0, ST_LineLocatePoint(g, ST_EndPoint(r)) AS f1,
                       ST_Length(r) AS run_m, ST_Length(ST_Intersection(r, bn)) AS near_m
                FROM r WHERE GeometryType(r) = 'LINESTRING' AND ST_Length(r) >= 50");
            foreach ($rows as $r) {
                [$f0, $f1] = $r->f0 <= $r->f1 ? [(float) $r->f0, (float) $r->f1] : [(float) $r->f1, (float) $r->f0];
                $runs[$r->id]['len'] = (float) $r->len;
                $runs[$r->id]['by'][(int) $r->line_id][] = [$f0, $f1, (float) $r->near_m];
            }
        }

        $n = 0;
        $km = 0.0;
        foreach ($runs as $id => $info) {
            $len = $info['len'];
            $kept = [];
            foreach ($info['by'] as $lid => $parts) {
                usort($parts, fn ($x, $y) => $x[0] <=> $y[0]);
                // Portions séparées de moins de 150 m : une seule (bord de tampon, croisement).
                $merged = [];
                foreach ($parts as $p) {
                    $last = count($merged) - 1;
                    if ($last >= 0 && ($p[0] - $merged[$last][1]) * $len < 150) {
                        $merged[$last][1] = max($merged[$last][1], $p[1]);
                        $merged[$last][2] += $p[2];
                    } else {
                        $merged[] = $p;
                    }
                }
                foreach ($merged as [$f0, $f1, $near]) {
                    $runM = ($f1 - $f0) * $len;
                    if ($runM >= self::PARTIAL_MIN_RUN_M && $near / $runM >= 0.5) {
                        $kept[] = [$f0, $f1, $lid, $near];
                    }
                }
            }
            if (! $kept) {
                continue;
            }
            // Portions de deux lignes qui se chevauchent : la mieux recouverte garde la portion.
            usort($kept, fn ($x, $y) => $y[3] <=> $x[3]);
            $chosen = [];
            foreach ($kept as $k) {
                foreach ($chosen as $c) {
                    if ($k[0] < $c[1] && $k[1] > $c[0]) {
                        continue 2;
                    }
                }
                $chosen[] = $k;
            }
            usort($chosen, fn ($x, $y) => $x[0] <=> $y[0]);
            $pieces = $this->partialPieces($id, $chosen, $len);
            if (! $pieces) {
                continue;
            }
            $desc = implode(' + ', array_map(fn ($p) => sprintf('%s → %s (%.1f km)', $p['from'], $p['line_id'] < 0
                ? ($this->planned[$p['line_id']]['name'] ?? '?').' (à créer)' : $p['line_id'].' « '.($this->lineName($p['line_id'])).' »', $p['len'] / 1000), $pieces));
            $this->splitLog[] = ['trace_id' => $id, 'label' => $this->traces[$id]['name'], 'length_m' => $this->traces[$id]['len'],
                'before' => $this->describeAssign($id), 'chains' => count($chosen), 'decision' => 'découpé (voie commune)', 'detail' => $desc];
            $this->splits[$id] = ['kind' => 'same_track', 'label' => (string) $this->traces[$id]['name'], 'chain' => $desc, 'pieces' => $pieces];
            unset($this->assign[$id]);
            $n++;
            $km += array_sum(array_map(fn ($p) => $p['method'] === 'same_track' ? $p['len'] : 0, $pieces)) / 1000;
        }
        $this->line(sprintf('  %d tracés candidats : %d découpés, %.1f km de portions rattachées à la ligne dont elles empruntent la voie.', count($cands), $n, $km));
        if ($this->output->isVerbose()) {
            foreach ($this->splits as $id => $s) {
                if ($s['kind'] === 'same_track') {
                    $this->line("    {$id} « {$s['label']} » : {$s['chain']}");
                }
            }
        }
    }

    /**
     * Morceaux d'un tracé découpé autour de portions communes (cf.
     * TraceSplitter::piecesAroundRuns), au format des découpes.
     *
     * @param  list<array{0: float, 1: float, 2: int, 3: float}>  $chosen  portions [f0, f1, ligne, m proches], triées
     * @return list<array<string, mixed>>
     */
    private function partialPieces(int $id, array $chosen, float $len): array
    {
        $pieces = [];
        foreach (TraceSplitter::piecesAroundRuns($chosen, $len, $this->assign[$id]['line_id']) as [$f0, $f1, $lid, $same]) {
            $pieces[] = ['line_id' => $lid, 'f0' => round($f0, 6), 'f1' => round($f1, 6),
                'from' => $same ? 'voie commune' : 'reste du tracé', 'to' => '',
                'len' => (int) round(($f1 - $f0) * $len), 'geom' => null, 'method' => $same ? 'same_track' : 'split'];
        }

        return $pieces;
    }

    private function lineName(int $id): string
    {
        return (string) DB::table('lines')->where('id', $id)->value('name');
    }

    // ───────────────────────────── d''. découpe ─────────────────────────────

    /**
     * Tracés « A B » qui mettent 2 ou 3 lignes connues bout à bout : coupés aux
     * jonctions, chaque morceau rattaché à sa ligne (cf. en-tête, étape d'').
     */
    private function splitAcrossLines(): void
    {
        $layers = self::SPLIT_LAYERS[$this->source] ?? [];
        $cands = [];
        foreach ($this->traces as $id => $t) {
            $p = $t['parsed'];
            if (! $t['mine'] || ! in_array($t['layer'], $layers, true) || $t['len'] < self::SPLIT_MIN_TRACE_M
                || count($p['places']) !== 2 || $p['junk'] || $p['generic'] || $p['network_label']
                || in_array($p['gauge'], [600, 700], true) || in_array('projet', $p['flags'], true)) {
                continue;
            }
            $a = $this->assign[$id] ?? null;
            // Déjà UNE ligne : presque entièrement recouvert par elle, ou nom complet (ses deux extrémités).
            if ($a && in_array($a['method'], ['sncf_overlap', 'trace_overlap', 'same_track'], true) && ($a['score'] ?? 0) >= 0.85) {
                continue;
            }
            if ($a && $a['method'] === 'name' && ($a['score'] ?? 0) >= 0.99) {
                continue;
            }
            $cands[] = $id;
        }
        if (! $cands) {
            return;
        }
        [$splitter, $meta] = $this->buildSplitter();
        $this->prepareSplitGazetteer();
        $hasGeom = array_flip(DB::table('lt_lgeom')->pluck('line_id')->all());

        $examined = 0;
        foreach ($cands as $id) {
            $t = $this->traces[$id];
            [$a, $b] = $t['parsed']['places'];
            $chains = $splitter->chains($a, $b);
            if (! $chains) {
                continue;
            }
            $examined++;
            $points = DB::select("SELECT s.name, s.kind, ST_Distance(s.g, m.g) AS d, ST_LineLocatePoint(m.g, s.g) AS f
                FROM (SELECT ST_LineMerge(g) AS g FROM lt_t WHERE id = ?) m
                JOIN lt_stn s ON ST_DWithin(s.g, m.g, ?)
                WHERE GeometryType(m.g) = 'LINESTRING'", [$id, self::SPLIT_COMMUNE_M]);
            $valid = [];
            $why = [];
            foreach ($chains as $chain) {
                $res = $this->validateChain($id, $chain, $points, $meta, $hasGeom);
                if ($res['ok']) {
                    $valid[] = $res;
                } else {
                    $why[$res['why']] = true;
                }
            }
            $log = ['trace_id' => $id, 'label' => $t['name'], 'length_m' => $t['len'],
                'before' => $this->describeAssign($id), 'chains' => count($chains)];
            if (! $valid) {
                $this->splitLog[] = $log + ['decision' => 'rejeté', 'detail' => implode(' ; ', array_keys($why))];

                continue;
            }
            usort($valid, fn ($x, $y) => $y['score'] <=> $x['score']);
            if (count($valid) > 1 && abs($valid[0]['score'] - $valid[1]['score']) < 1e-6
                && $valid[0]['lines'] !== $valid[1]['lines']) {
                $this->splitLog[] = $log + ['decision' => 'ambigu', 'detail' => $valid[0]['chain'].' | '.$valid[1]['chain']];

                continue;
            }
            $best = $valid[0];
            $this->splits[$id] = ['kind' => 'chain', 'label' => (string) $t['name'], 'chain' => $best['chain'], 'pieces' => $best['pieces']];
            unset($this->assign[$id]);
            $this->splitLog[] = $log + ['decision' => 'découpé', 'detail' => $best['chain']];
        }

        // Lignes planifiées qui n'ont plus aucun tracé (ni morceau de tracé découpé) : pas créées.
        $used = [];
        foreach ($this->assign as $a) {
            $used[$a['line_id']] = true;
        }
        foreach ($this->splits as $s) {
            foreach ($s['pieces'] as $p) {
                $used[$p['line_id']] = true;
            }
        }
        $dropped = 0;
        foreach (array_keys($this->planned) as $pid) {
            if (! isset($used[$pid])) {
                unset($this->planned[$pid]);
                $dropped++;
            }
        }
        $chains = array_filter($this->splits, fn ($s) => $s['kind'] === 'chain');
        $pieces = array_sum(array_map(fn ($s) => count($s['pieces']), $chains));
        $this->line(sprintf('  %d tracés candidats, %d avec une chaîne de lignes possible : %d découpés en %d morceaux, %d lignes planifiées devenues vides.',
            count($cands), $examined, count($chains), $pieces, $dropped));
        if ($this->output->isVerbose()) {
            foreach ($chains as $id => $s) {
                $this->line("    {$id} « {$s['label']} » : {$s['chain']}");
            }
        }
    }

    /**
     * Placement d'une chaîne sur le tracé : lieux localisés et ordonnés,
     * longueurs plausibles, morceaux proches de la géométrie connue de leur ligne.
     *
     * @param  list<array{line: int, from: string, to: string, from_end: bool, to_end: bool}>  $chain
     * @param  list<object>  $points  gares / communes à ≤ 5 km du tracé (nom, type, distance, abscisse)
     * @param  array<int, array{name: string, code: ?string, official: ?int}>  $meta
     * @param  array<int, int>  $hasGeom
     * @return array{ok: bool, why?: string, score?: float, lines?: string, chain?: string, pieces?: list<array<string, mixed>>}
     */
    private function validateChain(int $id, array $chain, array $points, array $meta, array $hasGeom): array
    {
        $len = (float) $this->traces[$id]['len'];
        if (! $points) {
            return ['ok' => false, 'why' => 'tracé non linéaire ou aucun lieu à proximité'];
        }
        $places = array_merge([$chain[0]['from']], array_column($chain, 'to'));
        $fractions = [];
        $stationHits = 0;
        foreach ($places as $place) {
            $loc = $this->locatePlace($place, $points);
            if (! $loc) {
                return ['ok' => false, 'why' => "« {$place} » non localisé près du tracé"];
            }
            $fractions[] = $loc['f'];
            $stationHits += $loc['kind'] === 'commune' ? 0 : 1;
        }
        $cut = TraceSplitter::cuts($fractions, $len);
        if (! $cut['ok']) {
            return ['ok' => false, 'why' => $cut['why']];
        }
        $n = count($chain);
        $pieces = [];
        $score = -10.0 * $n + 0.25 * $stationHits;
        foreach ($chain as $k => $leg) {
            // Abscisses dans le sens du tracé (la chaîne part de A, qui peut être au bout du tracé).
            [$f0, $f1] = $cut['reversed'] ? [1 - $cut['cuts'][$k + 1], 1 - $cut['cuts'][$k]] : [$cut['cuts'][$k], $cut['cuts'][$k + 1]];
            $pieceLen = ($f1 - $f0) * $len;
            $m = $meta[$leg['line']] ?? ['name' => '?', 'code' => null, 'official' => null];
            if (! TraceSplitter::plausibleLength($pieceLen, $m['official'], $leg['from_end'] && $leg['to_end'])) {
                return ['ok' => false, 'why' => sprintf('morceau %s - %s : %.1f km pour %.1f km officiels (%s)',
                    $leg['from'], $leg['to'], $pieceLen / 1000, $m['official'] / 1000, $m['name'])];
            }
            // Géométrie connue de la ligne dans le couloir du morceau (2 km de part et d'autre, bouts coupés
            // net : la ligne qui continue au-delà de la jonction ne compte pas). Souvent partielle (forme SNCF
            // du seul tronçon encore au RFN) : là où elle existe, le morceau doit la suivre (≤ 150 m).
            $share = null;
            if (isset($hasGeom[$leg['line']])) {
                $g = DB::selectOne("WITH p AS (SELECT ST_LineSubstring(ST_LineMerge(t.g), ?, ?) AS g FROM lt_t t WHERE t.id = ?),
                        c AS (SELECT p.g, ST_Buffer(p.g, 2000, 'endcap=flat quad_segs=2') AS corr FROM p),
                        n AS (SELECT c.g AS piece, ST_Intersection(ST_ClipByBox2D(l.g, ST_Expand(Box2D(c.g), 2100)), c.corr) AS g
                              FROM c, lt_lgeom l WHERE l.line_id = ?)
                    SELECT ST_Length(n.g) AS near_m, ST_Length(ST_Intersection(n.g, ST_Buffer(n.piece, 150, 'quad_segs=2'))) AS agree_m
                    FROM n", [$f0, $f1, $id, $leg['line']]);
                if ($g && $g->near_m >= 1000) {
                    $share = $g->agree_m / $g->near_m;
                    if ($share < self::SPLIT_GEOM_SHARE) {
                        return ['ok' => false, 'why' => sprintf('morceau %s - %s loin de la géométrie connue de %s (%.0f %% de %.1f km à ≤ 150 m)',
                            $leg['from'], $leg['to'], $m['name'], 100 * $share, $g->near_m / 1000)];
                    }
                    $score += 1;
                }
            }
            $score += $m['code'] ? 0.5 : 0;
            $pieces[] = ['line_id' => $leg['line'], 'f0' => round($f0, 6), 'f1' => round($f1, 6), 'from' => $leg['from'], 'to' => $leg['to'],
                'len' => (int) round($pieceLen), 'geom' => $share === null ? null : round($share, 3), 'method' => 'split'];
        }
        $desc = implode(' + ', array_map(fn ($p) => sprintf('%s - %s → %d « %s » (%.1f km)', $p['from'], $p['to'], $p['line_id'],
            $meta[$p['line_id']]['name'] ?? '?', $p['len'] / 1000), $pieces));

        return ['ok' => true, 'score' => $score, 'lines' => implode('>', array_column($chain, 'line')), 'chain' => $desc, 'pieces' => $pieces];
    }

    /**
     * Lieu de la chaîne sur le tracé : gare du même nom à ≤ 2,5 km, sinon commune à ≤ 5 km (la plus proche).
     *
     * @param  list<object>  $points
     * @return array{f: float, d: float, kind: string}|null
     */
    private function locatePlace(string $place, array $points): ?array
    {
        $best = null;
        foreach ($points as $p) {
            $isCommune = $p->kind === 'commune';
            if ($p->d > ($isCommune ? self::SPLIT_COMMUNE_M : self::SPLIT_STATION_M) || ! TraceSplitter::samePlace($place, $p->name)) {
                continue;
            }
            $rank = [$isCommune ? 1 : 0, (float) $p->d];
            if (! $best || $rank < $best['rank']) {
                $best = ['f' => (float) $p->f, 'd' => (float) $p->d, 'kind' => $p->kind, 'rank' => $rank];
            }
        }

        return $best;
    }

    /**
     * Extrémités et gares de chaque ligne candidate (toutes sauf celles créées
     * ici pour la source en cours, recalculées) : nom de la ligne, gares
     * Wikipédia, lignes-oubliees et SNCF de son code.
     *
     * @return array{0: TraceSplitter, 1: array<int, array{name: string, code: ?string, official: ?int}>}
     */
    private function buildSplitter(): array
    {
        $lines = [];
        $meta = [];
        $rows = DB::select("SELECT id, name, code, length_official_m, alt_names FROM lines
            WHERE NOT (COALESCE(alt_names->>'created_by', '') = 'vff:link-traces' AND COALESCE(alt_names->>'source', '') = ?)
              AND COALESCE(alt_names->>'name_source', '') NOT IN ('derived', 'derived_nearest')", [$this->source]);
        foreach ($rows as $r) {
            $names = [$r->name];
            $alt = json_decode($r->alt_names ?? '{}', true) ?: [];
            foreach (['lignes_oubliees', 'wikipedia', 'wikidata'] as $k) {
                if (is_array($alt[$k] ?? null) && is_string($alt[$k]['name'] ?? null)) {
                    $names[] = $alt[$k]['name'];
                }
            }
            foreach ($names as $name) {
                $parsed = PlaceNames::parseLineName($name);
                if ($parsed && count($parsed['places']) >= 2) {
                    $lines[(int) $r->id] = ['ends' => array_slice($parsed['places'], 0, 2), 'stations' => array_slice($parsed['places'], 2)];
                    $meta[(int) $r->id] = ['name' => $r->name, 'code' => $r->code, 'official' => $r->length_official_m ? (int) $r->length_official_m : null];
                    break;
                }
            }
        }
        $stations = DB::select('SELECT line_id, name FROM wikipedia_line_stations
            UNION SELECT line_id, name FROM source_stations WHERE line_id IS NOT NULL
            UNION SELECT l.id, g.libelle FROM sncf_gares g JOIN lines l ON l.code = g.code_ligne WHERE g.libelle IS NOT NULL');
        foreach ($stations as $s) {
            if (isset($lines[$s->line_id])) {
                $lines[$s->line_id]['stations'][] = $s->name;
            }
        }

        return [new TraceSplitter($lines), $meta];
    }

    /** Gares (VFF, SNCF) et communes en Lambert-93, pour localiser les lieux d'une chaîne. */
    private function prepareSplitGazetteer(): void
    {
        DB::statement('DROP TABLE IF EXISTS lt_stn');
        DB::statement("CREATE TEMP TABLE lt_stn AS
            SELECT name, 'station'::text AS kind, ST_Transform(geom, 2154) AS g FROM stations
            UNION ALL
            SELECT DISTINCT libelle, 'gare', ST_Transform(ST_SetSRID(ST_MakePoint(lon, lat), 4326), 2154)
            FROM sncf_gares WHERE lat IS NOT NULL AND libelle IS NOT NULL
            UNION ALL
            SELECT nom, 'commune', ST_Transform(geom, 2154) FROM communes");
        DB::statement('CREATE INDEX ON lt_stn USING gist (g)');
        DB::statement('ANALYZE lt_stn');
    }

    /** Rattachement courant d'un tracé, pour les rapports. */
    private function describeAssign(int $id): string
    {
        $a = $this->assign[$id] ?? null;
        if (! $a) {
            return '';
        }
        $name = $a['line_id'] < 0 ? ($this->planned[$a['line_id']]['name'] ?? '?').' (à créer)'
            : (string) DB::table('lines')->where('id', $a['line_id'])->value('name');

        return "{$a['method']} → {$a['line_id']} « {$name} »";
    }

    // ───────────────────────────── c. création ─────────────────────────────

    /** @param  array<string, array<string, mixed>>  $groups */
    /** Suffixe technique d'export de la carte source (« Le Cheylard Vals_ivPw8 ») : pas un nom de lieu. */
    public static function cleanSourceName(?string $name): ?string
    {
        return $name === null ? null : preg_replace('/_[A-Za-z0-9]{4,6}$/u', '', $name);
    }

    private function planNamedLines(array $groups): void
    {
        $n = 0;
        foreach ($groups as $g) {
            if (isset($g['matched']) || ! $g['network'] || in_array($g['layer'], self::NO_CREATE_LAYERS, true)) {
                continue;
            }
            $flags = $g['parsed']['flags'];
            if (in_array('projet', $flags, true)) {
                continue; // projet jamais réalisé : pas une ligne du réseau
            }
            // « Tracé initial » d'une ligne non retrouvée par son nom : traité comme générique (contact d'abord).
            if (in_array('trace_initial', $flags, true)) {
                foreach ($g['ids'] as $id) {
                    $this->traces[$id]['as_generic'] = true;
                }

                continue;
            }
            $curated = self::HOMONYMS[$g['layer'].'|'.PlaceNames::normalize($this->traces[$g['ids'][0]]['name'] ?? '')] ?? null;
            $pid = $this->plan($g['ids'], $g['network'], $curated['name'] ?? $g['name'], $curated ? 'curated' : $this->source, $g['key'],
                $curated ? ['curated' => $curated] : []);
            if ($curated && $curated['gauge']) {
                $this->planned[$pid]['gauge'] = $curated['gauge'];
            }
            $n++;
        }
        $this->line("  {$n} lignes nommées à créer.");
    }

    /**
     * Réseaux décrits par un seul libellé (bassin minier, front belge…) :
     * composantes connexes, puis secteurs de ~40 km pour les plus grandes.
     */
    private function planNetworkLabels(): void
    {
        $rows = [];
        foreach ($this->traces as $id => $t) {
            if ($t['mine'] && ! isset($this->assign[$id]) && $t['parsed']['network_label'] && $t['network']
                && ! in_array($t['layer'], self::NO_CREATE_LAYERS, true)) {
                $rows[] = [$id, $t['layer'].'|'.PlaceNames::normalize($t['parsed']['clean'])];
            }
        }
        if (! $rows) {
            return;
        }
        $groups = $this->sectorize($rows);
        foreach ($groups as $key => $ids) {
            $t = $this->traces[$ids[0]];
            $places = $this->sectorPlaces($ids);
            $namer = fn (string $place) => PlaceNames::derivedName($t['network'], $t['parsed'], $place);
            $this->plan($ids, $t['network'], $namer($places[0] ?? 'France'), 'derived', $key,
                ['alt_places' => $places, 'namer' => $namer]);
        }
        $this->line('  '.count($groups).' secteurs de réseaux (libellé collectif) à créer.');
    }

    /**
     * Composantes connexes (extrémités à ≤ --touch m) par clé, puis découpage
     * des composantes de plus de 60 km en secteurs compacts de ~40 km
     * (k-moyennes) : un réseau minier de 600 km n'est pas « une ligne ».
     *
     * @param  list<array{0: int, 1: string}>  $rows  [id tracé, clé de regroupement]
     * @return array<string, list<int>>
     */
    private function sectorize(array $rows): array
    {
        $this->setGroupKeys($rows);
        DB::statement('DROP TABLE IF EXISTS lt_comp');
        DB::statement('CREATE TEMP TABLE lt_comp AS
            SELECT id, gkey, ST_ClusterDBSCAN(g, ?, 1) OVER (PARTITION BY gkey) AS cid, g FROM lt_t WHERE gkey IS NOT NULL',
            [(float) $this->option('touch')]);
        // ORDER BY id : k-moyennes déterministes (sinon l'ordre physique des lignes, qui change à chaque
        // mise à jour, déplacerait les secteurs et recréerait des lignes à chaque exécution).
        $sectors = DB::select('WITH comp AS (SELECT gkey, cid, SUM(ST_Length(g)) AS len, COUNT(*) AS n FROM lt_comp GROUP BY gkey, cid)
            SELECT c.id, c.gkey, c.cid,
                   CASE WHEN comp.len > 60000 AND comp.n > 1
                        THEN ST_ClusterKMeans(ST_Centroid(c.g), LEAST(CEIL(comp.len / 40000)::int, comp.n::int)) OVER (PARTITION BY c.gkey, c.cid ORDER BY c.id)
                        ELSE 0 END AS sid
            FROM lt_comp c JOIN comp USING (gkey, cid) ORDER BY c.id');
        $groups = [];
        foreach ($sectors as $r) {
            $groups[$r->gkey.'#'.$r->cid.'.'.$r->sid][] = (int) $r->id;
        }

        return $groups;
    }

    /**
     * Communes candidates pour nommer un secteur : les plus peuplées à moins de
     * 2 km des tracés, puis la plus proche du centre.
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function sectorPlaces(array $ids): array
    {
        $idList = implode(',', $ids);
        $rows = DB::select("WITH s AS (SELECT ST_Collect(g) AS g FROM lt_t WHERE id IN ({$idList}))
            (SELECT z.nom FROM lt_gaz z, s WHERE z.kind = 'commune-actuelle' AND ST_DWithin(z.g, s.g, 2000)
             ORDER BY z.pop DESC NULLS LAST LIMIT 6)
            UNION ALL
            (SELECT z.nom FROM lt_gaz z, s WHERE z.kind = 'commune-actuelle' ORDER BY z.g <-> ST_Centroid(s.g) LIMIT 3)");

        return array_values(array_unique(array_map(fn ($r) => $r->nom, $rows)));
    }

    /**
     * Commune la plus proche du milieu d'un ensemble de tracés.
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function nearestPlaces(array $ids): array
    {
        $idList = implode(',', $ids);
        $rows = DB::select("WITH s AS (SELECT ST_Collect(g) AS g FROM lt_t WHERE id IN ({$idList})),
                m AS (SELECT ST_ClosestPoint(s.g, ST_Centroid(s.g)) AS p FROM s)
            SELECT z.nom FROM lt_gaz z, m WHERE z.kind = 'commune-actuelle' ORDER BY z.g <-> m.p LIMIT 4");

        return array_map(fn ($r) => $r->nom, $rows);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $extra
     */
    private function plan(array $ids, string $network, string $name, string $nameSource, string $key, array $extra = []): int
    {
        $pid = $this->nextPlanned--;
        $gauges = array_unique(array_filter(array_map(fn ($id) => $this->traces[$id]['parsed']['gauge'], $ids)));
        $labels = array_values(array_unique(array_filter(array_map(fn ($id) => $this->traces[$id]['name'], $ids))));
        $flags = array_values(array_unique(array_merge(...array_map(fn ($id) => array_filter(
            $this->traces[$id]['parsed']['flags'], fn ($f) => ! str_starts_with($f, 'reseau:') && $f !== 'liste'), $ids))));
        $anchor = min(array_map(fn ($id) => $this->traces[$id]['ref'], $ids));
        $len = max(1, array_sum(array_map(fn ($id) => $this->traces[$id]['len'], $ids)));
        $fr = array_sum(array_map(fn ($id) => ($this->traces[$id]['fr'] ?? 1.0) * $this->traces[$id]['len'], $ids)) / $len;
        if ($fr < 0.5) {
            $flags[] = 'hors_france'; // ligne surtout à l'étranger (contexte frontalier)
        }
        $this->planned[$pid] = [
            'name' => $name, 'network' => $network, 'name_source' => $nameSource,
            'gauge' => count($gauges) === 1 ? (int) reset($gauges) : null,
            'labels' => array_slice($labels, 0, 10), 'flags' => $flags,
            'group_key' => $this->source.':'.preg_replace('/#.*$/', '', $key).'@'.$anchor,
            'ids' => $ids,
        ] + $extra;
        foreach ($ids as $id) {
            $this->assign[$id] = ['line_id' => $pid, 'method' => 'created', 'score' => null];
        }

        return $pid;
    }

    // ───────────────────────────── d. contact ─────────────────────────────

    private function touching(): void
    {
        $todo = [];
        foreach ($this->traces as $id => $t) {
            $p = $t['parsed'];
            if ($t['mine'] && ! isset($this->assign[$id]) && $t['network']
                && ($p['junk'] || $p['generic'] || ($t['as_generic'] ?? false))
                && ! in_array('projet', $p['flags'], true)) {
                $todo[$id] = true;
            }
        }
        if (! $todo) {
            return;
        }
        $touch = (float) $this->option('touch');

        // Lignes et catégories des voisins possibles : tracés de la source (état courant) + tronçons SNCF.
        DB::statement('DROP TABLE IF EXISTS lt_nb, lt_cand');
        DB::statement('CREATE TEMP TABLE lt_nb (id bigint, line_id bigint, network text, g geometry)');
        DB::statement('INSERT INTO lt_nb SELECT NULL, l.id, l.network, ST_Transform(s.geom, 2154)
            FROM sncf_line_geometries s JOIN lines l ON l.code = s.code_ligne');
        DB::statement('CREATE INDEX ON lt_nb USING gist (g)');
        DB::statement('CREATE TEMP TABLE lt_cand AS SELECT id, ROW_NUMBER() OVER () AS pid, p FROM (
            SELECT t.id, (ST_Dump(ST_Boundary(t.g))).geom AS p FROM lt_t t WHERE t.id IN ('.implode(',', array_keys($todo)).')) x');
        // Extrémités des lignes RFN (tronçons SNCF fusionnés) : un tracé qui en part prolonge la ligne.
        DB::statement('DROP TABLE IF EXISTS lt_sncfend, lt_lend');
        DB::statement('CREATE TEMP TABLE lt_sncfend AS SELECT line_id, (ST_Dump(ST_Boundary(ST_LineMerge(ST_Collect(g))))).geom AS p
            FROM lt_nb WHERE id IS NULL GROUP BY line_id');
        DB::statement('CREATE INDEX ON lt_cand USING gist (p)');

        $lineNetwork = DB::table('lines')->pluck('network', 'id')->all();
        $total = 0;
        for ($pass = 1; $pass <= 30; $pass++) {
            // Rafraîchit les voisins issus de la source (assignations courantes, y compris lignes planifiées).
            DB::statement('DELETE FROM lt_nb WHERE id IS NOT NULL');
            $rows = [];
            foreach ($this->assign as $id => $a) {
                $net = $a['line_id'] < 0 ? $this->planned[$a['line_id']]['network'] : ($lineNetwork[$a['line_id']] ?? null);
                $rows[] = [$id, $a['line_id'], $net];
            }
            foreach ($this->traces as $id => $t) {
                if (! $t['mine'] && $t['fixed_line']) {
                    $rows[] = [$id, $t['fixed_line'], $lineNetwork[$t['fixed_line']] ?? null];
                }
            }
            foreach (array_chunk($rows, 1000) as $chunk) {
                $values = [];
                $bind = [];
                foreach ($chunk as [$id, $lid, $net]) {
                    $values[] = '(?::bigint, ?::bigint, ?::text)';
                    array_push($bind, $id, $lid, $net);
                }
                DB::statement('INSERT INTO lt_nb SELECT v.id, v.lid, v.net, t.g FROM (VALUES '.implode(',', $values).') v(id, lid, net)
                    JOIN lt_t t ON t.id = v.id', $bind);
            }

            $open = array_keys(array_filter($todo, fn ($v, $id) => ! isset($this->assign[$id]), ARRAY_FILTER_USE_BOTH));
            if (! $open) {
                break;
            }
            // Extrémités des lignes : celles de leurs tronçons SNCF fusionnés et de leurs tracés fusionnés.
            DB::statement('DROP TABLE IF EXISTS lt_lend');
            DB::statement('CREATE TEMP TABLE lt_lend AS SELECT line_id, p FROM lt_sncfend
                UNION ALL SELECT line_id, (ST_Dump(ST_Boundary(ST_LineMerge(ST_Collect(g))))).geom FROM lt_nb
                WHERE id IS NOT NULL GROUP BY line_id');
            DB::statement('CREATE INDEX ON lt_lend USING gist (p)');
            // ends = extrémités du tracé touchant la ligne ; cont = extrémités posées sur une EXTRÉMITÉ de la
            // ligne, seule ligne de cette catégorie à cet endroit (prolongement, pas embranchement).
            $hits = DB::select('WITH h AS (
                    SELECT c.id, c.pid, nb.line_id, nb.network, MIN(ST_Distance(c.p, nb.g)) AS d,
                           BOOL_OR(EXISTS (SELECT 1 FROM lt_lend e WHERE e.line_id = nb.line_id AND ST_DWithin(e.p, c.p, ?))) AS at_end
                    FROM lt_cand c JOIN lt_nb nb ON ST_DWithin(c.p, nb.g, ?) AND (nb.id IS NULL OR nb.id <> c.id)
                    WHERE c.id IN ('.implode(',', $open).')
                    GROUP BY c.id, c.pid, nb.line_id, nb.network),
                e AS (SELECT h.*, COUNT(*) OVER (PARTITION BY h.id, h.pid, h.network) AS nlines FROM h)
                SELECT id, line_id, network, COUNT(*) AS ends, MIN(d) AS d,
                       COUNT(*) FILTER (WHERE at_end AND nlines = 1) AS cont
                FROM e GROUP BY id, line_id, network
                ORDER BY id, ends DESC, cont DESC, d', [$touch, $touch]);
            $new = 0;
            $seen = [];
            foreach ($hits as $h) {
                if (isset($seen[$h->id])) {
                    continue;
                }
                $t = $this->traces[$h->id];
                if ($h->network !== $t['network']) {
                    continue; // une autre catégorie : on tente la ligne suivante de même catégorie
                }
                if (in_array($t['layer'], self::NO_CREATE_LAYERS, true) && ($h->ends < 2 || $t['len'] > 3000)) {
                    continue; // calque de contexte : seulement les courts raccordements tenus par les deux bouts
                }
                // Un long tracé SANS NOM accroché par un seul bout est une autre ligne (embranchement),
                // pas un morceau de sa voisine : il sera nommé d'après ses extrémités.
                // Exception : il PROLONGE la ligne (posé sur son extrémité, sans autre ligne à cet endroit).
                if ($t['parsed']['junk'] && ! ($t['as_generic'] ?? false) && $h->cont < 1
                    && ($t['len'] > 20000 || ($t['len'] > 3000 && $h->ends < 2))) {
                    continue;
                }
                $seen[$h->id] = true;
                $this->assign[$h->id] = ['line_id' => (int) $h->line_id, 'method' => 'touching', 'score' => min(1.0, $h->ends / 2)];
                $new++;
            }
            $total += $new;
            if (! $new) {
                break;
            }
        }
        $this->line("  {$total} tracés rattachés par contact.");
    }

    /**
     * Tracés restants sans ligne : composantes connexes par calque (sectorisées
     * au-delà de 60 km). Nom : libellé propre d'un ancien tracé, sinon libellé
     * générique + commune (« Embranchement militaire de Metzervisse »), sinon,
     * pour un tracé anonyme de plus de 3 km, ses extrémités (« Ligne de A à
     * B », d'abord recherchée parmi les lignes existantes), sinon « … près de
     * <commune> ».
     */
    private function planLeftovers(): void
    {
        $rows = [];
        foreach ($this->traces as $id => $t) {
            $p = $t['parsed'];
            if ($t['mine'] && ! isset($this->assign[$id]) && $t['network']
                && ! in_array($t['layer'], self::NO_CREATE_LAYERS, true)
                && ! in_array('projet', $p['flags'], true)) {
                $rows[] = [$id, $t['layer']];
            }
        }
        if (! $rows) {
            return;
        }
        $groups = $this->sectorize($rows);
        $byEnds = 0;
        $matched = 0;
        foreach ($groups as $key => $ids) {
            usort($ids, fn ($a, $b) => $this->traces[$b]['len'] <=> $this->traces[$a]['len']);
            $parsed = null;
            foreach ($ids as $id) {
                if (! $this->traces[$id]['parsed']['junk']) {
                    $parsed = $this->traces[$id]['parsed'];
                    break;
                }
            }
            $network = $this->traces[$ids[0]]['network'];
            $len = array_sum(array_map(fn ($id) => $this->traces[$id]['len'], $ids));
            $places = $this->nearestPlaces($ids);

            if ($parsed && ! $parsed['generic'] && ! $parsed['network_label']) {
                // Libellé propre d'un ancien tracé dont la ligne n'a été retrouvée ni par nom ni par contact.
                $name = PlaceNames::buildName($network, $parsed)
                    .(in_array('trace_initial', $parsed['flags'], true) ? ' (ancien tracé)' : '');
                $this->plan($ids, $network, $name, $this->source, 'named|'.$key, ['alt_places' => $places]);

                continue;
            }
            if (! $parsed && $len > 3000 && ($ends = $this->endPlaces($ids)) && $ends[0] !== $ends[1]) {
                // Tracé anonyme : nommé d'après ses extrémités, puis cherché parmi les lignes existantes.
                $hit = $this->findByName($ends, $ids, $network);
                if ($hit) {
                    foreach ($ids as $id) {
                        $this->assign[$id] = ['line_id' => $hit[0], 'method' => 'name', 'score' => $hit[1]];
                    }
                    $matched++;

                    continue;
                }
                $prefix = match ($network) {
                    'militaire' => 'Ligne militaire', 'particulier' => 'Voie industrielle', default => 'Ligne',
                };
                $name = PlaceNames::lineName($prefix, $ends);
                $this->plan($ids, $network, $name, 'derived', 'ends|'.$key, ['alt_places' => $places]);
                $byEnds++;

                continue;
            }
            $namer = fn (string $place) => PlaceNames::derivedName($network, $parsed, $place, $len);
            $this->plan($ids, $network, $namer($places[0] ?? 'France'), 'derived', 'derived|'.$key,
                ['alt_places' => $places, 'namer' => $namer]);
        }
        $this->line('  '.count($groups).' groupes restants : '.$matched.' rattachés par le nom de leurs extrémités, '
            .$byEnds.' lignes « de A à B » et '.(count($groups) - $matched - $byEnds).' lignes « près de » à créer.');
    }

    /**
     * Lignes EXPLOITÉES hors RFN (calque « exploitee » sans tronçon SNCF) :
     * CFC en Corse, Chemins de fer de Provence, ligne de Sceaux, T4 ex-ligne
     * des Coquetiers… Le calque ne crée pas de doublon des lignes RFN (elles
     * ont été rattachées par recouvrement), mais ces lignes-là n'existent
     * nulle part ailleurs : on les crée si elles sont surtout en France.
     * Nom : ligne existante de mêmes extrémités, sinon item Wikidata dont ≥ 2
     * points (terminus, gares) sont à ≤ 1,5 km du tracé, sinon « de A à B ».
     */
    private function planExploited(): void
    {
        $rows = [];
        foreach ($this->traces as $id => $t) {
            if ($t['mine'] && ! isset($this->assign[$id]) && in_array($t['layer'], self::NO_CREATE_LAYERS, true)) {
                $rows[] = [$id, $t['layer']];
            }
        }
        if (! $rows) {
            return;
        }
        $hasWd = DB::getSchemaBuilder()->hasTable('wikidata_lines');
        $created = 0;
        $named = 0;
        $abroad = 0;
        $byQid = [];
        foreach ($this->sectorize($rows) as $key => $ids) {
            $len = max(1, array_sum(array_map(fn ($id) => $this->traces[$id]['len'], $ids)));
            $fr = array_sum(array_map(fn ($id) => ($this->traces[$id]['fr'] ?? 1.0) * $this->traces[$id]['len'], $ids)) / $len;
            if ($fr < 0.5 || $len < 1000) {
                $abroad++;

                continue; // ligne étrangère (Belgique, Luxembourg…) ou bout de voie : contexte seulement
            }
            $ends = $this->endPlaces($ids);
            if ($ends && $ends[0] !== $ends[1] && ($hit = $this->findByName($ends, $ids, 'principal'))) {
                foreach ($ids as $id) {
                    $this->assign[$id] = ['line_id' => $hit[0], 'method' => 'name', 'score' => $hit[1]];
                }
                $named++;

                continue;
            }
            $extra = [];
            $name = null;
            if ($hasWd) {
                $idList = implode(',', $ids);
                $wd = DB::selectOne("WITH s AS (SELECT ST_Collect(g) AS g FROM lt_t WHERE id IN ({$idList}))
                    SELECT w.qid, w.label, w.codes, COUNT(*) AS n FROM wikidata_lines w,
                        LATERAL ST_Dump(ST_Transform(w.points, 2154)) d, s
                    WHERE w.line_id IS NULL AND w.label IS NOT NULL AND w.points IS NOT NULL AND ST_DWithin(d.geom, s.g, 1500)
                    GROUP BY w.qid, w.label, w.codes HAVING COUNT(*) >= 2 ORDER BY COUNT(*) DESC LIMIT 1");
                if ($wd) {
                    $name = PlaceNames::ucfirst($wd->label);
                    $codes = array_values(array_unique(array_filter(array_map(
                        fn ($c) => preg_match('/^\d{6}$/', (string) ($c['code'] ?? '')) ? $c['code'] : null,
                        json_decode($wd->codes ?? '[]', true) ?: [],
                    ))));
                    $extra = ['wikidata' => $wd->qid]
                        + (count($codes) === 1 && ! DB::table('lines')->where('code', $codes[0])->exists() ? ['code' => $codes[0]] : []);
                }
            }
            if (isset($extra['wikidata'], $byQid[$extra['wikidata']])) {
                // Deux morceaux du même item Wikidata : une seule ligne.
                $pid = $byQid[$extra['wikidata']];
                $this->planned[$pid]['ids'] = array_merge($this->planned[$pid]['ids'], $ids);
                foreach ($ids as $id) {
                    $this->assign[$id] = ['line_id' => $pid, 'method' => 'created', 'score' => null];
                }

                continue;
            }
            if (! $name && $ends && $ends[0] !== $ends[1]) {
                $name = PlaceNames::lineName('Ligne', $ends);
            }
            $name ??= 'Ligne près '.PlaceNames::de($this->nearestPlaces($ids)[0] ?? 'France');
            $pid = $this->plan($ids, 'principal', $name, $extra ? 'wikidata' : 'derived', 'exploitee|'.$key,
                $extra + ['alt_places' => $this->nearestPlaces($ids)]);
            $this->planned[$pid]['flags'][] = 'exploitee_hors_rfn';
            if (isset($extra['wikidata'])) {
                $byQid[$extra['wikidata']] = $pid;
            }
            $created++;
        }
        $this->line("  Lignes exploitées hors RFN : {$created} à créer, {$named} retrouvées par nom, {$abroad} ensembles étrangers ou résiduels laissés en contexte.");
    }

    /**
     * Lieux des deux extrémités les plus éloignées d'un ensemble de tracés :
     * gare SNCF à moins de 1 km, sinon commune (actuelle ou ancienne) la plus proche.
     *
     * @param  list<int>  $ids
     * @return list<string>|null
     */
    private function endPlaces(array $ids): ?array
    {
        $idList = implode(',', $ids);
        $pair = DB::selectOne("WITH s AS (SELECT ST_LineMerge(ST_Collect(g)) AS g FROM lt_t WHERE id IN ({$idList})),
                b AS (SELECT (ST_Dump(ST_Boundary(s.g))).geom AS p FROM s)
            SELECT ST_AsEWKT(b1.p) AS p1, ST_AsEWKT(b2.p) AS p2 FROM b b1, b b2
            ORDER BY ST_Distance(b1.p, b2.p) DESC LIMIT 1");
        if (! $pair) {
            return null;
        }
        $names = [];
        foreach ([$pair->p1, $pair->p2] as $p) {
            $r = DB::selectOne("SELECT nom FROM (
                    (SELECT nom, 0 AS rank, g FROM lt_gaz WHERE kind = 'gare' AND ST_DWithin(g, ST_GeomFromEWKT(?), 1000)
                     ORDER BY g <-> ST_GeomFromEWKT(?) LIMIT 1)
                    UNION ALL
                    (SELECT nom, 1, g FROM lt_gaz WHERE kind <> 'gare' ORDER BY g <-> ST_GeomFromEWKT(?) LIMIT 1)
                ) x ORDER BY rank LIMIT 1", [$p, $p, $p]);
            if (! $r) {
                return null;
            }
            $names[] = $r->nom;
        }

        return $names;
    }

    /**
     * Noms uniques : un même nom en plusieurs endroits est désambiguïsé par une
     * autre commune du secteur, ou suffixé « (secteur de X) », puis numéroté.
     */
    private function uniquifyPlannedNames(): void
    {
        $used = [];
        foreach (DB::select("SELECT name FROM lines WHERE NOT (COALESCE(alt_names->>'created_by', '') = 'vff:link-traces' AND COALESCE(alt_names->>'source', '') = ?)", [$this->source]) as $r) {
            $used[mb_strtolower($r->name)] = true;
        }
        $byName = [];
        foreach ($this->planned as $pid => $p) {
            $byName[mb_strtolower($p['name'])][] = $pid;
        }
        foreach ($byName as $lname => $pids) {
            if (count($pids) === 1 && ! isset($used[$lname])) {
                $used[$lname] = true;

                continue;
            }
            foreach ($pids as $pid) {
                $base = $this->planned[$pid]['name'];
                $places = $this->planned[$pid]['alt_places'] ?? $this->nearestPlaces($this->planned[$pid]['ids']);
                $namer = $this->planned[$pid]['namer']
                    ?? fn (string $place) => $base.' (secteur '.PlaceNames::de(PlaceNames::placeName($place)).')';
                $name = null;
                foreach (array_merge($places, $this->nearestPlaces($this->planned[$pid]['ids'])) as $place) {
                    $cand = $namer($place);
                    if (! isset($used[mb_strtolower($cand)])) {
                        $name = $cand;
                        break;
                    }
                }
                for ($i = 2; $name === null; $i++) {
                    $cand = $base.' ('.$i.')';
                    $name = isset($used[mb_strtolower($cand)]) ? null : $cand;
                }
                $this->planned[$pid]['name'] = $name;
                $used[mb_strtolower($name)] = true;
            }
        }
    }

    /**
     * Code RFN des lignes créées, d'après Wikidata (table wikidata_lines) :
     * « Carhaix Morlaix » (archeo, secondaire) = Q3239713 « ligne de Morlaix à
     * Carhaix », code 483000. Avec ce code, Wikidata et lignes-oubliees
     * peuvent rattacher leurs données à la ligne créée.
     *
     * Conditions : item Wikidata non rattaché, un seul code à 6 chiffres
     * inconnu de VFF ; mêmes extrémités dans les deux noms ; un point Wikidata
     * (gare, terminus) à ≤ 3 km du tracé, ou à défaut un toponyme du nom à
     * ≤ 8 km ; une seule ligne candidate.
     */
    private function matchWikidataCodes(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('wikidata_lines') || ! $this->planned) {
            return;
        }
        $used = array_flip(DB::table('lines')
            ->whereRaw("NOT (COALESCE(alt_names->>'created_by', '') = 'vff:link-traces' AND COALESCE(alt_names->>'source', '') = ?)", [$this->source])
            ->whereNotNull('code')->pluck('code')->all());
        $usedQid = [];
        foreach ($this->planned as $p) {
            if (isset($p['code'])) {
                $used[$p['code']] = true;
            }
            if (isset($p['wikidata'])) {
                $usedQid[$p['wikidata']] = true;
            }
        }

        // Index des lignes planifiées par mot significatif de leur nom.
        $plannedPlaces = [];
        $index = [];
        foreach ($this->planned as $pid => $p) {
            if (isset($p['code'])) {
                continue;
            }
            $parsed = PlaceNames::parseLineName($p['name']);
            if (! $parsed || count($parsed['places']) < 2) {
                continue;
            }
            $places = array_map(fn ($x) => PlaceNames::significant($x), $parsed['places']);
            if (in_array([], array_slice($places, 0, 2), true)) {
                continue;
            }
            $plannedPlaces[$pid] = $places;
            foreach (array_merge(...$places) as $w) {
                $index[$w][$pid] = true;
            }
        }

        $rows = DB::select("SELECT qid, label, codes, ST_AsEWKT(ST_Transform(points, 2154)) AS pts FROM wikidata_lines
            WHERE line_id IS NULL AND label IS NOT NULL AND jsonb_array_length(COALESCE(codes, '[]'::jsonb)) > 0");
        $byCode = [];
        foreach ($rows as $r) {
            $codes = array_values(array_unique(array_filter(array_map(
                fn ($c) => preg_match('/^\d{6}$/', (string) ($c['code'] ?? '')) ? $c['code'] : null,
                json_decode($r->codes, true) ?: [],
            ))));
            if (count($codes) !== 1 || isset($used[$codes[0]]) || isset($usedQid[$r->qid])) {
                continue;
            }
            $parsed = PlaceNames::parseLineName(PlaceNames::ucfirst($r->label));
            if (! $parsed || count($parsed['places']) < 2) {
                continue;
            }
            $wPlaces = array_map(fn ($x) => PlaceNames::significant($x), $parsed['places']);
            $wAll = array_values(array_unique(array_merge(...$wPlaces)));
            $cands = [];
            foreach ($wAll as $w) {
                foreach (array_keys($index[$w] ?? []) as $pid) {
                    $cands[$pid] = true;
                }
            }
            $hits = [];
            foreach (array_keys($cands) as $pid) {
                $lp = $plannedPlaces[$pid];
                $lAll = array_merge(...$lp);
                // Extrémités croisées : celles de Wikidata dans le nom VFF et réciproquement.
                if (! array_intersect($wPlaces[0], $lAll) || ! array_intersect($wPlaces[1], $lAll)
                    || ! array_intersect($lp[0], $wAll) || ! array_intersect($lp[1], $wAll)) {
                    continue;
                }
                $ids = implode(',', $this->planned[$pid]['ids']);
                if ($r->pts) {
                    $d = DB::selectOne("SELECT ST_Distance(ST_GeomFromEWKT(?), (SELECT ST_Collect(g) FROM lt_t WHERE id IN ({$ids}))) AS d", [$r->pts])->d;
                    $ok = $d !== null && $d <= 3000;
                } else {
                    $ok = $this->placesNear($parsed['places'], $this->planned[$pid]['ids'])['near'] > 0;
                }
                if ($ok) {
                    $hits[] = $pid;
                }
            }
            if (count($hits) === 1) {
                $byCode[$codes[0]][] = [$hits[0], $r->qid];
            }
        }
        $n = 0;
        foreach ($byCode as $code => $list) {
            $pids = array_unique(array_column($list, 0));
            if (count($pids) === 1) { // un code → une seule ligne
                $this->planned[$pids[0]]['code'] = (string) $code;
                $this->planned[$pids[0]]['wikidata'] = $list[0][1];
                $n++;
            }
        }
        $this->line("  {$n} lignes créées reçoivent un code RFN d'après Wikidata.");
    }

    // ───────────────────────────── écriture ─────────────────────────────

    private function apply(): void
    {
        $prefix = self::SLUG_PREFIX[$this->source] ?? Str::slug($this->source);
        $existing = [];
        foreach (DB::select("SELECT id, slug, alt_names->>'group_key' AS gk, alt_names FROM lines
            WHERE alt_names->>'created_by' = 'vff:link-traces' AND alt_names->>'source' = ?", [$this->source]) as $r) {
            $existing[$r->gk] = ['id' => (int) $r->id, 'slug' => $r->slug, 'alt' => json_decode($r->alt_names ?? '{}', true) ?: []];
        }
        $slugs = array_flip(DB::table('lines')->pluck('slug')->all());

        // 1. Lignes planifiées → lignes réelles (réutilise l'id d'une exécution précédente).
        $realId = [];
        $kept = [];
        foreach ($this->planned as $pid => $p) {
            $alt = [
                'name_source' => in_array($p['name_source'], ['derived', 'wikidata', 'curated'], true) ? $p['name_source'] : $this->source,
                'created_by' => 'vff:link-traces', 'source' => $this->source,
                'group_key' => $p['group_key'], 'labels' => $p['labels'],
            ] + ($p['flags'] ? ['flags' => $p['flags']] : [])
              + (isset($p['wikidata']) ? ['wikidata_qid' => $p['wikidata'], 'code_source' => 'wikidata'] : [])
              + (isset($p['curated']) ? ['homonym_of' => $p['curated']['homonym_of'], 'operator' => $p['curated']['operator'],
                  'proof' => $p['curated']['proof']] : []);
            $data = [
                'name' => Str::limit($p['name'], 250, ''), 'network' => $p['network'],
                'gauge_mm_default' => $p['gauge'],
                'alt_names' => json_encode($alt, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
            ] + (isset($p['code']) ? ['code' => $p['code']] : [])
              + (isset($p['curated']) ? ['historical_operator' => $p['curated']['operator']] : []);
            if (isset($existing[$p['group_key']])) {
                $id = $existing[$p['group_key']]['id'];
                // Clés posées par d'autres traitements (preuve d'un code vérifié, fusions…) : conservées.
                $alt += array_diff_key($existing[$p['group_key']]['alt'], array_flip(self::MANAGED_ALT_KEYS));
                $data['alt_names'] = json_encode($alt, JSON_UNESCAPED_UNICODE);
                DB::table('lines')->where('id', $id)->update($data);
            } else {
                $base = $prefix.'-'.(Str::slug($p['name']) ?: 'ligne');
                $slug = $base;
                for ($i = 2; isset($slugs[$slug]); $i++) {
                    $slug = $base.'-'.$i;
                }
                $slugs[$slug] = true;
                $id = DB::table('lines')->insertGetId($data + ['slug' => $slug, 'created_at' => now()]);
            }
            $realId[$pid] = $id;
            $kept[$id] = true;
        }

        // 2. Rattachements des tracés de la source (anciens rattachements des lignes créées gardés pour l'étape 5).
        $previous = [];
        $createdIds = array_column($existing, 'id');
        foreach (array_chunk($createdIds, 1000) as $chunk) {
            foreach (DB::table('source_traces')->where('source', $this->source)->whereIn('line_id', $chunk)->get(['id', 'line_id']) as $r) {
                $previous[(int) $r->line_id][] = (int) $r->id;
            }
        }
        $mineIds = array_keys(array_filter($this->traces, fn ($t) => $t['mine']));
        foreach (array_chunk($mineIds, 1000) as $chunk) {
            DB::table('source_traces')->whereIn('id', $chunk)->update(['line_id' => null, 'match_method' => null, 'match_score' => null]);
        }
        // Lignes supprimées entre-temps par un autre traitement : rattachement abandonné.
        $targets = array_unique(array_filter(array_column($this->assign, 'line_id'), fn ($l) => $l > 0));
        $alive = array_flip(DB::table('lines')->whereIn('id', $targets)->pluck('id')->all());
        foreach ($this->assign as $id => $a) {
            if ($a['line_id'] > 0 && ! isset($alive[$a['line_id']])) {
                unset($this->assign[$id]);
                $this->warn("  tracé {$id} : ligne {$a['line_id']} disparue entre-temps, non rattaché.");
            }
        }
        $rows = [];
        $primaryLine = [];
        foreach ($this->assign as $id => $a) {
            $lid = $a['line_id'] < 0 ? $realId[$a['line_id']] : $a['line_id'];
            $rows[] = [$id, $lid, $a['method'], $a['score']];
            $primaryLine[$id] = $lid;
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            $values = [];
            $bind = [];
            foreach ($chunk as [$id, $lid, $m, $s]) {
                $values[] = '(?::bigint, ?::bigint, ?::text, ?::float)';
                array_push($bind, $id, $lid, $m, $s);
            }
            DB::statement('UPDATE source_traces st SET line_id = v.lid, match_method = v.m, match_score = v.s, updated_at = NOW()
                FROM (VALUES '.implode(',', $values).') v(id, lid, m, s) WHERE st.id = v.id', $bind);
        }

        // 2 bis. Découpes (étape d'') : tracé d'origine gardé sans ligne, morceaux dérivés régénérés.
        $derived = $this->applySplits($realId);

        // 3. Rattachements multiples (source_trace_lines).
        DB::statement('DELETE FROM source_trace_lines stl USING source_traces st
            WHERE st.id = stl.source_trace_id AND st.source = ?', [$this->source]);
        $links = [];
        foreach ($derived as $did => [$lid, $share, $method]) {
            $links[] = [$did, $lid, 'primary', $method, $share, null];
        }
        foreach ($this->traces as $id => $t) {
            $primary = $primaryLine[$id] ?? ($t['mine'] ? null : $t['fixed_line']);
            if ($primary) {
                $share = $this->assign[$id]['score'] ?? null;
                $links[] = [$id, $primary, 'primary', $this->assign[$id]['method'] ?? 'external', $share, null];
            }
            foreach ($t['sncf'] ?? [] as $c) {
                if ($c['line_id'] !== $primary && ($c['share'] >= 0.1 || $c['overlap_m'] >= 500)) {
                    $links[] = [$id, $c['line_id'], 'secondary', 'sncf_overlap', round($c['share'], 3), $c['overlap_m']];
                }
            }
        }
        foreach (array_chunk($links, 1000) as $chunk) {
            DB::table('source_trace_lines')->insert(array_map(fn ($l) => [
                'source_trace_id' => $l[0], 'line_id' => $l[1], 'role' => $l[2], 'method' => $l[3],
                'share' => $l[4], 'overlap_m' => $l[5], 'created_at' => now(), 'updated_at' => now(),
            ], $chunk));
        }

        // 4. Catégorie des lignes existantes SANS tronçon SNCF (fermées avant le RFN actuel, ex. lo-*)
        //    rattachées par nom : le calque de la source fait foi s'il est unanime (Réseau breton → secondaire).
        $votes = [];
        foreach ($this->assign as $id => $a) {
            $t = $this->traces[$id];
            if ($a['line_id'] > 0 && $a['method'] !== 'sncf_overlap' && $t['network']
                && ! in_array($t['layer'], self::NO_CREATE_LAYERS, true) && ! isset($this->hasSncf[$a['line_id']])) {
                $votes[$a['line_id']][$t['network']] = ($votes[$a['line_id']][$t['network']] ?? 0) + $t['len'];
            }
        }
        $recat = 0;
        $protected = array_flip(self::protectedFromRecategorization(array_keys($votes)));
        foreach ($votes as $lid => $byNet) {
            if (isset($protected[$lid])) {
                continue;
            }
            if (count($byNet) === 1 && ($this->lineNetworkMap[$lid] ?? null) !== array_key_first($byNet)) {
                DB::table('lines')->where('id', $lid)->update(['network' => array_key_first($byNet), 'updated_at' => now()]);
                $recat++;
            }
        }
        if ($recat) {
            $this->line("  {$recat} lignes existantes (hors RFN actuel) recatégorisées d'après le calque source.");
        }

        // 5. Lignes créées lors d'une exécution précédente et devenues inutiles.
        //    Si TOUS leurs anciens tracés sont passés sur une même ligne, elles en étaient un doublon :
        //    fusion (événements sans doublon, tracés d'autres sources, références externes).
        $stale = array_values(array_filter(array_column($existing, 'id'), fn ($id) => ! isset($kept[$id])));
        $deleted = 0;
        $merged = 0;
        $merger = app(LineMerger::class);
        $chimeras = 0;
        $orphans = 0;
        $chimeraKept = [];
        foreach ($stale as $id) {
            // Ligne « chimère » : TOUS ses tracés de la source ont été découpés entre plusieurs lignes (étape d'').
            // Les tracés d'autres sources qui l'avaient suivie par recouvrement / nom seront recalculés à leur
            // tour (vff:link-traces --source=…) ; les événements déduits d'un tracé (OHM, nom archeo) sont
            // régénérés par vff:ohm-events / vff:dates-from-trace-names sur les lignes des morceaux.
            $prev = $previous[$id] ?? [];
            if ($prev && ! array_diff($prev, array_keys(array_filter($this->splits, fn ($x) => $x['kind'] === 'chain')))) {
                if ($this->dropOrphanCreatedLine($id)) {
                    $chimeras++;
                } else {
                    $chimeraKept[] = $id;
                }

                continue;
            }
            $refs = DB::selectOne('SELECT
                (SELECT COUNT(*) FROM source_traces WHERE line_id = ?) + (SELECT COUNT(*) FROM line_events WHERE line_id = ?)
                + (SELECT COUNT(*) FROM line_segments WHERE line_id = ?) AS n', [$id, $id, $id])->n;
            if ($refs == 0) {
                DB::table('lines')->where('id', $id)->delete();
                $deleted++;

                continue;
            }
            $targets = array_values(array_unique(array_map(fn ($t) => $primaryLine[$t] ?? 0, $previous[$id] ?? [])));
            if (count($targets) === 1 && $targets[0] > 0 && ! in_array($targets[0], $stale, true)) {
                $methods = array_unique(array_map(fn ($t) => $this->assign[$t]['method'] ?? '?', $previous[$id]));
                $merger->merge($id, $targets[0], null, 'vff:link-traces : tous ses tracés '.$this->source.' rattachés ('.implode(', ', $methods).')');
                $merged++;
            } elseif ($this->dropOrphanCreatedLine($id)) {
                // Tracés partis sur plusieurs lignes (ou disparus) : plus rien qui lui soit propre.
                $orphans++;
            }
        }
        if ($stale) {
            $this->line("  {$deleted}/".count($stale).' lignes créées précédemment et devenues inutiles supprimées, '
                .$merged.' fusionnées dans la ligne qui a reçu leurs tracés, '
                .$chimeras.' lignes « chimères » (tracés découpés) et '.$orphans.' lignes orphelines (tracés répartis sur plusieurs lignes) supprimées.');
        }
        if ($chimeraKept) {
            $this->warn('  Lignes « chimères » gardées (autres références, à relire) : '.implode(', ', $chimeraKept));
        }
    }

    /**
     * Ligne créée ici qui n'a plus aucun tracé de la source : les tracés
     * d'autres sources qui l'avaient suivie (recouvrement, nom…) sont libérés,
     * ils seront recalculés à leur tour (vff:link-traces --source=…) ; les
     * événements déduits d'un tracé ou de l'état actuel (OHM, nom archeo,
     * vff:detect-present-states) sont régénérés sur les lignes qui reçoivent ces
     * tracés. Supprimée si plus rien d'autre ne la référence (événements d'une
     * autre source : gardée, à relire).
     */
    /**
     * Créateurs de lignes dont la catégorie (network) est posée par une source plus sûre que le calque
     * d'une source de tracés : la recatégorisation de l'étape 4 de apply() ne les touche jamais.
     *
     * Règle : une ligne créée par vff:seed-off-network-lines (lignes hors RFN, liste sourcée
     * database/data/lignes_hors_rfn.php : touristiques, industrielles, métriques…) garde la catégorie
     * de cette liste. Le calque d'une source (ex. « secondaire » d'archeoferroviaire) décrit l'ancienne
     * exploitation et l'écraserait à chaque exécution de vff:link-traces.
     */
    public const RECAT_PROTECTED_CREATORS = [SeedOffNetworkLines::CREATED_BY];

    /** Vrai si une ligne de ce créateur (alt_names.created_by) peut être recatégorisée d'après un calque. */
    public static function recategorizable(?string $createdBy): bool
    {
        return ! in_array($createdBy, self::RECAT_PROTECTED_CREATORS, true);
    }

    /**
     * Parmi $ids, les lignes que la recatégorisation d'après le calque source ne doit pas toucher
     * (voir RECAT_PROTECTED_CREATORS).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function protectedFromRecategorization(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('lines')->whereIn('id', $ids)
            ->get(['id', DB::raw("alt_names->>'created_by' AS created_by")])
            ->filter(fn ($r) => ! self::recategorizable($r->created_by))
            ->map(fn ($r) => (int) $r->id)->values()->all();
    }

    private function dropOrphanCreatedLine(int $id): bool
    {
        DB::table('source_traces')->where('line_id', $id)->where('source', '<>', $this->source)
            ->whereIn('match_method', self::METHODS)
            ->update(['line_id' => null, 'match_method' => null, 'match_score' => null, 'updated_at' => now()]);
        DB::table('line_events')->where('line_id', $id)->where(fn ($q) => $q->where('source', 'ohm')
            ->orWhere('fingerprint', 'like', 'archeo-name:%')->orWhere('fingerprint', 'like', 'present:%'))->delete();
        $left = DB::selectOne('SELECT (SELECT COUNT(*) FROM source_traces WHERE line_id = ?)
            + (SELECT COUNT(*) FROM line_events WHERE line_id = ?) AS n', [$id, $id])->n;
        if ($left > 0) {
            return false;
        }
        DB::table('lines')->where('id', $id)->delete();

        return true;
    }

    /**
     * Écrit les découpes (étape d'') : tracés d'origine sans ligne (match_method
     * « split »), morceaux dérivés insérés ou mis à jour (source_ref
     * « <ref>#<n> », identifiants stables d'une exécution à l'autre), morceaux
     * d'une découpe abandonnée supprimés.
     *
     * @return array<int, array{0: int, 1: ?float}> id du morceau → [ligne, part à ≤ 150 m de la ligne]
     */
    private function applySplits(array $realId): array
    {
        $originals = array_keys($this->splits);
        foreach (array_chunk($originals, 1000) as $chunk) {
            DB::table('source_traces')->whereIn('id', $chunk)
                ->update(['line_id' => null, 'match_method' => 'split', 'match_score' => null, 'updated_at' => now()]);
        }
        $derived = [];
        foreach ($this->splits as $id => $s) {
            $n = count($s['pieces']);
            foreach ($s['pieces'] as $k => $p) {
                $lid = $p['line_id'] < 0 ? $realId[$p['line_id']] : $p['line_id'];
                $props = ['derived_from' => $id, 'split' => [
                    'part' => $k + 1, 'of' => $n, 'from' => $p['from'], 'to' => $p['to'], 'f0' => $p['f0'], 'f1' => $p['f1'],
                    'geom_share' => $p['geom'], 'chain' => $s['chain'],
                    'rule' => $s['kind'] === 'chain' ? 'vff:link-traces (découpe aux jonctions)' : 'vff:link-traces (portion sur la voie d\'une autre ligne)',
                ]];
                $row = DB::selectOne(<<<'SQL'
                    INSERT INTO source_traces (source, source_ref, layer, name, properties, geom, length_m, line_id,
                                               match_method, match_score, seen_at, created_at, updated_at)
                    SELECT t.source, t.source_ref || '#' || CAST(:k AS text), t.layer, t.name,
                           jsonb_build_object('derived_from_ref', t.source_ref) || CAST(:props AS jsonb),
                           ST_Multi(p.g), ROUND(ST_Length(p.g::geography))::int, :line, :method, :score, t.seen_at, NOW(), NOW()
                    FROM source_traces t,
                         LATERAL (SELECT ST_Transform(ST_LineSubstring(ST_LineMerge(ST_Transform(t.geom, 2154)), :f0, :f1), 4326) AS g) p
                    WHERE t.id = :id
                    ON CONFLICT (source, source_ref) DO UPDATE SET
                        layer = EXCLUDED.layer, name = EXCLUDED.name, properties = EXCLUDED.properties, geom = EXCLUDED.geom,
                        length_m = EXCLUDED.length_m, line_id = EXCLUDED.line_id, match_method = EXCLUDED.match_method,
                        match_score = EXCLUDED.match_score, seen_at = EXCLUDED.seen_at, updated_at = NOW()
                    RETURNING id
                    SQL, ['k' => $k + 1, 'props' => json_encode($props, JSON_UNESCAPED_UNICODE), 'line' => $lid,
                    'method' => $p['method'], 'score' => $p['geom'], 'f0' => $p['f0'], 'f1' => $p['f1'], 'id' => $id]);
                $derived[(int) $row->id] = [$lid, $p['geom'], $p['method']];
            }
        }
        // Morceaux d'une découpe qui n'a plus lieu (le tracé d'origine est rattaché autrement).
        $gone = DB::table('source_traces')->where('source', $this->source)->whereRaw("(properties->>'derived_from') IS NOT NULL")
            ->whereNotIn('id', array_keys($derived) ?: [0])->pluck('id')->all();
        // Dates lues dans le nom d'un tracé (vff:dates-from-trace-names) : reposées sur les morceaux à sa prochaine exécution.
        foreach (array_chunk(array_merge($originals, $gone), 1000) as $chunk) {
            DB::table('line_events')->whereIn('source_trace_id', $chunk)->where('fingerprint', 'like', 'archeo-name:%')->delete();
        }
        foreach (array_chunk($gone, 1000) as $chunk) {
            DB::table('source_traces')->whereIn('id', $chunk)->delete();
        }
        if ($this->splits || $gone) {
            $this->line(sprintf('  Découpes : %d tracés d\'origine gardés sans ligne, %d morceaux dérivés écrits, %d morceaux obsolètes supprimés.',
                count($originals), count($derived), count($gone)));
        }

        return $derived;
    }

    // ───────────────────────────── rapport ─────────────────────────────

    private function report(): void
    {
        $byMethod = [];
        foreach ($this->traces as $id => $t) {
            if (! $t['mine']) {
                $m = 'externe (non recalculé)';
            } elseif (isset($this->splits[$id])) {
                $m = 'split (découpé)';
            } else {
                $m = $this->assign[$id]['method'] ?? 'non rattaché';
            }
            $byMethod[$m][$t['layer']] = ($byMethod[$m][$t['layer']] ?? 0) + 1;
            $byMethod[$m]['km'] = ($byMethod[$m]['km'] ?? 0) + $t['len'] / 1000;
        }
        $layers = array_values(array_unique(array_column($this->traces, 'layer')));
        sort($layers);
        $table = [];
        foreach ($byMethod as $m => $c) {
            $table[] = array_merge([$m], array_map(fn ($l) => $c[$l] ?? 0, $layers), [array_sum(array_intersect_key($c, array_flip($layers))), round($c['km'])]);
        }
        $this->table(array_merge(['Méthode'], $layers, ['Total', 'km']), $table);

        $created = [];
        foreach ($this->planned as $p) {
            $created[$p['network']]['n'] = ($created[$p['network']]['n'] ?? 0) + 1;
            $created[$p['network']][$p['name_source']] = ($created[$p['network']][$p['name_source']] ?? 0) + 1;
            $created[$p['network']]['km'] = ($created[$p['network']]['km'] ?? 0)
                + array_sum(array_map(fn ($id) => $this->traces[$id]['len'], $p['ids'])) / 1000;
            if ($p['gauge']) {
                $created[$p['network']]['gauge'] = ($created[$p['network']]['gauge'] ?? 0) + 1;
            }
        }
        $this->table(['Lignes créées', 'Nombre', 'nom source', 'nom dérivé', 'écartement connu', 'km tracés'], array_map(
            fn ($net, $c) => [$net, $c['n'], $c[$this->source] ?? 0, $c['derived'] ?? 0, $c['gauge'] ?? 0, round($c['km'])],
            array_keys($created), $created,
        ));
        foreach ($this->rejects as $why => $n) {
            $this->line("  rejet ({$why}) : {$n}");
        }
    }

    /** Détail par tracé (relecture / curation) : storage/app/reports/link-traces-<source>.csv */
    private function writeCsv(): void
    {
        $names = DB::table('lines')->pluck('name', 'id')->all();
        $nets = DB::table('lines')->pluck('network', 'id')->all();
        $dir = storage_path('app/reports');
        @mkdir($dir, 0775, true);
        $path = $dir.'/link-traces-'.Str::slug($this->source).($this->dry ? '-dry-run' : '').'.csv';
        $fh = fopen($path, 'w');
        fputcsv($fh, ['trace_id', 'layer', 'label', 'length_m', 'method', 'score', 'line_id', 'line_name', 'line_network', 'new_line', 'secondary_lines', 'wikidata_code']);
        foreach ($this->traces as $id => $t) {
            $a = $this->assign[$id] ?? null;
            $lid = $a['line_id'] ?? ($t['mine'] ? null : $t['fixed_line']);
            $planned = $lid !== null && $lid < 0;
            $secondary = implode(' ', array_map(fn ($c) => $c['line_id'].':'.round($c['share'], 2),
                array_filter($t['sncf'] ?? [], fn ($c) => $c['line_id'] !== $lid && $c['share'] >= 0.1)));
            if (isset($this->splits[$id])) {
                fputcsv($fh, [$id, $t['layer'], $t['name'], $t['len'], 'split', '', '', $this->splits[$id]['chain'], '', '', $secondary, '']);

                continue;
            }
            fputcsv($fh, [
                $id, $t['layer'], $t['name'], $t['len'], $a['method'] ?? ($t['mine'] ? '' : 'externe'), $a['score'] ?? '',
                $planned ? '' : $lid, $planned ? $this->planned[$lid]['name'] : ($names[$lid] ?? ''),
                $planned ? $this->planned[$lid]['network'] : ($nets[$lid] ?? ''), $planned ? 'oui' : '', $secondary,
                $planned ? (($this->planned[$lid]['code'] ?? '').' '.($this->planned[$lid]['wikidata'] ?? '')) : '',
            ]);
        }
        fclose($fh);
        $this->line('  détail : '.$path);

        // Décisions de l'étape d'' (découpe), y compris les chaînes rejetées ou ambiguës.
        $path = $dir.'/link-traces-'.Str::slug($this->source).'-decoupes'.($this->dry ? '-dry-run' : '').'.csv';
        $fh = fopen($path, 'w');
        fputcsv($fh, ['trace_id', 'label', 'length_m', 'avant', 'chaines', 'decision', 'detail']);
        foreach ($this->splitLog as $l) {
            fputcsv($fh, [$l['trace_id'], $l['label'], $l['length_m'], $l['before'], $l['chains'], $l['decision'], $l['detail']]);
        }
        fclose($fh);
        $this->line('  découpes : '.$path);
    }

    // ───────────────────────────── e. tronçons SNCF ─────────────────────────────

    /**
     * Convertit les tronçons SNCF (sncf_line_geometries) en source_traces
     * source='sncf' (source_ref = idgaia), rattachés par leur code de ligne :
     * toutes les sources deviennent homogènes pour l'audit et la géométrie de
     * référence.
     */
    private function importSncf(): int
    {
        $n = DB::selectOne('SELECT COUNT(*) n FROM sncf_line_geometries')->n;
        if ($this->dry) {
            $this->info("{$n} tronçons SNCF seraient convertis.");

            return self::SUCCESS;
        }
        DB::transaction(function () {
            $started = now();
            DB::statement(<<<'SQL'
                INSERT INTO source_traces (source, source_ref, layer, name, properties, geom, length_m, line_id, match_method, match_score, seen_at, created_at, updated_at)
                SELECT 'sncf', COALESCE(s.idgaia, 'id:'||s.id), 'rfn', l.name,
                       jsonb_build_object('code_ligne', s.code_ligne, 'statut', s.libelle, 'mnemo', s.mnemo,
                                          'rg_troncon', s.rg_troncon, 'pk_debut_m', s.pk_debut_m, 'pk_fin_m', s.pk_fin_m),
                       ST_Multi(s.geom), ROUND(ST_Length(s.geom::geography))::int, l.id,
                       CASE WHEN l.id IS NULL THEN NULL ELSE 'code' END, CASE WHEN l.id IS NULL THEN NULL ELSE 1 END,
                       CAST(:seen AS timestamp), NOW(), NOW()
                FROM sncf_line_geometries s LEFT JOIN lines l ON l.code = s.code_ligne
                ON CONFLICT (source, source_ref) DO UPDATE SET
                    name = EXCLUDED.name, properties = EXCLUDED.properties, geom = EXCLUDED.geom,
                    length_m = EXCLUDED.length_m, line_id = EXCLUDED.line_id, match_method = EXCLUDED.match_method,
                    match_score = EXCLUDED.match_score, seen_at = EXCLUDED.seen_at, updated_at = NOW()
                SQL, ['seen' => $started]);
            DB::statement("DELETE FROM source_traces WHERE source = 'sncf' AND seen_at < ?", [$started]);
            DB::statement("DELETE FROM source_trace_lines stl USING source_traces st WHERE st.id = stl.source_trace_id AND st.source = 'sncf'");
            DB::statement("INSERT INTO source_trace_lines (source_trace_id, line_id, role, method, share, overlap_m, created_at, updated_at)
                SELECT id, line_id, 'primary', 'code', 1, length_m, NOW(), NOW() FROM source_traces WHERE source = 'sncf' AND line_id IS NOT NULL");
        });
        $r = DB::selectOne("SELECT COUNT(*) n, COUNT(line_id) linked, ROUND(SUM(length_m) / 1000.0) km FROM source_traces WHERE source = 'sncf'");
        $this->info("✓ {$r->n} tronçons SNCF dans source_traces ({$r->linked} rattachés par code, {$r->km} km).");

        return self::SUCCESS;
    }
}
