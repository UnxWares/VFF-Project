# Audit des gares, huitième passe (audit-gares-7, 23/11/2026)

Lecture seule sur `vff`. Sources : API Wikipédia (article de la gare, article de la ligne et de la ligne citée par l'événement de ligne de VFF, révisions citées) et API Wikidata (titres), 5 s entre deux requêtes, User-Agent du projet ; Gallica non utilisé. Liste et verdict : `storage/app/reports/agents/aprem-2111/audit-gares-7/verifiees.csv` (247 lignes, colonne `partie` : `relecture` ou `nouvelle`).

Leçon appliquée (Meroux, e364bf26) : une date qui change l'existant repose sur la chronologie détaillée de l'article de la ligne ou sur deux sources concordantes ; une infobox seule ne contredit jamais la date de la section de la ligne ; une inauguration n'est pas une ouverture au public (Besançon-Mouillère et Gilley gardent 1884, la ligne du Locle n'étant que « inaugurée » le 04/08/1884).

## 1. Relecture des 127 « confirmées » de la passe 6
Les 99 dont l'article de la gare ne donnait pas de date ont été recoupées à la main avec le texte de la ligne (chronologies par section) ; les 28 dont l'article donnait la même année sont gardées.
- 11 corrigées : Gannat 07/05/1855 (et non 1871), Brassac-les-Mines 03/09/1855 (terminus d'Issoire - Brassac), Estrées-Saint-Denis 12/05/1880, Vallorcine 01/07/1908 (et non 1901, Le Fayet - Chamonix), La Crau 06/12/1875, Vayres et Bassens 20/09/1852, Arches 10/11/1864, Brebières-Sud 01/04/1846, Marseille-en-Beauvaisis 01/07/1875, Port-Brillet 05/1857 (Laval - Rennes, et non la fourchette 1860).
- 8 passées à décider : Parent-Coudes-Champeix (1856 de Wikidata contre la section de 1855), Collonges-Fontaines, Arc-et-Senans, Azay-le-Rideau et Saint-Sébastien-Pas-Enchantés (fourchettes sans date de section), Gièvres (1902 contre 1869), Poligny (VFF 1884 est l'inauguration de la ligne de Dole, la gare existait déjà), Hesdin (1866 d'OpenHistoricalMap antérieur à la concession de 1869 de la ligne d'Arras à Étaples).
- 108 confirmées.

## 2. 120 gares de plus (de Santes 0,048 M à Le Trayas 0,018 M)
Même périmètre que les passes précédentes (gares actives, hors corrections sourcées et dates au jour de Wikidata, hors les 634 déjà relues), dans l'ordre de fréquentation après Chevrières.
- 19 corrigées : Monestier-de-Clermont 27/07/1878 (Vif - Veynes, et non 1876), Régny 1866 (et non 1833), Saint-Junien 26/04/1875 (et non 1911), Selles-sur-Cher 18/10/1869, Saint-Aigulin 20/09/1852, Lauterbourg 25/07/1876, Ascq 01/12/1865, Corbehem et Pont-de-la-Deûle 01/04/1846, Sainte-Anne 26/09/1862, Argentière 25/07/1906, Oderen et Fellering 02/01/1905 (Wesserling - Kruth, et non 1863), Corps-Nuds, Vern et Saint-Armel 28/12/1881, Casino-Lacroix-Laval 1997 (halte créée à la demande du casino), Olonne-sur-Mer 30/12/1866, Longuerue-Vieux-Manoir 1874 (gare construite sept ans après la ligne).
- 31 à décider (motif dans le CSV) : Logelbach, Breitenbach et Muhlbach (1868 de la section Colmar - Munster, ou 1893 au-delà de Munster), Grand'Combe-La Pise (1840 ou 1841), Compans (halte annoncée en 1891), Renescure (halte demandée dès 1873, après 1889), Saint-Étienne-du-Rouvray (créée par décret de 1876), Longueil-Sainte-Marie, Boën, Peyrehorade, Rivarennes, Massiac, Murat, Le Lioran, Anvin, Beaurainville et Auchy-lès-Hesdin (comme Hesdin), Amilly-Ouerray, Condé-sur-Huisne, Champagné et Le Theil-La Rouge (article : mai 1857 pour toute la ligne de Chartres à Rennes), La Brohinière, Monte-Carlo-Country-Club, Pontcharra-Saint-Forgeux, L'Hôpital-du-Grosbois, Anthéor-Cap-Roux (ouverte le 15/05/1933 selon l'article, une seule source), Santeuil-Le Perchay, Trie-Château, Montgeroult-Courcelles, Liancourt-Saint-Pierre et Lavilletertre (04 ou 10/10/1868).
- 70 confirmées.

## Totaux cumulés de l'audit des dates d'ouverture (754 gares relues)
204 corrigées (174 + 11 + 19), 449 confirmées, 101 à décider.

Note : la ROADMAP citait Meroux au 08/12/2018 (passe 6) ; la correction a été retirée (e364bf26), la mention est retirée du texte.

## Contrôles
`StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `StationCorrectionsConsistencyTest`, `TypographicDashTest` : 86 tests verts (base `vff_jour_audit_gares_7_test`) ; chaque citation vérifiée dans le texte de la révision citée avant écriture ; Pint propre ; aucun tiret long.

## Application (non lancée, coordinateur)
```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-audit-gares-7.dump
bin/php artisan vff:build-station-eras --parallel=2
```
30 gares changent d'événement d'ouverture et de `opened_at`. Une seule reconstruction suffit avec les sections 36 à 46 si elles ne sont pas encore appliquées.
