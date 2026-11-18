<?php

/**
 * Noms historiques et sections nommées des lignes, curés à la main d'après les articles
 * Wikipédia cités (rejoués par vff:import-line-names, étape de vff:rebuild-history).
 *
 * Reprend et date les sections de l'ancien Database\Seeders\LineSectionsSeeder, qui
 * n'était appelé ni par DatabaseSeeder ni par le pipeline : la table line_sections est
 * restée vide depuis la recréation de la base (15/10/2026), et la fiche de la LGV Sud-Est
 * avait perdu « LGV Paris - Lyon », « LN1 »…
 *
 * Par code RFN :
 *   - names    : autres noms de la ligne [name, from (année), to (année), source_url] ;
 *   - sections : sections nommées [name, alt_names, pk_start_m, pk_end_m, opening_year_from,
 *                opening_year_to, description, source_url] (PK de l'ancien seeder).
 */
return [
    // LGV Paris - Sud-Est : LN1 (Paris - Lyon), LN4 (Rhône-Alpes), LN5 (Méditerranée).
    '752000' => [
        'names' => [
            ['name' => 'LGV Sud-Est', 'from' => 1981, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est'],
            ['name' => 'LGV Paris - Lyon', 'from' => 1981, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est'],
            ['name' => 'LN1', 'from' => 1981, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est'],
        ],
        'sections' => [
            [
                'name' => 'LGV Sud-Est',
                'alt_names' => ['LN1', 'LGV Paris - Lyon'],
                'pk_start_m' => 0, 'pk_end_m' => 409000,
                'opening_year_from' => 1981, 'opening_year_to' => 1983,
                'description' => 'Première LGV française, de Combs-la-Ville à la bifurcation de Montanay, au nord de Lyon : section sud (Saint-Florentin - Sathonay) mise en service le 27 septembre 1981, section nord (Combs-la-Ville - Saint-Florentin) le 25 septembre 1983. Signalisation en cabine TVM 300.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est',
            ],
            [
                'name' => 'LGV Rhône-Alpes',
                'alt_names' => ['LN4'],
                'pk_start_m' => 409000, 'pk_end_m' => 498000,
                'opening_year_from' => 1992, 'opening_year_to' => 1994,
                'description' => 'Contournement de l’agglomération lyonnaise, de Montanay à Saint-Marcel-lès-Valence : jusqu’à Saint-Quentin-Fallavier le 13 décembre 1992, puis jusqu’à Valence le 3 juillet 1994. Signalisation en cabine TVM 430.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Rh%C3%B4ne-Alpes',
            ],
            [
                'name' => 'LGV Méditerranée',
                'alt_names' => ['LN5'],
                'pk_start_m' => 498000, 'pk_end_m' => 711000,
                'opening_year_from' => 2001, 'opening_year_to' => 2001,
                'description' => 'De Saint-Marcel-lès-Valence à Marseille, mise en service le 10 juin 2001. Signalisation en cabine TVM 430.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_M%C3%A9diterran%C3%A9e',
            ],
        ],
    ],

    // LGV Atlantique (LN2) : tronc commun Paris - Courtalain et branche Sud-Ouest vers Tours.
    '431000' => [
        'names' => [
            ['name' => 'LGV Atlantique', 'from' => 1989, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique'],
            ['name' => 'LN2', 'from' => 1989, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique'],
        ],
        'sections' => [
            [
                'name' => 'LGV Atlantique, tronc commun',
                'alt_names' => ['LN2'],
                'pk_start_m' => 0, 'pk_end_m' => 130000,
                'opening_year_from' => 1989, 'opening_year_to' => 1989,
                'description' => 'De Paris-Montparnasse à Courtalain, où se séparent les branches vers la Bretagne et vers le Sud-Ouest. Signalisation en cabine TVM 300.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique',
            ],
            [
                'name' => 'LGV Atlantique, branche Sud-Ouest',
                'alt_names' => ['LN2'],
                'pk_start_m' => 130000, 'pk_end_m' => 282000,
                'opening_year_from' => 1990, 'opening_year_to' => 1990,
                'description' => 'De Courtalain à Monts, vers Tours et le Sud-Ouest. Signalisation en cabine TVM 300.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique',
            ],
        ],
    ],

    // LGV Atlantique, branche Ouest (Courtalain - Connerré, vers Le Mans).
    '429000' => [
        'names' => [
            ['name' => 'LGV Atlantique, branche Ouest', 'from' => 1989, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique'],
            ['name' => 'LN2', 'from' => 1989, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Atlantique'],
        ],
    ],

    // LGV Est européenne : deux phases.
    '005000' => [
        'names' => [
            ['name' => 'LGV Est européenne', 'from' => 2007, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Est_europ%C3%A9enne'],
        ],
        'sections' => [
            [
                'name' => 'LGV Est européenne, première phase',
                'alt_names' => [],
                'pk_start_m' => 0, 'pk_end_m' => 300000,
                'opening_year_from' => 2007, 'opening_year_to' => 2007,
                'description' => 'De Vaires-sur-Marne (Île-de-France) à Baudrecourt (Moselle). Signalisation en cabine TVM 430 et ETCS.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Est_europ%C3%A9enne',
            ],
            [
                'name' => 'LGV Est européenne, seconde phase',
                'alt_names' => [],
                'pk_start_m' => 300000, 'pk_end_m' => 406000,
                'opening_year_from' => 2016, 'opening_year_to' => 2016,
                'description' => 'De Baudrecourt à Vendenheim (Bas-Rhin), vers Strasbourg.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Est_europ%C3%A9enne',
            ],
        ],
    ],

    // LGV Nord (LN3).
    '226000' => [
        'names' => [
            ['name' => 'LGV Nord', 'from' => 1993, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Nord'],
            ['name' => 'LN3', 'from' => 1993, 'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Nord'],
        ],
    ],
];
