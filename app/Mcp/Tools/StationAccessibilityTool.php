<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Services\Accessibility\StationAccessibility;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('station_accessibility')]
#[Title("Accessibilité et services d'une gare")]
#[Description(<<<'TXT'
Accessibilité et services d'une gare d'aujourd'hui d'après SNCF Gares & Connexions et SNCF Voyageurs (SNCF Open Data, ODbL) : accès des personnes à mobilité réduite, ascenseurs et leur état publié, boucle à induction magnétique, bandes d'éveil, toilettes, assistance en gare (service dit « Accès Plus »), guichet, stationnement vélo sécurisé ; même contenu que l'API REST GET /api/v1/stations/{station}/accessibility et que la section « Accessibilité et services » de la fiche de la gare.
Réponse : `accessibility` (nul quand les jeux n'ont rien pour la gare) avec `access` (`mobility` = partial : accessible en partie, none : non déclarée accessible par SNCF, nul : gare absente du jeu NeTEx ; `step_free`, `tactile_guidance`, `quays`), `elevators` (`count` recensés, `status` : état publié de chaque équipement avec `state_at`), `amenities[]` (`step_free_entrance`, `boarding_assistance`, `induction_loop`, `tactile_strips`, `audio_information`, `information_screens`, `wheelchair_loan`, `staff`, `toilets`, `toilets_accessible`), `assistance` (mode, gratuité, lieu de rendez-vous, horaires par jour), `sales`, `bike_parking`, `datasets[]` (jeux lus avec leur date), `attribution`, `method`.
Précautions : une absence d'équipement ne prouve pas qu'il n'existe pas (SNCF précise que la liste n'est pas complète) ; les jeux ne se recoupent pas toujours (`mobility` = none n'empêche pas des ascenseurs recensés : dire « non déclarée accessible par SNCF », jamais « inaccessible ») ; l'état des ascenseurs est celui de la dernière lecture (`state_at`), non contractuel d'après SNCF, jamais celui du moment : toujours dire la date ; les points de vente ne comptent pas Transilien ; le stationnement vélo date de fin 2024. Pour un voyageur qui prépare un trajet, renvoyer vers l'assistance de la gare plutôt que de garantir un accès.
Source et licence (champ `provenance` et bloc `attribution`) : SNCF, ODbL 1.0 ; la mention est à reproduire et toute base dérivée diffusée se partage sous la même licence.
Prend le slug renvoyé par search (ex. "limoges-benedictins"). Exemple : {"station": "limoges-benedictins"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class StationAccessibilityTool extends VffTool
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
        $station = $this->station($input['station']);
        if ($station === null) {
            return $this->fail('station_not_found', ['query' => $input['station']], ['suggestions' => $this->suggestions($input['station'], 'stations')]);
        }
        $accessibility = app(StationAccessibility::class)->forStation((int) $station->id);

        return $this->respond(
            [
                'station' => ['slug' => $station->slug, 'name' => $station->name, 'uic_code' => $station->uic_code],
                'accessibility' => $accessibility === null ? null : $accessibility + ['mention' => StationAccessibility::ATTRIBUTION],
            ],
            $accessibility === null ? [] : [StationAccessibility::SOURCE],
            $lang,
            self::url('/stations/'.$station->slug),
            ['accessibility.access.mobility'],
        );
    }
}
