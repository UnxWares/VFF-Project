<?php

namespace App\Services\SegmentAudit;

use App\Enums\AuditGrade;

/**
 * Règles de notation de l'audit des tronçons (docs/AUDIT-TRONCONS.md), sans base de données : les
 * faits sont mesurés par SegmentAuditor, ces fonctions les transforment en notes et en preuves.
 * Les codes de `reasons` sont stables (traduits dans lang/{fr,en,de}/audit_segments.php, clé
 * `reasons.<code>`) ; les seuils sont les constantes ci-dessous.
 *
 * Classement (état du tronçon comparé à chaque source datée) :
 *   classes : open (en service, voie unique, voie de service), closed (fermé, voie en place),
 *             gone (déposé, rasé), not_open (pas encore ouvert, en construction) ; une source peut
 *             aussi dire in_place (voie en place, ouverte ou fermée) ou closed_in_place (fermée avec
 *             la voie maintenue en place, selon SNCF Réseau : un tronçon déposé la contredit).
 *   un train touristique (is_heritage) n'est pas contredit par une source qui dit la ligne fermée ;
 *   ère courante : quand deux observations de l'état actuel sont d'accord et qu'aucune ne contredit,
 *   l'avis contraire d'une chronologie devient faible ; un tronçon « fermé, voie en place » sans
 *   aucune observation de voie, fermé depuis plus de OLD_CLOSURE_YEARS ans, est « à revoir »
 *   (règle track_unconfirmed : dépose probable).
 *   ère courante : une seule contradiction forte, venue de la BD TOPO ou d'un statut SNCF « déclassée
 *   vendue », devient faible quand deux observations actuelles fortes sont d'accord (règle
 *   observation_outvoted ; OSM « abandoned » ou « razed » reste une vraie contradiction) ;
 *   ère passée : un tronçon en service que seules des sources faibles confirment reste « sûr » quand
 *   aucune fin datée (fermeture, déclassement, dépose) ne le touche à cette ère et que le même tronçon
 *   est en service et sûr à l'ère courante (règle continuous_service ; règle R2, règles `.8` : une fin
 *   datée après l'ère, ou localisée ailleurs sur la ligne, ne compte plus, ni une fin suivie d'une
 *   réouverture avant l'ère, SegmentAuditor::blocksContinuity) ;
 *   une exploitation associative (heritage_start) n'est pas une première ouverture : une source qui ne
 *   date que le train touristique et des fins ne dit rien avant son début (règle R1) ; une fin des
 *   voyageurs seule n'est pas une fermeture : la source est d'accord avec un tronçon en fret (règle R3,
 *   avis `passenger_end_only`) ;
 *   un avis « contredit » est faible quand il change à ±NEAR_DATE_YEARS ans de la date de l'ère
 *   (la date d'un événement voisin suffit à l'expliquer), qu'il vient d'une source faible (WEAK_SOURCES)
 *   ou d'une observation qui n'est pas rattachée à la ligne (voie OSM d'une autre ligne, état
 *   relevé par lignes-oubliées).
 *   good    : au moins une source d'accord (hors sources faibles), aucune contradiction forte, et
 *             aucun avis faible contraire d'une source mieux classée que celles qui sont d'accord ;
 *   poor    : une contradiction forte d'une source fiable, sans accord d'une source au moins aussi
 *             fiable ; ou une règle métier violée (voyageurs seuls hors LGV) ;
 *   fair    : le reste (accord et contradiction mêlés, contradictions faibles, accord de sources faibles) ;
 *   unknown : aucune source ne parle de ce tronçon à cette date.
 *
 * Placement (écart du tracé à la voie réelle, points tous les SAMPLE_STEP_M mètres) :
 *   voie de référence : BD TOPO, OSM ou tracé relevé sur les photographies aériennes de l'IGN, à
 *   moins de REFERENCE_RADIUS_M, en prenant une source DIFFÉRENTE de celle du tracé, et jamais une
 *   voie rattachée à une autre ligne (elle passe souvent à quelques mètres d'une plate-forme propre) ;
 *   tronçon posé sur la voie d'une autre ligne (il ne colle qu'à elle, à TOLERANCE_M près, et pas aux voies
 *   libres ou de la ligne de la même source) :
 *     on_other_line_track si une voie rattachée à sa propre ligne suit le tronçon entre OWN_TRACK_MIN_M
 *          et OWN_TRACK_MAX_M : poor pour un tronçon fermé ou déposé (voie-mère ou embranchement recalé
 *          sur la ligne voisine) ; good pour un tronçon en service à l'ère courante, comme shared_track,
 *          la raison restant affichée (règle T1d, règles `.7` : dans les doubles voies et les faisceaux, chaque
 *          voie est souvent rattachée à une ligne différente, 8 alertes sur 12 étaient fausses au contrôle à
 *          la main) ; ce « sûr » ne recale pas le tracé (la note de tracé ne le compte pas comme calé) ;
 *     good (shared_track) sinon : tronc commun, voie de la ligne elle-même à moins de OWN_TRACK_MIN_M
 *          (même dans la source du tracé), ou embranchement posé sur la voie de départ sans voie propre
 *          connue (la note d'avant les réglages, mais signalée) ;
 *   good : médiane et 95e centile ≤ TOLERANCE_M (cible : médiane ≤ TARGET_M) sur au moins
 *          FULL_COVERAGE des points ; à l'ère courante, une autre référence indépendante qui colle ainsi suffit
 *          quand la première retenue (BD TOPO) est loin (règles `.7`) ; fair : médiane ≤ TOLERANCE_M, ou couverture partielle ;
 *   poor : médiane > TOLERANCE_M, ou tronçon en service à l'ère courante sans aucune voie de référence proche ;
 *   unknown : tronçon fermé ou déposé sans référence, tronçon hors de France (abroad), ou seule la
 *             source du tracé elle-même. Ère passée : la référence indépendante la plus proche est
 *             retenue (la voie a pu être déplacée depuis), et un tracé OSM rattaché à la ligne, qui le
 *             suit sur tous les points, n'est pas « faux » quand seule la BD TOPO est à plus de
 *             DISTANT_REFERENCE_M (distant_reference_only, règle R4).
 *
 * Exactitude (défauts de géométrie, gares) :
 *   poor : aller-retour (virage > HAIRPIN_DEG sur 40 m), écart de jonction > JUNCTION_POOR_M,
 *          trou non couvert, gare à plus de STATION_FAR_M de sa ligne ;
 *   fair : coude (virage > KINK_DEG sur 40 m), écart de jonction entre JUNCTION_MIN_M et
 *          JUNCTION_POOR_M, gare entre STATION_NEAR_M et STATION_FAR_M ;
 *   good : aucun défaut.
 *
 * Tracé (source retenue et précision connue) :
 *   good : source précise (BD TOPO 2,5 m, OSM environ 2 m), ou tracé d'une autre source déjà calé sur
 *          la voie réelle (placement « sûr », hors on_other_line_track), ou confirmé par une voie BD TOPO ou OSM indépendante qui
 *          n'est pas celle d'une autre ligne (CONFIRM_COVERAGE des points, médiane ≤ TARGET_M,
 *          95e centile ≤ TOLERANCE_M : confirmed_by_precise_source) ;
 *   fair : précision moyenne (OHM, tracé déclaré ou contribué), ou source peu précise sans meilleure
 *          trace connue, ou trace inutilisée dans un trou voisin ;
 *   poor : source peu précise (formes SNCF généralisées, archeoferroviaire non recalé) alors qu'une voie
 *          BD TOPO ou OSM suit le tronçon sur au moins MIN_COVERAGE des points ;
 *   unknown : source inconnue.
 */
final class AuditGrader
{
    /** Version des règles, recopiée dans chaque ligne d'audit (à changer avec les seuils). */
    public const RULE_VERSION = '2026-11-21.8';

    /** Ancienneté (ans) d'une fermeture au-delà de laquelle une voie « en place » non observée est suspecte. */
    public const OLD_CLOSURE_YEARS = 50;

    public const NEAR_DATE_YEARS = 3;

    /** Règles métier qui ne font qu'un doute (note « à revoir » au plus). */
    public const SOFT_RULES = ['track_unconfirmed'];

    /** Sources de dates faibles : estimations, décisions administratives tardives, Wikidata. */
    public const WEAK_SOURCES = ['estimation-reseau', 'lignes-oubliees-pdf', 'wikidata'];

    public const SAMPLE_STEP_M = 50.0;

    public const MAX_SAMPLES = 200;

    public const REFERENCE_RADIUS_M = 50.0;

    public const TARGET_M = 1.0;

    public const TOLERANCE_M = 5.0;

    public const MIN_COVERAGE = 0.5;

    public const FULL_COVERAGE = 0.8;

    /** Écarts entre lesquels une voie rattachée à la ligne compte comme « sa propre voie », à côté. */
    public const OWN_TRACK_MIN_M = 8.0;

    public const OWN_TRACK_MAX_M = 60.0;

    /**
     * Ère passée (R4) : écart au-delà duquel une référence BD TOPO seule ne prouve plus qu'un tracé OSM
     * rattaché à la ligne est faux (voie déplacée, voie voisine, faisceau généralisé).
     */
    public const DISTANT_REFERENCE_M = 15.0;

    /** Part des points qu'une source précise indépendante doit suivre pour confirmer un tracé (T2). */
    public const CONFIRM_COVERAGE = 0.8;

    public const HAIRPIN_DEG = 150.0;

    public const KINK_DEG = 60.0;

    public const JUNCTION_MIN_M = 0.05;

    public const JUNCTION_POOR_M = 1.0;

    public const JUNCTION_MAX_M = 10.0;

    public const HOLE_MIN_M = 0.5;

    public const HOLE_MAX_M = 500.0;

    public const STATION_NEAR_M = 150.0;

    public const STATION_FAR_M = 500.0;

    /** Écart relatif toléré entre la longueur du tracé de référence et la longueur officielle. */
    public const LENGTH_FAIR = 0.1;

    public const LENGTH_POOR = 0.25;

    /** Part de longueur au-delà de laquelle une note de tronçon devient celle de la ligne. */
    public const LINE_SHARE = 0.1;

    /** Ordre de préférence des voies de référence du placement. */
    public const REFERENCES = ['bdtopo', 'osm', 'ign-historique'];

    /**
     * Précision connue des sources de tracé (mètres, null si inconnue) et sa classe.
     *
     * @var array<string, array{0: ?float, 1: 'high'|'medium'|'low'}>
     */
    public const TRACE_PRECISION = [
        'bdtopo' => [2.5, 'high'],
        'osm' => [2.0, 'high'],
        'ohm' => [null, 'medium'],
        'ign-historique' => [null, 'medium'],
        'declared' => [null, 'medium'],
        'manual' => [null, 'medium'],
        'contrib' => [null, 'medium'],
        'sncf' => [165.0, 'low'],
        'archeoferroviaire' => [null, 'low'],
        'model' => [null, 'low'],
    ];

    /** Classe d'état d'un statut de tronçon (line_segments.status). */
    public static function statusClass(string $status): string
    {
        return match ($status) {
            'in_service', 'single_track', 'service_track' => 'open',
            'construction' => 'not_open',
            'abandoned' => 'closed',
            default => 'gone', // dismantled, razed
        };
    }

    /**
     * État attendu par une chronologie (EraTimeline::stateAt) : null veut dire « pas encore
     * ouverte » si la première ouverture de la source est postérieure, « disparue » sinon.
     *
     * @param  array{status: string, traffic: string}|null  $state
     * @return array{class: string, traffic: ?string}
     */
    public static function expectedFromState(?array $state, ?int $firstOpeningYear, int $year): array
    {
        if ($state === null) {
            return ['class' => $firstOpeningYear !== null && $firstOpeningYear > $year ? 'not_open' : 'gone', 'traffic' => null];
        }
        $class = self::statusClass($state['status']);

        return ['class' => $class, 'traffic' => $class === 'open' && $state['traffic'] !== 'unknown' ? $state['traffic'] : null];
    }

    /**
     * Avis d'une source sur l'état réel : true (d'accord) ou false (contredit), et la raison.
     *
     * @param  array{class: string, traffic?: ?string}  $expected
     * @return array{0: bool, 1: ?string} [d'accord, code de la contradiction]
     */
    public static function compare(string $status, string $traffic, array $expected): array
    {
        $actual = self::statusClass($status);
        $want = $expected['class'];
        $agrees = match ($want) {
            'in_place' => in_array($actual, ['open', 'closed'], true),
            'closed_in_place' => $actual === 'closed',
            // Une source qui dit « fermée » sans parler de dépose ne contredit pas une voie déposée.
            'closed' => in_array($actual, ['closed', 'gone'], true),
            default => $actual === $want,
        };
        if (! $agrees) {
            return [false, 'expected_'.$want];
        }
        $wantTraffic = $expected['traffic'] ?? null;
        if ($actual === 'open' && $wantTraffic !== null && $traffic !== 'unknown') {
            $wantFreight = $wantTraffic === 'freight';
            if ($wantFreight !== ($traffic === 'freight')) {
                return [false, $wantFreight ? 'expected_freight_only' : 'expected_passengers'];
            }
        }

        return [true, null];
    }

    /**
     * Note de classement à partir des avis des sources et des règles métier.
     *
     * @param  list<array{source: string, agrees: bool, weak?: bool, near_date?: bool, priority?: int, outvoted?: bool}>  $verdicts
     * @param  list<string>  $violatedRules
     * @param  bool  $continuity  ère passée : aucune fin datée ne touche le tronçon à cette ère, tronçon en service et sûr à l'ère courante
     * @return array{grade: AuditGrade, reasons: list<string>}
     */
    public static function classification(array $verdicts, array $violatedRules = [], bool $continuity = false): array
    {
        // « Dépose probable » n'est qu'un doute : à revoir, jamais faux à lui seul.
        $softRules = array_values(array_intersect($violatedRules, self::SOFT_RULES));
        $violatedRules = array_values(array_diff($violatedRules, self::SOFT_RULES));
        $reasons = array_map(fn (string $r): string => 'rule_'.$r, [...$violatedRules, ...$softRules]);
        if ($verdicts === []) {
            $grade = $violatedRules ? AuditGrade::Poor : ($softRules ? AuditGrade::Fair : AuditGrade::Unknown);

            return ['grade' => $grade, 'reasons' => [...$reasons, 'no_source']];
        }
        $strongAgree = array_filter($verdicts, fn (array $v): bool => $v['agrees'] && ! ($v['weak'] ?? false));
        $strongContra = array_filter($verdicts, fn (array $v): bool => ! $v['agrees'] && ! ($v['weak'] ?? false) && ! ($v['near_date'] ?? false));
        $softContra = array_filter($verdicts, fn (array $v): bool => ! $v['agrees'] && (($v['weak'] ?? false) || ($v['near_date'] ?? false)));
        $best = fn (array $list): int => min(array_map(fn (array $v): int => $v['priority'] ?? 50, $list) ?: [PHP_INT_MAX]);
        $bestAgree = $best($strongAgree);
        $bestContra = $best($strongContra);

        if ($violatedRules !== []) {
            $grade = AuditGrade::Poor;
        } elseif ($strongContra !== [] && $bestContra < $bestAgree) {
            $grade = AuditGrade::Poor;
            $reasons[] = 'source_contradicts';
        } elseif ($strongContra !== []) {
            $grade = AuditGrade::Fair;
            $reasons[] = 'sources_disagree';
        } elseif ($strongAgree === [] && $softContra === [] && $continuity) {
            // Ère passée : seules des sources faibles la datent, mais aucune fin datée ne le touche à cette
            // ère et le même tronçon est sûr aujourd'hui.
            $grade = AuditGrade::Good;
            $reasons[] = 'continuous_service';
        } elseif ($strongAgree === []) {
            $grade = AuditGrade::Fair;
            $reasons[] = $softContra !== [] ? 'weak_contradiction' : 'weak_sources_only';
        } elseif ($softContra !== [] && $best($softContra) < $bestAgree) {
            // Avis faible, mais d'une source mieux classée que toutes celles qui sont d'accord.
            $grade = AuditGrade::Fair;
            $reasons[] = 'weak_contradiction';
        } else {
            $grade = AuditGrade::Good;
            $reasons[] = 'sources_agree';
        }
        if ($softRules !== [] && $grade === AuditGrade::Good) {
            $grade = AuditGrade::Fair;
        }
        if (array_filter($verdicts, fn (array $v): bool => $v['outvoted'] ?? false) !== []) {
            $reasons[] = 'observation_outvoted';
        }

        return ['grade' => $grade, 'reasons' => $reasons];
    }

    /**
     * Note de placement.
     *
     * `$refs` : par voie de référence, l'écart aux voies qui ne sont pas rattachées à une autre ligne
     * (voies de la ligne et voies non rattachées). Clés facultatives, mesurées sur les mêmes points :
     * `all` (toutes les voies, celles des autres lignes comprises) et `own` (voies rattachées à la
     * ligne, à moins de OWN_TRACK_MAX_M).
     *
     * @param  array<string, array{points: int, found: int, median_m: ?float, p95_m: ?float, all?: array{found: int, median_m: ?float, p95_m: ?float}, own?: array{found: int, median_m: ?float}}>  $refs
     * @return array{grade: AuditGrade, reasons: list<string>, reference: ?string, same_source: bool, coverage: float, median_m: ?float, p95_m: ?float}
     */
    public static function placement(array $refs, ?string $source, bool $activeToday, bool $abroad = false, bool $bestMatch = false): array
    {
        $result = self::placementOn($refs, $source, $activeToday, $abroad, $bestMatch);
        if ($result['grade'] === AuditGrade::Good) {
            return $result;
        }
        $other = self::onOtherLine($refs, $source);
        if ($other === null && ! $bestMatch && ($fit = self::fittingReference($refs, $source)) !== null) {
            return $fit;
        }
        if ($other === null) {
            return $bestMatch && $result['grade'] === AuditGrade::Poor && self::ownOsmTraceFarFromBdtopo($refs, $source, $result)
                ? ['grade' => AuditGrade::Unknown, 'reasons' => ['distant_reference_only']] + $result
                : $result;
        }
        [$name, $all, $own] = $other;
        $points = $refs[$name]['points'];
        $measured = ['reference' => $name, 'same_source' => false, 'coverage' => round($all['found'] / max(1, $points), 3),
            'median_m' => $all['median_m'], 'p95_m' => $all['p95_m']];
        if ($own !== null) {
            // Voie-mère ou embranchement recalé sur la ligne voisine, sa propre voie passe à côté. En service
            // aujourd'hui (T1d) : le plus souvent une double voie dont chaque voie est rattachée à une ligne
            // différente ; la note reste sûre, la raison signale le cas.
            return ['grade' => $activeToday ? AuditGrade::Good : AuditGrade::Poor, 'reasons' => ['on_other_line_track'], 'median_m' => $own] + $measured;
        }

        return ['grade' => AuditGrade::Good, 'reasons' => ['shared_track']] + $measured;
    }

    /**
     * Ère courante (règles `.7`) : la première référence retenue (BD TOPO avant OSM) ne colle pas, mais une
     * autre voie BD TOPO ou OSM indépendante colle au tronçon sur ses voies libres ou de la ligne (FULL_COVERAGE
     * des points, médiane et 95e centile ≤ TOLERANCE_M), et aucune voie rattachée à la ligne ne passe à côté
     * entre OWN_TRACK_MIN_M et OWN_TRACK_MAX_M (sinon la voie libre est peut-être celle d'une autre ligne :
     * cas T1b, non tranché). Le tronçon est sur une voie réelle : placement « sûr » sur cette référence. Avant les règles `.5`, ces cas sortaient `shared_track` (sûr) ; les règles `.5`
     * les laissaient « faux » sur la BD TOPO lointaine (43 tronçons mesurés en 2030, cas 33 et 34 du
     * troisième contrôle à la main).
     *
     * @param  array<string, array<string, mixed>>  $refs
     * @return array{grade: AuditGrade, reasons: list<string>, reference: string, same_source: bool, coverage: float, median_m: float, p95_m: float}|null
     */
    private static function fittingReference(array $refs, ?string $source): ?array
    {
        foreach (self::REFERENCES as $name) {
            $o = $refs[$name]['own'] ?? null;
            if ($o !== null && $refs[$name]['points'] > 0 && $o['found'] / $refs[$name]['points'] >= self::MIN_COVERAGE
                && $o['median_m'] !== null && $o['median_m'] >= self::OWN_TRACK_MIN_M && $o['median_m'] <= self::OWN_TRACK_MAX_M) {
                return null;
            }
        }
        foreach (['bdtopo', 'osm'] as $name) {
            $r = $refs[$name] ?? null;
            if ($name === $source || $r === null || ! self::fits($r['found'], $r['points'], $r['median_m'], $r['p95_m'])) {
                continue;
            }

            return ['grade' => AuditGrade::Good, 'reasons' => [$r['median_m'] <= self::TARGET_M ? 'within_target' : 'within_tolerance'],
                'reference' => $name, 'same_source' => false, 'coverage' => round(self::coverage($r), 3),
                'median_m' => $r['median_m'], 'p95_m' => $r['p95_m']];
        }

        return null;
    }

    /**
     * Le placement « sûr » cale-t-il le tracé sur sa voie réelle (note de tracé `recalibrated`) ? Pas
     * quand il est posé sur la voie d'une autre ligne (`on_other_line_track`, T1d) : la voie qui le
     * confirme n'est pas la sienne.
     *
     * @param  array{grade: AuditGrade, reasons: list<string>}  $placement
     */
    public static function alignedOnOwnTrack(array $placement): bool
    {
        return $placement['grade'] === AuditGrade::Good && ! in_array('on_other_line_track', $placement['reasons'], true);
    }

    /**
     * Le tronçon ne colle qu'à la voie d'une autre ligne : référence (hors source du tracé) dont toutes
     * les voies donnent un placement « sûr », mais pas ses voies libres ou de la ligne. Rend [référence, mesure sur toutes les voies, médiane de
     * l'écart à la propre voie de la ligne si elle suit le tronçon entre OWN_TRACK_MIN_M et OWN_TRACK_MAX_M].
     *
     * @param  array<string, array<string, mixed>>  $refs
     * @return array{0: string, 1: array{found: int, median_m: ?float, p95_m: ?float}, 2: ?float}|null
     */
    private static function onOtherLine(array $refs, ?string $source): ?array
    {
        $own = null;
        $onOwnTrack = false;
        foreach (self::REFERENCES as $name) {
            $o = $refs[$name]['own'] ?? null;
            // Une voie de la ligne elle-même (même celle de la source du tracé) suit le tronçon : il est sur sa voie,
            // et la voie de l'autre ligne qui le confirme est un rattachement douteux (voie partagée).
            $onOwnTrack = $onOwnTrack || ($o !== null && $refs[$name]['points'] > 0 && $o['found'] / $refs[$name]['points'] >= self::MIN_COVERAGE
                && $o['median_m'] !== null && $o['median_m'] < self::OWN_TRACK_MIN_M);
        }
        foreach (self::REFERENCES as $name) {
            $o = $refs[$name]['own'] ?? null;
            if ($name !== $source && $o !== null && $refs[$name]['points'] > 0 && $o['found'] / $refs[$name]['points'] >= self::MIN_COVERAGE
                && $o['median_m'] !== null && $o['median_m'] >= self::OWN_TRACK_MIN_M && $o['median_m'] <= self::OWN_TRACK_MAX_M) {
                $own = $own === null ? $o['median_m'] : min($own, $o['median_m']);
            }
        }
        foreach (self::REFERENCES as $name) {
            $r = $refs[$name] ?? null;
            $all = $r['all'] ?? null;
            if ($name === $source || $r === null || $all === null || $r['points'] <= 0) {
                continue;
            }
            // La voie qui colle doit être celle d'une autre ligne : si les voies libres ou de la ligne de cette
            // référence collent aussi, le tronçon est sur une voie libre, pas sur celle d'une autre ligne.
            if (self::fits($all['found'], $r['points'], $all['median_m'], $all['p95_m'])
                && ! self::fits($r['found'], $r['points'], $r['median_m'], $r['p95_m'])) {
                return [$name, $all, $onOwnTrack ? null : $own];
            }
        }

        return null;
    }

    /**
     * Ère passée (R4, règles `.6`) : le tracé vient d'une voie OSM rattachée à la ligne, qui le suit sur tous
     * les points (voie propre à TARGET_M au plus en médiane, 95e centile des voies libres ou de la ligne à
     * TARGET_M au plus), et la seule référence indépendante retenue est la BD TOPO, à plus de
     * DISTANT_REFERENCE_M en médiane. La BD TOPO décrit la voie d'aujourd'hui : rien ne prouve que le tracé
     * de l'époque est faux (contrôle à la main du 21/11 : Bédarieux, Sarrebourg, La Châtre).
     *
     * @param  array<string, array<string, mixed>>  $refs
     * @param  array{reference: ?string, median_m: ?float}  $result
     */
    private static function ownOsmTraceFarFromBdtopo(array $refs, ?string $source, array $result): bool
    {
        $osm = $refs['osm'] ?? null;
        $own = $osm['own'] ?? null;

        return $source === 'osm' && $osm !== null && $own !== null && $osm['points'] > 0 && $own['found'] === $osm['points']
            && $own['median_m'] !== null && $own['median_m'] <= self::TARGET_M
            && $osm['p95_m'] !== null && $osm['p95_m'] <= self::TARGET_M
            && $result['reference'] === 'bdtopo' && $result['median_m'] !== null && $result['median_m'] > self::DISTANT_REFERENCE_M;
    }

    /** Placement « sûr » sur une mesure : FULL_COVERAGE des points, médiane et 95e centile ≤ TOLERANCE_M. */
    private static function fits(int $found, int $points, ?float $median, ?float $p95): bool
    {
        return $points > 0 && $found / $points >= self::FULL_COVERAGE && $median !== null && $p95 !== null
            && $median <= self::TOLERANCE_M && $p95 <= self::TOLERANCE_M;
    }

    /**
     * Note de placement sur les voies qui ne sont pas rattachées à une autre ligne.
     *
     * @param  array<string, array{points: int, found: int, median_m: ?float, p95_m: ?float}>  $refs
     * @return array{grade: AuditGrade, reasons: list<string>, reference: ?string, same_source: bool, coverage: float, median_m: ?float, p95_m: ?float}
     */
    private static function placementOn(array $refs, ?string $source, bool $activeToday, bool $abroad, bool $bestMatch): array
    {
        $pick = null;
        $sameSource = false;
        foreach (self::REFERENCES as $name) {
            $r = $refs[$name] ?? null;
            if ($name === $source || $r === null || self::coverage($r) < self::MIN_COVERAGE) {
                continue;
            }
            // Ère passée : la voie a pu être déplacée depuis (gare reconstruite, rectification) ;
            // la référence la plus proche (souvent le relevé sur photographies anciennes) compte.
            if ($pick === null || ($bestMatch && ($r['median_m'] ?? INF) < ($refs[$pick]['median_m'] ?? INF))) {
                $pick = $name;
            }
            if (! $bestMatch) {
                break;
            }
        }
        if ($pick === null && $source !== null && isset($refs[$source]) && self::coverage($refs[$source]) >= self::MIN_COVERAGE) {
            $pick = $source;
            $sameSource = true;
        }
        $empty = ['reference' => $pick, 'same_source' => $sameSource, 'coverage' => $pick ? round(self::coverage($refs[$pick]), 3) : 0.0, 'median_m' => null, 'p95_m' => null];
        if ($pick === null) {
            $poor = $activeToday && ! $abroad;

            return ['grade' => $poor ? AuditGrade::Poor : AuditGrade::Unknown, 'reasons' => [$poor ? 'no_track_nearby' : ($abroad ? 'abroad' : 'no_reference')]] + $empty;
        }
        if ($sameSource) {
            return ['grade' => AuditGrade::Unknown, 'reasons' => ['only_same_source']] + $empty;
        }
        $r = $refs[$pick];
        $median = $r['median_m'];
        $p95 = $r['p95_m'];
        $coverage = self::coverage($r);
        $reasons = [];
        if ($median > self::TOLERANCE_M) {
            $grade = AuditGrade::Poor;
            $reasons[] = 'median_over_tolerance';
        } elseif ($p95 > self::TOLERANCE_M) {
            $grade = AuditGrade::Fair;
            $reasons[] = 'p95_over_tolerance';
        } else {
            $grade = AuditGrade::Good;
            $reasons[] = $median <= self::TARGET_M ? 'within_target' : 'within_tolerance';
        }
        if ($coverage < self::FULL_COVERAGE) {
            $reasons[] = 'partial_coverage';
            if ($grade === AuditGrade::Good) {
                $grade = AuditGrade::Fair;
            }
        }

        return ['grade' => $grade, 'reasons' => $reasons, 'median_m' => $median, 'p95_m' => $p95, 'coverage' => round($coverage, 3)] + $empty;
    }

    /** @param  array{points: int, found: int}  $r */
    public static function coverage(array $r): float
    {
        return $r['points'] > 0 ? $r['found'] / $r['points'] : 0.0;
    }

    /**
     * Note d'exactitude d'un tronçon.
     *
     * @param  array{max_turn_deg: ?float, hairpins: int, kinks: int, junction_gaps: list<array{m: float}>, holes: list<array{m: float}>, stations: list<array{distance_m: float}>}  $facts
     * @return array{grade: AuditGrade, reasons: list<string>}
     */
    public static function accuracy(array $facts): array
    {
        $poor = [];
        $fair = [];
        if ($facts['hairpins'] > 0) {
            $poor[] = 'hairpin';
        } elseif ($facts['kinks'] > 0) {
            $fair[] = 'kink';
        }
        foreach ($facts['junction_gaps'] as $gap) {
            if ($gap['m'] > self::JUNCTION_POOR_M) {
                $poor[] = 'junction_gap';
            } elseif ($gap['m'] > self::JUNCTION_MIN_M) {
                $fair[] = 'junction_gap_small';
            }
        }
        if ($facts['holes'] !== []) {
            $poor[] = 'hole';
        }
        foreach ($facts['stations'] as $station) {
            if ($station['distance_m'] > self::STATION_FAR_M) {
                $poor[] = 'station_far';
            } elseif ($station['distance_m'] > self::STATION_NEAR_M) {
                $fair[] = 'station_off_track';
            }
        }
        $reasons = array_values(array_unique([...$poor, ...$fair]));

        return [
            'grade' => $poor !== [] ? AuditGrade::Poor : ($fair !== [] ? AuditGrade::Fair : AuditGrade::Good),
            'reasons' => $reasons ?: ['no_defect'],
        ];
    }

    /**
     * Note du tracé : précision de la source retenue et meilleures traces disponibles.
     *
     * @param  float  $preciseCoverage  part des points suivis par une voie BD TOPO ou OSM (0 à 1)
     * @param  bool  $aligned  placement « sûr » sur une voie de référence indépendante
     * @param  bool  $unusedTraceInGap  une trace non rattachée passe dans un trou voisin
     * @param  bool  $confirmed  une voie BD TOPO ou OSM indépendante, pas celle d'une autre ligne, suit le tracé
     *                           à TARGET_M près (cf. confirmedBy)
     * @return array{grade: AuditGrade, reasons: list<string>, precision_m: ?float, precision_class: ?string}
     */
    public static function trace(?string $source, float $preciseCoverage, bool $aligned = false, bool $unusedTraceInGap = false, bool $confirmed = false): array
    {
        [$precision, $class] = self::TRACE_PRECISION[$source ?? ''] ?? [null, null];
        $reasons = [];
        // Source peu précise, mais tracé déjà calé sur la voie réelle (placement « sûr ») : recalé.
        $recalibrated = $class !== 'high' && $class !== null && $aligned;
        // Source moyenne ou peu précise, mais une source précise indépendante la suit à 1 m près.
        $confirmed = $class !== 'high' && $class !== null && ! $recalibrated && $confirmed;
        $available = $preciseCoverage >= self::MIN_COVERAGE;
        $grade = match (true) {
            $class === 'high', $recalibrated, $confirmed => AuditGrade::Good,
            $class === 'medium' => AuditGrade::Fair,
            $class === 'low' => $available ? AuditGrade::Poor : AuditGrade::Fair,
            default => AuditGrade::Unknown,
        };
        $reasons[] = match (true) {
            $class === 'high' => 'precise_source',
            $recalibrated => 'recalibrated',
            $confirmed => 'confirmed_by_precise_source',
            $class === 'medium' => 'medium_source',
            $class === 'low' => $available ? 'precise_trace_available' : 'best_available',
            default => 'unknown_source',
        };
        if ($unusedTraceInGap) {
            $reasons[] = 'unused_trace_in_gap';
            if ($grade === AuditGrade::Good) {
                $grade = AuditGrade::Fair;
            }
        }

        return ['grade' => $grade, 'reasons' => $reasons, 'precision_m' => $precision, 'precision_class' => $class];
    }

    /**
     * Le tracé est-il confirmé par une source précise indépendante (T2) ? Une voie BD TOPO ou OSM,
     * autre que la source du tracé et jamais celle d'une autre ligne (`$refs` les exclut déjà), suit au
     * moins CONFIRM_COVERAGE des points avec une médiane ≤ TARGET_M et un 95e centile ≤ TOLERANCE_M.
     *
     * @param  array<string, array{points: int, found: int, median_m: ?float, p95_m: ?float}>  $refs
     */
    public static function confirmedBy(array $refs, ?string $source): bool
    {
        foreach (['bdtopo', 'osm'] as $name) {
            $r = $refs[$name] ?? null;
            if ($name !== $source && $r !== null && self::coverage($r) >= self::CONFIRM_COVERAGE
                && $r['median_m'] !== null && $r['p95_m'] !== null && $r['median_m'] <= self::TARGET_M && $r['p95_m'] <= self::TOLERANCE_M) {
                return true;
            }
        }

        return false;
    }

    /**
     * Note d'exactitude propre à la ligne : longueur du tracé de référence comparée à la longueur
     * officielle (ou à l'étendue des PK de lignes-oubliées, qui n'est qu'un minimum : $lowerBound),
     * ordre des gares le long des PK.
     *
     * @return array{grade: AuditGrade, reasons: list<string>, length_ratio: ?float}
     */
    public static function lineAccuracy(?int $traceLength, ?int $officialLength, int $orderInversions, bool $lowerBound = false): array
    {
        $reasons = [];
        $grade = AuditGrade::Good;
        $ratio = null;
        if ($traceLength !== null && $officialLength !== null && $officialLength > 0) {
            $ratio = round($traceLength / $officialLength, 3);
            // Étendue des PK cités par lignes-oubliées : une partie de la ligne seulement, donc un
            // minimum ; seul un tracé plus court qu'elle est suspect.
            $delta = $lowerBound ? max(0.0, 1 - $ratio) : abs($ratio - 1);
            if ($delta > self::LENGTH_POOR) {
                $grade = AuditGrade::Poor;
                $reasons[] = 'length_mismatch';
            } elseif ($delta > self::LENGTH_FAIR) {
                $grade = AuditGrade::Fair;
                $reasons[] = 'length_off';
            }
        }
        if ($orderInversions > 0) {
            $grade = AuditGrade::Poor;
            $reasons[] = 'station_order';
        }

        return ['grade' => $grade, 'reasons' => $reasons ?: ['no_defect'], 'length_ratio' => $ratio];
    }

    /**
     * Note d'une ligne sur un contrôle, d'après les longueurs de ses tronçons par note : poor dès
     * que LINE_SHARE de la longueur est faux, fair dès que LINE_SHARE est faux ou à revoir,
     * unknown si la moitié est inconnue, good sinon.
     *
     * @param  array<string, int>  $lengthByGrade  longueur (m) par valeur de note
     */
    public static function lineGrade(array $lengthByGrade): AuditGrade
    {
        $total = array_sum($lengthByGrade);
        if ($total <= 0) {
            return AuditGrade::Unknown;
        }
        $share = fn (string $g): float => ($lengthByGrade[$g] ?? 0) / $total;

        return match (true) {
            $share('poor') >= self::LINE_SHARE => AuditGrade::Poor,
            $share('poor') + $share('fair') >= self::LINE_SHARE => AuditGrade::Fair,
            $share('unknown') >= 0.5 => AuditGrade::Unknown,
            default => AuditGrade::Good,
        };
    }

    /** Score 0 (tout sûr) à 100 (tout faux) de quatre notes. */
    public static function score(AuditGrade ...$grades): int
    {
        $max = count($grades) * AuditGrade::Poor->points();

        return $max > 0 ? (int) round(100 * array_sum(array_map(fn (AuditGrade $g): int => $g->points(), $grades)) / $max) : 0;
    }
}
