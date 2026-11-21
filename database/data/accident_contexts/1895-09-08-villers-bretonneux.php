<?php

// Contexte sourcé du déraillement de l'omnibus 1256 près de Villers-Bretonneux du 8 septembre 1895 (recherche du 21/11/2026).
return [
    'slug' => '1895-09-08-villers-bretonneux',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1895',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1895',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : entrée du 8 septembre 1895 (omnibus Tergnier-Amiens déraillé dans une courbe après Villers-Bretonneux, 1 mort, 12 blessés dont 2 graves) ; renvoie à Le Figaro des 9 et 10 septembre 1895.',
        ],
        'vdr-villers-1895' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1895 - Accident sur la ligne Amiens-Tergnier à Aubigny (80)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1895-accident-sur-la-ligne-amiens-tergnier-a-aubigny-80/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Figaro du 10 septembre 1895 et Le XIXe siècle du 11 septembre 1895.',
        ],
        'figaro-1895-09-10' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 10 septembre 1895',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1895-09-10',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2833952/f1',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'xixe-siecle-1895-09-11' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le XIXe siècle du 11 septembre 1895',
            'publisher' => 'Le XIXe siècle',
            'author' => null,
            'published' => '1895-09-11',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k7563411c/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 8 septembre 1895, le train omnibus 1256 de Tergnier à Amiens, composé d\'un fourgon et de sept voitures, est arrivé à Villers-Bretonneux avec sept minutes de retard, puis a repris sa route à toute vitesse. Il transportait une cinquantaine de voyageurs.',
                'en' => 'On Sunday 8 September 1895, stopping train 1256 from Tergnier to Amiens, made up of a van and seven coaches, reached Villers-Bretonneux seven minutes late and then set off again at full speed. It was carrying about fifty passengers.',
                'de' => 'Am Sonntag, dem 8. September 1895, erreichte der Personenzug 1256 von Tergnier nach Amiens, bestehend aus einem Packwagen und sieben Wagen, Villers-Bretonneux mit sieben Minuten Verspätung und fuhr dann mit voller Geschwindigkeit weiter. Er beförderte etwa fünfzig Reisende.',
                'sources' => ['vdr-villers-1895'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Peu avant la halte de Blangy-Glisy, au kilomètre 12,400, dans une courbe très prononcée suivie d\'une pente assez forte, la locomotive est sortie des rails en entraînant tout le train. Le mécanicien a renversé la vapeur, mais le train a continué sur près de deux cents mètres dans le ballast ; la locomotive s\'est enfoncée dans un talus de six mètres. Le fourgon s\'est couché sur le talus et la première voiture de troisième classe s\'est écrasée sur lui ; les autres voitures se sont renversées de part et d\'autre de la voie.',
                'en' => 'Shortly before the Blangy-Glisy halt, at kilometre 12.400, in a very sharp curve followed by a fairly steep gradient, the locomotive left the rails, dragging the whole train with it. The driver put the engine into reverse, but the train carried on for nearly two hundred metres through the ballast; the locomotive dug into a six-metre embankment. The van lay down on the embankment and the first third-class coach crashed into it; the other coaches overturned on either side of the line.',
                'de' => 'Kurz vor der Haltestelle Blangy-Glisy bei Kilometer 12,400 sprang die Lokomotive in einer sehr scharfen Kurve mit anschließendem ziemlich starkem Gefälle aus dem Gleis und riss den ganzen Zug mit. Der Lokführer gab Gegendampf, doch der Zug rutschte noch fast zweihundert Meter durch den Schotter; die Lokomotive bohrte sich in eine sechs Meter hohe Böschung. Der Packwagen legte sich auf die Böschung und der erste Wagen dritter Klasse zerschellte an ihm; die übrigen Wagen kippten zu beiden Seiten des Gleises um.',
                'sources' => ['vdr-villers-1895'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est d\'un mort, une fillette de sept ans, et de neuf blessés, selon Le Figaro du 10 septembre 1895. Les trois premiers compartiments de la voiture écrasée contre le fourgon étaient broyés et il a fallu en retirer les blessés par-dessous ; la plupart des autres voyageurs n\'avaient que des contusions. Le mécanicien et le chauffeur n\'ont pas été blessés. Un train de secours demandé à Amiens est arrivé vers trois heures, et les blessés sont arrivés à Amiens vers quatre heures.',
                'en' => 'The toll is one dead, a seven-year-old girl, and nine injured, according to Le Figaro of 10 September 1895. The first three compartments of the coach crushed against the van were smashed and the injured had to be pulled out from underneath; most of the other passengers had only bruises. The driver and fireman were unhurt. A relief train requested from Amiens arrived at about three o\'clock, and the injured reached Amiens at about four.',
                'de' => 'Die Bilanz lautet nach Le Figaro vom 10. September 1895 ein Toter, ein siebenjähriges Mädchen, und neun Verletzte. Die ersten drei Abteile des gegen den Packwagen gedrückten Wagens waren zertrümmert, und die Verletzten mussten von unten geborgen werden; die meisten übrigen Reisenden hatten nur Prellungen. Lokführer und Heizer blieben unverletzt. Ein aus Amiens angeforderter Hilfszug traf gegen drei Uhr ein, und die Verletzten erreichten Amiens gegen vier Uhr.',
                'sources' => ['vdr-villers-1895', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les enquêteurs ont constaté que, sur deux cents mètres, la voie était tordue, les attaches des rails brisées et les rails arrachés. Sans pouvoir déterminer exactement les causes, Le Figaro les attribue à un ébranlement de la voie, qui était récemment reconstruite.',
                'en' => 'The investigators found that over two hundred metres the track was twisted, the rail fastenings broken and the rails torn out. Without being able to determine the causes exactly, Le Figaro attributes them to a shaking of the track, which had recently been rebuilt.',
                'de' => 'Die Ermittler stellten fest, dass das Gleis auf zweihundert Metern verbogen, die Schienenbefestigungen gebrochen und die Schienen herausgerissen waren. Ohne die Ursachen genau bestimmen zu können, führt Le Figaro sie auf eine Erschütterung des Gleises zurück, das erst kürzlich neu gebaut worden war.',
                'sources' => ['vdr-villers-1895'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia compte un mort et douze blessés, dont deux graves ; Le Figaro du 10 septembre 1895 compte un mort et neuf blessés.',
                'en' => 'The Wikipedia list counts one dead and twelve injured, two of them seriously; Le Figaro of 10 September 1895 counts one dead and nine injured.',
                'de' => 'Die Wikipedia-Liste zählt einen Toten und zwölf Verletzte, davon zwei schwer; Le Figaro vom 10. September 1895 zählt einen Toten und neun Verletzte.',
                'sources' => ['vdr-villers-1895', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
