<?php

// Fichier d'essai (tests de vff:enrich-accidents) : contenu fictif, au format des vrais fichiers.
return [
    'slug' => '1891-07-26-saint-mande',
    'checked_on' => '2026-11-21',
    'sources' => [
        'rapport' => [
            'provider' => 'bea-tt', 'kind' => 'report', 'reliability' => 'official',
            'title' => 'Rapport d\'essai', 'publisher' => 'BEA-TT', 'author' => null, 'published' => '1891-08',
            'locator' => 'p. 3', 'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/essai.pdf', 'access' => 'open',
            'consulted_on' => '2026-11-21', 'note' => null,
        ],
        'journal' => [
            'provider' => 'presse', 'kind' => 'press', 'reliability' => 'press',
            'title' => 'Journal d\'essai', 'publisher' => 'Le Journal', 'author' => null, 'published' => '1891-07-28',
            'locator' => 'p. 1', 'url' => 'https://example.org/journal', 'access' => 'open', 'consulted_on' => '2026-11-21',
        ],
        'retronews' => [
            'provider' => 'retronews', 'kind' => 'press', 'reliability' => 'press',
            'title' => 'Le Petit Journal, 27 juillet 1891', 'publisher' => 'Le Petit Journal', 'published' => '1891-07-27',
            'url' => 'https://www.retronews.fr/journal/essai', 'access' => 'paywall', 'consulted_on' => '2026-11-21',
        ],
    ],
    'sections' => [
        'circumstances' => [
            ['fr' => 'Deux trains de retour de la fête se suivent vers 21 h 18, environ 1 000 voyageurs.', 'en' => 'Two trains follow each other at about 21:18.', 'de' => 'Zwei Züge folgen einander gegen 21:18 Uhr.', 'sources' => ['rapport', 'journal']],
        ],
        'causes' => [
            [
                'fr' => 'L\'enquête retient un signal mal observé.', 'en' => 'The inquiry found a signal was not observed.', 'de' => 'Die Untersuchung nennt ein nicht beachtetes Signal.',
                'sources' => ['rapport'],
                'quote' => ['text' => 'Le signal était fermé.', 'lang' => 'fr', 'source' => 'rapport'],
            ],
        ],
        'disagreements' => [
            ['fr' => 'Le bilan varie de 43 à 50 morts.', 'en' => 'The toll varies from 43 to 50 deaths.', 'de' => 'Die Zahl der Toten schwankt zwischen 43 und 50.', 'sources' => ['rapport', 'journal']],
        ],
    ],
];
