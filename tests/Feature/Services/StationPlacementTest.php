<?php

namespace Tests\Feature\Services;

use App\Models\Line;
use App\Services\SectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gares nommées d'une ligne (schémas Wikipédia, lignes-oubliées) posées sur son
 * tracé (SectionResolver::placeStations), sur le tracé de référence des tests
 * du résolveur : ligne est-ouest à 47° N, d'Alphaville (2,0° E, PK 0) à
 * Gammaville (2,4° E, PK 30,395) par Betaville (2,2° E, PK 15,197) ;
 * 7 599 m par 0,1° de longitude.
 *
 * Une règle par test : gare connue homonyme (jamais de doublon), PK calé
 * (calage officiel, origine décalée, calage propre au schéma), extrapolation
 * bornée, nom approché sans preuve, commune traversée, repères mélangés.
 */
class StationPlacementTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = '901000';

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['poiMemo', 'communeMemo'] as $memo) {
            (new \ReflectionProperty(SectionResolver::class, $memo))->setValue(null, []);
        }
        Http::fake(['*' => Http::response([])]);

        $this->line = Line::factory()->create(['name' => 'Ligne de Alphaville à Gammaville', 'code' => self::CODE]);
        DB::statement(
            "INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
             VALUES (?, 30395, '[\"test\"]', ST_GeomFromText('MULTILINESTRING((2.0 47.0, 2.1 47.0, 2.2 47.0, 2.3 47.0, 2.4 47.0))', 4326), now(), now())",
            [$this->line->id],
        );
    }

    private function gare(string $name, float $lon, ?int $pk, string $code = self::CODE): void
    {
        DB::table('sncf_gares')->insert([
            'code_ligne' => $code, 'libelle' => $name, 'commune' => $name, 'pk_m' => $pk,
            'lon' => $lon, 'lat' => 47.0, 'voyageurs' => true, 'fret' => true,
        ]);
    }

    private function officialStations(): void
    {
        $this->gare('Alphaville', 2.0, 0);
        $this->gare('Betaville', 2.2, 15197);
        $this->gare('Gammaville', 2.4, 30395);
    }

    private function knownStation(string $name, float $lon, float $lat = 47.0005): void
    {
        DB::statement("INSERT INTO stations (slug, name, type, status, geom, created_at, updated_at)
            VALUES (?, ?, 'gare', 'closed', ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())", [str()->slug($name), $name, $lon, $lat]);
    }

    /** Commune dont le contour (± 0,02° de longitude) traverse le tracé, centre au nord de la voie. */
    private function crossedCommune(string $name, float $lon, float $lat = 47.005): void
    {
        $insee = (string) random_int(10000, 99999);
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, geom, created_at, updated_at)
            VALUES (?, 'commune-actuelle', ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())", [$insee, $name, mb_strtolower($name), $lon, $lat]);
        DB::table('commune_contours')->insert(['code_insee' => $insee, 'nom' => $name, 'departement' => '99',
            'geom' => DB::raw(sprintf('ST_Multi(ST_MakeEnvelope(%F, 46.98, %F, 47.02, 4326))', $lon - 0.02, $lon + 0.02)),
            'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  list<array{0: string, 1: ?int}>  $stations  [nom, PK]
     * @return array{placed: array<string, array{lon: float, lat: float, method: string, precision_m: ?int, pk_m: ?int, note: string}>, residuals: list<int>, groups: array<string, string>, loo: list<array{0: int, 1: int, 2: int, 3: string}>}
     */
    private function place(array $stations, string $group = 'schéma'): array
    {
        $in = array_map(fn ($s) => ['key' => $s[0], 'name' => $s[0], 'pk' => $s[1], 'group' => $group], $stations);

        return app(SectionResolver::class)->placeStations($this->line->id, $in);
    }

    private function assertLon(float $expected, array $placed, float $delta = 0.002): void
    {
        $this->assertEqualsWithDelta($expected, $placed['lon'], $delta);
        $this->assertEqualsWithDelta(47.0, $placed['lat'], 0.002);
    }

    public function test_known_station_of_the_same_name_keeps_its_exact_position(): void
    {
        $this->officialStations();
        $this->knownStation('Deltaville', 2.1, 47.001);

        $res = $this->place([['Alphaville', 0], ['Deltaville', 7700], ['Gammaville', 30395]]);

        $this->assertSame('station', $res['placed']['Deltaville']['method']);
        $this->assertNull($res['placed']['Deltaville']['precision_m'], 'précision de la gare connue');
        $this->assertEqualsWithDelta(2.1, $res['placed']['Deltaville']['lon'], 1e-9);
        $this->assertEqualsWithDelta(47.001, $res['placed']['Deltaville']['lat'], 1e-9);
    }

    public function test_unknown_station_is_placed_by_its_pk_on_the_official_calibration(): void
    {
        $this->officialStations();

        $res = $this->place([['Alphaville', 0], ['Betaville', 15197], ['Deltaville', 7599]]);

        $d = $res['placed']['Deltaville'];
        $this->assertSame('pk', $d['method']);
        $this->assertLon(2.1, $d);
        $this->assertSame(7599, $d['pk_m'], 'PK dans le repère officiel');
        $this->assertSame(SectionResolver::PK_PRECISION_OFFICIAL_M + (int) round(SectionResolver::PK_PRECISION_SLOPE * 7599), $d['precision_m']);
        $this->assertStringStartsWith('calage officiel de la ligne', $res['groups']['schéma']);
    }

    public function test_schema_with_another_pk_origin_is_shifted_onto_the_official_calibration(): void
    {
        $this->officialStations();

        $res = $this->place([['Alphaville', 100000], ['Betaville', 115197], ['Deltaville', 107599], ['Gammaville', 130395]]);

        $this->assertLon(2.1, $res['placed']['Deltaville']);
        $this->assertSame(7599, $res['placed']['Deltaville']['pk_m']);
        $this->assertStringContainsString('origine décalée de 100', $res['groups']['schéma']);
    }

    public function test_schema_without_official_calibration_is_calibrated_on_its_known_stations(): void
    {
        $this->knownStation('Alphaville', 2.0);
        $this->knownStation('Gammaville', 2.4);

        $res = $this->place([['Alphaville', 0], ['Deltaville', 7599], ['Gammaville', 30395]]);

        $d = $res['placed']['Deltaville'];
        $this->assertSame('pk', $d['method']);
        $this->assertLon(2.1, $d, 0.003);
        $this->assertNull($d['pk_m'], 'pas de repère officiel');
        $this->assertSame(SectionResolver::PK_PRECISION_STATIONS_M + (int) round(SectionResolver::PK_PRECISION_SLOPE * 7599), $d['precision_m']);
        $this->assertSame('calage du schéma sur 2 gares et 0 communes', $res['groups']['schéma']);
    }

    public function test_pk_is_never_extrapolated_more_than_2_km_beyond_the_control_points(): void
    {
        // Calage officiel d'Alphaville (PK 0) à Betaville (PK 15,197) seulement ; le tracé continue jusqu'à 2,4° E.
        $this->gare('Alphaville', 2.0, 0);
        $this->gare('Betaville', 2.2, 15197);

        $res = $this->place([['Alphaville', 0], ['Betaville', 15197], ['Epsilonville', 17000], ['Zetaville', 22796]]);

        $this->assertSame('pk', $res['placed']['Epsilonville']['method'], '1,8 km au-delà du dernier point de contrôle');
        $this->assertArrayNotHasKey('Zetaville', $res['placed'], '7,6 km au-delà');
    }

    public function test_approximate_name_of_a_known_station_without_pk_is_not_placed(): void
    {
        $this->officialStations();

        // « Betaville-Nord » n'est pas la gare de Betaville (nom seulement voisin) ; sans PK, rien ne le prouve.
        $res = $this->place([['Alphaville', null], ['Betaville-Nord', null]]);

        $this->assertSame('station', $res['placed']['Alphaville']['method']);
        $this->assertArrayNotHasKey('Betaville-Nord', $res['placed']);
    }

    public function test_approximate_name_far_from_its_pk_is_another_place_placed_by_its_pk(): void
    {
        $this->officialStations();

        $res = $this->place([['Alphaville', 0], ['Gammaville', 30395], ['Betaville-Nord', 22796]]);

        $this->assertSame('pk', $res['placed']['Betaville-Nord']['method']);
        $this->assertLon(2.3, $res['placed']['Betaville-Nord']);
    }

    public function test_known_station_named_more_precisely_is_used_only_where_the_pk_agrees(): void
    {
        $this->officialStations();
        // « Saint-Oustrille » (schéma) → gare connue « Saint-Oustrille-les-Bains » : même lieu, nom précisé.
        $this->knownStation('Saint-Oustrille-les-Bains', 2.3);

        $near = $this->place([['Alphaville', 0], ['Gammaville', 30395], ['Saint-Oustrille', 22000]]);
        $far = $this->place([['Alphaville', 0], ['Gammaville', 30395], ['Saint-Oustrille', 7599]], 'autre');

        $this->assertSame('station', $near['placed']['Saint-Oustrille']['method'], 'PK à moins de 2 km de la gare connue');
        $this->assertEqualsWithDelta(2.3, $near['placed']['Saint-Oustrille']['lon'], 1e-9);
        $this->assertArrayNotHasKey('Saint-Oustrille', $far['placed'], 'PK d\'un autre repère : rien plutôt qu\'un doublon');
    }

    public function test_known_station_of_the_same_name_off_the_trace_is_used_instead_of_a_duplicate(): void
    {
        $this->officialStations();
        // Gare connue à 3,3 km du tracé (terminus au-delà d'un tracé incomplet, gare d'une ligne voisine)…
        $this->knownStation('Omegaville', 2.3, 47.03);
        // … et une homonyme à 10 km : trop loin pour être la même.
        $this->knownStation('Psiville', 2.3, 47.09);

        $res = $this->place([['Alphaville', 0], ['Gammaville', 30395], ['Omegaville', 22796], ['Psiville', 22796]]);

        $o = $res['placed']['Omegaville'];
        $this->assertSame('station', $o['method']);
        $this->assertEqualsWithDelta(47.03, $o['lat'], 1e-9);
        $this->assertSame(22796, $o['pk_m'], 'PK de la source gardé (rattachement)');
        $this->assertStringContainsString('« Omegaville » à 33', $o['note']);
        $this->assertSame('pk', $res['placed']['Psiville']['method']);
        $this->assertLon(2.3, $res['placed']['Psiville']);
    }

    public function test_station_without_pk_is_placed_on_the_trace_in_its_crossed_commune(): void
    {
        $this->officialStations();
        $this->crossedCommune('Thetabourg', 2.3, 47.002);
        $this->crossedCommune('Iotabourg', 2.1, 47.01);

        $res = $this->place([['Thetabourg', null], ['Iotabourg', null]]);

        $t = $res['placed']['Thetabourg'];
        $this->assertSame('commune', $t['method']);
        $this->assertLon(2.3, $t);
        $this->assertSame(SectionResolver::COMMUNE_PRECISION_M, $t['precision_m'], 'centre à 222 m du tracé : au moins 500 m');
        $this->assertEqualsWithDelta(1112, $res['placed']['Iotabourg']['precision_m'], 3, 'centre à 1,1 km du tracé : cette distance');
    }

    public function test_commune_that_the_trace_does_not_cross_places_nothing(): void
    {
        $this->officialStations();
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, geom, created_at, updated_at)
            VALUES ('99998', 'commune-actuelle', 'Iotabourg', 'iotabourg', ST_SetSRID(ST_MakePoint(2.3, 47.02), 4326), now(), now())");

        $this->assertSame([], $this->place([['Iotabourg', null]])['placed']);
    }

    public function test_pk_far_from_the_crossed_homonym_commune_places_nothing(): void
    {
        $this->officialStations();
        $this->crossedCommune('Thetabourg', 2.3);

        // PK 5 → 2,066° E : à 17 km de la commune homonyme que le tracé traverse (repères mélangés).
        $res = $this->place([['Alphaville', 0], ['Gammaville', 30395], ['Thetabourg', 5000]]);

        $this->assertArrayNotHasKey('Thetabourg', $res['placed']);
    }

    public function test_line_without_trace_places_nothing(): void
    {
        $other = Line::factory()->create(['code' => '902000']);

        $res = app(SectionResolver::class)->placeStations($other->id, [['key' => 'a', 'name' => 'Alphaville', 'pk' => 0, 'group' => 'g']]);

        $this->assertSame([], $res['placed']);
    }
}
