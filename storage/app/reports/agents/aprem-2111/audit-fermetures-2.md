# Audit des fermetures 2 (audit-fermetures-2, 23/11/2026)

Lecture seule sur `vff` (aucune écriture, aucun calcul du moteur). Sources : API Wikidata (titres frwiki) et API Wikipédia (révisions citées en `oldid`), User-Agent du projet (VFF-Bot), 5 s entre deux requêtes, aucune barrière contournée. Gallica et sites sans API non utilisés.

## Méthode

1. Candidates : gares `declassified` dont la date de fermeture est déduite de la ligne (`provenance.closed_at` = `inferred:*`), avec une fiche Wikipédia (via Wikidata). Les gares déjà corrigées en fermeture dans `gares_corrections.php` (532 corrections de fermeture à HEAD, pas encore appliquées en base) sont écartées : 7 gares tirées d'abord (Faymont, Saint-Géry, Conduché, Saint-Cirq-Lapopie, Saint-Martin-Labouval, Toirac, Saint-Loup-Lamairé) l'étaient déjà et ont été remplacées par les suivantes.
2. Partie 1 : 22 gares de lignes encore ouvertes (un arrêt actif ou touristique de la même ligne à moins de 8 km) non relues par l'audit précédent. La liste exacte des 22 de l'audit précédent n'était pas conservée (elle dépendait de l'état de la base du matin) ; ces 22 sont tirées par empreinte MD5 de l'identifiant parmi les candidates de lignes ouvertes hors CSV précédent, hors dates nationales de 1938 et 1939.
3. Partie 2 : 100 gares de lignes fermées (aucun arrêt actif à moins de 8 km sur la même ligne) dont la fermeture déduite est postérieure à 1938, de 1939 à 2016, dans l'ordre chronologique.
4. Lecture de l'article de la gare (infobox et prose) puis, pour les gares sans date, de l'article de la ligne. Distinction faite entre fermeture voyageurs (`closure_passenger`), fermeture de la gare (`closure`), fin du fret et déclassement. Une date n'est corrigée que si l'article dit explicitement l'événement de la gare ou de sa section. Les doublons de dates (même gare, déjà corrigée) sont écartés.

## Résultat : 122 gares

| | corrigées | confirmées | à décider | sans source |
|---|---|---|---|---|
| Partie 1 (lignes ouvertes) | 8 | 5 | 7 | 2 |
| Partie 2 (lignes fermées, après 1938) | 30 | 15 | 15 | 40 |
| Total | 38 | 20 | 22 | 42 |

Corrections (section 44 de `database/data/gares_corrections.php`, 38 gares, toutes en `closure_passenger` sauf Régneville-sur-Mer, Drincham, Pitgam, Cogolin La Foux, Saint-Tropez et Draguignan en `closure`) :
- fermeture voyageurs du 15/05/1939 : Anderny, Landres, Joudreville (Baroncourt - Audun-le-Roman, dates déduites 1973 et 1986), Ouzouer-Dampierre, Saint-André-de-Lidon ;
- 1940 : Pouligny-Saint-Pierre (27/06), Amélie-les-Bains et Céret (23/05), Ingrandes-Mérigny et Saint-Aigny - Le Blanc (27/05), Concremiers et Chauvigny (année) ;
- Belvianes (18/04/1939), Soucht, Rosteig et Saint-Louis-lès-Bitche (28/09/1969, au lieu du déclassement du 26/07/1973), Gaël (06/03/1972, au lieu de 2018) ;
- Montmorency (30/06/1954 au lieu de 1947), Champtercier (06/03/1972 au lieu de 1988), Mortagne-au-Perche (1953), Saint-Hilaire (Indre) et Liglet (01/03/1933), Saint-Denis-de-Jouhet (01/07/1939), Noyant-d'Allier (1972), Audun-le-Tiche-Mont et Aumetz (05/05/1948), Roisel (01/04/1970), Aire-sur-l'Adour (12/10/1970), Airvault-Ville (1980), Gourgé (27/09/1980 : section Thouars - Parthenay - Niort) ;
- fermeture de la gare : Drincham et Pitgam (1951, avec la ligne de Bergues à Bollezeele), Cogolin La Foux (1948), Saint-Tropez (04/06/1949, jour précisé), Draguignan (1981, fermée au trafic ferroviaire), Régneville-sur-Mer (1942).

À décider (22, motif dans le CSV) : Hielle, Tencin-Theys, Saumur-Rive-Gauche, Trévières (l'article ouvert est celui du réseau des Chemins de fer du Calvados), Sembadel (train touristique), Corbenay, Civray-Ville, Falaise, Courseulles, Nozay (gare probablement desservie depuis la réouverture Nantes - Châteaubriant de 2014 : fiche à vérifier), Isigny-sur-Mer (gare en service), Saint-Genest-d'Ambière (fermée aux voyageurs le 15/05/1939 puis autorail de mai à août 1944), Savigny-sous-Faye, Carquefou, Hymont-Mattaincourt, Les Islettes et Clermont-en-Argonne (infobox « TER Grand Est » : gares desservies, fiches déclassées à vérifier), Benet, Tournon-Saint-Martin, Richelieu, Saint-Pourçain-sur-Sioule, Forcelles-Saint-Gorgon.

Sans source (42) : l'article de la gare et celui de la ligne ne datent pas la fermeture (Béthune-Rivage, Harnes-Halte, Civry-Saint-Cloud, Surzur, Meyzieu, Barjols-Tavernes, La Chapelle-la-Reine, Leigné-les-Bois, Montpellier - Chaptal, Lassigny, Vouvant - Cezais, Luché-Thouarsais, La Roche-Posay, Yzeures-sur-Creuse, Pleumartin, Péronne-la-Chapelette, Saint-André-d'Hébertot, Grimbosq (« au XXe siècle »), Berthegon, Cernay-Doussay, Monts-sur-Guesnes, La Haye-du-Puits, Saint-Savin, Maxonchamp, Breuil-Barret, Beaune-la-Rolande, Bray-Dunes, Tronget, Domfront (Orne), Quetteville, Nogaro, Manciet, Eauze, Colleret, Le Bouchet, La Bourdigalière, Châteauvillain, Latrecey, et les trois gares de tramway du Calvados Ouistreham, Ouistreham Riva-Bella et Bénouville, ainsi que Saint-Martin-des-Besaces, dont l'article ouvert est celui du réseau).

Confirmées (20) : Gruson (2015), Vorges et Presles-et-Thierny (01/06/1932), Caligny (fin du fret 29/05/1988), Roncq (12/12/1971, fin des voyageurs Tourcoing - Halluin), Cavone, Toulon Saint-Jean-du-Var, Réville, Mouchamps, Laruns - Eaux-Bonnes, Saint-Germain-de-Joux, Barbonne-Fayel, L'Orme-Saint-Pantaléon, Sarre-Union, La Rochefoucauld, Saint-Maurice, Fresse, Rupt-sur-Moselle, Ferdrupt, Ramonchamp (28/05/1989 : fin de l'exploitation Remiremont - Bussang, sans date propre à la gare), Saint-Loup-Lamairé (déjà corrigée).

Fichier : `storage/app/reports/agents/aprem-2111/audit-fermetures-2/verifiees.csv` (station_id, gare, date_avant, date_retenue, source, verdict, motif, partie).

## À signaler

- Beaucoup de gares candidates avaient déjà une correction de fermeture à HEAD que la base n'a pas encore appliquée (la liste des candidates de la base est donc périmée tant que `vff:build-station-eras` n'est pas rejoué).
- Le motif de fermeture d'une halte desservie par le TER (Hymont-Mattaincourt, Les Islettes, Clermont-en-Argonne, Isigny-sur-Mer, Nozay) est une erreur de fiche ou de statut, pas de date : à traiter par `current` ou par exclusion, pas par une fermeture.
- Les articles Wikipédia de nombreuses haltes (Loudun - Châtellerault, Châtellerault - Launay, Épinal - Bussang) n'ont pas de date de fermeture : les lignes-oubliees et Gallica seraient la suite.

## Contrôles

`StationClosureAudit2CorrectionsTest` (nouveau, une correction de fermeture par gare, date, précision, permalien, citation ; échoue sans la section 44), `StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `EventCorrectionFilesTest`, `StationAnomaliesCorrectionsTest`, `TypographicDashTest` : 91 verts. Pint propre, aucun tiret long. Base de test `vff_jour_audit_fermetures_2_test`.

## Application (non lancée, coordinateur)

```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-audit-fermetures-2.dump
bin/php artisan vff:build-station-eras --parallel=2
```
Les 38 gares changent d'événement de fermeture (source `correction`) ; les ères touchées vont de 1930 à 2020 selon la gare (surtout 1940, 1950, 1970 et 1980 pour les fermetures voyageurs de 1939, 1969 et 1980).
