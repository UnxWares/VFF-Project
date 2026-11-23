<?php

// Contexte sourcé du déraillement d'un train de marchandises près de Dompaire en février 1879 (recherche du 21/11/2026).
return [
    'slug' => '1879-02-20-dompaire',
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
            'note' => 'Entrée de la liste, d\'après Le Figaro du 21 février 1879 : le 20 février 1879, de nuit, sur la ligne d\'Épinal à Mirecourt, près de la gare de Dompaire, un train de marchandises déraille ; trois cheminots sont tués, un autre est grièvement blessé.',
        ],
        'delpher-1879-02-24-vaderland' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het vaderland du 24 février 1879',
            'publisher' => 'Het vaderland  (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1879-02-24',
            'locator' => 'p. 5',
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB23:001404046:mpeg21:a00009',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : un train de marchandises venant de Neufchâteau, en retard de 7 heures, déraille de nuit sur la ligne d\'Épinal à Mirecourt ; le chef de gare de Dompaire, le chauffeur et un autre agent sont tués, le mécanicien perd une jambe ; les wagons sont empilés les uns sur les autres.',
        ],
        'delpher-1879-02-25-rotterdamsche' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Rotterdamsche courant du 25 février 1879',
            'publisher' => 'Rotterdamsche courant  (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1879-02-25',
            'locator' => 'p. 1',
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:011005646:mpeg21:a0002',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même brève, rubrique « Frankrijk » : train de marchandises déraillé de nuit entre Épinal et Mirecourt ; le chef de gare de Dompaire, le chauffeur et un autre agent tués, le mécanicien amputé d\'une jambe.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'En février 1879, de nuit, un train de marchandises venant de Neufchâteau déraille près de la gare de Dompaire (Vosges), sur la ligne d\'Épinal à Mirecourt. Il avait sept heures de retard.',
                'en' => 'One night in February 1879, a goods train coming from Neufchâteau derailed near Dompaire station (Vosges), on the Épinal to Mirecourt line. It was running seven hours late.',
                'de' => 'In einer Februarnacht 1879 entgleiste nahe dem Bahnhof Dompaire (Vosges) auf der Strecke Épinal-Mirecourt ein aus Neufchâteau kommender Güterzug. Er hatte sieben Stunden Verspätung.',
                'sources' => ['wp-liste-1870', 'delpher-1879-02-24-vaderland', 'delpher-1879-02-25-rotterdamsche'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les wagons se sont empilés les uns sur les autres.',
                'en' => 'The wagons piled up on top of each other.',
                'de' => 'Die Wagen türmten sich übereinander.',
                'sources' => ['delpher-1879-02-24-vaderland', 'delpher-1879-02-25-rotterdamsche'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois agents sont tués : selon la presse néerlandaise, le chef de gare de Dompaire, le chauffeur et un troisième agent. Le mécanicien, grièvement blessé, perd une jambe.',
                'en' => 'Three railwaymen were killed: according to the Dutch press, the Dompaire stationmaster, the stoker and a third employee. The driver was seriously injured and lost a leg.',
                'de' => 'Drei Bedienstete wurden getötet: nach der niederländischen Presse der Bahnhofsvorsteher von Dompaire, der Heizer und ein dritter Bediensteter. Der Lokomotivführer wurde schwer verletzt und verlor ein Bein.',
                'sources' => ['wp-liste-1870', 'delpher-1879-02-24-vaderland', 'delpher-1879-02-25-rotterdamsche'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 3,
            'sources' => ['wp-liste-1870', 'delpher-1879-02-24-vaderland'],
            'fr' => 'Trois morts selon la liste de Wikipédia et la presse néerlandaise de février 1879 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Three dead according to the Wikipedia list and the Dutch press of February 1879; the record gave no number of deaths.',
            'de' => 'Drei Tote nach der Wikipedia-Liste und der niederländischen Presse vom Februar 1879; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['wp-liste-1870', 'delpher-1879-02-24-vaderland'],
            'fr' => 'Un blessé grave, le mécanicien, selon la liste de Wikipédia et la presse néerlandaise ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'One seriously injured, the driver, according to the Wikipedia list and the Dutch press; the record gave no number of injured.',
            'de' => 'Ein Schwerverletzter, der Lokomotivführer, nach der Wikipedia-Liste und der niederländischen Presse; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
