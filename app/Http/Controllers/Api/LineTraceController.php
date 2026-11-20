<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mcp\Support\Attribution;
use App\Models\Line;
use App\Services\TraceExport\GpxWriter;
use App\Services\TraceExport\KmlWriter;
use App\Services\TraceExport\LineTrace;
use App\Services\TraceExport\LineTraceExport;
use App\Services\TraceExport\TraceCaption;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

#[Group('Lignes', "L'annuaire des lignes et chaque ligne en détail : fiche complète, tronçons par ère, chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineTraceController extends Controller
{
    private const CACHE = 'public, max-age=300';

    public function __construct(
        private readonly LineTraceExport $export,
        private readonly Attribution $attribution,
    ) {}

    /**
     * Tracé d'une ligne en GPX.
     *
     * Le tracé de la ligne au 31/12 de l'ère (l'ère courante par défaut : aujourd'hui) au format
     * [GPX 1.1](https://www.topografix.com/GPX/1/1/), pour un GPS, une application de randonnée
     * ou un logiciel de cartographie : une seule trace (`trk`) au nom de la ligne, et un segment de
     * trace (`trkseg`) par tronçon, dans l'ordre des points kilométriques. Coordonnées WGS 84.
     * Le fichier est servi en pièce jointe (`Content-Disposition: attachment`), nommé
     * `{ligne}-{ère}.gpx` (par exemple `rfn-447000-1930.gpx`).
     *
     * **Ce que le fichier dit de lui-même.** Les métadonnées donnent le nom de la ligne, l'ère,
     * l'adresse de sa fiche sur VFF, le droit d'auteur de la base (ODbL 1.0) et, pour chaque source
     * dont vient la géométrie, sa mention et sa licence (par exemple « © contributeurs
     * OpenStreetMap, ODbL 1.0 »). Reprenez ces mentions si vous publiez le tracé.
     *
     * **Droits.** Seule une géométrie dont la source a une licence ouverte écrite et vérifiée (OpenStreetMap,
     * SNCF Réseau, IGN, OpenHistoricalMap, contributions validées) est exportée. Les tronçons d'une
     * source non commerciale, sous accord oral, de statut incertain ou non déclarée ne le sont jamais ;
     * le fichier dit combien de tronçons ont été laissés de côté. Si aucun tronçon n'est exportable,
     * la réponse est un HTTP 451 (`error` = `geometry_withheld`) qui détaille les sources écartées.
     * Une ligne sans aucun tracé à cette ère donne un HTTP 404 (`error` = `no_geometry`).
     *
     * Même contenu que `/lines/{line}/geojson` et `/lines/{line}/kml`.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-447000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante (aujourd'hui). Une année sans ère (`1935`) donne HTTP 404.", type: 'int', example: 1930)]
    #[QueryParameter('lang', 'Langue des textes écrits dans le fichier (nom, description, mentions des sources) : `fr` (défaut), `en` ou `de`.', type: 'string', example: 'fr')]
    #[ApiResponse(200, 'Fichier GPX 1.1 (`rfn-447000-1930.gpx`).', mediaType: 'application/gpx+xml; charset=UTF-8', type: 'string', format: 'binary')]
    #[ApiResponse(404, 'Ligne ou ère inconnue, ou ligne sans tracé à cette ère (`error` = `no_geometry`).', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, "`era` n'est pas un entier ou `lang` n'est pas `fr`, `en` ou `de`.", examples: [ErrorExamples::ERA_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    #[ApiResponse(451, "Le tracé existe mais sa source n'autorise pas la redistribution : rien n'est exporté (`error` = `geometry_withheld`, avec le détail des sources écartées dans `withheld`).", examples: [ErrorExamples::GEOMETRY_WITHHELD])]
    public function gpx(Request $request, Line $line, GpxWriter $writer): Response|JsonResponse
    {
        return $this->file($request, $line, 'gpx', GpxWriter::CONTENT_TYPE, $writer->write(...));
    }

    /**
     * Tracé d'une ligne en KML.
     *
     * Le tracé de la ligne au 31/12 de l'ère (l'ère courante par défaut : aujourd'hui) au format
     * [KML 2.2](https://www.ogc.org/standards/kml/) de l'OGC, pour Google Earth, QGIS ou Organic Maps :
     * un `Document` au nom de la ligne et un `Placemark` par tronçon, avec un `LineString`
     * (WGS 84, longitude puis latitude), dans l'ordre des points kilométriques ; la description
     * de chaque tronçon donne ses PK, son état et sa source. Le fichier est servi en pièce jointe,
     * nommé `{ligne}-{ère}.kml`.
     *
     * Le document porte le nom de la ligne, l'ère, l'adresse de sa fiche (`atom:link`), le droit
     * d'auteur de la base (ODbL 1.0) et la mention et la licence de chaque source de géométrie.
     * Mêmes règles de droits que pour le GPX : une géométrie dont la source n'autorise pas la
     * redistribution n'est pas exportée, et la réponse le dit (HTTP 451 si rien n'est exportable).
     *
     * Même contenu que `/lines/{line}/geojson` et `/lines/{line}/gpx`.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-447000')]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante (aujourd'hui). Une année sans ère (`1935`) donne HTTP 404.", type: 'int', example: 1930)]
    #[QueryParameter('lang', 'Langue des textes écrits dans le fichier (nom, description, mentions des sources) : `fr` (défaut), `en` ou `de`.', type: 'string', example: 'fr')]
    #[ApiResponse(200, 'Fichier KML 2.2 (`rfn-447000-1930.kml`).', mediaType: 'application/vnd.google-earth.kml+xml; charset=UTF-8', type: 'string', format: 'binary')]
    #[ApiResponse(404, 'Ligne ou ère inconnue, ou ligne sans tracé à cette ère (`error` = `no_geometry`).', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(422, "`era` n'est pas un entier ou `lang` n'est pas `fr`, `en` ou `de`.", examples: [ErrorExamples::ERA_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    #[ApiResponse(451, "Le tracé existe mais sa source n'autorise pas la redistribution : rien n'est exporté (`error` = `geometry_withheld`, avec le détail des sources écartées dans `withheld`).", examples: [ErrorExamples::GEOMETRY_WITHHELD])]
    public function kml(Request $request, Line $line, KmlWriter $writer): Response|JsonResponse
    {
        return $this->file($request, $line, 'kml', KmlWriter::CONTENT_TYPE, $writer->write(...));
    }

    /**
     * Construit la réponse d'un export : le fichier, ou l'erreur qui dit pourquoi il n'y en a pas.
     *
     * @param  callable(LineTrace, TraceCaption): string  $write
     */
    private function file(Request $request, Line $line, string $extension, string $contentType, callable $write): Response|JsonResponse
    {
        $input = $request->validate([
            'era' => ['nullable', 'integer'],
            'lang' => ['nullable', 'in:fr,en,de'],
        ]);
        $lang = $input['lang'] ?? 'fr';
        $trace = $this->export->trace($line, $this->export->era($input['era'] ?? null));

        if ($trace->segmentCount === 0) {
            return response()->json([
                'error' => 'no_geometry',
                'message' => "The line has no route in era {$trace->era->year}.",
            ], 404);
        }
        if ($trace->parts === []) {
            return response()->json([
                'error' => 'geometry_withheld',
                'message' => 'The route exists but its sources do not allow redistribution, so nothing is exported.',
                'withheld' => $trace->withheld,
            ], 451, ['Cache-Control' => self::CACHE]);
        }

        $filename = "{$line->slug}-{$trace->era->year}.{$extension}";

        return response($write($trace, TraceCaption::make($trace, $this->attribution, $lang)), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'Cache-Control' => self::CACHE,
        ]);
    }
}
