# Sauvegarde de la base de production

État au 23/11/2026 : le kit est prêt et essayé sur un poste de développement (base de test). Reste à choisir la
destination hors site avec l'hébergement, à installer sur le serveur et à brancher le suivi. Rien n'est installé ni
activé sur le poste de développement : les unités systemd `deploy/systemd/vff-backup.*` et
`vff-restore-test.*` sont pour le serveur seulement.

Contenu du kit (`deploy/backup/`) :

| Fichier | Rôle |
| --- | --- |
| `vff-backup.sh` | sauvegarde quotidienne : dump, contrôles, rotation, envoi chiffré, battement |
| `vff-restore-test.sh` | essai de restauration dans une base neuve et temporaire, avec contrôles |
| `lib.sh` | fonctions communes (garde-fous de suppression, comptes, battement) |
| `backup.env.example` | toutes les variables, avec des valeurs fictives |

## 1. Ce que fait la sauvegarde

Chaque nuit (3 h 15, `vff-backup.timer`, rattrapage au démarrage si le serveur était éteint) :

1. `pg_dump --format=custom --compress=zstd:6` de la base complète, écrit sous un nom provisoire dans
   `$BACKUP_DIR/tmp` (le fichier n'entre dans `daily/` que si tout est bon). Lecture seule : le dump ne bloque
   pas les écritures de l'application.
2. Contrôle 1, `pg_restore --list` (la table des matières se lit). Contrôle 2, lecture complète
   `pg_restore --file=/dev/null` (chaque bloc compressé se décompresse).
3. Empreinte `.sha256` et comptes de lignes des tables clés (`.counts` : `lines`, `stations`, `eras`,
   `line_sections`, `line_segments`, `line_events`, `media`, `users`, réglables par `COUNT_TABLES`).
4. Rotation locale : 7 quotidiennes, 4 hebdomadaires (copie du dimanche), 6 mensuelles (copie du 1er), par liens
   physiques (aucune place en plus). Réglable par `RETENTION_*`.
5. Envoi chiffré hors site (section 3) si `RESTIC_REPOSITORY` est défini.
6. Battement vers le suivi (section 5).

Un seul passage à la fois (verrou `flock`), vérification de la place libre avant de commencer (1,2 fois la taille de
la base), `umask 077` (fichiers lisibles par le seul compte de sauvegarde).

### Garde-fous de suppression (incident du 28/10/2026)

- Aucune suppression récursive, aucun joker, aucune suppression de masse, aucune synchronisation avec suppression.
- Une suppression ne vise qu'un fichier unique dont le chemin est sous `$BACKUP_DIR/` (variable obligatoire,
  absolue, différente de `/`) et dont le nom correspond au motif littéral `vff-AAAAMMJJ-HHMMSS.dump`
  (`sauvegarde_valide` dans `lib.sh`) ; sinon le script s'arrête sans rien supprimer.
- `set -euo pipefail`, `${VAR:?}` sur toutes les variables qui forment un chemin.
- La base temporaire de l'essai de restauration ne peut s'appeler que `vff_restauration_<date>_<pid>` et jamais
  comme la base en service ; seule une base créée par le script lui-même est supprimée.

## 2. Compte PostgreSQL de sauvegarde

Un rôle dédié, sans droit d'écriture sur les données :

```sql
CREATE ROLE vff_backup LOGIN PASSWORD '…' CREATEDB;   -- mot de passe dans /etc/vff/pgpass, mode 600
GRANT pg_read_all_data TO vff_backup;
```

`CREATEDB` ne sert qu'à l'essai de restauration. Les extensions (PostGIS notamment) se recréent à la restauration :
sur un serveur où le rôle n'est pas superutilisateur, créer d'abord les extensions dans une base modèle, ou lancer
l'essai avec un rôle qui peut les créer. Les binaires `pg_dump` et `pg_restore` doivent être de version supérieure ou
égale à celle du serveur (PostgreSQL 18 aujourd'hui) : `PG_BIN_DIR` désigne le bon dossier.

## 3. Envoi chiffré hors site : restic vers un S3 suisse (recommandé)

Même modèle que les sauvegardes du poste de développement : restic chiffre côté client (contenu, noms, arborescence,
AES-256) ; sans le mot de passe du dépôt, l'hébergeur ne lit rien. Un S3 suisse (par exemple Infomaniak Swiss Backup,
déjà utilisé pour le poste) garde les données sous droit suisse, hors du serveur de production et hors du fournisseur
d'hébergement : un incident chez l'hébergeur n'emporte pas les sauvegardes. Ce choix est une recommandation, à
confirmer avec l'hébergement : tout dépôt restic (S3 compatible, SFTP, rclone, stockage de l'hébergeur) fonctionne en
changeant `RESTIC_REPOSITORY`.

Variables (voir `backup.env.example`) :

| Variable | Sens |
| --- | --- |
| `RESTIC_REPOSITORY` | par exemple `s3:https://<point-d-accès-S3>/vff-prod-db` ; vide : pas d'envoi |
| `RESTIC_PASSWORD_FILE` | fichier (mode 600) contenant le mot de passe du dépôt ; une copie dans le coffre d'UnxWares, sinon la perte du fichier rend les sauvegardes illisibles |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | clés S3 du bucket dédié (droit d'écriture ; idéalement sans droit de suppression d'objets si l'hébergeur propose un verrou d'objets) |

Le script initialise le dépôt au premier passage, envoie le dump, son empreinte et ses comptes (étiquette `vff-db`),
applique la même rétention côté restic (`forget --prune`) et relit 5 % des données (`restic check
--read-data-subset=5%`) à chaque passage. Le fichier d'environnement doit être en mode 600 : les scripts refusent
de démarrer sinon. Aucune clé, aucun mot de passe ne figure dans le dépôt git.

Récupérer une sauvegarde depuis restic (par exemple sur une machine neuve) :

```bash
set -a; . /etc/vff/backup.env; set +a
restic snapshots --tag vff-db
restic restore latest --tag vff-db --target /var/backups/vff/reprise
# le dump est sous /var/backups/vff/reprise/var/backups/vff/daily/ ; le copier dans daily/ puis lancer l'essai (section 6)
```

## 4. Journaux WAL : non, pour l'instant (choix argumenté)

Mesure en lecture seule le 23/11/2026 sur la base de développement `vff` (`pg_database_size`) : 8 310 142 655
octets, soit 7,9 Gio, dont l'essentiel en tracés (`line_segments`, `network_edges`, `source_traces`,
`line_profile_points`, `media`). Le dump compressé en fait une fraction (non mesuré sur `vff` pour ne pas la
charger : l'estimation est de 1,5 à 3 Gio ; le premier passage réel donnera la valeur). Choix : **sauvegarde
logique quotidienne seule, sans archivage WAL**.

- Pour : une base de 8 Gio se sauvegarde et se restaure en quelques minutes à quelques dizaines de minutes ; un dump
  est indépendant de la version de PostgreSQL (restauration possible sur une version plus récente), lisible table par
  table, et son essai de restauration est simple, donc réellement fait. L'archivage WAL ajoute un service de plus à
  surveiller (pgBackRest ou WAL-G), un espace d'archive qui croît avec l'activité, et un risque de chaîne WAL
  cassée sans qu'on le sache.
- Contre : la perte de données possible (RPO) est de 24 h. Acceptable aujourd'hui : l'essentiel des données se
  recalcule (moteur d'histoire, imports de sources, recalcul quotidien de l'ère 2030) et les écritures des visiteurs
  (comptes, contributions) sont rares.
- À réévaluer, et passer à pgBackRest ou WAL-G (sauvegarde physique et WAL vers le même S3, restauration à un
  instant donné) si l'un de ces points change : les contributions deviennent nombreuses (RPO de 24 h devenu trop
  coûteux), la base dépasse environ 50 Gio (dump trop long), ou l'hébergement propose déjà la restauration à un
  instant donné (alors s'en servir en plus du dump, qui reste la copie indépendante de l'hébergeur).
- Palier intermédiaire sans WAL : deux dumps par jour (`OnCalendar=*-*-* 03:15,15:15`) ramènent le RPO à 12 h.

## 5. Alerte en cas d'échec : battement vers le suivi

Deux protections complémentaires :

- **Échec détecté** : sur toute erreur, `vff-backup.sh` appelle `HEARTBEAT_URL` suivi de `HEARTBEAT_FAIL_SUFFIX`
  (signal d'échec immédiat). Journal : `journalctl -u vff-backup.service`.
- **Silence** : à la fin d'une sauvegarde réussie, il appelle `HEARTBEAT_URL` seule. Le suivi doit alerter si
  l'appel n'arrive pas dans le délai prévu (26 h pour la sauvegarde, 33 jours pour l'essai de restauration) ; cela
  couvre le timer mort, le serveur éteint ou un script qui ne démarre pas, cas où aucun message d'échec ne part.

Branchement, selon ce qui sera installé (voir `docs/SUIVI-PRODUCTION.md` et `docs/SUIVI-ERREURS.md`) :

- **Kener** : un moniteur de type push (battement) pour la sauvegarde et un autre pour l'essai de restauration, avec
  la fenêtre d'attente ci-dessus ; l'adresse du moniteur va dans `HEARTBEAT_URL` et `RESTORE_HEARTBEAT_URL`. La
  forme exacte de l'adresse et du signal d'échec dépend de la version de Kener installée : la recopier de sa
  documentation dans `HEARTBEAT_URL` et `HEARTBEAT_FAIL_SUFFIX`, sans changer les scripts.
- **GlitchTip** : un moniteur de type heartbeat (adresse de ping donnée par l'interface) ; sans signal d'échec
  distinct, laisser `HEARTBEAT_FAIL_SUFFIX` vide : l'échec se voit alors par l'absence de battement.

Un échec d'envoi du battement est journalisé mais ne fait jamais échouer la sauvegarde. L'adresse contient une clé
secrète : elle reste dans `/etc/vff/backup.env` (mode 600), jamais dans git.

## 6. Procédure de restauration (testée)

Principe : on ne restaure **jamais** par-dessus la base en service. On restaure dans une base neuve, on vérifie,
puis on bascule l'application.

### Essai automatique (mensuel, ou à la demande)

```bash
sudo -u vffbackup /opt/vff/backup/vff-restore-test.sh /etc/vff/backup.env            # dernière quotidienne
sudo -u vffbackup /opt/vff/backup/vff-restore-test.sh /etc/vff/backup.env /var/backups/vff/weekly/vff-AAAAMMJJ-HHMMSS.dump
RESTORE_KEEP=1 …                                                                      # garde la base pour l'examiner
```

Le script : (1) vérifie l'empreinte SHA-256 ; (2) crée `vff_restauration_<date>_<pid>` ; (3) `pg_restore --no-owner
--no-privileges --exit-on-error --jobs=2` ; (4) compte les lignes des tables clés et les compare à celles notées au
moment du dump (écart toléré `COUNT_TOLERANCE_PCT`, 2 % par défaut, car la base vivante bouge pendant le dump) ;
(5) lance `vff:check-invariants` sur la base restaurée (variable `DB_DATABASE` posée pour l'application, jamais le
`.env`) ; puis supprime la base temporaire et envoie le battement de l'essai. Un invariant de données violé est
signalé sans faire échouer l'essai (`INVARIANTS_STRICT=1` pour le rendre bloquant) : ces invariants jugent la
qualité des données, pas la sauvegarde.

### Restauration réelle après sinistre

1. Si le serveur est perdu : installer PostgreSQL 18 et PostGIS, récupérer la sauvegarde (section 3), avoir le mot
   de passe restic (coffre d'UnxWares).
2. Passer l'application en maintenance. Créer une base neuve : `createdb vff_nouvelle`.
3. `pg_restore --dbname=vff_nouvelle --no-owner --exit-on-error --jobs=4 <fichier.dump>`, puis
   `DB_DATABASE=vff_nouvelle php artisan vff:check-invariants` et comparaison des comptes (fichier `.counts`).
4. Bascule : renommer l'ancienne base (`ALTER DATABASE vff RENAME TO vff_avant_<date>`, sans la supprimer), renommer
   `vff_nouvelle` en `vff`, relancer l'application, contrôler `/up/details`. L'ancienne base se supprime plus tard,
   par une décision humaine.
5. Interdit : restaurer avec l'option de nettoyage sur la base en service, et surtout avec l'option de nettoyage
   combinée à `-t` (tables restaurées sans index ni clés, incident du 19/11/2026). Pour récupérer une seule table,
   restaurer toute l'archive dans une base neuve puis copier la table de là.

### Essai réalisé le 23/11/2026 (poste de développement)

Base de test `vff_jour_sauvegarde_test` (migrations complètes, 5 lignes, 8 gares, 3 comptes, aucune écriture dans
`vff`) : sauvegarde (contrôles 1 et 2, 557 Kio, envoi restic vers un dépôt local d'essai, `restic check` sans
erreur), restauration dans `vff_restauration_*`, comptes identiques, 30 invariants exécutés (un violé : « aucune
ère courante », normal sur une base sans ère), base temporaire supprimée, code de sortie 0. Essai négatif : un
compte volontairement faux dans le fichier `.counts` fait échouer l'essai (code 1) avec suppression de la base
temporaire. Le dump de `vff` elle-même (7,9 Gio) n'a pas été lancé sur le poste : durée et taille réelles à relever
au premier passage sur le serveur.

## 7. Installation sur le serveur (à faire avec l'hébergement)

```bash
install -d -o root -g root /opt/vff/backup /etc/vff
install -m 755 deploy/backup/*.sh /opt/vff/backup/
install -m 600 -o vffbackup deploy/backup/backup.env.example /etc/vff/backup.env   # puis remplir
install -d -o vffbackup -m 700 /var/backups/vff
cp deploy/systemd/vff-backup.* deploy/systemd/vff-restore-test.* /etc/systemd/system/
systemctl daemon-reload
systemctl start vff-backup.service        # premier passage à la main : relever durée et taille
systemctl enable --now vff-backup.timer vff-restore-test.timer
```

Compte `vffbackup` : utilisateur système sans connexion ; pour l'essai de restauration, il lit le dossier de
l'application (`APP_DIR`) et lance `ARTISAN_CMD`.

## 8. Reste à décider

- Destination hors site : S3 suisse (recommandé), ou service de l'hébergeur ; bucket dédié, clés d'écriture seule.
- Si l'hébergeur propose sa propre sauvegarde ou une restauration à un instant donné : s'en servir en plus du dump.
- Moniteurs de battement dans Kener ou GlitchTip une fois installés.
- Conservation : les sauvegardes contiennent des comptes ; un compte effacé disparaît des sauvegardes au plus tard à
  la fin de la rétention (6 mois), à mentionner dans la politique de confidentialité.
