<?php

namespace Tests\Unit\Accidents;

use App\Enums\AccidentKind;
use App\Services\Accidents\AccidentSlugTable;
use App\Services\Accidents\AccidentSourceRef;
use App\Services\Accidents\ManualAccidentCollector;
use PHPUnit\Framework\TestCase;

/**
 * Noms de fiche retenus (database/data/accident_slugs.php) et fiches rédigées à la main
 * (database/data/accident_records_manual.php) : cohérence des données versionnées et lecture.
 */
class AccidentSlugTableTest extends TestCase
{
    public function test_the_retained_slug_is_found_from_any_source_of_the_record(): void
    {
        $table = new AccidentSlugTable([['key' => 'wikipedia:fr:1834-06-21-roanne-420cada6', 'from' => '1834-06-21-roanne', 'slug' => '1834-06-21-neulise', 'reason' => 'commune corrigée']]);

        $sources = [
            new AccidentSourceRef('bea-tt', 'autre', 'report', 't', 'https://example.org', 'LO'),
            new AccidentSourceRef('wikipedia', 'fr:1834-06-21-roanne-420cada6', 'list', 't', 'https://example.org', 'CC'),
        ];
        $this->assertSame('1834-06-21-neulise', $table->retained($sources));
        $this->assertNull($table->retained([$sources[0]]));
        $this->assertTrue($table->isRetired('1834-06-21-roanne'));
        $this->assertFalse($table->isRetired('1834-06-21-neulise'));
    }

    public function test_the_versioned_table_is_consistent_with_the_context_files(): void
    {
        $rows = (new AccidentSlugTable)->rows();
        $this->assertNotEmpty($rows);
        $dir = dirname(__DIR__, 3).'/database/data/accident_contexts';
        $this->assertCount(count($rows), array_unique(array_column($rows, 'key')), 'une source ne figure qu\'une fois');
        $this->assertCount(count($rows), array_unique(array_column($rows, 'slug')), 'un slug retenu ne figure qu\'une fois');
        foreach ($rows as $row) {
            $this->assertNotSame($row['from'], $row['slug'], $row['key']);
            $this->assertNotSame('', trim($row['reason']), $row['key']);
            $this->assertMatchesRegularExpression('/^\d{4}(-\d{2}){0,2}-[a-z0-9-]+$/', $row['slug']);
            $this->assertFileExists("{$dir}/{$row['slug']}.php", "le contexte de {$row['slug']} porte le nom retenu");
            $this->assertFileDoesNotExist("{$dir}/{$row['from']}.php", "l'ancien nom {$row['from']} n'a plus de contexte");
            $this->assertStringContainsString(':', $row['key']);
        }
        $retained = array_column($rows, 'slug');
        foreach ($rows as $row) {
            $this->assertNotContains($row['from'], $retained, 'un ancien nom n\'est le nom retenu d\'aucune fiche');
        }
    }

    public function test_the_hand_written_records_are_valid(): void
    {
        $records = require dirname(__DIR__, 3).'/database/data/accident_records_manual.php';
        $providers = array_keys((require dirname(__DIR__, 3).'/database/data/source_rights.php')['providers']);
        $ids = array_column($records, 'id');
        $this->assertCount(count($ids), array_unique($ids));
        foreach ($records as $r) {
            $this->assertMatchesRegularExpression('/^\d{4}(-\d{2}){0,2}$/', $r['date']);
            $this->assertNotNull(AccidentKind::tryFrom($r['kind']), $r['id']);
            $this->assertContains($r['source']['provider'], $providers, "{$r['id']} : fournisseur déclaré dans source_rights.php");
            $this->assertStringStartsWith('https://', $r['source']['url']);
            $this->assertNotSame('', $r['source']['licence']);
            $this->assertLessThanOrEqual(80, mb_strlen($r['source']['licence']), 'accident_sources.licence tient en 80 caractères');
            $this->assertLessThanOrEqual(80, strlen($r['id']), 'accident_sources.external_id tient en 80 caractères');
            $this->assertStringNotContainsString('—', json_encode($r, JSON_UNESCAPED_UNICODE) ?: '');
        }
        $this->assertContains('1872-06-20-juvisy-sur-orge', $ids);
    }

    public function test_a_record_becomes_a_candidate_with_its_source(): void
    {
        $collector = new ManualAccidentCollector([[
            'id' => '1872-06-20-juvisy-sur-orge', 'date' => '1872-06-20', 'commune' => 'Juvisy-sur-Orge', 'department' => '91', 'kind' => 'collision',
            'deaths' => 5, 'injuries' => null,
            'source' => ['provider' => 'delpher', 'title' => 'T', 'url' => 'https://resolver.kb.nl/x', 'licence' => 'Faits seulement'],
        ]]);

        [$c] = $collector->collect();

        $this->assertSame('manual:1872-06-20-juvisy-sur-orge', $c->key);
        $this->assertSame(['1872-06-20', 'day', 'collision', 'Juvisy-sur-Orge', '91'], [$c->date, $c->precision?->value, $c->kind?->value, $c->communeName, $c->departmentCode]);
        $this->assertSame(['manual' => 5], $c->deaths);
        $this->assertSame([], $c->injuries);
        $this->assertTrue($c->hasNote('place_from_manual'));
        $this->assertSame(['delpher', '1872-06-20-juvisy-sur-orge', 'press'], [$c->sources[0]->provider, $c->sources[0]->externalId, $c->sources[0]->role]);
    }
}
