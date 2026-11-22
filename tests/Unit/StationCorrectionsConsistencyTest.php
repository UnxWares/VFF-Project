<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Cohérence des événements ajoutés par database/data/gares_corrections.php : pour une même gare (même clé d'identification),
 * une seule date d'ouverture, une seule fin des voyageurs, un seul fret et aucune fermeture avant l'ouverture. Données
 * seules, sans base : un agent qui ajoute une correction à une gare déjà corrigée ailleurs dans le fichier est prévenu.
 */
class StationCorrectionsConsistencyTest extends TestCase
{
    private const CLOSURES = ['closure_passenger', 'closure_freight', 'closure'];

    /**
     * Événements « add » du fichier, groupés par gare (clé stable : uic, wikidata, osm ou position).
     *
     * @return array<string, list<array{type: string, date: string}>>
     */
    private static function eventsByStation(): array
    {
        /** @var list<array<string, mixed>> $corrections */
        $corrections = require dirname(__DIR__, 2).'/database/data/gares_corrections.php';
        $groups = [];
        foreach ($corrections as $c) {
            if (($c['action'] ?? null) !== 'add') {
                continue;
            }
            $st = $c['station'];
            $key = $st['name'].'#'.($st['uic'] ?? $st['wikidata'] ?? $st['osm'] ?? json_encode($st['near'] ?? []));
            $groups[$key][] = ['type' => (string) $c['type'], 'date' => (string) $c['date']];
        }

        return $groups;
    }

    /**
     * Gares avec plusieurs dates pour le même type d'événement.
     *
     * @param  array<string, list<array{type: string, date: string}>>  $groups
     * @return list<string>
     */
    private static function duplicates(array $groups): array
    {
        $found = [];
        foreach ($groups as $station => $events) {
            foreach (['opening', ...self::CLOSURES] as $type) {
                $dates = array_unique(array_column(array_filter($events, fn (array $e): bool => $e['type'] === $type), 'date'));
                if (count($dates) > 1) {
                    $found[] = "{$station} : {$type} ".implode(' / ', $dates);
                }
            }
        }

        return $found;
    }

    /**
     * Gares avec une fermeture dont l'année précède celle de l'ouverture.
     *
     * @param  array<string, list<array{type: string, date: string}>>  $groups
     * @return list<string>
     */
    private static function inverted(array $groups): array
    {
        $found = [];
        foreach ($groups as $station => $events) {
            $opening = array_values(array_filter($events, fn (array $e): bool => $e['type'] === 'opening'));
            if ($opening === []) {
                continue;
            }
            foreach ($events as $e) {
                if (in_array($e['type'], self::CLOSURES, true) && (int) substr($e['date'], 0, 4) < (int) substr($opening[0]['date'], 0, 4)) {
                    $found[] = "{$station} : {$e['type']} {$e['date']} avant l'ouverture {$opening[0]['date']}";
                }
            }
        }

        return $found;
    }

    public function test_a_station_gets_one_date_per_kind_of_event(): void
    {
        $this->assertSame([], self::duplicates(self::eventsByStation()), 'Plusieurs dates pour le même événement d\'une gare.');
    }

    public function test_no_closure_precedes_the_opening_of_the_same_station(): void
    {
        $this->assertSame([], self::inverted(self::eventsByStation()), 'Fermeture antérieure à l\'ouverture.');
    }

    public function test_the_checks_see_a_contradiction(): void
    {
        $groups = ['X#1' => [
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'opening', 'date' => '1902-05-01'],
            ['type' => 'closure_passenger', 'date' => '1899-01-01'],
            ['type' => 'closure_freight', 'date' => '1950-01-01'],
        ], 'Y#2' => [
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'closure', 'date' => '1900-06-01'],
        ]];

        $this->assertSame(['X#1 : opening 1900-01-01 / 1902-05-01'], self::duplicates($groups));
        $this->assertSame(["X#1 : closure_passenger 1899-01-01 avant l'ouverture 1900-01-01"], self::inverted($groups));
    }
}
