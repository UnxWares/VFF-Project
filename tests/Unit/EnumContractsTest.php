<?php

namespace Tests\Unit;

use App\Console\Commands\BuildEraSegments;
use App\Enums\LineEventType;
use App\Enums\LineType;
use App\Enums\SegmentStatus;
use App\Enums\StationStatus;
use App\Enums\TrafficType;
use App\Services\ConsistencyChecker;
use App\Services\EraTimeline;
use App\Services\HeritageText;
use App\Services\NetworkStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Énumérations et vocabulaires partagés : chaque valeur produite par le code
 * doit être acceptée par les contraintes CHECK de la base (migrations), et
 * les listes recopiées d'une classe à l'autre doivent rester alignées.
 */
class EnumContractsTest extends TestCase
{
    /** @return list<string> */
    private static function values(string $enum): array
    {
        return array_map(fn ($c) => $c->value, $enum::cases());
    }

    /** Liste « IN (...) » de la première contrainte CHECK sur $column d'une migration. */
    private static function checkValues(string $migration, string $column): array
    {
        $sql = file_get_contents(dirname(__DIR__, 2).'/database/migrations/'.$migration);
        preg_match('/CHECK \('.$column.' IN \(([^)]*)\)/', $sql, $m);

        return array_map(fn ($v) => trim($v, " '"), explode(',', $m[1] ?? ''));
    }

    /** Constante privée VALUES d'une migration anonyme : « ('a', 'b') ». */
    private static function migrationConstant(string $migration): array
    {
        $object = require dirname(__DIR__, 2).'/database/migrations/'.$migration;
        $list = (new \ReflectionObject($object))->getConstant('VALUES');

        return array_map(fn ($v) => trim($v, " '"), explode(',', trim($list, '()')));
    }

    private static function privateConstant(string $class, string $name): mixed
    {
        return (new \ReflectionClass($class))->getConstant($name);
    }

    // ─── SegmentStatus : libellés SNCF ───────────────────────────────────

    /** @return array<string, array{0: ?string, 1: SegmentStatus}> */
    public static function sncfLabelProvider(): array
    {
        // Les 13 libellés réels (audit 2026-05) et quelques variantes de saisie.
        return [
            'Exploitée' => ['Exploitée', SegmentStatus::InService],
            'Neutralisée' => ['Neutralisée', SegmentStatus::Abandoned],
            'Fermée' => ['Fermée', SegmentStatus::Abandoned],
            'Déclassée vendue' => ['Déclassée vendue', SegmentStatus::Abandoned],
            'Transférée en voie de service' => ['Transférée en voie de service', SegmentStatus::ServiceTrack],
            'Déclassée non vendue' => ['Déclassée non vendue', SegmentStatus::Abandoned],
            'Fermée non déposée' => ['Fermée non déposée (Plus utilisable)', SegmentStatus::Abandoned],
            'Retranchée' => ['Retranchée (Plus utilisable)', SegmentStatus::Abandoned],
            'Fermée et déposée' => ['Fermée et déposée (Plus utilisable)', SegmentStatus::Dismantled],
            'Fermée avec maintien en place' => ['Fermée avec maintien en place', SegmentStatus::Abandoned],
            'Fermée et mise à disposition de tiers' => ['Fermée et mise à disposition de tiers', SegmentStatus::Abandoned],
            'Neutralisée Défense' => ['Neutralisée Défense', SegmentStatus::Abandoned],
            'Ligne en travaux' => ['S9A3 - Ligne en travaux', SegmentStatus::InService],
            'sans accents, non déposée' => ['Fermee non deposee', SegmentStatus::Abandoned],
            'sans accents, déposée' => ['Fermee et deposee', SegmentStatus::Dismantled],
            'déferrée' => ['Déferrée', SegmentStatus::Dismantled],
            'majuscules et espaces' => ['  EXPLOITÉE  ', SegmentStatus::InService],
            'vide' => ['', SegmentStatus::InService],
            'null' => [null, SegmentStatus::InService],
        ];
    }

    #[DataProvider('sncfLabelProvider')]
    public function test_sncf_label_to_segment_status(?string $label, SegmentStatus $expected): void
    {
        $this->assertSame($expected, SegmentStatus::fromSncfLibelle($label));
    }

    public function test_only_in_service_and_single_track_carry_traffic(): void
    {
        $active = array_values(array_filter(SegmentStatus::cases(), fn ($s) => $s->isActive()));

        $this->assertSame([SegmentStatus::InService, SegmentStatus::SingleTrack], $active);
        // Les définitions recopiées ailleurs sont les mêmes.
        $this->assertSame(['in_service', 'single_track'], ConsistencyChecker::ACTIVE_SEGMENTS);
        $this->assertSame(['in_service', 'single_track'], NetworkStats::ACTIVE_STATUSES);
    }

    // ─── Contraintes CHECK de la base ───────────────────────────────────

    public function test_every_segment_status_is_accepted_by_the_database(): void
    {
        $this->assertEqualsCanonicalizing(
            self::values(SegmentStatus::class),
            self::checkValues('2026_05_31_120000_extend_segment_status_check.php', 'status'),
        );
    }

    public function test_every_traffic_type_is_accepted_by_the_database(): void
    {
        $this->assertEqualsCanonicalizing(
            self::values(TrafficType::class),
            self::checkValues('2026_05_29_100000_add_checks_to_line_segments.php', 'traffic_type'),
        );
    }

    public function test_line_types_are_known_traffic_types(): void
    {
        $this->assertEmpty(array_diff(self::values(LineType::class), self::values(TrafficType::class)));
        $this->assertNotContains('unknown', self::values(LineType::class), 'lines.type inconnu = NULL');
    }

    public function test_heritage_kinds_match_the_database(): void
    {
        $this->assertEqualsCanonicalizing(HeritageText::KINDS, self::migrationConstant('2026_10_15_115000_add_heritage_kind_to_events_and_segments.php'));
    }

    public function test_reuse_values_produced_by_the_engine_are_accepted(): void
    {
        $allowed = self::migrationConstant('2026_10_15_113000_add_reuse_to_events_and_segments.php');

        // Reconversion sans précision : « other ».
        $state = (new EraTimeline([
            ['type' => 'opening', 'date' => '1900-01-01'],
            ['type' => 'conversion', 'date' => '1990-01-01'],
        ]))->stateAt(1990);
        $this->assertContains($state['reuse'], $allowed);
        // Reconversions observées aujourd'hui (present_states) reprises telles quelles.
        $this->assertContains('greenway', $allowed);
        $this->assertContains('road', $allowed);
    }

    public function test_present_states_match_the_database(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_15_114000_create_present_states_table.php');
        preg_match('/CHECK \\(state IN \\(([^)]*)\\)/', $sql, $m);
        $allowed = array_map(fn ($v) => trim($v, " '"), explode(',', $m[1]));

        $this->assertEqualsCanonicalizing(array_merge(EraTimeline::GONE_STATES, ['track_in_place']), $allowed);
    }

    // ─── Vocabulaires recopiés ───────────────────────────────────────────

    public function test_engine_event_types_are_line_event_types(): void
    {
        $engine = array_keys(self::privateConstant(EraTimeline::class, 'TYPE_ORDER'));

        $this->assertEmpty(array_diff($engine, self::values(LineEventType::class)), 'type inconnu de l\'énumération');
        // Événements sans effet sur l'état d'une portion : ignorés par le moteur.
        $this->assertEqualsCanonicalizing(
            ['electrification', 'modernization', 'accident', 'concession', 'concession_end', 'other'],
            array_values(array_diff(self::values(LineEventType::class), $engine)),
        );
    }

    public function test_default_services_by_network_are_the_same_in_engine_and_checks(): void
    {
        $engine = self::privateConstant(BuildEraSegments::class, 'DEFAULT_SERVICES');
        $checks = self::privateConstant(ConsistencyChecker::class, 'DEFAULT_SERVICES');
        $networks = self::checkValues('2026_10_15_110000_create_source_traces_table.php', 'network');

        $this->assertSame($engine, $checks);
        $this->assertEqualsCanonicalizing($networks, array_keys($engine));
    }

    public function test_station_statuses_used_by_the_checks_exist(): void
    {
        $statuses = self::values(StationStatus::class);

        $this->assertEmpty(array_diff(ConsistencyChecker::OPEN_STATIONS, $statuses));
        $this->assertContains(ConsistencyChecker::CLOSED_STATION, $statuses);
        $this->assertNotContains(ConsistencyChecker::CLOSED_STATION, ConsistencyChecker::OPEN_STATIONS);
    }

    public function test_end_events_of_the_engine(): void
    {
        $isEnd = new \ReflectionMethod(BuildEraSegments::class, 'isEnd');

        foreach (['closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling', 'conversion'] as $type) {
            $this->assertTrue($isEnd->invoke(null, $type), $type);
        }
        foreach (['opening', 'reopening', 'heritage_start', 'heritage_end', 'electrification', 'concession_end'] as $type) {
            $this->assertFalse($isEnd->invoke(null, $type), $type);
        }
    }
}
