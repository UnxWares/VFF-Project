<?php

// Contexte sourcé du déraillement de Pontmort (Cellule, aujourd'hui Chambaron-sur-Morge) du 2 juillet 1871
// (recherche du 21/11/2026).
return [
    'slug' => '1871-07-02-chambaron-sur-morge',
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
            'locator' => '1871, 2 juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1871',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (6 morts, une douzaine de blessés).',
        ],
        'vdr-pontmort' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1871, L\'Express de Paris déraille près de la station de Pontmort',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2026-01-14',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1871-lexpress-de-paris-deraille-pres-de-la-station-de-pontmort/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Petit Journal du 7 juillet et Le Courrier de la Lozère du 9 juillet 1871, qui reprennent le Moniteur du Puy-de-Dôme.',
        ],
        'petit-journal-1871-07-07' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 7 juillet 1871',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1871-07-07',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k591150g/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 2 juillet 1871, l\'express venant de Paris, train-poste 715 de Saint-Germain-des-Fossés à Clermont-Ferrand, roulait à pleine vitesse vers Clermont.',
                'en' => 'On Sunday 2 July 1871, the express from Paris, mail train 715 from Saint-Germain-des-Fossés to Clermont-Ferrand, was running at full speed towards Clermont.',
                'de' => 'Am Sonntag, dem 2. Juli 1871, fuhr der Schnellzug aus Paris, der Postzug 715 von Saint-Germain-des-Fossés nach Clermont-Ferrand, mit voller Geschwindigkeit in Richtung Clermont.',
                'sources' => ['vdr-pontmort', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 5 h 30, à la hauteur de La Moutade, à 300 m de la petite gare de Pontmort (hameau de Cellule), le train a déraillé. Les deux locomotives se sont renversées sur le talus, les rails des deux voies ont été tordus sur plus de 50 m et six des seize voitures ont été broyées. Une voiture de première classe est passée par-dessus un fourgon, et un wagon-poste s\'est abattu sur elle.',
                'en' => 'At about 5.30 am, level with La Moutade, 300 m from the small station of Pontmort (a hamlet of Cellule), the train derailed. Both locomotives overturned onto the embankment, the rails of both tracks were twisted over more than 50 m and six of the sixteen vehicles were crushed. A first-class carriage rode over a van, and a mail van came down on top of it.',
                'de' => 'Gegen 5.30 Uhr entgleiste der Zug auf Höhe von La Moutade, 300 m vor dem kleinen Bahnhof Pontmort (einem Weiler von Cellule). Beide Lokomotiven stürzten die Böschung hinab, die Schienen beider Gleise wurden auf mehr als 50 m verbogen, und sechs der sechzehn Wagen wurden zermalmt. Ein Wagen erster Klasse schob sich über einen Packwagen, und ein Postwagen stürzte auf ihn.',
                'sources' => ['vdr-pontmort', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'C\'est dans cette voiture de première classe que les victimes ont été retrouvées. Le Moniteur du Puy-de-Dôme comptait quatre voyageurs tués et un serre-frein ; Wikipédia donne six morts et une douzaine de blessés, venus surtout des régions d\'Issoire, de Riom et de Clermont.',
                'en' => 'The victims were found in that first-class carriage. The Moniteur du Puy-de-Dôme counted four passengers and a brakeman killed; Wikipedia gives six dead and about a dozen injured, mostly from the Issoire, Riom and Clermont areas.',
                'de' => 'Die Opfer wurden in diesem Wagen erster Klasse gefunden. Der Moniteur du Puy-de-Dôme zählte vier getötete Reisende und einen Bremser; Wikipedia nennt sechs Tote und etwa ein Dutzend Verletzte, vor allem aus der Gegend von Issoire, Riom und Clermont.',
                'sources' => ['vdr-pontmort', 'wp-liste-1870'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La presse de juillet 1871 ne connaissait pas la cause. Selon Wikipédia, l\'accident a sans doute été provoqué par un écartement des rails dû au mauvais état de la voie.',
                'en' => 'The press in July 1871 did not know the cause. According to Wikipedia, the accident was probably caused by the rails spreading because the track was in poor condition.',
                'de' => 'Die Presse kannte im Juli 1871 die Ursache nicht. Laut Wikipedia wurde der Unfall vermutlich durch eine Spurerweiterung infolge des schlechten Gleiszustands ausgelöst.',
                'sources' => ['vdr-pontmort', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [],
];
