# VFF GraphQL API

*[Français]({{siteRoot}}/guide/introduction) · English · [Deutsch]({{siteRoot}}/guide-de/grundlagen)*

**VFF - Voies Ferrées de France** (French Railway Lines) is the collaborative encyclopedia of French railways. Its GraphQL API gives access to lines, stations, track sections, chronologies, signalling, rolling stock, accidents and media, to the comparison of two eras and to historical route finding. The reference of every query and type is generated from the schema; this guide explains how to use it. The example queries are in [Getting started]({{siteRoot}}/guide-en/getting-started).

The descriptions in the reference itself are written in French.

## Endpoint

- URL: `POST {{origin}}/graphql`, JSON body `{"query": "…", "variables": {…}}`, header `Content-Type: application/json`.
- Read-only for anonymous visitors. Contribution mutations need a member account and are not covered by this guide (see the `Mutation` type in the reference).
- Live experiments, with completion and built-in documentation: [GraphiQL]({{origin}}/graphiql).
- Full schema in SDL: [/docs/schema.graphql]({{origin}}/docs/schema.graphql). Introspection is open, so a typed client can generate itself.
- A `POST` whose body is a list of queries is accepted, up to 5 operations.

```bash
curl -X POST {{origin}}/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ stats { lines_count stations_count } }"}'
```

## Authentication and quotas

GraphQL is called without a token. An optional **personal token** raises the rate limit: a member creates it from their [profile]({{origin}}/profile) (section "API access tokens"). Like anonymous access, it can only read, and it is shown once. It is sent in the header `Authorization: Bearer <token>`.

| Profile | Limit | Counted |
| --- | --- | --- |
| No token | 300 requests per minute | per IP address |
| Member token | 1,200 requests per minute | per account, all its tokens together |
| Partner account token | 6,000 requests per minute | per account (profile granted by the VFF team) |

The counter is shared by GraphQL, the REST API and the MCP server. Every response carries `X-RateLimit-Limit` (the caller's limit) and `X-RateLimit-Remaining` (requests left in the minute).

```javascript
const token = process.env.VFF_TOKEN;
const response = await fetch("{{origin}}/graphql", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ query: "{ stats { lines_count } }" }),
});
console.info(response.status, response.headers.get("X-RateLimit-Remaining"));
console.info((await response.json()).data.stats.lines_count);
```

```python
import os

import requests

headers = {}
if os.environ.get("VFF_TOKEN"):
    headers["Authorization"] = f"Bearer {os.environ['VFF_TOKEN']}"
response = requests.post(
    "{{origin}}/graphql",
    json={"query": "{ stats { lines_count } }"},
    headers=headers,
    timeout=30,
)
print(response.status_code, response.headers["X-RateLimit-Remaining"])
print(response.json()["data"]["stats"]["lines_count"])
```

## Request limits

These refusals happen before execution, and the server reads no more than 2 MiB of body.

| Limit | Value | Refusal |
| --- | --- | --- |
| Rate | depends on the profile above | HTTP 429 and a `Retry-After` header (seconds to wait) |
| Complexity | 5,000 points | error `query_too_complex` |
| Depth | 15 levels | error `query_too_deep` |
| Batch of queries | 5 operations | HTTP 400, `extensions.code` `batch_too_large` |
| Request body | 2 MiB | HTTP 413, `extensions.code` `body_too_large` |
| Page size | 100 items (`first`) | validation error |

A field is worth 1 point plus its subfields. A paginated list multiplies its subfields by `first`, computed fields (`stops`, `signalling`, `pk`, `search`, `stats`…) cost 10 more points and heavy fields (`sheet`, `profile`, a line's `audit`, `route`, `eraComparison`) 250. The full scale is in the description of the queries in the reference. Ask for the fields you need rather than the whole sheet, and split a large query into several.

## Eras and units

The network is described by **decennial eras**, from 1830 to 2030. Each era is a snapshot on **31 December** of its year: the 1930 era shows the network on 31/12/1930. The **2030** era is **today's** network, recomputed daily. `era` arguments expect that year (`era: 1930`); without `era`, you get the current network.

Event dates stay exact: read `LineEvent.event_date` together with `date_precision` (`day`: dd/mm/yyyy, `month`: mm/yyyy, `year`: yyyy).

Distances and kilometre points (PK) are in **metres** (`pk_m: 373450` = PK 373+450), speeds in km/h, voltages in volts, gauges in millimetres, altitudes in metres above sea level (NGF), WGS 84 coordinates in decimal degrees.

## Pagination

Three forms, depending on the list. The order is fixed; it reverses with `filter: { order: DESC }` where that exists.

- **Cursor-based** (`lineEvents`, `media`, `materials`, `lineDirectory`, `stationDirectory`, `Line.media`, `Station.media`): arguments `first` (20 or 25 by default, 100 at most) and `after`. Results are in `edges { node }` and the next page's cursor in `pageInfo { hasNextPage endCursor }`; pass `endCursor` as `after` while `hasNextPage` is true. The directories return `next_cursor`, the same cursor as `meta.next_cursor` in the REST API.
- **Page-based** (`lines`, `stations`, `accidents`, `levelCrossings`): arguments `first` and `page` (from 1); results in `data`, counters in `paginatorInfo` (`total`, `lastPage`, `hasMorePages`).
- **By `limit` and `offset`**: network audits (`segmentAudits`, `stationAudits`), 50 by default and 200 at most.

Text filters ignore case and accept the `%` wildcard (`name: "%brest%"`). An invalid filter value (unknown type, malformed date, unknown line) returns a validation error.

## Errors

| Situation | Response |
| --- | --- |
| Invalid query, filter out of range | HTTP 200 and an `errors` array (GraphQL convention): `message` and, for a validation, `extensions.validation` details each faulty field |
| Unknown id or slug (`line(slug: …)`, `lineEvent(id: …)`) | `null`, not an error |
| Rate limit reached | HTTP 429 and `Retry-After` |
| Unknown, revoked or expired token | HTTP 401 `{"error": "invalid_token"}`; remove the header to go back to anonymous access |
| Token of a suspended account | HTTP 403 `{"error": "account_suspended"}` |
| Query too costly, too deep, batch or body too large | see "Request limits" |

`data` can be partial when only one branch fails: always read `errors` before using `data`.

## Versions and deprecations

The API has no version number in its URL: the schema grows by additions. A field is never removed without first being marked `@deprecated` in the schema (with the reason and the replacement field, shown in the reference, which strikes the name through) and announced in the repository's change log. A test compares the API with a snapshot of the contract and fails if a field, argument or value disappears without that announcement. A client should therefore:

- ignore fields it does not know and accept new values in text fields that list codes (`kind`, `status`, `provider`…);
- treat as nullable every field whose type is not followed by `!`;
- watch for deprecation warnings from its tooling (GraphiQL and client generators display them).

The same data exists in REST (`/api/v1`, GeoJSON, vector tiles, GTFS export) and through the MCP server: see [API access]({{origin}}/api), the [Scalar]({{origin}}/docs/api) reference and the [OpenAPI]({{origin}}/docs/api.json) specification.

## Licences and sources

Open data: credit "VFF - Voies Ferrées de France" with a link to [vff-project.org](https://vff-project.org). The data aggregates several sources whose licences apply: SNCF Open Data and OpenStreetMap (ODbL), IGN BD TOPO (Licence Ouverte 2.0), OpenHistoricalMap and Wikidata (CC0), Wikipedia (CC BY-SA). The provenance of every track (`LineSegment.provenance`), event (`LineEvent.provenance`, `source_url`) and media item (`license`, `attribution`) is in the response: keep it when you republish a piece of data.
