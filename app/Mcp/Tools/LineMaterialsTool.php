<?php

namespace App\Mcp\Tools;

use App\Enums\MaterialType;
use App\Http\Resources\LineMaterialPeriodResource;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Models\LineMaterial;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_materials')]
#[Title("Matériel roulant d'une ligne et périodes de circulation")]
#[Description(<<<'TXT'
Séries de matériel roulant qui ont circulé (ou circulent) sur une ligne, d'après une source citée, avec leurs périodes de circulation datées : même liste que l'API REST GET /api/v1/lines/{line}/materials, sans les phrases citées.
Chaque entrée : `material` (slug à passer à get_material, nom, catégorie), `confidence` du lien (high, medium, low), `source` et `source_url` (permalien de la révision lue ou élément Wikidata), `periods` (début `start_date` et fin `end_date` avec leur précision day, month ou year, portion `section` si l'article la nomme, `confidence` high ou medium, `origin`, `source`). Une date à l'année s'écrit au 1er janvier : lisez la précision. Une fin nulle ne veut pas dire que la série circule encore. Seules les périodes sûres sont données ; une série sans période datée a une liste vide.
Sources : Wikipédia (faits relevés dans les articles des lignes et des séries, CC BY-SA 4.0 : citez l'article avec le permalien) et Wikidata (P3438, CC0).
Exemples : {"line": "rfn-890000"} ; {"line": "909000", "type": "locomotive_diesel", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineMaterialsTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['sometimes', 'nullable', Rule::enum(MaterialType::class)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-890000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "890000").'),
            'type' => $schema->string()->enum(array_column(MaterialType::cases(), 'value'))
                ->description('Catégorie de matériel (ex. "autorail", "locomotive_diesel"). Toutes par défaut.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $links = $line->materialLinks()
            ->with(['material', 'shownPeriods'])
            ->join('materials', 'materials.id', '=', 'line_materials.material_id')
            ->when(($input['type'] ?? null) !== null, fn ($q) => $q->where('materials.type', $input['type']))
            ->orderByRaw('materials.in_service_from ASC NULLS LAST')
            ->orderBy('materials.name')
            ->select('line_materials.*')
            ->get();
        $request = new Request;

        $items = $links->map(fn (LineMaterial $link): array => [
            'material' => [
                'slug' => $link->material->slug,
                'name' => $link->material->name,
                'type' => $type = $link->material->getRawOriginal('type'),
                'type_label' => (string) trans("materials.type.{$type}", [], $lang),
            ],
            'confidence' => $link->confidence,
            'source' => $link->source,
            'source_url' => $link->source_url,
            'periods' => LineMaterialPeriodResource::collection($link->shownPeriods)->resolve($request),
        ])->all();

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                'total' => count($items),
                'dated' => count(array_filter($items, fn (array $i): bool => $i['periods'] !== [])),
                'materials' => $items,
            ],
            ['wikidata', 'wikipedia'],
            $lang,
            self::url('/lines/'.$line->slug.'#materials'),
            ['materials'],
        );
    }
}
