# Ouvertures des gares fermées aux voyageurs, première passe (23/11/2026)

Lecture seule sur `vff` (aucune écriture). Les audits précédents (sections 36 à 42) n'avaient relu que les gares encore ouvertes. Cette passe prend les gares fermées (statut déclassée ou fret seul, avec un événement de fermeture) qui ont un article Wikipédia de gare (lien de site Wikidata) et dont l'ouverture de la base n'est ni une correction sourcée ni un jour exact de Wikidata : année Wikidata ou OHM, date déduite de la ligne ou du tracé. 934 gares dans ce périmètre ; les articles de gare et de ligne viennent du cache disque des articles (`storage/app/sources/stations/wikipedia`, révisions citées), aucune requête réseau nouvelle, Gallica non utilisé, archeoferroviaire non cité.

## Sélection des 120 gares

Les 934 articles ont été lus (infobox, section Histoire). 84 gares dont l'article de la gare donne une date différente de celle de la base (jour, mois ou année), puis 36 gares dont l'article donne la même année que la base (jour tiré de la ligne). Pour chaque cas à écart, lecture de l'histoire de la gare puis de la chronologie de l'article de la ligne (Couliboeuf, Messei, Manduel, Étretat, Le Thillot, Hazebrouck - Hondschoote).

## Résultats

- 59 ouvertures corrigées (section 43) :
  - jour exact : Versailles-Matelots 12/05/1871 (et non 1883), Couliboeuf 01/02/1859, Manduel-Redessan 19/08/1840, Voellerdingen 01/05/1895, Saint-Ouen-Garibaldi 28/05/1972 (gare créée en 1972, pas 1874), Bel-Air - Fourqueux, Saint-Germain-en-Laye-Grande-Ceinture, Poissy-Grande-Ceinture et Saint-Cyr-Grande-Ceinture 04/09/1882 (et non 1877), Toutainville 08/06/1889, Messei 02/07/1866 (et non 1874), Lambel-Camors 29/05/1899, Croix-d'Hins 07/05/1841, Sincey-lès-Rouvray 23/08/1882, La Calade-Éguilles 11/05/1872, Sainte-Anastasie 29/11/1880, Rotangy 15/04/1876, Reuilly et Bel-Air (Paris) 22/09/1859 (et non 17/08/1853), Hazebrouck - Hondschoote 08/09/1894 pour six gares (Saint-Sylvestre-Cappel, Winnezeele, Killem, Rexpoëde, Herzeele, Bambecque ; la base avait le 01/07/1897), Étretat 22/06/1895, Courcelles-Ceinture 25/03/1869, La Cantraie 24/01/1903, Pierrefonds 24/01/1884, Nantes-État 15/04/1876, Le Thillot 07/11/1879, Tocqueville-Gouberville 08/07/1911, avenue de Saint-Ouen 16/11/1863, Sainte-Menehould 23/07/1867.
  - année : haltes ouvertes bien après la ligne (Achicourt 1910, Saint-Julien 1873, Cognin 1951, Plan-Plascassier 1892, Pont-d'Espenel 1898, Auzits-Aussibals 1875, La Tâche 1909, Cepoy 1888), gares créées après la ligne (Myennes 1864, Angers-Saint-Serge 1878, Mandres 1876, La Bourse 1857, Limours-État 1930, Boulevard Ornano 1869, Grenelle-Ceinture 1867), premières gares (Ravezies 1868, règle du déménagement), Le Havre-Graville 1882, Boulogne-Aéroglisseurs 1970, Brézilhou 1938 et Massia 1956 (dates approximatives « vers »).
- 41 confirmées : 5 après lecture de la ligne (Héming 12/08/1852, l'infobox de la gare est contredite par son texte et par la ligne ; Fort-l'Écluse-Collonges ; Saint-Nazaire, mise en service le 10/08/1857 et inauguration le 17/08/1857 ; Trémentines, la construction de 1865 n'est pas l'ouverture ; Villeurbanne) et 36 dont l'article donne la même année que la base.
- 20 à décider (motif dans le CSV) : cinq gares du Calvados et le réseau des houillères de Ronchamp (l'article lié à la fiche est celui du réseau, sans date de gare : Trévières, Varaville Le Home, Asnelles, Sallenelles, Bénouville, Ronchamp), dates contradictoires dans l'article (Noailles 1891 ou 1893, Tournon 1873 ou 1874, Sundhoffen 1878 ou 1880, La Motte-Sainte-Roseline 1863 ou 1864, La Bâthie), arrêts ouverts à une date seulement bornée (Fontenille, Crouin, Le Coudret, Nozay, Oisy-le-Verger, La Béraudière, Le Mans-les-Halles, Saint-Benoît-Saint-Aignan) et Saint-Louis-les-Aygalades (gare à charbons de 1855 ou gare des Aygalades de 1864).

Autres constats : les gares de Petite Ceinture, de Grande Ceinture et des réseaux secondaires (Calvados, Charentes, Flandres) sont les plus touchées par la date déduite de la ligne, parce que la ligne précède souvent la gare ou la halte de plusieurs années. Les 814 gares restantes du périmètre n'ont pas été relues (article sans année exploitable ou accord sur le jour) ; 250 d'entre elles n'ont pas de date dans l'infobox : prochaine passe.

Détail de chaque gare : `storage/app/reports/agents/aprem-2111/ouvertures-gares-fermees/verifiees.csv` (120 lignes).

## Contrôles

`StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `StationCorrectionsConsistencyTest`, `TypographicDashTest` : 84 tests verts (base de test `vff_jour_ouvertures_gares_fermees_test`) ; Pint propre ; aucun tiret long ; chaque gare visée par une clé unique (UIC ou Wikidata) dans `vff` (vérifié en lecture) ; aucune nouvelle ouverture ne suit la première fermeture de la gare.

## Application (non lancée, coordinateur)

```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-ouvertures-gares-fermees.dump
bin/php artisan vff:build-station-eras --parallel=2
```
59 gares changent d'événement d'ouverture et de `opened_at`. À lancer avec les sections 36 à 42 si elles ne sont pas encore appliquées : une seule reconstruction suffit.
