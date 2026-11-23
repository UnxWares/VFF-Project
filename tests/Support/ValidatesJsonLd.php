<?php

namespace Tests\Support;

/**
 * Validation hors ligne des données structurées (JSON-LD) servies par VFF : pour chaque type de schema.org
 * utilisé, les propriétés obligatoires (celles que Google exige pour ces types) et la liste des propriétés
 * admises (les propriétés du type et de ses parents dans schema.org). Une faute de frappe, une propriété
 * d'un autre type ou une valeur vide fait échouer le test ; un nœud qui n'est qu'une référence (`@id`) passe.
 */
trait ValidatesJsonLd
{
    /** Propriétés de Thing, admises sur tous les types. */
    private const LD_THING = ['@type', '@id', '@context', 'name', 'alternateName', 'description', 'url', 'image', 'sameAs', 'identifier', 'additionalType'];

    private const LD_PLACE = ['geo', 'address', 'containedInPlace', 'containsPlace', 'additionalProperty'];

    /** @var array<string, array{required: list<string>, allowed: list<string>}> */
    private const LD_TYPES = [
        'WebSite' => ['required' => ['name', 'url'], 'allowed' => ['inLanguage', 'publisher', 'potentialAction']],
        'Organization' => ['required' => ['name', 'url'], 'allowed' => ['legalName', 'logo']],
        'SearchAction' => ['required' => ['target', 'query-input'], 'allowed' => ['target', 'query-input']],
        'EntryPoint' => ['required' => ['urlTemplate'], 'allowed' => ['urlTemplate']],
        'ImageObject' => ['required' => ['url|contentUrl'], 'allowed' => ['contentUrl', 'thumbnailUrl', 'caption', 'width', 'height', 'creator', 'creditText', 'license', 'copyrightNotice', 'acquireLicensePage']],
        'Person' => ['required' => ['name'], 'allowed' => []],
        'BreadcrumbList' => ['required' => ['itemListElement'], 'allowed' => ['itemListElement']],
        'ListItem' => ['required' => ['position', 'name', 'item'], 'allowed' => ['position', 'item']],
        'Place' => ['required' => ['name'], 'allowed' => self::LD_PLACE],
        'TouristAttraction' => ['required' => ['name'], 'allowed' => self::LD_PLACE],
        'TrainStation' => ['required' => ['name'], 'allowed' => self::LD_PLACE],
        'City' => ['required' => ['name'], 'allowed' => self::LD_PLACE],
        'AdministrativeArea' => ['required' => ['name'], 'allowed' => self::LD_PLACE],
        'GeoCoordinates' => ['required' => ['latitude', 'longitude'], 'allowed' => ['latitude', 'longitude']],
        'PostalAddress' => ['required' => ['addressLocality'], 'allowed' => ['addressLocality', 'addressCountry']],
        'PropertyValue' => ['required' => ['value'], 'allowed' => ['propertyID', 'value', 'unitCode', 'unitText']],
        'Event' => ['required' => ['name', 'startDate'], 'allowed' => ['startDate', 'eventAttendanceMode', 'location']],
        'Thing' => ['required' => ['name'], 'allowed' => []],
        'Dataset' => ['required' => ['name', 'description'], 'allowed' => ['license', 'isAccessibleForFree', 'inLanguage', 'creator', 'publisher', 'isPartOf', 'keywords', 'hasPart', 'distribution', 'dateModified']],
        'DataDownload' => ['required' => ['contentUrl'], 'allowed' => ['contentUrl', 'encodingFormat', 'contentSize']],
    ];

    /**
     * @param  array<array-key, mixed>  $data  objet JSON-LD (ou graphe `@graph`)
     */
    protected function assertValidJsonLd(array $data, string $where = 'JSON-LD'): void
    {
        $this->assertSame('https://schema.org', $data['@context'] ?? null, "{$where} : @context");
        foreach ($data['@graph'] ?? [$data] as $node) {
            $this->assertLdNode($node, $where);
        }
    }

    /** @param  array<array-key, mixed>  $node */
    private function assertLdNode(array $node, string $where): void
    {
        if (array_keys($node) === ['@id']) {
            return;
        }
        $types = (array) ($node['@type'] ?? []);
        $this->assertNotEmpty($types, "{$where} : @type manquant");
        foreach ($types as $type) {
            $this->assertArrayHasKey($type, self::LD_TYPES, "{$where} : type {$type} non validé (à déclarer dans ValidatesJsonLd)");
            $spec = self::LD_TYPES[$type];
            foreach ($spec['required'] as $property) {
                $this->assertTrue(
                    array_any(explode('|', $property), fn (string $p): bool => array_key_exists($p, $node)),
                    "{$where} : {$type} sans {$property}",
                );
            }
            foreach (array_keys($node) as $key) {
                $this->assertContains($key, [...self::LD_THING, ...$spec['allowed']], "{$where} : propriété {$key} inconnue pour {$type}");
            }
        }
        foreach ($node as $key => $value) {
            $this->assertNotNull($value, "{$where} : {$key} nul");
            $this->assertNotSame('', $value, "{$where} : {$key} vide");
            $this->assertNotSame([], $value, "{$where} : {$key} vide");
            if ($key === '@context' || ! is_array($value)) {
                continue;
            }
            foreach (array_is_list($value) ? $value : [$value] as $child) {
                if (is_array($child) && isset($child['@type'])) {
                    $this->assertLdNode($child, "{$where} > {$key}");
                }
            }
        }
    }
}
