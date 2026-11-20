<?php

namespace Tests\Feature\Web;

use App\Models\Contribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\ParsesInertiaHtml;
use Tests\TestCase;

/**
 * Éditeur de tracé : contrôle d'accès des routes JSON, contraintes de route,
 * validation (messages traduits de lang/fr/editor.php), limite de débit de
 * /reference (240/min) et bornes de la proposition. Le scénario complet de
 * publication est dans tests/Feature/TraceContributionTest.php.
 */
class TraceEditorRoutesTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use ParsesInertiaHtml;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'kind' => 'new_line',
            'new_line_name' => 'Ligne de Mimizan',
            'network' => 'secondaire',
            'trace_source' => 'IGN SCAN 50 historique 1950',
            'portions' => [[
                'key' => 'p1',
                'coordinates' => [[-1.20, 44.20], [-1.25, 44.21]],
                'events' => [['type' => 'opening', 'precision' => 'year', 'date' => '1889', 'source' => 'Chaix 1890']],
            ]],
        ], $overrides);
    }

    /** @return array<string, array{0: string}> */
    public static function jsonRoutes(): array
    {
        return [
            'accrochage' => ['/contribute/trace/reference?bbox=2.1,48.78,2.35,48.9&zoom=12'],
            'ligne' => ['/contribute/trace/lines/paris-saint-lazare-versailles-rd'],
            'élément' => ['/contribute/trace/feature/segment/1'],
        ];
    }

    #[DataProvider('jsonRoutes')]
    public function test_json_routes_require_authentication(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_editor_without_line_and_with_unknown_line(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/contribute/trace')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/Trace')
                ->where('line', null)
                ->where('currentEra', 2030)
                ->where('networks', ['principal', 'secondaire', 'militaire', 'particulier'])
                ->where('precisions', ['day', 'month', 'year'])
                ->where('limits', ['portions' => 60, 'points' => 8000])
                ->where('eventTypes', fn ($types) => $types->contains('opening') && $types->contains('dismantling')
                    && ! $types->contains('bogus') && $types->count() === 13));

        $this->actingAs($user)->get('/contribute/trace?line='.urlencode("x' OR '1'='1"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('line', null));
    }

    public function test_feature_kind_and_id_are_constrained_by_the_route(): void
    {
        $user = $this->member();

        $this->actingAs($user)->getJson('/contribute/trace/feature/station/1')->assertNotFound();
        $this->actingAs($user)->getJson('/contribute/trace/feature/segment/abc')->assertNotFound();
        $this->actingAs($user)->getJson('/contribute/trace/feature/segment/999999')->assertNotFound();
        $this->actingAs($user)->getJson('/contribute/trace/feature/trace/999999')->assertNotFound();
        $this->actingAs($user)->getJson('/contribute/trace/feature/line/999999')->assertNotFound();
    }

    public function test_feature_line_returns_the_current_geometry_of_the_line(): void
    {
        $this->actingAs($this->member())
            ->getJson('/contribute/trace/feature/line/'.$this->line->id)
            ->assertOk()
            ->assertJsonPath('line.slug', $this->line->slug)
            ->assertJsonCount(1, 'parts')
            ->assertJsonCount(3, 'parts.0');
    }

    public function test_line_geometry_of_an_unknown_slug_is_404(): void
    {
        $this->actingAs($this->member())->getJson('/contribute/trace/lines/inconnue')->assertNotFound();
    }

    /** @return array<string, array{0: string, 1: array<int, string>}> */
    public static function invalidReferenceQueries(): array
    {
        return [
            'sans paramètre' => ['', ['bbox', 'zoom']],
            'bbox à 3 valeurs' => ['bbox=1,2,3&zoom=12', ['bbox']],
            'bbox injectée' => ['bbox='.urlencode("1,2,3,4'); DROP TABLE lines;--").'&zoom=12', ['bbox']],
            'zoom texte' => ['bbox=2.1,48.78,2.35,48.9&zoom=abc', ['zoom']],
            'zoom hors bornes' => ['bbox=2.1,48.78,2.35,48.9&zoom=25', ['zoom']],
            'zoom négatif' => ['bbox=2.1,48.78,2.35,48.9&zoom=-1', ['zoom']],
            'sources hors alphabet' => ['bbox=2.1,48.78,2.35,48.9&zoom=12&sources='.urlencode("osm' OR 1=1"), ['sources']],
            'segments non booléen' => ['bbox=2.1,48.78,2.35,48.9&zoom=12&segments=peut-etre', ['segments']],
        ];
    }

    #[DataProvider('invalidReferenceQueries')]
    public function test_reference_validation(string $query, array $errors): void
    {
        $this->actingAs($this->member())
            ->getJson('/contribute/trace/reference?'.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors);
    }

    /** @return array<string, array{0: string}> */
    public static function tooLargeBoxes(): array
    {
        return [
            'zoom trop faible' => ['bbox=2.1,48.78,2.35,48.9&zoom=9'],
            'bbox inversée' => ['bbox=2.35,48.9,2.1,48.78&zoom=12'],
            'bbox trop large' => ['bbox=0,45,4,46&zoom=12'],
            'bbox trop haute' => ['bbox=2,44,3,47&zoom=12'],
        ];
    }

    #[DataProvider('tooLargeBoxes')]
    public function test_reference_refuses_whole_country_queries(string $query): void
    {
        $this->actingAs($this->member())
            ->getJson('/contribute/trace/reference?'.$query)
            ->assertOk()
            ->assertExactJson(['type' => 'FeatureCollection', 'features' => [], 'too_large' => true]);
    }

    public function test_reference_is_rate_limited_to_240_requests_per_minute(): void
    {
        $user = $this->member();
        $uri = '/contribute/trace/reference?bbox=0,45,4,46&zoom=6'; // réponse immédiate (too_large)

        for ($i = 1; $i <= 240; $i++) {
            $response = $this->actingAs($user)->getJson($uri);
        }
        $response->assertOk()->assertHeader('X-RateLimit-Limit', '240')->assertHeader('X-RateLimit-Remaining', '0');

        $this->actingAs($user)->getJson($uri)->assertStatus(429)->assertHeader('Retry-After');

        // Limite par compte : un autre membre n'est pas bloqué.
        $this->actingAs($this->member())->getJson($uri)->assertOk();
    }

    public function test_store_validation_messages_are_translated(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/contribute/trace', $this->payload(['new_line_name' => 'ab', 'trace_source' => '']))
            ->assertSessionHasErrors([
                'new_line_name' => 'Indiquez le nom de la ligne (3 caractères minimum).',
                'trace_source' => 'La source est obligatoire.',
            ]);

        $this->actingAs($user)->post('/contribute/trace', ['kind' => 'correction', 'line_slug' => 'inconnue', 'network' => 'principal', 'trace_source' => 'IGN', 'portions' => []])
            ->assertSessionHasErrors([
                'line_slug' => 'Choisissez la ligne à corriger.',
                'portions' => 'Dessinez ou reprenez au moins une portion.',
            ]);

        $this->actingAs($user)->post('/contribute/trace', $this->payload(['portions' => [['events' => [['source_url' => 'javascript:alert(1)']]]]]))
            ->assertSessionHasErrors(['portions.0.events.0.source_url' => 'Lien invalide (adresse http:// ou https:// attendue).']);

        $this->assertSame(0, Contribution::count());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidStores(): array
    {
        return [
            'type de proposition inconnu' => [['kind' => 'delete_line'], 'kind'],
            'réseau inconnu' => [['network' => 'tramway'], 'network'],
            'longitude hors bornes' => [['portions' => [['coordinates' => [[-181, 44.2], [-1.25, 44.21]]]]], 'portions.0.coordinates.0.0'],
            'latitude hors bornes' => [['portions' => [['coordinates' => [[-1.2, 91], [-1.25, 44.21]]]]], 'portions.0.coordinates.0.1'],
            'point à 3 coordonnées' => [['portions' => [['coordinates' => [[-1.2, 44.2, 10], [-1.25, 44.21]]]]], 'portions.0.coordinates.0'],
            'type d\'événement inconnu' => [['portions' => [['events' => [['type' => 'teleportation']]]]], 'portions.0.events.0.type'],
            'précision inconnue' => [['portions' => [['events' => [['precision' => 'century']]]]], 'portions.0.events.0.precision'],
            'date avant 1750' => [['portions' => [['events' => [['date' => '1700']]]]], 'portions.0.events.0.date'],
            'date impossible' => [['portions' => [['events' => [['precision' => 'day', 'date' => '1889-02-30']]]]], 'portions.0.events.0.date'],
            'clé de portion trop longue' => [['portions' => [['key' => str_repeat('k', 25)]]], 'portions.0.key'],
        ];
    }

    #[DataProvider('invalidStores')]
    public function test_store_validation(array $overrides, string $error): void
    {
        $this->actingAs($this->member())
            ->post('/contribute/trace', $this->payload($overrides))
            ->assertSessionHasErrors($error);

        $this->assertSame(0, Contribution::count());
    }

    public function test_store_refuses_a_single_point_portion(): void
    {
        $payload = $this->payload();
        $payload['portions'][0]['coordinates'] = [[-1.2, 44.2]];

        $this->actingAs($this->member())
            ->post('/contribute/trace', $payload)
            ->assertSessionHasErrors(['portions.0.coordinates' => 'Portion trop courte ou géométrie invalide (au moins 2 sommets, 10 m).']);
        $this->assertSame(0, Contribution::count());
    }

    public function test_store_refuses_more_than_sixty_portions(): void
    {
        $portion = ['key' => 'p', 'coordinates' => [[-1.20, 44.20], [-1.25, 44.21]]];

        $this->actingAs($this->member())
            ->post('/contribute/trace', $this->payload(['portions' => array_fill(0, 61, $portion)]))
            ->assertSessionHasErrors('portions');
        $this->assertSame(0, Contribution::count());
    }

    public function test_degenerate_portion_is_refused_after_cleaning(): void
    {
        // Deux sommets identiques à 1e-7 près : une fois dédoublonnés, il n'en reste qu'un.
        $this->actingAs($this->member())
            ->post('/contribute/trace', $this->payload(['portions' => [['coordinates' => [[-1.2, 44.2], [-1.20000001, 44.20000001]]]]]))
            ->assertSessionHasErrors(['portions.0.coordinates' => 'Portion trop courte ou géométrie invalide (au moins 2 sommets, 10 m).']);
        $this->assertSame(0, Contribution::count());
    }

    public function test_store_cannot_forge_status_owner_or_target(): void
    {
        $author = $this->member('contributor');
        $victim = $this->member('validator');

        $this->actingAs($author)->post('/contribute/trace', [
            ...$this->payload(),
            'status' => 'applied',
            'user_id' => $victim->id,
            'type' => 'line_segment',
            'target_id' => $this->line->id,
        ])->assertSessionHasNoErrors();

        $c = Contribution::sole();
        $this->assertSame($author->id, $c->user_id);
        $this->assertSame('pending', $c->status->value);
        $this->assertSame('line_trace', $c->type);
        $this->assertNull($c->target_id);
        $this->assertSame(0, DB::table('source_traces')->count());
    }

    public function test_markup_in_a_proposal_cannot_break_or_script_the_moderation_page(): void
    {
        $author = $this->member('contributor');
        $payloads = array_values(self::htmlInjectionPayloads());

        foreach ($payloads as $i => $markup) {
            $this->actingAs($author)->post('/contribute/trace', $this->payload([
                'new_line_name' => 'Ligne '.$markup,
                'notes' => $markup,
                'portions' => [['key' => 'p'.$i, 'label' => $markup, 'from' => $markup]],
            ]))->assertSessionHasNoErrors();
        }

        // Stocké tel quel : l'échappement se fait à l'affichage, pas à l'enregistrement.
        $this->assertSame($payloads, Contribution::orderBy('id')->get()->map(fn ($c) => $c->payload['notes'])->all());

        // Avant correctif (resources/views/app.blade.php) : « <!--<script> » faisait
        // basculer l'analyseur HTML5 du navigateur, la balise de données avalait la
        // suite du document → JSON illisible, plus de #app : modération inutilisable.
        foreach (['/validate' => $this->member('validator'), '/contribute/mine' => $author] as $uri => $viewer) {
            $page = $this->parseInertiaHtml($this->actingAs($viewer)->get($uri)->assertOk());
            $notes = array_map(fn ($c) => $c['payload']['notes'], $page['props']['contributions']);
            sort($notes);
            $expected = $payloads;
            sort($expected);
            $this->assertSame($expected, $notes, $uri);
        }
    }
}
