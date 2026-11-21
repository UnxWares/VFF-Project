<?php

/*
 * Erreurs de l'API GraphQL (app/GraphQL/Support/QueryLimitErrorHandler.php). Réservé au serveur :
 * non envoyé au front (cf. App\Support\Translations::SERVER_ONLY).
 */

return [
    'limits' => [
        'query_too_complex' => 'Requête trop coûteuse : :value points pour :max au plus. Demandez moins d’éléments par page (first), retirez des listes imbriquées ou des champs lourds (sheet, profile, audit, route), ou découpez la requête en plusieurs.',
        'query_too_deep' => 'Requête trop imbriquée : :value niveaux sous la racine pour :max au plus. Découpez-la en plusieurs requêtes.',
    ],
];
