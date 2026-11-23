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

    public function test_every_correction_relocates_a_heritage_start_with_both_ends_and_a_cited_article_version(): void
    {
        $this->assertGreaterThanOrEqual(6, count($this->corrections()));
        foreach ($this->corrections() as $c) {
            $this->assertSame('relocate', $c['action'], $c['line']);
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

    public function test_the_target_lines_are_distinct_or_distinguished_by_their_start_place(): void
    {
        $seen = [];
        foreach ($this->corrections() as $c) {
            $key = $c['line'].'|'.$c['match']['date'].'|'.($c['match']['from'] ?? '');
            $this->assertArrayNotHasKey($key, $seen, 'événement visé deux fois : '.$key);
            $seen[$key] = true;
        }
    }
}
