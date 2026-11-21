# Les trois interfaces de données : REST, GraphQL, MCP

VFF publie ses données par trois interfaces en lecture. Elles lisent les mêmes services, donc les mêmes valeurs et les
mêmes noms de champs ; ce document dit où trouver chaque donnée et ce qui change d'une interface à l'autre.

| Interface | Adresse | Documentation | Où elle est décrite dans le code |
|---|---|---|---|
| REST (JSON, GeoJSON, tuiles) | `/api/v1/...` | `/docs/api` (Scalar), `/docs/swagger`, `/docs/api.json` | Docblocks et attributs Scramble des contrôleurs de `app/Http/Controllers/Api/` |
| GraphQL | `POST /graphql` | `/docs/graphql` (Magidoc), `/graphiql` | Descriptions de `graphql/schema.graphql` |
| Serveur MCP | `POST /mcp` | `docs/MCP.md`, page `/api` | `#[Description]` et `arguments()` de `app/Mcp/Tools/` |

Régénérer la référence GraphQL après un changement du schéma : `make docs-graphql`. La spécification OpenAPI se lit
sur `/docs/api.json` : elle est calculée à chaque requête, rien à régénérer.

## Limites communes

- Débit : 300 requêtes par minute sans jeton, 1 200 avec un jeton personnel (`config/api.php`).
- GraphQL : 5 000 points de complexité et 15 niveaux au plus, refus avant exécution (`query_too_complex`,
  `query_too_deep`) ; barème dans la description du type `Query`.
- Pagination : par curseur (`cursor`, `next_cursor`) pour les annuaires, les événements, les médias et le matériel ;
  par page (`page`) pour les accidents et les passages à niveau ; par `limit` et `offset` pour l'audit des tronçons
  (50 par défaut, 200 au plus, la même règle dans les trois interfaces : `App\Services\SegmentAudit\AuditPage`).
  Les listes d'audit du réseau (`App\Services\SegmentAudit\NetworkAudits`) suivent la même règle et rendent `total`, `limit`, `offset`, `items`. Coût GraphQL des pages d'audit : sous-champs multipliés par `limit`.
- Un message de soutien d'une ligne (`lines.advocacy`, encarts de l'accueil) n'est publié par aucune interface :
  c'est une position éditoriale du site, pas une donnée (test `AdvocacyNotPublishedTest`).

## Où trouver chaque donnée

| Donnée | REST | GraphQL | MCP |
|---|---|---|---|
| Audit des tronçons d'une ligne (4 notes, preuves) | `GET /lines/{line}/audit?era&grade&limit&offset` | `Line.audit(era)` : `segments(grade, limit, offset)`, `segments_total(grade)` | `line_audit` (`era`, `grade`, `limit`, `offset`) |
| Audit d'une gare (placement, dates, services) | `GET /stations/{station}/audit` | `Station.audit` | clé `audit` de `get_station` |
| Audit des tronçons de tout le réseau (liste, filtres, tri, total) | `GET /audits/segments?era&grade&check&reason&status&department&region&line&q&sort&limit&offset` | `segmentAudits(...)` : `total`, `items` | `segment_audits` (mêmes paramètres) |
| Audit des gares de tout le réseau (liste, filtres, tri, total) | `GET /audits/stations?grade&check&opening&closure&department&region&line&q&sort&limit&offset` | `stationAudits(...)` : `total`, `items` | `station_audits` (mêmes paramètres) |
| Profil en long | `GET /lines/{line}/profile?max_points` | `Line.profile(max_points)` | `line_profile` (`max_points`) |
| Référentiel d'altitude entre deux PK | `GET /lines/{line}/profile/points?pk_from_m&pk_to_m&max_points` | `LineProfile.samples(pk_from_m, pk_to_m, max_points)` | `line_profile` (`pk_from_m`, `pk_to_m`, résultat `samples`) |
| Altitude à un PK | `GET /lines/{line}/altitude?pk_m` | `LineProfile.altitude_at(pk_m)` | `line_profile` (`pk_m`, résultat `altitude_at_pk`) |
| Crémaillère (`rack`) et déclivité publiée | `profile.rack` | `LineProfile.rack` | `profile.rack` de `line_profile` |
| Contexte sourcé, documents, images et corrections d'un accident | `GET /accidents/{accident}` (`context`) | `Accident.context` | `get_accident` (sans citation) |
| Provenance des champs d'un accident | `provenance` | `Accident.provenance` | `provenance` de `get_accident` |
| Matériel d'une ligne, photo, fin de circulation, relations | `GET /lines/{line}/materials` (`image`, `end`, `relations`) | `Line.material_links` (`end`, `relations`), `LineSheet.materials` | `line_materials` |
| Rames remarquables (TGV 01 « Patrick ») | `notable_units` de `GET /materials/{material}` | `Material.notable_units` | `notable_units` de `get_material`, `notable_unit` de `search` |
| Images mises en avant (accueil) et compteurs | `GET /media?featured&facets` | `media(filter: {featured})`, `mediaFacets` | `media` (`featured`, `facets`) |
| Gares d'une ligne dans l'ordre des PK | `GET /lines/{line}/stops` (`pk_label`) | `Line.stops` (`pk_label`) | `get_line` (gares) |

Différences voulues :

- Un champ REST regroupé dans un objet (`place`, `review`) est à plat en GraphQL (`commune`, `department_code`,
  `review_status`, `review_notes`) ; les liens HATEOAS (`links`) n'existent qu'en REST.
- Le serveur MCP raccourcit les réponses trop longues (`truncated`) et retire les citations des textes de Wikipédia et
  des documents sous droits ; les listes de l'audit, du profil et des médias se continuent par `offset` ou `cursor`.
- Les tuiles vectorielles, les exports GPX, KML et GTFS ne sont qu'en REST.

## Ajouter une donnée

1. Un service ou une ressource qui calcule la valeur une seule fois (`app/Services/`, `app/Http/Resources/`).
2. REST : un contrôleur avec docblock Scramble (résumé de 60 caractères au plus, description, `QueryParameter`
   décrits avec exemple, `ApiResponse` avec corps d'exemple pour 200, 404, 422 et 429).
3. GraphQL : un champ décrit dans `graphql/schema.graphql`, avec `@complexity`, puis `make docs-graphql`.
4. MCP : un outil ou un champ de plus dans un outil existant, avec sa description, et sa section de `docs/MCP.md`.
5. Les mêmes filtres et la même pagination partout, et un test Feature qui compare les trois réponses
   (voir `tests/Feature/Api/ApiParityGapsTest.php` et `AuditPaginationParityTest.php`).

`tests/Feature/Api/ApiDocumentationCoverageTest.php` refuse une route publique sans description OpenAPI, un type ou un
champ GraphQL sans description, un outil MCP dont un argument n'est pas décrit ou n'est pas cité dans `docs/MCP.md`.
