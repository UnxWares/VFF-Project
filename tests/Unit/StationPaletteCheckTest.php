<?php

namespace Tests\Unit;

use App\Services\ConsistencyChecker;
use PHPUnit\Framework\TestCase;

/**
 * Contrôle de la palette des gares (ConsistencyChecker::stationPalette) sur de petits fichiers de test :
 * lecture des couleurs (littérales, constantes, tables de trafic), et chaque règle qui empêche de
 * confondre une gare fermée ou incertaine avec une gare ouverte.
 */
class StationPaletteCheckTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/vff-palette-check-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (['railStyle.js', 'RailMap.svelte'] as $file) {
            if (is_file("{$this->dir}/{$file}")) {
                unlink("{$this->dir}/{$file}");
            }
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    private const MAP = "'circle-stroke-color': stationColorExpr(),";

    /** @return array{colors: array<string, string>, fallback: ?string, errors: list<string>} */
    private function check(string $railStyle, string $railMap = self::MAP): array
    {
        file_put_contents("{$this->dir}/railStyle.js", $railStyle);
        file_put_contents("{$this->dir}/RailMap.svelte", $railMap);

        return ConsistencyChecker::stationPalette("{$this->dir}/railStyle.js", "{$this->dir}/RailMap.svelte");
    }

    private function styles(string $active = "'#ff6500'", string $tourist = "'#16a34a'", string $freight = "'#0284c7'", string $uncertain = "'#64748b'", string $closed = "'#78716c'"): string
    {
        return <<<JS
            export const STATION_CLOSED_COLOR = '#78716c';
            export const STATION_STYLES = [
                { key: 'active', color: {$active} },
                { key: 'tourist', color: {$tourist} },
                { key: 'no_commercial', color: {$freight} },
                { key: 'uncertain', color: {$uncertain} },
                { key: 'declassified', color: {$closed} },
            ];
            JS;
    }

    public function test_a_consistent_palette_has_no_error(): void
    {
        $palette = $this->check($this->styles());

        $this->assertSame([], $palette['errors']);
        $this->assertSame('#78716c', $palette['fallback']);
        $this->assertSame('#ff6500', $palette['colors']['active']);
        $this->assertSame('#78716c', $palette['colors']['declassified']);
    }

    public function test_colours_are_read_from_constants_and_the_traffic_table(): void
    {
        $js = <<<'JS'
            const MIXED = '#ff6500';
            export const HERITAGE_COLOR = '#16a34a';
            export const STATION_CLOSED_COLOR = '#78716c';
            const TRAFFIC_STYLES = [
                { key: 'mixed', color: MIXED },
                { key: 'freight', color: '#0284C7' },
            ];
            export const STATION_STYLES = [
                { key: 'active', color: TRAFFIC_COLOR.mixed },
                { key: 'tourist', color: HERITAGE_COLOR },
                { key: 'no_commercial', color: TRAFFIC_COLOR.freight },
                { key: 'uncertain', color: '#64748b' },
                { key: 'declassified', color: STATION_CLOSED_COLOR },
            ];
            JS;

        $palette = $this->check($js);

        $this->assertSame([], $palette['errors']);
        $this->assertSame(['active' => '#ff6500', 'tourist' => '#16a34a', 'no_commercial' => '#0284c7', 'uncertain' => '#64748b', 'declassified' => '#78716c'], $palette['colors']);
    }

    public function test_missing_files_report_the_missing_definitions(): void
    {
        $errors = ConsistencyChecker::stationPalette("{$this->dir}/absent.js", "{$this->dir}/absent.svelte")['errors'];

        $this->assertStringContainsString('STATION_STYLES', implode("\n", $errors));
        $this->assertStringContainsString('STATION_CLOSED_COLOR', implode("\n", $errors));
        $this->assertStringContainsString('stationColorExpr', implode("\n", $errors));
    }

    public function test_an_unreadable_colour_is_reported(): void
    {
        $errors = $this->check($this->styles(tourist: 'unePaletteInconnue.vert'))['errors'];

        $this->assertCount(2, $errors, 'illisible, puis statut ouvert sans couleur');
        $this->assertStringContainsString('« tourist » illisible', $errors[0]);
    }

    public function test_a_closed_colour_equal_to_an_open_one_is_reported(): void
    {
        $errors = $this->check(str_replace("export const STATION_CLOSED_COLOR = '#78716c';", "export const STATION_CLOSED_COLOR = '#16a34a';", $this->styles()))['errors'];

        $this->assertStringContainsString('couleur des gares fermées (#16a34a) est aussi celle des gares « tourist »', implode("\n", $errors));
    }

    public function test_an_uncertain_colour_equal_to_an_open_one_is_reported(): void
    {
        $errors = $this->check($this->styles(uncertain: "'#ff6500'"))['errors'];

        $this->assertStringContainsString('gares incertaines (#ff6500) est aussi celle des gares « active »', implode("\n", $errors));
    }

    public function test_a_missing_uncertain_status_is_reported(): void
    {
        $js = <<<'JS'
            export const STATION_CLOSED_COLOR = '#78716c';
            export const STATION_STYLES = [
                { key: 'active', color: '#ff6500' },
                { key: 'tourist', color: '#16a34a' },
                { key: 'no_commercial', color: '#0284c7' },
            ];
            JS;

        $this->assertStringContainsString('« uncertain » (gare incertaine) sans couleur', implode("\n", $this->check($js)['errors']));
    }

    public function test_the_declassified_style_must_equal_the_closed_colour(): void
    {
        $errors = $this->check($this->styles(closed: "'#111111'"))['errors'];

        $this->assertStringContainsString('« declassified » diffère de STATION_CLOSED_COLOR', implode("\n", $errors));
    }

    public function test_the_map_layer_must_use_the_shared_expression(): void
    {
        $errors = $this->check($this->styles(), "'circle-stroke-color': '#ff6500',")['errors'];

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('stationColorExpr', $errors[0]);
    }
}
