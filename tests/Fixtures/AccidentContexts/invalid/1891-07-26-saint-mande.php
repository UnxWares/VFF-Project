<?php

// Fichier d'essai invalide (tests de vff:enrich-accidents) : chaque règle enfreinte une fois.
return [
    'slug' => '1891-07-26-saint-mande',
    'checked_on' => '2026-13-01',
    'unknown' => true,
    'sources' => [
        'a' => ['provider' => 'inconnu', 'kind' => 'press', 'reliability' => 'press', 'title' => 'T', 'url' => 'https://example.org', 'access' => 'open', 'consulted_on' => '2026-11-21'],
        'payant' => ['provider' => 'retronews', 'kind' => 'press', 'reliability' => 'press', 'title' => 'T', 'url' => 'https://www.retronews.fr/x', 'access' => 'paywall', 'consulted_on' => '2026-11-21'],
        'presse' => ['provider' => 'presse', 'kind' => 'press', 'reliability' => 'press', 'title' => 'T', 'url' => 'https://example.org/p', 'access' => 'open', 'consulted_on' => '2026-11-21'],
    ],
    'sections' => [
        'course' => [
            ['fr' => "Texte avec un tiret long \u{2014} interdit.", 'en' => 'Text.', 'sources' => ['payant']],
            ['fr' => 'Texte.', 'en' => 'Text.', 'de' => 'Text.', 'sources' => ['presse'], 'quote' => ['text' => 'Citation.', 'lang' => 'fr', 'source' => 'presse']],
        ],
    ],
    'corrections' => [
        ['field' => 'commune_name', 'value' => 'X', 'sources' => ['presse'], 'fr' => 'x', 'en' => 'x', 'de' => 'x'],
    ],
];
