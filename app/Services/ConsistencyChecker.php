<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Contrôles automatiques de cohérence des données VFF, par ère (décennie).
 * Utilisé par `vff:check-consistency`.
 *
 * Règles métier OBLIGATOIRES (les seules corrigées automatiquement, cf. fix*) :
 *   R1 « Si y'a du passager, y'a du fret » : une portion ouverte aux voyageurs
 *      est mixte ; « voyageurs seuls » (passenger) uniquement sur les LGV.
 *   R2 Une gare ne peut pas être plus active que ses lignes : à une ère donnée,
 *      si toutes ses lignes sont fermées / déposées (ou absentes), la gare est
 *      fermée, même si la liste des gares SNCF la marque fret ou voyageurs ;
 *      lignes ouvertes au seul fret → au mieux gare « fret seul ».
 *
 * Les autres contrôles signalent des incohérences qui demandent un arbitrage
 * (dates contradictoires, catégories, trous entre décennies…) : rien n'est
 * modifié pour elles.
 *
 * Priorités : 1 = règle métier violée ou erreur visible sur la carte ;
 *             2 = données contradictoires à arbitrer ; 3 = complétude.
 *
 * Chaque constat : key, group, priority, rule, label, count, km, by_era,
 * detail, examples, fixable.
 */
final class ConsistencyChecker
{
    public const ACTIVE_SEGMENTS = ['in_service', 'single_track'];

    public const OPEN_STATIONS = ['active', 'tourist', 'no_commercial'];

    public const CLOSED_STATION = 'declassified';

    /** Statut affiché d'une gare ouverte selon ses sources mais non confirmée par les voies (station_eras.uncertain). */
    public const UNCERTAIN_STATION = 'uncertain';

    /** Gare « sans ligne » : aucun tronçon (quel que soit son statut) à moins de… (même rayon que vff:build-station-eras) */
    public const NO_LINE_RADIUS_M = StationLineCoverage::ISOLATION_RADIUS_M;

    /** Recouvrement minimal pour qu'une ligne particulière/militaire « recouvre » le RFN. */
    public const MIN_OVERLAP_M = 300;

    /** Longueur minimale d'une portion pour le contrôle des trous entre décennies. */
    public const MIN_GAP_PIECE_M = 200;

    /** Dates d'événements antérieures : implausibles (1er chemin de fer français : 1823-1827). */
    public const EARLIEST_PLAUSIBLE_DATE = '1820-01-01';

    public const GROUPS = ['gares', 'portions', 'evenements', 'categories', 'completude'];

    private const GROUPS_ORDER = ['gares' => 0, 'portions' => 1, 'evenements' => 2, 'categories' => 3, 'completude' => 4];

    /** Écart toléré entre les km reconstitués d'une ère et l'ordre de grandeur historique. */
    public const COMPLETENESS_TOLERANCE = 0.25;

    /** Écart absolu minimal (km) pour signaler une catégorie (évite le bruit sur les petits réseaux). */
    public const COMPLETENESS_MIN_GAP_KM = 500;

    /**
     * Ordres de grandeur historiques des km de lignes EN SERVICE, par catégorie
     * VFF (lines.network) et par ère. ['km' => x] : comparé à ±25 % ;
     * ['max' => x] : réseau quasi disparu, ne doit pas dépasser x ; absent :
     * pas de référence fiable (km affichés sans verdict).
     *
     * principal (intérêt général) : ~38 000 km vers 1900 ; apogée ~42 000 km
     *   vers 1923 après le raccordement de l'Alsace-Moselle (A. Beyer, Géohistoire
     *   de la contraction du réseau ferroviaire français, 2020,
     *   halshs.archives-ouvertes.fr/halshs-02880737) ; 43 000 km en 1938 puis
     *   34 600 km en 1982 (FNAUT, fnaut.fr/uploads/2021/07/276.pdf) ; 28 808 km
     *   exploités en 2016 (Beyer). 1950, 1960, 1990, 2010 : interpolés, à
     *   affiner avec les annuaires statistiques SNCF / INSEE.
     *   1840-1890 (ères ajoutées le 2026-10-19) : ordres de grandeur arrondis
     *   couramment cités (quelques centaines de km vers 1840, ~3 000 km vers
     *   1850, ~9 500 en 1860, ~17 000 en 1870, ~23 000 en 1880, ~33 000 en
     *   1890), à vérifier avec les statistiques officielles de l'époque.
     *   TERRITOIRE ACTUEL : la carte montre les lignes du territoire actuel à toutes les
     *   ères, alors que ces repères suivent les frontières de l'époque. De 1871 à 1918,
     *   l'Alsace-Moselle (réseau allemand des chemins de fer d'Alsace-Lorraine) n'y est pas
     *   comptée : environ 950 km en 1880, ajoutés au repère de 1880 (23 000 + 950, arrondi
     *   à 24 000) ; 1890-1910 : écart du même ordre (1 000 à 2 000 km, à chiffrer), laissé
     *   dans la tolérance de 25 %. Savoie et Nice (françaises depuis 1860) : rien à corriger,
     *   aucune ligne n'y est ouverte avant l'ère 1850 incluse.
     * secondaire (intérêt local, tramways départementaux, voie métrique) :
     *   apogée de 20 291 km en 1928 (fr.wikipedia.org, Histoire des chemins de
     *   fer français) ; « réduit de moitié en 1948 à environ 10 000 kilomètres »,
     *   puis « plus que quelques centaines de kilomètres vers 1960 » (Historail,
     *   « Les fermetures de lignes d'intérêt général en France », avril 2011, p. 20,
     *   cité par fr.wikipedia.org/w/index.php?title=Fermetures_de_lignes_ferroviaires_en_France&oldid=239176212) ;
     *   940 km d'intérêt local en 1960 (Guyot, 2001, p. 144, de seconde main).
     *   Plafonds : 1950 ≤ 10 000 km (le réseau ne fait que décroître après 1948) ;
     *   1960 ≤ 2 000 km = quelques centaines de km d'intérêt local (≤ 1 000) plus les
     *   lignes à voie métrique d'INTÉRÊT GÉNÉRAL que VFF classe aussi « secondaire »
     *   (Réseau breton jusqu'en 1967, Corse, Blanc-Argent, Cerdagne…). L'ancien plafond
     *   1950 de 4 000 km n'était pas sourcé (remplacé le 2026-10-20).
     */
    public const HISTORICAL_KM = [
        'principal' => [
            1850 => ['km' => 3000], 1860 => ['km' => 9500], 1870 => ['km' => 17000], 1880 => ['km' => 24000], 1890 => ['km' => 33000],
            1900 => ['km' => 38000], 1910 => ['km' => 40500], 1920 => ['km' => 41500], 1930 => ['km' => 42500],
            1940 => ['km' => 42500], 1950 => ['km' => 40000], 1960 => ['km' => 38000], 1970 => ['km' => 36000],
            1980 => ['km' => 34000], 1990 => ['km' => 33000], 2000 => ['km' => 32000], 2010 => ['km' => 30000],
            2020 => ['km' => 28000], 2030 => ['km' => 28000],
        ],
        'secondaire' => [
            1920 => ['km' => 20000], 1930 => ['km' => 19000],
            // Survivants après 1950 : Réseau breton (jusqu'en 1967), Vivarais (1968), CFD et
            // tramways de quelques départements, Corse, Provence. 1950 et 1960 : sources ci-dessus.
            1950 => ['max' => 10000], 1960 => ['max' => 2000], 1970 => ['max' => 1000], 1980 => ['max' => 1000],
            1990 => ['max' => 1000], 2000 => ['max' => 1000], 2010 => ['max' => 1000], 2020 => ['max' => 1000], 2030 => ['max' => 1000],
        ],
    ];

    private const OPENING_TYPES = ['opening', 'opening_passenger', 'opening_freight'];

    private const REOPENING_TYPES = ['reopening', 'reopening_passenger', 'reopening_freight'];

    private const CLOSURE_TYPES = ['closure', 'closure_passenger', 'closure_freight', 'declassification'];

    /** Services ouverts par une ouverture générique (aligné sur BuildEraSegments::DEFAULT_SERVICES). */
    private const DEFAULT_SERVICES = ['principal' => 'PF', 'secondaire' => 'PF', 'particulier' => 'F', 'militaire' => ''];

    private const MAX_EXAMPLES = 20;

    /** @var list<object{id: int, year: int, is_current: bool}> toutes les ères, par année */
    private array $eras;

    /** @var array<int, int> era_id => nombre de tronçons */
    private array $segmentCounts = [];

    /** @var array<int, array<int, array{0: int, 1: bool}>> cache era_id => desserte des gares */
    private array $levels = [];

    /** @var list<string> ères non contrôlables (pas de tronçons…) */
    private array $notes = [];

    /** @var array<int, array<int, true>> cache era_id => gares dont une ligne rattachée est dans l'ère */
    private array $modeled = [];

    /** Desserte des gares : logique partagée avec vff:build-station-eras. */
    private StationLineCoverage $coverage;

    /**
     * @param  int|null  $onlyYear  limite les contrôles PAR ÈRE à cette année
     * @param  list<string>|null  $groups  groupes de contrôles (null = tous)
     */
    public function __construct(private ?int $onlyYear = null, private ?array $groups = null)
    {
        $this->coverage = new StationLineCoverage;
        $this->eras = array_map(fn ($e) => (object) ['id' => (int) $e->id, 'year' => (int) $e->year, 'is_current' => (bool) $e->is_current],
            DB::select('SELECT id, year, is_current FROM eras ORDER BY year'));
    }

    /**
     * Lance tous les contrôles demandés.
     *
     * @return list<array<string, mixed>>
     */
    public function run(): array
    {
        $this->notes = [];
        $this->segmentCounts = [];
        foreach (DB::select('SELECT era_id, COUNT(*) AS n FROM line_segments GROUP BY era_id') as $r) {
            $this->segmentCounts[(int) $r->era_id] = (int) $r->n;
        }
        foreach ($this->scopedEras() as $era) {
            if (! $this->hasSegments($era)) {
                $this->notes[] = "Ère {$era->year} : aucun tronçon (moteur décennal pas encore passé), contrôles gares/portions de cette ère sautés.";
            }
        }

        $findings = [];
        if ($this->wants('gares')) {
            array_push($findings, ...$this->stationFindings(), ...$this->displayFindings());
        }
        if ($this->wants('portions')) {
            array_push($findings, ...$this->segmentFindings());
        }
        if ($this->wants('evenements')) {
            array_push($findings, ...$this->eventFindings());
        }
        if ($this->wants('categories')) {
            array_push($findings, ...$this->categoryFindings());
        }
        if ($this->wants('completude')) {
            array_push($findings, ...$this->completenessFindings());
        }

        // Priorité, puis constats non vides d'abord, puis groupe.
        usort($findings, fn ($a, $b) => [$a['priority'], $a['count'] === 0, self::GROUPS_ORDER[$a['group']] ?? 9]
            <=> [$b['priority'], $b['count'] === 0, self::GROUPS_ORDER[$b['group']] ?? 9]);

        return $findings;
    }

    /** @return list<string> */
    public function notes(): array
    {
        return array_values(array_unique($this->notes));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Gares
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Desserte des gares par les lignes à une ère : [niveau, uniquement touristique].
     * Niveau : 2 = ligne en service ouverte aux voyageurs (ou trafic inconnu),
     * 1 = fret seul / voie de service, 0 = aucune ligne en service (gare absente
     * du résultat = 0).
     *
     * Calculée par StationLineCoverage, partagé avec vff:build-station-eras :
     * la correction R2 et le moteur des gares produisent le même plafond.
     *
     * @return array<int, array{0: int, 1: bool}>
     */
    public function stationLineLevels(int $eraId): array
    {
        return $this->levels[$eraId] ??= $this->coverage->levels($eraId);
    }

    /**
     * Niveau de desserte qui plafonne la gare à cette ère (R2), ou null si la
     * gare échappe au plafond (StationLineCoverage::exemption : réseau non
     * modélisé, ou lignes toutes non datées à une ère historique).
     *
     * @return array{0: ?int, 1: ?string} [niveau, note d'exception]
     */
    private function capLevel(object $era, object $st): array
    {
        $levels = $this->stationLineLevels($era->id);
        $this->modeled[$era->id] ??= $this->coverage->modeled($era->id);
        $exemption = StationLineCoverage::exemption($era->is_current, isset($levels[$st->id]), isset($this->modeled[$era->id][$st->id]),
            $st->status_source ?? null, ! $era->is_current && isset($this->coverage->undatedOnly()[$st->id]),
            isset($this->coverage->linesGone((int) $era->year)[$st->id]), isset($this->coverage->liveUnmodeledTracks()[$st->id]));

        return $exemption !== null ? [null, $exemption] : [$levels[$st->id][0] ?? StationTimeline::LINES_CLOSED, null];
    }

    /** Oublie le cache de desserte (après une modification des tronçons). */
    public function forgetLevels(): void
    {
        $this->levels = [];
        $this->modeled = [];
        $this->coverage = new StationLineCoverage;
    }

    /**
     * Gares présentes à une ère : lignes station_eras ; pour l'ère courante,
     * aussi les gares sans ligne station_eras (affichées avec stations.status).
     *
     * @return list<object{id: int, name: string, slug: string, uic_code: ?string, status: string, has_row: bool, uncertain: bool}>
     */
    private function stationsAtEra(object $era): array
    {
        return array_map(function ($r) {
            $r->id = (int) $r->id;
            $r->has_row = (bool) $r->has_row;
            $r->uncertain = (bool) $r->uncertain;

            return $r;
        }, DB::select(<<<'SQL'
            SELECT s.id, s.name, s.slug, s.uic_code, COALESCE(se.status, s.status) AS status, se.id IS NOT NULL AS has_row,
                   COALESCE(se.uncertain, FALSE) AS uncertain,
                   s.provenance->>'status' AS status_source
            FROM stations s
            LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = :era
            WHERE se.id IS NOT NULL OR CAST(:current AS boolean)
        SQL, ['era' => $era->id, 'current' => $era->is_current ? 'true' : 'false']));
    }

    /**
     * Statut maximal autorisé par R2 pour une gare ouverte, ou null si conforme.
     * (Le cas « ligne uniquement touristique » est signalé à part, pas corrigé.)
     */
    public static function rule2Status(string $status, int $level): ?string
    {
        if (! in_array($status, self::OPEN_STATIONS, true)) {
            return null;
        }

        return match (true) {
            $level <= StationTimeline::LINES_CLOSED => self::CLOSED_STATION,
            $level === StationTimeline::LINES_FREIGHT && $status !== StationTimeline::FREIGHT => StationTimeline::FREIGHT,
            default => null,
        };
    }

    /** @return list<array<string, mixed>> */
    private function stationFindings(): array
    {
        $sncfFlagged = array_flip(array_map(fn ($r) => (string) $r->code_uic,
            DB::select('SELECT DISTINCT code_uic FROM sncf_gares WHERE code_uic IS NOT NULL AND (fret OR voyageurs)')));

        $closed = $this->bucket();
        $freightOnly = $this->bucket();
        $heritage = $this->bucket();
        $sncf = $this->bucket();
        $uncertain = $this->bucket();
        $sncfOk = 0;
        $byStatus = [];

        foreach ($this->scopedEras() as $era) {
            if (! $this->hasSegments($era)) {
                continue;
            }
            $levels = $this->stationLineLevels($era->id);
            foreach ($this->stationsAtEra($era) as $st) {
                [$level, $exemption] = $this->capLevel($era, $st);
                if ($level === null) {
                    // Hors plafond : lignes non datées (statut incertain) ou réseau non modélisé.
                    if ($exemption === StationLineCoverage::NOTE_UNDATED && in_array($st->status, self::OPEN_STATIONS, true)) {
                        $this->add($uncertain, $era->year, ['era' => $era->year, 'id' => $st->id, 'name' => $st->name, 'status' => $st->status]);
                    }

                    continue;
                }
                $heritageOnly = $levels[$st->id][1] ?? false;
                $flagged = $st->uic_code !== null && isset($sncfFlagged[$st->uic_code]);
                if ($era->is_current && $flagged && $level === StationTimeline::LINES_CLOSED && ! in_array($st->status, self::OPEN_STATIONS, true)) {
                    $sncfOk++;
                }
                $target = self::rule2Status($st->status, $level);
                $example = ['era' => $era->year, 'id' => $st->id, 'name' => $st->name, 'slug' => $st->slug,
                    'status' => $st->status, 'expected' => $target, 'row' => $st->has_row ? 'station_eras' : 'stations.status'];
                if ($target === self::CLOSED_STATION) {
                    $this->add($closed, $era->year, $example);
                    $byStatus[$st->status] = ($byStatus[$st->status] ?? 0) + 1;
                    if ($era->is_current && $flagged) {
                        $this->add($sncf, $era->year, $example);
                    }
                } elseif ($target === StationTimeline::FREIGHT) {
                    $this->add($freightOnly, $era->year, $example);
                } elseif ($st->status === StationTimeline::ACTIVE && $level === StationTimeline::LINES_PASSENGER && $heritageOnly) {
                    $this->add($heritage, $era->year, $example + ['expected' => StationTimeline::TOURIST]);
                }
            }
        }

        $labels = ['active' => 'voyageurs', 'tourist' => 'touristiques', 'no_commercial' => 'fret seul'];
        $split = implode(', ', array_map(fn ($k, $v) => "{$v} ".($labels[$k] ?? $k), array_keys($byStatus), $byStatus));

        return [
            $this->finding('stations.open_on_closed_lines', 'gares', 1, 'R2',
                'Gare ouverte alors que toutes ses lignes sont fermées, déposées ou absentes à cette ère',
                $closed, fixable: true, detail: $split ? "Statuts affichés : {$split}. Correction : station_eras (et stations.status à l'ère courante) → fermée." : null),
            $this->finding('stations.passenger_on_freight_only', 'gares', 1, 'R2',
                'Gare voyageurs (ou touristique) alors que ses lignes ne sont ouvertes qu\'au fret',
                $freightOnly, fixable: true, detail: 'Correction : → « fret seul ».'),
            $this->finding('stations.sncf_flag_on_closed_line', 'gares', 1, 'R2',
                'Gare marquée fret/voyageurs par SNCF liste-des-gares, sur des lignes toutes fermées, encore affichée ouverte (ère courante)',
                $sncf, fixable: true, detail: "{$sncfOk} gares dans le même cas sont déjà correctement fermées."),
            $this->finding('stations.uncertain_undated_lines', 'gares', 3, 'R2',
                'Gare ouverte non plafonnée car toutes ses lignes sont non datées (statut incertain, suit ses propres sources)',
                $uncertain, detail: 'Exception à R2 : « absente faute de date » n\'est pas « fermée ». Disparaît dès qu\'une ligne de la gare est datée.'),
            $this->finding('stations.active_on_heritage_only', 'gares', 3, 'R2',
                'Gare voyageurs desservie uniquement par des lignes touristiques (devrait être « touristique »)',
                $heritage, detail: 'Signalé seulement : relancer vff:build-station-eras.'),
            ...$this->noLineNearbyFindings(),
        ];
    }

    /**
     * Gares à plus de 300 m de tout tronçon de l'ère : affichées ouvertes
     * (erreur visible : gare qui flotte sans voie), ou incertaines (règle
     * d'affichage de vff:build-station-eras, cf. StationLineCoverage).
     *
     * @return list<array<string, mixed>>
     */
    private function noLineNearbyFindings(): array
    {
        $open = $this->bucket();
        $uncertain = $this->bucket();
        $closedCount = 0;
        foreach ($this->scopedEras() as $era) {
            if (! $this->hasSegments($era)) {
                continue;
            }
            $rows = DB::select(<<<'SQL'
                SELECT s.id, s.name, s.slug, COALESCE(se.status, s.status) AS status, COALESCE(se.uncertain, FALSE) AS uncertain
                FROM stations s
                LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = :era
                WHERE (se.id IS NOT NULL OR (CAST(:current AS boolean) AND s.status <> 'declassified'))
                  AND NOT EXISTS (
                      SELECT 1 FROM line_segments ls
                      WHERE ls.era_id = :era2 AND ls.geom && ST_Expand(s.geom, 0.005)
                        AND ST_DWithin(ls.geom::geography, s.geom::geography, :radius))
            SQL, ['era' => $era->id, 'era2' => $era->id, 'current' => $era->is_current ? 'true' : 'false', 'radius' => self::NO_LINE_RADIUS_M]);
            foreach ($rows as $r) {
                $ex = ['era' => $era->year, 'id' => (int) $r->id, 'name' => $r->name, 'slug' => $r->slug, 'status' => $r->status];
                if (! in_array($r->status, self::OPEN_STATIONS, true)) {
                    $closedCount++;
                } elseif ($r->uncertain) {
                    $this->add($uncertain, $era->year, $ex);
                } else {
                    $this->add($open, $era->year, $ex);
                }
            }
        }

        return [
            $this->finding('stations.no_line_nearby', 'gares', 2, null,
                'Gare affichée ouverte sans aucun tronçon (même fermé) à moins de '.self::NO_LINE_RADIUS_M.' m à cette ère',
                $open, detail: "Position douteuse ou tracé manquant ; relancer vff:build-station-eras (règle des gares isolées). Gares fermées sans tronçon proche (tracé disparu non reconstitué) : {$closedCount}."),
            $this->finding('stations.isolated_uncertain', 'gares', 3, 'R2',
                'Gare ouverte selon ses sources mais sans aucun tronçon à moins de '.self::NO_LINE_RADIUS_M.' m à cette ère : affichée « incertaine »',
                $uncertain, detail: 'Tronçon local non daté ou daté trop tard, ligne absente de VFF, réseau non modélisé : disparaît quand la voie est reconstituée à cette ère.'),
        ];
    }

    /**
     * Ce que la carte affiche : stations.geojson (GeoExporter) × palette de la
     * couche gares (railStyle.js STATION_STYLES, utilisée par RailMap.svelte).
     *
     * @return list<array<string, mixed>>
     */
    private function displayFindings(): array
    {
        $palette = self::stationPalette();
        $paletteBucket = $this->bucket();
        foreach ($palette['errors'] as $error) {
            $this->add($paletteBucket, null, ['detail' => $error]);
        }

        $mismatch = $this->bucket();
        $missingRow = $this->bucket();
        $hidden = $this->bucket();
        $kinds = [];
        $exporter = app(GeoExporter::class);
        $colorOf = fn (?string $status) => $palette['colors'][$status ?? ''] ?? $palette['fallback'];

        foreach ($this->scopedEras() as $era) {
            $features = $exporter->stationsAsGeoJson($era->year)['features'];
            $rows = [];
            foreach ($this->stationsAtEra($era) as $st) {
                $rows[$st->id] = $st;
            }
            $levels = $this->hasSegments($era) ? $this->stationLineLevels($era->id) : null;
            $emitted = [];
            foreach ($features as $f) {
                $id = (int) $f['id'];
                $emitted[$id] = true;
                $shown = $f['properties']['status'] ?? null;
                $st = $rows[$id] ?? null;
                $expected = $st?->status ?? self::CLOSED_STATION;
                if ($levels !== null && $st !== null && ($level = $this->capLevel($era, $st)[0]) !== null) {
                    $expected = self::rule2Status($expected, $level) ?? $expected;
                }
                // Gare ouverte non confirmée par les voies de l'ère : affichée « incertaine ».
                if ($st !== null && $st->uncertain && in_array($expected, self::OPEN_STATIONS, true)) {
                    $expected = self::UNCERTAIN_STATION;
                }
                $ex = ['era' => $era->year, 'id' => $id, 'name' => $f['properties']['name'] ?? '', 'slug' => $f['properties']['slug'] ?? null,
                    'shown' => $shown, 'expected' => $expected, 'color' => $colorOf($shown), 'expected_color' => $colorOf($expected)];
                if ($colorOf($shown) !== $colorOf($expected)) {
                    $kind = $expected === self::CLOSED_STATION ? 'fermée affichée ouverte' : "{$expected} affichée {$shown}";
                    $kinds[$kind] = ($kinds[$kind] ?? 0) + 1;
                    $this->add($mismatch, $era->year, $ex);
                }
            }
            foreach ($rows as $id => $st) {
                if (! in_array($st->status, self::OPEN_STATIONS, true)) {
                    continue;
                }
                $ex = ['era' => $era->year, 'id' => $id, 'name' => $st->name, 'slug' => $st->slug, 'status' => $st->status];
                if (! $st->has_row) {
                    // Ère courante : gare sans station_eras, statut de fiche = stations.status (non plafonné).
                    $this->add($missingRow, $era->year, $ex + ['on_map' => isset($emitted[$id])]);
                } elseif (! isset($emitted[$id])) {
                    $this->add($hidden, $era->year, $ex);
                }
            }
        }

        $kindsText = implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($kinds), $kinds));

        return [
            $this->finding('stations.palette', 'gares', 1, 'R2',
                'Palette de la couche gares (railStyle.js / RailMap.svelte) : une gare fermée ou inconnue pourrait prendre la couleur d\'une gare ouverte',
                $paletteBucket),
            $this->finding('stations.display_color_mismatch', 'gares', 1, 'R2',
                'Gare affichée sur la carte avec une couleur qui ne correspond pas à son statut (station_eras plafonné par ses lignes)',
                $mismatch, fixable: true, detail: $kindsText ?: null),
            $this->finding('stations.era_status_missing', 'gares', 2, null,
                'Gare ouverte selon stations.status mais sans statut calculé à l\'ère courante (pas de station_eras : statut de fiche non plafonné par ses lignes)',
                $missingRow, detail: 'Relancer vff:build-station-eras après les imports de gares.'),
            $this->finding('stations.open_but_hidden', 'gares', 2, null,
                'Gare ouverte selon station_eras mais absente de stations.geojson',
                $hidden),
        ];
    }

    /**
     * Palette des gares lue dans resources/js/lib/railStyle.js (source unique
     * des couleurs) et vérification que la couche gares de RailMap l'utilise.
     *
     * @return array{colors: array<string, string>, fallback: ?string, errors: list<string>}
     */
    public static function stationPalette(?string $railStylePath = null, ?string $railMapPath = null): array
    {
        $railStylePath ??= resource_path('js/lib/railStyle.js');
        $railMapPath ??= resource_path('js/Components/Map/RailMap.svelte');
        $errors = [];
        $js = @file_get_contents($railStylePath) ?: '';

        // Constantes simples : export const X = '#hex';
        $consts = [];
        preg_match_all("/(?:export\\s+)?const\\s+([A-Z_]+)\\s*=\\s*'(#[0-9a-fA-F]{3,8})'/", $js, $m, PREG_SET_ORDER);
        foreach ($m as $c) {
            $consts[$c[1]] = strtolower($c[2]);
        }
        $block = function (string $name) use ($js): array {
            if (! preg_match('/const\s+'.$name.'\s*=\s*\[(.*?)\];/s', $js, $b)) {
                return [];
            }
            preg_match_all("/key:\\s*'(\\w+)',\\s*color:\\s*([^,}\\s]+)/", $b[1], $entries, PREG_SET_ORDER);

            return array_map(fn ($e) => [$e[1], $e[2]], $entries);
        };
        $resolve = function (string $expr, array $maps) use ($consts): ?string {
            $expr = trim($expr);
            if (preg_match("/^'(#[0-9a-fA-F]{3,8})'$/", $expr, $h)) {
                return strtolower($h[1]);
            }
            if (isset($consts[$expr])) {
                return $consts[$expr];
            }
            if (preg_match('/^(\w+)\.(\w+)$/', $expr, $p)) {
                return $maps[$p[1]][$p[2]] ?? null;
            }

            return null;
        };

        $maps = ['TRAFFIC_COLOR' => [], 'STATUS_COLOR' => []];
        foreach ($block('TRAFFIC_STYLES') as [$key, $expr]) {
            $maps['TRAFFIC_COLOR'][$key] = $resolve($expr, $maps);
        }
        foreach ($block('STATUS_STYLES') as [$key, $expr]) {
            $maps['STATUS_COLOR'][$key] = $resolve($expr, $maps);
        }

        $colors = [];
        foreach ($block('STATION_STYLES') as [$key, $expr]) {
            $colors[$key] = $resolve($expr, $maps);
            if ($colors[$key] === null) {
                $errors[] = "Couleur de gare « {$key} » illisible dans railStyle.js ({$expr}).";
            }
        }
        $fallback = $consts['STATION_CLOSED_COLOR'] ?? null;

        if ($colors === []) {
            $errors[] = 'railStyle.js ne définit pas STATION_STYLES (palette des gares).';
        }
        if ($fallback === null) {
            $errors[] = 'railStyle.js ne définit pas STATION_CLOSED_COLOR (couleur des gares fermées / statut inconnu).';
        }
        foreach (self::OPEN_STATIONS as $open) {
            if ($colors !== [] && ! isset($colors[$open])) {
                $errors[] = "Statut de gare « {$open} » sans couleur dans STATION_STYLES.";
            }
            if ($fallback !== null && ($colors[$open] ?? null) === $fallback) {
                $errors[] = "La couleur des gares fermées ({$fallback}) est aussi celle des gares « {$open} ».";
            }
        }
        if ($colors !== [] && ! isset($colors[self::UNCERTAIN_STATION])) {
            $errors[] = 'Statut de gare « uncertain » (gare incertaine) sans couleur dans STATION_STYLES.';
        }
        foreach (self::OPEN_STATIONS as $open) {
            if (isset($colors[self::UNCERTAIN_STATION], $colors[$open]) && $colors[self::UNCERTAIN_STATION] === $colors[$open]) {
                $errors[] = "La couleur des gares incertaines ({$colors[self::UNCERTAIN_STATION]}) est aussi celle des gares « {$open} ».";
            }
        }
        if (isset($colors[self::CLOSED_STATION]) && $fallback !== null && $colors[self::CLOSED_STATION] !== $fallback) {
            $errors[] = 'STATION_STYLES « declassified » diffère de STATION_CLOSED_COLOR.';
        }

        $svelte = @file_get_contents($railMapPath) ?: '';
        if (! preg_match("/'circle-stroke-color'\\s*:\\s*stationColorExpr\\(\\)/", $svelte)) {
            $errors[] = 'La couche vff-stations-points de RailMap.svelte n\'utilise pas stationColorExpr() (palette partagée, repli « fermée »).';
        }

        return ['colors' => array_filter($colors), 'fallback' => $fallback, 'errors' => $errors];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Portions (line_segments) et lignes
    // ─────────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function segmentFindings(): array
    {
        $eraFilter = $this->eraIdsSql();
        $lgvCodes = 'SELECT code_ligne FROM sncf_line_catlig WHERE catlig ILIKE \'%grande vitesse%\'';

        // R1 : voyageurs seuls hors LGV.
        $passenger = $this->bucket();
        foreach (DB::select(<<<SQL
            SELECT e.year, l.id, l.name, l.slug, l.code, COUNT(*) AS n, ROUND(SUM(COALESCE(ls.length_meters, ST_Length(ls.geom::geography)))) AS m,
                   l.code IN ({$lgvCodes}) AS catlig_lgv
            FROM line_segments ls JOIN lines l ON l.id = ls.line_id JOIN eras e ON e.id = ls.era_id
            WHERE ls.traffic_type = 'passenger' AND NOT l.is_lgv {$eraFilter}
            GROUP BY e.year, l.id ORDER BY m DESC
        SQL) as $r) {
            $this->add($passenger, (int) $r->year, ['era' => (int) $r->year, 'id' => (int) $r->id, 'name' => $r->name, 'code' => $r->code,
                'segments' => (int) $r->n, 'km' => round($r->m / 1000, 1), 'catlig_lgv' => (bool) $r->catlig_lgv], (int) $r->n, (float) $r->m);
        }
        $linesPassenger = $this->bucket();
        foreach (DB::select("SELECT id, name, code, code IN ({$lgvCodes}) AS catlig_lgv FROM lines WHERE type = 'passenger' AND NOT is_lgv ORDER BY name") as $r) {
            $this->add($linesPassenger, null, ['id' => (int) $r->id, 'name' => $r->name, 'code' => $r->code, 'catlig_lgv' => (bool) $r->catlig_lgv]);
        }

        // LGV SNCF (catlig) ou nommées LGV sans is_lgv.
        $lgv = $this->bucket();
        foreach (DB::select(<<<SQL
            SELECT id, name, code, code IN ({$lgvCodes}) AS catlig
            FROM lines WHERE NOT is_lgv AND (code IN ({$lgvCodes}) OR name ~* '\\mLGV\\M')
            ORDER BY name
        SQL) as $r) {
            $this->add($lgv, null, ['id' => (int) $r->id, 'name' => $r->name, 'code' => $r->code, 'signal' => $r->catlig ? 'sncf_line_catlig' : 'nom']);
        }

        // Tronçons orphelins (ligne supprimée sans cascade, ère inconnue).
        $orphans = $this->bucket();
        foreach (DB::select(<<<'SQL'
            SELECT ls.id, ls.line_id, ls.era_id FROM line_segments ls
            LEFT JOIN lines l ON l.id = ls.line_id LEFT JOIN eras e ON e.id = ls.era_id
            WHERE l.id IS NULL OR e.id IS NULL LIMIT 1000
        SQL) as $r) {
            $this->add($orphans, null, ['segment_id' => (int) $r->id, 'line_id' => (int) $r->line_id, 'era_id' => (int) $r->era_id]);
        }

        return [
            $this->finding('segments.passenger_outside_lgv', 'portions', 1, 'R1',
                'Portion « voyageurs seuls » hors LGV (devrait être mixte ; affichée en violet LGV sur la carte)',
                $passenger, fixable: true, unit: 'segments',
                detail: 'Correction : traffic_type → mixed, sauf lignes dont le code SNCF est classé LGV (voir lines.lgv_not_flagged).'),
            $this->finding('lines.type_passenger_outside_lgv', 'portions', 1, 'R1',
                'Ligne typée « passenger » (lines.type) sans être une LGV',
                $linesPassenger, fixable: true, detail: 'Correction : lines.type → mixed (sauf code SNCF LGV).'),
            $this->finding('lines.lgv_not_flagged', 'portions', 1, 'R1',
                'Ligne LGV (sncf_line_catlig « grande vitesse » ou nom LGV) non marquée is_lgv : ni violette, ni « voyageurs seuls »',
                $lgv, detail: 'Relancer vff:classify-lines.'),
            $this->finding('segments.orphan', 'portions', 2, null, 'Tronçon sans ligne ou sans ère', $orphans),
            $this->namingFinding(),
            $this->noCategoryFinding(),
            $this->gapFinding(),
            $this->inServiceButClosedFinding(),
        ];
    }

    private function namingFinding(): array
    {
        $bucket = $this->bucket();
        $shown = array_flip(array_map(fn ($r) => (int) $r->line_id, DB::select(
            'SELECT DISTINCT ls.line_id FROM line_segments ls JOIN eras e ON e.id = ls.era_id WHERE e.is_current')));
        $onMap = 0;
        foreach (DB::select('SELECT id, name, slug, code, network FROM lines ORDER BY name') as $l) {
            $name = trim((string) $l->name);
            $reason = match (true) {
                $name === '' => 'sans nom',
                (bool) preg_match('/\bprès de\b/u', $name) => 'nom dérivé (« près de »)',
                $l->code !== null && PlaceNames::normalize($name) === PlaceNames::normalize($l->code) => 'nom = code',
                default => null,
            };
            if ($reason === null) {
                $p = PlaceNames::parseLabel($name);
                $reason = $p['junk'] ? 'libellé technique' : ($p['generic'] ? 'nom générique' : null);
            }
            if ($reason !== null) {
                $onMap += isset($shown[(int) $l->id]) ? 1 : 0;
                $this->add($bucket, null, ['id' => (int) $l->id, 'name' => $name, 'network' => $l->network, 'reason' => $reason]);
            }
        }

        return $this->finding('lines.no_real_name', 'portions', 3, null,
            'Ligne sans nom réel (nom dérivé « … près de X », générique ou technique)',
            $bucket, detail: "{$onMap} d'entre elles ont des tronçons à l'ère courante.");
    }

    private function noCategoryFinding(): array
    {
        $bucket = $this->bucket();
        foreach (DB::select('SELECT id, name, code FROM lines WHERE network IS NULL ORDER BY name') as $l) {
            $this->add($bucket, null, ['id' => (int) $l->id, 'name' => $l->name, 'code' => $l->code]);
        }

        return $this->finding('lines.no_category', 'portions', 2, null,
            'Ligne sans catégorie de réseau (lines.network vide)', $bucket);
    }

    /**
     * Portion en service à N-10 et à N+10 mais absente (ou fermée sans
     * événement) à N : le moteur décennal ne produit jamais ce cas, c'est le
     * signe d'une ère reconstruite à part ou d'un écrasement par un import.
     */
    private function gapFinding(): array
    {
        $bucket = $this->bucket();
        $kinds = ['absente' => 0, 'fermée sans événement' => 0];
        $eras = $this->eras;
        for ($i = 1; $i < count($eras) - 1; $i++) {
            [$prev, $cur, $next] = [$eras[$i - 1], $eras[$i], $eras[$i + 1]];
            if (! $this->inScope($cur) || ! $this->hasSegments($prev) || ! $this->hasSegments($cur) || ! $this->hasSegments($next)) {
                continue;
            }
            $rows = DB::select(<<<'SQL'
                WITH pieces AS (
                    SELECT p.line_id, COALESCE(p.length_meters, ST_Length(p.geom::geography)) AS m,
                           ST_LineInterpolatePoint(p.geom, 0.5) AS mid
                    FROM line_segments p
                    WHERE p.era_id = :prev AND p.status IN ('in_service', 'single_track')
                      AND COALESCE(p.length_meters, ST_Length(p.geom::geography)) >= :min
                )
                SELECT pc.line_id, l.name, l.slug, cur.active,
                       EXISTS (SELECT 1 FROM line_events le WHERE le.line_id = pc.line_id
                               AND le.type IN ('closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling', 'conversion',
                                               'reopening', 'reopening_passenger', 'reopening_freight')
                               AND EXTRACT(YEAR FROM le.event_date) > :py AND EXTRACT(YEAR FROM le.event_date) <= :ny) AS explained,
                       COUNT(*) AS n, ROUND(SUM(pc.m)) AS m
                FROM pieces pc
                JOIN lines l ON l.id = pc.line_id
                JOIN LATERAL (SELECT 1 FROM line_segments nx WHERE nx.era_id = :next AND nx.line_id = pc.line_id
                              AND nx.status IN ('in_service', 'single_track') AND nx.geom && ST_Expand(pc.mid, 0.0005)
                              AND ST_DWithin(nx.geom::geography, pc.mid::geography, 25) LIMIT 1) n ON TRUE
                LEFT JOIN LATERAL (SELECT BOOL_OR(c.status IN ('in_service', 'single_track')) AS active
                                   FROM line_segments c WHERE c.era_id = :cur AND c.line_id = pc.line_id
                                   AND c.geom && ST_Expand(pc.mid, 0.0005)
                                   AND ST_DWithin(c.geom::geography, pc.mid::geography, 25)) cur ON TRUE
                WHERE cur.active IS NOT TRUE
                GROUP BY pc.line_id, l.name, l.slug, cur.active
                ORDER BY m DESC
            SQL, ['prev' => $prev->id, 'cur' => $cur->id, 'next' => $next->id, 'min' => self::MIN_GAP_PIECE_M,
                'py' => $prev->year, 'ny' => $next->year]);
            foreach ($rows as $r) {
                $kind = $r->active === null ? 'absente' : 'fermée sans événement';
                if ($kind !== 'absente' && $r->explained) {
                    continue;
                }
                $kinds[$kind] += (int) $r->n;
                $this->add($bucket, $cur->year, ['era' => $cur->year, 'line_id' => (int) $r->line_id, 'name' => $r->name, 'kind' => $kind,
                    'pieces' => (int) $r->n, 'km' => round($r->m / 1000, 1), 'around' => "{$prev->year} et {$next->year}"], (int) $r->n, (float) $r->m);
            }
        }

        return $this->finding('segments.unexplained_gap', 'portions', 2, null,
            'Portion en service à N-10 et N+10 mais absente ou fermée à N sans événement qui l\'explique',
            $bucket, unit: 'portions', detail: "absentes : {$kinds['absente']}, fermées sans événement : {$kinds['fermée sans événement']}.");
    }

    /**
     * Tronçons en service d'une ligne que ses événements disent fermée ou pas
     * encore ouverte à cette ère :
     *   - « pas encore ouverte » : toutes ses ouvertures (toutes portions,
     *     toutes sources) sont postérieures à l'ère ;
     *   - « fermée » : la chronologie des événements portant sur TOUTE la ligne
     *     (fermetures, dépose, déclassement, ouvertures) la dit fermée, et
     *     aucune ouverture ou réouverture (même partielle) ne suit la dernière
     *     fermeture. Les événements partiels ne concluent jamais à la fermeture.
     */
    private function inServiceButClosedFinding(): array
    {
        $bucket = $this->bucket();
        $openings = [];
        $whole = [];
        foreach (DB::select(<<<'SQL'
            SELECT line_id, type, event_date::text AS d, source,
                   (section_resolution = 'whole_line' OR (section_from IS NULL AND section_pk_start_m IS NULL AND section_geom IS NULL)) AS whole
            FROM line_events
            WHERE type IN ('opening', 'opening_passenger', 'opening_freight', 'reopening', 'reopening_passenger', 'reopening_freight',
                           'closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling', 'conversion')
        SQL) as $e) {
            $line = (int) $e->line_id;
            if (str_contains($e->type, 'opening')) {
                $openings[$line][] = $e->d;
            }
            if ($e->whole) {
                $whole[$line][] = ['type' => $e->type, 'date' => $e->d, 'source' => $e->source];
            }
        }

        foreach ($this->scopedEras() as $era) {
            if (! $this->hasSegments($era)) {
                continue;
            }
            $year = $era->is_current ? min($era->year, (int) now()->year) : $era->year;
            $limit = sprintf('%04d-12-31', $year);
            $rows = DB::select(<<<'SQL'
                SELECT ls.line_id, l.name, l.network, COUNT(*) AS n, ROUND(SUM(COALESCE(ls.length_meters, ST_Length(ls.geom::geography)))) AS m
                FROM line_segments ls JOIN lines l ON l.id = ls.line_id
                WHERE ls.era_id = ? AND ls.status IN ('in_service', 'single_track')
                GROUP BY ls.line_id, l.name, l.network
            SQL, [$era->id]);
            foreach ($rows as $r) {
                $line = (int) $r->line_id;
                $opens = $openings[$line] ?? [];
                if ($opens === []) {
                    continue; // aucune ouverture datée : rien à opposer
                }
                $first = min($opens);
                $say = null;
                $last = null;
                if ($first > $limit) {
                    $say = 'pas encore ouverte (1re ouverture '.$first.')';
                } else {
                    $closures = array_filter($whole[$line] ?? [], fn ($e) => ! str_contains($e['type'], 'opening') && $e['date'] <= $limit);
                    if ($closures === []) {
                        continue;
                    }
                    $evs = $whole[$line];
                    if (! array_filter($evs, fn ($e) => str_contains($e['type'], 'opening'))) {
                        $evs[] = ['type' => 'opening', 'date' => $first, 'source' => null]; // ouverture connue sur une portion
                    }
                    $state = (new EraTimeline($evs, self::DEFAULT_SERVICES[$r->network ?? 'principal'] ?? 'PF'))->stateAt($year);
                    if ($state === null) {
                        // null = pas encore ouverte (sur ces seuls événements) ou disparue (déposée depuis 10 ans et plus).
                        if (! array_filter($evs, fn ($e) => str_contains($e['type'], 'opening') && $e['date'] <= $limit)) {
                            continue;
                        }
                        $state = ['status' => 'disparue'];
                    }
                    if ($state['status'] === 'in_service') {
                        continue;
                    }
                    $lastDate = max(array_column($closures, 'date'));
                    if (array_filter($opens, fn ($d) => $d > $lastDate && $d <= $limit)) {
                        continue; // réouverture (même partielle) après la dernière fermeture
                    }
                    $say = $state['status'];
                    $lastEvent = array_values(array_filter($closures, fn ($e) => $e['date'] === $lastDate))[0];
                    $last = $lastDate.' '.$lastEvent['type'].' ('.($lastEvent['source'] ?? '?').')';
                }
                $this->add($bucket, $era->year, ['era' => $era->year, 'line_id' => $line, 'name' => $r->name,
                    'events_say' => $say, 'last_closure' => $last, 'km' => round($r->m / 1000, 1)], (int) $r->n, (float) $r->m);
            }
        }

        return $this->finding('segments.in_service_but_closed_by_events', 'portions', 2, null,
            'Tronçons en service d\'une ligne que ses événements disent fermée (fermeture sur toute la ligne) ou pas encore ouverte',
            $bucket, unit: 'segments', detail: 'Arbitrer : statut SNCF / OSM actuel contre événement daté (souvent une fermeture aux voyageurs saisie comme fermeture totale).');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Événements
    // ─────────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function eventFindings(): array
    {
        $ev = <<<'SQL'
            ev AS (
                SELECT e.id, e.line_id, e.type, e.event_date, e.date_precision, e.source, e.section_from, e.section_to,
                       (e.section_resolution = 'whole_line'
                        OR (e.section_from IS NULL AND e.section_pk_start_m IS NULL AND e.section_geom IS NULL)) AS whole,
                       CASE WHEN e.section_geom IS NOT NULL THEN ST_Transform(e.section_geom, 2154) END AS g
                FROM line_events e
            )
        SQL;
        $openings = $this->inList(self::OPENING_TYPES);
        $reopenings = $this->inList([...self::OPENING_TYPES, ...self::REOPENING_TYPES]);
        $closures = $this->inList(self::CLOSURE_TYPES);

        $closureFirst = $this->bucket();
        foreach (DB::select(<<<SQL
            WITH {$ev}
            SELECT c.id, c.line_id, l.name, c.type, c.event_date::text AS d, c.source, MIN(o.event_date)::text AS first_opening
            FROM ev c JOIN lines l ON l.id = c.line_id
            JOIN ev o ON o.line_id = c.line_id AND o.type IN ({$openings}) AND {$this->samePortion('o', 'c')}
            WHERE c.type IN ({$closures})
            GROUP BY c.id, c.line_id, l.name, c.type, c.event_date, c.source
            HAVING BOOL_AND({$this->before('c', 'o')})
            ORDER BY l.name, c.event_date
        SQL) as $r) {
            $this->add($closureFirst, null, ['event_id' => (int) $r->id, 'line_id' => (int) $r->line_id, 'name' => $r->name,
                'type' => $r->type, 'date' => $r->d, 'source' => $r->source, 'first_opening' => $r->first_opening]);
        }

        $dismantlingFirst = $this->bucket();
        foreach (DB::select(<<<SQL
            WITH {$ev}
            SELECT d.id, d.line_id, l.name, d.event_date::text AS d, d.source, MIN(c.event_date)::text AS later_closure
            FROM ev d JOIN lines l ON l.id = d.line_id
            JOIN ev c ON c.line_id = d.line_id AND c.type IN ('closure', 'closure_passenger', 'closure_freight')
                     AND {$this->samePortion('c', 'd')} AND {$this->before('d', 'c')}
            WHERE d.type IN ('dismantling', 'conversion')
              AND NOT EXISTS (SELECT 1 FROM ev r WHERE r.line_id = d.line_id AND r.type IN ({$reopenings})
                              AND {$this->samePortion('r', 'd')} AND {$this->before('d', 'r')} AND NOT {$this->before('c', 'r')})
            GROUP BY d.id, d.line_id, l.name, d.event_date, d.source
            ORDER BY l.name, d.event_date
        SQL) as $r) {
            $this->add($dismantlingFirst, null, ['event_id' => (int) $r->id, 'line_id' => (int) $r->line_id, 'name' => $r->name,
                'date' => $r->d, 'source' => $r->source, 'later_closure' => $r->later_closure]);
        }

        $future = $this->bucket();
        $implausible = $this->bucket();
        foreach (DB::select(<<<'SQL'
            SELECT e.id, e.line_id, l.name, e.type, e.event_date::text AS d, e.source, e.event_date > CURRENT_DATE AS future
            FROM line_events e JOIN lines l ON l.id = e.line_id
            WHERE e.event_date > CURRENT_DATE OR e.event_date < :earliest
            ORDER BY e.event_date
        SQL, ['earliest' => self::EARLIEST_PLAUSIBLE_DATE]) as $r) {
            $example = ['event_id' => (int) $r->id, 'line_id' => (int) $r->line_id,
                'name' => $r->name, 'type' => $r->type, 'date' => $r->d, 'source' => $r->source];
            if ($r->future) {
                $this->add($future, null, $example);
            } else {
                $this->add($implausible, null, $example);
            }
        }

        $unresolved = $this->bucket();
        $split = [];
        foreach (DB::select(<<<'SQL'
            SELECT e.line_id, l.name, e.section_resolution AS res, COUNT(*) AS n
            FROM line_events e JOIN lines l ON l.id = e.line_id
            WHERE e.section_resolution IN ('pending', 'unresolved')
            GROUP BY e.line_id, l.name, e.section_resolution
            ORDER BY n DESC
        SQL) as $r) {
            $split[$r->res] = ($split[$r->res] ?? 0) + (int) $r->n;
            $this->add($unresolved, null, ['line_id' => (int) $r->line_id, 'name' => $r->name, 'resolution' => $r->res, 'events' => (int) $r->n], (int) $r->n);
        }
        $wholeLine = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS n FROM line_events
            WHERE section_resolution IN ('pending', 'unresolved')
              AND section_from IS NULL AND section_pk_start_m IS NULL AND section_geom IS NULL
        SQL)->n;

        return [
            $this->finding('events.closure_before_opening', 'evenements', 2, null,
                'Fermeture (ou déclassement) datée avant toute ouverture de la même portion', $closureFirst, unit: 'événements'),
            $this->finding('events.dismantling_before_closure', 'evenements', 2, null,
                'Dépose datée avant une fermeture de la même portion (sans réouverture entre les deux)', $dismantlingFirst, unit: 'événements',
                detail: 'Souvent une « dépose de la 2e voie » saisie comme dépose de la ligne : le moteur montre alors la ligne déposée trop tôt.'),
            $this->finding('events.future_date', 'evenements', 2, null,
                'Événement daté dans le futur', $future, unit: 'événements'),
            $this->finding('events.implausible_date', 'evenements', 2, null,
                'Événement daté avant '.substr(self::EARLIEST_PLAUSIBLE_DATE, 0, 4).' (avant les premiers chemins de fer français)', $implausible, unit: 'événements'),
            $this->finding('events.unresolved_section', 'evenements', 3, null,
                'Événements dont la portion n\'est pas résolue (ignorés par le moteur décennal)', $unresolved, unit: 'événements',
                detail: sprintf('pending : %d, unresolved : %d, dont %d sans portion (toute la ligne, en attente du résolveur) ; relancer vff:resolve-event-sections.',
                    $split['pending'] ?? 0, $split['unresolved'] ?? 0, $wholeLine)),
        ];
    }

    /** Deux événements portent-ils sur la même portion ? (SQL, alias de la CTE ev) */
    private function samePortion(string $a, string $b): string
    {
        return "({$a}.whole OR {$b}.whole
            OR ({$a}.g IS NOT NULL AND {$b}.g IS NOT NULL AND {$a}.g && ST_Expand({$b}.g, 30)
                AND ST_Length(ST_Intersection({$a}.g, ST_Buffer({$b}.g, 30))) >= LEAST(200, 0.3 * ST_Length({$a}.g)))
            OR ({$a}.g IS NULL AND {$b}.g IS NULL AND {$a}.section_from IS NOT NULL
                AND {$a}.section_from = {$b}.section_from AND {$a}.section_to IS NOT DISTINCT FROM {$b}.section_to))";
    }

    /** $a strictement avant $b, à la précision la plus grossière des deux dates. */
    private function before(string $a, string $b): string
    {
        return "(CASE WHEN {$a}.date_precision = 'year' OR {$b}.date_precision = 'year'
                     THEN EXTRACT(YEAR FROM {$a}.event_date) < EXTRACT(YEAR FROM {$b}.event_date)
                 WHEN {$a}.date_precision = 'month' OR {$b}.date_precision = 'month'
                     THEN DATE_TRUNC('month', {$a}.event_date) < DATE_TRUNC('month', {$b}.event_date)
                 ELSE {$a}.event_date < {$b}.event_date END)";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Catégories de réseau
    // ─────────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function categoryFindings(): array
    {
        $overlap = $this->bucket();
        foreach ($this->scopedEras() as $era) {
            if (! $this->hasSegments($era)) {
                continue;
            }
            foreach ($this->privateOverRfn($era->id) as $r) {
                // km du constat : longueur de ligne privée recouverte, une seule fois par ère et par ligne
                // privée (portée par sa paire la plus longue), même si deux lignes RFN partagent la voie.
                $this->add($overlap, $era->year, ['era' => $era->year, 'line_id' => (int) $r->line_id, 'name' => $r->name, 'network' => $r->network,
                    'rfn_line_id' => (int) $r->rfn_id, 'rfn_name' => $r->rfn_name, 'rfn_code' => $r->rfn_code, 'km' => round($r->m / 1000, 1)],
                    1, $r->first ? (float) $r->line_m : 0.0);
            }
        }

        $rfn = $this->bucket();
        foreach (DB::select(<<<'SQL'
            SELECT l.id, l.name, l.code, l.network FROM lines l
            WHERE l.network IS DISTINCT FROM 'principal'
              AND l.code IN (SELECT code_ligne FROM sncf_line_geometries)
            ORDER BY l.name
        SQL) as $r) {
            $this->add($rfn, null, ['id' => (int) $r->id, 'name' => $r->name, 'code' => $r->code, 'network' => $r->network]);
        }
        $historic = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS n FROM lines l
            WHERE l.code IS NOT NULL AND l.network IS DISTINCT FROM 'principal'
              AND l.code NOT IN (SELECT code_ligne FROM sncf_line_geometries)
        SQL)->n;

        return [
            $this->finding('lines.private_over_rfn', 'categories', 2, null,
                'Ligne particulière ou militaire superposée (≥ '.self::MIN_OVERLAP_M.' m) à une ligne RFN en service', $overlap, unit: 'paires',
                detail: 'Doublon de tracé ou embranchement mal rattaché : la portion devrait appartenir à la ligne RFN. '
                    .'km = longueur de ligne privée recouverte, comptée une fois par ère même si plusieurs lignes RFN partagent la voie.'),
            $this->finding('lines.rfn_not_principal', 'categories', 2, null,
                'Ligne du RFN actuel (code présent dans les tronçons SNCF) classée autrement que « principal »', $rfn,
                detail: "{$historic} lignes à code SNCF historique (hors RFN actuel) sont classées hors principal : normal pour d'anciennes lignes secondaires."),
        ];
    }

    /**
     * Paires (ligne particulière ou militaire, ligne RFN en service) superposées
     * à une ère, avec la longueur recouverte.
     *
     * Mesure par UNION, pas par somme de paires de tronçons : une ligne RFN
     * dessinée par plusieurs tronçons qui se recouvrent (une voie par sens à
     * 4 m d'écart, tracés de deux sources) ou dont les tampons de 15 m se
     * chevauchent aux raccords faisait compter la même portion privée deux fois
     * ou plus (jusqu'à dépasser la longueur de la ligne privée). Ici : union des
     * tronçons privés proches ∩ union des tampons des tronçons RFN proches.
     * line_m : même mesure toutes lignes RFN confondues (portion privée comptée
     * une fois même si deux lignes RFN partagent la voie), sur la ligne first.
     *
     * @return list<object{line_id: int, name: string, network: string, rfn_id: int, rfn_name: string, rfn_code: string, m: float, line_m: float, first: bool}>
     */
    private function privateOverRfn(int $eraId): array
    {
        return DB::select(<<<'SQL'
            WITH near AS MATERIALIZED (
                SELECT p.line_id, r.line_id AS rfn_id, p.id AS pid, r.id AS rid
                FROM line_segments p
                JOIN lines pl ON pl.id = p.line_id AND pl.network IN ('particulier', 'militaire')
                JOIN line_segments r ON r.era_id = p.era_id AND r.line_id <> p.line_id
                     AND r.status IN ('in_service', 'single_track') AND r.geom && ST_Expand(p.geom, 0.0003)
                     AND ST_DWithin(p.geom::geography, r.geom::geography, 15)
                JOIN lines rl ON rl.id = r.line_id AND rl.network = 'principal' AND rl.code IS NOT NULL
                WHERE p.era_id = :era
            ),
            pp AS (
                SELECT n.line_id, n.rfn_id, ST_Union(ST_Transform(s.geom, 2154)) AS g
                FROM (SELECT DISTINCT line_id, rfn_id, pid FROM near) n JOIN line_segments s ON s.id = n.pid
                GROUP BY n.line_id, n.rfn_id
            ),
            rr AS (
                SELECT n.line_id, n.rfn_id, ST_Union(ST_Buffer(ST_Transform(s.geom, 2154), 15)) AS g
                FROM (SELECT DISTINCT line_id, rfn_id, rid FROM near) n JOIN line_segments s ON s.id = n.rid
                GROUP BY n.line_id, n.rfn_id
            ),
            pairs AS MATERIALIZED (
                SELECT pp.line_id, pp.rfn_id, pp.g AS pg, rr.g AS rg, ST_Length(ST_Intersection(pp.g, rr.g)) AS m
                FROM pp JOIN rr USING (line_id, rfn_id)
            ),
            kept AS (SELECT * FROM pairs WHERE m >= :min),
            per_line AS (
                SELECT line_id, ST_Length(ST_Intersection(ST_Union(pg), ST_Union(rg))) AS line_m FROM kept GROUP BY line_id
            )
            SELECT k.line_id, pl.name, pl.network, k.rfn_id, rl.name AS rfn_name, rl.code AS rfn_code,
                   ROUND(k.m::numeric) AS m, ROUND(t.line_m::numeric) AS line_m,
                   ROW_NUMBER() OVER (PARTITION BY k.line_id ORDER BY k.m DESC, k.rfn_id) = 1 AS first
            FROM kept k
            JOIN per_line t ON t.line_id = k.line_id
            JOIN lines pl ON pl.id = k.line_id
            JOIN lines rl ON rl.id = k.rfn_id
            ORDER BY k.m DESC, k.line_id, k.rfn_id
        SQL, ['era' => $eraId, 'min' => self::MIN_OVERLAP_M]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Complétude des décennies
    // ─────────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function completenessFindings(): array
    {
        return [
            $this->networkKmFinding(),
            $this->undatedLinesFinding(),
            $this->vanishedFinding(1930, 1950),
            // Excédent de la dernière ère historique sur l'ère courante : fermetures non datées.
            $this->vanishedFinding($this->lastHistoricalYear(), 0, inactive: true),
        ];
    }

    /**
     * km de lignes en service par ère et par catégorie, comparés aux ordres de
     * grandeur historiques (HISTORICAL_KM). Somme des tronçons : deux lignes
     * qui partagent un tracé comptent deux fois.
     */
    private function networkKmFinding(): array
    {
        $km = [];
        foreach (DB::select(<<<'SQL'
            SELECT ls.era_id, COALESCE(l.network, '?') AS network,
                   SUM(COALESCE(ls.length_meters, ST_Length(ls.geom::geography))) / 1000.0 AS km
            FROM line_segments ls JOIN lines l ON l.id = ls.line_id
            WHERE ls.status IN ('in_service', 'single_track')
            GROUP BY ls.era_id, l.network
        SQL) as $r) {
            $km[(int) $r->era_id][$r->network] = (float) $r->km;
        }

        $bucket = $this->bucket();
        $table = [];
        $networks = ['principal', 'secondaire', 'militaire', 'particulier'];
        foreach ($this->scopedEras() as $era) {
            $row = ['era' => $era->year];
            foreach ($networks as $net) {
                $have = round($km[$era->id][$net] ?? 0);
                $ref = self::HISTORICAL_KM[$net][$era->year] ?? null;
                $row[$net] = (int) $have;
                if ($ref === null) {
                    continue;
                }
                $row[$net.'_ref'] = isset($ref['km']) ? $ref['km'] : '≤ '.$ref['max'];
                $verdict = null;
                if (isset($ref['km'])) {
                    $ratio = $ref['km'] > 0 ? $have / $ref['km'] : 0;
                    $row[$net.'_ratio'] = round($ratio, 2);
                    if (abs($ratio - 1) > self::COMPLETENESS_TOLERANCE && abs($have - $ref['km']) >= self::COMPLETENESS_MIN_GAP_KM) {
                        $verdict = $ratio < 1 ? 'manque '.number_format($ref['km'] - $have, 0, ',', ' ').' km' : 'excédent '.number_format($have - $ref['km'], 0, ',', ' ').' km';
                    }
                } elseif ($have > $ref['max']) {
                    $verdict = 'excédent : '.number_format($have, 0, ',', ' ').' km pour un réseau quasi disparu (≤ '.$ref['max'].')';
                }
                if ($verdict !== null) {
                    $this->add($bucket, $era->year, ['era' => $era->year, 'network' => $net, 'km' => (int) $have,
                        'reference' => $row[$net.'_ref'], 'ratio' => $row[$net.'_ratio'] ?? null, 'verdict' => $verdict]);
                }
            }
            $table[] = $row;
        }

        $f = $this->finding('completeness.network_km', 'completude', 2, null,
            'km en service d\'une ère et d\'une catégorie de réseau à plus de 25 % de l\'ordre de grandeur historique',
            $bucket, unit: 'cases ère × catégorie',
            detail: 'principal = intérêt général, secondaire = intérêt local / tramways / voie métrique ; somme des tronçons en service (doublons de tracé possibles). Tableau complet : clé « table » du JSON.');
        $f['table'] = $table;

        return $f;
    }

    /**
     * Lignes qui ont un tracé de référence mais AUCUN tronçon dans les ères
     * historiques, faute d'ouverture datée exploitable par le moteur décennal.
     */
    private function undatedLinesFinding(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT l.id, l.name, l.network, r.length_m,
                   EXISTS (SELECT 1 FROM line_events e WHERE e.line_id = l.id AND e.section_resolution IN ('pending', 'unresolved')) AS waiting,
                   EXISTS (SELECT 1 FROM line_events e WHERE e.line_id = l.id) AS has_events,
                   EXISTS (SELECT 1 FROM line_events e WHERE e.line_id = l.id
                           AND (e.type LIKE 'opening%' OR e.type LIKE 'reopening%')) AS has_opening,
                   (SELECT MIN(e.event_date) FROM line_events e WHERE e.line_id = l.id
                           AND (e.type LIKE 'opening%' OR e.type LIKE 'reopening%')) > MAKE_DATE(:last, 12, 31) AS opened_later,
                   EXISTS (SELECT 1 FROM source_traces st WHERE st.line_id = l.id AND st.opened_year IS NOT NULL) AS dated_trace
            FROM lines l
            JOIN line_reference_geometries r ON r.line_id = l.id
            WHERE NOT EXISTS (SELECT 1 FROM line_segments ls JOIN eras e ON e.id = ls.era_id
                              WHERE ls.line_id = l.id AND NOT e.is_current)
            ORDER BY r.length_m DESC
        SQL, ['last' => $this->lastHistoricalYear()]);
        $bucket = $this->bucket();
        $split = ['en attente de résolution' => [0, 0], 'événements sans ouverture exploitable' => [0, 0], 'aucune date' => [0, 0]];
        $datedTraces = 0;
        $later = 0;
        foreach ($rows as $r) {
            if ($r->opened_later) {
                $later++; // ouverte après la dernière ère historique : normal

                continue;
            }
            $kind = match (true) {
                $r->waiting => 'en attente de résolution',
                $r->has_events => 'événements sans ouverture exploitable',
                default => 'aucune date',
            };
            $split[$kind][0]++;
            $split[$kind][1] += (int) $r->length_m;
            if ($kind === 'aucune date' && $r->dated_trace) {
                $datedTraces++;
            }
            $this->add($bucket, null, ['line_id' => (int) $r->id, 'name' => $r->name, 'network' => $r->network,
                'km' => round($r->length_m / 1000, 1), 'kind' => $kind, 'opening_event' => (bool) $r->has_opening], 1, (float) $r->length_m);
        }
        $detail = implode(' ; ', array_map(fn ($k, $v) => sprintf('%s : %d lignes, %s km', $k, $v[0], number_format($v[1] / 1000, 0, ',', ' ')),
            array_keys($split), $split));

        $f = $this->finding('completeness.undated_lines', 'completude', 2, null,
            'Ligne avec un tracé de référence mais aucun tronçon dans les ères historiques (pas d\'ouverture datée exploitable)',
            $bucket, unit: 'lignes',
            detail: $detail.". Parmi les « aucune date », {$datedTraces} ont un tracé source daté (source_traces.opened_year) exploitable. {$later} lignes ouvertes après la dernière ère historique sont exclues.");
        $f['split'] = array_map(fn ($v) => ['lines' => $v[0], 'km' => round($v[1] / 1000)], $split);

        return $f;
    }

    /**
     * Portions qui disparaissent (ou cessent d'être en service) entre deux
     * ères sans événement de fermeture EXPLOITABLE par le moteur décennal sur
     * leur ligne (fermeture, fret, déclassement, dépose, reconversion daté au
     * plus tard en $to, portion résolue ou toute la ligne).
     *   $inactive = false : présente (tout statut) en $from, absente en $to
     *                       (aucun tronçon d'aucune ligne à moins de 25 m) ;
     *   $inactive = true  : en service en $from, absente ou plus en service en $to.
     * Détail : ligne sans aucun événement de fermeture, ou seulement des
     * événements non résolus (ignorés par le moteur).
     */
    private function vanishedFinding(int $fromYear, int $toYear, bool $inactive = false): array
    {
        $to = $toYear === 0 ? $this->currentEra() : $this->eraByYear($toYear);
        $toYear = $to?->year ?? $toYear;
        $toLabel = $to?->is_current ? "l'ère courante ({$toYear})" : (string) $toYear;
        $key = $inactive ? "completeness.closed_{$fromYear}_{$toYear}" : "completeness.vanished_{$fromYear}_{$toYear}";
        $label = $inactive
            ? "Portion en service en {$fromYear} mais fermée ou absente dans {$toLabel} sans événement de fermeture exploitable"
            : "Portion présente en {$fromYear} mais absente en {$toYear} sans événement de fermeture exploitable";
        $bucket = $this->bucket();
        $from = $this->eraByYear($fromYear);
        if (! $from || ! $to || ! $this->hasSegments($from) || ! $this->hasSegments($to)
            || ($this->onlyYear !== null && ! in_array($this->onlyYear, [$fromYear, $toYear], true))) {
            return $this->finding($key, 'completude', 2, null, $label, $bucket, unit: 'portions');
        }
        $limitYear = $to->is_current ? min($to->year, (int) now()->year) : $to->year;
        $rows = DB::select(<<<'SQL'
            WITH pieces AS (
                SELECT p.line_id, p.status, COALESCE(p.length_meters, ST_Length(p.geom::geography)) AS m,
                       ST_LineInterpolatePoint(p.geom, 0.5) AS mid
                FROM line_segments p
                WHERE p.era_id = :from AND COALESCE(p.length_meters, ST_Length(p.geom::geography)) >= :min
                  AND (NOT CAST(:inactive1 AS boolean) OR p.status IN ('in_service', 'single_track'))
            ),
            closures AS (
                SELECT le.line_id,
                       BOOL_OR(le.section_resolution IN ('pk', 'places', 'trace', 'whole_line')
                               OR (le.section_resolution IS NULL AND le.section_from IS NULL AND le.section_pk_start_m IS NULL)) AS usable
                FROM line_events le
                WHERE le.type IN ('closure', 'closure_freight', 'declassification', 'dismantling', 'conversion')
                  AND le.event_date <= MAKE_DATE(:limit, 12, 31)
                GROUP BY le.line_id
            )
            SELECT pc.line_id, l.name, l.network, cl.line_id IS NOT NULL AS unresolved_only,
                   COUNT(*) AS n, ROUND(SUM(pc.m)) AS m, STRING_AGG(DISTINCT pc.status, ', ') AS statuses,
                   BOOL_AND(NOT cur.present) AS all_absent
            FROM pieces pc
            JOIN lines l ON l.id = pc.line_id
            LEFT JOIN closures cl ON cl.line_id = pc.line_id
            JOIN LATERAL (SELECT COUNT(*) > 0 AS present, BOOL_OR(c.status IN ('in_service', 'single_track')) AS active
                               FROM line_segments c WHERE c.era_id = :to AND c.geom && ST_Expand(pc.mid, 0.0005)
                               AND ST_DWithin(c.geom::geography, pc.mid::geography, 25)) cur ON TRUE
            WHERE (cl.line_id IS NULL OR NOT cl.usable)
              AND (NOT cur.present OR (CAST(:inactive2 AS boolean) AND cur.active IS NOT TRUE))
            GROUP BY pc.line_id, l.name, l.network, cl.line_id
            ORDER BY m DESC
        SQL, ['from' => $from->id, 'to' => $to->id, 'min' => self::MIN_GAP_PIECE_M, 'limit' => $limitYear,
            'inactive1' => $inactive ? 'true' : 'false', 'inactive2' => $inactive ? 'true' : 'false']);
        $split = ['aucun événement de fermeture' => [0, 0.0], 'fermeture non résolue (ignorée)' => [0, 0.0]];
        $byNetwork = [];
        foreach ($rows as $r) {
            $kind = $r->unresolved_only ? 'fermeture non résolue (ignorée)' : 'aucun événement de fermeture';
            $split[$kind][0] += (int) $r->n;
            $split[$kind][1] += (float) $r->m;
            $byNetwork[$r->network ?? '?'] = ($byNetwork[$r->network ?? '?'] ?? 0) + (float) $r->m;
            $this->add($bucket, $toYear, ['line_id' => (int) $r->line_id, 'name' => $r->name, 'network' => $r->network,
                'pieces' => (int) $r->n, 'km' => round($r->m / 1000, 1), "status_{$fromYear}" => $r->statuses,
                "in_{$toYear}" => $r->all_absent ? 'absente' : 'fermée ou partiellement absente', 'kind' => $kind], (int) $r->n, (float) $r->m);
        }
        arsort($byNetwork);
        $detail = count($rows).' lignes. '.implode(' ; ', array_map(fn ($k, $v) => sprintf('%s : %d portions, %s km', $k, $v[0], number_format($v[1] / 1000, 0, ',', ' ')),
            array_keys($split), $split)).'. Par catégorie : '.implode(', ', array_map(fn ($k, $v) => $k.' '.number_format($v / 1000, 0, ',', ' ').' km',
                array_keys($byNetwork), $byNetwork)).'. À dater (fermeture) ou à résoudre (vff:resolve-event-sections).';

        return $this->finding($key, 'completude', 2, null, $label, $bucket, unit: 'portions', detail: $detail);
    }

    private function currentEra(): ?object
    {
        foreach ($this->eras as $era) {
            if ($era->is_current) {
                return $era;
            }
        }

        return null;
    }

    private function lastHistoricalYear(): int
    {
        $years = array_map(fn ($e) => $e->year, array_filter($this->eras, fn ($e) => ! $e->is_current));

        return $years ? max($years) : 0;
    }

    private function eraByYear(int $year): ?object
    {
        foreach ($this->eras as $era) {
            if ($era->year === $year) {
                return $era;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Corrections (R1 et R2 uniquement)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * R2 : gares plus actives que leurs lignes → plafonnées (fermée, ou fret
     * seul), dans station_eras, line_stops.effective_status et, à l'ère
     * courante, stations.status. Une ère sans tronçon n'est pas touchée.
     *
     * @param  callable(array<string, mixed>): void  $log
     */
    public function fixStationsRule2(callable $log): int
    {
        $this->segmentCounts = [];
        foreach (DB::select('SELECT era_id, COUNT(*) AS n FROM line_segments GROUP BY era_id') as $r) {
            $this->segmentCounts[(int) $r->era_id] = (int) $r->n;
        }
        $total = 0;
        foreach ($this->scopedEras() as $era) {
            if (! $this->hasSegments($era)) {
                continue;
            }
            $changes = [];
            foreach ($this->stationsAtEra($era) as $st) {
                $level = $this->capLevel($era, $st)[0];
                if ($level === null) {
                    continue; // hors plafond (lignes non datées, réseau non modélisé)
                }
                $target = self::rule2Status($st->status, $level);
                if ($target !== null) {
                    $changes[] = [$st, $target, $level];
                }
            }
            DB::transaction(function () use ($era, $changes, $log, &$total) {
                foreach ($changes as [$st, $target, $level]) {
                    $note = $level === StationTimeline::LINES_CLOSED
                        ? 'Plus aucune ligne en service à cette époque'
                        : 'Lignes ouvertes au seul fret à cette époque';
                    $reason = $level === StationTimeline::LINES_CLOSED
                        ? 'R2 : toutes les lignes de la gare sont fermées, déposées ou absentes à cette ère'
                        : 'R2 : les lignes de la gare ne sont ouvertes qu\'au fret à cette ère';
                    if ($st->has_row) {
                        DB::update(<<<'SQL'
                            UPDATE station_eras SET status = ?, updated_at = NOW(),
                                   note = CASE WHEN note IS NULL OR note = '' THEN ? WHEN POSITION(? IN note) > 0 THEN note ELSE ? || ' · ' || note END
                            WHERE station_id = ? AND era_id = ?
                        SQL, [$target, $note, $note, $note, $st->id, $era->id]);
                        $log(['rule' => 'R2', 'table' => 'station_eras', 'station_id' => $st->id, 'name' => $st->name, 'era' => $era->year,
                            'field' => 'status', 'before' => $st->status, 'after' => $target, 'reason' => $reason]);
                        $stops = DB::update('UPDATE line_stops SET effective_status = ?, updated_at = NOW() WHERE station_id = ? AND era_id = ? AND effective_status <> ?',
                            [$target, $st->id, $era->id, $target]);
                        if ($stops) {
                            $log(['rule' => 'R2', 'table' => 'line_stops', 'station_id' => $st->id, 'era' => $era->year,
                                'field' => 'effective_status', 'after' => $target, 'rows' => $stops, 'reason' => $reason]);
                        }
                    }
                    if ($era->is_current) {
                        $before = DB::table('stations')->where('id', $st->id)->value('status');
                        if ($before !== $target) {
                            DB::table('stations')->where('id', $st->id)->update(['status' => $target, 'updated_at' => now()]);
                            $log(['rule' => 'R2', 'table' => 'stations', 'station_id' => $st->id, 'name' => $st->name, 'era' => $era->year,
                                'field' => 'status', 'before' => $before, 'after' => $target, 'reason' => $reason]);
                        }
                    }
                    $total++;
                }
            });
        }

        return $total;
    }

    /**
     * R1 : « voyageurs seuls » hors LGV → mixte (tronçons et lines.type).
     * Les lignes dont le code SNCF est classé LGV (catlig) ne sont PAS
     * touchées : c'est leur drapeau is_lgv qui manque (vff:classify-lines).
     *
     * @param  callable(array<string, mixed>): void  $log
     */
    public function fixPassengerRule1(callable $log): int
    {
        $lgvCodes = 'SELECT code_ligne FROM sncf_line_catlig WHERE catlig ILIKE \'%grande vitesse%\'';
        $eraFilter = $this->eraIdsSql();
        $n = 0;
        DB::transaction(function () use ($lgvCodes, $eraFilter, $log, &$n) {
            $rows = DB::select(<<<SQL
                UPDATE line_segments ls SET traffic_type = 'mixed', updated_at = NOW()
                FROM lines l, eras e
                WHERE l.id = ls.line_id AND e.id = ls.era_id AND ls.traffic_type = 'passenger' AND NOT l.is_lgv
                  AND (l.code IS NULL OR l.code NOT IN ({$lgvCodes}))
                  {$eraFilter}
                RETURNING ls.id, ls.line_id, l.name, e.year
            SQL);
            foreach ($rows as $r) {
                $log(['rule' => 'R1', 'table' => 'line_segments', 'segment_id' => (int) $r->id, 'line_id' => (int) $r->line_id, 'name' => $r->name,
                    'era' => (int) $r->year, 'field' => 'traffic_type', 'before' => 'passenger', 'after' => 'mixed',
                    'reason' => 'R1 : « si y\'a du passager, y\'a du fret » ; voyageurs seuls réservé aux LGV']);
            }
            // lines.type n'est pas daté : corrigé seulement sans --era.
            $lines = $this->onlyYear !== null ? [] : DB::select(<<<SQL
                UPDATE lines SET type = 'mixed', updated_at = NOW()
                WHERE type = 'passenger' AND NOT is_lgv AND (code IS NULL OR code NOT IN ({$lgvCodes}))
                RETURNING id, name
            SQL);
            foreach ($lines as $r) {
                $log(['rule' => 'R1', 'table' => 'lines', 'line_id' => (int) $r->id, 'name' => $r->name,
                    'field' => 'type', 'before' => 'passenger', 'after' => 'mixed',
                    'reason' => 'R1 : ligne hors LGV ouverte aux voyageurs = mixte']);
            }
            $n = count($rows) + count($lines);
        });

        return $n;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Outils
    // ─────────────────────────────────────────────────────────────────────

    private function wants(string $group): bool
    {
        return $this->groups === null || in_array($group, $this->groups, true);
    }

    /** @return list<object> */
    private function scopedEras(): array
    {
        return array_values(array_filter($this->eras, fn ($e) => $this->inScope($e)));
    }

    private function inScope(object $era): bool
    {
        return $this->onlyYear === null || $era->year === $this->onlyYear;
    }

    private function hasSegments(object $era): bool
    {
        return ($this->segmentCounts[$era->id] ?? 0) > 0;
    }

    private function eraIdsSql(): string
    {
        if ($this->onlyYear === null) {
            return '';
        }
        $ids = array_map(fn ($e) => $e->id, $this->scopedEras()) ?: [0];

        return 'AND ls.era_id IN ('.implode(',', $ids).')';
    }

    /** @param  list<string>  $values */
    private function inList(array $values): string
    {
        return implode(', ', array_map(fn ($v) => "'".str_replace("'", "''", $v)."'", $values));
    }

    /** @return array{count: int, m: float, by_era: array<int, int>, examples: list<array<string, mixed>>} */
    private function bucket(): array
    {
        return ['count' => 0, 'm' => 0.0, 'by_era' => [], 'examples' => []];
    }

    private function add(array &$bucket, ?int $year, array $example, int $count = 1, float $meters = 0.0): void
    {
        $bucket['count'] += $count;
        $bucket['m'] += $meters;
        if ($year !== null) {
            $bucket['by_era'][$year] = ($bucket['by_era'][$year] ?? 0) + $count;
        }
        if (count($bucket['examples']) < self::MAX_EXAMPLES) {
            $bucket['examples'][] = $example;
        }
    }

    private function finding(string $key, string $group, int $priority, ?string $rule, string $label, array $bucket,
        bool $fixable = false, ?string $detail = null, string $unit = 'éléments'): array
    {
        ksort($bucket['by_era']);

        return [
            'key' => $key,
            'group' => $group,
            'priority' => $priority,
            'rule' => $rule,
            'label' => $label,
            'count' => $bucket['count'],
            'unit' => $unit,
            'km' => $bucket['m'] > 0 ? round($bucket['m'] / 1000, 1) : null,
            'by_era' => $bucket['by_era'],
            'detail' => $detail,
            'fixable' => $fixable,
            'examples' => $bucket['examples'],
        ];
    }
}
