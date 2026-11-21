<?php

// Contexte sourcé du déraillement de Dol-de-Bretagne du 4 août 1974 (recherche du 21/11/2026).
return [
    'slug' => '1974-08-04-dol-de-bretagne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-gare-dol' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Gare de Dol-de-Bretagne',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'paragraphe sur le déraillement du 4 août 1974',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Dol-de-Bretagne&oldid=240550489',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Paragraphe détaillé sur l\'accident, appuyé sur Paris-Normandie (6, 7 et 14 août 1974), Le Monde (6 août 1974) et Ouest-France (2013), non lus. Le passage reprend l\'entrée de la liste des accidents des années 1970.',
        ],
        'wp-liste-1970' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1970',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'section 1974',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1970&oldid=234343951#1974',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de l\'import : l\'entrée donne la date, la ligne, la vitesse, le bilan de dix morts et cite Paris-Normandie ; elle est donc sourcée, mais par une presse de 1974 non lue.',
        ],
        'forum-cheminots' => [
            'provider' => 'sites-web',
            'kind' => 'forum',
            'reliability' => 'forum',
            'title' => 'Les catastrophes ferroviaires (fil de discussion, page 7)',
            'publisher' => 'cheminots.net',
            'author' => null,
            'published' => '2018-05-11',
            'locator' => 'message « Le train fou de Dol de Bretagne » et réponse d\'août 2024',
            'url' => 'https://www.cheminots.net/topic/22490-les-catastrophes-ferroviaires/page/7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Témoignage d\'un membre dont un proche était à bord, qui donne 10 morts et une trentaine de blessés ; une réponse de 2024 cite un article de presse qui compte neuf morts et un train Caen - Rennes. Source faible, à ne pas utiliser seule.',
        ],
        'presse-lemonde-1974' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le déraillement de Dol-de-Bretagne pourrait s\'expliquer par un excès de vitesse',
            'publisher' => 'Le Monde',
            'author' => null,
            'published' => '1974-08-06',
            'locator' => null,
            'url' => 'https://www.lemonde.fr/archives/',
            'access' => 'paywall',
            'consulted_on' => '2026-11-21',
            'note' => 'Article de l\'époque cité par Wikipédia, repéré et non lu (archives payantes, adresse précise non retrouvée) ; à consulter directement.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 août 1974, un train de Caen à Rennes a déraillé à l\'approche de la gare de Dol-de-Bretagne (Ille-et-Vilaine), sur la ligne de Lison à Lamballe.',
                'en' => 'On 4 August 1974, a train from Caen to Rennes derailed on the approach to Dol-de-Bretagne station (Ille-et-Vilaine), on the Lison to Lamballe line.',
                'de' => 'Am 4. August 1974 entgleiste ein Zug von Caen nach Rennes bei der Einfahrt in den Bahnhof Dol-de-Bretagne (Ille-et-Vilaine) auf der Strecke von Lison nach Lamballe.',
                'sources' => ['wp-gare-dol', 'wp-liste-1970'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a abordé à 120 km/h une courbe limitée à 40 km/h et a déraillé.',
                'en' => 'The train entered a curve limited to 40 km/h at 120 km/h and derailed.',
                'de' => 'Der Zug fuhr mit 120 km/h in eine auf 40 km/h begrenzte Kurve und entgleiste.',
                'sources' => ['wp-gare-dol', 'wp-liste-1970'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait dix morts, parmi lesquels le mécanicien et le chef de train. Un message de forum évoque en plus une trentaine de blessés.',
                'en' => 'The accident killed ten people, among them the driver and the train manager. A forum post adds about thirty injured.',
                'de' => 'Bei dem Unfall starben zehn Menschen, darunter der Lokführer und der Zugführer. Ein Forumsbeitrag nennt zusätzlich etwa dreißig Verletzte.',
                'sources' => ['wp-gare-dol', 'wp-liste-1970', 'forum-cheminots'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause directe est la vitesse excessive dans la courbe. La SNCF, invoquant le secret de l\'instruction, a laissé circuler l\'explication d\'un malaise cardiaque du mécanicien qui aurait bloqué le dispositif de veille automatique (VACMA).',
                'en' => 'The direct cause was excessive speed in the curve. SNCF, citing the secrecy of the judicial investigation, let the explanation circulate that the driver had a heart problem which had jammed the automatic vigilance device (VACMA).',
                'de' => 'Die unmittelbare Ursache war die überhöhte Geschwindigkeit in der Kurve. Die SNCF ließ unter Berufung auf das Ermittlungsgeheimnis die Erklärung kursieren, ein Herzanfall des Lokführers habe die Sicherheitsfahrschaltung (VACMA) blockiert.',
                'sources' => ['wp-gare-dol', 'wp-liste-1970'],
            ],
            [
                'fr' => 'Selon Wikipédia, qui s\'appuie sur les témoignages de rescapés rapportés par Paris-Normandie, le mécanicien conduisait déjà bien avant l\'accident à une allure excessive pour la ligne et avait dû reculer après avoir freiné trop tard dans des gares précédentes. L\'autopsie aurait révélé une forte alcoolémie. Ce point n\'a pu être vérifié dans une source primaire.',
                'en' => 'According to Wikipedia, which relies on survivors\' accounts reported by Paris-Normandie, the driver had already been running at a speed excessive for the line well before the accident and had had to reverse after braking too late at earlier stations. The autopsy is said to have shown a high blood alcohol level. This point could not be checked in a primary source.',
                'de' => 'Laut Wikipedia, das sich auf von Paris-Normandie berichtete Aussagen Überlebender stützt, fuhr der Lokführer schon lange vor dem Unglück mit einer für die Strecke überhöhten Geschwindigkeit und musste zurücksetzen, nachdem er in früheren Bahnhöfen zu spät gebremst hatte. Die Obduktion soll einen hohen Alkoholspiegel ergeben haben. Dieser Punkt ließ sich in keiner Primärquelle überprüfen.',
                'sources' => ['wp-gare-dol', 'wp-liste-1970'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'L\'action pénale s\'est éteinte, les deux seules personnes susceptibles d\'être poursuivies étant mortes dans l\'accident.',
                'en' => 'The criminal action lapsed, as the only two people who could have been prosecuted had died in the accident.',
                'de' => 'Das Strafverfahren erlosch, da die beiden einzigen Personen, die hätten angeklagt werden können, bei dem Unfall gestorben waren.',
                'sources' => ['wp-gare-dol', 'wp-liste-1970'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan est de dix morts dans Wikipédia et dans un message de forum de 2018, mais un article de presse cité en 2024 sur ce même forum donne neuf morts. Le même message de forum parle d\'un « train fou » sans préciser la nature de l\'accident, alors que les autres sources décrivent un déraillement en courbe. Aucun rapport d\'enquête n\'a été retrouvé.',
                'en' => 'The toll is ten dead in Wikipedia and in a 2018 forum post, but a press article quoted in 2024 on the same forum gives nine dead. The same forum post speaks of a "runaway train" without stating the nature of the accident, whereas the other sources describe a derailment in a curve. No inquiry report was found.',
                'de' => 'Die Bilanz lautet bei Wikipedia und in einem Forumsbeitrag von 2018 zehn Tote, ein 2024 im selben Forum zitierter Pressetext nennt jedoch neun Tote. Derselbe Forumsbeitrag spricht von einem „Geisterzug“, ohne die Art des Unfalls zu nennen, während die übrigen Quellen eine Entgleisung in einer Kurve beschreiben. Ein Untersuchungsbericht wurde nicht gefunden.',
                'sources' => ['wp-gare-dol', 'wp-liste-1970', 'forum-cheminots'],
            ],
        ],
    ],
    'corrections' => [],
];
