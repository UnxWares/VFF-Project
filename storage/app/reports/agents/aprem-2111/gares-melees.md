# Gares mêlées à une station de tramway ou de métro (23/11/2026)

## Méthode (lectures seules sur vff, aucune écriture)
- Les 5 114 éléments Wikidata de `station_sources` relus un à un (API wbgetentities, User-Agent du projet, 5 s entre deux lots) : classes P31, lignes P81, descriptions, exploitant ; croisés avec la fiche rattachée (dates, provenance, distance du rattachement).
- Objets OSM et OHM : l'import ne garde que `railway=station|halt` ; aucun `tram_stop`, `subway` ou `bus_station` seul. Les objets OSM étiquetés `tram=yes` (Cité Universitaire, Gennevilliers, Strasbourg, Épinay-Villetaneuse, Le Fayet) sont des gares réellement desservies par un tramway : laissés.
- Fiches dont le nom évoque tram, TT, métro, funiculaire : 28 relues.

## Cas établis (section 53 de gares_corrections.php)
1. Meyzieu (20419, UIC 87729350) : Q28495871 est la station du tramway T3 (ouverture 05/12/2006, UIC 8779235, image « La Soie tram 3.jpg », à 275 m). unlink de Q28495871 sur la gare du CFEL, puis `kind excluded` sur l'élément détaché (il devient sa propre fiche). Effet : plus d'ouverture 2006 après la fermeture voyageurs du 31/12/1947 ; UIC, sources SNCF et position SNCF intactes.
2. Les Côteaux (19101, UIC 87382515) : ouverture du 02/07/1997 (Wikidata P1619) = tramway T2, après la fermeture de la gare le 21/05/1993. Même élément, même site : `neutralize` de cette seule ouverture (lien, UIC, médias gardés). L'ouverture ferroviaire reste à sourcer (article « Gare des Coteaux »).
3. Porte de Vincennes (21117) : fiche née du seul élément Q76233926 « station du tramway d'Île-de-France » (T3a, T3b), sans UIC ; dates 1854 et 1934 déduites d'une ligne voisine : `kind excluded`.

## Douteux ou laissés (preuve)
- Fiches T13 Express (Allée Royale, Les Portes de St-Cyr, L'Étang-les Sablons, Lisière Pereire, Fourqueux-Bel Air, Noisy-le-Roi TT, Mareil-Marly TT, Bailly, St-Nom TT, Saint-Germain-en-Laye) : UIC SNCF, desservies par le tram-train sur voie ferrée : restent rattachées ; les ouvertures 1877 déduites de la ligne pour Allée Royale, Portes de St-Cyr, L'Étang-les Sablons, Lisière Pereire, Noisy-le-Roi TT, Fourqueux-Bel Air sont à vérifier (stations créées en 2022 ?), déjà dans gares-a-decider.
- Poissy, Poissy Gambetta, Poissy ZAC (21128, 21131, 21133) : seules sources Wikidata « future station de la ligne 13 Express », sans date : candidates à `kind projected` (non écrit : décision de Baptiste).
- T4 (Les Coquetiers, Lycée Henri Sellier, Gargan, L'Abbaye, Les Pavillons-sous-Bois, Freinville Sevran, Allée de la Tour Rendez-Vous), T11 (Stains-Cerisaie TT, Villetaneuse-Université TT, Épinay TT...), T12 (Massy Europe, Champlan, Chilly-Mazarin, Gravigny-Balizy, Parc du Château), tram-train de la Thur (Cernay, Thann, Vieux-Thann ZI, Thann-Centre, Thann-St-Jacques), Hoenheim-Tram, Esbly - Crécy : UIC SNCF, voies ferrées : laissées.
- Suresnes-Longchamp (19100) : même double nature que Les Côteaux, mais ouverture 1889 déjà corrigée : rien à faire.
- Trévoux (20379) : l'élément Q114571659 (473 m de la gare SNCF) est l'ancienne gare du Tramway de l'Ain (OSM « Ancienne gare de Trévoux (TA) » à 22 m de celle de la SNCF) : réseau secondaire historique de VFF, pas un tramway urbain ; position SNCF à 500 m des objets OSM : à trancher.
- Caen (OSM « Gare routière » à 59 m, w1284903801) : objet bus rattaché à la gare ; aucune action ne détache un objet OSM (unlink = Wikidata seulement) : à signaler.
- Viarmes (21601, tramway de Rennes TIV), tramways de la Mayenne, du Mont-Blanc, de la Corrèze : réseaux historiques ou à crémaillère du périmètre VFF : laissés.
- Noailles : déjà `excluded`.

## Application (coordinateur, après la fusion du commit)
1. `bin/php artisan vff:consolidate-stations --dry-run` puis réelle : rattachement (Q28495871 quitte Meyzieu et devient une fiche).
2. `bin/php artisan vff:build-station-eras` (corrections : exclusion de la fiche Meyzieu T3, de Porte de Vincennes, ouverture 1997 retirée), puis ères comme d'habitude.
Effet attendu : Meyzieu sans ouverture 2006 ; Les Côteaux sans ouverture 1997 ; deux fiches de tramway exclues.
Ordre impératif : consolidation AVANT build-station-eras (sinon la correction `excluded` de Q28495871 vise encore la gare du CFEL).
