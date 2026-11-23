<?php

// Contexte sourcé de la collision de deux trains du RER C à Issy-les-Moulineaux du 24 décembre 1987 (recherche du 21/11/2026).
return [
    'slug' => '1987-12-24-issy-les-moulineaux',
    'checked_on' => '2026-11-21',
    'sources' => [
        'nrc-issy' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Veel gewonden bij treinongeluk in voorstad Parijs',
            'publisher' => 'NRC Handelsblad (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1987-12-24',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=KBNRC01:000029049:mpeg21:a0006',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
            'facts' => ['date_relative' => 'same_day', 'commune_insee' => '92040', 'deaths' => 1, 'deaths_provisional' => true],
        ],
        'wp-liste-1980' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1980',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1987',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1980&oldid=240619561#1987',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : nez à nez entre deux rames du RER C après franchissement d\'un signal d\'arrêt, défaut de signalisation probable, 1 mort et 13 blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 24 décembre 1987 à 8 h 45, dernier jour ouvré avant Noël, un train du RER C bondé de voyageurs a heurté à pleine vitesse, en gare d\'Issy-les-Moulineaux, au sud-ouest de Paris, un train vide qui y stationnait, selon NRC Handelsblad. La liste de Wikipédia décrit un nez à nez entre deux rames du RER C à la suite du franchissement d\'un signal d\'arrêt par un des conducteurs.',
                'en' => 'On Thursday 24 December 1987 at 8.45 am, the last working day before Christmas, a packed RER line C train hit at full speed, at Issy-les-Moulineaux station, south-west of Paris, an empty train standing there, according to NRC Handelsblad. The Wikipedia list describes a head-on collision between two RER C sets after one of the drivers passed a stop signal.',
                'de' => 'Am Donnerstag, dem 24. Dezember 1987, um 8.45 Uhr, dem letzten Arbeitstag vor Weihnachten, prallte ein überfüllter Zug der RER-Linie C im Bahnhof Issy-les-Moulineaux südwestlich von Paris mit voller Geschwindigkeit auf einen dort stehenden leeren Zug, nach NRC Handelsblad. Die Wikipedia-Liste beschreibt einen Frontalzusammenstoß zweier RER-C-Garnituren, nachdem einer der Fahrer ein Haltesignal überfahren hatte.',
                'sources' => ['nrc-issy', 'wp-liste-1980'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Trois voitures ont déraillé et des victimes étaient encore coincées dans les débris, d\'après le journal. Le plan rouge, qui s\'applique en cas d\'accident à nombreuses victimes, a été déclenché.',
                'en' => 'Three coaches derailed and some victims were still trapped in the wreckage, according to the newspaper. The "plan rouge", used when an accident causes many casualties, was triggered.',
                'de' => 'Drei Wagen entgleisten, und einige Opfer waren nach Angaben der Zeitung noch in den Trümmern eingeklemmt. Der „plan rouge“, der bei Unfällen mit vielen Verletzten gilt, wurde ausgelöst.',
                'sources' => ['nrc-issy'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon NRC Handelsblad, au moins une personne a été tuée et vingt-six autres blessées, dont une dans un état préoccupant.',
                'en' => 'According to NRC Handelsblad, at least one person was killed and twenty-six others injured, one of them in a worrying condition.',
                'de' => 'Nach NRC Handelsblad wurde mindestens eine Person getötet und sechsundzwanzig weitere verletzt, eine davon in besorgniserregendem Zustand.',
                'sources' => ['nrc-issy'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia indique qu\'après enquête, un défaut de signalisation serait la cause probable de l\'accident.',
                'en' => 'The Wikipedia list states that, after investigation, a signalling fault was the probable cause of the accident.',
                'de' => 'Die Wikipedia-Liste gibt an, dass nach der Untersuchung ein Signalisierungsfehler die wahrscheinliche Ursache des Unfalls gewesen sei.',
                'sources' => ['wp-liste-1980'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère : vingt-six selon NRC Handelsblad du jour même, treize selon la liste de Wikipédia, qui donne le bilan final d\'un mort et de treize blessés. La nature de la collision diffère aussi : un train qui en percute un à l\'arrêt (NRC Handelsblad) ou un nez à nez entre deux rames (liste de Wikipédia).',
                'en' => 'The number of injured differs: twenty-six according to NRC Handelsblad on the same day, thirteen according to the Wikipedia list, which gives the final toll as one dead and thirteen injured. The nature of the collision also differs: a train hitting one standing still (NRC Handelsblad) or a head-on collision between two sets (Wikipedia list).',
                'de' => 'Die Zahl der Verletzten weicht ab: sechsundzwanzig nach NRC Handelsblad vom selben Tag, dreizehn nach der Wikipedia-Liste, die die Schlussbilanz mit einem Toten und dreizehn Verletzten angibt. Auch die Art der Kollision weicht ab: ein Zug, der auf einen stehenden auffährt (NRC Handelsblad), oder ein Frontalzusammenstoß zweier Garnituren (Wikipedia-Liste).',
                'sources' => ['nrc-issy', 'wp-liste-1980'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
