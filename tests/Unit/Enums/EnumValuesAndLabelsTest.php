<?php

namespace Tests\Unit\Enums;

use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Enums\LineEventType;
use App\Enums\LineType;
use App\Enums\MaterialType;
use App\Enums\MediaType;
use App\Enums\SegmentStatus;
use App\Enums\StationStatus;
use App\Enums\StationType;
use App\Enums\TrafficType;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Énumérations : valeurs exactes (stockées en base et exposées par l'API, donc
 * contractuelles) et libellés traduits en fr, en et de pour celles qu'affiche
 * le front (clé « groupe.sous_clé.valeur » des fichiers lang).
 *
 * Complète tests/Unit/EnumContractsTest.php (contraintes CHECK, libellés SNCF).
 */
class EnumValuesAndLabelsTest extends TestCase
{
    /** @return list<string> */
    private static function values(string $enum): array
    {
        return array_map(fn (\BackedEnum $c) => $c->value, $enum::cases());
    }

    /** @return array<string, array{0: class-string, 1: list<string>}> */
    public static function enumValues(): array
    {
        return [
            'ContributionAction' => [ContributionAction::class, ['create', 'update', 'delete']],
            'ContributionStatus' => [ContributionStatus::class, ['pending', 'approved', 'rejected', 'applied']],
            'LineEventType' => [LineEventType::class, [
                'opening', 'opening_passenger', 'opening_freight', 'closure_passenger', 'reopening_passenger',
                'reopening_freight', 'electrification', 'closure', 'closure_freight', 'dismantling', 'conversion',
                'heritage_start', 'heritage_end', 'reopening', 'modernization', 'accident', 'concession',
                'concession_end', 'declassification', 'other',
            ]],
            'LineType' => [LineType::class, ['passenger', 'freight', 'mixed']],
            'MaterialType' => [MaterialType::class, [
                'locomotive_electrique', 'locomotive_diesel', 'locomotive_vapeur', 'automotrice', 'autorail',
                'rame_tgv', 'voiture', 'wagon', 'tramway', 'engin_service',
            ]],
            'MediaType' => [MediaType::class, ['photo', 'plan', 'document', 'map', 'postcard']],
            'SegmentStatus' => [SegmentStatus::class, [
                'in_service', 'single_track', 'service_track', 'construction', 'abandoned', 'dismantled', 'razed',
            ]],
            'StationStatus' => [StationStatus::class, ['active', 'tourist', 'no_commercial', 'declassified']],
            'StationType' => [StationType::class, ['gare', 'halte', 'point_arret', 'passage_niveau', 'embranchement']],
            'TrafficType' => [TrafficType::class, ['passenger', 'freight', 'mixed', 'unknown']],
            'UserRole' => [UserRole::class, ['visitor', 'contributor', 'validator', 'admin']],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('enumValues')]
    public function test_enum_values_are_stable(string $enum, array $expected): void
    {
        $this->assertSame($expected, self::values($enum));
        foreach ($expected as $value) {
            $this->assertSame($value, $enum::from($value)->value);
            $this->assertMatchesRegularExpression('/^[a-z]+(_[a-z0-9]+)*$/', $value, 'snake_case ASCII');
        }
        $this->assertNull($enum::tryFrom('valeur-inconnue'));
    }

    // ─── Libellés traduits ───────────────────────────────────────────────

    /** @return array<string, array{0: class-string, 1: string}> */
    public static function translatedEnums(): array
    {
        return [
            'événements de ligne' => [LineEventType::class, 'lines.event_types'],
            'type de ligne' => [LineType::class, 'lines.types'],
            'trafic' => [TrafficType::class, 'lines.traffics'],
            'statut de portion' => [SegmentStatus::class, 'maps.status'],
            'type de gare' => [StationType::class, 'stations.types'],
            'statut de gare' => [StationStatus::class, 'stations.statuses'],
            'type de média' => [MediaType::class, 'media.types'],
            'rôle' => [UserRole::class, 'auth.roles'],
            'statut de contribution' => [ContributionStatus::class, 'editor.mine.status_labels'],
        ];
    }

    /** @return array<string, mixed> */
    private static function lang(string $locale, string $file): array
    {
        return require dirname(__DIR__, 3)."/lang/{$locale}/{$file}.php";
    }

    #[DataProvider('translatedEnums')]
    public function test_every_displayed_enum_value_has_a_label_in_fr_en_de(string $enum, string $key): void
    {
        [$file, $path] = explode('.', $key, 2);
        $labels = [];
        foreach (['fr', 'en', 'de'] as $locale) {
            $group = self::lang($locale, $file);
            foreach (explode('.', $path) as $segment) {
                $this->assertIsArray($group[$segment] ?? null, "{$locale} : groupe {$key} absent");
                $group = $group[$segment];
            }
            foreach (self::values($enum) as $value) {
                $label = $group[$value] ?? null;
                $this->assertIsString($label, "{$locale} : {$key}.{$value} manquant");
                $this->assertNotSame('', trim($label), "{$locale} : {$key}.{$value} vide");
                $this->assertStringNotContainsString('—', $label, "{$locale} : tiret long dans {$key}.{$value}");
                $this->assertStringNotContainsString('--', $label, "{$locale} : double tiret dans {$key}.{$value}");
                $labels[$locale][$value] = $label;
            }
        }

        // Une traduction n'est pas un copier-coller du français pour toutes les valeurs.
        $this->assertNotSame($labels['fr'], $labels['en'], "{$key} : anglais identique au français");
        $this->assertNotSame($labels['fr'], $labels['de'], "{$key} : allemand identique au français");
    }

    // ─── SegmentStatus : branches restantes de fromSncfLibelle ───────────

    /** @return array<string, array{0: string, 1: SegmentStatus}> */
    public static function moreSncfLabels(): array
    {
        return [
            'voie de service seule' => ['Voie de service', SegmentStatus::ServiceTrack],
            'déclassée seule' => ['Déclassée', SegmentStatus::Abandoned],
            'declassee sans accent' => ['Declassee', SegmentStatus::Abandoned],
            'neutralisee sans accent' => ['Neutralisee', SegmentStatus::Abandoned],
            'deferree sans accent' => ['Deferree', SegmentStatus::Dismantled],
            'exploitee sans accent' => ['Exploitee', SegmentStatus::InService],
            'en travaux' => ['En travaux', SegmentStatus::InService],
            'libellé inconnu' => ['Statut inédit', SegmentStatus::InService],
            'espaces seuls' => ['   ', SegmentStatus::InService],
        ];
    }

    #[DataProvider('moreSncfLabels')]
    public function test_more_sncf_labels(string $label, SegmentStatus $expected): void
    {
        $this->assertSame($expected, SegmentStatus::fromSncfLibelle($label));
    }

    public function test_is_active_for_each_status(): void
    {
        $active = [];
        foreach (SegmentStatus::cases() as $status) {
            $active[$status->value] = $status->isActive();
        }

        $this->assertSame([
            'in_service' => true, 'single_track' => true, 'service_track' => false, 'construction' => false,
            'abandoned' => false, 'dismantled' => false, 'razed' => false,
        ], $active);
    }
}
