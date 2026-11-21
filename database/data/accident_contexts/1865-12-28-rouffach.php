<?php

// Contexte sourcé du tamponnement du train 23/89 par le train de marchandises 23/91 en gare de Rouffach, en décembre 1865 (recherche du 21/11/2026).
return [
    'slug' => '1865-12-28-rouffach',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1865, 28 décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1865',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (un mort et plusieurs blessés), d\'après La Presse du 3 janvier 1866.',
        ],
        'vdr-rouffach' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1865, Collision à la gare de Rouffach (68)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1865-collision-a-la-gare-de-rouffach-68/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit la lettre de Rouffach du 28 décembre 1865 au Courrier du Bas-Rhin, reprise par La Presse du 3 janvier 1866.',
        ],
        'la-presse-1866-01-03' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 3 janvier 1866',
            'publisher' => 'La Presse',
            'author' => null,
            'published' => '1866-01-03',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k511747q/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 28 décembre 1865 à 6 h 30, en gare de Rouffach, sur la ligne de Strasbourg à Mulhouse, le train de marchandises 23/91 a tamponné le train 23/89, auquel étaient attelées trois voitures de voyageurs.',
                'en' => 'On 28 December 1865 at 6.30 am, at Rouffach station on the Strasbourg to Mulhouse line, goods train 23/91 ran into train 23/89, which had three passenger carriages attached.',
                'de' => 'Am 28. Dezember 1865 um 6.30 Uhr fuhr im Bahnhof Rouffach an der Strecke Straßburg-Mülhausen der Güterzug 23/91 auf den Zug 23/89 auf, dem drei Personenwagen angehängt waren.',
                'sources' => ['vdr-rouffach', 'wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une femme a été tuée et plusieurs autres personnes ont été grièvement blessées. Wikipédia précise que trois voitures de voyageurs ont été écrasées.',
                'en' => 'A woman was killed and several other people were seriously injured. Wikipedia adds that three passenger carriages were crushed.',
                'de' => 'Eine Frau wurde getötet, mehrere weitere Personen wurden schwer verletzt. Wikipedia ergänzt, dass drei Personenwagen zerdrückt wurden.',
                'sources' => ['vdr-rouffach', 'wp-liste-1860'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon le correspondant du Courrier du Bas-Rhin, l\'accident était dû au brouillard intense, qui empêchait d\'apercevoir les signaux d\'arrêt.',
                'en' => 'According to the Courrier du Bas-Rhin\'s correspondent, the accident was caused by thick fog, which made the stop signals impossible to see.',
                'de' => 'Laut dem Korrespondenten des Courrier du Bas-Rhin war der Unfall auf dichten Nebel zurückzuführen, der die Haltesignale unsichtbar machte.',
                'sources' => ['vdr-rouffach', 'wp-liste-1860'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le procureur impérial et le juge d\'instruction se sont rendus sur les lieux pour recueillir les premières informations sur les causes, et le procureur général est venu lui aussi à Rouffach.',
                'en' => 'The imperial prosecutor and the examining magistrate went to the scene to gather the first information on the causes, and the procurator general also came to Rouffach.',
                'de' => 'Der kaiserliche Staatsanwalt und der Untersuchungsrichter begaben sich an den Unfallort, um erste Erkenntnisse über die Ursachen zu sammeln, und auch der Generalstaatsanwalt kam nach Rouffach.',
                'sources' => ['vdr-rouffach'],
            ],
        ],
    ],
    'corrections' => [],
];
