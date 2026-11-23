<?php

// Contexte sourcé du déraillement d'un train de travaux près de Laqueuille du 28 mai 1908 (recherche du 21/11/2026).
return [
    'slug' => '1908-05-28-laqueuille',
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
            'note' => 'Ligne Clermont-Tulle, près de la gare de Laqueuille, wagon d\'un train de travaux chargé de rails et de vingt-deux ouvriers, trois morts et onze blessés ; renvoie à Le Temps du 28 mai 1908 (non lu).',
        ],
        'delpher-maasbode-1908-05-30' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De Maasbode du 30 mai 1908',
            'publisher' => 'De Maasbode (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1908-05-30',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB04:000188973:mpeg21:a0032',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève de deux phrases : wagons chargés de rails, 16 ouvriers, trois morts, presque tous les autres blessés dont cinq gravement ; lieu écrit « Tygurand ».',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 28 mai 1908, vers 13 h, un train de travaux a déraillé sur la ligne de Clermont-Ferrand à Tulle, près de la gare de Laqueuille. Un de ses wagons, chargé de rails sur lesquels étaient juchés des ouvriers, s\'est renversé dans une courbe, selon la liste de Wikipédia.',
                'en' => 'On Thursday 28 May 1908, at about 1 pm, a work train derailed on the line from Clermont-Ferrand to Tulle, near Laqueuille station. One of its wagons, loaded with rails on which workers were perched, overturned in a curve, according to the Wikipedia list.',
                'de' => 'Am Donnerstag, dem 28. Mai 1908, entgleiste gegen 13 Uhr ein Bauzug auf der Strecke von Clermont-Ferrand nach Tulle nahe dem Bahnhof Laqueuille. Einer seiner Wagen, beladen mit Schienen, auf denen Arbeiter saßen, kippte nach der Wikipedia-Liste in einer Kurve um.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, vingt-deux ouvriers se trouvaient sur les rails ; l\'accident a fait trois morts et onze blessés. Une brève de la presse néerlandaise du 30 mai 1908, datée de Clermont-Ferrand, parle de 16 ouvriers sur des wagons chargés de rails, de trois morts et de presque tous les autres blessés, dont cinq gravement.',
                'en' => 'According to the Wikipedia list, twenty-two workers were on the rails; the accident killed three and injured eleven. A short item in the Dutch press of 30 May 1908, datelined Clermont-Ferrand, speaks of 16 workers on wagons loaded with rails, three dead and almost all the others injured, five of them seriously.',
                'de' => 'Nach der Wikipedia-Liste befanden sich zweiundzwanzig Arbeiter auf den Schienen; bei dem Unfall starben drei, elf wurden verletzt. Eine kurze Meldung der niederländischen Presse vom 30. Mai 1908 aus Clermont-Ferrand spricht von 16 Arbeitern auf mit Schienen beladenen Wagen, drei Toten und fast allen übrigen Verletzten, davon fünf schwer.',
                'sources' => ['wp-liste-1908', 'delpher-maasbode-1908-05-30'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre d\'ouvriers diffère (22 selon la liste de Wikipédia, 16 selon De Maasbode), de même que celui des blessés (onze, ou presque tous les ouvriers non tués). Le journal néerlandais situe le déraillement à un lieu qu\'il écrit « Tygurand » et non à Laqueuille ; ce nom ressemble à celui d\'Eygurande, autre gare de la même ligne, sans autre confirmation. Aucun compte rendu détaillé n\'a pu être lu : la presse française de l\'époque cité par la liste est sur Gallica.',
                'en' => 'The number of workers differs (22 according to the Wikipedia list, 16 according to De Maasbode), as does that of the injured (eleven, or almost all the workers who were not killed). The Dutch newspaper places the derailment at a spot it spells "Tygurand" rather than at Laqueuille; this name resembles Eygurande, another station on the same line, with no further confirmation. No detailed report could be read: the French press of the time cited by the list is on Gallica.',
                'de' => 'Die Zahl der Arbeiter weicht ab (22 nach der Wikipedia-Liste, 16 nach De Maasbode), ebenso die der Verletzten (elf oder fast alle nicht getöteten Arbeiter). Die niederländische Zeitung verortet die Entgleisung an einem Ort, den sie „Tygurand“ schreibt, und nicht in Laqueuille; der Name ähnelt Eygurande, einem anderen Bahnhof derselben Strecke, ohne weitere Bestätigung. Ein ausführlicher Bericht konnte nicht gelesen werden: Die von der Liste zitierte französische Presse jener Zeit liegt auf Gallica.',
                'sources' => ['wp-liste-1908', 'delpher-maasbode-1908-05-30'],
            ],
        ],
    ],
    'corrections' => [],
];
