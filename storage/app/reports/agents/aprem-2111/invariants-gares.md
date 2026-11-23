# invariants-gares (23/11/2026)

Chantier : expliquer et corriger les cas apparus après la première passe des ouvertures de sections datées au jour
(`line_event_corrections_sections_dates.php`, commit a5f21dfb, chaîne `sections-dates-application/`) :
`stations.active_without_track` 45 -> 61 et `stations.opened_before_lines` 30 -> 32.

Lecture seule de `vff` (`statement_timeout` 30 s, `default_transaction_read_only`, requête de l'invariant ère par ère).
Aucune écriture en base.

## stations.active_without_track : les 16 nouveaux cas

Tous à l'ère 1850, tous sur la section Tonnerre - Dijon de 830000 (Paris - Marseille), désormais ouverte le 22/06/1851
(événement 99484, ouverture au public ; inauguration le 01/06/1851) au lieu de l'ouverture Wikidata « toute la ligne » de 1847.

| Gare | id | PK | Date d'ouverture en base | Cause |
| --- | --- | --- | --- | --- |
| Tanlay, Lézinnes, Port-Sec-de-Pacy, Ancy-le-Franc, Aisy, Fain-lès-Montbard, Darcey, Thenissey, Verrey, Blaisy-Bas, Mâlain, Lantenay, Velars, Plombières | 20279, 20278, 17528, 20277, 20276, 20275, 17539, 17538, 17537, 17536, 17535, 17526, 17527, 20274 | 204,1 à 308,6 | 01/01/1847, `inferred:line_event` (ouverture Wikidata de toute la ligne) | `station_eras` pas recalculé : la chaîne a refait les tronçons (13 h 57) mais pas `vff:build-station-eras` (dernier calcul 13 h 39-13 h 42). Au prochain calcul, la date déduite devient le 22/06/1851 (événement de section 99484, prioritaire sur « toute la ligne ») et la gare n'est plus active en 1850. Rien à corriger dans les données. |
| Nuits-sous-Ravières | 17542 | 224,8 | 1849, Wikidata | même cause, plus une date fausse : Wikidata donne 1849 (ouverture Paris - Tonnerre) ; l'article de la gare dit « mise en service ... en juin 1851 ». Correction sourcée ajoutée : 22/06/1851. |
| Tonnerre | 17543 | 196,2 | 12/08/1849 (correction, juste : terminus de 1849 à 1851) | portion mal placée de 136 m : « Tonnerre » des sections Paris - Tonnerre (97058) et Tonnerre - Dijon (99484) est résolu sur le point de la liste des gares SNCF, à 136 m à l'ouest de la gare VFF (position OSM, à 4 m du tracé). En 1850, le tronçon en service s'arrête à 182 m de la gare, au-delà des 150 m de l'invariant. Corrigé dans le code (voir plus bas). |

## stations.opened_before_lines : les 2 nouveaux cas

| Gare | id | Gare | Ligne (nouvel événement) | Cause et correction |
| --- | --- | --- | --- | --- |
| Chamonix-Mont-Blanc | 17929 | 12/07/1901 (infobox de l'article de la gare) | 896000 Le Fayet - Chamonix 25/07/1901 (99491) | le 12/07 est l'inauguration (« La gare d'origine, inaugurée le 12 juillet 1901 ») ; l'article de la ligne : « inaugurée le 12 juillet 1901. Elle ouvre au public le 25 juillet ». Date de la gare portée au 25/07/1901 (comme Les Houches, déjà au 25/07/1901), entrée déplacée de `gares_dates_articles.php` vers `gares_dates_invariants.php`. |
| Aime-La Plagne | 17859 | 20/10/1913 (article de la gare) | 899000 Moûtiers - Bourg-Saint-Maurice 20/11/1913 (99496) | l'article d'Aime est seul à dire « inaugurée le 20 octobre 1913, en même temps que la portion de ligne » ; les articles des gares de Bourg-Saint-Maurice (d'après Penna, *La Tarentaise Autrefois*, 2005 ; inauguration le 28/06/1914) et de Landry, et la chronologie de la ligne donnent la mise en service le 20/11/1913. Entrée de `gares_corrections.php` corrigée au 20/11/1913. |

Les 30 autres cas existaient avant la passe : aucun n'est couvert par un des 70 nouveaux événements (contrôle fait).

## Corrections

1. `app/Services/SectionResolver.php` : dans `geocodeStations()`, une gare consolidée (table `stations`, nom principal ou
   autre nom de `station_sources`) rattachée à la ligne et au nom exact reçoit un avantage de 0,01 (équivalent de 200 m
   d'écart au tracé) sur la gare SNCF homonyme de la même ligne : l'extrémité de la portion tombe sur la gare que montrent
   la carte et `station_eras`, pas sur le point de la liste des gares SNCF. Il ne départage que des candidats presque à
   égalité ; sans rattachement à la ligne, le point SNCF reste choisi. Seuls les événements résolus de nouveau changent.
   Tests : `SectionResolverTest::test_consolidated_station_of_the_line_wins_over_the_sncf_point_of_the_same_name`
   (échoue sans le changement) et `test_sncf_point_is_kept_when_the_consolidated_station_is_not_linked_to_the_line`.
2. `database/data/gares_corrections.php` : Aime-La Plagne au 20/11/1913.
3. `database/data/gares_dates_articles.php` : ligne Chamonix-Mont-Blanc retirée (commentaire à sa place).
4. `database/data/gares_dates_invariants.php` : Chamonix-Mont-Blanc au 25/07/1901, Nuits-sous-Ravières au 22/06/1851.

Contrôles : `SectionResolverTest` et `StationPlacementTest` (80 tests), `StationCorrectionsTest`,
`StationCorrectionsConsistencyTest`, `StationCorrectionsMutationTest`, `SourcedCorrectionsInvariantsTest`,
`TypographicDashTest`, `StationsWithoutTrackCorrectionsTest` (61 tests) verts ; Pint propre ; PHPStan sans erreur
sur `SectionResolver.php`.

## Portions des 70 ajouts relues (pour mémoire, sans effet sur ces deux invariants)

- Résolutions justes pour les noms signalés : Barbentane - Rognonas, Saint-Chamas (« St-Chamas »), La Plaine
  (« La Plaine-Voyageurs »), Sevran (commune de la ligne, 7 m), Sathonay (« Sathonay-Rillieux »), Lutterbach (nom OSM de
  la gare), Le Fayet (« Le Fayet - Gare », nom Wikidata), Chamonix (« Chamonix-Mont-Blanc »), Moûtiers (commune de la
  ligne, 5 m), Bourg-Saint-Maurice, Courtalain - Saint-Pellerin (5 m).
- À reprendre :
  - 925000 « Cheval-Blanc » (99498, 99499) : résolu sur « Cheval-Blanc Base-Travaux-LGV », à 1 018 m du tracé (gare
    de 923000) ; aucune gare Cheval-Blanc connue sur 925000 (liste Wikipédia : Cavaillon puis Orgon). Les deux dates
    (1872, 1873) tombent dans la même décennie : pas d'effet sur la carte, mais l'extrémité est à 1 km près ;
  - 070000 Nancy - Sarrebourg (99444, 12/08/1852) : non résolu, « pas de chemin sur le tracé » ;
  - 830000 Chalon-sur-Saône - Lyon-Vaise (99429) : non résolu, même raison ;
  - 070000 « Strasbourg » à 562 m du tracé ; 897000 « Annemasse » à 228 m (gare SNCF d'une autre ligne) ;
  - extrémités prises sur une commune à 1,2 à 3,1 km de son centre : Thionville (204000), Bourg-en-Bresse (886000),
    Creil (242000), Lison (415000), Saint-Pierre-d'Albigny (899000), Thorigné et Montmirail (506000), Favières (041000).

## Commandes pour le coordinateur (non lancées)

Après la file en cours, avec la sauvegarde ciblée habituelle :

```
bin/php artisan vff:resolve-event-sections --line=830000 --all --dry-run   # contrôle : Tonnerre -> gare « Tonnerre »
bin/php artisan vff:resolve-event-sections --line=830000 --all
flock storage/app/reports/agents/.era-segments.lock bin/php artisan vff:build-era-segments --line=830
for i in 1 2; do bin/php artisan vff:build-shared-track --shard=$i/2 & done; wait
bin/php artisan vff:build-station-eras        # applique aussi les corrections de gares (Aime, Chamonix, Nuits)
bin/php artisan vff:check-invariants --only=stations.active_without_track,stations.opened_before_lines
```

Le `--all` de la ligne 830000 recalcule toutes ses portions : les extrémités nommées d'après une gare de la ligne
peuvent se déplacer de quelques dizaines de mètres vers la gare VFF (c'est le but). Effet attendu :
`stations.active_without_track` 61 -> 45 (14 gares par le seul recalcul des ères des gares, Nuits-sous-Ravières par
sa date, Tonnerre par la portion), `stations.opened_before_lines` 32 -> 30. Si Tonnerre restait à plus de 150 m du
tronçon de 1850 (lissage des tronçons de bord), le signaler : la portion sera alors bien sur la gare et la cause sera
dans `vff:build-era-segments`.

Leçon pour les chaînes suivantes : après des ouvertures datées, lancer `vff:build-station-eras` avant les invariants
(dates de gares déduites des sections et `station_eras`).
