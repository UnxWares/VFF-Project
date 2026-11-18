# Fiche ligne : refonte et régressions (à faire)

Demandé par l'utilisateur le 2026-10-19 (22 h 45), à traiter après la passe finale. Rien
n'est commencé. Exemples cités : 434000 Briouze - La Ferté-Macé, LGV Sud-Est (752000).

## 1. Carte de la fiche : la ligne entière, puis toutes ses phases

- **Vue par défaut** : la ligne dans son intégralité, au moment où elle était à son
  maximum (plus grande longueur en service). Aujourd'hui, la mini-carte montre l'état
  actuel : pour 434000, seul un petit bout vert en pointillés près de Bagnoles reste
  visible, ce qui « n'a aucun intérêt ».
- **Sélecteur de phases dynamique** à côté de la carte, comme le curseur des années de la
  carte principale, mais propre à la ligne. Une phase = chaque date où **le fer** change
  (ouverture d'une section, fermeture, dépose, réouverture, patrimoine) ou **le trafic**
  change (mixte → fret, fret → fermé, voyageurs saisonniers…). Les phases sont tirées
  des vraies dates de `line_events` (jj/mm/aaaa) et des portions qu'elles touchent, pas
  des décennies.
- Chaque phase montre ce qui a changé (portion, date, source) et l'état de toute la ligne
  à cette date.

## 2. « Decade by decade » : nombreux bugs

- Surtout les gares : leur fermeture y est mal placée, alors que leur fiche gare est
  juste. Comparer avec `station_eras` et la frise de la fiche gare, et corriger la source
  de vérité commune.
- À reprendre ou à remplacer par le sélecteur de phases (point 1).

## 3. LGV : noms historiques et étapes

- Les noms successifs ont disparu. Pour la LGV Sud-Est, `lines.alt_names` ne contient
  plus que `{"wikidata": "Q585219"}` : il manque « LGV Paris - Lyon », « LN1 », etc.
  Retrouver d'où venaient ces noms et pourquoi ils ont été écrasés (fusion, réimport).
- Montrer les étapes : d'abord Paris - Lyon (mises en service par section en 1981 et
  1983), puis les autres parties, avec leurs dates et leurs noms.

## 4. Frise (timeline) de la ligne : à repenser

- « Beaucoup d'infos, pas bien mises en valeur, pas faciles à lire ni à comprendre, des
  données sans queue ni tête, pas digne d'une encyclopédie. »
- Piste de l'utilisateur : supprimer la frise actuelle et exposer les données autrement.
  Par exemple : un récit par phases (point 1), les événements regroupés par portion,
  les sources en note, les contradictions entre sources signalées clairement.

## 5. Régressions (des informations ont disparu)

| Élément | Constat (2026-10-19) | Cause probable |
|---|---|---|
| « About this line » | « No description available » ; 15 lignes sur 2 707 ont une `description` | Jamais rempli en masse, ou rempli autrefois à l'affichage. À sourcer (introduction Wikipédia avec attribution CC BY-SA, ou résumé généré à partir des données) |
| Signalisation (bloc « Engineering & signalling ») | Table `line_signalling` **vide** (0 ligne) ; `line_segments.signalling` vide en 2020 et 2030 | `vff:import-sncf-signalling` ne fait pas partie de `vff:rebuild-history`, et les reconstructions l'ont effacée. Relancé dans la passe de nuit pour 2030 ; à ajouter durablement au pipeline |
| Liste des gares desservies | « Main stations » ne montre que les 2 terminus pour 434000 ; « 0 stations » dans l'en-tête | `station_lines` n'a que 2 gares pour 434000, alors que `line_stops` en a 26 (toutes ères). La fiche doit lister toutes les gares de la ligne (règle : la fiche garde toutes ses gares), avec leurs dates |
| Noms historiques des LGV | Voir le point 3 | |

## 6. Règles à respecter

- La fiche ligne garde toutes ses gares, même fermées ou sur une portion déposée.
- Vraies dates jj/mm/aaaa, portion par portion, source citée pour chaque information.
- Pas de tiret long dans les textes affichés ; couleurs : LGV violet, mixte orange, fret
  bleu, patrimoine vert.
- Beaucoup de tests (feature Inertia, Vitest pour le sélecteur de phases).
