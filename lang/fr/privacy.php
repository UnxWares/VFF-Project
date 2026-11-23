<?php

// Politique de confidentialité (RGPD, loi Informatique et Libertés). Décrit ce que fait réellement
// le code au 2026-11-19 : cookies (config/session.php, LocaleController, lib/stores/theme.svelte.js),
// stockage local (Pages/Contribute/Trace.svelte), services tiers chargés par le navigateur
// (Components/Map/*, Lines/LineGallery.svelte), géolocalisation (Map/RailMap.svelte), comptes
// (AuthController, ProfileController), notifications (DatabaseNotification), signalements
// (ErrorReport, limiteur error-reports d'AppServiceProvider), images proposées (MediaContributions,
// ImageSanitizer). Version : config/legal.php.
// « Autour de moi » (lib/nearby.js, WhatWasHereController, PositionlessError, location nginx
// /api/v1/what-was-here sans journal d'erreurs, docs/DEPLOIEMENT-NGINX.md) : texte vrai tant que ces règles tiennent.
// Toute évolution de ces points doit mettre ce texte à jour. Le français fait foi.
return [
    'meta_title' => 'Politique de confidentialité',
    'meta_description' => 'Données personnelles traitées par Voies Ferrées de France, cookies, services tiers et vos droits.',

    'hero' => [
        'title' => 'Politique de confidentialité',
        'updated' => 'Dernière mise à jour : :date',
    ],

    'summary' => [
        'title' => 'En bref',
        'paragraph' => '<ul>'
            .'<li>Pas de publicité, pas de mesure d’audience, aucun outil de suivi.</li>'
            .'<li>Aucun compte n’est nécessaire pour consulter le site.</li>'
            .'<li>Seulement des cookies nécessaires au fonctionnement ou à vos réglages : rien à accepter.</li>'
            .'<li>Vos données ne sont ni vendues, ni louées, ni cédées.</li>'
            .'<li>Vos données sont hébergées dans l’Union européenne.</li>'
            .'</ul>',
    ],

    'controller' => [
        'title' => 'Responsable du traitement',
        'paragraph' => 'UnxWares, société par actions simplifiée immatriculée au RCS de Paris sous le numéro 945 207 991, 78 avenue des Champs-Élysées, bureau 326, 75008 Paris, représentée par son président, Baptiste Gosselin. Pour toute question sur vos données : <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>. UnxWares n’est pas tenue de désigner un délégué à la protection des données et n’en a pas désigné.',
    ],

    'visitors' => [
        'title' => 'Si vous consultez le site sans compte',
        'paragraph' => '<p>Pour vous envoyer les pages, le serveur reçoit nécessairement votre adresse IP, la page demandée, la date et l’identification de votre navigateur. Ces informations sont enregistrées dans des journaux techniques, utilisés uniquement pour la sécurité du service, le diagnostic des erreurs et la lutte contre les abus, et conservés 12 mois au plus.</p>'
            .'<p>La page « Autour de moi » demande votre position à votre navigateur, avec votre autorisation, seulement quand vous cliquez sur « Utiliser ma position ». Votre navigateur l’arrondit aussitôt au millième de degré, soit une case d’environ 110 mètres du nord au sud et de 70 à 110 mètres d’est en ouest selon la latitude, puis n’envoie à VFF que cette position arrondie, avec le rayon choisi, pour trouver les lignes et les gares proches. Comme toute requête, elle arrive avec votre adresse IP. VFF ne garde cette position ni dans un cache, ni dans ses journaux techniques, ni dans ses rapports d’erreur, qui ne retiennent de cette requête, au plus, que son adresse sans ses paramètres. Si vous ouvrez ensuite la carte depuis cette page, son adresse contient cette position arrondie, comme celle de toute vue de la carte, et, comme avec le bouton « Me localiser », les tuiles de la zone affichée sont alors demandées.</p>'
            .'<p>Pour protéger l’API, le nombre de requêtes de chaque adresse IP est compté pendant une minute (300 requêtes au plus), puis oublié.</p>'
            .'<p>Votre navigateur peut aussi signaler au site, sans action de votre part, une ressource qu’une page a chargée en dehors des règles de sécurité du site (rapport de « politique de sécurité du contenu »). Le site n’en garde que l’adresse de la page sans ses paramètres, la règle en cause et l’origine de la ressource, sans votre adresse IP, votre navigateur ni votre compte, pendant 30 jours.</p>'
            .'<p>Si une page plante, le serveur ou votre navigateur peut envoyer un rapport d’erreur à un outil de suivi hébergé par UnxWares. Il contient le type de l’erreur, son message, la ligne de code en cause, l’adresse de la page sans ses paramètres et la version du site. Il ne contient ni votre adresse IP, ni votre navigateur, ni vos cookies, ni votre compte, ni le contenu de votre requête. Les rapports sont gardés 30 jours au plus. Pour surveiller que le site répond, un service vérifie aussi régulièrement une page d’état qui ne contient aucune donnée de visiteur.</p>'
            .'<p>Base légale : l’intérêt légitime d’UnxWares à assurer la sécurité et le bon fonctionnement du site.</p>',
    ],

    'cookies' => [
        'title' => 'Cookies et stockage dans votre navigateur',
        'paragraph' => '<div class="table-wrap"><table>'
            .'<thead><tr><th scope="col">Nom</th><th scope="col">À quoi il sert</th><th scope="col">Durée</th></tr></thead>'
            .'<tbody>'
            .'<tr><td><code>vff_session</code></td><td>Maintient votre session : connexion, envoi des formulaires.</td><td>2 heures d’inactivité</td></tr>'
            .'<tr><td><code>XSRF-TOKEN</code></td><td>Protège les formulaires contre les envois frauduleux.</td><td>2 heures</td></tr>'
            .'<tr><td><code>remember_web_…</code></td><td>Vous garde connecté si vous cochez « Se souvenir de moi ».</td><td>Environ 400 jours, ou jusqu’à la déconnexion</td></tr>'
            .'<tr><td><code>locale</code></td><td>Retient la langue que vous avez choisie.</td><td>1 an</td></tr>'
            .'<tr><td><code>theme</code></td><td>Retient le thème clair ou sombre que vous avez choisi.</td><td>1 an</td></tr>'
            .'<tr><td><code>vff:trace-draft:v1</code> (stockage local)</td><td>Garde le brouillon de l’éditeur de tracé si vous quittez la page avant de l’envoyer. Il reste dans votre navigateur tant que vous ne l’envoyez pas.</td><td>Jusqu’à l’envoi ou l’abandon du brouillon</td></tr>'
            .'<tr><td><code>vff-static</code>, <code>vff-pages</code>, <code>vff-tiles</code>, <code>vff-mapdata</code>, <code>vff-linedata</code>, <code>vff-images</code>, <code>vff-basemap</code>, <code>vff-offline</code> (cache de l’application)</td><td>Garde dans votre navigateur les fichiers du site, les pages publiques que vous avez consultées sans être connecté (60 au plus), les tuiles de la carte de VFF déjà affichées (500 au plus), les fichiers des gares et des accidents de la carte déjà affichés (8 au plus), les tracés des cartes des fiches de ligne déjà affichées (60 au plus), les images des fiches déjà affichées (vignettes de Wikimedia Commons et médias publiés sur VFF : 300 au plus, 40 Mo en tout) et les morceaux du fond de carte libre de VFF déjà affichés (2 000 au plus, 60 Mo en tout), pour que vous puissiez les relire sans connexion. Les pages de compte, de contribution et de validation n’y sont jamais gardées, ni leurs images. Le fond de plan d’un autre service (OpenStreetMap, par exemple) n’y est jamais gardé. Rien n’est envoyé à VFF.</td><td>30 jours pour une page, un tracé de fiche, une image ou un morceau du fond de carte, 7 jours pour une tuile ou un fichier de la carte ; les pages, les tracés des fiches, les tuiles et les fichiers de la carte sont effacés à chaque nouvelle version du site</td></tr>'
            .'</tbody></table></div>'
            .'<p>Ces cookies sont strictement nécessaires au service ou retiennent un réglage que vous avez choisi : ils sont dispensés de consentement (article 82 de la loi Informatique et Libertés). C’est pourquoi le site n’affiche pas de bandeau. VFF ne dépose aucun cookie de mesure d’audience ni de publicité. Vous pouvez les supprimer à tout moment depuis votre navigateur.</p>',
    ],

    'third_party' => [
        'title' => 'Services tiers chargés par votre navigateur',
        'paragraph' => '<p>Les fonds de carte et certaines images sont chargés directement depuis d’autres serveurs. Votre navigateur leur transmet alors votre adresse IP et ses informations techniques, comme pour toute image venant d’un autre site. Seule l’adresse du site (vff-project.org) leur est indiquée, pas la page consultée. VFF ne leur transmet aucune autre donnée.</p>'
            .'<ul>'
            .'<li><strong>Fond de carte</strong> : OpenStreetMap Foundation (Royaume-Uni), pour les tuiles de la carte ; OpenFreeMap, pour les polices de caractères de la carte ; MapTiler (Suisse), lorsque ce fond est activé.</li>'
            .'<li><strong>Calques facultatifs</strong>, chargés seulement si vous les activez : OpenRailwayMap sur la carte ; dans l’éditeur de tracé, les cartes et photographies anciennes de l’IGN (Géoplateforme, France).</li>'
            .'<li><strong>Images d’archives</strong> : Wikimedia Foundation (États-Unis), pour les images de Wikimedia Commons affichées dans les galeries, dans le formulaire « Proposer une image » et dans l’espace de validation.</li>'
            .'</ul>'
            .'<p>Le bouton « Me localiser » de la carte demande votre position à votre navigateur, avec votre autorisation, une seule fois et sans suivi. Elle sert uniquement à centrer la carte et ne quitte pas votre navigateur : VFF ne la reçoit pas et ne l’enregistre pas. Comme à chaque déplacement de la carte, les tuiles de la zone affichée sont ensuite demandées aux serveurs de VFF et au fond de carte (OpenStreetMap, ou MapTiler s’il est activé), qui peuvent en déduire approximativement où vous vous trouvez.</p>'
            .'<p>Ces services appliquent leur propre politique de confidentialité. Les polices de caractères du site sont hébergées par VFF : aucune requête n’est faite vers Google Fonts.</p>',
    ],

    'account' => [
        'title' => 'Si vous créez un compte',
        'paragraph' => '<ul>'
            .'<li><strong>Données</strong> : nom d’affichage, adresse électronique, mot de passe (enregistré uniquement sous forme hachée, jamais en clair), rôle (visiteur, contributeur, validateur ou administrateur), langue préférée, date de votre dernière visite connectée, date et version des conditions générales d’utilisation acceptées à l’inscription et, si vous les renseignez, une courte présentation et un avatar.</li>'
            .'<li><strong>Finalités</strong> : vous identifier, gérer vos contributions et leur validation, vous envoyer un lien pour réinitialiser votre mot de passe.</li>'
            .'<li><strong>Base légale</strong> : l’exécution des <a href="/use-conditions">conditions générales d’utilisation</a>, que vous acceptez en cochant la case prévue à l’inscription.</li>'
            .'<li><strong>Conservation</strong> : tant que le compte existe. Un compte inutilisé pendant 3 ans est supprimé, après un avertissement envoyé par courriel un mois avant. Un lien de réinitialisation du mot de passe expire au bout de 60 minutes.</li>'
            .'</ul>'
            .'<p>Si vous créez un jeton d’accès à l’API depuis votre profil, son nom, sa date de création et la date de sa dernière utilisation (mise à jour au plus une fois par minute) sont enregistrés avec votre compte. Le jeton lui-même n’est enregistré que sous forme hachée, jamais en clair, et ne vous est montré qu’une fois. Avec un jeton, le nombre de vos requêtes est compté par compte pendant une minute, puis oublié. Le nom et les dates de vos jetons figurent dans l’export de vos données, sans le jeton. Un jeton révoqué est supprimé, et vos jetons disparaissent avec votre compte. Un jeton est valable un an : sa date d’échéance est enregistrée avec lui, et un courriel vous prévient 30 jours avant, avec la date du rappel pour ne l’envoyer qu’une fois. La création, le renouvellement et la révocation d’un jeton sont consignés dans le journal d’audit avec le numéro du jeton, sans son nom ; cette entrée est effacée 12 mois plus tard.</p>'
            .'<p>Si vous ajoutez un webhook depuis votre profil, son adresse, les événements choisis, sa date de création, sa date de désactivation éventuelle et le nombre de livraisons échouées d’affilée sont enregistrés avec votre compte, ainsi qu’un journal de ses 50 dernières livraisons (événement, date, résultat, code HTTP reçu). Son secret de signature est enregistré chiffré et ne vous est montré qu’une fois. Les messages envoyés à votre adresse ne contiennent aucune donnée personnelle. L’adresse, les événements et le journal figurent dans l’export de vos données, sans le secret. Un webhook supprimé disparaît avec son journal, et vos webhooks disparaissent avec votre compte. L’ajout et la suppression d’un webhook sont consignés dans le journal d’audit avec son numéro, sans son adresse ; cette entrée est effacée 12 mois plus tard.</p>',
    ],

    'contributions' => [
        'title' => 'Contributions',
        'paragraph' => '<p>Chaque contribution (tracé, événement daté, correction, document) est enregistrée avec votre identifiant et sa date, puis, une fois examinée, avec l’identifiant de la personne qui l’a validée ou refusée et ses remarques. Les validateurs voient le nom d’affichage de l’auteur. Une contribution validée entre dans l’encyclopédie sous la licence prévue par les conditions générales d’utilisation.</p>'
            .'<p>Si vous proposez une image en déposant un fichier, VFF enregistre ce fichier avec le nom de son auteur et sa licence, que vous indiquez, et, si vous les donnez, la date et le lieu de la prise de vue et ses coordonnées. Avant d’enregistrer le fichier, VFF en retire les métadonnées (position GPS, appareil, dates) et n’en garde que l’orientation de l’image. Si une photo contient la position où elle a été prise, votre navigateur la lit pour vous proposer de la recopier dans les coordonnées : elle n’est envoyée à VFF que si vous acceptez. Jusqu’à sa publication, le fichier n’est visible que par vous et par les validateurs. Une fois l’image publiée, son auteur, sa licence, sa date, son lieu et ses coordonnées sont affichés avec elle et diffusés par l’API. Le fichier est effacé si l’image est refusée ou retirée, ou si vous supprimez votre compte avant sa publication. Si vous proposez une image de Wikimedia Commons, le serveur de VFF lit sur Commons son titre, son auteur et sa licence : seule l’adresse du fichier est envoyée à Commons.</p>'
            .'<p>Si vous supprimez votre compte, vos contributions validées restent publiées, car elles font partie de l’encyclopédie et de sa licence, mais elles ne sont plus rattachées à votre nom. Vos contributions en attente sont supprimées.</p>'
            .'<p>Quand une de vos contributions est relue (validée, publiée, refusée ou retirée), vous en êtes informé sur le site et par courriel, avec le motif s’il y en a un. Si vous êtes validateur, vous l’êtes aussi quand une contribution attend votre relecture et, sur le site seulement, quand une erreur est signalée. Ces notifications indiquent le numéro de la contribution ou du signalement et la ligne ou la fiche concernée, jamais le nom d’un autre membre. Elles figurent dans l’export de vos données et sont supprimées avec votre compte ; une notification lue est effacée 12 mois après sa lecture.</p>'
            .'<p>VFF calcule la réputation de chaque membre : le nombre de ses contributions acceptées et publiées, celui de ses contributions refusées, celui des relectures qu’il a faites s’il est validateur, et le niveau qui s’en déduit avec des seuils publics (<a href="/contribute/reputation">page d’aide sur la réputation</a>). Ces chiffres sont enregistrés avec votre compte et recalculés à chaque décision de validation. Vous les voyez dans votre profil. Les validateurs les voient, avec votre niveau, à côté de votre nom quand ils relisent une de vos contributions. Un compte suspendu garde ses chiffres mais n’affiche aucun niveau. Ils figurent dans l’export de vos données, ne sont jamais publiés ni transmis par l’API, et disparaissent avec votre compte.</p>'
            .'<p>Si un administrateur suspend votre compte, le motif, la date de début et la date de fin éventuelle sont enregistrés avec votre compte. Vous en êtes informé sur le site et par courriel, avec le motif, et ces informations figurent dans l’export de vos données et disparaissent avec votre compte. Un compte suspendu ne peut plus proposer de contribution, relire ni signaler d’erreur, mais garde l’accès à ses données : vous pouvez les télécharger et supprimer votre compte. La décision est aussi consignée dans le journal de modération, avec l’administrateur qui l’a prise et le numéro de votre compte (retiré si vous supprimez votre compte), sans le motif ni votre nom ; cette entrée est effacée 12 mois plus tard.</p>',
    ],

    'error_reports' => [
        'title' => 'Signalements d’erreur',
        'paragraph' => '<p>Quand vous signalez une erreur sur une fiche, nous enregistrons votre message, la source que vous indiquez et, si vous la donnez, votre adresse électronique, pour corriger la fiche et pouvoir vous répondre. Si vous avez laissé votre adresse, un courriel vous indique la suite donnée au signalement, avec la note de l’équipe. Si vous êtes connecté, le signalement est rattaché à votre compte. Base légale : l’intérêt légitime d’UnxWares à garder l’encyclopédie exacte.</p>'
            .'<p>Ni votre adresse IP ni aucun traceur ne sont enregistrés avec le signalement. Sans compte, votre adresse IP sert seulement à limiter le nombre d’envois, pendant 24 heures au plus. Seuls les validateurs et les administrateurs de VFF voient les signalements. Un signalement est supprimé 12 mois après son traitement ; si vous êtes membre, il figure dans l’export de vos données et disparaît avec votre compte. Pour faire effacer plus tôt un signalement envoyé sans compte, écrivez à <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.</p>',
    ],

    'emails' => [
        'title' => 'Courriels',
        'paragraph' => 'VFF n’envoie que des courriels de service, comme le lien de réinitialisation du mot de passe, la suite donnée à vos contributions ou l’annonce d’un changement des conditions d’utilisation ou de cette politique. Aucune lettre d’information, aucune prospection.',
    ],

    'recipients' => [
        'title' => 'Qui accède à vos données',
        'paragraph' => '<p>Seules les personnes qui administrent VFF pour UnxWares, et uniquement pour ce qui leur est nécessaire. Les validateurs voient le nom d’affichage de l’auteur des contributions qu’ils examinent.</p>'
            .'<p>Sous-traitants : UnxWares héberge le site sur des serveurs loués à Hetzner Online GmbH (Allemagne, centres de données dans l’Union européenne), avec Netaris (France) pour le réseau et Infomaniak Network SA (Suisse) pour le nom de domaine et les DNS. Les courriels de service partent de la messagerie d’UnxWares, hébergée par Hetzner Online GmbH (Allemagne).</p>',
    ],

    'transfers' => [
        'title' => 'Transferts hors de l’Union européenne',
        'paragraph' => 'Vos données sont hébergées dans l’Union européenne. Infomaniak, qui gère les DNS, est établie en Suisse, pays reconnu par la Commission européenne comme offrant une protection adéquate. Les services tiers listés plus haut reçoivent directement de votre navigateur votre adresse IP, notamment OpenStreetMap (Royaume-Uni) et la Wikimedia Foundation (États-Unis).',
    ],

    'security' => [
        'title' => 'Sécurité',
        'paragraph' => 'Connexion chiffrée (HTTPS), mots de passe hachés, cookies de session inaccessibles aux scripts, protection des formulaires contre les envois frauduleux, accès d’administration limités. En cas de violation de données présentant un risque pour vous, nous prévenons la CNIL et, si nécessaire, les personnes concernées.',
    ],

    'rights' => [
        'title' => 'Vos droits',
        'paragraph' => '<p>Vous pouvez demander l’accès à vos données, leur rectification, leur effacement, leur portabilité ou la limitation de leur traitement, et vous opposer à un traitement fondé sur l’intérêt légitime. Vous pouvez aussi définir des directives sur le sort de vos données après votre décès.</p>'
            .'<p>Écrivez à <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>, si possible depuis l’adresse de votre compte. Nous répondons dans un délai d’un mois. Votre nom d’affichage, votre présentation et votre langue se modifient aussi depuis votre profil.</p>'
            .'<p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir la CNIL : 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07, <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</p>',
    ],

    'minors' => [
        'title' => 'Mineurs',
        'paragraph' => 'Le site se consulte sans compte. Pour créer un compte avant 15 ans, il faut l’accord d’un titulaire de l’autorité parentale.',
    ],

    'changes' => [
        'title' => 'Évolution de cette politique',
        'paragraph' => 'Cette politique suit l’évolution du site. Sa date de mise à jour figure en haut de la page. En cas de changement important, les titulaires d’un compte sont prévenus sur le site et par courriel.',
    ],

    'contact' => [
        'title' => 'Contact',
        'paragraph' => 'Pour toute question sur vos données : <a href="mailto:privacy@vff-project.org">privacy@vff-project.org</a>.',
    ],
];
