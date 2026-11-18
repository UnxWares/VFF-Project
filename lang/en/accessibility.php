<?php

// Déclaration d'accessibilité, au format du RGAA 4.1.2. Sans audit RGAA complet, le référentiel
// impose l'état « non conforme ». Les contenus non accessibles reprennent les constats de l'audit
// interne du 2026-10-21 (storage/app/reports/agents/audit-ui.md) : à mettre à jour à chaque
// correction, et après l'audit RGAA (taux de conformité). Le français fait foi.
// Traduction de lang/fr/accessibility.php.
return [
    'meta_title' => 'Accessibility statement',
    'meta_description' => 'Compliance status of Voies Ferrées de France with the RGAA, inaccessible content and means of redress.',

    'hero' => [
        'eyebrow' => 'Accessibility',
        'title' => 'Accessibility statement',
        'updated' => 'Issued on :date',
    ],

    'commitment' => [
        'title' => 'Our commitment',
        'paragraph' => 'UnxWares wants Voies Ferrées de France to be usable by everyone, whatever their disability, device or way of browsing. VFF is not subject to the accessibility obligation of article 47 of French law no. 2005-102 of 11 February 2005, but voluntarily follows the French accessibility standard RGAA 4.1.2 (référentiel général d\'amélioration de l\'accessibilité). This statement applies to the site <a href="https://vff-project.org">vff-project.org</a>. This page is a translation provided for convenience: only the French version is legally binding.',
    ],

    'status' => [
        'title' => 'Compliance status',
        'paragraph' => '<p><strong>The site vff-project.org does not comply with RGAA 4.1.2.</strong></p>'
            .'<p>It has not yet undergone an RGAA compliance audit. In the absence of such an audit, the standard requires it to be declared non-compliant.</p>',
    ],

    'results' => [
        'title' => 'Test results',
        'paragraph' => 'An internal audit was carried out on 21 October 2026 on 47 pages and states of the site, at six screen widths (from 375 to 1,920 pixels), in light and dark themes, in French and partly in English and German. It identified display and usability defects, some of which affect accessibility. It does not follow the RGAA method and does not allow a compliance rate to be calculated.',
    ],

    'non_accessible' => [
        'title' => 'Inaccessible content',
        'paragraph' => '<p>Known defects, being fixed:</p>'
            .'<ul>'
            .'<li>Contrast: several orange texts on a light background, as well as white texts on an orange background, do not reach the 4.5:1 contrast ratio required for body text (criterion 3.2).</li>'
            .'<li>On some pages, the language and account menus pass under the page content on desktop, or are cut off on mobile: their entries are then not usable.</li>'
            .'<li>The interactive maps (network map by period, maps on line and station pages, route editor) are not fully usable with a keyboard and are not rendered by screen readers.</li>'
            .'<li>On small screens, some elements overlap, for example the “Today” note on line pages over the title of the next section.</li>'
            .'<li>Error messages on the login and registration forms are displayed in English on French pages, and the “page not found” error page is neither translated nor adapted to the dark theme.</li>'
            .'</ul>'
            .'<p>The site has not yet been tested with a screen reader: other difficulties may exist. Map information is also offered as text: line and station pages, lists of <a href="/lines">lines</a> and <a href="/stations">stations</a>, and the <a href="/api">API</a>.</p>',
    ],

    'exempt' => [
        'title' => 'Content not subject to the accessibility obligation',
        'paragraph' => 'Online maps and mapping services are among the content not subject to the accessibility obligation. VFF nevertheless endeavours to provide their essentials as text, in the line and station pages. Third-party content that VFF neither produces nor controls (Wikimedia Commons images, base maps, linked sites) is outside the scope of this statement.',
    ],

    'establishment' => [
        'title' => 'Preparation of this statement',
        'paragraph' => '<p>This statement was prepared on 21 October 2026.</p>'
            .'<ul>'
            .'<li><strong>Technologies used</strong>: HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Test environment</strong>: Chromium browser on Linux, from 375 to 1,920 pixels wide, in light and dark themes.</li>'
            .'<li><strong>Tools</strong>: automatic measurements (contrast, overflows, size of clickable areas, console errors) and visual review of each screenshot.</li>'
            .'<li><strong>Pages checked</strong>: home, map, lists and pages of lines and stations, rolling stock, contribute, donations, who we are, credits, legal pages, API and its documentation, login, registration, forgotten password, members-only pages, error page.</li>'
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
        'paragraph' => 'We first fix the blocking defects (header menus, overlaps, forms), then the contrast. A full RGAA compliance audit will then be carried out, and its compliance rate published on this page.',
    ],
];
