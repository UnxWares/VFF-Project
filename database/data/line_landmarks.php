<?php

/**
 * Points remarquables des lignes qui ne sont pas des gares : bifurcations, raccordements, origines et fins de
 * ligne, frontières (App\Services\Signalling\LineLandmarksFile). Ils placent les portions nommées par ces lieux
 * (« de la bifurcation de Saint-Florentin à Sathonay ») quand aucune gare de la ligne ne porte le nom
 * (SignallingSectionLocator : la gare passe toujours en premier).
 *
 * Un point :
 *   line    : code SNCF Réseau à 6 chiffres de la ligne sur laquelle le PK est compté ;
 *   name    : nom du lieu ; aliases : autres noms sous lesquels les sources le citent (comparés sans accents ni
 *             casse, « Saint » = « St ») ; deux points d'une même ligne ne partagent jamais un nom ;
 *   kind    : junction (bifurcation ou origine d'un raccordement), line_start, line_end, border ;
 *   pk_m    : PK du point sur la ligne, en mètres, numérotation de SNCF Réseau ;
 *   lat/lon : position du point sur la forme de la ligne (WGS 84) ;
 *   method  : origine du PK. `sncf_line_end` : extrémité de la forme de la ligne dans « Formes des lignes du
 *             RFN » ; `sncf_connection` : PK d'origine du raccordement, que SNCF Réseau compte dans la numérotation
 *             de la ligne ; `osm_milestones` : bornes hectométriques d'OpenStreetMap (`railway:position`) au droit
 *             du point ; `sncf_calibrated` : point de contact du raccordement avec la forme de la ligne, PK
 *             interpolé entre les deux gares voisines de PK officiel (liste des gares de SNCF Réseau), à
 *             quelques centaines de mètres près (une erreur de 4 km a été trouvée sur la LGV Nord : méthode
 *             abandonnée là où `sncf_track_circuits` existe) ; `sncf_track_circuits` : PK lu sur les extrémités des
 *             circuits de voie de la ligne (« Liste des circuits de voie », SNCF Réseau, PK et coordonnées de chaque
 *             extrémité), le point étant projeté sur la chaîne des extrémités de la voie, à 50 m près (contrôle sur
 *             des points de PK connu : Grenay 416,648 retrouvé à 0 m, Saint-Marcel 493,438 pour 493,427) ;
 *             `sncf_signalling_limit` : PK d'une limite de mode de cantonnement (« Mode de cantonnement des
 *             lignes », SNCF Réseau, PK et coordonnées de début et de fin de chaque tronçon) ;
 *   sources : au moins une, avec une adresse ; note : ce qu'il faut savoir du point (facultatif).
 *
 * Positions et PK relevés le 23/11/2026 (SNCF Réseau Open Data, ODbL ; OpenStreetMap, ODbL ; Wikipédia, CC BY-SA).
 * Relevé : storage/app/reports/agents/aprem-2111/lgv-bifurcations.md.
 */
$formes = ['publisher' => 'SNCF Réseau', 'title' => 'Formes des lignes du RFN', 'url' => 'https://ressources.data.sncf.com/explore/dataset/formes-des-lignes-du-rfn/'];
$circuits = ['publisher' => 'SNCF Réseau', 'title' => 'Liste des circuits de voie', 'url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-circuits-de-voie/'];
$cantonnement = ['publisher' => 'SNCF Réseau', 'title' => 'Mode de cantonnement des lignes', 'url' => 'https://ressources.data.sncf.com/explore/dataset/mode-de-cantonnement-des-lignes/'];
$gares = ['publisher' => 'SNCF Réseau', 'title' => 'Liste des gares', 'url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/'];
$wiki = fn (string $title, int $revision): array => ['publisher' => 'Wikipédia', 'title' => $title, 'url' => "https://fr.wikipedia.org/w/index.php?oldid={$revision}"];
$osm = fn (int $node, string $what): array => ['publisher' => 'OpenStreetMap', 'title' => $what, 'url' => "https://www.openstreetmap.org/node/{$node}"];

return [
    // LGV Est européenne
    [
        'line' => '005000', 'name' => 'Bifurcation de Vaires', 'aliases' => ['Vaires', 'Vaires-sur-Marne'], 'kind' => 'line_start',
        'pk_m' => 0, 'lat' => 48.87534, 'lon' => 2.64250, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Est européenne', 240823793), $osm(1531643152, 'Aiguillage de la bifurcation (PK 0,014)')],
        'note' => 'Origine de la ligne ; l\'article de la LGV situe l\'extrémité ouest de la phase 1 à 170 m de ce point.',
    ],
    [
        'line' => '005000', 'name' => 'Raccordement de Baudrecourt', 'aliases' => ['Baudrecourt', 'Raccordement de Herny'], 'kind' => 'junction',
        'pk_m' => 299_784, 'lat' => 48.95871, 'lon' => 6.42057, 'method' => 'sncf_calibrated',
        'sources' => [$formes, $gares, $wiki('LGV Est européenne', 240823793), $osm(2320652352, 'Bifurcation « Baudrecourt »')],
        'note' => 'Origine du raccordement 005345 vers la ligne de Rémilly à Stiring-Wendel, fin de la phase 1 (l\'article de la LGV place l\'extrémité est de la phase 1 sur ce raccordement). PK interpolé entre Lorraine TGV (281,324) et la fin du premier tronçon de la forme (301,475).',
    ],
    // LGV Rhin-Rhône
    [
        'line' => '014000', 'name' => 'Bifurcation de Villers-les-Pots', 'aliases' => ['Villers-les-Pots'], 'kind' => 'line_start',
        'pk_m' => 423, 'lat' => 47.21361, 'lon' => 5.30255, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Rhin-Rhône', 241036425)],
        'note' => 'Origine de la branche Est, jonction avec la ligne de Dijon à Vallorbe.',
    ],
    [
        'line' => '014000', 'name' => 'Raccordement ouest de Besançon TGV', 'aliases' => [], 'kind' => 'junction',
        'pk_m' => 52_301, 'lat' => 47.30930, 'lon' => 5.95799, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '014000', 'name' => 'Raccordement est de Besançon TGV', 'aliases' => [], 'kind' => 'junction',
        'pk_m' => 57_183, 'lat' => 47.33826, 'lon' => 6.00662, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '014000', 'name' => 'Raccordement de Petit-Croix', 'aliases' => ['Petit-Croix'], 'kind' => 'line_end',
        'pk_m' => 137_899, 'lat' => 47.60311, 'lon' => 6.94516, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Rhin-Rhône', 241036425)],
        'note' => 'Fin de la branche Est et origine du raccordement 014330 vers Belfort.',
    ],
    // LGV Nord
    [
        'line' => '226000', 'name' => 'Bifurcation de Gonesse', 'aliases' => ['Gonesse'], 'kind' => 'line_start',
        'pk_m' => 0, 'lat' => 49.00075, 'lon' => 2.42693, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Nord', 234893907), $osm(4030004731, 'Bifurcation « Gonesse »')],
    ],
    [
        'line' => '226000', 'name' => 'Bifurcation de Croisilles', 'aliases' => ['Croisilles', 'Arras', 'Raccordement d\'Arras-Sud'], 'kind' => 'junction',
        'pk_m' => 148_220, 'lat' => 50.19114, 'lon' => 2.86942, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Nord', 234893907)],
        'note' => 'Origine du raccordement 226306 vers Arras ; l\'article de la LGV écrit « Arras (bifurcation de Croisilles) ». PK lu sur les circuits de voie de la ligne 226000 (148,222 sur la voie 2 et 148,220 sur la voie 1, à 1 m et 6 m du point). Le PK interpolé entre les gares, 144,077, était faux de 4 km ; la borne OpenStreetMap estimée à 148 (nœud 4853804728) était donc juste.',
    ],
    [
        'line' => '226000', 'name' => 'Bifurcation de Fretin', 'aliases' => ['Fretin', 'Triangle de Fretin', 'Lille'], 'kind' => 'junction',
        'pk_m' => 197_826, 'lat' => 50.55680, 'lon' => 3.11732, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Nord', 234893907)],
        'note' => 'Origine de la ligne 216000 de Fretin à Fréthun par Lille ; l\'article de la LGV écrit « Lille (triangle de Fretin) ». PK lu sur les circuits de voie de la ligne 226000 (197,826 sur les deux voies, à 1 m et 4 m du point) ; le PK interpolé entre les gares, 194,643, était faux de 3 km.',
    ],
    [
        'line' => '226000', 'name' => 'Frontière belge', 'aliases' => ['Frontière franco-belge'], 'kind' => 'border',
        'pk_m' => 210_580, 'lat' => 50.57201, 'lon' => 3.27860, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Nord', 234893907)],
        'note' => 'Fin de la ligne (« Lille-Frontière »), à Wannehain.',
    ],
    // LGV Atlantique
    [
        'line' => '431000', 'name' => 'Bifurcation de Courtalain', 'aliases' => ['Courtalain'], 'kind' => 'junction',
        'pk_m' => 130_190, 'lat' => 48.08646, 'lon' => 1.11715, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Atlantique', 241274964), $osm(12012147897, 'Borne PK 130,2')],
        'note' => 'Séparation des branches Ouest (ligne 429000) et Sud-Ouest ; l\'article de la LGV donne le PK 130,186.',
    ],
    [
        'line' => '431000', 'name' => 'Raccordement de Saint-Pierre-des-Corps', 'aliases' => ['Saint-Pierre-des-Corps'], 'kind' => 'junction',
        'pk_m' => 214_699, 'lat' => 47.39823, 'lon' => 0.80200, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Atlantique', 241274964), $osm(12012148549, 'Borne PK 214,7')],
        'note' => 'Origine du raccordement 431315 vers la gare de Saint-Pierre-des-Corps.',
    ],
    // LGV Sud-Est, Rhône-Alpes et Méditerranée (une seule ligne 752000)
    [
        'line' => '752000', 'name' => 'Origine de Combs-la-Ville', 'aliases' => ['Combs-la-Ville'], 'kind' => 'line_start',
        'pk_m' => 168, 'lat' => 48.63828, 'lon' => 2.56500, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Sud-Est', 239633865)],
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation de Moisenay', 'aliases' => ['Moisenay', 'Raccordement de Moisenay'], 'kind' => 'junction',
        'pk_m' => 17_114, 'lat' => 48.56912, 'lon' => 2.75358, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Sud-Est', 239633865)],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Montereau nord', 'aliases' => ['Montereau nord'], 'kind' => 'junction',
        'pk_m' => 43_679, 'lat' => 48.39016, 'lon' => 2.98686, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Montereau sud', 'aliases' => ['Montereau sud', 'Montereau'], 'kind' => 'junction',
        'pk_m' => 44_199, 'lat' => 48.38687, 'lon' => 2.99180, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Sud-Est', 239633865)],
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation de Saint-Florentin', 'aliases' => ['Saint-Florentin'], 'kind' => 'junction',
        'pk_m' => 117_186, 'lat' => 47.97414, 'lon' => 3.69320, 'method' => 'osm_milestones',
        'sources' => [$formes, $osm(12036225248, 'Borne PK 117,2'), $wiki('LGV Sud-Est', 239633865)],
        'note' => 'Point de contact du raccordement 833300 vers Saint-Florentin ; le PK interpolé entre les gares de Vaumort et Tonnerre TGV donne 117,116.',
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Montchanin', 'aliases' => ['Montchanin'], 'kind' => 'junction',
        'pk_m' => 273_441, 'lat' => 46.76852, 'lon' => 4.49986, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Sud-Est', 239633865)],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Mâcon-Sud', 'aliases' => ['Mâcon-Sud'], 'kind' => 'junction',
        'pk_m' => 334_333, 'lat' => 46.27995, 'lon' => 4.78090, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Mâcon-Nord', 'aliases' => ['Mâcon-Nord'], 'kind' => 'junction',
        'pk_m' => 336_023, 'lat' => 46.26914, 'lon' => 4.79597, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Pont-de-Veyle', 'aliases' => ['Pont-de-Veyle'], 'kind' => 'junction',
        'pk_m' => 337_735, 'lat' => 46.26242, 'lon' => 4.81570, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Sud-Est', 239633865)],
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation de Montanay', 'aliases' => ['Montanay', 'Sathonay'], 'kind' => 'junction',
        'pk_m' => 380_500, 'lat' => 45.89361, 'lon' => 4.87708, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Rhône-Alpes', 240488881), $osm(12036228251, 'Borne PK 380,6')],
        'note' => 'Origine du raccordement 752330 vers Sathonay et Lyon-Saint-Clair, début de la LGV Rhône-Alpes. Sur la ligne 752000, une portion « jusqu\'à Sathonay » s\'arrête ici ; la suite est sur le raccordement 752330.',
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation de Grenay', 'aliases' => ['Grenay', 'Bifurcations de Grenay'], 'kind' => 'junction',
        'pk_m' => 416_648, 'lat' => 45.65978, 'lon' => 5.07312, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Rhône-Alpes', 240488881), $osm(12036225484, 'Borne PK 416,6')],
        'note' => 'Origine du raccordement de Grenay (752340) vers la ligne de Lyon à Grenoble, qui croise la LGV 900 m plus loin (PK 417,5). Ce n\'est pas la fin de la première section de 1992 : voir la bifurcation de Saint-Quentin-Fallavier.',
    ],
    [
        'line' => '752000', 'name' => 'Raccordement d\'Heyrieux', 'aliases' => ['Heyrieux'], 'kind' => 'junction',
        'pk_m' => 419_074, 'lat' => 45.63983, 'lon' => 5.08574, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation de Saint-Quentin-Fallavier', 'aliases' => ['Saint-Quentin-Fallavier'], 'kind' => 'junction',
        'pk_m' => 423_442, 'lat' => 45.60189, 'lon' => 5.09708, 'method' => 'sncf_signalling_limit',
        'sources' => [$cantonnement, $wiki('LGV Rhône-Alpes', 240488881), $osm(12036189052, 'Borne PK 423,6')],
        'note' => 'Limite de la TVM 300 (nord) et de la TVM 430 (sud) de SNCF Réseau. Choisi pour la fin de la première section de 1992 : l\'article de la LGV donne 42 km de Montanay à Saint-Quentin-Fallavier (380,5 + 42 = 422,5) et 73 km de là à Valence (Valence-TGV au PK 495,464 : 422,5), et dit que la TVM 430 commence « au sud de la bifurcation de St-Quentin-Fallavier » ; la bifurcation de Grenay (PK 416,6) serait à 36 km et 79 km. Écart de 0,9 km avec les longueurs de l\'article, contre 6,8 km pour Grenay.',
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Saint-Marcel-lès-Valence', 'aliases' => ['Saint-Marcel-lès-Valence', 'Saint-Marcel'], 'kind' => 'junction',
        'pk_m' => 493_427, 'lat' => 45.00972, 'lon' => 4.97688, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Rhône-Alpes', 240488881), $osm(12036226380, 'Borne PK 493,4')],
        'note' => 'Origine du raccordement 752342 vers la ligne de Valence à Moirans, fin de la LGV Rhône-Alpes.',
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Châteauneuf-sur-Isère', 'aliases' => ['Châteauneuf-sur-Isère'], 'kind' => 'junction',
        'pk_m' => 495_225, 'lat' => 44.99352, 'lon' => 4.97842, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Crest', 'aliases' => ['Crest', 'Base d\'Eurre', 'Eurre'], 'kind' => 'junction',
        'pk_m' => 524_532, 'lat' => 44.73542, 'lon' => 4.98567, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Méditerranée', 240289807)],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement nord de Bollène-Lamotte-du-Rhône', 'aliases' => ['Bollène nord'], 'kind' => 'junction',
        'pk_m' => 578_410, 'lat' => 44.30665, 'lon' => 4.70122, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement sud de Bollène-Lamotte-du-Rhône', 'aliases' => ['Bollène sud', 'Bollène'], 'kind' => 'junction',
        'pk_m' => 581_402, 'lat' => 44.27993, 'lon' => 4.69958, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Méditerranée', 240289807)],
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation d\'Avignon-Nord', 'aliases' => ['Avignon-Nord', 'Les Angles'], 'kind' => 'junction',
        'pk_m' => 617_742, 'lat' => 43.97346, 'lon' => 4.73692, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Cheval-Blanc', 'aliases' => ['Cheval-Blanc'], 'kind' => 'junction',
        'pk_m' => 652_332, 'lat' => 43.79364, 'lon' => 5.05345, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Méditerranée', 240289807)],
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Marseille-Saint-Louis', 'aliases' => ['Marseille', 'Saint-Louis'], 'kind' => 'line_end',
        'pk_m' => 711_163, 'lat' => 43.35340, 'lon' => 5.35403, 'method' => 'sncf_line_end',
        'sources' => [$formes, $osm(12036225598, 'Borne PK 711,1'), $wiki('LGV Méditerranée', 240289807)],
        'note' => 'Fin de la ligne, jonction avec la ligne de Paris à Marseille à Saint-Louis (Marseille).',
    ],
    // Raccordement de Lyon-Saint-Clair (fin de la première mise en service de la LGV Sud-Est)
    [
        'line' => '752330', 'name' => 'Origine de Montanay', 'aliases' => ['Montanay', 'Bifurcation de Montanay'], 'kind' => 'line_start',
        'pk_m' => 380_500, 'lat' => 45.89361, 'lon' => 4.87708, 'method' => 'sncf_line_end',
        'sources' => [$formes, $cantonnement],
        'note' => 'Origine du raccordement 752330, numéroté dans la suite de la LGV : voir la bifurcation de Montanay sur la ligne 752000. Les circuits de TVM 300 de SNCF Réseau s\'arrêtent au PK 386,000, le block automatique lumineux suit jusqu\'au PK 394,727.',
    ],
    [
        'line' => '752330', 'name' => 'Sathonay-Camp', 'aliases' => ['Sathonay', 'Lyon-Saint-Clair'], 'kind' => 'line_end',
        'pk_m' => 394_727, 'lat' => 45.78888, 'lon' => 4.86023, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Sud-Est', 239633865)],
        'note' => 'Fin de la ligne 752330 (Lyon-Saint-Clair) ; l\'article de la LGV Sud-Est situe la fin de la ligne à Sathonay-Camp.',
    ],
    // LGV Interconnexion Est (ligne de Villeneuve-Saint-Georges à Moisenay ; la branche nord vers Vémars n'a pas de ligne propre dans les données de SNCF Réseau)
    [
        'line' => '752100', 'name' => 'Origine de Villeneuve-Saint-Georges', 'aliases' => ['Villeneuve-Saint-Georges', 'Bifurcation de Créteil'], 'kind' => 'line_start',
        'pk_m' => 0, 'lat' => 48.77462, 'lon' => 2.43627, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Interconnexion Est', 239542099)],
    ],
    [
        'line' => '752100', 'name' => 'Raccordement de Valenton', 'aliases' => ['Valenton'], 'kind' => 'junction',
        'pk_m' => 4_027, 'lat' => 48.75082, 'lon' => 2.47123, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Interconnexion Est', 239542099)],
    ],
    [
        'line' => '752100', 'name' => 'Raccordement de Coubert', 'aliases' => ['Coubert'], 'kind' => 'junction',
        'pk_m' => 26_871, 'lat' => 48.67126, 'lon' => 2.72956, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Interconnexion Est', 239542099)],
    ],
    [
        'line' => '752100', 'name' => 'Bifurcation de Moisenay', 'aliases' => ['Moisenay'], 'kind' => 'line_end',
        'pk_m' => 39_406, 'lat' => 48.56911, 'lon' => 2.75357, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Interconnexion Est', 239542099)],
    ],
    // LGV Bretagne - Pays de la Loire (ligne 408000)
    [
        'line' => '408000', 'name' => 'Origine de Connerré', 'aliases' => ['Connerré'], 'kind' => 'line_start',
        'pk_m' => 0, 'lat' => 48.05067, 'lon' => 0.47655, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Bretagne-Pays de la Loire', 238141133)],
    ],
    [
        'line' => '408000', 'name' => 'Raccordement de Connerré fret', 'aliases' => ['Connerré fret'], 'kind' => 'junction',
        'pk_m' => 3_172, 'lat' => 48.06041, 'lon' => 0.43667, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '408000', 'name' => 'Raccordement de La Milesse fret', 'aliases' => ['La Milesse fret'], 'kind' => 'junction',
        'pk_m' => 26_463, 'lat' => 48.07811, 'lon' => 0.14235, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '408000', 'name' => 'Raccordement de La Milesse voyageurs', 'aliases' => ['La Milesse'], 'kind' => 'junction',
        'pk_m' => 28_648, 'lat' => 48.07285, 'lon' => 0.11402, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '408000', 'name' => 'Raccordement de Sablé-sur-Sarthe', 'aliases' => ['Sablé-sur-Sarthe', 'Sablé'], 'kind' => 'junction',
        'pk_m' => 64_113, 'lat' => 47.91002, 'lon' => -0.25663, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Bretagne-Pays de la Loire', 238141133)],
    ],
    [
        'line' => '408000', 'name' => 'Virgule de Sablé-sur-Sarthe', 'aliases' => ['Virgule de Sablé'], 'kind' => 'junction',
        'pk_m' => 69_661, 'lat' => 47.91293, 'lon' => -0.33040, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Bretagne-Pays de la Loire', 238141133)],
    ],
    [
        'line' => '408000', 'name' => 'Raccordement de Laval-Est', 'aliases' => ['Laval-Est'], 'kind' => 'junction',
        'pk_m' => 102_564, 'lat' => 48.08350, 'lon' => -0.67507, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '408000', 'name' => 'Raccordement de Laval-Ouest', 'aliases' => ['Laval-Ouest'], 'kind' => 'junction',
        'pk_m' => 124_180, 'lat' => 48.05681, 'lon' => -0.91716, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '408000', 'name' => 'Raccordement de Rennes', 'aliases' => ['Rennes', 'Cesson-Sévigné'], 'kind' => 'line_end',
        'pk_m' => 181_635, 'lat' => 48.11009, 'lon' => -1.61715, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('LGV Bretagne-Pays de la Loire', 238141133)],
    ],
    // LGV Sud Europe Atlantique (ligne 566000)
    [
        'line' => '566000', 'name' => 'Raccordement de Monts-Sud', 'aliases' => ['Monts-Sud', 'Monts'], 'kind' => 'junction',
        'pk_m' => 16_032, 'lat' => 47.21408, 'lon' => 0.65846, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '566000', 'name' => 'Raccordement de La Celle-Saint-Avant', 'aliases' => ['La Celle-Saint-Avant'], 'kind' => 'junction',
        'pk_m' => 36_269, 'lat' => 47.05663, 'lon' => 0.60177, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '566000', 'name' => 'Raccordement de Migne-Auxances', 'aliases' => ['Migne-Auxances', 'Poitiers'], 'kind' => 'junction',
        'pk_m' => 86_083, 'lat' => 46.66282, 'lon' => 0.35459, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Sud Europe Atlantique', 241340542)],
    ],
    [
        'line' => '566000', 'name' => 'Raccordement de Fontaine-le-Comte nord', 'aliases' => ['Fontaine-le-Comte nord'], 'kind' => 'junction',
        'pk_m' => 103_499, 'lat' => 46.52878, 'lon' => 0.24286, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '566000', 'name' => 'Raccordement de Fontaine-le-Comte sud', 'aliases' => ['Fontaine-le-Comte sud', 'Fontaine-le-Comte'], 'kind' => 'junction',
        'pk_m' => 108_085, 'lat' => 46.49037, 'lon' => 0.22087, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '566000', 'name' => 'Raccordement de Juillé', 'aliases' => ['Juillé'], 'kind' => 'junction',
        'pk_m' => 172_115, 'lat' => 45.92708, 'lon' => 0.12632, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Sud Europe Atlantique', 241340542)],
    ],
    [
        'line' => '566000', 'name' => 'Raccordement de Villognon', 'aliases' => ['Villognon', 'Angoulême'], 'kind' => 'junction',
        'pk_m' => 179_375, 'lat' => 45.86332, 'lon' => 0.11395, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('LGV Sud Europe Atlantique', 241340542)],
    ],
    [
        'line' => '566000', 'name' => 'Raccordement de La Couronne', 'aliases' => ['La Couronne'], 'kind' => 'junction',
        'pk_m' => 214_265, 'lat' => 45.55971, 'lon' => 0.06353, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    // Contournement de Nîmes et de Montpellier (ligne des Angles à Lattes)
    [
        'line' => '834000', 'name' => 'Bifurcation d\'Avignon-Nord', 'aliases' => ['Avignon-Nord', 'Les Angles'], 'kind' => 'line_start',
        'pk_m' => 0, 'lat' => 43.97346, 'lon' => 4.73692, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('Ligne des Angles à Lattes (LGV)', 240702409)],
    ],
    [
        'line' => '834000', 'name' => 'Raccordement du triangle des Angles', 'aliases' => ['Triangle des Angles'], 'kind' => 'junction',
        'pk_m' => 5_164, 'lat' => 43.93584, 'lon' => 4.71765, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '834000', 'name' => 'Raccordement de Redessan-Manduel nord 2', 'aliases' => ['Redessan-Manduel R2N'], 'kind' => 'junction',
        'pk_m' => 24_801, 'lat' => 43.83089, 'lon' => 4.52834, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '834000', 'name' => 'Raccordement de Redessan-Manduel nord 1', 'aliases' => ['Redessan-Manduel R1N'], 'kind' => 'junction',
        'pk_m' => 25_723, 'lat' => 43.82521, 'lon' => 4.52003, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '834000', 'name' => 'Raccordement de Jonquières', 'aliases' => ['Jonquières'], 'kind' => 'junction',
        'pk_m' => 29_102, 'lat' => 43.80511, 'lon' => 4.48846, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits],
    ],
    [
        'line' => '834000', 'name' => 'Raccordement de Saint-Gervasy', 'aliases' => ['Saint-Gervasy'], 'kind' => 'junction',
        'pk_m' => 29_540, 'lat' => 43.80261, 'lon' => 4.48430, 'method' => 'sncf_track_circuits',
        'sources' => [$formes, $circuits, $wiki('Contournement ferroviaire de Nîmes et de Montpellier', 240117712)],
    ],
    [
        'line' => '834000', 'name' => 'Raccordement de Lattes', 'aliases' => ['Lattes', 'Montpellier'], 'kind' => 'line_end',
        'pk_m' => 86_388, 'lat' => 43.56598, 'lon' => 3.86483, 'method' => 'sncf_line_end',
        'sources' => [$formes, $wiki('Ligne des Angles à Lattes (LGV)', 240702409), $wiki('Contournement ferroviaire de Nîmes et de Montpellier', 240117712)],
    ],
];
