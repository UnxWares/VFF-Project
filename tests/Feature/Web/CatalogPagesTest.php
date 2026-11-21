<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\Media;
use App\Support\Advocacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\ParsesInertiaHtml;
use Tests\TestCase;

/**
 * Matériels, crédits médias, accueil (encart de mobilisation) et pages
 * statiques : props exactes, tris, limites, 404, rendu HTML sûr.
 */
class CatalogPagesTest extends TestCase
{
    use BuildsRailNetwork;
    use ParsesInertiaHtml;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_material_page_lists_its_lines_by_name(): void
    {
        $lines = collect(range(1, 22))->map(fn ($i) => Line::factory()->create(['name' => sprintf('Ligne témoin %02d', $i)]));
        $this->material->lines()->attach($lines->pluck('id'));
        $expected = Line::whereIn('id', [...$lines->pluck('id'), $this->line->id])->orderBy('name')->pluck('slug')->all();

        $this->get('/materials/'.$this->material->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Materials/Show')
                ->where('material.slug', 'z-6400-test')
                ->where('material.name', 'Z 6400')
                ->where('material.type', $this->material->type->value)
                ->has('lines', 23)
                ->where('lines_total', 23)
                ->where('lines', fn ($l) => $l->pluck('slug')->all() === $expected));
    }

    public function test_unknown_material_is_404(): void
    {
        $this->get('/materials/inconnu')->assertNotFound();
    }

    public function test_media_credits_count_by_type_license_and_author(): void
    {
        $media = fn (array $a) => Media::factory()->create(['mediable_type' => Line::class, 'mediable_id' => $this->line->id, ...$a]);
        $media(['type' => 'photo', 'license' => 'CC-BY-SA-4.0', 'author' => 'Alice']);
        $media(['type' => 'photo', 'license' => 'CC-BY-SA-4.0', 'author' => 'Alice']);
        $media(['type' => 'plan', 'license' => 'CC0', 'author' => 'Bruno']);
        $media(['type' => 'document', 'license' => 'CC0', 'author' => null]);
        $media(['type' => 'postcard', 'license' => 'CC-BY-SA-4.0', 'author' => 'Alice']);

        $this->get('/credits/media')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Credits/Media')
                ->where('stats', ['total' => 5, 'photo_count' => 2, 'plan_count' => 1, 'document_count' => 1])
                ->where('byLicense', [['license' => 'CC-BY-SA-4.0', 'count' => 3], ['license' => 'CC0', 'count' => 2]])
                // Auteurs anonymes exclus, tri par nombre décroissant.
                ->where('byAuthor', [['author' => 'Alice', 'count' => 3], ['author' => 'Bruno', 'count' => 1]]));
    }

    public function test_media_credits_limit_authors_to_thirty(): void
    {
        foreach (range(1, 31) as $i) {
            Media::factory()->create(['mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'author' => "Auteur {$i}"]);
        }

        $this->get('/credits/media')->assertInertia(fn (Assert $page) => $page->has('byAuthor', 30)->where('stats.total', 31));
    }

    public function test_home_lists_supported_lines_threatened_first(): void
    {
        $reopening = Line::factory()->create(['slug' => 'angouleme-limoges', 'name' => 'Angoulême - Limoges', 'is_threatened' => false,
            'advocacy' => ['title' => 'Réouverture', 'cta_url' => 'https://angoulim.example']]);
        $threatened = Line::factory()->create(['slug' => 'ligne-des-hirondelles', 'name' => 'Ligne des Hirondelles', 'is_threatened' => true,
            'advocacy' => ['title' => 'Sauvons les Hirondelles']]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('threatened', 2)
            // Plaidoyer dans la langue de la page (App\Support\Advocacy) : requête de test en anglais, titre
            // propre non traduit gardé en français et signalé ; champs absents laissés absents.
            ->where('threatened.0', ['slug' => $threatened->slug, 'name' => 'Ligne des Hirondelles', 'advocacy' => ['kind' => null,
                'title' => 'Sauvons les Hirondelles', 'message' => null, 'cta_label' => null, 'cta_url' => null,
                'lang' => ['title' => 'fr', 'message' => null, 'cta_label' => null]],
                // Sans place précisée : encart complet, sans rang.
                'home' => ['rank' => null, 'display' => 'card', 'summary' => null, 'lang' => null]])
            ->where('threatened.1.slug', $reopening->slug)
            ->where('stats.lines_count', 3)
            ->where('stats.stations_count', 2));
    }

    public function test_home_order_and_form_come_from_the_advocacy_data(): void
    {
        // Rang explicite d'abord (même pour une ligne non menacée), puis les lignes sans rang ; une mention
        // garde son texte court traduit, une mention sans texte reste un encart complet.
        $mention = Line::factory()->create(['code' => '100001', 'slug' => 'mention', 'is_threatened' => true, 'advocacy' => ['title' => 'Menacée',
            'home' => ['rank' => 3, 'display' => 'mention', 'summary' => 'Phrase courte.', 'translations' => ['en' => 'Short sentence.']]]]);
        $reopening = Line::factory()->create(['code' => '900000', 'slug' => 'reouverture', 'is_threatened' => false, 'advocacy' => ['title' => 'Rouvrons',
            'home' => ['rank' => 2, 'display' => 'card']]]);
        $first = Line::factory()->create(['code' => '999999', 'slug' => 'premiere', 'is_threatened' => true, 'advocacy' => ['title' => 'Première',
            'home' => ['rank' => 1]]]);
        $unranked = Line::factory()->create(['code' => '100000', 'slug' => 'sans-rang', 'is_threatened' => true, 'advocacy' => ['title' => 'Sans rang',
            'home' => ['display' => 'mention']]]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('threatened.0.slug', $first->slug)
            ->where('threatened.1.slug', $reopening->slug)
            ->where('threatened.2.slug', $mention->slug)
            ->where('threatened.2.home', ['rank' => 3, 'display' => 'mention', 'summary' => 'Short sentence.', 'lang' => 'en'])
            ->where('threatened.3.slug', $unranked->slug)
            ->where('threatened.3.home.display', 'card'));

        $this->assertSame(['rank' => 3, 'display' => 'mention', 'summary' => 'Phrase courte.', 'lang' => 'fr'],
            Advocacy::home($mention->fresh()->advocacy, 'de'), 'repli français signalé');
    }

    public function test_home_stats_are_recomputed_when_data_changes(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('stats.lines_count', 1));

        Line::factory()->create();
        DB::table('line_segments')->update(['updated_at' => now()->addMinute()]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('stats.lines_count', 2));
    }

    public function test_advocacy_and_line_names_are_rendered_safely(): void
    {
        foreach (self::htmlInjectionPayloads() as $label => $markup) {
            Line::factory()->create(['name' => 'Ligne '.$markup, 'is_threatened' => true, 'advocacy' => ['title' => $markup, 'message' => $markup]]);
        }

        $page = $this->parseInertiaHtml($this->get('/')->assertOk());
        $titles = array_column(array_column($page['props']['threatened'], 'advocacy'), 'title');
        sort($titles);
        $expected = array_values(self::htmlInjectionPayloads());
        sort($expected);
        $this->assertSame($expected, $titles);

        $this->parseInertiaHtml($this->get('/lines')->assertOk());
    }

    public function test_line_and_station_pages_render_hostile_names_safely(): void
    {
        $this->line->update(['name' => '<!--<script> Ligne', 'description' => '</script><img src=x onerror=alert(1)>']);
        $this->origin->update(['name' => '<!--<script>', 'city' => '<svg/onload=alert(1)>']);

        $page = $this->parseInertiaHtml($this->get('/lines/'.$this->line->slug)->assertOk());
        $this->assertSame('<!--<script> Ligne', $page['props']['line']['name']);
        $this->assertSame('</script><img src=x onerror=alert(1)>', $page['props']['line']['description']);

        $page = $this->parseInertiaHtml($this->get('/stations/paris-saint-lazare')->assertOk());
        $this->assertSame('<!--<script>', $page['props']['station']['name']);

        $this->parseInertiaHtml($this->get('/stations')->assertOk());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function staticPages(): array
    {
        return [
            'qui sommes-nous' => ['/whoarewe', 'WhoAreWe'],
            'contribuer' => ['/contribute', 'Contribute'],
            'dons' => ['/donate', 'Donate'],
            'mentions légales' => ['/legal', 'Legal'],
            'confidentialité' => ['/privacy', 'Privacy'],
            'CGU' => ['/use-conditions', 'UseConditions'],
            'doc API' => ['/api', 'ApiDocs'],
            'crédits tech' => ['/credits/tech', 'Credits/Tech'],
        ];
    }

    #[DataProvider('staticPages')]
    public function test_static_pages_answer_head_and_inertia_partial_requests(string $uri, string $component): void
    {
        $this->call('HEAD', $uri)->assertOk();
        $version = $this->get($uri)->viewData('page')['version'];

        // Navigation Inertia (XHR) : JSON de page, pas de HTML.
        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, 'X-Requested-With' => 'XMLHttpRequest'])
            ->get($uri)
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', $component)
            ->assertJsonPath('url', $uri);

        $this->post($uri)->assertStatus(405);
    }

    public function test_stale_asset_version_forces_a_full_reload(): void
    {
        if ($this->get('/donate')->viewData('page')['version'] === null) {
            $this->markTestSkipped('Pas de manifeste Vite : version Inertia nulle.');
        }

        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'ancienne-version'])
            ->get('/donate')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/donate'));
    }

    public function test_unknown_page_is_404(): void
    {
        $this->get('/page-qui-n-existe-pas')->assertNotFound();
        $this->get('/lines/'.str_repeat('a', 300))->assertNotFound();
    }

    public function test_health_check(): void
    {
        $this->get('/up')->assertOk();
    }
}
