<?php

// Contexte sourcé du déraillement d'un train de marchandises dans la descente d'Aubignas au Teil, en juin 1876 (recherche du 21/11/2026).
return [
    'slug' => '1876-06-07-le-teil',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1876, 7 juin',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1876',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (5 morts, 4 blessés graves).',
        ],
        'vdr-ales-pouzin' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1876, Déraillement sur la ligne Alès - Pouzin (07)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2026-04-09',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1876-deraillement-sur-la-ligne-ales-pouzin/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit la correspondance de Montélimar du 9 juin 1876 publiée par Le Figaro du 10 juin 1876.',
        ],
        'figaro-1876-06-10' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 10 juin 1876',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1876-06-10',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k275994v/f2.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'La ligne du Teil à Alès avait été ouverte un mois plus tôt et comptait de fortes pentes. Un train de marchandises d\'une quarantaine de wagons chargés de rails et de charbon, en direction du Pouzin, a abordé la descente des Combes, d\'Aubignas vers Le Teil. Il avait quinze serre-freins.',
                'en' => 'The Le Teil to Alès line had opened a month earlier and had steep gradients. A freight train of about forty wagons loaded with rails and coal, heading for Le Pouzin, started down the Combes descent from Aubignas towards Le Teil. It carried fifteen brakemen.',
                'de' => 'Die Strecke Le Teil-Alès war einen Monat zuvor eröffnet worden und hatte starke Gefälle. Ein Güterzug mit etwa vierzig mit Schienen und Kohle beladenen Wagen in Richtung Le Pouzin fuhr in das Gefälle der Combes von Aubignas nach Le Teil ein. Er war mit fünfzehn Bremsern besetzt.',
                'sources' => ['vdr-ales-pouzin', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le poids du train l\'a emballé malgré le signal d\'alarme du mécanicien, les freins et la contre-vapeur ; à la traversée de Mélas, les freins laissaient une traînée lumineuse. Dans une courbe serrée au bas de la pente, près du Teil, la machine a déraillé et s\'est retournée en travers de la voie, et les wagons se sont empilés sur elle.',
                'en' => 'The weight of the train made it run away despite the driver\'s alarm signal, the brakes and the reversed steam; as it went through Mélas, the brakes left a trail of sparks. On a sharp curve at the foot of the slope, near Le Teil, the engine derailed and turned over across the track, and the wagons piled up on it.',
                'de' => 'Das Gewicht des Zuges ließ ihn trotz Alarmsignal des Lokführers, Bremsen und Gegendampf durchgehen; bei der Durchfahrt durch Mélas hinterließen die Bremsen eine leuchtende Spur. In einer engen Kurve am Fuß des Gefälles bei Le Teil entgleiste die Lokomotive, überschlug sich quer zum Gleis, und die Wagen türmten sich auf ihr.',
                'sources' => ['vdr-ales-pouzin', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été projeté par-dessus la locomotive et n\'a eu que des contusions. Le chauffeur, qui faisait son premier voyage, a été tué. Cinq corps ont été retirés des débris, et quatre blessés graves ont été transportés à l\'hospice de Montélimar.',
                'en' => 'The driver was thrown over the locomotive and had only bruises. The fireman, on his first trip, was killed. Five bodies were recovered from the wreckage, and four seriously injured men were taken to the Montélimar hospice.',
                'de' => 'Der Lokführer wurde über die Lokomotive geschleudert und erlitt nur Prellungen. Der Heizer, der seine erste Fahrt machte, kam ums Leben. Aus den Trümmern wurden fünf Leichen geborgen, und vier Schwerverletzte wurden ins Hospiz von Montélimar gebracht.',
                'sources' => ['vdr-ales-pouzin', 'wp-liste-1870'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Les parquets de Montélimar et de Privas ont ouvert une enquête sur place.',
                'en' => 'The public prosecutors of Montélimar and Privas opened an inquiry on site.',
                'de' => 'Die Staatsanwaltschaften von Montélimar und Privas leiteten vor Ort eine Untersuchung ein.',
                'sources' => ['vdr-ales-pouzin'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La date diffère : 7 juin 1876 selon Wikipédia ; la correspondance de Montélimar datée du 9 juin parle de « l\'accident d\'hier », soit le 8 juin. Le site Victimes du rail a identifié une sixième victime.',
                'en' => 'The date differs: 7 June 1876 according to Wikipedia; the Montélimar report dated 9 June speaks of "yesterday\'s accident", that is 8 June. The Victimes du rail website has identified a sixth victim.',
                'de' => 'Das Datum weicht ab: laut Wikipedia der 7. Juni 1876; der Bericht aus Montélimar vom 9. Juni spricht vom „gestrigen Unfall", also vom 8. Juni. Die Website Victimes du rail hat ein sechstes Opfer ermittelt.',
                'sources' => ['wp-liste-1870', 'vdr-ales-pouzin'],
            ],
        ],
    ],
    'corrections' => [],
];
