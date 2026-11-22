<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingCorrectionsFile;
use PHPUnit\Framework\TestCase;

/** Contrôle du fichier des corrections de l'historique de la signalisation (logique pure). */
class SignallingCorrectionsFileTest extends TestCase
{
    /** @param  array<string, mixed>  $over */
    private static function entry(array $over = []): array
    {
        return $over + [
            'line' => '752000', 'action' => 'neutralize',
            'target' => ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2024-11-12'],
            'sources' => [
                ['publisher' => 'Presse A', 'title' => 'Article A', 'url' => 'https://a.example/x', 'published' => '2024-11-13'],
                ['publisher' => 'Presse B', 'title' => 'Article B', 'url' => 'https://b.example/y'],
            ],
            'quote' => 'phrase courte', 'reason' => 'le fait est faux',
        ];
    }

    private static function error(array $entry): string
    {
        $r = SignallingCorrectionsFile::parse([$entry]);
        self::assertSame([], $r['corrections']);

        return $r['errors'][0];
    }

    public function test_a_valid_neutralization_is_flattened(): void
    {
        $r = SignallingCorrectionsFile::parse([self::entry(['target' => ['source' => 'wikipedia', 'system' => 'KVB', 'date' => '1989', 'from' => ' Metz ', 'to' => 'Nancy']])]);

        $this->assertSame([], $r['errors']);
        $c = $r['corrections'][0];
        $this->assertSame(['wikipedia', 'KVB', '1989-01-01', 'Metz', 'Nancy'], array_values($c['target']));
        $this->assertNull($c['with']);
    }

    public function test_one_official_source_or_two_sites_are_required(): void
    {
        $one = [['publisher' => 'Blog', 'title' => 'Billet', 'url' => 'https://a.example/x']];
        $this->assertStringContainsString('ni source officielle', self::error(self::entry(['sources' => $one])));

        $official = [['publisher' => 'SNCF Réseau', 'title' => 'Dossier', 'url' => 'https://a.example/x', 'official' => true]];
        $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['sources' => $official])])['errors']);
    }

    public function test_bad_entries_are_refused_with_a_reason(): void
    {
        $this->assertStringContainsString('action inconnue', self::error(self::entry(['action' => 'delete'])));
        $this->assertStringContainsString('source inconnue', self::error(self::entry(['target' => ['source' => 'autre', 'system' => 'ETCS', 'date' => '2024']])));
        $this->assertStringContainsString('système inconnu', self::error(self::entry(['target' => ['source' => 'wikipedia', 'system' => 'XYZ', 'date' => '2024']])));
        $this->assertStringContainsString('date impossible', self::error(self::entry(['target' => ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2024-02-31']])));
        $this->assertStringContainsString('deux extrémités', self::error(self::entry(['target' => ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2024', 'from' => 'Metz']])));
        $this->assertStringContainsString('tiret long', self::error(self::entry(['reason' => 'faux — vraiment'])));
        $this->assertStringContainsString('https', self::error(self::entry(['sources' => [['publisher' => 'A', 'title' => 'B', 'url' => 'http://a.example'], ['publisher' => 'C', 'title' => 'D', 'url' => 'https://c.example']]])));
        $this->assertStringContainsString('manquant', self::error(['line' => '752000']));
    }

    public function test_replace_needs_a_valid_right_fact_and_neutralize_refuses_one(): void
    {
        $this->assertStringContainsString('sans fait juste', self::error(self::entry(['action' => 'replace'])));
        $this->assertStringContainsString('seulement avec l\'action replace', self::error(self::entry(['with' => ['system' => 'ETCS']])));
        $this->assertStringContainsString('fait juste refusé', self::error(self::entry(['action' => 'replace', 'with' => ['system' => 'ETCS_L2', 'date' => '2026', 'scope' => 'whole_line', 'summary' => 'x', 'sources' => []]])));

        $right = ['system' => 'ETCS_L2', 'date' => '2026-01-01', 'scope' => 'whole_line', 'summary' => 'Mise en service.', 'sources' => [
            ['publisher' => 'SNCF Réseau', 'title' => 'Communiqué', 'url' => 'https://a.example/x', 'official' => true],
        ]];
        $r = SignallingCorrectionsFile::parse([self::entry(['action' => 'replace', 'with' => $right])]);
        $this->assertSame([], $r['errors']);
        $this->assertSame('752000', $r['corrections'][0]['with']['line']);
    }

    public function test_matching_compares_source_line_system_date_and_portion(): void
    {
        $c = SignallingCorrectionsFile::parse([self::entry(['target' => ['source' => 'wikipedia', 'system' => 'BAL', 'date' => '1962-06', 'from' => 'Garches', 'to' => 'Nancy']])])['corrections'][0];

        $this->assertTrue(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000', 'rfn-752000'], 'BAL', '1962-06-01', 'garches', ' Nancy'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'manual', ['752000'], 'BAL', '1962-06-01', 'Garches', 'Nancy'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['974000'], 'BAL', '1962-06-01', 'Garches', 'Nancy'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'BAPR', '1962-06-01', 'Garches', 'Nancy'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'BAL', '1962-06-02', 'Garches', 'Nancy'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'BAL', '1962-06-01', null, null));
    }

    public function test_the_stored_correction_keeps_reason_quote_and_sources_without_the_internal_flag(): void
    {
        $c = SignallingCorrectionsFile::parse([self::entry(['sources' => [['publisher' => 'SNCF', 'title' => 'T', 'url' => 'https://a.example/x', 'official' => true]]])])['corrections'][0];
        $stored = json_decode(SignallingCorrectionsFile::stored($c), true);

        $this->assertSame(['action', 'reason', 'quote', 'sources'], array_keys($stored));
        $this->assertSame(['publisher', 'title', 'url', 'published'], array_keys($stored['sources'][0]));
    }

    public function test_the_real_file_is_valid_and_corrects_the_etcs_of_the_lgv_sud_est(): void
    {
        $entries = require dirname(__DIR__, 3).'/database/data/signalling_history_corrections.php';
        $r = SignallingCorrectionsFile::parse($entries);

        $this->assertSame([], $r['errors']);
        $this->assertContains(['752000', 'neutralize', 'wikipedia', 'ETCS', '2024-11-12'],
            array_map(fn (array $c): array => [$c['line'], $c['action'], $c['target']['source'], $c['target']['system'], $c['target']['date']], $r['corrections']));
        foreach ($r['corrections'] as $c) {
            $this->assertNotSame('', $c['reason']);
            $this->assertNotEmpty($c['sources']);
        }
    }
}
