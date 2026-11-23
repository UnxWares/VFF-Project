<?php

namespace App\Mcp\Tools;

use App\Http\Resources\MaterialDetailResource;
use App\Mcp\Support\VffTool;
use App\Models\Material;
use App\Services\Api\MediaGallery;
use App\Services\Materials\PreservedRollingStock;
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
Fiche complète d'une série de matériel roulant (même contenu que l'API REST GET /api/v1/materials/{slug}) : tous les champs de la liste `materials`, plus `field_sources` (provenance de chaque champ : source, référence précise comme "Q746572#P729", adresse, licence, valeur d'origine quand VFF a converti une unité) et `lines` (lignes parcourues, une entrée par lien attesté : ligne, période si la source la date (`periods`, avec `portion` : gares `from` et `to` et PK `pk_start_m`, `pk_end_m` de la portion parcourue quand la source la nomme), `evidence` = preuves du lien, avec l'article, la révision lue, la section et le permalien `url` à citer, `confidence`, et `relations`, les relations que la série assurait sur cette ligne : `label`, gares `from` et `to`, `service`, `periods`). Aucune phrase des articles n'est recopiée : lisez-la à l'adresse du permalien.
Les liens avec les lignes ne viennent que d'une source explicite, jamais d'un nom approchant : Wikidata « véhicule habituellement utilisé » (`source` wikidata), section d'article Wikipédia (wikipedia), ou contribution relue par deux validateurs de VFF (contrib : chaque preuve `kind` contribution donne le titre, la nature et la licence de la source citée et son adresse `url`, nulle pour un ouvrage papier). Une ligne reliée par plusieurs sources a une entrée par source ; `lines_count` compte les lignes. Un champ de `field_sources` de source contrib vient d'une correction validée (référence contribution:<id>).
`notable_units` : rames ou engins remarquables de la série (ex. rame 01 « Patrick » du TGV Sud-Est), liste vide si aucun ; chacun avec `number`, `nickname`, `facts` (faits datés : `kind`, `date` et `date_to` AAAA-MM-JJ avec `precision` day, month, year ou nulle, `text` en français, reformulé, sans la citation de la source, `source` avec `publisher`, `reported_by`, `date`, `url` à citer, `provider`, `license`, `license_status`) et `unknown` (ce que les sources ne disent pas). Ne complétez pas ce qui est inconnu.
`media_count` : nombre d'images de la série publiées après validation d'une contribution ; lisez-les (auteur, licence, lien) avec l'outil media et {"material": "<slug>"}.
`preserved_count` : nombre d'exemplaires préservés de la série que citent les sources (musées, associations, lignes touristiques) ; lisez-les (lieu, état, protection monument historique, sources) avec l'outil preserved_rolling_stock et {"material": "<slug>"}.
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

        // Fournisseurs des données renvoyées : la fiche (Wikidata, Commons, Wikipédia, contributions) et chaque lien.
        $providers = [
            $this->attribution->providerFor('materials', $material->source),
            ...array_map(fn (array $entry): ?string => $this->attribution->providerFor('materials.fields', $entry['source']), array_values($material->provenance ?? [])),
            ...$material->lineLinks->map(fn ($link): ?string => $this->attribution->providerFor('line_materials', $link->source))->all(),
            'wikidata',
        ];

        return $this->respond(
            ['material' => MaterialDetailResource::make($material)->resolve(), 'media_count' => app(MediaGallery::class)->count($material),
                'preserved_count' => app(PreservedRollingStock::class)->countFor($material->slug)],
            $providers,
            $lang,
            self::url('/materials/'.$material->slug),
            ['material.lines'],
        );
    }
}
