<?php

namespace Tests\Unit;

use App\Services\ConsistencyChecker;
use App\Services\EraTimeline;
use App\Services\StationTimeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règles métier OBLIGATOIRES de VFF (logique pure, sans base) :
 *   R1 « Si y'a du passager, y'a du fret » : voyageurs seuls = LGV uniquement.
 *   R2 Une gare ne peut pas être plus active que ses lignes à une ère donnée.
 * + palette des gares de la carte (railStyle.js) : une gare fermée n'a jamais
 *   la couleur d'une gare ouverte.
 */
class BusinessRulesTest extends TestCase
{
    // ─── R1 ──────────────────────────────────────────────────────────────

    public function test_rule_1_classic_line_open_to_passengers_is_mixed(): void
    {
        foreach (['opening', 'opening_passenger', 'reopening_passenger'] as $type) {
            $t = new EraTimeline([['type' => 'opening', 'date' => '1880-01-01'], ['type' => 'closure', 'date' => '1950-01-01'],
                ['type' => $type, 'date' => '1990-01-01']]);
            $this->assertSame('mixed', $t->stateAt(2000)['traffic'], $type);
        }
    }

    public function test_rule_1_passenger_only_is_reserved_to_high_speed_lines(): void
    {
        $events = [['type' => 'opening_passenger', 'date' => '1981-09-27']];

        $this->assertSame('mixed', (new EraTimeline($events))->stateAt(1990)['traffic']);
        $this->assertSame('passenger', (new EraTimeline($events, 'PF', passengerImpliesFreight: false))->stateAt(1990)['traffic']);
    }

    public function test_rule_1_freight_only_after_passenger_closure_stays_freight(): void
    {
        $t = new EraTimeline([['type' => 'opening', 'date' => '1880-01-01'], ['type' => 'closure_passenger', 'date' => '1939-01-01']]);

        $this->assertSame(['status' => 'in_service', 'traffic' => 'freight'], $t->stateAt(1950));
    }

    // ─── R2 ──────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: int, 2: ?string}> */
    public static function rule2Provider(): array
    {
        $closed = StationTimeline::LINES_CLOSED;
        $freight = StationTimeline::LINES_FREIGHT;
        $passenger = StationTimeline::LINES_PASSENGER;

        return [
            // Le bug signalé : gare « fret » (drapeau SNCF) sur une ligne fermée.
            'gare fret, lignes fermées → fermée' => ['no_commercial', $closed, 'declassified'],
            'gare voyageurs, lignes fermées → fermée' => ['active', $closed, 'declassified'],
            'gare touristique, lignes fermées → fermée' => ['tourist', $closed, 'declassified'],
            'gare voyageurs, lignes fret seul → fret seul' => ['active', $freight, 'no_commercial'],
            'gare touristique, lignes fret seul → fret seul' => ['tourist', $freight, 'no_commercial'],
            'gare fret, lignes fret seul : conforme' => ['no_commercial', $freight, null],
            'gare fret, lignes voyageurs : conforme (moins active que ses lignes)' => ['no_commercial', $passenger, null],
            'gare voyageurs, lignes voyageurs : conforme' => ['active', $passenger, null],
            'gare fermée : jamais rouverte par le contrôle' => ['declassified', $passenger, null],
        ];
    }

    #[DataProvider('rule2Provider')]
    public function test_rule_2_station_cannot_be_more_active_than_its_lines(string $status, int $lines, ?string $expected): void
    {
        $this->assertSame($expected, ConsistencyChecker::rule2Status($status, $lines));
    }

    #[DataProvider('rule2Provider')]
    public function test_rule_2_check_agrees_with_station_engine(string $status, int $lines, ?string $expected): void
    {
        // Le contrôle et le moteur des gares (BuildStationEras → capByLines) donnent le même plafond.
        $this->assertSame($expected ?? $status, StationTimeline::capByLines($status, $lines));
    }

    // ─── Palette des gares ──────────────────────────────────────────────

    public function test_station_palette_never_gives_a_closed_station_an_open_colour(): void
    {
        $palette = ConsistencyChecker::stationPalette(self::path('resources/js/lib/railStyle.js'), self::path('resources/js/Components/Map/RailMap.svelte'));

        $this->assertSame([], $palette['errors']);
        $this->assertNotNull($palette['fallback']);
        foreach (ConsistencyChecker::OPEN_STATIONS as $open) {
            $this->assertArrayHasKey($open, $palette['colors']);
            $this->assertNotSame($palette['fallback'], $palette['colors'][$open], $open);
        }
        // Mêmes teintes que les lignes : voyageurs = orange VFF, fret seul = bleu fret.
        $this->assertSame('#ff6500', $palette['colors']['active']);
        $this->assertNotSame('#7c3aed', $palette['colors']['tourist'], 'le violet est réservé aux LGV');
    }

    public function test_station_palette_check_flags_an_orange_fallback(): void
    {
        $dir = sys_get_temp_dir().'/vff-palette-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("{$dir}/railStyle.js", <<<'JS'
            export const STATION_CLOSED_COLOR = '#ff6500';
            export const STATION_STYLES = [
                { key: 'active', color: '#ff6500' },
                { key: 'tourist', color: '#16a34a' },
                { key: 'no_commercial', color: '#0284c7' },
                { key: 'uncertain', color: '#94a3b8' },
            ];
            JS);
        file_put_contents("{$dir}/RailMap.svelte", "'circle-stroke-color': ['match', ['get', 'status'], 'active', '#ff6500', '#ff6500'],");

        $errors = ConsistencyChecker::stationPalette("{$dir}/railStyle.js", "{$dir}/RailMap.svelte")['errors'];
        exec('rm -rf '.escapeshellarg($dir));

        $this->assertCount(2, $errors);
        $this->assertStringContainsString('« active »', $errors[0]);
        $this->assertStringContainsString('stationColorExpr', $errors[1]);
    }

    private static function path(string $relative): string
    {
        return dirname(__DIR__, 2).'/'.$relative;
    }
}
