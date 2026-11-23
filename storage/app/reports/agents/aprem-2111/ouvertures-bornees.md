# Ouvertures bornées (ouvertures-bornees, 23/11/2026)

Aucune écriture dans `vff` (lectures bornées seulement). Sources : API Wikipédia aux révisions citées, User-Agent du projet.

## 1. Code : une ouverture peut être encadrée comme une fermeture
- `database/data/gares_corrections.php`, action `add`, `type` opening, `precision` `range` : `after` (ouverte après cette année) et / ou `date` (au plus tard ce jour, `null` sans borne haute). `StationCorrections::addedEvents` écrit deux événements d'ouverture : « après » (31/12 de `after`) et « avant » (la date). Validation : ouverture seulement, une borne au moins, bornes ordonnées.
- `StationTimeline::openingBounds` lit les bornes (une correction prime) ; `forTimeline` rejoue « après 1890 » au 01/01/1891 ; `lastConfirmedEra` : « après » ne prouve aucune ère ; `boundedOpeningNote` : « Date d'ouverture inconnue (entre 1891 et 1907) » ou « (pas avant 1889) » (fr, en, de dans `lang/*/generated.php`).
- `vff:build-station-eras` : fiche en `range` (opened_at = borne haute, à défaut première fermeture datée, à défaut aujourd'hui ; `opened_after` = borne basse - 1, gardé au passage d'inferEvents) ; plus d'ouverture déduite de la ligne ; ères absentes jusqu'à la borne basse, « incertaines » jusqu'à la borne haute, sûres ensuite ; desserte des lignes (`first_year`) à la borne basse, jamais à la borne haute (`StationTimeline::openedYearFloorSql`, aussi dans l'invariant `stations.line_service_outside_station_life`).
- Correctif trouvé en route : une ouverture corrigée ne remplaçait pas sur la carte la date plus ancienne d'une autre source (canonicalEvents gardait la plus ancienne). Mesure sur `vff` : 77 gares avec une correction d'ouverture d'une autre année et une date plus ancienne d'une autre source (Breitenbach, Muhlbach : 1868 de Wikidata). Désormais la correction prime ; leurs ères changent à la prochaine reconstruction.
- Affichage : fiche, annuaire, liste des gares de ligne, REST, GraphQL, MCP et « Autour de moi » lisaient déjà la précision `range` (règle G3). La chronologie de la fiche fond les deux bornes en une entrée `range`. Ajouts : `opened_precision` et `opened_after` dans le GeoJSON des gares d'une ère, colonne `opened_after` dans `gares.csv` (contrat d'API : deux ajouts, `docs/API-CHANGELOG.md`).
- Test `tests/Feature/StationOpeningBoundsTest.php` (5 tests, échoue sans le changement : vérifié). 369 tests ciblés des gares verts (les 4 échecs de G3/G7 venaient de `VFF_STATION_ERA_RULES=g3,g7` du `.env` copié ; verts sans lui). Pint, PHPStan sur les fichiers changés, `vff:i18n-check --strict` propres. Pas de front changé.

## 2. Les quatre gares (section 54)
- Compans : entre 1891 et 1907 (accord pour une halte annoncé le 1er mars 1891, carte postale d'avant 1907 ; Plancke). Remplace le 31/08/1861 de la ligne.
- Renescure : pas avant 1889 (halte encore à ouvrir le 22/08/1889), sans borne haute. Remplace le 01/09/1848.
- Saint-Étienne-du-Rouvray : entre 1876 et 1911 (décrets de 1876 qui autorisent la gare, gare de secours en 1911). Remplace le 09/05/1843.
- Sainte-Colombe-Septveilles : entre 1858 et 1868 (maison de garde-barrière en 1858, 504 billets en 1868) ; l'année 1858 de Wikidata neutralisée.

## 3. Étaples, et deux autres coquilles
L'article de la ligne de Longueau à Boulogne-Ville (chronologie et texte), « Gare d'Abbeville » (Atlas de Joanne), « Gare de Noyelles-sur-Mer » et le texte des articles de Rue et de Neufchâtel-Hardelot donnent le 21/12/1847 pour Abbeville - Neufchâtel. Étaples-Le Touquet, Rue et Neufchâtel-Hardelot passent du 21/11 au 21/12/1847 (lignes retirées de `gares_dates_articles.php`). À signaler : Ponthoile-Romaine et Port-le-Grand portent le 11/11/1847 déduit d'un événement OHM de la ligne (même erreur que Rang-du-Fliers) ; c'est l'événement de ligne qu'il faudrait corriger, non touché.

## Application (coordinateur, non lancée)
```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras -t stations -t station_lines -t line_stops vff > <dossier-dumps>/avant-ouvertures-bornees.dump
bin/php artisan vff:build-station-eras --parallel=2
```
Aucune migration. Une seule reconstruction suffit avec les sections précédentes.
