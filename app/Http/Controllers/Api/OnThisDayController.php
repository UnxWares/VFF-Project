<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnThisDay;
use Closure;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Époques', "Les ères de la carte (un instantané par décennie, de 1830 à aujourd'hui), leur présentation et les statistiques du réseau à chaque époque.", weight: 1)]
class OnThisDayController extends Controller
{
    /**
     * Ce jour-là : les faits datés au jour d'une date du calendrier.
     *
     * Ce qui s'est passé ce jour-là les années précédentes (sans l'année demandée : `GET /on-this-day/11-23` donne
     * les 23 novembre de toutes les années), trié par année : ouvertures, réouvertures et fermetures de lignes et de
     * gares, électrifications, mises en service de signalisation, démolitions de gares, accidents et jalons
     * nationaux. Sans date, le jour d'aujourd'hui. Seuls des faits dont la date est connue AU JOUR sont servis
     * (jamais une date à l'année ou au mois présentée comme un jour) et dont au moins une source est publiable ;
     * les accidents « à vérifier » ne sont pas servis. Les ouvertures et fermetures de gares sont celles que retient
     * la fiche de la gare, jamais déduites d'une ligne.
     *
     * Chaque fait : `id`, `subject` (`line`, `station`, `accident` ou `milestone`), `kind` (`milestone`, `opening`,
     * `reopening`, `closure`, `electrification`, `signalling`, `demolition`, `accident`), `traffic` (fermeture :
     * `passenger`, `freight` ou `total` ; ouverture de ligne : `passenger`, `freight` ou `null`), `year`, `years_ago`,
     * `date` ISO (toujours au jour), `when` (date rédigée), `title` (nom de la ligne, de la gare, de l'accident ou du
     * jalon), `system` (signalisation), `detail` (portions, commune ou ville), `text` (jalon), `deaths` (accident :
     * bilan connu), `path` (fiche sur le site), `line` ou `station` (`slug`, `name`) et `sources` (`provider`, `name`,
     * `url`). `types` (liste séparée par des virgules) restreint les types ; `lang` : `fr` par défaut, `en`, `de`.
     * `meta.coverage` donne le nombre de jours de l'année qui ont au moins un fait.
     */
    #[PathParameter('monthDay', 'Mois et jour « MM-JJ » (29 février accepté). Par défaut : aujourd\'hui.', example: '11-23')]
    #[ApiResponse(200, 'Les faits du jour (extrait).', examples: [[
        'data' => [
            'day' => '11-23', 'count' => 1,
            'facts' => [[
                'id' => 'a29', 'subject' => 'accident', 'kind' => 'accident', 'traffic' => null, 'year' => 1911, 'years_ago' => 115,
                'date' => '1911-11-23', 'when' => '23 novembre 1911', 'title' => 'Accident ferroviaire de Montreuil-Bellay le 23 novembre 1911',
                'system' => null, 'detail' => 'Montreuil-Bellay', 'text' => null, 'deaths' => 14, 'path' => '/accidents/1911-11-23-montreuil-bellay',
                'line' => null, 'station' => null,
                'sources' => [['provider' => 'wikidata', 'name' => 'Wikidata', 'url' => 'https://www.wikidata.org/wiki/Q20724294']],
            ]],
        ],
        'meta' => ['filters' => ['types' => []], 'coverage' => ['days' => 366, 'days_with_facts' => 365, 'facts' => 7959]],
        'links' => ['web' => 'https://vff-project.org/on-this-day/11-23', 'previous' => 'https://vff-project.org/api/v1/on-this-day/11-22', 'next' => 'https://vff-project.org/api/v1/on-this-day/11-24'],
    ]])]
    #[ApiResponse(404, 'Jour inconnu.', examples: [['message' => 'Unknown day 13-45: expected MM-DD.']])]
    #[ApiResponse(422, 'Filtre invalide.', examples: [['message' => 'The selected types is invalid.', 'errors' => ['types' => ['The selected types is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Request $request, OnThisDay $service, ?string $monthDay = null): JsonResponse
    {
        $valid = $request->validate([
            // Types de faits, séparés par des virgules (ex. « opening,closure »).
            'types' => ['nullable', 'string', 'max:200', function (string $attribute, mixed $value, Closure $fail): void {
                foreach (explode(',', (string) $value) as $type) {
                    if (! in_array(trim($type), OnThisDay::KINDS, true)) {
                        $fail('The selected types is invalid.');

                        return;
                    }
                }
            }],
            // Langue des textes rédigés (dates, portions, titres) : fr par défaut.
            'lang' => ['nullable', 'string', Rule::in(['fr', 'en', 'de'])],
        ]);
        // Sans jour demandé, la réponse est celle d'aujourd'hui : un cache partagé ne doit pas la garder après minuit.
        $maxAge = $monthDay === null ? min(3600, now()->endOfDay()->getTimestamp() - now()->getTimestamp() + 1) : 3600;
        $monthDay ??= now()->format('m-d');
        $parsed = OnThisDay::parse($monthDay);
        if ($parsed === null) {
            return response()->json(['message' => "Unknown day {$monthDay}: expected MM-DD."], 404);
        }
        $asked = array_map('trim', explode(',', (string) ($valid['types'] ?? '')));
        $types = array_values(array_filter(OnThisDay::KINDS, fn (string $k): bool => in_array($k, $asked, true)));
        $data = $service->day($parsed[0], $parsed[1], (string) ($valid['lang'] ?? 'fr'), $types);
        $coverage = $service->coverage();
        $days = OnThisDay::allDays();
        $i = (int) array_search($data['day'], $days, true);

        return response()->json([
            'data' => $data,
            'meta' => [
                'filters' => ['types' => $types],
                'coverage' => ['days' => $coverage['days'], 'days_with_facts' => $coverage['days_with_facts'], 'facts' => $coverage['facts']],
            ],
            'links' => [
                'web' => route('network.on-this-day', ['monthDay' => $data['day']]),
                'previous' => route('api.on-this-day', ['monthDay' => $days[($i + count($days) - 1) % count($days)]]),
                'next' => route('api.on-this-day', ['monthDay' => $days[($i + 1) % count($days)]]),
            ],
        ])->header('Cache-Control', 'public, max-age='.$maxAge);
    }
}
