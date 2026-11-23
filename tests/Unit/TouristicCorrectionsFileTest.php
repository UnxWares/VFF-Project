<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Corrections de sections des exploitations touristiques (`line_event_corrections_touristiques.php`) : chacune déplace
 * une exploitation de l'import vers les lieux que donne l'article Wikipédia, avec sa version et sa citation.
 */
class TouristicCorrectionsFileTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function corrections(): array
    {
        return require dirname(__DIR__, 2).'/database/data/line_event_corrections_touristiques.php';
    }

    public function test_every_relocation_moves_a_heritage_start_with_both_ends_and_a_cited_article_version(): void
    {
        $relocations = array_filter($this->corrections(), fn (array $c): bool => $c['action'] === 'relocate');
        $this->assertGreaterThanOrEqual(8, count($relocations));
        foreach ($relocations as $c) {
            $this->assertSame(['source', 'type', 'date'], array_slice(array_keys($c['match']), 0, 3), $c['line']);
            $this->assertSame('heritage', $c['match']['source'], $c['line']);
            $this->assertSame('heritage_start', $c['match']['type'], $c['line']);
            $this->assertNotSame('', trim($c['from']), $c['line']);
            $this->assertNotSame('', trim($c['to']), $c['line']);
            $this->assertNotSame($c['from'], $c['to'], $c['line']);
            $this->assertMatchesRegularExpression('#^https://fr\.wikipedia\.org/w/index\.php\?title=[^&]+&oldid=\d+$#', $c['source_url'], $c['line']);
            $this->assertNotSame('', trim($c['quote']), $c['line']);
            $this->assertNotSame('', trim($c['reason']), $c['line']);
        }
    }

    public function test_the_seven_incoherences_are_settled_each_with_a_source_a_quote_and_a_reason(): void
    {
        $byLineAction = [];
        foreach ($this->corrections() as $c) {
            $byLineAction[$c['line'].' '.$c['action']] = $c;
            $this->assertMatchesRegularExpression('#^https://#', $c['source_url'], $c['line']);
            $this->assertNotSame('', trim($c['quote'] ?? ''), $c['line']);
            $this->assertGreaterThan(40, mb_strlen($c['reason']), $c['line'].' : la raison cite les deux sources');
        }
        foreach (['751000 neutralize', '210000 redate', '474000 redate', '475000 redate', '475000 add', '487000 relocate', '785000 relocate'] as $key) {
            $this->assertArrayHasKey($key, $byLineAction, $key);
        }
        // Le début des Centre-Bretagne est le 28 juin 2021 sur Auray - Pontivy, et la ligne de Saint-Brieuc finit en 2017.
        $this->assertSame('2021-06-28', $byLineAction['474000 redate']['date']);
        $this->assertSame('Auray', $byLineAction['474000 redate']['to']);
        $this->assertSame('2017-01-01', $byLineAction['475000 add']['date']);
        $this->assertSame('heritage_end', $byLineAction['475000 add']['type']);
        $this->assertSame('tourist_train', $byLineAction['475000 add']['heritage_kind']);
        // Les sections redatées visent une année ou un jour valides ; les PK de Bon-Repos font 1 700 m de voie de 60 cm.
        foreach (['210000 redate', '474000 redate', '475000 redate'] as $key) {
            $this->assertTrue(checkdate((int) substr($byLineAction[$key]['date'], 5, 2), (int) substr($byLineAction[$key]['date'], 8, 2), (int) substr($byLineAction[$key]['date'], 0, 4)), $key);
            $this->assertContains($byLineAction[$key]['precision'], ['day', 'month', 'year'], $key);
            $this->assertStringContainsString('depuis', $byLineAction[$key]['date_text'], $key);
        }
        $this->assertSame(1700, $byLineAction['487000 relocate']['pk_end'] - $byLineAction['487000 relocate']['pk_start']);
        $this->assertSame('Ambert', $byLineAction['785000 relocate']['from']);
    }

    public function test_the_target_lines_are_distinct_or_distinguished_by_their_start_place(): void
    {
        $seen = [];
        foreach ($this->corrections() as $c) {
            if ($c['action'] === 'add') {
                continue;
            }
            $key = $c['line'].'|'.$c['match']['date'].'|'.($c['match']['from'] ?? '');
            $this->assertArrayNotHasKey($key, $seen, 'événement visé deux fois : '.$key);
            $seen[$key] = true;
        }
    }
}
