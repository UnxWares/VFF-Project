<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingRegimeRules;
use PHPUnit\Framework\TestCase;

class SignallingRegimeRulesTest extends TestCase
{
    private const VUTR = 'Voie unique à trafic restreint (consigne de ligne)';

    public function test_every_rule_cites_its_source_and_a_known_code(): void
    {
        foreach (SignallingRegimeRules::rules() as $regime => $rule) {
            $this->assertStringContainsString('EPSF', $rule['source'], $regime);
            $this->assertContains($rule['system'], ['CT-VU', 'NONE'], $regime);
        }
    }

    public function test_cuts_the_other_range_along_regime_portions(): void
    {
        $pieces = SignallingRegimeRules::pieces(10_000, 50_000, [
            ['from' => 0, 'to' => 20_000, 'regime' => self::VUTR],
            ['from' => 20_000, 'to' => 60_000, 'regime' => 'Voie unique'],
        ]);
        $this->assertCount(1, $pieces);
        $this->assertSame([10_000, 20_000, 'NONE'], [$pieces[0]['from'], $pieces[0]['to'], $pieces[0]['system']]);
    }

    public function test_plain_single_track_gives_no_system(): void
    {
        $this->assertSame([], SignallingRegimeRules::pieces(0, 9_000, [['from' => 0, 'to' => 9_000, 'regime' => 'Voie unique']]));
        $this->assertSame([], SignallingRegimeRules::pieces(0, 9_000, [['from' => 0, 'to' => 9_000, 'regime' => '_Autre']]));
        $this->assertSame([], SignallingRegimeRules::pieces(0, 9_000, []));
    }

    public function test_simplified_signalling_is_telephone_block_and_reversed_pk_are_read(): void
    {
        $pieces = SignallingRegimeRules::pieces(0, 9_000, [['from' => 5_000, 'to' => 2_000, 'regime' => 'Voie unique à signalisation simplifiée']]);
        $this->assertSame([2_000, 5_000, 'CT-VU'], [$pieces[0]['from'], $pieces[0]['to'], $pieces[0]['system']]);
    }

    public function test_overlapping_regimes_do_not_overlap_pieces(): void
    {
        $pieces = SignallingRegimeRules::pieces(0, 10_000, [
            ['from' => 0, 'to' => 6_000, 'regime' => self::VUTR],
            ['from' => 4_000, 'to' => 10_000, 'regime' => 'Régime en navette'],
        ]);
        $this->assertSame([[0, 6_000], [6_000, 10_000]], array_map(fn ($p) => [$p['from'], $p['to']], $pieces));
    }
}
