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
            'source_url' => $wp($g[2], $g[3]),
            'quote' => $g[8],
            'reason' => $g[7] === 'before'
                ? 'fermeture de la ligne selon l\'infobox : fin du fret de la gare au plus tard à cette date'
                : 'fin du fret, donc de toute desserte, selon l\'infobox de l\'article de la gare',
        ],
    ], [
        // gare, Wikidata, article, révision, fin des voyageurs, précision, fin du fret, précision, champ cité,
        // fermetures retirées [source, type, date]
        ['Angoville-sur-Ay', 'Q3095636', 'Gare d\'Angoville-sur-Ay', 237264528, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
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
        ['Lessay', 'Q1966097', 'Gare de Lessay', 231943184, '1970-01-01', 'year', '1988-01-24', 'day', 'Fermeture : voyageurs : 1970 marchandises : 24 janvier 1988', [['wikidata', 'closure', '1970-01-01']]],
        ['Lincel - Saint-Martin', 'Q3096890', 'Gare de Lincel - Saint-Martin', 206958157, '1934-01-15', 'day', '1956-01-01', 'year', 'Fermeture : 15 janvier 1934 (voyageurs) 1956 (marchandises)', [['wikidata', 'closure', '1934-01-01']]],
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
];
