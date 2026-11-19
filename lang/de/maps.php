<?php

return [
    'meta_title' => 'Karte des französischen Eisenbahnnetzes',
    'meta_description' => 'Interaktive Karte des französischen Eisenbahnnetzes, in Betrieb und verschwunden, Epoche für Epoche.',

    'hero' => [
        'title' => 'Die Karte der französischen Eisenbahn, von 1830 bis heute',
        'subtitle' => 'Strecken in Betrieb, stillgelegt oder abgebaut, Jahrzehnt für Jahrzehnt. Klicken Sie auf eine Strecke, um ihre Seite zu öffnen.',
    ],

    // Überschrift (vorgelesen, nicht angezeigt) des Kartenbereichs.
    'map_title' => 'Netzkarte',
    // Kurzlinks neben dem Titel, zu den Abschnitten unter der Karte.
    'jumps_label' => 'Auf dieser Seite',
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
        'none' => 'Keine',
        'accidents' => 'Unfälle',
        'level_crossings' => 'Bahnübergänge',
        'signalling' => 'Streckenblock',
    ],

    // Schaltfläche „Ebenen“ der Werkzeugleiste: VFF-Ebenen zum Ankreuzen, höchstens ein Hintergrund von OpenRailwayMap.
    'layers' => [
        'button' => 'Ebenen',
        'active_one' => ':count Ebene angezeigt',
        'active_other' => ':count Ebenen angezeigt',
        'data_legend' => 'Zur Karte hinzufügen',
        'today_hint' => 'Bahnübergänge und Streckenblock: nur das heutige Netz.',
        'orm_legend' => 'Hintergrund OpenRailwayMap',
        'orm_hint' => 'Jeweils ein Hintergrund, unter den Strecken von VFF.',
        'current_only_both' => 'Bahnübergänge und Streckenblock sind nur für das heutige Netz bekannt.',
        'orm' => [
            'standard' => 'Infrastruktur',
            'maxspeed' => 'Höchstgeschwindigkeiten',
            'signals' => 'Signale',
            'electrification' => 'Elektrifizierung',
            'gauge' => 'Spurweite',
        ],
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
        'station_active' => 'Für den Personenverkehr (und Güterverkehr) geöffnet',
        'station_tourist' => 'Museumsbahn',
        'station_freight' => 'Nur Güterverkehr',
        'station_uncertain' => 'Unsicher (kein bekanntes Gleis in dieser Zeit)',
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
        'other' => 'Andere Spannung (12 kV der Midi-Bahn…)',
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
        'fallback_line_name' => 'Streckensegment',
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
        'iframe_title' => 'Interaktive Karte des französischen Eisenbahnnetzes - VFF',
        'preview_title' => 'Vorschau der eingebetteten Karte',
        'credit' => 'Karte: VFF',
    ],

    // Abschnitt „Die Karte lesen“ unter der Karte.
    'read' => [
        'title' => 'Die Karte lesen',
        'era_term' => 'Epoche',
        'era_text' => 'Die Zeitleiste unter der Karte wählt das Jahrzehnt, von 1830 bis heute. Die Wiedergabetaste zeigt die Jahrzehnte nacheinander.',
        'color_term' => 'Farbe',
        'color_text' => 'Bei einer Strecke in Betrieb zeigt die Farbe, was darauf verkehrt: Violett für den Hochgeschwindigkeitsverkehr, Orange für das konventionelle Netz mit Personen- und Güterverkehr, Blau für reinen Güterverkehr, Grün für eine von einem Verein betriebene Strecke. Die Modi Elektrifizierung und Spurweite der Legende färben dieselben Strecken nach Spannung oder Spurweite.',
        'line_term' => 'Linie',
        'line_text' => 'Durchgezogene Linie: Strecke in Betrieb. Rot gestrichelt: stillgelegte Strecke, deren Gleis noch vorhanden ist. Grau gepunktet: Gleis abgebaut oder Trasse beseitigt.',
        'sheet_term' => 'Streckenseiten',
        'sheet_text' => 'Klicken Sie auf eine Strecke, um ihren Status in der gewählten Epoche zu sehen und ihre Seite zu öffnen. Bahnhöfe erscheinen beim Hineinzoomen.',
        'route_term' => 'Verbindung',
        'route_text' => 'Geben Sie zwei Bahnhöfe und ein Jahr an: Die Karte zeichnet den Weg auf dem damals betriebenen Netz und listet die befahrenen Strecken auf. Mit „Mit einer anderen Epoche vergleichen“ wird dieselbe Fahrt für ein zweites Jahr gezeichnet, die ältere gestrichelt mit blauem Rand.',
        'layers_term' => 'Ebenen',
        'layers_text' => 'Die Schaltfläche Ebenen über der Karte zeigt mehrere Ebenen zugleich. Ein Hintergrund von OpenRailwayMap (jeweils einer) blendet die heutige Infrastruktur ein: Signale, Geschwindigkeiten, Elektrifizierung. Die Schaltfläche mit der Uhr oben rechts auf der Karte zeigt alte Karten des IGN. Die Ebene Unfälle zeigt die Unfälle bis zur angezeigten Epoche; ein blasser Kreis bedeutet, dass nur die Gemeinde bekannt ist. Die Ebene Bahnübergänge zeigt die von SNCF Réseau veröffentlichten Übergänge, ab dem Maßstab eines Départements. Die Ebene Streckenblock färbt die heutigen Strecken nach ihrem Blocksystem.',
        'views_term' => 'Epochen vergleichen',
        'views_text' => 'Die Auswahl „Ansicht“ über der Karte vergleicht zwei Jahrzehnte (dazwischen eröffnete, stillgelegte oder abgebaute Strecken) oder legt alle Epochen übereinander, damit Sie sehen, wann jede Strecke den Betrieb verlor. Die Adresse der Seite behält diese Auswahl: Sie können sie teilen.',
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
    // Ansichten nach Epoche (Auswahl „Ansicht“ über der Karte).
    'view' => [
        'label' => 'Ansicht',
        'era' => 'Eine Epoche',
        'compare' => 'Vergleichen',
        'stack' => 'Alle Epochen',
    ],

    // Vergleich zweier Jahrzehnte (Leiste unter der Karte, Legende, Popups).
    'compare' => [
        'aria_label' => 'Vergleich zweier Jahrzehnte',
        'from' => 'Von',
        'to' => 'bis',
        'legend_title' => 'Von :from bis :to',
        'today_inline' => 'heute',
        'loading' => 'Unterschiede werden berechnet …',
        'error' => 'Die Unterschiede können gerade nicht berechnet werden.',
        'opened' => 'Eröffnet oder wiedereröffnet',
        'closed' => 'Stillgelegt, Gleis noch vorhanden',
        'removed' => 'Abgebaut oder verschwunden',
        'kept' => 'Zu beiden Zeitpunkten in Betrieb',
        'balance' => 'Strecken in Betrieb: :before km (:from), dann :after km (:to).',
        'none' => 'Keine Änderung zwischen diesen beiden Jahrzehnten.',
        'announce' => 'Zwischen :from und :to: :opened km eröffnet oder wiedereröffnet, :closed km stillgelegt mit Gleis, :removed km abgebaut oder verschwunden. Strecken in Betrieb: :before km, dann :after km.',
        'popup' => [
            'opened' => 'Zwischen :from und :to eröffnet oder wiedereröffnet',
            'closed' => 'Zwischen :from und :to stillgelegt, Gleis noch vorhanden',
            'removed' => 'Zwischen :from und :to abgebaut oder verschwunden',
            'kept' => 'Zu beiden Zeitpunkten in Betrieb (:from, :to)',
        ],
    ],

    // Alle Epochen übereinander (Farbton = letztes Jahrzehnt in Betrieb).
    'stack' => [
        'legend_title' => 'Alle Epochen',
        'intro' => 'Jede Strecke trägt den Farbton des letzten Jahrzehnts, in dem sie in Betrieb war: Je dunkler, desto länger blieb sie bestehen.',
        'group' => 'Letztes Jahrzehnt in Betrieb',
        'until' => ':year oder früher',
        'range' => ':from bis :to',
        'today' => 'Heute noch in Betrieb',
        'note' => 'Jedes Jahrzehnt zeigt das Netz am 31. Dezember des jeweiligen Jahres.',
        'popup_single' => 'Nur auf der Karte von :year in Betrieb',
        'popup_span' => 'Auf den Karten von :from bis :to in Betrieb',
        'popup_today' => 'Seit der Karte von :from in Betrieb, bis heute',
    ],

    // Ebene „Unfälle“ (Auswahl „Ebene“): Legende, Anzahl und Infofenster. Sachlicher, nüchterner Ton.
    'accidents' => [
        'title' => 'Unfälle',
        'until' => 'Bis :year',
        'until_today' => 'Bis heute',
        'between' => 'Von :from bis :to',
        'all' => 'Alle Daten',
        'count_one' => ':count verorteter Unfall',
        'count_other' => ':count verortete Unfälle',
        'count_none' => 'Kein verorteter Unfall in diesem Zeitraum',
        'point_exact' => 'Ort bekannt',
        'point_commune' => 'Nur die Gemeinde ist bekannt',
        'note' => 'Unfälle ohne bekannten Ort stehen nur in der Liste.',
        'list_link' => 'Liste der Unfälle',
        'loading' => 'Unfälle werden geladen…',
        'error' => 'Die Unfälle konnten nicht geladen werden. Entfernen Sie das Häkchen und setzen Sie es erneut, um es noch einmal zu versuchen.',
        'view_sheet' => 'Zur Seite des Unfalls',
    ],

    // Calque « Passages à niveau » (SNCF Réseau) : légende, bulle.
    'level_crossings' => [
        'title' => 'Bahnübergänge',
        'zoom_hint' => 'Zoomen Sie in die Karte: Bahnübergänge erscheinen ab dem Maßstab eines Départements.',
        'current_only' => 'Bahnübergänge sind nur für das heutige Netz bekannt.',
        'show_today' => 'Heutige Karte anzeigen',
        'note' => 'Ein Übergang auf mehreren Strecken wird nur einmal gezeichnet.',
        'source' => 'Quelle: SNCF Réseau (ODbL)',
        'pk' => 'km',
        'line' => 'Strecke',
        'fallback_title' => 'Bahnübergang',
        'view_line' => 'Streckenseite ansehen',
    ],

    // Calque « Signalisation » : modes de cantonnement (le KVB n'en est pas un), légende, bulle.
    'signalling' => [
        'title' => 'Streckenblock',
        'intro' => 'Blocksystem, das die Züge in Abstand hält, im heutigen Netz.',
        'families' => [
            'automatic_block' => 'Selbsttätiger Lichtblock (BAL)',
            'restricted_block' => 'Selbsttätiger Block mit eingeschränkter Permissivität (BAPR)',
            'manual_block' => 'Handblock (gestrichelt)',
            'telephone' => 'Telefonblock oder CAPI (gepunktet)',
            'cab' => 'Führerstandssignalisierung (TVM, ETCS)',
        ],
        'kvb_note' => 'Die KVB überwacht die Geschwindigkeit der Züge und ist kein Blocksystem: Sie steht in der Infoblase jedes Abschnitts.',
        'placement_note' => 'Die Grenzen der Abschnitte werden nach den Streckenkilometern gesetzt, in 9 von 10 Fällen auf weniger als 200 m genau.',
        'current_only' => 'Der Streckenblock ist nur für das heutige Netz bekannt.',
        'block_label' => 'Blocksystem',
        'cab_label' => 'Führerstandssignalisierung',
        'protection_label' => 'Geschwindigkeitsüberwachung',
        'pk_label' => 'km',
        'source' => 'Quelle: SNCF Réseau (ODbL). Grenzen auf einige hundert Meter genau gesetzt.',
    ],

    // Schaltfläche „Mich orten“: Meldungen, wenn der Standort nicht verfügbar ist.
    'geolocate' => [
        'denied' => 'Sie haben den Zugriff auf Ihren Standort nicht erlaubt. Erlauben Sie ihn in den Website-Einstellungen Ihres Browsers und klicken Sie dann erneut auf „Mich orten“.',
        'unavailable' => 'Ihr Standort konnte nicht ermittelt werden. Prüfen Sie, ob die Standortdienste Ihres Geräts aktiviert sind, und versuchen Sie es erneut.',
        'dismiss' => 'Meldung schließen',
    ],

    'controls' => [
        'map' => 'Interaktive Karte des Eisenbahnnetzes',
        'zoom_in' => 'Vergrößern',
        'zoom_out' => 'Verkleinern',
        'reset_bearing' => 'Ziehen, um die Karte zu drehen, klicken, um nach Norden auszurichten',
        'fullscreen_enter' => 'Vollbild anzeigen',
        'fullscreen_exit' => 'Vollbild beenden',
        'attribution' => 'Kartennachweise ein- oder ausblenden',
        'map_feedback' => 'Fehler in der Hintergrundkarte melden',
        'popup_close' => 'Popup schließen',
        'locate' => 'Mich orten (Ihr Standort dient nur dazu, die Karte zu zentrieren: Er wird weder an VFF gesendet noch gespeichert)',
        'locate_unavailable' => 'Ortung nicht verfügbar: Zugriff verweigert oder vom Browser nicht unterstützt',
    ],
];
