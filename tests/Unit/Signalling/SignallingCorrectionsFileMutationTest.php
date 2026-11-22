<?php

namespace Tests\Unit\Signalling;

use App\Services\Signalling\SignallingCorrectionsFile;
use PHPUnit\Framework\TestCase;

/** Messages exacts, bornes et valeurs rendues par le contrôle du fichier des corrections de signalisation. */
class SignallingCorrectionsFileMutationTest extends TestCase
{
    /** @param  array<string, mixed>  $over */
    private static function entry(array $over = []): array
    {
        return $over + [
            'line' => '752000', 'action' => 'neutralize',
            'target' => ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2024-11-12'],
            'sources' => [
                ['publisher' => 'Presse A', 'title' => 'Article A', 'url' => 'https://a.example/x', 'published' => '2024-11-13'],
                ['publisher' => 'Presse B', 'title' => 'Article B', 'url' => 'https://b.example/y'],
            ],
            'quote' => 'phrase courte', 'reason' => 'le fait est faux',
        ];
    }

    /** @param  array<string, mixed>  $target */
    private static function withTarget(array $target): array
    {
        return self::entry(['target' => $target + ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2024-11-12']]);
    }

    private static function error(mixed $entry): string
    {
        $r = SignallingCorrectionsFile::parse([$entry]);
        self::assertSame([], $r['corrections']);
        self::assertCount(1, $r['errors']);

        return $r['errors'][0];
    }

    public function test_the_constants_and_default_path(): void
    {
        $this->assertSame(['neutralize', 'replace'], SignallingCorrectionsFile::ACTIONS);
        $this->assertSame(['wikipedia', 'manual'], SignallingCorrectionsFile::TARGET_SOURCES);
    }

    public function test_each_error_names_the_correction_its_line_and_its_system(): void
    {
        $e = self::entry(['action' => 'delete']);
        $r = SignallingCorrectionsFile::parse([self::entry(), $e, self::entry(['line' => '974000', 'action' => 'delete', 'target' => ['source' => 'manual', 'system' => 'KVB', 'date' => '2020']])]);

        $this->assertCount(1, $r['corrections']);
        $this->assertSame([
            'correction n° 2 (752000 ETCS) : action inconnue (neutralize ou replace)',
            'correction n° 3 (974000 KVB) : action inconnue (neutralize ou replace)',
        ], $r['errors']);
        $this->assertSame('correction n° 1 (? ?) : pas un tableau', self::error('texte'));
        $this->assertSame('correction n° 1 (? ?) : champ « line » manquant', self::error([]));
        $this->assertSame('correction n° 1 (752000 ?) : champ « target » manquant', self::error(['line' => '752000', 'action' => 'neutralize']));
        $this->assertSame('correction n° 1 (752000 ?) : fait visé : source inconnue (wikipedia ou manual)', self::error(self::entry(['target' => 'x'])));
    }

    public function test_every_required_field_is_reported_when_missing_empty_or_an_empty_list(): void
    {
        foreach (['line', 'action', 'target', 'sources', 'quote', 'reason'] as $key) {
            $without = self::entry();
            unset($without[$key]);
            $this->assertStringEndsWith("champ « {$key} » manquant", self::error($without), "absent : $key");
            $this->assertStringEndsWith("champ « {$key} » manquant", self::error(self::entry([$key => ''])), "vide : $key");
        }
        $this->assertStringEndsWith('champ « sources » manquant', self::error(self::entry(['sources' => []])));
        $this->assertStringEndsWith('champ « target » manquant', self::error(self::entry(['target' => []])));
        $this->assertStringEndsWith('champ « line » manquant', self::error(self::entry(['line' => null])));
    }

    public function test_the_first_missing_field_in_the_documented_order_wins(): void
    {
        $this->assertStringEndsWith('champ « action » manquant', self::error(['line' => '1', 'reason' => 'r']));
        $this->assertStringEndsWith('champ « quote » manquant', self::error(['line' => '1', 'action' => 'neutralize', 'target' => ['source' => 'manual'], 'sources' => [['x']], 'reason' => 'r']));
    }

    public function test_systems_are_matched_as_a_whole(): void
    {
        foreach (['BAL', 'BAPR', 'BM', 'CT', 'TVM', 'TVM-300', 'TVM-430', 'ETCS', 'ETCS_L1', 'ETCS_L2', 'KVB'] as $system) {
            $this->assertSame([], SignallingCorrectionsFile::parse([self::withTarget(['system' => $system])])['errors'], $system);
        }
        foreach (['XBAL', 'BALX', 'bal', 'ETCS_L3', ' KVB', ''] as $system) {
            $this->assertStringEndsWith('fait visé : système inconnu', self::error(self::withTarget(['system' => $system])), "[$system]");
        }
        $this->assertStringEndsWith('fait visé : système inconnu', self::error(self::entry(['target' => ['source' => 'manual', 'date' => '2024']])));
    }

    public function test_a_system_followed_by_a_line_break_is_refused(): void
    {
        // Sans le drapeau D, `$` accepterait un saut de ligne final.
        $this->assertStringEndsWith('fait visé : système inconnu', self::error(self::withTarget(['system' => "ETCS\n"])));
    }

    public function test_target_sources_are_compared_strictly(): void
    {
        $msg = 'fait visé : source inconnue (wikipedia ou manual)';
        $this->assertStringEndsWith($msg, self::error(self::withTarget(['source' => 'Wikipedia'])));
        $this->assertStringEndsWith($msg, self::error(self::withTarget(['source' => 0])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['target' => ['system' => 'KVB', 'date' => '2024']])));
        $this->assertSame([], SignallingCorrectionsFile::parse([self::withTarget(['source' => 'manual'])])['errors']);
    }

    public function test_dates_follow_the_three_accepted_shapes_and_must_exist(): void
    {
        $unreadable = 'fait visé : date illisible (AAAA, AAAA-MM ou AAAA-MM-JJ)';
        foreach (['2024', '2024-11', '2024-11-12', '2024-02-29', '1999-12-31'] as $date) {
            $this->assertSame([], SignallingCorrectionsFile::parse([self::withTarget(['date' => $date])])['errors'], $date);
        }
        foreach (['24', '20245', '2024-1', '2024-11-1', '2024-11-123', '2024/11/12', ' 2024', '2024 ', 'x2024', '', '2024-11-12x', '2024-1112'] as $date) {
            $this->assertStringEndsWith($unreadable, self::error(self::withTarget(['date' => $date])), "[$date]");
        }
        $this->assertStringEndsWith($unreadable, self::error(self::entry(['target' => ['source' => 'manual', 'system' => 'KVB']])));
        foreach (['2023-02-29', '2024-02-30', '2024-13-01', '2024-00-10', '2024-04-31', '2024-01-00'] as $date) {
            $this->assertStringEndsWith('fait visé : date impossible', self::error(self::withTarget(['date' => $date])), $date);
        }
    }

    public function test_a_section_needs_both_ends_or_none_and_blank_ends_count_as_absent(): void
    {
        $msg = 'fait visé : portion sans ses deux extrémités';
        $this->assertStringEndsWith($msg, self::error(self::withTarget(['from' => 'Metz'])));
        $this->assertStringEndsWith($msg, self::error(self::withTarget(['to' => 'Nancy'])));
        $this->assertStringEndsWith($msg, self::error(self::withTarget(['from' => 'Metz', 'to' => '   '])));
        $this->assertStringEndsWith($msg, self::error(self::withTarget(['from' => '', 'to' => 'Nancy'])));

        $none = SignallingCorrectionsFile::parse([self::withTarget(['from' => ' ', 'to' => ''])]);
        $this->assertSame([], $none['errors']);
        $this->assertNull($none['corrections'][0]['target']['from']);
        $this->assertNull($none['corrections'][0]['target']['to']);
    }

    public function test_the_quote_is_bounded_at_250_characters_counted_as_characters(): void
    {
        $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['quote' => str_repeat('é', 250)])])['errors']);
        $this->assertStringEndsWith('citation de plus de 250 caractères', self::error(self::entry(['quote' => str_repeat('é', 251)])));
    }

    public function test_long_dashes_are_refused_in_the_reason_or_the_quote(): void
    {
        $msg = 'tiret long dans la raison ou la citation';
        $this->assertStringEndsWith($msg, self::error(self::entry(['reason' => "a \u{2014} b"])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['reason' => "a \u{2013} b"])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['reason' => 'a -- b'])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['quote' => "a \u{2014} b"])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['quote' => 'a--b'])));
        $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['reason' => 'a - b', 'quote' => 'a-b'])])['errors']);
    }

    public function test_sources_must_be_a_list_of_complete_documents(): void
    {
        $msg = 'source sans éditeur ou sans titre';
        $ok = ['publisher' => 'B', 'title' => 'T', 'url' => 'https://b.example/y'];
        $this->assertStringEndsWith('sources illisibles', self::error(self::entry(['sources' => 'texte'])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, 'texte']])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, ['title' => 'T', 'url' => 'https://a.example']]])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, ['publisher' => '  ', 'title' => 'T', 'url' => 'https://a.example']]])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, ['publisher' => 'P', 'url' => 'https://a.example']]])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, ['publisher' => 'P', 'title' => " \t", 'url' => 'https://a.example']]])));
    }

    public function test_source_links_must_be_https_and_valid_urls(): void
    {
        $msg = 'lien de source invalide (https attendu)';
        $ok = ['publisher' => 'B', 'title' => 'T', 'url' => 'https://b.example/y'];
        foreach (['http://a.example', 'ftp://a.example', 'a.example', 'https//a.example', ' https://a.example', 'https://', 'https://exa mple.org'] as $url) {
            $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, ['publisher' => 'P', 'title' => 'T', 'url' => $url]]])), "[$url]");
        }
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, ['publisher' => 'P', 'title' => 'T']]])));
    }

    public function test_the_publication_date_is_optional_but_must_be_readable(): void
    {
        $msg = 'date de publication illisible';
        $ok = ['publisher' => 'B', 'title' => 'T', 'url' => 'https://b.example/y'];
        foreach (['2024', '2024-05', '2024-05-06'] as $published) {
            $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['sources' => [$ok, ['publisher' => 'P', 'title' => 'T', 'url' => 'https://a.example', 'published' => $published]]])])['errors'], $published);
        }
        foreach (['24', '2024-5', 'mai 2024', '2024-05-06x', ''] as $published) {
            $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$ok, ['publisher' => 'P', 'title' => 'T', 'url' => 'https://a.example', 'published' => $published]]])), "[$published]");
        }
    }

    public function test_a_source_from_archeoferroviaire_is_not_citable_whatever_its_position(): void
    {
        $ok = ['publisher' => 'B', 'title' => 'T', 'url' => 'https://b.example/y', 'official' => true];
        $archeo = ['publisher' => 'A', 'title' => 'T', 'url' => 'https://www.archeoferroviaire.fr/x'];

        $this->assertStringEndsWith('source non citable', self::error(self::entry(['sources' => [$ok, $archeo]])));
        $this->assertStringEndsWith('source non citable', self::error(self::entry(['sources' => [$archeo, $ok]])));
    }

    public function test_independence_needs_an_official_source_or_two_different_hosts(): void
    {
        $msg = 'ni source officielle ni deux sources indépendantes (deux sites différents)';
        $a = ['publisher' => 'A', 'title' => 'T', 'url' => 'https://a.example/1'];
        $a2 = ['publisher' => 'A', 'title' => 'T2', 'url' => 'https://a.example/2'];
        $b = ['publisher' => 'B', 'title' => 'T', 'url' => 'https://b.example/1'];

        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$a, $a2]])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$a]])));
        $this->assertStringEndsWith($msg, self::error(self::entry(['sources' => [$a, $a2 + ['official' => false]]])));
        $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['sources' => [$a, $b]])])['errors']);
        $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['sources' => [$a + ['official' => true]]])])['errors']);
        $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['sources' => [$a, $a2 + ['official' => true]]])])['errors']);
    }

    public function test_replace_and_neutralize_rules_for_the_right_fact(): void
    {
        $this->assertStringEndsWith('remplacement sans fait juste (champ « with »)', self::error(self::entry(['action' => 'replace'])));
        $this->assertStringEndsWith('remplacement sans fait juste (champ « with »)', self::error(self::entry(['action' => 'replace', 'with' => 'texte'])));
        $this->assertStringEndsWith('fait juste (« with ») seulement avec l\'action replace', self::error(self::entry(['with' => ['system' => 'ETCS']])));
        $this->assertStringEndsWith('fait juste (« with ») seulement avec l\'action replace', self::error(self::entry(['with' => 'texte'])));
        $this->assertSame([], SignallingCorrectionsFile::parse([self::entry(['with' => null])])['errors'], 'with absent ou nul : accepté pour neutralize');

        $bad = self::error(self::entry(['action' => 'replace', 'with' => ['system' => 'ETCS_L2', 'date' => '2026', 'scope' => 'whole_line', 'summary' => 'x', 'sources' => []]]));
        $this->assertStringContainsString('fait juste refusé : fait n° 1 (752000 ETCS_L2) : ', $bad);
    }

    public function test_a_valid_replacement_is_flattened_with_trimmed_texts(): void
    {
        $right = ['system' => 'ETCS_L2', 'date' => '2026-01-01', 'scope' => 'whole_line', 'summary' => 'Mise en service.', 'sources' => [
            ['publisher' => 'SNCF Réseau', 'title' => 'Communiqué', 'url' => 'https://a.example/x', 'official' => true],
        ]];
        $r = SignallingCorrectionsFile::parse([self::entry([
            'action' => 'replace', 'with' => $right, 'line' => 752000, 'quote' => '  citation  ', 'reason' => "\n raison \n",
            'target' => ['source' => 'manual', 'system' => 'KVB', 'date' => '2020-03', 'from' => ' A ', 'to' => 'B '],
            'sources' => [
                ['publisher' => ' Éditeur ', 'title' => ' Titre ', 'url' => 'https://a.example/x', 'published' => '2020-04-05', 'official' => 1],
                ['publisher' => 'P', 'title' => 'T', 'url' => 'https://b.example/y'],
            ],
        ])]);

        $this->assertSame([], $r['errors']);
        $this->assertSame([
            'line' => '752000',
            'action' => 'replace',
            'target' => ['source' => 'manual', 'system' => 'KVB', 'date' => '2020-03-01', 'from' => 'A', 'to' => 'B'],
            'sources' => [
                ['publisher' => 'Éditeur', 'title' => 'Titre', 'url' => 'https://a.example/x', 'published' => '2020-04-05', 'official' => true],
                ['publisher' => 'P', 'title' => 'T', 'url' => 'https://b.example/y', 'published' => null, 'official' => false],
            ],
            'quote' => 'citation',
            'reason' => 'raison',
            'with' => $right + ['line' => '752000'],
        ], $r['corrections'][0]);
    }

    public function test_the_target_date_is_completed_by_precision_and_sources_are_reindexed(): void
    {
        $year = SignallingCorrectionsFile::parse([self::withTarget(['date' => '1989'])])['corrections'][0];
        $day = SignallingCorrectionsFile::parse([self::entry(['sources' => ['a' => ['publisher' => 'A', 'title' => 'T', 'url' => 'https://a.example'], 'b' => ['publisher' => 'B', 'title' => 'T', 'url' => 'https://b.example']]])])['corrections'][0];

        $this->assertSame('1989-01-01', $year['target']['date']);
        $this->assertSame([0, 1], array_keys($day['sources']));
        $this->assertSame('2024-11-12', $day['target']['date']);
        $this->assertNull($day['with']);
        $this->assertNull($day['target']['from']);
    }

    public function test_matching_ignores_case_and_edge_spaces_of_the_portion_only(): void
    {
        $c = SignallingCorrectionsFile::parse([self::withTarget(['date' => '1962-06', 'system' => 'BAL', 'from' => 'Garçes', 'to' => 'Nancy'])])['corrections'][0];
        $base = [$c, 'wikipedia', ['752000'], 'BAL', '1962-06-01', 'Garçes', 'Nancy'];

        $this->assertTrue(SignallingCorrectionsFile::matches(...$base));
        $this->assertTrue(SignallingCorrectionsFile::matches($c, 'wikipedia', ['x', '752000'], 'BAL', '1962-06-01', "  GARÇES\t", ' nancy '));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'BAL', '1962-06-01', 'Garçes', 'Metz'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'BAL', '1962-06-01', 'Metz', 'Nancy'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'BAL', '1962-06-01', 'Garçes', null));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', [], 'BAL', '1962-06-01', 'Garçes', 'Nancy'));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752'], 'BAL', '1962-06-01', 'Garçes', 'Nancy'));

        $whole = SignallingCorrectionsFile::parse([self::entry()])['corrections'][0];
        $this->assertTrue(SignallingCorrectionsFile::matches($whole, 'wikipedia', ['752000'], 'ETCS', '2024-11-12', null, null));
        $this->assertTrue(SignallingCorrectionsFile::matches($whole, 'wikipedia', ['752000'], 'ETCS', '2024-11-12', '  ', ''));
        $this->assertFalse(SignallingCorrectionsFile::matches($whole, 'wikipedia', ['752000'], 'ETCS', '2024-11-12', 'Metz', null));
        $this->assertFalse(SignallingCorrectionsFile::matches($whole, 'wikipedia', ['752000'], 'ETCS', '2024-11-12', null, 'Metz'));
        $this->assertFalse(SignallingCorrectionsFile::matches($whole, 'wikipedia', ['752000'], 'ETCS', '2024-11', null, null));
    }

    public function test_the_line_is_looked_up_by_strict_equality(): void
    {
        $c = SignallingCorrectionsFile::parse([self::entry(['line' => 'rfn-752000'])])['corrections'][0];

        $this->assertTrue(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000', 'rfn-752000'], 'ETCS', '2024-11-12', null, null));
        $this->assertFalse(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'ETCS', '2024-11-12', null, null));
    }

    public function test_the_stored_json_is_ordered_unescaped_and_without_the_official_flag(): void
    {
        $c = SignallingCorrectionsFile::parse([self::entry(['reason' => 'dé/fait', 'quote' => '« é »', 'sources' => [
            ['publisher' => 'SNCF', 'title' => 'T', 'url' => 'https://a.example/x/y', 'official' => true, 'published' => '2024'],
        ]])])['corrections'][0];

        $this->assertSame(
            '{"action":"neutralize","reason":"dé/fait","quote":"« é »","sources":[{"publisher":"SNCF","title":"T","url":"https://a.example/x/y","published":"2024"}]}',
            SignallingCorrectionsFile::stored($c),
        );
    }

    public function test_the_description_names_line_system_date_and_source(): void
    {
        $c = SignallingCorrectionsFile::parse([self::entry()])['corrections'][0];

        $this->assertSame('752000 ETCS du 2024-11-12 (wikipedia)', SignallingCorrectionsFile::describe($c));
    }

    public function test_curly_quotes_and_accents_are_not_long_dashes(): void
    {
        // Les guillemets courbes partagent des octets avec les tirets longs en UTF-8 : le contrôle lit des caractères.
        $accepted = self::entry(['reason' => "« l’\u{201C}ancien\u{201D} système » n’existait pas", 'quote' => "\u{2018}a\u{2019} \u{2026} é"]);

        $this->assertSame([], SignallingCorrectionsFile::parse([$accepted])['errors']);
    }

    public function test_leap_days_follow_the_calendar_of_the_year_written(): void
    {
        foreach (['2000-02-29', '2024-02-29', '2400-02-29'] as $date) {
            $this->assertSame([], SignallingCorrectionsFile::parse([self::withTarget(['date' => $date])])['errors'], $date);
        }
        foreach (['1900-02-29', '2100-02-29', '2023-02-29'] as $date) {
            $this->assertStringEndsWith('fait visé : date impossible', self::error(self::withTarget(['date' => $date])), $date);
        }
    }

    public function test_the_portion_is_compared_without_case_even_for_accented_capitals(): void
    {
        $c = SignallingCorrectionsFile::parse([self::withTarget(['from' => 'Étaples', 'to' => 'Île-Adam'])])['corrections'][0];

        $this->assertTrue(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'ETCS', '2024-11-12', 'étaples', 'île-adam'));
        $this->assertTrue(SignallingCorrectionsFile::matches($c, 'wikipedia', ['752000'], 'ETCS', '2024-11-12', 'ÉTAPLES', 'ÎLE-ADAM'));
    }
}
