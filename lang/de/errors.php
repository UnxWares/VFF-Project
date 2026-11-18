<?php

// Fehlerseiten (resources/js/Pages/Error.svelte, resources/views/errors/minimal.blade.php).
return [
    'eyebrow' => 'Fehler :code',
    'back' => 'Zurück',
    'home' => 'Zurück zur Startseite',
    'reload' => 'Seite neu laden',
    'login' => 'Anmelden',
    'explore' => 'Um weiterzustöbern',
    'map' => 'Karte öffnen',
    'lines' => 'Strecken durchsuchen',
    'stations' => 'Bahnhöfe durchsuchen',
    'retry_after' => 'Sie können es in :seconds Sekunden erneut versuchen.',

    '403' => [
        'title' => 'Zugriff verweigert',
        'text' => 'Sie haben keine Berechtigung, diese Seite zu öffnen. Falls Sie einen Fehler vermuten, melden Sie sich mit dem richtigen Konto an oder schreiben Sie uns.',
    ],
    '404' => [
        'title' => 'Seite nicht gefunden',
        'text' => 'Die angeforderte Adresse existiert nicht, oder die Seite wurde verschoben. Prüfen Sie die Adresse oder beginnen Sie erneut auf der Startseite, der Karte oder der Streckenliste.',
    ],
    '405' => [
        'title' => 'Anfrage nicht akzeptiert',
        'text' => 'Diese Adresse beantwortet diese Art von Anfrage nicht. Kehren Sie zur vorherigen Seite zurück oder beginnen Sie erneut auf der Startseite.',
    ],
    '419' => [
        'title' => 'Sitzung abgelaufen',
        'text' => 'Die Seite war zu lange geöffnet und Ihre Sitzung ist abgelaufen. Laden Sie die Seite neu und beginnen Sie erneut: Ihre Eingaben wurden nicht gespeichert.',
    ],
    '429' => [
        'title' => 'Zu viele Anfragen',
        'text' => 'Sie haben in kurzer Zeit zu viele Anfragen gesendet. Warten Sie einen Moment, bevor Sie es erneut versuchen.',
    ],
    '500' => [
        'title' => 'Serverfehler',
        'text' => 'Auf unserer Seite ist ein Problem aufgetreten, es wurde protokolliert. Versuchen Sie es in einigen Augenblicken erneut; besteht es weiter, melden Sie es uns auf Discord oder GitHub.',
    ],
    '503' => [
        'title' => 'Dienst vorübergehend nicht verfügbar',
        'text' => 'Die Website wird gewartet oder ist stark ausgelastet. Versuchen Sie es in einigen Minuten erneut.',
    ],
    'default' => [
        'title' => 'Etwas ist schiefgelaufen',
        'text' => 'Die Seite konnte nicht angezeigt werden. Versuchen Sie es erneut oder beginnen Sie erneut auf der Startseite.',
    ],
];
