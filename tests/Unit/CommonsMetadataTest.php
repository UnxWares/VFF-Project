<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Services\Media\CommonsMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verrouille les règles de DROITS et de DATATION des médias Wikimedia Commons
 * (vff:import-commons-media) : licence jamais inventée ni réécrite, domaine
 * public reconnu, année représentée tirée des gabarits Commons. Logique pure.
 */
class CommonsMetadataTest extends TestCase
{
    /** @return array<string, array{0: array<string, string>, 1: string, 2: ?bool, 3: ?bool}> */
    public static function rightsProvider(): array
    {
        return [
            'CC BY-SA' => [['LicenseShortName' => 'CC BY-SA 4.0', 'License' => 'cc-by-sa-4.0'], Media::RIGHTS_OPEN_LICENSE, true, true],
            'CC BY' => [['LicenseShortName' => 'CC BY 2.0', 'License' => 'cc-by-2.0'], Media::RIGHTS_OPEN_LICENSE, true, false],
            'domaine public' => [['LicenseShortName' => 'Public domain', 'License' => 'pd', 'Copyrighted' => 'False'], Media::RIGHTS_PUBLIC_DOMAIN, true, false],
            'CC0' => [['LicenseShortName' => 'CC0', 'License' => 'cc0'], Media::RIGHTS_PUBLIC_DOMAIN, true, false],
            'Licence Ouverte' => [['LicenseShortName' => 'Licence Ouverte', 'License' => 'etalab-2.0'], Media::RIGHTS_OPEN_LICENSE, true, false],
            'NC' => [['LicenseShortName' => 'CC BY-NC-SA 2.0'], Media::RIGHTS_OPEN_LICENSE, false, true],
            'inconnue' => [[], Media::RIGHTS_UNKNOWN, null, null],
        ];
    }

    /** @param  array<string, string>  $ext */
    #[DataProvider('rightsProvider')]
    public function test_rights_are_derived_without_guessing(array $ext, string $status, ?bool $commercial, ?bool $shareAlike): void
    {
        $r = CommonsMetadata::rights($ext);

        $this->assertSame($status, $r['rights_status']);
        $this->assertSame($commercial, $r['commercial_ok']);
        $this->assertSame($shareAlike, $r['share_alike']);
        // La licence est reprise TELLE QUELLE (ou NULL), jamais réécrite.
        $this->assertSame($ext['LicenseShortName'] ?? null, $r['license']);
    }

    /** @return array<string, array{0: string, 1: ?int, 2: ?string}> */
    public static function dateProvider(): array
    {
        return [
            'avant (P1326)' => ['avant 1<sup>er</sup> janvier 1923<div style="display: none;">date QS:P,+1923-01-01T00:00:00Z/7,P1326,+1923-01-01T00:00:00Z/11</div>', 1923, 'before'],
            'entre (P1319/P1326)' => ['entre 1900 et 1910<div style="display: none;">date QS:P,+1950-00-00T00:00:00Z/7,P1319,+1900-00-00T00:00:00Z/9,P1326,+1910-00-00T00:00:00Z/9</div>', 1900, 'range'],
            'siècle seul' => ['XX<sup>e</sup> siècle<div style="display: none;">date QS:P,+1950-00-00T00:00:00Z/7</div>', null, null],
            'jour (j.m.aaaa)' => ['1.5.1931', 1931, 'day'],
            'ISO + heure' => ['2021-10-03 14:22:10', 2021, 'day'],
            'vers' => ['vers 1910', 1910, 'circa'],
            'décennie' => ['années 1950', 1950, 'decade'],
        ];
    }

    #[DataProvider('dateProvider')]
    public function test_depicted_year_from_commons_date(string $raw, ?int $year, ?string $precision): void
    {
        $d = CommonsMetadata::parseDate($raw);

        $this->assertSame($year, $d['year'] ?? null);
        $this->assertSame($precision, $d['precision'] ?? null);
    }

    public function test_unknown_author_is_null_and_dashes_are_plain(): void
    {
        $this->assertNull(CommonsMetadata::author('<div class="fn value">Auteur inconnu<span style="display: none;">Unknown author</span> ou non renseigné</div>'));
        $this->assertSame('Scan - Editeur - B.F. Paris (1803-1879)', CommonsMetadata::plainDashes('Scan -- Editeur -- B.F. Paris (1803-1879)'));
    }

    public function test_old_postcard_scan_date_is_not_the_depicted_year(): void
    {
        $m = CommonsMetadata::normalize([
            'title' => 'File:Gare-St-Erme-Bombardée-CPancienne.jpg',
            'info' => [
                'mime' => 'image/jpeg', 'width' => 800, 'height' => 500,
                'url' => 'https://upload.wikimedia.org/y.jpg', 'thumburl' => 'https://upload.wikimedia.org/y.jpg',
                'extmetadata' => ['DateTimeOriginal' => ['value' => '2011-03-02'], 'LicenseShortName' => ['value' => 'Public domain']],
            ],
        ]);

        $this->assertSame('postcard', $m['type']);
        $this->assertNull($m['depicted_year']);
    }

    public function test_photo_dated_before_photography_falls_back_on_title_year(): void
    {
        $m = CommonsMetadata::normalize([
            'title' => 'File:Trains ligne Guingamp Carhaix 1928.jpg',
            'info' => [
                'mime' => 'image/jpeg', 'width' => 444, 'height' => 547,
                'url' => 'https://upload.wikimedia.org/x.jpg', 'thumburl' => 'https://upload.wikimedia.org/x.jpg',
                'extmetadata' => ['DateTimeOriginal' => ['value' => '1828-05-11'], 'LicenseShortName' => ['value' => 'Public domain'], 'License' => ['value' => 'pd']],
            ],
        ]);

        $this->assertSame(1928, $m['depicted_year']);
        $this->assertSame('Public domain, via Wikimedia Commons', $m['attribution']);
    }
}
