<?php

/*
 * Reputation der Mitglieder: Hilfeseite, Profil, Prüfbereich.
 * Die Stufen und ihre Schwellen stehen in config/reputation.php (gleiche Schlüssel unter `levels`).
 * Jede Änderung muss mit der Datenschutzerklärung (privacy.php) vereinbar bleiben.
 */

return [
    'meta_title' => 'Die Reputation der Mitglieder',
    'meta_description' => 'Was die Reputationszahlen bei VFF zählen, welche Schwellen jede Stufe hat und was sie ändern.',

    'hero' => [
        'title' => 'Die Reputation der Mitglieder',
        'subtitle' => 'Drei Zahlen und eine Stufe, für alle auf dieselbe Weise berechnet. Sonst fließt nichts ein.',
    ],

    'figures' => [
        'title' => 'Was gezählt wird',
        'intro' => 'Die Zahlen stammen aus den auf der Website gespeicherten Beiträgen und Prüfungen. Sie werden nach jeder Prüfentscheidung neu berechnet.',
        'applied' => [
            'title' => 'Angenommene Beiträge',
            'text' => 'Beiträge, die von zwei Prüfern freigegeben und anschließend in der Enzyklopädie veröffentlicht wurden.',
        ],
        'rejected' => [
            'title' => 'Abgelehnte Beiträge',
            'text' => 'Beiträge, die ein Prüfer mit Begründung abgelehnt hat. Ein von einem Administrator entfernter Beitrag (Spam, rechtswidriger Inhalt) wird nirgends mitgezählt.',
        ],
        'reviews' => [
            'title' => 'Geleistete Prüfungen',
            'text' => 'Die Freigaben und Ablehnungen, die ein Prüfer entschieden hat. Den eigenen Beitrag zu prüfen ist nicht möglich.',
        ],
        'rate' => [
            'title' => 'Annahmequote',
            'text' => 'Angenommene Beiträge geteilt durch die Beiträge, über die entschieden wurde (angenommen plus abgelehnt), auf eine ganze Zahl abgerundet. Ein noch wartender Beitrag zählt nicht mit.',
        ],
    ],

    'levels' => [
        'newcomer' => 'Neues Mitglied',
        'contributor' => 'Mitwirkender',
        'regular' => 'Regelmäßig Mitwirkender',
        'experienced' => 'Erfahrener Mitwirkender',
    ],

    'thresholds' => [
        'title' => 'Die Stufen',
        'intro' => 'Jede Stufe hat ihre Schwellen, die in der Konfiguration der Website stehen. Ein Mitglied hat die höchste Stufe, deren Schwellen alle erreicht sind. Die Stufe ist eine Orientierung für die Prüfer. Sie gibt keine Rechte: Die Prüfung ist für alle gleich, mit zwei Freigaben vor der Veröffentlichung.',
        'everyone' => 'Jedes Mitglied, ab der Registrierung.',
        'requires' => 'Erforderlich: :conditions.',
        'applied_one' => 'mindestens :count angenommener Beitrag',
        'applied_other' => 'mindestens :count angenommene Beiträge',
        'reviews_one' => 'mindestens :count geleistete Prüfung',
        'reviews_other' => 'mindestens :count geleistete Prüfungen',
        'acceptance' => 'eine Annahmequote von mindestens :count %',
        'rate_note' => 'Die Annahmequote wird erst geprüft, sobald über einen Beitrag entschieden wurde.',
    ],

    'rules' => [
        'title' => 'Einige Hinweise',
        'who_title' => 'Wer diese Zahlen sieht',
        'who_text' => 'Ihre eigenen sehen Sie in Ihrem Profil. Die Prüfer sehen die des Autors, mit seiner Stufe, neben seinem Namen, wenn sie einen Beitrag prüfen. Es gibt kein öffentliches Profil, und die Reputation ist nicht Teil der API.',
        'suspended_title' => 'Gesperrtes Konto',
        'suspended_text' => 'Ein gesperrtes Konto behält seine Zahlen, aber solange die Sperre dauert, wird keine Stufe angezeigt.',
        'deleted_title' => 'Gelöschtes Konto',
        'deleted_text' => 'Die Zahlen verschwinden mit dem Konto. Veröffentlichte Beiträge bleiben ohne Namen bestehen: Ein Prüfer sieht dann „Anonymer Beitragender“ und keine Zahlen.',
        'recalc_title' => 'Neuberechnung',
        'recalc_text' => 'Die Zahlen werden nach jeder Prüfentscheidung neu berechnet. Ein Administrator kann auch alles auf einmal neu berechnen, mit demselben Ergebnis: Es hängt nur von den gespeicherten Beiträgen und Prüfungen ab.',
        'export_title' => 'Ihre Daten',
        'export_text' => 'Ihre Zahlen sind im Export Ihrer Daten aus Ihrem Profil enthalten.',
        'privacy_link' => 'Datenschutzerklärung',
    ],

    'profile' => [
        'title' => 'Reputation',
        'level' => 'Stufe',
        'applied' => 'Angenommene Beiträge',
        'rejected' => 'Abgelehnte Beiträge',
        'reviews' => 'Geleistete Prüfungen',
        'acceptance' => 'Annahmequote',
        'percent' => ':count %',
        'level_hidden' => 'Solange Ihr Konto gesperrt ist, wird keine Stufe angezeigt. Ihre Zahlen bleiben erhalten.',
        'next' => 'Für die Stufe „:level“ brauchen Sie :conditions.',
        'missing_applied_one' => 'noch :count angenommenen Beitrag',
        'missing_applied_other' => 'noch :count angenommene Beiträge',
        'missing_reviews_one' => 'noch :count Prüfung',
        'missing_reviews_other' => 'noch :count Prüfungen',
        'missing_acceptance' => 'eine Annahmequote von mindestens :count %',
        'help_link' => 'So wird die Reputation berechnet',
    ],

    'validate' => [
        'by' => 'von :name',
        'applied_one' => ':count angenommen',
        'applied_other' => ':count angenommen',
        'rejected_one' => ':count abgelehnt',
        'rejected_other' => ':count abgelehnt',
    ],
];
