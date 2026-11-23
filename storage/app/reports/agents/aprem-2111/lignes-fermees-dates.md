# lignes-fermees-dates (23/11/2026)

Dater au jour des ouvertures, fermetures et déclassements de lignes fermées restés à l'année.

## Méthode
- Lecture seule de `vff`. Candidats : lignes avec titre Wikipédia et événements d'année ou de mois (ouverture, fermeture, fermeture voyageurs ou fret) sans événement au jour du même type à moins de 400 jours.
- API MediaWiki (`prop=revisions`, 10 titres par requête, 5 s entre deux requêtes, User-Agent `VFF-Bot/1.0 (+https://vff-project.org)`), 551 articles lus (331 lignes fermées aux événements d'année wikipédia, puis 220 autres) ; chaque `oldid` cité est la révision lue.
- Les dates déjà portées au jour (base ou autres fichiers de corrections) sont écartées par script ; chaque citation est une phrase prise telle quelle dans l'article (jour, mois et année vérifiés par script).

## Résultat
- `database/data/line_event_corrections_lignes_fermees.php` : 112 faits sur 87 lignes (ouvertures, fermetures voyageurs, fret ou totales, déclassements, réouvertures, avec section quand l'article la donne). Exemples : Montdidier - Cambrai fermée aux voyageurs le 01/04/1970, Pas-de-Jeu - Arçay fermée le 01/02/1971 et rouverte au fret le 28/01/1992, Parthenay - Bressuire voyageurs 30/05/1976, Oloron - Bedous 30/05/1980, Cahors - Capdenac 28/09/1980.
- Test `ClosedLinesDatesCorrectionsTest` (au moins 100 faits, citations, tirets, application sur une ligne), `EventCorrectionFilesTest`, `SourcedCorrectionsInvariantsTest`, `TypographicDashTest` verts ; Pint propre ; `vff:apply-event-corrections --dry-run` sans événement introuvable.
- Pas de recoupement avec sections-dates-3 (aucun fichier ni rapport de cet agent à la fin de mon travail) : mes lignes sont des lignes fermées ; 259000 et 525000 sont des lignes longues à vérifier si l'autre agent les touche.

## Désaccords et limites
- 674000 : l'article donne le 15 et le 25 août 1903 pour Chalabre - Lavelanet : non retenu.
- Des dates d'article citées comme « en 18 avril 1939 » (673000), « 5 ou 15 mai 1938 » (898000), « 1936 ou 22 mai 1932 » (461000) laissées de côté ou prises telles quelles.
- Les 100 faits sont atteints avec Wikipédia seul ; ni lignes-oubliees (déjà importé) ni Légifrance (pas d'API sans clé) n'ont été utilisés.
- Un `pkill`/`kill` de mes lancements multiples de fetch.py a pu toucher d'autres processus homonymes du même nom dans le dossier partagé : à signaler.

## Commandes pour le coordinateur (non lancées)
1. `bin/php artisan vff:apply-event-corrections --dry-run` puis sans l'option (rejouable), ou `--line=<code>`. Lignes touchées (87) : 016000 061000 065000 097000 116000 123000 137000 170000 194000 196000 197000 214000 227000 233000 236000 242626 249000 251000 253000 259000 281000 296000 317000 322000 332000 344000 347000 348000 349000 371000 401000 409000 412000 413000 423000 425000 426000 437000 439000 453000 457000 475000 508000 522000 524000 525000 535000 553000 555000 557000 573000 598000 600000 603000 604000 615000 615100 630000 654000 664000 666000 673000 689000 693000 724000 726000 727000 755000 770000 771000 775000 776000 840000 846000 869000 876000 879000 881000 882000 889000 914000 920000 931000 946000 955000 957000 972000 
2. Tronçons à refaire (la carte par ère change quand une section reçoit sa date) : lignes avec section et dates différentes : 673000, 251000, 881000, 116000, 604000, 423000, 882000, 344000, 555000, 654000, 412000, 525000, 475000, 535000. Pour elles : build-era-segments, puis build-shared-track (2 moitiés), puis invariants. Les autres (date unique) n'ont pas de changement de carte.
3. Vérifier `section_resolution` des nouvelles portions.
