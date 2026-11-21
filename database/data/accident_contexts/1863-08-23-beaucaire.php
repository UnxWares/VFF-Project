<?php

// Contexte sourcé du déraillement de Beaucaire du 23 août 1863 (recherche du 21/11/2026).
return [
    'slug' => '1863-08-23-beaucaire',
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
            'locator' => '1863, 23 août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1863',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après La Presse du 26 août et du 15 novembre 1863.',
        ],
        'vdr-beaucaire' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1863, Accident entre Nîmes et Tarascon à Beaucaire (30)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2026-04-01',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1863-accident-entre-nimes-et-tarascon-a-beaucaire-30/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit les récits du Messager du Midi et du Courrier du Gard repris par La Presse du 26 août 1863.',
        ],
        'la-presse-1863-08-26' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 26 août 1863',
            'publisher' => 'La Presse',
            'author' => null,
            'published' => '1863-08-26',
            'locator' => 'p. 2-3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k479893p/f2.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 23 août 1863, l\'express de Montpellier à Tarascon, parti de Nîmes à 12 h 40, approchait du viaduc qui franchit le Rhône entre Beaucaire et Tarascon.',
                'en' => 'On Sunday 23 August 1863, the Montpellier to Tarascon express, which had left Nîmes at 12.40 pm, was approaching the viaduct over the Rhône between Beaucaire and Tarascon.',
                'de' => 'Am Sonntag, dem 23. August 1863, näherte sich der Schnellzug Montpellier-Tarascon, der um 12.40 Uhr in Nîmes abgefahren war, dem Viadukt über die Rhone zwischen Beaucaire und Tarascon.',
                'sources' => ['vdr-beaucaire', 'wp-liste-1860'],
            ],
        ],
        'course' => [
            [
                'fr' => 'À la fin de la rampe, à une centaine de mètres du pont sur le canal, la locomotive s\'est soulevée et a quitté les rails. Elle a parcouru environ 50 m dans le ballast, heurté le parapet du pont puis roulé au bas du talus avec le fourgon à bagages et trois voitures. La rupture des chaînes d\'attelage a retenu en haut du talus une voiture de voyageurs et un fourgon. La voie était libre dès 16 h.',
                'en' => 'At the end of the climb, about a hundred metres from the bridge over the canal, the locomotive lifted and left the rails. It ran about 50 m through the ballast, struck the bridge parapet and rolled down the embankment with the luggage van and three coaches. The coupling chains broke, so one passenger coach and a van stayed at the top of the bank. The line was clear by 4 pm.',
                'de' => 'Am Ende der Steigung, etwa hundert Meter vor der Brücke über den Kanal, hob sich die Lokomotive und sprang aus den Schienen. Sie fuhr etwa 50 m durch den Schotter, prallte gegen die Brüstung der Brücke und rollte mit dem Gepäckwagen und drei Wagen die Böschung hinab. Da die Kupplungsketten rissen, blieben ein Personenwagen und ein Packwagen oben stehen. Ab 16 Uhr war die Strecke wieder frei.',
                'sources' => ['vdr-beaucaire'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Six personnes ont été tuées, dont le mécanicien et un garde-frein, et une douzaine blessées, dont cinq ont été transportées à l\'hôpital de Beaucaire. Le chauffeur et un contrôleur étaient parmi les blessés.',
                'en' => 'Six people were killed, including the driver and a brakeman, and about twelve were injured, five of whom were taken to Beaucaire hospital. The fireman and a ticket inspector were among the injured.',
                'de' => 'Sechs Menschen kamen ums Leben, darunter der Lokführer und ein Bremser, und etwa zwölf wurden verletzt, von denen fünf ins Krankenhaus von Beaucaire gebracht wurden. Unter den Verletzten waren der Heizer und ein Schaffner.',
                'sources' => ['vdr-beaucaire', 'wp-liste-1860'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La rumeur publique parlait aussitôt d\'un obstacle posé sur les rails. Peu après, des enfants surpris à placer de nouveaux obstacles sur la voie ont été soupçonnés ; l\'un d\'eux, âgé de neuf ans, a été placé en maison de correction.',
                'en' => 'Local rumour at once spoke of an obstacle placed on the rails. Shortly afterwards, children caught putting more obstacles on the line were suspected; one of them, aged nine, was sent to a reformatory.',
                'de' => 'Gerüchte sprachen sofort von einem Hindernis auf den Schienen. Kurz darauf gerieten Kinder in Verdacht, die beim Auflegen weiterer Hindernisse ertappt worden waren; eines von ihnen, neun Jahre alt, kam in eine Besserungsanstalt.',
                'sources' => ['vdr-beaucaire', 'wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche compte 10 blessés d\'après Wikipédia ; les journaux du Gard en donnent une douzaine. Les premières dépêches parlaient de cinq morts.',
                'en' => 'The record counts 10 injured from Wikipedia; the Gard newspapers give about twelve. The first dispatches spoke of five dead.',
                'de' => 'Der Eintrag nennt nach Wikipedia 10 Verletzte; die Zeitungen des Gard nennen etwa zwölf. Die ersten Depeschen sprachen von fünf Toten.',
                'sources' => ['wp-liste-1860', 'vdr-beaucaire'],
            ],
        ],
    ],
    'corrections' => [],
];
