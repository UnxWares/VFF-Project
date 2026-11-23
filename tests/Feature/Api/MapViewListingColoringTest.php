<?php

namespace Tests\Feature\Api;

use App\Models\LineSegment;
use App\Support\SpeedClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Coloration des tracés de /maps (« Colorer les tracés selon ») : classes de chaque ligne dans la liste de ce
 * qui est affiché (`GET /api/v1/eras/{year}/in-view?color=`) et colorations possibles par ère (props de la page).
 */
class MapViewListingColoringTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const PARIS_WEST = '2.0,48.7,2.5,49.0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_without_a_colour_the_lines_have_no_classes(): void
    {
        $line = $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST)->assertOk()->json('lines.items.0');

        $this->assertArrayNotHasKey('classes', $line);
    }

    public function test_speed_classes_with_their_km(): void
    {
        $line = $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST.'&color=speed')->assertOk()->json('lines.items.0');

        // 120 km/h : classe « de 100 à 160 km/h ».
        $this->assertSame(['v160'], array_column($line['classes'], 'key'));
        $this->assertEqualsWithDelta($line['km'], $line['classes'][0]['km'], 0.2);
    }

    public function test_a_portion_without_speed_is_unknown_and_classes_are_sorted_by_km(): void
    {
        LineSegment::query()->where('line_id', $this->line->id)->where('era_id', $this->currentEra->id)->update(['max_speed_kmh' => null]);

        $classes = $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST.'&color=speed')->json('lines.items.0.classes');

        $this->assertSame(['unknown'], array_column($classes, 'key'));
    }

    public function test_electrification_classes_follow_the_map_colours(): void
    {
        $classes = $this->getJson('/api/v1/eras/1960/in-view?bbox='.self::PARIS_WEST.'&color=electrification')->assertOk()->json('lines.items.0.classes');

        $this->assertSame(['dc1500'], array_column($classes, 'key'));
    }

    public function test_gauge_classes(): void
    {
        LineSegment::query()->where('line_id', $this->line->id)->update(['gauge_class' => 'metre']);

        $classes = $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST.'&color=gauge')->json('lines.items.0.classes');

        $this->assertSame(['metre'], array_column($classes, 'key'));
    }

    public function test_signalling_classes_of_a_past_era_come_from_the_dated_history(): void
    {
        $this->addSignallingHistory();

        $classes = $this->getJson('/api/v1/eras/1960/in-view?bbox='.self::PARIS_WEST.'&color=signalling')->assertOk()->json('lines.items.0.classes');
        $this->assertSame(['automatic_block'], array_column($classes, 'key'));

        // Aujourd'hui, aucune tranche de SNCF Réseau placée : une liste vide, jamais une famille devinée.
        $today = $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST.'&color=signalling')->assertOk()->json('lines.items.0.classes');
        $this->assertSame([], $today);
    }

    public function test_an_unknown_colour_is_refused(): void
    {
        $this->getJson('/api/v1/eras/2030/in-view?bbox='.self::PARIS_WEST.'&color=rainbow')->assertUnprocessable()->assertJsonValidationErrors('color');
    }

    public function test_the_map_page_tells_which_colourings_exist_at_each_era(): void
    {
        LineSegment::query()->where('era_id', $this->pastEra->id)->update(['max_speed_kmh' => null]);
        $this->addSignallingHistory();

        $this->get('/maps')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Maps')
            ->where('eras.0.year', 1960)
            ->where('eras.0.colorings', ['electrification', 'signalling'])
            ->where('eras.1.year', 2030)
            ->where('eras.1.colorings', ['electrification', 'speed'])
            ->where('dataVersion', fn (string $version): bool => str_ends_with($version, '-m2'))
        );
    }

    public function test_speed_class_bounds(): void
    {
        $this->assertNull(SpeedClass::of(null));
        $this->assertNull(SpeedClass::of(0));
        $this->assertSame(1, SpeedClass::of(100));
        $this->assertSame(2, SpeedClass::of(101));
        $this->assertSame(2, SpeedClass::of(160));
        $this->assertSame(3, SpeedClass::of(220));
        $this->assertSame(4, SpeedClass::of(320));

        foreach ([null, 40, 100, 101, 160, 161, 220, 221, 320] as $kmh) {
            $sql = SpeedClass::sql($kmh === null ? 'NULL::int' : (string) $kmh);
            $this->assertSame(SpeedClass::of($kmh), DB::scalar("SELECT {$sql}"), "vitesse {$kmh}");
        }
    }

    /** Mise en service datée du BAL en 1955, dessinée à l'ère 1960 sur tout le tracé de la ligne. */
    private function addSignallingHistory(): void
    {
        $eventId = DB::table('line_signalling_events')->insertGetId([
            'line_id' => $this->line->id, 'system' => 'BAL', 'role' => 'block', 'event_date' => '1955-06-01',
            'date_precision' => 'month', 'scope' => 'whole_line', 'confidence' => 'high', 'origin' => 'text',
            'source' => 'wikipedia', 'source_title' => 'Ligne de test', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1',
            'source_excerpt' => 'Le BAL est mis en service en juin 1955.', 'fingerprint' => sha1('bal-1955'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::statement(<<<'SQL'
            INSERT INTO line_signalling_history_map (era_id, line_id, event_id, family, location, sort, geom, created_at, updated_at)
            SELECT ls.era_id, ls.line_id, ?, 'automatic_block', 'line', 19550601, ST_Multi(ls.geom), now(), now()
            FROM line_segments ls WHERE ls.era_id = ? AND ls.line_id = ?
            SQL, [$eventId, $this->pastEra->id, $this->line->id]);
    }
}
