<?php

// Signalement d'une erreur depuis une fiche (ligne, gare, matériel, accident) et liste des
// signalements dans l'espace de validation (/validate/reports).
return [
    'prompt' => 'Une information de cette fiche vous semble fausse ?',
    'button' => 'Signaler une erreur',

    'dialog' => [
        'title' => 'Signaler une erreur',
        'intro' => 'L’équipe relit chaque signalement et corrige la fiche quand l’erreur est confirmée.',
        'subject' => 'Fiche',
        'description_label' => 'Ce qui est faux',
        'description_hint' => 'Indiquez l’information erronée et, si vous la connaissez, la bonne valeur. 2 000 caractères au plus.',
        'source_label' => 'Source',
        'source_hint' => 'Lien vers un document, un article ou une page qui appuie la correction.',
        'contact_label' => 'Votre adresse électronique',
        'contact_hint' => 'Seulement si vous acceptez que l’équipe vous écrive au sujet de ce signalement.',
        'optional' => 'facultatif',
        'honeypot_label' => 'Laissez ce champ vide',
        'privacy' => 'Votre message et votre adresse servent uniquement à traiter ce signalement. Ils sont effacés 12 mois après son traitement.',
        'privacy_link' => 'Politique de confidentialité',
        'submit' => 'Envoyer le signalement',
        'cancel' => 'Annuler',
        'thanks_title' => 'Merci, votre signalement est envoyé',
        'thanks_text' => 'L’équipe va le relire. Si vous avez laissé votre adresse, elle pourra vous écrire pour vous demander une précision.',
        'close' => 'Fermer',
    ],

    'errors' => [
        'description_required' => 'Décrivez l’erreur que vous avez repérée.',
        'description_min' => 'Décrivez l’erreur en :min caractères au moins.',
        'description_max' => 'Votre message dépasse :max caractères.',
        'source_url' => 'Indiquez une adresse web complète, commençant par https:// ou http://.',
        'contact_email' => 'Cette adresse électronique n’est pas valide.',
        'too_fast' => 'Le formulaire a été envoyé trop vite. Patientez quelques secondes, puis envoyez-le de nouveau.',
        'too_many' => 'Vous avez envoyé beaucoup de signalements en peu de temps. Réessayez un peu plus tard.',
        'subject_missing' => 'Cette fiche n’existe plus. Rechargez la page.',
        'token' => 'Le formulaire n’a pas pu être préparé. Vérifiez votre connexion, puis rouvrez-le.',
    ],

    // Notification du site envoyée aux validateurs et aux administrateurs.
    'notification' => [
        'title' => 'Signalement n° :id à examiner',
        'body' => 'Une erreur est signalée sur la fiche « :subject ».',
    ],

    'review' => [
        'meta_title' => 'Signalements d’erreur',
        'title' => 'Signalements d’erreur',
        'subtitle' => 'Erreurs signalées depuis les fiches par les visiteurs et les membres.',
        'nav_label' => 'Espace de validation',
        'contributions_link' => 'Contributions à relire',
        'reports_link' => 'Signalements d’erreur',
        'filter_label' => 'Afficher les signalements',
        'filters' => [
            'open' => 'Ouverts',
            'resolved' => 'Traités',
            'rejected' => 'Rejetés',
        ],
        'statuses' => [
            'open' => 'Ouvert',
            'resolved' => 'Traité',
            'rejected' => 'Rejeté',
        ],
        'subject_types' => [
            'line' => 'Ligne',
            'station' => 'Gare',
            'material' => 'Matériel',
            'accident' => 'Accident',
        ],
        'report_number' => 'Signalement n° :id',
        'open_sheet' => 'Voir la fiche',
        'sheet_missing' => 'Cette fiche n’existe plus.',
        'received' => 'Reçu le :date',
        'by_member' => 'par :name',
        'by_visitor' => 'par un visiteur',
        'source' => 'Source',
        'contact' => 'Contact',
        'page_language' => 'Langue de la page',
        'handled_by' => 'le :date par :name',
        'handled' => 'le :date',
        'note' => 'Note de l’équipe',
        'note_label' => 'Note de l’équipe',
        'note_hint' => 'Ce qui a été corrigé, ou la raison du rejet (obligatoire pour rejeter).',
        'mark_resolved' => 'Marquer comme traité',
        'reject' => 'Rejeter',
        'empty' => [
            'open' => 'Aucun signalement ouvert',
            'resolved' => 'Aucun signalement traité',
            'rejected' => 'Aucun signalement rejeté',
        ],
        'empty_text' => 'Les erreurs signalées depuis les fiches arrivent ici, et chaque validateur en est prévenu par une notification.',
        'flash' => [
            'resolved' => 'Le signalement n° :id est marqué comme traité.',
            'rejected' => 'Le signalement n° :id est rejeté.',
            'closed' => 'Ce signalement a déjà été traité.',
        ],
        'errors' => [
            'note_required' => 'Indiquez pourquoi ce signalement est rejeté.',
            'note_max' => 'La note dépasse :max caractères.',
        ],
    ],
];
