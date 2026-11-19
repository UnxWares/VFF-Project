<?php

namespace App\Http\Resources;

use App\Models\Material;
use App\Services\Api\Provenance;
use Illuminate\Http\Request;

/**
 * Fiche complète d'une série : champs de la liste, provenance de chaque champ et lignes parcourues
 * avec la preuve de chaque lien.
 *
 * @mixin Material
 */
class MaterialDetailResource extends MaterialResource
{
    /** Ordre des champs dans `field_sources`, celui de la fiche. */
    private const FIELD_ORDER = [
        'name', 'type', 'designations', 'description', 'builder', 'builders', 'operators', 'in_service_from',
        'in_service_to', 'units_built', 'max_speed_kmh', 'power_kw', 'electrification', 'gauge_mm', 'gauges',
        'wheel_arrangement', 'fleet_numbers', 'image', 'commons_category', 'wikipedia_fr', 'wikipedia_en',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            /**
             * Provenance de chaque champ renseigné : `field` (nom du champ), `source` (`wikidata`, `commons`,
             * `wikipedia`), `reference` (élément et propriété Wikidata, ex. `Q746572#P729` ; fichier Commons ;
             * catégorie Wikipédia), `url` (page de la source), `original` (valeur d'origine et unité Wikidata
             * quand VFF l'a convertie, sinon nul), et la licence de la source (`provider`, `name`, `license`,
             * `license_status`).
             *
             * @var list<array{field: string, source: string, reference: string|null, url: string|null, original: array{amount: float, unit: string}|null, provider: string|null, name: string|null, license: string|null, license_status: string|null}>
             */
            'field_sources' => self::fieldSourcesOf($this->resource),
            /**
             * Lignes parcourues, une entrée par lien attesté (voir `GET /api/v1/lines/{line}/materials` pour le
             * détail des champs d'un lien), triées par nom de ligne.
             */
            'lines' => LineMaterialLinkResource::collection($this->whenLoaded('lineLinks')),
        ];
    }

    /**
     * Provenance de chaque champ renseigné, avec la licence de sa source (partagée avec GraphQL).
     *
     * @return list<array<string, mixed>>
     */
    public static function fieldSourcesOf(Material $material): array
    {
        $provenance = app(Provenance::class);
        $out = [];
        foreach ($material->provenance ?? [] as $field => $entry) {
            $source = $entry['source'];
            $ref = $entry['ref'] ?? null;
            $out[] = [
                'field' => (string) $field,
                'source' => $source,
                'reference' => $ref,
                'url' => self::sourceUrl($source, $ref),
                'original' => $entry['original'] ?? null,
                ...$provenance->for('materials.fields', $source),
            ];
        }

        // Ordre de lecture d'une fiche (jsonb réordonne les clés).
        $order = array_flip(self::FIELD_ORDER);
        usort($out, fn (array $a, array $b): int => ($order[$a['field']] ?? 99) <=> ($order[$b['field']] ?? 99));

        return $out;
    }

    /** Page de la source d'un champ : élément Wikidata, fichier Commons ou catégorie Wikipédia. */
    public static function sourceUrl(string $source, ?string $ref): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        return match ($source) {
            'wikidata' => 'https://www.wikidata.org/wiki/'.strtok($ref, '#'),
            'commons' => 'https://commons.wikimedia.org/wiki/'.str_replace('%3A', ':', rawurlencode(str_replace(' ', '_', $ref))),
            'wikipedia' => 'https://fr.wikipedia.org/wiki/'.str_replace('%3A', ':', rawurlencode(str_replace(' ', '_', $ref))),
            default => null,
        };
    }
}
