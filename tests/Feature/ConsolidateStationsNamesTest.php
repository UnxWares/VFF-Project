<?php

namespace Tests\Feature;

use App\Console\Commands\ConsolidateStations;
use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:consolidate-stations and station names (real cases of the Wikidata import of 25/11/2026):
 *   - the order of the words of a compound name does not keep two sources of one station apart
 *     (Wikidata Q142166798 "Grignan - Chamaret", 376 m from "Chamaret-Grignan" of lignes-oubliees);
 *   - a generic name ("Ancienne gare") never becomes the name or the slug of a station that another
 *     source names (OSM w495279440 "Ancienne gare" and Wikidata Q106726094 "Saint-Geniès").
 */
class ConsolidateStationsNamesTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function source(string $source, string $ref, string $name, float $lat, float $lng, array $attributes = []): int
    {
        return DB::table('station_sources')->insertGetId($attributes + [
            'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'active', 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)),
        ]);
    }

    private function station(string $slug, string $name, float $lat, float $lng): int
    {
        return DB::table('stations')->insertGetId(['slug' => $slug, 'name' => $name, 'type' => 'gare', 'status' => 'declassified',
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{0: int, 1: int} lignes-oubliees source, Wikidata source */
    private function grignan(?int $loStation = null, ?int $wdStation = null): array
    {
        $lo = $this->source('lignes-oubliees', 'nyons:chamaret-grignan', 'Chamaret-Grignan', 44.396584644564, 4.8903392470429, [
            'lifecycle' => 'unknown', 'station_id' => $loStation,
            'properties' => json_encode(['geocoding' => 'pk', 'precision_m' => 508, 'line_code' => '914000'])]);
        $wd = $this->source('wikidata', 'Q142166798', 'Grignan - Chamaret', 44.394197, 4.886999, [
            'lifecycle' => 'disused', 'wikidata_id' => 'Q142166798', 'opened_on' => '1896-01-01', 'station_id' => $wdStation,
            'properties' => json_encode(['aliases' => ['gare de Grignan - Chamaret']])]);

        return [$lo, $wd];
    }

    public function test_same_words_in_another_order_nearby_are_one_station(): void
    {
        [$lo, $wd] = $this->grignan();

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame($links[$lo], $links[$wd]);
        $this->assertSame(1, DB::table('stations')->count());
    }

    public function test_the_duplicate_created_by_the_wikidata_import_joins_the_older_station_and_redirects(): void
    {
        // State of 25/11/2026: 23325 "Chamaret-Grignan" (lignes-oubliees), then 24778 "Grignan - Chamaret" (Wikidata) 376 m away.
        $old = $this->station('chamaret-grignan', 'Chamaret-Grignan', 44.396584644564, 4.8903392470429);
        $dup = $this->station('grignan-chamaret', 'Grignan - Chamaret', 44.394197, 4.886999);
        [$lo, $wd] = $this->grignan($old, $dup);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame($old, $links[$lo], 'the older station keeps its id and slug');
        $this->assertSame($old, $links[$wd]);
        $this->assertFalse(DB::table('stations')->where('id', $dup)->exists());
        $this->assertSame('chamaret-grignan', DB::table('stations')->where('id', $old)->value('slug'));
        $this->assertSame($old, (int) DB::table('station_redirects')->where('old_slug', 'grignan-chamaret')->value('station_id'));
    }

    public function test_reversed_words_do_not_lift_the_distinct_wikidata_guard(): void
    {
        // Two different Q-ids 376 m apart: the reversed name alone does not prove one station.
        $osm = $this->source('osm', 'n1', 'Chamaret-Grignan', 44.396584644564, 4.8903392470429, ['wikidata_id' => 'Q1']);
        $wd = $this->source('wikidata', 'Q142166798', 'Grignan - Chamaret', 44.394197, 4.886999, ['wikidata_id' => 'Q142166798']);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertNotSame($links[$osm], $links[$wd]);
    }

    /** @return array{0: int, 1: int} Wikidata source, OSM source */
    private function saintGenies(?int $station = null): array
    {
        $wd = $this->source('wikidata', 'Q106726094', 'Saint-Geniès', 44.99306, 1.25554, [
            'lifecycle' => 'disused', 'wikidata_id' => 'Q106726094', 'station_id' => $station,
            'properties' => json_encode(['aliases' => ['gare de Saint-Geniès']])]);
        $osm = $this->source('osm', 'w495279440', 'Ancienne gare', 44.9930665, 1.2555462, [
            'lifecycle' => 'abandoned', 'station_id' => $station,
            'properties' => json_encode(['name' => 'Ancienne gare', 'building' => 'yes', 'abandoned:railway' => 'station'])]);

        return [$wd, $osm];
    }

    public function test_a_generic_name_never_names_a_station_that_another_source_names(): void
    {
        [$wd, $osm] = $this->saintGenies();

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $links = DB::table('station_sources')->pluck('station_id', 'id');
        $this->assertSame($links[$wd], $links[$osm]);
        $st = DB::table('stations')->where('id', $links[$wd])->first(['name', 'slug', 'alt_names']);
        $this->assertSame(['Saint-Geniès', 'saint-genies'], [$st->name, $st->slug]);
        $this->assertSame([], json_decode((string) $st->alt_names, true), 'no "Ancienne gare" among the other names');
    }

    public function test_a_station_created_under_a_generic_name_takes_the_real_name_and_slug_and_redirects(): void
    {
        $id = $this->station('ancienne-gare', 'Ancienne gare', 44.9930665, 1.2555462);
        $this->saintGenies($id);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $st = DB::table('stations')->where('id', $id)->first(['name', 'slug']);
        $this->assertSame(['Saint-Geniès', 'saint-genies'], [$st->name, $st->slug]);
        $this->assertSame($id, (int) DB::table('station_redirects')->where('old_slug', 'ancienne-gare')->value('station_id'));

        // Idempotent: a second run keeps the new slug and the redirect.
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $this->assertSame('saint-genies', DB::table('stations')->where('id', $id)->value('slug'));
        $this->assertSame(1, DB::table('station_redirects')->count());
    }

    public function test_generic_slugs(): void
    {
        foreach (['ancienne-gare', 'gare', 'gare-24', 'halte-2', 'gare-sncf-2a', 'station-24-3'] as $slug) {
            $this->assertTrue(ConsolidateStations::isGenericSlug($slug), $slug);
        }
        foreach (['saint-genies', 'gare-de-lyon', 'pierrefonds', 'lezan-30', 'halte-de-valcanville'] as $slug) {
            $this->assertFalse(ConsolidateStations::isGenericSlug($slug), $slug);
        }
    }
}
