<?php

// Contexte sourcé du tamponnement d'un train de marchandises en manœuvre par un train mixte venant de Nantes en gare d'Angers, de mai 1886 (recherche du 21/11/2026).
return [
    'slug' => '1886-05-05-angers',
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
            'locator' => '1886',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1886',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 5 mai 1886, d\'après Le Temps des 9 mai et 28 juin : en gare d\'Angers, à 20 h, après une erreur d\'aiguillage, un train de marchandises en manœuvre est heurté par un train mixte venant de Nantes, dont le mécanicien, le chauffeur et un graisseur sont tués ; le mécanicien de la machine tamponnée et un voyageur blessés ; un mois et demi plus tard, le tribunal correctionnel d\'Angers condamne à deux mois de prison un homme d\'équipe qui remplaçait un aiguilleur absent un court instant.',
        ],
        'ddb-1886-05-08-dueren' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 6. Mai',
            'publisher' => 'Dürener Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1886-05-08',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/4TL2HV5WJK6ZBBTUKUXC3NCPWJQSFMS2?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 6 mai : en gare d\'Angers, collision de deux trains ; trois agents tués, un agent et un voyageur grièvement blessés.',
        ],
        'ddb-1886-05-11-halle' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück',
            'publisher' => 'Hallesches Tageblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1886-05-11',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/3DAF7CZAKFZEJ36BO3FNFZ4EVBPV556T?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : le 5 mai, deux trains se sont heurtés à Angers ; trois agents tués, un agent et un voyageur grièvement blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 5 mai 1886, vers 20 h, en gare d\'Angers, un train mixte de marchandises et de voyageurs venant de Nantes a heurté un train de marchandises en manœuvre, à la suite d\'une erreur d\'aiguillage.',
                'en' => 'On 5 May 1886, at about 8 pm, at Angers station, a mixed goods and passenger train from Nantes ran into a goods train that was shunting, after a points error.',
                'de' => 'Am 5. Mai 1886 gegen 20 Uhr fuhr im Bahnhof Angers ein gemischter Güter- und Personenzug aus Nantes nach einem Weichenfehler auf einen rangierenden Güterzug.',
                'sources' => ['wp-liste-1880', 'ddb-1886-05-08-dueren', 'ddb-1886-05-11-halle'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois agents du train venant de Nantes ont été tués, le mécanicien, le chauffeur et un graisseur selon la liste de Wikipédia. Le mécanicien de la machine heurtée et un voyageur ont été grièvement blessés.',
                'en' => 'Three railwaymen on the train from Nantes were killed, the driver, the stoker and a greaser according to the Wikipedia list. The driver of the engine that was struck and one passenger were seriously injured.',
                'de' => 'Drei Bedienstete des Zuges aus Nantes wurden getötet, nach der Wikipedia-Liste Lokführer, Heizer und ein Schmierer. Der Lokführer der getroffenen Maschine und ein Reisender wurden schwer verletzt.',
                'sources' => ['wp-liste-1880', 'ddb-1886-05-08-dueren', 'ddb-1886-05-11-halle'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Un mois et demi plus tard, le tribunal correctionnel d\'Angers a jugé responsable un homme d\'équipe qui remplaçait un aiguilleur absent un court instant, et l\'a condamné à deux mois de prison.',
                'en' => 'Six weeks later the Angers criminal court held responsible a yardman who had stood in for a signalman absent for a short moment, and sentenced him to two months in prison.',
                'de' => 'Sechs Wochen später sah das Strafgericht von Angers einen Rangierarbeiter, der einen kurz abwesenden Weichensteller vertreten hatte, als verantwortlich an und verurteilte ihn zu zwei Monaten Gefängnis.',
                'sources' => ['wp-liste-1880'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 3,
            'sources' => ['wp-liste-1880', 'ddb-1886-05-08-dueren', 'ddb-1886-05-11-halle'],
            'fr' => 'Trois agents tués selon la liste de Wikipédia (Le Temps du 9 mai 1886) et la dépêche de Paris du 6 mai ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Three railwaymen killed according to the Wikipedia list (Le Temps of 9 May 1886) and the Paris dispatch of 6 May; the record gave no number of deaths.',
            'de' => 'Drei Bedienstete getötet nach der Wikipedia-Liste (Le Temps vom 9. Mai 1886) und der Pariser Depesche vom 6. Mai; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['wp-liste-1880', 'ddb-1886-05-08-dueren', 'ddb-1886-05-11-halle'],
            'fr' => 'Le mécanicien de la machine heurtée et un voyageur blessés selon la liste de Wikipédia et la dépêche de Paris du 6 mai ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'The driver of the engine struck and one passenger injured according to the Wikipedia list and the Paris dispatch of 6 May; the record gave no number of injured.',
            'de' => 'Der Lokführer der getroffenen Maschine und ein Reisender verletzt nach der Wikipedia-Liste und der Pariser Depesche vom 6. Mai; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
