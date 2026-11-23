<?php

// Contexte sourcé de la collision du tunnel de Livernant du 20 mai 2009 (recherche du 22/11/2026).
// Fusion du 22/11/2026 : la fiche « collision à Boisné-La Tude » issue de la liste de Wikipédia (lieu : Charmant) décrit le même accident.
return [
    'slug' => '2009-05-20-livernant',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-2000' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 2000',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '20 mai 2009',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_2000&oldid=241007547',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée « Charmant (Charente) » qui cite le rapport d\'enquête du BEA-TT ; Charmant est depuis 2016 une commune déléguée de Boisné-La Tude.',
        ],
        'bea-tt-r119' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Tunnel de Livernant (16) - 20/05/2009 - collision entre un train de fret et le chargement d\'un train croiseur',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'Enquête technique r119',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/tunnel-de-livernant-r119.html?lang=fr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête : le bras articulé d\'un engin forestier chargé sur un train d\'Euro Cargo Rail heurte un train croiseur de la SNCF dans le tunnel de Livernant, à Charmant (Charente) ; conducteur légèrement blessé, locomotive en partie détruite.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le mercredi 20 mai 2009, veille du pont de l\'Ascension, dans le tunnel de Livernant à Charmant (Charente), sur la ligne de Paris à Bordeaux, un engin forestier mal arrimé sur un train de fret d\'Euro Cargo Rail a heurté la locomotive d\'un train de la SNCF qu\'il croisait et l\'a éventrée, d\'après la liste de Wikipédia ; le BEA-TT parle du bras articulé de l\'engin forestier et d\'un « train croiseur ».',
                'en' => 'On Wednesday 20 May 2009, the eve of the Ascension holiday, in the Livernant tunnel at Charmant (Charente), on the Paris to Bordeaux line, a badly secured forestry machine on a Euro Cargo Rail freight train struck the locomotive of an SNCF train passing in the other direction and ripped it open, according to the Wikipedia list; the BEA-TT speaks of the articulated arm of the machine and of a "passing train".',
                'de' => 'Am Mittwoch, dem 20. Mai 2009, am Vorabend von Christi Himmelfahrt, traf im Tunnel von Livernant bei Charmant (Charente) an der Strecke Paris - Bordeaux eine schlecht gesicherte Forstmaschine auf einem Güterzug von Euro Cargo Rail die Lokomotive eines entgegenkommenden Zugs der SNCF und riss sie auf, so die Wikipedia-Liste; die BEA-TT spricht vom Gelenkarm der Forstmaschine und von einem „Begegnungszug“.',
                'sources' => ['wp-liste-2000', 'bea-tt-r119'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur de la locomotive heurtée a été blessé au poignet. Le trafic voyageurs et fret a été fortement perturbé les jours suivants, le temps de dégager le matériel et de remettre les installations en état.',
                'en' => 'The driver of the struck locomotive was injured in the wrist. Passenger and freight traffic was heavily disrupted over the following days while the rolling stock was cleared and the installations repaired.',
                'de' => 'Der Lokführer der getroffenen Lokomotive wurde am Handgelenk verletzt. Personen- und Güterverkehr waren in den folgenden Tagen stark gestört, bis die Fahrzeuge geräumt und die Anlagen instand gesetzt waren.',
                'sources' => ['wp-liste-2000', 'bea-tt-r119'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '16082',
            'sources' => ['bea-tt-r119', 'wp-liste-2000'],
            'fr' => 'Le BEA-TT situe l\'accident dans le tunnel de Livernant « à Charmant (Charente) », que l\'import n\'avait pas su placer ; la liste de Wikipédia dit de même, et Charmant est aujourd\'hui commune déléguée de Boisné-La Tude.',
            'en' => 'The BEA-TT places the accident in the Livernant tunnel "at Charmant (Charente)", which the import could not place; the Wikipedia list agrees, and Charmant is now a delegated commune of Boisné-La Tude.',
            'de' => 'Die BEA-TT verortet den Unfall im Tunnel von Livernant „in Charmant (Charente)“, den der Import nicht verorten konnte; die Wikipedia-Liste stimmt überein, und Charmant ist heute Ortsteil (commune déléguée) von Boisné-La Tude.',
        ],
    ],
    'aliases' => [
        [
            'slug' => '2009-05-20-boisne-la-tude',
            'sources' => ['wp-liste-2000', 'bea-tt-r119'],
            'fr' => 'La fiche « collision à Boisné-La Tude » du 20 mai 2009 venait de la liste de Wikipédia (lieu : Charmant) : elle décrit la collision de deux trains dans le tunnel de Livernant, sur laquelle le BEA-TT a enquêté.',
            'en' => 'The "collision at Boisné-La Tude" record of 20 May 2009 came from the Wikipedia list (place: Charmant): it describes the collision of two trains in the Livernant tunnel, investigated by the BEA-TT.',
            'de' => 'Der Datensatz „Zusammenstoß in Boisné-La Tude“ vom 20. Mai 2009 stammte aus der Wikipedia-Liste (Ort: Charmant): Er beschreibt den Zusammenstoß zweier Züge im Tunnel von Livernant, den die BEA-TT untersucht hat.',
        ],
    ],
];
