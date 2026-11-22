<?php

namespace Tests\Unit;

use App\Support\Media\CommonsImagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Liste blanche des chemins du relais des vignettes de Commons (App\Support\Media\CommonsImagePath). */
class CommonsImagePathTest extends TestCase
{
    /** @return array<string, array{string, string, string, int|null}> chemin brut, chemin canonique, extension, largeur */
    public static function accepted(): array
    {
        return [
            'vignette 500 px' => [
                'thumb/3/36/Gare-Roscoff-CPancienne.jpg/500px-Gare-Roscoff-CPancienne.jpg',
                'thumb/3/36/Gare-Roscoff-CPancienne.jpg/500px-Gare-Roscoff-CPancienne.jpg', 'jpg', 500,
            ],
            'original JPEG' => ['7/79/CFA-Morlaix.jpg', '7/79/CFA-Morlaix.jpg', 'jpg', null],
            'vignette de TIFF, préfixe lossy-page1' => [
                'thumb/a/ac/Alter_Bahnhof_Metz.tif/lossy-page1-1280px-Alter_Bahnhof_Metz.tif.jpg',
                'thumb/a/ac/Alter_Bahnhof_Metz.tif/lossy-page1-1280px-Alter_Bahnhof_Metz.tif.jpg', 'jpg', 1280,
            ],
            'vignette PNG de SVG' => ['thumb/3/3f/Plan.svg/330px-Plan.svg.png', 'thumb/3/3f/Plan.svg/330px-Plan.svg.png', 'png', 330],
            'nom abrégé en thumbnail' => ['thumb/7/79/CFA-Morlaix.jpg/500px-thumbnail.jpg', 'thumb/7/79/CFA-Morlaix.jpg/500px-thumbnail.jpg', 'jpg', 500],
            // Encodage canonique : `'` et `(` encodés, comme `%27` et `%28` sur Commons, sans changer l'image.
            'caractères encodés' => [
                'thumb/2/26/Etat_140C_186%3F_%288131442677%29.jpg/500px-Etat_140C_186%3F_%288131442677%29.jpg',
                'thumb/2/26/Etat_140C_186%3F_%288131442677%29.jpg/500px-Etat_140C_186%3F_%288131442677%29.jpg', 'jpg', 500,
            ],
            'accent en UTF-8' => [
                'thumb/2/2d/Voie_Limoges-Angoul%C3%AAme_avec_des_mauvaises_herbes_sur_les_rails.jpg/1280px-Voie_Limoges-Angoul%C3%AAme_avec_des_mauvaises_herbes_sur_les_rails.jpg',
                'thumb/2/2d/Voie_Limoges-Angoul%C3%AAme_avec_des_mauvaises_herbes_sur_les_rails.jpg/1280px-Voie_Limoges-Angoul%C3%AAme_avec_des_mauvaises_herbes_sur_les_rails.jpg', 'jpg', 1280,
            ],
        ];
    }

    #[DataProvider('accepted')]
    public function test_accepts_the_shapes_vff_uses(string $raw, string $canonical, string $extension, ?int $width): void
    {
        $path = CommonsImagePath::parse($raw);

        $this->assertNotNull($path);
        $this->assertSame($canonical, $path->path);
        $this->assertSame($extension, $path->extension);
        $this->assertSame($width, $path->width);
        $this->assertSame('https://upload.wikimedia.org/wikipedia/commons/'.$canonical, $path->upstreamUrl());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $path->cacheKey());
    }

    /** @return array<string, array{string}> */
    public static function refused(): array
    {
        return [
            'vide' => [''],
            'remontée' => ['../../../etc/passwd'],
            'remontée dans le chemin' => ['7/79/../CFA-Morlaix.jpg'],
            'remontée encodée' => ['thumb/7/79/%2E%2E/500px-CFA-Morlaix.jpg'],
            'barre oblique encodée' => ['7/79/a%2Fb.jpg'],
            'contre-oblique encodée' => ['7/79/a%5Cb.jpg'],
            'double encodage' => ['7/79/CFA%252FMorlaix.jpg'],
            'octet nul' => ['7/79/CFA-Morlaix%00.jpg'],
            'retour à la ligne' => ['7/79/CFA-Morlaix%0A.jpg'],
            'UTF-8 invalide' => ['7/79/CFA%FF.jpg'],
            'échappement malformé' => ['7/79/CFA%G1.jpg'],
            'caractère brut hors liste' => ['7/79/CFA Morlaix.jpg'],
            'empreinte fausse' => ['7/80/CFA-Morlaix.jpg'],
            'empreinte incohérente' => ['8/79/CFA-Morlaix.jpg'],
            'original SVG' => ['3/3f/Plan.svg'],
            'original TIFF' => ['a/ac/Alter_Bahnhof_Metz.tif'],
            'vignette SVG' => ['thumb/3/3f/Plan.svg/500px-Plan.svg'],
            'largeur non utilisée' => ['thumb/7/79/CFA-Morlaix.jpg/4000px-CFA-Morlaix.jpg'],
            'largeur à zéro devant' => ['thumb/7/79/CFA-Morlaix.jpg/0500px-CFA-Morlaix.jpg'],
            'vignette d’un autre fichier' => ['thumb/7/79/CFA-Morlaix.jpg/500px-Autre.jpg'],
            'segment en trop' => ['thumb/7/79/CFA-Morlaix.jpg/500px-CFA-Morlaix.jpg/x'],
            'autre wiki' => ['wikipedia/en/7/79/CFA-Morlaix.jpg'],
            'schéma' => ['https://evil.example/7/79/CFA-Morlaix.jpg'],
            'trop long' => ['7/79/'.str_repeat('a', 1100).'.jpg'],
        ];
    }

    public function test_exposes_the_decoded_file_name_with_underscores_for_the_allowlist(): void
    {
        $thumb = CommonsImagePath::parse('thumb/3/36/Gare-Roscoff-CPancienne.jpg/500px-Gare-Roscoff-CPancienne.jpg');
        $original = CommonsImagePath::parse('7/79/CFA-Morlaix.jpg');

        $this->assertSame('Gare-Roscoff-CPancienne.jpg', $thumb?->file);
        $this->assertSame('CFA-Morlaix.jpg', $original?->file);
    }

    #[DataProvider('refused')]
    public function test_refuses_anything_outside_the_whitelist(string $raw): void
    {
        $this->assertNull(CommonsImagePath::parse($raw));
    }

    public function test_reads_full_urls_of_both_wikimedia_thumbnail_hosts_only(): void
    {
        $path = 'thumb/3/36/Gare-Roscoff-CPancienne.jpg/500px-Gare-Roscoff-CPancienne.jpg';

        $this->assertSame($path, CommonsImagePath::fromUrl('https://upload.wikimedia.org/wikipedia/commons/'.$path)?->path);
        $this->assertSame($path, CommonsImagePath::fromUrl('https://thumb.wikimedia.org/wikipedia/commons/'.$path)?->path);
        foreach ([
            'http://upload.wikimedia.org/wikipedia/commons/'.$path,
            'https://upload.wikimedia.org:444/wikipedia/commons/'.$path,
            'https://user@upload.wikimedia.org/wikipedia/commons/'.$path,
            'https://upload.wikimedia.org/wikipedia/commons/'.$path.'?a=1',
            'https://upload.wikimedia.org/wikipedia/commons/'.$path.'#x',
            'https://upload.wikimedia.org.evil.example/wikipedia/commons/'.$path,
            'https://127.0.0.1/wikipedia/commons/'.$path,
            'https://[::1]/wikipedia/commons/'.$path,
            'https://commons.wikimedia.org/wikipedia/commons/'.$path,
            '//upload.wikimedia.org/wikipedia/commons/'.$path,
            'file:///wikipedia/commons/'.$path,
        ] as $url) {
            $this->assertNull(CommonsImagePath::fromUrl($url), $url);
        }
    }
}
