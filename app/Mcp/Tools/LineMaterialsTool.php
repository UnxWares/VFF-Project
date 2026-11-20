<?php

namespace App\Mcp\Tools;

use App\Enums\MaterialType;
use App\Http\Resources\LineMaterialPeriodResource;
use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Models\LineMaterial;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
Séries de matériel roulant qui ont circulé (ou circulent) sur une ligne, d'après une source citée, avec leurs périodes de circulation datées : mêmes liens que l'API REST GET /api/v1/lines/{line}/materials, sans les phrases citées, regroupés par série (une série reliée par plusieurs sources n'apparaît qu'une fois ; `total` compte les séries).
Chaque entrée : `material` (slug à passer à get_material, nom, catégorie), `confidence`, `source` et `source_url` du lien le plus sûr, `links` (chaque lien de la série : `source`, `confidence`, `source_url`), `periods` (périodes de tous ses liens : début `start_date` et fin `end_date` avec leur précision day, month ou year, portion `section` si la source la nomme, `confidence` high ou medium, `origin`, `source`). Une date à l'année s'écrit au 1er janvier : lisez la précision. Une fin nulle ne veut pas dire que la série circule encore. Seules les périodes sûres sont données ; une série sans période datée a une liste vide. Une période peut porter `relation` (relation assurée : `label` comme "Paris-Montparnasse - Granville", gares `from` et `to` avec `id` et `name`, `service` comme TER ou Intercités). `relations` : relations que la série assurait sur la ligne d'après l'article de la série (`kind` relation ou itinerary, `label`, `from`, `to`, `service`, `title` de l'article d'itinéraire, `periods` qui les datent) ; liste vide si aucune n'est citée.
Sources d'un lien (`source`) : `wikipedia` (faits relevés dans les articles des lignes et des séries, CC BY-SA 4.0 : citez l'article avec le permalien `source_url`), `wikidata` (P3438, CC0) ou `contrib` (contribution relue par deux validateurs de VFF ; `source_url` est l'adresse de la source citée par le contributeur, nulle pour un ouvrage papier ; ODbL comme la base).
Exemples : {"line": "rfn-890000"} ; {"line": "909000", "type": "locomotive_diesel", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineMaterialsTool extends VffTool
{
    /** Ordre des fiabilités : le lien le plus sûr d'une série d'abord. */
    private const CONFIDENCE_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

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

        // Une entrée par série : le lien le plus sûr en tête, les périodes de tous ses liens (import, contribution).
        $items = $links->groupBy('material_id')->map(function (Collection $group) use ($request, $lang): array {
            $sorted = $group->sortBy(fn (LineMaterial $l): array => [self::CONFIDENCE_RANK[$l->confidence] ?? 3, $l->id])->values();
            $best = $sorted->first();
            $material = $best->material;
            $periods = $group->flatMap(fn (LineMaterial $l) => $l->shownPeriods)
                ->sortBy(fn ($p): string => ($p->start_date ?? $p->end_date)?->toDateString() ?? '9999')->values();

            return [
                'material' => [
                    'slug' => $material->slug,
                    'name' => $material->name,
                    'type' => $type = $material->getRawOriginal('type'),
                    'type_label' => (string) trans("materials.type.{$type}", [], $lang),
                ],
                'confidence' => $best->confidence,
                'source' => $best->source,
                'source_url' => $best->source_url,
                'links' => $sorted->map(fn (LineMaterial $l): array => [
                    'source' => $l->source,
                    'confidence' => $l->confidence,
                    'source_url' => $l->source_url,
                ])->all(),
                'periods' => LineMaterialPeriodResource::collection($periods)->resolve($request),
                // Relations assurées sur la ligne, une par libellé, tous liens de la série réunis.
                'relations' => array_values($group->flatMap(fn (LineMaterial $l): array => $l->relations($l->shownPeriods))->keyBy('label')->all()),
            ];
        })->values()->all();

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                'total' => count($items),
                'dated' => count(array_filter($items, fn (array $i): bool => $i['periods'] !== [])),
                'materials' => $items,
            ],
            $links->map(fn (LineMaterial $l): ?string => $this->attribution->providerFor('line_materials', $l->source))->unique()->values()->all(),
            $lang,
            self::url('/lines/'.$line->slug.'#materials'),
            ['materials'],
        );
    }
}
