<?php

namespace Tests\Feature\GraphQL;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Une requête qui sélectionne récursivement tous les champs sans argument obligatoire de `Line.sheet` (matériel
 * compris) sur une ligne riche doit répondre sans erreur : un champ dont le resolver rend un objet ou une
 * énumération là où le schéma attend du texte fait échouer toute la fiche.
 */
class LineSheetScalarSweepTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_every_field_of_the_line_sheet_resolves_on_a_rich_line(): void
    {
        $this->buildRailNetwork();
        $family = Material::factory()->create(['slug' => 'x-76500', 'name' => 'X 76500', 'type' => 'autorail']);
        $loco = Material::factory()->create(['slug' => 'bb-67400', 'name' => 'BB 67400', 'type' => 'locomotive_diesel']);
        foreach ([$this->material, $family, $loco] as $material) {
            $link = LineMaterial::create(['line_id' => $this->line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'high',
                'evidence' => [['kind' => 'wikipedia_line_article', 'quote' => 'Les séries assurent les trains.']]]);
            LineMaterialPeriod::create(['line_material_id' => $link->id, 'start_date' => '1960-01-01', 'start_precision' => 'year', 'end_date' => '1980-12-31',
                'end_precision' => 'year', 'confidence' => 'high', 'origin' => 'line_article', 'source' => 'wikipedia',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1', 'fingerprint' => md5((string) $material->id)]);
        }

        $selection = $this->selection(app(SchemaBuilder::class)->schema()->getType('LineSheet'), []);
        $response = $this->graphQL('query ($slug: String!) { line(slug: $slug) { sheet { '.$selection.' } } }', ['slug' => $this->line->slug]);

        $this->assertNull($response->json('errors'), (string) json_encode($response->json('errors')));
        $this->assertIsArray($response->json('data.line.sheet.materials'));
        $types = array_column($response->json('data.line.sheet.materials'), 'type', 'slug');
        $this->assertSame('automotrice', $types['z-6400-test']);
        $this->assertSame('locomotive_diesel', $types['bb-67400']);

        $material = $this->graphQL('query ($slug: String!) { material(slug: $slug) { '.$this->selection(app(SchemaBuilder::class)->schema()->getType('Material'), [], 1).' } }', ['slug' => 'z-6400-test']);
        $this->assertNull($material->json('errors'), (string) json_encode($material->json('errors')));
        $this->assertSame('automotrice', $material->json('data.material.type'));
    }

    /** @param  list<string>  $path */
    private function selection(Type $type, array $path, int $depth = 4): string
    {
        $parts = [];
        if (! $type instanceof ObjectType && ! $type instanceof InterfaceType) {
            return '';
        }
        foreach ($type->getFields() as $name => $field) {
            foreach ($field->args as $arg) {
                if ($arg->getType() instanceof NonNull && $arg->defaultValueExists() === false) {
                    continue 2;
                }
            }
            $named = Type::getNamedType($field->getType());
            if ($named instanceof ObjectType || $named instanceof InterfaceType) {
                if (in_array($named->name, $path, true) || count($path) >= $depth) {
                    continue;
                }
                $sub = $this->selection($named, [...$path, $named->name], $depth);
                if ($sub !== '') {
                    $parts[] = $name.' { '.$sub.' }';
                }
            } elseif ($named instanceof EnumType || Type::isLeafType($named)) {
                $parts[] = $name;
            }
        }

        return implode(' ', $parts);
    }
}
