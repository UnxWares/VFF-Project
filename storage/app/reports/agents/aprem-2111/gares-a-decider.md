# Gares « à décider » des audits d'ouverture (gares-a-decider, 23/11/2026)

Lecture seule sur `vff` (aucune écriture). Sources : API Wikipédia (articles de gares et de lignes à des révisions citées, « Histoire de Nogent-le-Rotrou »), User-Agent du projet, 5 s entre deux requêtes ; Gallica, archeoferroviaire et robots.txt non touchés. Liste et verdict de chaque cas : `storage/app/reports/agents/aprem-2111/gares-a-decider/verifiees.csv` (colonnes partie, station_id, gare, date_vff, verdict, motif).

Règle stricte appliquée : une date qui change l'existant demande la chronologie détaillée de l'article de la ligne (section qui contient la gare) ou deux sources concordantes ; une infobox seule ne contredit pas la section de la ligne ; une inauguration n'est pas une ouverture au public ; une halte créée après la ligne a sa propre date sourcée ; une date de gare antérieure à la mise en service de sa ligne est fausse et reçoit la date de la section.

## Résultat sur les 131 ouvertures encore « à décider » des passes 2 à 7 (liste fusionnée par gare)
- 21 corrigées (section 48 de `database/data/gares_corrections.php`).
- 8 confirmées avec la raison écrite.
- 13 déjà traitées par une section antérieure (nom présent dans le fichier).
- 89 toujours indécidables, avec le motif dans le CSV ; le détail par famille est ci-dessous.
Sur les 101 « à décider » comptés à la fin de la passe 7 : 21 corrigées et 8 confirmées, il en reste 72.

## 21 ouvertures corrigées
- Pontoise - Gisors, 4 octobre 1868 (et non 10, d'OpenHistoricalMap) : Boissy-l'Aillerie, Montgeroult-Courcelles, Santeuil-Le Perchay, Chars, Lavilletertre, Liancourt-Saint-Pierre, Chaumont-en-Vexin, Trie-Château. Chronologie de la ligne de Saint-Denis à Dieppe (« La ligne atteint Gisors le 4 octobre 1868 »), article de Gisors au même jour. Trie-Château : le récit parle d'une halte « prévue », confiance moyenne.
- Le Theil-La Rouge et Champagné, 1 juin 1854 : section de Chartres au Mans (articles de Saint-Mars-la-Brière, La Ferté-Bernard, Sceaux, Le Mans, Histoire de Nogent-le-Rotrou). Les articles des deux gares disent « mai 1857 » (recopie de l'ouverture jusqu'à Rennes).
- Logelbach 3 décembre 1868 (section Colmar - Munster) ; Breitenbach et Muhlbach-sur-Munster 1 novembre 1893 (prolongement de Munster à Metzeral), contre l'année 1868 de leurs articles et de Wikidata.
- Rang-du-Fliers-Verton 21 décembre 1847 (Abbeville - Neufchâtel).
- Longueil-Sainte-Marie 20 octobre 1847 (Creil - Compiègne ; 1882 de Wikidata est la ligne d'Ormoy-Villers à Boves).
- Pontarlier 24 juillet 1860 (chronologie de la ligne de Frasne à Verrières-de-Joux).
- Avesnes 30 octobre 1869 (Aulnoye - Hirson, et non 1872).
- Alès 19 août 1840 (Alès - Nîmes) ; Grand'Combe-La Pise janvier 1841 (Alès - La Levade, et non 1840).
- Cap-d'Ail 1881 (gare construite pour le PLM ; le 1866 de Wikidata précède la ligne, ouverte le 9 octobre 1868) ; Èze 9 octobre 1868 (1866 avant la ligne).

## 8 confirmées
Le Pallet, La Haie-Fouassière, Montaigu-Vendée : 26/12/1866 gardé, car l'article de la ligne des Sables-d'Olonne à Tours dit que le PO « ouvre » Nantes - La Roche-sur-Yon le 26 décembre et celui de Nantes à Saintes que le 30 décembre est l'inauguration. La Brohinière : 1884 (« livre au service en 1884 », le 07/09/1863 est une inauguration). Neufchâteau : « construite en 1866 » est une construction, la date de la section est gardée. Évron : 1855 (Wikidata et article de Laval). Condé-sur-Huisne : 1854 (Chartres - Nogent le 16/02/1854, Wikidata 1854). Saint-Pol-sur-Ternoise : 22/11/1875 gardé, le 21/11/1875 est l'inauguration.
Fermetures confirmées : Hotot-en-Auge (05/10/1947, la ligne dit que le trafic voyageurs cesse ce jour après la reprise de 1945) et Hielle (28/05/1989, fin de l'exploitation de Remiremont à Bussang).

## Toujours indécidables, par famille
- Chronologie de la ligne sans la section ou sans date : Hesdin, Beaurainville, Auchy-lès-Hesdin, Anvin (Saint-Pol - Étaples), Lillers, Isbergues, Don-Sainghin, Azay-le-Rideau, Rivarennes, Massiac, Murat, Le Lioran, Arc-et-Senans, Poligny, Peyrehorade, Orthez, Boën, Rognac, L'Estaque, Reims-Maison-Blanche.
- Halte postérieure à la ligne sans date : Compans (entre 1891 et 1907), Renescure (après 1889), Saint-Étienne-du-Rouvray (après 1876), Sainte-Colombe-Septveilles (entre 1858 et 1868), Montaudran, Labège-Innopole, Colomiers-Lycée-International, Cros-de-Cagnes, Villeneuve-Loubet-Plage, Cannes-la-Bocca, Monte-Carlo-Country-Club, Anthéor-Cap-Roux (une seule phrase sans source : 15/05/1933). Pour les quatre premières, la date de la base est démontrée fausse (1861, 1848, 1843...) mais aucune date ne la remplace : à neutraliser par une règle plutôt qu'à remplacer (une précision « avant » n'est pas lue pour les ouvertures).
- Deux sources de même rang qui se contredisent : Noailles (Corrèze) 1891 contre 1893, Brignais (1er juin ou 1er septembre 1910 selon deux articles), Marseille-Blancarde (l'article de la ligne dit le 20 et le 25 octobre 1858), La Loupe et Amilly-Ouerray (Wikidata 1852 contre le 16/02/1854 d'un article d'histoire locale, terminus temporaire à La Loupe).
- Choix du site, pas de la date : Nîmes, Montpellier, Boulogne-Ville, Saint-Nazaire, Reims, Villennes-sur-Seine, Moret-Veneux-les-Sablons, Saincaize ; fiches de tram-train (Lisière Pereire, Noisy-le-Roi TT, Fourqueux-Bel Air : la ligne 13 ouvre le 06/07/2022, mais quelle fiche porte quel service reste à décider).
- Articles de réseau ou fiches à classer par une règle (Chemins de fer du Calvados, houillères de Ronchamp).
- Fermetures : les 52 cas de `audit-fermetures` et `audit-fermetures-2` restent à l'agent `audit-fermetures-3` (arbre en cours), pour ne pas écrire deux corrections de la même gare.

Cas à signaler au coordinateur : la fiche d'Étaples porte le 21/11/1847 là où la ligne de Longueau à Boulogne-Ville donne le 21/12/1847 pour Abbeville - Neufchâtel (même coquille que Rang-du-Fliers) ; la fiche n'était pas dans la liste, non touchée.

## Totaux cumulés de l'audit des dates d'ouverture (754 gares relues, puis les cas à décider)
225 corrigées (204 + 21), 457 confirmées (449 + 8), 72 à décider (101 - 29).

## Contrôles
`StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `StationCorrectionsConsistencyTest`, `TypographicDashTest` et le nouveau `StationOpeningsToDecideCorrectionsTest` (échoue sans la section 48) : verts sur la base `vff_jour_gares_a_decider_test` ; chaque citation vérifiée dans le texte de la révision citée avant écriture ; Pint propre ; aucun tiret long.

## Application (non lancée, coordinateur)
```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-gares-a-decider.dump
bin/php artisan vff:build-station-eras --parallel=2
```
21 gares changent d'événement d'ouverture et de `opened_at`. Une seule reconstruction suffit avec les sections 36 à 47 si elles ne sont pas encore appliquées.
