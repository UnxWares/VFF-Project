<?php

namespace Tests\Unit\TraceExport;

use App\Services\TraceExport\TraceCaption;
use App\Services\TraceExport\TracePart;
use App\Support\SourceRights;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Petites fonctions pures des exports GPX et KML, et règle de redistribution du registre des droits. */
class TraceExportSupportTest extends TestCase
{
    #[DataProvider('pks')]
    public function test_pk_label(int $meters, string $label): void
    {
        $this->assertSame($label, TraceCaption::pkLabel($meters));
    }

    /** @return array<string, array{int, string}> */
    public static function pks(): array
    {
        return ['zéro' => [0, '0+000'], 'ordinaire' => [12345, '12+345'], 'négatif' => [-250, '-0+250'], 'rond' => [5000, '5+000']];
    }

    public function test_coordinates_are_written_with_a_dot_and_without_useless_zeros(): void
    {
        $this->assertSame('48', TracePart::format(48.0));
        $this->assertSame('2.3253', TracePart::format(2.3253));
        $this->assertSame('-4.123457', TracePart::format(-4.1234567));
        $this->assertSame('0', TracePart::format(0.0));
    }

    public function test_only_a_proved_open_licence_allows_redistribution(): void
    {
        $rights = new SourceRights(['providers' => [
            'ouvert' => ['statut' => SourceRights::SAFE],
            'oral' => ['statut' => SourceRights::UNCERTAIN],
            'ferme' => ['statut' => SourceRights::FORBIDDEN],
        ]]);

        $this->assertTrue($rights->allowsRedistribution('ouvert'));
        $this->assertFalse($rights->allowsRedistribution('oral'));
        $this->assertFalse($rights->allowsRedistribution('ferme'));
        $this->assertFalse($rights->allowsRedistribution('absent'));
        $this->assertFalse($rights->allowsRedistribution(null));
    }
}
