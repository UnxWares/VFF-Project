<?php

// Contexte sourcé du déraillement d'un train venant de Nantes entre Saint-Pierre-des-Corps et Tours, d'octobre 1899 (recherche du 21/11/2026).
return [
    'slug' => '1899-10-29-tours',
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
            'locator' => '1899',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1899',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 29 octobre 1899, d\'après Le Figaro du 30 octobre : entre la gare de Saint-Pierre-des-Corps et celle de Tours, vers 13 h, un train venant de Nantes déraille ; le mécanicien est tué, le chauffeur, le chef de train et quelques voyageurs sont blessés.',
        ],
        'zuid-limburger-1899-10-30' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De Zuid-Limburger du 30 octobre 1899',
            'publisher' => 'De Zuid-Limburger (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1899-10-30',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMCC01:048302118:mpeg21:a00020',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dépêche de Tours du 29 octobre : l\'après-midi, un train de voyageurs a déraillé entre Tours et Saint-Pierre-des-Corps ; la locomotive s\'est mise en travers, le tender s\'est renversé, le fourgon a été broyé ; le mécanicien a été tué sur le coup, le chef de train grièvement blessé, le chauffeur s\'est sauvé en sautant ; un train qui suivait a pu s\'arrêter à temps.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 29 octobre 1899 vers 13 h, un train de voyageurs venant de Nantes a déraillé entre Saint-Pierre-des-Corps et Tours. La locomotive s\'est mise en travers de la voie, le tender s\'est renversé et le fourgon a été broyé. Un train qui suivait a pu s\'arrêter à temps.',
                'en' => 'At about 1 pm on 29 October 1899, a passenger train from Nantes derailed between Saint-Pierre-des-Corps and Tours. The engine slewed across the track, the tender overturned and the van was crushed. A following train managed to stop in time.',
                'de' => 'Am 29. Oktober 1899 gegen 13 Uhr entgleiste zwischen Saint-Pierre-des-Corps und Tours ein Personenzug aus Nantes. Die Lokomotive stellte sich quer, der Tender stürzte um, der Packwagen wurde zertrümmert. Ein nachfolgender Zug konnte rechtzeitig halten.',
                'sources' => ['wp-liste-1890', 'zuid-limburger-1899-10-30'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué sur le coup et le chef de train grièvement blessé. Selon la liste de Wikipédia, quelques voyageurs ont aussi été blessés.',
                'en' => 'The driver was killed outright and the guard badly hurt. According to the Wikipedia list, a few passengers were also injured.',
                'de' => 'Der Lokomotivführer war sofort tot, der Zugführer wurde schwer verletzt. Nach der Wikipedia-Liste wurden auch einige Reisende verletzt.',
                'sources' => ['wp-liste-1890', 'zuid-limburger-1899-10-30'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le chauffeur est compté parmi les blessés par la liste de Wikipédia ; selon la dépêche de Tours, il s\'est sauvé en sautant de la machine.',
                'en' => 'The Wikipedia list counts the stoker among the injured; according to the Tours dispatch, he saved himself by jumping off the engine.',
                'de' => 'Die Wikipedia-Liste zählt den Heizer zu den Verletzten; nach der Meldung aus Tours rettete er sich durch einen Sprung von der Maschine.',
                'sources' => ['wp-liste-1890', 'zuid-limburger-1899-10-30'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'zuid-limburger-1899-10-30'],
            'fr' => 'Mécanicien tué selon la liste de Wikipédia (Le Figaro du 30 octobre 1899) et la dépêche de Tours du 29 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed according to the Wikipedia list (Le Figaro of 30 October 1899) and the Tours dispatch of the 29th; the record gave no number of deaths.',
            'de' => 'Lokomotivführer getötet nach der Wikipedia-Liste (Le Figaro vom 30. Oktober 1899) und der Meldung aus Tours vom 29.; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
