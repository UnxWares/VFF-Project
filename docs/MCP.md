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
6. [Limites et sécurité](#limites-et-sécurité)
7. [Attribution et licences](#attribution-et-licences)
8. [Ce que le serveur ne couvre pas encore](#ce-que-le-serveur-ne-couvre-pas-encore)
9. [Développement et tests](#développement-et-tests)

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
| `search` | Trouver une ligne, une gare ou un matériel par son nom (identifiants pour les autres outils). |
| `get_line` | Fiche d'une ligne : longueurs, électrification, signalisation, phases datées, chronologie. |
| `line_events` | Événements d'une ligne sur une période. |
| `get_station` | Fiche d'une gare : dates, statut par décennie, lignes desservies, sources. |
| `network_at_era` | État du réseau à une époque (km par état et par trafic, gares par statut). |
| `historical_route` | Itinéraire entre deux gares sur le réseau d'une époque. |
| `sources_and_licences` | Fournisseurs de données et licences. |
| `network_events` | Événements de toutes les lignes (département, type, période), paginés. |
| `media` | Photos, cartes postales, plans d'une ligne ou d'une gare, avec leurs droits. |
| `list_lines` | Annuaire des lignes (réseau, état, trafic, département, époque). |
| `list_stations` | Annuaire des gares (statut, département, époque). |
| `materials` | Séries de matériel roulant filtrées et triées. |
| `get_material` | Fiche d'une série de matériel, avec les lignes parcourues et leurs preuves. |

### `search` : rechercher une ligne, une gare ou un matériel

Point de départ : trouve les identifiants attendus par les autres outils.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `query` | chaîne, 2 à 100 caractères | oui | Nom de ligne, code de ligne à 6 chiffres, nom de gare ou de matériel. Casse et accents ignorés. |
| `kinds` | liste parmi `lines`, `stations`, `materials` | non | Catégories cherchées (par défaut : les trois). |
| `limit` | entier 1 à 20 | non | Résultats par catégorie (8 par défaut). |
| `lang` | `fr`, `en`, `de` | non | Langue (fr par défaut). |

Renvoie `lines` (`slug`, `name`, `code`, `vff_url`), `stations` (`slug`, `name`, `city`,
`department_code`, `lat`, `lng`, `vff_url`), `materials` (`slug`, `name`, `kind`, `builder`,
`vff_url`). Tri par pertinence : nom exact, début du nom, mot du nom, reste ; à pertinence égale,
lignes en service d'abord.

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
  `ETCS_2`…, avec km), `train_protection_km` (contrôle de vitesse, ex. `KVB`), `kvb`, `source`
  (`sncf`, SNCF Réseau Open Data) ;
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
`name`, `code`, `first_year`, `last_year`, `pk_km`, `served_today`) et `sources` (`source`, `ref`,
`name`, `url`, `distance_m` = écart à la position retenue).

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
cette ère), `available_eras`, `vff_url` (la carte à cette époque).

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

`network_events`, `media`, `list_lines`, `list_stations` et `materials` reprennent les filtres et les données
de l'API REST (`/api/v1/events`, `/api/v1/media`, `/api/v1/lines`, `/api/v1/stations`, `/api/v1/materials`) : mêmes
services (`LineEventQuery`, `MediaQuery`, `LineDirectoryQuery`, `StationDirectoryQuery`, `MaterialQuery`), mêmes
règles de validation, mêmes champs. Ils paginent **par curseur** : `limit` éléments par appel ;
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
| `type` | catégorie | non | `locomotive_vapeur`, `locomotive_diesel`, `locomotive_electrique`, `automotrice`, `autorail`, `rame_tgv`, `voiture`, `wagon`, `tramway`, `engin_service`. |
| `builder`, `operator` | chaîne | non | Constructeur ; exploitant (identifiant Wikidata exact ou nom partiel). |
| `line` | slug | non | Séries attestées sur la ligne par une source. |
| `in_service_at` | année | non | En service cette année-là. |
| `from_year`, `to_year` | années | non | Mise en service, bornes incluses. |
| `gauge_mm` | entier 500 à 2000 | non | Écartement. |
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
| « Pouvait-on aller de Limoges à Angoulême en 1990 ? Et aujourd'hui ? » | `historical_route` avec `year` 1990, puis sans `year` ; une erreur `no_path` ou `station_not_in_network` répond « non » |
| « How long was the French network in 1950? » (en anglais) | `network_at_era {"year": 1950, "lang": "en"}` |
| « Puis-je réutiliser ces dates dans une application commerciale ? » | `sources_and_licences {}` : ODbL pour la base, sauf les faits d'un fournisseur à `open_licence: false` (archeoferroviaire : non commercial) |
| « Quelles lignes du Finistère ont fermé aux voyageurs dans les années 1930 ? » | `network_events {"department": "29", "type": "closure_passenger", "from": "1930", "to": "1939"}` |
| « Liste les lignes fermées de la Creuse. » | `list_lines {"department": "23", "status": "closed"}` (puis `next_cursor` tant que `has_more`) |
| « Combien de lignes ne portaient que du fret en 1930 ? » | `list_lines {"traffic": "freight", "era": 1930, "facets": true, "limit": 1}` : lire `facets` |
| « Quelles gares du Finistère existaient en 1930 ? » | `list_stations {"department": "29", "era": 1930}` |
| « Montre-moi des cartes postales anciennes de la gare de Brest. » | `media {"station": "brest", "type": "postcard"}` ; afficher chaque image avec son `attribution` |
| « Quelles locomotives électriques étaient les plus rapides ? » | `materials {"type": "locomotive_electrique", "sort": "-max_speed_kmh", "limit": 5}` |
| « Quel matériel a circulé sur Lunéville - Saint-Dié ? » | `search {"query": "Lunéville"}` puis `materials {"line": "<slug>"}` et `get_material` pour la preuve |

## Limites et sécurité

- **Lecture seule** : aucun outil n'écrit en base ; aucun compte, aucune contribution en attente,
  aucune donnée personnelle n'est exposée. Pas d'appel à un service externe.
- **Débit** : 300 requêtes par minute et par adresse IP sur `POST /mcp` (la même limite que l'API
  REST et GraphQL ; `MCP_RATE_LIMIT`). Au-delà : HTTP 429.
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

- **Accidents** : le modèle, le service `App\Services\Api\AccidentQuery` et l'API REST
  `/api/v1/accidents` sont en cours d'écriture (nuit du 19 au 20/11/2026) ; un outil `accidents`
  (par ligne, période, département, nature, bilan) est à ajouter sur le socle `VffTool` avec
  `AccidentQuery` et `AccidentResource` dès qu'ils seront stables et testés.
- **Chronologie seule d'une gare** (`/api/v1/stations/{slug}/events`) : `get_station` en donne
  l'essentiel ; pas d'outil dédié paginé.
- **Gares d'une ligne dans l'ordre des PK** (`/api/v1/lines/{slug}/stops`), **conversion d'un point
  en PK** (`/api/v1/lines/{slug}/pk`), **signalisation tranche par tranche**
  (`/api/v1/lines/{slug}/signalling`), **tronçons par ère** (`/api/v1/lines/{slug}/segments`) et
  **géométries** (GeoJSON, tuiles, GTFS) : disponibles par l'API REST, pas par le serveur MCP
  (réponses trop volumineuses pour un assistant).
- **Historique de la signalisation** par époque : pas encore dans les données.
- Pas d'invites (prompts) MCP ni de complétion d'arguments.

## Développement et tests

- Code : `app/Mcp/Servers/VffServer.php` (serveur), `app/Mcp/Tools/` (outils),
  `app/Mcp/Resources/` (ressources), `app/Mcp/Support/` (socle commun : validation, langue,
  attribution, plafond de taille, fiche de ligne). Textes : `lang/{fr,en,de}/mcp.php`.
- Services partagés avec le site et l'API : `CatalogSearch` (recherche), `LineStats`,
  `LinePhases`, `LineFacts` (fiche ligne), `StationSheet` (fiche gare), `NetworkStats::eraProfile`
  (réseau à une ère), `PathfindingService` (itinéraire), `SourceRights` (licences) ; pour les outils
  de liste, les services de l'API REST : `LineEventQuery`, `MediaQuery`, `LineDirectoryQuery`,
  `StationDirectoryQuery`, `MaterialQuery` et leurs ressources (`LineEventResource`,
  `MediaResource`, `MaterialResource`, `MaterialDetailResource`).
- Tests : `VFF_TEST_DB=vff_nuit_apicomplet_test bin/php artisan test tests/Feature/Mcp tests/Unit/Mcp`.
- Ajouter un outil : une classe dans `app/Mcp/Tools/` qui étend `App\Mcp\Support\VffTool`
  (`rules()`, `arguments()`, `answer()`), déclarée dans `VffServer::$tools`, avec sa description
  détaillée (contenu, unités, limites, exemple), ses tests et sa ligne dans ce document.
