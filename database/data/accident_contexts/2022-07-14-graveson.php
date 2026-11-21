<?php

// Contexte sourcé de l'incendie de la Montagnette parti des abords de la voie à Graveson le 14 juillet 2022 (recherche du 21/11/2026).
return [
    'slug' => '2022-07-14-graveson',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-2022-06' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête sur l\'incendie aux abords des voies suite au passage d\'un train de marchandises le 14 juillet 2022 à Graveson (13)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => '2025-02',
            'locator' => 'affaire BEATT-2022-06',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt-2022-06_graveson_rapport_final-v06022025.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport final (version du 6 février 2025) : synthèse en français et en anglais, facteurs, cinq recommandations.',
        ],
        'bea-tt-page-graveson' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Graveson (13), 14/07/2022 : incendie aux abords des voies suite au passage d\'un train de marchandises',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/graveson-13-14-07-2022-incendie-aux-abords-des-r330.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 14 juillet 2022 vers 16 h 30, le train de fret 489 038 (Fos-Coussoul à Gevrey-Triage) a été arrêté sur la voie 2 entre Barbentane et Avignon, au point kilométrique 744,730, après son passage sur le détecteur de boîte chaude de Graveson, qui avait signalé une alarme de danger de frein sur un wagon d\'essence. Le wagon avait circulé avec les freins serrés ; le conducteur a isolé le frein pendant la visite de son train.',
                'en' => 'On 14 July 2022 at about 4.30 pm, freight train 489 038 (Fos-Coussoul to Gevrey-Triage) was stopped on track 2 between Barbentane and Avignon, at kilometre point 744.730, after passing the Graveson hot-axle-box detector, which had raised a brake danger alarm on a petrol wagon. The wagon had been running with its brakes applied; the driver isolated the brake while inspecting his train.',
                'de' => 'Am 14. Juli 2022 gegen 16.30 Uhr wurde der Güterzug 489 038 (Fos-Coussoul nach Gevrey-Triage) auf Gleis 2 zwischen Barbentane und Avignon bei Kilometerpunkt 744,730 angehalten, nachdem er den Heißläuferdetektor von Graveson passiert hatte, der bei einem Benzinwagen einen Bremsenalarm gemeldet hatte. Der Wagen war mit angezogenen Bremsen gefahren; der Lokführer isolierte bei der Zugkontrolle die Bremse.',
                'sources' => ['bea-tt-2022-06'],
            ],
            [
                'fr' => 'Au même moment, le conducteur du train suiveur a signalé des départs de feux aux abords de la voie 2, entre les points kilométriques 769 et 751. Les feux se sont propagés en un violent incendie qui a touché les communes de Barbentane, Graveson, Tarascon et Boulbon, puis, avec le vent et la canicule, le massif de la Montagnette, sur près de 1 450 hectares.',
                'en' => 'At the same time, the driver of the following train reported fires starting beside track 2, between kilometre points 769 and 751. The fires spread into a fierce blaze that hit the communes of Barbentane, Graveson, Tarascon and Boulbon, then, helped by wind and the heatwave, the Montagnette massif, over nearly 1,450 hectares.',
                'de' => 'Gleichzeitig meldete der Lokführer des nachfolgenden Zuges Brandherde neben Gleis 2 zwischen den Kilometerpunkten 769 und 751. Die Feuer breiteten sich zu einem heftigen Brand aus, der die Gemeinden Barbentane, Graveson, Tarascon und Boulbon erfasste und dann, begünstigt von Wind und Hitzewelle, das Massiv der Montagnette auf fast 1 450 Hektar.',
                'sources' => ['bea-tt-2022-06'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois cents maisons ont été évacuées sans qu\'aucune soit touchée, et les pompiers ont protégé l\'abbaye de Frigolet. Un sapeur-pompier volontaire est mort le 25 juillet des suites d\'un malaise survenu le 15 juillet pendant la lutte contre le feu. Les installations électriques ferroviaires ont subi des dégâts considérables, avec plusieurs câbles principaux de signalisation et d\'alimentation touchés, et le plan de transport a été adapté, avec détournements de trains et autocars.',
                'en' => 'Three hundred houses were evacuated without any being hit, and firefighters protected Frigolet abbey. A volunteer firefighter died on 25 July after being taken ill on 15 July while fighting the fire. The railway\'s electrical installations suffered considerable damage, with several main signalling and power cables hit, and the transport plan was adapted, with trains diverted and coaches substituted.',
                'de' => 'Dreihundert Häuser wurden evakuiert, ohne dass eines getroffen wurde, und die Feuerwehr schützte die Abtei Frigolet. Ein freiwilliger Feuerwehrmann starb am 25. Juli an den Folgen einer am 15. Juli bei der Brandbekämpfung erlittenen Unpässlichkeit. Die elektrischen Bahnanlagen erlitten erhebliche Schäden, mehrere Haupt-Signal- und Stromkabel wurden getroffen, und der Fahrplan wurde angepasst, mit umgeleiteten Zügen und Busersatz.',
                'sources' => ['bea-tt-2022-06'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT retient comme facteurs contributifs la canicule, qui a favorisé la propagation de l\'incendie, et les difficultés d\'accès à la Montagnette pour les engins lourds des pompiers, qui ont entravé leur intervention.',
                'en' => 'The BEA-TT gives as contributing factors the heatwave, which helped the fire spread, and the difficulty of reaching the Montagnette for the firefighters\' heavy vehicles, which hampered their work.',
                'de' => 'Als mitwirkende Faktoren nennt das BEA-TT die Hitzewelle, die die Ausbreitung des Brandes begünstigte, und die schwierige Zugänglichkeit der Montagnette für die schweren Fahrzeuge der Feuerwehr, die deren Einsatz behinderte.',
                'sources' => ['bea-tt-2022-06'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a émis cinq recommandations : étudier des solutions provisoires et rapides pour la traversée des voies par les véhicules lourds en section courante, rapprocher les détecteurs de boîte chaude et de frein serré dans les zones sensibles et en abaisser les seuils, donner plus de souplesse à SNCF Réseau pour ses obligations de débroussaillement, renforcer la surveillance en marche des conducteurs de fret, et expérimenter de meilleures détections d\'anomalies du freinage.',
                'en' => 'The BEA-TT made five recommendations: to study quick temporary solutions for heavy vehicles crossing the tracks on open line, to bring hot-axle-box and tight-brake detectors closer together in sensitive areas and lower their thresholds, to give SNCF Réseau more flexibility over its brush-clearing obligations, to reinforce in-service monitoring by freight drivers, and to trial better detection of braking anomalies.',
                'de' => 'Das BEA-TT sprach fünf Empfehlungen aus: schnelle vorläufige Lösungen für das Überqueren der Gleise durch schwere Fahrzeuge auf der freien Strecke zu prüfen, Heißläufer- und Festbremsdetektoren in empfindlichen Zonen näher zusammenzurücken und ihre Schwellen zu senken, SNCF Réseau mehr Spielraum bei den Pflichten zur Vegetationsbeseitigung zu geben, die Überwachung während der Fahrt durch Güterzug-Lokführer zu verstärken und eine bessere Erkennung von Bremsanomalien zu erproben.',
                'sources' => ['bea-tt-2022-06'],
            ],
        ],
    ],
    'corrections' => [],
];
