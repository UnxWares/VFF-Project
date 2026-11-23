# Fond de carte libre auto-hébergé (PMTiles)

VFF peut dessiner ses cartes sur un fond qu'il sert lui-même : une archive PMTiles (un seul fichier, lu par
plages d'octets) des données OpenStreetMap au schéma Protomaps, limitée à la France métropolitaine, à la
Corse et à leurs abords (environ 25 km au-delà des frontières).

- **Hors ligne**, dès qu'une archive est servie : une page relue sans réseau dessine ses cartes sur ce fond.
  Le service worker garde les morceaux déjà affichés (2 000 au plus, 60 Mo, 30 jours) : la carte retrouve
  son fond sur les zones déjà vues, et le reste reste uni (couleur « papier » du site).
- **En ligne**, seulement si l'option `VFF_BASEMAP_ONLINE=true` est posée : la carte /maps et les petites
  cartes des fiches prennent ce fond à la place des tuiles OpenStreetMap. Désactivée par défaut. Sans elle,
  aucun morceau n'est lu en ligne, donc rien n'est gardé pour la lecture hors ligne : le fond hors ligne reste uni.

Toutes les cartes choisissent leur fond au même endroit, `resources/js/lib/mapStyle.svelte.js` (voir plus bas,
« Petites cartes des fiches ») : mêmes règles, même style clair et sombre, mêmes polices, même crédit.

## Licences et crédit

| Élément | Licence | Obligation |
| --- | --- | --- |
| Tuiles (construction quotidienne Protomaps) | ODbL 1.0, « Produced Work » d'OpenStreetMap | Afficher « © OpenStreetMap » avec un lien vers openstreetmap.org/copyright |
| Style `@protomaps/basemaps`, module `pmtiles` | BSD-3-Clause ; dessin des cartes sous CC0 | Crédit « Protomaps » demandé, pas exigé |
| Polices Noto Sans (`public/basemap/fonts`) | SIL Open Font License 1.1 | Licence jointe (`public/basemap/fonts/OFL.txt`) |

Sources lues le 21/11/2026 : <https://docs.protomaps.com/basemaps/downloads>,
<https://github.com/protomaps/basemaps> (« Tilesets are ODbL, attribute OSM », « All code is BSD-3 »,
dessin CC0), <https://github.com/protomaps/basemaps-assets> (polices OFL). La carte affiche
« Protomaps © OpenStreetMap » dans son cartouche de crédits (`BasemapArchive::ATTRIBUTION`).

Le style de VFF n'a ni pictogramme ni planche de sprites : points d'intérêt, cartouches de routes et flèches
de sens unique sont retirés. Les voies ferrées du fond sont grises ; l'orange `#ff6500` reste aux tracés de VFF.

## Préparer ou mettre à jour l'archive

```bash
DRY_RUN=1 scripts/basemap-pmtiles.sh            # mesure : taille et nombre de tuiles, rien n'est écrit
scripts/basemap-pmtiles.sh                      # France et abords, zooms 0 à 15, dernière construction
scripts/basemap-pmtiles.sh normandie 15         # zone d'essai
scripts/basemap-pmtiles.sh france 14 20261120   # zoom 14, construction du 20/11/2026
```

Le script n'installe rien : l'outil `pmtiles` tourne dans l'image `docker.io/protomaps/go-pmtiles:v1.31.2`.
Il refuse d'écraser un fichier existant et de démarrer avec moins de 10 Gio libres, pose un `.nobackup` dans
`storage/app/data/pmtiles/` et vérifie l'archive à la fin.

Mesures du 21/11/2026 (construction 20261120, environ 45 Mo/s) :

| Zone | Zooms | Tuiles | Taille | Durée |
| --- | --- | --- | --- | --- |
| France et abords | 0 à 15 | 1 324 005 | 6,2 Go (5,8 Gio) | 69 s |
| France et abords | 0 à 14 | | 2,9 Go | (mesure seule) |
| Normandie (essai) | 0 à 15 | 153 811 | 515 Mo | 11 s (mesure seule) |

Le script construit jusqu'au zoom 15 par défaut (maximum accepté) ; la mise à jour de l'archive se fait à la main
(voir plus haut), sans tâche planifiée.

### Zoom de la carte : jusqu'au niveau 19

Décision du 22/11/2026. Les cartes qui utilisent ce fond (`/maps`, cartes des fiches de gare, d'accident et de
ligne) montent au niveau 19 (`MAP_MAX_ZOOM`, option `maxZoom` de la carte) : au-delà du niveau 15 construit, MapLibre
agrandit les tuiles vectorielles du niveau 15 (la source déclare `maxzoom: 15`, `BASEMAP_MAX_ZOOM` dans
`resources/js/lib/basemap.js`) : aucune requête de tuile plus fine, aucun flou, car les tracés sont des vecteurs
(vérifié sur Limoges-Bénédictins, captures du 22/11/2026 dans `storage/app/reports/agents/aprem-2111/prod-carte/`).

- Les couches de VFF restent nettes pour la même raison : tuiles de tracés, de comparaison, de superposition, de
  signalisation, de passages à niveau et de voies vertes ont `maxzoom: 14` (aucune requête au-delà du niveau 14 :
  maximum observé 14 en zoomant jusqu'à 19) ; gares et accidents sont du GeoJSON, sans tuile. Les fonds raster
  (OpenStreetMap, OpenRailwayMap) déclarent `maxzoom: 19`, les cartes historiques de l'IGN leur propre niveau
  (14 à 18, agrandies au-delà). La carte schématique (sans fond) garde son plafond à 13.
- Les boutons de zoom suivent : le bouton « + » se désactive au niveau 19, l'échelle passe à 10 m.
- Repli : fond en ligne (tuiles raster d'OpenStreetMap, jusqu'au niveau 19) ou fond libre injoignable (archive en
  404) : la carte garde ses limites et ses couches, sans erreur de page.

Les zones sont décrites par `database/data/basemap/france.geojson` (polygone tracé à la main, environ 25 km
au-delà des frontières, Corse comprise) et `normandie.geojson`.

## Réglages (`config/maps.php`, clé `basemap`)

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `VFF_BASEMAP_ARCHIVE` | `france-20261120.pmtiles` | Nom du fichier dans le dossier ; vide ou absent : aucun fond libre |
| `VFF_BASEMAP_DIRECTORY` | `storage/app/data/pmtiles` | Dossier de l'archive |
| `VFF_BASEMAP_ONLINE` | `false` | Fond libre aussi en ligne, sur /maps et sur les petites cartes des fiches ; `true` en production (décision du 23/11/2026, `docs/MISE-EN-PRODUCTION.md` étape 15) |
| `VFF_BASEMAP_MAX_AGE` | `2592000` (30 jours) | Durée de cache HTTP de l'archive |
| `VFF_BASEMAP_MAX_RANGE_BYTES` | `1048576` (1 Mio) | Plus grande plage d'octets servie |
| `VFF_BASEMAP_REQUESTS_PER_MINUTE` | `600` | Requêtes par minute et par adresse IP |
| `VFF_BASEMAP_BYTES_PER_HOUR` | `536870912` (512 Mio) | Octets par heure et par adresse IP |
| `VFF_BASEMAP_ACCEL_REDIRECT` | vide | Production derrière nginx : préfixe de la location interne (ex. `/_basemap/`) |

Une nouvelle archive porte une nouvelle date dans son nom, donc une nouvelle adresse : les navigateurs ne
mélangent jamais deux archives. Après le changement de nom, l'ancienne archive peut être retirée à la main.

## Service par VFF

`GET /basemap/{archive}` (`BasemapArchiveController`, hors du groupe web : ni session ni cookie) sert
seulement l'archive configurée, avec `Accept-Ranges: bytes`, réponses partielles 206,
`Cache-Control: public, max-age=…` et un ETag tiré du nom, de la taille et de la date du fichier. Les pages
reçoivent la propriété partagée `basemap` (`{ url, online, attribution }` ou `null`).

L'archive pèse 6 Go et un client PMTiles n'en lit jamais que des morceaux. La route refuse donc :

- en 416 (avec `Content-Range: bytes */<taille>` et un message en texte) une requête GET sans en-tête
  `Range`, à plusieurs plages, dans une autre unité, mal formée, hors du fichier ou plus grande que
  `VFF_BASEMAP_MAX_RANGE_BYTES` (formes admises : `bytes=a-b`, `bytes=a-`, `bytes=-n`). Une requête HEAD
  passe sans plage ;
- en 412 un `If-Range` qui ne correspond plus à l'archive (la réponse normale serait le fichier entier) ;
- en 429 (avec `Retry-After`) au-delà de `VFF_BASEMAP_REQUESTS_PER_MINUTE` requêtes par minute ou de
  `VFF_BASEMAP_BYTES_PER_HOUR` octets par heure pour une même adresse IP.

Mesures qui fixent ces seuils (21/11/2026) :

- archive France du 20/11 : plus grosse tuile 388 105 octets (99,9 % sous 97 040, médiane 2 512), plus gros
  répertoire 11 088 octets ; le client lit d'abord 16 384 octets (en-tête et répertoire racine). Le plafond
  de 1 Mio laisse donc plus du double de la plus grosse lecture ;
- carte /maps à 1 440 px, fond libre en ligne, zooms et déplacements au clavier sans pause : 57 requêtes en
  31 s (8 par seconde au plus, 30 en 10 s) au premier essai, 38 requêtes et 2,2 Mo en 55 s au second. La
  limite de 600 requêtes par minute est environ cinq fois ce rythme ; 512 Mio par heure, plus de trois fois
  le volume d'une heure entière de navigation de ce type (le service worker garde en plus les morceaux déjà
  lus).

En production, chaque tuile passe sinon par PHP. Avec `VFF_BASEMAP_ACCEL_REDIRECT=/_basemap/`, VFF applique
les mêmes règles puis répond `X-Accel-Redirect` : nginx envoie les octets depuis une location interne, en
respectant la plage demandée. La configuration de référence est celle de `deploy/nginx/vff-project.org.conf`
(locations `~ ^/basemap/[a-z0-9-]+\.pmtiles$` et `/_basemap/`, expliquées dans `docs/DEPLOIEMENT-NGINX.md`), posée
par l'étape 14 de `docs/MISE-EN-PRODUCTION.md` ; l'archive est copiée à l'étape 15. En bref :

```nginx
# Bloc http : limite de secours devant PHP (10 requêtes par seconde, soit les 600 par minute de VFF).
limit_req_zone $binary_remote_addr zone=vff_basemap:10m rate=10r/s;

# Bloc server :
location ~ ^/basemap/[a-z0-9-]+\.pmtiles$ {
    limit_req zone=vff_basemap burst=60 nodelay;
    limit_req_status 429;
    # Passe par Laravel comme les autres routes (index.php), qui vérifie la plage et les limites.
    try_files $uri /index.php?$query_string;
}

location /_basemap/ {
    internal;
    alias /srv/vff/storage/app/data/pmtiles/;
    include /etc/nginx/snippets/vff-security-headers.conf;
    # Cache-Control, Content-Type et Accept-Ranges viennent de Laravel (un add_header Cache-Control ici le doublerait).
    gzip off;
    types { }
    default_type application/octet-stream;
}
```

`VFF_BASEMAP_ACCEL_REDIRECT` doit correspondre à la location interne, et celle-ci rester `internal` :
servie directement, elle laisserait télécharger toute l'archive sans aucune limite. Derrière nginx, tout
`If-Range` reçoit 412, car nginx calcule sa propre empreinte ; les clients PMTiles n'en envoient pas.

## Côté navigateur

- `resources/js/lib/basemap.js` : style (palette claire et sombre de VFF), `usesBasemap`,
  `applyBasemapTheme` (changement de thème sans recharger le style).
- `resources/js/lib/mapStyle.svelte.js` : la seule fabrique du fond de toutes les cartes (`mapStyle`,
  `pageMapStyle`) et le suivi du thème sur le fond libre (`followBasemapTheme`).
- `resources/js/lib/pmtilesProtocol.js` : protocole `pmtiles://`, enregistré par `addProtocol` (MapLibre 5
  et 6), par `loadMaplibre` (petites cartes, `hasPmtilesProtocol()` dit s'il a pu l'être) et par `RailMap`
  (carte /maps).
- `resources/js/lib/offlineMap.svelte.js` : `neutralMapStyle` rend le fond libre quand la page en décrit un.
- `resources/js/pwa/` : le service worker garde chaque plage déjà lue sous sa propre clé
  (`basemapCacheKey`), la rend en 206 sans réseau, et garde les polices « cache d'abord ».

## Petites cartes des fiches

Cartes concernées : fiche de ligne (`LinePhaseMap`), de gare (`StationMiniMap`), de commune et de département
(`CommuneMap`), d'accident (`AccidentMiniMap`), carte du patrimoine (`HeritageMap`), des chemins de fer
touristiques (`TouristMap`) et des lignes d'un matériel (`MaterialLinesMap`). Chacune appelle
`pageMapStyle(offline, { pmtiles: hasPmtilesProtocol() })` ; la carte /maps passe par la même fabrique.

| Situation | Fond |
| --- | --- |
| En ligne, option désactivée (défaut) | Inchangé : tuiles raster d'OpenStreetMap (MapTiler si `VITE_MAPTILER_KEY`), crédit « © OpenStreetMap » |
| En ligne, option activée | Fond libre de VFF, clair ou sombre selon le thème, noms dans la langue de la page, crédit « Protomaps © OpenStreetMap » |
| Hors ligne, archive servie | Fond libre sur les zones déjà vues (morceaux gardés par le service worker), uni ailleurs |
| Hors ligne sans archive, ou protocole `pmtiles://` absent | Fond uni « papier » |

- Le fond libre passe avant MapTiler : avec l'option, la clé MapTiler ne sert plus aux cartes.
- Le thème changé pendant la visite repeint le fond libre sur place (`followBasemapTheme`), sans recharger
  les tracés ni les gares.
- Les noms de gares en gras de la fiche de ligne prennent `Noto Sans Medium` sur le fond libre (`boldLabelFont`),
  comme /maps : aucune police absente demandée.
- Les morceaux lus par une petite carte en ligne sont gardés par le service worker comme ceux de /maps : avec
  l'option, une fiche vue en ligne garde son fond hors ligne.
- Vérifié le 23/11/2026 (serveur de dev, sans fenêtre) sur les fiches de ligne (Angoulême - Limoges), de gare
  (Limoges-Bénédictins), de commune (Limoges) et d'accident (Brétigny-sur-Orge), à 320 et 1 280 px, en clair et en
  sombre : option activée, seules des lectures de l'archive (aucune tuile OpenStreetMap, aucune police en 404) ;
  option désactivée, tuiles OpenStreetMap comme avant, aucune lecture de l'archive. Captures dans
  `storage/app/reports/agents/aprem-2111/petites-cartes-fond/`.

## Images gardées hors ligne (service worker)

Le même service worker garde les images des fiches déjà affichées, dans le cache `vff-images-v1` (réglages dans
`resources/js/pwa/rules.js`) :

- sont gardés les médias publiés par VFF (`/storage/media/…`) et les vignettes de Wikimedia Commons
  (`thumb.wikimedia.org`, `upload.wikimedia.org`) ;
- limites : 300 images au plus, 40 Mo en tout, 2 Mo chacune, 30 jours ; la plus ancienne part la première ;
- jamais gardées : les images demandées par une page de compte, de contribution ou de validation (la page qui
  demande l'image est vérifiée), les avatars, ni les documents de Gallica (lien seulement) ;
- hors ligne, l'accueil garde ses 16 images du « Rail en images » sur 16 quand elles ont été vues en ligne.

Le cache des images et celui du fond de carte ne changent pas à chaque déploiement : leurs noms portent un numéro de
format, pas la version du site.

## Polices : pas de Bold

Les polices du fond libre viennent de <https://github.com/protomaps/basemaps-assets> (dossier `fonts`, Noto Sans,
SIL Open Font License 1.1, texte dans `public/basemap/fonts/OFL.txt`). Cette source ne publie que `Noto Sans Regular`,
`Noto Sans Medium` et `Noto Sans Italic` : aucune plage `Noto Sans Bold` n'existe chez elle, donc aucune n'est
ajoutee (pas de source de la meme licence a copier sans changer de provenance). Les etiquettes de VFF (noms de gares)
demandent `Noto Sans Bold` ; `boldLabelFont` (`resources/js/lib/basemap.js`) la remplace par `Noto Sans Medium`
quand les glyphes sont ceux de `/basemap/fonts`, et garde `Bold` avec les glyphes d'OpenFreeMap. Plus de 404 sur
`Noto Sans Bold/0-255.pbf`.

## Position et zoom dans l'adresse

`/maps` lit et ecrit `?center=lon,lat&zoom=z` (format de `/embed/maps`, `resources/js/lib/mapView.js` : zoom borne de
0 a 19, centre refuse hors de lon 180 et lat 85). L'adresse est mise a jour a la fin de chaque deplacement par
`history.replaceState` (aucune entree d'historique). Le code d'integration peut reprendre la vue courante.
