# Sections nommées (`line_sections`) - note de cadrage (#162)

## Ce qu'est `line_sections`

La table `line_sections` stocke les **sections nommées** d'une ligne : des
appellations commerciales ou historiques (« LGV Sud-Est », « LGV Méditerranée »,
« LGV Est phase 1 »…) qui ne sont **pas** des lignes SNCF distinctes mais qui
méritent d'être affichées sur la fiche ligne, bornées par un PK de début/fin.

Schéma (migration `2026_05_12_170000_create_line_sections_table.php`) :

| colonne | type | rôle |
|---|---|---|
| `line_id` | FK `lines` | ligne porteuse |
| `name` | string | nom de la section |
| `alt_names` | json | noms alternatifs (ex. `["LN1", "LGV Paris-Lyon"]`) |
| `pk_start_m` / `pk_end_m` | int (mètres) | bornes kilométriques |
| `opening_year_from` / `opening_year_to` | smallint | période d'ouverture |
| `description` | text | texte libre |
| `source_url` | string | source (Wikipedia, archive…) |

La fiche ligne (`LineController@show`) lit ces sections triées par `pk_start_m`
et les passe à la vue (clé `sections`).

## Limitation actuelle : couverture LGV uniquement

`line_sections` n'est aujourd'hui peuplée **que pour les LGV**, via
`Database\Seeders\LineSectionsSeeder` (données curées à la main : LGV PLM,
LGV Atlantique, LGV Est). Les **lignes classiques** n'ont donc aucune section.

### Conséquence pour la dérivation de statut « par section »

Toute logique qui voudrait dériver un statut (ou tout autre attribut) **par
section** pour les lignes classiques est aujourd'hui impossible : il n'existe pas
de découpage en sections pour ces lignes. Concrètement, **peupler `line_sections`
au-delà des LGV est un prérequis** à toute dérivation par section sur le réseau
classique.

Ce travail est de la **curation manuelle** (sources historiques, PK exacts) et
n'est pas automatisable de façon fiable à partir des données SNCF Open Data
seules. Il doit donc être amorcé ligne par ligne.

> NB : la classification de statut **par segment** (table `line_segments`,
> enum `SegmentStatus`, `LineClassifier`) reste inchangée et indépendante de
> `line_sections`. Cette note ne concerne QUE l'enrichissement éditorial en
> sections nommées.

## Amorcer la curation : commande squelette

Une commande **additive** permet d'ajouter **une** section à la fois à une ligne
(classique ou non), sans seed en masse et sans toucher aux segments / géométries :

```bash
php artisan vff:seed-line-sections \
    --code="420000" \
    --name="Section Tours - Vierzon" \
    --pk-start=0 \
    --pk-end=90000 \
    --alt-names="Ligne du Berry" \
    --opening-from=1847 \
    --opening-to=1847 \
    --description="Tronçon historique…" \
    --source-url="https://fr.wikipedia.org/wiki/…"
```

Options requises : `--code`, `--name`, `--pk-start`, `--pk-end` (PK en **mètres**).
La commande est idempotente sur le triplet (ligne, nom, PK) : relancer une
insertion identique ne crée pas de doublon.

Pour les LGV, continuer à utiliser le seeder dédié
(`Database\Seeders\LineSectionsSeeder`) qui regroupe les données curées.
