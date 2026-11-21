<?php

// Contexte sourcé de la collision au passage à niveau n° 7 de Bourg-en-Bresse du 9 octobre 2020 (recherche du 21/11/2026).
return [
    'slug' => '2020-10-09-bourg-en-bresse',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-2020-08' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur la collision entre un TER et un véhicule léger survenue le 9 octobre 2020 sur le passage à niveau n° 7 à Bourg-en-Bresse (01)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'affaire BEATT-2020-08',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2020-08.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport complet : résumé, causes, conclusion et recommandation sur l\'îlot central.',
        ],
        'bea-tt-page-bourg' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Bourg-en-Bresse (01), 9/10/2020 : collision entre un TER et un véhicule léger',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/bourg-en-bresse-01-9-10-2020-collision-entre-un-r302.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation de l\'enquête.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le passage à niveau n° 7 de Bourg-en-Bresse, sur la ligne vers Oyonnax, est équipé d\'une signalisation lumineuse et sonore automatique et de deux demi-barrières. Il n\'était pas inscrit, au moment de l\'accident, au programme national de sécurisation des passages à niveau. Un diagnostic de sécurité réalisé le 18 octobre 2018 avait relevé l\'absence d\'îlot central du côté de la ville, qui facilite le contournement d\'une demi-barrière baissée.',
                'en' => 'Level crossing no. 7 at Bourg-en-Bresse, on the line towards Oyonnax, has automatic light and sound signals and two half-barriers. At the time of the accident it was not included in the national level crossing safety programme. A safety diagnosis carried out on 18 October 2018 had noted the absence of a central island on the town side, which makes it easier to drive round a lowered half-barrier.',
                'de' => 'Der Bahnübergang Nr. 7 in Bourg-en-Bresse an der Strecke nach Oyonnax hat automatische Licht- und Tonsignale und zwei Halbschranken. Zum Zeitpunkt des Unfalls war er nicht im nationalen Programm zur Sicherung von Bahnübergängen enthalten. Eine Sicherheitsdiagnose vom 18. Oktober 2018 hatte das Fehlen einer Mittelinsel auf der Stadtseite festgestellt, die das Umfahren einer geschlossenen Halbschranke erleichtert.',
                'sources' => ['bea-tt-2020-08'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le vendredi 9 octobre 2020 à 10 h 34, un TER de Bourg-en-Bresse vers Oyonnax, roulant à 100 km/h, a heurté une voiture arrêtée sur le passage à niveau fermé. La voiture a été projetée à plus de 30 mètres en aval ; le train n\'a pas déraillé.',
                'en' => 'On Friday 9 October 2020 at 10.34 am, a TER from Bourg-en-Bresse towards Oyonnax, travelling at 100 km/h, struck a car stopped on the closed level crossing. The car was thrown more than 30 metres beyond the crossing; the train did not derail.',
                'de' => 'Am Freitag, dem 9. Oktober 2020, um 10.34 Uhr erfasste ein TER von Bourg-en-Bresse in Richtung Oyonnax mit 100 km/h ein auf dem geschlossenen Bahnübergang stehendes Auto. Das Auto wurde mehr als 30 Meter hinter den Übergang geschleudert; der Zug entgleiste nicht.',
                'sources' => ['bea-tt-2020-08', 'bea-tt-page-bourg'],
            ],
            [
                'fr' => 'Les investigations n\'ont pas permis de savoir si la voiture s\'était engagée avant la fermeture, pendant celle-ci, ou en contournant la demi-barrière déjà baissée. Les occupants ne l\'avaient pas évacuée après son arrêt.',
                'en' => 'The investigations could not establish whether the car had entered before the closing sequence, during it, or by driving round the already lowered half-barrier. The occupants had not left the vehicle after it stopped.',
                'de' => 'Die Untersuchungen konnten nicht klären, ob das Auto vor Beginn des Schließvorgangs, währenddessen oder unter Umfahren der bereits geschlossenen Halbschranke eingefahren war. Die Insassen hatten das Fahrzeug nach dem Anhalten nicht verlassen.',
                'sources' => ['bea-tt-2020-08'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les deux occupants de la voiture ont été tués sur le coup. Les dix-huit passagers, le contrôleur et l\'agent de conduite du train n\'ont pas été blessés.',
                'en' => 'The two occupants of the car were killed instantly. The eighteen passengers, the ticket inspector and the driver of the train were not injured.',
                'de' => 'Die beiden Insassen des Autos wurden sofort getötet. Die achtzehn Fahrgäste, der Zugbegleiter und der Triebfahrzeugführer blieben unverletzt.',
                'sources' => ['bea-tt-2020-08', 'bea-tt-page-bourg'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT retient comme cause directe l\'arrêt du véhicule sur la voie ferrée alors que le passage à niveau était fermé. Les équipements ont fonctionné correctement et rien n\'a suggéré un acte volontaire. La raison de l\'arrêt n\'a pas pu être établie avec certitude : le rapport retient une défaillance mécanique due au mauvais état d\'entretien comme hypothèse la plus probable, et n\'écarte pas un malaise du conducteur, que l\'autopsie n\'a pas confirmé.',
                'en' => 'The BEA-TT gives as the direct cause the vehicle\'s stopping on the railway while the level crossing was closed. The equipment worked correctly and nothing suggested a deliberate act. The reason for the stop could not be established with certainty: the report considers a mechanical failure due to poor maintenance the most likely hypothesis, and does not rule out the driver being taken ill, which the autopsy did not confirm.',
                'de' => 'Als unmittelbare Ursache nennt das BEA-TT das Anhalten des Fahrzeugs auf dem Gleis bei geschlossenem Bahnübergang. Die Einrichtungen arbeiteten korrekt, und nichts deutete auf eine Absicht hin. Der Grund für das Anhalten ließ sich nicht sicher klären: Der Bericht hält einen mechanischen Defekt wegen schlechten Wartungszustands für die wahrscheinlichste Hypothese und schließt eine Erkrankung des Fahrers nicht aus, die die Autopsie nicht bestätigt hat.',
                'quote' => ['text' => 'La cause directe de l\'accident est l\'arrêt du véhicule routier sur la voie ferrée alors que le passage à niveau était fermé.', 'lang' => 'fr', 'source' => 'bea-tt-2020-08'],
                'sources' => ['bea-tt-2020-08'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT n\'a pas formulé de recommandation liée aux circonstances de l\'accident. Il a recommandé à la ville de Bourg-en-Bresse et au conseil départemental de l\'Ain de finaliser l\'étude d\'un îlot central bordé du côté de la ville, pour dissuader le franchissement en chicane du passage à niveau.',
                'en' => 'The BEA-TT made no recommendation linked to the circumstances of the accident. It recommended that the city of Bourg-en-Bresse and the Ain departmental council finalise the study of a kerbed central island on the town side, to discourage weaving round the crossing barriers.',
                'de' => 'Das BEA-TT sprach keine Empfehlung zu den Unfallumständen aus. Es empfahl der Stadt Bourg-en-Bresse und dem Départementsrat des Ain, die Untersuchung einer eingefassten Mittelinsel auf der Stadtseite abzuschließen, um das Umfahren des Bahnübergangs im Slalom zu verhindern.',
                'sources' => ['bea-tt-2020-08'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2020-08', 'bea-tt-page-bourg'],
            'fr' => 'Le rapport indique qu\'aucune des personnes à bord du train n\'a été blessée ; les deux occupants de la voiture sont morts.',
            'en' => 'The report states that nobody on board the train was injured; the two car occupants died.',
            'de' => 'Der Bericht gibt an, dass niemand im Zug verletzt wurde; die beiden Autoinsassen starben.',
        ],
    ],
];
