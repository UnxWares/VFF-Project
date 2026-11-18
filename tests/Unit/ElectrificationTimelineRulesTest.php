<?php

namespace Tests\Unit;

use App\Services\Traction\ElectrificationTimeline as T;
use PHPUnit\Framework\TestCase;

/**
 * Chronologie d'électrification d'une portion (ElectrificationTimeline) :
 * filtres des événements, rejeu par ère, tension (citée, voisine, actuelle,
 * règle d'époque), bornes historiques sans événement.
 */
class ElectrificationTimelineRulesTest extends TestCase
{
    private static function e(string $date, ?int $v = null, string $scope = 'line', string $kind = 'electrification', array $extra = []): array
    {
        return ['date' => $date, 'voltage_v' => $v, 'scope' => $scope, 'kind' => $kind] + $extra;
    }

    private static function cur(?bool $state, ?int $v = null, ?string $source = 'sncf'): array
    {
        return ['state' => $state, 'voltage_v' => $v, 'source' => $source];
    }

    private static function at(T $t, int $year, bool $current = false, string $status = 'in_service'): string
    {
        $s = $t->stateAt($year, $current, $status);

        return ($s['electrified'] ? 'E'.$s['voltage_v'].'/'.$s['year'] : 'N').':'.$s['source'];
    }

    public function test_events_are_filtered(): void
    {
        $t = new T([
            self::e('1890-01-01', 600),            // avant 1900 : date d'ouverture lue comme électrification
            self::e('1930-01-01', 25000),          // 50 Hz avant 1951 : erreur de lecture
            self::e('1935-01-01', null, 'other'),  // portée inconnue
            self::e('1940-01-01', null, 'line', 'ignore'),
        ]);
        $this->assertFalse($t->isDated());
        $this->assertNull($t->firstYear());

        $t = new T([self::e('1890-01-01', 600, 'curated')]);
        $this->assertSame(1890, $t->firstYear(), 'curation : pas de borne');
    }

    public function test_curated_events_replace_the_others(): void
    {
        $t = new T([self::e('1925-01-01', 1500, 'located'), self::e('1960-01-01', 25000, 'curated')]);

        $this->assertSame('N:curated', self::at($t, 1930));
        $this->assertSame('E25000/1960:curated', self::at($t, 1970));
    }

    public function test_best_scope_and_later_whole_line_event(): void
    {
        // Portion électrifiée aujourd'hui, événement localisé puis ré-électrification « toute la ligne ».
        $t = new T([self::e('1925-01-01', 1500, 'located'), self::e('1928-01-01', 1500, 'unlocated'), self::e('1970-01-01', 25000, 'line')], self::cur(true, 25000));

        $this->assertSame('E1500/1925:event', self::at($t, 1930));
        $this->assertSame('E25000/1970:event_line', self::at($t, 1980));
        $this->assertSame('N:event', self::at($t, 1920));
    }

    public function test_post_1951_event_contradicting_the_current_voltage_is_another_section(): void
    {
        $t = new T([self::e('1960-01-01', 25000, 'line')], self::cur(true, 1500));
        $this->assertFalse($t->isDated());
        $t = new T([self::e('1960-01-01', 1500, 'line')], self::cur(true, 1500));
        $this->assertSame(1960, $t->firstYear());
        $t = new T([self::e('1960-01-01', 25000, 'located')], self::cur(true, 1500));
        $this->assertSame(1960, $t->firstYear(), 'événement localisé : gardé');
    }

    public function test_not_electrified_today_keeps_only_located_or_later_removed(): void
    {
        $t = new T([self::e('1925-01-01', 1500, 'line')], self::cur(false));
        $this->assertFalse($t->isDated(), 'date de toute la ligne : autre partie de la ligne');

        $t = new T([self::e('1925-01-01', 1500, 'line'), self::e('1970-01-01', null, 'line', 'deelectrification')], self::cur(false));
        $this->assertSame('E1500/1925:event_line', self::at($t, 1950));
        $this->assertSame('N:event', self::at($t, 1980));

        $t = new T([self::e('1925-01-01', 1500, 'located')], self::cur(false));
        $this->assertTrue($t->isDated());
    }

    public function test_status_lgv_and_current_era(): void
    {
        $t = new T([], self::cur(true, 25000), isLgv: true);
        $this->assertSame('N:status', self::at($t, 2030, true, 'dismantled'));
        $this->assertSame('N:status', self::at($t, 1990, false, 'construction'));
        $this->assertSame('E25000/:lgv', self::at($t, 1990));
        $this->assertSame('N:unknown', self::at($t, 1975), 'LGV avant 1981 : pas encore (date inconnue)');
        $this->assertSame('E25000/:sncf', self::at($t, 2030, true));

        $lgvUnknownVoltage = new T([], self::cur(true, null), isLgv: true);
        $this->assertSame('E25000/:sncf', self::at($lgvUnknownVoltage, 2030, true), 'LGV : 25 kV par défaut');

        $t = new T([self::e('2022-06-01', 25000, 'line')], self::cur(true, 25000));
        $this->assertSame('N:event_line', self::at($t, 2020), 'électrifiée après 2020 : pas en 2020');
        $this->assertSame('E25000/2022:sncf', self::at($t, 2030, true));

        $off = new T([], self::cur(false, null, 'osm'));
        $this->assertSame('N:osm', self::at($off, 2030, true));
        $this->assertSame('N:osm', self::at($off, 1950));
    }

    public function test_midi_12kv_becomes_1500v_after_1923(): void
    {
        $t = new T([self::e('1912-01-01', 12000, 'line')]);
        $this->assertSame('E12000/1912:event_line', self::at($t, 1920));
        $this->assertSame('E1500/1912:event_line', self::at($t, 1923));
        $curated = new T([self::e('1912-01-01', 12000, 'curated')]);
        $this->assertSame('E12000/1912:curated', self::at($curated, 1930), 'curation : tension gardée');
    }

    public function test_reelectrification_keeps_the_first_date_unless_voltage_changes(): void
    {
        $t = new T([self::e('1925-01-01', 1500), self::e('1930-01-01', null), self::e('1935-01-01', 1500)]);
        $this->assertSame('E1500/1925:event_line', self::at($t, 1940));
        $t = new T([self::e('1925-01-01', null), self::e('1935-01-01', 1500)]);
        $this->assertSame('E1500/1925:event_line', self::at($t, 1940), 'tension complétée par le second');
        $t = new T([self::e('1925-01-01', 1500), self::e('1960-01-01', 25000)]);
        $this->assertSame('E25000/1960:event_line', self::at($t, 1970));
        $t = new T([self::e('1925-01-01', 1500), self::e('1925-01-01', null, 'line', 'deelectrification')]);
        $this->assertSame('E1500/1925:event_line', self::at($t, 1930), 'même jour : fin de l\'ancien régime puis mise sous tension');
    }

    public function test_voltage_rules(): void
    {
        // Tension voisine (avant 1951) préférée à l'actuelle.
        $t = new T([self::e('1928-01-01', null, 'line', 'electrification', ['voltage_hint' => 750])], self::cur(true, 25000));
        $this->assertSame('E750/1928:event_line', self::at($t, 1930));
        // Tension voisine 50 Hz avant 1951 : ignorée, règle 1 500 V.
        $t = new T([self::e('1928-01-01', null, 'line', 'electrification', ['voltage_hint' => 25000])]);
        $this->assertSame('E1500/1928:event_line', self::at($t, 1930));
        // Après 1951 : l'actuelle prime sur la voisine.
        $t = new T([self::e('1960-01-01', null, 'line', 'electrification', ['voltage_hint' => 1500])], self::cur(true, 25000));
        $this->assertSame('E25000/1960:event_line', self::at($t, 1970));
        $t = new T([self::e('1960-01-01', null, 'line', 'electrification', ['voltage_hint' => 1500])]);
        $this->assertSame('E1500/1960:event_line', self::at($t, 1970), 'sans état actuel : la voisine');
        // Actuelle compatible (pas de 25 kV avant 1951).
        $t = new T([self::e('1930-01-01')], self::cur(true, 25000));
        $this->assertSame('E1500/1930:event_line', self::at($t, 1940));
        $t = new T([self::e('1930-01-01')], self::cur(true, 3000));
        $this->assertSame('E3000/1930:event_line', self::at($t, 1940));
        // Règle d'époque : 1 500 V entre 1921 et 1950, inconnue sinon.
        $this->assertSame('E1500/1921:event_line', self::at(new T([self::e('1921-01-01')]), 1930));
        $this->assertSame('E/1920:event_line', self::at(new T([self::e('1920-06-01')]), 1930));
        $this->assertSame('E/1951:event_line', self::at(new T([self::e('1951-01-01')]), 1960));
        // Dernière décennie : la tension observée prime, sauf événement postérieur.
        $t = new T([self::e('1930-01-01', 1500)], self::cur(true, 25000));
        $this->assertSame('E25000/1930:sncf', self::at($t, 2020));
        $later = new T([self::e('1930-01-01', 1500), self::e('2022-01-01', null, 'line', 'deelectrification')], self::cur(true, 25000));
        $this->assertSame('E1500/1930:event_line', self::at($later, 2010));
    }

    public function test_no_event_bounds(): void
    {
        $known = new T([], self::cur(true, 1500));
        $this->assertSame('N:rule', self::at($known, 1890));
        $this->assertSame('N:rule', self::at($known, 1920), '1 500 V : pas avant la décision de 1920');
        $this->assertSame('N:unknown', self::at($known, 1921));
        $this->assertSame('E1500/:sncf', self::at($known, 2020));

        $ac = new T([], self::cur(true, 25000));
        $this->assertSame('N:rule', self::at($ac, 1950));
        $this->assertSame('N:unknown', self::at($ac, 1951));

        $third = new T([], self::cur(true, 750));
        $this->assertSame('N:unknown', self::at($third, 1910), 'troisième rail : pas de borne 1920');

        $hist = new T([], self::cur(null), historicElectrified: true);
        $this->assertSame('N:unknown', self::at($hist, 1950));
        $this->assertSame('N:unknown', self::at($hist, 2020), 'caténaire disparue : pas d\'état actuel');

        $removed = new T([self::e('1970-01-01', null, 'line', 'deelectrification')]);
        $this->assertSame('N:rule', self::at($removed, 1895));
        $this->assertSame('N:unknown', self::at($removed, 1950), 'électrifiée avant, date inconnue');

        $this->assertSame('N:none', self::at(new T([]), 1950));
        $this->assertSame('N:event_unlocated', self::at(new T([self::e('1960-01-01', 1500, 'unlocated')]), 1950));
    }
}
