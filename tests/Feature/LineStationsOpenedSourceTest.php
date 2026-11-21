<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\LineStations;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Liste des gares de la fiche ligne : la provenance de la date d'ouverture accompagne la date, pour
 * que la liste dise « ouverte : 1866 (date de la ligne) » quand la date vient de l'ouverture de toute
 * la ligne (Messei sur la 405000, ouverte en 1866 selon Wikidata pour la ligne entière, absente de la
 * carte de 1870) et non d'une source sur la gare elle-même.
 */
class LineStationsOpenedSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_provenance_de_l_ouverture_est_transmise(): void
    {
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $line = Line::factory()->create(['code' => '900310', 'name' => 'Ligne témoin des ouvertures']);
        $inferred = $this->station($line, 'Halte déduite', 2.00, 1000, ['opened_at' => 'inferred:line_event']);
        $sourced = $this->station($line, 'Gare sourcée', 2.10, 2000, ['opened_at' => 'wikidata']);
        $unknown = $this->station($line, 'Gare sans provenance', 2.20, 3000, []);

        $bySlug = collect(app(LineStations::class)->forLine($line->id)['stations'])->keyBy('slug');

        $this->assertSame('inferred:line_event', $bySlug[$inferred->slug]['opened_source']);
        $this->assertSame('wikidata', $bySlug[$sourced->slug]['opened_source']);
        $this->assertNull($bySlug[$unknown->slug]['opened_source']);

        // La fiche ligne transmet la provenance à la liste des gares (LineSheet::stationProps).
        $this->get("/lines/{$line->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stations.0.slug', $inferred->slug)
            ->where('stations.0.opened_source', 'inferred:line_event')
            ->where('stations.1.opened_source', 'wikidata'));
    }

    /** @param  array<string, string>  $provenance */
    private function station(Line $line, string $name, float $lon, int $pk, array $provenance): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(47.50, $lon), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'sncf', 'provenance' => $provenance + ['status' => 'sncf'],
            'opened_at' => '1866-01-01', 'opened_precision' => 'year',
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => $pk,
            'pk_source' => 'sncf', 'distance_m' => 0, 'first_year' => 1866, 'last_year' => null, 'years_source' => 'line_events',
            'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }
}
