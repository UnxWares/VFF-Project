<?php

// Politique de confidentialité (RGPD, loi Informatique et Libertés). Décrit ce que fait réellement
// le code au 2026-10-21 : cookies (config/session.php, LocaleController, lib/stores/theme.svelte.js),
// stockage local (Pages/Contribute/Trace.svelte), services tiers chargés par le navigateur
// (Components/Map/*, Lines/LineGallery.svelte), comptes (AuthController, ProfileController).
// Toute évolution de ces points doit mettre ce texte à jour. Le français fait foi.
return [
    'meta_title' => 'Politique de confidentialité',
    'meta_description' => 'Données personnelles traitées par Voies Ferrées de France, cookies, services tiers et vos droits.',

    'hero' => [
        'eyebrow' => 'Données personnelles',
        'title' => 'Politique de confidentialité',
        'updated' => 'Dernière mise à jour : :date',
    ],

    'summary' => [
        'title' => 'En bref',
        'paragraph' => '<ul>'
            .'<li>Pas de publicité, pas de mesure d\'audience, aucun outil de suivi.</li>'
            .'<li>Aucun compte n\'est nécessaire pour consulter le site.</li>'
            .'<li>Seulement des cookies nécessaires au fonctionnement ou à vos réglages : rien à accepter.</li>'
            .'<li>Vos données ne sont ni vendues, ni louées, ni cédées.</li>'
            .'<li>Vos données sont hébergées dans l\'Union européenne.</li>'
            .'</ul>',
    ],

    'controller' => [
        'title' => 'Responsable du traitement',
        'paragraph' => 'UnxWares, société par actions simplifiée immatriculée au RCS de Paris sous le numéro 945 207 991, 78 avenue des Champs-Élysées, bureau 326, 75008 Paris, représentée par son président, Baptiste Gosselin. Pour toute question sur vos données : <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>. UnxWares n\'est pas tenue de désigner un délégué à la protection des données et n\'en a pas désigné.',
    ],

    'visitors' => [
        'title' => 'Si vous consultez le site sans compte',
        'paragraph' => '<p>Pour vous envoyer les pages, le serveur reçoit nécessairement votre adresse IP, la page demandée, la date et l\'identification de votre navigateur. Ces informations sont enregistrées dans des journaux techniques, utilisés uniquement pour la sécurité du service, le diagnostic des erreurs et la lutte contre les abus, et conservés 12 mois au plus.</p>'
            .'<p>Pour protéger l\'API, le nombre de requêtes de chaque adresse IP est compté pendant une minute (300 requêtes au plus), puis oublié.</p>'
            .'<p>Base légale : l\'intérêt légitime d\'UnxWares à assurer la sécurité et le bon fonctionnement du site.</p>',
    ],

    'cookies' => [
        'title' => 'Cookies et stockage dans votre navigateur',
        'paragraph' => '<div class="table-wrap"><table>'
            .'<thead><tr><th scope="col">Nom</th><th scope="col">À quoi il sert</th><th scope="col">Durée</th></tr></thead>'
            .'<tbody>'
            .'<tr><td><code>vff_session</code></td><td>Maintient votre session : connexion, envoi des formulaires.</td><td>2 heures d\'inactivité</td></tr>'
            .'<tr><td><code>XSRF-TOKEN</code></td><td>Protège les formulaires contre les envois frauduleux.</td><td>2 heures</td></tr>'
            .'<tr><td><code>remember_web_…</code></td><td>Vous garde connecté si vous cochez «&nbsp;Se souvenir de moi&nbsp;».</td><td>Environ 400 jours, ou jusqu\'à la déconnexion</td></tr>'
            .'<tr><td><code>locale</code></td><td>Retient la langue que vous avez choisie.</td><td>1 an</td></tr>'
            .'<tr><td><code>theme</code></td><td>Retient le thème clair ou sombre que vous avez choisi.</td><td>1 an</td></tr>'
            .'<tr><td><code>vff:trace-draft:v1</code> (stockage local)</td><td>Garde le brouillon de l\'éditeur de tracé si vous quittez la page avant de l\'envoyer. Il reste dans votre navigateur tant que vous ne l\'envoyez pas.</td><td>Jusqu\'à l\'envoi ou l\'abandon du brouillon</td></tr>'
            .'</tbody></table></div>'
            .'<p>Ces cookies sont strictement nécessaires au service ou retiennent un réglage que vous avez choisi : ils sont dispensés de consentement (article 82 de la loi Informatique et Libertés). C\'est pourquoi le site n\'affiche pas de bandeau. VFF ne dépose aucun cookie de mesure d\'audience ni de publicité. Vous pouvez les supprimer à tout moment depuis votre navigateur.</p>',
    ],

    'third_party' => [
        'title' => 'Services tiers chargés par votre navigateur',
        'paragraph' => '<p>Les fonds de carte et certaines images sont chargés directement depuis d\'autres serveurs. Votre navigateur leur transmet alors votre adresse IP et ses informations techniques, comme pour toute image venant d\'un autre site. Seule l\'adresse du site (vff-project.org) leur est indiquée, pas la page consultée. VFF ne leur transmet aucune autre donnée.</p>'
            .'<ul>'
            .'<li><strong>Fond de carte</strong> : OpenStreetMap Foundation (Royaume-Uni), pour les tuiles de la carte ; OpenFreeMap, pour les polices de caractères de la carte ; MapTiler (Suisse), lorsque ce fond est activé.</li>'
            .'<li><strong>Calques facultatifs</strong>, chargés seulement si vous les activez : OpenRailwayMap sur la carte ; dans l\'éditeur de tracé, les cartes et photographies anciennes de l\'IGN (Géoplateforme, France).</li>'
            .'<li><strong>Images d\'archives</strong> : Wikimedia Foundation (États-Unis), pour les images de Wikimedia Commons affichées dans les galeries.</li>'
            .'</ul>'
            .'<p>Ces services appliquent leur propre politique de confidentialité. Les polices de caractères du site sont hébergées par VFF : aucune requête n\'est faite vers Google Fonts.</p>',
    ],

    'account' => [
        'title' => 'Si vous créez un compte',
        'paragraph' => '<ul>'
            .'<li><strong>Données</strong> : nom d\'affichage, adresse électronique, mot de passe (enregistré uniquement sous forme hachée, jamais en clair), rôle (visiteur, contributeur, validateur ou administrateur), langue préférée et, si vous les renseignez, une courte présentation et un avatar.</li>'
            .'<li><strong>Finalités</strong> : vous identifier, gérer vos contributions et leur validation, vous envoyer un lien pour réinitialiser votre mot de passe.</li>'
            .'<li><strong>Base légale</strong> : l\'exécution des <a href="/use-conditions">conditions générales d\'utilisation</a>, que vous acceptez en créant le compte.</li>'
            .'<li><strong>Conservation</strong> : tant que le compte existe. Un compte inutilisé pendant 3 ans est supprimé, après un avertissement envoyé par courriel un mois avant. Un lien de réinitialisation du mot de passe expire au bout de 60 minutes.</li>'
            .'</ul>',
    ],

    'contributions' => [
        'title' => 'Contributions',
        'paragraph' => '<p>Chaque contribution (tracé, événement daté, correction, document) est enregistrée avec votre identifiant et sa date, puis, une fois examinée, avec l\'identifiant de la personne qui l\'a validée ou refusée et ses remarques. Les validateurs voient le nom d\'affichage de l\'auteur. Une contribution validée entre dans l\'encyclopédie sous la licence prévue par les conditions générales d\'utilisation.</p>'
            .'<p>Si vous supprimez votre compte, vos contributions validées restent publiées, car elles font partie de l\'encyclopédie et de sa licence, mais elles ne sont plus rattachées à votre nom. Vos contributions en attente sont supprimées.</p>',
    ],

    'emails' => [
        'title' => 'Courriels',
        'paragraph' => 'VFF n\'envoie que des courriels de service, comme le lien de réinitialisation du mot de passe. Aucune lettre d\'information, aucune prospection.',
    ],

    'recipients' => [
        'title' => 'Qui accède à vos données',
        'paragraph' => '<p>Seules les personnes qui administrent VFF pour UnxWares, et uniquement pour ce qui leur est nécessaire. Les validateurs voient le nom d\'affichage de l\'auteur des contributions qu\'ils examinent.</p>'
            .'<p>Sous-traitants : UnxWares héberge le site sur des serveurs loués à Hetzner Online GmbH (Allemagne, centres de données dans l\'Union européenne), avec Netaris (France) pour le réseau et Infomaniak Network SA (Suisse) pour le nom de domaine et les DNS. Les courriels de service partent de la messagerie d\'UnxWares, hébergée par Hetzner Online GmbH (Allemagne).</p>',
    ],

    'transfers' => [
        'title' => 'Transferts hors de l\'Union européenne',
        'paragraph' => 'Vos données sont hébergées dans l\'Union européenne. Infomaniak, qui gère les DNS, est établie en Suisse, pays reconnu par la Commission européenne comme offrant une protection adéquate. Les services tiers listés plus haut reçoivent directement de votre navigateur votre adresse IP, notamment OpenStreetMap (Royaume-Uni) et la Wikimedia Foundation (États-Unis).',
    ],

    'security' => [
        'title' => 'Sécurité',
        'paragraph' => 'Connexion chiffrée (HTTPS), mots de passe hachés, cookies de session inaccessibles aux scripts, protection des formulaires contre les envois frauduleux, accès d\'administration limités. En cas de violation de données présentant un risque pour vous, nous prévenons la CNIL et, si nécessaire, les personnes concernées.',
    ],

    'rights' => [
        'title' => 'Vos droits',
        'paragraph' => '<p>Vous pouvez demander l\'accès à vos données, leur rectification, leur effacement, leur portabilité ou la limitation de leur traitement, et vous opposer à un traitement fondé sur l\'intérêt légitime. Vous pouvez aussi définir des directives sur le sort de vos données après votre décès.</p>'
            .'<p>Écrivez à <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>, si possible depuis l\'adresse de votre compte. Nous répondons dans un délai d\'un mois. Votre nom d\'affichage, votre présentation et votre langue se modifient aussi depuis votre profil.</p>'
            .'<p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir la CNIL : 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07, <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</p>',
    ],

    'minors' => [
        'title' => 'Mineurs',
        'paragraph' => 'Le site se consulte sans compte. Pour créer un compte avant 15 ans, il faut l\'accord d\'un titulaire de l\'autorité parentale.',
    ],

    'changes' => [
        'title' => 'Évolution de cette politique',
        'paragraph' => 'Cette politique suit l\'évolution du site. Sa date de mise à jour figure en haut de la page. En cas de changement important, les titulaires d\'un compte sont prévenus.',
    ],

    'contact' => [
        'title' => 'Contact',
        'paragraph' => 'Pour toute question sur vos données : <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.',
    ],
];
