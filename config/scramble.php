<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API path. By default, all routes starting with this path will be added to the docs.
     * If you need to change this behavior, you can add your custom routes resolver using `Scramble::routes()`.
     */
    'api_path' => 'api/v1',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'MD'
            API publique de **VFF - Voies Ferrées de France**, l'encyclopédie collaborative du rail français :
            tracés, gares, chronologies, signalisation et médias du réseau ferroviaire, de 1830 à aujourd'hui.

            ## Ères
            Le réseau est décrit par **ères décennales**, de `1830` à `2030`. Chaque ère est un instantané
            au **31/12** de son année : l'ère `1930` montre le réseau au 31/12/1930. L'ère `2030` désigne
            le réseau **d'aujourd'hui**, recalculé au jour même. Les dates d'événements, elles, restent
            exactes au jour près quand on les connaît (voir la chronologie d'une ligne).

            ## Formats
            - **JSON** pour les fiches, la recherche et les itinéraires ;
            - **GeoJSON** (RFC 7946, coordonnées WGS 84 dans l'ordre longitude, latitude) pour les tracés et les gares ;
            - **Mapbox Vector Tiles** (MVT) pour l'affichage cartographique avec MapLibre GL ;
            - **GTFS Static** (ZIP) pour les outils de transport.

            Distances et points kilométriques (PK) en **mètres**, notation ferroviaire `km+mmm`
            (`249+204` = 249,204 km), vitesses en km/h, tensions en volts, écartements en millimètres.

            ## Accès
            Tous les endpoints sont en lecture (`GET`), **anonymes** et sans clé. Limite : 300 requêtes
            par minute et par adresse IP (HTTP 429 au-delà). Erreurs au format JSON : `message`, et pour
            certaines un code `error` stable (ex. `no_geometry`, `station_not_found`). Envoyez l'en-tête
            `Accept: application/json` : un paramètre invalide renvoie alors HTTP 422, avec le détail
            par paramètre dans `errors`.

            ## Licences et sources
            Données ouvertes : citez « VFF - Voies Ferrées de France » et un lien vers https://vff-project.org.
            Les tracés et les gares agrègent plusieurs sources dont les licences s'appliquent : SNCF Open
            Data et OpenStreetMap (ODbL), IGN BD TOPO (Licence Ouverte 2.0), OpenHistoricalMap et Wikidata
            (CC0), Wikipédia (CC BY-SA). Les données signalées par `source` = `archeoferroviaire` ne sont
            pas sous ODbL : usage non commercial uniquement, avec un crédit et un lien vers la source.
            Chaque média porte sa propre licence et son attribution.

            ## Voir aussi
            - API GraphQL : `POST /graphql`, référence sur [/docs/graphql](/docs/graphql), essais dans [GraphiQL](/graphiql) ;
            - spécification OpenAPI brute : [/docs/api.json](/docs/api.json) ;
            - cette documentation existe aussi en [Swagger UI](/docs/swagger).
            MD,
    ],

    /*
     * Interface de documentation. La vue Stoplight Elements de Scramble est remplacée
     * par Scalar (resources/views/vendor/scramble/docs.blade.php, /docs/api) et Swagger UI
     * (/docs/swagger) : seul `title` est encore lu, les autres réglages ne servent plus.
     */
    'ui' => [
        /*
         * Define the title of the documentation's website. App name is used when this config is `null`.
         */
        'title' => 'VFF - API publique v1',

        /*
         * Define the theme of the documentation. Available options are `light`, `dark`, and `system`.
         */
        'theme' => 'light',

        /*
         * Hide the `Try It` feature. Enabled by default.
         */
        'hide_try_it' => false,

        /*
         * Hide the schemas in the Table of Contents. Enabled by default.
         */
        'hide_schemas' => false,

        /*
         * URL to an image that displays as a small square logo next to the title, above the table of contents.
         */
        'logo' => '',

        /*
         * Use to fetch the credential policy for the Try It feature. Options are: omit, include (default), and same-origin
         */
        'try_it_credentials_policy' => 'include',

        /*
         * There are three layouts for Elements:
         * - sidebar - (Elements default) Three-column design with a sidebar that can be resized.
         * - responsive - Like sidebar, except at small screen sizes it collapses the sidebar into a drawer that can be toggled open.
         * - stacked - Everything in a single column, making integrations with existing websites that have their own sidebar or other columns already.
         */
        'layout' => 'responsive',
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' - Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' - Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' - Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    /*
     * Doc publique (pas de gate RestrictedDocsAccess) - l'API VFF v1 est
     * ouverte, donc la documentation l'est aussi.
     */
    'middleware' => [
        'web',
    ],

    'extensions' => [],
];
