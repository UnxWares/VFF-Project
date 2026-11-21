<?php

// Contexte sourcé de l'accident de Denguin du 17 juillet 2014 (recherche du 21/11/2026).
return [
    'slug' => '2014-07-17-denguin',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-rapport-2016' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête technique sur la collision par rattrapage de deux trains de voyageurs survenue le 17 juillet 2014 à Denguin (64)',
            'publisher' => 'Bureau d\'enquêtes sur les accidents de transport terrestre (BEA-TT)',
            'author' => null,
            'published' => '2016-01',
            'locator' => 'Résumé, §1.2, §2, §4, §6',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/Rapport_BEATT_2014-010.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport final (BEATT-2014-010) : circulations, bilan, causes techniques (guérite du signal S 23, rongeurs), maintenance, deux recommandations à SNCF Réseau.',
        ],
        'presse-2026-denguin' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Collision entre deux trains à Denguin : le procès qui devait s\'ouvrir ce lundi renvoyé à l\'automne',
            'publisher' => 'La République des Pyrénées (page relayée par Ground News)',
            'author' => null,
            'published' => '2026-03-05',
            'locator' => 'Titre et résumé',
            'url' => 'https://ground.news/article/collision-between-two-trains-in-denguin-the-trial-that-was-to-open-this-monday-sent-back-to-autumn',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page de relais : annonce le renvoi, pour manque de moyens, du procès prévu le lundi 9 mars (deux semaines). Date de la nouvelle audience non indiquée, prévenus non précisés ; article d\'origine non lu.',
        ],
        'presse-2017-denguin' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Accident de Denguin : la SNCF mise en examen trois ans après',
            'publisher' => 'Médias24 (dépêche d\'agence)',
            'author' => null,
            'published' => '2017',
            'locator' => null,
            'url' => 'https://medias24.com/agence-presse/accident-de-denguin-la-sncf-mise-en-examen-trois-ans-apres/',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche repérée par recherche, non lue (accès refusé) : mise en examen de la SNCF pour blessures involontaires trois ans après les faits.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 17 juillet 2014, vers 17 h 30, sur la commune de Denguin (Pyrénées-Atlantiques), un TER à destination de Dax en rattrape un TGV et le heurte à 95 km/h, sur la ligne de Tarbes à Dax entre Pau et Artix, près du passage à niveau n° 256 (PK 229,546).',
                'en' => 'On 17 July 2014 at about 5.30 pm, in the commune of Denguin (Pyrénées-Atlantiques), a TER regional train bound for Dax caught up with a TGV and hit it at 95 km/h, on the Tarbes to Dax line between Pau and Artix, close to level crossing no. 256 (km 229.546).',
                'de' => 'Am 17. Juli 2014 gegen 17.30 Uhr fuhr in der Gemeinde Denguin (Pyrénées-Atlantiques) ein Regionalzug (TER) nach Dax auf einen TGV auf und prallte mit 95 km/h auf ihn, auf der Strecke Tarbes-Dax zwischen Pau und Artix, nahe dem Bahnübergang Nr. 256 (km 229,546).',
                'sources' => ['bea-rapport-2016'],
            ],
            [
                'fr' => 'La ligne est à double voie, électrifiée en 1 500 V continu ; le TGV n° 8585 transportait 178 passagers et le TER n° 867285 en avait 50.',
                'en' => 'The line is double track, electrified at 1,500 V DC. TGV no. 8585 carried 178 passengers and TER no. 867285 carried 50.',
                'de' => 'Die Strecke ist zweigleisig und mit 1500 V Gleichstrom elektrifiziert. Im TGV Nr. 8585 saßen 178 Fahrgäste, im TER Nr. 867285 waren es 50.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le signal S 23 (PK 225) était tombé en dérangement et affichait « voie occupée » : le TGV s\'est arrêté devant lui, puis a été autorisé à pénétrer dans le canton occupé en marche à vue, à 30 km/h. Le TER s\'est arrêté à son tour devant le même signal. Environ une minute plus tard, le signal est passé au vert alors que le TGV se trouvait encore dans le canton.',
                'en' => 'Signal S 23 (km 225) had developed a fault and showed "line occupied". The TGV stopped in front of it and was then authorised to enter the occupied block at walking pace, at 30 km/h. The TER also stopped at the same signal. About a minute later the signal turned green while the TGV was still in the block.',
                'de' => 'Das Signal S 23 (km 225) war gestört und zeigte „Strecke besetzt“. Der TGV hielt davor und durfte dann auf Sicht mit höchstens 30 km/h in den besetzten Abschnitt einfahren. Der TER hielt am selben Signal. Etwa eine Minute später sprang das Signal auf Grün, obwohl sich der TGV noch im Abschnitt befand.',
                'sources' => ['bea-rapport-2016'],
            ],
            [
                'fr' => 'Le conducteur du TER a franchi le signal au vert et accéléré jusqu\'à 128 km/h. Il a aperçu la queue du TGV à la sortie d\'une courbe et déclenché le freinage d\'urgence, sans pouvoir éviter le choc, qui a eu lieu à 95 km/h contre un TGV roulant à 28 km/h.',
                'en' => 'The TER driver passed the signal at green and accelerated to 128 km/h. He saw the tail of the TGV as he came out of a curve and applied the emergency brake but could not avoid the collision, which occurred at 95 km/h against a TGV moving at 28 km/h.',
                'de' => 'Der Triebfahrzeugführer des TER passierte das grüne Signal und beschleunigte auf 128 km/h. Am Ausgang einer Kurve sah er das Zugende des TGV und leitete eine Schnellbremsung ein, konnte den Zusammenstoß aber nicht vermeiden: Er erfolgte mit 95 km/h auf einen mit 28 km/h fahrenden TGV.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Aucun mort. Le bilan de la gendarmerie fait état de 35 blessés : 2 soignés sur place, 33 admis dans cinq établissements de la région, dont 5 hospitalisés. Le plan ORSEC « nombreuses victimes » a été déclenché et un poste médical avancé installé dans l\'école et la mairie de Denguin.',
                'en' => 'Nobody was killed. The gendarmerie counted 35 injured: 2 treated on site and 33 taken to five hospitals in the region, 5 of whom were kept in. The ORSEC mass-casualty plan was activated and an advanced medical post was set up in the school and town hall of Denguin.',
                'de' => 'Es gab keine Toten. Nach der Zählung der Gendarmerie wurden 35 Personen verletzt: 2 wurden vor Ort versorgt, 33 kamen in fünf Krankenhäuser der Region, 5 von ihnen wurden stationär aufgenommen. Der ORSEC-Plan für viele Opfer wurde ausgelöst und in Schule und Rathaus von Denguin ein medizinischer Vorposten eingerichtet.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT retient comme cause directe la présentation indue du feu vert par le signal S 23 alors que le canton aval était occupé, très probablement due à une réalimentation intempestive de la commande « voie libre » : des rongeurs entrés dans la guérite de signalisation avaient dénudé plusieurs fils électriques.',
                'en' => 'The BEA-TT identifies the direct cause as signal S 23 wrongly showing green while the block ahead was occupied, most probably due to an unintended re-energising of the "line clear" command: rodents that had got into the signalling cabinet had stripped several electric wires.',
                'de' => 'Als unmittelbare Ursache nennt die BEA-TT, dass das Signal S 23 zu Unrecht Grün zeigte, obwohl der folgende Abschnitt besetzt war, sehr wahrscheinlich durch eine unbeabsichtigte Wiedereinspeisung des Befehls „Fahrt frei“: Nager, die in den Signalschrank eingedrungen waren, hatten mehrere Kabel freigelegt.',
                'sources' => ['bea-rapport-2016'],
            ],
            [
                'fr' => 'Deux facteurs ont contribué : une guérite insuffisamment étanche aux rongeurs, au câblage peu visible et sans séparation des circuits ; une prise en compte insuffisante des dégâts de rongeurs lors de la maintenance. Dans la journée, deux dérangements sans conséquence pour la sécurité avaient déjà touché le même signal, sans lien avec la cause de l\'accident.',
                'en' => 'Two factors contributed: a cabinet that was not sufficiently rodent-proof, with poorly visible wiring and no separation of circuits, and insufficient attention to rodent damage during maintenance. Earlier that day, two faults without safety consequences had already affected the same signal; they were unrelated to the cause of the accident.',
                'de' => 'Zwei Faktoren trugen dazu bei: ein nicht ausreichend nagetiersicherer Schrank mit schlecht einsehbarer Verdrahtung ohne Trennung der Stromkreise und eine unzureichende Berücksichtigung von Nagetierschäden bei der Instandhaltung. Am selben Tag hatten bereits zwei sicherheitsunkritische Störungen das Signal betroffen; sie standen in keinem Zusammenhang mit der Unfallursache.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a ouvert son enquête le 18 juillet 2014 et publié son rapport en janvier 2016. Il adresse deux recommandations à SNCF Réseau : revoir la conception des guérites de signalisation et renforcer la prévention du risque d\'intrusion de rongeurs lors de la maintenance.',
                'en' => 'The BEA-TT opened its investigation on 18 July 2014 and published its report in January 2016. It made two recommendations to SNCF Réseau: to review the design of signalling cabinets and to strengthen the prevention of rodent intrusion during maintenance.',
                'de' => 'Die BEA-TT leitete ihre Untersuchung am 18. Juli 2014 ein und veröffentlichte den Bericht im Januar 2016. Sie richtete zwei Empfehlungen an SNCF Réseau: die Bauweise der Signalschränke zu überprüfen und die Vorbeugung gegen das Eindringen von Nagern bei der Instandhaltung zu verstärken.',
                'sources' => ['bea-rapport-2016'],
            ],
            [
                'fr' => 'Un procès devant la justice pénale de Pau devait s\'ouvrir le lundi 9 mars 2026 pour deux semaines ; selon un article du 5 mars 2026, il a été renvoyé à l\'automne pour manque de moyens. Le résultat n\'est pas connu à la date de cette recherche.',
                'en' => 'A criminal trial in Pau was due to open on Monday 9 March 2026 for two weeks; according to an article of 5 March 2026 it was postponed to the autumn for lack of resources. The outcome was not known when this research was done.',
                'de' => 'Ein Strafprozess in Pau sollte am Montag, dem 9. März 2026, für zwei Wochen beginnen; laut einem Artikel vom 5. März 2026 wurde er mangels Mitteln auf den Herbst verschoben. Das Ergebnis war bei dieser Recherche nicht bekannt.',
                'sources' => ['presse-2026-denguin'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_at_time',
            'value' => '17:30',
            'sources' => ['bea-rapport-2016'],
            'fr' => 'Le rapport du BEA-TT place la collision vers 17 h 30.',
            'en' => 'The BEA-TT report places the collision at about 5.30 pm.',
            'de' => 'Der BEA-TT-Bericht nennt als Zeitpunkt der Kollision etwa 17.30 Uhr.',
        ],
    ],
];
