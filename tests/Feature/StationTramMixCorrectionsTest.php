<?php

namespace Tests\Feature;

use App\Services\StationCorrections;
use Tests\TestCase;

/**
 * Fiches mêlées à une station de tramway (23/11/2026) : Meyzieu (élément Wikidata de la station du T3 détaché puis fiche exclue),
 * Les Côteaux (ouverture du T2 neutralisée), Porte de Vincennes (fiche née d'une station de tramway, exclue).
 */
class StationTramMixCorrectionsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function all(): array
    {
        return require database_path('data/gares_corrections.php');
    }

    public function test_meyzieu_tram_element_is_unlinked_and_its_own_record_excluded(): void
    {
        $unlinks = (new StationCorrections($this->all()))->unlinks();
        $hit = array_values(array_filter($unlinks, fn ($u) => $u['ref'] === 'Q28495871'));
        $this->assertCount(1, $hit);
        $this->assertSame('87729350', $hit[0]['uic']);
        $kind = array_filter($this->all(), fn ($c) => ($c['action'] ?? null) === 'kind' && ($c['station']['wikidata'] ?? null) === 'Q28495871');
        $this->assertCount(1, $kind);
    }

    public function test_cotaux_tram_opening_is_neutralized_and_porte_de_vincennes_excluded(): void
    {
        $n = array_values(array_filter($this->all(), fn ($c) => ($c['action'] ?? null) === 'neutralize' && ($c['station']['uic'] ?? null) === '87382515'));
        $this->assertCount(1, $n);
        $this->assertSame('1997-07-02', $n[0]['match']['date']);
        $v = array_filter($this->all(), fn ($c) => ($c['action'] ?? null) === 'kind' && ($c['station']['wikidata'] ?? null) === 'Q76233926');
        $this->assertCount(1, $v);
    }

    public function test_every_new_correction_is_valid(): void
    {
        foreach ($this->all() as $c) {
            if (str_contains(json_encode($c), 'Q28495871') || str_contains(json_encode($c), 'Q76233926') || ($c['station']['uic'] ?? null) === '87382515' && ($c['action'] ?? '') === 'neutralize') {
                $this->assertSame([], StationCorrections::validate($c));
            }
        }
    }
}
