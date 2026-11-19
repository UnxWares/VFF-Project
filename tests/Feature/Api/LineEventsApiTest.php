<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GET /api/v1/events et /api/v1/events/{id} : liste filtrable des événements de lignes, pagination
 * par curseur, fiche avec citation, provenance et licence de la source.
 */
class LineEventsApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private Line $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();

        // La ligne du mini-réseau porte déjà un événement 1839 ; la seconde ligne cite la Finistère.
        $this->other = Line::factory()->create(['slug' => 'morlaix-roscoff', 'name' => 'Morlaix - Roscoff', 'code' => '535 000']);
        DB::table('lines')->where('id', $this->other->id)->update(['departements' => json_encode(['Finistère'])]);

        $this->event($this->line, 'electrification', '1928-05-01', 'wikidata', 'day');
        $this->event($this->other, 'closure_passenger', '1938-11-01', 'wikipedia', 'day', [
            'section_from' => 'Morlaix', 'section_to' => 'Roscoff', 'source_url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Morlaix_a_Roscoff',
            'source_quote' => 'Le service des voyageurs est supprimé le 1er novembre 1938.', 'confidence' => 'high',
            'section_pk_start_m' => 0, 'section_pk_end_m' => 27300, 'section_length_m' => 27300, 'section_resolution' => 'pk',
        ]);
        $this->event($this->other, 'dismantling', '1960-01-01', 'lignes-oubliees', 'year');
    }

    /** @param  array<string, mixed>  $extra */
    private function event(Line $line, string $type, string $date, string $source, string $precision, array $extra = []): int
    {
        return DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => $precision,
            'description' => "{$type} {$date}", 'source' => $source, 'created_at' => now(), 'updated_at' => now(),
        ] + $extra);
    }

    public function test_lists_events_in_chronological_order_with_line_and_provenance(): void
    {
        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.date', '1839-08-02')
            ->assertJsonPath('data.0.type', 'opening')
            ->assertJsonPath('data.1.type', 'electrification')
            ->assertJsonPath('data.1.provenance.provider', 'wikidata')
            ->assertJsonPath('data.1.provenance.license', 'CC0 1.0')
            ->assertJsonPath('data.1.provenance.license_status', 'verified')
            ->assertJsonPath('data.1.line.slug', $this->line->slug)
            ->assertJsonPath('data.2.section_from', 'Morlaix')
            ->assertJsonPath('data.2.source_url', 'https://fr.wikipedia.org/wiki/Ligne_de_Morlaix_a_Roscoff')
            ->assertJsonPath('data.2.confidence', 'high')
            ->assertJsonPath('data.3.date_precision', 'year')
            ->assertJsonPath('data.3.provenance.license_status', 'uncertain')
            ->assertJsonPath('links.next', null)
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_list_does_not_expose_the_quote_nor_the_geometry(): void
    {
        $first = $this->getJson('/api/v1/events')->assertOk()->json('data.2');

        $this->assertArrayNotHasKey('source_quote', $first);
        $this->assertArrayNotHasKey('section_geom', $first);
        $this->assertArrayNotHasKey('fingerprint', $first);
    }

    public function test_order_desc_reverses_the_list(): void
    {
        $this->getJson('/api/v1/events?order=desc')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'dismantling')
            ->assertJsonPath('data.3.type', 'opening');
    }

    public function test_filters_by_line_type_and_source(): void
    {
        $this->getJson('/api/v1/events?line=morlaix-roscoff')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/events?type=electrification')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.line.slug', $this->line->slug);
        $this->getJson('/api/v1/events?source=lignes-oubliees')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'dismantling');
        $this->getJson('/api/v1/events?line=morlaix-roscoff&type=dismantling')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_period_bounds_accept_years_and_dates(): void
    {
        $this->getJson('/api/v1/events?from=1900&to=1939')->assertOk()->assertJsonCount(2, 'data');
        // 1960 : année entière, le 1er janvier 1960 est inclus dans `to=1960` et dans `from=1960-01-01`.
        $this->getJson('/api/v1/events?from=1960-01-01')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/events?to=1960')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/events?from=1938-11-01&to=1938-11-01')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'closure_passenger');
        $this->getJson('/api/v1/events?from=1961')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_department_matches_the_line_sheet_or_a_served_station(): void
    {
        // Fiche de la ligne : « Finistère » = 29.
        $this->getJson('/api/v1/events?department=29')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.line.slug', 'morlaix-roscoff');

        // Gare desservie : la ligne du mini-réseau compte une gare dans le 78.
        DB::table('stations')->where('id', $this->destination->id)->update(['department_code' => '78']);
        DB::table('station_lines')->insert(['station_id' => $this->destination->id, 'line_id' => $this->line->id, 'method' => 'test', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/events?department=78')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.line.slug', $this->line->slug);

        // Code en minuscules et département sans ligne.
        DB::table('lines')->where('id', $this->other->id)->update(['departements' => json_encode(['Corse-du-Sud'])]);
        $this->getJson('/api/v1/events?department=2a')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/events?department=75')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cursor_pagination_walks_every_event_once_and_keeps_the_filters(): void
    {
        $seen = [];
        $url = '/api/v1/events?per_page=1&to=1960';
        for ($page = 0; $page < 6 && $url !== null; $page++) {
            $response = $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
            $seen[] = $response->json('data.0.id');
            $url = $response->json('links.next');
            if ($url !== null) {
                $this->assertStringContainsString('to=1960', $url);
                $this->assertNotNull($response->json('meta.next_cursor'));
            }
        }

        $this->assertNull($url, 'la dernière page n\'a pas de page suivante');
        $this->assertCount(4, $seen);
        $this->assertCount(4, array_unique($seen));
    }

    public function test_cursor_stays_valid_when_an_event_is_added_between_two_calls(): void
    {
        $page1 = $this->getJson('/api/v1/events?per_page=2')->assertOk();
        $this->event($this->line, 'other', '1700-01-01', 'manual', 'year');

        $next = $page1->json('links.next');
        $this->getJson($next)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'closure_passenger');
    }

    public function test_rejects_invalid_filters(): void
    {
        $this->getJson('/api/v1/events?type=teleportation')->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->getJson('/api/v1/events?line=inconnue')->assertUnprocessable()->assertJsonValidationErrors('line');
        $this->getJson('/api/v1/events?department=99')->assertUnprocessable()->assertJsonValidationErrors('department');
        $this->getJson('/api/v1/events?from=1930-02-31')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson('/api/v1/events?to=hier')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/events?order=random')->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->getJson('/api/v1/events?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/events?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_a_cursor_must_come_from_this_list(): void
    {
        $this->getJson('/api/v1/events?cursor=pas-un-curseur')->assertUnprocessable()->assertJsonValidationErrors('cursor');

        // Curseur d'une autre liste (médias : trié par identifiant seul) : 422, pas une erreur 500.
        $mediaCursor = base64_encode(json_encode(['media.id' => 1, '_pointsToNextItems' => true]));
        $this->getJson('/api/v1/events?cursor='.rtrim(strtr($mediaCursor, '+/', '-_'), '='))->assertUnprocessable()->assertJsonValidationErrors('cursor');
    }

    public function test_an_event_type_unknown_to_the_enum_does_not_break_the_list(): void
    {
        $this->event($this->line, 'type_futur', '1950-01-01', 'manual', 'day');

        $this->getJson('/api/v1/events?from=1950&to=1950')->assertOk()
            ->assertJsonPath('data.0.type', 'type_futur')
            ->assertJsonPath('data.0.provenance.provider', 'vff');
    }

    public function test_a_source_missing_from_the_rights_registry_has_no_provider(): void
    {
        $this->event($this->line, 'other', '1951-01-01', 'source-inconnue', 'day');

        $this->getJson('/api/v1/events?from=1951&to=1951')->assertOk()
            ->assertJsonPath('data.0.provenance', ['provider' => null, 'name' => null, 'license' => null, 'license_status' => null]);
    }

    public function test_show_returns_the_quote_the_section_and_the_provenance(): void
    {
        $id = LineEvent::query()->where('type', 'closure_passenger')->value('id');

        $this->getJson("/api/v1/events/{$id}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.type', 'closure_passenger')
            ->assertJsonPath('data.date', '1938-11-01')
            ->assertJsonPath('data.source_quote', 'Le service des voyageurs est supprimé le 1er novembre 1938.')
            ->assertJsonPath('data.source_url', 'https://fr.wikipedia.org/wiki/Ligne_de_Morlaix_a_Roscoff')
            ->assertJsonPath('data.provenance.provider', 'wikipedia')
            ->assertJsonPath('data.provenance.license_status', 'verified')
            ->assertJsonPath('data.section_pk_end_m', 27300)
            ->assertJsonPath('data.section_resolution', 'pk')
            ->assertJsonPath('data.line.slug', 'morlaix-roscoff')
            ->assertJsonStructure(['data' => ['reuse', 'heritage_kind', 'section_length_m']]);
    }

    public function test_show_unknown_or_oversized_id_is_a_404(): void
    {
        $this->getJson('/api/v1/events/999999999')->assertNotFound();
        $this->getJson('/api/v1/events/99999999999999999999')->assertNotFound();
        $this->getJson('/api/v1/events/abc')->assertNotFound();
    }

    public function test_station_is_not_needed_to_read_line_events(): void
    {
        $this->assertSame(0, Station::query()->where('department_code', '29')->count());
        $this->getJson('/api/v1/events?department=29')->assertOk();
    }
}
