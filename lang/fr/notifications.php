<?php

// Notifications du site (page /notifications, menu du compte) et des courriels qui les doublent.
// Les textes des notifications sont rédigés à l'envoi dans la langue du destinataire.
return [
    'meta_title' => 'Notifications',
    'title' => 'Notifications',
    'menu' => 'Notifications',
    'unread_count_one' => ':count notification non lue',
    'unread_count_other' => ':count notifications non lues',
    'all_read' => 'Vous avez tout lu.',
    'mark_all' => 'Tout marquer comme lu',
    'list_label' => 'Vos notifications',
    'unread' => 'Non lue',
    'reason' => 'Motif',
    'empty_title' => 'Aucune notification',
    'empty_text' => 'Quand une de vos contributions est relue, vous en êtes informé ici.',
    'flash' => [
        'all_read' => 'Toutes vos notifications sont marquées comme lues.',
    ],

    // Texte des notifications (enregistré à l'envoi) et du courriel.
    'about' => 'Ligne concernée : :subject.',
    'reason_line' => 'Motif : :reason',
    'open' => 'Voir la contribution',
    'kinds' => [
        'awaiting_review' => [
            'title' => 'La contribution n° :id attend votre relecture',
            'body' => 'Elle sera publiée après deux validations de membres différents.',
        ],
        'approved' => [
            'title' => 'Votre contribution n° :id a reçu :count validation sur les :threshold nécessaires|Votre contribution n° :id a reçu :count validations sur les :threshold nécessaires',
            'body' => 'Elle sera publiée quand un autre validateur l’aura approuvée.|Elle sera publiée quand :count autres validateurs l’auront approuvée.',
        ],
        'applied' => [
            'title' => 'Votre contribution n° :id est publiée',
            'body' => 'Elle a reçu ses deux validations et fait maintenant partie de l’encyclopédie.',
        ],
        'rejected' => [
            'title' => 'Votre contribution n° :id n’a pas été retenue',
            'body' => 'Un validateur l’a refusée pour le motif suivant.',
            'body_without_reason' => 'Un validateur l’a refusée sans donner de motif. Pour en savoir plus, écrivez à contact@vff-project.org.',
        ],
    ],
];
