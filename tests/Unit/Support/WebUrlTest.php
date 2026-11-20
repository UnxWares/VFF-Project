<?php

namespace Tests\Unit\Support;

use App\Support\WebUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Adresses venues d'une source extérieure, rendues en lien ou en image : http, https et adresses sans
 * schéma seulement (relecture du 20/11/2026 : `LicenseUrl` de Commons en `javascript:`).
 */
class WebUrlTest extends TestCase
{
    /** @return array<string, array{0: mixed}> */
    public static function refused(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'casse mélangée' => ['JaVaScRiPt:alert(1)'],
            'espaces en tête' => ['   javascript:alert(1)'],
            'retour à la ligne et tabulation en tête' => ["\n\tjavascript:alert(1)"],
            'tabulation dans le schéma' => ["java\tscript:alert(1)"],
            'caractère nul en tête' => ["\0javascript:alert(1)"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'data en majuscules' => ['DATA:text/html;base64,PHNjcmlwdD4='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'chemin relatif' => ['/storage/a.jpg'],
            'schéma seul' => ['https:'],
            'sans hôte' => ['https://'],
            'retour à la ligne au milieu' => ["https://example.org/\njavascript:alert(1)"],
            'vide' => [''],
            'nul' => [null],
            'nombre' => [42],
        ];
    }

    #[DataProvider('refused')]
    public function test_an_address_that_is_not_http_becomes_null(mixed $url): void
    {
        $this->assertNull(WebUrl::safe($url));
    }

    public function test_http_https_and_scheme_relative_addresses_are_kept_trimmed(): void
    {
        $this->assertSame('https://creativecommons.org/licenses/by-sa/4.0', WebUrl::safe('https://creativecommons.org/licenses/by-sa/4.0'));
        $this->assertSame('HTTP://example.org/a', WebUrl::safe('HTTP://example.org/a'));
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Gare_de_Bréauté.jpg', WebUrl::safe("  https://commons.wikimedia.org/wiki/File:Gare_de_Bréauté.jpg\n"));
        $this->assertSame('//upload.wikimedia.org/thumb/500.jpg', WebUrl::safe('//upload.wikimedia.org/thumb/500.jpg'));
    }
}
