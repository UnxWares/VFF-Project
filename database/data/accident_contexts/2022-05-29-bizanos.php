<?php

// Contexte sourcé de l'incident au passage à niveau n° 238 de Bizanos du 29 mai 2022 (recherche du 21/11/2026).
return [
    'slug' => '2022-05-29-bizanos',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-2022-05' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur l\'incident entre un train de voyageurs et un autocar survenu le 29 mai 2022 sur le PN n° 238 à Bizanos (64)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'affaire BEATT-2022-05',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2022_05_bizanos_rapport.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport complet : synthèse, causes, deux recommandations et deux invitations.',
        ],
        'bea-tt-page-bizanos' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Bizanos (64), 29/05/2022 : incident entre un train de voyageurs et un autocar',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/bizanos-64-29-05-2022-incident-entre-un-train-de-r334.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation : « Il n\'y a ni victimes, ni dégâts matériels. »',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le dimanche 29 mai 2022, peu après 17 heures, le train de voyageurs 8 585 de Tarbes à Paris a frôlé l\'arrière d\'un autocar immobilisé sur le passage à niveau n° 238, à Bizanos, dans les Pyrénées-Atlantiques. Le train, en freinage d\'urgence au moment du franchissement, roulait à 65 km/h et s\'est arrêté une centaine de mètres plus loin.',
                'en' => 'On Sunday 29 May 2022, shortly after 5 pm, passenger train 8 585 from Tarbes to Paris brushed against the rear of a coach stopped on level crossing no. 238, at Bizanos, in the Pyrénées-Atlantiques. The train, braking hard as it passed the crossing, was travelling at 65 km/h and stopped about a hundred metres further on.',
                'de' => 'Am Sonntag, dem 29. Mai 2022, kurz nach 17 Uhr streifte der Reisezug 8 585 von Tarbes nach Paris das Heck eines auf dem Bahnübergang Nr. 238 in Bizanos im Département Pyrénées-Atlantiques stehenden Reisebusses. Der Zug, der beim Überfahren des Übergangs schnellgebremst wurde, fuhr mit 65 km/h und hielt etwa hundert Meter weiter.',
                'sources' => ['bea-tt-2022-05'],
            ],
            [
                'fr' => 'Les soixante personnes environ qui se trouvaient dans l\'autocar, dont 53 enfants, avaient été évacuées avant l\'arrivée du train. Le car n\'a pas été heurté et a pu poursuivre sa route après avoir été dégagé.',
                'en' => 'The sixty or so people in the coach, including 53 children, had been evacuated before the train arrived. The coach was not hit and was able to continue its journey after being cleared.',
                'de' => 'Die etwa sechzig Personen im Reisebus, darunter 53 Kinder, waren vor dem Eintreffen des Zuges evakuiert worden. Der Bus wurde nicht getroffen und konnte nach dem Freimachen seine Fahrt fortsetzen.',
                'sources' => ['bea-tt-2022-05', 'bea-tt-page-bizanos'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Il n\'y a eu aucun blessé ni dans le train, ni parmi les occupants de l\'autocar, et aucun dégât matériel n\'a été constaté sur le train ni sur les infrastructures ferroviaires. L\'événement est qualifié d\'incident par le BEA-TT.',
                'en' => 'Nobody was injured, either on the train or among the coach occupants, and no material damage was found to the train or the railway infrastructure. The BEA-TT classes the event as an incident.',
                'de' => 'Weder im Zug noch unter den Businsassen gab es Verletzte, und weder am Zug noch an der Bahninfrastruktur wurde ein Sachschaden festgestellt. Das BEA-TT stuft das Ereignis als Zwischenfall ein.',
                'sources' => ['bea-tt-2022-05', 'bea-tt-page-bizanos'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause directe est le blocage du car, dont l\'arrière engageait le gabarit d\'une voie, du fait d\'un profil en long difficile de la route, conséquence du non-respect de la signalisation routière. Le conducteur du car avait emprunté un itinéraire interdit à ce type de véhicule, en se fiant à une application de navigation, et avait engagé son véhicule sur le passage à niveau sans prévenir SNCF Réseau ni faire descendre ses passagers. Le téléphone d\'alerte du passage à niveau était inopérant à la suite d\'un acte de vandalisme, et les installations du passage à niveau ont correctement fonctionné.',
                'en' => 'The direct cause is the coach getting stuck, its rear fouling the clearance of one track, because of a difficult longitudinal profile of the road, a consequence of failing to obey the road signs. The coach driver had taken a route barred to this type of vehicle, relying on a navigation app, and had driven onto the level crossing without warning SNCF Réseau or having his passengers get off. The crossing\'s alert telephone was out of order following an act of vandalism, and the crossing installations worked correctly.',
                'de' => 'Unmittelbare Ursache ist das Steckenbleiben des Busses, dessen Heck in das Profil eines Gleises ragte, wegen eines schwierigen Längsprofils der Straße infolge der Missachtung der Verkehrszeichen. Der Busfahrer war einer für diese Fahrzeugart gesperrten Route gefolgt, im Vertrauen auf eine Navigations-App, und war in den Bahnübergang eingefahren, ohne SNCF Réseau zu warnen oder die Fahrgäste aussteigen zu lassen. Das Alarmtelefon des Übergangs war nach einem Vandalenakt außer Betrieb, und die Einrichtungen des Übergangs arbeiteten korrekt.',
                'sources' => ['bea-tt-2022-05'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a formulé deux recommandations et deux invitations, portant notamment sur la préparation du voyage et de l\'itinéraire de retour, la clarté de la signalisation routière et l\'usage du téléphone de navigation au volant.',
                'en' => 'The BEA-TT made two recommendations and two invitations, covering among other things the preparation of the journey and the return route, the clarity of road signs and the use of a navigation phone while driving.',
                'de' => 'Das BEA-TT sprach zwei Empfehlungen und zwei Einladungen aus, die unter anderem die Vorbereitung der Fahrt und der Rückreiseroute, die Klarheit der Verkehrszeichen und die Nutzung des Navigationstelefons am Steuer betreffen.',
                'sources' => ['bea-tt-2022-05'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche portait une date au 30 mai 2022 et un mort ; le rapport et la page du BEA-TT donnent le dimanche 29 mai 2022 et indiquent qu\'il n\'y a eu ni victime ni blessé.',
                'en' => 'The record gave a date of 30 May 2022 and one death; the BEA-TT report and page give Sunday 29 May 2022 and state that there were no victims or injured.',
                'de' => 'Der Eintrag nannte den 30. Mai 2022 und einen Toten; der BEA-TT-Bericht und die Seite nennen Sonntag, den 29. Mai 2022, und geben an, dass es weder Opfer noch Verletzte gab.',
                'sources' => ['bea-tt-2022-05', 'bea-tt-page-bizanos'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_on',
            'value' => '2022-05-29',
            'sources' => ['bea-tt-2022-05', 'bea-tt-page-bizanos'],
            'fr' => 'Le rapport du BEA-TT date l\'incident du dimanche 29 mai 2022, peu après 17 heures.',
            'en' => 'The BEA-TT report dates the incident to Sunday 29 May 2022, shortly after 5 pm.',
            'de' => 'Der BEA-TT-Bericht datiert den Zwischenfall auf Sonntag, den 29. Mai 2022, kurz nach 17 Uhr.',
        ],
        [
            'field' => 'deaths',
            'value' => 0,
            'sources' => ['bea-tt-2022-05', 'bea-tt-page-bizanos'],
            'fr' => 'Aucune victime : l\'autocar n\'a pas été heurté et ses occupants avaient été évacués.',
            'en' => 'No victims: the coach was not hit and its occupants had been evacuated.',
            'de' => 'Keine Opfer: Der Bus wurde nicht getroffen und seine Insassen waren evakuiert worden.',
        ],
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2022-05', 'bea-tt-page-bizanos'],
            'fr' => 'Le rapport ne constate aucun blessé, dans le train comme dans l\'autocar.',
            'en' => 'The report records no injured person, in the train or in the coach.',
            'de' => 'Der Bericht verzeichnet keinen Verletzten, weder im Zug noch im Bus.',
        ],
    ],
];
