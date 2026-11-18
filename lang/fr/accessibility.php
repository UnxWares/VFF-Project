<?php

// Déclaration d'accessibilité, au format du RGAA 4.1.2. Sans audit RGAA complet, le référentiel
// impose l'état « non conforme ». Les contenus non accessibles reprennent les constats de l'audit
// interne du 2026-10-21 (storage/app/reports/agents/audit-ui.md) : à mettre à jour à chaque
// correction, et après l'audit RGAA (taux de conformité). Le français fait foi.
return [
    'meta_title' => 'Déclaration d’accessibilité',
    'meta_description' => 'État de conformité de Voies Ferrées de France au RGAA, contenus non accessibles et voies de recours.',

    'hero' => [
        'title' => 'Déclaration d’accessibilité',
        'updated' => 'Établie le :date',
    ],

    'commitment' => [
        'title' => 'Notre engagement',
        'paragraph' => 'UnxWares veut que Voies Ferrées de France soit utilisable par tous, quels que soient le handicap, l’appareil ou la façon de naviguer. VFF n’est pas soumis à l’obligation d’accessibilité de l’article 47 de la loi n° 2005-102 du 11 février 2005, mais suit volontairement le référentiel général d’amélioration de l’accessibilité (RGAA), version 4.1.2. Cette déclaration s’applique au site <a href="https://vff-project.org">vff-project.org</a>.',
    ],

    'status' => [
        'title' => 'État de conformité',
        'paragraph' => '<p><strong>Le site vff-project.org est non conforme au RGAA 4.1.2.</strong></p>'
            .'<p>Il n’a pas encore fait l’objet d’un audit de conformité RGAA. En l’absence d’un tel audit, le référentiel impose de le déclarer non conforme.</p>',
    ],

    'results' => [
        'title' => 'Résultats des tests',
        'paragraph' => 'Un audit interne a été mené le 21 octobre 2026 sur 47 pages et états du site, à six largeurs d’écran (de 375 à 1 920 pixels), en thème clair et en thème sombre, en français et en partie en anglais et en allemand. Il a relevé des défauts d’affichage et d’utilisation dont une partie touche l’accessibilité. Il ne suit pas la méthode du RGAA et ne permet pas de calculer un taux de conformité.',
    ],

    'non_accessible' => [
        'title' => 'Contenus non accessibles',
        'paragraph' => '<p>Défauts connus, en cours de correction :</p>'
            .'<ul>'
            .'<li>Contrastes : plusieurs textes orange sur fond clair, ainsi que des textes blancs sur fond orange, n’atteignent pas le rapport de contraste de 4,5:1 demandé pour le texte courant (critère 3.2).</li>'
            .'<li>Sur certaines pages, les menus de choix de la langue et du compte passent sous le contenu de la page sur ordinateur, ou sont coupés sur téléphone : leurs entrées ne sont alors pas utilisables.</li>'
            .'<li>Les cartes interactives (carte du réseau par époque, cartes des fiches, éditeur de tracé) ne sont pas pleinement utilisables au clavier et ne sont pas restituées par les lecteurs d’écran.</li>'
            .'<li>Sur petit écran, certains éléments se chevauchent, par exemple la note « Aujourd’hui » des fiches de ligne sur le titre de la section suivante.</li>'
            .'<li>Les messages d’erreur des formulaires de connexion et d’inscription s’affichent en anglais sur les pages en français, et la page d’erreur « page introuvable » n’est ni traduite ni adaptée au thème sombre.</li>'
            .'</ul>'
            .'<p>Le site n’a pas encore été testé avec un lecteur d’écran : d’autres difficultés peuvent exister. Les informations des cartes sont aussi proposées sous forme de texte : fiches des lignes et des gares, listes des <a href="/lines">lignes</a> et des <a href="/stations">gares</a>, et <a href="/api">API</a>.</p>',
    ],

    'exempt' => [
        'title' => 'Contenus non soumis à l’obligation d’accessibilité',
        'paragraph' => 'Les cartes et services de cartographie en ligne font partie des contenus non soumis à l’obligation d’accessibilité. VFF s’efforce malgré tout d’en donner l’essentiel sous forme de texte, dans les fiches des lignes et des gares. Les contenus de tiers que VFF ne produit ni ne contrôle (images de Wikimedia Commons, fonds de carte, sites liés) ne relèvent pas de cette déclaration.',
    ],

    'establishment' => [
        'title' => 'Établissement de cette déclaration',
        'paragraph' => '<p>Cette déclaration a été établie le 21 octobre 2026.</p>'
            .'<ul>'
            .'<li><strong>Technologies utilisées</strong> : HTML5, CSS, JavaScript (Svelte, MapLibre GL), SVG, WAI-ARIA.</li>'
            .'<li><strong>Environnement de test</strong> : navigateur Chromium sous Linux, de 375 à 1 920 pixels de large, en thème clair et sombre.</li>'
            .'<li><strong>Outils</strong> : mesures automatiques (contrastes, débordements, taille des zones cliquables, erreurs de la console) et relecture visuelle de chaque capture.</li>'
            .'<li><strong>Pages vérifiées</strong> : accueil, carte, listes et fiches des lignes et des gares, matériel roulant, contribuer, dons, qui sommes-nous, crédits, pages légales, API et sa documentation, connexion, inscription, mot de passe oublié, pages réservées aux membres, page d’erreur.</li>'
            .'</ul>',
    ],

    'feedback' => [
        'title' => 'Retour d’information et contact',
        'paragraph' => 'Si vous ne parvenez pas à accéder à un contenu ou à un service, écrivez à <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>, avec « Accessibilité » en objet. Nous vous orientons vers une solution accessible ou vous transmettons le contenu sous une autre forme, dans les meilleurs délais et au plus tard sous un mois.',
    ],

    'recourse' => [
        'title' => 'Voies de recours',
        'paragraph' => '<p>Si vous nous avez signalé un défaut d’accessibilité qui vous empêche d’accéder à un contenu ou à un service, et que vous n’avez pas obtenu de réponse satisfaisante, vous pouvez :</p>'
            .'<ul>'
            .'<li>écrire un message au Défenseur des droits, par le formulaire en ligne de <a href="https://www.defenseurdesdroits.fr" target="_blank" rel="noopener">defenseurdesdroits.fr</a> ;</li>'
            .'<li>contacter le délégué du Défenseur des droits dans votre région ;</li>'
            .'<li>appeler le 09 69 39 00 00 ;</li>'
            .'<li>envoyer un courrier, gratuit et sans timbre, à : Défenseur des droits, Libre réponse 71120, 75342 Paris CEDEX 07.</li>'
            .'</ul>',
    ],

    'plan' => [
        'title' => 'Améliorations prévues',
        'paragraph' => 'Nous corrigeons d’abord les défauts bloquants (menus de l’en-tête, chevauchements, formulaires), puis les contrastes. Un audit de conformité RGAA complet sera ensuite réalisé, et son taux de conformité publié sur cette page.',
    ],
];
