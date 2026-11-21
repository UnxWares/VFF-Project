<?php

// Contexte sourcé de l'accident de Nantes du 4 juin 1933 (recherche du 21/11/2026).
return [
    'slug' => '1933-06-04-nantes',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1933' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1933',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1933&oldid=233504346#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 4 juin 1933 ; elle renvoie à Le Petit Journal et Le Petit Parisien (5 juin 1933), Le Petit Parisien (19 mai 1934) et L\'Ouest-Éclair (2 juin 1934).',
        ],
        'rail-bretagne' => [
            'provider' => 'sites-web',
            'kind' => 'forum',
            'reliability' => 'forum',
            'title' => '1933. L\'express déraille à Nantes : 14 tués et 168 blessés',
            'publisher' => 'Forum Rail Bretagne et Pays de la Loire',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://railbreopdl.forumactif.org/t5361-1933-lexpress-deraille-a-nantes-14-tues-et-168-blesses',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Deux messages de forum, qui s\'appuient sur un article de Ouest-France non retrouvé et se contredisent sur le lieu exact.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 juin 1933 vers 4 h 50, un train supplémentaire qui double le rapide 141 de Paris au Croisic aborde à 92 km/h une voie provisoire près de la gare de triage du Grand-Blottereau, à quatre kilomètres de Nantes.',
                'en' => 'On 4 June 1933 at about 4.50 a.m., a relief train running behind the Paris to Le Croisic express no. 141 entered a temporary track at 92 km/h near the Grand-Blottereau marshalling yard, four kilometres from Nantes.',
                'de' => 'Am 4. Juni 1933 gegen 4.50 Uhr fuhr ein Zusatzzug, der den Schnellzug 141 von Paris nach Le Croisic verdoppelte, mit 92 km/h nahe dem Rangierbahnhof Grand-Blottereau, vier Kilometer von Nantes entfernt, auf ein Hilfsgleis auf.',
                'sources' => ['wp-liste-1933'],
            ],
            [
                'fr' => 'Cette voie provisoire avait été posée trois jours plus tôt après le déraillement d\'un train de marchandises, et la vitesse y était limitée à 30 km/h. Le train, parti de Paris à 22 h, était bondé.',
                'en' => 'This temporary track had been laid three days earlier after a goods train derailed, and the speed limit on it was 30 km/h. The train, which had left Paris at 10 p.m., was crowded.',
                'de' => 'Dieses Hilfsgleis war drei Tage zuvor nach der Entgleisung eines Güterzugs gelegt worden, und die Geschwindigkeit war dort auf 30 km/h begrenzt. Der um 22 Uhr in Paris abgefahrene Zug war überfüllt.',
                'sources' => ['wp-liste-1933', 'rail-bretagne'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Malgré un freinage d\'urgence, la locomotive et le tender se couchent, puis le fourgon et les huit voitures, à caisse en bois, se télescopent. Sapeurs-pompiers et militaires dégagent les victimes.',
                'en' => 'Despite emergency braking, the locomotive and tender overturned, then the van and the eight wooden-bodied coaches telescoped. Firefighters and soldiers freed the victims.',
                'de' => 'Trotz Schnellbremsung kippten Lokomotive und Tender um, dann schoben sich der Packwagen und die acht Wagen mit Holzaufbau ineinander. Feuerwehrleute und Soldaten bargen die Opfer.',
                'sources' => ['wp-liste-1933', 'rail-bretagne'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de quatorze morts. Le nombre de blessés est de 124 selon la liste de Wikipédia, qui cite la presse du 5 juin 1933.',
                'en' => 'The toll is fourteen dead. The number of injured is 124 according to the Wikipedia list, which cites the press of 5 June 1933.',
                'de' => 'Die Bilanz beträgt vierzehn Tote. Die Zahl der Verletzten liegt nach der Wikipedia-Liste, die die Presse vom 5. Juni 1933 zitiert, bei 124.',
                'sources' => ['wp-liste-1933'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le mécanicien, indemne, a reconnu avoir mal interprété la signalisation.',
                'en' => 'The driver, who was unhurt, admitted that he had misread the signalling.',
                'de' => 'Der unverletzte Lokführer räumte ein, die Signalisierung falsch gedeutet zu haben.',
                'sources' => ['wp-liste-1933'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le mécanicien est condamné à un an de prison et 300 francs d\'amende, avec sursis, en raison de ses vingt-six ans de service jusque-là irréprochables.',
                'en' => 'The driver was sentenced to one year in prison and a fine of 300 francs, both suspended, in view of his twenty-six years of previously blameless service.',
                'de' => 'Der Lokführer wurde zu einem Jahr Haft und 300 Franc Geldstrafe, jeweils auf Bewährung, verurteilt, im Hinblick auf seine bis dahin einwandfreien sechsundzwanzig Dienstjahre.',
                'sources' => ['wp-liste-1933'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le fil de forum consulté donne 168 blessés, et non 124, et situe l\'accident à environ trois kilomètres de la gare, entre le dépôt du Grand-Blottereau et Sainte-Luce-sur-Loire. Il date la voie provisoire de la veille, après la rupture d\'un essieu, et affirme que le mécanicien n\'avait pas respecté la consigne de 30 km/h.',
                'en' => 'The forum thread consulted gives 168 injured rather than 124, and places the accident about three kilometres from the station, between the Grand-Blottereau depot and Sainte-Luce-sur-Loire. It dates the temporary track to the previous day, after an axle broke, and states that the driver had not observed the 30 km/h order.',
                'de' => 'Der eingesehene Forumsbeitrag nennt 168 statt 124 Verletzte und verortet den Unfall etwa drei Kilometer vom Bahnhof entfernt, zwischen dem Depot Grand-Blottereau und Sainte-Luce-sur-Loire. Er datiert das Hilfsgleis auf den Vortag, nach einem Achsbruch, und gibt an, der Lokführer habe die Anweisung von 30 km/h nicht beachtet.',
                'sources' => ['rail-bretagne', 'wp-liste-1933'],
            ],
        ],
    ],
];
