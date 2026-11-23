# Audit des fermetures 3 (audit-fermetures-3, 23/11/2026)

Lecture seule sur `vff` (aucune écriture, aucun calcul du moteur). Sources : API Wikipédia (articles de ligne et de gare cités en `oldid`), API Wikidata (titres frwiki), User-Agent du projet (VFF-Bot), 5 s entre deux requêtes, aucune barrière contournée. Gallica non utilisé. La presse numérisée par API et le Journal officiel n'ont rien apporté de plus que les articles de ligne pour ces lignes (pas d'accès par API libre à du texte daté exploitable) ; les arrêtés de fermeture ne sont cités qu'à travers les articles.

## Méthode

1. Partie 1 : les 42 gares « sans source datée » de la passe 2. Lecture de l'article de la ligne (chronologie par sections), des lignes voisines pour les gares de bifurcation, et de l'article de la compagnie pour Lassigny.
2. Partie 2 : 140 gares (au lieu de 120) de lignes fermées aux voyageurs entre 1938 et 1990 dont la fermeture voyageurs est encore `inferred:line_event`, prises par longueur de ligne décroissante (15 lignes, de Givors-Canal à Grezan, 254 km, à Port-de-Piles à Argenton-sur-Creuse), sans doublon avec les CSV précédents ni avec les corrections de fermeture déjà versionnées. Seulement 15 de ces gares ont une fiche Wikidata liée à frwiki et aucune de ces fiches ne date la fermeture : la confrontation se fait donc sur l'article de la ligne, jour par jour, avec un contrôle automatique que la date déduite figure dans l'article (jour ou année).
3. Distinction faite entre arrêt du service, suspension, fermeture officielle, fin du fret et déclassement. Une date qui change l'existant n'est corrigée que si l'article de la ligne date la section de la gare (chronologie de la ligne) ou si deux articles concordent (Beaune-la-Rolande).

## Résultat

| | corrigées | confirmées | à décider | sans source |
|---|---|---|---|---|
| Partie 1 (42 gares) | 14 | 16 | 6 | 6 |
| Partie 2 (140 gares) | 5 | 133 | 2 | 0 |

Attention à la portée des 133 confirmations de la partie 2 : elles sont faites au niveau de la section de ligne (la date déduite est bien celle que l'article donne pour la section où se trouve la gare), pas de la gare. Aucune fiche de gare ne les date ; une halte peut avoir fermé avant sa section.

### Corrections (section 49 de `database/data/gares_corrections.php`, 19 gares)

- Fermeture voyageurs du 15/05/1939 (article de la ligne de Loudun à Châtellerault, voyageurs repris à la guerre seulement jusqu'à Châtellerault-Châteauneuf) : Le Bouchet, La Bourdigalière, Berthegon, Cernay-Doussay, Monts-sur-Guesnes (la date déduite était la fin du fret ou la fermeture de section : 1987, 2010, 2021).
- Beaune-la-Rolande : 15/05/1939 (Pithiviers - Beaune-la-Rolande ; la ligne d'Auxy - Juranville à Bourges donne 1939 pour Beaune - Bourges et 1932 pour Auxy - Beaune), au lieu de 1992 (fin du fret).
- Péronne-la-Chapelette : 01/04/1970 (voyageurs supprimés de Montdidier à Cambrai), au lieu du 24/02/1975. Confiance moyenne (halte non citée nommément).
- Bray-Dunes : 28/08/1994, dernières circulations (trains des dimanches d'été), jour précisé à la place de l'année.
- La Haye-du-Puits : été 1976 (année), au lieu du 01/08/1976, qui est la fin du fret Baupte - La Haye-du-Puits. La fin du fret de la gare (24/01/1988) est confirmée.
- Domfront (Orne) : 31/05/1970 (La Chapelle-Anthenaise - Flers fermée en totalité aux voyageurs), au lieu de 2000. Fin du fret Flers - Domfront le 01/10/1990 notée dans la raison, non appliquée.
- Eauze : 27/06/1940 (Port-Sainte-Marie - Riscle ; Eauze - Auch le 15/05/1939), la fiche n'avait aucun événement de fermeture en base.
- Montpellier - Chaptal : 01/02/1948 (voyageurs supprimés, après suspension du 01/01/1933 et rétablissement du 26/08/1939), au lieu de 1953.
- Maxonchamp : fermeture de la gare le 28/05/1989, fin de l'exploitation Remiremont - Bussang (type `closure`), au lieu du 15/11/1993 qui est le déclassement.
- Barjols-Tavernes : fermeture le 02/01/1950 (ligne Central-Var), jour précisé à la place de l'année.
- Ligne des Sables-d'Olonne à Tours, voyageurs arrêtés le 31/05/1970 entre Thouars et Chinon : Orbe, Pas-de-Jeu, Basses-Sammarcolles, Beuxes, La Roche-Clermault (jour précisé à la place de l'année 1970).

### Confirmées en partie 1 (16)

La Chapelle-la-Reine (voyageurs 01/08/1937, fin du fret 15/03/1950), Châteauvillain et Latrecey (voyageurs 18/04/1939, rouverts en 1939 et 1940 ; la date de 2015 n'est pas une fermeture de gare), Colleret (voyageurs 01/07/1939, ligne rouverte pendant la guerre), Quetteville et Saint-André-d'Hébertot (voyageurs 26/09/1971, trafic cessé le 01/06/1975 entre Pont-l'Évêque et Quetteville), Tronget (1972 ; l'article dit aussi 1969, le jour 06/03/1972 en base n'est pas confirmé), Nogaro et Manciet (voyageurs 27/06/1940, dernières circulations fin 2007), Grimbosq (halte, voyageurs 1970, la fermeture de 2003 déduite n'est pas la sienne), La Roche-Posay, Leigné-les-Bois, Yzeures-sur-Creuse et Pleumartin (voyageurs 15/09/1939, fret 07/10/1951 et 16/12/1973), Saint-Savin (voyageurs 27/05/1940), Vouvant - Cezais (voyageurs 01/03/1940, fret 08/04/1955).

### À décider (8) et sans source (6)

- À décider : Harnes-Halte (ligne d'Hénin-Beaumont à Bauvin - Provin, voyageurs 07/11/1938, halte non citée), Meyzieu (CFEL, 1947, mais la fiche est mêlée à la station du tramway T3 ouverte en 2006 : à scinder), Surzur (ligne fermée avant le 01/04/1947 sans date, deux lignes), Saint-Martin-des-Besaces (Caen - Vire, 01/03/1938, et tramway de Bayeux), Breuil-Barret (deux lignes), Luché-Thouarsais (ligne encore ouverte), et en partie 2 Moulot et Billy-sur-Oisy (le `closure` du 15/05/1938 déduit est contredit par le fret maintenu de Clamecy à Entrains, exploité depuis 1977 : à corriger par `closure` sourcé ou à retirer).
- Sans source : Béthune-Rivage, Civry-Saint-Cloud, Lassigny, Ouistreham, Ouistreham Riva-Bella et Bénouville (réseau du Calvados : l'exploitation cesse en 1944 sans jour, le 06/06/1944 déduit est sans source).

Ne sont pas reprises : les gares « à décider » de la passe 2, prises par gares-a-decider.

## À signaler

- La ligne de Givors-Canal à Grezan a rouvert aux voyageurs le 29/08/2022 entre Pont-Saint-Esprit et Nîmes-Centre (Bagnols-sur-Cèze, Avignon-Centre) : aucune de ces gares n'est dans la liste, mais les 30 autres gares de la ligne sont bien fermées depuis le 06/08/1973.
- Les dates par sections de la ligne de Clamecy à Gilly-sur-Loire viennent de lignes-oubliees seule (l'article Wikipédia n'en donne aucune) : confirmées par défaut.
- La fermeture voyageurs (`closure_passenger`) est ajoutée sans retirer le `closure` déduit de la ligne quand la gare a servi au fret après les voyageurs (Le Bouchet, Berthegon, Beaune-la-Rolande...), comme aux sections 44 et suivantes.

## Contrôles

`StationClosureAudit3CorrectionsTest` (nouveau, une correction de fermeture par gare, date, précision, permalien, citation ; échoue sans la section 49), `StationClosureAudit*`, `StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `EventCorrectionFilesTest`, `StationAnomaliesCorrectionsTest`, `TypographicDashTest` : 94 verts. Chaque citation vérifiée mot pour mot dans l'article cité (script). Pint propre, aucun tiret long. Base de test `vff_jour_audit_fermetures_3_test`.

Fichier : `storage/app/reports/agents/aprem-2111/audit-fermetures-3/verifiees.csv` (182 lignes : station_id, gare, date_avant, date_retenue, source, verdict, motif, partie).

## Application (non lancée, coordinateur)

```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-audit-fermetures-3.dump
bin/php artisan vff:build-station-eras --parallel=2
```
Les 19 gares changent d'événement de fermeture (source `correction`) ; ères touchées de 1930 à 2000 selon la gare (surtout 1930, 1940, 1970, 1990).
