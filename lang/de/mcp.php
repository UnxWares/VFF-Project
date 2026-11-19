<?php

// MCP-Server (app/Mcp, docs/MCP.md): Texte der Antworten an KI-Assistenten. Nur serverseitig.
return [
    'attribution' => [
        'notice' => 'Daten von Französische Bahnstrecken (Voies Ferrées de France, vff-project.org), Datenbank unter ODbL 1.0: Nennen Sie „Voies Ferrées de France (vff-project.org)“, den Link zum Eintrag und die folgenden Quellen.',
        'terms' => 'Die Daten von VFF dürfen unter der Open Database License (ODbL) 1.0 weiterverwendet werden, auch kommerziell: Nennen Sie „Voies Ferrées de France (vff-project.org)“ und seine Quellen, darunter „© OpenStreetMap-Mitwirkende“, und veröffentlichen Sie jede abgeleitete Datenbank unter derselben Lizenz. Ausnahme: Daten aus einer Quelle, die nur nicht kommerzielle Nutzung erlaubt (Feld source), dürfen nur nicht kommerziell und mit Nennung und Link der Quelle weiterverwendet werden.',
        'restricted' => '(keine offene Lizenz: Weiterverwendung nur mit dieser Nennung und dem Link zur Quelle)',
        'providers' => [
            'vff' => 'Französische Bahnstrecken (Voies Ferrées de France, vff-project.org), UnxWares, ODbL 1.0',
            'osm' => '© OpenStreetMap-Mitwirkende, ODbL 1.0 (https://www.openstreetmap.org/copyright)',
            'sncf' => 'SNCF Open Data (SNCF Réseau, SNCF Voyageurs, SNCF Gares & Connexions), ODbL 1.0',
            'ohm' => 'OpenHistoricalMap, CC0 1.0',
            'ign' => 'IGN (BD TOPO, historische BD ORTHO), Licence Ouverte 2.0',
            'wikipedia' => 'Wikipedia, CC BY-SA 4.0 (Texte); Fakten mit Link zum Artikel übernommen',
            'wikidata' => 'Wikidata, CC0 1.0',
            'commons' => 'Wikimedia Commons, Lizenz der jeweiligen Datei',
            'communes' => 'Etalab und IGN ADMIN EXPRESS (Verwaltungsgrenzen), ODbL',
            'jorf' => 'DILA, Journal officiel (Légifrance), Licence Ouverte',
            'archeoferroviaire' => 'archeoferroviaire.free.fr (J. Randé, F. Librini, K. Moreau), nicht kommerzielle Nutzung, mit Link zum Artikel',
            'lignes-oubliees' => 'lignes-oubliees.com, Angaben mit Herkunftsnennung und Link zum Eintrag wiedergegeben',
            'argentanwebferro' => 'Argentan Web Ferroviaire (Thierry Danglos), Fakten mit Link zum Artikel übernommen',
            'bea-tt' => 'BEA-TT (französische Untersuchungsstelle für Landverkehrsunfälle), Licence Ouverte 2.0 (Etalab); Gesamtübersicht der Untersuchungen und Links zu den Berichten, mit dem Aktualisierungsdatum der Übersicht',
        ],
    ],

    'errors' => [
        'unknown_arguments' => 'Unbekannte Argumente: :names.',
        'line_not_found' => 'Strecke nicht gefunden: „:query“. Suchen Sie ihre Kennung mit dem Werkzeug search (Vorschläge anbei).',
        'profile_not_computed' => 'Das Längsprofil von „:line“ ist noch nicht berechnet.',
        'station_not_found' => 'Bahnhof nicht gefunden: „:query“. Suchen Sie seine Kennung mit dem Werkzeug search (Vorschläge anbei).',
        'material_not_found' => 'Fahrzeugbaureihe nicht gefunden: „:query“. Suchen Sie ihre Kennung mit dem Werkzeug search oder materials (Vorschläge anbei).',
        'accident_not_found' => 'Unfall nicht gefunden: „:query“. Suchen Sie seine Kennung mit dem Werkzeug accidents (Filter q).',
        'era_not_found' => 'Keine Netzkarte für :year (siehe verfügbare Epochen).',
        'same_era' => 'Beide Jahre liegen in derselben Epoche der Karte (:year): Wählen Sie zwei verschiedene Jahrzehnte (siehe verfügbare Epochen).',
        'graph_not_built' => 'Der Streckengraph dieser Epoche ist noch nicht berechnet: derzeit keine Route möglich.',
        'ambiguous_station' => 'Mehrere Bahnhöfe passen: Geben Sie einen Slug aus den Kandidaten an.',
        'same_station' => 'Abfahrt und Ankunft bezeichnen denselben Bahnhof.',
        'station_not_in_network' => 'Einer der Bahnhöfe wird vom damals betriebenen Netz nicht bedient.',
        'no_path' => 'Keine Route zwischen :from und :to im damals betriebenen Netz.',
        'invalid_uri' => 'Ungültige Ressourcenadresse: erwartet vff://{lang}/lines/{slug}, lang = fr, en oder de.',
    ],

    'truncated' => 'Antwort gekürzt, um unter der Höchstgröße zu bleiben: Grenzen Sie die Anfrage ein (Zeitraum, Typen, Limit).',
    'truncated_page' => 'Seite gekürzt, um unter der Höchstgröße zu bleiben: next_cursor setzt beim ersten nicht gelieferten Element fort, es geht nichts verloren.',
    'between' => 'zwischen :from und :to (keine Quelle datiert diese Änderung)',
    'today' => 'heute',

    'accidents' => [
        'review_note' => 'Eintrag „zu prüfen“: Mindestens ein Punkt ist unsicher (Opferzahlen oder Daten, die je nach Quelle abweichen, Ort nur auf Gemeindeebene bekannt, unsichere Strecke). Stellen Sie ihn nicht als gesichert dar: Einzelheiten stehen in review.notes von get_accident.',
    ],

    'compare' => [
        'opened' => 'Zwischen den beiden Epochen eröffnet oder wiedereröffnet (in der zweiten in Betrieb, in der ersten nicht)',
        'closed' => 'In der zweiten Epoche stillgelegt, Gleis noch vorhanden, in der ersten nicht',
        'removed' => 'In der zweiten Epoche abgebaut oder verschwunden (in der ersten in Betrieb oder stillgelegt)',
        'kept' => 'In beiden Epochen in Betrieb',
    ],

    'traffic' => [
        'passenger' => 'Nur Personenverkehr',
    ],

    'electrification' => [
        'none' => 'Nicht elektrifiziert',
        'marginal' => 'Nur am Rand elektrifiziert (Bahnhofsgleise, Verbindungskurve)',
        'partial' => 'Teilweise elektrifiziert',
        'full' => 'Elektrifiziert',
    ],

    'route' => [
        'duration_note' => 'Richtwert bei durchschnittlich 100 km/h, kein historischer Fahrplan.',
    ],

    'markdown' => [
        'code' => 'Streckennummer',
        'length' => 'Länge der Trasse',
        'in_service_today' => 'heute in Betrieb',
        'max_speed' => 'Höchstgeschwindigkeit',
        'electrification' => 'Elektrifizierung',
        'signalling' => 'Signalisierung',
        'phases' => 'Phasen',
        'chronology' => 'Chronik',
        'source' => 'Quelle',
        'more_events' => ':n weitere Ereignisse: Werkzeug line_events.',
        'sources' => 'Quellen und Lizenzen',
        'sheet' => 'VFF-Eintrag',
        'licences_title' => 'Lizenzen und Quellenangaben der VFF-Daten',
        'licence' => 'Lizenz',
        'mention' => 'Nennung',
    ],
];
