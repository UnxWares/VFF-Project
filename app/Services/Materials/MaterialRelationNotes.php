<?php

namespace App\Services\Materials;

use App\Support\SourceRights;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\DB;

/**
 * Faits sourcés sur une relation assurée par une série (database/data/material_relation_notes.php), comme
 * « au biodiesel B100 (colza) depuis avril 2021 » pour le Régiolis sur Paris - Granville. Ils s'ajoutent, en
 * `notes`, aux relations que LineMaterial::relations() lit dans les preuves des liens : une note vaut pour les
 * relations de la série dont les extrémités sont les deux lieux du fichier, sur les lignes qu'il cite.
 *
 * Lu une fois par requête (codes RFN et éléments Wikidata traduits en identifiants), rien n'est écrit en base.
 */
#[Scoped]
final class MaterialRelationNotes
{
    /** @var array<int, array<int, list<array<string, mixed>>>>|null série → ligne → notes du fichier */
    private ?array $index = null;

    /** @param list<array<string, mixed>>|null $data entrées du fichier (null : database/data/material_relation_notes.php) */
    public function __construct(private ?array $data = null) {}

    /**
     * Notes d'une relation de la série sur la ligne (forme servie par l'API, sans les champs de rattachement).
     *
     * @param  array{from?: ?array{id?: ?int, name: string}, to?: ?array{id?: ?int, name: string}}  $relation
     * @return list<array<string, mixed>>
     */
    public function for(int $lineId, int $materialId, array $relation): array
    {
        $entries = $this->index()[$materialId][$lineId] ?? [];
        if ($entries === []) {
            return [];
        }
        $ends = [RelationRouter::key((string) ($relation['from']['name'] ?? '')), RelationRouter::key((string) ($relation['to']['name'] ?? ''))];
        $out = [];
        foreach ($entries as $entry) {
            [$a, $b] = array_map(RelationRouter::key(...), $entry['relation']);
            if ((self::names($a, $ends[0]) && self::names($b, $ends[1])) || (self::names($a, $ends[1]) && self::names($b, $ends[0]))) {
                $out[] = self::served($entry);
            }
        }

        return $out;
    }

    /** Le lieu du fichier (clé comparable) désigne-t-il cette gare ? Nom exact, ou ville dont la gare porte le nom (« Paris » → « Paris-Montparnasse »). */
    private static function names(string $place, string $station): bool
    {
        return $place !== '' && ($station === $place || str_starts_with($station, $place.'-'));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function served(array $entry): array
    {
        $rights = new SourceRights;
        $sources = [];
        foreach ($entry['sources'] as $source) {
            $provider = $rights->providerForUrl($source['url']);
            $sources[] = [
                'title' => $source['title'],
                'publisher' => $source['publisher'],
                'date' => $source['date'],
                'url' => $source['url'],
                'provider' => $provider,
                'license' => $provider !== null ? ($rights->provider($provider)['licence'] ?? null) : null,
                'license_status' => $provider !== null ? $rights->status($provider) : null,
            ];
        }

        return [
            'kind' => $entry['kind'],
            'fuel' => $entry['fuel'] ?? null,
            'feedstock' => $entry['feedstock'] ?? null,
            'since' => $entry['since'] ?? null,
            'since_precision' => $entry['since_precision'] ?? null,
            'reported' => $entry['reported'] ?? null,
            'reported_precision' => $entry['reported_precision'] ?? null,
            'sources' => $sources,
        ];
    }

    /** @return array<int, array<int, list<array<string, mixed>>>> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }
        /** @var list<array<string, mixed>> $data */
        $data = $this->data ?? require database_path('data/material_relation_notes.php');
        $this->index = [];
        if ($data === []) {
            return $this->index;
        }
        $materials = DB::table('materials')->whereIn('wikidata_id', array_column($data, 'material'))->pluck('id', 'wikidata_id')->all();
        $codes = array_merge(...array_column($data, 'lines'));
        $lines = [];
        foreach (DB::table('lines')->whereIn('code', $codes)->get(['id', 'code']) as $row) {
            $lines[(string) $row->code][] = (int) $row->id;
        }
        foreach ($data as $entry) {
            $materialId = $materials[$entry['material']] ?? null;
            if ($materialId === null) {
                continue;
            }
            foreach ($entry['lines'] as $code) {
                foreach ($lines[$code] ?? [] as $lineId) {
                    $this->index[(int) $materialId][$lineId][] = $entry;
                }
            }
        }

        return $this->index;
    }
}
