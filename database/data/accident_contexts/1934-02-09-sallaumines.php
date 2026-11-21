<?php

// Contexte sourcé de la collision entre un train de marchandises et un train de mineurs près de Lens en février 1934 (recherche du 21/11/2026).
return [
    'slug' => '1934-02-09-sallaumines',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-lens-a' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk bij Lens. Twee dooden, vijftien gewonden',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1934-02-09',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010015200:mpeg21:a0045',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-lens-b' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenland. Een spoorwegongeluk bij Lens. Drie dooden en veertig gewonden',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1934-02-09',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:011171415:mpeg21:a0083',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-lens-c' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Drie dooden bij een treinbotsing',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1934-02-09',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB04:000148648:mpeg21:a0015',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1934' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1934',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Février',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1934&oldid=233504363#Février',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : train de mineurs en attente du départ percuté à Sallaumines par une machine haut-le-pied, un mort et quarante blessés ; cite Le Temps du 10 février 1934.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Près de la gare de Lens, un train de marchandises a heurté un train de voyageurs transportant des mineurs, selon la presse néerlandaise qui le dit venu de Lille. La liste de Wikipédia situe l\'accident en gare de Sallaumines, sur la ligne de Lens à Don-Sainghin, et décrit un train de mineurs en attente du départ, percuté par une machine haut-le-pied circulant tender en avant, dont le mécanicien n\'avait pas vu un signal d\'arrêt.',
                'en' => 'Near Lens station, a goods train ran into a passenger train carrying miners, according to the Dutch press, which says it came from Lille. The Wikipedia list places the accident at Sallaumines station, on the Lens to Don-Sainghin line, and describes a miners\' train waiting to depart, hit by a light engine running tender first whose driver had not seen a stop signal.',
                'de' => 'Nahe dem Bahnhof Lens fuhr ein Güterzug auf einen Reisezug mit Bergleuten auf, der nach der niederländischen Presse aus Lille kam. Die Wikipedia-Liste verortet den Unfall im Bahnhof Sallaumines an der Strecke Lens-Don-Sainghin und beschreibt einen auf die Abfahrt wartenden Bergarbeiterzug, auf den eine mit dem Tender voraus fahrende Leerlokomotive auffuhr, deren Lokführer ein Haltesignal nicht gesehen hatte.',
                'sources' => ['presse-lens-a', 'presse-lens-c', 'wp-liste-1934'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le dernier wagon du train de mineurs a été entièrement détruit, d\'après la presse néerlandaise. La liste de Wikipédia précise que le choc a éventré la dernière voiture et projeté la suivante hors de la voie.',
                'en' => 'The last wagon of the miners\' train was completely destroyed, according to the Dutch press. The Wikipedia list adds that the impact tore open the last carriage and threw the next one off the track.',
                'de' => 'Der letzte Wagen des Bergarbeiterzugs wurde nach der niederländischen Presse völlig zerstört. Die Wikipedia-Liste ergänzt, dass der Aufprall den letzten Wagen aufriss und den vorletzten aus dem Gleis warf.',
                'sources' => ['presse-lens-a', 'presse-lens-c', 'wp-liste-1934'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les premiers comptes rendus donnent deux mineurs tués et quinze blessés, puis trois morts et quarante blessés dont plusieurs grièvement. La liste de Wikipédia compte un mort et quarante blessés.',
                'en' => 'The first reports give two miners killed and fifteen injured, then three dead and forty injured, several of them seriously. The Wikipedia list counts one dead and forty injured.',
                'de' => 'Die ersten Berichte nennen zwei getötete Bergleute und fünfzehn Verletzte, dann drei Tote und vierzig Verletzte, mehrere davon schwer. Die Wikipedia-Liste zählt einen Toten und vierzig Verletzte.',
                'sources' => ['presse-lens-a', 'presse-lens-b', 'presse-lens-c', 'wp-liste-1934'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de morts varie : deux (première dépêche), trois (dépêches suivantes) ou un (liste de Wikipédia). La date aussi : les quotidiens néerlandais datés du 9 février publient une dépêche du 8 février, l\'un d\'eux parlant d\'un accident « hier », alors que la liste de Wikipédia donne le 9 février vers 14 h. Les journaux néerlandais disent la rame venue de Lille, la liste de Wikipédia un train en attente du départ.',
                'en' => 'The number of dead varies: two (first dispatch), three (later dispatches) or one (Wikipedia list). So does the date: Dutch dailies dated 9 February publish a dispatch of 8 February, one of them speaking of an accident "yesterday", whereas the Wikipedia list gives 9 February at about 2 pm. The Dutch newspapers say the train came from Lille, the Wikipedia list a train waiting to depart.',
                'de' => 'Die Zahl der Toten schwankt: zwei (erste Depesche), drei (spätere Depeschen) oder einer (Wikipedia-Liste). Das Datum auch: Niederländische Tageszeitungen vom 9. Februar bringen eine Depesche vom 8. Februar, eine davon spricht von einem Unfall „gestern“, während die Wikipedia-Liste den 9. Februar gegen 14 Uhr nennt. Die niederländischen Zeitungen sagen, der Zug sei aus Lille gekommen, die Wikipedia-Liste einen auf die Abfahrt wartenden Zug.',
                'sources' => ['presse-lens-a', 'presse-lens-b', 'presse-lens-c', 'wp-liste-1934'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
