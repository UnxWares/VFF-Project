<?php

// Contexte sourcé de la collision de la station de Gonesse du 27 octobre 1867 (recherche du 21/11/2026).
return [
    'slug' => '1867-10-27-arnouville',
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
            'locator' => '27 octobre 1867',
            'url' => 'https://fr.wikipedia.org/wiki/Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Place l\'accident en gare de Gonesse (gare de Villiers-le-Bel - Gonesse - Arnouville), vers 7 h 15, avec trois morts et une dizaine de blessés ; cite Le Petit Journal du 30 octobre 1867.',
        ],
        'vdr-gonesse' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1867, La tragédie ferroviaire de Gonesse (95)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1867-la-tragedie-ferroviaire-de-gonesse-95/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Progrès du Nord du 31 octobre 1867 et Le Petit Journal du 30 octobre 1867.',
        ],
        'progres-du-nord-1867-10-31' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Progrès du Nord du 31 octobre 1867',
            'publisher' => 'Le Progrès du Nord',
            'author' => null,
            'published' => '1867-10-31',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k6665698n/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'petit-journal-1867-10-30' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 30 octobre 1867',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1867-10-30',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k589814r/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'La station de Gonesse, sur la ligne du Nord, est décrite en 1867 comme une petite station isolée dans la plaine, à environ deux kilomètres de Gonesse et de Villiers-le-Bel, qui ne comptait qu\'un restaurant-café. Un autre accident grave s\'y était produit un an plus tôt, et la presse la jugeait mal disposée, avec des voies qui se raccordaient mal.',
                'en' => 'The Gonesse station, on the Nord line, is described in 1867 as a small station isolated on the plain, about two kilometres from Gonesse and Villiers-le-Bel, with a single restaurant-café. Another serious accident had occurred there a year earlier, and the press judged its layout poor, with tracks that joined badly.',
                'de' => 'Die Station Gonesse an der Nordbahn wird 1867 als kleine, einsam in der Ebene gelegene Station etwa zwei Kilometer von Gonesse und Villiers-le-Bel beschrieben, mit nur einem Gasthaus. Ein Jahr zuvor hatte sich dort ein weiterer schwerer Unfall ereignet, und die Presse hielt die Anlage mit schlecht verbundenen Gleisen für mangelhaft.',
                'sources' => ['vdr-gonesse'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le dimanche 27 octobre 1867 à 7 h 15, l\'express de Calais, attendu à la gare du Nord, a heurté à une centaine de mètres de la station un train de banlieue qui se garait sur la voie de service. Les deux locomotives se sont brisées, les rails ont été arrachés sur trente mètres et trois wagons de première classe de l\'express ont été réduits en pièces.',
                'en' => 'On Sunday 27 October 1867 at 7.15 am, the Calais express, due at the Gare du Nord, struck, about a hundred metres from the station, a suburban train that was being put into the siding. Both locomotives broke up, the rails were torn up over thirty metres and three first-class carriages of the express were smashed.',
                'de' => 'Am Sonntag, dem 27. Oktober 1867 um 7.15 Uhr prallte der Calais-Schnellzug, der am Gare du Nord erwartet wurde, etwa hundert Meter vor der Station auf einen Vorortzug, der auf das Nebengleis gestellt wurde. Beide Lokomotiven zerbrachen, die Schienen wurden auf dreißig Metern herausgerissen, und drei Wagen erster Klasse des Schnellzugs wurden zertrümmert.',
                'sources' => ['vdr-gonesse', 'wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien de l\'express est mort quelques minutes après le choc. Les blessés ont été soignés au café-restaurant de la gare par les médecins des environs. Le personnel de conduite du train de banlieue a échappé à la mort en sautant sur la voie, et un vieillard retiré des débris d\'un wagon était indemne.',
                'en' => 'The express driver died a few minutes after the impact. The injured were treated at the station café-restaurant by doctors from the area. The crew of the suburban train escaped death by jumping onto the track, and an elderly man pulled from the debris of a carriage was unhurt.',
                'de' => 'Der Lokführer des Schnellzugs starb wenige Minuten nach dem Aufprall. Die Verletzten wurden im Bahnhofsgasthaus von Ärzten der Umgebung versorgt. Das Fahrpersonal des Vorortzugs entkam dem Tod durch einen Sprung auf das Gleis, und ein aus den Trümmern eines Wagens gezogener alter Mann blieb unverletzt.',
                'sources' => ['vdr-gonesse'],
            ],
            [
                'fr' => 'Le journal du 31 octobre évalue les morts à huit ; la liste de Wikipédia donne trois morts, le mécanicien et le chauffeur de l\'express et un voyageur anglais, et une dizaine de blessés.',
                'en' => 'The newspaper of 31 October puts the dead at eight; the Wikipedia list gives three dead, the express driver and fireman and an English passenger, and about ten injured.',
                'de' => 'Die Zeitung vom 31. Oktober schätzt die Zahl der Toten auf acht; die Wikipedia-Liste nennt drei Tote, Lokführer und Heizer des Schnellzugs sowie einen englischen Reisenden, und etwa zehn Verletzte.',
                'sources' => ['vdr-gonesse', 'wp-liste-1860'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le Progrès du Nord attribue l\'accident à la maladresse de l\'aiguilleur, qui faisait garer à vide le train de banlieue sur la voie de service ; selon Le Petit Journal, ce train, poussé un peu trop, s\'est avancé sur la voie directe.',
                'en' => 'Le Progrès du Nord blames the accident on the clumsiness of the pointsman, who was having the suburban train put empty into the siding; according to Le Petit Journal, that train, pushed a little too far, moved onto the through track.',
                'de' => 'Le Progrès du Nord führt das Unglück auf die Ungeschicklichkeit des Weichenstellers zurück, der den Vorortzug leer ins Nebengleis stellen ließ; laut Le Petit Journal rückte dieser Zug, etwas zu weit geschoben, auf das durchgehende Gleis vor.',
                'sources' => ['vdr-gonesse', 'wp-liste-1860'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le maire, le juge de paix, le procureur impérial, le commissaire de police et les ingénieurs de la Compagnie du Nord se sont rendus sur place pour une enquête. La voie a été déblayée et rouverte le jour même, et le journal du 31 octobre demande à la Compagnie de raser au plus vite la station.',
                'en' => 'The mayor, the justice of the peace, the imperial prosecutor, the police commissioner and the engineers of the Nord company went to the site for an inquiry. The line was cleared and reopened the same day, and the newspaper of 31 October calls on the company to demolish the station as soon as possible.',
                'de' => 'Der Bürgermeister, der Friedensrichter, der kaiserliche Staatsanwalt, der Polizeikommissar und die Ingenieure der Nordbahngesellschaft begaben sich zur Untersuchung an die Unglücksstelle. Die Strecke wurde geräumt und noch am selben Tag wieder freigegeben, und die Zeitung vom 31. Oktober fordert die Gesellschaft auf, die Station schnellstmöglich abzureißen.',
                'sources' => ['vdr-gonesse'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le numéro de l\'express est 40 dans Le Progrès du Nord et 12 dans Le Petit Journal. La vitesse est de 100 km/h dans le premier et de vingt lieues à l\'heure dans le second. Le nombre de morts est de huit dans Le Progrès du Nord et de trois selon la liste de Wikipédia. La fiche place l\'accident à Arnouville, alors que les journaux le situent à la station de Gonesse, en gare de Villiers-le-Bel - Gonesse - Arnouville selon la liste.',
                'en' => 'The express number is 40 in Le Progrès du Nord and 12 in Le Petit Journal. The speed is 100 km/h in the former and twenty leagues an hour in the latter. The number of dead is eight in Le Progrès du Nord and three according to the Wikipedia list. The record places the accident at Arnouville, whereas the newspapers locate it at Gonesse station, the Villiers-le-Bel - Gonesse - Arnouville station according to the list.',
                'de' => 'Die Nummer des Schnellzugs lautet im Progrès du Nord 40 und im Petit Journal 12. Die Geschwindigkeit beträgt in der ersten Zeitung 100 km/h, in der zweiten zwanzig Meilen (Lieues) pro Stunde. Die Zahl der Toten beträgt im Progrès du Nord acht und laut der Wikipedia-Liste drei. Der Eintrag verortet das Unglück in Arnouville, während die Zeitungen es an der Station Gonesse verorten, laut der Liste am Bahnhof Villiers-le-Bel - Gonesse - Arnouville.',
                'sources' => ['vdr-gonesse', 'wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [],
];
