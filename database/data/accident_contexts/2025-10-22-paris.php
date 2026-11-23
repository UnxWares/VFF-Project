<?php

// Contexte sourcé de du contact entre deux trains en gare de Paris-Est du 22 octobre 2025 (recherche du 23/11/2026).
return [
    'slug' => '2025-10-22-paris',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2025-10-22-paris' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Contact entre deux trains de SNCF V en gare de Paris-Est (75)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'fiche de présentation',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/beatt_2025_08-paris-est_note-etape.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Note d\'étape de l\'enquête : localisation, faits d\'après les premiers éléments, ouverture de l\'enquête.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'L\'événement s\'est produit en gare de Paris-Est, sur la voie 2 bis, banalisée, au niveau d\'un appareil de voie donnant accès aux voies à quai n° 27 et 22. Les circulations y sont commandées par le poste d\'aiguillage de Château-Landon (PRCI 23). La section est équipée du block automatique lumineux, du GSM-R et du KVB. Le plan de voie de la zone avait été modifié en 1999.',
                'en' => 'The event took place at Paris-Est station, on track 2 bis, which can be used in both directions, at a set of points giving access to platform tracks 27 and 22. Train movements there are controlled from the Château-Landon signal box (PRCI 23). The section has automatic colour-light block, GSM-R and KVB. The track layout of the area had been altered in 1999.',
                'de' => 'Das Ereignis fand im Bahnhof Paris-Est auf dem in beiden Richtungen befahrbaren Gleis 2 bis an einer Weiche statt, die zu den Bahnsteiggleisen 27 und 22 führt. Die Zugfahrten werden dort vom Stellwerk Château-Landon (PRCI 23) gesteuert. Der Abschnitt ist mit selbsttätigem Lichtblock, GSM-R und KVB ausgerüstet. Der Gleisplan der Zone war 1999 geändert worden.',
                'sources' => ['bea-tt-2025-10-22-paris'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 7 h, un TGV inOui sans voyageurs, arrêté devant le signal fermé C98 en attendant la voie 27, et un train Transilien avec voyageurs, dirigé vers la voie 22, se sont rapprochés jusqu\'à entrer en contact et abîmer une partie de leurs parois. Le TGV, long de 400 m, dépassait de l\'appareil de voie : le signal C98 n\'est qu\'à 413 m du garage franc, mais le circuit de voie de la zone le donnait à tort pour libre. Le Transilien, qui roulait à 22 km/h, a ralenti à 9 km/h et touché l\'arrière du TGV avant de s\'immobiliser. Il a ensuite rejoint la voie 22 à vitesse réduite.',
                'en' => 'At about 7 am an empty TGV inOui, stopped at the closed signal C98 while waiting for track 27, and a Transilien train carrying passengers, routed to track 22, came close enough to touch, damaging part of their sides. The 400 m long TGV stuck out past the points: signal C98 is only 413 m from the fouling point, yet the track circuit of the area wrongly showed it as clear. The Transilien, running at 22 km/h, slowed to 9 km/h and touched the rear of the TGV before stopping. It then went on to track 22 at reduced speed.',
                'de' => 'Gegen 7 Uhr kamen ein leerer TGV inOui, der vor dem Halt zeigenden Signal C98 auf Gleis 27 wartete, und ein Transilien mit Fahrgästen, der auf Gleis 22 geleitet wurde, einander so nahe, dass sie sich berührten und Teile ihrer Seitenwände beschädigten. Der 400 m lange TGV ragte über die Weiche hinaus: Das Signal C98 steht nur 413 m vor dem Grenzzeichen, doch der Gleisstromkreis der Zone zeigte fälschlich frei an. Der mit 22 km/h fahrende Transilien bremste auf 9 km/h und berührte das Heck des TGV, bevor er zum Stehen kam. Danach fuhr er mit verminderter Geschwindigkeit weiter zu Gleis 22.',
                'sources' => ['bea-tt-2025-10-22-paris'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Aucun voyageur n\'a été blessé et le conducteur du TGV n\'a ressenti aucune secousse anormale. Le BEA-TT juge le cas grave et particulièrement rare sur les chemins de fer français.',
                'en' => 'No passenger was injured and the TGV driver felt no unusual jolt. The BEA-TT calls the case serious and particularly rare on French railways.',
                'de' => 'Kein Fahrgast wurde verletzt, und der TGV-Fahrer spürte keinen ungewöhnlichen Ruck. Die BEA-TT bezeichnet den Fall als schwerwiegend und auf den französischen Eisenbahnen besonders selten.',
                'sources' => ['bea-tt-2025-10-22-paris'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon les premiers éléments recueillis, il s\'agit d\'un incident d\'exploitation consécutif à un défaut de signalisation, a priori un défaut de paramétrage d\'une installation. Les derniers travaux importants de la zone remontaient à avant l\'an 2000 et le BEA-TT juge possible, voire probable, que des situations analogues sans conséquence se soient déjà produites.',
                'en' => 'According to the first evidence gathered, this was an operating incident following a signalling fault, apparently a configuration fault in an installation. The last major works in the area dated from before 2000 and the BEA-TT considers it possible, even likely, that similar situations without consequences had already occurred.',
                'de' => 'Nach den ersten Erkenntnissen handelt es sich um ein betriebliches Ereignis infolge eines Signalisierungsfehlers, offenbar eines Parametrierfehlers einer Anlage. Die letzten größeren Arbeiten in der Zone lagen vor dem Jahr 2000, und die BEA-TT hält es für möglich, ja wahrscheinlich, dass sich ähnliche folgenlose Situationen schon ereignet hatten.',
                'sources' => ['bea-tt-2025-10-22-paris'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a ouvert une enquête technique pour tirer tous les enseignements de cet événement rarissime : identification et notification de l\'événement, cause du côté voie et signalisation, vérifications avant mise en service, gestion des précurseurs et visibilité de la signalisation depuis les cabines de conduite centrale des TGV. Il en a publié une note d\'étape.',
                'en' => 'The BEA-TT opened a technical investigation to draw every lesson from this very rare event: how it was identified and notified, its cause on the track and signalling side, checks before entry into service, handling of precursors, and the visibility of the signals from the centre cabs of TGVs. It has published an interim note.',
                'de' => 'Die BEA-TT hat eine technische Untersuchung eröffnet, um aus diesem äußerst seltenen Ereignis alle Lehren zu ziehen: Erkennung und Meldung des Ereignisses, Ursache auf der Gleis- und Signalseite, Prüfungen vor der Inbetriebnahme, Umgang mit Vorläufern und Sichtbarkeit der Signale aus den mittigen Führerständen der TGV. Sie hat dazu eine Zwischennotiz veröffentlicht.',
                'sources' => ['bea-tt-2025-10-22-paris'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2025-10-22-paris'],
            'fr' => 'Le BEA-TT précise qu\'aucun passager n\'a été blessé.',
            'en' => 'The BEA-TT states that no passenger was injured.',
            'de' => 'Die BEA-TT gibt an, dass kein Fahrgast verletzt wurde.',
        ],
    ],
];
