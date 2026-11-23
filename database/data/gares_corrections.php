<?php

/**
 * Corrections SOURCÉES des gares, appliquées par vff:build-station-eras à chaque passage
 * (App\Services\StationCorrections : format, actions, identification des fiches).
 *
 * Relecture des gares douteuses du 2026-10-20 (rapport storage/app/reports/agents/gares-relecture.md,
 * recherches détaillées dans storage/app/reports/agents/gares-relecture/R1-conflits.md,
 * R2-douteuses.md, R3-fret-seul.md). Les cas réglés par une RÈGLE (StationTimeline,
 * BuildStationEras) n'ont pas de ligne ici : Vincelles, Loriol, Nice-St-Augustin (fermeture
 * Wikidata / OSM contredite par la desserte actuelle), 15 gares fret seul dont la « fermeture »
 * Wikidata est la fin des voyageurs, réouvertures de lignes qui ne rouvrent pas les haltes.
 *
 * Pas de tiret long dans les textes (consigne éditoriale).
 *
 * @return list<array<string, mixed>>
 */
$wp = fn (string $title, int $oldid) => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $title)).'&oldid='.$oldid;
$osm = fn (string $ref) => 'https://www.openstreetmap.org/'.['n' => 'node', 'w' => 'way', 'r' => 'relation'][$ref[0]].'/'.substr($ref, 1);

// Trains miniatures de parcs (OSM railway=miniature, 500 à 600 mm) : écartés par la règle d'import
// (ImportStationSources : « train miniature »), entrés par une gare étiquetée railway=station / halt.
$miniature = fn (string $name, string $ref, string $park) => [
    'station' => ['name' => $name, 'osm' => $ref],
    'action' => 'kind',
    'kind' => 'excluded',
    'source_url' => $osm($ref),
    'reason' => "train miniature de parc ({$park}) : voie OSM railway=miniature, hors du réseau ferré (règle d'import des trains miniatures)",
];

return [
    // ── 1. Gares « incertaines » en 2030 ─────────────────────────────────────────────

    // Station de tramway et de métro de Marseille (ancien terminus souterrain du tramway), pas une gare
    // de chemin de fer : hors du réseau ferré (tramways écartés à l'import).
    [
        'station' => ['name' => 'Noailles', 'wikidata' => 'Q16638586'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Gare de Noailles', 240304744),
        'quote' => 'La gare de Noailles ou gare de l\'Est est une station de tramway et de métro du 1er arrondissement de Marseille.',
        'reason' => 'station de tramway et de métro, hors du réseau ferré',
    ],
    // Gare amont du funiculaire de Saint-Hilaire-du-Touvet (funiculaires écartés à l'import ; l'objet OSM
    // portait railway=station à côté de disused:funicular=yes). Fermé depuis la crue du 29/12/2021.
    [
        'station' => ['name' => 'Saint-Hilaire-du-Touvet', 'osm' => 'w37889707'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Funiculaire de Saint-Hilaire-du-Touvet', 235019758),
        'quote' => 'Le funiculaire est fermé fin 2021 à la suite de très grosses intempéries, et sa réouverture est prévue pour 2027.',
        'reason' => 'gare d\'un funiculaire (moyen de transport par câble), hors du réseau ferré ; fermé depuis le 29/12/2021',
    ],
    $miniature('Baignade', 'n13743493077', 'base de loisirs du Port aux Cerises, Draveil'),
    $miniature('Centre', 'n2098484665', 'base de loisirs du Port aux Cerises, Draveil'),
    $miniature('Port', 'n13743493078', 'base de loisirs du Port aux Cerises, Draveil'),
    $miniature('Rocaille', 'n2264605211', 'Parc floral de la Source, Orléans'),
    $miniature('Entrée', 'n2264638372', 'Parc floral de la Source, Orléans'),
    $miniature('Gare Nigloland', 'w153805462', 'Nigloland, Dolancourt'),
    $miniature('Petit Train - Sablons', 'w83059173', 'Jardin d\'Acclimatation, Paris'),
    $miniature('Le petit train', 'w399994192', 'parc de loisirs de Gujan-Mestras'),
    $miniature('Citadelle', 'n8040864946', 'Parc du Petit Prince, Ungersheim'),
    $miniature('Volcan', 'n5620400860', 'Parc du Petit Prince, Ungersheim'),
    // Zoo de Cerza : aucune voie ferrée cartographiée à moins de 600 m de la « gare » (Overpass,
    // 20/10/2026) ; le Safari-Train est un petit train de visite du parc, pas un chemin de fer.
    [
        'station' => ['name' => 'Safari Train', 'osm' => 'w165895454'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => 'https://www.openstreetmap.org/way/165895454',
        'reason' => 'petit train de visite du zoo de Cerza, aucune voie ferrée connue (OSM, 600 m) : hors du réseau ferré',
    ],
    // Disneyland Railroad (914 mm), Train des Sables (Mer de Sable), petit train du parc de la Tête d'Or
    // (voies narrow_gauge) : gares ouvertes, touristiques ; certitude par la règle
    // StationLineCoverage::attestedToday (source relevée aujourd'hui), pas de correction.

    // ── 2. Conflits de dates de fermeture ────────────────────────────────────────────

    // Beuzeville (Eure) : la section Glos-Montfort - Pont-Audemer - Quetteville est fermée aux voyageurs
    // le 28/09/1969 (article de la gare de Pont-Audemer, cohérent avec l'article de la ligne : 1969).
    // Le « 26 septembre 1971 » de l'article de Beuzeville est la fermeture de la ligne voisine
    // Pont-l'Évêque - Honfleur (même date dans son article), que les trains de Beuzeville n'empruntaient plus.
    [
        'station' => ['name' => 'Beuzeville (Eure)', 'uic' => '87415505'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pont-Audemer', 235033824),
        'quote' => 'La ligne (Évreux) Glos-Montfort - Pont-Audemer - Quetteville (Honfleur est fermée aux voyageurs le 28 septembre 1969.',
        'reason' => 'fermeture aux voyageurs de la section qui dessert Beuzeville',
    ],
    [
        'station' => ['name' => 'Beuzeville (Eure)', 'uic' => '87415505'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1971-01-01'],
        'source_url' => $wp('Ligne de Pont-l\'Évêque à Honfleur', 240289259),
        'quote' => 'Le 26 septembre 1971, la ligne est fermée au trafic voyageurs',
        'reason' => '1971 est la fermeture de la ligne Pont-l\'Évêque - Honfleur, pas de la section Pont-Audemer - Quetteville (fermée le 28/09/1969)',
    ],

    // Coulommiers - La Ferté-Gaucher : trafic supprimé le 11/11/2002 (ligne 002000 corrigée aussi :
    // line_event_corrections_gares.php). Le 31/12/2011 de Wikidata vient du référentiel SNCF des gares
    // voyageurs (date de mise à jour du jeu), pas d'une fermeture.
    ...array_merge(...array_map(fn (array $g) => array_values(array_filter([
        [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'add',
            'type' => 'closure',
            'date' => '2002-11-11',
            'precision' => 'day',
            'source_url' => $wp('Ligne P du Transilien', 240492233),
            'quote' => '11 novembre 2002 : suppression du trafic ferroviaire sur la section Coulommiers - La Ferté-Gaucher, et remplacement de la desserte par des bus.',
            'reason' => 'gare de la section Coulommiers - La Ferté-Gaucher, fermée avec elle',
        ],
        $g[2] ? [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'neutralize',
            'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '2011-12-31'],
            'source_url' => 'https://www.wikidata.org/wiki/'.$g[2],
            'reason' => 'P3999 du 31/12/2011 tiré du jeu SNCF referentiel-gares-voyageurs (date du jeu, pas une fermeture)',
        ] : null,
    ])), [
        ['Chailly-Boissy-Le Châtel', '87116343', 'Q3096166'],
        ['St-Siméon', '87116368', 'Q2762581'],
        ['Jouy-sur-Morin-Le Marais', '87116384', 'Q3096666'],
        ['Chauffry', '8711635', null],
        ['Saint-Rémy-la-Vanne', '8711637', null],
    ])),

    // Commequiers : fermée en 1982 avec la mise en service du raccordement ; le 09/12/1992 est le
    // déclassement du tronçon Commequiers - Coëx (même phrase), lu à tort comme fermeture de la gare.
    [
        'station' => ['name' => 'Commequiers', 'uic' => '87486548'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1982-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Nantes-État à La Roche-sur-Yon par Sainte-Pazanne', 235075128),
        'quote' => 'Afin de supprimer le rebroussement de Commequiers, un raccordement est construit en 1982, légèrement en amont de la gare. [...] En contrepartie, la gare de Commequiers est fermée',
        'reason' => 'gare fermée à la mise en service du raccordement de 1982',
    ],
    [
        'station' => ['name' => 'Commequiers', 'uic' => '87486548'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure', 'date' => '1992-12-09'],
        'source_url' => $wp('Ligne de Nantes-État à La Roche-sur-Yon par Sainte-Pazanne', 235075128),
        'quote' => 'le tronçon est déclassé jusqu\'à Coëx le 9 décembre 1992',
        'reason' => 'date du déclassement du tronçon Commequiers - Coëx, pas de la fermeture de la gare',
    ],

    // Lille-Saint-Sauveur : gare aux marchandises seulement, de 1865 à fin 2003 (jamais de voyageurs).
    [
        'station' => ['name' => 'Lille-St-Sauveur', 'uic' => '87286039'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1865-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Lille-Saint-Sauveur', 240416155),
        'quote' => 'Ouverte uniquement comme gare aux marchandises en 1865, elle est fermée puis désaffectée en 2003.',
        'reason' => 'gare aux marchandises dès son ouverture : jamais ouverte aux voyageurs',
    ],

    // Louviers : voyageurs vers Saint-Pierre-du-Vauvray jusqu'au 27/11/1969 (la ligne 376000 est corrigée
    // aussi : line_event_corrections_gares.php) ; marchandises ensuite, fin non datée.
    [
        'station' => ['name' => 'Louviers', 'uic' => '87415851'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-11-27',
        'precision' => 'day',
        'source_url' => $wp('Gare de Louviers', 235032785),
        'quote' => 'Le 27 novembre 1969, il est supprimé sur la ligne de Saint-Pierre-du-Vauvray à Louviers. La gare fut utilisée quelque temps pour le service marchandises.',
        'reason' => 'fin du service voyageurs à Louviers',
    ],

    // Besançon - Portes de Vesoul : halte annoncée lors des travaux de 2010-2011, jamais ouverte (ni
    // liste des gares TER, ni drapeau voyageurs SNCF ; l'ouverture 2015 de Wikidata n'a pas de référence).
    [
        'station' => ['name' => 'Portes-de-Vesoul', 'uic' => '87710723'],
        'action' => 'kind',
        'kind' => 'projected',
        'source_url' => $wp('Ligne de Besançon-Viotte à Vesoul', 235341766),
        'quote' => 'Enfin on créera trois haltes ferroviaires pour le TER Franche-Comté: Gare de Besançon - Portes de Vesoul',
        'reason' => 'halte projetée, jamais ouverte',
    ],

    // ── 3. Cas douteux gardés ouverts ────────────────────────────────────────────────

    // Arches - Saint-Dié : dernier train le 21/12/2018 ; la ligne rouvre le 12/12/2021 avec les seules
    // gares d'Arches, Bruyères et Saint-Dié (autocars pour les haltes). Wikidata : 14/12/2018 (inexact).
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'uic' => $g[1]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2018-12-22',
        'precision' => 'day',
        'source_url' => $g[2],
        'quote' => $g[3],
        'reason' => 'desserte voyageurs interrompue le 22/12/2018, halte non rouverte avec la ligne le 12/12/2021',
    ], [
        ['Docelles-Cheniménil', '87144030', $wp('Gare de Docelles - Cheniménil', 235031649),
            'Le 21 décembre 2018, circule le dernier train de voyageurs ; à compter du lendemain, la desserte de la ligne par des trains TER, et donc de cette halte SNCF, est interrompue'],
        ['Lépanges', '87144048', $wp('Gare de Lépanges', 195411889),
            'La réouverture de la ligne, le 12 décembre 2021, ne s\'accompagne pas de la remise en service de la halte.'],
        ['Laveline-devant-Bruyères', '87144162', $wp('Ligne d\'Arches à Saint-Dié', 228117866),
            'Seules les gares d\'Arches, Bruyères et Saint-Dié-des-Vosges sont alors desservies'],
        ['Biffontaine', '87144188', $wp('Ligne d\'Arches à Saint-Dié', 228117866),
            'Seules les gares d\'Arches, Bruyères et Saint-Dié-des-Vosges sont alors desservies'],
        ['Corcieux-Vanémont', '87144196', $wp('Gare de Corcieux - Vanémont', 195411801),
            'La réouverture de la ligne, le 12 décembre 2021, ne s\'accompagne pas de la remise en service de la halte.'],
    ]),
    [
        'station' => ['name' => 'Docelles-Cheniménil', 'uic' => '87144030'],
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '2011-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Docelles - Cheniménil', 235031649),
        'quote' => 'La gare était desservie par Fret SNCF jusqu\'en 2011, à l\'embranchement de la société TMF situé à proximité.',
        'reason' => 'fin du fret (le drapeau fret de liste-des-gares est périmé)',
    ],
    // Nice-Saint-Roch : remplacée par Nice-Pont-Michel le 01/09/2014, gare fret seul depuis
    // (Wikidata : 13/12/2014). La « réouverture » du 03/05/2021 de la ligne 945000 concerne la Roya.
    [
        'station' => ['name' => 'Nice-St-Roch', 'uic' => '87756007'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2014-09-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Nice-Saint-Roch', 210147246),
        'quote' => 'Nice-Saint-Roch n\'est plus desservie, depuis le 1er septembre 2014, par les trains TER Provence-Alpes-Côte d\'Azur',
        'reason' => 'fin du service voyageurs, remplacée par la halte de Nice-Pont-Michel ; fret maintenu',
    ],
    // Mauzens-Miremont : arrêt supprimé le 13/12/2020 ; la « réouverture » du 22/03/2026 de la ligne
    // Niversac - Agen suit une simple coupure (talus effondré à Sanilhac le 17/02/2026).
    [
        'station' => ['name' => 'Mauzens-Miremont', 'uic' => '87595728'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2020-12-13',
        'precision' => 'day',
        'source_url' => $wp('Gare de Mauzens-Miremont', 235033037),
        'quote' => 'Par manque de voyageurs d’après la SNCF, les trains ne marquent plus l’arrêt depuis le 13 décembre 2020.',
        'reason' => 'arrêt supprimé, transport à la demande depuis',
    ],
    // Pont-de-Claix : remplacée par Pont-de-Claix - L'Étoile (1,3 km plus au nord) le 29/03/2025 ; le
    // jeu SNCF gares-de-voyageurs garde l'ancienne gare.
    [
        'station' => ['name' => 'Pont-de-Claix', 'uic' => '87747519'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2025-03-29',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pont-de-Claix', 239308590),
        'quote' => 'Cette gare a été fermée le 29 mars 2025 à la suite de l\'ouverture de la gare de Pont-de-Claix l\'Étoile',
        'reason' => 'gare remplacée par Pont-de-Claix - L\'Étoile ; jeu gares-de-voyageurs périmé',
    ],
    // Nice-Saint-Augustin : déplacée de 400 m vers l'ouest le 01/09/2022 (gare provisoire), position de
    // la gare en service (OSM n27333931, uic_ref 87756254) au lieu de l'ancien bâtiment.
    [
        'station' => ['name' => 'Nice-St-Augustin', 'uic' => '87756254'],
        'action' => 'position',
        'lng' => 7.2106118,
        'lat' => 43.6684958,
        'source_url' => 'https://www.openstreetmap.org/node/27333931',
        'quote' => 'La gare est déplacée de 400 mètres à l\'ouest le 1er septembre 2022',
        'reason' => 'position de la gare en service depuis le 01/09/2022 (Wikipédia, Gare de Nice-Saint-Augustin, oldid 237836494)',
    ],
    // « Grasse » de liste-des-gares géocodée à Marseille-Saint-Charles (ligne 930000, PK 0+025, commune
    // MARSEILLE) : enregistrement erroné, la vraie gare de Grasse (même UIC) est une autre fiche.
    [
        'station' => ['name' => 'Grasse', 'uic' => '87757724', 'near' => [5.3806, 43.3028, 2]],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q=87757724',
        'reason' => 'enregistrement SNCF « Grasse » placé à Marseille-Saint-Charles (PK 0+025 de la 930000) : fiche erronée, doublon de la gare de Grasse',
    ],

    // ── 3 bis. Gares fret seul : fin du fret, démolition, dates propres ──────────────

    [
        'station' => ['name' => 'Paris-Tolbiac', 'uic' => '87545004'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2001-06-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Paris-Tolbiac', 235033713),
        'quote' => 'Elle est définitivement fermée en juin 2001 pour être détruite afin de permettre l\'opération d\'aménagement de Paris Rive Gauche.',
        'reason' => 'fermeture totale (le drapeau fret de liste-des-gares est périmé)',
    ],
    [
        'station' => ['name' => 'Bordeaux-Bastide', 'uic' => '87581017'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2014-10-14',
        'precision' => 'before',
        'source_url' => $wp('Gare de Bordeaux-Bastide', 235030961),
        'quote' => 'un service marchandises a été maintenu jusqu\'au XXIe siècle. La destruction de la gare marchandise a commencé le 14 octobre 2014.',
        'reason' => 'fret arrêté au plus tard à la destruction de la gare aux marchandises (date de fin non connue) ; le bâtiment voyageurs existe encore, le drapeau fret de liste-des-gares est périmé',
    ],
    [
        'station' => ['name' => 'La Motte-Ste-Rosseline', 'uic' => '87755835'],
        'action' => 'add',
        'type' => 'demolition',
        'date' => '2020-11-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de La Motte-Sainte-Roseline', 235748591),
        'quote' => 'La gare est finalement détruite en novembre 2020.',
        'reason' => 'gare démolie (le drapeau fret de liste-des-gares est périmé)',
    ],
    [
        'station' => ['name' => 'Leffrinckoucke', 'uic' => '87281626'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2002-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Leffrinckoucke', 210978984),
        'quote' => 'Il n\'y a plus de trafic voyageurs depuis 1992. Seul subsistait un trafic fret jusqu\'à l\'usine des dunes jusqu\'en 2002.',
        'reason' => 'fin du fret (le drapeau fret de liste-des-gares est périmé)',
    ],
    [
        'station' => ['name' => 'Bry-sur-Marne', 'uic' => '87271361'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2009-10-13',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bry-sur-Marne (SNCF)', 226552291),
        'quote' => 'Elle ferme au service des marchandises le 13 octobre 2009. Cependant la ligne reste parcourue par de nombreux trains de marchandises.',
        'reason' => 'fin du fret, donc de toute desserte (voyageurs fermés le 15/05/1939)',
    ],
    [
        'station' => ['name' => 'Bry-sur-Marne', 'uic' => '87271361'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '2009-01-01'],
        'source_url' => $wp('Gare de Bry-sur-Marne (SNCF)', 226552291),
        'quote' => 'La gare de Bry-sur-Marne ferme en 2009.',
        'reason' => 'la fermeture de 2009 est celle du fret (voyageurs fermés le 15/05/1939)',
    ],
    [
        'station' => ['name' => 'Réhon', 'uic' => '87194449'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1985-01-01'],
        'source_url' => $wp('Gare de Réhon', 222641462),
        'quote' => 'la halte voyageurs est fermée dans les années 1970 et détruite en 1985.',
        'reason' => '1985 est la démolition du bâtiment de la halte, pas une fermeture (voyageurs fermés dans les années 1970 ; fret par embranchement depuis)',
    ],
    [
        'station' => ['name' => 'Blanzy-Poste', 'uic' => '87694604'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'demolition', 'date' => '1997-01-01'],
        'source_url' => $wp('Gare de Blanzy-Poste', 211442566),
        'quote' => 'Le 28 septembre 1997 la gare est fermée au service des voyageurs, remplacée par la halte renommée gare de Blanzy alors qu\'elle est elle-même renommée gare de Blanzy-Poste.',
        'reason' => 'P576 (dissolution) 1997 = fin du service voyageurs, la gare reste ouverte au fret (embranchement Michelin)',
    ],

    // ── Fiche-contenus (2026-10-20) : dates de gares contredites par l'article de la gare ───────────
    // Mâcon-Loché-TGV : Wikidata donne le 19/09/1981, avant l'ouverture de sa seule ligne (LGV Sud-Est,
    // 27/09/1981) ; l'article de la gare : mise en service le 27/09/1981 avec la LGV.
    [
        'station' => ['name' => 'Mâcon-Loché-TGV', 'wikidata' => 'Q2253062'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1981-09-27',
        'precision' => 'day',
        'source_url' => $wp('Gare de Mâcon-Loché-TGV', 239205680),
        'quote' => 'Le 27 septembre 1981, la gare de Mâcon-Loché-TGV est mise en service en même temps que la gare du Creusot TGV, ainsi que la LGV Sud-Est',
        'reason' => 'mise en service avec la LGV Sud-Est, le jour de son ouverture commerciale',
    ],
    [
        'station' => ['name' => 'Mâcon-Loché-TGV', 'wikidata' => 'Q2253062'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1981-09-19'],
        'source_url' => $wp('Gare de Mâcon-Loché-TGV', 239205680),
        'reason' => 'date Wikidata antérieure à l\'ouverture de la ligne, contredite par l\'article de la gare',
    ],
    // Exideuil-sur-Vienne : halte fermée AVANT la ligne (le 11/12/2016, au changement de service), pas en
    // 2018 avec la section Saillat-Chassenon - Angoulême ; Wikidata ne donne que l'année.
    [
        'station' => ['name' => 'Exideuil-sur-Vienne', 'wikidata' => 'Q3095757'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2016-12-11',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Exideuil-sur-Vienne', 226945419),
        'quote' => 'Exideuil-sur-Vienne n\'est plus desservie par train depuis le 11 décembre 2016',
        'reason' => 'date au jour de la fin de desserte de la halte (Wikidata : 2016 seulement)',
    ],
    [
        'station' => ['name' => 'Exideuil-sur-Vienne', 'wikidata' => 'Q3095757'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '2016-01-01'],
        'source_url' => $wp('Gare d\'Exideuil-sur-Vienne', 226945419),
        'reason' => 'remplacée par la date au jour de l\'article de la gare',
    ],
    // Chabanais : fermée avec la section Saillat-Chassenon - Angoulême le 13/03/2018 (Wikidata : 2018).
    [
        'station' => ['name' => 'Chabanais', 'wikidata' => 'Q2335798'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2018-03-13',
        'precision' => 'day',
        'source_url' => $wp('Gare de Chabanais', 222234033),
        'quote' => 'Depuis le 13 mars 2018, la gare n\'est plus desservie par des trains, la ligne étant fermée entre Saillat - Chassenon et Angoulême',
        'reason' => 'date au jour de la fermeture de la section (Wikidata : 2018 seulement)',
    ],
    [
        'station' => ['name' => 'Chabanais', 'wikidata' => 'Q2335798'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '2018-01-01'],
        'source_url' => $wp('Gare de Chabanais', 222234033),
        'reason' => 'remplacée par la date au jour de l\'article de la gare',
    ],

    // ── 4. Desserte du jour (chantier « donnees-gares », 19/11/2026) ─────────────────────────────
    //
    // Écarts relevés par vff:import-station-service (storage/app/reports/agents/gares-desserte.md,
    // gares-desserte/ecarts-annotes.csv) : horaires GTFS ouverts du 15/04, du 15/06 et du 18/11/2026,
    // confirmés un par un par une source datée. Règles retenues (docs/METHODE-CLASSIFICATION.md,
    // « Desserte du jour ») : desserte saisonnière régulière = ouverte ; trains détournés quelques jours
    // pendant des travaux ailleurs = pas une desserte ; travaux avec reprise annoncée = pas une fermeture.

    // Montréjeau - Luchon : circulations suspendues le 18/11/2014, ligne rouverte le 22/06/2025 avec ses
    // quatre arrêts de 2014 (trains tous les jours dans les trois versions des horaires SNCF). La
    // « fermeture » Wikidata du 19/12/2014 ne correspond à aucune date de l'article ; la « démolition »
    // Wikidata de Luchon est la date de la suspension : le bâtiment voyageurs est resté ouvert.
    ...array_merge(...array_map(fn (array $g) => [
        [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'add',
            'type' => 'closure',
            'date' => '2014-11-18',
            'precision' => 'day',
            'source_url' => $wp('Ligne de Montréjeau - Gourdan-Polignan à Luchon', 239813913),
            'quote' => 'Réseau ferré de France et la SNCF suspendent les circulations ferroviaires à partir du 18 novembre 2014',
            'reason' => 'suspension des trains sur toute la ligne (remplacés par des cars jusqu\'en 2025)',
        ],
        [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'neutralize',
            'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '2014-12-19'],
            'source_url' => $wp('Ligne de Montréjeau - Gourdan-Polignan à Luchon', 239813913),
            'reason' => 'remplacée par la date de la suspension (18/11/2014) donnée par l\'article de la ligne',
        ],
        [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'add',
            'type' => 'reopening',
            'date' => '2025-06-22',
            'precision' => 'day',
            'source_url' => $wp('Ligne de Montréjeau - Gourdan-Polignan à Luchon', 239813913),
            'quote' => 'Les travaux et la pose des rails sont effectués en 2024. La réouverture a lieu le 22 juin 2025',
            'reason' => 'réouverture de la ligne et de ses quatre arrêts (Loures - Barbazan, Saléchan - Siradan, Marignac - Saint-Béat, Luchon)',
        ],
    ], [['Loures-Barbazan', '87611178'], ['Saléchan-Siradan', '87618710'], ['Marignac-St-Béat', '87611194'], ['Luchon', '87611236']])),
    [
        'station' => ['name' => 'Luchon', 'uic' => '87611236'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'demolition', 'date' => '2014-11-18'],
        'source_url' => $wp('Ligne de Montréjeau - Gourdan-Polignan à Luchon', 239813913),
        'quote' => 'Depuis 2014 le bâtiment voyageurs de la gare de Luchon est toujours ouvert, sauf ponctuellement',
        'reason' => 'date de la suspension des trains lue comme une démolition : le bâtiment voyageurs est resté ouvert',
    ],

    // RER A, section de la RATP : la liste SNCF des gares ne donne que le drapeau fret (« fret seul »),
    // les trains du RER A s'y arrêtent tous les jours (horaires Transilien du 18/11 au 17/12/2026).
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'uic' => $g[1]],
        'action' => 'current',
        'status' => 'active',
        'source_url' => 'https://transport.data.gouv.fr/datasets/horaires-des-lignes-transilien-1',
        'reason' => "gare du RER A (section de la RATP) desservie tous les jours : {$g[2]} arrêts de trains du 18/11 au 17/12/2026 dans les horaires GTFS Transilien (ODbL) ; la liste SNCF des gares ne la connaît qu'au fret",
    ], [['La Varenne-Chennevières', '87114389', '7 390'], ['Sucy-Bonneuil', '87114413', '7 390'], ['Rueil-Malmaison', '87382119', '7 390']]),

    // Haltes sans aucun train ni car dans les trois versions des horaires (avril, juin et novembre
    // 2026), fréquentation 2025 nulle ou presque : arrêt supprimé, date de l'article de la gare. Une
    // halte (point d'arrêt voyageurs, ni fret dans la liste SNCF) qui n'est plus desservie est fermée
    // (« closure ») ; une gare fermée « au service des voyageurs » garde ce type (fret seul ensuite si
    // la liste SNCF lui donne du fret).
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'uic' => $g[1]],
        'action' => 'add',
        'type' => $g[2],
        'date' => $g[3],
        'precision' => $g[4],
        'source_url' => $wp($g[5], $g[6]),
        'quote' => $g[7],
        'reason' => $g[8],
    ], [
        ['Liart', '87172544', 'closure_passenger', '2022-01-01', 'year', 'Gare de Liart', 232244635,
            'Jusqu\'en 2022, la gare est desservie par des trains du réseau TER Grand Est',
            'fin de la desserte voyageurs en 2022 (aucun train dans les horaires de 2026)'],
        ['Sérifontaine', '87381269', 'closure_passenger', '2024-07-19', 'day', 'Ligne de Saint-Denis à Dieppe', 239854982,
            'et ferme le 19 juillet 2024 pour un manque de fréquentation et un fort déficit',
            'trains Gisors - Serqueux remplacés par des cars (article de la gare, oldid 231989226 : « ferme à nouveau en juillet 2024 »)'],
        ['Gournay-Ferrières', '87381293', 'closure_passenger', '2024-07-19', 'day', 'Ligne de Saint-Denis à Dieppe', 239854982,
            'et ferme le 19 juillet 2024 pour un manque de fréquentation et un fort déficit',
            'trains Gisors - Serqueux remplacés par des cars (article de la gare, oldid 240339466 : « à nouveau supprimé en juillet 2024 »)'],
        ['Le Havre-Graville', '87413203', 'closure', '2024-09-01', 'month', 'Gare du Havre-Graville', 230878443,
            'Cette halte de la Société nationale des chemins de fer français (SNCF) n\'est plus desservie depuis septembre 2024',
            'liaison Le Havre - Montivilliers - Rolleville supprimée en septembre 2024 (cars en attendant le tramway)'],
        ['Bolbec-Nointot', '87413351', 'closure', '2019-01-01', 'year', 'Gare de Bolbec - Nointot', 218249834,
            'En juin, la fermeture en 2019 est confirmée',
            'halte fermée aux voyageurs fin 2019 (l\'article dit aussi « au début de l\'année 2020 » : même ère)'],
        ['Trentels-Ladignac', '87586446', 'closure', '2020-12-13', 'day', 'Gare de Trentels-Ladignac', 235034774,
            'depuis le 13 décembre 2020, les trains ne s\'arrêtent plus',
            'arrêt supprimé (transport à la demande à la place)'],
        ['Lavaveix-les-Mines', '87597781', 'closure', '2025-08-31', 'day', 'Gare de Lavaveix-les-Mines', 229097905,
            'La ligne ferroviaire reliant Guéret à Felletin ferme officiellement le soir du 31 août 2025',
            'fermeture de la ligne Busseau-sur-Creuse - Felletin (cars ensuite)'],
        ['Felletin', '87597849', 'closure', '2025-08-31', 'day', 'Gare de Felletin', 228892923,
            'la liaison ferroviaire reliant Guéret à Felletin est fermée au soir du 31 août 2025',
            'fermeture de la ligne Busseau-sur-Creuse - Felletin (cars ensuite)'],
        ['Lestelle', '87611111', 'closure', '2022-01-01', 'year', 'Gare de Lestelle', 241087122,
            'En 2022, le point d\'arrêt de Lestelle est supprimé',
            'point d\'arrêt supprimé en 2022'],
        ['Labarthe-Inard', '87611129', 'closure', '2022-01-01', 'year', 'Gare de Labarthe-Inard', 235032512,
            'En 2022, le point d\'arrêt de Labarthe -Inard est supprimé',
            'point d\'arrêt supprimé en 2022'],
        ['Ossun', '87671313', 'closure_passenger', '2022-01-01', 'year', 'Gare d\'Ossun', 235030562,
            'fermeture = 2022',
            'fermeture de la halte en 2022 (infobox de l\'article, gare rangée parmi les gares fermées des Hautes-Pyrénées)'],
        ['Carantilly-Marigny', '87447144', 'closure', '2018-12-09', 'day', 'Gare de Carantilly - Marigny', 235031158,
            'Aucun train ne s\'y arrête depuis sa fermeture annoncée par la Région Normandie en décembre 2018',
            'halte fermée le 09/12/2018 (date de l\'infobox de l\'article)'],
        ['Pont-Hébert', '87447185', 'closure', '2018-12-09', 'day', 'Gare de Pont-Hébert', 235033827,
            'Aucun train ne s\'y arrête depuis sa fermeture annoncée par la Région Normandie en décembre 2018',
            'halte fermée le 09/12/2018 (date de l\'infobox de l\'article)'],
        ['Trémentines', '87484238', 'closure_passenger', '2003-01-01', 'year', 'Gare de Trémentines', 235933884,
            'La gare est fermée au service des voyageurs en 2003',
            'fermeture au service des voyageurs en 2003'],
        ['St-Loup-Lamairé', '87487157', 'closure_passenger', '1980-09-27', 'day', 'Ligne de Chartres à Bordeaux-Saint-Jean', 240356620,
            'et de Thouars à Parthenay et Niort au 27 septembre 1980',
            'fermeture aux voyageurs de la section Thouars - Parthenay - Niort (l\'article de la gare dit aussi 1980)'],
        ['Villeneuve-lès-Avignon', '87765875', 'closure_passenger', '1973-08-06', 'day', 'Ligne de Givors-Canal à Grezan', 238687433,
            'Depuis la fermeture de la ligne au trafic voyageurs le 6 août 1973',
            'fermeture aux voyageurs de la rive droite du Rhône ; la gare n\'a pas rouvert avec la ligne en 2022'],
    ]),
    // Virville - Manneville : deux fiches portent son code UIC (doublon OSM n1281386660 posé à 37 km) ;
    // la fiche de la halte est désignée par son élément Wikidata.
    [
        'station' => ['name' => 'Virville-Manneville', 'wikidata' => 'Q2382506'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2018-12-09',
        'precision' => 'day',
        'source_url' => $wp('Gare de Virville - Manneville', 237268978),
        'quote' => 'fermeture = 9 décembre 2018',
        'reason' => 'halte fermée au service des voyageurs le 09/12/2018 (infobox de l\'article)',
    ],
    // Trentels-Ladignac : la ligne de Niversac à Agen rouvre le 22/03/2026 après une coupure, sans que les
    // trains s'arrêtent de nouveau à la halte (aucun arrêt dans les horaires de juin et de novembre 2026).
    [
        'station' => ['name' => 'Trentels-Ladignac', 'uic' => '87586446'],
        'action' => 'current',
        'status' => 'declassified',
        'source_url' => $wp('Gare de Trentels-Ladignac', 235034774),
        'quote' => 'depuis le 13 décembre 2020, les trains ne s\'arrêtent plus',
        'reason' => 'halte non rouverte avec la ligne en 2026 (aucun arrêt dans les horaires GTFS SNCF de juin et de novembre 2026)',
    ],
    // Haltes de la section Parthenay - Niort, fermée aux voyageurs avec elle (OSM : bâtiment, ancienne gare).
    [
        'station' => ['name' => 'Échiré - Saint-Gelais', 'osm' => 'w74192930'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-09-27',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Chartres à Bordeaux-Saint-Jean', 240356620),
        'quote' => 'et de Thouars à Parthenay et Niort au 27 septembre 1980',
        'reason' => 'halte de la section Parthenay - Niort, fermée aux voyageurs avec elle (OSM : bâtiment, disused:railway=station)',
    ],
    // Villeneuve-lès-Avignon : la rive droite du Rhône a rouvert aux voyageurs le 29/08/2022 sans cette gare
    // (OSM : proposed=station) ; travaux de réouverture de la gare pour la fin de 2027.
    [
        'station' => ['name' => 'Villeneuve-lès-Avignon', 'uic' => '87765875'],
        'action' => 'current',
        'status' => 'declassified',
        'source_url' => 'https://tvsudmagazine.fr/2026/11/villeneuve-lez-avignon-56-millions-deuros-investis-pour-rouvrir-la-gare-fin-2027-une-nouvelle-etape-pour-le-retour-du-train-sur-la-rive-droite-du-rhone/',
        'quote' => 'ce chantier doit permettre une remise en service de la gare fin 2027',
        'reason' => 'gare pas encore rouverte (réouverture annoncée pour la fin de 2027) : la réouverture de la ligne en 2022 ne la rouvre pas',
    ],
    // Vaulry : point d'arrêt fermé selon l'article (sans date) ; aucun train dans les horaires de 2026.
    [
        'station' => ['name' => 'Vaulry', 'uic' => '87592451'],
        'action' => 'current',
        'status' => 'declassified',
        'source_url' => $wp('Gare de Vaulry', 195455617),
        'quote' => 'Et est fermée au trafic actuellement',
        'reason' => 'halte fermée (sans date connue) ; aucun train ni car dans les horaires GTFS SNCF d\'avril, juin et novembre 2026',
    ],
    // Foulain : l'objet OSM est l'« emplacement de l'ancienne gare » ; liste SNCF ni voyageurs ni fret.
    [
        'station' => ['name' => 'Foulain', 'osm' => 'n14098359248'],
        'action' => 'current',
        'status' => 'declassified',
        'source_url' => $osm('n14098359248'),
        'quote' => 'Emplacement de l\'ancienne gare ferroviaire de Foulain',
        'reason' => 'ancienne gare (nom de l\'objet OSM ; liste SNCF des gares : ni voyageurs ni fret ; aucun train dans les horaires de 2026)',
    ],

    // ── 5. Données des fiches (chantier « donnees-gares », 19/11/2026) ───────────────────────────
    //
    // Constats de storage/app/reports/agents/ui-donnees-fiches.md (« Données fausses repérées »),
    // recherches dans storage/app/reports/agents/jour/donnees-gares-recherche-q18.md.

    // Ligne 610000 Limoges - Angoulême : haltes « non confirmées » de 1890 à 2010 faute de date propre
    // (fermeture déduite de la section Saillat-Chassenon - Angoulême, fermée le 13/03/2018).
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'uic' => $g[1]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2018-03-13',
        'precision' => 'day',
        'source_url' => $wp($g[2], $g[3]),
        'quote' => 'elle n\'est plus desservie par des trains depuis le 13 mars 2018',
        'reason' => 'fin de la desserte avec la fermeture de la section Saillat-Chassenon - Angoulême (cars TER ensuite)',
    ], [['Roumazières-Loubert', '87583468', 'Gare de Roumazières-Loubert', 222234533], ['Ruelle', '87583203', 'Gare de Ruelle', 222234559]]),
    [
        'station' => ['name' => 'Ruelle', 'uic' => '87583203'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Ruelle', 222234559),
        'quote' => 'Elle est mise en service en 1875 par la Compagnie des Charentes',
        'reason' => 'ouverture de la gare (VFF la déduisait de la ligne)',
    ],
    [
        'station' => ['name' => 'Roumazières-Loubert', 'uic' => '87583468'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-04-26',
        'precision' => 'day',
        'source_url' => $wp('Gare de Roumazières-Loubert', 222234533),
        'quote' => 'mise en service = 26 avril 1875',
        'reason' => 'date au jour de l\'infobox de l\'article (Wikidata : 1875 seulement)',
    ],
    [
        'station' => ['name' => 'Roumazières-Loubert', 'uic' => '87583468'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1875-01-01'],
        'source_url' => $wp('Gare de Roumazières-Loubert', 222234533),
        'reason' => 'remplacée par la date au jour de l\'article de la gare',
    ],
    // Verneuil-sur-Vienne : fermée aux voyageurs en 2017 (aucun train dans les horaires GTFS SNCF d'avril,
    // juin et novembre 2026, fréquentation 2025 nulle) ; la liste SNCF lui garde le fret.
    [
        'station' => ['name' => 'Verneuil-sur-Vienne', 'uic' => '87592675'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-04-26',
        'precision' => 'day',
        'source_url' => $wp('Gare de Verneuil-sur-Vienne', 223772559),
        'quote' => 'La compagnie met en service, le 26 avril 1875, sa ligne et donc la gare de Verneuil',
        'reason' => 'ouverture de la gare (VFF la déduisait de la ligne)',
    ],
    [
        'station' => ['name' => 'Verneuil-sur-Vienne', 'uic' => '87592675'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2017-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Verneuil-sur-Vienne', 223772559),
        'quote' => 'Elle est fermée au service des voyageurs en 2017',
        'reason' => 'fermeture au service des voyageurs (confirmée par l\'absence de tout train dans les horaires GTFS de 2026)',
    ],
    // Limoges-Bénédictins : ouverte le 02/06/1856 (Wikidata : 1856 seulement).
    [
        'station' => ['name' => 'Limoges-Bénédictins', 'uic' => '87592006'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-06-02',
        'precision' => 'day',
        'source_url' => $wp('Gare de Limoges-Bénédictins', 240488154),
        'quote' => 'Le 2 juin 1856, le premier train, un convoi de marchandises en provenance d\'Argenton-sur-Creuse, arrive en gare de Limoges',
        'reason' => 'date au jour de l\'article de la gare (première gare, remplacée par le bâtiment actuel en 1929)',
    ],
    [
        'station' => ['name' => 'Limoges-Bénédictins', 'uic' => '87592006'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1856-01-01'],
        'source_url' => $wp('Gare de Limoges-Bénédictins', 240488154),
        'reason' => 'remplacée par la date au jour de l\'article de la gare',
    ],
    // Brienne-le-Château : aucune ligne ne la dessert avant 1884 ; l'ouverture Wikidata « 1875 » est la
    // concession éventuelle de Jessains - Éclaron (31/12/1875). Ouverte avec Jessains - Éclaron.
    [
        'station' => ['name' => 'Brienne-le-Château', 'uic' => '87118752'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-04-10',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Jessains à Sorcy', 224485726),
        'quote' => 'Elle est finalement ouverte le 10 avril 1884',
        'reason' => 'ouverture de la ligne de Jessains à Éclaron par Brienne, première ligne de la gare (Troyes - Brienne en décembre 1886)',
    ],
    [
        'station' => ['name' => 'Brienne-le-Château', 'uic' => '87118752'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1875-01-01'],
        'source_url' => $wp('Ligne de Jessains à Sorcy', 224485726),
        'reason' => 'aucune ligne ne dessert Brienne en 1875 (date de la concession éventuelle de la ligne)',
    ],
    // Charantonnay : halte du tramway de Lyon à Saint-Marcellin (schéma Wikipédia), rattachée à tort à la
    // LGV Rhône-Alpes, qui la montrait ouverte de 1990 à 2020. Section Heyrieux - Saint-Jean-de-Bournay
    // fermée en 1935 ou 1936 : fermée au plus tard en 1936.
    [
        'station' => ['name' => 'Charantonnay', 'near' => [5.0936, 45.5433, 0.5]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1936-12-31',
        'precision' => 'before',
        'source_url' => $wp('Tramways de l\'Ouest du Dauphiné', 236094878),
        'quote' => 'Heyrieux - Saint-Jean-de-Bournay (23 novembre 1909 - 1935/36)',
        'reason' => 'fin du tramway entre Heyrieux et Saint-Jean-de-Bournay (cars à partir de 1937) ; la halte n\'a jamais été une gare de la LGV',
    ],
    // Troyes : PK 169,853 interpolé sur la ligne de Troyes à Brienne, 36 m de Saint-Julien-les-Villas.
    [
        'station' => ['name' => 'Troyes', 'uic' => '87118000'],
        'action' => 'pk',
        'line' => '012000',
        'pk_m' => 166193,
        'source_url' => $wp('Ligne de Troyes à Brienne-le-Château', 236561494),
        'quote' => 'BHF|166,193|Troyes',
        'reason' => 'PK de la gare de Troyes dans le schéma de la ligne (même PK que sur Paris-Est - Mulhouse : article de la gare, oldid 241434192)',
    ],

    // ── 6. Positions des gares (chantier « donnees-gares », 19/11/2026) ──────────────────────────
    //
    // Tri des 147 points SNCF à plus de 300 m de leur référence (storage/app/reports/agents/gares-position.md,
    // tri dans storage/app/reports/agents/jour/donnees-gares/tri-points-sncf.csv). Déplacées seulement quand
    // l'objet OSM de la gare (bâtiment voyageurs ou arrêt) et les coordonnées de l'article Wikipédia
    // concordent à moins de 35 m, sur la voie de la ligne (moins de 15 m du tracé), et que l'article donne le
    // PK de la liste SNCF à 200 m près (même gare) : le point SNCF, lui aussi sur la voie, est 0,5 à 1,7 km plus
    // loin, à l'écart de son propre PK.
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'uic' => $g[1]],
        'action' => 'position',
        'lng' => $g[2],
        'lat' => $g[3],
        'source_url' => $osm($g[4]),
        'quote' => $g[5],
        'reason' => $g[6],
    ], [
        ['Le Blanc', '87597211', 1.0662511, 46.6408492, 'w66915832', 'latitude = 46.64087, longitude = 1.066272',
            'bâtiment voyageurs OSM « Gare du Blanc » ; coordonnées et PK 407,8 de l\'article Gare du Blanc (oldid 240774659) ; point SNCF à 1 721 m'],
        ['Concremiers', '87597252', 1.0127365, 46.6004636, 'w182500831', 'latitude = 46.600566, longitude = 1.012838',
            'bâtiment voyageurs OSM « Gare de Concremiers » ; coordonnées et PK 400,144 de l\'article Gare de Concremiers (oldid 228675721) ; point SNCF à 568 m'],
        ['Talizat', '87783159', 3.0275431, 45.1107995, 'w970359982', 'latitude = 45.110816, longitude = 3.02759',
            'bâtiment voyageurs OSM (building=train_station) ; coordonnées et PK 702,971 de l\'article Gare de Talizat (oldid 231762671) ; point SNCF à 1 410 m'],
        ['Pellevoisin', '87576488', 1.420627, 46.9819771, 'n1960039507', 'latitude = 46.982004, longitude = 1.420452',
            'gare OSM « Pellevoisin » ; coordonnées et PK 268 de l\'article Gare de Pellevoisin (oldid 232043961) ; point SNCF à 485 m'],
    ]),

    // ── 7. Desserte du jour : trains touristiques et vélorail (chantier « donnees-corrections », 21/11/2026) ─────
    // Règles 5 et 6 de docs/METHODE-CLASSIFICATION.md (« Desserte du jour ») : une gare où seul un train
    // touristique s'arrête est touristique ; une gare de vélorail seul est fermée. Aucune de ces gares n'a de
    // train public dans les horaires GTFS de 2026 (rapport gares-desserte.md, groupe « passer en touristique »).
    [
        'station' => ['name' => 'Chamonix-Montenvers', 'wikidata' => 'Q105069647'],
        'action' => 'current',
        'status' => 'tourist',
        'source_url' => $wp('Chemin de fer du Montenvers', 239685415),
        'quote' => 'trafic = Touristique',
        'reason' => 'gare basse du chemin de fer à crémaillère du Montenvers, à côté de la gare SNCF de Chamonix-Mont-Blanc : train touristique seulement',
    ],
    [
        'station' => ['name' => 'Le Train des Mouettes - Saujon', 'osm' => 'n3887738106'],
        'action' => 'current',
        'status' => 'tourist',
        'source_url' => $wp('Gare de Saujon', 222377940),
        'quote' => 'À proximité de la gare de Saujon, se trouve la gare de départ du Train des mouettes',
        'reason' => 'gare de départ du Train des Mouettes (Saujon - La Tremblade) ; les TER desservent la gare SNCF voisine, une autre fiche',
    ],
    [
        'station' => ['name' => 'Troo', 'uic' => '87574772'],
        'action' => 'current',
        'status' => 'tourist',
        'source_url' => 'https://petitescitesdecaractere.com/sites/default/files/pcc_cvl_depliant_visite_troo_vnc.pdf',
        'quote' => 'La Gare de Trôo - train touristique',
        'reason' => 'desservie par le Train touristique de la vallée du Loir, aucun train TER sur la section (horaires GTFS SNCF de 2026)',
    ],
    [
        'station' => ['name' => 'Vélo-Rails Étretat Pays de Caux', 'osm' => 'w968921369'],
        'action' => 'current',
        'status' => 'declassified',
        'source_url' => $wp('Ligne des Ifs à Étretat', 238125959),
        'quote' => 'proposant une activité de vélo-rail combinée avec une circulation en draisine sur la portion de ligne comprise entre les gares des Loges-Vaucottes et d\'Étretat',
        'reason' => 'gare de vélorail : un vélorail n\'est pas une circulation ferroviaire (règle 6), la gare d\'Étretat est fermée',
    ],

    // ── 8. Contrôle à la main du 21/11/2026 (chantier « donnees-erreurs ») ──────────────────────────────
    // Erreurs relevées par storage/app/reports/agents/aprem-2111/controle-main-3.md (gares) et gares-planches.md,
    // vérifiées une à une (rapport aprem-2111/donnees-erreurs.md). Aucune des 42 gares minoritaires de G1.

    // 8 a. Hors du réseau ferré : station fantôme du métro de Paris et deux stations du métro de Lille.
    [
        'station' => ['name' => 'Champ de Mars', 'osm' => 'n4318849826'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $osm('n4318849826'),
        'quote' => 'disused:railway=station, wikipedia=fr:Champ de Mars (métro de Paris), wikidata=Q2687093',
        'reason' => 'station fantôme de la ligne 8 du métro de Paris (fermée le 02/09/1939), pas la gare RER du Champ-de-Mars - Tour Eiffel : hors du réseau ferré',
    ],
    [
        'station' => ['name' => 'Saint-Philibert', 'wikidata' => 'Q3463148'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => 'https://www.wikidata.org/wiki/Q3463148',
        'quote' => 'station du métro de Lille Métropole',
        'reason' => 'station du métro de Lille (VAL), hors du réseau ferré',
    ],
    [
        'station' => ['name' => 'Square Flandres', 'wikidata' => 'Q3237457'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => 'https://www.wikidata.org/wiki/Q3237457',
        'quote' => 'station du métro de Lille Métropole',
        'reason' => 'station du métro de Lille (VAL), hors du réseau ferré',
    ],

    // 8 b. Liens Wikidata posés sur une autre gare : leurs dates ne valent pas pour la fiche. Le lien lui-même
    // reste à défaire à l'import (aucune action de correction ne le permet, voir le rapport).
    [
        'station' => ['name' => 'St-Vidal', 'uic' => '87731844'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1866-01-01'],
        'source_url' => 'https://www.wikidata.org/wiki/Q1891124',
        'quote' => 'Le Puy-en-Velay, gare ferroviaire française',
        'reason' => 'Q1891124 est la gare du Puy-en-Velay ; la halte de Saint-Vidal ouvre avec Saint-Georges-d\'Aurac - Le Puy le 18/05/1874 (OHM)',
    ],
    [
        'station' => ['name' => 'Néronde-sur-Dore', 'uic' => '87731653'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1885-05-05'],
        'source_url' => 'https://www.wikidata.org/wiki/Q13406478',
        'quote' => 'Ambert, gare ferroviaire française',
        'reason' => 'Q13406478 est la gare d\'Ambert (ouverte avec Giroux - Ambert le 05/05/1885) ; Néronde-sur-Dore est sur Pont-de-Dore - Giroux, ouverte le 09/06/1883',
    ],

    // 8 c. Dates d'ouverture contredites par l'article de la gare (l'estimation par la ligne ou la date OHM est fausse).
    [
        'station' => ['name' => 'Pouilly-sur-Loire', 'uic' => '87696179'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1861-09-21',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pouilly-sur-Loire', 238453968),
        'quote' => 'mise en service = 21 septembre 1861',
        'reason' => 'VFF déduisait 1827 de la première ouverture de la ligne 750000',
    ],
    [
        'station' => ['name' => 'Effrico', 'wikidata' => 'Q120758366'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2014-03-10',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Effrico', 218874933),
        'quote' => 'La halte d\'Effrico est mise en service le 10 mars 2014 par les CFC.',
        'reason' => 'halte récente : VFF lui donnait l\'ouverture de la ligne (1888)',
    ],
    [
        'station' => ['name' => 'Samatan', 'wikidata' => 'Q56311130'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1901-04-06',
        'precision' => 'day',
        'source_url' => $wp('Gare de Samatan', 241491471),
        'quote' => 'La gare de Samatan est mise en service le 6 avril 1901 par la compagnie des chemins de fer du Sud-Ouest.',
        'reason' => 'date au jour de l\'article de la gare',
    ],
    [
        'station' => ['name' => 'Samatan', 'wikidata' => 'Q56311130'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1900-01-01'],
        'source_url' => $wp('Gare de Samatan', 241491471),
        'reason' => 'année OHM contredite par l\'article de la gare (06/04/1901)',
    ],
    [
        'station' => ['name' => 'Aytré-Plage', 'uic' => '87437806'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2008-12-14',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Aytré-Plage', 241307671),
        'quote' => 'mise en service = 14 décembre 2008',
        'reason' => 'halte ouverte en 2008 : l\'estimation par la ligne (1866) et l\'année Wikidata (1873) sont celles de la ligne',
    ],
    [
        'station' => ['name' => 'Lexos', 'uic' => '87613489'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1858-08-30',
        'precision' => 'day',
        'source_url' => $wp('Gare de Lexos', 237763842),
        'quote' => 'mise en service = 30 août 1858',
        'reason' => 'date au jour de l\'article de la gare ; l\'année OHM 1883 est celle d\'une autre section',
    ],
    [
        'station' => ['name' => 'Lexos', 'uic' => '87613489'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1883-01-01'],
        'source_url' => $wp('Gare de Lexos', 237763842),
        'reason' => 'année OHM contredite par l\'article de la gare (30/08/1858)',
    ],
    [
        'station' => ['name' => 'Commentry', 'uic' => '87641415'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-11-07',
        'precision' => 'day',
        'source_url' => $wp('Gare de Commentry', 241300246),
        'quote' => 'mise en service = 7 novembre 1859',
        'reason' => 'date au jour de l\'article de la gare (VFF n\'avait que l\'année, estimée par la ligne)',
    ],
    [
        'station' => ['name' => 'Firminy', 'uic' => '87726729'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-05-30',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'quote' => 'le 30 mai 1859 entre Firminy et Saint-Étienne',
        'reason' => 'Firminy est le terminus de la première section de la ligne ; l\'article de la gare ne donne pas de date',
    ],
    [
        'station' => ['name' => 'Firminy', 'uic' => '87726729'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1832-01-01'],
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'reason' => 'année de la ligne de Saint-Étienne à Lyon, pas de la gare de Firminy',
    ],
    [
        'station' => ['name' => 'Firminy', 'uic' => '87726729'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1863-11-09'],
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'reason' => 'ouverture de la section suivante (Pont-de-Lignon - Firminy), la gare est ouverte depuis le 30/05/1859',
    ],

    // 8 d. Fins des voyageurs données par l'infobox de l'article de la gare.
    [
        'station' => ['name' => 'St-Martin-Labouval', 'uic' => '87613562'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Martin-Labouval', 239161418),
        'quote' => 'fermeture = 28 septembre 1980 (voyageurs)',
        'reason' => 'VFF la fermait en 2011 ; fermée aux voyageurs en 1980',
    ],
    [
        'station' => ['name' => 'Hémonstoir-St-Gonnery', 'uic' => '87473397'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1987-09-27',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Hémonstoir - Saint-Gonnery', 233473504),
        'quote' => 'Le 27 septembre 1987, le trafic des trains de voyageurs s\'arrête définitivement entre Pontivy et Loudéac',
        'reason' => 'VFF la fermait en 2020 ; fermée aux voyageurs en 1987',
    ],
    [
        'station' => ['name' => 'Piennes', 'uic' => '87192831'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Piennes', 209702361),
        'quote' => 'fermeture = 15 mai 1939 (voyageurs), 1991 (marchandises)',
        'reason' => 'VFF la fermait le 17/10/1984 ; fermée aux voyageurs en 1939',
    ],
    [
        'station' => ['name' => 'Piennes', 'uic' => '87192831'],
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1991-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Piennes', 209702361),
        'quote' => 'fermeture = 15 mai 1939 (voyageurs), 1991 (marchandises)',
        'reason' => 'fin du fret selon l\'infobox de l\'article de la gare',
    ],
    [
        'station' => ['name' => 'St-Jean-Pla-de-Corts', 'uic' => '87784355'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-23',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Jean-Pla-de-Corts', 235034243),
        'quote' => 'fermeture = 23 mai 1940 (voyageurs)',
        'reason' => 'fin des voyageurs absente de VFF ; la gare reste ouverte au fret',
    ],

    // 8 e. Positions reprises sur le bâtiment voyageurs ou la gare OSM (contrôle à la main sur la BD ORTHO) :
    // le point VFF était sur la voie, de 113 à 725 m de la gare.
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'uic' => $g[1]],
        'action' => 'position',
        'lng' => $g[2],
        'lat' => $g[3],
        'source_url' => $osm($g[4]),
        'quote' => $g[5],
        'reason' => $g[6],
    ], [
        ['Vitrolles', '87753269', 5.236651, 43.450726, 'w101284887', 'name=Ancienne Gare de Vitrolles, building=train_station',
            'bâtiment de l\'ancienne gare (OSM) ; point VFF à 245 m sur la voie'],
        ['Espère-Caillac', '87613216', 1.384361, 44.507500, 'w134937724', 'name=Espère - Caillac, disused:railway=station',
            'bâtiment de l\'ancienne gare (OSM) ; point VFF à 185 m, au passage à niveau'],
        ['St-Cergues-les-Voirons', '87745570', 6.302576, 46.225721, 'w183374729', 'name=Ancienne gare de Saint-Cergues-Les Voirons',
            'bâtiment de l\'ancienne gare (OSM), d\'accord avec Wikidata ; point VFF à 175 m sur la voie'],
        ['Senozan', '87725671', 4.870542, 46.391467, 'w713451107', 'building=train_station',
            'bâtiment voyageurs (OSM), à côté de la gare OSM n6708159886 ; point VFF à 113 m au sud sur la voie'],
        ['Pithiviers', '87543769', 2.241790, 48.173547, 'w69168210', 'name=Pithiviers, building=train_station, disused:railway=station',
            'bâtiment de la gare (OSM, Wikidata) ; point VFF à 725 m au nord sur la voie'],
        ['St-Jean-Pla-de-Corts', '87784355', 2.784523, 42.513814, 'w1182420160', 'name=Gare ferroviaire Saint-Jean-Pla-de-Corts (FRET)',
            'gare OSM (fret) ; point VFF à 457 m à l\'est sur la ligne'],
    ]),

    // ── 9. Fin des voyageurs datée par Wikidata comme une fermeture totale (chantier « gares-fret », 21/11/2026) ──
    // Wikidata (P3999) ou OHM ferment la gare à la date où l'infobox de l'article ne ferme que le service des
    // voyageurs ; le fret a continué jusqu'à la date que la même infobox donne (rapport
    // storage/app/reports/agents/aprem-2111/gares-fret.md, deuxième source : la fin du fret de la ligne dans VFF,
    // article de la ligne ou lignes-oubliées). Pour chaque gare : la fermeture Wikidata/OHM est retirée, la fin
    // des voyageurs et la fin du fret (fermeture totale) sont posées. « before » : seule la fermeture de la ligne
    // est connue, le fret de la gare a cessé au plus tard à cette date.
    ...array_merge(...array_map(fn (array $g) => [
        ...array_map(fn (array $m) => [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'neutralize',
            'match' => ['source' => $m[0], 'type' => $m[1], 'date' => $m[2]],
            'source_url' => $wp($g[2], $g[3]),
            'quote' => $g[8],
            'reason' => 'la fermeture '.($m[0] === 'ohm' ? 'OHM' : 'Wikidata').' est la fin du service des voyageurs ; le fret a continué',
        ], $g[9]),
        [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'add',
            'type' => 'closure_passenger',
            'date' => $g[4],
            'precision' => $g[5],
            'source_url' => $wp($g[2], $g[3]),
            'quote' => $g[8],
            'reason' => 'fin des voyageurs selon l\'infobox de l\'article de la gare',
        ],
        [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'add',
            'type' => 'closure',
            'date' => $g[6],
            'precision' => $g[7],
            'source_url' => ($g[10] ?? [])['source_url'] ?? $wp($g[2], $g[3]),
            'quote' => ($g[10] ?? [])['quote'] ?? $g[8],
            'reason' => ($g[10] ?? [])['reason'] ?? ($g[7] === 'before'
                ? 'fermeture de la ligne selon l\'infobox : fin du fret de la gare au plus tard à cette date'
                : 'fin du fret, donc de toute desserte, selon l\'infobox de l\'article de la gare'),
        ],
    ], [
        // gare, Wikidata, article, révision, fin des voyageurs, précision, fin du fret, précision, champ cité,
        // fermetures retirées [source, type, date]
        ['Angoville-sur-Ay', 'Q3095636', 'Gare d\'Angoville-sur-Ay', 237264528, '1970-01-01', 'year', '1973-05-14', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']], [
            'source_url' => $wp('Ligne de Coutances à Sottevast', 240949203),
            'quote' => 'La ligne est fermée au service marchandises : [...] section de Lessay à La Haye-du-Puits, le 14 mai 1973',
            'reason' => 'la gare est entre Lessay et La Haye-du-Puits : le fret y cesse le 14/05/1973 (article de la ligne) ; le 24/01/1988 de l\'infobox de la gare est la fin des sections Coutances - Périers et La Haye-du-Puits - Sottevast',
        ]],
        ['Anould', 'Q2175387', 'Gare d\'Anould', 195862206, '1939-01-08', 'day', '2010-02-01', 'day', 'Fermeture : 8 janvier 1939 (voyageurs) 1er février 2010 (fret)', [['wikidata', 'closure', '1939-01-01']]],
        ['Maison des Associations d\'Aubenas', 'Q2529671', 'Gare d\'Aubenas', 235981496, '1969-03-09', 'day', '1988-04-01', 'day', 'Fermeture : 9 mars 1969 (voyageurs) 1er avril 1988 (fret)', [['wikidata', 'closure', '1969-03-09']]],
        ['Aubignas-Alba', 'Q125889075', 'Gare d\'Aubignas - Alba', 237570555, '1969-03-09', 'day', '1971-12-01', 'day', 'Fermeture : 9 mars 1969 aux voyageurs 1 décembre 1971 aux frets', [['wikidata', 'closure', '1969-03-09']]],
        ['Otterswiller', 'Q125468735', 'Gare d\'Otterswiller', 238409224, '1969-01-01', 'year', '1980-01-01', 'before', 'Fermeture : 1969 (voyageurs) 1980 (ligne)', [['wikidata', 'closure', '1969-01-01']]],
        ['Bapaume', 'Q21932011', 'Gare de Bapaume', 238680685, '1969-01-01', 'year', '2010-01-01', 'year', 'Fermeture : * 1969 (voyageurs) * 2010 (fret)', [['wikidata', 'closure', '1969-01-01']]],
        ['Berjou', 'Q3095969', 'Gare de Berjou', 171594393, '1970-01-01', 'year', '1979-01-01', 'year', 'Fermeture : 1970(voyageurs) 1979(marchandises) 1993(touristique)', [['wikidata', 'closure', '1970-01-01']]],
        ['Blain', 'Q3096000', 'Gare de Blain', 231550159, '1939-01-01', 'year', '1952-01-01', 'year', 'Fermeture : 1939 voyageurs 1952 marchandises', [['wikidata', 'closure', '1939-01-01']]],
        ['Bricquebec', 'Q3096064', 'Gare de Bricquebec', 235031035, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
        ['Bézenet', 'Q106377039', 'Gare de Bézenet', 222213944, '1932-10-02', 'day', '1964-07-01', 'day', 'Fermeture : 2 octobre 1932 (voyageurs) 1 juillet 1964 (marchandises)', [['wikidata', 'closure', '1932-10-02']]],
        ['Caux', 'Q16765107', 'Gare de Caux', 190994162, '1970-11-14', 'day', '1972-04-03', 'day', 'Fermeture : 14 novembre 1970 (voyageurs) 3 avril 1972 (marchandises)', [['wikidata', 'closure', '1970-01-01']]],
        ['Clermont-l\'Hérault', 'Q135742683', 'Gare de Clermont-l\'Hérault', 229054673, '1939-05-15', 'day', '1990-01-01', 'year', 'Fermeture : 15 mai 1939 (voyageurs) Années 1990 (marchandises)', [['wikidata', 'closure', '1939-05-15']]],
        ['Condé-sur-Noireau', 'Q2234022', 'Gare de Condé-sur-Noireau', 220863501, '1970-01-01', 'year', '1979-01-01', 'year', 'Fermeture : 1970 (voyageurs) 1979 (marchandises)', [['wikidata', 'closure', '1970-01-01']]],
        ['Cornimont', 'Q3096289', 'Gare de Cornimont', 228936672, '1989-05-28', 'day', '1992-09-27', 'day', 'Fermeture : 28 mai 1989 voyageurs 27 septembre 1992 marchandises', [['wikidata', 'closure', '1989-01-01']]],
        ['Dozulé - Putot', 'Q2005012', 'Gare de Dozulé - Putot', 229799378, '1938-01-01', 'year', '1969-01-01', 'year', 'Fermeture : 1938 (voyageurs) 1969 (marchandises)', [['wikidata', 'closure', '1938-01-01']]],
        ['Faymont', 'Q3096417', 'Gare de Faymont', 195935443, '1942-05-01', 'day', '1991-01-01', 'day', 'Fermeture : 1er mai 1942 voyageurs 1er janvier 1991 marchandises', [['wikidata', 'closure', '1942-01-01']]],
        ['Fougères', 'Q56310012', 'Gare de Fougères', 238933294, '1972-01-01', 'year', '1991-01-01', 'year', 'Fermeture : 1972 (voyageurs) 1991 (fret)', [['wikidata', 'closure', '1972-03-06']]],
        ['Fraize', 'Q2634179', 'Gare de Fraize', 200643230, '1939-01-08', 'day', '1988-01-01', 'year', 'Fermeture : 8 janvier 1939 (voyageurs) 1988 fermeture marchandises 1993 déclassement', [['wikidata', 'closure', '1939-01-01']]],
        ['Gabian', 'Q16765102', 'Gare de Gabian', 231934929, '1970-11-14', 'day', '1972-04-03', 'day', 'Fermeture : 14 novembre 1970 (voyageurs) 3 avril 1972 (marchandises)', [['wikidata', 'closure', '1970-01-01']]],
        ['Godewaersvelde', 'Q3096551', 'Gare de Godewaersvelde', 218605271, '1954-05-23', 'day', '1970-09-26', 'day', 'Fermeture : 23 mai 1954 voyageurs 26 septembre 1970 marchandises', [['wikidata', 'closure', '1954-01-01']]],
        ['La Chapelle-Gauthier', 'Q111828277', 'Gare de La Chapelle-Gauthier', 235032405, '1934-01-19', 'day', '1956-09-30', 'day', 'Fermeture : 19 janvier 1934 (voyageurs) 30 septembre 1956 (marchandises)', [['wikidata', 'demolition', '1934-01-19']]],
        ['La Chapelle-Yvon', 'Q111902817', 'Gare de La Chapelle-Yvon', 235032406, '1934-01-19', 'day', '1956-07-01', 'day', 'Fermeture : 19 janvier 1934 (voyageurs) 1 juillet 1956 (marchandises)', [['wikidata', 'demolition', '1934-01-19']]],
        ['Lessay', 'Q1966097', 'Gare de Lessay', 231943184, '1970-01-01', 'year', '1987-05-31', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']], [
            'source_url' => $wp('Ligne de Coutances à Sottevast', 240949203),
            'quote' => 'La ligne est fermée au service marchandises : [...] section de Périers-en-Cotentin à Lessay, le 31 mai 1987 [...] section de Lessay à La Haye-du-Puits, le 14 mai 1973',
            'reason' => 'Lessay n\'est plus relié que par la section Périers - Lessay, fermée au fret le 31/05/1987 (chronologie de l\'article de la ligne, reprise par Wikimanche) ; le 24/01/1988 de l\'infobox de la gare est la fin des sections Coutances - Périers et La Haye-du-Puits - Sottevast, seules exploitées en 1988 (Argentan Web Ferroviaire)',
        ]],
        ['Lincel - Saint-Martin', 'Q3096890', 'Gare de Lincel - Saint-Martin', 206958157, '1934-01-15', 'day', '1941-05-05', 'day', 'Fermeture : 15 janvier 1934 (voyageurs) 1956 (marchandises)', [['wikidata', 'closure', '1934-01-01']], [
            'source_url' => 'https://www.lignes-oubliees.com/index.php?page=stmaime',
            'quote' => 'Fermeture aux marchandises : Saignon - Biabaux : 05/05/1941',
            'reason' => 'la gare (PK 62,9) est sur Saignon - Biabaux, déclassée le 30/11/1941 (loi, article de la ligne, oldid 241491046) ; le 1956 de l\'infobox est la fin du fret de Biabaux - Saint-Maime',
        ]],
        ['Livarot', 'Q28496166', 'Gare de Livarot', 241104408, '1938-05-05', 'day', '1990-09-30', 'day', 'Fermeture : 5 mai 1938 (voyageurs) 30 septembre 1990 (marchandises)', [['wikidata', 'closure', '1938-01-01']]],
        ['Luxey', 'Q21980009', 'Gare de Luxey', 209217400, '1955-01-01', 'year', '1970-12-31', 'day', 'Fermeture : 1955 (voyageurs) 31 décembre 1970 (fermeture totale de la gare)', [['wikidata', 'closure', '1955-01-01']]],
        ['Lyon-Croix-Rousse', 'Q19406999', 'Gare de Lyon-Croix-Rousse', 235032836, '1953-01-01', 'year', '1975-01-01', 'year', 'Fermeture : 1953 (voyageurs) 1975 (marchandises)', [['wikidata', 'closure', '1953-01-01']]],
        ['Malestroit', 'Q2831152', 'Gare de Malestroit', 197759392, '1938-01-01', 'year', '1991-01-01', 'year', 'Fermeture : 1938 desserte voyageurs 1991 desserte marchandises', [['wikidata', 'closure', '1938-01-01']]],
        ['Marines', 'Q1933410', 'Gare de Marines (Halte)', 235032966, '1940-01-01', 'year', '1951-01-01', 'year', 'Fermeture : 1940 (voyageurs) 1951 (marchandises)', [['wikidata', 'closure', '1940-01-01']]],
        ['Coustelet', 'Q3096999', 'Gare de Maubec', 241520664, '1938-01-01', 'year', '1988-05-27', 'before', 'Fermeture : 1938 fermeture voyageurs 1988 fermeture Cavaillon-Apt 1991 déclassement Cavaillon-Apt', [['wikidata', 'closure', '1938-01-01']]],
        ['Mauron', 'Q2219316', 'Gare de Mauron', 238688246, '1971-01-01', 'year', '1998-01-01', 'year', 'Fermeture : 1971 (trafic voyageurs) 1998 (fermeture trafic marchandise)', [['wikidata', 'closure', '1971-01-01']]],
        ['Mesnil-Mauger', 'Q28496157', 'Gare de Mesnil-Mauger', 216906499, '1938-01-01', 'year', '1990-01-01', 'year', 'Fermeture : 1938 (voyageurs) 1990 (marchandises)', [['wikidata', 'closure', '1938-01-01']]],
        ['Millières', 'Q2423588', 'Gare de Millières', 166059366, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
        ['Ménil-Hubert - Pont d\'Ouilly', 'Q2165344', 'Gare de Ménil-Hubert - Pont d\'Ouilly', 193970072, '1938-01-01', 'year', '1969-01-01', 'year', 'Fermeture : 1938 voyageurs 1969 marchandises', [['wikidata', 'closure', '1938-01-01']]],
        ['Nizas - Fontès', 'Q16765187', 'Gare de Nizas - Fontès', 241202361, '1970-11-14', 'day', '1972-04-03', 'day', 'Fermeture : 14 novembre 1970 (voyageurs) 3 avril 1972 (marchandises)', [['wikidata', 'closure', '1970-01-01']]],
        ['Nucourt', 'Q2165585', 'Gare de Nucourt', 235033639, '1957-01-01', 'year', '1987-01-01', 'year', 'Fermeture : 1957 (voyageurs) 1987 (marchandises)', [['wikidata', 'closure', '1957-01-01']]],
        ['Néhou', 'Q3097233', 'Gare de Néhou', 232684071, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
        ['Pierrefitte-Nestalas', 'Q27862065', 'Gare de Pierrefitte-Nestalas', 221675468, '1970-09-27', 'day', '1993-08-10', 'day', 'Fermeture : 27 septembre 1970 (voyageurs) 10 août 1993 (marchandises)', [['wikidata', 'closure', '1970-09-27']]],
        ['Pleucadeuc', 'Q2654768', 'Gare de Pleucadeuc', 196084966, '1938-01-01', 'year', '1991-01-01', 'year', 'Fermeture : 1938 desserte voyageurs 1991 desserte marchandises', [['wikidata', 'closure', '1938-01-01']]],
        ['Pont-Érambourg', 'Q2746691', 'Gare de Pont-Érambourg', 232243007, '1970-01-01', 'year', '1979-01-01', 'year', 'Fermeture : 1970 (voyageurs) 1979 (marchandises)', [['wikidata', 'closure', '1970-01-01']]],
        ['Pouilly-sous-Charlieu', 'Q115057744', 'Gare de Pouilly-sous-Charlieu', 236625687, '1940-05-20', 'day', '2005-05-01', 'month', 'Fermeture : 20 mai 1940 (voyageurs) mai 2005 (Marchandises)', [['ohm', 'closure', '1940-05-20']]],
        ['Périers-en-Cotentin', 'Q3097358', 'Gare de Périers-en-Cotentin', 179659614, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
        ['Rehaincourt', 'Q3097386', 'Gare de Rehaincourt', 231900599, '1935-01-01', 'year', '1939-01-01', 'year', 'Fermeture : 1935 (voyageurs) 1939 (marchandises)', [['wikidata', 'closure', '1935-01-01']]],
        ['Reuilly', 'Q3097399', 'Gare de Reuilly (Paris)', 239997910, '1969-01-01', 'year', '1985-01-01', 'year', 'Fermeture : 1969 voyageurs 1985 marchandises', [['wikidata', 'closure', '1969-01-01']]],
        ['Roc-Saint-André - La Chapelle', 'Q2097876', 'Gare de Roc-Saint-André - La Chapelle', 229525590, '1938-01-01', 'year', '1991-01-01', 'year', 'Fermeture : 1938 desserte voyageurs 1991 desserte marchandises', [['wikidata', 'closure', '1938-01-01']]],
        ['Roujan - Neffiès', 'Q16964681', 'Gare de Roujan - Neffiès', 236806953, '1970-11-14', 'day', '1972-04-03', 'day', 'Fermeture : 14 novembre 1970 (voyageurs) 3 avril 1972 (marchandises)', [['wikidata', 'closure', '1970-01-01']]],
        ['Saint-Désiré', 'Q24037693', 'Gare de Saint-Désiré', 222484739, '1969-01-01', 'year', '1987-01-01', 'year', 'Fermeture : 1969 (voyageurs) 1987 (marchandises)', [['wikidata', 'closure', '1969-01-01']]],
        ['Saint-Julien-le-Faucon', 'Q111744594', 'Gare de Saint-Julien-le-Faucon', 235034248, '1938-05-05', 'day', '1990-09-30', 'day', 'Fermeture : 5 mai 1938 (voyageurs) 30 septembre 1990 (marchandises)', [['wikidata', 'demolition', '1938-05-05']]],
        ['Saint-Maime - Dauphin', 'Q3097564', 'Gare de Saint-Maime - Dauphin', 206958240, '1934-01-15', 'day', '1956-01-01', 'year', 'Fermeture : 15 janvier 1934 (voyageurs) 1956 (marchandises)', [['wikidata', 'closure', '1934-01-01']]],
        ['Saint-Martin-de-Bienfaite', 'Q111902829', 'Gare de Saint-Martin-de-Bienfaite', 235034273, '1934-01-19', 'day', '1956-07-01', 'day', 'Fermeture : 19 janvier 1934 (voyageurs) 1 juillet 1956 (marchandises)', [['wikidata', 'demolition', '1934-01-19']]],
        ['St-Riquier', 'Q17373218', 'Gare de Saint-Riquier', 233575159, '1956-01-01', 'year', '1989-01-01', 'year', 'Fermeture : * 1956 (voyageurs) * 1989 (fret)', [['wikidata', 'closure', '1956-01-01']]],
        ['St-Rémy (Calvados)', 'Q2327552', 'Gare de Saint-Rémy (Calvados)', 190545956, '1970-01-01', 'year', '1979-01-01', 'year', 'Fermeture : 1970 (voyageur) 1979 (marchandise) 1993 (touristique)', [['wikidata', 'closure', '1970-01-01']]],
        ['Saint-Sauveur-Lendelin', 'Q3097614', 'Gare de Saint-Sauveur-Lendelin', 231564945, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
        ['Saint-Sauveur-le-Vicomte', 'Q2390229', 'Gare de Saint-Sauveur-le-Vicomte', 211298163, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
        ['Sainte-Foy-de-Montgommery', 'Q111754630', 'Gare de Sainte-Foy-de-Montgommery', 235034329, '1938-05-05', 'day', '1990-09-30', 'day', 'Fermeture : 5 mai 1938 (voyageurs) 30 septembre 1990 (marchandises)', [['wikidata', 'demolition', '1938-05-05']]],
        ['Saulxures-sur-Moselotte', 'Q3097687', 'Gare de Saulxures-sur-Moselotte', 229817213, '1989-05-28', 'day', '1992-09-27', 'day', 'Fermeture : 28 mai 1989 voyageurs 27 septembre 1992 marchandises', [['wikidata', 'closure', '1989-01-01']]],
        ['Syndicat - Saint-Amé', 'Q3097799', 'Gare de Syndicat - Saint-Amé', 229766096, '1989-05-28', 'day', '1992-09-27', 'day', 'Fermeture : 28 mai 1989 voyageurs 27 septembre 1992 marchandises', [['wikidata', 'closure', '1989-01-01']]],
        ['Thiéfosse', 'Q3097862', 'Gare de Thiéfosse', 229471367, '1989-05-28', 'day', '1992-09-27', 'day', 'Fermeture : 28 mai 1989 voyageurs 27 septembre 1992 marchandises', [['wikidata', 'closure', '1989-01-01']]],
        ['Thuir', 'Q135847075', 'Gare de Thuir', 236850146, '1953-01-01', 'day', '1989-01-01', 'day', 'Fermeture : 1 janvier 1953 (voyageurs) 1 janvier 1989 (marchandises)', [['wikidata', 'closure', '1953-01-01']]],
        ['Vagney', 'Q2734261', 'Gare de Vagney', 228786100, '1989-05-28', 'day', '1992-09-27', 'day', 'Fermeture : 28 mai 1989 voyageurs 27 septembre 1992 marchandises', [['wikidata', 'closure', '1989-01-01']]],
        ['Vivier-au-Court', 'Q15983659', 'Gare de Vivier-au-Court', 228761453, '1930-07-15', 'day', '1991-05-31', 'day', 'Fermeture : 15 juillet 1930 (voyageurs) 31 mai 1991 (marchandises)', [['wikidata', 'closure', '1930-01-01']]],
        ['Vougy', 'Q115052887', 'Gare de Vougy', 210136806, '1940-05-20', 'day', '2013-06-06', 'before', 'Fermeture : 20 mai 1940 (voyageurs) 6 juin 2013 (ligne)', [['ohm', 'closure', '1940-05-20']]],
        ['Vrigne-aux-Bois', 'Q15983720', 'Gare de Vrigne-aux-Bois', 183436362, '1930-07-15', 'day', '1943-01-01', 'year', 'Fermeture : 15 juillet 1930 (voyageurs) 1943 (marchandises)', [['wikidata', 'closure', '1930-01-01']]],
        ['Zainvillers', 'Q682526', 'Gare de Zainvillers', 229097026, '1989-05-28', 'day', '1992-09-27', 'day', 'Fermeture : 28 mai 1989 voyageurs 27 septembre 1992 marchandises', [['wikidata', 'closure', '1989-01-01']]],
        ['Les Bains-d\'Évian', 'Q2419512', 'Gare des Bains-d\'Évian', 228313502, '1938-01-01', 'year', '1988-01-01', 'year', 'Fermeture : 1938 (service voyageurs) 1988 (service marchandises)', [['wikidata', 'closure', '1938-01-01']]],
        ['Le Bec-Hellouin', 'Q112031227', 'Gare du Bec-Hellouin', 241141807, '1969-09-27', 'day', '1971-02-01', 'day', 'Fermeture : 27 septembre 1969 (voyageurs) 1 février 1971 (marchandises)', [['wikidata', 'demolition', '1969-09-27']]],
        ['Le Grand-Pressigny', 'Q3098202', 'Gare du Grand-Pressigny', 194600882, '1940-06-27', 'day', '2005-01-01', 'year', 'Fermeture : 27 juin 1940 (voyageurs) 2005 (trafic marchandises)', [['wikidata', 'closure', '1940-01-01']]],
        ['Le Lion-d\'Or - Croissanville', 'Q111829642', 'Gare du Lion-d\'Or - Croissanville', 235035344, '1938-03-01', 'day', '1969-11-03', 'day', 'Fermeture : 1 mars 1938 (voyageurs) 3 novembre 1969 (marchandises)', [['wikidata', 'demolition', '1938-03-01']]],
        ['Le Val-d\'Ajol', 'Q3098242', 'Gare du Val-d\'Ajol', 231592413, '1942-05-01', 'day', '1991-01-01', 'day', 'Fermeture : 1er mai 1942 voyageurs 1er janvier 1991 marchandises', [['wikidata', 'closure', '1942-01-01']]],
        ['Le Vigan', 'Q135768367', 'Gare du Vigan', 238340579, '1969-03-09', 'day', '1987-04-01', 'day', 'Fermeture : 9 mars 1969 (voyageurs) 1 avril 1987 (marchandises)', [['wikidata', 'closure', '1969-03-09']]],
    ])),

    // 9 b. Même cas, fin du fret lue dans le texte de l'article de la gare (l'infobox ne date que les voyageurs).
    // Le fret de la section Iguerande - Paray-le-Monial cesse le 24/05/1990 (article de la gare de Marcigny,
    // lignes-oubliées). Ligne de Vincennes (Boissy - Verneuil) : section limitée au fret après les voyageurs,
    // fermée le 14/12/1969 (article de la ligne). Peyraud : fermée au fret après 1973 ; ligne fermée au fret le 06/11/1987 (lignes-oubliées).
    ...array_merge(...array_map(fn (array $g) => [
        [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'neutralize',
            'match' => ['source' => $g[9], 'type' => 'closure', 'date' => $g[10]],
            'source_url' => $wp($g[2], $g[3]),
            'quote' => $g[8],
            'reason' => 'la fermeture '.($g[9] === 'ohm' ? 'OHM' : 'Wikidata').' est la fin du service des voyageurs ; le fret a continué',
        ],
        [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'add',
            'type' => 'closure_passenger',
            'date' => $g[4],
            'precision' => $g[5],
            'source_url' => $wp($g[2], $g[3]),
            'quote' => $g[8],
            'reason' => 'fin des voyageurs selon l\'infobox de l\'article de la gare',
        ],
        [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'add',
            'type' => 'closure',
            'date' => $g[6],
            'precision' => $g[7],
            'source_url' => $wp($g[2], $g[3]),
            'quote' => $g[8],
            'reason' => $g[7] === 'before'
                ? 'fin du fret de la gare au plus tard à la fermeture de la section au fret'
                : 'fin du fret, donc de toute desserte, selon l\'article de la gare',
        ],
    ], [
        // gare, Wikidata, article, révision, fin des voyageurs, précision, fin du fret, précision, citation,
        // source et date de la fermeture retirée
        ['Cajarc', 'Q16008551', 'Gare de Cajarc', 240303811, '1980-09-28', 'day', '1989-09-01', 'month',
            'Le service marchandises s\'est arrêté en septembre 1989.', 'wikidata', '1980-01-01'],
        ['Dole-la-Bedugue', 'Q3096375', 'Gare de Dole-La Bedugue', 229621812, '1938-05-05', 'day', '2005-06-01', 'month',
            'Cette gare est ouverte, jusqu\'en juin 2005, au service du fret de l\'entreprise américaine Ideal Standard', 'wikidata', '1938-05-05'],
        ['Lodève', 'Q135742970', 'Gare de Lodève', 234260646, '1939-05-15', 'day', '1981-05-31', 'day',
            'La gare est fermée au trafic marchandises le 31 mai 1981 lors de la fermeture du tronçon entre Cartels et Lodève', 'wikidata', '1939-05-15'],
        ['Marcigny', 'Q72698168', 'Gare de Marcigny', 229509122, '1940-05-20', 'day', '1990-05-24', 'day',
            'La fermeture aux marchandises a lieu le 24 mai 1990 d\'Iguerande à Paray-le-Monial', 'ohm', '1940-05-20'],
        ['Montceaux-Vindecy', 'Q115129176', 'Gare de Montceaux - Vindecy', 235033213, '1940-05-20', 'day', '1990-05-24', 'day',
            'La fermeture aux marchandises a lieu le 24 mai 1990 d\'Iguerande à Paray-le-Monial.', 'ohm', '1940-05-20'],
        ['St-Yan', 'Q115130886', 'Gare de Saint-Yan', 235034315, '1940-05-20', 'day', '1990-05-24', 'day',
            'La fermeture aux marchandises a lieu le 24 mai 1990 d\'Iguerande à Paray-le-Monial.', 'ohm', '1940-05-20'],
        ['Peyraud', 'Q106708663', 'Gare de Peyraud', 232081317, '1973-01-01', 'year', '1987-11-06', 'before',
            'Elle est mise en service en 1869, ferme aux voyageurs en 1973 puis aux marchandises.', 'ohm', '1973-01-01'],
        ['Coubert - Soignolles', 'Q3096301', 'Gare de Coubert - Soignolles', 201370846, '1947-01-01', 'year', '1969-12-14', 'before',
            'L\'activité de cette section est dès lors limitée au trafic de marchandises, avec passage en voie unique en 1963-1964.', 'wikidata', '1947-01-01'],
        ['Grisy-Suisnes', 'Q3096582', 'Gare de Grisy-Suisnes', 207275260, '1947-01-01', 'year', '1969-12-14', 'before',
            'L\'activité de cette section est dès lors limitée au trafic de marchandises, avec passage en voie unique en 1963-1964.', 'wikidata', '1947-01-01'],
        ['Santeny - Servon', 'Q3097670', 'Gare de Santeny - Servon', 231601559, '1947-01-01', 'year', '1969-12-14', 'before',
            'L\'activité de cette section est dès lors limitée au trafic de marchandises, avec passage en voie unique en 1963-1964.', 'wikidata', '1947-01-01'],
    ])),

    // ── 10. Gares desservies notées fermées par la fermeture d'une autre gare (chantier « coherence-gares-lignes », 21/11/2026) ──
    // Contrôle : gare déclassée à une ère où un tronçon voyageurs ou mixte en service de ses lignes passe à moins de
    // 150 m, et ouverte aujourd'hui (rapport storage/app/reports/agents/aprem-2111/coherence-gares-lignes.md).
    // 10 a. L'objet OHM rapproché par le nom (name_near) est la gare homonyme d'un autre réseau (chemin de fer
    // départemental, tramway à vapeur, Nyon - Saint-Cergue - Morez, première ligne de Saint-Étienne à Andrézieux)
    // ou un ancien bâtiment : sa date de fin n'est pas celle de la gare du réseau principal, desservie sans
    // interruption (Sens, Montereau, Nogent-sur-Seine, Souppes : ligne de Paris à Marseille, de Paris à Mulhouse,
    // du Bourbonnais).
    // L'ouverture du même objet OHM est retirée aussi (gare homonyme) : Montereau et Souppes n'avaient qu'elle
    // (1888), leur ouverture vient alors de leurs lignes (1849, 1860).
    ...array_merge(...array_map(fn (array $g) => array_filter([
        [
            'station' => ['name' => $g[0], $g[1] => $g[2]],
            'action' => 'neutralize',
            'match' => ['source' => 'ohm', 'type' => 'closure', 'date' => $g[3]],
            'source_url' => 'https://www.openhistoricalmap.org/'.['n' => 'node', 'w' => 'way', 'r' => 'relation'][$g[4][0]].'/'.substr($g[4], 1),
            'reason' => $g[5],
        ],
        isset($g[6]) ? [
            'station' => ['name' => $g[0], $g[1] => $g[2]],
            'action' => 'neutralize',
            'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => $g[6]],
            'source_url' => 'https://www.openhistoricalmap.org/'.['n' => 'node', 'w' => 'way', 'r' => 'relation'][$g[4][0]].'/'.substr($g[4], 1),
            'reason' => 'ouverture de la même gare homonyme : '.$g[5],
        ] : null,
    ]), [
        // gare, clé, identifiant, date OHM retirée, objet OHM, raison, ouverture OHM retirée (gare homonyme)
        ['Sens', 'wikidata', 'Q2545246', '1948-03-31', 'n2181759785',
            'l\'objet OHM « Sens (CFD) » (1901 à 1948) est la gare des chemins de fer départementaux, pas la gare de la ligne de Paris à Marseille', '1901-11-10'],
        ['Montereau', 'wikidata', 'Q2117756', '1959-01-01', 'n2181778995',
            'l\'objet OHM « Montereau » (halte de 1888 à 1959) est l\'arrêt du chemin de fer départemental, pas la gare de la ligne de Paris à Marseille', '1888-10-01'],
        ['Nogent-sur-Seine', 'wikidata', 'Q2424780', '1939-01-01', 'n2181732475',
            'l\'objet OHM « Nogent sur Seine (CFD) » (1925 à 1939) est la gare des chemins de fer départementaux, pas la gare de la ligne de Paris à Mulhouse', '1925-09-21'],
        ['Souppes-Château-Landon', 'wikidata', 'Q2010209', '1959-01-01', 'n2181780573',
            'l\'objet OHM « Souppes (CFD) » (1888 à 1959) est la gare des chemins de fer départementaux, pas la gare de la ligne du Bourbonnais', '1888-10-01'],
        ['Morez', 'wikidata', 'Q2314163', '1958-09-27', 'n2087817589',
            'l\'objet OHM « Morez PLM » (exploitant NStCM, 1921 à 1958) est le terminus du Nyon - Saint-Cergue - Morez, fermé en 1958 ; la gare de la ligne d\'Andelot à La Cluse reste desservie', '1921-03-07'],
        ['Lavaur', 'wikidata', 'Q3096865', '1937-06-30', 'n2134788821',
            'l\'objet OHM « Lavaur » (exploitant TVT, voie de 60 cm, 1903 à 1937) est la gare des tramways à vapeur du Tarn, pas la gare SNCF, toujours desservie', '1903-05-26'],
        ['St-Sulpice', 'wikidata', 'Q3097624', '1931-01-01', 'n2134788832',
            'l\'objet OHM « Saint-Sulpice » (1925 à 1931) est la gare des tramways à vapeur du Tarn, pas la gare SNCF de Saint-Sulpice-la-Pointe, toujours desservie', '1925-04-11'],
        ['Andrézieux', 'wikidata', 'Q3095632', '1864-06-15', 'n2125380187',
            'l\'objet OHM « Gare d\'Andrézieux » (1827 à 1864) est la gare de la première ligne de Saint-Étienne à Andrézieux, remplacée par la gare actuelle'],
        ['Maubeuge', 'wikidata', 'Q2559978', '1976-06-30', 'r2892705',
            'l\'objet OHM est un bâtiment (multipolygone building=train_station) dont la fin date le bâtiment ; la gare de la ligne de Creil à Jeumont reste desservie'],
        ['Lourches', 'wikidata', 'Q1845098', '1975-06-30', 'r2897405',
            'l\'objet OHM est un bâtiment (multipolygone building=train_station) à date de fin notée arbitraire (end_date:fixme) ; la halte reste desservie'],
    ])),
    // 10 b. Gare d'Orsay : en 1939, seules les grandes lignes partent pour Austerlitz, la banlieue reste ; la gare
    // devient Musée d'Orsay sur le RER C en 1979.
    [
        'station' => ['name' => 'Musée-d\'Orsay', 'wikidata' => 'Q15260427'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'closure', 'date' => '1939-01-01'],
        'source_url' => $wp('Gare du Musée d\'Orsay', 240941106),
        'quote' => 'Le trafic grandes lignes est alors reporté à la gare de Paris-Austerlitz.',
        'reason' => 'fin des grandes lignes, pas de la gare : fonction limitée au trafic de banlieue en 1939, puis station du RER C en 1979',
    ],
    // 10 c. Dates d'inauguration d'un nouveau bâtiment voyageurs lues comme réouvertures (Wikidata P1619) : la règle
    // « réouverture sans fermeture antérieure » (vff:build-station-eras, étape g) leur prêtait la fermeture d'une
    // ligne voisine (683000 en 1969 à Orléans, 884000 en 1990 à Bellegarde) alors que la gare restait desservie.
    [
        'station' => ['name' => 'Orléans', 'wikidata' => 'Q1965397'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'reopening', 'date' => '2007-01-01'],
        'source_url' => $wp('Gare d\'Orléans', 235030555),
        'quote' => 'De 2005 à 2007, la gare est en travaux',
        'reason' => 'travaux de la quatrième gare (inaugurée le 18 janvier 2008), pas une réouverture : la gare n\'a pas fermé',
    ],
    [
        'station' => ['name' => 'Bellegarde', 'wikidata' => 'Q3095950'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'reopening', 'date' => '2010-05-21'],
        'source_url' => $wp('Gare de Bellegarde', 241398952),
        'quote' => 'Les services qu\'il regroupe sont ceux précédemment offerts par les anciennes installations',
        'reason' => 'inauguration du nouveau bâtiment voyageurs le 21 mai 2010, pas une réouverture : la gare de la ligne de Lyon à Genève est restée desservie',
    ],

    // ── 11. Gares minoritaires et dates de rang (chantier « gares-g1-g2 », 21/11/2026) ─────────────────────
    // Dossiers G1 et G2 relus le 21/11/2026 (rapports storage/app/reports/agents/matin-2111/audit-gares-suite.md et
    // aprem-2111/gares-planches.md, planches d'orthophotos IGN BD ORTHO dans matin-2111/audit-gares-suite/ortho/).
    // G1 : positions des gares « minoritaires » (contrôle retained_outvoted de vff:audit-stations), recopiées de
    // matin-2111/audit-gares-suite/gares_corrections_minoritaires.php, sections 1, 2 et 2 bis ; la section 3 (position
    // retenue juste, les deux dans la gare, source insuffisante) n'est pas reprise.
    // 11 a. Vérifiées sur l'orthophoto le matin : la position proposée est la gare (14).
    // 01. Trévoux (id 20379)
    [
        'station' => ['name' => 'Trévoux', 'uic' => '87723858'],
        'action' => 'position',
        'lng' => 4.7822449,
        'lat' => 45.9383748,
        'source_url' => $osm('n13395208775'),
        'quote' => 'Ancienne gare de Trévoux (SNCF) : lat = 45.9383748, lon = 4.7822449',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 452 m, wikidata à 475 m (dispersion 22 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 12 m de la voie ; planche 01',
    ],
    // 04. St-Gérand-le-Puy-Magnet (id 20446)
    [
        'station' => ['name' => 'St-Gérand-le-Puy-Magnet', 'uic' => '87732602'],
        'action' => 'position',
        'lng' => 3.5047959,
        'lat' => 46.2110922,
        'source_url' => $osm('n11432231106'),
        'quote' => 'Saint-Gérand-le-Puy - Magnet : lat = 46.2110922, lon = 3.5047959',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 335 m, wikidata à 259 m (dispersion 75 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 17 m de la voie ; planche 04',
    ],
    // 05. St-Louis-les-Aygalades (id 17979)
    [
        'station' => ['name' => 'St-Louis-les-Aygalades', 'uic' => '87751644'],
        'action' => 'position',
        'lng' => 5.3651173,
        'lat' => 43.3461208,
        'source_url' => $osm('w159889768'),
        'quote' => 'Saint-Louis-les-Aygalades : lat = 43.3461208, lon = 5.3651173',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 281 m, bdtopo à 278 m, wikidata à 286 m (dispersion 8 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 26 m de la voie ; planche 05',
    ],
    // 07. Clion-sur-Seugne (id 16588)
    [
        'station' => ['name' => 'Clion-sur-Seugne', 'uic' => '87491134'],
        'action' => 'position',
        'lng' => -0.5021032,
        'lat' => 45.4783195,
        'source_url' => $osm('w120165553'),
        'quote' => 'Clion-sur-Seugne : lat = 45.4783195, lon = -0.5021032',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 145 m, wikidata à 220 m (dispersion 75 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 11 m de la voie ; planche 07',
    ],
    // 12. Fenouillet-St-Alban (id 17161)
    [
        'station' => ['name' => 'Fenouillet-St-Alban', 'uic' => '87611962'],
        'action' => 'position',
        'lng' => 1.4018148,
        'lat' => 43.6840474,
        'source_url' => $osm('n6121183551'),
        'quote' => 'Fenouillet-Saint-Alban : lat = 43.6840474, lon = 1.4018148',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 410 m, bdtopo à 416 m, wikidata à 375 m (dispersion 59 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 7 m de la voie ; planche 12',
    ],
    // 16. Montpellier Sud-de-France (id 17435)
    [
        'station' => ['name' => 'Montpellier Sud-de-France', 'uic' => '87688887'],
        'action' => 'position',
        'lng' => 3.9239637,
        'lat' => 43.595115,
        'source_url' => $osm('n8096578751'),
        'quote' => 'Montpellier-Sud-de-France : lat = 43.595115, lon = 3.9239637',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 983 m, bdtopo à 974 m (dispersion 28 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 22 m de la voie ; planche 16',
    ],
    // 17. Montpellier-St-Roch (id 18151)
    [
        'station' => ['name' => 'Montpellier-St-Roch', 'uic' => '87773002'],
        'action' => 'position',
        'lng' => 3.8804316,
        'lat' => 43.6044803,
        'source_url' => $osm('r6022885'),
        'quote' => 'Gare de Montpellier Saint-Roch : lat = 43.6044803, lon = 3.8804316',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 259 m, bdtopo à 123 m, wikidata à 213 m (dispersion 52 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 2 m de la voie ; planche 17',
    ],
    // 22. Doulon (id 16460)
    [
        'station' => ['name' => 'Doulon', 'uic' => '87481085'],
        'action' => 'position',
        'lng' => -1.5181611,
        'lat' => 47.2274844,
        'source_url' => $osm('n14140081221'),
        'quote' => 'Doulon : lat = 47.2274844, lon = -1.5181611',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 465 m, wikidata à 461 m (dispersion 8 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 0 m de la voie ; planche 22',
    ],
    // 24. Carteret (id 19343)
    [
        'station' => ['name' => 'Carteret', 'uic' => '87447490'],
        'action' => 'position',
        'lng' => -1.7842443,
        'lat' => 49.3801073,
        'source_url' => $osm('n11047708545'),
        'quote' => 'Carteret : lat = 49.3801073, lon = -1.7842443',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 870 m, wikidata à 941 m (dispersion 77 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 2 m de la voie ; planche 24',
    ],
    // 30. Geispolsheim (id 15362)
    [
        'station' => ['name' => 'Geispolsheim', 'uic' => '87212258'],
        'action' => 'position',
        'lng' => 7.6849513,
        'lat' => 48.5209867,
        'source_url' => 'https://data.geopf.fr/wfs/ows?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=BDTOPO_V3:equipement_de_transport&FEATUREID=EQ_RESEA0000000048606956',
        'quote' => 'Halte Ferroviaire de Geispolsheim : lat = 48.5209867, lon = 7.6849513',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : bdtopo à 206 m, wikidata à 214 m (dispersion 5 m) ; familles d\'accord avec la position retenue : sncf ; proposée à 0 m de la voie ; planche 30',
    ],
    // 40. Paris-Bercy (id 17434)
    [
        'station' => ['name' => 'Paris-Bercy', 'uic' => '87686667'],
        'action' => 'position',
        'lng' => 2.38278,
        'lat' => 48.83917,
        'source_url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q=87686667',
        'quote' => 'Paris Bercy Bourgogne - Pays d\'Auvergne : lat = 48.83917, lon = 2.38278',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : sncf à 133 m, wikidata à 145 m (dispersion 26 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 134 m de la voie ; planche 40',
    ],
    // 44. Petit-Jouy-Les Loges (id 16109)
    [
        'station' => ['name' => 'Petit-Jouy-Les Loges', 'uic' => '87393504'],
        'action' => 'position',
        'lng' => 2.1464323,
        'lat' => 48.7703929,
        'source_url' => $osm('w253795589'),
        'quote' => 'Petit Jouy - Les Loges : lat = 48.7703929, lon = 2.1464323',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 233 m, bdtopo à 225 m, wikidata à 273 m (dispersion 52 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 30 m de la voie ; planche 44',
    ],
    // 52. Grigny-Centre (id 17348)
    [
        'station' => ['name' => 'Grigny-Centre', 'uic' => '87681379'],
        'action' => 'position',
        'lng' => 2.3958644,
        'lat' => 48.6534796,
        'source_url' => $osm('w993685345'),
        'quote' => 'Grigny-Centre : lat = 48.6534796, lon = 2.3958644',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 284 m, bdtopo à 268 m, wikidata à 260 m (dispersion 108 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 1 m de la voie ; planche 52',
    ],
    // 53. Massy-Palaiseau (id 16116)
    [
        'station' => ['name' => 'Massy-Palaiseau', 'uic' => '87393579'],
        'action' => 'position',
        'lng' => 2.2577783,
        'lat' => 48.7265363,
        'source_url' => $osm('w417349787'),
        'quote' => 'Massy Palaiseau RER C : lat = 48.7265363, lon = 2.2577783',
        'reason' => 'orthophoto relue le 21/11/2026 : position proposée au bâtiment voyageurs ou aux quais ; groupe majoritaire : osm à 297 m, bdtopo à 283 m (dispersion 0 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 74 m de la voie ; planche 53',
    ],

    // 11 b. Relues sur l'orthophoto l'après-midi : la position proposée est juste (24 ; Senozan et
    // St-Cergues-les-Voirons, déjà posées sur leur bâtiment en 8 e, ne sont pas reprises).
    // 02. Crézancy (id 18310)
    [
        'station' => ['name' => 'Crézancy', 'uic' => '87116798'],
        'action' => 'position',
        'lng' => 3.5161343,
        'lat' => 49.0496661,
        'source_url' => 'https://data.geopf.fr/wfs/ows?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=BDTOPO_V3:equipement_de_transport&FEATUREID=EQ_RESEA0000000358024719',
        'quote' => 'Gare de Crézancy (Association Touristique Ferroviaire) : lat = 49.0496661, lon = 3.5161343',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : petit bâtiment voyageurs au passage à niveau, à côté de la voie ; la position retenue est dans un champ derrière un hangar ; groupe majoritaire : bdtopo à 134 m, wikidata à 145 m (dispersion 8 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 14 m de la voie ; planche 02',
    ],
    // 06. Séon-St-Henry (id 17978)
    [
        'station' => ['name' => 'Séon-St-Henry', 'uic' => '87751636'],
        'action' => 'position',
        'lng' => 5.3399592,
        'lat' => 43.362419,
        'source_url' => $osm('w69630228'),
        'quote' => 'Séon-Saint-Henri : lat = 43.362419, lon = 5.3399592',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs et quais (OSM w69630228, quais w312396571 et w312396572) ; la position retenue est sous l\'échangeur autoroutier ; groupe majoritaire : osm à 253 m, wikidata à 255 m (dispersion 3 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 12 m de la voie ; planche 06',
    ],
    // 08. Aubusson (id 17093)
    [
        'station' => ['name' => 'Aubusson', 'uic' => '87597823'],
        'action' => 'position',
        'lng' => 2.1616888,
        'lat' => 45.9598728,
        'source_url' => $osm('n9006547004'),
        'quote' => 'Aubusson : lat = 45.9598728, lon = 2.1616888',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs et parking le long de la voie, quai OSM à 23 m ; la position retenue est dans les arbres ; groupe majoritaire : osm à 103 m, wikidata à 109 m (dispersion 6 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 11 m de la voie ; planche 08',
    ],
    // 10. Louviers (id 19281)
    [
        'station' => ['name' => 'Louviers', 'uic' => '87415851'],
        'action' => 'position',
        'lng' => 1.177345,
        'lat' => 49.2159366,
        'source_url' => $osm('w73067310'),
        'quote' => 'La Gare aux Musiques : lat = 49.2159366, lon = 1.177345',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : ancien bâtiment voyageurs, aujourd\'hui La Gare aux Musiques (OSM, start_date 1867) ; la position retenue est sur une casse ; groupe majoritaire : osm à 190 m, wikidata à 191 m (dispersion 2 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 16 m de la voie ; planche 10',
    ],
    // 11. Casamozza (id 20710)
    [
        'station' => ['name' => 'Casamozza', 'uic' => '87762005'],
        'action' => 'position',
        'lng' => 9.4410912,
        'lat' => 42.5218272,
        'source_url' => $osm('n1082754108'),
        'quote' => 'Casamozza : lat = 42.5218272, lon = 9.4410912',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs devant les quais ; la position retenue est 200 m au sud, sur le gril ; groupe majoritaire : osm à 200 m, bdtopo à 189 m (dispersion 8 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 2 m de la voie ; planche 11',
    ],
    // 14. Pointe-de-Grave (id 16853)
    [
        'station' => ['name' => 'Pointe-de-Grave', 'uic' => '87581397'],
        'action' => 'position',
        'lng' => -1.0655868,
        'lat' => 45.5666681,
        'source_url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-gares/table/?q=87581397',
        'quote' => 'Pointe-de-Grave : lat = 45.5666681, lon = -1.0655868',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : zone de la gare SNCF au débarcadère du bac, à 31 m du quai OSM w508758759 ; la position retenue est le dépôt du petit train touristique de la Pointe de Grave (OSM w508757688) ; réserve : le point est sur le parking, pas sur le quai ; groupe majoritaire : sncf à 128 m, bdtopo à 127 m (dispersion 46 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 38 m de la voie ; planche 14',
    ],
    // 15. Colombiers (id 18200)
    [
        'station' => ['name' => 'Colombiers', 'uic' => '87781187'],
        'action' => 'position',
        'lng' => 3.1396847,
        'lat' => 43.3190567,
        'source_url' => $osm('n61082760'),
        'quote' => 'Colombiers : lat = 43.3190567, lon = 3.1396847',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs au bord du faisceau de Colombiers ; la position retenue est 380 m plus loin, sur la ligne près du pont ; groupe majoritaire : osm à 378 m, wikidata à 377 m (dispersion 3 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 29 m de la voie ; planche 15',
    ],
    // 21. Sembadel (id 20471)
    [
        'station' => ['name' => 'Sembadel', 'uic' => '87734640'],
        'action' => 'position',
        'lng' => 3.7195031,
        'lat' => 45.2763252,
        'source_url' => 'https://www.openhistoricalmap.org/node/2135479570',
        'quote' => 'Gare de Sembadel : lat = 45.2763252, lon = 3.7195031',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs au bord des voies, dans le village ; la position retenue est 180 m à l\'est, vers la bifurcation ; groupe majoritaire : ohm à 184 m, wikidata à 226 m (dispersion 43 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 12 m de la voie ; planche 21',
    ],
    // 25. Bouvines (id 15668)
    [
        'station' => ['name' => 'Bouvines', 'uic' => '87287235'],
        'action' => 'position',
        'lng' => 3.2022205,
        'lat' => 50.5738094,
        'source_url' => $osm('n392132874'),
        'quote' => 'Bouvines : lat = 50.5738094, lon = 3.2022205',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : halte au passage à niveau de la ligne fermée (OSM disused halt, quai à 35 m) ; la position retenue est sur le pont de la ligne Lille - Tournai ; groupe majoritaire : osm à 120 m, wikidata à 113 m (dispersion 8 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 1 m de la voie ; planche 25',
    ],
    // 26. Surdon (id 16281)
    [
        'station' => ['name' => 'Surdon', 'uic' => '87444562'],
        'action' => 'position',
        'lng' => 0.1333975,
        'lat' => 48.6653453,
        'source_url' => $osm('n2351574817'),
        'quote' => 'Surdon : lat = 48.6653453, lon = 0.1333975',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs (OSM w494883491) ; la position retenue est 175 m à l\'est, au-delà du bâtiment, à plus de 300 m des quais ; groupe majoritaire : osm à 176 m, bdtopo à 162 m (dispersion 8 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 22 m de la voie ; planche 26',
    ],
    // 27. Conchil-le-Temple (id 15820)
    [
        'station' => ['name' => 'Conchil-le-Temple', 'uic' => '87317040'],
        'action' => 'position',
        'lng' => 1.6608308,
        'lat' => 50.3616834,
        'source_url' => 'https://data.geopf.fr/wfs/ows?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=BDTOPO_V3:equipement_de_transport&FEATUREID=EQ_RESEA0000000117906233',
        'quote' => 'Gare de Conchil-le-Temple : lat = 50.3616834, lon = 1.6608308',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : position la plus proche du bâtiment probable (60 m, rue de la Gare) ; la position retenue est au sud du faisceau, à 220 m ; réserve : pas de quai lisible ; groupe majoritaire : bdtopo à 167 m, wikidata à 220 m (dispersion 50 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 0 m de la voie ; planche 27',
    ],
    // 34. Jussey (id 15261)
    [
        'station' => ['name' => 'Jussey', 'uic' => '87185355'],
        'action' => 'position',
        'lng' => 5.903379,
        'lat' => 47.8368862,
        'source_url' => 'https://data.geopf.fr/wfs/ows?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=BDTOPO_V3:equipement_de_transport&FEATUREID=EQ_RESEA0000000243543424',
        'quote' => 'Gare de Jussey : lat = 47.8368862, lon = 5.903379',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs ; la position retenue est 100 m à l\'est sur les voies principales ; groupe majoritaire : bdtopo à 101 m, wikidata à 101 m (dispersion 13 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 12 m de la voie ; planche 34',
    ],
    // 36. St-Yan (id 20229)
    [
        'station' => ['name' => 'St-Yan', 'uic' => '87694802'],
        'action' => 'position',
        'lng' => 4.0351072,
        'lat' => 46.413162,
        'source_url' => 'https://www.openhistoricalmap.org/node/2137419318',
        'quote' => 'Gare de Saint-Yan : lat = 46.413162, lon = 4.0351072',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs à 19 m de la plateforme de la ligne du Coteau à Montchanin (OSM) ; la position retenue est au croisement de la route et des deux lignes ; groupe majoritaire : ohm à 124 m, wikidata à 118 m (dispersion 15 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 12 m de la voie ; planche 36',
    ],
    // 42. St-Aubin-sur-Scie (id 16236)
    [
        'station' => ['name' => 'St-Aubin-sur-Scie', 'uic' => '87415125'],
        'action' => 'position',
        'lng' => 1.0667322,
        'lat' => 49.8708348,
        'source_url' => $osm('n3470363595'),
        'quote' => 'Saint-Aubin-sur-Scie : lat = 49.8708348, lon = 1.0667322',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : abri sur le quai unique, côté est ; la position retenue est au sud, après la fin du quai ; groupe majoritaire : osm à 112 m, bdtopo à 192 m, wikidata à 204 m (dispersion 93 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 5 m de la voie ; planche 42',
    ],
    // 43. Le Mée (id 17372)
    [
        'station' => ['name' => 'Le Mée', 'uic' => '87682179'],
        'action' => 'position',
        'lng' => 2.6240913,
        'lat' => 48.5400705,
        'source_url' => $osm('w113477650'),
        'quote' => 'Gare de Le Mée : lat = 48.5400705, lon = 2.6240913',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs au pont routier (OSM w113477650) ; la position retenue est 170 m au nord-ouest, hors des quais (à 320 m) ; groupe majoritaire : osm à 176 m, wikidata à 142 m (dispersion 50 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 45 m de la voie ; planche 43',
    ],
    // 45. Mazières-Verruyes (id 16551)
    [
        'station' => ['name' => 'Mazières-Verruyes', 'uic' => '87485714'],
        'action' => 'position',
        'lng' => -0.3083996,
        'lat' => 46.5328303,
        'source_url' => $osm('n237289468'),
        'quote' => 'Mazières en Gâtine : lat = 46.5328303, lon = -0.3083996',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : halte OSM au passage de la route, au bord du bourg ; la position retenue est en pleine voie dans les champs ; réserve : aucun bâtiment lisible ; groupe majoritaire : osm à 251 m, wikidata à 240 m (dispersion 11 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 3 m de la voie ; planche 45',
    ],
    // 46. St-Varent (id 16567)
    [
        'station' => ['name' => 'St-Varent', 'uic' => '87487116'],
        'action' => 'position',
        'lng' => -0.2242533,
        'lat' => 46.8890003,
        'source_url' => 'https://data.geopf.fr/wfs/ows?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=BDTOPO_V3:equipement_de_transport&FEATUREID=EQ_RESEA0000000109256344',
        'quote' => 'Saint-Varent : lat = 46.8890003, lon = -0.2242533',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment et quais (OSM, quais à 21 et 23 m) ; la position retenue est 240 m au nord, en pleine voie ; groupe majoritaire : bdtopo à 240 m, wikidata à 224 m (dispersion 20 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 11 m de la voie ; planche 46',
    ],
    // 48. Malause (id 17156)
    [
        'station' => ['name' => 'Malause', 'uic' => '87611871'],
        'action' => 'position',
        'lng' => 0.9716252,
        'lat' => 44.0881773,
        'source_url' => $osm('n9353112089'),
        'quote' => 'Gare de Malause : lat = 44.0881773, lon = 0.9716252',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs et quais (OSM, quais à 5 et 14 m) ; la position retenue est 180 m au sud-est, le long du canal ; groupe majoritaire : osm à 180 m, wikidata à 168 m (dispersion 14 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 2 m de la voie ; planche 48',
    ],
    // 50. Hymont-Mattaincourt (id 15046)
    [
        'station' => ['name' => 'Hymont-Mattaincourt', 'uic' => '87144220'],
        'action' => 'position',
        'lng' => 6.1402279,
        'lat' => 48.2707868,
        'source_url' => $osm('n2297588288'),
        'quote' => 'Hymont : lat = 48.2707868, lon = 6.1402279',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : halte OSM et quais à 7 et 13 m, près de la route ; la position retenue est en pleine voie dans les champs ; groupe majoritaire : osm à 235 m, wikidata à 235 m (dispersion 5 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 2 m de la voie ; planche 50',
    ],
    // 51. Laveline-devant-Bruyères (id 15041)
    [
        'station' => ['name' => 'Laveline-devant-Bruyères', 'uic' => '87144162'],
        'action' => 'position',
        'lng' => 6.7512453,
        'lat' => 48.1856049,
        'source_url' => $osm('n512070535'),
        'quote' => 'Laveline-devant-Bruyères : lat = 48.1856049, lon = 6.7512453',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : ancien bâtiment voyageurs au passage à niveau, quai à 38 m ; la position retenue est dans les bois à l\'est ; groupe majoritaire : osm à 296 m, wikidata à 281 m (dispersion 15 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 1 m de la voie ; planche 51',
    ],
    // 55. Nanterre-Université (id 16047)
    [
        'station' => ['name' => 'Nanterre-Université', 'uic' => '87386318'],
        'action' => 'position',
        'lng' => 2.2134323,
        'lat' => 48.9011561,
        'source_url' => $osm('w386283983'),
        'quote' => 'Gare de Nanterre - Université : lat = 48.9011561, lon = 2.2134323',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : bâtiment voyageurs et point OSM de la gare (13 m) ; la position retenue est 200 m à l\'est, sous la dalle ; réserve : quais non cartographiés dans OSM ; groupe majoritaire : osm à 206 m, bdtopo à 178 m, wikidata à 109 m (dispersion 98 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 5 m de la voie ; planche 55',
    ],
    // 57. Gros-Noyer-St-Prix (id 15564)
    [
        'station' => ['name' => 'Gros-Noyer-St-Prix', 'uic' => '87276592'],
        'action' => 'position',
        'lng' => 2.2595198,
        'lat' => 48.9962878,
        'source_url' => $osm('n90700748'),
        'quote' => 'Gros Noyer - Saint-Prix : lat = 48.9962878, lon = 2.2595198',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : point OSM de la gare, quai voie 1 à 11 m ; la position retenue est 130 m au sud, après la fin des quais ; groupe majoritaire : osm à 132 m, bdtopo à 190 m, wikidata à 229 m (dispersion 99 m) ; familles d\'accord avec la position retenue : aucune ; proposée à 1 m de la voie ; planche 57',
    ],
    // 13. Bordeaux-Bastide (id 16844)
    [
        'station' => ['name' => 'Bordeaux-Bastide', 'uic' => '87581017'],
        'action' => 'position',
        'lng' => -0.562075,
        'lat' => 44.8416245,
        'source_url' => $osm('w219626597'),
        'quote' => 'Gare de Bordeaux-Bastide : lat = 44.8416245, lon = -0.562075',
        'reason' => 'relue le 21/11/2026 (après-midi) : ancienne gare d\'Orléans, quai des Queyries (article Gare de Bordeaux-Bastide, oldid 235030961, mêmes coordonnées que Wikidata) ; le point SNCF retenu est à Cenon, 2,7 km au nord-est, près de l\'origine de la ligne de Lormont à Bordeaux-Bastide ; groupe majoritaire : osm à 2670 m, wikidata à 2669 m ; planche 13',
    ],
    // 23. Angers-St-Serge (id 19450)
    [
        'station' => ['name' => 'Angers-St-Serge', 'uic' => '87484022'],
        'action' => 'position',
        'lng' => -0.5511239,
        'lat' => 47.4776343,
        'source_url' => 'https://www.openhistoricalmap.org/node/2130086691',
        'quote' => 'Gare d\'Angers-Saint-Serge : lat = 47.4776343, lon = -0.5511239',
        'reason' => 'relue le 21/11/2026 (après-midi) : gare rasée en 1992, place Saint-Serge (actuelle place François-Mitterrand), coordonnées de l\'article Gare d\'Angers-Saint-Serge (oldid 231419269) à 30 m ; le point SNCF retenu est 3,2 km au nord-est, sur les embranchements de Pomanjou le long de l\'A11 ; groupe majoritaire : ohm à 3164 m, wikidata à 3184 m ; planche 23',
    ],

    // 11 c. Relues : ni la retenue ni la proposée, position posée sur une autre source (2).
    // 28. Saléchan-Siradan (id 17235)
    [
        'station' => ['name' => 'Saléchan-Siradan', 'uic' => '87618710'],
        'action' => 'position',
        'lng' => 0.6335163,
        'lat' => 42.9586135,
        'source_url' => $osm('n12862385491'),
        'quote' => 'Saléchan - Siradan : lat = 42.9586135, lon = 0.6335163 ; note OSM : « Gare déplacée en 2025 de 500 mètres au sud de la ligne ferroviaire. »',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : la gare a été déplacée en 2025 à 500 m au sud (note de l\'objet OSM n12862385491, uic_ref 8761871, quai w1389278196) ; la position proposée (OSM w309136442) est l\'ancien bâtiment, désaffecté, et la position retenue est entre les deux, dans un champ ; position posée sur la halte en service ; planche 28',
    ],
    // 47. St-Sulpice (id 17211)
    [
        'station' => ['name' => 'St-Sulpice', 'uic' => '87615344'],
        'action' => 'position',
        'lng' => 1.6806679,
        'lat' => 43.7750301,
        'source_url' => $osm('w63891176'),
        'quote' => 'Gare de Saint-Sulpice (building=train_station) : lat = 43.7750301, lon = 1.6806679 (centre du bâtiment)',
        'reason' => 'orthophoto relue le 21/11/2026 (après-midi) : la position proposée (OHM n2134788832) tombe sur un bâtiment du parking, 65 m au sud du bâtiment voyageurs ; la position retenue est sur le faisceau, à 90 m ; position posée sur le bâtiment voyageurs d\'OSM (w63891176), à 30 m du point Wikidata ; planche 47',
    ],

    // 11 d. Règle de rang (G2), retenue pour 5 dates seulement : ouverture estimée par la ligne (ou absente) et
    // Wikidata (P1619, rang normal, élément vérifié : c'est bien la gare) donne une date postérieure, cohérente
    // avec l'article ou la ligne. Les autres dates de la règle restent en relecture (regle-rang.csv).
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], $g[1] => $g[2]],
        'action' => 'add',
        'type' => 'opening',
        'date' => $g[3],
        'precision' => $g[4],
        'source_url' => 'https://www.wikidata.org/wiki/'.$g[5].'#P1619',
        'quote' => $g[6],
        'reason' => $g[7],
    ], [
        // gare, clé, identifiant, date, précision, élément Wikidata, citation, raison
        ['La Gorp', 'uic', '87584755', '1852-01-01', 'year', 'Q675874', 'date de mise en service (P1619) : 1852',
            'ouverture estimée à 1840 par la ligne, antérieure à la section Bordeaux - Libourne (1852) ; Q675874 est bien la gare ; date de ligne, précision année'],
        ['Les Clairières-de-Verneuil', 'uic', '87386664', '1974-05-01', 'month', 'Q3098151', 'date de mise en service (P1619) : mai 1974',
            'ouverture estimée à 1843 par la ligne ; halte mise en service en mai 1974 (Wikidata, et infobox de l\'article Gare des Clairières de Verneuil, oldid 236348200)'],
        ['Raedersheim', 'uic', '87182717', '1841-01-01', 'year', 'Q631405', 'date de mise en service (P1619) : 1841',
            'ouverture estimée à 1840 par la ligne, antérieure à la ligne de Strasbourg à Bâle (1841) ; Q631405 est bien la gare'],
        ['Gare embarcadère de Sceaux', 'wikidata', 'Q96605529', '1846-01-01', 'year', 'Q3097699', 'date de mise en service (P1619) : 1846',
            'aucune ouverture dans VFF ; mise en service en 1846 (Wikidata Q3097699, et infobox de l\'article Gare de Sceaux (ancienne), oldid 241389426)'],
    ]),
    // Montauban-de-Bretagne : mise en service le 07/09/1863, jour de l'ouverture de Rennes - Guingamp (Wikidata P1619
    // ne donne que l'année ; recherche-lignes-2, 22/11/2026).
    [
        'station' => ['name' => 'Montauban-de-Bretagne', 'uic' => '87471086'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1863-09-07',
        'precision' => 'day',
        'source_url' => $wp('Gare de Montauban-de-Bretagne', 238688642),
        'quote' => 'La station de Montauban-de-Bretagne est mise en service le 7 septembre 1863 par la Compagnie des chemins de fer de l\'Ouest',
        'reason' => 'ouverture estimée à 1840 par la ligne ; le jour est celui de l\'ouverture de la section Rennes - Guingamp (article de la gare, d\'accord avec Wikidata P1619 pour l\'année 1863)',
    ],

    // 11 e. Liens Wikidata faux (G2) : l'objet OHM de la gare porte le Q-id d'une autre gare (tag wikidata), la
    // fiche le prenait. Défaits au rattachement des sources (vff:consolidate-stations, action « unlink ») ; les
    // dates tirées de ces Q-id sont déjà retirées en 8 b.
    [
        'station' => ['name' => 'St-Vidal', 'uic' => '87731844'],
        'action' => 'unlink',
        'source' => 'wikidata',
        'ref' => 'Q1891124',
        'source_url' => 'https://www.wikidata.org/wiki/Q1891124',
        'quote' => 'Le Puy-en-Velay, gare ferroviaire française',
        'reason' => 'Q1891124 est la gare du Puy-en-Velay, à 8 km ; le tag vient de l\'objet OHM n2125740909 (Gare du Saint-Vidal)',
    ],
    [
        'station' => ['name' => 'Néronde-sur-Dore', 'uic' => '87731653'],
        'action' => 'unlink',
        'source' => 'wikidata',
        'ref' => 'Q13406478',
        'source_url' => 'https://www.wikidata.org/wiki/Q13406478',
        'quote' => 'Ambert, gare ferroviaire française',
        'reason' => 'Q13406478 est la gare d\'Ambert, à 32 km ; le tag vient de l\'objet OHM n2135529387 (Halte de Néronde-sur-Dore)',
    ],
    // Même tag faux sur l'objet OHM de Courpière, relevé en vérifiant celui de Néronde-sur-Dore.
    [
        'station' => ['name' => 'Courpière', 'uic' => '87734525'],
        'action' => 'unlink',
        'source' => 'wikidata',
        'ref' => 'Q13406478',
        'source_url' => 'https://www.wikidata.org/wiki/Q13406478',
        'quote' => 'Ambert, gare ferroviaire française',
        'reason' => 'Q13406478 est la gare d\'Ambert, à 28 km ; le tag vient de l\'objet OHM n2135527207 (Gare de Courpière)',
    ],

    // ── 12. Questions ouvertes sur les gares (chantier « recherche-lignes », nuit du 22/11/2026) ──────────────
    // Rapport storage/app/reports/agents/aprem-2111/recherche-lignes.md.

    // Rion-des-Landes : ouverture estimée à 1841 par la première ouverture de la ligne (Bordeaux - La Teste) ;
    // la gare est sur Lamothe - Dax (PK 122,043), ouverte à la circulation en septembre 1854 et mise en service
    // officiellement le 12/11/1854 (déjà en base pour la ligne). L'article de la gare donne aussi 1854 (premier
    // train le 15/08/1854, sans source) : année seule.
    [
        'station' => ['name' => 'Rion-des-Landes', 'uic' => '87673111'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Bordeaux-Saint-Jean à Irun', 241340665),
        'quote' => 'La mise en service officielle se déroule le 12 novembre 1854.',
        'reason' => 'gare de la section Lamothe - Dax, ouverte à la circulation en septembre 1854 (article de la ligne) ; l\'article Gare de Rion-des-Landes (oldid 217417111) date le premier train de 1854 ; VFF l\'ouvrait en 1841',
    ],

    // Vraincourt-Viéville : halte abandonnée au changement de service de décembre 2019 (cars à la place) ; les
    // horaires GTFS SNCF lus le 19/11/2026 (jusqu'au 30/04/2027) n'y ont que des cars (P920 St Dizier - Chaumont).
    [
        'station' => ['name' => 'Vraincourt-Viéville', 'uic' => '87143024'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2019-12-01',
        'precision' => 'month',
        'source_url' => $wp('Ligne de Blesme - Haussignémont à Chaumont', 235341775),
        'quote' => 'd\'autres (Eurville, Bayard, Fronville, Gudmont, Vraincourt-Vièville) ont vu leur desserte abandonnée',
        'reason' => 'arrêt supprimé au service de décembre 2019, desserte reprise par autocar (source de l\'article : France 3 Grand Est, 29/10/2019) ; halte sans fret',
    ],
    [
        'station' => ['name' => 'Vraincourt-Viéville', 'uic' => '87143024'],
        'action' => 'current',
        'status' => 'declassified',
        'source_url' => 'https://transport.data.gouv.fr/datasets/horaires-sncf',
        'quote' => 'arrêt desservi par la seule ligne d\'autocar P920 St Dizier - Chaumont du 19/11/2026 au 30/04/2027',
        'reason' => 'plus aucun train de voyageurs ne s\'y arrête (horaires GTFS SNCF, station_services : bus_only)',
    ],

    // Le Perrier (ligne 785000, entre Courpière et Ambert) : gare fermée ; les autorails touristiques d'Agrivap
    // ne roulent plus qu'au sud d'Ambert (Ambert - La Chaise-Dieu). VFF la notait touristique.
    [
        'station' => ['name' => 'Le Perrier', 'osm' => 'n13934829824'],
        'action' => 'current',
        'status' => 'declassified',
        'source_url' => $wp('Gare d\'Ambert', 235030267),
        'quote' => 'entre les gares aujourd\'hui fermées du Perrier et de Marsac-en-Livradois',
        'reason' => 'gare fermée ; le seul train touristique de la ligne roule d\'Ambert à La Chaise-Dieu (site d\'Agrivap, 22/11/2026), au sud d\'Ambert ; au mieux un vélorail parti d\'Ambert, qui ne rend pas une gare touristique (règle 6)',
    ],

    // ── 13. Fin du fret des gares restées sans date en 9 (chantier « gares-fret-2 », nuit du 22/11/2026) ─────
    // Suite de la section 9 : Wikidata (P3999, P576) ou OHM ferment la gare à la fin des voyageurs donnée par
    // l'infobox, le fret a continué (rapport storage/app/reports/agents/aprem-2111/gares-fret-2.md). Fin du fret
    // lue dans l'article de la gare, l'article de la ligne ou lignes-oubliées. « before » : seule la fin du fret
    // de la section (ou la fermeture des voies de la gare) est datée, la gare a cessé le fret au plus tard à
    // cette date ; posé seulement pour une gare (pas une halte) d'une section dont l'article dit qu'elle est
    // restée ouverte au fret après les voyageurs. Onnaing est encore ouverte au fret : pas de fermeture.
    ...array_merge(...array_map(fn (array $g) => array_values(array_filter([
        ...array_map(fn (array $m) => [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'neutralize',
            'match' => ['source' => $m[0], 'type' => $m[1], 'date' => $m[2]],
            'source_url' => $wp($g[3][2], $g[3][3]),
            'quote' => $g[3][4],
            'reason' => 'la '.($m[1] === 'demolition' ? 'démolition' : 'fermeture').' '.($m[0] === 'ohm' ? 'OHM' : 'Wikidata').' est la fin du service des voyageurs ; le fret a continué',
        ], $g[2]),
        [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'add',
            'type' => 'closure_passenger',
            'date' => $g[3][0],
            'precision' => $g[3][1],
            'source_url' => $wp($g[3][2], $g[3][3]),
            'quote' => $g[3][4],
            'reason' => 'fin des voyageurs selon l\'infobox de l\'article de la gare',
        ],
        $g[4] === null ? null : [
            'station' => ['name' => $g[0], 'wikidata' => $g[1]],
            'action' => 'add',
            'type' => 'closure',
            'date' => $g[4][0],
            'precision' => $g[4][1],
            'source_url' => $g[4][2],
            'quote' => $g[4][3],
            'reason' => $g[4][4],
        ],
    ])), [
        // gare, Wikidata, fermetures retirées [source, type, date],
        // voyageurs [date, précision, article, révision, champ cité],
        // fin du fret [date, précision, source_url, citation, raison] ou null (gare encore ouverte au fret)
        ['Iguérande', 'Q115115074', [['ohm', 'closure', '1940-05-20']],
            ['1940-05-20', 'day', 'Gare d\'Iguerande', 235030487, 'Fermeture : 20 mai 1940 (voyageurs)'],
            ['1990-05-24', 'day', $wp('Ligne du Coteau à Montchanin', 235343208),
                'le tronçon reliant Pouilly-sous-Charlieu à Iguerande est le premier à fermer, le 15 octobre 1954, puis le tronçon reliant Iguerande à Paray-le-Monial ferme le 24 mai 1990.',
                'gare au bout de la section Iguerande - Paray-le-Monial, restée ouverte au fret jusqu\'au 24/05/1990 (article de la ligne ; lignes-oubliées, même date : Pouilly - Iguerande neutralisée en 1954 faute de trafic)']],
        ['Melle', 'Q3097010', [['wikidata', 'closure', '1938-01-01']],
            ['1938-01-01', 'year', 'Gare de Melle (France)', 195929402, 'Fermeture : 1938 (voyageurs)'],
            ['1991-01-27', 'day', 'https://www.lignes-oubliees.com/index.php?page=ruffec',
                'Fermeture aux marchandises : Melle - Chef-Boutonne : 25/05/1977 Prahecq - Melle : 27/01/1991',
                'gare au bout des deux sections fermées au fret (PK 27,048) : Melle - Chef-Boutonne en 1977, Prahecq - Melle le 27/01/1991 ; l\'article de la ligne (oldid 235036250) dit le fret jusqu\'à Chef-Boutonne en 1972']],
        ['Baupte', 'Q3095921', [['wikidata', 'closure', '1971-01-01']],
            ['1971-09-26', 'day', 'Gare de Baupte', 238211244, 'Fermeture : 26 septembre 1971 (voyageurs)'],
            ['1991-06-02', 'day', $wp('Ligne de Carentan à Carteret', 239830382),
                'De Carentan à Baupte, le 2 juin 1991',
                'gare au bout de la dernière section ouverte au fret, Carentan - Baupte, fermée le 02/06/1991 (article de la ligne ; lignes-oubliées, même date)']],
        ['Sarrancolin', 'Q3097679', [['wikidata', 'closure', '1969-01-01']],
            ['1969-01-01', 'year', 'Gare de Sarrancolin', 223819525, 'Fermeture : 1969 (voyageurs)'],
            ['2004-12-12', 'day', $wp('Gare de Sarrancolin', 223819525),
                'Le 12 décembre 2004 la gare est fermée au service des marchandises',
                'fin du fret selon l\'article de la gare (site Fret SNCF) ; section Labarthe-Avezac - Sarrancolin fermée au fret fin 2005 (lignes-oubliées)']],
        ['Uzès', 'Q3095856', [['wikidata', 'closure', '1938-01-01']],
            ['1938-01-01', 'year', 'Gare d\'Uzès', 238708133, 'Fermeture : 1938 (voyageurs)'],
            ['1959-01-01', 'year', $wp('Gare d\'Uzès', 238708133),
                'Le trafic marchandises se poursuit jusqu\'en 1959 puis des « navettes très spécifiques » circulent jusqu\'aux années 1990.',
                'fin du trafic marchandises selon l\'article de la gare ; la section Uzès - Montaren ferme le 31/05/1959 (article Ligne du Martinet à Beaucaire, oldid 227988999)']],
        ['Onnaing', 'Q18761653', [['wikidata', 'closure', '1954-01-01']],
            ['1954-01-01', 'year', 'Gare d\'Onnaing', 232234616, 'Fermeture : 1954 (voyageurs) ; C\'est une gare de la SNCF, ouverte au service de fret.'],
            null],
        ['Cadenet', 'Q135972044', [['wikidata', 'closure', '1940-05-22']],
            ['1940-05-22', 'day', 'Gare de Cadenet', 241520676, 'Fermeture : 22 mai 1940 (voyageurs)'],
            ['1989-12-31', 'before', $wp('Gare de Cadenet', 241520676),
                'Le service marchandise s\'arrête à la fin des années 80',
                'fin de la desserte locale marchandises à la fin des années 1980 (article de la gare ; fiche Trains d\'Europe de la gare, qu\'il cite) : au plus tard fin 1989']],
        ['Scorbé-Clairvaux', 'Q19407015', [['wikidata', 'closure', '1946-01-01']],
            ['1946-01-01', 'year', 'Gare de Scorbé-Clairvaux', 174424647, 'Fermeture : 1946 (voyageurs)'],
            ['1980-09-26', 'before', $wp('Gare de Scorbé-Clairvaux', 174424647),
                'La section de ligne, entre Lencloitre et Châteauneuf, est définitivement fermée à tout trafic le 26 septembre 1980',
                'section restée au fret après les voyageurs (article Ligne de Loudun à Châtellerault, oldid 241462135 : reprise du fret sur toute la ligne en 1955), fermée à tout trafic le 26/09/1980']],
        ['Brie-Comte-Robert', 'Q3096067', [['wikidata', 'closure', '1953-01-01']],
            ['1953-05-18', 'day', 'Gare de Brie-Comte-Robert', 240496784, 'Fermeture : 18 mai 1953 (service voyageurs)'],
            ['1969-12-31', 'before', $wp('Ligne de Paris-Bastille à Marles-en-Brie', 241097208),
                'le tronçon de Boissy-Saint-Léger à Brie-Comte-Robert est à son tour fermé aux voyageurs. L\'activité de cette section est dès lors limitée au trafic des marchandises',
                'section Boissy-Saint-Léger - Brie-Comte-Robert limitée au fret après 1953, fermée en 1969 avec la section de Boissy-Saint-Léger à Verneuil-l\'Étang (même article) : fin du fret de la gare au plus tard en 1969']],
        ['Nielles-lès-Blequin', 'Q16508062', [['wikidata', 'closure', '1959-07-15']],
            ['1959-07-15', 'day', 'Gare de Nielles-lès-Bléquin', 238683061, 'Fermeture : 15 juillet 1959 (voyageurs)'],
            ['1969-11-30', 'before', $wp('Ligne de Saint-Omer à Hesdigneul', 236135719),
                '30 novembre 1969 : fermeture marchandises de Lumbres à Lottinghen',
                'gare de la section Lumbres - Lottinghen, restée au fret après les voyageurs (1959) jusqu\'au 30/11/1969']],
        ['Lottinghen', 'Q16508007', [['wikidata', 'closure', '1959-07-15']],
            ['1959-07-15', 'day', 'Gare de Lottinghen', 238683059, 'Fermeture : 15 juillet 1959 (voyageurs)'],
            ['1969-11-30', 'before', $wp('Ligne de Saint-Omer à Hesdigneul', 236135719),
                '30 novembre 1969 : fermeture marchandises de Lumbres à Lottinghen',
                'gare au bout de la section Lumbres - Lottinghen, restée au fret après les voyageurs (1959) jusqu\'au 30/11/1969']],
        ['St-Jores', 'Q3097548', [['wikidata', 'closure', '1971-01-01']],
            ['1971-09-26', 'day', 'Gare de Saint-Jores', 166060752, 'Fermeture : 26 septembre 1971 (voyageurs)'],
            ['1979-05-27', 'before', 'https://www.lignes-oubliees.com/index.php?page=carteret',
                'Fermeture aux marchandises : Baupte - Carteret : 27/05/1979 Carentan - Baupte : 02/06/1991',
                'gare de la section Baupte - La Haye-du-Puits, fermée au fret le 01/08/1976 selon l\'article de la ligne (oldid 239830382), le 27/05/1979 selon lignes-oubliées : au plus tard la date la plus tardive']],
        ['Hèches', 'Q3096645', [['wikidata', 'closure', '1969-01-01']],
            ['1969-01-01', 'year', 'Gare de Hèches', 235032181, 'Fermeture : 1969 (voyageurs)'],
            ['2005-12-31', 'before', 'https://www.lignes-oubliees.com/index.php?page=arreau',
                'Fermeture aux marchandises : Sarrancolin - Arreau-Cadéac : 01/02/1971 Labarthe-Avezac - Sarrancolin : fin 2005',
                'gare de la section Labarthe-Avezac - Sarrancolin (PK 131,799), restée au fret après les voyageurs (1969) jusqu\'à fin 2005']],
        ['La Châtre', 'Q24037401', [['wikidata', 'closure', '1969-01-01']],
            ['1969-01-01', 'year', 'Gare de La Châtre', 235032416, 'Fermeture : 1969 (voyageurs)'],
            ['2006-09-14', 'before', $wp('Ligne de Châteauroux à La Ville-Gozet', 240558838),
                'En septembre 1969, la ligne est fermée au trafic voyageurs dans son intégralité, ainsi qu\'au trafic fret entre La Châtre et Champillet-Urciers.',
                'seule la section au sud de La Châtre ferme au fret en 1969 ; la section des voies de la gare (PK 298,267 à 299,324) est fermée le 14/09/2006 (lignes-oubliées, déjà en base pour la ligne 696000) : fin du fret au plus tard à cette date']],
        ['Xermaménil-Lamath', 'Q2278959', [['wikidata', 'closure', '1980-01-01']],
            ['1980-06-01', 'day', 'Gare de Xermaménil - Lamath', 231986438, 'La fermeture totale du trafic voyageur a lieu le 1 juin 1980 et la fermeture totale de la ligne en 1982.'],
            ['1988-06-01', 'before', $wp('Ligne de Mont-sur-Meurthe à Bruyères', 240775080),
                'le 1 juin 1988, fin du service marchandises',
                'fin du fret de la ligne le 01/06/1988 (article de la ligne) ; l\'article de la gare dit la ligne fermée en 1982 : au plus tard la date la plus tardive']],
        ['La Motte', 'Q3096795', [['wikidata', 'closure', '2006-01-01']],
            ['2006-10-02', 'day', 'Gare de La Motte', 196084222, 'Fermeture : 2 octobre 2006 (voyageurs)'],
            ['2012-12-31', 'before', $wp('Ligne de Saint-Brieuc à Pontivy', 239795109),
                '2012 : arrêt du trafic marchandise sur la totalité de la ligne',
                'fret de la section Saint-Brieuc - Loudéac jusqu\'en 2012 (article de la ligne) ; la fermeture Wikidata du 31/12/2011 (fin de validité du référentiel des gares voyageurs SNCF) reste']],
        ['Le Pas', 'Q3098222', [['wikidata', 'closure', '2006-01-01'], ['wikidata', 'demolition', '2006-01-01']],
            ['2006-01-01', 'year', 'Gare du Pas (France)', 196084346, 'Fermeture : 2006 (voyageurs)'],
            ['2012-12-31', 'before', $wp('Ligne de Saint-Brieuc à Pontivy', 239795109),
                '2012 : arrêt du trafic marchandise sur la totalité de la ligne',
                'fret de la section Saint-Brieuc - Loudéac jusqu\'en 2012 (article de la ligne) ; la fermeture Wikidata du 31/12/2011 (fin de validité du référentiel des gares voyageurs SNCF) reste']],
        ['Ploeuc-L\'Hermitage', 'Q16508034', [['wikidata', 'closure', '2006-01-01'], ['wikidata', 'demolition', '2006-01-01']],
            ['2006-01-01', 'year', 'Gare de Plœuc - L\'Hermitage', 235033800, 'Fermeture : 2006 (voyageurs)'],
            ['2012-12-31', 'before', $wp('Ligne de Saint-Brieuc à Pontivy', 239795109),
                '2012 : arrêt du trafic marchandise sur la totalité de la ligne',
                'fret de la section Saint-Brieuc - Loudéac jusqu\'en 2012 (article de la ligne) ; la fermeture Wikidata du 31/12/2011 (fin de validité du référentiel des gares voyageurs SNCF) reste']],
        ['St-Julien (Côtes-d\'Armor)', 'Q3097552', [['wikidata', 'closure', '2006-01-01']],
            ['2006-01-01', 'year', 'Gare de Saint-Julien (Côtes-d\'Armor)', 236325504, 'Fermeture : 2006 (voyageurs) ; Services : Fret SNCF'],
            ['2012-12-31', 'before', $wp('Ligne de Saint-Brieuc à Pontivy', 239795109),
                '2012 : arrêt du trafic marchandise sur la totalité de la ligne',
                'fret de la section Saint-Brieuc - Loudéac jusqu\'en 2012 (article de la ligne ; l\'infobox de la gare la dit encore desservie par Fret SNCF)']],
    ])),

    // ── Gares ouvertes avant leurs lignes (invariant stations.opened_before_lines, 22/11/2026,
    // rapport storage/app/reports/agents/aprem-2111/gares-avant-lignes.md) ────────────────────────

    // Haussmann - Saint-Lazare : le 12/07/1999 est l'inauguration de la ligne E par le Premier ministre ;
    // la mise en service commerciale, donc l'ouverture au public de la gare, est le 14/07/1999 (article,
    // OSM et chronologie de la ligne E). La date Wikidata du 12 est celle de la cérémonie.
    [
        'station' => ['name' => 'Haussmann-St-Lazare', 'wikidata' => 'Q800898'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1999-07-14',
        'precision' => 'day',
        'source_url' => $wp('Gare de Haussmann - Saint-Lazare', 137149820),
        'quote' => 'mise en service le 14 juillet 1999',
        'reason' => 'ouverture au public le 14/07/1999 ; le 12/07/1999 est l\'inauguration de la ligne E (Éole) par le Premier ministre',
    ],

    // Enghien-les-Bains : la ligne Paris - Lille ouvre le 20/06/1846 ; la gare est ouverte « moins d'un mois
    // plus tard, le 11 juillet 1846 ». Le 14/06/1846 de Wikidata précède l'ouverture de la ligne.
    [
        'station' => ['name' => 'Enghien-les-Bains', 'wikidata' => 'Q3095740'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1846-07-11',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Enghien-les-Bains', 237632630),
        'quote' => 'La gare d\'Enghien-les-Bains est ouverte moins d\'un mois plus tard, le 11 juillet 1846',
        'reason' => 'gare ouverte après la ligne du Nord (20/06/1846), desservie « depuis le 11 juillet 1846 » ; le 14/06/1846 de Wikidata est antérieur à la ligne',
    ],

    // Dormans : section Meaux - Épernay ouverte le 26/08/1849 (gare d'Épernay, chronologie de 1849 de
    // Wikipédia, notice Mérimée pour l'inauguration du 02/09/1849). Le 21/08/1849 vient de l'article de
    // Meaux et de celui de Dormans, en contradiction avec ces trois sources.
    [
        'station' => ['name' => 'Dormans', 'wikidata' => 'Q1985272'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1849-08-26',
        'precision' => 'day',
        'source_url' => $wp('1849 dans les chemins de fer', 240921379),
        'quote' => 'section de Meaux à Épernay',
        'reason' => 'section Meaux - Épernay mise en service le 26/08/1849 (chronologie de 1849 et gare d\'Épernay, oldid 241441584) ; inauguration officielle le 02/09/1849 ; le 21/08 de Wikidata n\'est confirmé par aucune source indépendante',
    ],

    // Caudiès-de-Fenouillèdes : le 14/07/1901 est l'ouverture de Saint-Paul-de-Fenouillet à Rivesaltes ;
    // Caudiès est sur le tronçon Quillan - Saint-Paul-de-Fenouillet, ouvert le 22/05/1904 (article de la
    // gare et événement de la ligne).
    [
        'station' => ['name' => 'Caudiès', 'wikidata' => 'Q80921437'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1904-05-22',
        'precision' => 'day',
        'source_url' => $wp('Gare de Caudiès-de-Fenouillèdes', 166070156),
        'quote' => 'Cette gare a été mise en service le 22 mai 1904 lors de l\'ouverture du tronçon entre Quillan et Saint-Paul-de-Fenouillet.',
        'reason' => 'gare du tronçon Quillan - Saint-Paul-de-Fenouillet (22/05/1904) ; le 14/07/1901 est l\'ouverture du tronçon Saint-Paul - Rivesaltes',
    ],

    // Saint-Hilaire (Indre) : la section La Trimouille - Le Blanc ouvre le 12/08/1888 (article de la ligne,
    // qui cite le Journal officiel du 19/07/1889) ; l'article de la gare donne le 13/06/1888 sur une seule
    // référence (Connaissance du rail), sans recoupement.
    [
        'station' => ['name' => 'St-Hilaire (Indre)', 'wikidata' => 'Q15948845'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1888-08-12',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Montmorillon à Saint-Aigny - Le Blanc', 239210121),
        'quote' => 'La section de La Trimouille au Blanc, longue de 18 kilomètres, est mise en service le 12 août 1888.',
        'reason' => 'gare de la section La Trimouille - Le Blanc (12/08/1888 selon la ligne, appuyée sur le Journal officiel) ; le 13/06/1888 de la gare n\'a qu\'une référence',
    ],

    // Chailvet-Urcel : la date OSM du 02/06/1862 est impossible, la section Soissons - Laon ouvre le
    // 03/01/1866 (événement de la ligne, tableau des lignes de Laon en 1914). La date d'ouverture de la
    // halte est inconnue : elle est retirée plutôt que remplacée.
    [
        'station' => ['name' => 'Chailvet-Urcel', 'osm' => 'n13648417951'],
        'action' => 'neutralize',
        'match' => ['source' => 'osm', 'type' => 'opening', 'date' => '1862-06-02'],
        'source_url' => 'https://www.rail4402.fr/PAGES/L_34/L34_02_LAO_01.htm',
        'reason' => 'la section Soissons - Laon, qui porte la gare, ouvre le 03/01/1866 : une ouverture en 1862 est impossible',
    ],

    // ── 14. Gares ouvertes avant leurs lignes, suite (chantier « gares-avant-lignes-2 », nuit du 22/11/2026) ──
    // Rapport : storage/app/reports/agents/aprem-2111/gares-avant-lignes-2.md.

    // Loulay : la section Niort - Saint-Jean-d'Angély ouvre le 17/10/1881 (article de la ligne de Chartres à
    // Bordeaux et page de la ligne Thouars - Saint-Jean-d'Angély de manche-ocean.fr) ; le 10/10/1881 de
    // l'article de la gare (fiche d'inventaire) précède la section qui porte la gare.
    [
        'station' => ['name' => 'Loulay', 'wikidata' => 'Q2073351'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-10-17',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Chartres à Bordeaux-Saint-Jean', 241089765),
        'quote' => 'Elle est ouverte le 17 octobre 1881.',
        'reason' => 'section Niort - Saint-Jean-d\'Angély ouverte le 17/10/1881 (article de la ligne et manche-ocean.fr) ; le 10/10/1881 de l\'article de la gare est antérieur à la section',
    ],

    // Saint-Hippolyte (Haut-Rhin) : la section Benfeld - Colmar ouvre le 19/10/1840 (article de la gare de Colmar,
    // qui cite Le Point Colmarien ; article de la gare de Sélestat, qui cite le guide de Duplessy de 1842). Le
    // 18/10/1840 de l'article de la gare n'a qu'une référence (Palau).
    [
        'station' => ['name' => 'St-Hippolyte (Haut-Rhin)', 'wikidata' => 'Q14627841'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1840-10-19',
        'precision' => 'day',
        'source_url' => $wp('Gare de Sélestat', 241545922),
        'quote' => 'La « station de Schlestadt » est mise en service le 19 octobre 1840',
        'reason' => 'section Benfeld - Colmar mise en service le 19/10/1840 (gares de Sélestat, d\'après Duplessy 1842, et de Colmar, d\'après Le Point Colmarien) ; le 18/10/1840 n\'a qu\'une référence',
    ],

    // Bray (Loiret) : la ligne d'Orléans à Gien ouvre le 03/11/1873 (article de la ligne, mairie des Bordes pour
    // la gare des Bordes inaugurée ce jour-là) ; le 03/01/1873 de Wikidata est une inversion probable du jour et du mois.
    [
        'station' => ['name' => 'Bray', 'wikidata' => 'Q135971266'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1873-11-03',
        'precision' => 'day',
        'source_url' => $wp('Gare des Bordes', 235035255),
        'quote' => 'le 3 novembre 1873',
        'reason' => 'ligne d\'Orléans à Gien ouverte le 03/11/1873 (article de la ligne et de la gare des Bordes, mairie des Bordes) ; le 03/01/1873 de Wikidata précède l\'ouverture',
    ],

    // Ligne Frévent - Lens (chemins de fer économiques du Nord) : mise en service entière le 01/12/1895 (article de
    // la ligne, d'après Le Pas-de-Calais au dix-neuvième siècle, 1900). Le 08/08/1890 d'OHM est la date de la loi
    // d'utilité publique, recopiée sur les nœuds gare.
    [
        'station' => ['name' => 'Liencourt', 'wikidata' => 'Q64850499'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Liencourt', 235032627),
        'quote' => 'La halte de Liencourt est mise en service le 1er décembre 1895',
        'reason' => 'halte mise en service avec la ligne Lens - Frévent le 01/12/1895 ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Souchez', 'wikidata' => 'Q61726977'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'gare ouverte avec la ligne Lens - Frévent (1895 selon l\'article de la gare, 01/12/1895 selon celui de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Carency', 'wikidata' => 'Q61726849'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'gare ouverte avec la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Camblain-l\'Abbé', 'wikidata' => 'Q56692247'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Camblain-l\'Abbé', 238683047),
        'quote' => 'est mise en service en 1895',
        'reason' => 'gare ouverte avec la ligne Lens - Frévent le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],

    // Ligne Frévent - Lens, suite : les autres arrêts de la ligne entre Bouret et Villers-au-Bois (rapport
    // storage/app/reports/agents/aprem-2111/frevent-lens.md). La ligne est ouverte d'un seul tenant le 01/12/1895 ;
    // les gares sont dans l'ordre des PK de la ligne 3645 et aucune section n'a une autre date (l'article n'en
    // donne pas). Le 08/08/1890 d'OHM est la date de la loi d'utilité publique.
    [
        'station' => ['name' => 'Villers-au-Bois', 'wikidata' => 'Q61726992'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Izel-les-Hameaux', 'wikidata' => 'Q61741602'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Noyelle-Vion', 'wikidata' => 'Q65157608'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Avesnes-le-Comte', 'wikidata' => 'Q56761839'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Estrée-Wamin', 'wikidata' => 'Q66377908'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Rebreuve-sur-Canche', 'wikidata' => 'Q86665133'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Savy', 'near' => [2.5648, 50.3491, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Tilloy', 'near' => [2.5504, 50.3283, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Bouret', 'near' => [2.3219, 50.2661, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],
    [
        'station' => ['name' => 'Rebreuviette', 'near' => [2.3633, 50.2627, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-12-01',
        'precision' => 'day',
        'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Chemin_de_fer_Lens_-_Fr%C3%A9vent&oldid=228842949',
        'quote' => 'La ligne de Lens à Frévent est mise en service le 1 décembre 1895.',
        'reason' => 'arrêt de la ligne Lens - Frévent, mise en service d\'un seul tenant le 01/12/1895 (article de la ligne) ; le 08/08/1890 d\'OHM est la date de la loi d\'utilité publique',
    ],

    // Fourvière Ouest-Lyonnais (lignes 3788 et 4205) : Lyon-Saint-Just - Vaugneray 19/04/1886 ; Le Tupinier - Messimy
    // janvier 1887 (27 pour Wikipédia et ferro-lyon.net, 25 pour le forum des cheminots et le PLU de Saint-Laurent-d'Agny :
    // seul le mois est retenu) ; Messimy - Mornant 11/07/1887 (Wikipédia, forum des cheminots) ; Vaugneray gare - Vaugneray
    // bourg 06/08/1906 (décret, Bulletin des lois). OHM mettait le 19/04/1886 sur tous les nœuds gare de la ligne.
    [
        'station' => ['name' => 'Vaugneray-Bourg', 'near' => [4.6583, 45.7378, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1906-08-06',
        'precision' => 'day',
        'source_url' => $wp('Fourvière Ouest-Lyonnais', 239282879),
        'quote' => '6 août 1906 : de Vaugneray gare à Vaugneray Bourg (1,3 km).',
        'reason' => 'prolongement de Vaugneray gare au bourg ouvert le 06/08/1906 (Bulletin des lois, décret n° 39407, cité par l\'article) ; le 19/04/1886 d\'OHM est celui de la section Lyon - Vaugneray gare',
    ],
    [
        'station' => ['name' => 'Pillardière', 'near' => [4.7040, 45.7309, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-01-01',
        'precision' => 'month',
        'source_url' => $wp('Fourvière Ouest-Lyonnais', 239282879),
        'quote' => '27 janvier 1887 : du Tupinier à Messimy (5,4 km).',
        'reason' => 'section Le Tupinier - Messimy ouverte en janvier 1887 (le 27 pour Wikipédia et ferro-lyon.net, le 25 pour le forum des cheminots et le PLU de Saint-Laurent-d\'Agny) ; le 19/04/1886 d\'OHM est celui de la section Lyon - Vaugneray',
    ],
    [
        'station' => ['name' => 'Brindas', 'near' => [4.6966, 45.7183, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-01-01',
        'precision' => 'month',
        'source_url' => $wp('Fourvière Ouest-Lyonnais', 239282879),
        'quote' => '27 janvier 1887 : du Tupinier à Messimy (5,4 km).',
        'reason' => 'section Le Tupinier - Messimy ouverte en janvier 1887 (le 27 pour Wikipédia et ferro-lyon.net, le 25 pour le forum des cheminots et le PLU de Saint-Laurent-d\'Agny) ; le 19/04/1886 d\'OHM est celui de la section Lyon - Vaugneray',
    ],
    [
        'station' => ['name' => 'Messimy (FOL)', 'near' => [4.6917, 45.7007, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-01-01',
        'precision' => 'month',
        'source_url' => $wp('Fourvière Ouest-Lyonnais', 239282879),
        'quote' => '27 janvier 1887 : du Tupinier à Messimy (5,4 km).',
        'reason' => 'terminus de la section Le Tupinier - Messimy ouverte en janvier 1887 ; le 19/04/1886 d\'OHM est celui de la section Lyon - Vaugneray',
    ],
    [
        'station' => ['name' => 'Soucieu-en-Jarrest', 'near' => [4.7057, 45.6783, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-07-11',
        'precision' => 'day',
        'source_url' => $wp('Fourvière Ouest-Lyonnais', 239282879),
        'quote' => '11 juillet 1887 : de Messimy à Mornant (12,2 km).',
        'reason' => 'section Messimy - Mornant ouverte le 11/07/1887 (Wikipédia et forum des cheminots) ; le 19/04/1886 d\'OHM est celui de la section Lyon - Vaugneray',
    ],
    [
        'station' => ['name' => 'Orliénas', 'near' => [4.7021, 45.6691, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-07-11',
        'precision' => 'day',
        'source_url' => $wp('Fourvière Ouest-Lyonnais', 239282879),
        'quote' => '11 juillet 1887 : de Messimy à Mornant (12,2 km).',
        'reason' => 'section Messimy - Mornant ouverte le 11/07/1887 (Wikipédia et forum des cheminots) ; le 19/04/1886 d\'OHM est celui de la section Lyon - Vaugneray',
    ],
    [
        'station' => ['name' => 'Saint-Laurent-d\'Agny', 'near' => [4.6917, 45.6422, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-07-11',
        'precision' => 'day',
        'source_url' => $wp('Fourvière Ouest-Lyonnais', 239282879),
        'quote' => '11 juillet 1887 : de Messimy à Mornant (12,2 km).',
        'reason' => 'section Messimy - Mornant ouverte le 11/07/1887 (Wikipédia et forum des cheminots) ; le 19/04/1886 d\'OHM est celui de la section Lyon - Vaugneray',
    ],

    // Sections-dates (storage/app/reports/agents/aprem-2111/sections-dates.md) : Coussey (Vosges, ligne 026000 Bologne -
    // Pagny-sur-Meuse, PK 55,146). La gare était datée de 1867 (année déduite de la ligne) ; elle se trouve sur la
    // section Neufchâteau - Vaucouleurs, ouverte le 14/04/1873 (Bologne - Neufchâteau : 14/08/1867). Elle apparaissait
    // en service en 1870 sans voie dessinée.
    [
        'station' => ['name' => 'Coussey', 'near' => [5.6876, 48.4013, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1873-04-14',
        'precision' => 'day',
        'source_url' => $wp('Liste des gares des Vosges', 240638671),
        'quote' => 'Coussey | Gare | 026 : 55,146 | 14 avril 1873',
        'reason' => 'gare de la section Neufchâteau - Vaucouleurs ouverte le 14/04/1873 (liste des gares des Vosges ; article Ligne de Bologne à Pagny-sur-Meuse : « de Neufchâteau à Vaucouleurs, le 14 avril 1873 »)',
    ],

    // ── 15. Dates d'ouverture OHM partagées entre les gares d'une ligne ouverte par sections (chantier « dates-ohm-lignes »,
    // matinée du 22/11/2026). Rapport : storage/app/reports/agents/aprem-2111/dates-ohm-lignes.md.
    // Chaque gare est placée sur sa section par l'ordre des PK ; la date est celle de la section (article de la ligne).

    // Ligne 785000 Saint-Germain-des-Fossés - Darsac : Courpière (PK 411,3) est sur la section Pont-de-Dore - Giroux, ouverte
    // le 09/06/1883 ; le 05/05/1885 d'OHM est celui de la section Giroux - Ambert, deux gares plus loin.
    [
        'station' => ['name' => 'Courpière', 'uic' => '87734525'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-06-09',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Germain-des-Fossés à Darsac', 235337759),
        'quote' => 'de Pont-de-Dore à Giroux le 9 juin 1883',
        'reason' => 'gare de la section Pont-de-Dore - Giroux ouverte le 09/06/1883 (article de la ligne) ; le 05/05/1885 d\'OHM est la date de la section Giroux - Ambert',
    ],
    [
        'station' => ['name' => 'Courpière', 'uic' => '87734525'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1885-05-05'],
        'source_url' => $wp('Ligne de Saint-Germain-des-Fossés à Darsac', 235337759),
        'reason' => 'date de la section Giroux - Ambert (05/05/1885), pas de la gare de Courpière, située avant Giroux',
    ],

    // Ligne 798000 Saint-Georges-d'Aurac - Saint-Étienne-Châteaucreux : le Chambon-Feugerolles et la Ricamarie (PK 126,6 et
    // 129,6) sont sur la section Firminy - Saint-Étienne, ouverte le 30/05/1859 ; le 09/11/1863 d'OHM est celui de la section
    // Pont-de-Lignon - Firminy. Semène (PK 116,6) est sur cette dernière section : sa date venait de l'année de la ligne.
    [
        'station' => ['name' => 'Le Chambon-Feugerolles', 'uic' => '87726711'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-05-30',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'quote' => 'le 30 mai 1859 entre Firminy et Saint-Étienne',
        'reason' => 'gare de la section Firminy - Saint-Étienne ouverte le 30/05/1859 (article de la ligne) ; le 09/11/1863 d\'OHM est celui de la section Pont-de-Lignon - Firminy, plus loin',
    ],
    [
        'station' => ['name' => 'Le Chambon-Feugerolles', 'uic' => '87726711'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1863-11-09'],
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'reason' => 'date de la section Pont-de-Lignon - Firminy (09/11/1863), pas de la gare, située entre Firminy et Saint-Étienne',
    ],
    [
        'station' => ['name' => 'La Ricamarie', 'uic' => '87726703'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-05-30',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'quote' => 'le 30 mai 1859 entre Firminy et Saint-Étienne',
        'reason' => 'gare de la section Firminy - Saint-Étienne ouverte le 30/05/1859 (article de la ligne) ; le 09/11/1863 d\'OHM est celui de la section Pont-de-Lignon - Firminy, plus loin',
    ],
    [
        'station' => ['name' => 'La Ricamarie', 'uic' => '87726703'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1863-11-09'],
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'reason' => 'date de la section Pont-de-Lignon - Firminy (09/11/1863), pas de la gare, située entre Firminy et Saint-Étienne',
    ],
    [
        'station' => ['name' => 'Semène', 'uic' => '87726752'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1863-11-09',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Georges-d\'Aurac à Saint-Étienne-Châteaucreux', 238752330),
        'quote' => 'le 9 novembre 1863 entre Pont-de-Lignon et Firminy',
        'reason' => 'gare de la section Pont-de-Lignon - Firminy ouverte le 09/11/1863 (article de la ligne) ; la date de 1859 venait de l\'année de la ligne',
    ],

    // Ligne 770000 Moulins - Mâcon : Digoin, terminus de la section Digoin - Paray-le-Monial ouverte le 16/09/1867 (article de
    // la gare : « mise en service le 16 septembre 1867 ») ; le 10/05/1869 d'OHM est celui de la section Moulins - Digoin.
    [
        'station' => ['name' => 'Digoin', 'uic' => '87694695'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-09-16',
        'precision' => 'day',
        'source_url' => $wp('Gare de Digoin', 235031626),
        'quote' => 'La gare de Digoin est mise en service le 16 septembre 1867',
        'reason' => 'gare mise en service avec la section Digoin - Paray-le-Monial (article de la gare et de la ligne) ; le 10/05/1869 d\'OHM est la date de la section Moulins - Digoin',
    ],
    [
        'station' => ['name' => 'Digoin', 'uic' => '87694695'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1869-05-10'],
        'source_url' => $wp('Gare de Digoin', 235031626),
        'reason' => 'date de la section Moulins - Digoin (10/05/1869), postérieure à l\'ouverture de la gare le 16/09/1867',
    ],

    // Ligne 775000 Paray-le-Monial - Givors-Canal : Saint-Laurent-d'Oingt (PK 81,5) est sur la section Lozanne - Lamure-sur-Azergues,
    // ouverte le 04/10/1895 ; sa date venait de l'année de la ligne (01/01/1895).
    [
        'station' => ['name' => 'St-Laurent-d\'Oingt', 'uic' => '87721795'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paray-le-Monial à Givors-Canal', 237061238),
        'quote' => 'le 4 octobre 1895 mise en service de la section de Lozanne à Lamure-sur-Azergues.',
        'reason' => 'gare de la section Lozanne - Lamure-sur-Azergues ouverte le 04/10/1895 (article de la ligne) ; la date de l\'année seule venait de la ligne entière',
    ],

    // Ligne 795000 Bonson - Sembadel : les gares sans date propre prenaient l'année de la ligne entière (1873). Sections de l'article
    // de la ligne : Bonson - Saint-Bonnet-le-Château 04/10/1873, Saint-Bonnet-le-Château - Craponne-sur-Arzon 01/08/1897, Craponne-sur-Arzon -
    // Sembadel 15/09/1902. Saint-Marcellin et Saint-Bonnet (PK 4,1 et 26,7) sont sur la première, Estivareilles et Usson-Saint-Pal (PK 31,6
    // et 39,8) sur la deuxième, Jullianges (PK 58,2) sur la troisième.
    [
        'station' => ['name' => 'St-Marcellin (Loire)', 'uic' => '87726505'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1873-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bonson à Sembadel', 235341794),
        'quote' => 'De Bonson à Saint-Bonnet-le-Château le 4 octobre 1873.',
        'reason' => 'gare de la section Bonson - Saint-Bonnet-le-Château ouverte le 04/10/1873 (article de la ligne) ; la date de l\'année seule venait de la ligne entière',
    ],
    [
        'station' => ['name' => 'Saint-Bonnet-le-Château', 'near' => [4.0667, 45.429, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1873-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bonson à Sembadel', 235341794),
        'quote' => 'De Bonson à Saint-Bonnet-le-Château le 4 octobre 1873.',
        'reason' => 'gare de la section Bonson - Saint-Bonnet-le-Château ouverte le 04/10/1873 (article de la ligne) ; la date de l\'année seule venait de la ligne entière',
    ],
    [
        'station' => ['name' => 'Estivareilles', 'wikidata' => 'Q124055174'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-08-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bonson à Sembadel', 235341794),
        'quote' => 'De Saint-Bonnet-le-Château à Craponne-sur-Arzon le 1er août 1897.',
        'reason' => 'gare de la section Saint-Bonnet-le-Château - Craponne-sur-Arzon ouverte le 01/08/1897 (article de la ligne) ; la date de 1873 venait de l\'année de la ligne entière',
    ],
    [
        'station' => ['name' => 'Usson-St-Pal', 'uic' => '87726588'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-08-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bonson à Sembadel', 235341794),
        'quote' => 'De Saint-Bonnet-le-Château à Craponne-sur-Arzon le 1er août 1897.',
        'reason' => 'gare de la section Saint-Bonnet-le-Château - Craponne-sur-Arzon ouverte le 01/08/1897 (article de la ligne) ; la date de 1873 venait de l\'année de la ligne entière',
    ],
    [
        'station' => ['name' => 'Jullianges', 'uic' => '87726620'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1902-09-15',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bonson à Sembadel', 235341794),
        'quote' => 'De Craponne-sur-Arzon à Sembadel le 15 septembre 1902.',
        'reason' => 'gare de la section Craponne-sur-Arzon - Sembadel ouverte le 15/09/1902 (article de la ligne) ; la date de 1873 venait de l\'année de la ligne entière',
    ],

    // Ligne 784000 Clermont-Ferrand - Saint-Just-sur-Loire : les gares sans date propre prenaient la date de la ligne de 1827
    // (Saint-Étienne - Andrézieux, 30/06/1827). Le tronçon de Clermont-Ferrand à Pont-de-Dore ouvre le 10/05/1869 (articles des gares de
    // Vertaizon et de Pont-de-Dore) ; Thiers et Courty ouvrent le 15/05/1872 (article de la gare de Thiers). Seychalles-Moissat,
    // Saint-Jean-d'Heurs et Lezoux (PK 19,8, 28,6 et 24,1) sont entre Vertaizon et Pont-de-Dore, Courty (PK 36,7) entre Pont-de-Dore
    // et Thiers.
    [
        'station' => ['name' => 'Seychalles-Moissat', 'uic' => '87734434'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-05-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Vertaizon', 235034883),
        'quote' => 'lors de l\'ouverture du tronçon de Clermont-Ferrand à Pont-de-Dore de la ligne de Clermont-Ferrand à Montbrisson',
        'reason' => 'gare du tronçon Clermont-Ferrand - Pont-de-Dore ouvert le 10/05/1869 (article de la gare de Vertaizon) ; la date de 1827 était celle de la ligne de Saint-Étienne à Andrézieux',
    ],
    [
        'station' => ['name' => 'St-Jean-d\'Heurs', 'uic' => '87731554'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-05-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Vertaizon', 235034883),
        'quote' => 'lors de l\'ouverture du tronçon de Clermont-Ferrand à Pont-de-Dore de la ligne de Clermont-Ferrand à Montbrisson',
        'reason' => 'gare du tronçon Clermont-Ferrand - Pont-de-Dore ouvert le 10/05/1869 (article de la gare de Vertaizon) ; la date de 1827 était celle de la ligne de Saint-Étienne à Andrézieux',
    ],
    [
        'station' => ['name' => 'Lezoux', 'uic' => '87734442'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-05-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pont-de-Dore', 235994354),
        'quote' => 'lorsqu\'elle ouvre à l\'exploitation le tronçon de Clermont-Ferrand à Pont-de-Dore',
        'reason' => 'gare du tronçon Clermont-Ferrand - Pont-de-Dore ouvert le 10/05/1869 (articles des gares de Vertaizon et de Pont-de-Dore) ; Wikidata ne donne que l\'année 1869',
    ],
    [
        'station' => ['name' => 'Lezoux', 'uic' => '87734442'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1869-01-01'],
        'source_url' => $wp('Gare de Pont-de-Dore', 235994354),
        'reason' => 'année seule, précédant l\'ouverture du tronçon Clermont-Ferrand - Pont-de-Dore le 10/05/1869',
    ],
    [
        'station' => ['name' => 'Courty Gare', 'near' => [3.4876, 45.8652, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1872-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Thiers', 230264209),
        'quote' => 'La gare ouvrit ses portes le 15 mai 1872',
        'reason' => 'gare de Courty mise en service le 15/05/1872, avec celle de Thiers (article de la gare de Thiers) ; la date de 1827 était celle de la ligne de Saint-Étienne à Andrézieux',
    ],

    // Ligne de Montereau à Château-Landon (chemins de fer départementaux, voie métrique, sans code) : Montereau - Souppes ouvre le
    // 01/10/1888 et Souppes - Château-Landon en janvier 1889 (01/01 pour le texte de l'article, 15/01 pour la liste des gares : le mois
    // seul est retenu). Château-Landon est le terminus de la seconde section ; Égreville (PK 30,9, ouverte en 1888 selon les deux
    // passages de l'article, le 30/09 ou le 01/10) avait la date OHM du 10/11/1901. Chaintraux et Fonteneilles-Coudray, entre
    // Égreville et Souppes, ne sont pas corrigées : l'article place leur section en 1888 dans le texte et en 1889 dans la liste.
    [
        'station' => ['name' => 'Chateau Landon', 'near' => [2.6966, 48.1486, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1889-01-01',
        'precision' => 'month',
        'source_url' => $wp('Ligne de Montereau à Château-Landon', 228407898),
        'quote' => 'la deuxième section de Souppes à Château-Landon est mise en service le 1er janvier 1889',
        'reason' => 'terminus de la section Souppes - Château-Landon, ouverte en janvier 1889 (article de la ligne) ; le 01/10/1888 d\'OHM est celui de la section Montereau - Souppes',
    ],
    [
        'station' => ['name' => 'Chateau Landon', 'near' => [2.6966, 48.1486, 0.3]],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1888-10-01'],
        'source_url' => $wp('Ligne de Montereau à Château-Landon', 228407898),
        'reason' => 'date de la section Montereau - Souppes (01/10/1888), pas du terminus Château-Landon',
    ],
    [
        'station' => ['name' => 'Egreville', 'near' => [2.872, 48.1788, 0.3]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1888-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Montereau à Château-Landon', 228407898),
        'quote' => 'La section de Montereau à Souppes est mise en service le 1er octobre 1888',
        'reason' => 'gare de la section Montereau - Égreville, ouverte en 1888 (article de la ligne : 30/09 ou 01/10 selon le passage) ; le 10/11/1901 d\'OHM est postérieur à la section',
    ],
    [
        'station' => ['name' => 'Egreville', 'near' => [2.872, 48.1788, 0.3]],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1901-11-10'],
        'source_url' => $wp('Ligne de Montereau à Château-Landon', 228407898),
        'reason' => 'date postérieure de 13 ans à l\'ouverture de la section Montereau - Égreville (1888)',
    ],

    // ── 16. Dates d'ouverture OHM hors de la section (chantier « gares-hors-section », matinée du 22/11/2026).
    // Rapport : storage/app/reports/agents/aprem-2111/gares-hors-section.md.
    // Saint-Étienne-Carnot : gare aérienne construite sur un viaduc pour désengorger Châteaucreux, mise en service le
    // 28/09/1980 (article de la gare ; Wikidata P1619 donne 1980). Le 06/08/1905 d'OHM ne correspond à aucune gare à Carnot
    // dans l'article : il est antérieur de 75 ans.
    [
        'station' => ['name' => 'St-Étienne-Carnot', 'uic' => '87726901'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1980-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Étienne-Carnot', 241233261),
        'quote' => 'Cette gare est mise en service le 28 septembre 1980.',
        'reason' => 'mise en service le 28/09/1980 (article de la gare, Wikidata P1619 : 1980) ; le 06/08/1905 d\'OHM est antérieur de 75 ans et sans autre source',
    ],
    [
        'station' => ['name' => 'St-Étienne-Carnot', 'uic' => '87726901'],
        'action' => 'neutralize',
        'match' => ['source' => 'ohm', 'type' => 'opening', 'date' => '1905-08-06'],
        'source_url' => $wp('Gare de Saint-Étienne-Carnot', 241233261),
        'reason' => 'date de 1905 contredite par l\'article de la gare (mise en service le 28/09/1980) et par Wikidata (1980)',
    ],

    // ── 17. État du jour « fret seul » (chantier « gare-fret-actuel », 22/11/2026).
    // Rapport : storage/app/reports/agents/aprem-2111/gare-fret-actuel.md.
    // Onnaing : fermée aux voyageurs en 1954 (section 13), mais l'article de la gare la dit « ouverte au service de fret »
    // pour deux installations terminales embranchées (annexe du Document de référence du réseau de SNCF Réseau citée par
    // l'article) ; les travaux de continuité de desserte des industriels embranchés de Valenciennes - Blanc-Misseron
    // (2021) comptent Toyota à Onnaing parmi les trois industriels desservis (presse, non relue en détail).
    [
        'station' => ['name' => 'Onnaing', 'wikidata' => 'Q18761653'],
        'action' => 'current',
        'status' => 'no_commercial',
        'source_url' => $wp('Gare d\'Onnaing', 232234616),
        'quote' => 'La gare est ouverte au service de fret',
        'reason' => 'gare fermée aux voyageurs en 1954 et encore desservie par le fret aujourd\'hui (deux installations terminales embranchées)',
    ],

    // ── 18. Gares sans date d'ouverture : audit-gares-suite, après-midi du 22/11/2026.
    // Rapport : storage/app/reports/agents/aprem-2111/audit-gares-suite.md.

    // Ligne 838000 Saint-Julien (Troyes) - Gray : l'article de la ligne donne les mises en service par section (Troyes -
    // Bar-sur-Seine 20/07/1862, Bar-sur-Seine - Châtillon-sur-Seine 19/10/1868, Châtillon-sur-Seine - Is-sur-Tille
    // 09/12/1882, Is-sur-Tille - Gray 27/10/1888), la fermeture aux voyageurs de Châtillon-sur-Seine - Gray le 02/03/1969
    // et de Troyes - Châtillon-sur-Seine le 31/05/1980, la fin du fret entre Mirebeau et Champagne le 30/05/1975. Aucune
    // de ces gares n'a d'article propre : la date est celle de la section, lue d'après l'ordre des PK. Les événements de
    // la ligne manquent en base (le gabarit de date de l'article n'est pas lu), les gares n'avaient donc aucune ouverture.
    // Gares laissées de côté, à faire dater par une source propre : Prusly-sur-Ource et Coulmier-le-Sec (codes UIC de haltes
    // récentes, doublon probable de Prusly-Vilotte pour la première), Mirebeau-sur-Bèze (jonction avec la ligne de Dijon,
    // ouverte en 1891), Saint-Julien-les-Villas.
    ...array_merge(...array_map(function (array $s) use ($wp) {
        [$section, $date, $quote, $stations] = $s;
        $src = $wp('Ligne de Saint-Julien (Troyes) à Gray', 239109653);

        return array_map(fn (array $g) => [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'add',
            'type' => 'opening',
            'date' => $date,
            'precision' => 'day',
            'source_url' => $src,
            'quote' => $quote,
            'reason' => "gare de la section {$section}, mise en service le ".implode('/', array_reverse(explode('-', $date))).' (article de la ligne) ; pas d\'article propre à la gare, la date est celle de la section',
        ], $stations);
    }, [
        ['Troyes - Bar-sur-Seine', '1862-07-20', 'Troyes - Bar-sur-Seine le 20/7/1862', [
            ['Buchères-Verrières', '87118430'], ['St-Thibault (Aube)', '87118448'], ['Clérey', '87118455'],
            ['St-Parres-lès-Vaudes', '87118463'], ['Fouchères-Vaux', '87118471'], ['Courtenot-Lenclos', '87118489'],
            ['Bar-sur-Seine', '87118497'],
        ]],
        ['Bar-sur-Seine - Châtillon-sur-Seine', '1868-10-19', 'Bar-sur-Seine - Châtillon-sur-Seine le 19/10/1868', [
            ['Polisot', '87118505'], ['Gyé-sur-Seine', '87118513'], ['Plaines', '87118521'],
            ['Mussy-sur-Seine', '87118539'], ['Pothières', '87118547'],
        ]],
        ['Châtillon-sur-Seine - Is-sur-Tille', '1882-12-09', 'Châtillon-sur-Seine - Is-sur-Tille le 9/12/1882', [
            ['Grancey-le-Château', '87143289'], ['Prusly-Vilotte', '87143073'], ['Vanvey-Villiers', '87142869'],
            ['Leuglay-Voulaines', '87142851'], ['Recey-sur-Ource', '87142844'], ['Villars-Santenoge', '87142836'],
            ['Poinson-lès-Grancey', '87143131'], ['Poinson-Beneuvre', '87142828'], ['Pavillon-lès-Grancey', '87142810'],
            ['Marey-sur-Tille', '87142802'], ['Villey-Crécey', '87142794'],
        ]],
        ['Is-sur-Tille - Gray', '1888-10-27', 'Is-sur-Tille et Gray est mise en service le 27/10/1888', [
            ['Tilchâtel', '87142786'], ['Lux', '87142778'], ['Bèze', '87142760'], ['Oisilly-Renève', '87142745'],
            ['Champagne-sur-Vingeanne', '87142737'], ['Autrey-lès-Gray', '87142729'],
        ]],
    ])),
    ...array_merge(...array_map(function (array $s) use ($wp) {
        [$section, $date, $stations] = $s;

        return array_map(fn (array $g) => [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'add',
            'type' => 'closure_passenger',
            'date' => $date,
            'precision' => 'day',
            'source_url' => $wp('Ligne de Saint-Julien (Troyes) à Gray', 239109653),
            'quote' => 'La ligne ferme au service voyageurs le 2/3/1969 pour la section Châtillon-sur-Seine - Gray et le 31 mai 1980 pour la section Troyes - Châtillon-sur-Seine.',
            'reason' => "fin du service voyageurs de la section {$section} le ".implode('/', array_reverse(explode('-', $date))).' (article de la ligne)',
        ], $stations);
    }, [
        ['Troyes - Châtillon-sur-Seine', '1980-05-31', [
            ['Buchères-Verrières', '87118430'], ['St-Thibault (Aube)', '87118448'], ['Clérey', '87118455'],
            ['St-Parres-lès-Vaudes', '87118463'], ['Fouchères-Vaux', '87118471'], ['Courtenot-Lenclos', '87118489'],
            ['Bar-sur-Seine', '87118497'], ['Polisot', '87118505'], ['Gyé-sur-Seine', '87118513'], ['Plaines', '87118521'],
            ['Mussy-sur-Seine', '87118539'], ['Pothières', '87118547'],
        ]],
        ['Châtillon-sur-Seine - Gray', '1969-03-02', [
            ['Grancey-le-Château', '87143289'], ['Prusly-Vilotte', '87143073'], ['Vanvey-Villiers', '87142869'],
            ['Leuglay-Voulaines', '87142851'], ['Recey-sur-Ource', '87142844'], ['Villars-Santenoge', '87142836'],
            ['Poinson-lès-Grancey', '87143131'], ['Poinson-Beneuvre', '87142828'], ['Pavillon-lès-Grancey', '87142810'],
            ['Marey-sur-Tille', '87142802'], ['Villey-Crécey', '87142794'], ['Tilchâtel', '87142786'], ['Lux', '87142778'],
            ['Bèze', '87142760'], ['Oisilly-Renève', '87142745'], ['Champagne-sur-Vingeanne', '87142737'],
            ['Autrey-lès-Gray', '87142729'],
        ]],
    ])),
    // Oisilly-Renève est entre Mirebeau et Champagne-sur-Vingeanne : fin du trafic marchandises le 30/05/1975.
    [
        'station' => ['name' => 'Oisilly-Renève', 'uic' => '87142745'],
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1975-05-30',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Julien (Troyes) à Gray', 239109653),
        'quote' => 'Le trafic marchandises cesse entre Villars et Poinson et entre Mirebeau-sur-Bèze et Champagne-sur-Vingeanne le 30/5/1975.',
        'reason' => 'gare entre Mirebeau et Champagne-sur-Vingeanne, section fermée au fret le 30/05/1975 (article de la ligne)',
    ],

    // Ligne 791000 Le Puy - Lalevade-d'Ardèche (transcévenole) : ligne jamais ouverte. Travaux commencés en 1911, suspendus
    // en 1914 puis arrêtés en 1939 (plateforme, gares et installations prêtes jusqu'à la limite des deux départements),
    // ligne déclassée le 30/11/1941 sans avoir reçu de train (article de la ligne, lignes-oubliées : « arrêt des travaux
    // vers 1939 »). Les 14 gares prévues entre Lantriac et Pont-de-Labeaume sont « projetées ». Lalevade-d'Ardèche (gare
    // réelle de la ligne Vogüé - Lalevade, ouverte en 1879) et Brives-Charensac (gare de la ligne du Puy à Langogne) restent.
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'near' => [$g[1], $g[2], 0.3]],
        'action' => 'kind',
        'kind' => 'projected',
        'source_url' => $wp('Ligne transcévenole', 241145861),
        'quote' => 'En 1939, la plateforme de la ligne, les gares et toutes les installations sont prêtes jusqu\'à la limite des deux départements. [...] Le 30 novembre 1941 dans les chemins de fer, la ligne est déclassée par une loi.',
        'reason' => 'gare prévue sur la ligne du Puy à Nieigles-Prades (transcévenole), jamais mise en service : travaux arrêtés vers 1939, ligne déclassée le 30/11/1941 sans avoir reçu de train',
    ], [
        ['Lantriac', 3.9921, 44.9985], ['Laussonne', 4.0169, 44.9616], ['Le Monastier-sur-Gazeille', 3.9953, 44.9418],
        ['Présailles', 4.0337, 44.8843], ['Issarlès', 4.0340, 44.8524], ['Le Plô', 4.0456, 44.8307],
        ['Lac d\'Issarlès', 4.0561, 44.8231], ['La Palisse', 4.1020, 44.7810], ['Saint-Cirgues-en-Montagne', 4.0958, 44.7548],
        ['Le Roux', 4.1291, 44.7042], ['Montpezat', 4.2041, 44.6943], ['Thueyts', 4.2248, 44.6784],
        ['Meyras-Burzet', 4.2672, 44.6869], ['Pont-de-Labeaume', 4.2923, 44.6615],
    ]),

    // Ligne 684000 Étampes - Beaune-la-Rolande : arrêts du vélorail de la Juine (exploitation de loisir mise en service le
    // 09/07/2022 sur 13 km de la ligne), entrés par des objets OSM : ce ne sont pas des gares.
    [
        'station' => ['name' => 'Arrivée du vélorail (gare d\'Etampes)', 'osm' => 'n534896944'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Ligne d\'Étampes à Beaune-la-Rolande', 239193697),
        'quote' => 'mise en place d\'un vélorail circulant sur les 13 km de la ligne d\'Étampes à Méréville',
        'reason' => 'point d\'arrivée du vélorail de la Juine (loisir), pas une gare',
    ],
    [
        'station' => ['name' => 'Départ du vélorail de la Juine', 'osm' => 'n534916303'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Ligne d\'Étampes à Beaune-la-Rolande', 239193697),
        'quote' => 'mise en place d\'un vélorail circulant sur les 13 km de la ligne d\'Étampes à Méréville',
        'reason' => 'point de départ du vélorail de la Juine (loisir), pas une gare',
    ],
    // ── 19. Gares sans date d'ouverture dont l'article de la gare donne les dates (audit-gares-suite, 22/11/2026).
    // Aucune ne portait de date propre : Wikidata sans P1619, aucun événement Wikipédia en base. Règle : ouverture lue
    // dans l'infobox ou le texte de l'article de la gare ; fin des voyageurs ou du fret seulement quand l'infobox ou le
    // texte la nomme (« voyageurs », « marchandises ») ou quand la ligne secondaire a fermé d'un bloc (suppression de la
    // ligne, section déclassée). Une fermeture d'infobox sans qualificatif sur une ligne qui garde du fret n'est pas posée.
    // Le 27/09/1969 de l'infobox des gares de la section Évreux - Glos-Montfort et le 26/09/1969 du texte de ces mêmes
    // articles divergent : précision au mois. Boynes (article recopié de Méréville, fermée en 1939 selon la ligne) et
    // Vers (aucun article) restent sans date.
    ...array_merge(...array_map(function (array $g) use ($wp) {
        [$name, $qid, $title, $oldid, $events] = $g;
        $labels = ['opening' => 'ouverture', 'closure_passenger' => 'fin du service voyageurs', 'closure_freight' => 'fin du trafic marchandises', 'closure' => 'fermeture'];

        return array_map(fn (array $e) => [
            'station' => ['name' => $name, 'wikidata' => $qid],
            'action' => 'add',
            'type' => $e[0],
            'date' => $e[1],
            'precision' => $e[2],
            'source_url' => $wp($title, $oldid),
            'quote' => $e[3],
            'reason' => $labels[$e[0]].' de la gare, d\'après l\'article de la gare (infobox et texte)',
        ], $events);
    }, [
        ['Marseille-Maritime-Arenc', 'Q105540959', 'Gare de Marseille-Maritime-Arenc', 241260050, [['opening', '1896-01-01', 'year', 'Mise en service : 1896']]],
        ['La Chapelle-St-Laurent', 'Q65153888', 'Gare de La Chapelle-Saint-Laurent', 236682565, [['opening', '1887-06-05', 'day', 'La gare de La Chapelle-Saint-Laurent est mise en service le 5 juin 1887']]],
        ['Méréville', 'Q123498082', 'Gare de Méréville', 235033427, [['opening', '1905-02-19', 'day', 'La gare est inaugurée le 19 février 1905 lors de l\'ouverture de la ligne'], ['closure_passenger', '1969-11-04', 'day', 'Fermeture : 4 novembre 1969 (voyageurs)']]],
        ['Le Musée La Mine Image', 'Q108409128', 'Gare du Musée La Mine Image', 209163842, [['opening', '2021-07-20', 'day', 'Un quai pré-existait déjà au niveau de la gare avant sa mise en service le 20 juillet 2021']]],
        ['Toulouse-Roguet', 'Q56311143', 'Gare de Toulouse-Roguet', 240777604, [['opening', '1900-01-01', 'year', 'La gare a été mise en service en 1900 par la CFSO, puis fermée en 1949'], ['closure', '1949-12-31', 'day', 'Fermeture : 31 décembre 1949']]],
        ['La Motte-d\'Aveillans', 'Q108388622', 'Gare de La Motte-d\'Aveillans', 233873844, [['opening', '1888-08-01', 'day', 'La gare de La Motte-d\'Aveillans a été mise en service le 1er août 1888 et a été fermée en 1950 aux voyageurs'], ['closure_passenger', '1950-01-01', 'year', 'fermée en 1950 aux voyageurs']]],
        ['Notre-Dame-de-Commiers', 'Q108388471', 'Gare de Notre-Dame-de-Commiers', 209163834, [['opening', '1888-08-01', 'day', 'La gare de Notre-Dame-de-Commiers a été mise en service le 1er août 1888 et a été fermée en 1950 aux voyageurs'], ['closure_passenger', '1950-01-01', 'year', 'fermée en 1950 aux voyageurs']]],
        ['Buzet-sur-Tarn', 'Q48757113', 'Gare de Buzet-sur-Tarn', 220839043, [['opening', '1884-12-15', 'day', 'La gare de Buzet-sur-Tarn est mise en service le 15 décembre 1884']]],
        ['La Châtaigneraie', 'Q135426465', 'Gare de La Châtaigneraie', 235082686, [['opening', '1890-05-28', 'day', 'La gare de La Châtaigneraie est inaugurée le 28 mai 1890']]],
        ['Harcourt - La Neuville-du-Bosc', 'Q113835965', 'Gare d\'Harcourt - La Neuville-du-Bosc', 211562284, [['opening', '1888-07-01', 'day', 'mise en service : 1 juillet 1888 jusqu\'à Glos-Montfort'], ['closure_passenger', '1969-09-01', 'month', 'Fermeture : 27 septembre 1969 (voyageurs)'], ['closure_freight', '1971-02-01', 'day', 'Fermeture : 1 février 1971 (marchandises)']]],
        ['Calleville', 'Q113835985', 'Gare de Calleville', 207791430, [['opening', '1888-07-01', 'day', 'mise en service : 1 juillet 1888 jusqu\'à Glos-Montfort'], ['closure_passenger', '1969-09-01', 'month', 'Fermeture : 27 septembre 1969 (voyageurs)'], ['closure_freight', '1971-02-01', 'day', 'Fermeture : 1 février 1971 (marchandises)']]],
        ['Neubourg', 'Q113836016', 'Gare du Neubourg', 193670823, [['opening', '1888-01-02', 'day', 'mise en service le 2 janvier 1888 entre Évreux et Le Neubourg'], ['closure_passenger', '1969-09-01', 'month', 'Fermeture : 27 septembre 1969 (voyageurs)'], ['closure_freight', '1990-09-30', 'day', 'Fermeture : 30 septembre 1990 (marchandises)']]],
        ['Bacquepuis', 'Q113835973', 'Gare de Bacquepuis', 240379951, [['opening', '1888-01-02', 'day', 'le 2 janvier 1888 entre Évreux et Le Neubourg, comprenant la gare de Bacquepuis'], ['closure_passenger', '1969-09-01', 'month', 'Fermeture : 27 septembre 1969 (voyageurs)'], ['closure_freight', '1990-11-30', 'day', 'Fermeture : 30 novembre 1990 (marchandises)']]],
        ['Quittebeuf', 'Q113836009', 'Gare de Quittebeuf', 241531539, [['opening', '1888-01-02', 'day', 'le 2 janvier 1888 entre Évreux et Le Neubourg, comprenant la gare de Quittebeuf'], ['closure_passenger', '1969-09-01', 'month', 'Fermeture : 27 septembre 1969 (voyageurs)'], ['closure_freight', '1990-11-30', 'day', 'Fermeture : 30 novembre 1990 (marchandises)']]],
        ['Gimouille', 'Q115867420', 'Gare de Gimouille', 239346753, [['opening', '1868-05-01', 'day', 'Mise en service : 1 mai 1868']]],
        ['Coulonges-sur-l’Autize', 'Q135426476', 'Gare de Coulonges-sur-l’Autize', 235082769, [['opening', '1868-12-28', 'day', 'Cette gare fut inaugurée le 28 décembre 1868']]],
        ['Moncoutant', 'Q135426482', 'Gare de Moncoutant', 235082928, [['opening', '1869-01-01', 'year', 'Mise en service : 1869']]],
        ['Aspet-Sarradère', 'Q60847561', 'Gare d\'Aspet-Sarradère', 240986739, [['opening', '1906-01-01', 'day', 'Il faut attendre encore trois mois pour la mise en service le 1 janvier 1906'], ['closure', '1936-01-10', 'day', 'Fermeture : 10 janvier 1936']]],
        ['Brosville', 'Q113835983', 'Gare de Brosville', 241492570, [['opening', '1872-05-10', 'day', 'La ligne est ouverte le 10 mai 1872, comprenant la gare de Brosville'], ['closure_passenger', '1940-03-01', 'day', 'Le service voyageurs est supprimé le 1 mars 1940 sur toute la ligne'], ['closure_freight', '1959-05-31', 'day', 'le 31 mai 1959 entre Brosville et Acquigny, ce qui entraîne la fermeture définitive de la gare']]],
        ['Boullay-les-Troux', 'Q106409694', 'Gare de Boullay-les-Troux', 234386399, [['opening', '1867-08-26', 'day', 'Le prolongement et les nouvelles gares de Boullay-les-Troux et de Limours sont inaugurés le 26 août 1867']]],
        ['Neuville-de-Poitou', 'Q135426470', 'Gare de Neuville-de-Poitou', 241417330, [['opening', '1874-05-15', 'day', 'Mise en service : 15 mai 1874']]],
        ['Aizenay', 'Q135439734', 'Gare d\'Aizenay', 235030242, [['opening', '1880-09-20', 'day', 'La gare d\'Aizenay fut inaugurée le 20 septembre 1880']]],
        ['Derval', 'Q135426518', 'Gare de Derval', 236876402, [['opening', '1882-01-01', 'year', 'La gare de Derval est officiellement inaugurée en 1882']]],
        ['Hondeghem', 'Q60846716', 'Gare d\'Hondeghem', 235030477, [['opening', '1894-01-01', 'year', 'La gare d\'Hondeghem est mise en service en 1894'], ['closure', '1954-01-01', 'year', 'Elle est fermée en 1954 lors de la suppression de la ligne']]],
        ['Urville-en-Hague', 'Q3095851', 'Gare d\'Urville-en-Hague', 235030593, [['opening', '1911-01-01', 'year', 'Mise en service : 1911'], ['closure', '1945-10-01', 'month', 'Fermeture : octobre 1945']]],
        ['Bierne', 'Q60847116', 'Gare de Bierne', 231973301, [['opening', '1914-01-01', 'year', 'La gare de Bierne est mise en service en 1914'], ['closure', '1951-01-01', 'year', 'Elle est fermée en 1951 lors de la suppression de la ligne']]],
        ['L\'Étang-Salé', 'Q3096741', 'Gare de L\'Étang-Salé', 229991127, [['opening', '1882-01-01', 'year', 'Mise en service : 1882']]],
        ['Segré', 'Q135439750', 'Gare de Segré', 236080863, [['opening', '1876-01-01', 'year', 'La gare de Segré fut inaugurée en 1876']]],
        ['Montaigut', 'Q60847730', 'Gare de Montaigut (Haute-Garonne)', 205932339, [['opening', '1903-01-01', 'year', 'Mise en service : 1903'], ['closure', '1946-01-01', 'year', 'Fermeture : 1946']]],
        ['Mondonville', 'Q60847696', 'Gare de Mondonville', 239737173, [['opening', '1903-01-01', 'year', 'Mise en service : 1903'], ['closure', '1946-01-01', 'year', 'Fermeture : 1946']]],
        ['Lévignac', 'Q60847655', 'Gare de Lévignac', 220839113, [['opening', '1903-01-01', 'year', 'Mise en service : 1903'], ['closure', '1946-01-01', 'year', 'Fermeture : 1946']]],
        ['Daux', 'Q60847769', 'Gare de Daux', 240844720, [['opening', '1903-01-01', 'year', 'Mise en service : 1903'], ['closure', '1946-01-01', 'year', 'Fermeture : 1946']]],
        ['Saint-Cézert', 'Q60847803', 'Gare de Saint-Cézert', 188279973, [['opening', '1903-01-01', 'year', 'Mise en service : 1903']]],
        ['Grenade', 'Q60847657', 'Gare de Grenade (Haute-Garonne)', 188267795, [['opening', '1903-01-01', 'year', 'Mise en service : 1903'], ['closure', '1947-01-01', 'year', 'Fermeture : 1947']]],
        ['Cornebarrieu', 'Q56311192', 'Gare de Cornebarrieu', 220839059, [['opening', '1903-01-01', 'year', 'Mise en service : 1903'], ['closure', '1947-01-01', 'year', 'Fermeture : 1947']]],
        ['Arcambal', 'Q113630410', 'Gare d\'Arcambal', 239161289, [['opening', '1886-07-14', 'day', 'Mise en service : 14 juillet 1886'], ['closure_passenger', '1980-09-28', 'day', 'Fermeture : 28 septembre 1980 (voyageurs)']]],
        ['St-Géry', 'Q113496362', 'Gare de Saint-Géry', 239161409, [['opening', '1886-07-14', 'day', 'Mise en service : 14 juillet 1886'], ['closure_passenger', '1980-09-28', 'day', 'Fermeture : 28 septembre 1980 (voyageurs)']]],
        ['Conduché', 'Q113626017', 'Gare de Conduché', 196437962, [['opening', '1886-07-14', 'day', 'Mise en service : 14 juillet 1886'], ['closure_passenger', '1980-09-28', 'day', 'Fermeture : 28 septembre 1980 (voyageurs)']]],
        ['St-Cirq-Lapopie', 'Q113463484', 'Gare de Saint-Cirq-Lapopie', 239161404, [['opening', '1886-07-14', 'day', 'Mise en service : 14 juillet 1886'], ['closure_passenger', '1980-09-28', 'day', 'Fermeture : 28 septembre 1980 (voyageurs)']]],
        ['Toirac', 'Q113461799', 'Gare de Toirac', 237878078, [['opening', '1886-07-14', 'day', 'Mise en service : 14 juillet 1886'], ['closure_passenger', '1980-09-28', 'day', 'Fermeture : 28 septembre 1980 (voyageurs)']]],
    ])),

    // Gares dont l'ouverture est annoncée pour plus tard (infobox : 2028, 2030, 2033) : projetées, jamais fermées.
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'wikidata' => $g[1]],
        'action' => 'kind',
        'kind' => 'projected',
        'source_url' => $wp($g[2], $g[3]),
        'quote' => $g[4],
        'reason' => 'gare pas encore ouverte : '.$g[4].' (infobox de l\'article de la gare)',
    ], [
        ['Labège-La Cadène', 'Q56309868', 'Gare de Labège-La Cadène', 237129357, 'Mise en service : 2028'],
        ['Bry - Villiers - Champigny', 'Q60176208', 'Gare de Villiers - Champigny - Bry', 239922586, 'Mise en service : 2030'],
        ['Sartrouville-Val Notre-Dame', 'Q3097682', 'Gare de Sartrouville-Val Notre-Dame', 231484737, 'Mise en service : 2033'],
    ]),

    // Gares d'Algérie (lignes d'Oran à Kenadsa et de Moulay Slissen à Saïda) entrées par Wikidata : hors du réseau décrit.
    [
        'station' => ['name' => 'Duveyrier', 'wikidata' => 'Q123498565'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Gare de Duveyrier', 239959848),
        'quote' => 'ancienne ligne à voie étroite d\'Oran à Kenadsa via Saïda',
        'reason' => 'gare d\'Algérie (ligne d\'Oran à Kenadsa), hors du réseau ferré de France',
    ],
    [
        'station' => ['name' => 'Saïda', 'wikidata' => 'Q123498604'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Gare de Saïda', 222099981),
        'quote' => 'lors de l\'inauguration de la ligne de Moulay Slissen à Saïda',
        'reason' => 'gare d\'Algérie (ligne de Moulay Slissen à Saïda), hors du réseau ferré de France',
    ],

    // Ligne 838000 Saint-Julien (Troyes) - Gray, suite : Châtillon-sur-Seine et Sainte-Colombe-sur-Seine.
    // Les deux articles datent la fin des voyageurs de mai 1980 (Sainte-Colombe : « fermée au service voyageurs depuis le
    // 31 mai 1980 », comme la section Troyes - Châtillon de l'article de la ligne). Châtillon est encore une gare de fret
    // (infobox : Fret SNCF, Fret Europorte Proximité ; « seuls des trains de marchandises circulent encore ») : le 1994
    // de Wikidata n'est pas sa fermeture.
    [
        'station' => ['name' => 'Ste-Colombe-sur-Seine', 'uic' => '87142562'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-05-31',
        'precision' => 'day',
        'source_url' => $wp('Gare de Sainte-Colombe-sur-Seine', 235034325),
        'quote' => 'La gare est fermée au service voyageurs depuis le 31 mai 1980.',
        'reason' => 'fin du service voyageurs de la gare (article de la gare ; même date pour la section Troyes - Châtillon dans l\'article de la ligne)',
    ],
    [
        'station' => ['name' => 'Châtillon-sur-Seine', 'uic' => '87142554'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-05-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Châtillon-sur-Seine', 241279211),
        'quote' => 'La gare de Châtillon-sur-Seine est fermée au trafic voyageurs en mai 1980.',
        'reason' => 'fin du service voyageurs en mai 1980 (article de la gare ; 31/05/1980 pour la section Troyes - Châtillon dans l\'article de la ligne)',
    ],
    [
        'station' => ['name' => 'Châtillon-sur-Seine', 'uic' => '87142554'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '1994-01-01'],
        'source_url' => $wp('Gare de Châtillon-sur-Seine', 241279211),
        'quote' => 'Seuls des trains de marchandises pour les coopératives agricoles circulent encore sur les voies de la gare.',
        'reason' => 'la gare reçoit toujours du fret (Europorte Proximité, gare-bois) : le 1994 de Wikidata n\'est pas sa fermeture',
    ],
    [
        'station' => ['name' => 'Châtillon-sur-Seine', 'uic' => '87142554'],
        'action' => 'current',
        'status' => 'no_commercial',
        'source_url' => $wp('Gare de Châtillon-sur-Seine', 241279211),
        'quote' => 'Seuls des trains de marchandises pour les coopératives agricoles circulent encore sur les voies de la gare.',
        'reason' => 'fermée aux voyageurs en 1980, encore desservie par le fret aujourd\'hui',
    ],
    // ── 20. Ligne 405000 Argentan - Granville : ouvertures par section et dates des gares fermées (audit-gares-suite, 22/11/2026).
    // L'article de la ligne (oldid 240174253) donne trois mises en service : Argentan - Flers le 02/07/1866, Flers - Vire le
    // 16/09/1867, Vire - Granville le 03/07/1870. Les gares fermées de la ligne portaient l'année 1866 de la première section
    // (déduite de la ligne), même au-delà de Flers et de Vire. L'article de Cerisy-Belle-Étoile confirme le 16/09/1867.
    // Les huit haltes fermées sans date de fin (Fromentel, Cerisy-Belle-Étoile, Montsecret-Vassy, Bernières-le-Patry,
    // Viessoix, Mesnil-Clinchamps, Saint-Aubin-des-Bois, Saint-Planchers) restent sans date de fermeture : aucun de leurs
    // articles ni celui de la ligne ne la donne, il faut une autre source (presse, indicateurs).
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'uic' => $g[1]],
        'action' => 'add',
        'type' => 'opening',
        'date' => $g[2],
        'precision' => 'day',
        'source_url' => $g[3] === 'Gare de Cerisi-Belle-Étoile' ? $wp('Gare de Cerisi-Belle-Étoile', 213901826) : $wp('Ligne d\'Argentan à Granville', 240174253),
        'quote' => $g[3] === 'Gare de Cerisi-Belle-Étoile'
            ? 'La gare ouvre en 1867 quand la ligne reliant la gare d\'Argentan et celle de Flers est prolongée jusqu\'à Vire.'
            : $g[4],
        'reason' => $g[3] === 'Gare de Cerisi-Belle-Étoile'
            ? 'gare de la section Flers - Vire, mise en service le 16/09/1867 (article de la gare et de la ligne) ; l\'année 1866 était celle de la première section'
            : 'gare de la section '.$g[5].', mise en service le '.implode('/', array_reverse(explode('-', $g[2]))).' (article de la ligne) ; l\'année 1866 était celle de la première section, pas de cette gare',
    ], [
        ['Cerisi-Belle-Étoile', '87448118', '1867-09-16', 'Gare de Cerisi-Belle-Étoile', '', 'Flers - Vire'],
        ['Montsecret-Vassy', '87448126', '1867-09-16', 'Ligne', '16 septembre 1867 : Flers à Vire', 'Flers - Vire'],
        ['Bernières-le-Patry', '87448134', '1867-09-16', 'Ligne', '16 septembre 1867 : Flers à Vire', 'Flers - Vire'],
        ['Viessoix', '87448142', '1867-09-16', 'Ligne', '16 septembre 1867 : Flers à Vire', 'Flers - Vire'],
        ['Mesnil-Clinchamps', '87447722', '1870-07-03', 'Ligne', '3 juillet 1870 : Vire à Granville', 'Vire - Granville'],
        ['St-Aubin-des-Bois', '87447706', '1870-07-03', 'Ligne', '3 juillet 1870 : Vire à Granville', 'Vire - Granville'],
        ['St-Planchers', '87447672', '1870-07-03', 'Ligne', '3 juillet 1870 : Vire à Granville', 'Vire - Granville'],
    ]),
    // Messei : plus aucun train de voyageurs depuis 1973, fret (petits colis) jusqu'en 1988 (article de la gare).
    [
        'station' => ['name' => 'Messei', 'uic' => '87448100'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1973-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Messei', 232257623),
        'quote' => 'Plus aucun train de voyageurs ne dessert cette gare depuis 1973',
        'reason' => 'fin du service voyageurs en 1973 (article de la gare)',
    ],
    [
        'station' => ['name' => 'Messei', 'uic' => '87448100'],
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1988-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Messei', 232257623),
        'quote' => 'le service des marchandises (notamment les petits colis) ayant quant à lui été assuré jusqu\'en 1988',
        'reason' => 'fin du fret en 1988 (article de la gare)',
    ],
    // Bellou-en-Houlme : halte sans bâtiment voyageurs, plus aucun train depuis 1970 ; le 31/12/2011 de Wikidata est la date
    // de sortie du référentiel SNCF des gares de voyageurs, pas une fermeture.
    [
        'station' => ['name' => 'Bellou-en-Houlme', 'uic' => '87448092'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1970-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Bellou-en-Houlme', 165650460),
        'quote' => 'Depuis 1970, aucun train ne s\'arrête plus à la halte de Bellou-en-Houlme.',
        'reason' => 'halte sans bâtiment voyageurs, arrêt supprimé en 1970 (article de la gare)',
    ],
    [
        'station' => ['name' => 'Bellou-en-Houlme', 'uic' => '87448092'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'closure', 'date' => '2011-12-31'],
        'source_url' => $wp('Gare de Bellou-en-Houlme', 165650460),
        'quote' => 'Depuis 1970, aucun train ne s\'arrête plus à la halte de Bellou-en-Houlme.',
        'reason' => 'le 31/12/2011 est la date de sortie du référentiel SNCF des gares de voyageurs, l\'arrêt est supprimé depuis 1970',
    ],

    // Ligne 633000 Cahors - Moissac : ligne jamais achevée (plateforme réceptionnée en 1932, travaux abandonnés en 1934,
    // déclassée le 30/11/1941 : article de la ligne, lignes-oubliées). Gares prévues, jamais ouvertes ; les trois autres gares
    // citées par la liste (Saint-Pantaléon, Miramont-Montesquieu, Castelsagrat) ne sont pas en base.
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], 'near' => [$g[1], $g[2], 0.3]],
        'action' => 'kind',
        'kind' => 'projected',
        'source_url' => $wp('Ligne de Cahors à Moissac', 226856040),
        'quote' => 'ligne de chemin de fer française qui ne fut jamais achevée, les travaux ayant été arrêtés en 1934',
        'reason' => 'gare prévue sur la ligne de Cahors à Moissac, jamais ouverte (travaux abandonnés en 1934, ligne déclassée le 30/11/1941)',
    ], [
        ['Montcuq', 1.1872, 44.3336], ['Lauzerte', 1.1298, 44.2591], ['Villesèque', 1.3224, 44.3860],
    ]),
    // ── 21. Fins des voyageurs et du fret lues dans le texte des articles de gare (audit-gares-suite, 22/11/2026).
    // Gares fermées ou « fret seul » sans date de fin de service, dont l'article donne une phrase explicite sur les voyageurs
    // ou les marchandises (l'infobox sans qualificatif, les phrases sur une ligne entière ou « avant 2011 » ne sont pas
    // retenues). Le texte des articles n'est pas lu par vff:import-station-passenger-ends (infobox seulement).
    ...array_merge(...array_map(function (array $g) use ($wp) {
        [$name, $qid, $title, $oldid, $events] = $g;
        $labels = ['closure_passenger' => 'fin du service voyageurs', 'closure_freight' => 'fin du trafic marchandises'];

        return array_map(fn (array $e) => [
            'station' => ['name' => $name, 'wikidata' => $qid],
            'action' => 'add',
            'type' => $e[0],
            'date' => $e[1],
            'precision' => $e[2],
            'source_url' => $wp($title, $oldid),
            'quote' => $e[3],
            'reason' => $labels[$e[0]].' de la gare, d\'après le texte de l\'article de la gare',
        ], $events);
    }, [
        ['Sézanne', 'Q3097814', 'Gare de Sézanne', 240934925, [['closure_passenger', '1972-01-01', 'year', 'La gare est fermée au trafic voyageurs depuis 1972']]],
        ['Héming', 'Q17355272', 'Gare de Héming', 238409807, [['closure_passenger', '1975-01-01', 'year', 'Au service d\'été 1975, plus aucun train de voyageurs ne s\'arrête en gare de Héming.']]],
        ['Pithiviers', 'Q3097274', 'Gare de Pithiviers', 239956556, [['closure_passenger', '1969-10-04', 'day', 'La fermeture du service voyageurs intervient le 4 octobre 1969']]],
        ['Wissous', 'Q3098083', 'Gare de Wissous', 236227929, [['closure_passenger', '1939-05-15', 'day', 'La gare est fermée au trafic des voyageurs avec l\'essentiel de la Grande Ceinture, le 15 mai 1939']]],
        ['Laluque', 'Q2524762', 'Gare de Laluque', 241007075, [['closure_passenger', '1950-04-27', 'day', 'Le 27 avril 1950, la gare est fermée aux voyageurs avec l\'ensemble de la ligne Tartas-Saint-Girons.']]],
        ['Ribeauvillé', 'Q3097407', 'Gare de Ribeauvillé', 240407982, [['closure_passenger', '2011-12-11', 'day', 'fermée au service des voyageurs en 2011 par la SNCF (infobox : 11 décembre 2011)']]],
        ['St-Cyr-Grande-Ceinture', 'Q3097507', 'Gare de Saint-Cyr-Grande-Ceinture', 219957684, [['closure_passenger', '1939-05-15', 'day', 'Le trafic des voyageurs prend fin le 15 mai 1939']]],
        ['St-Laurent-sur-Sèvre', 'Q28057996', 'Gare de Saint-Laurent-sur-Sèvre', 235034256, [['closure_passenger', '1939-01-01', 'year', 'La gare est fermée en 1939 au service des voyageurs'], ['closure_freight', '1992-01-01', 'year', 'et en 1992 pour le trafic de marchandises']]],
        ['St-Girons', 'Q3097535', 'Gare de Saint-Girons', 221735608, [['closure_passenger', '1969-01-01', 'year', 'fermée aux voyageurs en 1969 et aux marchandises en 1991'], ['closure_freight', '1991-01-01', 'year', 'fermée aux voyageurs en 1969 et aux marchandises en 1991']]],
        ['Auzits-Aussibals', 'Q114384695', 'Gare d\'Auzits-Aussibal', 238728008, [['closure_passenger', '2006-12-10', 'day', 'La gare est fermée au service des voyageurs le 10 décembre 2006.']]],
        ['Nuces', 'Q123498020', 'Gare de Nuces', 204906584, [['closure_passenger', '2006-10-30', 'day', 'Elle est fermée au service des voyageurs le 30 octobre 2006.']]],
        ['Allanche', 'Q1843663', 'Gare d\'Allanche', 229440543, [['closure_passenger', '1990-01-01', 'year', 'La SNCF a fermé le service voyageurs en 1990 et le trafic marchandise en 1991.'], ['closure_freight', '1991-01-01', 'year', 'La SNCF a fermé le service voyageurs en 1990 et le trafic marchandise en 1991.']]],
        ['Arreau', 'Q3095671', 'Gare d\'Arreau - Cadéac', 223819644, [['closure_passenger', '1969-01-01', 'year', 'Fermée au service des voyageurs en 1969 [...] jusqu\'à sa complète fermeture en 1971']]],
        ['Arlanc', 'Q30739934', 'Gare d\'Arlanc', 235030324, [['closure_passenger', '1980-09-28', 'day', '1980 (fermeture au trafic voyageur de la section Pont-de-Dore - Arlanc) ; infobox : 28 septembre 1980']]],
        ['Marcellaz-Hauteville', 'Q88308374', 'Gare de Marcellaz - Hauteville', 234117440, [['closure_passenger', '1993-01-01', 'year', 'Elle est fermée au trafic voyageurs en 1993.']]],
        ['Lovagny-Gorges-du-Fier', 'Q97366108', 'Gare de Lovagny-Gorges-du-Fier', 217416126, [['closure_passenger', '1993-01-01', 'year', 'Elle est fermée au trafic voyageurs en 1993.']]],
        ['Privas', 'Q54392628', 'Gare de Privas', 235033935, [['closure_passenger', '1938-12-01', 'month', 'La gare est fermée au service voyageurs en décembre 1938.']]],
        ['Axat (Aude)', 'Q81074887', 'Gare d\'Axat', 238872420, [['closure_passenger', '1939-04-18', 'day', 'La section entre Quillan et Rivesaltes a été fermée au trafic voyageurs le 18 avril 1939.']]],
        ['Maury', 'Q80795009', 'Gare de Maury', 228759382, [['closure_passenger', '1939-04-18', 'day', 'La section entre Quillan et Rivesaltes a été fermée au trafic voyageurs le 18 avril 1939.']]],
        ['Estagel', 'Q28710943', 'Gare d\'Estagel', 235030441, [['closure_passenger', '1939-04-18', 'day', 'La section entre Quillan et Rivesaltes a été fermée au trafic voyageurs le 18 avril 1939.']]],
        ['Chaumes', 'Q110656059', 'Gare de Chaumes', 240458718, [['closure_passenger', '1939-04-18', 'day', 'La gare de Chaumes-en-Brie fut fermée au trafic de voyageurs dès le 18 avril 1939 du fait de sa faible desserte.']]],
        ['Salelles-du-Bosc', 'Q18380045', 'Gare de Salelles-du-Bosc', 231200164, [['closure_passenger', '1939-05-15', 'day', 'cette dernière la ferme à son propre trafic voyageurs le 15 mai 1939 sur la section Paulhan - Lodève']]],
        ['La Forge del Mitg', 'Q83160711', 'Gare de La Forge del Mitg', 209217055, [['closure_passenger', '1937-06-01', 'day', 'fermée au service des voyageurs en 1937 par la CFPO (infobox : 1 juin 1937)']]],
        ['Saint-Laurent-de-Cerdans', 'Q83160923', 'Gare de Saint-Laurent-de-Cerdans', 232363855, [['closure_passenger', '1937-06-01', 'day', 'fermée au service des voyageurs en 1937 par la CFPO (infobox : 1 juin 1937)']]],
    ])),
    // ── 22. Ouvertures au jour lues dans l'article de la gare (audit-gares-suite, 22/11/2026) : données dans
    // database/data/gares_dates_articles.php (même format, une entrée par gare ; règles de sélection en tête de ce fichier).
    ...require __DIR__.'/gares_dates_articles.php',
    // Corrections du chantier « invariants-gares-nuit » (23/11/2026) : gares dont la date ne vient pas de l'entrée d'une
    // autre liste (Wikidata, OpenStreetMap, OpenHistoricalMap).
    ...require __DIR__.'/gares_dates_invariants.php',

    // ── Restes de la fiche gare (storage/app/reports/agents/aprem-2111/restes-fiches.md) : gares dont la date retenue
    // venait d'une autre ligne ou d'une décision administrative tardive.

    // Vigny (Moselle), ancienne gare de la ligne 099000 Metz-Ville - Château-Salins (PK 25,5 du schéma de l'article).
    // Placée au PK interpolé 2,838 (« entre Metz-Ville et Liocourt »), elle tombait dans la section Montigny-lès-Metz -
    // Augny dont la fermeture du 17/10/2023 lui était donnée ; elle est aussi rattachée à la LGV Est qui la survole (rattachement
    // de proximité). La section Metz - Delme est fermée aux voyageurs le 04/10/1953 et Delme - Augny au fret le 03/04/1972.
    [
        'station' => ['name' => 'Vigny', 'near' => [6.2523635, 48.9584031, 0.3]],
        'action' => 'pk',
        'line' => '099000',
        'pk_m' => 25500,
        'source_url' => $wp('Ligne de Metz-Ville à Château-Salins', 239700629),
        'quote' => '{{BS3ebis||exBHF|FLUG|25,5|[[Vigny (Moselle)|Vigny]]}}',
        'reason' => 'PK 25,5 du schéma de l\'article (le PK interpolé de 2,838 venait du placement par commune)',
    ],
    [
        'station' => ['name' => 'Vigny', 'near' => [6.2523635, 48.9584031, 0.3]],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Metz-Ville à Château-Salins', 239700629),
        'quote' => 'De Metz à Delme, le 4 octobre 1953.',
        'reason' => 'fermeture aux voyageurs de la section Metz - Delme, qui porte la gare (PK 25,5)',
    ],
    [
        'station' => ['name' => 'Vigny', 'near' => [6.2523635, 48.9584031, 0.3]],
        'action' => 'add',
        'type' => 'closure_freight',
        'date' => '1972-04-03',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Metz-Ville à Château-Salins', 239700629),
        'quote' => 'La section Delme - Augny a été fermée au trafic marchandises le 3 avril 1972.',
        'reason' => 'fermeture au fret de la section Delme - Augny, qui porte la gare (PK 25,5)',
    ],

    // Le Crouais (Ille-et-Vilaine), halte de la ligne Saint-Méen - Loudéac (schéma de l'article : halte fermée entre La
    // Brohinière et Saint-Méen). Elle recevait la fermeture 2018 de la ligne 472000 Ploërmel - La Brohinière, rattachée à
    // tort (PK interpolé 72,504). Une halte ne survit pas au service des voyageurs de sa ligne, fermé le 04/10/1953.
    [
        'station' => ['name' => 'Le Crouais', 'near' => [-2.1427191, 48.2105341, 0.3]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1953-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Méen à Loudéac', 240929645),
        'quote' => 'Elle est fermée au trafic voyageurs le 4 octobre 1953 et au trafic marchandises le 3 juillet 1967',
        'reason' => 'halte de la ligne Saint-Méen - Loudéac, fermée avec le service des voyageurs de la ligne le 04/10/1953 (la fermeture de 2018 est celle d\'une autre ligne)',
    ],

    // Villebois (Ain), terminus de la section Lagnieu - Villebois : fermée en décembre 1989 (Wikipédia), « vers 1989 »
    // (lignes-oubliees, fermeture aux marchandises). La date de 2006 est la décision administrative de RFF sur une section
    // sans trafic depuis 1989 (déclassée le 09/12/2002, voie déposée en 2013) ; aucun train touristique sur cette section.
    [
        'station' => ['name' => 'Villebois', 'uic' => '87743831'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1989-12-01',
        'precision' => 'month',
        'source_url' => $wp('Ligne d\'Ambérieu à Montalieu-Vercieu', 240595980),
        'quote' => 'La section Lagnieu - Villebois ferme à son tour en décembre 1989.',
        'reason' => 'fin du trafic de la section Lagnieu - Villebois en décembre 1989 (lignes-oubliees : fermeture aux marchandises vers 1989) ; la fermeture du 14/09/2006 est la décision administrative de RFF',
    ],
    // ── 23. Fins des voyageurs et du fret dites pour la gare elle-même (audit-gares-suite, 22/11/2026, deuxième passe sur
    // les articles de gare). Phrases explicites avec le jour, sur la gare ou « la ligne et la gare » ; les phrases sur une
    // section, une suspension de service ou une gare déjà corrigée plus haut ne sont pas reprises. Les 11 gares de
    // Pont-Saint-Vincent - Vittel (« fermée au service des voyageurs le 18 décembre 2016 du fait de la suspension de ce
    // service ») attendent la décision sur les lignes desservies par cars seuls.
    ...array_merge(...array_map(function (array $g) use ($wp) {
        [$name, $qid, $title, $oldid, $events] = $g;
        $labels = ['closure_passenger' => 'fin du service voyageurs', 'closure_freight' => 'fin du trafic marchandises'];

        return array_map(fn (array $e) => [
            'station' => ['name' => $name, 'wikidata' => $qid],
            'action' => 'add',
            'type' => $e[0],
            'date' => $e[1],
            'precision' => $e[2],
            'source_url' => $wp($title, $oldid),
            'quote' => $e[3],
            'reason' => $labels[$e[0]].' de la gare, d\'après le texte de l\'article de la gare',
        ], $events);
    }, [
        ['Layrac', 'Q18176985', 'Gare de Layrac', 237863700, [['closure_passenger', '1970-04-09', 'day', 'La ligne et la gare sont officiellement fermées au trafic voyageurs le 9 avril 1970.']]],
        ['Astaffort', 'Q18177281', 'Gare d\'Astaffort', 235030349, [['closure_passenger', '1970-04-09', 'day', 'La ligne et la gare sont officiellement fermées au trafic voyageurs le 9 avril 1970.']]],
        ['Goulens', 'Q18214837', 'Gare de Goulens', 237213766, [['closure_passenger', '1970-04-09', 'day', 'La ligne et la gare sont officiellement fermées au trafic voyageurs le 9 avril 1970.']]],
        ['Condom', 'Q3096277', 'Gare de Condom', 198994411, [['closure_passenger', '1940-06-27', 'day', 'La ligne et la gare sont fermées au trafic voyageurs le 27 juin 1940.']]],
        ['Berre', 'Q2310408', 'Gare de Berre', 200542195, [['closure_passenger', '2013-09-15', 'day', 'La gare est fermée au service des voyageurs le 15 septembre 2013.']]],
        ['Belleville-Villette', 'Q16507940', 'Gare de Belleville-Villette', 235030726, [['closure_passenger', '1934-07-23', 'day', 'Comme le reste de la Petite Ceinture, la gare est fermée au trafic voyageurs depuis le 23 juillet 1934.']]],
        ['Charonne', 'Q16507997', 'Gare de Charonne', 235031262, [['closure_passenger', '1934-07-23', 'day', 'Comme le reste de la Petite Ceinture, la gare est fermée au trafic voyageurs depuis le 23 juillet 1934.']]],
        ['Pont de Flandre', 'Q16508090', 'Gare du pont de Flandre', 241094309, [['closure_passenger', '1934-07-23', 'day', 'Comme le reste de la Petite Ceinture, la gare est fermée au trafic voyageurs depuis le 23 juillet 1934.']]],
        ['Courcelles-Ceinture', 'Q21427130', 'Gare de Courcelles-Ceinture', 202583562, [['closure_passenger', '1934-07-23', 'day', 'Comme le reste de la Petite Ceinture, la gare est fermée au trafic voyageurs depuis le 23 juillet 1934.']]],
        ['La rue Claude-Decaen', 'Q16507979', 'Gare de la rue Claude-Decaen', 241275518, [['closure_passenger', '1934-07-23', 'day', 'Comme le reste de la Petite Ceinture, la gare est fermée au trafic voyageurs depuis le 23 juillet 1934.']]],
        ['Bel-Air-Ceinture', 'Q16638560', 'Gare de Bel-Air-Ceinture', 241085798, [['closure_passenger', '1934-07-23', 'day', 'Comme le reste de la Petite Ceinture, la gare est fermée au trafic voyageurs depuis le 23 juillet 1934.']]],
        ['Grenelle-Ceinture', 'Q16508000', 'Gare de Grenelle-Ceinture', 235031977, [['closure_passenger', '1934-07-23', 'day', 'Comme le reste de la Petite Ceinture, la gare est fermée au trafic voyageurs depuis le 23 juillet 1934.']]],
        ['Blendecques', 'Q16507948', 'Gare de Blendecques', 238682912, [['closure_passenger', '1959-07-15', 'day', 'Comme la ligne, la gare est fermée au service des voyageurs le 15 juillet 1959.']]],
        ['Guitres', 'Q18285038', 'Gare de Guîtres', 239288271, [['closure_passenger', '1938-12-05', 'day', 'La ligne, et la gare, sont officiellement fermées au service des voyageurs le 5 décembre 1938.']]],
        ['Marcenais', 'Q18380039', 'Gare de Marcenais', 231414870, [['closure_passenger', '1938-12-05', 'day', 'Les deux lignes, et la gare, sont officiellement fermées au service des voyageurs le 5 décembre 1938.']]],
        ['St-Affrique', 'Q140783756', 'Gare de Saint-Affrique', 238467420, [['closure_passenger', '1938-12-05', 'day', 'La gare fut fermée au service des voyageurs, le 5 12 1938.']]],
        ['Châteauponsac', 'Q121677044', 'Gare de Châteauponsac', 235031330, [['closure_freight', '1987-04-30', 'day', 'La gare de Châteauponsac est ouverte au trafic le 23 décembre 1867, puis fermée aux voyageurs le 1 juillet 1939 et au trafic des marchandises le 30 avril 1987.'], ['closure_passenger', '1939-07-01', 'day', 'La gare de Châteauponsac est ouverte au trafic le 23 décembre 1867, puis fermée aux voyageurs le 1 juillet 1939 et au trafic des marchandises le 30 avril 1987.']]],
        ['Saint-Hippolyte-la-Vallée', 'Q21294371', 'Gare de Saint-Hippolyte-la-Vallée', 189058395, [['closure_passenger', '1971-09-26', 'day', 'La ligne, et la gare, seront fermées au service des voyageurs le 26 septembre 1971']]],
        ['Bourcefranc', 'Q21427331', 'Gare de Bourcefranc', 219026614, [['closure_passenger', '1971-09-26', 'day', 'La ligne, et la gare, seront fermées au service des voyageurs le 26 septembre 1971.']]],
        ['Le Chapus', 'Q21099771', 'Gare du Chapus', 239503182, [['closure_passenger', '1971-09-26', 'day', 'La ligne, et la gare, seront fermées au service des voyageurs le 26 septembre 1971 au dernier jour de l\'horaire d\'été.']]],
        ['Châtel-Guyon', 'Q18745199', 'Gare de Châtel-Guyon', 202031330, [['closure_passenger', '1971-09-26', 'day', 'La gare a été fermée au service voyageurs le 26 septembre 1971.']]],
        ['Espalion', 'Q3095748', 'Gare d\'Espalion', 229080210, [['closure_passenger', '1938-12-05', 'day', 'Mise en service le 25 juin 1908, la gare est fermée au trafic voyageurs le 5 décembre 1938 et le 1 juin 1987 pour le trafic marchandises.'], ['closure_freight', '1987-06-01', 'day', 'Mise en service le 25 juin 1908, la gare est fermée au trafic voyageurs le 5 décembre 1938 et le 1 juin 1987 pour le trafic marchandises.']]],
        ['St-Cergues-les-Voirons', 'Q3097501', 'Gare de Saint-Cergues-Les Voirons', 229485470, [['closure_freight', '2009-11-03', 'day', 'La gare est fermée au service des marchandises Fret SNCF le 3 novembre 2009.']]],
        ['Hondouville', 'Q113835969', 'Gare d\'Hondouville', 238868671, [['closure_freight', '1959-05-31', 'day', 'Le service marchandises est supprimé le 31 mai 1959 entre Brosville et Acquigny, ce qui entraîne la fermeture de la gare.']]],
    ])),

    // ── 24. Pont-Saint-Vincent - Vittel : fin du service voyageurs du 18/12/2016 (lignes-2027, 22/11/2026). Les
    // gares sont « fermées au service des voyageurs du fait de la suspension de ce service » ; aucun train voyageurs
    // n'y circule en 2027 : horaires GTFS SNCF lus le 19/11/2026 (jusqu'au 30/04/2027) sans aucun arrêt (service
    // « none », sauf Mirecourt, cars seuls) ; travaux de la concession Nancy - Contrexéville lancés le 17/12/2025
    // et menés « jusqu'à décembre 2027 », réouverture annoncée pour le 12/12/2027 (service 2028) ; l'Assemblée
    // nationale (QANR5L15QOSD39) parle d'une ligne « neutralisée depuis décembre 2016 ».
    ...array_map(function (array $g) use ($wp) {
        [$name, $qid, $title, $oldid, $kind] = $g;

        return [
            'station' => ['name' => $name, 'wikidata' => $qid],
            'action' => 'add',
            'type' => 'closure_passenger',
            'date' => '2016-12-18',
            'precision' => 'day',
            'source_url' => $wp($title, $oldid),
            'quote' => 'Le 18 décembre 2016, la '.$kind.' de '.$name.' est fermée au service des voyageurs du fait de la suspension de ce service, entre Pont-Saint-Vincent et Vittel.',
            'reason' => 'fin du service voyageurs de la gare, d\'après le texte de l\'article de la gare ; aucun train voyageurs en 2027 (travaux de la ligne Nancy - Contrexéville jusqu\'en décembre 2027, réouverture annoncée le 12/12/2027)',
        ];
    }, [
        ['Bainville-sur-Madon', 'Q3095895', 'Gare de Bainville', 240395209, 'gare'],
        ['Xeuilley', 'Q3098095', 'Gare de Xeuilley', 240648263, 'gare'],
        ['Pierreville', 'Q3097271', 'Gare de Pierreville', 235033782, 'gare'],
        ['Pulligny-Autrey', 'Q3097353', 'Gare de Pulligny - Autrey', 235033959, 'gare'],
        ['Ceintrey', 'Q3096152', 'Gare de Ceintrey', 235031196, 'gare'],
        ['Tantonville', 'Q3097834', 'Gare de Tantonville', 232001028, 'gare'],
        ['Vézelise', 'Q3098048', 'Gare de Vézelise', 240037324, 'gare'],
        ['Praye-sous-Vaudémont', 'Q3097343', 'Gare de Praye-sous-Vaudémont', 235033930, 'halte'],
        ['Diarville', 'Q3096361', 'Gare de Diarville', 240332572, 'gare'],
        ['Poussay', 'Q3097337', 'Gare de Poussay', 235033927, 'gare'],
        ['Mirecourt', 'Q2788266', 'Gare de Mirecourt', 235452660, 'gare'],
    ]),

    // ── 25. Dates d'ouverture de gares laissées en relecture, tranchées par une seconde source (gares-relecture-2, 22/11/2026,
    // rapport storage/app/reports/agents/aprem-2111/gares-relecture-2.md). Au mois ou à l'année quand les sources ne donnent pas le même jour.
    [
        'station' => ['name' => 'Brion-près-Thouet', 'wikidata' => 'Q3096069'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-10-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Brion-près-Thouet', 184079464),
        'quote' => 'La station de Brion est mise en service le 16 octobre 1882 par l\'Administration des chemins de fer de l\'État, lorsqu\'elle ouvre à l\'exploitation la ligne de Niort à Montreuil-Bellay.',
        'reason' => 'ouverture en octobre 1882, avec la ligne Niort - Montreuil-Bellay : l\'article donne trois jours selon les sources (le 16 d\'après le Journal des transports du 21/10/1882, le 22 d\'après l\'ingénieur de l\'État, le 23 d\'après Banaudo), seul le mois est commun ; remplace l\'année déduite de la ligne (1874)',
    ],
    [
        'station' => ['name' => 'L\'Orme-St-Pantaléon', 'wikidata' => 'Q114288117'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1916-11-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de L\'Orme-Saint-Pantaléon', 236929352),
        'quote' => 'Elle est ouverte en novembre 1916 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM), soit quasiment cinquante ans après l\'ouverture de la section reliant Autun à Épinac, sur laquelle elle est implantée.',
        'reason' => 'halte créée en 1916 (accord du conseil municipal de Saint-Pantaléon pour les ouvriers des schistes bitumineux d\'Autun), bien après la ligne de 1867 ; le jour (01/11/1916, infobox et article) n\'a qu\'une source, seul le mois est retenu ; remplace l\'année déduite de la ligne (1867)',
    ],
    [
        'station' => ['name' => 'Laroque-d\'Olmes', 'wikidata' => 'Q66281642'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1903-08-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Laroque-d\'Olmes', 220838895),
        'quote' => 'Mise en service : 25 août 1903',
        'reason' => 'gare de la section Chalabre - Lavelanet de la ligne de Moulin-Neuf à Lavelanet, ouverte en août 1903 (article de la ligne : le 25 août dans le corps du texte et l\'infobox de la gare, le 15 août dans l\'historique d\'après L\'Express du Midi du 15/08/1903) ; seul le mois est commun ; remplace l\'année 1902 déduite de la ligne',
    ],
    [
        'station' => ['name' => 'Lavelanet', 'wikidata' => 'Q109037573'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1903-08-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Lavelanet', 220838865),
        'quote' => 'Mise en service : 25 août 1903',
        'reason' => 'terminus de la section Chalabre - Lavelanet de la ligne de Moulin-Neuf à Lavelanet, ouverte en août 1903 (article de la ligne : le 25 août dans le corps du texte et l\'infobox de la gare, le 15 août dans l\'historique d\'après L\'Express du Midi du 15/08/1903) ; seul le mois est commun ; remplace l\'année 1902 déduite de la ligne',
    ],
    [
        'station' => ['name' => 'Vers', 'wikidata' => 'Q28040140'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1890-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Vers', 232264716),
        'quote' => 'Elle est mise en service en 1890, en tant qu\'arrêt, par la Compagnie des chemins de fer du Nord.',
        'reason' => 'arrêt créé en 1890 à la demande du conseil municipal de Vers, bien après la ligne de 1867 (infobox : 1er décembre 1890, d\'après les archives municipales de Vers-sur-Selle, source unique : seule l\'année est retenue) ; remplace l\'année déduite de la ligne (1867)',
    ],
    [
        'station' => ['name' => 'Chasteaux', 'wikidata' => 'Q28952372'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1891-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Chasteaux', 169851707),
        'quote' => 'La gare de Chasteaux est mise en service le 1er juillet 1891 par la compagnie du chemin de fer de Paris à Orléans, à l\'ouverture de la section entre Brive et Cahors.',
        'reason' => 'gare au PK 512,9, entre Noailles et Gignac - Cressensac : sur la section Brive - Souillac ouverte le 01/06/1891 (article de la ligne), pas sur Limoges - Brive du 01/07/1893 que l\'infobox de la gare recopie ; le jour de la gare (01/07/1891 avec « référence nécessaire ») est incertain, seule l\'année est retenue ; remplace l\'année déduite de la ligne (1847)',
    ],

    // ── 26. Audit des quais SNCF (quais-audit, 22/11/2026, rapport storage/app/reports/agents/aprem-2111/quais-audit.md) :
    // gares dont la fermeture n'avait qu'une borne (« range » : fermée à une date inconnue, ramenée au jour) alors que l'article
    // de la gare donne le jour, et fiche administrative de la liste des gares dont les quais sont ceux de Lyon-Perrache.
    [
        'station' => ['name' => 'Douzillac', 'wikidata' => 'Q3096398'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2017-07-02',
        'precision' => 'day',
        'source_url' => $wp('Gare de Douzillac', 235031673),
        'quote' => 'La gare a fermé le 2 juillet 2017.',
        'reason' => 'fin du service voyageurs de la halte de la ligne Coutras - Tulle, d\'après le texte de l\'article de la gare ; remplace la fermeture sans date (borne basse 1860)',
    ],
    [
        'station' => ['name' => 'Larche', 'wikidata' => 'Q3096856'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2020-12-13',
        'precision' => 'day',
        'source_url' => $wp('Gare de Larche', 235032565),
        'quote' => 'La gare de Larche a fermé ses portes le 13 décembre 2020 dans le cadre du plan du plan Optim’TER.',
        'reason' => 'fin des arrêts des trains TER de la ligne Coutras - Tulle (plan Optim\'TER), d\'après le texte de l\'article de la gare ; remplace la fermeture sans date (borne basse 1860)',
    ],
    // « Direction Régionale Rhône-Alpes » de liste-des-gares (UIC 87950022, ligne 830000, PK 510+600, ni voyageurs ni fret) :
    // un siège administratif, pas une gare. Ses quais du jeu « Liste des quais » sont ceux de Lyon-Perrache (mêmes libellés
    // « Quai B/C », « Quai 3/A », « Quai D/E », mêmes PK 510+655 à 511+113).
    [
        'station' => ['name' => 'Direction Régionale Rhône-Alpes', 'uic' => '87950022'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => 'https://ressources.data.sncf.com/explore/dataset/liste-des-quais/table/?q=lyon-perrache',
        'reason' => 'siège administratif de la SNCF placé au PK 510+600 de la ligne 830000 (ni voyageurs ni fret dans la liste des gares) : fiche erronée, ses quais sont ceux de Lyon-Perrache',
    ],

    // ── 27. Gares fermées à date inconnue dont l'audit des quais montre des quais de 2020 (gares-quais-2, 22/11/2026,
    // rapport storage/app/reports/agents/aprem-2111/gares-quais-2.md) : fins de desserte datées par les articles.
    [
        'station' => ['name' => 'Pont-du-Casse', 'wikidata' => 'Q1813789'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2020-12-13',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pont-du-Casse', 235033845),
        'quote' => 'Les trains ne desservent plus la gare depuis le 13 décembre 2020 faute de fréquentation.',
        'reason' => 'fin des arrêts des TER Périgueux - Agen au service du 13/12/2020, d\'après le texte de l\'article de la gare ; remplace la fermeture sans date (borne basse 1863)',
    ],
    [
        'station' => ['name' => 'Séon-St-Henry', 'wikidata' => 'Q3097816'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2019-01-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Séon-Saint-Henri', 235034637),
        'quote' => 'En janvier 2019, la gare est fermée au trafic voyageurs',
        'reason' => 'halte de Marseille plus desservie depuis janvier 2019 (article de la gare, qui cite L\'Express) ; seul le mois est connu ; remplace la fermeture sans date (borne basse 1848)',
    ],
    [
        'station' => ['name' => 'St-Martin-du-Vivier', 'wikidata' => 'Q3097577'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2019-08-25',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Martin-du-Vivier', 237268967),
        'quote' => 'Depuis le 25 août 2019, la gare n\'est plus desservie.',
        'reason' => 'fin des trains TER Rouen - Amiens à la halte le 25/08/2019 (l\'infobox donne le 24, le texte le 25 : le texte est retenu) ; remplace la fermeture sans date (borne basse 1867)',
    ],
    [
        'station' => ['name' => 'Noirétable', 'wikidata' => 'Q2257483'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '2016-05-29',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Clermont-Ferrand à Saint-Just-sur-Loire', 240660528),
        'quote' => 'a contraint SNCF Réseau à suspendre les circulations sur cette ligne le 29 mai 2016 entre Thiers et Boën-sur-Lignon',
        'reason' => 'circulations suspendues entre Thiers et Boën-sur-Lignon le 29/05/2016, trains remplacés par des cars (article de la ligne et presse : ici.fr, 30/05/2016, « Depuis ce dimanche, les circulations sont suspendues entre Thiers et Boën-sur-Lignon ») ; la gare n\'est plus desservie que par cars TER ; remplace la fermeture sans date (borne basse 1827)',
    ],

    // ── 28. Site technique sans service voyageurs (gares-horaires-archives, 22/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/gares-horaires-archives.md).
    // « Centre Henri Lang » (UIC fictif 87999999) : immeuble de SNCF Réseau, 16 rue Chrétien-de-Troyes (Paris 12e), qui abrite
    // le poste d'aiguillage et de régulation de la LGV Paris - Lyon ; aucun quai ni train de voyageurs.
    [
        'station' => ['name' => 'Centre Henri Lang', 'uic' => '87999999'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => 'https://www.ecologie.gouv.fr/presse/visite-francois-durovray-du-poste-daiguillage-regulation-ligne-grande-vitesse-lgv-sud-est',
        'reason' => 'siège technique de SNCF Réseau (poste d\'aiguillage et de régulation de la LGV Paris - Lyon), rendez-vous de visite « Centre Henri Lang, 16 rue Chrétien de Troyes, Paris 12e » (communiqué du ministère de la Transition écologique) ; deuxième source : Journées du patrimoine 2024 (OpenAgenda) ; l\'UIC 87999999 est fictif : pas une gare',
    ],
    // ── 29. Fermeture de l'ancienne gare de Sceaux (gares-dates-nuit, 23/11/2026). Wikidata P3999 et l'article de la gare
    // disent « 1894 » sans jour ; les archives municipales de Sceaux donnent le jour : la gare est désaffectée le 17/04/1894,
    // avec la transformation de la ligne de Sceaux (le prolongement de Bourg-la-Reine à Robinson est inauguré en mai 1893).
    [
        'station' => ['name' => 'Gare embarcadère de Sceaux', 'wikidata' => 'Q96605529'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1894-04-17',
        'precision' => 'day',
        'source_url' => 'https://archives.sceaux.fr/loctroi',
        'quote' => 'En 1883, le bureau se trouvait au 35 rue Houdan c\'est à dire dans les bâtiments de la gare de Sceaux. Elle est désaffectée définitivement le 17 avril 1894 avec transformation de la ligne de Sceaux.',
        'reason' => 'précise la fermeture « 1894 » de Wikidata et de l\'article Gare de Sceaux (ancienne) (oldid 241389426) ; la commune rachète les bâtiments en juin 1894 (même page)',
    ],
    // ── 30. Gares « actives » sans voie en service à moins de 150 m (gares-sans-voie, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/gares-sans-voie.md). Cas où la donnée de la gare est fausse ; les autres
    // (tracé de la ligne absent ou tronqué près de la gare) relèvent des tracés et sont listés dans le rapport.

    // Grenade, Cornebarrieu : haltes de la CFSO (Toulouse - Cadours) ; la position de Wikidata (4 décimales, point
    // décalé de 450 m vers le sud) n'est pas celle du bâtiment ; les coordonnées de l'article tombent à 10 et 11 m de la
    // voie de la ligne de Toulouse à Cadours aux ères 1910 à 1940 (orthophoto IGN : ancien bâtiment le long de la plateforme).
    [
        'station' => ['name' => 'Grenade', 'wikidata' => 'Q60847657'],
        'action' => 'position',
        'lng' => 1.29193,
        'lat' => 43.7691,
        'source_url' => $wp('Gare de Grenade (Haute-Garonne)', 188267795),
        'quote' => 'Situé sur une section déclassée et déferrée, le bâtiment est toujours présent et réaffecté.',
        'reason' => 'coordonnées de l\'article (43,7691 N, 1,29193 E) au bâtiment de la halte, à 11 m de la ligne de Toulouse à Cadours ; Wikidata (43,7651 N) est 450 m au sud, dans un lotissement',
    ],
    [
        'station' => ['name' => 'Cornebarrieu', 'wikidata' => 'Q56311192'],
        'action' => 'position',
        'lng' => 1.33263,
        'lat' => 43.65139,
        'source_url' => $wp('Gare de Cornebarrieu', 220839059),
        'quote' => 'Situé sur une section déclassée et déferrée, le bâtiment est toujours présent et réaffecté pour une utilisation privée.',
        'reason' => 'coordonnées de l\'article (43,65139 N, 1,33263 E) au bâtiment de la halte, à 10 m de la ligne de Toulouse à Cadours ; Wikidata (43,6475 N) est 440 m au sud, en forêt',
    ],
    // Hondeghem : gare de la ligne de Hazebrouck à Hondschoote ; Wikidata (50,7508 N) est 380 m au sud de l'article.
    [
        'station' => ['name' => 'Hondeghem', 'wikidata' => 'Q60846716'],
        'action' => 'position',
        'lng' => 2.523562,
        'lat' => 50.754231,
        'source_url' => $wp('Gare d\'Hondeghem', 235030477),
        'quote' => 'La gare d\'Hondeghem est une ancienne gare ferroviaire française de la ligne de de Hazebrouck à Hondschoote de la Compagnie des chemins de fer des Flandres (CF)',
        'reason' => 'coordonnées de l\'article (50,754231 N, 2,523562 E) à 11 m de la ligne de Hazebrouck à Hondschoote (ères 1900 à 1950), sur une maison au bord de la plateforme (orthophoto IGN) ; Wikidata (50,7508 N) est dans un champ, 380 m au sud',
    ],
    // Porte Maillot : la fiche réunit la gare de la ligne d'Auteuil (1854) et la station du RER E (1999) ; la position
    // retenue (médiane de sources éloignées de 380 m, puis recalée sur la voie du RER A à 171 m) n'est celle d'aucune des
    // deux. Les quais du RER C sont « entre les deux voies du boulevard Pereire, à l'est du boulevard Gouvion-Saint-Cyr »
    // (article) : position du nœud OSM de la gare, qui confirme celle de la liste SNCF (30 m) et tombe à 130 m de la ligne
    // d'Ermont-Eaubonne à Champ-de-Mars (VMI), seule voie de surface, aux ères 1900 à 1960.
    [
        'station' => ['name' => 'Porte Maillot', 'uic' => '87731448'],
        'action' => 'position',
        'lng' => 2.282378,
        'lat' => 48.878132,
        'source_url' => 'https://www.openstreetmap.org/node/13394835208',
        'quote' => 'Les quais de la ligne C du RER sont implantés entre les deux voies du boulevard Pereire, à l\'est du boulevard Gouvion-Saint-Cyr et du palais des Congrès.',
        'reason' => 'position de la gare (nœud OSM railway=station, quai du RER C ; Wikipédia, Gare de Neuilly - Porte Maillot, oldid 241410694) ; l\'ancienne position était entre la place et la porte, sans aucune gare',
    ],
    // Doullens : le service voyageurs de la gare est « finalement supprimé » en 1944 ; en 1960 elle n'a plus que des voies
    // de garage et des embranchements particuliers, la fermeture totale suit (1970, correction de la ligne de Saint-Roch à
    // Frévent). Aux ères 1950 et 1960 : gare fret seul, pas « active ».
    [
        'station' => ['name' => 'Doullens', 'wikidata' => 'Q22248350'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1944-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Doullens', 232235487),
        'quote' => 'rétabli pendant la Seconde Guerre mondiale (du 1er septembre 1940 au 5 mai 1941 vers Canaples et Amiens ainsi qu\'Arras, puis de cette date à 1944 vers Frévent), pour être finalement supprimé.',
        'reason' => 'fin du service voyageurs en 1944 ; en 1960 la gare dispose de voies de garage et dessert plusieurs embranchements particuliers (même article)',
    ],
    // Gimouille : terminus d'un embranchement de 2 km de Saincaize, ouvert « uniquement à la petite vitesse
    // (marchandises) » : jamais de voyageurs (comme Lille-Saint-Sauveur).
    [
        'station' => ['name' => 'Gimouille', 'wikidata' => 'Q115867420'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1868-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Gimouille', 239346753),
        'quote' => 'Ouverte uniquement à la petite vitesse (marchandises), la gare ferroviaire est établie en parallèle au canal.',
        'reason' => 'gare aux marchandises dès son ouverture le 1er mai 1868, sans service voyageurs ; fermée en 1980 (même article)',
    ],
    // La Rapée : « ancienne gare française aux marchandises », construite en 1862 pour desservir les entrepôts de Bercy.
    [
        'station' => ['name' => 'La Rapée', 'wikidata' => 'Q22248480'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1862-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de La Rapée', 235032490),
        'quote' => 'La gare de La Rapée est une ancienne gare française aux marchandises, désaffectée, de la ligne de Paris-Lyon à Marseille-Saint-Charles à Paris, en France.',
        'reason' => 'gare aux marchandises dès sa construction en 1862 (stockage et desserte des entrepôts de vins de Bercy) : jamais de voyageurs ; à ne pas confondre avec La Rapée-Bercy de la Petite Ceinture',
    ],
    // ── 31. Tracé de l'ère arrêté avant la gare ou passant à côté (traces-tronques, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/traces-tronques.md). La voie est juste ; c'est la date ou la position de la
    // gare qui la faisait « active » avant sa section, ou qui écartait sa position du découpage des sections.

    // Chaville - Vélizy : gare de la section Meudon-Val-Fleury - Viroflay-Rive-Gauche de la ligne des Invalides, mise en
    // service le 31/05/1902. L'ouverture de 1840 était déduite de l'ouverture « toute la ligne » (Paris - Versailles par la
    // rive gauche, dont la gare de Chaville-Rive-Gauche) : 7 ères 1840 à 1900 sans voie à moins de 250 m.
    [
        'station' => ['name' => 'Chaville-Vélizy', 'uic' => '87393173'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1902-05-31',
        'precision' => 'day',
        'source_url' => $wp('Ligne des Invalides à Versailles-Rive-Gauche', 240261607),
        'quote' => 'mise en service du tronçon de Meudon-Val Fleury à Viroflay-Rive-Gauche et desserte complète de la ligne des Invalides à Versailles-Rive-Gauche',
        'reason' => 'la gare est au PK 13,366 de la ligne des Invalides à Versailles-Rive-Gauche, entre Viroflay-Rive-Gauche et Meudon-Val-Fleury (Gare de Chaville - Vélizy, oldid 232787715) : elle ouvre avec ce tronçon, pas avec la ligne de 1840',
    ],
    // La Grave-d'Ambarès (gare inférieure) : halte de la ligne de Chartres à Bordeaux, entre Cubzac-les-Ponts et
    // Sainte-Eulalie - Carbon-Blanc, sur la section de Cavignac à Bordeaux ouverte en 1886 (correction de la ligne dans
    // line_event_corrections_traces_tronques.php). L'ouverture de 1874 était celle de la ligne entière (Wikidata).
    [
        'station' => ['name' => 'La Grave-d\'Ambarès-Gare-Inférieure', 'uic' => '87491274'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1886-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Saint-Mariens - Saint-Yzan', 239281710),
        'quote' => 'La connexion directe avec la capitale régionale est devenue possible en 1886 lors de la construction d\'un pont franchissant la Dordogne sur la commune de Cubzac-les-Ponts.',
        'reason' => 'la halte (PK 598,189, entre Cubzac-les-Ponts et Sainte-Eulalie - Carbon-Blanc, Gare de la Grave-d\'Ambarès oldid 223772792) ne peut pas précéder sa section, ouverte en 1886 ; date de la section, la halte peut être plus récente',
    ],
    // Moulot : gare de la section Clamecy - Cosne (PK 231,6, Clamecy au PK 226,686) de la ligne de Laroche-Migennes à
    // Cosne, mise en service le 01/06/1893 et fermée aux voyageurs le 15/05/1938 (date de fermeture de la gare).
    // L'ouverture de 1855 était celle de la première section (Laroche-Migennes - Auxerre).
    [
        'station' => ['name' => 'Moulot', 'near' => [3.472667, 47.453692, 0.5]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1893-06-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Laroche-Migennes à Cosne', 235261261),
        'quote' => '1er juin 1893, mise en service de Clamecy à Cosne, avec une double voie ; 15 mai 1938, fermeture du service voyageurs de Clamecy à Cosne',
        'reason' => 'gare de la section Clamecy - Cosne (PK de lignes-oubliées), ouverte avec elle ; sa fermeture du 15/05/1938 est celle de cette section',
    ],
    // Massy - Palaiseau : la fiche est la gare de la Grande Ceinture (position : quais du RER C), ouverte le 01/05/1883 « à
    // mi-chemin des gares de Massy (actuellement Massy - Verrières) et de Palaiseau » ; la gare de 1854 de la ligne de
    // Sceaux est Massy - Verrières. Les ères 1860 et 1870 n'avaient aucune voie à moins de 200 m.
    [
        'station' => ['name' => 'Massy-Palaiseau', 'uic' => '87393579'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Massy - Palaiseau', 240526522),
        'quote' => 'Le 1er mai 1883, le trafic de la ligne de Grande Ceinture ouvre aux voyageurs sur la section de Versailles-Chantiers à Savigny-sur-Orge. Pour créer une correspondance avec la gare de la ligne de Sceaux, une nouvelle station est ouverte aux voyageurs et aux marchandises. Située à mi-chemin des gares de Massy (actuellement Massy - Verrières) et de Palaiseau, dans une plaine encore vierge de toute habitation, elle prend le nom de Massy - Palaiseau.',
        'reason' => 'ouverture de la gare de la Grande Ceinture, que la fiche désigne (position des quais du RER C)',
    ],
    [
        'station' => ['name' => 'Massy-Palaiseau', 'uic' => '87393579'],
        'action' => 'neutralize',
        'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '1854-01-01'],
        'source_url' => $wp('Gare de Massy - Palaiseau', 240526522),
        'reason' => '1854 est l\'ouverture du prolongement de la ligne de Sceaux jusqu\'à Orsay et de la gare de Massy, « actuellement Massy - Verrières » (même article), pas celle de Massy - Palaiseau',
    ],
    // Corte et Rennes : position juste (nœud OSM sur le bâtiment voyageurs, orthophoto IGN, rapport gares-sans-voie) mais
    // signalée (écart des sources de 143 et 206 m, dû à la position du référentiel SNCF). Une gare signalée est écartée du
    // géocodage des portions (vff:resolve-event-sections) : les sections « Bastia - Corte » et « Rennes - Brest » s'arrêtaient
    // au point SNCF, 180 et 118 m plus loin, et la gare tombait dans la section suivante, ouverte plus tard (1894, 1863).
    // La position, sourcée, n'est plus signalée.
    [
        'station' => ['name' => 'Corte', 'uic' => '87762013'],
        'action' => 'position',
        'lng' => 9.158370,
        'lat' => 42.303835,
        'source_url' => 'https://www.openstreetmap.org/node/270887749',
        'quote' => 'Gare de Corte (nœud OSM railway=station, à 9 m du bâtiment voyageurs OSM ; Wikidata à 26 m)',
        'reason' => 'position OSM sur la gare (orthophoto IGN) ; le point du référentiel SNCF est 180 m au nord, sur la voie de sortie vers Bastia',
    ],
    [
        'station' => ['name' => 'Rennes', 'uic' => '87471003'],
        'action' => 'position',
        'lng' => -1.672381,
        'lat' => 48.103193,
        'source_url' => 'https://www.openstreetmap.org/node/4250849558',
        'quote' => 'Gare de Rennes (nœud OSM railway=station, à 15 m de la gare OSM ; Wikidata à 18 m)',
        'reason' => 'position OSM sur la halle voyageurs (orthophoto IGN) ; le point du référentiel SNCF est 118 m à l\'est',
    ],

    // ── 32. Positions de Wikidata fausses, relevées dans les sources ouvertes (erreurs-sources, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/erreurs-sources.md, E1 et E2). La position retenue était déjà bonne (liste SNCF,
    // OSM) ; la position de l'article, d'accord avec elle, supprime l'écart de 8 km et de 1,1 km qui levait le drapeau
    // « à vérifier » (position_spread_m).

    // Rosendaël : Wikidata Q3049345 est à 51,0705 N, 2,51782 E, près de la frontière belge, 8,0 km de l'article ; la liste
    // SNCF (UIC 87281600) donne 51,04251 N, 2,41126 E, à 90 m de l'article.
    [
        'station' => ['name' => 'Rosendael', 'wikidata' => 'Q3049345'],
        'action' => 'position',
        'lng' => 2.411732,
        'lat' => 51.043349,
        'source_url' => $wp('Gare de Rosendaël', 220965878),
        'quote' => 'Gare de Rosendaël (coordonnées de l\'article : 51,043349 N, 2,411732 E)',
        'reason' => 'coordonnées de l\'article, à 90 m de la liste des gares SNCF (51,04251 N, 2,41126 E, UIC 87281600) ; Wikidata (51,0705 N, 2,51782 E) est à 8 km, près de la frontière belge',
    ],
    // Avenue de Saint-Ouen : Wikidata Q16507976 reprend les coordonnées de Q16508091 (gare du boulevard Ornano), 1,15 km à l'est ;
    // l'objet OSM w77670163 (ancien bâtiment) est à 48,8957 N, 2,3287 E.
    [
        'station' => ['name' => 'Avenue de Saint-Ouen', 'wikidata' => 'Q16507976'],
        'action' => 'position',
        'lng' => 2.32866,
        'lat' => 48.89575,
        'source_url' => $wp('Gare de l\'avenue de Saint-Ouen', 228129603),
        'quote' => 'Gare de l\'avenue de Saint-Ouen (coordonnées de l\'article : 48,89575 N, 2,32866 E)',
        'reason' => 'coordonnées de l\'article, à 10 m de l\'objet OSM w77670163 ; Wikidata (48,8976 N, 2,3441 E) recopie celles de la gare du boulevard Ornano, 1,15 km plus loin',
    ],
    // Les Lacs (Ille-et-Vilaine) : Wikidata P1619 = 1857 est faux ; la halte est créée à la suite de l'accident du 13/07/1878.
    [
        'station' => ['name' => 'Les Lacs', 'wikidata' => 'Q2213900'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1878-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare des Lacs', 229513869),
        'quote' => 'la halte est créée à la suite de l\'accident du 13 juillet 1878',
        'reason' => 'halte créée après l\'accident du 13/07/1878 (source de l\'article : mairie de Saint-Aubin-des-Landes) ; la date de 1857 de Wikidata est fausse',
    ],

    // Gares datées par une phrase de portion mal lue (chantier « datation-gares », 23/11/2026). Barsanges et Ambrugeat sont entre
    // Viam et Meymac (PK 468329 et 473150 de la ligne 713000), sur la section Eymoutiers - Meymac, dont l'événement reste non
    // résolu : sans date propre, elles prenaient l'ouverture de la ligne entière (1880) ou la phrase « tunnel de La Saulière
    // supprimé en 1964 », lue à tort comme une ouverture (neutralisée dans line_event_corrections_sourcees.php).
    [
        'station' => ['name' => 'Barsanges', 'uic' => '87592188'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-10-08',
        'precision' => 'day',
        'source_url' => $wp('Ligne du Palais à Eygurande - Merlines', 238876015),
        'quote' => 'd\'Eymoutiers à Meymac : le 8 octobre 1883',
        'reason' => 'ouverture de la section Eymoutiers - Meymac, où se trouve la gare (PK 468329, entre Viam et Meymac)',
    ],
    [
        'station' => ['name' => 'Ambrugeat', 'uic' => '87592196'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-10-08',
        'precision' => 'day',
        'source_url' => $wp('Ligne du Palais à Eygurande - Merlines', 238876015),
        'quote' => 'd\'Eymoutiers à Meymac : le 8 octobre 1883',
        'reason' => 'ouverture de la section Eymoutiers - Meymac, où se trouve la gare (PK 473150, entre Viam et Meymac)',
    ],

    // ── 33. Dates d'ouverture douteuses relevées avant l'application des ères de gares (verif-gares, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/verif-gares.md). Une gare prenait la date d'une section ou d'une mise en
    // exploitation qui n'est pas celle de son ouverture au service public.

    // Auger-Saint-Vincent : le 01/09/1870, la ligne est mise en exploitation par les troupes d'occupation pour des convois
    // militaires exclusivement ; l'exploitation régulière pour le public commence le 01/07/1871, avec Senlis et Barbery
    // seulement, les autres arrêts étant ouverts « bientôt après ». Le jour exact de l'arrêt n'est pas trouvé : année 1871.
    [
        'station' => ['name' => 'Auger-St-Vincent', 'uic' => '87272187'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1871-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Chantilly - Gouvieux à Crépy-en-Valois', 237300162),
        'quote' => 'L\'exploitation régulière à destination du public commence le 1er juillet 1871',
        'reason' => 'le 01/09/1870 est la mise en exploitation par les troupes d\'occupation pour des convois militaires seulement ; le service public commence le 01/07/1871 (Senlis et Barbery d\'abord, les autres arrêts bientôt après), jour de l\'arrêt non trouvé',
    ],
    // Saignon : gare du prolongement d'Apt à Saint-Maime - Dauphin (PK 37, Apt au PK 32), mis en service le 25/10/1890. L'ouverture
    // de 1877 est celle de la section Cavaillon - Apt, qui ne va pas jusqu'à Saignon.
    [
        'station' => ['name' => 'Saignon', 'near' => [5.44702, 43.87668, 0.5]],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1890-10-25',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Cavaillon à Saint-Maime - Dauphin', 241491046),
        'quote' => 'La ligne et l\'embranchement, tous deux à voie unique, sont mis en service le 25 octobre 1890.',
        'reason' => 'gare de la section Apt - Saint-Maime - Dauphin (déclassement d\'Apt à Saignon : PK 32,400 à 37,353), mise en service le 25/10/1890 ; la date de 1877 est celle de Cavaillon - Apt',
    ],

    // ── 34. Fermetures au service des voyageurs relevées avant l'application des ères de gares (corrections-matin,
    // 23/11/2026, rapport storage/app/reports/agents/aprem-2111/corrections-matin.md).

    // Auger-Saint-Vincent : le 15/05/1939, la SNCF supprime les trains de voyageurs de Senlis à Crépy-en-Valois, mais le service
    // reprend fin septembre 1939 (puis cesse de nouveau en juillet 1940 et revient après la guerre : trois allers-retours, autorails
    // légers depuis le 15/11/1946). La suppression définitive est celle du service d'été 1949, que l'article de la commune de la gare
    // confirme (« fermée définitivement au service voyageurs en été 1949 »). Jour exact du service d'été non trouvé : année 1949.
    [
        'station' => ['name' => 'Auger-St-Vincent', 'uic' => '87272187'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1949-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Chantilly - Gouvieux à Crépy-en-Valois', 237300162),
        'quote' => 'l\'entrée en vigueur du service d\'été 1949, qui voit la suppression définitive des trains de voyageurs entre Senlis et Crépy-en-Valois',
        'reason' => 'le 15/05/1939 est une suppression temporaire (service repris fin septembre 1939, puis de nouveau supprimé en juillet 1940 et rétabli après la guerre) ; la fermeture définitive aux voyageurs date du service d\'été 1949 (article de la ligne ; article de la commune, oldid 241056468 : « fermée définitivement au service voyageurs en été 1949 »), jour non trouvé',
    ],

    // Saignon : le service des voyageurs s'arrête le 15/01/1934 (décision du 23/11/2026, arrêt des trains) ; la fermeture officielle de la
    // ligne aux voyageurs, « rendue définitive » le 02/10/1938, est un fait distinct, porté par la note de cet événement. Un second
    // événement de fermeture aux voyageurs, source « correction », aurait pris la place du premier (une seule fermeture par période) :
    // la date de fin de service ne change donc pas.
    [
        'station' => ['name' => 'Saignon', 'near' => [5.44702, 43.87668, 0.5]],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1934-01-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Forcalquier', 222987396),
        'quote' => 'fermée au trafic voyageurs depuis 1934',
        'reason' => 'arrêt des trains de voyageurs le 15/01/1934 (articles de la gare de Forcalquier, oldid 222987396, et de la ligne de Forcalquier à Volx, oldid 240748405) ; fermeture officielle aux voyageurs de la ligne le 02/10/1938 (article de la ligne de Cavaillon à Saint-Maime - Dauphin, oldid 241491046 : « L\'ensemble est fermé au trafic voyageurs le 2 octobre 1938 » ; article de Forcalquier : fermeture « rendue définitive » le 2 octobre 1938)',
    ],

    // ── 35. Gares fréquentées en 2020 absentes de l'ère 2020 (gares-2020, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/gares-2020.md).

    // Chilly-Mazarin et Gravigny-Balizy : Wikidata (P1619) donne le 10/12/2023, mise en service du tram-train T12 qui y remplace
    // la branche C8 du RER C. Les deux gares sont créées avec l'ouverture de la Grande Ceinture aux voyageurs, le 01/05/1883 :
    // avec la date de 2023, elles manquaient à toutes les ères historiques (1 027 095 et 489 424 voyageurs SNCF en 2019).
    ...array_merge(...array_map(fn (array $g) => [
        [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'neutralize',
            'match' => ['source' => 'wikidata', 'type' => 'opening', 'date' => '2023-12-10'],
            'source_url' => 'https://www.wikidata.org/w/index.php?title='.$g[2].'&oldid='.$g[3],
            'reason' => 'P1619 du 10/12/2023 : mise en service du tram-train T12 dans une gare de 1883, pas l\'ouverture de la gare',
        ],
        [
            'station' => ['name' => $g[0], 'uic' => $g[1]],
            'action' => 'add',
            'type' => 'opening',
            'date' => '1883-05-01',
            'precision' => 'day',
            'source_url' => $wp('Gare de '.$g[0], $g[4]),
            'quote' => 'Le 1er mai 1883, le trafic de la ligne de Grande Ceinture ouvre aux voyageurs [...] La gare de '.$g[0].' est créée à cette occasion.',
            'reason' => 'gare créée à l\'ouverture de la Grande Ceinture aux voyageurs ; desservie par le RER C puis, depuis le 09/12/2023, par le tram-train T12',
        ],
    ], [
        ['Chilly-Mazarin', '87393637', 'Q2330216', 2570057942, 241589862],
        ['Gravigny-Balizy', '87393645', 'Q2093838', 2546291953, 241589768],
    ])),
    // Dugny - La Courneuve : gare de la Tangentielle Nord (tram-train T11) sans aucune date, donc absente de toutes les ères
    // historiques. L'ancienne gare de la Grande Ceinture, fermée le 15/05/1939, n'est pas cette fiche (position du T11).
    [
        'station' => ['name' => 'Dugny-La Courneuve TT', 'uic' => '87697342'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2017-07-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Dugny - La Courneuve', 236142240),
        'quote' => 'ouverte en même temps que le premier tronçon de la ligne 11 du tramway d\'Île-de-France, le 1er juillet 2017',
        'reason' => 'gare du tram-train T11 Express (Tangentielle Nord), ouverte avec la ligne le 01/07/2017',
    ],
    // ── 36. Ouvertures des gares encore ouvertes datées à l'année par Wikidata ou déduites de la ligne, relues contre l'article
    // de la gare (révision citée) et, quand elle existe, la chronologie de l'article de la ligne (audit-gares-jour, 23/11/2026,
    // rapport storage/app/reports/agents/aprem-2111/audit-gares-jour.md). Une section de ligne ou la ligne entière donnait
    // l'année ; la gare ouvre le jour de sa propre section, ou plus tard (halte créée après la ligne).

    // Mont-de-Marsan (id 17275)
    [
        'station' => ['name' => 'Mont-de-Marsan', 'uic' => '87671016'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-09-06',
        'precision' => 'day',
        'source_url' => $wp('Gare de Mont-de-Marsan', 241330823),
        'quote' => 'Le service voyageurs et marchandises ouvre le dimanche 6 septembre 1857, sans cérémonie ni festivité.',
        'reason' => 'article de la gare (oldid 241330823) ; article de la ligne « Ligne de Morcenx à Bagnères-de-Bigorre » (oldid 240485101) : « celle de Saint-Martin-d\'Oney à Mont-de-Marsan, le 6 septembre 1857 ». Remplace année 1858 de Wikidata.',
    ],
    // Albigny-Neuville (id 17631)
    [
        'station' => ['name' => 'Albigny-Neuville', 'uic' => '87721266'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-07-10',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Albigny - Neuville', 236814785),
        'quote' => 'Le 10 juillet 1854 la ligne, à double voie, est mise en service.',
        'reason' => 'article de la gare (oldid 236814785) ; article de la ligne « Ligne de Paris-Lyon à Marseille-Saint-Charles » (oldid 241214704) : « La section complémentaire, reliant Chalon-sur-Saône à Lyon-Vaise, ouvrit trois ans plus tard, le 10 juillet 1854 ». Remplace année 1855 de Wikidata.',
    ],
    // Livron (id 18093)
    [
        'station' => ['name' => 'Livron', 'uic' => '87761247'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-06-29',
        'precision' => 'day',
        'source_url' => $wp('Gare de Livron', 230261924),
        'quote' => '| mise en service = 29 juin 1854',
        'reason' => 'article de la gare (oldid 230261924) ; article de la ligne « Ligne de Paris-Lyon à Marseille-Saint-Charles » (oldid 241214704) : « le 29 juin 1854 d\'Avignon à Orange et d\'Orange à Valence ». Remplace année 1855 de Wikidata.',
    ],
    // Caen (id 16258)
    [
        'station' => ['name' => 'Caen', 'uic' => '87444000'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-09-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Caen', 239088037),
        'quote' => 'infobox, mise en service : 1 septembre 1857',
        'reason' => 'article de la gare (oldid 239088037) ; article de la ligne « Ligne de Mantes-la-Jolie à Cherbourg » (oldid 241344631) : « 1 septembre 1857 : ouverture de Mondeville à Caen ». Remplace année 1858 de Wikidata.',
    ],
    // Embrun (id 18116)
    [
        'station' => ['name' => 'Embrun', 'uic' => '87763466'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-07-10',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Embrun', 228554323),
        'quote' => 'La station d\'Embrun est mise en service le 10 juillet 1883 par la Compagnie PLM, lorsqu\'elle ouvre à l\'exploitation la section de Gap à Mont-Dauphin de sa ligne de Gap à Briançon.',
        'reason' => 'article de la gare (oldid 228554323) ; article de la ligne « Ligne de Veynes à Briançon » (oldid 240049469) : « Gap - Embrun : 10 juillet 1883 (double voie de Gap à Chorges et voie unique de Chorges à Embrun) ». Remplace année 1884 de Wikidata.',
    ],
    // Fécamp (id 16227)
    [
        'station' => ['name' => 'Fécamp', 'uic' => '87413542'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-02-25',
        'precision' => 'day',
        'source_url' => $wp('Gare de Fécamp', 241505364),
        'quote' => 'La première gare de Fécamp est mise en service le 25 février 1856 par la Compagnie des chemins de fer de l\'Ouest, lorsqu\'elle ouvre à l\'exploitation une ligne de {{Unité|19|km}} de Beuzeville à Fécamp.',
        'reason' => 'article de la gare (oldid 241505364) ; article de la ligne « Ligne de Bréauté - Beuzeville à Fécamp » (oldid 240961943) : « 25 février 1856 : mise en service de Beuzeville à Fécamp (gare provisoire) ». Remplace année 1858 de Wikidata (la gare définitive ; la première gare ouvre en 1856).',
    ],
    // Pringy (id 17910)
    [
        'station' => ['name' => 'Pringy', 'uic' => '87746206'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-06-05',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pringy', 240867816),
        'quote' => 'La gare est mise en service le 5 juin 1884 par le PLM, en même temps que le tronçon Annecy-La Roche-sur-Foron de la ligne d\'Aix-les-Bains-Le Revard à Annemasse.',
        'reason' => 'article de la gare (oldid 240867816) ; article de la ligne « Ligne d\'Aix-les-Bains-Le Revard à Annemasse » (oldid 240496723) : « d\'Annecy à La Roche-sur-Foron le 5 juin 1884 ». Remplace année 1866 de Wikidata (section Aix - Annecy, au sud de Pringy).',
    ],
    // Luxeuil-les-Bains (id 15263)
    [
        'station' => ['name' => 'Luxeuil-les-Bains', 'uic' => '87185595'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1878-04-25',
        'precision' => 'day',
        'source_url' => $wp('Gare de Luxeuil-les-Bains', 241217945),
        'quote' => 'La section d\'Aillevillers à Lure {{référence souhaitée|comprenant la gare de Luxeuil-les-Bains}} est mise en service le 25 avril 1878 par la Compagnie des chemins de fer de l\'Est.',
        'reason' => 'article de la gare (oldid 241217945) ; article de la ligne « Ligne de Blainville - Damelevières à Lure » (oldid 240009224) : « d\'Aillevillers à Lure, le 25 avril 1878 ». Remplace année 1858 de Wikidata (autre section de la ligne).',
    ],
    // Franois (id 17601)
    [
        'station' => ['name' => 'Franois', 'uic' => '87718304'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-04-07',
        'precision' => 'day',
        'source_url' => $wp('Gare de Franois', 229489837),
        'quote' => 'La gare de Franois est mise en service le 7 avril 1856 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM), lorsqu\'elle ouvre la deuxième section de Dole à Besançon de sa ligne de Dijon à Belfort.',
        'reason' => 'article de la gare (oldid 229489837) ; article de la ligne « Ligne de Dole-Ville à Belfort » (oldid 241427688) : « La ligne a été ouverte en deux étapes : le 7 avril 1856 ». Remplace année 1854 de Wikidata.',
    ],
    // Mignaloux-Nouaillé (id 16800)
    [
        'station' => ['name' => 'Mignaloux-Nouaillé', 'uic' => '87575704'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-06-18',
        'precision' => 'day',
        'source_url' => $wp('Gare de Mignaloux - Nouaillé', 230642279),
        'quote' => '| mise en service = 18 juin 1883',
        'reason' => 'article de la gare (oldid 230642279) ; article de la ligne « Ligne de Saint-Benoît au Blanc » (oldid 234965434) : « 18 juin 1883, ouverture par l\'État de la section de Mignaloux - Nouaillé ». Remplace année 1880 de Wikidata.',
    ],
    // Albi-Madeleine (id 17201)
    [
        'station' => ['name' => 'Albi-Madeleine', 'uic' => '87615195'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1858-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Albi-Madeleine', 241142530),
        'quote' => 'L\'embarcadère d\'Albi-Madeleine est mis en service officiellement le 1 mai 1858 par la Compagnie des houillères et chemins de fer de Carmaux-Toulouse lorsqu\'elle ouvre le service régulier, voyageurs et marchandises, de sa ligne de Carmaux à Albi.',
        'reason' => 'article de la gare (oldid 241142530) ; article de la ligne « Ligne de Castelnaudary à Rodez » (oldid 241147760) : « 1er mai 1858, ouverture d\'Albi à Carmaux ». Remplace année 1857 de Wikidata.',
    ],
    // Grigny-Centre (id 17348)
    [
        'station' => ['name' => 'Grigny-Centre', 'uic' => '87681379'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1974-02-16',
        'precision' => 'day',
        'source_url' => $wp('Gare de Grigny-Centre', 238340584),
        'quote' => 'La gare de Grigny-Centre est mise en service le 16 février 1974 en première étape de la ligne nouvelle de Grigny à Corbeil-Essonnes.',
        'reason' => 'article de la gare (oldid 238340584) ; article de la ligne « Ligne de Grigny à Corbeil-Essonnes » (oldid 239549502) : « Le 16 février 1974, ouverture de la gare de Grigny-Centre ». Remplace année 1975 de Wikidata.',
    ],
    // Tende (id 18066)
    [
        'station' => ['name' => 'Tende', 'uic' => '87756882'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1928-10-30',
        'precision' => 'day',
        'source_url' => $wp('Gare de Tende', 228557460),
        'quote' => 'Cette gare a été inaugurée le 30 octobre 1928 en présence du ministre français de l\'économie M.',
        'reason' => 'article de la gare (oldid 228557460) ; article de la ligne « Ligne de Coni à Vintimille » (oldid 237352655) : « Le 30 octobre 1928 a lieu l\'inauguration suivie de la mise en exploitation des deux lignes Coni - Vintimille ». Remplace année 1913 de Wikidata.',
    ],
    // Bourg-la-Reine (id 20943)
    [
        'station' => ['name' => 'Bourg-la-Reine', 'wikidata' => 'Q1962846'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1846-06-23',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bourg-la-Reine', 238801856),
        'quote' => 'infobox, mise en service : 7 juin 1846',
        'reason' => 'article de la gare (oldid 238801856) ; article de la ligne « Ligne de Sceaux » (oldid 240177929) : « Il est inauguré le 7 juin 1846, et ouvert au public le 23 juin ». Remplace déduite de la ligne (23/06/1846) ; le 07/06/1846 de l\'infobox est l\'inauguration.',
    ],
    // Hombourg-Haut (id 15318)
    [
        'station' => ['name' => 'Hombourg-Haut', 'uic' => '87193276'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1851-11-16',
        'precision' => 'day',
        'source_url' => $wp('Gare de Hombourg-Haut', 241494287),
        'quote' => 'La « station de Hombourg » est mise en service le 16 novembre 1851 par la Compagnie du chemin de fer de Paris à Strasbourg, lorsqu\'elle ouvre officiellement à l\'exploitation le tronçon de {{unité|19|kilomètres}} de Saint-Avold à Forbach, troisième section de l\'embranchement.',
        'reason' => 'article de la gare (oldid 241494287) ; article de la ligne « Ligne de Rémilly à Stiring-Wendel » (oldid 235298902) : « La ligne est ouverte en deux sections, de Rémilly à Forbach en 1851 et de Forbach à la frontière en 1852 ». Remplace année 1852 de Wikidata.',
    ],
    // Kerhostin (id 16417)
    [
        'station' => ['name' => 'Kerhostin', 'uic' => '87476432'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1891-07-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Kerhostin', 236449559),
        'quote' => 'Une halte provisoire à l\'essai, dénommée « Kerostin », est mise en service le 1 juillet 1891 par la Compagnie du chemin de fer de Paris à Orléans (PO), elle est desservie par des trains légers.',
        'reason' => 'article de la gare (oldid 236449559) ; article de la ligne « Ligne d\'Auray à Quiberon » (oldid 241334496) : « une halte provisoire, à « Kerhostin », est créée en 1891. Cet arrêt, confirmé l\'année suivante ». Remplace année 1892 de Wikidata (confirmation de la halte).',
    ],
    // Aime-La Plagne (id 17859)
    [
        'station' => ['name' => 'Aime-La Plagne', 'uic' => '87741769'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1913-10-20',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Aime-La Plagne', 234915287),
        'quote' => 'La gare a été inaugurée le 20 octobre 1913, en même temps que la portion de ligne allant de Moûtiers à Bourg-Saint-Maurice.',
        'reason' => 'article de la gare (oldid 234915287) ; article de la ligne « Ligne de Saint-Pierre-d\'Albigny à Bourg-Saint-Maurice » (oldid 238227992) : « La section de ligne de Moûtiers à Bourg-Saint-Maurice est mise en service en 1913 ». Remplace année 1893 de Wikidata (section Albertville - Moûtiers, hors de la gare).',
    ],
    // Vincey (id 15033)
    [
        'station' => ['name' => 'Vincey', 'uic' => '87144063'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1887-08-07',
        'precision' => 'day',
        'source_url' => $wp('Gare de Vincey', 241364503),
        'quote' => 'La \'\'halte de Vincey\'\' est mise en service le 7 août 1887 par la Compagnie des chemins de fer de l\'Est.',
        'reason' => 'article de la gare (oldid 241364503). Remplace année 1857 de Wikidata (ouverture de la ligne, la halte est créée en 1887).',
    ],
    // Quédillac (id 16344)
    [
        'station' => ['name' => 'Quédillac', 'uic' => '87473058'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1899-08-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Quédillac', 238688631),
        'quote' => 'La halte de Quédillac est mise en service le 15 août 1899 par la Compagnie des chemins de fer de l\'Ouest.',
        'reason' => 'article de la gare (oldid 238688631). Remplace année 1863 de Wikidata (ouverture de la ligne, la halte est créée en 1899).',
    ],
    // Reuilly (id 17068)
    [
        'station' => ['name' => 'Reuilly', 'uic' => '87597039'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1888-08-13',
        'precision' => 'day',
        'source_url' => $wp('Gare de Reuilly (Indre)', 228675923),
        'quote' => 'La station de Reuilly est mise en service le 13 août 1888 par la Compagnie du chemin de fer de Paris à Orléans.',
        'reason' => 'article de la gare (oldid 228675923). Remplace année 1847 de Wikidata (ouverture de la ligne).',
    ],
    // Les Vallées (id 16046)
    [
        'station' => ['name' => 'Les Vallées', 'uic' => '87386300'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-10-15',
        'precision' => 'day',
        'source_url' => $wp('Gare des Vallées', 236109975),
        'quote' => 'La gare des Vallées est mise en service le 15 octobre 1897 par la Compagnie des chemins de fer de l\'Ouest.',
        'reason' => 'article de la gare (oldid 236109975). Remplace année 1894 de Wikidata.',
    ],
    // St-Martin-du-Mont (id 17869)
    [
        'station' => ['name' => 'St-Martin-du-Mont', 'uic' => '87743112'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1896-06-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Martin-du-Mont', 241399115),
        'quote' => 'La halte de Saint-Martin-du-Mont est mise en service le 1 juin 1896 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM), elle est ouverte au service des voyageurs sans bagages et au transport des chiens avec billets.',
        'reason' => 'article de la gare (oldid 241399115). Remplace année 1856 de Wikidata (ouverture de la ligne, la halte est créée en 1896).',
    ],
    // Chaingy-Fourneaux-Plage (id 16734)
    [
        'station' => ['name' => 'Chaingy-Fourneaux-Plage', 'uic' => '87574111'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1947-07-13',
        'precision' => 'day',
        'source_url' => $wp('Gare de Chaingy-Fourneaux-Plage', 241295652),
        'quote' => 'La gare est inaugurée le 13 juillet 1947.',
        'reason' => 'article de la gare (oldid 241295652). Remplace 1846 déduit de la ligne (ouverture de Paris - Orléans) : la gare est une création de 1947.',
    ],
    // Bressuire (id 16570)
    [
        'station' => ['name' => 'Bressuire', 'uic' => '87487249'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Bressuire', 235031029),
        'quote' => 'infobox, mise en service : 28 décembre 1868',
        'reason' => 'article de la gare (oldid 235031029) ; article de la ligne « Ligne de La Possonnière à Niort » (oldid 241119292) : « celle de Cholet à Niort le 26 décembre 1868 ». Remplace année 1866 de Wikidata (section La Possonnière - Cholet, qui ne va pas à Bressuire) ; le 28/12/1868 de l\'infobox et le 26/12/1868 de la ligne diffèrent de deux jours, année retenue.',
    ],

    // ── 37. Ouvertures des gares encore ouvertes datées à l'année par Wikidata ou déduites de la ligne, suite de la relecture contre
    // l'article de la gare (révision citée) et la chronologie de l'article de la ligne : 10 des 14 cas laissés à décider par la
    // section 36 (Reims, Puteaux, Les Aubrais, La Celle-Saint-Cloud, Lourdes, Montréjeau, Aiguillon, Pertuis, Aubiat, Urt) et 16 gares
    // des 60 suivantes par fréquentation (audit-gares-2, 23/11/2026, rapport storage/app/reports/agents/aprem-2111/audit-gares-2.md).
    // Une section de ligne ou la ligne entière donnait l'année ; la gare ouvre le jour de sa propre section, ou plus tard (halte).

    // Reims (id 15069)
    [
        'station' => ['name' => 'Reims', 'uic' => '87171009'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-06-05',
        'precision' => 'day',
        'source_url' => $wp('Gare de Reims', 241441206),
        'quote' => 'Ouvert en 1854 par la Compagnie des chemins de fer de l\'Est, l\'embarcadère est alors le terminus de la ligne d\'Épernay à Reims',
        'reason' => 'article de la gare (oldid 241441206) : le premier embarcadère de 1854 est le terminus de la ligne d\'Épernay à Reims ; article de la ligne « Ligne d\'Épernay à Reims » (oldid 235341667) : « La ligne est ouverte à l\'exploitation au service des voyageurs le 5 juin 1854 » (Palau). La gare actuelle de 1858 (10/06/1858) remplace cet embarcadère, la fiche garde l\'ouverture de la première gare. Remplace année 1854 de Wikidata.',
    ],
    // Puteaux (id 16030)
    [
        'station' => ['name' => 'Puteaux', 'uic' => '87382382'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1840-07-18',
        'precision' => 'day',
        'source_url' => $wp('Gare de Puteaux', 237145215),
        'quote' => 'La station est ouverte le 18 juillet 1840 par la Société anonyme du chemin de fer de Paris à Saint-Cloud et Versailles.',
        'reason' => 'article de la gare (oldid 237145215), date répétée dans l\'infobox et le texte. L\'article de la ligne « Ligne de Paris-Saint-Lazare à Versailles-Rive-Droite » (oldid 235113118) donne l\'ouverture de la ligne le 2 août 1839 sans citer Puteaux : la station est donc ouverte après la ligne (halte créée plus tard, phrase explicite). Remplace année 1839 de Wikidata (ouverture de la ligne).',
    ],
    // Les Aubrais-Orléans (id 16621)
    [
        'station' => ['name' => 'Les Aubrais-Orléans', 'uic' => '87543017'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1853-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare des Aubrais', 240222394),
        'quote' => 'La première gare, construite au lieu-dit Les Aubrais, est inaugurée le 28 septembre 1853.',
        'reason' => 'article de la gare (oldid 240222394) ; article de la ligne « Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean » (oldid 240534824) : une gare de passage est ouverte au lieu-dit Les Aubrais, « d\'abord provisoirement en 1853 ». Remplace année 1843 de Wikidata (ouverture de Paris - Orléans, la gare des Aubrais n\'existe pas encore).',
    ],
    // La Celle-St-Cloud (id 16031)
    [
        'station' => ['name' => 'La Celle-St-Cloud', 'uic' => '87382432'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1959-10-04',
        'precision' => 'day',
        'source_url' => $wp('Gare de La Celle-Saint-Cloud', 241224946),
        'quote' => 'Ouverte le 4 octobre 1959, c\'est une gare de la Société nationale des chemins de fer français (SNCF)',
        'reason' => 'article de la gare (oldid 241224946) : la ligne de 1884 traverse la commune « sans arrêt sur une distance de 4,7 kilomètres », les demandes de halte de 1895 et 1925 n\'aboutissent qu\'en 1959 ; article de la ligne « Ligne de Saint-Cloud à Saint-Nom-la-Bretèche - Forêt-de-Marly » (oldid 240844633) : ligne ouverte le 5 mai 1884 avec six points d\'arrêt, dont aucun à La Celle-Saint-Cloud. Remplace année 1884 déduite de la ligne.',
    ],
    // Lourdes (id 17283)
    [
        'station' => ['name' => 'Lourdes', 'uic' => '87671339'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-04-20',
        'precision' => 'day',
        'source_url' => $wp('Gare de Tarbes', 240593753),
        'quote' => 'La section de Tarbes à Lourdes est mise en service le 20 avril 1866',
        'reason' => 'article de la gare de Tarbes (oldid 240593753), confirmé par l\'infobox de l\'article de la gare de Lourdes (oldid 238810479, 20 avril 1866) et par l\'article de la gare d\'Ossun (oldid 235030562, même section le 20 avril 1866). Remplace année 1867 de Wikidata (ouverture de la section Lourdes - Pau).',
    ],
    // Montréjeau-Gourdan-Polignan (id 17110)
    [
        'station' => ['name' => 'Montréjeau-Gourdan-Polignan', 'uic' => '87611152'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1862-06-09',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Gaudens', 237169186),
        'quote' => 'La gare de Saint-Gaudens est mise en service le 9 juin 1862 par la Compagnie des chemins de fer du Midi et du Canal latéral à la Garonne lorsqu\'elle ouvre la section de Portet-Saint-Simon à Montréjeau, qui permet la circulation des trains depuis Toulouse.',
        'reason' => 'article de la gare de Saint-Gaudens (oldid 237169186) : section Portet-Saint-Simon - Montréjeau ouverte le 9 juin 1862 ; article de la gare de Montréjeau (oldid 239467932) : même jour et même section, mais l\'article écrit 1863 dans l\'infobox et dans une phrase et 1862 dans son introduction ; 1862 est retenu (deux articles et l\'année de Wikidata contre une seule phrase, qui contredit l\'introduction de son propre article). Le jour (9 juin) est identique partout. Remplace année 1862 de Wikidata par le jour.',
    ],
    // Aiguillon (id 16941)
    [
        'station' => ['name' => 'Aiguillon', 'uic' => '87586693'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-05-29',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Aiguillon', 223772493),
        'quote' => 'La station d\'Aiguillon est mise en service le 29 mai 1856 par la Compagnie des chemins de fer du Midi et du Canal latéral à la Garonne (Midi), lorsqu\'elle ouvre à l\'exploitation la section de Tonneins à Valence-d\'Agen',
        'reason' => 'article de la gare (oldid 223772493, introduction et catégorie : 1856 ; l\'infobox écrit 1855 par erreur) ; article de la ligne « Ligne de Bordeaux-Saint-Jean à Sète-Ville » (oldid 234926948) : « 29 mai 1856 : ouverture de Tonneins à Valence-d\'Agen ». Précise l\'année 1856 de Wikidata.',
    ],
    // Pertuis (id 17970)
    [
        'station' => ['name' => 'Pertuis', 'uic' => '87751362'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1872-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Pertuis', 241561871),
        'quote' => 'infobox, mise en service : 15 mai 1872',
        'reason' => 'article de la gare (oldid 241561871, 15 mai 1872) ; article de la ligne « Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble) » (oldid 241143131) : « De Pertuis à Aix-en-Provence le 11 mai 1872 ». Les deux dates diffèrent de quatre jours et l\'article de la ligne de Cheval-Blanc à Pertuis (oldid 241491042) ouvre l\'exploitation en octobre 1871 et le service voyageurs le 25 novembre 1872 : année 1872 retenue, jour non tranché. Remplace année 1863 de Wikidata.',
    ],
    // Aubiat (id 17798)
    [
        'station' => ['name' => 'Aubiat', 'uic' => '87734111'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1899-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Aubiat', 235118777),
        'quote' => 'En 1853 l\'enquête faite pour choisir l\'emplacement des gares conclut que le projet de création d\'une gare à Aubiat est abandonné [...] Elle est finalement ouverte au service voyageur le 15 mai 1899.',
        'reason' => 'article de la gare (oldid 235118777) : le projet de 1853 est abandonné, la halte ouvre en 1899 (halte créée bien après la ligne, phrase explicite, 1911 : halte nommée Aubiat). Remplace année 1855 de Wikidata (ouverture de la ligne).',
    ],
    // Urt (id 17303)
    [
        'station' => ['name' => 'Urt', 'uic' => '87672386'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-01-25',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Urt', 240100205),
        'quote' => 'infobox, mise en service : 25 janvier 1864 ; le premier chantier pour l\'arrivée du chemin de fer sur la commune est ouvert en 1862 [...] retards repoussant l\'ouverture prévue le 1 novembre 1863.',
        'reason' => 'article de la gare (oldid 240100205, introduction « mise en service en 1864 », catégorie 1864, historique des retards de chantier depuis 1862). Remplace année 1862 de Wikidata (début des travaux). L\'article de la ligne « Ligne de Toulouse à Bayonne » (oldid 241301414) ne détaille pas la section.',
    ],
    // Garges-Sarcelles (id 15526)
    [
        'station' => ['name' => 'Garges-Sarcelles', 'uic' => '87276196'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1959-01-11',
        'precision' => 'day',
        'source_url' => $wp('Gare de Garges - Sarcelles', 240682562),
        'quote' => 'La gare est finalement ouverte le 11 janvier 1959, comme simple point d\'arrêt',
        'reason' => 'article de la gare (oldid 240682562) : halte créée plus d\'un siècle après la ligne directe de Paris à Lille (10 mai et 1er juin 1859, article de la ligne « Ligne de Paris-Nord à Lille », oldid 241487697). Remplace année 1959 de Wikidata par le jour.',
    ],
    // Bois-Colombes (id 15971)
    [
        'station' => ['name' => 'Bois-Colombes', 'uic' => '87381079'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-11-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bois-Colombes', 238099461),
        'quote' => 'La gare du « Bois de Colombes » est ouverte le 1 novembre 1857 par la Compagnie des chemins de fer de l\'Ouest.',
        'reason' => 'article de la gare (oldid 238099461) : halte créée six ans après la ligne d\'Asnières à Argenteuil (28 avril 1851), phrase explicite. Remplace année 1857 de Wikidata par le jour.',
    ],
    // Bécon-les-Bruyères (id 16019)
    [
        'station' => ['name' => 'Bécon-les-Bruyères', 'uic' => '87382002'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1891-10-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Bécon-les-Bruyères', 239336909),
        'quote' => 'La gare de Bécon-les-Bruyères est ouverte en octobre 1891, et inaugurée le 1 juillet 1892 par M. Alexandre Ribot',
        'reason' => 'article de la gare (oldid 239336909) : ouverture au public en octobre 1891, inauguration le 1er juillet 1892 (l\'infobox donne l\'inauguration). Remplace le mois de mars 2017 d\'OSM (rénovation).',
    ],
    // Vert-Galant (id 15479)
    [
        'station' => ['name' => 'Vert-Galant', 'uic' => '87271437'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1890-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare du Vert-Galant', 238165661),
        'quote' => 'ce n\'est qu\'en 1890 qu\'un arrêt voit le jour. Cette halte prend le nom officiel de Vaujours, puis de Vert-Galant en 1895.',
        'reason' => 'article de la gare (oldid 238165661, infobox : 1890) : halte créée en 1890, le bâtiment de gare est inauguré en 1929. Remplace le 31/08/1861 déduit de la ligne (ouverture de la ligne, la halte n\'existait pas).',
    ],
    // Vigneux-sur-Seine (id 17342)
    [
        'station' => ['name' => 'Vigneux-sur-Seine', 'uic' => '87681304'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1863-05-18',
        'precision' => 'day',
        'source_url' => $wp('Gare de Vigneux-sur-Seine', 241289556),
        'quote' => 'La compagnie du PLM ouvre la section de Villeneuve Saint-Georges à Juvisy le 18 mai 1863 et ouvre par la même la gare de Vigneux',
        'reason' => 'article de la gare (oldid 241289556) ; article de la ligne « Ligne de Villeneuve-Saint-Georges à Montargis » (oldid 236077985) : « le tronçon Villeneuve-Saint-Georges - Juvisy est ouvert le 18 mai 1863. La gare de Vigneux fut ouverte lors du raccordement. » Remplace année 1973 de Wikidata (reconstruction du bâtiment voyageurs).',
    ],
    // Valenciennes (id 15910)
    [
        'station' => ['name' => 'Valenciennes', 'uic' => '87343004'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1842-11-14',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Douai à Blanc-Misseron', 235337712),
        'quote' => '14 novembre 1842 : ouverture du tronçon de Valenciennes à Quiévrain',
        'reason' => 'article de la ligne « Ligne de Douai à Blanc-Misseron » (oldid 235337712), section qui part de Valenciennes ; article de la gare (oldid 241339108, infobox : 1842) : « la plus ancienne du réseau de la Compagnie des chemins de fer du Nord ». Remplace année 1909 de Wikidata (bâtiment actuel).',
    ],
    // Chartres (id 16132)
    [
        'station' => ['name' => 'Chartres', 'uic' => '87394007'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1849-07-12',
        'precision' => 'day',
        'source_url' => $wp('Gare de Chartres', 239710073),
        'quote' => 'infobox, mise en service : 12 juillet 1849 (reconstruction : 1933)',
        'reason' => 'article de la gare (oldid 239710073) ; article de la ligne « Ligne de Paris-Montparnasse à Brest » (oldid 239547071) : « elle atteint Chartres le 12 juillet 1849 ». Précise l\'année 1849 de Wikidata.',
    ],
    // Annecy (id 17906)
    [
        'station' => ['name' => 'Annecy', 'uic' => '87746008'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-07-05',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Annecy', 238985694),
        'quote' => 'Le 5 juillet 1866, mise en service de la ligne entre Aix-les-Bains et Annecy, par le PLM.',
        'reason' => 'article de la gare (oldid 238985694) ; article de la ligne « Ligne d\'Aix-les-Bains-Le Revard à Annemasse » (oldid 240496723) : « d\'Aix-les-Bains à Annecy le 5 juillet 1866 » (Annecy est le terminus de la section). Précise l\'année 1866 de Wikidata.',
    ],
    // Amiens (id 15800)
    [
        'station' => ['name' => 'Amiens', 'uic' => '87313874'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1846-06-20',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Amiens', 241229268),
        'quote' => 'Le 20 juin 1846 a lieu la mise en service de l\'embarcadère d\'Amiens, dessiné par l\'architecte Alfred Armand',
        'reason' => 'article de la gare (oldid 241229268) ; article de la ligne « Ligne de Douai à Blanc-Misseron » (oldid 235337712) : la ligne de Paris-Nord à Lille est « ouverte le même jour » que Douai - Valenciennes, le 20 juin 1846. Précise l\'année 1846 de Wikidata.',
    ],
    // Goussainville (id 15531)
    [
        'station' => ['name' => 'Goussainville', 'uic' => '87276246'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Goussainville', 238918665),
        'quote' => 'La ligne de Saint-Denis à Creil via Survilliers fut ouverte en 1859 par la compagnie du Nord',
        'reason' => 'article de la gare (oldid 238918665) ; article de la ligne « Ligne de Paris-Nord à Lille » (oldid 241487697) : nouvel itinéraire de Villiers-le-Bel à Creil ouvert le 1er juin 1859. Remplace le 25/01/1846 déduit de la ligne (ancien itinéraire par Pontoise, qui ne passe pas à Goussainville). Année retenue, jour de la gare non cité.',
    ],
    // Besançon-Viotte (id 17586)
    [
        'station' => ['name' => 'Besançon-Viotte', 'uic' => '87718007'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-04-07',
        'precision' => 'day',
        'source_url' => $wp('Gare de Besançon-Viotte', 241433030),
        'quote' => 'Ouverte en 1856 par la Compagnie du chemin de fer de Paris à Lyon',
        'reason' => 'article de la gare (oldid 241433030, infobox : 1856) ; article de la ligne « Ligne de Dole-Ville à Belfort » (oldid 241427688) : « le 7 avril 1856 pour la section de Dole à Besançon » (Besançon est le terminus de la section). Remplace année 1855 de Wikidata (gare en bois provisoire construite en 1855).',
    ],
    // Épinay-sur-Seine TT (id 17508)
    [
        'station' => ['name' => 'Épinay-sur-Seine TT', 'uic' => '87697292'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2017-07-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne 11 du tramway d\'Île-de-France', 238239715),
        'quote' => '1 juillet 2017 : mise en service partielle entre Épinay-sur-Seine et Le Bourget',
        'reason' => 'article de la ligne 11 du tramway d\'Île-de-France (oldid 238239715) : Épinay-sur-Seine est le terminus de la section ouverte le 1er juillet 2017 ; cette fiche est l\'arrêt du tram-train (ligne 960000, Tangentielle Nord), pas la gare du RER C de 1908. Remplace le 1908 déduit du tracé.',
    ],
    // Pierrefite-Stains TT (id 17511)
    [
        'station' => ['name' => 'Pierrefite-Stains TT', 'uic' => '87697326'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2017-07-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne 11 du tramway d\'Île-de-France', 238239715),
        'quote' => '1 juillet 2017 : mise en service partielle entre Épinay-sur-Seine et Le Bourget',
        'reason' => 'article de la ligne 11 du tramway d\'Île-de-France (oldid 238239715) : Pierrefitte - Stains est un arrêt de la section Épinay-sur-Seine - Le Bourget ouverte le 1er juillet 2017 (même jour que Villetaneuse-Université, déjà daté) ; cette fiche est l\'arrêt du tram-train (ligne 960000), pas la gare du RER D. Remplace le 1859 déduit du tracé.',
    ],
    // Val-d'Argenteuil (id 16007)
    [
        'station' => ['name' => 'Val-d\'Argenteuil', 'uic' => '87381798'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1970-12-01',
        'precision' => 'month',
        'source_url' => $wp('Gare du Val d\'Argenteuil', 240274594),
        'quote' => 'infobox, mise en service : décembre 1970 (Bernard Collardey, Les trains de banlieue, p. 130)',
        'reason' => 'article de la gare (oldid 240274594, infobox citant Collardey). Précise l\'année 1970 de Wikidata au mois.',
    ],
    // Boussy-St-Antoine (id 17368)
    [
        'station' => ['name' => 'Boussy-St-Antoine', 'uic' => '87682138'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1969-06-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Boussy-Saint-Antoine', 238031038),
        'quote' => 'infobox, mise en service : juin 1969',
        'reason' => 'article de la gare (oldid 238031038, infobox et catégorie 1969). Précise l\'année 1969 de Wikidata au mois.',
    ],
    // Le Blanc-Mesnil (id 15483)
    [
        'station' => ['name' => 'Le Blanc-Mesnil', 'uic' => '87271478'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1980-06-01',
        'precision' => 'month',
        'source_url' => $wp('Gare du Blanc-Mesnil', 233856627),
        'quote' => 'La gare du Blanc-Mesnil n\'est mise en service que début juin 1980',
        'reason' => 'article de la gare (oldid 233856627, infobox : 1980). Précise l\'année 1980 de Wikidata au mois.',
    ],

    // ── 38. Fermetures au service des gares fermées sur une ligne encore ouverte, relues contre l'article (audit-fermetures,
    // 23/11/2026, rapport storage/app/reports/agents/aprem-2111/audit-fermetures.md). La date de fermeture de ces gares était déduite d'un
    // événement de la ligne (déclassement, fin du fret, dépose de la voie, fin d'une autre antenne) ou d'un tracé, sans rapport avec
    // l'arrêt de la gare elle-même.

    // Dommartin-Lissieu (ancienne gare, Rhône) : la gare a été déplacée de quelques centaines de mètres vers le sud en 2012 ; la nouvelle gare
    // (fiche 17656) est desservie par les TER. La fermeture de 1998, déduite d'un tracé, ne repose sur rien.
    [
        'station' => ['name' => 'Dommartin-Lissieu', 'near' => [4.73223, 45.84781, 0.2]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '2012-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Dommartin - Lissieu', 241156143),
        'quote' => 'En 2012, la gare a été déplacée de quelques centaines de mètres au sud, toujours sur la commune de Dommartin',
        'reason' => 'ancienne gare remplacée en 2012 par la gare actuelle, quelques centaines de mètres plus au sud (fiche 17656, desservie par les TER) ; la date 1998 était déduite d\'un tracé, jour de 2012 non trouvé',
    ],
    // Bercy-Ceinture : la gare de la ligne PLM ferme le 05/10/1942, jour de l'ouverture du prolongement de la ligne 8 du métro à
    // Charenton - Écoles. La date de 1993 est la fin de la Petite Ceinture, sans rapport avec cette gare.
    [
        'station' => ['name' => 'Bercy-Ceinture', 'wikidata' => 'Q21426977'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1942-10-05',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bercy-Ceinture', 235030745),
        'quote' => 'La gare reste en activité jusqu\'à sa fermeture le 5 octobre 1942 à la suite de l\'ouverture le même jour du prolongement de la ligne 8 du métro de Paris',
        'reason' => 'article de la gare : fermeture le 05/10/1942 (prolongement du métro à Charenton - Écoles) ; la date 1993 était celle de la fin de la Petite Ceinture, déduite de la ligne',
    ],
    // Fresnoy-Morangles : station du chemin de fer de Hermes à Beaumont fermée définitivement le 18/12/1935, treize ans avant la fin
    // de l'exploitation de la ligne (1948-1949) et vingt-quatre avant son déclassement (01/01/1959, date déduite jusque-là).
    [
        'station' => ['name' => 'Fresnoy-Morangles', 'near' => [2.28652, 49.20626, 0.3]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1935-12-18',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer de Hermes à Beaumont', 239087525),
        'quote' => 'fermeture définitive de la station de Fresnoy-Morangles le 18 décembre 1935',
        'reason' => 'article de la ligne : la station est fermée définitivement le 18/12/1935, bien avant la fin de l\'exploitation (1948-1949) ; la date 1959 était le déclassement de la ligne',
    ],
    // Gavarry : gare de l'antenne de La Pauline-Hyères aux Salins-d'Hyères, fermée officiellement au service des voyageurs le 24/05/1940.
    // La date de 2010 déduite de la ligne n'est pas celle des voyageurs.
    [
        'station' => ['name' => 'Gavarry', 'near' => [6.07595, 43.12989, 0.3]],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-24',
        'precision' => 'day',
        'source_url' => $wp('Ligne de La Pauline-Hyères aux Salins-d\'Hyères', 235223327),
        'quote' => 'La fermeture du service voyageurs, de la Pauline-Hyères aux Salins-d\'Hyères, intervient officiellement le 24 mai 1940.',
        'reason' => 'article de la ligne : fermeture officielle du service voyageurs de la Pauline-Hyères aux Salins-d\'Hyères le 24/05/1940 ; la date de 2010 déduite de la ligne est celle d\'un autre service',
    ],
    // Romanswiller : la desserte de marchandises (un aller-retour quotidien depuis Molsheim) dure jusqu'en 1988, dernière desserte de la
    // gare (voyageurs supprimés en 1969). Le 25/02/1993 de la fiche est le déclassement de Molsheim à Romanswiller.
    [
        'station' => ['name' => 'Romanswiller', 'wikidata' => 'Q125552008'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1988-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Romanswiller', 238409253),
        'quote' => 'Le trafic de marchandises sur la ligne subsistera jusqu’en 1988 sous la forme d\'une desserte quotidienne depuis Molsheim',
        'reason' => 'dernière desserte (le fret) en 1988, voyageurs supprimés en 1969 ; le 25/02/1993 est le déclassement de Molsheim à Romanswiller (article de la ligne de Sélestat à Saverne), pas la fin du service',
    ],
    // Gattières, Vence, Loup, Peymeinade (Central-Var) : l'armée allemande détruit les viaducs de Pascaressa, du Loup et de la Siagne le
    // 24/08/1944 ; dès 1945 la ligne est limitée à Meyrargues - Tanneron et l'est de la Siagne n'est jamais reconstruit. La date du
    // 02/01/1950 est la fermeture officielle du reste de la ligne (Montauroux, Callian, Fayence, Claviers : confirmée). Jour de l'arrêt
    // du service non trouvé : année 1944.
    [
        'station' => ['name' => 'Gattières', 'near' => [7.18033, 43.75533, 0.3]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1944-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne Central-Var', 229972705),
        'quote' => 'le 24 août 1944, l’armée allemande détruisit les trois principaux viaducs situés dans les Alpes-Maritimes',
        'reason' => 'arrêt du service : viaducs détruits le 24/08/1944 et jamais reconstruits, la ligne est limitée à Meyrargues - Tanneron dès 1945 (fermeture officielle du reste le 02/01/1950) ; la date 1950 déduite de la ligne est la fermeture de la section ouest',
    ],
    [
        'station' => ['name' => 'Vence', 'near' => [7.09677, 43.72511, 0.3]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1944-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne Central-Var', 229972705),
        'quote' => 'Dès 1945, la ligne Central-Var est alors limitée à la section Meyrargues - Tanneron',
        'reason' => 'arrêt du service : viaducs détruits le 24/08/1944 et jamais reconstruits, la section à l\'est de la Siagne ne rouvre pas (fermeture officielle du reste le 02/01/1950)',
    ],
    [
        'station' => ['name' => 'Loup', 'near' => [6.98531, 43.71373, 0.3]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1944-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne Central-Var', 229972705),
        'quote' => 'Dès 1945, la ligne Central-Var est alors limitée à la section Meyrargues - Tanneron',
        'reason' => 'arrêt du service : viaduc du Loup détruit le 24/08/1944 et jamais reconstruit (fermeture officielle du reste de la ligne le 02/01/1950)',
    ],
    [
        'station' => ['name' => 'Peymeinade', 'near' => [6.87547, 43.63909, 0.3]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1944-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne Central-Var', 229972705),
        'quote' => 'Dès 1945, la ligne Central-Var est alors limitée à la section Meyrargues - Tanneron',
        'reason' => 'arrêt du service : viaduc de la Siagne détruit le 24/08/1944 et jamais reconstruit, la section est de Tanneron ne rouvre pas (fermeture officielle du reste le 02/01/1950)',
    ],
];
