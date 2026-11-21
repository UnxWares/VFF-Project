<?php

// Contexte sourcé du déraillement d'Ingenheim du 5 mars 2020 (recherche du 21/11/2026).
return [
    'slug' => '2020-03-05-ingenheim',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-rapport-2022' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête technique sur le déraillement d\'un TGV sur la ligne à grande vitesse Est européenne le 5 mars 2020 à Ingenheim (Bas-Rhin)',
            'publisher' => 'Bureau d\'enquêtes sur les accidents de transport terrestre (BEA-TT)',
            'author' => null,
            'published' => '2022-01',
            'locator' => 'Synthèse, §1, §4.11',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2020_03.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport final (BEATT-2020-03) : circonstances, points kilométriques, bilan, expertise géotechnique du déblai DB 537, causes, mesures postérieures, quatre recommandations et trois invitations.',
        ],
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Déraillement d\'Ingenheim',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=D%C3%A9raillement_d%27Ingenheim&oldid=239047738',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article court tiré du rapport du BEA-TT : TGV Euroduplex, 307 passagers et 4 membres d\'équipage, 21 blessés légers et un blessé grave (22 dans l\'encadré) ; aucune procédure judiciaire mentionnée.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 5 mars 2020, à 7 h 32, le TGV n° 2350 de Colmar vers Paris circule à 284 km/h sur la LGV Est européenne, dans un déblai au droit de la commune de Duntzenheim (Bas-Rhin). Il heurte au PK 390,980 un monticule de terre qui recouvre la voie.',
                'en' => 'On Thursday 5 March 2020 at 7.32 am, TGV no. 2350 from Colmar to Paris was running at 284 km/h on the LGV Est européenne, in a cutting near the commune of Duntzenheim (Bas-Rhin). At km 390.980 it hit a mound of earth covering the track.',
                'de' => 'Am Donnerstag, dem 5. März 2020, um 7.32 Uhr fuhr der TGV Nr. 2350 von Colmar nach Paris mit 284 km/h auf der LGV Est européenne in einem Einschnitt bei der Gemeinde Duntzenheim (Bas-Rhin). Bei km 390,980 prallte er auf einen Erdhaufen, der das Gleis bedeckte.',
                'sources' => ['bea-rapport-2022', 'wp-fr'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La motrice de tête a traversé la plate-forme et s\'est placée à cheval sur la voie opposée ; les trois premières remorques ont déraillé en restant à cheval sur leur voie. Les éléments articulés sont restés solidaires et la rame, guidée par les rails, s\'est arrêtée 1 635 m et 40 secondes plus tard (PK 389,345), sur le territoire d\'Ingenheim.',
                'en' => 'The leading power car crossed the track bed and came to rest astride the opposite track; the first three trailers derailed but stayed astride their own track. The articulated vehicles stayed together and the train, guided by the rails, stopped 1,635 m and 40 seconds later (km 389.345), in the territory of Ingenheim.',
                'de' => 'Der Triebkopf querte die Plattform und kam rittlings auf dem Gegengleis zum Stehen; die ersten drei Wagen entgleisten, blieben aber rittlings auf ihrem Gleis. Die Gelenkfahrzeuge blieben verbunden, und der von den Schienen geführte Zug hielt 1635 m und 40 Sekunden später (km 389,345) auf dem Gebiet von Ingenheim an.',
                'sources' => ['bea-rapport-2022'],
            ],
            [
                'fr' => 'Les pompiers sont arrivés à 8 h 05 et ont évacué le conducteur ; l\'évacuation des voyageurs, par un point d\'accès relié à la RD 667, a eu lieu entre 10 h 45 et 11 h 45. Un autre TGV, qui venait de quitter Strasbourg, a été retenu à Vendenheim.',
                'en' => 'Firefighters arrived at 8.05 am and evacuated the driver; the passengers were evacuated, through an access point linked to the RD 667, between 10.45 and 11.45 am. Another TGV that had just left Strasbourg was held at Vendenheim.',
                'de' => 'Die Feuerwehr traf um 8.05 Uhr ein und barg den Lokführer; die Fahrgäste wurden zwischen 10.45 und 11.45 Uhr über einen an die RD 667 angebundenen Zugang evakuiert. Ein weiterer TGV, der Straßburg gerade verlassen hatte, wurde in Vendenheim zurückgehalten.',
                'sources' => ['bea-rapport-2022'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Aucun mort. Le BEA-TT compte un blessé grave, le conducteur, hospitalisé 12 jours puis revenu à la conduite, et 21 blessés légers parmi les 307 personnes à bord, soit 22 blessés.',
                'en' => 'Nobody was killed. The BEA-TT counts one seriously injured person, the driver, who spent 12 days in hospital and later returned to driving, and 21 slightly injured among the 307 people on board, 22 injured in all.',
                'de' => 'Es gab keine Toten. Die BEA-TT zählt einen Schwerverletzten, den Lokführer, der 12 Tage im Krankenhaus lag und später wieder fuhr, und 21 Leichtverletzte unter den 307 Personen an Bord, insgesamt also 22 Verletzte.',
                'sources' => ['bea-rapport-2022'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le monticule venait de l\'effondrement par glissement, quelques minutes avant le passage du TGV, d\'un talus de déblai : le talus s\'est effondré sur 17 m de hauteur et une soixantaine de mètres de largeur. Le BEA-TT attribue le glissement à la faible résistance d\'argiles du Domérien, dont la résistance avait été surestimée lors de la justification de la stabilité à la conception.',
                'en' => 'The mound came from the landslip of a cutting slope a few minutes before the TGV passed: the slope collapsed over a height of 17 m and a width of about sixty metres. The BEA-TT attributes the slip to the low strength of Domerian clays, whose strength had been overestimated when stability was justified at the design stage.',
                'de' => 'Der Erdhaufen stammte von einem Rutsch einer Einschnittsböschung wenige Minuten vor der Durchfahrt des TGV: Die Böschung brach auf 17 m Höhe und etwa sechzig Meter Breite ab. Die BEA-TT führt den Rutsch auf die geringe Festigkeit von Domérien-Tonen zurück, deren Festigkeit beim Standsicherheitsnachweis in der Planung überschätzt worden war.',
                'sources' => ['bea-rapport-2022'],
            ],
            [
                'fr' => 'Il cite parmi les facteurs la faiblesse des reconnaissances de sol, la non-vérification des paramètres malgré plusieurs effondrements pendant le chantier de terrassement, et l\'absence de rattrapage par les acteurs du projet. L\'instabilité ne se serait révélée qu\'après quatre ans d\'exploitation, en raison du comportement évolutif des argiles ou d\'apports d\'eau.',
                'en' => 'Among the factors it cites weak ground investigations, the failure to check the parameters despite several collapses during the earthworks, and the absence of any catching of these errors by those involved in the project. The instability may have shown itself only after four years of operation because of the changing behaviour of the clays or of water inflows.',
                'de' => 'Als Faktoren nennt sie unzureichende Baugrunderkundungen, die unterlassene Überprüfung der Kennwerte trotz mehrerer Abbrüche während der Erdarbeiten und das Ausbleiben einer Korrektur durch die am Projekt Beteiligten. Die Instabilität könnte sich erst nach vier Betriebsjahren gezeigt haben, wegen des veränderlichen Verhaltens der Tone oder wegen Wasserzutritts.',
                'sources' => ['bea-rapport-2022'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La section Baudrecourt - Eckwersheim a été fermée et les trains de Strasbourg détournés par la ligne classique (32 minutes de plus). Le talus a été reconstitué en avril et mai 2020 avec de meilleurs matériaux et une bêche d\'ancrage, un système de surveillance par capteurs d\'inclinaison a été posé en septembre sur quatre déblais, et les circulations ont repris le 27 septembre 2020 avec une limitation temporaire à 80 km/h sur la zone surveillée.',
                'en' => 'The Baudrecourt - Eckwersheim section was closed and Strasbourg trains were diverted onto the classic line (32 minutes longer). The slope was rebuilt in April and May 2020 with better materials and an anchor key, a tilt-sensor monitoring system was installed in September on four cuttings, and trains resumed on 27 September 2020 with a temporary 80 km/h limit over the monitored zone.',
                'de' => 'Der Abschnitt Baudrecourt - Eckwersheim wurde gesperrt, die Züge von und nach Straßburg wurden über die klassische Strecke umgeleitet (32 Minuten länger). Die Böschung wurde im April und Mai 2020 mit besserem Material und einem Fußsporn wiederhergestellt, im September wurde an vier Einschnitten ein Überwachungssystem mit Neigungssensoren eingebaut, und der Betrieb lief am 27. September 2020 mit einer vorübergehenden Geschwindigkeitsbeschränkung auf 80 km/h im überwachten Bereich wieder an.',
                'sources' => ['bea-rapport-2022'],
            ],
            [
                'fr' => 'Le BEA-TT, dont le rapport date de janvier 2022, formule quatre recommandations et trois invitations : mise en sécurité des talus à risque de la LGV, prescriptions des campagnes géotechniques, justification de la stabilité des ouvrages en terre, surveillance des déblais et des ouvrages de drainage, robustesse de l\'alerte radio.',
                'en' => 'The BEA-TT, whose report dates from January 2022, makes four recommendations and three invitations: making the at-risk slopes on the high-speed line safe, requirements for geotechnical surveys, justification of the stability of earthworks, monitoring of cuttings and drainage structures, and the robustness of the radio alert.',
                'de' => 'Die BEA-TT, deren Bericht vom Januar 2022 stammt, spricht vier Empfehlungen und drei Aufforderungen aus: Sicherung der gefährdeten Böschungen der Schnellfahrstrecke, Vorgaben für geotechnische Erkundungen, Standsicherheitsnachweise für Erdbauwerke, Überwachung von Einschnitten und Entwässerungsanlagen sowie Robustheit des Funkalarms.',
                'sources' => ['bea-rapport-2022'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu : l\'accident porte le nom d\'Ingenheim, commune où la rame s\'est immobilisée, alors que le choc initial s\'est produit au droit de Duntzenheim, 1 635 m plus tôt. La fiche garde Ingenheim.',
                'en' => 'The location: the accident bears the name of Ingenheim, the commune where the train came to rest, whereas the initial impact took place near Duntzenheim, 1,635 m earlier. The record keeps Ingenheim.',
                'de' => 'Der Ort: Der Unfall trägt den Namen von Ingenheim, der Gemeinde, in der der Zug zum Stehen kam, während der erste Aufprall 1635 m früher bei Duntzenheim erfolgte. Der Datensatz behält Ingenheim bei.',
                'sources' => ['bea-rapport-2022', 'wp-fr'],
            ],
            [
                'fr' => 'La date de l\'encadré de Wikipédia est mal précisée (année seule) et son encadré donne 22 blessés, alors que son texte en compte 21 légers et un grave ; le BEA-TT confirme le 5 mars 2020 et 22 blessés au total.',
                'en' => 'The date in the Wikipedia infobox is imprecise (year only) and its infobox gives 22 injured, whereas its text counts 21 slight and one serious; the BEA-TT confirms 5 March 2020 and 22 injured in all.',
                'de' => 'Das Datum im Wikipedia-Infokasten ist ungenau (nur das Jahr), und der Infokasten nennt 22 Verletzte, während der Text 21 Leicht- und einen Schwerverletzten zählt; die BEA-TT bestätigt den 5. März 2020 und insgesamt 22 Verletzte.',
                'sources' => ['bea-rapport-2022', 'wp-fr'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_at_time',
            'value' => '07:32',
            'sources' => ['bea-rapport-2022', 'wp-fr'],
            'fr' => 'Le BEA-TT et Wikipédia placent le choc à 7 h 32.',
            'en' => 'The BEA-TT and Wikipedia place the impact at 7.32 am.',
            'de' => 'Die BEA-TT und Wikipedia nennen den Aufprall um 7.32 Uhr.',
        ],
    ],
];
