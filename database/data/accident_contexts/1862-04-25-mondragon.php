<?php

// Contexte sourcé du déraillement d'un train venant de Marseille près de Mondragon, avril 1862 (recherche du 22/11/2026).
return [
    'slug' => '1862-04-25-mondragon',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1862',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1862',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée datée du 25 avril 1861, sous la rubrique 1862 et aussi sous 1861, d\'après Le Temps du 28 avril : près de Mondragon (Vaucluse), un train de voyageurs venant de Marseille déraille dans une courbe ; la locomotive, le tender et le fourgon dévalent le talus, le reste du train reste sur la voie ; le chauffeur est tué, quatre autres cheminots blessés, quelques voyageurs contusionnés.',
        ],
        'elux-1862-04-30-courrier' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Faits divers',
            'publisher' => 'Courrier du Grand-Duché de Luxembourg (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1862-04-30',
            'locator' => 'p. 2',
            'url' => 'https://persist.lu/ark:70795/0161c2/pages/2/articles/DTL38',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Récit repris de la presse française : le train de la Méditerranée parti vendredi de Marseille à 10 h, attendu le soir à Lyon, s\'est engagé à toute vitesse dans une courbe très prononcée qui longe un talus de huit mètres aux approches de Mondragon ; la locomotive a déraillé et a été précipitée avec son tender et un wagon de bagages ; le chauffeur tué sur le coup ; un wagon placé derrière a protégé les voitures de voyageurs.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le train de voyageurs parti de Marseille à 10 h pour Lyon s\'est engagé à grande vitesse dans une courbe très prononcée, le long d\'un talus de huit mètres, aux approches de Mondragon. La locomotive a déraillé et a dévalé le talus avec son tender et le fourgon à bagages. Les voitures de voyageurs sont restées sur la voie.',
                'en' => 'The passenger train that had left Marseille at 10 am for Lyon entered a very sharp curve at high speed, along an eight-metre embankment, on the approach to Mondragon. The engine derailed and rolled down the embankment with its tender and the luggage van. The passenger carriages stayed on the track.',
                'de' => 'Der um 10 Uhr in Marseille nach Lyon abgefahrene Personenzug fuhr vor Mondragon mit hoher Geschwindigkeit in eine sehr enge Kurve entlang einer acht Meter hohen Böschung. Die Lokomotive entgleiste und stürzte mit Tender und Gepäckwagen die Böschung hinab. Die Personenwagen blieben auf dem Gleis.',
                'sources' => ['wp-liste-1860', 'elux-1862-04-30-courrier'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur a été tué sur le coup. Selon la liste de Wikipédia, quatre autres cheminots ont été blessés et quelques voyageurs contusionnés.',
                'en' => 'The fireman was killed outright. According to the Wikipedia list, four other railwaymen were injured and a few passengers bruised.',
                'de' => 'Der Heizer war sofort tot. Nach der Wikipedia-Liste wurden vier weitere Eisenbahner verletzt und einige Reisende geprellt.',
                'sources' => ['elux-1862-04-30-courrier', 'wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Année : la liste de Wikipédia date l\'accident du 25 avril 1861 et le place à la fois sous 1861 et sous 1862. Le Courrier du Grand-Duché, paru le 30 avril 1862, parle d\'un train parti « vendredi » ; le 25 avril 1862 était un vendredi, le 25 avril 1861 un jeudi.',
                'en' => 'Year: the Wikipedia list dates the accident 25 April 1861 and places it under both 1861 and 1862. The Courrier du Grand-Duché, published on 30 April 1862, speaks of a train that left "on Friday"; 25 April 1862 was a Friday, 25 April 1861 a Thursday.',
                'de' => 'Jahr: Die Wikipedia-Liste datiert den Unfall auf den 25. April 1861 und führt ihn sowohl unter 1861 als auch unter 1862. Der am 30. April 1862 erschienene Courrier du Grand-Duché spricht von einem „am Freitag“ abgefahrenen Zug; der 25. April 1862 war ein Freitag, der 25. April 1861 ein Donnerstag.',
                'sources' => ['wp-liste-1860', 'elux-1862-04-30-courrier'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1860', 'elux-1862-04-30-courrier'],
            'fr' => 'Chauffeur tué selon la liste de Wikipédia (Le Temps) et le Courrier du Grand-Duché de Luxembourg du 30 avril 1862 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Fireman killed according to the Wikipedia list (Le Temps) and the Courrier du Grand-Duché de Luxembourg of 30 April 1862; the record gave no number of deaths.',
            'de' => 'Heizer getötet nach der Wikipedia-Liste (Le Temps) und dem Courrier du Grand-Duché de Luxembourg vom 30. April 1862; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
    'aliases' => [
        [
            'slug' => '1861-04-25-mondragon',
            'sources' => ['wp-liste-1860', 'elux-1862-04-30-courrier'],
            'fr' => 'La fiche de 1861 est le même accident : la liste de Wikipédia le range sous 1861 et sous 1862 avec le même texte (d\'après Le Temps du 28 avril). Le Courrier du Grand-Duché du 30 avril 1862 parle d\'un train parti « vendredi », et seul le 25 avril 1862 était un vendredi : l\'année retenue est 1862. La fiche de 1861 ne donnait ni bilan ni précision de plus.',
            'en' => 'The 1861 record is the same accident: the Wikipedia list files it under 1861 and under 1862 with the same text (from Le Temps of 28 April). The Courrier du Grand-Duché of 30 April 1862 speaks of a train that left "on Friday", and only 25 April 1862 was a Friday: the year kept is 1862. The 1861 record gave no toll and no detail beyond this one.',
            'de' => 'Der Eintrag von 1861 ist derselbe Unfall: Die Wikipedia-Liste führt ihn mit demselben Text (nach Le Temps vom 28. April) unter 1861 und unter 1862. Der Courrier du Grand-Duché vom 30. April 1862 spricht von einem „am Freitag“ abgefahrenen Zug, und nur der 25. April 1862 war ein Freitag: Beibehalten wird das Jahr 1862. Der Eintrag von 1861 nannte weder eine Bilanz noch weitere Angaben.',
        ],
    ],
];
