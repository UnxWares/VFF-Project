<?php

namespace App\Http\Resources;

use App\Models\Material;
use App\Services\Api\Provenance;
use App\Support\WebUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Série de matériel roulant, avec ses caractéristiques et la provenance de la fiche.
 *
 * Fiches importées de Wikidata (CC0) : un champ nul veut dire que la source ne le renseigne pas,
 * jamais une valeur estimée. L'image est hébergée par Wikimedia Commons, avec sa propre licence.
 *
 * @mixin Material
 */
class MaterialResource extends JsonResource
{
    /** Champs de l'image, dans l'ordre de la réponse. */
    private const IMAGE_KEYS = [
        'provider', 'file', 'thumb_url', 'display_url', 'source_url', 'width', 'height', 'author', 'license',
        'license_url', 'rights_status', 'commercial_ok', 'share_alike', 'attribution_required', 'attribution',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = $this->resource->getRawOriginal('type');

        return [
            /**
             * Identifiant stable et lisible, dérivé du nom (adresse de la fiche).
             *
             * @example bb-15000
             */
            'slug' => $this->slug,
            /**
             * Nom d'usage de la série : titre de l'article Wikipédia en français, sinon libellé Wikidata.
             *
             * @example BB 15000
             */
            'name' => $this->name,
            /**
             * Catégorie : `locomotive_vapeur`, `locomotive_diesel`, `locomotive_electrique`, `automotrice`
             * (rame automotrice électrique), `bimode` (engin qui circule sous caténaire et à l'énergie thermique :
             * Régiolis, AGC bimodes ; rangé là seulement quand Wikidata ou l'infobox de l'article Wikipédia le dit,
             * sinon l'engin reste `automotrice` ou `autorail`), `autorail` (rame thermique), `rame_tgv` (grande
             * vitesse), `voiture` (voiture voyageurs), `wagon` (marchandises), `tramway`, `engin_service` (engin de
             * l'infrastructure : auscultation, chasse-neige, draisine).
             *
             * @example locomotive_electrique
             */
            'type' => $type,
            /**
             * Libellé français de la catégorie.
             *
             * @example Locomotive électrique
             */
            'type_label' => __("materials.type.{$type}", [], 'fr'),
            /**
             * Autres noms : libellés et alias Wikidata (français, anglais), surnoms. Liste vide si aucun.
             *
             * @var list<string>
             *
             * @example ["SNCF BB 15000", "SNCF Class BB 15000"]
             */
            'designations' => $this->designations ?? [],
            /**
             * Courte description en français (description Wikidata, CC0). Nulle si absente.
             *
             * @example série de 65 locomotives électriques de la SNCF
             */
            'description' => $this->description,
            /**
             * Constructeurs, en une ligne lisible (noms séparés par des virgules), sans ceux qui n'ont pas de
             * libellé Wikidata (voir `builders`). Nul si inconnu.
             *
             * @example Alstom, Matériel de Traction Électrique
             */
            'builder' => $this->builder,
            /**
             * Constructeurs, un par entrée : `name`, `wikidata_id` et `units` (engins construits par lui, nul si
             * Wikidata ne le précise pas). Un élément Wikidata sans libellé en français ni en anglais garde son
             * identifiant comme `name` (ex. `Q529531`) : le site ne l'affiche pas comme un nom.
             *
             * @var list<array{name: string, wikidata_id: string|null, units: int|null}>
             */
            'builders' => self::shape($this->builders, ['name', 'wikidata_id', 'units']),
            /**
             * Exploitants : `name`, `wikidata_id`, `from_year` / `to_year` (période d'exploitation, nulles si non
             * précisées) et `units` (engins exploités, nul si non précisé).
             *
             * @var list<array{name: string, wikidata_id: string|null, from_year: int|null, to_year: int|null, units: int|null}>
             */
            'operators' => self::shape($this->operators, ['name', 'wikidata_id', 'from_year', 'to_year', 'units']),
            /**
             * Année de mise en service de la série (la plus ancienne déclarée). Nulle si inconnue.
             *
             * @example 1971
             */
            'in_service_from' => $this->in_service_from,
            /**
             * Année de retrait du service (la plus récente déclarée). Nulle si la série circule encore OU si la
             * date de retrait n'est pas renseignée : ne concluez pas qu'elle est en service (voir `service`
             * pour l'état d'utilisation, quand une source le dit).
             *
             * @example null
             */
            'in_service_to' => $this->in_service_to,
            /**
             * État d'utilisation, seulement quand une source le dit : `status` (`in_service` ou `withdrawn`, nul si
             * aucune source ne le dit : une date de retrait absente ne prouve pas qu'une série circule),
             * `units_in_service` (effectif cité par la source, nul si elle n'en donne pas), `as_of` (date à laquelle
             * la source donne cet état : `AAAA-MM-JJ`, `AAAA-MM` ou `AAAA` selon sa précision, nulle si elle n'en
             * donne pas), `checked_at` (date à laquelle VFF a lu la source, `AAAA-MM-JJ`), `source` (`wikidata`
             * propriété P5817, ou `wikipedia` effectif de l'infobox) et `url` (page de la source : élément Wikidata
             * ou version citée de l'article Wikipédia).
             *
             * @var array{status: string|null, units_in_service: int|null, as_of: string|null, checked_at: string|null, source: string|null, url: string|null}
             */
            'service' => self::service($this->resource),
            /**
             * Périmètre de la fiche : `france` (série rattachée à la France dans Wikidata : exploitant français ou
             * pays France) ou `attested` (série non rattachée, par exemple un ICE, un AVE ou une locomotive belge,
             * ajoutée parce qu'une source atteste sa circulation sur une ligne française : voir `provenance` du champ
             * `scope` dans `field_sources`, et les liens de ligne).
             *
             * @example france
             */
            'scope' => $this->scope,
            /**
             * Nombre d'engins construits, s'il est univoque dans la source. Nul sinon.
             *
             * @example 65
             */
            'units_built' => $this->units_built,
            /**
             * Vitesse maximale en km/h (la plus grande déclarée ; convertie si la source l'exprime en mi/h).
             *
             * @example 160
             */
            'max_speed_kmh' => $this->max_speed_kmh,
            /**
             * Puissance nominale en kilowatts (la plus grande déclarée, par exemple sous la tension la plus
             * favorable ; convertie depuis les chevaux-vapeur métriques si besoin, valeur d'origine dans
             * `field_sources` de la fiche). Rarement renseignée.
             *
             * @example 4620
             */
            'power_kw' => $this->power_kw,
            /**
             * Alimentations électriques : `label` (libellé Wikidata en français, ex. « électrification
             * ferroviaire 25 kV 50 Hz ») et `wikidata_id`. Liste vide pour un engin thermique ou si non renseignée.
             *
             * @var list<array{label: string, wikidata_id: string|null}>
             */
            'electrification' => self::shape($this->electrification, ['label', 'wikidata_id']),
            /**
             * Écartement des rails en millimètres, quand la série n'en a qu'un (1435 = voie normale, 1000 = voie
             * métrique). Nul si inconnu ou multiple (voir `gauges`).
             *
             * @example 1435
             */
            'gauge_mm' => $this->gauge_mm,
            /**
             * Écartements : `label` (ex. « écartement standard »), `mm` et `wikidata_id`.
             *
             * @var list<array{label: string, mm: int|null, wikidata_id: string|null}>
             */
            'gauges' => self::shape($this->gauges, ['label', 'mm', 'wikidata_id']),
            /**
             * Disposition des essieux, telle que libellée par Wikidata (notation UIC pour l'électrique et le
             * diesel, ex. « B'B' », Whyte ou française pour la vapeur, ex. « 141 »).
             *
             * @example B'B'
             */
            'wheel_arrangement' => $this->wheel_arrangement,
            /**
             * Numéros des engins de la série, tels que déclarés (plusieurs plages séparées par « ; »).
             *
             * @example 15001 - 15065
             */
            'fleet_numbers' => $this->fleet_numbers,
            /**
             * Image principale (Wikimedia Commons), nulle si aucune. Hébergée par Commons : `thumb_url` (500 px),
             * `display_url` (grande taille), `source_url` (page du fichier, à citer), `width` / `height` (pixels
             * de l'original). Droits du fichier, à afficher avec l'image : `author`, `license` (telle que
             * déclarée, nulle si inconnue), `license_url`, `rights_status` (`public_domain`, `open_license`,
             * `unknown`), `commercial_ok`, `share_alike`, `attribution_required` et `attribution` (ligne de crédit
             * prête à l'emploi).
             *
             * @var array{provider: string, file: string, thumb_url: string|null, display_url: string|null, source_url: string, width: int|null, height: int|null, author: string|null, license: string|null, license_url: string|null, rights_status: string, commercial_ok: bool|null, share_alike: bool|null, attribution_required: bool|null, attribution: string}|null
             */
            'image' => $this->image !== null ? self::image($this->image) : null,
            /**
             * Catégorie Wikimedia Commons de la série (autres photos), nulle si inconnue.
             *
             * @example SNCF Class BB 15000
             */
            'commons_category' => $this->commons_category,
            /**
             * Adresse de la catégorie Commons.
             *
             * @example https://commons.wikimedia.org/wiki/Category:SNCF_Class_BB_15000
             */
            'commons_category_url' => $this->commons_category !== null
                ? 'https://commons.wikimedia.org/wiki/Category:'.rawurlencode(str_replace(' ', '_', $this->commons_category))
                : null,
            /**
             * Élément Wikidata de la série. Nul pour une fiche saisie dans VFF.
             *
             * @example Q746572
             */
            'wikidata_id' => $this->wikidata_id,
            /**
             * Adresse de l'élément Wikidata.
             *
             * @example https://www.wikidata.org/wiki/Q746572
             */
            'wikidata_url' => $this->resource->wikidataUrl(),
            /**
             * Articles Wikipédia de la série : `fr` et `en`, chacun `{title, url}` ou nul.
             *
             * @var array{fr: array{title: string, url: string}|null, en: array{title: string, url: string}|null}
             */
            'wikipedia' => [
                'fr' => $this->wikipedia_fr !== null ? ['title' => $this->wikipedia_fr, 'url' => $this->resource->wikipediaUrl('fr')] : null,
                'en' => $this->wikipedia_en !== null ? ['title' => $this->wikipedia_en, 'url' => $this->resource->wikipediaUrl('en')] : null,
            ],
            /**
             * Nombre de lignes de VFF sur lesquelles la série a circulé d'après une source (voir la fiche).
             *
             * @example 37
             */
            'lines_count' => (int) ($this->lines_count ?? 0),
            /**
             * Provenance de la fiche : `wikidata`, ou nulle pour une fiche saisie dans VFF.
             *
             * @example wikidata
             */
            'source' => $this->source,
            /**
             * Fournisseur de la fiche et licence : `provider`, `name`, `license`, `license_status` (`verified` :
             * licence ouverte établie). Wikidata : CC0, aucune obligation. L'image a sa propre licence (`image`).
             *
             * @var array{provider: string|null, name: string|null, license: string|null, license_status: string|null}
             */
            'provenance' => app(Provenance::class)->for('materials', $this->source),
            /**
             * Adresses de la fiche : `api` (cette ressource), `page` (fiche du site) et `media` (images de la
             * série publiées après validation, avec leurs droits : galerie paginée de la fiche).
             *
             * @var array{api: string, page: string, media: string}
             */
            'links' => [
                'api' => route('api.materials.show', $this->slug),
                'page' => route('materials.show', $this->slug),
                'media' => route('api.materials.media', $this->slug),
            ],
        ];
    }

    /**
     * Bloc `service` : état d'utilisation et sa source (tout nul quand aucune source ne le dit).
     *
     * @return array{status: string|null, units_in_service: int|null, as_of: string|null, checked_at: string|null, source: string|null, url: string|null}
     */
    private static function service(Material $material): array
    {
        $entry = $material->service_status !== null ? ($material->provenance['service_status'] ?? null) : null;

        return [
            'status' => $material->service_status,
            'units_in_service' => $entry['units'] ?? null,
            'as_of' => $entry['as_of'] ?? null,
            'checked_at' => $material->service_status !== null ? $material->service_status_checked_at : null,
            'source' => $entry['source'] ?? null,
            'url' => $entry !== null ? MaterialDetailResource::sourceUrl($entry['source'], $entry['ref'] ?? null, $entry['revid'] ?? null) : null,
        ];
    }

    /**
     * Image Commons de la série, dans l'ordre de IMAGE_KEYS ; adresses en http(s) seulement (WebUrl).
     *
     * @param  array<string, mixed>  $image
     * @return array<string, mixed>
     */
    private static function image(array $image): array
    {
        $shaped = self::shape([$image], self::IMAGE_KEYS)[0];
        foreach (['thumb_url', 'display_url', 'source_url', 'license_url'] as $key) {
            $shaped[$key] = WebUrl::safe($shaped[$key]);
        }

        return $shaped;
    }

    /**
     * Objets JSON stockés (jsonb réordonne leurs clés) remis dans un ordre de clés fixe et documenté.
     *
     * @param  list<array<string, mixed>>|null  $items
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    protected static function shape(?array $items, array $keys): array
    {
        return array_map(fn (array $item): array => array_combine($keys, array_map(fn (string $k): mixed => $item[$k] ?? null, $keys)), $items ?? []);
    }
}
