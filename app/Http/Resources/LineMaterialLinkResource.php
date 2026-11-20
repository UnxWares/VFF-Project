<?php

namespace App\Http\Resources;

use App\Models\LineMaterial;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lien ligne × série : le matériel a circulé (ou circule) sur la ligne, d'après une source citée.
 *
 * @mixin LineMaterial
 */
class LineMaterialLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /**
             * La ligne (`slug`, `code` SNCF Réseau à 6 chiffres ou nul, `name`), présente dans la fiche d'une série.
             *
             * @var array{slug: string, code: string|null, name: string}
             */
            'line' => $this->whenLoaded('line', fn (): array => [
                'slug' => $this->line->slug,
                'code' => $this->line->code,
                'name' => $this->line->name,
            ]),
            /**
             * La série, présente dans la liste du matériel d'une ligne : champs principaux de la fiche (voir
             * `GET /api/v1/materials/{material}` pour la fiche complète).
             *
             * @var array{slug: string, name: string, type: string, type_label: string, builder: string|null, in_service_from: int|null, in_service_to: int|null, thumb_url: string|null}
             */
            'material' => $this->whenLoaded('material', fn (): array => [
                'slug' => $this->material->slug,
                'name' => $this->material->name,
                'type' => $type = $this->material->getRawOriginal('type'),
                'type_label' => __("materials.type.{$type}", [], 'fr'),
                'builder' => $this->material->builder,
                'in_service_from' => $this->material->in_service_from,
                'in_service_to' => $this->material->in_service_to,
                'thumb_url' => $this->material->image['thumb_url'] ?? null,
            ]),
            /**
             * Provenance du lien : `wikidata` (déclaration « véhicule habituellement utilisé », P3438),
             * `wikipedia` (lien posé dans une section d'article, voir `evidence`), `contrib` (proposé par des
             * contributeurs avec leur source et validé par deux relecteurs, une preuve par contribution), ou nulle
             * pour un lien saisi dans VFF. Un même couple ligne × série peut avoir un lien par provenance.
             *
             * @example wikipedia
             */
            'source' => $this->source,
            /**
             * Permalien de la source la plus forte : révision précise de l'article Wikipédia (`oldid`) ou élément
             * Wikidata. À citer.
             *
             * @example https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris-Est_%C3%A0_Strasbourg-Ville&oldid=226000000
             */
            'source_url' => $this->source_url,
            /**
             * Fiabilité du lien : `high` (Wikidata, ou section « Matériel roulant » de l'article de la ligne),
             * `medium` (autre section : exploitation, desserte de la ligne ; utilisation, services de la série),
             * `low` (à confirmer : article qui décrit plusieurs lignes de VFF à la fois, ou preuves qui portent
             * toutes un doute, voir `evidence[].doubt`). Lien `contrib` : `high` dès qu'une contribution déclare
             * un service régulier, `low` si toutes parlent d'une circulation exceptionnelle, d'essais ou d'un
             * train de musée. La preuve la plus forte décide.
             *
             * @example high
             */
            'confidence' => $this->confidence,
            /**
             * Ère VFF (décennie, 1830 à 2030) à laquelle le lien est rattaché, quand la source le date. Souvent nulle.
             *
             * @example null
             */
            'era_year' => $this->era?->year,
            /**
             * Début de la circulation saisi dans VFF (`AAAA-MM-JJ`) : nul pour les liens importés, dont les dates
             * lues dans les sources sont dans `periods`.
             *
             * @example null
             */
            'from_date' => $this->from_date?->toDateString(),
            /**
             * Fin de la circulation saisie dans VFF (`AAAA-MM-JJ`) : nulle pour les liens importés (voir `periods`).
             *
             * @example null
             */
            'to_date' => $this->to_date?->toDateString(),
            /**
             * Note libre (liens saisis dans VFF).
             *
             * @example null
             */
            'notes' => $this->notes,
            /**
             * Preuves du lien, les plus fortes d'abord (cinq au plus). `kind` : `wikipedia_line_article` (lien vers
             * la série dans l'article de la ligne), `wikipedia_material_article` (lien vers la ligne dans
             * l'article de la série) ou `wikidata`. Pour Wikipédia : `article`, `revid` (révision lue), `section`
             * (titre de la section, « Section > Sous-section »), `quote` (phrase qui porte le lien, extrait sous
             * CC BY-SA 4.0 : citez l'article), `url` (permalien), `shared_article` (vrai si l'article décrit
             * plusieurs lignes), `doubt` (absent si rien ne met la circulation en doute ; sinon la raison :
             * `order` commande ou contrat, `trial` essai ou record, `one_off` circulation exceptionnelle,
             * spéciale ou présentation, `preserved` musée ou préservation, `possible` simple possibilité,
             * `positioning` acheminement vers un atelier, `diverted` coupure ou détournement, `restricted`
             * matériel réservé à une autre ligne, `component` pièce reprise de la série, `footnote` lien posé
             * dans une note ou un passage sans source, `section` section d'essais, de records ou de
             * préservation). Pour Wikidata : `property` (`P3438`), `subject` (élément de la ligne),
             * `from_year` / `to_year` (période déclarée), `url`. Pour une contribution validée (`kind` =
             * `contribution`) : `contribution` (provenance, `contribution:<id>`), `title` (titre ou référence de
             * la source), `source_kind` (`web`, `document` ou `print`), `license` (licence d'un document cité),
             * `quote` (phrase de la source citée par le contributeur, nulle s'il n'en a pas donné), `doubt`
             * (`one_off`, `trial`, `preserved` ou nul selon la circulation déclarée), `url` (adresse de la
             * source, nulle pour un ouvrage papier).
             *
             * @var list<array<string, mixed>>
             */
            'evidence' => array_map(self::evidence(...), $this->evidence ?? []),
            /**
             * Périodes de circulation de la série sur la ligne, datées par une source (début, fin, ou les deux,
             * avec leur précision), de la plus ancienne à la plus récente. Seules les périodes sûres sont servies
             * (`high`, `medium`) ; liste vide quand aucune source ne date la circulation. Voir le détail des
             * champs dans `LineMaterialPeriod`.
             *
             * @var list<array<string, mixed>>
             */
            'periods' => LineMaterialPeriodResource::collection($this->shownPeriods)->resolve($request),
            /**
             * Fournisseur et licence de la source du lien (`provider`, `name`, `license`, `license_status`).
             *
             * @var array{provider: string|null, name: string|null, license: string|null, license_status: string|null}
             */
            'provenance' => app(Provenance::class)->for('line_materials', $this->source, $this->source_url),
        ];
    }

    /**
     * Preuve dans un ordre de clés fixe (jsonb réordonne les clés) ; les clés sans objet pour ce
     * type de preuve sont omises.
     *
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private static function evidence(array $evidence): array
    {
        $keys = ['kind', 'contribution', 'article', 'revid', 'section', 'title', 'source_kind', 'license', 'quote', 'property', 'subject', 'from_year', 'to_year', 'shared_article', 'doubt', 'url'];

        return array_filter(array_replace(array_flip($keys), $evidence), fn (mixed $v, string $k): bool => array_key_exists($k, $evidence), ARRAY_FILTER_USE_BOTH);
    }
}
