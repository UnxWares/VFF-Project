<?php

// Contexte sourcé du déraillement du 15 novembre 1893 entre Étival et Raon-l'Étape (recherche du 21/11/2026).
return [
    'slug' => '1893-11-15-raon-letape',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '15 novembre 1893, Raon-l\'Étape',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1893',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Place le déraillement sur la ligne de Lunéville à Saint-Dié (Est), vers 10 h, entre les gares d\'Étival et de Raon-l\'Étape : train de voyageurs en double traction, un mort et onze blessés ; le mécanicien de la seconde machine, seul tué, est déclaré responsable d\'une vitesse excessive ; cite Le Matin et La Presse du 17 novembre 1893.',
        ],
        'avondpost-1893-11-17' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De avondpost du 17 novembre 1893',
            'publisher' => 'De avondpost (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1893-11-17',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB27:017841308:mpeg21:a00002',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Rubrique Nieuws van den dag ; brève sur le déraillement entre Eslival et Raon-l\'Étape.',
        ],
        'matin-1893-11-17' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 17 novembre 1893, page 2',
            'publisher' => 'Le Matin (numérisé sur Gallica)',
            'author' => null,
            'published' => '1893-11-17',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k555695f',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 15 novembre 1893 (date de la liste de Wikipédia), entre Étival (Eslival dans le journal néerlandais) et Raon-l\'Étape, un train a déraillé. D\'après la liste de Wikipédia, il s\'agissait d\'un train de voyageurs en double traction de la ligne de Lunéville à Saint-Dié, sur le réseau de l\'Est, vers 10 h.',
                'en' => 'On 15 November 1893 (the date given by the Wikipedia list), between Étival (Eslival in the Dutch newspaper) and Raon-l\'Étape, a train derailed. According to the Wikipedia list, it was a double-headed passenger train on the Lunéville to Saint-Dié line, on the Est network, at about 10 am.',
                'de' => 'Am 15. November 1893 (Datum der Wikipedia-Liste) entgleiste zwischen Étival (in der niederländischen Zeitung Eslival) und Raon-l\'Étape ein Zug. Nach der Wikipedia-Liste handelte es sich um einen doppelt bespannten Reisezug auf der Strecke Lunéville-Saint-Dié im Netz der Est, gegen 10 Uhr.',
                'sources' => ['avondpost-1893-11-17', 'wp-liste-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le journal néerlandais De avondpost du 17 novembre compte un voyageur tué et huit blessés. La liste de Wikipédia compte un mort, le mécanicien de la seconde machine, et onze blessés.',
                'en' => 'The Dutch newspaper De avondpost of 17 November counts one passenger killed and eight injured. The Wikipedia list counts one dead, the driver of the second locomotive, and eleven injured.',
                'de' => 'Die niederländische Zeitung De avondpost vom 17. November zählt einen getöteten Reisenden und acht Verletzte. Die Wikipedia-Liste zählt einen Toten, den Lokführer der zweiten Lokomotive, und elf Verletzte.',
                'sources' => ['avondpost-1893-11-17', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le mécanicien de la seconde machine a été déclaré responsable : il aurait adopté une vitesse excessive en poussant la première machine, alors que celle-ci aurait dû régler la marche. La liste s\'appuie notamment sur un rapport au Comité de l\'exploitation technique des chemins de fer.',
                'en' => 'According to the Wikipedia list, the driver of the second locomotive was held responsible: he is said to have run at excessive speed, pushing the first locomotive, which should have set the pace. The list relies in particular on a report to the technical operating committee of the railways.',
                'de' => 'Nach der Wikipedia-Liste wurde der Lokführer der zweiten Lokomotive für verantwortlich erklärt: Er sei mit überhöhter Geschwindigkeit gefahren und habe die erste Lokomotive geschoben, die das Tempo hätte bestimmen müssen. Die Liste stützt sich unter anderem auf einen Bericht an das technische Betriebskomitee der Eisenbahnen.',
                'sources' => ['wp-liste-1890'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère : huit pour De avondpost, onze pour la liste de Wikipédia. Le journal néerlandais parle d\'un voyageur tué, la liste du mécanicien de la seconde machine.',
                'en' => 'The number of injured differs: eight for De avondpost, eleven for the Wikipedia list. The Dutch newspaper speaks of a passenger killed, the list of the driver of the second locomotive.',
                'de' => 'Die Zahl der Verletzten weicht ab: acht nach De avondpost, elf nach der Wikipedia-Liste. Die niederländische Zeitung spricht von einem getöteten Reisenden, die Liste vom Lokführer der zweiten Lokomotive.',
                'sources' => ['avondpost-1893-11-17', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
