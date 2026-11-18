<?php

namespace Tests\Feature\Api;

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

    public function test_route_requires_from_and_to(): void
    {
        $this->getJson('/api/v1/route')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
    }
}
