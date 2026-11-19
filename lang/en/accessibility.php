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
            .'<p>This status comes from an internal audit carried out on 19 November 2026 against the level A and AA criteria of WCAG 2.1, which are the basis of the RGAA. The audit did not cover the full RGAA grid (106 criteria) and was not carried out by a third party. We therefore publish no compliance rate, and the declared status remains to be confirmed by an audit of that kind.</p>',
    ],

    'results' => [
        'title' => 'Test results',
        'paragraph' => '<p>The internal audit of 19 November 2026 covered the sample of pages listed below, more than 250 views in all (pages, screen widths, light and dark themes, languages). Of the 50 WCAG 2.1 criteria at level A and AA, 42 are met, 1 is not (the language of passages, see below) and 7 do not apply, because the site has no video, no audio and no motion-operated controls.</p>'
            .'<p>Lighthouse scores 100 out of 100 for accessibility on the pages measured, on desktop and mobile. That score does not prove compliance: it covers only some of the criteria. We therefore also went through the whole site using the keyboard alone, and checked the display at 200% zoom and at 320 pixels wide, with increased text spacing and with reduced motion enabled.</p>'
            .'<p>The site has not been tested with a screen reader: difficulties that these tests do not reveal may remain.</p>',
    ],

    'non_accessible' => [
        'title' => 'Inaccessible content',
        'paragraph' => '<p>Non-compliance identified:</p>'
            .'<ul>'
            .'<li>Language of passages (RGAA criterion 8.7, WCAG 3.1.2): the captions and authors of Wikimedia Commons images are shown in their original language (English, Dutch, etc.) without that language being indicated. Screen readers may pronounce them with the wrong accent.</li>'
            .'</ul>'
            .'<p>Content that has not yet been audited:</p>'
            .'<ul>'
            .'<li>the members-only pages (route editor, validation, profile);</li>'
            .'<li>the API documentation tools (Swagger UI, GraphiQL, Magidoc), hosted by VFF and covered by this statement, some of whose labels are still in English.</li>'
            .'</ul>'
            .'<p>We claim no exemption for disproportionate burden.</p>',
    ],

    'exempt' => [
        'title' => 'Content not subject to the accessibility obligation',
        'paragraph' => 'Online maps and mapping services are among the content not subject to the accessibility obligation: the network map, the mini-maps on line and station pages, the embeddable map, the OpenStreetMap base map and third-party layers. Their use with a screen reader is not guaranteed. The information they show is also provided as text: line and station pages, directories of <a href="/lines">lines</a> and <a href="/stations">stations</a>, tables by decade, and the <a href="/api">API</a>. Third-party content that VFF neither produces nor controls (Wikimedia Commons images, linked sites) is outside the scope of this statement.',
    ],

    'establishment' => [
        'title' => 'Preparation of this statement',
        'paragraph' => '<p>This statement was prepared on 19 November 2026.</p>'
            .'<ul>'
            .'<li><strong>Technologies used</strong>: HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Test environment</strong>: Chromium browser on Linux, from 320 to 1,920 pixels wide, in light and dark themes, in French and partly in English and German.</li>'
            .'<li><strong>Tools</strong>: Lighthouse 13 (whose checks rely on axe-core), Chrome developer tools (accessibility tree, emulations), the project\'s own automatic measurements (calculated contrast, overflows, size of clickable areas), keyboard navigation, 200% zoom, text spacing, reduced motion.</li>'
            .'<li><strong>Pages checked</strong>: home, map, line pages (610000 and 752000) and station pages (Angoulême, Limoges-Bénédictins), directories of lines, stations and rolling stock, Our story, donations, contribute, credits, legal pages, API, login, registration, forgotten password, the “page not found” error page, embeddable map.</li>'
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
        'paragraph' => 'We plan to record the language of each caption when importing Wikimedia Commons images, then to mark it on the passage concerned, which will resolve the non-compliance with criterion 8.7. We also plan to audit the members-only pages and the API documentation tools, to test the site with a screen reader, and then to commission a full audit against the RGAA grid. Its compliance rate will be published on this page.',
    ],
];
