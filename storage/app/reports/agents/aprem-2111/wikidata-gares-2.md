# Éléments Wikidata des gares, deuxième passe (agent « wikidata-gares-2 », 23/11/2026)

Lecture seule de `vff` (`SET statement_timeout = '30s'`), aucune modification sur Wikidata. Sources : API Wikidata (`wbgetentities` par lots de 50, `wbsearchentities`, 5 s entre deux requêtes), API Wikipédia (`action=parse`, coordonnées de l'infobox et PK de l'article, une page à la fois, 5 s d'écart), BD TOPO de l'IGN déjà en cache (`storage/app/data/gares-audit/bdtopo`), objets OSM et listes SNCF déjà dans `station_sources`. User-Agent du projet. Suite de `wikidata-gares.md` (commit 4a732fc6, section 46).

## Bilan

| | Nombre |
|:-|-:|
| Candidats « nom et distance » tranchés | 8 sur 8 rattachés (2 autres écartés, voir 1) |
| Éléments de même UIC à plus de 300 m tranchés | 9 sur 9 rattachés |
| Éléments rattachés à plus de 300 m relus | 145 (dont 42 à plus de 1 km) |
| Erreur de Wikidata (à signaler, rien à changer chez nous) | 81 (dont 23 à plus de 1 km) |
| Erreur de la fiche VFF corrigée (`position`, deux sources concordantes) | 2 (Rédange, Honfleur ; les deux à plus de 1 km) |
| Erreur de VFF probable, non corrigée (pas de seconde source indépendante) | 48 (dont 14 à plus de 1 km) |
| Trois positions distinctes, sans source, ou lien à instruire | 14 (dont 3 à plus de 1 km) |
| Mauvais rattachement (`unlink`) | 0 prouvé ; 1 à instruire (Calais-Marée) |

Écrit : section 51 de `database/data/gares_corrections.php` (17 `link`, 2 `position`) et reprise des déclarations des éléments rattachés (code, tests, `docs/COMMANDES.md`).

## 1. Candidats « nom et distance » et UIC identiques à plus de 300 m

Règle de rattachement sûr : un seul élément et une seule fiche de ce nom à moins de 300 m, et au moins une preuve indépendante du nom (ligne et PK de l'article identiques à ceux de la fiche, commune de l'élément ou de l'article, UIC). Pour les neuf éléments de même UIC : même UIC (7 chiffres) ET même nom, une seule fiche, aucune autre gare de même nom ; ce sont des faisceaux de marchandises, où la liste SNCF et Wikidata ne posent pas le point au même endroit (écart de 424 m à 2 678 m).

| Élément | Fiche (id) | Écart | Décision | Preuve |
|:-|:-|-:|:-|:-|
| Q141873884 | Rochechouart (19807) | 205 m | rattaché | article « Gare de Rochechouart » (oldid 238824788) : ligne de Saillat-sur-Vienne à Bussière-Galant, PK 453,725, identique à la fiche |
| Q142182717 | Lahonce (20131) | 240 m | rattaché | seule gare de ce nom, ligne de Toulouse à Bayonne comme la fiche (pas d'article) |
| Q141804024 | Pont-les-Brie (18966) | 0 m | rattaché | commune Villers-Carbonnel et ligne de Saint-Just-en-Chaussée à Douai comme la fiche |
| Q141873880 | Ste-Feyre (19873) | 27 m | rattaché | article (oldid 238055007) : PK 398,855, ligne de Montluçon à Saint-Sulpice-Laurière, comme la fiche |
| Q2356489 | Chepoix (18977) | 12 m | rattaché | article (oldid 225457339) : PK 92,6 de la ligne de Paris-Nord à Lille, comme la fiche |
| Q113649031 | Marguerittes (20827) | 40 m | rattaché | seul élément de ce nom, commune de Marguerittes ; l'UIC de l'élément (8774570) n'est pas celui de la fiche (8777542), erreur de saisie de Wikidata à signaler |
| Q141874931 | Vix (Vendée) (19484) | 162 m | rattaché | article (oldid 239269843) : PK 141,348 comme la fiche, commune du Gué-de-Velluire |
| Q123498032 | Villechaud (20192) | 46 m | rattaché | article (oldid 213895944) : PK 200,345 comme la fiche, commune de Cosne-Cours-sur-Loire |
| Q19407022 | Lille-Délivrance (15613) | 1 231 m | rattaché | UIC et nom identiques, gare de triage de Lomme (article oldid 236352705) |
| Q16508061 | Strasbourg-Cronenbourg (15346) | 785 m | rattaché | UIC et nom identiques, faisceau de marchandises (article oldid 235034564, PK 500,606) |
| Q17355275 | Strasbourg-Neudorf (15347) | 677 m | rattaché | UIC et nom identiques (article oldid 240408199) |
| Q99709927 | Rungis (MIN) (16689) | 766 m | rattaché | UIC et nom identiques, embranchement du marché de Rungis (article oldid 241269139) |
| Q16964674 | Gravenchon-Port-Jérome (16226) | 670 m | rattaché | UIC, nom et PK 221,081 identiques (article oldid 239668426) |
| Q120758290 | Bassens-Appontements (16864) | 929 m | rattaché | UIC et nom identiques, voies du port de Bassens |
| Q18613203 | Marseille-Maritime-Canet (20630) | 981 m | rattaché | UIC identique, ligne de Marseille-Arenc à Marseille-Canet comme la fiche (article oldid 235032998) |
| Q120757987 | Trèbes (17194) | 424 m | rattaché | UIC et nom identiques ; seul élément « gare de Trèbes » de Wikidata, seule fiche de ce nom |
| Q3096570 | Grandpuits-Bagneux (14868) | 2 678 m | rattaché (confiance moyenne) | UIC et nom identiques ; attention : le lien Wikipédia de l'élément est une redirection vers l'article de Grandpuits (PK 64,828, fiche 14888, Q3096569), une autre gare que cette fiche (PK 62,480) |
| Q56402628 | Banyuls-dels-Aspres (22778) | 74 m | écarté | fiche absorbée par la fusion en attente (section 40 bis) ; élément de métro |
| Q1885854 | Mairie de Saint-Ouen (23150) | 190 m | écarté | élément de métro (classe Q928830) |

Les 17 rattachements sont écrits (section 51). Les positions de ces neuf fiches ne sont pas touchées : même UIC, deux points dans le même faisceau, aucune preuve que l'un soit faux (Gravenchon-Port-Jérôme : article et Wikidata à 670 m du point SNCF, à regarder avec l'orthophoto).

## 2. Les 145 éléments rattachés à plus de 300 m

Méthode. Pour chaque élément : position de VFF (liste SNCF ou autre), point de Wikidata (P625), coordonnées de l'infobox de l'article Wikipédia, objets OSM de la fiche, gares de la BD TOPO de l'IGN à moins de 150 m, commune de chaque point (contours VFF), gares voisines des sources à moins de 200 m du point Wikidata, PK de l'article contre PK de la liste SNCF, interpolation du PK sur la géométrie de la ligne à partir des gares voisines. Constats : (a) le PK de l'article est presque toujours celui de la liste SNCF (copié), donc non indépendant, sauf quand il en diffère ; (b) la position SNCF est calculée à partir du PK, donc sa cohérence avec ce PK ne prouve rien ; (c) l'infobox de l'article est souvent la copie du point Wikidata (concordance non indépendante).

Règles de décision, dans l'ordre :

1. **Erreur de Wikidata** (81) : une gare de la BD TOPO de même nom à moins de 150 m du point VFF et rien près du point Wikidata, ou l'infobox à moins de 200 m du point VFF et à plus de 300 m de Wikidata, ou le point Wikidata posé sur une autre gare, ou un objet OSM de la fiche à moins de 150 m du point VFF. Rien à changer chez nous.
2. **Erreur de la fiche VFF** (2, corrigée) : position SNCF posée sur une autre ligne, à 12 km de la commune que dit l'article (Rédange) ; objet OSM de l'ancienne gare à 278 m du point de Wikidata et de l'article, point SNCF à 1 950 m (Honfleur). Deux sources concordantes dans chaque cas.
3. **Erreur de VFF probable, non corrigée** (48) : Wikipédia et Wikidata concordent, la liste SNCF seule en face, sans OSM ni BD TOPO : l'infobox est sans doute la copie du point Wikidata, il manque une seconde source indépendante. Trois cas (Piennes, Viessoix, Brouilla) ont en plus un PK d'article différent de celui de la liste SNCF, le point de Wikidata tombant sur le PK de l'article ; j'avais d'abord écrit ces trois corrections, puis retiré : Saint-Gilles présente le même motif (article PK 8,345 contre 7,645, point de Wikidata sur le PK de l'article) alors que la BD TOPO et OSM confirment le point de VFF (et la section 50 de positions-gares le garde), donc le PK d'un article et ses coordonnées ne sont pas des preuves indépendantes. Régularité qui plaide pour VFF fautif : sur la ligne de Mantes-la-Jolie à Cherbourg, Couville, Martinvast, Sottevast, Fresville, Montebourg et Neuilly sont tous décalés de 0,7 à 1 km dans le même sens ; sur la ligne des Causses (Béziers à Neussargues), Loubaresse, Saint-Sauveur-de-Peyre et Ruynes-en-Margeride de 1,2 à 2,6 km. Piste : décalage du PK de la liste SNCF avec la géométrie de ces lignes, à vérifier sur l'orthophoto.
4. **Mauvais rattachement** : un seul cas à instruire, Calais-Marée (87317289) joint à « Calais-Maritime » (Q15971875, fermée en 1995, 8731728) : noms et sites différents (point SNCF à 58 m de Calais-Ville, élément sur l'emprise du port, 1 329 m), UIC de 7 chiffres commun ; non défait faute de preuve (l'UIC concorde).

### 2 a. Erreurs de la fiche VFF corrigées (section 51, action `position`)

| Gare (id) | Écart | Nouveau point | Preuves |
|:-|-:|:-|:-|
| Rédange (Moselle) (18740) | 12 257 m | 49,491016 N, 5,919448 E | la liste SNCF place Rédange à Boulange sur la ligne de Fontoy à Audun-le-Tiche (PK 4,570) ; l'article (oldid 223006410) la place sur la ligne d'Audun-le-Tiche à Hussigny-Godbrange, PK 4,6, commune de Rédange ; le point de l'article et de Wikidata est à 14 m d'une voie de la référence VFF et dans la commune de Rédange. Reste à corriger plus tard : sa ligne dans `station_lines` (195000 au lieu de la ligne d'Audun-le-Tiche à Hussigny-Godbrange) |
| Honfleur (19301) | 1 942 m | 49,418882 N, 0,237902 E | ancienne gare (bâtiment OSM w196859679, devenu gare routière) à 278 m du point de l'article (49,417362 N, 0,241028 E) et de Wikidata ; point SNCF à 1 950 m |

Recoupement avec la section 50 (positions-gares, 150 gares, 23/11) : 35 des 145 fiches y ont une position ; 34 gardent le point VFF (la section confirme que Wikidata est fautif), Thuès est déplacée au point de Wikidata (halte de la BD TOPO à 3 m). Aucun conflit avec les deux positions écrites ici.

### 2 b. Erreurs de Wikidata à signaler (81 éléments)

Le point (P625) de l'élément n'est pas celui de la gare ; le point de VFF est confirmé par la BD TOPO, par l'infobox de l'article, par un objet OSM ou par le fait que le point Wikidata est sur une autre gare. Dans la plupart des cas le point de Wikidata est le centre de la commune ou celui d'une gare voisine : Bray-Dunes (Rosendaël, Leffrinckoucke, Zuydcoote, Sanatorium de Zuydcoote), Kilstett (La Wantzenau), Groisy (Pringy), Aubazine-Saint-Hilaire (Malemort-Lafont), Conne-Saint Cernin (Issigeac), Blécourt et Sancourt (échangés), Roubaix (Croix-l'Allumette), et pour Malestroit, Pleucadeuc et Baud des degrés, minutes et secondes lus comme des décimales (47°48'25" saisi 47,4825). Pour Gorges, le point est celui de l'ancienne gare de 1866 (« à 1,2 km au nord-ouest du bourg » dans l'article).

| Élément | Fiche (id) | Écart | Preuve |
|:-|:-|-:|:-|
| [Q2104220](https://www.wikidata.org/wiki/Q2104220) | La Valbonne (17704) | 44460 m | BD TOPO :  « Gare de la Valbonne » à 33 m du point VFF, rien près du point Wikidata |
| [Q2912572](https://www.wikidata.org/wiki/Q2912572) | Baud (16420) | 38977 m | BD TOPO :  « Gare Marchandise de Baud » à 118 m du point VFF, rien près du point Wikidata |
| [Q2831152](https://www.wikidata.org/wiki/Q2831152) | Malestroit (21616) | 37829 m | infobox à 16 m du point VFF et à 37842 m de Wikidata |
| [Q2654768](https://www.wikidata.org/wiki/Q2654768) | Pleucadeuc (21297) | 36292 m | infobox à 11 m du point VFF et à 36282 m de Wikidata |
| [Q2448329](https://www.wikidata.org/wiki/Q2448329) | Pringy (17910) | 8915 m | BD TOPO :  « Arrêt de Pringy » à 3 m du point VFF, rien près du point Wikidata |
| [Q3049345](https://www.wikidata.org/wiki/Q3049345) | Rosendael (18837) | 8006 m | infobox à 0 m du point VFF et à 8006 m de Wikidata |
| [Q3095589](https://www.wikidata.org/wiki/Q3095589) | Aigueperse (17799) | 7415 m | BD TOPO :  « Arrêt d'Aigueperse » à 18 m du point VFF, rien près du point Wikidata |
| [Q2476004](https://www.wikidata.org/wiki/Q2476004) | Leffrinckoucke (15608) | 6292 m | infobox à 4 m du point VFF et à 6288 m de Wikidata |
| [Q5522706](https://www.wikidata.org/wiki/Q5522706) | Malemort-Lafont (19822) | 5755 m | point Wikidata à 20 m de la gare « Aubazine-St-Hilaire » (17017, fiche sncf) |
| [Q139796738](https://www.wikidata.org/wiki/Q139796738) | Issigeac (23036) | 5561 m | point Wikidata à 1 m de la gare « Conne-Saint Cernin » (21757, fiche osm) |
| [Q3096820](https://www.wikidata.org/wiki/Q3096820) | La Wantzenau (15367) | 2720 m | BD TOPO :  « Halte Ferroviaire de la Wantzenau » à 37 m du point VFF, rien près du point Wikidata |
| [Q16964691](https://www.wikidata.org/wiki/Q16964691) | Sanatorium-de-Zuydcoote (18857) | 2645 m | infobox à 46 m du point VFF et à 2690 m de Wikidata |
| [Q2690852](https://www.wikidata.org/wiki/Q2690852) | Orval-Hyenville (19344) | 2401 m | infobox à 22 m du point VFF et à 2380 m de Wikidata |
| [Q17091774](https://www.wikidata.org/wiki/Q17091774) | Zuydcoote (18856) | 2136 m | infobox à 59 m du point VFF et à 2078 m de Wikidata |
| [Q2098900](https://www.wikidata.org/wiki/Q2098900) | Sancourt (19076) | 2059 m | infobox à 21 m du point VFF et à 2070 m de Wikidata |
| [Q2335619](https://www.wikidata.org/wiki/Q2335619) | Rion-des-Landes (20120) | 1661 m | infobox à 23 m du point VFF et à 1684 m de Wikidata |
| [Q28952372](https://www.wikidata.org/wiki/Q28952372) | Chasteaux (19820) | 1532 m | objet OSM à 108 m du point VFF et à 1432 m de Wikidata |
| [Q1814219](https://www.wikidata.org/wiki/Q1814219) | Les Cauquillous (15861) | 1468 m | BD TOPO :  « Halte Ferroviaire des Cauquilloux » à 4 m du point VFF, rien près du point Wikidata |
| [Q934732](https://www.wikidata.org/wiki/Q934732) | Blécourt (19077) | 1343 m | infobox à 42 m du point VFF et à 1384 m de Wikidata |
| [Q3096324](https://www.wikidata.org/wiki/Q3096324) | Croix-l'Allumette (15587) | 1227 m | BD TOPO :  « Arrêt de Croix-l'Allumette » à 64 m du point VFF, rien près du point Wikidata |
| [Q16507976](https://www.wikidata.org/wiki/Q16507976) | Avenue de Saint-Ouen (21878) | 1147 m | infobox à 0 m du point VFF et à 1147 m de Wikidata |
| [Q3096555](https://www.wikidata.org/wiki/Q3096555) | Gorges (16479) | 1034 m | BD TOPO :  « Halte Ferroviaire de Gorges » à 44 m du point VFF, rien près du point Wikidata |
| [Q3098184](https://www.wikidata.org/wiki/Q3098184) | Camping Savaggio (20979) | 1004 m | BD TOPO :  « Arrêt Savaggio » à 3 m du point VFF, rien près du point Wikidata |
| [Q2007719](https://www.wikidata.org/wiki/Q2007719) | Esches (15506) | 928 m | BD TOPO :  « Arrêt d'Esches » à 105 m du point VFF, rien près du point Wikidata |
| [Q2296325](https://www.wikidata.org/wiki/Q2296325) | Étréchy (16657) | 891 m | BD TOPO :  « Gare d'Étréchy » à 17 m du point VFF, rien près du point Wikidata |
| [Q2286058](https://www.wikidata.org/wiki/Q2286058) | Louvroil (15686) | 780 m | BD TOPO :  « Arrêt de Louvroil » à 17 m du point VFF, rien près du point Wikidata |
| [Q106272725](https://www.wikidata.org/wiki/Q106272725) | Falgueyrat (21770) | 767 m | objet OSM à 0 m du point VFF et à 767 m de Wikidata |
| [Q3097534](https://www.wikidata.org/wiki/Q3097534) | St-Gilles (15188) | 741 m | BD TOPO :  « Halte Ferroviaire de Saint-Gilles » à 27 m du point VFF, rien près du point Wikidata |
| [Q17462003](https://www.wikidata.org/wiki/Q17462003) | Nouvel-Avricourt (18766) | 739 m | infobox à 198 m du point VFF et à 543 m de Wikidata |
| [Q3096467](https://www.wikidata.org/wiki/Q3096467) | Fraisses-Unieux (17759) | 693 m | BD TOPO :  « Arrêt de Fraisses-Unieux » à 33 m du point VFF, rien près du point Wikidata |
| [Q2212891](https://www.wikidata.org/wiki/Q2212891) | Port-Vendres (18243) | 665 m | BD TOPO :  « Halte Ferroviaire de Port-Vendres » à 16 m du point VFF, rien près du point Wikidata |
| [Q24733](https://www.wikidata.org/wiki/Q24733) | Jacques-Monod-La Demi-Lieue (16229) | 614 m | infobox à 19 m du point VFF et à 629 m de Wikidata |
| [Q3097078](https://www.wikidata.org/wiki/Q3097078) | Montesoro (20741) | 576 m | point Wikidata à 9 m de la gare « L'Arinella » (20740, fiche osm) |
| [Q82784422](https://www.wikidata.org/wiki/Q82784422) | Airvault-Ville (19528) | 551 m | infobox à 143 m du point VFF et à 409 m de Wikidata |
| [Q115129176](https://www.wikidata.org/wiki/Q115129176) | Montceaux-Vindecy (20230) | 542 m | infobox à 141 m du point VFF et à 401 m de Wikidata |
| [Q1897049](https://www.wikidata.org/wiki/Q1897049) | Prades-Molitg-les-Bains (18258) | 541 m | BD TOPO :  « Gare de Prades-Molitg-les-Bains » à 4 m du point VFF, rien près du point Wikidata |
| [Q3097206](https://www.wikidata.org/wiki/Q3097206) | Nice-St-Augustin (18041) | 531 m | BD TOPO :  « Gare de Nice Saint-Augustin » à 15 m du point VFF, rien près du point Wikidata |
| [Q2390290](https://www.wikidata.org/wiki/Q2390290) | Sermaise (16681) | 515 m | BD TOPO :  « Gare de Sermaise » à 5 m du point VFF, rien près du point Wikidata |
| [Q1897337](https://www.wikidata.org/wiki/Q1897337) | Saléchan-Siradan (17235) | 508 m | BD TOPO :  « Gare de Saléchan-Siradan » à 53 m du point VFF, rien près du point Wikidata |
| [Q3096375](https://www.wikidata.org/wiki/Q3096375) | Dole-la-Bedugue (20289) | 475 m | infobox à 20 m du point VFF et à 473 m de Wikidata |
| [Q16508084](https://www.wikidata.org/wiki/Q16508084) | Trélazé (16507) | 469 m | BD TOPO :  « Gare de Trélazé » à 29 m du point VFF, rien près du point Wikidata |
| [Q3096199](https://www.wikidata.org/wiki/Q3096199) | Chasse-sur-Rhône (17677) | 457 m | BD TOPO :  « Gare de Chasse-sur-Rhône » à 12 m du point VFF, rien près du point Wikidata |
| [Q57468981](https://www.wikidata.org/wiki/Q57468981) | La Bâthie (17855) | 457 m | BD TOPO :  « Gare de Fret de la Bâthie » à 15 m du point VFF, rien près du point Wikidata |
| [Q1822085](https://www.wikidata.org/wiki/Q1822085) | Lardy (16659) | 456 m | BD TOPO :  « Gare de Lardy » à 14 m du point VFF, rien près du point Wikidata |
| [Q47095408](https://www.wikidata.org/wiki/Q47095408) | Chignin-les-Marches (20488) | 453 m | infobox à 70 m du point VFF et à 418 m de Wikidata |
| [Q3097524](https://www.wikidata.org/wiki/Q3097524) | St-Genouph (16731) | 451 m | BD TOPO :  « Halte Ferroviaire de Saint-Genouph » à 50 m du point VFF, rien près du point Wikidata |
| [Q2672930](https://www.wikidata.org/wiki/Q2672930) | Le Méridien (17648) | 450 m | BD TOPO :  « Halte Ferroviaire du Méridien-la Ferrière » à 5 m du point VFF, rien près du point Wikidata |
| [Q60847657](https://www.wikidata.org/wiki/Q60847657) | Grenade (21729) | 445 m | infobox à 0 m du point VFF et à 445 m de Wikidata |
| [Q60847796](https://www.wikidata.org/wiki/Q60847796) | Puyssegur (21804) | 437 m | infobox à 9 m du point VFF et à 429 m de Wikidata |
| [Q60847730](https://www.wikidata.org/wiki/Q60847730) | Montaigut (21203) | 437 m | infobox à 3 m du point VFF et à 438 m de Wikidata |
| [Q60847631](https://www.wikidata.org/wiki/Q60847631) | Cadours (21239) | 435 m | infobox à 1 m du point VFF et à 436 m de Wikidata |
| [Q56311192](https://www.wikidata.org/wiki/Q56311192) | Cornebarrieu (21869) | 433 m | infobox à 0 m du point VFF et à 433 m de Wikidata |
| [Q60847696](https://www.wikidata.org/wiki/Q60847696) | Mondonville (21566) | 433 m | infobox à 2 m du point VFF et à 434 m de Wikidata |
| [Q2945500](https://www.wikidata.org/wiki/Q2945500) | Nurieux (17876) | 428 m | BD TOPO :  « Gare de Nurieux-Volognat » à 9 m du point VFF, rien près du point Wikidata |
| [Q104440477](https://www.wikidata.org/wiki/Q104440477) | St-Pourçain-sur-Sioule (17488) | 427 m | infobox à 28 m du point VFF et à 418 m de Wikidata |
| [Q112264716](https://www.wikidata.org/wiki/Q112264716) | Riom-ès-Montagnes (20045) | 425 m | BD TOPO :  « Arrêt de Riom-ès-Montagnes » à 11 m du point VFF, rien près du point Wikidata |
| [Q94944633](https://www.wikidata.org/wiki/Q94944633) | Taulé-Henvic (19399) | 416 m | infobox à 104 m du point VFF et à 470 m de Wikidata |
| [Q71559362](https://www.wikidata.org/wiki/Q71559362) | Irigny-Yvours (17666) | 413 m | BD TOPO :  « Halte Ferroviaire d'Irigny-Yvours » à 5 m du point VFF, rien près du point Wikidata |
| [Q83381893](https://www.wikidata.org/wiki/Q83381893) | Aytré (16531) | 406 m | infobox à 76 m du point VFF et à 410 m de Wikidata |
| [Q61883923](https://www.wikidata.org/wiki/Q61883923) | Châteauneuf-sur-Loire (16647) | 405 m | BD TOPO :  « Gare de Marchandise de Châteauneuf-sur-Loire » à 30 m du point VFF, rien près du point Wikidata |
| [Q80455895](https://www.wikidata.org/wiki/Q80455895) | Le Muy (18035) | 401 m | BD TOPO :  « Gare Marchandise du Muy » à 37 m du point VFF, rien près du point Wikidata |
| [Q97293084](https://www.wikidata.org/wiki/Q97293084) | Barbentane-Rognonas (18138) | 401 m | BD TOPO :  « Gare de Barbentane-Rognonas » à 20 m du point VFF, rien près du point Wikidata |
| [Q114796081](https://www.wikidata.org/wiki/Q114796081) | Palinges (20222) | 400 m | infobox à 3 m du point VFF et à 403 m de Wikidata |
| [Q56761839](https://www.wikidata.org/wiki/Q56761839) | Avesnes-le-Comte (21508) | 398 m | infobox à 15 m du point VFF et à 384 m de Wikidata |
| [Q2402394](https://www.wikidata.org/wiki/Q2402394) | Thuès (18266) | 392 m | objet OSM à 6 m du point VFF et à 395 m de Wikidata |
| [Q1809790](https://www.wikidata.org/wiki/Q1809790) | Rouen-Martainville (16193) | 391 m | infobox à 17 m du point VFF et à 374 m de Wikidata |
| [Q24238527](https://www.wikidata.org/wiki/Q24238527) | Aiserey (17561) | 386 m | BD TOPO :  « Arrêt d'Aiserey » à 72 m du point VFF, rien près du point Wikidata |
| [Q60846716](https://www.wikidata.org/wiki/Q60846716) | Hondeghem (21784) | 382 m | infobox à 0 m du point VFF et à 382 m de Wikidata |
| [Q2390007](https://www.wikidata.org/wiki/Q2390007) | Flavy-le-Martel (15722) | 379 m | BD TOPO :  « Halte Ferroviaire de Flavy-le-Martel » à 49 m du point VFF, rien près du point Wikidata |
| [Q1988162](https://www.wikidata.org/wiki/Q1988162) | Allons Argens (21041) | 376 m | BD TOPO :  « Arrêt d'Allons-Argens » à 27 m du point VFF, rien près du point Wikidata |
| [Q3096579](https://www.wikidata.org/wiki/Q3096579) | Grenelle-Marchandises (19128) | 375 m | point Wikidata à 190 m de la gare « Pont-du-Garigliano-Hôpital-Georges-Pompidou » (16097, fiche sncf) |
| [Q599069](https://www.wikidata.org/wiki/Q599069) | Besançon-Franche-Comté-TGV (15735) | 368 m | BD TOPO :  « Gare de Besançon-Franche-Comté TGV » à 25 m du point VFF, rien près du point Wikidata |
| [Q24938186](https://www.wikidata.org/wiki/Q24938186) | Sérignac (20846) | 343 m | objet OSM à 6 m du point VFF et à 342 m de Wikidata |
| [Q3097419](https://www.wikidata.org/wiki/Q3097419) | Rivoli (20738) | 341 m | BD TOPO :  « Arrêt de Bassanese » à 123 m du point VFF, rien près du point Wikidata |
| [Q3097657](https://www.wikidata.org/wiki/Q3097657) | Saltatojo (18792) | 339 m | BD TOPO :  « Arrêt Saltatojo » à 133 m du point VFF, rien près du point Wikidata |
| [Q3096176](https://www.wikidata.org/wiki/Q3096176) | Chamarande (16658) | 332 m | BD TOPO :  « Gare de Chamarande » à 14 m du point VFF, rien près du point Wikidata |
| [Q3096385](https://www.wikidata.org/wiki/Q3096385) | Domfront (16164) | 329 m | BD TOPO :  « Arrêt de Domfront » à 54 m du point VFF, rien près du point Wikidata |
| [Q2211930](https://www.wikidata.org/wiki/Q2211930) | Grandvilliers (15791) | 323 m | BD TOPO :  « Gare de Grandvilliers » à 10 m du point VFF, rien près du point Wikidata |
| [Q3097754](https://www.wikidata.org/wiki/Q3097754) | Sisteron (17966) | 312 m | BD TOPO :  « Gare de Sisteron » à 7 m du point VFF, rien près du point Wikidata |
| [Q112724435](https://www.wikidata.org/wiki/Q112724435) | Porte Maillot (17777) | 312 m | BD TOPO :  « Neuilly-Porte Maillot-Palais des Congrès » à 79 m du point VFF, rien près du point Wikidata |
| [Q24053005](https://www.wikidata.org/wiki/Q24053005) | Sarrance (17310) | 307 m | BD TOPO :  « Arrêt de Sarrance » à 14 m du point VFF, rien près du point Wikidata |

Autres erreurs de Wikidata rencontrées : Marguerittes Q113649031 (UIC 8774570, celui de la fiche est 8777542) ; Q3096570 « Grandpuits-Bagneux » (lien Wikipédia vers l'article de Grandpuits, coordonnées à 2,7 km du PK de la liste SNCF) ; Q15971875 « Calais-Maritime » (UIC 8731728, que la liste SNCF donne à « Calais-Marée » : à instruire).

### 2 c. Erreurs de VFF probables, non corrigées (48)

Wikipédia et Wikidata concordent, la liste SNCF est seule en face. Corriger demande une seconde source indépendante (orthophoto, BD TOPO du bâtiment, OSM) : à faire avec la planche de relecture.

| Élément | Fiche (id) | Écart | Preuve |
|:-|:-|-:|:-|
| [Q1995637](https://www.wikidata.org/wiki/Q1995637) | Loubaresse (20891) | 2642 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 668.116 contre SNCF [668116] |
| [Q120758344](https://www.wikidata.org/wiki/Q120758344) | Carquefou (16483) | 2087 m | Wikipédia et Wikidata concordent (à 8 m), la source SNCF seule en face ; PK article 383.806 contre SNCF [383806] |
| [Q16964675](https://www.wikidata.org/wiki/Q16964675) | Grémonville (19214) | 2026 m | Wikipédia et Wikidata concordent (à 6 m), la source SNCF seule en face ; PK article 175.276 contre SNCF [172275] |
| [Q120757163](https://www.wikidata.org/wiki/Q120757163) | Tiercelet-Villers-la-Montagne (15343) | 1955 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 357.0 contre SNCF [362624] |
| [Q18368445](https://www.wikidata.org/wiki/Q18368445) | Guérande (19446) | 1631 m | Wikipédia et Wikidata concordent (à 2 m), la source SNCF seule en face |
| [Q2649157](https://www.wikidata.org/wiki/Q2649157) | Auvers (Manche) (19334) | 1484 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 316.755 contre SNCF [316755] |
| [Q3096794](https://www.wikidata.org/wiki/Q3096794) | La Meauffe (19326) | 1461 m | Wikipédia et Wikidata concordent (à 2 m), la source SNCF seule en face ; PK article 7.125 contre SNCF [7125] |
| [Q123498386](https://www.wikidata.org/wiki/Q123498386) | Piennes (18696) | 1441 m | PK de l'article 8,809 contre 6,809 SNCF : à PK 8,809 la position Wikidata tombe à 25 m, la position VFF à 1 936 m |
| [Q19406999](https://www.wikidata.org/wiki/Q19406999) | Lyon-Croix-Rousse (20364) | 1349 m | Wikipédia et Wikidata concordent (à 5 m), la source SNCF seule en face |
| [Q3097615](https://www.wikidata.org/wiki/Q3097615) | St-Sauveur-de-Peyre (20893) | 1333 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 635.051 contre SNCF [635051] |
| [Q16638594](https://www.wikidata.org/wiki/Q16638594) | Strasbourg-Koenigshoffen (18742) | 1301 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 5.742 contre SNCF [4414, 5742, 0, 32697] |
| [Q1888866](https://www.wikidata.org/wiki/Q1888866) | Senlis (18804) | 1269 m | Wikipédia et Wikidata concordent (à 22 m), la source SNCF seule en face |
| [Q3097462](https://www.wikidata.org/wiki/Q3097462) | Ruynes-en-Margeride (20889) | 1224 m | Wikipédia et Wikidata concordent (à 3 m), la source SNCF seule en face ; PK article 678.984 contre SNCF [678984] |
| [Q106868075](https://www.wikidata.org/wiki/Q106868075) | Belley (20501) | 1201 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 105.361 contre SNCF [105361] |
| [Q17355273](https://www.wikidata.org/wiki/Q17355273) | Metz-Marchandises (15290) | 984 m | Wikipédia et Wikidata concordent (à 1 m), la source SNCF seule en face ; PK article 2.288 contre SNCF [2288] |
| [Q135742683](https://www.wikidata.org/wiki/Q135742683) | Clermont-l'Hérault (20861) | 964 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 488.939 contre SNCF [489939] |
| [Q3097767](https://www.wikidata.org/wiki/Q3097767) | Sottevast (19332) | 963 m | Wikipédia et Wikidata concordent (à 87 m), la source SNCF seule en face ; PK article 352.266 contre SNCF [352266, 73100] |
| [Q21427371](https://www.wikidata.org/wiki/Q21427371) | Harnes-Halte (19068) | 903 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 222.3 contre SNCF [222340, 19100] |
| [Q3096317](https://www.wikidata.org/wiki/Q3096317) | Couville (19319) | 869 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 358.971 contre SNCF [358971] |
| [Q3096987](https://www.wikidata.org/wiki/Q3096987) | Martinvast (19318) | 866 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 364.264 contre SNCF [364264] |
| [Q3096484](https://www.wikidata.org/wiki/Q3096484) | Fresville (19330) | 852 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 331.59 contre SNCF [331550] |
| [Q3097075](https://www.wikidata.org/wiki/Q3097075) | Montebourg (19331) | 829 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 334.912 contre SNCF [334912, 0] |
| [Q82549674](https://www.wikidata.org/wiki/Q82549674) | Brouilla (20909) | 767 m | PK de l'article 487,232 contre 486,456 SNCF : à PK 487,232 la position Wikidata tombe à 225 m, la position VFF à 1 021 m ; article |
| [Q6887677](https://www.wikidata.org/wiki/Q6887677) | Bouleternère (20916) | 739 m | Wikipédia et Wikidata concordent (à 56 m), la source SNCF seule en face ; PK article 492.831 contre SNCF [492831] |
| [Q26806211](https://www.wikidata.org/wiki/Q26806211) | Nefiach (20915) | 675 m | Wikipédia et Wikidata concordent (à 12 m), la source SNCF seule en face ; PK article 485.695 contre SNCF [485695] |
| [Q3097186](https://www.wikidata.org/wiki/Q3097186) | Neuilly (19328) | 670 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 301.472 contre SNCF [301472, 0] |
| [Q47188582](https://www.wikidata.org/wiki/Q47188582) | Domanova-Rodes (20917) | 599 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 495.33 contre SNCF [495330] |
| [Q3097977](https://www.wikidata.org/wiki/Q3097977) | Viescamp-sous-Jalles (20019) | 598 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 288.691 contre SNCF [698921, 288695] |
| [Q3096892](https://www.wikidata.org/wiki/Q3096892) | Lissac (20432) | 576 m | Wikipédia et Wikidata concordent (à 2 m), la source SNCF seule en face ; PK article 34.598 contre SNCF [34598] |
| [Q86683659](https://www.wikidata.org/wiki/Q86683659) | Villiers-le-Sec (15004) | 542 m | Wikipédia et Wikidata concordent (à 6 m), la source SNCF seule en face ; PK article 256.337 contre SNCF [256837, 8854] |
| [Q113836012](https://www.wikidata.org/wiki/Q113836012) | St-Juéry (17220) | 533 m | Wikipédia et Wikidata concordent (à 3 m), la source SNCF seule en face ; PK article 422.335 contre SNCF [422335, 69314] |
| [Q3095856](https://www.wikidata.org/wiki/Q3095856) | Uzès (20840) | 508 m | Wikipédia et Wikidata concordent (à 6 m), la source SNCF seule en face |
| [Q3097979](https://www.wikidata.org/wiki/Q3097979) | Viessoix (19359) | 475 m | PK de l'article 64,315 contre 64,815 SNCF : à PK 64,315 la position Wikidata tombe à 3 m, la position VFF à 426 m |
| [Q113835959](https://www.wikidata.org/wiki/Q113835959) | Auneau-Ville (19629) | 421 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 1.95 contre SNCF [20786, 0] |
| [Q65153888](https://www.wikidata.org/wiki/Q65153888) | La Chapelle-St-Laurent (19537) | 416 m | Wikipédia et Wikidata concordent (à 16 m), la source SNCF seule en face ; PK article 61.799 contre SNCF [61799] |
| [Q3095841](https://www.wikidata.org/wiki/Q3095841) | Outreau (19017) | 409 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 249.6 contre SNCF [249600] |
| [Q21294364](https://www.wikidata.org/wiki/Q21294364) | Carvin (19070) | 402 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 229.0 contre SNCF [228935, 11185, 21457] |
| [Q2878570](https://www.wikidata.org/wiki/Q2878570) | Badan (17669) | 376 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face |
| [Q20971048](https://www.wikidata.org/wiki/Q20971048) | Lurs (20605) | 371 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 320.045 contre SNCF [319942] |
| [Q3095767](https://www.wikidata.org/wiki/Q3095767) | Herme (18293) | 352 m | Wikipédia et Wikidata concordent (à 3 m), la source SNCF seule en face ; PK article 99.551 contre SNCF [99551] |
| [Q2789090](https://www.wikidata.org/wiki/Q2789090) | Barenton-Cohartille (18914) | 349 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 152.468 contre SNCF [152468] |
| [Q24350157](https://www.wikidata.org/wiki/Q24350157) | La Plage-d'Hyères (20646) | 339 m | Wikipédia et Wikidata concordent (à 1 m), la source SNCF seule en face ; PK article 13.891 contre SNCF [13542] |
| [Q123498036](https://www.wikidata.org/wiki/Q123498036) | Argent (19612) | 333 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 73.755 contre SNCF [73755, 22811, 68, 0] |
| [Q28041180](https://www.wikidata.org/wiki/Q28041180) | Bois-de-Céné (19433) | 326 m | Wikipédia et Wikidata concordent (à 0 m), la source SNCF seule en face ; PK article 47.47 contre SNCF [47470] |
| [Q16964671](https://www.wikidata.org/wiki/Q16964671) | Bolbec-Ville (19225) | 320 m | Wikipédia et Wikidata concordent (à 4 m), la source SNCF seule en face ; PK article 208.421 contre SNCF [208065] |
| [Q81720221](https://www.wikidata.org/wiki/Q81720221) | Céret (20911) | 320 m | Wikipédia et Wikidata concordent (à 11 m), la source SNCF seule en face ; PK article 504.317 contre SNCF [504313] |
| [Q18380052](https://www.wikidata.org/wiki/Q18380052) | Vicoigne (19060) | 311 m | Wikipédia et Wikidata concordent (à 34 m), la source SNCF seule en face ; PK article 41.35 contre SNCF [41350, 1452] |
| [Q3096329](https://www.wikidata.org/wiki/Q3096329) | Créchy (20237) | 302 m | Wikipédia et Wikidata concordent (à 5 m), la source SNCF seule en face ; PK article 347.843 contre SNCF [347843] |

### 2 d. Trois positions, sans source, ou lien à instruire (14)

Messei (2 156 m, l'article, Wikidata et VFF donnent trois points), Dirol (pas d'article), Calais-Marée (règle 4) et les cas ci-dessous de 300 à 1 000 m où l'article ne tranche pas entre les trois positions.

| Élément | Fiche (id) | Écart | Preuve |
|:-|:-|-:|:-|
| [Q3097028](https://www.wikidata.org/wiki/Q3097028) | Messei (19355) | 2156 m | trois positions : article à 2376 m de VFF et 797 m de Wikidata |
| [Q15971875](https://www.wikidata.org/wiki/Q15971875) | Calais-Marée (19022) | 1329 m | Calais-Marée (SNCF) et Calais-Maritime (Wikidata, fermée en 1995) : noms et sites différents, UIC de 7 chiffres commun ; à instrui |
| [Q15401830](https://www.wikidata.org/wiki/Q15401830) | Dirol (20249) | 1202 m | pas d'article Wikipédia ni d'autre source |
| [Q82746143](https://www.wikidata.org/wiki/Q82746143) | Airvault-Gare (16568) | 886 m | trois positions : article à 500 m de VFF et 416 m de Wikidata |
| [Q105540959](https://www.wikidata.org/wiki/Q105540959) | Marseille-Maritime-Arenc (17989) | 848 m | trois positions : article à 1095 m de VFF et 714 m de Wikidata |
| [Q97275246](https://www.wikidata.org/wiki/Q97275246) | Brias (19048) | 641 m | trois positions : article à 223 m de VFF et 425 m de Wikidata |
| [Q15278923](https://www.wikidata.org/wiki/Q15278923) | Les Verrières (20188) | 589 m | pas d'article Wikipédia ni d'autre source |
| [Q106393792](https://www.wikidata.org/wiki/Q106393792) | Le Lude (19181) | 543 m | pas d'article Wikipédia ni d'autre source |
| [Q106393654](https://www.wikidata.org/wiki/Q106393654) | Thorée-les-Pins (19179) | 542 m | pas d'article Wikipédia ni d'autre source |
| [Q15278909](https://www.wikidata.org/wiki/Q15278909) | Les Granges-Narboz (20300) | 474 m | pas d'article Wikipédia ni d'autre source |
| [Q3096750](https://www.wikidata.org/wiki/Q3096750) | La Boisse (20371) | 461 m | trois positions : article à 273 m de VFF et 201 m de Wikidata |
| [Q2151700](https://www.wikidata.org/wiki/Q2151700) | Maulde-Mortagne (15919) | 367 m | pas d'article Wikipédia ni d'autre source |
| [Q114571618](https://www.wikidata.org/wiki/Q114571618) | Fenières (20550) | 360 m | pas d'article Wikipédia ni d'autre source |
| [Q81411829](https://www.wikidata.org/wiki/Q81411829) | Palau-del-Vidre (20908) | 312 m | pas d'article Wikipédia ni d'autre source |

## 3. Reprise des déclarations des éléments rattachés par `link`

Code (arbre `wikidata-gares-2`) :

- `ImportStationSources` : `wikidata` lit en plus de la recherche les Q-id des corrections `link` (méthode statique testée `wikidataIds`), et `parseWikidata` ne les écarte plus pour leur classe (gare de marchandises, Q55493) ; une seule lecture du fichier de corrections par commande (`linkedWikidata`, `withLinkedWikidata` pour les tests). Les lignes ainsi importées portent UIC, dates (P1619, P3999, P576), coordonnées et état d'usage.
- `ConsolidateStations::matchById` : un élément Wikidata qui porte le Q-id de la gare rattachée la rejoint (à moins de 5 km, contraintes habituelles), même sans UIC ni nom concordant ; sans cela il aurait créé une fiche en double. La position et l'état du jour restent ceux de la liste SNCF (priorité des sources inchangée) ; les dates d'ouverture de Wikidata entrent comme événements de source `wikidata`, et ses coordonnées comme source secondaire (dispersion de position).
- `docs/COMMANDES.md` : une phrase.
- Tests : `StationSourcesWikidataTest` (classe exclue écartée sauf si rattachée, listes sans doublon), `ConsolidateStationsRulesTest::test_wikidata_item_linked_by_a_correction_joins_its_station_with_its_declarations` (échoue sans le changement de `matchById`, vérifié).

Limite : l'effet réel dépend des déclarations des 176 éléments (159 + 17) : aucun n'a de date de fermeture connue ; les ouvertures P1619 sont rares sur les éléments de marchandises.

## 4. Commande d'application (coordinateur, non lancée)

```
podman exec vff-db pg_dump -U vff -Fc -t stations -t station_sources -t station_events -t station_lines -t station_redirects vff > <dossier-dumps>/avant-wikidata-gares-2.dump
bin/php artisan vff:import-station-sources --source=wikidata        # lit en plus les 176 éléments rattachés (réseau, 0,5 s entre deux lots)
bin/php artisan vff:consolidate-stations --dry-run                  # attendu : 176 lignes « rattachement … : N objet(s) », aucun avertissement « introuvable »
bin/php artisan vff:consolidate-stations
bin/php artisan vff:build-station-eras                              # applique les 2 corrections de position et les dates
```

Contrôle ensuite : `select count(*) from stations where wikidata_id is null` baisse de 17 ; les deux fiches (Rédange, Honfleur) ont `position_source = 'correction'`. Les arrêts (`line_stops`) et les tronçons de ces deux gares sont recalculés par `vff:build-station-eras` ; si le coordinateur rejoue la chaîne complète, enchaîner ensuite `vff:build-shared-track` puis les invariants (consigne habituelle).

## 5. Contrôles

145 tests verts (corrections, fusions, consolidation, déclarations Wikidata, `TypographicDashTest`), Pint propre, PHPStan sans erreur sur les deux commandes. Aucune écriture dans `vff`, aucune modification sur Wikidata. Pas de tiret long. Incident de procédure : j'ai utilisé `git stash` deux fois dans mon arbre avant la consigne de 22 h (remises reprises aussitôt, `stash@{0}` à moi, rien d'autrui touché).
