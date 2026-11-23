# Points remarquables des LGV : les quatre points tranchés et les autres LGV (agent lgv-restes, 23/11/2026)

## Résultat en bref

- **Montrouge 1989** : laissé non placé, et on le dit (aucune source ne le situe).
- **Saint-Quentin-Fallavier** : placé à la limite TVM 300 / TVM 430 de SNCF Réseau (PK 423,442), plus à Grenay. Grenay et Heyrieux gardent leurs points.
- **Croisilles et Fretin** : les PK interpolés étaient FAUX de 4 km et 3 km. Corrigés par une source directe de SNCF Réseau (circuits de voie) : 148,220 et 197,826.
- **Raccordement 752330** : le fait de 1981 (Montanay - Sathonay) est ajouté, sourcé, et placé.
- **Autres LGV** : de 16 points sur 5 lignes à **63 points sur 10 lignes** (Méditerranée et Sud-Est, Rhin-Rhône, Bretagne - Pays de la Loire, SEA, Interconnexion Est, Contournement Nîmes - Montpellier).

Une méthode nouvelle sert à tout cela : la **liste des circuits de voie** de SNCF Réseau (Open Data), qui donne le PK ET les coordonnées de chaque extrémité de circuit. On projette le point sur la chaîne des extrémités de la voie et on lit le PK, à 50 m près. Contrôle sur des PK connus de 752000 : Grenay 416,648 retrouvé à 0 m (4 m du tracé), Saint-Marcel 493,438 (pour 493,427), Montanay 380,455 (pour 380,500), Saint-Florentin 117,229 (borne OSM 117,186).

## 1. Montrouge (fait 431000, 24/09/1989) : non placé

- L'article de la LGV Atlantique dit « de Montrouge à Connerré » sans PK. L'article parle de PK 6,38 (début du GET 1), du km 4,2 au 5,6 (150 km/h) et de Massy TGV au PK 14,376 : aucun PK de « Montrouge ».
- SNCF Réseau (Mode de cantonnement des lignes, ligne 431000) : block automatique lumineux du PK 1,216 au PK 15,900, TVM 300 du PK 15,900 au PK 222,994. Le bilan LOTI du TGV Atlantique ne contient ni « Montrouge » ni « TVM » (texte relu).
- La ligne 431000 commence au PK 1,216 à Paris 14e (48,8255 ; 2,3017), pas à Montrouge. Placer le fait en inventant un PK serait faux deux fois : la TVM 300 d'aujourd'hui ne commence qu'au PK 15,9.
- **Décision** : le fait reste `unlocated` (le test le pose). Pour le placer il faudrait une source qui dise où commence la TVM de 1989 ou la section mise en service (PK 15,9 au plus tôt) ; je ne l'ai pas trouvée.

## 2. Saint-Quentin-Fallavier : PK 423,442 (limite TVM), plus Grenay

Éléments (article LGV Rhône-Alpes, révision 240488881, et SNCF Réseau) :

| Mesure | PK de la fin de la 1re section |
|---|---|
| Article : 42 km de Montanay (380,5) à Saint-Quentin-Fallavier | 422,5 |
| Article : 73 km de là à Valence (Valence-TGV PK 495,464) | 422,5 |
| Article (histoire) : « quarante kilomètres » | 420,5 |
| Limite TVM 300 / TVM 430, SNCF Réseau (mode de cantonnement) | **423,442** |
| Bifurcation de Grenay (raccordement 752340) | 416,648 (36 km, puis 79 km) |
| Raccordement d'Heyrieux (752350) | 419,074 à 419,081 |
| Croisement avec la ligne de Lyon à Grenoble (905000), calculé sur les formes | environ 417,55 |

L'article dit aussi que la TVM 430 est installée « au sud de la bifurcation de St-Quentin-Fallavier » : c'est précisément la limite TVM. Les deux longueurs de l'article convergent à 0,9 km de la limite SNCF Réseau ; Grenay est à 6,8 km. Position de la limite : 45,60189 ; 5,09708 (coordonnées de la source), au droit de la borne OSM PK 423,6 (nœud 12036189052, à 4 m de ma recherche indépendante).

- Point « Bifurcation de Saint-Quentin-Fallavier » : PK 423 442, méthode nouvelle `sncf_signalling_limit`.
- Point « Bifurcation de Grenay » (alias Grenay) : PK 416 648 conservé. Le raccordement d'Heyrieux a son point (419 074).
- Effet : la TVM 300 de 1992 (Montanay - Saint-Quentin-Fallavier) couvre 380,5 à 423,442 et la TVM 430 de 1994 va de 423,442 à Saint-Marcel. Sur la carte des ères 1990 à 2020, c'est désormais cohérent avec l'état actuel de SNCF Réseau (avant : 7 km de TVM 430 là où SNCF donne de la TVM 300).
- Réserve : « bifurcation » a ici le sens de la source de la fin de section ; la bifurcation physique du raccordement de Grenay reste à 416,6 (point à part).

## 3. Croisilles et Fretin : PK lus sur une source directe

Les PK interpolés entre gares (144,077 et 194,643) étaient faux. La liste des circuits de voie de SNCF Réseau (ligne 226000, 422 circuits) donne :

| Point | Voie 1 | Voie 2 | Distance au point | PK retenu | Avant |
|---|---|---|---|---|---|
| Bifurcation de Croisilles | 148,220 | 148,222 | 6 m / 1 m | **148,220** | 144,077 (erreur 4,1 km) |
| Bifurcation de Fretin | 197,826 | 197,826 | 4 m / 1 m | **197,826** | 194,643 (erreur 3,2 km) |

Cela confirme au passage la borne OSM « estimée » à 148 écartée par l'agent précédent. La frontière belge reste au PK 210,580 (fin de la forme). Longueurs des portions : Gonesse - Arras 148,2 km, Arras - Lille 49,6 km, Fretin - frontière 12,8 km (le tracé ne dépendait pas de ce choix, il en dépend maintenant moins encore).

## 4. Fait de 1981 sur le raccordement 752330

- Ajouté dans `database/data/signalling_history_sources.php` : ligne 752330, TVM, 27/09/1981, de Montanay à Sathonay. Sources : Wikipédia (LGV Sud-Est : « premier tronçon entre Saint-Florentin et Sathonay », mise en service commerciale le 27 septembre 1981 ; l'article dit que la LGV va jusqu'à Sathonay-Camp) et le bilan LOTI de la LGV d'interconnexion en Île-de-France (mêmes sources que le fait voisin de la ligne 752000).
- Deux points sur la ligne 752330 : « Origine de Montanay » (PK 380,500) et « Sathonay-Camp » (PK 394,727 ; alias Sathonay, Lyon-Saint-Clair), tous deux lus aux extrémités de la forme. La gare « Sathonay-Rillieux » (PK 389,314) ne porte pas le nom exact, donc le point est utilisé.
- Résultat de la simulation : la portion est placée (`landmarks`, 380 500 à 394 727).
- **Réserve à connaître** : SNCF Réseau donne aujourd'hui de la TVM 300 du PK 380,5 au PK 386,0 de 752330, puis du block automatique lumineux jusqu'au 394,7. Le fait de 1981 dit « jusqu'à Sathonay » : la carte de 1981 montrera donc de la TVM sur 8,7 km où il n'y en a plus aujourd'hui. Je n'ai pas de source de 1981 qui limite la TVM à 386. À corriger par un fait de BAL si un jour on en a un.

## 5. Les autres LGV : 63 points sur 10 lignes (47 de plus)

Tableau par ligne (points au total, dont ceux du premier lot) :

| Ligne | LGV | Points | Contenu ajouté |
|---|---|---|---|
| 752000 | Sud-Est, Rhône-Alpes, Méditerranée | 21 | Montereau (2), Montchanin, Mâcon-Sud, Mâcon-Nord, Pont-de-Veyle, Heyrieux, Grenay, Saint-Quentin-Fallavier, Châteauneuf-sur-Isère, Crest, Bollène (2), Avignon-Nord (début du CNM), Cheval-Blanc, Moisenay (jonction avec l'Interconnexion) |
| 752330 | Raccordement de Lyon-Saint-Clair | 2 | Montanay, Sathonay-Camp |
| 014000 | Rhin-Rhône (branche Est) | 4 | Besançon TGV ouest et est |
| 408000 | Bretagne - Pays de la Loire | 9 | Connerré (origine), fret de Connerré, La Milesse (2), Sablé, virgule de Sablé, Laval-Est et Laval-Ouest, Rennes (fin) |
| 566000 | SEA | 8 | Monts-Sud, La Celle-Saint-Avant, Migne-Auxances, Fontaine-le-Comte (2), Juillé, Villognon, La Couronne |
| 752100 | Interconnexion Est (branche sud) | 4 | Villeneuve-Saint-Georges (origine), Valenton, Coubert, Moisenay (fin) |
| 834000 | Contournement Nîmes - Montpellier | 7 | Avignon-Nord (origine), triangle des Angles, Redessan-Manduel (2), Jonquières, Saint-Gervasy, Lattes (fin) |

Méthode : pour chaque raccordement de l'Open Data de SNCF Réseau (formes des lignes, `is_lgv`) dont une extrémité touche la forme de la LGV (moins de 40 m), le point de contact (`ST_ClosestPoint`) est projeté sur la chaîne des circuits de voie (méthode `sncf_track_circuits`) ; les origines et fins viennent de la forme (`sncf_line_end`). Chaque point cite la forme et la liste des circuits ; la ligne Wikipédia (révision lue, avec son numéro) n'est ajoutée que si le nom du lieu y figure. Plus 22 citations d'articles : LGV Rhin-Rhône, Bretagne - Pays de la Loire, SEA, Interconnexion Est, Méditerranée, Sud-Est, Rhône-Alpes, Ligne des Angles à Lattes, Contournement de Nîmes et Montpellier.

Limites :
- **Interconnexion Est, branche nord** (Coubert - Vémars, gares de Marne-la-Vallée - Chessy et CDG 2 TGV) : aucune ligne propre dans les formes de SNCF Réseau (ni dans `sncf_line_geometries`, ni dans `lines`) ; seule la branche sud (752100) est couverte. À reprendre si on trouve le code de la ligne.
- Lattes : le raccordement 834340 est trop proche du bout de la forme (198 m d'écart de projection), le point est donc la fin de la ligne.
- Les PK sont à 50 m près (circuits de voie : l'écart entre les deux voies va jusqu'à 25 m).
- Rhin-Rhône : la ligne 014000 n'est que la branche Est ; Besançon TGV est sur ses PK 52 et 57.

## Fichiers (commit unique)

- `database/data/line_landmarks.php` : 63 points, rangés par PK dans chaque ligne ; documentation de l'en-tête (méthodes `sncf_track_circuits`, `sncf_signalling_limit`).
- `app/Services/Signalling/LineLandmarksFile.php` : deux méthodes de plus dans `METHODS`.
- `database/data/signalling_history_sources.php` : fait 752330 (1981), résumé de 191 caractères.
- Tests : `LineLandmarksTest` (valeurs mises à jour : 423 442, 148 220, 197 826 ; fait 752330 ; Montrouge non placé, avec la raison), `LineLandmarksFileTest` (10 lignes, au moins 60 points (63), rangement par PK, Saint-Quentin-Fallavier = limite TVM et Grenay à part, Croisilles lu sur les circuits).

## Contrôles

- PHPUnit à 1 processus : `LineLandmarksTest`, `LineLandmarksFileTest`, `SignallingSourcesFileTest` et dossiers `tests/Unit/Signalling` et `tests/Feature/Signalling` : 213 tests, 1190 assertions, verts. Le test des faits réels échoue sans le changement (les valeurs 423 442, 148 220, 197 826 et la portion 752330 n'existent pas avant).
- Pint propre ; PHPStan sans erreur sur les fichiers touchés, rien ajouté à la baseline.
- Simulation sur `vff` en lecture seule : `vff:import-signalling-sources --dry-run -v` : 40 faits (39 + 1), Portion `landmarks` 15 (14 + le 752330), `unlocated` 1 (Montrouge), `stations` 8.
- Aucune écriture dans `vff`. SNCF Open Data (5 s entre deux requêtes, User-Agent du projet), Wikipédia (API, 5 s), Overpass (requête unique, deux refus 502 puis abandon : la position de la limite TVM vient de SNCF Réseau).
- Aucun texte d'interface touché (i18n inchangé), pas de front, pas de contrat d'API.

## Reste à faire

- Branche nord de l'Interconnexion Est et ses raccordements (Messy 226321, Annet 226320, Vémars) : code de ligne à trouver.
- Les gares des nouvelles LGV comme repères de tracé (Besançon TGV, Haute-Picardie, Champagne-Ardenne, etc.) : plus large, non fait.
- Faits de signalisation à écrire pour les nouvelles LGV (TVM 300/430 et ETCS de BPL le 02/07/2017, SEA, CNM le 07/12/2017, Rhin-Rhône le 11/12/2011 déjà fait) : ils se placent maintenant sur ces points.

## Commandes d'application (coordinateur)

1. Dump ciblé :
   `podman exec vff-db pg_dump -U vff -d vff -Fc -t public.line_signalling_events -t public.line_signalling_history_map > /mnt/Unified_Datas/BaptGosse/Pro/UnxWares/Projects/VFF/database-dumps/signalisation-avant-lgv-restes.dump`
   (restauration : l'archive entière, `pg_restore --single-transaction`, jamais `--clean -t`).
2. Rien à importer pour les points (fichier lu directement, pas de table).
3. `bin/php artisan vff:import-signalling-sources --dry-run -v` (attendu : 40 faits, Portion `landmarks` 15, `unlocated` 1), puis `bin/php artisan vff:import-signalling-sources`. Cela range les 15 portions, recale les tracés de Croisilles, Fretin et Saint-Quentin-Fallavier et recalcule la carte par ère.
4. Facultatif : `bin/php artisan vff:import-signalling-history --offline --dry-run`, puis sans `--dry-run`.
