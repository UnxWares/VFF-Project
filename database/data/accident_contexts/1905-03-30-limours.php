<?php

// Contexte sourcé du déraillement de la ligne de Sceaux (Arcueil-Cachan - Bourg-la-Reine) du 30 mars 1905 (recherche du 21/11/2026).
return [
    'slug' => '1905-03-30-limours',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1905' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1905',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1905&oldid=233590727#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne de Sceaux, train du Luxembourg pour Limours, tranchée en courbe entre Arcueil-Cachan et Bourg-la-Reine, incendie, trois morts et neuf blessés ; renvoie à Le Matin du 31 mars 1905 (non lu).',
        ],
        'delpher-tijd-1905-03-31' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeval',
            'publisher' => 'De Tijd (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1905-03-31',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010986465:mpeg21:a0008',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du 30 mars : déraillement vers 7 h sur la ligne de Sceaux entre Arcueil-Cachan et Bourg-la-Reine, trois morts, six blessés graves (le nom de la destination est déformé en « Limoges »).',
        ],
        'delpher-tilburgsche-1905-03-31' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorweg-ongeluk',
            'publisher' => 'Nieuwe Tilburgsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1905-03-31',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010157916:mpeg21:a0014',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche, avec l\'incendie causé par les lampes, l\'évacuation vers un hôpital et une courbe très serrée ; cause inconnue.',
        ],
        'delpher-courant-1905-03-31' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Korte Berichten',
            'publisher' => 'De Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1905-03-31',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB19:003539079:mpeg21:a00012',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : deux mécaniciens et un voyageur tués, six blessés, aucun étranger parmi les victimes ; noms de lieux déformés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 30 mars 1905, vers 6 h 30 ou 7 h du matin, un train de la ligne de Sceaux, parti de la gare du Luxembourg pour Limours, a déraillé entre les stations d\'Arcueil-Cachan et de Bourg-la-Reine. La liste de Wikipédia précise que c\'était dans une tranchée en courbe, où la vitesse limitée à 65 km/h restait excessive vu le mauvais état de la voie.',
                'en' => 'On Thursday 30 March 1905, at about 6.30 or 7 am, a train on the Sceaux line, which had left the Luxembourg station for Limours, derailed between the stations of Arcueil-Cachan and Bourg-la-Reine. The Wikipedia list adds that it happened in a curved cutting, where the speed limit of 65 km/h was still too high given the poor state of the track.',
                'de' => 'Am Donnerstag, dem 30. März 1905, entgleiste gegen 6.30 oder 7 Uhr morgens ein Zug der Ligne de Sceaux, der vom Bahnhof Luxembourg nach Limours gefahren war, zwischen den Stationen Arcueil-Cachan und Bourg-la-Reine. Die Wikipedia-Liste ergänzt, dies sei in einem Einschnitt in einer Kurve geschehen, wo die auf 65 km/h begrenzte Geschwindigkeit angesichts des schlechten Gleiszustands noch zu hoch war.',
                'sources' => ['wp-liste-1905', 'delpher-tijd-1905-03-31'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, la locomotive a déraillé, puis le tender, le fourgon et les voitures l\'ont percutée et se sont entassés entre les parois de la tranchée ; un incendie s\'est déclaré. La presse néerlandaise du lendemain parle de plusieurs voitures renversées et d\'un incendie provoqué par les lampes.',
                'en' => 'According to the Wikipedia list, the locomotive derailed, then the tender, the van and the coaches ran into it and piled up between the sides of the cutting; a fire broke out. The Dutch press the next day speaks of several overturned coaches and a fire started by the lamps.',
                'de' => 'Nach der Wikipedia-Liste entgleiste die Lokomotive, dann prallten Tender, Packwagen und Wagen auf sie und stauten sich zwischen den Wänden des Einschnitts; es brach ein Feuer aus. Die niederländische Presse vom folgenden Tag berichtet von mehreren umgestürzten Wagen und einem durch die Lampen ausgelösten Brand.',
                'sources' => ['wp-liste-1905', 'delpher-tilburgsche-1905-03-31'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois personnes sont mortes (selon les dépêches : le mécanicien, le chauffeur et un voyageur) et six ont été grièvement blessées et transportées à l\'hôpital selon la presse néerlandaise ; la liste de Wikipédia compte neuf blessés.',
                'en' => 'Three people died (according to the dispatches: the driver, the fireman and a passenger) and six were seriously injured and taken to hospital according to the Dutch press; the Wikipedia list counts nine injured.',
                'de' => 'Drei Menschen starben (laut den Depeschen der Lokführer, der Heizer und ein Reisender), und nach der niederländischen Presse wurden sechs schwer verletzt und in ein Krankenhaus gebracht; die Wikipedia-Liste zählt neun Verletzte.',
                'sources' => ['delpher-tijd-1905-03-31', 'delpher-tilburgsche-1905-03-31', 'wp-liste-1905'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La presse du 31 mars 1905 situait l\'accident dans une courbe très serrée et disait la cause du déraillement inconnue ; la liste de Wikipédia attribue l\'accident à une vitesse excessive pour l\'état de la voie.',
                'en' => 'The press of 31 March 1905 placed the accident in a very sharp curve and said the cause of the derailment was unknown; the Wikipedia list attributes it to a speed too high for the state of the track.',
                'de' => 'Die Presse vom 31. März 1905 verortete den Unfall in einer sehr scharfen Kurve und nannte die Entgleisungsursache unbekannt; die Wikipedia-Liste führt ihn auf eine für den Gleiszustand zu hohe Geschwindigkeit zurück.',
                'sources' => ['delpher-tilburgsche-1905-03-31', 'wp-liste-1905'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche indique Limours, destination du train ; les deux sources situent l\'accident entre Arcueil-Cachan et Bourg-la-Reine, sans dire sur quelle commune. L\'heure varie (vers 6 h 30 pour la liste, vers 7 h pour la presse néerlandaise), le nombre de blessés aussi (neuf ou six graves).',
                'en' => 'The record gives Limours, the train\'s destination; both sources place the accident between Arcueil-Cachan and Bourg-la-Reine, without saying in which commune. The time varies (about 6.30 am in the list, about 7 am in the Dutch press), and so does the number of injured (nine, or six seriously).',
                'de' => 'Der Datensatz nennt Limours, das Fahrtziel des Zuges; beide Quellen verorten den Unfall zwischen Arcueil-Cachan und Bourg-la-Reine, ohne die Gemeinde zu nennen. Die Uhrzeit schwankt (gegen 6.30 Uhr laut Liste, gegen 7 Uhr laut niederländischer Presse), ebenso die Zahl der Verletzten (neun oder sechs schwer).',
                'sources' => ['wp-liste-1905', 'delpher-tijd-1905-03-31', 'delpher-tilburgsche-1905-03-31'],
            ],
        ],
    ],
    'corrections' => [],
];
