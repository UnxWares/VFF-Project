<?php

// Contexte sourcé de la collision d'un train de voyageurs et d'un train de marchandises en gare de Montréjeau - Gourdan-Polignan, ligne de Toulouse à Bayonne, de novembre 1905 (recherche du 21/11/2026).
return [
    'slug' => '1905-11-20-gourdan-polignan',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1905' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1905',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1905&oldid=233590727#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 20 novembre 1905 : vers 8 h, en gare de Montréjeau, sur la ligne Toulouse - Bayonne, le train de voyageurs arrivant de Toulouse est aiguillé par erreur sur une voie où stationne déjà un train de marchandises ; le mécanicien et le chauffeur sont tués, trois cheminots et dix-neuf voyageurs blessés.',
        ],
        'ddb-1905-11-21-volkszeitung' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Tarbes, 20. Nov.',
            'publisher' => 'Neue westfälische Volks-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1905-11-21',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/ZOQYV47MX7SDI6IATCIOOPV7SBRAGWIU?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Tarbes du 20 novembre : en gare de Montréjeau, collision d\'un train de voyageurs et d\'un train de marchandises ; un mécanicien et un chauffeur tués, 30 voyageurs blessés, la plupart légèrement.',
        ],
        'ddb-1905-11-21-harburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Tarbes, 20. Nov.',
            'publisher' => 'Harburger Anzeigen und Nachrichten (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1905-11-21',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/PQO3RICCALKZGODAK2TUAI2IIFV4IVSU?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Tarbes : collision en gare de Montréjeau, un mécanicien et un chauffeur tués, 30 voyageurs blessés, la plupart légèrement.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 20 novembre 1905, vers 8 h, le train de voyageurs venant de Toulouse a été aiguillé par erreur, en gare de Montréjeau, sur la ligne de Toulouse à Bayonne, vers une voie déjà occupée par un train de marchandises, qu\'il a heurté.',
                'en' => 'On 20 November 1905, at about 8 am, the passenger train from Toulouse was wrongly switched at Montréjeau station, on the Toulouse to Bayonne line, onto a track already occupied by a goods train, which it struck.',
                'de' => 'Am 20. November 1905 gegen 8 Uhr wurde der aus Toulouse kommende Personenzug im Bahnhof Montréjeau an der Strecke Toulouse-Bayonne irrtümlich auf ein von einem Güterzug besetztes Gleis geleitet und fuhr auf ihn auf.',
                'sources' => ['wp-liste-1905', 'ddb-1905-11-21-volkszeitung'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués. Des cheminots et de nombreux voyageurs ont été blessés.',
                'en' => 'The driver and the stoker were killed. Railwaymen and many passengers were injured.',
                'de' => 'Lokomotivführer und Heizer wurden getötet, Eisenbahner und zahlreiche Reisende verletzt.',
                'sources' => ['wp-liste-1905', 'ddb-1905-11-21-volkszeitung', 'ddb-1905-11-21-harburg'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia compte trois cheminots et dix-neuf voyageurs blessés, la dépêche de Tarbes trente voyageurs, la plupart légèrement.',
                'en' => 'The Wikipedia list counts three railwaymen and nineteen passengers injured, the Tarbes dispatch thirty passengers, most of them slightly.',
                'de' => 'Die Wikipedia-Liste zählt drei verletzte Eisenbahner und neunzehn verletzte Reisende, die Meldung aus Tarbes dreißig Reisende, die meisten leicht.',
                'sources' => ['wp-liste-1905', 'ddb-1905-11-21-volkszeitung'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1905', 'ddb-1905-11-21-volkszeitung'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
