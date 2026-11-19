<?php

/*
 * Kontomoderation: Sperrung durch einen Administrator, Aufhebung, Mitteilungen an das Mitglied.
 * Zusagen der Nutzungsbedingungen („Prüfung und Moderation“) und der Datenschutzerklärung:
 * Änderungen müssen mit ihnen übereinstimmen. Die französische Datei ist die Referenz.
 */

return [
    'errors' => [
        'suspended' => 'Ihr Konto ist gesperrt: Sie können keine Beiträge mehr vorschlagen, prüfen oder Fehler melden. Ihr Profil bleibt zugänglich, mit der Begründung und Ihren Daten.',
        'suspended_until' => 'Ihr Konto ist bis einschließlich :date gesperrt: Sie können keine Beiträge mehr vorschlagen, prüfen oder Fehler melden. Ihr Profil bleibt zugänglich, mit der Begründung und Ihren Daten.',
        'self' => 'Sie können Ihr eigenes Konto nicht sperren.',
        'last_admin' => 'Dieses Konto ist der letzte handlungsfähige Administrator: Würde es gesperrt, könnte niemand mehr Rollen vergeben oder eine Sperrung aufheben.',
        'already_suspended' => 'Dieses Konto ist bereits gesperrt. Heben Sie die Sperrung zuerst auf, um eine neue zu setzen.',
        'not_suspended' => 'Dieses Konto ist nicht gesperrt.',
        'reason_required' => 'Geben Sie die Begründung der Sperrung an (mindestens :min Zeichen): Sie wird dem Mitglied mitgeteilt.',
        'reason_max' => 'Die Begründung darf höchstens :max Zeichen lang sein.',
        'until_format' => 'Das Enddatum ist ungültig.',
        'until_future' => 'Das Enddatum muss nach dem heutigen Tag liegen.',
    ],

    'flash' => [
        'suspended' => 'Das Konto von :name ist gesperrt. Das Mitglied wurde auf der Website und per E-Mail benachrichtigt.',
        'lifted' => 'Die Sperrung des Kontos von :name ist aufgehoben. Das Mitglied wurde benachrichtigt.',
    ],

    'notification' => [
        'suspended' => [
            'title' => 'Ihr Konto ist gesperrt',
            'indefinite' => 'Ein Administrator hat Ihr Konto gesperrt. Die Sperrung dauert, bis er sie aufhebt.',
            'until' => 'Ein Administrator hat Ihr Konto bis einschließlich :date gesperrt.',
            'effect' => 'Sie können keine Beiträge mehr vorschlagen, prüfen oder Fehler melden. Sie können die Website weiter nutzen, Ihre Daten herunterladen und Ihr Konto in Ihrem Profil löschen.',
        ],
        'lifted' => [
            'title' => 'Die Sperrung Ihres Kontos ist aufgehoben',
            'body' => 'Sie können wieder Beiträge vorschlagen und Fehler melden, und Beiträge prüfen, sofern Ihre Rolle das erlaubt.',
        ],
        'contest' => 'Um diese Entscheidung anzufechten, schreiben Sie an contact@vff-project.org: Sie wird erneut geprüft, nach Möglichkeit von einer anderen Person als der, die sie getroffen hat.',
    ],
    'mail' => [
        'action' => 'Mein Profil öffnen',
    ],

    'profile' => [
        'title' => 'Ihr Konto ist gesperrt',
        'indefinite' => 'Die Sperrung dauert, bis ein Administrator sie aufhebt.',
        'until' => 'Die Sperrung dauert bis einschließlich :date.',
        'since' => 'Sie begann am :date.',
        'reason' => 'Begründung: :reason',
        'effect' => 'Sie können keine Beiträge mehr vorschlagen, prüfen oder Fehler melden. Ihre Daten bleiben unten zugänglich: Sie können sie herunterladen oder Ihr Konto löschen.',
        'contest' => 'Um diese Entscheidung anzufechten, schreiben Sie an contact@vff-project.org.',
    ],

    'report' => [
        'suspended' => 'Ihr Konto ist gesperrt: Sie können keine Meldung senden. Die Begründung steht in Ihrem Profil.',
        'profile_link' => 'Mein Profil öffnen',
    ],

    'admin' => [
        'meta_title' => 'Konten',
        'title' => 'Konten',
        'subtitle' => 'Sperren Sie ein Konto, das gegen die Nutzungsbedingungen verstößt, oder heben Sie eine Sperrung auf. Das Mitglied erhält die Begründung auf der Website und per E-Mail.',
        'nav_link' => 'Konten',
        'search_label' => 'Konto suchen',
        'search_hint' => 'Name oder E-Mail-Adresse.',
        'search_button' => 'Suchen',
        'filter_label' => 'Konten anzeigen',
        'filters' => [
            'all' => 'Alle Konten',
            'suspended' => 'Gesperrt',
        ],
        'list_label' => 'Konten',
        'empty' => [
            'all' => 'Kein Konto entspricht dieser Suche.',
            'suspended' => 'Kein Konto ist gesperrt.',
        ],
        'member_since' => 'Registriert am :date',
        'contributions' => 'Beiträge: :count',
        'badge_suspended' => 'Gesperrt',
        'suspended_since' => 'Gesperrt am :date',
        'until' => 'bis einschließlich :date',
        'indefinite' => 'bis zur Aufhebung',
        'by' => 'von :name',
        'reason' => 'Begründung',
        'lift' => 'Sperrung aufheben',
        'suspend_open' => 'Dieses Konto sperren',
        'reason_label' => 'Begründung der Sperrung',
        'reason_hint' => 'Wird dem Mitglied auf der Website und per E-Mail mitgeteilt. Mindestens :min Zeichen.',
        'until_label' => 'Letzter Tag der Sperrung',
        'until_hint' => 'Optional. Ohne Datum dauert die Sperrung, bis Sie sie aufheben.',
        'submit' => 'Konto sperren',
        'self_note' => 'Ihr Konto',
    ],
];
