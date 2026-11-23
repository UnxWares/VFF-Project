<?php

namespace App\Http\Resources;

use App\Models\LineMaterialPeriod;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Période de circulation d'une série sur une ligne, datée par une source. Seules les périodes sûres
 * (`high`, `medium`) sont servies ; la phrase lue n'est jamais renvoyée (faits seulement).
 *
 * @mixin LineMaterialPeriod
 */
class LineMaterialPeriodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Début de la circulation sur la ligne (`AAAA-MM-JJ`), ou nul si la source ne donne que la fin
             * (« jusqu'en 1990 »). Lire avec `start_precision` : une date à l'année s'écrit au 1er janvier.
             *
             * @example 2009-09-06
             */
            'start_date' => $this->start_date?->toDateString(),
            /**
             * Précision du début : `day`, `month` (date au 1er du mois) ou `year` (date au 1er janvier). Nulle sans début.
             *
             * @example day
             */
            'start_precision' => $this->start_precision,
            /**
             * Fin de la circulation sur la ligne (`AAAA-MM-JJ`), ou nulle si la source ne la donne pas : une fin
             * nulle ne veut pas dire que la série y circule encore.
             *
             * @example null
             */
            'end_date' => $this->end_date?->toDateString(),
            /**
             * Précision de la fin : `day`, `month` ou `year`. Nulle sans fin.
             *
             * @example null
             */
            'end_precision' => $this->end_precision,
            /**
             * Portion parcourue (`from`, `to`, lieux tels qu'écrits), quand le titre de section de l'article la
             * nomme ou que le contributeur l'a donnée ; nulle sinon (la période vaut alors pour la ligne, sans
             * précision de portion).
             *
             * @var array{from: string, to: string}|null
             */
            'section' => $this->section_from !== null ? ['from' => $this->section_from, 'to' => $this->section_to] : null,
            /**
             * Relation assurée pendant cette période, quand la phrase datée cite une relation ou un itinéraire :
             * `label` (« Paris-Montparnasse - Granville »), `from` / `to` (gares d'extrémité, `id` et `name` tels
             * qu'en base, `id` nul si la gare n'a pas été identifiée), `service` (type de service cité ou nul).
             * Nulle sinon.
             *
             * @var array{label: string, from: array{id: int|null, name: string}|null, to: array{id: int|null, name: string}|null, service: string|null}|null
             */
            'relation' => $this->relation,
            /**
             * Portion de la ligne parcourue, placée sur le tracé quand la source la nomme (titre de section,
             * relation citée, portion écrite dans la phrase) : `location` (`stations` : deux gares de la ligne ;
             * `landmarks` : un point remarquable au moins, comme une bifurcation ; `line` : la portion couvre la
             * ligne entière), `from` / `to` (gares d'extrémité : `id` et `name`, `id` nul pour un point
             * remarquable), `pk_start_m` et `pk_end_m` (PK calés des gares ou des points, en mètres, début <= fin).
             * Nulle quand la source ne nomme aucune portion ou qu'elle n'a pas pu être placée sûrement : la période
             * vaut alors pour la ligne, sans précision.
             *
             * @var array{location: string, from: array{id: int|null, name: string}|null, to: array{id: int|null, name: string}|null, pk_start_m: int|null, pk_end_m: int|null}|null
             */
            'portion' => $this->portion(),
            /**
             * Fiabilité : `high` (chaque date au jour ou au mois), `medium` (une date à l'année). Les périodes
             * `low` (date approximative ou phrase ambiguë) ne sont jamais servies.
             *
             * @example high
             */
            'confidence' => $this->confidence,
            /**
             * Où le fait a été lu : `line_article` (article Wikipédia de la ligne, phrase qui cite la série),
             * `material_article` (article de la série, phrase qui cite la ligne) ou `wikidata` (qualificatifs
             * P580 et P582 de la déclaration P3438 de la ligne) ou `contribution` (proposée par un contributeur,
             * avec sa source, et validée par deux relecteurs).
             *
             * @example line_article
             */
            'origin' => $this->origin,
            /**
             * Source citée : `title` (titre de l'article Wikipédia, élément Wikidata de la ligne, ou titre et
             * licence de la source d'une contribution), `revision` (révision lue, `oldid`, nulle pour Wikidata
             * et les contributions), `url` (permalien à citer ; nulle seulement pour une contribution qui cite
             * un ouvrage ou une archive papier par sa référence).
             *
             * @var array{title: string|null, revision: int|null, url: string|null}
             */
            'source' => [
                'title' => $this->source_title,
                'revision' => $this->source_revision,
                'url' => $this->source_url,
            ],
            /**
             * Fournisseur et licence de la source (`provider`, `name`, `license`, `license_status`) : Wikipédia
             * (CC BY-SA 4.0, citer l'article avec le permalien), Wikidata (CC0) ou VFF (contribution validée).
             *
             * @var array{provider: string|null, name: string|null, license: string|null, license_status: string|null}
             */
            'provenance' => app(Provenance::class)->for('line_material_periods', $this->source, $this->source_url),
        ];
    }
}
