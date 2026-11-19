<?php

// Déclaration d'accessibilité, au format du modèle officiel du RGAA 4.1.2 (article 47 de la loi n° 2005-102).
// Elle repose sur l'audit interne RGAA 4.1.2 du 2026-11-19 (106 critères, 28 pages) : rapport
// storage/app/reports/agents/nuit/audit-rgaa.md, grille storage/app/reports/agents/nuit/audit-rgaa-grille.csv,
// calcul refait par storage/app/data/rgaa/grille.py. Seuils officiels : conforme à 100 %, partiellement
// conforme à partir de 50 % de critères respectés, non conforme en dessous ou sans audit en cours de validité.
// À mettre à jour à chaque correction ; la date est dans resources/js/Pages/Accessibility.svelte
// (prop updated) et dans la section establishment. Le français fait foi.
// Traduction de lang/fr/accessibility.php.
return [
    'meta_title' => 'Accessibility statement',
    'meta_description' => 'Compliance status of Voies Ferrées de France with the RGAA, internal audit results, inaccessible content and means of redress.',

    'hero' => [
        'title' => 'Accessibility statement',
        'updated' => 'Issued on :date',
    ],

    'commitment' => [
        'title' => 'Our commitment',
        'paragraph' => 'Voies Ferrées de France (VFF) is published by UnxWares, a French simplified joint-stock company (société par actions simplifiée) whose details are given in the <a href="/legal">legal notice</a>. UnxWares wants VFF to be usable by everyone, whatever their disability, device or way of browsing. The site is not subject to the accessibility obligation of article 47 of French law no. 2005-102 of 11 February 2005, but voluntarily follows the French accessibility standard RGAA 4.1.2 (référentiel général d\'amélioration de l\'accessibilité). This statement applies to the site <a href="https://vff-project.org">vff-project.org</a>. This page is a translation provided for convenience: only the French version is legally binding.',
    ],

    'status' => [
        'title' => 'Compliance status',
        'paragraph' => '<p><strong>The site vff-project.org is partially compliant with RGAA 4.1.2.</strong></p>'
            .'<p>This status is based on the internal audit carried out by UnxWares on 19 November 2026, following the RGAA 4.1.2 method: all 106 criteria were checked on a sample of 28 pages. The audit was not carried out by a third party.</p>',
    ],

    'results' => [
        'title' => 'Test results',
        'paragraph' => '<p>The internal compliance audit shows that:</p>'
            .'<ul>'
            .'<li>82% of the RGAA criteria are met (59 of the 72 criteria that apply to the site);</li>'
            .'<li>the average compliance rate of the online service is 99% (98.87%, the average of the rates of the 28 pages).</li>'
            .'</ul>'
            .'<p>Twenty-four of the 28 pages meet every criterion that applies to them. Eleven of the thirteen criteria not met fail only in the two API documentation tools, Swagger UI and Magidoc. The full grid (status and reason for each criterion on each page) can be downloaded: <a href="/rgaa/grille-audit-rgaa-2026-11-19.csv" download>RGAA audit grid (CSV file, 744 KB, in French)</a>.</p>'
            .'<p>The site has not been tested with a screen reader. The name, role and state of the components were checked in the code and in the browser’s accessibility tree, but how NVDA, JAWS, VoiceOver or TalkBack actually read them still has to be checked.</p>',
    ],

    'non_accessible' => [
        'title' => 'Inaccessible content',
        'paragraph' => '<p>Non-compliance:</p>'
            .'<ul>'
            .'<li>Line pages, list of stations: the small coloured strip under each station (its status in each decade) tells “open”, “status unconfirmed” and “closed” apart by colour alone (criterion 3.1). The opening and closing dates are written next to it, and screen readers read the full status. Since the audit, the strip also comes with a visible legend and each status has its own shape (solid, hatched, low or empty cell); this point will be checked again at the next audit.</li>'
            .'<li>Rolling stock pages: the arrow “→” used for renumberings (“PLM 1-30 → Est 601-612”) has no text equivalent (criterion 13.5).</li>'
            .'<li>REST API reference in Swagger UI (/docs/swagger): interface in English on a page declared in French (8.7), a link placed inside a button (8.2), two main regions (9.2), the “Filter by tag” field has no label (11.1). The same endpoints are described on the <a href="/api">API access</a> page.</li>'
            .'<li>GraphQL API reference in Magidoc (/docs/graphql): interface in English (8.7), page title that does not name the site (8.6), VFF logo labelled “Magidoc” (1.3), “#” anchors next to the headings (6.1 and 9.1), a button without a name (7.1), a button placed inside a link (8.2), horizontal scrolling at 320 pixels wide (10.11), search field without a label (11.1), no skip link (12.7). The <a href="/api">API access</a> page presents the GraphQL API.</li>'
            .'<li>Outside the audited sample, the captions and authors of some Wikimedia Commons images are shown in their original language (English, Dutch…) without that language being marked (criterion 8.7).</li>'
            .'</ul>'
            .'<p>We do not claim any exemption for disproportionate burden.</p>',
    ],

    'exempt' => [
        'title' => 'Content not subject to the accessibility obligation',
        'paragraph' => 'Online maps and mapping services are among the content not subject to the accessibility obligation: the network map, the mini-maps on line and station pages, the embeddable map, the OpenStreetMap base map and third-party layers, with their zoom buttons. Their use with a screen reader is not guaranteed. The information they show is also provided as text: line and station pages, directories of <a href="/lines">lines</a> and <a href="/stations">stations</a>, tables by decade, and the <a href="/api">API</a>. Third-party content that VFF neither produces nor controls (Wikimedia Commons images, linked sites) is outside the scope of this statement.',
    ],

    'establishment' => [
        'title' => 'Preparation of this statement',
        'paragraph' => '<p>This statement was prepared on 19 November 2026, after the internal RGAA audit.</p>'
            .'<ul>'
            .'<li><strong>Technologies used</strong>: HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Test environment</strong>: Chromium browser on Linux, from 320 to 1,280 pixels wide, in the light theme (the dark theme was measured on 19 November), in French. No assistive technology was used.</li>'
            .'<li><strong>Tools</strong>: inspection of the generated code and of the accessibility tree (project scripts), contrast calculation, keyboard walk-through of 45 tab stops per page, screenshots without style sheets, at 320 pixels wide and with increased text spacing, review of the component code.</li>'
            .'<li><strong>Pages checked</strong>: home; legal notice, which also gives the contact details (the site has no contact page); accessibility statement; contribute, which serves as the help page; sign-in; registration; forgotten password; map; directories of lines, stations, rolling stock and accidents; one line page (610000), station page (Angoulême), rolling stock page (PLM 1-30) and accident page (Meudon, 1842); API access; REST (Swagger UI) and GraphQL (Magidoc) references; the “page not found” error page; the search window; profile; trace editor; my contributions; contribution review; and, drawn at random, the privacy policy, the terms of use and the technical credits. The site has no site map. The REST reference in Scalar (/docs/api) was not part of the sample.</li>'
            .'</ul>',
    ],

    'feedback' => [
        'title' => 'Feedback and contact',
        'paragraph' => 'If you cannot access content or a service, write to <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>, with “Accessibility” as the subject. We will direct you to an accessible solution or provide you with the content in another form, as soon as possible and at the latest within one month.',
    ],

    'recourse' => [
        'title' => 'Means of redress',
        'paragraph' => '<p>If you have reported to us an accessibility defect that prevents you from accessing content or a service, and you have not received a satisfactory response, you may:</p>'
            .'<ul>'
            .'<li>write to the Défenseur des droits (French Defender of Rights), using the online form at <a href="https://www.defenseurdesdroits.fr" target="_blank" rel="noopener">defenseurdesdroits.fr</a>;</li>'
            .'<li>contact the delegate of the Défenseur des droits in your region;</li>'
            .'<li>call 09 69 39 00 00;</li>'
            .'<li>send a letter, free of charge and without a stamp, to: Défenseur des droits, Libre réponse 71120, 75342 Paris CEDEX 07.</li>'
            .'</ul>',
    ],

    'plan' => [
        'title' => 'Planned improvements',
        'paragraph' => 'We plan to check the decade strip in the list of stations again, to spell out rolling stock renumberings in words, to translate the Swagger UI and Magidoc interfaces into French and fix their code, and then to record the language of each caption when importing Wikimedia Commons images. We also plan to test the site with a screen reader and to audit the REST reference in Scalar. The compliance rate on this page will be updated after each fix.',
    ],
];
