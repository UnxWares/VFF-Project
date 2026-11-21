<?php

// Contexte sourcé de l'accident de Nangis du 21 avril 2015 (recherche du 21/11/2026).
return [
    'slug' => '2015-04-21-nangis',
    'checked_on' => '2026-11-21',
    'sources' => [
        'bea-rapport-2016' => [
            'provider' => 'bea-tt',
            'kind' => 'report',
            'reliability' => 'official',
            'title' => 'Rapport d\'enquête technique sur la collision entre un train de voyageurs et un convoi de transport exceptionnel survenue le 21 avril 2015 à Nangis (77)',
            'publisher' => 'Bureau d\'enquêtes sur les accidents de transport terrestre (BEA-TT)',
            'author' => null,
            'published' => '2016-12',
            'locator' => 'Résumé, §1, §4, §6',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/rapport_beatt_2015_006.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Rapport final (BEATT-2015-006) : bilan, chronologie à la seconde, causes, mesures prises, trois recommandations.',
        ],
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire de Nangis',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_ferroviaire_de_Nangis&oldid=234904895',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article signalé comme ébauche et peu sourcé ; incohérent sur le nombre de blessés (37 dans le texte, 41 dans l\'encadré). Cite le BEA-TT, la presse et un article du Parisien d\'octobre 2017 sur le procès.',
        ],
        'sudrail-2017' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => 'Communiqué de la fédération SUD-Rail sur l\'accident de Nangis',
            'publisher' => 'SUD-Rail',
            'author' => null,
            'published' => '2017-10-18',
            'locator' => null,
            'url' => 'https://www.sudrail.fr/IMG/pdf/2017_10_18_communique_nangis_sc.pdf',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Communiqué syndical de 2 pages, PDF non lisible ici : indique que le tribunal correctionnel de Melun devait se prononcer sur les responsabilités. Source partisane, à consulter directement.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mardi 21 avril 2015, vers 8 h 40 (8 h 40 min 32 s selon la caméra de surveillance), l\'Intercités n° 1646 Belfort - Paris-Est, composé d\'une locomotive diesel CC 72000 et de sept voitures Corail, percute un convoi routier exceptionnel immobilisé sur le passage à niveau n° 41 de la RD 419, à Nangis (Seine-et-Marne), à côté de la gare. Le train n\'avait pas d\'arrêt prévu à Nangis et transportait environ 350 passagers.',
                'en' => 'On Tuesday 21 April 2015 at about 8.40 am (8.40 and 32 seconds by the surveillance camera), Intercités train no. 1646 from Belfort to Paris-Est, made up of a CC 72000 diesel locomotive and seven Corail coaches, hit an abnormal road load stuck on level crossing no. 41 on the RD 419 at Nangis (Seine-et-Marne), next to the station. The train had no scheduled stop at Nangis and carried about 350 passengers.',
                'de' => 'Am Dienstag, dem 21. April 2015, gegen 8.40 Uhr (8.40 Uhr und 32 Sekunden nach der Überwachungskamera) prallte der Intercités Nr. 1646 von Belfort nach Paris-Est, bestehend aus einer Diesellok CC 72000 und sieben Corail-Wagen, am Bahnübergang Nr. 41 der RD 419 in Nangis (Seine-et-Marne) neben dem Bahnhof auf einen dort festsitzenden Schwertransport. Der Zug hatte in Nangis keinen planmäßigen Halt und beförderte rund 350 Reisende.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le convoi, un tracteur routier tirant une semi-remorque surbaissée chargée de matériel agricole, s\'est engagé sur le passage à niveau au profil en dos d\'âne : le plateau a accroché le sol et le convoi s\'est bloqué environ cinq minutes avant le train. Le conducteur a tenté en vain de relever la garde au sol, puis a couru vers la gare pour donner l\'alerte ; les barrières se sont fermées 34 secondes avant le choc.',
                'en' => 'The convoy, a road tractor pulling a low-loader semi-trailer carrying farm machinery, entered the level crossing, which has a humpback profile. The trailer bed caught the ground and the convoy stuck about five minutes before the train arrived. The driver tried in vain to raise the ground clearance, then ran to the station to raise the alarm; the barriers came down 34 seconds before the impact.',
                'de' => 'Der Konvoi, ein Straßenzugmaschine mit tiefergelegtem Auflieger und landwirtschaftlichem Gerät, fuhr auf den Bahnübergang mit Buckelprofil. Die Ladefläche setzte auf, und der Konvoi blieb etwa fünf Minuten vor dem Zug stecken. Der Fahrer versuchte vergeblich, die Bodenfreiheit zu erhöhen, und lief dann zum Bahnhof, um Alarm zu schlagen; die Schranken schlossen sich 34 Sekunden vor dem Aufprall.',
                'sources' => ['bea-rapport-2016'],
            ],
            [
                'fr' => 'Le conducteur du train a déclenché le freinage d\'urgence à environ 250 m de l\'obstacle, quand il l\'a vu en sortie de courbe, et a heurté le convoi à 125 km/h. La semi-remorque a été coupée en deux ; la locomotive et les sept voitures ont déraillé en restant sur la plate-forme, la rame s\'immobilisant en bout de quai de la gare.',
                'en' => 'The train driver applied the emergency brake about 250 m from the obstacle, when he saw it coming out of a curve, and hit the convoy at 125 km/h. The semi-trailer was cut in two; the locomotive and the seven coaches derailed but stayed on the track bed, the train coming to rest at the end of the station platform.',
                'de' => 'Der Lokführer leitete etwa 250 m vor dem Hindernis eine Schnellbremsung ein, als er es am Kurvenausgang sah, und prallte mit 125 km/h auf den Konvoi. Der Auflieger wurde in zwei Teile gerissen; Lok und sieben Wagen entgleisten, blieben aber auf dem Bahnkörper, und der Zug kam am Ende des Bahnsteigs zum Stehen.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Aucun mort. Le BEA-TT compte trois blessés graves, dont le conducteur du train, et 40 blessés légers, soit 43 blessés. Le plan « nombreuses victimes » a été déclenché, les victimes ont été regroupées dans la gare, et 31 engins, 74 sapeurs-pompiers et une cinquantaine de gendarmes ont été mobilisés.',
                'en' => 'Nobody was killed. The BEA-TT counts three seriously injured, including the train driver, and 40 slightly injured, 43 in all. The mass-casualty plan was activated, victims were gathered in the station, and 31 vehicles, 74 firefighters and about fifty gendarmes were deployed.',
                'de' => 'Es gab keine Toten. Die BEA-TT zählt drei Schwer- und 40 Leichtverletzte, insgesamt also 43 Verletzte. Der Plan für viele Opfer wurde ausgelöst, die Opfer wurden im Bahnhof gesammelt, und 31 Fahrzeuge, 74 Feuerwehrleute und rund fünfzig Gendarmen waren im Einsatz.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le BEA-TT retient comme cause directe le blocage du convoi surbaissé sur le passage à niveau. Il cite plusieurs facteurs : un conducteur qui n\'a pas respecté les interdictions locales de circulation, l\'absence de signalisation du profil en dos d\'âne (posée depuis), un profil pouvant avoir été aggravé par des travaux de bourrage quelques mois avant, l\'absence d\'usage des téléphones du passage à niveau et une mauvaise maîtrise du relevage de la semi-remorque.',
                'en' => 'The BEA-TT gives the direct cause as the low-loader convoy getting stuck on the level crossing. It lists several factors: a driver who ignored local traffic bans, the lack of signs warning of the humpback profile (installed since), a profile that may have been made worse by ballast tamping a few months earlier, the failure to use the crossing telephones, and poor command of the trailer\'s lifting system.',
                'de' => 'Als unmittelbare Ursache nennt die BEA-TT das Festsitzen des tiefergelegten Konvois auf dem Bahnübergang. Sie führt mehrere Faktoren an: einen Fahrer, der örtliche Fahrverbote missachtete, das Fehlen einer Beschilderung des Buckelprofils (inzwischen angebracht), ein Profil, das durch Stopfarbeiten einige Monate zuvor verschärft worden sein könnte, die Nichtbenutzung der Telefone am Bahnübergang und eine mangelnde Beherrschung der Hubvorrichtung des Aufliegers.',
                'sources' => ['bea-rapport-2016'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Dans le mois suivant, le franchissement a été refait et des panneaux « dos d\'âne » avec la mention « véhicules surbaissés » ont été posés de chaque côté ; les coffrets des téléphones ont été remplacés. La circulation a repris le 25 avril à vitesse réduite et le 29 avril à vitesse normale. Le BEA-TT a adressé trois recommandations (information des transporteurs exceptionnels, usage des téléphones de passage à niveau pour alerter les agents de circulation, prise en main des nouveaux véhicules).',
                'en' => 'Within a month the crossing surface was rebuilt and humpback warning signs with the words "low vehicles take care" were put up on both sides; the telephone cabinets were replaced. Trains ran again on 25 April at reduced speed and on 29 April at normal speed. The BEA-TT made three recommendations (informing abnormal-load hauliers, using level-crossing telephones to alert traffic controllers, and mastering new vehicles).',
                'de' => 'Innerhalb eines Monats wurde die Überfahrt erneuert, und auf beiden Seiten wurden Warnschilder „Bodenwelle“ mit dem Zusatz „Tieflader Vorsicht“ aufgestellt; die Telefonkästen wurden ersetzt. Der Zugverkehr lief am 25. April mit verminderter und am 29. April mit normaler Geschwindigkeit wieder. Die BEA-TT sprach drei Empfehlungen aus (Information der Schwertransportunternehmen, Nutzung der Bahnübergangstelefone zur Alarmierung der Fahrdienstleiter, Einweisung in neue Fahrzeuge).',
                'sources' => ['bea-rapport-2016'],
            ],
            [
                'fr' => 'Un procès est mentionné par l\'article de Wikipédia, qui renvoie à un article de presse d\'octobre 2017 ; l\'issue judiciaire n\'a pas été trouvée dans les documents lus.',
                'en' => 'A trial is mentioned in the Wikipedia article, which refers to a press article of October 2017; the legal outcome was not found in the documents read.',
                'de' => 'Ein Prozess wird im Wikipedia-Artikel erwähnt, der auf einen Presseartikel vom Oktober 2017 verweist; der Ausgang des Verfahrens wurde in den gelesenen Dokumenten nicht gefunden.',
                'sources' => ['wp-fr'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère : 41 dans l\'encadré de Wikipédia, 37 dans le texte du même article, 43 selon le BEA-TT (3 graves et 40 légers). La fiche est corrigée sur le chiffre du BEA-TT, source officielle. La nature est elle aussi précisée : collision à un passage à niveau.',
                'en' => 'The number of injured differs: 41 in the Wikipedia infobox, 37 in the text of the same article, and 43 according to the BEA-TT (3 serious and 40 slight). The record is corrected to the BEA-TT figure, the official source. The type is also made more precise: a collision at a level crossing.',
                'de' => 'Die Zahl der Verletzten weicht ab: 41 im Infokasten von Wikipedia, 37 im Text desselben Artikels und 43 laut BEA-TT (3 schwer, 40 leicht). Der Datensatz wird auf die Zahl der BEA-TT als amtliche Quelle korrigiert. Auch die Art wird präzisiert: Zusammenstoß an einem Bahnübergang.',
                'sources' => ['bea-rapport-2016', 'wp-fr'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 43,
            'sources' => ['bea-rapport-2016'],
            'fr' => 'Le BEA-TT dénombre trois blessés graves et 40 blessés légers.',
            'en' => 'The BEA-TT counts three seriously and 40 slightly injured.',
            'de' => 'Die BEA-TT zählt drei Schwer- und 40 Leichtverletzte.',
        ],
        [
            'field' => 'occurred_at_time',
            'value' => '08:40',
            'sources' => ['bea-rapport-2016'],
            'fr' => 'Le rapport du BEA-TT place le choc vers 8 h 40 (8 h 40 min 32 s).',
            'en' => 'The BEA-TT report places the impact at about 8.40 am (8.40 and 32 seconds).',
            'de' => 'Der BEA-TT-Bericht nennt den Aufprall gegen 8.40 Uhr (8.40 Uhr und 32 Sekunden).',
        ],
        [
            'field' => 'kind',
            'value' => 'level_crossing',
            'sources' => ['bea-rapport-2016'],
            'fr' => 'Le rapport décrit une collision avec un convoi routier bloqué sur un passage à niveau.',
            'en' => 'The report describes a collision with a road convoy stuck on a level crossing.',
            'de' => 'Der Bericht beschreibt einen Zusammenstoß mit einem auf einem Bahnübergang festsitzenden Straßenkonvoi.',
        ],
    ],
];
