<?php

// Contexte sourcé de la collision de deux trains de voyageurs sur le pont de l'Arve à Étrembières, près d'Annemasse, de septembre 1904 (recherche du 21/11/2026).
return [
    'slug' => '1904-09-06-etrembieres',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1904' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1904',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1904&oldid=240831504#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 6 septembre 1904, d\'après Le Petit Parisien : vers 19 h, à Étrembières, près d\'Annemasse, l\'express 1733 venant de Saint-Gervais et l\'omnibus 1616 pour Bellegarde, en double traction, se heurtent au bout du pont sur l\'Arve ; malgré la faible vitesse, le mécanicien et le chauffeur de la locomotive de tête du train pour Bellegarde sont tués, deux autres cheminots et une vingtaine de voyageurs blessés.',
        ],
        'ddb-1904-09-08-reichsanzeiger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankfurt a. M., 7. September (W. T. B.)',
            'publisher' => 'Deutscher Reichsanzeiger und Preußischer Staatsanzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1904-09-08',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/QFUCTKQ5CGESG7MXOXAT4275KN4LNRXB?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche W. T. B. d\'après la Frankfurter Zeitung : hier soir, sur le pont d\'Étrembières en Haute-Savoie, deux trains se sont heurtés ; selon la direction du PLM, deux mécaniciens sont morts ; le nombre de voyageurs blessés n\'est pas encore connu.',
        ],
        'ddb-1904-09-09-grossenhain' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Auf der Brücke von Etrembieres',
            'publisher' => 'Großenhainer Tageblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1904-09-09',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/EFOXWTXKPDUTHV5V5GD2CHQKK3ZIJJQ6?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle : collision de deux trains sur le pont d\'Étrembières, deux mécaniciens morts selon la direction du PLM, nombre de blessés encore inconnu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 6 septembre 1904, vers 19 h, l\'express de Saint-Gervais-Le Fayet à Annemasse et l\'omnibus d\'Annemasse à Bellegarde, tiré par deux locomotives, circulaient près d\'Annemasse, à Étrembières.',
                'en' => 'On 6 September 1904, at about 7 pm, the Saint-Gervais-Le Fayet to Annemasse express and the Annemasse to Bellegarde stopping train, double-headed, were running near Annemasse at Étrembières.',
                'de' => 'Am 6. September 1904 gegen 19 Uhr fuhren der Expresszug Saint-Gervais-Le Fayet-Annemasse und der doppelt bespannte Personenzug Annemasse-Bellegarde bei Annemasse in Étrembières.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les deux trains se sont heurtés au bout du pont sur l\'Arve qui mène à la bifurcation des lignes de Bellegarde et de Saint-Gervais, à faible vitesse selon la liste de Wikipédia.',
                'en' => 'The two trains collided at the end of the bridge over the Arve leading to the junction of the Bellegarde and Saint-Gervais lines, at low speed according to the Wikipedia list.',
                'de' => 'Die beiden Züge stießen am Ende der Arvebrücke zusammen, die zur Abzweigung der Strecken nach Bellegarde und Saint-Gervais führt, nach der Wikipedia-Liste bei geringer Geschwindigkeit.',
                'sources' => ['wp-liste-1904', 'ddb-1904-09-08-reichsanzeiger'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux hommes de la locomotive de tête du train pour Bellegarde ont été tués. La liste de Wikipédia compte aussi deux autres cheminots et une vingtaine de voyageurs blessés ; la dépêche du 7 septembre n\'en connaissait pas encore le nombre.',
                'en' => 'Two men on the leading engine of the Bellegarde train were killed. The Wikipedia list also counts two other railwaymen and about twenty passengers injured; the dispatch of 7 September did not yet know their number.',
                'de' => 'Zwei Mann der vorderen Lokomotive des Zuges nach Bellegarde wurden getötet. Die Wikipedia-Liste zählt außerdem zwei weitere Eisenbahner und etwa zwanzig Reisende als verletzt; die Depesche vom 7. September kannte ihre Zahl noch nicht.',
                'sources' => ['wp-liste-1904', 'ddb-1904-09-08-reichsanzeiger', 'ddb-1904-09-09-grossenhain'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1904', 'ddb-1904-09-08-reichsanzeiger', 'ddb-1904-09-09-grossenhain'],
            'fr' => 'Deux agents de la locomotive tués selon la liste de Wikipédia et la dépêche W. T. B. du 7 septembre 1904 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two engine crew killed according to the Wikipedia list and the W. T. B. dispatch of 7 September 1904; the record gave no number of deaths.',
            'de' => 'Zwei Mann Lokomotivpersonal getötet nach der Wikipedia-Liste und der Depesche des W. T. B. vom 7. September 1904; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
