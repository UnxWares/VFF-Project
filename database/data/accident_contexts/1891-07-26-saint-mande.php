<?php

// Contexte sourcé de l'accident de Saint-Mandé du 26 juillet 1891 (recherche du 21/11/2026).
return [
    'slug' => '1891-07-26-saint-mande',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-fr' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire de Saint-Mandé',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_ferroviaire_de_Saint-Mand%C3%A9&oldid=239904041',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé reposant sur une soixantaine de notes, surtout la presse de 1891 (Gallica) et des revues techniques.',
        ],
        'saint-mande-mag' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'La gare de Saint-Mandé',
            'publisher' => 'Ville de Saint-Mandé (magazine municipal, janvier 2023)',
            'author' => null,
            'published' => '2023-01',
            'locator' => 'encadré « La catastrophe de 1891 »',
            'url' => 'https://www.saintmande.fr/fileadmin/mediatheque/illustration/16-archives/Articles_historiques/La_gare_de_Saint-Mande_MAG_janvier_2023.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne le bilan de 46 morts et 150 blessés et la mention du monument.',
        ],
        'commons-monument' => [
            'provider' => 'commons',
            'kind' => 'memorial',
            'reliability' => 'encyclopedia',
            'title' => 'File:Monument cimetiere Sud St Mandé.jpg',
            'publisher' => 'Wikimedia Commons',
            'author' => 'JCB94',
            'published' => '2019-10',
            'locator' => null,
            'url' => 'https://commons.wikimedia.org/wiki/File:Monument_cimetiere_Sud_St_Mand%C3%A9.jpg',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Photographie (CC BY-SA 4.0) du monument à la mémoire des morts du 26 juillet 1891.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 26 juillet 1891, des milliers de Parisiens rentrent de la fête foraine de Saint-Mandé et d\'un concours de musique à Fontenay-sous-Bois par la ligne de Vincennes de la Compagnie de l\'Est. Le train 116, de vingt et une voitures à impériale, arrive à Saint-Mandé vers 21 h 18, avec huit minutes de retard.',
                'en' => 'On Sunday 26 July 1891, thousands of Parisians were returning from the Saint-Mandé funfair and a music contest at Fontenay-sous-Bois on the Vincennes line of the Est company. Train 116, made up of twenty-one double-deck carriages, reached Saint-Mandé at about 9.18 pm, eight minutes late.',
                'de' => 'Am Sonntag, dem 26. Juli 1891, kehrten Tausende Pariser vom Jahrmarkt in Saint-Mandé und von einem Musikwettbewerb in Fontenay-sous-Bois auf der Vincennes-Linie der Est-Gesellschaft zurück. Der Zug 116 aus einundzwanzig Doppelstockwagen traf gegen 21.18 Uhr mit acht Minuten Verspätung in Saint-Mandé ein.',
                'sources' => ['wp-fr', 'saint-mande-mag'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train 116 stationne plus de sept minutes en gare, au lieu des deux prévues, à la suite d\'un incident entre un voyageur et des employés. Le train 116 D, parti de Vincennes trois minutes plus tôt avec dix-huit véhicules, le percute. Sa locomotive, la 669 de type 031 T, écrase le fourgon de queue et deux voitures, et un incendie se déclare.',
                'en' => 'Train 116 stood in the station for more than seven minutes instead of the scheduled two, after an incident between a passenger and staff. Train 116 D, which had left Vincennes three minutes earlier with eighteen vehicles, ran into it. Its locomotive, no. 669, a 0-6-0 tank engine, crushed the rear van and two carriages, and a fire broke out.',
                'de' => 'Der Zug 116 stand mehr als sieben Minuten im Bahnhof statt der vorgesehenen zwei, nach einem Zwischenfall zwischen einem Reisenden und Personal. Der Zug 116 D, der drei Minuten früher mit achtzehn Fahrzeugen aus Vincennes abgefahren war, fuhr auf. Seine Lokomotive Nr. 669, eine Tenderlok der Bauart 031 T, zerdrückte den Schlusswagen und zwei Wagen, und es brach ein Brand aus.',
                'sources' => ['wp-fr', 'saint-mande-mag'],
            ],
            [
                'fr' => 'Les pompiers de Saint-Mandé, Vincennes et Charenton maîtrisent l\'incendie, et des militaires de Vincennes participent aux secours. Les corps sont déposés à la mairie-école de Saint-Mandé, ceux qui ne sont pas identifiés sont transférés à la Morgue de Paris.',
                'en' => 'Firefighters from Saint-Mandé, Vincennes and Charenton brought the fire under control, and soldiers from Vincennes helped with the rescue. The bodies were laid out in the town hall and school of Saint-Mandé, and those not identified were taken to the Paris Morgue.',
                'de' => 'Die Feuerwehren von Saint-Mandé, Vincennes und Charenton löschten den Brand, und Soldaten aus Vincennes beteiligten sich an der Rettung. Die Toten wurden im Rathaus mit Schule von Saint-Mandé aufgebahrt, nicht identifizierte kamen in die Pariser Morgue.',
                'sources' => ['wp-fr'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les premiers secours comptent environ 43 morts. Le bilan officiel passe à 46 dans le réquisitoire, puis à une cinquantaine dans le jugement. On cite 150 blessés. Les obsèques collectives ont lieu le 29 juillet 1891 et les victimes sont inhumées au cimetière Sud de Saint-Mandé.',
                'en' => 'The first rescue count gave about 43 dead. The official toll rose to 46 in the prosecution\'s address, then to about fifty in the judgment. 150 injured are cited. The collective funeral took place on 29 July 1891 and the victims were buried in the South cemetery of Saint-Mandé.',
                'de' => 'Bei den ersten Rettungsarbeiten wurden etwa 43 Tote gezählt. Die amtliche Zahl stieg in der Anklagerede auf 46, im Urteil auf etwa fünfzig. Genannt werden 150 Verletzte. Die gemeinsame Trauerfeier fand am 29. Juli 1891 statt, die Opfer wurden auf dem Südfriedhof von Saint-Mandé beigesetzt.',
                'sources' => ['wp-fr', 'saint-mande-mag'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'enquête retient plusieurs facteurs : l\'arrêt prolongé du train 116, le départ du train suivant donné par le sous-chef de gare sans vérification du sémaphore du block-system (dont le régime était dit permissif), et une anomalie de la conduite d\'air qui limitait le freinage aux quatre premières voitures. La conduite de la locomotive cheminée en arrière a été critiquée, sans qu\'aucune charge soit retenue sur ce point.',
                'en' => 'The inquiry identified several factors: the prolonged stop of train 116, the departure of the following train given without checking the block-system semaphore (whose regime was called permissive), and a fault in the air brake pipe that limited braking to the first four carriages. Running the locomotive chimney-first in reverse was criticised, but no charge was brought on that point.',
                'de' => 'Die Untersuchung nannte mehrere Faktoren: den langen Halt des Zuges 116, die Abfahrterlaubnis für den Folgezug ohne Prüfung des Blocksignals (dessen System als permissiv galt) und eine Störung der Luftbremsleitung, die das Bremsen auf die ersten vier Wagen beschränkte. Die Fahrt der Lokomotive mit dem Schornstein nach hinten wurde kritisiert, ohne dass dazu eine Anklage erhoben wurde.',
                'sources' => ['wp-fr'],
            ],
            [
                'fr' => 'Des essais menés ensuite ont montré que le train aurait pu s\'arrêter à temps avec une vigilance normale. La position du disque d\'entrée de Saint-Mandé reste contestée.',
                'en' => 'Tests carried out afterwards showed that the train could have stopped in time with normal vigilance. The position of the Saint-Mandé entry signal remains disputed.',
                'de' => 'Spätere Versuche zeigten, dass der Zug bei normaler Aufmerksamkeit rechtzeitig hätte halten können. Die Stellung des Einfahrsignals von Saint-Mandé bleibt umstritten.',
                'sources' => ['wp-fr'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le sous-chef de gare et le mécanicien sont jugés par le tribunal correctionnel de Paris à partir du 21 septembre 1891, sur la base de la loi du 15 juillet 1845 sur la police des chemins de fer. Le jugement du 26 septembre condamne le mécanicien à deux ans de prison et 500 francs d\'amende, le sous-chef de gare à quatre mois de prison et 300 francs d\'amende. La cour d\'appel confirme la condamnation du mécanicien le 30 octobre 1891 ; il bénéficie d\'une remise de peine en septembre 1892.',
                'en' => 'The assistant station master and the driver were tried by the Paris correctional court from 21 September 1891 under the law of 15 July 1845 on railway policing. The judgment of 26 September sentenced the driver to two years in prison and a 500-franc fine, and the assistant station master to four months and a 300-franc fine. The appeal court upheld the driver\'s sentence on 30 October 1891; he received a remission in September 1892.',
                'de' => 'Der Hilfsbahnhofsvorsteher und der Lokführer wurden ab dem 21. September 1891 vom Pariser Zuchtpolizeigericht auf Grundlage des Gesetzes vom 15. Juli 1845 über die Eisenbahnpolizei verurteilt. Das Urteil vom 26. September lautete für den Lokführer auf zwei Jahre Haft und 500 Francs Geldstrafe, für den Hilfsbahnhofsvorsteher auf vier Monate Haft und 300 Francs. Das Berufungsgericht bestätigte die Verurteilung des Lokführers am 30. Oktober 1891; im September 1892 wurde ihm die Strafe erlassen.',
                'sources' => ['wp-fr'],
            ],
            [
                'fr' => 'Le débat parlementaire porte sur le block-system : un député réclame le block absolu, tandis que le ministre défend le système permissif. L\'article consulté ne documente pas de mesure de sécurité précise adoptée en conséquence.',
                'en' => 'The parliamentary debate centred on the block system: one deputy called for absolute block working, while the minister defended the permissive system. The article consulted documents no specific safety measure adopted as a result.',
                'de' => 'Die parlamentarische Debatte drehte sich um das Blocksystem: Ein Abgeordneter forderte den absoluten Block, der Minister verteidigte das permissive System. Der eingesehene Artikel belegt keine konkrete daraufhin beschlossene Sicherheitsmaßnahme.',
                'sources' => ['wp-fr'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'Un monument à la mémoire des morts, financé par la Compagnie de l\'Est sur un terrain concédé gratuitement par la commune, se dresse au cimetière Sud de Saint-Mandé.',
                'en' => 'A monument to the dead, paid for by the Est company on land granted free of charge by the commune, stands in the South cemetery of Saint-Mandé.',
                'de' => 'Ein Denkmal für die Toten, von der Est-Gesellschaft auf einem von der Gemeinde unentgeltlich überlassenen Grundstück finanziert, steht auf dem Südfriedhof von Saint-Mandé.',
                'sources' => ['saint-mande-mag', 'wp-fr', 'commons-monument'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère selon les sources : 43 morts aux premiers secours, 46 dans le réquisitoire, une cinquantaine dans le jugement, 49 morts et 221 blessés dans une revue technique citée par Wikipédia, 150 blessés selon le magazine municipal.',
                'en' => 'The toll differs between sources: 43 dead at the first rescue count, 46 in the prosecution\'s address, about fifty in the judgment, 49 dead and 221 injured in a technical journal cited by Wikipedia, 150 injured according to the municipal magazine.',
                'de' => 'Die Opferzahlen unterscheiden sich je nach Quelle: 43 Tote bei den ersten Rettungsarbeiten, 46 in der Anklagerede, etwa fünfzig im Urteil, 49 Tote und 221 Verletzte in einer von Wikipedia zitierten Fachzeitschrift, 150 Verletzte laut Gemeindemagazin.',
                'sources' => ['wp-fr', 'saint-mande-mag'],
            ],
        ],
    ],
    'corrections' => [],
    'images' => [
        [
            'file' => 'File:Catastrophy of Saint-Mandé - First aid at 21.30, 26 July 1891.jpg',
            'caption' => [
                'fr' => 'Les premiers secours à 21 h 30 le 26 juillet 1891 (gravure du Monde illustré, 1891).',
                'en' => 'First aid at 9.30 pm on 26 July 1891 (engraving from Le Monde illustré, 1891).',
                'de' => 'Erste Hilfe um 21.30 Uhr am 26. Juli 1891 (Stich aus Le Monde illustré, 1891).',
            ],
        ],
        [
            'file' => 'File:Monument cimetiere Sud St Mandé.jpg',
            'caption' => [
                'fr' => 'Le monument aux victimes au cimetière Sud de Saint-Mandé.',
                'en' => 'The monument to the victims in the South cemetery of Saint-Mandé.',
                'de' => 'Das Denkmal für die Opfer auf dem Südfriedhof von Saint-Mandé.',
            ],
        ],
    ],
];
