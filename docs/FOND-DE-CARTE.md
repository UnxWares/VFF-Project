# Fond de carte libre auto-hébergé (PMTiles)

VFF peut dessiner ses cartes sur un fond qu'il sert lui-même : une archive PMTiles (un seul fichier, lu par
plages d'octets) des données OpenStreetMap au schéma Protomaps, limitée à la France métropolitaine, à la
Corse et à leurs abords (environ 25 km au-delà des frontières).

- **Hors ligne**, dès qu'une archive est servie : une page relue sans réseau dessine ses cartes sur ce fond.
  Le service worker garde les morceaux déjà affichés (2 000 au plus, 60 Mo, 30 jours) : la carte retrouve
  son fond sur les zones déjà vues, et le reste reste uni (couleur « papier » du site).
- **En ligne**, seulement si l'option `VFF_BASEMAP_ONLINE=true` est posée : la carte /maps prend ce fond à
  la place des tuiles OpenStreetMap. Désactivée par défaut. Sans elle, aucun morceau n'est lu en ligne, donc
  rien n'est gardé pour la lecture hors ligne : le fond hors ligne reste uni.

Les petites cartes des fiches (ligne, gare, accident) prennent ce fond hors ligne ; en ligne, elles gardent
leur fond habituel tant que leur code ne lit pas l'option (voir `usesBasemap` dans `resources/js/lib/basemap.js`).

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

Au-delà du zoom 15, MapLibre agrandit les tuiles du zoom 15 (vecteurs : aucun flou).

Les zones sont décrites par `database/data/basemap/france.geojson` (polygone tracé à la main, environ 25 km
au-delà des frontières, Corse comprise) et `normandie.geojson`.

## Réglages (`config/maps.php`, clé `basemap`)

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `VFF_BASEMAP_ARCHIVE` | `france-20261120.pmtiles` | Nom du fichier dans le dossier ; vide ou absent : aucun fond libre |
| `VFF_BASEMAP_DIRECTORY` | `storage/app/data/pmtiles` | Dossier de l'archive |
| `VFF_BASEMAP_ONLINE` | `false` | Fond libre aussi en ligne sur /maps |
| `VFF_BASEMAP_MAX_AGE` | `2592000` (30 jours) | Durée de cache HTTP de l'archive |

Une nouvelle archive porte une nouvelle date dans son nom, donc une nouvelle adresse : les navigateurs ne
mélangent jamais deux archives. Après le changement de nom, l'ancienne archive peut être retirée à la main.

## Service par VFF

`GET /basemap/{archive}` (`BasemapArchiveController`, hors du groupe web : ni session ni cookie) sert
seulement l'archive configurée, avec `Accept-Ranges: bytes`, réponses partielles 206 (et 416 pour une plage
impossible), `Cache-Control: public, max-age=…` et un ETag tiré du nom, de la taille et de la date du
fichier. Les pages reçoivent la propriété partagée `basemap` (`{ url, online, attribution }` ou `null`).

En production, chaque tuile lue passe sinon par PHP. Mieux vaut laisser le serveur web servir le fichier
lui-même, à la même adresse, par exemple avec nginx :

```nginx
location ~ ^/basemap/([a-z0-9-]+\.pmtiles)$ {
    alias /srv/vff/storage/app/data/pmtiles/$1;
    add_header Cache-Control "public, max-age=2592000";
    add_header X-Content-Type-Options nosniff;
    # Seulement si un autre site lit l'archive (inutile pour VFF lui-même) :
    # add_header Access-Control-Allow-Origin "*";
    # add_header Access-Control-Expose-Headers "ETag, Content-Range, Content-Length";
    types { } default_type application/octet-stream;
}
```

nginx gère les plages d'octets seul. Garder la même liste de noms permis que la route
(`[a-z0-9-]+\.pmtiles`).

## Côté navigateur

- `resources/js/lib/basemap.js` : style (palette claire et sombre de VFF), `usesBasemap`,
  `applyBasemapTheme` (changement de thème sans recharger le style).
- `resources/js/lib/pmtilesProtocol.js` : protocole `pmtiles://`, enregistré par `addProtocol` (MapLibre 5
  et 6), par `loadMaplibre` (petites cartes) et par `RailMap` (carte /maps).
- `resources/js/lib/offlineMap.svelte.js` : `neutralMapStyle` rend le fond libre quand la page en décrit un.
- `resources/js/pwa/` : le service worker garde chaque plage déjà lue sous sa propre clé
  (`basemapCacheKey`), la rend en 206 sans réseau, et garde les polices « cache d'abord ».
