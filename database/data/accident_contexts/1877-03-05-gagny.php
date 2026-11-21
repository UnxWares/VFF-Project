<?php

// Contexte sourcé de la collision du train-poste n° 37 avec des wagons de marchandises en gare de Gagny, en mars 1877 (recherche du 21/11/2026).
return [
    'slug' => '1877-03-05-gagny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1877, 5 mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1877',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (4 morts et 16 blessés) ; donne le jugement du tribunal correctionnel de la Seine.',
        ],
        'vdr-gagny' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1877, La catastrophe de Gagny (93)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1877-la-catastrophe-de-gagny/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit le Courrier de Rennes du 10 mars 1877 et une liste de blessés.',
        ],
        'courrier-rennes-1877-03-10' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Courrier de Rennes du 10 mars 1877',
            'publisher' => 'Courrier de Rennes',
            'author' => null,
            'published' => '1877-03-10',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k83566865/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'la-presse-1877-11-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 22 novembre 1877',
            'publisher' => 'La Presse',
            'author' => null,
            'published' => '1877-11-22',
            'locator' => 'p. 4',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5424362/f4.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Compte rendu du jugement, cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le train-poste n° 37 de la Compagnie de l\'Est avait quitté Paris à 19 h 50. En gare de Gagny, à 14 kilomètres de Paris, un train de marchandises manœuvrait à reculons pendant qu\'on le coupait ; à la suite d\'une erreur d\'aiguille, des wagons se sont trouvés sur la voie du train-poste.',
                'en' => 'The Compagnie de l\'Est mail train no. 37 had left Paris at 7.50 pm. At Gagny station, 14 kilometres from Paris, a goods train was reversing while being divided; because a set of points was wrongly set, some wagons ended up on the mail train\'s track.',
                'de' => 'Der Postzug Nr. 37 der Compagnie de l\'Est hatte Paris um 19.50 Uhr verlassen. Im Bahnhof Gagny, 14 Kilometer von Paris, rangierte ein Güterzug rückwärts, während er getrennt wurde; wegen einer falsch gestellten Weiche gerieten Wagen auf das Gleis des Postzugs.',
                'sources' => ['vdr-gagny', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 20 h 20, le train-poste a pris ces wagons en écharpe. Sa locomotive, défoncée, a déraillé et a été rejetée sur le côté, et les premières voitures se sont écrasées contre l\'obstacle.',
                'en' => 'At about 8.20 pm the mail train struck these wagons side-on. Its engine, smashed in, derailed and was thrown to one side, and the leading carriages were crushed against the obstruction.',
                'de' => 'Gegen 20.20 Uhr erfasste der Postzug diese Wagen seitlich. Seine eingedrückte Lokomotive entgleiste und wurde zur Seite geschleudert, und die vorderen Wagen wurden am Hindernis zerdrückt.',
                'sources' => ['vdr-gagny', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Dix-sept blessés ont été retirés des décombres ; les plus atteints ont été soignés chez un restaurateur du village, où deux sont morts dans la nuit et le lendemain. On a trouvé deux autres corps, dont celui d\'une femme découvert sous les débris l\'après-midi suivant. Le mécanicien et le chauffeur n\'ont pas été blessés.',
                'en' => 'Seventeen injured people were taken from the wreckage; the worst cases were cared for at a restaurant in the village, where two died during the night and the next day. Two more bodies were found, including that of a woman discovered under the debris the following afternoon. The driver and the fireman were unhurt.',
                'de' => 'Siebzehn Verletzte wurden aus den Trümmern geborgen; die schwersten Fälle wurden bei einem Gastwirt im Ort versorgt, wo zwei in der Nacht und am folgenden Tag starben. Zwei weitere Leichen wurden gefunden, darunter die einer Frau, die erst am nächsten Nachmittag unter den Trümmern entdeckt wurde. Lokführer und Heizer blieben unverletzt.',
                'sources' => ['vdr-gagny'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Huit mois plus tard, le tribunal correctionnel de la Seine a déclaré responsables l\'aiguilleur et un autre agent de la gare, condamnés à six mois et à quinze jours de prison.',
                'en' => 'Eight months later the Seine criminal court held the pointsman and another station employee responsible, sentencing them to six months and fifteen days in prison respectively.',
                'de' => 'Acht Monate später erklärte das Strafgericht des Departements Seine den Weichensteller und einen weiteren Bahnhofsbediensteten für verantwortlich und verurteilte sie zu sechs Monaten beziehungsweise fünfzehn Tagen Haft.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les blessés sont seize selon Wikipédia et dix-sept selon le Courrier de Rennes. Le journal fait partir le train de Paris à 19 h 50 ; Wikipédia parle d\'un express.',
                'en' => 'Wikipedia gives sixteen injured and the Courrier de Rennes seventeen. The newspaper has the train leaving Paris at 7.50 pm; Wikipedia calls it an express.',
                'de' => 'Wikipedia nennt sechzehn Verletzte, der Courrier de Rennes siebzehn. Laut der Zeitung fuhr der Zug um 19.50 Uhr in Paris ab; Wikipedia spricht von einem Schnellzug.',
                'sources' => ['wp-liste-1870', 'vdr-gagny'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['vdr-gagny', 'wp-liste-1870'],
            'fr' => 'Le train-poste a heurté des wagons de marchandises aiguillés par erreur sur sa voie, puis sa machine a déraillé : la première circonstance est une collision.',
            'en' => 'The mail train hit goods wagons wrongly switched onto its track, and its engine then derailed: the first event is a collision.',
            'de' => 'Der Postzug prallte auf irrtümlich auf sein Gleis geleitete Güterwagen, danach entgleiste seine Lokomotive: Das erste Ereignis ist ein Zusammenstoß.',
        ],
    ],
];
