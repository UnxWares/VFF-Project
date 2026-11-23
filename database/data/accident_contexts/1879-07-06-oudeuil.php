<?php

// Contexte sourcé du déraillement près de Beauvais du 6 juillet 1879 (recherche du 21/11/2026).
return [
    'slug' => '1879-07-06-oudeuil',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1879',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1879',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Figaro du 9 juillet 1879 : sur la ligne de Beauvais à Abancourt, un train de voyageurs venant d\'Amiens déraille vers 13 h, peu avant Beauvais, près de la gare d\'Oudeuil, par suite d\'une rupture d\'essieu de la locomotive ; le mécanicien est tué, le chauffeur et le chef de train grièvement blessés, un seul des 60 voyageurs contusionné.',
        ],
        'delpher-1879-07-09-vaderland' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het vaderland du 9 juillet 1879',
            'publisher' => 'Het vaderland  (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1879-07-09',
            'locator' => 'p. 1',
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB23:001415008:mpeg21:a00004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : avant-hier, le train de Beauvais à Paris a déraillé en pleine vitesse près de Crèvecœur ; le mécanicien est mort sur le coup, le chauffeur grièvement blessé, quelques voyageurs contusionnés.',
        ],
        'delpher-1879-07-11-rotterdamsche' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Rotterdamsche courant du 11 juillet 1879',
            'publisher' => 'Rotterdamsche courant  (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1879-07-11',
            'locator' => 'p. 1',
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:011005963:mpeg21:a0003',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève, rubrique « Frankrijk » : sur le chemin de fer du Nord, le train d\'Amiens a déraillé près de Beauvais ; le mécanicien est tué, un conducteur et un voyageur blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 6 juillet 1879, vers 13 h, un train de voyageurs venant d\'Amiens déraille peu avant Beauvais (Oise), sur le réseau du Nord.',
                'en' => 'On 6 July 1879, at about 1 pm, a passenger train coming from Amiens derailed shortly before Beauvais (Oise), on the Northern Railway network.',
                'de' => 'Am 6. Juli 1879 entgleiste gegen 13 Uhr kurz vor Beauvais (Oise) auf dem Netz der Nordbahn ein aus Amiens kommender Personenzug.',
                'sources' => ['wp-liste-1870', 'delpher-1879-07-11-rotterdamsche'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, un essieu de la locomotive s\'est rompu. Le train roulait en pleine vitesse, selon la presse néerlandaise.',
                'en' => 'According to the Wikipedia list, an axle of the locomotive broke. The train was running at full speed, according to the Dutch press.',
                'de' => 'Nach der Wikipedia-Liste brach eine Achse der Lokomotive. Der Zug fuhr nach der niederländischen Presse mit voller Geschwindigkeit.',
                'sources' => ['wp-liste-1870', 'delpher-1879-07-09-vaderland'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien est tué sur le coup. Le chauffeur est grièvement blessé ; la liste ajoute le chef de train, et un voyageur contusionné sur une soixantaine.',
                'en' => 'The driver was killed outright. The stoker was seriously injured; the list adds the guard, and one bruised passenger out of about sixty.',
                'de' => 'Der Lokomotivführer war sofort tot. Der Heizer wurde schwer verletzt; die Liste nennt zudem den Zugführer und einen von rund sechzig Reisenden mit Prellungen.',
                'sources' => ['wp-liste-1870', 'delpher-1879-07-09-vaderland', 'delpher-1879-07-11-rotterdamsche'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu diffère : près de la gare d\'Oudeuil, sur la ligne de Beauvais à Abancourt, selon la liste de Wikipédia ; près de Crèvecœur (Het vaderland) ou de Beauvais (Rotterdamsche courant) selon la presse néerlandaise, qui parle aussi d\'un train de Beauvais à Paris. Le nombre de voyageurs blessés va d\'un seul à « quelques-uns ». Het vaderland du 9 juillet place enfin l\'accident « avant-hier », soit le 7 juillet, au lieu du 6.',
                'en' => 'The place differs: near Oudeuil station, on the Beauvais to Abancourt line, according to the Wikipedia list; near Crèvecœur (Het vaderland) or Beauvais (Rotterdamsche courant) according to the Dutch press, which also speaks of a Beauvais to Paris train. The number of injured passengers ranges from one to "a few". Lastly, Het vaderland of 9 July dates the accident "the day before yesterday", that is 7 July, not the 6th.',
                'de' => 'Der Ort weicht ab: nahe dem Bahnhof Oudeuil an der Strecke Beauvais-Abancourt nach der Wikipedia-Liste, bei Crèvecœur (Het vaderland) oder bei Beauvais (Rotterdamsche courant) nach der niederländischen Presse, die zudem von einem Zug von Beauvais nach Paris spricht. Die Zahl der verletzten Reisenden reicht von einem bis zu „einigen“. Het vaderland vom 9. Juli datiert den Unfall schließlich auf „vorgestern“, also den 7. statt den 6. Juli.',
                'sources' => ['wp-liste-1870', 'delpher-1879-07-09-vaderland', 'delpher-1879-07-11-rotterdamsche'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1870', 'delpher-1879-07-09-vaderland'],
            'fr' => 'Un mort, le mécanicien, selon la liste de Wikipédia et la presse néerlandaise de juillet 1879 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One dead, the driver, according to the Wikipedia list and the Dutch press of July 1879; the record gave no number of deaths.',
            'de' => 'Ein Toter, der Lokomotivführer, nach der Wikipedia-Liste und der niederländischen Presse vom Juli 1879; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
