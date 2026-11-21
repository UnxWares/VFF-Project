<?php

// Fichier d'essai (tests de vff:enrich-accidents) : contenu fictif.
return [
    'slug' => '2013-07-12-bretigny-sur-orge',
    'checked_on' => '2026-11-20',
    'sources' => [
        'page' => [
            'provider' => 'sites-web', 'kind' => 'web', 'reliability' => 'local_history',
            'title' => 'Page d\'essai', 'publisher' => null, 'author' => null, 'published' => null, 'locator' => null,
            'url' => 'https://example.org/bretigny', 'access' => 'open', 'consulted_on' => '2026-11-20',
        ],
    ],
    'sections' => [
        'memory' => [
            ['fr' => 'Une stèle rappelle l\'accident.', 'en' => 'A memorial recalls the accident.', 'de' => 'Ein Gedenkstein erinnert an den Unfall.', 'sources' => ['page']],
        ],
    ],
];
