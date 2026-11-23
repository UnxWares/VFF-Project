<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Structures\LineStructures;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_structures')]
#[Title("Ouvrages d'art d'une ligne")]
#[Description(<<<'TXT'
Tunnels, viaducs et grands ponts d'une ligne, dans l'ordre des points kilométriques, ceux d'une ligne fermée ou déposée compris ; même contenu que l'API REST GET /api/v1/lines/{line}/structures.
Réponse : `summary` (`count`, `by_kind` : tunnel, viaduct, bridge ; `tunnel_length_m` ; `longest`) et `items[]` : `kind`, `name` (nul pour un ouvrage sans nom connu : dire « un tunnel de 320 m », sans inventer de nom), `length_m` (mesurée sur le tracé de la BD TOPO si `length_source` = bdtopo, sinon donnée par Wikidata), `pk_start_label`, `pk_end_label` (PK estimés sur le profil en long, calé sur les gares : dire « vers le PK »), `built`, `opened`, `closed` ({date, precision} : avec `precision` = year, ne donner que l'année), `heritage` (classified = classé monument historique, registered = inscrit, protected = protégé sans savoir si classé ou inscrit : ne pas trancher), `merimee_ref`, `image` (photo Commons : citer auteur et licence), `current` (faux : la ligne n'est plus en service à l'ouvrage aujourd'hui, fermée ou déposée ; l'ouvrage peut rester, reconverti), `sources[]` (source de chaque champ).
Couverture : les tunnels de 50 m ou plus et les ponts de 100 m ou plus de la BD TOPO, plus les ouvrages nommés de la BD TOPO et de Wikidata ; les petits ponts (passages inférieurs) n'y sont pas, et une liste vide ne prouve pas l'absence d'ouvrage.
Sources et licences (champ `provenance`, mention `attribution`) : IGN BD TOPO, Licence Ouverte 2.0 ; Wikidata, CC0 ; images Wikimedia Commons, licence de chaque fichier.
Exemples : {"line": "rfn-722000"} ; {"line": "806000", "kind": "tunnel", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineStructuresTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'kind' => ['sometimes', 'string', 'in:tunnel,viaduct,bridge'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-722000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "722000").'),
            'kind' => $schema->string()->enum(['tunnel', 'viaduct', 'bridge'])
                ->description('Ne garde que ce type d\'ouvrage (le résumé reste celui de toute la ligne).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $structures = app(LineStructures::class);
        $block = $structures->forLine((int) $line->id);
        if (isset($input['kind'])) {
            $block['items'] = array_values(array_filter($block['items'], fn (array $i): bool => $i['kind'] === $input['kind']));
        }

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
                ...$block,
                'provenance' => $structures->provenance() + ['mention' => LineStructures::ATTRIBUTION],
            ],
            ['ign', 'wikidata', 'commons'],
            $lang,
            self::url('/lines/'.$line->slug),
            ['items'],
        );
    }
}
