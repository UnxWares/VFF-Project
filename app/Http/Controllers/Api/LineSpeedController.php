<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Models\Line;
use App\Services\Speed\LineSpeeds;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineSpeedController extends Controller
{
    public function __construct(private readonly LineSpeeds $speeds) {}

    /**
     * Vitesses maximales d'une ligne.
     *
     * `ranges` : vitesse maximale nominale par tranche de points kilométriques (PK) homogène, pour
     * l'ère choisie, dans l'ordre des PK. Données de SNCF Réseau Open Data (jeu
     * `vitesse-maximale-nominale-sur-ligne`, ODbL : mention « SNCF Réseau », partage à l'identique
     * des bases dérivées), qui décrit le réseau au jour même : seule l'ère courante (2030) est
     * renseignée, une autre ère renvoie une liste vide. Vitesse nominale de la ligne, sans les
     * limitations temporaires ni les vitesses propres à une catégorie de trains. Les tranches
     * voisines de même vitesse sont réunies. PK en mètres (`pk_start_m`, `pk_end_m`, `length_m`)
     * et en notation `km+mmm`, dans la numérotation de SNCF Réseau (la même que `/signalling`).
     *
     * `summary` : vitesses la plus haute et la plus basse, longueur couverte, et longueur par vitesse
     * (`by_speed`, la plus haute d'abord).
     *
     * `history` : changements de vitesse datés relevés dans l'article Wikipédia de la ligne, du plus
     * ancien au plus récent. `kind` : `raise` (relèvement), `lower` (abaissement), `opening`
     * (vitesse à l'ouverture), `state` (vitesse autorisée à cette date). `previous_speed_kmh` quand
     * l'article donne la vitesse d'avant. `date_precision` : `day`, `month` ou `year` (jour et mois
     * manquants valent 01) ; `reliability` : `high` (jour ou mois) ou `medium` (année) ; les faits
     * ambigus ne sont jamais rendus. `scope` : `whole_line`, `section` (portion `section`, placée sur
     * la ligne par ses gares quand c'est possible : `pk_start_m`, `pk_end_m`) ou `unspecified`.
     * `source` : révision lue (permalien) et courte phrase de l'article (CC BY-SA 4.0). Un fait
     * absent ne veut pas dire que la vitesse n'a jamais changé.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     era_year: int,
     *     ranges: array<array{pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string, length_m: int, max_speed_kmh: int}>,
     *     summary: array{max_speed_kmh: ?int, min_speed_kmh: ?int, covered_m: int, by_speed: array<array{max_speed_kmh: int, length_m: int}>},
     *     history: array<array{
     *         kind: string, speed_kmh: int, previous_speed_kmh: ?int, date: string, date_precision: string,
     *         reliability: string, scope: string,
     *         section: ?array{from: ?string, to: ?string, pk_start_m: ?int, pk_end_m: ?int},
     *         source: array{provider: string, title: string, url: string, revision: int, excerpt: ?string}
     *     }>,
     *     provenance: array{
     *         ranges: array{provider: string, dataset: string, url: string, license: string, mention: string},
     *         history: array{provider: string, license: string, mention: string}
     *     }
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-536000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire le réseau d'aujourd'hui.", type: 'int', example: 2030)]
    #[ApiResponse(200, "Vitesses maximales de la ligne à l'ère choisie et changements datés.", examples: [[
        'line' => ['slug' => 'rfn-536000', 'code' => '536000', 'name' => 'Ligne de Sainte-Pazanne à Pornic'],
        'era_year' => 2030,
        'ranges' => [[
            'pk_start_m' => 34650, 'pk_end_m' => 61190, 'pk_start_label' => '34+650', 'pk_end_label' => '61+190',
            'length_m' => 26540, 'max_speed_kmh' => 140,
        ]],
        'summary' => ['max_speed_kmh' => 140, 'min_speed_kmh' => 140, 'covered_m' => 26540, 'by_speed' => [['max_speed_kmh' => 140, 'length_m' => 26540]]],
        'history' => [[
            'kind' => 'raise', 'speed_kmh' => 140, 'previous_speed_kmh' => null, 'date' => '2015-12-15', 'date_precision' => 'day',
            'reliability' => 'high', 'scope' => 'whole_line', 'section' => null,
            'source' => [
                'provider' => 'wikipedia', 'title' => 'Ligne de Sainte-Pazanne à Pornic',
                'url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Sainte-Pazanne_%C3%A0_Pornic&oldid=230000000',
                'revision' => 230000000, 'excerpt' => 'La vitesse de la ligne est relevée à 140 km/h le 15 décembre 2015.',
            ],
        ]],
        'provenance' => [
            'ranges' => ['provider' => 'SNCF Réseau', 'dataset' => 'vitesse-maximale-nominale-sur-ligne',
                'url' => LineSpeeds::DATASET_URL, 'license' => 'ODbL-1.0', 'mention' => 'SNCF Réseau, Open Data (ODbL)'],
            'history' => ['provider' => 'Wikipédia', 'license' => 'CC BY-SA 4.0',
                'mention' => 'Faits relevés dans les articles de Wikipédia (CC BY-SA 4.0), lien vers la révision lue'],
        ],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.')]
    public function index(Line $line, Request $request): JsonResponse
    {
        $request->validate(['era' => 'nullable|integer']);

        $era = $request->filled('era')
            ? Era::where('year', $request->integer('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$this->speeds->forLine($line->id, $era->id, (int) $era->year),
        ])->header('Cache-Control', 'public, max-age=600');
    }
}
