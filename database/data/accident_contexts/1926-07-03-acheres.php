<?php

// Contexte sourcé de l'accident d'Achères du 3 juillet 1926 (recherche du 21/11/2026).
return [
    'slug' => '1926-07-03-acheres',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1926' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1926',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '3 juillet, Achères',
            'url' => 'https://fr.wikipedia.org/wiki/Liste_des_accidents_ferroviaires_en_France_en_1926',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Heure, lieu, circonstances, 18 morts et 67 blessés ; cite Le Petit Journal du 5 juillet 1926 et l\'analyse de F. Maison (Annales des Mines, 1928).',
        ],
        'wp-3-juillet' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => '3 juillet dans les chemins de fer',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'entrée 1926',
            'url' => 'https://fr.wikipedia.org/wiki/3_juillet_dans_les_chemins_de_fer',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Résume l\'accident (aiguillage, 18 morts, 67 blessés, causes déclarées accidentelles et liées à la météo) sans citer de source.',
        ],
        'wiki-ouest-etat' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => 'Accidents et catastrophes naturelles (section « Accident du 3 juillet 1926 »)',
            'publisher' => 'Wiki Ouest-État (anciensreseaux.fr)',
            'author' => null,
            'published' => null,
            'locator' => 'Accident du 3 juillet 1926',
            'url' => 'https://wikiouestetat.anciensreseaux.fr/index.php/Accidents_et_catastrophes_naturelles',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne l\'heure, le lieu, le bilan de 18 morts et 67 blessés et deux causes possibles ; section sans source écrite, avec des légendes de photographies de presse. Contenu sous licence CC BY-SA 4.0.',
        ],
        'petit-journal-1926-07-05' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal, 5 juillet 1926',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1926-07-05',
            'locator' => 'p. 1-2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k6296035/f1.image.r=le%20petit%20journal.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
        'maison-1928' => [
            'provider' => 'gallica',
            'kind' => 'scholarly',
            'reliability' => 'scholarly',
            'title' => 'Note sur la situation générale des grands réseaux de chemins de fer français au regard des accidents survenus en 1926',
            'publisher' => 'Annales des Mines, 12e série, Mémoires, tome XIII',
            'author' => 'F. Maison',
            'published' => '1928',
            'locator' => 'p. 109 et suivantes',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k9618419h/f115.image.r=Maison?rk=21459;2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Analyse annuelle officielle des accidents de 1926, citée par Wikipédia ; à consulter pour les causes établies.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 juillet 1926 à 20 h 10, l\'express Le Havre-Paris déraille à la bifurcation des Ambassadeurs, entre Poissy et Achères (Yvelines). Un violent orage sévit à ce moment.',
                'en' => 'On 3 July 1926 at 8.10 pm the Le Havre-Paris express derailed at the Ambassadeurs junction, between Poissy and Achères (Yvelines). A violent storm was raging at the time.',
                'de' => 'Am 3. Juli 1926 um 20.10 Uhr entgleiste der Schnellzug Le Havre-Paris an der Abzweigung Ambassadeurs zwischen Poissy und Achères (Yvelines). Zu dieser Zeit wütete ein heftiges Gewitter.',
                'sources' => [
                    'wp-liste-1926',
                    'wiki-ouest-etat',
                ],
            ],
            [
                'fr' => 'La voie principale était en travaux : le train devait être dirigé par un aiguillage vers une voie déviée.',
                'en' => 'The main line was under works: the train was to be diverted by a set of points onto a deviation track.',
                'de' => 'Das Hauptgleis befand sich in Bauarbeiten: Der Zug sollte über eine Weiche auf ein Umleitungsgleis geführt werden.',
                'sources' => [
                    'wp-liste-1926',
                    'wiki-ouest-etat',
                ],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train déraille sur l\'aiguillage. Les voitures se télescopent et se disloquent.',
                'en' => 'The train derailed on the points. The coaches telescoped and broke apart.',
                'de' => 'Der Zug entgleiste auf der Weiche. Die Wagen schoben sich ineinander und zerbrachen.',
                'sources' => [
                    'wp-liste-1926',
                    'wiki-ouest-etat',
                ],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de 18 morts et 67 blessés.',
                'en' => 'The toll is 18 dead and 67 injured.',
                'de' => 'Die Bilanz beträgt 18 Tote und 67 Verletzte.',
                'sources' => [
                    'wp-liste-1926',
                    'wiki-ouest-etat',
                    'wp-3-juillet',
                ],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Aucune cause certaine n\'est retenue dans les pages lues. Elles évoquent le temps orageux, qui aurait pu perturber la signalisation électrique ou gêner la visibilité du mécanicien. Une page indique que les causes ont été déclarées accidentelles et liées à la météo, bien que l\'aiguillage fût en travaux.',
                'en' => 'The pages read give no established cause. They mention the stormy weather, which might have disturbed the electric signalling or impaired the driver\'s visibility. One page states that the causes were declared accidental and weather-related, even though the points were under works.',
                'de' => 'Die gelesenen Seiten nennen keine gesicherte Ursache. Sie verweisen auf das Gewitterwetter, das die elektrische Signalisierung gestört oder die Sicht des Lokführers beeinträchtigt haben könnte. Eine Seite gibt an, die Ursachen seien als zufällig und wetterbedingt erklärt worden, obwohl die Weiche in Bauarbeiten lag.',
                'sources' => [
                    'wp-liste-1926',
                    'wiki-ouest-etat',
                    'wp-3-juillet',
                ],
            ],
        ],
    ],
    'images' => [
        [
            'file' => 'File:4-7-26, catastrophe d\'Achères (déraillement du rapide Le Havre-Paris, près d\'Achères) - btv1b53165773z.jpg',
            'caption' => [
                'fr' => 'Photographie de presse de l\'Agence Rol du déraillement du rapide Le Havre-Paris près d\'Achères, juillet 1926.',
                'en' => 'Press photograph by the Agence Rol of the derailment of the Le Havre-Paris express near Achères, July 1926.',
                'de' => 'Pressefoto der Agence Rol von der Entgleisung des Schnellzugs Le Havre-Paris bei Achères, Juli 1926.',
            ],
        ],
        [
            'file' => 'File:4-7-26, catastrophe d\'Achères (déraillement du rapide Le Havre-Paris, près d\'Achères) - btv1b53165770m.jpg',
            'caption' => [
                'fr' => 'Autre vue du déraillement du rapide Le Havre-Paris près d\'Achères, Agence Rol, juillet 1926.',
                'en' => 'Another view of the derailment of the Le Havre-Paris express near Achères, Agence Rol, July 1926.',
                'de' => 'Weitere Ansicht der Entgleisung des Schnellzugs Le Havre-Paris bei Achères, Agence Rol, Juli 1926.',
            ],
        ],
    ],
];
