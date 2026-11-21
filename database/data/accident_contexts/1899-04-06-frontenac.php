<?php

// Contexte sourcé de la collision de deux trains de ballast à Frontenac en avril 1899 (recherche du 21/11/2026).
return [
    'slug' => '1899-04-06-frontenac',
    'checked_on' => '2026-11-21',
    'sources' => [
        'vdr-frontenac' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1899, Collision de Frontenac',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1899-collision-de-frontenac/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Gironde du 8 avril 1899.',
        ],
        'temps-1899-04-10' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 10 avril 1899',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1899-04-10',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k235975k/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1899',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1899',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : collision de deux trains de ballast en gare de Frontenac, sur la ligne de Bordeaux-Bastide à Eymet, deux morts ; cite Le Temps du 10 avril 1899.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Deux trains de ballast circulaient sur la ligne de Bordeaux-Bastide à Eymet, en cours de travaux. La rencontre a eu lieu dans une courbe, sur le petit pont métallique jeté au-dessus du ruisseau d\'Engranne, qui sépare les communes de Frontenac et de Baigneaux. Dans cette courbe, les trains ne s\'aperçoivent qu\'à environ 150 mètres.',
                'en' => 'Two ballast trains were running on the Bordeaux-Bastide to Eymet line, then under works. They met on a curve, on the small metal bridge over the Engranne stream, which separates the communes of Frontenac and Baigneaux. On this curve the trains can see each other only at about 150 metres.',
                'de' => 'Zwei Schotterzüge verkehrten auf der damals im Ausbau befindlichen Strecke von Bordeaux-Bastide nach Eymet. Sie trafen in einer Kurve auf der kleinen Metallbrücke über den Bach Engranne aufeinander, der die Gemeinden Frontenac und Baigneaux trennt. In dieser Kurve sehen sich die Züge erst auf etwa 150 Meter.',
                'sources' => ['vdr-frontenac', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La machine 510, qui revenait vers Sauveterre en remorquant les wagons vides du ballast transporté le matin, a tamponné le train de ballast de la machine 887 venant de Bellefond. Le tender de la machine 510 est monté sur la chaudière de la machine 887, qui a déraillé, et quatre ou cinq wagons de ballast de chaque train se sont brisés contre les machines. La chaudière de la machine 510 a éclaté au moment du choc.',
                'en' => 'Engine 510, returning towards Sauveterre hauling the empty wagons of the ballast carried that morning, ran into the ballast train of engine 887 coming from Bellefond. The tender of engine 510 climbed onto the boiler of engine 887, which derailed, and four or five ballast wagons of each train were smashed against the engines. The boiler of engine 510 burst at the moment of impact.',
                'de' => 'Die Lokomotive 510, die die leeren Wagen des am Morgen beförderten Schotters nach Sauveterre zurückzog, fuhr auf den Schotterzug der aus Bellefond kommenden Lokomotive 887 auf. Der Tender der Lokomotive 510 schob sich auf den Kessel der Lokomotive 887, die entgleiste, und je vier oder fünf Schotterwagen beider Züge zerschellten an den Lokomotiven. Der Kessel der Lokomotive 510 platzte im Augenblick des Aufpralls.',
                'sources' => ['vdr-frontenac'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien de la machine 510, pris par la jambe gauche entre le tender et la chaudière, n\'a pas pu être dégagé et est mort vers une heure et demie. Le chauffeur, brûlé sur tout le corps par la vapeur, était dans un état alarmant. Quatre jeunes filles montées sur le deuxième wagon de ballast ont été projetées : une seule était indemne, les autres étaient contusionnées ou brûlées. Le mécanicien et le chauffeur de la machine 887 n\'ont reçu que de très légères contusions.',
                'en' => 'The driver of engine 510, caught by the left leg between the tender and the boiler, could not be freed and died at about half past one. The fireman, scalded over his whole body by the steam, was in an alarming state. Four girls riding on the second ballast wagon were thrown off: only one was unhurt, the others were bruised or burned. The driver and fireman of engine 887 received only very slight bruises.',
                'de' => 'Der Lokführer der Lokomotive 510, mit dem linken Bein zwischen Tender und Kessel eingeklemmt, konnte nicht befreit werden und starb gegen halb zwei. Der Heizer, am ganzen Körper vom Dampf verbrüht, befand sich in bedrohlichem Zustand. Vier Mädchen, die auf dem zweiten Schotterwagen mitfuhren, wurden herausgeschleudert: Nur eines blieb unverletzt, die anderen erlitten Prellungen oder Verbrennungen. Lokführer und Heizer der Lokomotive 887 erlitten nur sehr leichte Prellungen.',
                'sources' => ['vdr-frontenac'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon La Gironde, le mécanicien de la machine 510 aurait reconnu avant de mourir qu\'il avait commis la faute de ne pas attendre la machine 887 à la gare de Saint-Brice, comme cela lui avait été prescrit.',
                'en' => 'According to La Gironde, the driver of engine 510 is said to have admitted before dying that he had made the mistake of not waiting for engine 887 at Saint-Brice station, as he had been instructed.',
                'de' => 'Nach La Gironde soll der Lokführer der Lokomotive 510 vor seinem Tod eingeräumt haben, den Fehler begangen zu haben, die Lokomotive 887 nicht wie angewiesen im Bahnhof Saint-Brice abzuwarten.',
                'sources' => ['vdr-frontenac'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La Gironde du 8 avril 1899 titre « Un mort. Quatre blessés », alors que la liste de Wikipédia, d\'après Le Temps du 10 avril, compte deux morts. La Gironde indique aussi que la voie était à peu près déblayée le mercredi soir, après une collision survenue dans la journée, ce qui désigne le mercredi 5 avril, alors que la liste de Wikipédia donne le 6 avril.',
                'en' => 'La Gironde of 8 April 1899 is headed "One dead. Four injured", whereas the Wikipedia list, citing Le Temps of 10 April, counts two dead. La Gironde also states that the line was more or less cleared on Wednesday evening, after a collision that took place during the day, which points to Wednesday 5 April, whereas the Wikipedia list gives 6 April.',
                'de' => 'La Gironde vom 8. April 1899 titelt „Ein Toter. Vier Verletzte“, während die Wikipedia-Liste unter Berufung auf Le Temps vom 10. April zwei Tote zählt. La Gironde gibt außerdem an, die Strecke sei am Mittwochabend nach einem im Lauf des Tages erfolgten Zusammenstoß einigermaßen geräumt gewesen, was auf Mittwoch, den 5. April, hindeutet, während die Wikipedia-Liste den 6. April nennt.',
                'sources' => ['vdr-frontenac', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
