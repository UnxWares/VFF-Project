<?php

// Contexte sourcé de la collision dans le tunnel de Lozanne du 6 août 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-08-06-lozanne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1912' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1912',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '6 août 1912, Lozanne',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne vers 14 h 45, l\'omnibus de Paray-le-Monial arrêté dans le tunnel avant la gare de Lozanne, l\'express Vichy-Lyon qui le télescope, l\'incendie, 5 morts et une vingtaine de blessés ; cite Le Petit Parisien des 7 et 8 août 1912 et Le Temps du 9 août 1912.',
        ],
        'tilburgsche-courant-1912-08-08' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Tilburgsche courant du 8 août 1912',
            'publisher' => 'Tilburgsche courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1912-08-08',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010190111:mpeg21:a0021',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Article « Het spoorwegongeluk te Lozanne ».',
        ],
        'petit-parisien-1912-08-07' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 7 août 1912, page 1',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1912-08-07',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k564420g',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 6 août 1912, dans un tunnel près de Lozanne (Rhône), à proximité de Lyon, le train de Paray-le-Monial à Lyon, parti à 14 h, était presque entièrement entré dans le tunnel quand l\'express de Vichy à Lyon, parti à 13 h 30, est arrivé derrière lui.',
                'en' => 'On 6 August 1912, in a tunnel near Lozanne (Rhône), close to Lyon, the Paray-le-Monial to Lyon train, which had left at 2 pm, had almost entirely entered the tunnel when the Vichy to Lyon express, which had left at 1.30 pm, came up behind it.',
                'de' => 'Am 6. August 1912 war in einem Tunnel bei Lozanne (Rhône) nahe Lyon der um 14 Uhr abgefahrene Zug von Paray-le-Monial nach Lyon fast vollständig in den Tunnel eingefahren, als der um 13.30 Uhr abgefahrene Schnellzug von Vichy nach Lyon hinter ihm eintraf.',
                'sources' => ['tilburgsche-courant-1912-08-08', 'wp-liste-1912'],
            ],
            [
                'fr' => 'D\'après plusieurs voyageurs, des pétards ont été posés quand le train de Paray-le-Monial est entré dans le tunnel, pour signaler que la voie était fermée, mais le mécanicien du second train ne les a pas entendus.',
                'en' => 'According to several passengers, detonators were placed when the Paray-le-Monial train entered the tunnel, to signal that the line was blocked, but the driver of the second train did not hear them.',
                'de' => 'Nach Aussagen mehrerer Reisender wurden Knallsignale gelegt, als der Zug aus Paray-le-Monial in den Tunnel einfuhr, um anzuzeigen, dass die Strecke gesperrt war, doch der Lokführer des zweiten Zuges hörte sie nicht.',
                'sources' => ['tilburgsche-courant-1912-08-08', 'wp-liste-1912'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'express est entré en collision avec les cinq dernières voitures du train de Paray-le-Monial ; deux voitures ont été entièrement détruites. La liste de Wikipédia ajoute qu\'un incendie s\'est déclaré.',
                'en' => 'The express collided with the last five carriages of the Paray-le-Monial train; two carriages were completely destroyed. The Wikipedia list adds that a fire broke out.',
                'de' => 'Der Schnellzug stieß mit den letzten fünf Wagen des Zuges aus Paray-le-Monial zusammen; zwei Wagen wurden völlig zerstört. Die Wikipedia-Liste fügt hinzu, dass ein Brand ausbrach.',
                'sources' => ['tilburgsche-courant-1912-08-08', 'wp-liste-1912'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Quatre morts et une vingtaine de blessés ont été retirés des décombres selon le journal néerlandais du 8 août. La liste de Wikipédia donne cinq morts, dont deux blessés décédés le lendemain, et une vingtaine de blessés.',
                'en' => 'Four dead and about twenty injured were taken from the wreckage according to the Dutch newspaper of 8 August. The Wikipedia list gives five dead, including two injured who died the next day, and about twenty injured.',
                'de' => 'Vier Tote und etwa zwanzig Verletzte wurden laut der niederländischen Zeitung vom 8. August aus den Trümmern geborgen. Die Wikipedia-Liste nennt fünf Tote, darunter zwei am folgenden Tag gestorbene Verletzte, und etwa zwanzig Verletzte.',
                'sources' => ['tilburgsche-courant-1912-08-08', 'wp-liste-1912'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'On a immédiatement demandé par télégraphe un train sanitaire, qui a emporté les blessés graves ; les blessés légers ont été installés dans les hôtels voisins et soignés par des médecins.',
                'en' => 'An ambulance train was immediately requested by telegraph and took away the seriously injured; the lightly injured were put up in nearby hotels and treated by doctors.',
                'de' => 'Es wurde sofort telegrafisch ein Sanitätszug angefordert, der die Schwerverletzten abtransportierte; die Leichtverletzten wurden in nahe gelegenen Hotels untergebracht und von Ärzten verbunden.',
                'sources' => ['tilburgsche-courant-1912-08-08'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le premier bilan néerlandais (4 morts) est antérieur au décès de deux blessés le lendemain, rapporté par la liste de Wikipédia, d\'où les 5 morts de la fiche.',
                'en' => 'The first Dutch toll (4 dead) predates the death of injured people the next day reported by the Wikipedia list, hence the 5 dead on the record.',
                'de' => 'Die erste niederländische Bilanz (4 Tote) liegt vor dem Tod von Verletzten am folgenden Tag, den die Wikipedia-Liste berichtet, daher die 5 Toten des Eintrags.',
                'sources' => ['tilburgsche-courant-1912-08-08', 'wp-liste-1912'],
            ],
        ],
    ],
    'corrections' => [],
];
