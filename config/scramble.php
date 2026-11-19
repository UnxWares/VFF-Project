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
        // {limit.anonymous|member|partner} : limites de config/api.php, posées par App\Support\ApiAuthDocumentation.
        'description' => <<<'MD'
            API publique de **VFF - Voies Ferrées de France**, l'encyclopédie collaborative du rail français :
            tracés, gares, chronologies, signalisation et médias du réseau ferroviaire, de 1830 à aujourd'hui.

            ## Ce que couvre l'API
            Tout ce que montre le site s'obtient ici, calculé par le même code que les pages :
            - **annuaire des lignes** (`/lines`, filtres de la page [/lines](/lines)) et **fiche complète**
              d'une ligne (`/lines/{line}` : chiffres clés, électrification, signalisation, phases datées,
              chronique sourcée, gares, sections nommées, sources), avec ses tronçons par ère
              (`/lines/{line}/segments`), en GeoJSON (`/lines/{line}/geojson`) ou en fichier GPX et KML à
              télécharger (`/lines/{line}/gpx`, `/lines/{line}/kml`), sa chronologie, ses gares par
              ère, sa signalisation, son profil en long, ses passages à niveau, ses médias ;
            - **annuaire des gares** (`/stations`) et **fiche complète** d'une gare (`/stations/{station}`) ;
            - **chiffres d'ensemble** du site et courbe du réseau (`/stats`), **époques** (`/eras`) et
              **statistiques du réseau** à chaque époque (`/eras/{year}`), cartes par ère (GeoJSON, tuiles
              MVT, GTFS) ;
            - **événements** de tout le réseau (`/events`) et **médias** (`/media`) ;
            - **matériel roulant** (`/materials`) ; **itinéraires historiques** (`/route`) ; **recherche** ;
            - **registre des sources et licences** (`/sources`).

            La même matière existe en **GraphQL** (`POST /graphql`, requêtes `lineDirectory`,
            `stationDirectory`, `Line.sheet`, `Station.sheet`, `Era.stats`, `sources`…) et, pour les
            assistants IA, par le **serveur MCP** (`POST /mcp`, voir `docs/MCP.md`). Seules les
            contributions, réservées aux comptes, restent hors de l'API publique.

            ## Ères
            Le réseau est décrit par **ères décennales**, de `1830` à `2030`. Chaque ère est un instantané
            au **31/12** de son année : l'ère `1930` montre le réseau au 31/12/1930. L'ère `2030` désigne
            le réseau **d'aujourd'hui**, recalculé au jour même. Les dates d'événements, elles, restent
            exactes au jour près quand on les connaît (voir la chronologie d'une ligne).

            ## Formats
            - **JSON** pour les fiches, la recherche et les itinéraires ;
            - **GeoJSON** (RFC 7946, coordonnées WGS 84 dans l'ordre longitude, latitude) pour les tracés et les gares ;
            - **GPX 1.1** et **KML 2.2** (fichiers à télécharger) pour le tracé d'une ligne ;
            - **Mapbox Vector Tiles** (MVT) pour l'affichage cartographique avec MapLibre GL ;
            - **GTFS Static** (ZIP) pour les outils de transport.

            Distances et points kilométriques (PK) en **mètres**, notation ferroviaire `km+mmm`
            (`249+204` = 249,204 km), vitesses en km/h, tensions en volts, écartements en millimètres.

            ## Authentification et quotas
            Tous les points d'accès sont en lecture (`GET`) et s'appellent **sans jeton** ni compte. Un **jeton
            personnel**, facultatif, relève la limite de débit : un membre le crée depuis son profil (section
            « Jetons d'accès à l'API » de [/profile](/profile)). Il ne sert qu'à lire, comme l'accès anonyme, et
            n'est affiché qu'une fois, à sa création. Il s'envoie dans l'en-tête `Authorization: Bearer <jeton>`.

            | Profil | Limite | Compteur |
            | --- | --- | --- |
            | Sans jeton | {limit.anonymous} requêtes par minute | par adresse IP |
            | Jeton d'un membre | {limit.member} requêtes par minute | par compte, tous ses jetons ensemble |
            | Jeton d'un compte partenaire | {limit.partner} requêtes par minute | par compte (profil accordé par l'équipe de VFF) |

            Le compteur est commun à l'API REST, à GraphQL et au serveur MCP. Chaque réponse porte
            `X-RateLimit-Limit` (la limite de l'appelant) et `X-RateLimit-Remaining` (requêtes restantes dans la
            minute). Au-delà, **HTTP 429** avec `Retry-After` (attente en secondes). Un jeton inconnu, révoqué
            ou expiré donne **HTTP 401** (`"error": "invalid_token"`) : l'appel n'est pas compté comme
            anonyme, retirez l'en-tête pour revenir à l'accès sans jeton. Le jeton d'un compte suspendu donne
            **HTTP 403** (`"error": "account_suspended"`) jusqu'à la fin de la suspension.

            ```bash
            curl -i https://vff-project.org/api/v1/stats
            curl -i -H "Authorization: Bearer $VFF_TOKEN" https://vff-project.org/api/v1/stats
            ```

            ```javascript
            const response = await fetch("https://vff-project.org/api/v1/stats", {
                headers: { Authorization: `Bearer ${process.env.VFF_TOKEN}` },
            });
            console.log(response.status, response.headers.get("X-RateLimit-Limit"), response.headers.get("X-RateLimit-Remaining"));
            ```

            ```python
            import os, time
            import requests

            headers = {"Authorization": f"Bearer {os.environ['VFF_TOKEN']}"}
            response = requests.get("https://vff-project.org/api/v1/stats", headers=headers, timeout=30)
            if response.status_code == 429:
                time.sleep(int(response.headers["Retry-After"]))
            print(response.status_code, response.headers["X-RateLimit-Limit"], response.headers["X-RateLimit-Remaining"])
            ```

            Les réponses portent aussi un en-tête `Cache-Control` (5 minutes en général, 1 heure pour le
            registre des sources) : gardez-les en cache plutôt que de les redemander. Envoyez l'en-tête
            `Accept: application/json`. Les textes tirés des sources et rédigés par VFF (notes, présentations
            d'ères, chroniques) sont en français.
            ## Conventions communes
            - **Identifiants** : les lignes et les gares se désignent par leur `slug` (ex. `rfn-420000`,
              `brest`), les événements et les médias par leur `id` numérique, les ères par leur année.
            - **Dates** : ISO `AAAA-MM-JJ`, toujours accompagnées de leur précision (`date_precision`,
              `opened_precision`… : `day`, `month`, `year`) ; quand seule l'année est connue, mois et jour
              valent `01`. N'affichez que la partie connue. Une période (`from`, `to`) accepte une année
              (`1930`) ou une date (`1930-06-15`), bornes incluses.
            - **Mêmes noms de paramètres partout** : `q` (recherche littérale), `department` (code INSEE),
              `era` (année d'une ère), `from`/`to`, `sort` et `order` (`asc`, `desc`), `per_page` et
              `cursor` ; booléens écrits `true`/`false` (ou `1`/`0`).
            - **Liens** : chaque élément d'annuaire et chaque fiche donnent `links.web` (la page du site)
              et les adresses des points d'accès liés.

            ## Erreurs
            - **404** : la ressource demandée n'existe pas : ligne, gare, événement, média, fournisseur ou
              ère du chemin, ou ère choisie par `era` pour un objet d'une ère (`/lines/{line}/stops`,
              `/signalling`, `/segments`, `/pk`, `/route`). Corps : `{"message": "…"}`, parfois avec un code
              `error` stable (ex. `era_not_found`, `station_not_found`, `no_geometry`).
            - **422** : paramètre invalide, y compris un **filtre** de liste qui ne désigne rien (ligne,
              département ou ère inconnus dans `/events`, `/lines`, `/stations`), une valeur hors
              énumération, une date mal formée, une borne dépassée ou un curseur illisible. Corps :
              `{"message": "…", "errors": {"paramètre": ["…"]}}`. Il n'y a pas de HTTP 400 : une requête
              mal formée est toujours signalée par un 422 détaillé.
            - **401** : jeton invalide, révoqué ou expiré (`invalid_token`) ; **403** : jeton d'un compte
              suspendu (`account_suspended`). Voir « Authentification et quotas ».
            - **429** : limite de requêtes par minute dépassée ({limit.anonymous} sans jeton). L'en-tête `Retry-After`
              donne l'attente en secondes, `X-RateLimit-Remaining` le nombre de requêtes restantes.
            - Les messages d'erreur de validation sont en anglais, quelle que soit la langue du site.

            ## Pagination par curseur
            Les annuaires des lignes, des gares et du matériel (`/lines`, `/stations`, `/materials`), les listes d'événements
            (`/events`, `/stations/{station}/events`) et de médias (`/media`) sont paginés **par curseur** :
            `per_page` (taille de page, bornée) et `cursor`. Chaque réponse
            contient `data`, `links.next` (adresse de la page suivante, filtres conservés, `null` à la
            dernière page) et `meta.next_cursor`. Suivez `links.next` jusqu'à `null`. Il n'y a ni numéro
            de page ni total : le curseur reste valable même si des données sont ajoutées entre deux
            appels. C'est une chaîne opaque, à reprendre telle quelle, valable seulement pour la liste
            et le tri qui l'ont produite (sinon HTTP 422). Tri : chronologique pour les événements, par
            identifiant pour les médias, au choix (`sort`) pour les annuaires ; `order=desc` l'inverse.
            Les annuaires ajoutent, sur demande (`facets=true`), les compteurs de la recherche en cours
            dans `meta.facets`. En GraphQL, le même curseur se passe dans `after`.

            ## Provenance et licence de chaque donnée
            Chaque événement, chaque tronçon, chaque source de gare porte sa source (`source`,
            `source_url`, parfois une citation `source_quote`) et un bloc `provenance` : fournisseur
            (`provider`, `name`), licence (`license`) et solidité de
            cette licence (`license_status` : `verified` licence ouverte établie, `uncertain` autorisation
            particulière ou conditions à lire, `forbidden`). Chaque média porte son auteur, sa licence,
            son `attribution` prête à afficher et le lien `source_url` de sa page d'origine. Les fiches
            gardent la source de chaque fait (`facts`, `attribute_sources`, notes de la chronique). Les
            sources d'une gare donnent leur provenance brute en lecture seule : identifiant dans la source,
            date de lecture (`fetched_at`), attributs reçus tels quels (`raw`), méthode de rapprochement et
            écart à la position retenue. La présentation d'une ligne (`description`) est un extrait de
            Wikipédia sous CC BY-SA 4.0 : `description_source` donne de quoi la citer (auteur collectif,
            article, permalien de la version lue, licence, mention prête à copier). Le registre complet des fournisseurs (licence, mention à reproduire, pages qui l'établissent)
            est sur `/sources`. Citez toujours le fournisseur en réutilisant une donnée.

            ## Licences et sources
            Données ouvertes : citez « VFF - Voies Ferrées de France » et un lien vers https://vff-project.org.
            Les tracés et les gares agrègent plusieurs sources dont les licences s'appliquent : SNCF Open
            Data et OpenStreetMap (ODbL), IGN BD TOPO (Licence Ouverte 2.0), OpenHistoricalMap et Wikidata
            (CC0), Wikipédia (CC BY-SA). Les données signalées par `source` = `archeoferroviaire` ne sont
            pas sous ODbL : usage non commercial uniquement, avec un crédit et un lien vers la source.
            Le tracé d'une ligne en fichier (`/lines/{line}/geojson`, `/gpx`, `/kml`) ne contient que les
            géométries dont la source a une licence ouverte : celles d'une source non commerciale, sous
            accord particulier ou de statut incertain en sont écartées, et la réponse dit lesquelles
            (`withheld`, `geometry_withheld`, ou HTTP 451 quand rien n'est exportable). Chaque fichier porte
            la mention de la base VFF et celle de chaque source utilisée. Chaque média porte sa propre
            licence et son attribution.

            ## Versionnement
            L'API est en **version 1** (préfixe `/api/v1`, numéro de version de ce document). Dans une
            même version, les changements sont seulement additifs : nouveaux points d'accès, nouveaux
            champs, nouvelles valeurs d'énumération (ignorez ce que vous ne connaissez pas). Un
            changement incompatible (champ retiré ou renommé, sens modifié) passera par un nouveau
            préfixe `/api/v2`, la v1 restant servie pendant la transition. Le schéma GraphQL suit les
            mêmes règles (champs retirés d'abord marqués `@deprecated`).

            ## Voir aussi
            - API GraphQL : `POST /graphql`, référence sur [/docs/graphql](/docs/graphql), essais dans [GraphiQL](/graphiql) ;
            - serveur MCP pour les assistants IA : `POST /mcp` (documentation : `docs/MCP.md` du dépôt) ;
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
