# Positions des gares, deuxième passe : 132 corrections sourcées (agent positions-gares-2, 23/11/2026)

Lecture seule de `vff` (statement_timeout 30 s, aucune écriture). Sources relues le 23/11/2026 : API Wikipédia fr (coordonnées et révision, 40 titres par requête, 5 s entre deux), WFS de la Géoplateforme (BD TOPO `equipement_de_transport`, 71 objets, 5 s entre deux), une requête Overpass pour les 192 éléments OSM, positions SNCF et Wikidata déjà en base (`station_audits.positions`). User-Agent du projet.

## Méthode (celle de la section 50)

- Candidates : gares à dispersion de position supérieure à 120 m (1 065), moins celles déjà visées par une correction de position, fusion ou exclusion : 880. Retenues pour examen : les gares ouvertes de dispersion d'au moins 250 m (celles que la première passe n'avait pas écrites) et toutes les gares fermées de plus de 120 m, soit 143 dont 135 ont un article avec coordonnées.
- Deux familles indépendantes à moins de 70 m du point écrit : BD TOPO, OSM (nœuds et bâtiments comptés pour une), liste SNCF (point géocodé et liste des gares de voyageurs comptés pour une), article Wikipédia ou Wikidata (une seule famille ; coordonnées de l'article relues par l'API, sinon P625).
- Point écrit : polygone BD TOPO « Gare voyageurs » non fictif, sinon bâtiment OSM, sinon nœud OSM, sinon point SNCF. Contrôles : au moins une source concordante à 40 m ou moins de la voie du tracé ; pas de second groupe de deux familles à plus de 100 m.
- Gares fermées : retenues seulement si les coordonnées de l'article concordent avec une autre famille (pas de description textuelle seule, aucune n'a été jugée assez précise pour poser un point).
- Écartées (11) : Pont-de-Sèvres (19104) : gare fermée, coordonnées de l'article absentes ou discordantes ; Paris-Montparnasse-Pasteur (19126) : deux groupes de familles concordantes ; Paris-Austerlitz-Souterrain (16697) : deux groupes de familles concordantes ; Bellevue Hiver (20971) : gare fermée sans article concordant ; Dompierre-sur-Mer (16525) : gare fermée sans article concordant ; Sentheim (18624) : gare fermée sans article concordant ; La Chapelle-Geneste (20469) : gare fermée sans article concordant ; Anduze (20829) : gare fermée sans article concordant ; St-Jean-du-Gard (20830) : gare fermée sans article concordant ; Sérignac (20846) : gare fermée sans article concordant ; Viam (16963) : gare fermée sans article concordant.

## Bilan

- 132 corrections `position` dans la section 52 de `database/data/gares_corrections.php` ; 113 gares dont la position VFF était fausse de 70 m ou plus (le plus souvent le point SNCF géocodé au PK, 130 à 405 m du bâtiment voyageurs), les autres ont une dispersion due à une source isolée fautive (Wikidata P625 surtout).
- Point écrit sur : polygone BD TOPO 37, bâtiment OSM 20, nœud OSM 70, point SNCF 5 (gares fermées dont seul le point SNCF et l'article concordent).
- Tests : `tests/Feature/StationPositionsSecondPassCorrectionsTest.php` (132 corrections valides, France, source citée parmi les concordantes, deux familles à 70 m au plus) ; le test de la première passe exclut désormais « deuxième passe ».

## Éléments fautifs à signaler aux sources

| Gare (id) | Dispersion avant | Position VFF avant | Écart à la vraie position | Élément à signaler |
|:-|-:|:-|-:|:-|
| St-Jory (17139) | 428 m | sncf | 393 m | liste SNCF UIC 87611657 (point géocodé) à 393 m |
| St-Pourçain-sur-Sioule (17488) | 427 m | sncf | 15 m | Wikidata Q104440477 (P625) à 412 m |
| Riom-ès-Montagnes (20045) | 425 m | sncf | 23 m | Wikidata Q112264716 (P625) à 410 m |
| Route-de-Launaguet (19908) | 422 m | sncf | 366 m | liste SNCF UIC 87611954 (point géocodé) à 366 m |
| Étang (17442) | 421 m | sncf | 12 m | aucun |
| Francheville (17684) | 410 m | sncf | 231 m | liste SNCF UIC 87722702 (point géocodé) à 231 m; coordonnées de l'article « Gare de Francheville » à 123 m |
| Caen (16258) | 406 m | sncf | 394 m | liste SNCF UIC 87444000 (point géocodé) à 394 m |
| Lacourtensourt (17124) | 406 m | sncf | 405 m | liste SNCF UIC 87611434 (point géocodé) à 405 m |
| Lalande-Église (19907) | 403 m | sncf | 403 m | liste SNCF UIC 87611947 (point géocodé) à 403 m |
| Barbentane-Rognonas (18138) | 401 m | sncf | 0 m | Wikidata Q97293084 (P625) à 401 m |
| Vitrolles-Aéroport-Marseille-Provence (16257) | 400 m | sncf | 259 m | liste SNCF UIC 87439554 (point géocodé) à 259 m; Wikidata Q2462992 (P625) à 142 m |
| Toulouse-Matabiau (17097) | 398 m | sncf | 365 m | liste SNCF UIC 87611004 (point géocodé) à 365 m; BD TOPO EQ_RESEA0000000073024100 à 154 m |
| Villenouvelle (17234) | 397 m | sncf | 388 m | liste SNCF UIC 87618215 (point géocodé) à 388 m |
| Lézignan (Aude) (17195) | 393 m | sncf | 352 m | liste SNCF UIC 87615112 (point géocodé) à 352 m |
| Purretone (19037) | 392 m | sncf | 382 m | liste SNCF UIC 87328112 (point géocodé) à 382 m |
| Bassanese (20739) | 388 m | sncf | 386 m | liste SNCF UIC 87762310 (point géocodé) à 386 m |
| Meroux (15245) | 386 m | sncf | 349 m | liste SNCF UIC 87184408 (point géocodé) à 349 m; liste SNCF UIC 87184408 (point géocodé) à 96 m |
| Aiserey (17561) | 386 m | sncf | 55 m | Wikidata Q24238527 (P625) à 359 m |
| Coursan (18199) | 383 m | sncf | 383 m | liste SNCF UIC 87781161 (point géocodé) à 383 m |
| Flavy-le-Martel (15722) | 379 m | sncf | 36 m | Wikidata Q2390007 (P625) à 415 m |
| Belfort (15238) | 373 m | sncf | 365 m | liste SNCF UIC 87184002 (point géocodé) à 365 m |
| Neufchâtel-Hardelot (15824) | 372 m | sncf | 363 m | liste SNCF UIC 87317099 (point géocodé) à 363 m |
| Hesdigneul (15825) | 371 m | sncf | 368 m | liste SNCF UIC 87317107 (point géocodé) à 368 m |
| Le Pecq (19110) | 371 m | sncf | 361 m | liste SNCF UIC 87382846 (point géocodé) à 361 m |
| Agde (18202) | 370 m | sncf | 369 m | liste SNCF UIC 87781278 (point géocodé) à 369 m; Wikidata Q2028453 (P625) à 78 m |
| Marseillan-Plage (18203) | 370 m | sncf | 368 m | liste SNCF UIC 87781294 (point géocodé) à 368 m |
| Chasse-sur-Rhône (17677) | 367 m | sncf | 130 m | liste SNCF UIC 87722561 (point géocodé) à 130 m; Wikidata Q3096199 (P625) à 457 m |
| Bram (17193) | 366 m | sncf | 364 m | liste SNCF UIC 87615054 (point géocodé) à 364 m |
| Frénouville-Cagny (16263) | 361 m | sncf | 361 m | liste SNCF UIC 87444182 (point géocodé) à 361 m |
| Bibliothèque-François-Mitterrand (15863) | 358 m | sncf | 338 m | liste SNCF UIC 87328328 (point géocodé) à 338 m; BD TOPO EQ_RESEA0000000000140445 à 201 m |
| Lupino (20737) | 358 m | sncf | 358 m | liste SNCF UIC 87762294 (point géocodé) à 358 m |
| Le Quesnoy (15698) | 357 m | sncf | 352 m | liste SNCF UIC 87295857 (point géocodé) à 352 m |
| Castelnaudary (17192) | 357 m | sncf | 343 m | liste SNCF UIC 87615039 (point géocodé) à 343 m |
| Machecoul (16474) | 352 m | sncf | 351 m | liste SNCF UIC 87481366 (point géocodé) à 351 m |
| Beuvrages (15913) | 351 m | sncf | 211 m | liste SNCF UIC 87343160 (point géocodé) à 211 m; Wikidata Q2336942 (P625) à 174 m; coordonnées de l'article « Gare de Beuvrages » à 174 m |
| Bastia (20919) | 349 m | sncf | 347 m | liste SNCF UIC 87860825 (point géocodé) à 347 m |
| Servon (16337) | 348 m | sncf | 345 m | liste SNCF UIC 87471532 (point géocodé) à 345 m; BD TOPO EQ_RESEA0000000069840632 à 129 m |
| Villefranche-de-Lauragais (17191) | 348 m | sncf | 338 m | liste SNCF UIC 87615013 (point géocodé) à 338 m |
| Dunkerque (15585) | 346 m | sncf | 204 m | liste SNCF UIC 87281006 (point géocodé) à 204 m |
| Francardo (20712) | 346 m | sncf | 346 m | liste SNCF UIC 87762021 (point géocodé) à 346 m |
| Nissan (20853) | 345 m | sncf | 345 m | liste SNCF UIC 87781179 (point géocodé) à 345 m |
| Larzalier (20901) | 344 m | sncf | 332 m | liste SNCF UIC 87783662 (point géocodé) à 332 m |
| Loison-sous-Lens (15936) | 342 m | sncf | 342 m | liste SNCF UIC 87345231 (point géocodé) à 342 m |
| Challans (16475) | 341 m | sncf | 342 m | liste SNCF UIC 87481390 (point géocodé) à 342 m; Wikidata Q3096175 (P625) à 71 m |
| Baroncourt (15309) | 340 m | sncf | 340 m | liste SNCF UIC 87192658 (point géocodé) à 340 m |
| Pont-de-Briques (15826) | 340 m | sncf | 331 m | liste SNCF UIC 87317115 (point géocodé) à 331 m |
| Carcassonne (17208) | 340 m | sncf | 324 m | liste SNCF UIC 87615286 (point géocodé) à 324 m |
| Dieppe (16233) | 338 m | sncf | 294 m | liste SNCF UIC 87415018 (point géocodé) à 294 m |
| Anse (17634) | 337 m | sncf | 111 m | OSM n2122065171 à 445 m; liste SNCF UIC 87721324 (point géocodé) à 111 m |
| Avignonet (17229) | 333 m | sncf | 333 m | liste SNCF UIC 87616011 (point géocodé) à 333 m |
| Moult-Argences (16264) | 332 m | sncf | 332 m | liste SNCF UIC 87444190 (point géocodé) à 332 m |
| Borgo (20706) | 329 m | sncf | 339 m | liste SNCF UIC 87761965 (point géocodé) à 339 m |
| Issy-Val-de-Seine (16095) | 327 m | sncf | 54 m | OSM w639123064 à 273 m |
| Baziège (17145) | 323 m | sncf | 323 m | liste SNCF UIC 87611723 (point géocodé) à 323 m |
| Valmont (19232) | 322 m | sncf | 322 m | liste SNCF UIC 87413625 (point géocodé) à 322 m |
| Mulhouse-Musées (Tram/Train) (16617) | 320 m | sncf | 16 m | liste SNCF UIC 87534339 (point géocodé) à 308 m |
| Pontarlier (17573) | 319 m | sncf | 331 m | liste SNCF UIC 87715003 (point géocodé) à 331 m |
| Villefranche-Vernet-les-Bains (18260) | 319 m | sncf | 282 m | liste SNCF UIC 87784686 (point géocodé) à 282 m |
| Monte-Carlo-Country-Club (15871) | 315 m | sncf | 280 m | liste SNCF UIC 87338590 (point géocodé) à 280 m; Wikidata Q3126243 (P625) à 178 m |
| Parthenay (16569) | 313 m | sncf | 103 m | liste SNCF UIC 87487173 (point géocodé) à 103 m; BD TOPO EQ_RESEA0000000357541012 à 79 m |
| Sisteron (17966) | 312 m | sncf | 16 m | Wikidata Q3097754 (P625) à 300 m |
| Sarrance (17310) | 307 m | sncf | 58 m | Wikidata Q24053005 (P625) à 253 m |
| St-Pierre-le-Moûtier (17481) | 304 m | sncf | 304 m | liste SNCF UIC 87696294 (point géocodé) à 304 m |
| Montlaur (17233) | 303 m | sncf | 303 m | liste SNCF UIC 87618207 (point géocodé) à 303 m |
| Calais-Ville (15838) | 298 m | sncf | 287 m | liste SNCF UIC 87317263 (point géocodé) à 287 m |
| Oullins (17664) | 298 m | sncf | 205 m | liste SNCF UIC 87722207 (point géocodé) à 205 m; Wikidata Q1920677 (P625) à 93 m; coordonnées de l'article « Gare d'Oullins » à 93 m |
| Bessay (17485) | 297 m | sncf | 228 m | liste SNCF UIC 87696336 (point géocodé) à 228 m; Wikidata Q3095977 (P625) à 71 m; coordonnées de l'article « Gare de Bessay-sur-Allier » à 71 m |
| Rang-du-Fliers-Verton (15821) | 292 m | sncf | 288 m | liste SNCF UIC 87317057 (point géocodé) à 288 m |
| Rueil-Malmaison (16020) | 290 m | sncf | 340 m | liste SNCF UIC 87382119 (point géocodé) à 340 m |
| Menton-Garavan (18054) | 290 m | sncf | 258 m | liste SNCF UIC 87756494 (point géocodé) à 258 m |
| Ste-Lizaigne (17069) | 288 m | sncf | 218 m | liste SNCF UIC 87597054 (point géocodé) à 218 m; liste SNCF UIC 87597054 (point géocodé) à 71 m |
| Argelès-sur-Mer (18241) | 286 m | sncf | 47 m | Wikidata Q3033406 (P625) à 278 m |
| St-Chéron (16680) | 285 m | sncf | 14 m | Wikidata Q2530265 (P625) à 289 m |
| Lardy (16659) | 284 m | sncf | 187 m | liste SNCF UIC 87545160 (point géocodé) à 187 m; Wikidata Q1822085 (P625) à 464 m |
| Grandvilliers (15791) | 279 m | sncf | 123 m | liste SNCF UIC 87313734 (point géocodé) à 123 m; Wikidata Q2211930 (P625) à 318 m |
| Reuilly (17068) | 278 m | sncf | 226 m | liste SNCF UIC 87597039 (point géocodé) à 226 m |
| Dannes-Camiers (15823) | 277 m | sncf | 273 m | liste SNCF UIC 87317081 (point géocodé) à 273 m |
| Besançon-Franche-Comté-TGV (15735) | 276 m | sncf | 270 m | liste SNCF UIC 87300863 (point géocodé) à 270 m; Wikidata Q599069 (P625) à 379 m |
| Ste-Pazanne (16469) | 276 m | sncf | 274 m | liste SNCF UIC 87481226 (point géocodé) à 274 m; Wikidata Q3097650 (P625) à 72 m |
| Saincaize (17480) | 276 m | sncf | 180 m | liste SNCF UIC 87696260 (point géocodé) à 180 m; Wikidata Q967434 (P625) à 96 m |
| Furiani (20713) | 276 m | sncf | 275 m | liste SNCF UIC 87762039 (point géocodé) à 275 m |
| Pierre-Bénite (17665) | 274 m | sncf | 252 m | liste SNCF UIC 87722231 (point géocodé) à 252 m |
| Irigny-Yvours (17666) | 272 m | sncf | 215 m | liste SNCF UIC 87722249 (point géocodé) à 215 m; Wikidata Q71559362 (P625) à 413 m |
| Blainville-Damelevières (14969) | 270 m | sncf | 270 m | liste SNCF UIC 87141135 (point géocodé) à 270 m; Wikidata Q2110347 (P625) à 77 m; coordonnées de l'article « Gare de Blainville - Damelevières » à 77 m |
| Lorraine-Louvigny-TGV (15007) | 266 m | sncf | 256 m | liste SNCF UIC 87142109 (point géocodé) à 256 m |
| Berre (17994) | 264 m | sncf | 264 m | liste SNCF UIC 87753228 (point géocodé) à 264 m |
| Chamousset (17841) | 263 m | sncf | 263 m | liste SNCF UIC 87741231 (point géocodé) à 263 m |
| Banyuls-sur-Mer (18244) | 262 m | sncf | 16 m | Wikidata Q2243839 (P625) à 248 m |
| St-Étienne-Châteaucreux (17737) | 261 m | sncf | 242 m | liste SNCF UIC 87726000 (point géocodé) à 242 m |
| Jarville-la-Malgrange (14958) | 258 m | sncf | 251 m | liste SNCF UIC 87141010 (point géocodé) à 251 m |
| Igny (16115) | 258 m | sncf | 248 m | liste SNCF UIC 87393561 (point géocodé) à 248 m |
| Chambéry-Challes-les-Eaux (17833) | 258 m | sncf | 219 m | liste SNCF UIC 87741009 (point géocodé) à 219 m |
| Trois-Chênes (15240) | 257 m | sncf | 202 m | liste SNCF UIC 87184242 (point géocodé) à 202 m |
| Martigues (18005) | 256 m | sncf | 250 m | liste SNCF UIC 87753509 (point géocodé) à 250 m |
| Penne (16934) | 255 m | sncf | 254 m | liste SNCF UIC 87586438 (point géocodé) à 254 m |
| Pontgouin (16142) | 254 m | sncf | 111 m | liste SNCF UIC 87394247 (point géocodé) à 111 m; Wikidata Q1828187 (P625) à 146 m |
| Lucciana (20667) | 251 m | sncf | 247 m | liste SNCF UIC 87761171 (point géocodé) à 247 m |
| Javel (16077) | 250 m | sncf | 8 m | OSM w1190204104 à 250 m |
| St-Chamas (17993) | 250 m | sncf | 250 m | liste SNCF UIC 87753202 (point géocodé) à 250 m |
| Cevins (20505) | 236 m | sncf | 236 m | liste SNCF UIC 87741686 (point géocodé) à 236 m |
| Chauvigny (19689) | 232 m | sncf | 0 m | OSM n4161623563 à 203 m; OSM w415086355 à 232 m |
| Champdeniers-St-Christophe (19505) | 216 m | sncf | 216 m | liste SNCF UIC 87485706 (point géocodé) à 216 m |
| St-Loup-Lamairé (19529) | 215 m | sncf | 215 m | liste SNCF UIC 87487157 (point géocodé) à 215 m |
| Boulevard-Masséna (19635) | 208 m | sncf | 208 m | liste SNCF UIC 87547323 (point géocodé) à 208 m |
| Draguignan (20655) | 207 m | sncf | 201 m | liste SNCF UIC 87755850 (point géocodé) à 201 m |
| Balme-Araches (20586) | 203 m | sncf | 195 m | liste SNCF UIC 87746396 (point géocodé) à 195 m |
| Faugères (20871) | 199 m | sncf | 199 m | liste SNCF UIC 87781641 (point géocodé) à 199 m |
| Lestelle (17107) | 198 m | sncf | 198 m | liste SNCF UIC 87611111 (point géocodé) à 198 m |
| St-Hilaire-la-Gravelle (19658) | 196 m | sncf | 194 m | liste SNCF UIC 87574525 (point géocodé) à 194 m |
| Cherveux (19504) | 194 m | sncf | 194 m | liste SNCF UIC 87485698 (point géocodé) à 194 m |
| Brunstatt (18601) | 190 m | sncf | 189 m | liste SNCF UIC 87181032 (point géocodé) à 189 m |
| Golfech (17231) | 186 m | sncf | 7 m | Wikidata Q3096560 (P625) à 194 m |
| Labarthe-Inard (17108) | 175 m | sncf | 153 m | liste SNCF UIC 87611129 (point géocodé) à 153 m |
| Montierchaume (17072) | 170 m | sncf | 170 m | liste SNCF UIC 87597088 (point géocodé) à 170 m; BD TOPO EQ_RESEA0000000104608975 à 154 m |
| Guitres (19589) | 168 m | sncf | 0 m | OSM n1811724301 à 168 m |
| Brion-près-Thouet (19545) | 159 m | sncf | 159 m | liste SNCF UIC 87487611 (point géocodé) à 159 m |
| Pas-de-Jeu (19667) | 159 m | sncf | 159 m | liste SNCF UIC 87575209 (point géocodé) à 159 m |
| Nonant-le-Pin (16282) | 156 m | sncf | 156 m | liste SNCF UIC 87444570 (point géocodé) à 156 m |
| Crissé (16166) | 153 m | sncf | 141 m | liste SNCF UIC 87396226 (point géocodé) à 141 m |
| Axat (Aude) (20912) | 145 m | sncf | 145 m | liste SNCF UIC 87784421 (point géocodé) à 145 m |
| Villeneuve-d'Aveyron (19947) | 143 m | sncf | 0 m | OSM n942950524 à 143 m |
| Saumur-Rive-Gauche (19549) | 142 m | sncf | 142 m | liste SNCF UIC 87487694 (point géocodé) à 142 m |
| Taillebourg (16585) | 139 m | sncf | 138 m | liste SNCF UIC 87491043 (point géocodé) à 138 m |
| Medan (19112) | 137 m | sncf | 126 m | liste SNCF UIC 87386631 (point géocodé) à 126 m |
| Le Merlerault (16283) | 132 m | sncf | 130 m | liste SNCF UIC 87444588 (point géocodé) à 130 m |
| Coulonges-Thouarsais (19531) | 132 m | sncf | 123 m | liste SNCF UIC 87487215 (point géocodé) à 123 m |
| St-Martin-Bellevue (20582) | 131 m | sncf | 0 m | OSM n9388719206 à 131 m |
| Caligny (19365) | 130 m | sncf | 127 m | liste SNCF UIC 87448308 (point géocodé) à 127 m |
| Noirterre (19532) | 130 m | sncf | 130 m | liste SNCF UIC 87487231 (point géocodé) à 130 m |
| Lambel-Camors (19404) | 128 m | sncf | 101 m | liste SNCF UIC 87476515 (point géocodé) à 101 m |
| Barisey-la-Côte (18393) | 126 m | sncf | 124 m | liste SNCF UIC 87141754 (point géocodé) à 124 m |
| Petit-Coeur-La Léchère-les-Bains (20506) | 121 m | sncf | 105 m | liste SNCF UIC 87741702 (point géocodé) à 105 m |

## Application (coordinateur, non lancée)

```
bin/php artisan vff:build-station-eras --parallel=2   # réapplique les corrections (position_source = correction, dispersion 0)
```

Les corrections de position sont lues par `StationCorrections::apply` : aucune migration. Dump ciblé conseillé avant : `-t stations`.
