<?php

// Politique de confidentialité (RGPD, loi Informatique et Libertés). Décrit ce que fait réellement
// le code au 2026-10-21 : cookies (config/session.php, LocaleController, lib/stores/theme.svelte.js),
// stockage local (Pages/Contribute/Trace.svelte), services tiers chargés par le navigateur
// (Components/Map/*, Lines/LineGallery.svelte), comptes (AuthController, ProfileController).
// Toute évolution de ces points doit mettre ce texte à jour. Le français fait foi.
// Traduction de lang/fr/privacy.php.
return [
    'meta_title' => 'Datenschutzerklärung',
    'meta_description' => 'Von Voies Ferrées de France verarbeitete personenbezogene Daten, Cookies, Drittdienste und Ihre Rechte.',

    'hero' => [
        'eyebrow' => 'Personenbezogene Daten',
        'title' => 'Datenschutzerklärung',
        'updated' => 'Zuletzt aktualisiert: :date',
    ],

    'summary' => [
        'title' => 'Kurz gefasst',
        'paragraph' => '<ul>'
            .'<li>Keine Werbung, keine Reichweitenmessung, keinerlei Tracking-Werkzeuge.</li>'
            .'<li>Zum Ansehen der Website ist kein Konto erforderlich.</li>'
            .'<li>Nur Cookies, die für den Betrieb oder für Ihre Einstellungen nötig sind: nichts zu akzeptieren.</li>'
            .'<li>Ihre Daten werden weder verkauft noch vermietet noch weitergegeben.</li>'
            .'<li>Ihre Daten werden in der Europäischen Union gehostet.</li>'
            .'</ul>'
            .'<p>Diese Seite ist eine Übersetzung: Rechtlich verbindlich ist allein die französische Fassung.</p>',
    ],

    'controller' => [
        'title' => 'Verantwortlicher',
        'paragraph' => 'UnxWares, vereinfachte Aktiengesellschaft französischen Rechts (SAS), eingetragen im Handels- und Gesellschaftsregister (RCS) Paris unter der Nummer 945 207 991, 78 avenue des Champs-Élysées, bureau 326, 75008 Paris, vertreten durch ihren Präsidenten, Baptiste Gosselin. Bei Fragen zu Ihren Daten: <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>. UnxWares ist nicht verpflichtet, einen Datenschutzbeauftragten zu benennen, und hat keinen benannt.',
    ],

    'visitors' => [
        'title' => 'Wenn Sie die Website ohne Konto besuchen',
        'paragraph' => '<p>Um Ihnen die Seiten zu senden, erhält der Server zwangsläufig Ihre IP-Adresse, die angeforderte Seite, das Datum und die Kennung Ihres Browsers. Diese Informationen werden in technischen Protokollen gespeichert, die ausschließlich für die Sicherheit des Dienstes, die Fehlerdiagnose und die Missbrauchsbekämpfung verwendet und höchstens 12 Monate aufbewahrt werden.</p>'
            .'<p>Zum Schutz der API wird die Anzahl der Anfragen jeder IP-Adresse eine Minute lang gezählt (höchstens 300 Anfragen) und danach vergessen.</p>'
            .'<p>Rechtsgrundlage: das berechtigte Interesse von UnxWares an der Sicherheit und am ordnungsgemäßen Betrieb der Website.</p>',
    ],

    'cookies' => [
        'title' => 'Cookies und Speicher in Ihrem Browser',
        'paragraph' => '<div class="table-wrap"><table>'
            .'<thead><tr><th scope="col">Name</th><th scope="col">Zweck</th><th scope="col">Dauer</th></tr></thead>'
            .'<tbody>'
            .'<tr><td><code>vff_session</code></td><td>Hält Ihre Sitzung aufrecht: Anmeldung, Absenden von Formularen.</td><td>2 Stunden Inaktivität</td></tr>'
            .'<tr><td><code>XSRF-TOKEN</code></td><td>Schützt die Formulare vor betrügerischen Übermittlungen.</td><td>2 Stunden</td></tr>'
            .'<tr><td><code>remember_web_…</code></td><td>Hält Sie angemeldet, wenn Sie „Angemeldet bleiben“ ankreuzen.</td><td>Etwa 400 Tage oder bis zur Abmeldung</td></tr>'
            .'<tr><td><code>locale</code></td><td>Merkt sich die von Ihnen gewählte Sprache.</td><td>1 Jahr</td></tr>'
            .'<tr><td><code>theme</code></td><td>Merkt sich das von Ihnen gewählte helle oder dunkle Design.</td><td>1 Jahr</td></tr>'
            .'<tr><td><code>vff:trace-draft:v1</code> (lokaler Speicher)</td><td>Bewahrt den Entwurf des Streckenverlauf-Editors auf, wenn Sie die Seite verlassen, bevor Sie ihn absenden. Er bleibt in Ihrem Browser, solange Sie ihn nicht absenden.</td><td>Bis zum Absenden oder Verwerfen des Entwurfs</td></tr>'
            .'</tbody></table></div>'
            .'<p>Diese Cookies sind für den Dienst unbedingt erforderlich oder merken sich eine von Ihnen gewählte Einstellung: Sie sind von der Einwilligungspflicht ausgenommen (Artikel 82 des französischen Gesetzes „Informatique et Libertés“). Deshalb zeigt die Website kein Banner an. VFF setzt keine Cookies zur Reichweitenmessung oder für Werbung. Sie können sie jederzeit über Ihren Browser löschen.</p>',
    ],

    'third_party' => [
        'title' => 'Von Ihrem Browser geladene Drittdienste',
        'paragraph' => '<p>Die Kartenhintergründe und bestimmte Bilder werden direkt von anderen Servern geladen. Ihr Browser übermittelt diesen dabei Ihre IP-Adresse und seine technischen Informationen, wie bei jedem Bild von einer anderen Website. Ihnen wird nur die Adresse der Website (vff-project.org) mitgeteilt, nicht die aufgerufene Seite. VFF übermittelt ihnen keine anderen Daten.</p>'
            .'<ul>'
            .'<li><strong>Kartenhintergrund</strong>: OpenStreetMap Foundation (Vereinigtes Königreich) für die Kartenkacheln; OpenFreeMap für die Schriftarten der Karte; MapTiler (Schweiz), wenn dieser Hintergrund aktiviert ist.</li>'
            .'<li><strong>Optionale Ebenen</strong>, die nur geladen werden, wenn Sie sie aktivieren: OpenRailwayMap auf der Karte; im Streckenverlauf-Editor die alten Karten und Luftbilder des IGN (Géoplateforme, Frankreich).</li>'
            .'<li><strong>Archivbilder</strong>: Wikimedia Foundation (Vereinigte Staaten) für die in den Galerien angezeigten Bilder von Wikimedia Commons.</li>'
            .'</ul>'
            .'<p>Diese Dienste wenden ihre eigene Datenschutzerklärung an. Die Schriftarten der Website werden von VFF selbst gehostet: Es erfolgt keine Anfrage an Google Fonts.</p>',
    ],

    'account' => [
        'title' => 'Wenn Sie ein Konto anlegen',
        'paragraph' => '<ul>'
            .'<li><strong>Daten</strong>: Anzeigename, E-Mail-Adresse, Passwort (nur in gehashter Form gespeichert, niemals im Klartext), Rolle (Besucher, Mitwirkender, Prüfer oder Administrator), bevorzugte Sprache und, wenn Sie sie angeben, eine kurze Vorstellung und ein Avatar.</li>'
            .'<li><strong>Zwecke</strong>: Sie identifizieren, Ihre Beiträge und deren Prüfung verwalten, Ihnen einen Link zum Zurücksetzen Ihres Passworts senden.</li>'
            .'<li><strong>Rechtsgrundlage</strong>: die Erfüllung der <a href="/use-conditions">Nutzungsbedingungen</a>, die Sie mit dem Anlegen des Kontos akzeptieren.</li>'
            .'<li><strong>Speicherdauer</strong>: solange das Konto besteht. Ein Konto, das 3 Jahre lang nicht genutzt wird, wird gelöscht, nach einer Warnung, die einen Monat vorher per E-Mail verschickt wird. Ein Link zum Zurücksetzen des Passworts läuft nach 60 Minuten ab.</li>'
            .'</ul>',
    ],

    'contributions' => [
        'title' => 'Beiträge',
        'paragraph' => '<p>Jeder Beitrag (Streckenverlauf, datiertes Ereignis, Korrektur, Dokument) wird mit Ihrer Kennung und seinem Datum gespeichert, nach der Prüfung außerdem mit der Kennung der Person, die ihn freigegeben oder abgelehnt hat, und deren Anmerkungen. Die Prüfer sehen den Anzeigenamen des Autors. Ein freigegebener Beitrag geht unter der in den Nutzungsbedingungen vorgesehenen Lizenz in die Enzyklopädie ein.</p>'
            .'<p>Wenn Sie Ihr Konto löschen, bleiben Ihre freigegebenen Beiträge veröffentlicht, da sie Teil der Enzyklopädie und ihrer Lizenz sind, sie sind aber nicht mehr mit Ihrem Namen verknüpft. Ihre ausstehenden Beiträge werden gelöscht.</p>',
    ],

    'emails' => [
        'title' => 'E-Mails',
        'paragraph' => 'VFF sendet nur Service-E-Mails, etwa den Link zum Zurücksetzen des Passworts. Kein Newsletter, keine Werbung.',
    ],

    'recipients' => [
        'title' => 'Wer Zugriff auf Ihre Daten hat',
        'paragraph' => '<p>Nur die Personen, die VFF für UnxWares verwalten, und nur soweit es für sie erforderlich ist. Die Prüfer sehen den Anzeigenamen des Autors der Beiträge, die sie prüfen.</p>'
            .'<p>Auftragsverarbeiter: UnxWares hostet die Website auf bei Hetzner Online GmbH gemieteten Servern (Deutschland, Rechenzentren in der Europäischen Union), mit Netaris (Frankreich) für das Netzwerk und Infomaniak Network SA (Schweiz) für den Domainnamen und die DNS. Die Service-E-Mails werden über das Mailsystem von UnxWares versendet, das von Hetzner Online GmbH (Deutschland) gehostet wird.</p>',
    ],

    'transfers' => [
        'title' => 'Übermittlungen außerhalb der Europäischen Union',
        'paragraph' => 'Ihre Daten werden in der Europäischen Union gehostet. Infomaniak, die die DNS verwaltet, hat ihren Sitz in der Schweiz, einem Land, dem die Europäische Kommission ein angemessenes Schutzniveau bescheinigt. Die weiter oben aufgeführten Drittdienste erhalten Ihre IP-Adresse direkt von Ihrem Browser, insbesondere OpenStreetMap (Vereinigtes Königreich) und die Wikimedia Foundation (Vereinigte Staaten).',
    ],

    'security' => [
        'title' => 'Sicherheit',
        'paragraph' => 'Verschlüsselte Verbindung (HTTPS), gehashte Passwörter, für Skripte unzugängliche Sitzungs-Cookies, Schutz der Formulare vor betrügerischen Übermittlungen, eingeschränkte Administratorzugriffe. Bei einer Datenschutzverletzung, die ein Risiko für Sie darstellt, benachrichtigen wir die CNIL (französische Datenschutzbehörde) und, falls erforderlich, die betroffenen Personen.',
    ],

    'rights' => [
        'title' => 'Ihre Rechte',
        'paragraph' => '<p>Sie können Auskunft über Ihre Daten, deren Berichtigung, Löschung, Übertragbarkeit oder die Einschränkung ihrer Verarbeitung verlangen und einer auf dem berechtigten Interesse beruhenden Verarbeitung widersprechen. Sie können auch Anweisungen zum Umgang mit Ihren Daten nach Ihrem Tod festlegen.</p>'
            .'<p>Schreiben Sie an <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>, wenn möglich von der Adresse Ihres Kontos aus. Wir antworten innerhalb eines Monats. Ihren Anzeigenamen, Ihre Vorstellung und Ihre Sprache können Sie auch in Ihrem Profil ändern.</p>'
            .'<p>Wenn Sie der Ansicht sind, dass Ihre Rechte nicht gewahrt werden, können Sie sich an die CNIL (französische Datenschutzbehörde) wenden: 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07, <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</p>',
    ],

    'minors' => [
        'title' => 'Minderjährige',
        'paragraph' => 'Die Website kann ohne Konto angesehen werden. Um vor dem Alter von 15 Jahren ein Konto anzulegen, ist die Zustimmung eines Inhabers der elterlichen Sorge erforderlich.',
    ],

    'changes' => [
        'title' => 'Änderungen dieser Erklärung',
        'paragraph' => 'Diese Erklärung folgt der Entwicklung der Website. Das Datum der letzten Aktualisierung steht oben auf der Seite. Bei wesentlichen Änderungen werden die Kontoinhaber benachrichtigt.',
    ],

    'contact' => [
        'title' => 'Kontakt',
        'paragraph' => 'Bei Fragen zu Ihren Daten: <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.',
    ],
];
