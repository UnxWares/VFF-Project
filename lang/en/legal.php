<?php

// Mentions légales (LCEN, art. 6). Informations vérifiées le 2026-10-21 : registre national des
// entreprises (recherche-entreprises.api.gouv.fr) et page https://www.unxwares.com/legal.
// Texte HTML de confiance rendu par LegalPage.svelte. Le français fait foi.
// Traduction de lang/fr/legal.php.
return [
    'meta_title' => 'Legal notice',
    'meta_description' => 'Publisher, host, licences, sources and reporting of content for Voies Ferrées de France.',

    'hero' => [
        'title' => 'Legal notice',
        'updated' => 'Last updated: :date',
    ],

    'editor' => [
        'title' => 'Site publisher',
        'paragraph' => '<p>Voies Ferrées de France (VFF), available at <a href="https://vff-project.org">vff-project.org</a>, is published by <strong>UnxWares</strong>, a simplified joint-stock company (SAS) with a share capital of €1,000, registered with the Paris Trade and Companies Register (RCS) under number 945 207 991.</p>'
            .'<ul>'
            .'<li>SIRET: 945 207 991 00015</li>'
            .'<li>Intra-Community VAT number: FR93 945 207 991</li>'
            .'<li>Registered office: 78 avenue des Champs-Élysées, bureau 326, 75008 Paris, France</li>'
            .'<li>Telephone: +33 6 70 33 02 22</li>'
            .'<li>Email: <a href="mailto:contact@vff-project.org">contact@vff-project.org</a></li>'
            .'<li>Company website: <a href="https://www.unxwares.com" target="_blank" rel="noopener">www.unxwares.com</a></li>'
            .'</ul>'
            .'<p>This page is a translation provided for convenience: only the French version is legally binding.</p>',
    ],

    'publication' => [
        'title' => 'Publication director',
        'paragraph' => 'Baptiste Gosselin, president of UnxWares.',
    ],

    'hosting' => [
        'title' => 'Hosting',
        'paragraph' => '<p>The site is hosted by <strong>UnxWares</strong>, on servers it administers (contact details above).</p>'
            .'<p>UnxWares relies on the following providers:</p>'
            .'<ul>'
            .'<li><strong>Hetzner Online GmbH</strong>, Industriestr. 25, 91710 Gunzenhausen, Germany: servers, in data centres in the European Union;</li>'
            .'<li><strong>Netaris</strong>, a single-shareholder simplified joint-stock company (SAS) registered with the Paris Trade and Companies Register (RCS) under number 940 260 391, 149 avenue du Maine, 75014 Paris, France: network and infrastructure platform;</li>'
            .'<li><strong>Infomaniak Network SA</strong>, rue Eugène-Marziano 25, 1227 Geneva, Switzerland: domain name and DNS servers.</li>'
            .'</ul>',
    ],

    'nature' => [
        'title' => 'Nature of the site',
        'paragraph' => 'VFF is a collaborative encyclopaedia of the French rail network, from 1830 to today: routes, dates, stations and the state of lines in each period, with their sources. The site is free, ad-free and has no tracking tools. It sells nothing.',
    ],

    'accessibility' => [
        'title' => 'Accessibility',
        'paragraph' => 'The site does not comply with the French accessibility standard RGAA 4.1.2 (référentiel général d\'amélioration de l\'accessibilité), as no audit has been carried out to date. Known defects, planned improvements and how to report a difficulty to us are detailed in the <a href="/accessibility">accessibility statement</a>.',
    ],

    'licences' => [
        'title' => 'Intellectual property and licences',
        'paragraph' => '<ul>'
            .'<li><strong>Source code</strong>: MIT licence (<a href="https://github.com/UnxWares/VFF-Project" target="_blank" rel="noopener">github.com/UnxWares/VFF-Project</a>). The model that extracts tracks from old aerial photographs, and its weights, are also under the MIT licence (<a href="https://github.com/UnxWares/VFF-Rail-Extraction" target="_blank" rel="noopener">github.com/UnxWares/VFF-Rail-Extraction</a>).</li>'
            .'<li><strong>Database</strong> (routes, dates, stations, state of lines, exports and API): <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener">Open Database License (ODbL) 1.0</a>, except for data taken from sources that only allow non-commercial use: its origin is given with each item (<code>source</code> field of the API) and it may only be reused for non-commercial purposes, citing its source with a link.</li>'
            .'<li><strong>Texts</strong>: texts written for VFF are licensed under <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.en" target="_blank" rel="noopener">CC BY-SA 4.0</a>. Wikipedia extracts remain under CC BY-SA 4.0 and are presented with a link to the article and its version.</li>'
            .'<li><strong>Images and documents</strong>: each file keeps its own licence, shown with its author. Wikimedia Commons images are displayed by direct link, without a copy on our servers. Files uploaded by contributors are under CC BY-SA 4.0.</li>'
            .'<li><strong>Name and logo</strong>: the name “Voies Ferrées de France”, the acronym “VFF” and the logo designate the project published by UnxWares. You may quote them to talk about the project, but not use them in a way that would suggest a partnership with, or an endorsement by, us.</li>'
            .'</ul>',
    ],

    'sources' => [
        'title' => 'Data sources',
        'paragraph' => '<p>VFF cross-references open sources, each cited with its licence, including:</p>'
            .'<ul>'
            .'<li>OpenStreetMap: © OpenStreetMap contributors, ODbL licence;</li>'
            .'<li>SNCF Réseau, SNCF Voyageurs and SNCF Gares &amp; Connexions (SNCF open data portal): ODbL;</li>'
            .'<li>IGN, Géoplateforme (BD TOPO, aerial photographs and old maps): Licence Ouverte 2.0 (French Open Licence 2.0);</li>'
            .'<li>OpenHistoricalMap and Wikidata: CC0;</li>'
            .'<li>Wikipedia: CC BY-SA 4.0; Wikimedia Commons: licence of each file.</li>'
            .'</ul>'
            .'<p>The full list, with attributions, appears on the <a href="/credits/tech">Technical credits</a> and <a href="/credits/media">Media credits</a> pages.</p>',
    ],

    'interests' => [
        'title' => 'Commitment',
        'paragraph' => 'The creator of VFF is a member of the association AngouLim, which campaigns for the reopening of the Angoulême - Limoges line, because he considers it important to defend forgotten lines. VFF supports this campaign on the line\'s page.',
    ],

    'liability' => [
        'title' => 'Liability',
        'paragraph' => '<p>VFF brings together historical sources that sometimes contradict each other. Despite the review of contributions, errors may remain: inferred dates (flagged as such), approximate routes, incomplete states. The information is provided for documentary purposes and does not replace official documents.</p>'
            .'<p>It must never be used to move about or carry out work on the rail network. Railway tracks and land, even if they look abandoned, are closed to the public and dangerous.</p>'
            .'<p>Links to other sites are provided for information: UnxWares is not responsible for their content. To report an error, use the contribution tools or write to us.</p>',
    ],

    'report' => [
        'title' => 'Reporting unlawful content',
        'paragraph' => '<p>If content published on VFF seems unlawful to you, report it to <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>, stating:</p>'
            .'<ul>'
            .'<li>the exact address (URL) of the content;</li>'
            .'<li>the reasons why you consider it unlawful, with the relevant legal provisions if possible;</li>'
            .'<li>your name and email address;</li>'
            .'<li>a statement certifying that your report is accurate and made in good faith.</li>'
            .'</ul>'
            .'<p>We acknowledge receipt, examine the report without delay and remove the content if it is manifestly unlawful. The author of removed content is informed, with the reasons. Reporting content that you know to be lawful, in order to have it removed, is punishable by law.</p>',
    ],

    'contact' => [
        'title' => 'Contact',
        'paragraph' => 'For any question about this notice: <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>. For the company UnxWares: <a href="mailto:contact@unxwares.com">contact@unxwares.com</a>, +33 6 70 33 02 22.',
    ],
];
