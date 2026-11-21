<?php

// Contexte sourcé de l'accident de Tournus du 31 octobre 1943 (recherche du 21/11/2026).
return [
    'slug' => '1943-10-31-tournus',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1943, 31 octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 31 octobre 1943, référencée par Le Matin du 2 novembre 1943 (Gallica) : à minuit et demi, express Marseille-Paris déraillé sur un ponceau après un sabotage, onze morts, quarante-sept blessés. Le mot « ponceau » y renvoie par erreur vers l\'article d\'une personne.',
        ],
        'ahicf-1943' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => '1943 : mécaniciens et chauffeurs sous la mitraille',
            'publisher' => 'Rails & histoire (AHICF)',
            'author' => 'Bruno Carrière',
            'published' => '2023-05-10',
            'locator' => 'Accident de Tournus',
            'url' => 'https://www.ahicf.com/post/1943-m%C3%A9caniciens-et-chauffeurs-sous-la-mitraille',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article de blog de l\'association d\'histoire des chemins de fer, appuyé sur les Renseignements hebdomadaires de la SNCF et ses archives ; le passage sur Tournus (0 h 40, express 110, rail déboulonné, onze morts) n\'a pas de note propre.',
        ],
        'gallica-matin-1943-11-02' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 2 novembre 1943, p. 1',
            'publisher' => 'Le Matin',
            'author' => null,
            'published' => '1943-11-02',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k587868f.r=d%C3%A9raille.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Compte rendu de presse sous occupation, cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du 30 au 31 octobre 1943, peu avant Tournus (Saône-et-Loire), sur la ligne de Paris-Lyon à Marseille-Saint-Charles, l\'express Marseille - Paris (train 110 selon un article d\'histoire ferroviaire) a déraillé. L\'heure est donnée à minuit et demi par la liste de Wikipédia et à 0 h 40 par l\'article d\'histoire.',
                'en' => 'In the night of 30 to 31 October 1943, shortly before Tournus (Saône-et-Loire), on the Paris-Lyon to Marseille-Saint-Charles line, the Marseille - Paris express (train 110 according to a railway history article) derailed. The time is given as half past midnight by the Wikipedia list and as 12.40 am by the history article.',
                'de' => 'In der Nacht vom 30. auf den 31. Oktober 1943 entgleiste kurz vor Tournus (Saône-et-Loire) auf der Strecke Paris-Lyon - Marseille-Saint-Charles der Schnellzug Marseille - Paris (laut einem eisenbahngeschichtlichen Artikel Zug 110). Die Uhrzeit wird von der Wikipedia-Liste mit halb eins, vom Geschichtsartikel mit 0.40 Uhr angegeben.',
                'sources' => ['wp-liste-1940', 'ahicf-1943'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a déraillé sur un ponceau (petit ouvrage d\'art) : la locomotive et sept voitures ont quitté la voie, et d\'après l\'article d\'histoire sont tombées dans la tranchée qui borde la ligne.',
                'en' => 'The train derailed on a culvert (a small bridge structure): the locomotive and seven coaches left the track and, according to the history article, ended up in the cutting beside the line.',
                'de' => 'Der Zug entgleiste auf einem Durchlass (kleines Brückenbauwerk): Die Lokomotive und sieben Wagen verließen das Gleis und landeten laut dem Geschichtsartikel im Einschnitt neben der Strecke.',
                'sources' => ['wp-liste-1940', 'ahicf-1943'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de onze morts et, selon la liste de Wikipédia, quarante-sept blessés.',
                'en' => 'The toll is eleven dead and, according to the Wikipedia list, forty-seven injured.',
                'de' => 'Die Bilanz lautet elf Tote und, laut der Wikipedia-Liste, siebenundvierzig Verletzte.',
                'sources' => ['wp-liste-1940', 'ahicf-1943'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les deux sources attribuent le déraillement à un sabotage : un rail avait été déboulonné. Aucun des documents lus ne nomme les auteurs ni leur groupe.',
                'en' => 'Both sources attribute the derailment to sabotage: a rail had been unbolted. None of the documents read names the perpetrators or their group.',
                'de' => 'Beide Quellen führen die Entgleisung auf einen Sabotageakt zurück: Eine Schiene war losgeschraubt worden. Keines der gelesenen Dokumente nennt die Täter oder ihre Gruppe.',
                'sources' => ['wp-liste-1940', 'ahicf-1943'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Heure : minuit et demi selon la liste de Wikipédia, 0 h 40 selon l\'article de Rails & histoire. Aucune des deux ne cite de document propre pour ce passage, et les journaux de l\'époque (Le Matin du 2 novembre 1943) n\'ont pas pu être lus.',
                'en' => 'Time: half past midnight according to the Wikipedia list, 12.40 am according to the Rails & histoire article. Neither cites a document of its own for this passage, and the newspapers of the time (Le Matin of 2 November 1943) could not be read.',
                'de' => 'Uhrzeit: halb eins laut der Wikipedia-Liste, 0.40 Uhr laut dem Artikel von Rails & histoire. Keiner von beiden nennt für diese Stelle ein eigenes Dokument, und die Zeitungen jener Zeit (Le Matin vom 2. November 1943) ließen sich nicht lesen.',
                'sources' => ['wp-liste-1940', 'ahicf-1943'],
            ],
            [
                'fr' => 'Lieu : l\'import avait retenu « Amédée Ponceau » comme lieu ; c\'est une erreur de lien de la liste de Wikipédia, où le nom commun « ponceau » renvoie vers l\'article d\'une personne. Le lieu exact du ponceau, sur la commune de Tournus ou à proximité, n\'est donné par aucune source lue.',
                'en' => 'Place: the import had taken "Amédée Ponceau" as the place; this is a link error in the Wikipedia list, where the common noun "ponceau" (culvert) links to the article of a person. The exact site of the culvert, in the commune of Tournus or near it, is given by no source read.',
                'de' => 'Ort: Der Import hatte „Amédée Ponceau“ als Ort übernommen; das ist ein Verlinkungsfehler der Wikipedia-Liste, in der das Substantiv „ponceau“ (Durchlass) auf den Artikel einer Person verweist. Den genauen Ort des Durchlasses, in der Gemeinde Tournus oder in ihrer Nähe, nennt keine der gelesenen Quellen.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'attack',
            'sources' => ['wp-liste-1940', 'ahicf-1943'],
            'fr' => 'La liste de Wikipédia et l\'association Rails & histoire attribuent toutes deux le déraillement de l\'express à un sabotage (rail déboulonné) : la fiche le classe comme attentat, comme les autres sabotages de la voie de cette période.',
            'en' => 'The Wikipedia list and the Rails & histoire association both attribute the derailment of the express to sabotage (an unbolted rail): the record classes it as an attack, like the other track sabotages of the period.',
            'de' => 'Die Wikipedia-Liste und der Verein Rails & histoire führen die Entgleisung des Schnellzugs beide auf eine Sabotage zurück (losgeschraubte Schiene): Der Eintrag führt sie als Anschlag, wie die anderen Gleissabotagen dieser Zeit.',
        ],
    ],
];
