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
            'insee' => 'INSEE, Code officiel géographique (Codes und Namen der französischen Departements und Regionen), Licence Ouverte 2.0',
            'gallica' => 'Quelle: gallica.bnf.fr / BnF; Katalogdaten der Bibliothèque nationale de France, Licence Ouverte, mit dem Abrufdatum (digitalisierte Dokumente: Nutzungsbedingungen von Gallica)',
            'sncf-archives' => 'Quelle: SNCF, Archiv- und Dokumentationsdienst der SNCF-Gruppe (Datensatz „Archives SNCF“), Open Database License (ODbL); digitalisierte Dokumente: Nutzungsbedingungen von archives.sncf.com',
        ],
    ],

    'errors' => [
        'invalid_day' => 'Ungültiger Tag: Geben Sie einen existierenden Monat (1 bis 12) und Tag des Monats an, beide oder keinen (Standard: heute).',
        'invalid_period' => 'Ungültiger Zeitraum: Geben Sie entweder `year` oder `decade` (Vielfaches von 10) an, innerhalb der Chronologie (1827 bis heute).',
        'unknown_arguments' => 'Unbekannte Argumente: :names.',
        'line_not_found' => 'Strecke nicht gefunden: „:query“. Suchen Sie ihre Kennung mit dem Werkzeug search (Vorschläge anbei).',
        'no_geometry' => 'Die Trasse von „:line“ existiert für die Epoche :year nicht: kein Streckenkilometer.',
        'profile_not_computed' => 'Das Längsprofil von „:line“ ist noch nicht berechnet.',
        'audit_not_computed' => 'Die Prüfung der Abschnitte von „:line“ ist für :year noch nicht berechnet.',
        'station_not_found' => 'Bahnhof nicht gefunden: „:query“. Suchen Sie seine Kennung mit dem Werkzeug search (Vorschläge anbei).',
        'material_not_found' => 'Fahrzeugbaureihe nicht gefunden: „:query“. Suchen Sie ihre Kennung mit dem Werkzeug search oder materials (Vorschläge anbei).',
        'accident_not_found' => 'Unfall nicht gefunden: „:query“. Suchen Sie seine Kennung mit dem Werkzeug accidents (Filter q).',
        'journal_not_found' => 'Logbuch-Artikel nicht gefunden: „:query“. Rufen Sie das Werkzeug journal ohne Argumente auf, um die Artikel aufzulisten.',
        'era_not_found' => 'Keine Netzkarte für :year (siehe verfügbare Epochen).',
        'same_era' => 'Beide Jahre liegen in derselben Epoche der Karte (:year): Wählen Sie zwei verschiedene Jahrzehnte (siehe verfügbare Epochen).',
        'invalid_area' => 'Ungültiges Gebiet: Geben Sie entweder ein vollständiges Rechteck (south, west, north, east, mit north > south und east > west) oder einen vollständigen Kreis (lat, lng, radius_km) an, nicht beides.',
        'area_too_large' => 'Gebiet zu groß: Ein Rechteck ist höchstens :height° hoch und :width° breit, ein Kreis hat höchstens :radius km Radius. Teilen Sie das Gebiet auf oder nutzen Sie network_at_era für ganz Frankreich.',
        'area_too_small' => 'Gebiet zu klein: Ein Rechteck hat mindestens :side° Seitenlänge, ein Kreis mindestens :radius km Radius (die Trassen mancher Quellen dürfen nicht weitergegeben werden).',
        'graph_not_built' => 'Der Streckengraph dieser Epoche ist noch nicht berechnet: derzeit keine Route möglich.',
        'ambiguous_station' => 'Mehrere Bahnhöfe passen: Geben Sie einen Slug aus den Kandidaten an.',
        'same_station' => 'Abfahrt und Ankunft bezeichnen denselben Bahnhof.',
        'station_not_in_network' => 'Einer der Bahnhöfe wird vom damals betriebenen Netz nicht bedient.',
        'no_path' => 'Keine Route zwischen :from und :to im damals betriebenen Netz.',
        'invalid_uri' => 'Ungültige Ressourcenadresse: erwartet vff://{lang}/lines/{slug}, lang = fr, en oder de.',
        'event_not_on_line' => 'Das Ereignis :event gehört nicht zur Strecke :line. Verwenden Sie eine ID aus line_events oder aus line_stories ohne `event`.',
        'no_story' => 'Das Ereignis :event hat keine Geschichte: weniger als 3 Bilder und Dokumente, die höchstens 5 Jahre entfernt datiert sind (davon 2 Bilder), oder ein Ereignistyp ohne Geschichte.',
        'figures_not_computed' => 'Diese Zahlen sind auf diesem Server noch nicht berechnet: Versuchen Sie es später erneut.',
        'company_not_found' => 'Gesellschaft nicht gefunden: „:query“. Die verfügbaren Adressen stehen in `available` (Werkzeug companies ohne Argument).',
        'commune_not_found' => 'Gemeinde nicht gefunden oder ohne Bahn: „:query“. Geben Sie ihren INSEE-Code (z. B. 16015) oder ihren genauen Namen an; nur Gemeinden mit einem Bahnhof oder einer Strecke haben ein Blatt.',
        'commune_ambiguous' => 'Mehrere Gemeinden passen zu „:query“: Geben Sie `department` an oder übergeben Sie den INSEE-Code einer der Kandidatinnen.',
        'department_not_found' => 'Département nicht gefunden oder ohne Bahn: „:query“. Geben Sie seinen INSEE-Code (z. B. 16, 2A, 971) oder seinen Namen an (siehe list_departments).',
        'invalid_pk' => 'Ungültiger Streckenkilometer: „:pk“. Geben Sie Kilometer (120, 120,5 oder 120+500) oder `pk_m` in Metern an.',
        'no_stops' => 'Die Strecke „:line“ hat keinen nach Streckenkilometer verorteten Bahnhof: keine Position möglich.',
        'pk_out_of_range' => 'Der Streckenkilometer :pk liegt außerhalb der Strecke, die von :from bis :to reicht (siehe `bounds`).',
    ],

    'truncated' => 'Antwort gekürzt, um unter der Höchstgröße zu bleiben: Grenzen Sie die Anfrage ein (Zeitraum, Typen, Limit).',
    'truncated_page' => 'Seite gekürzt, um unter der Höchstgröße zu bleiben: next_cursor setzt beim ersten nicht gelieferten Element fort, es geht nichts verloren.',
    'redirected' => [
        'line' => 'Die Kennung „:requested“ ist die alte Adresse einer umbenannten Strecke: Diese Antwort ist ihr aktuelles Datenblatt, „:current“. Verwenden Sie künftig „:current“ und sagen Sie es dem Nutzer, wenn er die alte Kennung angegeben hat.',
        'station' => 'Die Kennung „:requested“ ist die alte Adresse eines Bahnhofs, der mit einem anderen Eintrag zusammengeführt wurde (derselbe Bahnhof): Diese Antwort ist der aktuelle Eintrag, „:current“. Verwenden Sie künftig „:current“.',
        'accident' => 'Die Kennung „:requested“ ist die alte Adresse eines umbenannten oder zusammengeführten Unfalleintrags (derselbe Unfall): Diese Antwort ist der aktuelle Eintrag, „:current“. Verwenden Sie künftig „:current“.',
    ],
    'between' => 'zwischen :from und :to (keine Quelle datiert diese Änderung)',
    'today' => 'heute',
    'dates' => [
        'unknown_after' => 'Datum unbekannt, nach :year (keine Quelle nennt es)',
        'unknown' => 'Datum unbekannt (keine Quelle nennt es)',
    ],

    'projects' => [
        'planned_note' => 'Ein Schritt mit `done` = false ist ein von seiner Quelle GEPLANTES Datum, nie sicher: Schreiben Sie „geplant für <Datum> laut <source.label>“. Ein in Betrieb genommenes Projekt verlässt diese Liste.',
    ],

    'preserved' => [
        'coverage_note' => 'Liste der Fahrzeuge, die Quellen als erhalten nennen, kein Inventar: Ein fehlendes Fahrzeug ist nicht unbedingt verschrottet, und der Zustand ist der, den die Quelle zum Zeitpunkt der Lektüre angab.',
    ],

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
        'signalling_history' => 'Signalisierung und Zugbeeinflussung im Lauf der Zeit',
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

    // Invites (prompts) MCP : message envoyé à l'assistant, dans la langue demandée (app/Mcp/Prompts).
    'prompts' => [
        'language' => 'Deutsch',
        'present_line' => [
            'message' => 'Stellen Sie die Strecke „:line“ mit den Daten von VFF (Französische Bahnstrecken, vff-project.org) vor.
1. :identify
2. Lesen Sie ihr Datenblatt mit get_line, einschließlich der Ereignisse. Ist die Chronik gekürzt (truncated), ergänzen Sie sie mit line_events.
3. Schreiben Sie einige Absätze: wo die Strecke beginnt und endet, ihre Länge, Elektrifizierung und Signaltechnik, die wichtigen Daten (Eröffnung, Stilllegungen, Elektrifizierung, Abbau) und ihren heutigen Zustand.
4. Nennen Sie zu jedem Datum und jeder Zahl die Quelle (Feld source und Link source_url). Geben Sie ein Datum nur so genau an, wie es bekannt ist: Tag, Monat oder Jahr. Eine Phase mit der Genauigkeit „range“ ist durch keine Quelle datiert: Sagen Sie nur, dass sie zwischen zwei Jahrzehnten stattfand.
5. Schreiben Sie mit eigenen Worten und kopieren Sie keinen Text aus Wikipedia oder einer anderen Quelle.
6. Schließen Sie mit dem Link vff_url des Datenblatts und den Nennungen aus dem Block attribution.
Antworten Sie auf :language.',
            'found' => 'Die Strecke ist „:name“ (Kennung :slug): Übergeben Sie diese Kennung an die Werkzeuge. Ihr Datenblatt in Markdown steht auch in der Ressource vff://:lang/lines/:slug.',
            'search' => 'Suchen Sie ihre Kennung mit search {"query": ":line", "kinds": ["lines"]}. Passen mehrere Strecken, fragen Sie nach, welche gemeint ist, bevor Sie fortfahren.',
        ],
        'compare_region_eras' => [
            'message' => 'Vergleichen Sie das Eisenbahnnetz der Region :region (INSEE-Code :code) in :from und in :to mit den Daten von VFF. Departements der Region: :departments.
1. Zählen Sie für jede Epoche die offenen und stillgelegten Strecken mit list_lines {"region": ":code", "era": <Jahr>, "facets": true, "limit": 1} und die Bahnhöfe nach Status mit list_stations {"region": ":code", "era": <Jahr>, "facets": true, "limit": 1}.
2. Ermitteln Sie die Änderungen zwischen beiden Daten mit network_events {"region": ":code", "from": ":events_from", "to": ":events_to"} (Stilllegungen, Eröffnungen, Elektrifizierungen, Abbau) und folgen Sie next_cursor, solange has_more true ist.
3. Zum Vergleich mit dem ganzen Land liefert compare_eras {"from": :from, "to": :to} dieselben Zahlen für ganz Frankreich.
4. Zeigen Sie eine Tabelle der beiden Epochen und danach die wichtigsten Änderungen Strecke für Strecke, jeweils mit Datum, Genauigkeit und Quelle.
5. Erklären Sie, dass eine Strecke zur Region zählt, wenn sie dort einen Bahnhof bedient oder ihr Datenblatt eines ihrer Departements nennt. Die VFF-Karte ist eine Momentaufnahme zum 31. Dezember jedes Jahrzehnts; :era_note
6. Schließen Sie mit den Nennungen aus dem Block attribution.
Antworten Sie auf :language.',
            'current' => 'die Epoche :to beschreibt das heutige Netz.',
            'past' => 'beide Epochen sind Momentaufnahmen zum 31. Dezember ihres Jahres.',
        ],
        'station_story' => [
            'message' => 'Erzählen Sie die Geschichte des Bahnhofs „:station“ mit den Daten von VFF.
1. :identify
2. Lesen Sie sein Datenblatt mit get_station: Eröffnungs- und Schließungsdatum, Status in jedem Jahrzehnt, bediente Strecken mit ihren Bedienungsjahren, Quellen.
3. Für die Strecken, die ihn bedienen, liefert get_line (mit include_events auf false) Eröffnung und Stilllegung. Ergänzen Sie Unfälle in der Nähe des Bahnhofs mit accidents {"station": "<Kennung>"} und Archivbilder mit media {"station": "<Kennung>"}.
4. Erzählen Sie der Reihe nach: die Eröffnung, hinzukommende oder stillgelegte Strecken, Statusänderungen, die heutige Lage. Ein Jahrzehnt mit dem Status „uncertain“ ist durch die Gleise der Zeit nicht bestätigt: Weisen Sie darauf hin.
5. Geben Sie jedes Datum mit Genauigkeit und Quelle an, schreiben Sie mit eigenen Worten, ohne Text aus einer Quelle zu kopieren, und zeigen Sie jedes Bild mit seiner Nennung.
6. Schließen Sie mit dem Link vff_url des Datenblatts und den Nennungen aus dem Block attribution.
Antworten Sie auf :language.',
            'found' => 'Der Bahnhof ist „:name“ (:city, Kennung :slug): Übergeben Sie diese Kennung an die Werkzeuge.',
            'search' => 'Suchen Sie seine Kennung mit search {"query": ":station", "kinds": ["stations"]}. Manchmal tragen mehrere Bahnhöfe denselben Namen: Fragen Sie im Zweifel nach, welcher gemeint ist, bevor Sie fortfahren.',
        ],
    ],
];
