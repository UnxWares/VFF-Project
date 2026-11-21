<?php

// Contexte sourcé de l'accident d'un train de marchandises contre un heurtoir en gare de Montereau, ligne de Paris à Marseille, de septembre 1910 (recherche du 21/11/2026).
return [
    'slug' => '1910-09-28-montereau',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1910' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1910',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1910&oldid=233727870#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 28 septembre 1910, d\'après Le Petit Parisien du 29 septembre : sur la ligne de Dijon à Paris, vers 2 h 30, à Montereau, un train de marchandises de Laroche au triage de Villeneuve-Saint-Georges, dont le mécanicien est désorienté par le brouillard, s\'engage trop vite sur la voie où il doit être garé et s\'écrase contre un heurtoir ; le chauffeur est tué, le mécanicien, le conducteur et le chef de train blessés.',
        ],
        'ddb-1910-09-29-morgenpost' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Auf der Station Montereau',
            'publisher' => 'Berliner Morgenpost (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-09-29',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/SBHKKEV5WO5L722BR66S2T2HBCK3PQ26?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève parmi les accidents français du 28 septembre : en gare de Montereau, un train de marchandises a déraillé ; le chauffeur tué, le mécanicien et un des chefs de train grièvement blessés.',
        ],
        'ddb-1910-09-29-dortmund' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Auf der Station Montereau',
            'publisher' => 'General-Anzeiger für Dortmund und die Provinz Westfalen (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-09-29',
            'locator' => 'p. 14',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/KICQ5TQIJSCFGIR2ICQWFSK6XUPLYWAX?issuepage=14',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même brève : déraillement d\'un train de marchandises en gare de Montereau, chauffeur tué, mécanicien et un chef de train grièvement blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 28 septembre 1910, vers 2 h 30, un train de marchandises allant de Laroche au triage de Villeneuve-Saint-Georges arrivait en gare de Montereau, sur la ligne de Dijon à Paris, dans le brouillard.',
                'en' => 'On 28 September 1910, at about 2.30 am, a goods train from Laroche to the Villeneuve-Saint-Georges yard was arriving at Montereau station on the Dijon to Paris line, in fog.',
                'de' => 'Am 28. September 1910 gegen 2.30 Uhr fuhr ein Güterzug von Laroche zum Rangierbahnhof Villeneuve-Saint-Georges bei Nebel in den Bahnhof Montereau an der Strecke Dijon-Paris ein.',
                'sources' => ['wp-liste-1910'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le mécanicien, désorienté par le brouillard, s\'est engagé trop vite sur la voie où le train devait être garé, et le train s\'est écrasé contre un heurtoir. La presse allemande parle d\'un déraillement.',
                'en' => 'According to the Wikipedia list the driver, disoriented by the fog, entered the siding where the train was to be stabled too fast, and the train smashed into a buffer stop. The German press speaks of a derailment.',
                'de' => 'Nach der Wikipedia-Liste fuhr der vom Nebel irritierte Lokführer zu schnell auf das Abstellgleis, und der Zug prallte gegen einen Prellbock. Die deutsche Presse spricht von einer Entgleisung.',
                'sources' => ['wp-liste-1910', 'ddb-1910-09-29-morgenpost'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur a été tué. Le mécanicien et un chef de train ont été grièvement blessés ; la liste de Wikipédia compte aussi le conducteur parmi les blessés.',
                'en' => 'The stoker was killed. The driver and a guard were seriously injured; the Wikipedia list also counts the conductor among the injured.',
                'de' => 'Der Heizer wurde getötet. Der Lokführer und ein Zugführer wurden schwer verletzt; die Wikipedia-Liste zählt auch den Schaffner zu den Verletzten.',
                'sources' => ['wp-liste-1910', 'ddb-1910-09-29-morgenpost', 'ddb-1910-09-29-dortmund'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1910', 'ddb-1910-09-29-morgenpost', 'ddb-1910-09-29-dortmund'],
            'fr' => 'Chauffeur tué selon la liste de Wikipédia (Le Petit Parisien du 29 septembre 1910) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Stoker killed according to the Wikipedia list (Le Petit Parisien of 29 September 1910) and the German press; the record gave no number of deaths.',
            'de' => 'Heizer getötet nach der Wikipedia-Liste (Le Petit Parisien vom 29. September 1910) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '77305',
            'sources' => ['wp-liste-1910', 'ddb-1910-09-29-morgenpost'],
            'fr' => 'Gare de Montereau, sur la ligne de Dijon à Paris, entre Laroche et Villeneuve-Saint-Georges, selon la liste de Wikipédia et la presse allemande : c\'est Montereau-Fault-Yonne (Seine-et-Marne) ; la fiche portait sur Montereau (Loiret), qui n\'est pas sur cette ligne.',
            'en' => 'Montereau station, on the Dijon to Paris line between Laroche and Villeneuve-Saint-Georges, according to the Wikipedia list and the German press: this is Montereau-Fault-Yonne (Seine-et-Marne); the record was set at Montereau (Loiret), which is not on that line.',
            'de' => 'Bahnhof Montereau an der Strecke Dijon-Paris zwischen Laroche und Villeneuve-Saint-Georges nach der Wikipedia-Liste und der deutschen Presse: das ist Montereau-Fault-Yonne (Seine-et-Marne); der Eintrag lag in Montereau (Loiret), das nicht an dieser Strecke liegt.',
        ],
    ],
];
