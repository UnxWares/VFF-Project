<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Bibliography\SheetBibliography;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('bibliography')]
#[Title("Bibliographie d'une ligne ou d'une gare")]
#[Description(<<<'TXT'
Ouvrages et articles qui traitent d'une ligne ou d'une gare : monographies, revues ferroviaires, études locales. Mêmes données que l'API REST GET /api/v1/lines/{line}/bibliography et GET /api/v1/stations/{station}/bibliography (service SheetBibliography). VFF ne sert que les données de la notice, jamais le contenu de l'ouvrage.
Donnez soit `line`, soit `station`. Chaque `items[]` : `kind` (book ou article), `title`, `authors` (« Prénom Nom », quatre au plus), `publisher`, `place`, `year`, `isbn` (ISBN-13 sans séparateur), `journal` (revue d'un article), `url` (notice du catalogue général de la BnF, à défaut la révision de l'article de Wikipédia qui cite la référence, ou l'élément Wikidata), `source` (bnf, wikipedia, wikidata), `match` (pourquoi la référence est rattachée : `basis` = title, le titre nomme deux lieux de la ligne avec un mot du rail, `score` et `reasons` ; title_alias, le titre écrit un nom d'usage de la ligne ; article_bibliography ou article_reference, référence de l'article de Wikipédia dont le titre nomme la ligne ou la gare ; wikidata, « décrit par » ou « sujet principal » ; `origin_url` : la notice, la révision ou l'élément qui porte le rattachement), `fetched_on` (date de récupération, à citer avec la notice) et `provenance`.
Rattachement prudent : jamais un ouvrage général sur les chemins de fer ; pour une gare, le titre doit nommer la gare et un nom porté par plusieurs gares n'est jamais rattaché (les ouvrages sur la ligne sont dans la bibliographie de la ligne). Liste vide : aucune référence rattachée, ce qui ne veut pas dire qu'il n'en existe aucune.
Ordre : les plus récents d'abord, sans date en dernier ; 60 références au plus par fiche (`total` donne le nombre rattaché), paginées par `limit` et `offset`.
Licences (bloc `attribution`) : notices de la BnF sous Licence Ouverte (Etalab), à citer avec la source et `fetched_on` ; références de Wikipédia (CC BY-SA 4.0) et éléments de Wikidata (CC0).
Exemples : {"line": "rfn-722000"} ; {"station": "limoges-benedictins"} ; {"line": "rfn-570000", "limit": 10, "offset": 10}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class BibliographyTool extends VffTool
{
    private const MAX_LIMIT = SheetBibliography::MAX_ITEMS;

    protected function rules(): array
    {
        return [
            'line' => ['required_without:station', 'prohibits:station', 'string', 'min:1', 'max:100'],
            'station' => ['required_without:line', 'string', 'min:1', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:'.self::MAX_LIMIT],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)
                ->description('Ligne : slug (ex. "rfn-722000", renvoyé par search) ou code SNCF Réseau à 6 chiffres. Pas avec `station`.'),
            'station' => $schema->string()->min(1)->max(255)
                ->description('Gare : slug renvoyé par search (ex. "limoges-benedictins"). Pas avec `line`.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(20)
                ->description('Nombre de références renvoyées, 1 à 60 (20 par défaut).'),
            'offset' => $schema->integer()->min(0)->max(self::MAX_LIMIT)->default(0)
                ->description('Nombre de références à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $service = app(SheetBibliography::class);
        if (isset($input['line'])) {
            $line = $this->line($input['line']);
            if ($line === null) {
                return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
            }
            $subject = ['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name]];
            $bibliography = $service->forLine($line);
            $url = self::url('/lines/'.$line->slug);
        } else {
            $station = $this->station($input['station']);
            if ($station === null) {
                return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
            }
            $subject = ['station' => ['slug' => $station->slug, 'name' => $station->name]];
            $bibliography = $service->forStation($station);
            $url = self::url('/stations/'.$station->slug);
        }
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 20);
        $items = $bibliography['items'];
        $page = array_slice($items, $offset, $limit);

        return $this->respond($subject + [
            'total' => $bibliography['total'],
            'served' => count($items),
            'offset' => $offset,
            'count' => count($page),
            'has_more' => $offset + $limit < count($items),
            'items' => $page,
        ], array_map(fn (array $i): ?string => $this->attribution->providerFor('bibliography_entries', $i['source']), $page), $lang, $url, ['items']);
    }
}
