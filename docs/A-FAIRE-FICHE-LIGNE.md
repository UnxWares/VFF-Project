# Fiche ligne : refonte et régressions (à faire)

Demandé par l'utilisateur le 2026-10-19 (22 h 45). Exemples cités : 434000 Briouze - La Ferté-Macé,
LGV Sud-Est (752000). État des lieux du 2026-11-19 au soir (agent « fiche-ligne », rapport
`storage/app/reports/agents/soir/fiche-ligne.md`) : presque tout est fait ; ce qui reste est en fin de
chaque point et dans la section « Reste à faire ».

## 1. Carte de la fiche : la ligne entière, puis toutes ses phases (fait)

- **Vue par défaut** : la ligne entière à sa plus grande longueur en service (phase « au meilleur »,
  `App\Services\LinePhases::compute`, tolérance `BEST_TOLERANCE`). Fait : pour 434000, la carte s'ouvre
  sur la mise en service du 6 décembre 1869 (13,9 km), plus sur le bout en pointillés de Bagnoles.
  Fichiers : `resources/js/Components/Lines/LinePhaseMap.svelte`, `resources/js/lib/linePhases.js`
  (`bestPhaseIndex`).
- **Sélecteur de phases** à côté de la carte : une phase par date où le fer ou le trafic change, tirée
  des vraies dates de `line_events` et des portions qu'elles touchent (`App\Services\LinePhases`,
  `resources/js/Components/Lines/LinePhaseSelector.svelte`, flèches du clavier, bouton « Longueur
  maximale »). Fait.
- **Chaque phase montre ce qui a changé** (portion, date, source en note) et l'état de toute la ligne
  à cette date (carte recolorée, légende en km par catégorie) :
  `resources/js/Components/Lines/LinePhaseText.svelte`. Fait.
- Tests : `tests/Unit/LinePhasesTest.php`, `tests/Feature/LineSheetPageTest.php`,
  `tests/js/linePhases.test.js` (dont le rendu du sélecteur).

## 2. « Décennie par décennie » (fait)

- Source de vérité commune avec la fiche gare et la carte : `App\Services\LineStations` (états de
  `station_eras`, date retenue `stations.closed_at` / `closed_precision` / `provenance.closed_after`
  écrite par `vff:build-station-eras`), même libellé partout (`resources/js/lib/stationDates.js`,
  `formatStationDate`). Jeu de 21 gares témoins et test des trois affichages :
  `database/data/gares_temoins.json`, `tests/Feature/StationWitnessesTest.php` (chantier gares du
  2026-10-20).
- Colonnes « Au 31 décembre » et « Gares fermées entre-temps » : une fermeture du 26 mai 1991 est
  listée à la ligne 2000 (la gare est encore ouverte au 31/12/1990), comme sur sa fiche gare.
- 2026-11-19 : une gare comptée fermée sans date retenue (fermeture non datée, ou fermée puis rouverte,
  157 gares dans les données) n'était pas nommée ; elle l'est, avec l'intervalle de la frise
  (« entre 1931 et 1940, rouverte ensuite ») : `LineStations::closureEntries`,
  `resources/js/Components/Lines/LineDecades.svelte`, tests `tests/Unit/LineStationsClosureEntriesTest.php`,
  `tests/js/lineDecades.test.js`.
- 2026-11-19 : légende du ruban de composition sous le tableau, et sa composition lue par les lecteurs
  d'écran (la couleur ne porte plus seule le type de trafic ; question Q1, « une légende »).
- Gardée plutôt que remplacée : chaque ligne du tableau ouvre la carte sur la phase en vigueur
  (bouton « Carte »).
- Reste (données, pas l'affichage) : 19 gares dont la date retenue tombe hors de l'intervalle où leur
  frise passe à « fermée » (ex. Cabessut, Conduche, Saint-Martin-Labouval : date retenue du 9 juin 2011,
  mais « fret seul » jusqu'en 2020 dans la frise ; Vigny, Le Crouais, Saint-Samson : la frise les montre
  fermées puis rouvertes avant la date retenue). La fiche ligne et la fiche gare affichent la même chose ;
  liste dans le rapport du 19/11, à reprendre par `vff:build-station-eras`.

## 3. LGV : noms historiques et étapes (fait)

- **Noms** : restaurés et durables. Cause : ils vivaient dans `line_sections`, remplie par un seeder
  hors pipeline jamais rejoué après la recréation de la base, et la fiche lisait `alt_names` comme une
  liste alors que c'est un objet. Corrigé par `vff:import-line-names`
  (`app/Console/Commands/ImportLineNames.php`, étape de `vff:rebuild-history`) et les noms curés de
  `database/data/lignes_noms_historiques.php`. La LGV Sud-Est affiche « LGV Sud-Est », « LGV Paris -
  Lyon », « LN1 » (Wikipédia, depuis 1981) et ses sections LGV Rhône-Alpes (LN4), LGV Méditerranée
  (LN5).
- **Étapes** : chaque section nommée liste ses mises en service datées (2026-11-19) : LGV Sud-Est le
  27 septembre 1981 (263,9 km) puis le 25 septembre 1983 (118,7 km), LGV Rhône-Alpes le 13 décembre
  1992 puis le 3 juillet 1994, LGV Méditerranée le 10 juin 2001 ; chaque date avec ses notes de source
  et un bouton qui ouvre la carte sur la phase. Règle : `LineSheet::sectionStages` (phases datées des
  années d'ouverture de la section ; rien de deviné si deux sections partagent l'année ou si la phase est
  plus longue que la section). Aussi dans l'API (`sections[].stages`, REST et GraphQL `LineSectionStage`).
  Tests : `tests/Unit/LineSheetSectionStagesTest.php`, `tests/Feature/LineSheetPageTest.php`.
- Reste : la LGV Est européenne n'a qu'une date pour ses 409 km (2007, événement « toute la ligne ») :
  sa seconde phase (2016) n'a pas d'étape tant qu'un événement localisé ne la date pas. Seules trois
  lignes ont des sections nommées (752000, 431000, 005000).

## 4. Frise (timeline) de la ligne (fait)

- L'ancienne frise est remplacée par un récit par phases (« Histoire de la ligne », une entrée par
  phase avec « Voir sur la carte ») et par la « Chronologie détaillée, portion par portion »
  (`resources/js/Components/Lines/LineChronicle.svelte`, `App\Services\LineChronicle`) : événements
  regroupés par portion, citation de la source, notes numérotées, rôle de chaque événement pour la carte
  (« Date la carte », « Contexte », « Autre ligne »…), désaccords entre sources signalés.

## 5. Régressions (des informations avaient disparu)

| Élément | Constat (2026-10-19) | État au 2026-11-19 |
|---|---|---|
| « À propos » | 15 lignes sur 2 707 avec une `description` | 878 lignes sur 2 705, dont 862 avec l'introduction Wikipédia attribuée (CC BY-SA 4.0, version citée) : `vff:import-line-descriptions` |
| Signalisation | `line_signalling` vide | 1 373 tranches sur 612 lignes ; `vff:import-sncf-signalling` est une étape de `vff:rebuild-history` ; historique daté lu dans Wikipédia (`LineSignallingHistory`) |
| Liste des gares | 2 terminus, « 0 stations » dans l'en-tête | La fiche liste toutes les gares rattachées (`LineStations`), fermées comprises, et l'en-tête les compte. 434000 n'a encore que 3 gares rattachées (Briouze, Lonlay-le-Tesson, La Ferté-Macé) : le rattachement des haltes intermédiaires est un travail de données (agent « gares-recalcul », 19/11) |
| Noms historiques des LGV | Disparus | Restaurés (point 3) |

## 6. Règles à respecter

- La fiche ligne garde toutes ses gares, même fermées ou sur une portion déposée.
- Vraies dates jj/mm/aaaa, portion par portion, source citée pour chaque information.
- Pas de tiret long dans les textes affichés ; couleurs : LGV violet, mixte orange, fret
  bleu, patrimoine vert.
- Beaucoup de tests (feature Inertia, Vitest pour le sélecteur de phases).

## Reste à faire

- Données des gares : haltes intermédiaires manquantes (434000), 19 dates retenues hors de leur
  intervalle de frise (point 2).
- LGV Est européenne : dater la seconde phase (2016) par un événement localisé (point 3).
- Mise en page de « À propos » : sur ordinateur, la fiche reste un peu plus haute que le texte pour les
  lignes très documentées (LGV Sud-Est : 184 px de plus à 1 280 px, 31 px à 1 920 px) ; la supprimer
  tout à fait demande de changer l'ordre des sections (question Q4), ce qui n'a pas été fait. Jusqu'à
  1 180 px, la fiche passe sous le texte, en deux colonnes : plus de vide (901 px avant, à 1 024 px).
