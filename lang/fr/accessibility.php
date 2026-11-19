<?php

// Déclaration d'accessibilité, au format du modèle officiel du RGAA 4.1.2 (article 47 de la loi n° 2005-102).
// Elle repose sur l'audit interne du 2026-11-19 (grille WCAG 2.1 A et AA, socle du RGAA ; rapport :
// storage/app/reports/agents/ui-controle.md, section 6). Ce n'est ni un audit de la grille RGAA complète
// ni un audit par un tiers : aucun taux de conformité n'est publié. Pour s'en tenir à la lettre du
// référentiel, remplacer « partiellement conforme » par « non conforme » dans la section status.
// À mettre à jour à chaque correction ; la date est dans resources/js/Pages/Accessibility.svelte
// (prop updated) et dans la section establishment. Le français fait foi.
return [
    'meta_title' => 'Déclaration d’accessibilité',
    'meta_description' => 'État de conformité de Voies Ferrées de France au RGAA, résultats de l’audit interne, contenus non accessibles et voies de recours.',

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
            .'<p>Cet état résulte d’un audit interne, mené le 19 novembre 2026 sur les critères de niveau A et AA des WCAG 2.1, qui forment le socle du RGAA. L’audit n’a pas porté sur la grille complète du RGAA (106 critères) et n’a pas été réalisé par un organisme tiers. Nous ne publions donc pas de taux de conformité, et l’état déclaré reste à confirmer par un audit de ce type.</p>',
    ],

    'results' => [
        'title' => 'Résultats des tests',
        'paragraph' => '<p>L’audit interne du 19 novembre 2026 a porté sur l’échantillon de pages donné plus bas, soit plus de 250 vues (pages, largeurs d’écran, thèmes clair et sombre, langues). Sur les 50 critères des WCAG 2.1 de niveau A et AA : 42 sont respectés, 1 ne l’est pas (la langue des passages, voir plus bas) et 7 ne s’appliquent pas, faute de vidéo, de son ou de commande par mouvement sur le site.</p>'
            .'<p>Lighthouse donne 100 sur 100 en accessibilité sur les pages mesurées, sur ordinateur et sur téléphone. Ce score ne prouve pas la conformité : il ne couvre qu’une partie des critères. Nous avons donc aussi parcouru le site entièrement au clavier, vérifié l’affichage avec un zoom de 200 % et sur 320 pixels de large, avec l’espacement du texte augmenté et avec la réduction des animations activée.</p>'
            .'<p>Le site n’a pas été testé avec un lecteur d’écran : des difficultés que ces tests ne montrent pas peuvent subsister.</p>',
    ],

    'non_accessible' => [
        'title' => 'Contenus non accessibles',
        'paragraph' => '<p>Non-conformité constatée :</p>'
            .'<ul>'
            .'<li>Langue des passages (critère 8.7 du RGAA, 3.1.2 des WCAG) : les légendes et les auteurs des images de Wikimedia Commons s’affichent dans leur langue d’origine (anglais, néerlandais…) sans que cette langue soit indiquée. Les lecteurs d’écran peuvent les prononcer avec un mauvais accent.</li>'
            .'</ul>'
            .'<p>Contenus qui n’ont pas encore été audités :</p>'
            .'<ul>'
            .'<li>les pages réservées aux membres (éditeur de tracé, validation, profil) ;</li>'
            .'<li>les outils de documentation de l’API (Swagger UI, GraphiQL, Magidoc), hébergés par VFF et soumis à cette déclaration, dont certains libellés restent en anglais.</li>'
            .'</ul>'
            .'<p>Nous n’invoquons aucune dérogation pour charge disproportionnée.</p>',
    ],

    'exempt' => [
        'title' => 'Contenus non soumis à l’obligation d’accessibilité',
        'paragraph' => 'Les cartes et services de cartographie en ligne font partie des contenus non soumis à l’obligation d’accessibilité : carte du réseau, mini-cartes des fiches, carte intégrable, fond OpenStreetMap et calques de tiers. Leur utilisation avec un lecteur d’écran n’est pas garantie. Les informations qu’elles montrent sont proposées sous forme de texte : fiches des lignes et des gares, annuaires des <a href="/lines">lignes</a> et des <a href="/stations">gares</a>, tableaux par décennie, et <a href="/api">API</a>. Les contenus de tiers que VFF ne produit ni ne contrôle (images de Wikimedia Commons, sites liés) ne relèvent pas de cette déclaration.',
    ],

    'establishment' => [
        'title' => 'Établissement de cette déclaration',
        'paragraph' => '<p>Cette déclaration a été établie le 19 novembre 2026.</p>'
            .'<ul>'
            .'<li><strong>Technologies utilisées</strong> : HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Environnement de test</strong> : navigateur Chromium sous Linux, de 320 à 1 920 pixels de large, en thème clair et en thème sombre, en français et en partie en anglais et en allemand.</li>'
            .'<li><strong>Outils</strong> : Lighthouse 13 (dont les contrôles reposent sur axe-core), outils de développement de Chrome (arbre d’accessibilité, émulations), mesures automatiques du projet (contrastes calculés, débordements, taille des zones cliquables), parcours au clavier, zoom à 200 %, espacement du texte, réduction des animations.</li>'
            .'<li><strong>Pages vérifiées</strong> : accueil, carte, fiches de lignes (610000 et 752000) et de gares (Angoulême, Limoges-Bénédictins), annuaires des lignes, des gares et du matériel roulant, Notre histoire, dons, contribuer, crédits, pages légales, API, connexion, inscription, mot de passe oublié, page d’erreur « page introuvable », carte intégrable.</li>'
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
        'paragraph' => 'Nous prévoyons d’enregistrer la langue de chaque légende lors de l’import des images de Wikimedia Commons, puis de l’indiquer sur le passage concerné, ce qui lèvera la non-conformité du critère 8.7. Nous prévoyons aussi d’auditer les pages réservées aux membres et les outils de documentation de l’API, de tester le site avec un lecteur d’écran, puis de faire réaliser un audit complet de la grille du RGAA. Son taux de conformité sera publié sur cette page.',
    ],
];
