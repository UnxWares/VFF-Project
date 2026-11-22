<?php

// Contexte sourcé de la collision de deux express près de Riols, le 30 septembre 1908 (recherche du 21/11/2026).
return [
    'slug' => '1908-09-30-riols',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '30 septembre 1908, Riols',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne de Castres à Bédarieux, erreur du chef de gare de Saint-Étienne-d\'Albagnan, face-à-face près de Riols, 2 morts et 20 blessés ; cite Le Petit Journal du 1er octobre 1908.',
        ],
        'vaderland-1908-10-01' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het Vaderland du 1 octobre 1908',
            'publisher' => 'Het Vaderland (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1908-10-01',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB23:001477002:mpeg21:a00002',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève de la rubrique « Uit verschillende landen ».',
        ],
        'petit-journal-1908-10-01' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 1er octobre 1908, page 3',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1908-10-01',
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Septembre',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 30 septembre 1908, deux express de sens contraire, l\'un venant de Bédarieux et l\'autre de Castres, se sont rencontrés sur la voie unique de la ligne de Castres à Bédarieux, à Ardouane près de Riols (Hérault) selon Het Vaderland.',
                'en' => 'On 30 September 1908, two express trains running in opposite directions, one from Bédarieux and the other from Castres, met on the single track of the Castres to Bédarieux line, at Ardouane near Riols (Hérault) according to Het Vaderland.',
                'de' => 'Am 30. September 1908 trafen zwei Schnellzüge in Gegenrichtung, einer aus Bédarieux und der andere aus Castres, auf dem eingleisigen Abschnitt der Strecke Castres-Bédarieux zusammen, laut Het Vaderland in Ardouane bei Riols (Hérault).',
                'sources' => ['vaderland-1908-10-01', 'wp-liste-1908'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia attribue la rencontre à une erreur du chef de gare de Saint-Étienne-d\'Albagnan.',
                'en' => 'The Wikipedia list attributes the meeting to an error by the station master of Saint-Étienne-d\'Albagnan.',
                'de' => 'Die Wikipedia-Liste führt das Zusammentreffen auf einen Fehler des Bahnhofsvorstehers von Saint-Étienne-d\'Albagnan zurück.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Het Vaderland compte une personne tuée, un chef de train, et quatre blessés graves, les deux mécaniciens, un chauffeur et un médecin ; quinze autres voyageurs étaient plus ou moins gravement contusionnés. La liste de Wikipédia donne deux morts et vingt blessés.',
                'en' => 'Het Vaderland counts one person killed, a head guard, and four seriously injured, the two drivers, a fireman and a doctor; fifteen other passengers were more or less badly bruised. The Wikipedia list gives two dead and twenty injured.',
                'de' => 'Het Vaderland zählt einen Toten, einen Zugführer, und vier Schwerverletzte, die beiden Lokführer, einen Heizer und einen Arzt; fünfzehn weitere Reisende waren mehr oder weniger schwer geprellt. Die Wikipedia-Liste nennt zwei Tote und zwanzig Verletzte.',
                'sources' => ['vaderland-1908-10-01', 'wp-liste-1908'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de morts est de un dans la première brève et de deux dans la liste de Wikipédia.',
                'en' => 'The number of dead is one in the first brief and two in the Wikipedia list.',
                'de' => 'Die Zahl der Toten beträgt in der ersten Meldung eins und in der Wikipedia-Liste zwei.',
                'sources' => ['vaderland-1908-10-01', 'wp-liste-1908'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['vaderland-1908-10-01', 'wp-liste-1908'],
            'fr' => 'Deux express de sens contraire se sont rencontrés sur la voie unique : il s\'agit d\'une collision.',
            'en' => 'Two express trains running in opposite directions met on the single track: it was a collision.',
            'de' => 'Zwei Schnellzüge in Gegenrichtung trafen auf dem eingleisigen Abschnitt zusammen: Es handelt sich um einen Zusammenstoß.',
        ],
    ],
];
