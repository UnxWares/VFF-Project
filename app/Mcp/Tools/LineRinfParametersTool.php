<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Rinf\RinfParameterComparison;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('line_rinf_parameters')]
#[Title("Paramètres RINF d'une ligne, comparés")]
#[Description(<<<'TXT'
Paramètres qu'une ligne déclare au RINF (registre de l'infrastructure de l'Agence de l'Union européenne pour les chemins de fer, déclaré par SNCF Réseau et les autres gestionnaires), par point kilométrique (PK, en mètres), côte à côte avec l'autre source de VFF (SNCF Réseau) et l'accord ou le désaccord des deux ; même contenu que l'API REST GET /api/v1/lines/{line}/rinf-parameters. Réseau d'aujourd'hui. Aucune donnée n'est remplacée : quand les sources diffèrent, dire les deux valeurs et leur source, ne pas trancher.
`parameters[]` : `electrification` (key `25000V-50Hz`, `1500V-DC`, `none`), `max_speed` (km/h : vitesse d'une section du RINF comparée à la plus forte vitesse de SNCF Réseau sur la section), `train_protection` (key : systèmes que les deux savent nommer, KVB, TVM 300, TVM 430, ETCS L1, ETCS L2 ; `values` : tous les systèmes déclarés, Crocodile, DAAT…), `radio` (`gsm-r` ou `none`), `gauging` (gabarits), `load_capability` (catégorie EN 15528, classe nationale dans `values`), `track_gauge` (mm) ; les trois derniers sans autre source (`rinf_only`). Chaque portion : `status` (`agree`, `disagree`, `rinf_only`, `vff_only`), `rinf` et `vff` (valeur de chaque côté ou null), `rg_troncon` (tronçon de SNCF Réseau). `summary` : mètres par statut. `platforms` : quais du RINF (longueur, hauteur) rapprochés des quais de SNCF Réseau gare par gare. `sections[]` : sections du RINF (points d'exploitation, voies).
Licences (`sources[]`, bloc d'attribution) : RINF © Agence de l'Union européenne pour les chemins de fer, dump officiel sous EUPL 1.2 ; SNCF Réseau ODbL 1.0.
Exemples : {"line": "rfn-579000"} ; {"line": "566000", "parameters": ["max_speed", "train_protection"], "disagreements_only": true, "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineRinfParametersTool extends VffTool
{
    protected function rules(): array
    {
        return [
            'line' => ['required', 'string', 'min:1', 'max:100'],
            'parameters' => ['sometimes', 'array', 'max:7'],
            'parameters.*' => ['string', 'in:'.implode(',', RinfParameterComparison::PARAMETERS)],
            'disagreements_only' => ['sometimes', 'boolean'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(100)->required()
                ->description('Ligne : slug (ex. "rfn-579000", renvoyé par search) ou code SNCF Réseau à 6 chiffres (ex. "579000").'),
            'parameters' => $schema->array()->items($schema->string()->enum(RinfParameterComparison::PARAMETERS))->max(7)
                ->description('Paramètres à servir : electrification, max_speed, train_protection, radio, gauging, load_capability, track_gauge. Par défaut : tous.'),
            'disagreements_only' => $schema->boolean()
                ->description('Ne garder que les portions et les quais en désaccord (`summary` reste complet). Par défaut : false.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = $this->line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $data = app(RinfParameterComparison::class)->forLine($line);
        $only = $input['parameters'] ?? null;
        if ($only !== null) {
            $data['parameters'] = array_values(array_filter($data['parameters'], fn (array $p): bool => in_array($p['key'], $only, true)));
        }
        if ((bool) ($input['disagreements_only'] ?? false)) {
            foreach ($data['parameters'] as $i => $p) {
                $data['parameters'][$i]['portions'] = array_values(array_filter($p['portions'], fn (array $x): bool => $x['status'] === 'disagree'));
            }
            $data['platforms']['items'] = array_values(array_filter($data['platforms']['items'], fn (array $x): bool => $x['status'] === 'disagree'));
        }
        $lists = ['sections', 'platforms.items'];
        foreach (array_keys($data['parameters']) as $i) {
            $lists[] = "parameters.{$i}.portions";
        }

        return $this->respond(
            ['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name], ...$data],
            array_column($data['sources'], 'key'),
            $lang,
            self::url('/lines/'.$line->slug),
            $lists,
        );
    }
}
