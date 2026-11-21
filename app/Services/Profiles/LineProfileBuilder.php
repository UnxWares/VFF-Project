<?php

namespace App\Services\Profiles;

use App\Models\Line;
use App\Models\LineProfile;
use Illuminate\Support\Facades\DB;

/**
 * Calcul du profil en long d'une ligne (commande vff:compute-line-profiles), méthode du 21/11/2026 :
 *
 * 1. parcours : le tracé de RÉFÉRENCE de la ligne entière (`line_reference_parts`, toutes époques,
 *    lignes fermées et déposées comprises ; à défaut, les tronçons de toutes les ères hors voies de
 *    service), assemblé de bout en bout par ProfileChain, morceaux séparés par des trous compris
 *    (les trous restent des trous : aucune altitude n'y est mesurée), puis orienté du plus petit
 *    PK officiel des gares placées dessus vers le plus grand ;
 * 2. points tous les 100 à 250 m selon la longueur (ProfilePath::stepFor) ;
 * 3. altitude de la VOIE quand un tronçon de la BD TOPO passe à moins de TRACK_TOLERANCE_M du
 *    point (géométrie 3D, interpolée le long du tronçon) ; sinon altitude du terrain (RGE ALTI),
 *    sauf dans un tunnel ou sur un pont de la BD TOPO sans altitude : interpolation entre les deux
 *    têtes de l'ouvrage, puisque le modèle de terrain y donne le dessus de la montagne ou le fond
 *    de la vallée. Mesure du 2026-11-19 sur Saint-Gervais - Vallorcine : 90 ‰ avec la BD TOPO
 *    (valeur publiée de la ligne), 110 ‰ avec le seul modèle de terrain. Rien hors de France ;
 * 4. aberrations effacées puis interpolées, extrêmes, dénivelés et déclivité la plus forte
 *    (ProfileStats ; jamais publiée sur une ligne à crémaillère, RackRailways) ;
 * 5. PK de chaque point calé sur les PK officiels des gares (PkCalibration), toutes les gares de
 *    la ligne (fermées comprises) placées sur le profil, points enregistrés un par un dans
 *    `line_profile_points`.
 *
 * Lecture seule sur les tables du moteur ; seules `line_profiles` et `line_profile_points` sont
 * écrites. Une ligne dont le profil ne peut plus être calculé perd l'ancien : il ne correspond
 * plus à son tracé.
 */
final class LineProfileBuilder
{
    /** Distance au-delà de laquelle un tronçon de la BD TOPO n'est pas rattaché au point du parcours. */
    public const TRACK_TOLERANCE_M = 20.0;

    /** Valeur de `line_profiles.source` (registre des droits : fournisseur `ign`). */
    public const SOURCE = 'bdtopo-rge-alti';

    /** Écart maximal entre une gare et le parcours pour qu'elle serve au sens et au calage du PK. */
    private const STATION_MAX_OFFSET_M = 500.0;

    /**
     * Source du tracé sous chaque point : `source` du morceau du référentiel le plus proche, celle
     * qui définit le morceau, comme `line_segments.source` que lit la règle des droits du reste de
     * l'API (LineTraceExport). Un morceau archeoferroviaire recalé sur OpenStreetMap reste
     * archeoferroviaire : prudent, et identique aux exports de tracés. Complété par une clause WHERE.
     */
    public const TRACE_SOURCE_SQL = <<<'SQL'
        UPDATE line_profile_points p SET trace_source = (
            SELECT r.source FROM line_reference_parts r WHERE r.line_id = p.line_id
            ORDER BY r.geom <-> p.geom LIMIT 1
        )
        SQL;

    /** Marge autour d'un point pour choisir les tuiles du cache (plus large que TRACK_TOLERANCE_M). */
    private const TILE_MARGIN_DEG = 0.0005;

    /** Tuiles de la BD TOPO gardées en mémoire (le parcours les traverse dans l'ordre). */
    private const TILES_IN_MEMORY = 16;

    /** Origines de PK des gares qui servent au calage (les PK projetés sur le tracé sont exclus). */
    private const PK_ANCHOR_SOURCES = ['sncf', 'lignes-oubliees', 'wikipedia_schema', 'correction'];

    /** @var array<string, TrackIndex> tuiles chargées, la plus récente en dernier */
    private array $tiles = [];

    /** Motif du dernier profil non calculé par compute() : no_trace, outside_france, no_altitude. */
    private ?string $skipReason = null;

    public function __construct(private readonly GeoplateformeClient $client) {}

    /** Appels réseau faits aux services de l'IGN depuis la création du service. */
    public function networkCalls(): int
    {
        return $this->client->networkCalls();
    }

    /**
     * Ce que le calcul ferait, sans appeler l'IGN : parcours, longueurs, pas et points.
     *
     * Le parcours est la ligne principale (de 0 à `main_length_m`) suivie de ses embranchements,
     * chacun après un trou (`branches` : [début, fin] en mètres).
     *
     * @return array{path: ProfilePath, step: int, samples: list<float>, trace_length_m: float, covered_m: float,
     *     off_path_m: float, main_length_m: float, branches: list<array{0: float, 1: float}>, stops: list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float, status: string}>}|null
     */
    public function plan(Line $line): ?array
    {
        [$parts, $referenceLength] = $this->referenceParts($line);
        $chain = ProfileChain::assemble($parts);
        if ($chain === null) {
            return null;
        }
        $stops = $this->stops($line);
        $main = $this->orient($chain['path'], $stops, $line->name);
        ['path' => $path, 'branches' => $branches] = ProfileChain::append($main, $chain['branches']);
        $step = ProfilePath::stepFor($path->length());
        $gapLength = array_sum(array_map(fn (array $g): float => $g[1] - $g[0], $path->gaps));

        return [
            'path' => $path,
            'step' => $step,
            'samples' => $path->sampleDistances($step),
            'trace_length_m' => $referenceLength,
            'covered_m' => $path->length() - $gapLength,
            'off_path_m' => $chain['off_path_m'],
            'main_length_m' => $main->length(),
            'branches' => $branches,
            'stops' => $stops,
        ];
    }

    /**
     * Tuiles du cache de la BD TOPO que demanderait le calcul de la ligne.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function tilesFor(Line $line): array
    {
        $plan = $this->plan($line);
        if ($plan === null) {
            return [];
        }
        $tiles = [];
        foreach ($plan['samples'] as $d) {
            foreach ($this->tilesAround($plan['path']->pointAt($d)) as $key => $tile) {
                $tiles[$key] = $tile;
            }
        }

        return array_values($tiles);
    }

    /**
     * Calcule et enregistre le profil. Null si la ligne n'a aucun tracé exploitable ou aucune
     * altitude : l'ancien profil éventuel est alors supprimé, pour ne plus être servi.
     */
    /**
     * Pourquoi le dernier appel à compute() n'a pas donné de profil : `no_trace` (aucun tracé),
     * `outside_france` (aucun point du parcours en France, où seules la BD TOPO et le RGE ALTI
     * donnent une altitude), `no_altitude` (aucune altitude sur la partie française) ; null après
     * un profil calculé.
     */
    public function skipReason(): ?string
    {
        return $this->skipReason;
    }

    public function compute(Line $line): ?LineProfile
    {
        $this->skipReason = null;
        $plan = $this->plan($line);
        if ($plan === null) {
            $this->skipReason = 'no_trace';
            $this->discard($line);

            return null;
        }
        ['path' => $path, 'samples' => $samples] = $plan;

        $coords = array_map(fn (float $d): array => $path->pointAt($d), $samples);
        $track = $this->trackAltitudes($coords);

        // Modèle de terrain pour les seuls points sans altitude de voie, hors ouvrages et en France :
        // au-delà de la frontière, ni la BD TOPO ni ses tunnels ne sont connus (sous le Fréjus, le
        // RGE ALTI donnerait le dessus de la montagne côté italien).
        $inFrance = $this->inFrance($coords);
        $needTerrain = array_keys(array_filter(
            $track,
            fn (array $t, int $i): bool => $t['z'] === null && ! in_array($t['kind'], ['tunnel', 'bridge'], true) && $inFrance[$i],
            ARRAY_FILTER_USE_BOTH,
        ));
        $terrain = $needTerrain === [] ? [] : array_combine(
            $needTerrain,
            $this->client->elevations(array_map(fn (int $i): array => $coords[$i], $needTerrain)),
        );

        // Provenance de chaque mesure, avant la chasse aux aberrations.
        $points = [];
        $measured = [];
        foreach ($samples as $i => $d) {
            $z = $track[$i]['z'] ?? $terrain[$i] ?? null;
            $points[] = [$d, $z];
            $measured[] = match (true) {
                $track[$i]['z'] !== null => 'bdtopo',
                $z !== null => 'rge_alti',
                default => null,
            };
        }
        $points = ProfileStats::removeSpikes($points, $path->gaps);
        foreach ($points as $i => $p) {
            if ($p[1] === null) {
                $measured[$i] = null;
            }
        }
        $points = ProfileStats::fillStructures($points, array_map(
            fn (array $t): bool => $t['z'] === null && in_array($t['kind'], ['tunnel', 'bridge'], true),
            $track,
        ), gaps: $path->gaps);
        $stats = ProfileStats::summarize($points, $path->gaps, self::gradeFlags($points, $measured, array_column($track, 'kind')), RackRailways::isRack($line->slug));
        if ($stats === null) {
            $this->skipReason = in_array(true, $inFrance, true) ? 'no_altitude' : 'outside_france';
            $this->discard($line);

            return null;
        }

        $origins = ['bdtopo' => 0, 'rge_alti' => 0, 'interpolated' => 0, 'missing' => 0];
        foreach ($points as $i => $p) {
            $origins[$p[1] === null ? 'missing' : ($measured[$i] ?? 'interpolated')]++;
        }
        // PK calé sur la ligne principale seulement : un embranchement a sa propre numérotation.
        $anchors = $this->pkAnchors($path, $plan['stops'], $plan['main_length_m']);
        $pks = PkCalibration::estimate($anchors, $samples);
        $branchOf = array_fill(0, count($samples), null);
        foreach ($samples as $i => $d) {
            foreach ($plan['branches'] as $k => [$from, $to]) {
                if ($d >= $from - 0.5 && $d <= $to + 0.5) {
                    $branchOf[$i] = $k + 1;
                    $pks[$i] = null;
                }
            }
        }
        $pieces = array_fill(0, count($points), 0);
        foreach (ProfileStats::pieces($points, $path->gaps) as $k => [$first, $last]) {
            for ($i = $first; $i <= $last; $i++) {
                $pieces[$i] = $k;
            }
        }

        return DB::transaction(function () use ($line, $plan, $path, $points, $track, $origins, $stats, $anchors, $pks, $pieces, $branchOf, $measured, $coords, $samples): LineProfile {
            $profile = LineProfile::updateOrCreate(['line_id' => $line->id], [
                'era_id' => null,
                'source' => self::SOURCE,
                'resource' => GeoplateformeClient::RESOURCE,
                'step_m' => $plan['step'],
                'length_m' => (int) round($path->length()),
                'trace_length_m' => (int) round($plan['trace_length_m']),
                'covered_m' => (int) round($plan['covered_m']),
                'off_path_m' => (int) round($plan['off_path_m']),
                'pk_anchors' => count($anchors),
                'structures' => $this->structureRanges($samples, array_column($track, 'kind')),
                'altitude_sources' => $origins,
                'gaps' => array_map(fn (array $g): array => ['from_m' => (int) round($g[0]), 'to_m' => (int) round($g[1])], $path->gaps),
                'branches' => array_map(fn (array $b): array => ['from_m' => (int) round($b[0]), 'to_m' => (int) round($b[1])], $plan['branches']),
                'stations' => $this->placeStations($path, $points, $plan['stops']),
                ...$stats,
                'computed_at' => now(),
            ]);
            DB::table('line_profile_points')->where('line_profile_id', $profile->id)->delete();
            $rows = [];
            foreach ($points as $i => [$d, $z]) {
                $kind = $track[$i]['kind'];
                $rows[] = [
                    'seq' => $i,
                    'distance_m' => (int) round($d),
                    'pk_m' => $pks[$i],
                    'altitude_m' => $z === null ? null : round($z, 1),
                    'source' => $measured[$i],
                    'interpolated' => $z !== null && $measured[$i] === null,
                    'structure' => in_array($kind, ['tunnel', 'bridge'], true) ? $kind : null,
                    'piece' => $pieces[$i],
                    'branch' => $branchOf[$i],
                    'lon' => $coords[$i][0],
                    'lat' => $coords[$i][1],
                ];
            }
            foreach (array_chunk($rows, 2000) as $chunk) {
                $this->insertPoints($profile, $line, $chunk);
            }
            DB::statement(self::TRACE_SOURCE_SQL.' WHERE p.line_profile_id = ?', [$profile->id]);

            return $profile;
        });
    }

    /**
     * Drapeaux de la règle de publication de la déclivité (ProfileStats::summarize), un par point :
     * altitude mesurée sur la voie (BD TOPO), point en tunnel, altitude interpolée. Dans un tunnel,
     * l'altitude de la BD TOPO est reconstituée par l'IGN et peut être fausse près des têtes : à
     * l'entrée du tunnel de Marseille (LGV Méditerranée, `rfn-752000`), elle descend de 50 m sur le
     * premier kilomètre (50,9 ‰ pour 35 ‰ au plus sur cette ligne).
     *
     * @param  list<array{0: float|int, 1: float|null}>  $points  altitudes finales
     * @param  list<string|null>  $sources  provenance de la mesure (bdtopo, rge_alti, null)
     * @param  list<string|null>  $structures  ouvrage sous le point (tunnel, bridge, ground, null)
     * @return list<array{track: bool, tunnel: bool, interpolated: bool}>
     */
    public static function gradeFlags(array $points, array $sources, array $structures): array
    {
        $flags = [];
        foreach ($points as $i => $p) {
            $flags[] = [
                'track' => ($sources[$i] ?? null) === 'bdtopo',
                'tunnel' => ($structures[$i] ?? null) === 'tunnel',
                'interpolated' => $p[1] !== null && ($sources[$i] ?? null) === null,
            ];
        }

        return $flags;
    }

    /**
     * Chiffres du profil recalculés à partir des points déjà en base (`line_profile_points`), sans
     * appeler l'IGN (option --stats-only de la commande). Null si la ligne n'a pas de profil.
     */
    public function recomputeStats(Line $line): ?LineProfile
    {
        $profile = LineProfile::query()->where('line_id', $line->id)->first();
        if ($profile === null) {
            return null;
        }
        $rows = DB::select(
            'SELECT distance_m, altitude_m, source, structure FROM line_profile_points WHERE line_profile_id = ? ORDER BY seq',
            [$profile->id],
        );
        $points = array_map(fn (object $r): array => [(float) $r->distance_m, $r->altitude_m === null ? null : (float) $r->altitude_m], $rows);
        $gaps = array_map(fn (array $g): array => [(float) $g['from_m'], (float) $g['to_m']], $profile->gaps);
        $stats = ProfileStats::summarize($points, $gaps, self::gradeFlags(
            $points,
            array_map(fn (object $r): ?string => $r->source, $rows),
            array_map(fn (object $r): ?string => $r->structure, $rows),
        ), RackRailways::isRack($line->slug));
        if ($stats !== null) {
            $profile->update($stats);
        }

        return $profile;
    }

    /**
     * Insertion groupée des points (une requête par paquet, tableaux PostgreSQL dépliés).
     *
     * @param  list<array{seq: int, distance_m: int, pk_m: int|null, altitude_m: float|null, source: string|null,
     *     interpolated: bool, structure: string|null, piece: int, branch: int|null, lon: float, lat: float}>  $rows
     */
    private function insertPoints(LineProfile $profile, Line $line, array $rows): void
    {
        $array = fn (string $key, callable $format): string => '{'.implode(',', array_map(
            fn (array $r): string => $r[$key] === null ? 'NULL' : $format($r[$key]),
            $rows,
        )).'}';
        DB::statement(
            <<<'SQL'
            INSERT INTO line_profile_points
                (line_profile_id, line_id, seq, distance_m, pk_m, altitude_m, source, interpolated, structure, piece, branch, geom)
            SELECT ?, ?, t.seq, t.distance_m, t.pk_m, t.altitude_m, t.source, t.interpolated, t.structure, t.piece, t.branch,
                   ST_SetSRID(ST_MakePoint(t.lon, t.lat), 4326)
            FROM unnest(?::int[], ?::int[], ?::int[], ?::float8[], ?::text[], ?::bool[], ?::text[], ?::int[], ?::int[], ?::float8[], ?::float8[])
                AS t(seq, distance_m, pk_m, altitude_m, source, interpolated, structure, piece, branch, lon, lat)
            SQL,
            [
                $profile->id,
                $line->id,
                $array('seq', fn (int $v): string => (string) $v),
                $array('distance_m', fn (int $v): string => (string) $v),
                $array('pk_m', fn (int $v): string => (string) $v),
                $array('altitude_m', fn (float $v): string => (string) $v),
                $array('source', fn (string $v): string => $v),
                $array('interpolated', fn (bool $v): string => $v ? 't' : 'f'),
                $array('structure', fn (string $v): string => $v),
                $array('piece', fn (int $v): string => (string) $v),
                $array('branch', fn (int $v): string => (string) $v),
                $array('lon', fn (float $v): string => sprintf('%.7f', $v)),
                $array('lat', fn (float $v): string => sprintf('%.7f', $v)),
            ],
        );
    }

    /** Supprime le profil enregistré de la ligne et ses points, s'il y en a un. */
    private function discard(Line $line): void
    {
        LineProfile::query()->where('line_id', $line->id)->delete();
    }

    /**
     * Points situés dans une commune française (table `commune_contours`, index GIST).
     *
     * @param  list<array{0: float, 1: float}>  $coords
     * @return list<bool>
     */
    private function inFrance(array $coords): array
    {
        $inside = array_fill(0, count($coords), false);
        foreach (array_chunk($coords, 1000, true) as $chunk) {
            $rows = DB::select(
                <<<'SQL'
                SELECT t.i FROM unnest(?::float8[], ?::float8[], ?::int[]) AS t(lon, lat, i)
                WHERE EXISTS (
                    SELECT 1 FROM commune_contours c
                    WHERE ST_Intersects(c.geom, ST_SetSRID(ST_MakePoint(t.lon, t.lat), 4326))
                )
                SQL,
                [
                    '{'.implode(',', array_column($chunk, 0)).'}',
                    '{'.implode(',', array_column($chunk, 1)).'}',
                    '{'.implode(',', array_keys($chunk)).'}',
                ],
            );
            foreach ($rows as $row) {
                $inside[(int) $row->i] = true;
            }
        }

        return $inside;
    }

    /**
     * Morceaux du tracé de référence fusionnés (ST_LineMerge), sommets [lon, lat], et longueur du
     * référentiel. Sans référentiel, tronçons de toutes les ères hors voies de service.
     *
     * @return array{0: list<list<array{0: float, 1: float}>>, 1: float}
     */
    private function referenceParts(Line $line): array
    {
        $length = (float) DB::table('line_reference_parts')->where('line_id', $line->id)->sum('length_m');
        $rows = DB::select(
            <<<'SQL'
            SELECT ST_AsGeoJSON((ST_Dump(ST_LineMerge(ST_Collect(g)))).geom, 7) AS g
            FROM (SELECT (ST_Dump(geom)).geom AS g FROM line_reference_parts WHERE line_id = ?) parts
            SQL,
            [$line->id],
        );
        if ($length <= 0) {
            $rows = DB::select(
                <<<'SQL'
                SELECT ST_AsGeoJSON((ST_Dump(ST_LineMerge(ST_Collect(g)))).geom, 7) AS g
                FROM (
                    SELECT DISTINCT (ST_Dump(geom)).geom AS g FROM line_segments
                    WHERE line_id = ? AND status <> 'service_track'
                ) parts
                SQL,
                [$line->id],
            );
        }

        $parts = [];
        foreach ($rows as $row) {
            $geometry = json_decode((string) $row->g, true);
            if (($geometry['type'] ?? null) === 'LineString') {
                $parts[] = array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], $geometry['coordinates']);
            }
        }
        if ($length <= 0) {
            $length = array_sum(array_map(fn (array $p): float => (new ProfilePath($p))->length(), $parts));
        }

        return [$parts, $length];
    }

    /**
     * Gares rattachées à la ligne, toutes époques (`station_lines`), avec leur PK quand il vient
     * d'une source (liste SNCF, lignes-oubliees, schéma de Wikipédia, correction) et non d'une
     * projection sur le tracé ; triées par PK puis par nom.
     *
     * @return list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float, status: string}>
     */
    private function stops(Line $line): array
    {
        return array_map(fn (object $r): array => [
            'slug' => (string) $r->slug,
            'name' => (string) $r->name,
            'pk_m' => $r->pk_m === null ? null : (int) $r->pk_m,
            'lon' => (float) $r->lng,
            'lat' => (float) $r->lat,
            'status' => (string) $r->status,
        ], DB::select(
            'SELECT s.slug, s.name, s.status,
                    CASE WHEN sl.pk_source IN ('.implode(',', array_fill(0, count(self::PK_ANCHOR_SOURCES), '?')).') THEN sl.pk_m END AS pk_m,
                    ST_X(s.geom::geometry) AS lng, ST_Y(s.geom::geometry) AS lat
             FROM station_lines sl JOIN stations s ON s.id = sl.station_id
             WHERE sl.line_id = ? AND s.geom IS NOT NULL
             ORDER BY 4 NULLS LAST, s.name',
            [...self::PK_ANCHOR_SOURCES, $line->id],
        ));
    }

    /**
     * Sens du parcours : celui des PK croissants de la ligne. On lit les PK officiels des gares
     * placées sur le parcours (à moins de STATION_MAX_OFFSET_M). Sans deux PK officiels distincts,
     * le nom de la ligne (« Ligne de A à B ») : le parcours part de la gare A. Sinon, le sens de
     * l'assemblage est gardé. Les PK projetés sur le tracé ne servent pas : ils suivent le sens où la
     * géométrie est enregistrée, pas la numérotation de la ligne.
     *
     * @param  list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float, status: string}>  $stops
     */
    private function orient(ProfilePath $path, array $stops, string $lineName = ''): ProfilePath
    {
        $official = [];
        $placed = [];
        foreach ($stops as $stop) {
            $at = $path->locate([$stop['lon'], $stop['lat']]);
            if ($at['offset'] > self::STATION_MAX_OFFSET_M) {
                continue;
            }
            $placed[] = [$at['distance'], $stop['name']];
            if ($stop['pk_m'] !== null) {
                $official[] = [$at['distance'], $stop['pk_m']];
            }
        }
        $reverse = ProfileOrientation::shouldReverse($official);
        if ($reverse === null && count($placed) >= 2) {
            usort($placed, fn (array $x, array $y): int => $x[0] <=> $y[0]);
            $reverse = ProfileOrientation::reverseByName($lineName, $placed[0][1], $placed[count($placed) - 1][1], array_column($placed, 1));
        }

        return $reverse === true ? $path->reversed() : $path;
    }

    /**
     * Ancres du calage du PK : gares à PK officiel placées sur la ligne principale (avant
     * `$mainLength`), hors trous.
     *
     * @param  list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float, status: string}>  $stops
     * @return list<array{0: float, 1: int}>
     */
    private function pkAnchors(ProfilePath $path, array $stops, float $mainLength): array
    {
        $anchors = [];
        foreach ($stops as $stop) {
            if ($stop['pk_m'] === null) {
                continue;
            }
            $at = $path->locate([$stop['lon'], $stop['lat']]);
            if ($at['offset'] <= self::STATION_MAX_OFFSET_M && $at['distance'] <= $mainLength + 0.5 && ! $path->inGap($at['distance'])) {
                $anchors[] = [$at['distance'], $stop['pk_m']];
            }
        }

        return PkCalibration::monotone($anchors);
    }

    /**
     * Tuiles du cache qui couvrent le point et sa marge.
     *
     * @param  array{0: float, 1: float}  $point
     * @return array<string, array{0: int, 1: int}>
     */
    private function tilesAround(array $point): array
    {
        $tiles = [];
        foreach ([-1, 1] as $sx) {
            foreach ([-1, 1] as $sy) {
                $tile = GeoplateformeClient::tileOf($point[0] + $sx * self::TILE_MARGIN_DEG, $point[1] + $sy * self::TILE_MARGIN_DEG);
                $tiles[$tile[0].'_'.$tile[1]] = $tile;
            }
        }

        return $tiles;
    }

    /** Index des tronçons d'une tuile, gardé en mémoire pour les points suivants. */
    private function tile(string $key, int $x, int $y): TrackIndex
    {
        if (isset($this->tiles[$key])) {
            $index = $this->tiles[$key];
            unset($this->tiles[$key]);
        } else {
            $index = new TrackIndex($this->client->railTracksInTile($x, $y));
            if (count($this->tiles) >= self::TILES_IN_MEMORY) {
                unset($this->tiles[array_key_first($this->tiles)]);
            }
        }

        return $this->tiles[$key] = $index;
    }

    /**
     * Position (`ground`, `tunnel`, `bridge`) et altitude de la voie sous chaque point, d'après le
     * tronçon de la BD TOPO le plus proche (à moins de TRACK_TOLERANCE_M) ; kind null si aucun.
     * Tronçons lus par tuile fixe du cache (GeoplateformeClient::TILE_DEG).
     *
     * @param  list<array{0: float, 1: float}>  $coords
     * @return list<array{kind: string|null, z: float|null}>
     */
    private function trackAltitudes(array $coords): array
    {
        $out = [];
        foreach ($coords as $point) {
            $best = ['kind' => null, 'z' => null, 'offset' => INF];
            foreach ($this->tilesAround($point) as $key => [$x, $y]) {
                $found = $this->tile($key, $x, $y)->nearest($point, self::TRACK_TOLERANCE_M);
                if ($found['offset'] < $best['offset']) {
                    $best = $found;
                }
            }
            $out[] = ['kind' => $best['kind'], 'z' => $best['z']];
        }

        return $out;
    }

    /**
     * Suites de points en tunnel ou sur pont (BD TOPO), bornées aux points voisins hors ouvrage.
     *
     * @param  list<float>  $samples
     * @param  list<string|null>  $kinds
     * @return list<array{from_m: int, to_m: int, kind: string}>
     */
    private function structureRanges(array $samples, array $kinds): array
    {
        $ranges = [];
        $n = count($samples);
        for ($i = 0; $i < $n; $i++) {
            if (! in_array($kinds[$i], ['tunnel', 'bridge'], true)) {
                continue;
            }
            $j = $i;
            while ($j + 1 < $n && $kinds[$j + 1] === $kinds[$i]) {
                $j++;
            }
            $ranges[] = [
                'from_m' => (int) round($samples[max(0, $i - 1)]),
                'to_m' => (int) round($samples[min($n - 1, $j + 1)]),
                'kind' => $kinds[$i],
            ];
            $i = $j;
        }

        return $ranges;
    }

    /**
     * Toutes les gares rattachées à la ligne, projetées sur le parcours et triées par distance :
     * altitude du profil au point projeté (null dans un trou), PK officiel, statut de la gare
     * (`declassified` : gare fermée) et écart au parcours en mètres (une gare loin du parcours se
     * trouve sur un épi ou un tracé que le profil n'emprunte pas).
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float, status: string}>  $stops
     * @return list<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null, status: string, offset_m: int}>
     */
    private function placeStations(ProfilePath $path, array $points, array $stops): array
    {
        $placed = [];
        foreach ($stops as $stop) {
            $at = $path->locate([$stop['lon'], $stop['lat']]);
            $z = $path->inGap($at['distance']) ? null : ProfileStats::altitudeAt($points, $at['distance']);
            $placed[$stop['slug']] = [
                'slug' => $stop['slug'],
                'name' => $stop['name'],
                'distance_m' => (int) round($at['distance']),
                'altitude_m' => $z === null ? null : round($z, 1),
                'pk_m' => $stop['pk_m'],
                'status' => $stop['status'],
                'offset_m' => (int) round($at['offset']),
            ];
        }
        $placed = array_values($placed);
        usort($placed, fn (array $a, array $b): int => $a['distance_m'] <=> $b['distance_m']);

        return $placed;
    }
}
