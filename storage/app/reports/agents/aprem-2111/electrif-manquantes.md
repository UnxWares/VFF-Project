# electrif-manquantes (23/11/2026)

## Résultat
- Nouveau fichier `database/data/line_event_corrections_electrification_missing.php` : 28 ajouts d'électrifications
  (jour, section nommée, tension, permalien, citation) sur 22 lignes ; test `ElectrificationMissingCorrectionsTest`
  (6 tests ; la borne des trois lignes en conflit recule seulement avec le fichier).
- `line_material_supplements.php` : une période retirée (BB 25500 sur 909000).
- Aucune écriture dans `vff` (lectures en `default_transaction_read_only`, 30 s). Sources : API MediaWiki, User-Agent du
  projet, 5 s entre deux requêtes ; Gallica refuse les requêtes automatiques (Cloudflare), non contourné.

## 1. Les trois conflits de `vff:material-bound-conflicts`
| Ligne | Série | Verdict | Correction |
|---|---|---|---|
| 340000 Paris-Saint-Lazare - Le Havre | BB 17000, 1966 (année) | électrification plus ancienne MANQUANTE | VFF avait 11/01/1966 (Achères - Sotteville) et 20/09/1966 (Paris - Achères) en fiabilité faible et sans section : deux ajouts sectionnés au jour, 25 kV (tableau de la chronologie et chronologie de l'article de la ligne, concordants). La borne passe de 28/02/1967 à 11/01/1966, la série (1966) ne la précède plus. |
| 420000 Paris-Montparnasse - Brest | BB 22200, 1983 (année) | électrification plus ancienne MANQUANTE | Paris-Montparnasse - Le Mans, 08/05/1937, 1 500 V : déjà écrit par electrif-dates (pas encore appliqué, donc conflit toujours visible en base). Ajoutés : Le Mans - Laval 1964 à l'année (26/09/1964 dans l'article, 21/07/1964 dans le tableau : seule l'année est établie) et Laval - Rennes 01/07/1965 (article et tableau concordants). |
| 909000 Grenoble - Montmélian | BB 25500, septembre 1985 | c'est la date du MATÉRIEL qui est fausse | Le 1985 de l'article est l'électrification Lyon - Grenoble (18/02/1985). Grenoble - Gières n'est sous tension qu'en septembre 1991 : article « Ligne Lyon - Grenoble » (rév. 240861730, phrase datée), tableau de la chronologie et infobox de l'article de la ligne, concordants ; seule une phrase de l'article de la ligne dit 1985. Ajout : Grenoble - Gières, septembre 1991 (mois), 25 kV. Période BB 25500 « depuis septembre 1985 » retirée (`dropped_periods`), pas déplacée : aucune source ne date la venue de la série après 1991. |

Aucune date sourcée n'est déplacée. Les événements d'année ou de faible fiabilité de l'import restent en base.

## 2. Cas contradictoires de electrif-dates
Tranchés (deux sources qui concordent) :
- 787000 Vichy - Riom : 25/03/1990 (article de la ligne et tableau). 790000 Riom - Clermont-Ferrand et 785000
  Saint-Germain-des-Fossés - Vichy : même jour pour le même projet Moret - Clermont (tableau + article Vichy - Riom, qui
  date « les installations » du projet) ; les articles de 790000 (22/03) et 785000 (08/03) sont isolés. Les trois jours
  divergents sont cités dans le motif.
- 468000 Bruz - Redon et 470000 Savenay - Gestel : 08/09/1991 (articles des deux lignes concordants) ; le tableau donne
  le 22/08 (mise sous tension technique probable) et l'article de la gare de Quiberon le 29/09 (service d'hiver). 470000
  Gestel - Quimper : 08/09/1992 (tableau, et « un an plus tard » dans l'article).
- 800000 : 25/09/1977 et 23/05/1979 (tableau et infobox : 1977 et 1979 ; le texte de l'article dit 1978).
- 441000 Rennes - Saint-Malo : 08/11/2005 (tableau) ; le 11/12/2005 de l'article est l'arrivée du TGV, pas la mise sous tension
  (pas de contradiction réelle).
Laissés en l'état (désaccord, pas de troisième source) : 880000 (5 juillet 1995 « mise sous tension » de l'article contre
6 juillet du tableau, section de l'article non donnée), 366000 et 390000 (mise sous tension technique contre mise en service
commerciale, déjà au jour), 272000 (9/12/1958 de l'article, déjà en base, contre 25/11), 085000 et 159000 (mois de l'article,
sourcé AFAC, contre 9 août), 420000 Saint-Brieuc - Brest (12/09/1989 en base contre 24/08), 178000 (12/05/1960, deux fois
dans l'article, déjà en base, contre 17/05), 830000 (jours en base à 1 à 3 jours du tableau, source primaire inconnue),
930000 (le tableau écrit « 10 décembre » avec le lien « 10 septembre » ; l'article et la base disent le 10/09/1965).

## 3. Autres jours trouvés (14 ajouts)
074000 Épernay - Reims 18/05/1962 ; 201000 Montmédy - Écouviez 06/05/1955 ; 242000 Creil - Compiègne 16/05/1961 et Compiègne -
Montescourt 26/09/1961 ; 892000 Annemasse - Évian 01/10/1972 ; 903000 Saint-André-le-Gaz - Chambéry 18/02/1985 (article et tableau) ;
593000 Vierzon - Montlouis 11/07/2008 (25 kV, article et tableau) et Montlouis - La Ville-aux-Dames (1 500 V) ; 985000 Orly -
Massy-Verrières 01/04/1947 ; 672000 Ax-les-Thermes - La Tour-de-Carol 29/07/1929 ; 395000 Saint-Cyr - Plaisir-Grignon
01/10/1972 ; 650000 Lourdes - Pau 01/11/1913 (12 kV 16 2/3 Hz) ; 974000 Saint-Cloud - Saint-Nom 15/05/1931 (750 V troisième rail) ;
908000 PK 2,2 - Saint-Marcel 25/05/1994. Méthode : appariement automatique des événements à l'année de la base avec le tableau
(révision 238776991), puis relecture de l'article de la ligne ; écartés : tension hors liste (163000, 15 kV 16,7 Hz),
contradictions (415000 « début 2006 » contre 10/12/2006, 798000 inauguration 5 et service 11/12/2005 contre 15/11), réélectrifications
(900000, 896000, 899000). Il reste d'autres événements à l'année sans jour trouvé (infoboxes sans jour).

## Tests et contrôles
- ElectrificationMissingCorrectionsTest, ElectrificationDatesCorrectionsTest, TypographicDashTest, EventCorrectionFilesTest,
  SourcedCorrectionsInvariantsTest, MaterialDataFilesTest, ElectrificationBoundTest, MaterialFamilyLinksTest : 55 + 6 verts ;
  Pint propre. `vendor` monté sur `/app/vendor`.

## Commande d'application (coordinateur, écrit dans `vff`)
    bin/php artisan vff:apply-event-corrections --dry-run
    bin/php artisan vff:apply-event-corrections
    bin/php artisan vff:import-material-periods --offline --materials=Q842202     # retire la période BB 25500 sur 909000
    bin/php artisan vff:build-era-segments --line=074000,201000,242000,340000,395000,420000,441000,468000,470000,593000,650000,672000,785000,787000,790000,800000,892000,903000,908000,909000,974000,985000
    bin/php artisan vff:build-shared-track   # puis les invariants
    bin/php artisan vff:material-bound-conflicts   # doit être vide (420000 exige que electrif-dates soit appliqué aussi)
Lignes touchées (22) : 074000,201000,242000,340000,395000,420000,441000,468000,470000,593000,650000,672000,785000,787000,790000,800000,892000,903000,908000,909000,974000,985000
Ajouter à cette liste les lignes d'electrif-dates (inchangée, 59 lignes) : le conflit de 420000 ne disparaît que si les deux fichiers sont appliqués.
