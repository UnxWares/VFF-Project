<?php

namespace Tests\Unit;

use App\Support\FontPreloads;
use Illuminate\Foundation\Vite;
use Tests\TestCase;

/**
 * Polices : la feuille commune déclare une police de repli ajustée par police web et la place dans chaque pile ;
 * les fichiers préchargés sont retrouvés dans le manifeste Vite quel que soit le chemin pnpm de leur clé.
 */
class FontFallbackTest extends TestCase
{
    public function test_every_web_font_stack_names_its_adjusted_fallback(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/general.css'));

        foreach (['Lato', 'Gabarito', 'Inter', 'Montserrat'] as $family) {
            $this->assertStringContainsString("font-family: '$family Fallback';", $css, "Repli de $family non déclaré");
            $this->assertStringContainsString("$family, '$family Fallback', 'Helvetica Neue', Arial", $css, "Pile de $family sans repli");
            $this->assertMatchesRegularExpression(
                "/font-family: '$family Fallback';[^}]*size-adjust:[^}]*ascent-override:[^}]*descent-override:[^}]*line-gap-override:/",
                $css,
            );
        }
    }

    public function test_preload_urls_follow_the_vite_manifest(): void
    {
        $this->withVite();
        $dir = 'build-fonts-test';
        $path = public_path($dir);
        @mkdir($path);
        $manifest = [];
        foreach (FontPreloads::FILES as $name) {
            $manifest["node_modules/.pnpm/@fontsource+x@1.0.0/node_modules/@fontsource/x/files/$name.woff2"] = ['file' => "assets/$name-abc123.woff2"];
        }
        file_put_contents("$path/manifest.json", json_encode($manifest));

        try {
            $vite = app(Vite::class);
            $vite->useHotFile('/tmp/aucun-hot-test');
            $urls = FontPreloads::urls($dir);
        } finally {
            @unlink("$path/manifest.json");
            @rmdir($path);
        }

        $this->assertCount(count(FontPreloads::FILES), $urls);
        $this->assertStringEndsWith('/'.$dir.'/assets/lato-latin-400-normal-abc123.woff2', $urls[0]);
    }

    public function test_no_preload_without_manifest(): void
    {
        $this->withVite();
        $vite = app(Vite::class);
        $vite->useHotFile('/tmp/aucun-hot-test');

        $this->assertSame([], FontPreloads::urls('build-absent'));
    }
}
