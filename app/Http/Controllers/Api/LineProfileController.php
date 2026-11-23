<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Profiles\LineProfiles;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineProfileController extends Controller
{
    /** Exemple réel (Saint-Gervais-les-Bains-Le Fayet - Vallorcine, calculé le 2026-11-21), listes raccourcies. */
    public const EXAMPLE = [
        'line' => ['slug' => 'rfn-896000', 'code' => '896000', 'name' => 'Ligne de St-Gervais-les-Bains-Le Fayet à Vallorcine (frontière)'],
        'basis' => 'reference',
        'era_year' => null,
        'step_m' => 100,
        'length_m' => 36643,
        'trace_length_m' => 36649,
        'covered_m' => 36643,
        'off_path_m' => 0,
        'pk_anchors' => 19,
        'summary' => [
            'min_altitude_m' => 578.3, 'max_altitude_m' => 1379.3, 'ascent_m' => 799, 'descent_m' => 247,
            'max_grade_permil' => 90.8, 'max_grade_from_m' => 4600, 'max_grade_to_m' => 5600, 'max_grade_direction' => 'up',
            'max_grade_withheld' => null,
            'climb_method' => ['method' => 'hysteresis', 'threshold_m' => 3.0, 'terrain_smoothing_m' => 1000.0],
        ],
        'rack' => null,
        'sourced_grade' => null,
        'sourced_grade_compared' => null,
        'points' => [[0, 578.9, -145], [100, 578.3, -45], [5200, 756.2, 5111], [10500, 958.6, 10425]],
        'structures' => [['from_m' => 700, 'to_m' => 900, 'kind' => 'bridge']],
        'altitude_sources' => ['bdtopo' => 339, 'rge_alti' => 5, 'rge_track' => 0, 'interpolated' => 24, 'missing' => 0],
        'gaps' => [],
        'branches' => [],
        'trace_holes' => ['count' => 0, 'length_m' => 0, 'share' => 0.0, 'ranges' => []],
        'terrain_ranges' => [['from_m' => 25900, 'to_m' => 26300]],
        'track_estimate_ranges' => [],
        'track_estimate' => null,
        'stations' => [
            ['slug' => 'st-gervais-les-bains-le-fayet', 'name' => 'St-Gervais-les-Bains-Le Fayet', 'distance_m' => 145, 'altitude_m' => 578.4, 'pk_m' => 0, 'status' => 'active', 'offset_m' => 17],
            ['slug' => 'chamonix-montenvers', 'name' => 'Chamonix-Montenvers', 'distance_m' => 19160, 'altitude_m' => 1037.1, 'pk_m' => null, 'status' => 'tourist', 'offset_m' => 68],
            ['slug' => 'vallorcine', 'name' => 'Vallorcine', 'distance_m' => 34138, 'altitude_m' => 1260.7, 'pk_m' => 34115, 'status' => 'active', 'offset_m' => 10],
        ],
        'computed_at' => '2026-11-21T03:35:49+01:00',
        'provenance' => [
            'provider' => 'ign',
            'name' => 'IGN (BD TOPO, BD ORTHO historique, RGE ALTI)',
            'license' => 'Licence Ouverte 2.0 (Etalab), données publiques de l\'IGN libres depuis le 1er janvier 2021',
            'license_status' => 'verified',
        ],
    ];

    /**
     * Profil en long d'une ligne.
     *
     * Altitude le long du tracé, comme le profil d'un livret de ligne, avec les gares placées
     * dessus et quelques chiffres : altitudes extrêmes, dénivelés cumulés, déclivité la plus forte.
     *
     * **Parcours** : le tracé de RÉFÉRENCE de la ligne entière, toutes époques (lignes fermées et
     * déposées comprises), assemblé de bout en bout le long de l'artère principale et orienté du
     * plus petit PK officiel vers le plus grand (`basis` = `reference` ; `era` et `era_year` pour un
     * profil calculé avant le 21/11/2026 sur le tracé d'une seule ère). Les morceaux séparés par un
     * trou du tracé sont tous gardés : la courbe s'interrompt dans le trou (`gaps`). Les épis, les
     * raccordements et les branches d'un réseau en arbre viennent ensuite, bout à bout, chacun
     * après un trou de longueur conventionnelle (`branches`, en distances ; 1 % de la ligne
     * principale, 500 m au moins). `points` donne `[distance_m, altitude_m, pk_m]` : distance en
     * mètres le long de ce parcours depuis son origine, trous compris ; altitude en mètres
     * (NGF-IGN69), arrondie au décimètre, `null` sans valeur ; PK estimé en mètres, calé sur les
     * PK officiels des gares (`pk_anchors` gares, interpolation linéaire, un mètre par mètre
     * au-delà des gares extrêmes), `null` sans gare de calage et sur les embranchements. Un point
     * tous les `step_m` mètres (100 à 250 m selon la longueur). Longueurs : `trace_length_m`
     * (référentiel), `covered_m` (parcours hors trous), `off_path_m` (bouts du référentiel de
     * moins de 100 m laissés hors du parcours). Le détail de chaque point (provenance, position,
     * ouvrage) est dans `GET /api/v1/lines/{line}/profile/points`.
     *
     * **Altitudes**, de l'IGN, sous Licence Ouverte 2.0 (mention « IGN ») : altitude de la voie
     * dans la BD TOPO (géométrie 3D, tronçon à moins de 20 m du parcours) ; à défaut, altitude du
     * terrain (RGE ALTI, service de calcul altimétrique de la Géoplateforme) ; dans un tunnel ou
     * sur un pont sans altitude, interpolation linéaire entre les deux têtes de l'ouvrage ;
     * rien hors de France. Les points qui s'écartent de plus de 20 m de leurs voisins sont
     * interpolés. `altitude_sources` compte les points de chaque origine (`bdtopo`, `rge_alti`,
     * `rge_track`, `interpolated`, `missing`).
     *
     * **Voie estimée d'après le terrain** (`rge_track`, profils calculés avec cette option) : là où
     * la BD TOPO ne donne pas l'altitude de la voie, la plateforme est repérée sur une coupe en
     * travers du RGE ALTI (bande plate de 4 à 14 m la plus proche du tracé, sur ±15 m : crête de
     * remblai ou fond de tranchée), puis lissée le long de la ligne. `track_estimate_ranges` donne
     * ces parties, `track_estimate` la méthode et l'erreur mesurée (null sans point estimé) :
     * `accuracy` = écarts avec le MNT LiDAR HD de l'IGN sous la voie (2 642 points de voies en
     * service, au pas de 100 m, hors ouvrages) selon l'écart entre le tracé et la voie
     * (`trace_offset_m` = 5, 10 ou 20 m) : médiane, 90e et 99e centiles en mètres, parts sous 1 m
     * et 2 m (0 à 1), 90e centile de l'écart de pente sur 1 km en ‰ et part à moins de 1 ‰ ;
     * `bridges` = ponts interpolés entre les culées, comparés à la BD TOPO. Un tracé ancien peut
     * s'écarter de la voie de 10 à 30 m : l'erreur est alors celle des lignes 10 et 20 m. La
     * déclivité la plus forte n'est pas publiée sur ces points, comme sur le terrain.
     *
     * **Chiffres** (`summary`) : `ascent_m` et `descent_m` = dénivelés positif et négatif cumulés
     * dans le sens du parcours. Méthode (`climb_method`) : les altitudes du terrain (RGE ALTI) sont
     * d'abord remplacées par leur moyenne sur 1 km (`terrain_smoothing_m`), puisque le terrain sous
     * un tracé ancien suit les talus et les versants voisins ; celles de la voie restent telles
     * quelles ; puis les variations de moins de 3 m (`threshold_m`) ne sont pas comptées
     * (hystérésis, `method` = `hysteresis`). Mesuré sur 40 lignes dont on connaît l'altitude de la
     * voie : sans la moyenne, le terrain donnait deux à trois fois leurs dénivelés ;
     * `max_grade_permil` = déclivité la plus forte en ‰ (mètres par kilomètre), pente moyenne par
     * moindres carrés sur une fenêtre de 1 km, entre `max_grade_from_m` et `max_grade_to_m`, mesurée à
     * l'intérieur d'un même morceau (jamais à travers un trou ni vers un embranchement), hors fenêtres
     * dont plus du quart des points sont en tunnel ou interpolés ou dont moins des trois quarts sont
     * mesurés sur la voie (BD TOPO), et hors pentes de plus de 120 ‰ (artefacts). Sans fenêtre
     * admise, la déclivité n'est pas publiée : `max_grade_permil`, ses bornes et sa direction sont
     * nuls, et `max_grade_withheld` en donne le motif (`terrain_only` : altitude du terrain
     * seulement, ligne sans voie dans la BD TOPO ; `tunnel` ; `interpolated` ; `rack` : ligne à
     * crémaillère) ; le motif est nul aussi quand aucune portion
     * de 1 km n'est exploitable (parcours trop court) : rien n'est affirmé ;
     * `max_grade_direction` : `up` (rampe dans le sens du parcours) ou `down` (pente). Valeurs
     * indicatives : quelques ‰ d'écart avec la déclivité officielle sont courants.
     *
     * **Crémaillère** (`rack`) : `null` pour une ligne à adhérence. Pour une ligne à crémaillère
     * (liste versionnée `database/data/rack_railways.php`, d'après Wikipédia), la déclivité mesurée
     * n'est pas publiée (`max_grade_withheld` = `rack`) et `rack` donne `system` (`strub`, `abt`,
     * `riggenbach`), `extent` (`full` : tout le parcours ; `partial` : une partie ; `null` si la
     * source ne le dit pas), `max_grade_permil` (déclivité la plus forte publiée par la source, en
     * ‰), `braking_only` (vrai si la crémaillère ne servait qu'au freinage) et `source` (`provider`
     * = `wikipedia`, `title`, `url` = permalien de la révision lue, `license` = `CC BY-SA 4.0`).
     *
     * **Déclivité d'une source écrite** (`sourced_grade`) : quand la déclivité mesurée n'est pas
     * publiée pour cause de terrain seul, de tunnel ou de points interpolés, et qu'une source écrite
     * la donne, `sourced_grade` donne `max_grade_permil` (déclivité la plus forte selon la source,
     * en ‰, sans position ni sens), `covered_share` (part de la ligne décrite par la source, de 0
     * à 1 ; `null` quand la source porte sur toute la ligne) et `source` (`provider`, `title`,
     * `url`, `license`, `attribution` : mention due à l'auteur). Deux fournisseurs :
     * `wikipedia` (`database/data/sourced_grades.php` : champ « pente » de l'infobox ou texte de
     * l'article de Wikipédia ; `url` = permalien de la révision lue, `license` = `CC BY-SA 4.0`,
     * `attribution` = `Wikipédia`) et `sncf` (`database/data/sourced_grades_sncf.php`, commande
     * `vff:import-sncf-grades` : segment le plus raide du jeu « Caractéristique des voies et
     * déclivité » ; `url` = page du jeu filtrée sur la ligne, `license` = `ODbL 1.0`,
     * `attribution` = `SNCF Réseau`). Le jeu SNCF ne décrit qu'une partie des voies : il n'est repris
     * que pour une ligne couverte à 10 % au moins, et une valeur au-dessus de 60 ‰ hors ligne de
     * montagne ou de plus de 3 fois la déclivité mesurée est écartée. `null` sinon : une déclivité
     * mesurée publiée n'est jamais remplacée, une ligne à crémaillère a `rack`. Contrôle sur 235
     * lignes de Wikipédia mesurées sur la voie : 141 à 2 ‰ près, 210 à 5 ‰ près (l'infobox donne
     * souvent la pente d'un point, la mesure celle d'une rampe d'1 km).
     *
     * **Source à côté de la mesure** (`sourced_grade_compared`) : quand la déclivité mesurée est
     * publiée et qu'une source écrite en donne une (mêmes fichiers et même forme que
     * `sourced_grade`), elle est servie à côté, jamais à sa place, avec `difference_permil` = mesure
     * moins source, en ‰ arrondi au dixième (positif : la mesure est plus forte). `null` sinon.
     *
     * **Continuité du tracé** (`trace_holes`) : vrais trous du tracé de référence, sans les trous
     * conventionnels posés devant chaque embranchement : `count`, `length_m` (longueur cumulée, à vol
     * d'oiseau entre les bords de chaque trou : la voie disparue était au moins aussi longue), `share`
     * (part des trous dans le parcours connu plus les trous, de 0 à 1 ; `null` pour un profil
     * antérieur au 21/11/2026) et `ranges` (bornes de chaque trou, en distances). Les chiffres de
     * `summary` ne portent que sur la partie connue.
     *
     * **Autres listes** : `structures` = tunnels (`tunnel`) et ponts (`bridge`) de la BD TOPO sous
     * le parcours, en distances ; `gaps` = trous du tracé, sans altitude ; `terrain_ranges` = parties
     * dont l'altitude est celle du terrain (RGE ALTI) et non de la voie ; `track_estimate_ranges` =
     * parties à l'altitude de la voie estimée d'après le terrain ; `stations` = toutes les
     * gares rattachées à la ligne (fermées comprises : `status` = `declassified`), projetées sur le
     * parcours, avec leur distance, leur altitude (null dans un trou), leur PK officiel (`pk_m`,
     * en mètres, null s'il est inconnu ou seulement estimé) et leur écart au parcours (`offset_m`).
     *
     * Les profils sont calculés à l'avance, ligne par ligne : une ligne sans profil répond 404 avec
     * `error` = `profile_not_computed` et le motif dans `reason` : `no_trace` (aucun tracé connu de la
     * ligne), `outside_france` (tracé entièrement hors de France, où l'IGN ne donne pas d'altitude) ou
     * `not_computed` (calcul possible, pas encore fait).
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     basis: string, era_year: int|null, step_m: int, length_m: int, trace_length_m: int,
     *     covered_m: int|null, off_path_m: int|null, pk_anchors: int,
     *     summary: array{min_altitude_m: float, max_altitude_m: float, ascent_m: float, descent_m: float,
     *         max_grade_permil: float|null, max_grade_from_m: int|null, max_grade_to_m: int|null, max_grade_direction: string|null,
     *         max_grade_withheld: string|null,
     *         climb_method: array{method: string, threshold_m: float, terrain_smoothing_m: float}},
     *     rack: array{system: string, extent: string|null, max_grade_permil: int, braking_only: bool,
     *         source: array{provider: string, title: string, url: string, license: string}}|null,
     *     sourced_grade: array{max_grade_permil: float, covered_share: float|null, source: array{provider: string, title: string, url: string, license: string, attribution: string}}|null,
     *     sourced_grade_compared: array{max_grade_permil: float, covered_share: float|null, difference_permil: float, source: array{provider: string, title: string, url: string, license: string, attribution: string}}|null,
     *     points: array<array{0: int, 1: float|null, 2: int|null}>,
     *     structures: array<array{from_m: int, to_m: int, kind: string}>,
     *     altitude_sources: array{bdtopo: int, rge_alti: int, rge_track: int, interpolated: int, missing: int},
     *     gaps: array<array{from_m: int, to_m: int}>,
     *     branches: array<array{from_m: int, to_m: int}>,
     *     trace_holes: array{count: int, length_m: int, share: float|null, ranges: array<array{from_m: int, to_m: int}>},
     *     terrain_ranges: array<array{from_m: int, to_m: int}>,
     *     track_estimate_ranges: array<array{from_m: int, to_m: int}>,
     *     track_estimate: array{method: string, calibrated_on: string, reference: string, step_m: int,
     *         accuracy: array<array{trace_offset_m: int, points: int, median_m: float, p90_m: float, p99_m: float,
     *             under_1m_share: float, under_2m_share: float, grade_1km_p90_permil: float, grade_1km_within_1_permil_share: float}>,
     *         bridges: array{points: int, median_m: float, p90_m: float}}|null,
     *     stations: array<array{slug: string, name: string, distance_m: int, altitude_m: float|null, pk_m: int|null, status: string, offset_m: int}>,
     *     computed_at: string,
     *     provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-896000')]
    #[QueryParameter('max_points', 'Nombre de points au plus (10 à 5 000) : profil allégé, points régulièrement espacés plus les deux bouts, le point le plus bas, le plus haut et les bords des trous. Par défaut : tous les points.', type: 'int', example: 200)]
    #[ApiResponse(200, 'Profil en long de la ligne.', examples: [self::EXAMPLE])]
    #[ApiResponse(404, 'Ligne inconnue, ou pas de profil pour cette ligne (`error` = `profile_not_computed`, motif dans `reason` : `no_trace`, `outside_france` ou `not_computed`).', examples: [['error' => 'profile_not_computed', 'reason' => 'outside_france', 'message' => 'Profil en long impossible : le tracé de cette ligne est entièrement hors de France, où l\'IGN ne donne pas d\'altitude.']])]
    #[ApiResponse(422, 'Paramètre invalide (`max_points` hors de 10 à 5 000).', examples: [['message' => 'The max points field must be between 10 and 5000.', 'errors' => ['max_points' => ['The max points field must be between 10 and 5000.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Line $line, Request $request, LineProfiles $profiles): JsonResponse
    {
        $request->validate(['max_points' => 'nullable|integer|between:10,5000']);

        $profile = $profiles->forLine($line, $request->filled('max_points') ? $request->integer('max_points') : null);
        if ($profile === null) {
            return self::notComputed($line, $profiles);
        }

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$profile,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /** Exemple réel de `points` (Argentan - Granville, calculé le 2026-11-21), autour de Flers. */
    public const POINTS_EXAMPLE = [
        'line' => ['slug' => 'rfn-405000', 'code' => '405000', 'name' => 'Ligne d\'Argentan à Granville'],
        'count' => 2,
        'points' => [
            ['seq' => 457, 'distance_m' => 45700, 'pk_m' => 45738, 'altitude_m' => 188.2, 'source' => 'bdtopo', 'interpolated' => false, 'structure' => null, 'piece' => 0, 'branch' => null, 'lat' => 48.744303, 'lng' => -0.572941, 'position_withheld' => null],
            ['seq' => 458, 'distance_m' => 45800, 'pk_m' => 45837, 'altitude_m' => 188.0, 'source' => 'bdtopo', 'interpolated' => false, 'structure' => null, 'piece' => 0, 'branch' => null, 'lat' => 48.744912, 'lng' => -0.573945, 'position_withheld' => null],
        ],
        'provenance' => ['provider' => 'ign', 'name' => 'IGN (BD TOPO, BD ORTHO historique, RGE ALTI)', 'license' => 'Licence Ouverte 2.0 (Etalab), données publiques de l\'IGN libres depuis le 1er janvier 2021', 'license_status' => 'verified'],
    ];

    /**
     * Points du profil en long, un par un.
     *
     * Le référentiel d'altitude de la ligne, tel qu'il est enregistré : pour chaque point, son rang
     * (`seq`), sa distance le long du parcours (`distance_m`, trous compris), son PK estimé
     * (`pk_m`, calé sur les PK officiels des gares, null sans calage ou sur un embranchement),
     * son altitude (`altitude_m`, mètres NGF-IGN69, null sans valeur), la provenance de la mesure
     * (`source` : `bdtopo` = altitude de la voie dans la BD TOPO, `rge_alti` = altitude du terrain,
     * `rge_track` = altitude de la voie estimée d'après le terrain, erreur dans `track_estimate` du profil ;
     * null si l'altitude est interpolée, absente, ou d'un profil calculé avant le 21/11/2026),
     * `interpolated` (altitude interpolée entre deux mesures : tunnel, pont, point aberrant, petit
     * trou), l'ouvrage sous le point (`structure` : `tunnel`, `bridge` ou null), le morceau continu
     * (`piece`, un nouveau après chaque trou), l'embranchement (`branch` : 1, 2… ou null sur la
     * ligne principale) et sa position WGS 84 (`lat`, `lng`, sur le tracé de référence).
     *
     * **Droits des tracés** : comme pour les autres géométries de l'API, la position n'est donnée
     * que si le tracé sous le point vient d'une source dont la licence permet la redistribution.
     * Sinon `lat` et `lng` sont nuls et `position_withheld` en donne le motif : `licence_not_open`
     * (licence non ouverte, par exemple un tracé tous droits réservés) ou `unknown_source` (source
     * inconnue du registre). Distance, PK et altitude restent donnés.
     *
     * Filtres : `pk_from_m` et `pk_to_m` (bornes incluses, points sans PK exclus dès qu'une borne
     * est donnée) ; `max_points` allège la liste comme pour le profil. Licence : IGN, Licence
     * Ouverte 2.0 (mention « IGN »).
     *
     * @response array{line: array{slug: string, code: ?string, name: string}, count: int, points: array<array{seq: int, distance_m: int, pk_m: int|null, altitude_m: float|null, source: string|null, interpolated: bool, structure: string|null, piece: int, branch: int|null, lat: float|null, lng: float|null, position_withheld: string|null}>, provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}}
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-405000')]
    #[QueryParameter('pk_from_m', 'PK de début en mètres (borne incluse).', type: 'int', example: 45700)]
    #[QueryParameter('pk_to_m', 'PK de fin en mètres (borne incluse).', type: 'int', example: 45900)]
    #[QueryParameter('max_points', 'Nombre de points au plus (10 à 5 000). Par défaut : tous.', type: 'int', example: 500)]
    #[ApiResponse(200, 'Points du profil.', examples: [self::POINTS_EXAMPLE])]
    #[ApiResponse(404, 'Ligne inconnue, ou pas de profil (`error` = `profile_not_computed`, motif dans `reason` : `no_trace`, `outside_france` ou `not_computed`).', examples: [['error' => 'profile_not_computed', 'reason' => 'not_computed', 'message' => 'Le profil en long de cette ligne n\'est pas encore calculé.']])]
    #[ApiResponse(422, 'Paramètre invalide (`pk_to_m` inférieur à `pk_from_m`, `max_points` hors de 10 à 5 000).', examples: [['message' => 'The pk to m field must be greater than or equal to 15000.', 'errors' => ['pk_to_m' => ['The pk to m field must be greater than or equal to 15000.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function points(Line $line, Request $request, LineProfiles $profiles): JsonResponse
    {
        $request->validate([
            'pk_from_m' => 'nullable|integer|between:-100000,5000000',
            'pk_to_m' => 'nullable|integer|between:-100000,5000000'.($request->filled('pk_from_m') ? '|gte:pk_from_m' : ''),
            'max_points' => 'nullable|integer|between:10,5000',
        ]);
        $profile = $profiles->find($line);
        if ($profile === null) {
            return self::notComputed($line, $profiles);
        }
        $points = $profiles->detailedPoints(
            $profile,
            $request->filled('pk_from_m') ? $request->integer('pk_from_m') : null,
            $request->filled('pk_to_m') ? $request->integer('pk_to_m') : null,
            $request->filled('max_points') ? $request->integer('max_points') : null,
        );

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'count' => count($points),
            'points' => $points,
            'provenance' => $profiles->present($profile, 10)['provenance'],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Altitude de la ligne à un point kilométrique.
     *
     * Altitude du référentiel au PK demandé, interpolée entre les deux points du profil qui
     * l'encadrent dans le même morceau : `altitude_m` (mètres NGF-IGN69, au décimètre),
     * `distance_m` (distance le long du parcours), `interpolated` (vrai si l'un des deux points
     * est lui-même interpolé), `sources` (provenance des deux mesures), `structure` (tunnel ou pont
     * sous le point) et position (`lat`, `lng`), nulle avec son motif dans `position_withheld`
     * (`licence_not_open`, `unknown_source`) quand le tracé de l'un des deux points n'est pas
     * redistribuable (règle des droits des tracés de l'API). Le PK des points est estimé à partir des PK
     * officiels des gares : il peut s'écarter de quelques dizaines de mètres du PK réel.
     *
     * 404 `profile_not_computed` sans profil ; 404 `altitude_unknown` hors du profil, dans un trou
     * ou sur une ligne sans gare de calage du PK.
     *
     * @response array{line: array{slug: string, code: ?string, name: string}, pk_m: int, altitude_m: float, distance_m: int, interpolated: bool, sources: array<string>, structure: string|null, lat: float|null, lng: float|null, position_withheld: string|null, provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}}
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-405000')]
    #[QueryParameter('pk_m', 'PK en mètres (obligatoire).', type: 'int', required: true, example: 45764)]
    #[ApiResponse(200, 'Altitude au PK (exemple réel : gare de Flers, PK 45+764, calculé le 2026-11-21).', examples: [['line' => ['slug' => 'rfn-405000', 'code' => '405000', 'name' => 'Ligne d\'Argentan à Granville'], 'pk_m' => 45764, 'altitude_m' => 188.1, 'distance_m' => 45726, 'interpolated' => false, 'sources' => ['bdtopo'], 'structure' => null, 'lat' => 48.744463, 'lng' => -0.573205, 'position_withheld' => null, 'provenance' => ['provider' => 'ign', 'name' => 'IGN (BD TOPO, BD ORTHO historique, RGE ALTI)', 'license' => 'Licence Ouverte 2.0 (Etalab), données publiques de l\'IGN libres depuis le 1er janvier 2021', 'license_status' => 'verified']]])]
    #[ApiResponse(404, 'Ligne inconnue, profil pas encore calculé (`profile_not_computed`) ou altitude inconnue à ce PK (`altitude_unknown`).', examples: [['error' => 'altitude_unknown', 'message' => 'Altitude inconnue à ce PK (hors du profil, dans un trou du tracé, ou PK non calé).']])]
    #[ApiResponse(422, 'Paramètre `pk_m` absent ou invalide.', examples: [['message' => 'The pk m field is required.', 'errors' => ['pk_m' => ['The pk m field is required.']]]])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function altitude(Line $line, Request $request, LineProfiles $profiles): JsonResponse
    {
        $request->validate(['pk_m' => 'required|integer|between:-100000,5000000']);
        $profile = $profiles->find($line);
        if ($profile === null) {
            return self::notComputed($line, $profiles);
        }
        $at = $profiles->altitudeAtPk($profile, $request->integer('pk_m'));
        if ($at === null) {
            return response()->json([
                'error' => 'altitude_unknown',
                'message' => 'Altitude inconnue à ce PK (hors du profil, dans un trou du tracé, ou PK non calé).',
            ], 404);
        }

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            ...$at,
            'provenance' => $profiles->present($profile, 10)['provenance'],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /** 404 d'une ligne sans profil, avec son motif (LineProfiles::unavailable). */
    private static function notComputed(Line $line, LineProfiles $profiles): JsonResponse
    {
        $reason = $profiles->unavailable($line)['reason'] ?? 'not_computed';

        return response()->json([
            'error' => 'profile_not_computed',
            'reason' => $reason,
            'message' => match ($reason) {
                'no_trace' => 'Profil en long impossible : aucun tracé de cette ligne n\'est connu.',
                'outside_france' => 'Profil en long impossible : le tracé de cette ligne est entièrement hors de France, où l\'IGN ne donne pas d\'altitude.',
                default => 'Le profil en long de cette ligne n\'est pas encore calculé.',
            },
        ], 404);
    }
}
