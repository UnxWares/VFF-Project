<?php

// Galerie de médias d'une ligne (photos, cartes postales, plans, archives).
// Médias externes affichés par lien direct : chaque fichier garde SA licence.
return [
    'title' => 'Photos & archives',
    'subtitle' => 'Photographies, cartes postales, plans et documents d’archive de la ligne.',
    'loading' => 'Chargement des images…',
    'error' => 'Impossible de charger les images pour le moment.',
    'retry' => 'Réessayer',
    'empty_filtered' => 'Aucune image ne correspond à ces filtres.',
    'load_more' => 'Afficher plus d’images',
    'shown' => ':shown sur :total',

    'sort' => [
        'label' => 'Trier',
        'oldest' => 'Les plus anciennes',
        'newest' => 'Les plus récentes',
    ],

    'filter' => [
        'all' => 'Toutes',
        'type' => 'Type de document',
        'period' => 'Époque représentée',
    ],

    'types' => [
        'photo' => 'Photos',
        'postcard' => 'Cartes postales',
        'map' => 'Cartes',
        'plan' => 'Plans & schémas',
        'document' => 'Documents',
    ],

    'periods' => [
        'pre1900' => 'Avant 1900',
        '1900_1944' => '1900-1944',
        '1945_1969' => '1945-1969',
        '1970_1999' => '1970-1999',
        'since2000' => 'Depuis 2000',
        'undated' => 'Non datées',
    ],

    'date' => [
        'unknown' => 'Date inconnue',
        'circa' => 'vers :year',
        'before' => 'avant :year',
        'after' => 'après :year',
        'decade' => ':year-:end',
        'range' => ':from-:to',
        'approx' => ':year (env.)',
    ],

    'rights' => [
        'public_domain' => 'Domaine public',
        'open_license' => 'Licence libre',
        'permission' => 'Reproduit avec autorisation',
        'unknown' => 'Licence non précisée (voir la source)',
    ],

    'author_unknown' => 'Auteur inconnu',
    'view_source' => 'Voir le fichier sur :provider',
    'via' => 'via :provider',
    'share_alike' => 'Partage dans les mêmes conditions',
    'no_commercial' => 'Pas d’usage commercial',
    'hosted_notice' => 'Images hébergées par :provider et affichées par lien direct : VFF ne les copie pas. Chaque image conserve sa propre licence ; réutilisation possible en respectant l’auteur, la licence et le lien indiqués.',
    'open' => 'Agrandir : :title',
    'file_name' => 'Fichier : :name',

    'lightbox' => [
        'close' => 'Fermer',
        'previous' => 'Image précédente',
        'next' => 'Image suivante',
        'counter' => ':current / :total',
        'open_original' => 'Ouvrir l’image en grand',
    ],
];
