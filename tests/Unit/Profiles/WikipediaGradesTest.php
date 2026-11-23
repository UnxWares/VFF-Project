<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\WikipediaGrades;
use PHPUnit\Framework\TestCase;

/**
 * Lecture de la déclivité maximale dans un article de Wikipédia (WikipediaGrades) : infobox, texte,
 * contrôles d'identité de l'article et de plausibilité, fusion avec le fichier en place.
 */
class WikipediaGradesTest extends TestCase
{
    private const INFOBOX = "{{Infobox Ligne ferroviaire\n | nomligne = Argentan à Granville\n | numéro = 405 000\n | longueur = 131<ref>RFN</ref>\n | pente = 10 ‰ <!-- vers Briouze -->\n}}\n";

    /** @return array{title: string, revid: int, content: string} */
    private static function article(string $content, string $title = 'Ligne d\'Argentan à Granville'): array
    {
        return ['title' => $title, 'revid' => 240174253, 'content' => $content];
    }

    /** @return \Closure(): (array{value: float, kind: string}|null) */
    private static function measure(?float $value, string $kind = 'track'): \Closure
    {
        return fn (): ?array => $value === null ? null : ['value' => $value, 'kind' => $kind];
    }

    public function test_the_infobox_fields_are_read_without_notes_or_comments(): void
    {
        $fields = WikipediaGrades::infobox(self::INFOBOX);

        $this->assertSame(['numéro' => '405 000', 'longueur' => '131', 'pente' => '10 ‰'], $fields);
        $this->assertSame(10.0, WikipediaGrades::grade('10 ‰'));
        $this->assertSame(7.8, WikipediaGrades::grade('7,8'));
        $this->assertSame(15.0, WikipediaGrades::grade('{{unité|15|‰}}'));
        $this->assertSame(12.0, WikipediaGrades::grade('12 mm/m'));
        $this->assertNull(WikipediaGrades::grade('10 à 15 ‰'));
        $this->assertNull(WikipediaGrades::grade('< 70'));
        $this->assertSame(41.028, WikipediaGrades::length('41.028 km'));
    }

    public function test_the_line_number_must_be_the_whole_field(): void
    {
        $this->assertTrue(WikipediaGrades::sameCode('405 000', '405000'));
        $this->assertTrue(WikipediaGrades::sameCode('088000', '88000'));
        $this->assertFalse(WikipediaGrades::sameCode('834000 <small>(deuxième section)</small>', '834000'));
        $this->assertFalse(WikipediaGrades::sameCode('405000', '406000'));
        $this->assertFalse(WikipediaGrades::sameCode('', '405000'));
    }

    public function test_the_infobox_is_taken_when_its_length_or_number_matches_the_line(): void
    {
        $byLength = WikipediaGrades::decide(self::article(self::INFOBOX), '999999', 131_500, false, false, self::measure(null));
        $this->assertSame('infobox_length', $byLength['status']);
        $this->assertNotNull($byLength['entry']);
        $this->assertSame(10, $byLength['entry']['max_grade_permil']);
        $this->assertSame('pente = 10 ‰ ; longueur = 131', $byLength['entry']['quote']);
        $this->assertStringEndsWith('&oldid=240174253', $byLength['entry']['source_url']);

        // Longueur à plus de 25 % : le numéro de ligne suffit.
        $byNumber = WikipediaGrades::decide(self::article(self::INFOBOX), '405000', 60_000, false, false, self::measure(null));
        $this->assertSame('infobox_number', $byNumber['status']);
        $this->assertSame('pente = 10 ‰ ; numéro = 405 000', $byNumber['entry']['quote'] ?? null);

        // Ni l'un ni l'autre : l'article décrit sans doute une autre ligne, et son texte ne dit rien.
        $this->assertSame('infobox_unmatched', WikipediaGrades::decide(self::article(self::INFOBOX), '999999', 60_000, false, false, self::measure(null))['status']);
    }

    public function test_shared_articles_and_rack_railways_are_never_read(): void
    {
        $this->assertSame('shared_article', WikipediaGrades::decide(self::article(self::INFOBOX), '405000', 131_000, false, true, self::measure(null))['status']);
        $this->assertSame('rack', WikipediaGrades::decide(self::article(self::INFOBOX), '405000', 131_000, true, false, self::measure(null))['status']);
        $this->assertNull(WikipediaGrades::decide(self::article(self::INFOBOX), '405000', 131_000, true, false, self::measure(null))['entry']);
    }

    public function test_the_text_gives_a_single_maximum_and_ignores_ranges_portions_and_other_maximums(): void
    {
        $text = "Intro.\n\nC'est une ligne au profil convenable, les déclivités ne dépassent pas 8 ‰. Les rampes maximales sont de {{unité|8|‰}}.";
        $this->assertSame([8.0, 8.0], array_column(WikipediaGrades::textGrades(self::INFOBOX.$text), 'value'));

        foreach ([
            'Le profil présente des pentes de 6 à 10 mm/m.',
            'Entre Albertville et Moûtiers, le tracé est plat avec une déclivité maximale de 10 mm/m.',
            'Les déclivités ne dépassaient pas 5 ‰ (accidentellement 7 ‰ sur un kilomètre).',
            'Les mécaniciens demandaient le maximum de puissance pour prendre la rampe à 30 mm/m.',
            'Une rampe de 12 ‰ mène à la gare.',
        ] as $sentence) {
            $this->assertSame([], WikipediaGrades::textGrades($sentence), $sentence);
        }
        // La pente de l'infobox n'est pas lue comme du texte.
        $this->assertSame([], WikipediaGrades::textGrades(self::INFOBOX));
    }

    public function test_a_long_sentence_is_quoted_around_its_value(): void
    {
        $long = 'Cette ligne quittait la gare de Quimperlé pour gagner Pont-Aven, '.str_repeat('via des bourgs et des vallons, ', 8).'et les rampes atteignaient souvent 25 ‰ dans la région.';
        $found = WikipediaGrades::textGrades($long);

        $this->assertCount(1, $found);
        $this->assertStringContainsString('25 ‰', $found[0]['quote']);
        $this->assertStringStartsWith('…', $found[0]['quote']);
        $this->assertLessThanOrEqual(WikipediaGrades::QUOTE_MAX, mb_strlen($found[0]['quote']));
    }

    public function test_a_text_value_is_kept_only_when_the_profile_agrees(): void
    {
        $article = self::article("{{Infobox Ligne ferroviaire\n | nomligne = X\n}}\nLes déclivités atteignent 16 ‰.");

        $kept = WikipediaGrades::decide($article, '1', 10_000, false, false, self::measure(17.5));
        $this->assertSame('text', $kept['status']);
        $this->assertSame('texte : « Les déclivités atteignent 16 ‰. »', $kept['entry']['quote'] ?? null);
        // Terrain sous un tracé ancien : il surestime, jusqu'à 4 fois la valeur est admis.
        $this->assertSame('text', WikipediaGrades::decide($article, '1', 10_000, false, false, self::measure(60.0, 'terrain'))['status']);
        $this->assertSame('text_implausible', WikipediaGrades::decide($article, '1', 10_000, false, false, self::measure(70.0, 'terrain'))['status']);
        $this->assertSame('text_implausible', WikipediaGrades::decide($article, '1', 10_000, false, false, self::measure(5.0, 'terrain'))['status']);
        // Sur la voie : 5 ‰ ou la moitié de la mesure d'écart au plus.
        $this->assertSame('text_implausible', WikipediaGrades::decide($article, '1', 10_000, false, false, self::measure(34.0))['status']);
        $this->assertSame('text', WikipediaGrades::decide($article, '1', 10_000, false, false, self::measure(11.0))['status']);
        $this->assertSame('text_unchecked', WikipediaGrades::decide($article, '1', 10_000, false, false, self::measure(null))['status']);

        $twice = self::article("Les déclivités atteignent 16 ‰.\nLes rampes maximales sont de 20 ‰.");
        $this->assertSame('text_ambiguous', WikipediaGrades::decide($twice, '1', 10_000, false, false, self::measure(17.0))['status']);
    }

    public function test_the_merge_adds_and_updates_but_never_removes_or_replaces_a_reviewed_entry(): void
    {
        $entry = fn (float $v, string $rev, string $quote = 'pente = 1'): array => ['max_grade_permil' => $v, 'provider' => 'wikipedia',
            'license' => 'CC BY-SA 4.0', 'source_url' => "https://fr.wikipedia.org/w/index.php?title=X&oldid={$rev}", 'source_title' => 'X', 'quote' => $quote];
        $existing = [
            'a' => $entry(10, '1'),
            'b' => $entry(12, '1', 'pente = 12'),
            'c' => $entry(40, '1', 'pente = 6 ; texte : « rampe pouvant atteindre les 40 ‰ »'),
            'd' => $entry(15, '1'),
        ];
        $read = ['a' => $entry(10, '1', 'autre citation'), 'b' => $entry(12, '2', 'pente = 12'), 'c' => $entry(6, '2'), 'e' => $entry(8, '3')];

        $merged = WikipediaGrades::merge($existing, $read);

        $this->assertSame(['a' => 'unchanged', 'b' => 'updated', 'c' => 'kept_reviewed', 'd' => 'kept_not_read', 'e' => 'added'], $merged['outcome']);
        $this->assertSame($existing['a'], $merged['entries']['a']);
        $this->assertStringEndsWith('oldid=2', $merged['entries']['b']['source_url']);
        $this->assertSame(40, (int) $merged['entries']['c']['max_grade_permil']);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], array_keys($merged['entries']));
    }

    public function test_the_rendered_file_reads_back_the_same_entries(): void
    {
        $entries = ['z-ligne' => WikipediaGrades::entry(self::article(''), 7.8, 'pente = 7,8'), 'a-ligne' => WikipediaGrades::entry(self::article(''), 25.0, "texte : « l'article »")];
        $path = tempnam(sys_get_temp_dir(), 'vff-grades');
        $this->assertIsString($path);
        try {
            file_put_contents($path, WikipediaGrades::render($entries));
            $read = require $path;
        } finally {
            unlink($path);
        }

        $this->assertSame(['a-ligne', 'z-ligne'], array_keys($read));
        $this->assertSame(25, $read['a-ligne']['max_grade_permil']);
        $this->assertSame(7.8, $read['z-ligne']['max_grade_permil']);
        $this->assertSame(WikipediaGrades::render($entries), WikipediaGrades::render(array_reverse($entries, true)));
    }
}
