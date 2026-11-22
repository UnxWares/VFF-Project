<?php

// Gallica (BnF) documents linked to lines (vff:import-gallica): "In the archives" section of the line page.
return [
    'line' => [
        'title' => 'In the archives',
        'intro' => 'Documents digitised in Gallica, the digital library of the BnF (French National Library), whose title refers to this line. Each link opens the document on the BnF website.',
        'undated' => 'Undated',
        'circa' => 'c. :year',
        'before' => 'before :year',
        'after' => 'after :year',
        'types' => [
            'monograph' => 'Printed text',
            'map' => 'Map or plan',
            'image' => 'Image',
            'manuscript' => 'Manuscript',
        ],
        'shelfmark_one' => 'shelfmark :shelfmark',
        'shelfmark_other' => 'shelfmarks :shelfmark',
        'other_views' => 'Other views:',
        'view' => 'view :number',
        'intro_both' => 'Archive documents and records whose title refers to this line. Each link opens the document on the website of the institution that holds it.',
        'gallica_heading' => 'Gallica (BnF)',
        'gallica_hint' => 'Gallica',
        'show_more_one' => 'Show the other document',
        'show_more_other' => 'Show the other :count documents',
        'show_less' => 'Show fewer documents',
        'method' => 'A document is listed when its title links two places on the line (“railway from X to Y”), or names both of its ends and no place off the line. It must also mention the railway and have a date that fits the history of the line. Newspapers are not searched yet.',
        'source' => 'Source: gallica.bnf.fr / BnF. Catalogue records of the Bibliothèque nationale de France (Open Licence), retrieved on :date.',
    ],
    // Records from the SNCF archives linked to the lines (vff:import-sncf-archives), below the Gallica documents.
    'records' => [
        'heading' => 'SNCF group archives',
        'intro' => 'Files, photographs and films from the SNCF group archives whose title refers to this line. Each link opens the record on archives.sncf.com.',
        'types' => [
            'document' => 'Archive file',
            'image' => 'Photograph or poster',
            'video' => 'Film',
        ],
        'reference' => 'file :reference',
        'hint' => 'archives.sncf.com',
        'method' => 'A record is listed when its title links two places on the line (“on the line from Le Mans to Mézidon”) or names both of its ends, and its date fits the history of the line. A company name (“Compagnie du chemin de fer de Paris à Orléans”) does not refer to a line. Records about individual staff are left out.',
        'source' => 'Source: SNCF, Archives and Documentation Department (“Archives SNCF” dataset, ODbL licence), retrieved on :date.',
    ],
];
