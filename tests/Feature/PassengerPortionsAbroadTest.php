<?php

namespace Tests\Feature;

use App\Services\PassengerPortions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PassengerPortions::abroad : bout de ligne à l'étranger (branché au réseau voyageurs du pays
 * voisin). Cas 930000 : la gare de Vintimille est à 0,09° de la dernière commune française.
 */
class PassengerPortionsAbroadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // « Menton » : commune frontalière, la frontière suit la longitude 7,53.
        DB::table('commune_contours')->insert(['code_insee' => '06083', 'nom' => 'Menton', 'departement' => '06',
            'geom' => DB::raw("ST_GeomFromText('MULTIPOLYGON(((7.40 43.70, 7.53 43.70, 7.53 43.85, 7.40 43.85, 7.40 43.70)))', 4326)"),
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function abroad(float $lon, float $lat): bool
    {
        return app(PassengerPortions::class)->abroad(['lon' => $lon, 'lat' => $lat]);
    }

    public function test_ventimiglia_station_six_km_beyond_the_border_is_abroad(): void
    {
        $this->assertTrue($this->abroad(7.608956, 43.793136), 'Vintimille, à 0,079° de la commune la plus proche');
        $this->assertTrue($this->abroad(7.56, 43.79), 'juste au-delà de la frontière');
    }

    public function test_points_in_france_or_far_from_any_loaded_commune_are_not_abroad(): void
    {
        $this->assertFalse($this->abroad(7.50, 43.78), 'dans la commune');
        $this->assertFalse($this->abroad(7.531, 43.78), 'à moins de 300 m de la commune, sans rails étrangers');
        $this->assertFalse($this->abroad(7.80, 43.79), 'à plus de ABROAD_NEAR_FRANCE_DEG de toute commune chargée');
    }

    public function test_radius_constant(): void
    {
        $this->assertGreaterThan(0.09, PassengerPortions::ABROAD_NEAR_FRANCE_DEG, 'Vintimille - Menton');
        $this->assertLessThanOrEqual(0.25, PassengerPortions::ABROAD_NEAR_FRANCE_DEG, 'reste un garde-fou local');
    }
}
