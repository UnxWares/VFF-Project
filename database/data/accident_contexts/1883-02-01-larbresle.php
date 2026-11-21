<?php

// Contexte sourcé de la collision près de Saint-Romain-de-Popey du 1er février 1883 (recherche du 21/11/2026).
return [
    'slug' => '1883-02-01-larbresle',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1883',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1883',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : entrée du 1er février 1883 (machine haut-le-pied contre un train de voyageurs entre L\'Arbresle et Saint-Romain-de-Popey, un mort et neuf blessés, condamnation du responsable) ; renvoie à Le Temps du 17 avril 1883.',
        ],
        'vdr-arbresle-1883' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1883 - Tamponnement à la gare de Saint-Romain-de-Popey',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1883-tamponnement-a-la-gare-de-saint-romain-de-popey/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Progrès de Lyon du 3 février 1883 et une dépêche de Villefranche du 2 février 1883 (journal non indiqué sur le site).',
        ],
        'progres-1883-02-03' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Progrès de Lyon du 3 février 1883',
            'publisher' => 'Le Progrès de Lyon',
            'author' => null,
            'published' => '1883-02-03',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bd6t52684676g/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 1er février 1883, vers 17 h 45, le train de voyageurs n° 616, de huit voitures, parti de Lyon-Perrache à 16 h pour Paris par le Bourbonnais, sortait du tunnel de Saint-Romain sur la ligne du P.L.M., à voie unique de Saint-Germain-au-Mont-d\'Or à Roanne. À environ deux kilomètres et demi de la gare de Saint-Romain-de-Popey, la voie décrit une courbe prononcée et monte en rampe de dix millimètres par mètre.',
                'en' => 'On Thursday 1 February 1883, at about 5.45 pm, passenger train no. 616, of eight coaches, which had left Lyon-Perrache at 4 pm for Paris via the Bourbonnais, was coming out of the Saint-Romain tunnel on the P.L.M. line, single track from Saint-Germain-au-Mont-d\'Or to Roanne. About two and a half kilometres from Saint-Romain-de-Popey station, the line runs through a sharp curve and climbs a gradient of ten millimetres per metre.',
                'de' => 'Am Donnerstag, dem 1. Februar 1883, gegen 17.45 Uhr, kam der Reisezug Nr. 616 mit acht Wagen, der um 16 Uhr in Lyon-Perrache nach Paris über das Bourbonnais abgefahren war, auf der PLM-Strecke, die von Saint-Germain-au-Mont-d\'Or nach Roanne eingleisig ist, aus dem Tunnel von Saint-Romain. Etwa zweieinhalb Kilometer vom Bahnhof Saint-Romain-de-Popey entfernt verläuft die Strecke in einer scharfen Kurve und steigt mit zehn Millimetern pro Meter an.',
                'sources' => ['vdr-arbresle-1883'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Au point kilométrique 473,500, le train 616 a été tamponné par une machine isolée qui allait vers L\'Arbresle : elle venait de renforcer un autre train jusqu\'à Tarare et rentrait au dépôt. Lancée par la descente de la rampe, elle a causé un choc terrible : les deux locomotives ont été broyées, le tender de celle du train 616 est entré dans le fourgon et une voiture de seconde classe a été coupée en deux. Les blessés ont été conduits dans une ferme voisine, et des trains de secours sont venus de Lyon et de Tarare.',
                'en' => 'At kilometre point 473.500, train 616 was hit by a light engine heading for L\'Arbresle: it had just banked another train as far as Tarare and was returning to the depot. Running at speed down the gradient, it caused a terrible impact: both locomotives were crushed, the tender of train 616\'s engine drove into the van, and a second-class coach was cut in two. The injured were taken to a nearby farm, and relief trains came from Lyon and Tarare.',
                'de' => 'Bei Kilometerpunkt 473,500 wurde der Zug 616 von einer allein fahrenden Lokomotive gerammt, die nach L\'Arbresle unterwegs war: Sie hatte gerade einen anderen Zug bis Tarare verstärkt und fuhr zum Depot zurück. Vom Gefälle beschleunigt, verursachte sie einen furchtbaren Aufprall: Beide Lokomotiven wurden zerdrückt, der Tender der Lokomotive des Zuges 616 drang in den Packwagen ein, und ein Wagen zweiter Klasse wurde in zwei Teile gerissen. Die Verletzten wurden in einen nahen Bauernhof gebracht, und aus Lyon und Tarare kamen Hilfszüge.',
                'sources' => ['vdr-arbresle-1883'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Onze personnes ont été trouvées dans les débris, cinq voyageurs et six agents de la Compagnie. Deux sont mortes dans les heures qui ont suivi, un jeune voyageur et le chauffeur de la machine isolée ; Le Progrès de Lyon annonce ensuite le décès d\'une femme voyageuse, ce qui porte à trois morts et huit blessés. Une dépêche de Villefranche du 2 février parlait de deux morts, et la note de la Compagnie de huit blessés dont quatre agents.',
                'en' => 'Eleven people were found in the wreckage, five passengers and six company employees. Two died in the hours that followed, a young passenger and the fireman of the light engine; Le Progrès de Lyon then reports the death of a woman passenger, bringing the toll to three dead and eight injured. A dispatch from Villefranche of 2 February spoke of two dead, and the company\'s note of eight injured, four of them employees.',
                'de' => 'Elf Personen wurden in den Trümmern gefunden, fünf Reisende und sechs Angestellte der Gesellschaft. Zwei starben in den folgenden Stunden, ein junger Reisender und der Heizer der allein fahrenden Lokomotive; Le Progrès de Lyon meldet anschließend den Tod einer Reisenden, womit sich drei Tote und acht Verletzte ergeben. Eine Depesche aus Villefranche vom 2. Februar sprach von zwei Toten, die Mitteilung der Gesellschaft von acht Verletzten, darunter vier Angestellte.',
                'sources' => ['vdr-arbresle-1883'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La machine isolée devait s\'arrêter à Saint-Romain-de-Popey pour laisser passer le train 616 : le bulletin de circulation remis à Tarare portait la mention « Arrêt à Saint-Romain-de-Popey pour garage du train 616 ». Elle a traversé la gare à grande vitesse alors que le disque et le sémaphore étaient à l\'arrêt, ce que le chef de gare a prouvé par des attestations de témoins. Le Progrès de Lyon met la responsabilité sur le mécanicien de la machine isolée, sans savoir s\'il s\'était endormi ou distrait.',
                'en' => 'The light engine was to stop at Saint-Romain-de-Popey to let train 616 pass: the running order handed over at Tarare bore the note "Stop at Saint-Romain-de-Popey to clear train 616". It went through the station at high speed while the disc and semaphore signals were at stop, which the station master proved with witness statements. Le Progrès de Lyon puts the responsibility on the driver of the light engine, without knowing whether he had fallen asleep or been distracted.',
                'de' => 'Die allein fahrende Lokomotive sollte in Saint-Romain-de-Popey halten, um den Zug 616 durchzulassen: Der in Tarare ausgehändigte Fahrschein trug den Vermerk „Halt in Saint-Romain-de-Popey zum Ausweichen des Zuges 616“. Sie durchfuhr den Bahnhof mit hoher Geschwindigkeit, obwohl Scheibe und Semaphor auf Halt standen, was der Bahnhofsvorsteher durch Zeugenaussagen belegte. Le Progrès de Lyon legt die Verantwortung auf den Lokführer der allein fahrenden Lokomotive, ohne zu wissen, ob er eingeschlafen oder abgelenkt war.',
                'sources' => ['vdr-arbresle-1883'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le responsable de la collision, qui invoquait une fatigue excessive due à la surcharge de travail, a été condamné par le tribunal correctionnel de Villefranche à 15 mois de prison et 300 francs d\'amende.',
                'en' => 'According to the Wikipedia list, the person responsible for the collision, who pleaded excessive tiredness caused by overwork, was sentenced by the criminal court of Villefranche to 15 months in prison and a 300-franc fine.',
                'de' => 'Nach der Wikipedia-Liste wurde der Verantwortliche der Kollision, der übermäßige Müdigkeit durch Arbeitsüberlastung geltend machte, vom Strafgericht von Villefranche zu 15 Monaten Haft und 300 Francs Geldstrafe verurteilt.',
                'sources' => ['wp-liste-1880'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia compte un mort et neuf blessés, alors que la presse de l\'époque, transcrite sur le site, compte deux morts le soir même, puis trois morts et huit blessés dans Le Progrès de Lyon, et deux morts dans une dépêche de Villefranche. La liste situe l\'accident entre L\'Arbresle et Saint-Romain-de-Popey ; Le Progrès de Lyon le place à 1 600 mètres de la gare de Saint-Romain-de-Popey, et la note de la Compagnie entre Saint-Romain et L\'Arbresle.',
                'en' => 'The Wikipedia list counts one dead and nine injured, whereas the press of the time, transcribed on the site, counts two dead that same evening, then three dead and eight injured in Le Progrès de Lyon, and two dead in a dispatch from Villefranche. The list places the accident between L\'Arbresle and Saint-Romain-de-Popey; Le Progrès de Lyon puts it 1,600 metres from Saint-Romain-de-Popey station, and the company\'s note between Saint-Romain and L\'Arbresle.',
                'de' => 'Die Wikipedia-Liste zählt einen Toten und neun Verletzte, während die auf der Website transkribierte zeitgenössische Presse noch am selben Abend zwei Tote, dann in Le Progrès de Lyon drei Tote und acht Verletzte und in einer Depesche aus Villefranche zwei Tote zählt. Die Liste verortet den Unfall zwischen L\'Arbresle und Saint-Romain-de-Popey; Le Progrès de Lyon setzt ihn 1 600 Meter vom Bahnhof Saint-Romain-de-Popey an, die Mitteilung der Gesellschaft zwischen Saint-Romain und L\'Arbresle.',
                'sources' => ['vdr-arbresle-1883', 'wp-liste-1880'],
            ],
        ],
    ],
    'corrections' => [],
];
