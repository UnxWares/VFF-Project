<?php

// Contexte sourcé de la collision en gare d'Ailly-sur-Somme, ligne d'Amiens à Calais, de juillet 1906 (recherche du 21/11/2026).
return [
    'slug' => '1906-07-11-ailly-sur-somme',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1906' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1906',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1906&oldid=233590740#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 11 juillet 1906, d\'après Le Temps et Le Petit Journal du 13 juillet : à 18 h 45, en gare d\'Ailly-sur-Somme, le rapide Calais - Bâle heurte deux wagons d\'un train de marchandises en manœuvre sur les voies principales ; la marquise s\'effondre, le bâtiment voyageurs est éventré, la machine, le tender et les voitures de tête se couchent 150 m plus loin et font s\'effondrer une maison inhabitée ; six blessés, dont cinq voyageurs.',
        ],
        'ddb-1906-07-12-national' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunfall',
            'publisher' => 'National-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1906-07-12',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/V5PDCPPADZZ6QB2E2G47KVSVNLW62FUJ?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme d\'Amiens du 11 juillet : le train Calais - Bâle est entré en gare d\'Ailly-sur-Somme dans un train de marchandises ; le mécanicien et cinq voyageurs blessés, la locomotive et plusieurs wagons du train de marchandises détruits, le bâtiment de la gare et une maison voisine fortement endommagés.',
        ],
        'ddb-1906-07-13-hamburger-echo' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Amiens, 12. Juli',
            'publisher' => 'Hamburger Echo, édition du soir (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1906-07-13',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/HISH5WCJAX3JEODZWTASKNXOMY36T7WU?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche d\'Amiens, reprise par une dizaine de journaux du 12 et du 13 juillet : le train Calais - Bâle contre un train de marchandises en gare d\'Ailly-sur-Somme, le mécanicien et cinq voyageurs blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 11 juillet 1906, vers 18 h 45, le rapide Calais - Bâle traversait la gare d\'Ailly-sur-Somme, sur la ligne de Calais à Amiens. Un train de marchandises manœuvrait alors sur les voies principales.',
                'en' => 'On 11 July 1906, at about 6.45 pm, the Calais to Basel express was passing through Ailly-sur-Somme station on the Calais to Amiens line. A goods train was shunting on the main tracks at the time.',
                'de' => 'Am 11. Juli 1906 gegen 18.45 Uhr durchfuhr der Schnellzug Calais-Basel den Bahnhof Ailly-sur-Somme an der Strecke Calais-Amiens. Zur selben Zeit rangierte ein Güterzug auf den Hauptgleisen.',
                'sources' => ['wp-liste-1906', 'ddb-1906-07-12-national'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le rapide a heurté le train de marchandises, dont la locomotive et plusieurs wagons ont été détruits. Le bâtiment de la gare et une maison voisine ont été fortement endommagés. Selon la liste de Wikipédia, la marquise s\'est effondrée, et la machine, le tender et les voitures de tête du rapide se sont couchés 150 mètres plus loin.',
                'en' => 'The express struck the goods train, whose engine and several wagons were wrecked. The station building and a neighbouring house were badly damaged. According to the Wikipedia list the platform canopy collapsed, and the express engine, tender and leading coaches overturned 150 metres further on.',
                'de' => 'Der Schnellzug fuhr in den Güterzug, dessen Lokomotive und mehrere Wagen zertrümmert wurden. Das Bahnhofsgebäude und ein Nachbarhaus wurden stark beschädigt. Nach der Wikipedia-Liste stürzte das Bahnsteigdach ein, und Lokomotive, Tender und vordere Wagen des Schnellzugs kippten 150 Meter weiter um.',
                'sources' => ['ddb-1906-07-12-national', 'ddb-1906-07-13-hamburger-echo', 'wp-liste-1906'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Six personnes ont été blessées : le mécanicien du rapide et cinq voyageurs. Aucune source ne mentionne de mort.',
                'en' => 'Six people were injured: the driver of the express and five passengers. No source mentions any death.',
                'de' => 'Sechs Personen wurden verletzt: der Lokführer des Schnellzugs und fünf Reisende. Keine Quelle erwähnt Tote.',
                'sources' => ['ddb-1906-07-12-national', 'ddb-1906-07-13-hamburger-echo', 'wp-liste-1906'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1906', 'ddb-1906-07-12-national'],
            'fr' => 'Le rapide a heurté un train de marchandises selon la liste de Wikipédia et le télégramme d\'Amiens du 11 juillet 1906 ; la fiche parlait d\'un ouvrage qui cède.',
            'en' => 'The express struck a goods train according to the Wikipedia list and the Amiens telegram of 11 July 1906; the record described a structure failure.',
            'de' => 'Der Schnellzug fuhr nach der Wikipedia-Liste und dem Telegramm aus Amiens vom 11. Juli 1906 in einen Güterzug; der Eintrag sprach vom Versagen eines Bauwerks.',
        ],
        [
            'field' => 'deaths',
            'value' => 0,
            'sources' => ['wp-liste-1906', 'ddb-1906-07-12-national', 'ddb-1906-07-13-hamburger-echo'],
            'fr' => 'Six blessés et aucun mort selon la liste de Wikipédia et les dépêches d\'Amiens ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Six injured and no deaths according to the Wikipedia list and the Amiens dispatches; the record gave no number of deaths.',
            'de' => 'Sechs Verletzte und keine Toten nach der Wikipedia-Liste und den Depeschen aus Amiens; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
