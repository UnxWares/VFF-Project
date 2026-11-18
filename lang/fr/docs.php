<?php

// Barre et pages des documentations techniques (/docs/api, /docs/swagger, /graphiql).
return [
    'title_scalar' => 'API REST de VFF - Référence',
    'title_swagger' => 'API REST de VFF - Swagger UI',
    'title_graphiql' => 'API GraphQL de VFF - GraphiQL',
    'desc_scalar' => "Référence de l'API REST de VFF : points d'accès, paramètres, réponses et exemples de code, avec essais en direct.",
    'desc_swagger' => "Référence de l'API REST de VFF dans Swagger UI, sur la même spécification OpenAPI que la référence principale.",
    'desc_graphiql' => "Console GraphiQL pour écrire et exécuter des requêtes sur l'API GraphQL de VFF.",
    'nav_label' => 'Documentations de l’API',
    'menu' => 'Documentations',
    'api_page' => "Accès à l'API",
    'rest_scalar' => 'REST (Scalar)',
    'rest_swagger' => 'REST (Swagger UI)',
    'openapi_json' => 'OpenAPI (JSON)',
    'graphql_docs' => 'GraphQL (référence)',
    'graphiql' => 'GraphiQL',
    'lang_switch' => 'Langue',
    'theme_toggle' => 'Basculer entre thème clair et thème sombre',
    'loading' => 'Chargement de la documentation…',
    'noscript' => 'Cette documentation interactive nécessite JavaScript. La spécification brute reste disponible au format OpenAPI (JSON).',
    // Requête affichée à l'ouverture de GraphiQL (commentaire et requête réelle sur l'API de VFF).
    'graphiql_query' => <<<'GQL'
# Écrivez une requête, puis exécutez-la avec le bouton de lecture.
# Ctrl + Espace propose les champs du schéma.
# Exemple : une ligne et ses événements datés.
{
  line(slug: "rfn-405000") {
    name
    code
    events {
      type
      event_date
      description
    }
  }
}

GQL,
];
