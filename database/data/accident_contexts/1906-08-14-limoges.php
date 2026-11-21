<?php

// Contexte sourcé de la dérive de wagons d'un train de marchandises jusqu'en gare de Limoges-Bénédictins, d'août 1906 (recherche du 21/11/2026).
return [
    'slug' => '1906-08-14-limoges',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1906' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1906',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1906&oldid=233590740#Août',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 14 août 1906, d\'après Le Petit Journal du 15 août : après une rupture d\'attelage, dix wagons d\'un train de marchandises de la ligne de Limoges au Dorat repartent en arrière et heurtent en gare de Limoges-Bénédictins une locomotive de manœuvre, projetée sur un train de voyageurs en partance ; une grue hydraulique est détruite ; le mécanicien et le chauffeur de la machine heurtée sont tués, une voyageuse frappée par une portière est blessée.',
        ],
        'utrechtsche-courant-1906-08-18' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Utrechtsche courant du 18 août 1906',
            'publisher' => 'Utrechtsche courant',
            'author' => null,
            'published' => '1906-08-18',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMUTRA04:253285370:mpeg21:a00015',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Au sommet de la rampe à la sortie de Limoges, une chaîne d\'attelage a cassé ; les wagons sont revenus à grande vitesse en gare et se sont jetés sur la locomotive d\'un train prêt à partir ; le mécanicien a été tué sur le coup, le chauffeur est mort sur le chemin de l\'hôpital ; une voyageuse qui montait a eu des côtes cassées par une portière.',
        ],
        'arnhemsche-courant-1906-08-16' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Arnhemsche courant du 16 août 1906',
            'publisher' => 'Arnhemsche courant',
            'author' => null,
            'published' => '1906-08-16',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB08:000097503:mpeg21:a0030',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. À la gare des Bénédictins de Limoges, un train de marchandises a heurté une locomotive en manœuvre, projetée sur un train de voyageurs ; le mécanicien et le chauffeur du train de voyageurs ont été tués, plusieurs voyageurs plus ou moins grièvement blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 14 août 1906 au matin, l\'attelage d\'un train de marchandises de la ligne de Limoges au Dorat s\'est rompu dans la rampe à la sortie de Limoges. Les wagons détachés sont redescendus jusqu\'en gare de Limoges-Bénédictins, où ils ont heurté une locomotive de manœuvre. Celle-ci a été projetée sur un train de voyageurs prêt à partir, et une grue hydraulique a été détruite.',
                'en' => 'On the morning of 14 August 1906, a coupling broke on a goods train of the Limoges to Le Dorat line on the climb out of Limoges. The loose wagons ran back down into Limoges-Bénédictins station, where they hit a shunting engine. It was thrown against a passenger train about to leave, and a water crane was destroyed.',
                'de' => 'Am Morgen des 14. August 1906 riss in der Steigung hinter Limoges die Kupplung eines Güterzugs der Strecke Limoges-Le Dorat. Die abgerissenen Wagen rollten in den Bahnhof Limoges-Bénédictins zurück und prallten auf eine Rangierlokomotive, die gegen einen abfahrbereiten Personenzug geschleudert wurde. Ein Wasserkran wurde zerstört.',
                'sources' => ['wp-liste-1906', 'utrechtsche-courant-1906-08-18', 'arnhemsche-courant-1906-08-16'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux agents de machine ont été tués : le mécanicien sur le coup, le chauffeur sur le chemin de l\'hôpital. Une voyageuse qui montait en voiture a été blessée par une portière.',
                'en' => 'Two enginemen were killed: the driver outright, the stoker on the way to hospital. A woman passenger who was boarding was hurt by a carriage door.',
                'de' => 'Zwei Mann Lokomotivpersonal kamen ums Leben, der Lokomotivführer sofort, der Heizer auf dem Weg ins Krankenhaus. Eine einsteigende Reisende wurde von einer Wagentür verletzt.',
                'sources' => ['wp-liste-1906', 'utrechtsche-courant-1906-08-18'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les morts étaient le mécanicien et le chauffeur de la machine heurtée selon la liste de Wikipédia, ceux du train de voyageurs selon l\'Arnhemsche courant, qui parle aussi de plusieurs voyageurs blessés là où la liste n\'en compte qu\'une.',
                'en' => 'The dead were the driver and stoker of the engine that was struck according to the Wikipedia list, those of the passenger train according to the Arnhemsche courant, which also speaks of several injured passengers where the list counts only one.',
                'de' => 'Die Toten waren nach der Wikipedia-Liste Lokomotivführer und Heizer der getroffenen Maschine, nach der Arnhemsche courant die des Personenzugs; diese spricht auch von mehreren verletzten Reisenden, wo die Liste nur eine Verletzte zählt.',
                'sources' => ['wp-liste-1906', 'arnhemsche-courant-1906-08-16'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1906', 'utrechtsche-courant-1906-08-18', 'arnhemsche-courant-1906-08-16'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Petit Journal du 15 août 1906) et la presse néerlandaise des 16 et 18 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Petit Journal of 15 August 1906) and the Dutch press of the 16th and 18th; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste (Le Petit Journal vom 15. August 1906) und der niederländischen Presse vom 16. und 18.; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
