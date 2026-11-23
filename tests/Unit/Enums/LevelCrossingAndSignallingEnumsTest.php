<?php

namespace Tests\Unit\Enums;

use App\Enums\LevelCrossingGroup;
use App\Enums\SignallingFamily;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Classes de passages à niveau SNCF Réseau → groupes, codes de cantonnement → familles. Le KVB
 * (contrôle de vitesse) n'est jamais une famille de cantonnement.
 */
class LevelCrossingAndSignallingEnumsTest extends TestCase
{
    /** @return iterable<string, array{0: int, 1: LevelCrossingGroup}> */
    public static function classes(): iterable
    {
        yield 'secondaire' => [0, LevelCrossingGroup::Secondary];
        yield 'gardé par un agent' => [10, LevelCrossingGroup::Guarded];
        yield 'gardé à distance' => [16, LevelCrossingGroup::Guarded];
        yield 'SAL 2' => [17, LevelCrossingGroup::Automatic];
        yield 'SAL 4' => [19, LevelCrossingGroup::Automatic];
        yield 'sans barrières' => [20, LevelCrossingGroup::NoBarrier];
        yield 'SAL 0' => [21, LevelCrossingGroup::NoBarrier];
        yield 'piétons' => [32, LevelCrossingGroup::Pedestrian];
        yield 'privé' => [41, LevelCrossingGroup::Private];
        yield 'privé piétons' => [46, LevelCrossingGroup::Private];
    }

    #[DataProvider('classes')]
    public function test_class_maps_to_group(int $class, LevelCrossingGroup $group): void
    {
        $this->assertSame($group, LevelCrossingGroup::fromClass($class));
    }

    public function test_every_official_class_has_a_group_and_unknown_classes_none(): void
    {
        foreach (LevelCrossingGroup::CLASSES as $class) {
            $this->assertNotNull(LevelCrossingGroup::fromClass($class), "classe {$class}");
        }
        $this->assertNull(LevelCrossingGroup::fromClass(25));
        $this->assertSame([17, 18, 19], LevelCrossingGroup::Automatic->classes());
        $this->assertSame(array_map(fn (LevelCrossingGroup $g) => $g->value, LevelCrossingGroup::cases()), LevelCrossingGroup::ORDER);
    }

    public function test_parses_the_sncf_class_label(): void
    {
        $this->assertSame(17, LevelCrossingGroup::parseClass('CLASSE 17'));
        $this->assertSame(0, LevelCrossingGroup::parseClass('CLASSE 00'));
        $this->assertNull(LevelCrossingGroup::parseClass('PN gardé'));
        $this->assertNull(LevelCrossingGroup::parseClass(null));
    }

    /** @return iterable<string, array{0: string, 1: SignallingFamily|null}> */
    public static function codes(): iterable
    {
        yield 'BAL' => ['BAL', SignallingFamily::AutomaticBlock];
        yield 'BAL-VB' => ['BAL-VB', SignallingFamily::AutomaticBlock];
        yield 'BAL-Tram' => ['BAL-Tram', SignallingFamily::AutomaticBlock];
        yield 'BAPR-VB' => ['BAPR-VB', SignallingFamily::RestrictedBlock];
        yield 'BM' => ['BM', SignallingFamily::ManualBlock];
        yield 'BMVU-SE' => ['BMVU-SE', SignallingFamily::ManualBlock];
        yield 'CT-VU' => ['CT-VU', SignallingFamily::Telephone];
        yield 'CAPI' => ['CAPI', SignallingFamily::Telephone];
        yield 'TVM-300' => ['TVM-300', SignallingFamily::Cab];
        yield 'ETCS_L2' => ['ETCS_L2', SignallingFamily::Cab];
        yield 'CC : commande centralisée, pas un cantonnement' => ['CC', SignallingFamily::CentralizedControl];
        yield 'Sans cantonnement : rien à dessiner' => ['NONE', null];
        yield 'KVB n’est pas un cantonnement' => ['KVB', null];
        yield 'ERTMS est un contrôle' => ['ERTMS_L2', null];
    }

    #[DataProvider('codes')]
    public function test_block_code_maps_to_family(string $code, ?SignallingFamily $family): void
    {
        $this->assertSame($family, SignallingFamily::fromCode($code));
    }
}
