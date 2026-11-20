# Gares : fiche gare et gares des lignes (à faire)

Ouvert le 2026-10-19 (22 h 50) à la demande de l'utilisateur (« y'a aussi un à faire gares »),
à compléter avec ses constats. Voir aussi `docs/A-FAIRE-FICHE-LIGNE.md`. La partie « frise » (date de
fermeture identique sur la carte, la fiche gare et la vue par décennie de la fiche ligne) est faite :
voir « Après le chantier gares » et « 2026-11-19 » plus bas ; le reste concerne les données.

## Constats déjà connus

- **Rattachement gares-lignes incomplet** : 434000 Briouze - La Ferté-Macé n'a que ses 2 terminus
  dans `station_lines`, alors que `line_stops` compte 26 arrêts (toutes ères). L'en-tête de la fiche
  ligne affiche « 0 stations ». À mesurer sur toutes les lignes (gares intermédiaires manquantes).
- **Fermetures incohérentes** (fait) : la vue « Decade by decade » des fiches lignes plaçait mal la
  fermeture des gares. Une seule source de vérité (`station_eras` et `stations.closed_at`), utilisée
  partout : `App\Services\LineStations` (fiche ligne : liste, carte, décennies), `StationSheet` (fiche
  gare), `GeoExporter::stationsAsGeoJson` (carte), même libellé (`resources/js/lib/stationDates.js`).
- **Dates manquantes** (rapport `storage/app/reports/agents/fermeture-gares.md`) : 4 467 gares fermées,
  dont 2 488 avec une date (941 de source, le reste déduit des lignes) et **1 979 sans aucune date**.
  Les gares sans date restent « incertaines » après leur dernière preuve.
- **Gares incertaines en 2030** : 18, surtout des parcs (à vérifier une par une).

## Règles à respecter

- Une gare est fermée si toutes ses lignes sont fermées à l'ère, **mais aussi dès qu'elle n'est plus
  exploitée**, même si ses lignes restent ouvertes (arrêt supprimé sur une ligne en service). Rappel de
  l'utilisateur, 2026-10-19 : « une gare est aussi fermée si juste elle n'est plus exploitée ». À vérifier
  dans `StationTimeline` / `vff:build-station-eras` : une gare sur une ligne en service ne doit pas
  rester « active » sans desserte (sources : SNCF gares voyageurs, fermetures datées, liste-des-gares).
- Une gare fermée sur une ligne déposée ou disparue n'est plus affichée sur la carte, sauf si elle est
  sur une ligne exploitée ; la fiche ligne garde toutes ses gares.
- Les dates de fermeture doivent être justes dans la frise : vraies dates jj/mm/aaaa, source citée.

## Constats de l'utilisateur

- **La frise de fermeture des gares « déconne »** (2026-10-19, 22 h 55), encore après le chantier
  fermeture-gares (terminé à 21 h 30). Déjà signalé plus tôt avec une capture : dates de fermeture
  fausses dans la frise des cartes. À reprendre de bout en bout : la date affichée dans la frise de
  la carte, sur la fiche gare et dans la vue par décennie de la fiche ligne doit être la même,
  sourcée, et respecter la règle « plus exploitée = fermée ». Construire un jeu de gares témoins
  (dates connues et sourcées) et un test qui compare les trois affichages. Fait : 21 témoins
  (`database/data/gares_temoins.json`), test `tests/Feature/StationWitnessesTest.php`.

## Après le chantier gares (2026-10-20, ~1 h, rapport `storage/app/reports/agents/gares-fiche.md`)

Fait : une seule date de fermeture (21 témoins, 0 écart), « plus exploitée = fermée », rattachement des gares
de bifurcation (11 033 rattachements, 434000 : 3 gares). Reste :
- **Gares absentes des données** : 145 lignes de 10 km ou plus sans aucune gare (3 550 km), 230 avec moins d'une
  gare pour 20 km. Piste : importer les gares des schémas de ligne des articles Wikipédia en cache.
- **Ligne manquante** : 433000 Couterne - La Ferté-Macé n'existe pas dans `lines`.
- **À relire** : 18 gares incertaines en 2030, 10 conflits de dates de fermeture (`provenance.closed_conflict`).
- **Cas douteux** (gardées ouvertes) :
  - réouverture de ligne déduite alors qu'OSM et Wikidata les disent désaffectées : Docelles-Cheniménil, Lépanges,
    Laveline-devant-Bruyères, Biffontaine, Corcieux-Vanémont, Nice-St-Roch, Mauzens-Miremont ;
  - présentes dans gares-de-voyageurs malgré une fermeture Wikidata récente : Pont-de-Claix, Vincelles,
    Nice-St-Augustin, Loriol ;
  - sans preuve de fermeture : Grasse et les haltes du tram-train de Mulhouse.

  À cela s'ajoutent 27 gares fret seul dont la « fermeture » Wikidata est la fin du service voyageurs.
- **Invariant `stations.active_without_track`** (92 cas, tous à des ères passées) : tracés anciens décalés de 150 à
  300 m ou positions imprécises. Proposition : rayon selon la précision de la position.
- **Après la reconstruction nationale de la phase 2 des jonctions** : relancer `vff:build-station-eras`.

## 2026-11-19 (agent « fiche-ligne », rapport `storage/app/reports/agents/soir/fiche-ligne.md`)

Fait (affichage seulement, aucune donnée modifiée) :
- Vue par décennie de la fiche ligne : une gare comptée fermée sans date retenue (fermeture non datée,
  ou fermée puis rouverte : 157 gares) est maintenant nommée dans « Gares fermées entre-temps », avec
  l'intervalle de sa frise (« entre 1931 et 1940, rouverte ensuite ») : `LineStations::closureEntries`,
  `resources/js/Components/Lines/LineDecades.svelte`.
- Constat mesuré sur toute la base : 3 152 gares fermées sur 3 171 ont leur date retenue dans
  l'intervalle où leur frise passe à « fermée ».

Reste (données) :
- 19 gares dont la date retenue tombe hors de cet intervalle : 3 datées après (Vigny, Saint-Samson,
  Le Crouais : la frise les montre fermées puis rouvertes) et 16 avant (Cabessut, Conduche et quatre
  autres gares de la vallée du Lot au 9 juin 2011, Bruay-en-Artois et trois voisines au 21 octobre 2018,
  Fienvillers-Candas, Tournon-St-Martin, Lissac, Villebois, Le Rouquet, Chambray-Fontaine-sous-Jouy). Les trois affichages montrent la même
  chose, mais la frise et la date se contredisent ; à reprendre par `vff:build-station-eras`.
- La ligne 433000 Couterne - La Ferté-Macé existe maintenant dans `lines` (`rfn-433000`).
- Gares incertaines à l'ère courante : 0 au 19/11 (18 le 20/10).
