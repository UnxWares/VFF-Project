<?php

// Contexte sourcé de l'accident de Millas du 14 décembre 2017 (recherche du 21/11/2026).
return [
    'slug' => '2017-12-14-millas',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-page-2019' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur la collision entre un train et un autocar le 14/12/2017 à Millas (page de synthèse)',
            'publisher' => 'Bureau d\'enquêtes sur les accidents de transport terrestre (BEA-TT)',
            'author' => null,
            'published' => '2019-05-17',
            'locator' => 'Synthèse de la page',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rapport-d-enquete-sur-la-collision-entre-un-train-a921.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation du rapport final : heure, trajet du train, bilan, cause directe, scénario retenu, facteurs et sujets des recommandations. Mise à jour le 20/10/2023.',
        ],
        'bea-rapport-2019' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête technique sur la collision entre un train de voyageurs et un autocar scolaire survenue le 14 décembre 2017 à Millas (66)',
            'publisher' => 'Bureau d\'enquêtes sur les accidents de transport terrestre (BEA-TT)',
            'author' => null,
            'published' => '2019-05',
            'locator' => 'Rapport final (PDF)',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2017-06.pdf',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Le PDF s\'extrait avec des polices illisibles : aucune phrase ne s\'appuie sur lui, à consulter directement. Les énoncés viennent de la page de synthèse du BEA-TT.',
        ],
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident de Millas',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_de_Millas&oldid=241196701',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé, sourcé par la presse nationale et régionale et le rapport du BEA-TT ; donne 17 blessés dans le texte et 18 dans l\'encadré.',
        ],
        'presse-2022-proces' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Attendu ou redouté, le procès de la collision mortelle de Millas s\'est ouvert à Marseille',
            'publisher' => 'Tendance Ouest (reprise de dépêche)',
            'author' => null,
            'published' => '2022-09-19',
            'locator' => null,
            'url' => 'https://www.tendanceouest.com/actualite-402721-attendu-ou-redoute-le-proces-de-la-collision-mortelle-de-millas-s-est-ouvert-a-marseille',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ouverture du procès de première instance à Marseille : seule prévenue la conductrice, accusation, retransmission à Perpignan, bilan humain (6 morts, 17 blessés dont 8 graves).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 14 décembre 2017, vers 16 h 07, le TER reliant Villefranche - Vernet-les-Bains à Perpignan percute un autocar scolaire sur le passage à niveau n° 25, sur la route départementale 612 à Millas (Pyrénées-Orientales). Le car ramenait des collégiens chez eux.',
                'en' => 'On Thursday 14 December 2017 at about 4.07 pm, the TER from Villefranche - Vernet-les-Bains to Perpignan hit a school coach at level crossing no. 25 on the departmental road 612 at Millas (Pyrénées-Orientales). The coach was taking pupils home from their secondary school.',
                'de' => 'Am Donnerstag, dem 14. Dezember 2017, gegen 16.07 Uhr prallte der TER von Villefranche - Vernet-les-Bains nach Perpignan am Bahnübergang Nr. 25 an der Departementsstraße 612 in Millas (Pyrénées-Orientales) auf einen Schulbus. Der Bus brachte Schüler nach Hause.',
                'sources' => ['bea-page-2019', 'presse-2022-proces'],
            ],
            [
                'fr' => 'Selon l\'article de Wikipédia, qui s\'appuie sur le BEA-TT, le TER roulait à 75 km/h et le car à 12 km/h ; le car, coupé en deux, a été heurté alors qu\'il achevait un tourne-à-gauche, tout près des demi-barrières.',
                'en' => 'According to the Wikipedia article, which relies on the BEA-TT, the TER was travelling at 75 km/h and the coach at 12 km/h; the coach, cut in two, was hit as it finished a left turn, very close to the half-barriers.',
                'de' => 'Laut dem Wikipedia-Artikel, der sich auf die BEA-TT stützt, fuhr der TER mit 75 km/h und der Bus mit 12 km/h; der Bus wurde beim Abschluss einer Linksabbiegung unmittelbar vor den Halbschranken erfasst und in zwei Teile gerissen.',
                'sources' => ['wp-fr', 'bea-page-2019'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Six adolescents sont morts, tous passagers du car. Le BEA-TT décrit des blessures graves pour les 18 autres occupants du car et légères pour quelques voyageurs du train ; la presse et Wikipédia comptent 17 blessés dont 8 graves. Les secours ont mobilisé le plan « nombreuses victimes », environ 80 pompiers, 50 gendarmes et cinq hélicoptères.',
                'en' => 'Six teenagers died, all of them passengers in the coach. The BEA-TT describes serious injuries to the 18 other people in the coach and slight injuries to a few train passengers; the press and Wikipedia count 17 injured, 8 of them seriously. The rescue effort involved the mass-casualty plan, about 80 firefighters, 50 gendarmes and five helicopters.',
                'de' => 'Sechs Jugendliche starben, alle Fahrgäste des Busses. Die BEA-TT beschreibt schwere Verletzungen bei den 18 übrigen Businsassen und leichte bei einigen Fahrgästen des Zuges; Presse und Wikipedia zählen 17 Verletzte, davon 8 schwer. Im Einsatz waren der Plan für viele Opfer, etwa 80 Feuerwehrleute, 50 Gendarmen und fünf Hubschrauber.',
                'sources' => ['bea-page-2019', 'wp-fr', 'presse-2022-proces'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT ne relève aucun dysfonctionnement de la circulation ferroviaire ni du déclenchement du passage à niveau. La cause directe est le non-arrêt du car malgré les feux rouges clignotants et la barrière ; le scénario le plus probable est que la conductrice n\'a pas perçu que le passage était fermé.',
                'en' => 'The BEA-TT found no malfunction in the rail traffic or in the activation of the level crossing. The direct cause is the coach not stopping despite the flashing red lights and the barrier; the most probable scenario is that the driver did not notice that the crossing was closed.',
                'de' => 'Die BEA-TT stellte weder eine Störung im Bahnbetrieb noch bei der Auslösung des Bahnübergangs fest. Unmittelbare Ursache ist, dass der Bus trotz blinkender roter Lichter und Schranke nicht hielt; am wahrscheinlichsten nahm die Fahrerin nicht wahr, dass der Übergang geschlossen war.',
                'sources' => ['bea-page-2019'],
            ],
            [
                'fr' => 'Plusieurs facteurs ont pu jouer : la visibilité réduite des feux, surtout le feu rouge de droite ; l\'arrêt des sonneries quand les demi-barrières sont baissées ; l\'attention requise pour le tourne-à-gauche ; la proximité du carrefour, qui a laissé le car à moins d\'un mètre de la demi-barrière, dans son angle mort ; l\'absence de ces contraintes dans le diagnostic de sécurité du passage à niveau.',
                'en' => 'Several factors may have played a part: the reduced visibility of the lights, especially the right-hand red light; the bells stopping once the half-barriers are down; the attention needed for the left turn; the nearness of the road junction, which left the coach less than a metre from the lowered half-barrier, in its blind spot; and the fact that these constraints were missing from the crossing\'s safety assessment.',
                'de' => 'Mehrere Faktoren können eine Rolle gespielt haben: die eingeschränkte Sichtbarkeit der Lichtzeichen, vor allem des rechten roten Lichts; das Verstummen der Läutewerke, sobald die Halbschranken unten sind; die für das Linksabbiegen nötige Aufmerksamkeit; die Nähe der Kreuzung, durch die der Bus weniger als einen Meter vor der gesenkten Halbschranke in deren totem Winkel stand; und das Fehlen dieser Einschränkungen in der Sicherheitsbewertung des Bahnübergangs.',
                'sources' => ['bea-page-2019'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT formule des recommandations sur le statut d\'équipement routier de la signalisation de position des passages à niveau, l\'adaptation des carrefours voisins à la giration des véhicules lourds, les diagnostics de sécurité, et la faisabilité de caméras sur les passages à niveau et en tête des trains.',
                'en' => 'The BEA-TT makes recommendations on classing the position signals of level crossings as road equipment, on adapting nearby junctions to the turning of heavy vehicles, on safety assessments, and on the feasibility of cameras at level crossings and on the front of trains.',
                'de' => 'Die BEA-TT gibt Empfehlungen zur Einstufung der Lichtzeichen an Bahnübergängen als Straßenausrüstung, zur Anpassung benachbarter Kreuzungen an das Abbiegen schwerer Fahrzeuge, zu den Sicherheitsdiagnosen und zur Machbarkeit von Kameras an Bahnübergängen und an der Spitze der Züge.',
                'sources' => ['bea-page-2019'],
            ],
            [
                'fr' => 'Le procès de première instance s\'est ouvert à Marseille le 19 septembre 2022 ; la conductrice était la seule prévenue et niait avoir forcé la barrière. Selon Wikipédia, le tribunal l\'a condamnée le 18 novembre 2022 à cinq ans de prison dont quatre avec sursis ; en appel à Aix-en-Provence, la peine a été portée le 7 février 2025 à cinq ans dont deux ferme, à purger sous bracelet électronique.',
                'en' => 'The first-instance trial opened in Marseille on 19 September 2022; the driver was the only defendant and denied having forced the barrier. According to Wikipedia, the court sentenced her on 18 November 2022 to five years in prison, four of them suspended; on appeal in Aix-en-Provence the sentence was raised on 7 February 2025 to five years, two of them to be served, under electronic tagging.',
                'de' => 'Der Prozess erster Instanz begann am 19. September 2022 in Marseille; die Fahrerin war die einzige Angeklagte und bestritt, die Schranke durchbrochen zu haben. Laut Wikipedia verurteilte sie das Gericht am 18. November 2022 zu fünf Jahren Haft, davon vier auf Bewährung; in der Berufung in Aix-en-Provence wurde die Strafe am 7. Februar 2025 auf fünf Jahre erhöht, davon zwei ohne Bewährung, zu verbüßen mit elektronischer Fußfessel.',
                'sources' => ['presse-2022-proces', 'wp-fr'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'Une stèle portant les prénoms des six victimes a été inaugurée le 14 décembre 2019 près du passage à niveau.',
                'en' => 'A memorial stone bearing the first names of the six victims was unveiled on 14 December 2019 near the level crossing.',
                'de' => 'Ein Gedenkstein mit den Vornamen der sechs Opfer wurde am 14. Dezember 2019 in der Nähe des Bahnübergangs enthüllt.',
                'sources' => ['wp-fr'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés varie : 14 dans la fiche (liste et Wikidata), 17 dans le texte de Wikipédia et dans la presse, 18 dans l\'encadré de Wikipédia, et 18 blessés graves selon le BEA-TT (« les 18 autres personnes de l\'autocar »), auxquels s\'ajoutent quelques blessés légers du train. Les sources lues ne concordent pas, la fiche n\'est donc pas corrigée sur ce point.',
                'en' => 'The number of injured varies: 14 in the record (list and Wikidata), 17 in the Wikipedia text and in the press, 18 in the Wikipedia infobox, and 18 seriously injured according to the BEA-TT ("the 18 other people in the coach"), plus a few slightly injured on the train. The sources read do not agree, so the record is not corrected on this point.',
                'de' => 'Die Zahl der Verletzten schwankt: 14 im Datensatz (Liste und Wikidata), 17 im Wikipedia-Text und in der Presse, 18 im Wikipedia-Infokasten und 18 Schwerverletzte laut BEA-TT („die 18 übrigen Personen im Bus“), hinzu kommen einige Leichtverletzte im Zug. Die gelesenen Quellen stimmen nicht überein, der Datensatz wird deshalb in diesem Punkt nicht korrigiert.',
                'sources' => ['bea-page-2019', 'wp-fr', 'presse-2022-proces'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_at_time',
            'value' => '16:07',
            'sources' => ['bea-page-2019', 'wp-fr'],
            'fr' => 'Le BEA-TT et Wikipédia placent la collision vers 16 h 07.',
            'en' => 'The BEA-TT and Wikipedia place the collision at about 4.07 pm.',
            'de' => 'Die BEA-TT und Wikipedia nennen als Zeitpunkt der Kollision etwa 16.07 Uhr.',
        ],
    ],
];
