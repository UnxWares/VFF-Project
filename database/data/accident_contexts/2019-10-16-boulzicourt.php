<?php

// Contexte sourcé de l'accident de Saint-Pierre-sur-Vence (Boulzicourt) du 16 octobre 2019 (recherche du 21/11/2026).
return [
    'slug' => '2019-10-16-boulzicourt',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-rapport-2021' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête technique sur la collision entre un train express régional et un ensemble routier surbaissé survenue le 16 octobre 2019 sur le passage à niveau n° 70 à Boulzicourt (08)',
            'publisher' => 'Bureau d\'enquêtes sur les accidents de transport terrestre (BEA-TT)',
            'author' => null,
            'published' => '2021-12',
            'locator' => 'Synthèse, §1, §2.2',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2019-14.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport final (BEATT-2019-14) : synthèse, circonstances, bilan, description du passage à niveau, cause directe, facteurs, recommandations.',
        ],
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident de Saint-Pierre-sur-Vence',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_de_Saint-Pierre-sur-Vence&oldid=239609608',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé (presse nationale, rapport interne de la SNCF). Situe le passage à niveau à cheval sur Saint-Pierre-sur-Vence et Boulzicourt, compte 62 voyageurs et 11 blessés ; sections sur le BEA-TT incomplètes.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mercredi 16 octobre 2019, à 16 h 12, le TER n° 840 808 de Charleville-Mézières vers Champagne-Ardenne TGV, avec 63 personnes à bord, percute un convoi routier exceptionnel (tracteur et semi-remorque surbaissée chargée d\'une machine agricole, accompagné d\'un véhicule pilote) sur le passage à niveau n° 70, ligne de Soissons à Givet, dans les Ardennes.',
                'en' => 'On Wednesday 16 October 2019 at 4.12 pm, TER no. 840 808 from Charleville-Mézières to Champagne-Ardenne TGV, with 63 people on board, hit an abnormal road load (a tractor and a low-loader semi-trailer carrying agricultural machinery, escorted by a pilot vehicle) at level crossing no. 70 on the Soissons to Givet line, in the Ardennes.',
                'de' => 'Am Mittwoch, dem 16. Oktober 2019, um 16.12 Uhr prallte der TER Nr. 840 808 von Charleville-Mézières nach Champagne-Ardenne TGV mit 63 Personen an Bord am Bahnübergang Nr. 70 der Strecke Soissons-Givet in den Ardennes auf einen Schwertransport (Zugmaschine und tiefergelegter Auflieger mit einer Landmaschine, begleitet von einem Begleitfahrzeug).',
                'sources' => ['bea-rapport-2021', 'wp-fr'],
            ],
            [
                'fr' => 'Le passage à niveau, équipé de feux et sonneries et de deux demi-barrières, croise la route départementale 28A. Le BEA-TT le situe sur le territoire de la commune de Boulzicourt, la commune de Saint-Pierre-sur-Vence se trouvant à quelques mètres à l\'ouest ; il est classé à franchissement difficile pour les véhicules à faible garde au sol.',
                'en' => 'The level crossing, equipped with lights, bells and two half-barriers, crosses the departmental road 28A. The BEA-TT places it in the commune of Boulzicourt, with the commune of Saint-Pierre-sur-Vence a few metres to the west; it is classed as difficult to cross for vehicles with low ground clearance.',
                'de' => 'Der Bahnübergang mit Lichtzeichen, Läutewerk und zwei Halbschranken kreuzt die Departementsstraße 28A. Die BEA-TT verortet ihn auf dem Gebiet der Gemeinde Boulzicourt, während Saint-Pierre-sur-Vence wenige Meter westlich liegt; er gilt als schwierig zu überqueren für Fahrzeuge mit geringer Bodenfreiheit.',
                'sources' => ['bea-rapport-2021'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le TER roulait à 120 km/h au moment du choc. Il a déraillé sans se renverser, restant à cheval sur sa voie en engageant le gabarit de la voie voisine ; la semi-remorque a été coupée en deux et le tracteur détruit. Blessé, le conducteur du train a pu, avec celui du premier train croiseur, éviter un sur-accident malgré la perte de la radio sol-train.',
                'en' => 'The TER was travelling at 120 km/h at the time of the impact. It derailed without overturning, remaining astride its track and fouling the gauge of the neighbouring track; the semi-trailer was cut in two and the tractor destroyed. Injured, the train driver was able, together with the driver of the first train to cross, to prevent a further accident despite the loss of the ground-to-train radio.',
                'de' => 'Der TER fuhr beim Aufprall mit 120 km/h. Er entgleiste, ohne umzukippen, und blieb rittlings auf seinem Gleis stehen, wobei er das Lichtraumprofil des Nachbargleises beeinträchtigte; der Auflieger wurde in zwei Teile gerissen, die Zugmaschine zerstört. Der verletzte Lokführer konnte zusammen mit dem Lokführer des ersten Gegenzuges trotz des Ausfalls des Zugfunks einen Folgeunfall verhindern.',
                'sources' => ['bea-rapport-2021'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Aucun mort. Le BEA-TT compte 12 blessés légers : le conducteur du train, dix voyageurs et le conducteur du convoi routier, qui se trouvait hors de son véhicule.',
                'en' => 'Nobody was killed. The BEA-TT counts 12 slightly injured: the train driver, ten passengers and the driver of the road convoy, who was outside his vehicle.',
                'de' => 'Es gab keine Toten. Die BEA-TT zählt 12 Leichtverletzte: den Lokführer, zehn Fahrgäste und den Fahrer des Straßenkonvois, der sich außerhalb seines Fahrzeugs befand.',
                'sources' => ['bea-rapport-2021'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause directe est l\'arrêt du convoi surbaissé sur le passage à niveau : son conducteur l\'avait stoppé pour descendre manœuvrer l\'hydraulique de la semi-remorque et augmenter la garde au sol. Le BEA-TT relève que le conducteur du convoi et celui du véhicule pilote n\'avaient ni préparé ni fait autoriser ni reconnu un itinéraire adapté, et que la garde au sol n\'avait pas été relevée au maximum en amont.',
                'en' => 'The direct cause is the low-loader convoy stopping on the level crossing: its driver had halted it to get out and operate the semi-trailer\'s hydraulics to raise the ground clearance. The BEA-TT notes that the convoy driver and the pilot vehicle driver had neither prepared, nor obtained authorisation for, nor reconnoitred a suitable route, and that the ground clearance had not been raised to the maximum before the crossing.',
                'de' => 'Unmittelbare Ursache ist das Anhalten des tiefergelegten Konvois auf dem Bahnübergang: Sein Fahrer hatte ihn gestoppt, um auszusteigen und die Hydraulik des Aufliegers zur Erhöhung der Bodenfreiheit zu bedienen. Die BEA-TT stellt fest, dass der Konvoifahrer und der Fahrer des Begleitfahrzeugs weder eine geeignete Route vorbereitet, genehmigen lassen noch erkundet hatten und dass die Bodenfreiheit vor dem Übergang nicht auf das Maximum angehoben worden war.',
                'sources' => ['bea-rapport-2021'],
            ],
            [
                'fr' => 'Le conducteur du convoi était de nationalité allemande, étranger à la langue et aux procédures françaises ; le BEA-TT juge l\'organisation et la préparation du transport déficientes et note des similitudes avec les accidents de Nangis (2015) et de Balbigny (2011).',
                'en' => 'The convoy driver was German, a stranger to the French language and procedures; the BEA-TT judges the organisation and preparation of the transport to have been deficient and notes similarities with the accidents at Nangis (2015) and Balbigny (2011).',
                'de' => 'Der Konvoifahrer war Deutscher und mit der französischen Sprache und den Verfahren nicht vertraut; die BEA-TT hält Organisation und Vorbereitung des Transports für mangelhaft und sieht Parallelen zu den Unfällen von Nangis (2015) und Balbigny (2011).',
                'sources' => ['bea-rapport-2021'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT adresse une recommandation à la délégation à la sécurité routière (outil cartographique et calculateur d\'itinéraires pour convois exceptionnels, signalant notamment les passages à niveau) et une à SNCF Voyageurs (système autonome d\'alerte automatique après un choc, pour les trains AGC). La circulation a été rétablie le 18 octobre à vitesse réduite et le 28 octobre à vitesse normale.',
                'en' => 'The BEA-TT addresses one recommendation to the road safety delegation (a mapping tool and route calculator for abnormal loads, flagging level crossings in particular) and one to SNCF Voyageurs (an autonomous automatic alert system after an impact, for AGC trains). Traffic resumed on 18 October at reduced speed and on 28 October at normal speed.',
                'de' => 'Die BEA-TT richtet eine Empfehlung an die Verkehrssicherheitsdelegation (Kartenwerkzeug und Routenrechner für Schwertransporte, die insbesondere Bahnübergänge kennzeichnen) und eine an SNCF Voyageurs (autonomes, automatisches Alarmsystem nach einem Aufprall für AGC-Triebzüge). Der Verkehr lief am 18. Oktober mit verminderter und am 28. Oktober mit normaler Geschwindigkeit wieder.',
                'sources' => ['bea-rapport-2021'],
            ],
            [
                'fr' => 'L\'accident, survenu sur un TER circulant avec un seul agent à bord, a déclenché un mouvement d\'exercice du droit de retrait de nombreux conducteurs et contrôleurs, qui a fortement perturbé le trafic plusieurs jours. Selon Wikipédia, le conseil de prud\'hommes a reconnu ce droit de retrait quatre ans plus tard.',
                'en' => 'The accident, on a TER running with a single member of staff on board, set off a wave of workers exercising their right to withdraw from dangerous work among many drivers and conductors, which badly disrupted traffic for several days. According to Wikipedia, the labour court recognised this right of withdrawal four years later.',
                'de' => 'Der Unfall, der einen TER mit nur einem Bediensteten an Bord betraf, löste unter vielen Lokführern und Zugbegleitern eine Welle der Berufung auf das Recht zur Arbeitsverweigerung bei Gefahr aus, die den Verkehr mehrere Tage lang stark störte. Laut Wikipedia erkannte das Arbeitsgericht dieses Recht vier Jahre später an.',
                'sources' => ['wp-fr'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu : la fiche (Wikipédia, Wikidata) retient Saint-Pierre-sur-Vence ; le BEA-TT intitule son rapport « Boulzicourt (08) » et situe le passage à niveau sur cette commune, Saint-Pierre-sur-Vence étant à quelques mètres à l\'ouest ; Wikipédia le dit à cheval sur les deux. La fiche est corrigée sur le code INSEE de Boulzicourt, commune retenue par le rapport officiel ; le nom de la fiche reste celui de Saint-Pierre-sur-Vence.',
                'en' => 'The location: the record (Wikipedia, Wikidata) gives Saint-Pierre-sur-Vence; the BEA-TT titles its report "Boulzicourt (08)" and places the level crossing in that commune, with Saint-Pierre-sur-Vence a few metres to the west; Wikipedia says it straddles both. The record is corrected to the INSEE code of Boulzicourt, the commune used in the official report; the record keeps the name Saint-Pierre-sur-Vence.',
                'de' => 'Der Ort: Der Datensatz (Wikipedia, Wikidata) nennt Saint-Pierre-sur-Vence; die BEA-TT betitelt ihren Bericht „Boulzicourt (08)“ und verortet den Bahnübergang in dieser Gemeinde, wobei Saint-Pierre-sur-Vence wenige Meter westlich liegt; Wikipedia sagt, er liege auf beiden. Der Datensatz wird auf den INSEE-Code von Boulzicourt korrigiert, der Gemeinde des amtlichen Berichts; der Name des Datensatzes bleibt Saint-Pierre-sur-Vence.',
                'sources' => ['bea-rapport-2021', 'wp-fr'],
            ],
            [
                'fr' => 'Le bilan et les vitesses diffèrent : 11 blessés et 62 voyageurs selon Wikipédia, 12 blessés légers et 63 personnes à bord selon le BEA-TT (qui compte le conducteur du convoi) ; 120 km/h au choc selon le BEA-TT, environ 136 km/h en approche et 118 km/h au choc selon Wikipédia (d\'après la SNCF). La fiche est corrigée sur le chiffre du BEA-TT.',
                'en' => 'Toll and speeds differ: 11 injured and 62 passengers according to Wikipedia, 12 slightly injured and 63 people on board according to the BEA-TT (which counts the convoy driver); 120 km/h at impact according to the BEA-TT, about 136 km/h on approach and 118 km/h at impact according to Wikipedia (citing SNCF). The record is corrected to the BEA-TT figure.',
                'de' => 'Bilanz und Geschwindigkeiten weichen ab: 11 Verletzte und 62 Fahrgäste laut Wikipedia, 12 Leichtverletzte und 63 Personen an Bord laut BEA-TT (die den Konvoifahrer mitzählt); 120 km/h beim Aufprall laut BEA-TT, etwa 136 km/h bei der Annäherung und 118 km/h beim Aufprall laut Wikipedia (nach Angaben der SNCF). Der Datensatz wird auf die Zahl der BEA-TT korrigiert.',
                'sources' => ['bea-rapport-2021', 'wp-fr'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 12,
            'sources' => ['bea-rapport-2021'],
            'fr' => 'Le BEA-TT compte 12 blessés légers, conducteur du convoi routier compris.',
            'en' => 'The BEA-TT counts 12 slightly injured, including the road convoy driver.',
            'de' => 'Die BEA-TT zählt 12 Leichtverletzte, den Fahrer des Straßenkonvois eingeschlossen.',
        ],
        [
            'field' => 'occurred_at_time',
            'value' => '16:12',
            'sources' => ['bea-rapport-2021'],
            'fr' => 'Le rapport du BEA-TT place le choc à 16 h 12.',
            'en' => 'The BEA-TT report places the impact at 4.12 pm.',
            'de' => 'Der BEA-TT-Bericht nennt den Aufprall um 16.12 Uhr.',
        ],
        [
            'field' => 'kind',
            'value' => 'level_crossing',
            'sources' => ['bea-rapport-2021'],
            'fr' => 'Le rapport décrit une collision avec un convoi routier arrêté sur un passage à niveau.',
            'en' => 'The report describes a collision with a road convoy stopped on a level crossing.',
            'de' => 'Der Bericht beschreibt einen Zusammenstoß mit einem auf einem Bahnübergang stehenden Straßenkonvoi.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '08076',
            'sources' => ['bea-rapport-2021', 'wp-fr'],
            'fr' => 'Le rapport du BEA-TT situe le passage à niveau n° 70 sur la commune de Boulzicourt (Wikipédia le dit à cheval sur Boulzicourt et Saint-Pierre-sur-Vence).',
            'en' => 'The BEA-TT report places level crossing no. 70 in the commune of Boulzicourt (Wikipedia says it straddles Boulzicourt and Saint-Pierre-sur-Vence).',
            'de' => 'Der BEA-TT-Bericht verortet den Bahnübergang Nr. 70 in der Gemeinde Boulzicourt (Wikipedia sagt, er liege auf Boulzicourt und Saint-Pierre-sur-Vence).',
        ],
    ],
];
