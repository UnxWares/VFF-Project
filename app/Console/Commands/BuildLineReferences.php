<?php

namespace App\Console\Commands;

use App\Services\JunctionRepair;
use App\Services\SingleAxis;
use App\Services\SourceAgreement;
use App\Services\TraceConflation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Construit le tracé de référence de chaque ligne (toutes époques confondues)
 * en fusionnant ses `source_traces` par ordre de PRÉCISION, puis en recalant
 * les morceaux peu précis sur la géométrie réelle de la voie.
 *
 * 1. Fusion par priorité : chaque source n'ajoute que ce que les sources plus
 *    précises ne couvrent pas déjà, à une tolérance propre à sa précision :
 *
 *      contrib (corrections validées)  →  bdtopo (IGN, 2,5 m)  →  osm (~2 m)
 *      →  archeoferroviaire (~1-3 m aux sommets, cordes)  →  ohm  →  sncf (généralisé)
 *
 * 2. Recalage (App\Services\TraceConflation) : un morceau archeo, OHM ou SNCF
 *    est remplacé, portion par portion, par la géométrie précise qui suit le
 *    même axe dans TOUTES les sources précises, rattachées ou non (BD TOPO
 *    toutes natures sauf projets ; OSM y compris abandoned / razed / cycles
 *    de vie). La provenance du dessin est gardée (matched_source + ids) ;
 *    `source` reste la source qui définit le morceau (archeo = ligne disparue).
 *    Sans géométrie précise, le tracé d'origine est gardé tel quel (aucun
 *    lissage) et listé dans storage/app/reports/trace-sans-donnee-precise*.csv.
 *    Voie d'une autre ligne refusée (écart médian > 12 m) : la voie candidate
 *    suivante (voie propre, faisceau) est essayée avant de garder l'original.
 *    Tronc commun aux jonctions (TraceConflation::junctionTrunk) : une portion
 *    sans géométrie précise propre (tracé d'origine, ou voie de service d'un
 *    faisceau) qui longe à 12-30 m la voie principale d'une AUTRE ligne et
 *    converge vers elle (la rejoint, ou s'arrête à la gare de jonction) prend
 *    cette voie : voie partagée, masquée ensuite par vff:build-shared-track
 *    (sinon deux traits côte à côte à l'approche de la gare, ex. Vire). Un
 *    long parallélisme sans convergence est gardé et signalé : action
 *    kept_parallel dans line_reference_duplicates et trace-doublons*.csv
 *    (merged_trunk pour les portions reprises).
 *
 * 3. Un seul axe : un morceau (OSM rattaché, recalé ou grossier) qui longe de
 *    bout en bout une géométrie déjà retenue de la ligne n'est qu'un doublon
 *    parallèle (autre voie du faisceau, version décalée) et il est retiré.
 *    Recousage : les extrémités laissées à 10-30 m d'une source plus précise
 *    par la découpe sont raccordées au point le plus proche (pas de trous).
 *    Puis, une fois toutes les sources fusionnées (App\Services\SingleAxis) :
 *    les portions qui longent une autre portion de la MÊME ligne à moins de
 *    30 m sur au moins 100 m (4 voies BD TOPO saisies sur deux axes, double
 *    voie saisie voie par voie, voies de gare, voies OSM séparées, recouvrement
 *    entre sources après recalage, tracé archeo saisi deux fois) sont retirées,
 *    l'axe le plus fiable puis le plus long étant gardé ; coupes et extrémités
 *    orphelines raccordées. Les anciens tracés datés (Culoz - Modane, 1866 et
 *    1876) et les voies déposées le long de la voie actuelle restent. Détail
 *    dans line_reference_duplicates et storage/app/reports/trace-doublons*.csv.
 *    Une coupure n'est recousue que par un seul raccord (pas un par bord).
 *
 * 3 bis. Continuité sur les voies réelles (App\Services\JunctionRepair) :
 *    sauts perpendiculaires entre voies parallèles (raccords « en escalier »)
 *    remplacés par l'aiguillage réel ou retirés avec la voie qu'ils
 *    desservaient, trous (ponts, passages à niveau) comblés par la voie
 *    précise, embranchement arrêté avant la jonction prolongé par les voies
 *    réelles jusqu'à l'autre ligne, dépassement d'un tracé grossier coupé au
 *    croisement. Rien n'est inventé : tout ajout suit des voies BD TOPO / OSM,
 *    aiguillage par aiguillage (déviation <= 30° à chaque nœud). Seule
 *    exception, marquée : l'arrivée coudée d'un tracé imprécis sur une portion
 *    précise, sans voie réelle, est refaite par une courbe lissée de rayon
 *    plausible (matched_source « raccord-lisse », géométrie estimée).
 *
 * 4. Croisement des sources (App\Services\SourceAgreement) : chaque morceau
 *    est redécoupé par indice de confiance (high / medium / low) avec le
 *    nombre de sources concordantes et les écarts ; les divergences
 *    (line_reference_divergences) sont listées, triées par écart × longueur,
 *    dans storage/app/reports/trace-divergences-sources*.csv.
 *
 * 5. Nettoyage final (SingleAxis::tidy) : bouts superposés à moins de 0,5 m
 *    retirés quelle que soit leur longueur (chevauchements bout à bout de
 *    deux tracés, raccords en double), extrémités recollées au bit près
 *    (raccords en T : sommet inséré), raccords de moins de 6 m fusionnés avec
 *    la portion qu'ils prolongent. Sans lui, le moteur décennal produit des
 *    tronçons en double et des tronçons de longueur nulle.
 *
 * Rapport chiffré avant / après (km par provenance, écart à la meilleure
 * géométrie précise, cordes > 300 m). Calculs en Lambert-93. Idempotent.
 *
 *   php artisan vff:build-line-references
 *   php artisan vff:build-line-references --line=956000 --line=3544
 *   php artisan vff:build-line-references --shard=1/4 &  (… --shard=4/4)   # ~12 min
 *   php artisan vff:build-line-references --report-only     # mesures + rapports CSV
 *   php artisan vff:build-line-references --no-conflate     # ancien comportement
 *   php artisan vff:build-line-references --no-single-axis  # sans l'étape « un seul axe »
 */
class BuildLineReferences extends Command
{
    protected $signature = 'vff:build-line-references
        {--line=* : id ou code d\'une ligne (répétable)}
        {--no-conflate : garder les morceaux peu précis tels quels (ancien comportement)}
        {--no-agreement : sans croisement des sources (ni confiance ni divergences)}
        {--no-single-axis : garder les portions dédoublées d\'une même ligne (pas d\'étape « un seul axe »)}
        {--no-junction-repair : sans l\'étape de continuité sur les voies réelles (sauts, trous, jonctions)}
        {--report-only : mesurer la précision du tracé actuel et réécrire les rapports, sans rien reconstruire}
        {--shard= : i/n - ne traiter qu\'une ligne sur n (processus parallèles ; rapports avec --report-only ensuite)}';

    protected $description = 'Fusionne les tracés de chaque ligne (IGN, OSM, archeo, OHM, SNCF), recale les morceaux peu précis sur la voie réelle et croise les sources.';

    /**
     * Ordre de priorité : [source, tolérance de recouvrement (m), filtre SQL sur source_traces, recalable].
     * Les voies de service, métros et tramways urbains ne font pas partie du tracé d'une ligne.
     */
    private const SOURCES = [
        ['contrib', 10, 'TRUE', false],
        // Voies « Non exploité » : nature « Sans objet » dans la BD TOPO, mais rails en place.
        ['bdtopo', 0, "(layer IN ('LGV', 'Voie ferrée principale') OR (layer = 'Sans objet' AND properties->>'etat_de_l_objet' = 'Non exploité'))", false],
        ['osm', 15, "(properties->>'service') IS NULL AND layer NOT IN ('subway', 'tram', 'monorail', 'funicular', 'miniature')", false],
        // Le calque « lignes exploitées » d'archeo n'est qu'un fond de contexte peu précis (~6 pts/km).
        ['archeoferroviaire', 30, "layer <> 'exploitee'", true],
        ['ohm', 40, "layer NOT IN ('subway', 'tram', 'proposed')", true],
        ['sncf', 250, 'TRUE', true],
    ];

    /** Morceaux résiduels plus courts que ça = artefacts de bord de tampon. */
    private const MIN_PART_M = 60;

    /** Tolérance de découpe d'un morceau recalé contre les sources plus précises (m). */
    private const MATCH_TOL_M = 15;

    /** Doublon parallèle : écart max (m) d'un morceau qui longe une géométrie déjà retenue (par source). */
    private const SHADOW_M = ['osm' => 80, 'archeoferroviaire' => 120, 'ohm' => 200, 'sncf' => 400];

    /** Raccord maximal entre une extrémité découpée et la géométrie déjà retenue (m). */
    private const MAX_STITCH_M = 60;

    private const PRECISE = ['contrib', 'bdtopo', 'osm', 'ign-historique'];

    /** Libellés des actions de line_reference_duplicates (rapport trace-doublons*.csv). */
    private const DUPLICATE_ACTIONS = [
        'removed' => 'retirée',
        'kept_distinct' => 'gardée (tracé distinct)',
        'merged_trunk' => 'tronc commun (voie de l\'autre ligne)',
        'kept_parallel' => 'gardée, parallèle sans convergence (à vérifier)',
    ];

    private array $conflationStats = [];

    /** Étape « un seul axe » : longueurs (m) retirées par cause, tracés distincts gardés, raccords. */
    private array $axisStats = [];

    /** Nettoyage final : bouts superposés retirés, raccords en double, extrémités recollées, raccords fusionnés. */
    private array $tidyStats = [];

    /** Continuité sur les voies réelles (JunctionRepair) : sauts, trous, changements de voie, prolongements, dépassements. */
    private array $repairStats = [];

    /** Tronc commun aux jonctions (TraceConflation::junctionTrunk) de la ligne en cours : enregistré après « un seul axe ». */
    private array $junctionRecords = [];

    /** Durées cumulées par étape (s), affichées avec -v. */
    private array $timings = [];

    public function __construct(private readonly TraceConflation $conflation, private readonly SourceAgreement $agreement, private readonly SingleAxis $singleAxis,
        private readonly JunctionRepair $junctionRepair)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ini_set('memory_limit', '2G');
        $lineIds = $this->targetLines();
        $partial = (bool) $this->option('line') || (bool) $this->option('shard');
        $this->info(count($lineIds).' lignes à traiter');
        if (! $lineIds) {
            return self::SUCCESS;
        }

        $t = microtime(true);
        $n = $this->conflation->preparePool($partial ? $this->envelope($lineIds) : null);
        $this->line(sprintf('  pool de géométries : %d traces (%.1f s)', $n, microtime(true) - $t));

        $t = microtime(true);
        $before = $this->metrics($lineIds);
        $this->timings['mesures'] = microtime(true) - $t;
        if ($this->option('report-only')) {
            $this->printMetrics(['actuel' => $before]);
            $this->writeReports($lineIds, $partial);

            return self::SUCCESS;
        }

        if (! $this->option('line') && in_array($this->option('shard'), [null, '1/'.explode('/', (string) $this->option('shard').'/')[1]], true)) {
            // Lignes qui n'ont plus aucun tracé rattaché : leur ancien tracé de référence n'a plus de source.
            $all = DB::table('source_traces')->whereNotNull('line_id')->distinct()->pluck('line_id')->all();
            $stale = DB::table('line_reference_geometries')->whereNotIn('line_id', $all)->pluck('line_id')->all();
            foreach (['line_reference_parts', 'line_reference_geometries', 'line_reference_divergences', 'line_reference_duplicates'] as $table) {
                DB::table($table)->whereIn('line_id', $stale)->delete();
            }
            $this->line('  '.count($stale).' tracés de référence obsolètes retirés (lignes sans aucun tracé rattaché)');
        }

        $bar = $this->output->createProgressBar(count($lineIds));
        foreach ($lineIds as $lineId) {
            DB::transaction(fn () => $this->buildLine($lineId));
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        foreach (DB::select("SELECT source, COALESCE(matched_source, '-') AS matched, COUNT(DISTINCT line_id) AS lines, ROUND(SUM(length_m) / 1000.0) AS km
                             FROM line_reference_parts WHERE line_id = ANY(CAST(? AS bigint[]))
                             GROUP BY 1, 2 ORDER BY km DESC", [$this->pgIds($lineIds)]) as $r) {
            $this->line(sprintf('  %-18s dessin %-10s %5d lignes  %8s km', $r->source, $r->matched, $r->lines, $r->km));
        }
        $total = DB::selectOne('SELECT COUNT(*) n, ROUND(SUM(length_m) / 1000.0) km FROM line_reference_geometries WHERE line_id = ANY(CAST(? AS bigint[]))', [$this->pgIds($lineIds)]);
        $missing = DB::selectOne('SELECT COUNT(*) n FROM lines l WHERE NOT EXISTS (SELECT 1 FROM line_reference_geometries r WHERE r.line_id = l.id)');
        $this->info("✓ {$total->n} lignes avec tracé de référence ({$total->km} km), {$missing->n} lignes sans aucun tracé.");

        $s = $this->conflationStats;
        if ($s) {
            $this->line(sprintf('  recalage : %s km grossiers examinés → %s km recalés sur une voie précise, %s km de raccords, %s km gardés faute de donnée précise (%d sous-tronçons refusés à la validation, %s km de cordes au profil incompatible), %s km de doublons parallèles retirés',
                $this->km($s['coarse_m'] ?? 0), $this->km($s['matched_m'] ?? 0), $this->km($s['connector_m'] ?? 0), $this->km($s['kept_m'] ?? 0), $s['rejected_runs'] ?? 0, $this->km($s['chord_rejected_m'] ?? 0), $this->km($s['shadow_m'] ?? 0)));
            $this->line(sprintf('  jonctions : %s km posés sur la voie d\'une autre ligne (tronc commun), %s km de parallèles sans convergence gardés et signalés',
                $this->km($s['junction_m'] ?? 0), $this->km($s['parallel_kept_m'] ?? 0)));
        }
        if ($a = $this->axisStats) {
            $removed = array_filter($a, fn ($k) => str_starts_with($k, 'removed:'), ARRAY_FILTER_USE_KEY);
            arsort($removed);
            $this->line(sprintf('  un seul axe : %s km de portions dédoublées retirés (%s), %s km d\'anciens tracés distincts gardés, %s km de raccords',
                $this->km(array_sum($removed)),
                implode(', ', array_map(fn ($k, $v) => substr($k, 8).' '.$this->km($v), array_keys($removed), $removed)) ?: '-',
                $this->km($a['kept_distinct'] ?? 0), $this->km($a['connector_m'] ?? 0)));
        }
        if ($j = $this->repairStats) {
            $this->line(sprintf('  continuité (avant un seul axe) : %d trous comblés tout droit par la voie précise (%s km)',
                $j['straight_hole_filled'] ?? 0, $this->km($j['straight_hole_filled_m'] ?? 0)));
            $this->line(sprintf('  continuité : %d sauts entre voies parallèles retirés (%d remis faute de voie réelle), %d trous comblés (%s km), %d changements de voie sur aiguillage réel (%s km), %s km de bouts et %s km de restes parallèles retirés, %d embranchements prolongés jusqu\'à la jonction (%s km), %s km de dépassements coupés',
                $j['jog_removed'] ?? 0, $j['jog_restored'] ?? 0, $j['hole_filled'] ?? 0, $this->km($j['hole_filled_m'] ?? 0), $j['rerouted'] ?? 0, $this->km($j['rerouted_m'] ?? 0),
                $this->km($j['stub_trimmed_m'] ?? 0), $this->km($j['remnant_removed_m'] ?? 0), $j['extended'] ?? 0, $this->km($j['extended_m'] ?? 0), $this->km($j['overshoot_trimmed_m'] ?? 0)));
            $this->line(sprintf('  arrivées de tracés imprécis : %d refaites par la voie réelle (%s km), %d par une courbe lissée estimée « raccord-lisse » (%s km) ; %d bouts raccordés à la voie d\'une autre ligne (%s km), %d trous sans voie précise comblés (%s km), courbes estimées',
                $j['leg_rerouted'] ?? 0, $this->km($j['leg_rerouted_m'] ?? 0), $j['leg_smoothed'] ?? 0, $this->km($j['leg_smoothed_m'] ?? 0),
                $j['end_joined'] ?? 0, $this->km($j['end_joined_m'] ?? 0), $j['hole_smoothed'] ?? 0, $this->km($j['hole_smoothed_m'] ?? 0)));
        }
        if ($n = $this->tidyStats) {
            $this->line(sprintf('  nettoyage : %s km de bouts de tracé superposés retirés, %s km de raccords en double (dont %s km de portions courtes presque confondues, à moins de 5 m), %d extrémités recollées, %d raccords courts fusionnés',
                $this->km($n['superposed_m'] ?? 0), $this->km($n['connector_dup_m'] ?? 0), $this->km($n['near_m'] ?? 0), $n['snapped'] ?? 0, $n['folded'] ?? 0));
        }

        $t = microtime(true);
        $after = $this->metrics($lineIds);
        $this->timings['mesures'] += microtime(true) - $t;
        $this->printMetrics(['avant' => $before, 'après' => $after]);
        if ($this->output->isVerbose()) {
            $this->line('  durées : '.implode(', ', array_map(fn ($k, $v) => sprintf('%s %.1f s', $k, $v), array_keys($this->timings), $this->timings)));
            $this->line('  recalage : '.implode(', ', array_map(fn ($k, $v) => sprintf('%s %.1f s', $k, $v), array_keys($this->conflation->timings), $this->conflation->timings)));
        }
        $this->writeReports($lineIds, $partial);

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    private function targetLines(): array
    {
        $opts = (array) $this->option('line');
        if ($opts) {
            $ids = array_map(fn ($o) => ctype_digit($o) && strlen($o) < 6 ? (int) $o : 0, $opts);

            return DB::table('lines')->whereIn('id', $ids)->orWhereIn('code', $opts)->orderBy('id')->pluck('id')->all();
        }

        $ids = DB::table('source_traces')->whereNotNull('line_id')->distinct()->orderBy('line_id')->pluck('line_id')->all();
        if ($shard = $this->option('shard')) {
            [$i, $n] = array_map('intval', explode('/', $shard));
            $ids = array_values(array_filter($ids, fn ($id, $k) => $k % $n === $i - 1, ARRAY_FILTER_USE_BOTH));
        }

        return $ids;
    }

    /** Emprise (WKT Lambert-93) des traces des lignes visées, élargie de 3 km. */
    private function envelope(array $lineIds): ?string
    {
        return DB::selectOne('SELECT ST_AsText(ST_Expand(ST_Extent(ST_Transform(geom, 2154))::geometry, 3000)) AS wkt FROM source_traces WHERE line_id = ANY(CAST(? AS bigint[]))',
            [$this->pgIds($lineIds)])?->wkt;
    }

    private function buildLine(int $lineId): void
    {
        // Verrou par ligne (relâché à la fin de la transaction) : deux reconstructions simultanées
        // de la même ligne inséreraient ses morceaux en double. Clé à deux entiers : espace distinct
        // du verrou de vff:build-era-segments (clé bigint = id de ligne).
        DB::select('SELECT pg_advisory_xact_lock(2154, ?)', [$lineId]);
        DB::delete('DELETE FROM line_reference_parts WHERE line_id = ?', [$lineId]);
        DB::delete('DELETE FROM line_reference_geometries WHERE line_id = ?', [$lineId]);
        DB::delete('DELETE FROM line_reference_divergences WHERE line_id = ?', [$lineId]);
        DB::delete('DELETE FROM line_reference_duplicates WHERE line_id = ?', [$lineId]);

        // Géométrie déjà retenue (EWKB hex, Lambert-93), enrichie source après source.
        $acc = null;
        $this->junctionRecords = [];
        foreach (self::SOURCES as [$source, $tolerance, $filter, $coarse]) {
            $g = DB::selectOne(<<<SQL
                SELECT encode(ST_AsEWKB(ST_UnaryUnion(ST_Collect(ST_Transform(geom, 2154)))), 'hex') AS ewkb,
                       jsonb_agg(id ORDER BY id)::text AS ids
                FROM source_traces
                WHERE line_id = :line AND source = :source AND {$filter}
                  -- Une correction validée (contrib) remplace les tracés qu'elle désigne.
                  AND id NOT IN (
                      SELECT (jsonb_array_elements_text(properties->'supersedes'))::bigint
                      FROM source_traces WHERE source = 'contrib' AND properties->'supersedes' IS NOT NULL -- (pas l'opérateur « ? » : PDO le prend pour un paramètre)
                  )
                HAVING COUNT(*) > 0
                SQL, ['line' => $lineId, 'source' => $source]);
            if (! $g || ! $g->ewkb) {
                continue;
            }

            $tol = max($tolerance, 0.01);
            $bufTol = $this->buffer($acc, $tol);
            $min = $source === 'bdtopo' || $source === 'contrib' ? 0 : self::MIN_PART_M;
            $resid = $this->cut($g->ewkb, $bufTol, $min);
            if (! $resid) {
                continue;
            }

            $added = [];
            if (! $coarse || $this->option('no-conflate')) {
                // Source précise rattachée après une autre (OSM après BD TOPO) : une voie qui longe
                // la géométrie déjà retenue (autre voie du faisceau, voie de gare) n'ajoute qu'un doublon.
                if ($acc !== null && ! $this->option('no-conflate') && isset(self::SHADOW_M[$source])) {
                    $resid = $this->dropShadows($resid, [$acc], $source);
                }
                if ($resid) {
                    $added[] = [null, [], $resid];
                }
            } else {
                $added = $this->conflate($lineId, $source, $g->ewkb, $resid, $acc, $bufTol);
            }
            if (! $added) {
                continue;
            }

            // Recousage des extrémités découpées contre la géométrie déjà retenue.
            if ($acc !== null && ! $this->option('no-conflate')) {
                $stitch = $this->stitch(array_column($added, 2), $acc, min(max($tolerance, self::MATCH_TOL_M) + 5, self::MAX_STITCH_M));
                if ($stitch) {
                    $added[] = ['connector', [], $stitch];
                }
            }

            foreach ($added as [$matched, $matchedIds, $ewkb]) {
                DB::insert(<<<'SQL'
                    INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, matched_trace_ids, length_m, geom, created_at, updated_at)
                    SELECT ?, ?, CAST(? AS jsonb), ?, CAST(? AS jsonb), ROUND(ST_Length(g))::int, ST_Multi(ST_Transform(g, 4326)), NOW(), NOW()
                    FROM (SELECT ST_GeomFromEWKB(decode(?, 'hex')) AS g) t
                    WHERE ST_Length(g) > 0
                    SQL, [$lineId, $source, $g->ids, $matched, $matched && $matched !== 'connector' ? json_encode($matchedIds) : null, $ewkb]);
            }

            $acc = DB::selectOne(<<<'SQL'
                SELECT encode(ST_AsEWKB(ST_Union(ARRAY(
                    SELECT ST_GeomFromEWKB(decode(x, 'hex')) FROM unnest(CAST(:geoms AS text[])) x
                ))), 'hex') AS ewkb
                SQL, ['geoms' => '{'.implode(',', array_filter(array_merge([$acc], array_column($added, 2)))).'}'])->ewkb;
        }

        // Trous de la ligne comblés tout droit par la voie précise (avant « un seul axe » : voie continue).
        if (! $this->option('no-junction-repair') && ! $this->option('no-single-axis') && ! $this->option('no-conflate')) {
            $t = microtime(true);
            foreach ($this->junctionRepair->fillStraightHoles($lineId) as $k => $v) {
                $this->repairStats['straight_'.$k] = ($this->repairStats['straight_'.$k] ?? 0) + $v;
            }
            $this->timings['continuité'] = ($this->timings['continuité'] ?? 0) + microtime(true) - $t;
        }

        // Un seul axe par ligne : portions qui en longent une autre de la même ligne retirées.
        if (! $this->option('no-single-axis') && ! $this->option('no-conflate')) {
            $t = microtime(true);
            foreach ($this->singleAxis->collapse($lineId) as $k => $v) {
                $this->axisStats[$k] = ($this->axisStats[$k] ?? 0) + $v;
            }
            $this->timings['axe unique'] = ($this->timings['axe unique'] ?? 0) + microtime(true) - $t;
        }
        $this->storeJunctionRecords($lineId);

        // Continuité sur les voies réelles : sauts entre voies parallèles, trous, jonctions manquées, dépassements.
        if (! $this->option('no-junction-repair') && ! $this->option('no-single-axis') && ! $this->option('no-conflate')) {
            $t = microtime(true);
            foreach ($this->junctionRepair->repair($lineId) as $k => $v) {
                $this->repairStats[$k] = ($this->repairStats[$k] ?? 0) + $v;
            }
            $this->timings['continuité'] = ($this->timings['continuité'] ?? 0) + microtime(true) - $t;
        }

        if (! $this->option('no-agreement') && ! $this->option('no-conflate')) {
            $t = microtime(true);
            $this->agreement->analyze($lineId);
            $this->timings['croisement'] = ($this->timings['croisement'] ?? 0) + microtime(true) - $t;
        }

        // Nettoyage final, après le redécoupage du croisement : bouts superposés retirés, extrémités
        // recollées au bit près, raccords courts fusionnés (sinon tronçons en double par ère).
        if (! $this->option('no-single-axis') && ! $this->option('no-conflate')) {
            $t = microtime(true);
            foreach ($this->singleAxis->tidy($lineId) as $k => $v) {
                $this->tidyStats[$k] = ($this->tidyStats[$k] ?? 0) + $v;
            }
            $this->timings['nettoyage'] = ($this->timings['nettoyage'] ?? 0) + microtime(true) - $t;
        }

        DB::insert(<<<'SQL'
            INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            SELECT ?, ROUND(SUM(len))::int, jsonb_object_agg(source, len),
                   ST_Multi(ST_LineMerge(ST_Collect(geom))), NOW(), NOW()
            FROM (SELECT source, SUM(length_m) AS len, ST_Collect(geom) AS geom
                  FROM line_reference_parts WHERE line_id = ? GROUP BY source) s
            HAVING COUNT(*) > 0
            SQL, [$lineId, $lineId]);
    }

    /**
     * Recale une source grossière : la portion à ajouter (résidu) est recalée
     * avec son contexte (± corridor), puis découpée contre les sources plus
     * précises (15 m pour une géométrie précise, tolérance de la source sinon).
     *
     * @return array<int, array{0: ?string, 1: array<int, int>, 2: string}>
     */
    private function conflate(int $lineId, string $source, string $full, string $resid, ?string $acc, ?string $bufTol): array
    {
        $p = TraceConflation::PROFILES[$source];
        $ctx = $p['w0'] + $p['sag_cap'] + 300;
        $input = DB::selectOne(<<<'SQL'
            SELECT encode(ST_AsEWKB(CASE WHEN ST_Length(r) > 0.6 * ST_Length(f) THEN f
                        ELSE ST_CollectionExtract(ST_Intersection(f, ST_Buffer(r, :ctx)), 2) END), 'hex') AS ewkb
            FROM (SELECT ST_GeomFromEWKB(decode(:f, 'hex')) AS f, ST_GeomFromEWKB(decode(:r, 'hex')) AS r) t
            SQL, ['f' => $full, 'r' => $resid, 'ctx' => $ctx])->ewkb;

        $t = microtime(true);
        $res = $this->conflation->match($lineId, $input, $source);
        $this->timings['recalage'] = ($this->timings['recalage'] ?? 0) + microtime(true) - $t;
        foreach ($res['stats'] as $k => $v) {
            $this->conflationStats[$k] = ($this->conflationStats[$k] ?? 0) + $v;
        }
        foreach ($res['junctions'] as $j) {
            $this->junctionRecords[] = $j + ['source' => $source];
        }

        $bufMatch = $this->buffer($acc, self::MATCH_TOL_M);
        $out = [];
        // Géométries précises d'abord, puis raccords, puis tracé d'origine conservé.
        $order = fn ($f) => $f === null ? 9 : ($f === 'connector' ? 5 : (int) array_search($f, self::PRECISE, true));
        usort($res['pieces'], fn ($a, $b) => $order($a['family']) <=> $order($b['family']));
        foreach ($res['pieces'] as $piece) {
            [$buf, $min] = match ($piece['family']) {
                null => [$bufTol, self::MIN_PART_M],
                'connector' => [$bufMatch, 1],
                default => [$bufMatch, 20],
            };
            // Un passage gardé raccordé à la chaîne recalée reste, même court.
            $chain = $piece['family'] === null ? array_column(array_filter($res['pieces'], fn ($x) => $x['family'] !== null), 'ewkb') : [];
            if (! $kept = $this->cut($piece['ewkb'], $buf, $min, $chain)) {
                continue;
            }
            if ($piece['family'] !== 'connector') {
                // Contre la géométrie déjà retenue seulement : les morceaux recalés de cette même
                // chaîne encadrent légitimement un passage gardé (ex. gare contournée par la voie verte).
                // Un morceau recalé qui longe la géométrie retenue est une AUTRE voie du même
                // faisceau (gare, voie parallèle) : retiré aussi.
                $kept = $this->dropShadows($kept, array_filter([$acc]), $source);
                if (! $kept) {
                    continue;
                }
            }
            $out[] = [$piece['family'], $piece['trace_ids'], $kept];
        }

        return $this->dropDanglingConnectors($out, $acc);
    }

    /**
     * Un raccord dont une extrémité ne touche plus rien (le morceau qu'il
     * reliait a été retiré : doublon parallèle, trop court) est retiré ; une
     * extrémité laissée près de la géométrie déjà retenue (découpe) sera recousue.
     */
    private function dropDanglingConnectors(array $out, ?string $acc): array
    {
        $others = array_column(array_filter($out, fn ($o) => $o[0] !== 'connector'), 2);
        foreach ($out as $n => [$family, , $ewkb]) {
            if ($family !== 'connector') {
                continue;
            }
            $row = DB::selectOne(<<<'SQL'
                WITH o AS (SELECT ST_Collect(ARRAY(SELECT ST_GeomFromEWKB(decode(x, 'hex')) FROM unnest(CAST(:others AS text[])) x)) AS g),
                a AS (SELECT CASE WHEN CAST(:acc AS text) IS NULL THEN NULL ELSE ST_GeomFromEWKB(decode(CAST(:acc2 AS text), 'hex')) END AS g)
                SELECT encode(ST_AsEWKB(ST_Collect(d.geom)), 'hex') AS ewkb
                FROM ST_Dump(ST_GeomFromEWKB(decode(:c, 'hex'))) d, o, a
                WHERE (ST_DWithin(ST_StartPoint(d.geom), o.g, 1) OR ST_DWithin(ST_StartPoint(d.geom), a.g, :tol))
                  AND (ST_DWithin(ST_EndPoint(d.geom), o.g, 1) OR ST_DWithin(ST_EndPoint(d.geom), a.g, :tol2))
                HAVING COUNT(*) > 0
                SQL, ['others' => '{'.implode(',', $others).'}', 'acc' => $acc, 'acc2' => $acc, 'c' => $ewkb,
                'tol' => self::MATCH_TOL_M + 2, 'tol2' => self::MATCH_TOL_M + 2]);
            if ($row?->ewkb) {
                $out[$n][2] = $row->ewkb;
            } else {
                unset($out[$n]);
            }
        }

        return array_values($out);
    }

    /**
     * Doublons parallèles : un morceau (gardé faute de donnée précise, ou recalé
     * sur une autre voie du même faisceau) qui LONGE
     * de bout en bout une géométrie déjà retenue de la ligne (à moins de
     * SHADOW_M partout, et projeté à l'intérieur de celle-ci, pas au-delà de
     * son extrémité) n'est qu'une seconde version, imprécise, de la même voie
     * (ex. tracé OHM décalé de 40-80 m le long de Paris - Brest) : il est
     * retiré. Un prolongement après la fin de la géométrie retenue, lui, se
     * projette sur son extrémité et reste. S'il s'agissait d'un ancien tracé,
     * il figure dans le rapport des divergences (source de la ligne).
     */
    private function dropShadows(string $kept, array $others, string $source): ?string
    {
        if (! $others) {
            return $kept;
        }
        $shadow = self::SHADOW_M[$source] ?? 120;
        $accepted = [];
        $parts = DB::select("SELECT encode(ST_AsEWKB(d.geom), 'hex') AS ewkb FROM ST_Dump(ST_GeomFromEWKB(decode(?, 'hex'))) d ORDER BY ST_Length(d.geom) DESC", [$kept]);
        foreach ($parts as $part) {
            $r = DB::selectOne(<<<'SQL'
                WITH t AS (SELECT ST_Union(ARRAY(SELECT ST_GeomFromEWKB(decode(x, 'hex')) FROM unnest(CAST(:geoms AS text[])) x)) AS g),
                tb AS (SELECT g, ST_Boundary(ST_CollectionExtract(g, 2)) AS ends FROM t),
                p AS (SELECT ST_GeomFromEWKB(decode(:p, 'hex')) AS g),
                s AS (
                    SELECT ST_LineInterpolatePoint(p.g, f::float8 / q.n) AS pt
                    FROM p, LATERAL (SELECT GREATEST(4, CEIL(ST_Length(p.g) / 25))::int AS n) q, LATERAL generate_series(0, q.n) f
                )
                SELECT MAX(ST_Distance(s.pt, tb.g)) AS maxd,
                       AVG(CASE WHEN tb.ends IS NULL OR ST_IsEmpty(tb.ends)
                                  OR ST_Distance(ST_ClosestPoint(tb.g, s.pt), tb.ends) > 10 THEN 1 ELSE 0 END) AS interior,
                       (SELECT ST_Length(g) FROM p) AS len
                FROM s, tb
                SQL, ['geoms' => '{'.implode(',', array_merge($others, $accepted)).'}', 'p' => $part->ewkb]);
            if ($r->maxd <= $shadow && $r->interior >= 0.8) {
                $this->conflationStats['shadow_m'] = ($this->conflationStats['shadow_m'] ?? 0) + $r->len;

                continue;
            }
            $accepted[] = $part->ewkb;
        }
        if (! $accepted) {
            return null;
        }

        return DB::selectOne("SELECT encode(ST_AsEWKB(ST_LineMerge(ST_Collect(ARRAY(SELECT ST_GeomFromEWKB(decode(x, 'hex')) FROM unnest(CAST(? AS text[])) x)))), 'hex') AS ewkb",
            ['{'.implode(',', $accepted).'}'])->ewkb;
    }

    /**
     * Tronc commun aux jonctions (line_reference_duplicates) : portion du tracé d'origine remplacée
     * par la voie d'une autre ligne (merged_trunk), ou long parallélisme sans convergence gardé et
     * signalé (kept_parallel). Raison : « tronc commun <code de l'autre ligne> » / « parallèle à … ».
     */
    private function storeJunctionRecords(int $lineId): void
    {
        foreach ($this->junctionRecords as $r) {
            $other = $r['lines'] ? DB::table('lines')->whereIn('id', $r['lines'])->orderBy('id')
                ->get(['id', 'code'])->map(fn ($l) => $l->code ?: '#'.$l->id)->implode(', ') : '?';
            DB::insert(<<<'SQL'
                INSERT INTO line_reference_duplicates (line_id, action, reason, source, matched_source, trace_ids, kept_source, kept_matched_source,
                    kept_trace_ids, length_m, gap_median_m, geom, created_at, updated_at)
                SELECT ?, ?, ?, ?, NULL, '[]'::jsonb, ?, NULL, CAST(? AS jsonb), ROUND(ST_Length(g))::int, ?, ST_Transform(g, 4326), NOW(), NOW()
                FROM (SELECT ST_GeomFromEWKB(decode(?, 'hex')) AS g) t
                WHERE ST_GeometryType(g) = 'ST_LineString' AND ST_Length(g) > 0
                SQL, [$lineId, $r['action'], mb_substr(($r['action'] === 'merged_trunk' ? 'tronc commun ' : (($r['why'] ?? null) ? $r['why'].' : ' : 'parallèle à ')).$other, 0, 40),
                $r['source'], $r['family'], json_encode($r['trace_ids']), round($r['gap'], 1), $r['ewkb']]);
        }
    }

    /** Tampon (EWKB hex) de la géométrie retenue, ou null. */
    private function buffer(?string $acc, float $tol): ?string
    {
        if ($acc === null) {
            return null;
        }

        return DB::selectOne("SELECT encode(ST_AsEWKB(ST_Buffer(ST_GeomFromEWKB(decode(?, 'hex')), ?)), 'hex') AS ewkb", [$acc, $tol])->ewkb;
    }

    /**
     * Géométrie hors tampon, morceaux d'au moins $min mètres (ou touchant l'une
     * des géométries $keepTouching), fusionnés (EWKB hex) ou null.
     */
    private function cut(string $ewkb, ?string $buffer, float $min, array $keepTouching = []): ?string
    {
        $row = DB::selectOne(<<<'SQL'
            WITH g AS (
                SELECT CASE WHEN CAST(:buf AS text) IS NULL THEN ST_GeomFromEWKB(decode(:g, 'hex'))
                            ELSE ST_Difference(ST_GeomFromEWKB(decode(:g2, 'hex')), ST_GeomFromEWKB(decode(CAST(:buf2 AS text), 'hex'))) END AS g
            ),
            t AS (SELECT ST_Collect(ARRAY(SELECT ST_GeomFromEWKB(decode(x, 'hex')) FROM unnest(CAST(:touch AS text[])) x)) AS g)
            SELECT encode(ST_AsEWKB(ST_LineMerge(ST_Collect(d.geom))), 'hex') AS ewkb
            FROM g, t, LATERAL ST_Dump(ST_CollectionExtract(g.g, 2)) d
            WHERE ST_Length(d.geom) >= :min OR (t.g IS NOT NULL AND ST_Length(d.geom) >= 1 AND ST_DWithin(d.geom, t.g, 0.5))
            HAVING COUNT(*) > 0
            SQL, ['g' => $ewkb, 'g2' => $ewkb, 'buf' => $buffer, 'buf2' => $buffer, 'min' => $min, 'touch' => '{'.implode(',', $keepTouching).'}']);

        return $row?->ewkb;
    }

    /**
     * Raccords droits entre les extrémités « en l'air » des morceaux ajoutés et
     * le point le plus proche de la géométrie déjà retenue (≤ $max m).
     */
    private function stitch(array $added, string $acc, float $max): ?string
    {
        return DB::selectOne(<<<'SQL'
            WITH a AS (SELECT ST_GeomFromEWKB(decode(:acc, 'hex')) AS g),
            ad AS (
                SELECT d.geom AS g FROM unnest(CAST(:geoms AS text[])) x,
                       LATERAL ST_Dump(ST_GeomFromEWKB(decode(x, 'hex'))) d
            ),
            ends AS (SELECT ST_StartPoint(g) AS p FROM ad UNION ALL SELECT ST_EndPoint(g) FROM ad),
            lone AS (SELECT e.p FROM ends e WHERE (SELECT COUNT(*) FROM ad WHERE ST_DWithin(ad.g, e.p, 0.5)) = 1)
            SELECT encode(ST_AsEWKB(ST_Collect(ST_MakeLine(l.p, ST_ClosestPoint(a.g, l.p)))), 'hex') AS ewkb
            FROM lone l, a
            WHERE ST_DWithin(l.p, a.g, :max) AND NOT ST_DWithin(l.p, a.g, 0.5)
            HAVING COUNT(*) > 0
            SQL, ['acc' => $acc, 'geoms' => '{'.implode(',', $added).'}', 'max' => $max])?->ewkb;
    }

    /**
     * Mesures de précision du tracé de référence des lignes visées :
     *   - km par provenance du dessin (précis / grossier) ;
     *   - cordes > 300 m dans le dessin grossier (et nombre total) ;
     *   - écart des portions non précises au meilleur tracé précis du même axe (≤ 60 m),
     *     échantillons tous les 50 m (0 par construction pour une portion précise).
     */
    private function metrics(array $lineIds): array
    {
        $ids = $this->pgIds($lineIds);
        $prov = "CASE WHEN p.matched_source IN ('connector', '".JunctionRepair::SMOOTHED."') THEN 'raccord'
                      WHEN p.matched_source IS NOT NULL OR p.source IN ('contrib', 'bdtopo', 'osm') THEN 'précis'
                      ELSE 'grossier' END";
        $m = [];
        foreach (DB::select("SELECT {$prov} AS prov, SUM(p.length_m) / 1000.0 AS km FROM line_reference_parts p WHERE p.line_id = ANY(CAST(? AS bigint[])) GROUP BY 1", [$ids]) as $r) {
            $m['km_'.$r->prov] = (float) $r->km;
        }
        foreach (DB::select('SELECT COALESCE(matched_source, source) AS src, SUM(length_m) / 1000.0 AS km FROM line_reference_parts p WHERE p.line_id = ANY(CAST(? AS bigint[])) GROUP BY 1', [$ids]) as $r) {
            $m['dessin_'.$r->src] = (float) $r->km;
        }
        $c = DB::selectOne(<<<SQL
            WITH segs AS (
                SELECT {$prov} AS prov, ST_Distance(ST_PointN(d.geom, n), ST_PointN(d.geom, n + 1)) AS len
                FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d,
                     LATERAL generate_series(1, ST_NPoints(d.geom) - 1) n
                WHERE p.line_id = ANY(CAST(:ids AS bigint[]))
            )
            SELECT COUNT(*) FILTER (WHERE len > 300 AND prov <> 'précis') AS chords_coarse,
                   COUNT(*) FILTER (WHERE len > 300) AS chords_all,
                   SUM(len) FILTER (WHERE len > 300 AND prov <> 'précis') / 1000.0 AS chords_coarse_km,
                   MAX(len) FILTER (WHERE prov <> 'précis') AS max_chord_coarse
            FROM segs
            SQL, ['ids' => $ids]);
        $m['cordes_300_grossier'] = (int) $c->chords_coarse;
        $m['cordes_300_km_grossier'] = (float) $c->chords_coarse_km;
        $m['cordes_300_total'] = (int) $c->chords_all;
        $m['corde_max_grossier_m'] = (float) $c->max_chord_coarse;

        // Écart au meilleur tracé précis du même axe (≤ 60 m) des portions NON précises.
        $rows = DB::select(<<<SQL
            WITH parts AS MATERIALIZED (
                SELECT {$prov} AS prov, d.geom AS g, ST_Length(d.geom) AS len
                FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
                WHERE p.line_id = ANY(CAST(:ids AS bigint[])) AND ST_Length(d.geom) > 1
            ),
            s AS MATERIALIZED (
                SELECT prov, ST_LineInterpolatePoint(g, (j + 0.5) / q.n) AS pt,
                       ST_Azimuth(ST_LineInterpolatePoint(g, GREATEST(0, (j + 0.5) / q.n - 6 / len)),
                                  ST_LineInterpolatePoint(g, LEAST(1, (j + 0.5) / q.n + 6 / len))) AS az
                FROM parts, LATERAL (SELECT GREATEST(1, ROUND(len / 50))::int AS n) q, LATERAL generate_series(0, q.n - 1) j
                WHERE prov <> 'précis'
            ),
            n AS MATERIALIZED (
                SELECT prov, (SELECT ST_Distance(c.g, s.pt) FROM cf_pool c
                        WHERE c.cand AND ST_DWithin(c.g, s.pt, 60) AND pg_temp.cf_angle(c.g, s.pt, s.az) <= 35
                        ORDER BY c.g <-> s.pt LIMIT 1) AS d
                FROM s
            )
            SELECT COUNT(*) AS samples, COUNT(d) AS with_ref,
                   percentile_cont(0.5) WITHIN GROUP (ORDER BY d) AS med,
                   percentile_cont(0.9) WITHIN GROUP (ORDER BY d) AS p90,
                   MAX(d) AS dmax,
                   COUNT(*) FILTER (WHERE d > 5) AS off5, COUNT(*) FILTER (WHERE d > 20) AS off20
            FROM n
            SQL, ['ids' => $ids]);
        $d = $rows[0];
        $m['grossier_echantillons_50m'] = (int) $d->samples;
        $m['grossier_avec_voie_precise_60m_pct'] = $d->samples ? round(100 * $d->with_ref / $d->samples, 1) : 0;
        $m['grossier_ecart_median_m'] = round((float) $d->med, 1);
        $m['grossier_ecart_p90_m'] = round((float) $d->p90, 1);
        $m['grossier_ecart_max_m'] = round((float) $d->dmax, 1);
        $m['grossier_ecart_sup_5m_km'] = round($d->off5 * 0.05, 1);
        $m['grossier_ecart_sup_20m_km'] = round($d->off20 * 0.05, 1);
        foreach (DB::select('SELECT confidence, SUM(length_m) / 1000.0 AS km FROM line_reference_parts WHERE line_id = ANY(CAST(? AS bigint[])) AND confidence IS NOT NULL GROUP BY 1', [$ids]) as $r) {
            $m['confiance_'.$r->confidence.'_km'] = (float) $r->km;
        }

        return $m;
    }

    private function printMetrics(array $sets): void
    {
        $keys = [];
        foreach ($sets as $set) {
            $keys = array_unique(array_merge($keys, array_keys($set)));
        }
        sort($keys);
        $rows = [];
        foreach ($keys as $k) {
            $rows[] = array_merge([$k], array_map(fn ($set) => isset($set[$k]) ? (is_float($set[$k]) ? round($set[$k], 2) : $set[$k]) : '', array_values($sets)));
        }
        $this->table(array_merge(['mesure'], array_keys($sets)), $rows);
    }

    /** CSV : portions sans géométrie précise (à retracer) et divergences entre sources. */
    private function writeReports(array $lineIds, bool $partial): void
    {
        $suffix = $partial ? '-partiel' : '';
        $disk = Storage::disk('local');

        // 1. Portions gardées faute de donnée précise (≥ 200 m), par ligne puis par portion.
        $rows = DB::select(<<<'SQL'
            WITH d AS (
                SELECT p.line_id, p.source, dd.geom AS g, ST_Length(ST_Transform(dd.geom, 2154)) AS len
                FROM line_reference_parts p, LATERAL ST_Dump(p.geom) dd
                WHERE p.line_id = ANY(CAST(:ids AS bigint[])) AND p.matched_source IS NULL
                  AND p.source IN ('archeoferroviaire', 'ohm', 'sncf')
            ),
            c AS (
                SELECT d.*, (SELECT MAX(ST_Distance(ST_PointN(ST_Transform(d.g, 2154), n), ST_PointN(ST_Transform(d.g, 2154), n + 1)))
                             FROM generate_series(1, ST_NPoints(d.g) - 1) n) AS max_chord,
                       ST_LineInterpolatePoint(d.g, 0.5) AS mid
                FROM d WHERE d.len >= 200
            )
            SELECT l.id, l.code, l.name, l.slug, c.source, ROUND(c.len) AS len, ROUND(c.max_chord) AS max_chord,
                   ROUND(ST_Y(c.mid)::numeric, 5) AS lat, ROUND(ST_X(c.mid)::numeric, 5) AS lon,
                   SUM(c.len) OVER (PARTITION BY l.id) AS line_len
            FROM c JOIN lines l ON l.id = c.line_id
            ORDER BY line_len DESC, l.id, c.len DESC
            SQL, ['ids' => $this->pgIds($lineIds)]);
        $csv = [['ligne_id', 'code', 'nom', 'source', 'km_ligne', 'longueur_m', 'plus_longue_corde_m', 'lat', 'lon', 'osm', 'geoportail', 'editeur']];
        foreach ($rows as $r) {
            $csv[] = [$r->id, $r->code, $r->name, $r->source, round($r->line_len / 1000, 2), $r->len, $r->max_chord, $r->lat, $r->lon,
                $this->osmLink($r->lat, $r->lon), $this->geoportailLink($r->lat, $r->lon), '/contribute/trace?line='.$r->slug];
        }
        $disk->put("reports/trace-sans-donnee-precise{$suffix}.csv", $this->toCsv($csv));
        $byLine = [];
        foreach ($rows as $r) {
            $byLine[$r->id] ??= [$r->id, $r->code, $r->name, round($r->line_len / 1000, 2), 0, '/contribute/trace?line='.$r->slug];
            $byLine[$r->id][4]++;
        }
        $disk->put("reports/trace-sans-donnee-precise-par-ligne{$suffix}.csv",
            $this->toCsv(array_merge([['ligne_id', 'code', 'nom', 'km_sans_donnee_precise', 'portions', 'editeur']], array_values($byLine))));
        $this->line(sprintf('  → storage/app/reports/trace-sans-donnee-precise%s.csv : %d portions, %d lignes, %s km',
            $suffix, count($rows), count($byLine), $this->km(array_sum(array_column($rows, 'len')))));

        // 2. Divergences entre sources (table line_reference_divergences), triées par écart × longueur.
        $divs = DB::select(<<<'SQL'
            SELECT d.*, l.code, l.name, ST_Y(ST_LineInterpolatePoint(d.geom, 0.5)) AS lat, ST_X(ST_LineInterpolatePoint(d.geom, 0.5)) AS lon
            FROM line_reference_divergences d JOIN lines l ON l.id = d.line_id
            WHERE d.line_id = ANY(CAST(? AS bigint[]))
            ORDER BY d.score DESC
            SQL, [$this->pgIds($lineIds)]);
        $csv = [['rang', 'ligne_id', 'code', 'nom', 'type', 'source_retenue', 'source_divergente', 'longueur_m', 'ecart_median_m', 'ecart_max_m',
            'score_m_km', 'ids_retenus', 'ids_divergents', 'lat', 'lon', 'osm', 'geoportail']];
        foreach ($divs as $i => $d) {
            $csv[] = [$i + 1, $d->line_id, $d->code, $d->name, $d->kind === 'precise' ? 'sources précises' : 'source de la ligne',
                $d->retained_source, $d->other_source, $d->length_m, $d->gap_median_m, $d->gap_max_m, $d->score,
                implode(' ', json_decode($d->retained_trace_ids ?? '[]', true)), implode(' ', json_decode($d->other_trace_ids ?? '[]', true)),
                round($d->lat, 6), round($d->lon, 6), $this->osmLink($d->lat, $d->lon), $this->geoportailLink($d->lat, $d->lon)];
        }
        $disk->put("reports/trace-divergences-sources{$suffix}.csv", $this->toCsv($csv));
        $this->line(sprintf('  → storage/app/reports/trace-divergences-sources%s.csv : %d divergences (%s km)',
            $suffix, count($divs), $this->km(array_sum(array_column($divs, 'length_m')))));

        // 3. Portions dédoublées d'une même ligne (étape « un seul axe ») : retirées, ou gardées comme tracé distinct.
        $dups = DB::select(<<<'SQL'
            SELECT d.*, l.code, l.name, ST_Y(ST_LineInterpolatePoint(d.geom, 0.5)) AS lat, ST_X(ST_LineInterpolatePoint(d.geom, 0.5)) AS lon
            FROM line_reference_duplicates d JOIN lines l ON l.id = d.line_id
            WHERE d.line_id = ANY(CAST(? AS bigint[]))
            ORDER BY d.action DESC, d.length_m DESC
            SQL, [$this->pgIds($lineIds)]);
        $csv = [['ligne_id', 'code', 'nom', 'action', 'cause', 'source', 'dessin', 'ids', 'source_gardee', 'dessin_garde', 'ids_gardes',
            'longueur_m', 'ecart_median_m', 'lat', 'lon', 'osm', 'geoportail']];
        foreach ($dups as $d) {
            $csv[] = [$d->line_id, $d->code, $d->name, self::DUPLICATE_ACTIONS[$d->action] ?? $d->action, $d->reason,
                $d->source, $d->matched_source, implode(' ', json_decode($d->trace_ids ?? '[]', true)),
                $d->kept_source, $d->kept_matched_source, implode(' ', json_decode($d->kept_trace_ids ?? '[]', true)),
                $d->length_m, $d->gap_median_m, round($d->lat, 6), round($d->lon, 6), $this->osmLink($d->lat, $d->lon), $this->geoportailLink($d->lat, $d->lon)];
        }
        $disk->put("reports/trace-doublons{$suffix}.csv", $this->toCsv($csv));
        $this->line(sprintf('  → storage/app/reports/trace-doublons%s.csv : %d portions retirées (%s km), %d tracés distincts gardés (%s km)',
            $suffix, count($r = array_filter($dups, fn ($d) => $d->action === 'removed')), $this->km(array_sum(array_column($r, 'length_m'))),
            count($k = array_filter($dups, fn ($d) => $d->action === 'kept_distinct')), $this->km(array_sum(array_column($k, 'length_m')))));
        $j = array_filter($dups, fn ($d) => $d->action === 'merged_trunk');
        $q = array_filter($dups, fn ($d) => $d->action === 'kept_parallel');
        $this->line(sprintf('    dont tronc commun aux jonctions : %d portions (%s km) ; parallèles sans convergence à vérifier : %d (%s km)',
            count($j), $this->km(array_sum(array_column($j, 'length_m'))), count($q), $this->km(array_sum(array_column($q, 'length_m')))));
    }

    private function osmLink(float $lat, float $lon): string
    {
        return sprintf('https://www.openstreetmap.org/#map=17/%.5f/%.5f&layers=T', $lat, $lon);
    }

    private function geoportailLink(float $lat, float $lon): string
    {
        return sprintf('https://www.geoportail.gouv.fr/carte?c=%.5f,%.5f&z=17&l0=ORTHOIMAGERY.ORTHOPHOTOS::GEOPORTAIL:OGC:WMTS(1)&l1=TRANSPORTNETWORKS.RAILWAYS::GEOPORTAIL:OGC:WMTS(1)&permalink=yes', $lon, $lat);
    }

    private function toCsv(array $rows): string
    {
        $h = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($h, $row, ',', '"', '');
        }
        rewind($h);

        return stream_get_contents($h);
    }

    private function km(float $m): string
    {
        return number_format($m / 1000, 1, ',', ' ');
    }

    private function pgIds(array $ids): string
    {
        return '{'.implode(',', array_map('intval', $ids)).'}';
    }
}
