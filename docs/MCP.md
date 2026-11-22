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
5. [Invites (prompts)](#invites-prompts)
6. [Exemples : questions et appels](#exemples--questions-et-appels)
7. [Authentification et quotas](#authentification-et-quotas)
8. [Limites et sécurité](#limites-et-sécurité)
9. [Attribution et licences](#attribution-et-licences)
10. [Ce que le serveur ne couvre pas encore](#ce-que-le-serveur-ne-couvre-pas-encore)
11. [Développement et tests](#développement-et-tests)

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
| `get_line` | Fiche d'une ligne : longueurs, électrification, signalisation, phases datées, gares, chronologie. |
| `line_events` | Événements d'une ligne sur une période. |
| `get_station` | Fiche d'une gare : dates, statut par décennie, lignes desservies et dates de desserte, sources. |
| `network_at_era` | État du réseau à une époque (km par état et par trafic, gares par statut). |
| `historical_route` | Itinéraire entre deux gares sur le réseau d'une époque, ou le même trajet comparé à deux époques. |
| `sources_and_licences` | Fournisseurs de données et licences. |
| `network_events` | Événements de toutes les lignes (département, type, période), paginés. |
| `media` | Photos, cartes postales, plans d'une ligne, d'une gare, d'une série de matériel ou d'un événement, avec leurs droits. |
| `list_lines` | Annuaire des lignes (réseau, état, trafic, département, époque). |
| `list_stations` | Annuaire des gares (statut, département, époque). |
| `materials` | Séries de matériel roulant filtrées et triées. |
| `get_material` | Fiche d'une série de matériel, avec les lignes parcourues, leurs preuves et leurs périodes de circulation. |
| `line_materials` | Matériel roulant d'une ligne, avec les périodes de circulation de chaque série (début, fin, précision). |
| `accidents` | Accidents et événements graves du rail (ligne, période, nature, département, bilan), paginés. |
| `get_accident` | Fiche d'un accident : lignes rattachées, sources lues et leur licence, points à vérifier, contexte sourcé (archives, enquêtes, presse). |
| `compare_eras` | Ce qui a changé sur le réseau entre deux ères : km ouverts, fermés, déposés, restés en service. |
| `level_crossings` | Passages à niveau publiés par SNCF Réseau (classe, PK, commune), par ligne, groupe, commune ou emprise, paginés. |
| `line_profile` | Profil en long d'une ligne : altitudes le long du tracé, dénivelés, déclivité la plus forte, altitude des gares (IGN). |
| `line_audit` | Audit des tronçons d'une ligne à une époque : classement, placement, exactitude et tracé de chaque tronçon, avec leurs preuves. |
| `segment_audits` | Audit des tronçons de tout le réseau à une époque, les plus mal notés d'abord, filtrés par note, contrôle, raison, état, département, région, ligne ; paginés par `limit` et `offset`. |
| `station_audits` | Audit des gares de tout le réseau : note de placement, écarts, notes des dates, contrôles en échec, filtrés par note, contrôle, département, région, ligne ; paginés par `limit` et `offset`. |
| `line_greenways` | Voies vertes OpenStreetMap sur l'ancienne emprise d'une ligne fermée ou disparue : longueur sur l'emprise, nom, année d'ouverture, source ODbL. |
| `line_infrastructure` | Nombre de voies et liaison radio sol-train (GSM-R…) d'une ligne par portion de PK, aujourd'hui : SNCF Réseau, ODbL. |
| `line_archives` | Documents numérisés dans Gallica (BnF) dont le titre désigne une ligne : titre, date et précision, type, auteurs, cote, lien vers Gallica, score de rattachement. |
| `line_archive_records` | Notices des fonds d'archives (Archives SNCF : dossiers, photographies, films) dont le titre désigne une ligne : titre, type, cote, classement, date et précision, lien vers archives.sncf.com, score de rattachement. |
| `line_stories` | Histoire d'un événement d'une ligne : images de la ligne datées à 5 ans au plus et documents de Gallica de la période, chacun avec son écart en années. |
| `journal` | Articles longs du journal de bord (liste, ou article complet avec son texte en HTML, ses sources en notes et les fiches citées). |
| `locate_on_line` | Point kilométrique d'un lieu (latitude, longitude) sur le tracé d'une ligne. |
| `signalling_history` | Mises en service datées de signalisation d'une ligne (Wikipédia), et faits en vigueur à une époque, ceux que montre la carte. |
| `list_departments` | Régions et départements (codes INSEE et noms) à passer dans `department` et `region`, avec le nombre de lignes, de gares et d'accidents de chacun. |

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
  `particulier`), `is_lgv`, `is_threatened` (ligne menacée de fermeture que VFF soutient ; le message
  de soutien n'est pas publié) ;
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
  Open Data) ; `history` : mises en service datées lues dans l'article Wikipédia de la ligne (voir
  ci-dessous), liste vide si l'article n'en date aucune ;
- `facts` : faits encyclopédiques (`key`, valeur `km`/`kmh`/`mm`/`n`/`year`/`list`, `source`
  `vff`/`sncf`/`wikidata`, `item` quand l'élément Wikidata ne décrit qu'une partie de la ligne,
  `label`, `note`) ;
- `phases` : chaque date où le fer ou le trafic change sur une portion (`date`, `precision`,
  `when`, `kind` : `growth`, `traffic`, `decline`, `removal`, `heritage` ; `changes` : `from` et
  `to` (catégories), `length_km`, `event_id`, `source`, `source_url` ; `in_service_after_km`),
  `best_phase` (indice de la phase du plus grand développement) ;
- `decades` : `year`, `in_service_km`, `by_category_km` ;
- `stations` : gares de la ligne dans l'ordre du tracé (`slug`, `name`, `pk_km`, `is_terminal`,
  `served_from`, `served_until`, `served_until_kind`, `years_source`, `service_start`,
  `service_end`, `status_today`), avec les mêmes dates de desserte que `get_station` (voir
  ci-dessous) ;
- `event_count`, `events` (même forme que `line_events`), `events_omitted`.

`signalling.history` est un historique à part de l'état actuel (`block_systems_km`, SNCF Réseau) : il
n'en corrige rien. Chaque fait : `system` (`BAL`, `BAPR`, `BM`, `CT`, `TVM-300`, `TVM-430`, `TVM`,
`ETCS_L1`, `ETCS_L2`, `ETCS`, `KVB`), `role` (`block` cantonnement, `cab` signalisation en cabine, TVM et
ETCS, `speed_control` contrôle de vitesse : le KVB n'est jamais un cantonnement), `date` et
`date_precision` (`day`, `month`, `year` : le jour et le mois manquants valent `01`), `reliability` (`high`
jour ou mois donné ; `medium` année seule, date reprise d'une phrase voisine, qui peut être au jour, ou date
déduite de l'ouverture ; les faits douteux ne sont pas exposés), `origin` (`text`, `list`, `table`, `infobox`,
`opening` : TVM d'une ligne à grande vitesse, en service dès l'ouverture), `scope` (`whole_line`, `section`,
`unspecified`), `section` (`from`, `to` tels qu'écrits ; `location` : `stations` placée par ses gares
d'extrémité, `article_pk` PK de l'article, `unlocated` non placée ; `pk_start_m`, `pk_end_m` de la portion
placée ; `from_station`, `to_station` avec `slug`, `name`, `pk_m`) et `source` (`title`, `url` permalien de la
révision lue, `revision` ; pas de phrase de l'article, comme pour la description des lignes : l'API REST la
donne). Relevé partiel : un fait absent ne veut pas dire qu'aucun système n'a été mis en service. L'outil
`signalling_history` donne les mêmes faits et ceux en vigueur à une époque. Wikipédia étant citée dans
`attribution`, reprenez la mention CC BY-SA 4.0.

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
date, précision et `when`, la date rédigée à sa seule précision ; une fermeture de précision `range`
n'est connue que par un intervalle : `date` vaut `null`, `from_year` et `to_year` donnent les bornes en
années, `to_year` est nul quand la date est inconnue, et `when` dit « entre 1980 et 1989 » ou « date
inconnue, après 1870 », jamais un jour ; une ouverture `range` (ouverture déduite des lignes et bornée par
la carte) se lit de même, avec `to_year` toujours donné et `when` « entre 1847 et 1860 » ; `opened_after`
vaut alors l'année après laquelle la gare a ouvert, et `null` pour toute autre précision), `eras` (statut à chaque décennie ; `uncertain` = non confirmé par les
voies de l'époque), `events` (chronologie : `type`, `label`, date, `source`, `source_url`,
`inferred` = date déduite des lignes et non d'une source sur la gare, `note`), `lines` (`slug`,
`name`, `code`, `first_year`, `last_year`, `last_year_kind`, `years_source`, `pk_km`, `served_today` ;
`last_year_kind` vaut `year` pour une année tirée d'une date, `upper_bound` pour « au plus tard cette
année », `undated` pour une fin non datée où l'année n'est que celle du calcul ; `years_source` vaut
`station` quand les années viennent des dates de la gare, faute d'événement daté de la ligne ;
`service_start` et `service_end` : voir plus bas) et `sources` (provenance brute,
lecture seule : `source`, `ref` = identifiant dans la source, `name`, `url`, `distance_m` = écart à la
position retenue, `match_method` = comment la source a été rapprochée de la gare, `fetched_at` = date
de lecture par VFF, `raw` = attributs bruts reçus de la source, de forme variable). `station` porte
aussi `attribute_sources` (quelle source a fourni chaque attribut, recalage de la position compris)
et `position` (`source`, `spread_m`, `flagged` = position à vérifier). `media_count` donne le nombre
d'images publiées de la gare : l'outil `media` avec `station` les renvoie, avec leurs crédits.
`platforms` reprend la clé `platforms` de `GET /api/v1/stations/{station}` : quais de la gare d'après le jeu
« Liste des quais » de SNCF Réseau (ODbL, état actuel, `vff:import-sncf-platforms`), avec `count` (quais distincts ;
un quai central qui borde deux voies compte une fois), `longest_m`, `total_length_m`, `items` (`label` SNCF,
`code_ligne` de la voie bordée, `length_m`, `pk_start_m` et `pk_end_m` en mètres), `dataset_modified`, `attribution`
(mention « SNCF Réseau » à reproduire) et `provenance`. Elle vaut `null` quand le jeu ne donne aucun quai pour la gare,
ce qui ne prouve pas qu'elle n'en a pas.
`audit` reprend `GET /api/v1/stations/{station}/audit` (contrôles croisés `checks[].code`, dont `outlier_source`, une source précise isolée loin des autres, et `closure_sncf_referential`, fermeture tirée du référentiel SNCF) (nul tant que la gare n'a pas été auditée,
voir docs/AUDIT-GARES.md) : `placement` (`grade` : `agree` une source indépendante à moins de
20 m, `close` entre 20 et 100 m, `isolated` aucune source indépendante proche, `disagree` une
source précise à plus de 100 m ou une grossière à plus de 1 km, `none` ; écarts en mètres et une
position par source, coordonnées seulement si la source est redistribuable), `dates`
(`opening_grade`, `closure_grade` : `sourced`, `estimated`, `unknown`, `not_applicable` ;
`services.passenger` et `services.freight`), `checks` (contrôles croisés en échec) et
`date_candidates` (dates de Wikidata P1619 et P3999 comparées à celles de VFF, sans les
remplacer).

Dates de desserte (`lines[].service_start` et `service_end` de `get_station`, `stations[]` de
`get_line`) : la date de l'événement de ligne qui ouvre ou termine la desserte de la gare, avec
`date` (`AAAA-MM-JJ`), `precision` (`day`, `month`, `year`), `when` (la date rédigée à cette
précision, jamais un jour pour une date au mois ou à l'année) et `event_id`, l'identifiant de cet
événement (le même `id` que dans `events` de `get_line` et `line_events`, et
`/api/v1/events/{id}` en REST). L'objet vaut `null` quand seule l'année est sûre : année tirée d'un
tracé daté ou des dates de la gare, gare ouverte (ou fermée) la même année à une date qui pourrait
tomber après l'ouverture (avant la fin) de la ligne, fin qui n'est qu'une borne (`last_year_kind`
autre que `year`), ligne qui n'a jamais desservi la gare. La règle est la même dans l'API REST et
GraphQL. Exemple réel, Saint-Calais sur la ligne de Bessé-sur-Braye à Saint-Calais (`lo-507000`) :

```json
{"slug": "lo-507000", "first_year": 1879, "last_year": 1938, "last_year_kind": "year",
 "service_start": {"date": "1879-03-31", "precision": "day", "when": "31 mars 1879", "event_id": 416},
 "service_end": {"date": "1938-05-15", "precision": "day", "when": "15 mai 1938", "event_id": 417}}
```

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
| `region` | code INSEE de la région | non | `53`, `84`, `01` : lignes citant l'un de ses départements ou y desservant une gare. Codes et noms : `list_departments`. |
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
| `line`, `station` ou `material` | slug | non | Médias d'une ligne, d'une gare ou d'une série de matériel (un seul des quatre filtres de rattachement). |
| `event` | entier | non | Images d'un événement daté d'une ligne (`id` des événements de `get_line` ou `line_events`). Exclusif avec les trois autres. |
| `type` | `photo`, `postcard`, `plan`, `map`, `document` | non | Nature. |
| `provider` | chaîne | non | `commons`, `upload`, `argentanwebferro`… |
| `license` | chaîne | non | Licence exacte, casse ignorée (`CC BY-SA 4.0`). |
| `rights_status` | `public_domain`, `open_license`, `unknown` | non | État des droits. Les images que le site montre avec une simple autorisation de leur auteur (`permission`) ne sont jamais servies par cet outil, ni par `GET /api/v1/media` ni par GraphQL. |
| `commercial_ok` | booléen | non | Réutilisation commerciale permise ou non. |
| `period` | `pre1900`, `1900_1944`, `1945_1969`, `1970_1999`, `since2000`, `undated` | non | Époque représentée. |
| `from_year`, `to_year` | entiers | non | Année représentée, bornes incluses. |
| `featured` | booléen | non | `true` : seulement les médias mis en avant (images que l'accueil peut montrer) ; `false` : les autres. |
| `facets` | booléen | non | `true` : ajoute `facets`, les compteurs de la recherche (`total`, `types` par nature, `licenses`, 30 premiers `authors`), comme `meta.facets` de `GET /api/v1/media?facets=true` et `mediaFacets` en GraphQL. |
| `order` | `asc`, `desc` | non | Ordre des identifiants. |
| `limit` | entier 1 à 60 | non | Médias par appel (20 par défaut). |
| `cursor` | chaîne opaque | non | Page suivante. |

Chaque média : `title`, `caption`, `thumb_url`, `display_url`, `source_url` (page d'origine),
dimensions, `depicted_year`, `author`, `license`, `license_url`, `attribution`, `rights_status`,
`commercial_ok`, `share_alike`, `location`, `subject` (ligne, gare, série ou événement) et
`contribution_id` (numéro de la contribution validée qui a publié l'image, `null` pour un import).
Une image proposée et encore en attente de validation n'est jamais renvoyée. **Affichez toujours
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
| `region` | code INSEE de la région | non | Lignes citant l'un de ses départements ou y desservant une gare. Codes et noms : `list_departments`. |
| `era` | décennie 1830 à 2030 | non | Ère de référence (par défaut : aujourd'hui). |
| `sort` | `name`, `code`, `slug` | non | Tri (`name` : alphabétique français ; `code` : lignes sans code en dernier). |
| `order` | `asc`, `desc` | non | Sens du tri. |
| `facets` | booléen | non | Ajoute les compteurs de la recherche. |
| `limit` | entier 1 à 100 | non | Lignes par appel (25 par défaut). |
| `cursor` | chaîne opaque | non | Page suivante. |

Renvoie `era_year` et `lines` : `slug`, `code`, `name`, `network`, `historical_operator`,
`regions`, `departements`, `state` (`open`, `closed`, `unknown` : sans tracé à cette ère),
`traffic`, `heritage`, `vff_url` ; avec `facets` : `total`, `open`, `closed`, `unknown`,
`networks`, `traffics`, `heritage`, `departments` (lignes par département, sans le filtre `department`).

```json
{"traffic": "freight", "era": 1930, "facets": true}
```

### `list_stations` : annuaire des gares

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `q` | chaîne, 100 au plus | non | Nom, commune, autre nom ou début du code UIC. |
| `status` | `active`, `tourist`, `no_commercial`, `declassified`, `gone`, `planned` | non | Statut (avec `era` : les quatre premiers). |
| `department` | code INSEE en majuscules | non | Département de la gare. |
| `region` | code INSEE de la région | non | Gares de ses départements. Codes et noms : `list_departments`. |
| `era` | décennie 1830 à 2030 | non | Statut à cette ère ; gares absentes de l'ère exclues. |
| `flagged` | booléen | non | Position à vérifier. |
| `sort` | `name`, `city`, `opened`, `closed` | non | Tri (valeurs manquantes en dernier). |
| `order` | `asc`, `desc` | non | Sens du tri. |
| `facets` | booléen | non | Ajoute le nombre de gares par statut (`facets`) et par département (`department_facets`, sans le filtre `department`). |
| `limit` | entier 1 à 100 | non | Gares par appel (25 par défaut). |
| `cursor` | chaîne opaque | non | Page suivante. |

Chaque gare : `slug`, `name`, `type`, `status`, `city`, `department_code`, `uic_code`,
`wikidata_id`, `opened_at`/`closed_at` et leur précision, `opened_after` (ouverte après cette année, précision `range`), `closed_after`, `lat`, `lng`,
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
(provenance de chaque champ ; `contrib` pour une valeur corrigée par une contribution validée, référence
`contribution:<id>`) et `lines` (lignes parcourues, une entrée par lien et par source, chaque lien avec sa `source`
telle quelle (`wikidata`, `wikipedia` ou `contrib`), sa preuve `evidence` et sa fiabilité `confidence`, et `periods`,
ses périodes de circulation datées : voir `line_materials`, et `relations`, les relations que la série assurait sur
la ligne, décrites dans `line_materials`). `lines_count` compte les lignes, pas les liens.
`notable_units` liste les rames ou engins remarquables de la série (par exemple la rame 01 « Patrick » du TGV
Sud-Est ; liste vide pour la plupart des séries) : `number`, `nickname`, `facts` et `unknown`. Chaque fait donne
`kind`, `date` et `date_to` (AAAA-MM-JJ, au 1er du mois ou de l'année selon `precision` : `day`, `month`, `year`, nulle
sans date), `text` (le fait reformulé en français) et `source` (`publisher`, `reported_by` quand la page rapporte un
autre média, `date` de publication, `url` à citer, `provider`, `license`, `license_status`). La citation courte que
donne l'API REST (`quote`) n'est pas renvoyée. `unknown` dit ce que les sources ne disent pas : n'y suppléez pas.
`search {"query": "Patrick"}` trouve la série, avec `notable_unit` (numéro et surnom de la rame).
`media_count` donne le nombre d'images publiées de la série : l'outil `media` avec `material` les renvoie. Série
inconnue : erreur `material_not_found` avec `suggestions`.

Chaque preuve donne l'article, la révision lue (`revid`), la section et le permalien `url`, mais pas la
phrase de l'article : l'API REST la renvoie dans `quote`, le serveur MCP jamais (décision Q22, voir
« Limites et sécurité »).

```json
{"material": "bb-15000"}
```

### `line_materials` : matériel roulant d'une ligne et périodes de circulation

Même liste que `GET /api/v1/lines/{line}/materials`, sans les phrases citées (décision Q22 : aucun extrait de
Wikipédia dans les réponses du serveur MCP). Les périodes sont calculées par `vff:import-material-periods` à partir des
phrases des articles Wikipédia de la ligne et des séries (faits seulement, CC BY-SA 4.0) et des qualificatifs de début
et de fin des déclarations Wikidata P3438 (CC0).

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `type` | catégorie | non | Catégorie de matériel (`autorail`, `locomotive_diesel`…). Toutes par défaut. |
| `lang` | `fr`, `en`, `de` | non | Langue des libellés et des messages. |

Renvoie `line`, `total` (séries attestées), `dated` (séries qui ont au moins une période datée) et `materials` : une
entrée par série, même reliée par plusieurs sources, avec `material` (`slug` à passer à `get_material`, `name`,
`type`, `type_label`), `confidence`, `source` et `source_url` du lien le plus sûr (permalien de la révision lue,
élément Wikidata, ou adresse de la source citée par un contributeur, nulle pour un ouvrage papier), `links` (chaque
lien de la série : `source` `wikipedia`, `wikidata` ou `contrib`, `confidence`, `source_url`) et `periods` (celles de
tous ses liens). Une période donne `start_date`
et `end_date` (`AAAA-MM-JJ`, l'une peut être nulle) avec `start_precision` et `end_precision` (`day`, `month` : date au
1er du mois, `year` : date au 1er janvier), `section` (portion nommée par le titre de section de l'article, sinon
nulle), `confidence` (`high` : jour ou mois, `medium` : année), `origin` (`line_article`, `material_article`,
`wikidata`, `contribution`), `relation` (relation assurée pendant la période quand la phrase datée en cite une :
`label` comme « Paris-Montparnasse - Granville », gares d'extrémité `from` et `to` avec leur `id` et leur `name` tels
qu'en base, `service` comme `TER`, `Intercités`, `express`, `omnibus` ou `fret`, sinon nulle), `source` (`title`,
`revision`, `url`) et `provenance`. Seules les périodes sûres sont données ; une fin
nulle ne veut pas dire que la série circule encore.

Chaque série porte aussi `relations` : les relations qu'elle assurait sur la ligne, lues dans l'article Wikipédia de
la série. Une relation entre deux gares (« Relation Argentan - Granville ») est rattachée aux lignes du plus court
chemin du graphe de l'époque, seulement s'il n'existe pas d'autre itinéraire raisonnable ; un lien vers un article
d'itinéraire (« Ligne de Paris à Granville ») est rattaché aux lignes qu'il enchaîne
(`database/data/wikipedia_itineraries.php`). Chaque entrée donne `kind` (`relation` ou `itinerary`), `label`,
`from`, `to`, `service`, `title` (article de l'itinéraire), `periods` (début, fin et précision des périodes qui la
datent) et `notes` (faits sourcés sur la relation, hors Wikipédia, tirés de `database/data/material_relation_notes.php` :
`kind` `fuel`, `fuel`, `feedstock`, `since`, `reported` avec leur précision, et `sources` avec `title`, `publisher`,
`date`, `url` et les droits du fournisseur ; exemple réel : le Régiolis sur Paris - Granville, `fuel` `B100`,
`feedstock` `rapeseed`, depuis avril 2021, bilan du 17 janvier 2023). Liste vide si aucune relation n'est citée.
Les séries viennent de la plus récente à la plus ancienne (encore en service sans fin connue sur la ligne, puis fin
la plus récente, puis début), comme sur la fiche de la ligne et dans l'API REST. `end` donne la fin telle que les
sources la connaissent : `line` (fin datée sur la ligne, `year`), `series_in_service`, `series_withdrawn` (retrait de
la série, jamais présenté comme la fin sur la ligne ; `year` nulle si seul l'état est connu) ou `unknown`, avec
`source`, `source_url` et `since` (début de la période ouverte sur la ligne, nul sinon). L'ordre tient d'abord à la
présence datée sur la ligne, puis seulement à l'état de la série : une série encore en service ailleurs mais
remplacée ici ne passe pas devant (Paris - Granville : Régiolis à partir de 2014, X 72500 de 1999 à 2015). `material.image` : vignette Commons (`url`, `width`, `height`) et son crédit (`author`,
`license`, `source_url`), nulle sans photo. Exemple réel : sur la ligne de Grenoble à Montmélian
(`rfn-909000`), les CC 72000 ont une période du 20 août 1982 au 30 mai 1986 (`high`), les BB 67300 une fin au
2 mai 2016 (`high`). Ligne inconnue : erreur `line_not_found` avec `suggestions`.

```json
{"line": "rfn-909000"}
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
| `region` | code INSEE de la région | non | Lieu dans l'un de ses départements (`11`). Codes et noms : `list_departments`. |
| `station` | slug | non | Accidents dont cette gare est la gare la plus proche du lieu (10 km au plus) ; gare inconnue : erreur. |
| `kind` | nature | non | `collision`, `derailment`, `level_crossing`, `fire`, `explosion`, `structure_failure`, `attack`, `war_action`, `person_struck`, `runaway`, `incident`, `other`. |
| `review` | `ok`, `to_review` | non | Fiches dont la date et le lieu sont confirmés (BEA-TT ou deux sources indépendantes), ou « à vérifier ». |
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
les donne ; `0` : aucun), `toll_source` (`bea-tt`, `wikidata`, `wikipedia`, `manual`), `review_status`, `url`. Aucune victime
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

`context` : contexte sourcé (`vff:enrich-accidents`), rédigé par VFF à partir de documents lus, dans la langue
demandée. `checked_on` (date de la recherche) ; `sections` (`circumstances`, `course`, `victims`, `causes`,
`aftermath`, `memory`, `disagreements`), chaque énoncé avec `text` et `references` (clés des documents) ;
`references` (documents : `key`, `provider`, `kind`, `reliability`, `title`, `publisher`, `author`, `published`,
`locator`, `url`, `access` = `open`, `paywall` ou `link_only`, `consulted_on`, `note`, `license`, `license_status`) ;
`images` (Wikimedia Commons : `image_url`, `author`, `license`, `caption`, `attribution`) ; `corrections` (champ,
valeur d'avant, valeur retenue, raison). Les courtes citations servies par l'API REST et GraphQL (`quote`) ne le sont
pas ici (décision Q22). Listes vides si la fiche n'a pas de contexte. Les fournisseurs de ces documents s'ajoutent au
bloc d'attribution.

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
| `pk_m` | entier | non | PK en mètres : ajoute `altitude_at_pk`, l'altitude de la ligne à ce PK (null si elle est inconnue). |
| `pk_from_m`, `pk_to_m` | entiers | non | PK de début et de fin en mètres, bornes incluses (`pk_to_m` au moins égal à `pk_from_m`) : ajoute `samples`, le référentiel point par point entre ces PK, allégé à `max_points`. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Le profil suit le tracé de référence de la ligne entière, toutes époques (lignes fermées et déposées comprises) ;
les morceaux séparés par un trou sont gardés, la courbe s'interrompt dans le trou (`gaps`), et les embranchements
suivent bout à bout (`branches`). Renvoie `line`, `profile` (`basis`, `step_m`, `length_m`, `trace_length_m`,
`covered_m`, `off_path_m`, `pk_anchors`, `summary` avec altitudes extrêmes, dénivelés et déclivité la plus forte en
‰ sur 1 km mesurée sur la voie (nulle quand elle n'est pas publiée, motif dans `max_grade_withheld` : `terrain_only`,
`tunnel`, `interpolated`, `rack` ; motif nul aussi si aucune portion de 1 km n'est exploitable) et méthode des dénivelés (`climb_method` : altitudes du terrain moyennées sur 1 km,
puis variations de moins de 3 m ignorées), `rack` (ligne à crémaillère : type, étendue, déclivité la plus forte
publiée par la source et lien vers l'article de Wikipédia lu ; null pour une ligne à adhérence), `sourced_grade`
(quand la déclivité mesurée n'est pas publiée pour cause de terrain seul, de tunnel ou de points interpolés : déclivité
la plus forte selon une source écrite, en ‰, avec `covered_share`, part de la ligne décrite par la source, et `source` :
`wikipedia`, infobox ou texte de l'article, permalien de la révision lue, CC BY-SA 4.0 ; ou `sncf`, segment le plus
raide du jeu « Caractéristique des voies et déclivité » de SNCF Réseau, ODbL 1.0, attribution « SNCF Réseau » à citer ;
null sinon, jamais à la place d'une mesure publiée), `terrain_ranges` (parties à l'altitude du terrain), `track_estimate_ranges` (parties à l'altitude de la voie estimée
d'après le terrain) et `track_estimate` (méthode et erreur mesurée contre le MNT LiDAR HD selon l'écart entre le tracé et la
voie, null sans point estimé), `points` = `[distance_m, altitude_m, pk_m]` le long du parcours, `structures`, `gaps`, `branches`,
`stations` (toutes les gares de la ligne, fermées comprises, avec leur PK officiel, leur statut et leur écart au
parcours), `altitude_sources`, et `altitude_at_pk` si `pk_m` est donné, sans position quand le tracé à cet endroit n'est pas
redistribuable : `position_withheld`) et `vff_url` (section de la fiche). Le PK
de chaque point est estimé à partir des PK officiels des gares. Le détail point par point (provenance, position) est
donné par `samples` quand `pk_from_m` ou `pk_to_m` est fourni, comme par l'API REST `GET /api/v1/lines/{line}/profile/points` et `LineProfile.samples` en GraphQL. Attribution : IGN (BD TOPO, RGE ALTI), Licence Ouverte 2.0.

```json
{"line": "rfn-896000", "max_points": 100}
{"line": "rfn-405000", "pk_m": 45764}
{"line": "rfn-405000", "pk_from_m": 45000, "pk_to_m": 46000, "max_points": 50}
```

### `line_audit` : audit des tronçons d'une ligne

Même contenu que `GET /api/v1/lines/{line}/audit` (service `SegmentAudits`) ; les tronçons sont paginés de la même façon par l'API REST, GraphQL (`LineAudit.segments(limit, offset)`, `segments_total`) et l'outil : `limit` (50 par défaut, 200 au plus) et `offset`. Notes calculées à l'avance par
`vff:audit-segments` (méthode et seuils : `docs/AUDIT-TRONCONS.md`) : une ligne pas encore auditée à l'époque demandée
renvoie l'erreur `audit_not_computed`.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `era` | entier 1830 à 2030 | non | Année de l'ère (2030 par défaut, état du jour). |
| `grade` | `good`, `fair`, `poor`, `unknown` | non | Ne garder que les tronçons dont la plus mauvaise note vaut celle-ci. |
| `limit` | entier 1 à 200 | non | Nombre de tronçons au plus (50 par défaut), les plus mal notés d'abord. |
| `offset` | entier, 0 ou plus | non | Rang du premier tronçon rendu (0 par défaut) ; page suivante : `offset + limit` tant que ce rang est inférieur à `segments_total`. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `era_year`, `audited_at`, `rule_version`, `grades` (classement, placement, exactitude, tracé),
`worst_grade`, `score` (0 à 100), `shares` (part de la longueur par note), `line_checks` (longueur comparée à la
longueur officielle, ordre des gares), `segments_total` (après le filtre `grade`), `limit`, `offset` et `segments` (une page, chacun avec ses notes et `evidence`, codes
`reasons` stables) et `vff_url`. Codes de raisons récents : `on_other_line_track` (tronçon posé sur la voie d'une autre ligne alors que sa propre voie passe à 8 à 60 m : sûr en service aujourd'hui, faux s'il est fermé, déposé ou à une ère passée), `shared_track` (tronc commun ou embranchement sur la voie de départ, sans voie propre : sûr), `confirmed_by_precise_source` (tracé confirmé par une voie BD TOPO ou OSM indépendante), `observation_outvoted` (une seule observation actuelle contre deux d'accord) et `continuous_service` (ère passée : aucune fin datée ne touche le tronçon à cette ère, tronçon sûr aujourd'hui). Attribution : SNCF Réseau et OpenStreetMap (ODbL), IGN (Licence Ouverte 2.0) pour
les états observés cités dans les preuves.

```json
{"line": "rfn-405000", "era": 2030, "grade": "poor"}
```

### `segment_audits` : audit des tronçons de tout le réseau

Même contenu que `GET /api/v1/audits/segments` et que la requête GraphQL `segmentAudits` (service `NetworkAudits`) : les tronçons audités d'une ère, toutes lignes ensemble, les plus mal notés d'abord. Les preuves complètes d'un tronçon restent dans `line_audit`. Notes calculées par `vff:audit-segments` (`docs/AUDIT-TRONCONS.md`). Pas d'erreur pour une ère sans audit : `total` vaut 0.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `era` | entier 1830 à 2030 | non | Année de l'ère (2030 par défaut). |
| `grade` | `good`, `fair`, `poor`, `unknown` | non | Note à garder : celle du contrôle `check`, ou la plus mauvaise des quatre sans `check`. |
| `check` | `classification`, `placement`, `accuracy`, `trace` | non | Contrôle dont on filtre la note ; limite aussi `reason` à ce contrôle. |
| `reason` | chaîne, 60 caractères au plus | non | Code de raison (`on_other_line_track`, `median_over_tolerance`, `source_contradicts`…). |
| `status` | `in_service`, `single_track`, `service_track`, `construction`, `abandoned`, `dismantled`, `razed` | non | État du tronçon. |
| `department` | code INSEE en majuscules | non | Tronçons des lignes qui passent dans ce département (liste : `list_departments`). |
| `region` | code INSEE | non | Tronçons des lignes de la région. |
| `line` | slug ou code à 6 chiffres | non | Une seule ligne. |
| `q` | chaîne, 120 caractères au plus | non | Texte cherché dans le nom de la ligne, casse et accents ignorés. |
| `sort` | `worst`, `length`, `line` | non | Tri : le plus mal noté d'abord (défaut), le plus long d'abord, code de ligne. |
| `limit` | entier 1 à 200 | non | Nombre de tronçons au plus (50 par défaut). |
| `offset` | entier, 0 ou plus | non | Rang du premier tronçon rendu (0 par défaut) ; page suivante : `offset + limit` tant que ce rang est inférieur à `total`. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `era_year`, `total` (après les filtres), `limit`, `offset` et `items` : par tronçon `line_slug`, `line_code`, `line_name`, `segment_id`, `status`, `traffic_type`, `trace_source`, `length_m`, `grades` (quatre notes), `worst_grade`, `score` (0 à 100), `reasons` (codes de chaque contrôle), `audited_at` ; puis `vff_url` et l'attribution. Une réponse trop volumineuse est raccourcie par la fin : `truncated.items` donne le nombre de tronçons retirés, reprendre à `offset + limit - truncated.items`.

```json
{"grade": "poor", "check": "placement", "department": "61", "limit": 20}
```

### `station_audits` : audit des gares de tout le réseau

Même contenu que `GET /api/v1/audits/stations` et que la requête GraphQL `stationAudits` (service `NetworkAudits`) : les gares auditées par `vff:audit-stations` (`docs/AUDIT-GARES.md`), les plus éloignées de leurs sources d'abord. Une passe par gare, sans ère. Aucune coordonnée : le détail d'une gare (positions des sources redistribuables, dates candidates) est dans la clé `audit` de `get_station`.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `grade` | `agree`, `close`, `isolated`, `disagree`, `none` | non | Note de placement (toutes par défaut). |
| `check` | code d'un contrôle croisé | non | Contrôle en échec (`far_from_track`, `wikidata_differs`, `closure_before_opening`, `outlier_source`…). |
| `opening` | `sourced`, `estimated`, `unknown`, `not_applicable` | non | Note de la date d'ouverture. |
| `closure` | `sourced`, `estimated`, `unknown`, `not_applicable` | non | Note de la date de fermeture. |
| `department` | code INSEE en majuscules | non | Département de la gare. |
| `region` | code INSEE | non | Région de la gare. |
| `line` | slug ou code à 6 chiffres | non | Gares desservies par cette ligne. |
| `q` | chaîne, 120 caractères au plus | non | Texte cherché dans le nom de la gare, casse et accents ignorés. |
| `sort` | `spread`, `track`, `name` | non | Tri : écart à la source précise la plus éloignée (défaut), écart à la voie, nom. |
| `limit` | entier 1 à 200 | non | Nombre de gares au plus (50 par défaut). |
| `offset` | entier, 0 ou plus | non | Rang de la première gare rendue (0 par défaut) ; page suivante : `offset + limit` tant que ce rang est inférieur à `total`. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `total` (après les filtres), `limit`, `offset` et `items` : par gare `station_id`, `slug`, `name`, `department`, `region`, `status`, `position_source`, `placement_grade`, `families`, `nearest_agreeing_m`, `farthest_precise_m`, `track_offset_m`, `opening_grade`, `closure_grade`, `passenger`, `freight`, `checks`, `sources` ; puis `vff_url` et l'attribution. Raccourcissement : comme `segment_audits`.

```json
{"grade": "disagree", "region": "28", "limit": 20}
```

### `line_infrastructure` : voies et radio sol-train d'une ligne

Même contenu que `GET /api/v1/lines/{line}/infrastructure` (service `LineInfrastructure`). Réseau d'aujourd'hui, par
portion de points kilométriques, d'après deux jeux de SNCF Réseau (ODbL) importés par `vff:import-sncf-track-regime` et
`vff:import-sncf-radio` : ni ère, ni date de mise en service.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `include_portions` | booléen | non | Garde le détail par portion (vrai par défaut) ; faux : résumés seulement. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `tracks` et `radio`, `provenance` (avec `mention`) et `vff_url`. `tracks.summary` (nul sans portion au
nombre établi) donne le nombre de voies dominant en longueur (`tracks` : 1 voie unique ou navette, 2 double voie),
`uniform` (90 % de la longueur décrite ou plus, voie banalisée comprise dans le total), `dominant_share`, `dominant_length_m`, `length_m_by_tracks` et
`banalised_length_m` ; `tracks.portions` donne `pk_start_label`, `pk_end_label`, `length_m`, `tracks`, `regime` (libellé
SNCF tel quel) et `banalised`. La voie banalisée ne dit pas le nombre de voies (`tracks` nul) : ne pas en déduire une
double voie. `radio.systems` donne la longueur par système (`gsm-r`, `gsm-gfu`, `rst-data`, `rst-id`, `rst`), `gsm_r` et
`gsm_r_length_m` résument le GSM-R, `radio.portions` le détail avec `system` et `label`. Une ligne absente des jeux renvoie
des listes vides. Raccourcissement : `tracks.portions` et `radio.portions` (`truncated`).

```json
{"line": "rfn-579000"}
```

### `line_greenways` : voies vertes d'une ancienne ligne

Même contenu que `GET /api/v1/lines/{line}/greenways` (service `LineGreenways`). Voies vertes et chemins cyclables
d'OpenStreetMap qui reprennent l'emprise d'une ligne fermée ou disparue, rattachés par `vff:link-greenways`. Le
rattachement est mesuré : un way OSM est retenu quand au moins 50 m de sa longueur (et la moitié) suivent l'axe de la
ligne à 8 m près, là où aucune voie n'est en service ni en place à moins de 30 m ; une ligne n'a de voie verte qu'à
partir de 300 m communs. Les tramways, métros, funiculaires, téléphériques et plans inclinés (d'après le nom de la
fiche) n'en reçoivent jamais ; un tram-train du réseau ferré national (code SNCF Réseau à 6 chiffres) est une ligne
ferroviaire.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `include_ways` | booléen | non | Ajoute le détail par way OSM, 50 au plus par voie verte (`ways_not_shown` donne le reste). Faux par défaut. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `total_shared_length_m` (mètres de voie verte sur l'ancienne emprise), `osm_date` (date de lecture
d'OpenStreetMap), `greenways` (une voie verte par nom, la plus longue d'abord : `name`, `shared_length_m`, `way_count`,
`opened_year`, `osm_relation_id`, `osm_url`, et `ways` avec `include_ways`), `provenance`, `method` (les seuils
ci-dessus) et `vff_url` (section de la fiche). Le nom d'une voie verte est celui de son itinéraire cyclable OSM, sinon
celui du way, à condition qu'il désigne un itinéraire : un lieu (« Camping », « Le Lac »), un type de voie (« Voie
verte ») ou un nom de rue (« Rue du Denier », « Route de Saint-Mesmin ») ne nomme pas un groupe, qui reste alors sans
nom (`name` nul) ; la règle est écrite dans `GreenwayNames`, et le `name` de chaque way garde le nom OSM tel quel.
`opened_year` n'est donnée que si OSM la fournit (jamais devinée). Une ligne sans voie verte rattachée renvoie une
liste vide.

Provenance et licence : OpenStreetMap, ODbL 1.0. `provenance` donne le fournisseur, la licence et la mention
« © contributeurs OpenStreetMap, ODbL 1.0 (https://www.openstreetmap.org/copyright) » à reproduire ; le bloc
`attribution` cite OpenStreetMap comme toute réponse. Une base dérivée diffusée se partage sous la même licence.

```json
{"line": "rfn-436000", "include_ways": true}
```

### `line_archives` : documents d'archives d'une ligne (Gallica)

Même contenu que `GET /api/v1/lines/{line}/archives` (service `LineArchives`). Documents numérisés en accès libre dans
Gallica, la bibliothèque numérique de la BnF, dont le titre désigne la ligne : projets, plans et cartes du tracé,
mémoires, guides, photographies. Ils sont trouvés par `vff:import-gallica` dans le catalogue général de la BnF, puis
rattachés à la ligne par un score calculé sur le seul titre de la notice (`ArchiveMatcher`) :

| Raison | Points | Condition |
|---|---|---|
| `rail_route` | 4 | le titre décrit une voie ferrée entre deux lieux de la ligne qui se suivent (« chemin de fer de X à Y », « ligne de X à Y », « la ligne X à Y », « chemin de fer de X à Z et à Y ») |
| `route` | 3 | à défaut, un trajet entre ces deux lieux (« de X à Y », « entre X et Y », « rapide Paris - Bordeaux ») ; aussi deux lieux voisins d'une liste « entre X, Y, Z » et une énumération « de X à Z, W et Y » sans « chemin de fer » ou « ligne » juste avant |
| `two_places` | 1 | à défaut, le titre nomme deux lieux de la ligne (communes, gares, extrémités de ses noms) |
| `only_line_places` | 1 | sans trajet, tous les lieux reconnus dans le titre sont sur la ligne |
| `ends` | 2 | ces deux lieux sont les extrémités de la ligne (nom actuel, ancien nom ou gares terminus) ; pas pour une variante dont le nom donne un lieu de passage (« via Grenoble ») quand le titre ne le nomme pas et qu'une autre ligne passe aussi par les lieux nommés |
| `rail_word` / `rail_hint` | 2 / 1 | chemin de fer, voie ferrée, tramway, railway, embranchement, ferroviaire, « ligne de X à Y » ; ou seulement gare, train, locomotive, wagon |
| `date_in_life` | 2 | date entre la première date connue de la ligne et sa fermeture (ou aujourd'hui) |
| `date_project` / `date_after_closure` | 1 | dans les 40 ans avant la première date ; après la fermeture |
| `date_unknown` | 0 | document sans date, ou ligne sans date connue |
| `date_doubtful` | 0 | le titre imprime une année à plus de 40 ans de la date de la notice : la date ne compte pas |
| `doc_type` | 1 | texte imprimé, carte, image fixe ou manuscrit |
| `secondary_mention` | 0 | la ligne n'est citée que comme raccordement d'une autre : ses extrémités ne comptent pas |

Un document est retenu à partir de 9 points sur 11, avec un mot du rail, sans autre transport dans le titre (métro,
canal, bateaux à vapeur, autocar, téléphone) sauf « chemin de fer de X à Y », et jamais avant 1820 ni plus de 40 ans avant la
première date de la ligne. Deux lieux nommés sans trajet ne suffisent que s'ils sont les extrémités de la ligne, que le titre
ne cite aucun autre lieu connu et que la date tombe dans la vie de la ligne. Seul le meilleur score garde le document ; à
égalité, une ligne reliée par un trajet passe avant une ligne seulement nommée. Un titre qui convient aussi bien à plus de
deux lignes n'est rattaché à aucune. La presse
n'est pas cherchée. Une liste vide ne veut pas dire que Gallica n'a rien sur la ligne.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `type` | `monograph`, `map`, `image`, `manuscript` | non | Ne garder qu'un type de document. Tous par défaut. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `total`, `documents` (du plus ancien au plus récent, sans date en dernier : `gallica_ark`,
`gallica_url`, `catalogue_ark`, `catalogue_url`, `title`, `type`, `authors`, `date` avec `label`, `year`, `year_to`,
`exact` et `precision` (`day`, `month`, `year`, `circa`, `range`, `before` : au plus tard `year`, `after` : au plus
tôt `year`, `unknown`), `shelfmark`, `match` avec le score, le
seuil, les raisons et les lieux reconnus, `record_updated_on`, `fetched_at`), `provenance`, `method` (seuil et points)
et `vff_url` (section de la fiche).

Droits : les métadonnées des notices de la BnF sont sous Licence Ouverte ; reprenez la mention « Source :
gallica.bnf.fr / BnF » avec la date de récupération (`fetched_at`). VFF ne sert aucune image, vignette ni texte
numérisé : renvoyez le lecteur vers `gallica_url`. La réutilisation des documents eux-mêmes suit les conditions
d'utilisation de Gallica (non commerciale libre avec la mention de source, commerciale soumise à redevance).

```json
{"line": "rfn-340000", "type": "map"}
```

### `line_archive_records` : notices des fonds d'archives d'une ligne (Archives SNCF)

Même contenu que `GET /api/v1/lines/{line}/archive-records` (service `LineArchives`). Notices d'inventaire des fonds
d'archives dont le titre désigne la ligne. Seule source à ce jour : le jeu de données « Archives SNCF »
(`archives-sncf-sardo`, ressources.data.sncf.com, ODbL) du Service Archives Documentation du groupe SNCF, qui décrit
les dossiers, photographies de la Médiathèque SNCF et films numérisés consultables sur archives.sncf.com. Les notices
sont importées par `vff:import-sncf-archives` et rattachées par le même score que `line_archives` (tableau ci-dessus,
seuil 9 sur 11), avec deux règles propres au fonds :

- un nom de compagnie ne désigne pas une ligne : « Compagnie du chemin de fer de Paris à Orléans » est retiré du titre
  avant le calcul ;
- dans « située sur les lignes du Mans à Mézidon et d'Argentan à Granville », chaque trajet de la liste compte comme
  une ligne (le document va aux deux lignes ; au-delà de deux, il est ambigu et ne va à aucune).

Les notices des thèmes qui portent sur des personnes (fichiers des cheminots blessés et tués, contentieux, questions de
personnel) ne sont jamais rattachées, et la description d'une notice n'est jamais reprise. Précision mesurée le
22/11/2026 sur deux tirages de 40 notices relues à la main : 78 justes, 2 douteuses, aucune fausse. Une liste vide ne
veut pas dire que les archives de la SNCF n'ont rien sur la ligne : le jeu de données n'en décrit qu'une partie.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `type` | `document`, `image`, `video` | non | Ne garder qu'un type de notice : dossier d'archives, photographie ou affiche, film. Tous par défaut. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `total`, `records` (du plus ancien au plus récent, sans date en dernier : `source`, `reference`
(numéro du dossier), `url`, `title`, `type`, `theme`, `sub_theme`, `holder` (service versant), `box`, `rights`, `date`
avec `label`, `year`, `year_to`, `exact` et `precision` (`day`, `month`, `year`, `range`, `unknown`), `match` avec le
score, le seuil, les raisons et les lieux reconnus, `fetched_at`), `provenance`, `method` (seuil et points) et
`vff_url` (section de la fiche).

Droits : le jeu de données est sous ODbL ; reprenez la mention « SNCF, Service Archives Documentation ». VFF ne sert ni
description, ni image, ni document : renvoyez le lecteur vers `url`, dont la réutilisation suit les conditions
d'archives.sncf.com.

```json
{"line": "rfn-430000", "type": "image"}
```

### `journal` : articles du journal de bord

Même contenu que `GET /api/v1/journal` et `GET /api/v1/journal/{slug}`, et que les pages `/journal` du site. Les
articles sont des fichiers Markdown versionnés (`resources/content/journal/<slug>.<langue>.md`) écrits par l'équipe ;
un article sans traduction est renvoyé en français (`lang` dit la langue du texte renvoyé, `locales` celles qui
existent).

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `slug` | chaîne | non | Slug d'un article. Sans lui : la liste. |
| `line` | code à 6 chiffres | non | Liste : seulement les articles qui parlent de cette ligne. |
| `lang` | `fr`, `en`, `de` | non | Langue souhaitée (et des messages). |

Liste : `total` et `articles` (`slug`, `title`, `date`, `updated`, `summary`, `lang`, `locales`, `image`, `lines` :
codes RFN, `stations` : slugs, `path`, `vff_url`). Article : les mêmes champs, plus `author` (toujours l'équipe),
`disclosure` (engagement de l'équipe sur le sujet de l'article, à reprendre avec l'article), `html` (texte, liens internes relatifs au site,
sources en notes de fin), `toc`, `lines` et `stations` remplacés par les fiches citées qui existent, `words`,
`reading_minutes`, `license` et `license_url` (texte sous CC BY-SA 4.0 ; l'image garde sa licence). Erreur :
`journal_not_found`.

### `line_stories` : histoires d'événements d'une ligne

Même contenu que `GET /api/v1/lines/{line}/stories` et `GET /api/v1/lines/{line}/stories/{event}` (service
`EventStories`), et que la page `/lines/{line}/stories/{event}` du site. Une histoire réunit un événement de la ligne,
les images publiées de la ligne datées à 5 ans au plus de son année et les documents de Gallica de la même période.
Aucun texte n'est rédigé : celui de l'événement vient de sa source.

| Règle | Valeur |
|---|---|
| Événements concernés | ouvertures, réouvertures, fermetures (tous services, voyageurs, fret), déclassements, déposes, reconversions, électrifications, modernisations, débuts et fins d'exploitation associative, accidents ; pas les concessions ni `other` |
| Image retenue | image de la ligne dont toutes les années possibles sont à 5 ans au plus de l'événement : date exacte (`day`, `month`, `year`), année « vers » (`circa`, comptée à 2 ans près), décennie (`decade`), fourchette (`range`) ; jamais une borne seule (`before`, `after`). Domaine public ou licence ouverte (`public_domain`, `open_license`) : les images que le site montre avec une simple autorisation (`permission`) ne sont pas diffusées par l'API ni par cet outil. Plus les images rattachées à l'événement, quelle que soit leur date |
| Document retenu | notice de Gallica de la ligne datée au moins à l'année près (`day`, `month`, `year`, `circa`, `range`) dans la même fenêtre |
| Seuil | 3 images et documents au moins, dont 2 images |
| Carrousel | les 24 images les plus proches de la date, rangées dans l'ordre chronologique ; un même fichier (rattaché à la ligne et à l'événement) n'y figure qu'une fois |
| Plusieurs sources | un même fait (même type, même date et même précision, même portion : toute la ligne ou les deux mêmes lieux) n'a qu'une histoire, à l'identifiant de la source la plus sûre (lignes-oubliees, puis Wikipédia, OpenHistoricalMap, Wikidata ; puis la confiance et la précision de la date) ; demandée par une autre source, l'outil donne la même histoire |

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `event` | entier | non | Identifiant d'un événement de la ligne. Sans lui : la liste des événements qui ont une histoire. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Sans `event` : `line`, `rules` (fenêtre, seuils, limite), `total` et `stories` (pour chacune : `event`, `source_event_ids`
(événements du même fait, celui-ci d'abord), nombre d'`images` et de `documents`, `vff_url` de la page). Avec `event` :
`line`, `event` (celui de l'adresse de l'histoire, avec `provenance`), `sources` (tous les événements du fait, à citer), `window`
(`years`, `from`, `to`), `images_total`, `images` (champs de l'outil `media`, plus `gap_years`, `gap_years_min`, `gap_years_max`, `gap_exact` et `attached`),
`documents` (champs de `line_archives`, plus `gap_years`), `stations` (gares de la portion : nom et slug), `rules`,
`archives_mention` et `vff_url`.

`gap_years` est l'écart signé avec l'année de l'événement : 4 = quatre ans après, -2 = deux ans avant, 0 = la même
année, nul pour une image de l'événement sans date. Dites-le au lecteur : une photo de 1934 n'est pas une photo de
l'ouverture de 1930. Quand `gap_exact` est faux, la date de l'image est approximative : `gap_years_min` et
`gap_years_max` bornent l'écart possible, à dire « environ trois ans avant » ou « de trois ans avant à deux ans
après ». Erreurs : `line_not_found`, `event_not_on_line` (l'événement est celui d'une autre ligne),
`no_story` (sous le seuil, ou type sans histoire).

Droits : montrez chaque image avec son auteur, sa licence et sa source ; citez la source de l'événement. Gallica :
métadonnées sous Licence Ouverte, jamais d'image, renvoyez le lecteur vers `gallica_url`.

```json
{"line": "rfn-340000", "event": 12793}
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

Renvoie `line`, `era_year`, `pk_m` (mètres), `pk_label` (`km+mmm`), `total_m` (longueur du tracé à cette ère),
`geometry_withheld`, `geometry_withheld_reason`, `snap` (`lat`, `lng` du point du tracé retenu,
`distance_from_input_m` : au-delà de quelques dizaines de mètres, le lieu n'est probablement pas sur la ligne) et
`vff_url`. Ce PK est mesuré le long du tracé VFF : il peut différer du PK officiel de SNCF Réseau. Erreur `no_geometry`
si la ligne n'a pas de tracé à cette ère.

Quand le point projeté longe un tronçon dont la source n'autorise pas la redistribution, `geometry_withheld` vaut
`true` (raison `licence_not_open` ou `unknown_source`) et `snap` vaut `null` : ni le point projeté ni la distance au
tracé ne sont donnés, car une grille de requêtes suffirait à reconstituer le tracé, même avec une distance arrondie.
Le lieu est ramené au centre de sa maille de 0,001 degré (environ 110 m sur 75 m en France) et le PK est arrondi à
100 m : il est juste à 150 m près environ. Même règle pour `GET /api/v1/lines/{line}/pk` et `Line.pk` en GraphQL.

```json
{"line": "rfn-420000", "lat": 48.10, "lng": -1.67}
```

### `signalling_history` : historique de la signalisation d'une ligne

Mêmes faits que `GET /api/v1/lines/{line}/signalling-history` (service `LineSignallingHistory`) : mises en service
datées de BAL, BAPR, block manuel, cantonnement téléphonique, TVM, ETCS et KVB, relevées dans l'article Wikipédia de
la ligne (texte, listes, tableaux, infobox) ou dans d'autres documents datés relevés par VFF (`source.provider` :
`manual`, avec `source.verification` et `source.documents` : éditeur, titre, lien, date de publication ;
`origin` : `document`). Les champs de chaque fait sont décrits avec `get_line` ci-dessus. Aucune
phrase de l'article n'est renvoyée (décision Q22) : le permalien de la révision lue suffit pour vérifier.

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `line` | slug ou code | oui | Ligne (slug renvoyé par `search`, ou code SNCF Réseau à 6 chiffres). |
| `year` | entier 1800 à 2100 | non | Année ramenée à l'ère de la carte : ajoute `era` (`year`, `is_current`) et `in_force`. |
| `lang` | `fr`, `en`, `de` | non | Langue des messages. |

Renvoie `line`, `history` (tous les faits sûrs, du plus ancien au plus récent), `corrections` (faits relevés que des
documents contredisent, jamais dans `history` ni `in_force` : `system`, `date`, `date_precision`, `section`, `original`,
`action`, `reason`, `quote`, `sources`, comme la clé `corrections` de l'API REST ; liste vide sinon), avec `year` : `era` et `in_force`, les
faits encore en vigueur au 31 décembre de l'année de l'ère (aujourd'hui pour l'ère courante), ceux que dessine le calque
« Signalisation » de la carte à cette époque. Un fait plus récent du même groupe (cantonnement, TVM, ETCS, KVB) qui
porte sur toute la ligne ou recouvre sa portion placée le remplace ; un fait sans portion connue ne remplace rien, et un
remplacement que l'article ne date pas reste invisible. Puis `provenance` (Wikipédia, CC BY-SA 4.0, `mention`),
`vff_url` et `attribution`. Erreurs : `line_not_found` (avec des suggestions), `era_not_found`.

```json
{"line": "rfn-905000", "year": 1990}
```

### `list_departments` : départements et régions

Liste de référence des territoires (Code officiel géographique de l'INSEE, Licence Ouverte 2.0), mêmes données que
`GET /api/v1/regions` et `GET /api/v1/departments` (service `Territories`).

| Paramètre | Type | Obligatoire | Description |
|---|---|---|---|
| `region` | code INSEE de la région | non | Une seule région. Par défaut : toutes. |
| `lang` | `fr`, `en`, `de` | non | Langue des mentions (les noms restent en français). |

Renvoie `source` et `regions` (ordre alphabétique) : `code`, `name`, `counts` et `departments` (`code`, `name`,
`counts`, dans l'ordre du code officiel). `counts` : `lines` (ce que renvoie `list_lines` avec ce filtre : lignes
dont la fiche cite le territoire ou qui y desservent une gare ; une ligne qui traverse plusieurs départements d'une
région y compte une fois), `stations` (gares de l'annuaire), `accidents` (accidents publiés, département actuel du
lieu).

```json
{"region": "53"}
```

## Ressources

| URI | Type | Contenu |
|---|---|---|
| `vff://licences` | `text/markdown` | Conditions de réutilisation et licence de chaque source (français). |
| `vff://{lang}/lines/{slug}` | `text/markdown` | Fiche d'une ligne prête à citer : identité, longueurs, électrification, signalisation, phases, chronologie (50 événements au plus), sources et lien. Ex. `vff://fr/lines/rfn-420000`. |

## Invites (prompts)

Le serveur déclare la capacité `prompts` (`prompts/list`, `prompts/get`) et `completions`
(`completion/complete`). Une invite renvoie un seul message (rôle `user`), rédigé dans la langue
`lang`, qui dit à l'assistant quels outils appeler, comment dater et citer chaque fait, et qu'il
doit écrire avec ses propres mots. Le message ne contient aucune donnée de source (aucun extrait de
Wikipédia, décision Q22), seulement le nom et l'identifiant de l'objet quand il est trouvé ; sinon,
il demande de le chercher avec `search`. Textes : `lang/{fr,en,de}/mcp.php`, clé `prompts`.

| Invite | Arguments | Ce que demande le message |
|---|---|---|
| `present_line` | `line` (obligatoire : nom, code à 6 chiffres ou slug), `lang` | Présenter la ligne : tracé, longueur, électrification, signalisation, grandes dates, état actuel, avec `get_line` et `line_events`, chaque fait sourcé ; renvoie aussi vers la ressource `vff://{lang}/lines/{slug}`. |
| `compare_region_eras` | `region` (obligatoire : code INSEE ou nom), `from`, `to` (obligatoires : années 1830 à 2100, ramenées aux ères de la carte), `lang` | Comparer la région aux deux ères : `list_lines` et `list_stations` avec `region` et `era`, `network_events` entre les deux dates, `compare_eras` pour la France entière. |
| `station_story` | `station` (obligatoire : nom ou slug), `lang` | Raconter l'histoire de la gare : `get_station`, `get_line` des lignes desservies, `accidents` et `media` avec `station`. |

Erreurs : argument manquant, région inconnue, années de la même ère ou langue hors `fr`, `en`, `de`
donnent l'erreur JSON-RPC `-32602`. Complétion : `lang`, `region` (codes et noms), `line` et
`station` (20 identifiants au plus dont le début ou le nom correspond à la saisie, accents ignorés).

```json
{"jsonrpc": "2.0", "id": 1, "method": "prompts/get",
 "params": {"name": "compare_region_eras", "arguments": {"region": "Bretagne", "from": "1930", "to": "2026"}}}
```

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
| « Quelles lignes de Bretagne étaient ouvertes en 1930 ? » | `list_departments {"region": "53"}` si le code n'est pas connu, puis `list_lines {"region": "53", "era": 1930, "status": "open"}` |
| « Y a-t-il eu des accidents près de la gare de Brétigny ? » | `accidents {"station": "bretigny"}` |
| « Combien de lignes ne portaient que du fret en 1930 ? » | `list_lines {"traffic": "freight", "era": 1930, "facets": true, "limit": 1}` : lire `facets` |
| « Quelles gares du Finistère existaient en 1930 ? » | `list_stations {"department": "29", "era": 1930}` |
| « Montre-moi des cartes postales anciennes de la gare de Brest. » | `media {"station": "brest", "type": "postcard"}` ; afficher chaque image avec son `attribution` |
| « Quelles locomotives électriques étaient les plus rapides ? » | `materials {"type": "locomotive_electrique", "sort": "-max_speed_kmh", "limit": 5}` |
| « Quels ont été les déraillements les plus meurtriers ? » | `accidents {"kind": "derailment", "min_deaths": 10, "sort": "-deaths", "limit": 5}` ; afficher `review_note` quand une fiche est « à vérifier » |
| « Y a-t-il eu des accidents sur Paris - Brest depuis 1950 ? » | `accidents {"line": "rfn-420000", "from": 1950}` puis `get_accident` pour les sources et la portion |
| « Combien de kilomètres ont fermé entre 1930 et 1980 ? » | `compare_eras {"from": 1930, "to": 1980}` : lire `changes.closed` et `changes.removed` |
| « À quel PK de Paris - Brest est ce point (48,10 ; -1,67) ? » | `locate_on_line {"line": "rfn-420000", "lat": 48.10, "lng": -1.67}` |
| « Une voie verte a-t-elle remplacé la ligne de La Chapelle-Anthenaise à Flers ? » | `line_greenways {"line": "rfn-436000"}` : citer la mention de `provenance` |
| « La ligne de Beillant à Angoulême est-elle à voie unique, et équipée du GSM-R ? » | `line_infrastructure {"line": "rfn-579000"}` : `tracks.summary`, `radio.systems` ; citer « SNCF Réseau » |
| « Gallica a-t-il des plans du chemin de fer de Paris au Havre ? » | `line_archives {"line": "rfn-340000", "type": "map"}` : donner `gallica_url` et la mention « Source : gallica.bnf.fr / BnF » |
| « Les archives de la SNCF ont-elles des photos de la ligne du Mans à Mézidon ? » | `line_archive_records {"line": "rfn-430000", "type": "image"}` : donner `url` et la mention « SNCF, Service Archives Documentation » (ODbL) |
| « Quel matériel a circulé sur Lunéville - Saint-Dié ? » | `search {"query": "Lunéville"}` puis `materials {"line": "<slug>"}` et `get_material` pour la preuve |
| « Jusqu'à quand les X 4300 ont-ils roulé sur Lunéville - Saint-Dié ? » | `line_materials {"line": "rfn-67000", "type": "autorail"}` : période de chaque série |

## Authentification et quotas

Le serveur s'utilise sans compte ni jeton. Un **jeton personnel** de l'API, facultatif, relève la
limite de débit : un membre le crée depuis son profil (section « Jetons d'accès à l'API » de
`/profile`). Il ne sert qu'à lire, comme l'accès anonyme, et n'est affiché qu'une fois. Il est
valable un an : un courriel prévient son titulaire 30 jours avant l'échéance, et le profil le
renouvelle d'un clic sans changer son secret. Le serveur MCP lit le même en-tête que l'API REST et
GraphQL : `Authorization: Bearer <jeton>`.

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
- **Textes Wikipédia exclus** : aucune réponse ne recopie une phrase de Wikipédia (CC BY-SA 4.0),
  pour ne pas faire circuler un texte sous licence à partage dans les mêmes conditions sans sa
  mention. `get_line` ne renvoie pas la description de la ligne ; les preuves du matériel
  (`get_material`), la signalisation (`signalling_history`, `get_line`) et les périodes
  (`line_materials`) donnent l'article, la révision lue et son permalien, sans la phrase citée. Le
  serveur retire de toute réponse les clés `quote`, `excerpt`, `source_quote` et `source_excerpt`, et
  un test parcourt les réponses de tous les outils pour le vérifier. L'API REST et GraphQL publient
  ces textes avec leur auteur collectif, le lien de l'article, le permalien de la révision lue et la
  licence (`description_source`).
- **Débit** : 300 requêtes par minute et par adresse IP sans jeton, davantage avec un jeton
  personnel, sur un compteur commun à l'API REST et à GraphQL (voir « Authentification et quotas »).
  Au-delà : HTTP 429.
- **Corps de requête** : 2 Mio au plus (`API_MAX_BODY_BYTES`, `api.max_body_bytes`). Au-delà : HTTP 413, avant tout
  traitement, avec une erreur JSON-RPC : `{"jsonrpc": "2.0", "id": null, "error": {"code": -32600, "message": "The request body is larger than 2097152 bytes."}}`.
  Les lots JSON-RPC (une liste de requêtes) ne sont pas acceptés : une requête par appel. La limite des lots de
  GraphQL (5 opérations, 400 `batch_too_large`) est décrite dans `docs/API.md`.
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

**Tracés.** Le serveur MCP ne renvoie aucune géométrie de tracé : ses réponses donnent des
points (gares, passages à niveau, accidents, point du tracé retenu par `locate_on_line` quand sa
source autorise la redistribution), des longueurs et des PK, jamais les lignes elles-mêmes. Les tracés s'obtiennent par l'API REST, qui ne
redistribue que la géométrie d'une source dont la licence le permet : pour une source dont la
licence n'autorise pas la redistribution, `geometry` vaut `null`, avec `geometry_withheld` et la
raison (voir la documentation de l'API, « Droits des tracés »). Un test vérifie qu'aucun outil
ne renvoie de géométrie de tracé, même interrogé exactement sur un sommet d'un tracé retenu.

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
- **Notifications de changement** : le serveur répond aux questions mais ne prévient de rien. Pour
  être averti quand une contribution est publiée, quand le réseau d'aujourd'hui est recalculé ou
  quand un import publie un accident ou un matériel, un membre déclare un **webhook** depuis son
  profil. VFF envoie alors un court message JSON signé (HMAC-SHA256) à son adresse HTTPS, et
  l'agent peut ensuite interroger le serveur MCP sur la ressource annoncée (`data.id` : slug de la
  ligne, de la gare, de l'accident ou du matériel). Format, signature et exemples de vérification :
  section `#webhooks` de la page `/api` et section « Webhooks » de `/docs/api`.

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
