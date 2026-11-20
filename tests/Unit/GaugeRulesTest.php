<?php

namespace Tests\Unit;

use App\Services\Traction\Gauge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Écartement des voies (App\Services\Traction\Gauge) : classes et bornes,
 * lecture des sources (OSM, BD TOPO, SNCF, noms, phrases), conversions
 * datées et écartement à une date donnée.
 */
class GaugeRulesTest extends TestCase
{
    /** @return array<string, array{0: ?int, 1: ?string}> */
    public static function classes(): array
    {
        return [
            'nul' => [null, null], 'zéro' => [0, null], 'négatif' => [-5, null],
            'normal bas' => [1400, 'standard'], 'normal' => [1435, 'standard'], 'normal haut' => [1450, 'standard'],
            'large' => [1451, 'broad'], 'ibérique' => [1668, 'broad'],
            'métrique bas' => [990, 'metre'], 'métrique haut' => [1010, 'metre'],
            'étroit 989' => [989, 'narrow'], 'entre deux' => [1011, 'narrow'], 'étroit 1399' => [1399, 'narrow'], 'decauville' => [600, 'narrow'],
        ];
    }

    #[DataProvider('classes')]
    public function test_class_of(?int $mm, ?string $class): void
    {
        $this->assertSame($class, Gauge::classOf($mm));
    }

    public function test_osm_tag(): void
    {
        $this->assertNull(Gauge::fromTag(null));
        $this->assertNull(Gauge::fromTag('  '));
        $this->assertSame(1435, Gauge::fromTag('1435'));
        $this->assertSame(1435, Gauge::fromTag('1000;1435'), 'voie mixte : le plus grand');
        $this->assertSame(1000, Gauge::fromTag(' 1000.4 ; x'));
        $this->assertNull(Gauge::fromTag('200'), 'hors plage');
        $this->assertNull(Gauge::fromTag('2001'));
        $this->assertSame(300, Gauge::fromTag('300'));
        $this->assertSame(2000, Gauge::fromTag('2000'));
        $this->assertNull(Gauge::fromTag('standard'));
    }

    public function test_bdtopo_and_sncf_labels(): void
    {
        $this->assertSame([1435, 'standard'], Gauge::fromBdtopo('Normale'));
        $this->assertSame([null, 'narrow'], Gauge::fromBdtopo('Etroite'));
        $this->assertSame([null, 'narrow'], Gauge::fromBdtopo('Étroite'));
        $this->assertSame([null, 'broad'], Gauge::fromBdtopo('Large'));
        $this->assertSame([null, null], Gauge::fromBdtopo(null));
        $this->assertSame([null, null], Gauge::fromBdtopo('Sans objet'));

        $this->assertSame([null, null], Gauge::fromSncfCatlig(null));
        $this->assertSame([null, 'narrow'], Gauge::fromSncfCatlig('Ligne à voie étroite'));
        $this->assertSame([null, 'narrow'], Gauge::fromSncfCatlig('VOIE ETROITE'));
        $this->assertSame([1435, 'standard'], Gauge::fromSncfCatlig('Ligne à écartement normal'));
        $this->assertSame([1435, 'standard'], Gauge::fromSncfCatlig('Ligne à grande vitesse'));
        $this->assertSame([null, null], Gauge::fromSncfCatlig('Ligne de fret'));
    }

    /** @return array<string, array{0: ?string, 1: array{0: ?int, 1: ?string}}> */
    public static function names(): array
    {
        return [
            'vide' => ['  ', [null, null]], 'nul' => [null, [null, null]], 'rien' => ['Ligne de Paris à Lyon', [null, null]],
            'V60' => ['Pithiviers Toury (V60)', [600, 'narrow']], 'V 60' => ['Tracé V 60', [600, 'narrow']],
            '0m60' => ['voie de 0m60', [600, 'narrow']], '0,60' => ['écartement 0,60', [600, 'narrow']], '60 cm' => ['voie de 60 cm', [600, 'narrow']],
            'Decauville' => ['réseau Decauville', [600, 'narrow']],
            '0 m 70' => ['voie de 0 m 70', [700, 'narrow']], '70cm' => ['voie de 70cm', [700, 'narrow']],
            'V75' => ['Tracé V75', [750, 'narrow']], '0.75' => ['voie de 0.75', [750, 'narrow']], '75 cm' => ['75 cm', [750, 'narrow']],
            'VM' => ['Tramway VM', [1000, 'metre']], 'vm minuscule' => ['tramway vm', [null, null]],
            'voie métrique' => ['Ligne à Voie Métrique', [1000, 'metre']], 'écartement 1000 mm' => ['écartement de 1 000 mm', [1000, 'metre']],
            '1 mètre' => ['1 mètre d\'écartement', [1000, 'metre']],
            'VN' => ['Raccordement VN', [1435, 'standard']], 'voie normale' => ['en voie normale', [1435, 'standard']],
            'écartement standard' => ['écartement standard', [1435, 'standard']], '1435 mm' => ['1435 mm', [1435, 'standard']],
            'voie étroite' => ['ligne à voie étroite', [null, 'narrow']],
            'le premier cité' => ['voie normale puis voie métrique', [1435, 'standard']],
            'le premier cité (2)' => ['voie métrique puis voie normale', [1000, 'metre']],
        ];
    }

    #[DataProvider('names')]
    public function test_from_name(?string $name, array $expected): void
    {
        $this->assertSame($expected, Gauge::fromName($name));
    }

    public function test_from_line_sentence(): void
    {
        $this->assertSame([null, null], Gauge::fromLineSentence(null));
        $this->assertSame([1000, 'metre'], Gauge::fromLineSentence('La ligne est à voie métrique.'));
        $this->assertSame([1435, 'standard'], Gauge::fromLineSentence('Elle est construite en voie normale.'));
        $this->assertSame([600, 'narrow'], Gauge::fromLineSentence("D'un écartement de 0,60 m, elle dessert les carrières."));
        $this->assertSame([null, null], Gauge::fromLineSentence('Le tramway, à voie métrique, dessert la ville.'), 'sujet non reconnu');
        $this->assertSame([null, null], Gauge::fromLineSentence('La ligne croise une voie métrique.'), 'autre ligne citée');
        $this->assertSame([null, null], Gauge::fromLineSentence('La ligne rejoint la voie métrique.'));
        $this->assertSame([null, null], Gauge::fromLineSentence('La ligne de Saint-Jean à Saint-Saviol est à voie métrique.', 'Ligne de Niort à Ruffec'));
        $this->assertSame([1000, 'metre'], Gauge::fromLineSentence('La ligne de Niort à Ruffec est à voie métrique.', 'Ligne de Niort à Ruffec'));
        $this->assertSame([1000, 'metre'], Gauge::fromLineSentence('La ligne de Niort à Ruffec est à voie métrique.'));
        $this->assertSame([null, null], Gauge::fromLineSentence('Voie métrique.'));
    }

    public function test_conversion(): void
    {
        $this->assertNull(Gauge::conversion(null));
        $this->assertSame(['year' => 1930, 'to_mm' => 1435, 'from_mm' => null], Gauge::conversion('La ligne est mise à voie normale en 1930.'));
        $this->assertSame(['year' => 1957, 'to_mm' => 1435, 'from_mm' => null], Gauge::conversion('Section transformée en voie normale, travaux achevés en 1957.'));
        $this->assertSame(['year' => 1912, 'to_mm' => 1435, 'from_mm' => null], Gauge::conversion('Ligne convertie à l\'écartement normal', 1912));
        $this->assertNull(Gauge::conversion('Ligne convertie à l\'écartement normal'), 'sans année');
        $this->assertSame(['year' => 1950, 'to_mm' => 1435, 'from_mm' => 1000], Gauge::conversion('Elle reste en voie métrique jusqu\'en 1950.'));
        $this->assertSame(['year' => 1949, 'to_mm' => 1435, 'from_mm' => 1000], Gauge::conversion('voie métrique jusqu’à 1949'));
        $this->assertNull(Gauge::conversion('La ligne est à voie normale depuis 1900.'));
    }

    // ─── écartement à une date ───────────────────────────────────────────

    private static function g(?int $mm, string $source, array $extra = []): array
    {
        return ['mm' => $mm, 'class' => Gauge::classOf($mm), 'source' => $source] + $extra;
    }

    public function test_at_curated_line_gauge_wins_without_conversion(): void
    {
        $this->assertSame(['mm' => 1000, 'class' => 'metre', 'source' => 'curated'],
            Gauge::at(1930, self::g(1435, 'osm'), [], [self::g(1000, 'event'), self::g(1000, 'curated')], []));
    }

    public function test_at_dated_conversions(): void
    {
        $conv = [['year' => 1950, 'to_mm' => 1435, 'from_mm' => null, 'source' => 'event'], ['year' => 1920, 'to_mm' => 1435, 'from_mm' => 1000, 'source' => 'curated']];
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'event'], Gauge::at(1960, null, [], [], $conv), 'dernière conversion passée');
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'curated'], Gauge::at(1930, null, [], [], $conv));
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'curated'], Gauge::at(1920, null, [], [], $conv), 'année même : convertie');
        $this->assertSame(['mm' => 1000, 'class' => 'metre', 'source' => 'curated'], Gauge::at(1910, null, [], [], $conv), 'avant : écartement d\'origine');

        // Origine inconnue de la conversion : écartement ancien relevé ailleurs.
        $c = [['year' => 1930, 'to_mm' => 1435, 'from_mm' => null, 'source' => 'event']];
        $this->assertSame(['mm' => 600, 'class' => 'narrow', 'source' => 'event'], Gauge::at(1910, null, [self::g(600, 'archeo')], [], $c));
        $this->assertSame(['mm' => null, 'class' => 'narrow', 'source' => 'bdtopo'],
            Gauge::at(1910, null, [], [['mm' => null, 'class' => 'narrow', 'source' => 'bdtopo']], $c), 'classe seule');
        // Ancien = nouveau : pas d'information, on continue (voie actuelle).
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], Gauge::at(1910, self::g(1435, 'osm'), [self::g(1435, 'archeo')], [], $c));
    }

    public function test_at_current_track_except_dated_older_alignment(): void
    {
        $current = self::g(1435, 'osm');
        $ohm = self::g(1000, 'ohm', ['from' => 1900, 'until' => 1950]);
        $this->assertSame(['mm' => 1000, 'class' => 'metre', 'source' => 'ohm'], Gauge::at(1930, $current, [$ohm], [], []));
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], Gauge::at(1950, $current, [$ohm], [], []), 'à la date de fin : nouvelle voie');
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], Gauge::at(1890, $current, [$ohm], [], []), 'avant le tracé ancien');
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], Gauge::at(1930, $current, [self::g(1435, 'ohm', ['until' => 1950])], [], []), 'même classe');
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], Gauge::at(1930, $current, [self::g(1000, 'ohm')], [], []), 'sans date de fin');
        $this->assertSame(['mm' => null, 'class' => 'narrow', 'source' => 'bdtopo'], Gauge::at(1930, ['mm' => null, 'class' => 'narrow', 'source' => 'bdtopo'], [], [], []));
    }

    public function test_at_vanished_track(): void
    {
        $h = self::g(600, 'ohm', ['from' => 1900, 'until' => 1930]);
        $this->assertSame(['mm' => 600, 'class' => 'narrow', 'source' => 'ohm'], Gauge::at(1935, null, [$h], [], []), 'jusqu\'à 10 ans après la fin');
        $this->assertSame(['mm' => 600, 'class' => 'narrow', 'source' => 'ohm'], Gauge::at(1950, null, [$h], [], []), 'hors période : indice ancien quand même');
        $this->assertSame(['mm' => 1000, 'class' => 'metre', 'source' => 'line'], Gauge::at(1930, null, [], [self::g(1000, 'line')], []));
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'network'], Gauge::at(1930, null, [], [], [], 1435));
        $this->assertSame(['mm' => null, 'class' => null, 'source' => null], Gauge::at(1930, null, [], [], []));
        $this->assertSame(['mm' => null, 'class' => null, 'source' => null], Gauge::at(1930, ['mm' => null, 'class' => null, 'source' => 'osm'], [['mm' => null, 'class' => null, 'source' => 'x']], [], []));
    }
}
