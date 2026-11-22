<?php

namespace Tests\Unit\Sncf;

use App\Services\Sncf\TrackRegime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrackRegimeTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?int}> */
    public static function regimes(): array
    {
        return [
            'double voie' => ['Double voie', 2],
            'voie unique' => ['Voie unique', 1],
            'signalisation simplifiée' => ['Voie unique à signalisation simplifiée', 1],
            'trafic restreint' => ['Voie unique à trafic restreint (consigne de ligne)', 1],
            'navette' => ['Régime en navette', 1],
            'banalisée : nombre non établi' => ['Voie banalisée', null],
            'autre' => ['_Autre', null],
            'vide' => [null, null],
        ];
    }

    #[DataProvider('regimes')]
    public function test_tracks_of_a_regime(?string $regime, ?int $expected): void
    {
        $this->assertSame($expected, TrackRegime::tracks($regime));
    }

    public function test_pk_in_kilometres(): void
    {
        $this->assertEqualsWithDelta(17.25, TrackRegime::pkKm('017+250'), 0.0001);
        $this->assertEqualsWithDelta(0.75, TrackRegime::pkKm('001-250'), 0.0001);
        $this->assertEqualsWithDelta(-0.389, TrackRegime::pkKm('000-389'), 0.0001);
        $this->assertNull(TrackRegime::pkKm('D+841'));
        $this->assertNull(TrackRegime::pkKm(null));
    }

    public function test_summary_keeps_the_dominant_track_count_by_length(): void
    {
        $summary = TrackRegime::summarize([
            ['code_ligne' => '100000', 'lib_ligne' => 'A', 'exploitati' => 'Double voie', 'pkd' => '000+000', 'pkf' => '095+000'],
            ['code_ligne' => '100000', 'lib_ligne' => 'A', 'exploitati' => 'Voie unique', 'pkd' => '095+000', 'pkf' => '100+000'],
            ['code_ligne' => '200000', 'lib_ligne' => 'B', 'exploitati' => 'Voie unique', 'pkd' => '000+000', 'pkf' => '010+000'],
            ['code_ligne' => '200000', 'lib_ligne' => 'B', 'exploitati' => 'Double voie', 'pkd' => '010+000', 'pkf' => '020+000'],
            ['code_ligne' => '300000', 'lib_ligne' => 'C', 'exploitati' => 'Voie banalisée', 'pkd' => '000+000', 'pkf' => '020+000'],
        ]);

        $this->assertEquals(['100000', '200000'], array_keys($summary), 'la ligne seulement banalisée est écartée');
        $this->assertSame(2, $summary[100000]['tracks']);
        $this->assertTrue($summary[100000]['uniform']);
        $this->assertFalse($summary[200000]['uniform'], 'moitié double, moitié unique : régime partagé');
        $this->assertSame(['Double voie', 'Voie unique'], $summary[100000]['regimes']);
    }

    public function test_banalised_and_other_portions_count_in_the_length_of_the_line(): void
    {
        // Ligne 604000 du jeu : 6,1 km de voie unique, 72,3 km de voie banalisée au nombre de voies non établi.
        // La voie unique ne vaut que pour 8 % de la ligne : ni uniforme, ni « 1 voie » pour toute la ligne.
        $summary = TrackRegime::summarize([
            ['code_ligne' => '604000', 'lib_ligne' => 'F', 'exploitati' => 'Voie unique', 'pkd' => '000+000', 'pkf' => '006+100'],
            ['code_ligne' => '604000', 'lib_ligne' => 'F', 'exploitati' => 'Voie banalisée', 'pkd' => '006+100', 'pkf' => '078+400'],
            ['code_ligne' => '700000', 'lib_ligne' => 'G', 'exploitati' => 'Double voie', 'pkd' => '000+000', 'pkf' => '080+000'],
            ['code_ligne' => '700000', 'lib_ligne' => 'G', 'exploitati' => '_Autre', 'pkd' => '080+000', 'pkf' => '100+000'],
        ]);

        $this->assertSame(1, $summary['604000']['tracks']);
        $this->assertFalse($summary['604000']['uniform']);
        $this->assertEqualsWithDelta(0.078, $summary['604000']['share'], 0.001);
        $this->assertEqualsWithDelta(78.4, $summary['604000']['km'], 0.01);
        $this->assertFalse($summary['700000']['uniform'], '80 % en double voie, 20 % au régime « _Autre »');
    }

    public function test_verdicts(): void
    {
        $uniform = ['tracks' => 2, 'uniform' => true];
        $mixed = ['tracks' => 2, 'uniform' => false];

        $this->assertSame('new', TrackRegime::verdict($uniform, null));
        $this->assertSame('agree', TrackRegime::verdict($uniform, 2));
        $this->assertSame('contradiction', TrackRegime::verdict($uniform, 1));
        $this->assertSame('mixed', TrackRegime::verdict($mixed, 1));
    }
}
