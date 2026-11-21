<?php

// Contexte sourcé de l'accident de Morgny (Morgny-la-Pommeraye) du 18 mai 1940 (recherche du 21/11/2026).
return [
    'slug' => '1940-05-18-morgny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1940',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Cite Rouen Gazette (14/02/1941), Paris-Soir (26/12/1941) et un arrêt de la Cour de cassation du 10/12/1943, non lus.',
        ],
        'gare-morgny' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Gare de Morgny',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/wiki/Gare_de_Morgny',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Situe la gare à Morgny-la-Pommeraye (Seine-Maritime).',
        ],
        'commune-morgny' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Morgny-la-Pommeraye',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/wiki/Morgny-la-Pommeraye',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Mentionne l\'accident en une phrase (une cinquantaine de morts, une centaine de blessés).',
        ],
        'rouen-gazette-1941' => [
            'provider' => 'sites-web',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Rouen Gazette, 14 février 1941, p. 1 (procès du mécanicien)',
            'publisher' => 'Rouen Gazette',
            'author' => null,
            'published' => '1941-02-14',
            'locator' => 'p. 1',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste Wikipédia ; journal non retrouvé en ligne, à consulter aux archives.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 18 mai 1940, pendant l\'exode consécutif à l\'invasion de la Belgique, un accident survient près de la gare de Morgny, à Morgny-la-Pommeraye (Seine-Maritime), sur la ligne d\'Amiens à Rouen. La gare appartient à la ligne de Saint-Roch à Darnétal-Bifurcation, ouverte en 1867.',
                'en' => 'On 18 May 1940, during the exodus that followed the invasion of Belgium, an accident occurred near Morgny station, at Morgny-la-Pommeraye (Seine-Maritime), on the Amiens to Rouen line. The station belongs to the Saint-Roch to Darnétal-Bifurcation line, opened in 1867.',
                'de' => 'Am 18. Mai 1940, während der Massenflucht nach dem Einmarsch in Belgien, ereignete sich nahe dem Bahnhof Morgny in Morgny-la-Pommeraye (Seine-Maritime) auf der Strecke Amiens-Rouen ein Unfall. Der Bahnhof liegt an der 1867 eröffneten Strecke Saint-Roch-Darnétal-Bifurcation.',
                'sources' => ['liste-1940', 'gare-morgny'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un train de réfugiés français, roulant à 52 km/h, vitesse jugée excessive, a heurté l\'arrière d\'un train de réfugiés belges à l\'arrêt. Le choc a disloqué les voitures à caisse en bois du train français.',
                'en' => 'A train of French refugees, travelling at 52 km/h, a speed judged excessive, struck the rear of a stationary train of Belgian refugees. The impact broke up the wooden-bodied carriages of the French train.',
                'de' => 'Ein Zug französischer Flüchtlinge fuhr mit 52 km/h, einer als überhöht beurteilten Geschwindigkeit, auf das Ende eines haltenden Zuges belgischer Flüchtlinge auf. Der Aufprall zerlegte die Wagen mit Holzaufbauten des französischen Zuges.',
                'sources' => ['liste-1940', 'gare-morgny'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait 53 morts et 128 blessés. Les victimes étaient des réfugiés. La censure militaire a occulté l\'événement.',
                'en' => 'The accident killed 53 people and injured 128. The victims were refugees. Military censorship concealed the event.',
                'de' => 'Bei dem Unfall starben 53 Menschen, 128 wurden verletzt. Die Opfer waren Flüchtlinge. Die Militärzensur verschwieg das Ereignis.',
                'sources' => ['liste-1940', 'gare-morgny'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le mécanicien du train tamponneur a d\'abord été condamné à un mois de prison avec sursis. La cour d\'appel de Rouen l\'a relaxé, ainsi que la SNCF, par un arrêt du 23 décembre 1941 ; la Cour de cassation a confirmé cette décision le 10 décembre 1943.',
                'en' => 'The driver of the striking train was first sentenced to one month in prison, suspended. The Rouen court of appeal acquitted him, and the SNCF, in a ruling of 23 December 1941; the Court of Cassation upheld the decision on 10 December 1943.',
                'de' => 'Der Lokführer des auffahrenden Zuges wurde zunächst zu einem Monat Haft auf Bewährung verurteilt. Das Berufungsgericht Rouen sprach ihn und die SNCF mit Urteil vom 23. Dezember 1941 frei; der Kassationshof bestätigte dies am 10. Dezember 1943.',
                'sources' => ['liste-1940'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche indique le département 27 (Eure), alors que les pages consultées situent l\'accident à Morgny-la-Pommeraye, en Seine-Maritime (76). Les sources lues dérivent toutes de Wikipédia : aucune source primaire n\'a pu être consultée.',
                'en' => 'The record gives département 27 (Eure), whereas the pages consulted place the accident at Morgny-la-Pommeraye, in Seine-Maritime (76). All sources read derive from Wikipedia: no primary source could be consulted.',
                'de' => 'Der Datensatz nennt das Département 27 (Eure), die eingesehenen Seiten verorten den Unfall jedoch in Morgny-la-Pommeraye, Seine-Maritime (76). Alle gelesenen Quellen stammen aus Wikipedia: Eine Primärquelle ließ sich nicht einsehen.',
                'sources' => ['liste-1940', 'gare-morgny', 'commune-morgny'],
            ],
        ],
    ],
];
