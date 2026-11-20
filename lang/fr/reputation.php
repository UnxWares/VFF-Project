<?php

/*
 * Réputation des membres : page d’aide, profil, espace de validation.
 * Les niveaux et leurs seuils sont dans config/reputation.php (clés de `levels` identiques).
 * Toute évolution doit rester cohérente avec la politique de confidentialité (privacy.php).
 */

return [
    'meta_title' => 'La réputation des membres',
    'meta_description' => 'Ce que comptent les chiffres de réputation sur VFF, les seuils de chaque niveau et ce qu’ils changent.',

    'hero' => [
        'title' => 'La réputation des membres',
        'subtitle' => 'Trois chiffres et un niveau, calculés de la même façon pour tout le monde. Rien d’autre n’entre en compte.',
    ],

    'figures' => [
        'title' => 'Ce que l’on compte',
        'intro' => 'Les chiffres viennent des contributions et des relectures enregistrées sur le site. Ils sont recalculés après chaque décision de validation.',
        'applied' => [
            'title' => 'Contributions acceptées',
            'text' => 'Les contributions approuvées par deux validateurs, puis publiées dans l’encyclopédie.',
        ],
        'rejected' => [
            'title' => 'Contributions refusées',
            'text' => 'Les contributions qu’un validateur a refusées, avec son motif. Une contribution retirée par un administrateur (spam, contenu illicite) n’est comptée nulle part.',
        ],
        'reviews' => [
            'title' => 'Relectures faites',
            'text' => 'Les approbations et les refus décidés par un validateur. Relire sa propre contribution est impossible.',
        ],
        'rate' => [
            'title' => 'Taux d’acceptation',
            'text' => 'Contributions acceptées divisées par celles qui ont reçu une décision (acceptées plus refusées), arrondi à l’entier inférieur. Une contribution encore en attente n’y figure pas.',
        ],
    ],

    // Libellés des niveaux (mêmes clés que config/reputation.php).
    'levels' => [
        'newcomer' => 'Nouveau membre',
        'contributor' => 'Contributeur',
        'regular' => 'Contributeur régulier',
        'experienced' => 'Contributeur confirmé',
    ],

    'thresholds' => [
        'title' => 'Les niveaux',
        'intro' => 'Chaque niveau a ses seuils, écrits dans la configuration du site. Un membre a le niveau le plus élevé dont tous les seuils sont atteints. Le niveau sert de repère aux validateurs. Il ne donne aucun droit : la relecture reste la même pour tout le monde, avec deux approbations avant publication.',
        'everyone' => 'Tout membre, dès l’inscription.',
        'requires' => 'Il faut :conditions.',
        'applied_one' => 'au moins :count contribution acceptée',
        'applied_other' => 'au moins :count contributions acceptées',
        'reviews_one' => 'au moins :count relecture faite',
        'reviews_other' => 'au moins :count relectures faites',
        'acceptance' => 'un taux d’acceptation d’au moins :count %',
        'rate_note' => 'Le taux d’acceptation n’est vérifié qu’à partir du moment où une contribution a reçu une décision.',
    ],

    'rules' => [
        'title' => 'Quelques précisions',
        'who_title' => 'Qui voit ces chiffres',
        'who_text' => 'Vous voyez les vôtres dans votre profil. Les validateurs voient ceux de l’auteur, avec son niveau, à côté de son nom quand ils relisent une contribution. Il n’existe pas de profil public, et la réputation ne figure pas dans l’API.',
        'suspended_title' => 'Compte suspendu',
        'suspended_text' => 'Un compte suspendu garde ses chiffres, mais aucun niveau n’est affiché tant que la suspension dure.',
        'deleted_title' => 'Compte supprimé',
        'deleted_text' => 'Les chiffres disparaissent avec le compte. Les contributions publiées restent, sans nom : un validateur voit alors « Contributeur anonyme » et aucun chiffre.',
        'recalc_title' => 'Recalcul',
        'recalc_text' => 'Les chiffres sont recalculés à chaque décision de validation. Un administrateur peut aussi tout recalculer d’un coup, avec le même résultat : il ne dépend que des contributions et des relectures enregistrées.',
        'export_title' => 'Vos données',
        'export_text' => 'Vos chiffres figurent dans l’export de vos données depuis votre profil.',
        'privacy_link' => 'Politique de confidentialité',
    ],

    // Profil du membre.
    'profile' => [
        'title' => 'Réputation',
        'level' => 'Niveau',
        'applied' => 'Contributions acceptées',
        'rejected' => 'Contributions refusées',
        'reviews' => 'Relectures faites',
        'acceptance' => 'Taux d’acceptation',
        'percent' => ':count %',
        'level_hidden' => 'Aucun niveau n’est affiché tant que votre compte est suspendu. Vos chiffres sont conservés.',
        'next' => 'Pour atteindre le niveau « :level » : :conditions.',
        'missing_applied_one' => ':count contribution acceptée de plus',
        'missing_applied_other' => ':count contributions acceptées de plus',
        'missing_reviews_one' => ':count relecture de plus',
        'missing_reviews_other' => ':count relectures de plus',
        'missing_acceptance' => 'un taux d’acceptation d’au moins :count %',
        'help_link' => 'Comment la réputation est calculée',
    ],

    // Espace de validation : à côté du nom de l’auteur.
    'validate' => [
        'by' => 'par :name',
        'applied_one' => ':count acceptée',
        'applied_other' => ':count acceptées',
        'rejected_one' => ':count refusée',
        'rejected_other' => ':count refusées',
    ],
];
