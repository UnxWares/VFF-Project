<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RailProjects;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Lignes', 'Les lignes du réseau ferré français : annuaire, fiche complète, tracés, chronologie, matériel, voies vertes et exploitations touristiques.', weight: 2)]
class RailProjectController extends Controller
{
    /**
     * Projets ferroviaires : réouvertures et lignes nouvelles.
     *
     * Les projets français pas encore en service (réouvertures au service voyageurs, lignes nouvelles, LGV, RER
     * métropolitains, électrifications annoncées) : ce que montre la page « Projets : réouvertures et lignes nouvelles ».
     *
     * Chaque projet de `projects` donne :
     *
     * - `id`, `name`, `kind` : `reopening` (réouverture), `new_line` (ligne nouvelle), `lgv` (ligne à grande vitesse),
     *   `rer` (RER métropolitain) ou `electrification` ;
     * - `state` : dernière étape franchie, `study` (étude), `debate` (débat public ou concertation), `dup` (déclaration
     *   d'utilité publique), `funding` (financement acté) ou `works` (travaux), ou `suspended` (suspendu, reporté sans date
     *   ou abandonné) ;
     * - `regions`, `porteurs` (nom et rôle : maître d'ouvrage, financeur, autorité organisatrice, association) ;
     * - `lines` : fiches de ligne VFF concernées (`slug`, `name`), vide quand la ligne n'a pas de fiche ;
     * - `summary` : une ou deux phrases en français, sans jugement ;
     * - `stages` : chaque étape (`kind` : `study`, `debate`, `dup`, `funding`, `works`, `service` ou `commissioning`) avec sa `date`
     *   (`YYYY`, `YYYY-MM` ou `YYYY-MM-DD`, à la précision de la source), `precision` (`year`, `month`, `day`), `done`
     *   (`true` : étape franchie ; `false` : date PRÉVUE, jamais certaine ; `service` = service voyageurs déjà ouvert sur une partie du projet ou ouvert puis arrêté), `note` et `source` (`label`, `url`,
     *   `published` quand connue) ;
     * - `next_stage` : première étape non franchie, `planned_commissioning` : mise en service prévue, `null` quand aucune
     *   source ne la donne ;
     * - `checked` : date de la dernière vérification des sources.
     *
     * Une date prévue est toujours à lire avec sa source : « prévue en 2028 selon SNCF Réseau ».
     */
    #[QueryParameter('state', 'État : `study`, `debate`, `dup`, `funding`, `works` ou `suspended`. Inconnu : HTTP 422.', type: 'string', example: 'works')]
    #[QueryParameter('kind', 'Type de projet : `reopening`, `new_line`, `lgv`, `rer` ou `electrification`. Inconnu : HTTP 422.', type: 'string', example: 'reopening')]
    #[QueryParameter('region', 'Région (nom exact, ex. `Occitanie`), tel que listé dans `regions` de la réponse.', type: 'string', example: 'Occitanie')]
    #[ApiResponse(200, 'Les projets (extrait).', examples: [[
        'data' => [
            'projects' => [[
                'id' => 'exemple-reouverture', 'name' => 'Réouverture de l\'exemple', 'kind' => 'reopening', 'state' => 'funding',
                'regions' => ['Occitanie'], 'porteurs' => [['name' => 'Région Occitanie', 'role' => 'financeur']],
                'lines' => [['slug' => 'rfn-000000', 'name' => 'Ligne d\'Exemple-Nord à Exemple-Sud']],
                'summary' => 'Remise en service de la ligne au service voyageurs.',
                'stages' => [
                    ['kind' => 'dup', 'date' => '2024-05-12', 'precision' => 'day', 'done' => true, 'note' => 'arrêté préfectoral', 'source' => ['label' => 'Préfecture', 'url' => 'https://example.org/dup', 'published' => '2024-05-13']],
                    ['kind' => 'commissioning', 'date' => '2028', 'precision' => 'year', 'done' => false, 'note' => null, 'source' => ['label' => 'SNCF Réseau', 'url' => 'https://example.org/projet', 'published' => null]],
                ],
                'next_stage' => ['kind' => 'commissioning', 'date' => '2028', 'precision' => 'year', 'done' => false, 'note' => null, 'source' => ['label' => 'SNCF Réseau', 'url' => 'https://example.org/projet', 'published' => null]],
                'planned_commissioning' => ['kind' => 'commissioning', 'date' => '2028', 'precision' => 'year', 'done' => false, 'note' => null, 'source' => ['label' => 'SNCF Réseau', 'url' => 'https://example.org/projet', 'published' => null]],
                'checked' => '2026-11-23',
            ]],
            'regions' => ['Occitanie'],
            'kinds' => ['reopening', 'new_line', 'lgv', 'rer', 'electrification'],
            'states' => ['study', 'debate', 'dup', 'funding', 'works', 'suspended'],
            'checked' => '2026-11-23',
        ],
        'meta' => ['projects' => 1],
        'links' => ['web' => 'https://vff-project.org/projects'],
    ]])]
    #[ApiResponse(422, 'Type ou état inconnu : détail par paramètre dans `errors`.', examples: [['message' => 'The selected state is invalid.', 'errors' => ['state' => ['The selected state is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Request $request, RailProjects $projects): JsonResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', 'string', Rule::in(RailProjects::STATES)],
            'kind' => ['nullable', 'string', Rule::in(RailProjects::KINDS)],
            'region' => ['nullable', 'string', 'max:100'],
        ]);
        $data = $projects->get();
        $state = $filters['state'] ?? null;
        $kind = $filters['kind'] ?? null;
        $region = $filters['region'] ?? null;

        $list = array_values(array_filter($data['projects'], fn (array $p): bool => ($state === null || $p['state'] === $state)
            && ($kind === null || $p['kind'] === $kind)
            && ($region === null || in_array($region, $p['regions'], true))));

        return response()->json([
            'data' => ['projects' => $list, 'regions' => $data['regions'], 'kinds' => $data['kinds'], 'states' => $data['states'], 'checked' => $data['checked']],
            'meta' => ['projects' => count($list)],
            'links' => ['web' => route('projects')],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
