<?php

namespace App\Http\Resources;

use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\AccidentSource;
use App\Models\Station;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;

/**
 * Fiche complète d'un accident (`GET /api/v1/accidents/{accident}`) : champs de la liste, plus
 * les lignes rattachées (portion, PK, méthode, confiance), la gare la plus proche, les trains,
 * les articles de Wikipédia, les sources avec leur licence et les faits lus dans chacune, les
 * points à vérifier et la provenance de chaque fait retenu.
 *
 * @mixin Accident
 */
class AccidentDetailResource extends AccidentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $provenance = app(Provenance::class);
        $station = $this->relationLoaded('nearestStation') ? $this->nearestStation : null;

        return [
            ...parent::toArray($request),
            /**
             * Lignes rattachées, la principale d'abord : ligne retenue (`is_primary`) puis lignes au même endroit
             * (tronc commun, jonction) ou citées par une source. Liste vide si aucune. Pour chacune : `line`
             * (`slug`, `code`, `name`), `is_primary`, `era_year` (ère du tracé utilisé), `distance_m` (du lieu au
             * tracé), `pk_m` et `pk_label` (point kilométrique estimé, en mètres et en `km+mmm`), `section`
             * (portion : gares `from` et `to`), `method`, `confidence` (`high`, `medium`, `low`), `score` (0 à 1),
             * `cited_as` et `cited_url` (article de ligne cité par la source, et permalien de cette source).
             *
             * @var list<array{line: array{slug: string, code: string|null, name: string}|null, is_primary: bool, era_year: int|null, distance_m: int|null, pk_m: int|null, pk_label: string|null, section: array{from: array{slug: string, name: string}|null, to: array{slug: string, name: string}|null}, method: list<string>, confidence: string, score: float, cited_as: string|null, cited_url: string|null}>
             */
            'lines' => $this->lineLinks->sortByDesc('is_primary')->values()->map(fn (AccidentLine $l) => [
                /** Ligne VFF : `slug`, `code` (SNCF Réseau, 6 chiffres, nul hors réseau national), `name`. */
                'line' => $l->line !== null ? ['slug' => $l->line->slug, 'code' => $l->line->code, 'name' => $l->line->name] : null,
                'is_primary' => $l->is_primary,
                /** Ère dont le tracé a servi au rattachement (décennie qui encadre la date de l'accident). */
                'era_year' => $l->era_year,
                /** Distance du lieu au tracé de la ligne, en mètres. */
                'distance_m' => $l->distance_m,
                /**
                 * Point kilométrique estimé, en mètres : PK du passage à niveau cité (SNCF Réseau), sinon interpolé
                 * entre les PK des deux gares qui encadrent le lieu. Nul si inconnu.
                 */
                'pk_m' => $l->pk_m,
                /** Le même PK en notation ferroviaire `km+mmm`. */
                'pk_label' => $l->pk_m !== null ? sprintf('%d+%03d', intdiv($l->pk_m, 1000), $l->pk_m % 1000) : null,
                /**
                 * Portion : gares de part et d'autre du lieu le long du tracé de l'époque (`from`, `to`, chacune
                 * `{slug, name}`). `to` nul et `from` renseigné : accident en gare de `from`. Nulles si le lieu
                 * n'est connu qu'à la commune près.
                 */
                'section' => [
                    'from' => $this->stationRef($l->sectionFrom),
                    'to' => $this->stationRef($l->sectionTo),
                ],
                /**
                 * Comment la ligne a été trouvée : `proximity` (tracé de l'époque le plus proche), `wikidata` (ligne
                 * citée par l'élément Wikidata, P81), `wikipedia` (article « Ligne de … » cité par l'article),
                 * `level_crossing` (ligne du passage à niveau cité).
                 *
                 * @var list<string>
                 */
                'method' => explode('+', $l->method),
                /** `high`, `medium` ou `low` (voir `line.confidence`). */
                'confidence' => $l->confidence->value,
                /** Score de 0 à 1 : proximité du tracé et concordance des sources. */
                'score' => $l->score,
                /**
                 * Titre de l'article de ligne de Wikipédia cité par la source (ex. `Ligne de Paris-Lyon à
                 * Marseille-Saint-Charles`). Nul si aucune source ne cite cette ligne.
                 */
                'cited_as' => $l->cited_as,
                /** Permalien de la source qui cite la ligne (révision lue de la page de Wikipédia). Nul sinon. */
                'cited_url' => $l->cited_url,
            ])->all(),
            /**
             * Gare VFF la plus proche du lieu, ouverte à la date de l'accident quand ses dates sont connues (10 km
             * au plus) : `slug`, `name`, `distance_m`. Nulle si aucune.
             *
             * @var array{slug: string, name: string, distance_m: int|null}|null
             */
            'nearest_station' => $station !== null ? $this->stationRef($station) + ['distance_m' => $this->nearest_station_distance_m] : null,
            /**
             * Trains et matériel impliqués, quand les sources les nomment : `label` (nom de service, ex. `TER`,
             * `Intercités`, `RER C`), `category` (`passenger`, `freight`, `works`, `test`), `number` (numéro de
             * train), `material` (matériel, en français : nom de série, ou description tirée de Wikipédia),
             * `operator` (exploitant), `source` (fournisseur qui le cite : `wikidata`, `wikipedia`, `bea-tt`).
             * Liste vide si inconnus.
             *
             * @var list<array{label: ?string, category: ?string, number: ?string, material: ?string, operator: ?string, source: ?string}>
             */
            'trains' => $this->trains ?? [],
            /**
             * Lien qui appuie le bilan retenu (référence Wikidata, permalien Wikipédia, rapport du BEA-TT).
             *
             * @example https://www.bea-tt.developpement-durable.gouv.fr/IMG/pdf/RapportBEATT-2013-007.pdf
             */
            'toll_source_url' => $this->toll_source_url,
            /**
             * Articles de Wikipédia sur l'accident, par langue (`fr`, `en`, `de`) : `title`, `url`, `revid`
             * (révision lue, pour le français seulement). Objet vide si aucun.
             *
             * @var array<string, array{title: string, url: string, revid: int|null}>
             */
            'wikipedia' => (object) ($this->wikipedia ?? []),
            /**
             * Sources consultées, avec leur licence et les faits qu'on y a lus (date, morts, blessés, coordonnées…),
             * pour vérifier le recoupement : `provider` (`wikidata`, `wikipedia`, `bea-tt`), `role` (`data`,
             * `article`, `list` liste d'accidents de Wikipédia, `report` rapport d'enquête, `inventory` bilan des enquêtes), `title`, `url` (permalien de
             * la révision lue pour Wikipédia), `license`, `license_url`, `license_status` (`verified` : licence
             * ouverte prouvée), `revision`, `retrieved_at` (ISO 8601), `facts`. Citez le fournisseur en
             * réutilisant une donnée.
             *
             * @var list<array{provider: string, role: string, title: string, url: string, license: string, license_url: string|null, license_status: string|null, revision: int|null, retrieved_at: string, facts: array<string, mixed>}>
             */
            'sources' => $this->sources->sortBy(fn (AccidentSource $s) => [array_search($s->role, ['report', 'inventory', 'data', 'article', 'list'], true), $s->id])
                ->values()->map(fn (AccidentSource $s) => [
                    /** Fournisseur : `wikidata`, `wikipedia`, `bea-tt`. */
                    'provider' => $s->provider,
                    /** `data` (données structurées), `article` (encyclopédie), `list` (liste d'accidents de Wikipédia, faits seulement), `report` (rapport d'enquête), `inventory` (bilan des enquêtes). */
                    'role' => $s->role,
                    'title' => $s->title,
                    'url' => $s->url,
                    /** Licence de la source, telle qu'établie dans le registre des droits de VFF. */
                    'license' => $s->licence,
                    'license_url' => $s->licence_url,
                    /** `verified` : licence ouverte écrite et prouvée. */
                    'license_status' => $provenance->for('accident_sources', $s->provider)['license_status'],
                    /** Révision lue (oldid Wikipédia, lastrevid Wikidata), nulle sinon. */
                    'revision' => $s->revision,
                    /** Date de consultation (ISO 8601). */
                    'retrieved_at' => $s->retrieved_at->toIso8601String(),
                    /** Faits lus dans cette source. */
                    'facts' => (object) ($s->facts ?? []),
                ])->all(),
            /** Points incertains de la fiche (`review_status` = `to_review`). */
            'review' => [
                'status' => $this->review_status->value,
                /**
                 * Notes : `code` stable (ex. `toll_mismatch`, `location_commune_only`, `line_uncertain`), `label`
                 * (explication en français), `detail` (valeurs en cause).
                 *
                 * @var list<array{code: string, label: string, detail: string}>
                 */
                'notes' => array_map(fn (array $n) => [
                    'code' => $n['code'],
                    'label' => __("accidents.review.{$n['code']}", [], 'fr'),
                    'detail' => $n['detail'],
                ], $this->review_notes ?? []),
                /**
                 * Vérification de la date et du lieu (`vff:verify-accidents`), nulle si la fiche n'a pas été vérifiée :
                 * `rule` (`primary_source` : l'enquête du BEA-TT donne la date et la commune ; `two_sources` : deux
                 * sources de familles différentes ; `wikidata_wikipedia` : Wikidata et Wikipédia seulement, dont l'une
                 * peut être copiée de l'autre ; nul : aucune règle satisfaite), `date` et `place` (valeur retenue, et pour
                 * chacune les sources qui concordent `confirmed_by`, celles qui divergent `disagreeing`, les communes
                 * voisines `nearby` ; chaque source avec `provider`, `role`, `url`, sa valeur et `retrieved_at`, la date
                 * de lecture), `families`, `kind` (nature lue dans une source quand la fiche était « autre »), `aliases`
                 * (autres éléments Wikidata du même accident, gardés avec leurs sources).
                 *
                 * @var array<string, mixed>|null
                 */
                'verification' => $this->verification,
                /** Date de la dernière vérification (ISO 8601), nulle si la fiche n'a pas été vérifiée. */
                'verified_at' => $this->verified_at?->toIso8601String(),
            ],
            /**
             * Fournisseur de chaque fait retenu : `date`, `kind`, `location`, `deaths`, `injuries`, `name`
             * (`vff` : nom construit par VFF). `label` pour une nature tirée du seul nom de l'accident.
             *
             * @var array<string, string>
             */
            'provenance' => (object) ($this->provenance ?? []),
            /** Date du dernier import de la fiche (ISO 8601). */
            'imported_at' => $this->imported_at->toIso8601String(),
        ];
    }

    /** @return array{slug: string, name: string}|null */
    private function stationRef(?Station $station): ?array
    {
        return $station !== null ? ['slug' => $station->slug, 'name' => $station->name] : null;
    }
}
