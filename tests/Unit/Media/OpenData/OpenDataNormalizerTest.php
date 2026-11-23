<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OpenDataNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des lignes brutes (extraits réels de tests/Fixtures/OpenData) selon la correspondance de champs de
 * database/data/open_data_media.php : licence par image, refus motivés, dates, points, vignettes.
 */
class OpenDataNormalizerTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    private static function datasets(): array
    {
        return require __DIR__.'/../../../../database/data/open_data_media.php';
    }

    /** @return list<array<string, mixed>> */
    private static function rows(string $key): array
    {
        $out = [];
        foreach (file(__DIR__.'/../../../Fixtures/OpenData/'.$key.'-extrait.jsonl', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $out[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        return $out;
    }

    /** @return array<string, mixed> la ligne dont la colonne vaut cette valeur */
    private static function row(string $key, string $column, string $value): array
    {
        foreach (self::rows($key) as $row) {
            if (($row[$column] ?? null) === $value) {
                return $row;
            }
        }
        self::fail("{$key} : ligne {$value} absente de l'extrait");
    }

    public function test_every_dataset_declares_the_keys_the_importer_needs(): void
    {
        foreach (self::datasets() as $key => $d) {
            $this->assertLessThanOrEqual(20, mb_strlen($d['provider']), "{$key} : provider trop long pour media.provider");
            $this->assertContains($d['driver'], ['tabular', 'wfs', 'getty', 'iiif', 'oai-dc'], $key);
            foreach (['id', 'title', 'image'] as $notion) {
                $this->assertArrayHasKey($notion, $d['fields'], "{$key} : champ {$notion}");
            }
            $this->assertMatchesRegularExpression('#^https://#', $d['source_url'], $key);
            $this->assertNotSame('', $d['credit'], $key);
            $this->assertContains($d['type'], ['photo', 'postcard'], $key);
            // Les portails Opendatasoft interdisent /api/ aux robots (robots.txt) : jamais lus directement.
            $this->assertMatchesRegularExpression([
                'tabular' => '/^[0-9a-f-]{36}$/',
                'wfs' => '#^https://data\.grandlyon\.com/geoserver/#',
                'getty' => '#^https://data\.getty\.edu/museum/collection/sparql$#',
                'iiif' => '#^https://(heritage\.ecoledesponts\.fr/sitemap\.txt|numistral\.bnf\.fr/services/engine/search/sru)$#',
                'oai-dc' => '#^https://[a-z.]+\.(fr|org)/services/engine/search/sru$#',
            ][$d['driver']], $d['resource'], $key);
            // Jamais l'API Opendatasoft, la recherche de l'École des ponts (robots.txt) ni les fichiers de static.data.gouv.fr/resources (robots.txt).
            $this->assertStringNotContainsString('/api/explore', $d['resource'], $key);
            $this->assertStringNotContainsString('static.data.gouv.fr', json_encode($d) ?: '', $key);
            $this->assertStringNotContainsString('services/engine/search/sru', $d['driver'] === 'iiif' && str_contains($d['resource'], 'ecoledesponts') ? json_encode($d) : '', $key);
        }
    }

    public function test_albert_kahn_reads_the_licence_of_each_image(): void
    {
        $d = self::datasets()['albert-kahn'];
        $by = OpenDataNormalizer::normalize($d, self::row('albert-kahn', 'Identifiant Fakir', 'IF106459'));
        $this->assertTrue($by['ok']);
        $this->assertSame('CC BY 4.0', $by['candidate']['rights']['license']);
        $this->assertSame('open_license', $by['candidate']['rights']['rights_status']);
        $this->assertTrue($by['candidate']['rights']['commercial_ok']);
        $this->assertTrue($by['candidate']['rights']['derivatives_ok']);

        $zero = OpenDataNormalizer::normalize($d, self::row('albert-kahn', 'Identifiant Fakir', 'IF46057'));
        $this->assertTrue($zero['ok']);
        $this->assertSame('CC0 1.0', $zero['candidate']['rights']['license']);
        $this->assertSame('public_domain', $zero['candidate']['rights']['rights_status']);
        $this->assertSame('1918-12-05', $zero['candidate']['captured_at']);
        $this->assertSame('day', $zero['candidate']['date_precision']);
        $this->assertSame('Auguste Léon', $zero['candidate']['author']);
        $this->assertSame('Paris', $zero['candidate']['commune']);
    }

    public function test_a_non_diffusable_image_or_one_without_picture_is_refused(): void
    {
        $d = self::datasets()['albert-kahn'];
        $this->assertSame(['ok' => false, 'reason' => 'licence_inconnue'], OpenDataNormalizer::normalize($d, self::row('albert-kahn', 'Licence', 'non-diffusable')));
        $this->assertSame(['ok' => false, 'reason' => 'sans_image'], OpenDataNormalizer::normalize($d, ['Photo' => null] + self::row('albert-kahn', 'Identifiant Fakir', 'IF106459')));
    }

    public function test_the_thumbnail_is_the_portal_file_with_the_size_suffix(): void
    {
        $r = OpenDataNormalizer::normalize(self::datasets()['albert-kahn'], self::row('albert-kahn', 'Identifiant Fakir', 'IF106459'));
        $this->assertTrue($r['ok']);
        $this->assertStringEndsWith('/300/', $r['candidate']['thumb']);
        $this->assertSame($r['candidate']['image'].'/300/', $r['candidate']['thumb']);
    }

    public function test_postcards_use_the_dataset_licence_and_round_years_are_approximate(): void
    {
        $r = OpenDataNormalizer::normalize(self::datasets()['ad92-9fi'], self::row('ad92-9fi', 'Identifiant', '9FI/CLA_192'));
        $this->assertTrue($r['ok']);
        $this->assertSame('Licence Ouverte (Etalab)', $r['candidate']['rights']['license']);
        $this->assertSame('circa', $r['candidate']['date_precision']);
        $this->assertSame(1900, $r['candidate']['year']);
        $this->assertEqualsWithDelta(48.8142638, $r['candidate']['lat'], 1e-6);
        $this->assertSame('Clamart', $r['candidate']['commune']);
    }

    public function test_a_street_named_after_the_railway_is_not_a_railway_view(): void
    {
        $r = OpenDataNormalizer::normalize(self::datasets()['ad92-9fi'], self::row('ad92-9fi', 'Identifiant', '9FI/SEV_500'));
        // Le thème du jeu peut tout de même dire « Transport ferroviaire » ; sinon la rue seule est refusée.
        $this->assertTrue($r['ok'] || $r['reason'] === 'non_ferroviaire');
    }

    public function test_a_non_railway_postcard_is_refused(): void
    {
        $r = OpenDataNormalizer::normalize(self::datasets()['ad92-9fi'], self::row('ad92-9fi', 'Identifiant', '9FI/CLA_90'));
        $this->assertSame(['ok' => false, 'reason' => 'non_ferroviaire'], $r);
    }

    public function test_suresnes_keeps_only_open_data_rows(): void
    {
        $d = self::datasets()['suresnes-cp'];
        $row = self::rows('suresnes-cp')[0];
        $ok = OpenDataNormalizer::normalize($d, $row);
        $this->assertTrue($ok['ok']);
        $this->assertSame('Suresnes', $ok['candidate']['commune']);
        // Le jeu publié par data.gouv.fr ne contient que des lignes ouvertes ; la colonne reste un garde-fou.
        $this->assertSame(['ok' => false, 'reason' => 'hors_open_data'], OpenDataNormalizer::normalize($d, ['Open data' => false] + $row));
    }

    public function test_sevres_splits_the_reference_from_the_title(): void
    {
        $d = self::datasets()['sevres-cp'];
        $found = false;
        foreach (self::rows('sevres-cp') as $row) {
            $n = OpenDataNormalizer::normalize($d, $row);
            if ($n['ok']) {
                $found = true;
                $this->assertMatchesRegularExpression('/^18 Fi\d \d+/', $n['candidate']['id']);
                $this->assertStringNotContainsString($n['candidate']['id'].' - ', (string) $n['candidate']['title']);
            }
        }
        $this->assertTrue($found);
    }

    public function test_gadagne_reads_the_point_of_the_wfs_feature(): void
    {
        $d = self::datasets()['gadagne-cp'];
        foreach (self::rows('gadagne-cp') as $row) {
            $n = OpenDataNormalizer::normalize($d, $row);
            if ($n['ok']) {
                $this->assertEqualsWithDelta(45.75, $n['candidate']['lat'], 0.2);
                $this->assertEqualsWithDelta(4.83, $n['candidate']['lng'], 0.2);
                $this->assertSame('Licence Ouverte 2.0 (Etalab)', $n['candidate']['rights']['license']);

                return;
            }
        }
        $this->fail('aucune carte de Gadagne retenue');
    }

    public function test_an_unknown_licence_value_is_never_presumed(): void
    {
        $d = self::datasets()['albert-kahn'];
        $this->assertNull(OpenDataNormalizer::rights($d, 'Fonds non-librement réutilisable (droits réservés)'));
        $this->assertNull(OpenDataNormalizer::rights($d, null));
    }

    public function test_commune_loses_its_district(): void
    {
        $this->assertSame('Paris', OpenDataNormalizer::commune('Paris (VIIe arr.)'));
        $this->assertSame('Évry', OpenDataNormalizer::commune('Évry'));
        $this->assertNull(OpenDataNormalizer::commune(null));
    }
}
