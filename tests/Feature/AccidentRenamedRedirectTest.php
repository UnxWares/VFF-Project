<?php

namespace Tests\Feature;

use App\Models\Accident;
use App\Services\Accidents\AccidentSlugTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une fiche renommée par database/data/accident_slugs.php garde son ancienne adresse : 301 vers la nouvelle (site et
 * API REST), jamais 404.
 */
class AccidentRenamedRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_slug_table_gives_the_new_address_of_a_renamed_sheet(): void
    {
        $table = new AccidentSlugTable([
            ['key' => 'k1', 'from' => 'ancien', 'slug' => 'nouveau', 'reason' => 'essai'],
            ['key' => 'k2', 'from' => 'stable', 'slug' => 'stable', 'reason' => 'essai'],
        ]);

        $this->assertSame('nouveau', $table->renamedTo('ancien'));
        $this->assertNull($table->renamedTo('stable'));
        $this->assertNull($table->renamedTo('inconnu'));
    }

    public function test_the_old_address_of_a_renamed_sheet_redirects_to_the_new_one(): void
    {
        // Ligne réelle de la table : Breteuil 1910 renommé La Faloise le 23/11/2026.
        Accident::factory()->create(['slug' => '1910-09-20-la-faloise']);

        $this->assertSame('1910-09-20-la-faloise', Accident::redirectSlugFor('1910-09-20-breteuil'));
        $this->get('/accidents/1910-09-20-breteuil')->assertStatus(301)->assertRedirect('/accidents/1910-09-20-la-faloise');
        $this->get('/api/v1/accidents/1910-09-20-breteuil')->assertStatus(301);
    }

    public function test_an_unknown_or_live_address_is_not_redirected(): void
    {
        Accident::factory()->create(['slug' => '1910-09-20-la-faloise']);

        $this->assertNull(Accident::redirectSlugFor('1999-01-01-nulle-part'));
        $this->assertNull(Accident::redirectSlugFor('1910-09-20-la-faloise'));
    }
}
