<?php

// Conditions générales d'utilisation du site et de l'API. Les licences doivent rester cohérentes
// avec les mentions légales (legal.php) et la politique de confidentialité (privacy.php).
// Le français fait foi.
// Traduction de lang/fr/use_conditions.php.
return [
    'meta_title' => 'Terms of use',
    'meta_description' => 'Rules for using Voies Ferrées de France: account, contributions, licences, reuse of data and of the API.',

    'hero' => [
        'title' => 'Terms of use',
        'updated' => 'Last updated: :date',
    ],

    'purpose' => [
        'title' => 'Purpose',
        'paragraph' => 'These terms of use govern the use of Voies Ferrées de France (VFF), the site <a href="https://vff-project.org">vff-project.org</a> and its APIs, published by UnxWares (see the <a href="/legal">legal notice</a>). In the event of any difference between translations, the French version prevails.',
    ],

    'acceptance' => [
        'title' => 'Acceptance',
        'paragraph' => 'Using the site constitutes acceptance of the terms of use in force. Creating an account requires you to accept them expressly. If you do not accept them, do not use the service.',
    ],

    'access' => [
        'title' => 'Access to the service',
        'paragraph' => 'VFF is free and can be browsed without an account. Contributing requires an account. The service is provided as is: it may be interrupted for maintenance, change or stop, without entitling you to compensation.',
    ],

    'account' => [
        'title' => 'Your account',
        'paragraph' => '<ul>'
            .'<li>An account is personal. It requires a display name, a valid email address and a password of at least 8 characters, which you keep secret. You are responsible for what is done from your account.</li>'
            .'<li>Your display name must not impersonate another person, nor be insulting or misleading.</li>'
            .'<li>Before the age of 15, the consent of a holder of parental authority is required.</li>'
            .'<li>Every account starts with the visitor role. The roles of contributor, validator (who reviews other people\'s contributions) and administrator are granted by the VFF team.</li>'
            .'<li>You may request the deletion of your account at any time, as explained in the <a href="/privacy">privacy policy</a>.</li>'
            .'</ul>',
    ],

    'contributing' => [
        'title' => 'Contributing',
        'paragraph' => 'You may propose routes, corrections, dated events (opening, closure, electrification, etc.) and documents. Each piece of information must rely on a verifiable source: the link to the source and, for a date, the quotation that establishes it. Contributions are reviewed before being published.',
    ],

    'licence' => [
        'title' => 'Licence of your contributions',
        'paragraph' => '<p>You remain the author of your contributions. By submitting them, you agree that they are published and reusable by everyone:</p>'
            .'<ul>'
            .'<li>data (routes, dates, stations, line characteristics) under the <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener">Open Database License (ODbL) 1.0</a>, like the rest of the database;</li>'
            .'<li>texts, photographs and documents under <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.en" target="_blank" rel="noopener">CC BY-SA 4.0</a>.</li>'
            .'</ul>'
            .'<p>This licence is free of charge, valid worldwide and cannot be withdrawn for a contribution that has already been published. Your display name is used to credit you.</p>'
            .'<p>You warrant that you hold the necessary rights. Do not copy protected content (text, map, photograph, database) without the permission of its holder or a compatible licence.</p>',
    ],

    'moderation' => [
        'title' => 'Validation and moderation',
        'paragraph' => '<p>Contributions are reviewed by validators before publication. A rejected contribution is rejected with a reason.</p>'
            .'<p>UnxWares may remove at any time content that is unlawful or contrary to these terms of use. It informs the author with the reasons, unless the law forbids it. To challenge a decision, write to <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>: it will be reviewed again, as far as possible by a person other than the one who made it.</p>',
    ],

    'conduct' => [
        'title' => 'Rules of conduct',
        'paragraph' => '<p>It is forbidden to publish on VFF:</p>'
            .'<ul>'
            .'<li>unlawful content: defamation, insults, incitement to hatred or violence, invasion of privacy or breach of image rights, infringement;</li>'
            .'<li>other people\'s personal data: address, telephone number, a photograph of an identifiable person without their consent;</li>'
            .'<li>invented or deliberately false information, or a source used contrary to its meaning;</li>'
            .'<li>advertising, spam or links unrelated to the subject;</li>'
            .'<li>vandalism: deletion or degradation of content without justification.</li>'
            .'</ul>'
            .'<p>It is also forbidden to disrupt the service: attempted intrusion, circumvention of API limits, mass creation of accounts, automated collection of other users\' personal data.</p>',
    ],

    'safety' => [
        'title' => 'Railway safety',
        'paragraph' => 'VFF also documents closed or abandoned lines. A railway track or land, even if it looks disused, may be in service, belong to someone and be dangerous: never enter it without authorisation. VFF does not accept any document obtained by putting oneself in danger or breaking the law.',
    ],

    'reuse' => [
        'title' => 'Reusing the data and the API',
        'paragraph' => '<ul>'
            .'<li>VFF data is freely reusable, including for commercial purposes, under the ODbL 1.0: cite “Voies Ferrées de France (vff-project.org)” and its sources, including “© OpenStreetMap contributors”, and distribute under the same licence any derived database that you publish. The only exception is data taken from sources that only allow non-commercial use, flagged by the <code>source</code> field of the API: it may only be reused for non-commercial purposes, citing its source with a link.</li>'
            .'<li>The REST <a href="/api">API</a> and the GraphQL API are public, free and keyless, within the limit of 300 requests per minute per IP address. Identify your application in the <code>User-Agent</code> header, with a way to contact you.</li>'
            .'<li>For large volumes, prefer the exports (GeoJSON, GTFS) to looped requests. UnxWares may limit or block a use that degrades the service.</li>'
            .'<li>The API is provided without any guarantee of availability. Changes to it are announced in its documentation.</li>'
            .'</ul>',
    ],

    'liability' => [
        'title' => 'Liability',
        'paragraph' => '<p>VFF information is documentary and may contain errors, particularly for older periods. It does not replace official documents and must not be used for railway safety or operating decisions.</p>'
            .'<p>Each contributor is responsible for the content they publish. For this content, UnxWares acts as a host: its liability can be incurred only if, having been informed of manifestly unlawful content, it did not promptly remove it (see “Reporting unlawful content” in the <a href="/legal">legal notice</a>).</p>'
            .'<p>Within the limits permitted by law, UnxWares is not liable for any interruption of the service, loss of data or indirect damage related to the use of the site.</p>',
    ],

    'property' => [
        'title' => 'Intellectual property and personal data',
        'paragraph' => 'The code, data and content of VFF are subject to the licences described in the <a href="/legal">legal notice</a>. The processing of your personal data is described in the <a href="/privacy">privacy policy</a>.',
    ],

    'suspension' => [
        'title' => 'Suspension and deletion of an account',
        'paragraph' => 'In the event of a breach of the terms of use, UnxWares may suspend or delete an account, after a warning except in urgent cases or serious breaches. The decision is reasoned and can be challenged at <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>.',
    ],

    'changes' => [
        'title' => 'Changes to the terms of use',
        'paragraph' => 'UnxWares may amend the terms of use. The version in force is the one published on this page, with its date. Account holders are notified of any important change at least 15 days before it takes effect. Continuing to use the service afterwards constitutes acceptance.',
    ],

    'law' => [
        'title' => 'Governing law and disputes',
        'paragraph' => 'The terms of use are governed by French law. In the event of a dispute, let us first seek an amicable solution: write to <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>. Failing that, the French courts have jurisdiction, under the conditions provided for by law.',
    ],

    'contact' => [
        'title' => 'Contact',
        'paragraph' => 'For any question about these terms: <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>.',
    ],
];
