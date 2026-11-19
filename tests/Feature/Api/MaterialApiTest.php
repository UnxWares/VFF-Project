<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * API REST du matériel roulant : liste (filtres, tris, pagination, erreurs), fiche (provenance
 * par champ, lignes avec preuve) et matériel d'une ligne ; documentation OpenAPI complète.
 */
class MaterialApiTest extends TestCase
{
    use RefreshDatabase;

    /** Points d'accès du matériel dans la spécification OpenAPI (préfixe /api/v1 retiré). */
    private const PATHS = ['/materials', '/materials/{material}', '/lines/{line}/materials'];

    private Material $bb15000;

    private Material $x73500;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bb15000 = Material::factory()->fromWikidata()->create([
            'slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'builder' => 'Alstom',
            'in_service_from' => 1971, 'in_service_to' => null, 'max_speed_kmh' => 160, 'units_built' => 65,
        ]);
        $this->x73500 = Material::factory()->create([
            'slug' => 'x-73500', 'name' => 'X 73500', 'type' => 'autorail', 'builder' => 'Alstom',
            'in_service_from' => 1999, 'in_service_to' => null, 'max_speed_kmh' => 140, 'units_built' => 331, 'gauge_mm' => 1435,
            'operators' => [['name' => 'SNCF', 'wikidata_id' => 'Q13646', 'from_year' => null, 'to_year' => null, 'units' => null]],
        ]);
        Material::factory()->create([
            'slug' => 'x-3800', 'name' => 'X 3800', 'type' => 'autorail', 'builder' => 'Renault, De Dietrich',
            'designations' => ['Autorail Picasso'], 'in_service_from' => 1950, 'in_service_to' => 1988,
            'max_speed_kmh' => null, 'units_built' => 251, 'gauge_mm' => 1000,
            'operators' => [['name' => 'Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée', 'wikidata_id' => 'Q1121154', 'from_year' => null, 'to_year' => null, 'units' => null]],
        ]);
        $this->line = Line::factory()->create(['slug' => 'rfn-67000', 'code' => '067000', 'name' => 'Ligne de Lunéville à St-Dié']);
        LineMaterial::create([
            'line_id' => $this->line->id, 'material_id' => $this->bb15000->id, 'source' => 'wikipedia', 'confidence' => 'high',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070',
            'evidence' => [['url' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070', 'kind' => 'wikipedia_line_article', 'quote' => 'Rames Corail tractées par des BB 15000.', 'revid' => 239088070, 'article' => 'Ligne de Lunéville à Saint-Dié', 'section' => 'Trafic > Matériel roulant']],
        ]);
        LineMaterial::create(['line_id' => $this->line->id, 'material_id' => $this->x73500->id, 'source' => 'wikipedia', 'confidence' => 'medium', 'evidence' => []]);
    }

    public function test_the_service_state_and_the_scope_are_given_only_with_their_source(): void
    {
        Material::factory()->fromWikidata()->inService(12, '2026-07-13')->attested()->create(['slug' => 'db-407', 'name' => 'DB 407', 'type' => 'rame_tgv']);

        $this->getJson('/api/v1/materials/db-407')
            ->assertOk()
            ->assertJsonPath('data.service', [
                'status' => 'in_service', 'units_in_service' => 12, 'as_of' => '2026-07-13', 'checked_at' => '2026-11-19',
                'source' => 'wikipedia', 'url' => 'https://fr.wikipedia.org/w/index.php?title=BB_15000&oldid=237800094',
            ])
            ->assertJsonPath('data.scope', 'attested');

        // Sans source : tout est nul, jamais « en service » parce qu'il n'y a pas de date de retrait.
        $this->getJson('/api/v1/materials/bb-15000')
            ->assertJsonPath('data.service', ['status' => null, 'units_in_service' => null, 'as_of' => null, 'checked_at' => null, 'source' => null, 'url' => null])
            ->assertJsonPath('data.in_service_to', null)
            ->assertJsonPath('data.scope', 'france');
    }

    public function test_materials_filter_by_service_state_scope_and_bimode_category(): void
    {
        Material::factory()->fromWikidata()->inService()->attested()->create(['slug' => 'db-407', 'name' => 'DB 407', 'type' => 'rame_tgv']);
        Material::factory()->create(['slug' => 'regiolis', 'name' => 'Régiolis', 'type' => 'bimode']);

        $this->getJson('/api/v1/materials?service_status=in_service')->assertOk()->assertJsonPath('data.*.slug', ['db-407']);
        $this->getJson('/api/v1/materials?scope=attested')->assertOk()->assertJsonPath('data.*.slug', ['db-407']);
        $this->getJson('/api/v1/materials?scope=france')->assertOk()->assertJsonPath('data.*.slug', ['bb-15000', 'regiolis', 'x-3800', 'x-73500']);
        $this->getJson('/api/v1/materials?type=bimode')->assertOk()
            ->assertJsonPath('data.*.slug', ['regiolis'])
            ->assertJsonPath('data.0.type_label', 'Rame ou engin bimode');
        $this->getJson('/api/v1/materials?service_status=en_service')->assertUnprocessable()->assertJsonValidationErrors('service_status');
        $this->getJson('/api/v1/materials?scope=etranger')->assertUnprocessable()->assertJsonValidationErrors('scope');
    }

    public function test_the_list_gives_full_sheets_sorted_by_name_with_pagination(): void
    {
        $this->getJson('/api/v1/materials')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=600, public')
            ->assertJsonPath('data.*.slug', ['bb-15000', 'x-3800', 'x-73500'])
            ->assertJsonPath('data.0.type_label', 'Locomotive électrique')
            ->assertJsonPath('data.0.builders.0', ['name' => 'Alstom', 'wikidata_id' => 'Q309084', 'units' => null])
            ->assertJsonPath('data.0.image.license', 'CC BY-SA 4.0')
            ->assertJsonPath('data.0.provenance.license', 'CC0 1.0')
            ->assertJsonPath('data.0.lines_count', 1)
            ->assertJsonPath('data.0.links.api', route('api.materials.show', 'bb-15000'))
            ->assertJsonPath('data.1.designations', ['Autorail Picasso'])
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.next_cursor', null)
            ->assertJsonPath('links.next', null)
            ->assertJsonMissingPath('meta.total')
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next'], 'meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor']]);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function filters(): array
    {
        return [
            'recherche sans accents dans les autres noms' => ['q=PICASSO', ['x-3800']],
            'recherche dans les constructeurs' => ['q=dietrich', ['x-3800']],
            'catégorie' => ['type=autorail', ['x-3800', 'x-73500']],
            'constructeur' => ['builder=alstom', ['bb-15000', 'x-73500']],
            'exploitant par identifiant' => ['operator=Q13646', ['bb-15000', 'x-73500']],
            'exploitant par nom' => ['operator=paris a lyon', ['x-3800']],
            'ligne parcourue' => ['line=rfn-67000', ['bb-15000', 'x-73500']],
            'en service en 1960' => ['in_service_at=1960', ['x-3800']],
            'en service en 1990' => ['in_service_at=1990', ['bb-15000']],
            'période de mise en service' => ['from_year=1960&to_year=1999', ['bb-15000', 'x-73500']],
            'écartement' => ['gauge_mm=1000', ['x-3800']],
            'avec image' => ['has_image=1', ['bb-15000']],
            'sans ligne' => ['has_lines=0', ['x-3800']],
            'avec image, booléen en toutes lettres' => ['has_image=true', ['bb-15000']],
            'sans ligne, booléen en toutes lettres' => ['has_lines=false', ['x-3800']],
            'mises en service jusqu\'en 1959 (fin seule)' => ['to_year=1959', ['x-3800']],
            'plus rapides d\'abord, inconnues en dernier' => ['sort=-max_speed_kmh', ['bb-15000', 'x-73500', 'x-3800']],
            'plus construites d\'abord' => ['sort=-units_built', ['x-73500', 'x-3800', 'bb-15000']],
            'plus de lignes d\'abord' => ['sort=-lines_count', ['bb-15000', 'x-73500', 'x-3800']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('filters')]
    public function test_filters_and_sorts(string $query, array $expected): void
    {
        $this->getJson("/api/v1/materials?{$query}")->assertOk()->assertJsonPath('data.*.slug', $expected);
    }

    public function test_pages_follow_each_other_by_cursor_in_every_sort(): void
    {
        foreach (['name', '-name', '-max_speed_kmh', 'units_built', '-lines_count', 'in_service_from'] as $sort) {
            $all = $this->getJson("/api/v1/materials?sort={$sort}")->assertOk()->json('data.*.slug');
            $first = $this->getJson("/api/v1/materials?per_page=2&sort={$sort}")->assertOk()->assertJsonCount(2, 'data');
            $next = $first->json('links.next');
            $this->assertStringContainsString('per_page=2', $next);
            $this->assertStringContainsString('sort='.urlencode($sort), $next);
            $second = $this->getJson($next)->assertOk()->assertJsonPath('links.next', null)->assertJsonPath('meta.next_cursor', null);
            $this->assertSame($all, [...$first->json('data.*.slug'), ...$second->json('data.*.slug')], "tri {$sort}");
        }
    }

    /** @return array<string, array{string, string}> */
    public static function invalidQueries(): array
    {
        return [
            'catégorie inconnue' => ['type=fusee', 'type'],
            'tri inconnu' => ['sort=prix', 'sort'],
            'année hors bornes' => ['in_service_at=1700', 'in_service_at'],
            'période inversée' => ['from_year=1990&to_year=1980', 'to_year'],
            'ligne inconnue' => ['line=rfn-000000', 'line'],
            'trop par page' => ['per_page=500', 'per_page'],
            'curseur illisible' => ['cursor=nimporte', 'cursor'],
            'curseur d\'une autre liste' => ['cursor=eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiNDc3MDAwIiwiaWQiOjg1MiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ', 'cursor'],
            'recherche trop longue' => ['q='.str_repeat('a', 101), 'q'],
            'booléen inconnu' => ['has_image=oui', 'has_image'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_parameters_give_422(string $query, string $field): void
    {
        $this->getJson("/api/v1/materials?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_the_sheet_gives_field_sources_and_lines_with_evidence(): void
    {
        $this->getJson('/api/v1/materials/bb-15000')
            ->assertOk()
            ->assertJsonPath('data.name', 'BB 15000')
            ->assertJsonPath('data.wikidata_url', 'https://www.wikidata.org/wiki/'.$this->bb15000->wikidata_id)
            ->assertJsonPath('data.wikipedia.fr.url', 'https://fr.wikipedia.org/wiki/BB_15000')
            ->assertJsonPath('data.field_sources.0.field', 'name')
            ->assertJsonPath('data.field_sources.0.license', 'CC0 1.0')
            ->assertJsonPath('data.field_sources.0.url', 'https://www.wikidata.org/wiki/'.$this->bb15000->wikidata_id)
            ->assertJsonPath('data.lines.0.line', ['slug' => 'rfn-67000', 'code' => '067000', 'name' => 'Ligne de Lunéville à St-Dié'])
            ->assertJsonPath('data.lines.0.confidence', 'high')
            ->assertJsonPath('data.lines.0.evidence.0', [
                'kind' => 'wikipedia_line_article', 'article' => 'Ligne de Lunéville à Saint-Dié', 'revid' => 239088070,
                'section' => 'Trafic > Matériel roulant', 'quote' => 'Rames Corail tractées par des BB 15000.',
                'url' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070',
            ])
            ->assertJsonPath('data.lines.0.provenance.provider', 'wikipedia');

        /** @var list<array<string, mixed>> $sources */
        $sources = $this->getJson('/api/v1/materials/bb-15000')->json('data.field_sources');
        $image = array_values(array_filter($sources, fn (array $s): bool => $s['field'] === 'image'))[0];
        $this->assertSame('commons', $image['source']);
        $this->assertStringStartsWith('https://commons.wikimedia.org/wiki/File:', $image['url']);
    }

    public function test_an_unknown_sheet_is_404(): void
    {
        $this->getJson('/api/v1/materials/inconnu')->assertNotFound();
        $this->getJson('/api/v1/materials/BB_15000')->assertNotFound();
    }

    public function test_the_materials_of_a_line_come_with_their_evidence(): void
    {
        $this->getJson('/api/v1/lines/rfn-67000/materials')
            ->assertOk()
            ->assertJsonPath('line.slug', 'rfn-67000')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.*.material.slug', ['bb-15000', 'x-73500'])
            ->assertJsonPath('data.0.material.type_label', 'Locomotive électrique')
            ->assertJsonPath('data.0.material.thumb_url', $this->bb15000->image['thumb_url'])
            ->assertJsonPath('data.0.source_url', 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070')
            ->assertJsonMissingPath('data.0.line');

        $this->getJson('/api/v1/lines/rfn-67000/materials?min_confidence=high')->assertOk()->assertJsonPath('data.*.material.slug', ['bb-15000']);
        $this->getJson('/api/v1/lines/rfn-67000/materials?type=autorail')->assertOk()->assertJsonPath('data.*.material.slug', ['x-73500']);
        $this->getJson('/api/v1/lines/rfn-67000/materials?source=wikidata')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/lines/rfn-67000/materials?min_confidence=sure')->assertUnprocessable()->assertJsonValidationErrors('min_confidence');
        $this->getJson('/api/v1/lines/inconnue/materials')->assertNotFound();
    }

    public function test_every_material_endpoint_is_fully_documented(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        foreach (self::PATHS as $path) {
            $operation = $spec['paths'][$path]['get'] ?? null;
            $this->assertNotNull($operation, "{$path} absent de la spécification");
            $this->assertSame(['Matériel roulant'], $operation['tags']);
            $this->assertLessThanOrEqual(60, mb_strlen($operation['summary']), "{$path} : résumé trop long");
            $this->assertGreaterThan(800, mb_strlen($operation['description'] ?? ''), "{$path} : description trop courte");
            foreach ($operation['parameters'] ?? [] as $parameter) {
                $this->assertNotEmpty($parameter['description'] ?? null, "{$path} : paramètre {$parameter['name']} sans description");
                $this->assertArrayHasKey('example', $parameter, "{$path} : paramètre {$parameter['name']} sans exemple");
            }
            $this->assertNotEmpty($operation['responses']['200']['content']['application/json']['schema']['examples'] ?? null, "{$path} : exemple manquant");
        }
        // Chaque erreur possible documentée, avec un exemple de corps.
        // 401 et 403 : jeton invalide ou d'un compte suspendu, sur chaque opération (ApiAuthDocumentation).
        foreach (['/materials' => [200, 401, 403, 422, 429], '/materials/{material}' => [200, 401, 403, 404, 429], '/lines/{line}/materials' => [200, 401, 403, 404, 422, 429]] as $path => $codes) {
            $responses = $spec['paths'][$path]['get']['responses'];
            $this->assertEqualsCanonicalizing($codes, array_map('intval', array_keys($responses)), "{$path} : codes de réponse");
            foreach ($codes as $code) {
                $this->assertNotEmpty($responses[(string) $code]['description'] ?? null, "{$path} : réponse {$code} sans description");
                $this->assertNotEmpty($responses[(string) $code]['content']['application/json']['schema']['examples'] ?? null, "{$path} : réponse {$code} sans exemple");
            }
        }

        foreach (['MaterialResource', 'MaterialDetailResource', 'LineMaterialLinkResource'] as $schema) {
            foreach ($spec['components']['schemas'][$schema]['properties'] as $name => $property) {
                $this->assertNotEmpty($property['description'] ?? null, "{$schema}.{$name} sans description");
            }
        }
    }
}
