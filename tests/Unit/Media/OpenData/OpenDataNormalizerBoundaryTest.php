<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OpenDataNormalizer;
use PHPUnit\Framework\TestCase;

/** Gardes et frontières du normaliseur des fonds ouverts, sur un jeu synthétique (une mutation par assertion). */
class OpenDataNormalizerBoundaryTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private static function dataset(array $over = []): array
    {
        return $over + [
            'fields' => ['id' => 'id', 'title' => 'titre', 'image' => 'img', 'date' => 'date', 'point' => 'point', 'country' => 'pays', 'commune' => 'commune', 'license' => 'licence'],
            'license' => ['kind' => 'fixed', 'license' => 'Licence Ouverte 2.0', 'rights_status' => 'open_license', 'commercial_ok' => true, 'derivatives_ok' => true],
            'rail' => ['fields' => ['title'], 'pattern' => '/gare|train/i'],
        ];
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private static function row(array $over = []): array
    {
        return $over + ['id' => 'A1', 'titre' => 'La gare', 'img' => 'https://example.org/a.jpg', 'date' => '1950', 'point' => '48.5, 2.5', 'commune' => 'Paris (VIIe arr.)', 'pays' => 'France'];
    }

    private static function reason(array $dataset, array $row): ?string
    {
        $r = OpenDataNormalizer::normalize($dataset, $row);

        return $r['ok'] ? null : $r['reason'];
    }

    public function test_baseline_row_is_accepted(): void
    {
        $r = OpenDataNormalizer::normalize(self::dataset(), self::row());
        $this->assertTrue($r['ok']);
        $this->assertSame('Paris', $r['candidate']['commune']);
        $this->assertSame([48.5, 2.5], [$r['candidate']['lat'], $r['candidate']['lng']]);
    }

    public function test_country_must_match_only_when_present(): void
    {
        $d = self::dataset(['country' => 'France']);
        $this->assertNull(self::reason($d, self::row()));
        $this->assertSame('hors_france', self::reason($d, self::row(['pays' => 'Italie'])));
        $this->assertNull(self::reason($d, self::row(['pays' => null])), 'pays absent : gardé');
        $this->assertNull(self::reason($d, self::row(['pays' => '  '])), 'pays vide : gardé');
        $this->assertNull(self::reason($d, self::row(['pays' => '*'])), '« * » vaut vide');
        $this->assertNull(self::reason($d, self::row(['pays' => '/'])), '« / » vaut vide');
        $this->assertNull(self::reason(self::dataset(), self::row(['pays' => 'Italie'])), 'sans réglage : pas de contrôle');
    }

    public function test_image_must_be_an_http_address(): void
    {
        $this->assertNull(self::reason(self::dataset(), self::row(['img' => 'http://example.org/a.jpg'])));
        $this->assertNull(self::reason(self::dataset(), self::row(['img' => 'HTTPS://EXAMPLE.ORG/A.JPG'])));
        foreach (['ftp://example.org/a.jpg', 'javascript:alert(1)', '//example.org/a.jpg', 'a.jpg', '', null] as $bad) {
            $this->assertSame('sans_image', self::reason(self::dataset(), self::row(['img' => $bad])), (string) $bad);
        }
    }

    public function test_identifier_is_required_and_split_on_the_dash_only_when_asked(): void
    {
        $this->assertSame('sans_identifiant', self::reason(self::dataset(), self::row(['id' => null])));
        $this->assertSame('sans_identifiant', self::reason(self::dataset(), self::row(['id' => '  '])));
        $r = OpenDataNormalizer::normalize(self::dataset(['split_dash' => true]), self::row(['id' => 'B2 - Gare de Lyon']));
        $this->assertSame(['B2', 'Gare de Lyon'], [$r['candidate']['id'], $r['candidate']['title']]);
        $keep = OpenDataNormalizer::normalize(self::dataset(['split_dash' => true]), self::row(['id' => 'B2-3']));
        $this->assertSame(['B2-3', 'La gare'], [$keep['candidate']['id'], $keep['candidate']['title']], 'tiret sans espaces : pas de coupe');
        $space = OpenDataNormalizer::normalize(self::dataset(['split_dash' => true]), self::row(['id' => 'B2 3']));
        $this->assertSame(['B2 3', 'La gare'], [$space['candidate']['id'], $space['candidate']['title']], 'espace seul : pas de coupe');
        $off = OpenDataNormalizer::normalize(self::dataset(), self::row(['id' => 'B2 - Gare de Lyon']));
        $this->assertSame('B2 - Gare de Lyon', $off['candidate']['id']);
    }

    public function test_period_bounds_have_a_one_year_margin(): void
    {
        $d = self::dataset(['min_year' => 1900, 'max_year' => 1950]);
        foreach ([1899 => null, 1900 => null, 1951 => null, 1950 => null, 1898 => 'hors_periode', 1952 => 'hors_periode'] as $year => $expected) {
            $this->assertSame($expected, self::reason($d, self::row(['date' => (string) $year])), (string) $year);
        }
        $this->assertNull(self::reason($d, self::row(['date' => null])), 'sans date : gardé');
        $this->assertNull(self::reason(self::dataset(['min_year' => 1900]), self::row(['date' => '2020'])), 'sans max : pas de borne haute');
        $this->assertNull(self::reason(self::dataset(['max_year' => 1950]), self::row(['date' => '1500'])), 'sans min : pas de borne basse');
    }

    public function test_point_bounds_and_precision(): void
    {
        $lat = fn (string $p) => OpenDataNormalizer::normalize(self::dataset(), self::row(['point' => $p]))['candidate'];
        $this->assertSame([90.0, 180.0], [$lat('90, 180')['lat'], $lat('90, 180')['lng']]);
        $this->assertSame([-90.0, -180.0], [$lat('-90, -180')['lat'], $lat('-90, -180')['lng']]);
        $this->assertNull($lat('90.1, 2')['lat']);
        $this->assertNull($lat('45, 180.1')['lat']);
        $this->assertNull($lat('0, 0')['lat'], 'île nulle : refusée');
        $this->assertSame([0.0, 2.0], [$lat('0, 2')['lat'], $lat('0, 2')['lng']], 'équateur seul : valide');
        $this->assertSame([2.0, 0.0], [$lat('2, 0')['lat'], $lat('2, 0')['lng']], 'méridien seul : valide');
        $this->assertSame(48.1234568, $lat('48.12345678, 2')['lat'], 'arrondi à 7 décimales');
        $this->assertNull($lat('48.5')['lat']);
        $this->assertNull($lat('abc, def')['lat']);
        $this->assertNull($lat('')['lat']);
        $this->assertSame([1.5, 2.5], [$lat('  1.5 ,2.5 ')['lat'], $lat('  1.5 ,2.5 ')['lng']]);
    }

    public function test_hidden_coordinates_of_the_driver_win(): void
    {
        $r = OpenDataNormalizer::normalize(self::dataset(), self::row(['__lat' => 47.0, '__lng' => 3.0, 'point' => '1, 1']));
        $this->assertSame([47.0, 3.0], [$r['candidate']['lat'], $r['candidate']['lng']]);
    }

    public function test_unknown_licence_value_refuses_and_known_one_keeps_the_declared_text(): void
    {
        $d = self::dataset(['license' => ['kind' => 'map', 'map' => ['/^CC0/' => ['license' => 'CC0 1.0', 'rights_status' => 'public_domain']]]]);
        $this->assertSame('licence_inconnue', self::reason($d, self::row(['licence' => 'tous droits reserves'])));
        $this->assertSame('licence_inconnue', self::reason($d, self::row(['licence' => null])), 'licence absente : jamais présumée');
        $this->assertSame('licence_inconnue', self::reason($d, self::row(['licence' => '*'])));
        $r = OpenDataNormalizer::normalize($d, self::row(['licence' => 'CC0 1.0 universel']));
        $this->assertSame('CC0 1.0 universel', $r['candidate']['rights']['declared']);
    }

    public function test_non_railway_rows_and_missing_titles(): void
    {
        $this->assertSame('non_ferroviaire', self::reason(self::dataset(), self::row(['titre' => 'Une église'])));
        $this->assertNull(self::reason(self::dataset(), self::row(['titre' => 'Le TRAIN'])), 'casse ignorée');
    }

    public function test_commune_helper(): void
    {
        $this->assertSame('Paris', OpenDataNormalizer::commune('Paris (VIIe arr.)'));
        $this->assertSame('Paris', OpenDataNormalizer::commune(' Paris '));
        $this->assertNull(OpenDataNormalizer::commune('(VIIe arr.)'));
        $this->assertNull(OpenDataNormalizer::commune(null));
        $this->assertNull(OpenDataNormalizer::commune(''));
        $this->assertSame('Évry Courcouronnes', OpenDataNormalizer::commune('Évry (Essonne) Courcouronnes'));
    }

    public function test_hostile_and_very_long_values_do_not_break_the_reader(): void
    {
        $long = str_repeat('gare ', 20000);
        $r = OpenDataNormalizer::normalize(self::dataset(), self::row(['titre' => $long, 'id' => str_repeat('9', 10000)]));
        $this->assertTrue($r['ok']);
        $r = OpenDataNormalizer::normalize(self::dataset(), self::row(['titre' => "Gare \u{202E}\u{0000}東京", 'commune' => '東京']));
        $this->assertTrue($r['ok']);
        $this->assertSame('東京', $r['candidate']['commune']);
    }

    public function test_require_true_and_patterns(): void
    {
        $this->assertSame('hors_open_data', self::reason(self::dataset(['require_true' => 'open']), self::row(['open' => 'true'])), 'chaîne : pas un vrai booléen');
        $this->assertNull(self::reason(self::dataset(['require_true' => 'open']), self::row(['open' => true])));
        $this->assertSame('hors_fonds', self::reason(self::dataset(['require_pattern' => ['commune' => '/^Lyon/']]), self::row()));
        $this->assertSame('hors_france', self::reason(self::dataset(['reject_pattern' => ['commune' => '/Paris/']]), self::row()));
    }
}
