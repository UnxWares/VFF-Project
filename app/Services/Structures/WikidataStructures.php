<?php

declare(strict_types=1);

namespace App\Services\Structures;

use App\Services\Media\CommonsClient;

/**
 * Ouvrages ferroviaires de France dans Wikidata (CC0) : tunnels, ponts et viaducs ferroviaires, et ponts,
 * viaducs ou tunnels génériques (ceux-là ne seront gardés que s'ils coïncident avec une voie en tunnel ou
 * sur pont de la BD TOPO : StructureImporter).
 *
 * Deux temps, pour rester sous la limite de temps du service SPARQL : la liste des éléments (classes
 * directes, sans parcours de sous-classes), puis leurs propriétés par lots de BATCH éléments. Propriétés
 * lues : libellé (français, sinon anglais), coordonnées (P625), longueur (P2043, en mètres ou en
 * kilomètres), construction (P571), mise en service (P729, à défaut inauguration P1619), fermeture
 * (P730, à défaut fin d'existence P576), protection (P1435 : classé ou inscrit monument historique ; à
 * défaut, notice Mérimée « PA » : protégé sans plus de précision),
 * référence Mérimée (P380), image (P18), article de Wikipédia en français.
 */
final class WikidataStructures
{
    public const BATCH = 150;

    /** Classes propres aux ouvrages ferroviaires, par type d'ouvrage de VFF. */
    public const RAIL_CLASSES = [
        'Q1311958' => 'tunnel',   // tunnel ferroviaire
        'Q109445423' => 'tunnel', // tunnel ferroviaire à grande vitesse
        'Q1210334' => 'bridge',   // pont ferroviaire
        'Q108893042' => 'bridge', // pont ferroviaire à grande vitesse
        'Q39486269' => 'viaduct', // viaduc ferroviaire
        'Q109274653' => 'viaduct', // viaduc ferroviaire à grande vitesse
    ];

    /** Classes génériques (route et rail confondus). */
    public const GENERIC_CLASSES = [
        'Q44377' => 'tunnel',   // tunnel
        'Q12280' => 'bridge',   // pont
        'Q158438' => 'bridge',  // pont en arc
        'Q181348' => 'viaduct', // viaduc
    ];

    /**
     * Statuts de protection (P1435) des monuments historiques, libellés de Wikidata relus le 23/11/2026 : Q10387684
     * « monument historique classé », Q10387575 « monument historique inscrit », Q916475 « monument historique »
     * (protégé, sans dire s'il est classé ou inscrit).
     */
    public const HERITAGE = [
        'Q10387684' => 'classified',
        'Q10387575' => 'registered',
        'Q916475' => 'protected',
    ];

    /** Ordre de précision : le classement l'emporte sur l'inscription, qui l'emporte sur la protection seule. */
    private const HERITAGE_RANK = ['protected' => 1, 'registered' => 2, 'classified' => 3];

    private const UNIT_M = 'Q11573';

    private const UNIT_KM = 'Q828224';

    public function __construct(private readonly CommonsClient $client) {}

    /**
     * Éléments situés en France (P17) de l'une des classes, avec leurs classes.
     *
     * @return array<string, list<string>> identifiant Q → classes (identifiants Q)
     */
    public function listItems(): array
    {
        $classes = implode(' ', array_map(fn (string $q): string => 'wd:'.$q, array_keys(self::RAIL_CLASSES + self::GENERIC_CLASSES)));
        $rows = $this->client->sparql(<<<SPARQL
            SELECT ?item ?c WHERE {
              VALUES ?c { {$classes} }
              ?item wdt:P31 ?c ; wdt:P17 wd:Q142 .
            }
            SPARQL);
        $out = [];
        foreach ($rows as $row) {
            $item = self::qid($row['item']['value'] ?? '');
            $class = self::qid($row['c']['value'] ?? '');
            if ($item !== null && $class !== null) {
                $out[$item][] = $class;
            }
        }

        return $out;
    }

    /**
     * Propriétés des éléments, par lots.
     *
     * @param  list<string>  $ids
     * @return list<array<string, mixed>> lignes SPARQL brutes (une par élément)
     */
    public function details(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, self::BATCH) as $chunk) {
            $values = implode(' ', array_map(fn (string $q): string => 'wd:'.$q, $chunk));
            $times = implode("\n", array_map(
                fn (string $p, string $v): string => "OPTIONAL { ?item p:{$p}/psv:{$p} ?{$v}N . ?{$v}N wikibase:timeValue ?{$v}T ; wikibase:timePrecision ?{$v}P . BIND(CONCAT(STR(?{$v}T), '|', STR(?{$v}P)) AS ?{$v}) }",
                ['P571', 'P729', 'P1619', 'P730', 'P576'],
                ['inc', 'open', 'inau', 'ret', 'dis'],
            ));
            $rows = $this->client->sparql(<<<SPARQL
                SELECT ?item (SAMPLE(?lfr) AS ?labelFr) (SAMPLE(?len) AS ?labelEn) (SAMPLE(?coord) AS ?coords)
                  (SAMPLE(?length) AS ?lengths) (MIN(?inc) AS ?inception) (MIN(?open) AS ?opened) (MIN(?inau) AS ?inaugurated)
                  (MAX(?ret) AS ?retired) (MAX(?dis) AS ?dissolved) (SAMPLE(?mer) AS ?merimee)
                  (GROUP_CONCAT(DISTINCT STR(?her); separator='|') AS ?heritage) (SAMPLE(?img) AS ?image) (SAMPLE(?art) AS ?article)
                WHERE {
                  VALUES ?item { {$values} }
                  OPTIONAL { ?item rdfs:label ?lfr . FILTER(LANG(?lfr) = 'fr') }
                  OPTIONAL { ?item rdfs:label ?len . FILTER(LANG(?len) = 'en') }
                  OPTIONAL { ?item wdt:P625 ?coord }
                  OPTIONAL { ?item p:P2043/psv:P2043 ?lenN . ?lenN wikibase:quantityAmount ?lenA ; wikibase:quantityUnit ?lenU .
                             BIND(CONCAT(STR(?lenA), '|', STR(?lenU)) AS ?length) }
                  {$times}
                  OPTIONAL { ?item wdt:P380 ?mer }
                  OPTIONAL { ?item wdt:P1435 ?her }
                  OPTIONAL { ?item wdt:P18 ?img }
                  OPTIONAL { ?art schema:about ?item ; schema:isPartOf <https://fr.wikipedia.org/> }
                } GROUP BY ?item
                SPARQL);
            foreach ($rows as $row) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Ligne SPARQL (details) et classes de l'élément ramenées aux champs de VFF. Null sans coordonnées
     * lisibles (l'ouvrage ne peut être rattaché à aucune ligne).
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $classes
     * @return array{wikidata_id: string, kind: string, rail_class: bool, name: ?string, lon: float, lat: float,
     *     length_m: ?int, built: ?array{date: string, precision: string}, opened: ?array{date: string, precision: string},
     *     closed: ?array{date: string, precision: string}, heritage: ?string, merimee_ref: ?string,
     *     commons_file: ?string, wikipedia_title: ?string}|null
     */
    public static function normalize(array $row, array $classes): ?array
    {
        $v = fn (string $k): ?string => isset($row[$k]['value']) && trim((string) $row[$k]['value']) !== '' ? trim((string) $row[$k]['value']) : null;
        $id = self::qid($v('item') ?? '');
        if ($id === null || ! preg_match('/^Point\(([-0-9.eE]+) ([-0-9.eE]+)\)$/', $v('coords') ?? '', $m)) {
            return null;
        }

        $railKinds = array_values(array_intersect_key(self::RAIL_CLASSES, array_flip($classes)));
        $genericKinds = array_values(array_intersect_key(self::GENERIC_CLASSES, array_flip($classes)));
        $kinds = $railKinds !== [] ? $railKinds : $genericKinds;
        if ($kinds === []) {
            return null;
        }
        // Un élément à la fois « pont ferroviaire » et « viaduc » est un viaduc.
        $kind = in_array('tunnel', $kinds, true) ? 'tunnel' : (in_array('viaduct', [...$railKinds, ...$genericKinds], true) ? 'viaduct' : 'bridge');
        $name = $v('labelFr') ?? $v('labelEn');
        if ($kind === 'bridge' && $name !== null && preg_match('/^viaduc\b/iu', $name)) {
            $kind = 'viaduct';
        }

        $heritage = null;
        foreach (explode('|', $v('heritage') ?? '') as $status) {
            $h = self::HERITAGE[self::qid($status) ?? ''] ?? null;
            // Un ouvrage peut avoir été inscrit puis classé : le statut le plus précis l'emporte.
            if ($h !== null && self::HERITAGE_RANK[$h] > ($heritage === null ? 0 : self::HERITAGE_RANK[$heritage])) {
                $heritage = $h;
            }
        }

        // Notice Mérimée « PA » : monument historique protégé, sans dire s'il est classé ou inscrit.
        if ($heritage === null && str_starts_with((string) $v('merimee'), 'PA')) {
            $heritage = 'protected';
        }

        $image = $v('image');
        $article = $v('article');

        return [
            'wikidata_id' => $id,
            'kind' => $kind,
            'rail_class' => $railKinds !== [],
            'name' => $name === $id ? null : $name,
            'lon' => (float) $m[1],
            'lat' => (float) $m[2],
            'length_m' => self::length($v('lengths')),
            'built' => self::date($v('inception')),
            'opened' => self::date($v('opened')) ?? self::date($v('inaugurated')),
            'closed' => self::date($v('retired')) ?? self::date($v('dissolved')),
            'heritage' => $heritage,
            'merimee_ref' => $v('merimee'),
            'commons_file' => $image === null ? null : str_replace('_', ' ', rawurldecode((string) preg_replace('#^.*/Special:FilePath/#', '', $image))),
            'wikipedia_title' => $article === null ? null : str_replace('_', ' ', rawurldecode((string) preg_replace('#^https://fr\.wikipedia\.org/wiki/#', '', $article))),
        ];
    }

    /**
     * Date Wikidata « 1884-01-01T00:00:00Z|9 » en date et précision de VFF (année, mois, jour). Les dates
     * moins précises que l'année (décennie, siècle) ne sont pas gardées.
     *
     * @return array{date: string, precision: string}|null
     */
    public static function date(?string $value): ?array
    {
        if ($value === null || ! preg_match('/^\+?(\d{4})-(\d{2})-(\d{2})T[^|]*\|(\d+)$/', $value, $m)) {
            return null;
        }
        $precision = match ((int) $m[4]) {
            9 => 'year',
            10 => 'month',
            11, 12, 13, 14 => 'day',
            default => null,
        };
        if ($precision === null) {
            return null;
        }
        $month = $precision === 'year' ? '01' : $m[2];
        $day = $precision === 'day' ? $m[3] : '01';
        if (! checkdate((int) $month, (int) $day, (int) $m[1])) {
            return null;
        }

        return ['date' => "{$m[1]}-{$month}-{$day}", 'precision' => $precision];
    }

    /** Longueur « 564|http://www.wikidata.org/entity/Q11573 » en mètres ; null pour une autre unité. */
    public static function length(?string $value): ?int
    {
        if ($value === null || ! str_contains($value, '|')) {
            return null;
        }
        [$amount, $unit] = explode('|', $value, 2);
        $metres = match (self::qid($unit)) {
            self::UNIT_M => (float) $amount,
            self::UNIT_KM => (float) $amount * 1000,
            default => null,
        };

        return $metres === null || $metres <= 0 ? null : (int) round($metres);
    }

    private static function qid(string $uri): ?string
    {
        return preg_match('#(Q\d+)$#', $uri, $m) ? $m[1] : null;
    }
}
