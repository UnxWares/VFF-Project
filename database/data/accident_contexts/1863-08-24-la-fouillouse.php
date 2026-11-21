<?php

// Contexte sourcé de la collision de l'express de Paris contre le train d'Andrézieux en gare de La Fouillouse, en août 1863 (recherche du 21/11/2026).
return [
    'slug' => '1863-08-24-la-fouillouse',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1863, 24 août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1863',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (4 morts et une dizaine de blessés) ; donne le jugement du 7 novembre 1863 et l\'appel.',
        ],
        'vdr-la-fouillouse' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1863, Accident de la Fouillouse (42)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1863-accident-de-la-fouillouse-42/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Petit Journal du 27 août 1863, qui reprend le Mémorial de la Loire.',
        ],
        'petit-journal-1863-08-27' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 27 août 1863',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1863-08-27',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5883214/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'petit-journal-1863-11-11' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 11 novembre 1863',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1863-11-11',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k588394p/f3.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Compte rendu du jugement du tribunal correctionnel de Saint-Étienne, cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'L\'express de Paris, qui passait à Saint-Étienne à 17 h 47 sur la ligne du Bourbonnais, était annoncé avec un retard important. On a quand même fait partir devant lui le train d\'Andrézieux, qui le suivait d\'habitude à quelques minutes. Ce train s\'est arrêté en gare de La Fouillouse pour prendre de l\'eau.',
                'en' => 'The Paris express, due through Saint-Étienne at 5.47 pm on the Bourbonnais line, was reported to be running well behind time. The Andrézieux train, which normally followed it a few minutes later, was nevertheless sent off ahead of it. This train stopped at La Fouillouse station to take on water.',
                'de' => 'Der Schnellzug nach Paris, der Saint-Étienne auf der Bourbonnais-Strecke um 17.47 Uhr passierte, war mit erheblicher Verspätung gemeldet. Trotzdem ließ man den Zug nach Andrézieux, der ihm gewöhnlich wenige Minuten später folgte, vor ihm abfahren. Dieser Zug hielt im Bahnhof La Fouillouse, um Wasser zu nehmen.',
                'sources' => ['vdr-la-fouillouse', 'wp-liste-1860'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Il lui restait une minute d\'arrêt quand l\'express est arrivé à pleine vitesse ; il avait regagné quatre minutes depuis Châteaucreux. Un employé de la voie a ouvert les portières et une partie des voyageurs a pu sauter. L\'express a broyé les voitures de queue sans tenir compte du disque, et la locomotive, détachée par le choc, a continué seule jusqu\'à Saint-Galmier.',
                'en' => 'It still had a minute to wait when the express arrived at full speed; it had made up four minutes since Châteaucreux. A track worker opened the doors and some passengers managed to jump out. The express crushed the rear carriages without heeding the disc signal, and its engine, torn loose by the impact, ran on alone as far as Saint-Galmier.',
                'de' => 'Er hatte noch eine Minute Aufenthalt, als der Schnellzug mit voller Geschwindigkeit herankam; seit Châteaucreux hatte er vier Minuten aufgeholt. Ein Bahnarbeiter öffnete die Türen, und ein Teil der Reisenden konnte abspringen. Der Schnellzug zermalmte ohne Rücksicht auf das Scheibensignal die letzten Wagen, und seine durch den Stoß abgerissene Lokomotive rollte allein bis Saint-Galmier weiter.',
                'sources' => ['vdr-la-fouillouse'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Quatre personnes ont été tuées. Les blessés n\'avaient pas été comptés le lendemain ; on parlait de douze ou quinze, Wikipédia en retient une dizaine. Des médecins présents dans le train et ceux de La Fouillouse ont donné les premiers soins, et un wagon spécial a ramené des blessés à Saint-Étienne dans la nuit.',
                'en' => 'Four people were killed. The injured had not been counted the next day; twelve or fifteen were spoken of, and Wikipedia gives about ten. Doctors travelling on the train and those of La Fouillouse gave first aid, and a special carriage took injured people back to Saint-Étienne during the night.',
                'de' => 'Vier Menschen wurden getötet. Die Verletzten waren am folgenden Tag noch nicht gezählt; man sprach von zwölf oder fünfzehn, Wikipedia nennt etwa zehn. Im Zug reisende Ärzte und die Ärzte von La Fouillouse leisteten Erste Hilfe, und ein Sonderwagen brachte in der Nacht Verletzte nach Saint-Étienne zurück.',
                'sources' => ['vdr-la-fouillouse', 'wp-liste-1860'],
            ],
            [
                'fr' => 'Le comte de Persigny, alors au banquet du préfet de la Loire pour le conseil général, s\'est rendu sur les lieux avec le préfet et les magistrats de Saint-Étienne.',
                'en' => 'The Comte de Persigny, then at the banquet given by the prefect of the Loire for the departmental council, went to the scene with the prefect and the Saint-Étienne magistrates.',
                'de' => 'Der Comte de Persigny, der gerade am Bankett des Präfekten der Loire für den Generalrat teilnahm, begab sich mit dem Präfekten und den Justizbeamten von Saint-Étienne an den Unfallort.',
                'sources' => ['vdr-la-fouillouse'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le 7 novembre 1863, le tribunal correctionnel de Saint-Étienne a condamné neuf agents (deux chefs de gare, un chef de train, un mécanicien, un chauffeur et quatre gardes-barrières) à des peines de quinze jours à quinze mois de prison et à des amendes de 50 à 1 000 francs. L\'appel a confirmé ces peines, sauf pour l\'un des chefs de gare, relaxé.',
                'en' => 'On 7 November 1863 the Saint-Étienne criminal court convicted nine employees (two stationmasters, a guard, a driver, a fireman and four crossing keepers), with prison terms of fifteen days to fifteen months and fines of 50 to 1,000 francs. The appeal upheld the sentences except for one of the stationmasters, who was acquitted.',
                'de' => 'Am 7. November 1863 verurteilte das Strafgericht von Saint-Étienne neun Bedienstete (zwei Bahnhofsvorsteher, einen Zugführer, einen Lokführer, einen Heizer und vier Schrankenwärter) zu Haftstrafen von fünfzehn Tagen bis fünfzehn Monaten und zu Geldstrafen von 50 bis 1.000 Francs. Die Berufung bestätigte die Strafen, außer für einen der Bahnhofsvorsteher, der freigesprochen wurde.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'L\'heure n\'est pas sûre : Wikipédia dit vers 18 heures, et la dépêche ne donne que l\'horaire de l\'express à Saint-Étienne (17 h 47). Wikipédia parle aussi d\'un omnibus arrêté, la dépêche du « train d\'Andrézieux ».',
                'en' => 'The time is not certain: Wikipedia says about 6 pm, and the dispatch gives only the express\'s scheduled time at Saint-Étienne (5.47 pm). Wikipedia also speaks of a stopping train, the dispatch of "the Andrézieux train".',
                'de' => 'Die Uhrzeit ist unsicher: Wikipedia nennt etwa 18 Uhr, die Depesche nur die Fahrplanzeit des Schnellzugs in Saint-Étienne (17.47 Uhr). Wikipedia spricht zudem von einem haltenden Personenzug, die Depesche vom „Zug nach Andrézieux".',
                'sources' => ['wp-liste-1860', 'vdr-la-fouillouse'],
            ],
        ],
    ],
    'corrections' => [],
];
