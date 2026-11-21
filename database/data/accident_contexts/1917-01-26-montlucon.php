<?php

// Contexte sourcé de l'accident du 26 janvier 1917 sur la ligne de Bourges à Montluçon (recherche du 21/11/2026).
return [
    'slug' => '1917-01-26-montlucon',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1917' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1917',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Janvier, 26 janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1917&oldid=235357718#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée sourcée (Le Matin du 27 janvier 1917, Le Figaro du 29 janvier, sur Gallica, non lus) ; place l\'accident près de la gare de Châteauneuf-sur-Cher, pas à Montluçon.',
        ],
        'francearchives-accidents' => [
            'provider' => 'francearchives',
            'kind' => 'archive',
            'reliability' => 'official',
            'title' => 'Accidents : déraillements (train de Paris - La Bourboule en gare de La Celle-Bruère, le 15 août 1905 ; express venant de Montluçon, à Châteauneuf-sur-Cher, le 26 janvier 1917 ; ...)',
            'publisher' => 'FranceArchives',
            'author' => null,
            'published' => null,
            'locator' => 'notice de composant d\'inventaire',
            'url' => 'https://francearchives.gouv.fr/facomponent/1a805630fd0c271ee3d0242b4deaef8eaf3d4cb6',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Intitulé d\'un dossier d\'archives repéré par une recherche : il range l\'accident parmi les déraillements, à Châteauneuf-sur-Cher ; la notice n\'a pas pu être ouverte (fonds et cote non relevés).',
        ],
        'gallica-matin-1917-01-27' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 27 janvier 1917, pages 2 et 3',
            'publisher' => 'Le Matin (Gallica)',
            'author' => null,
            'published' => '1917-01-27',
            'locator' => 'p. 2-3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k571893p/f3.image.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Compte rendu contemporain cité par la liste de Wikipédia ; Gallica non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 26 janvier 1917, vers 2 heures du matin, sur la ligne de Bourges à Montluçon, près de la gare de Châteauneuf-sur-Cher (Cher), le train de voyageurs Montluçon - Paris a percuté un train de ballast.',
                'en' => 'On 26 January 1917, at about 2 am, on the Bourges to Montluçon line near Châteauneuf-sur-Cher station (Cher), the Montluçon - Paris passenger train ran into a ballast train.',
                'de' => 'Am 26. Januar 1917 prallte gegen 2 Uhr nachts auf der Strecke Bourges-Montluçon nahe dem Bahnhof Châteauneuf-sur-Cher (Cher) der Personenzug Montluçon - Paris auf einen Schotterzug.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive et dix voitures du train de voyageurs ont déraillé ; trois de ces voitures ont été complètement disloquées.',
                'en' => 'The locomotive and ten coaches of the passenger train derailed; three of these coaches were completely wrecked.',
                'de' => 'Die Lokomotive und zehn Wagen des Personenzugs entgleisten; drei dieser Wagen wurden völlig zertrümmert.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Des trois voitures disloquées, les secours ont tiré onze morts et cinquante-huit blessés. La liste de Wikipédia ajoute que deux blessés sont morts ensuite, ce qui ferait 13 morts au total si les deux ne figurent pas déjà parmi les onze.',
                'en' => 'From the three wrecked coaches, rescuers recovered eleven dead and fifty-eight injured. The Wikipedia list adds that two of the injured died afterwards, which would make 13 dead in all if those two are not already among the eleven.',
                'de' => 'Aus den drei zertrümmerten Wagen bargen die Helfer elf Tote und achtundfünfzig Verletzte. Die Wikipedia-Liste fügt hinzu, zwei Verletzte seien später gestorben, was insgesamt 13 Tote ergäbe, falls diese beiden nicht schon unter den elf sind.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Lieu : la fiche place l\'accident à Montluçon (Allier), mais la liste de Wikipédia le situe près de la gare de Châteauneuf-sur-Cher, dans le Cher, sur la ligne de Bourges à Montluçon ; l\'intitulé d\'un dossier d\'archives repéré sur FranceArchives (non lu) désigne lui aussi Châteauneuf-sur-Cher pour un express venant de Montluçon le 26 janvier 1917. Un seul document lu appuyant ce lieu, la fiche n\'est pas corrigée.',
                'en' => 'Place: the record puts the accident at Montluçon (Allier), but the Wikipedia list places it near Châteauneuf-sur-Cher station, in the Cher département, on the Bourges to Montluçon line; the title of an archive file found on FranceArchives (not read) also names Châteauneuf-sur-Cher for an express coming from Montluçon on 26 January 1917. With only one document read supporting this place, the record is not corrected.',
                'de' => 'Ort: Der Datensatz verortet den Unfall in Montluçon (Allier), die Wikipedia-Liste jedoch nahe dem Bahnhof Châteauneuf-sur-Cher im Département Cher auf der Strecke Bourges-Montluçon; auch der Titel einer auf FranceArchives gefundenen Archivakte (nicht gelesen) nennt Châteauneuf-sur-Cher für einen Schnellzug aus Montluçon am 26. Januar 1917. Da nur ein gelesenes Dokument diesen Ort stützt, wird der Datensatz nicht korrigiert.',
                'sources' => ['wp-liste-1917'],
            ],
            [
                'fr' => 'Nature : la fiche indique un déraillement. La liste de Wikipédia décrit un choc contre un train de ballast suivi du déraillement du train de voyageurs ; l\'intitulé d\'archives classe l\'accident parmi les déraillements. La cause du choc n\'est pas documentée.',
                'en' => 'Nature: the record states a derailment. The Wikipedia list describes a collision with a ballast train followed by the derailment of the passenger train; the archive title files the accident among derailments. The cause of the collision is not documented.',
                'de' => 'Art: Der Datensatz nennt eine Entgleisung. Die Wikipedia-Liste beschreibt einen Zusammenstoß mit einem Schotterzug, auf den die Entgleisung des Personenzugs folgte; der Archivtitel ordnet den Unfall unter den Entgleisungen ein. Die Ursache des Zusammenstoßes ist nicht dokumentiert.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
    ],
    'corrections' => [],
];
