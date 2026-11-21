<?php

namespace App\Console\Commands;

use App\Enums\SegmentStatus;
use App\Services\EraContinuity;
use App\Services\EraTimeline;
use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Génère `line_segments` pour CHAQUE ère (décennie) à partir du tracé de
 * référence des lignes et de leurs événements datés PAR PORTION.
 *
 * Méthode, ligne par ligne :
 *   1. Le tracé de référence (line_reference_parts, avec sa provenance) est
 *      découpé en tronçons de STEP_M mètres.
 *   2. Pour chaque tronçon, on note les événements qui le concernent :
 *      section_geom à moins de COVER_TOL_M (ou événement sur toute la ligne).
 *   3. Les tronçons ayant exactement les mêmes événements (et le même état
 *      actuel observé) forment une « portion » : une ligne fermée en partie en
 *      1980 donne deux portions, l'une avec l'événement de fermeture, l'autre sans.
 *   4. Chaque portion est rejouée par EraTimeline à la date de chaque ère
 *      (31/12 de l'année ; jour même pour l'ère courante) → statut (in_service / abandoned / dismantled) et
 *      trafic (passenger / freight / mixed / unknown) à cette époque.
 *   5. Continuité (cf. smooth()) : le long du tracé (morceaux de référence
 *      enchaînés par leurs extrémités), un morceau de moins de MIN_PIECE_M d'un
 *      état différent de ses deux voisins, ou un trou de moins de MAX_GAP_M au
 *      milieu d'une portion active, est absorbé par ses voisins quand aucun
 *      événement propre ne le justifie (bruit de découpage aux bords des
 *      sections, bout d'ancien tracé intercalé, observation ponctuelle).
 *
 * Ouvertures : une portion datée seulement par des ouvertures « toute la ligne »
 * prend l'ouverture de la portion datée la plus proche de la même ligne (cf.
 * inferOpenings), bornée par ces ouvertures globales ; à défaut, la plus ancienne
 * ouverture globale (EraTimeline::wholeLineOpening). Une ligne ouverte par étapes
 * n'apparaît donc plus entière dès sa première section quand ses autres sections
 * sont localisées.
 *
 * L'état ACTUEL observé de l'emprise (table present_states : voie en place,
 * déposée, voie verte…) est attaché à chaque portion : une voie disparue
 * n'apparaît « déposée » que sur la carte de la décennie qui suit sa dépose,
 * puis plus du tout (cf. EraTimeline::stateAt). Sans observation, un tracé de
 * ligne disparue (archeoferroviaire) est réputé disparu.
 *
 * Les événements dont la portion n'est pas résolue (section_resolution
 * pending / unresolved) sont IGNORÉS et comptés : relancer
 * vff:resolve-event-sections d'abord. Une portion sans aucune date
 * d'ouverture n'apparaît dans aucune ère historique ; pour l'ère courante on
 * retombe sur l'état connu aujourd'hui (disparue = absente, voie en place =
 * fermée, autres = en service). Pour l'ère courante, le statut officiel SNCF de
 * chaque tronçon (formes-des-lignes-du-rfn) est appliqué portion par portion ;
 * vff:classify-lines pose ensuite le trafic actuel officiel (voyageurs/fret).
 *
 *   php artisan vff:build-era-segments
 *   php artisan vff:build-era-segments --line=956000 --era=1980
 *   php artisan vff:build-era-segments --line=643000,413000,931
 */
class BuildEraSegments extends Command
{
    protected $signature = 'vff:build-era-segments
        {--line= : id ou code d\'une ligne, ou plusieurs séparés par des virgules (ex. 643000,413000)}
        {--era= : une ou plusieurs ères (années séparées par des virgules, ex. 1830,1840)}
        {--step=100 : longueur des tronçons d\'analyse (m)}
        {--shard= : traitement parallèle « i/n » (ex. 1/4) : ne prend que les lignes dont id % n = i-1}
        {--no-classify : ne relance pas vff:classify-lines sur l\'ère courante (passage unique)}
        {--no-enrich : avec --line, ne relance pas l\'électrification (vff:enrich-era-segments) ni les vitesses SNCF (vff:import-sncf-vmax)}';

    protected $description = 'Génère les segments de chaque décennie (statut + trafic par portion) depuis le tracé de référence et les événements datés.';

    private const COVER_TOL_M = 30;

    private const USABLE_RESOLUTIONS = ['pk', 'places', 'trace', 'whole_line'];

    /** Services ouverts par une ouverture générique, selon la catégorie du réseau. */
    private const DEFAULT_SERVICES = ['principal' => 'PF', 'secondaire' => 'PF', 'particulier' => 'F', 'militaire' => ''];

    /** Tracés de lignes disparues : sans observation contraire, réputés disparus aujourd'hui. */
    private const HISTORICAL_SOURCES = ['archeoferroviaire'];

    /** Distance max (m) entre une portion et une observation de son état actuel. */
    private const PRESENT_TOL_M = 15;

    /** Précision (m) des nœuds calculés entre tronçons d'un même groupe (ST_UnaryUnion). */
    private const GRID_M = 0.001;

    /** Tronçon plus court (m) : bout dégénéré, jamais inséré. */
    private const MIN_SEGMENT_M = 0.01;

    /** Distance max (m) entre une portion et le tronçon SNCF (forme généralisée) dont elle prend le statut. */
    private const SNCF_TOL_M = 200;

    /** Morceau plus court (m), d'un état différent de ses deux voisins et que rien ne justifie : absorbé. */
    private const MIN_PIECE_M = 200;

    /** Trou plus court (m) au milieu d'une portion active, que rien ne justifie : recousu. */
    private const MAX_GAP_M = 500;

    /** Distance max (m) entre les extrémités de deux morceaux du tracé de référence pour les enchaîner. */
    private const LINK_TOL_M = 30;

    /** Taille (m) des cases de la recherche de la portion datée la plus proche. */
    private const NEIGHBOUR_OPENING_M = 2000;

    /** Distance max (m) à la portion datée dont une portion datée « toute la ligne » prend l'ouverture : toute la ligne. */
    private const STAGED_NEIGHBOUR_OPENING_M = 100000;

    /** Anciens tracés : ne restent dans la référence que là où ils s'écartent de l'alignement actuel. */
    private const OLD_ALIGNMENT_SOURCES = ['archeoferroviaire', 'ohm'];

    /** Longueur max (m) d'une portion jamais datée ou tardive prise « en sandwich » entre deux portions datées. */
    private const SANDWICH_MAX_M = 20000;

    /** Raccordement (ou courte section) jamais daté : longueur max (m) hors nom « Raccordement… ». */
    private const SHORT_LINK_M = 5000;

    /** Distance max (m) entre l'extrémité d'un raccordement et la ligne qu'il relie. */
    private const JUNCTION_TOL_M = 50;

    private array $stats = ['undated_m' => 0, 'pieces' => 0, 'skipped_events' => 0, 'contradicted_closures' => 0, 'orphan_closures_used' => 0,
        'smoothed_m' => 0, 'inferred_opening_m' => 0, 'inferred_closure_m' => 0, 'junction_lines' => 0, 'sandwich_opening_m' => 0];

    /** Ouverture déduite des lignes reliées, pour la ligne en cours (cf. junctionOpening). */
    private ?string $junctionOpening = null;

    public function handle(): int
    {
        // Les états sont calculés sur TOUTES les ères (le lissage compare les portions sur
        // l'ensemble de la chronologie) : résultat identique avec ou sans --era.
        $allEras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
        $years = $this->option('era') ? array_map('intval', explode(',', $this->option('era'))) : null;
        $eras = $years ? $allEras->filter(fn ($e) => in_array((int) $e->year, $years, true))->values() : $allEras;
        $lines = $this->targetLines();
        $this->info(count($lines).' lignes × '.count($eras).' ères');

        $bar = $this->output->createProgressBar(count($lines));
        foreach ($lines as $line) {
            // 5 tentatives : Laravel rejoue la transaction sur interblocage (40P01), fréquent
            // quand plusieurs générations et la construction du graphe tournent en parallèle.
            DB::transaction(fn () => $this->buildLine($line, $eras, $allEras), 5);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->report($eras);

        // Passage unique (--line, ou toutes les lignes sans --shard) qui recalcule l'ère courante :
        // le trafic officiel (vff:classify-lines) remplace celui du moteur, sinon des lignes voyageurs
        // repassent en fret. En --shard, vff:rebuild-history le lance une seule fois après tous les processus.
        // Reconstruction par ligne (--line) : les tronçons régénérés ont perdu l'électrification et la
        // vitesse, posées après le moteur ; on les rejoue sur ces seules lignes (vff:rebuild-history
        // le fait une fois pour toutes après les --shard). Vitesses : cache local seulement, sans réseau.
        if ($this->option('line') && ! $this->option('shard') && ! $this->option('no-enrich')) {
            foreach ($lines as $line) {
                $this->call('vff:enrich-era-segments', ['--line' => (string) $line->id]);
            }
            if ($lines) {
                $this->call('vff:import-sncf-vmax', ['--line' => implode(',', array_map(fn ($l) => $l->id, $lines)), '--cache-only' => true, '--previous' => true]);
            }
        }

        $current = $eras->first(fn ($e) => $e->is_current);
        if ($current && ! $this->option('shard') && ! $this->option('no-classify')) {
            $this->call('vff:classify-lines', ['--era' => (string) $current->year]);
        }

        return self::SUCCESS;
    }

    private function targetLines(): array
    {
        $q = DB::table('lines')->join('line_reference_geometries as r', 'r.line_id', '=', 'lines.id')
            // LGV : même définition que la carte (is_lgv OU catlig SNCF OU nom), valable avant tout vff:classify-lines.
            ->select(['lines.id', 'lines.name', 'lines.network', 'lines.gauge_mm_default', 'r.length_m AS ref_length',
                DB::raw(Lgv::sql('lines', null).' AS is_lgv')])->orderBy('lines.id');
        if ($shard = $this->option('shard')) {
            [$i, $n] = array_map('intval', explode('/', $shard));
            $q->whereRaw('lines.id % ? = ?', [$n, $i - 1]);
        }
        if ($opt = $this->option('line')) {
            // Plusieurs lignes en un seul passage (vff:classify-lines une seule fois à la fin) :
            // un code SNCF a 6 chiffres, un id moins.
            $refs = array_values(array_filter(array_map('trim', explode(',', $opt)), fn ($r) => $r !== ''));
            $ids = array_map('intval', array_filter($refs, fn ($r) => ctype_digit($r) && strlen($r) < 6));
            $q->where(fn ($w) => $w->whereIn('lines.code', $refs)->orWhereIn('lines.id', $ids ?: [0]));
        }

        return $q->get()->all();
    }

    private function buildLine(object $line, $eras, $allEras): void
    {
        // Verrou par ligne (relâché à la fin de la transaction) : deux générations lancées en
        // parallèle sur la même ligne inséreraient sinon ses segments en double.
        DB::select('SELECT pg_advisory_xact_lock(?)', [$line->id]);

        [$events, $orphanReopenings, $orphanClosures, $sectionLen, $hasOpening] = $this->lineEvents($line);
        $ids = array_keys($events) ?: [0];
        $this->junctionOpening = $hasOpening ? null : $this->junctionOpening($line);

        // Portions des événements, découpées et indexées dans une table temporaire : la
        // recherche « quels événements couvrent ce tronçon de 100 m » passe par l'index GiST.
        DB::statement('CREATE TEMP TABLE IF NOT EXISTS tmp_event_parts (id bigint, g geometry)');
        DB::statement('CREATE INDEX IF NOT EXISTS tmp_event_parts_g ON tmp_event_parts USING GIST (g)');
        DB::statement('TRUNCATE tmp_event_parts');
        DB::insert("INSERT INTO tmp_event_parts
            SELECT id, ST_Subdivide(ST_Transform(section_geom, 2154), 32) FROM line_events
            WHERE id = ANY(CAST(? AS bigint[])) AND section_geom IS NOT NULL AND section_resolution IS DISTINCT FROM 'whole_line'",
            ['{'.implode(',', $ids).'}']);
        DB::statement('ANALYZE tmp_event_parts');

        // Tronçons de --step mètres et ce qui les concerne (supprimés à la fin de la transaction ; table
        // restée d'un passage précédent dans une transaction englobante, ex. tests : supprimée d'abord).
        DB::statement('DROP TABLE IF EXISTS tmp_chunks');
        DB::statement('CREATE TEMP TABLE tmp_chunks (part_id bigint, part_no int, i int, source text, c geometry, len float8,
            evs text, present text, sncf text, ign_active boolean, abroad boolean) ON COMMIT DROP');
        DB::insert(<<<'SQL'
            WITH parts AS (
                SELECT p.id AS part_id, p.source, d.path[1] AS part_no, d.geom AS g
                FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
                WHERE p.line_id = :line
            ),
            chunks AS MATERIALIZED (
                SELECT part_id, source, part_no, i, c, mid, ST_Transform(mid, 4326) AS mid4
                FROM (
                    SELECT part_id, source, part_no, i, ST_LineSubstring(g, i::float8 / n, (i + 1)::float8 / n) AS c
                    FROM parts,
                         LATERAL (SELECT GREATEST(1, CEIL(ST_Length(g) / :step))::int AS n) k,
                         LATERAL generate_series(0, n - 1) AS i
                ) t, LATERAL ST_LineInterpolatePoint(c, 0.5) AS mid
            ),
            -- MATERIALIZED : sinon PostgreSQL ré-inline ces CTE et refait les ST_Transform
            -- pour CHAQUE tronçon de 100 m (passe complète ~10× plus lente).
            ev_whole AS MATERIALIZED (
                -- Événements sans portion géométrique (toute la ligne) : s'appliquent partout.
                SELECT id FROM line_events WHERE id = ANY(CAST(:ids AS bigint[]))
                  AND (section_geom IS NULL OR section_resolution = 'whole_line')
            ),
            sncf_tr AS MATERIALIZED (
                -- Statut SNCF officiel actuel des tronçons de la ligne (formes généralisées :
                -- décalage possible de plus de 100 m en montagne, d'où la tolérance large).
                SELECT libelle, ST_Transform(geom, 2154) AS g
                FROM sncf_line_geometries WHERE code_ligne = (SELECT code FROM lines WHERE id = :line3)
            )
            INSERT INTO tmp_chunks
            SELECT part_id, part_no, i, source, c, ST_Length(c),
                   COALESCE((SELECT array_agg(x.id ORDER BY x.id) FROM (
                                 SELECT id FROM ev_whole
                                 UNION
                                 SELECT id FROM tmp_event_parts WHERE ST_DWithin(tmp_event_parts.g, mid, :tol)
                             ) x), '{}')::text AS evs,
                   -- Rails encore présents > disparition : une voie verte posée À CÔTÉ
                   -- d'une voie laissée en place ne fait pas disparaître la voie.
                   (SELECT ps.state || ':' || COALESCE(ps.since_year::text, '') FROM present_states ps
                    WHERE ps.geom && ST_Expand(mid4, 0.0004) AND ST_DWithin(ST_Transform(ps.geom, 2154), mid, :ptol)
                    ORDER BY (ps.state = 'track_in_place') DESC, ps.since_year NULLS LAST LIMIT 1) AS present,
                   (SELECT libelle FROM sncf_tr WHERE ST_DWithin(mid, sncf_tr.g, :stol)
                    ORDER BY sncf_tr.g <-> mid LIMIT 1) AS sncf,
                   -- Tronçon IGN « En service » sous la portion : preuve physique d'exploitation.
                   -- Filtre && en degrés autour du point (index GiST) avant le calcul en mètres.
                   EXISTS (SELECT 1 FROM source_traces st
                           WHERE st.source = 'bdtopo' AND st.geom && ST_Expand(mid4, 0.0001)
                             AND st.layer IN ('LGV', 'Voie ferrée principale')
                             AND st.properties->>'etat_de_l_objet' = 'En service'
                             AND ST_DWithin(ST_Transform(st.geom, 2154), mid, 5)) AS ign_active,
                   -- À l'étranger (près de la France, hors de toute commune et à plus de 300 m de la
                   -- côte ou de la frontière : même règle que PassengerPortions::abroad) : aucune
                   -- source d'état actuel n'y observe la voie (SNCF, IGN, OSM France).
                   (EXISTS (SELECT 1 FROM commune_contours cc WHERE cc.geom && ST_Expand(mid4, 0.2))
                    AND NOT EXISTS (SELECT 1 FROM commune_contours cc WHERE cc.geom && ST_Expand(mid4, 0.005)
                                    AND ST_DWithin(cc.geom::geography, mid4::geography, 300))) AS abroad
            FROM chunks
            SQL, ['line' => $line->id, 'line3' => $line->id, 'step' => (float) $this->option('step'),
            'ids' => '{'.implode(',', $ids).'}', 'tol' => self::COVER_TOL_M, 'ptol' => self::PRESENT_TOL_M, 'stol' => self::SNCF_TOL_M]);

        $chunks = DB::select('SELECT part_id, part_no, i, source, evs, present, sncf, ign_active, abroad, len,
                ST_X(ST_StartPoint(c)) AS sx, ST_Y(ST_StartPoint(c)) AS sy, ST_X(ST_EndPoint(c)) AS ex, ST_Y(ST_EndPoint(c)) AS ey
            FROM tmp_chunks ORDER BY part_id, part_no, i');

        DB::delete('DELETE FROM line_segments WHERE line_id = ? AND era_id = ANY(CAST(? AS bigint[]))',
            [$line->id, '{'.$eras->pluck('id')->implode(',').'}']);
        if (! $chunks) {
            return;
        }

        // LGV : une ouverture générique n'ouvre que le service voyageurs (« voyageurs seuls »
        // = grande vitesse) ; sinon 'PF' donnerait « mixte » malgré passengerImpliesFreight=false.
        $defaults = $line->is_lgv ? 'P' : (self::DEFAULT_SERVICES[$line->network ?? 'principal'] ?? 'PF');
        $lineActiveToday = (bool) DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM sncf_line_geometries WHERE code_ligne = (SELECT code FROM lines WHERE id = ?) AND libelle ILIKE 'Exploit%') AS a",
            [$line->id],
        )->a;

        // Portions : tronçons de mêmes événements et même état actuel observé.
        $pieces = [];
        $chunkKey = [];
        foreach ($chunks as $n => $c) {
            $k = implode('|', [$c->source, $c->evs, $c->present, $c->sncf, $c->ign_active ? 1 : 0, ($c->abroad ?? false) ? 1 : 0]);
            $chunkKey[$n] = $k;
            $pieces[$k] ??= $this->piece($c, $line, $events, $orphanReopenings, $orphanClosures, $defaults, $lineActiveToday);
        }
        $this->inferOpenings($chunks, $chunkKey, $pieces);
        $orders = $this->chainOrders($chunks);
        $this->inferSandwichedOpenings($chunks, $orders, $chunkKey, $pieces);
        $this->inferClosures($chunks, $chunkKey, $pieces, $orphanClosures, $defaults, ! $line->is_lgv);

        // État de chaque portion à chaque ère, et sa « signature » (états de toutes les ères).
        $prevYears = [];
        $prev = null;
        foreach ($allEras as $era) {
            $prevYears[$era->year] = $prev ?? (int) $era->year - 10;
            $prev = (int) $era->year;
        }
        $states = [];
        $sig = [];
        foreach (array_unique($chunkKey) as $k) {
            [$states[$k], $sig[$k], $pieces[$k]['dated']] = $this->eraStates($pieces[$k], $allEras, $prevYears, $defaults, ! $line->is_lgv);
        }

        $this->stats['smoothed_m'] += $this->smoothLine($chunks, $orders, $chunkKey, $pieces, $sig, $sectionLen);

        // Géométrie de chaque groupe (portion retenue × provenance du tracé).
        $groups = [];
        $u = ['pid' => [], 'pno' => [], 'i' => [], 'g' => []];
        foreach ($chunks as $n => $c) {
            $u['pid'][] = $c->part_id;
            $u['pno'][] = $c->part_no;
            $u['i'][] = $c->i;
            $u['g'][] = $groups[$chunkKey[$n]."\n".$c->source] ??= count($groups);
        }
        $groupKeys = array_flip($groups);
        $geoms = DB::select(<<<'SQL'
            SELECT u.g,
                   -- ST_UnaryUnion : deux morceaux de référence recalés sur la même voie donneraient
                   -- sinon des tronçons identiques, insérés en double. Nœuds calculés au millimètre
                   -- (GRID_M) : une extrémité posée à 1e-9 m près sur une autre portion ne laisse plus
                   -- de bout de longueur nulle.
                   encode(ST_AsEWKB(ST_Transform(ST_LineMerge(ST_UnaryUnion(ST_Collect(t.c ORDER BY t.part_id, t.part_no, t.i), :grid)), 4326)), 'hex') AS ewkb,
                   ROUND(SUM(t.len))::int AS len
            FROM unnest(CAST(:pid AS bigint[]), CAST(:pno AS int[]), CAST(:i AS int[]), CAST(:g AS int[])) AS u(part_id, part_no, i, g)
            JOIN tmp_chunks t ON t.part_id = u.part_id AND t.part_no = u.part_no AND t.i = u.i
            GROUP BY u.g
            SQL, ['pid' => '{'.implode(',', $u['pid']).'}', 'pno' => '{'.implode(',', $u['pno']).'}',
            'i' => '{'.implode(',', $u['i']).'}', 'g' => '{'.implode(',', $u['g']).'}', 'grid' => self::GRID_M]);

        $rows = ['ewkb' => [], 'era' => [], 'status' => [], 'traffic' => [], 'source' => [], 'reuse' => [], 'heritage' => []];
        foreach ($geoms as $geom) {
            [$k, $source] = explode("\n", $groupKeys[$geom->g], 2);
            $this->stats['pieces']++;
            if (! $pieces[$k]['dated']) {
                $this->stats['undated_m'] += $geom->len;
            }
            foreach ($eras as $era) {
                $state = $states[$k][$era->id];
                if (! $state) {
                    continue;
                }
                $rows['ewkb'][] = $geom->ewkb;
                $rows['era'][] = $era->id;
                $rows['status'][] = $state['status'];
                $rows['traffic'][] = $state['traffic'];
                $rows['source'][] = $source;
                $rows['reuse'][] = $state['reuse'] ?? '';
                $rows['heritage'][] = $state['heritage'] ?? '';
            }
        }

        if (! $rows['ewkb']) {
            return;
        }

        // Insertion groupée ; line_segments.geom est un LineString : on éclate les MultiLineString.
        DB::insert(<<<'SQL'
            INSERT INTO line_segments (line_id, era_id, geom, status, reuse, is_heritage, heritage_kind, traffic_type, source, gauge_mm, length_meters, created_at, updated_at)
            SELECT :line, r.era, d.geom, r.status, NULLIF(r.reuse, ''), r.heritage <> '', NULLIF(r.heritage, ''), r.traffic, r.source, :gauge,
                   ROUND(ST_Length(d.geom::geography))::int, NOW(), NOW()
            FROM unnest(CAST(:ewkb AS text[]), CAST(:era AS bigint[]), CAST(:status AS text[]), CAST(:traffic AS text[]), CAST(:source AS text[]),
                        CAST(:reuse AS text[]), CAST(:heritage AS text[]))
                 AS r(ewkb, era, status, traffic, source, reuse, heritage),
                 LATERAL ST_Dump(ST_GeomFromEWKB(decode(r.ewkb, 'hex'))) d
            WHERE ST_NPoints(d.geom) >= 2 AND ST_Length(d.geom::geography) >= :min_len
            SQL, [
            'line' => $line->id,
            'min_len' => self::MIN_SEGMENT_M,
            'gauge' => $line->gauge_mm_default,
            'ewkb' => $this->pgArray($rows['ewkb']),
            'era' => '{'.implode(',', $rows['era']).'}',
            'status' => $this->pgArray($rows['status']),
            'traffic' => $this->pgArray($rows['traffic']),
            'source' => $this->pgArray($rows['source']),
            'reuse' => $this->pgArray($rows['reuse']),
            'heritage' => $this->pgArray($rows['heritage']),
        ]);
    }

    /**
     * Événements de la ligne : utilisables (portion résolue ou toute la ligne), et à part
     * les réouvertures et fermetures dont la portion n'a pas pu être localisée.
     *
     * @return array{0: array<int, array>, 1: array<int, array>, 2: array<int, array>, 3: array<int, int>}
     *                                                                                                     [événements, réouvertures orphelines, fermetures orphelines, longueur (m) de la section des événements localisés]
     */
    private function lineEvents(object $line): array
    {
        $events = [];
        $orphanReopenings = [];
        $orphanClosures = [];
        $sectionLen = [];
        $hasOpening = false;
        foreach (DB::select('SELECT id, type, reuse, heritage_kind, event_date, source, source_url, confidence, section_resolution, section_from, section_to, section_pk_start_m,
                                    COALESCE(section_length_m, ROUND(ST_Length(section_geom::geography))) AS section_len
                             FROM line_events WHERE line_id = ?', [$line->id]) as $e) {
            // LGV ou raccordement LGV : rien avant la grande vitesse (une date du XIXe siècle vient
            // d'un tracé classique voisin, ex. way OHM d'une ligne classique sous la tolérance).
            if ($line->is_lgv && EraTimeline::predatesHighSpeed($e->type, $e->event_date)) {
                continue;
            }
            $hasOpening = $hasOpening || (bool) preg_match('/^(re)?opening|^heritage_start/', $e->type);
            $whole = $e->section_resolution === null && $e->section_from === null && $e->section_pk_start_m === null;
            if (! $whole && ! in_array($e->section_resolution, self::USABLE_RESOLUTIONS, true)) {
                // Réouverture sans AUCUNE portion citée : la source parle de la ligne en
                // général. Elle s'applique aux portions encore exploitées (cf. piece()).
                if (str_starts_with($e->type, 'reopening') && $e->section_from === null
                    && $e->section_to === null && $e->section_pk_start_m === null) {
                    $orphanReopenings[$e->id] = ['type' => $e->type, 'date' => $e->event_date, 'source' => EraTimeline::rankedSource($e->source, $e->source_url, $e->confidence),
                        'reuse' => null, 'heritage_kind' => null, 'whole' => true];
                }
                // Fermeture dont la portion n'a pas pu être localisée : servira aux portions
                // fermées ou disparues aujourd'hui qui n'ont aucune fermeture propre (cf. piece()).
                if (str_starts_with($e->type, 'closure') || in_array($e->type, ['declassification', 'dismantling'], true)) {
                    $orphanClosures[$e->id] = ['type' => $e->type, 'date' => $e->event_date,
                        'source' => EraTimeline::rankedSource($e->source, $e->source_url, $e->confidence),
                        'reuse' => null, 'heritage_kind' => null, 'whole' => true];
                }
                $this->stats['skipped_events']++;

                continue;
            }
            // Rang de la source : tableau PDF lignes-oubliees (décision administrative), contribution selon la source citée.
            $source = EraTimeline::rankedSource($e->source, $e->source_url, $e->confidence);
            $events[$e->id] = ['type' => $e->type, 'date' => $e->event_date, 'source' => $source, 'reuse' => $e->reuse,
                'heritage_kind' => $e->heritage_kind, 'whole' => $whole || $e->section_resolution === 'whole_line'];
            if (! $events[$e->id]['whole'] && $e->section_len !== null && (self::isEnd($e->type) || preg_match('/^(re)?opening|^heritage/', $e->type))) {
                $sectionLen[$e->id] = (int) $e->section_len;
            }
        }

        return [$events, $orphanReopenings, $orphanClosures, $sectionLen, $hasOpening];
    }

    /**
     * Une portion (attributs communs de ses tronçons) : événements retenus après les
     * garde-fous (fermetures contredites par une exploitation actuelle, réouvertures
     * non localisées, fermetures non localisées) et état actuel observé.
     */
    private function piece(object $c, object $line, array $events, array $orphanReopenings, array $orphanClosures, string $defaults, bool $lineActiveToday): array
    {
        $evIds = array_values(array_filter(explode(',', trim($c->evs, '{}'))));
        [$presentState, $presentSince] = $c->present ? explode(':', $c->present) + [null, null] : [null, null];
        $presentSince = $presentSince !== '' && $presentSince !== null ? (int) $presentSince : null;
        $oldAlignment = in_array($c->source, self::OLD_ALIGNMENT_SOURCES, true);
        // Statut SNCF actuel du tronçon : « déposée » vaut preuve de disparition faute d'observation.
        // Ignoré pour les anciens tracés (archeo, OHM) : ils ne restent dans le tracé de
        // référence que là où ils s'écartent de l'alignement actuel, et la tolérance large
        // (formes SNCF généralisées) leur prêterait à tort le statut de la ligne voisine.
        $sncfStatus = $c->sncf && ! $oldAlignment ? SegmentStatus::fromSncfLibelle($c->sncf) : null;
        if ($presentState === null && $sncfStatus === SegmentStatus::Dismantled) {
            $presentState = 'dismantled';
        }
        $historical = in_array($c->source, self::HISTORICAL_SOURCES, true);
        $closedToday = ($presentState !== null ? in_array($presentState, EraTimeline::GONE_STATES, true) : $historical)
            || ($sncfStatus !== null && ! $sncfStatus->isActive() && ! $c->ign_active);
        $pieceEvents = array_values(array_intersect_key($events, array_flip($evIds)));
        if ($sncfStatus?->isActive()) {
            $pieceEvents = $this->dropContradictedClosures($pieceEvents);
        } elseif (EraContinuity::wholeLineClosureContradicted($lineActiveToday, $closedToday)) {
            // Une partie de la ligne est exploitée : une fermeture « toute la ligne » sans
            // réouverture est fausse là où la portion vit encore, même si la forme SNCF ne colle
            // pas. Elle reste valable pour une portion fermée ou disparue aujourd'hui (ex.
            // Hirondelles : Saint-Claude - Oyonnax fermée en 2017, Andelot - Saint-Claude ouverte).
            $pieceEvents = $this->dropContradictedClosures($pieceEvents, wholeLineOnly: true);
        }
        if ($c->ign_active && ! $oldAlignment) {
            // Voie en service aujourd'hui selon l'IGN : un déclassement sans réouverture est un
            // acte administratif (ex. transfert à la RATP du RER A en 1969), pas une fermeture.
            $pieceEvents = $this->dropContradictedClosures($pieceEvents, types: ['declassification']);
        }
        if (($sncfStatus?->isActive() || $c->ign_active) && ! $oldAlignment) {
            $pieceEvents = $this->reopeningsForActivePiece($pieceEvents, $orphanReopenings);
        }
        if ($sncfStatus?->isActive()) {
            $pieceEvents = $this->dropContradictedPartialClosures($pieceEvents, $defaults, ! $line->is_lgv);
        }
        // Portion fermée ou disparue AUJOURD'HUI sans aucune fermeture localisée : elle a
        // forcément fermé un jour ; on lui applique les fermetures de la ligne dont la portion
        // n'a pas pu être localisée (sinon elle resterait « en service » jusqu'en 2020).
        $goneOrClosedToday = $closedToday;
        $hasClosure = (bool) array_filter($pieceEvents, fn ($e) => self::isEnd($e['type']));
        if ($goneOrClosedToday && ! $hasClosure && $orphanClosures) {
            $pieceEvents = array_merge($pieceEvents, array_values($orphanClosures));
            $this->stats['orphan_closures_used']++;
        }
        // Raccordement jamais daté encore en place : ouvert au plus tôt avec la plus récente des
        // lignes qu'il relie (confiance basse, cf. junctionOpening).
        if ($this->junctionOpening !== null && ! $goneOrClosedToday && ! $oldAlignment) {
            $pieceEvents[] = ['type' => 'opening', 'date' => $this->junctionOpening, 'source' => 'raccordement',
                'reuse' => null, 'heritage_kind' => null, 'whole' => true];
        }

        // Ouvertures : propre à la portion (section localisée) ou seulement « toute la ligne ».
        $own = null;
        $wholeOpenings = [];
        foreach ($pieceEvents as $e) {
            if (str_starts_with($e['type'], 'opening')) {
                if ($e['whole'] ?? false) {
                    $wholeOpenings[] = $e['date'];
                }
            }
        }
        // Fermetures : définitive propre à la portion (pour ses voisines), rang de sa meilleure
        // fermeture (pour qu'une fermeture déduite ne l'écarte pas à l'arbitrage), dernier événement.
        $ownFinal = null;
        $closureRank = null;
        $lastEvent = null;
        $kept = EraTimeline::dedupe($pieceEvents);
        // Fin des voyageurs ET fin du fret de la portion (sans réouverture après) : fin de tout
        // trafic, aussi définitive qu'une fermeture totale pour ses voisines (4194 Le Mans - La
        // Chartre : voyageurs et marchandises fermés le 1er mars 1947, section par section).
        // Proposée aux voisines à défaut de fermeture définitive voisine (cf. inferClosures).
        $pairedEnd = EraContinuity::pairedServiceEnd($kept);
        foreach ($kept as $e) {
            if (str_starts_with($e['type'], 'opening') && ! $e['whole'] && ($own === null || $e['date'] < $own['date'])) {
                $own = ['date' => $e['date'], 'source' => $e['source']];
            }
            if (in_array($e['type'], ['closure', 'declassification', 'dismantling', 'conversion'], true) && ! $e['whole']
                && ($ownFinal === null || $e['date'] < $ownFinal['date'])) {
                $ownFinal = ['date' => $e['date'], 'source' => $e['source']];
            }
            if (str_starts_with($e['type'], 'closure')) {
                $closureRank ??= ['source' => $e['source'], 'whole' => $e['whole']];
            }
            // (Seuls les événements de la chronologie comptent : pas une fin de concession.)
            if (preg_match('/^(re)?opening|^closure|^heritage/', $e['type'])) {
                $lastEvent = max($lastEvent ?? $e['date'], $e['date']);
            }
        }
        $wholeOnly = $own === null && $wholeOpenings;
        $completion = $wholeOnly ? EraTimeline::completionOpening($pieceEvents)['date'] : null;
        $firstEnd = null;
        foreach ($pieceEvents as $e) {
            if (self::isEnd($e['type'])) {
                $firstEnd = min($firstEnd ?? $e['date'], $e['date']);
            }
        }

        return ['events' => $pieceEvents, 'evIds' => $evIds, 'present' => $presentState, 'since' => $presentSince,
            'historical' => $historical, 'abroad' => (bool) ($c->abroad ?? false), 'gone' => $presentState !== null ? in_array($presentState, EraTimeline::GONE_STATES, true) : $historical,
            'sncf' => $sncfStatus, 'ign' => (bool) $c->ign_active, 'own_opening' => $own, 'whole_only' => $wholeOnly,
            'whole_first' => $wholeOpenings ? min($wholeOpenings) : null,
            'whole_last' => $completion !== null && $completion > min($wholeOpenings) ? $completion : null,
            'first_end' => $firstEnd, 'dated' => false,
            // (Une voie transférée en voie de service roule encore : pas de fermeture à déduire.)
            'closed_today' => $goneOrClosedToday && $sncfStatus !== SegmentStatus::ServiceTrack,
            'recent_closure' => (bool) array_filter($pieceEvents, fn ($e) => EraTimeline::isRecentSourcedClosure($e, (int) now()->year)),
            // Reprise associative sans fermeture connue = fin du trafic commercial, sauf sur le
            // réseau principal là où SNCF montre encore une exploitation (EraTimeline::heritageEndsCommercialTraffic).
            'heritage_ends' => ($line->network ?? 'principal') !== 'principal' || ! $sncfStatus?->isActive(),
            'own_final' => $ownFinal, 'paired_end' => $pairedEnd, 'closure_rank' => $closureRank, 'last_event' => $lastEvent];
    }

    /**
     * Portion fermée ou disparue AUJOURD'HUI (observation, statut SNCF, ancien tracé) que
     * sa chronologie laisse pourtant en service : la date de sa fermeture manque, et elle
     * « vivrait » jusqu'en 2020 pour disparaître en 2030. Elle prend la fermeture
     * définitive (fermeture, déclassement, dépose) de la portion la plus proche de la
     * même ligne qui en a une localisée, à défaut la première fermeture non localisée de
     * la ligne, à condition qu'elle suive tous ses propres événements (une portion encore
     * desservie en 1980 n'a pas fermé en 1970). Fermeture de tout trafic : la voie
     * disparue est ensuite traitée par EraTimeline (déposée dans l'ère suivante).
     */
    private function inferClosures(array $chunks, array &$chunkKey, array &$pieces, array $orphanClosures, string $defaults, bool $passengerImpliesFreight): void
    {
        $year = (int) now()->year;
        $stillOpen = [];
        $open = function (string $k) use (&$stillOpen, &$pieces, $defaults, $passengerImpliesFreight, $year): bool {
            if (! isset($stillOpen[$k])) {
                $p = $pieces[$k];
                $s = $p['closed_today'] ? (new EraTimeline($p['events'], $defaults, $passengerImpliesFreight))
                    ->heritageEndsCommercialTraffic($p['heritage_ends'])->stateAt($year, true, null, now()->toDateString()) : null;
                $stillOpen[$k] = $s !== null && $s['status'] === 'in_service' && empty($s['heritage']);
            }

            return $stillOpen[$k];
        };
        $orphanFinal = array_values(array_filter($orphanClosures, fn ($e) => in_array($e['type'], ['closure', 'declassification', 'dismantling'], true)));
        usort($orphanFinal, fn ($a, $b) => $a['date'] <=> $b['date']);

        $cell = self::NEIGHBOUR_OPENING_M;
        $mid = fn ($c) => [($c->sx + $c->ex) / 2, ($c->sy + $c->ey) / 2];
        $grid = [];
        $pairedGrid = [];
        foreach ($chunks as $n => $c) {
            [$x, $y] = $mid($c);
            if ($final = $pieces[$chunkKey[$n]]['own_final']) {
                $grid[(int) floor($x / $cell)][(int) floor($y / $cell)][] = [$x, $y, $final];
            }
            if ($paired = $pieces[$chunkKey[$n]]['paired_end']) {
                $pairedGrid[(int) floor($x / $cell)][(int) floor($y / $cell)][] = [$x, $y, $paired];
            }
        }
        foreach ($chunks as $n => $c) {
            $k = $chunkKey[$n];
            if (! $open($k)) {
                continue;
            }
            $p = $pieces[$k];
            [$x, $y] = $mid($c);
            // La voisine la plus proche qui a fermé APRÈS le dernier événement de la portion : une
            // voisine plus proche fermée avant (ex. 647000, Vic - Auch fermée en 1959, Bon-Encontre -
            // Auch encore desservie aux voyageurs jusqu'en 1970) ne la date pas.
            $after = fn (array $v) => $p['last_event'] === null || $v['date'] > $p['last_event'];
            $best = $grid ? EraContinuity::nearest($grid, $x, $y, $cell, self::STAGED_NEIGHBOUR_OPENING_M, $after) : null;
            $closure = EraContinuity::neighbourClosure($best, $orphanFinal, $p['last_event']);
            // À défaut de fermeture définitive voisine (fermeture, déclassement, dépose) ou de la ligne,
            // la fin des deux services d'une voisine (4194) : jamais à la place d'une fermeture définitive.
            $closure ??= $pairedGrid ? EraContinuity::nearest($pairedGrid, $x, $y, $cell, self::STAGED_NEIGHBOUR_OPENING_M, $after) : null;
            if ($closure === null) {
                continue;
            }
            $nk = $k.'|fermeture:'.$closure['date'];
            if (! isset($pieces[$nk])) {
                $p['events'][] = ['type' => 'closure', 'date' => $closure['date'], 'reuse' => null, 'heritage_kind' => null]
                    + ($p['closure_rank'] ?? ['source' => $closure['source'], 'whole' => false]);
                $p['own_final'] = null;
                $p['paired_end'] = null;
                $p['closed_today'] = false;
                $pieces[$nk] = $p;
            }
            $chunkKey[$n] = $nk;
            $this->stats['inferred_closure_m'] += $c->len;
        }
    }

    /**
     * Raccordement (nom « Raccordement… ») ou courte section (moins de SHORT_LINK_M) sans
     * AUCUNE date d'ouverture, qui relie des lignes datées dont une LGV : sans date il
     * n'apparaîtrait que sur la carte actuelle et les itinéraires historiques feraient un
     * détour (ex. raccordement de Lyon-Saint-Clair, sortie de la LGV Sud-Est). Il ne peut
     * pas être plus ancien que les lignes qu'il relie : il prend l'ouverture de la plus
     * récente, celle de la LGV qu'il dessert (EraContinuity::junctionOpening). Entre deux
     * lignes classiques, la date de la plus récente n'est qu'une borne inférieure souvent
     * fausse de plusieurs décennies (raccordements tardifs, tunnels urbains comme le RER D) :
     * pas de date. Réservé aux portions encore en place (cf. piece()). Pour chaque extrémité
     * libre du tracé, la ligne la plus proche (moins de JUNCTION_TOL_M) donne sa date à cet
     * endroit : ouverture de la portion qui y passe, sinon son ouverture « toute la ligne ».
     */
    private function junctionOpening(object $line): ?string
    {
        if ($line->is_lgv && ! str_starts_with(mb_strtolower((string) $line->name), 'raccordement')) {
            return null;
        }
        if (! str_starts_with(mb_strtolower((string) $line->name), 'raccordement') && (int) $line->ref_length > self::SHORT_LINK_M) {
            return null;
        }
        $ends = DB::select(str_replace('{lgv}', Lgv::sql('l', null), <<<'SQL'
            WITH m AS (
                SELECT (ST_Dump(ST_LineMerge(ST_Union(geom)))).geom AS g FROM line_reference_parts WHERE line_id = :line
            ),
            e AS (SELECT ST_StartPoint(g) AS p, g FROM m UNION ALL SELECT ST_EndPoint(g), g FROM m),
            free AS (
                SELECT p FROM e WHERE NOT EXISTS (SELECT 1 FROM m WHERE NOT ST_Equals(m.g, e.g) AND ST_DWithin(m.g::geography, e.p::geography, 30))
            ),
            nb AS (
                SELECT f.p, (SELECT o.line_id FROM line_reference_parts o
                             WHERE o.line_id <> :line2 AND o.geom && ST_Expand(f.p, 0.001) AND ST_DWithin(o.geom::geography, f.p::geography, :tol)
                             ORDER BY ST_Distance(o.geom::geography, f.p::geography) LIMIT 1) AS line_id
                FROM free f
            )
            SELECT nb.line_id, (SELECT {lgv} FROM lines l WHERE l.id = nb.line_id) AS lgv,
                   (SELECT MIN(ev.event_date) FROM line_events ev
                    WHERE ev.line_id = nb.line_id AND ev.type LIKE 'opening%' AND ev.section_resolution IN ('pk', 'places', 'trace')
                      AND ev.section_geom && ST_Expand(nb.p, 0.001) AND ST_DWithin(ev.section_geom::geography, nb.p::geography, :tol2)) AS portion,
                   (SELECT string_agg(ev.event_date::text, ',' ORDER BY ev.event_date) FROM line_events ev
                    WHERE ev.line_id = nb.line_id AND ev.type LIKE 'opening%'
                      AND (ev.section_resolution = 'whole_line' OR (ev.section_resolution IS NULL AND ev.section_from IS NULL AND ev.section_pk_start_m IS NULL))) AS whole
            FROM nb
            SQL), ['line' => $line->id, 'line2' => $line->id, 'tol' => self::JUNCTION_TOL_M, 'tol2' => self::JUNCTION_TOL_M]);
        $dated = array_map(function ($end) {
            if ($end->line_id === null || $end->portion !== null) {
                return [$end->line_id === null ? null : (int) $end->line_id, $end->portion, (bool) $end->lgv];
            }
            $whole = $end->whole ? array_map(fn ($d) => ['type' => 'opening', 'date' => $d], explode(',', $end->whole)) : [];

            return [(int) $end->line_id, $whole ? EraTimeline::wholeLineOpening($whole)['date'] : null, (bool) $end->lgv];
        }, $ends);
        $date = EraContinuity::junctionOpening($dated);
        if ($date !== null) {
            $this->stats['junction_lines']++;
        }

        return $date;
    }

    private static function isEnd(string $type): bool
    {
        return str_starts_with($type, 'closure') || in_array($type, ['declassification', 'dismantling', 'conversion'], true);
    }

    /**
     * Portion datée seulement par des ouvertures « toute la ligne » : elle prend
     * l'ouverture de la portion datée PROPREMENT (section localisée) la plus proche,
     * bornée par les ouvertures globales (pas avant la première, pas après l'achèvement)
     * et sans dépasser sa propre première fermeture (EraContinuity::neighbourOpening),
     * cherchée sur toute la ligne (STAGED_NEIGHBOUR_OPENING_M). Une ouverture globale unique
     * (Wikidata P1619, infobox) est celle de la PREMIÈRE section : elle ne date pas le reste
     * (ex. 784000 « 1827 » = Saint-Étienne - Andrézieux seulement). Une ligne ouverte par
     * étapes se dessine ainsi section par section au lieu d'apparaître entière dès la
     * première (ères 1830-1890 surestimées).
     */
    private function inferOpenings(array $chunks, array &$chunkKey, array &$pieces): void
    {
        $cell = self::NEIGHBOUR_OPENING_M;
        $mid = fn ($c) => [($c->sx + $c->ex) / 2, ($c->sy + $c->ey) / 2];
        $grid = [];
        foreach ($chunks as $n => $c) {
            if ($own = $pieces[$chunkKey[$n]]['own_opening']) {
                [$x, $y] = $mid($c);
                $grid[(int) floor($x / $cell)][(int) floor($y / $cell)][] = [$x, $y, $own];
            }
        }
        if (! $grid) {
            return;
        }
        foreach ($chunks as $n => $c) {
            $p = $pieces[$chunkKey[$n]];
            if (! $p['whole_only']) {
                continue;
            }
            [$x, $y] = $mid($c);
            $best = EraContinuity::nearest($grid, $x, $y, $cell, self::STAGED_NEIGHBOUR_OPENING_M);
            if ($best === null) {
                continue;
            }
            $date = EraContinuity::neighbourOpening($best['date'], $p['whole_first'], $p['whole_last'], $p['first_end']);
            if ($date === null) {
                continue;
            }
            $k = $chunkKey[$n].'|ouverture:'.$date.':'.$best['source'];
            if (! isset($pieces[$k])) {
                $p['events'][] = ['type' => 'opening', 'date' => $date, 'source' => $best['source'], 'reuse' => null, 'heritage_kind' => null, 'whole' => false];
                $p['whole_only'] = false;
                $p['own_opening'] = null;
                $pieces[$k] = $p;
            }
            $chunkKey[$n] = $k;
            $this->stats['inferred_opening_m'] += $c->len;
        }
    }

    /**
     * État de la portion à chaque ère (null = absente de la carte), sa signature (états de
     * toutes les ères, pour comparer des portions voisines) et si elle est datée.
     *
     * @return array{0: array<int, ?array>, 1: string, 2: bool}
     */
    private function eraStates(array $p, $allEras, array $prevYears, string $defaults, bool $passengerImpliesFreight): array
    {
        $timeline = (new EraTimeline($p['events'], $defaults, $passengerImpliesFreight))
            // Tracé d'une ligne disparue (archeo) sans observation actuelle : disparu par défaut, SAUF à
            // l'étranger, où aucune source d'état actuel n'observe la voie (176000 Niedaltdorf - Dillingen,
            // en service en Sarre) : une portion DATÉE y suit sa chronologie, comme un tracé OHM.
            ->withPresentState($p['present'], $p['since'], goneByDefault: $p['historical'] && ! ($p['abroad'] ?? false))
            ->heritageEndsCommercialTraffic($p['heritage_ends']);
        $dated = $timeline->isDated();
        $sncfStatus = $p['sncf'];
        $out = [];
        foreach ($allEras as $era) {
            $year = $era->is_current ? min((int) $era->year, (int) now()->year) : (int) $era->year;
            // Ère courante : état au jour même (« 2030 » = aujourd'hui), pas au 31/12.
            $asOf = $era->is_current ? now()->toDateString() : null;
            $state = $dated ? $timeline->stateAt($year, (bool) $era->is_current, $prevYears[$era->year], $asOf) : null;

            if (! $dated && $era->is_current && ! $p['gone']) {
                // Pas de chronologie : état connu aujourd'hui, à affiner par les statuts SNCF.
                $state = $p['present'] === 'track_in_place' || $p['historical']
                    ? ['status' => 'abandoned', 'traffic' => 'unknown']
                    : ['status' => 'in_service', 'traffic' => 'unknown'];
            }
            // (Une exploitation associative n'est pas effacée par le statut SNCF : c'est
            // justement une ligne fermée au trafic commercial, reprise par une association.)
            if ($state && $era->is_current && $sncfStatus !== null && $sncfStatus !== SegmentStatus::Dismantled && empty($state['heritage'])) {
                // Ère courante : le statut officiel SNCF du tronçon prime sur la chronologie
                // (fermée / neutralisée / voie de service, ou au contraire exploitée).
                if ($sncfStatus->isActive() && $state['status'] !== 'in_service' && ! $p['recent_closure']) {
                    // (Sauf fermeture récente et sourcée : le statut SNCF peut être périmé.)
                    $state = ['status' => 'in_service', 'traffic' => 'unknown'];
                } elseif (! $sncfStatus->isActive() && $state['status'] === 'in_service' && ! $p['ign']) {
                    // (Une voie que l'IGN voit « En service » reste en service même si SNCF Réseau
                    // l'a déclassée : elle est exploitée par un autre gestionnaire, ex. RER A / RATP.)
                    $state = ['status' => $sncfStatus->value, 'traffic' => 'unknown'];
                }
            }
            $out[$era->id] = $state ?: null;
        }
        $sig = implode(';', array_map(fn ($s) => $s === null ? '-'
            : $s['status'].'/'.$s['traffic'].'/'.($s['reuse'] ?? '').'/'.($s['heritage'] ?? ''), $out));

        return [$out, $sig, $dated];
    }

    /**
     * Lissage le long du tracé (cf. EraContinuity::smooth) : les morceaux de référence sont
     * enchaînés par leurs extrémités, puis les morceaux ou trous courts que rien ne justifie
     * prennent l'état de leurs voisins.
     *
     * @return float longueur réaffectée (m)
     */
    /**
     * Tronçons de la ligne dans l'ordre du tracé : morceaux de référence enchaînés par leurs
     * extrémités (EraContinuity::chains).
     *
     * @return list<list<int>> indices de $chunks
     */
    private function chainOrders(array $chunks): array
    {
        $seqs = [];
        foreach ($chunks as $n => $c) {
            $id = $c->part_id.':'.$c->part_no;
            if (isset($seqs[$id])) {
                $seqs[$id][1] = $n;
            } else {
                $seqs[$id] = [$n, $n];
            }
        }
        $seqs = array_values($seqs);
        $ends = array_map(fn ($s) => [$chunks[$s[0]]->sx, $chunks[$s[0]]->sy, $chunks[$s[1]]->ex, $chunks[$s[1]]->ey], $seqs);
        $lengths = array_map(fn ($s) => array_sum(array_map(fn ($n) => $chunks[$n]->len, range($s[0], $s[1]))), $seqs);
        $orders = [];
        foreach (EraContinuity::chains($ends, self::LINK_TOL_M, $lengths) as $chain) {
            $order = [];
            foreach ($chain as [$s, $forward]) {
                array_push($order, ...($forward ? range($seqs[$s][0], $seqs[$s][1]) : range($seqs[$s][1], $seqs[$s][0])));
            }
            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * Portions jamais datées, ou ouvertes des décennies après leurs DEUX voisines, au milieu
     * d'une ligne datée (EraContinuity::sandwichedOpenings) : elles prennent l'ouverture de
     * la plus récente des deux, avec sa source (cf. EraTimeline::earlierConflictingOpening,
     * qui la fait passer devant la date tardive).
     */
    private function inferSandwichedOpenings(array $chunks, array $orders, array &$chunkKey, array &$pieces): void
    {
        $first = function (string $k) use (&$pieces): ?array {
            if (! array_key_exists('first_open', $pieces[$k])) {
                $best = null;
                foreach (EraTimeline::dedupe($pieces[$k]['events']) as $e) {
                    if (str_starts_with($e['type'], 'opening') && ($best === null || $e['date'] < $best['date'])) {
                        $best = ['date' => $e['date'], 'source' => $e['source']];
                    }
                }
                $pieces[$k]['first_open'] = $best;
            }

            return $pieces[$k]['first_open'];
        };
        foreach ($orders as $order) {
            $keys = array_map(fn ($n) => $chunkKey[$n], $order);
            $inferred = EraContinuity::sandwichedOpenings(
                array_map(fn ($n) => (float) $chunks[$n]->len, $order),
                array_map(fn ($k) => $first($k)['date'] ?? null, $keys),
                self::SANDWICH_MAX_M, EraTimeline::OPENING_CONFLICT_YEARS,
            );
            foreach ($inferred as $pos => ['date' => $date, 'from' => $from]) {
                $k = $keys[$pos];
                $source = $first($keys[$from])['source'] ?? null;
                $nk = $k.'|sandwich:'.$date.':'.$source;
                if (! isset($pieces[$nk])) {
                    $p = $pieces[$k];
                    $p['events'][] = ['type' => 'opening', 'date' => $date, 'source' => $source, 'reuse' => null, 'heritage_kind' => null, 'whole' => false];
                    $p['whole_only'] = false;
                    $p['own_opening'] = null;
                    unset($p['first_open']);
                    $pieces[$nk] = $p;
                }
                $chunkKey[$order[$pos]] = $nk;
                $this->stats['sandwich_opening_m'] += $chunks[$order[$pos]]->len;
            }
        }
    }

    private function smoothLine(array $chunks, array $orders, array &$chunkKey, array $pieces, array $sig, array $sectionLen): float
    {
        $moved = 0.0;
        foreach ($orders as $order) {
            $keys = array_map(fn ($n) => $chunkKey[$n], $order);
            // Justifié : un événement absent des tronçons voisins, dont la section est à la mesure
            // du morceau (une fermeture réellement limitée à 150 m reste visible).
            $ids = function (array $positions) use ($keys, $pieces): array {
                $out = [];
                foreach ($positions as $p) {
                    foreach ($pieces[$keys[$p]]['evIds'] as $id) {
                        $out[$id] = true;
                    }
                }

                return $out;
            };
            $justified = function (array $run, array $around) use ($ids, $order, $chunks, $sectionLen): bool {
                $len = array_sum(array_map(fn ($p) => $chunks[$order[$p]]->len, $run));
                foreach (array_diff_key($ids($run), $ids($around)) as $id => $_) {
                    if (isset($sectionLen[$id]) && $sectionLen[$id] <= $len + 2 * self::COVER_TOL_M + 100) {
                        return true;
                    }
                }

                return false;
            };
            $src = EraContinuity::smooth(
                array_map(fn ($n) => (float) $chunks[$n]->len, $order),
                array_map(fn ($k) => $sig[$k], $keys),
                self::MIN_PIECE_M, self::MAX_GAP_M, $justified,
            );
            foreach ($src as $pos => $from) {
                if ($from !== $pos && $keys[$from] !== $keys[$pos]) {
                    $chunkKey[$order[$pos]] = $keys[$from];
                    $moved += $chunks[$order[$pos]]->len;
                }
            }
        }

        return $moved;
    }

    /**
     * Portion EXPLOITÉE aujourd'hui selon SNCF : une fermeture de tout trafic, une
     * dépose ou un déclassement qui n'est suivi d'AUCUNE réouverture est en
     * contradiction avec la réalité. C'est presque toujours un événement mal
     * attribué (ex. Culoz - Modane « fermée en 1871 » : il s'agissait du chemin de
     * fer provisoire du Mont-Cenis). On l'ignore et on le compte. Les fermetures
     * aux seuls voyageurs restent valables (ligne restée ouverte au fret).
     *
     * @param  array<int, array{type: string, date: string, source: ?string, reuse: ?string}>  $events
     * @return array<int, array{type: string, date: string, source: ?string, reuse: ?string}>
     */
    private function dropContradictedClosures(array $events, bool $wholeLineOnly = false, array $types = ['closure', 'dismantling', 'declassification', 'conversion']): array
    {
        // Sur les événements effectivement retenus par l'arbitrage (une ouverture
        // écartée ne « rouvre » pas une fermeture).
        $lastOpening = null;
        foreach (EraTimeline::dedupe($events) as $e) {
            if (str_starts_with($e['type'], 'opening') || str_starts_with($e['type'], 'reopening')) {
                $lastOpening = max($lastOpening ?? '', $e['date']);
            }
        }

        return array_values(array_filter($events, function ($e) use ($lastOpening, $wholeLineOnly, $types) {
            // (Une fermeture récente, localisée et sourcée prime sur un statut actuel peut-être périmé.)
            $terminal = in_array($e['type'], $types, true)
                && (! $wholeLineOnly || $e['whole'])
                && ! EraTimeline::isRecentSourcedClosure($e, (int) now()->year);
            if ($terminal && ($lastOpening === null || $e['date'] >= $lastOpening)) {
                $this->stats['contradicted_closures']++;

                return false;
            }

            return true;
        }));
    }

    /**
     * Portion encore exploitée aujourd'hui (SNCF ou IGN) : les réouvertures sont souvent
     * décrites sans portion (« la ligne rouvre aux voyageurs en 1948 », « tram-train mis en
     * service en 2014 »), alors que la fermeture correspondante est notée sur toute la ligne.
     *   - une réouverture sans portion citée s'applique à la portion ;
     *   - une ouverture « toute la ligne » datée plus d'un an après la première ouverture de
     *     la portion ET après une fermeture est en réalité une RÉouverture (sinon elle serait
     *     ignorée). Sans fermeture entre les deux, c'est un achèvement (ligne ouverte par
     *     étapes) : cf. EraTimeline::completionOpening.
     * Réservé aux portions exploitées : un ancien tracé abandonné ne doit pas être « rouvert »
     * par la date d'achèvement de la ligne (cas Culoz - Modane).
     */
    private function reopeningsForActivePiece(array $events, array $orphanReopenings): array
    {
        return EraTimeline::wholeOpeningsAsReopenings(array_merge($events, array_values($orphanReopenings)));
    }

    /**
     * Portion EXPLOITÉE aujourd'hui selon SNCF que ses fermetures partielles laissent sans
     * trafic : cf. EraTimeline::withoutContradictedPartialClosures.
     */
    private function dropContradictedPartialClosures(array $events, string $defaults, bool $passengerImpliesFreight): array
    {
        $kept = EraTimeline::withoutContradictedPartialClosures($events, $defaults, $passengerImpliesFreight, (int) now()->year);
        $this->stats['contradicted_closures'] += count($events) - count($kept);

        return $kept;
    }

    private function pgArray(array $values): string
    {
        return '{'.implode(',', array_map(fn ($v) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v).'"', $values)).'}';
    }

    private function report($eras): void
    {
        $this->line(sprintf('  %d portions ; %s km sans aucune date d\'ouverture ; %d événements ignorés (portion non résolue) ; %d fermetures contredites par une exploitation actuelle ; %d portions datées par une fermeture non localisée',
            $this->stats['pieces'], number_format($this->stats['undated_m'] / 1000, 0, ',', ' '), $this->stats['skipped_events'], $this->stats['contradicted_closures'], $this->stats['orphan_closures_used']));
        $this->line(sprintf('  continuité : %s km de morceaux ou trous courts absorbés par leurs voisins ; %s km datés par l\'ouverture de la portion voisine ; %s km fermés aujourd\'hui datés par la fermeture voisine',
            number_format($this->stats['smoothed_m'] / 1000, 0, ',', ' '), number_format($this->stats['inferred_opening_m'] / 1000, 0, ',', ' '),
            number_format($this->stats['inferred_closure_m'] / 1000, 0, ',', ' ')));
        $this->line(sprintf('  %d raccordements ou courtes sections sans date datés par les lignes qu\'ils relient ; %s km jamais datés ou tardifs datés par leurs deux voisines',
            $this->stats['junction_lines'], number_format($this->stats['sandwich_opening_m'] / 1000, 0, ',', ' ')));

        $rows = DB::select(<<<'SQL'
            SELECT e.year, ls.status, ls.traffic_type, ROUND(SUM(ls.length_meters) / 1000.0) AS km
            FROM line_segments ls JOIN eras e ON e.id = ls.era_id
            WHERE e.id = ANY(CAST(? AS bigint[]))
            GROUP BY e.year, ls.status, ls.traffic_type ORDER BY e.year, km DESC
            SQL, ['{'.$eras->pluck('id')->implode(',').'}']);
        $table = [];
        foreach ($rows as $r) {
            $table[$r->year][$r->status === 'in_service' ? $r->traffic_type : $r->status] = $r->km;
        }
        $cols = ['mixed', 'passenger', 'freight', 'unknown', 'abandoned', 'dismantled'];
        $this->table(array_merge(['Ère'], $cols), array_map(
            fn ($year) => array_merge([$year], array_map(fn ($c) => $table[$year][$c] ?? 0, $cols)),
            array_keys($table),
        ));
    }
}
