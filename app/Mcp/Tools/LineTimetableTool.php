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

#[Name('line_timetable')]
#[Title('Trains par jour sur une ligne')]
#[Description(<<<'TXT'
Nombre de trains de voyageurs par jour sur une ligne, un jour ouvrable type (un mardi d'une semaine sans jour férié), d'après les horaires théoriques ouverts de SNCF Voyageurs, de Transilien et de ZOU ! (ODbL) ; même contenu que `timetable` de l'API REST GET /api/v1/lines/{line}.
Réponse : `timetable` (nul quand aucun train de voyageurs ne parcourt la ligne ce jour-là : ligne fermée, touristique, fret seul, ou hors des horaires lus) avec `service_date`, `trains` (courses qui parcourent au moins deux gares consécutives de la ligne, chacune comptée une fois, quels que soient la longueur du trajet et le sens), `by_type` (`ter`, `intercites`, `tgv`, `transilien`, `other`).
Précaution : un train qui emprunte plusieurs lignes compte sur chacune ; les chiffres de deux lignes ne s'additionnent donc pas. Horaires théoriques d'un jour type : dire `service_date` avec le chiffre.
Source et licence (champ `provenance` et bloc `attribution`) : SNCF, ODbL 1.0.
Prend le slug d'une ligne renvoyé par search. Exemple : {"line": "paris-austerlitz-a-bordeaux-saint-jean"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class LineTimetableTool extends VffTool
{
    protected function rules(): array
    {
        return ['line' => ['required', 'string', 'min:1', 'max:255']];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'line' => $schema->string()->min(1)->max(255)->required()
                ->description('Ligne : slug renvoyé par search.'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $line = Lookup::line($input['line']);
        if ($line === null) {
            return $this->fail('line_not_found', ['query' => $input['line']], ['suggestions' => $this->suggestions($input['line'], 'lines')]);
        }
        $timetable = app(Timetables::class)->forLine((int) $line->id);

        return $this->respond(
            [
                'line' => ['slug' => $line->slug, 'name' => $line->name, 'code' => $line->code],
                'timetable' => $timetable === null ? null : $timetable + ['mention' => Timetables::ATTRIBUTION],
            ],
            $timetable === null ? [] : [Timetables::SOURCE],
            $lang,
            self::url('/lines/'.$line->slug),
            ['timetable.trains'],
        );
    }
}
