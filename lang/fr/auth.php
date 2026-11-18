<?php

/*
 * Messages d'authentification.
 *
 * Les clés `failed`, `password`, `throttle` et les statuts `passwords` sont
 * utilisés par Laravel en interne (validation, broker password). On les garde
 * au même niveau pour profiter du fallback `__('auth.failed')` natif.
 */

return [
    // Messages natifs Laravel
    'failed' => 'Identifiants invalides.',
    'password' => 'Mot de passe incorrect.',
    'throttle' => 'Trop de tentatives. Réessaye dans :seconds secondes.',

    // Statuts du broker password (Password::status)
    'passwords' => [
        'reset' => 'Ton mot de passe a été réinitialisé.',
        'sent' => 'Un lien de réinitialisation t\'a été envoyé par e-mail.',
        'throttled' => 'Trop de demandes, patiente un peu.',
        'token' => 'Ce lien de réinitialisation est invalide ou expiré.',
        'user' => 'Aucun compte ne correspond à cet e-mail.',
    ],

    // Pages
    'login_title' => 'Connexion',
    'login_subtitle' => 'Heureux de te revoir !',
    'register_title' => 'Créer un compte',
    'register_subtitle' => 'Rejoins la communauté VFF.',
    'forgot_title' => 'Mot de passe oublié',
    'forgot_subtitle' => 'Saisis ton e-mail, on t\'envoie un lien de réinitialisation.',
    'reset_title' => 'Nouveau mot de passe',
    'reset_subtitle' => 'Choisis un mot de passe solide (8 caractères minimum).',
    'profile_title' => 'Mon profil',
    'profile_subtitle' => 'Gère tes informations personnelles.',

    // Champs
    'name_label' => 'Nom',
    'email_label' => 'Adresse e-mail',
    'password_label' => 'Mot de passe',
    'password_confirmation_label' => 'Confirmation du mot de passe',
    'bio_label' => 'Bio',
    'bio_placeholder' => 'Quelques mots à ton sujet (optionnel)',
    'locale_label' => 'Langue préférée',
    'remember_label' => 'Se souvenir de moi',
    'role_label' => 'Rôle',
    'reputation_label' => 'Réputation',
    'member_since' => 'Membre depuis',

    // Actions
    'submit' => 'Valider',
    'login_cta' => 'Se connecter',
    'register_terms' => 'En créant un compte, tu acceptes les <a href="/use-conditions">conditions générales d\'utilisation</a> et la <a href="/privacy">politique de confidentialité</a>.',
    'register_cta' => 'Créer mon compte',
    'forgot_cta' => 'Envoyer le lien',
    'reset_cta' => 'Réinitialiser',
    'save_profile' => 'Enregistrer',
    'logout' => 'Se déconnecter',
    'profile' => 'Mon profil',
    'account' => 'Mon compte',
    'my_contributions' => 'Mes contributions',
    'validate' => 'Valider des contribs',
    'login' => 'Connexion',
    'register' => 'Inscription',

    // Liens & textes secondaires
    'forgot_password' => 'Mot de passe oublié ?',
    'no_account' => 'Pas encore de compte ?',
    'has_account' => 'Déjà un compte ?',
    'back_to_login' => 'Retour à la connexion',

    // Confirmations
    'registered' => 'Bienvenue dans VFF !',
    'profile_updated' => 'Profil mis à jour.',

    // Rôles
    'roles' => [
        'visitor' => 'Visiteur',
        'contributor' => 'Contributeur',
        'validator' => 'Validateur',
        'admin' => 'Administrateur',
    ],
];
