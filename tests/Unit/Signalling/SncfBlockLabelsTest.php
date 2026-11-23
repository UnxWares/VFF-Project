<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingRanges;
use App\Services\Signalling\SncfBlockLabels;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Libellés du jeu SNCF Réseau `mode-de-cantonnement-des-lignes` : un système, deux systèmes
 * installés ensemble (« ETCS 2/TVM 300 »), libellés sans cantonnement et libellés inconnus.
 */
class SncfBlockLabelsTest extends TestCase
{
    /** @return iterable<string, array{string, ?list<string>}> */
    public static function labels(): iterable
    {
        yield 'BAL' => ['Block automatique lumineux', ['BAL']];
        yield 'BAPR double voie' => ['Block automatique à permissivité restreinte de double voie', ['BAPR']];
        yield 'TVM 430' => ['Transmission voie-machine 430', ['TVM-430']];
        yield 'ETCS 1' => ['European train control system 1', ['ETCS_L1']];
        yield 'ETCS 2 et TVM 300' => ['European train control system 2/Transmission voie-machine 300', ['ETCS_L2', 'TVM-300']];
        yield 'ETCS 2 et TVM 430' => ['European train control system 2/Transmission voie-machine 430', ['ETCS_L2', 'TVM-430']];
        yield 'espaces autour de la barre' => ['European train control system 2 / Transmission voie-machine 300', ['ETCS_L2', 'TVM-300']];
        yield 'deux block manuels de même code' => ['Block manuel de double voie de type SNCF/Block manuel à circuit de voie', ['BM']];
        yield 'sans cantonnement' => ['Sans cantonnement', ['NONE']];
        yield 'autre' => ['_Autre', []];
        yield 'inconnu' => ['Block électronique', null];
        yield 'une partie inconnue' => ['European train control system 2/Système inconnu', null];
        yield 'vide' => ['', null];
    }

    /** @param  ?list<string>  $expected */
    #[DataProvider('labels')]
    public function test_label_maps_to_every_system_it_names(string $label, ?array $expected): void
    {
        $this->assertSame($expected, SncfBlockLabels::systems($label));
    }

    public function test_protection_follows_the_cab_systems_and_never_adds_kvb(): void
    {
        $this->assertSame(['ERTMS_L2', 'TVM-300'], SncfBlockLabels::protection(['ETCS_L2', 'TVM-300']));
        $this->assertSame(['ERTMS_L1'], SncfBlockLabels::protection(['ETCS_L1']));
        $this->assertSame([], SncfBlockLabels::protection(['BAL']));
        $this->assertTrue(SncfBlockLabels::isCab('ETCS_L2'));
        $this->assertTrue(SncfBlockLabels::isCab('TVM-430'));
        $this->assertFalse(SncfBlockLabels::isCab('BAL'));
        // « Sans cantonnement » : ni cabine ni contrôle de vitesse.
        $this->assertFalse(SncfBlockLabels::isCab(SncfBlockLabels::NONE));
        $this->assertSame([], SncfBlockLabels::protection([SncfBlockLabels::NONE]));
    }

    public function test_ranges_without_the_list_fall_back_on_their_main_code(): void
    {
        $this->assertSame(['BAL'], SignallingRanges::systems('BAL', '[]'));
        $this->assertSame(['BAL'], SignallingRanges::systems('BAL', null));
        $this->assertSame(['ETCS_L2', 'TVM-300'], SignallingRanges::systems('ETCS_L2', '["ETCS_L2", "TVM-300"]'));
        // Code principal absent de la liste : remis en tête.
        $this->assertSame(['ETCS_L2', 'TVM-300'], SignallingRanges::systems('ETCS_L2', '["TVM-300"]'));
    }
}
