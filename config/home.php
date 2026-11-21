<?php

return [
    /*
    | Section « Le rail en images » de l'accueil.
    |
    | rotation_minutes : durée d'une fenêtre fixe. Un nouveau tirage par fenêtre, le même pour tous les
    | visiteurs (graine = début de la fenêtre), sans reprendre d'image de la fenêtre précédente quand la
    | réserve le permet. 30 minutes par défaut.
    */
    'archives' => [
        'rotation_minutes' => (int) env('HOME_ARCHIVES_ROTATION_MINUTES', 30),
        'count' => 7,
    ],
];
