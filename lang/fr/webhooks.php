<?php

// Webhooks de l'API (section du profil, App\Http\Controllers\WebhookSubscriptionController,
// App\Services\Webhooks). Vocabulaire : « abonnement », « adresse », « livraison », « secret de signature ».
return [
    'title' => 'Webhooks',
    'intro' => 'Avec un webhook, VFF envoie un court message JSON signé à l’adresse HTTPS de votre choix à chaque événement que vous suivez. Votre programme n’a donc pas besoin d’interroger l’API à intervalles réguliers.',
    'docs_link' => 'Format des messages et vérification de la signature',

    'events' => [
        'contribution_applied' => [
            'label' => 'Contribution publiée',
            'hint' => 'Une contribution approuvée vient d’être appliquée à une ligne ou à une gare.',
        ],
        'current_era_refreshed' => [
            'label' => 'Réseau d’aujourd’hui recalculé',
            'hint' => 'Des lignes ou des gares ont changé dans l’ère courante (recalcul quotidien).',
        ],
        'accident_published' => [
            'label' => 'Nouvel accident',
            'hint' => 'Une fiche d’accident est publiée, une fois ses sources vérifiées.',
        ],
        'material_published' => [
            'label' => 'Nouveau matériel',
            'hint' => 'Une fiche de matériel roulant vient d’être publiée par un import.',
        ],
    ],

    'list_title' => 'Vos abonnements',
    'count' => ':count sur :max',
    'empty' => 'Vous n’avez pas encore d’abonnement.',
    'created_at' => 'Créé le :date',
    'active' => 'Actif',
    'disabled' => 'Désactivé',
    'disabled_failures' => 'Désactivé le :date après :count livraisons échouées d’affilée. Vérifiez que votre adresse répond par un code 2xx, puis réactivez l’abonnement.',
    'failing' => 'Livraisons échouées d’affilée : :count (désactivation à :max).',
    'events_followed' => 'Événements suivis : :list',

    'log_title' => 'Dernières livraisons',
    'log_empty' => 'Aucune livraison pour l’instant.',
    'log_caption' => 'Dernières livraisons de l’abonnement, de la plus récente à la plus ancienne',
    'log_date' => 'Date',
    'log_event' => 'Événement',
    'log_result' => 'Résultat',
    'log_attempts' => 'Essais : :count',
    'log_http' => 'HTTP :code',
    'statuses' => [
        'pending' => 'En attente',
        'succeeded' => 'Livrée',
        'failed' => 'Échouée',
        'skipped' => 'Non envoyée',
    ],
    'delivery_errors' => [
        'timeout' => 'délai dépassé',
        'connection_failed' => 'connexion impossible',
        'http_status' => 'code de réponse refusé',
        'redirect' => 'redirection (jamais suivie)',
        'address_refused' => 'adresse refusée',
        'dns_failed' => 'nom introuvable',
        'internal' => 'erreur de VFF',
    ],

    'enable' => 'Réactiver',
    'enable_label' => 'Réactiver l’abonnement vers :url',
    'delete' => 'Supprimer',
    'delete_label' => 'Supprimer l’abonnement vers :url',
    'delete_confirm_title' => 'Supprimer cet abonnement ?',
    'delete_confirm_text' => 'VFF n’enverra plus rien à :url. Le journal des livraisons est supprimé avec l’abonnement.',
    'delete_confirm_button' => 'Supprimer l’abonnement',
    'cancel' => 'Annuler',

    'create_title' => 'Ajouter un abonnement',
    'url_label' => 'Adresse de réception',
    'url_hint' => 'Une adresse HTTPS publique, sur le port 443, par exemple https://exemple.fr/vff/webhook. Les adresses locales ou privées sont refusées.',
    'events_legend' => 'Événements à recevoir',
    'create_button' => 'Ajouter l’abonnement',
    'blocked_suspended' => 'Votre compte est suspendu : vous ne pouvez pas ajouter d’abonnement, et VFF n’envoie rien à vos adresses jusqu’à la fin de la suspension.',
    'blocked_max' => 'Vous avez :max abonnements, le nombre maximal. Supprimez-en un pour en ajouter un autre.',
    'rules' => 'Chaque message part dans la file d’attente. En cas d’échec, VFF fait :attempts essais au total, espacés de 1 minute à 2 heures. Après :max livraisons échouées d’affilée, l’abonnement est désactivé et vous en êtes averti sur le site.',

    'new_title' => 'Copiez votre secret de signature maintenant',
    'new_text' => 'Il ne sera plus affiché. Votre programme s’en sert pour vérifier que chaque message vient bien de VFF.',
    'new_label' => 'Secret de signature',
    'copy' => 'Copier le secret',
    'copied' => 'Secret copié.',
    'copy_failed' => 'La copie a échoué : sélectionnez le secret et copiez-le à la main.',

    'errors' => [
        'url_required' => 'Indiquez l’adresse de réception.',
        'events_required' => 'Choisissez au moins un événement.',
        'suspended' => 'Votre compte est suspendu : vous ne pouvez pas ajouter d’abonnement.',
        'too_many' => 'Vous avez déjà :max abonnement, le nombre maximal.|Vous avez déjà :max abonnements, le nombre maximal.',
        'url' => [
            'invalid' => 'Cette adresse n’est pas valable.',
            'not_https' => 'L’adresse doit commencer par https://.',
            'credentials' => 'L’adresse ne doit contenir ni identifiant ni mot de passe.',
            'port' => 'Seul le port HTTPS standard (443) est accepté.',
            'host' => 'Ce nom désigne un réseau local ou interne. Indiquez une adresse publique.',
            'private_address' => 'Cette adresse mène à un réseau privé, local ou réservé. Indiquez une adresse publique.',
            'unresolved' => 'Ce nom de domaine est introuvable.',
        ],
    ],

    'flash' => [
        'created' => 'L’abonnement a été ajouté.',
        'deleted' => 'L’abonnement a été supprimé.',
        'enabled' => 'L’abonnement a été réactivé.',
    ],

    'notification' => [
        'title' => 'Un abonnement webhook a été désactivé',
        'body' => 'La livraison vers :url a échoué :count fois d’affilée. VFF n’y envoie plus rien. Vous pouvez réactiver l’abonnement depuis votre profil.|Les livraisons vers :url ont échoué :count fois d’affilée. VFF n’y envoie plus rien. Vous pouvez réactiver l’abonnement depuis votre profil.',
    ],
];
