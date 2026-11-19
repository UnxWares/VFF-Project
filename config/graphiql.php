<?php

/**
 * Console GraphiQL (mll-lab/laravel-graphiql), vue publiée dans resources/views/vendor/graphiql.
 *
 * La route du paquet n'a aucun middleware par défaut : sans le groupe `web`, SetLocale ne tournait pas
 * et la page restait en français quelle que soit la langue du visiteur (cookie `locale`,
 * Accept-Language). Le groupe `web` lui donne aussi les en-têtes de sécurité des autres pages.
 */
return [
    'routes' => [
        '/graphiql' => [
            'name' => 'graphiql',
            'middleware' => ['web'],
            'endpoint' => '/graphql',
            'subscription-endpoint' => env('GRAPHIQL_SUBSCRIPTION_ENDPOINT'),
        ],
    ],

    'enabled' => env('GRAPHIQL_ENABLED', true),
];
