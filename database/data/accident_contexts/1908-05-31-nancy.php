<?php

// Contexte sourcé de l'accident du funiculaire de la Cure d'Air à Nancy du 31 mai 1908 (recherche du 21/11/2026).
return [
    'slug' => '1908-05-31-nancy',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nancy : voiture du funiculaire de la Cure d\'Air détachée du câble de traction lors de son ascension, partie à la dérive et percutant les voitures suivantes ; un mort et plusieurs blessés.',
        ],
        'delpher-venlosche-1908-06-02' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Nieuwe Venlosche Courant du 2 juin 1908',
            'publisher' => 'Nieuwe Venlosche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1908-06-02',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMGAVL01:000003840:mpeg21:a0004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Pèlerins de Bayon sur le funiculaire de Nancy, deuxième voiture mal placée sur la crémaillère, collision avec la première, deux morts, sept blessés.',
        ],
        'delpher-maasbode-1908-06-03' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenland Duitschland',
            'publisher' => 'De Maasbode (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1908-06-03',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB04:000188980:mpeg21:a0043',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Une voiture du chemin de fer à crémaillère s\'est détachée et heurté une autre ; première voiture brisée, deux morts dont une religieuse, sept blessés graves.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 31 mai 1908, vingt-quatre pèlerins de Bayon, qui avaient visité à Nancy la maison de Saint-Antoine où ils avaient déjeuné, ont pris le funiculaire pour le retour, selon la presse néerlandaise. La liste de Wikipédia désigne le funiculaire de la Cure d\'Air.',
                'en' => 'On Sunday 31 May 1908, twenty-four pilgrims from Bayon, who had visited the Saint-Antoine house in Nancy, where they had had lunch, took the funicular for the return journey, according to the Dutch press. The Wikipedia list names the Cure d\'Air funicular.',
                'de' => 'Am Sonntag, dem 31. Mai 1908, nahmen vierundzwanzig Pilger aus Bayon, die in Nancy das Haus Saint-Antoine besucht und dort zu Mittag gegessen hatten, nach der niederländischen Presse für den Rückweg die Standseilbahn. Die Wikipedia-Liste nennt die Standseilbahn der Cure d\'Air.',
                'sources' => ['delpher-venlosche-1908-06-02', 'delpher-maasbode-1908-06-03', 'wp-liste-1908'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, une voiture s\'est détachée du câble de traction lors de l\'ascension et est partie à la dérive le long de la pente, percutant les voitures qui la suivaient. La presse néerlandaise écrit que la deuxième voiture, qui contenait neuf personnes et qui n\'était pas bien placée sur la crémaillère, est entrée en collision avec la première, qui a été entièrement brisée.',
                'en' => 'According to the Wikipedia list, a car came off the traction cable during the ascent and ran away down the slope, hitting the cars that followed it. The Dutch press writes that the second car, which held nine people and was not properly placed on the rack, collided with the first, which was entirely smashed.',
                'de' => 'Nach der Wikipedia-Liste löste sich bei der Bergfahrt ein Wagen vom Zugseil und lief am Hang zurück, wobei er die nachfolgenden Wagen rammte. Die niederländische Presse schreibt, der zweite Wagen, der neun Personen enthielt und nicht richtig auf der Zahnstange saß, sei mit dem ersten zusammengestoßen, der völlig zertrümmert wurde.',
                'sources' => ['wp-liste-1908', 'delpher-venlosche-1908-06-02', 'delpher-maasbode-1908-06-03'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les journaux néerlandais du 2 et du 3 juin 1908 comptent deux morts, dont une religieuse, et sept blessés, graves selon De Maasbode. La liste de Wikipédia compte un mort et plusieurs blessés.',
                'en' => 'The Dutch newspapers of 2 and 3 June 1908 count two dead, one of them a nun, and seven injured, seriously according to De Maasbode. The Wikipedia list counts one dead and several injured.',
                'de' => 'Die niederländischen Zeitungen vom 2. und 3. Juni 1908 zählen zwei Tote, darunter eine Ordensschwester, und sieben Verletzte, nach De Maasbode schwer. Die Wikipedia-Liste zählt einen Toten und mehrere Verletzte.',
                'sources' => ['delpher-venlosche-1908-06-02', 'delpher-maasbode-1908-06-03', 'wp-liste-1908'],
            ],
        ],
    ],
    'corrections' => [],
];
