<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Lines\LinePkPosition;
use App\Support\Search\PkQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('position_at_pk')]
#[Title("Position d'un point kilométrique sur une ligne")]
#[Description(<<<'TXT'
Où tombe un point kilométrique (PK) sur une ligne : les deux gares qui l'encadrent, la commune, un point à placer sur la carte. L'inverse de `locate_on_line` (qui donne le PK d'un lieu) ; mêmes données que l'API REST GET /api/v1/lines/{line}/at-pk et que la recherche « 570000 PK 120 » (service LinePkPosition).
Donnez `pk` en kilomètres ("120", "120,5" ou "120+500" : kilomètres et mètres) ou `pk_m` en mètres. Réponse : `line`, `pk_m`, `pk_label` (notation km+mmm), `in_range`, `bounds` (PK de la première et de la dernière gare : `from_m`, `to_m`, `from_label`, `to_label`), `at_station` (la gare dont c'est le PK) ou `between` (`from` et `to` : `slug`, `name`, `city`, `pk_m`, `pk_label`), `commune` (celle de la gare la plus proche en PK), `lat`, `lng` et `position_method` (profile : lu sur le tracé du profil en long ; stations : placé en ligne droite entre les deux gares, approximatif ; station : la gare elle-même), `position_withheld` (raison quand le tracé sous ce point n'est pas redistribuable : le point est alors placé entre les gares).
Les PK sont ceux des gares de la ligne (SNCF Réseau pour la plupart) à l'ère courante, sinon à la dernière ère où la ligne a des gares ; ils peuvent différer du PK mesuré par `locate_on_line` le long du tracé VFF. PK hors de la ligne : erreur `pk_out_of_range` avec `bounds` ; ligne sans gare : erreur `no_stops`.
Sources et licences (bloc `attribution`) : PK des gares de SNCF Réseau (ODbL), profil en long IGN (Licence Ouverte 2.0), gares de VFF (ODbL).
Exemples : {"line": "rfn-570000", "pk": "120+500"} ; {"line": "420000", "pk_m": 249204}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class PositionAtPkTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'pk' => ['required_without:pk_m', 'prohibits:pk_m', 'string', 'max:20'],
            'pk_m' => ['required_without:pk', 'integer', 'between:0,5000000'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-570000", renvoyé par search) ou code SNCF Réseau à 6 chiffres.'),
            'pk' => $schema->string()->max(20)
                ->description('PK en kilomètres : "120", "120,5" ou "120+500". Pas avec `pk_m`.'),
            'pk_m' => $schema->integer()->min(0)->max(5_000_000)
                ->description('PK en mètres (au lieu de `pk`), 0 à 5 000 000.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $pk = isset($input['pk_m']) ? (int) $input['pk_m'] : PkQuery::parseValue((string) $input['pk']);
        if ($pk === null) {
            return $this->fail('invalid_pk', ['pk' => $input['pk'] ?? '']);
        }
        $at = app(LinePkPosition::class)->at($line, $pk);
        if ($at === null) {
            return $this->fail('no_stops', ['line' => $line->name]);
        }
        if (! $at['in_range']) {
            return $this->fail('pk_out_of_range', ['pk' => $at['pk_label'], 'from' => $at['bounds']['from_label'], 'to' => $at['bounds']['to_label']], ['bounds' => $at['bounds']]);
        }

        return $this->respond($at, $at['position_method'] === 'profile' ? ['ign'] : [], $lang, self::url('/lines/'.$line->slug));
    }
}
