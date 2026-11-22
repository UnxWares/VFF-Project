<?php

namespace Tests\Unit;

use App\Services\ManualStations;
use Tests\TestCase;

/** Validation et enregistrements des gares saisies à la main : messages et bornes exacts. */
class ManualStationsMutationTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function entry(): array
    {
        return [
            'ref' => 'exemple-gare', 'name' => 'Exemple', 'lat' => 46.0, 'lon' => 2.0,
            'opened_on' => '1855-01-01', 'opened_precision' => 'year', 'closed_on' => '1962-01-01', 'closed_precision' => 'month',
            'source_url' => 'https://exemple.test/p', 'quote' => 'citation', 'reason' => 'motif', 'network' => 'Nord',
        ];
    }

    /** @param  array<string, mixed>  $changes */
    private static function errors(array $changes): array
    {
        return ManualStations::validate($changes + self::entry());
    }

    public function test_constants(): void
    {
        $this->assertSame('manual', ManualStations::SOURCE);
        $this->assertSame(['day', 'month', 'year'], ManualStations::PRECISIONS);
        $this->assertSame([], ManualStations::validate(self::entry()));
    }

    public function test_each_required_text_field_is_reported_by_name_when_missing_blank_or_not_text(): void
    {
        foreach (['ref', 'name', 'source_url', 'quote', 'reason'] as $k) {
            $this->assertContains("{$k} manquant", self::errors([$k => '']), $k.' vide');
            $this->assertContains("{$k} manquant", self::errors([$k => " \t "]), $k.' blanc');
            $this->assertContains("{$k} manquant", self::errors([$k => 12]), $k.' nombre');
            $this->assertContains("{$k} manquant", self::errors([$k => null]), $k.' nul');
            $entry = self::entry();
            unset($entry[$k]);
            $this->assertContains("{$k} manquant", ManualStations::validate($entry), $k.' absent');
        }
        $this->assertSame(['name manquant'], self::errors(['name' => ' ']));
    }

    public function test_ref_must_be_a_lowercase_slug(): void
    {
        foreach (['a', 'a-b', 'a1-b2-c3', '0', '12-34'] as $ok) {
            $this->assertSame([], self::errors(['ref' => $ok]), $ok);
        }
        foreach (['A', 'a_b', '-a', 'a-', 'a--b', 'a b', 'é', 'a-B'] as $bad) {
            $this->assertSame(['ref : slug minuscule'], self::errors(['ref' => $bad]), $bad);
        }
        $this->assertSame(['ref manquant', 'ref : slug minuscule'], self::errors(['ref' => '']));
    }

    public function test_source_must_be_https(): void
    {
        $this->assertSame([], self::errors(['source_url' => 'https://x']));
        $this->assertSame(['source_url : lien https'], self::errors(['source_url' => 'http://x']));
        $this->assertSame(['source_url : lien https'], self::errors(['source_url' => 'ftp://x']));
        $this->assertSame(['source_url : lien https'], self::errors(['source_url' => ' https://x']));
        $this->assertSame(['source_url manquant', 'source_url : lien https'], self::errors(['source_url' => '']));
    }

    public function test_coordinates_bounds_are_inclusive_and_numeric_text_is_accepted(): void
    {
        $message = 'lat, lon : coordonnées WGS 84 de France métropolitaine';
        foreach ([[41, 2], [52, 2], [46, -5.5], [46, 10], ['46.5', '2.5'], [41.0, -5.5], [52.0, 10.0]] as [$lat, $lon]) {
            $this->assertSame([], self::errors(['lat' => $lat, 'lon' => $lon]), "{$lat},{$lon}");
        }
        foreach ([[40.99, 2], [52.01, 2], [46, -5.51], [46, 10.01], ['x', 2], [46, 'y'], [null, 2], [46, null], ['46x', 2], [46, '0x'], [46, '']] as [$lat, $lon]) {
            $this->assertSame([$message], self::errors(['lat' => $lat, 'lon' => $lon]), json_encode([$lat, $lon]));
        }
        $entry = self::entry();
        unset($entry['lat']);
        $this->assertSame([$message], ManualStations::validate($entry));
    }

    public function test_dates_forms_and_precision_for_both_opening_and_closing(): void
    {
        foreach (['opened', 'closed'] as $k) {
            $this->assertSame([], self::errors(["{$k}_on" => '1900-02-28', "{$k}_precision" => 'day']));
            $this->assertSame([], self::errors(["{$k}_on" => '1904-02-29', "{$k}_precision" => 'year']));
            foreach (['1900-02-29', '1901-13-01', '1901-00-10', '1901-04-31', '1901-4-3', '19010403', '1901-04-03 ', 'texte', 12] as $bad) {
                $errors = self::errors(["{$k}_on" => $bad]);
                $this->assertContains("{$k}_on : date AAAA-MM-JJ", $errors, json_encode($bad));
            }
            $this->assertSame(["{$k}_precision : day, month ou year"], self::errors(["{$k}_precision" => 'decade']));
            $this->assertSame(["{$k}_precision : day, month ou year"], self::errors(["{$k}_precision" => null]));
            // Sans date, la précision n'est pas contrôlée.
            $this->assertSame([], self::errors(["{$k}_on" => null, "{$k}_precision" => 'decade']));
            $this->assertContains("{$k}_on : date AAAA-MM-JJ", self::errors(["{$k}_on" => '1900x02x03-1999-12-31']), 'date précédée d un chiffre de trop');
        }
        $this->assertSame(['opened_on : date AAAA-MM-JJ', 'opened_precision : day, month ou year'], self::errors(['opened_on' => '1855-02-30', 'opened_precision' => 'x']));
        // Une ouverture absente n'arrête pas le contrôle de la fermeture.
        $this->assertSame(['closed_on : date AAAA-MM-JJ'], self::errors(['opened_on' => null, 'closed_on' => '1900-02-30', 'closed_precision' => 'day']));
    }

    public function test_closing_before_opening_is_refused_and_the_same_day_is_not(): void
    {
        $this->assertSame(["fermeture avant l'ouverture"], self::errors(['opened_on' => '1900-05-02', 'closed_on' => '1900-05-01']));
        $this->assertSame([], self::errors(['opened_on' => '1900-05-02', 'closed_on' => '1900-05-02']));
        $this->assertSame([], self::errors(['opened_on' => '1900-05-02', 'closed_on' => '1900-05-03']));
        $entry = self::entry();
        unset($entry['opened_on'], $entry['opened_precision']);
        $this->assertSame([], ManualStations::validate(['closed_on' => '1800-01-01'] + $entry));
        // Une date malformée n'ajoute pas le message d'ordre quand l'autre est absente.
        $entry = self::entry();
        unset($entry['closed_on'], $entry['closed_precision']);
        $this->assertSame([], ManualStations::validate($entry));
    }

    public function test_long_dashes_are_refused_in_name_quote_and_reason_only(): void
    {
        foreach (['name', 'quote', 'reason'] as $k) {
            $this->assertSame(["{$k} : tiret long"], self::errors([$k => "a \u{2014} b"]), $k.' cadratin');
            $this->assertSame(["{$k} : tiret long"], self::errors([$k => "a \u{2013} b"]), $k.' demi-cadratin');
            $this->assertSame(["{$k} : tiret long"], self::errors([$k => 'a -'.'- b']), $k.' double trait');
            $this->assertSame([], self::errors([$k => 'a - b']), $k.' trait simple');
        }
        $this->assertSame([], self::errors(['network' => "Nord \u{2014} Est"]));
    }

    public function test_all_returns_the_entries_given(): void
    {
        $entries = [self::entry()];

        $this->assertSame($entries, (new ManualStations($entries))->all());
        $this->assertSame([], (new ManualStations([]))->all());
    }

    public function test_records_have_the_exact_import_shape(): void
    {
        $result = (new ManualStations([self::entry()]))->records();

        $this->assertSame([], $result['errors']);
        $this->assertSame([[
            'source_ref' => 'exemple-gare', 'name' => 'Exemple', 'lat' => 46.0, 'lon' => 2.0, 'kind' => 'gare', 'lifecycle' => 'historic',
            'opened_on' => '1855-01-01', 'opened_precision' => 'year', 'closed_on' => '1962-01-01', 'closed_precision' => 'month',
            'passenger' => true,
            'properties' => ['source_url' => 'https://exemple.test/p', 'quote' => 'citation', 'reason' => 'motif', 'network' => 'Nord'],
        ]], $result['records']);
    }

    public function test_a_station_without_dates_or_network_is_active_with_null_dates_and_filtered_properties(): void
    {
        $entry = array_diff_key(self::entry(), array_flip(['opened_on', 'opened_precision', 'closed_on', 'closed_precision', 'network']));
        $entry['lat'] = '46.25';
        $entry['lon'] = '2';

        $record = (new ManualStations([$entry]))->records()['records'][0];

        $this->assertSame('active', $record['lifecycle']);
        $this->assertSame(46.25, $record['lat']);
        $this->assertSame(2.0, $record['lon']);
        $this->assertNull($record['opened_on']);
        $this->assertNull($record['opened_precision']);
        $this->assertNull($record['closed_on']);
        $this->assertNull($record['closed_precision']);
        $this->assertSame(['source_url' => 'https://exemple.test/p', 'quote' => 'citation', 'reason' => 'motif'], $record['properties']);
    }

    public function test_invalid_and_duplicate_entries_are_reported_by_ref_or_position_and_not_imported(): void
    {
        $ok = self::entry();
        $dup = ['name' => 'Autre'] + $ok;
        $bad = ['name' => ''] + ['ref' => 'autre-gare'] + $ok;
        $noRef = $ok;
        unset($noRef['ref']);
        $badRef = ['ref' => 12] + $ok;

        $result = (new ManualStations([$ok, $dup, $bad, $noRef, $badRef]))->records();

        $this->assertCount(1, $result['records']);
        $this->assertSame('exemple-gare', $result['records'][0]['source_ref']);
        $this->assertSame(['ref en double'], $result['errors']['exemple-gare']);
        $this->assertSame(['name manquant'], $result['errors']['autre-gare']);
        $this->assertSame(['ref manquant'], $result['errors']['#3']);
        $this->assertSame(['ref manquant'], $result['errors']['#4']);
        $this->assertSame(['exemple-gare', 'autre-gare', '#3', '#4'], array_keys($result['errors']));
    }

    public function test_a_second_entry_is_flagged_even_when_otherwise_invalid_and_errors_accumulate(): void
    {
        $a = self::entry();
        $b = ['source_url' => 'http://x'] + $a;

        $errors = (new ManualStations([$a, $b]))->records()['errors'];

        $this->assertSame(['source_url : lien https', 'ref en double'], $errors['exemple-gare']);
    }

    public function test_without_a_file_the_entries_come_from_the_versioned_data(): void
    {
        $file = dirname(__DIR__, 2).'/database/data/gares_manuelles.php';

        $this->assertSame(require $file, (new ManualStations)->all());
    }

    public function test_a_trailing_line_break_is_not_accepted_in_a_slug_or_a_date(): void
    {
        // Sans le drapeau D, `$` accepterait un saut de ligne final.
        $this->assertSame(['ref : slug minuscule'], self::errors(['ref' => "exemple-gare\n"]));
        $this->assertContains('opened_on : date AAAA-MM-JJ', self::errors(['opened_on' => "1855-01-01\n"]));
    }
}
