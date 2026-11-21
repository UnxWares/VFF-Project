<?php

// Contexte sourcé de la collision de Neuillé-Pont-Pierre du 31 mars 1999 (recherche du 21/11/2026).
return [
    'slug' => '1999-03-31-neuille-pont-pierre',
    'checked_on' => '2026-11-21',
    'sources' => [
        'frmtr-x72500-1999' => [
            'provider' => 'sites-web',
            'kind' => 'forum',
            'reliability' => 'forum',
            'title' => 'Et un X 72500 accidenté, un !',
            'publisher' => 'Groupe fr.misc.transport.rail (Google Groupes)',
            'author' => null,
            'published' => '1999-03-31',
            'locator' => 'fil du 31 mars au 1er avril 1999',
            'url' => 'https://groups.google.com/g/fr.misc.transport.rail/c/mJ2zlhHgBM0',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Messages du jour même : collision à un passage à niveau, avis de la SNCF reproduit (train 87110, retards), bilan annoncé, version d\'un intervenant sur la cause. Fiabilité limitée à celle d\'un forum.',
        ],
        'wp-liste-1990' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1990',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1999',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1990&oldid=239974394#1999',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Camion de céréales contre un X 72500 sur la ligne de Tours au Mans, un mort « le conducteur » et une dizaine de blessés ; cite le fil du forum et un article de La Nouvelle République de 2014 (non lu).',
        ],
        'nr-2014-travaux' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Quinze ans après l\'accident, les travaux ont enfin débuté',
            'publisher' => 'La Nouvelle République du Centre-Ouest',
            'author' => null,
            'published' => '2014-04-01',
            'locator' => null,
            'url' => 'http://www.lanouvellerepublique.fr/Indre-et-Loire/Actualite/24-Heures/n/Contenus/Articles/2014/04/01/Quinze-ans-apres-l-accident-les-travaux-ont-enfin-debute-1853733',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par Wikipédia, à consulter : la page n\'a pas pu être lue depuis la machine. Il n\'appuie aucun énoncé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 31 mars 1999, un camion et le TER 87110 (Tours 12 h 05, Le Mans 13 h 08) sont entrés en collision au passage à niveau de Neuillé-Pont-Pierre (Indre-et-Loire), sur la ligne de Tours au Mans. Le train était un autorail X 72500, endommagé dans le choc. L\'heure exacte de la collision n\'est pas connue.',
                'en' => 'On 31 March 1999, a lorry and TER train 87110 (Tours 12.05 pm, Le Mans 1.08 pm) collided at the level crossing at Neuillé-Pont-Pierre (Indre-et-Loire), on the Tours to Le Mans line. The train was an X 72500 railcar, which was damaged in the impact. The exact time of the collision is not known.',
                'de' => 'Am 31. März 1999 stießen ein Lastwagen und der TER 87110 (Tours 12.05 Uhr, Le Mans 13.08 Uhr) am Bahnübergang von Neuillé-Pont-Pierre (Indre-et-Loire) auf der Strecke von Tours nach Le Mans zusammen. Der Zug war ein Triebwagen X 72500, der beim Aufprall beschädigt wurde. Die genaue Uhrzeit des Zusammenstoßes ist nicht bekannt.',
                'sources' => ['frmtr-x72500-1999', 'wp-liste-1990'],
            ],
            [
                'fr' => 'La liste de Wikipédia précise que le camion transportait des céréales.',
                'en' => 'The Wikipedia list adds that the lorry was carrying cereals.',
                'de' => 'Die Wikipedia-Liste ergänzt, dass der Lastwagen Getreide transportierte.',
                'sources' => ['wp-liste-1990'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan annoncé le jour même est d\'un mort et d\'une dizaine de blessés. D\'après un intervenant du forum, la victime est le mécanicien du train et le routier est indemne ; la liste de Wikipédia écrit seulement « le conducteur ».',
                'en' => 'The toll announced on the day was one dead and about ten injured. According to one forum contributor, the person killed was the train driver and the lorry driver was unhurt; the Wikipedia list says only "le conducteur" (the driver).',
                'de' => 'Die am selben Tag gemeldete Bilanz lautet ein Toter und etwa zehn Verletzte. Nach Angaben eines Forumsteilnehmers war das Todesopfer der Triebfahrzeugführer des Zuges, der Lkw-Fahrer blieb unverletzt; die Wikipedia-Liste schreibt nur „le conducteur“ (der Fahrer).',
                'sources' => ['frmtr-x72500-1999', 'wp-liste-1990'],
            ],
            [
                'fr' => 'Le même intervenant écrit, d\'après les images de télévision, que la cabine de conduite a été complètement broyée.',
                'en' => 'The same contributor writes, from television pictures, that the driving cab was completely crushed.',
                'de' => 'Derselbe Teilnehmer schreibt nach Fernsehbildern, dass der Führerstand völlig zerquetscht wurde.',
                'sources' => ['frmtr-x72500-1999'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon un intervenant du forum, le routier avait forcé un passage à niveau fermé avec son semi-remorque de céréales, et ce passage à niveau figurait depuis des années sur la liste des passages dangereux, au bout d\'une longue ligne droite. Aucun autre document lu ne le confirme.',
                'en' => 'According to a forum contributor, the lorry driver had forced a closed level crossing with a cereal semi-trailer, and the crossing had been on the list of dangerous crossings for years, at the end of a long straight. No other document read confirms this.',
                'de' => 'Nach Angaben eines Forumsteilnehmers hatte der Lkw-Fahrer mit seinem Getreide-Sattelzug einen geschlossenen Bahnübergang erzwungen, und der Übergang stand seit Jahren auf der Liste gefährlicher Übergänge, am Ende einer langen Geraden. Kein anderes gelesenes Dokument bestätigt dies.',
                'sources' => ['frmtr-x72500-1999'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Un avis de la SNCF du 31 mars 1999, valable jusqu\'à 22 h, signalait des retards d\'environ 30 minutes sur les trains Tours - Le Mans - Caen.',
                'en' => 'An SNCF notice dated 31 March 1999, valid until 10 pm, reported delays of about 30 minutes on Tours - Le Mans - Caen trains.',
                'de' => 'Eine SNCF-Mitteilung vom 31. März 1999, gültig bis 22 Uhr, meldete Verspätungen von etwa 30 Minuten auf den Zügen Tours - Le Mans - Caen.',
                'sources' => ['frmtr-x72500-1999'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'level_crossing',
            'sources' => ['frmtr-x72500-1999', 'wp-liste-1990'],
            'fr' => 'L\'avis de la SNCF reproduit dans le fil du jour parle d\'un accident au passage à niveau de Neuillé-Pont-Pierre entre un camion et un TER ; la liste de Wikipédia décrit la collision d\'un camion avec un autorail.',
            'en' => 'The SNCF notice reproduced in the same-day thread reports an accident at the Neuillé-Pont-Pierre level crossing between a lorry and a TER train; the Wikipedia list describes a lorry colliding with a railcar.',
            'de' => 'Die im Beitrag desselben Tages wiedergegebene SNCF-Mitteilung berichtet von einem Unfall am Bahnübergang Neuillé-Pont-Pierre zwischen einem Lastwagen und einem TER; die Wikipedia-Liste beschreibt die Kollision eines Lastwagens mit einem Triebwagen.',
        ],
    ],
];
