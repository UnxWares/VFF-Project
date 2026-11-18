<?php

// Déclaration d'accessibilité, au format du RGAA 4.1.2. Sans audit RGAA complet, le référentiel
// impose l'état « non conforme ». Les contenus non accessibles reprennent les constats de l'audit
// interne du 2026-10-21 (storage/app/reports/agents/audit-ui.md) : à mettre à jour à chaque
// correction, et après l'audit RGAA (taux de conformité). Le français fait foi.
// Traduction de lang/fr/accessibility.php.
return [
    'meta_title' => 'Erklärung zur Barrierefreiheit',
    'meta_description' => 'Stand der Konformität von Voies Ferrées de France mit dem RGAA, nicht barrierefreie Inhalte und Rechtsbehelfe.',

    'hero' => [
        'eyebrow' => 'Barrierefreiheit',
        'title' => 'Erklärung zur Barrierefreiheit',
        'updated' => 'Erstellt am :date',
    ],

    'commitment' => [
        'title' => 'Unser Engagement',
        'paragraph' => 'UnxWares möchte, dass Voies Ferrées de France für alle nutzbar ist, unabhängig von Behinderung, Gerät oder Art der Navigation. VFF unterliegt nicht der Barrierefreiheitspflicht nach Artikel 47 des französischen Gesetzes Nr. 2005-102 vom 11. Februar 2005, folgt aber freiwillig dem französischen Barrierefreiheitsstandard RGAA 4.1.2. Diese Erklärung gilt für die Website <a href="https://vff-project.org">vff-project.org</a>. Diese Seite ist eine Übersetzung: Rechtlich verbindlich ist allein die französische Fassung.',
    ],

    'status' => [
        'title' => 'Konformitätsstatus',
        'paragraph' => '<p><strong>Die Website vff-project.org entspricht nicht dem RGAA 4.1.2.</strong></p>'
            .'<p>Sie wurde noch keinem RGAA-Konformitätsaudit unterzogen. Ohne ein solches Audit verlangt der Standard, sie als nicht konform zu erklären.</p>',
    ],

    'results' => [
        'title' => 'Testergebnisse',
        'paragraph' => 'Am 21. Oktober 2026 wurde ein internes Audit an 47 Seiten und Zuständen der Website durchgeführt, bei sechs Bildschirmbreiten (von 375 bis 1.920 Pixel), im hellen und im dunklen Design, auf Französisch und teilweise auf Englisch und Deutsch. Es ergab Darstellungs- und Bedienungsmängel, von denen ein Teil die Barrierefreiheit betrifft. Es folgt nicht der Methode des RGAA und erlaubt keine Berechnung einer Konformitätsquote.',
    ],

    'non_accessible' => [
        'title' => 'Nicht barrierefreie Inhalte',
        'paragraph' => '<p>Bekannte Mängel, die derzeit behoben werden:</p>'
            .'<ul>'
            .'<li>Kontraste: Mehrere orangefarbene Texte auf hellem Grund sowie weiße Texte auf orangefarbenem Grund erreichen nicht das für Fließtext geforderte Kontrastverhältnis von 4,5:1 (Kriterium 3.2).</li>'
            .'<li>Auf bestimmten Seiten verschwinden die Auswahlmenüs für Sprache und Konto am Computer unter dem Seiteninhalt oder werden auf dem Smartphone abgeschnitten: Die Einträge sind dann nicht nutzbar.</li>'
            .'<li>Die interaktiven Karten (Netzkarte nach Epoche, Karten der Streckenseiten, Streckenverlauf-Editor) sind per Tastatur nicht vollständig bedienbar und werden von Screenreadern nicht wiedergegeben.</li>'
            .'<li>Auf kleinen Bildschirmen überlappen sich bestimmte Elemente, zum Beispiel der Hinweis „Heute“ der Streckenseiten mit dem Titel des nächsten Abschnitts.</li>'
            .'<li>Die Fehlermeldungen der Anmelde- und Registrierungsformulare werden auf den französischen Seiten auf Englisch angezeigt, und die Fehlerseite „Seite nicht gefunden“ ist weder übersetzt noch an das dunkle Design angepasst.</li>'
            .'</ul>'
            .'<p>Die Website wurde noch nicht mit einem Screenreader getestet: Es können weitere Schwierigkeiten bestehen. Die Informationen der Karten werden auch als Text angeboten: Seiten der Strecken und Bahnhöfe, Listen der <a href="/lines">Strecken</a> und der <a href="/stations">Bahnhöfe</a> sowie die <a href="/api">API</a>.</p>',
    ],

    'exempt' => [
        'title' => 'Inhalte, die nicht der Barrierefreiheitspflicht unterliegen',
        'paragraph' => 'Online-Karten und Kartendienste gehören zu den Inhalten, die nicht der Barrierefreiheitspflicht unterliegen. VFF bemüht sich dennoch, das Wesentliche davon als Text darzustellen, auf den Seiten der Strecken und Bahnhöfe. Inhalte Dritter, die VFF weder erstellt noch kontrolliert (Bilder von Wikimedia Commons, Kartenhintergründe, verlinkte Websites), fallen nicht unter diese Erklärung.',
    ],

    'establishment' => [
        'title' => 'Erstellung dieser Erklärung',
        'paragraph' => '<p>Diese Erklärung wurde am 21. Oktober 2026 erstellt.</p>'
            .'<ul>'
            .'<li><strong>Verwendete Technologien</strong>: HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Testumgebung</strong>: Chromium-Browser unter Linux, 375 bis 1.920 Pixel Breite, im hellen und dunklen Design.</li>'
            .'<li><strong>Werkzeuge</strong>: automatische Messungen (Kontraste, Überläufe, Größe der klickbaren Flächen, Konsolenfehler) und visuelle Durchsicht jedes Screenshots.</li>'
            .'<li><strong>Geprüfte Seiten</strong>: Startseite, Karte, Listen und Seiten der Strecken und Bahnhöfe, Fahrzeuge, Mitwirken, Spenden, Über uns, Danksagungen, rechtliche Seiten, API und ihre Dokumentation, Anmeldung, Registrierung, Passwort vergessen, Mitgliederbereiche, Fehlerseite.</li>'
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
        'paragraph' => 'Wir beheben zuerst die blockierenden Mängel (Menüs der Kopfzeile, Überlappungen, Formulare), danach die Kontraste. Anschließend wird ein vollständiges RGAA-Konformitätsaudit durchgeführt und seine Konformitätsquote auf dieser Seite veröffentlicht.',
    ],
];
