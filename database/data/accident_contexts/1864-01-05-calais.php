<?php

// Contexte sourcé de la collision de la station de Pierrefitte (chemin de fer du Nord) du 5 janvier 1864, d'un omnibus venant de Calais (recherche du 21/11/2026).
return [
    'slug' => '1864-01-05-calais',
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
            'locator' => '1864, 5 janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1864',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
        'vdr-pierrefitte' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1864, Carambolage de trains à la station de Pierrefitte (93)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2026-05-01',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1864-carambolage-de-trains-a-la-station-de-pierrefitte-93/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit L\'Écho de la vallée de Bray du 9 janvier 1864, qui reprend le communiqué de la Compagnie du Nord.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le mardi 5 janvier 1864, l\'omnibus n° 24 venant de Calais, attendu à Paris à 16 h 15, avait une bielle de sa machine cassée entre Gonesse et Pierrefitte. Il a continué avec peine jusqu\'à la station de Pierrefitte, où il s\'est arrêté, couvert par les signaux réglementaires selon la Compagnie du Nord.',
                'en' => 'On Tuesday 5 January 1864, stopping train no. 24 from Calais, due in Paris at 4.15 pm, broke a connecting rod on its engine between Gonesse and Pierrefitte. It struggled on to Pierrefitte station and stopped there, protected by the regulation signals according to the Nord company.',
                'de' => 'Am Dienstag, dem 5. Januar 1864, brach am Personenzug Nr. 24 aus Calais, der um 16.15 Uhr in Paris erwartet wurde, zwischen Gonesse und Pierrefitte eine Treibstange der Lokomotive. Er schleppte sich bis zur Station Pierrefitte und hielt dort, laut der Nordbahn durch die vorgeschriebenen Signale gedeckt.',
                'sources' => ['vdr-pierrefitte', 'wp-liste-1860'],
            ],
            [
                'fr' => 'Vers 16 h 40, le semi-direct n° 26 venant de Bruxelles est arrivé malgré les signaux d\'arrêt et a heurté violemment l\'arrière de l\'omnibus, dont il a brisé cinq voitures. Sa machine est montée sur les wagons, qui ont pris feu.',
                'en' => 'At about 4.40 pm, semi-fast train no. 26 from Brussels arrived despite the stop signals and ran hard into the rear of the stopping train, smashing five of its coaches. Its engine rode up onto the wagons, which caught fire.',
                'de' => 'Gegen 16.40 Uhr traf der Eilzug Nr. 26 aus Brüssel trotz der Haltesignale ein und prallte heftig auf das Ende des Personenzugs, von dem er fünf Wagen zertrümmerte. Seine Lokomotive schob sich auf die Wagen, die in Brand gerieten.',
                'sources' => ['vdr-pierrefitte', 'wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un inspecteur avait fait descendre le plus de voyageurs possible. Cinq voyageurs de l\'omnibus ont pourtant été tués et dix-neuf blessés ; ceux du train de Bruxelles n\'ont eu que des contusions. Tous les blessés ont été amenés à Paris dans la soirée, et les plus graves hospitalisés.',
                'en' => 'An inspector had got as many passengers as possible out of the train. Even so, five passengers of the stopping train were killed and nineteen injured; those on the Brussels train had only bruises. All the injured were taken to Paris that evening, and the most serious cases admitted to hospital.',
                'de' => 'Ein Inspektor hatte so viele Reisende wie möglich aussteigen lassen. Dennoch wurden fünf Reisende des Personenzugs getötet und neunzehn verletzt; die Reisenden des Brüsseler Zuges erlitten nur Prellungen. Alle Verletzten wurden noch am Abend nach Paris gebracht, die schwersten Fälle ins Krankenhaus.',
                'sources' => ['vdr-pierrefitte', 'wp-liste-1860'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le 31 janvier 1864, le tribunal correctionnel de Paris a condamné le mécanicien du train tamponneur à trois ans de prison, son chauffeur et le chef de train à six mois et 300 francs d\'amende, pour n\'avoir pas respecté la signalisation, et a déclaré la Compagnie du Nord civilement responsable.',
                'en' => 'On 31 January 1864 the Paris criminal court sentenced the driver of the colliding train to three years in prison and his fireman and the guard to six months and a 300-franc fine for disregarding the signals, and held the Nord company civilly liable.',
                'de' => 'Am 31. Januar 1864 verurteilte das Pariser Strafgericht den Lokführer des auffahrenden Zuges zu drei Jahren Gefängnis, seinen Heizer und den Zugführer zu sechs Monaten und 300 Franc Geldstrafe, weil sie die Signale missachtet hatten, und erklärte die Nordbahn für zivilrechtlich haftbar.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '93059',
            'sources' => ['vdr-pierrefitte', 'wp-liste-1860'],
            'fr' => 'Calais était l\'origine de l\'omnibus ; la collision a eu lieu en gare de Pierrefitte, sur la ligne de Paris à Lille.',
            'en' => 'Calais was where the stopping train came from; the collision happened at Pierrefitte station, on the Paris to Lille line.',
            'de' => 'Calais war der Ausgangsort des Personenzugs; der Zusammenstoß ereignete sich im Bahnhof Pierrefitte an der Strecke Paris-Lille.',
        ],
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['vdr-pierrefitte', 'wp-liste-1860'],
            'fr' => 'Le semi-direct de Bruxelles a heurté l\'arrière de l\'omnibus arrêté : c\'est une collision ; la rupture d\'une bielle n\'a fait qu\'immobiliser l\'omnibus.',
            'en' => 'The Brussels semi-fast train ran into the rear of the stopped train: this is a collision; the broken connecting rod only brought the stopping train to a halt.',
            'de' => 'Der Brüsseler Eilzug fuhr auf das Ende des haltenden Personenzugs auf: Es handelt sich um einen Zusammenstoß; der Bruch der Treibstange brachte den Personenzug nur zum Stehen.',
        ],
    ],
];
