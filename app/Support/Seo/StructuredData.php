<?php

namespace App\Support\Seo;

/**
 * Données structurées schema.org (JSON-LD) des pages publiques, côté serveur : les aperçus de
 * partage (Facebook, Mastodon, Discord…) et beaucoup de robots ne lisent pas le JavaScript.
 *
 * Même logique, mêmes objets que `resources/js/lib/structuredData.js` (que le navigateur utilise
 * après une navigation interne) : toute modification d'un côté se reporte de l'autre.
 *
 * Règle : rien d'inventé. Une propriété n'est écrite que si la donnée existe dans la base (pas de
 * licence devinée, pas de coordonnées approchées, pas de logo d'emprunt) ; une valeur vide est
 * retirée de l'objet plutôt qu'écrite à null.
 */
final class StructuredData
{
    /** Éditeur et hébergeur de VFF (mentions légales). */
    private const PUBLISHER = [
        '@type' => 'Organization',
        'name' => 'UnxWares',
        'legalName' => 'UnxWares SAS',
        'url' => 'https://www.unxwares.com',
    ];

    /** Élément Wikidata « ligne de chemin de fer », type précis d'une fiche ligne (schema.org n'en a pas). */
    private const WIKIDATA_RAILWAY_LINE = 'https://www.wikidata.org/wiki/Q728937';

    /**
     * Retire les valeurs vides (null, chaîne vide, tableau vide), récursivement ; 0 et false restent.
     */
    public static function compact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $item) {
            $item = self::compact($item);
            if ($item !== null && $item !== '' && $item !== []) {
                $out[$key] = $item;
            }
        }

        return array_is_list($value) ? array_values($out) : $out;
    }

    /**
     * Texte JSON prêt pour une balise `<script type="application/ld+json">` : « < » échappé, pour
     * qu'un texte de la base (« </script> ») ne puisse jamais fermer la balise.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function serialize(array $data): string
    {
        $json = json_encode(self::compact($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return str_replace('<', '\u003c', $json);
    }

    /** Adresse absolue à partir d'un chemin (ou d'une adresse) du site. */
    public static function absoluteUrl(string $origin, string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return rtrim($origin, '/').'/'.ltrim($path, '/');
    }

    /** Identifiant Wikidata valide (« Q12345 ») en adresse, sinon null. */
    public static function wikidataUrl(mixed $id): ?string
    {
        return is_string($id) && preg_match('/^Q\d+$/', trim($id)) === 1
            ? 'https://www.wikidata.org/wiki/'.trim($id)
            : null;
    }

    /** Article de Wikipédia en français (les extraits des fiches en viennent). */
    public static function wikipediaUrl(?string $title): ?string
    {
        return $title === null || $title === ''
            ? null
            : 'https://fr.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title));
    }

    /** Texte ramené à `$max` caractères au plus, coupé sur un mot, avec « … ». */
    public static function shorten(?string $text, int $max = 300): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $flat = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($flat) <= $max) {
            return $flat;
        }
        $cut = mb_substr($flat, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut;

        return preg_replace('/[\s,;:.]+$/u', '', $cut).'…';
    }

    /** Locale Open Graph d'une langue du site (« fr » donne « fr_FR »). */
    public static function ogLocale(string $lang): string
    {
        return ['fr' => 'fr_FR', 'en' => 'en_GB', 'de' => 'de_DE'][$lang] ?? 'fr_FR';
    }

    /**
     * Accueil : le site (avec sa recherche, qui mène à l'annuaire des lignes) et son éditeur.
     *
     * @return array<string, mixed>
     */
    public static function website(string $origin, string $name, ?string $alternateName, ?string $description, string $lang): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => self::absoluteUrl($origin, '/#website'),
                    'url' => self::absoluteUrl($origin, '/'),
                    'name' => $name,
                    'alternateName' => $alternateName,
                    'description' => $description,
                    'inLanguage' => $lang,
                    'publisher' => ['@id' => self::absoluteUrl($origin, '/#publisher')],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => self::absoluteUrl($origin, '/lines').'?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                self::PUBLISHER + ['@id' => self::absoluteUrl($origin, '/#publisher')],
            ],
        ];
    }

    /**
     * Fil d'Ariane.
     *
     * @param  list<array{name: string, path: string}>  $items  du plus général au plus précis
     * @return array<string, mixed>
     */
    public static function breadcrumb(string $origin, array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => self::absoluteUrl($origin, $item['path']),
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Média de la base (ressource MediaResource de l'API) en ImageObject, avec auteur et licence tels
     * que la base les donne. Null sans image affichable.
     *
     * @param  array<string, mixed>|null  $m
     * @return array<string, mixed>|null
     */
    public static function imageObject(?array $m): ?array
    {
        $url = $m['display_url'] ?? $m['thumb_url'] ?? null;
        if ($url === null || $url === '') {
            return null;
        }
        $thumb = $m['thumb_url'] ?? null;

        return [
            '@type' => 'ImageObject',
            'contentUrl' => $url,
            'thumbnailUrl' => $thumb !== $url ? $thumb : null,
            'name' => $m['title'] ?? null,
            'caption' => self::shorten($m['caption'] ?? null),
            'width' => ($m['width'] ?? 0) ?: null,
            'height' => ($m['height'] ?? 0) ?: null,
            'creator' => ($m['author'] ?? null) ? ['@type' => 'Person', 'name' => $m['author']] : null,
            'creditText' => $m['attribution'] ?? null,
            // `license` attend une adresse : seulement quand la base en a une (pas de lien deviné).
            'license' => $m['license_url'] ?? null,
            'copyrightNotice' => ($m['license'] ?? null) && ! ($m['license_url'] ?? null) ? $m['license'] : null,
            'acquireLicensePage' => $m['source_url'] ?? null,
        ];
    }

    /**
     * Fiche ligne : un lieu (Place) précisé par l'élément Wikidata « ligne de chemin de fer ».
     *
     * @param  array<string, mixed>  $line
     * @param  list<array<string, mixed>>  $images  médias de la ligne
     * @return array<string, mixed>
     */
    public static function line(string $origin, array $line, ?string $description, array $images): array
    {
        $url = self::absoluteUrl($origin, '/lines/'.$line['slug']);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Place',
            '@id' => $url.'#line',
            'additionalType' => self::WIKIDATA_RAILWAY_LINE,
            'url' => $url,
            'name' => $line['name'],
            'alternateName' => array_values(array_filter($line['alt_names'] ?? [], fn ($n): bool => $n && $n !== $line['name'])),
            'description' => self::shorten($description),
            'identifier' => ($line['code'] ?? null)
                ? ['@type' => 'PropertyValue', 'propertyID' => 'Code de ligne RFN', 'value' => $line['code']]
                : null,
            'sameAs' => array_values(array_filter([self::wikidataUrl($line['wikidata'] ?? null), self::wikipediaUrl($line['wikipedia_title'] ?? null)])),
            'containedInPlace' => array_map(fn ($r): array => [
                '@type' => 'AdministrativeArea',
                'name' => is_string($r) ? $r : ($r['name'] ?? null),
            ], $line['regions'] ?? []),
            'image' => array_values(array_filter(array_map(self::imageObject(...), $images))),
        ];
    }

    /**
     * Fiche gare : TrainStation, avec sa position et sa commune.
     *
     * @param  array<string, mixed>  $station
     * @return array<string, mixed>
     */
    public static function station(string $origin, array $station, ?string $description): array
    {
        $url = self::absoluteUrl($origin, '/stations/'.$station['slug']);
        // Position signalée comme douteuse (sources éloignées) : pas de coordonnées données pour sûres.
        $hasGeo = is_numeric($station['lat'] ?? null) && is_numeric($station['lng'] ?? null) && ! ($station['position_flagged'] ?? false);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'TrainStation',
            '@id' => $url.'#station',
            'url' => $url,
            'name' => $station['name'],
            'alternateName' => array_values(array_filter(
                array_map(fn ($n) => is_string($n) ? $n : ($n['name'] ?? null), $station['alt_names'] ?? []),
                fn ($n): bool => $n && $n !== $station['name'],
            )),
            'description' => self::shorten($description),
            'identifier' => ($station['uic_code'] ?? null)
                ? ['@type' => 'PropertyValue', 'propertyID' => 'UIC', 'value' => (string) $station['uic_code']]
                : null,
            'geo' => $hasGeo ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $station['lat'], 'longitude' => (float) $station['lng']] : null,
            'address' => ($station['city'] ?? null)
                ? ['@type' => 'PostalAddress', 'addressLocality' => $station['city'], 'addressCountry' => 'FR']
                : null,
            'sameAs' => array_filter([self::wikidataUrl($station['wikidata_id'] ?? null)]),
        ];
    }

    /**
     * Fiche de matériel roulant : une série décrite comme une chose (Thing) et non comme un produit,
     * car Google exige d'un Product un prix ou des avis qu'une série de matériel n'a pas. Image créditée
     * et références Wikidata et Wikipédia quand la base les a.
     *
     * @param  array<string, mixed>  $material
     * @return array<string, mixed>
     */
    public static function material(string $origin, array $material, ?string $description): array
    {
        $url = self::absoluteUrl($origin, '/materials/'.$material['slug']);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Thing',
            '@id' => $url.'#material',
            'url' => $url,
            'name' => $material['name'],
            'alternateName' => array_values(array_filter($material['designations'] ?? [], fn ($n): bool => is_string($n) && $n !== $material['name'])),
            'description' => self::shorten($description),
            'image' => self::imageObject($material['image'] ?? null),
            'sameAs' => array_values(array_filter([
                $material['wikidata_url'] ?? null,
                $material['wikipedia']['fr']['url'] ?? null,
                $material['wikipedia']['en']['url'] ?? null,
            ])),
        ];
    }

    /**
     * Fiche d'accident : un événement daté (la date garde sa précision : année, mois ou jour), situé
     * seulement quand la position est donnée par une source et pas déduite de la commune.
     *
     * @param  array<string, mixed>  $accident
     * @return array<string, mixed>
     */
    public static function accident(string $origin, array $accident, string $lang, ?string $placeText): array
    {
        $url = self::absoluteUrl($origin, '/accidents/'.$accident['slug']);
        $name = $accident['name'][$lang] ?? $accident['name']['fr'] ?? null;
        $summary = $accident['summary'][$lang] ?? $accident['summary']['fr'] ?? null;
        $place = $accident['place'] ?? [];
        $exact = is_numeric($place['lat'] ?? null) && is_numeric($place['lng'] ?? null) && ($place['precision'] ?? null) !== 'commune';
        $date = (string) ($accident['date'] ?? '');
        $startDate = match ($accident['date_precision'] ?? null) {
            'year' => substr($date, 0, 4),
            'month' => substr($date, 0, 7),
            default => $date,
        };

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            '@id' => $url.'#accident',
            'url' => $url,
            'name' => $name,
            'description' => self::shorten($summary),
            'startDate' => $startDate,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $placeText !== null || $exact ? [
                '@type' => 'Place',
                'name' => $placeText,
                'geo' => $exact ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $place['lat'], 'longitude' => (float) $place['lng']] : null,
                'address' => ($place['commune'] ?? null)
                    ? ['@type' => 'PostalAddress', 'addressLocality' => $place['commune'], 'addressCountry' => 'FR']
                    : null,
            ] : null,
            'sameAs' => array_values(array_filter(array_map(
                fn ($w): ?string => is_array($w) ? ($w['url'] ?? null) : null,
                array_values((array) ($accident['wikipedia'] ?? [])),
            ))),
        ];
    }
}
