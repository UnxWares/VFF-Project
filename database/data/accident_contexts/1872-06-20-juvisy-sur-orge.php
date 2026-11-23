<?php

// Contexte sourcé de la collision de Juvisy du 20 juin 1872 (source repérée le 21/11/2026 par l'agent accidents-ddb-1,
// texte relu le 22/11/2026). Fiche à créer : aucune source de l'import ne la décrit (voir accidents-decisions.md).
return [
    'slug' => '1872-06-20-juvisy-sur-orge',
    'checked_on' => '2026-11-22',
    'sources' => [
        'delpher-1872-06-24-arnhemsche' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengde berigten',
            'publisher' => 'Arnhemsche courant  (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1872-06-24',
            'locator' => 'p. 2',
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB08:000090706:mpeg21:a0006',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Brève : le 20, à Juvisy, le train d\'Orléans à Paris est entré en collision avec un train de marchandises ; la locomotive et le tender ont déraillé, le train a pris feu, cinq personnes sont mortes dans les flammes, vingt à trente sont plus ou moins gravement blessées (texte OCR, chiffre des blessés peu lisible).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 20 juin 1872, à Juvisy, le train d\'Orléans à Paris est entré en collision avec un train de marchandises.',
                'en' => 'On 20 June 1872, at Juvisy, the Orléans to Paris train collided with a goods train.',
                'de' => 'Am 20. Juni 1872 stieß in Juvisy der Zug von Orléans nach Paris mit einem Güterzug zusammen.',
                'sources' => ['delpher-1872-06-24-arnhemsche'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive et le tender ont déraillé, et le train a pris feu.',
                'en' => 'The locomotive and the tender derailed, and the train caught fire.',
                'de' => 'Lokomotive und Tender entgleisten, und der Zug geriet in Brand.',
                'sources' => ['delpher-1872-06-24-arnhemsche'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Cinq personnes sont mortes dans les flammes. Une vingtaine à une trentaine d\'autres ont été plus ou moins gravement blessées.',
                'en' => 'Five people died in the flames. Some twenty to thirty others were more or less seriously injured.',
                'de' => 'Fünf Menschen kamen in den Flammen ums Leben. Etwa zwanzig bis dreißig weitere wurden mehr oder weniger schwer verletzt.',
                'sources' => ['delpher-1872-06-24-arnhemsche'],
            ],
        ],
    ],
];
