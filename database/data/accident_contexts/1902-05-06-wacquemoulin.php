<?php

// Contexte sourcé de l'accident de Wacquemoulin (dit de Moyenneville) du 6 mai 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-05-06-wacquemoulin',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-moyenneville' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire de Moyenneville',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_ferroviaire_de_Moyenneville&oldid=237586089',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé (train, déroulement, secours, causes, suites) ; la fiche d\'infobox indique Moyenneville, le texte situe l\'accident à environ 500 m après la halte de Wacquemoulin. Références de presse de l\'époque non lues.',
        ],
        'wp-liste-1902' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1902',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mai, 6 mai 1902',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée à Moyenneville (le nom de Mouscron, ville de départ du train, apparaît dans la ligne) ; elle cite Le Figaro des 7 et 8 mai 1902, non lu.',
        ],
        'wikidata-q17626104' => [
            'provider' => 'wikidata',
            'kind' => 'data',
            'reliability' => 'encyclopedia',
            'title' => 'Déraillement de Moyenneville du 6 mai 1902 (Q17626104)',
            'publisher' => 'Wikidata',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.wikidata.org/wiki/Q17626104',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Élément sans référence : date du 6 mai 1902, 8 victimes, département de l\'Oise, coordonnées 49,4983 N et 2,615 E (commune non précisée).',
        ],
        'gallica-croix-1902' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Croix (compte rendu de l\'accident du train de pèlerins belges)',
            'publisher' => 'La Croix, via Gallica',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k219709p.texte.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Repéré par recherche, non lu (Gallica) ; date du numéro non vérifiée.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 6 mai 1902, vers 14 h 45, un train de pèlerins belges en route vers Lourdes a déraillé sur la ligne d\'Amiens à Compiègne par Montdidier, à environ 500 m après la halte de Wacquemoulin (Oise), au lieu-dit « le bosquet Mathieu », près du remblai de la vallée de l\'Aronde. L\'accident est connu sous le nom d\'accident de Moyenneville, commune voisine.',
                'en' => 'On 6 May 1902, at about 2.45 pm, a train of Belgian pilgrims bound for Lourdes derailed on the Amiens to Compiègne line via Montdidier, about 500 m past Wacquemoulin halt (Oise), at the place called "le bosquet Mathieu", near the embankment of the Aronde valley. The accident is known as the Moyenneville accident, after the neighbouring commune.',
                'de' => 'Am 6. Mai 1902 gegen 14.45 Uhr entgleiste ein Zug belgischer Pilger auf dem Weg nach Lourdes auf der Strecke von Amiens nach Compiègne über Montdidier, etwa 500 m hinter dem Haltepunkt Wacquemoulin (Oise), an der Stelle „le bosquet Mathieu“ nahe dem Damm des Aronde-Tals. Das Unglück ist als Unfall von Moyenneville nach der Nachbargemeinde bekannt.',
                'sources' => ['wp-moyenneville', 'wp-liste-1902'],
            ],
            [
                'fr' => 'Le train, formé de voitures du réseau d\'Orléans et tracté par une locomotive compound du Nord, était l\'un des dix trains spéciaux du pèlerinage national belge du 6 au 14 mai 1902. Il transportait environ 260 personnes.',
                'en' => 'The train, made up of Orléans network coaches and hauled by a Nord compound locomotive, was one of ten special trains of the Belgian national pilgrimage of 6 to 14 May 1902. It carried about 260 people.',
                'de' => 'Der Zug aus Wagen der Orléans-Bahn, gezogen von einer Verbundlokomotive der Nord, war einer von zehn Sonderzügen der belgischen Nationalwallfahrt vom 6. bis 14. Mai 1902. Er beförderte etwa 260 Personen.',
                'sources' => ['wp-moyenneville'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train abordait une courbe à environ 60 km/h quand les voitures se sont mises à osciller puis ont quitté les rails. Un attelage a cédé une centaine de mètres plus loin et l\'arrière du convoi s\'est empilé ; la neuvième voiture, enfoncée dans le remblai, a été écrasée par les suivantes. La locomotive s\'est arrêtée environ 200 m plus loin.',
                'en' => 'The train was taking a curve at about 60 km/h when the coaches began to sway and then left the rails. A coupling broke about a hundred metres further on and the rear of the train piled up; the ninth coach, lodged in the embankment, was crushed by those behind. The locomotive stopped about 200 m further on.',
                'de' => 'Der Zug fuhr mit etwa 60 km/h in eine Kurve, als die Wagen ins Schlingern gerieten und aus den Gleisen sprangen. Etwa hundert Meter weiter riss eine Kupplung, und das Zugende türmte sich auf; der neunte Wagen, im Damm verkeilt, wurde von den nachfolgenden zerdrückt. Die Lokomotive hielt etwa 200 m weiter an.',
                'sources' => ['wp-moyenneville'],
            ],
            [
                'fr' => 'Les habitants de Wacquemoulin ont aidé à dégager les voyageurs et ont transporté les blessés à l\'école et à l\'auberge. Un médecin voyageant avec les pèlerins les a soignés, rejoint par un médecin militaire de Compiègne ; des trains de secours sont partis d\'Amiens à 15 h 55 et de Paris à 18 h 20.',
                'en' => 'Residents of Wacquemoulin helped free the passengers and carried the injured to the school and the inn. A doctor travelling with the pilgrims treated them, joined by a military doctor from Compiègne; relief trains left Amiens at 3.55 pm and Paris at 6.20 pm.',
                'de' => 'Einwohner von Wacquemoulin halfen, die Reisenden zu befreien, und brachten die Verletzten in die Schule und ins Gasthaus. Ein mit den Pilgern reisender Arzt versorgte sie, unterstützt von einem Militärarzt aus Compiègne; Hilfszüge fuhren um 15.55 Uhr in Amiens und um 18.20 Uhr in Paris ab.',
                'sources' => ['wp-moyenneville'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de 8 morts (six femmes, une fillette de 12 ans et un homme) et d\'une cinquantaine de blessés, dont 25 graves. La plupart des morts se trouvaient dans la neuvième voiture.',
                'en' => 'The toll is 8 dead (six women, a 12-year-old girl and a man) and about fifty injured, 25 of them seriously. Most of the dead were in the ninth coach.',
                'de' => 'Die Bilanz beträgt 8 Tote (sechs Frauen, ein 12-jähriges Mädchen und ein Mann) und etwa fünfzig Verletzte, davon 25 schwer. Die meisten Toten befanden sich im neunten Wagen.',
                'sources' => ['wp-moyenneville'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La presse a d\'abord soupçonné une rupture d\'essieu, mais les témoignages et les traces relevées sur place désignaient plutôt la voie : de fortes pluies étaient tombées, la voie avait fait l\'objet de travaux un mois plus tôt et le poids de la locomotive pouvait avoir provoqué un affaissement du ballast. Le parquet a écarté l\'excès de vitesse (66 km/h estimés) et n\'a relevé aucune infraction ; les causes n\'ont jamais été pleinement établies.',
                'en' => 'The press first suspected an axle failure, but witness accounts and the traces found on site pointed rather to the track: heavy rain had fallen, the track had been worked on a month earlier and the weight of the locomotive may have caused the ballast to subside. The prosecutors ruled out speeding (66 km/h estimated) and found no offence; the causes were never fully established.',
                'de' => 'Die Presse vermutete zunächst einen Achsbruch, doch Zeugenaussagen und die vor Ort gefundenen Spuren wiesen eher auf das Gleis hin: Es hatte stark geregnet, das Gleis war einen Monat zuvor bearbeitet worden, und das Gewicht der Lokomotive könnte eine Senkung des Schotters verursacht haben. Die Staatsanwaltschaft schloss überhöhte Geschwindigkeit aus (geschätzt 66 km/h) und stellte keine Straftat fest; die Ursachen wurden nie vollständig geklärt.',
                'sources' => ['wp-moyenneville'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Environ 200 pèlerins ont poursuivi vers Lourdes le soir même à partir de Compiègne et une trentaine sont rentrés en Belgique ; une voie unique provisoire a rouvert le lendemain. Les obsèques ont eu lieu à Moyenneville le 9 mai et les corps ont été rapatriés en Belgique aux frais de la compagnie. Léopold II a ensuite décerné la croix civique à l\'épouse de l\'instituteur de Wacquemoulin pour les soins donnés aux blessés.',
                'en' => 'About 200 pilgrims continued to Lourdes that evening from Compiègne and about thirty returned to Belgium; a temporary single track reopened the next day. Funerals were held at Moyenneville on 9 May and the bodies were repatriated to Belgium at the railway company\'s expense. Leopold II later awarded the civic cross to the wife of the Wacquemoulin schoolteacher for her care of the injured.',
                'de' => 'Rund 200 Pilger setzten die Reise nach Lourdes noch am Abend von Compiègne aus fort, etwa dreißig kehrten nach Belgien zurück; ein provisorisches Einzelgleis wurde am nächsten Tag wieder eröffnet. Die Trauerfeiern fanden am 9. Mai in Moyenneville statt, die Toten wurden auf Kosten der Bahngesellschaft nach Belgien überführt. Leopold II. verlieh später der Frau des Lehrers von Wacquemoulin das Zivilkreuz für ihre Pflege der Verletzten.',
                'sources' => ['wp-moyenneville'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu : le nom de l\'accident est Moyenneville, mais le texte de l\'article situe la sortie des rails sur le territoire proche de la halte de Wacquemoulin, commune retenue par la fiche (limitrophe de Moyenneville) ; la commune exacte du lieu-dit n\'est pas établie par les documents lus. Le nombre de voyageurs : environ 260 dans l\'article détaillé, environ 500 dans la liste de Wikipédia. Le nombre de blessés : une cinquantaine (25 graves) dans l\'article, une trentaine de blessés graves dans la liste. La fiche ne donne pas le nombre de blessés. Wikidata confirme la date et les 8 victimes mais ne nomme pas la commune.',
                'en' => 'The place: the accident is named after Moyenneville, but the article text places the derailment close to Wacquemoulin halt, the commune used by the record (adjoining Moyenneville); the exact commune of the site is not established by the documents read. The number of passengers: about 260 in the detailed article, about 500 in the Wikipedia list. The number of injured: about fifty (25 serious) in the article, about thirty seriously injured in the list. The record gives no injury count. Wikidata confirms the date and the 8 victims but does not name the commune.',
                'de' => 'Der Ort: Das Unglück ist nach Moyenneville benannt, doch der Artikeltext verortet die Entgleisung nahe dem Haltepunkt Wacquemoulin, der vom Datensatz verwendeten Gemeinde (Nachbar von Moyenneville); die genaue Gemeinde der Stelle ist durch die gelesenen Dokumente nicht geklärt. Die Zahl der Reisenden: etwa 260 im ausführlichen Artikel, etwa 500 in der Wikipedia-Liste. Die Zahl der Verletzten: etwa fünfzig (25 schwer) im Artikel, etwa dreißig Schwerverletzte in der Liste. Der Datensatz enthält keine Verletztenzahl. Wikidata bestätigt Datum und 8 Opfer, nennt aber die Gemeinde nicht.',
                'sources' => ['wp-moyenneville', 'wp-liste-1902', 'wikidata-q17626104'],
            ],
        ],
    ],
    'corrections' => [],
];
