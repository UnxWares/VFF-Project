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
        'date' => '1913-11-20',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bourg-Saint-Maurice', 240157164),
        'quote' => 'La gare de Bourg-Saint-Maurice est mise en service le 20 novembre 1913 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM), lors de l\'ouverture à l\'exploitation de la section de Moûtiers à Bourg-Saint-Maurice',
        'reason' => 'mise en service de la section de Moûtiers à Bourg-Saint-Maurice, où est la gare : article de la gare de Bourg-Saint-Maurice (oldid 240157164, d\'après Penna, La Tarentaise Autrefois, 2005, p. 118 et 127 ; inauguration le 28 juin 1914), article de la gare de Landry (oldid 230267366) et chronologie de l\'article de la ligne (oldid 238227992, « 20 novembre 1913 : de Moûtiers à Bourg-Saint-Maurice »). L\'article de la gare d\'Aime-La Plagne (oldid 234915287, « inaugurée le 20 octobre 1913, en même temps que la portion de ligne ») est seul à donner le 20 octobre, un mois avant l\'ouverture de la section. Remplace année 1893 de Wikidata (section Albertville - Moûtiers, hors de la gare).',
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

    // ── 39. Ouvertures des gares encore ouvertes datées à l'année par Wikidata ou déduites de la ligne, troisième relecture contre
    // l'article de la gare (révision citée) et la chronologie de l'article de la ligne : 2 des 6 cas laissés à décider par la section 37
    // (Marseille-Saint-Charles, Montpellier-Saint-Roch) et 18 gares des 80 suivantes par fréquentation (audit-gares-3, 23/11/2026,
    // rapport storage/app/reports/agents/aprem-2111/audit-gares-3.md). Lorsque la gare a déménagé, la fiche garde la date de la première
    // gare de la ville, comme pour Reims (section 37).

    // Marseille-St-Charles (id 17963)
    [
        'station' => ['name' => 'Marseille-St-Charles', 'uic' => '87751008'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1848-01-16',
        'precision' => 'day',
        'source_url' => $wp('Gare de Marseille-Saint-Charles', 241581746),
        'quote' => 'Le 16 janvier 1848 : mise en exploitation publique de la ligne entre Marseille et Rognonas',
        'reason' => 'article de la gare (oldid 241581746, « Dates clés ») : le 8 janvier 1848 est l\'inauguration de la gare et le premier convoi officiel, la mise en exploitation publique de la ligne Marseille - Rognonas date du 16 janvier 1848 ; article de la ligne « Ligne de Paris-Lyon à Marseille-Saint-Charles » (oldid 241214704) : sections de l\'ancienne ligne d\'Avignon à Marseille ouvertes en 1847 et 1849, compagnie en cessation de paiements en 1848, ce qui place le service de Marseille en 1848. Précise l\'année 1848 de Wikidata au jour d\'ouverture au public (et non à l\'inauguration).',
    ],
    // Montpellier-St-Roch (id 18151)
    [
        'station' => ['name' => 'Montpellier-St-Roch', 'uic' => '87773002'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1839-03-08',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Tarascon à Sète-Ville', 236799571),
        'quote' => '8 mars 1839 dans les chemins de fer, mise en service de Montpellier à Sète',
        'reason' => 'article de la ligne « Ligne de Tarascon à Sète-Ville » (oldid 236799571, chronologie et Palau 2.4 « Montpellier - Cette : 8 mars 1839 ») : mise en service pour les voyageurs le 8 mars 1839 ; article de la gare (oldid 239952248) : la première gare de Montpellier est le terminus de cette ligne, la gare actuelle ne date que de la ligne de Nîmes (service le 9 janvier 1845). La fiche garde la date de la première gare de la ville comme VFF le fait pour Reims ; précise l\'année 1839 de Wikidata au jour.',
    ],
    // Viry-Châtillon (id 17343)
    [
        'station' => ['name' => 'Viry-Châtillon', 'uic' => '87681312'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1959-01-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Viry-Châtillon', 236226689),
        'quote' => 'infobox, mise en service : janvier 1959',
        'reason' => 'article de la gare (oldid 236226689, infobox : janvier 1959). Précise l\'année 1959 de Wikidata au mois.',
    ],
    // Les Noues (id 15530)
    [
        'station' => ['name' => 'Les Noues', 'uic' => '87276238'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1964-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare des Noues', 238918749),
        'quote' => 'La gare est ouverte en 1964 grâce à l\'augmentation de la population du quartier',
        'reason' => 'article de la gare (oldid 238918749) : la ligne de Saint-Denis à Creil ouvre en 1859, la gare des Noues est une création de 1964. Remplace l\'année 1859 de Wikidata (ouverture de la ligne).',
    ],
    // Aubergenville-Élisabethville (id 16057)
    [
        'station' => ['name' => 'Aubergenville-Élisabethville', 'uic' => '87386730'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1891-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Aubergenville-Élisabethville', 230660645),
        'quote' => 'La halte d\'Aubergenville est mise en service en 1891 par la compagnie des chemins de fer de l\'Ouest',
        'reason' => 'article de la gare (oldid 230660645, infobox : 1891, catégorie 1891) : halte créée en 1891, développée en gare dans les années 1930. Remplace le 09/05/1843 déduit de l\'ouverture de la ligne.',
    ],
    // Avenue-du-Président-Kennedy-Radio-France (id 16637)
    [
        'station' => ['name' => 'Avenue-du-Président-Kennedy-Radio-France', 'uic' => '87543207'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1904-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de l\'avenue du Président-Kennedy', 236109269),
        'quote' => 'Elle est ouverte en 1904, après l\'inauguration de la ligne, en 1900 et était dénommé Halte du quai de Passy',
        'reason' => 'article de la gare (oldid 236109269) : halte du quai de Passy ouverte en 1904, la ligne datant de 1900. Remplace l\'année 1988 de Wikidata (mise en service de la ligne C du RER). Confiance moyenne : l\'infobox donne 1988.',
    ],
    // Lens (id 15923)
    [
        'station' => ['name' => 'Lens', 'uic' => '87345025'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1860-10-06',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Lens à Ostricourt', 235337741),
        'quote' => '6 octobre 1860 : mise en service de Lens à Leforest par la Compagnie du Nord',
        'reason' => 'article de la gare (oldid 241513442, infobox : 1860 ; le chemin de fer arrive à Lens en 1860) ; article de la ligne « Ligne de Lens à Ostricourt » (oldid 235337741) : Lens à Leforest mis en service le 6 octobre 1860, Lens en est le terminus. Remplace l\'année 1927 de Wikidata (inauguration du bâtiment le 15/08/1927).',
    ],
    // Champagne-Ardenne-TGV (id 15103)
    [
        'station' => ['name' => 'Champagne-Ardenne-TGV', 'uic' => '87171926'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2007-06-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Champagne-Ardenne TGV', 241441371),
        'quote' => 'Elle est mise en service le 10 juin 2007',
        'reason' => 'article de la gare (oldid 241441371, infobox et phrase) : mise en service le 10 juin 2007 avec la première phase de la LGV Est européenne. Précise l\'année 2007 de Wikidata au jour.',
    ],
    // Plaisir-Les Clayes (id 16121)
    [
        'station' => ['name' => 'Plaisir-Les Clayes', 'uic' => '87393629'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1973-01-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Plaisir - Les Clayes', 240747926),
        'quote' => 'Elle est mise en service en janvier 1973',
        'reason' => 'article de la gare (oldid 240747926, infobox et phrase) : création de la gare en janvier 1973. Remplace l\'année 1864 déduite de l\'ouverture de la ligne.',
    ],
    // Sens (id 17392)
    [
        'station' => ['name' => 'Sens', 'uic' => '87683003'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1849-08-12',
        'precision' => 'day',
        'source_url' => $wp('Gare de Sens', 232264091),
        'quote' => 'Sa mise en service intervient le 12 août 1849, avec l\'ouverture au trafic de la section de Paris à Tonnerre',
        'reason' => 'article de la gare (oldid 232264091, infobox) et article de la ligne « Ligne de Paris-Lyon à Marseille-Saint-Charles » (oldid 241214704) : toute la section de Paris à Tonnerre est ouverte le 12 août 1849. Précise l\'année 1849 de Wikidata au jour.',
    ],
    // Libercourt (id 15938)
    [
        'station' => ['name' => 'Libercourt', 'uic' => '87345256'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1865-06-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Libercourt', 241123982),
        'quote' => 'Une station provisoire est ouverte en juin 1865 par la Compagnie des mines de Carvin',
        'reason' => 'article de la gare (oldid 241123982, infobox : 1865). Précise l\'année 1865 de Wikidata au mois.',
    ],
    // Orchies (id 15643)
    [
        'station' => ['name' => 'Orchies', 'uic' => '87286583'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1870-06-22',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Fives à Hirson', 240286509),
        'quote' => 'de Lille à Valenciennes, le 22 juin 1870',
        'reason' => 'article de la ligne « Ligne de Fives à Hirson » (oldid 240286509) : Lille - Valenciennes ouvre le 22 juin 1870 ; article de la gare (oldid 235030548) : le projet de 1867 est seulement approuvé le 5 juin 1867 et la position de la station d\'Orchies reste à fixer. Remplace l\'année 1867 de Wikidata (approbation du tracé). Confiance moyenne : l\'article de la gare ne date pas l\'ouverture.',
    ],
    // Provins (id 14901)
    [
        'station' => ['name' => 'Provins', 'uic' => '87116160'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1858-12-11',
        'precision' => 'day',
        'source_url' => $wp('Gare de Provins', 236932202),
        'quote' => 'Le chemin de fer arriva à Provins le 11 décembre 1858',
        'reason' => 'article de la gare (oldid 236932202) et article de la ligne « Ligne de Longueville à Esternay » (oldid 235342288) : l\'embranchement vers Provins est ouvert au trafic le 11 décembre 1858. Précise l\'année 1858 de Wikidata au jour.',
    ],
    // Aix-en-Provence (id 17972)
    [
        'station' => ['name' => 'Aix-en-Provence', 'uic' => '87751404'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-10-10',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Aix-en-Provence', 241049588),
        'quote' => 'La ligne de Rognac à Aix-en-Provence sera ouverte le 10 octobre 1856',
        'reason' => 'article de la gare (oldid 241049588, infobox : 1856 première gare, 1877 gare actuelle) et article de la ligne « Ligne de Rognac à Aix-en-Provence » (oldid 235989403) : ouverture à l\'exploitation le 10 octobre 1856, première gare d\'Aix. La fiche garde la date de la première gare de la ville comme pour Reims ; remplace l\'année 1877 de Wikidata (gare actuelle, relocalisée).',
    ],
    // Grenoble Universités-Gières (id 17948)
    [
        'station' => ['name' => 'Grenoble Universités-Gières', 'uic' => '87747402'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-09-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Grenoble-Universités-Gières', 235031984),
        'quote' => 'Le 15 septembre 1864, mise en service de la ligne Grenoble - Montmélian (50 km), et de la gare de « Gières-Uriage »',
        'reason' => 'article de la gare (oldid 235031984) et article de la ligne « Ligne de Grenoble à Montmélian » (oldid 239895345) : mise en service le 15 septembre 1864. Précise l\'année 1864 de Wikidata au jour.',
    ],
    // Arles (id 18012)
    [
        'station' => ['name' => 'Arles', 'uic' => '87753657'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1848-01-16',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Arles', 235030325),
        'quote' => 'Elle est bâtie en 1848 lors de la création du chemin de fer d\'Avignon à Marseille',
        'reason' => 'article de la gare d\'Arles (oldid 235030325, infobox : 1848) ; article de la gare de Marseille-Saint-Charles (oldid 241581746) : mise en exploitation publique de la ligne entre Marseille et Rognonas, via Arles, le 16 janvier 1848 (inauguration le 8 janvier). Précise l\'année 1848 de Wikidata au jour. Confiance moyenne : l\'article d\'Arles ne donne que l\'année.',
    ],
    // Montmélian (id 17839)
    [
        'station' => ['name' => 'Montmélian', 'uic' => '87741181'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-10-20',
        'precision' => 'day',
        'source_url' => $wp('Gare de Montmélian', 241354307),
        'quote' => 'Le 20 octobre 1856 est mise en service la ligne d\'Aix-les-Bains à Saint-Jean-de-Maurienne. Une station est créée à Montmélian',
        'reason' => 'article de la gare (oldid 241354307) : station créée à Montmélian à l\'ouverture de la ligne d\'Aix-les-Bains à Saint-Jean-de-Maurienne le 20 octobre 1856. Précise l\'année 1856 de Wikidata au jour.',
    ],
    // Cholet (id 16515)
    [
        'station' => ['name' => 'Cholet', 'uic' => '87484303'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-09-24',
        'precision' => 'day',
        'source_url' => $wp('Gare de Cholet', 238676072),
        'quote' => 'La section de La Possonnière à Cholet a été mise en service le 24 septembre 1866',
        'reason' => 'article de la gare (oldid 238676072) : construite pour l\'inauguration de la ligne La Possonnière - Niort ; article de la ligne « Ligne de La Possonnière à Niort » (oldid 241119292) : La Possonnière - Cholet mis en service le 24 septembre 1866 (Cholet - Niort le 26/12/1868). Remplace l\'année 1865 de Wikidata (construction).',
    ],
    // Dunkerque (id 15585)
    [
        'station' => ['name' => 'Dunkerque', 'uic' => '87281006'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1848-09-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Dunkerque', 234757520),
        'quote' => 'Le chemin de fer arrive à Dunkerque en 1848',
        'reason' => 'article de la gare (oldid 234757520, catégorie 1848) et article de la ligne « Ligne d\'Arras à Dunkerque-Locale » (oldid 239736601) : mise en service d\'Hazebrouck à Dunkerque le 1er septembre 1848. Remplace l\'année 1838 de Wikidata.',
    ],
    // Chalon-sur-Saône (id 17716)
    [
        'station' => ['name' => 'Chalon-sur-Saône', 'uic' => '87725002'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1849-09-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Chalon-sur-Saône', 233473917),
        'quote' => '1 septembre 1849 dans les chemins de fer, ancienne gare de Chalon-Ville',
        'reason' => 'article de la gare (oldid 233473917, infobox) et article de la ligne « Ligne de Paris-Lyon à Marseille-Saint-Charles » (oldid 241214704) : ouverture au public de la section Dijon - Chalon-sur-Saône le 1er septembre 1849. La fiche garde la date de la première gare de la ville (gare actuelle de 1893) comme pour Reims ; précise l\'année 1849 de Wikidata au jour.',
    ],

    // ── 39. Anomalies de gares relevées par l'audit des fermetures (gares-anomalies, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/gares-anomalies.md).

    // Pamiers - Limoux et Bram - Belvèze : le service des voyageurs est fermé le 18/04/1939 (Moulin-Neuf - Pamiers : 15/05/1939), puis
    // rétabli du 05/05/1941 au 06/05/1946 par une voiture ajoutée au train de marchandises (« trains reliant Bram à Lavelanet et
    // desservant Mirepoix ») : la fermeture définitive aux voyageurs des gares de Belvèze, Moulin-Neuf et Mirepoix est celle du 06/05/1946
    // (même cas qu'Auger-Saint-Vincent). Les sections Pamiers - Mirepoix et Belvèze - Limoux, sans train depuis le 01/06/1941, n'ont pas
    // été rouvertes : Belvèze n'est pas dans la section fermée le 18/04/1939 sans retour, elle est le terminus des trains de Bram.
    [
        'station' => ['name' => 'Belvèze', 'near' => [2.08917, 43.12133, 0.5]],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1946-05-06',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bram à Belvèze', 216790588),
        'quote' => 'Du 5 mai 1941 au 6 mai 1946 a eu lieu réouverture temporaire aux voyageurs avec des trains reliant Bram à Lavelanet et desservant Mirepoix',
        'reason' => 'le 15/05/1939 (ou le 18/04/1939) est une suppression temporaire : le service voyageurs est rétabli du 05/05/1941 au 06/05/1946 sur Bram - Belvèze - Moulin-Neuf - Lavelanet (articles de la ligne de Bram à Belvèze, oldid 216790588, de la ligne de Moulin-Neuf à Lavelanet, oldid 221071157, et de la ligne de Pamiers à Limoux, oldid 238677089) ; la fermeture définitive est celle du 06/05/1946',
    ],
    [
        'station' => ['name' => 'Moulin-Neuf', 'near' => [1.95275, 43.07808, 0.5]],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1946-05-06',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Moulin-Neuf à Lavelanet', 221071157),
        'quote' => 'Il y eut une réouverture temporaire du service voyageur du 5 mai 1941 au 6 mai 1946',
        'reason' => 'bifurcation de Lavelanet : les trains reliant Bram à Lavelanet et desservant Mirepoix (qui repartent en marche arrière jusqu\'à Moulin-Neuf, faute de plaque tournante à Mirepoix) y passent ; aucune fermeture aux voyageurs n\'était inscrite (la fiche gardait la fin du fret de 1973), la fermeture définitive est celle du 06/05/1946',
    ],
    [
        'station' => ['name' => 'Mirepoix', 'uic' => '8761545'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1946-05-06',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bram à Belvèze', 216790588),
        'quote' => 'Du 5 mai 1941 au 6 mai 1946 a eu lieu réouverture temporaire aux voyageurs avec des trains reliant Bram à Lavelanet et desservant Mirepoix',
        'reason' => 'le 15/05/1939 (fermeture du service voyageurs, article de la gare oldid 239381589) est une suppression temporaire : Mirepoix est desservie par les trains de Bram à Lavelanet du 05/05/1941 au 06/05/1946, date de la fermeture définitive',
    ],

    // Bourogne : gare de la ligne de Belfort à Delle, fermée aux voyageurs en 1992 avec toute la ligne. La réouverture du 09/12/2018 ne
    // concerne que Danjoutin, Meroux, Morvillars, Grandvillars, Joncherey et Delle : Bourogne, sans halte neuve, reste fermée. La date
    // de 2010 était déduite (fin d'un trafic de déchets jusqu'en 2007) et ne repose sur rien.
    [
        'station' => ['name' => 'Bourogne', 'near' => [6.92612, 47.56196, 0.5]],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1992-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Belfort à Delle', 241021512),
        'quote' => 'abandon du trafic voyageurs en 1992',
        'reason' => 'la gare reste fermée à la réouverture de 2018 (la liste des arrêts de la ligne rouverte ne la compte pas) ; la date de 2010 était déduite, jour de 1992 non trouvé',
    ],

    // CdN : arrêt de la ligne à voie métrique de Lens à Frévent (Chemins de fer économiques du Nord, PK 53,74), fermée le 31/05/1948 ; la
    // date de 1955 venait d'une ligne de Lens voisine sans rapport.
    [
        'station' => ['name' => 'CdN', 'near' => [2.82814, 50.42769, 0.5]],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1948-05-31',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer Lens - Frévent', 228842949),
        'quote' => 'fermeture le 31 mai 1948',
        'reason' => 'arrêt du schéma du chemin de fer Lens - Frévent (PK 53,74, icône d\'arrêt supprimé) ; fin de la ligne le 31/05/1948 ; la date de 1955 venait d\'une autre ligne de Lens',
    ],

    // Passerelle Atlantis - Vilmorin : la passerelle couverte (« le lien », OSM building=train_station, bridge=yes) du pôle de Massy -
    // Palaiseau, ouverte le 31/01/2012 pour relier les gares RATP, SNCF et gares routières ; ce n'est pas une gare de la ligne de Sceaux.
    [
        'station' => ['name' => 'Passerelle Atlantis - Vilmorin', 'osm' => 'w417349802'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Gare de Massy - Palaiseau', 240526522),
        'quote' => 'la construction de la nouvelle passerelle, le « lien » en raison du lien qu\'il créera entre les deux parties de la ville',
        'reason' => 'passerelle piétonne couverte du pôle de gares de Massy - Palaiseau (objet OSM bridge=yes, hall principal de la passerelle), pas une gare : la gare est celle de Massy - Palaiseau',
    ],

    // ── 40. Ouvertures des gares encore ouvertes datées à l'année par Wikidata ou déduites de la ligne, quatrième relecture contre
    // l'article de la gare (révision citée) et la chronologie de l'article de la ligne : 28 gares des 100 suivantes par fréquentation
    // (de Saint-Sulpice 0,76 M à Boulogne-Tintelleries 0,39 M ; audit-gares-4, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/audit-gares-4.md). Lorsque la gare a déménagé, la fiche garde la date de la première gare
    // de la ville, comme pour Reims (section 37). Les 14 cas douteux restent à décider (Thann-Saint-Jacques, Oissel, Lille-CHR, Orthez,
    // Montaigu-Vendée, Chaumont-en-Vexin, Brignais, La Loupe, Saint-Mammès, Dombasle-sur-Meurthe, Villeneuve-Loubet-Plage,
    // Noisy-le-Roi TT, Lisière Pereire, Rang-du-Fliers-Verton).

    // St-Sulpice (id 17211)
    [
        'station' => ['name' => 'St-Sulpice', 'uic' => '87615344'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-10-24',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Sulpice (Tarn)', 241055734),
        'quote' => 'La gare de « Saint-Sulpice » est mise en service le 24 octobre 1864 par la Compagnie du chemin de fer de Paris à Orléans (PO), lorsqu\'elle ouvre à l\'exploitation la ligne de Toulouse à Lexos',
        'reason' => 'article de la gare (oldid 241055734) et article de la ligne « Ligne de Brive-la-Gaillarde à Toulouse-Matabiau via Capdenac » (oldid 240395777) : « 24 octobre 1864, ouverture de la ligne de Lexos à Toulouse ». Remplace le 11/04/1925 d\'OpenHistoricalMap (bâtiment ou embranchement de Montauban, ouvert en 1884).',
    ],
    // Méry (Val-d'Oise) (id 15571)
    [
        'station' => ['name' => 'Méry (Val-d\'Oise)', 'uic' => '87276667'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-08-26',
        'precision' => 'day',
        'source_url' => $wp('Gare de Méry-sur-Oise', 240487005),
        'quote' => 'La ligne est ouverte, d\'abord à voie unique, le 26 août 1876 au public et à la « grande vitesse »',
        'reason' => 'article de la ligne « Ligne d\'Ermont - Eaubonne à Valmondois » (oldid 240238013) : ouverte au public le 26 août 1876 (petite vitesse le 02/11/1876) ; l\'article de la gare (oldid 240487005) ne donne que l\'année 1876. Précise l\'année 1876 de Wikidata au jour, confiance moyenne.',
    ],
    // St-Ouen-l'Aumône-Église (id 15991)
    [
        'station' => ['name' => 'St-Ouen-l\'Aumône-Église', 'uic' => '87381426'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1877-10-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Ouen-l\'Aumône-Quartier de l\'Église', 226883185),
        'quote' => 'Le tronçon est ouvert le 15 octobre 1877',
        'reason' => 'article de la ligne « Ligne d\'Achères à Pontoise » (oldid 235184812) : tronçon ouvert le 15 octobre 1877 ; l\'article de la gare (oldid 226883185) ne donne aucune date. Précise l\'année 1877 déduite de la ligne au jour, confiance moyenne.',
    ],
    // La Ciotat (id 17985)
    [
        'station' => ['name' => 'La Ciotat', 'uic' => '87751784'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-05-03',
        'precision' => 'day',
        'source_url' => $wp('Gare de La Ciotat - Ceyreste', 240110173),
        'quote' => 'Les deux voies sont en service le 24 avril, pour les transports militaires, puis le 3 mai, pour un début d\'exploitation commerciale qui devient régulière le 28 mai 1859. Le trajet Marseille - Toulon nécessite deux heures trente, avec une desserte des douze gares du parcours',
        'reason' => 'article de la ligne « Ligne de Marseille-Saint-Charles à Vintimille (frontière) » (oldid 240736526) : exploitation commerciale de Marseille à Toulon le 3 mai 1859 avec douze gares, dont La Ciotat ; l\'article de la gare (oldid 240110173) ne donne aucune date (le 1887 de Wikidata est celui du bâtiment). Confiance moyenne.',
    ],
    // Issoire (id 17805)
    [
        'station' => ['name' => 'Issoire', 'uic' => '87734202'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1855-07-02',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Issoire', 237601240),
        'quote' => 'Elle a été prolongée jusqu\'à Issoire le 2 juillet',
        'reason' => 'article de la gare (oldid 237601240) : ligne de Saint-Germain-des-Fossés à Clermont-Ferrand construite en 1855 puis prolongée jusqu\'à Issoire le 2 juillet ; article de la ligne « Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac » (oldid 240237870) : « 2 juillet 1855 : Clermont-Ferrand - Issoire ». Remplace l\'année 1856 de Wikidata.',
    ],
    // Anse (id 17634)
    [
        'station' => ['name' => 'Anse', 'uic' => '87721324'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-07-10',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Anse', 238104738),
        'quote' => 'La « station d\'Anse » est mise en service le 10 juillet 1854 par la Compagnie du chemin de fer de Paris à Lyon (PL), lorsqu\'elle ouvre à l\'exploitation la section de Chalon (Saint-Côme) à Lyon (Vaise)',
        'reason' => 'article de la gare (oldid 238104738, section « Première gare : 1854-2006 ») et article de la ligne « Ligne de Paris-Lyon à Marseille-Saint-Charles » (oldid 241214704) : « la section complémentaire, reliant Chalon-sur-Saône à Lyon-Vaise, ouvrit trois ans plus tard, le 10 juillet 1854 ». La gare actuelle (2006) remplace la première : la fiche garde la date de la première gare de la ville (règle de Reims, section 37). Remplace décembre 2006 de Wikidata.',
    ],
    // Longueau (id 15738)
    [
        'station' => ['name' => 'Longueau', 'uic' => '87313049'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1853-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Longueau', 241516544),
        'quote' => 'Une station est créée à Longueau vers 1853, et se situe sur le raccordement précité (au nord de la gare actuelle)',
        'reason' => 'article de la gare (oldid 241516544) : la ligne Paris - Lille passe à Longueau dès 1846 (article de la ligne, oldid 241487697) mais la station n\'est créée que vers 1853 sur le raccordement vers Amiens ; la gare actuelle est de 1883 puis 1994. Remplace l\'année 1846 de Wikidata (ouverture de la ligne). Date approximative (« vers 1853 »), confiance moyenne.',
    ],
    // Cahors (id 17165)
    [
        'station' => ['name' => 'Cahors', 'uic' => '87613000'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-12-20',
        'precision' => 'day',
        'source_url' => $wp('Gare de Cahors', 240125009),
        'quote' => 'La première ligne à atteindre Cahors fut la ligne de Monsempron-Libos à Cahors de la Compagnie du chemin de fer de Paris à Orléans (PO), le 20 décembre 1869',
        'reason' => 'article de la gare (oldid 240125009, infobox 1869 et histoire) : la gare, construite en 1868-1869, est inaugurée comme terminus de la ligne Monsempron-Libos - Cahors le 20 décembre 1869 ; article de la ligne « Ligne de Monsempron-Libos à Cahors » (oldid 239879682) : mise en service 1869. Précise l\'année 1869 de Wikidata au jour.',
    ],
    // Hénin-Beaumont (id 15931)
    [
        'station' => ['name' => 'Hénin-Beaumont', 'uic' => '87345157'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1860-10-06',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Hénin-Beaumont', 238682952),
        'quote' => 'La mise en service du tronçon de Lens à Leforest a lieu le 6 octobre 1860',
        'reason' => 'article de la ligne « Ligne de Lens à Ostricourt » (oldid 235337741) : Lens - Leforest mis en service le 6 octobre 1860, premier tronçon du chemin de fer des houillères, qui passe par Hénin-Liétard ; l\'article de la gare (oldid 238682952) ne donne aucune date. Précise l\'année 1860 déduite de la ligne au jour, confiance moyenne.',
    ],
    // Osny (id 15977)
    [
        'station' => ['name' => 'Osny', 'uic' => '87381145'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1891-10-18',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Osny', 233895858),
        'quote' => 'Une halte est finalement ouverte le 18 octobre 1891',
        'reason' => 'article de la gare (oldid 233895858, infobox et histoire) : la liaison Pontoise - Gisors est opérationnelle dès 1868 mais il n\'existe à cette date aucun point d\'arrêt à Osny ; la halte ouvre le 18/10/1891 (remplacée en 1976 par une gare). Remplace le 10/10/1868 déduit de la ligne Saint-Denis - Dieppe (oldid 239854982).',
    ],
    // Forbach (id 15314)
    [
        'station' => ['name' => 'Forbach', 'uic' => '87193003'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1851-11-16',
        'precision' => 'day',
        'source_url' => $wp('Gare de Forbach', 241364327),
        'quote' => 'Elle devient une gare de passage le 16 novembre lors de la mise en service du tronçon suivant de Saint-Avold à Forbach',
        'reason' => 'article de la gare de Saint-Avold (oldid 241364462) : le tronçon Saint-Avold - Forbach est mis en service le 16 novembre (1851, après le 24 juillet 1851 de Metz - Saint-Avold) ; article de la gare de Forbach (oldid 241364327, infobox 1851) : « la mise en service de la gare a lieu en 1851, avec l\'ouverture du tronçon de Saint-Avold à Forbach » ; article de la ligne « Ligne de Rémilly à Stiring-Wendel » (oldid 235298902) : Rémilly - Forbach en 1851. Précise l\'année 1851 de Wikidata au jour, confiance moyenne (le jour vient de l\'article de Saint-Avold).',
    ],
    // Champagne-sur-Seine (id 17385)
    [
        'station' => ['name' => 'Champagne-sur-Seine', 'uic' => '87682450'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-06-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Champagne-sur-Seine', 229220897),
        'quote' => 'À l\'ouverture de la ligne en 1897, treize nouvelles gares furent créées en plus des gares préexistantes de Corbeil, Melun et Montereau',
        'reason' => 'article de la ligne « Ligne de Corbeil-Essonnes à Montereau » (oldid 241117450) : mise en service le 1er juin 1897, Champagne-sur-Seine est l\'une des treize gares créées à l\'ouverture ; l\'article de la gare (oldid 229220897) ne donne aucune date. Précise l\'année 1897 déduite de la ligne au jour.',
    ],
    // Nogent-sur-Seine (id 14941)
    [
        'station' => ['name' => 'Nogent-sur-Seine', 'uic' => '87118190'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1848-04-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Nogent-sur-Seine', 241434058),
        'quote' => 'La Compagnie du chemin de fer de Montereau à Troyes inaugure la ligne le 6 avril 1848, et la met en service ainsi que la gare de Nogent le 10 avril 1848',
        'reason' => 'article de la gare (oldid 241434058, infobox et histoire) ; la ligne de Montereau à Troyes ouverte en 1848 est citée par l\'article « Ligne de Paris-Est à Mulhouse-Ville » (oldid 235342496). Précise l\'année 1848 de Wikidata au jour d\'ouverture au public (et non à l\'inauguration du 06/04).',
    ],
    // Grand-Bourg (id 17346)
    [
        'station' => ['name' => 'Grand-Bourg', 'uic' => '87681353'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1946-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Grand Bourg', 236226828),
        'quote' => 'Cette gare fut créée en 1971 pour succéder à la halte de Grand-Bourg créée en 1946, et qui desservait le quartier du même nom',
        'reason' => 'article de la gare (oldid 236226828) : la halte de Grand-Bourg date de 1946, la gare actuelle de 1971 lui succède ; la fiche garde la date de la première halte du quartier (règle de Reims, section 37). Remplace l\'année 1840 de Wikidata, qui est l\'ouverture de la ligne Villeneuve-Saint-Georges - Montargis (oldid 236077985), où la halte n\'existait pas.',
    ],
    // Ponthierry-Pringy (id 17390)
    [
        'station' => ['name' => 'Ponthierry-Pringy', 'uic' => '87682526'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-06-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Ponthierry - Pringy', 235572395),
        'quote' => 'La gare de Ponthierry - Pringy a été ouverte au public en 1897, dès l\'inauguration de la ligne de Corbeil à Melun et à Montereau',
        'reason' => 'article de la gare (oldid 235572395, infobox 1897) et article de la ligne « Ligne de Corbeil-Essonnes à Montereau » (oldid 241117450) : « elle a été mise en service le 1 juin 1897 » avec treize gares nouvelles dont Ponthierry - Pringy. Précise l\'année 1897 de Wikidata au jour.',
    ],
    // Romilly-sur-Seine (id 14939)
    [
        'station' => ['name' => 'Romilly-sur-Seine', 'uic' => '87118158'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1848-04-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Romilly-sur-Seine', 241434141),
        'quote' => 'La Compagnie du chemin de fer de Montereau à Troyes inaugure cette ligne le 6 avril 1848, et la met en service, ainsi que la station de Romilly, le 10 avril 1848',
        'reason' => 'article de la gare (oldid 241434141) ; la ligne de Montereau à Troyes ouverte en 1848 est citée par l\'article « Ligne de Paris-Est à Mulhouse-Ville » (oldid 235342496). Précise l\'année 1848 de Wikidata au jour (la gare actuelle date de 1888, la première gare de la ville est gardée).',
    ],
    // Voreppe (id 17946)
    [
        'station' => ['name' => 'Voreppe', 'uic' => '87747337'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-07-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Voreppe', 230262770),
        'quote' => 'Le tronçon de Saint-Rambert à Rives a été ouvert le 5 novembre 1856, celui de Rives à Piquepierre le 10 juillet 1857',
        'reason' => 'article de la ligne « Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble) » (oldid 241143131) et article « Ligne de Saint-Rambert-d\'Albon à Rives » (oldid 235342752) : Rives - Piquepierre (Voreppe) ouvert le 10 juillet 1857 ; l\'article de la gare (oldid 230262770) ne donne aucune date. Remplace le 12/07/1857 déduit, qu\'aucune des deux sources ne confirme, confiance moyenne.',
    ],
    // Frépillon (id 15570)
    [
        'station' => ['name' => 'Frépillon', 'uic' => '87276659'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-08-26',
        'precision' => 'day',
        'source_url' => $wp('Gare de Frépillon', 238312997),
        'quote' => 'La ligne est ouverte, d\'abord à voie unique, le 26 août 1876 au public et à la « grande vitesse »',
        'reason' => 'article de la ligne « Ligne d\'Ermont - Eaubonne à Valmondois » (oldid 240238013) : ouverte au public le 26 août 1876 (petite vitesse le 02/11/1876) ; l\'article de la gare (oldid 238312997) ne donne que l\'année 1876. Précise l\'année 1876 de Wikidata au jour, confiance moyenne.',
    ],
    // Mériel (id 15572)
    [
        'station' => ['name' => 'Mériel', 'uic' => '87276675'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-08-26',
        'precision' => 'day',
        'source_url' => $wp('Gare de Mériel', 235033415),
        'quote' => 'La ligne est ouverte, d\'abord à voie unique, le 26 août 1876 au public et à la « grande vitesse »',
        'reason' => 'article de la ligne « Ligne d\'Ermont - Eaubonne à Valmondois » (oldid 240238013) : ouverte au public le 26 août 1876 (petite vitesse le 02/11/1876) ; l\'article de la gare (oldid 235033415) ne donne que l\'année 1876. Précise l\'année 1876 de Wikidata au jour, confiance moyenne.',
    ],
    // Igny (id 16115)
    [
        'station' => ['name' => 'Igny', 'uic' => '87393561'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Igny', 236064922),
        'quote' => 'Le 1 mai 1883, le trafic de la ligne de Grande Ceinture ouvre aux voyageurs sur la section de Versailles-Chantiers à Savigny-sur-Orge. Une halte est créée à Igny',
        'reason' => 'article de la gare (oldid 236064922) : halte créée à Igny à l\'ouverture aux voyageurs de la section Versailles-Chantiers - Savigny-sur-Orge le 01/05/1883 (gare en 1889) ; article de la ligne « Ligne de la Grande Ceinture de Paris » (oldid 238288627) : tronçon Juvisy - Versailles ouvert le 1er mai 1883. Remplace le 1877 déduit de la ligne.',
    ],
    // Vauboyen (id 16112)
    [
        'station' => ['name' => 'Vauboyen', 'uic' => '87393538'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Vauboyen', 235034843),
        'quote' => 'Le 1 mai 1883, le trafic de la ligne de Grande Ceinture ouvre aux voyageurs sur la section de Versailles-Chantiers à Savigny-sur-Orge. La gare de Vauboyen est créée à cette occasion',
        'reason' => 'article de la gare (oldid 235034843) et article de la ligne « Ligne de la Grande Ceinture de Paris » (oldid 238288627) : tronçon Juvisy - Versailles ouvert le 1er mai 1883. Remplace le 1877 déduit de la ligne.',
    ],
    // Saint-Jean-de-Maurienne (id 17865)
    [
        'station' => ['name' => 'Saint-Jean-de-Maurienne', 'uic' => '87742320'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-10-20',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Jean-de-Maurienne - Vallée de l\'Arvan', 238203540),
        'quote' => 'La « station de Saint-Jean de Maurienne » est mise en service le 20 octobre 1856 par la Compagnie du chemin de fer Victor-Emmanuel, lorsqu\'elle ouvre à l\'exploitation la section de ligne entre Aix-les-Bains et Saint-Jean-de-Maurienne',
        'reason' => 'article de la gare (oldid 238203540, infobox) et article de la ligne « Ligne de Culoz à Modane (frontière) » (oldid 238204405) : « 20 octobre 1856 : d\'Aix-les-Bains à Saint-Jean-de-Maurienne ». La gare actuelle (Vallée de l\'Arvan, juin 2022 d\'après OSM) remplace la première : la fiche garde la date de la première gare de la ville.',
    ],
    // Bièvres (id 16113)
    [
        'station' => ['name' => 'Bièvres', 'uic' => '87393546'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bièvres', 240958222),
        'quote' => 'Le 1 mai 1883, le trafic de la ligne de Grande Ceinture ouvre aux voyageurs sur la section de Versailles-Chantiers à Savigny-sur-Orge',
        'reason' => 'article de la gare (oldid 240958222, infobox : mise en service 1883) et article de la ligne « Ligne de la Grande Ceinture de Paris » (oldid 238288627) : tronçon Juvisy - Versailles ouvert le 1er mai 1883. Remplace le 1877 déduit de la ligne.',
    ],
    // Nointel-Mours (id 15578)
    [
        'station' => ['name' => 'Nointel-Mours', 'uic' => '87276758'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1877-04-05',
        'precision' => 'day',
        'source_url' => $wp('Gare de Nointel - Mours', 229171909),
        'quote' => 'd\'Épinay - Villetaneuse à Persan - Beaumont, le 5 avril 1877',
        'reason' => 'article de la ligne « Ligne d\'Épinay - Villetaneuse au Tréport - Mers » (oldid 241187520) : la section Épinay - Villetaneuse - Persan - Beaumont, où se trouve Nointel - Mours, ouvre le 5 avril 1877 ; l\'article de la gare (oldid 229171909) ne donne que l\'année 1877. Précise l\'année 1877 de Wikidata au jour, confiance moyenne.',
    ],
    // Marles-en-Brie (id 14906)
    [
        'station' => ['name' => 'Marles-en-Brie', 'uic' => '87116228'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1861-02-02',
        'precision' => 'day',
        'source_url' => $wp('Gare de Marles-en-Brie', 235911887),
        'quote' => 'La Compagnie ouvre la ligne de Gretz-Armainvilliers à Mortcerf () le 2 février 1861',
        'reason' => 'article de la ligne « Ligne de Gretz-Armainvilliers à Sézanne » (oldid 240817135) : Gretz-Armainvilliers - Mortcerf ouvert le 2 février 1861 ; article de la gare (oldid 235911887, infobox : mise en service 1861). Marles-en-Brie est sur cette section, la section Mortcerf - Coulommiers date du 2 avril 1863. Remplace le 01/06/1893 déduit.',
    ],
    // Vic-le-Comte (id 17803)
    [
        'station' => ['name' => 'Vic-le-Comte', 'uic' => '87734186'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1855-07-02',
        'precision' => 'day',
        'source_url' => $wp('Gare de Vic-le-Comte', 239518229),
        'quote' => '2 juillet 1855 : Clermont-Ferrand - Issoire',
        'reason' => 'article de la ligne « Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac » (oldid 240237870) : la section Clermont-Ferrand - Issoire, où se trouve Vic-le-Comte, ouvre le 2 juillet 1855 ; l\'article de la gare (oldid 239518229) ne donne aucune date. Remplace l\'année 1856 de Wikidata, confiance moyenne.',
    ],
    // Phalempin (id 15636)
    [
        'station' => ['name' => 'Phalempin', 'uic' => '87286500'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1865-06-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Phalempin', 235033767),
        'quote' => 'En juin 1865, la compagnie des mines de Carvin édifie à l\'emplacement actuel de la gare une station provisoire pour le service des voyageurs',
        'reason' => 'article de la gare (oldid 235033767, infobox 1865) : la gare n\'existait pas à l\'ouverture de la ligne Paris - Lille en 1846 ; station provisoire des mines de Carvin en juin 1865. Précise l\'année 1865 de Wikidata au mois.',
    ],
    // Ste-Pazanne (id 16469)
    [
        'station' => ['name' => 'Ste-Pazanne', 'uic' => '87481226'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-09-11',
        'precision' => 'day',
        'source_url' => $wp('Gare de Sainte-Pazanne', 237406960),
        'quote' => '11 septembre 1875 : mise en service de Nantes (au Pont-Rousseau) à Sainte-Pazanne',
        'reason' => 'article de la ligne « Ligne de Nantes-État à La Roche-sur-Yon par Sainte-Pazanne » (oldid 241345071) : Nantes - Sainte-Pazanne mis en service le 11 septembre 1875, Sainte-Pazanne - Machecoul seulement le 25 mars 1878 (date prise à tort pour la gare) ; l\'article de la gare (oldid 237406960) ne donne aucune date.',
    ],

    // ── Doublons de fiches (relecture du 23/11/2026, rapport aprem-2111/gares-doublons.md) ────────────────

    // Monteux-les-Escampades (id 18283) : enregistrement désaffecté de la liste SNCF (UIC 87025098, PK 8,900 de la ligne 927000, ni voyageurs
    // ni fret), à 2,2 km de la gare de Monteux. Le schéma de la ligne ne compte que Althen-les-Paluds (PK 6,943) puis Monteux (PK 11,260), et
    // l'article de la gare de Monteux ne cite aucun point d'arrêt des Escampades : les dates de Monteux (1863, 1938, 2015) lui venaient de la ligne.
    [
        'station' => ['name' => 'Monteux-les-Escampades', 'uic' => '87025098'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Ligne de Sorgues - Châteauneuf-du-Pape à Carpentras', 241491038),
        'quote' => 'Monteux',
        'reason' => 'enregistrement SNCF désaffecté (PK 8,900), absent du schéma de la ligne qui ne compte que Althen-les-Paluds puis Monteux, à 2,2 km de la gare de Monteux (oldid 241561892, aucune mention des Escampades) ; les dates de Monteux lui étaient prêtées par la ligne',
    ],
    // Aniche Gare (id 22933) : bâtiment extrait du cadastre (OSM w96473701 : building=yes, disused:railway=station, old_name=Aniche Gare,
    // mise à jour 2010), à 1 093 m de la gare d'Aniche (infobox 50,3288 / 3,2527 ; objet OSM à 50,3367 / 3,2619, vérifié par l'API OSM le
    // 23/11/2026). L'article ne connaît qu'une gare d'Aniche (bâtiment voyageurs démoli en octobre 2010), qui est la fiche 15952.
    [
        'station' => ['name' => 'Aniche Gare', 'osm' => 'w96473701'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Gare d\'Aniche', 235030291),
        'quote' => 'Aniche',
        'reason' => 'bâtiment cadastral nommé d\'après la gare d\'Aniche (OSM w96473701, building=yes, extraction du cadastre 2010), à 1 093 m de la gare réelle : doublon de la fiche Aniche, dont l\'article ne cite qu\'une gare',
    ],
    // Limours-État (id 21583, Q106409717) : second élément Wikidata de la même gare (ligne d'Ouest-Ceinture à Chartres, 111 m de l'autre, même
    // PK de la ligne), sans article ; l'article « Gare de Limours-État » ne décrit qu'une gare, construite en 1922, ouverte en 1930, fermée en 1939.
    // La fiche conservée est celle de Q107205111, qui porte l'article.
    [
        'station' => ['name' => 'Limours-État', 'wikidata' => 'Q106409717'],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $wp('Gare de Limours-État', 204050069),
        'quote' => 'La gare de Limours-État est une ancienne gare ferroviaire française de la ligne d\'Ouest-Ceinture à Chartres.',
        'reason' => 'doublon Wikidata de la gare de Limours-État (Q107205111, qui porte l\'article) : même nom, même ligne, même PK, à 111 m ; l\'article ne décrit qu\'une gare',
    ],

    // Saint-Samson (id 23805) : arrêt de la ligne de Dinan à Dinard-Saint-Énogat (PK 4,207). Voyageurs fermés le 27/09/1986, marchandises le
    // 01/02/1988 : la fermeture de 1988 déduite était celle du fret.
    [
        'station' => ['name' => 'Saint-Samson', 'near' => [-2.02880, 48.48995, 0.5]],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1986-09-27',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Dinan à Dinard-Saint-Énogat', 238645019),
        'quote' => 'Fermeture voyageurs le 27 septembre 1986.',
        'reason' => 'le schéma de la ligne place Saint-Samson au PK 4,207 ; la ligne ferme aux voyageurs le 27/09/1986 et aux marchandises le 01/02/1988 (date déduite à tort comme fermeture de la gare)',
    ],

    // Fusions de fiches (action « merge », rapport aprem-2111/fusion-fiches.md) : deux fiches pour une gare que le schéma de sa ligne
    // ne compte qu'une fois. Les règles du rapprochement les gardaient distinctes (ancien bâtiment OSM à plus de 150 m de la gare SNCF) ;
    // exclure l'une aurait perdu son UIC ou ses sources. La fiche gardée est la fiche SNCF (UIC, slug sans suffixe de département).

    // Pleine-Fougères (16445 SNCF, UIC 87478255, PK 121,099 de la ligne 415000 ; 22882 nœud OSM n2514130142 was:railway=station et
    // source argentanwebferro). Le tableau des gares de la ligne de Caen à Rennes donne 48° 31′ 47″ N, 1° 33′ 58″ O : le nœud OSM est à 6 m,
    // le point SNCF à 164 m (les deux à 159 m l'un de l'autre, sur la voie) ; position de la fiche absorbée.
    [
        'station' => ['name' => 'Pleine-Fougères', 'uic' => '87478255'],
        'action' => 'merge',
        'absorbed' => ['name' => 'Pleine-Fougères', 'osm' => 'n2514130142'],
        'position' => 'absorbed',
        'source_url' => $wp('Ligne de Caen à Rennes', 237081548),
        'quote' => 'Pleine-Fougères (fermée)',
        'reason' => 'une seule gare de Pleine-Fougères sur la ligne de Caen à Rennes (schéma : PK 121,099 ; tableau des gares : 48° 31′ 47″ N, 1° 33′ 58″ O) : la fiche OSM n2514130142 (was:railway=station, à 6 m de ces coordonnées) est la fiche SNCF 87478255 (à 164 m), position du nœud OSM',
    ],
    // Condé-sur-Vire (16293 SNCF, UIC 87447177, PK 11,650 de la ligne 414000 ; 22996 nœud OSM n14143415501 razed:railway=station, schéma
    // Wikipédia et lignes-oubliees au même PK, 169 m). Le schéma de la ligne de Saint-Lô à Guilberville ne compte qu'une gare de Condé-sur-Vire.
    // Aucune source citable ne donne de coordonnées (ni article de la gare, ni tableau) : position SNCF gardée.
    [
        'station' => ['name' => 'Condé-sur-Vire', 'uic' => '87447177'],
        'action' => 'merge',
        'absorbed' => ['name' => 'Condé-sur-Vire', 'osm' => 'n14143415501'],
        'position' => 'kept',
        'source_url' => $wp('Ligne de Saint-Lô à Guilberville', 235911791),
        'quote' => 'Condé-sur-Vire',
        'reason' => 'une seule gare de Condé-sur-Vire au schéma de la ligne de Saint-Lô à Guilberville, au PK 11,650 de la fiche SNCF 87447177 comme de la liste lignes-oubliees : l\'ancienne gare rasée d\'OSM (n14143415501, à 169 m) est la même gare ; aucune coordonnée sourcée ne départage les positions, celle de la SNCF est gardée',
    ],

    // ── 40 bis. Doublons de fiches de gares, deuxième relecture (23/11/2026, rapport storage/app/reports/agents/aprem-2111/doublons-2.md) ──
    // Dix-sept fusions (« merge », rejouées par vff:consolidate-stations) : une seule gare au schéma ou à l'article, deux fiches (SNCF
    // contre nœud OSM, bâtiment du cadastre ou second élément Wikidata sans article). Chaque raison dit quelle position est gardée.
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], $g[1] => $g[2]],
        'action' => 'merge',
        'absorbed' => ['name' => $g[3], $g[4] => $g[5]],
        'position' => $g[6],
        'source_url' => $g[7],
        'quote' => $g[8],
        'reason' => $g[9],
    ], [
        ['Saint-Gérand', 'uic', '87476564', 'Saint-Gérand', 'osm', 'n14039112297', 'kept', $wp('Gare de Saint-Gérand', 235456841),
            'La gare de Saint-Gérand est une ancienne gare ferroviaire française de la ligne de Saint-Brieuc à Pontivy',
            'une seule gare de Saint-Gérand (article de la gare : infobox 48,116492 N, 2,882484 O ; schéma de la ligne de Saint-Brieuc à Pontivy, oldid 239795109 : PK 537,128) : la fiche SNCF 87476564 est à 9 m de ces coordonnées, le nœud OSM n14039112297 (à 307 m) est la même gare, position SNCF gardée'],
        ['Banyuls-dels-Aspres', 'uic', '87784322', 'Banyuls-dels-Aspres', 'osm', 'n1101773632', 'absorbed', $wp('Gare de Banyuls-dels-Aspres', 217349138),
            'Établie à 72 mètres d\'altitude, la gare de Banyuls-dels-Aspres est située au point kilométrique (PK) 491,054 de la ligne d\'Elne à Arles-sur-Tech',
            'une seule gare de Banyuls-dels-Aspres (article de la gare : PK 491,054, infobox 42,557511 N, 2,869618 E) : le nœud OSM n1101773632 est à 2 m de ces coordonnées et au PK 490,993, le point SNCF 87784322 à 782 m (PK 490,277) ; position du nœud OSM'],
        ['Tôt', 'wikidata', 'Q3126265', 'Tôt', 'osm', 'n1825991359', 'absorbed', $wp('Halte du Tôt', 183076551),
            'la halte du Tôt était située au point kilométrique (PK) 354,300 de la ligne de Carentan à Carteret, entre les gares de Barneville et de Carteret.',
            'une seule halte du Tôt sur la ligne de Carentan à Carteret (PK 354,300) : le nœud OSM n1825991359 (Train touristique du Cotentin) est au PK 354,164, les coordonnées de l\'article (portées par l\'élément Wikidata) au PK 353,648 et à 902 m de lui ; position du nœud OSM, confiance moyenne'],
        ['Marigny-lès-Usages', 'uic', '87543702', 'Ancienne gare Marigny les Usages', 'osm', 'n6286442726', 'kept', $wp('Ligne des Aubrais - Orléans à Malesherbes', 239167115),
            'Marigny-les-Usages',
            'une seule gare de Marigny-les-Usages au schéma de la ligne des Aubrais - Orléans à Malesherbes (PK 126,466, celui de la fiche SNCF 87543702) : le nœud OSM n6286442726 « Ancienne gare Marigny les Usages » (opérateur : la mairie, à 210 m) est l\'ancien bâtiment de cette gare ; aucune coordonnée sourcée, position SNCF gardée'],
        ['Jullianges', 'uic', '87726620', 'Jullianges', 'osm', 'n13934883233', 'kept', $wp('Ligne de Bonson à Sembadel', 235341794),
            'Jullianges',
            'une seule gare de Jullianges au schéma de la ligne de Bonson à Sembadel (PK 58,240, celui de la fiche SNCF 87726620) ; l\'article de la commune (oldid 240386160) montre « l\'ancienne gare de Jullianges, située sur le chemin de fer du Haut Forez » : le nœud OSM n13934883233 du Haut-Forez (à 211 m) et les nœuds OHM de la même fiche sont la même gare ; aucune coordonnée sourcée, position SNCF gardée'],
        ['Aunay-Tréon', 'uic', '87393678', 'Aunay - Tréon', 'osm', 'n816855158', 'kept', $wp('Ligne de Chartres à Dreux', 241466880),
            'Aunay - Tréon',
            'une seule gare d\'Aunay - Tréon au schéma de la ligne de Chartres à Dreux (PK 34,682, celui de la fiche SNCF 87393678, 172 m après l\'embranchement du silo du PK 34,510) : le bâtiment du cadastre 2010 n816855158 (à 447 m, projeté au PK 34,187, avant l\'embranchement) n\'est pas à l\'emplacement de la gare, position SNCF gardée'],
        ['Issigeac', 'wikidata', 'Q139796738', 'Issigeac', 'osm', 'n13828769041', 'absorbed', 'https://www.wikidata.org/wiki/Q139796738',
            'ancienne gare de Dordogne',
            'le nœud OSM n13828769041 porte wikidata=Q139796738 : c\'est le même élément que la fiche Wikidata d\'Issigeac. Les coordonnées de cet élément (44,770379 N, 0,562508 E) sont identiques à celles de Conne-Saint Cernin (Q139796677, 1 m) et à 5,5 km du nœud OSM : coordonnées recopiées par erreur, position du nœud OSM'],
        ['Esternay', 'uic', '87116483', 'Esternay', 'osm', 'n2422110816', 'kept', $wp('Gare d\'Esternay', 240824878),
            'La gare était une étoile ferroviaire avec le passage de la ligne de Mézy à Romilly-sur-Seine aujourd\'hui fermée et désaffectée et l\'embranchement de la ligne de Longueville à Esternay.',
            'une seule gare d\'Esternay, étoile des lignes de Gretz-Armainvilliers à Sézanne (PK 115,500), de Mézy à Romilly-sur-Seine et de Longueville à Esternay (article de la gare, infobox 48,730968 N, 3,561819 E ; articles des deux autres lignes, oldid 238323255 et 240817135) : le point SNCF 87116483 est à 21 m de ces coordonnées, la halte OSM n2422110816 (disused:railway=halt, à 219 m) est la même gare, position SNCF gardée'],
        ['Échiré', 'uic', '87485680', 'Échiré - Saint-Gelais', 'osm', 'w74192930', 'kept', $wp('Schéma de la ligne de Chartres à Bordeaux-Saint-Jean', 234064576),
            'Échiré',
            'une seule gare d\'Échiré (Échiré - Saint-Gelais) au schéma de la ligne de Chartres à Bordeaux-Saint-Jean (PK 405,566, celui de la fiche SNCF 87485680) ; l\'article de la gare de Cherveux (oldid 170615044) la nomme « Échiré - Saint-Gelais » : le bâtiment du cadastre 2010 w74192930 (à 201 m) est la même gare ; aucune coordonnée sourcée, position SNCF gardée'],
        ['Brens-Virignin', 'uic', '87741561', 'Brens - Virignin', 'wikidata', 'Q114571608', 'kept', $wp('Ligne de Pressins à Virieu-le-Grand', 240450400),
            'Brens - Virignin',
            'une seule gare de Brens - Virignin au schéma de la ligne de Pressins à Virieu-le-Grand (PK 100,404, celui de la fiche SNCF 87741561) : l\'élément Wikidata Q114571608 (ancienne gare, sans article, même nom, même ligne, même ouverture le 10/09/1884) est la même gare ; ses coordonnées sont à 1,92 km du PK 100,404 (PK déduit 98,658), position SNCF gardée'],
        ['Ribaute-les-Lieuran', 'uic', '87781690', 'Ribaute-lès-Lieuran', 'wikidata', 'Q18745427', 'kept', $wp('Gare de Ribaute-lès-Lieuran', 141414748),
            'la gare de Ribaute-lès-Lieuran est située au point kilométrique (PK) 441,328 de la ligne de Béziers à Neussargues',
            'une seule gare de Ribaute-lès-Lieuran, au PK 441,328 de la ligne de Béziers à Neussargues, celui de la fiche SNCF 87781690 : les coordonnées de l\'article (43,415413 N, 3,242454 E, portées par l\'élément Wikidata Q18745427) sont à 1,95 km de ce PK ; position SNCF gardée'],
        ['Gallician', 'uic', '87775890', 'Gallician', 'wikidata', 'Q139770172', 'absorbed', $wp('Ligne d\'Arles à Lunel', 239493121),
            'Gallician',
            'une seule gare de Gallician au schéma de la ligne d\'Arles à Lunel (PK 28,53x) : l\'élément Wikidata Q139770172 (ancienne gare ouverte le 27/01/1868, comme la fiche SNCF 87775890, 1868) est la même gare ; ses coordonnées donnent le PK 28,680 (écart 150 m avec le schéma) contre le PK 26,525 du point SNCF (2,03 km), position de l\'élément Wikidata, confiance moyenne'],
        ['La Burbanche', 'uic', '87743765', 'La Burbanche', 'wikidata', 'Q114571626', 'kept', $wp('Schéma de la ligne de Lyon-Perrache à Genève (frontière)', 240566134),
            'La Burbanche',
            'une seule gare de La Burbanche au schéma de la ligne de Lyon-Perrache à Genève (PK 76,254, celui de la fiche SNCF 87743765) : l\'élément Wikidata Q114571626 (ancienne gare, sans article, même nom, même ouverture en 1856) est la même gare ; ses coordonnées sont à 3,34 km du PK 76,254 (PK déduit 79,589), position SNCF gardée'],
        ['Thoiras-Lasalle', 'osm', 'n12036270086', 'Thoiras - Lasalle', 'wikidata', 'Q111694089', 'kept', $wp('Ligne de Lézan à Saint-Jean-du-Gard', 211474255),
            'Thoiras - Lasalle',
            'une seule gare de Thoiras - Lasalle au schéma de la ligne de Lézan à Saint-Jean-du-Gard (PK 704,1xx, celui du nœud OSM n12036270086) : l\'élément Wikidata Q111694089 (même ouverture le 26/05/1909) est la même gare ; ses coordonnées donnent le PK 706,143, celui de Massiès (706,0xx), position du nœud OSM gardée'],
        ['Toucy-Ville', 'uic', '87684852', 'Toucy-Ville', 'osm', 'n13092272140', 'absorbed', $wp('Ligne de Triguères à Surgy', 240386952),
            'Toucy-Ville',
            'une seule gare de Toucy-Ville au schéma de la ligne de Triguères à Surgy (PK 178,349, celui de la fiche SNCF 87684852) : le point SNCF (commune de Dracy) est à 3,4 km du nœud OSM n13092272140 et du bâtiment w218930994 de la gare du chemin de fer touristique de l\'AATY, à Toucy même ; l\'écart avec Toucy-Moulins (PK 182,133, 3,78 km) vaut 3,6 km entre les nœuds OSM contre 3,0 km entre les points SNCF : position des objets OSM'],
        ['Toucy-Moulins', 'uic', '87684860', 'Toucy-Moulins', 'osm', 'n1037302403', 'absorbed', $wp('Ligne de Triguères à Surgy', 240386952),
            'Toucy-Moulins',
            'une seule gare de Toucy-Moulins au schéma de la ligne de Triguères à Surgy (PK 182,133, celui de la fiche SNCF 87684860) : le nœud OSM n1037302403 (AATY, à 3,95 km du point SNCF) est la même gare ; sa distance à Toucy-Ville (3,6 km) est celle de l\'écart de PK (3,78 km), pas celle des points SNCF (3,0 km) : position du nœud OSM'],
        ['Martel', 'uic', '87594713', 'Hall des voyageurs', 'osm', 'w1544567915', 'kept', $wp('Gare de Martel', 241160307),
            'qui a installé son « hall voyageurs » dans l\'ancienne halle aux marchandises',
            'le « hall des voyageurs » du chemin de fer touristique du Haut Quercy est dans l\'ancienne halle aux marchandises de la gare de Martel (article de la gare, infobox 44,933411 N, 1,608479 E, à 11 m du point SNCF 87594713) : le chemin OSM w1544567915 (à 72 m) n\'est pas une autre gare, position SNCF gardée'],
    ]),
    // Dix enregistrements qui ne sont pas une gare distincte : établissement ou bâtiment annexe d\'une gare qui a déjà sa fiche (commerce, cinéma, passerelle,
    // halle, salle des voyageurs du bâtiment, ancien bâtiment) ou nœud OSM aux étiquettes d\'une autre gare. Tags OSM relus par l\'API le 23/11/2026.
    ...array_map(fn (array $g) => [
        'station' => ['name' => $g[0], $g[1] => $g[2]],
        'action' => 'kind',
        'kind' => 'excluded',
        'source_url' => $g[3],
        'quote' => $g[4],
        'reason' => $g[5],
    ], [
        ['Bistrot de la gare', 'osm', 'n4924654037', $osm('n4924654037'), 'amenity=restaurant, craft=caterer, disused:railway=station', 'restaurant installé dans le bâtiment de la gare de Saint-Pal-de-Mons - Saint-Romain-Lachalm (étiquettes amenity=restaurant, craft=caterer, old_name ; à 12 m de la fiche de la gare) : un commerce, pas une gare'],
        ['Le Club', 'osm', 'w219131099', $osm('w219131099'), 'amenity=cinema, disused:name=Gare d\'Isigny-sur-Mer, disused:wikidata=Q84182784', 'cinéma dans l\'ancien bâtiment de la gare d\'Isigny-sur-Mer (disused:wikidata=Q84182784 est la fiche de la gare, à 11 m) : un cinéma, pas une gare'],
        ['Cinéma de la Gare', 'osm', 'w280966911', $osm('w280966911'), 'amenity=cinema', 'cinéma de Courseulles-sur-Mer (étiquette amenity=cinema, à 54 m de la fiche de la gare) : le nom contient « Gare », l\'objet est un cinéma'],
        ['Maison des associations', 'osm', 'w228140932', $osm('w228140932'), 'amenity=community_centre, alt_name=La Gare, disused:railway=station', 'maison des associations de Douvres-la-Délivrande dans l\'ancien bâtiment de la gare (amenity=community_centre, alt_name=La Gare, à 75 m de la fiche de la gare de Douvres-la-Délivrande) : le bâtiment réaffecté, pas une autre gare'],
        ['Ancienne halle aux marchandises de la gare de Trévoux', 'osm', 'w554527145', $osm('w554527145'), 'Ancienne halle aux marchandises de la gare de Trévoux', 'halle aux marchandises de la gare de Trévoux (nom de l\'objet, à 77 m de la fiche de la gare) : un bâtiment annexe, pas une gare'],
        ['Virville-Manneville', 'osm', 'n1281386660', $osm('n1281386660'), 'disused:railway=station, uic_ref=87413336, railway:position:exact=207.488', 'nœud OSM aux étiquettes de la gare de Virville - Manneville (UIC 87413336, PK 207,488 de la ligne de Paris-Saint-Lazare au Havre, article oldid 237268978) mais posé à 49,2509 N, 0,2186 E, sur la ligne de Lisieux à Trouville - Deauville à 19 m de la halte de Fierville-les-Parcs (PK 202,766, schéma oldid 235342270) : une position fausse, la gare a sa fiche (19223) et la halte aussi (19285)'],
        ['Galerie Villette', 'osm', 'w1039333290', $osm('w1039333290'), 'building=train_station, name=Galerie Villette, operator=SNCF', 'galerie de circulation de la gare de Lyon-Part-Dieu (galeries Villette et Béraudier, bâtiments building=train_station nommés comme des galeries, à 160 m de la fiche de la gare) : une partie de la gare, pas une gare distincte'],
        ['Hall 3 (Belcier)', 'osm', 'w934979720', $osm('w934979720'), 'building=train_station, name=Hall 3 (Belcier)', 'hall 3 du côté Belcier de la gare de Bordeaux-Saint-Jean (bâtiment building=train_station nommé comme un hall, à 170 m de la fiche de la gare) : une partie de la gare, pas une gare distincte'],
        ['Gare centre cote clairmarais', 'osm', 'w87848565', $osm('w87848565'), 'building=train_station, name=Gare centre cote clairmarais', 'bâtiment du côté Clairmarais de la gare de Reims (building=train_station, cadastre 2010, à 101 m de la fiche de la gare de Reims) : une partie de la gare, pas une gare distincte'],
        ['Ancien bâtiment de la gare de Choisy-le-Roi', 'wikidata', 'Q72573709', $wp('Gare de Choisy-le-Roi', 238875780), 'Ancien bâtiment de la gare.', 'élément Wikidata « ancien bâtiment d\'une gare dans le Val-de-Marne » : l\'ancien bâtiment de la gare de Choisy-le-Roi, reconverti en studio de danse (section Patrimoine ferroviaire de l\'article de la gare), à 146 m de la fiche de la gare : un bâtiment patrimonial, pas une gare distincte'],
    ]),

    // ── 41. Ouvertures des gares encore ouvertes datées à l'année par Wikidata ou déduites de la ligne, cinquième relecture contre
    // l'article de la gare (révision citée) et la chronologie de l'article de la ligne : 42 gares des 120 suivantes par fréquentation
    // (de Montchanin 0,39 M à Vernaison 0,18 M ; audit-gares-5, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/audit-gares-5.md). Lorsque la gare a déménagé, la fiche garde la date de la première gare
    // de la ville, comme pour Reims (section 37). Les 27 cas douteux restent à décider (Le Pallet, La Haie-Fouassière, Cros-de-Cagnes,
    // Évron, Èze, Pontarlier, Saint-Mariens, Rognac, Lentilly-Charpenay, Le Sablon, Vernaison, Saint-Pol-sur-Ternoise, La Souterraine,
    // Labège-Innopole, Colomiers-Lycée-International, La Hume, Don-Sainghin, Boissy-l'Aillerie, Chars, Cannes-la-Bocca, Romorantin,
    // Reims-Maison-Blanche, Avesnes, L'Estaque, Rosières, Lillers, Isbergues).

    // Montchanin (id 17447)
    [
        'station' => ['name' => 'Montchanin', 'uic' => '87694307'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1861-09-21',
        'precision' => 'day',
        'source_url' => $wp('Gare de Montchanin', 238809912),
        'quote' => '== Histoire == === Gare PLM (1861-1938) === La gare de Montchanin est mise en service le 21 Septembre 1861 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM), lorsqu\'elle ouvre à l\'exploitation la première section de M',
        'reason' => 'article de la gare (oldid 238809912) : « mise en service le 21 Septembre 1861 ». Remplace l\'année 1858 de Wikidata.',
    ],

    // Petit-Vaux (id 16124)
    [
        'station' => ['name' => 'Petit-Vaux', 'uic' => '87393652'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Petit Vaux', 241589595),
        'quote' => 'Le 1 mai 1883, le trafic de la ligne de Grande Ceinture ouvre aux voyageurs sur la section de Versailles-Chantiers à Savigny-sur-Orge',
        'reason' => 'article de la gare (oldid 241589595) : « Le 1 mai 1883, le trafic ». Remplace l\'année 1883 de Wikidata (précise au jour).',
    ],

    // Colomiers (id 17127)
    [
        'station' => ['name' => 'Colomiers', 'uic' => '87611467'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1877-10-22',
        'precision' => 'day',
        'source_url' => $wp('Gare de Colomiers', 236880416),
        'quote' => 'La Compagnie des Chemins de fer du Midi met en service sa ligne de Toulouse à Auch le 22 octobre 1877',
        'reason' => 'article de la gare (oldid 236880416) : « met en service sa ligne de Toulouse à Auch le 22 octobre 1877 ». Remplace l\'année 1960 d\'OpenHistoricalMap (la commune a été transformée dans les années 1960, la gare date de 1877).',
    ],

    // Ingwiller (id 15402)
    [
        'station' => ['name' => 'Ingwiller', 'uic' => '87213785'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1889-12-16',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bouxwiller à Ingwiller', 235341814),
        'quote' => '=== Chiffres clés === * La ligne fut mise en service le 16 décembre 1889',
        'reason' => 'article de la ligne « Ligne de Bouxwiller à Ingwiller » (oldid 235341814) : « La ligne fut mise en service le 16 décembre 1889 ». Corrige le 16/11/1889 en base (mois erroné).',
    ],

    // Munster (id 15210)
    [
        'station' => ['name' => 'Munster', 'uic' => '87182394'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-12-03',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Colmar-Central à Metzeral', 240511725),
        'quote' => 'La section de Colmar à Munster est ouverte le 3 décembre 1868 par la Compagnie des chemins de fer de l\'Est',
        'reason' => 'article de la ligne « Ligne de Colmar-Central à Metzeral » (oldid 240511725) : « La section de Colmar à Munster est ouverte le 3 décembre 1868 ». Précise l\'année 1868 de Wikidata et de l\'infobox au jour.',
    ],

    // Brignoud (id 17950)
    [
        'station' => ['name' => 'Brignoud', 'uic' => '87747451'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-09-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Brignoud', 236558998),
        'quote' => '== Histoire == Le 15 septembre 1864, la ligne de Grenoble à Montmélian () est mise en service par le PLM',
        'reason' => 'article de la gare (oldid 236558998) : « Le 15 septembre 1864, la ligne de Grenoble à Montmélian ». Précise l\'année 1864 de Wikidata au jour.',
    ],

    // Salon (id 18017)
    [
        'station' => ['name' => 'Salon', 'uic' => '87753764'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1873-05-28',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Avignon à Miramas', 240727914),
        'quote' => 'sur cinq années : * d’Avignon à Cavaillon, le 29 décembre 1868 ; * de Cavaillon à Cheval-Blanc, le 25 novembre 1872 ; * de Cheval-Blanc à Miramas, le 28 mai 1873',
        'reason' => 'article de la ligne « Ligne d\'Avignon à Miramas » (oldid 240727914) : « de Cheval-Blanc à Miramas, le 28 mai 1873 ». Précise l\'année 1873 de Wikidata (infobox « mai 1873 ») au jour.',
    ],

    // Presles-Courcelles (id 15555)
    [
        'station' => ['name' => 'Presles-Courcelles', 'uic' => '87276501'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1877-04-05',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Épinay - Villetaneuse au Tréport - Mers', 241187520),
        'quote' => 'Beauvais à Abancourt et de Persan - Beaumont à Méru, le 1er juillet 1875 ; * de Méru à Beauvais, le 15 avril 1876 ; * d\'Épinay - Villetaneuse à Persan - Beaumont, le 5 avril 1877',
        'reason' => 'article de la ligne « Ligne d\'Épinay - Villetaneuse au Tréport - Mers » (oldid 241187520) : « Épinay - Villetaneuse à Persan - Beaumont, le  5 avril 1877 ». Remplace l\'année 1880 de Wikidata (1880 est l\'embranchement Montsoult - Luzarches, la gare est sur la ligne d\'Épinay au Tréport, entre Montsoult - Maffliers et Nointel - Mours).',
    ],

    // Janzé (id 16328)
    [
        'station' => ['name' => 'Janzé', 'uic' => '87471334'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-12-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Janzé', 241212922),
        'quote' => 'L’inauguration de la ligne a lieu le 28 décembre 1881',
        'reason' => 'article de la gare (oldid 241212922) : « L’inauguration de la ligne a lieu le 28 décembre 1881 ». Précise l\'année 1881 de Wikidata au jour.',
    ],

    // St-Germain-des-Fossés (id 17782)
    [
        'station' => ['name' => 'St-Germain-des-Fossés', 'uic' => '87732206'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-06-19',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Germain-des-Fossés', 240408086),
        'quote' => '= === Mise en service et années de gloire === La Compagnie du chemin de fer Grand-Central de France (« Grand-Central ») met en service le 19 juin 1854, la section de Varennes à Saint-Germain-des-Fossés, via la station intermédiaire de Créchy, de sa ligne de Moulins à Roanne',
        'reason' => 'article de la gare (oldid 240408086) : « met en service le 19 juin 1854 ». Remplace le 18/06/1854 d\'OpenHistoricalMap.',
    ],

    // Chasse-sur-Rhône (id 17677)
    [
        'station' => ['name' => 'Chasse-sur-Rhône', 'uic' => '87722561'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-06-06',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Givors-Canal à Chasse-sur-Rhône', 235400294),
        'quote' => 'La ligne est mise en service le 6 juin 1857',
        'reason' => 'article de la ligne « Ligne de Givors-Canal à Chasse-sur-Rhône » (oldid 235400294) : « La ligne est mise en service le 6 juin 1857 ». Corrige le 06/06/1852 en base (année erronée : 1852 est l\'adjudication).',
    ],

    // Chalonnes (id 16513)
    [
        'station' => ['name' => 'Chalonnes', 'uic' => '87484204'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-09-24',
        'precision' => 'day',
        'source_url' => $wp('Ligne de La Possonnière à Niort', 241119292),
        'quote' => 'La section de La Possonnière à Cholet a été mise en service le 24 septembre 1866 et celle de Cholet à Niort le 26 décembre 1868',
        'reason' => 'article de la ligne « Ligne de La Possonnière à Niort » (oldid 241119292) : « La section de La Possonnière à Cholet a été mise en service le 24 septembre 1866 ». Remplace l\'année 1865 de Wikidata (l\'article de la gare dit « construite en 1865 pour l\'inauguration de la ligne », qui n\'a lieu qu\'en 1866).',
    ],

    // Hoerdt (id 15400)
    [
        'station' => ['name' => 'Hoerdt', 'uic' => '87213686'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1855-07-18',
        'precision' => 'day',
        'source_url' => $wp('Gare de Hœrdt', 241533197),
        'quote' => '== Histoire == La station de Hœrdt est mise en service le 18 juillet 1855 par la Compagnie des chemins de fer de l\'Est (Est), lorsqu\'elle ouvre à l\'exploitation la section de Vendenheim à Gare de Haguenau de son c',
        'reason' => 'article de la gare (oldid 241533197) : « La station de Hœrdt est mise en service le 18 juillet 1855 ». Précise l\'année 1855 de Wikidata au jour.',
    ],

    // St-Maurice-de-Beynost (id 17701)
    [
        'station' => ['name' => 'St-Maurice-de-Beynost', 'uic' => '87723536'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-06-23',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Maurice-de-Beynost', 241398769),
        'quote' => '== Histoire == La section de ligne, entre Lyon et Ambérieu, ouverte le 23 juin 1856 débute sur la rive droite à la gare de Lyon-Saint-Clair, car le pont sur le Rhône n\'est pas terminé, la ligne longe le fleuve jusqu\'à la ga',
        'reason' => 'article de la gare (oldid 241398769) : « ouverte le 23 juin 1856 ». Précise l\'année 1856 de Wikidata au jour (l\'infobox de la gare dit « date inconnue »).',
    ],

    // Reignier (id 17920)
    [
        'station' => ['name' => 'Reignier', 'uic' => '87746511'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-06-05',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Aix-les-Bains-Le Revard à Annemasse', 240496723),
        'quote' => 'trois étapes : * d\'Aix-les-Bains à Annecy le 5 juillet 1866, * de La Roche-sur-Foron à Annemasse le 10 juillet 1883, * d\'Annecy à La Roche-sur-Foron le 5 juin 1884 (ouverture retardée en raison de désordres sur le viaduc d\'Évires)',
        'reason' => 'article de la ligne « Ligne d\'Aix-les-Bains-Le Revard à Annemasse » (oldid 240496723) : « d\'Annecy à La Roche-sur-Foron le 5 juin 1884 ». Remplace l\'année 1883 de Wikidata (1883 est la section La Roche - Annemasse ; l\'infobox de la gare dit 1884).',
    ],

    // Beynost (id 17702)
    [
        'station' => ['name' => 'Beynost', 'uic' => '87723544'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-06-23',
        'precision' => 'day',
        'source_url' => $wp('Gare de Beynost', 241398781),
        'quote' => '== Histoire == La section de ligne, entre Lyon et Ambérieu, ouverte le 23 juin 1856 débute sur la rive droite à la gare de Lyon-Saint-Clair, car le pont sur le Rhône n\'est pas terminé, la ligne longe le fleuve jusqu\'à la ga',
        'reason' => 'article de la gare (oldid 241398781) : « ouverte le 23 juin 1856 ». Précise l\'année 1856 de Wikidata au jour.',
    ],

    // Billy-Montigny (id 15930)
    [
        'station' => ['name' => 'Billy-Montigny', 'uic' => '87345140'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-01-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Billy-Montigny', 238682909),
        'quote' => '== Histoire == La station de Billy-Montigny est mise en service et ouverte au service voyageurs le 1 janvier 1864 par la Compagnie des chemins de fer du Nord, le service marchandises ouvre le 8 juin de la même année',
        'reason' => 'article de la gare (oldid 238682909) : « mise en service et ouverte au service voyageurs le 1 janvier 1864 ». Précise l\'année 1864 de Wikidata au jour.',
    ],

    // Loches (id 16715)
    [
        'station' => ['name' => 'Loches', 'uic' => '87571471'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1878-07-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Loches', 229527084),
        'quote' => '== Histoire == La gare est ouverte le 15 juillet 1878',
        'reason' => 'article de la gare (oldid 229527084) : « La gare est ouverte le 15 juillet 1878 ». Précise l\'année 1878 de Wikidata au jour.',
    ],

    // Mouans-Sartoux (id 21017)
    [
        'station' => ['name' => 'Mouans-Sartoux', 'uic' => '87401323'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1871-11-13',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Cannes-la-Bocca à Grasse', 237782258),
        'quote' => '== Histoire == === Chronologie === * 13 novembre 1871 : ouverture de ligne * 1910 - 1914 : électrification expérimentale en - de la section de Mouans-Sartoux à Grasse * 2 octobre 1938 : fermeture au trafic des voya',
        'reason' => 'article de la ligne « Ligne de Cannes-la-Bocca à Grasse » (oldid 237782258) : « 13 novembre 1871 : ouverture de ligne ». Précise l\'année 1871 de Wikidata au jour.',
    ],

    // Montdidier (id 15761)
    [
        'station' => ['name' => 'Montdidier', 'uic' => '87313346'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1883-06-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Ormoy-Villers à Boves', 240747767),
        'quote' => 'La section d\'Estrées-Saint-Denis à Longueau n\'est ouverte que le 1 juin 1883, soit onze mois après la mise en service de la section d\'Ormoy-Villers à Estrées-Saint-Denis',
        'reason' => 'article de la ligne « Ligne d\'Ormoy-Villers à Boves » (oldid 240747767) : « La section d\'Estrées-Saint-Denis à Longueau n\'est ouverte que le 1 juin 1883 ». Précise l\'année 1883 de Wikidata au jour.',
    ],

    // Lézignan (Aude) (id 17195)
    [
        'station' => ['name' => 'Lézignan (Aude)', 'uic' => '87615112'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-04-22',
        'precision' => 'day',
        'source_url' => $wp('Gare de Lézignan-Corbières', 237028873),
        'quote' => '== Histoire == La Compagnie des chemins de fer du Midi et du Canal latéral à la Garonne met en service la section de Toulouse à Béziers et la gare de Lézignan le 22 avril 1857',
        'reason' => 'article de la gare (oldid 237028873) : « met en service la section de Toulouse à Béziers et la gare de Lézignan le 22 avril 1857 ». Précise l\'année 1857 de Wikidata au jour.',
    ],

    // St-Étienne-Bellevue (id 17743)
    [
        'station' => ['name' => 'St-Étienne-Bellevue', 'uic' => '87726190'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Saint-Étienne-Bellevue', 238736898),
        'quote' => '== Histoire == La première gare de Bellevue fut construite en 1857',
        'reason' => 'article de la gare (oldid 238736898) : « La première gare de Bellevue fut construite en 1857 ». Remplace l\'année 1932 de Wikidata (gare actuelle reconstruite en 1932 ; la fiche garde la date de la première gare, règle du déménagement).',
    ],

    // Cesson-Sévigné (id 15865)
    [
        'station' => ['name' => 'Cesson-Sévigné', 'uic' => '87334490'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1994-06-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Cesson-Sévigné', 238687421),
        'quote' => '== Histoire == La halte est ouverte en 06 1994, la même année que la gare de Rennes-La Poterie',
        'reason' => 'article de la gare (oldid 238687421) : « La halte est ouverte en 06 1994 ». Précise l\'année 1994 de Wikidata au mois.',
    ],

    // Sain-Bel (id 17653)
    [
        'station' => ['name' => 'Sain-Bel', 'uic' => '87721605'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1873-11-10',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Lyon-Saint-Paul à Montbrison', 234927022),
        'quote' => 'La ligne a été ouverte en deux étapes : * le 10 novembre 1873, un embranchement ferroviaire est ouvert entre la gare de l\'Arbresle, située sur la ligne du Coteau à Saint-Germain-au-Mont-d\'Or et ouverte en 1866-1868, et Sain-Bel ; * le 17 janvier 1876, les sections de Mo',
        'reason' => 'article de la ligne « Ligne de Lyon-Saint-Paul à Montbrison » (oldid 234927022) : « le 10 novembre 1873, un embranchement ferroviaire est ouvert entre la gare de l\'Arbresle ». Remplace le 21/11/1873 d\'OpenHistoricalMap.',
    ],

    // La Valbonne (id 17704)
    [
        'station' => ['name' => 'La Valbonne', 'uic' => '87723577'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1872-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de La Valbonne', 241398820),
        'quote' => 'Il fut finalement abandonné au profit de la construction de la gare de La Valbonne en 1872',
        'reason' => 'article de la gare (oldid 241398820) : « construction de la gare de La Valbonne en 1872 ». Remplace l\'année 1856 de Wikidata (la ligne de 1856 n\'avait pas d\'arrêt ici ; infobox 1872).',
    ],

    // Weyersheim (id 15399)
    [
        'station' => ['name' => 'Weyersheim', 'uic' => '87213678'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1855-07-18',
        'precision' => 'day',
        'source_url' => $wp('Gare de Hœrdt', 241533197),
        'quote' => '== Histoire == La station de Hœrdt est mise en service le 18 juillet 1855 par la Compagnie des chemins de fer de l\'Est (Est), lorsqu\'elle ouvre à l\'exploitation la section de Vendenheim à Gare de Haguenau de son c',
        'reason' => 'article de Hœrdt (gare voisine) (oldid 241533197) : « La station de Hœrdt est mise en service le 18 juillet 1855 ». Précise l\'année 1855 de Wikidata au jour : Weyersheim est voisine de Hœrdt sur la même section Vendenheim - Haguenau ; l\'article de Weyersheim ne donne aucune date. Confiance moyenne.',
    ],

    // St-Gilles-Croix-de-Vie (id 16564)
    [
        'station' => ['name' => 'St-Gilles-Croix-de-Vie', 'uic' => '87486571'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-10-17',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Gilles-Croix-de-Vie', 240257834),
        'quote' => '== Histoire == Un temps envisagé du côté de Saint-Gilles-sur-Vie, la première gare du terminus de Croix-de-Vie, est ouverte le 17 octobre 1881',
        'reason' => 'article de la gare (oldid 240257834) : « est ouverte le 17 octobre 1881 ». Précise l\'année 1881 de Wikidata au jour.',
    ],

    // Laigneville (id 15527)
    [
        'station' => ['name' => 'Laigneville', 'uic' => '87276204'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-08-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Laigneville', 241483882),
        'quote' => '== Histoire == L\'inauguration de la \'\'halte de Laigneville\'\' a lieu le 28 août 1892',
        'reason' => 'article de la gare (oldid 241483882) : « L\'inauguration de la \'\'halte de Laigneville\'\' a lieu le 28 août 1892 ». Précise l\'année 1892 de Wikidata au jour.',
    ],

    // La Possonnière (id 16518)
    [
        'station' => ['name' => 'La Possonnière', 'uic' => '87484337'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1851-08-21',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Tours à Saint-Nazaire', 235999065),
        'quote' => '= Chronologie === * Le 20 décembre 1848 : ouverture de Tours à Saumur * Le 1 août 1849 : ouverture de Saumur à Angers * Le 21 août 1851 : ouverture d\'Angers à Nantes * Le 1 janvier 1854 : ouverture du prolongement sur les quais du port de Nantes * Le 10 août 1857 : ouverture de Nantes à Saint-Nazaire ===',
        'reason' => 'article de la ligne « Ligne de Tours à Saint-Nazaire » (oldid 235999065) : « Le 21 août 1851 : ouverture d\'Angers à Nantes ». Corrige le 17/08/1851 en base.',
    ],

    // Lancey (id 16254)
    [
        'station' => ['name' => 'Lancey', 'uic' => '87417501'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-09-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Lancey', 236558749),
        'quote' => '== Histoire == Le 15 septembre 1864, le PLM met en service la ligne de allant de Grenoble à Montmélian',
        'reason' => 'article de la gare (oldid 236558749) : « Le 15 septembre 1864, le PLM met en service ». Précise l\'année 1864 de Wikidata au jour.',
    ],

    // Aurillac (id 17261)
    [
        'station' => ['name' => 'Aurillac', 'uic' => '87645002'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Aurillac', 241271782),
        'quote' => 'En 1866, la Compagnie du chemin de fer de Paris à Orléans met en service une ligne entre Figeac et Aurillac (section de l\'actuelle ligne de Figeac à Arvant)',
        'reason' => 'article de la gare (oldid 241271782) : « En 1866, la Compagnie du chemin de fer de Paris à Orléans met en service une ligne entre Figeac et Aurillac ». Remplace l\'année 1861 déduite de la ligne. Confiance moyenne.',
    ],

    // Marquise-Rinxent (id 15841)
    [
        'station' => ['name' => 'Marquise-Rinxent', 'uic' => '87317339'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-01-07',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Boulogne-Ville à Calais-Maritime', 240637467),
        'quote' => 'ompagnie des chemins de fer du Nord * 26 juillet 1861 : prorogation de la concession (les travaux n\'ont pas commencé) * 7 janvier 1867 : mise en service par la Compagnie du Nord de la ligne entre Boulogne et les Fontinettes * mai 1994 : mise en service de l\'électrification === Premières étud',
        'reason' => 'article de la ligne « Ligne de Boulogne-Ville à Calais-Maritime » (oldid 240637467) : « 7 janvier 1867 : mise en service ». Précise l\'année 1867 déduite de la ligne au jour.',
    ],

    // La Borne-Blanche (id 15535)
    [
        'station' => ['name' => 'La Borne-Blanche', 'uic' => '87276287'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1964-12-06',
        'precision' => 'day',
        'source_url' => $wp('Gare de La Borne Blanche', 241066633),
        'quote' => '6 décembre 1964 (construction en mai 1962)',
        'reason' => 'article de la gare (oldid 241066633) : « 6 décembre 1964 (construction en mai 1962) ». Remplace le mois de mai 1962 de Wikidata (construction), l\'infobox donne la mise en service le 6 décembre 1964.',
    ],

    // Villabé (id 17359)
    [
        'station' => ['name' => 'Villabé', 'uic' => '87681619'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-06-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Corbeil-Essonnes à Montereau', 241117450),
        'quote' => 'Elle a été mise en service le 1 juin 1897 et inaugurée le 27 juin 1897',
        'reason' => 'article de la ligne « Ligne de Corbeil-Essonnes à Montereau » (oldid 241117450) : « Elle a été mise en service le 1 juin 1897 ». Précise l\'année 1897 de Wikidata au jour (la gare ouvre dès l\'inauguration de la ligne).',
    ],

    // Châtel-Nomexy (id 15034)
    [
        'station' => ['name' => 'Châtel-Nomexy', 'uic' => '87144071'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-06-24',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Blainville - Damelevières à Lure', 240009224),
        'quote' => 'La ligne a été ouverte en trois étapes : * de Blainville à Épinal, le 24 juin 1857 ; * d\'Épinal à Aillevillers, le 24 septembre 1863, terminant ainsi la liaison Vesoul – Nancy ; * d\'Aillevillers à Lure, le 25 avril 1878, l',
        'reason' => 'article de la ligne « Ligne de Blainville - Damelevières à Lure » (oldid 240009224) : « de Blainville à Épinal, le 24 juin 1857 ». Précise l\'année 1857 de Wikidata au jour.',
    ],

    // Ste-Musse (id 17864)
    [
        'station' => ['name' => 'Ste-Musse', 'uic' => '87742262'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2022-12-11',
        'precision' => 'day',
        'source_url' => $wp('Gare de Toulon-Sainte-Musse', 238999681),
        'quote' => 'La halte est mise en service le 11 décembre 2022, à l\'issue de travaux de création commencés en janvier 2022',
        'reason' => 'article de la gare (oldid 238999681) : « La halte est mise en service le 11 décembre 2022 ». Précise l\'année 2022 de Wikidata au jour.',
    ],

    // Lavaur (id 17217)
    [
        'station' => ['name' => 'Lavaur', 'uic' => '87615476'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1888-03-04',
        'precision' => 'day',
        'source_url' => $wp('Gare de Lavaur', 237165105),
        'quote' => '== Histoire == La station de Lavaur est mise en service le 4 mars 1888 par la Compagnie des chemins de fer du Midi et du Canal latéral à la Garonne (Midi), lorsqu\'elle ouvre à l\'exploitation la section de Saint',
        'reason' => 'article de la gare (oldid 237165105) : « La station de Lavaur est mise en service le 4 mars 1888 ». Précise l\'année 1888 de Wikidata au jour.',
    ],

    // Oudon (id 16465)
    [
        'station' => ['name' => 'Oudon', 'uic' => '87481184'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1851-08-21',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Tours à Saint-Nazaire', 235999065),
        'quote' => '= Chronologie === * Le 20 décembre 1848 : ouverture de Tours à Saumur * Le 1 août 1849 : ouverture de Saumur à Angers * Le 21 août 1851 : ouverture d\'Angers à Nantes * Le 1 janvier 1854 : ouverture du prolongement sur les quais du port de Nantes * Le 10 août 1857 : ouverture de Nantes à Saint-Nazaire ===',
        'reason' => 'article de la ligne « Ligne de Tours à Saint-Nazaire » (oldid 235999065) : « Le 21 août 1851 : ouverture d\'Angers à Nantes ». Remplace l\'année 1848 d\'OpenStreetMap (1848 est l\'ouverture de Tours à Saumur, loin d\'Oudon).',
    ],

    // St-Pierre-d'Albigny (id 17840)
    [
        'station' => ['name' => 'St-Pierre-d\'Albigny', 'uic' => '87741223'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-10-20',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Culoz à Modane (frontière)', 238204405),
        'quote' => 'mb|[[Pont Victor-Emmanuel à Cruet à l\'emplacement de la ligne en rive gauche de l\'Isère, en service de 1856 à 1876.]] * 20 octobre 1856 : d\'Aix-les-Bains à Saint-Jean-de-Maurienne',
        'reason' => 'article de la ligne « Ligne de Culoz à Modane (frontière) » (oldid 238204405) : « 20 octobre 1856 : d\'Aix-les-Bains à Saint-Jean-de-Maurienne ». Précise l\'année 1856 de Wikidata au jour (premier voyage ; l\'inauguration officielle est en 1857). Confiance moyenne.',
    ],

    // Groisy-Thorens-la-Caille (id 17911)
    [
        'station' => ['name' => 'Groisy-Thorens-la-Caille', 'uic' => '87746248'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-06-05',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Aix-les-Bains-Le Revard à Annemasse', 240496723),
        'quote' => 'trois étapes : * d\'Aix-les-Bains à Annecy le 5 juillet 1866, * de La Roche-sur-Foron à Annemasse le 10 juillet 1883, * d\'Annecy à La Roche-sur-Foron le 5 juin 1884 (ouverture retardée en raison de désordres sur le viaduc d\'Évires)',
        'reason' => 'article de la ligne « Ligne d\'Aix-les-Bains-Le Revard à Annemasse » (oldid 240496723) : « d\'Annecy à La Roche-sur-Foron le 5 juin 1884 ». Remplace l\'année 1883 de Wikidata (1883 est la section La Roche - Annemasse). Confiance moyenne : l\'article de la gare ne donne aucune date.',
    ],

    // Réaumont-St-Cassien (id 17943)
    [
        'station' => ['name' => 'Réaumont-St-Cassien', 'uic' => '87747279'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-07-10',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble)', 241143131),
        'quote' => 'Le tronçon de Saint-Rambert à Rives a été ouvert le 5 novembre 1856, celui de Rives à Piquepierre le 10 juillet 1857, prolongé jusqu\'à Grenoble le 1 juillet 1858 après achèvement du pont sur l\'Isère',
        'reason' => 'article de la ligne « Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble) » (oldid 241143131) : « celui de Rives à Piquepierre le 10 juillet 1857 ». Corrige le 12/07/1857 en base. Confiance moyenne : l\'article de la gare ne donne aucune date.',
    ],

    // St-Loubès (id 16923)
    [
        'station' => ['name' => 'St-Loubès', 'uic' => '87584730'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1852-09-20',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean', 240534824),
        'quote' => 's rentable sans prolongement, et préférèrent attendre l\'achèvement de la section de Libourne à Bordeaux pour équiper et mettre en service l\'ensemble le 20 septembre 1852',
        'reason' => 'article de la ligne « Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean » (oldid 240534824) : « mettre en service l\'ensemble le 20 septembre 1852 ». Précise l\'année 1852 de Wikidata au jour (section Libourne - Bordeaux). Confiance moyenne.',
    ],

    // ── 42. Ouvertures des grandes gares encore ouvertes (maximum de fréquentation depuis 2019 de 0,15 M à 53,9 M de voyageurs) que les cinq
    // premières relectures n'avaient pas lues : leur ouverture venait d'OpenStreetMap, d'OpenHistoricalMap, de la ligne ou de Wikidata à
    // l'année. 62 gares relues contre l'article de la gare (révision citée) et la chronologie de l'article de la ligne ; 14 corrigées
    // (audit-grandes-gares, 23/11/2026, rapport storage/app/reports/agents/aprem-2111/audit-grandes-gares.md). Lorsque la gare actuelle
    // remplace une première gare ou une gare provisoire de la ville, la fiche garde la date de la première gare (règle du déménagement,
    // section 37) : Nantes (gare provisoire de 1851), Lyon-Perrache, Viroflay-Rive-Gauche. Les 6 cas douteux restent à décider (Marseille-Blancarde,
    // Alès, La Fère, Cap-d'Ail, Veauche, Remise à Jorelle).

    // Nantes (id 16456)
    [
        'station' => ['name' => 'Nantes', 'uic' => '87481002'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1851-08-25',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Tours à Saint-Nazaire', 235999065),
        'quote' => 'L\'exploitation de la section d\'Angers à Nantes a débuté le 21 août 1851 pour le service des marchandises et le 25 août 1851 pour le service des voyageurs.',
        'reason' => 'article de la ligne « Ligne de Tours à Saint-Nazaire » (oldid 235999065) : « L\'exploitation de la section d\'Angers à Nantes a débuté le 21 août 1851 pour le service des marchandises et le 25 août 1851 pour le service des voyageurs. ». Remplace le 18/09/1989 d\'OpenStreetMap (nouveau bâtiment voyageurs) : la gare actuelle remplace la gare provisoire de 1851 puis la gare de Nantes-Orléans de 1853 (règle du déménagement, première gare de la ville). Gare de Nantes, oldid 241317487 : « Le 21 août 1851, mise en service de la ligne d\'Angers à Nantes ».',
    ],

    // Toulouse-Matabiau (id 17097)
    [
        'station' => ['name' => 'Toulouse-Matabiau', 'uic' => '87611004'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-08-30',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Bordeaux-Saint-Jean à Sète-Ville', 234926948),
        'quote' => '30 août 1856 : ouverture de Valence-d\'Agen à Toulouse.',
        'reason' => 'article de la ligne « Ligne de Bordeaux-Saint-Jean à Sète-Ville » (oldid 234926948) : « 30 août 1856 : ouverture de Valence-d\'Agen à Toulouse. ». Remplace le 16/10/1856 d\'OpenHistoricalMap (« La gare primitive est inaugurée le 16 octobre 1856 » dans l\'article de la gare, oldid 238641166, dont l\'infobox donne la mise en service le 30 août 1856). Confiance moyenne.',
    ],

    // Lyon-Perrache (id 17663)
    [
        'station' => ['name' => 'Lyon-Perrache', 'uic' => '87722025'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1856-11-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Lyon-Perrache', 241464982),
        'quote' => 'la gare de Perrache est mise en service le 10 novembre 1856, lorsque cette compagnie prolonge sa ligne depuis la gare de Vaise',
        'reason' => 'article de la gare (oldid 241464982) : « la gare de Perrache est mise en service le 10 novembre 1856, lorsque cette compagnie prolonge sa ligne depuis la gare de Vaise ». Remplace le 24/11/1857 d\'OpenHistoricalMap (la gare est inaugurée début juin 1857 ; l\'article de la ligne de Moret à Lyon-Perrache date le tracé définitif du Coteau à Lyon-Perrache du 24 novembre 1857).',
    ],

    // St-Étienne-Châteaucreux (id 17737)
    [
        'station' => ['name' => 'St-Étienne-Châteaucreux', 'uic' => '87726000'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-12-14',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Étienne-Châteaucreux', 239671917),
        'quote' => 'La gare est ouverte au service des voyageurs le 14 décembre 1857.',
        'reason' => 'article de la gare (oldid 239671917) : « La gare est ouverte au service des voyageurs le 14 décembre 1857. ». Remplace le 20/11/1857 d\'OpenHistoricalMap (premier bâtiment en bois de 1857, bâtiment en dur de 1882 à 1884).',
    ],

    // Viroflay-Rive-Gauche (id 16088)
    [
        'station' => ['name' => 'Viroflay-Rive-Gauche', 'uic' => '87393215'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1840-09-10',
        'precision' => 'day',
        'source_url' => $wp('Gare de Viroflay-Rive-Gauche', 232545789),
        'quote' => 'Elle est mise en service le 10 septembre 1840 avec l\'ouverture de la voie entre la gare de Paris-Montparnasse et la gare de Versailles-Rive-Gauche',
        'reason' => 'article de la gare (oldid 232545789) : « Elle est mise en service le 10 septembre 1840 avec l\'ouverture de la voie entre la gare de Paris-Montparnasse et la gare de Versailles-Rive-Gauche ». Remplace le 27/11/1979 d\'OpenStreetMap, qui est la reconstruction de la gare pour la Transversale Rive Gauche (plaque de 1979). L\'article de la ligne des Invalides à Versailles-Rive-Gauche date la ligne du 10/09/1840. Confiance moyenne.',
    ],

    // Montereau (id 17379)
    [
        'station' => ['name' => 'Montereau', 'uic' => '87682302'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1849-08-12',
        'precision' => 'day',
        'source_url' => $wp('Gare de Montereau', 240764099),
        'quote' => 'La gare de Montereau est mise en service le 12 août 1849, avec l\'ouverture au trafic de la section de Paris à Tonnerre par l\'État',
        'reason' => 'article de la gare (oldid 240764099) : « La gare de Montereau est mise en service le 12 août 1849, avec l\'ouverture au trafic de la section de Paris à Tonnerre par l\'État ». Remplace le 01/10/1888 d\'OpenHistoricalMap. L\'article de la ligne de Paris-Lyon à Marseille-Saint-Charles (oldid 241214704) cite une mise en service provisoire de Melun - Montereau le 3 janvier 1849 et l\'ouverture de Paris à Tonnerre le 12 août 1849.',
    ],

    // Bellegarde (id 17888)
    [
        'station' => ['name' => 'Bellegarde', 'uic' => '87745000'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1858-03-18',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bellegarde', 241398952),
        'quote' => 'La section Seyssel-Genève est ouverte le 18 mars 1858 avec un point d\'arrêt dans ce qui deviendra, le 6 décembre suivant, la toute nouvelle commune de Bellegarde',
        'reason' => 'article de la gare (oldid 241398952) : « La section Seyssel-Genève est ouverte le 18 mars 1858 avec un point d\'arrêt dans ce qui deviendra, le 6 décembre suivant, la toute nouvelle commune de Bellegarde ». Remplace le 21/05/2010 d\'OpenStreetMap (inauguration du pôle multimodal). L\'article de la ligne de Lyon-Perrache à Genève (oldid 240596388) : « L\'ouverture de la section de ligne entre Seyssel et Genève a lieu le 18 mars 1858 ».',
    ],

    // Stains-Cerisaie TT (id 17512)
    [
        'station' => ['name' => 'Stains-Cerisaie TT', 'uic' => '87697334'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '2017-07-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Stains-La Cerisaie', 238954803),
        'quote' => 'La gare est desservie depuis le 1 juillet 2017 par le T11',
        'reason' => 'article de la gare (oldid 238954803) : « La gare est desservie depuis le 1 juillet 2017 par le T11 ». Précise le mois de Wikidata (juillet 2017) au jour : infobox de l\'article de la gare, « mise en service 1 juillet 2017 ».',
    ],

    // Rumilly (id 17909)
    [
        'station' => ['name' => 'Rumilly', 'uic' => '87746149'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-07-05',
        'precision' => 'day',
        'source_url' => $wp('Gare de Rumilly', 240914363),
        'quote' => 'Le 5 juillet 1866, la ligne entre Aix-les-Bains et Annecy, est mise en service par la compagnie du PLM.',
        'reason' => 'article de la gare (oldid 240914363) : « Le 5 juillet 1866, la ligne entre Aix-les-Bains et Annecy, est mise en service par la compagnie du PLM. ». Précise l\'année 1866 de Wikidata au jour (même date dans la section 41 pour les gares voisines de la ligne d\'Aix-les-Bains à Annecy).',
    ],

    // Chauny (id 15720)
    [
        'station' => ['name' => 'Chauny', 'uic' => '87296616'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1849-10-21',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Creil à Jeumont', 236310314),
        'quote' => 'Le 21 octobre 1849, de Noyon à Chauny',
        'reason' => 'article de la ligne « Ligne de Creil à Jeumont » (oldid 236310314) : « Le 21 octobre 1849, de Noyon à Chauny ». Précise l\'année 1849 de Wikidata au jour (section Noyon - Chauny, dont Chauny est le terminus ; l\'article de la gare dit « ouverte en 1849, en même temps que la portion de la ligne de Creil à Jeumont qui la dessert alors »).',
    ],

    // Livry-sur-Seine (id 17380)
    [
        'station' => ['name' => 'Livry-sur-Seine', 'uic' => '87682401'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-06-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Corbeil-Essonnes à Montereau', 241117450),
        'quote' => 'Elle a été mise en service le 1 juin 1897 et inaugurée le 27 juin 1897.',
        'reason' => 'article de la ligne « Ligne de Corbeil-Essonnes à Montereau » (oldid 241117450) : « Elle a été mise en service le 1 juin 1897 et inaugurée le 27 juin 1897. ». Précise l\'année 1897 de la ligne au jour (même date que Villabé, section 41). Confiance moyenne : l\'article de la gare ne donne aucune date.',
    ],

    // Le Grand-Lemps (id 17941)
    [
        'station' => ['name' => 'Le Grand-Lemps', 'uic' => '87747220'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1860-12-24',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble)', 241143131),
        'quote' => 'Du Grand-Lemps à Rives le 24 décembre 1860.',
        'reason' => 'article de la ligne « Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble) » (oldid 241143131) : « Du Grand-Lemps à Rives le 24 décembre 1860. ». Remplace le 02/01/1861 déduit de la ligne : le Grand-Lemps est le terminus de la section ouverte le 24/12/1860 vers Rives. Confiance moyenne : l\'article de la gare ne donne aucune date.',
    ],

    // St-Sulpice-Izon (id 16922)
    [
        'station' => ['name' => 'St-Sulpice-Izon', 'uic' => '87584722'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1852-09-20',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean', 240534824),
        'quote' => 's rentable sans prolongement, et préférèrent attendre l\'achèvement de la section de Libourne à Bordeaux pour équiper et mettre en service l\'ensemble le 20 septembre 1852',
        'reason' => 'article de la ligne « Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean » (oldid 240534824) : « s rentable sans prolongement, et préférèrent attendre l\'achèvement de la section de Libourne à Bordeaux pour équiper et mettre en service l\'ensemble le 20 septembre 1852 ». Précise l\'année 1852 de Wikidata au jour (section Libourne - Bordeaux, comme Saint-Loubès, section 41). Confiance moyenne : l\'article de la gare ne donne aucune date.',
    ],

    // Thiviers (id 17037)
    [
        'station' => ['name' => 'Thiviers', 'uic' => '87595124'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1861-08-26',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Limoges-Bénédictins à Périgueux', 234971310),
        'quote' => 'La ligne a été mise en service le 26 août 1861.',
        'reason' => 'article de la ligne « Ligne de Limoges-Bénédictins à Périgueux » (oldid 234971310) : « La ligne a été mise en service le 26 août 1861. ». Remplace le 30/05/1892 déduit de la ligne du Quéroy-Pranzac à Thiviers : Thiviers est l\'une des neuf gares d\'origine de la ligne de Limoges à Périgueux (« Aux neuf gares d\'origine comprises entre Limoges et Périgueux (Beynac, Nexon, Lafarge, Bussière-Galant, La Coquille, Thiviers... »). Confiance moyenne.',
    ],

    // ── 43. Ouvertures des gares FERMÉES aux voyageurs, datées à l'année par Wikidata ou déduites de la ligne, première relecture contre
    // l'article Wikipédia de la gare (révision citée) et, au besoin, celui de la ligne : les audits précédents n'avaient relu que les gares
    // encore ouvertes. 120 gares relues (les 84 dont l'article de la gare contredit la date de la base, puis 36 qui la confirment à l'année) ;
    // 59 corrigées, 41 confirmées, 20 à décider (article lié à un réseau et non à la gare, dates contradictoires dans l'article, date
    // approximative ; rapport storage/app/reports/agents/aprem-2111/ouvertures-gares-fermees.md). Règle du déménagement (section 37) : une gare
    // remplacée par une nouvelle garde la date de la première (Saint-Germain-en-Laye-Bel-Air - Fourqueux, Ravezies). Une halte ouverte après
    // la ligne prend sa propre date, non celle de la ligne (Cognin, Plan-Plascassier, Pont-d'Espenel, Achicourt).

    // Versailles-Matelots (id 16083)
    [
        'station' => ['name' => 'Versailles-Matelots', 'uic' => '87393132'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1871-05-12',
        'precision' => 'day',
        'source_url' => $wp('Gare de Versailles-Matelots', 232708478),
        'quote' => 'Cela permet l\'ouverture de la gare à l\'exploitation le 12 mai 1871',
        'reason' => 'article de la gare (oldid 232708478) : « Cela permet l\'ouverture de la gare à l\'exploitation le 12 mai 1871 ». Précise ou corrige la date de la base (1883-01-01, wikidata).',
    ],

    // Le Havre-Graville (id 16218)
    [
        'station' => ['name' => 'Le Havre-Graville', 'uic' => '87413203'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare du Havre-Graville', 230878443),
        'quote' => 'La gare est mise en service en 1882.',
        'reason' => 'article de la gare (oldid 230878443) : « La gare est mise en service en 1882. ». Remplace l\'année 1847 de Wikidata (ouverture de la ligne de Paris au Havre, qui n\'avait pas cette gare).',
    ],

    // Couliboeuf (id 16268)
    [
        'station' => ['name' => 'Couliboeuf', 'uic' => '87444232'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-02-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne du Mans à Mézidon', 239514750),
        'quote' => 'Le 1er février 1859 entre Argentan et Mézidon, avec embranchement entre la gare de Coulibœuf et la gare de Falaise',
        'reason' => 'article de la ligne « Ligne du Mans à Mézidon » (oldid 239514750) : « Le 1er février 1859 entre Argentan et Mézidon, avec embranchement entre la gare de Coulibœuf et la gare de Falaise ». Précise ou corrige la date de la base (1859-01-01, wikidata). L\'infobox de l\'article de la gare (oldid 208419078) donne aussi le 1 février 1859.',
    ],

    // Myennes (id 17471)
    [
        'station' => ['name' => 'Myennes', 'uic' => '87696138'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Myennes', 240518059),
        'quote' => 'La gare de Myennes est ajoutée, en 1864, sur la ligne ouverte le 21 septembre 1861',
        'reason' => 'article de la gare (oldid 240518059) : « La gare de Myennes est ajoutée, en 1864, sur la ligne ouverte le 21 septembre 1861 ». Remplace le 21/09/1861 déduit de la ligne : la gare n\'existait pas à l\'ouverture de la ligne.',
    ],

    // Manduel-Redessan (id 18164)
    [
        'station' => ['name' => 'Manduel-Redessan', 'uic' => '87775064'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1840-08-19',
        'precision' => 'day',
        'source_url' => $wp('Gare de Manduel - Redessan', 218055638),
        'quote' => 'la station de Manduel est officiellement mise en service par la Compagnie des mines de la Grand\'Combe et des chemins de fer du Gard lorsqu\'elle ouvre à l\'exploitation la ligne d\'Alais à Beaucaire via Nîmes, le 19 août 1840',
        'reason' => 'article de la gare (oldid 218055638) : « la station de Manduel est officiellement mise en service par la Compagnie des mines de la Grand\'Combe et des chemins de fer du Gard lorsqu\'elle ouvre à l\'exploitation la ligne d\'Alais à Beaucaire via Nîmes, le 19 août 1840 ». Remplace l\'année 1839 de Wikidata (premier train de la foire de Beaucaire le 15/07/1839, avant la mise en service).',
    ],

    // Ste-Menehould (id 18560)
    [
        'station' => ['name' => 'Ste-Menehould', 'uic' => '87174870'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-07-23',
        'precision' => 'day',
        'source_url' => $wp('Gare de Sainte-Menehould', 229581713),
        'quote' => 'La station de Sainte-Menehould est mise en service le 23 juillet 1867 par la Compagnie des chemins de fer de l\'Est lorsqu\'elle ouvre à l\'exploitation la section de Châlons à Sainte-Menehould.',
        'reason' => 'article de la gare (oldid 229581713) : « La station de Sainte-Menehould est mise en service le 23 juillet 1867 par la Compagnie des chemins de fer de l\'Est lorsqu\'elle ouvre à l\'exploitation la section de Châlons à Sainte-Menehould. ». Précise ou corrige la date de la base (1867-01-01, wikidata).',
    ],

    // Voellerdingen (id 18781)
    [
        'station' => ['name' => 'Voellerdingen', 'uic' => '87215640'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-05-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Vœllerdingen', 188424218),
        'quote' => 'La gare de Vœllerdingen est mise en service lors de l\'ouverture de la section de voie ferrée de Mommenheim à Kalhausen, le 1 mai 1895.',
        'reason' => 'article de la gare (oldid 188424218) : « La gare de Vœllerdingen est mise en service lors de l\'ouverture de la section de voie ferrée de Mommenheim à Kalhausen, le 1 mai 1895. ». Précise ou corrige la date de la base (1895-01-01, wikidata).',
    ],

    // St-Ouen-Garibaldi (id 18798)
    [
        'station' => ['name' => 'St-Ouen-Garibaldi', 'uic' => '87271254'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1972-05-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Ouen-Garibaldi', 224691760),
        'quote' => 'La nouvelle gare est ouverte le 28 mai 1972 dans les chemins de fer et devient le terminus de dix aller-retour quotidiens',
        'reason' => 'article de la gare (oldid 224691760) : « La nouvelle gare est ouverte le 28 mai 1972 dans les chemins de fer et devient le terminus de dix aller-retour quotidiens ». Remplace le 1874 déduit de la ligne (la ligne de La Plaine à Ermont - Eaubonne est inaugurée en 1908, la gare est créée en 1972).',
    ],

    // Achicourt (id 19041)
    [
        'station' => ['name' => 'Achicourt', 'uic' => '87342154'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1910-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Achicourt', 238683027),
        'quote' => 'il est complété par une halte voyageurs en 1910',
        'reason' => 'article de la gare (oldid 238683027) : « il est complété par une halte voyageurs en 1910 ». Remplace le 1876 de Wikidata (simple poste de bifurcation, sans voyageurs).',
    ],

    // St-Germain-en-Laye-Bel-Air-Fourqueux (id 19086)
    [
        'station' => ['name' => 'St-Germain-en-Laye-Bel-Air-Fourqueux', 'uic' => '87366922'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-09-04',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Germain-en-Laye-Bel-Air - Fourqueux', 238757435),
        'quote' => 'la gare de Fourqueux ouvre aux voyageurs le 4 septembre 1882, lors de l\'inauguration du service de Versailles-Chantiers à Achères',
        'reason' => 'article de la gare (oldid 238757435) : « la gare de Fourqueux ouvre aux voyageurs le 4 septembre 1882, lors de l\'inauguration du service de Versailles-Chantiers à Achères ». Précise ou corrige la date de la base (1877-01-01, déduite de la ligne). Première gare du lieu (règle du déménagement) : la gare actuelle de 2004 est une nouvelle gare.',
    ],

    // St-Germain-en-Laye-Grande-Ceinture (id 19108)
    [
        'station' => ['name' => 'St-Germain-en-Laye-Grande-Ceinture', 'uic' => '87382804'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-09-04',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Germain-en-Laye-Grande-Ceinture', 240770886),
        'quote' => 'la gare de Saint-Germain-en-Laye-Grande-Ceinture ouvre aux voyageurs le 4 septembre 1882, lors de l\'inauguration du service de Versailles-Chantiers à Achères',
        'reason' => 'article de la gare (oldid 240770886) : « la gare de Saint-Germain-en-Laye-Grande-Ceinture ouvre aux voyageurs le 4 septembre 1882, lors de l\'inauguration du service de Versailles-Chantiers à Achères ». Précise ou corrige la date de la base (1877-01-01, déduite de la ligne).',
    ],

    // Poissy-Grande-Ceinture (id 19111)
    [
        'station' => ['name' => 'Poissy-Grande-Ceinture', 'uic' => '87386565'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-09-04',
        'precision' => 'day',
        'source_url' => $wp('Gare de Poissy-Grande-Ceinture', 235033809),
        'quote' => 'La « gare de Poissy (grande ceinture) » est officiellement mise en service le 4 septembre 1882',
        'reason' => 'article de la gare (oldid 235033809) : « La « gare de Poissy (grande ceinture) » est officiellement mise en service le 4 septembre 1882 ». Précise ou corrige la date de la base (1877-01-01, déduite de la ligne).',
    ],

    // St-Cyr-Grande-Ceinture (id 19140)
    [
        'station' => ['name' => 'St-Cyr-Grande-Ceinture', 'uic' => '87393850'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-09-04',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Cyr-Grande-Ceinture', 219957684),
        'quote' => 'La gare de Saint-Cyr-Grande-Ceinture ouvre au trafic des voyageurs le 4 septembre 1882 lors de l\'inauguration, sur la ligne de Grande ceinture, du service de Versailles-Chantiers à Achères',
        'reason' => 'article de la gare (oldid 219957684) : « La gare de Saint-Cyr-Grande-Ceinture ouvre au trafic des voyageurs le 4 septembre 1882 lors de l\'inauguration, sur la ligne de Grande ceinture, du service de Versailles-Chantiers à Achères ». Précise ou corrige la date de la base (1877-01-01, déduite de la ligne).',
    ],

    // Toutainville (id 19267)
    [
        'station' => ['name' => 'Toutainville', 'uic' => '87415497'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1889-06-08',
        'precision' => 'day',
        'source_url' => $wp('Gare de Toutainville', 166062174),
        'quote' => 'Elle a été ouverte le 8 juin 1889 par la Compagnie des chemins de fer de l\'Ouest.',
        'reason' => 'article de la gare (oldid 166062174) : « Elle a été ouverte le 8 juin 1889 par la Compagnie des chemins de fer de l\'Ouest. ». Précise ou corrige la date de la base (1867-01-01, déduite de la ligne).',
    ],

    // Messei (id 19355)
    [
        'station' => ['name' => 'Messei', 'uic' => '87448100'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-07-02',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Argentan à Granville', 240174253),
        'quote' => '2 juillet 1866 : Argentan à Flers (embranchement de la ligne du Mans à Mézidon)',
        'reason' => 'article de la ligne « Ligne d\'Argentan à Granville » (oldid 240174253) : « 2 juillet 1866 : Argentan à Flers (embranchement de la ligne du Mans à Mézidon) ». Précise ou corrige la date de la base (1874-05-18, déduite de la ligne). Messei est sur la section d\'Argentan à Flers ; l\'infobox de l\'article de la gare (oldid 232257623) donne 1866.',
    ],

    // St-Julien (Côtes-d'Armor) (id 19389)
    [
        'station' => ['name' => 'St-Julien (Côtes-d\'Armor)', 'uic' => '87473314'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1873-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Saint-Julien (Côtes-d\'Armor)', 236325504),
        'quote' => 'La compagnie ouvre une halte à côté du passage à niveau n° 8 en 1873',
        'reason' => 'article de la gare (oldid 236325504) : « La compagnie ouvre une halte à côté du passage à niveau n° 8 en 1873 ». Remplace l\'année 1872 de Wikidata (la section de la ligne ouvre le 20/11/1871 sans halte).',
    ],

    // Lambel-Camors (id 19404)
    [
        'station' => ['name' => 'Lambel-Camors', 'uic' => '87476515'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1899-05-29',
        'precision' => 'day',
        'source_url' => $wp('Gare de Lambel - Camors', 235362694),
        'quote' => 'La halte de Lambel - Camors est mise en service le 29 mai 1899 par la Compagnie du chemin de fer de Paris à Orléans (PO)',
        'reason' => 'article de la gare (oldid 235362694) : « La halte de Lambel - Camors est mise en service le 29 mai 1899 par la Compagnie du chemin de fer de Paris à Orléans (PO) ». Précise ou corrige la date de la base (1898-01-01, wikidata).',
    ],

    // Angers-St-Serge (id 19450)
    [
        'station' => ['name' => 'Angers-St-Serge', 'uic' => '87484022'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1878-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Angers-Saint-Serge', 231419269),
        'quote' => 'La gare est ouverte en 1878.',
        'reason' => 'article de la gare (oldid 231419269) : « La gare est ouverte en 1878. ». Précise ou corrige la date de la base (1863-01-01, wikidata).',
    ],

    // Croix-d'Hins (id 19736)
    [
        'station' => ['name' => 'Croix-d\'Hins', 'uic' => '87582437'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1841-05-07',
        'precision' => 'day',
        'source_url' => $wp('Gare de Croix-d\'Hins', 240588260),
        'quote' => 'Elle ouvrit le 7 mai 1841.',
        'reason' => 'article de la gare (oldid 240588260) : « Elle ouvrit le 7 mai 1841. ». Remplace le 07/07/1841 de Wikidata.',
    ],

    // Auzits-Aussibals (id 19918)
    [
        'station' => ['name' => 'Auzits-Aussibals', 'uic' => '87613240'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Auzits-Aussibal', 238728008),
        'quote' => 'En 1875, le conseil général se satisfait d\'avoir obtenu la construction de la station, ou gare, d\'Aussibal ouverte uniquement au service des voyageurs.',
        'reason' => 'article de la gare (oldid 238728008) : « En 1875, le conseil général se satisfait d\'avoir obtenu la construction de la station, ou gare, d\'Aussibal ouverte uniquement au service des voyageurs. ». Remplace le 30/08/1858 déduit de la ligne : la ligne s\'ouvre sans station à Aussibal. Confiance moyenne : l\'article date l\'obtention de la station en 1875, l\'infobox donne 1875.',
    ],

    // Sincey-lès-Rouvray (id 20211)
    [
        'station' => ['name' => 'Sincey-lès-Rouvray', 'uic' => '87694430'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-08-23',
        'precision' => 'day',
        'source_url' => $wp('Gare de Sincey-lès-Rouvray', 232110226),
        'quote' => 'La gare de Sincey est mise en service le 23 août 1882 dans les chemins de fer par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM)',
        'reason' => 'article de la gare (oldid 232110226) : « La gare de Sincey est mise en service le 23 août 1882 dans les chemins de fer par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM) ». Précise ou corrige la date de la base (1876-01-01, wikidata).',
    ],

    // Chatelay-Chissey (id 20330)
    [
        'station' => ['name' => 'Chatelay-Chissey', 'uic' => '87718858'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Chatelay - Chissey', 222067327),
        'quote' => 'Elle est mise en service en 1867 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM)',
        'reason' => 'article de la gare (oldid 222067327) : « Elle est mise en service en 1867 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM) ». Précise ou corrige la date de la base (1870-12-31, déduite de la ligne).',
    ],

    // Cognin (id 20495)
    [
        'station' => ['name' => 'Cognin', 'uic' => '87741405'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1951-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Cognin', 235031390),
        'quote' => 'le projet d\'une halte ferroviaire aboutit en 1951',
        'reason' => 'article de la gare (oldid 235031390) : « le projet d\'une halte ferroviaire aboutit en 1951 ». Remplace le 1884 déduit de la ligne (inaugurée en 1884 sans arrêt à Cognin).',
    ],

    // La Calade-Éguilles (id 20612)
    [
        'station' => ['name' => 'La Calade-Éguilles', 'uic' => '87751396'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1872-05-11',
        'precision' => 'day',
        'source_url' => $wp('Gare de La Calade - Éguilles', 235032396),
        'quote' => 'Ouverte le 11 mai 1872 avec la ligne de Aix-en-Provence à Pertuis',
        'reason' => 'article de la gare (oldid 235032396) : « Ouverte le 11 mai 1872 avec la ligne de Aix-en-Provence à Pertuis ». Précise ou corrige la date de la base (1870-01-31, déduite de la ligne).',
    ],

    // Ste-Anastasie-Garage (id 20648)
    [
        'station' => ['name' => 'Ste-Anastasie-Garage', 'uic' => '87755710'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1880-11-29',
        'precision' => 'day',
        'source_url' => $wp('Gare de Sainte-Anastasie-sur-Issole', 206880925),
        'quote' => 'met en service la station de Sainte-Anastasie lors de l\'ouverture de la section de Carnoules à Trets le 29 novembre 1880',
        'reason' => 'article de la gare (oldid 206880925) : « met en service la station de Sainte-Anastasie lors de l\'ouverture de la section de Carnoules à Trets le 29 novembre 1880 ». Précise ou corrige la date de la base (1880-01-01, wikidata).',
    ],

    // Ners (id 20823)
    [
        'station' => ['name' => 'Ners', 'uic' => '87775338'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1841-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Ners', 235033496),
        'quote' => 'La gare est mise en service en 1841 par la Compagnie des Mines de la Grand\'Combe et des chemins de fer du Gard.',
        'reason' => 'article de la gare (oldid 235033496) : « La gare est mise en service en 1841 par la Compagnie des Mines de la Grand\'Combe et des chemins de fer du Gard. ». Remplace le 19/08/1840 déduit de la ligne : la gare est construite entre 1839 et 1841 et mise en service en 1841.',
    ],

    // Rotangy (id 21061)
    [
        'station' => ['name' => 'Rotangy', 'wikidata' => 'Q60851449'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-04-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Rotangy', 239716639),
        'quote' => 'Il sera ouvert le 15 avril 1876.',
        'reason' => 'article de la gare (oldid 239716639) : « Il sera ouvert le 15 avril 1876. ». Précise ou corrige la date de la base (1874-01-01, déduite de la ligne). Confiance moyenne : la phrase parle de la ligne de Beauvais à Amiens ; l\'infobox de la gare donne 1876.',
    ],

    // Les tramways départementaux de Vesoul (id 21123)
    [
        'station' => ['name' => 'Les tramways départementaux de Vesoul', 'wikidata' => 'Q16638604'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1910-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare des chemins de fer vicinaux de Vesoul', 240982502),
        'quote' => 'Inauguré en 1910, la gare (généralement nommée « gare du Tacot ») a fonctionné, dans le cadre du service ferroviaire précité, de 1910 à 1938.',
        'reason' => 'article de la gare (oldid 240982502) : « Inauguré en 1910, la gare (généralement nommée « gare du Tacot ») a fonctionné, dans le cadre du service ferroviaire précité, de 1910 à 1938. ». Gare des chemins de fer vicinaux (voie métrique), distincte de la gare de Vesoul : remplace le 1858 déduit des lignes de la gare de Vesoul.',
    ],

    // Reuilly (id 21140)
    [
        'station' => ['name' => 'Reuilly', 'uic' => '8711401'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-09-22',
        'precision' => 'day',
        'source_url' => $wp('Gare de Reuilly (Paris)', 239997910),
        'quote' => 'La ligne de Paris-Bastille à Marles-en-Brie est ouverte le 22 septembre 1859.',
        'reason' => 'article de la gare (oldid 239997910) : « La ligne de Paris-Bastille à Marles-en-Brie est ouverte le 22 septembre 1859. ». Remplace le 17/08/1853 déduit de la Petite Ceinture.',
    ],

    // Ravezies (id 21148)
    [
        'station' => ['name' => 'Ravezies', 'uic' => '8723531'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Ravezies', 240915040),
        'quote' => 'La gare est mise en service en 1868 sous le nom de Bordeaux-Saint-Louis en même temps que la ligne du Médoc',
        'reason' => 'article de la gare (oldid 240915040) : « La gare est mise en service en 1868 sous le nom de Bordeaux-Saint-Louis en même temps que la ligne du Médoc ». Précise ou corrige la date de la base (1968-01-01, wikidata). Première gare du lieu (règle du déménagement) : le 1968 de Wikidata est le déplacement de la gare (22/04/1968).',
    ],

    // Saint-Sylvestre-Cappel (id 21159)
    [
        'station' => ['name' => 'Saint-Sylvestre-Cappel', 'wikidata' => 'Q60846721'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1894-09-08',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer de Hazebrouck à Bergues et Hondschoote', 237506140),
        'quote' => 'La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894',
        'reason' => 'article de la ligne « Chemin de fer de Hazebrouck à Bergues et Hondschoote » (oldid 237506140) : « La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894 ». L\'article de la gare confirme l\'année 1894. Remplace le 01/07/1897 (autre section du réseau).',
    ],

    // Wervicq (id 21173)
    [
        'station' => ['name' => 'Wervicq', 'wikidata' => 'Q56884205'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1897-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Wervicq-Sud', 235035066),
        'quote' => 'La gare de Wervicq-Sud est mise en service en 1897 lors de l\'ouverture de la section Frelinghien - Halluin de la ligne de tramway à écartement métrique d\'Armentières à Halluin',
        'reason' => 'article de la gare (oldid 235035066) : « La gare de Wervicq-Sud est mise en service en 1897 lors de l\'ouverture de la section Frelinghien - Halluin de la ligne de tramway à écartement métrique d\'Armentières à Halluin ». Précise ou corrige la date de la base (1895-01-01, déduite de la ligne).',
    ],

    // Winnezeele (id 21206)
    [
        'station' => ['name' => 'Winnezeele', 'wikidata' => 'Q60846727'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1894-09-08',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer de Hazebrouck à Bergues et Hondschoote', 237506140),
        'quote' => 'La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894',
        'reason' => 'article de la ligne « Chemin de fer de Hazebrouck à Bergues et Hondschoote » (oldid 237506140) : « La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894 ». L\'article de la gare confirme l\'année 1894. Remplace le 01/07/1897 (autre section du réseau).',
    ],

    // Étretat (id 21226)
    [
        'station' => ['name' => 'Étretat', 'wikidata' => 'Q113835961'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1895-06-22',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Étretat', 240098795),
        'quote' => 'elle a été mise en service le 22 juin 1895, son terminus étant la gare d\'Étretat qui ouvrit à cette même occasion',
        'reason' => 'article de la gare (oldid 240098795) : « elle a été mise en service le 22 juin 1895, son terminus étant la gare d\'Étretat qui ouvrit à cette même occasion ». Précise ou corrige la date de la base (1895-01-01, déduite de la ligne).',
    ],

    // Jallieu (id 21292)
    [
        'station' => ['name' => 'Jallieu', 'wikidata' => 'Q28041425'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1899-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Jallieu', 172660849),
        'quote' => 'La gare ouvre en 1899, est fermée aux voyageurs en 1931',
        'reason' => 'article de la gare (oldid 172660849) : « La gare ouvre en 1899, est fermée aux voyageurs en 1931 ». Gare des chemins de fer de l\'Est de Lyon (CFEL) : remplace le 1881 déduit d\'une autre ligne.',
    ],

    // Courcelles-Ceinture (id 21294)
    [
        'station' => ['name' => 'Courcelles-Ceinture', 'wikidata' => 'Q21427130'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-03-25',
        'precision' => 'day',
        'source_url' => $wp('Gare de Courcelles-Ceinture', 202583562),
        'quote' => 'La gare de Courcelles-Ceinture est ouverte aux voyageurs le 25 mars 1869 dans les chemins de fer',
        'reason' => 'article de la gare (oldid 202583562) : « La gare de Courcelles-Ceinture est ouverte aux voyageurs le 25 mars 1869 dans les chemins de fer ». Précise ou corrige la date de la base (1854-05-02, déduite de la ligne).',
    ],

    // La Cantraie (id 21378)
    [
        'station' => ['name' => 'La Cantraie', 'wikidata' => 'Q19406991'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1903-01-24',
        'precision' => 'day',
        'source_url' => $wp('Gare de La Cantraie', 166058710),
        'quote' => 'L\'arrêt de La Cantraie est ouvert en 1903 par approbation « à titre précaire » le 24 janvier 1903 du ministre des Travaux Publics.',
        'reason' => 'article de la gare (oldid 166058710) : « L\'arrêt de La Cantraie est ouvert en 1903 par approbation « à titre précaire » le 24 janvier 1903 du ministre des Travaux Publics. ». Remplace le 23/12/1877 déduit de la ligne (arrêt créé 26 ans après).',
    ],

    // Boulogne – Aéroglisseurs (id 21386)
    [
        'station' => ['name' => 'Boulogne – Aéroglisseurs', 'uic' => '8731755'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1970-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Boulogne-Aéroglisseurs', 241115734),
        'quote' => 'La gare de Boulogne-Aéroglisseurs est ouverte en 1970',
        'reason' => 'article de la gare (oldid 241115734) : « La gare de Boulogne-Aéroglisseurs est ouverte en 1970 ». Précise ou corrige la date de la base (1968-01-01, wikidata).',
    ],

    // Ancienne gare (id 21391)
    [
        'station' => ['name' => 'Ancienne gare', 'wikidata' => 'Q1991017'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-01-24',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pierrefonds', 235033780),
        'quote' => 'La nouvelle gare de « Pierrefonds-les-Bains » est inaugurée le 24 janvier 1884 dans les chemins de fer',
        'reason' => 'article de la gare (oldid 235033780) : « La nouvelle gare de « Pierrefonds-les-Bains » est inaugurée le 24 janvier 1884 dans les chemins de fer ». Précise ou corrige la date de la base (1884-01-01, wikidata).',
    ],

    // Killem (id 21438)
    [
        'station' => ['name' => 'Killem', 'wikidata' => 'Q60846737'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1894-09-08',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer de Hazebrouck à Bergues et Hondschoote', 237506140),
        'quote' => 'La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894',
        'reason' => 'article de la ligne « Chemin de fer de Hazebrouck à Bergues et Hondschoote » (oldid 237506140) : « La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894 ». L\'article de la gare confirme l\'année 1894. Remplace le 01/07/1897 (autre section du réseau).',
    ],

    // Mandres-les-Roses (id 21472)
    [
        'station' => ['name' => 'Mandres-les-Roses', 'wikidata' => 'Q96483432'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Mandres', 238222513),
        'quote' => 'La gare est ouverte pour le transport de voyageurs en 1876',
        'reason' => 'article de la gare (oldid 238222513) : « La gare est ouverte pour le transport de voyageurs en 1876 ». Remplace le 31/12/1880 déduit de la ligne.',
    ],

    // Nantes-État (id 21486)
    [
        'station' => ['name' => 'Nantes-État', 'uic' => '8748102'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-04-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Nantes-État', 235033466),
        'quote' => 'exploitée à partir du 15 avril 1876 par la Compagnie des chemins de fer nantais',
        'reason' => 'article de la gare (oldid 235033466) : « exploitée à partir du 15 avril 1876 par la Compagnie des chemins de fer nantais ». Remplace l\'année 1887 d\'OSM.',
    ],

    // Le Plan-Plascassier (id 21497)
    [
        'station' => ['name' => 'Le Plan-Plascassier', 'wikidata' => 'Q78149772'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1892-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare du Plan-Plascassier', 240746165),
        'quote' => 'La « halte du Plan-de-Grasse » est ouverte en 1892 par la Compagnie du PLM.',
        'reason' => 'article de la gare (oldid 240746165) : « La « halte du Plan-de-Grasse » est ouverte en 1892 par la Compagnie du PLM. ». Remplace le 13/11/1871 déduit de la ligne : il n\'y avait pas de halte à la mise en service.',
    ],

    // Cepoy (id 21503)
    [
        'station' => ['name' => 'Cepoy', 'wikidata' => 'Q115271714'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1888-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Cepoy', 200697080),
        'quote' => 'En 1887, la gare de Cepoy est en cours de construction par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM)',
        'reason' => 'article de la gare (oldid 200697080) : « En 1887, la gare de Cepoy est en cours de construction par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM) ». Titre de section « Station PLM (1888-1937) » et infobox « vers 1888 ». Remplace le 14/08/1860 déduit de la ligne. Confiance moyenne.',
    ],

    // La Bourse (id 21552)
    [
        'station' => ['name' => 'La Bourse', 'wikidata' => 'Q27786387'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de la Bourse', 203976606),
        'quote' => 'Elle est achevée en 1857',
        'reason' => 'article de la gare (oldid 203976606) : « Elle est achevée en 1857 ». Remplace le 17/08/1851 déduit de la ligne (la station est créée après la ligne, section inaugurée le 06/04/1853).',
    ],

    // Rexpoëde (id 21564)
    [
        'station' => ['name' => 'Rexpoëde', 'wikidata' => 'Q60846734'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1894-09-08',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer de Hazebrouck à Bergues et Hondschoote', 237506140),
        'quote' => 'La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894',
        'reason' => 'article de la ligne « Chemin de fer de Hazebrouck à Bergues et Hondschoote » (oldid 237506140) : « La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894 ». L\'article de la gare confirme l\'année 1894. Remplace le 01/07/1897 (autre section du réseau).',
    ],

    // Bel-Air (id 21576)
    [
        'station' => ['name' => 'Bel-Air', 'wikidata' => 'Q131343811'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1859-09-22',
        'precision' => 'day',
        'source_url' => $wp('Gare de Bel-Air (Paris)', 239900483),
        'quote' => 'La ligne de Paris-Bastille à Marles-en-Brie est ouverte le 22 septembre 1859.',
        'reason' => 'article de la gare (oldid 239900483) : « La ligne de Paris-Bastille à Marles-en-Brie est ouverte le 22 septembre 1859. ». Remplace le 17/08/1853 déduit de la Petite Ceinture.',
    ],

    // Le Thillot (id 21619)
    [
        'station' => ['name' => 'Le Thillot', 'uic' => '8714454'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1879-11-07',
        'precision' => 'day',
        'source_url' => $wp('Gare du Thillot', 176707893),
        'quote' => 'Mise en service en 1879 par la Compagnie des chemins de fer de l\'Est',
        'reason' => 'article de la gare (oldid 176707893) : « Mise en service en 1879 par la Compagnie des chemins de fer de l\'Est ». Précise ou corrige la date de la base (1879-01-01, wikidata). L\'infobox donne le 7 novembre 1879, date de la section de Remiremont à Saint-Maurice-sur-Moselle de la ligne d\'Épinal à Bussang (oldid 219647311). Confiance moyenne.',
    ],

    // Tocqueville - Gouberville (id 21631)
    [
        'station' => ['name' => 'Tocqueville - Gouberville', 'wikidata' => 'Q2689720'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1911-07-08',
        'precision' => 'day',
        'source_url' => $wp('Gare de Tocqueville - Gouberville', 202420186),
        'quote' => 'La gare de Tocqueville - Gouberville est mise en service le 8 juillet 1911 par la Compagnie des chemins de fer de la Manche (CFM)',
        'reason' => 'article de la gare (oldid 202420186) : « La gare de Tocqueville - Gouberville est mise en service le 8 juillet 1911 par la Compagnie des chemins de fer de la Manche (CFM) ». Précise ou corrige la date de la base (1886-01-01, wikidata).',
    ],

    // Boulevard Ornano (id 21731)
    [
        'station' => ['name' => 'Boulevard Ornano', 'wikidata' => 'Q16508091'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare du boulevard Ornano', 241230868),
        'quote' => 'La gare est ouverte aux voyageurs en 1869.',
        'reason' => 'article de la gare (oldid 241230868) : « La gare est ouverte aux voyageurs en 1869. ». Remplace le 1862 déduit de la ligne de Petite Ceinture.',
    ],

    // Grenelle-Ceinture (id 21809)
    [
        'station' => ['name' => 'Grenelle-Ceinture', 'wikidata' => 'Q16508000'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1867-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Grenelle-Ceinture', 235031977),
        'quote' => 'La gare de Grenelle-Ceinture est mise en service en 1867.',
        'reason' => 'article de la gare (oldid 235031977) : « La gare de Grenelle-Ceinture est mise en service en 1867. ». Remplace le 1862 déduit de la ligne de Petite Ceinture.',
    ],

    // Avenue de Saint-Ouen (id 21878)
    [
        'station' => ['name' => 'Avenue de Saint-Ouen', 'wikidata' => 'Q16507976'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1863-11-16',
        'precision' => 'day',
        'source_url' => $wp('Gare de l\'avenue de Saint-Ouen', 228129603),
        'quote' => 'La gare est ouverte aux voyageurs le 16 novembre 1863 dans les chemins de fer',
        'reason' => 'article de la gare (oldid 228129603) : « La gare est ouverte aux voyageurs le 16 novembre 1863 dans les chemins de fer ». Remplace le 1862 déduit de la ligne de Petite Ceinture.',
    ],

    // Herzeele (id 21907)
    [
        'station' => ['name' => 'Herzeele', 'wikidata' => 'Q60846730'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1894-09-08',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer de Hazebrouck à Bergues et Hondschoote', 237506140),
        'quote' => 'La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894',
        'reason' => 'article de la ligne « Chemin de fer de Hazebrouck à Bergues et Hondschoote » (oldid 237506140) : « La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894 ». L\'article de la gare confirme l\'année 1894. Remplace le 01/07/1897 (autre section du réseau).',
    ],

    // Pont-d'Espenel (id 21911)
    [
        'station' => ['name' => 'Pont-d\'Espenel', 'wikidata' => 'Q71561821'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1898-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare du Pont-d\'Espenel', 229517806),
        'quote' => 'annonce dans le journal officiel, du 28 novembre 1898, l\'ouverture « de la halte du Pont-d\'Espenel (Drôme) au service des voyageurs',
        'reason' => 'article de la gare (oldid 229517806) : « annonce dans le journal officiel, du 28 novembre 1898, l\'ouverture « de la halte du Pont-d\'Espenel (Drôme) au service des voyageurs ». Remplace le 01/09/1885 déduit de la ligne (section de Crest à Die) : la halte ouvre 13 ans plus tard.',
    ],

    // La Tâche (id 21920)
    [
        'station' => ['name' => 'La Tâche', 'wikidata' => 'Q107142614'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1909-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de La Tâche', 241339803),
        'quote' => 'les CFD autorisent par délibération, le 29 mars 1908 la construction d\'une halte à l\'emplacement de La Tâche',
        'reason' => 'article de la gare (oldid 241339803) : « les CFD autorisent par délibération, le 29 mars 1908 la construction d\'une halte à l\'emplacement de La Tâche ». L\'infobox donne 1909. Remplace le 1896 déduit de la ligne. Confiance moyenne.',
    ],

    // Bambecque (id 21921)
    [
        'station' => ['name' => 'Bambecque', 'wikidata' => 'Q60846732'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1894-09-08',
        'precision' => 'day',
        'source_url' => $wp('Chemin de fer de Hazebrouck à Bergues et Hondschoote', 237506140),
        'quote' => 'La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894',
        'reason' => 'article de la ligne « Chemin de fer de Hazebrouck à Bergues et Hondschoote » (oldid 237506140) : « La ligne Hazebrouck - Hondschoote et son embranchement de Rexpoëde à Bergues (Porte de Cassel) fut ouverte le 8 septembre 1894 ». L\'article de la gare confirme l\'année 1894. Remplace le 01/07/1897 (autre section du réseau).',
    ],

    // Brézilhou (id 21932)
    [
        'station' => ['name' => 'Brézilhou', 'wikidata' => 'Q86724318'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1938-01-01',
        'precision' => 'year',
        'source_url' => $wp('Halte de Brézilhou', 224625189),
        'quote' => 'Cette halte a été mise en service vers 1938 lorsque la SNCF a repris la ligne.',
        'reason' => 'article de la gare (oldid 224625189) : « Cette halte a été mise en service vers 1938 lorsque la SNCF a repris la ligne. ». Date approximative (« vers »). Remplace le 01/07/1878 déduit de la ligne. Confiance moyenne.',
    ],

    // Massia (id 21943)
    [
        'station' => ['name' => 'Massia', 'wikidata' => 'Q86724368'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1956-01-01',
        'precision' => 'year',
        'source_url' => $wp('Halte de Massia', 209241219),
        'quote' => 'Cette halte a été mise en service vers 1956 lorsque la SNCF a repris la ligne.',
        'reason' => 'article de la gare (oldid 209241219) : « Cette halte a été mise en service vers 1956 lorsque la SNCF a repris la ligne. ». Date approximative (« vers »). Remplace le 01/07/1878 déduit de la ligne. Confiance moyenne.',
    ],

    // Limours-État (id 21965)
    [
        'station' => ['name' => 'Limours-État', 'wikidata' => 'Q107205111'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1930-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Limours-État', 204050069),
        'quote' => 'mais ouverte seulement en 1930',
        'reason' => 'article de la gare (oldid 204050069) : « mais ouverte seulement en 1930 ». Remplace l\'année 1922 de Wikidata (gare achevée en 1922, premiers trains en 1930).',
    ],

    // ── 44. Fermetures des gares fermées dont la date de fermeture était déduite de la ligne (audit-fermetures-2, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/audit-fermetures-2.md). Les gares y ont souvent fermé avant la ligne (fermeture voyageurs du
    // 15/05/1939, du 28/09/1969, de 1980 pour la desserte par autocars), la date déduite étant celle du déclassement, de la fin du fret
    // ou de la fermeture totale. Seules les gares dont l'article (de la gare ou de la ligne, révision citée) dit la fermeture sont corrigées ;
    // les autres restent à décider ou sans source (voir le CSV du rapport).

    // Aire-sur-l'Adour (id 17277)
    [
        'station' => ['name' => 'Aire-sur-l\'Adour', 'wikidata' => 'Q115386235'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-10-12',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Aire-sur-l\'Adour', 220918422),
        'quote' => 'et est fermée aux voyageurs en 1970',
        'reason' => 'article de la gare (oldid 220918422) : « et est fermée aux voyageurs en 1970 » ; Infobox : fermeture voyageurs le 12/10/1970 ; 2014 deduit de la ligne.',
    ],

    // Joudreville (id 18695)
    [
        'station' => ['name' => 'Joudreville', 'wikidata' => 'Q123498378'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Joudreville', 209684329),
        'quote' => 'La SNCF met fin aux trains de voyageurs sur la ligne le 15 mai 1939',
        'reason' => 'article de la gare (oldid 209684329) : « La SNCF met fin aux trains de voyageurs sur la ligne le 15 mai 1939 » ; Fermeture voyageurs du 15/05/1939 ; 1986 etait deduit de la ligne (fret jusqu\'en 1991).',
    ],

    // Landres (id 18697)
    [
        'station' => ['name' => 'Landres', 'wikidata' => 'Q123498388'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Landres', 209700791),
        'quote' => 'Les trains de voyageurs cessent de desservir la ligne le 15 mai 1939',
        'reason' => 'article de la gare (oldid 209700791) : « Les trains de voyageurs cessent de desservir la ligne le 15 mai 1939 » ; Fermeture voyageurs du 15/05/1939 ; 1973 etait deduit de la ligne (fret jusqu\'en 1991).',
    ],

    // Aumetz (id 18735)
    [
        'station' => ['name' => 'Aumetz', 'wikidata' => 'Q123498291'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1948-05-05',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Aumetz', 230742995),
        'quote' => 'la ligne ferme aux trains de passagers le 5 mai 1948',
        'reason' => 'article de la gare (oldid 230742995) : « la ligne ferme aux trains de passagers le 5 mai 1948 » ; Fermeture voyageurs du 05/05/1948 ; 2005 deduit de la ligne.',
    ],

    // Roisel (id 18946)
    [
        'station' => ['name' => 'Roisel', 'wikidata' => 'Q2566610'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-04-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Roisel', 212959250),
        'quote' => 'Le trafic voyageurs a été reporté sur la route le 1 avril 1970.',
        'reason' => 'article de la gare (oldid 212959250) : « Le trafic voyageurs a été reporté sur la route le 1 avril 1970. » ; Trafic voyageurs reporte sur la route le 01/04/1970 ; 2010 deduit de la ligne.',
    ],

    // Mortagne-au-Perche (id 19316)
    [
        'station' => ['name' => 'Mortagne-au-Perche', 'wikidata' => 'Q109945175'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1953-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Mortagne-au-Perche', 234442336),
        'quote' => 'La gare fermera au service voyageurs en 1953.',
        'reason' => 'article de la gare (oldid 234442336) : « La gare fermera au service voyageurs en 1953. » ; Infobox : fermeture voyageurs 1953 (ligne d\'Alencon a Conde fermee aux voyageurs en 1935) ; 1989 deduit de la ligne.',
    ],

    // Gaël (id 19379)
    [
        'station' => ['name' => 'Gaël', 'wikidata' => 'Q26021574'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1972-03-06',
        'precision' => 'day',
        'source_url' => $wp('Gare de Gaël', 229514833),
        'quote' => 'Elle est fermée au service des voyageurs le 6 mars 1972.',
        'reason' => 'article de la gare (oldid 229514833) : « Elle est fermée au service des voyageurs le 6 mars 1972. » ; Fermeture voyageurs du 06/03/1972 (toute la ligne) ; 2018 deduit de la ligne (fret rouvert en 2009).',
    ],

    // Airvault-Ville (id 19528)
    [
        'station' => ['name' => 'Airvault-Ville', 'wikidata' => 'Q82784422'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Airvault-Ville', 188243396),
        'quote' => 'Elle est fermée par la Société nationale des chemins de fer français (SNCF) lorsqu\'elle transfère sur route, en 1980, les dernières circulations omnibus du service des voyageurs.',
        'reason' => 'article de la gare (oldid 188243396) : « Elle est fermée par la Société nationale des chemins de fer français (SNCF) lorsqu\'elle transfère sur route, en 1980, les dernières circulations omnibus du service des voyageurs. » ; Fermee aux voyageurs en 1980 (transfert sur route) ; 2015 deduit de la ligne.',
    ],

    // Gourge (id 19530)
    [
        'station' => ['name' => 'Gourge', 'wikidata' => 'Q83144554'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-09-27',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Chartres à Bordeaux-Saint-Jean', 241089765),
        'quote' => 'de Thouars à Parthenay et Niort au 27 septembre 1980',
        'reason' => 'article de la ligne (oldid 241089765) : « de Thouars à Parthenay et Niort au 27 septembre 1980 » ; Fermeture voyageurs de la section Thouars - Parthenay - Niort le 27/09/1980 (article de la ligne ; la gare : « fermee au service des voyageurs en 1980 ») ; 2015 est la fermeture a toutes les circulations.',
    ],

    // St-André-de-Lidon (id 19595)
    [
        'station' => ['name' => 'St-André-de-Lidon', 'wikidata' => 'Q24575294'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-André-de-Lidon', 220862797),
        'quote' => 'La gare, comme l\'ensemble de la ligne, est fermée au service des voyageurs 15 05 1939.',
        'reason' => 'article de la gare (oldid 220862797) : « La gare, comme l\'ensemble de la ligne, est fermée au service des voyageurs 15 05 1939. » ; Fermeture voyageurs du 15/05/1939 ; 2000 deduit de la ligne.',
    ],

    // Chauvigny (id 19689)
    [
        'station' => ['name' => 'Chauvigny', 'wikidata' => 'Q28040128'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Chauvigny', 150577964),
        'quote' => 'Elle est fermée au service des voyageurs en 1940',
        'reason' => 'article de la gare (oldid 150577964) : « Elle est fermée au service des voyageurs en 1940 » ; Fermee aux voyageurs en 1940 ; 1989 deduit de la ligne.',
    ],

    // Concremiers (id 19853)
    [
        'station' => ['name' => 'Concremiers', 'wikidata' => 'Q28040130'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Concremiers', 228675721),
        'quote' => 'Elle est fermée au service des voyageurs en 1940',
        'reason' => 'article de la gare (oldid 228675721) : « Elle est fermée au service des voyageurs en 1940 » ; Fermee aux voyageurs en 1940 ; 1973 deduit de la ligne.',
    ],

    // Ingrandes-Mérigny (id 19854)
    [
        'station' => ['name' => 'Ingrandes-Mérigny', 'wikidata' => 'Q3095792'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-27',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Ingrandes - Mérigny', 229526879),
        'quote' => 'Elle est officiellement fermée au service des voyageurs le 27 mai 1940',
        'reason' => 'article de la gare (oldid 229526879) : « Elle est officiellement fermée au service des voyageurs le 27 mai 1940 » ; Fermeture voyageurs du 27/05/1940 ; 1973 deduit de la ligne.',
    ],

    // St-Hilaire (Indre) (id 19855)
    [
        'station' => ['name' => 'St-Hilaire (Indre)', 'wikidata' => 'Q15948845'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1933-03-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Hilaire (Indre)', 228675946),
        'quote' => 'Infobox, fermeture : 1 mars 1933 (voyageurs)',
        'reason' => 'article de la gare (oldid 228675946) : Infobox, fermeture : 1 mars 1933 (voyageurs). Infobox : fermeture voyageurs le 01/03/1933 ; 1993 est le declassement de la ligne.',
    ],

    // Liglet (id 19856)
    [
        'station' => ['name' => 'Liglet', 'wikidata' => 'Q26714973'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1933-03-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Liglet', 235032634),
        'quote' => 'La gare est fermée, comme la ligne, au service des voyageurs le 1 mars 1933.',
        'reason' => 'article de la gare (oldid 235032634) : « La gare est fermée, comme la ligne, au service des voyageurs le 1 mars 1933. » ; Fermeture voyageurs du 01/03/1933 ; 1993 est le declassement de la ligne.',
    ],

    // St-Denis-de-Jouhet (id 19867)
    [
        'station' => ['name' => 'St-Denis-de-Jouhet', 'wikidata' => 'Q16638591'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-07-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Denis-de-Jouhet', 228675941),
        'quote' => 'La gare, comme la ligne, est fermée au service des voyageurs le 1 juillet 1939.',
        'reason' => 'article de la gare (oldid 228675941) : « La gare, comme la ligne, est fermée au service des voyageurs le 1 juillet 1939. » ; Fermeture voyageurs du 01/07/1939 ; 1995 est le declassement de la section.',
    ],

    // Larnagol-Calvignac (id 19937)
    [
        'station' => ['name' => 'Larnagol-Calvignac', 'wikidata' => 'Q113462421'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Calvignac', 236444829),
        'quote' => 'Infobox, fermeture : 28 septembre 1980 (voyageurs)',
        'reason' => 'article de la gare (oldid 236444829) : Infobox, fermeture : 28 septembre 1980 (voyageurs). Infobox : fermeture voyageurs le 28/09/1980 (ligne de Cahors a Capdenac) ; 2011 deduit de la ligne.',
    ],

    // Montbrun (id 19939)
    [
        'station' => ['name' => 'Montbrun', 'wikidata' => 'Q113458917'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1980-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Montbrun', 237108391),
        'quote' => 'Infobox, fermeture : 28 septembre 1980 (voyageurs)',
        'reason' => 'article de la gare (oldid 237108391) : Infobox, fermeture : 28 septembre 1980 (voyageurs). Infobox : fermeture voyageurs le 28/09/1980 (ligne de Cahors a Capdenac) ; 2011 deduit de la ligne.',
    ],

    // Noyant-d'Allier (id 20006)
    [
        'station' => ['name' => 'Noyant-d\'Allier', 'wikidata' => 'Q16508055'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1972-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Noyant-d\'Allier', 235033632),
        'quote' => 'La gare de Noyant-d\'Allier est fermée au service des voyageurs en 1972, lors de la fermeture de la section de Commentry à Moulins.',
        'reason' => 'article de la gare (oldid 235033632) : « La gare de Noyant-d\'Allier est fermée au service des voyageurs en 1972, lors de la fermeture de la section de Commentry à Moulins. » ; Fermee aux voyageurs en 1972 avec la section Commentry - Moulins ; 1999 deduit de la ligne.',
    ],

    // Champtercier (id 20617)
    [
        'station' => ['name' => 'Champtercier', 'wikidata' => 'Q20971045'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1972-03-06',
        'precision' => 'day',
        'source_url' => $wp('Gare de Champtercier', 200688849),
        'quote' => 'Elle est fermée le 6 mars 1972 avec la fin du trafic omnibus',
        'reason' => 'article de la gare (oldid 200688849) : « Elle est fermée le 6 mars 1972 avec la fin du trafic omnibus » ; Fin du trafic omnibus le 06/03/1972 ; la ligne ferme totalement le 22/05/1991, 1988 non retrouve.',
    ],

    // Draguignan (id 20655)
    [
        'station' => ['name' => 'Draguignan', 'wikidata' => 'Q30739585'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1981-01-01',
        'precision' => 'year',
        'source_url' => $wp('Anciennes gares ferroviaires de Draguignan', 206881532),
        'quote' => 'En 1981, la gare de Draguignan est fermée au trafic ferroviaire',
        'reason' => 'article de la gare (oldid 206881532) : « En 1981, la gare de Draguignan est fermée au trafic ferroviaire » ; Fermee au trafic ferroviaire en 1981 (devient un point de vente) ; 1992 deduit de la ligne.',
    ],

    // Céret (id 20911)
    [
        'station' => ['name' => 'Céret', 'wikidata' => 'Q81720221'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-23',
        'precision' => 'day',
        'source_url' => $wp('Gare de Céret', 235031546),
        'quote' => 'fermée au service des voyageurs en 1940',
        'reason' => 'article de la gare (oldid 235031546) : « fermée au service des voyageurs en 1940 » ; Infobox : fermeture voyageurs le 23/05/1940 ; 2014 deduit de la ligne.',
    ],

    // Drincham (id 21202)
    [
        'station' => ['name' => 'Drincham', 'wikidata' => 'Q60847117'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Drincham', 231973436),
        'quote' => 'Elle est fermée en 1951 lors de la suppression de la ligne.',
        'reason' => 'article de la gare (oldid 231973436) : « Elle est fermée en 1951 lors de la suppression de la ligne. » ; Fermee avec la ligne en 1951 ; 1944 deduit de la ligne.',
    ],

    // Ouzouer - Dampierre (id 21222)
    [
        'station' => ['name' => 'Ouzouer - Dampierre', 'wikidata' => 'Q61896217'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Ouzouer - Dampierre', 235030574),
        'quote' => 'la suppression du trafic voyageur intervient le 15 mai 1939',
        'reason' => 'article de la gare (oldid 235030574) : « la suppression du trafic voyageur intervient le 15 mai 1939 » ; Fermeture voyageurs du 15/05/1939 ; 1954 deduit de la ligne.',
    ],

    // Saint-Aigny - Le Blanc (id 21258)
    [
        'station' => ['name' => 'Saint-Aigny - Le Blanc', 'wikidata' => 'Q28040132'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-27',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Aigny - Le Blanc', 238289077),
        'quote' => 'Elle est officiellement fermée au service des voyageurs le 27 mai 1940',
        'reason' => 'article de la gare (oldid 238289077) : « Elle est officiellement fermée au service des voyageurs le 27 mai 1940 » ; Fermeture voyageurs du 27/05/1940 ; 1994 deduit de la ligne.',
    ],

    // Rosteig (id 21268)
    [
        'station' => ['name' => 'Rosteig', 'wikidata' => 'Q22340663'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Rosteig', 235034165),
        'quote' => 'Elle est fermée au service des voyageurs, comme la ligne, le 28 septembre 1969.',
        'reason' => 'article de la gare (oldid 235034165) : « Elle est fermée au service des voyageurs, comme la ligne, le 28 septembre 1969. » ; Fermeture voyageurs du 28/09/1969 ; 1973-07-26 est le declassement de la ligne.',
    ],

    // Saint-Louis-lès-Bitche (id 21291)
    [
        'station' => ['name' => 'Saint-Louis-lès-Bitche', 'wikidata' => 'Q22341813'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Louis-lès-Bitche', 229519298),
        'quote' => 'Infobox, fermeture : 28 septembre 1969 (voyageurs)',
        'reason' => 'article de la gare (oldid 229519298) : Infobox, fermeture : 28 septembre 1969 (voyageurs). Infobox : fermeture voyageurs le 28/09/1969 ; 1973-07-26 est le declassement de la ligne.',
    ],

    // Montmorency (id 21441)
    [
        'station' => ['name' => 'Montmorency', 'wikidata' => 'Q16508052'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1954-06-30',
        'precision' => 'day',
        'source_url' => $wp('Gare de Montmorency', 212402047),
        'quote' => 'elle est fermée en 1954',
        'reason' => 'article de la gare (oldid 212402047) : « elle est fermée en 1954 » ; Infobox : fermeture voyageurs le 30/06/1954 ; 1947 non retrouve dans l\'article.',
    ],

    // Soucht (id 21448)
    [
        'station' => ['name' => 'Soucht', 'wikidata' => 'Q22343124'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1969-09-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Soucht', 216787616),
        'quote' => 'Elle est fermée au service des voyageurs, comme la ligne, le 28 septembre 1969.',
        'reason' => 'article de la gare (oldid 216787616) : « Elle est fermée au service des voyageurs, comme la ligne, le 28 septembre 1969. » ; Fermeture voyageurs du 28/09/1969 ; 1973-07-26 est le declassement de la ligne.',
    ],

    // Pouligny-Saint-Pierre (id 21673)
    [
        'station' => ['name' => 'Pouligny-Saint-Pierre', 'wikidata' => 'Q3097334'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-06-27',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pouligny-Saint-Pierre', 228675912),
        'quote' => 'Elle est fermée au service voyageurs, le 27 juin 1940.',
        'reason' => 'article de la gare (oldid 228675912) : « Elle est fermée au service voyageurs, le 27 juin 1940. » ; Fermeture voyageurs du 27/06/1940 ; 1970 est le declassement de la ligne.',
    ],

    // Cogolin La Foux (id 21677)
    [
        'station' => ['name' => 'Cogolin La Foux', 'wikidata' => 'Q108154002'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1948-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Cogolin La Foux', 239464028),
        'quote' => 'fut officiellement fermée en 1948',
        'reason' => 'article de la gare (oldid 239464028) : « fut officiellement fermée en 1948 » ; Fermeture officielle de la ligne en 1948 (infobox 1948) ; 1949 deduit de la ligne.',
    ],

    // Pitgam (id 21714)
    [
        'station' => ['name' => 'Pitgam', 'wikidata' => 'Q56753715'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1951-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Pitgam', 217416935),
        'quote' => 'Elle est fermée en 1951 lors de la suppression de la ligne.',
        'reason' => 'article de la gare (oldid 217416935) : « Elle est fermée en 1951 lors de la suppression de la ligne. » ; Fermee avec la ligne en 1951 ; 1944 deduit de la ligne.',
    ],

    // Audun-le-Tiche-Mont (id 21825)
    [
        'station' => ['name' => 'Audun-le-Tiche-Mont', 'wikidata' => 'Q123498162'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1948-05-05',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Audun-le-Tiche-Mont', 230682739),
        'quote' => 'La SNCF supprime le trafic des passagers sur la ligne de Fontoy à Audun-le-Tiche le 5 mai 1948.',
        'reason' => 'article de la gare (oldid 230682739) : « La SNCF supprime le trafic des passagers sur la ligne de Fontoy à Audun-le-Tiche le 5 mai 1948. » ; Fermeture voyageurs du 05/05/1948 ; 2009 deduit de la ligne.',
    ],

    // Régneville-sur-Mer (id 21884)
    [
        'station' => ['name' => 'Régneville-sur-Mer', 'wikidata' => 'Q65157674'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1942-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Regnéville', 239621002),
        'quote' => 'Infobox, fermeture : 1942',
        'reason' => 'article de la gare (oldid 239621002) : Infobox, fermeture : 1942. Infobox : fermeture 1942 ; 1939 deduit de la ligne.',
    ],

    // Anderny (id 21941)
    [
        'station' => ['name' => 'Anderny', 'wikidata' => 'Q123497996'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Anderny', 238957889),
        'quote' => 'La SNCF cesse de desservir la ligne par des trains de voyageurs le 15 mai 1939',
        'reason' => 'article de la gare (oldid 238957889) : « La SNCF cesse de desservir la ligne par des trains de voyageurs le 15 mai 1939 » ; Fermeture voyageurs du 15/05/1939 (fiche de la gare) ; 1973 etait une date deduite de la ligne (fret jusqu\'en 1991).',
    ],

    // Belvianes (id 21946)
    [
        'station' => ['name' => 'Belvianes', 'wikidata' => 'Q81075822'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-04-18',
        'precision' => 'day',
        'source_url' => $wp('Gare de Belvianes', 228481627),
        'quote' => 'La section entre Quillan et Rivesaltes a été fermée au trafic voyageurs le 18 04 1939.',
        'reason' => 'article de la gare (oldid 228481627) : « La section entre Quillan et Rivesaltes a été fermée au trafic voyageurs le 18 04 1939. » ; Fermeture voyageurs du 18/04/1939 (infobox) ; 1956 deduit de la ligne.',
    ],

    // Amélie-les-Bains (id 21950)
    [
        'station' => ['name' => 'Amélie-les-Bains', 'wikidata' => 'Q82925388'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-05-23',
        'precision' => 'day',
        'source_url' => $wp('Gare d\'Amélie-les-Bains', 231707538),
        'quote' => 'fermée au service des voyageurs en 1940',
        'reason' => 'article de la gare (oldid 231707538) : « fermée au service des voyageurs en 1940 » ; Infobox : fermeture voyageurs le 23/05/1940 ; 1972 est la fermeture du troncon Amelie-les-Bains - Ceret (fret).',
    ],

    // Saint-Tropez (id 21979)
    [
        'station' => ['name' => 'Saint-Tropez', 'wikidata' => 'Q104162986'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1949-06-04',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Tropez', 221816503),
        'quote' => 'jusqu\'à sa fermeture le 4 06 1949',
        'reason' => 'article de la gare (oldid 221816503) : « jusqu\'à sa fermeture le 4 06 1949 » ; Jour precise : 04/06/1949 (l\'annee 1949 etait deduite de la ligne).',
    ],

    // ── 45. Ouvertures de 150 gares encore ouvertes de 0,148 M à 0,05 M de voyageurs (maximum depuis 2019), que les passes précédentes n'avaient
    // pas lues, relues contre l'article de la gare (révision citée) et la chronologie de l'article de la ligne : 20 corrigées
    // (audit-gares-6, 23/11/2026, rapport storage/app/reports/agents/aprem-2111/audit-gares-6.md). Les 3 cas douteux restent à décider
    // (Sainte-Colombe-Septveilles, Montaudran, Neufchâteau).

    // Bagneaux-sur-Loing (id 17419)
    [
        'station' => ['name' => 'Bagneaux-sur-Loing', 'uic' => '87684191'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1916-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Bagneaux-sur-Loing', 231119820),
        'quote' => 'Mise en service en 1916 par la Compagnie des chemins de fer de Paris à Lyon et à la Méditerranée (PLM)',
        'reason' => 'article de la gare « Gare de Bagneaux-sur-Loing » (oldid 231119820) : la halte date de 1916 (le texte précise qu\'il n\'existait pas d\'arrêt à Bagneaux à l\'ouverture de la ligne). Remplace le 14/08/1860 déduit de la ligne.',
    ],

    // St-Roch (Somme) (id 15742)
    [
        'station' => ['name' => 'St-Roch (Somme)', 'uic' => '87313080'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Saint-Roch (Somme)', 241300034),
        'quote' => 'mise en service = 1875 (marchandises), 1876 (voyageurs)',
        'reason' => 'article de la gare « Gare de Saint-Roch (Somme) » (oldid 241300034) : infobox : 1875 pour les marchandises, 1876 pour les voyageurs ; la gare n\'a pas été ouverte avec la ligne de 1847. Remplace l\'année 1847 de Wikidata.',
    ],

    // Pauillac (id 16849)
    [
        'station' => ['name' => 'Pauillac', 'uic' => '87581256'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1870-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Pauillac', 241414232),
        'quote' => 'mise en service = 1870 dans les chemins de fer',
        'reason' => 'article de la gare « Gare de Pauillac » (oldid 241414232) : infobox : 1870, ouverture de la gare avec l\'extension jusqu\'à Pauillac de la ligne du Médoc. Remplace l\'année 1868 déduite de la ligne.',
    ],

    // Artix (id 17297)
    [
        'station' => ['name' => 'Artix', 'uic' => '87672212'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1863-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Artix', 238760141),
        'quote' => 'La gare d’Artix est mise en service en 1863 par la Compagnie des chemins de fer du Midi, lors de l’ouverture de la section de ligne entre Pau et Puyoô.',
        'reason' => 'article de la gare « Gare d\'Artix » (oldid 238760141) : mise en service en 1863 avec la section Pau - Puyoô. Remplace l\'année 1867 déduite de la ligne.',
    ],

    // Puyoô (id 17301)
    [
        'station' => ['name' => 'Puyoô', 'uic' => '87672279'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1863-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Artix', 238760141),
        'quote' => 'La gare d’Artix est mise en service en 1863 par la Compagnie des chemins de fer du Midi, lors de l’ouverture de la section de ligne entre Pau et Puyoô.',
        'reason' => 'article de la gare d\'Artix (oldid 238760141) : la section de ligne entre Pau et Puyoô est ouverte en 1863, Puyoô en est le terminus ; l\'article de Puyoô (oldid 239298922) dit seulement « La gare a été construite en 1862 ». Remplace l\'année 1867 déduite de la ligne. Confiance moyenne.',
    ],

    // Meroux : non corrigé. Seule l'infobox de « Gare de Belfort - Montbéliard TGV » (oldid 240819248) donne le 8 décembre 2018 ;
    // la ligne de Belfort à Delle a rouvert au service voyageurs le 09/12/2018, date gardée (une seule source contre la chronologie).

    // Pléchâtel (id 16319)
    [
        'station' => ['name' => 'Pléchâtel', 'uic' => '87471177'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-11-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Pléchâtel', 240863371),
        'quote' => 'La gare de Pléchâtel est mise en service en novembre 1864, par la compagnie des chemins de fer de l\'Ouest, soit deux ans après l\'inauguration de la ligne.',
        'reason' => 'article de la gare « Gare de Pléchâtel » (oldid 240863371) : mise en service en novembre 1864, deux ans après l\'ouverture de la ligne (infobox : 1864). Remplace l\'année 1862 de Wikidata.',
    ],

    // Guéthary (id 17324)
    [
        'station' => ['name' => 'Guéthary', 'uic' => '87673459'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Guéthary', 238892606),
        'quote' => 'La gare a été inaugurée en 1884, vingt ans après la mise en service du tronçon Bayonne – Irun (1864)',
        'reason' => 'article de la gare « Gare de Guéthary » (oldid 238892606) : gare inaugurée en 1884, vingt ans après le tronçon Bayonne - Irun. Remplace le 21/04/1864 déduit de la ligne.',
    ],

    // Margaux (id 16847)
    [
        'station' => ['name' => 'Margaux', 'uic' => '87581215'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Margaux', 223773147),
        'quote' => 'La gare a été mise en service en même temps que la première partie de la ligne du Médoc, en 1868.',
        'reason' => 'article de la gare « Gare de Margaux » (oldid 223773147) : mise en service avec la première partie de la ligne du Médoc, en 1868 (le Verdon n\'est atteint qu\'en 1875). Remplace le 01/03/1884 déduit de la ligne. Confiance moyenne.',
    ],

    // Noeux-les-Mines (id 15883)
    [
        'station' => ['name' => 'Noeux-les-Mines', 'uic' => '87342139'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1861-10-15',
        'precision' => 'day',
        'source_url' => $wp('Gare de Nœux-les-Mines', 238682988),
        'quote' => 'mise en service = 15 octobre 1861 dans les chemins de fer',
        'reason' => 'article de la gare « Gare de Nœux-les-Mines » (oldid 238682988) : infobox et section Histoire : la station est mise en service le 15 octobre 1861 lors de l\'ouverture de la section Lens - Béthune. Précise l\'année 1861 de Wikidata au jour.',
    ],

    // Lutzelbourg (id 15447)
    [
        'station' => ['name' => 'Lutzelbourg', 'uic' => '87215160'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1851-05-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Lutzelbourg', 241364369),
        'quote' => 'mise en service (avec un bâtiment voyageurs provisoire) par la Compagnie du chemin de fer de Paris à Strasbourg en mai 1851',
        'reason' => 'article de la gare « Gare de Lutzelbourg » (oldid 241364369) : mise en service en mai 1851 lors de l\'inauguration de la section Strasbourg - Sarrebourg. Précise l\'année 1851 de Wikidata au mois.',
    ],

    // Retiers (id 16330)
    [
        'station' => ['name' => 'Retiers', 'uic' => '87471359'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-12-01',
        'precision' => 'month',
        'source_url' => $wp('Gare de Retiers', 236827897),
        'quote' => 'La gare de Retiers est ouverte lors de la mise en service de la ligne en décembre 1881.',
        'reason' => 'article de la gare « Gare de Retiers » (oldid 236827897) : ouverture avec la ligne en décembre 1881. Précise l\'année 1881 de Wikidata au mois.',
    ],

    // Mende (id 18232)
    [
        'station' => ['name' => 'Mende', 'uic' => '87783605'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1884-05-03',
        'precision' => 'day',
        'source_url' => $wp('Gare de Mende', 235795184),
        'quote' => 'La gare de Mende est mise en service le 3 mai 1884, lors de l\'ouverture de la section du Monastier à Mende.',
        'reason' => 'article de la gare « Gare de Mende » (oldid 235795184) : infobox et section Histoire : le 3 mai 1884. Précise l\'année 1884 de Wikidata au jour.',
    ],

    // Morez (id 17584)
    [
        'station' => ['name' => 'Morez', 'uic' => '87715441'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1900-08-12',
        'precision' => 'day',
        'source_url' => $wp('Gare de Morez', 234924903),
        'quote' => 'La gare était, à son ouverture le 12 août 1900 dans les chemins de fer, le terminus de la ligne venant d\'Andelot.',
        'reason' => 'article de la gare « Gare de Morez » (oldid 234924903) : infobox et section Histoire : le 12 août 1900. Précise l\'année 1900 de Wikidata au jour.',
    ],

    // Delle (id 15248)
    [
        'station' => ['name' => 'Delle', 'uic' => '87184440'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-06-29',
        'precision' => 'day',
        'source_url' => $wp('Gare de Delle', 232254189),
        'quote' => 'Le chemin de fer arrive à Delle le 29 juin 1868',
        'reason' => 'article de la gare « Gare de Delle » (oldid 232254189) : le chemin de fer arrive à Delle le 29 juin 1868, avec l\'inauguration de la ligne de Montbéliard à Delle. Précise l\'année 1868 de Wikidata au jour.',
    ],

    // Saujon (id 16611)
    [
        'station' => ['name' => 'Saujon', 'uic' => '87491811'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-08-28',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saujon', 222377940),
        'quote' => 'lors de l\'inauguration de sa ligne de Pons à Royan le 28 août 1875',
        'reason' => 'article de la gare « Gare de Saujon » (oldid 222377940) : mise en service lors de l\'inauguration de la ligne de Pons à Royan le 28 août 1875. Précise l\'année 1875 de Wikidata au jour.',
    ],

    // Istres (id 17997)
    [
        'station' => ['name' => 'Istres', 'uic' => '87753418'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare d\'Istres', 240315247),
        'quote' => 'La ligne est mise en service en 1882.',
        'reason' => 'article de la gare « Gare d\'Istres » (oldid 240315247) : la ligne Miramas - Istres - Port-de-Bouc, concédée en 1875, est mise en service en 1882. Remplace l\'année 1875 de Wikidata (décret de concession). Confiance moyenne : l\'article ne date pas l\'arrêt d\'Istres seul.',
    ],

    // Port-de-Bouc (id 18001)
    [
        'station' => ['name' => 'Port-de-Bouc', 'uic' => '87753459'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1882-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Port-de-Bouc', 238957960),
        'quote' => 'La ligne est mise en service en 1882.',
        'reason' => 'article de la gare « Gare de Port-de-Bouc » (oldid 238957960) : la ligne Miramas - Istres - Port-de-Bouc, concédée en 1875, est mise en service en 1882. Remplace l\'année 1875 de Wikidata (décret de concession). Confiance moyenne : l\'article ne date pas l\'arrêt de Port-de-Bouc seul.',
    ],

    // Oyonnax (id 17878)
    [
        'station' => ['name' => 'Oyonnax', 'uic' => '87743534'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1885-05-16',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Andelot-en-Montagne à La Cluse', 241260215),
        'quote' => 'd\'Oyonnax à La Cluse : 16 mai 1885',
        'reason' => 'article de la ligne « Ligne d\'Andelot-en-Montagne à La Cluse » (oldid 241260215) : section d\'Oyonnax à La Cluse ouverte le 16 mai 1885 (article de la gare, oldid 241399044 : construction du 28 mars 1883 au 16 mai 1885, inauguration le 17 mai). Précise l\'année 1885 de Wikidata au jour. Confiance moyenne.',
    ],

    // Pont-St-Vincent (id 14987)
    [
        'station' => ['name' => 'Pont-St-Vincent', 'uic' => '87141523'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1872-11-11',
        'precision' => 'day',
        'source_url' => $wp('Gare de Pont-Saint-Vincent', 241364429),
        'quote' => 'L\'histoire de la gare de Pont-Saint-Vincent débute le 11 novembre 1872, avec l\'ouverture de la première partie de la',
        'reason' => 'article de la gare « Gare de Pont-Saint-Vincent » (oldid 241364429) : ouverture de la première partie de la ligne de Jarville-la-Malgrange à Vézelise le 11 novembre 1872. Remplace le 18/11/1872 déduit de la ligne. Confiance moyenne.',
    ],

    // ── 46. Rattachements à Wikidata (23/11/2026, rapport storage/app/reports/agents/aprem-2111/wikidata-gares.md) ──────────────────────────────────
    // Gares de VFF sans élément Wikidata dont l'élément existe : même UIC (P722, sur 7 chiffres, sans la clé), même nom et à moins de 300 m
    // de la position VFF (les douze gares sans UIC : même nom, élément seul de ce nom à moins de 300 m, aucune autre fiche du même nom).
    // Éléments lus sur l'API Wikidata le 23/11/2026 (wbgetentities, requêtes SPARQL bornées). Les éléments de classe « gare de marchandises »
    // (Q55493) rejoignent les fiches de gares fret seul ou plus desservies créées de la même liste SNCF (même UIC, même point). Écartés : les
    // fiches des fusions en attente, les homonymes à moins de 300 m, les éléments de tramway ou de métro. Appliqué par vff:consolidate-stations
    // (action « link » : le Q-id est posé sur l'objet source de la gare, sans jamais remplacer celui qu'un objet porte déjà).

    // Paris-La Villette-Hébert (id 14870)
    [
        'station' => ['name' => 'Paris-La Villette-Hébert', 'uic' => '87113134'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758491',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758491',
        'quote' => 'P722 = 8711313',
        'reason' => 'élément Wikidata Q120758491 (gare de marchandises) : UIC 87113134 (P722 8711313) identique, nom « Paris-La Villette-Hébert » et à 20 m de la position VFF.',
    ],

    // Artonges (id 14886)
    [
        'station' => ['name' => 'Artonges', 'uic' => '87115378'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758185',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758185',
        'quote' => 'P722 = 8711537',
        'reason' => 'élément Wikidata Q120758185 (gare de marchandises) : UIC 87115378 (P722 8711537) identique, nom « Artonges » et à 0 m de la position VFF.',
    ],

    // St-Mesmin (id 14937)
    [
        'station' => ['name' => 'St-Mesmin', 'uic' => '87118091'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757178',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757178',
        'quote' => 'P722 = 8711809',
        'reason' => 'élément Wikidata Q120757178 (gare de marchandises) : UIC 87118091 (P722 8711809) identique, nom « Saint-Mesmin » et à 25 m de la position VFF.',
    ],

    // Crancey (id 14940)
    [
        'station' => ['name' => 'Crancey', 'uic' => '87118166'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757176',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757176',
        'quote' => 'P722 = 8711816',
        'reason' => 'élément Wikidata Q120757176 (gare de marchandises) : UIC 87118166 (P722 8711816) identique, nom « Crancey » et à 25 m de la position VFF.',
    ],

    // Jessains (id 14943)
    [
        'station' => ['name' => 'Jessains', 'uic' => '87118273'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757181',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757181',
        'quote' => 'P722 = 8711827',
        'reason' => 'élément Wikidata Q120757181 (gare de marchandises) : UIC 87118273 (P722 8711827) identique, nom « Jessains » et à 25 m de la position VFF.',
    ],

    // Clairvaux (id 14945)
    [
        'station' => ['name' => 'Clairvaux', 'uic' => '87118315'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757167',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757167',
        'quote' => 'P722 = 8711831',
        'reason' => 'élément Wikidata Q120757167 (gare de marchandises) : UIC 87118315 (P722 8711831) identique, nom « Clairvaux » et à 25 m de la position VFF.',
    ],

    // Bouilly-Roncenay (id 14946)
    [
        'station' => ['name' => 'Bouilly-Roncenay', 'uic' => '87118356'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757174',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757174',
        'quote' => 'P722 = 8711835',
        'reason' => 'élément Wikidata Q120757174 (gare de marchandises) : UIC 87118356 (P722 8711835) identique, nom « Bouilly - Roncenay » et à 0 m de la position VFF.',
    ],

    // Buchères-Verrières (id 14947)
    [
        'station' => ['name' => 'Buchères-Verrières', 'uic' => '87118430'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757185',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757185',
        'quote' => 'P722 = 8711843',
        'reason' => 'élément Wikidata Q120757185 (gare de marchandises) : UIC 87118430 (P722 8711843) identique, nom « Buchères - Verrières » et à 0 m de la position VFF.',
    ],

    // Bar-sur-Seine (id 14948)
    [
        'station' => ['name' => 'Bar-sur-Seine', 'uic' => '87118497'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757175',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757175',
        'quote' => 'P722 = 8711849',
        'reason' => 'élément Wikidata Q120757175 (gare de marchandises) : UIC 87118497 (P722 8711849) identique, nom « Bar-sur-Seine » et à 0 m de la position VFF.',
    ],

    // Polisot (id 14949)
    [
        'station' => ['name' => 'Polisot', 'uic' => '87118505'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757168',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757168',
        'quote' => 'P722 = 8711850',
        'reason' => 'élément Wikidata Q120757168 (gare de marchandises) : UIC 87118505 (P722 8711850) identique, nom « Polisot » et à 0 m de la position VFF.',
    ],

    // Charmont (id 14950)
    [
        'station' => ['name' => 'Charmont', 'uic' => '87118554'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757183',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757183',
        'quote' => 'P722 = 8711855',
        'reason' => 'élément Wikidata Q120757183 (gare de marchandises) : UIC 87118554 (P722 8711855) identique, nom « Charmont » et à 177 m de la position VFF.',
    ],

    // Arcis-sur-Aube (id 14951)
    [
        'station' => ['name' => 'Arcis-sur-Aube', 'uic' => '87118588'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757170',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757170',
        'quote' => 'P722 = 8711858',
        'reason' => 'élément Wikidata Q120757170 (gare de marchandises) : UIC 87118588 (P722 8711858) identique, nom « Arcis-sur-Aube » et à 176 m de la position VFF.',
    ],

    // Chavanges (id 14953)
    [
        'station' => ['name' => 'Chavanges', 'uic' => '87118786'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757172',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757172',
        'quote' => 'P722 = 8711878',
        'reason' => 'élément Wikidata Q120757172 (gare de marchandises) : UIC 87118786 (P722 8711878) identique, nom « Chavanges » et à 0 m de la position VFF.',
    ],

    // Estissac (id 14954)
    [
        'station' => ['name' => 'Estissac', 'uic' => '87118810'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757187',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757187',
        'quote' => 'P722 = 8711881',
        'reason' => 'élément Wikidata Q120757187 (gare de marchandises) : UIC 87118810 (P722 8711881) identique, nom « Estissac » et à 0 m de la position VFF.',
    ],

    // Coussey (id 14975)
    [
        'station' => ['name' => 'Coussey', 'uic' => '87141341'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756954',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756954',
        'quote' => 'P722 = 8714134',
        'reason' => 'élément Wikidata Q120756954 (gare de marchandises) : UIC 87141341 (P722 8714134) identique, nom « Coussey » et à 0 m de la position VFF.',
    ],

    // Rolampont (id 15005)
    [
        'station' => ['name' => 'Rolampont', 'uic' => '87142075'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757252',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757252',
        'quote' => 'P722 = 8714207',
        'reason' => 'élément Wikidata Q120757252 (gare de marchandises) : UIC 87142075 (P722 8714207) identique, nom « Rolampont » et à 78 m de la position VFF.',
    ],

    // Rimaucourt (id 15014)
    [
        'station' => ['name' => 'Rimaucourt', 'uic' => '87142299'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757250',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757250',
        'quote' => 'P722 = 8714229',
        'reason' => 'élément Wikidata Q120757250 (gare de marchandises) : UIC 87142299 (P722 8714229) identique, nom « Rimaucourt » et à 0 m de la position VFF.',
    ],

    // Manois (id 15015)
    [
        'station' => ['name' => 'Manois', 'uic' => '87142307'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757257',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757257',
        'quote' => 'P722 = 8714230',
        'reason' => 'élément Wikidata Q120757257 (gare de marchandises) : UIC 87142307 (P722 8714230) identique, nom « Manois » et à 0 m de la position VFF.',
    ],

    // Vaux-sous-Aubigny (id 15024)
    [
        'station' => ['name' => 'Vaux-sous-Aubigny', 'uic' => '87142620'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757254',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757254',
        'quote' => 'P722 = 8714262',
        'reason' => 'élément Wikidata Q120757254 (gare de marchandises) : UIC 87142620 (P722 8714262) identique, nom « Vaux-sous-Aubigny » et à 0 m de la position VFF.',
    ],

    // Gironcourt-Houécourt (id 15054)
    [
        'station' => ['name' => 'Gironcourt-Houécourt', 'uic' => '87144352'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757037',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757037',
        'quote' => 'P722 = 8714435',
        'reason' => 'élément Wikidata Q120757037 (gare de marchandises) : UIC 87144352 (P722 8714435) identique, nom « Gironcourt - Houécourt » et à 0 m de la position VFF.',
    ],

    // Châtenois-Dolaincourt (id 15055)
    [
        'station' => ['name' => 'Châtenois-Dolaincourt', 'uic' => '87144360'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757032',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757032',
        'quote' => 'P722 = 8714436',
        'reason' => 'élément Wikidata Q120757032 (gare de marchandises) : UIC 87144360 (P722 8714436) identique, nom « Châtenois - Dolaincourt » et à 0 m de la position VFF.',
    ],

    // Messempré (id 15132)
    [
        'station' => ['name' => 'Messempré', 'uic' => '87172486'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757243',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757243',
        'quote' => 'P722 = 8717248',
        'reason' => 'élément Wikidata Q120757243 (gare de marchandises) : UIC 87172486 (P722 8717248) identique, nom « Messempré » et à 0 m de la position VFF.',
    ],

    // Tournes (id 15133)
    [
        'station' => ['name' => 'Tournes', 'uic' => '87172502'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q117311407',
        'source_url' => 'https://www.wikidata.org/wiki/Q117311407',
        'quote' => 'P722 = 8717250',
        'reason' => 'élément Wikidata Q117311407 (gare de marchandises) : UIC 87172502 (P722 8717250) identique, nom « Tournes » et à 0 m de la position VFF.',
    ],

    // Vouziers (id 15135)
    [
        'station' => ['name' => 'Vouziers', 'uic' => '87172593'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q107472266',
        'source_url' => 'https://www.wikidata.org/wiki/Q107472266',
        'quote' => 'P722 = 8717259',
        'reason' => 'élément Wikidata Q107472266 (gare de marchandises) : UIC 87172593 (P722 8717259) identique, nom « Vouziers » et à 0 m de la position VFF.',
    ],

    // Stenay (id 15138)
    [
        'station' => ['name' => 'Stenay', 'uic' => '87172775'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757205',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757205',
        'quote' => 'P722 = 8717277',
        'reason' => 'élément Wikidata Q120757205 (gare de marchandises) : UIC 87172775 (P722 8717277) identique, nom « Stenay » et à 0 m de la position VFF.',
    ],

    // Oiry (id 15142)
    [
        'station' => ['name' => 'Oiry', 'uic' => '87174102'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758244',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758244',
        'quote' => 'P722 = 8717410',
        'reason' => 'élément Wikidata Q120758244 (gare de marchandises) : UIC 87174102 (P722 8717410) identique, nom « Oiry » et à 4 m de la position VFF.',
    ],

    // Mailly-le-Camp (id 15153)
    [
        'station' => ['name' => 'Mailly-le-Camp', 'uic' => '87174573'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757180',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757180',
        'quote' => 'P722 = 8717457',
        'reason' => 'élément Wikidata Q120757180 (gare de marchandises) : UIC 87174573 (P722 8717457) identique, nom « Mailly-le-Camp » et à 179 m de la position VFF.',
    ],

    // Ancerville-Guë (id 15166)
    [
        'station' => ['name' => 'Ancerville-Guë', 'uic' => '87175166'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757391',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757391',
        'quote' => 'P722 = 8717516',
        'reason' => 'élément Wikidata Q120757391 (gare de marchandises) : UIC 87175166 (P722 8717516) identique, nom « Ancerville-Guë » et à 0 m de la position VFF.',
    ],

    // Sorcy (id 15169)
    [
        'station' => ['name' => 'Sorcy', 'uic' => '87175281'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q108740428',
        'source_url' => 'https://www.wikidata.org/wiki/Q108740428',
        'quote' => 'P722 = 8717528',
        'reason' => 'élément Wikidata Q108740428 (gare de marchandises) : UIC 87175281 (P722 8717528) identique, nom « Sorcy » et à 102 m de la position VFF.',
    ],

    // Ligny (id 15170)
    [
        'station' => ['name' => 'Ligny', 'uic' => '87175323'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757392',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757392',
        'quote' => 'P722 = 8717532',
        'reason' => 'élément Wikidata Q120757392 (gare de marchandises) : UIC 87175323 (P722 8717532) identique, nom « Ligny » et à 0 m de la position VFF.',
    ],

    // Dugny (id 15172)
    [
        'station' => ['name' => 'Dugny', 'uic' => '87175661'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757390',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757390',
        'quote' => 'P722 = 8717566',
        'reason' => 'élément Wikidata Q120757390 (gare de marchandises) : UIC 87175661 (P722 8717566) identique, nom « Dugny » et à 0 m de la position VFF.',
    ],

    // Baleycourt (id 15173)
    [
        'station' => ['name' => 'Baleycourt', 'uic' => '87175760'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757203',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757203',
        'quote' => 'P722 = 8717576',
        'reason' => 'élément Wikidata Q120757203 (gare de marchandises) : UIC 87175760 (P722 8717576) identique, nom « Baleycourt » et à 0 m de la position VFF.',
    ],

    // Buzy (Meuse) (id 15176)
    [
        'station' => ['name' => 'Buzy (Meuse)', 'uic' => '87175802'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757394',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757394',
        'quote' => 'P722 = 8717580',
        'reason' => 'élément Wikidata Q120757394 (gare de marchandises) : UIC 87175802 (P722 8717580) identique, nom « Buzy (Meuse) » et à 0 m de la position VFF.',
    ],

    // St-Germain-sur-Meuse (id 15178)
    [
        'station' => ['name' => 'St-Germain-sur-Meuse', 'uic' => '87175836'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757393',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757393',
        'quote' => 'P722 = 8717583',
        'reason' => 'élément Wikidata Q120757393 (gare de marchandises) : UIC 87175836 (P722 8717583) identique, nom « Saint-Germain-sur-Meuse » et à 0 m de la position VFF.',
    ],

    // Mulhouse-Nord (id 15189)
    [
        'station' => ['name' => 'Mulhouse-Nord', 'uic' => '87182006'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q17628881',
        'source_url' => 'https://www.wikidata.org/wiki/Q17628881',
        'quote' => 'P722 = 8718200',
        'reason' => 'élément Wikidata Q17628881 (Q519608) : UIC 87182006 (P722 8718200) identique, nom « gare de Mulhouse-Nord » et à 87 m de la position VFF.',
    ],

    // Florange (id 15273)
    [
        'station' => ['name' => 'Florange', 'uic' => '87191247'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756860',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756860',
        'quote' => 'P722 = 8719124',
        'reason' => 'élément Wikidata Q120756860 (gare de marchandises) : UIC 87191247 (P722 8719124) identique, nom « Florange » et à 22 m de la position VFF.',
    ],

    // Batilly (id 15308)
    [
        'station' => ['name' => 'Batilly', 'uic' => '87192617'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757388',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757388',
        'quote' => 'P722 = 8719261',
        'reason' => 'élément Wikidata Q120757388 (gare de marchandises) : UIC 87192617 (P722 8719261) identique, nom « Batilly » et à 0 m de la position VFF.',
    ],

    // Jeandelize (id 15311)
    [
        'station' => ['name' => 'Jeandelize', 'uic' => '87192708'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757159',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757159',
        'quote' => 'P722 = 8719270',
        'reason' => 'élément Wikidata Q120757159 (gare de marchandises) : UIC 87192708 (P722 8719270) identique, nom « Jeandelize » et à 0 m de la position VFF.',
    ],

    // Cocheren (id 15316)
    [
        'station' => ['name' => 'Cocheren', 'uic' => '87193227'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756895',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756895',
        'quote' => 'P722 = 8719322',
        'reason' => 'élément Wikidata Q120756895 (gare de marchandises) : UIC 87193227 (P722 8719322) identique, nom « Cocheren » et à 19 m de la position VFF.',
    ],

    // Bitche-Camp (id 15335)
    [
        'station' => ['name' => 'Bitche-Camp', 'uic' => '87193839'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756856',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756856',
        'quote' => 'P722 = 8719383',
        'reason' => 'élément Wikidata Q120756856 (gare de marchandises) : UIC 87193839 (P722 8719383) identique, nom « Bitche-Camp » et à 0 m de la position VFF.',
    ],

    // Schiltigheim (id 15365)
    [
        'station' => ['name' => 'Schiltigheim', 'uic' => '87212290'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q17628898',
        'source_url' => 'https://www.wikidata.org/wiki/Q17628898',
        'quote' => 'P722 = 8721229',
        'reason' => 'élément Wikidata Q17628898 (gare de marchandises) : UIC 87212290 (P722 8721229) identique, nom « Schiltigheim » et à 169 m de la position VFF.',
    ],

    // Ciry-Sermoise (id 15498)
    [
        'station' => ['name' => 'Ciry-Sermoise', 'uic' => '87271866'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758138',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758138',
        'quote' => 'P722 = 8727186',
        'reason' => 'élément Wikidata Q120758138 (gare de marchandises) : UIC 87271866 (P722 8727186) identique, nom « Ciry - Sermoise » et à 0 m de la position VFF.',
    ],

    // Dunkerque-Port-Est (id 15593)
    [
        'station' => ['name' => 'Dunkerque-Port-Est', 'uic' => '87281204'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120769303',
        'source_url' => 'https://www.wikidata.org/wiki/Q120769303',
        'quote' => 'P722 = 8728120',
        'reason' => 'élément Wikidata Q120769303 (plusieurs classes) : UIC 87281204 (P722 8728120) identique, nom « Dunkerque-Port-Est » et à 1 m de la position VFF.',
    ],

    // Lomme (MIN) (id 15674)
    [
        'station' => ['name' => 'Lomme (MIN)', 'uic' => '87287417'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120769291',
        'source_url' => 'https://www.wikidata.org/wiki/Q120769291',
        'quote' => 'P722 = 8728741',
        'reason' => 'élément Wikidata Q120769291 (gare de marchandises) : UIC 87287417 (P722 8728741) identique, nom « Lomme (MIN) » et à 3 m de la position VFF.',
    ],

    // Feignies (id 15678)
    [
        'station' => ['name' => 'Feignies', 'uic' => '87295030'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q15279077',
        'source_url' => 'https://www.wikidata.org/wiki/Q15279077',
        'quote' => 'P722 = 8729503',
        'reason' => 'élément Wikidata Q15279077 (plusieurs classes) : UIC 87295030 (P722 8729503) identique, nom « Feignies » et à 27 m de la position VFF.',
    ],

    // Douzies (id 15684)
    [
        'station' => ['name' => 'Douzies', 'uic' => '87295238'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120769287',
        'source_url' => 'https://www.wikidata.org/wiki/Q120769287',
        'quote' => 'P722 = 8729523',
        'reason' => 'élément Wikidata Q120769287 (gare de marchandises) : UIC 87295238 (P722 8729523) identique, nom « Douzies » et à 12 m de la position VFF.',
    ],

    // Athies-sous-Laon (id 15710)
    [
        'station' => ['name' => 'Athies-sous-Laon', 'uic' => '87296327'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758155',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758155',
        'quote' => 'P722 = 8729632',
        'reason' => 'élément Wikidata Q120758155 (gare de marchandises) : UIC 87296327 (P722 8729632) identique, nom « Athies-sous-Laon » et à 0 m de la position VFF.',
    ],

    // Clermont-les-Fermes (id 15711)
    [
        'station' => ['name' => 'Clermont-les-Fermes', 'uic' => '87296376'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758126',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758126',
        'quote' => 'P722 = 8729637',
        'reason' => 'élément Wikidata Q120758126 (gare de marchandises) : UIC 87296376 (P722 8729637) identique, nom « Clermont-les-Fermes » et à 0 m de la position VFF.',
    ],

    // Montcornet (id 15712)
    [
        'station' => ['name' => 'Montcornet', 'uic' => '87296384'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758119',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758119',
        'quote' => 'P722 = 8729638',
        'reason' => 'élément Wikidata Q120758119 (gare de marchandises) : UIC 87296384 (P722 8729638) identique, nom « Montcornet » et à 0 m de la position VFF.',
    ],

    // Origny-Ste-Benoite (id 15717)
    [
        'station' => ['name' => 'Origny-Ste-Benoite', 'uic' => '87296467'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758112',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758112',
        'quote' => 'P722 = 8729646',
        'reason' => 'élément Wikidata Q120758112 (gare de marchandises) : UIC 87296467 (P722 8729646) identique, nom « Origny-Sainte-Benoite » et à 0 m de la position VFF.',
    ],

    // Chivres-en-Laonnois (id 15728)
    [
        'station' => ['name' => 'Chivres-en-Laonnois', 'uic' => '87297192'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758150',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758150',
        'quote' => 'P722 = 8729719',
        'reason' => 'élément Wikidata Q120758150 (gare de marchandises) : UIC 87297192 (P722 8729719) identique, nom « Chivres-en-Laonnois » et à 0 m de la position VFF.',
    ],

    // Saleux (id 15737)
    [
        'station' => ['name' => 'Saleux', 'uic' => '87313015'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q109581650',
        'source_url' => 'https://www.wikidata.org/wiki/Q109581650',
        'quote' => 'P722 = 8731301',
        'reason' => 'élément Wikidata Q109581650 (gare de marchandises) : UIC 87313015 (P722 8731301) identique, nom « Saleux » et à 8 m de la position VFF.',
    ],

    // Montières (id 15748)
    [
        'station' => ['name' => 'Montières', 'uic' => '87313197'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q18380201',
        'source_url' => 'https://www.wikidata.org/wiki/Q18380201',
        'quote' => 'P722 = 8731319',
        'reason' => 'élément Wikidata Q18380201 (gare de marchandises) : UIC 87313197 (P722 8731319) identique, nom « Montières » et à 15 m de la position VFF.',
    ],

    // Bacouël (id 15802)
    [
        'station' => ['name' => 'Bacouël', 'uic' => '87315010'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q109581289',
        'source_url' => 'https://www.wikidata.org/wiki/Q109581289',
        'quote' => 'P722 = 8731501',
        'reason' => 'élément Wikidata Q109581289 (gare de marchandises) : UIC 87315010 (P722 8731501) identique, nom « Bacouel » et à 0 m de la position VFF.',
    ],

    // Épehy (id 15943)
    [
        'station' => ['name' => 'Épehy', 'uic' => '87345363'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757433',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757433',
        'quote' => 'P722 = 8734536',
        'reason' => 'élément Wikidata Q120757433 (gare de marchandises) : UIC 87345363 (P722 8734536) identique, nom « Épehy » et à 0 m de la position VFF.',
    ],

    // St-Sébastien-Frêne-Rond (id 15965)
    [
        'station' => ['name' => 'St-Sébastien-Frêne-Rond', 'uic' => '87354597'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q2675849',
        'source_url' => 'https://www.wikidata.org/wiki/Q2675849',
        'quote' => 'P722 = 8735459',
        'reason' => 'élément Wikidata Q2675849 (gare ferroviaire) : UIC 87354597 (P722 8735459) identique, nom « Saint-Sébastien-Frêne-Rond » et à 53 m de la position VFF.',
    ],

    // Paris-Batignolles (id 15966)
    [
        'station' => ['name' => 'Paris-Batignolles', 'uic' => '87381004'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q107000687',
        'source_url' => 'https://www.wikidata.org/wiki/Q107000687',
        'quote' => 'P722 = 8738100',
        'reason' => 'élément Wikidata Q107000687 (gare de marchandises) : UIC 87381004 (P722 8738100) identique, nom « Paris-Batignolles » et à 11 m de la position VFF.',
    ],

    // St-Aubin-du-Vieil-Évreux (id 16061)
    [
        'station' => ['name' => 'St-Aubin-du-Vieil-Évreux', 'uic' => '87387126'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757380',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757380',
        'quote' => 'P722 = 8738712',
        'reason' => 'élément Wikidata Q120757380 (gare de marchandises) : UIC 87387126 (P722 8738712) identique, nom « Saint-Aubin-du-Vieil-Évreux » et à 15 m de la position VFF.',
    ],

    // Breteuil (id 16066)
    [
        'station' => ['name' => 'Breteuil', 'uic' => '87387241'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q114836193',
        'source_url' => 'https://www.wikidata.org/wiki/Q114836193',
        'quote' => 'P722 = 8738724',
        'reason' => 'élément Wikidata Q114836193 (gare de marchandises) : UIC 87387241 (P722 8738724) identique, nom « Breteuil » et à 0 m de la position VFF.',
    ],

    // St-André-de-l\'Eure (id 16067)
    [
        'station' => ['name' => 'St-André-de-l\'Eure', 'uic' => '87387308'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q114836178',
        'source_url' => 'https://www.wikidata.org/wiki/Q114836178',
        'quote' => 'P722 = 8738730',
        'reason' => 'élément Wikidata Q114836178 (gare de marchandises) : UIC 87387308 (P722 8738730) identique, nom « Saint-André-de-l\'Eure » et à 0 m de la position VFF.',
    ],

    // Garnay (id 16125)
    [
        'station' => ['name' => 'Garnay', 'uic' => '87393660'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756792',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756792',
        'quote' => 'P722 = 8739366',
        'reason' => 'élément Wikidata Q120756792 (gare de marchandises) : UIC 87393660 (P722 8739366) identique, nom « Garnay » et à 0 m de la position VFF.',
    ],

    // Beaulieu-Le Coudray (id 16147)
    [
        'station' => ['name' => 'Beaulieu-Le Coudray', 'uic' => '87394387'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q108112580',
        'source_url' => 'https://www.wikidata.org/wiki/Q108112580',
        'quote' => 'P722 = 8739438',
        'reason' => 'élément Wikidata Q108112580 (gare de marchandises) : UIC 87394387 (P722 8739438) identique, nom « Beaulieu-Le Coudray » et à 6 m de la position VFF.',
    ],

    // St-Sauveur-Châteauneuf (id 16155)
    [
        'station' => ['name' => 'St-Sauveur-Châteauneuf', 'uic' => '87394569'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q108130605',
        'source_url' => 'https://www.wikidata.org/wiki/Q108130605',
        'quote' => 'P722 = 8739456',
        'reason' => 'élément Wikidata Q108130605 (gare de marchandises) : UIC 87394569 (P722 8739456) identique, nom « Saint-Sauveur - Châteauneuf » et à 15 m de la position VFF.',
    ],

    // Coltainville (id 16156)
    [
        'station' => ['name' => 'Coltainville', 'uic' => '87394866'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756842',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756842',
        'quote' => 'P722 = 8739486',
        'reason' => 'élément Wikidata Q120756842 (gare de marchandises) : UIC 87394866 (P722 8739486) identique, nom « Coltainville » et à 0 m de la position VFF.',
    ],

    // La Chartre-sur-le-Loir (id 16187)
    [
        'station' => ['name' => 'La Chartre-sur-le-Loir', 'uic' => '87396671'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757429',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757429',
        'quote' => 'P722 = 8739667',
        'reason' => 'élément Wikidata Q120757429 (gare de marchandises) : UIC 87396671 (P722 8739667) identique, nom « La Chartre-sur-le-Loir » et à 164 m de la position VFF.',
    ],

    // Grand-Quevilly (id 16194)
    [
        'station' => ['name' => 'Grand-Quevilly', 'uic' => '87411108'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q115119079',
        'source_url' => 'https://www.wikidata.org/wiki/Q115119079',
        'quote' => 'P722 = 8741110',
        'reason' => 'élément Wikidata Q115119079 (gare de marchandises) : UIC 87411108 (P722 8741110) identique, nom « Grand-Quevilly » et à 33 m de la position VFF.',
    ],

    // Petit-Couronne (id 16195)
    [
        'station' => ['name' => 'Petit-Couronne', 'uic' => '87411116'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q115119133',
        'source_url' => 'https://www.wikidata.org/wiki/Q115119133',
        'quote' => 'P722 = 8741111',
        'reason' => 'élément Wikidata Q115119133 (gare de marchandises) : UIC 87411116 (P722 8741111) identique, nom « Petit-Couronne » et à 0 m de la position VFF.',
    ],

    // St-Pierre-lès-Elbeuf (id 16196)
    [
        'station' => ['name' => 'St-Pierre-lès-Elbeuf', 'uic' => '87411132'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q115119193',
        'source_url' => 'https://www.wikidata.org/wiki/Q115119193',
        'quote' => 'P722 = 8741113',
        'reason' => 'élément Wikidata Q115119193 (gare de marchandises) : UIC 87411132 (P722 8741113) identique, nom « Saint-Pierre-lès-Elbeuf » et à 0 m de la position VFF.',
    ],

    // St-Ouen-du-Breuil (id 16214)
    [
        'station' => ['name' => 'St-Ouen-du-Breuil', 'uic' => '87411728'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757422',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757422',
        'quote' => 'P722 = 8741172',
        'reason' => 'élément Wikidata Q120757422 (gare de marchandises) : UIC 87411728 (P722 8741172) identique, nom « Saint-Ouen-du-Breuil » et à 1 m de la position VFF.',
    ],

    // Rouxmesnil (id 16234)
    [
        'station' => ['name' => 'Rouxmesnil', 'uic' => '87415034'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757415',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757415',
        'quote' => 'P722 = 8741503',
        'reason' => 'élément Wikidata Q120757415 (gare de marchandises) : UIC 87415034 (P722 8741503) identique, nom « Rouxmesnil » et à 71 m de la position VFF.',
    ],

    // Étrépagny (id 16249)
    [
        'station' => ['name' => 'Étrépagny', 'uic' => '87415828'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q114836112',
        'source_url' => 'https://www.wikidata.org/wiki/Q114836112',
        'quote' => 'P722 = 8741582',
        'reason' => 'élément Wikidata Q114836112 (gare de marchandises) : UIC 87415828 (P722 8741582) identique, nom « Étrépagny » et à 0 m de la position VFF.',
    ],

    // Vendeuvre-Jort (id 16267)
    [
        'station' => ['name' => 'Vendeuvre-Jort', 'uic' => '87444224'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757403',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757403',
        'quote' => 'P722 = 8744422',
        'reason' => 'élément Wikidata Q120757403 (gare de marchandises) : UIC 87444224 (P722 8744422) identique, nom « Vendeuvre - Jort » et à 9 m de la position VFF.',
    ],

    // Montabard (id 16278)
    [
        'station' => ['name' => 'Montabard', 'uic' => '87444521'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757396',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757396',
        'quote' => 'P722 = 8744452',
        'reason' => 'élément Wikidata Q120757396 (gare de marchandises) : UIC 87444521 (P722 8744452) identique, nom « Montabard » et à 17 m de la position VFF.',
    ],

    // Zu-Rhein (id 16341)
    [
        'station' => ['name' => 'Zu-Rhein', 'uic' => '87472639'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758352',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758352',
        'quote' => 'P722 = 8747263',
        'reason' => 'élément Wikidata Q120758352 (gare de marchandises) : UIC 87472639 (P722 8747263) identique, nom « Zu-Rhein » et à 0 m de la position VFF.',
    ],

    // Montreuil-Belfroi (id 16505)
    [
        'station' => ['name' => 'Montreuil-Belfroi', 'uic' => '87484030'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757425',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757425',
        'quote' => 'P722 = 8748403',
        'reason' => 'élément Wikidata Q120757425 (gare de marchandises) : UIC 87484030 (P722 8748403) identique, nom « Montreuil-Belfroi » et à 0 m de la position VFF.',
    ],

    // Gennes-Longuefuye (id 16522)
    [
        'station' => ['name' => 'Gennes-Longuefuye', 'uic' => '87484667'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757195',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757195',
        'quote' => 'P722 = 8748466',
        'reason' => 'élément Wikidata Q120757195 (gare de marchandises) : UIC 87484667 (P722 8748466) identique, nom « Gennes - Longuefuye » et à 0 m de la position VFF.',
    ],

    // La Rochelle-Pallice (id 16524)
    [
        'station' => ['name' => 'La Rochelle-Pallice', 'uic' => '87485011'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120769315',
        'source_url' => 'https://www.wikidata.org/wiki/Q120769315',
        'quote' => 'P722 = 8748501',
        'reason' => 'élément Wikidata Q120769315 (gare de marchandises) : UIC 87485011 (P722 8748501) identique, nom « La Rochelle-Pallice » et à 1 m de la position VFF.',
    ],

    // Luché (id 16571)
    [
        'station' => ['name' => 'Luché', 'uic' => '87487256'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757247',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757247',
        'quote' => 'P722 = 8748725',
        'reason' => 'élément Wikidata Q120757247 (gare de marchandises) : UIC 87487256 (P722 8748725) identique, nom « Luché » et à 151 m de la position VFF.',
    ],

    // Janville (id 16629)
    [
        'station' => ['name' => 'Janville', 'uic' => '87543108'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756849',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756849',
        'quote' => 'P722 = 8754310',
        'reason' => 'élément Wikidata Q120756849 (gare de marchandises) : UIC 87543108 (P722 8754310) identique, nom « Janville » et à 0 m de la position VFF.',
    ],

    // Fains-la-Folie (id 16638)
    [
        'station' => ['name' => 'Fains-la-Folie', 'uic' => '87543306'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756806',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756806',
        'quote' => 'P722 = 8754330',
        'reason' => 'élément Wikidata Q120756806 (gare de marchandises) : UIC 87543306 (P722 8754330) identique, nom « Gare de Fains-la-Folie » et à 1 m de la position VFF.',
    ],

    // Orgères (id 16639)
    [
        'station' => ['name' => 'Orgères', 'uic' => '87543314'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756850',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756850',
        'quote' => 'P722 = 8754331',
        'reason' => 'élément Wikidata Q120756850 (gare de marchandises) : UIC 87543314 (P722 8754331) identique, nom « Orgères-en-Beauce » et à 0 m de la position VFF.',
    ],

    // Gommiers (id 16640)
    [
        'station' => ['name' => 'Gommiers', 'uic' => '87543322'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757383',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757383',
        'quote' => 'P722 = 8754332',
        'reason' => 'élément Wikidata Q120757383 (gare de marchandises) : UIC 87543322 (P722 8754332) identique, nom « Gommiers » et à 0 m de la position VFF.',
    ],

    // Bricy-Boulay (id 16642)
    [
        'station' => ['name' => 'Bricy-Boulay', 'uic' => '87543348'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757377',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757377',
        'quote' => 'P722 = 8754334',
        'reason' => 'élément Wikidata Q120757377 (gare de marchandises) : UIC 87543348 (P722 8754334) identique, nom « Bricy - Boulay » et à 0 m de la position VFF.',
    ],

    // Sully-sur-Loire (id 16648)
    [
        'station' => ['name' => 'Sully-sur-Loire', 'uic' => '87543538'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757374',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757374',
        'quote' => 'P722 = 8754353',
        'reason' => 'élément Wikidata Q120757374 (gare de marchandises) : UIC 87543538 (P722 8754353) identique, nom « Sully-sur-Loire » et à 0 m de la position VFF.',
    ],

    // Engenville (id 16649)
    [
        'station' => ['name' => 'Engenville', 'uic' => '87543686'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q107649264',
        'source_url' => 'https://www.wikidata.org/wiki/Q107649264',
        'quote' => 'P722 = 8754368',
        'reason' => 'élément Wikidata Q107649264 (gare de marchandises) : UIC 87543686 (P722 8754368) identique, nom « Engenville » et à 9 m de la position VFF.',
    ],

    // Neuville-aux-Bois (id 16650)
    [
        'station' => ['name' => 'Neuville-aux-Bois', 'uic' => '87543728'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757376',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757376',
        'quote' => 'P722 = 8754372',
        'reason' => 'élément Wikidata Q120757376 (gare de marchandises) : UIC 87543728 (P722 8754372) identique, nom « Neuville-aux-Bois » et à 0 m de la position VFF.',
    ],

    // Fossé-Marolles (id 16749)
    [
        'station' => ['name' => 'Fossé-Marolles', 'uic' => '87574400'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756912',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756912',
        'quote' => 'P722 = 8757440',
        'reason' => 'élément Wikidata Q120756912 (gare de marchandises) : UIC 87574400 (P722 8757440) identique, nom « Fossé-Marolles » et à 77 m de la position VFF.',
    ],

    // Selommes (id 16764)
    [
        'station' => ['name' => 'Selommes', 'uic' => '87574715'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756908',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756908',
        'quote' => 'P722 = 8757471',
        'reason' => 'élément Wikidata Q120756908 (gare de marchandises) : UIC 87574715 (P722 8757471) identique, nom « Selommes » et à 78 m de la position VFF.',
    ],

    // Moulins-sur-Yèvre (id 16830)
    [
        'station' => ['name' => 'Moulins-sur-Yèvre', 'uic' => '87576413'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757428',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757428',
        'quote' => 'P722 = 8757641',
        'reason' => 'élément Wikidata Q120757428 (gare de marchandises) : UIC 87576413 (P722 8757641) identique, nom « Moulins-sur-Yèvre » et à 0 m de la position VFF.',
    ],

    // Asnières-lès-Bourges (id 16835)
    [
        'station' => ['name' => 'Asnières-lès-Bourges', 'uic' => '87576504'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757284',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757284',
        'quote' => 'P722 = 8757650',
        'reason' => 'élément Wikidata Q120757284 (gare de marchandises) : UIC 87576504 (P722 8757650) identique, nom « Asnières-lès-Bourges » et à 0 m de la position VFF.',
    ],

    // Aubigny-sur-Nère (id 16836)
    [
        'station' => ['name' => 'Aubigny-sur-Nère', 'uic' => '87576579'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757373',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757373',
        'quote' => 'P722 = 8757657',
        'reason' => 'élément Wikidata Q120757373 (gare de marchandises) : UIC 87576579 (P722 8757657) identique, nom « Aubigny-sur-Nère » et à 0 m de la position VFF.',
    ],

    // Le Bec-d\'Ambès (id 16866)
    [
        'station' => ['name' => 'Le Bec-d\'Ambès', 'uic' => '87581884'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758258',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758258',
        'quote' => 'P722 = 8758188',
        'reason' => 'élément Wikidata Q120758258 (gare de marchandises) : UIC 87581884 (P722 8758188) identique, nom « Le Bec-d\'Ambès » et à 0 m de la position VFF.',
    ],

    // Solférino (id 16872)
    [
        'station' => ['name' => 'Solférino', 'uic' => '87582577'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q16764804',
        'source_url' => 'https://www.wikidata.org/wiki/Q16764804',
        'quote' => 'P722 = 8758257',
        'reason' => 'élément Wikidata Q16764804 (gare ferroviaire) : UIC 87582577 (P722 8758257) identique, nom « Solférino » et à 4 m de la position VFF.',
    ],

    // Charmant (id 16896)
    [
        'station' => ['name' => 'Charmant', 'uic' => '87583658'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757398',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757398',
        'quote' => 'P722 = 8758365',
        'reason' => 'élément Wikidata Q120757398 (gare de marchandises) : UIC 87583658 (P722 8758365) identique, nom « Charmant » et à 0 m de la position VFF.',
    ],

    // Colayrac (id 16927)
    [
        'station' => ['name' => 'Colayrac', 'uic' => '87586149'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756938',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756938',
        'quote' => 'P722 = 8758614',
        'reason' => 'élément Wikidata Q120756938 (gare de marchandises) : UIC 87586149 (P722 8758614) identique, nom « Colayrac » et à 0 m de la position VFF.',
    ],

    // Ste-Christie (id 16931)
    [
        'station' => ['name' => 'Ste-Christie', 'uic' => '87586362'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757191',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757191',
        'quote' => 'P722 = 8758636',
        'reason' => 'élément Wikidata Q120757191 (gare de marchandises) : UIC 87586362 (P722 8758636) identique, nom « Sainte-Christie » et à 0 m de la position VFF.',
    ],

    // Lasserre (id 16943)
    [
        'station' => ['name' => 'Lasserre', 'uic' => '87586750'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756946',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756946',
        'quote' => 'P722 = 8758675',
        'reason' => 'élément Wikidata Q120756946 (gare de marchandises) : UIC 87586750 (P722 8758675) identique, nom « Lasserre » et à 0 m de la position VFF.',
    ],

    // Limoges-Puy-Imbert (id 16953)
    [
        'station' => ['name' => 'Limoges-Puy-Imbert', 'uic' => '87592014'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757259',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757259',
        'quote' => 'P722 = 8759201',
        'reason' => 'élément Wikidata Q120757259 (gare de marchandises) : UIC 87592014 (P722 8759201) identique, nom « Limoges-Puy-Imbert » et à 147 m de la position VFF.',
    ],

    // Bessines (id 16985)
    [
        'station' => ['name' => 'Bessines', 'uic' => '87592626'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757260',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757260',
        'quote' => 'P722 = 8759262',
        'reason' => 'élément Wikidata Q120757260 (gare de marchandises) : UIC 87592626 (P722 8759262) identique, nom « Bessines » et à 0 m de la position VFF.',
    ],

    // Buzançais (id 17080)
    [
        'station' => ['name' => 'Buzançais', 'uic' => '87597377'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756899',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756899',
        'quote' => 'P722 = 8759737',
        'reason' => 'élément Wikidata Q120756899 (gare de marchandises) : UIC 87597377 (P722 8759737) identique, nom « Buzançais » et à 195 m de la position VFF.',
    ],

    // Clion-sur-Indre (id 17081)
    [
        'station' => ['name' => 'Clion-sur-Indre', 'uic' => '87597401'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756900',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756900',
        'quote' => 'P722 = 8759740',
        'reason' => 'élément Wikidata Q120756900 (gare de marchandises) : UIC 87597401 (P722 8759740) identique, nom « Clion-sur-Indre » et à 0 m de la position VFF.',
    ],

    // Châtillon-sur-Indre (id 17082)
    [
        'station' => ['name' => 'Châtillon-sur-Indre', 'uic' => '87597419'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q117476852',
        'source_url' => 'https://www.wikidata.org/wiki/Q117476852',
        'quote' => 'P722 = 8759741',
        'reason' => 'élément Wikidata Q117476852 (gare de marchandises) : UIC 87597419 (P722 8759741) identique, nom « Châtillon-sur-Indre » et à 89 m de la position VFF.',
    ],

    // Fléré-la-Rivière (id 17083)
    [
        'station' => ['name' => 'Fléré-la-Rivière', 'uic' => '87597427'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756903',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756903',
        'quote' => 'P722 = 8759742',
        'reason' => 'élément Wikidata Q120756903 (gare de marchandises) : UIC 87597427 (P722 8759742) identique, nom « Fléré-la-Rivière » et à 0 m de la position VFF.',
    ],

    // Empalot (id 17098)
    [
        'station' => ['name' => 'Empalot', 'uic' => '87611020'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120769307',
        'source_url' => 'https://www.wikidata.org/wiki/Q120769307',
        'quote' => 'P722 = 8761102',
        'reason' => 'élément Wikidata Q120769307 (gare de marchandises) : UIC 87611020 (P722 8761102) identique, nom « Empalot » et à 4 m de la position VFF.',
    ],

    // Montluçon-Eau (id 17238)
    [
        'station' => ['name' => 'Montluçon-Eau', 'uic' => '87641019'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758196',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758196',
        'quote' => 'P722 = 8764101',
        'reason' => 'élément Wikidata Q120758196 (gare de marchandises) : UIC 87641019 (P722 8764101) identique, nom « Montluçon-Eau » et à 0 m de la position VFF.',
    ],

    // Archignat (id 17247)
    [
        'station' => ['name' => 'Archignat', 'uic' => '87641225'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758208',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758208',
        'quote' => 'P722 = 8764122',
        'reason' => 'élément Wikidata Q120758208 (gare de marchandises) : UIC 87641225 (P722 8764122) identique, nom « Archignat » et à 0 m de la position VFF.',
    ],

    // Grenade-sur-l\'Adour (id 17276)
    [
        'station' => ['name' => 'Grenade-sur-l\'Adour', 'uic' => '87671024'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757419',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757419',
        'quote' => 'P722 = 8767102',
        'reason' => 'élément Wikidata Q120757419 (gare de marchandises) : UIC 87671024 (P722 8767102) identique, nom « Grenade-sur-l\'Adour » et à 0 m de la position VFF.',
    ],

    // Roquefort (Landes) (id 17281)
    [
        'station' => ['name' => 'Roquefort (Landes)', 'uic' => '87671263'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757411',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757411',
        'quote' => 'P722 = 8767126',
        'reason' => 'élément Wikidata Q120757411 (gare de marchandises) : UIC 87671263 (P722 8767126) identique, nom « Roquefort » et à 0 m de la position VFF.',
    ],

    // Labarthe-Avezac (id 17285)
    [
        'station' => ['name' => 'Labarthe-Avezac', 'uic' => '87671404'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757389',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757389',
        'quote' => 'P722 = 8767140',
        'reason' => 'élément Wikidata Q120757389 (gare de marchandises) : UIC 87671404 (P722 8767140) identique, nom « Labarthe - Avezac » et à 0 m de la position VFF.',
    ],

    // Maubourguet (id 17289)
    [
        'station' => ['name' => 'Maubourguet', 'uic' => '87671651'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q108730260',
        'source_url' => 'https://www.wikidata.org/wiki/Q108730260',
        'quote' => 'P722 = 8767165',
        'reason' => 'élément Wikidata Q108730260 (gare de marchandises) : UIC 87671651 (P722 8767165) identique, nom « Maubourguet » et à 0 m de la position VFF.',
    ],

    // Mauco-Benquet (id 17290)
    [
        'station' => ['name' => 'Mauco-Benquet', 'uic' => '87671867'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757413',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757413',
        'quote' => 'P722 = 8767186',
        'reason' => 'élément Wikidata Q120757413 (gare de marchandises) : UIC 87671867 (P722 8767186) identique, nom « Mauco - Benquet » et à 0 m de la position VFF.',
    ],

    // Hagetmau (id 17291)
    [
        'station' => ['name' => 'Hagetmau', 'uic' => '87671891'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757412',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757412',
        'quote' => 'P722 = 8767189',
        'reason' => 'élément Wikidata Q120757412 (gare de marchandises) : UIC 87671891 (P722 8767189) identique, nom « Hagetmau » et à 0 m de la position VFF.',
    ],

    // Lescar (id 17296)
    [
        'station' => ['name' => 'Lescar', 'uic' => '87672170'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757406',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757406',
        'quote' => 'P722 = 8767217',
        'reason' => 'élément Wikidata Q120757406 (gare de marchandises) : UIC 87672170 (P722 8767217) identique, nom « Lescar » et à 78 m de la position VFF.',
    ],

    // Lacq (id 17298)
    [
        'station' => ['name' => 'Lacq', 'uic' => '87672220'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757408',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757408',
        'quote' => 'P722 = 8767222',
        'reason' => 'élément Wikidata Q120757408 (gare de marchandises) : UIC 87672220 (P722 8767222) identique, nom « Lacq » et à 71 m de la position VFF.',
    ],

    // Baigts-de-Béarn (id 17300)
    [
        'station' => ['name' => 'Baigts-de-Béarn', 'uic' => '87672261'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757409',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757409',
        'quote' => 'P722 = 8767226',
        'reason' => 'élément Wikidata Q120757409 (gare de marchandises) : UIC 87672261 (P722 8767226) identique, nom « Baigts-de-Béarn » et à 70 m de la position VFF.',
    ],

    // Mignères-Gondréville (id 17429)
    [
        'station' => ['name' => 'Mignères-Gondréville', 'uic' => '87684563'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q107646786',
        'source_url' => 'https://www.wikidata.org/wiki/Q107646786',
        'quote' => 'P722 = 8768456',
        'reason' => 'élément Wikidata Q107646786 (gare de marchandises) : UIC 87684563 (P722 8768456) identique, nom « Mignères - Gondreville » et à 16 m de la position VFF.',
    ],

    // Tavaux-Solvay (id 17550)
    [
        'station' => ['name' => 'Tavaux-Solvay', 'uic' => '87713487'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q111792835',
        'source_url' => 'https://www.wikidata.org/wiki/Q111792835',
        'quote' => 'P722 = 8771348',
        'reason' => 'élément Wikidata Q111792835 (gare de marchandises) : UIC 87713487 (P722 8771348) identique, nom « Tavaux-Solvay » et à 0 m de la position VFF.',
    ],

    // Dijon-Porte-d\'Ouche-Perrigny (id 17572)
    [
        'station' => ['name' => 'Dijon-Porte-d\'Ouche-Perrigny', 'uic' => '87713867'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120769257',
        'source_url' => 'https://www.wikidata.org/wiki/Q120769257',
        'quote' => 'P722 = 8771386',
        'reason' => 'élément Wikidata Q120769257 (gare de marchandises) : UIC 87713867 (P722 8771386) identique, nom « Dijon-Porte-d\'Ouche-Perrigny » et à 35 m de la position VFF.',
    ],

    // Ste-Colombe-les-Vienne-St-Romain-en-Gal (id 17671)
    [
        'station' => ['name' => 'Ste-Colombe-les-Vienne-St-Romain-en-Gal', 'uic' => '87722322'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758321',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758321',
        'quote' => 'P722 = 8772232',
        'reason' => 'élément Wikidata Q120758321 (gare de marchandises) : UIC 87722322 (P722 8772232) identique, nom « Sainte-Colombe-lès-Vienne-Saint-Romain-en-Gal » et à 0 m de la position VFF.',
    ],

    // Vénissieux-Nord (id 17689)
    [
        'station' => ['name' => 'Vénissieux-Nord', 'uic' => '87723304'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q105422211',
        'source_url' => 'https://www.wikidata.org/wiki/Q105422211',
        'quote' => 'P722 = 8772330',
        'reason' => 'élément Wikidata Q105422211 (gare de marchandises) : UIC 87723304 (P722 8772330) identique, nom « Vénissieux-Nord » et à 51 m de la position VFF.',
    ],

    // Port-Édouard-Herriot (id 17773)
    [
        'station' => ['name' => 'Port-Édouard-Herriot', 'uic' => '87729202'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q105384286',
        'source_url' => 'https://www.wikidata.org/wiki/Q105384286',
        'quote' => 'P722 = 8772920',
        'reason' => 'élément Wikidata Q105384286 (gare de marchandises) : UIC 87729202 (P722 8772920) identique, nom « Port-Édouard-Herriot » et à 120 m de la position VFF.',
    ],

    // Lempdes (Haute-Loire) (id 17830)
    [
        'station' => ['name' => 'Lempdes (Haute-Loire)', 'uic' => '87734855'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757246',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757246',
        'quote' => 'P722 = 8773485',
        'reason' => 'élément Wikidata Q120757246 (gare de marchandises) : UIC 87734855 (P722 8773485) identique, nom « Lempdes » et à 226 m de la position VFF.',
    ],

    // Port-St-Louis-du-Rhône (id 18018)
    [
        'station' => ['name' => 'Port-St-Louis-du-Rhône', 'uic' => '87753855'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120769320',
        'source_url' => 'https://www.wikidata.org/wiki/Q120769320',
        'quote' => 'P722 = 8775385',
        'reason' => 'élément Wikidata Q120769320 (gare de marchandises) : UIC 87753855 (P722 8775385) identique, nom « Port-Saint-Louis-du-Rhône » et à 0 m de la position VFF.',
    ],

    // Portes (id 18091)
    [
        'station' => ['name' => 'Portes', 'uic' => '87761205'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757399',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757399',
        'quote' => 'P722 = 8776120',
        'reason' => 'élément Wikidata Q120757399 (gare de marchandises) : UIC 87761205 (P722 8776120) identique, nom « Portes-Lès-Valence » et à 127 m de la position VFF.',
    ],

    // Manthes-Lapeyrouse (id 18095)
    [
        'station' => ['name' => 'Manthes-Lapeyrouse', 'uic' => '87761320'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757402',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757402',
        'quote' => 'P722 = 8776132',
        'reason' => 'élément Wikidata Q120757402 (gare de marchandises) : UIC 87761320 (P722 8776132) identique, nom « Manthes - Lapeyrouse » et à 0 m de la position VFF.',
    ],

    // Alixan-Châteauneuf-d\'Isère (id 18099)
    [
        'station' => ['name' => 'Alixan-Châteauneuf-d\'Isère', 'uic' => '87761619'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757400',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757400',
        'quote' => 'P722 = 8776161',
        'reason' => 'élément Wikidata Q120757400 (gare de marchandises) : UIC 87761619 (P722 8776161) identique, nom « Alixan - Châteauneuf-d\'Isère » et à 0 m de la position VFF.',
    ],

    // Avignon-Fontcouverte (id 18133)
    [
        'station' => ['name' => 'Avignon-Fontcouverte', 'uic' => '87765024'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757041',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757041',
        'quote' => 'P722 = 8776502',
        'reason' => 'élément Wikidata Q120757041 (gare de marchandises) : UIC 87765024 (P722 8776502) identique, nom « Avignon-Fontcouverte » et à 0 m de la position VFF.',
    ],

    // Cavaillon (MIN) (id 18147)
    [
        'station' => ['name' => 'Cavaillon (MIN)', 'uic' => '87765537'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757148',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757148',
        'quote' => 'P722 = 8776553',
        'reason' => 'élément Wikidata Q120757148 (gare de marchandises) : UIC 87765537 (P722 8776553) identique, nom « Cavaillon (MIN) » et à 0 m de la position VFF.',
    ],

    // Florensac (id 18204)
    [
        'station' => ['name' => 'Florensac', 'uic' => '87781328'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757432',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757432',
        'quote' => 'P722 = 8778132',
        'reason' => 'élément Wikidata Q120757432 (gare de marchandises) : UIC 87781328 (P722 8778132) identique, nom « Florensac » et à 0 m de la position VFF.',
    ],

    // Lézignan-la-Cèbe (id 18205)
    [
        'station' => ['name' => 'Lézignan-la-Cèbe', 'uic' => '87781369'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q109559191',
        'source_url' => 'https://www.wikidata.org/wiki/Q109559191',
        'quote' => 'P722 = 8778136',
        'reason' => 'élément Wikidata Q109559191 (gare de marchandises) : UIC 87781369 (P722 8778136) identique, nom « Lézignan-la-Cèbe » et à 0 m de la position VFF.',
    ],

    // Dienville (id 18361)
    [
        'station' => ['name' => 'Dienville', 'uic' => '87118760'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757190',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757190',
        'quote' => 'P722 = 8711876',
        'reason' => 'élément Wikidata Q120757190 (gare de marchandises) : UIC 87118760 (P722 8711876) identique, nom « Dienville » et à 0 m de la position VFF.',
    ],

    // Braine (id 18493)
    [
        'station' => ['name' => 'Braine', 'uic' => '87171496'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758176',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758176',
        'quote' => 'P722 = 8717149',
        'reason' => 'élément Wikidata Q120758176 (gare de marchandises) : UIC 87171496 (P722 8717149) identique, nom « Braine » et à 0 m de la position VFF.',
    ],

    // Autet (id 18660)
    [
        'station' => ['name' => 'Autet', 'uic' => '87185538'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757199',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757199',
        'quote' => 'P722 = 8718553',
        'reason' => 'élément Wikidata Q120757199 (gare de marchandises) : UIC 87185538 (P722 8718553) identique, nom « Autet » et à 0 m de la position VFF.',
    ],

    // Villersexel (id 18667)
    [
        'station' => ['name' => 'Villersexel', 'uic' => '87185835'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q24046086',
        'source_url' => 'https://www.wikidata.org/wiki/Q24046086',
        'quote' => 'P722 = 8718583',
        'reason' => 'élément Wikidata Q24046086 (gare ferroviaire) : UIC 87185835 (P722 8718583) identique, nom « Villersexel » et à 78 m de la position VFF.',
    ],

    // Marquaix-Hamelet (id 18988)
    [
        'station' => ['name' => 'Marquaix-Hamelet', 'uic' => '87316265'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q141863881',
        'source_url' => 'https://www.wikidata.org/wiki/Q141863881',
        'quote' => 'P722 = 8731626',
        'reason' => 'élément Wikidata Q141863881 (gare ferroviaire) : UIC 87316265 (P722 8731626) identique, nom « Gare de Marquaix-Hamelet » et à 0 m de la position VFF.',
    ],

    // Éragny-Bazincourt (id 19089)
    [
        'station' => ['name' => 'Éragny-Bazincourt', 'uic' => '87381251'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q3095876',
        'source_url' => 'https://www.wikidata.org/wiki/Q3095876',
        'quote' => 'P722 = 8738125',
        'reason' => 'élément Wikidata Q3095876 (gare ferroviaire) : UIC 87381251 (P722 8738125) identique, nom « Éragny - Bazincourt » et à 85 m de la position VFF.',
    ],

    // Mondoubleau (id 19191)
    [
        'station' => ['name' => 'Mondoubleau', 'uic' => '87396747'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120756921',
        'source_url' => 'https://www.wikidata.org/wiki/Q120756921',
        'quote' => 'P722 = 8739674',
        'reason' => 'élément Wikidata Q120756921 (gare de marchandises) : UIC 87396747 (P722 8739674) identique, nom « Mondoubleau » et à 165 m de la position VFF.',
    ],

    // Colleville-Ste-Hélène (id 19231)
    [
        'station' => ['name' => 'Colleville-Ste-Hélène', 'uic' => '87413617'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757417',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757417',
        'quote' => 'P722 = 8741361',
        'reason' => 'élément Wikidata Q120757417 (gare de marchandises) : UIC 87413617 (P722 8741361) identique, nom « Colleville-Sainte-Hélène » et à 0 m de la position VFF.',
    ],

    // Mesnières-en-Bray (id 19244)
    [
        'station' => ['name' => 'Mesnières-en-Bray', 'uic' => '87415083'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q20971050',
        'source_url' => 'https://www.wikidata.org/wiki/Q20971050',
        'quote' => 'P722 = 8741508',
        'reason' => 'élément Wikidata Q20971050 (gare ferroviaire) : UIC 87415083 (P722 8741508) identique, nom « Mesnières-en-Bray » et à 49 m de la position VFF.',
    ],

    // St-Mesmin-le-Vieux (id 19533)
    [
        'station' => ['name' => 'St-Mesmin-le-Vieux', 'uic' => '87487298'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q62017327',
        'source_url' => 'https://www.wikidata.org/wiki/Q62017327',
        'quote' => 'P722 = 8748729',
        'reason' => 'élément Wikidata Q62017327 (gare ferroviaire) : UIC 87487298 (P722 8748729) identique, nom « Saint-Mesmin-le-Vieux » et à 91 m de la position VFF.',
    ],

    // Gontaud-Fauguerolles (id 19776)
    [
        'station' => ['name' => 'Gontaud-Fauguerolles', 'uic' => '87586578'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q2471354',
        'source_url' => 'https://www.wikidata.org/wiki/Q2471354',
        'quote' => 'P722 = 8758657',
        'reason' => 'élément Wikidata Q2471354 (gare ferroviaire) : UIC 87586578 (P722 8758657) identique, nom « Gontaud - Fauguerolles » et à 9 m de la position VFF.',
    ],

    // Porta (id 19896)
    [
        'station' => ['name' => 'Porta', 'uic' => '87611491'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q97365959',
        'source_url' => 'https://www.wikidata.org/wiki/Q97365959',
        'quote' => 'P722 = 8761149',
        'reason' => 'élément Wikidata Q97365959 (gare ferroviaire) : UIC 87611491 (P722 8761149) identique, nom « Porta » et à 71 m de la position VFF.',
    ],

    // Bayonne-Allées-Marines (id 20118)
    [
        'station' => ['name' => 'Bayonne-Allées-Marines', 'uic' => '87673012'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120757404',
        'source_url' => 'https://www.wikidata.org/wiki/Q120757404',
        'quote' => 'P722 = 8767301',
        'reason' => 'élément Wikidata Q120757404 (gare de marchandises) : UIC 87673012 (P722 8767301) identique, nom « Bayonne-Allées-Marines » et à 0 m de la position VFF.',
    ],

    // Lapalisse-St-Prix (id 20447)
    [
        'station' => ['name' => 'Lapalisse-St-Prix', 'uic' => '87732610'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q3096851',
        'source_url' => 'https://www.wikidata.org/wiki/Q3096851',
        'quote' => 'P722 = 8773261',
        'reason' => 'élément Wikidata Q3096851 (gare ferroviaire) : UIC 87732610 (P722 8773261) identique, nom « Lapalisse - Saint-Prix » et à 232 m de la position VFF.',
    ],

    // Chassal (id 20532)
    [
        'station' => ['name' => 'Chassal', 'uic' => '87743609'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q55964538',
        'source_url' => 'https://www.wikidata.org/wiki/Q55964538',
        'quote' => 'P722 = 8774360',
        'reason' => 'élément Wikidata Q55964538 (gare de marchandises) : UIC 87743609 (P722 8774360) identique, nom « Chassal » et à 7 m de la position VFF.',
    ],

    // Aramon (id 20796)
    [
        'station' => ['name' => 'Aramon', 'uic' => '87765883'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q120758323',
        'source_url' => 'https://www.wikidata.org/wiki/Q120758323',
        'quote' => 'P722 = 8776588',
        'reason' => 'élément Wikidata Q120758323 (gare de marchandises) : UIC 87765883 (P722 8776588) identique, nom « Aramon » et à 36 m de la position VFF.',
    ],

    // Sedan-Balan (id 22052)
    [
        'station' => ['name' => 'Sedan-Balan', 'near' => [4.954710, 49.696963, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q113350170',
        'source_url' => 'https://www.wikidata.org/wiki/Q113350170',
        'quote' => 'libellé « Sedan Balan »',
        'reason' => 'élément Wikidata Q113350170 (gare ferroviaire) : nom « Sedan Balan » identique, élément seul de ce nom à 10 m de la position VFF, fiche sans UIC.',
    ],

    // Saint-Sorlin-Vertrieu (id 22616)
    [
        'station' => ['name' => 'Saint-Sorlin-Vertrieu', 'near' => [5.368744, 45.884715, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q114571651',
        'source_url' => 'https://www.wikidata.org/wiki/Q114571651',
        'quote' => 'libellé « Saint-Sorlin - Vertrieu »',
        'reason' => 'élément Wikidata Q114571651 (gare ferroviaire) : nom « Saint-Sorlin - Vertrieu » identique, élément seul de ce nom à 97 m de la position VFF, fiche sans UIC.',
    ],

    // Remise à Jorelle (id 22674)
    [
        'station' => ['name' => 'Remise à Jorelle', 'uic' => '87988709'],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q3424532',
        'source_url' => 'https://www.wikidata.org/wiki/Q3424532',
        'quote' => 'P722 = 8798870',
        'reason' => 'élément Wikidata Q3424532 (plusieurs classes) : UIC 87988709 (P722 8798870) identique, nom « La Remise à Jorelle » et à 39 m de la position VFF.',
    ],

    // Saint-Just-en-Chevalet (id 22865)
    [
        'station' => ['name' => 'Saint-Just-en-Chevalet', 'near' => [3.843895, 45.908885, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q141875604',
        'source_url' => 'https://www.wikidata.org/wiki/Q141875604',
        'quote' => 'libellé « Gare de Saint-Just-en-Chevalet »',
        'reason' => 'élément Wikidata Q141875604 (gare ferroviaire) : nom « Gare de Saint-Just-en-Chevalet » identique, élément seul de ce nom à 8 m de la position VFF, fiche sans UIC.',
    ],

    // Ribérac (id 23027)
    [
        'station' => ['name' => 'Ribérac', 'near' => [0.332605, 45.256306, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q106291626',
        'source_url' => 'https://www.wikidata.org/wiki/Q106291626',
        'quote' => 'libellé « Ribérac »',
        'reason' => 'élément Wikidata Q106291626 (gare ferroviaire) : nom « Ribérac » identique, élément seul de ce nom à 2 m de la position VFF, fiche sans UIC.',
    ],

    // Faulx (id 23184)
    [
        'station' => ['name' => 'Faulx', 'near' => [6.195324, 48.789244, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q106938310',
        'source_url' => 'https://www.wikidata.org/wiki/Q106938310',
        'quote' => 'libellé « Faulx »',
        'reason' => 'élément Wikidata Q106938310 (gare ferroviaire) : nom « Faulx » identique, élément seul de ce nom à 25 m de la position VFF, fiche sans UIC.',
    ],

    // Vic-Fezensac (id 23256)
    [
        'station' => ['name' => 'Vic-Fezensac', 'near' => [0.303856, 43.767079, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q123498144',
        'source_url' => 'https://www.wikidata.org/wiki/Q123498144',
        'quote' => 'libellé « Vic-Fezensac »',
        'reason' => 'élément Wikidata Q123498144 (gare ferroviaire) : nom « Vic-Fezensac » identique, élément seul de ce nom à 263 m de la position VFF, fiche sans UIC.',
    ],

    // Saint-Paul-Trois-Châteaux (id 23323)
    [
        'station' => ['name' => 'Saint-Paul-Trois-Châteaux', 'near' => [4.762248, 44.345776, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q142167269',
        'source_url' => 'https://www.wikidata.org/wiki/Q142167269',
        'quote' => 'libellé « Saint-Paul-Trois-Châteaux »',
        'reason' => 'élément Wikidata Q142167269 (gare ferroviaire abandonnée) : nom « Saint-Paul-Trois-Châteaux » identique, élément seul de ce nom à 152 m de la position VFF, fiche sans UIC.',
    ],

    // Montségur (id 23324)
    [
        'station' => ['name' => 'Montségur', 'near' => [4.848281, 44.360329, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q142167049',
        'source_url' => 'https://www.wikidata.org/wiki/Q142167049',
        'quote' => 'libellé « Montségur »',
        'reason' => 'élément Wikidata Q142167049 (gare ferroviaire abandonnée) : nom « Montségur » identique, élément seul de ce nom à 56 m de la position VFF, fiche sans UIC.',
    ],

    // Venterol-Rousset (id 23327)
    [
        'station' => ['name' => 'Venterol-Rousset', 'near' => [5.084568, 44.395117, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q142166610',
        'source_url' => 'https://www.wikidata.org/wiki/Q142166610',
        'quote' => 'libellé « Venterol - Rousset »',
        'reason' => 'élément Wikidata Q142166610 (gare ferroviaire abandonnée) : nom « Venterol - Rousset » identique, élément seul de ce nom à 41 m de la position VFF, fiche sans UIC.',
    ],

    // Pleurtuit (id 23508)
    [
        'station' => ['name' => 'Pleurtuit', 'near' => [-2.063427, 48.579381, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q106362275',
        'source_url' => 'https://www.wikidata.org/wiki/Q106362275',
        'quote' => 'libellé « Pleurtuit »',
        'reason' => 'élément Wikidata Q106362275 (gare ferroviaire) : nom « Pleurtuit » identique, élément seul de ce nom à 71 m de la position VFF, fiche sans UIC.',
    ],

    // Flines-lez-Raches (id 24702)
    [
        'station' => ['name' => 'Flines-lez-Raches', 'near' => [3.185312, 50.428072, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q57262541',
        'source_url' => 'https://www.wikidata.org/wiki/Q57262541',
        'quote' => 'libellé « Flines-lez-Raches »',
        'reason' => 'élément Wikidata Q57262541 (gare ferroviaire) : nom « Flines-lez-Raches » identique, élément seul de ce nom à 18 m de la position VFF, fiche sans UIC.',
    ],

    // Saint-Germain-des-Près (id 24723)
    [
        'station' => ['name' => 'Saint-Germain-des-Près', 'near' => [0.998347, 45.347926, 0.3]],
        'action' => 'link',
        'source' => 'wikidata',
        'ref' => 'Q141874741',
        'source_url' => 'https://www.wikidata.org/wiki/Q141874741',
        'quote' => 'libellé « Gare de Saint-Germain-des-Prés »',
        'reason' => 'élément Wikidata Q141874741 (gare ferroviaire) : nom « Gare de Saint-Germain-des-Prés » identique, élément seul de ce nom à 41 m de la position VFF, fiche sans UIC.',
    ],

    // ── 47. Relecture de 127 ouvertures « confirmées » à la passe 6 parce que l'article de la gare ne donnait pas de date, recoupées avec le
    // texte détaillé de l'article de la ligne, puis 120 gares encore ouvertes de 0,048 M à 0,018 M de voyageurs (maximum depuis 2019) que les
    // passes précédentes n'avaient pas lues : 30 ouvertures corrigées (audit-gares-7, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/audit-gares-7.md). Une date qui change l'existant repose sur la chronologie détaillée de l'article de
    // la ligne (section nommant la gare ou la contenant) ou sur l'article de la gare quand il dit la gare créée après la ligne ; une infobox
    // seule ne contredit jamais la date de la section de la ligne (Meroux, e364bf26) et une inauguration n'est pas une ouverture au public.
    // Les 39 cas douteux restent à décider (liste dans le CSV du rapport).

    // Gannat (id 17785)
    [
        'station' => ['name' => 'Gannat', 'uic' => '87732404'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1855-05-07',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac', 240237870),
        'quote' => '7 mai 1855 dans les chemins de fer : Saint-Germain-des-Fossés – Gannat – Clermont-Ferrand',
        'reason' => 'article de la ligne « Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac » (oldid 240237870) : la section Saint-Germain-des-Fossés - Gannat - Clermont-Ferrand est mise en service le 7 mai 1855 ; l\'article de la gare (oldid 231518020) dit qu\'elle a été construite pour cette liaison, et le 1871 de Wikidata est l\'arrivée de la ligne de Commentry. Remplace l\'année 1871 de Wikidata.',
    ],

    // Brassac-les-Mines-Ste-Florine (id 17807)
    [
        'station' => ['name' => 'Brassac-les-Mines-Ste-Florine', 'uic' => '87734244'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1855-09-03',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac', 240237870),
        'quote' => '3 septembre 1855 dans les chemins de fer : Issoire – Brassac-les-Mines',
        'reason' => 'article de la ligne « Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac » (oldid 240237870) : Brassac-les-Mines est le terminus de la section Issoire - Brassac-les-Mines mise en service le 3 septembre 1855 (la suite vers Arvant ne l\'est que le 8 mai 1856). Remplace l\'année 1856 de Wikidata. Confiance moyenne.',
    ],

    // Estrées-St-Denis (id 15764)
    [
        'station' => ['name' => 'Estrées-St-Denis', 'uic' => '87313387'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1880-05-12',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Ormoy-Villers à Boves', 240747767),
        'quote' => 'Cette gare située sur la ligne de Rochy-Condé à Soissons avait déjà été mise en service le 12 mai 1880',
        'reason' => 'article de la ligne « Ligne d\'Ormoy-Villers à Boves » (oldid 240747767) : l\'article de la ligne d\'Ormoy-Villers à Boves dit la gare d\'Estrées-Saint-Denis déjà en service le 12 mai 1880, ce que confirme la chronologie de la ligne de Rochy-Condé à Soissons (section du Bois-de-Lihus à Estrées-Saint-Denis et à Compiègne le 12 mai 1880). Précise l\'année 1880 de Wikidata au jour.',
    ],

    // Vallorcine (id 17936)
    [
        'station' => ['name' => 'Vallorcine', 'uic' => '87746875'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1908-07-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Gervais-les-Bains-Le Fayet à Vallorcine (frontière)', 240212251),
        'quote' => 'La section Argentière – Le Châtelard est enfin ouverte le 1 juillet 1908.',
        'reason' => 'article de la ligne « Ligne de Saint-Gervais-les-Bains-Le Fayet à Vallorcine (frontière) » (oldid 240212251) : les sections jusqu\'à Argentière et Vallorcine ouvrent en 1906 et 1908, Vallorcine dans la section Argentière - Le Châtelard ouverte le 1er juillet 1908. Remplace l\'année 1901 (ouverture du Fayet à Chamonix) déduite de la ligne.',
    ],

    // La Crau (id 18036)
    [
        'station' => ['name' => 'La Crau', 'uic' => '87755611'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-12-06',
        'precision' => 'day',
        'source_url' => $wp('Ligne de La Pauline-Hyères aux Salins-d\'Hyères', 235223327),
        'quote' => 'Elle est ouverte à l\'exploitation le 6 décembre 1875 dans les chemins de fer de La Pauline à Hyères',
        'reason' => 'article de la ligne « Ligne de La Pauline-Hyères aux Salins-d\'Hyères » (oldid 235223327) : chronologie de la ligne de La Pauline à Hyères, qui dessert La Crau : ouverte à l\'exploitation le 6 décembre 1875. Précise l\'année 1875 de Wikidata au jour.',
    ],

    // Vayres (id 16921)
    [
        'station' => ['name' => 'Vayres', 'uic' => '87584714'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1852-09-20',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean', 240534824),
        'quote' => 'mettre en service l\'ensemble le 20 septembre 1852',
        'reason' => 'article de la ligne « Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean » (oldid 240534824) : la section de Libourne à Bordeaux, où se trouve Vayres, est mise en service avec l\'ensemble de la section jusqu\'à Angoulême le 20 septembre 1852. Précise l\'année 1852 de Wikidata au jour. Confiance moyenne.',
    ],

    // Bassens (id 16863)
    [
        'station' => ['name' => 'Bassens', 'uic' => '87581850'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1852-09-20',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean', 240534824),
        'quote' => 'mettre en service l\'ensemble le 20 septembre 1852',
        'reason' => 'article de la ligne « Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean » (oldid 240534824) : la section de Libourne à Bordeaux, où se trouve Bassens, est mise en service avec l\'ensemble de la section jusqu\'à Angoulême le 20 septembre 1852. Précise l\'année 1852 de Wikidata au jour. Confiance moyenne.',
    ],

    // Arches (id 15057)
    [
        'station' => ['name' => 'Arches', 'uic' => '87144410'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1864-11-10',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Épinal à Bussang', 219647311),
        'quote' => 'd\'Épinal à Remiremont le 10 novembre 1864 dans les chemins de fer',
        'reason' => 'article de la ligne « Ligne d\'Épinal à Bussang » (oldid 219647311) : la section d\'Épinal à Remiremont, où se trouve Arches, est ouverte le 10 novembre 1864. Précise l\'année 1864 de Wikidata au jour.',
    ],

    // Brebières-Sud (id 15926)
    [
        'station' => ['name' => 'Brebières-Sud', 'uic' => '87345108'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1846-04-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Nord à Lille', 241487697),
        'quote' => '1 avril 1846 dans les chemins de fer : ouverture des tronçons Arras – Lille et Arras – Valenciennes.',
        'reason' => 'article de la ligne « Ligne de Paris-Nord à Lille » (oldid 241487697) : le tronçon Arras - Lille, où se trouve Brebières, ouvre le 1er avril 1846. Précise l\'année 1846 de Wikidata au jour. Confiance moyenne.',
    ],

    // Marseille-en-Beauvaisis (id 15790)
    [
        'station' => ['name' => 'Marseille-en-Beauvaisis', 'uic' => '87313726'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-07-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Épinay - Villetaneuse au Tréport - Mers', 241187520),
        'quote' => 'de Beauvais à Abancourt et de Persan - Beaumont à Méru, le 1er juillet 1875 dans les chemins de fer',
        'reason' => 'article de la ligne « Ligne d\'Épinay - Villetaneuse au Tréport - Mers » (oldid 241187520) : la section de Beauvais à Abancourt, où se trouve Marseille-en-Beauvaisis, est mise en service le 1er juillet 1875. Précise l\'année 1875 de Wikidata au jour.',
    ],

    // Monestier-de-Clermont (id 17958)
    [
        'station' => ['name' => 'Monestier-de-Clermont', 'uic' => '87747592'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1878-07-27',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble)', 241143131),
        'quote' => 'De Vif à Veynes (bifurcation du Poteau-Saint-Luc) le 27 juillet 1878',
        'reason' => 'article de la ligne « Ligne de Lyon-Perrache à Marseille-Saint-Charles (via Grenoble) » (oldid 241143131) : Monestier-de-Clermont est entre Vif et Veynes, section mise en service le 27 juillet 1878 (Grenoble - Vif le 11 décembre 1876, d\'où le 1876 de Wikidata). Remplace l\'année 1876 de Wikidata. Confiance moyenne.',
    ],

    // Régny (id 17626)
    [
        'station' => ['name' => 'Régny', 'uic' => '87721050'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne du Coteau à Saint-Germain-au-Mont-d\'Or', 241352718),
        'quote' => 'La ligne est partiellement mise en service en 1866. La dernière section, comprise entre Amplepuis et Tarare, l\'est pour sa part le 19 octobre 1868',
        'reason' => 'article de la ligne « Ligne du Coteau à Saint-Germain-au-Mont-d\'Or » (oldid 241352718) : mise en service partielle en 1866, la dernière section d\'Amplepuis à Tarare seule en 1868 ; Régny, entre Le Coteau et Amplepuis, est donc dans la partie de 1866 (jour inconnu). Remplace l\'année 1833 de Wikidata, antérieure à la ligne. Confiance moyenne.',
    ],

    // St-Junien (id 16991)
    [
        'station' => ['name' => 'St-Junien', 'uic' => '87592709'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1875-04-26',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Limoges-Bénédictins à Angoulême', 241341612),
        'quote' => 'mise en service le 26 avril 1875 dans les chemins de fer',
        'reason' => 'article de la ligne « Ligne de Limoges-Bénédictins à Angoulême » (oldid 241341612) : mise en service le 26 avril 1875 de Limoges à Angoulême, dont Saint-Junien est une gare intermédiaire. Remplace l\'année 1911 déduite d\'OpenHistoricalMap. Confiance moyenne.',
    ],

    // Selles-sur-Cher (id 16838)
    [
        'station' => ['name' => 'Selles-sur-Cher', 'uic' => '87576868'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-10-18',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Vierzon à Saint-Pierre-des-Corps', 236330049),
        'quote' => 'mise en service le 18 octobre 1869 dans les chemins de fer',
        'reason' => 'article de la ligne « Ligne de Vierzon à Saint-Pierre-des-Corps » (oldid 236330049) : construite en 1860 et mise en service le 18 octobre 1869. Précise l\'année 1869 déduite de la ligne au jour.',
    ],

    // St-Aigulin-La Roche-Chalais (id 16918)
    [
        'station' => ['name' => 'St-Aigulin-La Roche-Chalais', 'uic' => '87584540'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1852-09-20',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean', 240534824),
        'quote' => 'mettre en service l\'ensemble le 20 septembre 1852',
        'reason' => 'article de la ligne « Ligne de Paris-Austerlitz à Bordeaux-Saint-Jean » (oldid 240534824) : la section d\'Angoulême à Bordeaux, où se trouve Saint-Aigulin - La Roche-Chalais, est mise en service avec l\'ensemble le 20 septembre 1852. Précise l\'année 1852 de Wikidata au jour. Confiance moyenne.',
    ],

    // Lauterbourg (id 15380)
    [
        'station' => ['name' => 'Lauterbourg', 'uic' => '87212464'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1876-07-25',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Strasbourg à Lauterbourg', 238408373),
        'quote' => 'dans son intégralité le 25 juillet 1876',
        'reason' => 'article de la ligne « Ligne de Strasbourg à Lauterbourg » (oldid 238408373) : ouverte dans son intégralité le 25 juillet 1876, Lauterbourg en étant le terminus (l\'article de la gare, oldid 239140948 ou voisin, donne la même date). Remplace l\'année 1874 de Wikidata.',
    ],

    // Ascq (id 15659)
    [
        'station' => ['name' => 'Ascq', 'uic' => '87286864'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1865-12-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Fives à Baisieux', 240286308),
        'quote' => 'La mise en service le 1 décembre 1865',
        'reason' => 'article de la ligne « Ligne de Fives à Baisieux » (oldid 240286308) : mise en service le 1er décembre 1865, avec deux stations en France, Ascq et Baisieux ; l\'article de la gare dit 1865. Précise l\'année 1865 de Wikidata au jour.',
    ],

    // Corbehem (id 15927)
    [
        'station' => ['name' => 'Corbehem', 'uic' => '87345116'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1846-04-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Nord à Lille', 241487697),
        'quote' => '1 avril 1846 dans les chemins de fer : ouverture des tronçons Arras – Lille et Arras – Valenciennes.',
        'reason' => 'article de la ligne « Ligne de Paris-Nord à Lille » (oldid 241487697) : le tronçon Arras - Lille, où se trouve Corbehem, ouvre le 1er avril 1846. Précise l\'année 1846 de Wikidata au jour. Confiance moyenne.',
    ],

    // Pont-de-la-Deûle (id 15933)
    [
        'station' => ['name' => 'Pont-de-la-Deûle', 'uic' => '87345207'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1846-04-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Paris-Nord à Lille', 241487697),
        'quote' => '1 avril 1846 dans les chemins de fer : ouverture des tronçons Arras – Lille et Arras – Valenciennes.',
        'reason' => 'article de la ligne « Ligne de Paris-Nord à Lille » (oldid 241487697) : le tronçon Arras - Lille, où se trouve Pont-de-la-Deûle, ouvre le 1er avril 1846. Précise l\'année 1846 de Wikidata au jour. Confiance moyenne.',
    ],

    // Ste-Anne (id 16423)
    [
        'station' => ['name' => 'Ste-Anne', 'uic' => '87476648'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1862-09-26',
        'precision' => 'day',
        'source_url' => $wp('Gare Sainte-Anne (Morbihan)', 230077628),
        'quote' => 'La gare Sainte-Anne est mise en service à l\'occasion de l\'ouverture de la ligne Rennes - Redon et du tronçon, à voie unique, jusqu\'à Lorient de la ligne Savenay - Landerneau, le 26 septembre 1862.',
        'reason' => 'article de la gare « Gare Sainte-Anne (Morbihan) » (oldid 230077628) : infobox et section Histoire : mise en service le 26 septembre 1862. Précise l\'année 1862 de Wikidata au jour.',
    ],

    // Argentière (id 17934)
    [
        'station' => ['name' => 'Argentière', 'uic' => '87746859'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1906-07-25',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Gervais-les-Bains-Le Fayet à Vallorcine (frontière)', 240212251),
        'quote' => 'La section entre Chamonix et Argentière ouvre au public le 25 juillet 1906.',
        'reason' => 'article de la ligne « Ligne de Saint-Gervais-les-Bains-Le Fayet à Vallorcine (frontière) » (oldid 240212251) : la section Chamonix - Argentière, terminus alors, ouvre au public le 25 juillet 1906. Précise l\'année 1906 de Wikidata au jour.',
    ],

    // Oderen (id 15222)
    [
        'station' => ['name' => 'Oderen', 'uic' => '87182634'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1905-01-02',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Lutterbach à Kruth', 235373537),
        'quote' => 'La section de Wesserling à Kruth est mise en service le 2 janvier 1905',
        'reason' => 'article de la ligne « Ligne de Lutterbach à Kruth » (oldid 235373537) : Oderen est entre Wesserling et Kruth, section mise en service le 2 janvier 1905 (Thann - Wesserling le 25 novembre 1863, d\'où le 1863 de Wikidata). Remplace l\'année 1863 de Wikidata. Confiance moyenne.',
    ],

    // Fellering (id 15221)
    [
        'station' => ['name' => 'Fellering', 'uic' => '87182626'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1905-01-02',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Lutterbach à Kruth', 235373537),
        'quote' => 'La section de Wesserling à Kruth est mise en service le 2 janvier 1905',
        'reason' => 'article de la ligne « Ligne de Lutterbach à Kruth » (oldid 235373537) : Fellering est entre Wesserling et Kruth, section mise en service le 2 janvier 1905 (Thann - Wesserling le 25 novembre 1863, d\'où le 1863 de Wikidata). Remplace l\'année 1863 de Wikidata. Confiance moyenne.',
    ],

    // Corps-Nuds (id 16327)
    [
        'station' => ['name' => 'Corps-Nuds', 'uic' => '87471326'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-12-28',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Châteaubriant à Rennes', 234893924),
        'quote' => 'Elle a été ouverte à l\'exploitation par l\'État le 28 décembre 1881.',
        'reason' => 'article de la ligne « Ligne de Châteaubriant à Rennes » (oldid 234893924) : ouverte à l\'exploitation le 28 décembre 1881 ; l\'article de la gare (oldid 231122716) dit la gare inaugurée dès la mise en place de la ligne en 1881. Précise l\'année 1881 de Wikidata au jour.',
    ],

    // Vern (id 16307)
    [
        'station' => ['name' => 'Vern', 'uic' => '87471029'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-12-28',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Châteaubriant à Rennes', 234893924),
        'quote' => 'Elle a été ouverte à l\'exploitation par l\'État le 28 décembre 1881.',
        'reason' => 'article de la ligne « Ligne de Châteaubriant à Rennes » (oldid 234893924) : ouverte à l\'exploitation le 28 décembre 1881 ; l\'article de la gare (oldid 238687587) dit la gare inaugurée dès la mise en place de la ligne en 1881. Précise l\'année 1881 de Wikidata au jour.',
    ],

    // Casino-Lacroix-Laval (id 15344)
    [
        'station' => ['name' => 'Casino-Lacroix-Laval', 'uic' => '87208553'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1997-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Casino-Lacroix-Laval', 241156062),
        'quote' => 'Cette halte a été créée en 1997 à la demande du directeur du Casino Le Lyon vert',
        'reason' => 'article de la gare « Gare de Casino-Lacroix-Laval » (oldid 241156062) : section Histoire : la halte a été créée en 1997 à la demande du casino, elle n\'existait donc pas à l\'ouverture de la ligne. Remplace l\'année 1882 d\'OpenHistoricalMap (ligne de Lyon à Montbrison).',
    ],

    // Port-Brillet (id 16454)
    [
        'station' => ['name' => 'Port-Brillet', 'uic' => '87478750'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1857-05-01',
        'precision' => 'month',
        'source_url' => $wp('Ligne de Paris-Montparnasse à Brest', 239547071),
        'quote' => 'puis enfin Rennes en mai 1857',
        'reason' => 'article de la ligne « Ligne de Paris-Montparnasse à Brest » (oldid 239547071) : Laval - Rennes, où se trouve Port-Brillet, est ouverte en mai 1857 (Chartres en 1849, Guingamp en septembre 1863). Remplace la fourchette 1860 déduite de la ligne. Confiance moyenne.',
    ],

    // Olonne-sur-Mer (id 16561)
    [
        'station' => ['name' => 'Olonne-sur-Mer', 'uic' => '87486431'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1866-12-30',
        'precision' => 'day',
        'source_url' => $wp('Ligne des Sables-d\'Olonne à Tours', 239596144),
        'quote' => 'est mise en service le 30 décembre 1866 dans les chemins de fer',
        'reason' => 'article de la ligne « Ligne des Sables-d\'Olonne à Tours » (oldid 239596144) : la section Les Sables-d\'Olonne - La Roche-sur-Yon, où se trouve Olonne-sur-Mer, est mise en service le 30 décembre 1866 ; l\'article de la gare dit 1866. Précise l\'année 1866 de Wikidata au jour.',
    ],

    // Longuerue-Vieux-Manoir (id 16210)
    [
        'station' => ['name' => 'Longuerue-Vieux-Manoir', 'uic' => '87411447'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1874-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Longuerue - Vieux-Manoir', 235032722),
        'quote' => 'celle-ci n\'a construit la gare de Longuerue-Vieux-Manoir qu\'en 1874',
        'reason' => 'article de la gare « Gare de Longuerue - Vieux-Manoir » (oldid 235032722) : section Histoire : la ligne est ouverte dès 1867 mais la gare n\'est construite qu\'en 1874. Remplace l\'année 1867 déduite de la ligne. Confiance moyenne.',
    ],

    // St-Armel (id 16326)
    [
        'station' => ['name' => 'St-Armel', 'uic' => '87471318'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-12-28',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Châteaubriant à Rennes', 234893924),
        'quote' => 'Elle a été ouverte à l\'exploitation par l\'État le 28 décembre 1881.',
        'reason' => 'article de la ligne « Ligne de Châteaubriant à Rennes » (oldid 234893924) : ouverte à l\'exploitation le 28 décembre 1881 ; l\'article de la gare (oldid 238686402) dit la gare inaugurée dès la mise en place de la ligne en 1881. Précise l\'année 1881 de Wikidata au jour.',
    ],

    // ── 48. Ouvertures « à décider » des audits de gares, tranchées avec la règle stricte (23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/gares-a-decider.md) : une date qui change l'existant repose sur la chronologie détaillée de l'article
    // de la ligne ou sur deux sources concordantes, une infobox seule ne contredit pas la section de la ligne, une inauguration n'est pas une
    // ouverture au public, une halte créée après la ligne a sa propre date sourcée. Les gares de Pontoise à Gisors (mise en service de la
    // section le 4 octobre 1868), du Theil et de Champagné (section de Chartres au Mans, 1 juin 1854), Logelbach, Breitenbach et Muhlbach
    // (sections de la ligne de Colmar à Metzeral), Rang-du-Fliers, Pontarlier, Avesnes, Alès, La Grand-Combe et Longueil-Sainte-Marie reçoivent
    // la date de leur section, Cap-d'Ail et Èze une date qui n'est plus antérieure à leur ligne.

    // Boissy-l'Aillerie (id 15978)
    [
        'station' => ['name' => 'Boissy-l\'Aillerie', 'uic' => '87381152'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Montgeroult-Courcelles (id 15979)
    [
        'station' => ['name' => 'Montgeroult-Courcelles', 'uic' => '87381160'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Santeuil-Le Perchay (id 15981)
    [
        'station' => ['name' => 'Santeuil-Le Perchay', 'uic' => '87381186'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Chars (id 15982)
    [
        'station' => ['name' => 'Chars', 'uic' => '87381194'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Lavilletertre (id 15983)
    [
        'station' => ['name' => 'Lavilletertre', 'uic' => '87381202'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Liancourt-St-Pierre (id 15984)
    [
        'station' => ['name' => 'Liancourt-St-Pierre', 'uic' => '87381210'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Chaumont-en-Vexin (id 15985)
    [
        'station' => ['name' => 'Chaumont-en-Vexin', 'uic' => '87381228'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Trie-Château (id 15986)
    [
        'station' => ['name' => 'Trie-Château', 'uic' => '87381236'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-04',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Denis à Dieppe', 239854982),
        'quote' => 'La ligne atteint Gisors le 4 octobre 1868 dans les chemins de fer.',
        'reason' => 'article de la ligne « Ligne de Saint-Denis à Dieppe » (oldid 239854982) : la section de Pontoise à Gisors, où se trouve la gare, est mise en service le 4 octobre 1868 (Gisors, article de la gare oldid 232268107 : même jour) ; le 10 octobre 1868 de la base vient d\'OpenHistoricalMap, sans autre source. Le même article dit une halte prévue à Trie-Château pour les voyageurs dans le récit de cette mise en service. Remplace le 10 octobre 1868. Confiance moyenne.',
    ],

    // Le Theil-La Rouge (id 16175)
    [
        'station' => ['name' => 'Le Theil-La Rouge', 'uic' => '87396333'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-06-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Mars-la-Brière', 240063538),
        'quote' => 'lorsqu\'elle ouvre à l\'exploitation la section de Chartres au Mans de sa ligne de Paris-Montparnasse à Brest',
        'reason' => 'article de la gare « Gare de Saint-Mars-la-Brière » (oldid 240063538) : la section de Chartres au Mans est ouverte le 1 juin 1854 ; les articles « Gare de La Ferté-Bernard » (oldid 230876421), « Gare de Sceaux - Boëssé » (oldid 223032459) et « Gare du Mans » (oldid 238252707) donnent le même jour pour les gares entre Nogent-le-Rotrou et Le Mans, et « Histoire de Nogent-le-Rotrou » (oldid 239795400) : Nogent reliée au Mans le 1 juin 1854. Le Theil-La Rouge est sur cette section. L\'article de la gare dit « mai 1857 » (recopie de l\'ouverture de la ligne jusqu\'à Rennes). Précise l\'année 1854 de Wikidata au jour. Confiance moyenne.',
    ],

    // Champagné (id 16169)
    [
        'station' => ['name' => 'Champagné', 'uic' => '87396275'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1854-06-01',
        'precision' => 'day',
        'source_url' => $wp('Gare de Saint-Mars-la-Brière', 240063538),
        'quote' => 'lorsqu\'elle ouvre à l\'exploitation la section de Chartres au Mans de sa ligne de Paris-Montparnasse à Brest',
        'reason' => 'article de la gare « Gare de Saint-Mars-la-Brière » (oldid 240063538) : la section de Chartres au Mans est ouverte le 1 juin 1854 ; les articles « Gare de La Ferté-Bernard » (oldid 230876421), « Gare de Sceaux - Boëssé » (oldid 223032459) et « Gare du Mans » (oldid 238252707) donnent le même jour pour les gares entre Nogent-le-Rotrou et Le Mans, et « Histoire de Nogent-le-Rotrou » (oldid 239795400) : Nogent reliée au Mans le 1 juin 1854. Champagné est sur cette section. L\'article de la gare dit « mai 1857 » (recopie de l\'ouverture de la ligne jusqu\'à Rennes). Précise l\'année 1854 de Wikidata au jour. Confiance moyenne.',
    ],

    // Logelbach (id 15205)
    [
        'station' => ['name' => 'Logelbach', 'uic' => '87182345'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-12-03',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Colmar-Central à Metzeral', 240511725),
        'quote' => 'La section de Colmar à Munster est ouverte le 3 décembre 1868 par la Compagnie des chemins de fer de l\'Est.',
        'reason' => 'article de la ligne « Ligne de Colmar-Central à Metzeral » (oldid 240511725) : la section de Colmar à Munster, où se trouve Logelbach, est ouverte le 3 décembre 1868 ; l\'article de la gare (oldid 241597894) dit 1867 « lors de l\'ouverture de la ligne », ce qui ne peut pas être antérieur à la section. Remplace l\'année 1867 de Wikidata et de l\'article de la gare. Confiance moyenne.',
    ],

    // Breitenbach (id 15224)
    [
        'station' => ['name' => 'Breitenbach', 'uic' => '87182659'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1893-11-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Colmar-Central à Metzeral', 240511725),
        'quote' => 'La ligne est prolongée jusqu\'à Metzeral par la Direction générale impériale des chemins de fer d\'Alsace-Lorraine (EL) le 1er novembre 1893',
        'reason' => 'article de la ligne « Ligne de Colmar-Central à Metzeral » (oldid 240511725) : la section de Colmar à Munster est ouverte le 3 décembre 1868, la ligne n\'est prolongée au-delà de Munster jusqu\'à Metzeral que le 1er novembre 1893 ; Breitenbach est après Munster (l\'article de la gare, oldid 241597867, la place entre Luttenbach-près-Munster et Metzeral et dit 1868 « lors de l\'ouverture de la ligne de Colmar à Munster »). Remplace l\'année 1868 de Wikidata et de l\'article de la gare. Confiance moyenne.',
    ],

    // Muhlbach-sur-Munster (id 15231)
    [
        'station' => ['name' => 'Muhlbach-sur-Munster', 'uic' => '87182733'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1893-11-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Colmar-Central à Metzeral', 240511725),
        'quote' => 'La ligne est prolongée jusqu\'à Metzeral par la Direction générale impériale des chemins de fer d\'Alsace-Lorraine (EL) le 1er novembre 1893',
        'reason' => 'article de la ligne « Ligne de Colmar-Central à Metzeral » (oldid 240511725) : la section de Colmar à Munster est ouverte le 3 décembre 1868, la ligne n\'est prolongée au-delà de Munster jusqu\'à Metzeral que le 1er novembre 1893 ; Muhlbach-sur-Munster est après Munster (l\'article de la gare, oldid 241597911, la place entre Luttenbach-près-Munster et Metzeral et dit 1868 « lors de l\'ouverture de la ligne de Colmar à Munster »). Remplace l\'année 1868 de Wikidata et de l\'article de la gare. Confiance moyenne.',
    ],

    // Rang-du-Fliers-Verton (id 15821)
    [
        'station' => ['name' => 'Rang-du-Fliers-Verton', 'uic' => '87317057'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1847-12-21',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Longueau à Boulogne-Ville', 235223334),
        'quote' => '21 décembre 1847 dans les chemins de fer : ouverture d\'Abbeville à Neufchâtel',
        'reason' => 'article de la ligne « Ligne de Longueau à Boulogne-Ville » (oldid 235223334) : la section d\'Abbeville à Neufchâtel, où se trouve Rang-du-Fliers, est ouverte le 21 décembre 1847 ; la base portait le 11 novembre 1847 (OpenHistoricalMap), sans source. Remplace le 11 novembre 1847.',
    ],

    // Cap-d'Ail (id 18050)
    [
        'station' => ['name' => 'Cap-d\'Ail', 'uic' => '87756395'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1881-01-01',
        'precision' => 'year',
        'source_url' => $wp('Gare de Cap-d\'Ail', 228568248),
        'quote' => 'La gare est construite en 1881 pour la Compagnie Paris-Lyon-Méditerranée',
        'reason' => 'article de la gare « Gare de Cap-d\'Ail » (oldid 228568248) : section Histoire : la gare est construite en 1881 pour le PLM ; la section de Nice à Monaco de la ligne est ouverte le 9 octobre 1868 (article de la ligne, oldid 240736526), le 1866 de Wikidata est donc antérieur à la ligne elle-même. Remplace l\'année 1866 de Wikidata. Confiance moyenne (« construite » et non « ouverte »).',
    ],

    // Èze (id 18049)
    [
        'station' => ['name' => 'Èze', 'uic' => '87756387'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1868-10-09',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Marseille-Saint-Charles à Vintimille (frontière)', 240736526),
        'quote' => 'Le 9 octobre 1868, ouverture de la ligne entre Nice et Monaco.',
        'reason' => 'article de la ligne « Ligne de Marseille-Saint-Charles à Vintimille (frontière) » (oldid 240736526) : la section de Nice à Monaco, où se trouve Èze-sur-Mer, est ouverte le 9 octobre 1868 ; l\'article de la gare (oldid 228568278) dit « mise en service en 1866 », antérieur à la ligne, comme le 1866 de Wikidata (construction vraisemblable des ouvrages). Remplace l\'année 1866 de Wikidata. Confiance moyenne.',
    ],

    // Pontarlier (id 17573)
    [
        'station' => ['name' => 'Pontarlier', 'uic' => '87715003'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1860-07-24',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Frasne à Verrières-de-Joux (frontière)', 235342104),
        'quote' => '24 juillet 1860, mise en service de Pontarlier aux Verrières et à la frontière suisse',
        'reason' => 'article de la ligne « Ligne de Frasne à Verrières-de-Joux (frontière) » (oldid 235342104) : chronologie détaillée, Pontarlier aux Verrières mise en service le 24 juillet 1860 (référence Palau citée) ; l\'infobox de la gare donne le 25 et OpenStreetMap le 17 : une infobox seule ne contredit pas la chronologie de la ligne. Remplace le 17 juillet 1860 d\'OpenStreetMap.',
    ],

    // Avesnes (id 15694)
    [
        'station' => ['name' => 'Avesnes', 'uic' => '87295733'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1869-10-30',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Fives à Hirson', 240286509),
        'quote' => 'd\'Aulnoye à Hirson, le 30 octobre 1869',
        'reason' => 'article de la ligne « Ligne de Fives à Hirson » (oldid 240286509) : chronologie des ouvertures, d\'Aulnoye à Hirson le 30 octobre 1869 (Avesnes-sur-Helpe est sur cette section, entre Aulnoye-Aymeries et Fourmies), de Valenciennes à Aulnoye le 1 septembre 1872 ; l\'article de la gare dit « ouverte en 1872 » pour la ligne de Lille à Thionville (autre section). Remplace l\'année 1872 de Wikidata. Confiance moyenne.',
    ],

    // Alès (id 18179)
    [
        'station' => ['name' => 'Alès', 'uic' => '87775288'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1840-08-19',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac', 240237870),
        'quote' => '19 août 1840 dans les chemins de fer : Alès – Nîmes',
        'reason' => 'article de la ligne « Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac » (oldid 240237870) : chronologie, Alès - Nîmes le 19 août 1840 ; l\'article « Gare de Nîmes » (oldid 239952199) dit la ligne d\'Alais à Beaucaire ouverte en totalité le 19 août 1840 ; l\'article de la gare d\'Alès (oldid 238810237) et Wikidata disent 1840. Précise l\'année 1840 de Wikidata au jour.',
    ],

    // Grand'Combe-La Pise (id 18178)
    [
        'station' => ['name' => 'Grand\'Combe-La Pise', 'uic' => '87775254'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1841-01-01',
        'precision' => 'month',
        'source_url' => $wp('Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac', 240237870),
        'quote' => 'janvier 1841 dans les chemins de fer : Alès – La Levade',
        'reason' => 'article de la ligne « Ligne de Saint-Germain-des-Fossés à Nîmes-Courbessac » (oldid 240237870) : chronologie, Alès - La Levade (La Grand-Combe) en janvier 1841, et « dès 1841 » dans l\'introduction ; le texte de l\'article de la gare dit 1841, l\'infobox et Wikidata 1840 (l\'année de la section Alès - Nîmes, qui ne dessert pas La Grand-Combe). Remplace l\'année 1840 de Wikidata. Confiance moyenne.',
    ],

    // Longueil-Ste-Marie (id 15537)
    [
        'station' => ['name' => 'Longueil-Ste-Marie', 'uic' => '87276303'],
        'action' => 'add',
        'type' => 'opening',
        'date' => '1847-10-20',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Creil à Jeumont', 236310314),
        'quote' => 'Le 20 octobre 1847, de Creil à Compiègne via Pont-Sainte-Maxence',
        'reason' => 'article de la ligne « Ligne de Creil à Jeumont » (oldid 236310314) : chronologie des mises en service, de Creil à Compiègne via Pont-Sainte-Maxence le 20 octobre 1847 ; l\'article de la gare (oldid 241485293) dit que la gare s\'appelait d\'abord Verberie jusqu\'à l\'ouverture de la ligne d\'Ormoy-Villers à Boves en 1882 : elle est donc plus ancienne que cette ligne, 1882 de Wikidata étant la date de cette ligne. Remplace l\'année 1882 de Wikidata. Confiance moyenne.',
    ],

    // ── 49. Fermetures voyageurs ou de service relues sur les articles de ligne (audit-fermetures-3, 23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/audit-fermetures-3.md). Les 42 gares « sans source datée » de la passe 2 et 140 gares de plus de
    // lignes fermées entre 1938 et 1990 dont la fermeture voyageurs était déduite de la ligne ont été confrontées à la chronologie par sections
    // de leur ligne (Wikipédia, révision citée) : seules les dates qui changent l'existant ou le précisent sont corrigées ici (fermeture
    // voyageurs, fin de l'exploitation, jour à la place de l'année) ; les autres sont confirmées ou restent à décider dans le CSV du rapport.

    // Péronne-la-Chapelette (id 15799)
    [
        'station' => ['name' => 'Péronne-la-Chapelette', 'wikidata' => 'Q3097360'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-04-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Saint-Just-en-Chaussée à Douai', 241007487),
        'quote' => 'le service voyageurs est également supprimé le 1 avril 1970 entre Montdidier et Cambrai',
        'reason' => 'section Montdidier - Cambrai dont fait partie la halte de Péronne-la-Chapelette ; la date déduite (24/02/1975) était celle de la fin de la desserte marchandises de la section ; l\'article de la gare ne date pas la fermeture. Confiance moyenne : la halte n\'est pas citée nommément. (oldid 241007487)',
    ],

    // Beaune-la-Rolande (id 16652)
    [
        'station' => ['name' => 'Beaune-la-Rolande', 'wikidata' => 'Q16507923'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Étampes à Beaune-la-Rolande', 239193697),
        'quote' => 'La ligne est fermée au trafic voyageurs le 15 05 1939 de Pithiviers à Beaune-la-Rolande',
        'reason' => 'article de la ligne d\'Étampes à Beaune-la-Rolande : voyageurs fermés le 15/05/1939 de Pithiviers à Beaune-la-Rolande, et l\'article de la ligne d\'Auxy - Juranville à Bourges (oldid 240777187) donne 1939 pour Beaune-la-Rolande à Bourges (Auxy - Beaune fermée en 1932) : la dernière desserte voyageurs de la gare est de 1939. 1992 déduit de la ligne (fin du fret Pithiviers - Beaune). (oldid 239193697)',
    ],

    // Le Bouchet (id 16794)
    [
        'station' => ['name' => 'Le Bouchet', 'wikidata' => 'Q19407025'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Loudun à Châtellerault', 241462135),
        'quote' => 'Le service voyageur est arrêté le 15 mai 1939',
        'reason' => 'article de la ligne de Loudun à Châtellerault : service voyageurs arrêté le 15/05/1939, repris partiellement pendant la guerre mais seulement jusqu\'à Châtellerault-Châteauneuf, puis arrêté définitivement à l\'été 1946 ; la date déduite était celle de la fin du fret ou de la fermeture de la section, pas celle des voyageurs. (oldid 241462135)',
    ],

    // La Bourdigalière (id 21281)
    [
        'station' => ['name' => 'La Bourdigalière', 'wikidata' => 'Q19406990'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Loudun à Châtellerault', 241462135),
        'quote' => 'Le service voyageur est arrêté le 15 mai 1939',
        'reason' => 'article de la ligne de Loudun à Châtellerault : service voyageurs arrêté le 15/05/1939, repris partiellement pendant la guerre mais seulement jusqu\'à Châtellerault-Châteauneuf, puis arrêté définitivement à l\'été 1946 ; la date déduite était celle de la fin du fret ou de la fermeture de la section, pas celle des voyageurs. (oldid 241462135)',
    ],

    // Berthegon (id 21288)
    [
        'station' => ['name' => 'Berthegon', 'wikidata' => 'Q19406977'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Loudun à Châtellerault', 241462135),
        'quote' => 'Le service voyageur est arrêté le 15 mai 1939',
        'reason' => 'article de la ligne de Loudun à Châtellerault : service voyageurs arrêté le 15/05/1939, repris partiellement pendant la guerre mais seulement jusqu\'à Châtellerault-Châteauneuf, puis arrêté définitivement à l\'été 1946 ; la date déduite était celle de la fin du fret ou de la fermeture de la section, pas celle des voyageurs. (oldid 241462135)',
    ],

    // Cernay-Doussay (id 21485)
    [
        'station' => ['name' => 'Cernay-Doussay', 'wikidata' => 'Q19544659'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Loudun à Châtellerault', 241462135),
        'quote' => 'Le service voyageur est arrêté le 15 mai 1939',
        'reason' => 'article de la ligne de Loudun à Châtellerault : service voyageurs arrêté le 15/05/1939, repris partiellement pendant la guerre mais seulement jusqu\'à Châtellerault-Châteauneuf, puis arrêté définitivement à l\'été 1946 ; la date déduite était celle de la fin du fret ou de la fermeture de la section, pas celle des voyageurs. (oldid 241462135)',
    ],

    // Monts-sur-Guesnes (id 21547)
    [
        'station' => ['name' => 'Monts-sur-Guesnes', 'wikidata' => 'Q19407001'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1939-05-15',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Loudun à Châtellerault', 241462135),
        'quote' => 'Le service voyageur est arrêté le 15 mai 1939',
        'reason' => 'article de la ligne de Loudun à Châtellerault : service voyageurs arrêté le 15/05/1939, repris partiellement pendant la guerre mais seulement jusqu\'à Châtellerault-Châteauneuf, puis arrêté définitivement à l\'été 1946 ; la date déduite était celle de la fin du fret ou de la fermeture de la section, pas celle des voyageurs. (oldid 241462135)',
    ],

    // Bray-Dunes (id 18838)
    [
        'station' => ['name' => 'Bray-Dunes', 'wikidata' => 'Q1935964'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1994-08-28',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Dunkerque-Locale à Bray-Dunes', 235337718),
        'quote' => 'dernières circulations le dimanche 28/08',
        'reason' => 'article de la ligne de Dunkerque-Locale à Bray-Dunes : seuls des trains des dimanches d\'été circulaient les dernières années, la dernière année étant 1994 avec des dernières circulations le 28/08 ; en 1995 le service est assuré par autocars. Précise l\'année 1994 déduite au jour (arrêt du service des trains, pas un arrêté de fermeture). (oldid 235337718)',
    ],

    // La Haye-du-Puits (id 19333)
    [
        'station' => ['name' => 'La Haye-du-Puits', 'wikidata' => 'Q3096789'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1976-01-01',
        'precision' => 'year',
        'source_url' => $wp('Ligne de Carentan à Carteret', 239830382),
        'quote' => 'Elle a été fermée au service des voyageurs dans le courant de l\'été 1976',
        'reason' => 'article de la ligne de Carentan à Carteret : voyageurs fermés dans le courant de l\'été 1976 (jour non donné) ; le 01/08/1976 déduit est la fin du fret Baupte - La Haye-du-Puits. La ligne de Coutances à Sottevast (oldid 240949203) est fermée aux voyageurs au printemps 1970 : 1976 est la dernière desserte. La fin du fret de la gare (24/01/1988, La Haye-du-Puits - Sottevast) reste celle de la fermeture déduite. (oldid 239830382)',
    ],

    // Domfront (Orne) (id 19361)
    [
        'station' => ['name' => 'Domfront (Orne)', 'wikidata' => 'Q3096379'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => $wp('Ligne de La Chapelle-Anthenaise à Flers', 237590911),
        'quote' => 'La ligne est fermée en 1970 en totalité aux voyageurs le 31 mai 1970',
        'reason' => 'article de la ligne de La Chapelle-Anthenaise à Flers : fermée en totalité aux voyageurs le 31/05/1970 ; la ligne d\'Alençon à Domfront perd ses voyageurs le 15/05/1938 et celle de Domfront à Pontaubault le 18/07/1939 (rouverte de 1940 à 1946). Fin du fret Flers - Domfront le 01/10/1990 ; la date de fermeture 2000 déduite de la ligne n\'est pas confirmée. (oldid 237590911)',
    ],

    // Maxonchamp (id 21269)
    [
        'station' => ['name' => 'Maxonchamp', 'wikidata' => 'Q3097003'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1989-05-28',
        'precision' => 'day',
        'source_url' => $wp('Ligne d\'Épinal à Bussang', 219647311),
        'quote' => 'La fin de l\'exploitation de la ligne entre Remiremont et Bussang a eu lieu le 28 mai 1989',
        'reason' => 'article de la ligne d\'Épinal à Bussang : fin de l\'exploitation de Remiremont à Bussang le 28/05/1989 ; le 15/11/1993 déduit est le déclassement de la section, pas l\'arrêt du service. (oldid 219647311)',
    ],

    // Barjols-Tavernes (id 21355)
    [
        'station' => ['name' => 'Barjols-Tavernes', 'wikidata' => 'Q48759889'],
        'action' => 'add',
        'type' => 'closure',
        'date' => '1950-01-02',
        'precision' => 'day',
        'source_url' => $wp('Ligne Central-Var', 229972705),
        'quote' => 'définitivement fermée le 2 janvier 1950',
        'reason' => 'article de la ligne Central-Var : limitée à Meyrargues - Tanneron dès 1945, puis définitivement fermée le 02/01/1950. Précise l\'année 1950 déduite au jour. (oldid 229972705)',
    ],

    // Eauze (id 20061)
    [
        'station' => ['name' => 'Eauze', 'wikidata' => 'Q123498148'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1940-06-27',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Port-Sainte-Marie à Riscle', 239005015),
        'quote' => 'La ligne est fermée au transport des voyageurs le 27 juin 1940.',
        'reason' => 'article de la ligne de Port-Sainte-Marie à Riscle : voyageurs fermés le 27/06/1940 ; la ligne d\'Eauze à Auch (oldid 236368945) est fermée aux voyageurs le 15/05/1939. Dernières circulations de la section Eauze - Riscle fin 2007, la gare restant ouverte administrativement. (oldid 239005015)',
    ],

    // Montpellier - Chaptal (id 21812)
    [
        'station' => ['name' => 'Montpellier - Chaptal', 'wikidata' => 'Q136008508'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1948-02-01',
        'precision' => 'day',
        'source_url' => $wp('Ligne de Montpellier à Rabieux', 239000949),
        'quote' => 'Le 1 février 1948 le service voyageur est supprimé.',
        'reason' => 'article de la ligne de Montpellier à Rabieux : service voyageurs suspendu le 01/01/1933, rétabli le 26/08/1939, supprimé définitivement le 01/02/1948 ; la gare reste un dépôt jusqu\'en 1961. 1953 déduit de la ligne (fin du fret de sections). (oldid 239000949)',
    ],

    // Orbe (id 19669)
    [
        'station' => ['name' => 'Orbe', 'wikidata' => 'Q65137503'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => $wp('Ligne des Sables-d\'Olonne à Tours', 239596144),
        'quote' => 'La section de la ligne entre Thouars et Chinon a cessé de voir circuler des trains de voyageurs le 31 mai 1970',
        'reason' => 'article de la ligne des Sables-d\'Olonne à Tours : voyageurs arrêtés le 31/05/1970 entre Thouars et Chinon. Précise l\'année 1970 déduite au jour. (oldid 239596144)',
    ],

    // Pas-de-Jeu (id 19667)
    [
        'station' => ['name' => 'Pas-de-Jeu', 'wikidata' => 'Q65137493'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => $wp('Ligne des Sables-d\'Olonne à Tours', 239596144),
        'quote' => 'La section de la ligne entre Thouars et Chinon a cessé de voir circuler des trains de voyageurs le 31 mai 1970',
        'reason' => 'article de la ligne des Sables-d\'Olonne à Tours : voyageurs arrêtés le 31/05/1970 entre Thouars et Chinon. Précise l\'année 1970 déduite au jour. (oldid 239596144)',
    ],

    // Basses-Sammarcolles (id 19649)
    [
        'station' => ['name' => 'Basses-Sammarcolles', 'uic' => '87571729'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => $wp('Ligne des Sables-d\'Olonne à Tours', 239596144),
        'quote' => 'La section de la ligne entre Thouars et Chinon a cessé de voir circuler des trains de voyageurs le 31 mai 1970',
        'reason' => 'article de la ligne des Sables-d\'Olonne à Tours : voyageurs arrêtés le 31/05/1970 entre Thouars et Chinon. Précise l\'année 1970 déduite au jour. (oldid 239596144)',
    ],

    // Beuxes (id 19648)
    [
        'station' => ['name' => 'Beuxes', 'uic' => '87571711'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => $wp('Ligne des Sables-d\'Olonne à Tours', 239596144),
        'quote' => 'La section de la ligne entre Thouars et Chinon a cessé de voir circuler des trains de voyageurs le 31 mai 1970',
        'reason' => 'article de la ligne des Sables-d\'Olonne à Tours : voyageurs arrêtés le 31/05/1970 entre Thouars et Chinon. Précise l\'année 1970 déduite au jour. (oldid 239596144)',
    ],

    // La Roche-Clermault (id 19647)
    [
        'station' => ['name' => 'La Roche-Clermault', 'uic' => '87571703'],
        'action' => 'add',
        'type' => 'closure_passenger',
        'date' => '1970-05-31',
        'precision' => 'day',
        'source_url' => $wp('Ligne des Sables-d\'Olonne à Tours', 239596144),
        'quote' => 'La section de la ligne entre Thouars et Chinon a cessé de voir circuler des trains de voyageurs le 31 mai 1970',
        'reason' => 'article de la ligne des Sables-d\'Olonne à Tours : voyageurs arrêtés le 31/05/1970 entre Thouars et Chinon. Précise l\'année 1970 déduite au jour. (oldid 239596144)',
    ],

    // ── 50. Positions des gares dont les sources de position s'écartent le plus (23/11/2026, rapport
    // storage/app/reports/agents/aprem-2111/positions-gares.md) : 150 gares, position établie par au moins deux familles de sources
    // indépendantes à moins de 70 m (BD TOPO équipement de transport, OSM, liste SNCF, coordonnées de l'article Wikipédia ou de
    // Wikidata comptées pour une seule famille), posée sur le bâtiment voyageurs ou le milieu des quais ; l'élément fautif est dit dans « reason ». ──
    // 001. Luxembourg (id 20994)
    [
        'station' => ['name' => 'Luxembourg', 'uic' => '8775861'],
        'action' => 'position',
        'lng' => 2.3402953,
        'lat' => 48.8467395,
        'source_url' => $wp('Gare du Luxembourg', 241230867),
        'quote' => 'Gare du Luxembourg : latitude = 48.846751, longitude = 2.340281',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare du Luxembourg » (oldid 241230867) à 2 m, Wikidata Q1772325 à 8 m, BD TOPO EQ_RESEA0000000000140328 à 0 m ; à 10 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 145 m',
    ],
    // 002. Nouvel-Avricourt (id 18766)
    [
        'station' => ['name' => 'Nouvel-Avricourt', 'uic' => '87215111'],
        'action' => 'position',
        'lng' => 6.8217182,
        'lat' => 48.6512847,
        'source_url' => $wp('Gare de Nouvel-Avricourt', 232258083),
        'quote' => 'Gare de Nouvel-Avricourt : latitude = 48.651300, longitude = 6.821824',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Nouvel-Avricourt » (oldid 232258083) à 8 m, OSM w1375605685 à 0 m ; à 27 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 191 m ; à signaler à la source : liste SNCF UIC 87215111 (point géocodé) à 191 m, Wikidata Q17462003 (P625) à 550 m',
    ],
    // 003. Trélazé (id 16507)
    [
        'station' => ['name' => 'Trélazé', 'uic' => '87484063'],
        'action' => 'position',
        'lng' => -0.4696274,
        'lat' => 47.4528445,
        'source_url' => $wp('Gare de Trélazé', 240684443),
        'quote' => 'Gare de Trélazé : latitude = 47.452861, longitude = -0.469642',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Trélazé » (oldid 240684443) à 2 m, BD TOPO EQ_RESEA0000002201647093 à 46 m, OSM n6271161685 à 39 m, liste SNCF 87701532 à 14 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 78 m ; à signaler à la source : Wikidata Q16508084 (P625) à 391 m',
    ],
    // 004. Boulogne-sur-Gesse (id 21208)
    [
        'station' => ['name' => 'Boulogne-sur-Gesse', 'wikidata' => 'Q56310932'],
        'action' => 'position',
        'lng' => 0.6536905,
        'lat' => 43.2876995,
        'source_url' => $wp('Gare de Boulogne-sur-Gesse', 188119490),
        'quote' => 'Gare de Boulogne-sur-Gesse : latitude = 43.287680, longitude = 0.653700',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Boulogne-sur-Gesse » (oldid 188119490) à 2 m, OHM w198652897 à 2 m ; à 17 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (wikidata) à 186 m ; à signaler à la source : Wikidata Q56310932 (P625) à 445 m',
    ],
    // 005. Avesnes (id 15694)
    [
        'station' => ['name' => 'Avesnes', 'uic' => '87295733'],
        'action' => 'position',
        'lng' => 3.933017,
        'lat' => 50.1269589,
        'source_url' => $wp('Gare d\'Avesnes', 230178819),
        'quote' => 'Gare d\'Avesnes : latitude = 50.126890, longitude = 3.933131',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Avesnes » (oldid 230178819) à 11 m, Wikidata Q3095711 à 11 m, BD TOPO EQ_RESEA0000000056480976 à 14 m, OSM n10186929392 à 0 m, liste SNCF 87295733 à 25 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 75 m',
    ],
    // 006. Thuès (id 18266)
    [
        'station' => ['name' => 'Thuès', 'uic' => '87784751'],
        'action' => 'position',
        'lng' => 2.2274117,
        'lat' => 42.5237564,
        'source_url' => $wp('Gare de Thuès-Carença', 241417690),
        'quote' => 'Gare de Thuès-Carença : latitude = 42.523733, longitude = 2.227429',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Thuès-Carença » (oldid 241417690) à 3 m, Wikidata Q2402394 à 3 m, BD TOPO EQ_RESEA0000000038468513 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 391 m ; à signaler à la source : OSM n939389867 à 394 m, liste SNCF UIC 87784751 (point géocodé) à 391 m, liste SNCF UIC 87784751 (point géocodé) à 394 m',
    ],
    // 007. Domfront (id 16164)
    [
        'station' => ['name' => 'Domfront', 'uic' => '87396200'],
        'action' => 'position',
        'lng' => 0.0262364,
        'lat' => 48.107709,
        'source_url' => $wp('Gare de Domfront (Sarthe)', 221752572),
        'quote' => 'Gare de Domfront (Sarthe) : latitude = 48.107624, longitude = 0.026275',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Domfront (Sarthe) » (oldid 221752572) à 10 m, OSM n4224368144 à 0 m, liste SNCF 87396200 à 4 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 77 m ; à signaler à la source : Wikidata Q3096385 (P625) à 404 m',
    ],
    // 008. Talence-Médoquine (id 19732)
    [
        'station' => ['name' => 'Talence-Médoquine', 'uic' => '87581504'],
        'action' => 'position',
        'lng' => -0.600976,
        'lat' => 44.816282,
        'source_url' => $wp('Gare de Talence-Médoquine', 236100858),
        'quote' => 'Gare de Talence-Médoquine : latitude = 44.816156, longitude = -0.601154',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Talence-Médoquine » (oldid 236100858) à 20 m, Wikidata Q3097820 à 17 m, liste SNCF 87748350 à 36 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 186 m ; à signaler à la source : liste SNCF UIC 87581504 (point géocodé) à 186 m',
    ],
    // 009. Noisy-le-Grand - Mont d’Est (id 20987)
    [
        'station' => ['name' => 'Noisy-le-Grand - Mont d’Est', 'uic' => '8775833'],
        'action' => 'position',
        'lng' => 2.5513294,
        'lat' => 48.8407157,
        'source_url' => $osm('w261942460'),
        'quote' => 'Noisy-le-Grand - Mont d\'Est (osm) : lat = 48.8407834, lon = 2.5513489',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : Wikidata Q1841795 à 33 m, BD TOPO EQ_RESEA0000000324016295 à 1 m, OSM w261942460 à 8 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 261 m ; à signaler à la source : OSM n239822735 à 261 m, coordonnées de l\'article « Gare de Noisy-le-Grand-Mont d\'Est » à 240 m',
    ],
    // 010. Picquigny (id 15744)
    [
        'station' => ['name' => 'Picquigny', 'uic' => '87313106'],
        'action' => 'position',
        'lng' => 2.143258,
        'lat' => 49.9461258,
        'source_url' => $wp('Gare de Picquigny', 240528899),
        'quote' => 'Gare de Picquigny : latitude = 49.946259, longitude = 2.142949',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Picquigny » (oldid 240528899) à 27 m, OSM n5583988491 à 0 m, liste SNCF 87313106 à 4 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 84 m ; à signaler à la source : Wikidata Q2268433 (P625) à 193 m',
    ],
    // 011. St-Laurent-de-la-Prée (id 16528)
    [
        'station' => ['name' => 'St-Laurent-de-la-Prée', 'uic' => '87485136'],
        'action' => 'position',
        'lng' => -1.0268753,
        'lat' => 45.9925893,
        'source_url' => $osm('n9964832833'),
        'quote' => 'Saint-Laurent-de-la-Prée - Fouras (osm) : lat = 45.9925893, lon = -1.0268753',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : OSM n9964832833 à 0 m, liste SNCF 87485136 à 63 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 87 m ; à signaler à la source : Wikidata Q2285744 (P625) à 180 m, coordonnées de l\'article « Gare de Saint-Laurent - Fouras » à 180 m',
    ],
    // 012. Sathonay-Rillieux (id 17706)
    [
        'station' => ['name' => 'Sathonay-Rillieux', 'uic' => '87723700'],
        'action' => 'position',
        'lng' => 4.8752648,
        'lat' => 45.8202291,
        'source_url' => $wp('Gare de Sathonay - Rillieux', 239259404),
        'quote' => 'Gare de Sathonay - Rillieux : latitude = 45.819887, longitude = 4.874797',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Sathonay - Rillieux » (oldid 239259404) à 53 m, Wikidata Q3097685 à 53 m, BD TOPO EQ_RESEA0000000013173220 à 3 m, OSM n4290859477 à 26 m, liste SNCF 87723700 à 5 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 102 m',
    ],
    // 013. La Chapelle-St-Ursin-Morthomiers (id 16821)
    [
        'station' => ['name' => 'La Chapelle-St-Ursin-Morthomiers', 'uic' => '87576264'],
        'action' => 'position',
        'lng' => 2.3024705,
        'lat' => 47.0493611,
        'source_url' => $wp('Gare de La Chapelle-Saint-Ursin - Morthomiers', 214539582),
        'quote' => 'Gare de La Chapelle-Saint-Ursin - Morthomiers : latitude = 47.049179, longitude = 2.302376',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de La Chapelle-Saint-Ursin - Morthomiers » (oldid 214539582) à 21 m, Wikidata Q24936334 à 21 m, BD TOPO EQ_RESEA0000000016698893 à 0 m ; à 17 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 281 m ; à signaler à la source : liste SNCF UIC 87576264 (point géocodé) à 281 m',
    ],
    // 014. Saint-Benoit (id 21048)
    [
        'station' => ['name' => 'Saint-Benoit', 'wikidata' => 'Q120159350'],
        'action' => 'position',
        'lng' => 6.7284166,
        'lat' => 43.959873,
        'source_url' => $osm('w197346620'),
        'quote' => 'Saint-Benoît (osm) : lat = 43.9598730, lon = 6.7284166',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : Wikidata Q120159350 à 8 m, OSM w197346620 à 0 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 258 m ; à signaler à la source : OSM n5812149404 à 258 m',
    ],
    // 015. Valenciennes (id 15910)
    [
        'station' => ['name' => 'Valenciennes', 'uic' => '87343004'],
        'action' => 'position',
        'lng' => 3.5171895,
        'lat' => 50.3632612,
        'source_url' => $wp('Gare de Valenciennes', 241339108),
        'quote' => 'Gare de Valenciennes : latitude = 50.363574, longitude = 3.516697',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Valenciennes » (oldid 241339108) à 49 m, Wikidata Q1887294 à 19 m, BD TOPO EQ_RESEA0000000056480422 à 5 m, OSM n1648968005 à 42 m, liste SNCF 87343004 à 5 m ; à 8 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 97 m',
    ],
    // 016. Schwindratzheim (id 15354)
    [
        'station' => ['name' => 'Schwindratzheim', 'uic' => '87212159'],
        'action' => 'position',
        'lng' => 7.6005468,
        'lat' => 48.7536468,
        'source_url' => $osm('n3507536495'),
        'quote' => 'Schwindratzheim (osm) : lat = 48.7536468, lon = 7.6005468',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : BD TOPO EQ_RESEA0000000048606778 à 13 m, OSM n3507536495 à 0 m, liste SNCF 87212159 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 79 m ; à signaler à la source : Wikidata Q2743043 (P625) à 157 m, coordonnées de l\'article « Gare de Schwindratzheim » à 157 m',
    ],
    // 017. St-Aubin-St-Luperce (id 16140)
    [
        'station' => ['name' => 'St-Aubin-St-Luperce', 'uic' => '87394213'],
        'action' => 'position',
        'lng' => 1.3318523,
        'lat' => 48.4476356,
        'source_url' => $wp('Gare de Saint-Aubin - Saint-Luperce', 223001821),
        'quote' => 'Gare de Saint-Aubin - Saint-Luperce : latitude = 48.447769, longitude = 1.331899',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Aubin - Saint-Luperce » (oldid 223001821) à 15 m, BD TOPO EQ_RESEA0000000015531748 à 16 m, OSM n385361577 à 0 m, liste SNCF 87394213 à 1 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 87 m ; à signaler à la source : Wikidata Q3097495 (P625) à 314 m',
    ],
    // 018. Valence-d'Agen (id 17155)
    [
        'station' => ['name' => 'Valence-d\'Agen', 'uic' => '87611863'],
        'action' => 'position',
        'lng' => 0.8915703,
        'lat' => 44.1116786,
        'source_url' => $wp('Gare de Valence-d\'Agen', 237210627),
        'quote' => 'Gare de Valence-d\'Agen : latitude = 44.111783, longitude = 0.891622',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Valence-d\'Agen » (oldid 237210627) à 12 m, BD TOPO EQ_RESEA0000000025667293 à 3 m, OSM n13289730198 à 14 m, liste SNCF 87611863 à 6 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 82 m',
    ],
    // 019. Couhé-Vérac (id 16790)
    [
        'station' => ['name' => 'Couhé-Vérac', 'uic' => '87575340'],
        'action' => 'position',
        'lng' => 0.2452569,
        'lat' => 46.308189,
        'source_url' => $wp('Gare de Couhé-Vérac', 208460033),
        'quote' => 'Gare de Couhé-Vérac : latitude = 46.308531, longitude = 0.245141',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Couhé-Vérac » (oldid 208460033) à 39 m, Wikidata Q2082613 à 39 m, BD TOPO EQ_RESEA0000000054254002 à 0 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 157 m ; à signaler à la source : liste SNCF UIC 87575340 (point géocodé) à 157 m',
    ],
    // 020. Val d'Europe (id 20954)
    [
        'station' => ['name' => 'Val d\'Europe', 'uic' => '8773006'],
        'action' => 'position',
        'lng' => 2.7738015,
        'lat' => 48.8556361,
        'source_url' => $wp('Gare du Val d\'Europe', 240400832),
        'quote' => 'Gare du Val d\'Europe : latitude = 48.855385, longitude = 2.773383',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare du Val d\'Europe » (oldid 240400832) à 41 m, BD TOPO EQ_RESEA0000000001726061 à 7 m, OSM w116188676 à 7 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 122 m',
    ],
    // 021. Vienne (id 17679)
    [
        'station' => ['name' => 'Vienne', 'uic' => '87722587'],
        'action' => 'position',
        'lng' => 4.8742834,
        'lat' => 45.5211236,
        'source_url' => $wp('Gare de Vienne', 239233480),
        'quote' => 'Gare de Vienne : latitude = 45.521330, longitude = 4.874582',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Vienne » (oldid 239233480) à 33 m, Wikidata Q2744807 à 33 m, BD TOPO EQ_RESEA0000000007458985 à 4 m, OSM w116990333 à 4 m, liste SNCF 87722587 à 11 m ; à 15 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 149 m ; à signaler à la source : OSM n7647450474 à 210 m',
    ],
    // 022. Saint-Jean-de-Maurienne (id 17865)
    [
        'station' => ['name' => 'Saint-Jean-de-Maurienne', 'uic' => '87742320'],
        'action' => 'position',
        'lng' => 6.3535198,
        'lat' => 45.2781926,
        'source_url' => $wp('Gare de Saint-Jean-de-Maurienne - Vallée de l\'Arvan', 238203540),
        'quote' => 'Gare de Saint-Jean-de-Maurienne - Vallée de l\'Arvan : latitude = 45.277701, longitude = 6.354755',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Jean-de-Maurienne - Vallée de l\'Arvan » (oldid 238203540) à 111 m, Wikidata Q3097549 à 111 m, BD TOPO EQ_RESEA0000000012530596 à 4 m, OSM n9911267224 à 50 m, liste SNCF 87742320 à 118 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 72 m',
    ],
    // 023. Conflans-Fin-d'Oise (id 15992)
    [
        'station' => ['name' => 'Conflans-Fin-d\'Oise', 'uic' => '87381459'],
        'action' => 'position',
        'lng' => 2.0746389,
        'lat' => 48.989243,
        'source_url' => $osm('w421970873'),
        'quote' => 'Gare de Conflans Fin d\'Oise (osm) : lat = 48.9892313, lon = 2.0746257',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : BD TOPO EQ_RESEA0000000004792747 à 2 m, OSM w421970873 à 2 m, liste SNCF 87381459 à 7 m ; à 18 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 112 m ; à signaler à la source : OSM n4637346463 à 302 m, OSM w421970879 à 306 m, Wikidata Q2532157 (P625) à 164 m, coordonnées de l\'article « Gare de Conflans-Fin-d\'Oise » à 180 m, BD TOPO EQ_RESEA0000000004792748 à 317 m',
    ],
    // 024. Chenonceaux-Chisseaux (id 16754)
    [
        'station' => ['name' => 'Chenonceaux-Chisseaux', 'uic' => '87574491'],
        'action' => 'position',
        'lng' => 1.0649685,
        'lat' => 47.3307366,
        'source_url' => $wp('Gare de Chenonceaux', 237635303),
        'quote' => 'Gare de Chenonceaux : latitude = 47.330640, longitude = 1.065860',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Chenonceaux » (oldid 237635303) à 68 m, BD TOPO EQ_RESEA0000000031057363 à 21 m, OSM n5111215481 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 170 m ; à signaler à la source : liste SNCF UIC 87574491 (point géocodé) à 170 m',
    ],
    // 025. Port-St-Père-St-Mars (id 16468)
    [
        'station' => ['name' => 'Port-St-Père-St-Mars', 'uic' => '87481218'],
        'action' => 'position',
        'lng' => -1.7394251,
        'lat' => 47.1165631,
        'source_url' => $wp('Gare de Port-Saint-Père - Saint-Mars', 195976729),
        'quote' => 'Gare de Port-Saint-Père - Saint-Mars : latitude = 47.116531, longitude = -1.739524',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Port-Saint-Père - Saint-Mars » (oldid 195976729) à 8 m, Wikidata Q3097322 à 12 m, liste SNCF 87481218 à 12 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 153 m ; à signaler à la source : liste SNCF UIC 87481218 (point géocodé) à 153 m',
    ],
    // 026. Auber (id 20936)
    [
        'station' => ['name' => 'Auber', 'uic' => '8775859'],
        'action' => 'position',
        'lng' => 2.3298611,
        'lat' => 48.8723994,
        'source_url' => $osm('w680119434'),
        'quote' => 'Auber (osm) : lat = 48.8722248, lon = 2.3297922',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : BD TOPO EQ_RESEA0000000000140137 à 1 m, OSM w680119434 à 20 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 99 m ; à signaler à la source : BD TOPO EQ_RESEA0000000000140114 à 285 m, BD TOPO EQ_RESEA0000000000140116 à 676 m',
    ],
    // 027. Luant (id 17073)
    [
        'station' => ['name' => 'Luant', 'uic' => '87597096'],
        'action' => 'position',
        'lng' => 1.5901218,
        'lat' => 46.7357607,
        'source_url' => $wp('Gare de Luant', 235032795),
        'quote' => 'Gare de Luant : latitude = 46.735293, longitude = 1.589707',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Luant » (oldid 235032795) à 61 m, Wikidata Q1970651 à 61 m, OSM n11367547333 à 0 m, liste SNCF 87597096 à 6 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 97 m',
    ],
    // 028. Modane (id 17863)
    [
        'station' => ['name' => 'Modane', 'uic' => '87742007'],
        'action' => 'position',
        'lng' => 6.6589843,
        'lat' => 45.1934693,
        'source_url' => $wp('Gare de Modane', 241197921),
        'quote' => 'Gare de Modane : latitude = 45.192774, longitude = 6.658348',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Modane » (oldid 241197921) à 92 m, Wikidata Q2767199 à 92 m, BD TOPO EQ_RESEA0000000012530544 à 1 m, OSM n11599419549 à 16 m, liste SNCF 87742007 à 66 m ; à 7 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 153 m ; à signaler à la source : liste SNCF UIC 87742007 (point géocodé) à 153 m',
    ],
    // 029. Bonnières (id 16252)
    [
        'station' => ['name' => 'Bonnières', 'uic' => '87415893'],
        'action' => 'position',
        'lng' => 1.5815366,
        'lat' => 49.0386579,
        'source_url' => $wp('Gare de Bonnières', 237096135),
        'quote' => 'Gare de Bonnières : latitude = 49.038745, longitude = 1.581472',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Bonnières » (oldid 237096135) à 11 m, Wikidata Q1826419 à 11 m, BD TOPO EQ_RESEA0000000004792704 à 2 m, OSM n537989902 à 13 m, liste SNCF 87415893 à 51 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 80 m',
    ],
    // 030. St-Agne (id 17115)
    [
        'station' => ['name' => 'St-Agne', 'uic' => '87611301'],
        'action' => 'position',
        'lng' => 1.4502026,
        'lat' => 43.5797073,
        'source_url' => $wp('Gare de Toulouse-Saint-Agne', 237624152),
        'quote' => 'Gare de Toulouse-Saint-Agne : latitude = 43.579644, longitude = 1.450290',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Toulouse-Saint-Agne » (oldid 237624152) à 10 m, Wikidata Q2179915 à 6 m, BD TOPO EQ_RESEA0000000073015890 à 4 m, OSM n6121253683 à 12 m, liste SNCF 87611301 à 4 m, OHM n2093430383 à 43 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 84 m',
    ],
    // 031. Pringy (id 17910)
    [
        'station' => ['name' => 'Pringy', 'uic' => '87746206'],
        'action' => 'position',
        'lng' => 6.1234176,
        'lat' => 45.9395669,
        'source_url' => $wp('Gare de Pringy', 240867816),
        'quote' => 'Gare de Pringy : latitude = 45.939536, longitude = 6.123413',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Pringy » (oldid 240867816) à 3 m, BD TOPO EQ_RESEA0000000067337301 à 8 m, OSM n7093491696 à 0 m, liste SNCF 87746206 à 10 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 10 m ; à signaler à la source : Wikidata Q2448329 (P625) à 8 905 m',
    ],
    // 032. Aigueperse (id 17799)
    [
        'station' => ['name' => 'Aigueperse', 'uic' => '87734129'],
        'action' => 'position',
        'lng' => 3.1981096,
        'lat' => 46.0148696,
        'source_url' => $wp('Gare d\'Aigueperse', 240422105),
        'quote' => 'Gare d\'Aigueperse : latitude = 46.014720, longitude = 3.198060',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Aigueperse » (oldid 240422105) à 17 m, OSM n7316888307 à 0 m, liste SNCF 87734129 à 16 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 16 m ; à signaler à la source : Wikidata Q3095589 (P625) à 7 401 m',
    ],
    // 033. Leffrinckoucke (id 15608)
    [
        'station' => ['name' => 'Leffrinckoucke', 'uic' => '87281626'],
        'action' => 'position',
        'lng' => 2.4334045,
        'lat' => 51.0508772,
        'source_url' => $wp('Gare de Leffrinckoucke', 210978984),
        'quote' => 'Gare de Leffrinckoucke : latitude = 51.050876, longitude = 2.433434',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Leffrinckoucke » (oldid 210978984) à 2 m, liste SNCF 87281626 à 2 m ; à 19 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 2 m ; à signaler à la source : Wikidata Q2476004 (P625) à 6 290 m',
    ],
    // 034. La Wantzenau (id 15367)
    [
        'station' => ['name' => 'La Wantzenau', 'uic' => '87212316'],
        'action' => 'position',
        'lng' => 7.8249589,
        'lat' => 48.6653542,
        'source_url' => $wp('Gare de La Wantzenau', 241525137),
        'quote' => 'Gare de La Wantzenau : latitude = 48.665330, longitude = 7.824930',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de La Wantzenau » (oldid 241525137) à 3 m, OSM n3502488423 à 0 m, liste SNCF 87212316 à 9 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 9 m ; à signaler à la source : Wikidata Q3096820 (P625) à 2 726 m',
    ],
    // 035. Sanatorium-de-Zuydcoote (id 18857)
    [
        'station' => ['name' => 'Sanatorium-de-Zuydcoote', 'uic' => '87287060'],
        'action' => 'position',
        'lng' => 2.4816978,
        'lat' => 51.0627488,
        'source_url' => $wp('Gare du sanatorium-maritime-de-Zuydcoote', 235035430),
        'quote' => 'Gare du sanatorium-maritime-de-Zuydcoote : latitude = 51.062716, longitude = 2.481374',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare du sanatorium-maritime-de-Zuydcoote » (oldid 235035430) à 23 m, liste SNCF 87287060 à 23 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 23 m ; à signaler à la source : Wikidata Q16964691 (P625) à 2 667 m',
    ],
    // 036. Orval-Hyenville (id 19344)
    [
        'station' => ['name' => 'Orval-Hyenville', 'uic' => '87447508'],
        'action' => 'position',
        'lng' => -1.4614691,
        'lat' => 48.993946,
        'source_url' => $wp('Gare d\'Orval - Hyenville', 234730951),
        'quote' => 'Gare d\'Orval - Hyenville : latitude = 48.994105, longitude = -1.461424',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Orval - Hyenville » (oldid 234730951) à 18 m, Wikidata Q106807941 à 6 m, liste SNCF 87447508 à 4 m, galerie de photographies 02d38ae9774a145c3bfd5f27fe99f6403cfb33c6 à 4 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 4 m ; à signaler à la source : Wikidata Q2690852 (P625) à 2 397 m',
    ],
    // 037. Rive-de-Gier (id 17748)
    [
        'station' => ['name' => 'Rive-de-Gier', 'uic' => '87726331'],
        'action' => 'position',
        'lng' => 4.606608,
        'lat' => 45.524354,
        'source_url' => $wp('Gare de Rive-de-Gier', 230263028),
        'quote' => 'Gare de Rive-de-Gier : latitude = 45.524256, longitude = 4.606197',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Rive-de-Gier » (oldid 230263028) à 34 m, Wikidata Q14918105 à 34 m, BD TOPO EQ_RESEA0000000011980786 à 2 m, OSM n6568598621 à 3 m, liste SNCF 87726331 à 5 m, OHM n2126014065 à 2 m ; à 8 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 3 m ; à signaler à la source : liste SNCF UIC 87726331 (point géocodé) à 223 m',
    ],
    // 038. Zuydcoote (id 18856)
    [
        'station' => ['name' => 'Zuydcoote', 'uic' => '87287052'],
        'action' => 'position',
        'lng' => 2.4891294,
        'lat' => 51.064673,
        'source_url' => $wp('Gare de Zuydcoote', 186522274),
        'quote' => 'Gare de Zuydcoote : latitude = 51.064728, longitude = 2.489540',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Zuydcoote » (oldid 186522274) à 29 m, liste SNCF 87287052 à 29 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 29 m ; à signaler à la source : Wikidata Q17091774 (P625) à 2 107 m',
    ],
    // 039. Sancourt (id 19076)
    [
        'station' => ['name' => 'Sancourt', 'uic' => '87345470'],
        'action' => 'position',
        'lng' => 3.2045522,
        'lat' => 50.2308275,
        'source_url' => $wp('Gare de Sancourt', 232678150),
        'quote' => 'Gare de Sancourt : latitude = 50.230889, longitude = 3.204443',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Sancourt » (oldid 232678150) à 10 m, liste SNCF 87345470 à 10 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 10 m ; à signaler à la source : Wikidata Q2098900 (P625) à 2 065 m',
    ],
    // 040. Beaucaire (id 18162)
    [
        'station' => ['name' => 'Beaucaire', 'uic' => '87775023'],
        'action' => 'position',
        'lng' => 4.6470865,
        'lat' => 43.80209,
        'source_url' => $wp('Gare de Beaucaire', 235030712),
        'quote' => 'Gare de Beaucaire : latitude = 43.802238, longitude = 4.647075',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Beaucaire » (oldid 235030712) à 16 m, Wikidata Q3095929 à 16 m, BD TOPO EQ_RESEA0000000095074230 à 14 m, OSM n7270737080 à 15 m, liste SNCF 87775023 à 24 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 24 m',
    ],
    // 041. Torfou (id 16565)
    [
        'station' => ['name' => 'Torfou', 'uic' => '87486894'],
        'action' => 'position',
        'lng' => -1.0937304,
        'lat' => 47.0328413,
        'source_url' => $wp('Gare de Torfou - Le Longeron - Tiffauges', 238676082),
        'quote' => 'Gare de Torfou - Le Longeron - Tiffauges : latitude = 47.032811, longitude = -1.093896',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Torfou - Le Longeron - Tiffauges » (oldid 238676082) à 13 m, Wikidata Q3097881 à 13 m, BD TOPO EQ_RESEA0000000011163550 à 6 m, OSM n5809575640 à 0 m, liste SNCF 87486894 à 6 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 6 m ; à signaler à la source : liste SNCF UIC 87486894 (point géocodé) à 1 793 m',
    ],
    // 042. Rion-des-Landes (id 20120)
    [
        'station' => ['name' => 'Rion-des-Landes', 'uic' => '87673111'],
        'action' => 'position',
        'lng' => -0.917656,
        'lat' => 43.9179593,
        'source_url' => $wp('Gare de Rion-des-Landes', 217417111),
        'quote' => 'Gare de Rion-des-Landes : latitude = 43.917858, longitude = -0.917690',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Rion-des-Landes » (oldid 217417111) à 12 m, liste SNCF 87673111 à 12 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 12 m ; à signaler à la source : Wikidata Q2335619 (P625) à 1 673 m',
    ],
    // 043. Bayonne (id 17312)
    [
        'station' => ['name' => 'Bayonne', 'uic' => '87673004'],
        'action' => 'position',
        'lng' => -1.470448,
        'lat' => 43.4967666,
        'source_url' => $wp('Gare de Bayonne', 241041694),
        'quote' => 'Gare de Bayonne : latitude = 43.497162, longitude = -1.470004',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Bayonne » (oldid 241041694) à 57 m, Wikidata Q3095942 à 57 m, BD TOPO EQ_RESEA0000000025032523 à 25 m, OSM n8312617455 à 27 m, liste SNCF 87673004 à 12 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 27 m',
    ],
    // 044. Les Cabrils (id 18208)
    [
        'station' => ['name' => 'Les Cabrils', 'uic' => '87781559'],
        'action' => 'position',
        'lng' => 3.1860246,
        'lat' => 43.7799932,
        'source_url' => $wp('Gare des Cabrils', 239471104),
        'quote' => 'Gare des Cabrils : latitude = 43.779684, longitude = 3.186088',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare des Cabrils » (oldid 239471104) à 35 m, OSM n444383478 à 0 m, liste SNCF 87781559 à 32 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87781559 (point géocodé) à 1 517 m, Wikidata Q3098145 (P625) à 237 m',
    ],
    // 045. St-Georges-de-Luzençon (id 18225)
    [
        'station' => ['name' => 'St-Georges-de-Luzençon', 'uic' => '87783423'],
        'action' => 'position',
        'lng' => 2.983784,
        'lat' => 44.066553,
        'source_url' => $wp('Gare de Saint-Georges-de-Luzençon', 237112307),
        'quote' => 'Gare de Saint-Georges-de-Luzençon : latitude = 44.065949, longitude = 2.983582',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Georges-de-Luzençon » (oldid 237112307) à 69 m, Wikidata Q3097526 à 69 m, BD TOPO EQ_RESEA0000000105039825 à 75 m, OSM w79060234 à 0 m, liste SNCF 87783423 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783423 (point géocodé) à 1 515 m',
    ],
    // 046. St-Chély-d'Apcher (id 18217)
    [
        'station' => ['name' => 'St-Chély-d\'Apcher', 'uic' => '87783241'],
        'action' => 'position',
        'lng' => 3.2708923,
        'lat' => 44.8017727,
        'source_url' => $wp('Gare de Saint-Chély-d\'Apcher', 237168694),
        'quote' => 'Gare de Saint-Chély-d\'Apcher : latitude = 44.801697, longitude = 3.270942',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Chély-d\'Apcher » (oldid 237168694) à 9 m, Wikidata Q3097505 à 9 m, BD TOPO EQ_RESEA0000000099382990 à 1 m, OSM n13196321018 à 18 m, liste SNCF 87783241 à 16 m ; à 8 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 18 m ; à signaler à la source : liste SNCF UIC 87783241 (point géocodé) à 1 489 m',
    ],
    // 047. Aumont-Aubrac (id 18218)
    [
        'station' => ['name' => 'Aumont-Aubrac', 'uic' => '87783266'],
        'action' => 'position',
        'lng' => 3.2835587,
        'lat' => 44.7182521,
        'source_url' => $wp('Gare d\'Aumont-Aubrac', 237140349),
        'quote' => 'Gare d\'Aumont-Aubrac : latitude = 44.718275, longitude = 3.283415',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Aumont-Aubrac » (oldid 237140349) à 12 m, Wikidata Q2281625 à 12 m, BD TOPO EQ_RESEA0000000099383008 à 18 m, OSM n493923803 à 0 m, liste SNCF 87783266 à 9 m ; à 8 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783266 (point géocodé) à 1 470 m',
    ],
    // 048. Arcomie (id 20892)
    [
        'station' => ['name' => 'Arcomie', 'uic' => '87783233'],
        'action' => 'position',
        'lng' => 3.2510152,
        'lat' => 44.8656189,
        'source_url' => $wp('Gare d\'Arcomie', 202102229),
        'quote' => 'Gare d\'Arcomie : latitude = 44.865793, longitude = 3.251148',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Arcomie » (oldid 202102229) à 22 m, Wikidata Q3095659 à 23 m, OSM n836413089 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783233 (point géocodé) à 1 458 m',
    ],
    // 049. Lunas (id 18209)
    [
        'station' => ['name' => 'Lunas', 'uic' => '87781575'],
        'action' => 'position',
        'lng' => 3.1949198,
        'lat' => 43.7095711,
        'source_url' => $wp('Gare de Lunas', 237057808),
        'quote' => 'Gare de Lunas : latitude = 43.709250, longitude = 3.194754',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Lunas » (oldid 237057808) à 38 m, Wikidata Q3096920 à 38 m, OSM n444372348 à 0 m, liste SNCF 87781575 à 2 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87781575 (point géocodé) à 1 473 m',
    ],
    // 050. Les Cauquillous (id 15861)
    [
        'station' => ['name' => 'Les Cauquillous', 'uic' => '87328021'],
        'action' => 'position',
        'lng' => 1.7546857,
        'lat' => 43.7339939,
        'source_url' => $osm('n774095189'),
        'quote' => 'Les Cauquillous (osm) : lat = 43.7339939, lon = 1.7546857',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : OSM n774095189 à 0 m, liste SNCF 87328021 à 18 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 18 m ; à signaler à la source : Wikidata Q1814219 (P625) à 1 487 m, coordonnées de l\'article « Gare des Cauquillous » à 1 487 m',
    ],
    // 051. Vendôme-Villiers-sur-Loir (id 16705)
    [
        'station' => ['name' => 'Vendôme-Villiers-sur-Loir', 'uic' => '87571216'],
        'action' => 'position',
        'lng' => 1.0210058,
        'lat' => 47.8219983,
        'source_url' => $wp('Gare de Vendôme - Villiers-sur-Loir TGV', 237321482),
        'quote' => 'Gare de Vendôme - Villiers-sur-Loir TGV : latitude = 47.821991, longitude = 1.020781',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Vendôme - Villiers-sur-Loir TGV » (oldid 237321482) à 17 m, Wikidata Q3089660 à 14 m, BD TOPO EQ_RESEA0000000016081820 à 7 m, OSM w30662829 à 0 m, liste SNCF 87571216 à 12 m ; à 11 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87571216 (point géocodé) à 1 465 m',
    ],
    // 052. Millau (id 18215)
    [
        'station' => ['name' => 'Millau', 'uic' => '87783001'],
        'action' => 'position',
        'lng' => 3.074634,
        'lat' => 44.1023694,
        'source_url' => $wp('Gare de Millau', 237112766),
        'quote' => 'Gare de Millau : latitude = 44.102400, longitude = 3.074366',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Millau » (oldid 237112766) à 22 m, Wikidata Q3097038 à 22 m, BD TOPO EQ_RESEA0000000060437440 à 10 m, OSM n10936292223 à 18 m, liste SNCF 87783001 à 6 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 18 m ; à signaler à la source : liste SNCF UIC 87783001 (point géocodé) à 1 457 m',
    ],
    // 053. Garabit (id 20890)
    [
        'station' => ['name' => 'Garabit', 'uic' => '87783217'],
        'action' => 'position',
        'lng' => 3.1792159,
        'lat' => 44.9708448,
        'source_url' => $wp('Gare de Garabit', 229440607),
        'quote' => 'Gare de Garabit : latitude = 44.971113, longitude = 3.178908',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Garabit » (oldid 229440607) à 38 m, Wikidata Q3096516 à 37 m, OSM n957041359 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783217 (point géocodé) à 1 407 m',
    ],
    // 054. Marvejols (id 18219)
    [
        'station' => ['name' => 'Marvejols', 'uic' => '87783282'],
        'action' => 'position',
        'lng' => 3.2810964,
        'lat' => 44.5446591,
        'source_url' => $wp('Gare de Marvejols', 237140265),
        'quote' => 'Gare de Marvejols : latitude = 44.544674, longitude = 3.281013',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Marvejols » (oldid 237140265) à 7 m, Wikidata Q3096989 à 7 m, BD TOPO EQ_RESEA0000000099383059 à 8 m, OSM n369691089 à 0 m, liste SNCF 87783282 à 3 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783282 (point géocodé) à 1 441 m',
    ],
    // 055. Joncels (id 20870)
    [
        'station' => ['name' => 'Joncels', 'uic' => '87781567'],
        'action' => 'position',
        'lng' => 3.1957172,
        'lat' => 43.7369741,
        'source_url' => $wp('Gare de Joncels', 235032236),
        'quote' => 'Gare de Joncels : latitude = 43.737293, longitude = 3.195740',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Joncels » (oldid 235032236) à 36 m, Wikidata Q18745263 à 36 m, OSM n444383349 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87781567 (point géocodé) à 1 406 m',
    ],
    // 056. Givors (id 17672)
    [
        'station' => ['name' => 'Givors', 'uic' => '87722405'],
        'action' => 'position',
        'lng' => 4.7661847,
        'lat' => 45.5850206,
        'source_url' => $wp('Gare de Givors-Ville', 239233402),
        'quote' => 'Gare de Givors-Ville : latitude = 45.585232, longitude = 4.765786',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Givors-Ville » (oldid 239233402) à 39 m, Wikidata Q2788094 à 39 m, BD TOPO EQ_RESEA0000000013173363 à 5 m, OSM n4290854884 à 40 m, liste SNCF 87722405 à 11 m, OHM n2126047310 à 12 m ; à 7 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 40 m ; à signaler à la source : liste SNCF UIC 87722405 (point géocodé) à 234 m',
    ],
    // 057. St-Rome-de-Cernon (id 18226)
    [
        'station' => ['name' => 'St-Rome-de-Cernon', 'uic' => '87783431'],
        'action' => 'position',
        'lng' => 2.9673586,
        'lat' => 44.0139911,
        'source_url' => $wp('Gare de Saint-Rome-de-Cernon', 237086586),
        'quote' => 'Gare de Saint-Rome-de-Cernon : latitude = 44.013980, longitude = 2.967376',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Rome-de-Cernon » (oldid 237086586) à 2 m, Wikidata Q3097612 à 2 m, BD TOPO EQ_RESEA0000000060437452 à 13 m, OSM n348356697 à 0 m, liste SNCF 87783431 à 2 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783431 (point géocodé) à 1 415 m',
    ],
    // 058. Montpaon (id 18206)
    [
        'station' => ['name' => 'Montpaon', 'uic' => '87781534'],
        'action' => 'position',
        'lng' => 3.1097091,
        'lat' => 43.8685341,
        'source_url' => $wp('Gare de Montpaon', 237084998),
        'quote' => 'Gare de Montpaon : latitude = 43.868525, longitude = 3.109723',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Montpaon » (oldid 237084998) à 2 m, Wikidata Q3097085 à 2 m, OSM n11134203425 à 0 m, liste SNCF 87781534 à 2 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87781534 (point géocodé) à 1 399 m',
    ],
    // 059. Ceilhes-Roqueredonde (id 18207)
    [
        'station' => ['name' => 'Ceilhes-Roqueredonde', 'uic' => '87781542'],
        'action' => 'position',
        'lng' => 3.1551959,
        'lat' => 43.8123578,
        'source_url' => $wp('Gare de Ceilhes - Roqueredonde', 237084222),
        'quote' => 'Gare de Ceilhes - Roqueredonde : latitude = 43.812253, longitude = 3.155148',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Ceilhes - Roqueredonde » (oldid 237084222) à 12 m, Wikidata Q3096153 à 12 m, OSM n444383654 à 0 m, liste SNCF 87781542 à 11 m ; à 6 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87781542 (point géocodé) à 1 378 m',
    ],
    // 060. Le Bousquet-d'Orb (id 18210)
    [
        'station' => ['name' => 'Le Bousquet-d\'Orb', 'uic' => '87781583'],
        'action' => 'position',
        'lng' => 3.1690801,
        'lat' => 43.6912698,
        'source_url' => $wp('Gare du Bousquet-d\'Orb', 238475411),
        'quote' => 'Gare du Bousquet-d\'Orb : latitude = 43.691198, longitude = 3.168932',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare du Bousquet-d\'Orb » (oldid 238475411) à 14 m, Wikidata Q3098175 à 14 m, OSM n444366495 à 0 m, liste SNCF 87781583 à 13 m ; à 6 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87781583 (point géocodé) à 1 355 m',
    ],
    // 061. Blécourt (id 19077)
    [
        'station' => ['name' => 'Blécourt', 'uic' => '87345488'],
        'action' => 'position',
        'lng' => 3.2002424,
        'lat' => 50.2122167,
        'source_url' => $wp('Gare de Blécourt', 228219187),
        'quote' => 'Gare de Blécourt : latitude = 50.212029, longitude = 3.200206',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Blécourt » (oldid 228219187) à 21 m, liste SNCF 87345488 à 21 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 21 m ; à signaler à la source : Wikidata Q934732 (P625) à 1 364 m',
    ],
    // 062. St-Flour-Chaudes-Aigues (id 18216)
    [
        'station' => ['name' => 'St-Flour-Chaudes-Aigues', 'uic' => '87783175'],
        'action' => 'position',
        'lng' => 3.1062173,
        'lat' => 45.0350199,
        'source_url' => $wp('Gare de Saint-Flour - Chaudes-Aigues', 237168682),
        'quote' => 'Gare de Saint-Flour - Chaudes-Aigues : latitude = 45.034700, longitude = 3.106210',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Flour - Chaudes-Aigues » (oldid 237168682) à 36 m, Wikidata Q3097518 à 36 m, BD TOPO EQ_RESEA0000000108010160 à 12 m, OSM n957040374 à 0 m, liste SNCF 87783175 à 5 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783175 (point géocodé) à 1 343 m',
    ],
    // 063. Calais-Marée (id 19022)
    [
        'station' => ['name' => 'Calais-Marée', 'uic' => '87317289'],
        'action' => 'position',
        'lng' => 1.8506675,
        'lat' => 50.9535294,
        'source_url' => 'https://data.geopf.fr/wfs/ows?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=BDTOPO_V3:equipement_de_transport&OUTPUTFORMAT=application/json&CQL_FILTER=cleabs%3D%27EQ_RESEA0000000045722284%27',
        'quote' => 'Gare de Calais-Ville (bdtopo) : lat = 50.9535294, lon = 1.8506675',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : BD TOPO EQ_RESEA0000000045722284 à 0 m, liste SNCF 87317289 à 68 m ; à 39 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 68 m ; à signaler à la source : Wikidata Q15971875 (P625) à 1 361 m, coordonnées de l\'article « Gare de Calais-Maritime » à 1 361 m',
    ],
    // 064. Cahors (id 17165)
    [
        'station' => ['name' => 'Cahors', 'uic' => '87613000'],
        'action' => 'position',
        'lng' => 1.4332726,
        'lat' => 44.4492353,
        'source_url' => $wp('Gare de Cahors', 240125009),
        'quote' => 'Gare de Cahors : latitude = 44.449212, longitude = 1.433004',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Cahors » (oldid 240125009) à 21 m, Wikidata Q2361230 à 19 m, BD TOPO EQ_RESEA0000000015023003 à 15 m, OSM n388185240 à 14 m, liste SNCF 87613000 à 6 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 14 m',
    ],
    // 065. Breteil (id 16315)
    [
        'station' => ['name' => 'Breteil', 'uic' => '87471102'],
        'action' => 'position',
        'lng' => -1.8972095,
        'lat' => 48.1405713,
        'source_url' => $wp('Gare de Breteil', 223670566),
        'quote' => 'Gare de Breteil : latitude = 48.140526, longitude = -1.897384',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Breteil » (oldid 223670566) à 14 m, Wikidata Q2847254 à 14 m, BD TOPO EQ_RESEA0000000046889514 à 19 m, OSM n3467544494 à 0 m, liste SNCF 87471102 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87471102 (point géocodé) à 1 256 m',
    ],
    // 066. Campagnac-St-Geniez (id 18223)
    [
        'station' => ['name' => 'Campagnac-St-Geniez', 'uic' => '87783340'],
        'action' => 'position',
        'lng' => 3.0663959,
        'lat' => 44.4219997,
        'source_url' => $wp('Gare de Campagnac - Saint-Geniez', 237133501),
        'quote' => 'Gare de Campagnac - Saint-Geniez : latitude = 44.421964, longitude = 3.066336',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Campagnac - Saint-Geniez » (oldid 237133501) à 6 m, Wikidata Q3096117 à 6 m, OSM n1027947109 à 0 m, liste SNCF 87783340 à 5 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783340 (point géocodé) à 1 255 m',
    ],
    // 067. Chirac (id 18220)
    [
        'station' => ['name' => 'Chirac', 'uic' => '87783290'],
        'action' => 'position',
        'lng' => 3.2637499,
        'lat' => 44.5244688,
        'source_url' => $wp('Gare de Chirac', 240497721),
        'quote' => 'Gare de Chirac : latitude = 44.524577, longitude = 3.263797',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Chirac » (oldid 240497721) à 13 m, Wikidata Q3096221 à 13 m, BD TOPO EQ_RESEA0000000099383073 à 26 m, OSM n799573565 à 0 m, liste SNCF 87783290 à 22 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783290 (point géocodé) à 1 223 m',
    ],
    // 068. Croix-l'Allumette (id 15587)
    [
        'station' => ['name' => 'Croix-l\'Allumette', 'uic' => '87281055'],
        'action' => 'position',
        'lng' => 3.1512449,
        'lat' => 50.6868931,
        'source_url' => $wp('Gare de Croix-L\'Allumette', 233760511),
        'quote' => 'Gare de Croix-L\'Allumette : latitude = 50.686904, longitude = 3.151387',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Croix-L\'Allumette » (oldid 233760511) à 10 m, OSM n7042284669 à 0 m, liste SNCF 87281055 à 60 m, OHM n2090840305 à 5 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 60 m ; à signaler à la source : Wikidata Q3096324 (P625) à 1 287 m',
    ],
    // 069. Banassac-La Canourgue (id 18222)
    [
        'station' => ['name' => 'Banassac-La Canourgue', 'uic' => '87783324'],
        'action' => 'position',
        'lng' => 3.195884,
        'lat' => 44.4475487,
        'source_url' => $wp('Gare de Banassac - La Canourgue', 237138170),
        'quote' => 'Gare de Banassac - La Canourgue : latitude = 44.447516, longitude = 3.196146',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Banassac - La Canourgue » (oldid 237138170) à 21 m, Wikidata Q2410794 à 21 m, BD TOPO EQ_RESEA0000000099383114 à 17 m, OSM n3470966794 à 0 m, liste SNCF 87783324 à 24 m ; à 7 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783324 (point géocodé) à 1 198 m',
    ],
    // 070. Plouaret-Trégor (id 16355)
    [
        'station' => ['name' => 'Plouaret-Trégor', 'uic' => '87473181'],
        'action' => 'position',
        'lng' => -3.4667489,
        'lat' => 48.6062088,
        'source_url' => $wp('Gare de Plouaret-Trégor', 230643854),
        'quote' => 'Gare de Plouaret-Trégor : latitude = 48.606232, longitude = -3.466863',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Plouaret-Trégor » (oldid 230643854) à 9 m, Wikidata Q2434830 à 9 m, BD TOPO EQ_RESEA0000000076159641 à 20 m, OSM n4294306010 à 0 m, liste SNCF 87473181 à 10 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87473181 (point géocodé) à 1 196 m',
    ],
    // 071. Prades-Molitg-les-Bains (id 18258)
    [
        'station' => ['name' => 'Prades-Molitg-les-Bains', 'uic' => '87784637'],
        'action' => 'position',
        'lng' => 2.4292664,
        'lat' => 42.6171455,
        'source_url' => $wp('Gare de Prades - Molitg-les-Bains', 238792145),
        'quote' => 'Gare de Prades - Molitg-les-Bains : latitude = 42.617059, longitude = 2.429304',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Prades - Molitg-les-Bains » (oldid 238792145) à 10 m, BD TOPO EQ_RESEA0000000038468434 à 1 m, OSM w30473927 à 0 m, liste SNCF 87784637 à 13 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87784637 (point géocodé) à 709 m, Wikidata Q1897049 (P625) à 541 m',
    ],
    // 072. Cherbourg-en-Cotentin (id 16289)
    [
        'station' => ['name' => 'Cherbourg-en-Cotentin', 'uic' => '87444877'],
        'action' => 'position',
        'lng' => -1.6210672,
        'lat' => 49.6333468,
        'source_url' => $osm('w112095036'),
        'quote' => 'Cherbourg-en-Cotentin (osm) : lat = 49.6333468, lon = -1.6210672',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : Wikidata Q2677692 à 23 m, BD TOPO EQ_RESEA0000000024230403 à 28 m, OSM w112095036 à 0 m, liste SNCF 87444877 à 37 m ; à 7 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87444877 (point géocodé) à 1 037 m',
    ],
    // 073. Massy-TGV (id 16127)
    [
        'station' => ['name' => 'Massy-TGV', 'uic' => '87393702'],
        'action' => 'position',
        'lng' => 2.2613784,
        'lat' => 48.7258466,
        'source_url' => $wp('Gare de Massy TGV', 236089855),
        'quote' => 'Gare de Massy TGV : latitude = 48.725840, longitude = 2.261345',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Massy TGV » (oldid 236089855) à 3 m, Wikidata Q801170 à 36 m, BD TOPO EQ_RESEA0000000003089599 à 19 m, OSM n3530841971 à 3 m, liste SNCF 87393702 à 13 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 3 m ; à signaler à la source : liste SNCF UIC 87393702 (point géocodé) à 1 007 m',
    ],
    // 074. Venaco (id 20726)
    [
        'station' => ['name' => 'Venaco', 'uic' => '87762161'],
        'action' => 'position',
        'lng' => 9.1749801,
        'lat' => 42.2332163,
        'source_url' => $wp('Gare de Venaco', 229547644),
        'quote' => 'Gare de Venaco : latitude = 42.233654, longitude = 9.175246',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Venaco » (oldid 229547644) à 53 m, Wikidata Q3097946 à 53 m, BD TOPO EQ_RESEA0000000109751229 à 2 m, OSM n270887644 à 10 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 10 m',
    ],
    // 075. Gorges (id 16479)
    [
        'station' => ['name' => 'Gorges', 'uic' => '87481432'],
        'action' => 'position',
        'lng' => -1.3061145,
        'lat' => 47.100327,
        'source_url' => $wp('Gare de Gorges', 238076645),
        'quote' => 'Gare de Gorges : latitude = 47.100110, longitude = -1.305892',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Gorges » (oldid 238076645) à 29 m, BD TOPO EQ_RESEA0000000060253139 à 40 m, OSM n3287430375 à 0 m, liste SNCF 87481432 à 8 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 8 m ; à signaler à la source : Wikidata Q3096555 (P625) à 1 026 m',
    ],
    // 076. Pouxeux (id 15058)
    [
        'station' => ['name' => 'Pouxeux', 'uic' => '87144428'],
        'action' => 'position',
        'lng' => 6.5694811,
        'lat' => 48.108776,
        'source_url' => $wp('Gare de Pouxeux', 241364438),
        'quote' => 'Gare de Pouxeux : latitude = 48.108790, longitude = 6.569473',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Pouxeux » (oldid 241364438) à 2 m, Wikidata Q1938345 à 6 m, OSM n8171965480 à 0 m, liste SNCF 87144428 à 17 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 17 m ; à signaler à la source : liste SNCF UIC 87144428 (point géocodé) à 1 012 m',
    ],
    // 077. Liévin CEN (id 21189)
    [
        'station' => ['name' => 'Liévin CEN', 'wikidata' => 'Q61741595'],
        'action' => 'position',
        'lng' => 2.772826,
        'lat' => 50.4202114,
        'source_url' => $wp('Gare de Liévin (CEN)', 238683057),
        'quote' => 'Gare de Liévin (CEN) : latitude = 50.420126, longitude = 2.772860',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Liévin (CEN) » (oldid 238683057) à 10 m, Wikidata Q61741595 à 10 m, OHM n2093639580 à 10 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (wikidata) à 10 m',
    ],
    // 078. Bessèges (id 20831)
    [
        'station' => ['name' => 'Bessèges', 'uic' => '87775494'],
        'action' => 'position',
        'lng' => 4.1005901,
        'lat' => 44.2884691,
        'source_url' => $wp('Gare de Bessèges', 235030861),
        'quote' => 'Gare de Bessèges : latitude = 44.288351, longitude = 4.100489',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Bessèges » (oldid 235030861) à 15 m, Wikidata Q641621 à 44 m, OSM n1601214589 à 0 m ; à 16 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87775494 (point géocodé) à 922 m',
    ],
    // 079. Albertville (id 17854)
    [
        'station' => ['name' => 'Albertville', 'uic' => '87741645'],
        'action' => 'position',
        'lng' => 6.3832429,
        'lat' => 45.6731918,
        'source_url' => $wp('Gare d\'Albertville', 232703134),
        'quote' => 'Gare d\'Albertville : latitude = 45.673282, longitude = 6.383208',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Albertville » (oldid 232703134) à 10 m, BD TOPO EQ_RESEA0000000012530160 à 25 m, OSM n3080728368 à 0 m, liste SNCF 87741645 à 25 m ; à 9 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87741645 (point géocodé) à 958 m',
    ],
    // 080. Esches (id 15506)
    [
        'station' => ['name' => 'Esches', 'uic' => '87272096'],
        'action' => 'position',
        'lng' => 2.1708179,
        'lat' => 49.2125135,
        'source_url' => $wp('Gare d\'Esches', 228946770),
        'quote' => 'Gare d\'Esches : latitude = 49.212534, longitude = 2.170768',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Esches » (oldid 228946770) à 4 m, OSM n2279594708 à 0 m, liste SNCF 87272096 à 17 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 17 m ; à signaler à la source : Wikidata Q2007719 (P625) à 928 m',
    ],
    // 081. Dommartin-Lissieu (id 17656)
    [
        'station' => ['name' => 'Dommartin-Lissieu', 'uic' => '87721738'],
        'action' => 'position',
        'lng' => 4.7344912,
        'lat' => 45.8397827,
        'source_url' => $wp('Gare de Dommartin - Lissieu', 241156143),
        'quote' => 'Gare de Dommartin - Lissieu : latitude = 45.839739, longitude = 4.734408',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Dommartin - Lissieu » (oldid 241156143) à 8 m, Wikidata Q3096384 à 8 m, BD TOPO EQ_RESEA0000000013173217 à 12 m, OSM w322836835 à 0 m, liste SNCF 87721738 à 40 m, OHM n2137237657 à 1 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 40 m',
    ],
    // 082. Heugnes (id 19702)
    [
        'station' => ['name' => 'Heugnes', 'uic' => '87576397'],
        'action' => 'position',
        'lng' => 1.4138362,
        'lat' => 47.0147438,
        'source_url' => $wp('Gare d\'Heugnes', 232043846),
        'quote' => 'Gare d\'Heugnes : latitude = 47.014694, longitude = 1.413875',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Heugnes » (oldid 232043846) à 6 m, Wikidata Q2788535 à 13 m, OSM n1960034853 à 15 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 15 m ; à signaler à la source : liste SNCF UIC 87576397 (point géocodé) à 868 m',
    ],
    // 083. Le Monastier (id 18221)
    [
        'station' => ['name' => 'Le Monastier', 'uic' => '87783308'],
        'action' => 'position',
        'lng' => 3.2521133,
        'lat' => 44.508993,
        'source_url' => $wp('Gare du Monastier', 236369378),
        'quote' => 'Gare du Monastier : latitude = 44.508946, longitude = 3.252054',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare du Monastier » (oldid 236369378) à 7 m, Wikidata Q3098214 à 7 m, OSM n470273463 à 0 m, liste SNCF 87783308 à 5 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783308 (point géocodé) à 872 m',
    ],
    // 084. Chef-du-Pont-Ste-Mère (id 19329)
    [
        'station' => ['name' => 'Chef-du-Pont-Ste-Mère', 'uic' => '87447250'],
        'action' => 'position',
        'lng' => -1.3442307,
        'lat' => 49.3845555,
        'source_url' => $wp('Gare de Chef-du-Pont - Sainte-Mère', 231489952),
        'quote' => 'Gare de Chef-du-Pont - Sainte-Mère : latitude = 49.384494, longitude = -1.344192',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Chef-du-Pont - Sainte-Mère » (oldid 231489952) à 7 m, Wikidata Q3096211 à 7 m, OSM n1935227822 à 0 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87447250 (point géocodé) à 851 m',
    ],
    // 085. Carentan (id 16295)
    [
        'station' => ['name' => 'Carentan', 'uic' => '87447243'],
        'action' => 'position',
        'lng' => -1.2449307,
        'lat' => 49.3023893,
        'source_url' => $wp('Gare de Carentan', 235836451),
        'quote' => 'Gare de Carentan : latitude = 49.302276, longitude = -1.244986',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Carentan » (oldid 235836451) à 13 m, Wikidata Q1842714 à 13 m, BD TOPO EQ_RESEA0000000024230734 à 1 m, OSM w198292010 à 0 m, liste SNCF 87447243 à 10 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87447243 (point géocodé) à 841 m',
    ],
    // 086. Valognes (id 16296)
    [
        'station' => ['name' => 'Valognes', 'uic' => '87447284'],
        'action' => 'position',
        'lng' => -1.4800289,
        'lat' => 49.5052265,
        'source_url' => $wp('Gare de Valognes', 235347517),
        'quote' => 'Gare de Valognes : latitude = 49.505250, longitude = -1.480001',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Valognes » (oldid 235347517) à 3 m, Wikidata Q771166 à 3 m, BD TOPO EQ_RESEA0000000111641570 à 17 m, OSM w226306303 à 0 m, liste SNCF 87447284 à 28 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87447284 (point géocodé) à 830 m',
    ],
    // 087. Sévérac-le-Château (id 18224)
    [
        'station' => ['name' => 'Sévérac-le-Château', 'uic' => '87783365'],
        'action' => 'position',
        'lng' => 3.0569861,
        'lat' => 44.3246336,
        'source_url' => $wp('Gare de Sévérac-le-Château', 240368598),
        'quote' => 'Gare de Sévérac-le-Château : latitude = 44.324645, longitude = 3.056842',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Sévérac-le-Château » (oldid 240368598) à 12 m, Wikidata Q2191151 à 9 m, BD TOPO EQ_RESEA0000000060437412 à 7 m, OSM n11134203424 à 0 m, liste SNCF 87783365 à 4 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783365 (point géocodé) à 673 m',
    ],
    // 088. Arles (id 18012)
    [
        'station' => ['name' => 'Arles', 'uic' => '87753657'],
        'action' => 'position',
        'lng' => 4.6320536,
        'lat' => 43.6847247,
        'source_url' => $wp('Gare d\'Arles', 235030325),
        'quote' => 'Gare d\'Arles : latitude = 43.684812, longitude = 4.631861',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Arles » (oldid 235030325) à 18 m, Wikidata Q1897505 à 9 m, BD TOPO EQ_RESEA0000000040208115 à 3 m, OSM n10933963119 à 24 m, liste SNCF 87753657 à 14 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 24 m ; à signaler à la source : liste SNCF UIC 87753657 (point géocodé) à 351 m',
    ],
    // 089. Neussargues (id 17272)
    [
        'station' => ['name' => 'Neussargues', 'uic' => '87645507'],
        'action' => 'position',
        'lng' => 2.979191,
        'lat' => 45.1254454,
        'source_url' => $wp('Gare de Neussargues', 237602788),
        'quote' => 'Gare de Neussargues : latitude = 45.125444, longitude = 2.979222',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Neussargues » (oldid 237602788) à 2 m, Wikidata Q2156841 à 2 m, BD TOPO EQ_RESEA0000000108010122 à 1 m, OSM n9956762221 à 13 m, liste SNCF 87645507 à 16 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 13 m ; à signaler à la source : liste SNCF UIC 87645507 (point géocodé) à 542 m',
    ],
    // 090. Tournemire-Roquefort (id 18227)
    [
        'station' => ['name' => 'Tournemire-Roquefort', 'uic' => '87783456'],
        'action' => 'position',
        'lng' => 3.0166443,
        'lat' => 43.970018,
        'source_url' => $wp('Gare de Tournemire - Roquefort', 237085613),
        'quote' => 'Gare de Tournemire - Roquefort : latitude = 43.969722, longitude = 3.016667',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Tournemire - Roquefort » (oldid 237085613) à 33 m, Wikidata Q3097889 à 33 m, BD TOPO EQ_RESEA0000000060437456 à 8 m, OSM n9689376063 à 0 m, liste SNCF 87783456 à 24 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783456 (point géocodé) à 750 m',
    ],
    // 091. Vendenheim (id 15350)
    [
        'station' => ['name' => 'Vendenheim', 'uic' => '87212118'],
        'action' => 'position',
        'lng' => 7.7181672,
        'lat' => 48.6657397,
        'source_url' => $wp('Gare de Vendenheim', 241462022),
        'quote' => 'Gare de Vendenheim : latitude = 48.665998, longitude = 7.718044',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Vendenheim » (oldid 241462022) à 30 m, Wikidata Q2190004 à 30 m, BD TOPO EQ_RESEA0000000048606824 à 15 m, OSM n9315748060 à 0 m, liste SNCF 87212118 à 38 m, OHM n2178212619 à 39 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87212118 (point géocodé) à 750 m',
    ],
    // 092. Marseille-St-Charles (id 17963)
    [
        'station' => ['name' => 'Marseille-St-Charles', 'uic' => '87751008'],
        'action' => 'position',
        'lng' => 5.3806611,
        'lat' => 43.3031977,
        'source_url' => $osm('n4290857022'),
        'quote' => 'Marseille Saint-Charles (osm) : lat = 43.3032635, lon = 5.3811927',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : Wikidata Q371217 à 40 m, BD TOPO EQ_RESEA0000000040208439 à 26 m, OSM n4290857022 à 44 m, liste SNCF 87751008 à 63 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 44 m ; à signaler à la source : liste SNCF UIC 87751008 (point géocodé) à 369 m',
    ],
    // 093. St-Gilles (id 15188)
    [
        'station' => ['name' => 'St-Gilles', 'uic' => '87181552'],
        'action' => 'position',
        'lng' => 7.2610708,
        'lat' => 48.0759067,
        'source_url' => $osm('n9200872122'),
        'quote' => 'Saint-Gilles Lycée Haut-Rhin (osm) : lat = 48.0759067, lon = 7.2610708',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : BD TOPO EQ_RESEA0000000047868991 à 6 m, OSM n9200872122 à 0 m, liste SNCF 87181552 à 14 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 14 m ; à signaler à la source : Wikidata Q3097534 (P625) à 728 m, coordonnées de l\'article « Gare de Saint-Gilles » à 730 m',
    ],
    // 094. St-Féliu-d'Avall (id 18253)
    [
        'station' => ['name' => 'St-Féliu-d\'Avall', 'uic' => '87784520'],
        'action' => 'position',
        'lng' => 2.7378292,
        'lat' => 42.6773175,
        'source_url' => $wp('Gare de Saint-Féliu-d\'Avall', 238862967),
        'quote' => 'Gare de Saint-Féliu-d\'Avall : latitude = 42.677385, longitude = 2.737735',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Féliu-d\'Avall » (oldid 238862967) à 11 m, Wikidata Q1937209 à 11 m, BD TOPO EQ_RESEA0000000038468404 à 25 m, OSM n9288070933 à 0 m, liste SNCF 87784520 à 3 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87784520 (point géocodé) à 706 m',
    ],
    // 095. Rungis-la-Fraternelle (id 16693)
    [
        'station' => ['name' => 'Rungis-la-Fraternelle', 'uic' => '87546291'],
        'action' => 'position',
        'lng' => 2.3527966,
        'lat' => 48.740342,
        'source_url' => $wp('Gare de Rungis - La Fraternelle', 237812606),
        'quote' => 'Gare de Rungis - La Fraternelle : latitude = 48.740444, longitude = 2.352790',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Rungis - La Fraternelle » (oldid 237812606) à 11 m, Wikidata Q2784751 à 23 m, BD TOPO EQ_RESEA0000000004264410 à 1 m, OSM n3531066587 à 18 m, liste SNCF 87546291 à 14 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 18 m ; à signaler à la source : liste SNCF UIC 87546291 (point géocodé) à 691 m',
    ],
    // 096. Villers-Cotterêts (id 15493)
    [
        'station' => ['name' => 'Villers-Cotterêts', 'uic' => '87271734'],
        'action' => 'position',
        'lng' => 3.0938506,
        'lat' => 49.250125,
        'source_url' => $wp('Gare de Villers-Cotterêts', 237134346),
        'quote' => 'Gare de Villers-Cotterêts : latitude = 49.250107, longitude = 3.093957',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Villers-Cotterêts » (oldid 237134346) à 8 m, Wikidata Q2234195 à 8 m, BD TOPO EQ_RESEA0000000063661208 à 12 m, OSM n9805769321 à 0 m, liste SNCF 87271734 à 19 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87271734 (point géocodé) à 686 m',
    ],
    // 097. Argy (id 19707)
    [
        'station' => ['name' => 'Argy', 'uic' => '87576595'],
        'action' => 'position',
        'lng' => 1.4411659,
        'lat' => 46.9388304,
        'source_url' => $wp('Gare d\'Argy', 231251196),
        'quote' => 'Gare d\'Argy : latitude = 46.938812, longitude = 1.440952',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Argy » (oldid 231251196) à 16 m, Wikidata Q3095664 à 16 m, OSM n1954826502 à 0 m ; à 6 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87576595 (point géocodé) à 702 m',
    ],
    // 098. Ille-sur-Têt (id 18255)
    [
        'station' => ['name' => 'Ille-sur-Têt', 'uic' => '87784561'],
        'action' => 'position',
        'lng' => 2.6243485,
        'lat' => 42.6683785,
        'source_url' => $wp('Gare d\'Ille-sur-Têt', 238988818),
        'quote' => 'Gare d\'Ille-sur-Têt : latitude = 42.668301, longitude = 2.624401',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Ille-sur-Têt » (oldid 238988818) à 10 m, Wikidata Q3095787 à 10 m, BD TOPO EQ_RESEA0000000038468405 à 2 m, OSM n13246593429 à 17 m, liste SNCF 87784561 à 16 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 17 m ; à signaler à la source : liste SNCF UIC 87784561 (point géocodé) à 684 m',
    ],
    // 099. Bures-sur-Yvette (id 21006)
    [
        'station' => ['name' => 'Bures-sur-Yvette', 'uic' => '8775885'],
        'action' => 'position',
        'lng' => 2.1634155,
        'lat' => 48.6958563,
        'source_url' => $wp('Gare de Bures-sur-Yvette', 237898559),
        'quote' => 'Gare de Bures-sur-Yvette : latitude = 48.695784, longitude = 2.163042',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Bures-sur-Yvette » (oldid 237898559) à 29 m, Wikidata Q3096100 à 28 m, BD TOPO EQ_RESEA0000000003089644 à 30 m, OSM n5838690264 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m',
    ],
    // 100. Millas (id 18254)
    [
        'station' => ['name' => 'Millas', 'uic' => '87784546'],
        'action' => 'position',
        'lng' => 2.693772,
        'lat' => 42.6889497,
        'source_url' => $wp('Gare de Millas', 239510075),
        'quote' => 'Gare de Millas : latitude = 42.689002, longitude = 2.693307',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Millas » (oldid 239510075) à 38 m, Wikidata Q2609571 à 38 m, BD TOPO EQ_RESEA0000000038468401 à 12 m, OSM n9286224580 à 0 m, liste SNCF 87784546 à 6 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87784546 (point géocodé) à 658 m',
    ],
    // 101. Fraisses-Unieux (id 17759)
    [
        'station' => ['name' => 'Fraisses-Unieux', 'uic' => '87726737'],
        'action' => 'position',
        'lng' => 4.2611918,
        'lat' => 45.3947974,
        'source_url' => $wp('Gare de Fraisses - Unieux', 238735800),
        'quote' => 'Gare de Fraisses - Unieux : latitude = 45.394771, longitude = 4.261145',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Fraisses - Unieux » (oldid 238735800) à 5 m, BD TOPO EQ_RESEA0000000011980844 à 2 m, OSM n9974582845 à 0 m, liste SNCF 87726737 à 35 m, OHM n2125383399 à 23 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 35 m ; à signaler à la source : Wikidata Q3096467 (P625) à 665 m',
    ],
    // 102. Pont-à-Vendin (id 15939)
    [
        'station' => ['name' => 'Pont-à-Vendin', 'uic' => '87345264'],
        'action' => 'position',
        'lng' => 2.8752081,
        'lat' => 50.4714065,
        'source_url' => $wp('Gare de Pont-à-Vendin', 238682997),
        'quote' => 'Gare de Pont-à-Vendin : latitude = 50.471384, longitude = 2.875150',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Pont-à-Vendin » (oldid 238682997) à 5 m, Wikidata Q3097306 à 5 m, OSM n917150916 à 0 m, liste SNCF 87345264 à 3 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87345264 (point géocodé) à 688 m',
    ],
    // 103. Ria (id 18259)
    [
        'station' => ['name' => 'Ria', 'uic' => '87784660'],
        'action' => 'position',
        'lng' => 2.390617,
        'lat' => 42.6035315,
        'source_url' => $wp('Gare de Ria', 240706620),
        'quote' => 'Gare de Ria : latitude = 42.603422, longitude = 2.390676',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Ria » (oldid 240706620) à 13 m, Wikidata Q2455646 à 13 m, BD TOPO EQ_RESEA0000000038468443 à 18 m, OSM w303986587 à 0 m, liste SNCF 87784660 à 17 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87784660 (point géocodé) à 683 m',
    ],
    // 104. Donges-Bonne Nouvelle (id 16493)
    [
        'station' => ['name' => 'Donges-Bonne Nouvelle', 'uic' => '87481721'],
        'action' => 'position',
        'lng' => -2.073832,
        'lat' => 47.3147972,
        'source_url' => $wp('Gare de Donges', 235031656),
        'quote' => 'Gare de Donges : latitude = 47.315000, longitude = -2.073570',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Donges » (oldid 235031656) à 30 m, Wikidata Q3052088 à 53 m, BD TOPO EQ_RESEA0000000028743715 à 30 m, OSM n10003047039 à 0 m, liste SNCF 87481721 à 40 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 40 m ; à signaler à la source : OSM n3486337397 à 698 m',
    ],
    // 105. Écueillé (id 19701)
    [
        'station' => ['name' => 'Écueillé', 'uic' => '87576389'],
        'action' => 'position',
        'lng' => 1.3575749,
        'lat' => 47.079997,
        'source_url' => $wp('Gare d\'Écueillé', 233821975),
        'quote' => 'Gare d\'Écueillé : latitude = 47.079885, longitude = 1.357495',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Écueillé » (oldid 233821975) à 14 m, Wikidata Q3095868 à 4 m, OSM n1041358793 à 0 m ; à 6 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87576389 (point géocodé) à 671 m',
    ],
    // 106. Cases-de-Pène (id 18250)
    [
        'station' => ['name' => 'Cases-de-Pène', 'uic' => '87784488'],
        'action' => 'position',
        'lng' => 2.7931033,
        'lat' => 42.7790421,
        'source_url' => $wp('Gare de Cases-de-Pène', 228759389),
        'quote' => 'Gare de Cases-de-Pène : latitude = 42.779042, longitude = 2.793103',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Cases-de-Pène » (oldid 228759389) à 0 m, Wikidata Q28710784 à 15 m, OSM n283613546 à 0 m, liste SNCF 87784488 à 7 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 7 m ; à signaler à la source : OSM n13235136106 à 669 m',
    ],
    // 107. Port-Vendres (id 18243)
    [
        'station' => ['name' => 'Port-Vendres', 'uic' => '87784264'],
        'action' => 'position',
        'lng' => 3.1025761,
        'lat' => 42.5136077,
        'source_url' => $wp('Gare de Port-Vendres-Ville', 238784803),
        'quote' => 'Gare de Port-Vendres-Ville : latitude = 42.513490, longitude = 3.102698',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Port-Vendres-Ville » (oldid 238784803) à 16 m, BD TOPO EQ_RESEA0000000038468531 à 22 m, OSM n5669448842 à 17 m, liste SNCF 87784264 à 27 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 27 m ; à signaler à la source : Wikidata Q2212891 (P625) à 645 m',
    ],
    // 108. Lison (id 16294)
    [
        'station' => ['name' => 'Lison', 'uic' => '87447219'],
        'action' => 'position',
        'lng' => -1.0503463,
        'lat' => 49.2269115,
        'source_url' => $wp('Gare de Lison', 235032670),
        'quote' => 'Gare de Lison : latitude = 49.226992, longitude = -1.050403',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Lison » (oldid 235032670) à 10 m, Wikidata Q3096895 à 10 m, BD TOPO EQ_RESEA0000000013905698 à 10 m, OSM n8862955467 à 3 m, liste SNCF 87447219 à 9 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 3 m ; à signaler à la source : liste SNCF UIC 87447219 (point géocodé) à 656 m',
    ],
    // 109. Marquixanes (id 18257)
    [
        'station' => ['name' => 'Marquixanes', 'uic' => '87784629'],
        'action' => 'position',
        'lng' => 2.4858285,
        'lat' => 42.6427724,
        'source_url' => $wp('Gare de Marquixanes', 237173086),
        'quote' => 'Gare de Marquixanes : latitude = 42.642797, longitude = 2.485838',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Marquixanes » (oldid 237173086) à 3 m, Wikidata Q2437638 à 3 m, BD TOPO EQ_RESEA0000000312707556 à 33 m, OSM n9737372885 à 0 m, liste SNCF 87784629 à 6 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87784629 (point géocodé) à 654 m',
    ],
    // 110. Le Molay-Littry (id 16262)
    [
        'station' => ['name' => 'Le Molay-Littry', 'uic' => '87444083'],
        'action' => 'position',
        'lng' => -0.8815611,
        'lat' => 49.249198,
        'source_url' => $wp('Gare du Molay-Littry', 230463410),
        'quote' => 'Gare du Molay-Littry : latitude = 49.249223, longitude = -0.881640',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare du Molay-Littry » (oldid 230463410) à 6 m, Wikidata Q3098213 à 6 m, OSM n4966009012 à 0 m, liste SNCF 87444083 à 45 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87444083 (point géocodé) à 643 m',
    ],
    // 111. Louvroil (id 15686)
    [
        'station' => ['name' => 'Louvroil', 'uic' => '87295535'],
        'action' => 'position',
        'lng' => 3.9534722,
        'lat' => 50.2684622,
        'source_url' => $wp('Gare de Louvroil', 230178885),
        'quote' => 'Gare de Louvroil : latitude = 50.268263, longitude = 3.953404',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Louvroil » (oldid 230178885) à 23 m, OSM n497011328 à 0 m, liste SNCF 87295535 à 15 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87295535 (point géocodé) à 155 m, Wikidata Q2286058 (P625) à 780 m',
    ],
    // 112. Vinça (id 18256)
    [
        'station' => ['name' => 'Vinça', 'uic' => '87784611'],
        'action' => 'position',
        'lng' => 2.5276073,
        'lat' => 42.6481528,
        'source_url' => $wp('Gare de Vinça', 238993360),
        'quote' => 'Gare de Vinça : latitude = 42.648107, longitude = 2.527661',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Vinça » (oldid 238993360) à 7 m, Wikidata Q3098022 à 7 m, BD TOPO EQ_RESEA0000000038468418 à 6 m, OSM n9737457187 à 0 m, liste SNCF 87784611 à 2 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87784611 (point géocodé) à 619 m',
    ],
    // 113. Audrieu (id 16260)
    [
        'station' => ['name' => 'Audrieu', 'uic' => '87444059'],
        'action' => 'position',
        'lng' => -0.5960849,
        'lat' => 49.2162658,
        'source_url' => $wp('Gare d\'Audrieu', 222034733),
        'quote' => 'Gare d\'Audrieu : latitude = 49.216110, longitude = -0.595833',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Audrieu » (oldid 222034733) à 25 m, Wikidata Q2353104 à 36 m, BD TOPO EQ_RESEA0000000013905718 à 27 m, OSM n3467066693 à 0 m, liste SNCF 87444059 à 16 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87444059 (point géocodé) à 617 m',
    ],
    // 114. Bayeux (id 16261)
    [
        'station' => ['name' => 'Bayeux', 'uic' => '87444067'],
        'action' => 'position',
        'lng' => -0.6981168,
        'lat' => 49.269709,
        'source_url' => $wp('Gare de Bayeux', 231517308),
        'quote' => 'Gare de Bayeux : latitude = 49.269599, longitude = -0.697923',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Bayeux » (oldid 231517308) à 19 m, Wikidata Q2189086 à 5 m, BD TOPO EQ_RESEA0000000230021759 à 2 m, OSM n5598395305 à 18 m, liste SNCF 87444067 à 15 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 18 m ; à signaler à la source : liste SNCF UIC 87444067 (point géocodé) à 615 m',
    ],
    // 115. Jacques-Monod-La Demi-Lieue (id 16229)
    [
        'station' => ['name' => 'Jacques-Monod-La Demi-Lieue', 'uic' => '87413716'],
        'action' => 'position',
        'lng' => 0.1860692,
        'lat' => 49.5269739,
        'source_url' => $wp('Gare de Jacques-Monod-la-Demi-Lieue', 239757209),
        'quote' => 'Gare de Jacques-Monod-la-Demi-Lieue : latitude = 49.526984, longitude = 0.186118',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Jacques-Monod-la-Demi-Lieue » (oldid 239757209) à 4 m, OSM n13746107567 à 0 m, liste SNCF 87413716 à 17 m ; à 2 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 17 m ; à signaler à la source : Wikidata Q24733 (P625) à 629 m',
    ],
    // 116. Auneau (id 16683)
    [
        'station' => ['name' => 'Auneau', 'uic' => '87545657'],
        'action' => 'position',
        'lng' => 1.7800726,
        'lat' => 48.4458848,
        'source_url' => $wp('Gare d\'Auneau', 224032507),
        'quote' => 'Gare d\'Auneau : latitude = 48.446136, longitude = 1.779880',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Auneau » (oldid 224032507) à 31 m, Wikidata Q3095696 à 31 m, BD TOPO EQ_RESEA0000000015531735 à 29 m, OSM n11014390349 à 0 m, liste SNCF 87545657 à 10 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87545657 (point géocodé) à 588 m',
    ],
    // 117. Bretteville-Norrey (id 16259)
    [
        'station' => ['name' => 'Bretteville-Norrey', 'uic' => '87444042'],
        'action' => 'position',
        'lng' => -0.5127472,
        'lat' => 49.2034044,
        'source_url' => $osm('n2015018013'),
        'quote' => 'Bretteville-Norrey (osm) : lat = 49.2034044, lon = -0.5127472',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : BD TOPO EQ_RESEA0000000093934171 à 4 m, OSM n2015018013 à 0 m, liste SNCF 87444042 à 1 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87444042 (point géocodé) à 598 m',
    ],
    // 118. Rixheim (id 15193)
    [
        'station' => ['name' => 'Rixheim', 'uic' => '87182071'],
        'action' => 'position',
        'lng' => 7.4063103,
        'lat' => 47.7488047,
        'source_url' => $wp('Gare de Rixheim', 241597928),
        'quote' => 'Gare de Rixheim : latitude = 47.748745, longitude = 7.406178',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Rixheim » (oldid 241597928) à 12 m, Wikidata Q3097424 à 12 m, BD TOPO EQ_RESEA0000000243551220 à 30 m, OSM n8040307079 à 0 m, liste SNCF 87182071 à 19 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87182071 (point géocodé) à 575 m',
    ],
    // 119. St-Valéry-Canal (id 19026)
    [
        'station' => ['name' => 'St-Valéry-Canal', 'uic' => '87317420'],
        'action' => 'position',
        'lng' => 1.6512097,
        'lat' => 50.1768039,
        'source_url' => $wp('Gare de Saint-Valery-Canal', 214492738),
        'quote' => 'Gare de Saint-Valery-Canal : latitude = 50.176763, longitude = 1.651292',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Valery-Canal » (oldid 214492738) à 7 m, Wikidata Q3097629 à 6 m, OSM n624294567 à 0 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87317420 (point géocodé) à 564 m',
    ],
    // 120. Sallaumines (id 15934)
    [
        'station' => ['name' => 'Sallaumines', 'uic' => '87345215'],
        'action' => 'position',
        'lng' => 2.8556244,
        'lat' => 50.4223061,
        'source_url' => $wp('Gare de Sallaumines', 238683013),
        'quote' => 'Gare de Sallaumines : latitude = 50.422294, longitude = 2.855602',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Sallaumines » (oldid 238683013) à 2 m, Wikidata Q3097654 à 2 m, OSM n2275683991 à 0 m, liste SNCF 87345215 à 4 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87345215 (point géocodé) à 551 m',
    ],
    // 121. La Valbonne (id 17704)
    [
        'station' => ['name' => 'La Valbonne', 'uic' => '87723577'],
        'action' => 'position',
        'lng' => 5.1250781,
        'lat' => 45.8491508,
        'source_url' => $osm('n12696444032'),
        'quote' => 'La Valbonne (osm) : lat = 45.8491508, lon = 5.1250781',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : OSM n12696444032 à 0 m, liste SNCF 87723577 à 62 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 62 m ; à signaler à la source : Wikidata Q2104220 (P625) à 492 m, coordonnées de l\'article « Gare de La Valbonne » à 478 m',
    ],
    // 122. Pierrefitte-Stains (id 15464)
    [
        'station' => ['name' => 'Pierrefitte-Stains', 'uic' => '87271163'],
        'action' => 'position',
        'lng' => 2.3721153,
        'lat' => 48.9640182,
        'source_url' => $wp('Gare de Pierrefitte - Stains', 241499178),
        'quote' => 'Gare de Pierrefitte - Stains : latitude = 48.963873, longitude = 2.372285',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Pierrefitte - Stains » (oldid 241499178) à 20 m, Wikidata Q2260729 à 47 m, OSM w81186848 à 0 m, liste SNCF 87271163 à 20 m ; à 7 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 20 m ; à signaler à la source : OSM n3580040424 à 319 m, OSM w539918270 à 546 m',
    ],
    // 123. Demi-Lune (la Pomme) (id 22212)
    [
        'station' => ['name' => 'Demi-Lune (la Pomme)', 'osm' => 'n4268976565'],
        'action' => 'position',
        'lng' => 4.7793351,
        'lat' => 45.7597277,
        'source_url' => $osm('n4268976565'),
        'quote' => 'Ancienne gare de La Demi-Lune (la Pomme) (osm) : lat = 45.7597277, lon = 4.7793351',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : OSM n4268976565 à 0 m, OHM n2136332778 à 10 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : OSM n13366977630 à 418 m, BD TOPO EQ_RESEA0000000013173283 à 666 m',
    ],
    // 124. Beuvry (Pas-de-Calais) (id 15898)
    [
        'station' => ['name' => 'Beuvry (Pas-de-Calais)', 'uic' => '87342402'],
        'action' => 'position',
        'lng' => 2.6927634,
        'lat' => 50.5355169,
        'source_url' => $wp('Gare de Beuvry (Pas-de-Calais)', 238682907),
        'quote' => 'Gare de Beuvry (Pas-de-Calais) : latitude = 50.535775, longitude = 2.691692',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Beuvry (Pas-de-Calais) » (oldid 238682907) à 81 m, Wikidata Q3095981 à 81 m, OSM n2275685055 à 0 m, liste SNCF 87342402 à 27 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m',
    ],
    // 125. Sermaise (id 16681)
    [
        'station' => ['name' => 'Sermaise', 'uic' => '87545517'],
        'action' => 'position',
        'lng' => 2.0688306,
        'lat' => 48.5354133,
        'source_url' => $wp('Gare de Sermaise', 237812932),
        'quote' => 'Gare de Sermaise : latitude = 48.535418, longitude = 2.068843',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Sermaise » (oldid 237812932) à 1 m, BD TOPO EQ_RESEA0000000003089919 à 5 m, OSM n6094930528 à 11 m, liste SNCF 87545517 à 11 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 11 m ; à signaler à la source : Wikidata Q2390290 (P625) à 510 m',
    ],
    // 126. Meudon (id 16080)
    [
        'station' => ['name' => 'Meudon', 'uic' => '87393108'],
        'action' => 'position',
        'lng' => 2.2409131,
        'lat' => 48.8149007,
        'source_url' => $wp('Gare de Meudon', 222335141),
        'quote' => 'Gare de Meudon : latitude = 48.814915, longitude = 2.240945',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Meudon » (oldid 222335141) à 3 m, Wikidata Q2848177 à 3 m, BD TOPO EQ_RESEA0000000000389062 à 1 m, OSM n4325154503 à 20 m, liste SNCF 87393108 à 11 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 11 m',
    ],
    // 127. Labège-Innopole (id 17162)
    [
        'station' => ['name' => 'Labège-Innopole', 'uic' => '87612002'],
        'action' => 'position',
        'lng' => 1.5128101,
        'lat' => 43.5474999,
        'source_url' => $osm('n6132447177'),
        'quote' => 'Labège-Innopole (osm) : lat = 43.5474999, lon = 1.5128101',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : Wikidata Q3096827 à 9 m, OSM n6132447177 à 0 m, liste SNCF 87612002 à 21 m ; à 1 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87612002 (point géocodé) à 471 m',
    ],
    // 128. L'Arinella (id 20740)
    [
        'station' => ['name' => 'L\'Arinella', 'uic' => '87762328'],
        'action' => 'position',
        'lng' => 9.4439485,
        'lat' => 42.6707068,
        'source_url' => $wp('Gare de Sole-Meo', 239579671),
        'quote' => 'Gare de Sole-Meo : latitude = 42.670748, longitude = 9.443953',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Sole-Meo » (oldid 239579671) à 5 m, Wikidata Q3097756 à 5 m, liste SNCF 87762328 à 9 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 9 m ; à signaler à la source : OSM n339833263 à 268 m',
    ],
    // 129. Étrembières-Salève (id 20567)
    [
        'station' => ['name' => 'Étrembières-Salève', 'uic' => '87745489'],
        'action' => 'position',
        'lng' => 6.228611,
        'lat' => 46.1823496,
        'source_url' => $osm('n6138246607'),
        'quote' => 'Étrembières (osm) : lat = 46.1823496, lon = 6.2286110',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : OSM n6138246607 à 0 m, liste SNCF 87745489 à 8 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 8 m ; à signaler à la source : OSM n5858559820 à 472 m',
    ],
    // 130. Chauny (id 15720)
    [
        'station' => ['name' => 'Chauny', 'uic' => '87296616'],
        'action' => 'position',
        'lng' => 3.2236417,
        'lat' => 49.6111381,
        'source_url' => $wp('Gare de Chauny', 241488757),
        'quote' => 'Gare de Chauny : latitude = 49.611148, longitude = 3.223532',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Chauny » (oldid 241488757) à 8 m, Wikidata Q2184682 à 8 m, BD TOPO EQ_RESEA0000000063660550 à 9 m, OSM w142993913 à 0 m, liste SNCF 87296616 à 9 m ; à 18 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87296616 (point géocodé) à 470 m',
    ],
    // 131. Langres (id 15006)
    [
        'station' => ['name' => 'Langres', 'uic' => '87142091'],
        'action' => 'position',
        'lng' => 5.3444225,
        'lat' => 47.8769645,
        'source_url' => $wp('Gare de Langres', 241449894),
        'quote' => 'Gare de Langres : latitude = 47.877076, longitude = 5.344605',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Langres » (oldid 241449894) à 18 m, Wikidata Q2268586 à 18 m, BD TOPO EQ_RESEA0000000066666753 à 9 m, OSM n2516923028 à 0 m, liste SNCF 87142091 à 2 m ; à 6 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87142091 (point géocodé) à 463 m',
    ],
    // 132. Castelnau-d'Estrétefonds (id 17140)
    [
        'station' => ['name' => 'Castelnau-d\'Estrétefonds', 'uic' => '87611665'],
        'action' => 'position',
        'lng' => 1.3416175,
        'lat' => 43.7861011,
        'source_url' => $wp('Gare de Castelnau-d\'Estrétefonds', 235031180),
        'quote' => 'Gare de Castelnau-d\'Estrétefonds : latitude = 43.786155, longitude = 1.341725',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Castelnau-d\'Estrétefonds » (oldid 235031180) à 11 m, Wikidata Q2204610 à 40 m, BD TOPO EQ_RESEA0000000073015809 à 12 m, OSM n8334192489 à 0 m, liste SNCF 87611665 à 16 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87611665 (point géocodé) à 425 m',
    ],
    // 133. Montaudran (id 17163)
    [
        'station' => ['name' => 'Montaudran', 'uic' => '87612010'],
        'action' => 'position',
        'lng' => 1.4800914,
        'lat' => 43.5736441,
        'source_url' => $wp('Gare de Toulouse-Montaudran', 241315438),
        'quote' => 'Gare de Toulouse-Montaudran : latitude = 43.573752, longitude = 1.479944',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Toulouse-Montaudran » (oldid 241315438) à 17 m, OSM n3474055993 à 0 m, liste SNCF 87612010 à 35 m ; à 5 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87612010 (point géocodé) à 427 m',
    ],
    // 134. GR 20 Dolce-Vita (id 18793)
    [
        'station' => ['name' => 'GR 20 Dolce-Vita', 'uic' => '87242453'],
        'action' => 'position',
        'lng' => 8.7926998,
        'lat' => 42.5561016,
        'source_url' => $wp('Gare de Dolce Vita GR 20 (Calvi)', 229548175),
        'quote' => 'Gare de Dolce Vita GR 20 (Calvi) : latitude = 42.556126, longitude = 8.792727',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Dolce Vita GR 20 (Calvi) » (oldid 229548175) à 4 m, Wikidata Q3096373 à 4 m, OSM n1071697178 à 0 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87242453 (point géocodé) à 461 m',
    ],
    // 135. Béziers (id 18195)
    [
        'station' => ['name' => 'Béziers', 'uic' => '87781005'],
        'action' => 'position',
        'lng' => 3.2188947,
        'lat' => 43.3362879,
        'source_url' => $wp('Gare de Béziers', 239949498),
        'quote' => 'Gare de Béziers : latitude = 43.336108, longitude = 3.218669',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Béziers » (oldid 239949498) à 27 m, Wikidata Q2801649 à 27 m, BD TOPO EQ_RESEA0000000044347552 à 7 m, OSM n10933961551 à 23 m, liste SNCF 87781005 à 5 m ; à 16 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 23 m ; à signaler à la source : liste SNCF UIC 87781005 (point géocodé) à 368 m',
    ],
    // 136. La Bâthie (id 17855)
    [
        'station' => ['name' => 'La Bâthie', 'uic' => '87741678'],
        'action' => 'position',
        'lng' => 6.4452457,
        'lat' => 45.6302923,
        'source_url' => $osm('w223051272'),
        'quote' => 'La Bâthie (osm) : lat = 45.6302923, lon = 6.4452457',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : OSM w223051272 à 0 m, liste SNCF 87741678 à 18 m ; à 5 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 18 m ; à signaler à la source : Wikidata Q57468981 (P625) à 442 m, coordonnées de l\'article « Gare de La Bâthie » à 442 m',
    ],
    // 137. Chignin-les-Marches (id 20488)
    [
        'station' => ['name' => 'Chignin-les-Marches', 'uic' => '87741173'],
        'action' => 'position',
        'lng' => 6.003431,
        'lat' => 45.5160966,
        'source_url' => $wp('Gare de Chignin - Les Marches', 240674166),
        'quote' => 'Gare de Chignin - Les Marches : latitude = 45.515956, longitude = 6.003831',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Chignin - Les Marches » (oldid 240674166) à 35 m, liste SNCF 87741173 à 35 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 35 m ; à signaler à la source : Wikidata Q47095408 (P625) à 434 m',
    ],
    // 138. St-Genouph (id 16731)
    [
        'station' => ['name' => 'St-Genouph', 'uic' => '87571851'],
        'action' => 'position',
        'lng' => 0.5969637,
        'lat' => 47.3709212,
        'source_url' => $osm('n11611830640'),
        'quote' => 'Saint-Genouph (osm) : lat = 47.3709212, lon = 0.5969637',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : BD TOPO EQ_RESEA0000000031057615 à 54 m, OSM n11611830640 à 0 m, liste SNCF 87571851 à 1 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 1 m ; à signaler à la source : Wikidata Q3097524 (P625) à 449 m, coordonnées de l\'article « Gare de Saint-Genouph » à 449 m',
    ],
    // 139. Ponte-Leccia (id 20721)
    [
        'station' => ['name' => 'Ponte-Leccia', 'uic' => '87762112'],
        'action' => 'position',
        'lng' => 9.2039301,
        'lat' => 42.4654468,
        'source_url' => $wp('Gare de Ponte-Leccia', 229547544),
        'quote' => 'Gare de Ponte-Leccia : latitude = 42.465336, longitude = 9.203887',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Ponte-Leccia » (oldid 229547544) à 13 m, Wikidata Q3097316 à 13 m, BD TOPO EQ_RESEA0000000109751140 à 13 m, OSM n304724561 à 0 m ; à 4 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87762112 (point géocodé) à 437 m',
    ],
    // 140. St-Affrique (id 20898)
    [
        'station' => ['name' => 'St-Affrique', 'uic' => '87783480'],
        'action' => 'position',
        'lng' => 2.8914911,
        'lat' => 43.9543613,
        'source_url' => $wp('Gare de Saint-Affrique', 238467420),
        'quote' => 'Gare de Saint-Affrique : latitude = 43.954392, longitude = 2.891489',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Saint-Affrique » (oldid 238467420) à 3 m, Wikidata Q140783756 à 7 m, OSM w66298039 à 0 m ; à 10 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87783480 (point géocodé) à 444 m',
    ],
    // 141. Le Méridien (id 17648)
    [
        'station' => ['name' => 'Le Méridien', 'uic' => '87721522'],
        'action' => 'position',
        'lng' => 4.7484432,
        'lat' => 45.7684995,
        'source_url' => $wp('Gare du Méridien', 241156052),
        'quote' => 'Gare du Méridien : latitude = 45.768499, longitude = 4.748443',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare du Méridien » (oldid 241156052) à 0 m, OSM w321447107 à 0 m, liste SNCF 87721522 à 48 m, OHM n2136000299 à 19 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 48 m ; à signaler à la source : Wikidata Q2672930 (P625) à 498 m',
    ],
    // 142. Labège-Village (id 17164)
    [
        'station' => ['name' => 'Labège-Village', 'uic' => '87612028'],
        'action' => 'position',
        'lng' => 1.5328211,
        'lat' => 43.5305699,
        'source_url' => $wp('Halte de Labège-Village', 230132231),
        'quote' => 'Halte de Labège-Village : latitude = 43.530601, longitude = 1.532784',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Halte de Labège-Village » (oldid 230132231) à 5 m, Wikidata Q3126240 à 5 m, OSM n6131099113 à 0 m, liste SNCF 87612028 à 9 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : liste SNCF UIC 87612028 (point géocodé) à 435 m',
    ],
    // 143. Rosa Parks (id 17273)
    [
        'station' => ['name' => 'Rosa Parks', 'uic' => '87654798'],
        'action' => 'position',
        'lng' => 2.3741066,
        'lat' => 48.8962224,
        'source_url' => $wp('Gare Rosa-Parks', 241524861),
        'quote' => 'Gare Rosa-Parks : latitude = 48.896466, longitude = 2.374100',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare Rosa-Parks » (oldid 241524861) à 27 m, Wikidata Q3095535 à 27 m, BD TOPO EQ_RESEA0000000296796753 à 26 m, OSM n3884781096 à 30 m, liste SNCF 87654798 à 25 m ; à 9 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 30 m ; à signaler à la source : liste SNCF UIC 87654798 (point géocodé) à 423 m',
    ],
    // 144. Puyssegur (id 21804)
    [
        'station' => ['name' => 'Puyssegur', 'wikidata' => 'Q60847796'],
        'action' => 'position',
        'lng' => 1.0863598,
        'lat' => 43.7404333,
        'source_url' => $wp('Gare de Puysségur', 188279933),
        'quote' => 'Gare de Puysségur : latitude = 43.740360, longitude = 1.086420',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Puysségur » (oldid 188279933) à 9 m, OSM w1391766361 à 0 m ; à 10 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : Wikidata Q60847796 (P625) à 437 m',
    ],
    // 145. Montaigut (id 21203)
    [
        'station' => ['name' => 'Montaigut', 'wikidata' => 'Q60847730'],
        'action' => 'position',
        'lng' => 1.2310826,
        'lat' => 43.6932283,
        'source_url' => $wp('Gare de Montaigut (Haute-Garonne)', 205932339),
        'quote' => 'Gare de Montaigut (Haute-Garonne) : latitude = 43.693239, longitude = 1.231121',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Montaigut (Haute-Garonne) » (oldid 205932339) à 3 m, OSM w1531638096 à 0 m ; à 10 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : Wikidata Q60847730 (P625) à 437 m',
    ],
    // 146. Cadours (id 21239)
    [
        'station' => ['name' => 'Cadours', 'wikidata' => 'Q60847631'],
        'action' => 'position',
        'lng' => 1.053202,
        'lat' => 43.7299091,
        'source_url' => $wp('Gare de Cadours', 220006537),
        'quote' => 'Gare de Cadours : latitude = 43.729920, longitude = 1.053210',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Cadours » (oldid 220006537) à 1 m, OSM w72039088 à 0 m ; à 7 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : Wikidata Q60847631 (P625) à 435 m',
    ],
    // 147. Mondonville (id 21566)
    [
        'station' => ['name' => 'Mondonville', 'wikidata' => 'Q60847696'],
        'action' => 'position',
        'lng' => 1.2836945,
        'lat' => 43.6709898,
        'source_url' => $wp('Gare de Mondonville', 239737173),
        'quote' => 'Gare de Mondonville : latitude = 43.671000, longitude = 1.283670',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Mondonville » (oldid 239737173) à 2 m, OSM w87248997 à 0 m ; à 11 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 0 m ; à signaler à la source : Wikidata Q60847696 (P625) à 433 m',
    ],
    // 148. Escalquens (id 17144)
    [
        'station' => ['name' => 'Escalquens', 'uic' => '87611707'],
        'action' => 'position',
        'lng' => 1.5424088,
        'lat' => 43.5171276,
        'source_url' => $wp('Gare d\'Escalquens', 240892378),
        'quote' => 'Gare d\'Escalquens : latitude = 43.517094, longitude = 1.542265',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare d\'Escalquens » (oldid 240892378) à 12 m, Wikidata Q3095745 à 12 m, BD TOPO EQ_RESEA0000000328806209 à 1 m, OSM n6131085779 à 12 m, liste SNCF 87611707 à 11 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (osm) à 12 m ; à signaler à la source : liste SNCF UIC 87611707 (point géocodé) à 433 m',
    ],
    // 149. Lamonzie-St-Martin (id 16909)
    [
        'station' => ['name' => 'Lamonzie-St-Martin', 'uic' => '87584243'],
        'action' => 'position',
        'lng' => 0.3802809,
        'lat' => 44.8434582,
        'source_url' => $wp('Gare de Lamonzie-Saint-Martin', 223772849),
        'quote' => 'Gare de Lamonzie-Saint-Martin : latitude = 44.843427, longitude = 0.380301',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Lamonzie-Saint-Martin » (oldid 223772849) à 4 m, Wikidata Q3096840 à 4 m, OSM n3435151446 à 0 m, liste SNCF 87584243 à 17 m ; à 0 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 17 m ; à signaler à la source : liste SNCF UIC 87584243 (point géocodé) à 444 m',
    ],
    // 150. Nurieux (id 17876)
    [
        'station' => ['name' => 'Nurieux', 'uic' => '87743351'],
        'action' => 'position',
        'lng' => 5.5327024,
        'lat' => 46.1842257,
        'source_url' => $wp('Gare de Nurieux', 241398998),
        'quote' => 'Gare de Nurieux : latitude = 46.184106, longitude = 5.532671',
        'reason' => 'bâtiment voyageurs ou milieu des quais (règle de placement VFF) ; sources concordantes à moins de 70 m : article Wikipédia « Gare de Nurieux » (oldid 241398998) à 14 m, BD TOPO EQ_RESEA0000000008682113 à 40 m, OSM n2494019283 à 4 m, liste SNCF 87743351 à 59 m ; à 3 m de la voie du tracé (source concordante la plus proche) ; point VFF actuel (sncf) à 59 m ; à signaler à la source : Wikidata Q2945500 (P625) à 373 m',
    ],

];
