<?php

// Contexte sourcé de l'accident du 21 juillet 1908 près de Châteauvillain (recherche du 21/11/2026).
return [
    'slug' => '1908-07-21-chateauvillain',
    'checked_on' => '2026-11-21',
    'sources' => [
        'ddb-1908-07-22-reichsanzeiger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dépêche de Paris du 22 juillet 1908 sur un accident près de Châteauvillain',
            'publisher' => 'Deutscher Reichsanzeiger und Preußischer Staatsanzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1908-07-22',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/TMMCTKIJL2FCXLNS7AP2SBEQEP77AUKO?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de l\'agence W. T. B. datée de Paris, 22 juillet, d\'après Chaumont (Haute-Marne) : un accident ferroviaire près de Châteauvillain a tué un inspecteur de train et blessé quatre voyageurs.',
        ],
        'ddb-1908-07-23-vorwaerts' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dépêche de Paris du 22 juillet 1908 sur un accident près de Châteauvillain',
            'publisher' => 'Vorwärts (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1908-07-23',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/5H6LD3NFLKPBX5LXDB7D7SNR5GSNQPCZ?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche d\'agence. D\'autres journaux la reprennent aussi, dont la Saale-Zeitung et le Dresdner Journal du 22 juillet et le General-Anzeiger du 23 juillet 1908.',
        ],
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : sur la ligne de Chaumont à Châtillon-sur-Seine, la Compagnie de l\'Est lance un fourgon dans une pente pour des essais de freinage ; près de Châteauvillain, le fourgon, où ont pris place des ingénieurs, est percuté par la locomotive haut-le-pied qui le suit ; un mort et sept blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Selon la liste de Wikipédia, la Compagnie de l\'Est faisait des essais de freinage sur la ligne de Chaumont à Châtillon-sur-Seine : un fourgon, occupé par des ingénieurs munis de leurs appareils de mesure, était lancé dans une pente.',
                'en' => 'According to the Wikipedia list, the Compagnie de l\'Est was running braking tests on the Chaumont to Châtillon-sur-Seine line: a brake van carrying engineers with their measuring instruments was sent down a gradient.',
                'de' => 'Nach der Wikipedia-Liste führte die Compagnie de l\'Est Bremsversuche auf der Strecke von Chaumont nach Châtillon-sur-Seine durch: Ein Gepäckwagen mit Ingenieuren und ihren Messgeräten wurde ein Gefälle hinabgelassen.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Une dépêche d\'agence du 22 juillet 1908, reprise par la presse allemande, signale un accident ferroviaire près de Châteauvillain (Haute-Marne), transmis depuis Chaumont.',
                'en' => 'A news agency dispatch of 22 July 1908, carried by the German press, reports a railway accident near Châteauvillain (Haute-Marne), relayed from Chaumont.',
                'de' => 'Eine Agenturdepesche vom 22. Juli 1908, die die deutsche Presse abdruckt, meldet einen Eisenbahnunfall bei Châteauvillain (Haute-Marne), übermittelt aus Chaumont.',
                'sources' => ['ddb-1908-07-22-reichsanzeiger', 'ddb-1908-07-23-vorwaerts'],
            ],
            [
                'fr' => 'D\'après la liste de Wikipédia, le fourgon d\'essai a été percuté et broyé par la locomotive haut-le-pied qui le suivait et n\'a pas pu s\'arrêter à temps.',
                'en' => 'According to the Wikipedia list, the test van was struck and crushed by the light engine following it, which could not stop in time.',
                'de' => 'Nach der Wikipedia-Liste wurde der Versuchswagen von der ihm folgenden Leerfahrt-Lokomotive gerammt und zerquetscht, die nicht rechtzeitig anhalten konnte.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La dépêche d\'agence fait état d\'un inspecteur de train tué et de quatre voyageurs blessés. La liste de Wikipédia donne un mort et sept blessés.',
                'en' => 'The agency dispatch reports one train inspector killed and four travellers injured. The Wikipedia list gives one dead and seven injured.',
                'de' => 'Die Agenturdepesche meldet einen getöteten Zuginspektor und vier verletzte Reisende. Die Wikipedia-Liste nennt einen Toten und sieben Verletzte.',
                'sources' => ['ddb-1908-07-22-reichsanzeiger', 'ddb-1908-07-23-vorwaerts', 'wp-liste-1908'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère : quatre voyageurs selon la dépêche du 22 juillet 1908, sept selon la liste de Wikipédia. La dépêche parle de voyageurs, la liste d\'un fourgon d\'essai occupé par des ingénieurs.',
                'en' => 'The number of injured differs: four travellers according to the dispatch of 22 July 1908, seven according to the Wikipedia list. The dispatch speaks of travellers, the list of a test van carrying engineers.',
                'de' => 'Die Zahl der Verletzten weicht ab: vier Reisende nach der Depesche vom 22. Juli 1908, sieben nach der Wikipedia-Liste. Die Depesche spricht von Reisenden, die Liste von einem Versuchswagen mit Ingenieuren.',
                'sources' => ['ddb-1908-07-22-reichsanzeiger', 'wp-liste-1908'],
            ],
        ],
    ],
];
