# GraphQL-API von VFF

*[Français]({{siteRoot}}/guide/introduction) · [English]({{siteRoot}}/guide-en/introduction) · Deutsch*

**VFF - Voies Ferrées de France** (Französische Bahnstrecken) ist die gemeinschaftliche Enzyklopädie der französischen Eisenbahn. Die GraphQL-API bietet Zugriff auf Strecken, Bahnhöfe, Segmente, Chroniken, Signalisierung, Fahrzeuge, Unfälle und Medien, auf den Vergleich zweier Epochen und auf die historische Routenberechnung. Die Referenz jeder Abfrage und jedes Typs wird aus dem Schema erzeugt; dieser Leitfaden erklärt, wie Sie sie nutzen. Die Beispielabfragen stehen unter [Erste Schritte]({{siteRoot}}/guide-de/erste-schritte).

Die Beschreibungen in der Referenz selbst sind auf Französisch verfasst.

## Endpunkt

- URL: `POST {{origin}}/graphql`, JSON-Body `{"query": "…", "variables": {…}}`, Header `Content-Type: application/json`.
- Für anonyme Besucher nur lesend. Mutationen für Beiträge erfordern ein Mitgliedskonto und werden in diesem Leitfaden nicht behandelt (siehe Typ `Mutation` in der Referenz).
- Live-Tests mit Autovervollständigung und eingebauter Dokumentation: [GraphiQL]({{origin}}/graphiql).
- Vollständiges Schema im SDL-Format: [/docs/schema.graphql]({{origin}}/docs/schema.graphql). Die Introspektion ist offen, ein typisierter Client kann sich also selbst erzeugen.
- Ein `POST`, dessen Body eine Liste von Abfragen ist, wird akzeptiert, bis zu 5 Operationen.

```bash
curl -X POST {{origin}}/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ stats { lines_count stations_count } }"}'
```

## Authentifizierung und Kontingente

GraphQL wird ohne Token aufgerufen. Ein optionales **persönliches Token** erhöht das Anfragelimit: Ein Mitglied erstellt es in seinem [Profil]({{origin}}/profile) (Abschnitt »API-Zugriffstoken«). Wie der anonyme Zugriff kann es nur lesen und wird nur einmal angezeigt. Es wird im Header `Authorization: Bearer <Token>` gesendet.

| Profil | Limit | Zähler |
| --- | --- | --- |
| Ohne Token | 300 Anfragen pro Minute | pro IP-Adresse |
| Token eines Mitglieds | 1.200 Anfragen pro Minute | pro Konto, alle Tokens zusammen |
| Token eines Partnerkontos | 6.000 Anfragen pro Minute | pro Konto (Profil vom VFF-Team vergeben) |

Der Zähler gilt gemeinsam für GraphQL, die REST-API und den MCP-Server. Jede Antwort enthält `X-RateLimit-Limit` (das Limit des Aufrufers) und `X-RateLimit-Remaining` (verbleibende Anfragen in der Minute).

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

## Grenzen einer Abfrage

Diese Ablehnungen erfolgen vor der Ausführung, und der Server liest höchstens 2 MiB Body.

| Grenze | Wert | Ablehnung |
| --- | --- | --- |
| Rate | je nach Profil oben | HTTP 429 und Header `Retry-After` (zu wartende Sekunden) |
| Komplexität | 5.000 Punkte | Fehler `query_too_complex` |
| Tiefe | 15 Ebenen | Fehler `query_too_deep` |
| Abfrage-Batch | 5 Operationen | HTTP 400, `extensions.code` `batch_too_large` |
| Body der Anfrage | 2 MiB | HTTP 413, `extensions.code` `body_too_large` |
| Seitengröße | 100 Elemente (`first`) | Validierungsfehler |

Ein Feld kostet 1 Punkt plus seine Unterfelder. Eine seitenweise Liste multipliziert ihre Unterfelder mit `first`, berechnete Felder (`stops`, `signalling`, `pk`, `search`, `stats` …) kosten 10 Punkte mehr und schwere Felder (`sheet`, `profile`, `audit` einer Strecke, `route`, `eraComparison`) 250. Die vollständige Staffelung steht in der Beschreibung der Abfragen in der Referenz. Fragen Sie nur die Felder ab, die Sie brauchen, nicht das ganze Datenblatt, und teilen Sie eine große Abfrage auf.

## Epochen und Einheiten

Das Netz wird in **Jahrzehnt-Epochen** von 1830 bis 2030 beschrieben. Jede Epoche ist eine Momentaufnahme zum **31.12.** ihres Jahres: Die Epoche 1930 zeigt das Netz am 31.12.1930. Die Epoche **2030** bezeichnet das Netz von **heute**, tagesaktuell neu berechnet. Die Argumente `era` erwarten dieses Jahr (`era: 1930`); ohne `era` erhalten Sie das aktuelle Netz.

Ereignisdaten bleiben exakt: `LineEvent.event_date` wird zusammen mit `date_precision` gelesen (`day`: TT.MM.JJJJ, `month`: MM.JJJJ, `year`: JJJJ).

Entfernungen und Streckenkilometer (PK) in **Metern** (`pk_m: 373450` = PK 373+450), Geschwindigkeiten in km/h, Spannungen in Volt, Spurweiten in Millimetern, Höhen in Metern über NGF, WGS-84-Koordinaten in Dezimalgrad.

## Paginierung

Drei Formen, je nach Liste. Die Reihenfolge ist fest; wo vorhanden, kehrt `filter: { order: DESC }` sie um.

- **Per Cursor** (`lineEvents`, `media`, `materials`, `lineDirectory`, `stationDirectory`, `Line.media`, `Station.media`): Argumente `first` (standardmäßig 20 oder 25, höchstens 100) und `after`. Die Ergebnisse stehen in `edges { node }`, der Cursor der nächsten Seite in `pageInfo { hasNextPage endCursor }`; übergeben Sie `endCursor` als `after`, solange `hasNextPage` wahr ist. Die Verzeichnisse liefern `next_cursor`, denselben Cursor wie `meta.next_cursor` der REST-API.
- **Per Seite** (`lines`, `stations`, `accidents`, `levelCrossings`): Argumente `first` und `page` (ab 1); Ergebnisse in `data`, Zähler in `paginatorInfo` (`total`, `lastPage`, `hasMorePages`).
- **Per `limit` und `offset`**: Netzprüfungen (`segmentAudits`, `stationAudits`), standardmäßig 50, höchstens 200.

Textfilter ignorieren die Groß- und Kleinschreibung und akzeptieren den Platzhalter `%` (`name: "%brest%"`). Ein ungültiger Filterwert (unbekannter Typ, fehlerhaftes Datum, unbekannte Strecke) liefert einen Validierungsfehler.

## Fehler

| Situation | Antwort |
| --- | --- |
| Ungültige Abfrage, Filter außerhalb der Grenzen | HTTP 200 und ein Array `errors` (GraphQL-Konvention): `message` und bei einer Validierung beschreibt `extensions.validation` jedes fehlerhafte Feld |
| Unbekannte ID oder unbekannter Slug (`line(slug: …)`, `lineEvent(id: …)`) | `null`, kein Fehler |
| Anfragelimit erreicht | HTTP 429 und `Retry-After` |
| Unbekanntes, widerrufenes oder abgelaufenes Token | HTTP 401 `{"error": "invalid_token"}`; entfernen Sie den Header, um zum anonymen Zugriff zurückzukehren |
| Token eines gesperrten Kontos | HTTP 403 `{"error": "account_suspended"}` |
| Abfrage zu teuer, zu tief, Batch oder Body zu groß | siehe »Grenzen einer Abfrage« |

`data` kann unvollständig sein, wenn nur ein Zweig fehlschlägt: Lesen Sie immer `errors`, bevor Sie `data` verwenden.

## Versionen und Abkündigungen

Die API hat keine Versionsnummer in der URL: Das Schema wächst durch Ergänzungen. Ein Feld wird nie entfernt, ohne zuvor im Schema als `@deprecated` markiert (mit Grund und Ersatzfeld, in der Referenz angezeigt, die den Namen durchstreicht) und im Änderungsprotokoll des Repositorys angekündigt worden zu sein. Ein Test vergleicht die API mit einem Schnappschuss des Vertrags und schlägt fehl, wenn ein Feld, ein Argument oder ein Wert ohne diese Ankündigung verschwindet. Ein Client sollte daher:

- Felder ignorieren, die er nicht kennt, und neue Werte in Textfeldern akzeptieren, die Codes aufzählen (`kind`, `status`, `provider` …);
- jedes Feld als nullbar behandeln, dessen Typ nicht auf `!` endet;
- auf Abkündigungswarnungen seiner Werkzeuge achten (GraphiQL und Client-Generatoren zeigen sie an).

Dieselben Daten gibt es in REST (`/api/v1`, GeoJSON, Vektorkacheln, GTFS-Export) und über den MCP-Server: siehe [API-Zugang]({{origin}}/api), die [Scalar]({{origin}}/docs/api)-Referenz und die [OpenAPI]({{origin}}/docs/api.json)-Spezifikation.

## Lizenzen und Quellen

Offene Daten: Nennen Sie »VFF - Voies Ferrées de France« mit einem Link auf [vff-project.org](https://vff-project.org). Die Daten vereinen mehrere Quellen, deren Lizenzen gelten: SNCF Open Data und OpenStreetMap (ODbL), IGN BD TOPO (Licence Ouverte 2.0), OpenHistoricalMap und Wikidata (CC0), Wikipedia (CC BY-SA). Die Herkunft jedes Verlaufs (`LineSegment.provenance`), jedes Ereignisses (`LineEvent.provenance`, `source_url`) und jedes Mediums (`license`, `attribution`) steht in der Antwort: Übernehmen Sie sie, wenn Sie Daten weiterveröffentlichen.
