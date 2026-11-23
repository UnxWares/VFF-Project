# Matériel préservé, suite (agent preserve-suite, 23/11/2026)

## Livré

- `database/data/preserved_rolling_stock_complement.php` : complément de même forme que `preserved_rolling_stock.php`, qu'il ne modifie pas.
- `PreservedRollingStock::merge()` (service) : fusionne le complément au fichier de base. Un exemplaire déjà connu (même série, même numéro)
  ne reçoit que ce qui lui manquait (lieu, texte du lieu, état, protection, sources) ; jamais de remplacement d'un fait connu. Un exemplaire
  nouveau est ajouté, un lieu nouveau aussi. Aucune écriture en base, aucune migration, aucune route nouvelle (contrat d'API inchangé).
- Tests : 5 nouveaux dans `tests/Unit/Materials/PreservedRollingStockTest.php` (au moins 150 exemplaires touchés et 40 séries qui n'en
  avaient aucun, rien d'écrasé, fusion sur un cas fabriqué, règles communes dont protection = notice Palissy citée et pas de tiret long,
  exemplaires vérifiés à la main).

## Mesures

- 184 entrées dans le complément : 106 exemplaires nouveaux, 78 exemplaires existants complétés (lieu, état ou protection manquants).
- 97 séries touchées, dont 47 qui n'avaient aucun exemplaire (BB 1500, BB 1280, BB 71000, BB 67300, 231 H, 242 AT, 150 P, 150 X, C 61000, BB 62400...).
- Cité du train : 32 exemplaires nouveaux rattachés à leur série ; 2 lieux nouveaux pour ses sites (annexe de la remise de Mohon, réserve de
  Châlons-en-Champagne).
- 9 lieux nouveaux (lignes touristiques et associations), les autres exemplaires sont rattachés aux 217 lieux déjà connus ; les lieux à l'étranger
  restent des textes (`where`), jamais des lieux fichés.
- Protection : références Palissy lues dans les articles (PM...), date et nature reprises du fichier palissy.csv du 13/11/2026 (copie de travail de la
  veille) ; une protection n'est posée que si la notice est citée dans les sources de l'exemplaire.

## Sources et méthode

1. Wikipédia fr (CC BY-SA 4.0), API MediaWiki, User-Agent du projet, 5 s entre deux requêtes (3 requêtes en tout) :
   - « Matériel moteur préservé de la SNCF » (version 241069362, 721 puces lues) : section = série, numéro, « préservé par X », état dit
     (en état de marche, exposé en monument, en restauration, garé), protection `{{Classé MH}}` et `{{Base Palissy}}`. Les exemplaires
     « anciennement préservés », ferraillés, vendus ou barrés sont écartés (102 puces). Le rattachement à la série passe par le code et
     l'intervalle du nom de la série (matcher du premier lot), avec repli sur l'intitulé de la section quand le code est celui de la série.
   - « Cité du train » (version 241565103), tableaux des collections : le lien de chaque ligne vers l'article de la série donne la série
     (c'est ce qui permet d'atteindre les locomotives à vapeur des anciennes compagnies), la dernière colonne donne le site (Mulhouse, Mohon,
     réserve, prêt à une association). L'état n'est jamais déduit du site (« site d'exposition » n'est pas « exposé »).
2. Palissy (POP, ministère de la Culture, Licence Ouverte 2.0) : dates et nature des protections, depuis le fichier déjà téléchargé la veille
   (l'API de recherche POP ne répond pas aux appels anonymes que j'ai tentés : 404 sur les trois formes essayées ; un seul téléchargement du
   jeu ouvert a suffi, rien de nouveau n'a été aspiré aujourd'hui).
3. Wikidata : aucun appel nouveau (le premier lot l'avait déjà lu, il est maigre).

Sans exploration d'autres sites. Rien recopié au-delà du numéro, du lieu, d'un état et de la protection.

## Vérifications

- Dix exemplaires du complément contrôlés à la main sur les articles (test `test_complement_units_checked_by_hand`) : BB 63013 (Cité du
  train), BB 63069 (Cotentin, en état de marche), BB 63165 (Train du pays Cathare, en état de marche), CC 65001, 231 H 8, BB 12125 (Cité du
  train), BB 12114 (AJECTA), X 2816 (Haut-Doubs, en état de marche).
- Erreurs trouvées et corrigées en route : « préservée » lu comme « réserve » (garé) ; une association déjà fichée (AAATV) recréée sous un autre
  nom (« AAATV CVL ») ; lieux fabriqués à partir d'une phrase (« Cité du Train en version Plathée ») ; phrases tronquées dans le lieu (BB 66014).
- PHPUnit ciblé (291 tests, 1 processus), `TypographicDashTest`, Pint, PHPStan sur le service : verts, baseline intacte.

## Limites

- Les locomotives à vapeur numérotées par la SNCF (140 C, 230 B, 141 TA...) de l'article des matériels moteurs ne sont pas rattachées : nos séries à
  vapeur portent le nom de la compagnie d'origine, et la correspondance numéro SNCF vers numéro d'origine n'est pas dans l'article. Seules celles
  que la Cité du train relie à un article de série (lien) sont reprises.
- Non repris : tout le matériel remorqué (voitures, wagons, engins d'infrastructure de la Cité du train), les locotracteurs sans site, les rames TGV
  (déjà saisies à la main), les Y et DU dont le numéro est masqué (« Y 21** »).
- Lieux : « préservé par X » dit qui conserve, pas toujours le site exact ; le texte de la fiche garde « d'après nos sources ».

## Commande d'application pour le coordinateur

Aucune : le complément est un fichier versionné, lu à chaque requête, sans table ni migration. Après déploiement du commit, rien à lancer en
base ; un `vff:` n'est pas concerné.

Scripts rejouables : `storage/app/reports/agents/aprem-2111/preserve-suite/` (parse1, m1, venues1, build1, cdt, final, genphp ; chemins de travail
dans le dossier de session à adapter).
