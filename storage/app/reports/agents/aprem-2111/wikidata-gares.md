# Éléments Wikidata des gares : rattachements sûrs et écarts de position (agent « wikidata-gares », 23/11/2026)

Lecture seule de `vff` (`SET statement_timeout = '30s'`), aucune modification sur Wikidata. Sources : requêtes SPARQL bornées du service Wikidata (UIC P722 en France ; éléments des 26 classes de gare d'`ImportStationSources::WD_CLASSES` situés en France, une classe par requête, 5 s entre deux) et `wbgetentities` (311 éléments, lots de 50, 5 s d'écart), User-Agent du projet. Corrections et fusions en attente (`doublons-2.md`, `erreurs-sources.md`) non touchées.

## Bilan

| | Nombre |
|:-|-:|
| Gares de VFF (9 395) sans élément Wikidata | 4 221 (dont 2 028 avec UIC) |
| Éléments Wikidata de France, de classe gare ou à UIC 87…, non rattachés à une fiche | 311 |
| Rattachements sûrs écrits (section 46 de `gares_corrections.php`) | 159 (147 par UIC, 12 par nom et distance) |
| Candidats écartés ou à décider (voir plus bas) | 152 |
| Éléments rattachés dont les coordonnées s'écartent de plus de 300 m de la fiche | 145 (+ 56 éléments sans coordonnées) |

Constat qui explique le peu de rattachements : l'UIC de Wikidata (P722) a 7 chiffres (sans la clé), celui de VFF 8 ; la comparaison se fait sur les 7 premiers chiffres (comme `vff:consolidate-stations`). Les 2 028 fiches sans Q-id mais avec UIC n'ont presque aucun élément à joindre : l'import (`ImportStationSources`) trouve déjà par la recherche les éléments de classe gare ; les 311 restants sont surtout (144, dont 133 rattachés ici) des éléments de classe « gare de marchandises » (Q55493) exclus à l'import, créés en 2023 de la liste SNCF avec leur UIC.

## Méthode

1. Éléments : tous ceux de France (P17 = Q142) dont P31 est l'une des classes de gare de l'import, avec coordonnées, plus tous ceux dont l'UIC P722 commence par 87 ; ôtés ceux déjà portés par une fiche (5 114 Q-id).
2. Rattachement sûr = **UIC identique** (7 chiffres) ET **distance de moins de 300 m** ET **nom identique** après normalisation (Saint/Ste/St, tirets, parenthèses, « TT »), ET un seul élément pour la fiche et une seule fiche pour l'élément ; ou, pour les fiches sans UIC, **nom identique** ET élément seul de ce nom à moins de 300 m (aucune autre fiche de même nom à moins de 300 m, ni élément concurrent).
3. Refusés : homonymes à plus de 300 m, éléments d'une autre gare de la même ville (Lyon-Guillotière, Saint-Étienne-Pont-de-l'Âne et Terrenoire : une autre fiche du même nom à moins de 300 m), éléments de tramway ou de métro (classe Q2175765, Q928830 : Épinay-sur-Seine TT, Lutterbach, Dugny - La Courneuve, Mairie de Saint-Ouen...), éléments de bâtiment ou de triage, fiches des fusions en attente (Pleine-Fougères, Condé-sur-Vire, Aunay-Tréon, Brens-Virignin, Banyuls-dels-Aspres).
4. Classe : 133 des 159 éléments sont de classe « gare de marchandises » seule ; les 159 fiches sont fret seul (`no_commercial`, 91), plus desservies (`declassified`, 65) ou en service (3) ; celles des éléments de marchandises sont issues de la même liste SNCF, avec le même UIC et le même point (0 m pour la plupart). L'élément de voyageurs, s'il existe, aurait déjà été rattaché.

## Ce qui est écrit

- `database/data/gares_corrections.php`, **section 46** (159 corrections `link`, numérotée après la 45, dernière de HEAD au moment de l'écriture), chacune avec l'URL de l'élément, la propriété lue et la raison.
- Aucun format existant ne posait un Q-id sur une fiche : nouvelle action `link` (`App\Services\StationCorrections::links()`, lue par `vff:consolidate-stations`). Le Q-id est posé sur l'objet source de la gare (UIC sur 7 chiffres, ou nom + position pour les fiches sans UIC) ; **un Q-id déjà porté par un objet n'est jamais remplacé**, un lien défait (`unlink`) l'emporte ; provenance `wikidata_id` = `correction`. Sans effet dans `vff:build-station-eras`.
- Seul le Q-id est posé : ces éléments ne sont pas importés (classe exclue ou absents de la recherche), donc leurs déclarations ne viennent pas dans `station_sources`. À faire plus tard, si voulu : les ajouter à l'import.
- Tests : `StationCorrectionsValidateTest` (forme), `StationCorrectionsTest` (liste), `ConsolidateStationsRulesTest` (rattachement par UIC, par nom et position, homonyme à 11 km non rattaché, Q-id existant non remplacé). Pint propre, PHPStan sans erreur sur les deux fichiers.

## Vérification à la main d'un tirage de 40

Tirage aléatoire (graine 23) dans les 159, relu élément par élément contre la fiche VFF (libellé Wikidata, UIC P722, commune P131 quand elle est donnée, distance). 40 sur 40 concordent (nom, UIC, commune ou département) ; aucun rejet. Cas à noter : Saint-Sauveur - Châteauneuf (commune Wikidata Saint-Sauveur-Marville, fiche SNCF « St-Sauveur-Châteauneuf », 15 m, même UIC) ; Gontaud - Fauguerolles (commune Fauguerolles, fiche idem) ; Port-Édouard-Herriot (Wikidata : 7e arrondissement de Lyon, fiche : 8e, à 120 m, même UIC : limite d'arrondissement) ; Vic-Fezensac (par nom, à 263 m, fiche issue de lignes-oubliées, seul homonyme).

| Élément | Libellé Wikidata | Fiche VFF (id) | UIC | Écart | Mode |
|:-|:-|:-|:-|-:|:-|
| Q120758155 | Athies-sous-Laon | Athies-sous-Laon (15710) | 87296327 | 0 m | uic |
| Q120756903 | Fléré-la-Rivière | Fléré-la-Rivière (17083) | 87597427 | 0 m | uic |
| Q120757185 | Buchères - Verrières | Buchères-Verrières (14947) | 87118430 | 0 m | uic |
| Q120757383 | Gommiers | Gommiers (16640) | 87543322 | 0 m | uic |
| Q120757176 | Crancey | Crancey (14940) | 87118166 | 25 m | uic |
| Q120756912 | Fossé-Marolles | Fossé-Marolles (16749) | 87574400 | 77 m | uic |
| Q120769287 | Douzies | Douzies (15684) | 87295238 | 12 m | uic |
| Q120757391 | Ancerville-Guë | Ancerville-Guë (15166) | 87175166 | 0 m | uic |
| Q120757409 | Baigts-de-Béarn | Baigts-de-Béarn (17300) | 87672261 | 70 m | uic |
| Q115119133 | Petit-Couronne | Petit-Couronne (16195) | 87411116 | 0 m | uic |
| Q120756850 | Orgères-en-Beauce | Orgères (16639) | 87543314 | 0 m | uic |
| Q105384286 | Port-Édouard-Herriot | Port-Édouard-Herriot (17773) | 87729202 | 120 m | uic |
| Q120757041 | Avignon-Fontcouverte | Avignon-Fontcouverte (18133) | 87765024 | 0 m | uic |
| Q120758126 | Clermont-les-Fermes | Clermont-les-Fermes (15711) | 87296376 | 0 m | uic |
| Q120758176 | Braine | Braine (18493) | 87171496 | 0 m | uic |
| Q108130605 | Saint-Sauveur - Châteauneuf | St-Sauveur-Châteauneuf (16155) | 87394569 | 15 m | uic |
| Q2471354 | Gontaud - Fauguerolles | Gontaud-Fauguerolles (19776) | 87586578 | 9 m | uic |
| Q120757392 | Ligny | Ligny (15170) | 87175323 | 0 m | uic |
| Q120757259 | Limoges-Puy-Imbert | Limoges-Puy-Imbert (16953) | 87592014 | 147 m | uic |
| Q106938310 | Faulx | Faulx (23184) | - | 25 m | nom |
| Q120757374 | Sully-sur-Loire | Sully-sur-Loire (16648) | 87543538 | 0 m | uic |
| Q120758321 | Sainte-Colombe-lès-Vienne-Saint-Romain-en-Gal | Ste-Colombe-les-Vienne-St-Romain-en-Gal (17671) | 87722322 | 0 m | uic |
| Q142167269 | Saint-Paul-Trois-Châteaux | Saint-Paul-Trois-Châteaux (23323) | - | 152 m | nom |
| Q120758258 | Le Bec-d'Ambès | Le Bec-d'Ambès (16866) | 87581884 | 0 m | uic |
| Q107649264 | Engenville | Engenville (16649) | 87543686 | 9 m | uic |
| Q114836178 | Saint-André-de-l'Eure | St-André-de-l'Eure (16067) | 87387308 | 0 m | uic |
| Q123498144 | Vic-Fezensac | Vic-Fezensac (23256) | - | 263 m | nom |
| Q120757159 | Jeandelize | Jeandelize (15311) | 87192708 | 0 m | uic |
| Q120757429 | La Chartre-sur-le-Loir | La Chartre-sur-le-Loir (16187) | 87396671 | 164 m | uic |
| Q108740428 | Sorcy | Sorcy (15169) | 87175281 | 102 m | uic |
| Q16764804 | Solférino | Solférino (16872) | 87582577 | 4 m | uic |
| Q108112580 | Beaulieu-Le Coudray | Beaulieu-Le Coudray (16147) | 87394387 | 6 m | uic |
| Q120756899 | Buzançais | Buzançais (17080) | 87597377 | 195 m | uic |
| Q120769307 | Empalot | Empalot (17098) | 87611020 | 4 m | uic |
| Q120769257 | Dijon-Porte-d'Ouche-Perrigny | Dijon-Porte-d'Ouche-Perrigny (17572) | 87713867 | 35 m | uic |
| Q120757396 | Montabard | Montabard (16278) | 87444521 | 17 m | uic |
| Q120757250 | Rimaucourt | Rimaucourt (15014) | 87142299 | 0 m | uic |
| Q120756921 | Mondoubleau | Mondoubleau (19191) | 87396747 | 165 m | uic |
| Q120757406 | Lescar | Lescar (17296) | 87672170 | 78 m | uic |
| Q20971050 | Mesnières-en-Bray | Mesnières-en-Bray (19244) | 87415083 | 49 m | uic |

## Écartés ou à décider (non écrits)

**Meilleur candidat à examiner (nom et distance, l'UIC n'a pas confirmé ou diffère)** :

- Q141873884 « Gare de Rochechouart » : fiche 19807 « Rochechouart » à 205 m, UIC fiche 87592857 (absent de l'élément ou autre)
- Q56402628 « Banyuls-dels-Aspres » : fiche 22778 « Banyuls-dels-Aspres » à 74 m (fiche sans UIC, mais élément de métro, ou fiche en fusion en attente)
- Q142182717 « Lahonce » : fiche 20131 « Lahonce » à 240 m, UIC fiche 87673814 (absent de l'élément ou autre)
- Q1885854 « Mairie de Saint-Ouen » : fiche 23150 « Mairie de Saint-Ouen » à 190 m (fiche sans UIC, mais élément de métro, ou fiche en fusion en attente)
- Q141804024 « Gare de Pont-les-Brie » : fiche 18966 « Pont-les-Brie » à 0 m, UIC fiche 87315242 (absent de l'élément ou autre)
- Q141873880 « Gare de Sainte-Feyre » : fiche 19873 « Ste-Feyre » à 27 m, UIC fiche 87597690 (absent de l'élément ou autre)
- Q2356489 « Chepoix » : fiche 18977 « Chepoix » à 12 m, UIC fiche 87316117 (absent de l'élément ou autre)
- Q113649031 « Marguerittes » : fiche 20827 « Marguerittes » à 40 m, UIC fiche 87775429 (absent de l'élément ou autre)
- Q141874931 « Gare de Vix » : fiche 19484 « Vix (Vendée) » à 162 m, UIC fiche 87485086 (absent de l'élément ou autre)
- Q123498032 « Villechaud » : fiche 20192 « Villechaud » à 46 m, UIC fiche 87691311 (absent de l'élément ou autre)

**UIC identique mais fiche à 300 m ou plus de l'élément (position à contrôler)** :

- Q19407022 « gare de triage de Lille-Délivrance » : fiche 15613 « Lille-Délivrance » (UIC 87286013) à 1231 m
- Q16508061 « Strasbourg-Cronenbourg » : fiche 15346 « Strasbourg-Cronenbourg » (UIC 87212050) à 785 m
- Q17355275 « Strasbourg-Neudorf » : fiche 15347 « Strasbourg-Neudorf » (UIC 87212068) à 677 m
- Q99709927 « Rungis MIN » : fiche 16689 « Rungis (MIN) » (UIC 87546002) à 766 m
- Q16964674 « Gravenchon-Port-Jérôme » : fiche 16226 « Gravenchon-Port-Jérome » (UIC 87413484) à 670 m
- Q120758290 « Bassens-Appontements » : fiche 16864 « Bassens-Appontements » (UIC 87581868) à 929 m
- Q18613203 « Marseille-Canet » : fiche 20630 « Marseille-Maritime-Canet » (UIC 87752717) à 981 m
- Q120757987 « Trèbes » : fiche 17194 « Trèbes » (UIC 87615062) à 424 m
- Q3096570 « Grandpuits-Bagneux » : fiche 14868 « Grandpuits-Bagneux » (UIC 87112086) à 2678 m

**Éléments sans fiche VFF correspondante** : 102 (lignes de métro, RER, bâtiments, terminaux d'aéroport, gares de Belgique ou de Suisse, gares dont aucune fiche n'a le même UIC ni le même nom à 300 m). Ils ne sont pas à rattacher ; certains pourraient être des gares absentes de VFF (à regarder : Saint-Péray, Aire-sur-l'Adour, Fleurance, Anglefort, Thoiry, Leyment, Vivy, Longué, Saint-Juéry, Trèbes déjà vu ci-dessus). Liste complète dans l'étape de calcul : `/tmp/claude-1000/.../scratchpad/wd/lists.json`.

**Éléments sans coordonnées** : 4 (Q109661138 Haulchin - Thiant, Q125789427 Diedendorff, Q110655300 Cappelle - Huquinville, Q132778432 Saint-Gérand).

## Éléments rattachés dont les coordonnées s'écartent de plus de 300 m de la position VFF (à signaler, rien n'est changé)

145 fiches (hors celles déjà dans `erreurs-sources.md` : Rosendaël, Aigueperse, Issigeac, Leffrinckoucke, Malemort-Lafont, La Wantzenau, etc., qui figurent ici aussi). Répartition : 300 à 500 m : 63 ; 500 m à 1 km : 40 ; 1 à 5 km : 31 ; plus de 5 km : 11. Le fautif n'est pas déterminé (position VFF ou coordonnées Wikidata) : à trancher fiche par fiche avec la position SNCF et l'infobox. 56 autres fiches rattachées à un élément sans coordonnées n'ont pas de comparaison possible.

| Fiche (id) | Commune VFF | Élément | Écart | Source de la position VFF |
|:-|:-|:-|-:|:-|
| La Valbonne (17704) | Béligneux | Q2104220 | 44460 m | sncf |
| Baud (16420) | Languidic | Q2912572 | 38977 m | sncf |
| Malestroit (21616) | Saint-Marcel | Q2831152 | 37829 m | wikidata |
| Pleucadeuc (21297) | Pleucadeuc | Q2654768 | 36292 m | wikidata |
| Rédange (Moselle) (18740) | Boulange | Q106074182 | 12257 m | sncf |
| Pringy (17910) | Annecy | Q2448329 | 8915 m | sncf |
| Rosendael (18837) | Dunkerque | Q3049345 | 8006 m | correction |
| Aigueperse (17799) | Aigueperse | Q3095589 | 7415 m | sncf |
| Leffrinckoucke (15608) | Dunkerque | Q2476004 | 6292 m | sncf |
| Malemort-Lafont (19822) | Malemort | Q5522706 | 5755 m | sncf |
| Issigeac (23036) | Issigeac | Q139796738 | 5561 m | osm |
| La Wantzenau (15367) | La Wantzenau | Q3096820 | 2720 m | sncf |
| Sanatorium-de-Zuydcoote (18857) | Zuydcoote | Q16964691 | 2645 m | sncf |
| Loubaresse (20891) | Val d'Arcomie | Q1995637 | 2642 m | sncf |
| Orval-Hyenville (19344) | Orval sur Sienne | Q2690852 | 2401 m | sncf |
| Messei (19355) | Messei | Q3097028 | 2156 m | sncf |
| Zuydcoote (18856) | Zuydcoote | Q17091774 | 2136 m | sncf |
| Carquefou (16483) | Carquefou | Q120758344 | 2087 m | sncf |
| Sancourt (19076) | Abancourt | Q2098900 | 2059 m | sncf |
| Grémonville (19214) | Grémonville | Q16964675 | 2026 m | sncf |
| Tiercelet-Villers-la-Montagne (15343) | Tiercelet | Q120757163 | 1955 m | sncf |
| Honfleur (19301) | Honfleur | Q5522692 | 1942 m | sncf |
| Rion-des-Landes (20120) | Rion-des-Landes | Q2335619 | 1661 m | sncf |
| Guérande (19446) | Guérande | Q18368445 | 1631 m | sncf |
| Chasteaux (19820) | Chasteaux | Q28952372 | 1532 m | sncf |
| Auvers (Manche) (19334) | Méautis | Q2649157 | 1484 m | sncf |
| Les Cauquillous (15861) | Lavaur | Q1814219 | 1468 m | sncf |
| La Meauffe (19326) | La Meauffe | Q3096794 | 1461 m | sncf |
| Piennes (18696) | Piennes | Q123498386 | 1441 m | sncf |
| Lyon-Croix-Rousse (20364) | Caluire-et-Cuire | Q19406999 | 1349 m | sncf |
| Blécourt (19077) | Sancourt | Q934732 | 1343 m | sncf |
| St-Sauveur-de-Peyre (20893) | Peyre en Aubrac | Q3097615 | 1333 m | sncf |
| Calais-Marée (19022) | Calais | Q15971875 | 1329 m | sncf |
| Strasbourg-Koenigshoffen (18742) | Strasbourg | Q16638594 | 1301 m | sncf |
| Senlis (18804) | Senlis | Q1888866 | 1269 m | sncf |
| Croix-l'Allumette (15587) | Croix | Q3096324 | 1227 m | sncf |
| Ruynes-en-Margeride (20889) | Ruynes-en-Margeride | Q3097462 | 1224 m | sncf |
| Dirol (20249) | Dirol | Q15401830 | 1202 m | sncf |
| Belley (20501) | Belley | Q106868075 | 1201 m | sncf |
| Avenue de Saint-Ouen (21878) | Paris | Q16507976 | 1147 m | correction |
| Gorges (16479) | Gorges | Q3096555 | 1034 m | sncf |
| Camping Savaggio (20979) | Vivario | Q3098184 | 1004 m | osm |
| Metz-Marchandises (15290) | Metz | Q17355273 | 984 m | sncf |
| Clermont-l'Hérault (20861) | Clermont-l'Hérault | Q135742683 | 964 m | sncf |
| Sottevast (19332) | Rocheville | Q3097767 | 963 m | sncf |
| Esches (15506) | Esches | Q2007719 | 928 m | sncf |
| Harnes-Halte (19068) | Fouquières-lès-Lens | Q21427371 | 903 m | sncf |
| Étréchy (16657) | Étréchy | Q2296325 | 891 m | osm |
| Airvault-Gare (16568) | Airvault | Q82746143 | 886 m | sncf |
| Couville (19319) | Couville | Q3096317 | 869 m | sncf |
| Martinvast (19318) | Sideville | Q3096987 | 866 m | sncf |
| Fresville (19330) | Fresville | Q3096484 | 852 m | sncf |
| Marseille-Maritime-Arenc (17989) | Marseille 14e Arrondissement | Q105540959 | 848 m | sncf |
| Montebourg (19331) | Le Ham | Q3097075 | 829 m | sncf |
| Louvroil (15686) | Louvroil | Q2286058 | 780 m | osm |
| Falgueyrat (21770) | Plaisance | Q106272725 | 767 m | osm |
| Brouilla (20909) | Ortaffa | Q82549674 | 767 m | sncf |
| St-Gilles (15188) | Wintzenheim | Q3097534 | 741 m | sncf |
| Bouleternère (20916) | Bouleternère | Q6887677 | 739 m | sncf |
| Nouvel-Avricourt (18766) | Avricourt | Q17462003 | 739 m | sncf |
| Fraisses-Unieux (17759) | Fraisses | Q3096467 | 693 m | sncf |
| Nefiach (20915) | Néfiach | Q26806211 | 675 m | sncf |
| Neuilly (19328) | Isigny-sur-Mer | Q3097186 | 670 m | sncf |
| Port-Vendres (18243) | Port-Vendres | Q2212891 | 665 m | sncf |
| Brias (19048) | Brias | Q97275246 | 641 m | sncf |
| Jacques-Monod-La Demi-Lieue (16229) | Montivilliers | Q24733 | 614 m | sncf |
| Domanova-Rodes (20917) | Bouleternère | Q47188582 | 599 m | sncf |
| Viescamp-sous-Jalles (20019) | Lacapelle-Viescamp | Q3097977 | 598 m | sncf |
| Les Verrières (20188) | Verrières-de-Joux | Q15278923 | 589 m | sncf |
| Lissac (20432) | Lissac | Q3096892 | 576 m | sncf |
| Montesoro (20741) | Bastia | Q3097078 | 576 m | sncf |
| Airvault-Ville (19528) | Airvault | Q82784422 | 551 m | sncf |
| Le Lude (19181) | Le Lude | Q106393792 | 543 m | sncf |
| Montceaux-Vindecy (20230) | Vindecy | Q115129176 | 542 m | sncf |
| Thorée-les-Pins (19179) | Luché-Pringé | Q106393654 | 542 m | sncf |
| Villiers-le-Sec (15004) | Villiers-le-Sec | Q86683659 | 542 m | sncf |
| Prades-Molitg-les-Bains (18258) | Prades | Q1897049 | 541 m | osm |
| St-Juéry (17220) | Saint-Juéry | Q113836012 | 533 m | sncf |
| Nice-St-Augustin (18041) | Nice | Q3097206 | 531 m | correction |
| Sermaise (16681) | Sermaise | Q2390290 | 515 m | sncf |
| Saléchan-Siradan (17235) | Saléchan | Q1897337 | 508 m | correction |
| Uzès (20840) | Uzès | Q3095856 | 508 m | sncf |
| Viessoix (19359) | Valdallière | Q3097979 | 475 m | sncf |
| Dole-la-Bedugue (20289) | Dole | Q3096375 | 475 m | sncf |
| Les Granges-Narboz (20300) | Granges-Narboz | Q15278909 | 474 m | sncf |
| Trélazé (16507) | Trélazé | Q16508084 | 469 m | sncf |
| La Boisse (20371) | La Boisse | Q3096750 | 461 m | sncf |
| Chasse-sur-Rhône (17677) | Chasse-sur-Rhône | Q3096199 | 457 m | osm |
| La Bâthie (17855) | La Bâthie | Q57468981 | 457 m | sncf |
| Lardy (16659) | Lardy | Q1822085 | 456 m | osm |
| Chignin-les-Marches (20488) | Chignin | Q47095408 | 453 m | sncf |
| St-Genouph (16731) | Saint-Genouph | Q3097524 | 451 m | sncf |
| Le Méridien (17648) | Tassin-la-Demi-Lune | Q2672930 | 450 m | sncf |
| Grenade (21729) | Grenade | Q60847657 | 445 m | correction |
| Puyssegur (21804) | Puysségur | Q60847796 | 437 m | osm |
| Montaigut (21203) | Montaigut-sur-Save | Q60847730 | 437 m | osm |
| Cadours (21239) | Cadours | Q60847631 | 435 m | osm |
| Cornebarrieu (21869) | Cornebarrieu | Q56311192 | 433 m | correction |
| Mondonville (21566) | Mondonville | Q60847696 | 433 m | osm |
| Nurieux (17876) | Nurieux-Volognat | Q2945500 | 428 m | sncf |
| St-Pourçain-sur-Sioule (17488) | Saint-Pourçain-sur-Sioule | Q104440477 | 427 m | sncf |
| Riom-ès-Montagnes (20045) | Riom-ès-Montagnes | Q112264716 | 425 m | sncf |
| Auneau-Ville (19629) | Auneau-Bleury-Saint-Symphorien | Q113835959 | 421 m | sncf |
| Taulé-Henvic (19399) | Taulé | Q94944633 | 416 m | sncf |
| La Chapelle-St-Laurent (19537) | La Chapelle-Saint-Laurent | Q65153888 | 416 m | sncf |
| Irigny-Yvours (17666) | Irigny | Q71559362 | 413 m | osm |
| Outreau (19017) | Saint-Étienne-au-Mont | Q3095841 | 409 m | sncf |
| Aytré (16531) | Aytré | Q83381893 | 406 m | sncf |
| Châteauneuf-sur-Loire (16647) | Châteauneuf-sur-Loire | Q61883923 | 405 m | sncf |
| Carvin (19070) | Carvin | Q21294364 | 402 m | sncf |
| Le Muy (18035) | Le Muy | Q80455895 | 401 m | sncf |
| Barbentane-Rognonas (18138) | Barbentane | Q97293084 | 401 m | sncf |
| Palinges (20222) | Palinges | Q114796081 | 400 m | sncf |
| Avesnes-le-Comte (21508) | Avesnes-le-Comte | Q56761839 | 398 m | wikipedia_schema |
| Thuès (18266) | Thuès-Entre-Valls | Q2402394 | 392 m | sncf |
| Rouen-Martainville (16193) | Rouen | Q1809790 | 391 m | sncf |
| Aiserey (17561) | Aiserey | Q24238527 | 386 m | sncf |
| Hondeghem (21784) | Hondeghem | Q60846716 | 382 m | correction |
| Flavy-le-Martel (15722) | Flavy-le-Martel | Q2390007 | 379 m | sncf |
| Badan (17669) | Grigny-sur-Rhône | Q2878570 | 376 m | sncf |
| Allons Argens (21041) | Allons | Q1988162 | 376 m | osm |
| Grenelle-Marchandises (19128) | Paris 15e Arrondissement | Q3096579 | 375 m | sncf |
| Lurs (20605) | Lurs | Q20971048 | 371 m | sncf |
| Besançon-Franche-Comté-TGV (15735) | Les Auxons | Q599069 | 368 m | osm |
| Maulde-Mortagne (15919) | Mortagne-du-Nord | Q2151700 | 367 m | sncf |
| Fenières (20550) | Thoiry | Q114571618 | 360 m | sncf |
| Herme (18293) | Hermé | Q3095767 | 352 m | sncf |
| Barenton-Cohartille (18914) | Barenton-sur-Serre | Q2789090 | 349 m | sncf |
| Sérignac (20846) | Sérignac | Q24938186 | 343 m | sncf |
| Rivoli (20738) | Bastia | Q3097419 | 341 m | sncf |
| La Plage-d'Hyères (20646) | Hyères | Q24350157 | 339 m | sncf |
| Saltatojo (18792) | Furiani | Q3097657 | 339 m | sncf |
| Argent (19612) | Argent-sur-Sauldre | Q123498036 | 333 m | sncf |
| Chamarande (16658) | Chamarande | Q3096176 | 332 m | osm |
| Domfront (16164) | Domfront-en-Champagne | Q3096385 | 329 m | sncf |
| Bois-de-Céné (19433) | Bois-de-Céné | Q28041180 | 326 m | sncf |
| Grandvilliers (15791) | Grandvilliers | Q2211930 | 323 m | osm |
| Bolbec-Ville (19225) | Bolbec | Q16964671 | 320 m | sncf |
| Céret (20911) | Céret | Q81720221 | 320 m | sncf |
| Sisteron (17966) | Sisteron | Q3097754 | 312 m | sncf |
| Palau-del-Vidre (20908) | Palau-del-Vidre | Q81411829 | 312 m | sncf |
| Porte Maillot (17777) | Paris 17e Arrondissement | Q112724435 | 312 m | correction |
| Vicoigne (19060) | Raismes | Q18380052 | 311 m | sncf |
| Sarrance (17310) | Sarrance | Q24053005 | 307 m | sncf |
| Créchy (20237) | Créchy | Q3096329 | 302 m | sncf |

## Commande d'application (à lancer par le coordinateur, non lancée)

Les corrections sont lues par `vff:consolidate-stations`, qui recalcule les gares : à enchaîner dans la chaîne d'application en cours (après la section 40 bis et la 45), puis la suite habituelle :

```
bin/php artisan vff:consolidate-stations --dry-run   # contrôle : 159 lignes « rattachement … : N objet(s) », aucun avertissement « introuvable »
bin/php artisan vff:consolidate-stations
bin/php artisan vff:build-station-eras
```

Contrôle ensuite : `select count(*) from stations where wikidata_id is null` doit passer de 4 221 à environ 4 062.

