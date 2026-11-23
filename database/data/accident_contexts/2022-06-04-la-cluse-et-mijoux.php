<?php

// Contexte sourcé de de la dérive et de la collision sur le train touristique du Coni'fer à La Cluse-et-Mijoux (Doubs) du 4 juin 2022 (recherche du 23/11/2026).
return [
    'slug' => '2022-06-04-la-cluse-et-mijoux',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2022-06-04-la-cluse-et-mijoux' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Accident du train touristique à vapeur du Coni’fer à La Cluse-et-Mijoux (25)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/la-cluse-et-mijoux-25-4-06-2022-derive-et-r337.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 4 juin 2022, un fourgon et une voiture-restaurant attelés sont partis en dérive et ont percuté une locomotive à vapeur à la gare de Fontaine Ronde, sur la ligne du train touristique du Coni\'fer. Les dégâts matériels sont minimes, mais sur la trentaine de passagers on compte 18 blessés légers et un blessé grave.',
                'en' => 'On 4 June 2022 a coupled luggage van and restaurant car ran away and struck a steam locomotive at Fontaine Ronde station, on the Coni\'fer tourist railway. The material damage is minimal, but of the thirty or so passengers there were 18 slightly injured and one seriously injured.',
                'de' => 'Am 4. Juni 2022 rollten ein gekuppelter Gepäckwagen und ein Speisewagen ab und prallten im Bahnhof Fontaine Ronde auf der Touristenbahn Coni\'fer auf eine Dampflokomotive. Der Sachschaden ist gering, doch von den etwa dreißig Fahrgästen wurden 18 leicht und einer schwer verletzt.',
                'sources' => ['bea-tt-2022-06-04-la-cluse-et-mijoux'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 9 juin 2023, sur le thème de la dérive et du rattrapage, avec des recommandations adressées au STRMTG et au CFTPV.',
                'en' => 'The BEA-TT published its final report on 9 June 2023, on the theme of runaway and catching up, with recommendations addressed to the STRMTG and the CFTPV.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 9. Juni 2023 zum Thema Abrollen und Auffahren, mit Empfehlungen an die STRMTG und den CFTPV.',
                'sources' => ['bea-tt-2022-06-04-la-cluse-et-mijoux'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 19,
            'sources' => ['bea-tt-2022-06-04-la-cluse-et-mijoux'],
            'fr' => 'Le BEA-TT compte 18 blessés légers et un blessé grave.',
            'en' => 'The BEA-TT counts 18 slightly injured and one seriously injured.',
            'de' => 'Die BEA-TT zählt 18 Leichtverletzte und einen Schwerverletzten.',
        ],
    ],
];
