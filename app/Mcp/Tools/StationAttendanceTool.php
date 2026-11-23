<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Sncf\StationAttendance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_attendance')]
#[Title("Fréquentation annuelle d'une gare")]
#[Description(<<<'TXT'
Fréquentation annuelle d'une gare (voyageurs par an), toutes les années publiées depuis 2015, d'après les jeux « Fréquentation en gares » de SNCF Gares & Connexions (SNCF Open Data, ODbL) ; même contenu que l'API REST GET /api/v1/stations/{station}/attendance et que la courbe de la fiche de la gare.
Réponse : `attendance` (nul quand les jeux n'en donnent aucune pour la gare : fermée avant 2015 ou absente des jeux, ce qui ne veut pas dire « aucun voyageur » ; environ 3 000 gares voyageurs en service sont couvertes) avec `years[]` (`year`, `passengers` = voyageurs, `footfall` = voyageurs et non-voyageurs réunis, `non_passengers` = la différence estimée par enquête, `series`), `latest` (dernière année et `published_year`, année de mise en ligne du jeu), `series_break`, `sources[]`, `uic_code`.
Précaution : SNCF écrit que 2025 vient d'autres sources et d'une autre méthode de calcul que 2015 à 2024 : les séries `2015-2024` et `2025` ne sont pas directement comparables. Quand `series_break` est vrai, ne jamais présenter la variation 2024-2025 comme une hausse ou une baisse réelle de fréquentation.
Source et licence (champ `provenance` et bloc `attribution`) : SNCF Gares & Connexions, ODbL 1.0 ; la mention est à reproduire et toute base dérivée diffusée se partage sous la même licence.
Prend le slug renvoyé par search (ex. "angouleme"). Exemple : {"station": "angouleme"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationAttendanceTool extends VffTool
{
    protected function rules(): array
    {
        return ['station' => ['required', 'string', 'min:1', 'max:255']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'station' => $schema->string()->min(1)->max(255)->required()
                ->description('Gare : slug renvoyé par search (ex. "angouleme").'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $station = $this->station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $attendance = app(StationAttendance::class)->forStation((int) $station->id);

        return $this->respond(
            [
                'station' => ['slug' => $station->slug, 'name' => $station->name, 'uic_code' => $station->uic_code],
                'attendance' => $attendance === null ? null : $attendance + ['mention' => StationAttendance::ATTRIBUTION],
            ],
            $attendance === null ? [] : [StationAttendance::SOURCE],
            $lang,
            self::url('/stations/'.$station->slug),
            ['attendance.years'],
        );
    }
}
