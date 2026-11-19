<?php

namespace App\Services\Materials;

use App\Services\Media\CommonsMetadata;

/**
 * Traduit un élément Wikidata (réduit par WikidataMaterialSource::trimEntity) en fiche de
 * matériel VFF, sans rien estimer : un champ que Wikidata ne renseigne pas reste nul, et chaque
 * champ rempli garde sa provenance (élément et propriété Wikidata, fichier Commons).
 *
 * Règles de prudence :
 *   - années de service lues seulement à la précision « année » ou mieux (une décennie
 *     n'est pas une année) ; un retrait antérieur à la mise en service est écarté ;
 *   - nombre construit retenu seulement s'il est univoque ;
 *   - vitesse et puissance : la plus grande valeur déclarée (modes d'alimentation), convertie en
 *     km/h et kW, la valeur d'origine gardée dans la provenance quand l'unité diffère.
 */
final readonly class WikidataMaterialMapper
{
    public const SKIP_NO_ENTITY = 'absent_de_wikidata';

    public const SKIP_FOREIGN = 'exploitants_hors_de_france';

    public const SKIP_METRO = 'materiel_de_metro';

    public const SKIP_NO_TYPE = 'categorie_inconnue';

    public const SKIP_NO_NAME = 'sans_nom';

    public function __construct(private WikidataMaterialRules $rules) {}

    /**
     * @param  array<string, mixed>|null  $entity  élément réduit
     * @param  list<string>  $ancestors  classes consultées par les règles dont la série descend
     * @param  array<string, array{fr: ?string, en: ?string}|null>  $labels  libellés des éléments cités
     * @param  array<string, int|null>  $gaugeWidths  largeur des écartements, en mm
     * @param  array<string, array<string, mixed>|null>  $images  métadonnées Commons par nom de fichier
     * @param  list<string>  $categories  catégories de l'article Wikipédia, repli quand Wikidata ne range pas la série
     * @return array<string, mixed>|string attributs de la fiche, ou motif d'exclusion (constantes SKIP_*)
     */
    public function map(string $qid, ?array $entity, array $ancestors, array $labels, array $gaugeWidths, array $images, array $categories = []): array|string
    {
        if ($entity === null) {
            return self::SKIP_NO_ENTITY;
        }
        $claims = $entity['claims'] ?? [];
        $operatorIds = array_values(array_unique(array_filter(array_map(fn (array $c): mixed => is_string($c['v']) ? $c['v'] : null, $claims['P137'] ?? []))));
        if ($this->rules->onlyForeignOperators($operatorIds)) {
            return self::SKIP_FOREIGN;
        }
        if ($this->rules->excludedRoot($ancestors) !== null) {
            return self::SKIP_METRO;
        }
        $ref = fn (string $what): array => ['source' => 'wikidata', 'ref' => "{$qid}#{$what}"];
        $type = $this->rules->typeFor($ancestors);
        $typeProvenance = $type !== null ? $ref('P279:'.$type[1]) : null;
        if ($type === null && ($fallback = $this->rules->typeFromCategories($categories)) !== null) {
            if ($fallback[0] === null) {
                return self::SKIP_METRO;
            }
            $type = $fallback;
            $typeProvenance = ['source' => 'wikipedia', 'ref' => 'Catégorie:'.$fallback[1]];
        }
        if ($type === null || $typeProvenance === null) {
            return self::SKIP_NO_TYPE;
        }
        [$name, $nameRef] = $this->name($entity);
        if ($name === null) {
            return self::SKIP_NO_NAME;
        }

        $label = fn (string $id): string => self::plain($labels[$id]['fr'] ?? $labels[$id]['en'] ?? null) ?? $id;
        $provenance = ['name' => $ref($nameRef), 'type' => $typeProvenance];

        $builders = [];
        foreach ($claims['P176'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $builders[] = ['name' => $label($c['v']), 'wikidata_id' => $c['v'], 'units' => self::firstInt($c['q']['P1092'] ?? [])];
            }
        }
        $operators = [];
        foreach ($claims['P137'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $operators[] = [
                    'name' => $label($c['v']),
                    'wikidata_id' => $c['v'],
                    'from_year' => self::year($c['q']['P580'][0] ?? null),
                    'to_year' => self::year($c['q']['P582'][0] ?? null),
                    'units' => self::firstInt($c['q']['P1114'] ?? []),
                ];
            }
        }

        $from = self::extremeYear($claims['P729'] ?? [], 'min');
        $to = self::extremeYear($claims['P730'] ?? [], 'max');
        if ($from !== null && $to !== null && $to < $from) {
            $to = null;
        }

        [$speed, $speedOrigin] = $this->maxConverted($claims['P2052'] ?? [], fn (float $a, string $u): ?int => $this->rules->speedKmh($a, $u), 'Q180154');
        [$power, $powerOrigin] = $this->maxConverted($claims['P2109'] ?? [], fn (float $a, string $u): ?int => $this->rules->powerKw($a, $u), 'Q3320608');

        $electrification = [];
        foreach ($claims['P930'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $electrification[] = ['label' => $label($c['v']), 'wikidata_id' => $c['v']];
            }
        }
        $gauges = [];
        foreach ($claims['P1064'] ?? [] as $c) {
            if (is_string($c['v'])) {
                $gauges[] = ['label' => $label($c['v']), 'mm' => $gaugeWidths[$c['v']] ?? null, 'wikidata_id' => $c['v']];
            }
        }
        $gaugeValues = array_values(array_unique(array_filter(array_column($gauges, 'mm'))));

        $wheel = self::firstString($claims['P2978'] ?? []);
        $fleet = array_values(array_filter(array_map(fn (array $c): ?string => is_string($c['v']) ? self::plain($c['v']) : null, $claims['P2802'] ?? [])));
        $file = self::preferredString($claims['P18'] ?? []);
        $image = $file !== null && is_array($images[$file] ?? null) ? self::image($images[$file]) : null;
        $category = self::firstString($claims['P373'] ?? []);

        $attributes = [
            'wikidata_id' => $qid,
            'name' => $name,
            'type' => $type[0]->value,
            'designations' => $this->designations($entity, $name) ?: null,
            'description' => self::plain($entity['descriptions']['fr'] ?? null),
            'builder' => $builders !== [] ? mb_strimwidth(implode(', ', array_unique(array_column($builders, 'name'))), 0, 255, '…') : null,
            'builders' => $builders ?: null,
            'operators' => $operators ?: null,
            'in_service_from' => $from,
            'in_service_to' => $to,
            'units_built' => self::units($claims['P1092'] ?? []),
            'max_speed_kmh' => $speed,
            'power_kw' => $power,
            'electrification' => $electrification ?: null,
            'gauges' => $gauges ?: null,
            'gauge_mm' => count($gaugeValues) === 1 ? (int) $gaugeValues[0] : null,
            'wheel_arrangement' => $wheel !== null ? mb_substr($label($wheel), 0, 60) : null,
            'fleet_numbers' => $fleet !== [] ? mb_strimwidth(implode(' ; ', $fleet), 0, 255, '…') : null,
            'image' => $image,
            'commons_category' => $category,
            'wikipedia_fr' => $entity['sitelinks']['frwiki'] ?? null,
            'wikipedia_en' => $entity['sitelinks']['enwiki'] ?? null,
        ];

        $sources = [
            'designations' => 'labels', 'description' => 'description:fr', 'builder' => 'P176', 'builders' => 'P176',
            'operators' => 'P137', 'in_service_from' => 'P729', 'in_service_to' => 'P730', 'units_built' => 'P1092',
            'max_speed_kmh' => 'P2052', 'power_kw' => 'P2109', 'electrification' => 'P930', 'gauges' => 'P1064',
            'gauge_mm' => 'P1064', 'wheel_arrangement' => 'P2978', 'fleet_numbers' => 'P2802', 'commons_category' => 'P373',
            'wikipedia_fr' => 'sitelink:frwiki', 'wikipedia_en' => 'sitelink:enwiki',
        ];
        foreach ($sources as $field => $what) {
            if ($attributes[$field] !== null) {
                $provenance[$field] = $ref($what);
            }
        }
        if ($speedOrigin !== null && $speed !== null) {
            $provenance['max_speed_kmh']['original'] = $speedOrigin;
        }
        if ($powerOrigin !== null && $power !== null) {
            $provenance['power_kw']['original'] = $powerOrigin;
        }
        if ($image !== null) {
            $provenance['image'] = ['source' => 'commons', 'ref' => $image['file'], 'via' => "{$qid}#P18"];
        }
        $attributes['provenance'] = $provenance;

        return $attributes;
    }

    /** @return list<string> identifiants Wikidata cités par l'élément (constructeurs, exploitants, alimentations, écartements, essieux) */
    public static function referencedIds(array $entity): array
    {
        $ids = [];
        foreach (['P176', 'P137', 'P930', 'P1064', 'P2978'] as $property) {
            foreach ($entity['claims'][$property] ?? [] as $c) {
                if (is_string($c['v']) && str_starts_with($c['v'], 'Q')) {
                    $ids[] = $c['v'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<string> */
    public static function gaugeIds(array $entity): array
    {
        return array_values(array_unique(array_filter(array_map(fn (array $c): ?string => is_string($c['v']) ? $c['v'] : null, $entity['claims']['P1064'] ?? []))));
    }

    /** Nom de l'image principale (P18 de rang préféré, sinon le premier). */
    public static function imageFile(array $entity): ?string
    {
        return self::preferredString($entity['claims']['P18'] ?? []);
    }

    /**
     * Nom d'usage : titre de l'article Wikipédia en français (sans la précision entre parenthèses),
     * sinon libellé français, sinon libellé anglais.
     *
     * @param  array<string, mixed>  $entity
     * @return array{0: ?string, 1: string} nom et provenance
     */
    private function name(array $entity): array
    {
        $title = $entity['sitelinks']['frwiki'] ?? null;
        if (is_string($title) && ($clean = self::plain(preg_replace('/\s*\([^()]*\)$/u', '', $title))) !== null) {
            return [$clean, 'sitelink:frwiki'];
        }
        foreach (['fr', 'en'] as $lang) {
            if (($label = self::plain($entity['labels'][$lang] ?? null)) !== null) {
                return [$label, "label:{$lang}"];
            }
        }

        return [null, ''];
    }

    /**
     * Autres noms : libellés, alias et surnoms, sans le nom d'usage ni doublon (casse ignorée).
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    private function designations(array $entity, string $name): array
    {
        $candidates = [
            $entity['labels']['fr'] ?? null,
            $entity['labels']['en'] ?? null,
            ...($entity['aliases']['fr'] ?? []),
            ...($entity['aliases']['en'] ?? []),
        ];
        foreach ($entity['claims']['P1449'] ?? [] as $c) {
            $candidates[] = is_array($c['v']) ? ($c['v']['text'] ?? null) : null;
        }
        $seen = [mb_strtolower($name) => true];
        $out = [];
        foreach ($candidates as $candidate) {
            $text = self::plain(is_string($candidate) ? $candidate : null);
            if ($text !== null && ! isset($seen[$key = mb_strtolower($text)])) {
                $seen[$key] = true;
                $out[] = mb_substr($text, 0, 120);
            }
        }

        return array_slice($out, 0, 20);
    }

    /**
     * Plus grande valeur convertie ; valeur d'origine renvoyée quand son unité n'est pas l'unité VFF.
     *
     * @param  list<array<string, mixed>>  $claims
     * @param  callable(float, string): ?int  $convert
     * @return array{0: ?int, 1: ?array{amount: float, unit: string}}
     */
    private function maxConverted(array $claims, callable $convert, string $baseUnit): array
    {
        $best = null;
        $origin = null;
        foreach ($claims as $c) {
            if (! is_array($c['v']) || ! isset($c['v']['amount']) || ! is_string($c['v']['unit'] ?? null)) {
                continue;
            }
            $value = $convert((float) $c['v']['amount'], $c['v']['unit']);
            if ($value !== null && ($best === null || $value > $best)) {
                $best = $value;
                $origin = $c['v']['unit'] !== $baseUnit ? ['amount' => (float) $c['v']['amount'], 'unit' => $c['v']['unit']] : null;
            }
        }

        return [$best, $origin];
    }

    /**
     * Image Commons : adresses, dimensions et droits tels que déclarés par Commons.
     *
     * @param  array<string, mixed>  $meta  sortie de CommonsMetadata::normalize
     * @return array<string, mixed>
     */
    private static function image(array $meta): array
    {
        return [
            'provider' => 'commons',
            'file' => $meta['file_title'],
            'thumb_url' => $meta['thumb_url'],
            'display_url' => $meta['display_url'],
            'source_url' => $meta['source_url'],
            'width' => $meta['width'],
            'height' => $meta['height'],
            'author' => $meta['author'],
            'license' => $meta['license'],
            'license_url' => $meta['license_url'],
            'rights_status' => $meta['rights_status'],
            'commercial_ok' => $meta['commercial_ok'],
            'share_alike' => $meta['share_alike'],
            'attribution_required' => $meta['attribution_required'],
            'attribution' => $meta['attribution'],
        ];
    }

    /** Année d'une valeur temporelle Wikidata, à la précision « année » ou mieux ; null sinon. */
    public static function year(mixed $time): ?int
    {
        if (! is_array($time) || ($time['precision'] ?? 0) < 9 || ! preg_match('/^([+-])(\d{1,4})-/', (string) ($time['time'] ?? ''), $m)) {
            return null;
        }
        $year = (int) $m[2];

        return $m[1] === '+' && $year >= 1800 && $year <= 2100 ? $year : null;
    }

    /** @param list<array<string, mixed>> $claims */
    private static function extremeYear(array $claims, string $mode): ?int
    {
        $years = array_values(array_filter(array_map(fn (array $c): ?int => self::year($c['v']), $claims)));
        if ($years === []) {
            return null;
        }

        return $mode === 'min' ? min($years) : max($years);
    }

    /**
     * Nombre construit univoque : une seule valeur, ou une seule sans qualificatif, ou des valeurs égales.
     *
     * @param  list<array<string, mixed>>  $claims
     */
    private static function units(array $claims): ?int
    {
        $values = [];
        $bare = [];
        foreach ($claims as $c) {
            if (is_array($c['v']) && isset($c['v']['amount']) && $c['v']['amount'] > 0) {
                $values[] = (int) round($c['v']['amount']);
                if (($c['q'] ?? []) === []) {
                    $bare[] = (int) round($c['v']['amount']);
                }
            }
        }
        $distinct = array_values(array_unique($values));
        if (count($distinct) === 1) {
            return $distinct[0];
        }

        return count($bare) === 1 ? $bare[0] : null;
    }

    /** @param list<mixed> $quantities */
    private static function firstInt(array $quantities): ?int
    {
        $first = $quantities[0] ?? null;

        return is_array($first) && isset($first['amount']) && $first['amount'] > 0 ? (int) round($first['amount']) : null;
    }

    /** @param list<array<string, mixed>> $claims */
    private static function firstString(array $claims): ?string
    {
        foreach ($claims as $c) {
            if (is_string($c['v']) && trim($c['v']) !== '') {
                return trim($c['v']);
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $claims */
    private static function preferredString(array $claims): ?string
    {
        usort($claims, fn (array $a, array $b): int => (($b['rank'] ?? '') === 'preferred') <=> (($a['rank'] ?? '') === 'preferred'));

        return self::firstString($claims);
    }

    /** Texte d'une source, espaces normalisés et tirets longs ou moyens remplacés par un tiret simple. */
    public static function plain(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) CommonsMetadata::plainDashes($text)));

        return $text !== '' ? $text : null;
    }
}
