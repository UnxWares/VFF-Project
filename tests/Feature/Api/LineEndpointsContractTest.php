<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Contrat JSON des endpoints par ligne (historique, arrêts, signalisation,
 * médias) : agrégats, tris, formats de PK, valeurs par défaut, types.
 */
class LineEndpointsContractTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    private function segment(Era $era, string $status, ?string $traffic, bool $electrified, bool $heritage = false): void
    {
        LineSegment::factory()->create([
            'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => $traffic,
            'electrified' => $electrified, 'is_heritage' => $heritage,
            'geom' => LineString::make([Point::makeGeodetic(48.80, 2.10), Point::makeGeodetic(48.70, 2.00)]),
        ]);
    }

    // ─── /history ────────────────────────────────────────────────────────

    public function test_history_aggregates_segments_per_era_in_chronological_order(): void
    {
        // Ère créée en dernier mais antérieure : l'ordre suit l'année, pas l'id.
        $era1900 = Era::factory()->create(['year' => 1900]);
        $this->segment($era1900, 'in_service', 'mixed', false);
        $this->segment($this->currentEra, 'in_service', 'passenger', false);
        $this->segment($this->currentEra, 'abandoned', 'freight', false, heritage: true);

        $this->getJson("/api/v1/lines/{$this->line->slug}/history")
            ->assertOk()
            ->assertJsonPath('status_by_era', [
                ['year' => 1900, 'segment_count' => 1, 'dominant_status' => 'in_service', 'dominant_traffic' => 'mixed',
                    'has_heritage_section' => false, 'electrified_count' => 0],
                ['year' => 1960, 'segment_count' => 1, 'dominant_status' => 'in_service', 'dominant_traffic' => 'passenger',
                    'has_heritage_section' => false, 'electrified_count' => 1],
                ['year' => 2030, 'segment_count' => 3, 'dominant_status' => 'in_service', 'dominant_traffic' => 'passenger',
                    'has_heritage_section' => true, 'electrified_count' => 1],
            ])
            ->assertJsonPath('line', [
                'slug' => $this->line->slug,
                'code' => '999 000',
                'name' => 'Paris-Saint-Lazare – Versailles-Rive-Droite',
                'type' => 'passenger',
                'historical_operator' => 'Compagnie de l\'Ouest',
            ]);
    }

    public function test_history_events_are_sorted_with_precision_and_tolerate_unknown_types(): void
    {
        DB::table('line_events')->insert([
            ['line_id' => $this->line->id, 'type' => 'electrification', 'event_date' => '1936-01-01', 'date_precision' => 'year',
                'description' => 'Électrification.', 'created_at' => now(), 'updated_at' => now()],
            // Type présent en base mais pas (encore) dans l'enum PHP : renvoyé tel quel.
            ['line_id' => $this->line->id, 'type' => 'transfer', 'event_date' => '1909-01-01', 'date_precision' => 'day',
                'description' => 'Rachat par l\'État.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson("/api/v1/lines/{$this->line->slug}/history")->assertOk();

        $this->assertSame(['1839-08-02', '1909-01-01', '1936-01-01'], array_column($response->json('events'), 'date'));
        $this->assertSame(['opening', 'transfer', 'electrification'], array_column($response->json('events'), 'type'));
        $this->assertSame(['day', 'day', 'year'], array_column($response->json('events'), 'date_precision'));
        $this->assertSame(['type', 'date', 'date_precision', 'section_from', 'section_to', 'description', 'source', 'source_url'],
            array_keys($response->json('events.0')));
    }

    public function test_history_of_a_line_without_data(): void
    {
        $empty = Line::factory()->create(['slug' => 'ligne-vide', 'type' => null, 'code' => null]);

        $this->getJson('/api/v1/lines/ligne-vide/history')
            ->assertOk()
            ->assertExactJson([
                'line' => ['slug' => 'ligne-vide', 'code' => null, 'name' => $empty->name, 'type' => null,
                    'historical_operator' => $empty->historical_operator],
                'events' => [],
                'status_by_era' => [],
            ]);
    }

    // ─── /stops ──────────────────────────────────────────────────────────

    public function test_stops_are_ordered_by_position_with_pk_labels_and_status(): void
    {
        $halt = Station::factory()->create([
            'slug' => 'halte-test', 'name' => 'Halte d\'essai', 'type' => 'halte', 'city' => null,
            'geom' => Point::makeGeodetic(48.80, 2.10),
        ]);
        DB::table('line_stops')->insert([
            'line_id' => $this->line->id, 'era_id' => $this->currentEra->id, 'station_id' => $halt->id,
            'position' => 3, 'pk_m' => 21_005, 'effective_status' => 'declassified', 'is_terminal' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/lines/{$this->line->slug}/stops")->assertOk();

        $this->assertSame([1, 2, 3], array_column($response->json('stops'), 'position'));
        $this->assertSame(['0+000', '17+250', '21+005'], array_column($response->json('stops'), 'pk_label'));
        $this->assertSame(['active', 'active', 'declassified'], array_column($response->json('stops'), 'effective_status'));
        $this->assertSame([true, true, false], array_column($response->json('stops'), 'is_terminal'));
        $this->assertSame([
            'slug' => 'halte-test', 'name' => 'Halte d\'essai', 'type' => 'halte', 'city' => null, 'lat' => 48.8, 'lng' => 2.1,
        ], $response->json('stops.2.station'));
        $this->assertIsFloat($response->json('stops.0.station.lat'));
    }

    public function test_stops_and_signalling_need_a_current_era(): void
    {
        $this->currentEra->update(['is_current' => false]);

        $this->getJson("/api/v1/lines/{$this->line->slug}/stops")->assertNotFound();
        $this->getJson("/api/v1/lines/{$this->line->slug}/signalling")->assertNotFound();
        $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.85&lng=2.24")->assertNotFound();
        // Avec une ère explicite, ça reste possible.
        $this->getJson("/api/v1/lines/{$this->line->slug}/stops?era=2030")->assertOk()->assertJsonCount(2, 'stops');
    }

    // ─── /signalling ─────────────────────────────────────────────────────

    public function test_signalling_ranges_are_sorted_with_labels_and_defaults(): void
    {
        DB::table('line_signalling')->insert([
            'line_id' => $this->line->id, 'era_id' => $this->currentEra->id,
            'pk_start_m' => 17_250, 'pk_end_m' => 20_040, 'signalling' => 'BAPR',
            'train_protection' => null, 'kvb_equipped' => false, 'note' => 'Voie unique',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/lines/{$this->line->slug}/signalling")
            ->assertOk()
            ->assertJsonPath('ranges', [
                ['pk_start_m' => 0, 'pk_end_m' => 17_250, 'pk_start_label' => '0+000', 'pk_end_label' => '17+250',
                    'length_m' => 17_250, 'signalling' => 'BAL', 'train_protection' => ['KVB'], 'kvb_equipped' => true, 'note' => null],
                ['pk_start_m' => 17_250, 'pk_end_m' => 20_040, 'pk_start_label' => '17+250', 'pk_end_label' => '20+040',
                    'length_m' => 2_790, 'signalling' => 'BAPR', 'train_protection' => [], 'kvb_equipped' => false, 'note' => 'Voie unique'],
            ]);
    }

    // ─── /media ──────────────────────────────────────────────────────────

    private function media(array $attributes): void
    {
        DB::table('media')->insert($attributes + [
            'mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'type' => 'photo',
            'provider' => 'commons', 'rights_status' => 'unknown', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_media_featured_sort_upload_urls_location_and_empty_pages(): void
    {
        $this->media(['external_id' => 'a', 'title' => 'Ancienne', 'depicted_year' => 1890, 'thumb_url' => 'https://upload.wikimedia.org/a.jpg']);
        $this->media(['external_id' => 'b', 'title' => 'Vedette', 'depicted_year' => 1990, 'is_featured' => true,
            'thumb_url' => 'https://upload.wikimedia.org/b.jpg', 'display_url' => 'https://upload.wikimedia.org/b-1280.jpg']);
        $this->media(['provider' => 'upload', 'file_path' => 'media/gare.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 1000,
            'title' => 'Envoi local', 'depicted_year' => 1950, 'rights_status' => 'open_license', 'license' => 'CC BY 4.0']);
        DB::statement('UPDATE media SET location = ST_SetSRID(ST_MakePoint(2.24, 48.85), 4326) WHERE title = ?', ['Ancienne']);

        $slug = $this->line->slug;
        $featured = $this->getJson("/api/v1/lines/{$slug}/media?sort=featured")->assertOk();
        $this->assertSame(['Vedette', 'Ancienne', 'Envoi local'], array_column($featured->json('data'), 'title'));
        $this->assertSame([true, false, false], array_column($featured->json('data'), 'featured'));
        $this->assertSame('https://upload.wikimedia.org/b-1280.jpg', $featured->json('data.0.display_url'));
        $this->assertSame('Wikimedia Commons', $featured->json('data.0.provider_label'));
        $this->assertSame('year', $featured->json('data.0.date_precision'));

        $upload = $featured->json('data.2');
        $this->assertSame('VFF', $upload['provider_label']);
        $this->assertSame(Storage::url('media/gare.jpg'), $upload['thumb_url']);
        $this->assertSame($upload['thumb_url'], $upload['display_url'], 'pas de version écran : la miniature');
        $this->assertNull($upload['location']);
        $this->assertSame(['lat' => 48.85, 'lng' => 2.24], $featured->json('data.1.location'));

        $this->getJson("/api/v1/lines/{$slug}/media?period=pre1900")->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Ancienne');
        $this->getJson("/api/v1/lines/{$slug}/media?period=1945_1969&type=photo")->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Envoi local');
        $this->getJson("/api/v1/lines/{$slug}/media?page=9")->assertOk()
            ->assertJsonPath('meta', ['current_page' => 9, 'per_page' => 24, 'total' => 3, 'last_page' => 1, 'sort' => 'oldest'])
            ->assertJsonPath('data', []);
        $all = $this->getJson("/api/v1/lines/{$slug}/media")->assertOk()
            ->assertJsonPath('facets.periods', ['pre1900' => 1, '1900_1944' => 0, '1945_1969' => 1, '1970_1999' => 1, 'since2000' => 0, 'undated' => 0]);
        $this->assertEqualsCanonicalizing(['commons' => 2, 'upload' => 1], $all->json('facets.providers'));
        $this->assertEqualsCanonicalizing(['unknown' => 2, 'open_license' => 1], $all->json('facets.rights'));
    }

    public function test_media_validation_messages(): void
    {
        $slug = $this->line->slug;
        foreach (['per_page=0' => 'per_page', 'per_page=61' => 'per_page', 'page=0' => 'page', 'sort=random' => 'sort',
            'type=video' => 'type', 'page=abc' => 'page'] as $query => $field) {
            $this->getJson("/api/v1/lines/{$slug}/media?{$query}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }
    }

    public function test_media_of_a_line_without_media(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/media")
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('facets.types', [])
            ->assertJsonPath('facets.rights', []);
    }
}
