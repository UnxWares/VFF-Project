<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Models\Station;
use App\Services\Journal\JournalArticle;
use App\Services\Journal\JournalRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Rendu des articles du journal : titres, liens vers l'encyclopédie, liens externes, notes, durée de lecture. */
class JournalRendererMutationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $stations
     * @return array{html: string, toc: list<array{id: string, label: string}>, lines: list<array{code: string, slug: string, name: string}>, stations: list<array{slug: string, name: string, city: ?string}>, words: int, reading_minutes: int}
     */
    private function render(string $body, array $lines = [], array $stations = [], string $tab = 'nouvel onglet', string $back = 'retour'): array
    {
        $article = new JournalArticle('essai', 'fr', ['fr'], 'Essai', '2026-01-01', null, 'Résumé', $lines, $stations, null, null, $body);

        return (new JournalRenderer)->render($article, $tab, $back);
    }

    public function test_a_level_one_heading_is_refused(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Titre de niveau 1 dans le texte');
        $this->render("# Titre\n\nTexte");
    }

    public function test_heading_ids_come_from_the_text_and_never_collide(): void
    {
        $r = $this->render("## Été à Morez\n\n## Été à Morez\n\n## Été à Morez\n\n### Sous-partie\n\n## Contenu\n\n## ???\n\n## !!!\n\n## Notebook\n\n## Appel-2 final\n");

        $this->assertSame(
            [
                ['id' => 'ete-a-morez', 'label' => 'Été à Morez'],
                ['id' => 'ete-a-morez-2', 'label' => 'Été à Morez'],
                ['id' => 'ete-a-morez-3', 'label' => 'Été à Morez'],
                ['id' => 'contenu-2', 'label' => 'Contenu'],
                ['id' => 'section', 'label' => '???'],
                ['id' => 'section-2', 'label' => '!!!'],
                ['id' => 'notebook', 'label' => 'Notebook'],
                ['id' => 'section-appel-2-final', 'label' => 'Appel-2 final'],
            ],
            $r['toc'],
        );
        $this->assertStringContainsString('<h2 id="ete-a-morez">Été à Morez</h2>', $r['html']);
        $this->assertStringContainsString('<h3 id="sous-partie">Sous-partie</h3>', $r['html']);
        $this->assertStringContainsString('<h2 id="ete-a-morez-3">', $r['html']);
    }

    public function test_reserved_identifiers_are_taken_by_the_site_template(): void
    {
        $r = $this->render("## Main\n\n## App\n\n## Contenu\n");

        $this->assertSame(['main-2', 'app-2', 'contenu-2'], array_column($r['toc'], 'id'));
    }

    public function test_links_to_the_encyclopedia_become_sheet_links_with_their_anchor(): void
    {
        Line::factory()->create(['code' => '878000', 'slug' => 'rfn-878000', 'name' => 'Andelot - Saint-Claude']);
        Station::factory()->create(['slug' => 'morez', 'name' => 'Morez', 'city' => 'Morez']);

        $r = $this->render('Voir [la ligne](ligne:878000#chronique), [la gare](gare:morez) et [sans ancre](ligne:878000).');

        $this->assertStringContainsString('<a href="/lines/rfn-878000#chronique">la ligne</a>', $r['html']);
        $this->assertStringContainsString('<a href="/stations/morez">la gare</a>', $r['html']);
        $this->assertStringContainsString('<a href="/lines/rfn-878000">sans ancre</a>', $r['html']);
        $this->assertSame([['code' => '878000', 'slug' => 'rfn-878000', 'name' => 'Andelot - Saint-Claude']], $r['lines']);
        $this->assertSame([['slug' => 'morez', 'name' => 'Morez', 'city' => 'Morez']], $r['stations']);
    }

    public function test_a_link_to_a_missing_sheet_keeps_its_text_without_a_link(): void
    {
        $r = $this->render('Voir [une **ligne** absente](ligne:999999) et [une gare](gare:inconnue#x).');

        $this->assertStringNotContainsString('<a', $r['html']);
        $this->assertStringNotContainsString('ligne:', $r['html']);
        $this->assertStringContainsString('<p>Voir une <strong>ligne</strong> absente et une gare.</p>', $r['html']);
        $this->assertSame([], $r['lines']);
        $this->assertSame([], $r['stations']);
    }

    public function test_only_well_formed_targets_are_internal_links(): void
    {
        $long = str_repeat('a', 161);
        $r = $this->render("[a](ligne:ABC) [b](gare:{$long}) [c](ligne:878000#Mauvaise) [d](ligne:) [e](gare:ok#)");

        // Aucun n'est une cible valide : le lien reste tel quel, ni retiré ni réécrit, ni ouvert dans un onglet.
        $this->assertStringContainsString('<a href="ligne:ABC">a</a>', $r['html']);
        $this->assertStringContainsString("<a href=\"gare:{$long}\">b</a>", $r['html']);
        $this->assertStringContainsString('<a href="ligne:878000#Mauvaise">c</a>', $r['html']);
        $this->assertStringNotContainsString('_blank', $r['html']);
    }

    public function test_target_length_limits_are_inclusive(): void
    {
        $slug160 = str_repeat('a', 160);
        $anchor80 = str_repeat('b', 80);
        Station::factory()->create(['slug' => $slug160, 'name' => 'Longue']);

        $ok = $this->render("[x](gare:{$slug160}#{$anchor80})");
        $this->assertStringContainsString("<a href=\"/stations/{$slug160}#{$anchor80}\">x</a>", $ok['html']);

        $anchor81 = str_repeat('b', 81);
        $bad = $this->render("[x](gare:{$slug160}#{$anchor81})");
        $this->assertStringContainsString("<a href=\"gare:{$slug160}#{$anchor81}\">x</a>", $bad['html']);

        $slug161 = str_repeat('a', 161);
        $this->assertStringContainsString("href=\"gare:{$slug161}\"", $this->render("[x](gare:{$slug161})")['html']);
    }

    public function test_the_header_lists_come_first_in_their_order_without_duplicates(): void
    {
        foreach (['100000' => 'Une', '200000' => 'Deux', '300000' => 'Trois'] as $code => $name) {
            Line::factory()->create(['code' => $code, 'slug' => "rfn-$code", 'name' => $name]);
        }
        foreach (['gare-a' => 'A', 'gare-b' => 'B'] as $slug => $name) {
            Station::factory()->create(['slug' => $slug, 'name' => $name, 'city' => null]);
        }

        $r = $this->render('[x](ligne:300000) [y](ligne:100000) [z](gare:gare-b) [w](gare:gare-a)', ['200000', '100000'], ['gare-a']);

        $this->assertSame(['200000', '100000', '300000'], array_column($r['lines'], 'code'));
        $this->assertSame(['gare-a', 'gare-b'], array_column($r['stations'], 'slug'));
        $this->assertNull($r['stations'][0]['city']);
        // Les lignes de l'en-tête sont rendues même sans lien dans le texte, les absentes sont écartées.
        $this->assertSame(['200000'], array_column($this->render('Texte seul', ['200000', '999999'])['lines'], 'code'));
    }

    public function test_an_old_station_slug_leads_to_the_current_sheet_once(): void
    {
        $station = Station::factory()->create(['slug' => 'nouveau-slug', 'name' => 'Gare', 'city' => 'Ville']);
        DB::table('station_redirects')->insert(['old_slug' => 'ancien-slug', 'station_id' => $station->id]);

        $r = $this->render('[a](gare:ancien-slug) et [b](gare:nouveau-slug)', [], ['ancien-slug']);

        $this->assertStringContainsString('<a href="/stations/nouveau-slug">a</a>', $r['html']);
        $this->assertStringContainsString('<a href="/stations/nouveau-slug">b</a>', $r['html']);
        $this->assertCount(1, $r['stations']);
        $this->assertSame('nouveau-slug', $r['stations'][0]['slug']);
    }

    public function test_lines_prefer_the_national_network_sheet_for_a_shared_code(): void
    {
        $renderer = new JournalRenderer;
        Line::factory()->create(['code' => '123456', 'slug' => 'ancienne-ligne', 'name' => 'Ancienne']);
        Line::factory()->create(['code' => '123456', 'slug' => 'rfn-123456', 'name' => 'Actuelle']);
        Line::factory()->create(['code' => '123456', 'slug' => 'autre', 'name' => 'Autre']);
        Line::factory()->create(['code' => '222222', 'slug' => 'premiere', 'name' => 'Premiere']);
        Line::factory()->create(['code' => '222222', 'slug' => 'seconde', 'name' => 'Seconde']);

        $found = $renderer->lines(['123456', '222222', '000000']);

        $this->assertSame(['code' => '123456', 'slug' => 'rfn-123456', 'name' => 'Actuelle'], array_values(array_filter($found, fn (array $f): bool => $f['code'] === '123456'))[0]);
        $this->assertSame('premiere', array_values(array_filter($found, fn (array $f): bool => $f['code'] === '222222'))[0]['slug'], 'sans fiche rfn-, la plus ancienne');
        $this->assertCount(2, $found);
        $this->assertArrayNotHasKey('000000', $found);
        $this->assertSame([], $renderer->lines([]));
    }

    public function test_external_links_open_in_a_new_tab_with_the_spoken_label(): void
    {
        $r = $this->render('[SNCF](https://www.sncf.com/x) [Test](HTTP://exemple.org) [mail](mailto:a@b.c) [rel](/lines/x) [ftp](ftp://h.org)', [], [], 'nouvel onglet & co');

        $this->assertStringContainsString('<a target="_blank" rel="noopener noreferrer" href="https://www.sncf.com/x">SNCF<i class="bi bi-box-arrow-up-right vff-ext" aria-hidden="true"></i><span class="vff-sr-only"> (nouvel onglet &amp; co)</span></a>', $r['html']);
        $this->assertStringContainsString('<a target="_blank" rel="noopener noreferrer" href="HTTP://exemple.org">Test<i', $r['html']);
        $this->assertSame(2, substr_count($r['html'], '_blank'));
        $this->assertSame(2, substr_count($r['html'], 'vff-ext'));
        $this->assertStringContainsString('<a href="/lines/x">rel</a>', $r['html']);
    }

    public function test_raw_html_is_escaped_and_unsafe_links_are_dropped(): void
    {
        $r = $this->render("<script>alert(1)</script>\n\n[x](javascript:alert(1))");

        $this->assertStringContainsString('&lt;script&gt;', $r['html']);
        $this->assertStringNotContainsString('<script', $r['html']);
        $this->assertStringNotContainsString('javascript:', $r['html']);
    }

    public function test_footnotes_have_their_prefixes_and_an_accessible_back_link(): void
    {
        $r = $this->render("Texte[^1].\n\n[^1]: Source.\n", [], [], 'onglet', 'retour à l\'appel "1"');

        $this->assertStringContainsString('id="appel-1"', $r['html']);
        $this->assertStringContainsString('id="note-1"', $r['html']);
        $this->assertStringContainsString('role="doc-backlink" aria-label="retour à l&#039;appel &quot;1&quot;"', $r['html']);
        $this->assertStringContainsString('↑', $r['html']);
        $this->assertStringContainsString('role="doc-endnotes"', $r['html']);
        $this->assertStringContainsString('role="doc-noteref"', $r['html']);
        $this->assertStringNotContainsString('doc-endnote"', $r['html']);
        $this->assertStringNotContainsString('<hr', $r['html']);
        $this->assertStringStartsWith('<p>', $r['html']);
        $this->assertStringEndsNotWith("\n", $r['html']);
    }

    public function test_tables_are_rendered(): void
    {
        $r = $this->render("| a | b |\n|---|---|\n| 1 | 2 |\n");

        $this->assertStringContainsString('<table>', $r['html']);
    }

    public function test_the_reading_time_follows_the_word_count(): void
    {
        $words = fn (int $n): string => trim(str_repeat('mot ', $n));

        $empty = $this->render('');
        $this->assertSame([0, 1], [$empty['words'], $empty['reading_minutes']]);
        $one = $this->render($words(1));
        $this->assertSame([1, 1], [$one['words'], $one['reading_minutes']]);
        $a = $this->render($words(220));
        $this->assertSame([220, 1], [$a['words'], $a['reading_minutes']]);
        $b = $this->render($words(221));
        $this->assertSame([221, 2], [$b['words'], $b['reading_minutes']]);
        $c = $this->render($words(441));
        $this->assertSame([441, 3], [$c['words'], $c['reading_minutes']]);
        $this->assertSame(220, JournalRenderer::WORDS_PER_MINUTE);
    }

    public function test_words_count_accents_digits_apostrophes_and_hyphens_as_one_word(): void
    {
        $this->assertSame(5, $this->render("L'été d'Œuvre 1865 Haut-Jura œil.")['words']);
        $this->assertSame(4, $this->render('naïve ÀÂÄÇ ß 12')['words']);
        $this->assertSame(3, $this->render('**gras** _italique_ fin')['words']);
    }

    public function test_a_heading_containing_a_note_word_in_the_middle_keeps_its_own_id(): void
    {
        $r = $this->render("## Ma note-1 perso\n\n## Mon appel-2 final\n");

        $this->assertSame(['ma-note-1-perso', 'mon-appel-2-final'], array_column($r['toc'], 'id'));
    }

    public function test_an_external_link_does_not_stop_the_search_for_internal_targets(): void
    {
        Line::factory()->create(['code' => '100000', 'slug' => 'rfn-100000', 'name' => 'Une']);

        $r = $this->render('[dehors](https://x.org/a) puis [dedans](ligne:100000)');

        $this->assertSame(['100000'], array_column($r['lines'], 'code'));
    }

    public function test_a_target_must_be_the_whole_address_and_an_http_scheme_must_start_it(): void
    {
        Line::factory()->create(['code' => '878000', 'slug' => 'rfn-878000', 'name' => 'Une']);

        $r = $this->render('[a](https://x.org/ligne:878000) [b](/redir?u=https://x.org/p)');

        $this->assertSame([], $r['lines']);
        $this->assertStringContainsString('<a target="_blank" rel="noopener noreferrer" href="https://x.org/ligne:878000">a<i', $r['html']);
        $this->assertStringContainsString('<a href="/redir?u=https://x.org/p">b</a>', $r['html']);
        $this->assertSame(1, substr_count($r['html'], '_blank'));
    }

    public function test_a_current_slug_wins_over_an_old_slug_with_the_same_text(): void
    {
        $current = Station::factory()->create(['slug' => 'partage', 'name' => 'Actuelle']);
        $other = Station::factory()->create(['slug' => 'autre-gare', 'name' => 'Autre']);
        DB::table('station_redirects')->insert(['old_slug' => 'partage', 'station_id' => $other->id]);

        $r = $this->render('[x](gare:partage)');

        $this->assertStringContainsString('<a href="/stations/partage">x</a>', $r['html']);
        $this->assertSame([['slug' => 'partage', 'name' => 'Actuelle', 'city' => $current->city]], $r['stations']);
    }

    public function test_blocks_nested_deeper_than_the_limit_are_left_as_text(): void
    {
        $r = $this->render(str_repeat('> ', 40).'fond');

        $this->assertSame(20, substr_count($r['html'], '<blockquote>'));
    }
}
