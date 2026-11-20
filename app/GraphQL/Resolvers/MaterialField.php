<?php

namespace App\GraphQL\Resolvers;

use App\Http\Resources\MaterialDetailResource;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\Materials\NotableUnits;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use WeakMap;

/**
 * Champs calculés d'une série de matériel : la même valeur que l'API REST, lue dans
 * MaterialResource (résolue une fois par série et par requête).
 */
final class MaterialField
{
    /** @var WeakMap<Material, array<string, mixed>> */
    private WeakMap $resolved;

    public function __construct()
    {
        $this->resolved = new WeakMap;
    }

    public function __invoke(Material $material, array $args, GraphQLContext $context, ResolveInfo $info): mixed
    {
        return match ($info->fieldName) {
            'wikipedia_fr_url' => $material->wikipediaUrl('fr'),
            'wikipedia_en_url' => $material->wikipediaUrl('en'),
            'lines_count' => $this->linesCount($material),
            'notable_units' => app(NotableUnits::class)->forMaterial($material->slug, (string) ($args['lang'] ?? 'fr')),
            'field_sources' => array_map(fn (array $s): array => $s + [
                'original_amount' => $s['original']['amount'] ?? null,
                'original_unit' => $s['original']['unit'] ?? null,
            ], MaterialDetailResource::fieldSourcesOf($material)),
            default => $this->resource($material)[$info->fieldName] ?? null,
        };
    }

    /** @return array<string, mixed> */
    private function resource(Material $material): array
    {
        if (! isset($this->resolved[$material])) {
            /** @var array<string, mixed> $fields */
            $fields = MaterialResource::make($material)->resolve(new Request);
            $this->resolved[$material] = $fields;
        }

        return $this->resolved[$material];
    }

    /** Nombre de lignes distinctes : compté avec la liste (MaterialQuery), sinon pour cette seule série. */
    private function linesCount(Material $material): int
    {
        $count = $material->getAttribute('lines_count');

        return $count !== null
            ? (int) $count
            : (int) DB::table('line_materials')->where('material_id', $material->id)->distinct()->count('line_id');
    }
}
