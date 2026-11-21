<?php

// Conditions générales d'utilisation du site et de l'API. Les licences doivent rester cohérentes
// avec les mentions légales (legal.php) et la politique de confidentialité (privacy.php).
// Le français fait foi.
// Traduction de lang/fr/use_conditions.php.
return [
    'meta_title' => 'Nutzungsbedingungen',
    'meta_description' => 'Regeln für die Nutzung von Voies Ferrées de France: Konto, Beiträge, Lizenzen, Weiterverwendung der Daten und der API.',

    'hero' => [
        'title' => 'Nutzungsbedingungen',
        'updated' => 'Zuletzt aktualisiert: :date',
    ],

    'purpose' => [
        'title' => 'Gegenstand',
        'paragraph' => 'Diese Nutzungsbedingungen regeln die Nutzung von Voies Ferrées de France (VFF), der Website <a href="https://vff-project.org">vff-project.org</a> und ihrer APIs, herausgegeben von UnxWares (siehe das <a href="/legal">Impressum</a>). Bei Unterschieden zwischen den Übersetzungen ist die französische Fassung maßgeblich.',
    ],

    'acceptance' => [
        'title' => 'Akzeptanz',
        'paragraph' => 'Die Nutzung der Website gilt als Akzeptanz der geltenden Nutzungsbedingungen. Für das Anlegen eines Kontos müssen Sie ihnen ausdrücklich zustimmen. Wenn Sie sie nicht akzeptieren, nutzen Sie den Dienst nicht.',
    ],

    'access' => [
        'title' => 'Zugang zum Dienst',
        'paragraph' => 'VFF ist kostenlos und kann ohne Konto genutzt werden. Zum Mitwirken ist ein Konto erforderlich. Der Dienst wird im jeweiligen Ist-Zustand bereitgestellt: Er kann zu Wartungszwecken unterbrochen werden, sich weiterentwickeln oder eingestellt werden, ohne dass dies einen Entschädigungsanspruch begründet.',
    ],

    'account' => [
        'title' => 'Ihr Konto',
        'paragraph' => '<ul>'
            .'<li>Ein Konto ist persönlich. Es erfordert einen Anzeigenamen, eine gültige E-Mail-Adresse und ein Passwort mit mindestens 8 Zeichen, das Sie geheim halten. Sie sind für alles verantwortlich, was von Ihrem Konto aus geschieht.</li>'
            .'<li>Ihr Anzeigename darf nicht die Identität einer anderen Person vortäuschen und nicht beleidigend oder irreführend sein.</li>'
            .'<li>Vor dem Alter von 15 Jahren ist die Zustimmung eines Inhabers der elterlichen Sorge erforderlich.</li>'
            .'<li>Jedes Konto beginnt mit der Rolle des Besuchers. Die Rollen Mitwirkender, Prüfer (der die Beiträge anderer prüft) und Administrator werden vom VFF-Team vergeben.</li>'
            .'<li>Sie können jederzeit die Löschung Ihres Kontos verlangen, wie in der <a href="/privacy">Datenschutzerklärung</a> erläutert.</li>'
            .'</ul>',
    ],

    'contributing' => [
        'title' => 'Mitwirken',
        'paragraph' => 'Sie können Streckenverläufe, Korrekturen, datierte Ereignisse (Eröffnung, Stilllegung, Elektrifizierung …) und Dokumente vorschlagen. Jede Information muss sich auf eine überprüfbare Quelle stützen: den Link zur Quelle und, bei einem Datum, das Zitat, das es belegt. Die Beiträge werden vor der Veröffentlichung geprüft.',
    ],

    'licence' => [
        'title' => 'Lizenz Ihrer Beiträge',
        'paragraph' => '<p>Sie bleiben Urheber Ihrer Beiträge. Mit dem Einsenden stimmen Sie zu, dass sie veröffentlicht und von allen weiterverwendet werden dürfen:</p>'
            .'<ul>'
            .'<li>die Daten (Streckenverläufe, Daten, Bahnhöfe, Merkmale der Strecken) unter der <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener">Open Database License (ODbL) 1.0</a>, wie der Rest der Datenbank;</li>'
            .'<li>die Texte, Fotos und Dokumente unter <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener">CC BY-SA 4.0</a>.</li>'
            .'</ul>'
            .'<p>Diese Lizenz ist kostenlos, weltweit gültig und kann für einen bereits veröffentlichten Beitrag nicht zurückgezogen werden. Ihr Anzeigename dient dazu, Sie als Urheber zu nennen.</p>'
            .'<p>Sie sichern zu, über die erforderlichen Rechte zu verfügen. Kopieren Sie keine geschützten Inhalte (Text, Karte, Fotografie, Datenbank) ohne die Erlaubnis des Rechteinhabers oder eine kompatible Lizenz.</p>',
    ],

    'moderation' => [
        'title' => 'Prüfung und Moderation',
        'paragraph' => '<p>Die Beiträge werden vor der Veröffentlichung von Prüfern gelesen. Die Ablehnung eines Beitrags wird begründet.</p>'
            .'<p>UnxWares kann jederzeit einen rechtswidrigen oder gegen diese Nutzungsbedingungen verstoßenden Inhalt entfernen. Sie informiert den Autor darüber mit Angabe der Gründe, sofern das Gesetz dies nicht verbietet. Um eine Entscheidung anzufechten, schreiben Sie an <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>: Sie wird erneut geprüft, soweit möglich von einer anderen Person als derjenigen, die sie getroffen hat.</p>',
    ],

    'conduct' => [
        'title' => 'Verhaltensregeln',
        'paragraph' => '<p>Es ist verboten, auf VFF Folgendes zu veröffentlichen:</p>'
            .'<ul>'
            .'<li>rechtswidrige Inhalte: Verleumdung, Beleidigung, Aufstachelung zu Hass oder Gewalt, Verletzung der Privatsphäre oder des Rechts am eigenen Bild, Urheberrechtsverletzung;</li>'
            .'<li>personenbezogene Daten anderer Personen: Adresse, Telefonnummer, Fotografie einer identifizierbaren Person ohne deren Zustimmung;</li>'
            .'<li>erfundene oder vorsätzlich falsche Informationen oder eine Quelle, die ihrem Sinn entfremdet wurde;</li>'
            .'<li>Werbung, Spam oder Links ohne Bezug zum Thema;</li>'
            .'<li>Vandalismus: Löschen oder Beschädigen von Inhalten ohne Rechtfertigung.</li>'
            .'</ul>'
            .'<p>Es ist ebenfalls verboten, den Dienst zu stören: Einbruchsversuche, Umgehung der Grenzen der API, massenhaftes Anlegen von Konten, automatisiertes Sammeln personenbezogener Daten anderer Nutzer.</p>',
    ],

    'safety' => [
        'title' => 'Eisenbahnsicherheit',
        'paragraph' => 'VFF dokumentiert auch stillgelegte oder aufgegebene Strecken. Eine Bahnstrecke oder ein Bahngelände, auch wenn es stillgelegt aussieht, kann in Betrieb sein, jemandem gehören und gefährlich sein: Betreten Sie es niemals ohne Erlaubnis. VFF akzeptiert keine Dokumente, die unter Selbstgefährdung oder unter Verstoß gegen das Gesetz beschafft wurden.',
    ],

    'reuse' => [
        'title' => 'Daten und API weiterverwenden',
        'paragraph' => '<ul>'
            .'<li>Die Daten von VFF dürfen unter der ODbL 1.0 frei weiterverwendet werden, auch für kommerzielle Zwecke: Nennen Sie „Voies Ferrées de France (vff-project.org)“ und seine Quellen, darunter „© OpenStreetMap-Mitwirkende“, und verbreiten Sie jede von Ihnen veröffentlichte abgeleitete Datenbank unter derselben Lizenz. Einzige Ausnahme sind Daten aus Quellen, die nur eine nicht kommerzielle Nutzung erlauben und die im Feld <code>source</code> der API gekennzeichnet sind: Sie dürfen nur zu nicht kommerziellen Zwecken weiterverwendet werden, mit Angabe der Quelle und einem Link.</li>'
            .'<li>Die REST-<a href="/api">API</a> und die GraphQL-API sind öffentlich, kostenlos und ohne Schlüssel nutzbar, im Rahmen von 300 Anfragen pro Minute und IP-Adresse. Kennzeichnen Sie Ihre Anwendung im Header <code>User-Agent</code>, mit einer Möglichkeit, Sie zu kontaktieren.</li>'
            .'<li>Für große Datenmengen nutzen Sie bitte die Exporte (GeoJSON, GTFS) statt Anfragen in Schleifen. UnxWares kann eine Nutzung, die den Dienst beeinträchtigt, einschränken oder sperren.</li>'
            .'<li>Die API wird ohne Verfügbarkeitsgarantie bereitgestellt. Ihre Weiterentwicklungen werden in ihrer Dokumentation angekündigt.</li>'
            .'</ul>',
    ],

    'liability' => [
        'title' => 'Haftung',
        'paragraph' => '<p>Die Informationen von VFF dienen der Dokumentation und können Fehler enthalten, insbesondere für frühere Zeiträume. Sie ersetzen keine amtlichen Dokumente und dürfen nicht für Entscheidungen zur Sicherheit oder zum Eisenbahnbetrieb herangezogen werden.</p>'
            .'<p>Jeder Mitwirkende ist für die von ihm veröffentlichten Inhalte verantwortlich. Für diese Inhalte handelt UnxWares als Hosting-Anbieter: Ihre Haftung kann nur dann geltend gemacht werden, wenn sie einen offensichtlich rechtswidrigen Inhalt, von dem sie Kenntnis erlangt hat, nicht unverzüglich entfernt hat (siehe „Rechtswidrige Inhalte melden“ im <a href="/legal">Impressum</a>).</p>'
            .'<p>Im gesetzlich zulässigen Rahmen ist UnxWares nicht verantwortlich für eine Unterbrechung des Dienstes, einen Datenverlust oder einen mittelbaren Schaden im Zusammenhang mit der Nutzung der Website.</p>',
    ],

    'property' => [
        'title' => 'Geistiges Eigentum und personenbezogene Daten',
        'paragraph' => 'Der Code, die Daten und die Inhalte von VFF unterliegen den im <a href="/legal">Impressum</a> beschriebenen Lizenzen. Die Verarbeitung Ihrer personenbezogenen Daten ist in der <a href="/privacy">Datenschutzerklärung</a> beschrieben.',
    ],

    'suspension' => [
        'title' => 'Sperrung und Löschung eines Kontos',
        'paragraph' => 'Bei einem Verstoß gegen die Nutzungsbedingungen kann UnxWares ein Konto sperren oder löschen, nach einer Warnung, außer in dringenden Fällen oder bei einem schweren Verstoß. Die Entscheidung wird begründet und kann unter <a href="mailto:contact@vff-project.org">contact@vff-project.org</a> angefochten werden. UnxWares beantwortet die Anfechtung innerhalb von 15 Tagen nach ihrem Eingang.',
    ],

    'changes' => [
        'title' => 'Änderung der Nutzungsbedingungen',
        'paragraph' => 'UnxWares kann die Nutzungsbedingungen ändern. Maßgeblich ist die auf dieser Seite veröffentlichte Fassung mit ihrem Datum. Die Kontoinhaber werden über jede wesentliche Änderung mindestens 15 Tage vor ihrem Inkrafttreten benachrichtigt. Die weitere Nutzung des Dienstes danach gilt als Akzeptanz.',
    ],

    'law' => [
        'title' => 'Anwendbares Recht und Streitigkeiten',
        'paragraph' => 'Die Nutzungsbedingungen unterliegen französischem Recht. Bei einem Streit suchen wir zunächst eine gütliche Lösung: Schreiben Sie an <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>. Andernfalls sind die französischen Gerichte zuständig, nach Maßgabe der gesetzlichen Bestimmungen.',
    ],

    'contact' => [
        'title' => 'Kontakt',
        'paragraph' => 'Bei Fragen zu diesen Bedingungen: <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>.',
    ],
];
