<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GtfsExporter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('Exports GTFS', 'Le réseau d\'une ère au format GTFS Static, pour les outils de transport.', weight: 4)]
class GtfsController extends Controller
{
    public function __construct(protected GtfsExporter $exporter) {}

    /**
     * Réseau d'une ère au format GTFS.
     *
     * Archive ZIP conforme à la spécification [GTFS Static](https://gtfs.org/schedule/reference/),
     * générée à la demande pour l'ère choisie (instantané au 31/12 de l'année ;
     * `2030` = réseau d'aujourd'hui). Fichiers : `agency.txt`, `routes.txt`
     * (une route par ligne ; `route_type` 2 pour une ligne voyageurs ou mixte,
     * 100 pour une ligne fret), `stops.txt` (gares desservies à l'ère : statuts
     * `active`, `tourist`, `no_commercial`), `trips.txt` (un trajet par ligne
     * ayant au moins deux gares desservies, de la première à la dernière),
     * `stop_times.txt`, `shapes.txt` (tracé de la ligne, orienté dans le sens du
     * trajet ; `shape_dist_traveled` en mètres le long du tracé, strictement
     * croissant ; quand le tracé s'arrête au début d'une voie partagée avec une
     * autre ligne, la forme suit cette voie jusqu'à la gare terminus, 2,5 km au
     * plus, sans jamais tirer de segment droit), `calendar.txt`, `feed_info.txt` (avec le contact du flux) et
     * `attributions.txt` (VFF, OpenStreetMap et SNCF, licence ODbL 1.0).
     *
     * **Horaires synthétiques** : VFF décrit l'infrastructure, pas les
     * services. Les heures de `stop_times.txt` sont calculées à 60 km/h le long
     * du tracé (à vol d'oiseau entre gares quand le trajet n'a pas de distance) :
     * elles ne servent qu'à ordonner les arrêts ; les données spatiales (gares,
     * tracés) sont celles de VFF. `shape_dist_traveled` des arrêts est la
     * distance de la gare le long du tracé (même unité que `shapes.txt`, et non
     * le point kilométrique) ; il est vide pour tout le trajet quand la ligne n'a
     * pas de forme ou que l'ordre des gares ne suit pas le tracé.
     * `feed_info.txt` couvre l'année de l'ère (du 01/01 au 31/12). Données
     * ouvertes : citez « VFF - Voies Ferrées de France » et respectez les
     * licences des sources (ODbL pour la part issue d'OpenStreetMap et de la
     * SNCF).
     *
     * **Droits des tracés** : `shapes.txt` ne contient que les formes des
     * lignes dont tous les tronçons exploités à l'ère viennent d'une source à
     * licence ouverte vérifiée. Quand une partie du tracé vient d'une source
     * dont la licence n'autorise pas la redistribution, la ligne n'a pas de
     * forme : son trajet (`trips.txt`) garde ses arrêts, sans `shape_id`. Une
     * voie partagée n'est suivie que si ses tronçons sont eux aussi
     * redistribuables ; sinon la forme s'arrête au bout du tracé de la ligne. Le
     * GeoJSON de l'ère (`/eras/{year}/lines.geojson`) dit quels tronçons sont
     * concernés (`geometry_withheld`, résumé `withheld`).
     *
     * @param  int  $year  Année de l'ère (décennie de 1830 à 2030 ; 2030 = aujourd'hui).
     */
    #[PathParameter('year', "Année de l'ère : une décennie de 1830 à 2030 (2030 = réseau actuel).", example: 2030)]
    #[ApiResponse(200, 'Archive ZIP GTFS (fichier `vff-gtfs-{year}.zip`).', mediaType: 'application/zip', type: 'string', format: 'binary')]
    #[ApiResponse(404, "Ère inconnue (année qui n'est pas une décennie entre 1830 et 2030).")]
    public function export(int $year): BinaryFileResponse
    {
        $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vff-gtfs-'.$year.'-'.uniqid().'.zip';
        $this->exporter->exportEra($year, $tmpPath);

        return response()->download($tmpPath, "vff-gtfs-{$year}.zip", [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'public, max-age=3600',
        ])->deleteFileAfterSend();
    }
}
