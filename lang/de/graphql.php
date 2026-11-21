<?php

/*
 * Fehler der GraphQL-API (app/GraphQL/Support/QueryLimitErrorHandler.php). Nur für den Server:
 * wird nicht an das Frontend gesendet (siehe App\Support\Translations::SERVER_ONLY).
 */

return [
    'limits' => [
        'query_too_complex' => 'Abfrage zu aufwendig: :value Punkte, erlaubt sind höchstens :max. Fordern Sie weniger Einträge pro Seite an (first), lassen Sie verschachtelte Listen oder aufwendige Felder (sheet, profile, audit, route) weg oder teilen Sie die Abfrage auf.',
        'query_too_deep' => 'Abfrage zu tief verschachtelt: :value Ebenen unter der Wurzel, erlaubt sind höchstens :max. Teilen Sie die Abfrage auf.',
    ],
];
