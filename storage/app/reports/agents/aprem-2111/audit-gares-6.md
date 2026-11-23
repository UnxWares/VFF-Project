# Audit des gares, septième passe (audit-gares-6, 23/11/2026)

Lecture seule sur `vff`. Sources : API Wikipédia (article de la gare puis article de la ligne, révisions citées) et API Wikidata (titres), 5 s entre deux requêtes, User-Agent du projet ; Gallica non utilisé.

## Périmètre
150 gares `active` de type `gare`, maximum de `footfall` depuis 2019 de 0,148 M (Arnèke) à 0,05 M (Chevrières), dont l'ouverture n'est ni une correction sourcée ni une date au jour de Wikidata, hors les 484 gares des passes précédentes. Il n'y avait aucune gare non relue entre Vernaison (0,184 M) et Arnèke. Liste et verdict : `storage/app/reports/agents/aprem-2111/audit-gares-6/verifiees.csv` (150 lignes).

## Résultat
- 20 ouvertures corrigées (section 45) : Bagneaux-sur-Loing 1916 (et non 1860), Saint-Roch (Somme) 1876 voyageurs, Pauillac 1870, Artix 1863, Puyoô 1863 (section Pau - Puyoô, confiance moyenne), Meroux 08/12/2018 (et non le 09), Pléchâtel 11/1864, Guéthary 1884, Margaux 1868, Nœux-les-Mines 15/10/1861, Lutzelbourg 05/1851, Retiers 12/1881, Mende 03/05/1884, Morez 12/08/1900, Delle 29/06/1868, Saujon 28/08/1875, Istres et Port-de-Bouc 1882 (mise en service de la ligne Miramas - Port-de-Bouc, et non la concession de 1875), Oyonnax 16/05/1885, Pont-Saint-Vincent 11/11/1872.
- 127 confirmées (l'article de la gare ne donne aucune date d'ouverture, ou la même : la date de la section de la ligne est gardée).
- 3 à décider : Sainte-Colombe-Septveilles (la halte n'était qu'une maison de garde-barrière à l'ouverture de 1858), Montaudran (halte des années 1980-1990), Neufchâteau (gare construite en 1866, section en 1867).
- Limite : la relecture est surtout automatique (infobox et phrases datées de l'article de la gare) ; les gares sans date dans l'article n'ont pas été recoupées avec le texte détaillé de la ligne, comme dans les passes précédentes.

## Totaux cumulés (634 gares relues)
174 corrigées (154 + 20), 398 confirmées, 62 à décider.

## Contrôles
`StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `StationCorrectionsConsistencyTest`, `TypographicDashTest` : 84 tests verts (base `vff_jour_audit_gares_6_test`) ; Pint propre ; aucun tiret long.

## Application (non lancée, coordinateur)
```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-audit-gares-6.dump
bin/php artisan vff:build-station-eras --parallel=2
```
20 gares changent d'événement d'ouverture et de `opened_at`. À lancer avec les sections 36 à 42 si elles ne sont pas encore appliquées : une seule reconstruction suffit.
