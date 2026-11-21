<?php

// Contexte sourcé de la collision d'un train de l'Ouest et d'un train du Nord au raccordement de Pontoise, de septembre 1892 (recherche du 21/11/2026).
return [
    'slug' => '1892-09-10-pontoise',
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
            'locator' => '1892',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1892',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 10 septembre 1892, d\'après La Presse du 12 septembre : près de Pontoise, au raccordement avec la ligne de Paris à Mantes par Conflans-Sainte-Honorine, un train de voyageurs de la Compagnie de l\'Ouest est pris en écharpe par un train du Nord, dont la locomotive dévale un remblai, tuant son chauffeur ; deux hommes d\'équipe et un conducteur blessés.',
        ],
        'ddb-1892-09-14-dresden' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der gestrige Tag hat mehrere schwere Eisenbahn-Unfälle gebracht',
            'publisher' => 'Dresdner Journal (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1892-09-14',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/3WODU2FI3XOFFJXTU44CP6CCFEXTYOJT?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Correspondance de Paris (même texte dans le Schwäbischer Merkur et le Hamburgischer Correspondent du 14 septembre) : « hier », près de Pontoise, un train de voyageurs venant de Paris a heurté un autre train qui allait de Beaumont vers Paris ; aucun voyageur blessé, le chauffeur de ce dernier train tué et deux autres agents blessés.',
        ],
        'ddb-1892-09-14-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris 12. Sept.',
            'publisher' => 'Schwäbischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1892-09-14',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/73EBPJQ6UIUBIRG7XEMKDO52BR7ZWJKM?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Correspondance de Paris du 12 septembre : même récit de la collision près de Pontoise, chauffeur tué, deux agents blessés, voyageurs indemnes.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Près de Pontoise, au raccordement avec la ligne de Paris à Mantes par Conflans-Sainte-Honorine, un train de voyageurs de la Compagnie de l\'Ouest venant de Paris a été pris en écharpe par un train du Nord qui allait de Beaumont vers Paris. La locomotive du train du Nord a dévalé un remblai.',
                'en' => 'Near Pontoise, at the junction with the Paris to Mantes line via Conflans-Sainte-Honorine, an Ouest company passenger train from Paris was struck side-on by a Nord train running from Beaumont towards Paris. The Nord engine ran down an embankment.',
                'de' => 'Bei Pontoise, an der Verbindung mit der Strecke Paris-Mantes über Conflans-Sainte-Honorine, wurde ein aus Paris kommender Personenzug der Westbahn von einem Zug der Nordbahn, der von Beaumont nach Paris fuhr, seitlich gerammt. Die Lokomotive des Nordbahnzugs stürzte eine Böschung hinab.',
                'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden', 'ddb-1892-09-14-merkur'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur du train du Nord a été tué. Les voyageurs n\'ont pas été blessés ; deux agents ont été blessés selon la presse allemande, deux hommes d\'équipe et un conducteur selon la liste de Wikipédia.',
                'en' => 'The stoker of the Nord train was killed. The passengers were unhurt; two railwaymen were injured according to the German press, two yardmen and a conductor according to the Wikipedia list.',
                'de' => 'Der Heizer des Nordbahnzugs wurde getötet. Die Reisenden blieben unverletzt; nach der deutschen Presse wurden zwei Zugbeamte verletzt, nach der Wikipedia-Liste zwei Rangierarbeiter und ein Zugbegleiter.',
                'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden', 'ddb-1892-09-14-merkur'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La correspondance de Paris du 12 septembre place l\'accident « hier », soit le 11 septembre ; la liste de Wikipédia le date du 10 septembre. Deux ou trois blessés selon les sources.',
                'en' => 'The Paris report of 12 September places the accident "yesterday", that is 11 September; the Wikipedia list dates it 10 September. Two or three injured depending on the source.',
                'de' => 'Der Pariser Bericht vom 12. September setzt den Unfall auf „gestern“, also den 11. September; die Wikipedia-Liste datiert ihn auf den 10. September. Je nach Quelle zwei oder drei Verletzte.',
                'sources' => ['ddb-1892-09-14-merkur', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden', 'ddb-1892-09-14-merkur'],
            'fr' => 'Chauffeur du train du Nord tué selon la liste de Wikipédia (La Presse du 12 septembre 1892) et la correspondance de Paris ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Stoker of the Nord train killed according to the Wikipedia list (La Presse of 12 September 1892) and the Paris report; the record gave no number of deaths.',
            'de' => 'Heizer des Nordbahnzugs getötet nach der Wikipedia-Liste (La Presse vom 12. September 1892) und dem Pariser Bericht; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
