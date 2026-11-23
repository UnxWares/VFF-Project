<?php

return [
    'meta_title' => 'VFF - Französische Bahnstrecken',
    'meta_description' => 'Eine gemeinschaftliche Enzyklopädie der französischen Eisenbahn von 1830 bis heute: Strecken, Bahnhöfe, eine Karte des Netzes für jedes Jahrzehnt und Archivmaterial mit Quellenangaben.',
    'share_image_alt' => 'Das französische Eisenbahnnetz in Betrieb, orange gezeichnet, mit Logo und Namen von Voies Ferrées de France',

    // Großes Titelbild (VFF-Scheibe zwischen zwei TGV): Zeile unter der Scheibe und zwei Aktionen.
    'hero' => [
        'tagline' => 'Die Enzyklopädie der französischen Bahnstrecken, von 1830 bis heute',
        'open_map' => 'Karte öffnen',
        'search' => 'Strecke oder Bahnhof suchen',
        // Hintergrund: Ausschnitt der Karte von Vuillemin (1865), siehe credits.media.partners.home_map_*.
        'credit' => 'Hintergrund: Eisenbahnkarte von Alexandre Vuillemin (1865), gemeinfrei',
    ],

    // Vorstellung (ursprüngliches Design: Logo, Titel, Text, zwei Schaltflächen).
    'whoarewe' => [
        'title' => 'VFF - Wo sich die Schienen kreuzen',
        'paragraph' => 'Als Projekt von UnxWares, getragen von seinen beiden Gesellschaftern und den Mitwirkenden, ist VFF eine gemeinschaftliche Enzyklopädie der französischen Eisenbahn von 1830 bis heute. Sie beschreibt jede Strecke, ob in Betrieb oder verschwunden: ihren Verlauf, ihre Bahnhöfe, ihre Daten und die Quelle jeder Angabe. Sie können sie lesen, Korrekturen vorschlagen und ihre Daten über die öffentliche API weiterverwenden.',
        'cta_history' => 'Unsere Geschichte',
        'cta_donate' => 'Spenden',
    ],

    // Hinweise zu den von VFF unterstützten Strecken. Titel, Text und Schaltfläche stammen aus der Datenbank.
    'advocacy' => [
        'label' => 'Von VFF unterstützte Strecken',
        'kind_threatened' => 'Bedrohte Strecke',
        'kind_reopening' => 'Kampagne für die Wiedereröffnung',
        'threatened' => 'Bedrohte Strecke',
        'reopening' => 'Für die Wiedereröffnung der Strecke',
        'see_line' => 'Zur Seite der Strecke',
        'act' => 'Aktiv werden',
        'default_title_threatened' => 'Von der Stilllegung bedrohte Strecke',
        'default_title_reopening' => 'Für die Wiedereröffnung der Strecke',
        'default_message_threatened' => 'Wir setzen uns für den Erhalt dieser Strecke ein.',
        'default_message_reopening' => 'Wir unterstützen die Wiedereröffnung dieser Strecke.',
        'default_cta' => 'Für die Strecke aktiv werden',
    ],

    // Orangefarbenes Band: drei Zugänge zur Enzyklopädie (ursprüngliches Design).
    'maps' => [
        'box1_title' => 'Zeitreisekarte',
        'box1_text' => 'Das französische Eisenbahnnetz Jahrzehnt für Jahrzehnt, von 1830 bis heute: die in jeder Epoche eröffneten, stillgelegten oder abgebauten Strecken.',
        'box1_link' => 'Karte öffnen',
        'box2_title' => 'Detailseiten',
        'box2_text' => 'Zu jeder Strecke: ihr Verlauf, ihre Bahnhöfe, eine datierte Chronik, Archivfotos und die Quelle jeder Angabe.',
        'box2_link' => 'Strecken durchsuchen',
        'box3_title' => 'Offene Daten',
        'box3_text' => 'Die Daten von VFF sind über eine öffentliche API (REST und GraphQL) zugänglich, für Ihre Recherchen und Projekte, unter der Lizenz der jeweiligen Quelle.',
        'box3_link' => 'API-Dokumentation lesen',
    ],

    'genesis' => [
        'title' => 'Bevor die Schienen verschwinden',
        'p1' => 'Um 1930 besaß Frankreich fast 60.000 Kilometer Eisenbahnstrecken, Nebenbahnen eingeschlossen. Weniger als 30.000 sind heute noch in Betrieb. Die übrigen wurden stillgelegt, abgebaut und manchmal aus der Landschaft getilgt, und ihre Geschichte ist über Archive, alte Karten und Erinnerungen verstreut.',
        'p2' => 'VFF trägt dieses Gedächtnis zusammen: jede Strecke erfassen, datieren, auf der Karte verorten, ihre Quellen nennen und sie teilen, damit andere sie ergänzen können.',
        'signature' => 'Die Gründer',
    ],

    // Profil des Netzes: Länge der in jedem Jahrzehnt betriebenen Strecken (Zahlen aus der Datenbank).
    'network' => [
        'title' => 'Das Netz, Jahrzehnt für Jahrzehnt',
        'intro' => 'Von 1830 bis 1930 wuchs das französische Eisenbahnnetz unaufhörlich. Danach schrumpfte es und hat seitdem die Hälfte seiner Länge verloren.',
        'chart_caption' => 'Länge der am 31. Dezember jedes Jahrzehnts betriebenen Strecken in Kilometern, nach den in VFF erfassten Eröffnungs- und Stilllegungsdaten. Wählen Sie ein Jahrzehnt, um seine Karte zu öffnen.',
        'today' => 'Heute',
        'bar' => ':year: :km, :lines. Karte dieses Jahrzehnts öffnen.',
        'bar_today' => 'Heute: :km, :lines. Karte des heutigen Netzes öffnen.',
        'km' => ":value\u{00A0}km",
        'lines_one' => ":value\u{00A0}Strecke",
        'lines_other' => ":value\u{00A0}Strecken",
        'table_toggle' => 'Zahlen nach Jahrzehnt anzeigen',
        'table_caption' => 'Länge der betriebenen Strecken und Zahl der Strecken, nach Jahrzehnt',
        'col_decade' => 'Jahrzehnt',
        'col_length' => 'Länge',
        'col_lines' => 'Betriebene Strecken',
        'milestones_title' => 'Wegmarken',
        'm1827' => 'Saint-Étienne - Andrézieux, die erste Eisenbahnstrecke Frankreichs, befördert Kohle aus dem Loire-Becken in Wagen, die von Pferden gezogen werden.',
        'm1842' => 'Das Gesetz vom 11. Juni 1842 legt ein sternförmiges Netz um Paris fest, gebaut mit dem Staat und betrieben von Gesellschaften.',
        'm1879' => 'Der Freycinet-Plan bringt Tausende Kilometer Strecken auf den Weg, um jede Unterpräfektur anzuschließen.',
        'm1930' => 'Das Netz erreicht seine größte Ausdehnung: fast 60.000 km, Nebenbahnen eingeschlossen.',
        'm1938' => 'Die großen Bahngesellschaften werden in der SNCF zusammengeführt, die am 1. Januar 1938 gegründet wird.',
        'm1981' => 'Die Schnellfahrstrecke Paris - Lyon nimmt im September 1981 ihren ersten Abschnitt in Betrieb.',
        'cta' => 'Karte öffnen',
    ],

    // Galerie: echte Bilder aus Wikimedia Commons, den Strecken zugeordnet.
    'archives' => [
        'title' => 'Die Eisenbahn in Bildern',
        'intro' => 'Fotos und Postkarten aus Wikimedia Commons, den Seiten der Strecken zugeordnet, mit Urheber und Lizenz.',
        'decade' => ':decade',
        'author_unknown' => 'Unbekannter Urheber',
        'source' => 'Wikimedia Commons',
        'cta' => 'Strecken durchsuchen',
    ],

    // Schnellzugriff direkt nach der großen Kopfzeile: Suche und direkte Einträge (Namen: nav.*).
    'quick' => [
        'label' => 'Schnellzugriff',
        'shortcut_hint' => 'Auf jeder Seite öffnet :keys die Suche.',
        'map_detail' => '1830 bis heute',
        'materials_detail' => 'Lokomotiven, Triebzüge, Reisezugwagen',
        'api_detail' => 'REST, GraphQL, GeoJSON',
        'contribute_detail' => 'Eine Strecke korrigieren, eine Quelle angeben',
    ],

    // Inhalt der Enzyklopädie mit den tatsächlichen Zahlen aus der Datenbank.
    'contents' => [
        'title' => 'In der Enzyklopädie',
        'lines_title' => 'Strecken',
        'lines_text' => 'Verlauf, Bahnhöfe, Chronik und Quellen jeder Strecke, ob in Betrieb oder verschwunden.',
        'lines_count_one' => ":value\u{00A0}Seite",
        'lines_count_other' => ":value\u{00A0}Seiten",
        'stations_title' => 'Bahnhöfe',
        'stations_text' => 'Bahnhöfe und Haltepunkte mit ihren Daten und den Strecken, die sie bedienen.',
        'stations_count_one' => ":value\u{00A0}Seite",
        'stations_count_other' => ":value\u{00A0}Seiten",
        'materials_title' => 'Fahrzeuge',
        'materials_text' => 'Lokomotiven, Triebzüge und Reisezugwagen mit den Strecken, auf denen sie fuhren.',
        'materials_count_one' => ":value\u{00A0}Seite",
        'materials_count_other' => ":value\u{00A0}Seiten",
        'map_title' => 'Historische Karte',
        'map_text' => 'Das Netz, wie es in jedem Jahrzehnt aussah, von 1830 bis heute.',
        'map_count_one' => ":value\u{00A0}Jahrzehnt",
        'map_count_other' => ":value\u{00A0}Jahrzehnte",
        'media_title' => 'Archivbilder',
        'media_text' => 'Die den Strecken zugeordneten Fotos und Dokumente, mit Urheber und Lizenz.',
        'media_count_one' => ":value\u{00A0}Bild",
        'media_count_other' => ":value\u{00A0}Bilder",
        'api_title' => 'API und Daten',
        'api_text' => 'Die Daten von VFF in REST, GraphQL und GeoJSON, zur Weiterverwendung unter der Lizenz der jeweiligen Quelle.',
    ],

    // Abschließende Einladung (ein einziges „cta“-Band pro Seite).
    'contribute' => [
        'title' => 'An der Enzyklopädie mitwirken',
        'subtitle' => 'VFF entsteht gemeinsam mit seinen Leserinnen und Lesern. Zwei Prüfer sichten jeden Vorschlag vor der Veröffentlichung, und jede Angabe nennt ihre Quelle.',
        'data_title' => 'Eine Strecke dokumentieren',
        'data_text' => 'Korrigieren Sie ein Datum oder ergänzen Sie einen Verlauf im Editor, mit Angabe Ihrer Quelle.',
        'media_title' => 'Archive teilen',
        'media_text' => 'Haben Sie alte Fotos, Pläne oder Dokumente? Schreiben Sie uns, damit wir sie mit Urheber und Lizenz veröffentlichen.',
        'code_title' => 'Den Code verbessern',
        'code_text' => 'Der Quellcode von VFF ist auf GitHub unter der MIT-Lizenz veröffentlicht: Melden Sie ein Problem, schlagen Sie eine Übersetzung oder eine Korrektur vor.',
        'cta' => 'So können Sie mitwirken',
        'donate' => 'Spenden',
        'discord' => 'Discord',
        'github' => 'GitHub',
    ],
];
