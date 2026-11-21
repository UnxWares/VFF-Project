<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\LineStations;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Points qui ne sont pas des gares (vff:build-station-eras, StationTimeline::kind) :
 * un point de liste-des-gares sans aucune preuve d'ouverture au public (LGV
 * Sud-Est : Marolles-TGV, Vaumort, Tonnerre-TGV…) n'apparaît ni dans la liste
 * des gares de la ligne, ni dans les compteurs, ni sur la carte, ni dans
 * l'annuaire ; une gare projetée jamais ouverte est « projetée », jamais
 * « fermée ».
 */
class StationKindsTest extends TestCase
{
    use RefreshDatabase;

    private Line $lgv;

    private Line $classic;

    protected function setUp(): void
    {
        parent::setUp();
        $past = Era::factory()->create(['year' => 1990]);
        $current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->lgv = Line::factory()->create(['code' => '752000', 'name' => 'LGV Sud-Est', 'is_lgv' => true]);
        $this->classic = Line::factory()->create(['code' => '010000', 'name' => 'Ligne classique', 'is_lgv' => false]);
        foreach ([$past, $current] as $era) {
            foreach ([[$this->lgv, 47.0], [$this->classic, 47.5]] as [$line, $lat]) {
                LineSegment::factory()->create([
                    'line_id' => $line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
                    'geom' => LineString::make([Point::makeGeodetic($lat, 2.00), Point::makeGeodetic($lat, 2.40)]),
                ]);
            }
        }
        DB::table('line_events')->insert(['line_id' => $this->lgv->id, 'type' => 'opening', 'event_date' => '1981-09-27', 'date_precision' => 'day',
            'description' => 'Ouverture', 'confidence' => 'high', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param  array<string, array{0: string, 1?: bool, 2?: bool}>  $sources  source => [cycle de vie, voyageurs, fret] */
    private function point(string $name, Line $line, float $lon, int $pk, array $sources, string $sourceStatus = 'declassified'): Station
    {
        $lat = $line->is($this->lgv) ? 47.0 : 47.5;
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic($lat, $lon), 'status' => $sourceStatus === 'gone' ? 'declassified' : $sourceStatus,
            'source_status' => $sourceStatus, 'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'], 'opened_at' => null,
            'lifecycle' => collect($sources)->contains(fn ($s) => $s[0] === 'planned') ? 'planned' : 'disused',
        ]);
        foreach ($sources as $source => $s) {
            DB::table('station_sources')->insert([
                'source' => $source, 'source_ref' => "{$name}-{$source}", 'station_id' => $station->id, 'name' => $name,
                'lifecycle' => $s[0], 'passenger' => $s[1] ?? null, 'freight' => $s[2] ?? null,
                'seen_at' => '2026-10-15 17:08:34', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_SetSRID(ST_MakePoint({$lon}, {$lat}), 4326)"),
            ]);
        }
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
            'pk_m' => $pk, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    public function test_technical_points_and_planned_stations_are_not_stations_of_the_line(): void
    {
        $tgv = $this->point('Mâcon-Loché-TGV', $this->lgv, 2.05, 5_000, ['sncf' => ['active', true, false], 'sncf_voyageurs' => ['active']], 'active');
        $poste = $this->point('Tonnerre-TGV', $this->lgv, 2.10, 10_000, ['sncf' => ['disused', false, false]]);
        $projected = $this->point('Vandières TGV', $this->lgv, 2.15, 15_000, ['wikidata' => ['planned']], 'gone');
        $garage = $this->point('Azincourt-Garage', $this->classic, 2.10, 10_000, ['sncf' => ['disused', false, false]]);
        // Ancienne gare d'une ligne classique, connue par la seule liste SNCF : reste une gare.
        $former = $this->point('Vertus', $this->classic, 2.20, 20_000, ['sncf' => ['disused', false, false]]);

        $this->artisan('vff:build-station-eras', ['--today' => '2026-10-20'])->assertSuccessful();

        $kind = fn (Station $s) => $s->fresh()->provenance['kind'] ?? null;
        $this->assertSame([null, 'technical', 'projected', 'technical', null],
            [$kind($tgv), $kind($poste), $kind($projected), $kind($garage), $kind($former)]);
        // Ni ère (carte, line_stops), ni date déduite, ni fermeture.
        foreach ([$poste, $projected, $garage] as $s) {
            $this->assertSame(0, DB::table('station_eras')->where('station_id', $s->id)->count(), $s->name);
            $this->assertSame(0, DB::table('station_events')->where('station_id', $s->id)->where('is_inferred', true)->count(), $s->name);
            $this->assertNull($s->fresh()->closed_at, $s->name);
        }
        $this->assertNotNull($former->fresh()->closed_at, 'ancienne gare : date de fermeture (intervalle)');

        // Fiche ligne : gares seulement ; points techniques à part ; gare projetée sans état.
        $data = (new LineStations)->forLine($this->lgv->id);
        $this->assertSame(['Mâcon-Loché-TGV', 'Vandières TGV'], array_column($data['stations'], 'name'));
        $this->assertSame(['station', 'projected'], array_column($data['stations'], 'kind'));
        $this->assertSame(['Tonnerre-TGV'], array_column($data['technical_points'], 'name'));
        $this->assertNull($data['stations'][1]['closed_at']);
        $this->assertNull($data['stations'][1]['eras'][1990]['status']);
        // Pas de terminus : Mâcon-Loché-TGV est à 3,8 km du bout du tracé (> LineStations::TERMINUS_MAX_M),
        // une gare intermédiaire de LGV, plus « la première gare à PK » (contrôle qualité du 2026-10-20).
        $this->assertSame([false, false], array_column($data['stations'], 'is_terminal'));
        $counts = collect((new LineStations)->byEra($this->lgv->id))->keyBy('year');
        $this->assertSame([1, 0, 0, []], [$counts[2030]['open'], $counts[2030]['closed'], $counts[2030]['uncertain'], $counts[2030]['closures']]);

        // Carte : ni le poste ni la gare projetée.
        /** @var list<array<string, mixed>> $rawFeatures */
        $rawFeatures = $this->getJson('/api/v1/eras/2030/stations.geojson')->assertOk()->json('features');
        $names = collect($rawFeatures)->pluck('properties.name')->all();
        $this->assertContains('Mâcon-Loché-TGV', $names);
        $this->assertNotContains('Tonnerre-TGV', $names);
        $this->assertNotContains('Vandières TGV', $names);

        // Annuaire : pas de point technique ; la gare projetée est « projetée ».
        $this->get('/stations')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 3)
            ->where('counts.planned', 1)
            ->where('stations.data', fn ($rows) => collect($rows)->firstWhere('name', 'Vandières TGV')['status'] === 'planned'
                && collect($rows)->pluck('name')->doesntContain('Tonnerre-TGV'))
            ->etc());
        // Fiche gare : statut lisible.
        $this->get('/stations/'.$poste->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('station.status', 'technical')->etc());
        $this->get('/stations/'.$projected->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->where('station.status', 'planned')->etc());
    }

    public function test_a_new_proof_turns_a_technical_point_back_into_a_station(): void
    {
        $poste = $this->point('Pasilly', $this->lgv, 2.10, 10_000, ['sncf' => ['disused', false, false]]);
        $this->artisan('vff:build-station-eras', ['--today' => '2026-10-20'])->assertSuccessful();
        $this->assertSame('technical', $poste->fresh()->provenance['kind'] ?? null);

        DB::table('station_events')->insert(['station_id' => $poste->id, 'type' => 'opening', 'event_date' => '1981-09-27', 'date_precision' => 'day',
            'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('vff:build-station-eras', ['--today' => '2026-10-20'])->assertSuccessful();
        $this->assertArrayNotHasKey('kind', $poste->fresh()->provenance);
        $this->assertGreaterThan(0, DB::table('station_eras')->where('station_id', $poste->id)->count());
    }
}
