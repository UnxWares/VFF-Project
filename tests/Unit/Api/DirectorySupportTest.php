<?php

namespace Tests\Unit\Api;

use App\Mcp\Support\Attribution;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\SourceRegistry;
use App\Services\Api\StationDirectoryQuery;
use App\Support\QueryBoolean;
use App\Support\SourceRights;
use Tests\TestCase;

/**
 * Briques des annuaires et du registre des sources de l'API publique : booléens d'URL, forme
 * canonique des filtres, fiche publique d'un fournisseur sans les notes internes du registre.
 */
class DirectorySupportTest extends TestCase
{
    public function test_url_booleans_accept_true_and_false_and_leave_the_rest_to_validation(): void
    {
        $this->assertTrue(QueryBoolean::cast('true'));
        $this->assertFalse(QueryBoolean::cast('FALSE'));
        $this->assertSame('1', QueryBoolean::cast('1'));
        $this->assertSame('peut-etre', QueryBoolean::cast('peut-etre'));
        $this->assertNull(QueryBoolean::cast(null));
        $this->assertSame(['facets' => true, 'q' => 'true'], QueryBoolean::castKeys(['facets' => 'true', 'q' => 'true'], ['facets', 'absent']));
    }

    public function test_directory_filters_are_normalized(): void
    {
        $this->assertSame(['department' => '2A', 'heritage' => true], LineDirectoryQuery::normalize(['department' => ' 2a ', 'heritage' => 'true']));
        $this->assertSame(['department' => '971', 'flagged' => false], StationDirectoryQuery::normalize(['department' => '971', 'flagged' => 'false']));
    }

    public function test_public_provider_entry_leaves_out_internal_notes(): void
    {
        $rights = new SourceRights([
            'providers' => [
                'demo' => [
                    'nom' => 'Démo', 'statut' => 'incertain', 'licence' => 'Autorisation écrite', 'accord' => 'ecrit',
                    'preuve' => ['https://exemple.org/apropos'], 'citation' => 'Librement reproductible.',
                    'obligations' => 'Note interne', 'remarques' => 'Risque interne', 'domaines' => ['exemple.org'], 'lu_le' => '2026-10-20',
                ],
            ],
            'sources' => ['line_events' => ['demo' => 'demo', 'autre' => 'osm']],
            'composites' => ['line_events' => ['manual' => 'demo']],
        ]);
        $registry = new SourceRegistry($rights, new Attribution($rights));

        $entry = $registry->find('demo');
        $this->assertNotNull($entry);
        $this->assertSame('uncertain', $entry['license_status']);
        $this->assertFalse($entry['open_licence']);
        $this->assertSame(['line_events' => ['demo', 'manual']], $entry['source_values']);
        $this->assertSame(['https://exemple.org/apropos'], $entry['proof']);
        $encoded = (string) json_encode($entry);
        foreach (['Note interne', 'Risque interne', 'ecrit'] as $internal) {
            $this->assertStringNotContainsString($internal, $encoded);
        }
        $this->assertNull($registry->find('inconnu'));
        $this->assertSame(['demo'], $registry->keys());
    }
}
