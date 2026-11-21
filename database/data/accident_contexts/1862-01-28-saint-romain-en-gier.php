<?php

// Contexte sourcé de la collision du tunnel de Saint-Romain-en-Gier, en janvier 1862 (recherche du 21/11/2026).
return [
    'slug' => '1862-01-28-saint-romain-en-gier',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '28 janvier 1862',
            'url' => 'https://fr.wikipedia.org/wiki/Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne la date (28 janvier), l\'heure (vers 23 h 30), la voie unique des travaux, trois morts et plusieurs blessés ; cite La Presse du 31 janvier 1862.',
        ],
        'vdr-saint-romain' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1862, Accident au tunnel de Saint-Romain-en-Gier (69)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1862-accident-au-tunnel-de-saint-romain-en-gier-69/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Presse du 31 janvier 1862, qui reprend Le Courrier de Lyon.',
        ],
        'la-presse-1862-01-31' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 31 janvier 1862',
            'publisher' => 'La Presse',
            'author' => null,
            'published' => '1862-01-31',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k479328m/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le tunnel de Saint-Romain-en-Gier, sur la ligne de Lyon à Saint-Étienne, menaçait ruine depuis longtemps. Des travaux de remise à jour y occupaient une soixantaine d\'ouvriers, une des voies avait été supprimée provisoirement, et les voyageurs avaient plusieurs fois dû descendre pour reprendre le train plus loin.',
                'en' => 'The Saint-Romain-en-Gier tunnel, on the Lyon to Saint-Étienne line, had long been threatening to fall in. Repair work employed about sixty workers there, one of the tracks had been taken out of use for the time being, and passengers had several times had to get out and rejoin the train further on.',
                'de' => 'Der Tunnel von Saint-Romain-en-Gier auf der Strecke von Lyon nach Saint-Étienne drohte seit langem einzustürzen. Instandsetzungsarbeiten beschäftigten dort etwa sechzig Arbeiter, eines der Gleise war vorübergehend aufgehoben worden, und die Reisenden hatten mehrmals aussteigen müssen, um den Zug weiter vorn wieder zu besteigen.',
                'sources' => ['vdr-saint-romain', 'wp-liste-1860'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le soir du 28 janvier 1862, vers 23 h 30 selon la liste de Wikipédia, le train de voyageurs n° 322, parti de Lyon à 22 h 45, a rencontré à cet endroit un train de marchandises venant de Saint-Étienne. Le mécanicien du train de marchandises a fait remonter à son train 109 mètres de la forte pente pour éviter le choc, mais le train de voyageurs, lancé à toute vitesse, n\'a pu s\'arrêter.',
                'en' => 'On the evening of 28 January 1862, at about 11.30 pm according to the Wikipedia list, passenger train no. 322, which had left Lyon at 10.45 pm, met a goods train from Saint-Étienne at that spot. The goods train\'s driver backed it 109 metres up the steep gradient to avoid the collision, but the passenger train, running at full speed, could not stop.',
                'de' => 'Am Abend des 28. Januar 1862, laut der Wikipedia-Liste gegen 23.30 Uhr, traf der um 22.45 Uhr aus Lyon abgefahrene Personenzug Nr. 322 an dieser Stelle auf einen Güterzug aus Saint-Étienne. Der Lokführer des Güterzugs ließ diesen 109 Meter das starke Gefälle hinaufrollen, um den Zusammenstoß zu vermeiden, doch der mit voller Geschwindigkeit fahrende Personenzug konnte nicht mehr anhalten.',
                'sources' => ['vdr-saint-romain', 'wp-liste-1860'],
            ],
            [
                'fr' => 'Le choc a été violent : la locomotive du train de marchandises, renversée, a broyé les trois premiers wagons du train de voyageurs.',
                'en' => 'The impact was violent: the goods train\'s locomotive, overturned, crushed the first three coaches of the passenger train.',
                'de' => 'Der Aufprall war heftig: Die umgestürzte Lokomotive des Güterzugs zertrümmerte die ersten drei Wagen des Personenzugs.',
                'sources' => ['vdr-saint-romain'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois hommes ont été tués sur le coup : le conducteur-chef du train de marchandises, écrasé par la locomotive, le conducteur du train de voyageurs et un voyageur.',
                'en' => 'Three men were killed outright: the head guard of the goods train, crushed by the locomotive, the guard of the passenger train and a passenger.',
                'de' => 'Drei Männer waren sofort tot: der Zugführer des Güterzugs, von der Lokomotive erdrückt, der Schaffner des Personenzugs und ein Reisender.',
                'sources' => ['vdr-saint-romain', 'wp-liste-1860'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon La Presse, le désastre serait dû, comme tant d\'autres, à une fausse manœuvre d\'un aiguilleur ; la liste de Wikipédia parle d\'une erreur d\'aiguillage et d\'une voie unique établie pour refaire le tunnel.',
                'en' => 'According to La Presse, the disaster was due, like so many others, to a wrong move by a pointsman; the Wikipedia list speaks of a switching error and of a single track laid for the rebuilding of the tunnel.',
                'de' => 'Laut La Presse ging das Unglück wie so viele andere auf eine falsche Handlung eines Weichenstellers zurück; die Wikipedia-Liste spricht von einem Weichenfehler und einem eingleisigen Betrieb wegen der Tunnelerneuerung.',
                'sources' => ['vdr-saint-romain', 'wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La Presse du 31 janvier 1862 ne donne pas la date de l\'accident, seulement « avant-hier » ; la date du 28 janvier vient de la liste de Wikipédia. Le journal ne mentionne que trois morts, sans parler de blessés.',
                'en' => 'La Presse of 31 January 1862 does not give the date of the accident, only "the day before yesterday"; the date of 28 January comes from the Wikipedia list. The newspaper mentions only three dead and says nothing of injured.',
                'de' => 'La Presse vom 31. Januar 1862 nennt kein Datum des Unglücks, nur „vorgestern“; das Datum 28. Januar stammt aus der Wikipedia-Liste. Die Zeitung nennt nur drei Tote und erwähnt keine Verletzten.',
                'sources' => ['vdr-saint-romain', 'wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [],
];
