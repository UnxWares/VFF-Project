<?php

/*
 * Modération des comptes : suspension par un administrateur, levée, messages au membre.
 * Engagements des CGU (« Validation et modération ») et de la politique de confidentialité :
 * toute évolution doit rester cohérente avec elles. Texte au vouvoiement.
 */

return [
    'errors' => [
        'suspended' => 'Votre compte est suspendu : vous ne pouvez plus proposer de contribution, relire ni signaler d’erreur. Votre profil reste accessible, avec le motif et vos données.',
        'suspended_until' => 'Votre compte est suspendu jusqu’au :date inclus : vous ne pouvez plus proposer de contribution, relire ni signaler d’erreur. Votre profil reste accessible, avec le motif et vos données.',
        'self' => 'Vous ne pouvez pas suspendre votre propre compte.',
        'last_admin' => 'Ce compte est le dernier administrateur en mesure d’agir : le suspendre laisserait le site sans personne pour attribuer les rôles ou lever une suspension.',
        'already_suspended' => 'Ce compte est déjà suspendu. Levez d’abord la suspension pour en poser une autre.',
        'not_suspended' => 'Ce compte n’est pas suspendu.',
        'reason_required' => 'Indiquez le motif de la suspension (:min caractères au moins) : il est communiqué au membre.',
        'reason_max' => 'Le motif ne peut pas dépasser :max caractères.',
        'until_format' => 'La date de fin n’est pas valide.',
        'until_future' => 'La date de fin doit être postérieure à aujourd’hui.',
    ],

    'flash' => [
        'suspended' => 'Le compte de :name est suspendu. Le membre en est informé sur le site et par courriel.',
        'lifted' => 'La suspension du compte de :name est levée. Le membre en est informé.',
    ],

    // Notification du site et courriel envoyés au membre.
    'notification' => [
        'suspended' => [
            'title' => 'Votre compte est suspendu',
            'indefinite' => 'Un administrateur a suspendu votre compte. La suspension dure jusqu’à ce qu’il la lève.',
            'until' => 'Un administrateur a suspendu votre compte jusqu’au :date inclus.',
            'effect' => 'Vous ne pouvez plus proposer de contribution, relire ni signaler d’erreur. Vous pouvez toujours consulter le site, télécharger vos données et supprimer votre compte depuis votre profil.',
        ],
        'lifted' => [
            'title' => 'La suspension de votre compte est levée',
            'body' => 'Vous pouvez de nouveau proposer des contributions, relire si votre rôle le permet, et signaler des erreurs.',
        ],
        'contest' => 'Pour contester cette décision, écrivez à contact@vff-project.org : elle sera réexaminée, autant que possible par une autre personne que celle qui l’a prise.',
    ],
    'mail' => [
        'action' => 'Ouvrir mon profil',
    ],

    // Encart du profil d’un membre suspendu.
    'profile' => [
        'title' => 'Votre compte est suspendu',
        'indefinite' => 'La suspension dure jusqu’à ce qu’un administrateur la lève.',
        'until' => 'La suspension dure jusqu’au :date inclus.',
        'since' => 'Elle a commencé le :date.',
        'reason' => 'Motif : :reason',
        'effect' => 'Vous ne pouvez plus proposer de contribution, relire ni signaler d’erreur. Vos données restent accessibles ci-dessous : vous pouvez les télécharger ou supprimer votre compte.',
        'contest' => 'Pour contester cette décision, écrivez à contact@vff-project.org.',
    ],

    // Fenêtre « Signaler une erreur » ouverte par un membre suspendu.
    'report' => [
        'suspended' => 'Votre compte est suspendu : vous ne pouvez pas envoyer de signalement. Le motif figure dans votre profil.',
        'profile_link' => 'Ouvrir mon profil',
    ],

    // Espace de modération, onglet « Comptes » (administrateurs).
    'admin' => [
        'meta_title' => 'Comptes',
        'title' => 'Comptes',
        'subtitle' => 'Suspendez un compte qui enfreint les conditions d’utilisation, ou levez une suspension. Le membre reçoit le motif sur le site et par courriel.',
        'nav_link' => 'Comptes',
        'search_label' => 'Rechercher un compte',
        'search_hint' => 'Nom ou adresse électronique.',
        'search_button' => 'Rechercher',
        'filter_label' => 'Afficher les comptes',
        'filters' => [
            'all' => 'Tous les comptes',
            'suspended' => 'Suspendus',
        ],
        'list_label' => 'Comptes',
        'empty' => [
            'all' => 'Aucun compte ne correspond à cette recherche.',
            'suspended' => 'Aucun compte n’est suspendu.',
        ],
        'member_since' => 'Inscrit le :date',
        'contributions' => 'Contributions : :count',
        'badge_suspended' => 'Suspendu',
        'suspended_since' => 'Suspendu le :date',
        'until' => 'jusqu’au :date inclus',
        'indefinite' => 'jusqu’à la levée',
        'by' => 'par :name',
        'reason' => 'Motif',
        'lift' => 'Lever la suspension',
        'suspend_open' => 'Suspendre ce compte',
        'reason_label' => 'Motif de la suspension',
        'reason_hint' => 'Communiqué au membre, sur le site et par courriel. :min caractères au moins.',
        'until_label' => 'Dernier jour de la suspension',
        'until_hint' => 'Facultatif. Sans date, la suspension dure jusqu’à ce que vous la leviez.',
        'submit' => 'Suspendre le compte',
        'self_note' => 'Votre compte',
    ],
];
