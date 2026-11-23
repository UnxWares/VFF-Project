<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NetworkChronology;
use App\Support\FrenchDepartments;
use Closure;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Époques', "Les ères de la carte (un instantané par décennie, de 1830 à aujourd'hui), leur présentation et les statistiques du réseau à chaque époque.", weight: 1)]
class ChronologyController extends Controller
{
    private string $lang = 'fr';

    /**
     * Chronologie du rail en France : aperçu année par année.
     *
     * Ce que l'encyclopédie sait du réseau, année par année de 1827 à aujourd'hui, compté par type de fait : les
     * mêmes données que la page « Chronologie du rail en France », calculées par une commande puis gardées en cache
     * (`computed_at`). Types (`types`, liste séparée par des virgules) : `milestone` (jalons nationaux : lois,
     * création des entreprises du réseau), `opening`, `reopening`, `closure` (aux voyageurs, au fret ou totale),
     * `electrification`, `signalling` (mises en service de BAL, BAPR, TVM, ETCS…), `material` (séries d'au moins
     * 100 exemplaires et rames TGV, année de mise en service), `accident` (accidents d'au moins 5 morts).
     * `region` : code INSEE de la région (portion située par ses gares, sinon toute la ligne) ; jalons et matériel
     * n'ont pas de région.
     *
     * Chaque compte : `shown` (faits détaillés, au moins une source publiable), `hidden` (faits connus seulement par
     * des sources dont les faits ne sont pas publiés : comptés, jamais détaillés) et `km` (somme des longueurs
     * connues des faits détaillés). Un même fait rapporté par plusieurs sources n'est compté qu'une fois. Les faits
     * d'une décennie : `GET /chronology/{decade}`.
     */
    #[ApiResponse(200, 'La chronologie, comptes par année (extrait).', examples: [[
        'data' => [
            'computed_at' => '2026-11-23T03:00:00+01:00', 'first_year' => 1827, 'last_year' => 2026,
            'filters' => ['types' => ['closure'], 'region' => null],
            'decades' => [['decade' => 1930, 'shown' => 610, 'hidden' => 0]],
            'years' => [['year' => 1938, 'counts' => ['closure' => ['shown' => 144, 'hidden' => 0, 'km' => 4373.4]]]],
        ],
        'links' => ['web' => 'https://vff-project.org/chronology', 'decade' => 'https://vff-project.org/api/v1/chronology/1820'],
    ]])]
    #[ApiResponse(422, 'Filtre invalide.', examples: [['message' => 'The selected region is invalid.', 'errors' => ['region' => ['The selected region is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Request $request, NetworkChronology $chronology): JsonResponse
    {
        [$types, $region] = $this->filters($request);
        $overview = $chronology->overview($types, $region);
        [$first] = NetworkChronology::decadeBounds($overview['last_year']);

        return response()->json([
            'data' => $overview,
            'links' => ['web' => route('network.chronology'), 'decade' => route('api.chronology.decade', $first)],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Chronologie du rail en France : les faits d'une décennie.
     *
     * Les faits détaillés de la décennie (1930 : de 1930 à 1939), année par année, avec les comptes de l'année
     * (voir `GET /chronology`). Chaque fait : `id`, `kind`, `traffic` (fermeture : `passenger`, `freight` ou
     * `total` ; ouverture ou réouverture : `passenger`, `freight` ou `null`), `date` ISO à lire avec `precision`
     * (`day` : toutes les sources qui le datent au jour s'accordent ; `month` ; `year` : année seule), `when` (date
     * rédigée), `title` (accident, matériel, jalon, système de signalisation), `text` (jalon), `line` (`slug`,
     * `name`), `sections` (portions, `text` et `km`), `km` (longueur connue, sans compter deux fois un tronçon),
     * `deaths`, `units`, `path` (fiche sur le site), `regions` et `sources` (`provider`, `name`, `url`). Les textes
     * suivent la langue demandée (`lang` : `fr` par défaut, `en`, `de`).
     */
    #[PathParameter('decade', 'Décennie : 1820 à la décennie en cours, multiple de 10.', example: 1930)]
    #[ApiResponse(200, 'Les faits de la décennie (extrait).', examples: [[
        'data' => [
            'decade' => 1930, 'computed_at' => '2026-11-23T03:00:00+01:00', 'filters' => ['types' => [], 'region' => null],
            'years' => [[
                'year' => 1938,
                'counts' => ['milestone' => ['shown' => 1, 'hidden' => 0, 'km' => 0], 'closure' => ['shown' => 144, 'hidden' => 0, 'km' => 4373.4]],
                'facts' => [[
                    'id' => 'e10234', 'kind' => 'closure', 'traffic' => 'passenger', 'year' => 1938, 'date' => '1938-01-01', 'precision' => 'year',
                    'when' => '1938', 'title' => null, 'text' => null, 'line' => ['slug' => 'rfn-427000', 'name' => "Ligne d'Alençon à Domfront"],
                    'sections' => [['text' => 'de Couterne à Domfront', 'km' => 22.8]], 'km' => 22.8, 'deaths' => null, 'units' => null,
                    'path' => '/lines/rfn-427000', 'regions' => ['28'],
                    'sources' => [['provider' => 'wikipedia', 'name' => 'Wikipédia', 'url' => 'https://fr.wikipedia.org/wiki/Ligne_d%27Alen%C3%A7on_%C3%A0_Domfront']],
                ]],
            ]],
        ],
        'links' => ['web' => 'https://vff-project.org/chronology?decade=1930', 'previous' => 'https://vff-project.org/api/v1/chronology/1920', 'next' => 'https://vff-project.org/api/v1/chronology/1940'],
    ]])]
    #[ApiResponse(404, 'Décennie hors de la chronologie.', examples: [['message' => 'Unknown decade 1935: decades are 1820 to 2020.']])]
    #[ApiResponse(422, 'Filtre invalide.', examples: [['message' => 'The selected region is invalid.', 'errors' => ['region' => ['The selected region is invalid.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Request $request, NetworkChronology $chronology, int $decade): JsonResponse
    {
        [$types, $region] = $this->filters($request);
        $overview = $chronology->overview($types, $region);
        [$first, $last] = NetworkChronology::decadeBounds($overview['last_year']);
        if ($decade % 10 !== 0 || $decade < $first || $decade > $last) {
            return response()->json(['message' => "Unknown decade {$decade}: decades are {$first} to {$last}."], 404);
        }
        $data = $chronology->decade($decade, $types, $region, $this->lang);

        return response()->json([
            'data' => [
                'decade' => $decade,
                'computed_at' => $overview['computed_at'],
                'filters' => $overview['filters'],
                'years' => $data['years'],
            ],
            'links' => [
                'web' => route('network.chronology', ['decade' => $decade]),
                'previous' => $decade > $first ? route('api.chronology.decade', $decade - 10) : null,
                'next' => $decade < $last ? route('api.chronology.decade', $decade + 10) : null,
            ],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @return array{0: list<string>, 1: ?string}
     */
    private function filters(Request $request): array
    {
        $valid = $request->validate([
            // Types de faits, séparés par des virgules (ex. « opening,reopening »).
            'types' => ['nullable', 'string', 'max:200', function (string $attribute, mixed $value, Closure $fail): void {
                foreach (explode(',', (string) $value) as $type) {
                    if (! in_array(trim($type), NetworkChronology::KINDS, true)) {
                        $fail('The selected types is invalid.');

                        return;
                    }
                }
            }],
            // Code INSEE de la région (« 53 » Bretagne).
            'region' => ['nullable', 'string', Rule::in(FrenchDepartments::regionCodes())],
            // Langue des textes rédigés (dates, portions, titres) : fr par défaut.
            'lang' => ['nullable', 'string', Rule::in(['fr', 'en', 'de'])],
        ]);
        $this->lang = (string) ($valid['lang'] ?? 'fr');
        $asked = array_map('trim', explode(',', (string) ($valid['types'] ?? '')));
        $types = array_values(array_filter(NetworkChronology::KINDS, fn (string $k): bool => in_array($k, $asked, true)));

        return [$types, isset($valid['region']) ? (string) $valid['region'] : null];
    }
}
