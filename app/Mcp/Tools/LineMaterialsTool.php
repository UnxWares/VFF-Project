<?php

namespace App\Mcp\Tools;

use App\Enums\MaterialType;
use App\Http\Resources\LineMaterialPeriodResource;
use App\Mcp\Support\VffTool;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\Materials\LineMaterialEnd;
use App\Services\Materials\LineMaterialFacts;
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
Séries de matériel roulant qui ont circulé (ou circulent) sur une ligne, d'après une source citée, avec leurs périodes de circulation datées : mêmes liens que l'API REST GET /api/v1/lines/{line}/materials, sans les phrases citées, regroupés par série (une série reliée par plusieurs sources n'apparaît qu'une fois ; `total` compte les séries). Ordre : la plus récente d'abord, d'abord d'après la présence datée sur la ligne (dernière année de présence), puis seulement d'après l'état de la série. `end` : fin de circulation telle que les sources la connaissent, `kind` `line` (fin datée sur la ligne, `year`), `series_in_service`, `series_withdrawn` (retrait de la SÉRIE, `year` nulle si seul l'état est connu : ne pas l'attribuer à la ligne) ou `unknown`, avec `source_url` et `since` (début de la période ouverte sur la ligne).
Chaque entrée : `material` (slug à passer à get_material, nom, catégorie, `image` : vignette Commons avec `url`, `width`, `height`, `author`, `license`, `source_url` à citer, nulle sans photo), `confidence`, `source` et `source_url` du lien le plus sûr, `links` (chaque lien de la série : `source`, `confidence`, `source_url`), `periods` (périodes de tous ses liens : début `start_date` et fin `end_date` avec leur précision day, month ou year, portion `section` si la source la nomme, `confidence` high ou medium, `origin`, `source`). Une date à l'année s'écrit au 1er janvier : lisez la précision. Une fin nulle ne veut pas dire que la série circule encore. Seules les périodes sûres sont données ; une série sans période datée a une liste vide. Une période peut porter `relation` (relation assurée : `label` comme "Paris-Montparnasse - Granville", gares `from` et `to` avec `id` et `name`, `service` comme TER ou Intercités). `relations` : relations que la série assurait sur la ligne d'après l'article de la série (`kind` relation ou itinerary, `label`, `from`, `to`, `service`, `title` de l'article d'itinéraire, `periods` qui les datent, `notes` : faits sourcés sur la relation comme un carburant, `fuel` "B100", `feedstock` "rapeseed", `since`, `reported` = date du dernier bilan connu, `sources` avec `url` à citer) ; liste vide si aucune n'est citée.
Comme la fiche de la ligne : `link_doubt` (raison quand les sources citent la série sur la ligne sans attester un service : `trial` essais, `one_off` circulation exceptionnelle ou présentation, `order` commande, `positioning` acheminement à vide, `preserved`, `possible`, `diverted`, `restricted`, `component`, `section`, `other` ; nulle pour une série qui a circulé : ne la présentez pas comme ayant assuré le service ; `doubtful` les compte), `earliest_on_line` (série électrique : pas avant la première électrification connue de la ligne, `date`, `precision`, `section` électrifiée la première ; `conflict` vrai quand une période sourcée commence avant : la période garde sa date et une électrification plus ancienne manque probablement dans VFF, la borne n'est alors pas sûre), `family` (famille dont la série est une version, `slug` et `name`) et `folded_into_versions` (famille que la fiche ne montre pas parce qu'une de ses versions a circulé : comptez les versions, pas la famille en plus). Les périodes sont toutes sourcées et ne sont jamais déplacées par la borne.
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
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $links = $line->materialLinks()
            ->with(['material', 'shownPeriods'])
            ->join('materials', 'materials.id', '=', 'line_materials.material_id')
            ->when(($input['type'] ?? null) !== null, fn ($q) => $q->where('materials.type', $input['type']))
            ->select('line_materials.*')
            ->get();
        // Le plus récent d'abord, comme la fiche de la ligne et l'API REST (LineMaterialEnd::sortLinks).
        $links = new Collection(LineMaterialEnd::sortLinks($links));
        $request = new Request;
        // Doute, borne d'électrification et famille comme sur la fiche, sur tous les liens de la ligne (sans le filtre).
        $facts = LineMaterialFacts::forLine($line);

        // Une entrée par série : le lien le plus sûr en tête, les périodes de tous ses liens (import, contribution).
        $items = $links->groupBy('material_id')->map(function (Collection $group) use ($request, $lang, $facts): array {
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
                    // Vignette de la photo (Commons) et son crédit, nulle sans photo.
                    'image' => Material::thumbnail($material->image),
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
                // Fin sur la ligne, sinon de la série, sinon inconnue, avec sa source (LineMaterialEnd).
                'end' => LineMaterialEnd::of($material, $periods->map(fn ($p): array => $p->toSheet())->values()->all()),
                // Comme la fiche de la ligne (LineMaterialFacts).
                'link_doubt' => $facts->doubt($material->id),
                'earliest_on_line' => $facts->earliest($material->id),
                'family' => $facts->family($material->id),
                'folded_into_versions' => $facts->folded($material->id),
            ];
        })->values()->all();

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                'total' => count($items),
                'dated' => count(array_filter($items, fn (array $i): bool => $i['periods'] !== [])),
                'doubtful' => count(array_filter($items, fn (array $i): bool => $i['link_doubt'] !== null)),
                'materials' => $items,
            ],
            $links->map(fn (LineMaterial $l): ?string => $this->attribution->providerFor('line_materials', $l->source))->unique()->values()->all(),
            $lang,
            self::url('/lines/'.$line->slug.'#materials'),
            ['materials'],
        );
    }
}
