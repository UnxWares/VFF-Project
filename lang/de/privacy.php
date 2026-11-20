<?php

// Politique de confidentialité (RGPD, loi Informatique et Libertés). Décrit ce que fait réellement
// le code au 2026-11-19 : cookies (config/session.php, LocaleController, lib/stores/theme.svelte.js),
// stockage local (Pages/Contribute/Trace.svelte), services tiers chargés par le navigateur
// (Components/Map/*, Lines/LineGallery.svelte), géolocalisation (Map/RailMap.svelte), comptes
// (AuthController, ProfileController), notifications (DatabaseNotification), signalements
// (ErrorReport, limiteur error-reports d'AppServiceProvider). Version : config/legal.php.
// Toute évolution de ces points doit mettre ce texte à jour. Le français fait foi.
// Traduction de lang/fr/privacy.php.
return [
    'meta_title' => 'Datenschutzerklärung',
    'meta_description' => 'Von Voies Ferrées de France verarbeitete personenbezogene Daten, Cookies, Drittdienste und Ihre Rechte.',

    'hero' => [
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
            .'<p>Ihr Browser kann der Website außerdem ohne Ihr Zutun eine Ressource melden, die eine Seite außerhalb der Sicherheitsregeln der Website geladen hat (Bericht zur „Content Security Policy“). Die Website speichert davon nur die Adresse der Seite ohne ihre Parameter, die betroffene Regel und die Herkunft der Ressource, ohne Ihre IP-Adresse, Ihren Browser oder Ihr Konto, 30 Tage lang.</p>'
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
            .'<tr><td><code>vff-static</code>, <code>vff-pages</code>, <code>vff-tiles</code>, <code>vff-mapdata</code>, <code>vff-offline</code> (Cache der App)</td><td>Bewahrt in Ihrem Browser die Dateien der Website, die öffentlichen Seiten, die Sie ohne Anmeldung aufgerufen haben (höchstens 60), die bereits angezeigten Kartenkacheln von VFF (höchstens 500) und die bereits angezeigten Dateien der Karte zu Bahnhöfen und Unfällen (höchstens 8) auf, damit Sie diese ohne Verbindung erneut lesen können. Seiten zu Konto, Beiträgen und Prüfung werden dort nie gespeichert. Der Hintergrund der Karte (OpenStreetMap oder ein anderer Dienst) wird dort nie gespeichert. Nichts davon wird an VFF gesendet.</td><td>30 Tage für eine Seite, 7 Tage für eine Kachel oder eine Kartendatei; mit jeder neuen Version der Website wird alles gelöscht</td></tr>'
            .'</tbody></table></div>'
            .'<p>Diese Cookies sind für den Dienst unbedingt erforderlich oder merken sich eine von Ihnen gewählte Einstellung: Sie sind von der Einwilligungspflicht ausgenommen (Artikel 82 des französischen Gesetzes „Informatique et Libertés“). Deshalb zeigt die Website kein Banner an. VFF setzt keine Cookies zur Reichweitenmessung oder für Werbung. Sie können sie jederzeit über Ihren Browser löschen.</p>',
    ],

    'third_party' => [
        'title' => 'Von Ihrem Browser geladene Drittdienste',
        'paragraph' => '<p>Die Kartenhintergründe und bestimmte Bilder werden direkt von anderen Servern geladen. Ihr Browser übermittelt diesen dabei Ihre IP-Adresse und seine technischen Informationen, wie bei jedem Bild von einer anderen Website. Ihnen wird nur die Adresse der Website (vff-project.org) mitgeteilt, nicht die aufgerufene Seite. VFF übermittelt ihnen keine anderen Daten.</p>'
            .'<ul>'
            .'<li><strong>Kartenhintergrund</strong>: OpenStreetMap Foundation (Vereinigtes Königreich) für die Kartenkacheln; OpenFreeMap für die Schriftarten der Karte; MapTiler (Schweiz), wenn dieser Hintergrund aktiviert ist.</li>'
            .'<li><strong>Optionale Ebenen</strong>, die nur geladen werden, wenn Sie sie aktivieren: OpenRailwayMap auf der Karte; im Streckenverlauf-Editor die alten Karten und Luftbilder des IGN (Géoplateforme, Frankreich).</li>'
            .'<li><strong>Archivbilder</strong>: Wikimedia Foundation (Vereinigte Staaten) für die Bilder von Wikimedia Commons, die in den Galerien, im Formular „Bild vorschlagen“ und im Prüfbereich angezeigt werden.</li>'
            .'</ul>'
            .'<p>Die Schaltfläche „Mich orten“ der Karte fragt Ihren Browser mit Ihrer Erlaubnis einmalig und ohne Verfolgung nach Ihrem Standort. Er dient nur dazu, die Karte zu zentrieren, und verlässt Ihren Browser nicht: VFF erhält ihn nicht und speichert ihn nicht. Wie bei jeder Bewegung der Karte werden danach die Kacheln des angezeigten Gebiets bei den Servern von VFF und beim Kartenhintergrund (OpenStreetMap oder, falls aktiviert, MapTiler) angefragt, die daraus ungefähr ableiten können, wo Sie sich befinden.</p>'
            .'<p>Diese Dienste wenden ihre eigene Datenschutzerklärung an. Die Schriftarten der Website werden von VFF selbst gehostet: Es erfolgt keine Anfrage an Google Fonts.</p>',
    ],

    'account' => [
        'title' => 'Wenn Sie ein Konto anlegen',
        'paragraph' => '<ul>'
            .'<li><strong>Daten</strong>: Anzeigename, E-Mail-Adresse, Passwort (nur in gehashter Form gespeichert, niemals im Klartext), Rolle (Besucher, Mitwirkender, Prüfer oder Administrator), bevorzugte Sprache, Datum Ihres letzten Besuchs mit Anmeldung, Datum und Fassung der bei der Registrierung akzeptierten Nutzungsbedingungen und, wenn Sie sie angeben, eine kurze Vorstellung und ein Avatar.</li>'
            .'<li><strong>Zwecke</strong>: Sie identifizieren, Ihre Beiträge und deren Prüfung verwalten, Ihnen einen Link zum Zurücksetzen Ihres Passworts senden.</li>'
            .'<li><strong>Rechtsgrundlage</strong>: die Erfüllung der <a href="/use-conditions">Nutzungsbedingungen</a>, die Sie mit dem dafür vorgesehenen Häkchen bei der Registrierung akzeptieren.</li>'
            .'<li><strong>Speicherdauer</strong>: solange das Konto besteht. Ein Konto, das 3 Jahre lang nicht genutzt wird, wird gelöscht, nach einer Warnung, die einen Monat vorher per E-Mail verschickt wird. Ein Link zum Zurücksetzen des Passworts läuft nach 60 Minuten ab.</li>'
            .'</ul>'
            .'<p>Wenn Sie in Ihrem Profil ein API-Zugangstoken erstellen, werden sein Name, sein Erstellungsdatum und das Datum seiner letzten Verwendung (höchstens einmal pro Minute aktualisiert) mit Ihrem Konto gespeichert. Das Token selbst wird nur gehasht gespeichert, nie im Klartext, und Ihnen nur einmal angezeigt. Mit einem Token werden Ihre Anfragen eine Minute lang pro Konto gezählt und danach vergessen. Name und Daten Ihrer Tokens sind im Export Ihrer Daten enthalten, ohne das Token selbst. Ein widerrufenes Token wird gelöscht, und Ihre Tokens werden mit Ihrem Konto gelöscht. Ein Token gilt ein Jahr: Sein Ablaufdatum wird mit ihm gespeichert, und 30 Tage vorher erhalten Sie eine E-Mail; das Datum dieser Erinnerung wird gespeichert, damit sie nur einmal gesendet wird. Erstellung, Verlängerung und Widerruf eines Tokens werden im Audit-Protokoll mit der Nummer des Tokens vermerkt, ohne seinen Namen; dieser Eintrag wird 12 Monate später gelöscht.</p>'
            .'<p>Wenn Sie in Ihrem Profil einen Webhook hinzufügen, werden seine Adresse, die gewählten Ereignisse, sein Erstellungsdatum, gegebenenfalls das Datum seiner Deaktivierung und die Zahl der fehlgeschlagenen Zustellungen in Folge mit Ihrem Konto gespeichert, dazu ein Protokoll seiner letzten 50 Zustellungen (Ereignis, Datum, Ergebnis, empfangener HTTP-Code). Sein Signaturgeheimnis wird verschlüsselt gespeichert und Ihnen nur einmal angezeigt. Die an Ihre Adresse gesendeten Nachrichten enthalten keine personenbezogenen Daten. Adresse, Ereignisse und Protokoll sind im Export Ihrer Daten enthalten, ohne das Geheimnis. Ein gelöschter Webhook verschwindet mit seinem Protokoll, und Ihre Webhooks werden mit Ihrem Konto gelöscht. Hinzufügen und Löschen eines Webhooks werden im Audit-Protokoll mit seiner Nummer vermerkt, ohne seine Adresse; dieser Eintrag wird 12 Monate später gelöscht.</p>',
    ],

    'contributions' => [
        'title' => 'Beiträge',
        'paragraph' => '<p>Jeder Beitrag (Streckenverlauf, datiertes Ereignis, Korrektur, Dokument) wird mit Ihrer Kennung und seinem Datum gespeichert, nach der Prüfung außerdem mit der Kennung der Person, die ihn freigegeben oder abgelehnt hat, und deren Anmerkungen. Die Prüfer sehen den Anzeigenamen des Autors. Ein freigegebener Beitrag geht unter der in den Nutzungsbedingungen vorgesehenen Lizenz in die Enzyklopädie ein.</p>'
            .'<p>Wenn Sie ein Bild vorschlagen, indem Sie eine Datei hochladen, speichert VFF diese Datei mit dem Namen ihres Urhebers und ihrer Lizenz, die Sie angeben, sowie, falls Sie sie nennen, Datum und Ort der Aufnahme und deren Koordinaten. Vor dem Speichern entfernt VFF die Metadaten der Datei (GPS-Standort, Kamera, Daten) und behält nur die Ausrichtung des Bildes. Bis zur Veröffentlichung sehen nur Sie und die Prüfer die Datei. Nach der Veröffentlichung werden Urheber, Lizenz, Datum, Ort und Koordinaten mit dem Bild angezeigt und über die API bereitgestellt. Die Datei wird gelöscht, wenn das Bild abgelehnt oder entfernt wird oder wenn Sie Ihr Konto vor der Veröffentlichung löschen. Wenn Sie ein Bild aus Wikimedia Commons vorschlagen, liest der Server von VFF Titel, Urheber und Lizenz auf Commons: An Commons wird nur die Adresse der Datei gesendet.</p>'
            .'<p>Wenn Sie Ihr Konto löschen, bleiben Ihre freigegebenen Beiträge veröffentlicht, da sie Teil der Enzyklopädie und ihrer Lizenz sind, sie sind aber nicht mehr mit Ihrem Namen verknüpft. Ihre ausstehenden Beiträge werden gelöscht.</p>'
            .'<p>Wenn einer Ihrer Beiträge geprüft wird (freigegeben, veröffentlicht, abgelehnt oder entfernt), werden Sie auf der Website und per E-Mail informiert, mit der Begründung, falls es eine gibt. Als Prüfer werden Sie außerdem informiert, wenn ein Beitrag auf Ihre Prüfung wartet, und, nur auf der Website, wenn ein Fehler gemeldet wird. Diese Benachrichtigungen nennen die Nummer des Beitrags oder der Meldung und die betroffene Strecke oder Seite, nie den Namen eines anderen Mitglieds. Sie sind im Export Ihrer Daten enthalten und werden mit Ihrem Konto gelöscht; eine gelesene Benachrichtigung wird 12 Monate nach dem Lesen gelöscht.</p>'
            .'<p>VFF berechnet für jedes Mitglied eine Reputation: die Zahl seiner angenommenen und veröffentlichten Beiträge, die Zahl seiner abgelehnten Beiträge, bei Prüfern die Zahl der geleisteten Prüfungen und die Stufe, die sich daraus mit öffentlichen Schwellen ergibt (<a href="/contribute/reputation">Hilfeseite zur Reputation</a>). Diese Zahlen werden mit Ihrem Konto gespeichert und nach jeder Prüfentscheidung neu berechnet. Sie sehen sie in Ihrem Profil. Die Prüfer sehen sie mit Ihrer Stufe neben Ihrem Namen, wenn sie einen Ihrer Beiträge prüfen. Ein gesperrtes Konto behält seine Zahlen, zeigt aber keine Stufe an. Sie sind im Export Ihrer Daten enthalten, werden nie veröffentlicht oder über die API weitergegeben und verschwinden mit Ihrem Konto.</p>'
            .'<p>Wenn ein Administrator Ihr Konto sperrt, werden die Begründung, das Anfangsdatum und gegebenenfalls das Enddatum mit Ihrem Konto gespeichert. Sie werden auf der Website und per E-Mail benachrichtigt, mit der Begründung; diese Angaben sind im Export Ihrer Daten enthalten und verschwinden mit Ihrem Konto. Ein gesperrtes Konto kann keine Beiträge mehr vorschlagen, prüfen oder Fehler melden, behält aber den Zugriff auf seine Daten: Sie können sie herunterladen und Ihr Konto löschen. Die Entscheidung wird außerdem im Moderationsprotokoll festgehalten, mit dem entscheidenden Administrator und Ihrer Kontonummer (entfernt, wenn Sie Ihr Konto löschen), ohne die Begründung und ohne Ihren Namen; dieser Eintrag wird 12 Monate später gelöscht.</p>',
    ],

    'error_reports' => [
        'title' => 'Fehlermeldungen',
        'paragraph' => '<p>Wenn Sie einen Fehler auf einer Seite melden, speichern wir Ihre Nachricht, die angegebene Quelle und, falls Sie sie angeben, Ihre E-Mail-Adresse, um die Seite zu korrigieren und Ihnen antworten zu können. Haben Sie Ihre Adresse angegeben, teilt Ihnen eine E-Mail mit, wie mit Ihrer Meldung verfahren wurde, zusammen mit der Anmerkung des Teams. Sind Sie angemeldet, wird die Meldung mit Ihrem Konto verknüpft. Rechtsgrundlage: das berechtigte Interesse von UnxWares an einer korrekten Enzyklopädie.</p>'
            .'<p>Weder Ihre IP-Adresse noch ein Tracker wird mit der Meldung gespeichert. Ohne Konto dient Ihre IP-Adresse nur dazu, die Zahl der Meldungen zu begrenzen, höchstens 24 Stunden lang. Nur die Prüfer und Administratoren von VFF sehen die Meldungen. Eine Meldung wird 12 Monate nach ihrer Bearbeitung gelöscht; sind Sie Mitglied, ist sie im Export Ihrer Daten enthalten und verschwindet mit Ihrem Konto. Wenn Sie eine ohne Konto gesendete Meldung früher löschen lassen möchten, schreiben Sie an <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.</p>',
    ],

    'emails' => [
        'title' => 'E-Mails',
        'paragraph' => 'VFF sendet nur Service-E-Mails, etwa den Link zum Zurücksetzen des Passworts, Nachrichten zu Ihren Beiträgen oder die Ankündigung einer Änderung der Nutzungsbedingungen oder dieser Erklärung. Kein Newsletter, keine Werbung.',
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
        'paragraph' => 'Diese Erklärung folgt der Entwicklung der Website. Das Datum der letzten Aktualisierung steht oben auf der Seite. Bei wesentlichen Änderungen werden die Kontoinhaber auf der Website und per E-Mail benachrichtigt.',
    ],

    'contact' => [
        'title' => 'Kontakt',
        'paragraph' => 'Bei Fragen zu Ihren Daten: <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.',
    ],
];
