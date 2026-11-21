<?php

namespace Tests\Unit;

use App\Services\Traction\ElectrificationText;
use App\Services\Traction\ElectrificationTimeline;
use App\Services\Traction\Gauge;
use App\Services\Traction\TractionCuration;
use PHPUnit\Framework\TestCase;

/**
 * Électrification et écartement par portion et par ère (vff:enrich-era-segments).
 */
class TractionTest extends TestCase
{
    // ─── Lecture des textes ─────────────────────────────────────────────

    public function test_voltage_is_read_in_its_french_spellings(): void
    {
        $this->assertSame(1500, ElectrificationText::voltage('électrifiée en 1 500 V continu'));
        $this->assertSame(1500, ElectrificationText::voltage('La ligne a été électrifiée en 1.5 kV - CC en 1930'));
        $this->assertSame(1500, ElectrificationText::voltage('en courant continu 1,5 kV'));
        $this->assertSame(25000, ElectrificationText::voltage('électrifiée en 25 kV - 50 Hz le 16 septembre 1955'));
        $this->assertSame(25000, ElectrificationText::voltage('électrifié en 25kv - 50Hz'));
        $this->assertSame(12000, ElectrificationText::voltage('électrification expérimentale en 12 kV-25 Hz de la section'));
        $this->assertSame(850, ElectrificationText::voltage('Électrification par troisième rail latéral en courant continu 850 V'));
        $this->assertSame(25000, ElectrificationText::voltage('Wikidata Q1 · P930 = électrification ferroviaire 25 kV 50 Hz'));
        $this->assertNull(ElectrificationText::voltage('Infobox : électrification = 1964'));
        // Tension d'alimentation d'une sous-station ≠ tension de la ligne.
        $this->assertSame(850, ElectrificationText::voltage('à la tension de 20 000 volts, en courant triphasé, transformé et redressé ensuite en courant continu 850 V continu'));
    }

    public function test_event_kind_separates_commissioning_works_and_deelectrification(): void
    {
        $this->assertSame('electrification', ElectrificationText::kind('26 mai 1952 : Chalon – Lyon-Perrache ;'));
        $this->assertSame('deelectrification', ElectrificationText::kind('La section Oloron-Sainte-Marie - Canfranc a été dés-électrifiée en 1992,'));
        $this->assertSame('deelectrification', ElectrificationText::kind('la ligne est mise hors tension en 1971'));
        $this->assertSame('ignore', ElectrificationText::kind('Dès le 3 janvier 2019, les travaux d\'électrification de la ligne ont commencé'));
        $this->assertSame('ignore', ElectrificationText::kind('Les travaux pour l\'électrification en 1500 volts continu débutent en 1943'));
        $this->assertSame('ignore', ElectrificationText::kind('début des travaux préparatoires à l\'électrification de la ligne'));
        $this->assertSame('ignore', ElectrificationText::kind('L\'électrification de la section est inscrite au contrat de plan État-Région'));
        $this->assertSame('electrification', ElectrificationText::kind('Le 22 mai 1962, dans le cadre du programme d\'électrification du réseau Est, la section Bondy - Gargan est mise sous tension en 25 kV - 50Hz'));
        $this->assertSame('ignore', ElectrificationText::kind('bénéficier du programme d\'électrification par caténaire 1500 V continu défini dans la décision ministérielle du 29 août 1920'));
    }

    public function test_completion_dates_and_wikidata_end_qualifier(): void
    {
        $this->assertSame(1938, ElectrificationText::completionYear('La ligne a été entièrement électrifiée par caténaire en courant continu 1500 volts entre 1926 et 1938.', 1926));
        $this->assertSame(1964, ElectrificationText::completionYear('La ligne a été intégralement électrifiée en décembre 1964', 1964));
        $this->assertNull(ElectrificationText::completionYear('Laroche - Migennes – Dijon. Mise sous tension.', 1949));
        $this->assertSame(1976, ElectrificationText::wikidataEndYear('qualificatifs : P2283 = troisième rail ; P580 = 1925-00-00 ; P582 = 1976-00-00'));
        $this->assertTrue(ElectrificationText::partialInfobox('Infobox : électrification = 1954 ; électrification2 = 1964 ; électrification3 = partiel'));
    }

    public function test_osm_and_sncf_labels(): void
    {
        $this->assertSame(25000, ElectrificationText::osmVoltage('25000;1500'));
        $this->assertNull(ElectrificationText::osmVoltage(''));
        $this->assertTrue(ElectrificationText::osmElectrified('contact_line'));
        $this->assertTrue(ElectrificationText::osmElectrified('rail'));
        $this->assertFalse(ElectrificationText::osmElectrified('no'));
        $this->assertSame([25000, 50.0, false], ElectrificationText::fromSncfLabel('25000 volts alternatif'));
        $this->assertSame([15000, 16.7, false], ElectrificationText::fromSncfLabel('15000 volts 16 2/3 alternatif'));
        $this->assertSame([850, 0.0, true], ElectrificationText::fromSncfLabel('850 volts continu par 3ème rail'));
    }

    // ─── Chronologie d'électrification ──────────────────────────────────

    public function test_paris_lyon_is_electrified_in_1960_not_in_1940(): void
    {
        $t = new ElectrificationTimeline(
            [['date' => '1952-05-26', 'kind' => 'electrification', 'voltage_v' => null, 'scope' => 'located']],
            ['state' => true, 'voltage_v' => 1500, 'source' => 'sncf'],
        );
        $this->assertFalse($t->stateAt(1940)['electrified']);
        $this->assertFalse($t->stateAt(1950)['electrified']);
        $this->assertSame(['electrified' => true, 'voltage_v' => 1500, 'source' => 'event', 'year' => 1952], $t->stateAt(1960));
        $this->assertSame('sncf', $t->stateAt(2030, true)['source']);
    }

    public function test_located_events_win_over_whole_line_ones(): void
    {
        // Paris - Marseille : « Laroche - Dijon 1949 » mal résolu sur toute la ligne.
        $t = new ElectrificationTimeline([
            ['date' => '1949-12-22', 'kind' => 'electrification', 'voltage_v' => null, 'scope' => 'line'],
            ['date' => '1962-06-22', 'kind' => 'electrification', 'voltage_v' => null, 'scope' => 'located'],
        ], ['state' => true, 'voltage_v' => 1500, 'source' => 'sncf']);
        $this->assertFalse($t->stateAt(1950)['electrified']);
        $this->assertTrue($t->stateAt(1970)['electrified']);
    }

    public function test_curated_timeline_with_deelectrification_and_new_voltage(): void
    {
        // Perpignan - Villefranche : 12 kV 16 2/3 Hz (1912), hors tension (1971), 1 500 V (1984).
        $events = array_map(fn ($e) => $e + ['scope' => 'curated'], TractionCuration::electrification('679000')['events']);
        $t = new ElectrificationTimeline($events, ['state' => true, 'voltage_v' => 1500, 'source' => 'sncf']);
        $this->assertFalse($t->stateAt(1910)['electrified']);
        $this->assertSame(12000, $t->stateAt(1930)['voltage_v']);
        $this->assertSame(12000, $t->stateAt(1960)['voltage_v']);
        $this->assertFalse($t->stateAt(1980)['electrified']);
        $this->assertSame(1500, $t->stateAt(1990)['voltage_v']);
    }

    public function test_cerdagne_is_electrified_from_the_1910s(): void
    {
        $events = array_map(fn ($e) => $e + ['scope' => 'curated'], TractionCuration::electrification('669000')['events']);
        $t = new ElectrificationTimeline($events, ['state' => true, 'voltage_v' => 850, 'source' => 'sncf']);
        $this->assertSame(['electrified' => true, 'voltage_v' => 850, 'source' => 'curated', 'year' => 1910], $t->stateAt(1910));
    }

    public function test_moulineaux_third_rail_from_1928_to_1993(): void
    {
        // Puteaux - Issy-Plaine : 650 V (1928), 750 V après 1933 (année du régime : 1934), plus rien après 1993.
        $events = array_map(fn ($e) => $e + ['scope' => 'curated'], TractionCuration::electrification('972000')['events']);
        $t = new ElectrificationTimeline($events, ['state' => false, 'voltage_v' => null, 'source' => 'sncf']);
        $this->assertFalse($t->stateAt(1920)['electrified']);
        $this->assertSame(650, $t->stateAt(1930)['voltage_v']);
        $this->assertSame(['electrified' => true, 'voltage_v' => 750, 'source' => 'curated', 'year' => 1934], $t->stateAt(1980));
        $this->assertFalse($t->stateAt(2000)['electrified']);
    }

    public function test_midi_12kv_lines_are_converted_to_1500v_after_1923(): void
    {
        $t = new ElectrificationTimeline(
            [['date' => '1917-05-17', 'kind' => 'electrification', 'voltage_v' => 12000, 'scope' => 'located']],
            ['state' => true, 'voltage_v' => 1500, 'source' => 'sncf'],
        );
        $this->assertSame(12000, $t->stateAt(1920)['voltage_v']);
        $this->assertSame(1500, $t->stateAt(1930)['voltage_v']);
    }

    public function test_paris_suburb_third_rail_voltage_comes_from_a_sibling_event(): void
    {
        // Saint-Lazare - Bécon (1924, tension non citée) : la ligne cite 750 V par troisième rail
        // en 1928 ; elle est aujourd'hui en 25 kV (1976).
        $t = new ElectrificationTimeline([
            ['date' => '1924-04-27', 'kind' => 'electrification', 'voltage_v' => null, 'voltage_hint' => 750, 'scope' => 'located'],
            ['date' => '1976-09-09', 'kind' => 'electrification', 'voltage_v' => 25000, 'scope' => 'located'],
        ], ['state' => true, 'voltage_v' => 25000, 'source' => 'sncf']);
        $this->assertSame(750, $t->stateAt(1930)['voltage_v']);
        $this->assertSame(['electrified' => true, 'voltage_v' => 25000, 'source' => 'event', 'year' => 1976], $t->stateAt(1980));
    }

    public function test_same_voltage_modernisation_keeps_the_original_date(): void
    {
        // Culoz - Modane : troisième rail 1 500 V (1930), caténaire 1 500 V (1976, toute la ligne).
        $t = new ElectrificationTimeline([
            ['date' => '1930-01-01', 'kind' => 'electrification', 'voltage_v' => 1500, 'scope' => 'located'],
            ['date' => '1976-01-01', 'kind' => 'electrification', 'voltage_v' => 1500, 'scope' => 'line'],
        ], ['state' => true, 'voltage_v' => 1500, 'source' => 'sncf']);
        $this->assertSame(['electrified' => true, 'voltage_v' => 1500, 'source' => 'event', 'year' => 1930], $t->stateAt(1980));
    }

    public function test_conflicting_modern_voltage_from_a_vague_event_is_ignored(): void
    {
        // « ré-électrification de Bellegarde - Genève en 25 kV » (2014) ne concerne pas Lyon - Culoz (1 500 V).
        $t = new ElectrificationTimeline(
            [['date' => '2014-01-01', 'kind' => 'electrification', 'voltage_v' => 25000, 'scope' => 'line']],
            ['state' => true, 'voltage_v' => 1500, 'source' => 'sncf'],
        );
        $this->assertFalse($t->isDated());
        $this->assertSame('unknown', $t->stateAt(1990)['source']);
    }

    public function test_vague_dates_do_not_electrify_a_section_without_catenary_today(): void
    {
        // « Infobox : électrification = 1982 ; partiel » : la portion que SNCF dit non électrifiée
        // n'est pas celle-là…
        $vague = new ElectrificationTimeline(
            [['date' => '1982-01-01', 'kind' => 'electrification', 'voltage_v' => null, 'scope' => 'unlocated']],
            ['state' => false, 'voltage_v' => null, 'source' => 'sncf'],
        );
        $this->assertFalse($vague->stateAt(2010)['electrified']);
        // …sauf désélectrification postérieure (Pau - Canfranc, toute la ligne en 1928, déposée ensuite).
        $gone = new ElectrificationTimeline([
            ['date' => '1928-07-18', 'kind' => 'electrification', 'voltage_v' => 1500, 'scope' => 'line'],
            ['date' => '1992-01-01', 'kind' => 'deelectrification', 'voltage_v' => null, 'scope' => 'located'],
        ], ['state' => false, 'voltage_v' => null, 'source' => 'sncf']);
        $this->assertSame(1500, $gone->stateAt(1960)['voltage_v']);
        $this->assertFalse($gone->stateAt(2000)['electrified']);
    }

    public function test_undated_electrification_is_unknown_but_bounded_by_rules(): void
    {
        $ac = new ElectrificationTimeline([], ['state' => true, 'voltage_v' => 25000, 'source' => 'sncf']);
        $this->assertSame('rule', $ac->stateAt(1940)['source']);       // 50 Hz avant 1951 : impossible
        $this->assertSame('unknown', $ac->stateAt(1970)['source']);
        $this->assertFalse($ac->stateAt(1970)['electrified']);
        $this->assertTrue($ac->stateAt(2020)['electrified']);          // décennie récente : état actuel
        $dc = new ElectrificationTimeline([], ['state' => true, 'voltage_v' => 1500, 'source' => 'osm']);
        $this->assertSame('rule', $dc->stateAt(1890)['source']);
        $this->assertSame('rule', $dc->stateAt(1910)['source']);        // 1 500 V : décision de 1920
        $this->assertSame('unknown', $dc->stateAt(1930)['source']);
        $none = new ElectrificationTimeline([], ['state' => false, 'voltage_v' => null, 'source' => 'bdtopo']);
        $this->assertSame(['electrified' => false, 'voltage_v' => null, 'source' => 'bdtopo', 'year' => null], $none->stateAt(1960));
    }

    public function test_recent_electrification_is_not_back_dated_to_2020(): void
    {
        // Gretz - Nogent-sur-Seine : mise sous tension le 3 juillet 2022.
        $t = new ElectrificationTimeline(
            [['date' => '2022-07-03', 'kind' => 'electrification', 'voltage_v' => 25000, 'scope' => 'located']],
            ['state' => true, 'voltage_v' => 25000, 'source' => 'sncf'],
        );
        $this->assertFalse($t->stateAt(2020)['electrified']);
        $this->assertTrue($t->stateAt(2030, true)['electrified']);
    }

    public function test_dismantled_track_and_lgv(): void
    {
        $t = new ElectrificationTimeline([['date' => '1930-01-01', 'kind' => 'electrification', 'voltage_v' => 1500, 'scope' => 'located']]);
        $this->assertSame('status', $t->stateAt(1990, false, 'dismantled')['source']);
        $lgv = new ElectrificationTimeline([], ['state' => null, 'voltage_v' => null, 'source' => null], isLgv: true);
        $this->assertSame(['electrified' => true, 'voltage_v' => 25000, 'source' => 'lgv', 'year' => null], $lgv->stateAt(1990));
    }

    public function test_no_electrification_before_1900_from_misread_events(): void
    {
        // « Chantenay à Savenay : 12 janvier 1885 » est une date d'ouverture.
        $t = new ElectrificationTimeline([['date' => '1885-01-12', 'kind' => 'electrification', 'voltage_v' => null, 'scope' => 'located']]);
        $this->assertFalse($t->isDated());
    }

    // ─── Écartement ─────────────────────────────────────────────────────

    public function test_gauge_classes_and_tags(): void
    {
        $this->assertSame('standard', Gauge::classOf(1435));
        $this->assertSame('metre', Gauge::classOf(1000));
        $this->assertSame('narrow', Gauge::classOf(600));
        $this->assertSame('broad', Gauge::classOf(1668));
        $this->assertNull(Gauge::classOf(null));
        $this->assertSame(1435, Gauge::fromTag('1000;1435'));
        $this->assertSame(600, Gauge::fromTag('600'));
        $this->assertNull(Gauge::fromTag('narrow'));
        $this->assertSame([null, 'narrow'], Gauge::fromBdtopo('Etroite'));
        $this->assertSame([null, 'narrow'], Gauge::fromSncfCatlig('Ligne du réseau conventionnel à voie étroite'));
    }

    public function test_gauge_from_archeo_names(): void
    {
        $this->assertSame([600, 'narrow'], Gauge::fromName('Tinson Oulches 14-18 (voie de 0m60)'));
        $this->assertSame([600, 'narrow'], Gauge::fromName('Dépôt de munitions de Reinange Mil 30-40 V60'));
        $this->assertSame([1435, 'standard'], Gauge::fromName('Ligne Trémery Reinange Mil 30-40 VN'));
        $this->assertSame([700, 'narrow'], Gauge::fromName('Le Huga Les Manchets (voie de 0m70 ouverte vers 1920)'));
        $this->assertSame([600, 'narrow'], Gauge::fromName('Ecouis Saussay-la-Campagne (ligne betteravière 0,60 m)'));
        $this->assertSame([1000, 'metre'], Gauge::fromName('Etricourt Saillisel 14-18 (ligne allemande à voie métrique, puis mise à voie normale)'));
        $this->assertSame([null, null], Gauge::fromName('Verdun Génicourt (réseau militaire 14/18)'));
        $this->assertSame([null, null], Gauge::fromName('Ligne VMI'));
    }

    public function test_gauge_sentences_and_conversions(): void
    {
        $this->assertSame([1000, 'metre'], Gauge::fromLineSentence('La ligne est mise en service entre 1891 et 1893, elle est construite à l\'écartement métrique.'));
        $this->assertSame([null, null], Gauge::fromLineSentence('Elle longe ensuite l\'Auxence où elle croisait à niveau la ligne à voie métrique de la Société générale'));
        $this->assertSame([null, null], Gauge::fromLineSentence('La ligne de chemin de fer secondaire à voie métrique des CFD Réseau des Charentes et Deux-Sèvres de Saint-Jean-d\'Angély à Saint-Saviol, ouverte en 1896', 'Ligne d\'Aiffres à Ruffec'));
        $this->assertSame([1000, 'metre'], Gauge::fromLineSentence('La ligne de Vienne à Voiron est construite à l\'écartement métrique.', 'Ligne de Vienne à Voiron'));
        $this->assertSame(['year' => 1916, 'to_mm' => 1435, 'from_mm' => null], Gauge::conversion('La ligne, mise à voie normale, est rouverte le 21 août 1916.'));
        $this->assertSame(['year' => 1967, 'to_mm' => 1435, 'from_mm' => null], Gauge::conversion('C\'est une ancienne ligne du réseau breton à voie métrique, transformée à l\'écartement normal en 1967.'));
        $this->assertSame(['year' => 1915, 'to_mm' => 1435, 'from_mm' => 1000], Gauge::conversion('Colmar Ensisheim Bollwiller (à voie métrique jusqu’en 1915)'));
    }

    public function test_reseau_breton_carhaix_guingamp_is_metre_gauge_until_1967(): void
    {
        $c = TractionCuration::gaugeConversion('485000');
        $conversions = [['year' => $c['year'], 'from_mm' => $c['from_mm'], 'to_mm' => $c['to_mm'], 'source' => 'curated']];
        $current = ['mm' => 1435, 'class' => 'standard', 'source' => 'osm'];
        $this->assertSame(['mm' => 1000, 'class' => 'metre', 'source' => 'curated'], Gauge::at(1960, $current, [], [], $conversions));
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'curated'], Gauge::at(1970, $current, [], [], $conversions));
        $this->assertSame(1000, TractionCuration::gaugeForName('Ligne de Carhaix à Morlaix'));
    }

    public function test_gauge_precedence_for_current_and_vanished_tracks(): void
    {
        // Voie en place : l'écartement observé vaut pour toutes les ères…
        $this->assertSame('osm', Gauge::at(1900, ['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], [], [], [])['source']);
        // …sauf tracé ancien DATÉ d'un autre écartement (conversion implicite à sa date de fin).
        $ohm = ['mm' => 1000, 'class' => 'metre', 'source' => 'ohm', 'from' => 1898, 'until' => 1950];
        $this->assertSame(1000, Gauge::at(1930, ['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], [$ohm], [], [])['mm']);
        $this->assertSame(1435, Gauge::at(1960, ['mm' => 1435, 'class' => 'standard', 'source' => 'osm'], [$ohm], [], [])['mm']);
        // Ligne disparue : indice historique, puis ligne, puis réseau ; sinon inconnu.
        $this->assertSame(['mm' => 600, 'class' => 'narrow', 'source' => 'archeo'], Gauge::at(1920, null, [['mm' => 600, 'class' => 'narrow', 'source' => 'archeo']], [], []));
        $this->assertSame(['mm' => 1435, 'class' => 'standard', 'source' => 'network'], Gauge::at(1920, null, [], [], [], 1435));
        $this->assertSame(['mm' => null, 'class' => null, 'source' => null], Gauge::at(1920, null, [], [], []));
    }
}
