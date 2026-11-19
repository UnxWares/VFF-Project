<?php

namespace App\Services\Profiles;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineProfile;
use Illuminate\Support\Facades\DB;

/**
 * Calcul du profil en long d'une ligne (commande vff:compute-line-profiles) :
 *
 * 1. tracé de l'ère courante (sinon de la dernière ère qui en a un), voies de service exclues,
 *    assemblé en un parcours de bout en bout (ProfileChain) et orienté de la première gare de la
 *    ligne (plus petit PK) vers la dernière ;
 * 2. points tous les 100 à 250 m selon la longueur (ProfilePath::stepFor) ;
 * 3. altitude de la VOIE quand un tronçon de la BD TOPO passe à moins de TRACK_TOLERANCE_M du
 *    point (géométrie 3D, interpolée le long du tronçon) ; sinon altitude du terrain (RGE ALTI),
 *    sauf dans un tunnel ou sur un pont de la BD TOPO sans altitude : interpolation entre les deux
 *    têtes de l'ouvrage, puisque le modèle de terrain y donne le dessus de la montagne ou le fond
 *    de la vallée. Mesure du 2026-11-19 sur Saint-Gervais - Vallorcine : 90 ‰ avec la BD TOPO
 *    (valeur publiée de la ligne), 110 ‰ avec le seul modèle de terrain ;
 * 5. extrêmes, dénivelés et déclivité la plus forte (ProfileStats), gares placées sur le profil.
 *
 * Lecture seule sur les tables du moteur ; seule `line_profiles` est écrite.
 */
final readonly class LineProfileBuilder
{
    /** Distance au-delà de laquelle un tronçon de la BD TOPO n'est pas rattaché au point du parcours. */
    public const TRACK_TOLERANCE_M = 20.0;

    /** Valeur de `line_profiles.source` (registre des droits : fournisseur `ign`). */
    public const SOURCE = 'bdtopo-rge-alti';

    /** Longueur de parcours couverte par une requête WFS (emprise de la requête). */
    private const TRACK_CHUNK_M = 10_000.0;

    /** Écart maximal entre une gare et le parcours pour la placer sur le profil. */
    private const STATION_MAX_OFFSET_M = 500.0;

    public function __construct(private GeoplateformeClient $client) {}

    /** Appels réseau faits aux services de l'IGN depuis la création du service. */
    public function networkCalls(): int
    {
        return $this->client->networkCalls();
    }

    /**
     * Ce que le calcul ferait, sans appeler l'IGN : ère, longueur, pas et nombre de points.
     *
     * @return array{era: Era, path: ProfilePath, step: int, samples: list<float>, trace_length_m: float}|null
     */
    public function plan(Line $line): ?array
    {
        $era = $this->eraWithTrace($line);
        if ($era === null) {
            return null;
        }
        $parts = $this->parts($line, $era);
        $path = ProfileChain::build($parts);
        if ($path === null) {
            return null;
        }
        $path = $this->orient($path, $this->stops($line, $era));
        $step = ProfilePath::stepFor($path->length());

        return [
            'era' => $era,
            'path' => $path,
            'step' => $step,
            'samples' => $path->sampleDistances($step),
            'trace_length_m' => array_sum(array_map(fn (array $p): float => (new ProfilePath($p))->length(), $parts)),
        ];
    }

    /** Calcule et enregistre le profil ; null si la ligne n'a aucun tracé exploitable. */
    public function compute(Line $line): ?LineProfile
    {
        $plan = $this->plan($line);
        if ($plan === null) {
            return null;
        }
        ['era' => $era, 'path' => $path, 'samples' => $samples] = $plan;

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

        $points = [];
        $origins = ['bdtopo' => 0, 'rge_alti' => 0, 'interpolated' => 0, 'missing' => 0];
        foreach ($samples as $i => $d) {
            $z = $track[$i]['z'] ?? $terrain[$i] ?? null;
            $points[] = [$d, $z];
            if ($track[$i]['z'] !== null) {
                $origins['bdtopo']++;
            } elseif (isset($terrain[$i])) {
                $origins['rge_alti']++;
            }
        }
        $spikes = $points;
        $points = ProfileStats::removeSpikes($points);
        foreach ($points as $i => $p) {
            if ($p[1] === null && $spikes[$i][1] !== null) {
                // Aberration effacée : le point sera compté parmi les interpolés.
                $origins[$track[$i]['z'] !== null ? 'bdtopo' : 'rge_alti']--;
            }
        }
        $unknown = array_map(fn (array $p): bool => $p[1] === null, $points);
        $points = ProfileStats::fillStructures($points, array_map(
            fn (array $t): bool => $t['z'] === null && in_array($t['kind'], ['tunnel', 'bridge'], true),
            $track,
        ));
        $origins['missing'] = count(array_filter($points, fn (array $p): bool => $p[1] === null));
        $origins['interpolated'] = count($points) - $origins['bdtopo'] - $origins['rge_alti'] - $origins['missing'];
        $stats = ProfileStats::summarize($points, $path->gaps, $unknown);
        if ($stats === null) {
            return null;
        }

        return LineProfile::updateOrCreate(['line_id' => $line->id], [
            'era_id' => $era->id,
            'source' => self::SOURCE,
            'resource' => GeoplateformeClient::RESOURCE,
            'step_m' => $plan['step'],
            'length_m' => (int) round($path->length()),
            'trace_length_m' => (int) round($plan['trace_length_m']),
            'points' => array_map(fn (array $p): array => [(int) round($p[0]), $p[1] === null ? null : round($p[1], 1)], $points),
            'structures' => $this->structureRanges($samples, array_column($track, 'kind')),
            'altitude_sources' => $origins,
            'gaps' => array_map(fn (array $g): array => ['from_m' => (int) round($g[0]), 'to_m' => (int) round($g[1])], $path->gaps),
            'stations' => $this->placeStations($path, $points, $this->stops($line, $era)),
            ...$stats,
            'computed_at' => now(),
        ]);
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

    /** Ère courante si la ligne y a un tracé, sinon la plus récente qui en a un. */
    private function eraWithTrace(Line $line): ?Era
    {
        $eraId = DB::table('line_segments')
            ->join('eras', 'eras.id', '=', 'line_segments.era_id')
            ->where('line_segments.line_id', $line->id)
            ->where('line_segments.status', '<>', 'service_track')
            ->orderByDesc('eras.is_current')
            ->orderByDesc('eras.year')
            ->value('eras.id');

        return $eraId === null ? null : Era::find($eraId);
    }

    /**
     * Morceaux du tracé fusionné (ST_LineMerge), sommets [lon, lat].
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    private function parts(Line $line, Era $era): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT ST_AsGeoJSON((ST_Dump(ST_LineMerge(ST_Collect(geom)))).geom, 7) AS g
            FROM line_segments
            WHERE line_id = ? AND era_id = ? AND status <> 'service_track'
            SQL,
            [$line->id, $era->id],
        );

        $parts = [];
        foreach ($rows as $row) {
            $geometry = json_decode((string) $row->g, true);
            if (($geometry['type'] ?? null) === 'LineString') {
                $parts[] = array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], $geometry['coordinates']);
            }
        }

        return $parts;
    }

    /**
     * Gares de la ligne à l'ère du tracé, dans l'ordre des PK.
     *
     * @return list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float}>
     */
    private function stops(Line $line, Era $era): array
    {
        return array_map(fn (object $r): array => [
            'slug' => (string) $r->slug,
            'name' => (string) $r->name,
            'pk_m' => $r->pk_m === null ? null : (int) $r->pk_m,
            'lon' => (float) $r->lng,
            'lat' => (float) $r->lat,
        ], DB::select(
            'SELECT s.slug, s.name, ls.pk_m, ST_X(s.geom::geometry) AS lng, ST_Y(s.geom::geometry) AS lat
             FROM line_stops ls JOIN stations s ON s.id = ls.station_id
             WHERE ls.line_id = ? AND ls.era_id = ? AND s.geom IS NOT NULL
             ORDER BY ls.position',
            [$line->id, $era->id],
        ));
    }

    /**
     * Le parcours part du côté de la première gare (plus petit PK) quand les deux gares extrêmes
     * sont placées dessus ; sinon il garde le sens de l'assemblage.
     *
     * @param  list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float}>  $stops
     */
    private function orient(ProfilePath $path, array $stops): ProfilePath
    {
        if (count($stops) < 2) {
            return $path;
        }
        $first = $path->locate([$stops[0]['lon'], $stops[0]['lat']]);
        $last = $path->locate([$stops[count($stops) - 1]['lon'], $stops[count($stops) - 1]['lat']]);
        if ($first['offset'] > self::STATION_MAX_OFFSET_M || $last['offset'] > self::STATION_MAX_OFFSET_M) {
            return $path;
        }

        return $first['distance'] > $last['distance'] ? $path->reversed() : $path;
    }

    /**
     * Position (`ground`, `tunnel`, `bridge`) et altitude de la voie sous chaque point, d'après le
     * tronçon de la BD TOPO le plus proche (à moins de TRACK_TOLERANCE_M) ; kind null si aucun.
     * Une requête WFS par tranche de TRACK_CHUNK_M de parcours, emprise élargie de 0,0005°.
     *
     * @param  list<array{0: float, 1: float}>  $coords
     * @return list<array{kind: string|null, z: float|null}>
     */
    private function trackAltitudes(array $coords): array
    {
        $out = array_fill(0, count($coords), ['kind' => null, 'z' => null]);
        $chunkSize = max(1, (int) ceil(self::TRACK_CHUNK_M / max(1.0, $this->meanSpacing($coords))));
        foreach (array_chunk($coords, $chunkSize, true) as $chunk) {
            $lons = array_column($chunk, 0);
            $lats = array_column($chunk, 1);
            $margin = 0.0005;
            // Tronçons d'un seul sommet écartés ; parcours construits une fois par tranche.
            $tracks = [];
            foreach ($this->client->railTracks([min($lons) - $margin, min($lats) - $margin, max($lons) + $margin, max($lats) + $margin]) as $t) {
                if (count($t['coords']) >= 2) {
                    $tracks[] = ['kind' => $t['kind'], 'z' => $t['z'], 'path' => new ProfilePath($t['coords'])];
                }
            }
            if ($tracks === []) {
                continue;
            }
            foreach ($chunk as $i => $point) {
                $out[$i] = $this->trackAt($point, $tracks);
            }
        }

        return $out;
    }

    /** @param list<array{0: float, 1: float}> $coords */
    private function meanSpacing(array $coords): float
    {
        $n = count($coords);

        return $n < 2 ? 1.0 : (new ProfilePath($coords))->length() / ($n - 1);
    }

    /**
     * Tronçon le plus proche du point et altitude interpolée entre les deux sommets qui l'encadrent
     * (null si l'un d'eux n'a pas d'altitude).
     *
     * @param  array{0: float, 1: float}  $point
     * @param  list<array{kind: string, z: list<float|null>, path: ProfilePath}>  $tracks
     * @return array{kind: string|null, z: float|null}
     */
    private function trackAt(array $point, array $tracks): array
    {
        $best = ['kind' => null, 'z' => null];
        $bestOffset = self::TRACK_TOLERANCE_M;
        foreach ($tracks as $t) {
            $at = $t['path']->locate($point);
            if ($at['offset'] > $bestOffset) {
                continue;
            }
            $bestOffset = $at['offset'];
            $za = $t['z'][$at['segment']] ?? null;
            $zb = $t['z'][$at['segment'] + 1] ?? null;
            $best = [
                'kind' => $t['kind'],
                'z' => $za === null || $zb === null ? null : $za + $at['t'] * ($zb - $za),
            ];
        }

        return $best;
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
     * Gares placées sur le profil (à moins de STATION_MAX_OFFSET_M du parcours), triées par distance.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<array{slug: string, name: string, pk_m: int|null, lon: float, lat: float}>  $stops
     * @return list<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null}>
     */
    private function placeStations(ProfilePath $path, array $points, array $stops): array
    {
        $placed = [];
        foreach ($stops as $stop) {
            $at = $path->locate([$stop['lon'], $stop['lat']]);
            if ($at['offset'] > self::STATION_MAX_OFFSET_M || $path->inGap($at['distance'])) {
                continue;
            }
            $z = ProfileStats::altitudeAt($points, $at['distance']);
            $placed[$stop['slug']] = [
                'slug' => $stop['slug'],
                'name' => $stop['name'],
                'distance_m' => (int) round($at['distance']),
                'altitude_m' => $z === null ? null : round($z, 1),
                'pk_m' => $stop['pk_m'],
            ];
        }
        $placed = array_values($placed);
        usort($placed, fn (array $a, array $b): int => $a['distance_m'] <=> $b['distance_m']);

        return $placed;
    }
}
