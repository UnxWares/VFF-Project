<?php

// Déclaration d'accessibilité, au format du modèle officiel du RGAA 4.1.2 (article 47 de la loi n° 2005-102).
// Elle repose sur l'audit interne RGAA 4.1.2 du 2026-11-19 (106 critères, 28 pages) et sur sa revue du soir
// (écrans changés dans la journée, 32 pages) : rapports storage/app/reports/agents/nuit/audit-rgaa.md et
// storage/app/reports/agents/soir/accessibilite-soir.md, grille publiée public/rgaa/grille-audit-rgaa-2026-11-19.csv,
// calcul refait par storage/app/data/rgaa/grille.py. Seuils officiels : conforme à 100 %, partiellement
// conforme à partir de 50 % de critères respectés, non conforme en dessous ou sans audit en cours de validité.
// À mettre à jour à chaque correction ; la date est dans resources/js/Pages/Accessibility.svelte
// (prop updated) et dans la section establishment. Le français fait foi.
// Traduction de lang/fr/accessibility.php.
return [
    'meta_title' => 'Erklärung zur Barrierefreiheit',
    'meta_description' => 'Stand der Konformität von Voies Ferrées de France mit dem RGAA, Ergebnisse des internen Audits, nicht barrierefreie Inhalte und Rechtsbehelfe.',

    'hero' => [
        'title' => 'Erklärung zur Barrierefreiheit',
        'updated' => 'Erstellt am :date',
    ],

    'commitment' => [
        'title' => 'Unser Engagement',
        'paragraph' => 'Voies Ferrées de France (VFF) wird von UnxWares herausgegeben, einer französischen vereinfachten Aktiengesellschaft (société par actions simplifiée), deren Angaben im <a href="/legal">Impressum</a> stehen. UnxWares möchte, dass VFF für alle nutzbar ist, unabhängig von Behinderung, Gerät oder Art der Navigation. Die Website unterliegt nicht der Barrierefreiheitspflicht nach Artikel 47 des französischen Gesetzes Nr. 2005-102 vom 11. Februar 2005, folgt aber freiwillig dem französischen Barrierefreiheitsstandard RGAA 4.1.2. Diese Erklärung gilt für die Website <a href="https://vff-project.org">vff-project.org</a>. Diese Seite ist eine Übersetzung: Rechtlich verbindlich ist allein die französische Fassung.',
    ],

    'status' => [
        'title' => 'Konformitätsstatus',
        'paragraph' => '<p><strong>Die Website vff-project.org entspricht teilweise dem RGAA 4.1.2.</strong></p>'
            .'<p>Dieser Stand beruht auf dem internen Audit, das UnxWares am 19. November 2026 nach der Methode des RGAA 4.1.2 durchgeführt hat: Alle 106 Kriterien wurden an einer Stichprobe von 28 Seiten geprüft, am Abend desselben Tages dann an den tagsüber hinzugefügten oder geänderten Ansichten und an vier neuen Seiten, insgesamt 32 Seiten. Das Audit wurde nicht von einer externen Stelle durchgeführt.</p>',
    ],

    'results' => [
        'title' => 'Testergebnisse',
        'paragraph' => '<p>Das interne Konformitätsaudit ergibt:</p>'
            .'<ul>'
            .'<li>86 % der RGAA-Kriterien sind erfüllt (63 der 73 Kriterien, die auf die Website zutreffen);</li>'
            .'<li>die durchschnittliche Konformitätsrate des Online-Dienstes beträgt 99 % (99,25 %, Mittelwert der Raten der 32 Seiten).</li>'
            .'</ul>'
            .'<p>30 der 32 Seiten erfüllen alle Kriterien, die für sie gelten. Die zehn nicht erfüllten Kriterien scheitern nur in den beiden Werkzeugen der API-Dokumentation, Swagger UI und Magidoc. Das vollständige Prüfraster (Status und Begründung jedes Kriteriums auf jeder Seite) können Sie herunterladen: <a href="/rgaa/grille-audit-rgaa-2026-11-19.csv" download>RGAA-Prüfraster (CSV-Datei, 885 KB, auf Französisch)</a>.</p>'
            .'<p>Die Website wurde nicht mit einem Screenreader getestet. Name, Rolle und Zustand der Bedienelemente wurden im Code und im Barrierefreiheitsbaum des Browsers geprüft; wie NVDA, JAWS, VoiceOver oder TalkBack sie tatsächlich ausgeben, ist noch zu prüfen.</p>',
    ],

    'non_accessible' => [
        'title' => 'Nicht barrierefreie Inhalte',
        'paragraph' => '<p>Nicht erfüllte Anforderungen:</p>'
            .'<ul>'
                                    .'<li>Referenz der REST-API in Swagger UI (/docs/swagger): ein Link innerhalb einer Schaltfläche (8.2), zwei Hauptbereiche (9.2), das Feld „Filter by tag“ ohne Beschriftung (11.1). Die Oberfläche von Swagger UI ist englisch, auf einer als englisch ausgezeichneten Seite. Dieselben Endpunkte sind auf der Seite <a href="/api">Zugang zur API</a> beschrieben.</li>'
            .'<li>Referenz der GraphQL-API in Magidoc (/docs/graphql), mit englischer Oberfläche: Seitentitel ohne Namen der Website (8.6), VFF-Logo mit dem Text „Magidoc“ (1.3), Anker „#“ neben den Überschriften (6.1 und 9.1), eine Schaltfläche ohne Namen (7.1), eine Schaltfläche innerhalb eines Links (8.2), waagerechtes Scrollen bei 320 Pixeln Breite (10.11), Suchfeld ohne Beschriftung (11.1), kein Sprunglink (12.7). Die Seite <a href="/api">Zugang zur API</a> stellt die GraphQL-API vor.</li>'
            .'<li>Außerhalb der geprüften Stichprobe erscheinen Bildunterschriften und Urheber einiger Bilder von Wikimedia Commons in ihrer Originalsprache (Englisch, Niederländisch …), ohne dass diese Sprache ausgezeichnet ist (Kriterium 8.7).</li>'
            .'</ul>'
            .'<p>Wir berufen uns auf keine Ausnahme wegen unverhältnismäßiger Belastung.</p>',
    ],

    'exempt' => [
        'title' => 'Inhalte, die nicht der Barrierefreiheitspflicht unterliegen',
        'paragraph' => 'Online-Karten und Kartendienste gehören zu den Inhalten, die nicht der Pflicht zur Barrierefreiheit unterliegen: die Netzkarte, die Minikarten der Strecken- und Bahnhofsseiten, die einbettbare Karte, die OpenStreetMap-Grundkarte und Ebenen Dritter, samt ihren Zoom-Schaltflächen. Ihre Nutzung mit einem Screenreader ist nicht gewährleistet. Die Informationen, die sie zeigen, stehen auch als Text bereit: Strecken- und Bahnhofsseiten, Verzeichnisse der <a href="/lines">Strecken</a> und <a href="/stations">Bahnhöfe</a>, Tabellen nach Jahrzehnt und die <a href="/api">API</a>. Inhalte Dritter, die VFF weder erstellt noch kontrolliert (Bilder von Wikimedia Commons, verlinkte Websites), fallen nicht unter diese Erklärung.',
    ],

    'establishment' => [
        'title' => 'Erstellung dieser Erklärung',
        'paragraph' => '<p>Diese Erklärung wurde am 19. November 2026 nach dem internen RGAA-Audit erstellt und am Abend desselben Tages aktualisiert, nachdem die tagsüber hinzugefügten Ansichten geprüft worden waren.</p>'
            .'<ul>'
            .'<li><strong>Verwendete Technologien</strong>: HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Testumgebung</strong>: Browser Chromium unter Linux, für das Audit 320 bis 1 280 Pixel breit im hellen Design (das dunkle Design wurde am selben Tag gemessen), für die am Abend geprüften Ansichten 320 bis 2 560 Pixel breit im hellen und im dunklen Design; auf Französisch. Die tagsüber hinzugefügten Seiten für die Prüfung und die Verwaltung wurden mit Testdaten angezeigt. Es wurde keine assistive Technologie eingesetzt.</li>'
            .'<li><strong>Werkzeuge</strong>: Prüfung des erzeugten Codes und des Barrierefreiheitsbaums (Skripte des Projekts), Berechnung der Kontraste, Tastaturdurchgang mit 45 Tabulatorschritten je Seite, Bildschirmfotos ohne Stylesheets, bei 320 Pixeln Breite und mit vergrößerten Textabständen, Durchsicht des Codes der Komponenten.</li>'
            .'<li><strong>Geprüfte Seiten</strong>: Startseite; Impressum, das auch die Kontaktangaben enthält (die Website hat keine Kontaktseite); Erklärung zur Barrierefreiheit; Mitmachen, das als Hilfeseite dient; Anmeldung; Registrierung; Passwort vergessen; Karte; Verzeichnisse der Strecken, Bahnhöfe, Fahrzeuge und Unfälle; je eine Seite zu einer Strecke (610000), einem Bahnhof (Angoulême), einem Fahrzeug (PLM 1-30) und einem Unfall (Meudon, 1842); Zugang zur API; Referenzen REST (Swagger UI) und GraphQL (Magidoc); die Fehlerseite „Seite nicht gefunden“; das Suchfenster; Profil; Trassen-Editor; meine Beiträge; Prüfung der Beiträge; dazu, ausgelost, die Datenschutzerklärung, die Nutzungsbedingungen und die technischen Credits. Am Abend kamen vier Seiten hinzu: die Reputation der Mitglieder, die Moderation der Konten, die Bearbeitung der Fehlermeldungen und die Benachrichtigungen. Die Website hat keine Sitemap-Seite. Die REST-Referenz in Scalar (/docs/api) gehörte nicht zur Stichprobe.</li>'
            .'</ul>',
    ],

    'feedback' => [
        'title' => 'Rückmeldung und Kontakt',
        'paragraph' => 'Wenn Sie auf einen Inhalt oder einen Dienst nicht zugreifen können, schreiben Sie an <a href="mailto:contact@vff-project.org">contact@vff-project.org</a> mit „Accessibilité“ im Betreff. Wir verweisen Sie auf eine barrierefreie Lösung oder übermitteln Ihnen den Inhalt in anderer Form, so schnell wie möglich und spätestens innerhalb eines Monats.',
    ],

    'recourse' => [
        'title' => 'Rechtsbehelfe',
        'paragraph' => '<p>Wenn Sie uns einen Mangel der Barrierefreiheit gemeldet haben, der Sie daran hindert, auf einen Inhalt oder einen Dienst zuzugreifen, und Sie keine zufriedenstellende Antwort erhalten haben, können Sie:</p>'
            .'<ul>'
            .'<li>dem Défenseur des droits (französischer Bürgerbeauftragter) eine Nachricht über das Online-Formular auf <a href="https://www.defenseurdesdroits.fr" target="_blank" rel="noopener">defenseurdesdroits.fr</a> schreiben;</li>'
            .'<li>den Delegierten des Défenseur des droits in Ihrer Region kontaktieren;</li>'
            .'<li>die Nummer 09 69 39 00 00 anrufen;</li>'
            .'<li>einen kostenlosen Brief ohne Briefmarke senden an: Défenseur des droits, Libre réponse 71120, 75342 Paris CEDEX 07.</li>'
            .'</ul>',
    ],

    'plan' => [
        'title' => 'Geplante Verbesserungen',
        'paragraph' => 'Wir planen, die Oberflächen von Swagger UI und Magidoc ins Französische zu übersetzen und ihren Code zu korrigieren und anschließend beim Import der Bilder von Wikimedia Commons die Sprache jeder Bildunterschrift zu speichern. Außerdem planen wir, die Website mit einem Screenreader zu testen und die REST-Referenz in Scalar zu prüfen. Die Konformitätsrate auf dieser Seite wird nach jeder Korrektur aktualisiert.',
    ],
];
