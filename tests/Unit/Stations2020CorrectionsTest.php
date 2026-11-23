<?php

namespace Tests\Unit;

use App\Services\StationCorrections;
use App\Services\StationTimeline;
use PHPUnit\Framework\TestCase;

/**
 * Gares fréquentées en 2020 (série SNCF) absentes de l'ère 2020 (chantier « gares-2020 », 23/11/2026) : ouvertures de
 * Chilly-Mazarin, Gravigny-Balizy (date du tram-train T12 lue comme celle de la gare) et Dugny - La Courneuve (sans date),
 * fermeture d'une autre ligne lue sur Saintes - Royan, réouverture de 2005 de Cannes - Grasse, ouverture de la Tangentielle
 * Nord. Données seules, sans base : forme, sources et effet sur la chronologie.
 */
class Stations2020CorrectionsTest extends TestCase
{
    /** @return list<array<string, mixed>> corrections de gares visant $name */
    private static function stationCorrections(string $name): array
    {
        /** @var list<array<string, mixed>> $all */
        $all = require dirname(__DIR__, 2).'/database/data/gares_corrections.php';

        return array_values(array_filter($all, fn (array $c) => ($c['station']['name'] ?? null) === $name));
    }

    /** @return array<string, mixed>|null correction de ligne (action, type, date) */
    private static function lineCorrection(string $line, string $action, string $type, string $date): ?array
    {
        /** @var list<array<string, mixed>> $all */
        $all = require dirname(__DIR__, 2).'/database/data/line_event_corrections_sourcees.php';
        foreach ($all as $c) {
            $t = $c['type'] ?? $c['match']['type'] ?? null;
            $d = $c['date'] ?? $c['match']['date'] ?? null;
            if ($c['line'] === $line && $c['action'] === $action && $t === $type && $d === $date) {
                return $c;
            }
        }

        return null;
    }

    public function test_t12_stations_open_with_the_grande_ceinture_in_1883(): void
    {
        foreach (['Chilly-Mazarin', 'Gravigny-Balizy'] as $name) {
            $mine = self::stationCorrections($name);
            $this->assertCount(2, $mine, $name);
            foreach ($mine as $c) {
                $this->assertSame([], StationCorrections::validate($c), $name);
            }
            [$neutralize, $add] = $mine;
            $this->assertSame(['source' => 'wikidata', 'type' => 'opening', 'date' => '2023-12-10'], $neutralize['match']);
            $this->assertMatchesRegularExpression('~^https://www\.wikidata\.org/w/index\.php\?title=Q\d+&oldid=\d+$~', $neutralize['source_url']);
            $this->assertSame(['opening', '1883-05-01', 'day'], [$add['type'], $add['date'], $add['precision']]);
            $this->assertStringContainsString('oldid=', $add['source_url']);
        }
    }

    public function test_the_t12_date_alone_hid_the_stations_in_2020_and_the_1883_opening_shows_them(): void
    {
        $t12 = [['type' => 'opening', 'date' => '2023-12-10']];
        $this->assertNull(StationTimeline::statusFromEvents($t12, 2020, true));
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents([['type' => 'opening', 'date' => '1883-05-01']], 2020, true));
        // Sans aucune date (Dugny - La Courneuve avant la correction) : pas de gare à l'ère.
        $this->assertNull(StationTimeline::statusFromEvents([], 2020, true));
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents([['type' => 'opening', 'date' => '2017-07-01']], 2020, true));
    }

    public function test_dugny_opens_with_the_t11_in_2017(): void
    {
        $mine = self::stationCorrections('Dugny-La Courneuve TT');
        $this->assertCount(1, $mine);
        $this->assertSame([], StationCorrections::validate($mine[0]));
        $this->assertSame(['add', 'opening', '2017-07-01'], [$mine[0]['action'], $mine[0]['type'], $mine[0]['date']]);
        $this->assertSame('87697342', $mine[0]['station']['uic']);
    }

    public function test_line_corrections_are_sourced(): void
    {
        $cases = [
            ['544000', 'neutralize', 'closure_passenger', '1939-05-15'],
            ['944000', 'add', 'reopening_passenger', '2005-03-26'],
            ['960000', 'add', 'opening', '2017-07-01'],
        ];
        foreach ($cases as [$line, $action, $type, $date]) {
            $c = self::lineCorrection($line, $action, $type, $date);
            $this->assertNotNull($c, "{$line} {$action} {$type} {$date}");
            $this->assertMatchesRegularExpression('~^https://fr\.wikipedia\.org/w/index\.php\?title=.+&oldid=\d+$~', $c['source_url'], $line);
            $this->assertNotSame('', trim((string) $c['quote']), $line);
            $this->assertNotSame('', trim((string) $c['reason']), $line);
        }
        // La réouverture de Cannes - Grasse vise la section fermée en 1995, sinon la fermeture plus précise l'emporte.
        $this->assertSame(['Cannes-la-Bocca', 'Ranguin'], [self::lineCorrection('944000', 'add', 'reopening_passenger', '2005-03-26')['from'],
            self::lineCorrection('944000', 'add', 'reopening_passenger', '2005-03-26')['to']]);
    }

    public function test_no_long_dash_in_the_new_texts(): void
    {
        $texts = array_merge(self::stationCorrections('Chilly-Mazarin'), self::stationCorrections('Gravigny-Balizy'),
            self::stationCorrections('Dugny-La Courneuve TT'));
        $this->assertDoesNotMatchRegularExpression('/—|--/u', json_encode($texts, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
