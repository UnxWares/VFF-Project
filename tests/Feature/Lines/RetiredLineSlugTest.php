<?php

namespace Tests\Feature\Lines;

use App\Mcp\Support\Lookup;
use App\Models\Line;
use App\Services\Lines\LineSlugRedirects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Ancien slug d'une ligne renommée (database/data/line_slug_redirects.php) : 301 vers la nouvelle adresse sur le site
 * et l'API REST (sous-chemin et paramètres gardés), ancien slug accepté par GraphQL et le serveur MCP, jamais 404.
 * Correspondances d'essai : aucun slug ici n'est dans le vrai fichier.
 */
class RetiredLineSlugTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(LineSlugRedirects::class, new LineSlugRedirects([
            'archeo-ligne-de-essai' => 'ligne-de-essai',
            'archeo-encore-portee' => 'encore-portee',
            'archeo-sans-cible' => 'sans-cible',
        ]));
        Line::factory()->create(['slug' => 'ligne-de-essai', 'name' => 'Ligne d\'Essai à Test', 'code' => null]);
    }

    public function test_the_old_address_of_a_line_sheet_redirects_permanently(): void
    {
        $this->get('/lines/archeo-ligne-de-essai')->assertStatus(301)->assertRedirect('/lines/ligne-de-essai');
        $this->get('/lines/archeo-ligne-de-essai/stories/12?lang=en')->assertStatus(301)
            ->assertRedirect('/lines/ligne-de-essai/stories/12?lang=en');
        $this->get('/lines/archeo-ligne-de-essai/portions/0123456789ab.json')->assertStatus(301)
            ->assertRedirect('/lines/ligne-de-essai/portions/0123456789ab.json');
    }

    public function test_the_old_address_in_the_rest_api_redirects_with_its_sub_path_and_query(): void
    {
        $this->getJson('/api/v1/lines/archeo-ligne-de-essai')->assertStatus(301)->assertRedirect('/api/v1/lines/ligne-de-essai');
        $this->getJson('/api/v1/lines/archeo-ligne-de-essai/history?lang=en')->assertStatus(301)
            ->assertRedirect('/api/v1/lines/ligne-de-essai/history?lang=en');
    }

    public function test_an_old_slug_in_the_line_filter_redirects(): void
    {
        $this->get('/accidents?line=archeo-ligne-de-essai&page=2')->assertStatus(301)
            ->assertRedirect('/accidents?line=ligne-de-essai&page=2');
        $this->getJson('/api/v1/accidents?line=archeo-ligne-de-essai')->assertStatus(301)
            ->assertRedirect('/api/v1/accidents?line=ligne-de-essai');
    }

    public function test_unknown_live_or_targetless_slugs_are_not_redirected(): void
    {
        Line::factory()->create(['slug' => 'archeo-encore-portee', 'code' => null]);
        Line::factory()->create(['slug' => 'encore-portee', 'code' => null]);

        $this->assertNull(Line::redirectSlugFor('archeo-encore-portee'));
        $this->assertNull(Line::redirectSlugFor('archeo-sans-cible'));
        $this->assertNull(Line::redirectSlugFor('ligne-de-essai'));
        $this->get('/lines/archeo-sans-cible')->assertNotFound();
        $this->getJson('/api/v1/lines/nulle-part')->assertNotFound();
    }

    public function test_graphql_and_mcp_accept_the_old_slug_and_give_the_new_one(): void
    {
        $this->graphQL('{ line(slug: "archeo-ligne-de-essai") { slug name } }')
            ->assertJsonPath('data.line.slug', 'ligne-de-essai');
        $this->graphQL('{ line(slug: "ligne-de-essai") { slug } }')->assertJsonPath('data.line.slug', 'ligne-de-essai');
        $this->graphQL('{ line(slug: "nulle-part") { slug } }')->assertJsonPath('data.line', null);

        $this->assertSame('ligne-de-essai', Lookup::line('archeo-ligne-de-essai')?->slug);
        $this->assertNull(Lookup::line('archeo-sans-cible'));
    }

    public function test_the_command_renames_once_and_keeps_its_correspondences(): void
    {
        $map = sys_get_temp_dir().'/vff-slugs-'.bin2hex(random_bytes(4)).'.php';
        $mother = Line::factory()->create(['slug' => 'archeo-ligne-mere-essai', 'code' => null, 'alt_names' => [
            'merged' => [['name' => 'Section', 'slug' => 'archeo-section-fusionnee-essai', 'reason' => 'essai']],
        ]]);
        Line::factory()->create(['slug' => 'ligne-mere-essai', 'code' => null]);

        $this->artisan('vff:neutralize-line-slugs', ['--dry-run' => true, '--map' => $map])
            ->expectsOutputToContain('1 lignes à renommer, 1 anciens slugs de lignes fusionnées à rediriger, 1 collision(s).')
            ->assertSuccessful();
        $this->assertFileDoesNotExist($map);
        $this->assertSame('archeo-ligne-mere-essai', $mother->fresh()?->slug);

        $this->artisan('vff:neutralize-line-slugs', ['--map' => $map])->assertSuccessful();
        $this->assertSame('ligne-mere-essai-2', $mother->fresh()->slug);
        $this->assertSame([
            'archeo-ligne-mere-essai' => 'ligne-mere-essai-2',
            'archeo-section-fusionnee-essai' => 'ligne-mere-essai-2',
        ], LineSlugRedirects::read($map));
        $this->assertSame(0, DB::table('lines')->where('slug', 'like', 'archeo-%')->count());

        $written = (string) file_get_contents($map);
        $this->artisan('vff:neutralize-line-slugs', ['--map' => $map])
            ->expectsOutputToContain('0 lignes à renommer, 0 anciens slugs de lignes fusionnées à rediriger, 0 collision(s).')
            ->assertSuccessful();
        $this->assertSame($written, file_get_contents($map));
        unlink($map);
    }
}
