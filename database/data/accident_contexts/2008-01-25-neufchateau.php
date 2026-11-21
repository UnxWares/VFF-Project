<?php

// Contexte sourcé de la collision au passage à niveau n° 82 de Neufchâteau du 25 janvier 2008 (recherche du 21/11/2026).
return [
    'slug' => '2008-01-25-neufchateau',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-tt-2008-002' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête technique sur la collision survenue entre un train et une voiture sur le PN 82 à Neufchâteau (88) le 25 janvier 2008',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => '2008-08-22',
            'locator' => 'affaire BEATT-2008-002',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/Rapport_BEATT_2008_002_cle729213.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport complet (23 pages) : déroulement, fonctionnement du passage à niveau, analyse. L\'enquête se limite aux aspects ferroviaires.',
        ],
        'bea-tt-page-neufchateau' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Neufchâteau (88), 25/01/2008 : collision entre une voiture et un train',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/neufchateau-88-25-01-2008-collision-entre-une-r89.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de présentation de l\'enquête : résumé et lien vers le rapport.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le passage à niveau n° 82 se trouve au point kilométrique 72,547, à 1,7 km au nord du bâtiment voyageurs de la gare de Neufchâteau, sur la route départementale 164. Il est équipé de deux demi-barrières à signalisation automatique. La ligne, à double voie et électrifiée, voit passer environ 75 trains par jour, surtout la nuit.',
                'en' => 'Level crossing no. 82 lies at kilometre point 72.547, 1.7 km north of the passenger building of Neufchâteau station, on the D 164 road. It has two half-barriers with automatic signalling. The line, double-track and electrified, carries about 75 trains a day, mostly at night.',
                'de' => 'Der Bahnübergang Nr. 82 liegt bei Kilometerpunkt 72,547, 1,7 km nördlich des Empfangsgebäudes des Bahnhofs Neufchâteau, an der Départementsstraße D 164. Er ist mit zwei Halbschranken und automatischer Signalisierung ausgestattet. Die zweigleisige, elektrifizierte Strecke wird täglich von etwa 75 Zügen befahren, überwiegend nachts.',
                'sources' => ['bea-tt-2008-002'],
            ],
            [
                'fr' => 'Une zone de brouillard dense couvrait le passage à niveau. Les trains y sont visibles 12 secondes avant leur passage sur la voie 1 et 20 secondes sur la voie 2.',
                'en' => 'Dense fog covered the level crossing. Trains are visible from it 12 seconds before they pass on track 1 and 20 seconds on track 2.',
                'de' => 'Dichter Nebel lag über dem Bahnübergang. Züge sind von dort 12 Sekunden vor der Durchfahrt auf Gleis 1 und 20 Sekunden auf Gleis 2 zu sehen.',
                'sources' => ['bea-tt-2008-002'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Dans la nuit du vendredi 25 janvier 2008, un conducteur au volant d\'une voiture volée était poursuivi par trois gendarmes dans Neufchâteau. Il a trouvé le passage à niveau fermé, a contourné la demi-barrière et s\'est immobilisé sur la voie 2 pour attendre le passage d\'un train de fret sur la voie 1.',
                'en' => 'On the night of Friday 25 January 2008, a driver in a stolen car was being chased through Neufchâteau by three gendarmes. He found the level crossing closed, drove round the half-barrier and stopped on track 2 to wait for a freight train passing on track 1.',
                'de' => 'In der Nacht zum Freitag, dem 25. Januar 2008, wurde ein Fahrer eines gestohlenen Wagens in Neufchâteau von drei Gendarmen verfolgt. Er fand den Bahnübergang geschlossen, umfuhr die Halbschranke und hielt auf Gleis 2, um einen Güterzug auf Gleis 1 vorbeifahren zu lassen.',
                'sources' => ['bea-tt-2008-002', 'bea-tt-page-neufchateau'],
            ],
            [
                'fr' => 'Les trois gendarmes ont arrêté leur voiture avant la barrière, puis sont allés vers le véhicule volé, un à droite et deux à gauche. Une vingtaine de secondes après le passage du premier train, le train de fret 49250, venant de Toul, est arrivé sur la voie 2 vers 1 h 46. Son conducteur a déclenché l\'arrêt d\'urgence et l\'alerte radio, mais le train a heurté la voiture et les gendarmes, puis s\'est arrêté à environ 600 mètres du passage à niveau.',
                'en' => 'The three gendarmes stopped their car before the barrier, then walked to the stolen vehicle, one to the right and two to the left. About twenty seconds after the first train had passed, freight train 49250, coming from Toul, arrived on track 2 at about 1.46 am. Its driver triggered the emergency brake and the radio alert, but the train struck the car and the gendarmes and stopped about 600 metres beyond the crossing.',
                'de' => 'Die drei Gendarmen hielten ihr Fahrzeug vor der Schranke an und gingen dann zum gestohlenen Wagen, einer auf die rechte, zwei auf die linke Seite. Etwa zwanzig Sekunden nach der Durchfahrt des ersten Zuges erreichte der aus Richtung Toul kommende Güterzug 49250 gegen 1.46 Uhr Gleis 2. Sein Lokführer löste die Schnellbremsung und den Funkalarm aus, doch der Zug erfasste das Auto und die Gendarmen und kam etwa 600 Meter hinter dem Übergang zum Stehen.',
                'sources' => ['bea-tt-2008-002'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les trois gendarmes et le conducteur de la voiture volée sont morts, sur le coup ou après leur transfert à l\'hôpital. Le rapport ne signale aucun blessé.',
                'en' => 'The three gendarmes and the driver of the stolen car died, either at the scene or after being taken to hospital. The report mentions no injured person.',
                'de' => 'Die drei Gendarmen und der Fahrer des gestohlenen Wagens kamen ums Leben, entweder an der Unfallstelle oder nach der Einlieferung ins Krankenhaus. Der Bericht nennt keine Verletzten.',
                'sources' => ['bea-tt-2008-002', 'bea-tt-page-neufchateau'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT juge l\'intrusion du véhicule sur le passage à niveau volontaire. Les installations du passage à niveau, de la signalisation et du matériel roulant ont fonctionné normalement ; le fonctionnement du passage à niveau a été contrôlé le jour même par la gendarmerie en présence d\'agents de la SNCF.',
                'en' => 'The BEA-TT considers the vehicle\'s intrusion onto the level crossing deliberate. The crossing, the signalling and the rolling stock worked normally; the crossing was checked the same day by the gendarmerie in the presence of SNCF staff.',
                'de' => 'Das BEA-TT hält das Einfahren des Fahrzeugs in den Bahnübergang für absichtlich. Übergang, Signalisierung und Fahrzeuge funktionierten normal; der Übergang wurde noch am selben Tag von der Gendarmerie im Beisein von SNCF-Mitarbeitern geprüft.',
                'quote' => ['text' => 'L\'intrusion du véhicule sur le PN apparaît être volontaire.', 'lang' => 'fr', 'source' => 'bea-tt-2008-002'],
                'sources' => ['bea-tt-2008-002'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le bureau d\'enquêtes a limité son enquête aux aspects ferroviaires, sans examiner l\'action des gendarmes en service, qui relevait de l\'enquête de la gendarmerie. Il n\'a formulé aucune recommandation.',
                'en' => 'The investigation bureau limited its inquiry to the railway aspects, without examining the actions of the gendarmes on duty, which were covered by the gendarmerie\'s own inquiry. It made no recommendation.',
                'de' => 'Die Untersuchungsstelle beschränkte ihre Untersuchung auf die Bahnaspekte und prüfte das Handeln der diensthabenden Gendarmen nicht, das Gegenstand der Ermittlungen der Gendarmerie war. Sie sprach keine Empfehlung aus.',
                'sources' => ['bea-tt-2008-002', 'bea-tt-page-neufchateau'],
            ],
        ],
    ],
    'corrections' => [],
];
