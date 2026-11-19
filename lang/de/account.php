<?php

/*
 * Kontodaten: Export, Löschung, inaktive Konten, Mitteilungen an Mitglieder.
 * Maßgeblich ist die französische Fassung.
 */

return [
    'anonymous_contributor' => 'Anonymer Beitragender',

    'data' => [
        'title' => 'Meine Daten',
        'intro' => 'Sie können alles herunterladen, was Französische Bahnstrecken zu Ihrem Konto speichert, oder Ihr Konto löschen.',
        'privacy_link' => 'Ihre Rechte erläutert die Datenschutzerklärung.',
        'export_title' => 'Meine Daten herunterladen',
        'export_text' => 'Eine JSON-Datei mit Ihrem Profil, Ihren Beiträgen, Ihren Freigaben und Ihren Prüfungen.',
        'export_button' => 'Meine Daten herunterladen',
        'delete_title' => 'Mein Konto löschen',
        'delete_text' => 'Ihr Profil (Name, E-Mail-Adresse, Vorstellung, Avatar) wird gelöscht. Ihre veröffentlichten Beiträge bleiben in der Enzyklopädie und werden „Anonymer Beitragender“ zugeschrieben. Ihre ausstehenden oder abgelehnten Beiträge werden gelöscht. Dieser Schritt ist endgültig.',
        'delete_button' => 'Mein Konto löschen',
        'delete_confirm_title' => 'Möchten Sie Ihr Konto endgültig löschen?',
        'delete_confirm_text' => 'Geben Sie zur Bestätigung Ihr Passwort ein. Laden Sie Ihre Daten vorher herunter: Danach lassen sie sich nicht wiederherstellen.',
        'delete_password_label' => 'Passwort',
        'delete_password_required' => 'Bitte geben Sie Ihr Passwort ein, um das Löschen zu bestätigen.',
        'delete_confirm_button' => 'Endgültig löschen',
        'delete_cancel' => 'Abbrechen',
    ],

    'flash' => [
        'deleted' => 'Ihr Konto wurde gelöscht. Ihre veröffentlichten Beiträge bleiben ohne Ihren Namen in der Enzyklopädie.',
        'contribution_removed' => 'Der Beitrag wurde entfernt und sein Autor benachrichtigt.',
    ],

    'errors' => [
        'last_admin' => 'Sie sind der einzige Administrator: Übertragen Sie diese Rolle einem anderen Mitglied, bevor Sie Ihr Konto löschen.',
        'removal_published' => 'Dieser Beitrag ist bereits veröffentlicht: Korrigieren Sie die Daten mit einem neuen Beitrag, statt ihn zu entfernen.',
    ],

    'mail' => [
        'greeting' => 'Guten Tag :name,',
        'salutation' => 'Das Team von Französische Bahnstrecken',

        'inactive' => [
            'subject' => 'Ihr Konto bei Französische Bahnstrecken wird am :date gelöscht',
            'intro' => 'Sie haben sich seit fast 3 Jahren nicht bei Französische Bahnstrecken angemeldet.',
            'deletion' => 'Wie in der Datenschutzerklärung angekündigt, wird ein 3 Jahre lang ungenutztes Konto gelöscht. Ihres wird am :date gelöscht.',
            'keep' => 'Um es zu behalten, melden Sie sich vor diesem Datum an.',
            'action' => 'Anmelden',
            'contributions' => 'Wird Ihr Konto gelöscht, bleiben Ihre veröffentlichten Beiträge ohne Ihren Namen in der Enzyklopädie. Die übrigen werden gelöscht.',
        ],

        'removed' => [
            'subject' => 'Ihr Beitrag Nr. :id wurde entfernt',
            'intro' => 'Ihr Beitrag Nr. :id vom :date wurde vom Team von Französische Bahnstrecken entfernt.',
            'reason' => 'Begründung: :reason',
            'contest' => 'Um diese Entscheidung anzufechten, schreiben Sie an contact@vff-project.org: Sie wird erneut geprüft, nach Möglichkeit von einer anderen Person als der, die sie getroffen hat.',
            'action' => 'Nutzungsbedingungen lesen',
        ],

        'terms' => [
            'use-conditions' => [
                'subject' => 'Änderung der Nutzungsbedingungen von Französische Bahnstrecken',
                'intro' => 'Die Nutzungsbedingungen von Französische Bahnstrecken ändern sich. Die neue Fassung gilt ab dem :date.',
            ],
            'privacy' => [
                'subject' => 'Änderung der Datenschutzerklärung von Französische Bahnstrecken',
                'intro' => 'Die Datenschutzerklärung von Französische Bahnstrecken ändert sich. Die neue Fassung gilt ab dem :date.',
            ],
            'read' => 'Bitte lesen Sie sie vor diesem Datum.',
            'action' => 'Neue Fassung lesen',
            'acceptance' => 'Wenn Sie den Dienst nach diesem Datum weiter nutzen, gilt dies als Zustimmung. Wenn Sie nicht zustimmen, können Sie Ihr Konto in Ihrem Profil löschen.',
            'rights' => 'Sie können Ihre Daten jederzeit in Ihrem Profil herunterladen oder Ihr Konto löschen.',
        ],
    ],
];
