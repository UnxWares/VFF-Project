<?php

namespace App\Services;

use App\Console\Commands\AttachStations;
use App\Console\Commands\BuildSharedTrack;
use App\Services\Materials\MaterialGaugeRule;
use App\Services\Materials\MaterialTractionRule;
use App\Support\Lgv;
use App\Support\TrackPriority;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Invariants des données réelles (carte décennale, gares, textes), vérifiés
 * en LECTURE SEULE : uniquement des SELECT, dans une transaction READ ONLY
 * quand aucune transaction n'est ouverte (cf. vff:check-invariants).
 *
 * Chaque invariant donne un nombre de cas et quelques exemples. Ils complètent
 * vff:check-consistency (règles R1/R2, événements, complétude) sans le
 * dupliquer : ici, ce qui ne doit JAMAIS arriver dans le résultat du moteur.
 *
 * Règles métier vérifiées (données de l'utilisateur) :
 *   - ère courante : pas de tronçon « fret seul » qui porte une gare voyageurs
 *     SNCF desservie de son code (voyageurs implique mixte, portion par portion :
 *     une ligne peut être fret seul au-delà de sa dernière gare voyageurs) ;
 *   - ère courante : pas de portion fermée (voie en place) qui porte une gare voyageurs
 *     desservie sur un tronçon SNCF « Exploitée » (fausse fermeture d'une source :
 *     366000 fermée en 2030 entre Lison et Cherbourg par un déraillement) ;
 *   - pas de LGV ni de raccordement LGV en service avant 1981 ;
 *   - pas de portion en service avant la première ouverture datée de sa ligne
 *     (tolérance : l'ère de l'ouverture) ;
 *   - pas de gare « active » sans voie en service à moins de 150 m ;
 *   - une voie déposée n'apparaît « déposée » que dans UNE ère ;
 *   - km en service par ère dans les fourchettes historiques
 *     (ConsistencyChecker::HISTORICAL_KM) ;
 *   - pas de chute de plus de 50 % des km d'une ligne sans fermeture ni dépose (une portion
 *     transférée en voie de service compte encore comme exploitée ; une reprise associative
 *     datée explique la chute, pas une simple attestation) ;
 *   - pas de tronçon en double (même ligne, même ère) ;
 *   - pas de tracé peu précis qui double à 10-30 m la voie d'une autre ligne (jonctions : tronc
 *     commun dessiné deux fois, ex. Vire), sauf parallèle distinct signalé par vff:build-line-references ;
 *   - patrimoine : train touristique / musée en service, vélorail fermé ;
 *   - pas de tiret long (U+2014) ni de double tiret dans les textes affichés ;
 *   - ère courante : pas de tronçon patrimoine sur une gare voyageurs desservie ;
 *   - pas de lien ligne × matériel dont l'écartement contredit celui de la ligne, sauf source explicite.
 */
class DataInvariants
{
    /** Exemples affichés par invariant violé. */
    public const EXAMPLES = 5;

    /** Écart toléré (jours) entre l'ouverture d'une gare et celle de sa ligne : inauguration la veille de l'ouverture au public. */
    public const STATION_OPENED_TOLERANCE_DAYS = 3;

    /** Rayon (m) autour d'une gare « active » où une voie en service est attendue. */
    public const STATION_TRACK_RADIUS_M = 150;

    /** Rayon maximal pour une position de précision connue et mauvaise (= rayon d'isolement de vff:build-station-eras). */
    public const STATION_ISOLATION_RADIUS_M = StationLineCoverage::ISOLATION_RADIUS_M;

    /** Perte de km (part des km en service de l'ère précédente) qui exige un événement. */
    public const MAX_LOSS_RATIO = 0.5;

    /** En deçà de cette perte (km), pas de signalement (bruit de découpage). */
    public const MIN_LOSS_KM = 1.0;

    /** Recouvrement (part du plus court des deux tronçons) au-delà duquel deux tronçons sont en double. */
    public const DUPLICATE_OVERLAP = 0.9;

    /** Tolérance (m) du recouvrement de deux tronçons en double. */
    public const DUPLICATE_TOL_M = 5;

    /**
     * Tronçon plus court que ça (m) : raccord d'aiguillage ou recollage de deux tracés, pas un doublon
     * (décision du 2026-10-20 : 1 177 des 1 557 paires de la passe nationale 2 avaient un tronçon de
     * moins de 10 m, posé sur la voie qu'il raccorde ; invisibles sur la carte).
     */
    public const DUPLICATE_MIN_M = 10;

    /** Tolérance (m) : un tronçon déposé est sur un tronçon déposé de l'ère précédente (même tracé de référence). */
    public const SAME_PORTION_TOL_M = 2;

    /**
     * Même portion déposée deux ères de suite : au moins la moitié du tronçon, et au moins
     * SAME_PORTION_MIN_M, est sur un tronçon déposé de l'ère précédente. Un morceau plus court
     * accolé au bout d'une portion déposée avant lui (raccord entre deux portions de
     * chronologies différentes) n'est pas la même portion.
     */
    public const SAME_PORTION_MIN_M = 10;

    /**
     * Doublon décalé d'une autre ligne : portion peu précise du tracé de référence (archeo, OHM,
     * SNCF… non recalée, ou raccord) à plus de TrackPriority::TOLERANCE_M (non masquée par
     * vff:build-shared-track) et au plus à junction_m (TraceConflation : tronc commun) de la voie
     * principale précise d'une AUTRE ligne, sur au moins OFFSET_DUPLICATE_MIN_M. Échantillons tous
     * les OFFSET_DUPLICATE_STEP_M.
     */
    public const OFFSET_DUPLICATE_MIN_M = 200;

    public const OFFSET_DUPLICATE_STEP_M = 50;

    /**
     * Trous du tracé de référence : extrémités libres (rien de la ligne à 0,5 m) face à face (cap à moins
     * de HOLE_FACE_DEG de l'autre extrémité), à HOLE_MIN_M - HOLE_MAX_M, et voie précise BD TOPO / OSM
     * (rattachée ou non) le long d'au moins 90 % du trou à HOLE_COVER_M près : JunctionRepair les comble.
     */
    public const HOLE_MIN_M = 2;

    public const HOLE_MAX_M = 80;

    public const HOLE_FACE_DEG = 45;

    public const HOLE_COVER_M = 3;

    /**
     * Jonction dessinée qui ne touche pas (exigence « 0 m » : sur la carte, une branche finit SUR la ligne
     * qu'elle rejoint) : bout libre du DESSIN d'une ligne (géométrie des tuiles, line_segment_covers compris)
     * à plus de JUNCTION_GAP_M du dessin de la ligne la plus proche, quand c'est une jonction réelle :
     * suite masquée sous une autre ligne (vff:build-shared-track) ou bout posé sur la partie masquée d'une autre
     * ligne, dessin à au plus BuildSharedTrack::CORRIDOR_M (relais : la voie continue, dessinée par la ligne qui
     * la masque), ou arrivée à au plus
     * JUNCTION_ARRIVAL_M (aucune voie n'est à 1,5 m d'une autre sans la rejoindre) ou, à au plus
     * JUNCTION_REACH_M, cap vers elle (prolongement de JUNCTION_RAY_M à moins de JUNCTION_ARRIVAL_M).
     * Heurtoirs et terminus le long d'une autre ligne ne sont pas des jonctions. Mêmes règles que
     * BuildSharedTrack::contact, qui les corrige, et que jonctions-detecteur/scripts/check_join_0m.sql.
     */
    public const JUNCTION_GAP_M = 0.5;

    public const JUNCTION_ARRIVAL_M = 1.5;

    public const JUNCTION_REACH_M = 15;

    public const JUNCTION_RAY_M = 60;

    /** Suite masquée : ligne cherchée jusqu'à JUNCTION_MASKED_SEARCH_M (au-delà : « aucune ligne dessinée »). */
    public const JUNCTION_MASKED_SEARCH_M = 50;

    /** Année de l'ère courante (état actuel du réseau, mis à jour jusqu'en 2030). */
    public const CURRENT_ERA_YEAR = 2030;

    /** Part maximale des km en service du RFN (code à 6 chiffres, hors patrimoine) sans vitesse, à l'ère courante. */
    public const MAX_MISSING_SPEED_RATIO = 0.2;

    private const ACTIVE = "('in_service', 'single_track')";

    private const KM = 'COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) / 1000.0';

    /** Clé → libellé (unité des cas entre parenthèses). */
    public const INVARIANTS = [
        'eras.current' => 'Une seule ère courante, l\'ère '.self::CURRENT_ERA_YEAR.' (ères)',
        'segments.current_freight_on_passenger_code' => 'Ère courante : tronçon « fret » qui porte une gare voyageurs SNCF desservie de son code (lignes)',
        'segments.current_closed_on_served_station' => 'Ère courante : portion fermée qui porte une gare voyageurs desservie, sur un tronçon SNCF « Exploitée » (lignes)',
        'segments.lgv_before_1981' => 'LGV ou raccordement LGV en service avant 1981 (lignes)',
        'segments.in_service_before_first_opening' => 'Tronçon en service avant l\'ère de la première ouverture datée de sa ligne (lignes)',
        'stations.active_without_track' => 'Gare « active » sans voie en service à moins de '.self::STATION_TRACK_RADIUS_M.' m (gare × ère)',
        'stations.closed_at_outside_eras' => 'Gare affirmée ouverte sur la frise après sa date de fermeture retenue (stations.closed_at) (gare × ère)',
        'segments.dismantled_two_eras' => 'Portion « déposée » deux ères de suite (ligne × ère)',
        'network.km_out_of_range' => 'Km en service hors des fourchettes historiques de ConsistencyChecker::HISTORICAL_KM (ère × réseau)',
        'lines.km_drop_without_event' => 'Ligne qui perd plus de 50 % de ses km d\'une ère à l\'autre sans fermeture ni dépose (ligne × ère)',
        'segments.duplicates' => 'Tronçons en double : même ligne, même ère, recouvrement de plus de 90 % (paires ; raccords de moins de 10 m exclus)',
        'lines.junction_offset_duplicates' => 'Tracé peu précis qui double à 10-30 m la voie d\'une autre ligne sur au moins 200 m, non signalé comme parallèle distinct (portions)',
        'lines.reference_holes' => 'Trou du tracé de référence : deux extrémités libres d\'une même ligne face à face, à 2-80 m, sur une voie précise qui traverse le trou (pont, passage à niveau) (paires)',
        'segments.heritage_status' => 'Patrimoine incohérent : vélorail en service, train touristique ou musée fermé, type sans drapeau (tronçons)',
        'segments.inactive_with_traffic' => 'Tronçon fermé ou déposé avec un trafic renseigné (tronçons)',
        'lines.junction_gap' => 'Jonction dessinée qui ne touche pas : bout de branche à plus de 0,5 m (JUNCTION_GAP_M) du dessin de la ligne qu\'il rejoint (suite masquée sous elle, ou arrivée à moins de 1,5 m ou cap vers elle) (bout × ère)',
        'texts.long_dash' => 'Texte affiché avec un tiret long (U+2014) ou un double tiret (textes)',
        // Ajout « petits-correctifs » (2026-10-19), isolé en fin de liste.
        'segments.current_heritage_on_served_station' => 'Ère courante : tronçon patrimoine (associatif) qui porte une gare voyageurs SNCF desservie (lignes)',
        // Ajout « enrichissements » (2026-10-20), isolé en fin de liste : données posées après le moteur
        // (vff:enrich-era-segments, vff:import-sncf-vmax), perdues quand une ligne est régénérée seule.
        'segments.current_lgv_not_electrified' => 'Ère courante : LGV en service non électrifiée (lignes)',
        'segments.current_missing_speed' => 'Ère courante : part des km en service du RFN sans vitesse maximale au-dessus de '.(self::MAX_MISSING_SPEED_RATIO * 100).' % (ère)',
        // Ajout « fiche-contenus » (2026-10-20), isolé en fin de liste : fiche gare et fiche ligne ne se
        // contredisent pas (« desservie de … à … », dates de la gare, arrêts de la carte).
        'stations.line_service_outside_station_life' => 'Ligne « desservie » avant l\'ouverture ou après la fermeture retenue de la gare, fin avant début, ou arrêt de la carte hors de cette période (gare × ligne)',
        'stations.opened_before_lines' => 'Gare ouverte (date au jour, sourcée) plus de '.self::STATION_OPENED_TOLERANCE_DAYS.' jours avant l\'ouverture au jour de toutes ses lignes à son PK (section résolue, hors terminus nommé et PK projeté) (gares)',
        // Ajout « trafic-2030 » (2026-10-20), isolé en fin de liste : gare voyageurs rattachée à un autre
        // code dans liste-des-gares (944000 Cannes-la Bocca - Grasse, « fret » en 2030).
        'segments.current_freight_on_reopened_or_station' => 'Ère courante : tronçon « fret » rouvert aux voyageurs (événement daté, sans fermeture après) ou portant une gare de voyageurs SNCF desservie à moins de '.PassengerPortions::ADOPT_M.' m, quel que soit son code (lignes)',
        // Ajout « materiel-ecartement » (2026-11-19), isolé en fin de liste : X 73500 (voie normale) relié
        // aux lignes métriques du Blanc-Argent par une phrase de comparaison.
        'line_materials.gauge_conflict' => 'Lien ligne × matériel dont l\'écartement couvre moins de '.(MaterialGaugeRule::MIN_SHARE * 100).' % de la longueur de la ligne (toutes ères), sans source explicite ni exception relue (liens)',
        // Ajout « materiel-audit » (2026-11-19), isolé en fin de liste : Z 890 (896000) en « source directe »
        // sur la seule foi d'un contrat d'achat.
        'line_materials.doubtful_confidence' => 'Lien importé affiché sûr (high ou medium) alors que toutes ses preuves portent un doute (commande, essai, circulation exceptionnelle…) ou viennent d\'un article commun à plusieurs lignes (liens)',
        // Ajout « materiel-electrification » (2026-11-19), isolé en fin de liste : BB 9300 et BB 7200 (1 500 V
        // continu) reliées à Valence - Moirans (25 kV) par une phrase sur un changement de machine à Toulouse.
        'line_materials.traction_conflict' => 'Lien ligne × série électrique dont aucun système ne couvre '.(MaterialTractionRule::MIN_SHARE * 100).' % de la longueur de la ligne à une ère au moins de son service, sans phrase qui nomme la tension ni exception relue (liens)',
    ];

    /** @param  list<string>|null  $only  clés (ou préfixes, ex. « segments ») à vérifier */
    public function __construct(private ?array $only = null) {}

    /**
     * Invariant dont la requête échoue : compté violé (1 cas), avec le message dans `error`.
     * $onResult reçoit chaque résultat dès qu'il est calculé (affichage au fil de l'eau).
     *
     * @return list<array{key: string, label: string, count: int, examples: list<string>, seconds: float, error?: string}>
     */
    public function run(?callable $onResult = null): array
    {
        $results = [];
        // Garde-fou : transaction en lecture seule (toute écriture échouerait), annulée à la fin.
        // Dans une transaction déjà ouverte (tests), on ne peut plus changer son mode.
        $own = DB::transactionLevel() === 0;
        if ($own) {
            DB::beginTransaction();
            DB::statement('SET TRANSACTION READ ONLY');
        }
        // Pas de requête parallèle : le conteneur PostgreSQL n'a que 64 Mo de /dev/shm et les
        // jointures spatiales sur tous les tronçons y échouent (« could not resize shared memory »).
        DB::statement('SET LOCAL max_parallel_workers_per_gather = 0');
        try {
            foreach (self::INVARIANTS as $key => $label) {
                if (! $this->selected($key)) {
                    continue;
                }
                $started = microtime(true);
                $method = lcfirst(str_replace(' ', '', ucwords(str_replace(['.', '_'], ' ', $key))));
                $error = null;
                // Point de sauvegarde par invariant : une requête en erreur n'interrompt pas les suivantes.
                DB::beginTransaction();
                try {
                    ['count' => $count, 'examples' => $examples] = $this->{$method}();
                } catch (QueryException $e) {
                    ['count' => $count, 'examples' => $examples] = ['count' => 1, 'examples' => []];
                    $error = mb_strimwidth($e->getPrevious()?->getMessage() ?? $e->getMessage(), 0, 300, '…');
                } finally {
                    DB::rollBack();
                }
                $results[] = ['key' => $key, 'label' => $label, 'count' => $count, 'examples' => $examples,
                    'seconds' => round(microtime(true) - $started, 1)] + ($error !== null ? ['error' => $error] : []);
                if ($onResult) {
                    $onResult(end($results));
                }
            }
        } finally {
            if ($own) {
                DB::rollBack();
            }
        }

        return $results;
    }

    private function selected(string $key): bool
    {
        if (! $this->only) {
            return true;
        }
        foreach ($this->only as $wanted) {
            if ($key === $wanted || str_starts_with($key, rtrim($wanted, '.').'.')) {
                return true;
            }
        }

        return false;
    }

    // ─── Invariants ──────────────────────────────────────────────────────

    /** @return array{count: int, examples: list<string>} */
    private function erasCurrent(): array
    {
        $current = DB::select('SELECT year FROM eras WHERE is_current ORDER BY year');
        $years = array_map(fn ($r) => (int) $r->year, $current);
        if ($years === [self::CURRENT_ERA_YEAR]) {
            return ['count' => 0, 'examples' => []];
        }

        return ['count' => max(1, count($years)), 'examples' => [
            $years ? 'ères courantes : '.implode(', ', $years) : 'aucune ère courante',
        ]];
    }

    /**
     * Portion par portion (App\Services\PassengerPortions) : une gare voyageurs desservie
     * (drapeau liste-des-gares, sur un tronçon SNCF exploité, sans fermeture aux voyageurs
     * récente) à l'intérieur d'un tronçon « fret » de son code (à plus de MIN_PIECE_M de ses
     * bouts : la gare qui borne une portion voyageurs est au bout du tronçon fret voisin).
     *
     * @return array{count: int, examples: list<string>}
     */
    private function segmentsCurrentFreightOnPassengerCode(): array
    {
        $km = self::KM;
        $p = PassengerPortions::stationPoint('g');
        $served = PassengerPortions::servedStationSql('g');
        $snap = PassengerPortions::SNAP_M;
        $end = PassengerPortions::MIN_PIECE_M;

        return $this->violations(<<<SQL
            SELECT h.id, h.code, h.name, SUM(h.km) AS km, string_agg(h.gares, ', ') AS gares, SUM(h.km) AS sort
            FROM (
                SELECT l.id, l.code, l.name, ls.id AS segment_id, MAX({$km}) AS km, string_agg(DISTINCT g.libelle, ', ') AS gares
                FROM line_segments ls
                JOIN eras e ON e.id = ls.era_id AND e.is_current
                JOIN lines l ON l.id = ls.line_id
                JOIN sncf_gares g ON g.code_ligne = l.code AND g.voyageurs AND g.lat IS NOT NULL
                WHERE ls.traffic_type = 'freight' AND ls.status IN {$this->active()}
                  AND ls.geom && ST_Expand({$p}, 0.0035) AND ST_DWithin(ls.geom::geography, ({$p})::geography, {$snap})
                  AND ST_LineLocatePoint(ls.geom, {$p}) * ST_Length(ls.geom::geography) > {$end}
                  AND (1 - ST_LineLocatePoint(ls.geom, {$p})) * ST_Length(ls.geom::geography) > {$end}
                  AND {$served}
                GROUP BY l.id, l.code, l.name, ls.id
            ) h
            GROUP BY h.id, h.code, h.name
            SQL, fn ($r) => sprintf('%s %s : %s km en « fret » sur la gare voyageurs %s', $r->code, $r->name, $this->km($r->km), $r->gares));
    }

    /**
     * Garde-fou des fausses fermetures récentes (EraTimeline::isRecentSourcedClosure fait passer
     * une fermeture récente et sourcée devant le statut SNCF) : tronçon fermé, voie en place, de l'ère
     * courante (une voie déposée près d'une gare est un ancien tracé : Château-du-Loir) qui porte (à l'intérieur, comme segmentsCurrentFreightOnPassengerCode) une gare
     * voyageurs desservie de son code, sur un tronçon SNCF « Exploitée », sans autre tronçon en
     * service de la même ligne pour la desservir (ancien tracé parallèle).
     *
     * @return array{count: int, examples: list<string>}
     */
    private function segmentsCurrentClosedOnServedStation(): array
    {
        $km = self::KM;
        $p = PassengerPortions::stationPoint('g');
        $served = PassengerPortions::servedStationSql('g');
        $snap = PassengerPortions::SNAP_M;
        $end = PassengerPortions::MIN_PIECE_M;
        $near = PassengerPortions::STATION_EXPLOITED_M;

        return $this->violations(<<<SQL
            SELECT h.id, h.code, h.name, SUM(h.km) AS km, string_agg(h.gares, ', ') AS gares, SUM(h.km) AS sort
            FROM (
                SELECT l.id, l.code, l.name, ls.id AS segment_id, MAX({$km}) AS km, string_agg(DISTINCT g.libelle, ', ') AS gares
                FROM line_segments ls
                JOIN eras e ON e.id = ls.era_id AND e.is_current
                JOIN lines l ON l.id = ls.line_id
                JOIN sncf_gares g ON g.code_ligne = l.code AND g.voyageurs AND g.lat IS NOT NULL
                WHERE ls.status = 'abandoned'
                  AND ls.geom && ST_Expand({$p}, 0.0035) AND ST_DWithin(ls.geom::geography, ({$p})::geography, {$snap})
                  AND ST_LineLocatePoint(ls.geom, {$p}) * ST_Length(ls.geom::geography) > {$end}
                  AND (1 - ST_LineLocatePoint(ls.geom, {$p})) * ST_Length(ls.geom::geography) > {$end}
                  AND EXISTS (SELECT 1 FROM sncf_line_geometries xg WHERE xg.code_ligne = l.code AND xg.libelle ILIKE 'Exploit%'
                              AND xg.geom && ST_Expand({$p}, 0.0035) AND ST_DWithin(xg.geom::geography, ({$p})::geography, {$near}))
                  AND NOT EXISTS (SELECT 1 FROM line_segments la WHERE la.line_id = ls.line_id AND la.era_id = ls.era_id
                                  AND la.status IN {$this->active()}
                                  AND la.geom && ST_Expand({$p}, 0.0035) AND ST_DWithin(la.geom::geography, ({$p})::geography, {$snap}))
                  AND {$served}
                GROUP BY l.id, l.code, l.name, ls.id
            ) h
            GROUP BY h.id, h.code, h.name
            SQL, fn ($r) => sprintf('%s %s : %s km fermés sur la gare voyageurs %s (tronçon SNCF exploité)', $r->code, $r->name, $this->km($r->km), $r->gares));
    }

    /** @return array{count: int, examples: list<string>} */
    private function segmentsLgvBefore1981(): array
    {
        $km = self::KM;
        $lgv = Lgv::sql('l', 'ls');

        return $this->violations(<<<SQL
            SELECT l.id, l.code, l.name, MIN(e.year) AS first_era, string_agg(DISTINCT e.year::text, ', ') AS eras, SUM({$km}) AS km,
                   SUM({$km}) AS sort
            FROM line_segments ls
            JOIN eras e ON e.id = ls.era_id AND e.year < ?
            JOIN lines l ON l.id = ls.line_id
            WHERE ls.status IN {$this->active()} AND {$lgv}
            GROUP BY l.id, l.code, l.name
            SQL, fn ($r) => sprintf('%s %s : en service en %s (%s km)', $r->code ?? '-', $r->name, $r->eras, $this->km($r->km)),
            [EraTimeline::HIGH_SPEED_FIRST_YEAR]);
    }

    /** @return array{count: int, examples: list<string>} */
    private function segmentsInServiceBeforeFirstOpening(): array
    {
        $km = self::KM;

        return $this->violations(<<<SQL
            WITH first AS (
                SELECT line_id, MIN(event_date) AS d FROM line_events
                WHERE type LIKE 'opening%' OR type LIKE 'reopening%' OR type = 'heritage_start'
                GROUP BY line_id
            )
            SELECT l.id, l.code, l.name, f.d::text AS first_opening, MIN(e.year) AS first_era, SUM({$km}) AS km,
                   EXTRACT(YEAR FROM f.d) - MIN(e.year) AS sort
            FROM line_segments ls
            JOIN eras e ON e.id = ls.era_id
            JOIN first f ON f.line_id = ls.line_id
            JOIN lines l ON l.id = ls.line_id
            WHERE ls.status IN {$this->active()}
              AND e.year < (EXTRACT(YEAR FROM f.d)::int / 10) * 10
            GROUP BY l.id, l.code, l.name, f.d
            SQL, fn ($r) => sprintf('%s %s : en service dès %d, première ouverture datée le %s (%s km avant)',
            $r->code ?? '-', $r->name, $r->first_era, $r->first_opening, $this->km($r->km)));
    }

    /** @return array{count: int, examples: list<string>} */
    private function stationsActiveWithoutTrack(): array
    {
        $radius = self::STATION_TRACK_RADIUS_M;
        // Rayon relâché pour les seules positions de précision CONNUE et mauvaise (gares-relecture,
        // 2026-10-20) : position déduite d'un PK ou d'un schéma de ligne (provenance.geom_precision_m),
        // au plus STATION_ISOLATION_RADIUS_M (au-delà, vff:build-station-eras ne montre jamais la gare
        // ouverte : StationLineCoverage::ISOLATION_RADIUS_M).
        $max = self::STATION_ISOLATION_RADIUS_M;
        $r = "LEAST({$max}, GREATEST({$radius}, COALESCE((s.provenance->>'geom_precision_m')::int, 0)))";
        // Pré-filtre && en degrés (index GiST) : 0,006° ≥ 300 m en latitude comme en longitude en France.
        $deg = 0.003 * max(1, $max / 150);

        // LATERAL … LIMIT 1 : une recherche par l'index GiST pour chaque gare (un NOT EXISTS donne
        // une anti-jointure par ère, qui compare chaque gare à tous les tronçons de l'ère).
        return $this->violations(<<<SQL
            SELECT s.id, s.name, e.year, -e.year AS sort
            FROM station_eras se
            JOIN eras e ON e.id = se.era_id
            JOIN stations s ON s.id = se.station_id
            LEFT JOIN LATERAL (
                SELECT 1 AS hit FROM line_segments ls
                WHERE ls.geom && ST_Expand(s.geom, {$deg})
                  AND ls.era_id = se.era_id AND ls.status IN {$this->active()}
                  AND ST_DWithin(ls.geom::geography, s.geom::geography, {$r})
                LIMIT 1
            ) near ON true
            WHERE se.status = 'active' AND NOT se.uncertain AND near.hit IS NULL
            SQL, fn ($r) => sprintf('%d : %s (gare #%d)', $r->year, $r->name, $r->id), order: 'sort, name');
    }

    /**
     * Une seule date de fermeture par gare (StationTimeline::resolveClosure, agent gares-fiche) :
     * la carte, la fiche gare et la fiche ligne lisent stations.closed_at et station_eras ; une
     * gare ne doit jamais être affirmée ouverte à une ère postérieure à sa fermeture.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function stationsClosedAtOutsideEras(): array
    {
        return $this->violations(<<<'SQL'
            SELECT s.id, s.name, e.year, s.closed_at, -e.year AS sort
            FROM station_eras se
            JOIN eras e ON e.id = se.era_id
            JOIN stations s ON s.id = se.station_id
            WHERE s.closed_at IS NOT NULL AND se.status IN ('active', 'tourist', 'no_commercial') AND NOT se.uncertain
              AND ((NOT e.is_current AND e.year >= EXTRACT(YEAR FROM s.closed_at)) OR (e.is_current AND s.closed_at <= CURRENT_DATE))
            SQL, fn ($r) => sprintf('%d : %s (gare #%d, fermée le %s)', $r->year, $r->name, $r->id, $r->closed_at), order: 'sort, name');
    }

    /** @return array{count: int, examples: list<string>} */
    private function segmentsDismantledTwoEras(): array
    {
        $km = self::KM;
        $tol = self::SAME_PORTION_TOL_M;
        $min = self::SAME_PORTION_MIN_M;

        return $this->violations(<<<SQL
            WITH e AS (SELECT id, year, LAG(id) OVER (ORDER BY year) AS prev_id, LAG(year) OVER (ORDER BY year) AS prev_year FROM eras)
            SELECT l.id, l.code, l.name, e.prev_year, e.year, SUM({$km}) AS km, SUM({$km}) AS sort
            FROM line_segments ls
            JOIN e ON e.id = ls.era_id
            JOIN lines l ON l.id = ls.line_id
            CROSS JOIN LATERAL (
                SELECT ST_Length(ST_Intersection(ST_Transform(ls.geom, 2154), ST_Buffer(ST_Transform(ST_Collect(b.geom), 2154), {$tol}))) AS m
                FROM line_segments b
                WHERE b.era_id = e.prev_id AND b.line_id = ls.line_id AND b.status = 'dismantled'
                  AND b.geom && ST_Expand(ls.geom, 0.0001)
            ) ov
            WHERE ls.status = 'dismantled'
              AND ov.m >= GREATEST({$min}, 0.5 * ST_Length(ST_Transform(ls.geom, 2154)))
            GROUP BY l.id, l.code, l.name, e.prev_year, e.year
            SQL, fn ($r) => sprintf('%s %s : déposée en %d et en %d (%s km)', $r->code ?? '-', $r->name, $r->prev_year, $r->year, $this->km($r->km)));
    }

    /** @return array{count: int, examples: list<string>} */
    private function networkKmOutOfRange(): array
    {
        $km = self::KM;
        $have = [];
        foreach (DB::select(<<<SQL
            SELECT e.year, l.network, SUM({$km}) AS km
            FROM line_segments ls JOIN eras e ON e.id = ls.era_id JOIN lines l ON l.id = ls.line_id
            WHERE ls.status IN {$this->active()} AND l.network IS NOT NULL
            GROUP BY e.year, l.network
            SQL) as $r) {
            $have[(int) $r->year][$r->network] = (float) $r->km;
        }
        $years = array_map(fn ($r) => (int) $r->year, DB::select('SELECT year FROM eras ORDER BY year'));

        $out = [];
        foreach ($years as $year) {
            foreach (ConsistencyChecker::HISTORICAL_KM as $network => $refs) {
                // Réseau absent de l'ère : ère non calculée (ou catégorie non renseignée), pas une mesure.
                if (! isset($refs[$year]) || ! isset($have[$year][$network])) {
                    continue;
                }
                if ($why = self::kmOutOfRange($have[$year][$network], $refs[$year])) {
                    $out[] = "{$year} {$network} : {$why}";
                }
            }
        }

        return ['count' => count($out), 'examples' => array_slice($out, 0, self::EXAMPLES)];
    }

    /**
     * Km en service hors de la fourchette historique : écart relatif de plus de
     * COMPLETENESS_TOLERANCE ET d'au moins COMPLETENESS_MIN_GAP_KM (mêmes seuils que
     * vff:check-consistency), ou plafond dépassé pour un réseau quasi disparu.
     *
     * @param  array{km?: int, max?: int}  $ref
     */
    public static function kmOutOfRange(float $have, array $ref): ?string
    {
        if (isset($ref['km'])) {
            $gap = $have - $ref['km'];
            $ratio = $ref['km'] > 0 ? $have / $ref['km'] : 0;
            if (abs($ratio - 1) > ConsistencyChecker::COMPLETENESS_TOLERANCE && abs($gap) >= ConsistencyChecker::COMPLETENESS_MIN_GAP_KM) {
                return sprintf('%s km, attendu %s km ± %d %% (%s%s km)', self::fmt($have), self::fmt($ref['km']),
                    ConsistencyChecker::COMPLETENESS_TOLERANCE * 100, $gap > 0 ? '+' : '-', self::fmt(abs($gap)));
            }

            return null;
        }
        if (isset($ref['max']) && $have > $ref['max']) {
            return sprintf('%s km, plafond %s km', self::fmt($have), self::fmt($ref['max']));
        }

        return null;
    }

    /** @return array{count: int, examples: list<string>} */
    private function linesKmDropWithoutEvent(): array
    {
        $km = self::KM;

        return $this->violations(<<<SQL
            WITH e AS (SELECT id, year, LAG(id) OVER (ORDER BY year) AS prev_id, LAG(year) OVER (ORDER BY year) AS prev_year FROM eras),
            -- Après : une portion transférée en voie de service roule encore (desserte d'embranchement,
            -- manœuvres) : ce n'est pas une perte de km exploités (812000 à L'Ardoise).
            km AS (
                SELECT ls.line_id, ls.era_id, SUM({$km}) FILTER (WHERE ls.status IN {$this->active()}) AS km,
                       SUM({$km}) AS kept_km
                FROM line_segments ls
                WHERE ls.status IN {$this->active()} OR ls.status = 'service_track' GROUP BY ls.line_id, ls.era_id
            )
            SELECT l.id, l.code, l.name, e.prev_year, e.year, p.km AS before_km, COALESCE(c.kept_km, 0) AS after_km,
                   p.km - COALESCE(c.kept_km, 0) AS sort
            FROM km p
            JOIN e ON e.prev_id = p.era_id
            LEFT JOIN km c ON c.line_id = p.line_id AND c.era_id = e.id
            JOIN lines l ON l.id = p.line_id
            WHERE p.km > 0 AND COALESCE(c.kept_km, 0) < p.km * ? AND p.km - COALESCE(c.kept_km, 0) >= ?
              AND NOT EXISTS (
                  SELECT 1 FROM line_events ev
                  WHERE ev.line_id = p.line_id
                    AND (ev.type LIKE 'closure%' OR ev.type IN ('declassification', 'dismantling', 'conversion', 'heritage_end')
                         -- Reprise associative DATÉE : fin du trafic commercial (EraTimeline::heritageEndsCommercialTraffic,
                         -- 689000 : Cyclorail du Sancerrois depuis 2004). Pas une simple attestation (« date de début
                         -- non trouvée ») : elle ne date rien.
                         OR (ev.type = 'heritage_start' AND ev.description NOT LIKE '%date de début non trouvée%'))
                    AND ev.event_date > make_date(e.prev_year, 12, 31) AND ev.event_date <= make_date(e.year, 12, 31)
              )
            SQL, fn ($r) => sprintf('%s %s : %s km en %d, %s km en %d, sans fermeture ni dépose entre les deux',
            $r->code ?? '-', $r->name, $this->km($r->before_km), $r->prev_year, $this->km($r->after_km), $r->year),
            [1 - self::MAX_LOSS_RATIO, self::MIN_LOSS_KM]);
    }

    /** @return array{count: int, examples: list<string>} */
    private function segmentsDuplicates(): array
    {
        $tol = self::DUPLICATE_TOL_M;
        $min = self::DUPLICATE_MIN_M;
        // Pré-filtre : le milieu du plus court des deux est sur l'autre (≈ 5 m en degrés).
        $deg = $tol / 100000;

        return $this->violations(<<<SQL
            SELECT a.id AS a_id, b.id AS b_id, l.code, l.name, e.year,
                   LEAST(ST_Length(a.geom::geography), ST_Length(b.geom::geography)) / 1000.0 AS km,
                   LEAST(ST_Length(a.geom::geography), ST_Length(b.geom::geography)) AS sort
            FROM line_segments a
            JOIN line_segments b ON b.line_id = a.line_id AND b.era_id = a.era_id AND b.id > a.id AND b.geom && a.geom
            JOIN lines l ON l.id = a.line_id
            JOIN eras e ON e.id = a.era_id
            WHERE (ST_DWithin(ST_LineInterpolatePoint(a.geom, 0.5), b.geom, {$deg}) OR ST_DWithin(ST_LineInterpolatePoint(b.geom, 0.5), a.geom, {$deg}))
              AND LEAST(ST_Length(ST_Transform(a.geom, 2154)), ST_Length(ST_Transform(b.geom, 2154))) >= {$min}
              AND ST_Length(ST_Intersection(ST_Transform(a.geom, 2154), ST_Buffer(ST_Transform(b.geom, 2154), {$tol})))
                  > ? * LEAST(ST_Length(ST_Transform(a.geom, 2154)), ST_Length(ST_Transform(b.geom, 2154)))
            SQL, fn ($r) => sprintf('%d : %s %s, tronçons #%d et #%d (%s km en double)', $r->year, $r->code ?? '-', $r->name, $r->a_id, $r->b_id,
            $this->km($r->km)), [self::DUPLICATE_OVERLAP]);
    }

    /**
     * Tracé de référence (donc toutes les ères) : séries d'échantillons consécutifs d'une portion
     * peu précise à (TOLERANCE_M, junction_m] de la même voie principale précise rattachée à une
     * autre ligne (BD TOPO, OSM, contribution ; ni voie de service ni métro / tram). Les parallèles
     * sans convergence gardés et signalés (line_reference_duplicates « kept_parallel ») sont admis, comme
     * les voies que le garde-fou de junctionTrunk interdit de partager (écartements différents ; ligne
     * secondaire ou militaire d'écartement inconnu le long d'une ligne principale : voie distincte).
     *
     * @return array{count: int, examples: list<string>}
     */
    private function linesJunctionOffsetDuplicates(): array
    {
        $min = TrackPriority::TOLERANCE_M;
        $max = TraceConflation::PROFILES['archeoferroviaire']['junction_m'];
        $step = self::OFFSET_DUPLICATE_STEP_M;
        $samples = (int) ceil(self::OFFSET_DUPLICATE_MIN_M / $step) + 1;
        // Pré-filtre && en degrés : 0,0005° ≥ 37 m en longitude et 55 m en latitude en France.
        $deg = 0.0005 * max(1, $max / 30);
        $narrow = "'".implode("','", TraceConflation::JUNCTION_NARROW_NETWORKS)."'";

        return $this->violations(<<<SQL
            WITH c AS MATERIALIZED (
                SELECT p.line_id, d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d
                WHERE ((p.matched_source IS NULL AND p.source NOT IN ('contrib', 'bdtopo', 'osm', 'ign-historique')) OR p.matched_source = 'connector')
                  AND ST_Length(d.geom::geography) >= {$step} * ({$samples} - 1)
            ),
            s AS MATERIALIZED (
                SELECT c.line_id, c.g, x.path[1] AS j, x.geom AS pt
                FROM c, LATERAL ST_DumpPoints(ST_LineInterpolatePoints(c.g, LEAST(1, {$step} / ST_Length(c.g::geography)), true)) x
            ),
            nb AS (
                SELECT s.line_id, s.g, s.j, s.pt, n.oline, n.d
                FROM s CROSS JOIN LATERAL (
                    SELECT st.line_id AS oline, ST_Distance(st.geom::geography, s.pt::geography) AS d
                    FROM source_traces st
                    WHERE st.geom && ST_Expand(s.pt, {$deg}) AND st.source IN ('bdtopo', 'osm', 'contrib')
                      AND st.line_id IS NOT NULL AND st.line_id <> s.line_id
                      AND st.properties->>'service' IS NULL AND COALESCE(st.layer, '') NOT IN ('Voie de service', 'subway', 'tram')
                    ORDER BY 2 LIMIT 1
                ) n
                WHERE n.d > {$min} AND n.d <= {$max}
            ),
            r AS (SELECT nb.*, j - row_number() OVER (PARTITION BY line_id, g, oline ORDER BY j) AS grp FROM nb),
            runs AS (
                SELECT line_id, oline, COUNT(*) AS k, percentile_cont(0.5) WITHIN GROUP (ORDER BY d) AS gap,
                       (array_agg(pt ORDER BY j))[COUNT(*) / 2 + 1] AS mid
                FROM r GROUP BY line_id, g, oline, grp HAVING COUNT(*) >= {$samples}
            )
            SELECT a.code, a.name, b.code AS ocode, b.name AS oname, (runs.k - 1) * {$step} AS m, runs.gap,
                   ST_Y(runs.mid) AS lat, ST_X(runs.mid) AS lon, (runs.k - 1) * {$step} AS sort
            FROM runs JOIN lines a ON a.id = runs.line_id JOIN lines b ON b.id = runs.oline
            WHERE NOT EXISTS (SELECT 1 FROM line_reference_duplicates x WHERE x.line_id = runs.line_id AND x.action = 'kept_parallel'
                              AND ST_DWithin(x.geom, runs.mid, 0.00002))
              -- Partage de voie physiquement impossible (garde-fou de TraceConflation::junctionTrunk) : écartements
              -- connus et différents ; à défaut, ligne secondaire ou militaire le long d'une ligne principale.
              AND NOT (a.gauge_mm_default IS NOT NULL AND b.gauge_mm_default IS NOT NULL AND a.gauge_mm_default <> b.gauge_mm_default)
              AND NOT COALESCE((a.gauge_mm_default IS NULL OR b.gauge_mm_default IS NULL) AND a.network IN ({$narrow}) AND b.network = 'principal', FALSE)
            SQL, fn ($r) => sprintf('%s %s : %d m à %s m de %s %s (%.5f, %.5f)', $r->code ?? '-', $r->name, $r->m,
            number_format((float) $r->gap, 0), $r->ocode ?? '-', $r->oname, $r->lat, $r->lon));
    }

    /** @return array{count: int, examples: list<string>} */
    private function linesReferenceHoles(): array
    {
        $face = self::HOLE_FACE_DEG;

        return $this->violations(<<<SQL
            WITH s AS MATERIALIZED (
                SELECT p.id, p.line_id, d.path[1] AS sub, d.geom AS g4, ST_Transform(d.geom, 2154) AS g
                FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d
            ),
            e AS MATERIALIZED (
                SELECT s.line_id, x.p,
                       ST_Azimuth(ST_LineInterpolatePoint(s.g, CASE WHEN x.k = 0 THEN LEAST(0.5, 15 / ST_Length(s.g)) ELSE GREATEST(0.5, 1 - 15 / ST_Length(s.g)) END), x.p) AS az
                FROM s, LATERAL (VALUES (0, ST_StartPoint(s.g), ST_StartPoint(s.g4)), (1, ST_EndPoint(s.g), ST_EndPoint(s.g4))) x(k, p, p4)
                WHERE ST_Length(s.g) > 1 AND NOT ST_DWithin(ST_StartPoint(s.g), ST_EndPoint(s.g), 0.5)
                  AND NOT EXISTS (
                      SELECT 1 FROM line_reference_parts o, LATERAL ST_Dump(o.geom) od
                      WHERE o.line_id = s.line_id AND o.geom && ST_Expand(x.p4, 0.00002) AND NOT (o.id = s.id AND od.path[1] = s.sub)
                        AND ST_DWithin(ST_Transform(od.geom, 2154), x.p, 0.5))
            ),
            pr AS (
                SELECT a.line_id, ST_Distance(a.p, b.p) AS d, ST_MakeLine(a.p, b.p) AS gap
                FROM e a JOIN e b ON b.line_id = a.line_id AND ST_AsEWKB(b.p) > ST_AsEWKB(a.p) AND ST_DWithin(a.p, b.p, :max)
                WHERE ST_Distance(a.p, b.p) >= :min
                  AND degrees(abs(atan2(sin(ST_Azimuth(a.p, b.p) - a.az), cos(ST_Azimuth(a.p, b.p) - a.az)))) < {$face}
                  AND degrees(abs(atan2(sin(ST_Azimuth(b.p, a.p) - b.az), cos(ST_Azimuth(b.p, a.p) - b.az)))) < {$face}
            )
            SELECT l.code, l.name, pr.d, ST_Y(ST_Transform(ST_Centroid(pr.gap), 4326)) AS lat, ST_X(ST_Transform(ST_Centroid(pr.gap), 4326)) AS lon, pr.d AS sort
            FROM pr JOIN lines l ON l.id = pr.line_id
            WHERE EXISTS (
                SELECT 1 FROM source_traces st
                WHERE st.source IN ('bdtopo', 'osm') AND st.geom && ST_Transform(ST_Expand(pr.gap, :cover), 4326)
                  AND ST_Length(ST_Intersection(ST_Buffer(ST_Transform(st.geom, 2154), :cover2), pr.gap)) >= 0.9 * pr.d)
            SQL, fn ($r) => sprintf('%s %s : trou de %s m (%.5f, %.5f)', $r->code ?? '-', $r->name, number_format((float) $r->d, 0), $r->lat, $r->lon),
            ['min' => self::HOLE_MIN_M, 'max' => self::HOLE_MAX_M, 'cover' => self::HOLE_COVER_M, 'cover2' => self::HOLE_COVER_M]);
    }

    /** @return array{count: int, examples: list<string>} */
    private function linesJunctionGap(): array
    {
        // Alias « xs » : partsJoinSql utilise lui-même « x » dans sa sous-requête de signature.
        $px = TrackPriority::partsJoinSql('xs');
        // Dessin (parties non masquées) des portions de la ligne `{$cond}` proches du point (en 4326, index GIST).
        $drawn = fn (string $cond, string $near, float $deg, string $era = 'en.era_id') => <<<SQL
            SELECT xs.id, xs.line_id, COALESCE(xd.path[1], 0) AS k0, ST_Transform(xd.geom, 2154) AS g
            FROM line_segments xs {$px} CROSS JOIN LATERAL ST_Dump(part.g) xd
            WHERE xs.era_id = {$era} AND {$cond} AND NOT part.covered AND xs.geom && ST_Expand({$near}, {$deg})
            SQL;
        $parts = TrackPriority::partsJoinSql('ls');
        $same = $drawn('xs.line_id = en.line_id', 'en.p4', 0.00002);
        $other = $drawn('xs.line_id <> en.line_id', 'en.p4', 0.0008);
        $ray = $drawn('xs.line_id = n.oline', 'ST_Transform(n.ry, 4326)', 0.00004, 'n.era_id');
        // Parties masquées (de la ligne, ou d'une autre ligne : relais) au bout.
        $own = str_replace('NOT part.covered', 'part.covered', $same);
        $hidden = str_replace('NOT part.covered', 'part.covered', $drawn('TRUE', 'en.p4', 0.00002));

        return $this->violations(<<<SQL
            WITH d AS MATERIALIZED (
                SELECT ls.id, ls.line_id, ls.era_id, COALESCE(dd.path[1], 0) AS k0, ST_Transform(dd.geom, 2154) AS g
                FROM line_segments ls {$parts} CROSS JOIN LATERAL ST_Dump(part.g) dd
                WHERE NOT part.covered
            ),
            e0 AS MATERIALIZED (
                SELECT d.*, x.k, x.p, ST_Transform(x.p, 4326) AS p4,
                       -- Clé du bout (grille de 10 cm) : deux bouts de la ligne sur la même clé sont raccordés.
                       ST_AsEWKB(ST_SnapToGrid(x.p, 0.1)) AS key,
                       ST_Azimuth(ST_LineInterpolatePoint(d.g, CASE WHEN x.k = 0 THEN LEAST(1, 30 / ST_Length(d.g)) ELSE GREATEST(0, 1 - 30 / ST_Length(d.g)) END), x.p) AS az
                FROM d, LATERAL (VALUES (0, ST_StartPoint(d.g)), (1, ST_EndPoint(d.g))) x(k, p)
                WHERE ST_Length(d.g) > 0.05 AND NOT ST_IsClosed(d.g)
                  AND (ST_Length(d.g) <= 4 OR NOT ST_DWithin(CASE WHEN x.k = 0 THEN ST_LineSubstring(d.g, 2 / ST_Length(d.g), 1)
                                                                 ELSE ST_LineSubstring(d.g, 0, 1 - 2 / ST_Length(d.g)) END, x.p, 0.5))
            ),
            en AS MATERIALIZED (
                SELECT e0.* FROM e0
                JOIN (SELECT era_id, line_id, key FROM e0 GROUP BY era_id, line_id, key HAVING COUNT(*) = 1) u
                  ON u.era_id = e0.era_id AND u.line_id = e0.line_id AND u.key = e0.key
            ),
            fr AS MATERIALIZED (
                SELECT en.* FROM en
                WHERE NOT EXISTS (SELECT 1 FROM ({$same}) s WHERE NOT (s.id = en.id AND s.k0 = en.k0) AND ST_DWithin(s.g, en.p, 0.5))
            ),
            n AS MATERIALIZED (
                SELECT en.*, o.line_id AS oline, o.d,
                       -- Suite masquée (toute distance) ; relais sur la partie masquée d'une autre ligne : dessin à au plus CORRIDOR_M.
                       (EXISTS (SELECT 1 FROM ({$own}) c WHERE ST_DWithin(c.g, en.p, 0.5))
                        OR (COALESCE(o.d, 1e9) <= :corridor AND EXISTS (SELECT 1 FROM ({$hidden}) c WHERE ST_DWithin(c.g, en.p, 0.5)))) AS masked,
                       ST_MakeLine(en.p, ST_Translate(en.p, :ray * sin(en.az), :ray * cos(en.az))) AS ry
                FROM fr en
                LEFT JOIN LATERAL (
                    SELECT o.line_id, ST_Distance(o.g, en.p) AS d FROM ({$other}) o
                    WHERE ST_DWithin(o.g, en.p, :search) ORDER BY ST_Distance(o.g, en.p), o.id LIMIT 1
                ) o ON TRUE
                WHERE o.d IS NULL OR o.d > :gap
            )
            SELECT y.year, l.code, l.name, ol.code AS ocode, ol.name AS oname, n.d, n.masked,
                   ST_Y(n.p4) AS lat, ST_X(n.p4) AS lon, COALESCE(n.d, 1000) AS sort
            FROM n
            JOIN eras y ON y.id = n.era_id
            JOIN lines l ON l.id = n.line_id
            LEFT JOIN lines ol ON ol.id = n.oline
            WHERE n.masked
               OR (n.d <= :reach AND (n.d <= :arrival
                   OR EXISTS (SELECT 1 FROM ({$ray}) r WHERE ST_DWithin(r.g, n.ry, :arrival2))))
            SQL, fn ($r) => sprintf('%d : %s %s : bout %s (%.5f, %.5f)', $r->year, $r->code ?? '-', $r->name,
            $r->d === null ? 'masqué sans ligne dessinée à moins de '.self::JUNCTION_MASKED_SEARCH_M.' m'
                : sprintf('à %s m de %s %s', number_format((float) $r->d, 2, ',', ' '), $r->ocode ?? '-', $r->oname).($r->masked ? ' (suite masquée)' : ''),
            $r->lat, $r->lon),
            ['corridor' => BuildSharedTrack::CORRIDOR_M, 'ray' => self::JUNCTION_RAY_M, 'search' => self::JUNCTION_MASKED_SEARCH_M, 'gap' => self::JUNCTION_GAP_M,
                'reach' => self::JUNCTION_REACH_M, 'arrival' => self::JUNCTION_ARRIVAL_M, 'arrival2' => self::JUNCTION_ARRIVAL_M]);
    }

    /** @return array{count: int, examples: list<string>} */
    private function segmentsHeritageStatus(): array
    {
        return $this->violations(<<<SQL
            SELECT ls.id, l.code, l.name, e.year, ls.status, ls.heritage_kind, ls.is_heritage,
                   CASE
                       WHEN NOT ls.is_heritage THEN 'type patrimoine sans drapeau is_heritage'
                       WHEN ls.heritage_kind = 'velorail' THEN 'vélorail « '||ls.status||' » (attendu : fermé)'
                       ELSE ls.heritage_kind||' « '||ls.status||' » (attendu : en service)'
                   END AS why,
                   e.year AS sort
            FROM line_segments ls JOIN lines l ON l.id = ls.line_id JOIN eras e ON e.id = ls.era_id
            WHERE ls.heritage_kind IS NOT NULL AND (
                NOT ls.is_heritage
                OR (ls.heritage_kind = 'velorail' AND ls.status <> 'abandoned')
                OR (ls.heritage_kind IN ('tourist_train', 'museum') AND ls.status NOT IN {$this->active()})
            )
            SQL, fn ($r) => sprintf('%d : %s %s, tronçon #%d : %s', $r->year, $r->code ?? '-', $r->name, $r->id, $r->why), order: 'sort DESC, id');
    }

    /** @return array{count: int, examples: list<string>} */
    private function segmentsInactiveWithTraffic(): array
    {
        return $this->violations(<<<SQL
            SELECT ls.id, l.code, l.name, e.year, ls.status, ls.traffic_type, e.year AS sort
            FROM line_segments ls JOIN lines l ON l.id = ls.line_id JOIN eras e ON e.id = ls.era_id
            WHERE ls.status NOT IN {$this->active()} AND ls.traffic_type <> 'unknown'
            SQL, fn ($r) => sprintf('%d : %s %s, tronçon #%d « %s » en trafic « %s »', $r->year, $r->code ?? '-', $r->name, $r->id, $r->status, $r->traffic_type),
            order: 'sort DESC, id');
    }

    /** @return array{count: int, examples: list<string>} */
    private function textsLongDash(): array
    {
        $fields = [
            ['lines', 'name'], ['lines', 'description'], ['stations', 'name'], ['stations', 'description'],
            ['line_events', 'description'], ['eras', 'label'], ['eras', 'description'],
        ];
        // Motifs passés en paramètres : pas de tiret long dans le code, ni de « - » (commentaire SQL).
        $long = "\u{2014}";
        $double = str_repeat('-', 2);
        $parts = array_map(fn ($f) => "SELECT '{$f[0]}.{$f[1]}' AS field, id, {$f[1]} AS text FROM {$f[0]} WHERE {$f[1]} LIKE ? OR {$f[1]} LIKE ?", $fields);
        $bindings = array_merge(...array_fill(0, count($fields), ["%{$long}%", "%{$double}%"]));

        return $this->violations(implode(' UNION ALL ', $parts), function ($r) use ($long, $double) {
            $pos = mb_strpos($r->text, $long);
            $pos = $pos === false ? (int) mb_strpos($r->text, $double) : $pos;
            $excerpt = mb_substr($r->text, max(0, $pos - 30), 60);

            return sprintf('%s #%d : « %s »', $r->field, $r->id, trim(str_replace(["\n", "\r"], ' ', $excerpt)));
        }, $bindings, order: 'field, id');
    }

    /**
     * Ère courante : un tronçon patrimoine (is_heritage, exploitation associative) ne porte
     * jamais une gare voyageurs SNCF desservie (tous codes, hors bouts du tronçon : une gare de
     * jonction en bout de voie touristique est normale). Le classement SNCF ne requalifie plus
     * un tronçon associatif (LineClassifier) : une exploitation associative posée sur une
     * portion encore desservie (portion trop large, « toute la ligne », ou desserte commerciale
     * prise pour un train touristique, 996000 tramway de la Balagne) y effacerait le trafic voyageurs.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function segmentsCurrentHeritageOnServedStation(): array
    {
        $km = self::KM;
        $p = PassengerPortions::stationPoint('g');
        $served = PassengerPortions::servedStationSql('g');
        $near = PassengerPortions::STATION_EXPLOITED_M;
        $end = PassengerPortions::MIN_PIECE_M;

        return $this->violations(<<<SQL
            SELECT h.id, h.code, h.name, SUM(h.km) AS km, string_agg(DISTINCT h.gares, ', ') AS gares, SUM(h.km) AS sort
            FROM (
                SELECT l.id, l.code, l.name, ls.id AS segment_id, MAX({$km}) AS km, string_agg(DISTINCT g.libelle, ', ') AS gares
                FROM line_segments ls
                JOIN eras e ON e.id = ls.era_id AND e.is_current
                JOIN lines l ON l.id = ls.line_id
                JOIN sncf_gares g ON g.voyageurs AND g.lat IS NOT NULL
                     AND ls.geom && ST_Expand({$p}, 0.0035) AND ST_DWithin(ls.geom::geography, ({$p})::geography, {$near})
                WHERE ls.is_heritage AND ls.status IN {$this->active()}
                  AND ST_LineLocatePoint(ls.geom, {$p}) * ST_Length(ls.geom::geography) > {$end}
                  AND (1 - ST_LineLocatePoint(ls.geom, {$p})) * ST_Length(ls.geom::geography) > {$end}
                  AND {$served}
                GROUP BY l.id, l.code, l.name, ls.id
            ) h
            GROUP BY h.id, h.code, h.name
            SQL, fn ($r) => sprintf('%s %s : %s km « patrimoine » sur la gare voyageurs desservie %s', $r->code ?? '-', $r->name, $this->km($r->km), $r->gares));
    }

    /**
     * Ère courante : une LGV en service est toujours électrifiée (25 kV). Une LGV non électrifiée
     * signale une ligne régénérée (vff:build-era-segments --line) sans vff:enrich-era-segments.
     *
     * Exception attestée : electrification_source = 'sncf' et non électrifié = code RFN dont la forme
     * SNCF passe à moins de 200 m mais absent de la liste SNCF des lignes électrifiées. C'est le cas
     * des raccordements de service entre une LGV et une ligne classique non électrifiée (752352 Crest,
     * 431305 Courtalain, 072311 Ocquerre, 752321 Montereau Nord…), non électrifiés aussi dans OSM
     * (electrified=no) et la BD TOPO (vérifié le 20/10/2026) : ce n'est pas une anomalie.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function segmentsCurrentLgvNotElectrified(): array
    {
        $km = self::KM;
        $lgv = Lgv::sql('l', 'ls');

        return $this->violations(<<<SQL
            SELECT l.id, l.code, l.name, SUM({$km}) AS km, COUNT(*) FILTER (WHERE ls.electrification_source IS NULL) AS never, SUM({$km}) AS sort
            FROM line_segments ls
            JOIN eras e ON e.id = ls.era_id AND e.is_current
            JOIN lines l ON l.id = ls.line_id
            WHERE ls.status IN {$this->active()} AND NOT COALESCE(ls.is_heritage, false) AND NOT COALESCE(ls.electrified, false) AND {$lgv}
              AND ls.electrification_source IS DISTINCT FROM 'sncf'
            GROUP BY l.id, l.code, l.name
            SQL, fn ($r) => sprintf('%s %s : %s km non électrifiés%s', $r->code ?? '-', $r->name, $this->km($r->km),
            $r->never > 0 ? " ({$r->never} tronçons jamais enrichis)" : ''));
    }

    /**
     * Ère courante : part des km en service du RFN (code ligne à 6 chiffres, hors patrimoine)
     * sans vitesse maximale (vff:import-sncf-vmax). Au-dessus de MAX_MISSING_SPEED_RATIO : un cas,
     * qui cite les trois lignes aux plus longs km sans vitesse.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function segmentsCurrentMissingSpeed(): array
    {
        $km = self::KM;
        $rows = DB::select(<<<SQL
            SELECT l.code, l.name, SUM({$km}) AS km, SUM({$km}) FILTER (WHERE ls.max_speed_kmh IS NULL) AS missing
            FROM line_segments ls
            JOIN eras e ON e.id = ls.era_id AND e.is_current
            JOIN lines l ON l.id = ls.line_id
            WHERE ls.status IN {$this->active()} AND NOT COALESCE(ls.is_heritage, false) AND l.code ~ '^[0-9]{6}$'
            GROUP BY l.code, l.name
            SQL);
        $total = array_sum(array_map(fn ($r) => (float) $r->km, $rows));
        $missing = array_sum(array_map(fn ($r) => (float) $r->missing, $rows));
        if ($total <= 0 || $missing / $total <= self::MAX_MISSING_SPEED_RATIO) {
            return ['count' => 0, 'examples' => []];
        }
        usort($rows, fn ($a, $b) => (float) $b->missing <=> (float) $a->missing);
        $worst = array_map(fn ($r) => sprintf('%s %s (%s km)', $r->code, $r->name, $this->km($r->missing)),
            array_slice(array_filter($rows, fn ($r) => (float) $r->missing > 0), 0, 3));

        return ['count' => 1, 'examples' => [sprintf('%s km sur %s km sans vitesse maximale (%s %%, seuil %s %%), dont %s',
            self::fmt($missing), self::fmt($total), number_format(100 * $missing / $total, 1, ',', ' '), self::MAX_MISSING_SPEED_RATIO * 100,
            implode(', ', $worst))]];
    }

    /**
     * Fiche gare (station_lines.first_year / last_year : « ligne desservie de … à … ») contre les
     * dates de la gare (fiche gare, fiche ligne, carte) et les arrêts de la carte (line_stops).
     *
     * @return array{count: int, examples: list<string>}
     */
    private function stationsLineServiceOutsideStationLife(): array
    {
        return $this->violations(<<<'SQL'
            SELECT s.id, s.name, l.code, l.name AS line, sl.first_year, sl.last_year, s.opened_at, s.closed_at, x.stop_year,
                   CASE WHEN s.opened_at IS NOT NULL AND sl.first_year < EXTRACT(YEAR FROM s.opened_at) THEN 'desservie avant l''ouverture de la gare'
                        WHEN s.closed_at IS NOT NULL AND s.closed_at <= CURRENT_DATE AND (sl.last_year IS NULL OR sl.last_year > EXTRACT(YEAR FROM s.closed_at)) THEN 'desservie après la fermeture de la gare'
                        WHEN sl.last_year < sl.first_year AND sl.years_source IS DISTINCT FROM 'never_served' THEN 'fin avant le début'
                        ELSE 'arrêt sur la carte hors de la période de desserte' END AS why,
                   s.id AS sort
            FROM station_lines sl
            JOIN stations s ON s.id = sl.station_id
            JOIN lines l ON l.id = sl.line_id
            LEFT JOIN LATERAL (
                SELECT e.year AS stop_year FROM line_stops ls JOIN eras e ON e.id = ls.era_id
                WHERE ls.line_id = sl.line_id AND ls.station_id = sl.station_id
                  AND ((sl.last_year IS NOT NULL AND sl.last_year <= e.year) OR (sl.first_year IS NOT NULL AND sl.first_year > e.year AND NOT e.is_current))
                LIMIT 1) x ON TRUE
            WHERE COALESCE(s.provenance->>'kind', '') = ''
              AND ((s.opened_at IS NOT NULL AND sl.first_year < EXTRACT(YEAR FROM s.opened_at))
                OR (s.closed_at IS NOT NULL AND s.closed_at <= CURRENT_DATE AND (sl.last_year IS NULL OR sl.last_year > EXTRACT(YEAR FROM s.closed_at)))
                OR (sl.last_year < sl.first_year AND sl.years_source IS DISTINCT FROM 'never_served')
                OR x.stop_year IS NOT NULL)
            SQL, fn ($r) => sprintf('%s (gare #%d) / %s : desservie %s-%s, gare %s-%s : %s', $r->name, $r->id, $r->code ?? $r->line,
            $r->first_year ?? '?', $r->last_year ?? '…', $r->opened_at ?? '?', $r->closed_at ?? '…', $r->why), order: 'sort');
    }

    /**
     * Gare ouverte avant toutes ses lignes (Mâcon-Loché-TGV « ouverte le 19 septembre 1981 », la LGV
     * Sud-Est le 27/09/1981) : date de gare à revoir (inauguration, essais, autre source).
     *
     * Trois règles contre les faux positifs (agents gares-avant-lignes, 2026-11-22) :
     *  1. la gare n'est comparée qu'aux événements de ligne dont la section est résolue
     *     (section_resolution = trace ou places) ; « whole_line » et « unresolved » ignorés ;
     *  2. les rattachements par nom de terminus (terminus_name) ou par PK projeté sans PK officiel
     *     (pk_source = projected_raw) ne comptent pas : Dormans et Plancoët, terminus de lignes secondaires
     *     plus tardives, sont des gares plus anciennes ;
     *  3. un écart de STATION_OPENED_TOLERANCE_DAYS jours au plus (dates au jour des deux côtés) est toléré :
     *     inauguration la veille de l'ouverture au public.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function stationsOpenedBeforeLines(): array
    {
        $cover = AttachStations::EVENT_COVERS_STATION;
        $days = self::STATION_OPENED_TOLERANCE_DAYS;
        $counted = "sl.method <> 'terminus_name' AND COALESCE(sl.pk_source, '') <> 'projected_raw'";

        return $this->violations(<<<SQL
            SELECT s.id, s.name, s.opened_at, MIN(le.event_date) AS line_opening, s.id AS sort
            FROM stations s
            JOIN station_lines sl ON sl.station_id = s.id AND {$counted}
            JOIN line_events le ON le.line_id = sl.line_id AND le.type IN ('opening', 'opening_passenger') AND le.date_precision = 'day'
                 AND le.section_resolution IN ('trace', 'places') AND {$cover}
            WHERE s.opened_at IS NOT NULL AND s.opened_precision = 'day' AND COALESCE(s.provenance->>'opened_at', '') NOT LIKE 'inferred:%'
              AND COALESCE(s.provenance->>'kind', '') = ''
            GROUP BY s.id
            HAVING MIN(le.event_date) - s.opened_at > {$days}
               AND COUNT(DISTINCT sl.line_id) = (SELECT COUNT(*) FROM station_lines a WHERE a.station_id = s.id
                                                 AND a.method <> 'terminus_name' AND COALESCE(a.pk_source, '') <> 'projected_raw')
            SQL, fn ($r) => sprintf('%s (gare #%d) : ouverte le %s, ses lignes le %s au plus tôt', $r->name, $r->id, $r->opened_at, $r->line_opening), order: 'sort');
    }

    /**
     * Tronçon « fret » de l'ère courante (forme SNCF « Exploitée ») couvert par une réouverture aux
     * voyageurs datée et sourcée sans fermeture après (PassengerPortions::reopenedPassengerSql), ou
     * qui porte à son intérieur une gare de voyageurs SNCF (sncf_voyageurs) à moins de ADOPT_M, sans
     * autre ligne en service plus proche à ADOPT_MARGIN_M près (gare de bifurcation, ligne
     * parallèle) et desservie pour le code du tronçon (mêmes contrôles que PassengerPortions).
     *
     * @return array{count: int, examples: list<string>}
     */
    private function segmentsCurrentFreightOnReopenedOrStation(): array
    {
        $km = self::KM;
        $mid = 'ST_LineInterpolatePoint(ls.geom, 0.5)';
        $reopened = PassengerPortions::reopenedPassengerSql($mid, 'ls.line_id', 'ls.era_id');
        $exploitedMid = PassengerPortions::exploitedSql($mid, 'l.code', PassengerPortions::PIECE_EXPLOITED_M);
        $sp = 's.geom';
        $exploited = PassengerPortions::exploitedSql($sp, 'l.code', PassengerPortions::PIECE_EXPLOITED_M);
        $closed = PassengerPortions::recentlyClosedSql($sp, 'cl.code = l.code');
        $elsewhere = PassengerPortions::reopenedElsewhereSql($sp, 'cl.code = l.code');
        $adopt = PassengerPortions::ADOPT_M;
        $margin = PassengerPortions::ADOPT_MARGIN_M;
        $end = PassengerPortions::MIN_PIECE_M;

        return $this->violations(<<<SQL
            SELECT l.id, l.code, l.name, SUM(h.km) AS km, string_agg(DISTINCT h.why, ', ') AS why, SUM(h.km) AS sort
            FROM (
                SELECT ls.line_id, {$km} AS km, 'réouverture aux voyageurs datée' AS why
                FROM line_segments ls
                JOIN eras e ON e.id = ls.era_id AND e.is_current
                JOIN lines l ON l.id = ls.line_id
                WHERE ls.traffic_type = 'freight' AND ls.status IN {$this->active()} AND NOT ls.is_heritage
                  AND {$reopened} AND {$exploitedMid}
                UNION ALL
                SELECT ls.line_id, MAX({$km}), 'gare voyageurs ' || string_agg(DISTINCT s.name, ', ')
                FROM line_segments ls
                JOIN eras e ON e.id = ls.era_id AND e.is_current
                JOIN lines l ON l.id = ls.line_id
                JOIN stations s ON s.geom && ST_Expand(ls.geom, 0.002) AND ST_DWithin(ls.geom::geography, s.geom::geography, {$adopt})
                JOIN station_sources ss ON ss.station_id = s.id AND ss.source = 'sncf_voyageurs' AND ss.passenger AND ss.lifecycle = 'active'
                WHERE ls.traffic_type = 'freight' AND ls.status IN {$this->active()} AND NOT ls.is_heritage
                  AND ST_LineLocatePoint(ls.geom, s.geom) * ST_Length(ls.geom::geography) > {$end}
                  AND (1 - ST_LineLocatePoint(ls.geom, s.geom)) * ST_Length(ls.geom::geography) > {$end}
                  AND NOT EXISTS (SELECT 1 FROM line_segments o JOIN lines ol ON ol.id = o.line_id
                                  WHERE o.era_id = ls.era_id AND o.status IN {$this->active()} AND ol.code IS DISTINCT FROM l.code
                                    AND o.geom && ST_Expand(s.geom, 0.002)
                                    AND ST_DWithin(o.geom::geography, s.geom::geography, ST_Distance(ls.geom::geography, s.geom::geography) + {$margin}))
                  AND {$exploited} AND NOT {$closed} AND NOT {$elsewhere}
                GROUP BY ls.id, ls.line_id
            ) h
            JOIN lines l ON l.id = h.line_id
            GROUP BY l.id, l.code, l.name
            SQL, fn ($r) => sprintf('%s %s : %s km en « fret » (%s)', $r->code, $r->name, $this->km($r->km), $r->why));
    }

    /**
     * Série reliée à une ligne d'un autre écartement (MaterialGaugeRule : moins du quart de la longueur,
     * toutes ères), sans phrase de preuve qui nomme l'écartement ni exception de
     * database/data/line_material_gauge_exceptions.php.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function lineMaterialsGaugeConflict(): array
    {
        $conflicts = MaterialGaugeRule::fromDatabase()->storedConflicts();

        return ['count' => count($conflicts), 'examples' => array_map(
            fn (array $c): string => sprintf('%s × %s (lien %d) : %s', $c['line'], $c['material'], $c['id'], $c['reason']),
            array_slice($conflicts, 0, self::EXAMPLES),
        )];
    }

    /**
     * Lien importé en `high` ou `medium` sans aucune preuve franche : chaque preuve porte un doute
     * (`doubt`, WikipediaMaterialSections::doubt()) ou vient d'un article commun à plusieurs lignes.
     * LineMaterialLinker::attributes() le range en `low` ; un écart veut dire une saisie à la main ou
     * des preuves écrites par une ancienne version de l'import.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function lineMaterialsDoubtfulConfidence(): array
    {
        return $this->violations(<<<'SQL'
            SELECT lm.id AS sort, lm.id, l.code, l.name AS line_name, m.name AS material, lm.confidence,
                   (SELECT string_agg(DISTINCT COALESCE(e->>'doubt', 'shared_article'), ', ') FROM jsonb_array_elements(lm.evidence) e) AS doubts
            FROM line_materials lm
            JOIN lines l ON l.id = lm.line_id
            JOIN materials m ON m.id = lm.material_id
            WHERE lm.source IN ('wikidata', 'wikipedia')
              AND lm.confidence IN ('high', 'medium')
              AND jsonb_typeof(lm.evidence) = 'array' AND jsonb_array_length(lm.evidence) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM jsonb_array_elements(lm.evidence) e
                  WHERE e->>'doubt' IS NULL AND COALESCE((e->>'shared_article')::boolean, false) = false
              )
            SQL, fn ($r) => sprintf('%s × %s (lien %d, %s) : %s', $r->code ?? $r->line_name, $r->material, $r->id, $r->confidence, $r->doubts), order: 'sort');
    }

    /**
     * Série électrique reliée à une ligne jamais électrifiée dans un de ses systèmes pendant son service
     * (MaterialTractionRule : moins de MIN_SHARE de la longueur connue à chaque ère), sans phrase de preuve
     * qui nomme sa tension ni exception de database/data/line_material_traction_exceptions.php.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function lineMaterialsTractionConflict(): array
    {
        $conflicts = MaterialTractionRule::fromDatabase()->storedConflicts();

        return ['count' => count($conflicts), 'examples' => array_map(
            fn (array $c): string => sprintf('%s × %s (lien %d) : %s', $c['line'], $c['material'], $c['id'], $c['reason']),
            array_slice($conflicts, 0, self::EXAMPLES),
        )];
    }

    // ─── Outils ──────────────────────────────────────────────────────────

    /**
     * Nombre de lignes du SELECT et les EXAMPLES premières (colonne `sort`
     * décroissante par défaut), en une requête.
     *
     * @return array{count: int, examples: list<string>}
     */
    private function violations(string $sql, callable $format, array $bindings = [], string $order = 'sort DESC'): array
    {
        $rows = DB::select("SELECT q.*, COUNT(*) OVER () AS total__ FROM ({$sql}) q ORDER BY {$order} LIMIT ".self::EXAMPLES, $bindings);

        return ['count' => (int) ($rows[0]->total__ ?? 0), 'examples' => array_map($format, $rows)];
    }

    private function active(): string
    {
        return self::ACTIVE;
    }

    private function km(float|string|null $km): string
    {
        return number_format((float) $km, 1, ',', ' ');
    }

    private static function fmt(float|int $km): string
    {
        return number_format($km, 0, ',', ' ');
    }
}
