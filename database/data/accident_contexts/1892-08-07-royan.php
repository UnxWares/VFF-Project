<?php

// Contexte sourcé du déraillement de l'express n° 87 de Paris à Saintes près de Grandjean et de Fenioux, en août 1892 (recherche du 21/11/2026).
return [
    'slug' => '1892-08-07-royan',
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
            'locator' => '1892, 7 août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1892',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (3 morts et 8 blessés), d\'après Le Petit Parisien du 9 août 1892.',
        ],
        'vdr-fenioux' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1892, L\'Express Paris - Saintes déraille à Fenioux',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1892-lexpress-paris-saintes-deraille-a-fenioux-16/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit « Le déraillement de Grandjean », Le Courrier de La Rochelle du 11 août 1892.',
        ],
        'petit-parisien-1892-08-09' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 9 août 1892',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1892-08-09',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k516445d.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'L\'express n° 87 de Paris à Saintes, sur la ligne du réseau de l\'État, transportait environ deux cents voyageurs et avait ce dimanche soir quarante minutes de retard. Après Saint-Jean-d\'Angély, sa vitesse habituelle de 60 km/h a été portée à 65, puis ramenée à 60 à l\'entrée d\'une courbe assez prononcée après la station de Grandjean.',
                'en' => 'Express no. 87 from Paris to Saintes, on the State railway line, was carrying about two hundred passengers and was forty minutes late that Sunday evening. After Saint-Jean-d\'Angély its usual speed of 60 km/h was raised to 65, then brought back to 60 at the start of a fairly sharp curve beyond Grandjean station.',
                'de' => 'Der Schnellzug Nr. 87 von Paris nach Saintes auf der Strecke der Staatsbahn beförderte etwa zweihundert Reisende und hatte an diesem Sonntagabend vierzig Minuten Verspätung. Nach Saint-Jean-d\'Angély wurde seine übliche Geschwindigkeit von 60 auf 65 km/h erhöht und am Beginn einer recht engen Kurve hinter dem Bahnhof Grandjean wieder auf 60 km/h verringert.',
                'sources' => ['vdr-fenioux', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 18 h 15, à deux kilomètres après Grandjean, à la hauteur du clocher de Fenioux, la machine et le tender ont quitté la voie et se sont renversés sur le côté droit. Le fourgon s\'est brisé, le wagon-restaurant, très endommagé à l\'avant, est resté sur les rails, et une voiture de première classe a volé en éclats.',
                'en' => 'At about 6.15 pm, two kilometres beyond Grandjean, level with the church tower of Fenioux, the engine and tender left the track and overturned to the right. The van broke up, the dining car, badly damaged at the front, stayed on the rails, and a first-class carriage was smashed to pieces.',
                'de' => 'Gegen 18.15 Uhr, zwei Kilometer hinter Grandjean auf der Höhe des Kirchturms von Fenioux, sprangen Lokomotive und Tender aus dem Gleis und stürzten nach rechts um. Der Packwagen zerbrach, der vorn schwer beschädigte Speisewagen blieb auf den Schienen, und ein Wagen erster Klasse wurde völlig zertrümmert.',
                'sources' => ['vdr-fenioux', 'wp-liste-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les voyageurs ont été relativement épargnés : les victimes sont surtout des agents du train et le personnel du wagon-restaurant. Le mécanicien et le chef de train sont morts, et le chauffeur, gravement brûlé contre la chaudière, a succombé le lendemain soir. Neuf blessés ont été conduits à l\'hospice de Saintes et quatre autres soignés près de Fenioux.',
                'en' => 'The passengers were relatively unharmed: the victims were mainly train crew and dining-car staff. The driver and the guard died, and the fireman, badly scalded against the boiler, died the following evening. Nine injured were taken to the Saintes hospice and four more were cared for near Fenioux.',
                'de' => 'Die Reisenden blieben verhältnismäßig verschont: Opfer waren vor allem Zugpersonal und Bedienstete des Speisewagens. Lokführer und Zugführer kamen ums Leben, der am Kessel schwer verbrühte Heizer starb am folgenden Abend. Neun Verletzte wurden ins Hospiz von Saintes gebracht, vier weitere bei Fenioux versorgt.',
                'sources' => ['vdr-fenioux', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause n\'était pas établie quelques jours après ; on l\'attribuait généralement à un écartement de la voie. Wikipédia décrit un déraillement dans une courbe.',
                'en' => 'The cause had not been established a few days later; it was generally put down to the rails spreading. Wikipedia describes a derailment on a curve.',
                'de' => 'Einige Tage später stand die Ursache noch nicht fest; man führte sie allgemein auf eine Spurerweiterung zurück. Wikipedia beschreibt eine Entgleisung in einer Kurve.',
                'sources' => ['vdr-fenioux', 'wp-liste-1890'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Un train de secours est parti de Saintes avec des médecins pour transborder les voyageurs. Le préfet et les sous-préfets de Saint-Jean-d\'Angély et de Saintes sont arrivés à Grandjean à 3 heures du matin, et la circulation a repris dès le lendemain à 6 h 21.',
                'en' => 'A relief train left Saintes with doctors to transfer the passengers. The prefect and the sub-prefects of Saint-Jean-d\'Angély and Saintes reached Grandjean at 3 am, and traffic resumed the next morning at 6.21 am.',
                'de' => 'Ein Hilfszug mit Ärzten fuhr von Saintes ab, um die Reisenden umsteigen zu lassen. Der Präfekt und die Unterpräfekten von Saint-Jean-d\'Angély und Saintes trafen um 3 Uhr morgens in Grandjean ein, und der Verkehr lief am nächsten Morgen um 6.21 Uhr wieder.',
                'sources' => ['vdr-fenioux'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche place l\'accident à Royan, destination de l\'express selon Wikipédia, qui le situe près de Taillebourg. Le Courrier de La Rochelle le place à deux kilomètres après Grandjean, à la hauteur de Fenioux, sans nommer la commune. Wikipédia compte huit blessés, le journal treize.',
                'en' => 'The record places the accident at Royan, the express\'s destination according to Wikipedia, which puts it near Taillebourg. The Courrier de La Rochelle places it two kilometres beyond Grandjean, level with Fenioux, without naming the commune. Wikipedia counts eight injured, the newspaper thirteen.',
                'de' => 'Der Eintrag verortet den Unfall in Royan, laut Wikipedia dem Ziel des Schnellzugs, die ihn bei Taillebourg ansiedelt. Der Courrier de La Rochelle verortet ihn zwei Kilometer hinter Grandjean auf der Höhe von Fenioux, ohne die Gemeinde zu nennen. Wikipedia zählt acht Verletzte, die Zeitung dreizehn.',
                'sources' => ['wp-liste-1890', 'vdr-fenioux'],
            ],
        ],
    ],
    'corrections' => [],
];
