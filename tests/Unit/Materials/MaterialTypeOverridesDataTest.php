<?php

namespace Tests\Unit\Materials;

use App\Enums\MaterialType;
use App\Support\MaterialTypeOverrides;
use Tests\TestCase;

/** Forme du fichier des catégories posées à la main (database/data/material_type_overrides.php) et de sa lecture. */
class MaterialTypeOverridesDataTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function rows(): array
    {
        return require __DIR__.'/../../../database/data/material_type_overrides.php';
    }

    public function test_every_row_is_complete_and_valid(): void
    {
        $rows = self::rows();
        $this->assertNotEmpty($rows);
        foreach ($rows as $i => $row) {
            $where = "ligne {$i} (".($row['material'] ?? '?').')';
            foreach (['material', 'type', 'source', 'quote', 'wikidata'] as $key) {
                $this->assertArrayHasKey($key, $row, "{$where} : {$key}");
                $this->assertIsString($row[$key], "{$where} : {$key}");
                $this->assertNotSame('', trim($row[$key]), "{$where} : {$key} vide");
            }
            $this->assertMatchesRegularExpression('/^Q[1-9]\d{0,9}$/', $row['material'], $where);
            $this->assertNotNull(MaterialType::tryFrom($row['type']), "{$where} : catégorie inconnue");
            $this->assertMatchesRegularExpression('#^https://[a-z.]+\.(wikipedia|wikidata)\.org/#', $row['source'], "{$where} : permalien");
            $this->assertStringNotContainsString('—', json_encode($row, JSON_UNESCAPED_UNICODE) ?: '', "{$where} : tiret long");
            $this->assertStringNotContainsString(' -- ', json_encode($row, JSON_UNESCAPED_UNICODE) ?: '', "{$where} : double tiret");
            if (isset($row['name'])) {
                $this->assertSame(trim($row['name']), $row['name'], "{$where} : nom");
                $this->assertNotSame('', $row['name'], "{$where} : nom vide");
            }
        }
    }

    public function test_each_series_appears_once_and_lookup_finds_it(): void
    {
        $ids = array_column(self::rows(), 'material');
        $this->assertSame($ids, array_values(array_unique($ids)), 'série en double');
        foreach (self::rows() as $row) {
            $found = MaterialTypeOverrides::for($row['material']);
            $this->assertNotNull($found, $row['material']);
            $this->assertSame([MaterialType::from($row['type']), $row['source']], $found);
            $this->assertSame($row['name'] ?? null, MaterialTypeOverrides::nameFor($row['material']));
        }
    }

    public function test_unknown_and_malformed_identifiers_have_no_override(): void
    {
        foreach (['Q0', 'Q999999999999', '', 'q2876837', ' Q2876837', 'Q2876837 ', "Q2876837\0", str_repeat('Q', 5000)] as $qid) {
            $this->assertNull(MaterialTypeOverrides::for($qid), $qid);
            $this->assertNull(MaterialTypeOverrides::nameFor($qid), $qid);
        }
    }
}
