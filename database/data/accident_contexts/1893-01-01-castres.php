<?php

// Contexte sourcé du déraillement du train 706 près de Castres du 1er janvier 1893 (recherche du 21/11/2026).
return [
    'slug' => '1893-01-01-castres',
    'checked_on' => '2026-11-21',
    'sources' => [
        'vdr-castres' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1893, Déraillement du train n° 706 à destination de Montauban (Castres, 81)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1893-deraillement-castres/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit la dépêche de Castres du 1er janvier 1893 publiée par Le Matin du 2 janvier 1893.',
        ],
        'matin-1893-01-02' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 2 janvier 1893',
            'publisher' => 'Le Matin',
            'author' => null,
            'published' => '1893-01-02',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k555114r/f1',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1893',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1893',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : déraillement au passage à niveau de Sainte-Croix, 2 morts (mécanicien et chauffeur) et 9 blessés dont la garde-barrière ; cite Le Matin du 2 janvier 1893 et le JO du 20 avril 1893.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 1er janvier 1893, le train 706 est parti de la gare de Castres à 11 h 12, sur la ligne de Castres à Saint-Sulpice. Il a déraillé cinq minutes plus tard, à 11 h 17, en face de la maisonnette de Sainte-Croix, qui a été renversée. La liste de Wikipédia place l\'accident au passage à niveau de Sainte-Croix.',
                'en' => 'On Sunday 1 January 1893, train 706 left Castres station at 11.12 am, on the Castres to Saint-Sulpice line. It derailed five minutes later, at 11.17 am, opposite the Sainte-Croix crossing keeper\'s cottage, which was knocked down. The Wikipedia list places the accident at the Sainte-Croix level crossing.',
                'de' => 'Am Sonntag, dem 1. Januar 1893, fuhr der Zug 706 um 11.12 Uhr im Bahnhof Castres auf der Strecke von Castres nach Saint-Sulpice ab. Fünf Minuten später, um 11.17 Uhr, entgleiste er gegenüber dem Wärterhäuschen von Sainte-Croix, das umgeworfen wurde. Die Wikipedia-Liste verortet den Unfall am Bahnübergang Sainte-Croix.',
                'sources' => ['vdr-castres', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La machine est restée couchée sur le dos en dehors de la voie, recouverte par son tender, lui-même recouvert par les débris du fourgon. La première voiture est montée au sommet de ces débris, presque intacte, et une voiture de deuxième classe a été complètement brisée sans que ses voyageurs soient blessés. La voie n\'a pas pu être dégagée avant le lendemain.',
                'en' => 'The engine lay on its back outside the track, covered by its tender, itself covered by the wreckage of the luggage van. The first carriage rose to the top of this wreckage, almost intact, and a second-class carriage was completely smashed without its passengers being hurt. The line could not be cleared before the next day.',
                'de' => 'Die Lokomotive lag auf dem Rücken außerhalb des Gleises, bedeckt von ihrem Tender, der seinerseits von den Trümmern des Gepäckwagens bedeckt war. Der erste Wagen kam fast unversehrt oben auf den Trümmern zu liegen, und ein Wagen der zweiten Klasse wurde völlig zerbrochen, ohne dass seine Reisenden verletzt wurden. Die Strecke konnte erst am folgenden Tag geräumt werden.',
                'sources' => ['vdr-castres'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués : le corps du mécanicien, carbonisé, était sous la machine, et le chauffeur est mort dans la maisonnette voisine où il avait été transporté. Neuf voyageurs ont été légèrement blessés, ainsi que la garde-barrière. L\'artillerie a aidé au transbordement des voyageurs arrivant de Castelnaudary et de Montauban.',
                'en' => 'The driver and the fireman were killed: the driver\'s body, charred, lay under the engine, and the fireman died in the nearby cottage where he had been carried. Nine passengers were slightly injured, as was the crossing keeper. Artillery troops helped to transfer the passengers arriving from Castelnaudary and Montauban.',
                'de' => 'Lokführer und Heizer wurden getötet: Der verkohlte Leichnam des Lokführers lag unter der Lokomotive, und der Heizer starb in dem nahen Häuschen, in das er gebracht worden war. Neun Reisende wurden leicht verletzt, ebenso die Schrankenwärterin. Artilleristen halfen beim Umsteigen der aus Castelnaudary und Montauban eintreffenden Reisenden.',
                'sources' => ['vdr-castres', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le correspondant du Matin écrit qu\'on ignore la cause de l\'accident ; le parquet au complet a procédé à des investigations.',
                'en' => 'Le Matin\'s correspondent writes that the cause of the accident was unknown; the full public prosecutor\'s office carried out investigations.',
                'de' => 'Der Korrespondent des Matin schreibt, die Ursache des Unfalls sei unbekannt; die gesamte Staatsanwaltschaft habe Ermittlungen angestellt.',
                'sources' => ['vdr-castres'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le Matin compte neuf voyageurs légèrement blessés « ainsi que la garde-barrière », soit dix blessés, alors que la liste de Wikipédia compte neuf blessés dont la garde-barrière. Le nombre de blessés reste donc incertain, entre neuf et dix.',
                'en' => 'Le Matin counts nine passengers slightly injured "as well as the crossing keeper", so ten injured, whereas the Wikipedia list counts nine injured including the crossing keeper. The number of injured therefore remains uncertain, between nine and ten.',
                'de' => 'Le Matin zählt neun leicht verletzte Reisende „sowie die Schrankenwärterin“, also zehn Verletzte, während die Wikipedia-Liste neun Verletzte einschließlich der Schrankenwärterin zählt. Die Zahl der Verletzten bleibt daher unsicher, zwischen neun und zehn.',
                'sources' => ['vdr-castres', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'derailment',
            'sources' => ['vdr-castres', 'wp-liste-1890'],
            'fr' => 'Les deux sources décrivent un déraillement du train 706 sans véhicule routier en cause : la machine s\'est couchée hors de la voie et a renversé la maisonnette de la garde-barrière.',
            'en' => 'Both sources describe a derailment of train 706 with no road vehicle involved: the engine overturned off the track and knocked down the crossing keeper\'s cottage.',
            'de' => 'Beide Quellen beschreiben eine Entgleisung des Zuges 706 ohne beteiligtes Straßenfahrzeug: Die Lokomotive kippte neben das Gleis und warf das Wärterhäuschen um.',
        ],
    ],
];
