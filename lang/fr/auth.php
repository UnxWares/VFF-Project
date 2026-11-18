<?php

/*
 * Messages d'authentification.
 *
 * Les clés `failed`, `password`, `throttle` et les statuts `passwords` sont
 * utilisés par Laravel en interne (validation, broker password). On les garde
 * au même niveau pour profiter du fallback `__('auth.failed')` natif.
 * Texte au vouvoiement.
 */

return [
    // Messages natifs Laravel
    'failed' => 'Identifiants invalides.',
    'password' => 'Mot de passe incorrect.',
    'throttle' => 'Trop de tentatives. Réessayez dans :seconds secondes.',

    // Statuts du broker password (Password::status)
    'passwords' => [
        'reset' => 'Votre mot de passe a été réinitialisé.',
        'sent' => 'Un lien de réinitialisation vous a été envoyé par e-mail.',
        'throttled' => 'Trop de demandes, patientez un peu avant de réessayer.',
        'token' => 'Ce lien de réinitialisation est invalide ou a expiré.',
        'user' => 'Aucun compte ne correspond à cette adresse e-mail.',
    ],

    // Pages
    'login_title' => 'Connexion',
    'login_subtitle' => 'Heureux de vous revoir !',
    'register_title' => 'Créer un compte',
    'register_subtitle' => 'Rejoignez la communauté VFF.',
    'forgot_title' => 'Mot de passe oublié',
    'forgot_subtitle' => 'Saisissez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.',
    'reset_title' => 'Nouveau mot de passe',
    'reset_subtitle' => 'Choisissez un mot de passe d\'au moins 8 caractères.',
    'profile_title' => 'Mon profil',
    'profile_subtitle' => 'Gérez vos informations personnelles.',

    // Champs
    'edit_title' => 'Modifier mon profil',
    'name_label' => 'Nom d\'affichage',
    'name_hint' => 'Les validateurs voient ce nom à côté de vos contributions.',
    'email_label' => 'Adresse e-mail',
    'password_label' => 'Mot de passe',
    'password_new_label' => 'Nouveau mot de passe',
    'password_hint' => '8 caractères au moins.',
    'password_confirmation_label' => 'Confirmation du mot de passe',
    'show_password' => 'Afficher le mot de passe',
    'hide_password' => 'Masquer le mot de passe',
    'bio_label' => 'Présentation',
    'bio_hint' => 'Facultatif, 2 000 caractères au plus.',
    'bio_placeholder' => 'Quelques mots sur vous',
    'locale_label' => 'Langue préférée',
    'locale_hint' => 'La langue s\'applique dès l\'enregistrement.',
    'remember_label' => 'Se souvenir de moi',
    'role_label' => 'Rôle',
    'reputation_label' => 'Réputation',
    'member_since' => 'Membre depuis',
    'email_fixed_hint' => 'Cette adresse sert à vous connecter.',

    // Erreurs de saisie (contrôlées dans le navigateur avant l'envoi)
    'errors' => [
        'name_required' => 'Saisissez votre nom d\'affichage.',
        'email_required' => 'Saisissez votre adresse e-mail.',
        'email_invalid' => 'Cette adresse e-mail n\'est pas valide.',
        'password_required' => 'Saisissez votre mot de passe.',
        'password_new_required' => 'Choisissez un mot de passe.',
        'password_short' => 'Le mot de passe doit contenir au moins 8 caractères.',
        'confirmation_required' => 'Saisissez à nouveau le mot de passe pour le confirmer.',
        'confirmation_mismatch' => 'Les deux mots de passe ne sont pas identiques.',
        'login_failed_title' => 'Connexion impossible',
    ],

    // Actions
    'submit' => 'Valider',
    'login_cta' => 'Se connecter',
    'register_terms' => 'En créant un compte, vous acceptez les <a href="/use-conditions">conditions générales d\'utilisation</a>.',
    'register_privacy' => 'UnxWares, responsable du traitement, utilise votre nom, votre adresse e-mail et votre mot de passe (jamais conservé en clair) pour vous identifier et gérer vos contributions. Durées de conservation, droits et contact : <a href="/privacy">politique de confidentialité</a>.',
    'register_cta' => 'Créer mon compte',
    'forgot_cta' => 'Envoyer le lien',
    'reset_cta' => 'Enregistrer le mot de passe',
    'save_profile' => 'Enregistrer le profil',
    'logout' => 'Se déconnecter',
    'profile' => 'Mon profil',
    'account' => 'Mon compte',
    'my_contributions' => 'Mes contributions',
    'validate' => 'Contributions à valider',
    'login' => 'Connexion',
    'register' => 'Inscription',

    // Liens & textes secondaires
    'forgot_password' => 'Mot de passe oublié ?',
    'no_account' => 'Pas encore de compte ?',
    'has_account' => 'Déjà un compte ?',
    'back_to_login' => 'Retour à la connexion',

    // Confirmations
    'registered' => 'Bienvenue dans VFF !',
    'profile_updated' => 'Profil mis à jour.',

    // Rôles
    'roles' => [
        'visitor' => 'Visiteur',
        'contributor' => 'Contributeur',
        'validator' => 'Validateur',
        'admin' => 'Administrateur',
    ],
];
