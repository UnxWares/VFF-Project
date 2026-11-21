<?php

// Contexte sourcé de l'accident de Bischwiller du 4 janvier 1900 (recherche du 21/11/2026).
return [
    'slug' => '1900-01-04-bischwiller',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-de-bischweiler' => [
            'provider' => 'wikipedia-de',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Eisenbahnunfall von Bischweiler',
            'publisher' => 'Wikipedia en allemand',
            'author' => null,
            'published' => null,
            'locator' => 'article entier',
            'url' => 'https://de.wikipedia.org/w/index.php?title=Eisenbahnunfall_von_Bischweiler&oldid=252280414',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé reposant sur un seul ouvrage (Trouillet, Les Chemins de fer impériaux d\'Alsace-Lorraine, 2018, p. 332-333) ; identification de la locomotive incertaine.',
        ],
        'wikidata-bischwiller' => [
            'provider' => 'wikidata',
            'kind' => 'data',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire de Bischweiler (Q110257581)',
            'publisher' => 'Wikidata',
            'author' => null,
            'published' => null,
            'locator' => 'Q110257581',
            'url' => 'https://www.wikidata.org/wiki/Q110257581',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Notice de l\'import : 4 janvier 1900, 4 morts, coordonnées.',
        ],
        'loquis-bischweiler' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => 'Accident ferroviaire de Bischweiler',
            'publisher' => 'Loquis',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.loquis.com/fr/loquis/5794554/Accident+ferroviaire+de+Bischweiler',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Notice de podcast sur l\'accident du 4 janvier 1900, repérée par recherche ; page tronquée, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 janvier 1900, l\'express D 76 de Berlin à Bâle par Francfort, qui avait quitté Haguenau à 13 h 54 et devait traverser la gare de Bischweiler (aujourd\'hui Bischwiller, alors dans le Reichsland d\'Alsace-Lorraine) sans s\'arrêter, est entré en collision avec un train de marchandises garé sur la voie 4. Celui-ci, en retard de 40 minutes, avait été garé là pour laisser passer l\'express ; son dernier wagon était une citerne contenant 40 m³ d\'alcool.',
                'en' => 'On 4 January 1900, express D 76 from Berlin to Basel via Frankfurt, which had left Haguenau at 1.54 pm and was to pass through Bischweiler station (now Bischwiller, then in the Reichsland of Alsace-Lorraine) without stopping, collided with a goods train standing on track 4. That train, 40 minutes late, had been put there to let the express through; its last wagon was a tank car holding 40 m³ of alcohol.',
                'de' => 'Am 4. Januar 1900 stieß der D 76 von Berlin über Frankfurt nach Basel, der um 13.54 Uhr Hagenau verlassen hatte und den Bahnhof Bischweiler (heute Bischwiller, damals im Reichsland Elsaß-Lothringen) ohne Halt durchfahren sollte, mit einem auf Gleis 4 abgestellten Güterzug zusammen. Dieser, 40 Minuten verspätet, war dort abgestellt worden, um den Schnellzug durchzulassen; sein letzter Wagen war ein Kesselwagen mit 40 m³ Alkohol.',
                'sources' => ['wp-de-bischweiler'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le signal d\'entrée indiquait voie libre et l\'express est entré à la vitesse maximale autorisée ; une aiguille était restée réglée sur la voie 4. La locomotive a heurté le dernier wagon du train de marchandises, l\'alcool s\'est répandu sur la machine et sur deux wagons, s\'est enflammé et a explosé ; les wagons à bagages et postal ont entièrement brûlé. Les pompiers d\'une usine de jute voisine et ceux de la commune ont empêché le feu de gagner les voitures suivantes, et un ouvrier a ouvert les robinets de la locomotive pour évacuer la vapeur, évitant probablement l\'explosion de la chaudière.',
                'en' => 'The entry signal showed clear and the express entered at the maximum permitted speed; a set of points had been left set for track 4. The locomotive struck the last wagon of the goods train, the alcohol spilled over the engine and two wagons, ignited and exploded; the luggage and mail vans burned out completely. Firefighters from a neighbouring jute mill and from the town stopped the fire from reaching the following coaches, and a worker opened the locomotive\'s cocks to release steam, probably preventing a boiler explosion.',
                'de' => 'Das Einfahrsignal zeigte freie Fahrt, und der Schnellzug fuhr mit der zulässigen Höchstgeschwindigkeit ein; eine Weiche stand noch auf Gleis 4. Die Lokomotive prallte auf den letzten Wagen des Güterzugs, der Alkohol ergoss sich über die Maschine und zwei Wagen, entzündete sich und explodierte; Gepäck- und Postwagen brannten vollständig aus. Die Feuerwehr einer benachbarten Jutefabrik und die der Gemeinde verhinderten, dass das Feuer auf die folgenden Wagen übergriff, und ein Arbeiter öffnete die Hähne der Lokomotive, um den Dampf abzulassen, was wohl eine Kesselexplosion verhinderte.',
                'sources' => ['wp-de-bischweiler'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Quatre personnes sont mortes : trois employés des postes, carbonisés dans le wagon postal, et le chauffeur de la locomotive, grièvement blessé en sautant et mort plus tard à l\'hôpital. Le chef de train, le convoyeur de fourgon et le mécanicien ont été grièvement blessés ; aucun voyageur ni agent de l\'arrière du train n\'a été blessé.',
                'en' => 'Four people died: three postal employees, burned to death in the mail van, and the locomotive fireman, seriously hurt when he jumped and who died later in hospital. The guard, the van attendant and the driver were seriously injured; no passenger and no staff at the rear of the train were hurt.',
                'de' => 'Vier Menschen starben: drei Postbedienstete, die im Postwagen verbrannten, und der Heizer der Lokomotive, der beim Abspringen schwer verletzt wurde und später im Krankenhaus starb. Zugführer, Packwagenbegleiter und Lokführer wurden schwer verletzt; kein Reisender und kein Bediensteter am Zugende wurde verletzt.',
                'sources' => ['wp-de-bischweiler'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause immédiate est une aiguille non remise en position après l\'entrée du train de marchandises sur la voie 4. Des travaux de construction d\'une courbe de raccordement vers Oberhoffen étaient en cours et les dépendances entre aiguilles et signaux avaient été mises hors service. Le chef de gare avait fait garer le train de marchandises en gare pour ne pas retarder l\'express.',
                'en' => 'The immediate cause was a set of points not returned to position after the goods train entered track 4. Construction of a connecting curve towards Oberhoffen was under way and the interlocking between points and signals had been taken out of service. The stationmaster had had the goods train parked in the station so as not to delay the express.',
                'de' => 'Unmittelbare Ursache war eine Weiche, die nach der Einfahrt des Güterzugs auf Gleis 4 nicht zurückgestellt worden war. Es wurde an einer Verbindungskurve nach Oberhoffen gebaut, und die Abhängigkeiten zwischen Weichen und Signalen waren außer Betrieb gesetzt. Der Bahnhofsvorsteher hatte den Güterzug im Bahnhof abstellen lassen, um den Schnellzug nicht zu verzögern.',
                'sources' => ['wp-de-bischweiler'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Un procès pénal a eu lieu ; l\'agent chargé de l\'aiguille a été reconnu responsable et condamné à une peine de prison.',
                'en' => 'A criminal trial took place; the employee in charge of the points was found responsible and sentenced to imprisonment.',
                'de' => 'Es kam zu einem Strafverfahren; der für die Weiche zuständige Bedienstete wurde für schuldig befunden und zu einer Gefängnisstrafe verurteilt.',
                'sources' => ['wp-de-bischweiler'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche classait l\'accident en « autre », faute de détail ; l\'article décrit une collision en gare suivie d\'un incendie et d\'une explosion. Le texte ne donne ni l\'heure précise ni le détail du bilan de façon explicite, et tout repose sur un seul ouvrage ; le nombre de blessés (au moins trois blessés graves) ne figure pas dans la fiche.',
                'en' => 'The record classed the accident as "other" for lack of detail; the article describes a collision in the station followed by a fire and an explosion. The text gives neither the exact time nor an explicit breakdown of the toll, and everything rests on a single book; the number of injured (at least three seriously) is not in the record.',
                'de' => 'Der Eintrag stufte den Unfall mangels Details als „sonstiger“ ein; der Artikel beschreibt einen Zusammenstoß im Bahnhof mit anschließendem Brand und Explosion. Der Text nennt weder die genaue Uhrzeit noch eine ausdrückliche Aufschlüsselung der Bilanz, und alles beruht auf einem einzigen Buch; die Zahl der Verletzten (mindestens drei Schwerverletzte) fehlt im Datensatz.',
                'sources' => ['wp-de-bischweiler'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-de-bischweiler'],
            'fr' => 'L\'express a heurté en gare un train de marchandises garé sur une voie dont l\'aiguille était restée ouverte ; l\'incendie et l\'explosion de la citerne d\'alcool ont suivi le choc. La fiche, venue de Wikidata, n\'avait pas de nature précise.',
            'en' => 'The express struck a goods train standing in the station on a track whose points had been left set; the fire and the explosion of the alcohol tank wagon followed the impact. The record, taken from Wikidata, had no specific type.',
            'de' => 'Der Schnellzug prallte im Bahnhof auf einen Güterzug, der auf einem Gleis mit falsch gestellter Weiche stand; Brand und Explosion des Alkoholkesselwagens folgten dem Aufprall. Der aus Wikidata übernommene Eintrag hatte keine genaue Art.',
        ],
    ],
];
