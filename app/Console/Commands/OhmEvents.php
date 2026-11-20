<?php

namespace App\Console\Commands;

use App\Services\OhmDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déduit des tracés OpenHistoricalMap (source_traces.source = 'ohm', cf.
 * vff:import-ohm) les événements datés PAR PORTION de chaque ligne
 * (`line_events`, source = 'ohm', section_resolution = 'trace').
 *
 * Conventions OHM constatées sur les voies françaises (13 680 ways, oct. 2026)
 * ---------------------------------------------------------------------------
 * - Modèle officiel OHM : un way = un état, borné par start_date / end_date ;
 *   un changement (déviation, électrification, passage au fret seul…) se
 *   cartographie par un NOUVEAU way superposé aux dates successives. Les
 *   préfixes de cycle de vie (disused:railway=…) sont dépréciés (≈ 5 ways).
 *   → un end_date n'est PAS forcément une fermeture : ≈ 5 % des ways rail datés
 *   ont un successeur actif superposé qui démarre la même année.
 * - railway=disused (≈ 830 ways, Bretagne / Pays de la Loire surtout) est, en
 *   pratique, utilisé pour une ligne FERMÉE avec start_date = ouverture et
 *   end_date = fermeture (ex. Carhaix-Morlaix 1891-09-28 → 1967) : seuls
 *   ≈ 1 % suivent un way actif qui se termine quand ils commencent (sens
 *   officiel « période d'abandon »). On tranche donc PAR TRONÇON : disused
 *   précédé d'un way actif terminé avant (ou dans l'année) → période d'abandon (sa fin
 *   = dépose, confiance faible) ; sinon → période d'exploitation. Un disused
 *   sans end_date ne prolonge pas l'activité (fermeture inconnue).
 * - railway=abandoned / razed / dismantled (≈ 4 ways) : start_date = dépose,
 *   jamais une ouverture.
 * - Trafic : aucun tag passenger=* ; `usage=freight` / `railway:traffic_mode`
 *   (freight, passenger, mixed) existent, mais traffic_mode=passenger vient
 *   surtout de l'import du RFN actuel (LGV, CP) et décrit l'état ACTUEL. On ne
 *   produit donc une variante voyageurs/fret qu'au passage d'un way à son
 *   successeur (ex. way « mixte » jusqu'en 1938, way usage=freight ensuite →
 *   closure_passenger 1938) ou pour une ouverture explicitement fret.
 * - Électrification : seulement quand un way electrified=no est relayé par un
 *   way électrifié (les ways du RFN importé portent l'électrification actuelle
 *   dès l'ouverture).
 * - Autres tags utiles : name (« Ligne X - Y »), ref:FR:SNCF_Reseau (« 570000,
 *   3,V1,… »), railway:track_ref (« 233 000 » = code de ligne chez certains
 *   contributeurs), wikidata / wikipedia, source / source:url (archeo-
 *   ferroviaire, lignes-oubliees, Wikipédia, cartes d'état-major…),
 *   start_date:edtf / end_date:edtf (incertitudes, parfois en texte libre).
 *
 * Méthode
 * -------
 * 1. Géométrie de référence de la ligne (Lambert-93) : `line_reference_geometries`
 *    si la ligne y figure ; sinon l'assemblage des `source_traces` rattachés
 *    (line_id, hors OHM et hors voies de service BD TOPO) et de la forme SNCF
 *    du code de ligne, source par source (BD TOPO, SNCF, OSM, autres, archeo).
 * 2. Découpage en tronçons de --step mètres (50 m) ; un tronçon à moins de
 *    --buffer m d'un tronçon déjà retenu (voie doublée, même voie dessinée par deux
 *    sources) est écarté. Un way OHM « couvre » un tronçon si le milieu du
 *    tronçon est à moins de --buffer mètres (30 m) du way.
 * 3. Un way n'est retenu pour la ligne que s'il la longe vraiment (≥ 150 m
 *    couverts, et ≥ 50 % de sa longueur ou ≥ 1 km) ; on écarte les voies de
 *    service (service=*), les embranchements industriels / militaires d'une
 *    ligne publique, les ways portant un autre code de ligne SNCF, ceux dont le
 *    nom « Ligne A - B » n'a aucune extrémité commune avec celui de la ligne
 *    (tronc commun, gare partagée) et ceux d'un écartement différent de
 *    l'écartement dominant. Sont aussi écartés (ils décrivent une AUTRE voie qui
 *    longe celle-ci) : un way rattaché (vff:link-traces) à une autre ligne d'une
 *    autre catégorie de réseau (grande ligne longée par un tramway ou une voie
 *    métrique, embranchement minier ou militaire sur la ligne qu'il dessert) ; un
 *    tramway moderne (railway=tram / light_rail ouvert à partir de 1985, première
 *    ligne moderne à Nantes) sur une ligne qui n'est pas exploitée aujourd'hui par
 *    SNCF Réseau : c'est une infrastructure neuve posée sur l'emprise ou la voirie
 *    (T3 de Lyon sur l'ancien CFEL, tramways de Grenoble sur les anciennes lignes
 *    VFD), pas une réouverture de l'ancienne ligne. Un start_date égal à l'année de fin
 *    de la carte source (« … for the period 1820 to 1866 » → 1866) n'est pas une ouverture
 *    (cf. placeholderStart).
 * 4. Par tronçon, on fusionne les périodes d'exploitation (tolérance d'un an
 *    entre un way et son successeur) : début = ouverture (ou réouverture),
 *    fin = fermeture ; les changements trafic / électrification sont lus aux
 *    raccords entre ways successifs.
 * 5. Les tronçons d'un même événement (type + date) sont regroupés en portions
 *    continues (trous non couverts ≤ 500 m comblés), puis les portions dont
 *    les extrémités sont à moins de 1 km sont réunies (formes SNCF mal
 *    raccordées, ways contigus de même date) ; portions < 200 m ignorées.
 *    → un line_event par portion : section_geom = portion sur la géométrie de
 *    référence (MultiLineString), section_from/to = gares les plus proches des
 *    extrémités (≤ 3 km, table stations puis sncf_gares), source_trace_id /
 *    source_url = way OHM le plus représenté, source_quote = ses tags datés.
 *
 * Idempotent : fingerprint = empreinte (ligne, type, date, ways OHM) ; upsert,
 * puis suppression des événements 'ohm' de la ligne qui n'ont pas été
 * reproduits. À relancer après tout changement des tracés rattachés
 * (vff:import-ohm, rattachement des tracés, line_reference_geometries).
 *
 *   php artisan vff:ohm-events
 *   php artisan vff:ohm-events --line=956000 --line=485000 --dry-run
 */
class OhmEvents extends Command
{
    protected $signature = 'vff:ohm-events
        {--line=* : code, slug ou id de ligne (toutes par défaut)}
        {--step=50 : longueur des tronçons d\'analyse (m)}
        {--buffer=30 : tolérance de superposition way OHM / ligne (m)}
        {--trace=* : (diagnostic, avec --dry-run) id de source_traces à ajouter à la géométrie de référence}
        {--dry-run : affiche les événements sans écrire}';

    protected $description = 'Écrit dans line_events les ouvertures / fermetures par portion déduites d\'OpenHistoricalMap.';

    /** Calques OHM porteurs d'une période d'exploitation. */
    private const ACTIVE = ['rail', 'narrow_gauge', 'light_rail', 'tram', 'subway', 'preserved'];

    private const GONE = ['abandoned', 'razed', 'dismantled', 'demolished', 'removed'];

    private const MIN_WAY_CHUNKS = 3;

    private const MAX_GAP_M = 500;

    private const CLUSTER_M = 1000;

    private const MIN_RUN_M = 200;

    private const TOLERANCE_DAYS = 366;

    /** Premier tramway moderne français (Nantes, 7 janvier 1985) : un way tram plus récent est une voie neuve. */
    public const MODERN_TRAM_YEAR = 1985;

    private const MODERN_TRAM_LAYERS = ['tram', 'light_rail'];

    /** @var array<string, true> codes de ligne exploités aujourd'hui (SNCF Réseau) */
    private array $exploitedCodes = [];

    private const LABELS = [
        'opening' => 'Ouverture',
        'opening_freight' => 'Ouverture au fret',
        'reopening' => 'Réouverture',
        'reopening_freight' => 'Réouverture au fret',
        'reopening_passenger' => 'Réouverture aux voyageurs',
        'closure' => 'Fermeture (tout trafic)',
        'closure_passenger' => 'Fermeture au service voyageurs (fret maintenu)',
        'closure_freight' => 'Fermeture au fret (voyageurs maintenus)',
        'dismantling' => 'Dépose de la voie',
        'electrification' => 'Électrification',
    ];

    private float $step;

    private float $buffer;

    private bool $dryRun;

    private ?bool $hasReferenceTable = null;

    /** @var array<string, int> */
    private array $stats = ['lines' => 0, 'no_geom' => 0, 'with_events' => 0, 'written' => 0, 'deleted' => 0, 'name_excluded' => 0,
        'network_excluded' => 0, 'modern_tram_excluded' => 0];

    /** @var array<string, array{n: int, m: float}> */
    private array $byType = [];

    public function handle(): int
    {
        $this->step = max(10.0, (float) $this->option('step'));
        $this->buffer = max(5.0, (float) $this->option('buffer'));
        $this->dryRun = (bool) $this->option('dry-run');

        $lines = $this->selectLines();
        if ($lines === []) {
            $this->error('Aucune ligne trouvée.');

            return self::FAILURE;
        }

        $this->prepareWays();

        $bar = count($lines) > 1 && ! $this->dryRun ? $this->output->createProgressBar(count($lines)) : null;
        foreach ($lines as $line) {
            $this->processLine($line);
            $bar?->advance();
        }
        $bar?->finish();
        $this->newLine();

        $this->table(['Type', 'Événements', 'km'], array_map(
            fn ($type, $s) => [$type, $s['n'], number_format($s['m'] / 1000, 1, ',', ' ')],
            array_keys($this->byType), $this->byType,
        ));
        $this->info(sprintf(
            '✓ %d lignes traitées (%d sans géométrie), %d avec événements OHM ; %d événements %s, %d obsolètes supprimés ; rattachements way/ligne écartés : %d sur le nom, %d d\'une ligne d\'une autre catégorie, %d tramways modernes.',
            $this->stats['lines'], $this->stats['no_geom'], $this->stats['with_events'],
            $this->stats['written'], $this->dryRun ? 'calculés (dry-run)' : 'écrits', $this->stats['deleted'], $this->stats['name_excluded'],
            $this->stats['network_excluded'], $this->stats['modern_tram_excluded'],
        ));

        return self::SUCCESS;
    }

    /** @return array<int, object> */
    private function selectLines(): array
    {
        $refs = array_filter((array) $this->option('line'));
        $query = DB::table('lines')->select('id', 'code', 'name', 'network', 'gauge_mm_default')->orderBy('id');
        if ($refs) {
            $query->where(function ($q) use ($refs): void {
                foreach ($refs as $ref) {
                    $q->orWhere('code', $ref)->orWhere('slug', $ref);
                    if (ctype_digit((string) $ref) && strlen((string) $ref) < 6) {
                        $q->orWhere('id', (int) $ref);
                    }
                }
            });
        }

        return $query->get()->all();
    }

    /** Copie Lambert-93 indexée des ways OHM utiles, pour la durée de la commande. */
    private function prepareWays(): void
    {
        DB::statement('DROP TABLE IF EXISTS ohm_ways');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE ohm_ways AS
            SELECT st.id, st.source_ref, st.layer, st.properties, ST_Transform(st.geom, 2154) AS g, ST_Length(ST_Transform(st.geom, 2154)) AS len,
                   st.line_id AS linked_line, l.network AS linked_network
            FROM source_traces st
            LEFT JOIN lines l ON l.id = st.line_id
            WHERE st.source = 'ohm' AND st.layer IN ('rail', 'narrow_gauge', 'light_rail', 'tram', 'subway', 'preserved',
                                                'disused', 'abandoned', 'razed', 'dismantled', 'demolished', 'removed', 'historic')
            SQL);
        DB::statement('CREATE INDEX ON ohm_ways USING GIST (g)');
        DB::statement('ANALYZE ohm_ways');
        $this->exploitedCodes = array_fill_keys(DB::table('sncf_line_geometries')->where('libelle', 'ilike', 'exploit%')
            ->distinct()->pluck('code_ligne')->map(fn ($c) => (string) $c)->all(), true);
    }

    private function processLine(object $line): void
    {
        $this->stats['lines']++;
        if (! $this->buildChunks($line)) {
            $this->stats['no_geom']++;
            $this->purge($line->id, []);

            return;
        }

        $chunks = [];
        $sql = 'SELECT id, comp, idx, len, ST_X(ST_StartPoint(g)) AS x0, ST_Y(ST_StartPoint(g)) AS y0,
                       ST_X(ST_EndPoint(g)) AS x1, ST_Y(ST_EndPoint(g)) AS y1
                FROM ohm_chunks ORDER BY comp, idx';
        foreach (DB::select($sql) as $c) {
            $chunks[$c->id] = [
                'comp' => (int) $c->comp, 'idx' => (int) $c->idx, 'len' => (float) $c->len, 'ways' => [],
                'a' => [(float) $c->x0, (float) $c->y0], 'b' => [(float) $c->x1, (float) $c->y1],
            ];
        }

        $counts = [];
        foreach (DB::select('SELECT c.id AS chunk, w.id AS way FROM ohm_chunks c JOIN ohm_ways w ON ST_DWithin(w.g, c.mid, ?)', [$this->buffer]) as $p) {
            $chunks[$p->chunk]['ways'][] = (int) $p->way;
            $counts[$p->way] = ($counts[$p->way] ?? 0) + 1;
        }
        $ways = [];
        if ($counts) {
            $ids = '{'.implode(',', array_keys($counts)).'}';
            foreach (DB::select('SELECT id AS way, source_ref, layer, properties, len, linked_line, linked_network FROM ohm_ways WHERE id = ANY(CAST(? AS bigint[]))', [$ids]) as $p) {
                $ways[$p->way] = $this->describeWay($p) + ['chunks' => $counts[$p->way]];
            }
        }

        $kept = $this->filterWays($line, $ways);

        // Événements par tronçon (mis en cache par ensemble de ways identique).
        $cache = [];
        $events = []; // clé type|date|precision => [chunkId => event]
        foreach ($chunks as $chunkId => &$chunk) {
            $chunk['ways'] = array_values(array_filter(array_unique($chunk['ways']), fn ($id) => isset($kept[$id])));
            sort($chunk['ways']);
            if (! $chunk['ways']) {
                continue;
            }
            $sig = implode(',', $chunk['ways']);
            $cache[$sig] ??= $this->timeline(array_map(fn ($id) => $kept[$id], $chunk['ways']));
            foreach ($cache[$sig] as $event) {
                $events[$event['type'].'|'.$event['date'].'|'.$event['precision']][$chunkId] = $event;
            }
        }
        unset($chunk);

        $rows = [];
        foreach ($events as $byChunk) {
            foreach ($this->runs($byChunk, $chunks) as $run) {
                $rows[] = $run;
            }
        }

        $fingerprints = [];
        $seenFp = [];
        foreach ($rows as $row) {
            $base = 'ohm:'.md5(implode('|', [$line->id, $row['type'], $row['date'], $row['precision'], implode(',', $row['ways'])]));
            $fp = $base;
            for ($i = 2; isset($seenFp[$fp]); $i++) {
                $fp = 'ohm:'.md5($base.'|'.$i);
            }
            $seenFp[$fp] = true;
            $fingerprints[] = $fp;
            $this->write($line, $row, $fp, $kept);
            $this->byType[$row['type']] ??= ['n' => 0, 'm' => 0.0];
            $this->byType[$row['type']]['n']++;
            $this->byType[$row['type']]['m'] += $row['length'];
        }
        if ($rows) {
            $this->stats['with_events']++;
        }
        $this->stats['written'] += count($rows);
        $this->purge($line->id, $fingerprints);
    }

    /**
     * Construit `ohm_chunks` (tronçons de la géométrie de référence, Lambert-93).
     * Retourne false si la ligne n'a aucune géométrie.
     *
     * Sans `line_reference_geometries`, la référence est l'assemblage des tracés
     * rattachés, source par source (BD TOPO, forme SNCF, OSM, autres, archeo) ;
     * un tronçon situé à moins de --buffer m d'un tronçon déjà retenu (voie doublée,
     * même voie dessinée par deux sources, voies parallèles en gare) est écarté.
     */
    private function buildChunks(object $line): bool
    {
        DB::statement('DROP TABLE IF EXISTS ohm_ref');
        DB::statement('CREATE TEMP TABLE ohm_ref (prio int, g geometry)');

        if ($this->referenceTableAvailable()) {
            DB::statement(<<<'SQL'
                INSERT INTO ohm_ref
                SELECT 0, ST_LineMerge(ST_CollectionExtract(ST_Collect(ST_Transform(geom, 2154)), 2))
                FROM line_reference_geometries WHERE line_id = ? HAVING COUNT(*) > 0
                SQL, [$line->id]);
        }
        $traces = $this->dryRun ? '{'.implode(',', array_map('intval', array_filter((array) $this->option('trace')))).'}' : '{}';
        if (! DB::selectOne('SELECT 1 AS ok FROM ohm_ref WHERE g IS NOT NULL AND NOT ST_IsEmpty(g)')) {
            DB::statement('DELETE FROM ohm_ref');
            DB::statement(<<<'SQL'
                INSERT INTO ohm_ref
                SELECT prio, ST_LineMerge(ST_CollectionExtract(ST_Collect(ST_Transform(geom, 2154)), 2))
                FROM (
                    SELECT CASE source WHEN 'bdtopo' THEN 1 WHEN 'sncf' THEN 2 WHEN 'osm' THEN 3
                                       WHEN 'archeoferroviaire' THEN 5 ELSE 4 END AS prio, geom
                    FROM source_traces
                    WHERE line_id = ? AND source <> 'ohm' AND NOT (source = 'bdtopo' AND layer = 'Voie de service')
                    UNION ALL
                    SELECT 2, geom FROM sncf_line_geometries WHERE code_ligne = ?
                    UNION ALL
                    SELECT 6, geom FROM source_traces WHERE id = ANY(CAST(? AS bigint[]))
                ) pieces
                GROUP BY prio
                SQL, [$line->id, (string) $line->code, $traces]);
        }

        DB::statement('DROP TABLE IF EXISTS ohm_chunks');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE ohm_chunks AS
            WITH comps AS (
                SELECT prio * 1000000 + COALESCE((d).path[1], 1) AS comp, (d).geom AS g
                FROM (SELECT prio, ST_Dump(g) AS d FROM ohm_ref WHERE g IS NOT NULL) x
                WHERE ST_Length((d).geom) >= 5
            ), sized AS (
                SELECT comp, g, ST_Length(g) AS len, GREATEST(1, CEIL(ST_Length(g) / ?))::int AS n FROM comps
            )
            SELECT row_number() OVER (ORDER BY comp, i)::int AS id, comp, i AS idx,
                   ST_LineSubstring(g, i::float8 / n, (i + 1)::float8 / n) AS g,
                   ST_LineInterpolatePoint(g, (i + 0.5) / n) AS mid,
                   len / n AS len
            FROM sized, generate_series(0, n - 1) AS i
            SQL, [$this->step]);
        DB::statement('CREATE INDEX ON ohm_chunks USING GIST (g)');
        DB::statement(<<<'SQL'
            DELETE FROM ohm_chunks c USING ohm_chunks d
            WHERE d.id < c.id AND ST_DWithin(d.g, c.mid, ?) AND NOT (d.comp = c.comp AND c.idx - d.idx <= 1)
            SQL, [$this->buffer]);
        DB::statement('CREATE INDEX ON ohm_chunks USING GIST (mid)');
        DB::statement('ANALYZE ohm_chunks');

        return (bool) DB::selectOne('SELECT COUNT(*) AS n FROM ohm_chunks')->n;
    }

    private function referenceTableAvailable(): bool
    {
        if ($this->hasReferenceTable === null) {
            $cols = DB::table('information_schema.columns')
                ->where('table_schema', 'public')->where('table_name', 'line_reference_geometries')
                ->pluck('column_name')->all();
            $this->hasReferenceTable = in_array('line_id', $cols, true) && in_array('geom', $cols, true);
            if ($this->hasReferenceTable) {
                $this->line('→ line_reference_geometries disponible : prioritaire pour les lignes qu\'elle couvre.');
            }
        }

        return $this->hasReferenceTable;
    }

    /** @return array<string, mixed> */
    private function describeWay(object $p): array
    {
        $tags = json_decode($p->properties, true) ?: [];
        $layer = (string) $p->layer;
        if ($layer === 'historic') {
            $layer = 'rail'; // historic=railway sans railway=* : voie historique en service
        }
        $kind = in_array($layer, self::ACTIVE, true) ? 'active' : ($layer === 'disused' ? 'disused' : (in_array($layer, self::GONE, true) ? 'gone' : 'ignore'));

        $usage = mb_strtolower((string) ($tags['usage'] ?? ''));
        $traffic = mb_strtolower((string) ($tags['railway:traffic_mode'] ?? ''));
        $mode = match (true) {
            $usage === 'tourism' || $layer === 'preserved' || ($tags['railway:preserved'] ?? null) === 'yes'
                || str_contains((string) ($tags['service'] ?? ''), 'touristic') => 'tourism',
            $usage === 'freight' || $traffic === 'freight' => 'freight',
            $traffic === 'mixed' || str_contains($usage, 'main;freight') => 'mixed',
            $traffic === 'passenger' => 'passenger',
            default => null,
        };
        $electrified = $tags['electrified'] ?? null;
        $elec = match (true) {
            $electrified === 'no' => false,
            in_array($electrified, ['contact_line', 'rail', 'yes', '4th_rail', 'ground-level_power_supply'], true) => true,
            default => null,
        };

        // Code de ligne SNCF éventuel porté par le way.
        $code = null;
        if (! empty($tags['ref:FR:SNCF_Reseau']) && preg_match('/^(\d{6})/', (string) $tags['ref:FR:SNCF_Reseau'], $m)) {
            $code = $m[1];
        } else {
            foreach (['ref', 'railway:track_ref'] as $key) {
                if (isset($tags[$key]) && preg_match('/^(\d{3})\s?(\d{3})$/', trim((string) $tags[$key]), $m)) {
                    $code = $m[1].$m[2];
                    break;
                }
            }
        }

        $gauges = [];
        foreach (preg_split('/[;,]/', (string) ($tags['gauge'] ?? '')) as $g) {
            if (ctype_digit(trim($g))) {
                $gauges[] = (int) trim($g);
            }
        }

        return [
            'id' => (int) $p->way,
            'osm' => (string) $p->source_ref,
            'layer' => (string) $p->layer,
            'kind' => $kind,
            'tags' => $tags,
            'len' => (float) $p->len,
            'start' => self::placeholderStart($tags) ? null : OhmDate::fromTags($tags, 'start'),
            'end' => OhmDate::fromTags($tags, 'end'),
            'mode' => $mode,
            'elec' => $elec,
            'code' => $code,
            'gauges' => $gauges,
            'narrow' => $layer === 'narrow_gauge',
            'service' => isset($tags['service']),
            'usage' => $usage,
            // Ligne VFF à laquelle vff:link-traces a rattaché le way (et sa catégorie de réseau).
            'linked_line' => isset($p->linked_line) ? (int) $p->linked_line : null,
            'linked_network' => $p->linked_network ?? null,
        ];
    }

    /**
     * Ne garde que les ways qui longent réellement la ligne et lui appartiennent.
     *
     * @param  array<int, array<string, mixed>>  $ways
     * @return array<int, array<string, mixed>>
     */
    private function filterWays(object $line, array $ways): array
    {
        $public = in_array($line->network, ['principal', 'secondaire', null], true);
        $lineEnds = self::nameEnds((string) $line->name);
        $kept = [];
        foreach ($ways as $id => $w) {
            // Nom « Ligne A - B » sans aucune extrémité commune avec « Ligne de C à D » :
            // c'est une autre ligne qui longe ou croise celle-ci (gare commune, tronc commun).
            if ($lineEnds && isset($w['tags']['name']) && ($wayEnds = self::nameEnds((string) $w['tags']['name']))
                && ! self::endsMatch($lineEnds, $wayEnds)) {
                $this->stats['name_excluded']++;
                if ($this->output?->isVerbose()) {
                    $this->line("    ≠ nom : way {$w['osm']} « {$w['tags']['name']} » écarté pour « {$line->name} »");
                }

                continue;
            }
            // Way rattaché à une autre ligne d'une autre catégorie de réseau : il décrit cette ligne-là
            // (grande ligne longée par un tramway, embranchement minier sur la ligne qu'il dessert).
            if (self::otherNetworkWay($line, $w)) {
                $this->stats['network_excluded']++;

                continue;
            }
            // Tramway moderne sur l'emprise ou la voirie d'une ligne qui n'est plus exploitée : voie neuve.
            if (self::modernTramWay($w) && ! isset($this->exploitedCodes[(string) $line->code])) {
                $this->stats['modern_tram_excluded']++;

                continue;
            }
            $covered = $w['chunks'] * $this->step;
            if ($w['kind'] === 'ignore'
                || ($w['start'] === null && $w['end'] === null)
                || $w['chunks'] < self::MIN_WAY_CHUNKS
                || ($covered < 0.5 * $w['len'] && $covered < 1000)
                || $w['service']
                || ($public && in_array($w['usage'], ['industrial', 'military', 'test'], true))
                || ($line->code && $w['code'] && $w['code'] !== $line->code)) {
                continue;
            }
            $kept[$id] = $w;
        }

        // Écartement : celui de la ligne s'il est connu, sinon l'écartement dominant
        // (≥ 70 % des tronçons couverts par un way d'écartement connu).
        $gauge = $line->gauge_mm_default ? (int) $line->gauge_mm_default : null;
        if ($gauge === null) {
            $weights = [];
            foreach ($kept as $w) {
                if (count($w['gauges']) === 1) {
                    $weights[$w['gauges'][0]] = ($weights[$w['gauges'][0]] ?? 0) + $w['chunks'];
                }
            }
            arsort($weights);
            $total = array_sum($weights);
            if ($total > 0 && reset($weights) / $total >= 0.7) {
                $gauge = (int) array_key_first($weights);
            }
        }
        if ($gauge !== null) {
            $kept = array_filter($kept, function ($w) use ($gauge) {
                if ($w['gauges']) {
                    return (bool) array_filter($w['gauges'], fn ($g) => abs($g - $gauge) <= 15);
                }

                return ! ($w['narrow'] && $gauge >= 1435);
            });
        }

        return $kept;
    }

    /**
     * Way rattaché par vff:link-traces à une AUTRE ligne d'une autre catégorie de réseau
     * (principal, secondaire, particulier, militaire ; non renseignée = principal).
     *
     * @param  array<string, mixed>  $w
     */
    public static function otherNetworkWay(object $line, array $w): bool
    {
        return ($w['linked_line'] ?? null) !== null && (int) $w['linked_line'] !== (int) $line->id
            && ($w['linked_network'] ?? 'principal') !== ($line->network ?? 'principal');
    }

    /**
     * start_date de remplissage : l'année de FIN de la période d'une carte source (« French
     * military maps (Etat-Major) for the period 1820 to 1866 » → start_date=1866). Le way
     * figure sur la carte (ou a été tracé d'après elle), ce n'est pas une ouverture : la date
     * est fausse pour presque toutes les lignes concernées (Pont-de-la-Deûle - Bachy-Mouchin,
     * inaugurée en 1883 ; tramway de Bergues à Rexpoëde, 1894 ; Chaugey - Lons-le-Saunier,
     * 1905 ; même une LGV). Seule l'année seule est visée : une date au jour près reste lue.
     *
     * @param  array<string, mixed>  $tags
     */
    public static function placeholderStart(array $tags): bool
    {
        $start = trim((string) ($tags['start_date'] ?? ''));

        return preg_match('/^\d{4}$/', $start) === 1
            && preg_match('/\bperiod\s+(\d{4})\s*(?:to|-|–)\s*(\d{4})\b/i', (string) ($tags['source'] ?? ''), $m) === 1
            && $start === $m[2];
    }

    /**
     * Way de tramway moderne : railway=tram / light_rail ouvert à partir de MODERN_TRAM_YEAR.
     *
     * @param  array<string, mixed>  $w
     */
    public static function modernTramWay(array $w): bool
    {
        return in_array($w['layer'] ?? null, self::MODERN_TRAM_LAYERS, true)
            && isset($w['start']['date']) && (int) substr((string) $w['start']['date'], 0, 4) >= self::MODERN_TRAM_YEAR;
    }

    /**
     * Événements d'un tronçon couvert par un ensemble de ways.
     *
     * @param  array<int, array<string, mixed>>  $ways
     * @return array<int, array<string, mixed>>
     */
    private function timeline(array $ways): array
    {
        $events = [];
        $actives = array_values(array_filter($ways, fn ($w) => $w['kind'] === 'active'));

        // Périodes d'exploitation : ways actifs + disused « vie de la ligne ».
        $periods = [];
        foreach ($actives as $w) {
            $periods[] = $this->period($w, false);
        }
        foreach ($ways as $w) {
            if ($w['kind'] !== 'disused') {
                continue;
            }
            $startDay = $w['start'] ? self::day($w['start']['date']) : null;
            // « Période d'abandon » si un way actif s'est terminé avant (ou vers) son début.
            $isState = $startDay !== null && (bool) array_filter($actives, fn ($a) => $a['end'] && self::endDay($a['end']) <= $startDay + self::TOLERANCE_DAYS);
            if ($isState) {
                // Période d'abandon : sa fin (sans réouverture) = dépose.
                if ($w['end'] && ! array_filter($actives, fn ($a) => $a['start'] && abs(self::day($a['start']['date']) - self::endDay($w['end'])) <= self::TOLERANCE_DAYS)) {
                    $events[] = $this->event('dismantling', $w['end'], [$w], 'low', 'fin du way railway=disused');
                }

                continue;
            }
            $periods[] = $this->period($w, true);
        }
        foreach ($ways as $w) {
            if ($w['kind'] === 'gone' && $w['start']) {
                $events[] = $this->event('dismantling', $w['start'], [$w], 'low', "début du way railway={$w['layer']}");
            }
        }
        $periods = array_values(array_filter($periods));
        if (! $periods) {
            return $events;
        }

        usort($periods, fn ($a, $b) => $a['s'] <=> $b['s']);
        $groups = [];
        foreach ($periods as $p) {
            $last = array_key_last($groups);
            if ($last !== null && $p['s'] <= $groups[$last]['e'] + self::TOLERANCE_DAYS) {
                $groups[$last]['e'] = max($groups[$last]['e'], $p['e']);
                $groups[$last]['periods'][] = $p;
            } else {
                $groups[] = ['s' => $p['s'], 'e' => $p['e'], 'periods' => [$p]];
            }
        }

        foreach ($groups as $k => $group) {
            // Début : parmi les ways qui démarrent l'année la plus ancienne, la date la plus précise.
            $starts = array_filter($group['periods'], fn ($p) => $p['w']['start'] !== null);
            if ($group['s'] > PHP_INT_MIN && $starts) {
                $year = min(array_map(fn ($p) => $p['w']['start']['year'], $starts));
                $pick = $this->mostPrecise(array_filter($starts, fn ($p) => $p['w']['start']['year'] === $year), 'start', false);
                $w = $pick['w'];
                $freight = $w['mode'] === 'freight';
                $type = $k === 0 ? ($freight ? 'opening_freight' : 'opening') : ($freight ? 'reopening_freight' : 'reopening');
                $events[] = $this->event($type, $w['start'], [$w], $w['start']['approx'] ? 'low' : 'medium', $pick['life'] ? 'railway=disused daté (dates de vie de la ligne)' : null);
            }

            // Fin : parmi les ways qui finissent l'année la plus tardive, la date la plus précise.
            $ends = array_filter($group['periods'], fn ($p) => $p['w']['end'] !== null && ! isset($p['w']['openOnly']));
            if ($group['e'] < PHP_INT_MAX && $ends) {
                $year = max(array_map(fn ($p) => $p['w']['end']['year'], $ends));
                $pick = $this->mostPrecise(array_filter($ends, fn ($p) => $p['w']['end']['year'] === $year), 'end', true);
                $w = $pick['w'];
                $events[] = $this->event('closure', $w['end'], [$w], $w['end']['approx'] ? 'low' : 'medium', $pick['life'] ? 'railway=disused daté (dates de vie de la ligne)' : null);
            }

            array_push($events, ...$this->transitions($group));
        }

        return $events;
    }

    /**
     * Changements de trafic / d'électrification aux raccords entre ways successifs.
     *
     * @param  array{s: int, e: int, periods: array<int, array<string, mixed>>}  $group
     * @return array<int, array<string, mixed>>
     */
    private function transitions(array $group): array
    {
        if (count($group['periods']) < 2) {
            return [];
        }
        $bounds = [];
        foreach ($group['periods'] as $p) {
            if ($p['s'] > $group['s'] && $p['w']['start']) {
                $bounds[] = ['t' => $p['s'], 'w' => $p['w'], 'date' => $p['w']['start'], 'rank' => 0];
            }
            if ($p['e'] < $group['e'] && $p['w']['end']) {
                $bounds[] = ['t' => $p['e'], 'w' => $p['w'], 'date' => $p['w']['end'], 'rank' => 1];
            }
        }
        usort($bounds, fn ($a, $b) => [$a['t'], $a['rank']] <=> [$b['t'], $b['rank']]);

        $events = [];
        $state = $this->stateAt($group['periods'], $group['s']);
        $done = [];
        foreach ($bounds as $b) {
            if (isset($done[$b['t']])) {
                continue;
            }
            $done[$b['t']] = true;
            $next = $this->stateAt($group['periods'], $b['t']);
            if ($next === null) {
                continue; // trou toléré : on compare avec l'état suivant non vide
            }
            if ($state !== null) {
                $type = match (true) {
                    in_array($state['mode'], [null, 'mixed'], true) && $next['mode'] === 'freight' => 'closure_passenger',
                    $state['mode'] === 'freight' && $next['mode'] === 'mixed' => 'reopening_passenger',
                    $state['mode'] === 'mixed' && $next['mode'] === 'passenger' => 'closure_freight',
                    $state['mode'] === 'passenger' && $next['mode'] === 'mixed' => 'reopening_freight',
                    default => null,
                };
                if ($type) {
                    $events[] = $this->event($type, $b['date'], [$b['w']], $state['mode'] === null ? 'low' : 'medium', 'changement de trafic entre ways successifs');
                }
                if ($state['elec'] === false && $next['elec'] === true) {
                    $events[] = $this->event('electrification', $b['date'], [$b['w']], 'medium', 'way electrified=no relayé par un way électrifié');
                }
            }
            $state = $next;
        }

        return $events;
    }

    /**
     * État strict (trafic, électrification) à l'instant t : une valeur n'est
     * retenue que si tous les ways actifs la partagent.
     *
     * @param  array<int, array<string, mixed>>  $periods
     * @return array{mode: ?string, elec: ?bool}|null
     */
    private function stateAt(array $periods, int $t): ?array
    {
        $on = array_filter($periods, fn ($p) => $p['s'] <= $t && $t < $p['e']);
        if (! $on) {
            return null;
        }
        $modes = array_unique(array_map(fn ($p) => $p['w']['mode'] ?? '?', $on));
        $elecs = array_unique(array_map(fn ($p) => var_export($p['w']['elec'], true), $on));

        return [
            'mode' => count($modes) === 1 && $modes[array_key_first($modes)] !== '?' ? $modes[array_key_first($modes)] : (in_array('?', $modes, true) ? null : 'mixed'),
            'elec' => count($elecs) === 1 ? $on[array_key_first($on)]['w']['elec'] : null,
        ];
    }

    /** @return array{s: int, e: int, w: array<string, mixed>, life: bool}|null */
    private function period(array $w, bool $life): ?array
    {
        $s = $w['start'] ? self::day($w['start']['date']) : PHP_INT_MIN;
        if ($life && ! $w['end']) {
            // disused sans fin : on sait seulement qu'elle a ouvert ; fermeture inconnue.
            return $s === PHP_INT_MIN ? null : ['s' => $s, 'e' => $s + 1, 'w' => $w + ['openOnly' => true], 'life' => true];
        }
        $e = $w['end'] ? self::endDay($w['end']) : PHP_INT_MAX;

        return $e < $s ? null : ['s' => $s, 'e' => $e, 'w' => $w, 'life' => $life];
    }

    /** @param  array<int, array<string, mixed>>  $periods */
    private function mostPrecise(array $periods, string $bound, bool $latest): array
    {
        $rank = ['day' => 0, 'month' => 1, 'year' => 2];
        usort($periods, function ($a, $b) use ($rank, $bound, $latest) {
            $da = $a['w'][$bound];
            $db = $b['w'][$bound];

            return [$rank[$da['precision']], $a['life'] ? 1 : 0, $latest ? $db['date'] : $da['date']]
                <=> [$rank[$db['precision']], $b['life'] ? 1 : 0, $latest ? $da['date'] : $db['date']];
        });

        return $periods[0];
    }

    /** @param  array<int, array<string, mixed>>  $ways */
    private function event(string $type, array $date, array $ways, string $confidence, ?string $note): array
    {
        return [
            'type' => $type,
            'date' => $date['date'],
            'precision' => $date['precision'],
            'raw' => $date['raw'] ?? $date['date'],
            'confidence' => $confidence,
            'way' => $ways[0]['id'],
            'note' => $note,
        ];
    }

    /**
     * Regroupe les tronçons d'un même événement en portions continues.
     *
     * @param  array<int, array<string, mixed>>  $byChunk
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array<int, array<string, mixed>>
     */
    private function runs(array $byChunk, array $chunks): array
    {
        ksort($byChunk); // les id de tronçons suivent (comp, idx)
        $runs = [];
        $current = null;
        $prevId = null;
        foreach ($byChunk as $chunkId => $event) {
            $continues = false;
            if ($current !== null && $chunks[$chunkId]['comp'] === $chunks[$prevId]['comp']) {
                $gap = 0.0;
                $clean = true;
                for ($i = $prevId + 1; $i < $chunkId; $i++) {
                    if (isset($chunks[$i])) { // sinon : doublon écarté, couvert par un autre tronçon
                        $gap += $chunks[$i]['len'];
                        $clean = $clean && ! $chunks[$i]['ways'];
                    }
                }
                $continues = $gap <= self::MAX_GAP_M && $clean;
                if ($continues) {
                    for ($i = $prevId + 1; $i < $chunkId; $i++) {
                        if (isset($chunks[$i])) {
                            $current['chunks'][] = $i;
                        }
                    }
                }
            }
            if (! $continues) {
                if ($current) {
                    $runs[] = $current;
                }
                $current = ['chunks' => [], 'events' => []];
            }
            $current['chunks'][] = $chunkId;
            $current['events'][] = $event;
            $prevId = $chunkId;
        }
        if ($current) {
            $runs[] = $current;
        }

        // Regroupement des portions voisines (≤ CLUSTER_M entre extrémités) : les
        // formes SNCF d'un même code ne se raccordent pas toujours exactement, et
        // une même date peut venir de plusieurs ways contigus.
        foreach ($runs as &$run) {
            $run['ends'] = [$chunks[$run['chunks'][0]]['a'], $chunks[$run['chunks'][array_key_last($run['chunks'])]]['b']];
        }
        unset($run);
        $parent = array_keys($runs);
        $find = function (int $i) use (&$parent, &$find): int {
            return $parent[$i] === $i ? $i : ($parent[$i] = $find($parent[$i]));
        };
        for ($i = 0; $i < count($runs); $i++) {
            for ($j = $i + 1; $j < count($runs); $j++) {
                foreach ($runs[$i]['ends'] as $p) {
                    foreach ($runs[$j]['ends'] as $q) {
                        if (hypot($p[0] - $q[0], $p[1] - $q[1]) <= self::CLUSTER_M) {
                            $parent[$find($j)] = $find($i);
                        }
                    }
                }
            }
        }
        $clusters = [];
        foreach ($runs as $i => $run) {
            $root = $find($i);
            $clusters[$root]['chunks'] = array_merge($clusters[$root]['chunks'] ?? [], $run['chunks']);
            $clusters[$root]['events'] = array_merge($clusters[$root]['events'] ?? [], $run['events']);
            $clusters[$root]['ends'] = array_merge($clusters[$root]['ends'] ?? [], $run['ends']);
        }

        $out = [];
        foreach ($clusters as $cluster) {
            $length = array_sum(array_map(fn ($id) => $chunks[$id]['len'], $cluster['chunks']));
            if ($length < self::MIN_RUN_M) {
                continue;
            }
            $wayCounts = array_count_values(array_map(fn ($e) => $e['way'], $cluster['events']));
            arsort($wayCounts);
            $main = (int) array_key_first($wayCounts);
            $first = $cluster['events'][0];
            $ways = array_keys($wayCounts);
            sort($ways);
            $confidences = array_count_values(array_map(fn ($e) => $e['confidence'], $cluster['events']));
            arsort($confidences);
            $notes = array_values(array_unique(array_filter(array_map(fn ($e) => $e['note'], $cluster['events']))));

            // Extrémités de la portion : les deux bouts de runs les plus éloignés.
            [$a, $b] = [$cluster['ends'][0], $cluster['ends'][1]];
            $best = -1.0;
            foreach ($cluster['ends'] as $p) {
                foreach ($cluster['ends'] as $q) {
                    $d = hypot($p[0] - $q[0], $p[1] - $q[1]);
                    if ($d > $best) {
                        [$best, $a, $b] = [$d, $p, $q];
                    }
                }
            }

            $out[] = [
                'type' => $first['type'],
                'date' => $first['date'],
                'precision' => $first['precision'],
                'raw' => current(array_filter($cluster['events'], fn ($e) => $e['way'] === $main))['raw'] ?? $first['raw'],
                'confidence' => (string) array_key_first($confidences),
                'chunks' => $cluster['chunks'],
                'length' => $length,
                'main' => $main,
                'ways' => $ways,
                'notes' => $notes,
                'from' => $a,
                'to' => $b,
            ];
        }

        return $out;
    }

    /** @param  array<int, array<string, mixed>>  $kept */
    private function write(object $line, array $row, string $fingerprint, array $kept): void
    {
        $chunkIds = '{'.implode(',', array_unique($row['chunks'])).'}';
        $ends = DB::selectOne(<<<'SQL'
            WITH ends AS (SELECT ST_SetSRID(ST_MakePoint(?, ?), 2154) AS a, ST_SetSRID(ST_MakePoint(?, ?), 2154) AS b),
            -- Lieux nommés : gares VFF, sinon gares SNCF (Lambert-93, distance ≤ 3 km).
            places AS (
                SELECT name, ST_Transform(geom, 2154) AS p FROM stations
                UNION ALL
                SELECT libelle, ST_Transform(ST_SetSRID(ST_MakePoint(lon, lat), 4326), 2154)
                FROM sncf_gares WHERE lat IS NOT NULL AND lon IS NOT NULL AND libelle IS NOT NULL
            )
            SELECT
              (SELECT name FROM places, ends WHERE ST_DWithin(p, ends.a, 3000) ORDER BY ST_Distance(p, ends.a) LIMIT 1) AS from_name,
              (SELECT name FROM places, ends WHERE ST_DWithin(p, ends.b, 3000) ORDER BY ST_Distance(p, ends.b) LIMIT 1) AS to_name
            SQL, [$row['from'][0], $row['from'][1], $row['to'][0], $row['to'][1]]);
        $from = $ends->from_name;
        $to = $ends->to_name;

        $way = $kept[$row['main']];
        $km = number_format($row['length'] / 1000, 1, ',', ' ');
        $label = self::LABELS[$row['type']] ?? $row['type'];
        $portion = ($from && $to && $from !== $to) ? "de la portion {$from} - {$to} ({$km} km)" : "d'une portion de {$km} km".($from ? " près de {$from}" : '');
        $description = "{$label} {$portion}, d'après OpenHistoricalMap.";

        $quoteTags = array_intersect_key($way['tags'], array_flip(['railway', 'name', 'start_date', 'start_date:edtf', 'end_date', 'end_date:edtf', 'usage', 'railway:traffic_mode', 'electrified', 'gauge', 'source', 'source:url', 'wikidata']));
        $quote = "way {$way['osm']} : ".implode(', ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($quoteTags), $quoteTags));
        if (count($row['ways']) > 1) {
            $quote .= ' (+'.(count($row['ways']) - 1).' autre(s) way(s) OHM)';
        }
        if ($row['notes']) {
            $quote .= ' - '.implode(' ; ', $row['notes']);
        }

        if ($this->dryRun) {
            $this->line(sprintf('  [%s] %-20s %s (%s) %6s km  way %s  %s',
                $line->code ?? $line->id, $row['type'], $row['date'], $row['precision'], $km, $way['osm'], $portion));

            return;
        }

        DB::statement(<<<'SQL'
            INSERT INTO line_events (line_id, type, event_date, date_precision, description, section_from, section_to,
                                     section_resolution, section_geom, source, source_url, source_trace_id, source_quote,
                                     confidence, fingerprint, created_at, updated_at)
            SELECT ?, ?, CAST(? AS date), ?, ?, ?, ?, 'trace',
                   (SELECT ST_Multi(ST_Transform(ST_LineMerge(ST_Union(g)), 4326)) FROM ohm_chunks WHERE id = ANY(CAST(? AS int[]))),
                   'ohm', ?, ?, ?, ?, ?, NOW(), NOW()
            ON CONFLICT (fingerprint) DO UPDATE SET
                type = EXCLUDED.type, event_date = EXCLUDED.event_date, date_precision = EXCLUDED.date_precision,
                description = EXCLUDED.description, section_from = EXCLUDED.section_from, section_to = EXCLUDED.section_to,
                section_resolution = EXCLUDED.section_resolution, section_geom = EXCLUDED.section_geom,
                source_url = EXCLUDED.source_url, source_trace_id = EXCLUDED.source_trace_id,
                source_quote = EXCLUDED.source_quote, confidence = EXCLUDED.confidence, updated_at = NOW()
            SQL, [
            $line->id, $row['type'], $row['date'], $row['precision'], $description, $from, $to,
            $chunkIds,
            "https://www.openhistoricalmap.org/way/{$way['osm']}", $way['id'], mb_substr($quote, 0, 2000),
            $row['confidence'], $fingerprint,
        ]);
    }

    /** Supprime les événements OHM de la ligne qui n'ont pas été reproduits. */
    private function purge(int $lineId, array $fingerprints): void
    {
        if ($this->dryRun) {
            return;
        }
        $this->stats['deleted'] += DB::table('line_events')
            ->where('source', 'ohm')->where('line_id', $lineId)
            ->when($fingerprints, fn ($q) => $q->whereNotIn('fingerprint', $fingerprints))
            ->delete();
    }

    /**
     * Extrémités d'un nom de ligne (« Ligne de A à B », « Ligne A - B »,
     * « Chemin de fer de A à B »…), normalisées ; null si le nom ne s'y prête pas.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function nameEnds(string $name): ?array
    {
        $n = preg_replace('/\s*\(.*\)\s*$/u', '', trim($name));
        // (« Voie-mère de A à B », « Ligne militaire de A à B » : mêmes extrémités qu'une ligne.)
        $n = preg_replace('/^(ligne(?:\s+militaire)?|chemin\s+de\s+fer|tramway|raccordement|embranchement(?:\s+militaire)?|voies?[-\s]m[eè]res?|voie\s+industrielle)\s+(?:(?:de\s+la|de\s+l[\'’]|des|du|de|d[\'’])\s*)?/iu', '', $n, 1, $count);
        if (! $count) {
            return null;
        }
        // « a » sans accent : graphie fréquente des noms OHM (« Ligne de Fives a Abeville »).
        $parts = preg_split('/\s+(?:à|a|au|aux|-|–|—)\s+/u', $n);
        if (count($parts) !== 2) {
            return null;
        }
        $norm = function (string $place): string {
            $p = mb_strtolower(Str::ascii($place));
            $p = preg_replace('/\bsainte\b/', 'ste', $p);
            $p = preg_replace('/\bsaint\b/', 'st', $p);

            return trim(preg_replace('/[^a-z0-9]+/', '-', $p), '-');
        };
        $ends = [$norm($parts[0]), $norm($parts[1])];

        return in_array('', $ends, true) ? null : $ends;
    }

    /** Une extrémité commune (égale, ou préfixe à une frontière de mot : « nimes » ~ « nimes-courbessac ») ? */
    private static function endsMatch(array $a, array $b): bool
    {
        foreach ($a as $x) {
            foreach ($b as $y) {
                if ($x === $y || str_starts_with($x, $y.'-') || str_starts_with($y, $x.'-')) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function day(string $date): int
    {
        return intdiv((new \DateTimeImmutable($date.' 00:00:00', new \DateTimeZone('UTC')))->getTimestamp(), 86400);
    }

    /** Dernier jour couvert par une date de fin (année → 31/12, mois → fin de mois). */
    private static function endDay(array $date): int
    {
        $d = new \DateTimeImmutable($date['date'].' 00:00:00', new \DateTimeZone('UTC'));
        $d = match ($date['precision']) {
            'year' => $d->setDate((int) $d->format('Y'), 12, 31),
            'month' => $d->modify('last day of this month'),
            default => $d,
        };

        return intdiv($d->getTimestamp(), 86400);
    }
}
