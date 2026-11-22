<?php

// Contexte sourcé de la collision de trains près d'Outreau, ligne d'Amiens à Boulogne, du 9 juin 1908 (recherche du 22/11/2026).
return [
    'slug' => '1908-06-09-outreau',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juin',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 9 juin 1908, d\'après Le Petit Parisien du 10 juin : à Outreau, sur la ligne Amiens - Boulogne, vers 6 h, l\'arrière d\'un convoi en manœuvre à la sortie de l\'embranchement des aciéries est heurté par un train de marchandises venant de Longueau, dont le mécanicien et le chauffeur sont tués ; un serre-frein est grièvement blessé.',
        ],
        'harlinger-1908-06-14' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenlandsche berichten',
            'publisher' => 'Nieuwe Harlinger Courant',
            'author' => null,
            'published' => '1908-06-14',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMTRES04:211297079:mpeg21:a00028',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; brève : à Outreau, près d\'Arras, un train de marchandises est entré en collision avec un train de voyageurs ; le machiniste et le chauffeur du train de marchandises sont tués, le conducteur blessé.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 9 juin 1908, vers 6 h, à Outreau, sur la ligne d\'Amiens à Boulogne, un train de marchandises venant de Longueau a heurté un autre convoi. Selon la liste de Wikipédia, il s\'agissait de l\'arrière d\'un convoi en manœuvre à la sortie de l\'embranchement des aciéries.',
                'en' => 'On 9 June 1908, at about 6 am, at Outreau on the Amiens to Boulogne line, a goods train from Longueau ran into another train. According to the Wikipedia list it was the rear of a train shunting at the exit of the steelworks siding.',
                'de' => 'Am 9. Juni 1908 gegen 6 Uhr fuhr in Outreau auf der Strecke von Amiens nach Boulogne ein Güterzug aus Longueau auf einen anderen Zug auf. Nach der Wikipedia-Liste war es das Ende eines Zugs, der am Ausgang des Anschlussgleises des Stahlwerks rangierte.',
                'sources' => ['wp-liste-1908', 'harlinger-1908-06-14'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur du train de marchandises ont été tués. Un serre-frein est grièvement blessé selon la liste de Wikipédia, le conducteur est blessé selon la presse néerlandaise.',
                'en' => 'The driver and the fireman of the goods train were killed. A brakeman was seriously injured according to the Wikipedia list; the guard was injured according to the Dutch press.',
                'de' => 'Der Lokführer und der Heizer des Güterzugs wurden getötet. Ein Bremser wurde nach der Wikipedia-Liste schwer verletzt, der Zugführer nach der niederländischen Presse verletzt.',
                'sources' => ['wp-liste-1908', 'harlinger-1908-06-14'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La brève néerlandaise dit que le train de marchandises a heurté un train de voyageurs et situe Outreau près d\'Arras, alors que la liste de Wikipédia parle d\'un convoi en manœuvre et place Outreau sur la ligne d\'Amiens à Boulogne.',
                'en' => 'The Dutch brief says the goods train ran into a passenger train and puts Outreau near Arras, whereas the Wikipedia list speaks of a shunting train and places Outreau on the Amiens to Boulogne line.',
                'de' => 'Die niederländische Meldung sagt, der Güterzug sei auf einen Personenzug aufgefahren, und verlegt Outreau in die Nähe von Arras, während die Wikipedia-Liste von einem rangierenden Zug spricht und Outreau an der Strecke Amiens-Boulogne ansiedelt.',
                'sources' => ['wp-liste-1908', 'harlinger-1908-06-14'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1908', 'harlinger-1908-06-14'],
            'fr' => 'Deux morts (le mécanicien et le chauffeur) selon la liste de Wikipédia (Le Petit Parisien du 10 juin 1908) et la brève néerlandaise ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two dead (the driver and the fireman) according to the Wikipedia list (Le Petit Parisien of 10 June 1908) and the Dutch brief; the record gave no number of deaths.',
            'de' => 'Zwei Tote (Lokführer und Heizer) nach der Wikipedia-Liste (Le Petit Parisien vom 10. Juni 1908) und der niederländischen Meldung; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
