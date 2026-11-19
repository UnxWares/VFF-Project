<?php

namespace Tests\Support;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use Illuminate\Support\Facades\DB;

/**
 * Données factices pour les tests des voies vertes : lignes avec leur axe de référence, tronçons en
 * service de l'ère courante, voies laissées en place, ways OpenStreetMap (`present_osm_ways`).
 *
 * Repères : 0,00009° de latitude valent environ 10 m ; en longitude, `metresOf` donne la distance.
 * Chaque cas prend sa propre latitude pour rester isolé des autres.
 */
trait SeedsGreenways
{
    private ?Era $currentEra = null;

    /** Distance en mètres de `$degrees` de longitude à la latitude donnée (sphère de 6 371 km). */
    protected function metresOf(float $degrees, float $lat): float
    {
        return $degrees * M_PI / 180 * 6_371_000 * cos(deg2rad($lat));
    }

    /** Ligne avec son axe de référence (ligne droite d'ouest en est entre deux longitudes). */
    protected function seedLine(string $code, string $name, float $lat, float $lonFrom, float $lonTo): Line
    {
        $line = Line::factory()->create(['code' => $code, 'name' => $name, 'slug' => 'rfn-'.$code, 'network' => 'principal', 'type' => null]);
        $wkt = sprintf('LINESTRING(%F %F, %F %F)', $lonFrom, $lat, $lonTo, $lat);
        DB::insert("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '[]'::jsonb, ST_Multi(ST_GeomFromText(?, 4326)), now(), now())", [$line->id, $wkt, $wkt]);

        return $line;
    }

    /** Tronçon en service à l'ère courante (la ligne, ou une autre, est encore exploitée là). */
    protected function seedInServiceSegment(Line $line, float $lat, float $lonFrom, float $lonTo): void
    {
        $this->currentEra ??= Era::factory()->current()->create(['year' => 2030]);
        $segment = LineSegment::factory()->inService()->create(['line_id' => $line->id, 'era_id' => $this->currentEra->id]);
        DB::update('UPDATE line_segments SET geom = ST_GeomFromText(?, 4326) WHERE id = ?', [
            sprintf('LINESTRING(%F %F, %F %F)', $lonFrom, $lat, $lonTo, $lat), $segment->id,
        ]);
    }

    /** Voie laissée en place (rails BD TOPO ou OSM) : état `track_in_place` de `present_states`. */
    protected function seedTrackInPlace(float $lat, float $lonFrom, float $lonTo): void
    {
        DB::insert("INSERT INTO present_states (source, source_ref, state, geom, created_at, updated_at)
            VALUES ('bdtopo', ?, 'track_in_place', ST_Multi(ST_GeomFromText(?, 4326)), now(), now())", [
            'rails-'.$lat.'-'.$lonFrom, sprintf('LINESTRING(%F %F, %F %F)', $lonFrom, $lat, $lonTo, $lat),
        ]);
    }

    /**
     * Way OSM de `present_osm_ways`, géométrie en WKT.
     *
     * @param  array<string, mixed>  $tags
     * @param  list<array<string, mixed>>|null  $routes  relations cyclables dont le way est membre
     */
    protected function seedOsmWay(int $id, string $kind, string $wkt, array $tags = [], ?array $routes = null, ?int $startYear = null): void
    {
        $tags += $kind === 'greenway' ? ['highway' => 'cycleway'] : [];
        DB::insert("INSERT INTO present_osm_ways (id, region, kind, highway, name, tags, routes, start_year, length_m, seen_at, geom, created_at, updated_at)
            VALUES (?, 'test', ?, ?, ?, ?::jsonb, ?::jsonb, ?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '2026-11-18', ST_GeomFromText(?, 4326), now(), now())", [
            $id, $kind, $tags['highway'] ?? null, $tags['name'] ?? null, json_encode($tags), $routes === null ? null : json_encode($routes),
            $startYear, $wkt, $wkt,
        ]);
    }

    /** Voie verte en ligne droite d'ouest en est, à la latitude donnée. */
    protected function seedGreenway(int $id, float $lat, float $lonFrom, float $lonTo, array $tags = [], ?array $routes = null, ?int $startYear = null): void
    {
        $this->seedOsmWay($id, 'greenway', sprintf('LINESTRING(%F %F, %F %F)', $lonFrom, $lat, $lonTo, $lat), $tags, $routes, $startYear);
    }
}
