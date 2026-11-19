# Contributions : types, relecture, publication

Les membres connectés proposent ; deux validateurs différents approuvent ; la deuxième approbation
publie. Un refus porte un motif transmis à l'auteur. Les mêmes règles valent sur le site et par la
mutation GraphQL `submitContribution` (référence : `/docs/graphql`, résumé sur `/api#contribute`).

Code commun : `app/Services/Contributions/ContributionSubmissions.php` (enregistrement et annonce
aux validateurs) et `ContributionReview.php` (approbation, refus, publication selon le type,
réputation, journal d'audit, notifications ; webhook `contribution.applied` par
`AnnounceWebhookEvents`).

| Type (`contributions.type`) | Formulaire | Service | Publication |
| --- | --- | --- | --- |
| `line_segment` | `/contribute/edit-segment` | `ContributionSubmissions::segmentRules()` | attributs des tronçons (`line_segments`) entre deux PK, à une ère |
| `line_trace` | `/contribute/trace` | `TraceContributionService` | `source_traces` et `line_events` (source `contrib`, portion = tracé dessiné) |
| `line_event` | `/contribute/event` | `EventContributions` | une ligne de `line_events` |
| `media` | `/contribute/media` | `MediaContributions` | une ligne de `media` (et le fichier déposé dans le disque public) |

## Événement daté (`line_event`)

Saisie : ligne existante, type (tous ceux de `LineEventType` sauf `accident`, qui a sa propre fiche
vérifiée), date à la précision de la source (`day`, `month`, `year` ; une année seule reste une
année), portion (toute la ligne, entre deux lieux, entre deux PK), précision publiée (obligatoire
pour `other`), source obligatoire : nature (`web`, `document`, `print`), titre ou référence,
adresse (sauf pour un ouvrage papier), licence pour un document numérisé.

À la publication (`EventContributions::apply`), dans la transaction de l'approbation :

- `line_events.source` = `contrib` (contributions validées ; registre des droits : fournisseur
  `vff`), `fingerprint` = `contribution:<id>` (provenance ; rejouer la publication met à jour la
  même ligne), `source_url`, `source_quote` (titre de la source et sa licence), `confidence` =
  `high`, description en français terminée par « (contribution n° <id>) » ;
- `section_from` / `section_to` ou `section_pk_start_m` / `section_pk_end_m`, et
  `section_resolution` = `pending`, `section_geom` vide.

**Aucun recalcul du moteur n'est lancé.** La portion et la carte par décennie suivent par une tâche
à part, à lancer par l'équipe (jamais par un agent sans accord) :

```sh
# 1. Portion de l'événement sur le tracé de la ligne (événements « pending » de cette ligne).
bin/php artisan vff:resolve-event-sections --line=<code ou slug>
# 2. Carte par décennie : au prochain passage du pipeline de l'historique (vff:rebuild-history),
#    qui lit les événements de toutes les sources, dont « contrib ».
```

Les validateurs voient, à côté de la proposition, les événements de la même famille déjà connus
sur la ligne (ouvertures, fermetures, réouvertures…), du plus proche au plus lointain de la date
proposée (`EventContributions::similarEvents`).

## Image (`media`)

Rattachement : une ligne, une gare, ou un événement daté d'une ligne (`LineEvent`). Deux origines :

- **Wikimedia Commons** (`source` = `commons`) : adresse du fichier. `CommonsFileResolver` lit le
  titre, l'auteur, la licence et la miniature par l'API publique de Commons (une requête, User-Agent
  générique de `CommonsClient`, sans reprise longue ; résultat gardé une heure en cache). Refus : format
  autre qu'une image ou un document numérisé, licence inconnue, non libre ou non commerciale. Rien
  n'est téléchargé : l'image reste hébergée par Wikimedia.
- **Fichier déposé** (`source` = `upload`) : JPEG, PNG ou WebP, 10 Mo au plus, chaque côté de 400 à
  12 000 pixels, auteur, licence de la liste fermée `ContentLicense::forMedia()` (domaine public,
  CC0, CC BY 4.0, CC BY-SA 4.0, Licence Ouverte 2.0), attestation du droit de partager, date de
  prise de vue à sa précision, lieu et coordonnées facultatifs.

Contrôles d'un fichier déposé (`ImageSanitizer`, sans GD ni Imagick : lecture de la structure du
fichier) : type lu dans les premiers octets et confirmé par `getimagesizefromstring()`, jamais
d'après le nom ; retrait des métadonnées EXIF (dont la position GPS), XMP, IPTC, commentaires,
blocs de texte PNG et de tout ce qui suit la fin de l'image ; seule l'orientation d'un JPEG est
réécrite dans un EXIF minimal ; relecture finale : un fichier où il resterait une métadonnée est
refusé. Doublons refusés (empreinte SHA-256 du fichier nettoyé, page Commons déjà rattachée).

Fichier en attente : réserve privée `storage/app/contributions/media/` (disque `local`), jamais
servie publiquement. Seuls l'auteur et les validateurs le voient, par
`/contribute/media/{id}/file` (politique `ContributionPolicy::viewPendingFile`, en-têtes
`Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`, CSP `sandbox`). Il est
effacé au refus, au retrait par un administrateur et à la suppression du compte de l'auteur
(événements du modèle `Contribution`).

À la publication (`MediaContributions::apply`) : une ligne de `media` avec `provider_meta.source` =
`contribution:<id>` (et `external_id` = `contribution:<id>` pour un fichier déposé, `file_path` dans
`media/contributions/` du disque public, adresses calculées par l'API). Le fichier est copié vers le
disque public une fois la transaction validée, puis effacé de la réserve privée. Les images de
lignes apparaissent dans la galerie de leur fiche ; celles des gares et des événements sont dans
l'API (`/api/v1/media`), les fiches ne les montrent pas encore.

Pas de miniature générée : l'image PHP du projet n'a pas d'extension graphique. Les galeries
reçoivent le fichier entier (10 Mo au plus).

## Raisons d'échec d'une publication

`PublicationFailure` (montrée au validateur, `extensions.reason` dans l'API) : `no_geometry`,
`line_not_found`, `target_not_found` (ligne, gare ou événement disparu), `already_present` (fichier
Commons rattaché entre-temps à la même fiche), `file_missing` (fichier déposé absent de la réserve
privée), `internal_error`. Rien n'est écrit et l'approbation est annulée.
