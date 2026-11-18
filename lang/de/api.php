<?php

return [
    'meta_title' => 'API-Zugang',
    'meta_description' => 'Öffentliche API-Dokumentation von VFF (in Vorbereitung).',

    'hero' => [
        'eyebrow' => 'Öffentliche API',
        'title' => 'Erstellen Sie eigene Werkzeuge auf Grundlage unserer Daten',
        'subtitle' => 'Eine offene, dokumentierte und kostenfreie REST- und GeoJSON-API zur Entwicklung von Anwendungen, Visualisierungen und Forschungsarbeiten rund um das französische Schienennetz.',
        'status' => 'Phase 6 - Spezifikation in Arbeit',
    ],

    'endpoints' => [
        'title' => 'Geplante Endpunkte',
        'lines' => 'Übersicht und Detailinformationen zu Bahnstrecken, nach Epoche, Status und Region.',
        'segments' => 'Streckensegmente als GeoJSON oder MVT zur Einbindung in eine Karte von Drittanbietern.',
        'stations' => 'Gegenwärtige und historische Bahnhöfe, mit Eröffnungs- und Schließungsdaten.',
        'materials' => 'Fahrzeuge: Datenblätter, Betriebszeiträume und befahrene Strecken.',
        'events' => 'Historische Ereignisse: Einweihungen, Stilllegungen und Rückbauten.',
        'media' => 'Medien: geolokalisierte und datierte Archivfotografien.',
        'items' => [
            'lines_geojson' => 'GeoJSON-FeatureCollection aller Streckensegmente einer Epoche.',
            'stations_geojson' => 'FeatureCollection der in dieser Epoche offenen Bahnhöfe (nach Status gefiltert).',
            'tiles' => 'Mapbox-Vektorkachel (binär) für die Darstellung mit MapLibre. Eigenschaften: status, traffic_type, electrified, is_heritage, voltage_v, max_speed_kmh, line_slug, line_name, line_code.',
            'search' => 'Suche mit Autovervollständigung in Strecken, Bahnhöfen und Fahrzeugen. Mindestens 2 Zeichen.',
            'history' => 'Vollständige Geschichte: alle Ereignisse in zeitlicher Reihenfolge und der Status in jeder Epoche.',
            'pk' => 'Wandelt (lat, lng) mit PostGIS ST_LineLocatePoint in einen Streckenkilometer (PK) um. Genauigkeit unter einem Meter.',
            'stops' => 'Geordnete Folge der Bahnhöfe der Strecke (entspricht GTFS stop_times): Position, PK, Status in der Epoche, is_terminal.',
            'route' => 'Bahnverbindung zwischen 2 Bahnhöfen (Dijkstra auf dem materialisierten Graphen): Entfernung, geschätzte Dauer, Abschnitte, Polylinie.',
            'gtfs' => 'Vollständiger GTFS-Export des Netzes einer Epoche (agency, routes, stops, trips, stop_times, shapes, calendar), mit jedem GTFS-Standardwerkzeug kompatibel.',
        ],
    ],

    'format' => [
        'title' => 'Unterstützte Formate',
        'json' => 'JSON (klassisches REST)',
        'geojson' => 'GeoJSON (Kartenebenen)',
        'mvt' => 'MVT: Mapbox Vector Tiles (für MapLibre / Leaflet)',
    ],

    'example' => [
        'title' => 'Vorschau-Beispiel',
        'description' => 'Alle im Jahr 2000 in Betrieb befindlichen Strecken abrufen (um einen Eindruck vom Format zu gewinnen):',
        'response' => 'Erwartete Antwort:',
        'curl_lines' => 'Alle im Jahr 2030 in Betrieb befindlichen Strecken abrufen (GeoJSON)',
        'curl_pk' => 'Den Streckenkilometer eines Punktes auf der Strecke Paris - Brest finden',
        'curl_search' => 'Suche mit Autovervollständigung (Strecken, Bahnhöfe und Fahrzeuge)',
    ],

    'graphql' => [
        'title' => 'GraphQL-API (Lighthouse)',
        'text' => 'Neben der REST-API bietet VFF einen einzigen GraphQL-Endpunkt für komplexe Abfragen: eine Strecke mit ihren Segmenten, Ereignissen, Fahrzeugen und Bahnhöfen in einer einzigen Anfrage.',
        'explorer_desc' => 'Schema erkunden, Autovervollständigung, Abfragen ausführen',
        'endpoint_desc' => 'Ein einziger Endpunkt für alle Abfragen',
        'example_comment' => 'Beispiel: die Strecke Argentan - Granville mit ihren Bahnhöfen und Ereignissen',
    ],

    'openapi' => [
        'title' => 'Interaktive OpenAPI-3-Dokumentation',
        'text_before' => 'Die OpenAPI-3.1-Spezifikation wird automatisch aus den Laravel-Controllern erzeugt (mit',
        'text_middle' => ') und mit',
        'text_after' => 'dargestellt.',
        'docs' => 'Interaktive Dokumentation',
        'docs_desc' => 'Scalar-Oberfläche mit Live-Test',
        'spec' => 'OpenAPI-3.1-Spezifikation (JSON)',
        'spec_desc' => 'Zum Import in Postman, Insomnia…',
    ],

    'cta' => [
        'title' => 'Bleiben Sie auf dem Laufenden',
        'text' => 'Die API erscheint in Phase 6 der Roadmap. Verfolgen Sie den Fortschritt auf Discord oder GitHub.',
        'discord' => 'Discord',
        'github' => 'Den Code ansehen',
    ],
];
