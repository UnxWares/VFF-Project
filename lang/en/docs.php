<?php

// Bar and pages of the technical documentation (/docs/api, /docs/swagger, /graphiql).
return [
    'title_scalar' => 'VFF REST API - Reference',
    'title_swagger' => 'VFF REST API - Swagger UI',
    'title_graphiql' => 'VFF GraphQL API - GraphiQL',
    'desc_scalar' => 'Reference for the VFF REST API: endpoints, parameters, responses and code samples, with live requests.',
    'desc_swagger' => 'Reference for the VFF REST API in Swagger UI, on the same OpenAPI specification as the main reference.',
    'desc_graphiql' => 'GraphiQL console to write and run queries on the VFF GraphQL API.',
    'nav_label' => 'API documentation',
    'menu' => 'Documentation',
    'api_page' => 'API access',
    'rest_scalar' => 'REST (Scalar)',
    'rest_swagger' => 'REST (Swagger UI)',
    'openapi_json' => 'OpenAPI (JSON)',
    'graphql_docs' => 'GraphQL (reference)',
    'graphiql' => 'GraphiQL',
    'lang_switch' => 'Language',
    'theme_toggle' => 'Switch between light and dark theme',
    'loading' => 'Loading documentation…',
    'noscript' => 'This interactive documentation requires JavaScript. The raw specification is still available as OpenAPI (JSON).',
    // Query shown when GraphiQL opens (comment and a real query on the VFF API).
    'graphiql_query' => <<<'GQL'
# Write a query, then run it with the play button.
# Ctrl + Space suggests the fields of the schema.
# Example: a line and its dated events.
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
