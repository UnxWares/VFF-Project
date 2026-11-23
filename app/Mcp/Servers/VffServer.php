<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CompareRegionErasPrompt;
use App\Mcp\Prompts\PresentLinePrompt;
use App\Mcp\Prompts\StationStoryPrompt;
use App\Mcp\Resources\LicencesResource;
use App\Mcp\Resources\LineSheetResource;
use App\Mcp\Tools\AccidentSheetTool;
use App\Mcp\Tools\AccidentsTool;
use App\Mcp\Tools\CompareErasTool;
use App\Mcp\Tools\CompareLineErasTool;
use App\Mcp\Tools\HistoricalRouteTool;
use App\Mcp\Tools\JournalTool;
use App\Mcp\Tools\LevelCrossingsTool;
use App\Mcp\Tools\LineArchiveRecordsTool;
use App\Mcp\Tools\LineArchivesTool;
use App\Mcp\Tools\LineAuditTool;
use App\Mcp\Tools\LineEventsTool;
use App\Mcp\Tools\LineGreenwaysTool;
use App\Mcp\Tools\LineInfrastructureTool;
use App\Mcp\Tools\LineMaterialsTool;
use App\Mcp\Tools\LineProfileTool;
use App\Mcp\Tools\LineSheetTool;
use App\Mcp\Tools\LineStopsTool;
use App\Mcp\Tools\LineStoriesTool;
use App\Mcp\Tools\ListDepartmentsTool;
use App\Mcp\Tools\ListLinesTool;
use App\Mcp\Tools\ListStationsTool;
use App\Mcp\Tools\LocateOnLineTool;
use App\Mcp\Tools\MaterialSheetTool;
use App\Mcp\Tools\MaterialsTool;
use App\Mcp\Tools\MediaTool;
use App\Mcp\Tools\NetworkAtEraTool;
use App\Mcp\Tools\NetworkEventsTool;
use App\Mcp\Tools\NetworkInAreaTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\SegmentAuditsTool;
use App\Mcp\Tools\SignallingHistoryTool;
use App\Mcp\Tools\SourcesTool;
use App\Mcp\Tools\StationAerialTool;
use App\Mcp\Tools\StationAuditsTool;
use App\Mcp\Tools\StationSheetTool;
use App\Mcp\Tools\WhatWasHereTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Serveur MCP public de VFF, en lecture seule (docs/MCP.md) : HTTP « streamable » sur POST /mcp
 * (routes/ai.php, même limite de débit que l'API publique) et stdio par `php artisan mcp:start vff`.
 */
#[Name('VFF - Voies Ferrées de France')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
VFF (Voies Ferrées de France, vff-project.org, édité par UnxWares) est une encyclopédie des lignes de chemin de fer françaises, actuelles et disparues : lignes, gares, phases datées, signalisation, électrification, itinéraires historiques, avec la source de chaque fait. Serveur en lecture seule.
Méthode : commencez par `search` pour obtenir l'identifiant (slug) d'une ligne ou d'une gare, puis `get_line`, `line_events`, `get_station` ; `network_at_era` pour l'état du réseau à une époque ; `historical_route` pour un trajet à une date ; `sources_and_licences` pour les licences.
Listes et questions transversales : `list_lines` (annuaire des lignes : réseau, état, trafic, département, époque), `list_stations` (annuaire des gares : statut, département, époque), `network_events` (événements de toutes les lignes : par département, type, période), `media` (photos, cartes postales, plans d'une ligne, d'une gare, d'une série de matériel ou d'un événement, avec auteur et licence : affichez toujours l'attribution du média avec l'image). Ces outils paginent par curseur : repassez `next_cursor` dans `cursor`.
Accidents : `accidents` (accidents et événements graves du rail, filtrés par ligne, période, nature, département, bilan ; pagination par curseur) puis `get_accident` (fiche avec les lignes rattachées, les sources lues et leur licence, les points à vérifier ; une fiche « à vérifier » ne se présente jamais comme sûre ; faits de Wikidata CC0, de Wikipédia CC BY-SA pour toute citation, du BEA-TT Licence Ouverte).
Passages à niveau : `level_crossings` (PN publiés par SNCF Réseau, ODbL : classe, PK, commune ; par ligne, groupe, commune ou emprise ; état actuel seulement, sans dates). Signalisation : `get_line` (le cantonnement espace les trains, le contrôle de vitesse KVB n'est jamais un cantonnement ; sur la LGV Est, la LGV Bretagne - Pays de la Loire et la LGV SEA, l'ETCS niveau 2 et la TVM sont installés ensemble et comptent tous les deux). Voies et radio : `line_infrastructure` (nombre de voies par portion de PK et liaison radio sol-train, GSM-R compris, aujourd'hui seulement ; SNCF Réseau, ODbL ; la voie banalisée ne dit pas le nombre de voies). Relief : `line_profile` (profil en long d'une ligne : altitudes, dénivelés, déclivité la plus forte, gares ; IGN, Licence Ouverte 2.0). Voies vertes : `line_greenways` (voies vertes OpenStreetMap sur l'ancienne emprise d'une ligne fermée ou disparue, longueur mesurée sur l'emprise ; OSM, ODbL). Archives : `line_archives` (documents numérisés dans Gallica dont le titre parle d'une ligne : projets, plans, mémoires, photographies ; métadonnées de la BnF sous Licence Ouverte et lien vers Gallica, jamais l'image) ; `line_archive_records` (notices des fonds d'archives qui désignent une ligne : dossiers, photographies et films des Archives SNCF ; ODbL, lien vers archives.sncf.com, ni description ni image) ; `line_stories` (histoire d'un événement : images de la ligne datées à 5 ans au plus et documents de Gallica de la période, chacun avec son écart en années à dire au lecteur). Position : `locate_on_line` (point kilométrique d'un lieu donné par ses coordonnées, sur le tracé d'une ligne).
Journal de bord : `journal` (articles longs de l'équipe sur des lignes emblématiques, avec leurs sources en notes ; texte CC BY-SA 4.0 ; reprenez la mention `disclosure` quand elle existe).
Territoires : `list_departments` (codes INSEE et noms des régions et des départements, à passer dans `department` et `region` de list_lines, list_stations, network_events et accidents).
Invites (prompts) : `present_line` (présenter une ligne avec ses sources), `compare_region_eras` (comparer deux époques d'une région), `station_story` (histoire d'une gare).
Époques : `compare_eras` (kilomètres ouverts, fermés, déposés ou restés en service entre deux ères de la carte).
Lieux et dates : `what_was_here` (autour d'un point et pour une année : lignes présentes et leur état à cet endroit, gares proches, signalisation en vigueur, matériel daté, événements et accidents de l'année) ; `network_in_area` (réseau d'une zone, rectangle ou cercle, à une époque : km en service, fermés, déposés, lignes et gares) ; `compare_line_eras` (une ligne à deux époques : km par catégorie, gares ouvertes ou fermées, signalisation, événements entre les deux) ; `line_stops` (gares d'une ligne dans l'ordre des PK à une époque, avec le lien GTFS de l'ère) ; `station_aerial` (photos aériennes historiques de l'IGN autour d'une gare, 1950 à 1995, Licence Ouverte 2.0). Signalisation à une date : `signalling_history` avec `year`.
Matériel roulant : `materials` (séries filtrées par catégorie, constructeur, exploitant, ligne, année ; pagination par `page`) puis `get_material` (fiche avec la provenance de chaque champ et les lignes parcourues, chaque lien avec sa preuve et ses périodes de circulation datées) ; `line_materials` (séries d'une ligne avec leurs périodes de circulation : début, fin, précision).
Dates : ISO AAAA-MM-JJ, à lire avec leur précision (day, month, year) ; n'affichez que la partie connue. La carte est un instantané au 31/12 de chaque décennie (l'ère courante = aujourd'hui).
Obligations en réutilisant les données : citez « Voies Ferrées de France (vff-project.org) » avec le lien `vff_url` de la réponse et les mentions du bloc `attribution` (ODbL 1.0 : © contributeurs OpenStreetMap, SNCF Open Data…). Les données d'un fournisseur dont `open_licence` vaut false ne se reprennent qu'avec sa mention et son lien. Les faits d'une source à usage non commercial (archeoferroviaire) ne sont jamais servis par ce serveur ; un tracé non redistribuable n'est jamais donné en coordonnées (`geometry_withheld`). Ne présentez jamais une date « between » (non datée par une source) comme sûre.
Langue : paramètre `lang` (fr par défaut, en, de).
TXT)]
class VffServer extends Server
{
    /**
     * Tous les outils tiennent dans la première page de `tools/list` : le défaut du paquet (15) en
     * laissait de côté pour les clients qui ne suivent pas `nextCursor`.
     */
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        SearchTool::class,
        LineSheetTool::class,
        LineEventsTool::class,
        StationSheetTool::class,
        NetworkAtEraTool::class,
        HistoricalRouteTool::class,
        SourcesTool::class,
        NetworkEventsTool::class,
        MediaTool::class,
        ListLinesTool::class,
        ListStationsTool::class,
        MaterialsTool::class,
        MaterialSheetTool::class,
        AccidentsTool::class,
        AccidentSheetTool::class,
        CompareErasTool::class,
        LevelCrossingsTool::class,
        LineProfileTool::class,
        LineAuditTool::class,
        SegmentAuditsTool::class,
        StationAuditsTool::class,
        LineGreenwaysTool::class,
        LineInfrastructureTool::class,
        LineArchivesTool::class,
        LineArchiveRecordsTool::class,
        LineStoriesTool::class,
        JournalTool::class,
        LineMaterialsTool::class,
        LocateOnLineTool::class,
        SignallingHistoryTool::class,
        ListDepartmentsTool::class,
        NetworkInAreaTool::class,
        WhatWasHereTool::class,
        CompareLineErasTool::class,
        LineStopsTool::class,
        StationAerialTool::class,
    ];

    protected array $resources = [
        LicencesResource::class,
        LineSheetResource::class,
    ];

    protected array $prompts = [
        PresentLinePrompt::class,
        CompareRegionErasPrompt::class,
        StationStoryPrompt::class,
    ];

    /** Complétion des arguments des invites (`completion/complete`) : VffPrompt::complete. */
    protected function boot(): void
    {
        $this->addCapability(self::CAPABILITY_COMPLETIONS);
    }
}
