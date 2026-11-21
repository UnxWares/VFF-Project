<?php

// Contexte sourcé de la collision au passage à niveau n° 2 d'Avenay-Val-d'Or du 15 juillet 2019 (recherche du 21/11/2026).
return [
    'slug' => '2019-07-15-avenay-val-dor',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-2019-07' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur la collision entre un TER et un véhicule léger le 15/07/2019 à Avenay-Val d\'Or (51)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2019-07.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport complet : lieux, reconstitution à la seconde près, causes, recommandation et invitation.',
        ],
        'bea-tt-page-avenay' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Avenay-Val-d\'Or (51), 15/07/2019 : collision entre un TER et un véhicule léger',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/avenay-val-d-or-51-15-07-2019-collision-entre-un-r284.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation de l\'enquête avec le résumé du bilan.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le passage à niveau n° 2 est situé en agglomération sur la route départementale 201, à une quarantaine de mètres de la gare d\'Avenay-Val-d\'Or, sur la ligne d\'Épernay à Reims. Il est équipé d\'une signalisation lumineuse et sonore automatique et de deux demi-barrières. La route y a un tracé en S, avec une vitesse limitée à 50 km/h ; la ligne, à voie unique, voit passer 36 trains de voyageurs par jour.',
                'en' => 'Level crossing no. 2 lies in the built-up area on departmental road 201, about forty metres from Avenay-Val-d\'Or station, on the Épernay to Reims line. It has automatic light and sound signals and two half-barriers. The road follows an S-shaped course there, with a 50 km/h limit; the single-track line carries 36 passenger trains a day.',
                'de' => 'Der Bahnübergang Nr. 2 liegt innerorts an der Départementsstraße 201, etwa vierzig Meter vom Bahnhof Avenay-Val-d\'Or entfernt, an der Strecke von Épernay nach Reims. Er ist mit automatischen Licht- und Tonsignalen und zwei Halbschranken ausgestattet. Die Straße verläuft dort in S-Form, die Höchstgeschwindigkeit beträgt 50 km/h; auf der eingleisigen Strecke verkehren täglich 36 Reisezüge.',
                'sources' => ['bea-tt-2019-07'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le lundi 15 juillet 2019 à 9 h 45, le TER 839 945, d\'Épernay vers Reims, a heurté une automobile sur le passage à niveau. Il circulait à 116 km/h, pour une vitesse maximale autorisée de 120 km/h. Le conducteur du train a déclenché le freinage d\'urgence environ une seconde avant le passage au droit du passage à niveau ; la rame s\'est arrêtée 450 mètres plus loin.',
                'en' => 'On Monday 15 July 2019 at 9.45 am, TER 839 945, from Épernay towards Reims, struck a car at the level crossing. It was travelling at 116 km/h, with a maximum permitted speed of 120 km/h. The train driver applied the emergency brake about one second before passing the crossing; the train stopped 450 metres further on.',
                'de' => 'Am Montag, dem 15. Juli 2019, um 9.45 Uhr erfasste der TER 839 945 von Épernay in Richtung Reims am Bahnübergang ein Auto. Er fuhr mit 116 km/h bei einer zulässigen Höchstgeschwindigkeit von 120 km/h. Der Lokführer löste die Schnellbremsung etwa eine Sekunde vor dem Passieren des Übergangs aus; der Zug kam 450 Meter weiter zum Stehen.',
                'sources' => ['bea-tt-2019-07'],
            ],
            [
                'fr' => 'Selon la reconstitution du BEA-TT, l\'automobile roulait à environ 35 km/h deux secondes avant le choc, sans freiner, et a heurté la demi-barrière abaissée. À ce moment, le train était masqué à la conductrice par une maison et un mur de clôture. Le véhicule a été projeté à une quarantaine de mètres au-delà du passage à niveau.',
                'en' => 'According to the BEA-TT reconstruction, the car was travelling at about 35 km/h two seconds before the impact, without braking, and struck the lowered half-barrier. At that moment the train was hidden from the driver by a house and a boundary wall. The vehicle was thrown about forty metres beyond the crossing.',
                'de' => 'Nach der Rekonstruktion des BEA-TT fuhr das Auto zwei Sekunden vor dem Aufprall mit etwa 35 km/h, ohne zu bremsen, und prallte gegen die geschlossene Halbschranke. In diesem Moment war der Zug für die Fahrerin durch ein Haus und eine Einfriedungsmauer verdeckt. Das Fahrzeug wurde etwa vierzig Meter hinter den Übergang geschleudert.',
                'sources' => ['bea-tt-2019-07'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les quatre occupants de l\'automobile, la conductrice et trois enfants, sont morts sur le coup. Vingt passagers et le conducteur se trouvaient dans le train, qui n\'a pas déraillé ; quatre passagers ont été légèrement blessés.',
                'en' => 'The four occupants of the car, the driver and three children, died at the scene. Twenty passengers and the driver were on the train, which did not derail; four passengers were slightly injured.',
                'de' => 'Die vier Insassen des Autos, die Fahrerin und drei Kinder, starben an der Unfallstelle. Im Zug, der nicht entgleiste, befanden sich zwanzig Fahrgäste und der Triebfahrzeugführer; vier Fahrgäste wurden leicht verletzt.',
                'sources' => ['bea-tt-2019-07', 'bea-tt-page-avenay'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT retient comme cause directe le non-arrêt du véhicule léger au passage à niveau fermé. Il relève aussi la présence dans l\'organisme de la conductrice d\'un médicament, qui a très vraisemblablement joué un rôle en altérant ses facultés de conduite.',
                'en' => 'The BEA-TT gives as the direct cause the failure of the light vehicle to stop at the closed level crossing. It also notes the presence in the driver\'s body of a medicine, which very probably played a part by impairing her ability to drive.',
                'de' => 'Als unmittelbare Ursache nennt das BEA-TT, dass das Fahrzeug am geschlossenen Bahnübergang nicht anhielt. Es stellt zudem ein Medikament im Körper der Fahrerin fest, das sehr wahrscheinlich eine Rolle spielte, weil es ihre Fahrtüchtigkeit beeinträchtigte.',
                'quote' => ['text' => 'La cause directe de l\'accident est le non-arrêt du véhicule léger au passage à niveau fermé.', 'lang' => 'fr', 'source' => 'bea-tt-2019-07'],
                'sources' => ['bea-tt-2019-07'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a formulé une recommandation à la direction générale de l\'énergie et du climat : porter devant les instances internationales l\'intérêt d\'inclure la détection des barrières abaissées dans les cas d\'usage pour l\'homologation des systèmes automatiques de freinage d\'urgence des véhicules routiers.',
                'en' => 'The BEA-TT made one recommendation to the French directorate-general for energy and climate: to put to international bodies the case for including the detection of lowered level-crossing barriers among the use cases for approving automatic emergency braking systems on road vehicles.',
                'de' => 'Das BEA-TT sprach eine Empfehlung an die französische Generaldirektion für Energie und Klima aus: bei den internationalen Gremien dafür zu werben, die Erkennung geschlossener Bahnübergangsschranken in die Anwendungsfälle für die Zulassung automatischer Notbremssysteme von Straßenfahrzeugen aufzunehmen.',
                'sources' => ['bea-tt-2019-07'],
            ],
            [
                'fr' => 'Il a aussi invité, sans recommandation formelle, la mairie d\'Avenay-Val-d\'Or, le conseil départemental de la Marne et SNCF Réseau à améliorer la visibilité des feux rouges clignotants et de la demi-barrière abaissée pour les usagers circulant dans le sens du véhicule impliqué. Il regrette que le constructeur du véhicule n\'ait répondu à aucune de ses demandes de documentation technique, sans que cela invalide ses hypothèses.',
                'en' => 'Without a formal recommendation, it also invited the town hall of Avenay-Val-d\'Or, the Marne departmental council and SNCF Réseau to improve the visibility of the flashing red lights and of the lowered half-barrier for road users travelling in the direction of the vehicle involved. It regrets that the vehicle manufacturer answered none of its requests for technical documentation, which did not invalidate its hypotheses.',
                'de' => 'Ohne förmliche Empfehlung lud es zudem die Gemeinde Avenay-Val-d\'Or, den Generalrat der Marne und SNCF Réseau ein, die Sichtbarkeit der blinkenden roten Lichter und der geschlossenen Halbschranke für Verkehrsteilnehmer in Fahrtrichtung des beteiligten Fahrzeugs zu verbessern. Es bedauert, dass der Fahrzeughersteller keine seiner Anfragen nach technischen Unterlagen beantwortete, was seine Hypothesen jedoch nicht entkräftete.',
                'sources' => ['bea-tt-2019-07'],
            ],
        ],
    ],
    'corrections' => [],
];
