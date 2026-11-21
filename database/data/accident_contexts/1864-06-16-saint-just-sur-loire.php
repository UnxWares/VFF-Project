<?php

// Contexte sourcé du déraillement entre Saint-Just-sur-Loire et La Fouillouse du 16 juin 1864 (recherche du 21/11/2026).
return [
    'slug' => '1864-06-16-saint-just-sur-loire',
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
            'locator' => '1864',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1864',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Petit Journal du 20 juin 1864 : sur la ligne de Saint-Étienne à Roanne, entre Saint-Just-sur-Loire et La Fouillouse, vers 19 h 30, un train de voyageurs venant de Roanne, trop rapide pour rattraper son retard, déraille dans une courbe ; le mécanicien est tué, le conducteur, le chef de train et plusieurs voyageurs grièvement blessés.',
        ],
        'ddb-1864-06-21-bamberger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 18. Juni. Auf der Eisenbahn des Bourbonnais hat sich, und zwar wieder in der Nähe von La Fouillouse',
            'publisher' => 'Bamberger Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1864-06-21',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/NAELR72XF6SYLXO7DWM3G6EZRVR4LRL7?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 18 juin : nouvel accident sur le chemin de fer du Bourbonnais près de La Fouillouse ; le mécanicien est passé trop vite dans une courbe bordée d\'un talus et d\'un ravin ; la machine, sortie des rails, est projetée contre le talus ; le mécanicien, tombé sous la machine, est tué, le chauffeur légèrement blessé ; les premières voitures sont brisées, quatre voyageurs grièvement blessés, beaucoup d\'autres contusionnés.',
        ],
        'ddb-1864-06-24-wuerzburger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück. Paris, 19. Juni. Auf der Eisenbahn des Bourbonnais',
            'publisher' => 'Würzburger Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1864-06-24',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/GZQ7BLOTWQM6VQCV5JFATHGBQ4ZRWEFW?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche, datée du 19 juin : machine sortie des rails dans une courbe près de La Fouillouse, mécanicien tué, chauffeur légèrement blessé, quatre voyageurs grièvement blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 16 juin 1864, vers 19 h 30, un train de voyageurs venant de Roanne roulait sur la ligne de Roanne à Saint-Étienne, entre Saint-Just-sur-Loire et La Fouillouse, dans une courbe bordée d\'un talus d\'un côté et d\'un ravin de l\'autre.',
                'en' => 'On 16 June 1864, at about 7.30 pm, a passenger train from Roanne was running on the Roanne to Saint-Étienne line, between Saint-Just-sur-Loire and La Fouillouse, on a curve with an embankment on one side and a ravine on the other.',
                'de' => 'Am 16. Juni 1864 gegen 19.30 Uhr fuhr ein Personenzug aus Roanne auf der Strecke Roanne-Saint-Étienne zwischen Saint-Just-sur-Loire und La Fouillouse durch eine Kurve mit einer Böschung auf der einen und einem Abgrund auf der anderen Seite.',
                'sources' => ['wp-liste-1860', 'ddb-1864-06-21-bamberger'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train est passé trop vite dans la courbe. La machine est sortie des rails et a été projetée contre le talus ; les premières voitures ont été brisées.',
                'en' => 'The train took the curve too fast. The engine left the rails and was thrown against the embankment; the leading carriages were smashed.',
                'de' => 'Der Zug fuhr zu schnell in die Kurve. Die Lokomotive sprang aus den Schienen und wurde gegen die Böschung geworfen; die ersten Wagen wurden zertrümmert.',
                'sources' => ['ddb-1864-06-21-bamberger', 'ddb-1864-06-24-wuerzburger', 'wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien, tombé sous la machine, a été tué. Selon la presse allemande, le chauffeur a été légèrement blessé, quatre voyageurs grièvement et beaucoup d\'autres contusionnés. La liste de Wikipédia cite le conducteur, le chef de train et plusieurs voyageurs grièvement blessés.',
                'en' => 'The driver fell under the engine and was killed. According to the German press, the stoker was slightly hurt, four passengers were seriously injured and many others bruised. The Wikipedia list mentions the guard, the chief guard and several passengers seriously injured.',
                'de' => 'Der Lokomotivführer stürzte unter die Maschine und wurde getötet. Nach der deutschen Presse wurde der Heizer leicht verletzt, vier Reisende schwer, viele weitere erlitten Prellungen. Die Wikipedia-Liste nennt den Zugbegleiter, den Zugführer und mehrere Reisende als schwer verletzt.',
                'sources' => ['ddb-1864-06-21-bamberger', 'ddb-1864-06-24-wuerzburger', 'wp-liste-1860'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La presse impute l\'accident à la vitesse excessive dans la courbe ; selon la liste de Wikipédia, le train roulait vite pour rattraper son retard.',
                'en' => 'The press blames excessive speed on the curve; according to the Wikipedia list, the train was running fast to make up lost time.',
                'de' => 'Die Presse führt den Unfall auf zu hohe Geschwindigkeit in der Kurve zurück; nach der Wikipedia-Liste fuhr der Zug schnell, um seine Verspätung aufzuholen.',
                'sources' => ['ddb-1864-06-21-bamberger', 'wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['ddb-1864-06-21-bamberger', 'wp-liste-1860'],
            'fr' => 'Un mort, le mécanicien, selon la liste de Wikipédia et la presse allemande de juin 1864 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One dead, the driver, according to the Wikipedia list and the German press of June 1864; the record gave no number of deaths.',
            'de' => 'Ein Toter, der Lokomotivführer, nach der Wikipedia-Liste und der deutschen Presse vom Juni 1864; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
