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
    'meta_title' => 'Privacy policy',
    'meta_description' => 'Personal data processed by Voies Ferrées de France, cookies, third-party services and your rights.',

    'hero' => [
        'title' => 'Privacy policy',
        'updated' => 'Last updated: :date',
    ],

    'summary' => [
        'title' => 'In brief',
        'paragraph' => '<ul>'
            .'<li>No advertising, no audience measurement, no tracking tools.</li>'
            .'<li>No account is needed to browse the site.</li>'
            .'<li>Only cookies that are necessary for the site to work or that store your settings: nothing to accept.</li>'
            .'<li>Your data is neither sold, nor rented, nor passed on.</li>'
            .'<li>Your data is hosted in the European Union.</li>'
            .'</ul>'
            .'<p>This page is a translation provided for convenience: only the French version is legally binding.</p>',
    ],

    'controller' => [
        'title' => 'Data controller',
        'paragraph' => 'UnxWares, a simplified joint-stock company (SAS) registered with the Paris Trade and Companies Register (RCS) under number 945 207 991, 78 avenue des Champs-Élysées, bureau 326, 75008 Paris, represented by its president, Baptiste Gosselin. For any question about your data: <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>. UnxWares is not required to appoint a data protection officer and has not appointed one.',
    ],

    'visitors' => [
        'title' => 'If you browse the site without an account',
        'paragraph' => '<p>To send you the pages, the server necessarily receives your IP address, the page requested, the date and the identification of your browser. This information is recorded in technical logs, used only for the security of the service, error diagnosis and the fight against abuse, and kept for 12 months at most.</p>'
            .'<p>To protect the API, the number of requests from each IP address is counted for one minute (300 requests at most), then forgotten.</p>'
            .'<p>Legal basis: UnxWares\' legitimate interest in ensuring the security and proper functioning of the site.</p>',
    ],

    'cookies' => [
        'title' => 'Cookies and storage in your browser',
        'paragraph' => '<div class="table-wrap"><table>'
            .'<thead><tr><th scope="col">Name</th><th scope="col">Purpose</th><th scope="col">Duration</th></tr></thead>'
            .'<tbody>'
            .'<tr><td><code>vff_session</code></td><td>Maintains your session: login, form submission.</td><td>2 hours of inactivity</td></tr>'
            .'<tr><td><code>XSRF-TOKEN</code></td><td>Protects forms against fraudulent submissions.</td><td>2 hours</td></tr>'
            .'<tr><td><code>remember_web_…</code></td><td>Keeps you logged in if you tick “Remember me”.</td><td>About 400 days, or until you log out</td></tr>'
            .'<tr><td><code>locale</code></td><td>Remembers the language you chose.</td><td>1 year</td></tr>'
            .'<tr><td><code>theme</code></td><td>Remembers the light or dark theme you chose.</td><td>1 year</td></tr>'
            .'<tr><td><code>vff:trace-draft:v1</code> (local storage)</td><td>Keeps the draft from the route editor if you leave the page before submitting it. It stays in your browser until you submit it.</td><td>Until the draft is submitted or discarded</td></tr>'
            .'<tr><td><code>vff-static</code>, <code>vff-pages</code>, <code>vff-tiles</code>, <code>vff-offline</code> (app cache)</td><td>Keeps in your browser the site’s files, the public pages you viewed while not signed in (60 at most) and the VFF map tiles already displayed (500 at most), so that you can read them again without a connection. Account, contribution and review pages are never kept there. Nothing is sent to VFF.</td><td>30 days for a page, 7 days for a tile; everything is cleared with each new version of the site</td></tr>'
            .'</tbody></table></div>'
            .'<p>These cookies are strictly necessary for the service or remember a setting you chose: they are exempt from consent (article 82 of the French Data Protection Act, loi Informatique et Libertés). This is why the site does not display a banner. VFF sets no audience measurement or advertising cookies. You can delete them at any time from your browser.</p>',
    ],

    'third_party' => [
        'title' => 'Third-party services loaded by your browser',
        'paragraph' => '<p>Base maps and some images are loaded directly from other servers. Your browser then sends them your IP address and its technical information, as for any image coming from another site. Only the address of the site (vff-project.org) is given to them, not the page being viewed. VFF sends them no other data.</p>'
            .'<ul>'
            .'<li><strong>Base map</strong>: OpenStreetMap Foundation (United Kingdom), for the map tiles; OpenFreeMap, for the map fonts; MapTiler (Switzerland), when this base map is enabled.</li>'
            .'<li><strong>Optional layers</strong>, loaded only if you enable them: OpenRailwayMap on the map; in the route editor, the IGN old maps and photographs (Géoplateforme, France).</li>'
            .'<li><strong>Archive images</strong>: Wikimedia Foundation (United States), for the Wikimedia Commons images displayed in galleries.</li>'
            .'</ul>'
            .'<p>The map\'s “Locate me” button asks your browser for your position, with your permission, once and without tracking. It is only used to centre the map and never leaves your browser: VFF neither receives nor stores it. As whenever the map moves, the tiles for the area shown are then requested from VFF\'s servers and from the base map (OpenStreetMap, or MapTiler if enabled), which can infer roughly where you are.</p>'
            .'<p>These services apply their own privacy policy. The site\'s fonts are hosted by VFF: no request is made to Google Fonts.</p>',
    ],

    'account' => [
        'title' => 'If you create an account',
        'paragraph' => '<ul>'
            .'<li><strong>Data</strong>: display name, email address, password (stored only in hashed form, never in plain text), role (visitor, contributor, validator or administrator), preferred language, the date of your last signed-in visit, the date and version of the terms of use accepted when you registered and, if you provide them, a short introduction and an avatar.</li>'
            .'<li><strong>Purposes</strong>: to identify you, manage your contributions and their validation, and send you a link to reset your password.</li>'
            .'<li><strong>Legal basis</strong>: performance of the <a href="/use-conditions">terms of use</a>, which you accept by ticking the box when you register.</li>'
            .'<li><strong>Retention</strong>: for as long as the account exists. An account unused for 3 years is deleted, after a warning sent by email one month beforehand. A password reset link expires after 60 minutes.</li>'
            .'</ul>'
            .'<p>If you create an API access token from your profile, its name, creation date and date of last use (updated at most once a minute) are stored with your account. The token itself is stored only in hashed form, never in plain text, and is shown to you only once. With a token, your requests are counted per account for one minute, then forgotten. The name and dates of your tokens are included in your data export, without the token. A revoked token is deleted, and your tokens are deleted with your account. Creating and revoking a token are recorded in the audit log with the token’s number, without its name; this entry is erased 12 months later.</p>'
            .'<p>If you add a webhook from your profile, its address, the events you chose, its creation date, the date it was disabled (if it was) and the number of failed deliveries in a row are stored with your account, together with a log of its last 50 deliveries (event, date, result, HTTP code received). Its signing secret is stored encrypted and shown to you only once. The messages sent to your address contain no personal data. The address, the events and the log are included in your data export, without the secret. A deleted webhook disappears with its log, and your webhooks are deleted with your account. Adding and deleting a webhook are recorded in the audit log with its number, without its address; this entry is erased 12 months later.</p>',
    ],

    'contributions' => [
        'title' => 'Contributions',
        'paragraph' => '<p>Each contribution (route, dated event, correction, document) is recorded with your identifier and its date, then, once reviewed, with the identifier of the person who approved or rejected it and their remarks. Validators see the author\'s display name. A validated contribution enters the encyclopaedia under the licence provided for in the terms of use.</p>'
            .'<p>If you delete your account, your validated contributions remain published, because they are part of the encyclopaedia and its licence, but they are no longer linked to your name. Your pending contributions are deleted.</p>'
            .'<p>When one of your contributions is reviewed (approved, published, rejected or removed), you are told on the site and by email, with the reason if one was given. If you are a validator, you are also told when a contribution is awaiting your review and, on the site only, when an error is reported. These notifications give the number of the contribution or report and the line or page concerned, never the name of another member. They are included in the export of your data and deleted with your account; a notification you have read is erased 12 months after you read it.</p>'
            .'<p>VFF works out a reputation for each member: the number of their accepted and published contributions, the number of their rejected contributions, the number of reviews they have done if they are a validator, and the level that follows from them under public thresholds (<a href="/contribute/reputation">reputation help page</a>). These figures are recorded with your account and recalculated after every review decision. You see them in your profile. Validators see them, with your level, next to your name when they review one of your contributions. A suspended account keeps its figures but shows no level. They are included in the export of your data, are never published or provided through the API, and disappear with your account.</p>'
            .'<p>If an administrator suspends your account, the reason, the start date and the end date, if any, are recorded with your account. You are told on the site and by email, with the reason, and this information is included in the export of your data and disappears with your account. A suspended account can no longer propose contributions, review them or report errors, but keeps access to its data: you can download it and delete your account. The decision is also recorded in the moderation log, with the administrator who made it and your account number (removed if you delete your account), without the reason or your name; this entry is erased 12 months later.</p>',
    ],

    'error_reports' => [
        'title' => 'Error reports',
        'paragraph' => '<p>When you report an error on a page, we record your message, the source you give and, if you provide it, your email address, so that we can correct the page and reply to you. If you left your address, an email tells you what was done with your report, with the team\'s note. If you are signed in, the report is linked to your account. Legal basis: UnxWares\'s legitimate interest in keeping the encyclopaedia accurate.</p>'
            .'<p>Neither your IP address nor any tracker is stored with the report. Without an account, your IP address is only used to limit the number of reports sent, for 24 hours at most. Only VFF\'s validators and administrators see reports. A report is deleted 12 months after it has been dealt with; if you are a member, it is included in the export of your data and disappears with your account. To have a report sent without an account erased sooner, write to <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.</p>',
    ],

    'emails' => [
        'title' => 'Emails',
        'paragraph' => 'VFF sends only service emails, such as the password reset link, news of what happened to your contributions or notice of a change to the terms of use or to this policy. No newsletter, no marketing.',
    ],

    'recipients' => [
        'title' => 'Who has access to your data',
        'paragraph' => '<p>Only the people who administer VFF for UnxWares, and only for what they need. Validators see the display name of the author of the contributions they review.</p>'
            .'<p>Processors: UnxWares hosts the site on servers rented from Hetzner Online GmbH (Germany, data centres in the European Union), with Netaris (France) for the network and Infomaniak Network SA (Switzerland) for the domain name and DNS. Service emails are sent from the UnxWares mail server, hosted by Hetzner Online GmbH (Germany).</p>',
    ],

    'transfers' => [
        'title' => 'Transfers outside the European Union',
        'paragraph' => 'Your data is hosted in the European Union. Infomaniak, which manages the DNS, is established in Switzerland, a country recognised by the European Commission as providing an adequate level of protection. The third-party services listed above receive your IP address directly from your browser, notably OpenStreetMap (United Kingdom) and the Wikimedia Foundation (United States).',
    ],

    'security' => [
        'title' => 'Security',
        'paragraph' => 'Encrypted connection (HTTPS), hashed passwords, session cookies inaccessible to scripts, protection of forms against fraudulent submissions, limited administration access. In the event of a data breach presenting a risk to you, we notify the CNIL (French data protection authority) and, where necessary, the persons concerned.',
    ],

    'rights' => [
        'title' => 'Your rights',
        'paragraph' => '<p>You may request access to your data, its rectification, erasure or portability, or the restriction of its processing, and object to processing based on legitimate interest. You may also set directives on what happens to your data after your death.</p>'
            .'<p>Write to <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>, if possible from your account\'s address. We reply within one month. Your display name, introduction and language can also be changed from your profile.</p>'
            .'<p>If you consider that your rights are not being respected, you may lodge a complaint with the CNIL (French data protection authority): 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07, <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</p>',
    ],

    'minors' => [
        'title' => 'Minors',
        'paragraph' => 'The site can be browsed without an account. To create an account before the age of 15, the consent of a holder of parental authority is required.',
    ],

    'changes' => [
        'title' => 'Changes to this policy',
        'paragraph' => 'This policy follows the evolution of the site. Its update date appears at the top of the page. In the event of an important change, account holders are notified on the site and by email.',
    ],

    'contact' => [
        'title' => 'Contact',
        'paragraph' => 'For any question about your data: <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.',
    ],
];
