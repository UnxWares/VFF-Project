<?php

// Contexte sourcé de la collision d'un train de voyageurs avec un train de marchandises près de Lyon du 16 septembre 1992 (recherche du 21/11/2026).
return [
    'slug' => '1992-09-16-curis-au-mont-dor',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-curis-a' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dode bij treinongeluk Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1992-09-17',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:011020046:mpeg21:a0202',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-curis-b' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Trein ravage bij Lyon',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1992-09-17',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010646610:mpeg21:a0552',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1990' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1990',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1992',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1990&oldid=239974394#1992',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : rattrapage d\'un train Corail Lyon - Dijon sur un train de marchandises à Curis-au-Mont-d\'Or, 1 mort.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mercredi 16 septembre 1992, dans l\'heure de pointe du matin, un train de voyageurs qui venait de quitter Lyon-Perrache a heurté un train de marchandises à l\'arrière, près de Lyon. La liste de Wikipédia précise qu\'il s\'agissait d\'un train Corail qui reliait Lyon à Dijon et qu\'il a rattrapé un train de marchandises à Curis-au-Mont-d\'Or.',
                'en' => 'On Wednesday 16 September 1992, in the morning rush hour, a passenger train that had just left Lyon-Perrache ran into the rear of a goods train near Lyon. The Wikipedia list states that it was a Corail train running from Lyon to Dijon and that it caught up with a goods train at Curis-au-Mont-d\'Or.',
                'de' => 'Am Mittwoch, dem 16. September 1992, im Morgenberufsverkehr, fuhr ein soeben aus Lyon-Perrache abgefahrener Reisezug nahe Lyon auf das Ende eines Güterzugs auf. Die Wikipedia-Liste gibt an, es sei ein Corail-Zug von Lyon nach Dijon gewesen, der in Curis-au-Mont-d\'Or einen Güterzug eingeholt habe.',
                'sources' => ['presse-curis-a', 'wp-liste-1990'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur du train de voyageurs a été tué. Vingt-deux passagers ont été blessés, dont sept grièvement.',
                'en' => 'The driver of the passenger train was killed. Twenty-two passengers were injured, seven of them seriously.',
                'de' => 'Der Lokführer des Reisezugs wurde getötet. Zweiundzwanzig Fahrgäste wurden verletzt, davon sieben schwer.',
                'sources' => ['presse-curis-a', 'presse-curis-b'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le quotidien néerlandais qui reprend une dépêche AP indique que la raison pour laquelle le conducteur du train de banlieue a heurté le train de marchandises n\'était pas connue, alors qu\'une légende de photo de l\'agence Reuter attribue la collision à un non-fonctionnement du système de freinage automatique du train de voyageurs.',
                'en' => 'The Dutch daily relaying an AP dispatch says the reason why the commuter train driver ran into the goods train was not known, whereas a Reuter photo caption attributes the collision to the failure of the passenger train\'s automatic braking system.',
                'de' => 'Die niederländische Tageszeitung, die eine AP-Meldung wiedergibt, schreibt, der Grund, weshalb der Lokführer des Pendlerzugs auf den Güterzug auffuhr, sei nicht bekannt, während eine Bildunterschrift der Agentur Reuters die Kollision auf den Ausfall des automatischen Bremssystems des Reisezugs zurückführt.',
                'sources' => ['presse-curis-a', 'presse-curis-b'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 22,
            'sources' => ['presse-curis-a', 'presse-curis-b'],
            'fr' => 'Les deux articles de la presse néerlandaise du 17 septembre 1992 comptent vingt-deux blessés.',
            'en' => 'Both Dutch press articles of 17 September 1992 count twenty-two injured.',
            'de' => 'Beide niederländischen Presseartikel vom 17. September 1992 zählen zweiundzwanzig Verletzte.',
        ],
    ],
];
