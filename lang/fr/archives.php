<?php

// Documents de Gallica (BnF) rattachés aux lignes (vff:import-gallica) : section « Dans les archives » de la fiche ligne.
// Seules les métadonnées des notices de la BnF sont reprises (Licence Ouverte), avec le lien vers Gallica.
return [
    'line' => [
        'title' => 'Dans les archives',
        'intro' => 'Documents numérisés dans Gallica, la bibliothèque numérique de la BnF, dont le titre désigne cette ligne. Chaque lien ouvre le document sur le site de la BnF.',
        'undated' => 'Sans date',
        'circa' => 'vers :year',
        'before' => 'avant :year',
        'after' => 'après :year',
        'types' => [
            'monograph' => 'Texte imprimé',
            'map' => 'Carte ou plan',
            'image' => 'Image',
            'manuscript' => 'Manuscrit',
        ],
        'shelfmark_one' => 'cote :shelfmark',
        'shelfmark_other' => 'cotes :shelfmark',
        'other_views' => 'Autres vues :',
        'view' => 'vue :number',
        'intro_both' => 'Documents et notices d’archives dont le titre désigne cette ligne. Chaque lien ouvre le document sur le site de l’institution qui le conserve.',
        'gallica_heading' => 'Gallica (BnF)',
        'gallica_hint' => 'Gallica',
        'show_more_one' => 'Afficher l’autre document',
        'show_more_other' => 'Afficher les :count autres documents',
        'show_less' => 'Afficher moins de documents',
        'method' => 'Un document est retenu quand son titre relie deux lieux de la ligne (« chemin de fer de X à Y »), ou quand il nomme ses deux extrémités sans citer de lieu situé hors de la ligne. Il doit aussi parler de chemin de fer et porter une date compatible avec l’histoire de la ligne. La presse n’est pas encore consultée.',
        'source' => 'Source : gallica.bnf.fr / BnF. Notices du catalogue général de la Bibliothèque nationale de France (Licence Ouverte), récupérées le :date.',
    ],
    // Notices des Archives SNCF rattachées aux lignes (vff:import-sncf-archives), sous les documents de Gallica.
    'records' => [
        'heading' => 'Archives du groupe SNCF',
        'intro' => 'Dossiers, photographies et films des archives du groupe SNCF dont le titre désigne cette ligne. Chaque lien ouvre la notice sur archives.sncf.com.',
        'types' => [
            'document' => 'Dossier d’archives',
            'image' => 'Photographie ou affiche',
            'video' => 'Film',
        ],
        'reference' => 'dossier :reference',
        'hint' => 'archives.sncf.com',
        'method' => 'Une notice est retenue quand son titre relie deux lieux de la ligne (« située sur la ligne du Mans à Mézidon ») ou nomme ses deux extrémités, et que sa date convient à l’histoire de la ligne. Un nom de compagnie (« Compagnie du chemin de fer de Paris à Orléans ») ne désigne pas une ligne. Les notices qui portent sur des agents ne sont pas reprises.',
        'source' => 'Source : SNCF, Service Archives Documentation (jeu de données « Archives SNCF », licence ODbL), récupéré le :date.',
    ],
];
