<?php

declare(strict_types=1);

namespace App\Services\Structures;

use App\Models\Line;
use App\Services\Api\Provenance;
use App\Services\LineStations;
use Illuminate\Support\Facades\DB;

/**
 * Ouvrages d'art d'une ligne (tables `structures` et `line_structures`, `vff:import-structures`), triés
 * par PK : section « Ouvrages d'art » de la fiche ligne, `GET /api/v1/lines/{line}/structures`, champ
 * GraphQL `Line.structures` et outil MCP `line_structures`. Lecture seule.
 *
 * Tracés et longueurs : BD TOPO de l'IGN (Licence Ouverte 2.0) ; noms, dates, protection, image : Wikidata
 * (CC0), image de Wikimedia Commons sous sa propre licence (auteur et licence à reprendre).
 */
final class LineStructures
{
    public const ATTRIBUTION = 'Tracés et longueurs : IGN, BD TOPO (Licence Ouverte 2.0) ; noms, dates et protection : Wikidata (CC0) ; images : Wikimedia Commons, licence de chaque fichier.';

    public const KINDS = ['tunnel', 'viaduct', 'bridge'];

    public function __construct(private readonly Provenance $provenance) {}

    /**
     * @return array{summary: array{count: int, by_kind: array<string, int>, tunnel_length_m: int, longest: ?array{name: ?string, kind: string, length_m: int}},
     *     items: list<array<string, mixed>>}
     */
    public function forLine(int $lineId): array
    {
        return $this->forLines([$lineId])[$lineId];
    }

    /**
     * Ouvrages de plusieurs lignes en une requête (chargement groupé de GraphQL), par identifiant de ligne.
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{summary: array{count: int, by_kind: array<string, int>, tunnel_length_m: int, longest: ?array{name: ?string, kind: string, length_m: int}},
     *     items: list<array<string, mixed>>}>
     */
    public function forLines(array $lineIds): array
    {
        $rows = $lineIds === [] ? collect() : DB::table('line_structures as ls')
            ->join('structures as s', 's.id', '=', 'ls.structure_id')
            ->whereIn('ls.line_id', $lineIds)
            ->orderBy('ls.line_id')
            ->orderByRaw('ls.pk_start_m IS NULL, ls.pk_start_m, ls.distance_from_m, s.name, s.id')
            ->get([
                'ls.line_id', 'ls.pk_start_m', 'ls.pk_end_m', 'ls.distance_from_m', 'ls.distance_to_m', 'ls.offset_m', 'ls.era_years',
                's.id', 's.kind', 's.name', 's.length_m', 's.length_source', 's.lat', 's.lon', 's.built_on', 's.built_precision',
                's.opened_on', 's.opened_precision', 's.closed_on', 's.closed_precision', 's.heritage', 's.merimee_ref',
                's.image', 's.wikidata_id', 's.wikipedia_title', 's.sources',
            ]);

        $current = $rows->isEmpty() ? 0 : (int) (DB::table('eras')->where('is_current', true)->value('year') ?? 0);
        $out = [];
        foreach ($lineIds as $id) {
            $out[$id] = ['summary' => self::summary([]), 'items' => []];
        }
        foreach ($rows->groupBy('line_id') as $lineId => $lineRows) {
            $items = array_map(fn (object $r): array => self::item($r, $current), $lineRows->all());
            $out[(int) $lineId] = ['summary' => self::summary($items), 'items' => array_values($items)];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{count: int, by_kind: array<string, int>, tunnel_length_m: int, longest: ?array{name: ?string, kind: string, length_m: int}}
     */
    public static function summary(array $items): array
    {
        $byKind = array_fill_keys(self::KINDS, 0);
        $tunnels = 0;
        $longest = null;
        foreach ($items as $i) {
            $byKind[$i['kind']]++;
            if ($i['kind'] === 'tunnel') {
                $tunnels += (int) ($i['length_m'] ?? 0);
            }
            if ($i['length_m'] !== null && ($longest === null || $i['length_m'] > $longest['length_m'])) {
                $longest = ['name' => $i['name'], 'kind' => $i['kind'], 'length_m' => (int) $i['length_m']];
            }
        }

        return ['count' => count($items), 'by_kind' => $byKind, 'tunnel_length_m' => $tunnels, 'longest' => $longest];
    }

    /** @return array<string, mixed> */
    private static function item(object $r, int $currentYear): array
    {
        $date = fn (?string $d, ?string $p): ?array => $d === null ? null : ['date' => substr($d, 0, 10), 'precision' => (string) $p];
        $image = $r->image === null ? null : json_decode((string) $r->image, true);
        $years = array_map('intval', json_decode((string) $r->era_years, true) ?: []);
        $sources = json_decode((string) $r->sources, true) ?: [];

        return [
            'id' => (int) $r->id,
            'kind' => (string) $r->kind,
            'name' => $r->name,
            'length_m' => $r->length_m === null ? null : (int) $r->length_m,
            'length_source' => $r->length_source,
            'pk_start_m' => $r->pk_start_m === null ? null : (int) $r->pk_start_m,
            'pk_end_m' => $r->pk_end_m === null ? null : (int) $r->pk_end_m,
            'pk_start_label' => LineStations::pkLabel($r->pk_start_m === null ? null : (int) $r->pk_start_m),
            'pk_end_label' => LineStations::pkLabel($r->pk_end_m === null ? null : (int) $r->pk_end_m),
            'distance_from_m' => $r->distance_from_m === null ? null : (int) $r->distance_from_m,
            'distance_to_m' => $r->distance_to_m === null ? null : (int) $r->distance_to_m,
            'built' => $date($r->built_on, $r->built_precision),
            'opened' => $date($r->opened_on, $r->opened_precision),
            'closed' => $date($r->closed_on, $r->closed_precision),
            'heritage' => $r->heritage,
            'merimee_ref' => $r->merimee_ref,
            'wikidata_id' => $r->wikidata_id,
            'wikipedia_url' => $r->wikipedia_title === null ? null : 'https://fr.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', (string) $r->wikipedia_title)),
            'image' => is_array($image) ? [
                'thumb_url' => (string) ($image['thumb_url'] ?? ''),
                'width' => isset($image['width']) ? (int) $image['width'] : null,
                'height' => isset($image['height']) ? (int) $image['height'] : null,
                'file' => $image['file'] ?? null,
                'author' => $image['author'] ?? null,
                'license' => $image['license'] ?? null,
                'license_url' => $image['license_url'] ?? null,
                'source_url' => $image['source_url'] ?? null,
            ] : null,
            'era_years' => $years,
            // La ligne est en service à l'ouvrage à l'ère actuelle (faux pour une ligne fermée ou déposée).
            'current' => in_array($currentYear, $years, true),
            'lat' => round((float) $r->lat, 6),
            'lon' => round((float) $r->lon, 6),
            'offset_m' => (int) $r->offset_m,
            'sources' => array_values(array_map(fn (array $s): array => [
                'source' => (string) ($s['source'] ?? ''),
                'url' => (string) ($s['url'] ?? ''),
                'fields' => array_values(array_map('strval', (array) ($s['fields'] ?? []))),
            ], $sources)),
        ];
    }

    /**
     * Section de la fiche ligne (mêmes ouvrages, sans les sources détaillées ni les coordonnées).
     *
     * @return array{summary: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function forSheet(Line $line): array
    {
        $block = $this->forLine((int) $line->id);
        $block['items'] = array_map(fn (array $i): array => array_diff_key($i, array_flip(['sources', 'lat', 'lon', 'offset_m', 'length_source', 'era_years'])), $block['items']);

        return $block;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('structures', 'bdtopo');
    }
}
