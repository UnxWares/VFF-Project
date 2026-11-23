<?php

// Contexte sourcé du tamponnement de deux trains de marchandises à La Coquille, ligne de Limoges à Périgueux, nuit du 21 au 22 septembre 1901 (Journal de Genève, lu le 23/11/2026).
return [
    'slug' => '1901-09-22-la-coquille',
    'checked_on' => '2026-11-23',
    'sources' => [
        'letemps-jdg-1901-09-23' => [
            'provider' => 'letempsarchives',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dépêches télégraphiques, Limoges, 22 septembre, Journal de Genève du 23 septembre 1901, page 3',
            'publisher' => 'Journal de Genève (Le Temps Archives)',
            'author' => null,
            'published' => '1901-09-23',
            'locator' => 'p. 3',
            'url' => 'https://www.letempsarchives.ch/page/JDG_1901_09_23/3',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Brève de Limoges datée du 22 : « un tamponnement » entre deux trains de marchandises à La Coquille, ligne de Limoges à Périgueux, vers une heure de la nuit ; deux garde-freins tués, le mécanicien et le chef du train tamponneur grièvement blessés. Faits relevés par VFF, aucun texte recopié.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Dans la nuit du 21 au 22 septembre 1901, vers une heure du matin, deux trains de marchandises se sont tamponnés à La Coquille, sur la ligne de Limoges à Périgueux. Les trains de voyageurs ont subi des retards considérables.',
                'en' => 'In the night of 21 to 22 September 1901, at about one in the morning, two goods trains collided at La Coquille, on the Limoges to Périgueux line. Passenger trains suffered considerable delays.',
                'de' => 'In der Nacht vom 21. auf den 22. September 1901 stießen gegen ein Uhr morgens in La Coquille auf der Strecke von Limoges nach Périgueux zwei Güterzüge zusammen. Die Personenzüge erlitten erhebliche Verspätungen.',
                'sources' => ['letemps-jdg-1901-09-23'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la dépêche de Limoges du 22 septembre, les deux garde-freins ont été tués ; le mécanicien et le chef du train tamponneur ont été grièvement blessés.',
                'en' => 'According to the Limoges dispatch of 22 September, both brakemen were killed; the driver and the head of the train that ran into the other were seriously injured.',
                'de' => 'Nach der Depesche aus Limoges vom 22. September wurden beide Bremser getötet; der Lokführer und der Zugführer des auffahrenden Zuges wurden schwer verletzt.',
                'sources' => ['letemps-jdg-1901-09-23'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['letemps-jdg-1901-09-23'],
            'fr' => 'Deux garde-freins tués selon la dépêche du Journal de Genève du 23 septembre 1901 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two brakemen killed according to the dispatch in the Journal de Genève of 23 September 1901; the record gave no number of deaths.',
            'de' => 'Zwei Bremser getötet nach der Depesche im Journal de Genève vom 23. September 1901; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['letemps-jdg-1901-09-23'],
            'fr' => 'Deux blessés graves (le mécanicien et le chef du train tamponneur) selon la même dépêche ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Two seriously injured (the driver and the head of the train that ran into the other) according to the same dispatch; the record gave no number of injured.',
            'de' => 'Zwei Schwerverletzte (der Lokführer und der Zugführer des auffahrenden Zuges) nach derselben Depesche; der Eintrag nannte keine Zahl von Verletzten.',
        ],
        [
            'field' => 'occurred_at_time',
            'value' => '01:00',
            'sources' => ['letemps-jdg-1901-09-23'],
            'fr' => 'Vers une heure de la nuit selon la dépêche (heure approximative).',
            'en' => 'At about one in the night according to the dispatch (approximate time).',
            'de' => 'Gegen ein Uhr nachts nach der Depesche (ungefähre Zeit).',
        ],
    ],
];
