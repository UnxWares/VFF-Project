<?php

// Déclaration d'accessibilité, au format du modèle officiel du RGAA 4.1.2 (article 47 de la loi n° 2005-102).
// Elle repose sur l'audit interne RGAA 4.1.2 du 2026-11-19 (106 critères, 28 pages) : rapport
// storage/app/reports/agents/nuit/audit-rgaa.md, grille storage/app/reports/agents/nuit/audit-rgaa-grille.csv,
// calcul refait par storage/app/data/rgaa/grille.py. Seuils officiels : conforme à 100 %, partiellement
// conforme à partir de 50 % de critères respectés, non conforme en dessous ou sans audit en cours de validité.
// À mettre à jour à chaque correction ; la date est dans resources/js/Pages/Accessibility.svelte
// (prop updated) et dans la section establishment. Le français fait foi.
return [
    'meta_title' => 'Déclaration d’accessibilité',
    'meta_description' => 'État de conformité de Voies Ferrées de France au RGAA, résultats de l’audit interne RGAA, contenus non accessibles et voies de recours.',

    'hero' => [
        'title' => 'Déclaration d’accessibilité',
        'updated' => 'Établie le :date',
    ],

    'commitment' => [
        'title' => 'Notre engagement',
        'paragraph' => 'Voies Ferrées de France (VFF) est éditée par UnxWares, société par actions simplifiée dont les coordonnées figurent dans les <a href="/legal">mentions légales</a>. UnxWares veut que VFF soit utilisable par tous, quels que soient le handicap, l’appareil ou la façon de naviguer. Le site n’est pas soumis à l’obligation d’accessibilité de l’article 47 de la loi n° 2005-102 du 11 février 2005, mais suit volontairement le référentiel général d’amélioration de l’accessibilité (RGAA), version 4.1.2. Cette déclaration s’applique au site <a href="https://vff-project.org">vff-project.org</a>.',
    ],

    'status' => [
        'title' => 'État de conformité',
        'paragraph' => '<p><strong>Le site vff-project.org est partiellement conforme au RGAA 4.1.2.</strong></p>'
            .'<p>Cet état repose sur l’audit interne réalisé par UnxWares le 19 novembre 2026, selon la méthode du RGAA 4.1.2 : les 106 critères ont été vérifiés sur un échantillon de 28 pages. L’audit n’a pas été réalisé par un organisme tiers.</p>',
    ],

    'results' => [
        'title' => 'Résultats des tests',
        'paragraph' => '<p>L’audit de conformité réalisé en interne révèle que :</p>'
            .'<ul>'
            .'<li>82 % des critères du RGAA sont respectés (59 critères sur les 72 qui s’appliquent au site) ;</li>'
            .'<li>le taux moyen de conformité du service en ligne s’élève à 99 % (98,87 %, moyenne des taux des 28 pages).</li>'
            .'</ul>'
            .'<p>Vingt-quatre pages sur 28 respectent tous les critères qui les concernent. Onze des treize critères non respectés le sont seulement dans les deux outils de documentation de l’API, Swagger UI et Magidoc. La grille complète (statut et justification de chaque critère sur chaque page) se télécharge : <a href="/rgaa/grille-audit-rgaa-2026-11-19.csv" download>grille d’audit RGAA (fichier CSV, 744 Ko)</a>.</p>'
            .'<p>Le site n’a pas été testé avec un lecteur d’écran. Le nom, le rôle et l’état des composants ont été vérifiés dans le code et dans l’arbre d’accessibilité du navigateur, mais leur restitution réelle par NVDA, JAWS, VoiceOver ou TalkBack reste à contrôler.</p>',
    ],

    'non_accessible' => [
        'title' => 'Contenus non accessibles',
        'paragraph' => '<p>Non-conformités :</p>'
            .'<ul>'
            .'<li>Fiches des lignes, liste des gares : la petite bande colorée sous chaque gare (état de la gare à chaque décennie) distingue « ouverte », « état non confirmé » et « fermée » par la couleur seule (critère 3.1). Les dates d’ouverture et de fermeture sont écrites à côté, et l’état complet est lu par les lecteurs d’écran. Depuis l’audit, la bande est aussi accompagnée d’une légende visible et chaque état a sa forme (case pleine, hachurée, basse ou vide) ; ce point sera revérifié lors du prochain audit.</li>'
            .'<li>Fiches du matériel roulant : la flèche « → » des renumérotations (« PLM 1-30 → Est 601-612 ») n’a pas d’équivalent textuel (critère 13.5).</li>'
            .'<li>Référence de l’API REST dans Swagger UI (/docs/swagger) : interface en anglais dans une page déclarée en français (8.7), lien placé dans un bouton (8.2), deux zones principales (9.2), champ « Filter by tag » sans étiquette (11.1). Les mêmes points d’accès sont décrits en français sur la page <a href="/api">Accès à l’API</a>.</li>'
            .'<li>Référence de l’API GraphQL dans Magidoc (/docs/graphql) : interface en anglais (8.7), titre de page qui ne nomme pas le site (8.6), logo de VFF remplacé par « Magidoc » (1.3), ancres « # » à côté des titres (6.1 et 9.1), bouton sans nom (7.1), bouton placé dans un lien (8.2), défilement horizontal à 320 pixels de large (10.11), champ de recherche sans étiquette (11.1), pas de lien d’évitement (12.7). La page <a href="/api">Accès à l’API</a> présente l’API GraphQL en français.</li>'
            .'<li>Hors de l’échantillon audité, les légendes et les auteurs de certaines images de Wikimedia Commons s’affichent dans leur langue d’origine (anglais, néerlandais…) sans que cette langue soit indiquée (critère 8.7).</li>'
            .'</ul>'
            .'<p>Nous n’invoquons aucune dérogation pour charge disproportionnée.</p>',
    ],

    'exempt' => [
        'title' => 'Contenus non soumis à l’obligation d’accessibilité',
        'paragraph' => 'Les cartes et services de cartographie en ligne font partie des contenus non soumis à l’obligation d’accessibilité : carte du réseau, mini-cartes des fiches, carte intégrable, fond OpenStreetMap et calques de tiers, avec leurs boutons de zoom. Leur utilisation avec un lecteur d’écran n’est pas garantie. Les informations qu’elles montrent sont proposées sous forme de texte : fiches des lignes et des gares, annuaires des <a href="/lines">lignes</a> et des <a href="/stations">gares</a>, tableaux par décennie, et <a href="/api">API</a>. Les contenus de tiers que VFF ne produit ni ne contrôle (images de Wikimedia Commons, sites liés) ne relèvent pas de cette déclaration.',
    ],

    'establishment' => [
        'title' => 'Établissement de cette déclaration',
        'paragraph' => '<p>Cette déclaration a été établie le 19 novembre 2026, après l’audit interne RGAA.</p>'
            .'<ul>'
            .'<li><strong>Technologies utilisées</strong> : HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Environnement de test</strong> : navigateur Chromium sous Linux, de 320 à 1 280 pixels de large, en thème clair (le thème sombre a été mesuré le 19 novembre), en français. Aucune technologie d’assistance n’a été utilisée.</li>'
            .'<li><strong>Outils</strong> : inspection du code généré et de l’arbre d’accessibilité (scripts du projet), calcul des contrastes, parcours au clavier de 45 tabulations par page, captures sans feuille de style, à 320 pixels de large et avec l’espacement du texte augmenté, lecture du code des composants.</li>'
            .'<li><strong>Pages vérifiées</strong> : accueil ; mentions légales, qui donnent aussi le contact (le site n’a pas de page de contact) ; déclaration d’accessibilité ; contribuer, qui sert de page d’aide ; connexion ; inscription ; mot de passe oublié ; carte ; annuaires des lignes, des gares, du matériel roulant et des accidents ; une fiche de ligne (610000), de gare (Angoulême), de matériel (PLM 1-30) et d’accident (Meudon, 1842) ; accès à l’API ; références REST (Swagger UI) et GraphQL (Magidoc) ; page d’erreur « page introuvable » ; fenêtre de recherche ; profil ; éditeur de tracé ; mes contributions ; validation des contributions ; et, tirées au sort, la politique de confidentialité, les conditions d’utilisation et les crédits techniques. Le site n’a pas de plan du site. La référence REST dans Scalar (/docs/api) n’a pas fait partie de l’échantillon.</li>'
            .'</ul>',
    ],

    'feedback' => [
        'title' => 'Retour d’information et contact',
        'paragraph' => 'Si vous ne parvenez pas à accéder à un contenu ou à un service, écrivez à <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>, avec « Accessibilité » en objet. Nous vous orientons vers une solution accessible ou vous transmettons le contenu sous une autre forme, dans les meilleurs délais et au plus tard sous un mois.',
    ],

    'recourse' => [
        'title' => 'Voies de recours',
        'paragraph' => '<p>Si vous nous avez signalé un défaut d’accessibilité qui vous empêche d’accéder à un contenu ou à un service, et que vous n’avez pas obtenu de réponse satisfaisante, vous pouvez :</p>'
            .'<ul>'
            .'<li>écrire un message au Défenseur des droits, par le formulaire en ligne de <a href="https://www.defenseurdesdroits.fr" target="_blank" rel="noopener">defenseurdesdroits.fr</a> ;</li>'
            .'<li>contacter le délégué du Défenseur des droits dans votre région ;</li>'
            .'<li>appeler le 09 69 39 00 00 ;</li>'
            .'<li>envoyer un courrier, gratuit et sans timbre, à : Défenseur des droits, Libre réponse 71120, 75342 Paris CEDEX 07.</li>'
            .'</ul>',
    ],

    'plan' => [
        'title' => 'Améliorations prévues',
        'paragraph' => 'Nous prévoyons de revérifier la bande des décennies dans la liste des gares, d’écrire en toutes lettres les renumérotations du matériel roulant, de traduire en français les interfaces de Swagger UI et de Magidoc et d’en corriger le code, puis d’enregistrer la langue de chaque légende lors de l’import des images de Wikimedia Commons. Nous prévoyons aussi de tester le site avec un lecteur d’écran et d’auditer la référence REST dans Scalar. Le taux de conformité de cette page sera mis à jour après chaque correction.',
    ],
];
