<?php

namespace Tests\Unit\Sncf;

use App\Services\Sncf\RadioSystems;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RadioSystemsTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> les cinq libellés du jeu, tels que SNCF Réseau les publie */
    public static function labels(): array
    {
        return [
            'GSM-R' => ['GSMR (Global System for Mobile Raywails)', 'gsm-r'],
            'GSM en groupe fermé' => ["Global System for Mobil- Groupe Fermé d'utilisateurs", 'gsm-gfu'],
            'analogique sans données' => ['Sans transmission de données', 'rst'],
            'analogique avec données' => ['Avec transmission de données', 'rst-data'],
            'analogique avec identification' => ['Sans transmission de données avec identification', 'rst-id'],
            'inconnu' => ['Radio inconnue', null],
            'vide' => [null, null],
        ];
    }

    #[DataProvider('labels')]
    public function test_system_of_a_label(?string $label, ?string $expected): void
    {
        $this->assertSame($expected, RadioSystems::system($label));
    }

    public function test_summary_adds_lengths_by_system_in_a_stable_order(): void
    {
        $summary = RadioSystems::summarize([
            ['code_ligne' => '100000', 'rst' => 'Sans transmission de données', 'pkd' => '010+000', 'pkf' => '015+000'],
            ['code_ligne' => '100000', 'rst' => 'GSMR (Global System for Mobile Raywails)', 'pkd' => '000+000', 'pkf' => '010+000'],
            ['code_ligne' => '100000', 'rst' => 'GSMR (Global System for Mobile Raywails)', 'pkd' => 'D+841', 'pkf' => '020+000'],
            ['code_ligne' => '200000', 'rst' => 'Radio inconnue', 'pkd' => '000+000', 'pkf' => '010+000'],
        ]);

        $this->assertEquals(['100000'], array_keys($summary), 'libellé inconnu écarté');
        $this->assertSame(['gsm-r' => 11.0, 'rst' => 5.0], $summary['100000'], 'un PK illisible pèse 1 km');
    }
}
