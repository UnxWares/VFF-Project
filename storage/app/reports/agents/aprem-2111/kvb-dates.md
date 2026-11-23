# Protection des trains (KVB, crocodile, DAAT, TVM, ETCS) : relevé du 23/11/2026

## Résultat : 2 faits nouveaux sur 40 visés

La source libre est tarie pour le KVB par ligne : la cible de 40 n'est pas atteignable sans inventer.

## Comment VFF range la protection des trains

- `line_signalling_events` : `system` = KVB, TVM, TVM-300, TVM-430, ETCS, ETCS_L1, ETCS_L2 ; `role` = `speed_control` (KVB),
  `cab` (TVM, ETCS), distinct du `block` (BAL, BAPR, BM, CT) et de `remote_control` (CC). Le KVB est donc bien un contrôle de vitesse,
  jamais un cantonnement ; `SignallingHistoryState` remplace un fait seulement dans son groupe (KVB, TVM et ETCS ont chacun le leur).
- Les trois fichiers (compléments Wikipédia, sources, corrections) acceptent déjà KVB, TVM et ETCS : aucun changement de code.
- Le crocodile (répétition ponctuelle des signaux) et le DAAT n'ont PAS de code : il faudrait de nouveaux codes (RPS, DAAT) et le rôle `speed_control`.
  Non créés : aucun fait par ligne ne les date.
- `FIRST_YEAR['KVB'] = 1980`.

## Faits ajoutés (compléments Wikipédia, citation vérifiée dans l'article en cache, 61 faits, 0 erreur)

| Ligne | Système | Date | Portion | Article |
|---|---|---|---|---|
| 001000 Paris-Est - Mulhouse | ETCS_L1 | 2000 | Marles-en-Brie - Tournan (expérimentation SNCF) | Système européen de contrôle des trains |
| 115000 Strasbourg - Saint-Louis | ETCS_L1 | 2022 | ligne entière | Ligne de Strasbourg-Ville à Saint-Louis |

## Relevé fait

- Cache : 94 articles citant KVB/crocodile/DAAT, puis 157 articles de plus (15 recherches plein texte frwiki paginées, 5 s, User-Agent du projet) dont « Contrôle de vitesse par balises », « Crocodile (signalisation ferroviaire) », « Transmission voie-machine », « Système européen de contrôle des trains ». Phrases à année relues avec `kvb-dates/mine.php`.
- « Contrôle de vitesse par balises » ne date que la décision (accident d'Argenton-sur-Creuse 1985, mise en oeuvre « au tout début des années 1990 », accident de Melun du 17/10/1991) : pas de date par ligne, pas de fait.
- EPSF : les pages de la liste des bulletins officiels (robots.txt respecté, 5 s) ont été lues ; les 32 décisions de mise en service de lignes ou de contrôle-commande sont déjà dans `signalling_history_sources.php` (ETCS L1 : Uckange, Longuyon, Hautmont, Belfort - Delle, Pontarlier, La Plaine, Redessan, BPL ; LGV SEA, BPL, Est). Rien de neuf.
- Le KVB n'est daté nulle part par ligne (les articles disent « équipée du KVB » sans date).

## Refusés ou en suspens

- Mantes - Cherbourg et Corbeil - Montereau : « équipée du BAL et du KVB » dans une phrase qui date l'électrification, pas le KVB.
- Blanc-Argent : DAAT en service le 18/06/2015 (article X 240) ou « juin 2015 » (article de la ligne), les deux divergent sur le jour ; pas de code DAAT. À reprendre si un code est créé.
- Besançon - Le Locle : KVB « jusqu'en 2029 » puis ETCS (futur) ; Marseille - Vintimille : ETCS N1 prévu 2026 à 2031 (futur) ; corridors « à l'horizon 2018 » (plan dépassé).
- Crocodile « 1925, unification en France » : national, sans ligne. Luxembourg, Belgique : hors périmètre.
- Pistes non lues : bilan LOTI du KVB (RFF 2008, 48 p.), rapports ART, fiche SAM S707 de l'EPSF.

## À nettoyer par l'utilisateur

Le dossier `storage/app/reports/agents/aprem-2111/kvb-dates/epsf/` (pages HTML de l'EPSF, non commitées) n'a pas pu être supprimé : le crochet interdit toute suppression récursive.

## Commande d'application (à lancer par le coordinateur)

```
bin/php artisan vff:import-signalling-history --dry-run
bin/php artisan vff:import-signalling-history
```
