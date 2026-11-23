# Getting started

*[Français]({{siteRoot}}/guide/premiers-pas) · English · [Deutsch]({{siteRoot}}/guide-de/erste-schritte)*

Ten complete queries, to paste into [GraphiQL]({{origin}}/graphiql) or to send as a `POST` to `{{origin}}/graphql`. Each is checked against the schema whenever it changes. The identifiers (`rfn-420000`, `brest`) are the `slug` of the site's pages: the slug of a line or station is in the address of its page.

The queries are named and take their values from variables where they have any: this is the form to prefer in a program, because the query text does not change from one call to the next.

## 1. A line and its segments at a date

A line's track segments (`segments`) at one era: state, traffic, electrification, speed, kilometre points and provenance of each segment. Without `era`, the query returns every era.

{{query:01-line-at-date}}

Variables:

{{variables:01-line-at-date}}

`status` tells whether the segment is in service, closed with the track in place, or lifted at the end of that year. `provenance` gives the supplier of the track and its licence.

## 2. The stations of a line at one era

`stops` lists the stations in running order, with their kilometre point (`pk_label`) and their state at the requested era (`effective_status`). Without `era`, it is today.

{{query:02-line-stations}}

## 3. A station and its history by era

A station's sheet gives its state at each era (`eras`), the lines that serve it and its neighbours.

{{query:03-station-eras}}

`opened_at` is read with the precision of the date: a station dated to the year alone has no day.

## 4. A line's longitudinal profile

Altitude along the track, with extremes, cumulative climbs and the maximum gradient in per mille. `max_points` lightens the response: the profile keeps both ends, the lowest and the highest point. `profile` is null until the line's profile is computed.

{{query:04-line-profile}}

## 5. A line's accidents

Date and its precision, kind, human toll and the sources read with their licence. A sheet whose `review_status` is `to_review` has an uncertain point: present it as such. To search every accident, see `accidents(filter: …)`.

{{query:05-line-accidents}}

## 6. A line's rolling stock

`material_links` keeps the evidence for each link between a line and a rolling stock series: source, reliability (`confidence`) and period. `materials` gives the bare list, without the evidence.

{{query:06-line-materials}}

## 7. Search

A line, station, rolling stock series or accident by name, at least 2 characters. `limit` caps each category (8 by default, 20 at most). The field costs more than a simple one: see the limits in the introduction.

{{query:07-search}}

Variables:

{{variables:07-search}}

## 8. Media and their rights

Each media item carries its author, its licence, a ready-to-display credit line (`attribution`) and the link to its original page. `rights_status` lets you keep only public domain images; `derivatives_ok` set to `false` flags a no-derivatives licence (show the whole image, never cropped); `commercial_ok` and `attribution_required` complete the picture. Never republish an image without keeping these fields.

{{query:08-media-rights}}

## 9. What changed between two eras

Kilometres and number of lines opened, closed with the track in place, lifted or kept in service between two eras, with the length in service at each date. Same figures as `GET /api/v1/eras/{year}/compare/{other}`. It is a heavy field (250 points): one comparison per query.

{{query:09-era-comparison}}

## 10. Walking through a list by cursor

Closures to passenger traffic in Finistère between 1930 and 1969, twenty at a time. For the next page, send the same query with `after` set to the value of `pageInfo.endCursor`, as long as `hasNextPage` is true.

{{query:10-paginated-events}}

Variables for the first page:

{{variables:10-paginated-events}}

Each event keeps its source (`source_url`) and that source's licence (`provenance`).

## Going further

- The full sheet of a line or station, as on the site, is in `Line.sheet` and `Station.sheet`; it can take a few seconds to compute, so ask for one at a time.
- `Line.pk(lat:, lng:)` converts coordinates into a kilometre point on the line; `route(from:, to:, era:)` computes a route at a date.
- The `lineDirectory` and `stationDirectory` directories reuse the filters and order of the /lines and /stations pages.
- Every query and type has its page in the reference, with arguments, nested types and deprecated fields.
