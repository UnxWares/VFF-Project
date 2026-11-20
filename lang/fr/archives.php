<?php

// Documents de Gallica (BnF) rattachés aux lignes (vff:import-gallica) : section « Dans les archives » de la fiche ligne.
// Seules les métadonnées des notices de la BnF sont reprises (Licence Ouverte), avec le lien vers Gallica.
return [
    'line' => [
        'title' => 'Dans les archives',
        'intro' => 'Documents numérisés dans Gallica, la bibliothèque numérique de la BnF, dont le titre désigne cette ligne. Chaque lien ouvre le document sur le site de la BnF.',
        'undated' => 'Sans date',
        'circa' => 'vers :year',
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
        'gallica_hint' => 'Gallica',
        'show_more_one' => 'Afficher l’autre document',
        'show_more_other' => 'Afficher les :count autres documents',
        'show_less' => 'Afficher moins de documents',
        'method' => 'Un document est retenu quand son titre relie deux lieux de la ligne (« chemin de fer de X à Y »), ou quand il nomme ses deux extrémités sans citer de lieu situé hors de la ligne. Il doit aussi parler de chemin de fer et porter une date compatible avec l’histoire de la ligne. La presse n’est pas encore consultée.',
        'source' => 'Source : gallica.bnf.fr / BnF. Notices du catalogue général de la Bibliothèque nationale de France (Licence Ouverte), récupérées le :date.',
    ],
];
