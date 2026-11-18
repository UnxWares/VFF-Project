<?php

return [
    'meta_title' => 'Unsere Karten',
    'meta_description' => 'Interaktive Karte des französischen Schienennetzes, in Betrieb und verschwunden, Epoche für Epoche.',

    'hero' => [
        'title' => 'Die Karte der französischen Eisenbahn, von 1830 bis heute',
        'subtitle' => 'Strecken in Betrieb, stillgelegt oder abgebaut, Jahrzehnt für Jahrzehnt. Klicken Sie auf eine Strecke, um ihre Seite zu öffnen.',
    ],

    // Überschrift (vorgelesen, nicht angezeigt) des Kartenbereichs.
    'map_title' => 'Netzkarte',
    'era_selector' => 'Epoche',

    'search' => [
        'label' => 'Bahnstrecke oder Bahnhof suchen',
        'placeholder' => 'Bahnstrecke oder Bahnhof suchen…',
        'lines' => 'Bahnstrecken',
        'stations' => 'Bahnhöfe',
        'materials' => 'Fahrzeuge',
        'loading' => 'Suche läuft…',
        'results_one' => ':count Ergebnis',
        'results_other' => ':count Ergebnisse',
        'no_results' => 'Keine Bahnstrecke und kein Bahnhof entspricht „:query“.',
        'error' => 'Die Suche ist im Moment nicht verfügbar. Bitte versuchen Sie es gleich noch einmal.',
        'clear' => 'Suche löschen',
    ],

    'overlay' => [
        'label' => 'Ebene',
        'none' => 'Keine',
        'orm_standard' => 'OpenRailwayMap - Infrastruktur',
        'orm_maxspeed' => 'OpenRailwayMap - Höchstgeschwindigkeiten',
        'orm_signals' => 'OpenRailwayMap - Signale',
        'orm_electrification' => 'OpenRailwayMap - Elektrifizierung',
        'orm_gauge' => 'OpenRailwayMap - Spurweite',
    ],

    'legend' => [
        'heritage_train' => 'Von einem Verein betrieben: Museumsbahn',
        'heritage_velorail' => 'Von einem Verein betrieben: Draisinenbahn',
        'title' => 'Netz im Jahr :year',
        'title_current' => 'Heutiges Netz',
        'threatened' => 'Bedrohte Strecke (roter Schein)',
        'reopening' => 'Von VFF unterstützte Kampagne zur Wiedereröffnung (türkiser Schein)',
        'hint' => 'Klicken Sie auf einen Eintrag, um ihn ein- oder auszublenden.',
        'collapse' => 'Legende einklappen',
        'expand' => 'Legende',
        'expand_label' => 'Legende ausklappen',
        // Bahnhöfe (Farbe = Status im gewählten Jahrzehnt, siehe railStyle.js STATION_STYLES).
        'stations' => 'Bahnhöfe in dieser Zeit',
        'stations_hint' => 'Beim Hineinzoomen sichtbar.',
        'station_active' => 'Für den Personenverkehr geöffnet (und Güterverkehr)',
        'station_tourist' => 'Touristisch / Museumsbahn',
        'station_freight' => 'Nur Güterverkehr',
        'station_uncertain' => 'Unsicher (keine bekannte Strecke in dieser Zeit)',
        'station_closed' => 'Geschlossen',
    ],

    // Art des Vereinsbetriebs (line_segments.heritage_kind).
    'heritage_kind' => [
        'tourist_train' => 'Museumsbahn',
        'velorail' => 'Draisinenbahn',
        'museum' => 'Fahrendes Eisenbahnmuseum',
    ],

    // Farbe der Abschnitte IN BETRIEB = Verkehrsart im gewählten Jahrzehnt.
    'traffic' => [
        'title' => 'Netz in Betrieb',
        'lgv' => 'Schnellfahrstrecke (LGV)',
        'passenger' => 'Hochgeschwindigkeit (Personenverkehr)',
        'mixed' => 'Konventionelles Netz: Personen- und Güterverkehr',
        'freight' => 'Konventionelles Netz: nur Güterverkehr',
        'unknown' => 'Konventionelles Netz: Verkehr unbekannt',
    ],

    // Strichart der Abschnitte nach ihrem Status im gewählten Jahrzehnt.
    'status' => [
        'title' => 'Status des Abschnitts',
        'in_service' => 'In Betrieb',
        'single_track' => 'In Betrieb (eingleisig)',
        'service_track' => 'Betriebs- / Hafengleis',
        'construction' => 'Im Bau',
        'abandoned' => 'Stillgelegt (Gleis vorhanden)',
        'dismantled' => 'Gleis abgebaut',
        'razed' => 'Verschwunden (Trasse beseitigt)',
        'gone' => 'Abgebaut / verschwunden',
    ],

    // Anzeigemodus der Karte (Legende): Linienfarbe nach Verkehr, Elektrifizierung
    // oder Spurweite im gewählten Jahrzehnt (vff:enrich-era-segments).
    'mode' => [
        'label' => 'Strecken einfärben nach',
        'traffic' => 'Verkehr',
        'electrification' => 'Elektrifizierung',
        'gauge' => 'Spurweite',
    ],

    'electrification' => [
        'title' => 'Elektrifizierung zu dieser Zeit',
        'ac25' => '25 kV 50 Hz (Wechselstrom)',
        'dc1500' => '1,5 kV Gleichstrom',
        'dc3000' => '3 kV Gleichstrom',
        'ac15' => '15 kV 16,7 Hz (Wechselstrom)',
        'low' => '1.000 V und weniger (Stromschiene)',
        'other' => 'Andere Spannung (12 kV der Midi…)',
        'unknown_voltage' => 'Elektrifiziert, Spannung unbekannt',
        'unknown_date' => 'Heute elektrifiziert, Datum unbekannt',
        'none' => 'Nicht elektrifiziert',
        'since' => 'elektrifiziert seit :year',
    ],

    'gauge' => [
        'title' => 'Spurweite',
        'standard' => 'Normalspur (1.435 mm)',
        'metre' => 'Meterspur (1.000 mm)',
        'narrow' => 'Schmalspur (600, 700, 750 mm…)',
        'broad' => 'Breitspur',
        'unknown' => 'Spurweite unbekannt',
    ],

    'networks' => [
        'principal' => 'Hauptnetz',
        'secondaire' => 'Nebennetz (Lokalbahnen)',
        'militaire' => 'Militärbahnen',
        'particulier' => 'Privat- und Werksbahnen',
    ],

    'era' => [
        'no_data' => 'Für :year wurde noch kein Streckenverlauf rekonstruiert: Dieses Jahrzehnt wird gerade kartiert.',
        'no_data_short' => 'Noch keine Daten',
    ],

    // Zeitleiste. Das „aktuelle“ Jahrzehnt (2030) zeigt das heutige Netz: Es wird als „Heute“
    // angezeigt, nie als zukünftiges Jahr.
    'timeline' => [
        'aria_label' => 'Zeitleiste der Epochen',
        'group_aria' => 'Zeitraum',
        'slider' => 'Angezeigtes Jahrzehnt',
        'era' => 'Jahrzehnt',
        'today' => 'Heute',
        'today_caption' => 'Das heutige Netz',
        'since' => 'Seit :year',
        'value_today' => 'Heute, aktuelles Netz',
        'value_empty' => ':year, noch keine Daten',
        'play' => 'Jahrzehnte nacheinander abspielen',
        'pause' => 'Wiedergabe anhalten',
    ],

    // Streckeninfo beim Klick auf die Karte (LineDrawer) und Popup der eingebetteten Karte.
    'drawer' => [
        'fallback_line_name' => 'Abschnitt',
        'view_full_sheet' => 'Seite der Strecke öffnen',
        'view_station' => 'Seite des Bahnhofs öffnen',
        'suggest_edit' => 'Änderung vorschlagen',
        'close' => 'Streckeninfo schließen',
        'status' => 'Status',
        'status_at' => 'Status im Jahr :year',
        'status_today' => 'Heutiger Status',
        'max_speed' => 'Höchstgeschwindigkeit',
        'electrification' => 'Elektrifizierung',
        'traffic' => 'Verkehr',
        'network' => 'Netz',
        'heritage' => 'Vereinsbetrieb',
        'today' => 'heute',
    ],

    'embed' => [
        'button' => 'Einbetten',
        'title' => 'Die Karte auf Ihrer Website einbetten',
        'intro' => 'Stellen Sie die Karte ein, kopieren Sie dann den Code und fügen Sie ihn in Ihre Seite ein.',
        'width' => 'Breite',
        'width_hint' => 'In Pixeln oder Prozent, zum Beispiel 100% oder 800.',
        'height' => 'Höhe (px)',
        'theme' => 'Design',
        'theme_auto' => 'Automatisch (Einstellung des Besuchers)',
        'theme_light' => 'Hell',
        'theme_dark' => 'Dunkel',
        'mode' => 'Farbe der Strecken',
        'options' => 'Angezeigte Elemente',
        'show_timeline' => 'Zeitleiste der Epochen',
        'show_legend' => 'Legende',
        'show_vanished' => 'Stillgelegte und abgebaute Strecken',
        'show_attribution' => 'Link zu VFF (empfohlen)',
        'snippet_label' => 'In Ihre Seite einzufügender Code',
        'copy' => 'Code kopieren',
        'copied' => 'Code kopiert',
        'copy_failed' => 'Automatisches Kopieren ist nicht möglich: Der Code ist markiert, kopieren Sie ihn mit Strg+C (Cmd+C auf dem Mac).',
        'preview' => 'Vorschau',
        'iframe_title' => 'Interaktive Karte des französischen Schienennetzes - VFF',
        'preview_title' => 'Vorschau der eingebetteten Karte',
        'credit' => 'Karte: VFF',
    ],

    // Abschnitt „Die Karte lesen“ unter der Karte.
    'read' => [
        'title' => 'Die Karte lesen',
        'era_term' => 'Epoche',
        'era_text' => 'Die Zeitleiste unter der Karte wählt das Jahrzehnt, von 1830 bis heute. Die Wiedergabetaste zeigt die Jahrzehnte nacheinander.',
        'color_term' => 'Farbe',
        'color_text' => 'Bei einer Strecke in Betrieb zeigt die Farbe, was darauf verkehrt: Violett für den Hochgeschwindigkeitsverkehr, Orange für das klassische Netz mit Personen- und Güterverkehr, Blau für reinen Güterverkehr, Grün für eine von einem Verein betriebene Strecke. Die Modi Elektrifizierung und Spurweite der Legende färben dieselben Strecken nach Spannung oder Spurweite.',
        'line_term' => 'Strich',
        'line_text' => 'Durchgezogener Strich: in Betrieb. Rote Striche: stillgelegte Strecke, Gleis noch vorhanden. Graue Punkte: Gleis abgebaut oder Trasse verschwunden.',
        'sheet_term' => 'Streckenseiten',
        'sheet_text' => 'Klicken Sie auf eine Strecke, um ihren Status in der gewählten Epoche zu sehen und ihre Seite zu öffnen. Bahnhöfe erscheinen beim Hineinzoomen.',
        'route_term' => 'Reiseweg',
        'route_text' => 'Geben Sie zwei Bahnhöfe und ein Jahr an: Die Karte zeichnet den Weg auf dem damals betriebenen Netz und listet die befahrenen Strecken auf.',
        'layers_term' => 'Ebenen',
        'layers_text' => 'Die Ebene OpenRailwayMap zeigt die heutige Infrastruktur: Signale, Geschwindigkeiten, Elektrifizierung. Die Uhr-Schaltfläche oben rechts auf der Karte zeigt alte Karten des IGN.',
    ],

    'tech' => [
        'title' => 'Wie die Karte entsteht',
        'paragraph' => 'Die Karte wird mit MapLibre GL JS auf einem OpenStreetMap-Hintergrund gezeichnet. Die Strecken sind in einer PostgreSQL-Datenbank mit PostGIS gespeichert und werden als Vektorkacheln an den Browser gesendet: So bleibt die Karte flüssig, auch mit Tausenden von Streckenabschnitten aus zwei Jahrhunderten.',
        'credits' => 'Software und Datenquellen von VFF',
    ],

    'cta' => [
        'title' => 'Fehlt eine Strecke oder ist ein Verlauf falsch?',
        'text' => 'Schlagen Sie auf der Seite der Strecke eine Korrektur vor oder zeichnen Sie sie auf der Karte nach. Jeder Vorschlag wird geprüft, bevor er übernommen wird.',
        'contribute' => 'Korrektur vorschlagen',
        'discord' => 'Dem Discord beitreten',
    ],

    // Schaltflächen der Karte (MapLibre): Tooltips und von Screenreadern vorgelesene Namen.
    'controls' => [
        'map' => 'Interaktive Karte des Schienennetzes',
        'zoom_in' => 'Vergrößern',
        'zoom_out' => 'Verkleinern',
        'reset_bearing' => 'Ziehen, um die Karte zu drehen, klicken, um nach Norden auszurichten',
        'fullscreen_enter' => 'Vollbild anzeigen',
        'fullscreen_exit' => 'Vollbild beenden',
        'attribution' => 'Kartennachweise ein- oder ausblenden',
        'map_feedback' => 'Fehler in der Hintergrundkarte melden',
        'popup_close' => 'Popup schließen',
    ],
];
