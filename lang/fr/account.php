<?php

/*
 * Données du compte : export, suppression, comptes inactifs, information des membres.
 * Engagements de la politique de confidentialité (privacy.php) et des CGU (use_conditions.php) :
 * toute évolution de ces textes doit rester cohérente avec eux. Texte au vouvoiement.
 */

return [
    // Auteur d'une contribution publiée dont le compte a été supprimé.
    'anonymous_contributor' => 'Contributeur anonyme',

    // Section « Mes données » du profil.
    'data' => [
        'title' => 'Mes données',
        'intro' => 'Vous pouvez télécharger tout ce que VFF garde de votre compte, ou supprimer votre compte.',
        // Lien vers /privacy, à la suite de l’introduction.
        'privacy_link' => 'Vos droits sont détaillés dans la politique de confidentialité.',
        'export_title' => 'Télécharger mes données',
        'export_text' => 'Un fichier JSON avec votre profil, vos contributions, vos approbations et vos relectures.',
        'export_button' => 'Télécharger mes données',
        'delete_title' => 'Supprimer mon compte',
        'delete_text' => 'Votre profil (nom, adresse électronique, présentation, avatar) est effacé. Vos contributions publiées restent dans l’encyclopédie, attribuées à « Contributeur anonyme ». Vos contributions en attente ou refusées sont supprimées. Cette action est définitive.',
        'delete_button' => 'Supprimer mon compte',
        'delete_confirm_title' => 'Supprimer définitivement votre compte ?',
        'delete_confirm_text' => 'Saisissez votre mot de passe pour confirmer. Pensez à télécharger vos données avant : elles ne pourront plus être récupérées.',
        'delete_password_label' => 'Mot de passe',
        'delete_password_required' => 'Saisissez votre mot de passe pour confirmer la suppression.',
        'delete_confirm_button' => 'Supprimer définitivement',
        'delete_cancel' => 'Annuler',
    ],

    'flash' => [
        'deleted' => 'Votre compte a été supprimé. Vos contributions publiées restent dans l’encyclopédie, sans votre nom.',
        'contribution_removed' => 'La contribution a été retirée et son auteur prévenu.',
    ],

    'errors' => [
        'last_admin' => 'Vous êtes le seul administrateur : confiez ce rôle à un autre membre avant de supprimer votre compte.',
        'removal_published' => 'Cette contribution est déjà publiée : corrigez les données par une nouvelle contribution plutôt que de la retirer.',
    ],

    // Courriels de service (envoyés dans la langue choisie dans le profil).
    'mail' => [
        'greeting' => 'Bonjour :name,',
        'salutation' => 'L’équipe de Voies Ferrées de France',

        'inactive' => [
            'subject' => 'Votre compte Voies Ferrées de France sera supprimé le :date',
            'intro' => 'Vous ne vous êtes pas connecté à Voies Ferrées de France depuis bientôt 3 ans.',
            'deletion' => 'Comme l’annonce la politique de confidentialité, un compte inutilisé pendant 3 ans est supprimé. Le vôtre le sera le :date.',
            'keep' => 'Pour le garder, il suffit de vous connecter avant cette date.',
            'action' => 'Me connecter',
            'contributions' => 'Si votre compte est supprimé, vos contributions publiées resteront dans l’encyclopédie, sans votre nom. Les autres seront supprimées.',
        ],

        'removed' => [
            'subject' => 'Votre contribution n° :id a été retirée',
            'intro' => 'Votre contribution n° :id, envoyée le :date, a été retirée par l’équipe de Voies Ferrées de France.',
            'reason' => 'Motif : :reason',
            'contest' => 'Pour contester cette décision, écrivez à contact@vff-project.org : elle sera réexaminée, autant que possible par une autre personne que celle qui l’a prise.',
            'action' => 'Lire les conditions générales d’utilisation',
        ],

        'terms' => [
            'use-conditions' => [
                'subject' => 'Les conditions générales d’utilisation de Voies Ferrées de France changent',
                'intro' => 'Les conditions générales d’utilisation de Voies Ferrées de France changent. La nouvelle version entre en vigueur le :date.',
            ],
            'privacy' => [
                'subject' => 'La politique de confidentialité de Voies Ferrées de France change',
                'intro' => 'La politique de confidentialité de Voies Ferrées de France change. La nouvelle version entre en vigueur le :date.',
            ],
            'read' => 'Nous vous invitons à la lire avant cette date.',
            'action' => 'Lire la nouvelle version',
            'acceptance' => 'Continuer à utiliser le service après cette date vaut acceptation. Si vous ne l’acceptez pas, vous pouvez supprimer votre compte depuis votre profil.',
            'rights' => 'Vous pouvez à tout moment télécharger vos données ou supprimer votre compte depuis votre profil.',
        ],
    ],
];
