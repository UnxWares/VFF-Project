<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use App\Services\LineStations;
use App\Services\StationTimeline;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Terminus d'une ligne (LineStations::terminals, contrôle qualité du 2026-10-20) : la gare la plus
 * proche de chacun des deux bouts du tracé, à 3 km au plus, et non « la première et la dernière
 * gare à PK ». Sur la LGV Sud-Est, Le Creusot-TGV et Aix-en-Provence-TGV étaient marquées terminus ;
 * sur Limoges - Angoulême, un PK faux mettait Angoulême en tête de liste.
 */
class LineStationsTerminalsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        $this->line = Line::factory()->create(['code' => '900200', 'name' => 'Ligne de Paris-Austerlitz à Omega']);
        $era = Era::query()->firstOrFail();
        // Deux morceaux bout à bout (2,00 → 2,10 → 2,20° E) : ST_LineMerge les recolle.
        foreach ([[2.00, 2.10], [2.10, 2.20]] as [$a, $b]) {
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed', 'is_heritage' => false,
                'geom' => LineString::make([Point::makeGeodetic(47.50, $a), Point::makeGeodetic(47.50, $b)]),
            ]);
        }
    }

    private function station(string $name, float $lon, int $pk): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(47.50, $lon), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'sncf', 'provenance' => ['status' => 'sncf'], 'opened_at' => null,
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
            'pk_m' => $pk, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    /** @return array<string, bool> nom => terminus */
    private function terminals(): array
    {
        return collect((new LineStations)->forLine($this->line->id)['stations'])->pluck('is_terminal', 'name')->all();
    }

    public function test_terminus_are_the_stations_at_the_ends_of_the_track_not_the_first_and_last_pk(): void
    {
        // PK faux : « Omega » (au bout est) a le plus petit PK, elle est en tête de liste.
        $this->station('Omega', 2.198, 100);
        $this->station('Paris-Austerlitz', 2.001, 500);
        $this->station('Milieu', 2.10, 7_500);
        $this->station('Avant-dernière', 2.16, 12_000);

        $this->assertSame(['Omega' => true, 'Paris-Austerlitz' => true, 'Milieu' => false, 'Avant-dernière' => false], $this->terminals());
    }

    public function test_a_line_ending_in_open_country_has_no_terminus(): void
    {
        // LGV qui finit en pleine voie à une bifurcation : gares à 3,8 km et 4,5 km des bouts.
        $this->station('Le Creusot-TGV', 2.05, 5_000);
        $this->station('Aix-TGV', 2.14, 14_000);

        $this->assertSame(['Le Creusot-TGV' => false, 'Aix-TGV' => false], $this->terminals());
    }

    public function test_the_station_named_by_the_line_wins_at_its_end(): void
    {
        // Deux gares au bout ouest : celle que le nom de la ligne désigne, même un peu plus loin.
        $this->station('Paris-Austerlitz-Souterrain', 2.0003, 0);
        $this->station('Paris-Austerlitz', 2.002, 200);
        $this->station('Omega', 2.199, 15_000);

        $this->assertSame(['Paris-Austerlitz-Souterrain' => false, 'Paris-Austerlitz' => true, 'Omega' => true], $this->terminals());
    }

    public function test_the_ends_are_found_without_merging_the_track(): void
    {
        // Un tracé en double (deux ères) et un embranchement au nœud 2,10° E : les bouts restent 2,00 et 2,20° E.
        $era = Era::factory()->create(['year' => 1990]);
        foreach ([[[47.50, 2.00], [47.50, 2.10]], [[47.50, 2.10], [47.52, 2.12]]] as [$a, $b]) {
            LineSegment::factory()->create(['line_id' => $this->line->id, 'era_id' => $era->id,
                'geom' => LineString::make([Point::makeGeodetic(...$a), Point::makeGeodetic(...$b)])]);
        }
        $this->station('Paris-Austerlitz', 2.001, 0);
        $this->station('Milieu', 2.10, 7_500);
        $this->station('Omega', 2.199, 15_000);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->assertSame(['Paris-Austerlitz' => true, 'Milieu' => false, 'Omega' => true], $this->terminals());
        // Recoller la ligne (ST_LineMerge de tous les tracés) prenait 165 ms sur Paris - Marseille : seuls les bouts
        // des tracés sont lus.
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'ST_LineMerge'))));
    }

    public function test_a_line_made_only_of_a_loop_keeps_a_terminus_at_its_start(): void
    {
        // Boucle de trois tracés (aucun bout libre) : le point de départ du recollement, comme avant.
        LineSegment::query()->where('line_id', $this->line->id)->delete();
        $era = Era::query()->firstOrFail();
        foreach ([[[47.50, 2.00], [47.50, 2.01]], [[47.50, 2.01], [47.51, 2.005]], [[47.51, 2.005], [47.50, 2.00]]] as [$a, $b]) {
            LineSegment::factory()->create(['line_id' => $this->line->id, 'era_id' => $era->id,
                'geom' => LineString::make([Point::makeGeodetic(...$a), Point::makeGeodetic(...$b)])]);
        }
        $this->station('Paris-Austerlitz', 2.001, 0);

        $this->assertSame(['Paris-Austerlitz' => true], $this->terminals());
    }

    public function test_named_in_matches_whole_words_without_accents_or_case(): void
    {
        $this->assertTrue(LineStations::namedIn('Paris-Austerlitz', 'Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean'));
        $this->assertTrue(LineStations::namedIn('Angoulême', 'Ligne de Limoges-Bénédictins à ANGOULEME'));
        $this->assertTrue(LineStations::namedIn('Limoges-Bénédictins (Haute-Vienne)', 'Ligne de Limoges-Bénédictins à Angoulême'));
        $this->assertFalse(LineStations::namedIn('Paris-Austerlitz-Souterrain', 'Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean'));
        $this->assertFalse(LineStations::namedIn('Paris', 'Ligne de Parisot à Lexos'));
        $this->assertFalse(LineStations::namedIn('', 'Ligne de Paris à Lyon'));
    }

    public function test_distance_in_metres(): void
    {
        // 0,1° de longitude à 47,5° N ≈ 7 509 m.
        $this->assertEqualsWithDelta(7_509, LineStations::metres(2.00, 47.50, 2.10, 47.50), 5);
        $this->assertSame(0.0, LineStations::metres(2.0, 47.5, 2.0, 47.5));
    }

    /** Noms qui ne sont pas des gares (pseudo-gares de la fiche ligne, contrôle qualité du 2026-10-20). */
    public function test_names_of_companies_and_generic_points_are_not_stations(): void
    {
        $kind = fn (string $name) => StationTimeline::kind($name, true, false, false);
        $this->assertSame(StationTimeline::KIND_EXCLUDED, $kind('Chemin de Fer de Charente-Limousine'));
        $this->assertSame(StationTimeline::KIND_EXCLUDED, $kind('Chemins de fer départementaux de la Mayenne'));
        $this->assertSame(StationTimeline::KIND_EXCLUDED, $kind('Ancien bâtiment de la gare de Choisy-le-Roi'));
        $this->assertSame(StationTimeline::KIND_EXCLUDED, $kind('Vélorail du Larzac'));
        $this->assertSame(StationTimeline::KIND_TECHNICAL, $kind('Bifurcation'));
        $this->assertSame(StationTimeline::KIND_TECHNICAL, $kind('Poste 2'));
        // Une vraie gare dont le nom commence comme un mot réservé reste une gare.
        $this->assertSame(StationTimeline::KIND_STATION, $kind('Bifurcation-de-Saint-Pierre'));
        $this->assertSame(StationTimeline::KIND_STATION, $kind('Garage-lès-Moulins'));
        $this->assertSame(StationTimeline::KIND_STATION, $kind('Compiègne'));
    }
}
