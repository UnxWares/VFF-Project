<?php

// Contexte sourcé de l'accident du 11 décembre 1919 à Saint-Barthélemy (Marseille), d'un train de Marseille vers les
// Alpes par Aix-en-Provence (recherche du 21/11/2026).
return [
    'slug' => '1919-12-11-marseille',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre, 11 décembre 1919',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de décembre 1919 (Gallica).',
        ],
        'maasbode-1919-12-13' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Verklaringen van Renner',
            'publisher' => 'De Maasbode (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1919-12-13',
            'locator' => 'avondeditie',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB04:000192465:mpeg21:a0010:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Brève de la chronique de France : « ces jours-ci » un accident sur la ligne de Marseille vers les Alpes, un train de voyageurs déraille et heurte un train de marchandises à l\'arrêt, cinq morts dont le mécanicien et le chauffeur, quelques blessés légers.',
        ],
        'utrechtsche-1919-12-15' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Korte berichten',
            'publisher' => 'Utrechtsche courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1919-12-15',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMUTRA04:253310143:mpeg21:a00066:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Même dépêche reprise sous le mot « Botsing » : accident sur la ligne de Marseille vers les Alpes, cinq morts dont le mécanicien et le chauffeur.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 11 décembre 1919, le train de Marseille aux Alpes par Aix-en-Provence a quitté la gare de Marseille-Saint-Charles à 4 h 45. Peu après, à la station de Saint-Barthélemy, où se séparent les lignes vers Paris et vers Grenoble, il s\'est engagé sur une voie de garage.',
                'en' => 'On 11 December 1919, the train from Marseille to the Alps via Aix-en-Provence left Marseille-Saint-Charles station at 4.45 am. Shortly afterwards, at Saint-Barthélemy station, where the lines to Paris and Grenoble divide, it ran onto a siding.',
                'de' => 'Am 11. Dezember 1919 verließ der Zug von Marseille in die Alpen über Aix-en-Provence um 4.45 Uhr den Bahnhof Marseille-Saint-Charles. Kurz darauf fuhr er im Bahnhof Saint-Barthélemy, wo sich die Strecken nach Paris und nach Grenoble trennen, auf ein Abstellgleis.',
                'sources' => ['wp-liste-1919', 'maasbode-1919-12-13', 'utrechtsche-1919-12-15'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Il s\'est écrasé contre un heurtoir adossé à la pile d\'un pont. L\'accident a fait six morts, dont le mécanicien et le chauffeur, et six blessés.',
                'en' => 'It crashed into a buffer stop set against a bridge pier. The accident killed six people, including the driver and the fireman, and injured six.',
                'de' => 'Er prallte gegen einen Prellbock, der an einem Brückenpfeiler stand. Der Unfall forderte sechs Tote, darunter Lokführer und Heizer, und sechs Verletzte.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Deux erreurs se sont ajoutées : l\'aiguilleur n\'avait pas ouvert à temps la voie vers Aix, et le mécanicien, gêné par la vapeur et la fumée de sa machine, n\'a pas vu le signal fermé.',
                'en' => 'Two errors combined: the signalman had not set the route towards Aix in time, and the driver, hampered by steam and smoke from his engine, did not see the signal at danger.',
                'de' => 'Zwei Fehler kamen zusammen: Der Weichenwärter hatte den Fahrweg nach Aix nicht rechtzeitig eingestellt, und der Lokführer, durch Dampf und Rauch seiner Maschine behindert, sah das Halt zeigende Signal nicht.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Deux journaux néerlandais des 13 et 15 décembre 1919 confirment un accident survenu « ces jours-ci » sur la ligne de Marseille vers les Alpes, avec la mort du mécanicien et du chauffeur, mais ils ne nomment pas la station. Ils comptent cinq morts, contre six dans la liste de Wikipédia, et décrivent un train de voyageurs qui déraille et heurte un train de marchandises à l\'arrêt, là où la liste parle d\'un heurtoir. Le bilan de la liste est gardé ; le lieu précis (Saint-Barthélemy, Marseille) ne repose toujours que sur la liste.',
                'en' => 'Two Dutch newspapers of 13 and 15 December 1919 confirm an accident "these days" on the Marseille to Alps line, with the death of the driver and the fireman, but they do not name the station. They count five dead, against six in the Wikipedia list, and describe a passenger train that derails and hits a stationary goods train, where the list speaks of a buffer stop. The list\'s toll is kept; the precise place (Saint-Barthélemy, Marseille) still rests on the list alone.',
                'de' => 'Zwei niederländische Zeitungen vom 13. und 15. Dezember 1919 bestätigen einen Unfall „dieser Tage“ auf der Strecke von Marseille in die Alpen, bei dem Lokführer und Heizer starben, nennen aber den Bahnhof nicht. Sie zählen fünf Tote gegenüber sechs in der Wikipedia-Liste und beschreiben einen Personenzug, der entgleist und gegen einen stehenden Güterzug prallt, wo die Liste von einem Prellbock spricht. Die Zahl der Liste wird beibehalten; der genaue Ort (Saint-Barthélemy, Marseille) beruht weiterhin nur auf der Liste.',
                'sources' => ['maasbode-1919-12-13', 'utrechtsche-1919-12-15', 'wp-liste-1919'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '13055',
            'single_source' => true,
            'sources' => ['wp-liste-1919'],
            'fr' => 'Aix-en-Provence était sur l\'itinéraire du train ; l\'accident a eu lieu à la station de Saint-Barthélemy, à Marseille, peu après le départ de Saint-Charles.',
            'en' => 'Aix-en-Provence was on the train\'s route; the accident happened at Saint-Barthélemy station in Marseille, shortly after leaving Saint-Charles.',
            'de' => 'Aix-en-Provence lag auf dem Laufweg des Zuges; der Unfall ereignete sich im Bahnhof Saint-Barthélemy in Marseille, kurz nach der Abfahrt in Saint-Charles.',
        ],
    ],
];
