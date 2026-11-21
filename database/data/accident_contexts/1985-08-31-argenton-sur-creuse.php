<?php

// Contexte sourcé de l'accident d'Argenton-sur-Creuse du 31 août 1985 (recherche du 21/11/2026).
return [
    'slug' => '1985-08-31-argenton-sur-creuse',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire d\'Argenton-sur-Creuse',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_ferroviaire_d%27Argenton-sur-Creuse&oldid=233413957',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Seule source lue donnant l\'heure, le PK, les suites judiciaires ; passage sur l\'enquête incomplet.',
        ],
        'wp-de' => [
            'provider' => 'wikipedia-de',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Eisenbahnunfall von Argenton-sur-Creuse',
            'publisher' => 'Wikipedia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://de.wikipedia.org/wiki/Eisenbahnunfall_von_Argenton-sur-Creuse',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Appuyé sur une thèse de 2004 et un livre de Semmens ; 38 blessés, limite de 40 km/h.',
        ],
        'wp-en-gare' => [
            'provider' => 'wikipedia-en',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Argenton-sur-Creuse station',
            'publisher' => 'Wikipedia',
            'author' => null,
            'published' => null,
            'locator' => 'section Histoire',
            'url' => 'https://en.wikipedia.org/wiki/Argenton-sur-Creuse_station',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Paragraphe sur l\'accident, appuyé sur un article de France 3 Centre-Val de Loire de 2012.',
        ],
        'france3-2012' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'On a retrouvé la Mémoire : catastrophe ferroviaire d\'Argenton',
            'publisher' => 'France 3 Centre-Val de Loire',
            'author' => null,
            'published' => '2012',
            'locator' => null,
            'url' => 'https://france3-regions.francetvinfo.fr/centre-val-de-loire/',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par Wikipédia, adresse exacte non retrouvée ; à consulter pour la mémoire locale.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 31 août 1985, à 0 h 08 selon Wikipédia, un train de nuit Corail de Paris-Austerlitz à Portbou, tracté par la BB 7248, déraille juste après la gare d\'Argenton-sur-Creuse (Indre), sur la ligne des Aubrais-Orléans à Montauban-Ville-Bourbon, vers le point kilométrique 295.',
                'en' => 'On 31 August 1985, at 12.08 am according to Wikipedia, a Corail night train from Paris-Austerlitz to Portbou, hauled by BB 7248, derailed just after Argenton-sur-Creuse station (Indre), on the Les Aubrais-Orléans to Montauban-Ville-Bourbon line, at about kilometre point 295.',
                'de' => 'Am 31. August 1985 entgleiste nach der französischen Wikipedia um 0.08 Uhr ein Corail-Nachtzug von Paris-Austerlitz nach Portbou, gezogen von der BB 7248, unmittelbar hinter dem Bahnhof Argenton-sur-Creuse (Indre) auf der Strecke von Les Aubrais-Orléans nach Montauban-Ville-Bourbon, bei etwa Kilometer 295.',
                'sources' => ['wp-fr', 'wp-en-gare'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train roule à environ 100 km/h dans une zone de travaux où la vitesse est limitée. Le freinage d\'urgence se déclenche, dix voitures déraillent et deux d\'entre elles débordent sur le gabarit de la voie voisine.',
                'en' => 'The train was travelling at about 100 km/h in a work zone with a speed limit. The emergency brake was applied, ten cars derailed and two of them fouled the clearance of the adjacent track.',
                'de' => 'Der Zug fuhr mit etwa 100 km/h durch einen Baustellenbereich mit Geschwindigkeitsbeschränkung. Die Notbremsung wurde ausgelöst, zehn Wagen entgleisten, und zwei davon ragten in das Lichtraumprofil des Nachbargleises.',
                'sources' => ['wp-fr', 'wp-de'],
            ],
            [
                'fr' => 'Un train postal de Brive-la-Gaillarde à Paris, qui circule sur la voie contraire, ne peut s\'arrêter et sa locomotive heurte les deux voitures qui débordent.',
                'en' => 'A mail train from Brive-la-Gaillarde to Paris, running on the opposite track, could not stop and its locomotive struck the two cars fouling the line.',
                'de' => 'Ein Postzug von Brive-la-Gaillarde nach Paris, der auf dem Gegengleis fuhr, konnte nicht mehr anhalten, und seine Lokomotive prallte gegen die beiden in das Profil ragenden Wagen.',
                'sources' => ['wp-fr', 'wp-de', 'wp-en-gare'],
            ],
            [
                'fr' => 'Les premiers secours arrivent à 0 h 14 ; des renforts viennent des départements voisins et 250 sapeurs-pompiers sont mobilisés.',
                'en' => 'The first emergency crews arrived at 12.14 am; reinforcements came from neighbouring departments and 250 firefighters were mobilised.',
                'de' => 'Die ersten Rettungskräfte trafen um 0.14 Uhr ein; aus den Nachbardépartements kamen Verstärkungen, und 250 Feuerwehrleute waren im Einsatz.',
                'sources' => ['wp-fr'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait 43 morts. Le nombre de blessés est donné comme 40 par l\'article français de Wikipédia et comme 38 par l\'article allemand.',
                'en' => 'The accident killed 43 people. The number of injured is given as 40 by the French Wikipedia article and as 38 by the German one.',
                'de' => 'Bei dem Unglück starben 43 Menschen. Die Zahl der Verletzten wird im französischen Wikipedia-Artikel mit 40 und im deutschen mit 38 angegeben.',
                'sources' => ['wp-fr', 'wp-de'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le train était lancé à environ 100 km/h alors que des travaux imposaient une limitation de vitesse (30 km/h selon Wikipédia en français, 40 km/h selon les versions allemande et anglaise). Le signal d\'annonce de la limitation était en partie masqué par d\'autres signaux, et le conducteur ne l\'a pas perçu.',
                'en' => 'The train was running at about 100 km/h while works imposed a speed restriction (30 km/h according to French Wikipedia, 40 km/h according to the German and English versions). The signal announcing the restriction was partly hidden by other signals, and the driver did not notice it.',
                'de' => 'Der Zug fuhr mit etwa 100 km/h, obwohl Bauarbeiten eine Geschwindigkeitsbeschränkung erforderten (30 km/h laut französischer, 40 km/h laut deutscher und englischer Wikipedia). Das Vorsignal der Beschränkung war teilweise durch andere Signale verdeckt, und der Lokführer nahm es nicht wahr.',
                'sources' => ['wp-fr', 'wp-de', 'wp-en-gare'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'En juillet 1988, le tribunal de Châteauroux condamne le conducteur pour homicides et blessures involontaires à un an de prison avec sursis et 15 000 francs d\'amende. Il retient contre lui de n\'avoir pas lu le dossier de ligne, d\'avoir négligé la feuille de limitation temporaire de vitesse et d\'avoir maintenu sa vitesse.',
                'en' => 'In July 1988 the court at Châteauroux convicted the driver of involuntary manslaughter and injury, with a one-year suspended prison sentence and a fine of 15,000 francs. It held against him that he had not read the route file, had overlooked the temporary speed-restriction sheet and had kept up his speed.',
                'de' => 'Im Juli 1988 verurteilte das Gericht in Châteauroux den Lokführer wegen fahrlässiger Tötung und Körperverletzung zu einem Jahr Haft auf Bewährung und 15 000 Franc Geldstrafe. Es lastete ihm an, die Streckenunterlagen nicht gelesen, das Blatt mit der vorübergehenden Geschwindigkeitsbeschränkung übersehen und seine Geschwindigkeit beibehalten zu haben.',
                'sources' => ['wp-fr'],
            ],
            [
                'fr' => 'Le tribunal juge aussi la SNCF en partie responsable, pour un temps de préparation du service du conducteur trop court et une signalisation incohérente.',
                'en' => 'The court also held SNCF partly responsible, for allowing too little time to prepare the driver\'s duty and for inconsistent signage.',
                'de' => 'Das Gericht hielt auch die SNCF für teilweise verantwortlich, wegen zu knapp bemessener Vorbereitungszeit für den Dienst des Lokführers und widersprüchlicher Beschilderung.',
                'sources' => ['wp-fr'],
            ],
            [
                'fr' => 'Après trois accidents mortels durant l\'été 1985 (Saint-Pierre-du-Vauvray, Flaujac et Argenton-sur-Creuse), le président de la SNCF quitte ses fonctions.',
                'en' => 'After three fatal accidents in the summer of 1985 (Saint-Pierre-du-Vauvray, Flaujac and Argenton-sur-Creuse), the president of SNCF left his post.',
                'de' => 'Nach drei tödlichen Unfällen im Sommer 1985 (Saint-Pierre-du-Vauvray, Flaujac und Argenton-sur-Creuse) schied der Präsident der SNCF aus dem Amt.',
                'sources' => ['wp-fr', 'wp-de'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés (40 ou 38) et la vitesse limite en zone de travaux (30 ou 40 km/h) diffèrent selon les versions de Wikipédia lues. Aucun rapport officiel n\'a pu être consulté pour trancher.',
                'en' => 'The number of injured (40 or 38) and the speed limit in the work zone (30 or 40 km/h) differ between the Wikipedia versions read. No official report could be consulted to settle the matter.',
                'de' => 'Die Zahl der Verletzten (40 oder 38) und die zulässige Geschwindigkeit im Baustellenbereich (30 oder 40 km/h) unterscheiden sich in den gelesenen Wikipedia-Versionen. Ein amtlicher Bericht zur Klärung war nicht einsehbar.',
                'sources' => ['wp-fr', 'wp-de', 'wp-en-gare'],
            ],
        ],
    ],
];
