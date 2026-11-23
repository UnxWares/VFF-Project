# Audit des gares, cinquième passe (audit-gares-5, 23/11/2026)

Lecture seule sur `vff` (aucune écriture). Sources : API Wikipédia et API Wikidata (révisions citées, requêtes par lots, 5 s entre deux requêtes, User-Agent du projet), article de la gare puis article de la ligne. Gallica et sites protégés non utilisés. Classement par fréquentation (maximum 2019 et après du champ `footfall` de `station_attendances`), gares actives dont l'ouverture n'est ni une correction sourcée ni une date au jour de Wikidata (même périmètre que les passes précédentes), après Boulogne-Tintelleries (0,39 M), en écartant les 302 gares déjà relues et les deux gares déjà corrigées dans le fichier (Nice-Saint-Augustin, Petit-Jouy-Les Loges).

Remarque de périmètre : une cinquantaine de très grandes gares du haut du classement (Paris-Est, Magenta, Nantes, Bondy, Toulouse-Matabiau, Poissy, Nice-Ville, Lyon-Perrache, Chelles-Gournay, Saint-Étienne-Châteaucreux, Mâcon-Ville, Vichy, Alès...) ne figurent dans aucune des cinq passes : leur ouverture vient d'OSM ou d'OHM au jour (ou de la ligne) et passe donc le filtre, mais les passes précédentes les avaient laissées de côté. À relire dans une passe suivante.

## 1. 120 gares de plus (de Montchanin 0,39 M à Vernaison 0,18 M)

- 42 ouvertures corrigées (section 41) : Montchanin 21/09/1861 (et non 1858), Colomiers 22/10/1877 (et non 1960 d'OHM), Petit-Vaux 01/05/1883, Munster 03/12/1868, Brignoud et Lancey 15/09/1864, Salon 28/05/1873, Presles-Courcelles 05/04/1877 (et non 1880, gare de la ligne d'Épinay au Tréport), Janzé 28/12/1881, Saint-Germain-des-Fossés 19/06/1854, Chasse-sur-Rhône 06/06/1857 (et non 1852), Ingwiller 16/12/1889 (et non 16/11), Chalonnes 24/09/1866, Hœrdt et Weyersheim 18/07/1855, Saint-Maurice-de-Beynost et Beynost 23/06/1856, Reignier et Groisy-Thorens 05/06/1884 (et non 1883), La Possonnière et Oudon 21/08/1851 (Oudon : le 1848 d'OSM est l'ouverture de Tours - Saumur), Billy-Montigny 01/01/1864, Loches 15/07/1878, Mouans-Sartoux 13/11/1871, Montdidier 01/06/1883, Lézignan 22/04/1857, Saint-Étienne-Bellevue 1857 (première gare, règle du déménagement), Cesson-Sévigné 06/1994, Sain-Bel 10/11/1873, La Valbonne 1872 (la ligne de 1856 n'avait pas d'arrêt), Saint-Gilles-Croix-de-Vie 17/10/1881, Laigneville 28/08/1892, Aurillac 1866, Marquise-Rinxent 07/01/1867, La Borne-Blanche 06/12/1964 (et non mai 1962, construction), Villabé 01/06/1897, Châtel-Nomexy 24/06/1857, Sainte-Musse 11/12/2022, Lavaur 04/03/1888, Saint-Pierre-d'Albigny 20/10/1856, Réaumont-Saint-Cassien 10/07/1857, Saint-Loubès 20/09/1852.
- 51 confirmées (article de la gare datant la même ouverture, ou aucune date dans l'article : la date de la section de la ligne est gardée).
- 27 à décider (motif dans le CSV) : Le Pallet et La Haie-Fouassière (30/12/1866 selon la ligne, 26/12/1866 en base, comme Montaigu-Vendée), Cros-de-Cagnes, Évron, Èze (1866 de l'article contre 1868 de la section Nice - Monaco), Pontarlier (24/07, 25/07 ou 17/07/1860), Saint-Mariens - Saint-Yzan, Rognac, Lentilly-Charpenay, Le Sablon, Vernaison, Saint-Pol-sur-Ternoise (21 ou 22/11/1875), La Souterraine (marchandises 1856, voyageurs 1858), Labège-Innopole, Colomiers-Lycée-International, La Hume, Don-Sainghin (1865 ou 1867), Boissy-l'Aillerie et Chars (04 ou 10/10/1868), Cannes-la-Bocca (1885 ou 1886), Romorantin, Reims-Maison-Blanche, Avesnes (1869 ou 1872), L'Estaque, Rosières, Lillers et Isbergues (le 06/10/1860 en base est la date de Lens - Le Forest, pas celle de leur section : date de l'ouverture à rechercher).

Détail de chaque gare, verdict et motif : `storage/app/reports/agents/aprem-2111/audit-gares-5/verifiees.csv` (120 lignes dans l'ordre de fréquentation).

## Totaux cumulés de l'audit des dates d'ouverture (422 gares relues)

140 corrigées (24 section 36, 26 section 37, 20 section 39, 28 section 40, 42 section 41), 229 confirmées, 53 à décider (les 26 des passes précédentes et les 27 ci-dessus).

## Contrôles

`StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `StationCorrectionsConsistencyTest`, `TypographicDashTest` : 84 tests verts (base de test `vff_jour_audit_gares_5_test`) ; Pint propre ; aucun tiret long.

## Application (non lancée, coordinateur)

```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-audit-gares-5.dump
bin/php artisan vff:build-station-eras --parallel=2
```
42 gares changent d'événement d'ouverture et de `opened_at`. À lancer avec les sections 36 à 40 si elles ne sont pas encore appliquées : une seule reconstruction suffit.
