<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Heritage\StationHeritage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_heritage')]
#[Title("Patrimoine d'une gare")]
#[Description(<<<'TXT'
Patrimoine du bâtiment voyageurs et des ouvrages d'une gare : monument historique classé ou inscrit (avec la date de l'arrêté), label « Architecture contemporaine remarquable », recensement à l'Inventaire général ; architecte, date de construction, style, référence Mérimée avec son lien, image libre. D'après la base Mérimée du ministère de la Culture (POP, Licence Ouverte 2.0) et Wikidata (CC0) ; même contenu que l'API REST GET /api/v1/stations/{station}/heritage et que la ligne de la fiche de la gare.
Réponse : `heritage` (nul quand VFF ne connaît aucun patrimoine pour la gare, ce qui ne veut pas dire « sans intérêt » : seule une centaine de gares ont un bâtiment classé ou inscrit) avec `items[]` (bâtiment voyageurs d'abord : `element`, `protection` = classified | registered | acr | inventory, `protection_date`, `scope` = partial quand seules des parties sont protégées, `architects[]` avec `role` (une notice peut citer aussi un sculpteur ou un verrier), `built`, `style`, `reference`, `image`, `source`, `match_method`), `protected`, `attribution`.
Précaution : `match_method` dit comment la gare a été trouvée (`wikidata` : sûr ; `name-commune`, `proximity` : par le nom, la commune et la proximité) ; ne jamais présenter un rattachement par proximité comme certain pour une gare voisine d'un autre monument.
Source et licence (champ `provenance` et bloc `attribution`) : Mérimée sous Licence Ouverte 2.0 (mention « Ministère de la Culture, base Mérimée (POP) »), Wikidata CC0, images sous la licence de chaque fichier Commons.
Prend le slug renvoyé par search (ex. "limoges-benedictins"). Exemple : {"station": "limoges-benedictins"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationHeritageTool extends VffTool
{
    protected function rules(): array
    {
        return ['station' => ['required', 'string', 'min:1', 'max:255']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'station' => $schema->string()->min(1)->max(255)->required()
                ->description('Gare : slug renvoyé par search (ex. "limoges-benedictins").'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $station = $this->station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $heritage = app(StationHeritage::class)->forStation((int) $station->id);

        return $this->respond(
            [
                'station' => ['slug' => $station->slug, 'name' => $station->name, 'wikidata_id' => $station->wikidata_id],
                'heritage' => $heritage,
            ],
            $heritage === null ? [] : StationHeritage::providers($heritage),
            $lang,
            self::url('/stations/'.$station->slug),
            ['heritage.items'],
        );
    }
}
