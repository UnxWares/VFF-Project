<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\CoverageFigures;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('data_coverage')]
#[Title("Ce que sait l'encyclopédie : couverture des données")]
#[Description(<<<'TXT'
Ce que sait l'encyclopédie, en nombre et en part : la couverture des données de VFF. Mêmes mesures que l'API REST GET /api/v1/stats/coverage et la page « Ce que sait l'encyclopédie » (/network/coverage, service CoverageFigures, calcul gardé en cache : `computed_at` en donne la date). À utiliser pour dire à l'utilisateur jusqu'où une réponse est complète (part des lignes dont l'ouverture est datée au jour, gares avec fréquentation, accidents relus…).
Ensembles : `lines`, `stations`, `accidents` et `materials`, chacun avec `metrics` ; une mesure donne `key` et `unit` (ce qui est mesuré : lines, stations, accidents, series, ou km pour le tracé), `available` (faux quand la table qui porte la donnée n'est pas encore importée : les autres champs valent alors 0), `total`, `done` et `share` (l'ensemble, ce qui est renseigné et sa part de 0 à 1, null sans ensemble), `open` (ce qui est renseigné à partir de sources sous licence ouverte écrite, null quand la mesure ne le dit pas) et, pour les dates d'ouverture et de fermeture, `levels` (précision day, month, year ou none avec `count`, `share`, `open`). `accidents.by_decade` et `materials.by_decade` ventilent par décennie ; `eras` donne par ère de la carte les lignes et gares ouvertes, celles dont l'ouverture est datée au jour ou au mois, et les kilomètres au tracé précis.
`set` limite la réponse à un ensemble (lines, stations, accidents, materials ou eras). Aucune donnée d'une fiche n'est servie, seulement des comptes calculés par VFF.
Licence (bloc `attribution`) : comptes calculés par VFF sur sa base (ODbL 1.0).
Exemples : {} ; {"set": "lines"} ; {"set": "eras", "lang": "en"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class DataCoverageTool extends VffTool
{
    private const SETS = ['lines', 'stations', 'accidents', 'materials', 'eras'];

    protected function rules(): array
    {
        return ['set' => ['sometimes', 'string', 'in:'.implode(',', self::SETS)]];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'set' => $schema->string()->enum(self::SETS)
                ->description('Ensemble renvoyé seul : lines, stations, accidents, materials ou eras. Par défaut : tous.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $coverage = app(CoverageFigures::class)->get();
        if (isset($input['set'])) {
            $coverage = ['computed_at' => $coverage['computed_at'] ?? null, $input['set'] => $coverage[$input['set']] ?? null];
        }

        return $this->respond(['set' => $input['set'] ?? null] + $coverage, [], $lang, self::url('/network/coverage'), ['eras']);
    }
}
