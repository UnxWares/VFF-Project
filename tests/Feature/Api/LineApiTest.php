<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * API v1 par ligne (historique, arrêts, signalisation, PK) + recherche.
 */
class LineApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_history(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/history")
            ->assertOk()
            ->assertJsonPath('line.slug', $this->line->slug)
            ->assertJsonPath('line.type', 'passenger')
            ->assertJsonPath('events.0.type', 'opening')
            ->assertJsonPath('events.0.date', '1839-08-02')
            ->assertJsonCount(2, 'status_by_era')
            ->assertJsonPath('status_by_era.0.year', 1960)
            ->assertJsonPath('status_by_era.1.year', 2030)
            ->assertJsonPath('status_by_era.1.dominant_status', 'in_service')
            ->assertJsonPath('status_by_era.1.electrified_count', 1);
    }

    public function test_stops_default_to_current_era(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/stops")
            ->assertOk()
            ->assertJsonPath('era_year', 2030)
            ->assertJsonCount(2, 'stops')
            ->assertJsonStructure(['line' => ['slug', 'code', 'name'], 'era_year', 'stops' => [[
                'position', 'pk_m', 'pk_label', 'effective_status', 'is_terminal',
                'station' => ['slug', 'name', 'type', 'city', 'lat', 'lng'],
            ]]])
            ->assertJsonPath('stops.0.station.slug', 'paris-saint-lazare')
            ->assertJsonPath('stops.1.pk_label', '17+250');
    }

    public function test_stops_for_past_era_and_unknown_era(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/stops?era=1960")
            ->assertOk()
            ->assertJsonPath('era_year', 1960)
            ->assertJsonCount(0, 'stops');

        $this->getJson("/api/v1/lines/{$this->line->slug}/stops?era=1850")->assertNotFound();
        $this->getJson("/api/v1/lines/{$this->line->slug}/stops?era=abc")->assertUnprocessable();
    }

    public function test_signalling(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/signalling")
            ->assertOk()
            ->assertJsonPath('era_year', 2030)
            ->assertJsonCount(1, 'ranges')
            ->assertJsonPath('ranges.0.signalling', 'BAL')
            ->assertJsonPath('ranges.0.train_protection', ['KVB'])
            ->assertJsonPath('ranges.0.kvb_equipped', true)
            ->assertJsonPath('ranges.0.length_m', 17_250);
    }

    public function test_pk_from_point_snaps_on_the_line(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.8763&lng=2.3253")
            ->assertOk()
            ->assertJsonPath('pk_m', 0)
            ->assertJsonPath('pk_label', '0+000')
            ->assertJsonStructure(['line', 'pk_m', 'pk_label', 'total_m', 'snap' => ['lat', 'lng', 'distance_from_input_m']]);

        $this->getJson("/api/v1/lines/{$this->line->slug}/pk")->assertUnprocessable();
    }

    public function test_line_endpoints_unknown_slug_is_404(): void
    {
        foreach (['history', 'stops', 'signalling'] as $endpoint) {
            $this->getJson("/api/v1/lines/inconnue/{$endpoint}")->assertNotFound();
        }
    }

    public function test_search(): void
    {
        $this->getJson('/api/v1/search?q=versailles')
            ->assertOk()
            ->assertJsonPath('lines.0.slug', $this->line->slug)
            ->assertJsonPath('stations.0.slug', 'versailles-rive-droite')
            ->assertJsonStructure(['lines', 'stations', 'materials']);

        // Moins de 2 caractères : réponse vide, pas d'erreur.
        $this->getJson('/api/v1/search?q=v')
            ->assertOk()
            ->assertJsonCount(0, 'lines')
            ->assertJsonCount(0, 'stations');
    }

    public function test_search_returns_the_department_code_of_stations(): void
    {
        $this->origin->forceFill(['department_code' => '75'])->save();
        $this->destination->forceFill(['department_code' => null])->save();

        $this->getJson('/api/v1/search?q=paris')
            ->assertOk()
            ->assertJsonPath('stations.0.slug', 'paris-saint-lazare')
            ->assertJsonPath('stations.0.city', 'Paris')
            ->assertJsonPath('stations.0.department_code', '75');

        // Département inconnu : la clé est présente et vide (l'affichage ne met alors pas de parenthèses).
        $this->getJson('/api/v1/search?q=versailles')
            ->assertOk()
            ->assertJsonPath('stations.0.slug', 'versailles-rive-droite')
            ->assertJsonPath('stations.0.department_code', null)
            ->assertJsonStructure(['stations' => [['type', 'slug', 'name', 'city', 'department_code', 'lat', 'lng']]]);
    }

    /** @return list<string> */
    private function searchSlugs(string $q, string $kind): array
    {
        return array_column($this->getJson('/api/v1/search?q='.rawurlencode($q))->assertOk()->json($kind), 'slug');
    }

    private function searchLine(string $slug, string $name, ?string $code = null, string $status = 'in_service'): Line
    {
        $line = Line::factory()->create(['slug' => $slug, 'name' => $name, 'code' => $code]);
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->currentEra->id, 'status' => $status]);

        return $line;
    }

    public function test_search_lines_exact_name_or_code_come_first(): void
    {
        $this->searchLine('l-zz-long', 'Ligne de Vire à Romagny');
        $this->searchLine('l-code', 'Autre ligne', '123 456');
        $this->searchLine('l-debut', 'Vire-Nord');
        $this->searchLine('l-exact', 'Vire');
        $this->searchLine('l-code-prefix', 'Zone', '123 4567');

        $this->assertSame(['l-exact', 'l-debut', 'l-zz-long'], $this->searchSlugs('vire', 'lines'));
        // Le code exact passe avant un code qui ne fait que commencer par la saisie ; espaces du code ignorés.
        $this->assertSame(['l-code', 'l-code-prefix'], $this->searchSlugs('123456', 'lines'));
        $this->assertSame(['l-code', 'l-code-prefix'], $this->searchSlugs('123 456', 'lines'));
    }

    public function test_search_lines_name_start_comes_before_a_word_of_the_name_then_the_rest(): void
    {
        $this->searchLine('l-reste', 'Embranchement de Chevire');
        $this->searchLine('l-mot', 'Ligne de Vire à Romagny');
        $this->searchLine('l-apostrophe', 'Ligne d\'Aix à Vire');
        $this->searchLine('l-tiret', 'Condé-Vire');
        $this->searchLine('l-debut', 'Virevoltes');

        $this->assertSame(['l-debut', 'l-tiret', 'l-apostrophe', 'l-mot', 'l-reste'], $this->searchSlugs('vire', 'lines'));
    }

    public function test_search_lines_in_service_come_before_closed_ones_at_equal_relevance(): void
    {
        $this->searchLine('l-fermee-a', 'Ligne Alpha', null, 'abandoned');
        $this->searchLine('l-ouverte-b', 'Ligne Bravo');
        $this->searchLine('l-voie-unique', 'Ligne Charlie', null, 'single_track');
        $this->searchLine('l-sans-trace', 'Ligne Delta');
        LineSegment::where('line_id', Line::where('slug', 'l-sans-trace')->value('id'))->delete();

        $this->assertSame(['l-ouverte-b', 'l-voie-unique', 'l-fermee-a', 'l-sans-trace'], $this->searchSlugs('ligne', 'lines'));
        // La pertinence reste prioritaire : un nom exact fermé passe avant un début de nom ouvert.
        $this->searchLine('l-exacte-fermee', 'Ligne', null, 'dismantled');
        $this->assertSame('l-exacte-fermee', $this->searchSlugs('ligne', 'lines')[0]);
    }

    public function test_search_lines_equal_relevance_are_in_french_alphabetical_order(): void
    {
        $this->searchLine('l-3', 'Ligne Zèbre');
        $this->searchLine('l-1', 'Ligne Éole');
        $this->searchLine('l-2', 'Ligne Zèbres');
        $this->searchLine('l-0', 'Ligne Ébène');

        // « É » se range avec « E » (collation française), pas après « Z ».
        $this->assertSame(['l-0', 'l-1', 'l-3', 'l-2'], $this->searchSlugs('ligne', 'lines'));
    }

    public function test_search_stations_are_ranked_by_relevance_then_alphabetically(): void
    {
        foreach ([
            'condé-sur-vire' => 'Condé-sur-Vire',
            'evires' => 'Évires',
            'embranchement' => 'Chevire',
            'vire' => 'Vire',
            'vireux' => 'Vireux-Molhain',
            'vire-nord' => 'Vire-Est',
            'saint-vire' => 'Saint Vire',
        ] as $slug => $name) {
            Station::factory()->create(['slug' => $slug, 'name' => $name]);
        }

        // Exact, début (alphabétique : Vire-Est, Vireux-Molhain), mot (Condé-sur-Vire, Saint Vire), reste (Chevire, Évires).
        $this->assertSame(
            ['vire', 'vire-nord', 'vireux', 'condé-sur-vire', 'saint-vire', 'embranchement', 'evires'],
            $this->searchSlugs('vire', 'stations'),
        );
    }

    public function test_route_requires_from_and_to(): void
    {
        $this->getJson('/api/v1/route')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
    }
}
