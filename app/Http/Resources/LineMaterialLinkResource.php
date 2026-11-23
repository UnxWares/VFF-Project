<?php

namespace App\Http\Resources;

use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\Api\Provenance;
use App\Services\Materials\LineMaterialEnd;
use App\Services\Materials\LineMaterialFacts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lien ligne × série : le matériel a circulé (ou circule) sur la ligne, d'après une source citée.
 *
 * @mixin LineMaterial
 */
class LineMaterialLinkResource extends JsonResource
{
    /** Attribut de la requête qui porte les faits de la ligne listée (LineMaterialFacts), posé par la liste d'une ligne. */
    public const FACTS = 'vff.line_material_facts';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $facts = $request->attributes->get(self::FACTS);
        $facts = $facts instanceof LineMaterialFacts ? $facts : null;

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
             * `GET /api/v1/materials/{material}` pour la fiche complète). `thumb_url` : miniature de 500 px ;
             * `image` : vignette de 330 px au plus avec ses dimensions et son crédit (`file`, `author`, `license`,
             * `license_url`, `rights_status`, `source_url` à citer, `provider`), nulle sans photo.
             *
             * @var array{slug: string, name: string, type: string, type_label: string, builder: string|null, in_service_from: int|null, in_service_to: int|null, thumb_url: string|null, image: array<string, mixed>|null}
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
                'image' => Material::thumbnail($this->material->image),
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
             * plusieurs lignes), `route` (preuve tirée d'une relation ou d'un itinéraire cité par l'article de la
             * série, voir `relations` : `kind`, `label`, `from`, `to`, `service`, `title`, `source` (article de
             * l'itinéraire), `stops` (arrêts de la relation, `id` et `name`), `era_year` (époque du graphe qui a
             * donné le plus court chemin)), `doubt` (absent si rien ne met la circulation en doute ; sinon la raison :
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
             * Relations que la série assurait sur la ligne, lues dans l'article Wikipédia de la série (relation
             * entre deux gares, ou itinéraire comme « Ligne de Paris à Granville ») : `kind` (`relation` ou
             * `itinerary`), `label` (« Paris-Montparnasse - Granville »), `from` et `to` (gares d'extrémité,
             * `id` et `name` tels qu'en base, `id` nul si la gare n'a pas été identifiée), `service` (type de
             * service cité : `TER`, `Intercités`, `TGV`, `grandes lignes`, `express`, `omnibus`, `fret`, ou
             * nul), `title` (article de l'itinéraire, nul pour une relation), `periods` (périodes montrées qui
             * datent cette relation : `start_date`, `start_precision`, `end_date`, `end_precision`), `notes` (faits
             * sourcés sur la relation, hors Wikipédia : `kind` `fuel`, `fuel` comme `B100`, `feedstock` comme
             * `rapeseed`, `since` et `since_precision`, `reported` et `reported_precision` (date du dernier bilan
             * connu, pas une fin), `sources` avec `title`, `publisher`, `date`, `url`, `provider`, `license`,
             * `license_status`). Liste vide quand aucune preuve ne cite de relation. Les preuves qui portent un
             * doute ne sont pas reprises.
             *
             * @var list<array<string, mixed>>
             */
            'relations' => $this->resource->relations($this->resource->relationLoaded('shownPeriods') ? $this->shownPeriods : null),
            /**
             * Fin de circulation, telle que les sources la connaissent (jamais déduite) : `kind` `line` (fin datée
             * sur la ligne par la dernière période de ce lien, `year` et sa source), `series_in_service` (pas de
             * fin sur la ligne, la série est encore en service : on ne sait pas si elle roule encore ici),
             * `series_withdrawn` (pas de fin sur la ligne, la série est retirée : `year` est celle du retrait de
             * la série, nulle si seul l'état est connu) ou `unknown`. `source` (`wikipedia`, `wikidata`) et
             * `source_url` (permalien) disent d'où vient l'année. `since` : début de la période ouverte sur la
             * ligne (début daté sans fin), nul sinon. Présent quand la série est chargée.
             *
             * @var array{kind: string, year: int|null, since: int|null, source: string|null, source_url: string|null}|null
             */
            'end' => $this->resource->relationLoaded('material') && $this->resource->relationLoaded('shownPeriods')
                ? LineMaterialEnd::of($this->material, $this->shownPeriods->map(fn ($p): array => $p->toSheet())->values()->all())
                : null,
            /**
             * Liste d'une ligne seulement, comme la fiche de la ligne (calculé sur tous les liens de la série, quels
             * que soient les filtres) : raison du doute quand toutes les preuves de la série sur la ligne en portent
             * un et qu'aucune période n'est datée (`trial`, `one_off`, `order`, `positioning`, `preserved`,
             * `possible`, `diverted`, `restricted`, `component`, `section`, `other`), nulle pour une série qui a
             * circulé. La fiche range ces séries à part (« citées, sans service attesté »).
             *
             * @example null
             */
            'link_doubt' => $this->when($facts !== null, fn (): ?string => $facts?->doubt($this->material_id)),
            /**
             * Liste d'une ligne seulement : borne « pas avant l'électrification » d'une série électrique, comme la
             * fiche de la ligne : `reason` (`electrification`), `date` (`AAAA-MM-JJ`), `precision`, `section`
             * (section électrifiée la première, nulle pour la ligne entière), `conflict` (vrai quand une période
             * sourcée de la série commence avant la borne : la période garde sa date, et une électrification plus
             * ancienne manque probablement dans VFF ; la borne n'est alors pas une date sûre). Nulle sans borne.
             * Les périodes (`periods`) sont toutes sourcées : elles ne sont jamais déplacées par la borne.
             *
             * @var array{reason: string, date: string, precision: string, section: string|null, conflict: bool}|null
             */
            'earliest_on_line' => $this->when($facts !== null, fn (): ?array => $facts?->earliest($this->material_id)),
            /**
             * Liste d'une ligne seulement : famille dont la série est une version (`slug`, `name`), quand la famille
             * est aussi liée à la ligne, nulle sinon.
             *
             * @var array{slug: string, name: string}|null
             */
            'family' => $this->when($facts !== null, fn (): ?array => $facts?->family($this->material_id)),
            /**
             * Liste d'une ligne seulement : vrai pour une famille que la fiche de la ligne ne montre pas, parce
             * qu'une de ses versions liées à la ligne a circulé (la fiche montre les versions avec le nom de la
             * famille).
             *
             * @example false
             */
            'folded_into_versions' => $this->when($facts !== null, fn (): bool => (bool) $facts?->folded($this->material_id)),
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
        $keys = ['kind', 'contribution', 'article', 'revid', 'section', 'title', 'source_kind', 'license', 'quote', 'property', 'subject', 'from_year', 'to_year', 'shared_article', 'route', 'doubt', 'url'];

        return array_filter(array_replace(array_flip($keys), $evidence), fn (mixed $v, string $k): bool => array_key_exists($k, $evidence), ARRAY_FILTER_USE_BOTH);
    }
}
