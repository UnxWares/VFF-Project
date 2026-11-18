<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Services\Media\CommonsClient;
use App\Services\Media\CommonsMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fonctions de CommonsMetadata non couvertes par CommonsMetadataTest :
 * ligne de crédit, date d'après les catégories, type de document, URL de la
 * visionneuse, nettoyage HTML et typographie (pas de tiret long affiché).
 */
class CommonsMetadataHelpersTest extends TestCase
{
    public function test_attribution_line(): void
    {
        $this->assertSame('Jean Dupont, CC BY-SA 4.0, via Wikimedia Commons',
            CommonsMetadata::attribution('Jean Dupont', ['license' => 'CC BY-SA 4.0', 'rights_status' => Media::RIGHTS_OPEN_LICENSE], null));
        $this->assertSame('Public domain, via Wikimedia Commons',
            CommonsMetadata::attribution(null, ['license' => null, 'rights_status' => Media::RIGHTS_PUBLIC_DOMAIN], null));
        // Le texte d'attribution imposé par le fichier l'emporte sur l'auteur ; licence jamais inventée.
        $this->assertSame('Archives Y, licence non précisée, via Wikimedia Commons',
            CommonsMetadata::attribution('X', ['license' => null, 'rights_status' => Media::RIGHTS_UNKNOWN], 'Archives Y'));
    }

    /** @return array<string, array{0: list<string>, 1: ?array}> */
    public static function categoryProvider(): array
    {
        return [
            'année de photo' => [['1953 photographs of France', 'Trains'], ['year' => 1953, 'precision' => 'category', 'date' => null]],
            '« in 1969 »' => [['Ligne de Vincennes in 1969'], ['year' => 1969, 'precision' => 'category', 'date' => null]],
            'décennie de cartes postales' => [['1900s postcards of France'], ['year' => 1900, 'precision' => 'decade', 'date' => null]],
            '« in the 1930s »' => [['Trains in the 1930s'], ['year' => 1930, 'precision' => 'decade', 'date' => null]],
            'l\'année précise prime sur la décennie' => [['1900s postcards of France', '1907 postcards'], ['year' => 1907, 'precision' => 'category', 'date' => null]],
            'année invraisemblable' => [['1700 photographs'], null],
            'aucune date' => [['Gare de Lyon'], null],
            'aucune catégorie' => [[], null],
        ];
    }

    #[DataProvider('categoryProvider')]
    public function test_date_from_categories(array $categories, ?array $expected): void
    {
        $this->assertSame($expected, CommonsMetadata::dateFromCategories($categories));
    }

    /** @return array<string, array{0: string, 1: list<string>, 2: string, 3: string}> */
    public static function typeProvider(): array
    {
        return [
            'carte postale (titre)' => ['File:CPA Gare de Morlaix.jpg', [], 'image/jpeg', 'postcard'],
            'carte postale (numéro CP)' => ['File:Gare-CP123.jpg', [], 'image/jpeg', 'postcard'],
            'carte postale (catégorie)' => ['File:Gare.jpg', ['Postcards of Brittany'], 'image/jpeg', 'postcard'],
            'document PDF' => ['File:Horaire.pdf', [], 'application/pdf', 'document'],
            'plan de voies' => ['File:Plan de voies de la gare.png', [], 'image/png', 'plan'],
            'carte' => ['File:Carte du réseau.jpg', [], 'image/jpeg', 'map'],
            'carte (catégorie)' => ['File:Gare.jpg', ['Maps of France'], 'image/jpeg', 'map'],
            'photo par défaut' => ['File:Gare.jpg', [], 'image/jpeg', 'photo'],
        ];
    }

    #[DataProvider('typeProvider')]
    public function test_detect_type(string $title, array $categories, string $mime, string $expected): void
    {
        $this->assertSame($expected, CommonsMetadata::detectType($title, $categories, $mime));
    }

    public function test_display_url_uses_the_light_original_or_a_standard_thumbnail(): void
    {
        $w = CommonsClient::THUMB_WIDTH;
        $thumb = "https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare.jpg/{$w}px-Gare.jpg";
        $sized = fn (int $px) => "https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare.jpg/{$px}px-Gare.jpg";

        $this->assertSame('https://u/orig.jpg', CommonsMetadata::displayUrl($thumb, 'https://u/orig.jpg', 1000, 'image/jpeg'));
        $this->assertSame($sized(1280), CommonsMetadata::displayUrl($thumb, 'https://u/orig.jpg', 4000, 'image/jpeg'));
        // TIFF (non affichable par le navigateur) de 1000 px : miniature 960.
        $this->assertSame($sized(960), CommonsMetadata::displayUrl($thumb, 'https://u/orig.tif', 1000, 'image/tiff'));
        $this->assertSame($sized(1280), CommonsMetadata::displayUrl($thumb, 'https://u/orig.svg', 300, 'image/svg+xml'));
        $this->assertSame('https://u/orig.jpg', CommonsMetadata::displayUrl(null, 'https://u/orig.jpg', null, 'image/jpeg'));
        $this->assertNull(CommonsMetadata::displayUrl(null, null, null, 'image/jpeg'));
    }

    public function test_clean_removes_hidden_elements_and_bounds_length(): void
    {
        $this->assertSame('Gare de Lyon & quai', CommonsMetadata::clean('<span style="display:none">caché</span>Gare <b>de</b> Lyon<br/>&amp; quai'));
        $this->assertNull(CommonsMetadata::clean(''));
        $this->assertNull(CommonsMetadata::clean('<p> </p>'));
        $this->assertNull(CommonsMetadata::clean(null));
        $this->assertSame('aaaaaaaaa…', CommonsMetadata::clean(str_repeat('a', 20), 10));
        $this->assertSame(10, mb_strlen(CommonsMetadata::clean(str_repeat('é', 20), 10)));
    }

    public function test_plain_dashes_never_leave_a_long_dash(): void
    {
        $this->assertSame('A - B - C - D', CommonsMetadata::plainDashes('A — B – C -- D'));
        $this->assertSame('Gare - quai', CommonsMetadata::plainDashes('Gare ― quai'));
        $this->assertNull(CommonsMetadata::plainDashes(null));
        foreach (['x—y', 'x --- y', '—'] as $s) {
            $out = CommonsMetadata::plainDashes($s);
            $this->assertStringNotContainsString('—', $out, $s);
            $this->assertStringNotContainsString('--', $out, $s);
        }
    }

    public function test_file_title_and_url_helpers(): void
    {
        $this->assertSame('Gare de Lyon 1900', CommonsMetadata::titleFromFile('File:Gare_de_Lyon_1900.jpg'));
        $this->assertSame('X', CommonsMetadata::titleFromFile('Fichier:X.tiff'));
        $this->assertSame('https://x/y.jpg', CommonsMetadata::stripQuery('https://x/y.jpg?a=1&b=2'));
        $this->assertNull(CommonsMetadata::stripQuery(''));
        $this->assertNull(CommonsMetadata::stripQuery(null));
    }

    public function test_author_unknown_is_null_and_dashes_are_plain(): void
    {
        $this->assertNull(CommonsMetadata::author('Auteur inconnu'));
        $this->assertNull(CommonsMetadata::author('<i>Unknown author</i>'));
        $this->assertSame('Studio X - Paris', CommonsMetadata::author('Studio X — Paris'));
    }
}
