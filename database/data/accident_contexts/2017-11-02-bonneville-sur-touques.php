<?php

// Contexte sourcé de la collision au passage à niveau n° 8 de Bonneville-sur-Touques du 2 novembre 2017 (recherche du 21/11/2026).
return [
    'slug' => '2017-11-02-bonneville-sur-touques',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-2017-05' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur la collision entre un TER et un véhicule léger survenue le 2 novembre 2017 au passage à niveau n° 8 à Bonneville-sur-Touques (14)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'affaire BEATT-2017-05',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2017-05.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport complet : constats, contexte, déroulement, causes, deux recommandations.',
        ],
        'bea-tt-page-bonneville' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Bonneville-sur-Touques (14), 2/11/2017 : collision entre un TER et un véhicule léger',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/bonneville-sur-touques-14-2-11-2017-collision-r263.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation de l\'enquête avec le résumé du bilan.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le passage à niveau n° 8 est un passage non gardé, protégé par une croix de Saint-André et un panneau « Stop », sur le chemin de la Libération. Il se trouve sur la ligne à voie unique de Lisieux à Trouville-Deauville, électrifiée, limitée à 140 km/h et parcourue chaque jour par 22 trains de voyageurs. Il donne accès à des marais, par un chemin en impasse dont le revêtement s\'arrête juste après la voie.',
                'en' => 'Level crossing no. 8 is an unguarded crossing, protected by a St Andrew\'s cross and a "Stop" sign, on Chemin de la Libération. It lies on the single-track, electrified Lisieux to Trouville-Deauville line, limited to 140 km/h and used by 22 passenger trains a day. It gives access to marshland by a dead-end track whose surface ends just after the railway.',
                'de' => 'Der Bahnübergang Nr. 8 ist ein unbewachter, mit Andreaskreuz und Stoppschild gesicherter Übergang am Chemin de la Libération. Er liegt an der eingleisigen, elektrifizierten Strecke von Lisieux nach Trouville-Deauville, die mit 140 km/h befahren werden darf und täglich von 22 Reisezügen benutzt wird. Er führt über einen Sackgassenweg, dessen Belag unmittelbar hinter dem Gleis endet, zu Marschland.',
                'sources' => ['bea-tt-2017-05'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le jeudi 2 novembre 2017 à 15 h 52, le TER 852429 de Lisieux à Trouville-Deauville, qui roulait à 135 km/h avec 95 personnes à bord, a heurté une automobile sur ce passage à niveau. Le premier bogie a déraillé et le train a percuté un poteau de caténaire, sans se renverser. La voiture a été disloquée : la carcasse principale a été projetée à 70 mètres du passage à niveau et des débris se sont répandus sur 400 mètres.',
                'en' => 'On Thursday 2 November 2017 at 3.52 pm, TER 852429 from Lisieux to Trouville-Deauville, travelling at 135 km/h with 95 people on board, struck a car at this crossing. The first bogie derailed and the train hit a catenary pole, without overturning. The car was broken up: the main wreck was thrown 70 metres from the crossing and debris was scattered over 400 metres.',
                'de' => 'Am Donnerstag, dem 2. November 2017, um 15.52 Uhr erfasste der TER 852429 von Lisieux nach Trouville-Deauville, der mit 135 km/h und 95 Personen an Bord fuhr, an diesem Bahnübergang ein Auto. Das erste Drehgestell entgleiste, und der Zug prallte gegen einen Fahrleitungsmast, ohne umzukippen. Das Auto wurde zerlegt: Das Hauptwrack wurde 70 Meter vom Übergang weggeschleudert, Trümmer verteilten sich über 400 Meter.',
                'sources' => ['bea-tt-2017-05', 'bea-tt-page-bonneville'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les trois occupants de la voiture sont morts dans la collision. Aucune des 95 personnes à bord du train n\'a été tuée ni blessée ; le conducteur et un voyageur ont été choqués. Le plan ORSEC « nombreuses victimes » a été déclenché, et les voyageurs ont été conduits à Deauville par la route.',
                'en' => 'The three occupants of the car died in the collision. None of the 95 people on the train was killed or injured; the driver and one passenger were in shock. The ORSEC "many casualties" plan was triggered, and passengers were taken to Deauville by road.',
                'de' => 'Die drei Insassen des Autos starben bei der Kollision. Von den 95 Personen im Zug wurde keine getötet oder verletzt; der Lokführer und ein Fahrgast standen unter Schock. Der ORSEC-Plan für zahlreiche Opfer wurde ausgelöst, die Fahrgäste wurden auf der Straße nach Deauville gebracht.',
                'sources' => ['bea-tt-2017-05'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT retient comme cause directe que le conducteur de la voiture n\'a pas perçu l\'arrivée du train avant de traverser. La cause sous-jacente serait une erreur d\'orientation : le conducteur s\'est engagé dans une voie en impasse, a constaté après le passage à niveau l\'absence de revêtement, puis a fait demi-tour et s\'est engagé de nouveau sur le passage à niveau à l\'arrivée du TER.',
                'en' => 'The BEA-TT gives as the direct cause that the driver of the car did not notice the train\'s approach before crossing. The underlying cause is probably a navigation error: the driver entered a dead-end road, noticed the lack of road surface after the crossing, then turned round and entered the crossing again as the TER arrived.',
                'de' => 'Als unmittelbare Ursache nennt das BEA-TT, dass der Fahrer des Autos das Herannahen des Zuges vor dem Überqueren nicht wahrnahm. Die zugrunde liegende Ursache sei wahrscheinlich ein Orientierungsfehler: Der Fahrer fuhr in eine Sackgasse, bemerkte nach dem Übergang den fehlenden Straßenbelag, wendete und fuhr beim Eintreffen des TER erneut in den Übergang.',
                'sources' => ['bea-tt-2017-05'],
            ],
            [
                'fr' => 'Parmi les facteurs associés, le rapport cite un diagnostic de sécurité qui n\'avait pas relevé les risques liés à l\'environnement du passage, l\'accès aux marais ouvert à tous, et l\'absence de dispositif lumineux et sonore au passage à niveau : un tel dispositif aurait alerté au moins 20 secondes avant l\'arrivée du train, alors que le sifflet du TER n\'a retenti que 8 secondes avant.',
                'en' => 'Among the associated factors, the report cites a safety diagnosis that had not identified the risks linked to the crossing\'s surroundings, access to the marshes open to everyone, and the absence of light and sound signals at the crossing: such a device would have given warning at least 20 seconds before the train arrived, whereas the TER\'s horn sounded only 8 seconds before.',
                'de' => 'Zu den beteiligten Faktoren zählt der Bericht eine Sicherheitsdiagnose, die die mit der Umgebung des Übergangs verbundenen Risiken nicht erkannt hatte, den für jedermann offenen Zugang zu den Sümpfen und das Fehlen von Licht- und Tonsignalen am Übergang: Eine solche Einrichtung hätte mindestens 20 Sekunden vor der Ankunft des Zuges gewarnt, während das Signalhorn des TER erst 8 Sekunden vorher ertönte.',
                'sources' => ['bea-tt-2017-05'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La préfecture, le conseil départemental et la commune ont lancé une procédure de suppression du passage à niveau n° 8 ; en attendant, la signalisation routière a été renforcée à l\'entrée du chemin. Le BEA-TT a ouvert son enquête le 9 novembre 2017 et émis deux recommandations : à la commune, un dispositif interdisant l\'accès aux personnes autres que les ayants droit ; à SNCF Réseau, une étude des risques des passages à croix de Saint-André situés sur des lignes à 140 km/h.',
                'en' => 'The prefecture, the departmental council and the commune started a procedure to remove level crossing no. 8; meanwhile road signs were reinforced at the entrance to the track. The BEA-TT opened its investigation on 9 November 2017 and made two recommendations: to the commune, a device barring access to anyone other than those entitled to use it; to SNCF Réseau, a risk study of St Andrew\'s cross crossings on lines run at 140 km/h.',
                'de' => 'Präfektur, Départementsrat und Gemeinde leiteten ein Verfahren zur Beseitigung des Bahnübergangs Nr. 8 ein; bis dahin wurde die Straßenbeschilderung am Zugang zum Weg verstärkt. Das BEA-TT eröffnete seine Untersuchung am 9. November 2017 und sprach zwei Empfehlungen aus: an die Gemeinde eine Einrichtung, die den Zugang für Nichtberechtigte verwehrt; an SNCF Réseau eine Risikostudie zu Übergängen mit Andreaskreuz an Strecken mit 140 km/h.',
                'sources' => ['bea-tt-2017-05'],
            ],
        ],
    ],
    'corrections' => [],
];
