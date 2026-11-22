<script>
    import Layout from '$components/Layout.svelte';
    import PageHero from '$components/PageHero.svelte';
    import SectionBlock from '$components/SectionBlock.svelte';
    import IconCard from '$components/IconCard.svelte';
    import { i18n } from '$lib/i18n.js';

    /**
     * Page « Accès à l'API » : renvoie vers les six documentations (Scalar, Swagger UI, OpenAPI, référence
     * GraphQL, GraphiQL, schéma), rappelle les conditions d'accès, liste les points d'accès REST publics
     * de routes/api.php par sujet (avec les noms de paramètres de la spécification), donne des exemples
     * dont la réponse est celle du serveur et présente le serveur MCP. Les documentations sont des pages
     * hors Inertia : liens simples.
     *
     * `rate_limits` : requêtes par minute de chaque profil (config/api.php, routes/web.php), montrées
     * dans « Accès et limites », « Authentification et quotas » (ancre #auth, liée depuis le profil)
     * et la section MCP.
     *
     * Section « Contribuer par GraphQL » (ancre #contribute, liée depuis le profil) : mutations
     * submitContribution et validateContribution, authentification, exemples curl et erreurs.
     *
     * `webhooks` : règles de livraison des webhooks (config/webhooks.php), section « Webhooks » (ancre
     * #webhooks, liée depuis le profil).
     *
     * @type {{
     *   rate_limits: { anonymous: number, member: number, partner: number },
     *   webhooks: { tolerance: number, timeout: number, attempts: number, disable_after: number },
     * }}
     */
    let { rate_limits, webhooks } = $props();
    const number = (n) => n.toLocaleString($i18n.language);
    const authProfiles = ['anonymous', 'member', 'partner'];

    const docs = [
        { key: 'scalar', href: '/docs/api' },
        { key: 'swagger', href: '/docs/swagger' },
        { key: 'openapi', href: '/docs/api.json' },
        { key: 'graphql', href: '/docs/graphql' },
        { key: 'graphiql', href: '/graphiql' },
        { key: 'schema', href: '/docs/schema.graphql' },
    ];

    const access = [{ key: 'readonly' }, { key: 'no_key' }, { key: 'limit' }, { key: 'licence' }];

    // Points d'accès de routes/api.php, rangés par sujet (mêmes groupes que la référence Scalar).
    const endpointGroups = [
        {
            key: 'network',
            items: [
                { path: '/api/v1/stats', key: 'stats' },
                { path: '/api/v1/eras', key: 'eras' },
                { path: '/api/v1/eras/{year}', key: 'era' },
                { path: '/api/v1/eras/{year}/lines.geojson', key: 'lines_geojson' },
                { path: '/api/v1/eras/{year}/stations.geojson', key: 'stations_geojson' },
                { path: '/api/v1/eras/{year}/schematic.geojson', key: 'schematic' },
                { path: '/api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt', key: 'tiles' },
                { path: '/api/v1/eras/{year}/gtfs.zip', key: 'gtfs' },
            ],
        },
        {
            key: 'lines',
            items: [
                { path: '/api/v1/lines', key: 'lines' },
                { path: '/api/v1/lines/{line}', key: 'line' },
                { path: '/api/v1/lines/{line}/segments?era={year}', key: 'segments' },
                { path: '/api/v1/lines/{line}/geojson?era={year}', key: 'line_geojson' },
                {
                    path: '/api/v1/lines/{line}/gpx?era={year}',
                    key: 'line_gpx',
                    doc: '/docs/api#api-1/tag/lignes/GET/lines/{line}/gpx',
                },
                {
                    path: '/api/v1/lines/{line}/kml?era={year}',
                    key: 'line_kml',
                    doc: '/docs/api#api-1/tag/lignes/GET/lines/{line}/kml',
                },
                { path: '/api/v1/lines/{line}/history', key: 'history' },
                { path: '/api/v1/lines/{line}/pk?lat={lat}&lng={lng}', key: 'pk' },
                { path: '/api/v1/lines/{line}/stops?era={year}', key: 'stops' },
                { path: '/api/v1/lines/{line}/signalling', key: 'signalling' },
                { path: '/api/v1/lines/{line}/signalling-history', key: 'signalling_history' },
                { path: '/api/v1/lines/{line}/profile', key: 'profile' },
                {
                    path: '/api/v1/lines/{line}/profile/points?pk_from_m={pk}&pk_to_m={pk}',
                    key: 'profile_points',
                },
                { path: '/api/v1/lines/{line}/altitude?pk_m={pk}', key: 'altitude' },
                {
                    path: '/api/v1/lines/{line}/audit?era={year}&limit={n}&offset={n}',
                    key: 'line_audit',
                },
                {
                    path: '/api/v1/audits/segments?era={year}&grade={grade}&department={code}&limit={n}&offset={n}',
                    key: 'segment_audits',
                },
                {
                    path: '/api/v1/audits/stations?grade={grade}&department={code}&limit={n}&offset={n}',
                    key: 'station_audits',
                },
                { path: '/api/v1/lines/{line}/materials', key: 'line_materials' },
                { path: '/api/v1/lines/{line}/media', key: 'media' },
                { path: '/api/v1/lines/{line}/greenways', key: 'line_greenways' },
                { path: '/api/v1/lines/{line}/infrastructure', key: 'line_infrastructure' },
                { path: '/api/v1/lines/{line}/archives', key: 'line_archives' },
                { path: '/api/v1/lines/{line}/archive-records', key: 'line_archive_records' },
                { path: '/api/v1/lines/{line}/stories/{event}', key: 'line_stories' },
                {
                    path: '/api/v1/greenways/tiles/{z}/{x}/{y}.mvt',
                    key: 'greenways_tiles',
                },
            ],
        },
        {
            key: 'equipment',
            items: [
                { path: '/api/v1/level-crossings', key: 'level_crossings' },
                { path: '/api/v1/lines/{line}/level-crossings', key: 'line_level_crossings' },
                {
                    path: '/api/v1/level-crossings/tiles/{z}/{x}/{y}.mvt',
                    key: 'level_crossings_tiles',
                },
                {
                    path: '/api/v1/eras/{year}/signalling/tiles/{z}/{x}/{y}.mvt',
                    key: 'signalling_tiles',
                },
            ],
        },
        {
            key: 'stations',
            items: [
                { path: '/api/v1/stations', key: 'stations' },
                { path: '/api/v1/stations/{station}', key: 'station' },
                { path: '/api/v1/stations/{station}/events', key: 'station_events' },
                { path: '/api/v1/stations/{station}/media', key: 'station_media' },
            ],
        },
        {
            key: 'territories',
            items: [
                { path: '/api/v1/departments?region={region}', key: 'departments' },
                { path: '/api/v1/departments/{department}', key: 'department' },
                { path: '/api/v1/regions', key: 'regions' },
                { path: '/api/v1/regions/{region}', key: 'region' },
            ],
        },
        {
            key: 'events_media',
            items: [
                { path: '/api/v1/events', key: 'events' },
                { path: '/api/v1/events/{event}', key: 'event' },
                { path: '/api/v1/media', key: 'media_all' },
                { path: '/api/v1/media/{media}', key: 'media_item' },
            ],
        },
        {
            key: 'materials_accidents',
            items: [
                { path: '/api/v1/materials', key: 'materials' },
                { path: '/api/v1/materials/{material}', key: 'material' },
                { path: '/api/v1/materials/{material}/media', key: 'material_media' },
                { path: '/api/v1/accidents', key: 'accidents' },
                { path: '/api/v1/accidents.geojson', key: 'accidents_map' },
                { path: '/api/v1/accidents/{accident}', key: 'accident' },
            ],
        },
        {
            key: 'journal',
            items: [
                { path: '/api/v1/journal', key: 'journal' },
                { path: '/api/v1/journal/{slug}', key: 'journal_article' },
            ],
        },
        {
            key: 'tools',
            items: [
                { path: '/api/v1/search?q={query}', key: 'search' },
                { path: '/api/v1/route?from={station}&to={station}&era={year}', key: 'route' },
                {
                    path: '/api/v1/route/compare?from={station}&to={station}&era={year}&compare={year}',
                    key: 'route_compare',
                },
                { path: '/api/v1/sources', key: 'sources' },
                { path: '/api/v1/sources/{provider}', key: 'source' },
            ],
        },
    ];

    // Serveur MCP (docs/MCP.md, app/Mcp/Servers/VffServer.php) : outils dans l'ordre du serveur.
    const MCP_URL = 'https://vff-project.org/mcp';
    const mcpTools = [
        'search',
        'get_line',
        'line_events',
        'network_events',
        'get_station',
        'network_at_era',
        'historical_route',
        'list_lines',
        'list_stations',
        'media',
        'materials',
        'get_material',
        'accidents',
        'get_accident',
        'compare_eras',
        'level_crossings',
        'line_profile',
        'line_audit',
        'segment_audits',
        'station_audits',
        'line_greenways',
        'line_infrastructure',
        'line_archives',
        'line_archive_records',
        'line_stories',
        'journal',
        'line_materials',
        'locate_on_line',
        'signalling_history',
        'list_departments',
        'sources_and_licences',
    ];
    // Invites (prompts) du serveur MCP, dans l'ordre du serveur.
    const mcpPrompts = ['present_line', 'compare_region_eras', 'station_story'];
    const mcpCli = `claude mcp add --transport http vff ${MCP_URL}`;
    const mcpConfig = `{
  "mcpServers": {
    "vff": {
      "type": "http",
      "url": "${MCP_URL}"
    }
  }
}`;

    /** Coupe un chemin après chaque barre oblique : une longue adresse passe à la ligne entre deux segments. */
    const segments = (path) => path.split(/(?<=[/?&])/);

    const curlExample = $derived(`# ${$i18n.t('api.example.curl_lines')}
curl https://vff-project.org/api/v1/eras/2030/lines.geojson

# ${$i18n.t('api.example.curl_pk')}
curl "https://vff-project.org/api/v1/lines/rfn-420000/pk?lat=48.10&lng=-1.67"

# ${$i18n.t('api.example.curl_search')}
curl "https://vff-project.org/api/v1/search?q=rennes"`);

    // La deuxième requête en JavaScript (Node.js 18 et plus, ou navigateur : `fetch` est natif) et en Python.
    // Chaînes simples : un gabarit de texte interpréterait « ${...} ».
    const jsExample = [
        'const url = new URL("https://vff-project.org/api/v1/lines/rfn-420000/pk");',
        'url.search = new URLSearchParams({ lat: "48.10", lng: "-1.67" });',
        '',
        'const response = await fetch(url);',
        'if (!response.ok) throw new Error(`HTTP ${response.status}`);',
        'const body = await response.json();',
        '',
        'console.info(body.pk_label, body.snap.distance_from_input_m);',
    ].join('\n');

    const pyExample = [
        'import requests',
        '',
        'response = requests.get(',
        '    "https://vff-project.org/api/v1/lines/rfn-420000/pk",',
        '    params={"lat": 48.10, "lng": -1.67},',
        '    timeout=30,',
        ')',
        'response.raise_for_status()',
        'body = response.json()',
        '',
        'print(body["pk_label"], body["snap"]["distance_from_input_m"])',
    ].join('\n');

    // Réponse réelle du serveur à la deuxième requête (nom de ligne : celui de la base).
    const responseExample = `{
  "line": {
    "slug": "rfn-420000",
    "code": "420000",
    "name": "Ligne de Paris-Montparnasse à Brest"
  },
  "pk_m": 249204,
  "pk_label": "249+204",
  "total_m": 626972,
  "snap": {
    "lat": 48.10357643870743,
    "lng": -1.6702395515287747,
    "distance_from_input_m": 395.12002852
  }
}`;

    const graphqlExample = $derived(`# ${$i18n.t('api.graphql.example_comment')}
{
  line(slug: "rfn-405000") {
    name
    code
    events {
      type
      event_date
    }
  }
}`);

    // Section « Audits, profils, images et accidents » : exemples des listes d'audit du réseau, de l'audit
    // paginé d'une ligne, du profil en long par tranche de PK, des compteurs d'images et de la provenance
    // des accidents, avec des réponses réelles du serveur (champs abrégés par « … » quand c'est long).
    // Chaînes simples ou tableaux joints : un gabarit de texte interpréterait « ${...} ».
    const BASE = 'https://vff-project.org';
    const auditSegmentsCurl = `curl "${BASE}/api/v1/audits/segments?era=2030&grade=poor&department=29&limit=1"`;
    const auditSegmentsResponse = `{
  "era_year": 2030,
  "total": 26,
  "limit": 1,
  "offset": 0,
  "items": [
    {
      "line_slug": "archeo-ligne-de-landerneau-a-lesneven",
      "line_code": null,
      "line_name": "Ligne de Landerneau à Lesneven",
      "segment_id": 9309008,
      "status": "abandoned",
      "traffic_type": "unknown",
      "trace_source": "archeoferroviaire",
      "length_m": 53,
      "grades": { "classification": "fair", "placement": "poor", "accuracy": "poor", "trace": "poor" },
      "worst_grade": "poor",
      "score": 88,
      "reasons": {
        "classification": ["rule_track_unconfirmed", "weak_sources_only"],
        "placement": ["median_over_tolerance"],
        "accuracy": ["hole"],
        "trace": ["precise_trace_available", "unused_trace_in_gap"]
      },
      "audited_at": "2026-11-21T10:45:56+01:00"
    }
  ]
}`;
    const auditSegmentsJs = [
        'const url = new URL("https://vff-project.org/api/v1/audits/segments");',
        'url.search = new URLSearchParams({ era: "2030", grade: "poor", department: "29", limit: "50", offset: "0" });',
        '',
        'const response = await fetch(url);',
        'if (!response.ok) throw new Error(`HTTP ${response.status}`);',
        'const body = await response.json();',
        '',
        'console.info(body.total, "segments, era", body.era_year);',
        'for (const s of body.items) console.info(s.line_slug, s.segment_id, s.worst_grade, s.reasons);',
        '// Next page: offset + limit, until you reach body.total.',
    ].join('\n');
    const auditSegmentsPy = [
        'import requests',
        '',
        'response = requests.get(',
        '    "https://vff-project.org/api/v1/audits/segments",',
        '    params={"era": 2030, "grade": "poor", "department": "29", "limit": 50, "offset": 0},',
        '    timeout=30,',
        ')',
        'response.raise_for_status()',
        'body = response.json()',
        '',
        'print(body["total"], "segments, era", body["era_year"])',
        'for s in body["items"]:',
        '    print(s["line_slug"], s["segment_id"], s["worst_grade"], s["reasons"])',
        '# Next page: offset + limit, until you reach body["total"].',
    ].join('\n');
    const auditStationsCurl = `curl "${BASE}/api/v1/audits/stations?grade=disagree&department=57&limit=1"`;
    const auditStationsResponse = `{
  "limit": 1,
  "offset": 0,
  "total": 5,
  "items": [
    {
      "station_id": 18740,
      "slug": "redange-moselle",
      "name": "Rédange (Moselle)",
      "department": "57",
      "region": "Grand Est",
      "status": "declassified",
      "position_source": "sncf",
      "placement_grade": "disagree",
      "families": 2,
      "nearest_agreeing_m": 12262,
      "farthest_precise_m": null,
      "track_offset_m": 1,
      "opening_grade": "estimated",
      "closure_grade": "estimated",
      "passenger": "closed",
      "freight": "closed",
      "checks": ["freight_only_undated"],
      "sources": ["sncf", "wikidata"]
    }
  ]
}`;
    const lineAuditCurl = `curl "${BASE}/api/v1/lines/rfn-405000/audit?era=2030&grade=good&limit=1&offset=0"`;
    const lineAuditResponse = `{
  "line": { "slug": "rfn-405000", "code": "405000", "name": "Ligne d'Argentan à Granville" },
  "era_year": 2030,
  "audited_at": "2026-11-21T10:45:17+01:00",
  "grades": { "classification": "good", "placement": "good", "accuracy": "good", "trace": "good" },
  "worst_grade": "good",
  "score": 0,
  "length_m": 130866,
  "line_checks": { "reasons": ["no_defect"], "length_ratio": 0.999, "official_length_m": 131000 },
  "segments_total": 34,
  "limit": 1,
  "offset": 0,
  "segments": [
    {
      "segment_id": 9379222,
      "status": "in_service",
      "traffic_type": "mixed",
      "trace_source": "bdtopo",
      "length_m": 36415,
      "worst_grade": "good",
      "score": 0,
      "evidence": { "trace": "…", "accuracy": "…", "placement": "…", "classification": "…" }
    }
  ]
}`;
    const profileCurl = `curl "${BASE}/api/v1/lines/rfn-420000/profile/points?pk_from_m=249000&pk_to_m=250000&max_points=10"`;
    const profileResponse = `{
  "line": { "slug": "rfn-420000", "code": "420000", "name": "Ligne de Paris-Montparnasse à Brest" },
  "count": 4,
  "points": [
    { "pk_m": 249093, "altitude_m": 155.9, "source": "bdtopo", "interpolated": false, "lat": 48.175107, "lng": -0.161683 },
    { "pk_m": 249344, "altitude_m": 155.4, "…": "…" },
    { "pk_m": 249595, "altitude_m": 155.1, "…": "…" },
    { "pk_m": 249845, "altitude_m": 153.9, "…": "…" }
  ],
  "provenance": { "provider": "ign", "name": "IGN (BD TOPO, BD ORTHO historique, RGE ALTI)", "license_status": "verified" }
}`;
    const mediaFacetsCurl = `curl "${BASE}/api/v1/media?featured=true&facets=true&per_page=1"`;
    const mediaFacetsResponse = `{
  "data": [ { "id": 2930, "type": "photo", "title": "…", "license": "…" } ],
  "meta": {
    "per_page": 1,
    "facets": {
      "total": 514,
      "types": { "map": 4, "photo": 458, "postcard": 52 },
      "licenses": [
        { "license": "CC BY-SA 3.0", "count": 240 },
        { "license": "CC BY-SA 4.0", "count": 105 }
      ],
      "authors": [ { "author": "Florian Pépellin", "count": 23 } ]
    }
  }
}`;
    const accidentCurl = `curl "${BASE}/api/v1/accidents/2017-12-14-millas"`;
    const accidentResponse = `{
  "data": {
    "slug": "2017-12-14-millas",
    "name": { "de": "Busunfall von Millas", "en": "Millas crash", "fr": "Accident de Millas" },
    "kind": "level_crossing",
    "date": "2017-12-14",
    "provenance": {
      "date": "wikidata",
      "kind": "wikidata",
      "name": "wikidata",
      "deaths": "bea-tt",
      "injuries": "wikidata",
      "location": "wikidata"
    },
    "imported_at": "2026-11-19T12:54:57+01:00"
  }
}`;
    const auditGraphql = `curl -X POST ${BASE}/graphql \\
  -H "Content-Type: application/json" \\
  -d @- <<'JSON'
{
  "query": "{ segmentAudits(era: 2030, grade: POOR, department: \\"29\\", limit: 1) { era_year total items { line_slug length_m worst_grade reasons } } stationAudits(grade: \\"disagree\\", department: \\"57\\", limit: 1) { total items { slug name placement_grade checks } } mediaFacets(filter: { featured: true }) { total } accident(slug: \\"2017-12-14-millas\\") { name provenance imported_at kind_label(lang: \\"fr\\") department_name } line(slug: \\"rfn-420000\\") { profile { samples(pk_from_m: 249000, pk_to_m: 250000, max_points: 10) { pk_m altitude_m } } } }"
}
JSON`;
    const auditGraphqlResponse = `{
  "data": {
    "segmentAudits": {
      "era_year": 2030,
      "total": 26,
      "items": [
        {
          "line_slug": "archeo-ligne-de-landerneau-a-lesneven",
          "length_m": 53,
          "worst_grade": "POOR",
          "reasons": { "placement": ["median_over_tolerance"], "accuracy": ["hole"], "…": "…" }
        }
      ]
    },
    "stationAudits": {
      "total": 5,
      "items": [
        { "slug": "redange-moselle", "name": "Rédange (Moselle)", "placement_grade": "disagree", "checks": ["freight_only_undated"] }
      ]
    },
    "mediaFacets": { "total": 514 },
    "accident": {
      "name": "Accident de Millas",
      "provenance": { "date": "wikidata", "deaths": "bea-tt", "…": "…" },
      "imported_at": "2026-11-19 12:54:57",
      "kind_label": "Passage à niveau",
      "department_name": "Pyrénées-Orientales"
    },
    "line": { "profile": { "samples": [ { "pk_m": 249093, "altitude_m": 155.9 }, { "pk_m": 249344, "altitude_m": 155.4 } ] } }
  }
}`;
    const auditMcp = `curl -s ${BASE}/mcp \\
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \\
  -H 'MCP-Protocol-Version: 2025-06-18' \\
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"segment_audits","arguments":{"era":2030,"grade":"poor","department":"29","limit":1}}}'`;
    const auditMcpResponse = `{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{\\"era_year\\":2030,\\"total\\":26,\\"limit\\":1,\\"offset\\":0,\\"items\\":[{\\"line_slug\\":\\"archeo-ligne-de-landerneau-a-lesneven\\",\\"segment_id\\":9309008,\\"worst_grade\\":\\"poor\\",\\"…\\":\\"…\\"}],\\"vff_url\\":\\"https://vff-project.org/lines\\",\\"attribution\\":{\\"…\\":\\"…\\"}}"
      }
    ],
    "isError": false
  }
}`;
    const auditErrorCurl = `curl -i "${BASE}/api/v1/audits/segments?grade=excellent&limit=500"`;
    const auditError422 = `HTTP/2 422
content-type: application/json

{
  "message": "The selected grade is invalid. (and 1 more error)",
  "errors": {
    "grade": ["The selected grade is invalid."],
    "limit": ["The limit field must be between 1 and 200."]
  }
}`;
    const auditError429 = `HTTP/2 429
retry-after: 37
x-ratelimit-limit: 300
x-ratelimit-remaining: 0
content-type: application/json

{ "message": "Too Many Attempts." }`;
    // Réponses relevées sur le serveur de dev (POST /graphql avec 6 requêtes dans un lot, puis un corps de 2,1 Mio).
    const auditError400 = `HTTP/2 400
content-type: application/json

{
  "errors": [
    {
      "message": "A batch holds at most 5 operations: split it into several requests.",
      "extensions": { "code": "batch_too_large", "max_batch": 5 }
    }
  ]
}`;
    const auditError413 = `HTTP/2 413
content-type: application/json

{
  "errors": [
    {
      "message": "The request body is larger than 2097152 bytes.",
      "extensions": { "code": "body_too_large", "max_body_bytes": 2097152 }
    }
  ]
}`;
    // Blocs de la section, dans l'ordre : légende (api.audit_examples.<key>) et code.
    const auditBlocks = $derived([
        { key: 'segments', code: auditSegmentsCurl },
        { key: 'segments_response', code: auditSegmentsResponse },
        { key: 'segments_js', code: auditSegmentsJs },
        { key: 'segments_py', code: auditSegmentsPy },
        { key: 'stations', code: auditStationsCurl },
        { key: 'stations_response', code: auditStationsResponse },
        { key: 'line_audit', code: lineAuditCurl },
        { key: 'line_audit_response', code: lineAuditResponse },
        { key: 'profile', code: profileCurl },
        { key: 'profile_response', code: profileResponse },
        { key: 'media_facets', code: mediaFacetsCurl },
        { key: 'media_facets_response', code: mediaFacetsResponse },
        { key: 'accident', code: accidentCurl },
        { key: 'accident_response', code: accidentResponse },
        { key: 'graphql', code: auditGraphql },
        { key: 'graphql_response', code: auditGraphqlResponse },
        { key: 'mcp', code: auditMcp },
        { key: 'mcp_response', code: auditMcpResponse },
        { key: 'errors', code: auditErrorCurl },
        { key: 'error_422', code: auditError422 },
        { key: 'error_429', code: auditError429 },
        { key: 'error_400', code: auditError400 },
        { key: 'error_413', code: auditError413 },
    ]);

    // Section « Authentification et quotas » : mêmes requêtes avec et sans jeton (variable VFF_TOKEN).
    const authCurl = $derived(`# ${$i18n.t('api.auth.curl_without')}
curl -i https://vff-project.org/api/v1/stats
# ${$i18n.t('api.auth.curl_with')}
curl -i -H "Authorization: Bearer $VFF_TOKEN" https://vff-project.org/api/v1/stats`);
    const authJs = [
        'const token = process.env.VFF_TOKEN;',
        'const headers = token ? { Authorization: `Bearer ${token}` } : {};',
        '',
        'async function get(url) {',
        '    const response = await fetch(url, { headers });',
        '    if (response.status === 429) {',
        '        const wait = Number(response.headers.get("Retry-After") ?? 60);',
        '        await new Promise((resolve) => setTimeout(resolve, wait * 1000));',
        '        return get(url);',
        '    }',
        '    if (!response.ok) throw new Error(`HTTP ${response.status}`);',
        '    console.info(',
        '        response.headers.get("X-RateLimit-Limit"),',
        '        response.headers.get("X-RateLimit-Remaining"),',
        '    );',
        '    return response.json();',
        '}',
        '',
        'const stats = await get("https://vff-project.org/api/v1/stats");',
        'console.info(stats.data.lines_count);',
    ].join('\n');
    const authPy = [
        'import os',
        'import time',
        '',
        'import requests',
        '',
        'headers = {}',
        'if os.environ.get("VFF_TOKEN"):',
        '    headers["Authorization"] = f"Bearer {os.environ[\'VFF_TOKEN\']}"',
        '',
        '',
        'def get(url):',
        '    while True:',
        '        response = requests.get(url, headers=headers, timeout=30)',
        '        if response.status_code != 429:',
        '            break',
        '        time.sleep(int(response.headers.get("Retry-After", 60)))',
        '    response.raise_for_status()',
        '    print(response.headers["X-RateLimit-Limit"], response.headers["X-RateLimit-Remaining"])',
        '    return response.json()',
        '',
        '',
        'stats = get("https://vff-project.org/api/v1/stats")',
        'print(stats["data"]["lines_count"])',
    ].join('\n');
    // En-têtes d'une réponse réelle du serveur de développement avec un jeton de membre.
    const authResponse = $derived(`HTTP/1.1 200 OK
Content-Type: application/json
X-RateLimit-Limit: ${rate_limits.member}
X-RateLimit-Remaining: ${rate_limits.member - 1}`);

    // Section « Webhooks » : événements, message reçu et vérification de la signature (exemples
    // exécutés contre une signature produite par App\Services\Webhooks\WebhookSignature).
    const webhookEvents = [
        'contribution.applied',
        'current_era.refreshed',
        'accident.published',
        'material.published',
    ];
    const webhookPayload = `POST /vff/webhook HTTP/1.1
Content-Type: application/json
User-Agent: VFF-Webhooks/1.0
VFF-Event: contribution.applied
VFF-Delivery: 6f1c2e0a-8d4b-4c1e-9a7f-2b5d3e4c1a90
VFF-Signature: t=1795097531,v1=5b0f3c…

{
  "id": "6f1c2e0a-8d4b-4c1e-9a7f-2b5d3e4c1a90",
  "type": "contribution.applied",
  "created_at": "2026-11-19T14:02:11+00:00",
  "data": {
    "resource": "line",
    "id": "rfn-420000",
    "url": "https://vff-project.org/api/v1/lines/rfn-420000"
  }
}`;
    const webhookJs = $derived(
        [
            'import crypto from "node:crypto";',
            '',
            '// rawBody : le corps reçu, tel quel ; header : l’en-tête VFF-Signature.',
            `export function verifyVffSignature(rawBody, header, secret, toleranceSeconds = ${webhooks.tolerance}) {`,
            '    const fields = Object.fromEntries(header.split(",").map((part) => part.trim().split("=")));',
            '    const timestamp = Number(fields.t);',
            '    if (!Number.isInteger(timestamp) || Math.abs(Date.now() / 1000 - timestamp) > toleranceSeconds) {',
            '        return false;',
            '    }',
            '    const expected = crypto.createHmac("sha256", secret).update(`${timestamp}.${rawBody}`).digest("hex");',
            '    const received = Buffer.from(fields.v1 ?? "");',
            '    return received.length === expected.length && crypto.timingSafeEqual(received, Buffer.from(expected));',
            '}',
        ].join('\n'),
    );
    const webhookPy = $derived(
        [
            'import hashlib',
            'import hmac',
            'import time',
            '',
            '',
            `def verify_vff_signature(raw_body: bytes, header: str, secret: str, tolerance: int = ${webhooks.tolerance}) -> bool:`,
            '    fields = dict(part.strip().split("=", 1) for part in header.split(",") if "=" in part)',
            '    try:',
            '        timestamp = int(fields["t"])',
            '    except (KeyError, ValueError):',
            '        return False',
            '    if abs(time.time() - timestamp) > tolerance:',
            '        return False',
            '    expected = hmac.new(secret.encode(), f"{timestamp}.".encode() + raw_body, hashlib.sha256).hexdigest()',
            '    return hmac.compare_digest(expected, fields.get("v1", ""))',
        ].join('\n'),
    );

    const graphqlCurl = `curl -X POST https://vff-project.org/graphql \\
  -H "Content-Type: application/json" \\
  -d '{"query": "{ line(slug: \\"rfn-405000\\") { name code } }"}'`;

    // Mutations de contribution (graphql/schema.graphql, type Mutation) : jeton avec la capacité
    // `contribute`. Corps envoyé par un document « here » pour garder le JSON lisible.
    const contributeCurl = `curl -X POST https://vff-project.org/graphql \\
  -H "Authorization: Bearer $VFF_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d @- <<'JSON'
{
  "query": "mutation ($input: ContributionInput!) { submitContribution(input: $input) { id status approvals approvals_required } }",
  "variables": {
    "input": {
      "segment": {
        "line_code": "405000",
        "era_year": 2030,
        "pk_start_m": 12000,
        "pk_end_m": 18500,
        "changes": { "max_speed_kmh": 140 },
        "sources": ["Référence de votre source : document, page, date"]
      }
    }
  }
}
JSON`;
    const contributeResponse = `{
  "data": {
    "submitContribution": {
      "id": "1287",
      "status": "PENDING",
      "approvals": 0,
      "approvals_required": 2
    }
  }
}`;
    // Image déposée : requête multipart (spécification GraphQL multipart request, scalaire Upload).
    const mediaCurl = `curl -X POST https://vff-project.org/graphql \\
  -H "Authorization: Bearer $VFF_TOKEN" \\
  -F operations='{"query": "mutation ($input: ContributionInput!) { submitContribution(input: $input) { id status } }", "variables": {"input": {"media": {"source": "upload", "target_type": "line", "line_slug": "rfn-610000", "file": null, "media_type": "photo", "title": "Gare de Nontron, côté quai", "author": "Prénom Nom", "license": "cc-by-sa-4.0", "rights_confirmed": true}}}}' \\
  -F map='{"0": ["variables.input.media.file"]}' \\
  -F 0=@gare-de-nontron.jpg`;
    // Matériel sur une ligne : la phrase citée (quote) lève la règle d'électrification (Z 1200, 750 V).
    const materialCurl = `curl -X POST https://vff-project.org/graphql \\
  -H "Authorization: Bearer $VFF_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d @- <<'JSON'
{
  "query": "mutation ($input: ContributionInput!) { submitContribution(input: $input) { id status material { name } } }",
  "variables": {
    "input": {
      "line_material": {
        "line_slug": "rfn-972000",
        "material_slug": "z-1200",
        "circulation": "regular",
        "end_precision": "year",
        "end_date": "1965",
        "source_kind": "web",
        "source_title": "Ligne de Puteaux à Issy-Plaine, Wikipédia",
        "source_url": "https://fr.wikipedia.org/w/index.php?title=Ligne_de_Puteaux_%C3%A0_Issy-Plaine&oldid=241186256",
        "quote": "La ligne est électrifiée par troisième rail en 750 V ; tous les mouvements sont assurés par les automotrices Z 1200."
      }
    }
  }
}
JSON`;
    const reviewCurl = `curl -X POST https://vff-project.org/graphql \\
  -H "Authorization: Bearer $VFF_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"query": "mutation { validateContribution(id: \\"1287\\", decision: REJECT, reason: \\"La source ne couvre pas cette section.\\") { outcome contribution { status review_notes } } }"}'`;
    const contributeError = `{
  "errors": [
    {
      "message": "This API token does not have the \\"contribute\\" ability. Create a token with this ability in your profile.",
      "path": ["submitContribution"],
      "extensions": { "code": "insufficient_scope" }
    }
  ],
  "data": { "submitContribution": null }
}`;
    const contributeAuth = ['token', 'session', 'limit'];
    const contributeErrors = [
        'post_required',
        'unauthenticated',
        'insufficient_scope',
        'csrf_token_mismatch',
        'account_suspended',
        'daily_limit_reached',
        'forbidden',
        'not_found',
        'own_contribution',
        'already_closed',
        'already_approved',
        'application_failed',
        'validation',
    ];
</script>

<svelte:head>
    <title>{$i18n.t('api.meta_title')} - VFF</title>
    <meta name="description" content={$i18n.t('api.meta_description')} />
</svelte:head>

<Layout compactHeader>
    <main>
        <PageHero title={$i18n.t('api.hero.title')} subtitle={$i18n.t('api.hero.subtitle')} />

        <SectionBlock
            title={$i18n.t('api.docs.title')}
            intro={$i18n.t('api.docs.intro')}
            maxWidth="1180px"
        >
            <ul class="docs-grid">
                {#each docs as d (d.key)}
                    <li>
                        <a class="vff-tile doc" href={d.href}>
                            <span class="doc-text">
                                <strong>{$i18n.t(`api.docs.items.${d.key}.title`)}</strong>
                                <span>{$i18n.t(`api.docs.items.${d.key}.text`)}</span>
                                <code class="doc-path">{d.href}</code>
                            </span>
                        </a>
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock kind="alt" title={$i18n.t('api.access.title')} maxWidth="1180px">
            <ul class="vff-points">
                {#each access as a (a.key)}
                    <li>
                        <IconCard
                            title={$i18n.t(`api.access.${a.key}.title`, {
                                count: number(rate_limits.anonymous),
                            })}
                            text={$i18n.t(`api.access.${a.key}.text`)}
                        />
                    </li>
                {/each}
            </ul>
        </SectionBlock>

        <SectionBlock
            id="auth"
            title={$i18n.t('api.auth.title')}
            intro={$i18n.t('api.auth.intro')}
            maxWidth="900px"
        >
            <div class="vff-table-wrap">
                <table class="vff-table quotas">
                    <caption class="vff-sr-only">{$i18n.t('api.auth.table_caption')}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{$i18n.t('api.auth.col_profile')}</th>
                            <th scope="col" class="num">{$i18n.t('api.auth.col_limit')}</th>
                            <th scope="col">{$i18n.t('api.auth.col_counter')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each authProfiles as p (p)}
                            <tr>
                                <th scope="row">{$i18n.t(`api.auth.profiles.${p}.name`)}</th>
                                <td class="num">
                                    {$i18n.t('api.auth.per_minute', {
                                        count: number(rate_limits[p]),
                                    })}
                                </td>
                                <td>{$i18n.t(`api.auth.profiles.${p}.counter`)}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <p class="caption">{$i18n.t('api.auth.shared')}</p>
            <p class="auth-link">
                <a class="vff-btn secondary" href="/profile#api-tokens-title"
                    >{$i18n.t('api.auth.create')}</a
                >
            </p>

            <h3 class="group-title">{$i18n.t('api.auth.headers_title')}</h3>
            <ul class="auth-headers">
                {#each ['limit', 'remaining', 'retry', 'invalid', 'suspended'] as h (h)}
                    <li>{$i18n.t(`api.auth.headers.${h}`)}</li>
                {/each}
            </ul>

            <h3 class="group-title">{$i18n.t('api.auth.examples_title')}</h3>
            <p class="caption">{$i18n.t('api.auth.examples_intro')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{authCurl}</code></pre>
            <p class="caption">{$i18n.t('api.auth.response_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{authResponse}</code></pre>
            <p class="caption">{$i18n.t('api.auth.js_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{authJs}</code></pre>
            <p class="caption">{$i18n.t('api.auth.py_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{authPy}</code></pre>
        </SectionBlock>

        <SectionBlock
            id="webhooks"
            kind="alt"
            title={$i18n.t('api.webhooks.title')}
            intro={$i18n.t('api.webhooks.intro')}
            maxWidth="900px"
        >
            <p class="auth-link">
                <a class="vff-btn secondary" href="/profile#webhooks-title"
                    >{$i18n.t('api.webhooks.create')}</a
                >
            </p>

            <h3 class="group-title">{$i18n.t('api.webhooks.events_title')}</h3>
            <dl class="webhook-events">
                {#each webhookEvents as e (e)}
                    <div>
                        <dt><code>{e}</code></dt>
                        <dd>{$i18n.t(`webhooks.events.${e.replace('.', '_')}.hint`)}</dd>
                    </div>
                {/each}
            </dl>

            <h3 class="group-title">{$i18n.t('api.webhooks.payload_title')}</h3>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{webhookPayload}</code></pre>
            <p class="caption">{$i18n.t('api.webhooks.payload_intro')}</p>

            <h3 class="group-title">{$i18n.t('api.webhooks.headers_title')}</h3>
            <ul class="auth-headers">
                {#each ['event', 'delivery', 'signature'] as h (h)}
                    <li>{$i18n.t(`api.webhooks.headers.${h}`)}</li>
                {/each}
            </ul>

            <h3 class="group-title">{$i18n.t('api.webhooks.verify_title')}</h3>
            <p class="caption">
                {$i18n.t('api.webhooks.verify_intro', { tolerance: webhooks.tolerance })}
            </p>
            <p class="caption">{$i18n.t('api.webhooks.js_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{webhookJs}</code></pre>
            <p class="caption">{$i18n.t('api.webhooks.py_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{webhookPy}</code></pre>

            <h3 class="group-title">{$i18n.t('api.webhooks.delivery_title')}</h3>
            <p class="caption">
                {$i18n.t('api.webhooks.delivery_text', {
                    timeout: webhooks.timeout,
                    attempts: webhooks.attempts,
                    max: webhooks.disable_after,
                })}
            </p>
            <p class="caption">{$i18n.t('api.webhooks.security_text')}</p>
        </SectionBlock>

        <SectionBlock
            title={$i18n.t('api.endpoints.title')}
            intro={$i18n.t('api.endpoints.intro')}
            maxWidth="1180px"
        >
            {#each endpointGroups as g (g.key)}
                <h3 class="group-title" id="endpoints-{g.key}">
                    {$i18n.t(`api.endpoints.groups.${g.key}`)}
                </h3>
                <ul class="endpoints" aria-labelledby="endpoints-{g.key}">
                    {#each g.items as e (e.key)}
                        <li>
                            <code class="path"
                                >{#each segments(e.path) as s (s)}{s}<wbr />{/each}</code
                            >
                            <span class="desc"
                                >{$i18n.t(`api.endpoints.items.${e.key}`)}
                                {#if e.doc}<a class="vff-link" href={e.doc}
                                        >{$i18n.t('api.endpoints.reference')}</a
                                    >{/if}</span
                            >
                        </li>
                    {/each}
                </ul>
            {/each}
        </SectionBlock>

        <SectionBlock
            kind="alt"
            title={$i18n.t('api.example.title')}
            intro={$i18n.t('api.example.intro')}
            maxWidth="900px"
        >
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{curlExample}</code></pre>
            <p class="caption">{$i18n.t('api.example.js_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{jsExample}</code></pre>
            <p class="caption">{$i18n.t('api.example.py_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{pyExample}</code></pre>
            <p class="caption">{$i18n.t('api.example.response')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{responseExample}</code></pre>
        </SectionBlock>

        <SectionBlock
            title={$i18n.t('api.graphql.title')}
            intro={$i18n.t('api.graphql.text')}
            maxWidth="900px"
        >
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{graphqlExample}</code></pre>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{graphqlCurl}</code></pre>
            <p>{$i18n.t('api.graphql.limits')}</p>
            <p class="links">
                <a class="vff-link" href="/docs/graphql"
                    >{$i18n.t('api.docs.items.graphql.title')}</a
                >
                <a class="vff-link" href="/graphiql">{$i18n.t('api.docs.items.graphiql.title')}</a>
            </p>
        </SectionBlock>

        <SectionBlock
            id="audit-examples"
            kind="alt"
            title={$i18n.t('api.audit_examples.title')}
            intro={$i18n.t('api.audit_examples.intro')}
            maxWidth="900px"
        >
            {#each auditBlocks as block (block.key)}
                <p class="caption">{$i18n.t(`api.audit_examples.${block.key}`)}</p>
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
                <pre
                    class="code"
                    role="group"
                    tabindex="0"
                    aria-label={$i18n.t('api.example.code_label')}><code>{block.code}</code></pre>
            {/each}
        </SectionBlock>

        <SectionBlock
            id="contribute"
            title={$i18n.t('api.contribute.title')}
            intro={$i18n.t('api.contribute.intro')}
            maxWidth="900px"
        >
            <h3 class="group-title">{$i18n.t('api.contribute.auth_title')}</h3>
            <ul class="auth-headers">
                {#each contributeAuth as a (a)}
                    <li>{$i18n.t(`api.contribute.auth.${a}`)}</li>
                {/each}
            </ul>

            <h3 class="group-title">{$i18n.t('api.contribute.submit_title')}</h3>
            <p class="caption">{$i18n.t('api.contribute.submit_intro')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{contributeCurl}</code></pre>
            <p class="caption">{$i18n.t('api.contribute.response_caption')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{contributeResponse}</code
                ></pre>

            <h3 class="group-title">{$i18n.t('api.contribute.media_title')}</h3>
            <p class="caption">{$i18n.t('api.contribute.media_intro')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{mediaCurl}</code></pre>

            <h3 class="group-title">{$i18n.t('api.contribute.material_title')}</h3>
            <p class="caption">{$i18n.t('api.contribute.material_intro')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{materialCurl}</code></pre>

            <h3 class="group-title">{$i18n.t('api.contribute.review_title')}</h3>
            <p class="caption">{$i18n.t('api.contribute.review_intro')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{reviewCurl}</code></pre>

            <h3 class="group-title" id="contribute-errors">
                {$i18n.t('api.contribute.errors_title')}
            </h3>
            <p class="caption">{$i18n.t('api.contribute.errors_intro')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{contributeError}</code></pre>
            <ul class="endpoints tools" aria-labelledby="contribute-errors">
                {#each contributeErrors as code (code)}
                    <li>
                        <code class="path">{code}</code>
                        <span class="desc">{$i18n.t(`api.contribute.errors.${code}`)}</span>
                    </li>
                {/each}
            </ul>
            <p class="links">
                <a class="vff-link" href="/docs/graphql">{$i18n.t('api.contribute.reference')}</a>
            </p>
        </SectionBlock>

        <SectionBlock
            kind="alt"
            id="mcp"
            title={$i18n.t('api.mcp.title')}
            intro={$i18n.t('api.mcp.intro')}
            maxWidth="900px"
        >
            <dl class="facts" aria-label={$i18n.t('api.mcp.facts_label')}>
                <div>
                    <dt>{$i18n.t('api.mcp.address_label')}</dt>
                    <dd>
                        <code class="path">{MCP_URL}</code>
                        <span class="note">{$i18n.t('api.mcp.address_note')}</span>
                    </dd>
                </div>
                <div>
                    <dt>{$i18n.t('api.mcp.access_label')}</dt>
                    <dd>{$i18n.t('api.mcp.access')}</dd>
                </div>
                <div>
                    <dt>{$i18n.t('api.mcp.limit_label')}</dt>
                    <dd>
                        {$i18n.t('api.mcp.limit', { count: number(rate_limits.anonymous) })}
                    </dd>
                </div>
                <div>
                    <dt>{$i18n.t('api.mcp.lang_label')}</dt>
                    <dd>{$i18n.t('api.mcp.lang')}</dd>
                </div>
            </dl>

            <h3 class="group-title">{$i18n.t('api.mcp.install_title')}</h3>
            <p class="caption">{$i18n.t('api.mcp.install_cli')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{mcpCli}</code></pre>
            <p class="caption">{$i18n.t('api.mcp.install_config')}</p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex (zone défilable : atteignable au clavier) -->
            <pre
                class="code"
                role="group"
                tabindex="0"
                aria-label={$i18n.t('api.example.code_label')}><code>{mcpConfig}</code></pre>
            <p class="caption">{$i18n.t('api.mcp.install_relay')}</p>

            <h3 class="group-title" id="mcp-tools">{$i18n.t('api.mcp.tools_title')}</h3>
            <ul class="endpoints tools" aria-labelledby="mcp-tools">
                {#each mcpTools as tool (tool)}
                    <li>
                        <code class="path">{tool}</code>
                        <span class="desc">{$i18n.t(`api.mcp.tools.${tool}`)}</span>
                    </li>
                {/each}
            </ul>

            <h3 class="group-title" id="mcp-prompts">{$i18n.t('api.mcp.prompts_title')}</h3>
            <p class="caption">{$i18n.t('api.mcp.prompts_intro')}</p>
            <ul class="endpoints tools" aria-labelledby="mcp-prompts">
                {#each mcpPrompts as prompt (prompt)}
                    <li>
                        <code class="path">{prompt}</code>
                        <span class="desc">{$i18n.t(`api.mcp.prompts.${prompt}`)}</span>
                    </li>
                {/each}
            </ul>

            <h3 class="group-title">{$i18n.t('api.mcp.licence_title')}</h3>
            <p class="caption">{$i18n.t('api.mcp.licence_text')}</p>
        </SectionBlock>

        <SectionBlock
            kind="cta"
            title={$i18n.t('api.cta.title')}
            intro={$i18n.t('api.cta.text')}
            maxWidth="640px"
        >
            {#snippet actions()}
                <a
                    href="https://discord.com/invite/6SwTfXBx4h"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vff-btn primary"
                >
                    <i class="bi bi-discord" aria-hidden="true"></i>
                    {$i18n.t('api.cta.discord')}<i
                        class="bi bi-box-arrow-up-right vff-ext"
                        aria-hidden="true"
                    ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span>
                </a>
                <a
                    href="https://github.com/UnxWares/VFF-Project"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vff-btn secondary"
                >
                    <i class="bi bi-github" aria-hidden="true"></i>
                    {$i18n.t('api.cta.github')}<i
                        class="bi bi-box-arrow-up-right vff-ext"
                        aria-hidden="true"
                    ></i><span class="vff-sr-only"> ({$i18n.t('common.new_tab')})</span>
                </a>
            {/snippet}
        </SectionBlock>
    </main>
</Layout>

<style>
    /* Documentations : entrées cliquables en tuiles, trois colonnes sur ordinateur. */
    .docs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
        gap: 16px 24px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .docs-grid li {
        display: flex;
    }
    .doc {
        display: flex;
        flex: 1;
        gap: 14px;
        align-items: flex-start;
        padding: 18px 20px;
    }
    .doc-text {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .doc-text strong {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-md);
        font-weight: 600;
        color: var(--vff-text);
    }
    .doc-path {
        margin-top: 2px;
        font-family: var(--api-mono);
        font-size: var(--vff-fs-xs);
        color: var(--vff-text-muted);
        overflow-wrap: anywhere;
    }

    /* Points d'accès : chemin à gauche, description alignée sur une même colonne, filets entre les lignes. */
    .endpoints {
        margin: 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--vff-border-strong);
    }
    .endpoints li {
        display: grid;
        grid-template-columns: minmax(0, 30rem) minmax(0, 1fr);
        gap: 8px 32px;
        align-items: baseline;
        padding: 14px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    /* Outils MCP : noms courts, colonne plus étroite. */
    .tools li {
        grid-template-columns: minmax(0, 13rem) minmax(0, 1fr);
    }
    .path {
        font-family: var(--api-mono);
        font-size: var(--vff-fs-sm);
        line-height: 1.5;
        color: var(--vff-text);
    }
    .desc {
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }

    /* Sous-titres des groupes de points d'accès et des parties de la section MCP. */
    .group-title {
        margin: var(--vff-block-space) 0 var(--vff-space-3);
    }
    .group-title:first-child {
        margin-top: 0;
    }

    /* Serveur MCP : adresse, accès, limites, langues, en rangées à filets. */
    .facts {
        margin: 0;
        border-top: 1px solid var(--vff-border-strong);
    }
    .facts > div {
        display: grid;
        grid-template-columns: minmax(0, 10rem) minmax(0, 1fr);
        gap: 4px 32px;
        padding: 14px 0;
        border-bottom: 1px solid var(--vff-border);
    }
    .facts dt {
        font-family: var(--vff-font-heading);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
        color: var(--vff-text);
    }
    .facts dd {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 12px;
        align-items: baseline;
        margin: 0;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .facts .path {
        overflow-wrap: anywhere;
    }
    .note {
        font-size: var(--vff-fs-sm);
    }

    .caption {
        max-width: var(--vff-measure);
        margin: var(--vff-space-5) 0 var(--vff-space-3);
        font-size: var(--vff-fs-md);
        line-height: 1.5;
        color: var(--vff-text-muted);
    }
    .code {
        margin: 0 0 var(--vff-space-4);
        padding: 16px 20px;
        overflow-x: auto;
        border: 1px solid var(--vff-border-strong);
        border-radius: var(--vff-radius-md);
        background-color: var(--vff-bg-elevated);
        color: var(--vff-text);
        font-family: var(--api-mono);
        font-size: var(--vff-fs-sm);
        line-height: 1.6;
    }
    .code code {
        display: block;
        font: inherit;
        white-space: pre;
    }
    /* Authentification et quotas : tableau des limites, en-têtes et erreurs. */
    .quotas {
        font-size: var(--vff-fs-md);
    }
    .quotas tbody th {
        font-weight: 600;
    }
    /* Téléphone : chaque profil en bloc (nom, limite, compteur), sans défilement horizontal. */
    @media (max-width: 600px) {
        .quotas thead {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
        }
        .quotas tr {
            display: block;
            padding: 12px 20px;
            border-bottom: 1px solid var(--vff-border);
        }
        .quotas tbody tr:last-child {
            border-bottom: 0;
        }
        .vff-table-wrap .quotas :is(th, td) {
            display: block;
            padding: 2px 0;
            border: 0;
            text-align: left;
        }
    }
    .auth-link {
        margin: var(--vff-space-4) 0 var(--vff-block-space);
    }
    .auth-headers {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-2);
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-block-space);
        padding-left: 1.2em;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .webhook-events {
        display: flex;
        flex-direction: column;
        gap: var(--vff-space-3);
        max-width: var(--vff-measure);
        margin: 0 0 var(--vff-block-space);
    }
    .webhook-events dt code {
        font-family: var(--api-mono);
        font-size: var(--vff-fs-sm);
        font-weight: 600;
    }
    .webhook-events dd {
        margin: 2px 0 0;
        font-size: var(--vff-fs-md);
        line-height: 1.5;
    }
    .links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 24px;
        margin: var(--vff-space-5) 0 0;
    }

    main {
        --api-mono:
            'SF Mono', 'Cascadia Code', 'JetBrains Mono', Consolas, 'Liberation Mono', monospace;
    }

    @media (max-width: 900px) {
        .endpoints li,
        .tools li,
        .facts > div {
            grid-template-columns: minmax(0, 1fr);
        }
    }
    @media (max-width: 600px) {
        .code {
            padding: 14px 16px;
        }
    }
</style>
