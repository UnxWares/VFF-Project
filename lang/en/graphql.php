<?php

/*
 * GraphQL API errors (app/GraphQL/Support/QueryLimitErrorHandler.php). Server only:
 * not sent to the front end (see App\Support\Translations::SERVER_ONLY).
 */

return [
    'limits' => [
        'query_too_complex' => 'Query too expensive: :value points, the limit is :max. Ask for fewer items per page (first), drop nested lists or heavy fields (sheet, profile, audit, route), or split the query into several.',
        'query_too_deep' => 'Query nested too deeply: :value levels below the root, the limit is :max. Split it into several queries.',
    ],
];
