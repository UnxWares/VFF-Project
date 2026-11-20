<?php

// Benachrichtigungen auf der Website (Seite /notifications, Kontomenü) und die zugehörigen E-Mails.
return [
    'meta_title' => 'Benachrichtigungen',
    'title' => 'Benachrichtigungen',
    'menu' => 'Benachrichtigungen',
    'unread_count_one' => ':count ungelesene Benachrichtigung',
    'unread_count_other' => ':count ungelesene Benachrichtigungen',
    'all_read' => 'Sie haben alles gelesen.',
    'mark_all' => 'Alle als gelesen markieren',
    'list_label' => 'Ihre Benachrichtigungen',
    'unread' => 'Ungelesen',
    'reason' => 'Begründung',
    'empty_title' => 'Keine Benachrichtigungen',
    'empty_text' => 'Wenn einer Ihrer Beiträge geprüft wird, erfahren Sie es hier.',
    'flash' => [
        'all_read' => 'Alle Ihre Benachrichtigungen sind als gelesen markiert.',
    ],

    'about' => 'Betroffene Strecke: :subject.',
    'reason_line' => 'Begründung: :reason',
    'open' => 'Beitrag ansehen',
    'kinds' => [
        'awaiting_review' => [
            'title' => 'Beitrag Nr. :id wartet auf Ihre Prüfung',
            'body' => 'Er wird veröffentlicht, sobald zwei verschiedene Mitglieder ihn freigegeben haben.',
        ],
        'approved' => [
            'title' => 'Ihr Beitrag Nr. :id hat :count Freigabe erhalten (:threshold erforderlich)|Ihr Beitrag Nr. :id hat :count Freigaben erhalten (:threshold erforderlich)',
            'body' => 'Er wird veröffentlicht, sobald ein weiterer Prüfer ihn freigegeben hat.|Er wird veröffentlicht, sobald :count weitere Prüfer ihn freigegeben haben.',
        ],
        'applied' => [
            'title' => 'Ihr Beitrag Nr. :id ist veröffentlicht',
            'body' => 'Er hat beide Freigaben erhalten und ist jetzt Teil der Enzyklopädie.',
        ],
        'rejected' => [
            'title' => 'Ihr Beitrag Nr. :id wurde nicht angenommen',
            'body' => 'Ein Prüfer hat ihn aus folgendem Grund abgelehnt.',
            'body_without_reason' => 'Ein Prüfer hat ihn ohne Begründung abgelehnt. Wenn Sie mehr erfahren möchten, schreiben Sie an contact@vff-project.org.',
        ],
    ],
];
