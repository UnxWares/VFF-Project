<?php

// Leiste und Seiten der technischen Dokumentation (/docs/api, /docs/swagger, /graphiql).
return [
    'title_scalar' => 'VFF REST-API - Referenz',
    'title_swagger' => 'VFF REST-API - Swagger UI',
    'title_graphiql' => 'VFF GraphQL-API - GraphiQL',
    'desc_scalar' => 'Referenz der VFF-REST-API: Endpunkte, Parameter, Antworten und Codebeispiele, mit Live-Anfragen.',
    'desc_swagger' => 'Referenz der VFF-REST-API in Swagger UI, auf derselben OpenAPI-Spezifikation wie die Hauptreferenz.',
    'desc_graphiql' => 'GraphiQL-Konsole zum Schreiben und Ausführen von Abfragen an die VFF-GraphQL-API.',
    'nav_label' => 'API-Dokumentation',
    'menu' => 'Dokumentation',
    'api_page' => 'API-Zugang',
    'rest_scalar' => 'REST (Scalar)',
    'rest_swagger' => 'REST (Swagger UI)',
    'openapi_json' => 'OpenAPI (JSON)',
    'graphql_docs' => 'GraphQL (Referenz)',
    'graphiql' => 'GraphiQL',
    'lang_switch' => 'Sprache',
    'theme_toggle' => 'Zwischen hellem und dunklem Design wechseln',
    'loading' => 'Dokumentation wird geladen…',
    'noscript' => 'Diese interaktive Dokumentation benötigt JavaScript. Die Rohspezifikation ist weiterhin als OpenAPI (JSON) verfügbar.',
    // Beim Öffnen von GraphiQL angezeigte Abfrage (Kommentar und echte Abfrage an die VFF-API).
    'graphiql_query' => <<<'GQL'
# Schreiben Sie eine Abfrage und führen Sie sie mit der Wiedergabetaste aus.
# Strg + Leertaste schlägt die Felder des Schemas vor.
# Beispiel: eine Strecke und ihre datierten Ereignisse.
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
