<?php

// Mentions légales (LCEN, art. 6). Informations vérifiées le 2026-10-21 : registre national des
// entreprises (recherche-entreprises.api.gouv.fr) et page https://www.unxwares.com/legal.
// Texte HTML de confiance rendu par LegalPage.svelte. Le français fait foi.
return [
    'meta_title' => 'Mentions légales',
    'meta_description' => 'Éditeur, hébergeur, licences, sources et signalement des contenus de Voies Ferrées de France.',

    'hero' => [
        'title' => 'Mentions légales',
        'updated' => 'Dernière mise à jour : :date',
    ],

    'editor' => [
        'title' => 'Éditeur du site',
        'paragraph' => '<p>Voies Ferrées de France (VFF), accessible à l’adresse <a href="https://vff-project.org">vff-project.org</a>, est éditée par <strong>UnxWares</strong>, société par actions simplifiée au capital de 1 000 €, immatriculée au registre du commerce et des sociétés de Paris sous le numéro 945 207 991.</p>'
            .'<ul>'
            .'<li>SIRET : 945 207 991 00015</li>'
            .'<li>Numéro de TVA intracommunautaire : FR93 945 207 991</li>'
            .'<li>Siège social : 78 avenue des Champs-Élysées, bureau 326, 75008 Paris, France</li>'
            .'<li>Téléphone : +33 6 70 33 02 22</li>'
            .'<li>Courriel : <a href="mailto:contact@vff-project.org">contact@vff-project.org</a></li>'
            .'<li>Site de la société : <a href="https://www.unxwares.com" target="_blank" rel="noopener">www.unxwares.com</a></li>'
            .'</ul>',
    ],

    'publication' => [
        'title' => 'Directeur de la publication',
        'paragraph' => 'Baptiste Gosselin, président d’UnxWares.',
    ],

    'hosting' => [
        'title' => 'Hébergement',
        'paragraph' => '<p>Le site est hébergé par <strong>UnxWares</strong>, sur des serveurs qu’elle administre (coordonnées ci-dessus).</p>'
            .'<p>UnxWares s’appuie sur les prestataires suivants :</p>'
            .'<ul>'
            .'<li><strong>Hetzner Online GmbH</strong>, Industriestr. 25, 91710 Gunzenhausen, Allemagne : serveurs, dans des centres de données de l’Union européenne ;</li>'
            .'<li><strong>Netaris</strong>, société par actions simplifiée unipersonnelle immatriculée au RCS de Paris sous le numéro 940 260 391, 149 avenue du Maine, 75014 Paris, France : réseau et plateforme d’infrastructure ;</li>'
            .'<li><strong>Infomaniak Network SA</strong>, rue Eugène-Marziano 25, 1227 Genève, Suisse : nom de domaine et serveurs DNS.</li>'
            .'</ul>',
    ],

    'nature' => [
        'title' => 'Nature du site',
        'paragraph' => 'VFF est une encyclopédie collaborative du réseau ferré français, de 1830 à aujourd’hui : tracés, dates, gares et état des lignes à chaque époque, avec leurs sources. Le site est gratuit, sans publicité et sans outil de suivi. Il ne vend rien.',
    ],

    'accessibility' => [
        'title' => 'Accessibilité',
        'paragraph' => 'Le site est non conforme au référentiel général d’amélioration de l’accessibilité (RGAA 4.1.2), faute d’audit à ce jour. Les défauts connus, les améliorations prévues et le moyen de nous signaler une difficulté sont détaillés dans la <a href="/accessibility">déclaration d’accessibilité</a>.',
    ],

    'licences' => [
        'title' => 'Propriété intellectuelle et licences',
        'paragraph' => '<ul>'
            .'<li><strong>Code source</strong> : licence MIT (<a href="https://github.com/UnxWares/VFF-Project" target="_blank" rel="noopener">github.com/UnxWares/VFF-Project</a>). Le modèle qui extrait les voies des photographies aériennes anciennes, et ses poids, sont aussi sous licence MIT (<a href="https://github.com/UnxWares/VFF-Rail-Extraction" target="_blank" rel="noopener">github.com/UnxWares/VFF-Rail-Extraction</a>).</li>'
            .'<li><strong>Base de données</strong> (tracés, dates, gares, état des lignes, exports et API) : <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener">Open Database License (ODbL) 1.0</a>, sauf les données reprises de sources qui n’autorisent qu’un usage non commercial : leur provenance est indiquée avec chaque donnée (champ <code>source</code> de l’API) et elles ne sont réutilisables qu’à des fins non commerciales, en citant leur source avec un lien.</li>'
            .'<li><strong>Textes</strong> : les textes rédigés pour VFF sont sous licence <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" target="_blank" rel="noopener">CC BY-SA 4.0</a>. Les extraits de Wikipédia restent sous CC BY-SA 4.0 et sont présentés avec le lien vers l’article et sa version.</li>'
            .'<li><strong>Images et documents</strong> : chaque fichier garde sa propre licence, indiquée avec son auteur. Les images de Wikimedia Commons sont affichées par lien direct, sans copie sur nos serveurs. Les fichiers déposés par les contributeurs sont sous CC BY-SA 4.0.</li>'
            .'<li><strong>Nom et logo</strong> : le nom « Voies Ferrées de France », le sigle « VFF » et le logo désignent le projet édité par UnxWares. Vous pouvez les citer pour parler du projet, mais pas les utiliser d’une façon qui laisserait croire à un partenariat ou à une approbation de notre part.</li>'
            .'</ul>',
    ],

    'sources' => [
        'title' => 'Sources des données',
        'paragraph' => '<p>VFF croise des sources ouvertes, chacune citée avec sa licence, notamment :</p>'
            .'<ul>'
            .'<li>OpenStreetMap : © contributeurs OpenStreetMap, licence ODbL ;</li>'
            .'<li>SNCF Réseau, SNCF Voyageurs et SNCF Gares &amp; Connexions (portail de données ouvertes de la SNCF) : ODbL ;</li>'
            .'<li>IGN, Géoplateforme (BD TOPO, photographies aériennes et cartes anciennes) : Licence Ouverte 2.0 ;</li>'
            .'<li>OpenHistoricalMap et Wikidata : CC0 ;</li>'
            .'<li>Wikipédia : CC BY-SA 4.0 ; Wikimedia Commons : licence de chaque fichier.</li>'
            .'</ul>'
            .'<p>La liste complète, avec les attributions, figure sur les pages <a href="/credits/tech">Crédits techniques</a> et <a href="/credits/media">Crédits des médias</a>.</p>',
    ],

    'interests' => [
        'title' => 'Engagement',
        'paragraph' => 'Le créateur de VFF adhère à l’association AngouLim, qui milite pour la réouverture de la ligne Angoulême - Limoges, parce qu’il juge important de défendre les lignes oubliées. VFF soutient cette campagne sur la fiche de la ligne.',
    ],

    'liability' => [
        'title' => 'Responsabilité',
        'paragraph' => '<p>VFF rassemble des sources historiques qui se contredisent parfois. Malgré la relecture des contributions, des erreurs peuvent subsister : dates déduites (signalées comme telles), tracés approximatifs, états incomplets. Les informations sont données à titre documentaire et ne remplacent pas les documents officiels.</p>'
            .'<p>Elles ne doivent jamais servir à circuler ou à intervenir sur le réseau ferré. Les voies et emprises ferroviaires, même d’apparence abandonnée, sont interdites au public et dangereuses.</p>'
            .'<p>Les liens vers d’autres sites sont donnés pour information : UnxWares n’est pas responsable de leur contenu. Pour signaler une erreur, utilisez les outils de contribution ou écrivez-nous.</p>',
    ],

    'report' => [
        'title' => 'Signaler un contenu illicite',
        'paragraph' => '<p>Si un contenu publié sur VFF vous paraît illicite, signalez-le à <a href="mailto:contact@vff-project.org">contact@vff-project.org</a> en indiquant :</p>'
            .'<ul>'
            .'<li>l’adresse exacte (URL) du contenu ;</li>'
            .'<li>les raisons pour lesquelles vous le jugez illicite, avec si possible les dispositions légales concernées ;</li>'
            .'<li>vos nom et adresse électronique ;</li>'
            .'<li>une déclaration attestant que votre signalement est exact et fait de bonne foi.</li>'
            .'</ul>'
            .'<p>Nous accusons réception, examinons le signalement sans délai et retirons le contenu s’il est manifestement illicite. L’auteur d’un contenu retiré en est informé, avec les motifs. Signaler un contenu en le sachant licite, dans le but d’obtenir son retrait, est puni par la loi.</p>',
    ],

    'contact' => [
        'title' => 'Contact',
        'paragraph' => 'Pour toute question sur ces mentions : <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>. Pour la société UnxWares : <a href="mailto:contact@unxwares.com">contact@unxwares.com</a>, +33 6 70 33 02 22.',
    ],
];
