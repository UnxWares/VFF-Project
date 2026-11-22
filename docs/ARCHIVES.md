# Fonds d'archives ferroviaires : répertoire et rattachement aux lignes

Ce document recense les fonds d'archives publics utiles à VFF (relevé du 22/11/2026), dit lesquels sont rattachés
aux lignes et comment, et ce qui reste à faire. Il ne recopie aucun inventaire : il donne le lien vers chacun.

## Ce que VFF rattache aujourd'hui

| Source | Commande | Table | Notices | Lignes | API |
|:-|:-|:-|-:|-:|:-|
| Gallica (BnF), documents numérisés en accès libre | `vff:import-gallica` | `line_archive_documents` | 236 | 61 | `GET /api/v1/lines/{line}/archives`, GraphQL `Line.archives`, MCP `line_archives` |
| Archives SNCF, jeu de données `archives-sncf-sardo` | `vff:import-sncf-archives` | `line_archive_records` | 198 | 65 | `GET /api/v1/lines/{line}/archive-records`, GraphQL `Line.archive_records`, MCP `line_archive_records` |

Les deux sources s'affichent dans la section « Dans les archives » de la fiche ligne, chacune sous son intertitre
quand les deux sont présentes.

### Archives SNCF : méthode

Le Service Archives Documentation du groupe SNCF (SARDO) publie sur ressources.data.sncf.com, sous ODbL, le jeu de
données « Archives SNCF » : 12 447 notices (11 243 dossiers d'archives, 1 100 photographies de la Médiathèque SNCF,
102 films), toutes numérisées et consultables sur archives.sncf.com. Interface : plateforme Opendatasoft, export
JSON complet en une requête (`/api/explore/v2.1/catalog/datasets/archives-sncf-sardo/exports/json`). Dernière
modification du jeu : 21/12/2023.

1. **Lecture** (`SardoParser`) : titre, type (`document`, `image`, `video`), thème et sous-thème, service versant,
   boîte, titulaire des droits, date et lien. La description n'est jamais gardée : celle des dossiers de contentieux
   ou de personnel nomme des agents. Les thèmes qui portent sur des personnes (fichiers des cheminots blessés et tués,
   contentieux, questions de personnel) sont écartés en entier : 1 118 notices.
2. **Date** : début et fin du dossier (années) ; à défaut la date de prise de vue, dont le 1er du mois se lit au mois
   et le 1er janvier à l'année (le fonds y range les photographies datées du seul mois ou de la seule année).
3. **Titre préparé** : les noms de compagnies sont retirés (« Compagnie du chemin de fer de Paris à Orléans » ne
   désigne pas la ligne de Paris à Orléans), et le mot « ligne » est répété dans les listes (« située sur les lignes
   du Mans à Mézidon et d'Argentan à Granville » donne deux trajets de lignes).
4. **Jugement** : le comparateur de Gallica (`ArchiveMatcher`), sans changement, seuil 9 sur 11. Une notice va à la
   ligne au meilleur score, à deux lignes au plus à égalité ; au-delà (gare de trois lignes), à aucune.

Résultat du 22/11/2026 : 250 notices nomment deux lieux d'une même ligne, 198 sont retenues sur 65 lignes (189 avant
la lecture des listes de lignes), 2 sont ambiguës. Ce sont surtout des photographies de gares des années 1960 à 1990
(« Esplanade de la gare d'Alençon située sur la ligne du Mans à Mézidon ») et quelques dossiers (reconstruction des
ouvrages après 1944, électrification Nord-Paris, rapports des Ponts et chaussées de 1860-1870).

**Précision mesurée** sur deux tirages de 40 notices retenues, relues à la main (graines 2211 et 5) : 78 justes,
2 douteuses, aucune fausse. Les deux douteuses : une photographie d'un autorail en gare de Lens dans un reportage sur
l'électrification de Paris - Lille (Lens n'est pas sur cette ligne) et la reconstruction du viaduc de l'Aigle « sur la
ligne entre Miramas et Fos-sur-Mer », rattachée à la ligne de Miramas à L'Estaque.

Rappel limité : seul le titre est lu. Les 930 notices dont la DESCRIPTION cite « ligne de X à Y » ne sont pas lues ;
les lire demanderait de séparer les lignes citées en passant de celles dont parle le dossier.

Relance : `bin/php artisan vff:import-sncf-archives --download --dry-run --report=storage/app/data/archives-sncf/candidats.csv`,
puis sans `--dry-run`. Chaque import remplace toutes les notices de la source.

## Répertoire des fonds

Légende de l'interface : **ouverte** (export ou protocole documenté, licence ouverte), **lien** (inventaire en ligne,
pas d'interface utilisable), **sur place**.

### Groupe SNCF

- **Archives SNCF, jeu de données `archives-sncf-sardo`** : notices des dossiers, photographies et films numérisés.
  Licence ODbL (métadonnées) ; documents selon les conditions d'archives.sncf.com. Interface **ouverte** :
  https://ressources.data.sncf.com/explore/dataset/archives-sncf-sardo/ (copie sur data.gouv.fr). **Rattaché.**
- **Centre national des archives historiques (Le Mans)** et **Centre national des archives du personnel (Béziers)**,
  gérés par SARDO : archives des anciennes compagnies et de la SNCF (Le Mans), dossiers de carrière de plus de
  800 000 agents (Béziers). Consultation **sur place**, sur rendez-vous. https://www.groupe-sncf.com/en/group/history-archives/sardo
  Une présentation du SARDO est déposée sur FranceArchives. Pas d'inventaire ouvert hors du jeu de données ci-dessus.
- **Médiathèque SNCF** (photographies, films) : une sélection est dans le jeu de données (titulaire des droits
  « SNCF-MEDIATHEQUE ») ; le reste sur demande.

### Archives publiques

- **Archives nationales du monde du travail (Roubaix)** : fonds de la Compagnie du chemin de fer du Nord, cote
  202 AQ (personnel, exploitation, matériel, Nord-Belge). Inventaires en ligne, identifiants ARK stables :
  https://recherche-anmt.culture.gouv.fr/ ; **lien** (page refusée aux clients automatiques le 22/11/2026). Aucun
  fonds AQ trouvé pour le PLM, le PO, l'Est, l'Ouest ou le Midi : leurs archives sont surtout au Mans et aux Archives
  nationales.
- **Archives nationales (Pierrefitte)** : série F/14 (Travaux publics : concessions, compagnies, matériel fixe) et
  versements du ministère des Transports. Salle des inventaires virtuelle :
  https://www.siv.archives-nationales.culture.gouv.fr/ ; **lien**. Réutilisation gratuite des informations publiques
  avec mention de l'origine (registre `archives-nationales`).
- **Ministère de la Transition écologique, centre d'archives** : répertoires en PDF des versements sur les chemins de
  fer (direction des transports terrestres, service des chemins de fer, concessions, tramways), par numéro de
  versement : https://www.archives.developpement-durable.gouv.fr/ ; **lien** (le site refuse les clients
  automatiques).
- **Archives départementales, série S** (travaux publics et transports ; chemins de fer d'intérêt local et tramways
  souvent en 3 S ou 5 S, la cote varie selon le département). Exemples d'inventaires en ligne : Isère
  (https://archives.isere.fr/), Creuse (https://archives.creuse.fr/), Haute-Vienne (https://archives.haute-vienne.fr/),
  Cantal (https://archives.cantal.fr/). Licence propre à chaque département (registre `archives-departementales`) ;
  **lien**.
- **FranceArchives** (portail national, notices des services d'archives) : https://francearchives.gouv.fr/ ; le site
  renvoie une page « JavaScript et cookies requis » aux clients automatiques, VFF ne la contourne pas. Pas d'entrepôt
  OAI-PMH ni d'API publique documentés. En revanche, l'archive complète des inventaires au format APE-EAD est publiée
  sous Licence Ouverte : https://www.data.gouv.fr/datasets/portail-francearchives-inventaires-des-archives-francaises
  (fichier `francearchives_ape_ead.zip`, mis à jour le 27/08/2025). Mais le 22/11/2026 l'adresse de ce fichier
  (data-dump.francearchives.gouv.fr) renvoyait elle aussi la page anti-robots (260 octets de HTML et un cookie
  `bot_mitigation_cookie`), et les deux ressources de data.gouv.fr ne sont que des renvois (109 et 2 octets) :
  interface **ouverte** sur le papier, inaccessible à un client automatique sans contourner la protection.

### Bibliothèques, musées et revues

- **Gallica (BnF)** : **rattaché** (`vff:import-gallica`).
- **Calames** (manuscrits et archives des bibliothèques de l'enseignement supérieur) : Licence Ouverte, point d'accès
  OAI-PMH simulé https://calames.abes.fr/oai/oai2.aspx ; **ouverte**, non exploré pour le rail.
- **Europeana** : métadonnées CC0, API avec clé gratuite (https://api.europeana.eu/record/v2/search.json), IIIF ;
  **ouverte**, non exploré.
- **École des ponts ParisTech, « Héritage des ponts et chaussées »** : fonds ancien sur l'infrastructure de Gallica ;
  **lien**.
- **Rails et histoire (AHICF)** : Revue d'histoire des chemins de fer sur OpenEdition (https://journals.openedition.org/rhcf/),
  métadonnées CC0 par l'OAI-PMH d'OpenEdition (https://metadata.openedition.org/oai) ; textes sous droits. Pas de
  catalogue de bibliothèque en ligne trouvé.
- **Cité du train (Mulhouse)** : musée du patrimoine ferroviaire SNCF ; pas d'inventaire documentaire en ligne trouvé.

### Associations

Les associations de préservation et d'histoire (FACS, COPEF, AMTUIR, Cercle généalogique des cheminots) publient des
revues et gardent des fonds, mais aucune n'a d'inventaire en ligne ni d'interface trouvés le 22/11/2026.

### Mémoire des cheminots

Mémoire des hommes (ministère des Armées) n'a pas de base propre aux cheminots. Le fichier SNCF des cheminots blessés
et tués pendant la Seconde Guerre mondiale est dans le jeu de données des Archives SNCF, mais VFF ne le rattache pas
aux lignes : il porte sur des personnes.

## Suite proposée

1. **Inventaires de FranceArchives (APE-EAD, Licence Ouverte)** : obtenir l'archive par un téléchargement fait à la
   main dans un navigateur (ou demander à l'équipe de FranceArchives un accès pour les moissonneurs), puis en extraire les
   composants dont l'intitulé parle de chemin de fer (séries S départementales, F/14), et les rattacher avec le même
   comparateur. À mesurer d'abord sur deux ou trois départements : taille du fichier, part des intitulés qui nomment
   deux lieux, précision sur 40 notices relues.
2. **Descriptions des Archives SNCF** : lire la description en plus du titre, avec une règle propre aux mentions en
   passant, puis mesurer la précision avant tout import.
3. **Calames et Europeana** : sondage des notices ferroviaires françaises.
