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
 *             quelques centaines de mètres près ;
 *   sources : au moins une, avec une adresse ; note : ce qu'il faut savoir du point (facultatif).
 *
 * Positions et PK relevés le 23/11/2026 (SNCF Réseau Open Data, ODbL ; OpenStreetMap, ODbL ; Wikipédia, CC BY-SA).
 * Relevé : storage/app/reports/agents/aprem-2111/lgv-bifurcations.md.
 */
$formes = ['publisher' => 'SNCF Réseau', 'title' => 'Formes des lignes du RFN', 'url' => 'https://ressources.data.sncf.com/explore/dataset/formes-des-lignes-du-rfn/'];
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
        'pk_m' => 144_077, 'lat' => 50.19114, 'lon' => 2.86942, 'method' => 'sncf_calibrated',
        'sources' => [$formes, $gares, $wiki('LGV Nord', 234893907)],
        'note' => 'Origine du raccordement 226306 vers Arras ; l\'article de la LGV écrit « Arras (bifurcation de Croisilles) ». PK interpolé entre TGV Haute-Picardie (110,823) et la frontière (210,580) ; une borne OpenStreetMap estimée (148, nœud 4853804728) le contredit de 4 km, non retenue faute de seconde borne.',
    ],
    [
        'line' => '226000', 'name' => 'Bifurcation de Fretin', 'aliases' => ['Fretin', 'Triangle de Fretin', 'Lille'], 'kind' => 'junction',
        'pk_m' => 194_643, 'lat' => 50.55680, 'lon' => 3.11732, 'method' => 'sncf_calibrated',
        'sources' => [$formes, $gares, $wiki('LGV Nord', 234893907)],
        'note' => 'Origine de la ligne 216000 de Fretin à Fréthun par Lille ; l\'article de la LGV écrit « Lille (triangle de Fretin) ». PK interpolé entre TGV Haute-Picardie et la frontière.',
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
        'line' => '752000', 'name' => 'Bifurcation de Saint-Florentin', 'aliases' => ['Saint-Florentin'], 'kind' => 'junction',
        'pk_m' => 117_186, 'lat' => 47.97414, 'lon' => 3.69320, 'method' => 'osm_milestones',
        'sources' => [$formes, $osm(12036225248, 'Borne PK 117,2'), $wiki('LGV Sud-Est', 239633865)],
        'note' => 'Point de contact du raccordement 833300 vers Saint-Florentin ; le PK interpolé entre les gares de Vaumort et Tonnerre TGV donne 117,116.',
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation de Montanay', 'aliases' => ['Montanay', 'Sathonay'], 'kind' => 'junction',
        'pk_m' => 380_500, 'lat' => 45.89361, 'lon' => 4.87708, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Rhône-Alpes', 240488881), $osm(12036228251, 'Borne PK 380,6')],
        'note' => 'Origine du raccordement 752330 vers Sathonay et Lyon-Saint-Clair, début de la LGV Rhône-Alpes. Sur la ligne 752000, une portion « jusqu\'à Sathonay » s\'arrête ici ; la suite est sur le raccordement 752330.',
    ],
    [
        'line' => '752000', 'name' => 'Bifurcation de Saint-Quentin-Fallavier', 'aliases' => ['Saint-Quentin-Fallavier', 'Bifurcation de Grenay', 'Grenay'], 'kind' => 'junction',
        'pk_m' => 416_648, 'lat' => 45.65978, 'lon' => 5.07312, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Rhône-Alpes', 240488881), $osm(12036225484, 'Borne PK 416,6')],
        'note' => 'Origine du raccordement de Grenay (752340) vers la ligne de Lyon à Grenoble ; l\'article de la LGV écrit « bifurcation de Saint-Quentin-Fallavier (ou bifurcations de Grenay) ». Le raccordement d\'Heyrieux rejoint la ligne au PK 419,081 ; le mode de cantonnement de SNCF Réseau passe de la TVM 300 à la TVM 430 au PK 423,442.',
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Saint-Marcel-lès-Valence', 'aliases' => ['Saint-Marcel-lès-Valence', 'Saint-Marcel'], 'kind' => 'junction',
        'pk_m' => 493_427, 'lat' => 45.00972, 'lon' => 4.97688, 'method' => 'sncf_connection',
        'sources' => [$formes, $wiki('LGV Rhône-Alpes', 240488881), $osm(12036226380, 'Borne PK 493,4')],
        'note' => 'Origine du raccordement 752342 vers la ligne de Valence à Moirans, fin de la LGV Rhône-Alpes.',
    ],
    [
        'line' => '752000', 'name' => 'Raccordement de Marseille-Saint-Louis', 'aliases' => ['Marseille', 'Saint-Louis'], 'kind' => 'line_end',
        'pk_m' => 711_163, 'lat' => 43.35340, 'lon' => 5.35403, 'method' => 'sncf_line_end',
        'sources' => [$formes, $osm(12036225598, 'Borne PK 711,1'), $wiki('LGV Méditerranée', 240289807)],
        'note' => 'Fin de la ligne, jonction avec la ligne de Paris à Marseille à Saint-Louis (Marseille).',
    ],
];
