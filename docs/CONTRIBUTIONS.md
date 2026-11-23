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
| `line_material` | `/contribute/material` | `LineMaterialContributions` | un lien de `line_materials` (source `contrib`) et, s'il y a des dates, une période de `line_material_periods` |
| `material_series` | `/contribute/series` | `MaterialSeriesContributions` | une série créée ou corrigée dans `materials` |

La source (nature, titre ou référence, adresse sauf pour un ouvrage papier, licence d'un document
numérisé) suit les mêmes règles pour les événements, le matériel et les séries
(`SourceCitation`).

## Plafond quotidien

Chaque compte envoie au plus 30 propositions par jour civil, tous types confondus, site et API
GraphQL ensemble (`ContributionLimits::DAILY`). Au-delà, le site affiche un message traduit
(`proposals.errors.daily_limit`) et la mutation renvoie le code `daily_limit_reached`. Les
validateurs et les administrateurs en sont exemptés.

## Événement daté (`line_event`)

Saisie : ligne existante, type (tous ceux de `LineEventType` sauf `accident`, qui a sa propre fiche
vérifiée), date à la précision de la source (`day`, `month`, `year` ; une année seule reste une
année), portion (toute la ligne, entre deux lieux, entre deux PK), précision publiée (obligatoire
pour `other`), source obligatoire : nature (`web`, `document`, `print`), titre ou référence,
adresse (sauf pour un ouvrage papier), licence pour un document numérisé, et citation courte
facultative (`source_excerpt`, 300 caractères au plus, la phrase qui date le fait) ; note pour les
validateurs, non publiée.

### Correction d'un événement existant

Chaque fait de la chronologie de la fiche de ligne porte un lien « Corriger »
(`/contribute/event?line=<slug>&event=<id>`), sauf un accident (fiche vérifiée à part) et un fait
déjà neutralisé par une correction sourcée. Le formulaire est prérempli avec les valeurs actuelles
(type, date à sa précision, portion) et la ligne est fixée ; la raison de la correction
(`correction_reason`, de 10 à 300 caractères, publiée) est obligatoire. Même champ dans la
mutation (`corrects_event_id`, `correction_reason`).

- Envoi (`EventContributions::normalize`) : l'événement doit appartenir à la ligne et être
  corrigeable (`EventContributions::correctable`) ; la charge utile garde l'événement tel qu'il
  était (`corrects` : identifiant, source, type, date, précision, portion) ; la contribution a
  l'action `update`. Rien n'est écrit dans `line_events` avant les deux approbations.
- Relecture : les validateurs voient l'événement remplacé, la raison, et l'événement tel qu'il est
  maintenant (`corrected_event`, nul s'il a disparu ou a déjà été neutralisé).
- Publication (`EventContributions::apply`, dans la transaction de l'approbation) : l'événement
  corrigé entre comme un nouvel événement `contrib` (règles ci-dessous) et l'événement visé est
  neutralisé comme une neutralisation des fichiers `database/data/line_event_corrections*.php` :
  type `other`, description « <fait>, fait corrigé par la contribution n° <id> (correction sourcée :
  <raison>) » (`applied.corrected_event_ids`).
- Rejeu : les imports réécrivent leurs événements (nouvel identifiant, type d'origine).
  `vff:apply-event-corrections`, lancé par `vff:rebuild-history` avant la résolution des portions,
  rejoue après les fichiers les neutralisations des corrections publiées
  (`EventContributions::replayCorrections`) : l'événement est retrouvé par son identifiant, sinon par
  sa ligne, sa source, son type, sa date et son départ. Une correction dont l'événement a disparu est
  signalée (« événement corrigé introuvable »).

À la publication (`EventContributions::apply`), dans la transaction de l'approbation :

- `line_events.source` = `contrib` (contributions validées), `fingerprint` = `contribution:<id>`
  (provenance ; rejouer la publication met à jour la même ligne), `source_url`, `source_quote`
  (la citation courte si elle est donnée, sinon le titre de la source et sa licence), description en
  français terminée par « (contribution n° <id>) » (« (contribution n° <id>, source : <titre>) »
  quand la citation prend la place du titre) ;
- la confiance suit la source citée (décision du 20/11/2026), comme pour les corrections `manual` :
    - registre des droits : `contrib` est une source composite, le fournisseur est celui du lien cité
      (Wikipédia, lignes-oubliees, Gallica…), `vff` pour un ouvrage ou une archive papier ;
    - `confidence` : `high` pour un ouvrage, une archive, un document numérisé ou le site d'une source
      sûre hors des agrégateurs (Gallica, Légifrance…), `medium` pour Wikipédia, lignes-oubliees,
      SNCF, OSM, OHM ou une page web inconnue, `low` pour Wikidata (`EraTimeline::citedConfidence`) ;
    - rang dans le moteur (`EraTimeline::rankedSource`) : un lien vers une source que le moteur connaît
      prend son rang (un contributeur qui cite Wikipédia pèse comme Wikipédia), une autre page web de
      confiance moyenne devient `contrib-web` (rang de Wikipédia), le reste garde le rang de `contrib` ;
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

Rattachement : une ligne, une gare, un événement daté d'une ligne (`LineEvent`) ou une série de matériel
roulant (`Material`, lien « Proposer une image » de la fiche série, `/contribute/media?material=<slug>`).
L'image publiée entre dans `media` rattachée à la série ; l'API la donne avec `GET /api/v1/media?material=<slug>`
(GraphQL : `media(filter: { material })`). Deux origines :

- **Wikimedia Commons** (`source` = `commons`) : adresse du fichier. `CommonsFileResolver` lit le
  titre, l'auteur, la licence et la miniature par l'API publique de Commons (une requête, User-Agent
  générique de `CommonsClient`, sans reprise longue ; résultat gardé une heure en cache). Refus : format
  autre qu'une image ou un document numérisé, licence inconnue, non libre ou non commerciale. Rien
  n'est téléchargé : l'image reste hébergée par Wikimedia. Le contributeur peut y ajouter une légende en
  anglais (`caption_en`) et en allemand (`caption_de`) : rangées dans `provider_meta.captions` (jamais de
  `fr`), servies par `captions` avec la légende de Commons dans `caption` ; chaque visiteur lit celle de
  sa langue, sinon celle de Commons.
- **Fichier déposé** (`source` = `upload`) : JPEG, PNG ou WebP, 10 Mo au plus, chaque côté de 400 à
  12 000 pixels, auteur, licence de la liste fermée `ContentLicense::forMedia()` (domaine public,
  CC0 1.0, CC BY et CC BY-SA en versions 4.0, 3.0, 2.5 et 2.0, Licence Ouverte 2.0 : toutes
  permettent l'usage commercial et la modification, d'où `commercial_ok` et `derivatives_ok` vrais
  à la publication), choisie en deux temps dans le formulaire (famille, puis version), attestation
  du droit de partager, date de prise de vue à sa précision (rangée aussi dans `media.date_precision`),
  lieu et coordonnées facultatifs, légende facultative en français (`caption`), anglais
  (`caption_en`) et allemand (`caption_de`). La légende principale (`media.caption`) est la
  française, sinon la première donnée ; les trois sont gardées dans `provider_meta.captions` et
  servies par l'API (`captions`, REST et GraphQL) ; la galerie des fiches montre celle de la langue
  de la page.
- **Position GPS d'une photo** : le navigateur lit la position de l'EXIF d'un JPEG (segment APP1),
  d'un PNG (bloc `eXIf`) ou d'un WebP (bloc `EXIF`) choisi (`resources/js/lib/exifPosition.js` ; un
  PNG ou un WebP est lu en entier, car ce bloc peut suivre les pixels) et propose de la recopier dans
  les coordonnées ; rien n'est envoyé sans ce geste. Le fichier, lui, est toujours enregistré sans sa
  position (`ImageSanitizer` retire `eXIf` et `EXIF`, tests `ImageSanitizerTest`).

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
disque public une fois la transaction validée, puis effacé de la réserve privée. L'image apparaît
dans la galerie de sa fiche (ligne, gare, série de matériel), celle d'un événement dans la chronologie
de la ligne, et dans l'API (`/api/v1/media`).

Miniatures : `ThumbnailGenerator` crée après la publication des vignettes WebP de 320 et 1 280 pixels
(`provider_meta.thumbs`) ; sans elles, l'original est servi et `vff:media-thumbnails` les refait.

## Matériel sur une ligne (`line_material`)

Saisie : ligne et série existantes (recherche du site), nature de la circulation (`regular` service
régulier, `occasional` circulation exceptionnelle, `trial` essais, `heritage` train de musée ou
touristique), période de service facultative (début et fin, chacun à sa précision `day`, `month`,
`year`, ou inconnu), portion parcourue facultative (deux lieux, avec une date), source obligatoire,
phrase de la source (`quote`, 10 à 600 caractères comptés après la réduction des espaces), note pour les validateurs. Les fiches ligne
(« Proposer un matériel ») et série (« Proposer une ligne où cette série a circulé ») ouvrent le
formulaire avec la fiche choisie (`?line=<slug>`, `?material=<slug>`).

Règles d'écartement et d'électrification, les mêmes que l'import (`MaterialGaugeRule`,
`MaterialTractionRule`, exceptions relues comprises) :

- un lien compatible (ou que les règles ne jugent pas) passe ;
- un lien incompatible est refusé, avec un message qui donne les chiffres (écartement de la série
  et de la ligne, ou tensions de la série et part de la ligne électrifiée) ;
- il passe quand même si `quote` cite une phrase de la source qui nomme l'écartement (« voie
  normale », « standard gauge », « Meterspur », « 1 435 mm ») ou la tension de la série (« 750 V »,
  « 1,500 V ») : c'est la preuve qu'accepte déjà la règle pour un lien importé. Une phrase tapée par
  un contributeur doit en plus nommer la série (nom ou désignation) et ne pas nier la valeur
  (« jamais », « pas », « sans », « aucun », « never », « not », « nicht », « kein »… à six mots ou
  moins, dans la même phrase) : `ExceptionQuote`. Pour une série électrique, seule une circulation
  régulière lève le conflit. La charge utile garde alors `exception` (`rule`, `reason`), que l'espace de
  validation affiche en tête de la proposition. Exemple : les Z 1200 sur la ligne des Moulineaux
  (972000), électrifiée par troisième rail en 750 V, que VFF ne connaît pas.

Le formulaire contrôle le couple dès que la ligne et la série sont choisies
(`GET /contribute/material/check?line=…&material=…`, 30 par minute) : conflit, lien déjà connu,
exclusion relue de `database/data/line_material_exclusions.php`. Un couple exclu est refusé à l'envoi,
avec le motif : une contribution ne lève pas une exclusion relue, en attendant la décision de
l'éditeur (`QUESTIONS.md`).

Doublons : un lien déjà sûr (`high` ou `medium`, toutes sources) n'est accepté qu'avec une
période ; la même proposition (couple et période) en attente est refusée, comme une période déjà
publiée pour le couple par une contribution (mêmes bornes, précisions et portion). Publier deux
fois la même période ne crée pas de doublon.

À la publication (`LineMaterialContributions::apply`) :

- `line_materials` : un lien `source` = `contrib` par couple ligne × série, à côté des liens
  importés, que l'import ne touche jamais. Chaque contribution y ajoute une preuve `kind` =
  `contribution` (`contribution` = `contribution:<id>`, `title`, `source_kind`, `license`, `quote`,
  `doubt`, `url`). Fiabilité : `high` dès qu'une preuve déclare un service régulier, sinon `low`
  (familles de doute `one_off`, `trial`, `preserved`, comme `LineMaterialLinker`) ;
- `line_material_periods` : la période, empreinte `contribution:<id>`, `origin` = `contribution`,
  `source` = `contrib`, `source_title` (référence et licence), `source_url` (nulle pour un ouvrage
  papier), fiabilité `high` (jour ou mois), `medium` (une année) ou `low` (circulation douteuse,
  jamais montrée) ;
- la règle est relue avec la phrase citée : un lien devenu incompatible entre-temps échoue
  (`incompatible`).

Aucun import n'est relancé. `vff:import-material-periods` garde les périodes des contributions
(`LineMaterialPeriod::CONTRIBUTED_SOURCES`) et remplace toutes les autres, même d'une source qu'il ne
connaît pas. Les fiches ligne et
série montrent une série ou une ligne une seule fois même si plusieurs sources les relient.

## Série de matériel (`material_series`)

Deux modes : `new` (série absente de VFF) et `correction` (`material_slug`, formulaire prérempli
avec les valeurs actuelles : `/contribute/series?material=<slug>`). Champs : nom, catégorie
(`MaterialType`, qui dit la traction), systèmes d'électrification (liste fermée
`MaterialSeriesContributions::ELECTRIFICATION`, mêmes libellés et éléments Wikidata que l'import),
écartements (`gauges`, liste : 1 435, 1 000, 600, 1 668, 1 520 mm et tous ceux que portent déjà les
séries de VFF, 891 mm par exemple ; une série peut en avoir plusieurs), constructeurs, années de mise
en service et de retrait, identifiant Wikidata facultatif, source obligatoire.

- L'identifiant Wikidata est vérifié s'il change (`WikidataItemResolver` : une requête
  `wbgetentities`, User-Agent générique, 10 s, cache d'une heure) : un élément inconnu est refusé,
  comme un élément qui n'est pas une série de matériel roulant (classes P31 et P279 comparées à
  celles de l'import, sinon une requête SPARQL ASK). Une nouvelle série prend l'identifiant final
  d'un élément fusionné ; une correction refuse l'élément fusionné en donnant l'identifiant final, à
  saisir si l'auteur le veut. Wikidata injoignable donne un message clair. Bouton « Vérifier » :
  `GET /contribute/series/wikidata?qid=…` (30 par minute et par compte, `requested` et `item.id`
  différents pour un élément fusionné, erreurs sous `qid`). Envoi d'une série : 10 par minute et par
  compte, compteur commun au site et à GraphQL (`ContributionLimits`).
- Doublons : une série de même identifiant Wikidata ou de même nom (casse et accents ignorés) est
  refusée, avec l'invitation à corriger sa fiche ; une proposition semblable en attente aussi.
- Correction : seuls les champs qui changent sont gardés (`changes` : `before`, `after`) ; un champ
  vide ne change rien, `clear` efface une valeur fausse (`after` nul, montré comme un effacement à la
  relecture ; l'import ne remplit pas de nouveau un champ effacé ainsi).
- Effet sur les liens : `impact` liste les liens de la série qui deviendraient contraires aux règles
  d'écartement ou d'électrification. Le formulaire le montre avant l'envoi
  (`GET /contribute/series/impact`), l'espace de validation le recalcule (`series_context`).

À la publication (`MaterialSeriesContributions::apply`), la série est créée (`source` = `contrib`,
adresse tirée du nom) ou corrigée ; chaque champ écrit prend la provenance `{source: contrib, ref:
contribution:<id>, url}`. `MaterialImporter` ne remplace jamais un champ dont la provenance n'est
pas importée : une valeur validée reste. La série créée devient la cible de la contribution. Une
correction échoue si un champ n'a plus sa valeur « avant » (`stale`), si le retrait viendrait avant
la mise en service (`inconsistent`) ou si elle rendrait des liens de la série contraires aux règles
(`incompatible`, liens cités au validateur). Ce dernier refus est voulu : publier laisserait
`vff:check-invariants` au rouge pour les liens contribués, et le prochain import retirerait en
silence les liens importés ; les liens fautifs se retirent d'abord (exclusion relue, signalement).

## Raisons d'échec d'une publication

`PublicationFailure` (montrée au validateur, `extensions.reason` dans l'API) : `no_geometry`,
`line_not_found`, `target_not_found` (ligne, gare, événement ou série disparu), `already_present` (fichier
Commons rattaché entre-temps à la même fiche, ou série de même nom ou de même identifiant Wikidata
ajoutée entre-temps), `file_missing` (fichier déposé absent de la réserve privée), `incompatible`
(lien de matériel devenu contraire à la règle d'écartement ou d'électrification, ou correction de série
qui en rendrait), `stale` (série changée depuis l'envoi), `inconsistent` (années dans le désordre),
`internal_error`. Les précisions utiles au validateur (champs, liens) suivent le message sur le site et
sont dans `extensions.details` dans l'API.
Rien n'est écrit et l'approbation est annulée.
