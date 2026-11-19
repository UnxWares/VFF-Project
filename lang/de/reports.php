<?php

// Fehlermeldung von einer Seite (Strecke, Bahnhof, Fahrzeug, Unfall) und Liste der Meldungen
// im Prüfbereich (/validate/reports).
return [
    'prompt' => 'Ist eine Angabe auf dieser Seite falsch?',
    'button' => 'Fehler melden',

    'dialog' => [
        'title' => 'Fehler melden',
        'intro' => 'Das Team liest jede Meldung und korrigiert die Seite, wenn sich der Fehler bestätigt.',
        'subject' => 'Seite',
        'description_label' => 'Was ist falsch?',
        'description_hint' => 'Nennen Sie die falsche Angabe und, falls bekannt, den richtigen Wert. Höchstens 2.000 Zeichen.',
        'source_label' => 'Quelle',
        'source_hint' => 'Link zu einem Dokument, Artikel oder einer Seite, die die Korrektur belegt.',
        'contact_label' => 'Ihre E-Mail-Adresse',
        'contact_hint' => 'Nur wenn das Team Ihnen zu dieser Meldung schreiben darf.',
        'optional' => 'freiwillig',
        'honeypot_label' => 'Dieses Feld bitte leer lassen',
        'privacy' => 'Ihre Nachricht und Ihre Adresse dienen nur der Bearbeitung dieser Meldung. Sie werden 12 Monate nach der Bearbeitung gelöscht.',
        'privacy_link' => 'Datenschutzerklärung',
        'submit' => 'Meldung senden',
        'cancel' => 'Abbrechen',
        'thanks_title' => 'Danke, Ihre Meldung wurde gesendet',
        'thanks_text' => 'Das Team wird sie lesen. Wenn Sie Ihre Adresse angegeben haben, kann es Ihnen für eine Rückfrage schreiben.',
        'close' => 'Schließen',
    ],

    'errors' => [
        'description_required' => 'Beschreiben Sie den Fehler, den Sie gefunden haben.',
        'description_min' => 'Beschreiben Sie den Fehler in mindestens :min Zeichen.',
        'description_max' => 'Ihre Nachricht ist länger als :max Zeichen.',
        'source_url' => 'Geben Sie eine vollständige Webadresse an, die mit https:// oder http:// beginnt.',
        'contact_email' => 'Diese E-Mail-Adresse ist ungültig.',
        'too_fast' => 'Das Formular wurde zu schnell gesendet. Warten Sie einige Sekunden und senden Sie es erneut.',
        'too_many' => 'Sie haben in kurzer Zeit viele Meldungen gesendet. Versuchen Sie es etwas später noch einmal.',
        'subject_missing' => 'Diese Seite gibt es nicht mehr. Laden Sie die Seite neu.',
        'token' => 'Das Formular konnte nicht vorbereitet werden. Prüfen Sie Ihre Verbindung und öffnen Sie es erneut.',
    ],

    // Benachrichtigung auf der Website für Prüferinnen, Prüfer und Administratoren.
    'notification' => [
        'title' => 'Meldung Nr. :id zu prüfen',
        'body' => 'Auf der Seite „:subject“ wurde ein Fehler gemeldet.',
    ],

    'review' => [
        'meta_title' => 'Fehlermeldungen',
        'title' => 'Fehlermeldungen',
        'subtitle' => 'Fehler, die Besucher und Mitglieder auf den Seiten gemeldet haben.',
        'nav_label' => 'Prüfbereich',
        'contributions_link' => 'Zu prüfende Beiträge',
        'reports_link' => 'Fehlermeldungen',
        'filter_label' => 'Meldungen anzeigen',
        'filters' => [
            'open' => 'Offen',
            'resolved' => 'Erledigt',
            'rejected' => 'Abgelehnt',
        ],
        'statuses' => [
            'open' => 'Offen',
            'resolved' => 'Erledigt',
            'rejected' => 'Abgelehnt',
        ],
        'subject_types' => [
            'line' => 'Strecke',
            'station' => 'Bahnhof',
            'material' => 'Fahrzeug',
            'accident' => 'Unfall',
        ],
        'report_number' => 'Meldung Nr. :id',
        'open_sheet' => 'Seite ansehen',
        'sheet_missing' => 'Diese Seite gibt es nicht mehr.',
        'received' => 'Eingegangen am :date',
        'by_member' => 'von :name',
        'by_visitor' => 'von einem Besucher',
        'source' => 'Quelle',
        'contact' => 'Kontakt',
        'page_language' => 'Sprache der Seite',
        'handled_by' => 'am :date von :name',
        'handled' => 'am :date',
        'note' => 'Notiz des Teams',
        'note_label' => 'Notiz des Teams',
        'note_hint' => 'Was korrigiert wurde oder warum die Meldung abgelehnt wird (zum Ablehnen erforderlich).',
        'mark_resolved' => 'Als erledigt markieren',
        'reject' => 'Ablehnen',
        'empty' => [
            'open' => 'Keine offenen Meldungen',
            'resolved' => 'Keine erledigten Meldungen',
            'rejected' => 'Keine abgelehnten Meldungen',
        ],
        'empty_text' => 'Auf den Seiten gemeldete Fehler erscheinen hier, und alle Prüfer erhalten eine Benachrichtigung.',
        'flash' => [
            'resolved' => 'Meldung Nr. :id ist als erledigt markiert.',
            'rejected' => 'Meldung Nr. :id wurde abgelehnt.',
            'closed' => 'Diese Meldung wurde bereits bearbeitet.',
        ],
        'errors' => [
            'note_required' => 'Geben Sie an, warum diese Meldung abgelehnt wird.',
            'note_max' => 'Die Notiz ist länger als :max Zeichen.',
        ],
    ],
];
