<?php

namespace Tests\Feature\Accidents;

use App\Enums\AccidentLocationPrecision;
use App\Models\Accident;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentLineMatcher;
use App\Services\Accidents\AccidentLocator;
use App\Services\StationChronology;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Un lieu connu seulement à la commune près (centre de la commune) n'est « à X m » d'aucune gare :
 * pas de gare la plus proche, ni dans la fiche, ni dans la chronologie de la gare.
 */
class NearestStationPrecisionTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private function candidate(?AccidentLocationPrecision $precision): AccidentCandidate
    {
        $c = new AccidentCandidate('test:paris');
        $c->date = '1891-07-12';
        // Exactement à la gare de Paris-Saint-Lazare du mini-réseau.
        $c->lat = 48.8763;
        $c->lng = 2.3253;
        $c->locationPrecision = $precision;

        return $c;
    }

    public function test_a_precise_position_is_attached_to_the_station(): void
    {
        $this->buildRailNetwork();
        $matcher = app(AccidentLineMatcher::class);

        foreach ([AccidentLocationPrecision::Exact, AccidentLocationPrecision::Station, AccidentLocationPrecision::LevelCrossing] as $precision) {
            $station = $matcher->nearestStation($this->candidate($precision));
            $this->assertSame($this->origin->id, $station['id'] ?? null, $precision->value);
            $this->assertLessThan(50, $station['distance_m']);
        }
    }

    public function test_a_commune_centre_or_an_unknown_precision_has_no_nearest_station(): void
    {
        $this->buildRailNetwork();
        $matcher = app(AccidentLineMatcher::class);

        $this->assertNull($matcher->nearestStation($this->candidate(AccidentLocationPrecision::Commune)));
        $this->assertNull($matcher->nearestStation($this->candidate(null)));
    }

    public function test_the_station_chronology_ignores_commune_only_accidents(): void
    {
        $this->buildRailNetwork();
        foreach ([['commune', '1891-07-12-paris'], ['station', '1893-11-01-paris']] as [$precision, $slug]) {
            Accident::factory()->create(['slug' => $slug, 'occurred_on' => substr($slug, 0, 10),
                'nearest_station_id' => $this->origin->id, 'nearest_station_distance_m' => 0,
                'location_precision' => $precision, 'geom' => Point::makeGeodetic(48.8763, 2.3253)]);
        }

        $slugs = array_column(app(StationChronology::class)->accidents($this->origin->id, 'fr'), 'slug');

        $this->assertSame(['1893-11-01-paris'], $slugs);
    }

    public function test_a_wikidata_point_on_the_commune_centre_is_a_commune_position_but_a_real_point_is_not(): void
    {
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, geom, created_at, updated_at)
            VALUES ('75056', 'commune-actuelle', 'Paris', 'paris', '75', ST_SetSRID(ST_MakePoint(2.3522, 48.8566), 4326), now(), now())");
        DB::table('commune_contours')->insert(['code_insee' => '75056', 'nom' => 'Paris', 'departement' => '75',
            'geom' => DB::raw('ST_Multi(ST_MakeEnvelope(2.22, 48.81, 2.47, 48.90, 4326))'), 'created_at' => now(), 'updated_at' => now()]);
        $locator = app(AccidentLocator::class);

        $centre = new AccidentCandidate('wikidata:Q1');
        $centre->lat = 48.8566;
        $centre->lng = 2.3522;
        $locator->locate($centre);
        $this->assertSame(AccidentLocationPrecision::Commune, $centre->locationPrecision);

        $real = new AccidentCandidate('wikidata:Q2');
        $real->lat = 48.8895;
        $real->lng = 2.3590;
        $locator->locate($real);
        $this->assertSame(AccidentLocationPrecision::Exact, $real->locationPrecision);
    }

    public function test_a_station_named_as_an_approach_is_never_a_position_in_station(): void
    {
        $this->buildRailNetwork();
        $locator = app(AccidentLocator::class);

        // « En gare de Paris-Saint-Lazare » : la gare est le lieu, à 0 m.
        $inStation = new AccidentCandidate('wikipedia-list:in');
        $inStation->communeName = 'Paris-Saint-Lazare';
        $locator->locate($inStation);
        $this->assertSame(AccidentLocationPrecision::Station, $inStation->locationPrecision);

        // « À l'approche de Paris-Saint-Lazare » (lieu approximatif) : la gare retrouvée par son nom ne donne qu'une
        // position de commune, qui n'est « à X m » d'aucune gare.
        $approach = new AccidentCandidate('wikipedia-list:approach');
        $approach->communeName = 'Paris-Saint-Lazare';
        $approach->approximatePlace = true;
        $locator->locate($approach);
        $this->assertSame(AccidentLocationPrecision::Commune, $approach->locationPrecision);
        $this->assertNull(app(AccidentLineMatcher::class)->nearestStation($approach));
    }
}
