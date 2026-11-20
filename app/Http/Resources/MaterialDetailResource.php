<?php

namespace App\Http\Resources;

use App\Models\Material;
use App\Services\Api\Provenance;
use App\Services\Materials\NotableUnits;
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
        'name', 'type', 'scope', 'service_status', 'designations', 'description', 'builder', 'builders', 'operators', 'in_service_from',
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
             * `wikipedia`, ou `contrib` pour une valeur proposée par un contributeur et validée), `reference`
             * (élément et propriété Wikidata, ex. `Q746572#P729` ; fichier Commons ; catégorie Wikipédia ;
             * `contribution:<id>`), `url` (page de la source), `original` (valeur d'origine et unité Wikidata
             * quand VFF l'a convertie, sinon nul), et la licence de la source (`provider`, `name`, `license`,
             * `license_status`).
             *
             * @var list<array{field: string, source: string, reference: string|null, url: string|null, original: array{amount: float, unit: string}|null, provider: string|null, name: string|null, license: string|null, license_status: string|null}>
             */
            'field_sources' => self::fieldSourcesOf($this->resource),
            /**
             * Rames ou engins remarquables de la série (ex. rame 01 « Patrick » du TGV Sud-Est), liste vide si aucun :
             * `number` (numéro), `nickname` (surnom ou nul), `facts` (faits datés : `kind`, `date` au format
             * AAAA-MM-JJ et `date_to` pour une période, au 1er du mois ou de l'année selon `precision` = `day`,
             * `month`, `year` ou nulle sans date ; `text` en français, reformulé ; `quote`, courte citation exacte de
             * la source ou nulle ; `source` : `publisher`, `reported_by` quand la page rapporte un autre média,
             * `date` de publication, `url` à citer, `provider`, `license`, `license_status`), et `unknown` (ce que
             * les sources ne disent pas, ou nul). Données relues à la main (database/data/material_notable_units.php).
             *
             * @var list<array{number: string, nickname: string|null, facts: list<array<string, mixed>>, unknown: string|null}>
             */
            'notable_units' => app(NotableUnits::class)->forMaterial($this->slug),
            /**
             * Lignes parcourues, une entrée par lien attesté (voir `GET /api/v1/lines/{line}/materials` pour le
             * détail des champs d'un lien, dont `periods`, les périodes de circulation datées), triées par nom de ligne.
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
                // Contribution validée : l'adresse de la source citée par le contributeur, gardée avec la provenance.
                'url' => $entry['url'] ?? self::sourceUrl($source, $ref, $entry['revid'] ?? null),
                'original' => $entry['original'] ?? null,
                ...$provenance->for('materials.fields', $source),
            ];
        }

        // Ordre de lecture d'une fiche (jsonb réordonne les clés).
        $order = array_flip(self::FIELD_ORDER);
        usort($out, fn (array $a, array $b): int => ($order[$a['field']] ?? 99) <=> ($order[$b['field']] ?? 99));

        return $out;
    }

    /**
     * Page de la source d'un champ : élément Wikidata, fichier Commons, catégorie ou article Wikipédia (version
     * citée quand la révision est connue).
     */
    public static function sourceUrl(string $source, ?string $ref, ?int $revid = null): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        return match ($source) {
            'wikidata' => 'https://www.wikidata.org/wiki/'.strtok($ref, '#'),
            'commons' => 'https://commons.wikimedia.org/wiki/'.str_replace('%3A', ':', rawurlencode(str_replace(' ', '_', $ref))),
            'wikipedia' => self::wikipediaUrl($ref, $revid),
            default => null,
        };
    }

    /** Article Wikipédia : permalien de la révision citée quand elle est connue, sinon article courant. L'ancre (`#infobox:Type`) n'est pas une page. */
    private static function wikipediaUrl(string $ref, ?int $revid): string
    {
        $title = str_replace(' ', '_', (string) strtok($ref, '#'));
        if ($revid !== null) {
            return 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode($title).'&oldid='.$revid;
        }

        return 'https://fr.wikipedia.org/wiki/'.str_replace('%3A', ':', rawurlencode($title));
    }
}
