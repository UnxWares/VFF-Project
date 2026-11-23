# Erste Schritte

*[Français]({{siteRoot}}/guide/premiers-pas) · [English]({{siteRoot}}/guide-en/getting-started) · Deutsch*

Zehn vollständige Abfragen zum Einfügen in [GraphiQL]({{origin}}/graphiql) oder zum Senden als `POST` an `{{origin}}/graphql`. Jede wird bei jeder Schemaänderung gegen das Schema geprüft. Die Kennungen (`rfn-420000`, `brest`) sind die `slug` der Seiten der Website: Der Slug einer Strecke oder eines Bahnhofs steht in der Adresse seiner Seite.

Die Abfragen sind benannt und beziehen ihre Werte aus Variablen, wo sie welche haben: Diese Form ist in einem Programm vorzuziehen, weil der Abfragetext von Aufruf zu Aufruf gleich bleibt.

## 1. Eine Strecke und ihre Segmente zu einem Datum

Die Segmente (`segments`) einer Strecke in einer Epoche: Zustand, Verkehr, Elektrifizierung, Geschwindigkeit, Kilometerpunkte und Herkunft jedes Segments. Ohne `era` liefert die Abfrage alle Epochen.

{{query:01-line-at-date}}

Variablen:

{{variables:01-line-at-date}}

`status` sagt, ob das Segment in Betrieb ist, mit liegendem Gleis stillgelegt oder am Ende dieses Jahres abgebaut. `provenance` nennt den Lieferanten des Verlaufs und seine Lizenz.

## 2. Die Bahnhöfe einer Strecke in einer Epoche

`stops` listet die Bahnhöfe in Fahrtreihenfolge auf, mit ihrem Kilometerpunkt (`pk_label`) und ihrem Zustand in der angefragten Epoche (`effective_status`). Ohne `era` gilt heute.

{{query:02-line-stations}}

## 3. Ein Bahnhof und seine Geschichte je Epoche

Das Datenblatt eines Bahnhofs nennt seinen Zustand in jeder Epoche (`eras`), die Strecken, die ihn bedienen, und seine Nachbarn.

{{query:03-station-eras}}

`opened_at` wird mit der Genauigkeit des Datums gelesen: Ein nur jahresgenau datierter Bahnhof hat keinen Tag.

## 4. Das Längenprofil einer Strecke

Höhe entlang des Verlaufs, mit Extremwerten, kumulierten Höhenunterschieden und maximaler Steigung in Promille. `max_points` verschlankt die Antwort: Das Profil behält beide Enden, den tiefsten und den höchsten Punkt. `profile` ist null, solange das Profil der Strecke nicht berechnet ist.

{{query:04-line-profile}}

## 5. Die Unfälle einer Strecke

Datum und seine Genauigkeit, Art, Opferzahlen und die gelesenen Quellen mit ihrer Lizenz. Ein Datenblatt, dessen `review_status` `to_review` ist, hat einen unsicheren Punkt: Stellen Sie es als solches dar. Um alle Unfälle zu durchsuchen, siehe `accidents(filter: …)`.

{{query:05-line-accidents}}

## 6. Die Fahrzeuge einer Strecke

`material_links` behält den Beleg jeder Verbindung zwischen einer Strecke und einer Fahrzeugbaureihe: Quelle, Zuverlässigkeit (`confidence`) und Zeitraum. `materials` liefert nur die Liste, ohne Beleg.

{{query:06-line-materials}}

## 7. Die Suche

Eine Strecke, ein Bahnhof, ein Fahrzeug oder ein Unfall nach Namen, mindestens 2 Zeichen. `limit` begrenzt jede Kategorie (standardmäßig 8, höchstens 20). Das Feld kostet mehr als ein einfaches Feld: siehe die Grenzen in den Grundlagen.

{{query:07-search}}

Variablen:

{{variables:07-search}}

## 8. Medien und ihre Rechte

Jedes Medium trägt seinen Urheber, seine Lizenz, eine anzeigefertige Quellenzeile (`attribution`) und den Link auf seine Ursprungsseite. Mit `rights_status` behalten Sie nur gemeinfreie Bilder; `derivatives_ok` auf `false` kennzeichnet eine Lizenz ohne Bearbeitung (Bild vollständig anzeigen, nie beschneiden); `commercial_ok` und `attribution_required` vervollständigen das Bild. Veröffentlichen Sie ein Bild nie weiter, ohne diese Felder zu übernehmen.

{{query:08-media-rights}}

## 9. Was sich zwischen zwei Epochen geändert hat

Kilometer und Anzahl der Strecken, die zwischen zwei Epochen eröffnet, mit liegendem Gleis stillgelegt, abgebaut oder in Betrieb geblieben sind, mit der Länge in Betrieb zu jedem Datum. Dieselben Zahlen wie `GET /api/v1/eras/{year}/compare/{other}`. Es ist ein schweres Feld (250 Punkte): ein Vergleich pro Abfrage.

{{query:09-era-comparison}}

## 10. Eine Liste per Cursor durchlaufen

Die Stilllegungen im Personenverkehr im Finistère zwischen 1930 und 1969, jeweils zwanzig. Für die nächste Seite senden Sie dieselbe Abfrage mit `after` gleich dem Wert von `pageInfo.endCursor`, solange `hasNextPage` wahr ist.

{{query:10-paginated-events}}

Variablen der ersten Seite:

{{variables:10-paginated-events}}

Jedes Ereignis behält seine Quelle (`source_url`) und deren Lizenz (`provenance`).

## Weiterführend

- Das vollständige Datenblatt einer Strecke oder eines Bahnhofs, wie auf der Website, steht in `Line.sheet` und `Station.sheet`; die Berechnung kann einige Sekunden dauern, fragen Sie jeweils nur eines ab.
- `Line.pk(lat:, lng:)` wandelt Koordinaten in einen Kilometerpunkt auf der Strecke um; `route(from:, to:, era:)` berechnet eine Route zu einem Datum.
- Die Verzeichnisse `lineDirectory` und `stationDirectory` übernehmen Filter und Sortierung der Seiten /lines und /stations.
- Jede Abfrage und jeder Typ hat in der Referenz eine eigene Seite mit Argumenten, verschachtelten Typen und veralteten Feldern.
