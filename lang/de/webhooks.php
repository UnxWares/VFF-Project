<?php

// Webhooks der API (Profilbereich, App\Http\Controllers\WebhookSubscriptionController,
// App\Services\Webhooks). Anrede: Sie.
return [
    'title' => 'Webhooks',
    'intro' => 'Mit einem Webhook schickt Französische Bahnstrecken bei jedem Ereignis, dem Sie folgen, eine kurze signierte JSON-Nachricht an eine HTTPS-Adresse Ihrer Wahl. Ihr Programm muss die API also nicht in regelmäßigen Abständen abfragen.',
    'docs_link' => 'Nachrichtenformat und Prüfung der Signatur',

    'events' => [
        'contribution_applied' => [
            'label' => 'Beitrag veröffentlicht',
            'hint' => 'Ein freigegebener Beitrag wurde gerade auf eine Strecke oder einen Bahnhof angewendet.',
        ],
        'current_era_refreshed' => [
            'label' => 'Heutiges Netz neu berechnet',
            'hint' => 'In der aktuellen Epoche haben sich Strecken oder Bahnhöfe geändert (tägliche Neuberechnung).',
        ],
        'accident_published' => [
            'label' => 'Neuer Unfall',
            'hint' => 'Ein Import hat gerade einen Unfalleintrag veröffentlicht.',
        ],
        'material_published' => [
            'label' => 'Neues Rollmaterial',
            'hint' => 'Ein Import hat gerade einen Eintrag zum Rollmaterial veröffentlicht.',
        ],
    ],

    'list_title' => 'Ihre Abonnements',
    'count' => ':count von :max',
    'empty' => 'Sie haben noch kein Abonnement.',
    'created_at' => 'Erstellt am :date',
    'active' => 'Aktiv',
    'disabled' => 'Deaktiviert',
    'disabled_failures' => 'Am :date nach :count fehlgeschlagenen Zustellungen in Folge deaktiviert. Prüfen Sie, ob Ihre Adresse mit einem 2xx-Code antwortet, und aktivieren Sie das Abonnement dann wieder.',
    'failing' => 'Fehlgeschlagene Zustellungen in Folge: :count (Deaktivierung bei :max).',
    'events_followed' => 'Abonnierte Ereignisse: :list',

    'log_title' => 'Letzte Zustellungen',
    'log_empty' => 'Noch keine Zustellung.',
    'log_caption' => 'Letzte Zustellungen dieses Abonnements, die neueste zuerst',
    'log_date' => 'Datum',
    'log_event' => 'Ereignis',
    'log_result' => 'Ergebnis',
    'log_attempts' => 'Versuche: :count',
    'log_http' => 'HTTP :code',
    'statuses' => [
        'pending' => 'Ausstehend',
        'succeeded' => 'Zugestellt',
        'failed' => 'Fehlgeschlagen',
        'skipped' => 'Nicht gesendet',
    ],
    'delivery_errors' => [
        'timeout' => 'Zeitüberschreitung',
        'connection_failed' => 'keine Verbindung',
        'http_status' => 'Antwortcode nicht akzeptiert',
        'redirect' => 'Weiterleitung (nie befolgt)',
        'address_refused' => 'Adresse abgelehnt',
        'dns_failed' => 'Name nicht gefunden',
        'internal' => 'Fehler auf unserer Seite',
    ],

    'enable' => 'Wieder aktivieren',
    'enable_label' => 'Abonnement an :url wieder aktivieren',
    'delete' => 'Löschen',
    'delete_label' => 'Abonnement an :url löschen',
    'delete_confirm_title' => 'Dieses Abonnement löschen?',
    'delete_confirm_text' => 'An :url wird nichts mehr gesendet. Das Zustellungsprotokoll wird mit dem Abonnement gelöscht.',
    'delete_confirm_button' => 'Abonnement löschen',
    'cancel' => 'Abbrechen',

    'create_title' => 'Abonnement hinzufügen',
    'url_label' => 'Empfangsadresse',
    'url_hint' => 'Eine öffentliche HTTPS-Adresse auf Port 443, zum Beispiel https://beispiel.de/vff/webhook. Lokale und private Adressen werden abgelehnt.',
    'events_legend' => 'Zu empfangende Ereignisse',
    'create_button' => 'Abonnement hinzufügen',
    'blocked_suspended' => 'Ihr Konto ist gesperrt: Sie können kein Abonnement hinzufügen, und an Ihre Adressen wird bis zum Ende der Sperre nichts gesendet.',
    'blocked_max' => 'Sie haben :max Abonnements, die Höchstzahl. Löschen Sie eines, um ein neues hinzuzufügen.',
    'rules' => 'Jede Nachricht läuft über die Warteschlange. Schlägt die Zustellung fehl, gibt es insgesamt :attempts Versuche im Abstand von 1 Minute bis 2 Stunden. Nach :max fehlgeschlagenen Zustellungen in Folge wird das Abonnement deaktiviert, und Sie werden auf der Website benachrichtigt.',

    'new_title' => 'Kopieren Sie jetzt Ihr Signaturgeheimnis',
    'new_text' => 'Es wird nicht noch einmal angezeigt. Ihr Programm prüft damit, dass jede Nachricht wirklich von Französische Bahnstrecken stammt.',
    'new_label' => 'Signaturgeheimnis',
    'copy' => 'Geheimnis kopieren',
    'copied' => 'Geheimnis kopiert.',
    'copy_failed' => 'Das Kopieren ist fehlgeschlagen: Markieren Sie das Geheimnis und kopieren Sie es von Hand.',

    'errors' => [
        'url_required' => 'Geben Sie die Empfangsadresse an.',
        'events_required' => 'Wählen Sie mindestens ein Ereignis.',
        'suspended' => 'Ihr Konto ist gesperrt: Sie können kein Abonnement hinzufügen.',
        'too_many' => 'Sie haben bereits :max Abonnement, die Höchstzahl.|Sie haben bereits :max Abonnements, die Höchstzahl.',
        'url' => [
            'invalid' => 'Diese Adresse ist ungültig.',
            'not_https' => 'Die Adresse muss mit https:// beginnen.',
            'credentials' => 'Die Adresse darf weder Benutzernamen noch Passwort enthalten.',
            'port' => 'Nur der HTTPS-Standardport (443) ist zulässig.',
            'host' => 'Dieser Name bezeichnet ein lokales oder internes Netz. Geben Sie eine öffentliche Adresse an.',
            'private_address' => 'Diese Adresse führt in ein privates, lokales oder reserviertes Netz. Geben Sie eine öffentliche Adresse an.',
            'unresolved' => 'Dieser Domainname wurde nicht gefunden.',
        ],
    ],

    'flash' => [
        'created' => 'Das Abonnement wurde hinzugefügt.',
        'deleted' => 'Das Abonnement wurde gelöscht.',
        'enabled' => 'Das Abonnement wurde wieder aktiviert.',
    ],

    'notification' => [
        'title' => 'Ein Webhook-Abonnement wurde deaktiviert',
        'body' => 'Die Zustellung an :url ist :count Mal in Folge fehlgeschlagen. Dorthin wird nichts mehr gesendet. Sie können das Abonnement in Ihrem Profil wieder aktivieren.|Die Zustellungen an :url sind :count Mal in Folge fehlgeschlagen. Dorthin wird nichts mehr gesendet. Sie können das Abonnement in Ihrem Profil wieder aktivieren.',
    ],
];
