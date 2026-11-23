<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Lookup;
use App\Mcp\Support\VffTool;
use App\Services\Timetable\Timetables;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_timetable')]
#[Title("Desserte d'une gare un jour ouvrable type")]
#[Description(<<<'TXT'
Desserte d'une gare un jour ouvrable type (un mardi d'une semaine sans jour férié) d'après les horaires théoriques ouverts de SNCF Voyageurs (TER, Intercités, TGV INOUI, OUIGO), de Transilien (RER compris) et, pour quelques gares du Sud, de ZOU ! (SNCF Open Data et transport.data.gouv.fr, ODbL) ; même contenu que l'API REST GET /api/v1/stations/{station}/timetable et que la section « Desserte » de la fiche de la gare.
Réponse : `timetable` (nul quand aucun train de voyageurs ne s'arrête dans la gare ce jour-là : fermée, desservie par des cars, saisonnière, hors des horaires lus comme la Corse ; ce n'est pas une preuve de fermeture) avec `service_date`, `trains` (chacun compté une fois, arrivées et départs confondus), `by_type` (`ter`, `intercites`, `tgv`, `transilien`, `other`), `first` et `last` (heures `HH:MM`), `directions[]` (les deux sens : `heading`, `trains`, `by_type`, `first`, `last`, `destinations[]` ou `origins[]` sur un terminus), `destinations[]` (principales destinations directes), `feeds[]` (horaires utilisés : version, jour lu, fin de validité, licence).
Précaution : ce sont des horaires théoriques d'un jour type, pas la réalité d'un jour donné (travaux, suppressions, retards inconnus) ; la desserte d'un samedi, d'un dimanche ou des vacances est différente. Dire la date du jour type (`service_date`) avec le chiffre.
Source et licence (champ `provenance` et bloc `attribution`) : SNCF, ODbL 1.0 ; la mention est à reproduire et toute base dérivée diffusée se partage sous la même licence.
Prend le slug renvoyé par search (ex. "limoges-benedictins"). Exemple : {"station": "limoges-benedictins"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationTimetableTool extends VffTool
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
        $station = Lookup::station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $timetable = app(Timetables::class)->forStation((int) $station->id);

        return $this->respond(
            [
                'station' => ['slug' => $station->slug, 'name' => $station->name, 'uic_code' => $station->uic_code],
                'timetable' => $timetable === null ? null : $timetable + ['mention' => Timetables::ATTRIBUTION],
            ],
            $timetable === null ? [] : [Timetables::SOURCE],
            $lang,
            self::url('/stations/'.$station->slug),
            ['timetable.trains'],
        );
    }
}
