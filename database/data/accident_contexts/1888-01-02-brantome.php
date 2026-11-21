<?php

// Contexte sourcé du déraillement d'un train de ballast sur la ligne en construction de Périgueux à Brantôme, le 2 janvier 1888 (recherche du 21/11/2026).
return [
    'slug' => '1888-01-02-brantome',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '2 janvier 1888',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1888',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Train de ballast de la ligne en construction de Périgueux à Brantôme (Société des chemins de fer du Périgord), déraillement dans une courbe, 2 morts et 4 blessés ; cite Le Petit Journal du 5 janvier 1888 et La Croix du 6 janvier 1888.',
        ],
        'vdr-brantome' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1888, Vitesse excessive sur la voie en construction Périgueux - Brantôme (24)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1888-vitesse-excessive-sur-la-voie-en-construction-perigueux-brantome/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Gazette du Centre du 5 janvier 1888.',
        ],
        'gazette-centre-1888-01-05' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Gazette du Centre du 5 janvier 1888',
            'publisher' => 'La Gazette du Centre (numérisée sur Gallica)',
            'author' => null,
            'published' => '1888-01-05',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bd6t5440840m/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le lundi 2 janvier 1888, vers quatre heures du soir, la machine n° 2 des tramways du Périgord, revenant de Brantôme et poussant devant elle trois wagons de ballast chargés de 10 000 kilos chacun selon le journal, descendait à très grande vitesse une rampe jugée parmi les plus rapides de la contrée, sur la ligne en construction de Périgueux à Brantôme.',
                'en' => 'On Monday 2 January 1888, at about four in the afternoon, engine no. 2 of the Périgord tramways, returning from Brantôme and pushing three ballast wagons ahead of it, loaded with 10,000 kilos according to the newspaper, was running at very high speed down a gradient reckoned among the steepest in the area, on the line under construction from Périgueux to Brantôme.',
                'de' => 'Am Montag, dem 2. Januar 1888, gegen vier Uhr nachmittags fuhr die Maschine Nr. 2 der Périgord-Straßenbahnen, aus Brantôme zurückkehrend und drei Schotterwagen vor sich herschiebend, die laut der Zeitung mit je 10 000 Kilo beladen waren, mit sehr hoher Geschwindigkeit ein Gefälle hinab, das als eines der steilsten der Gegend gilt, auf der im Bau befindlichen Strecke von Périgueux nach Brantôme.',
                'sources' => ['vdr-brantome', 'wp-liste-1880'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Dans le coude de la route, entre les bornes 16 et 17 près du village de Versac, le wagon de tête a déraillé et tout le train a été précipité hors des rails, les wagons étant mis en miettes. Seize ouvriers ont été projetés sur la route et sur les talus.',
                'en' => 'At the bend in the road, between posts 16 and 17 near the village of Versac, the leading wagon derailed and the whole train was thrown off the rails, the wagons being smashed to pieces. Sixteen workers were thrown onto the road and the slopes.',
                'de' => 'In der Straßenkurve zwischen den Steinen 16 und 17 nahe dem Dorf Versac entgleiste der vorderste Wagen, und der ganze Zug wurde aus den Schienen geschleudert, wobei die Wagen in Stücke gingen. Sechzehn Arbeiter wurden auf die Straße und die Böschungen geschleudert.',
                'sources' => ['vdr-brantome'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux terrassiers ont été tués, l\'un d\'eux ayant le crâne fracassé, tous deux mariés et pères de deux enfants, et quatre autres ont été blessés, dont trois gravement. L\'enterrement a eu lieu le mercredi à Brantôme.',
                'en' => 'Two earthworkers were killed, one of them with a smashed skull, both married with two children, and four others were injured, three of them seriously. The funeral took place on the Wednesday at Brantôme.',
                'de' => 'Zwei Erdarbeiter wurden getötet, einer mit zertrümmertem Schädel, beide verheiratet und Väter von zwei Kindern, und vier weitere wurden verletzt, drei davon schwer. Die Beerdigung fand am Mittwoch in Brantôme statt.',
                'sources' => ['vdr-brantome', 'wp-liste-1880'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le journal attribue l\'accident à l\'allure trop précipitée de la machine à la rampe 16, le mécanicien n\'ayant pu l\'arrêter bien qu\'il ait renversé la vapeur.',
                'en' => 'The newspaper puts the accident down to the machine\'s too rapid pace on gradient 16, the driver having been unable to stop it although he reversed the steam.',
                'de' => 'Die Zeitung führt das Unglück auf das zu schnelle Tempo der Maschine am Gefälle 16 zurück; der Lokführer konnte sie trotz Gegendampf nicht anhalten.',
                'sources' => ['vdr-brantome'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Les ingénieurs, le chef d\'exploitation des tramways et le procureur de la République se sont rendus sur les lieux le soir même ; le mardi à 7 h, le procureur et le juge d\'instruction sont revenus pour ouvrir l\'enquête.',
                'en' => 'The engineers, the tramway operations manager and the public prosecutor went to the scene the same evening; on Tuesday at 7 am the prosecutor and the examining magistrate returned to open the inquiry.',
                'de' => 'Die Ingenieure, der Betriebsleiter der Straßenbahnen und der Staatsanwalt begaben sich noch am selben Abend zur Unglücksstelle; am Dienstag um 7 Uhr kehrten der Staatsanwalt und der Untersuchungsrichter zurück, um die Untersuchung zu eröffnen.',
                'sources' => ['vdr-brantome'],
            ],
        ],
    ],
    'corrections' => [],
];
