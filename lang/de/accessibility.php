<?php

// Déclaration d'accessibilité, au format du modèle officiel du RGAA 4.1.2 (article 47 de la loi n° 2005-102).
// Elle repose sur l'audit interne du 2026-11-19 (grille WCAG 2.1 A et AA, socle du RGAA ; rapport :
// storage/app/reports/agents/ui-controle.md, section 6). Ce n'est ni un audit de la grille RGAA complète
// ni un audit par un tiers : aucun taux de conformité n'est publié. Pour s'en tenir à la lettre du
// référentiel, remplacer « partiellement conforme » par « non conforme » dans la section status.
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
        'paragraph' => '<p><strong>Die Website vff-project.org entspricht dem RGAA 4.1.2 teilweise.</strong></p>'
            .'<p>Dieser Status beruht auf einem internen Audit vom 19. November 2026 anhand der Kriterien der Stufen A und AA der WCAG 2.1, die die Grundlage des RGAA bilden. Das Audit deckte nicht die vollständige RGAA-Kriterienliste (106 Kriterien) ab und wurde nicht von einer dritten Stelle durchgeführt. Wir veröffentlichen deshalb keine Konformitätsquote, und der erklärte Status ist durch ein solches Audit noch zu bestätigen.</p>',
    ],

    'results' => [
        'title' => 'Testergebnisse',
        'paragraph' => '<p>Das interne Audit vom 19. November 2026 umfasste die unten aufgeführten Beispielseiten, insgesamt mehr als 250 Ansichten (Seiten, Bildschirmbreiten, helles und dunkles Design, Sprachen). Von den 50 Kriterien der WCAG 2.1 der Stufen A und AA werden 42 erfüllt, 1 nicht (die Sprache von Textstellen, siehe unten) und 7 sind nicht anwendbar, weil die Website weder Video noch Ton noch Bedienelemente per Bewegung enthält.</p>'
            .'<p>Lighthouse vergibt bei der Barrierefreiheit auf den gemessenen Seiten 100 von 100 Punkten, am Computer und auf dem Smartphone. Diese Punktzahl beweist keine Konformität: Sie deckt nur einen Teil der Kriterien ab. Deshalb haben wir die Website außerdem vollständig nur mit der Tastatur durchlaufen und die Darstellung bei 200 % Zoom und bei 320 Pixel Breite geprüft, mit vergrößertem Textabstand und mit aktivierter Reduzierung von Animationen.</p>'
            .'<p>Die Website wurde nicht mit einem Screenreader getestet: Schwierigkeiten, die diese Tests nicht zeigen, können bestehen bleiben.</p>',
    ],

    'non_accessible' => [
        'title' => 'Nicht barrierefreie Inhalte',
        'paragraph' => '<p>Festgestellte Nichtkonformität:</p>'
            .'<ul>'
            .'<li>Sprache von Textstellen (RGAA-Kriterium 8.7, WCAG 3.1.2): Die Bildunterschriften und Urheberangaben der Bilder von Wikimedia Commons erscheinen in ihrer Originalsprache (Englisch, Niederländisch usw.), ohne dass diese Sprache angegeben ist. Screenreader können sie daher mit falscher Aussprache vorlesen.</li>'
            .'</ul>'
            .'<p>Inhalte, die noch nicht geprüft wurden:</p>'
            .'<ul>'
            .'<li>die Mitgliederbereiche (Streckenverlauf-Editor, Validierung, Profil);</li>'
            .'<li>die Dokumentationswerkzeuge der API (Swagger UI, GraphiQL, Magidoc), die von VFF gehostet werden und unter diese Erklärung fallen und deren Beschriftungen teilweise noch auf Englisch sind.</li>'
            .'</ul>'
            .'<p>Wir berufen uns auf keine Ausnahme wegen unverhältnismäßiger Belastung.</p>',
    ],

    'exempt' => [
        'title' => 'Inhalte, die nicht der Barrierefreiheitspflicht unterliegen',
        'paragraph' => 'Online-Karten und Kartendienste gehören zu den Inhalten, die nicht der Barrierefreiheitspflicht unterliegen: die Netzkarte, die Minikarten der Seiten, die einbettbare Karte, der OpenStreetMap-Kartenhintergrund und Ebenen von Dritten. Ihre Nutzung mit einem Screenreader ist nicht gewährleistet. Die Informationen, die sie zeigen, werden auch als Text angeboten: Seiten der Strecken und Bahnhöfe, Verzeichnisse der <a href="/lines">Strecken</a> und der <a href="/stations">Bahnhöfe</a>, Tabellen nach Jahrzehnt und die <a href="/api">API</a>. Inhalte Dritter, die VFF weder erstellt noch kontrolliert (Bilder von Wikimedia Commons, verlinkte Websites), fallen nicht unter diese Erklärung.',
    ],

    'establishment' => [
        'title' => 'Erstellung dieser Erklärung',
        'paragraph' => '<p>Diese Erklärung wurde am 19. November 2026 erstellt.</p>'
            .'<ul>'
            .'<li><strong>Verwendete Technologien</strong>: HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Testumgebung</strong>: Chromium-Browser unter Linux, 320 bis 1.920 Pixel Breite, im hellen und im dunklen Design, auf Französisch und teilweise auf Englisch und Deutsch.</li>'
            .'<li><strong>Werkzeuge</strong>: Lighthouse 13 (dessen Prüfungen auf axe-core beruhen), Entwicklerwerkzeuge von Chrome (Barrierefreiheitsbaum, Emulationen), die automatischen Messungen des Projekts (berechnete Kontraste, Überläufe, Größe der klickbaren Flächen), Bedienung per Tastatur, Zoom auf 200 %, Textabstand, Reduzierung von Animationen.</li>'
            .'<li><strong>Geprüfte Seiten</strong>: Startseite, Karte, Streckenseiten (610000 und 752000) und Bahnhofsseiten (Angoulême, Limoges-Bénédictins), Verzeichnisse der Strecken, Bahnhöfe und Fahrzeuge, Unsere Geschichte, Spenden, Mitwirken, Danksagungen, rechtliche Seiten, API, Anmeldung, Registrierung, Passwort vergessen, die Fehlerseite „Seite nicht gefunden“, einbettbare Karte.</li>'
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
        'paragraph' => 'Wir planen, beim Import der Bilder von Wikimedia Commons die Sprache jeder Bildunterschrift zu speichern und sie dann an der betroffenen Textstelle anzugeben, womit die Nichtkonformität beim Kriterium 8.7 behoben wird. Außerdem planen wir, die Mitgliederbereiche und die Dokumentationswerkzeuge der API zu prüfen, die Website mit einem Screenreader zu testen und danach ein vollständiges Audit anhand der RGAA-Kriterienliste in Auftrag zu geben. Dessen Konformitätsquote wird auf dieser Seite veröffentlicht.',
    ],
];
