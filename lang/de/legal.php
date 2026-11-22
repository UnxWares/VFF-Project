<?php

// Mentions légales (LCEN, art. 6). Informations vérifiées le 2026-10-21 : registre national des
// entreprises (recherche-entreprises.api.gouv.fr) et page https://www.unxwares.com/legal.
// Texte HTML de confiance rendu par LegalPage.svelte. Le français fait foi.
// Traduction de lang/fr/legal.php.
return [
    'meta_title' => 'Impressum',
    'meta_description' => 'Herausgeber, Hosting, Lizenzen, Quellen und Meldung von Inhalten bei Voies Ferrées de France.',

    'hero' => [
        'title' => 'Impressum',
        'updated' => 'Zuletzt aktualisiert: :date',
    ],

    'editor' => [
        'title' => 'Herausgeber der Website',
        'paragraph' => '<p>Voies Ferrées de France (VFF), erreichbar unter <a href="https://vff-project.org">vff-project.org</a>, wird herausgegeben von <strong>UnxWares</strong>, einer vereinfachten Aktiengesellschaft französischen Rechts (SAS) mit einem Kapital von 1.000 €, eingetragen im Handels- und Gesellschaftsregister (RCS) Paris unter der Nummer 945 207 991.</p>'
            .'<ul>'
            .'<li>SIRET: 945 207 991 00015</li>'
            .'<li>Umsatzsteuer-Identifikationsnummer: FR93 945 207 991</li>'
            .'<li>Geschäftssitz: 78 avenue des Champs-Élysées, bureau 326, 75008 Paris, Frankreich</li>'
            .'<li>Telefon: +33 6 70 33 02 22</li>'
            .'<li>E-Mail: <a href="mailto:contact@vff-project.org">contact@vff-project.org</a></li>'
            .'<li>Website des Unternehmens: <a href="https://www.unxwares.com" target="_blank" rel="noopener">www.unxwares.com</a></li>'
            .'</ul>'
            .'<p>Diese Seite ist eine Übersetzung: Rechtlich verbindlich ist allein die französische Fassung.</p>',
    ],

    'publication' => [
        'title' => 'Verantwortlich für den Inhalt',
        'paragraph' => 'Baptiste Gosselin, Präsident von UnxWares.',
    ],

    'hosting' => [
        'title' => 'Hosting',
        'paragraph' => '<p>Die Website wird von <strong>UnxWares</strong> auf von ihr verwalteten Servern gehostet (Kontaktdaten siehe oben).</p>'
            .'<p>UnxWares greift auf folgende Dienstleister zurück:</p>'
            .'<ul>'
            .'<li><strong>Hetzner Online GmbH</strong>, Industriestr. 25, 91710 Gunzenhausen, Deutschland: Server in Rechenzentren in der Europäischen Union;</li>'
            .'<li><strong>Netaris</strong>, vereinfachte Aktiengesellschaft französischen Rechts mit einem Alleingesellschafter, eingetragen im Handels- und Gesellschaftsregister (RCS) Paris unter der Nummer 940 260 391, 149 avenue du Maine, 75014 Paris, Frankreich: Netzwerk und Infrastrukturplattform;</li>'
            .'<li><strong>Infomaniak Network SA</strong>, rue Eugène-Marziano 25, 1227 Genf, Schweiz: Domainname und DNS-Server.</li>'
            .'</ul>',
    ],

    'nature' => [
        'title' => 'Art der Website',
        'paragraph' => 'VFF ist eine gemeinschaftlich erstellte Enzyklopädie des französischen Eisenbahnnetzes von 1830 bis heute: Streckenverläufe, Daten, Bahnhöfe und der Zustand der Strecken in jeder Epoche, jeweils mit ihren Quellen. Die Website ist kostenlos, werbefrei und ohne Tracking-Werkzeuge. Sie verkauft nichts.',
    ],

    'accessibility' => [
        'title' => 'Barrierefreiheit',
        'paragraph' => 'Die Website entspricht mangels eines bisher durchgeführten Audits nicht dem französischen Barrierefreiheitsstandard RGAA 4.1.2. Die bekannten Mängel, die geplanten Verbesserungen und die Möglichkeit, uns eine Schwierigkeit zu melden, sind in der <a href="/accessibility">Erklärung zur Barrierefreiheit</a> beschrieben.',
    ],

    'licences' => [
        'title' => 'Geistiges Eigentum und Lizenzen',
        'paragraph' => '<ul>'
            .'<li><strong>Quellcode</strong>: MIT-Lizenz (<a href="https://github.com/UnxWares/VFF-Project" target="_blank" rel="noopener">github.com/UnxWares/VFF-Project</a>). Das Modell, das Bahnstrecken aus alten Luftbildern extrahiert, und seine Gewichte stehen ebenfalls unter der MIT-Lizenz (<a href="https://github.com/UnxWares/VFF-Rail-Extraction" target="_blank" rel="noopener">github.com/UnxWares/VFF-Rail-Extraction</a>).</li>'
            .'<li><strong>Datenbank</strong> (Streckenverläufe, Daten, Bahnhöfe, Zustand der Strecken, Exporte und API): <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener">Open Database License (ODbL) 1.0</a>, mit Ausnahme von Daten aus Quellen, die nur eine nicht kommerzielle Nutzung erlauben: Ihre Herkunft ist bei jedem Datensatz angegeben (Feld <code>source</code> der API), und sie dürfen nur zu nicht kommerziellen Zwecken weiterverwendet werden, mit Angabe der Quelle und einem Link.</li>'
            .'<li><strong>Texte</strong>: Die für VFF verfassten Texte stehen unter der Lizenz <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener">CC BY-SA 4.0</a>. Auszüge aus Wikipedia bleiben unter CC BY-SA 4.0 und werden mit einem Link auf den Artikel und seine Version dargestellt.</li>'
            .'<li><strong>Bilder und Dokumente</strong>: Jede Datei behält ihre eigene Lizenz, die zusammen mit ihrem Urheber angegeben wird. Bilder von Wikimedia Commons werden per Direktlink angezeigt, ohne Kopie auf unseren Servern. Von Mitwirkenden hochgeladene Dateien stehen unter CC BY-SA 4.0.</li>'
            .'<li><strong>Name und Logo</strong>: Der Name „Voies Ferrées de France“, das Kürzel „VFF“ und das Logo bezeichnen das von UnxWares herausgegebene Projekt. Sie dürfen sie nennen, um über das Projekt zu sprechen, aber nicht in einer Weise verwenden, die eine Partnerschaft oder eine Billigung durch uns vermuten ließe.</li>'
            .'</ul>',
    ],

    'sources' => [
        'title' => 'Datenquellen',
        'paragraph' => '<p>VFF führt offene Quellen zusammen, jeweils mit Angabe ihrer Lizenz, insbesondere:</p>'
            .'<ul>'
            .'<li>OpenStreetMap: © OpenStreetMap-Mitwirkende, Lizenz ODbL;</li>'
            .'<li>SNCF Réseau, SNCF Voyageurs und SNCF Gares &amp; Connexions (Open-Data-Portal der SNCF): ODbL;</li>'
            .'<li>IGN, Géoplateforme (BD TOPO, Luftbilder und historische Karten): Licence Ouverte 2.0;</li>'
            .'<li>OpenHistoricalMap und Wikidata: CC0;</li>'
            .'<li>Wikipedia: CC BY-SA 4.0; Wikimedia Commons: Lizenz der jeweiligen Datei.</li>'
            .'</ul>'
            .'<p>Die vollständige Liste mit den Quellenangaben finden Sie auf den Seiten <a href="/credits/tech">Technische Danksagung</a> und <a href="/credits/media">Bildnachweise</a>.</p>',
    ],

    'interests' => [
        'title' => 'Engagement',
        'paragraph' => 'Der Gründer von VFF ist Mitglied des Vereins AngouLim, der sich für die Wiedereröffnung der Strecke Angoulême - Limoges einsetzt, weil er es für wichtig hält, vergessene Strecken zu verteidigen. VFF unterstützt diese Kampagne auf der Seite der Strecke.',
    ],

    'liability' => [
        'title' => 'Haftung',
        'paragraph' => '<p>VFF trägt historische Quellen zusammen, die sich teilweise widersprechen. Trotz der Prüfung der Beiträge können Fehler verbleiben: abgeleitete Daten (als solche gekennzeichnet), ungefähre Streckenverläufe, unvollständige Zustandsangaben. Die Informationen dienen der Dokumentation und ersetzen keine amtlichen Dokumente.</p>'
            .'<p>Sie dürfen niemals dazu verwendet werden, sich im Eisenbahnnetz zu bewegen oder dort einzugreifen. Bahnstrecken und Bahngelände, auch wenn sie verlassen aussehen, sind für die Öffentlichkeit gesperrt und gefährlich.</p>'
            .'<p>Links zu anderen Websites dienen nur der Information: UnxWares ist für deren Inhalt nicht verantwortlich. Um einen Fehler zu melden, nutzen Sie die Werkzeuge zum Mitwirken oder schreiben Sie uns.</p>',
    ],

    'report' => [
        'title' => 'Rechtswidrige Inhalte melden',
        'paragraph' => '<p>Wenn Ihnen ein auf VFF veröffentlichter Inhalt rechtswidrig erscheint, melden Sie ihn an <a href="mailto:contact@vff-project.org">contact@vff-project.org</a> und geben Sie dabei an:</p>'
            .'<ul>'
            .'<li>die genaue Adresse (URL) des Inhalts;</li>'
            .'<li>die Gründe, aus denen Sie ihn für rechtswidrig halten, wenn möglich mit den einschlägigen Rechtsvorschriften;</li>'
            .'<li>Ihren Namen und Ihre E-Mail-Adresse;</li>'
            .'<li>eine Erklärung, dass Ihre Meldung zutreffend ist und in gutem Glauben erfolgt.</li>'
            .'</ul>'
            .'<p>Wir bestätigen den Eingang, prüfen die Meldung unverzüglich und entfernen den Inhalt, wenn er offensichtlich rechtswidrig ist. Der Urheber eines entfernten Inhalts wird darüber mit Angabe der Gründe informiert. Einen Inhalt in Kenntnis seiner Rechtmäßigkeit zu melden, um seine Entfernung zu erreichen, ist gesetzlich strafbar.</p>',
    ],

    'contact' => [
        'title' => 'Kontakt',
        'paragraph' => 'Bei Fragen zu diesem Impressum: <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>. Für das Unternehmen UnxWares: <a href="mailto:contact@unxwares.com">contact@unxwares.com</a>, +33 6 70 33 02 22.',
    ],
];
