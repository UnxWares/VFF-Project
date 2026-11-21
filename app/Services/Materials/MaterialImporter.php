<?php

namespace App\Services\Materials;

use App\Models\Material;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Écrit les fiches de matériel lues dans Wikidata (WikidataMaterialMapper), de façon idempotente.
 *
 * Une fiche est retrouvée par son `wikidata_id`. Un champ n'est remplacé que s'il vient déjà de
 * Wikidata ou de Commons (provenance) ou s'il est vide : une valeur saisie ou corrigée dans VFF
 * n'est jamais écrasée, et un champ qu'une contribution validée a effacé (provenance `contrib`) n'est
 * pas rempli de nouveau. Un champ importé que la source ne donne plus est vidé. Le slug d'une fiche
 * existante ne change jamais (adresses stables) ; une nouvelle fiche prend le slug de son nom,
 * suffixé de l'identifiant Wikidata en cas d'homonymie.
 */
final class MaterialImporter
{
    /** Sources dont l'import peut mettre à jour la valeur (Wikipédia : catégorie bimode et état d'utilisation d'après l'infobox). */
    private const IMPORTED = ['wikidata', 'commons', 'wikipedia'];

    /** Champ sans provenance propre : celle d'un autre champ fait foi. */
    private const COMPANION = ['service_status_checked_at' => 'service_status'];

    /** Champs écrits par l'import (hors identifiant, slug, provenance et dates techniques). */
    private const FIELDS = [
        'name', 'type', 'designations', 'description', 'builder', 'builders', 'operators', 'in_service_from',
        'in_service_to', 'units_built', 'max_speed_kmh', 'power_kw', 'electrification', 'gauges', 'gauge_mm',
        'wheel_arrangement', 'fleet_numbers', 'image', 'commons_category', 'wikipedia_fr', 'wikipedia_en',
        'service_status', 'service_status_checked_at', 'scope',
    ];

    /**
     * `$dryRun` : rien n'est écrit, le bilan dit ce qui changerait. `fields` : nombre de fiches existantes dont
     * chaque champ changerait (provenance comprise).
     *
     * @param  list<array<string, mixed>>  $records  attributs produits par WikidataMaterialMapper
     * @return array{created: int, updated: int, unchanged: int, fields: array<string, int>}
     */
    public function import(array $records, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'fields' => []];

        DB::transaction(function () use ($records, $dryRun, &$stats): void {
            $existing = Material::query()->whereIn('wikidata_id', array_column($records, 'wikidata_id'))->get()->keyBy('wikidata_id');
            foreach ($records as $record) {
                $material = $existing[$record['wikidata_id']] ?? null;
                if ($material === null) {
                    if (! $dryRun) {
                        $this->create($record);
                    }
                    $stats['created']++;

                    continue;
                }
                $changed = $this->update($material, $record, $dryRun);
                foreach ($changed as $field) {
                    $stats['fields'][$field] = ($stats['fields'][$field] ?? 0) + 1;
                }
                $stats[$changed !== [] ? 'updated' : 'unchanged']++;
            }
        });
        ksort($stats['fields']);

        return $stats;
    }

    /** @param array<string, mixed> $record */
    private function create(array $record): void
    {
        $values = array_intersect_key($record, array_flip(self::FIELDS));
        Material::query()->create($values + [
            'wikidata_id' => $record['wikidata_id'],
            'slug' => $this->uniqueSlug((string) $record['name'], (string) $record['wikidata_id']),
            'source' => 'wikidata',
            'provenance' => $record['provenance'],
            'fetched_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string> champs modifiés (rien n'est enregistré avec `$dryRun`)
     */
    private function update(Material $material, array $record, bool $dryRun = false): array
    {
        $original = $material->provenance ?? [];
        $provenance = $original;
        foreach (self::FIELDS as $field) {
            $current = $material->getAttribute($field);
            // La date de vérification d'un état d'utilisation suit la provenance de cet état.
            $owner = self::COMPANION[$field] ?? $field;
            // Le périmètre est toujours calculé par l'import (jamais saisi dans VFF).
            $owned = $field === 'scope' || in_array($original[$owner]['source'] ?? null, self::IMPORTED, true);
            $empty = $current === null || $current === [] || $current === '';
            // Une valeur effacée par une contribution validée (vide, provenance `contrib`) reste vide.
            $contributed = ($original[$owner]['source'] ?? null) === 'contrib';
            if ($contributed || (! $owned && ! $empty)) {
                continue;
            }
            $value = $record[$field] ?? ($field === 'scope' ? 'france' : null);
            if (! JsonEquality::same($current instanceof \BackedEnum ? $current->value : $current, $value)) {
                $material->setAttribute($field, $value);
            }
            if (isset($record['provenance'][$field])) {
                $provenance[$field] = $record['provenance'][$field];
            } else {
                unset($provenance[$field]);
            }
        }
        if (! JsonEquality::same($material->provenance, $provenance)) {
            $material->provenance = $provenance;
        }
        $material->source ??= 'wikidata';
        $changed = array_keys($material->getDirty());
        if (! $dryRun) {
            $material->fetched_at = now();
            $material->save();
        }

        return $changed;
    }

    private function uniqueSlug(string $name, string $qid): string
    {
        $base = Str::slug($name) ?: strtolower($qid);
        if (! Material::query()->where('slug', $base)->exists()) {
            return $base;
        }

        return $base.'-'.strtolower($qid);
    }
}
