<?php

// Contexte sourcé de la collision du 17 décembre 1876 au bord du lac du Bourget, entre Aix-les-Bains et Châtillon,
// d'un train spécial de Culoz à Modane (recherche du 21/11/2026).
return [
    'slug' => '1876-12-17-modane',
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
            'locator' => '1876, 17 décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1876',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (7 morts et 14 blessés), d\'après Le Figaro du 18, Le Petit Journal du 20 et Le Petit Parisien du 21 décembre 1876.',
        ],
        'vdr-culoz-modane' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1876, Terrible catastrophe entre Culoz et Modane',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2026-05-11',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1876-terrible-catastrophe-entre-culoz-et-modane/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit les communiqués du PLM publiés par Le Petit Journal du 20 décembre et le Journal du département de la Marne du 22 décembre 1876.',
        ],
        'petit-journal-1876-12-20' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 20 décembre 1876',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1876-12-20',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k593140p/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Communiqués du PLM, lus seulement dans leur transcription.',
        ],
        'marne-1876-12-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Journal du département de la Marne du 22 décembre 1876',
            'publisher' => 'Journal du département de la Marne',
            'author' => null,
            'published' => '1876-12-22',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k69817877/f2.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Liste des victimes communiquée par le PLM, lue seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Un accident survenu la veille au soir à Montereau avait retardé l\'express de Paris de 20 h 40. Pour conduire ses voyageurs vers l\'Italie, le PLM a mis en marche le 17 décembre 1876 un train spécial de Culoz à Modane.',
                'en' => 'An accident at Montereau the previous evening had delayed the 8.40 pm express from Paris. To carry its passengers on to Italy, the PLM ran a special train from Culoz to Modane on 17 December 1876.',
                'de' => 'Ein Unfall am Vorabend in Montereau hatte den Schnellzug ab Paris um 20.40 Uhr verspätet. Um dessen Reisende nach Italien weiterzubefördern, setzte die PLM am 17. Dezember 1876 einen Sonderzug von Culoz nach Modane ein.',
                'sources' => ['vdr-culoz-modane', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 18 h 30, sur la voie unique qui longe le lac du Bourget entre Aix-les-Bains et la gare de Châtillon, le train spécial a heurté de front un train ordinaire venant en sens inverse. Dix voitures ont été brisées, et il a fallu deux jours pour dégager les deux machines ; un transbordement a été organisé.',
                'en' => 'At about 6.30 pm, on the single track along Lake Bourget between Aix-les-Bains and Châtillon station, the special train met an ordinary train coming the other way head-on. Ten vehicles were smashed and it took two days to clear the two engines; passengers were transferred between trains.',
                'de' => 'Gegen 18.30 Uhr stieß der Sonderzug auf dem eingleisigen Abschnitt am Lac du Bourget zwischen Aix-les-Bains und dem Bahnhof Châtillon frontal mit einem planmäßigen Zug aus der Gegenrichtung zusammen. Zehn Wagen wurden zertrümmert, und die Räumung der beiden Lokomotiven dauerte zwei Tage; die Reisenden wurden umgeladen.',
                'sources' => ['vdr-culoz-modane', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste du PLM, l\'accident a fait neuf morts, trois voyageurs et six agents de la compagnie, et quatorze blessés, en majorité des voyageurs étrangers. Les obsèques des neuf victimes ont été célébrées à Aix-les-Bains, sous la présidence de l\'archevêque de Chambéry.',
                'en' => 'According to the PLM list, the accident killed nine people, three passengers and six company employees, and injured fourteen, most of them foreign travellers. The funeral of the nine victims was held at Aix-les-Bains, led by the Archbishop of Chambéry.',
                'de' => 'Nach der Liste der PLM forderte der Unfall neun Tote, drei Reisende und sechs Bedienstete der Gesellschaft, sowie vierzehn Verletzte, überwiegend ausländische Reisende. Die Trauerfeier für die neun Opfer fand in Aix-les-Bains unter dem Vorsitz des Erzbischofs von Chambéry statt.',
                'sources' => ['vdr-culoz-modane'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le PLM a attribué l\'accident à une erreur de la gare de Châtillon, dont le chef de gare a été arrêté. Le 18 février 1877, le tribunal correctionnel de Chambéry l\'a reconnu coupable d\'homicide par imprudence et condamné à trois ans de prison.',
                'en' => 'The PLM blamed the accident on an error at Châtillon station, whose stationmaster was arrested. On 18 February 1877, the Chambéry criminal court found him guilty of manslaughter by negligence and sentenced him to three years in prison.',
                'de' => 'Die PLM führte den Unfall auf einen Fehler im Bahnhof Châtillon zurück, dessen Vorsteher festgenommen wurde. Am 18. Februar 1877 sprach ihn das Strafgericht Chambéry der fahrlässigen Tötung schuldig und verurteilte ihn zu drei Jahren Gefängnis.',
                'sources' => ['vdr-culoz-modane', 'wp-liste-1870'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan a changé d\'un communiqué à l\'autre : huit morts et quinze blessés, puis sept morts, chiffre repris par Wikipédia, puis neuf morts et quatorze blessés dans la liste nominative du PLM. La fiche est placée à Modane, destination du train spécial ; la collision a eu lieu au bord du lac du Bourget, entre Aix-les-Bains et Chindrieux, et aucune source lue ne donne la commune exacte.',
                'en' => 'The toll changed from one statement to the next: eight dead and fifteen injured, then seven dead, the figure used by Wikipedia, then nine dead and fourteen injured in the PLM\'s list of names. The record is placed at Modane, the special train\'s destination; the collision happened by Lake Bourget, between Aix-les-Bains and Chindrieux, and no source read gives the exact commune.',
                'de' => 'Die Bilanz änderte sich von Mitteilung zu Mitteilung: acht Tote und fünfzehn Verletzte, dann sieben Tote, die Zahl bei Wikipedia, dann neun Tote und vierzehn Verletzte in der Namensliste der PLM. Der Eintrag ist in Modane verortet, dem Ziel des Sonderzugs; der Zusammenstoß ereignete sich am Lac du Bourget zwischen Aix-les-Bains und Chindrieux, und keine gelesene Quelle nennt die genaue Gemeinde.',
                'sources' => ['vdr-culoz-modane', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 9,
            'sources' => ['vdr-culoz-modane'],
            'fr' => 'La liste nominative publiée par le PLM compte neuf morts, et neuf cercueils ont été inhumés à Aix-les-Bains ; sept était un bilan provisoire.',
            'en' => 'The list of names published by the PLM gives nine dead, and nine coffins were buried at Aix-les-Bains; seven was a provisional figure.',
            'de' => 'Die von der PLM veröffentlichte Namensliste nennt neun Tote, und in Aix-les-Bains wurden neun Särge beigesetzt; sieben war eine vorläufige Zahl.',
        ],
    ],
];
