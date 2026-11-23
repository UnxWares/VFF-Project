# Audit des grandes gares, sixième passe (audit-grandes-gares, 23/11/2026)

Lecture seule sur `vff` (aucune écriture). Sources : API Wikipédia (article de la gare puis article de la ligne, révisions citées) et API Wikidata (titres), requêtes par lots, 5 s entre deux, User-Agent du projet ; Gallica et sites protégés non utilisés.

## Liste exacte des gares relues

Filtre (même périmètre que les passes précédentes) : gares `active` de type `gare`, maximum de `footfall` depuis 2019 de 0,15 M et plus, dont l'ouverture n'est ni une correction sourcée (`provenance.opened_at = correction`) ni une date au jour de Wikidata, et qui ne figurent dans aucun des cinq `verifiees.csv` précédents (422 gares). Résultat : 64 gares, dont Nice-Saint-Augustin et Petit-Jouy-Les Loges déjà corrigées dans le fichier (écartées) : **62 gares relues**, des plus fréquentées aux moins fréquentées (Paris-Est 53,9 M, Magenta, Nantes, Toulouse-Matabiau, Bondy, Nice-Ville, Poissy, Lyon-Perrache, Chelles-Gournay, Cergy-le-Haut, Saint-Étienne-Châteaucreux ... jusqu'à Fleurieux-sur-l'Arbresle 0,15 M). Liste et verdict de chacune : `storage/app/reports/agents/aprem-2111/audit-grandes-gares/verifiees.csv` (62 lignes).

## Résultat

- 14 ouvertures corrigées (section 42) : Nantes 25/08/1851 (gare provisoire, les voyageurs de la ligne d'Angers ; le 18/09/1989 d'OSM est le nouveau bâtiment), Toulouse-Matabiau 30/08/1856 (et non le 16/10, inauguration), Lyon-Perrache 10/11/1856, Saint-Étienne-Châteaucreux 14/12/1857, Viroflay-Rive-Gauche 10/09/1840 (le 27/11/1979 est la reconstruction), Montereau 12/08/1849 (et non 1888), Bellegarde 18/03/1858 (et non 21/05/2010, pôle multimodal), Thiviers 26/08/1861 (et non 1892 : gare d'origine de Limoges - Périgueux), Chauny 21/10/1849, Rumilly 05/07/1866, Grand-Lemps 24/12/1860, Livry-sur-Seine 01/06/1897, Saint-Sulpice-Izon 20/09/1852, Stains-La Cerisaie 01/07/2017 (mois précisé au jour).
- 42 confirmées (Paris-Est 05/07/1849, Magenta 14/07/1999, Bondy, Nice-Ville 18/10/1864, Poissy 09/05/1843, Chelles, Cergy-le-Haut, Mâcon, Vichy, Tourcoing 14/11/1842 première gare, etc.).
- 6 à décider : Marseille-Blancarde (20 ou 25/10/1858 dans l'article de la ligne), Alès (1840 ou 1841), La Fère, Cap-d'Ail (1866 non confirmé, section de 1868), Veauche - Saint-Galmier (1832 non confirmé), Remise à Jorelle (site technique, pas une gare voyageurs).
- Règle du déménagement appliquée : Nantes, Lyon-Perrache, Tourcoing (première gare de 1842 gardée), Vitrolles-Aéroport gardé à 2008 (l'ancienne gare de Vitrolles est une fiche distincte, fermée).
- Les très grandes gares parisiennes (Paris-Est, Bondy, Esbly, Chelles, Poissy, Montsoult) ont une ouverture déjà exacte (embarcadère de 1849 ou 1843 ; les reconstructions ne changent pas la date de la première gare).

## Totaux cumulés de l'audit des dates d'ouverture (484 gares relues)

154 corrigées (24 section 36, 26 section 37, 20 section 39, 28 section 40, 42 section 41, 14 section 42), 271 confirmées, 59 à décider (les 53 précédentes et les 6 ci-dessus).

## Contrôles

`StationCorrections*`, `SourcedCorrectionsInvariantsTest`, `StationCorrectionsConsistencyTest`, `TypographicDashTest` : 84 tests verts (base `vff_jour_audit_grandes_gares_test`) ; Pint propre ; aucun tiret long. Les tests existants valident chaque entrée de la section (identification de la fiche, date, précision, source, citation) ; il n'y a pas de test par gare dans les sections précédentes.

## Application (non lancée, coordinateur)

```
podman exec vff-db pg_dump -U vff -Fc -t station_events -t station_eras vff > <dossier-dumps>/avant-audit-grandes-gares.dump
bin/php artisan vff:build-station-eras --parallel=2
```
14 gares changent d'événement d'ouverture et de `opened_at`. À lancer avec les sections 36 à 41 si elles ne sont pas encore appliquées : une seule reconstruction suffit.
