<?php

// Contexte sourcé de la collision du rapide de Belfort et de l'omnibus de Troyes dans le tunnel de La Pommeraye, entre Foulain et Rolampont, en mai 1898 (recherche du 21/11/2026).
return [
    'slug' => '1898-05-09-rolampont',
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
            'locator' => '1898, 9 mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1898',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (4 morts et une trentaine de blessés), d\'après Le Petit Parisien des 10 et 11 mai 1898.',
        ],
        'vdr-la-pommeraye' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1898, Accident sous le tunnel de La Pommeraye à Marnay-sur-Marne (52)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1898-accident-sous-le-tunnel-de-la-pommeraye-a-marnay-sur-marne-52/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit les correspondances de Chaumont publiées par Le Petit Parisien des 10 et 11 mai 1898.',
        ],
        'petit-parisien-1898-05-10' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 10 mai 1898',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1898-05-10',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5185382/f1',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'petit-parisien-1898-05-11' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 11 mai 1898',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1898-05-11',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k518539f.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le tunnel de La Pommeraye, long de 298 mètres et en courbe, se trouve entre Foulain et Rolampont sur la ligne de Paris à Mulhouse. Il était en réparation depuis le 18 février 1898 à cause d\'infiltrations : un échafaudage occupait une voie et les trains passaient sur une voie unique, sous le régime du pilotage. Aucun train ne devait s\'y engager sans prendre à bord le pilote, qui faisait la navette d\'un bout à l\'autre de la section.',
                'en' => 'La Pommeraye tunnel, 298 metres long and curved, lies between Foulain and Rolampont on the Paris to Mulhouse line. It had been under repair since 18 February 1898 because of water seepage: scaffolding took up one track and trains used a single line worked by pilotman. No train was to enter without taking on board the pilotman, who shuttled from one end of the section to the other.',
                'de' => 'Der 298 Meter lange, gekrümmte Tunnel von La Pommeraye liegt zwischen Foulain und Rolampont an der Strecke Paris-Mulhouse. Wegen Wassereinbrüchen wurde er seit dem 18. Februar 1898 instand gesetzt: Ein Gerüst belegte ein Gleis, und die Züge fuhren eingleisig unter Lotsenbetrieb. Kein Zug durfte einfahren, ohne den Lotsen an Bord zu nehmen, der zwischen beiden Enden des Abschnitts pendelte.',
                'sources' => ['vdr-la-pommeraye', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 14 heures, le pilote accompagnait l\'omnibus 51-53, parti de Troyes pour Belfort, et la machine allait sortir du tunnel quand le rapide 40-30 bis, parti de Belfort à 11 h 35, s\'est présenté à grande vitesse à l\'autre entrée sans s\'être arrêté. L\'omnibus n\'avait pas encore rejoint sa voie normale : les deux machines et le premier wagon de chaque train se sont télescopés.',
                'en' => 'At about 2 pm the pilotman was riding on stopping train 51-53, which had left Troyes for Belfort, and its engine was about to leave the tunnel when express 40-30 bis, which had left Belfort at 11.35 am, came up at speed at the other entrance without having stopped. The stopping train had not yet regained its normal track: the two engines and the first vehicle of each train telescoped.',
                'de' => 'Gegen 14 Uhr begleitete der Lotse den Personenzug 51-53 von Troyes nach Belfort, und dessen Lokomotive wollte gerade aus dem Tunnel fahren, als der Schnellzug 40-30 bis, um 11.35 Uhr in Belfort abgefahren, ohne Halt mit hoher Geschwindigkeit an der anderen Einfahrt erschien. Der Personenzug war noch nicht auf sein reguläres Gleis zurückgekehrt: Beide Lokomotiven und der erste Wagen jedes Zuges schoben sich ineinander.',
                'sources' => ['vdr-la-pommeraye', 'wp-liste-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois voyageurs ont été tués, dont une fillette, et le pilote, qui avait eu une jambe coupée, est mort ensuite. Une trentaine de voyageurs ont été blessés ; ils ont été soignés à Vesaignes, à Chaumont et à Langres.',
                'en' => 'Three passengers were killed, among them a little girl, and the pilotman, whose leg had been severed, died later. About thirty passengers were injured and were treated at Vesaignes, Chaumont and Langres.',
                'de' => 'Drei Reisende wurden getötet, darunter ein kleines Mädchen, und der Lotse, dem ein Bein abgetrennt worden war, starb später. Etwa dreißig Reisende wurden verletzt und in Vesaignes, Chaumont und Langres behandelt.',
                'sources' => ['vdr-la-pommeraye', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le mécanicien du rapide n\'avait pas respecté le signal d\'arrêt ni attendu le pilote. Il a affirmé que le frein Westinghouse n\'avait pas fonctionné ; les ingénieurs répondaient que les freins avaient été essayés au départ de Vesoul et avaient bien fonctionné à l\'arrivée à Langres, un quart d\'heure plus tôt.',
                'en' => 'The express driver had ignored the stop signal and had not waited for the pilotman. He said the Westinghouse brake had failed; the engineers replied that the brakes had been tested on leaving Vesoul and had worked properly on arrival at Langres a quarter of an hour earlier.',
                'de' => 'Der Lokführer des Schnellzugs hatte das Haltesignal missachtet und nicht auf den Lotsen gewartet. Er gab an, die Westinghouse-Bremse habe versagt; die Ingenieure entgegneten, die Bremsen seien bei der Abfahrt in Vesoul geprüft worden und hätten bei der Einfahrt in Langres eine Viertelstunde zuvor einwandfrei gewirkt.',
                'sources' => ['vdr-la-pommeraye', 'wp-liste-1890'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le ministre des Travaux publics, Turrel, a chargé de l\'enquête un inspecteur général des Mines et le chef adjoint de son cabinet. Les débris ont été dégagés le lendemain avec l\'aide d\'une compagnie du 109e de ligne, et le service a repris dans le tunnel à midi.',
                'en' => 'The Minister of Public Works, Turrel, entrusted the inquiry to an inspector general of mines and the deputy head of his private office. The wreckage was cleared the next day with the help of a company of the 109th Line Infantry, and services resumed through the tunnel at midday.',
                'de' => 'Der Minister für öffentliche Arbeiten, Turrel, beauftragte einen Generalinspektor der Bergwerke und den stellvertretenden Leiter seines Kabinetts mit der Untersuchung. Die Trümmer wurden am nächsten Tag mit Hilfe einer Kompanie des 109. Linienregiments geräumt, und der Betrieb durch den Tunnel wurde mittags wieder aufgenommen.',
                'sources' => ['vdr-la-pommeraye'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les premières dépêches parlaient de deux employés tués, puis la presse a compté une mère parmi les morts ; selon Victimes du rail, elle était morte en avril 1898 et les journaux ont recopié cette erreur. Le site situe le tunnel sur la commune de Marnay-sur-Marne ; la presse et Wikipédia disent seulement « entre Foulain et Rolampont ».',
                'en' => 'The first dispatches spoke of two employees killed, then the press counted a mother among the dead; according to Victimes du rail she had died in April 1898 and the newspapers copied the error. The site places the tunnel in the commune of Marnay-sur-Marne; the press and Wikipedia only say "between Foulain and Rolampont".',
                'de' => 'Die ersten Meldungen sprachen von zwei getöteten Bediensteten, später zählte die Presse eine Mutter zu den Toten; laut Victimes du rail war sie im April 1898 gestorben, und die Zeitungen übernahmen den Fehler. Die Website verortet den Tunnel in der Gemeinde Marnay-sur-Marne; Presse und Wikipedia sagen nur „zwischen Foulain und Rolampont".',
                'sources' => ['vdr-la-pommeraye', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
