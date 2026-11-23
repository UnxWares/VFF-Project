<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Station;
use App\Services\StationCorrections;
use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:consolidate-stations recalcule les noms issus de station_sources, pas les autres noms de
 * vff:import-station-names (Wikipédia, Wikidata, lieux osm_place) ni les clés de provenance qu'elle ne calcule
 * pas : elles survivent à chaque passage et suivent la fiche gardée lors d'une fusion.
 */
class ConsolidateStationsKeepsOtherNamesTest extends TestCase
{
    use RefreshDatabase;

    private const SNCF = [48.5308625, -1.5646822];

    private const OSM = [48.5297585, -1.5660572];

    /** @param  array<string, mixed>  $attributes */
    private function source(string $source, string $ref, string $name, array $latLng, array $attributes = []): void
    {
        DB::table('station_sources')->insert($attributes + [
            'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'active', 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $latLng[1], $latLng[0])),
        ]);
    }

    /** @param  list<array<string, mixed>>  $alt */
    private function setAlt(int $id, array $alt): void
    {
        DB::table('stations')->where('id', $id)->update(['alt_names' => json_encode($alt, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return list<array<string, mixed>> */
    private function alt(int $id): array
    {
        // jsonb range les clés dans son ordre : on les trie des deux côtés avant de comparer.
        return array_map(function (array $a): array {
            ksort($a);

            return $a;
        }, json_decode((string) DB::table('stations')->where('id', $id)->value('alt_names'), true));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function sorted(array $entry): array
    {
        ksort($entry);

        return $entry;
    }

    /** @param  list<array<string, mixed>>  $corrections */
    private function consolidate(array $corrections = []): void
    {
        $this->app->instance(StationCorrections::class, new StationCorrections($corrections));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
    }

    public function test_other_names_and_provenance_keys_survive_a_new_consolidation(): void
    {
        $this->source('sncf', '87478255', 'Pleine-Fougères', self::SNCF, ['uic_code' => '87478255']);
        $this->consolidate();
        $id = (int) DB::table('stations')->value('id');
        $wp = ['name' => 'Pleine-Fougères-Sougeal', 'from' => 1900, 'to' => 1950, 'source' => 'wikipedia_history', 'source_url' => 'https://fr.wikipedia.org/x'];
        $wd = ['name' => 'Pleine Fougères', 'from' => null, 'to' => 1900, 'source' => 'wikidata_names', 'lang' => 'fr'];
        $stale = ['name' => 'Ancien nom OSM', 'source' => 'osm'];
        $this->setAlt($id, [$stale, $wp, $wd]);
        DB::update("UPDATE stations SET provenance = provenance || '{\"kind\": \"technical\", \"closed_after\": 1950}'::jsonb WHERE id = ?", [$id]);

        $this->consolidate();

        $alt = $this->alt($id);
        $this->assertContains($this->sorted($wp), $alt);
        $this->assertContains($this->sorted($wd), $alt);
        $this->assertNotContains($stale, $alt, 'une entrée d\'une source recalculée disparaît quand la source ne la porte plus');
        $prov = json_decode((string) DB::table('stations')->where('id', $id)->value('provenance'), true);
        $this->assertSame(['technical', 1950, 'sncf'], [$prov['kind'], $prov['closed_after'], $prov['name']]);

        $this->consolidate();
        $this->assertSame($alt, $this->alt($id), 'idempotent');
    }

    public function test_a_merge_reports_the_other_names_of_the_absorbed_station_on_the_kept_one(): void
    {
        $this->source('sncf', '87478255', 'Pleine-Fougères', self::SNCF, ['uic_code' => '87478255', 'freight' => true]);
        $this->source('osm', 'n2514130142', 'Pleine-Fougères', self::OSM, ['lifecycle' => 'razed']);
        $this->consolidate();
        $kept = (int) DB::table('stations')->where('uic_code', '87478255')->value('id');
        $absorbed = (int) DB::table('stations')->where('id', '!=', $kept)->value('id');
        $keptName = ['name' => 'Pleine-Fougères-Gare', 'source' => 'wikipedia_history', 'from' => 1880, 'to' => null];
        $absorbedName = ['name' => 'Halte du Mont', 'source' => 'wikidata_names', 'from' => null, 'to' => 1930];
        $this->setAlt($kept, [$keptName]);
        $this->setAlt($absorbed, [$absorbedName, $keptName]);

        $this->consolidate([[
            'station' => ['name' => 'Pleine-Fougères', 'uic' => '87478255'], 'action' => 'merge',
            'absorbed' => ['name' => 'Pleine-Fougères', 'osm' => 'n2514130142'], 'position' => 'absorbed',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Rennes&oldid=1', 'reason' => 'une seule gare',
        ]]);

        $this->assertSame(1, DB::table('stations')->count());
        $alt = $this->alt($kept);
        $this->assertContains($this->sorted($keptName), $alt);
        $this->assertContains($this->sorted($absorbedName), $alt);
        $this->assertCount(1, array_filter($alt, fn ($a) => $a === $this->sorted($keptName)), 'pas de doublon');
    }

    public function test_the_railway_name_set_for_a_place_name_is_not_reverted(): void
    {
        $this->source('osm', 'n1', 'La Recyclerie', self::OSM);
        $this->consolidate();
        $id = (int) DB::table('stations')->value('id');
        DB::table('stations')->where('id', $id)->update(['name' => 'Boulevard Ornano']);
        DB::update("UPDATE stations SET provenance = jsonb_set(provenance, '{name}', '\"wikidata\"') WHERE id = ?", [$id]);
        $this->setAlt($id, [['name' => 'La Recyclerie', 'source' => 'osm_place']]);

        $this->consolidate();

        $st = DB::table('stations')->where('id', $id)->first(['name', 'provenance']);
        $this->assertSame('Boulevard Ornano', $st->name);
        $this->assertSame('wikidata', json_decode($st->provenance, true)['name']);
        $this->assertSame([['name' => 'La Recyclerie', 'source' => 'osm_place']], $this->alt($id));
    }

    public function test_data_written_by_other_commands_on_a_kept_station_survives(): void
    {
        $this->source('sncf', '87478255', 'Pleine-Fougères', self::SNCF, ['uic_code' => '87478255']);
        $this->consolidate();
        $id = (int) DB::table('stations')->value('id');
        $now = now();
        Media::factory()->create(['mediable_type' => Station::class, 'mediable_id' => $id, 'provider' => 'commons', 'external_id' => 'A.jpg']);
        DB::table('station_attendances')->insert(['station_id' => $id, 'year' => 2023, 'passengers' => 1200, 'series' => 'voyageurs', 'uic_code' => '87478255',
            'match_method' => 'uic', 'dataset' => 'frequentation', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('station_accessibility')->insert(['station_id' => $id, 'uic_code' => '87478255', 'sncf_station_id' => '00000000-0000-4000-8000-000000000001',
            'amenities' => '{}', 'datasets' => '[]', 'computed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('station_events')->insert(['station_id' => $id, 'type' => 'closure', 'event_date' => '1950-01-01', 'date_precision' => 'year',
            'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => $now, 'updated_at' => $now]);

        $this->consolidate();

        $this->assertSame([1, 1, 1, 1], [
            DB::table('media')->where('mediable_id', $id)->count(), DB::table('station_attendances')->where('station_id', $id)->count(),
            DB::table('station_accessibility')->where('station_id', $id)->count(), DB::table('station_events')->where('station_id', $id)->where('source', 'wikipedia')->count(),
        ]);
    }
}
