<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('sources_and_licences')]
#[Title('Sources et licences')]
#[Description(<<<'TXT'
Sources de VFF et conditions de réutilisation : licence de la base (ODbL 1.0) et, pour chaque fournisseur (OpenStreetMap, SNCF Open Data, IGN, OpenHistoricalMap, Wikipédia, Wikidata, Wikimedia Commons, Journal officiel, lignes-oubliees, archeoferroviaire…), son nom, sa licence, la mention à reproduire, `open_licence` (false = pas de licence ouverte écrite : reprendre seulement avec la mention et le lien demandés, parfois sans usage commercial), les pages qui établissent la licence et leur date de lecture.
Le champ `provider` des autres outils renvoie à ces fournisseurs. Sans argument : tous les fournisseurs.
Exemple : {} ; {"provider": "osm", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SourcesTool extends VffTool
{
    protected function rules(): array
    {
        return ['provider' => ['sometimes', 'string', 'in:'.implode(',', $this->attribution->providerKeys())]];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'provider' => $schema->string()->enum($this->attribution->providerKeys())
                ->description('Un seul fournisseur (clé renvoyée dans le champ `provider` des autres outils, ex. "osm", "sncf", "wikipedia"). Par défaut : tous.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $keys = isset($input['provider']) ? [$input['provider']] : $this->attribution->providerKeys();

        return $this->respond([
            'database_licence' => [
                'name' => 'Open Database License (ODbL) 1.0',
                'url' => 'https://opendatacommons.org/licenses/odbl/1-0/',
                'terms' => (string) trans('mcp.attribution.terms', [], $lang),
            ],
            'providers' => array_values(array_filter(array_map(fn (string $k) => $this->attribution->publicProvider($k, $lang), $keys))),
        ], $keys, $lang, self::url('/legal'));
    }
}
