<?php

// Contexte sourcé de la collision entre Lérouville et Ernecourt-Loxéville, le 21 mars 1920 (recherche du 21/11/2026).
return [
    'slug' => '1920-03-21-lerouville',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1920' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1920',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '21 mars 1920, Lérouville',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Place la collision vers 1 h du matin, dans le brouillard, entre Lérouville et Ernecourt-Loxéville ; un mort et neuf blessés ; cite Le Petit Journal du 22 mars 1920.',
        ],
        'grondwet-1920-03-23' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De grondwet du 23 mars 1920',
            'publisher' => 'De grondwet (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1920-03-23',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMGARO01:000175539:mpeg21:a0005',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève de la rubrique « In enkele regels ».',
        ],
        'petit-journal-1920-03-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 22 mars 1920, page 3',
            'publisher' => 'Le Petit Journal (numérisé sur Gallica)',
            'author' => null,
            'published' => '1920-03-22',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k622567h',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le 21 mars 1920 vers 1 h du matin, sur la ligne de Paris à Strasbourg, l\'express de Metz à Paris était arrêté au sémaphore de Cousances-les-Triconville, entre Lérouville et Ernecourt-Loxéville, quand un train de marchandises descendant vers Bar-le-Duc l\'a percuté.',
                'en' => 'According to the Wikipedia list, on 21 March 1920 at about 1 am, on the Paris to Strasbourg line, the Metz to Paris express was stopped at the Cousances-les-Triconville signal, between Lérouville and Ernecourt-Loxéville, when a goods train heading down towards Bar-le-Duc ran into it.',
                'de' => 'Laut der Wikipedia-Liste stand am 21. März 1920 gegen 1 Uhr morgens auf der Strecke Paris-Straßburg der Schnellzug Metz-Paris am Signal von Cousances-les-Triconville zwischen Lérouville und Ernecourt-Loxéville, als ein nach Bar-le-Duc fahrender Güterzug auf ihn auffuhr.',
                'sources' => ['wp-liste-1920', 'grondwet-1920-03-23'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le mécanicien du train de marchandises, gêné par le brouillard, n\'avait pas observé les signaux d\'arrêt, d\'après la liste de Wikipédia.',
                'en' => 'The goods train driver, hampered by fog, had not seen the stop signals, according to the Wikipedia list.',
                'de' => 'Der Lokführer des Güterzugs hatte laut der Wikipedia-Liste wegen des Nebels die Haltsignale nicht beachtet.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'De Grondwet signale que l\'express Metz-Paris est entré en collision avec un autre train près de la gare de Lerouville, tuant un homme et en blessant sept, avec des dégâts matériels importants. La liste de Wikipédia compte un mort et neuf blessés, le fourgon de queue et la dernière voiture du train tamponné ayant été écrasés.',
                'en' => 'De Grondwet reports that the Metz to Paris express collided with another train near Lerouville station, killing one man and injuring seven, with considerable material damage. The Wikipedia list counts one dead and nine injured, the rear van and last coach of the struck train having been crushed.',
                'de' => 'De Grondwet meldet, der Schnellzug Metz-Paris sei nahe dem Bahnhof Lerouville mit einem anderen Zug zusammengestoßen, wobei ein Mann getötet und sieben verletzt worden seien, bei erheblichem Sachschaden. Die Wikipedia-Liste zählt einen Toten und neun Verletzte, wobei der Schlusswagen und der letzte Wagen des gerammten Zuges zerdrückt wurden.',
                'sources' => ['grondwet-1920-03-23', 'wp-liste-1920'],
            ],
        ],
    ],
    'corrections' => [],
];
