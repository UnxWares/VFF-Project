<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Companies\CompanyFigures;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('companies')]
#[Title('Compagnies de chemins de fer historiques')]
#[Description(<<<'TXT'
Les compagnies de chemins de fer historiques (Nord, Est, Ouest, Paris-Orléans, PLM, Midi, État, Alsace-Lorraine, SNCF, RFF, SNCF Réseau, réseaux secondaires) : mêmes données que l'API REST GET /api/v1/companies et GET /api/v1/companies/{company} et les pages /companies (service CompanyFigures, chiffres calculés puis gardés en cache).
Sans `company` : la liste, une entrée par compagnie : `slug` (à passer dans `company`), `kind` (grande-compagnie, etat, sncf, infrastructure, secondaire), `name` officiel, `short` (appellation courante), `wikidata`, `founded` et `ended` (date et précision day ou year ; `ended` nul tant que la compagnie existe), `seat`, `summary`, `lines` (lignes rattachées), `lines_counted`, `km_peak` à l'ère `peak_year`, `km_today`, `lines_open_today` ; et `coverage` : par ère, la part des kilomètres ouverts rattachés à au moins une compagnie (le reste n'est rattaché à aucune : ne concluez jamais qu'une ligne n'avait pas de compagnie).
Avec `company` : la fiche : `company`, `events` (dates de la compagnie, texte dans la langue demandée), `sources` (pages lues), `predecessors`, `successors`, `decades` (par carte décennale : lignes rattachées et en service, km de voie ouverte, voies parallèles comprises), `peak`, et `lines` (paginées par `limit` et `offset`, `lines_total`) : chaque ligne avec `from_year`, `from_date`, `from_precision` (événement de concession), `to_year` et `to_basis` (event : fin de concession datée ; successor : concession suivante à une autre compagnie ; company_end : fin de la compagnie ; open : sans fin ; unknown : fin inconnue, la ligne n'est alors pas comptée par décennie), `km` et `open_today`.
Règle : une ligne n'est rattachée à une compagnie que par un événement daté de concession sourcé (infobox de l'article de Wikipédia de la ligne), jamais deviné ; une ligne absente n'est pas « sans compagnie ». Aucun tracé n'est servi (la carte du réseau est sur la page du site).
Sources et licences (bloc `attribution`) : Wikipédia (CC BY-SA 4.0, faits datés avec le lien de l'article), Wikidata (CC0), longueurs calculées par VFF sur ses tracés (ODbL). Chiffres pas encore calculés : erreur `figures_not_computed`.
Exemples : {} ; {"company": "plm"} ; {"company": "midi", "limit": 50, "offset": 50}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class CompaniesTool extends VffTool
{
    private const MAX_LIMIT = 100;

    protected function rules(): array
    {
        return [
            'company' => ['sometimes', 'string', 'regex:/^[a-z0-9-]{2,40}$/'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'company' => $schema->string()->description('Compagnie : `slug` donné par la liste (ex. "nord", "est", "ouest", "paris-orleans", "plm", "midi", "etat", "alsace-lorraine", "sncf", "rff", "sncf-reseau"). Sans lui : la liste des compagnies.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(30)->description('Avec `company` : nombre de lignes rattachées renvoyées, 1 à 100 (30 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)->description('Avec `company` : nombre de lignes à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $figures = app(CompanyFigures::class);
        $index = $figures->index($lang);
        if ($index === null) {
            return $this->fail('figures_not_computed');
        }
        if (! isset($input['company'])) {
            return $this->respond([
                'computed_at' => $index['computed_at'],
                'current_year' => $index['current_year'],
                'companies' => $index['companies'],
                'coverage' => $index['coverage'],
            ], ['wikipedia', 'wikidata'], $lang, self::url('/companies'), ['companies']);
        }

        $sheet = $figures->sheet($input['company'], $lang);
        if ($sheet === null) {
            return $this->fail('company_not_found', ['query' => $input['company']], ['available' => array_column($index['companies'], 'slug')]);
        }
        unset($sheet['summary'], $sheet['map_info'], $sheet['map']);
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 30);
        $lines = $sheet['lines'];
        $sheet['lines'] = array_slice($lines, $offset, $limit);
        $sheet['lines_total'] = count($lines);
        $sheet['lines_offset'] = $offset;
        $sheet['lines_has_more'] = $offset + $limit < count($lines);

        $providers = ['wikipedia', 'wikidata'];
        foreach ($sheet['sources'] ?? [] as $s) {
            $providers[] = $this->attribution->providerForUrl($s['url'] ?? null);
        }

        return $this->respond($sheet, $providers, $lang, self::url('/companies/'.$sheet['company']['slug']), ['lines', 'events', 'decades']);
    }
}
