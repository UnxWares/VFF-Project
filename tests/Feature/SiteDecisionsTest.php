<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Décisions de Baptiste du 20/11/2026 sur les textes et les liens du site : libellé court « Schéma »
 * de la vue ferroviaire dans la barre de la carte, domaine unxwares.com, plus de lien X dans le pied
 * de page, plus de mention de partenariat des associations CFTA, AAATV et MFCF dans les crédits.
 */
class SiteDecisionsTest extends TestCase
{
    public function test_the_map_bar_says_schema_while_the_legend_keeps_railway_view(): void
    {
        $this->assertSame(['Schéma', 'Schematic', 'Schema'], array_map(fn (string $l) => __('maps.view.schema', [], $l), ['fr', 'en', 'de']));
        $this->assertSame('Vue ferroviaire, :year', __('maps.schema.legend_title', [], 'fr'));
        $this->assertSame('Railway view, :year', __('maps.schema.legend_title', [], 'en'));
        $this->assertStringContainsString('« Schéma »', __('maps.read.schema_text', [], 'fr'));
    }

    public function test_the_footer_has_no_x_link_and_points_to_unxwares_com(): void
    {
        $footer = (string) file_get_contents(resource_path('js/Components/Footer.svelte'));

        $this->assertStringNotContainsString('x.com', $footer);
        $this->assertStringNotContainsString('bi-twitter-x', $footer);
        $this->assertStringContainsString('https://www.unxwares.com', $footer);
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->assertArrayNotHasKey('twitter', (array) __('footer', [], $locale));
        }
    }

    public function test_unxwares_studio_is_gone_from_the_site_sources(): void
    {
        foreach ([resource_path('js'), resource_path('views'), lang_path()] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $this->assertStringNotContainsString('unxwares.studio', (string) file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }

    public function test_credits_do_not_present_the_associations_as_partners(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $credits = (string) json_encode(__('credits', [], $locale), JSON_UNESCAPED_UNICODE);
            foreach (['CFTA', 'AAATV', 'MFCF'] as $name) {
                $this->assertStringNotContainsString($name, $credits, "{$name} ({$locale})");
            }
        }
    }
}
