<?php

// Contexte sourcé de de la collision entre un train de voyageurs et un poids lourd au passage à niveau n° 71 de Millau (Aveyron) de novembre 2004 (recherche du 23/11/2026).
return [
    'slug' => '2004-11-21-millau',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2004-11-21-millau' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Passage à niveau RN à Millau',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/millau-12-24-11-2004-collision-entre-un-train-de-r50.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un train Grande Ligne de Paris à Béziers a percuté un poids lourd immobilisé sur le passage à niveau n° 71. Le bilan est de trois blessés légers et les dégâts matériels sont importants.',
                'en' => 'A main-line train from Paris to Béziers hit a lorry stopped on level crossing no. 71. The toll is three slightly injured and the material damage is considerable.',
                'de' => 'Ein Fernzug von Paris nach Béziers prallte auf einen auf dem Bahnübergang Nr. 71 stehenden Lkw. Die Bilanz lautet drei Leichtverletzte, und die Sachschäden sind erheblich.',
                'sources' => ['bea-tt-2004-11-21-millau'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 31 mai 2006, puis les recommandations et les réponses de la ville de Millau, de l\'Instance PN, de la DSCR et du Sétra le 17 novembre 2009.',
                'en' => 'The BEA-TT published its final report on 31 May 2006, then the recommendations and the replies of the town of Millau, the Instance PN, the DSCR and the Sétra on 17 November 2009.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 31. Mai 2006, dann am 17. November 2009 die Empfehlungen und die Antworten der Stadt Millau, der Instance PN, der DSCR und des Sétra.',
                'sources' => ['bea-tt-2004-11-21-millau'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le BEA-TT date l\'accident du 24 novembre 2004 dans le titre de sa page et dans celui de la synthèse anglaise, alors que la fiche est datée du 21 novembre (le titre du rapport donne même le 24 octobre) ; la date de la fiche est à vérifier avant d\'être corrigée.',
                'en' => 'The BEA-TT dates the accident to 24 November 2004 in the title of its page and of the English summary, whereas the record is dated 21 November (the report title even gives 24 October); the record\'s date needs checking before it is corrected.',
                'de' => 'Die BEA-TT datiert den Unfall im Titel ihrer Seite und der englischen Zusammenfassung auf den 24. November 2004, während der Datensatz den 21. November nennt (der Berichtstitel nennt sogar den 24. Oktober); das Datum des Datensatzes ist zu prüfen, bevor es korrigiert wird.',
                'sources' => ['bea-tt-2004-11-21-millau'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 3,
            'sources' => ['bea-tt-2004-11-21-millau'],
            'fr' => 'Le BEA-TT compte trois blessés légers.',
            'en' => 'The BEA-TT counts three slightly injured.',
            'de' => 'Die BEA-TT zählt drei Leichtverletzte.',
        ],
    ],
];
