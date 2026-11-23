<?php

namespace Tests\Feature;

use App\Models\Accident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Redirections 301 des fiches d'accident : renommage, alias, chaînes d'alias, cycles et fiche retenue non publiée. */
class AccidentRedirectChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_renamed_address_whose_new_sheet_is_missing_leads_nowhere(): void
    {
        $this->assertNull(Accident::redirectSlugFor('1910-09-20-breteuil'));
    }

    public function test_a_renamed_address_still_held_by_a_sheet_is_not_redirected_by_the_rename(): void
    {
        Accident::factory()->create(['slug' => '1910-09-20-la-faloise']);
        Accident::factory()->create(['slug' => '1910-09-20-breteuil']);

        $this->assertNull(Accident::redirectSlugFor('1910-09-20-breteuil'), 'la fiche existe encore sous cette adresse');
    }

    public function test_a_renamed_sheet_that_became_an_alias_follows_the_alias(): void
    {
        $kept = Accident::factory()->create(['slug' => '1910-09-20-kept']);
        $new = Accident::factory()->create(['slug' => '1910-09-20-la-faloise']);
        $new->forceFill(['alias_of_id' => $kept->id])->save();
        $this->assertSame('1910-09-20-kept', Accident::redirectSlugFor('1910-09-20-la-faloise'));
    }

    public function test_an_alias_redirects_to_the_kept_sheet_and_a_plain_sheet_does_not(): void
    {
        $kept = Accident::factory()->create(['slug' => '2000-01-01-kept']);
        Accident::factory()->create(['slug' => '2000-01-01-alias', 'alias_of_id' => $kept->id]);

        $this->assertSame('2000-01-01-kept', Accident::redirectSlugFor('2000-01-01-alias'));
        $this->assertNull(Accident::redirectSlugFor('2000-01-01-kept'));
        $this->assertNull(Accident::redirectSlugFor('2000-01-01-absent'));
        $this->assertNull(Accident::redirectSlugFor(''));
        $this->assertNull(Accident::redirectSlugFor(str_repeat('x', 5000)));
    }

    public function test_a_chain_of_aliases_is_followed_to_the_kept_sheet_up_to_five_steps(): void
    {
        $kept = Accident::factory()->create(['slug' => '2000-01-01-end']);
        $previous = $kept;
        foreach (range(1, 5) as $i) {
            $previous = Accident::factory()->create(['slug' => "2000-01-01-a{$i}", 'alias_of_id' => $previous->id]);
        }

        $this->assertSame('2000-01-01-end', Accident::redirectSlugFor('2000-01-01-a1'), 'un pas');
        $this->assertSame('2000-01-01-end', Accident::redirectSlugFor('2000-01-01-a3'));
        $this->assertSame('2000-01-01-end', Accident::redirectSlugFor('2000-01-01-a4'), 'quatre pas');
        $this->assertSame('2000-01-01-end', Accident::redirectSlugFor('2000-01-01-a5'), 'cinq alias en chaîne : cinq pas');
        $sixth = Accident::factory()->create(['slug' => '2000-01-01-a6', 'alias_of_id' => $previous->id]);
        $this->assertNull(Accident::redirectSlugFor($sixth->slug), 'six alias en chaîne : au-delà de cinq pas, rien');
    }

    public function test_a_cycle_of_aliases_ends_without_a_loop(): void
    {
        $a = Accident::factory()->create(['slug' => '2000-01-01-a']);
        $b = Accident::factory()->create(['slug' => '2000-01-01-b', 'alias_of_id' => $a->id]);
        $a->forceFill(['alias_of_id' => $b->id])->save();

        $this->assertNull(Accident::redirectSlugFor('2000-01-01-a'));
        $this->assertNull(Accident::redirectSlugFor('2000-01-01-b'));
    }
}
