<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Models\Line;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingRanges;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineSignallingController extends Controller
{
    public function __construct(private readonly SignallingRanges $ranges, private readonly LineSignallingHistory $history) {}

    /**
     * Signalisation d'une ligne.
     *
     * Tranches de points kilométriques (PK) homogènes en signalisation, pour
     * l'ère choisie. Données de SNCF Réseau Open Data (jeux
     * `mode-de-cantonnement-des-lignes` et `lignes-equipees-de-kvb` de SNCF Réseau, ODbL :
     * mention « SNCF Réseau », partage à l'identique des bases dérivées), qui
     * couvrent aussi l'ETCS des lignes à grande vitesse récentes. Aujourd'hui seule l'ère courante (2030) est renseignée ; une
     * autre ère renvoie en général une liste vide.
     *
     * Deux notions distinctes :
     * - `signalling` : mode de **cantonnement**, qui espace les trains :
     *   `BAL` (block automatique lumineux), `BAPR` (block automatique à
     *   permissivité restreinte), `BM` (block manuel), `BMVU` (block manuel de
     *   voie unique), `CAPI` (cantonnement assisté par informatique),
     *   `CT` (cantonnement téléphonique de double voie), `TVM-300` / `TVM-430` (signalisation
     *   en cabine des LGV, sans signaux latéraux), `ETCS_L1` / `ETCS_L2`.
     *   Suffixes : `-VB` voie banalisée (circulation dans les deux sens sur
     *   chaque voie), `-VU` voie unique, `-Tram` section tram-train ;
     * - `signalling_systems` : **tous** les systèmes de cantonnement installés sur la
     *   tranche, code principal (`signalling`) en premier. En général un seul ; deux sur les
     *   LGV équipées à la fois de l'ETCS niveau 2 et de la TVM (`["ETCS_L2", "TVM-300"]` sur
     *   la LGV Bretagne - Pays de la Loire et la LGV SEA, `["ETCS_L2", "TVM-430"]` sur la
     *   LGV Est), que SNCF Réseau publie dans un seul libellé ;
     * - `source_label` : libellé de SNCF Réseau tel quel (ex. « European train control
     *   system 2/Transmission voie-machine 300 »), null pour une tranche d'une autre source ;
     * - `train_protection` : systèmes de **contrôle de vitesse** superposés
     *   au cantonnement (`KVB`, `KVBP`, `TVM-300`, `TVM-430`, `ERTMS_L1`,
     *   `ERTMS_L2`). Le KVB est un contrôle de vitesse, pas un mode de
     *   cantonnement ;
     * - `kvb_equipped` : raccourci, la tranche est équipée du KVB ou du KVBP.
     *
     * PK en mètres (`pk_start_m`, `pk_end_m`, `length_m`) et en notation
     * ferroviaire `km+mmm` (`pk_start_label`, `pk_end_label`), dans la
     * numérotation de SNCF Réseau. Les mêmes tranches sont dessinées sur la
     * carte par `GET /eras/{year}/signalling/tiles/{z}/{x}/{y}.mvt`.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     era_year: int,
     *     ranges: array<array{
     *         pk_start_m: int, pk_end_m: int,
     *         pk_start_label: string, pk_end_label: string,
     *         length_m: int, signalling: string, signalling_systems: array<string>,
     *         train_protection: array<string>, kvb_equipped: bool,
     *         source_label: ?string, note: ?string
     *     }>
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire le réseau d'aujourd'hui.", type: 'int', example: 2030)]
    #[ApiResponse(200, "Tranches de signalisation de la ligne à l'ère choisie.", examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest'],
        'era_year' => 2030,
        'ranges' => [[
            'pk_start_m' => 420110, 'pk_end_m' => 436505, 'pk_start_label' => '420+110', 'pk_end_label' => '436+505',
            'length_m' => 16395, 'signalling' => 'BAPR', 'signalling_systems' => ['BAPR'], 'train_protection' => ['KVB'], 'kvb_equipped' => true,
            'source_label' => 'Block automatique à permissivité restreinte de double voie',
            'note' => 'Source : SNCF Open Data, mode-de-cantonnement-des-lignes',
        ]],
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue.')]
    public function index(Line $line, Request $request): JsonResponse
    {
        $request->validate(['era' => 'nullable|integer']);

        $era = $request->filled('era')
            ? Era::where('year', $request->integer('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $ranges = $this->ranges->forLine($line->id, $era->id);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'era_year' => $era->year,
            'ranges' => $ranges,
        ])->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Historique de la signalisation d'une ligne.
     *
     * Mises en service **datées** de systèmes de signalisation, relevées dans l'article Wikipédia de la
     * ligne (`vff:import-signalling-history`) : un fait par système, date et portion. C'est un historique à
     * part : l'état actuel de la ligne reste `GET /lines/{line}/signalling` (SNCF Réseau), que ces faits ne
     * corrigent pas, et la carte par ère n'en tient pas compte. Liste vide si l'article ne date aucune mise
     * en service.
     *
     * Pour chaque fait :
     * - `system` : `BAL` (block automatique lumineux), `BAPR` (block automatique à permissivité restreinte),
     *   `BM` (block manuel, toutes variantes), `CT` (cantonnement téléphonique), `TVM-300`, `TVM-430` ou `TVM`
     *   (niveau non précisé), `ETCS_L1`, `ETCS_L2` ou `ETCS` (niveau non précisé), `KVB` ;
     * - `role` : `block` (cantonnement, qui espace les trains), `cab` (signalisation en cabine, TVM et ETCS : à
     *   la fois cantonnement et contrôle de vitesse), `speed_control` (contrôle de vitesse : le KVB n'est
     *   **jamais** un cantonnement) ;
     * - `date` (`AAAA-MM-JJ`) et `date_precision` (`day`, `month` ou `year` : le jour ou le mois manquants
     *   sont complétés par `01`, ne les lisez pas comme une date exacte) ;
     * - `reliability` : `high` (jour ou mois donné) ou `medium` (année seule). Les faits douteux (date
     *   approximative « vers 1937 », plusieurs dates dans la même phrase, période « de 1968 à 1984 », article
     *   de plusieurs lignes) sont gardés en interne et jamais exposés ;
     * - `scope` : `whole_line` (l'article dit toute la ligne), `section` (portion nommée) ou `unspecified`
     *   (l'article ne dit pas) ; `section` donne alors les lieux tels qu'écrits (`from`, `to`) et les PK en
     *   mètres (`pk_start_m`, `pk_end_m`) quand l'article les cite, sinon null ;
     * - `source` : `provider` (`wikipedia`), `title` de l'article, `url` (permalien de la révision lue),
     *   `revision` et `excerpt`, une courte phrase de l'article (200 caractères au plus) pour relire le fait.
     *
     * Premier relevé, limité : les tableaux des articles ne sont pas lus, et un fait absent ne veut pas dire
     * qu'aucun système n'a été mis en service. Le texte des articles est sous CC BY-SA 4.0 : reproduisez la
     * mention du champ `attribution` ; seuls des faits sont repris. `provenance` donne le fournisseur et la
     * licence, comme les autres réponses.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-974000')]
    #[ApiResponse(200, "Mises en service de signalisation datées de la ligne (liste vide s'il n'y en a aucune).", examples: [[
        'line' => ['slug' => 'rfn-974000', 'code' => '974000', 'name' => 'Ligne de St-Cloud à St-Nom-la-Bretèche-Forêt-de-Marly'],
        'history' => [[
            'system' => 'BAL', 'role' => 'block', 'date' => '1962-06-01', 'date_precision' => 'month', 'reliability' => 'high',
            'scope' => 'section', 'section' => ['from' => 'Garches', 'to' => 'Saint-Nom-la-Bretèche', 'pk_start_m' => null, 'pk_end_m' => null],
            'source' => [
                'provider' => 'wikipedia', 'title' => 'Ligne de Saint-Cloud à Saint-Nom-la-Bretèche - Forêt-de-Marly',
                'url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Saint-Cloud_%C3%A0_Saint-Nom-la-Bret%C3%A8che_-_For%C3%AAt-de-Marly&oldid=240844633',
                'revision' => 240844633, 'excerpt' => 'En juin 1962, le block automatique lumineux est étendu de Garches à Saint-Nom-la-Bretèche.',
            ],
        ]],
        'attribution' => LineSignallingHistory::ATTRIBUTION,
        'provenance' => ['provider' => 'wikipedia', 'name' => 'Wikipédia (FR)', 'license' => 'CC BY-SA 4.0 pour les textes ; les faits (dates) ne sont pas protégés', 'license_status' => 'verified'],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function history(Line $line): JsonResponse
    {
        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'history' => $this->history->facts($line),
            'attribution' => LineSignallingHistory::ATTRIBUTION,
            'provenance' => $this->history->provenance(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
