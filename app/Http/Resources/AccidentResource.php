<?php

namespace App\Http\Resources;

use App\Models\Accident;
use App\Models\AccidentLine;
use App\Support\FrenchDepartments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Accident ou événement grave du rail, tel qu'il figure dans la liste (`GET /api/v1/accidents`).
 *
 * Faits recoupés entre Wikidata (CC0), Wikipédia (faits seulement, CC BY-SA 4.0 pour toute
 * citation) et le bilan des enquêtes du BEA-TT (Licence Ouverte 2.0) ; le détail des sources est
 * donné par la fiche (`/accidents/{accident}`). Un champ nul veut dire qu'aucune source consultée
 * ne le donne : VFF n'estime aucune valeur. Aucune victime n'est nommée.
 *
 * @mixin Accident
 */
class AccidentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $kind = $this->resource->getRawOriginal('kind');
        /** @var AccidentLine|null $primary */
        $primary = $this->relationLoaded('lineLinks') ? $this->lineLinks->firstWhere('is_primary', true) : null;

        return [
            /**
             * Identifiant stable et lisible : date (selon sa précision) puis commune.
             *
             * @example 2013-07-12-bretigny-sur-orge
             */
            'slug' => $this->slug,
            /**
             * Élément Wikidata de l'accident (`Q…`), nul si l'accident n'y figure pas (accident connu par le seul BEA-TT).
             *
             * @example Q13835935
             */
            'wikidata_id' => $this->wikidata_id,
            /**
             * Nom usuel par langue (`fr`, `en`, `de`) : libellé Wikidata, sinon nom construit par VFF (nature et commune).
             *
             * @var array{fr: string, en: string, de: string}
             */
            'name' => $this->name,
            /**
             * Nature : `collision` (entre trains ou avec un obstacle sur la voie), `derailment` (déraillement),
             * `level_crossing` (collision à un passage à niveau), `fire` (incendie), `explosion`,
             * `structure_failure` (rupture d'ouvrage : pont, tunnel, mur, rail), `attack` (attentat),
             * `person_struck` (personnes heurtées hors passage à niveau), `runaway` (dérive d'un train ou de
             * wagons), `incident` (incident grave sans accident : quasi-collision, dépassement de vitesse,
             * immobilisation), `other`. La première circonstance décrite l'emporte (« dérive puis déraillement » : `runaway`).
             *
             * @example derailment
             */
            'kind' => $kind,
            /**
             * Libellé français de la nature.
             *
             * @example Déraillement
             */
            'kind_label' => __("accidents.kinds.{$kind}", [], 'fr'),
            /**
             * Date de l'accident (`AAAA-MM-JJ`), à lire avec `date_precision` : au 1er du mois si seul le mois est
             * connu, au 1er janvier si seule l'année l'est.
             *
             * @example 2013-07-12
             */
            'date' => $this->occurred_on->toDateString(),
            /**
             * Précision de `date` : `day` (date exacte), `month` (mois et année), `year` (année seule).
             *
             * @example day
             */
            'date_precision' => $this->date_precision->value,
            /**
             * Heure locale (`HH:MM`) quand une source la donne, sinon nulle.
             *
             * @example 17:14
             */
            'time' => $this->occurred_at_time !== null ? substr((string) $this->occurred_at_time, 0, 5) : null,
            /** Lieu de l'accident. */
            'place' => [
                /**
                 * Commune actuelle (contours IGN ADMIN EXPRESS) où se trouve le lieu.
                 *
                 * @example Brétigny-sur-Orge
                 */
                'commune' => $this->commune_name,
                /**
                 * Code INSEE de la commune.
                 *
                 * @example 91103
                 */
                'commune_insee' => $this->commune_insee,
                /**
                 * Code INSEE du département actuel (`2A`, `2B` pour la Corse).
                 *
                 * @example 91
                 */
                'department_code' => $this->department_code,
                /**
                 * Nom du département actuel.
                 *
                 * @example Essonne
                 */
                'department' => $this->department_code !== null ? (FrenchDepartments::find($this->department_code)[0] ?? null) : null,
                /**
                 * Latitude WGS 84, en degrés décimaux. Nulle si le lieu est inconnu.
                 *
                 * @example 48.607222
                 */
                'lat' => $this->lat !== null ? round((float) $this->lat, 6) : null,
                /**
                 * Longitude WGS 84, en degrés décimaux. Nulle si le lieu est inconnu.
                 *
                 * @example 2.302222
                 */
                'lng' => $this->lng !== null ? round((float) $this->lng, 6) : null,
                /**
                 * D'où vient la position, du plus sûr au plus vague : `exact` (coordonnées données par la source),
                 * `level_crossing` (passage à niveau cité, position du jeu SNCF Réseau, ODbL), `station` (gare ou
                 * lieu cité), `commune` (centre de la commune : le lieu exact est inconnu). Nulle si le lieu est inconnu.
                 *
                 * @example exact
                 */
                'precision' => $this->location_precision?->value,
            ],
            /**
             * Ligne VFF retenue comme lieu de l'accident (rattachement principal), nulle si aucune. Voir la fiche pour
             * la portion, le PK et les autres lignes possibles.
             */
            'line' => $primary !== null && $primary->line !== null ? [
                /** @example rfn-570000 */
                'slug' => $primary->line->slug,
                /** @example 570000 */
                'code' => $primary->line->code,
                /** @example Ligne de Paris-Austerlitz à Bordeaux-St-Jean */
                'name' => $primary->line->name,
                /**
                 * Confiance du rattachement : `high` (position précise et ligne citée par une source, ou à moins de
                 * 100 m d'une seule ligne), `medium` (à quelques centaines de mètres, ou commune seule mais ligne citée),
                 * `low` (commune seule, ligne la plus proche : à vérifier).
                 *
                 * @example high
                 */
                'confidence' => $primary->confidence->value,
            ] : null,
            /**
             * Nombre de morts, selon `toll_source`. Nul : inconnu des sources consultées (0 veut dire aucun mort).
             *
             * @example 7
             */
            'deaths' => $this->deaths,
            /**
             * Nombre de blessés, selon la même règle. Les sources comptent parfois différemment (blessés graves
             * seulement, ou tous) : voir `review_status` et les faits de chaque source sur la fiche.
             *
             * @example 192
             */
            'injuries' => $this->injuries,
            /**
             * Fournisseur dont vient le bilan retenu : `bea-tt` (enquête officielle, préférée), `wikidata`, `wikipedia`, `manual` (fiche rédigée à la main d'après un document cité).
             *
             * @example bea-tt
             */
            'toll_source' => $this->toll_source,
            /**
             * Résumé rédigé par VFF à partir des faits retenus (jamais recopié d'une source), par langue.
             *
             * @var array{fr: string, en: string, de: string}
             */
            'summary' => $this->summary,
            /**
             * `ok` : la date et le lieu (à la précision que la source donne) sont confirmés par une source primaire
             * (enquête du BEA-TT) ou par deux sources indépendantes, voir `review.verification` de la fiche. `to_review`
             * quand un point reste incertain (une seule source, bilans ou dates qui diffèrent, lieu douteux) : détail
             * dans `review.notes`. Le rattachement à une ligne porte sa propre confiance.
             *
             * @example to_review
             */
            'review_status' => $this->review_status->value,
            /**
             * Adresse de la fiche sur le site.
             *
             * @example https://vff-project.org/accidents/2013-07-12-bretigny-sur-orge
             */
            'url' => url('/accidents/'.$this->slug),
        ];
    }
}
