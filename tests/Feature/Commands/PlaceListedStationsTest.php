<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use App\Services\StationPositioning\IgnPlaceSearch;
use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:place-listed-stations : gares des schémas de ligne posées par leur PK, recalées par
 * le calage robuste « PK = distance » (gares de position fiable, lieux-dits IGN).
 *
 * Cas réel : ligne de Montsecret - Vassy aux Maures (19,6 km). Le schéma Wikipédia donne
 * Montsecret - Vassy au PK 0 et Les Maures au PK 17,525, qui est son PK sur la ligne de
 * Vire à Romagny ; l'ancien calage étirait donc toutes les haltes de 11 % (le Paillot à
 * 1,5 km de son lieu-dit). Axe droit en Lambert-93, d'ouest en est : abscisse = x - X0.
 */
class PlaceListedStationsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const X0 = 400000.0;

    private const Y0 = 6850000.0;

    private const LENGTH = 19440.0;

    private const GROUP = 'wp:Ligne de Montsecret - Vassy aux Maures#1.0';

    private Line $line;

    /** @var array<string, int> nom → stations.id */
    private array $ids = [];

    /** @var array<string, array{0: float, 1: float}> lieux-dits renvoyés par le géocodage IGN (x, y Lambert-93) */
    private array $places = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        IgnPlaceSearch::forget();
        Sleep::fake();
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $features = [];
            foreach ($this->places as $name => [$x, $y]) {
                if (mb_strtolower($name) === mb_strtolower((string) ($q['q'] ?? ''))) {
                    [$lon, $lat] = $this->wgs84($x, $y);
                    $features[] = ['geometry' => ['coordinates' => [$lon, $lat]],
                        'properties' => ['toponym' => $name, 'category' => ['lieu-dit habité', "zone d'habitation"], 'city' => ['Tinchebray-Bocage']]];
                }
            }

            return Http::response(['features' => $features]);
        });

        $this->line = Line::factory()->create(['name' => 'Ligne de Montsecret aux Maures', 'code' => null, 'network' => 'secondaire']);
        DB::statement(<<<'SQL'
            INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, 19440, '{"archeoferroviaire": 19440}', ST_Multi(ST_Transform(ST_Segmentize(
                ST_SetSRID(ST_MakeLine(ST_MakePoint(?, ?), ST_MakePoint(?, ?)), 2154), 100), 4326)), NOW(), NOW())
        SQL, [$this->line->id, self::X0, self::Y0, self::X0 + self::LENGTH, self::Y0]);

        // Gares de position fiable (SNCF) : Montsecret - Vassy au début de l'axe, Les Maures 250 m à côté du bout.
        $this->reliable('Montsecret - Vassy', 0, 0, 0, ['code_ligne' => '405000', 'pk_m' => 56770]);
        $this->reliable('Les Maures', self::LENGTH, -250, 17525, ['code_ligne' => '440000', 'pk_m' => 17525]);
        // Haltes posées par l'ancien calage, étiré entre ces deux gares (pente 19 440 / 17 525).
        $this->listed('Tinchebray', 7400, 7400 * self::LENGTH / 17525, 520);
        $this->listed('Le Paillot', 14400, 14400 * self::LENGTH / 17525, 306);
    }

    /** @return array{0: float, 1: float} */
    private function wgs84(float $x, float $y): array
    {
        $p = DB::selectOne('SELECT ST_X(g) AS lon, ST_Y(g) AS lat FROM ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326) g', [$x, $y]);

        return [(float) $p->lon, (float) $p->lat];
    }

    /** @return array{0: float, 1: float} position de la gare en Lambert-93 */
    private function l93(string $name): array
    {
        $p = DB::selectOne('SELECT ST_X(ST_Transform(geom, 2154)) AS x, ST_Y(ST_Transform(geom, 2154)) AS y FROM stations WHERE id = ?', [$this->ids[$name]]);

        return [(float) $p->x, (float) $p->y];
    }

    /** @param  array<string, mixed>  $props */
    private function source(string $source, string $ref, string $name, int $stationId, float $x, float $y, array $props): void
    {
        [$lon, $lat] = $this->wgs84($x, $y);
        DB::table('station_sources')->insert([
            'source' => $source, 'source_ref' => $ref, 'station_id' => $stationId, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'unknown', 'properties' => json_encode($props), 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lon, $lat)),
        ]);
    }

    private function station(string $name, float $x, float $y, string $positionSource, ?int $precision): int
    {
        [$lon, $lat] = $this->wgs84($x, $y);
        $id = DB::table('stations')->insertGetId([
            'slug' => StationNames::normalize($name), 'name' => $name, 'type' => 'gare', 'status' => 'declassified',
            'position_source' => $positionSource, 'position_spread_m' => 0, 'sources_count' => 2,
            'provenance' => json_encode(['geom' => $positionSource] + ($precision ? ['geom_method' => 'pk', 'geom_precision_m' => $precision] : [])),
            'created_at' => now(), 'updated_at' => now(), 'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lon, $lat)),
        ]);

        return $this->ids[$name] = $id;
    }

    /** @param  array{code_ligne: string, pk_m: int}  $sncfLine */
    private function reliable(string $name, float $m, float $offset, int $pk, array $sncfLine): void
    {
        $id = $this->station($name, self::X0 + $m, self::Y0 + $offset, 'sncf', null);
        $this->source('sncf', 'uic-'.$id, $name, $id, self::X0 + $m, self::Y0 + $offset, ['lines' => [$sncfLine + ['voyageurs' => false, 'fret' => false]]]);
        $this->source('wikipedia_schema', 'L:'.$id, $name, $id, self::X0 + $m, self::Y0 + $offset,
            ['line_id' => $this->line->id, 'group' => self::GROUP, 'pk_m' => null, 'pk_source_m' => $pk, 'geocoding' => 'station', 'precision_m' => null,
                'placement' => "gare « {$name} » de la ligne"]);
    }

    private function listed(string $name, int $pk, float $m, int $precision, string $calibration = 'calage du schéma sur 2 gares et 0 communes'): void
    {
        $id = $this->station($name, self::X0 + $m, self::Y0, 'wikipedia_schema', $precision);
        $this->source('wikipedia_schema', 'L:'.$id, $name, $id, self::X0 + $m, self::Y0,
            ['line_id' => $this->line->id, 'group' => self::GROUP, 'pk_m' => null, 'pk_source_m' => $pk, 'geocoding' => 'pk', 'precision_m' => $precision,
                'placement' => sprintf('PK %s calé (%s), 3.1 km du point de contrôle le plus proche', number_format($pk / 1000, 3, ',', ''), $calibration),
                'calibration' => $calibration]);
    }

    public function test_montsecret_halts_move_to_pk_equal_distance_and_the_wrong_anchor_is_left_out(): void
    {
        // Lieux-dits BD TOPO : à 75 m et 86 m (le long de la voie) du PK, à une centaine de mètres de la voie.
        $this->places = ['le Paillot' => [self::X0 + 14325, self::Y0 + 126], 'Tinchebray' => [self::X0 + 7486, self::Y0 + 124]];
        $before = $this->l93('Les Maures');

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id]])
            ->expectsOutputToContain('replacée : 2')
            ->expectsOutputToContain('2 gare(s) déplacée(s)')
            ->assertSuccessful();

        [$x, $y] = $this->l93('Le Paillot');
        $this->assertEqualsWithDelta(self::X0 + 14400, $x, 2.0);
        $this->assertEqualsWithDelta(self::Y0, $y, 2.0);
        $this->assertEqualsWithDelta(self::X0 + 7400, $this->l93('Tinchebray')[0], 2.0);
        $this->assertSame($before, $this->l93('Les Maures'));

        $src = json_decode((string) DB::table('station_sources')->where('station_id', $this->ids['Le Paillot'])->value('properties'), true);
        $this->assertSame(['pk', 100 + 432, 306], [$src['geocoding'], $src['precision_m'], $src['recalage_pk']['precision_m']]);
        $this->assertStringContainsString('Montsecret - Vassy', $src['placement']);
        $this->assertStringContainsString('écartés : « Les Maures » (PK d\'une autre ligne)', $src['calibration']);
        $this->assertGreaterThan(1500, $src['recalage_pk']['moved_m']);

        $st = DB::table('stations')->where('id', $this->ids['Le Paillot'])->first();
        $prov = json_decode((string) $st->provenance, true);
        $this->assertSame(['wikipedia_schema', 'pk_consensus', 532], [$st->position_source, $prov['geom_recalage']['mode'], $prov['geom_precision_m']]);
        $this->assertFileExists(storage_path('app/reports/gares-recalage-pk.csv'));
        $this->assertCount(3, file(storage_path('app/reports/gares-recalage-pk.csv')));

        // Idempotent : un second passage ne déplace plus rien.
        $snapshot = $this->snapshot(['stations', 'station_sources']);
        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id]])->expectsOutputToContain('0 gare(s) déplacée(s)')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['stations', 'station_sources']));
    }

    public function test_a_single_reliable_station_without_a_concordant_place_is_not_enough(): void
    {
        // Les Maures écartée (PK d'une autre ligne), Montsecret - Vassy seule : ni le sens ni
        // l'origine des PK ne sont confirmés, rien ne bouge.
        $snapshot = $this->snapshot(['stations', 'station_sources']);

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id], '--no-places' => true])
            ->expectsOutputToContain('calage ambigu ou insuffisant : 2')
            ->assertSuccessful();

        $this->assertSame($snapshot, $this->snapshot(['stations', 'station_sources']));
        Http::assertNothingSent();
    }

    public function test_a_halt_named_after_a_commune_never_leaves_it(): void
    {
        $this->places = ['le Paillot' => [self::X0 + 14325, self::Y0 + 126], 'Tinchebray' => [self::X0 + 7486, self::Y0 + 124]];
        // Commune de Tinchebray : 400 m autour de l'ancienne position (abscisse 8 209), la nouvelle (7 400)
        // est à 609 m du contour ; le Paillot n'est pas une commune.
        $old = $this->l93('Tinchebray');
        DB::insert(<<<'SQL'
            INSERT INTO communes (code_insee, type, nom, nom_norm, geom, created_at, updated_at)
            VALUES ('61486', 'commune-actuelle', 'Tinchebray', 'tinchebray', ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 2154), 4326), NOW(), NOW())
        SQL, [$old[0], $old[1] + 300]);
        DB::insert(<<<'SQL'
            INSERT INTO commune_contours (code_insee, nom, geom, created_at, updated_at)
            VALUES ('61486', 'Tinchebray', ST_Multi(ST_Transform(ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 2154), 200), 4326)), NOW(), NOW())
        SQL, [$old[0], $old[1]]);

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id]])
            ->expectsOutputToContain('hors de sa commune : 1')
            ->expectsOutputToContain('replacée : 1')
            ->assertSuccessful();

        $this->assertSame($old, $this->l93('Tinchebray'));
        $this->assertEqualsWithDelta(self::X0 + 14400, $this->l93('Le Paillot')[0], 2.0);
    }

    public function test_two_reliable_stations_that_disagree_without_other_evidence_change_nothing(): void
    {
        // Les Maures sans PK SNCF égal à celui du schéma : rien ne dit lequel des deux est faux.
        DB::update("UPDATE station_sources SET properties = jsonb_set(properties, '{lines,0,pk_m}', '99999') WHERE source = 'sncf'");
        $snapshot = $this->snapshot(['stations', 'station_sources']);

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id], '--no-places' => true])
            ->expectsOutputToContain('calage ambigu ou insuffisant : 2')
            ->assertSuccessful();

        $this->assertSame($snapshot, $this->snapshot(['stations', 'station_sources']));
    }

    public function test_a_halt_whose_own_place_contradicts_the_calibration_stays(): void
    {
        $this->places = ['le Paillot' => [self::X0 + 17500, self::Y0 + 80], 'Tinchebray' => [self::X0 + 7486, self::Y0 + 124]];
        $old = $this->l93('Le Paillot');

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id]])
            ->expectsOutputToContain('le lieu-dit contredit le calage : 1')
            ->assertSuccessful();

        $this->assertSame($old, $this->l93('Le Paillot'));
        $this->assertEqualsWithDelta(self::X0 + 7400, $this->l93('Tinchebray')[0], 2.0);
    }

    public function test_a_halt_never_moves_away_from_its_own_place_when_not_bracketed(): void
    {
        // Lieu-dit du Paillot à l'abscisse 15 300 : concordant (écart 900 m pour 932 tolérés), mais plus
        // proche de l'ancienne position (15 973) que du PK seul (14 400), qui n'est encadré par aucune gare.
        $this->places = ['le Paillot' => [self::X0 + 15300, self::Y0 + 120], 'Tinchebray' => [self::X0 + 7486, self::Y0 + 124]];
        $old = $this->l93('Le Paillot');

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id]])
            ->expectsOutputToContain("s'éloignerait de son lieu-dit : 1")
            ->assertSuccessful();

        $this->assertSame($old, $this->l93('Le Paillot'));
        $this->assertEqualsWithDelta(self::X0 + 7400, $this->l93('Tinchebray')[0], 2.0);
    }

    public function test_stations_placed_by_the_official_calibration_are_never_moved(): void
    {
        DB::table('station_sources')->where('station_id', $this->ids['Le Paillot'])
            ->update(['properties' => DB::raw("jsonb_set(properties, '{placement}', '\"PK 14,400 calé (calage officiel de la ligne)\"')")]);
        $this->places = ['Tinchebray' => [self::X0 + 7486, self::Y0 + 124]];
        $old = $this->l93('Le Paillot');

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id]])->assertSuccessful();

        $this->assertSame($old, $this->l93('Le Paillot'));
        $this->assertEqualsWithDelta(self::X0 + 7400, $this->l93('Tinchebray')[0], 2.0);
    }

    public function test_dry_run_writes_the_proposals_but_not_the_database(): void
    {
        $this->places = ['le Paillot' => [self::X0 + 14325, self::Y0 + 126], 'Tinchebray' => [self::X0 + 7486, self::Y0 + 124]];
        $snapshot = $this->snapshot(['stations', 'station_sources']);
        $csv = storage_path('app/reports/simulation.csv');

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id], '--dry-run' => true, '--report' => $csv])
            ->expectsOutputToContain('replacée : 2')
            ->assertSuccessful();

        $this->assertSame($snapshot, $this->snapshot(['stations', 'station_sources']));
        $this->assertCount(3, file($csv));
    }

    public function test_cross_validation_reports_both_methods(): void
    {
        // Troisième gare fiable au PK 10 : replacée sans elle par Montsecret seul (Les Maures écartée).
        $this->reliable('Sept-Fours', 10000, 0, 10000, ['code_ligne' => '405000', 'pk_m' => 99999]);

        $this->artisan('vff:place-listed-stations', ['--line' => [$this->line->id], '--validate' => true, '--no-places' => true])
            ->expectsOutputToContain('Validation croisée : 2 gares d\'appui')
            ->assertSuccessful();
    }
}
