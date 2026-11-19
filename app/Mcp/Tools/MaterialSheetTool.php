<?php

namespace App\Mcp\Tools;

use App\Http\Resources\MaterialDetailResource;
use App\Mcp\Support\VffTool;
use App\Models\Material;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_material')]
#[Title("Fiche d'une série de matériel roulant")]
#[Description(<<<'TXT'
Fiche complète d'une série de matériel roulant (même contenu que l'API REST GET /api/v1/materials/{slug}) : tous les champs de la liste `materials`, plus `field_sources` (provenance de chaque champ : source, référence précise comme "Q746572#P729", adresse, licence, valeur d'origine quand VFF a converti une unité) et `lines` (lignes parcourues, une entrée par lien attesté : ligne, période si la source la date, `evidence` = preuves du lien, avec l'article, la révision lue, la section et le permalien `url` à citer, `confidence`). Aucune phrase des articles n'est recopiée : lisez-la à l'adresse du permalien.
Les liens avec les lignes ne viennent que d'une source explicite (Wikidata « véhicule habituellement utilisé », ou section d'article Wikipédia), jamais d'un nom approchant.
Identifiant : `material` = slug renvoyé par search ou materials (ex. "bb-15000").
Exemple : {"material": "bb-15000"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class MaterialSheetTool extends VffTool
{
    protected function rules(): array
    {
        return ['material' => ['required', 'string', 'min:1', 'max:255']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'material' => $schema->string()->min(1)->max(255)->required()
                ->description('Slug de la série (ex. "bb-15000"), tel que renvoyé par search ou materials.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $material = Material::where('slug', $input['material'])->first();
        if ($material === null) {
            return $this->fail('material_not_found', ['query' => $input['material']], ['suggestions' => $this->suggestions($input['material'], 'materials')]);
        }
        // Même chargement que la fiche REST (Api\MaterialController::show).
        $material->loadCount(['lineLinks as lines_count' => fn (Builder $q) => $q->select(DB::raw('count(distinct line_id)'))]);
        $material->load(['lineLinks' => fn ($q) => $q->with(['line:id,slug,code,name', 'era:id,year', 'shownPeriods'])
            ->join('lines', 'lines.id', '=', 'line_materials.line_id')
            ->orderBy('lines.name')
            ->select('line_materials.*')]);

        return $this->respond(
            ['material' => MaterialDetailResource::make($material)->resolve()],
            ['wikidata', 'commons', 'wikipedia'],
            $lang,
            self::url('/materials/'.$material->slug),
            ['material.lines'],
        );
    }
}
