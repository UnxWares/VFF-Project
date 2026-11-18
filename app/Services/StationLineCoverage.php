<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Desserte des gares par les lignes, à une ère : source UNIQUE partagée par
 * le moteur des gares (vff:build-station-eras) et le contrôle de cohérence
 * (vff:check-consistency), pour qu'ils appliquent exactement le même plafond
 * (règle R2 : une gare ne peut pas être plus active que ses lignes).
 *
 * Exceptions au plafond (la gare suit alors ses propres sources) :
 *   - réseau non modélisé : aucune ligne de la gare dans les tronçons de l'ère
 *     et statut attesté ailleurs que par la liste des gares SNCF ;
 *   - lignes non datées : à une ère HISTORIQUE, toutes les lignes de la gare
 *     sont absentes de TOUTES les ères historiques faute de date d'ouverture.
 *     « Absente parce que non datée » n'est pas « fermée » : statut incertain,
 *     non plafonné. Dès qu'une ligne de la gare est datée, R2 reste stricte.
 *
 * Gare isolée (isolated() / isolationVerdict()) : ouverte selon ses sources
 * mais sans AUCUN tronçon de l'ère, même fermé, à moins de 300 m. Elle
 * flotterait sans voie sur la carte ; elle n'y est donc jamais montrée
 * « ouverte » :
 *   - voie disparue : la gare avait une voie à une ère antérieure, la
 *     dernière était fermée (abandonnée, déposée…) et aucune ne revient
 *     ensuite → fermée (R2 : ses lignes sont fermées puis absentes) ;
 *   - existence seulement déduite des lignes (aucune ouverture propre) et
 *     voie qui n'apparaît qu'à une ère postérieure → la gare n'existe pas
 *     encore (elle suit sa ligne) ;
 *   - sinon (voie datée plus tard alors que les sources de la gare la disent
 *     ouverte, trou entre décennies, ligne non datée ou absente de VFF,
 *     réseau non modélisé…) → « incertaine » (station_eras.uncertain) :
 *     même logique que l'exception « lignes non datées » de R2 : on ne ferme
 *     pas une gare faute de tracé, mais on ne la montre pas ouverte.
 *
 * Réseau non modélisé ou lignes disparues (2026-10-19, 117 gares « active »
 * sans voie en service à moins de 150 m sur la carte de 2030) :
 *   - lignes disparues : les lignes rattachées à la gare figurent dans les
 *     tronçons d'une ère ANTÉRIEURE mais plus dans ceux de l'ère (fermées puis
 *     déposées), et aucune voie actuelle hors VFF ne passe près de la gare →
 *     ce n'est pas un réseau non modélisé : R2 s'applique (gare fermée), quel
 *     que soit le statut Wikidata (« en service » sur des haltes disparues) ;
 *   - voie actuelle hors VFF (liveUnmodeledTracks) : voie principale en
 *     service (BD TOPO, OSM) à moins de LIVE_TRACK_RADIUS_M de la gare et
 *     rattachée à aucune ligne VFF (RER A de la RATP, trains touristiques…) :
 *     l'exception « réseau non modélisé » reste (statut incertain) ;
 *   - gare touristique : cette voie est touristique (OSM usage=tourism,
 *     railway=preserved, exploitant touristique, nom « petit train »…) ou les
 *     sources de la gare le disent (tourism=*, exploitant touristique) → au
 *     mieux « touristique » (jamais « active »), toujours incertaine tant que
 *     sa ligne n'est pas dans VFF.
 */
final class StationLineCoverage
{
    public const NOTE_UNMODELED = 'Ligne absente des tronçons de l\'ère : statut non plafonné';

    public const NOTE_UNDATED = 'Statut incertain : lignes de la gare non datées, statut non plafonné';

    public const NOTE_NO_TRACK = 'Statut incertain : aucune voie reconstituée à moins de 300 m à cette époque';

    public const NOTE_TRACK_GONE = 'Plus aucune voie à cette époque (ligne fermée puis disparue)';

    public const NOTE_UNMODELED_TOURIST = 'Train touristique absent des lignes VFF : statut non plafonné';

    /** Gare « isolée » : aucun tronçon de l'ère, quel que soit son statut, à moins de… (m). */
    public const ISOLATION_RADIUS_M = 300;

    /** Voie actuelle hors VFF (BD TOPO, OSM) à moins de… (m) d'une gare (cf. liveUnmodeledTracks). */
    public const LIVE_TRACK_RADIUS_M = 80;

    /** Nom d'une voie ou d'une gare de chemin de fer touristique. */
    public const TOURIST_NAME_RX = '/petit train|p[\'’]tit train|train touristique|chemin de fer touristique|tramway touristique|train à vapeur|vélo-?rail|velo-?rail|draisine|disneyland|baie de somme/iu';

    /** @var array<int, bool>|null */
    private ?array $liveTracks = null;

    /** @var array<int, true>|null */
    private ?array $touristStations = null;

    /** @var array<int, array<int, true>> */
    private array $linesGone = [];

    /** Verdicts d'isolationVerdict(). */
    public const ISOLATED_CLOSED = 'closed';

    public const ISOLATED_ABSENT = 'absent';

    public const ISOLATED_UNCERTAIN = 'uncertain';

    /** @var array<int, true>|null */
    private ?array $undatedOnly = null;

    /**
     * Desserte des gares à une ère : [niveau, uniquement touristique].
     * Niveau (StationTimeline::LINES_*) : 2 = ligne en service ouverte aux
     * voyageurs (ou trafic inconnu), 1 = fret seul / voie de service, 0 = aucune.
     * Gare absente du résultat = aucune ligne connue à cette ère.
     *
     * @return array<int, array{0: int, 1: bool}>
     */
    public function levels(int $eraId): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT s.id,
                   MAX(CASE WHEN ls.status IN ('in_service', 'single_track') AND ls.traffic_type <> 'freight' THEN 2
                            WHEN ls.status IN ('in_service', 'single_track', 'service_track') THEN 1
                            ELSE 0 END) AS lines,
                   COALESCE(BOOL_AND(ls.is_heritage) FILTER (
                       WHERE ls.status IN ('in_service', 'single_track') AND ls.traffic_type <> 'freight'), FALSE) AS heritage_only
            FROM stations s
            JOIN line_segments ls ON ls.era_id = :era AND ls.geom && ST_Expand(s.geom, 0.007)
            LEFT JOIN station_lines sl ON sl.station_id = s.id AND sl.line_id = ls.line_id
            WHERE (sl.id IS NOT NULL AND ST_DWithin(ls.geom::geography, s.geom::geography, 500))
               OR (NOT EXISTS (SELECT 1 FROM station_lines x WHERE x.station_id = s.id)
                   AND ST_DWithin(ls.geom::geography, s.geom::geography,
                       CASE WHEN s.position_source IN ('sncf', 'osm', 'ohm') THEN 100 ELSE 300 END))
            GROUP BY s.id
        SQL, ['era' => $eraId]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = [(int) $r->lines, (bool) $r->heritage_only];
        }

        // Ligne rattachée présente à l'ère mais dont le tracé n'atteint pas la
        // gare (tracé de référence incomplet, ex. Paris-Bastille) : état de la
        // ligne dans son ensemble à cette ère. Pas quand la portion de la gare a
        // DISPARU (ex. halte de Has, ligne de Templeuve à Don-Sainghin dont il ne
        // reste que 2,4 km en service à Templeuve) : à l'ère courante, le tracé de
        // référence de la ligne passe près de la gare ; à une ère historique, la
        // ligne avait un tronçon près de la gare à une ère antérieure (une portion
        // seulement non datée, jamais dessinée, garde l'état d'ensemble).
        $era = DB::table('eras')->where('id', $eraId)->first(['year', 'is_current']);
        $lineLevel = DB::select(<<<'SQL'
            WITH sl2 AS (
                SELECT sl.station_id, sl.line_id
                FROM station_lines sl JOIN stations s ON s.id = sl.station_id
                WHERE CASE WHEN CAST(:current AS boolean) THEN NOT EXISTS (
                               SELECT 1 FROM line_reference_geometries r
                               WHERE r.line_id = sl.line_id AND r.geom && ST_Expand(s.geom, 0.007)
                                 AND ST_DWithin(r.geom::geography, s.geom::geography, 500))
                           ELSE NOT EXISTS (
                               SELECT 1 FROM line_segments p JOIN eras e ON e.id = p.era_id
                               WHERE p.line_id = sl.line_id AND e.year < :year AND p.geom && ST_Expand(s.geom, 0.007)
                                 AND ST_DWithin(p.geom::geography, s.geom::geography, 500)) END
            )
            SELECT sl2.station_id AS id,
                   MAX(CASE WHEN ls.status IN ('in_service', 'single_track') AND ls.traffic_type <> 'freight' THEN 2
                            WHEN ls.status IN ('in_service', 'single_track', 'service_track') THEN 1
                            ELSE 0 END) AS lines,
                   COALESCE(BOOL_AND(ls.is_heritage) FILTER (
                       WHERE ls.status IN ('in_service', 'single_track') AND ls.traffic_type <> 'freight'), FALSE) AS heritage_only
            FROM sl2
            JOIN line_segments ls ON ls.era_id = :era AND ls.line_id = sl2.line_id
            GROUP BY sl2.station_id
        SQL, ['era' => $eraId, 'current' => ($era->is_current ?? false) ? 'true' : 'false', 'year' => (int) ($era->year ?? 0)]);
        foreach ($lineLevel as $r) {
            $out[(int) $r->id] ??= [(int) $r->lines, (bool) $r->heritage_only];
        }

        return $out;
    }

    /**
     * Gares dont au moins une ligne rattachée figure dans les tronçons de l'ère.
     *
     * @return array<int, true>
     */
    public function modeled(int $eraId): array
    {
        return array_fill_keys(array_map('intval', DB::table('station_lines as sl')
            ->whereExists(fn ($q) => $q->from('line_segments as ls')->where('ls.era_id', $eraId)->whereColumn('ls.line_id', 'sl.line_id'))
            ->distinct()->pluck('sl.station_id')->all()), true);
    }

    /**
     * Gares dont au moins une ligne rattachée figure dans les tronçons d'une ère
     * ANTÉRIEURE à $year (combiné à « aucune ligne rattachée dans l'ère » :
     * lignes disparues, cf. exemption()).
     *
     * @return array<int, true>
     */
    public function linesGone(int $year): array
    {
        return $this->linesGone[$year] ??= array_fill_keys(array_map('intval', array_column(DB::select(<<<'SQL'
            SELECT DISTINCT sl.station_id
            FROM station_lines sl
            WHERE EXISTS (SELECT 1 FROM line_segments ls JOIN eras e ON e.id = ls.era_id
                          WHERE ls.line_id = sl.line_id AND e.year < :year)
        SQL, ['year' => $year]), 'station_id')), true);
    }

    /**
     * Gares à moins de LIVE_TRACK_RADIUS_M d'une voie ACTUELLE rattachée à
     * aucune ligne VFF (BD TOPO voie principale « En service » ; OSM rail,
     * narrow_gauge, light_rail, preserved hors voies de service) :
     * [id => true si au moins une de ces voies est touristique].
     *
     * @return array<int, bool>
     */
    public function liveUnmodeledTracks(): array
    {
        if ($this->liveTracks !== null) {
            return $this->liveTracks;
        }
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT s.id, t.source, t.layer, t.properties->>'usage' AS usage, t.properties->>'operator' AS operator,
                   t.properties->>'network' AS network, COALESCE(t.properties->>'name', t.properties->>'cpx_toponyme') AS name,
                   t.properties->>'railway:preserved' AS preserved
            FROM stations s
            JOIN source_traces t ON t.source IN ('osm', 'bdtopo') AND t.line_id IS NULL
                 AND t.geom && ST_Expand(s.geom, 0.0012)
                 AND CASE WHEN t.source = 'bdtopo' THEN t.layer IN ('Voie ferrée principale', 'LGV') AND t.properties->>'etat_de_l_objet' = 'En service'
                          ELSE t.layer IN ('rail', 'narrow_gauge', 'light_rail', 'preserved') AND NOT jsonb_exists(t.properties, 'service') END
                 AND ST_DWithin(t.geom::geography, s.geom::geography, :radius)
        SQL, ['radius' => self::LIVE_TRACK_RADIUS_M]) as $r) {
            $out[(int) $r->id] = ($out[(int) $r->id] ?? false) || self::isTouristTrack($r->layer, $r->usage, $r->operator, $r->network, $r->name, $r->preserved);
        }

        return $this->liveTracks = $out;
    }

    /**
     * Gares dont les propres sources (OSM) disent la gare touristique :
     * tourism=*, usage=tourism, railway:preserved, exploitant ou nom touristique.
     *
     * @return array<int, true>
     */
    public function touristStations(): array
    {
        if ($this->touristStations !== null) {
            return $this->touristStations;
        }
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT ss.station_id AS id, ss.properties->>'usage' AS usage, ss.properties->>'operator' AS operator,
                   ss.properties->>'network' AS network, COALESCE(ss.properties->>'name', ss.name) AS name,
                   ss.properties->>'railway:preserved' AS preserved, ss.properties->>'tourism' AS tourism
            FROM station_sources ss
            WHERE ss.station_id IS NOT NULL AND ss.source = 'osm'
        SQL) as $r) {
            if (in_array($r->tourism, ['yes', 'attraction'], true)
                || self::isTouristTrack(null, $r->usage, $r->operator, $r->network, $r->name, $r->preserved)) {
                $out[(int) $r->id] = true;
            }
        }

        return $this->touristStations = $out;
    }

    /** Voie (ou gare) d'un chemin de fer touristique, d'après ses attributs OSM / BD TOPO. */
    public static function isTouristTrack(?string $layer, ?string $usage, ?string $operator, ?string $network, ?string $name, ?string $preserved): bool
    {
        return $usage === 'tourism' || $layer === 'preserved' || $preserved === 'yes'
            || ($usage !== null && (bool) preg_match('/vélo-?rail|touristique/iu', $usage))
            || OsmImporter::isHeritageOperator($operator) || OsmImporter::isHeritageOperator($network)
            || (bool) preg_match(self::TOURIST_NAME_RX, implode(' | ', array_filter([$name, $operator, $network])));
    }

    /**
     * Gares dont TOUTES les lignes connues sont absentes de toutes les ères
     * historiques (faute de date d'ouverture). Lignes d'une gare : ses
     * rattachements station_lines, à défaut les tracés de référence à moins de
     * 100 m (position sncf / osm / ohm) ou 300 m.
     *
     * @return array<int, true>
     */
    public function undatedOnly(): array
    {
        if ($this->undatedOnly !== null) {
            return $this->undatedOnly;
        }
        $rows = DB::select(<<<'SQL'
            WITH dated AS (
                SELECT DISTINCT ls.line_id FROM line_segments ls JOIN eras e ON e.id = ls.era_id WHERE NOT e.is_current
            ),
            own AS (
                SELECT sl.station_id, sl.line_id FROM station_lines sl
                UNION ALL
                SELECT s.id, r.line_id
                FROM stations s
                JOIN line_reference_geometries r ON r.geom && ST_Expand(s.geom, 0.005)
                     AND ST_DWithin(r.geom::geography, s.geom::geography,
                         CASE WHEN s.position_source IN ('sncf', 'osm', 'ohm') THEN 100 ELSE 300 END)
                WHERE NOT EXISTS (SELECT 1 FROM station_lines x WHERE x.station_id = s.id)
            )
            SELECT own.station_id FROM own LEFT JOIN dated d ON d.line_id = own.line_id
            GROUP BY own.station_id
            HAVING BOOL_AND(d.line_id IS NULL)
        SQL);

        return $this->undatedOnly = array_fill_keys(array_map(fn ($r) => (int) $r->station_id, $rows), true);
    }

    /**
     * Parmi $ids, gares sans aucun tronçon de l'ère (quel que soit son statut)
     * à moins de ISOLATION_RADIUS_M. Même définition que le contrôle
     * stations.no_line_nearby de vff:check-consistency.
     *
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    public function isolated(int $eraId, array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 2000) as $chunk) {
            $rows = DB::select(<<<'SQL'
                SELECT s.id FROM stations s
                WHERE s.id = ANY (CAST(:ids AS bigint[]))
                  AND NOT EXISTS (
                      SELECT 1 FROM line_segments ls
                      WHERE ls.era_id = :era AND ls.geom && ST_Expand(s.geom, 0.005)
                        AND ST_DWithin(ls.geom::geography, s.geom::geography, :radius))
            SQL, ['ids' => '{'.implode(',', $chunk).'}', 'era' => $eraId, 'radius' => self::ISOLATION_RADIUS_M]);
            foreach ($rows as $r) {
                $out[(int) $r->id] = true;
            }
        }

        return $out;
    }

    /**
     * Voies proches de chaque gare, ère par ère : [année => une voie exploitée
     * (en service / voie de service) figure parmi les tronçons à moins de
     * ISOLATION_RADIUS_M]. Ère absente du tableau = aucun tronçon proche.
     *
     * @param  list<int>  $ids
     * @return array<int, array<int, bool>>
     */
    public function trackHistory(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = DB::select(<<<'SQL'
                SELECT s.id, e.year, BOOL_OR(ls.status IN ('in_service', 'single_track', 'service_track')) AS live
                FROM stations s
                JOIN line_segments ls ON ls.geom && ST_Expand(s.geom, 0.005)
                     AND ST_DWithin(ls.geom::geography, s.geom::geography, :radius)
                JOIN eras e ON e.id = ls.era_id
                WHERE s.id = ANY (CAST(:ids AS bigint[]))
                GROUP BY s.id, e.year
            SQL, ['ids' => '{'.implode(',', $chunk).'}', 'radius' => self::ISOLATION_RADIUS_M]);
            foreach ($rows as $r) {
                $out[(int) $r->id][(int) $r->year] = (bool) $r->live;
            }
        }

        return $out;
    }

    /**
     * Sort d'une gare ouverte selon ses sources mais isolée à l'ère $year
     * (cf. docblock de la classe) : ISOLATED_CLOSED, ISOLATED_ABSENT ou
     * ISOLATED_UNCERTAIN.
     *
     * @param  array<int, bool>  $history  trackHistory() de la gare
     * @param  bool  $inferredOnly  la gare n'a aucune ouverture propre (dates déduites des lignes)
     */
    /**
     * Statut affiché d'une gare non plafonnée (exception ou gare isolée incertaine) :
     * une gare touristique n'est jamais « active » ni « fret seul ».
     */
    public static function touristCap(string $status, bool $tourist): string
    {
        return $tourist && in_array($status, [StationTimeline::ACTIVE, StationTimeline::FREIGHT], true) ? StationTimeline::TOURIST : $status;
    }

    public static function isolationVerdict(array $history, int $year, bool $isCurrentEra, bool $inferredOnly): string
    {
        $earlier = array_filter($history, fn ($live, $y) => $y < $year, ARRAY_FILTER_USE_BOTH);
        $later = array_filter($history, fn ($live, $y) => $y > $year, ARRAY_FILTER_USE_BOTH);
        if ($earlier !== [] && $later === []) {
            ksort($earlier);

            // Dernière voie connue fermée, puis plus rien : la ligne a disparu.
            return end($earlier) ? self::ISOLATED_UNCERTAIN : self::ISOLATED_CLOSED;
        }
        if ($earlier === [] && $later !== [] && $inferredOnly && ! $isCurrentEra) {
            return self::ISOLATED_ABSENT;
        }

        return self::ISOLATED_UNCERTAIN;
    }

    /**
     * Note d'exception au plafond R2, ou null si la gare doit être plafonnée.
     *
     * @param  bool  $hasLevel  la gare a une desserte calculée à l'ère (levels())
     * @param  bool  $modeled  une ligne rattachée figure dans les tronçons de l'ère
     * @param  string|null  $statusSource  provenance du statut (provenance->>'status')
     * @param  bool  $undatedOnly  toutes ses lignes sont non datées (undatedOnly())
     * @param  bool  $linesGone  une ligne rattachée figure dans une ère antérieure (linesGone())
     * @param  bool  $liveTrack  voie actuelle hors VFF près de la gare (liveUnmodeledTracks())
     */
    /**
     * Ère courante : statut d'une gare hors des lignes VFF attesté par une source relevée
     * aujourd'hui (OSM, Wikidata, gares-de-voyageurs), pas seulement par un drapeau de
     * liste-des-gares (qui garde des gares fermées) : la gare est en service, son statut
     * n'est pas incertain (trains de parcs, Disneyland Railroad, Nigloland).
     */
    public static function attestedToday(?string $statusSource, ?string $sourceStatus): bool
    {
        return in_array($statusSource, ['osm', 'wikidata', 'sncf_voyageurs'], true)
            && in_array($sourceStatus, StationTimeline::OPEN_STATUSES, true);
    }

    public static function exemption(bool $isCurrentEra, bool $hasLevel, bool $modeled, ?string $statusSource, bool $undatedOnly,
        bool $linesGone = false, bool $liveTrack = false, bool $ownChronology = false): ?string
    {
        // $ownChronology : gare rattachée à AUCUNE ligne, datée par ses propres sources (ouverture et
        // fermeture) ; son statut vient de sa chronologie, pas des drapeaux SNCF (Lille-Saint-Sauveur,
        // gare aux marchandises de 1865 à 2003, embranchement absent des lignes VFF).
        if (! $hasLevel && ! $modeled && (($statusSource ?? 'sncf') !== 'sncf' || $ownChronology)) {
            // Lignes VFF de la gare fermées puis disparues, et aucune autre voie actuelle :
            // la gare suit ses lignes (R2), même si Wikidata ou OSM la disent « en service ».
            if ($linesGone && ! $liveTrack) {
                return null;
            }

            return self::NOTE_UNMODELED;
        }
        if (! $isCurrentEra && ! $hasLevel && $undatedOnly) {
            return self::NOTE_UNDATED;
        }

        return null;
    }
}
