<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Traction\LineElectrificationHistory;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineElectrificationHistoryController extends Controller
{
    public function __construct(private readonly LineElectrificationHistory $electrification) {}

    /**
     * Histoire de l'électrification d'une ligne.
     *
     * `ranges` : tranches de points kilométriques (PK) électrifiées aujourd'hui, avec leur tension, dans
     * l'ordre des PK. Données de SNCF Réseau Open Data (jeu `liste-des-lignes-electrifiees`, ODbL :
     * mention « SNCF Réseau », partage à l'identique des bases dérivées). `system` : `dc` (courant continu,
     * 1 500 V ou 3 000 V), `ac` (alternatif, 25 kV 50 Hz) ou `third_rail` (troisième rail) ; `unknown` si le
     * jeu ne le dit pas. Les tranches voisines de même tension sont réunies. PK en mètres et en notation
     * `km+mmm`, dans la numérotation de SNCF Réseau (la même que `/signalling`). Liste vide pour une ligne
     * hors du réseau de SNCF Réseau ou sans tranche publiée.
     *
     * `summary` : `line_electrified` et `line_voltage_v` (état de la ligne dans VFF, toutes sources, à
     * l'ère courante), longueur électrifiée selon SNCF et longueur par tension (`by_voltage`, la plus
     * longue d'abord).
     *
     * `history` : mises sous tension, conversions de tension et désélectrifications datées, relevées dans
     * les chronologies des articles Wikipédia de la ligne et dans Wikidata, du plus ancien au plus récent.
     * `kind` : `electrification` (mise sous tension d'une portion), `conversion` (ré-électrification ou
     * changement de tension, par exemple du troisième rail à la caténaire) ou `deelectrification` (dépose de
     * la caténaire). `voltage_v` et `system` quand la phrase les cite (jamais déduits). `date_precision` :
     * `day`, `month` ou `year` (jour et mois manquants valent 01) ; `reliability` : `high` (date au jour
     * ou au mois dans la phrase) ou `medium` (année). `scope` : `whole_line`, `section` (portion `section`,
     * placée sur la ligne par ses gares quand c'est possible : `pk_start_m`, `pk_end_m`) ou `unspecified`
     * (la phrase ne dit pas quelle portion). Seuls les faits sûrs sont rendus : travaux, projets,
     * décisions, fourchettes de dates (« de 1924 à 1927 ») et phrases ambiguës sont écartés ; un fait
     * absent ne veut pas dire que la portion n'a pas été électrifiée à une autre date. `source` : fournisseur
     * (`wikipedia`, `wikidata`), permalien de la révision lue et courte phrase de l'article (CC BY-SA 4.0).
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     ranges: array<array{pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string, length_m: int, voltage_v: ?int, system: string}>,
     *     summary: array{line_electrified: bool, line_voltage_v: ?int, electrified_m: int, by_voltage: array<array{voltage_v: ?int, system: string, length_m: int}>},
     *     history: array<array{
     *         kind: string, voltage_v: ?int, system: ?string, date: string, date_precision: string,
     *         reliability: string, scope: string,
     *         section: ?array{from: ?string, to: ?string, pk_start_m: ?int, pk_end_m: ?int},
     *         source: array{provider: string, url: ?string, excerpt: ?string}
     *     }>,
     *     provenance: array{
     *         ranges: array{provider: string, dataset: string, url: string, license: string, mention: string},
     *         history: array{provider: string, license: string, mention: string}
     *     }
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-830000')]
    #[ApiResponse(200, 'Électrification actuelle de la ligne par tranche de PK et mises sous tension datées.', examples: [[
        'line' => ['slug' => 'rfn-830000', 'code' => '830000', 'name' => 'Ligne de Paris-Lyon à Marseille-St-Charles'],
        'ranges' => [[
            'pk_start_m' => -47, 'pk_end_m' => 862100, 'pk_start_label' => '-0+047', 'pk_end_label' => '862+100',
            'length_m' => 862147, 'voltage_v' => 1500, 'system' => 'dc',
        ]],
        'summary' => ['line_electrified' => true, 'line_voltage_v' => 1500, 'electrified_m' => 862147,
            'by_voltage' => [['voltage_v' => 1500, 'system' => 'dc', 'length_m' => 862147]]],
        'history' => [[
            'kind' => 'electrification', 'voltage_v' => null, 'system' => null, 'date' => '1952-05-26', 'date_precision' => 'day',
            'reliability' => 'high', 'scope' => 'section',
            'section' => ['from' => 'Chalon', 'to' => 'Lyon-Perrache', 'pk_start_m' => 382150, 'pk_end_m' => 510914],
            'source' => [
                'provider' => 'wikipedia',
                'url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Lyon_%C3%A0_Marseille-Saint-Charles&oldid=241214704',
                'excerpt' => '26 mai 1952 : Chalon - Lyon-Perrache ;',
            ],
        ]],
        'provenance' => [
            'ranges' => ['provider' => 'SNCF Réseau', 'dataset' => 'liste-des-lignes-electrifiees',
                'url' => LineElectrificationHistory::DATASET_URL, 'license' => 'ODbL-1.0', 'mention' => 'SNCF Réseau, Open Data (ODbL)'],
            'history' => ['provider' => 'Wikipédia, Wikidata', 'license' => 'CC BY-SA 4.0, CC0 1.0',
                'mention' => 'Faits relevés dans les articles de Wikipédia (CC BY-SA 4.0), lien vers la révision lue, et dans Wikidata (CC0)'],
        ],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.')]
    public function index(Line $line): JsonResponse
    {
        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$this->electrification->forLine($line),
        ])->header('Cache-Control', 'public, max-age=600');
    }
}
