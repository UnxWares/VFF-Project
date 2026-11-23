# Clôture de la feuille de route (agent roadmap-cloture, 23/11/2026)

Inventaire de chaque point `[ ]` et `[~]` de ROADMAP.md, hors phase 9 (3D) et hors points qui demandent le GPU.
Preuves lues dans le dépôt, les journaux des scripts d'application (`aprem-2111/*/deroule.log`, marqueurs `fait`) et
la base `vff` en lecture seule (`statement_timeout` de 30 s, transaction en lecture seule), le 23/11 vers 15 h.

Classes : **(a)** fait mais pas coché ; **(b)** code faisable maintenant sans serveur ni décision ; **(c)** demande le
serveur de production ; **(d)** demande une décision de Baptiste ; **(e)** travail de données continu (seuil de
« fait » proposé) ; **(f)** ajoutée par moi pour être exact : code commité et testé, application en base en file chez
le coordinateur (ni code à écrire, ni décision, ni serveur).

## Tableau des points

| Phase | Point | Classe | Preuve | Action |
|:-|:-|:-|:-|:-|
| 0 | Suivi des erreurs, part hébergement (Kener, GlitchTip) | c | `docs/SUIVI-PRODUCTION.md`, choix de Baptiste du 22/11 (GlitchTip avec Kener) | aucune avant le serveur |
| 4 | Altitude de la voie estimée d'après le terrain | f | code 99d608af et 86ef8956 ; `altitude-appliquer.sh` sans marqueur `fait` ; APREM 70 et 72 | en file (ce soir, 4 à 6 h de requêtes IGN) |
| 4 | Trous du tracé sous le profil | f | 6e3b0e16, 50b1e64b ; `trous-trace-appliquer.sh` non lancé (APREM 75) | en file |
| 4 | Profil : 1 622 lignes sans déclivité mesurée ou sourcée, 60 sans profil | e | 2 630 lignes avec profil en base | seuil : chaque ligne à tracé ouvert a un profil, avec l'erreur de l'altitude estimée ; une déclivité estimée n'est jamais publiée comme mesurée, donc les 1 622 ne se « ferment » pas autrement |
| 4 | Adresses de lignes neutres | **a** | `appliquer-slugs-neutres/deroule.log` (fin 13 h 08, 309 tests verts) ; 0 slug `archeo-` sur 2 690 lignes | coché |
| 4 | JSON-LD « à rendre côté serveur » (point coché, reste écrit) | **a** | `resources/views/partials/share-tags.blade.php` rend `$seo->jsonLd` ; 1c6ee8db, 1735c043 | mention corrigée |
| 6 | Infrastructure SNCF Réseau (voies, radio, quais) | **a** | `apres-sncf-infra/fait` (23/11 2 h 04) ; 1 094 portions de voies, 1 528 de radio, 5 507 quais | coché ; phase 6 passée à « faite » |
| 7 | Temps réel en production (Reverb) | c | unités `deploy/systemd/production/`, `docs/DEPLOIEMENT-SERVICES.md` | aucune avant le serveur |
| 8 | Apache AGE | d | aucun usage dans le code ; le graphe en tables sert tout | question 2 |
| 10 | Affinage sur photos aériennes, `ortho-v2`, fusion de plusieurs dates, poids complets | hors champ (GPU) | `aprem-2111/affinage-ortho.md`, `biais-ortho.md` | non traité |
| 10 | Jonctions, phase « escaliers » | f et d | latérales appliquées (APREM 60) ; fragments de Domfront arrêtés (QUESTIONS, « Exclusions de tracé ») | question 1 |
| 10 | Chronologies par étapes | f (en partie a) | réimport Wikipédia fait (`apres-chronologie/fait`, 2 h 04) ; première série d'ouvertures au jour appliquée (`sections-dates-application/fait`, 13 h 56) ; seconde série non lancée (APREM 72) | texte mis à jour, reste `[~]` |
| 10 | Fusion des sources à 1 m, fusion nationale | f puis e | pilote `fusion-calvados-appliquer.sh` en file (APREM 72) | seuil : passe nationale appliquée, tracés en service à 5 m au plus de la plateforme mesurée, 0 m aux jonctions |
| 10 | Audit des tronçons | f puis e | `vff:audit-segments` fait ; ères passées à repasser | seuil : 21 ères repassées, au moins 90 % des km sûrs en 2030 (83,4 %) ; « invariant par note » : laissé à l'agent de relecture des audits |
| 10 | Audit des gares | f puis e | 634 gares relues, 62 à décider (sections 36 à 45 de `gares_corrections.php`) ; texte du point abîmé (« reRelecture », phrase répétée) | texte réparé ; seuil : gares de plus de 100 000 voyageurs par an toutes relues (les passes vont déjà jusqu'à 50 000) et les 62 cas « à décider » tranchés ; laissé à l'agent des audits de gares |
| 10 | Gallica (presse, API) | e et c | API refusée à la machine de développement | seuil : chaque fonds de `docs/ARCHIVES.md` rattaché ou écarté avec raison ; l'API se réessaie depuis le serveur |
| 10 | Fonds d'archives (FranceArchives, descriptions SNCF) | d et b | FranceArchives derrière une protection anti-robots (`docs/ARCHIVES.md`) | question 9 ; descriptions SNCF : voir « Pour un prochain agent » |
| 11 | Partenariats (AngouLim) | d | soutien en place | « engagement déclaré » retiré du texte (ce n'est pas un lien d'intérêts) ; question 8 |
| 11 | Page de dons, plateforme | d | association UWOSC pas encore créée | question 7 |
| 11 | Programme de partenariat, conférences | d | aucun code | question 8 |
| 11 | Journal : « traductions des articles à venir » (point coché, reste écrit) | **a** | `resources/content/journal/*.en.md` et `*.de.md` pour les deux articles | mention corrigée |
| 11 | Mode hors ligne, fin (fond libre en ligne, nginx, petites cartes) | d, c, et autre agent | `VFF_BASEMAP_ONLINE` désactivé ; petites cartes : agent « fond libre des petites cartes » | question 3 |
| 12 | Messagerie de `vff-project.org` | d puis c | aucun MX | question 4 |
| 12 | Hébergement chez UnxWares | d puis c | domaine sur un serveur Hetzner | question 5 |
| 12 | Planificateur en production | c | `deploy/systemd/production/` | aucune |
| 12 | Relais des vignettes de Commons | c | `VFF_COMMONS_PROXY` désactivé | aucune |
| 12 | Suivi des erreurs (doublon de la phase 0) | c | idem phase 0 | aucune |
| 12 | Sauvegarde de la base de production | c et d | kit `deploy/backup/` essayé | question 6 |
| 13 | Signalisation, faits relus dans Wikipédia | **a** | `suite-application/2-reel.log` : 852 lignes lues, 70 avec des faits, 124 faits ; 164 faits en base | coché |
| 13 | Signalisation, relevé sur les lignes classiques | e | sources libres lues (bb05320f : rien de plus dans les bulletins de l'EPSF ni l'article KVB) | seuil : chaque ligne classique en service a sa mise en service datée ou la mention qu'aucun document libre ne la date ; laissé aux agents signalisation et KVB |
| 13 | Médias, droits et fonds ouverts | e | 114 803 médias ; 4 697 gares sur 9 395 et 623 lignes sur 2 690 illustrées | seuil : chaque gare ouverte aux voyageurs illustrée, chaque fonds repéré intégré ou écarté avec raison ; agent images Commons en cours |
| 13 | Fonds d'images à intégrer, courriels | e et d | courriels E2 à E14 préparés, à envoyer par Baptiste | déjà dans QUESTIONS |
| 13 | Panoramax | f (en partie a) | seuil 0,95 décidé ; 99 photos importées (`suite-application/deroule.log`, 12 h 58) ; passe réseau non lancée | texte mis à jour, reste `[~]` |

## Ce que j'ai fait

- ROADMAP.md : cinq points cochés avec leur preuve (adresses neutres, infrastructure SNCF, signalisation relue dans
  Wikipédia, et les restes périmés de deux points déjà cochés : JSON-LD côté serveur, traductions du journal) ; phases 5
  et 6 marquées « faites » (aucun point ouvert) ; textes des chronologies par étapes, de Panoramax et d'Apache AGE mis
  à jour ; point de l'audit des gares réparé (« reRelecture » et phrase répétée deux fois) ; « engagement déclaré »
  retiré du point AngouLim. Tableau de tête réécrit, chiffres relus en base, « Priorités du moment » remplacées par
  « Ce qui reste » en quatre groupes (base, serveur, décisions, travail continu avec ses seuils).
- README.md : section « En cours et prévu » réécrite dans les mêmes quatre groupes ; retrait de la mention périmée des
  deux liens Z 20500 (ils sont désormais sourcés dans `database/data/material_relation_lines.php`).
- QUESTIONS.md (fichier local, non suivi par git) : liste de 10 décisions en tête, chacune avec sa recommandation.
- Aucun point (b) réalisé : chaque point de code encore ouvert est soit déjà pris par un autre agent (petites cartes,
  invariant par note de l'audit, signalisation, images), soit dépendant d'une source bloquée ou d'une mesure longue.

## Pour un prochain agent (points b de plus de deux heures)

1. **Descriptions des notices des Archives SNCF** (`docs/ARCHIVES.md`, « Suite proposée », point 2) : lire la
   description en plus du titre dans le comparateur de `vff:import-sncf-archives`, avec une règle propre aux mentions en passant (« par », « via », « sur la ligne de »),
   mesurer la précision sur deux tirages de 40 notices avant tout import ; seuil d'acceptation : aucune fausse.
   Vérifier d'abord la licence du champ description (ODbL du jeu entier) et ne servir que titre, type, cote, date et
   lien si la description n'est pas reprise.
2. **Matériel roulant** (phase 13, point coché qui garde un « à faire ») : lire les tableaux des articles Wikipédia
   des séries (affectations par dépôt et par période), puis rattacher les périodes de `line_material_periods` aux
   portions du tracé (gares d'extrémité et PK calés, comme `vff:import-signalling-history`).
3. **Calames et Europeana** (`docs/ARCHIVES.md`, point 3) : sondage des notices ferroviaires françaises par leurs
   interfaces ouvertes (OAI-PMH de Calames, API Europeana avec clé gratuite), précision mesurée avant tout import.

## Commandes en base (à lancer par le coordinateur, pas par moi)

Aucune nouvelle : la file d'APREM.md (points 72 à 75) reste valable dans son ordre.

## Arbre de travail

Arbre détaché dans le scratchpad (`wt-roadmap-cloture`), lien `vendor` et `.env` copié, retirés à la fin.
