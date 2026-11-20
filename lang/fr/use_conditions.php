<?php

// Conditions générales d'utilisation du site et de l'API. Les licences doivent rester cohérentes
// avec les mentions légales (legal.php) et la politique de confidentialité (privacy.php).
// Le français fait foi.
return [
    'meta_title' => 'Conditions générales d’utilisation',
    'meta_description' => 'Règles d’utilisation de Voies Ferrées de France : compte, contributions, licences, réutilisation des données et de l’API.',

    'hero' => [
        'title' => 'Conditions générales d’utilisation',
        'updated' => 'Dernière mise à jour : :date',
    ],

    'purpose' => [
        'title' => 'Objet',
        'paragraph' => 'Les présentes conditions générales d’utilisation (CGU) encadrent l’utilisation de Voies Ferrées de France (VFF), le site <a href="https://vff-project.org">vff-project.org</a> et ses API, édités par UnxWares (voir les <a href="/legal">mentions légales</a>). En cas de différence entre les traductions, la version française fait foi.',
    ],

    'acceptance' => [
        'title' => 'Acceptation',
        'paragraph' => 'Utiliser le site vaut acceptation des CGU en vigueur. Créer un compte suppose de les accepter expressément. Si vous ne les acceptez pas, n’utilisez pas le service.',
    ],

    'access' => [
        'title' => 'Accès au service',
        'paragraph' => 'VFF est gratuit et se consulte sans compte. Contribuer demande un compte. Le service est fourni en l’état : il peut être interrompu pour maintenance, évoluer ou s’arrêter, sans que cela ouvre droit à une indemnité.',
    ],

    'account' => [
        'title' => 'Votre compte',
        'paragraph' => '<ul>'
            .'<li>Un compte est personnel. Il demande un nom d’affichage, une adresse électronique valide et un mot de passe d’au moins 8 caractères, que vous gardez secret. Vous êtes responsable de ce qui est fait depuis votre compte.</li>'
            .'<li>Votre nom d’affichage ne doit pas usurper l’identité d’une autre personne, ni être injurieux ou trompeur.</li>'
            .'<li>Avant 15 ans, l’accord d’un titulaire de l’autorité parentale est nécessaire.</li>'
            .'<li>Tout compte commence avec le rôle de visiteur. Les rôles de contributeur, de validateur (qui examine les contributions des autres) et d’administrateur sont attribués par l’équipe de VFF.</li>'
            .'<li>Vous pouvez demander la suppression de votre compte à tout moment, comme l’explique la <a href="/privacy">politique de confidentialité</a>.</li>'
            .'</ul>',
    ],

    'contributing' => [
        'title' => 'Contribuer',
        'paragraph' => 'Vous pouvez proposer des tracés, des corrections, des événements datés (ouverture, fermeture, électrification…) et des documents. Chaque information doit s’appuyer sur une source vérifiable : le lien vers la source et, pour une date, la citation qui l’établit. Les contributions sont examinées avant d’être publiées.',
    ],

    'licence' => [
        'title' => 'Licence de vos contributions',
        'paragraph' => '<p>Vous restez l’auteur de vos contributions. En les envoyant, vous acceptez qu’elles soient publiées et réutilisables par tous :</p>'
            .'<ul>'
            .'<li>les données (tracés, dates, gares, caractéristiques des lignes) sous <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener">Open Database License (ODbL) 1.0</a>, comme le reste de la base ;</li>'
            .'<li>les textes, photos et documents sous <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" target="_blank" rel="noopener">CC BY-SA 4.0</a>.</li>'
            .'</ul>'
            .'<p>Cette licence est gratuite, valable dans le monde entier et ne peut pas être retirée pour une contribution déjà publiée. Votre nom d’affichage sert à vous créditer.</p>'
            .'<p>Vous garantissez détenir les droits nécessaires. Ne copiez pas un contenu protégé (texte, carte, photographie, base de données) sans l’autorisation de son titulaire ou une licence compatible.</p>',
    ],

    'moderation' => [
        'title' => 'Validation et modération',
        'paragraph' => '<p>Les contributions sont relues par des validateurs avant publication. Une contribution refusée l’est avec un motif.</p>'
            .'<p>UnxWares peut retirer à tout moment un contenu illicite ou contraire aux présentes CGU. Elle en informe l’auteur avec les motifs, sauf si la loi l’interdit. Pour contester une décision, écrivez à <a href="mailto:contact@vff-project.org">contact@vff-project.org</a> : elle sera réexaminée, autant que possible par une autre personne que celle qui l’a prise.</p>',
    ],

    'conduct' => [
        'title' => 'Règles de conduite',
        'paragraph' => '<p>Il est interdit de publier sur VFF :</p>'
            .'<ul>'
            .'<li>un contenu illicite : diffamation, injure, incitation à la haine ou à la violence, atteinte à la vie privée ou au droit à l’image, contrefaçon ;</li>'
            .'<li>des données personnelles d’autres personnes : adresse, téléphone, photographie d’une personne identifiable sans son accord ;</li>'
            .'<li>des informations inventées ou volontairement fausses, ou une source détournée de son sens ;</li>'
            .'<li>de la publicité, du spam ou des liens sans rapport avec le sujet ;</li>'
            .'<li>du vandalisme : suppression ou dégradation de contenus sans justification.</li>'
            .'</ul>'
            .'<p>Il est aussi interdit de perturber le service : tentative d’intrusion, contournement des limites de l’API, création de comptes en masse, collecte automatisée des données personnelles des autres utilisateurs.</p>',
    ],

    'safety' => [
        'title' => 'Sécurité ferroviaire',
        'paragraph' => 'VFF documente aussi des lignes fermées ou abandonnées. Une voie ou une emprise ferroviaire, même d’apparence désaffectée, peut être en service, appartenir à quelqu’un et être dangereuse : n’y entrez jamais sans autorisation. VFF n’accepte pas de document obtenu en se mettant en danger ou en enfreignant la loi.',
    ],

    'reuse' => [
        'title' => 'Réutiliser les données et l’API',
        'paragraph' => '<ul>'
            .'<li>Les données de VFF sont librement réutilisables, y compris à des fins commerciales, sous ODbL 1.0 : citez « Voies Ferrées de France (vff-project.org) » et ses sources, dont « © contributeurs OpenStreetMap », et diffusez sous la même licence toute base de données dérivée que vous publiez. Seule exception : les données reprises de sources qui n’autorisent qu’un usage non commercial, signalées par le champ <code>source</code> de l’API, ne sont réutilisables qu’à des fins non commerciales, en citant leur source avec un lien.</li>'
            .'<li>L’<a href="/api">API</a> REST et l’API GraphQL sont publiques, gratuites et sans clé, dans la limite de 300 requêtes par minute et par adresse IP. Identifiez votre application dans l’en-tête <code>User-Agent</code>, avec un moyen de vous contacter.</li>'
            .'<li>Pour de gros volumes, préférez les exports (GeoJSON, GTFS) aux requêtes en boucle. UnxWares peut limiter ou bloquer un usage qui dégrade le service.</li>'
            .'<li>L’API est fournie sans garantie de disponibilité. Ses évolutions sont annoncées dans sa documentation.</li>'
            .'</ul>',
    ],

    'liability' => [
        'title' => 'Responsabilité',
        'paragraph' => '<p>Les informations de VFF sont documentaires et peuvent comporter des erreurs, notamment pour les périodes anciennes. Elles ne remplacent pas les documents officiels et ne doivent pas servir à des décisions de sécurité ou d’exploitation ferroviaire.</p>'
            .'<p>Chaque contributeur est responsable des contenus qu’il publie. Pour ces contenus, UnxWares agit comme hébergeur : sa responsabilité ne peut être engagée que si, informée d’un contenu manifestement illicite, elle ne l’a pas retiré promptement (voir « Signaler un contenu illicite » dans les <a href="/legal">mentions légales</a>).</p>'
            .'<p>Dans les limites permises par la loi, UnxWares n’est pas responsable d’une interruption du service, d’une perte de données ou d’un dommage indirect lié à l’utilisation du site.</p>',
    ],

    'property' => [
        'title' => 'Propriété intellectuelle et données personnelles',
        'paragraph' => 'Le code, les données et les contenus de VFF sont soumis aux licences décrites dans les <a href="/legal">mentions légales</a>. Le traitement de vos données personnelles est décrit dans la <a href="/privacy">politique de confidentialité</a>.',
    ],

    'suspension' => [
        'title' => 'Suspension et suppression d’un compte',
        'paragraph' => 'En cas de manquement aux CGU, UnxWares peut suspendre ou supprimer un compte, après un avertissement sauf urgence ou manquement grave. La décision est motivée et peut être contestée à <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>.',
    ],

    'changes' => [
        'title' => 'Modification des CGU',
        'paragraph' => 'UnxWares peut modifier les CGU. La version en vigueur est celle publiée sur cette page, avec sa date. Les titulaires d’un compte sont prévenus de tout changement important au moins 15 jours avant son entrée en vigueur. Continuer à utiliser le service ensuite vaut acceptation.',
    ],

    'law' => [
        'title' => 'Droit applicable et litiges',
        'paragraph' => 'Les CGU sont soumises au droit français. En cas de litige, cherchons d’abord une solution amiable : écrivez à <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>. À défaut, les tribunaux français sont compétents, dans les conditions prévues par la loi.',
    ],

    'contact' => [
        'title' => 'Contact',
        'paragraph' => 'Pour toute question sur ces conditions : <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>.',
    ],
];
