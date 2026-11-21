<?php

// Contexte sourcé de la collision en gare d'Andelot du 16 septembre 1890 (recherche du 21/11/2026).
return [
    'slug' => '1890-09-16-andelot-en-montagne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'vdr-andelot' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1890, Collision due à un tamponnement en gare d\'Andelot-en-Montagne (39)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1890-collision-due-a-un-tamponnement-en-gare-dandelot-en-montagne/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Figaro du 18 septembre 1890 et Le Courrier de Saône-et-Loire du 20 septembre 1890.',
        ],
        'figaro-1890-09-18' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 18 septembre 1890',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1890-09-18',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k281252p/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1890',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1890',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : train mixte contre un train de marchandises en manoeuvre, 2 morts et 9 blessés ; cite Le Figaro du 18 septembre 1890.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'En gare d\'Andelot, sur la ligne de Dole à Vallorbe, un train mixte de voyageurs et de marchandises a tamponné un train de marchandises qui manoeuvrait à 300 mètres de la gare. Le Figaro du 18 septembre 1890 situe la collision « hier », soit le 16 septembre.',
                'en' => 'At Andelot station, on the Dole to Vallorbe line, a mixed passenger and goods train ran into a goods train that was shunting 300 metres from the station. Le Figaro of 18 September 1890 places the collision "yesterday", that is 16 September.',
                'de' => 'Im Bahnhof Andelot an der Strecke von Dole nach Vallorbe fuhr ein gemischter Zug für Reisende und Güter auf einen Güterzug auf, der 300 Meter vom Bahnhof entfernt rangierte. Le Figaro vom 18. September 1890 verlegt die Kollision auf „gestern“, also den 16. September.',
                'sources' => ['vdr-andelot', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les deux machines ont été très endommagées. Dix wagons ont été broyés et culbutés hors de la voie, et un wagon de troisième classe est entré dans une voiture de première. Le déblaiement a pris une journée entière.',
                'en' => 'Both engines were badly damaged. Ten wagons were crushed and thrown off the track, and a third-class carriage drove into a first-class one. Clearing the line took a whole day.',
                'de' => 'Beide Lokomotiven wurden stark beschädigt. Zehn Wagen wurden zerdrückt und aus dem Gleis geworfen, und ein Wagen der dritten Klasse schob sich in einen der ersten Klasse. Das Räumen der Strecke dauerte einen ganzen Tag.',
                'sources' => ['vdr-andelot'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux personnes ont été tuées : un prêtre de vingt-sept ans, professeur au séminaire de la Consolation près de Besançon, et une jeune femme de vingt et un ans. Les deux corps ont été déposés dans la salle d\'attente de la gare.',
                'en' => 'Two people were killed: a twenty-seven-year-old priest, a teacher at the Consolation seminary near Besançon, and a twenty-one-year-old young woman. Both bodies were laid in the station waiting room.',
                'de' => 'Zwei Menschen kamen ums Leben: ein siebenundzwanzigjähriger Priester, Lehrer am Priesterseminar La Consolation bei Besançon, und eine einundzwanzigjährige junge Frau. Beide Leichen wurden im Wartesaal des Bahnhofs aufgebahrt.',
                'sources' => ['vdr-andelot'],
            ],
            [
                'fr' => 'Parmi les blessés figuraient des employés de la compagnie PLM, c\'est-à-dire le personnel des deux trains, et une petite fille aux deux jambes fracturées. Une dizaine de voyageurs ont été plus ou moins contusionnés, et on craignait que quatre blessés ne succombent. Les blessés ont été soignés dans des maisons particulières d\'Andelot.',
                'en' => 'The injured included PLM company employees, that is the crews of the two trains, and a little girl with both legs broken. About ten passengers were more or less bruised, and it was feared that four of the injured would not survive. The injured were treated in private houses in Andelot.',
                'de' => 'Zu den Verletzten zählten Angestellte der PLM-Gesellschaft, das heißt das Personal der beiden Züge, und ein kleines Mädchen mit zwei gebrochenen Beinen. Etwa zehn Reisende erlitten mehr oder weniger starke Prellungen, und man befürchtete, dass vier Verletzte ihren Verletzungen erliegen würden. Die Verletzten wurden in Privathäusern von Andelot gepflegt.',
                'sources' => ['vdr-andelot'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les journaux donnent des bilans de blessés différents : selon Le Figaro, trois voyageurs sont blessés grièvement et six employés légèrement (neuf blessés, comme dans la liste de Wikipédia), tandis que Le Courrier de Saône-et-Loire du 20 septembre compte six employés blessés grièvement, une enfant aux jambes fracturées et une dizaine de voyageurs contusionnés. Le nombre de morts, deux, est le même partout.',
                'en' => 'The newspapers give different injury figures: according to Le Figaro, three passengers were seriously injured and six employees slightly (nine injured, as in the Wikipedia list), whereas Le Courrier de Saône-et-Loire of 20 September counts six employees seriously injured, a child with broken legs and about ten bruised passengers. The number of dead, two, is the same everywhere.',
                'de' => 'Die Zeitungen nennen unterschiedliche Verletztenzahlen: Nach Le Figaro wurden drei Reisende schwer und sechs Angestellte leicht verletzt (neun Verletzte, wie in der Wikipedia-Liste), während Le Courrier de Saône-et-Loire vom 20. September sechs schwer verletzte Angestellte, ein Kind mit gebrochenen Beinen und etwa zehn Reisende mit Prellungen zählt. Die Zahl der Toten, zwei, ist überall dieselbe.',
                'sources' => ['vdr-andelot', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
