# Serveur MCP de VFF

VFF expose son encyclopédie aux assistants IA (Claude, ChatGPT, Le Chat, Cursor, VS Code…) par le
**Model Context Protocol** (MCP). Le serveur est **public, gratuit et en lecture seule** : il ne
modifie rien, ne demande pas de compte et ne renvoie aucune donnée personnelle (ni comptes, ni
contributions en attente de validation). Chaque réponse cite ses sources et donne la mention exigée
par les licences.

- Adresse (transport HTTP « streamable ») : `https://vff-project.org/mcp` (méthode POST, JSON-RPC 2.0)
- Commande locale (transport stdio) : `php artisan mcp:start vff`
- Protocole : versions `2025-03-26`, `2025-06-18`, `2025-11-25` (poignée de main `initialize`) et
  `2026-07-28` (découverte), selon le client
- Implémentation : paquet officiel [`laravel/mcp`](https://laravel.com/docs/mcp) (v1.2), code dans
  `app/Mcp/`, route dans `routes/ai.php`, réglages dans `config/mcp.php`

## Sommaire

1. [Installation dans un client MCP](#installation-dans-un-client-mcp)
2. [Conventions communes](#conventions-communes)
3. [Outils](#outils)
4. [Ressources](#ressources)
5. [Exemples : questions et appels](#exemples--questions-et-appels)
6. [Authentification et quotas](#authentification-et-quotas)
7. [Limites et sécurité](#limites-et-sécurité)
8. [Attribution et licences](#attribution-et-licences)
9. [Ce que le serveur ne couvre pas encore](#ce-que-le-serveur-ne-couvre-pas-encore)
10. [Développement et tests](#développement-et-tests)

## Installation dans un client MCP

### Claude Code

```bash
claude mcp add --transport http vff https://vff-project.org/mcp
```

### Claude Desktop, Cursor, VS Code et autres clients à fichier de configuration

Serveur distant (HTTP) :

```json
{
  "mcpServers": {
    "vff": {
      "type": "http",
      "url": "https://vff-project.org/mcp"
    }
  }
}
```

Les clients qui ne savent lancer que des serveurs locaux peuvent passer par le relais
`mcp-remote` : `"command": "npx", "args": ["-y", "mcp-remote", "https://vff-project.org/mcp"]`.

### Serveur local (copie de développement de VFF)

Avec une base VFF locale (cf. `README.md`), le serveur tourne en stdio :

```json
{
  "mcpServers": {
    "vff-local": {
      "command": "/chemin/vers/VFF-Project/bin/php",
      "args": ["artisan", "mcp:start", "vff"]
    }
  }
}
```

En local, l'adresse HTTP est `http://127.0.0.1:8000/mcp`. Pour inspecter le serveur :
`bin/php artisan mcp:inspector vff` (ouvre l'inspecteur MCP officiel).

### Essai sans client MCP

```bash
curl -s https://vff-project.org/mcp \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"search","arguments":{"query":"brest"}}}'
```

La réponse JSON de l'outil est dans `result.content[0].text` ; `result.isError` vaut `true` en cas
d'erreur.

## Conventions communes

- **Identifiants** : les lignes et les gares se désignent par leur `slug`, renvoyé par `search`.
  Les lignes du réseau ferré national ont pour slug `rfn-` suivi de leur code SNCF Réseau à
  6 chiffres (`rfn-420000`) ; le code seul (`420000` ou `420 000`) est aussi accepté.
- **Langue** : chaque outil accepte `lang` = `fr` (par défaut), `en` ou `de`. Elle s'applique aux
  libellés, aux dates rédigées (`when`) et aux textes générés. Les noms propres, les citations de
  sources et les identifiants ne sont pas traduits ; un texte resté en français porte
  `text_lang: "fr"`.
- **Dates** : `date` au format ISO `AAAA-MM-JJ`, toujours avec `precision` : `day` (date exacte),
  `month` (jour fixé à 01), `year` (mois et jour fixés à 01). N'affichez que la partie connue
  (`when` le fait pour vous : « 1er avril 1865 », « avril 1865 », « 1865 »). Une phase sans date
  (`precision: "range"`) dit seulement qu'un changement a eu lieu **entre** deux décennies : aucune
  source ne le date, ne la présentez jamais comme sûre.
- **Époques** : la carte de VFF est un instantané au **31/12 de chaque décennie** (1830 à 2020) ;
  l'ère courante (2030) décrit le réseau **d'aujourd'hui**. Une année quelconque est ramenée à
  l'ère qui la contient ou la précède (1937 donne 1930) ; à partir de l'année en cours, réseau
  actuel.
- **Unités** : longueurs en kilomètres (`*_km`, une décimale) ou en mètres (`*_m`), vitesses en
  km/h, écartements en mm, tensions en volts, coordonnées WGS 84 en degrés décimaux, PK en km.
- **Sources** : chaque fait porte `source` (valeur brute : `wikipedia`, `wikidata`, `ohm`, `osm`,
  `sncf`, `jorf`, `lignes-oubliees`, `archeoferroviaire`, `manual`…), `source_url` (document
  consulté) et `provider` (fournisseur dont la licence s'applique, détaillé par
  `sources_and_licences`).
- **Enveloppe** : chaque réponse réussie contient `vff_url` (lien de la fiche VFF à citer) et
  `attribution` (`notice` + `sources` : `provider`, `mention`, `open_licence`). Si la réponse a été
  raccourcie : `truncated` (liste => nombre d'éléments retirés) et `truncated_note`.
- **Erreurs** : `isError: true` et un JSON `{"error": "<code>", "message": "…"}` avec, selon le cas,
  `suggestions` (slugs proches), `candidates` (gares homonymes) ou `available_eras`. Les erreurs de
  validation (argument manquant, hors bornes, inconnu) sont des messages texte.

## Outils

Tous les outils sont annotés `readOnlyHint: true`, `idempotentHint: true`, `openWorldHint: false`.

| Outil | Rôle |
|---|---|
| `search` | Trouver une ligne, une gare, un matériel ou un accident par son nom (identifiants pour les autres outils). |
| `get_line` | Fiche d'une ligne : longueurs, électrification, signalisation, phases datées, chronologie. |
| `line_events` | Événements d'une ligne sur une période. |
| `get_station` | Fiche d'une gare : dates, statut par décennie, lignes desservies, sources. |
| `network_at_era` | État du réseau à une époque (km par état et par trafic, gares par statut). |
| `historical_route` | Itinéraire entre deux gares sur le réseau d'une époque, ou le même trajet comparé à deux époques. |
| `sources_and_licences` | Fournisseurs de données et licences. |
| `network_events` | Événements de toutes les lignes (département, type, période), paginés. |
| `media` | Photos, cartes postales, plans d'une ligne ou d'une gare, avec leurs droits. |
| `list_lines` | Annuaire des lignes (réseau, état, trafic, département, époque). |
| `list_stations` | Annuaire des gares (statut, département, époque). |
| `materials` | Séries de matériel roulant filtrées et triées. |
| `get_material` | Fiche d'une série de matériel, avec les lignes parcourues et leurs preuves. |
| `accidents` | Accidents et événements graves du rail (ligne, période, nature, département, bilan), paginés. |
| `get_accident` | Fiche d'un accident : lignes rattachées, sources lues et leur licence, points à vérifier. |
| `compare_eras` | Ce qui a changé sur le réseau entre deux ères : km ouverts, fermés, déposés, restés en service. |
| `level_crossings` | Passages à niveau publiés par SNCF Réseau (classe, PK, commune), par ligne, groupe, commune ou emprise, paginés. |
| `line_profile` | Profil en long d'une ligne : altitudes le long du tracé, dénivelés, déclivité la plus forte, altitude des gares (IGN). |
| `locate_on_line` | Point kilométrique d'un lieu (latitude, longitude) sur le tracé d'une ligne. |

### `search` : rechercher une ligne, une gare, un matériel ou un accident

Point de départ : trouve les identifiants attendus par les autres outils.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `query` | chaîne, 2 à 100 caractères | oui | Nom de ligne, code de ligne à 6 chiffres, nom de gare, de matériel ou d'accident, commune ou date d'accident (`1891`, `07/2013`, `2013-07-12`). Casse et accents ignorés. |
| `kinds` | liste parmi `lines`, `stations`, `materials`, `accidents` | non | Catégories cherchées (par défaut : les quatre). |
| `limit` | entier 1 à 20 | non | Résultats par catégorie (8 par défaut). |
| `lang` | `fr`, `en`, `de` | non | Langue (fr par défaut). |

Renvoie `lines` (`slug`, `name`, `code`, `vff_url`), `stations` (`slug`, `name`, `city`,
`department_code`, `lat`, `lng`, `vff_url`), `materials` (`slug`, `name`, `kind`, `builder`,
`vff_url`), `accidents` (`slug`, `name` dans la langue demandée, `names` en `fr`, `en` et `de`, `date`
et `date_precision`, `kind`, `commune`, `department_code`, `lat`, `lng` nuls sans position,
`vff_url` ; le `slug` va à `get_accident`). Les accidents se trouvent par leur nom (dans les trois
langues), leur commune ou leur date : une année (`1891`), un mois (`07/2013`, `2013-07`) ou un jour
(`12/07/2013`, `2013-07-12`) ; un accident dont seule l'année ou le mois est connu ne répond pas à
un jour précis. Seuls les accidents publiés sortent, jamais ceux que VFF ne montre pas. Tri par
pertinence : nom exact, début du nom, mot du nom, reste ; à pertinence égale, lignes en service
d'abord, accidents du plus ancien au plus récent.

```json
{"query": "420000", "kinds": ["lines"]}
```

### `get_line` : fiche d'une ligne

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | chaîne, 1 à 100 caractères | oui | Slug (`rfn-420000`) ou code à 6 chiffres. |
| `include_events` | booléen | non | Inclure la chronologie détaillée (`true` par défaut). |
| `max_events` | entier 1 à 300 | non | Événements de la chronologie au plus (100 par défaut) ; `events_omitted` donne le reste. |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `line` :

- identité : `slug`, `code`, `name`, `network` (`principal`, `secondaire`, `militaire`,
  `particulier`), `is_lgv` ;
- `today` : `state` (`open` ou `closed_or_gone`) et `by_category_km` (km par catégorie, avec
  libellé : `lgv` grande vitesse ou voyageurs seuls, `mixed`, `freight`, `unknown`, `heritage`,
  `velorail`, `service_track`, `construction`, `closed` voie en place, `dismantled`, `razed`) ;
- `length` : `trace_km` (tracé VFF à son plus grand développement, toutes portions comprises),
  `in_service_today_km`, `sncf_km` (formes généralisées SNCF, souvent un peu plus courtes) ;
- `stats_era` (ère des chiffres qui suivent), `max_speed_kmh`, `gauge_mm` ;
- `electrification` : `state` (`none`, `marginal` moins de 5 %, `partial`, `full` 95 % et plus),
  `state_label`, `km`, `pct`, `voltages` (`voltage_v`, `km`) ;
- `signalling` : `block_systems_km` (cantonnement : `BAL`, `BAPR-VB`, `CT-VU`, `TVM-430`,
  `ETCS_L2`…, avec km ; une tranche où l'ETCS niveau 2 et la TVM sont installés ensemble, comme
  sur la LGV Est, la LGV Bretagne - Pays de la Loire ou la LGV SEA, compte pour chacun des deux),
  `train_protection_km` (contrôle de vitesse, ex. `KVB`), `kvb`, `source` (`sncf`, SNCF Réseau
  Open Data) ;
- `facts` : faits encyclopédiques (`key`, valeur `km`/`kmh`/`mm`/`n`/`year`/`list`, `source`
  `vff`/`sncf`/`wikidata`, `item` quand l'élément Wikidata ne décrit qu'une partie de la ligne,
  `label`, `note`) ;
- `phases` : chaque date où le fer ou le trafic change sur une portion (`date`, `precision`,
  `when`, `kind` : `growth`, `traffic`, `decline`, `removal`, `heritage` ; `changes` : `from` et
  `to` (catégories), `length_km`, `event_id`, `source`, `source_url` ; `in_service_after_km`),
  `best_phase` (indice de la phase du plus grand développement) ;
- `decades` : `year`, `in_service_km`, `by_category_km` ;
- `event_count`, `events` (même forme que `line_events`), `events_omitted`.

```json
{"line": "rfn-610000", "max_events": 50, "lang": "en"}
```

### `line_events` : événements d'une ligne sur une période

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | chaîne | oui | Slug ou code à 6 chiffres. |
| `from_year` | entier 1700 à 2100 | non | Première année incluse. |
| `to_year` | entier 1700 à 2100, supérieur ou égal à `from_year` | non | Dernière année incluse. |
| `types` | liste de types | non | `opening`, `opening_passenger`, `opening_freight`, `closure`, `closure_passenger`, `closure_freight`, `reopening`, `reopening_passenger`, `reopening_freight`, `electrification`, `modernization`, `dismantling`, `conversion`, `concession`, `concession_end`, `declassification`, `heritage_start`, `heritage_end`, `accident`, `other`. |
| `limit` | entier 1 à 500 | non | Événements au plus (200 par défaut). |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `line`, `period`, `total` (nombre trouvé) et `events` : `id`, `date`, `precision`, `when`,
`type`, `label`, `section` (« De - À » si l'événement ne touche qu'une portion), `text`,
`text_lang`, `source`, `source_url`, `provider`.

`opening`, `closure`, `reopening` concernent tous les trafics ; les variantes `_passenger` et
`_freight` un seul service (une ligne fermée aux voyageurs mais ouverte au fret devient une ligne
fret). `declassification` est un retrait juridique, sans preuve de dépose ; `dismantling` la dépose
de la voie.

```json
{"line": "rfn-610000", "from_year": 1900, "to_year": 1950, "types": ["closure_passenger", "closure"]}
```

### `get_station` : fiche d'une gare

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `station` | chaîne, 1 à 150 caractères | oui | Slug renvoyé par `search` (ex. `limoges-benedictins`). |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `station` (`slug`, `name`, `alt_names`, `type` : `gare`, `halte`, `point_arret`…,
`status` : code et libellé parmi `active` ouverte aux voyageurs, `tourist`, `no_commercial` fret
seul, `declassified` fermée, `gone` disparue, `planned` projetée, `technical`, `excluded` ;
`city`, `department_code`, `uic_code`, `wikidata_id`, `lat`, `lng`, `opened` et `closed` avec
date, précision et `when`), `eras` (statut à chaque décennie ; `uncertain` = non confirmé par les
voies de l'époque), `events` (chronologie : `type`, `label`, date, `source`, `source_url`,
`inferred` = date déduite des lignes et non d'une source sur la gare, `note`), `lines` (`slug`,
`name`, `code`, `first_year`, `last_year`, `pk_km`, `served_today`) et `sources` (provenance brute,
lecture seule : `source`, `ref` = identifiant dans la source, `name`, `url`, `distance_m` = écart à la
position retenue, `match_method` = comment la source a été rapprochée de la gare, `fetched_at` = date
de lecture par VFF, `raw` = attributs bruts reçus de la source, de forme variable). `station` porte
aussi `attribute_sources` (quelle source a fourni chaque attribut, recalage de la position compris)
et `position` (`source`, `spread_m`, `flagged` = position à vérifier).

```json
{"station": "brest"}
```

### `network_at_era` : état du réseau à une époque

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `year` | entier 1800 à 2100 | oui | Année quelconque, ramenée à l'ère de la carte. |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `requested_year`, `era_year`, `is_current`, `snapshot` (`AAAA-12-31` ou « aujourd'hui »),
`open_km` (en service), `closed_km` (fermé, voie en place), `gone_km` (déposé ou effacé),
`electrified_km` (électrifié en service), `active_lines`, `open_km_by_traffic` (`mixed`,
`passenger`, `freight`, `heritage`, `unknown`), `stations_by_status` (nombre de gares par statut à
cette ère), `available_eras`, `site_totals` (lignes, gares, matériels, ères cartographiées et km exploités
aujourd'hui : les chiffres de l'accueil), `network_profile` (km en service et lignes actives à chaque ère, voies
parallèles comprises), `vff_url` (la carte à cette époque).

Les longueurs additionnent les tronçons de la carte (voies parallèles d'une même ligne comprises) :
un ordre de grandeur, pas le kilométrage officiel du réseau.

```json
{"year": 1937}
```

### `historical_route` : itinéraire historique entre deux gares

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `from` | chaîne, 1 à 200 caractères | oui | Gare de départ : slug (de préférence), nom exact ou nom de ville (« Paris » = toutes les gares parisiennes ouvertes à l'époque). |
| `to` | chaîne, 1 à 200 caractères | oui | Gare d'arrivée, mêmes formes. |
| `year` | entier 1800 à 2100 | non | Année (réseau actuel par défaut). |
| `heritage` | booléen | non | Autoriser les voies associatives (`false` par défaut). |
| `traffic` | `passenger` ou `all` | non | `passenger` (défaut) : voies sans service voyageurs comptées 4 fois leur longueur. |
| `compare_year` | entier 1800 à 2100 | non | Seconde année : compare le même trajet à deux époques (elle doit tomber dans une autre ère que `year`). |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `from`, `to`, `requested_year`, `era_year`, `distance_km`, `duration_h` (durée
**indicative** à 100 km/h, pas un horaire historique) et `duration_note`, `hops` (sections gare à
gare), `lines` (`slug`, `name`, `code`, `km`, `from`, `to`), `stations` (au plus 120, extrémités
gardées ; `stations_omitted`), `non_passenger_km`, `heritage_km`, `gauge_changes` (mm),
`warnings` (`gap_joins` : raccords entre tracés non jointifs) et `vff_url` (l'itinéraire sur la
carte).

Erreurs : `era_not_found`, `graph_not_built`, `station_not_found`, `ambiguous_station` (avec
`candidates`), `same_station`, `station_not_in_network` (avec `station` et `status`), `no_path`.

```json
{"from": "rennes", "to": "brest", "year": 1930}
```

Avec `compare_year`, la réponse range les deux époques dans l'ordre chronologique : `earlier` et
`later`, chacune avec `requested_year`, `era_year`, `available` et, selon le cas, l'itinéraire
résumé (mêmes champs que ci-dessus) ou `unavailable` (`error` = `no_path` ou
`station_not_in_network`, `message` dans la langue demandée, `station` et `status` le cas échéant).
Puis `differences` : `outcome` (`both`, `lost` pour un trajet devenu impossible, `gained`,
`neither`), `distance_change_km` et `distance_change_percent` (seconde époque moins première),
`same_path`, `shared_lines`, `lines_only_earlier` et `lines_only_later` (avec `other_era` : `open`,
`partly_open`, `closed` ou `none` à l'autre époque) et `shared_stations`. `vff_url` ouvre les deux
trajets sur la carte. Erreur supplémentaire : `same_era`. Même calcul que
`GET /api/v1/route/compare`.

```json
{"from": "angouleme", "to": "limoges-benedictins", "year": 1930, "compare_year": 2026}
```

### `sources_and_licences` : sources et licences

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `provider` | clé de fournisseur | non | Un seul fournisseur (`osm`, `sncf`, `ign`, `ohm`, `wikipedia`, `wikidata`, `commons`, `jorf`, `lignes-oubliees`, `archeoferroviaire`…). Par défaut : tous. |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `database_licence` (ODbL 1.0 et conditions) et `providers` : `provider`, `name`,
`licence`, `open_licence`, `mention`, `proof` (pages qui établissent la licence), `checked_on`
(date de lecture). Source : registre `database/data/source_rights.php` (les notes internes du
registre ne sont pas publiées).

```json
{"provider": "osm", "lang": "en"}
```

### Outils de liste : conventions

`network_events`, `media`, `list_lines`, `list_stations`, `materials` et `accidents` reprennent les filtres et les
données de l'API REST (`/api/v1/events`, `/api/v1/media`, `/api/v1/lines`, `/api/v1/stations`, `/api/v1/materials`,
`/api/v1/accidents`) : mêmes services (`LineEventQuery`, `MediaQuery`, `LineDirectoryQuery`,
`StationDirectoryQuery`, `MaterialQuery`, `AccidentQuery`), mêmes règles de validation, mêmes champs. Ils paginent **par curseur** : `limit` éléments par appel ;
la réponse donne `count`, `has_more` et `next_cursor`, à repasser tel quel dans `cursor`, avec
les mêmes filtres, pour la page suivante (`null` à la dernière page). Une page qui dépasserait la
taille maximale est raccourcie à la frontière d'un élément (`truncated`, `truncated_note`) et
`next_cursor` reprend alors au premier élément non renvoyé : en suivant les curseurs, on ne perd
aucun élément. `count` donne le nombre d'éléments renvoyés, `filters` rappelle les filtres appliqués.

### `network_events` : événements de tout le réseau

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug | non | Une seule ligne (ex. `rfn-447000`). |
| `type` | type d'événement | non | Mêmes types que `line_events`. |
| `source` | chaîne, 64 au plus | non | `wikipedia`, `wikidata`, `ohm`, `lignes-oubliees`, `archeoferroviaire`, `osm`, `heritage`, `manual`… |
| `department` | code INSEE en majuscules | non | `29`, `2A`, `974` : lignes citant ce département ou y desservant une gare. |
| `from`, `to` | année `AAAA` ou date `AAAA-MM-JJ` | non | Période, bornes incluses (une année va du 1er janvier au 31 décembre). |
| `order` | `asc`, `desc` | non | Ordre chronologique (`asc` par défaut). |
| `limit` | entier 1 à 200 | non | Événements par appel (50 par défaut). |
| `cursor` | chaîne opaque | non | `next_cursor` d'un appel précédent. |
| `lang` | `fr`, `en`, `de` | non | Langue de `when`. |

Chaque événement : `id`, `when` (date rédigée), `type`, `date`, `date_precision`, `section_from`,
`section_to`, `description` (en français), `confidence` (`high`, `medium`, `low`), `source`,
`source_url`, `provenance` (`provider`, `name`, `license`, `license_status` : `verified`,
`uncertain`, `forbidden`) et `line` (`slug`, `code`, `name`).

```json
{"department": "29", "type": "closure_passenger", "from": "1930", "to": "1939"}
```

### `media` : photos, cartes postales, plans et documents

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` ou `station` | slug | non | Médias d'une ligne ou d'une gare (l'un ou l'autre). |
| `type` | `photo`, `postcard`, `plan`, `map`, `document` | non | Nature. |
| `provider` | chaîne | non | `commons`, `upload`, `argentanwebferro`… |
| `license` | chaîne | non | Licence exacte, casse ignorée (`CC BY-SA 4.0`). |
| `rights_status` | `public_domain`, `open_license`, `permission`, `unknown` | non | État des droits. |
| `commercial_ok` | booléen | non | Réutilisation commerciale permise ou non. |
| `period` | `pre1900`, `1900_1944`, `1945_1969`, `1970_1999`, `since2000`, `undated` | non | Époque représentée. |
| `from_year`, `to_year` | entiers | non | Année représentée, bornes incluses. |
| `order` | `asc`, `desc` | non | Ordre des identifiants. |
| `limit` | entier 1 à 60 | non | Médias par appel (20 par défaut). |
| `cursor` | chaîne opaque | non | Page suivante. |

Chaque média : `title`, `caption`, `thumb_url`, `display_url`, `source_url` (page d'origine),
dimensions, `depicted_year`, `author`, `license`, `license_url`, `attribution`, `rights_status`,
`commercial_ok`, `share_alike`, `location`, `subject` (ligne ou gare). **Affichez toujours
`attribution` et le lien `source_url` avec l'image.**

```json
{"station": "brest", "limit": 5}
```

### `list_lines` : annuaire des lignes

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `q` | chaîne, 100 au plus | non | Nom, code ou compagnie d'origine. |
| `network` | `principal`, `secondaire`, `militaire`, `particulier`, `none` | non | Réseau. |
| `status` | `open`, `closed` | non | État à l'ère de référence. |
| `traffic` | `passenger`, `mixed`, `freight`, `unknown` | non | Trafic d'une portion en service à l'ère de référence. |
| `heritage` | booléen | non | Portion associative (train touristique, vélorail). |
| `department` | code INSEE en majuscules | non | Lignes citant ce département ou y desservant une gare. |
| `era` | décennie 1830 à 2030 | non | Ère de référence (par défaut : aujourd'hui). |
| `sort` | `name`, `code`, `slug` | non | Tri (`name` : alphabétique français ; `code` : lignes sans code en dernier). |
| `order` | `asc`, `desc` | non | Sens du tri. |
| `facets` | booléen | non | Ajoute les compteurs de la recherche. |
| `limit` | entier 1 à 100 | non | Lignes par appel (25 par défaut). |
| `cursor` | chaîne opaque | non | Page suivante. |

Renvoie `era_year` et `lines` : `slug`, `code`, `name`, `network`, `historical_operator`,
`regions`, `departements`, `state` (`open`, `closed`, `unknown` : sans tracé à cette ère),
`traffic`, `heritage`, `vff_url` ; avec `facets` : `total`, `open`, `closed`, `unknown`,
`networks`, `traffics`, `heritage`.

```json
{"traffic": "freight", "era": 1930, "facets": true}
```

### `list_stations` : annuaire des gares

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `q` | chaîne, 100 au plus | non | Nom, commune, autre nom ou début du code UIC. |
| `status` | `active`, `tourist`, `no_commercial`, `declassified`, `gone`, `planned` | non | Statut (avec `era` : les quatre premiers). |
| `department` | code INSEE en majuscules | non | Département de la gare. |
| `era` | décennie 1830 à 2030 | non | Statut à cette ère ; gares absentes de l'ère exclues. |
| `flagged` | booléen | non | Position à vérifier. |
| `sort` | `name`, `city`, `opened`, `closed` | non | Tri (valeurs manquantes en dernier). |
| `order` | `asc`, `desc` | non | Sens du tri. |
| `facets` | booléen | non | Ajoute le nombre de gares par statut. |
| `limit` | entier 1 à 100 | non | Gares par appel (25 par défaut). |
| `cursor` | chaîne opaque | non | Page suivante. |

Chaque gare : `slug`, `name`, `type`, `status`, `city`, `department_code`, `uic_code`,
`wikidata_id`, `opened_at`/`closed_at` et leur précision, `closed_after`, `lat`, `lng`,
`position_flagged`, `sources_count`, `lines` (10 au plus) et `lines_more`, `vff_url`.

```json
{"department": "29", "era": 1930, "sort": "opened"}
```

### `materials` : matériel roulant

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `q` | chaîne, 100 au plus | non | Nom, autres noms, constructeur, numéros d'engins (sans accents). |
| `type` | catégorie | non | `locomotive_vapeur`, `locomotive_diesel`, `locomotive_electrique`, `automotrice`, `bimode`, `autorail`, `rame_tgv`, `voiture`, `wagon`, `tramway`, `engin_service`. |
| `builder`, `operator` | chaîne | non | Constructeur ; exploitant (identifiant Wikidata exact ou nom partiel). |
| `line` | slug | non | Séries attestées sur la ligne par une source. |
| `in_service_at` | année | non | En service cette année-là. |
| `from_year`, `to_year` | années | non | Mise en service, bornes incluses. |
| `gauge_mm` | entier 500 à 2000 | non | Écartement. |
| `service_status` | chaîne | non | `in_service` ou `withdrawn`, seulement quand une source le dit (Wikidata P5817, effectif de l'infobox Wikipédia). |
| `scope` | chaîne | non | `france` (rattachée à la France dans Wikidata) ou `attested` (série non rattachée, ICE ou locomotive belge par exemple, dont une source atteste la circulation sur une ligne française). |
| `has_image`, `has_lines` | booléens | non | Séries illustrées ; séries reliées à une ligne. |
| `sort` | `name`, `in_service_from`, `max_speed_kmh`, `units_built`, `lines_count` (préfixe `-` : décroissant) | non | Tri, inconnues en dernier. |
| `limit` | entier 1 à 50 | non | Séries par appel (20 par défaut). |
| `cursor` | chaîne opaque | non | `next_cursor` d'un appel précédent (mêmes filtres, même tri). |

Renvoie `count`, `has_more`, `next_cursor` et `materials` (fiche de liste de l'API REST : caractéristiques
en km/h, kW, mm, années ; image avec son attribution ; `provenance`). Un champ nul n'est pas
renseigné par la source : VFF n'estime aucune valeur.

```json
{"type": "locomotive_electrique", "sort": "-max_speed_kmh", "limit": 5}
```

### `get_material` : fiche d'une série de matériel

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `material` | slug | oui | Slug renvoyé par `search` ou `materials` (ex. `bb-15000`). |

Renvoie `material` : la fiche de l'API REST `/api/v1/materials/{slug}`, avec `field_sources`
(provenance de chaque champ) et `lines` (lignes parcourues, chaque lien avec sa preuve `evidence`
et sa fiabilité `confidence`). Série inconnue : erreur `material_not_found` avec `suggestions`.

```json
{"material": "bb-15000"}
```

### `accidents` : accidents et événements graves du rail

Mêmes filtres, mêmes tris et mêmes données que `GET /api/v1/accidents` (service `AccidentQuery`, ressource
`AccidentResource`) ; pagination par curseur comme les autres outils de liste.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `q` | chaîne, 100 au plus | non | Nom de l'accident (toutes langues) ou commune, sans accents ni capitales. |
| `from`, `to` | années 1800 à 2100 | non | Période, bornes incluses (`to` au moins égale à `from`). |
| `line` | slug | non | Accidents rattachés à cette ligne (rattachement principal ou secondaire) ; ligne inconnue : erreur. |
| `department` | code INSEE en majuscules | non | Département actuel du lieu (`91`, `2A`, `974`). |
| `kind` | nature | non | `collision`, `derailment`, `level_crossing`, `fire`, `explosion`, `structure_failure`, `attack`, `person_struck`, `runaway`, `incident`, `other`. |
| `review` | `ok`, `to_review` | non | Fiches sans point incertain, ou « à vérifier ». |
| `min_deaths` | entier, 0 ou plus | non | Bilan connu d'au moins ce nombre de morts (exclut les bilans inconnus). |
| `has_line` | booléen | non | `true` : rattachés à une ligne VFF ; `false` : sans ligne (lieu inconnu). |
| `sort` | `date`, `-date`, `deaths`, `-deaths` | non | Tri (`date` par défaut) ; bilan inconnu en dernier. |
| `limit` | entier 1 à 50 | non | Accidents par appel (20 par défaut). |
| `cursor` | chaîne opaque | non | `next_cursor` d'un appel précédent (mêmes filtres, même tri). |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `filters`, `count`, `has_more`, `next_cursor` et `accidents`. Chaque accident reprend la liste REST, avec
`name`, `summary` et `kind_label` dans la **seule langue demandée** (le REST donne les trois) et `when` (date
rédigée) : `slug`, `wikidata_id`, `kind`, `date` à lire avec `date_precision` (`day`, `month`, `year`), `time`,
`place` (`commune`, `commune_insee`, `department_code`, `department`, `lat` et `lng` en WGS 84, `precision` de la
position : `exact`, `level_crossing`, `station`, `commune` = lieu exact inconnu), `line` (`slug`, `code`, `name`,
`confidence` du rattachement : `high`, `medium`, `low`), `deaths` et `injuries` (`null` : aucune source consultée ne
les donne ; `0` : aucun), `toll_source` (`bea-tt`, `wikidata`, `wikipedia`), `review_status`, `url`. Aucune victime
n'est nommée.

**Prudence** : `review_status: "to_review"` (« à vérifier ») signale un point incertain (bilans ou dates qui diffèrent
selon les sources, lieu connu à la commune près, ligne incertaine). La réponse porte alors `review_note`, et
l'assistant doit présenter la fiche comme incertaine. Le bilan retenu est celui de l'enquête du BEA-TT, sinon de
Wikidata, sinon de Wikipédia.

**Attribution** : le bloc `attribution` ajoute, en plus de VFF, OpenStreetMap et SNCF, un fournisseur par source
effectivement lue pour les accidents renvoyés : Wikidata (CC0), Wikipédia (faits seulement, aucun texte repris ;
toute citation de l'article est sous CC BY-SA 4.0, avec lien vers l'article), BEA-TT (Licence Ouverte 2.0, mention
du BEA-TT et de la date de mise à jour du bilan).

```json
{"kind": "derailment", "min_deaths": 10, "sort": "-deaths", "limit": 5, "lang": "en"}
```

### `get_accident` : fiche d'un accident

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `accident` | slug | oui | Slug renvoyé par `accidents` (date puis commune, ex. `2013-07-12-bretigny-sur-orge`). |
| `lang` | `fr`, `en`, `de` | non | Langue. |

Renvoie `accident` (la fiche de `GET /api/v1/accidents/{slug}`, `AccidentDetailResource`) : les champs de la liste,
plus `lines` (lignes rattachées, la principale d'abord : `line`, `is_primary`, `era_year`, `distance_m`, `pk_m` et
`pk_label` en « km+mmm », `section` = gares de part et d'autre, `method`, `confidence`, `score`), `nearest_station`,
`trains`, `toll_source_url`, `wikipedia` (articles par langue), `sources` (`provider`, `role`, `title`, `url`,
`license`, `license_url`, `license_status`, `retrieved_at`, `facts` lus dans la source), `review` (`status` et
`notes` : `code`, `label` dans la langue demandée, `detail`) et `provenance` (fournisseur de chaque fait retenu),
avec `review_note` si la fiche est « à vérifier ». Accident inconnu : erreur `accident_not_found`.

```json
{"accident": "2013-07-12-bretigny-sur-orge", "lang": "en"}
```

### `compare_eras` : comparer le réseau à deux époques

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `from` | entier 1800 à 2100 | oui | Première année, ramenée à l'ère de la carte qui la contient ou la précède (1937 donne 1930). |
| `to` | entier 1800 à 2100 | oui | Seconde année, même règle ; peut précéder `from` (les deux sont remises dans l'ordre chronologique). |
| `lang` | `fr`, `en`, `de` | non | Langue des libellés. |

Même résumé que `GET /api/v1/eras/{year}/compare/{other}` (service `EraComparison`) et que la vue « Comparer » de
la carte. Renvoie `requested_years` (dans l'ordre demandé), `from` et `to` (ères réellement comparées),
`changes` (`opened`, `closed`, `removed`, `kept`, chacun `{km, lines}`), `open_km` (`from`, `to` : longueur en
service à chaque ère), `labels` (sens de chaque rubrique dans la langue demandée), `links` (`map` : la carte
comparée ; `tiles` : gabarit des tuiles vectorielles REST), `available_eras`.

- `opened` : en service à `to`, pas à `from` ; `closed` : fermé voie en place à `to` (voie de service comprise), pas
  à `from` ; `removed` : en service ou fermé à `from`, déposé ou disparu à `to` ; `kept` : en service aux deux.
- `km` : somme des tronçons de la carte (voies parallèles d'une même ligne comprises), arrondie au kilomètre :
  un ordre de grandeur, pas le kilométrage officiel. `lines` : lignes dont au moins un tronçon est concerné.
- Deux années dans la même ère : erreur `same_era` ; année sans carte : `era_not_found` (avec `available_eras`).

```json
{"from": 1930, "to": 1980}
```

### `level_crossings` : passages à niveau

Mêmes filtres et mêmes données que `GET /api/v1/level-crossings` (service `LevelCrossingQuery`) ; pagination par
curseur comme les autres outils de liste. Environ 16 800 PN publiés par SNCF Réseau (jeu
`liste-des-passages-a-niveau`, ODbL). État actuel seulement : aucune date d'ouverture ni de suppression.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug | non | PN de cette ligne (rattachés par son code SNCF Réseau) ; ligne inconnue : erreur. La réponse ajoute `summary` (nombre par groupe). |
| `group` | groupe | non | `automatic` (classes 17 à 19), `guarded` (10 à 16), `no_barrier` (20, 21), `pedestrian` (31, 32), `private` (41 à 46), `secondary` (0). |
| `class` | entier | non | Classe exacte : 0, 10 à 21, 31, 32, 41 à 46. |
| `commune` | chaîne, 2 à 100 | non | Partie du nom de la commune, sans accents ni capitales. |
| `bbox` | `ouest,sud,est,nord` | non | Emprise en degrés WGS 84. |
| `limit` | entier 1 à 100 | non | PN par appel (50 par défaut). |
| `cursor` | chaîne opaque | non | `next_cursor` d'un appel précédent (mêmes filtres). |
| `lang` | `fr`, `en`, `de` | non | Langue des libellés de classe et de groupe. |

Renvoie `filters`, `count`, `has_more`, `next_cursor`, `level_crossings` et, avec `line`, `summary` et `vff_url`
(section de la fiche ligne). Chaque PN : `id`, `label`, `class` et `class_label`, `group` et `group_label`, `pk_m` et
`pk_label`, `commune`, `crossed_way` (libellé brut de la voie franchie), `line`, `lat`, `lng`. Un PN secondaire
(`secondary`) est un passage situé sur plusieurs lignes et compté sur une autre : à exclure pour compter des
passages. Attribution : SNCF Réseau, ODbL 1.0.

```json
{"line": "rfn-70000", "group": "automatic", "limit": 20, "lang": "en"}
```

### `line_profile` : profil en long

Même contenu que `GET /api/v1/lines/{line}/profile` (service `LineProfiles`), allégé à 200 points par défaut. Profils
calculés à l'avance par `vff:compute-line-profiles` : une ligne sans profil renvoie l'erreur `profile_not_computed`.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `max_points` | entier 10 à 2 000 | non | Nombre de points au plus (200 par défaut) ; les bouts, le point le plus bas et le plus haut restent. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `profile` (`era_year`, `step_m`, `length_m`, `summary` avec altitudes extrêmes, dénivelés et
déclivité la plus forte en ‰ sur 1 km, `points` = `[distance_m, altitude_m]` le long du parcours, `structures`,
`gaps`, `stations` avec leur PK officiel, `altitude_sources`) et `vff_url` (section de la fiche). Les distances ne
sont pas des PK. Attribution : IGN (BD TOPO, RGE ALTI), Licence Ouverte 2.0.

```json
{"line": "rfn-896000", "max_points": 100}
```

### `locate_on_line` : point kilométrique d'un lieu

Même résultat que `GET /api/v1/lines/{line}/pk` (service `LinePk`) : le point est projeté sur le tracé de la ligne à
l'ère choisie, puis la distance est mesurée le long du tracé depuis son origine.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `lat` | nombre, -90 à 90 | oui | Latitude WGS 84, en degrés décimaux. |
| `lng` | nombre, -180 à 180 | oui | Longitude WGS 84, en degrés décimaux. |
| `year` | entier 1800 à 2100 | non | Année ramenée à l'ère de la carte (le réseau d'aujourd'hui par défaut). |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `era_year`, `pk_m` (mètres), `pk_label` (`km+mmm`), `total_m` (longueur du tracé à cette ère), `snap`
(`lat`, `lng` du point du tracé retenu, `distance_from_input_m` : au-delà de quelques dizaines de mètres, le lieu n'est
probablement pas sur la ligne) et `vff_url`. Ce PK est mesuré le long du tracé VFF : il peut différer du PK officiel de
SNCF Réseau. Erreur `no_geometry` si la ligne n'a pas de tracé à cette ère.

```json
{"line": "rfn-420000", "lat": 48.10, "lng": -1.67}
```

## Ressources

| URI | Type | Contenu |
|---|---|---|
| `vff://licences` | `text/markdown` | Conditions de réutilisation et licence de chaque source (français). |
| `vff://{lang}/lines/{slug}` | `text/markdown` | Fiche d'une ligne prête à citer : identité, longueurs, électrification, signalisation, phases, chronologie (50 événements au plus), sources et lien. Ex. `vff://fr/lines/rfn-420000`. |

## Exemples : questions et appels

| Question d'un utilisateur | Appels de l'assistant |
|---|---|
| « Quand la ligne Limoges - Angoulême a-t-elle fermé aux voyageurs ? » | `search {"query": "Limoges Angoulême", "kinds": ["lines"]}` puis `line_events {"line": "rfn-610000", "types": ["closure_passenger", "closure", "reopening_passenger"]}` |
| « Quelle signalisation et quelle électrification sur Paris - Brest ? » | `get_line {"line": "420000", "include_events": false}` |
| « Raconte l'histoire de la ligne de Saint-Germain-des-Fossés à Darsac. » | `search {"query": "Darsac"}` puis `get_line {"line": "<slug>", "max_events": 150}` ou la ressource `vff://fr/lines/<slug>` |
| « Que s'est-il passé sur la ligne 830000 pendant la Seconde Guerre mondiale ? » | `line_events {"line": "830000", "from_year": 1939, "to_year": 1945}` |
| « La gare de Brest est-elle ouverte depuis quand, et quelles lignes la desservent ? » | `search {"query": "Brest", "kinds": ["stations"]}` puis `get_station {"station": "brest"}` |
| « Combien de kilomètres de voies ferrées en France en 1930, et aujourd'hui ? » | `network_at_era {"year": 1930}` et `network_at_era {"year": 2026}` |
| « Comment aller de Rennes à Brest en train en 1930 ? » | `historical_route {"from": "rennes", "to": "brest", "year": 1930}` |
| « Pouvait-on aller de Limoges à Angoulême en 1990 ? Et aujourd'hui ? » | `historical_route` avec `year` 1990 et `compare_year` 2026 : `differences.outcome` répond (`both`, `lost`…) |
| « How long was the French network in 1950? » (en anglais) | `network_at_era {"year": 1950, "lang": "en"}` |
| « Puis-je réutiliser ces dates dans une application commerciale ? » | `sources_and_licences {}` : ODbL pour la base, sauf les faits d'un fournisseur à `open_licence: false` (archeoferroviaire : non commercial) |
| « Quelles lignes du Finistère ont fermé aux voyageurs dans les années 1930 ? » | `network_events {"department": "29", "type": "closure_passenger", "from": "1930", "to": "1939"}` |
| « Liste les lignes fermées de la Creuse. » | `list_lines {"department": "23", "status": "closed"}` (puis `next_cursor` tant que `has_more`) |
| « Combien de lignes ne portaient que du fret en 1930 ? » | `list_lines {"traffic": "freight", "era": 1930, "facets": true, "limit": 1}` : lire `facets` |
| « Quelles gares du Finistère existaient en 1930 ? » | `list_stations {"department": "29", "era": 1930}` |
| « Montre-moi des cartes postales anciennes de la gare de Brest. » | `media {"station": "brest", "type": "postcard"}` ; afficher chaque image avec son `attribution` |
| « Quelles locomotives électriques étaient les plus rapides ? » | `materials {"type": "locomotive_electrique", "sort": "-max_speed_kmh", "limit": 5}` |
| « Quels ont été les déraillements les plus meurtriers ? » | `accidents {"kind": "derailment", "min_deaths": 10, "sort": "-deaths", "limit": 5}` ; afficher `review_note` quand une fiche est « à vérifier » |
| « Y a-t-il eu des accidents sur Paris - Brest depuis 1950 ? » | `accidents {"line": "rfn-420000", "from": 1950}` puis `get_accident` pour les sources et la portion |
| « Combien de kilomètres ont fermé entre 1930 et 1980 ? » | `compare_eras {"from": 1930, "to": 1980}` : lire `changes.closed` et `changes.removed` |
| « À quel PK de Paris - Brest est ce point (48,10 ; -1,67) ? » | `locate_on_line {"line": "rfn-420000", "lat": 48.10, "lng": -1.67}` |
| « Quel matériel a circulé sur Lunéville - Saint-Dié ? » | `search {"query": "Lunéville"}` puis `materials {"line": "<slug>"}` et `get_material` pour la preuve |

## Authentification et quotas

Le serveur s'utilise sans compte ni jeton. Un **jeton personnel** de l'API, facultatif, relève la
limite de débit : un membre le crée depuis son profil (section « Jetons d'accès à l'API » de
`/profile`). Il ne sert qu'à lire, comme l'accès anonyme, et n'est affiché qu'une fois. Le serveur
MCP lit le même en-tête que l'API REST et GraphQL : `Authorization: Bearer <jeton>`.

| Profil | Limite (valeurs par défaut) | Compteur |
| --- | --- | --- |
| Sans jeton | 300 requêtes par minute (`API_RATE_LIMIT_ANONYMOUS`) | par adresse IP |
| Jeton d'un membre | 1 200 requêtes par minute (`API_RATE_LIMIT_MEMBER`) | par compte, tous ses jetons ensemble |
| Jeton d'un compte partenaire | 6 000 requêtes par minute (`API_RATE_LIMIT_PARTNER`) | par compte |

Les valeurs se règlent dans `config/api.php`. Le profil partenaire est accordé par un administrateur
du serveur (`php artisan vff:api-partner <compte>`, `--revoke` pour le retirer, `--list` pour la
liste). Le compteur est **commun** à `POST /mcp`, à l'API REST et à GraphQL : un appel MCP compte
comme une requête REST.

Chaque réponse HTTP porte `X-RateLimit-Limit` (la limite de l'appelant) et `X-RateLimit-Remaining`
(requêtes restantes dans la minute). Au-delà de la limite : **HTTP 429** avec `Retry-After` (attente
en secondes), avant toute réponse JSON-RPC. Un jeton inconnu, révoqué ou expiré donne **HTTP 401**
(`{"error": "invalid_token"}`) : l'appel n'est pas servi en anonyme, et il est compté sur le
compteur de l'adresse IP. Le jeton d'un compte suspendu donne **HTTP 403**
(`{"error": "account_suspended"}`) jusqu'à la fin de la suspension.

Claude Code, avec un jeton :

```bash
claude mcp add --transport http vff https://vff-project.org/mcp \
  --header "Authorization: Bearer $VFF_TOKEN"
```

Fichier de configuration (Claude Desktop, Cursor, VS Code) :

```json
{
  "mcpServers": {
    "vff": {
      "type": "http",
      "url": "https://vff-project.org/mcp",
      "headers": { "Authorization": "Bearer <votre jeton>" }
    }
  }
}
```

Essai en ligne de commande (`-i` affiche les en-têtes de limite) :

```bash
curl -si https://vff-project.org/mcp \
  -H "Authorization: Bearer $VFF_TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

Réponse du serveur de développement avec le jeton d'un membre (extrait) :

```http
HTTP/1.1 200 OK
X-RateLimit-Limit: 1200
X-RateLimit-Remaining: 1197
```

## Limites et sécurité

- **Lecture seule** : aucun outil n'écrit en base ; aucun compte, aucune contribution en attente,
  aucune donnée personnelle n'est exposée. Pas d'appel à un service externe.
- **Textes Wikipédia exclus** : la description d'une ligne (extrait de Wikipédia, CC BY-SA 4.0) n'est
  pas renvoyée par `get_line`, pour ne pas faire circuler un texte sous licence à partage dans les
  mêmes conditions sans sa mention. L'API REST et GraphQL la publient avec son auteur collectif, le
  lien de l'article, le permalien de la révision lue et la licence (`description_source`).
- **Débit** : 300 requêtes par minute et par adresse IP sans jeton, davantage avec un jeton
  personnel, sur un compteur commun à l'API REST et à GraphQL (voir « Authentification et quotas »).
  Au-delà : HTTP 429.
- **Taille** : 48 000 octets au plus par réponse (`MCP_MAX_RESPONSE_BYTES`). Au-delà, les listes les
  plus longues (événements, phases, gares…) sont raccourcies par la fin, et `truncated` le dit :
  réduisez la période, les types ou `max_events`. Les outils de liste ne perdent rien : leur
  `next_cursor` reprend au premier élément non renvoyé.
- **Validation stricte** : arguments inconnus refusés, types et bornes vérifiés (chaînes bornées,
  années 1700 à 2100 ou 1800 à 2100, listes de valeurs fermées).
- **Origine** : un navigateur d'une autre origine que le site est refusé (protection contre le
  « DNS rebinding » ; origines supplémentaires dans `MCP_ALLOWED_ORIGINS`). Les clients MCP hors
  navigateur ne sont pas concernés.
- **Liens** : `vff_url` vise toujours le site public (`VFF_PUBLIC_URL`, par défaut
  `https://vff-project.org`), y compris depuis un serveur local.

## Attribution et licences

La base de VFF est publiée sous **Open Database License (ODbL) 1.0**. En réutilisant une réponse :

1. citez « **Voies Ferrées de France (vff-project.org)** » avec le lien `vff_url` ;
2. reproduisez les mentions du bloc `attribution.sources` (toujours « © contributeurs
   OpenStreetMap » et SNCF Open Data, plus les fournisseurs des données renvoyées) ;
3. publiez sous ODbL toute base de données dérivée que vous diffusez ;
4. pour un fournisseur à `open_licence: false`, reprenez seulement avec sa mention et le lien de
   la source (`source_url`) ; les faits venant d'**archeoferroviaire** sont réservés à un usage
   **non commercial**.

Les instructions du serveur rappellent ces obligations aux assistants.

## Ce que le serveur ne couvre pas encore

- **Accidents sur la carte** (`/api/v1/accidents.geojson`) : GeoJSON volumineux, disponible par l'API REST seulement.
- **Détail par ligne d'une comparaison d'ères** et **tuiles** de comparaison : par l'API REST
  (`/api/v1/eras/{year}/compare/{other}/tiles/...`) ; `compare_eras` donne le résumé chiffré.
- **Chronologie seule d'une gare** (`/api/v1/stations/{slug}/events`) : `get_station` en donne
  l'essentiel ; pas d'outil dédié paginé.
- **Gares d'une ligne dans l'ordre des PK** (`/api/v1/lines/{slug}/stops`), **conversion d'un point
  en PK** (`/api/v1/lines/{slug}/pk`), **signalisation tranche par tranche**
  (`/api/v1/lines/{slug}/signalling`), **tronçons par ère** (`/api/v1/lines/{slug}/segments`) et
  **géométries** (GeoJSON, tuiles, GTFS) : disponibles par l'API REST, pas par le serveur MCP
  (réponses trop volumineuses pour un assistant).
- **Historique de la signalisation** par époque : pas encore dans les données.
- **Tuiles de la signalisation et des passages à niveau** (`/api/v1/eras/{year}/signalling/tiles/...`,
  `/api/v1/level-crossings/tiles/...`) : par l'API REST seulement.
- Pas d'invites (prompts) MCP ni de complétion d'arguments.

## Développement et tests

- Code : `app/Mcp/Servers/VffServer.php` (serveur), `app/Mcp/Tools/` (outils),
  `app/Mcp/Resources/` (ressources), `app/Mcp/Support/` (socle commun : validation, langue,
  attribution, plafond de taille, fiche de ligne). Textes : `lang/{fr,en,de}/mcp.php`.
- Services partagés avec le site et l'API : `CatalogSearch` (recherche), `LineStats`,
  `LinePhases`, `LineFacts` (fiche ligne), `StationSheet` (fiche gare), `NetworkStats::eraProfile`
  (réseau à une ère), `PathfindingService` (itinéraire), `SourceRights` (licences) ; pour les outils
  de liste, les services de l'API REST : `LineEventQuery`, `MediaQuery`, `LineDirectoryQuery`,
  `StationDirectoryQuery`, `MaterialQuery`, `AccidentQuery` et leurs ressources (`LineEventResource`,
  `MediaResource`, `MaterialResource`, `MaterialDetailResource`, `AccidentResource`, `AccidentDetailResource`) ;
  `EraComparison` (comparaison d'ères). Présentation des accidents : `App\Mcp\Support\AccidentView`.
- Tests : `VFF_TEST_DB=vff_xxx_test bin/php artisan test tests/Feature/Mcp tests/Unit/Mcp`.
- `tools/list` tient en une seule page (`VffServer::$defaultPaginationLength` = 50) : le défaut du paquet (15) aurait
  laissé des outils hors de la première page pour les clients qui ne suivent pas `nextCursor`.
- Ajouter un outil : une classe dans `app/Mcp/Tools/` qui étend `App\Mcp\Support\VffTool`
  (`rules()`, `arguments()`, `answer()`), déclarée dans `VffServer::$tools`, avec sa description
  détaillée (contenu, unités, limites, exemple), ses tests et sa ligne dans ce document.
