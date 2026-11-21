<?php

// Contexte sourcé de la collision de septembre 1928 près de Barchetta (fiche « Bastia ») (recherche du 21/11/2026).
return [
    'slug' => '1928-09-19-bastia',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-chemins-de-fer-corse' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Chemins de fer de la Corse',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section sur le matériel roulant, automotrices Crochat',
            'url' => 'https://fr.wikipedia.org/wiki/Chemins_de_fer_de_la_Corse',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Autorail Ae 51 détruit dans une collision le 18 septembre 1928 au point kilométrique 27,3 près de Barchetta, suites pour l\'Ae 52.',
        ],
        'wp-gare-barchetta' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Gare de Barchetta',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/wiki/Gare_de_Barchetta',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Gare au point kilométrique 29,6 de la ligne de Bastia à Ajaccio, sur la commune de Volpajola.',
        ],
        'wp-liste-1928' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1928',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1928&oldid=233501561#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Départ donné par erreur par le chef de gare de Barchetta, collision frontale près d\'un pont sur le Golo, un mort et quatre blessés graves.',
        ],
        'gallica-petit-parisien-1928-09-20' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 20 septembre 1928, page 3',
            'publisher' => 'Le Petit Parisien (Gallica, BnF)',
            'author' => null,
            'published' => '1928-09-20',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k607337r/f3.image.r=tamponne.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence de presse citée par la liste de Wikipédia, à consulter ; non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'En décembre 1925, les chemins de fer de la Corse ont reçu à Bastia deux automotrices pétroléo-électriques Crochat, numérotées Ae 51 et Ae 52, utilisées pour un train direct Bastia - Ajaccio. Elles n\'avaient que des places de première et de deuxième classe.',
                'en' => 'In December 1925 the Corsican railways received two Crochat petrol-electric railcars at Bastia, numbered Ae 51 and Ae 52, used for a direct Bastia to Ajaccio train. They had first and second class seats only.',
                'de' => 'Im Dezember 1925 erhielten die korsischen Eisenbahnen in Bastia zwei benzinelektrische Triebwagen von Crochat mit den Nummern Ae 51 und Ae 52, die für einen durchgehenden Zug Bastia - Ajaccio eingesetzt wurden. Sie hatten nur Plätze der ersten und zweiten Klasse.',
                'sources' => ['wp-chemins-de-fer-corse'],
            ],
        ],
        'course' => [
            [
                'fr' => 'D\'après l\'article sur les chemins de fer de la Corse, l\'Ae 51 a été détruite dans une collision au point kilométrique 27,3, près de Barchetta. La gare de Barchetta se trouve au point kilométrique 29,6 de la ligne de Bastia à Ajaccio, sur la commune de Volpajola.',
                'en' => 'According to the article on the Corsican railways, railcar Ae 51 was destroyed in a collision at kilometre point 27.3, near Barchetta. Barchetta station is at kilometre point 29.6 on the Bastia to Ajaccio line, in the commune of Volpajola.',
                'de' => 'Nach dem Artikel über die korsischen Eisenbahnen wurde der Triebwagen Ae 51 bei einem Zusammenstoss bei Kilometerpunkt 27,3 nahe Barchetta zerstört. Der Bahnhof Barchetta liegt bei Kilometerpunkt 29,6 der Strecke von Bastia nach Ajaccio, auf dem Gebiet der Gemeinde Volpajola.',
                'sources' => ['wp-chemins-de-fer-corse', 'wp-gare-barchetta'],
            ],
            [
                'fr' => 'Selon la liste de Wikipédia, le chef de gare de Barchetta a donné par erreur le départ à un train spécial alors qu\'un autorail pour Calvi était déjà parti en sens inverse de Casamozza. La collision frontale a eu lieu près d\'un pont sur le Golo.',
                'en' => 'According to the Wikipedia list, the station master at Barchetta gave a special train the signal to leave by mistake while a railcar for Calvi had already left Casamozza in the opposite direction. The head-on collision took place near a bridge over the Golo.',
                'de' => 'Nach der Wikipedia-Liste gab der Bahnhofsvorsteher von Barchetta einem Sonderzug irrtümlich die Abfahrt, obwohl ein Triebwagen nach Calvi bereits in Gegenrichtung von Casamozza abgefahren war. Der Frontalzusammenstoss ereignete sich nahe einer Brücke über den Golo.',
                'sources' => ['wp-liste-1928'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte un mort et quatre blessés graves. L\'article sur les chemins de fer de la Corse ne donne pas de bilan humain.',
                'en' => 'The Wikipedia list gives one dead and four seriously injured. The article on the Corsican railways gives no human toll.',
                'de' => 'Die Wikipedia-Liste nennt einen Toten und vier Schwerverletzte. Der Artikel über die korsischen Eisenbahnen nennt keine Opferzahl.',
                'sources' => ['wp-liste-1928', 'wp-chemins-de-fer-corse'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La Compagnie des chemins de fer départementaux ne voulait pas assurer de trains automoteurs sans engin de réserve : elle a muté l\'Ae 52 à Saint-Jean-d\'Angély en décembre 1929, et envoyé les pièces récupérables de l\'Ae 51 vers le réseau des Charentes et des Deux-Sèvres, où elles ont servi à construire un locotracteur.',
                'en' => 'The Compagnie des chemins de fer départementaux did not want to run railcar trains without a spare vehicle: it moved Ae 52 to Saint-Jean-d\'Angély in December 1929 and sent the salvageable parts of Ae 51 to the Charentes and Deux-Sèvres network, where they were used to build a diesel shunter.',
                'de' => 'Die Compagnie des chemins de fer départementaux wollte keine Triebwagenzüge ohne Reservefahrzeug betreiben: Sie versetzte die Ae 52 im Dezember 1929 nach Saint-Jean-d\'Angély und schickte die brauchbaren Teile der Ae 51 zum Netz der Charentes und Deux-Sèvres, wo sie für den Bau einer Rangierlokomotive verwendet wurden.',
                'sources' => ['wp-chemins-de-fer-corse'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La date diffère d\'un jour : l\'article sur les chemins de fer de la Corse donne le 18 septembre 1928 pour la destruction de l\'Ae 51, la liste de Wikipédia le 19 septembre. Les deux textes ne disent pas expressément qu\'il s\'agit du même accident, mais la date, à un jour près, et le lieu, près de Barchetta, concordent. Le lieu est aussi décrit différemment : point kilométrique 27,3 d\'un côté, pont sur le Golo de l\'autre. La liste parle d\'un autorail pour Calvi, l\'article d\'un train direct Bastia - Ajaccio.',
                'en' => 'The date differs by one day: the article on the Corsican railways gives 18 September 1928 for the destruction of Ae 51, the Wikipedia list gives 19 September. Neither text says expressly that it is the same accident, but the date, within a day, and the place, near Barchetta, agree. The place is also described differently: kilometre point 27.3 on one side, a bridge over the Golo on the other. The list speaks of a railcar for Calvi, the article of a direct Bastia to Ajaccio train.',
                'de' => 'Das Datum weicht um einen Tag ab: Der Artikel über die korsischen Eisenbahnen nennt für die Zerstörung der Ae 51 den 18. September 1928, die Wikipedia-Liste den 19. September. Beide Texte sagen nicht ausdrücklich, dass es sich um denselben Unfall handelt, doch Datum (auf einen Tag genau) und Ort nahe Barchetta stimmen überein. Auch der Ort wird unterschiedlich beschrieben: Kilometerpunkt 27,3 einerseits, eine Brücke über den Golo andererseits. Die Liste spricht von einem Triebwagen nach Calvi, der Artikel von einem durchgehenden Zug Bastia - Ajaccio.',
                'sources' => ['wp-chemins-de-fer-corse', 'wp-liste-1928'],
            ],
        ],
    ],
    'corrections' => [],
];
