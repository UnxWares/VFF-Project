<?php

namespace Tests\Unit\Rinf;

use App\Services\Rinf\RinfParameters;
use App\Services\Rinf\RinfParser;
use PHPUnit\Framework\TestCase;

/**
 * Paramètres des voies déclarés au RINF : lecture de six réponses réelles du point SPARQL de l'ERA (lues le 25/11/2026,
 * tests/Fixtures/rinf/track-parameters-sample.json, RINF © ERA) et formes normalisées qui servent à la comparaison.
 */
class RinfParametersTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    private function sample(): array
    {
        $json = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/rinf/track-parameters-sample.json'), true);

        return RinfParser::trackParameters($json['results']['bindings']);
    }

    public function test_real_tracks_of_a_high_speed_line_a_classic_line_and_a_regional_line(): void
    {
        $p = $this->sample();
        $this->assertCount(6, $p);

        // Voie 1 de la LGV SEA (graphe 3436) : 25 kV 50 Hz, TVM 300, KVB, ETCS niveau 2, GSM-R d'un code que le
        // vocabulaire de l'ERA ne définit pas (servi tel quel), catégorie C4.
        $sea = $p['http://data.europa.eu/949/track/14bdc4ec2e'];
        $this->assertSame(['overhead', 'AC10', 25000, 50.0], [$sea['contact_line'], $sea['energy_supply'], $sea['voltage_v'], $sea['frequency_hz']]);
        $this->assertSame(['ETCS L2', 'KVB', 'TVM 300'], RinfParameters::comparedFromRinf($sea['train_protection'], $sea['etcs_level']));
        $this->assertSame(['TVM 300', 'KVB', 'ATC vR'], $sea['train_protection']);
        $this->assertSame(['2', true, 'gsmr-versions/40'], [$sea['etcs_level'], $sea['gsm_r'], $sea['gsm_r_version']]);
        $this->assertSame(['FR-3.3', 'GI2'], $sea['gauging_profiles']);
        $this->assertSame(['C4', '5', 1435], [$sea['load_category'], $sea['national_load_class'], $sea['track_gauge_mm']]);

        // Voie 1 de la LGV Est (0087) : TVM 430 déclarée deux fois (protection héritée et autre protection), une seule fois servie.
        $est = $p['http://data.europa.eu/949/track/1001cdbce2'];
        $this->assertSame(['TVM 430', 'KVB'], $est['train_protection']);
        $this->assertSame(['Baseline 1', 'D4'], [$est['gsm_r_version'], $est['load_category']]);
        $this->assertSame(['GC', 'FR-3.3', 'GI2'], $est['gauging_profiles']);

        // Voie 1 bis de Paris - Bordeaux : 1 500 V continu, KVB et Crocodile, sans ETCS.
        $dc = $p['http://data.europa.eu/949/track/0070f651a6'];
        $this->assertSame([1500, 0.0, 'none'], [$dc['voltage_v'], $dc['frequency_hz'], $dc['etcs_level']]);
        $this->assertSame(['KVB', 'Crocodile'], $dc['train_protection']);
        $this->assertSame(['GB1', 'FR-3.3', 'GI2'], $dc['gauging_profiles']);

        // Montréjeau - Luchon (CY76) : non électrifiée, « aucune » protection (concept 00), ETCS « N », classe « 20t/essieu ».
        $luchon = $p['http://data.europa.eu/949/track/67c4f4c622'];
        $this->assertSame(['none', null, null, [], 'none'], [$luchon['contact_line'], $luchon['energy_supply'], $luchon['voltage_v'], $luchon['train_protection'], $luchon['etcs_level']]);
        $this->assertSame(['C4', '20t/essieu'], [$luchon['load_category'], $luchon['national_load_class']]);

        // Saint-Julien-les-Villas - Brienne-le-Château : protection et GSM-R déclarés sans objet (aucun), pas « non dit ».
        $na = $p['http://data.europa.eu/949/track/0033360491'];
        $this->assertSame([[], false, null], [$na['train_protection'], $na['gsm_r'], $na['gsm_r_version']]);
        $this->assertSame('none', $na['etcs_level']);

        // Un numéro que le concept other-protection-control-warning ne définit pas reste tel quel.
        $this->assertSame(['ETCS L1', 'KVB'], RinfParameters::comparedFromRinf($p['http://data.europa.eu/949/track/03eb17815b']['train_protection'], '1'));
        $this->assertContains('other-protection-control-warning/31', $p['http://data.europa.eu/949/track/03eb17815b']['train_protection']);
        $this->assertNotContains('EBICAB 700', $p['http://data.europa.eu/949/track/03eb17815b']['train_protection']);
    }

    public function test_a_track_without_any_parameter_says_nothing(): void
    {
        $p = RinfParser::trackParameters([['t' => ['type' => 'uri', 'value' => 'http://data.europa.eu/949/track/x'], 'protection' => ['type' => 'literal', 'value' => '']]]);

        $this->assertSame(array_fill_keys(RinfParameters::COLUMNS, null), $p['http://data.europa.eu/949/track/x']);
    }

    public function test_main_tracks_and_compared_systems(): void
    {
        foreach (['Voie V1 de Paris-12 à Marseille-01', 'Voie 2 de Paris-Gare-de-Lyon à Marseille-St-Charles', 'Voie 1 LGV SEA', 'Voie UNIQUE de Beillant à St-Michel-sur-Charente', 'UNIQUE', '1', 'VU'] as $ref) {
            $this->assertTrue(RinfParameters::isMainTrack($ref), $ref);
        }
        foreach (['Voie 1Bis de St-Pierre-des-Corps', 'Voie V1H de Varennes-sur-Seine à La Brosse-Montceaux', 'Voie V1B1 de Noisy-le-Sec', 'Voie 12', 'Voie 7V de Plibou', 'Voie E de Sireuil', 'Tiroir F de Nanterre-La folie'] as $ref) {
            $this->assertFalse(RinfParameters::isMainTrack($ref), $ref);
        }

        // KVBP compte comme KVB ; Crocodile, DAAT, KCVP ne sont pas confrontés (SNCF Réseau ne les publie pas).
        $this->assertSame(['KVB'], RinfParameters::comparedFromRinf(['KVBP', 'Crocodile', 'DAAT', 'KCVP'], 'none'));
        $this->assertSame([], RinfParameters::comparedFromRinf([], null));
        $this->assertSame(['ETCS L2', 'TVM 300'], RinfParameters::comparedFromVff(['ERTMS_L2', 'TVM-300'], ['ETCS_L2', 'TVM-300']));
        $this->assertSame(['KVB'], RinfParameters::comparedFromVff(['KVB'], ['BAPR-VB']));

        $this->assertSame('25000V-50Hz', RinfParameters::electrificationKey(true, 25000, 50.0));
        $this->assertSame('15000V-16.7Hz', RinfParameters::electrificationKey(true, 15000, 16.7));
        $this->assertSame('1500V-DC', RinfParameters::electrificationKey(true, 1500, 0.0));
        $this->assertSame('none', RinfParameters::electrificationKey(false, 1500, 0.0));
        $this->assertSame('electrified', RinfParameters::electrificationKey(true, null, null));

        $this->assertSame('KVB', RinfParameters::protectionLabel('http://data.europa.eu/949/concepts/train-protection-legacy-systems/12'));
        $this->assertNull(RinfParameters::protectionLabel('http://data.europa.eu/949/concepts/other-protection-control-warning/100'));
        $this->assertSame(['ETCS L2', 'TVM 430', 'KVB', 'Crocodile', 'ATC vR'], RinfParameters::sortProtection(['Crocodile', 'ATC vR', 'KVB', 'TVM 430', 'ETCS L2']));
    }
}
