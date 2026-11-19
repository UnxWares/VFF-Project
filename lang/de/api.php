<?php

// Seite „API-Zugang“ (/api). Begriffe: „Endpunkt“, „Epoche“, „Referenz“ für die erzeugte Dokumentation.
return [
    'meta_title' => 'API-Zugang',
    'meta_description' => 'Zugang zur VFF-API: REST-Endpunkte, GraphQL-API, OpenAPI-Spezifikation und Beispielabfragen. Nur lesend, ohne Schlüssel und ohne Konto.',

    'hero' => [
        'title' => 'Die Daten von VFF für Ihre eigenen Werkzeuge',
        'subtitle' => 'Strecken, Bahnhöfe, Chroniken und Streckenverläufe des Eisenbahnnetzes von 1830 bis heute, als JSON, GeoJSON, Vektorkacheln und GTFS. Nur lesend, ohne Schlüssel und ohne Konto.',
    ],

    'docs' => [
        'title' => 'Dokumentation',
        'intro' => 'Alle beschreiben dieselben Daten: Wählen Sie die Oberfläche, die Ihnen am besten zusagt.',
        'items' => [
            'scalar' => ['title' => 'REST-Referenz (Scalar)', 'text' => 'Die Hauptreferenz mit Live-Anfragen und Codebeispielen.'],
            'swagger' => ['title' => 'REST-Referenz (Swagger UI)', 'text' => 'Dieselbe Spezifikation in einer klassischeren Oberfläche.'],
            'openapi' => ['title' => 'OpenAPI-3.1-Spezifikation', 'text' => 'JSON-Datei zum Import in Postman, Insomnia oder einen Client-Generator.'],
            'graphql' => ['title' => 'GraphQL-Referenz', 'text' => 'Typen, Abfragen und Beispiele des Schemas.'],
            'graphiql' => ['title' => 'GraphiQL', 'text' => 'Konsole zum Schreiben und Ausführen von GraphQL-Abfragen.'],
            'schema' => ['title' => 'GraphQL-Schema (SDL)', 'text' => 'Das vollständige Schema als Text.'],
        ],
    ],

    'access' => [
        'title' => 'Zugang und Grenzen',
        'readonly' => ['title' => 'Nur lesend', 'text' => 'Alle REST-Anfragen sind GET-Anfragen; GraphQL bietet nur Leseabfragen.'],
        'no_key' => ['title' => 'Ohne Schlüssel und Konto', 'text' => 'Keine Registrierung: Rufen Sie die Adressen direkt auf.'],
        'limit' => ['title' => '300 Anfragen pro Minute', 'text' => 'Grenze pro IP-Adresse, für REST wie für GraphQL. Darüber antwortet der Server mit HTTP 429.'],
        'licence' => ['title' => 'Lizenzen der Daten', 'text' => 'Nennen Sie „VFF - Voies Ferrées de France“ mit einem Link auf vff-project.org. Jede Quelle behält ihre eigene Lizenz: Einzelheiten stehen in den Referenzen.'],
    ],

    'endpoints' => [
        'title' => 'REST-Endpunkte',
        'intro' => 'Alle Adressen beginnen mit /api/v1 und antworten auf GET. In den Pfaden steht {year} für eine Zehnjahresepoche (1830 bis 2030) und {line} für die Kennung einer Strecke, zum Beispiel rfn-420000.',
        'label' => 'REST-Endpunkte der VFF-API',
        'items' => [
            'lines_geojson' => 'Streckenverläufe des Netzes in einer Epoche als GeoJSON (FeatureCollection von Segmenten).',
            'stations_geojson' => 'Bahnhöfe einer Epoche als GeoJSON. Geschlossene Bahnhöfe sind enthalten, solange in der Nähe ein Gleis besteht, mit ihrem Status.',
            'tiles' => 'Vektorkachel (MVT) einer Epoche, für eine MapLibre- oder OpenLayers-Quelle.',
            'gtfs' => 'Das Netz einer Epoche im Format GTFS Static (ZIP-Archiv).',
            'search' => 'Suche nach Namen in Strecken, Bahnhöfen und Fahrzeugen. Mindestens 2 Zeichen.',
            'history' => 'Chronik einer Strecke: datierte Ereignisse und ihr Zustand in jeder Epoche.',
            'pk' => 'Streckenkilometer eines Ortes auf einer Strecke, aus Breiten- und Längengrad.',
            'stops' => 'Bahnhöfe einer Strecke in der Reihenfolge der Streckenkilometer, in einer Epoche.',
            'signalling' => 'Kilometerabschnitte mit einheitlicher Signalisierung.',
            'media' => 'Fotos, Postkarten und Pläne zu einer Strecke, mit ihren Rechten.',
            'route' => 'Bahnverbindung zwischen zwei Bahnhöfen im Netz einer Epoche: Entfernung, Halte und Streckenverlauf.',
        ],
    ],

    'example' => [
        'title' => 'REST-Beispiele',
        'intro' => 'Drei Anfragen zum Kopieren in ein Terminal, danach die Antwort auf die zweite, so wie der Server sie liefert.',
        'curl_lines' => 'Streckenverläufe des heutigen Netzes (Epoche 2030) als GeoJSON',
        'curl_pk' => 'Streckenkilometer eines Ortes auf der Strecke Paris-Montparnasse - Brest',
        'curl_search' => 'Suche nach Namen',
        'response' => 'Antwort auf die zweite Anfrage: Der Punkt wird auf den nächstgelegenen Streckenverlauf gelegt (snap), hier 395 m entfernt.',
        'code_label' => 'Codebeispiel',
    ],

    'graphql' => [
        'title' => 'GraphQL-API',
        'text' => 'Für umfangreichere Abfragen liefert ein einziger GraphQL-Endpunkt, POST /graphql, eine Strecke mit ihren Segmenten und Ereignissen auf einmal. Das Schema steht in der Referenz; Ihre Abfragen können Sie in GraphiQL ausprobieren.',
        'links' => 'GraphQL-Dokumentation',
        'example_comment' => 'Eine Strecke mit ihren Ereignissen',
    ],

    'cta' => [
        'title' => 'Eine Frage, eine Idee?',
        'text' => 'Erzählen Sie uns auf Discord, was Sie bauen oder was fehlt, oder melden Sie ein Problem auf GitHub.',
        'discord' => 'Auf Discord austauschen',
        'github' => 'Code auf GitHub ansehen',
    ],
];
