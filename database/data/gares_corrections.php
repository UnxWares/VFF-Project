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
];
