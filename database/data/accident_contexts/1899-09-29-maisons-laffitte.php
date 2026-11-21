<?php

// Contexte sourcé de l'accident du train de chevaux de course contre un heurtoir en gare de Maisons-Laffitte, de septembre 1899 (recherche du 21/11/2026).
return [
    'slug' => '1899-09-29-maisons-laffitte',
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
            'locator' => '1899',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1899',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 29 septembre 1899, d\'après Le Matin du 30 septembre : en gare de Maisons-Laffitte, à 18 h, un train spécial ramenant des chevaux et des lads anglais de l\'hippodrome vers leurs écuries de Chantilly est aiguillé par erreur sur une voie de garage et s\'écrase contre un heurtoir ; le chauffeur, le chef de train et deux lads tués, ainsi que huit chevaux ; plusieurs blessés.',
        ],
        'ddb-1899-09-30-westdeutsche' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 30. Sept.',
            'publisher' => 'Westdeutsche Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1899-09-30',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/BQJ2V72QZZJQIERYOWDJAVRMG53K4Z3M?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Deux dépêches de Paris du 30 septembre : un train de chevaux a heurté un heurtoir près du champ de courses de Maisons-Laffitte et a été brisé ; deux agents du train et, semble-t-il, deux palefreniers étrangers tués, plusieurs blessés ; sept personnes blessées selon la seconde dépêche.',
        ],
        'ddb-1899-10-03-morgenpost' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück',
            'publisher' => 'Berliner Morgenpost (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1899-10-03',
            'locator' => 'p. 7',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/JB2GOKXXKH4JAY7QEWUFVYWWUY6GSH2F?issuepage=7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Lettre de Paris : un train de chevaux de course, partis le soir du champ de courses après les épreuves du jour, a heurté le heurtoir à l\'entrée de la gare de Maisons-Laffitte ; le mécanicien, le chauffeur et deux palefreniers anglais tués, plusieurs blessés ; quatre chevaux tués, d\'autres abattus ; un mauvais fonctionnement du frein serait en cause.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 29 septembre 1899, en fin de journée, un train spécial emmenait des chevaux qui avaient couru à l\'hippodrome de Maisons-Laffitte, avec leurs lads anglais, vers les écuries de Chantilly.',
                'en' => 'On 29 September 1899, in the early evening, a special train was taking horses that had raced at the Maisons-Laffitte racecourse, with their English stable lads, to the stables at Chantilly.',
                'de' => 'Am 29. September 1899 gegen Abend brachte ein Sonderzug Pferde, die auf der Rennbahn von Maisons-Laffitte gelaufen waren, mit ihren englischen Pferdeknechten zu den Ställen von Chantilly.',
                'sources' => ['wp-liste-1890', 'ddb-1899-10-03-morgenpost'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 18 h, à l\'entrée de la gare de Maisons-Laffitte, le train a heurté un heurtoir et a été brisé.',
                'en' => 'At about 6 pm, on entering Maisons-Laffitte station, the train struck a buffer stop and was wrecked.',
                'de' => 'Gegen 18 Uhr fuhr der Zug bei der Einfahrt in den Bahnhof Maisons-Laffitte gegen einen Prellbock und wurde zertrümmert.',
                'sources' => ['wp-liste-1890', 'ddb-1899-09-30-westdeutsche', 'ddb-1899-10-03-morgenpost'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Quatre hommes ont été tués, deux agents du train et deux lads anglais. Plusieurs personnes ont été blessées, sept selon une dépêche de Paris du 30 septembre. Des chevaux ont été tués ou ont dû être abattus.',
                'en' => 'Four men were killed, two railwaymen and two English stable lads. Several people were injured, seven according to a Paris dispatch of 30 September. Horses were killed or had to be put down.',
                'de' => 'Vier Männer wurden getötet, zwei Zugbedienstete und zwei englische Pferdeknechte. Mehrere Personen wurden verletzt, nach einer Pariser Depesche vom 30. September sieben. Pferde wurden getötet oder mussten getötet werden.',
                'sources' => ['wp-liste-1890', 'ddb-1899-09-30-westdeutsche', 'ddb-1899-10-03-morgenpost'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les agents tués sont le chauffeur et le chef de train selon la liste de Wikipédia, le mécanicien et le chauffeur selon la Berliner Morgenpost. La liste compte huit chevaux tués, la Morgenpost quatre. La liste attribue l\'accident à une erreur d\'aiguillage vers une voie de garage, la Morgenpost à un mauvais fonctionnement du frein.',
                'en' => 'The railwaymen killed are the stoker and the guard according to the Wikipedia list, the driver and the stoker according to the Berliner Morgenpost. The list counts eight horses killed, the Morgenpost four. The list puts the accident down to the train being wrongly switched into a siding, the Morgenpost to a faulty brake.',
                'de' => 'Die getöteten Bediensteten sind nach der Wikipedia-Liste Heizer und Zugführer, nach der Berliner Morgenpost Lokführer und Heizer. Die Liste zählt acht getötete Pferde, die Morgenpost vier. Die Liste führt den Unfall auf eine falsch gestellte Weiche zu einem Abstellgleis zurück, die Morgenpost auf eine schlecht wirkende Bremse.',
                'sources' => ['wp-liste-1890', 'ddb-1899-10-03-morgenpost'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 4,
            'sources' => ['wp-liste-1890', 'ddb-1899-09-30-westdeutsche', 'ddb-1899-10-03-morgenpost'],
            'fr' => 'Deux agents du train et deux lads tués selon la liste de Wikipédia (Le Matin du 30 septembre 1899) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two railwaymen and two stable lads killed according to the Wikipedia list (Le Matin of 30 September 1899) and the German press; the record gave no number of deaths.',
            'de' => 'Zwei Zugbedienstete und zwei Pferdeknechte getötet nach der Wikipedia-Liste (Le Matin vom 30. September 1899) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
