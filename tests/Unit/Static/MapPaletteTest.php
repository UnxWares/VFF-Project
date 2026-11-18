<?php

namespace Tests\Unit\Static;

use PHPUnit\Framework\TestCase;

/**
 * Cohérence des couleurs de la carte : une seule source de vérité,
 * resources/js/lib/railStyle.js, reprise telle quelle par la légende.
 *
 *   LGV = violet, mixte = orange VFF, fret = bleu, patrimoine = vert.
 */
class MapPaletteTest extends TestCase
{
    private const RAIL_STYLE = 'resources/js/lib/railStyle.js';

    private const LEGEND = 'resources/js/Components/Map/MapLegend.svelte';

    /** Couleurs de référence (règles métier, cf. docs et légende publiée). */
    private const REFERENCE = [
        'lgv' => '#7c3aed',
        'mixed' => '#ff6500',
        'freight' => '#0284c7',
        'heritage' => '#16a34a',
    ];

    /** Teintes neutres tolérées en dur dans la légende (fonds, contours). */
    private const NEUTRAL = ['#fff', '#ffffff', '#000', '#000000'];

    public function test_rail_style_defines_the_reference_palette(): void
    {
        $js = SourceText::javascript(SourceText::read(self::RAIL_STYLE));

        $this->assertSame(self::REFERENCE['lgv'], $this->constant($js, 'LGV_COLOR'), 'LGV : violet #7c3aed');
        $this->assertSame(self::REFERENCE['heritage'], $this->constant($js, 'HERITAGE_COLOR'), 'Patrimoine : vert #16a34a');

        $traffic = $this->arrayLiteral($js, 'TRAFFIC_STYLES');
        $this->assertSame(self::REFERENCE['mixed'], $this->styleColor($traffic, 'mixed'), 'Réseau mixte : orange VFF #ff6500');
        $this->assertSame(self::REFERENCE['freight'], $this->styleColor($traffic, 'freight'), 'Fret seul : bleu #0284c7');
        $this->assertSame('LGV_COLOR', $this->styleColor($traffic, 'lgv'), 'La catégorie lgv de TRAFFIC_STYLES doit reprendre LGV_COLOR');

        $heritage = $this->arrayLiteral($js, 'HERITAGE_STYLES');
        $this->assertSame('HERITAGE_COLOR', $this->styleColor($heritage, 'heritage'), 'Le train touristique doit reprendre HERITAGE_COLOR');
        $this->assertSame('HERITAGE_COLOR', $this->styleColor($heritage, 'velorail'), 'Le vélorail doit reprendre HERITAGE_COLOR');
    }

    public function test_legend_uses_rail_style_constants(): void
    {
        $source = SourceText::read(self::LEGEND);
        $displayed = SourceText::svelte($source);

        $this->assertMatchesRegularExpression(
            '/import\s*\{[^}]*\bTRAFFIC_STYLES\b[^}]*\}\s*from\s*[\'"]\$lib\/railStyle\.js[\'"]/s', $displayed,
            'La légende doit importer TRAFFIC_STYLES de railStyle.js',
        );
        $this->assertMatchesRegularExpression(
            '/import\s*\{[^}]*\bHERITAGE_STYLES\b[^}]*\}\s*from\s*[\'"]\$lib\/railStyle\.js[\'"]/s', $displayed,
            'La légende doit importer HERITAGE_STYLES de railStyle.js',
        );
        $this->assertMatchesRegularExpression('/\bTRAFFIC_STYLES\s*\.\s*map\s*\(/', $displayed, 'Les lignes « trafic » de la légende doivent venir de TRAFFIC_STYLES');
        $this->assertMatchesRegularExpression('/\bHERITAGE_STYLES\s*\.\s*map\s*\(/', $displayed, 'Les lignes « patrimoine » de la légende doivent venir de HERITAGE_STYLES');

        // Aucune couleur de référence recopiée en dur (même dans le CSS de la légende).
        foreach (self::REFERENCE as $category => $color) {
            $this->assertStringNotContainsStringIgnoringCase($color, $source, "Couleur « {$category} » codée en dur dans la légende : utiliser la constante de railStyle.js");
        }

        // Toute autre couleur en dur (script, balisage) doit appartenir à la palette.
        $palette = $this->hexColors(SourceText::read(self::RAIL_STYLE));
        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $displayed, $matches);
        $foreign = array_values(array_diff(
            array_unique(array_map('strtolower', $matches[0])),
            $palette, self::NEUTRAL,
        ));
        $this->assertSame([], $foreign, 'Couleur de la légende absente de railStyle.js : '.implode(', ', $foreign));
    }

    private function constant(string $js, string $name): ?string
    {
        return preg_match('/export\s+const\s+'.$name.'\s*=\s*[\'"](#[0-9a-fA-F]{6})[\'"]/', $js, $m) ? strtolower($m[1]) : null;
    }

    private function arrayLiteral(string $js, string $name): string
    {
        $this->assertMatchesRegularExpression('/export\s+const\s+'.$name.'\s*=\s*\[(.*?)\];/s', $js, "{$name} introuvable dans railStyle.js");
        preg_match('/export\s+const\s+'.$name.'\s*=\s*\[(.*?)\];/s', $js, $m);

        return $m[1];
    }

    /** Couleur d'une entrée `{ key: 'x', color: … }` : hexadécimal (minuscules) ou nom de constante. */
    private function styleColor(string $array, string $key): ?string
    {
        if (! preg_match('/\{\s*key:\s*[\'"]'.preg_quote($key, '/').'[\'"]\s*,\s*color:\s*([\'"]#[0-9a-fA-F]{6}[\'"]|[A-Z_]+)/', $array, $m)) {
            return null;
        }

        return ctype_upper($m[1][0]) ? $m[1] : strtolower(trim($m[1], '\'"'));
    }

    /** @return list<string> */
    private function hexColors(string $source): array
    {
        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $source, $m);

        return array_values(array_unique(array_map('strtolower', $m[0])));
    }
}
