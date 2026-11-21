<?php

// Contexte sourcé du tamponnement du train de voyageurs n° 330 par le train de marchandises n° 5002 en gare d'Albert, en mai 1896 (recherche du 21/11/2026).
return [
    'slug' => '1896-05-03-albert',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1896, 3 mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1896',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (3 morts et une douzaine de blessés), d\'après Le Temps du 6 mai 1896.',
        ],
        'vdr-albert' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1896, Le tamponnement d\'Albert (80)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1896-le-tamponnement-dalbert-80/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit la lettre du correspondant d\'Amiens publiée par Le Temps du 6 mai 1896.',
        ],
        'le-temps-1896-05-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 6 mai 1896',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1896-05-06',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k234850d/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 3 mai 1896, jour d\'élections municipales, le petit train spécial hebdomadaire d\'Albert à Amiens, n° 330, s\'apprêtait à partir à 22 heures. Il ne portait qu\'une vingtaine de voyageurs, dont des militaires qui regagnaient leur garnison à Amiens.',
                'en' => 'On Sunday 3 May 1896, a municipal election day, the small weekly special train no. 330 from Albert to Amiens was about to leave at 10 pm. It carried only about twenty passengers, including soldiers returning to their garrison in Amiens.',
                'de' => 'Am Sonntag, dem 3. Mai 1896, einem Tag der Gemeindewahlen, sollte der kleine wöchentliche Sonderzug Nr. 330 von Albert nach Amiens um 22 Uhr abfahren. Er beförderte nur etwa zwanzig Reisende, darunter Soldaten auf dem Rückweg in ihre Garnison in Amiens.',
                'sources' => ['vdr-albert', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le surveillant de nuit a vu le train de marchandises n° 5002 déboucher à toute vitesse sous le pont de la direction d\'Arras et a sifflé ; le train de voyageurs a démarré, mais après une dizaine de mètres la machine du 5002 a tamponné sa queue, à 22 h 05. Les deux dernières voitures de troisième classe ont été réduites en miettes ; la dernière était vide.',
                'en' => 'The night inspector saw goods train no. 5002 come out at full speed from under the bridge on the Arras side and blew his whistle; the passenger train moved off, but after about ten metres the engine of no. 5002 ran into its rear, at 10.05 pm. The last two third-class carriages were smashed to pieces; the very last one was empty.',
                'de' => 'Der Nachtaufseher sah den Güterzug Nr. 5002 mit voller Geschwindigkeit unter der Brücke in Richtung Arras hervorkommen und pfiff; der Personenzug fuhr an, doch nach etwa zehn Metern prallte die Lokomotive des 5002 um 22.05 Uhr auf sein Ende. Die beiden letzten Wagen dritter Klasse wurden zertrümmert; der allerletzte war leer.',
                'sources' => ['vdr-albert', 'wp-liste-1890'],
            ],
            [
                'fr' => 'La voie a été défoncée sur cinquante mètres et le mur du bureau de la petite vitesse enfoncé. Un quart d\'heure après, plus de cinq cents personnes avaient envahi la gare ; les morts et les blessés ont été conduits à l\'hospice.',
                'en' => 'The track was torn up over fifty metres and the wall of the goods office was stove in. A quarter of an hour later more than five hundred people had crowded into the station; the dead and injured were taken to the hospice.',
                'de' => 'Das Gleis wurde auf fünfzig Metern aufgerissen und die Mauer des Stückgutbüros eingedrückt. Eine Viertelstunde später drängten sich mehr als fünfhundert Menschen im Bahnhof; Tote und Verletzte wurden ins Hospiz gebracht.',
                'sources' => ['vdr-albert'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'avant-dernière voiture portait une dizaine de voyageurs, dont trois ont été tués sur le coup. Une dizaine d\'autres voyageurs, les conducteurs et le garde-frein ont été sérieusement contusionnés.',
                'en' => 'The second-to-last carriage held about ten passengers, three of whom were killed outright. About ten other passengers, the guards and the brakeman were badly bruised.',
                'de' => 'Im vorletzten Wagen saßen etwa zehn Reisende, von denen drei sofort tot waren. Etwa zehn weitere Reisende, die Zugbegleiter und der Bremser erlitten schwere Prellungen.',
                'sources' => ['vdr-albert', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le mécanicien du 5002 soutenait que les signaux indiquaient la voie libre. Le correspondant du Temps rappelait que l\'entrée de la gare d\'Albert passait pour dangereuse auprès des agents du Nord : une courbe très prononcée à quelques centaines de mètres, et une pente assez rapide depuis Achiet où les mécaniciens avaient souvent du mal à tenir leur machine.',
                'en' => 'The driver of no. 5002 maintained that the signals showed the line clear. Le Temps\'s correspondent noted that Nord railway staff considered the approach to Albert station dangerous: a very sharp curve a few hundred metres out, and a fairly steep descent from Achiet on which drivers often struggled to control their engines.',
                'de' => 'Der Lokführer des 5002 behauptete, die Signale hätten freie Fahrt gezeigt. Der Korrespondent des Temps merkte an, dass die Einfahrt in den Bahnhof Albert unter den Bediensteten der Nordbahn als gefährlich galt: eine sehr enge Kurve einige hundert Meter vorher und ein recht starkes Gefälle ab Achiet, auf dem die Lokführer ihre Maschine oft kaum beherrschten.',
                'sources' => ['vdr-albert'],
            ],
        ],
    ],
    'corrections' => [],
];
