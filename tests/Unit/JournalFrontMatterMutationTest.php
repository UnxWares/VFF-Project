<?php

namespace Tests\Unit;

use App\Services\Journal\JournalFrontMatter;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use PHPUnit\Framework\TestCase;

/** Messages, numéros de ligne et formes limites de l'en-tête d'un article du journal. */
class JournalFrontMatterMutationTest extends TestCase
{
    private function message(string $yaml): string
    {
        try {
            (new JournalFrontMatter)->parse($yaml);
        } catch (InvalidFrontMatterException $e) {
            return $e->getMessage();
        }
        $this->fail('Un en-tête invalide devait être refusé.');
    }

    public function test_the_opening_fence_is_dropped_only_at_the_very_start(): void
    {
        $this->assertSame(['title' => 'A'], (new JournalFrontMatter)->parse("---\ntitle: A"));
        $this->assertSame(['title' => 'A'], (new JournalFrontMatter)->parse("---\r\ntitle: A"));
        $this->assertSame(['title' => 'A'], (new JournalFrontMatter)->parse('title: A'));
        // Une ligne --- plus loin n'est pas une clé : refusée.
        $this->assertSame('Ligne 3 illisible : « --- ».', $this->message("---\ntitle: A\n---"));
    }

    public function test_error_messages_carry_the_line_number_of_the_file(): void
    {
        $this->assertSame('Clé « title » répétée (ligne 3).', $this->message("---\ntitle: A\ntitle: B"));
        $this->assertSame('Ligne 2 illisible : « du texte ».', $this->message("---\ndu texte"));
        $this->assertSame('Liste et dictionnaire mêlés sous « lines » (ligne 4).', $this->message("---\nlines:\n  - 1\n  src: x"));
        $this->assertSame('Liste et dictionnaire mêlés sous « image » (ligne 4).', $this->message("---\nimage:\n  src: x\n  - 1"));
    }

    public function test_blank_lines_and_comments_still_count_in_line_numbers(): void
    {
        $this->assertSame('Ligne 5 illisible : « oups ».', $this->message("---\n\n# note\n   \noups"));
        $this->assertSame('Ligne 4 illisible : « oups ».', $this->message("---\n  # note indentée\ntitle: A\noups"));
    }

    public function test_trailing_spaces_of_a_line_are_ignored_and_the_message_shows_the_trimmed_line(): void
    {
        $this->assertSame(['title' => 'A'], (new JournalFrontMatter)->parse("title: A   \n"));
        $this->assertSame('Ligne 2 illisible : « oups ».', $this->message("---\noups   "));
    }

    public function test_a_key_follows_the_documented_alphabet(): void
    {
        $this->assertSame(['image_src' => 'x'], (new JournalFrontMatter)->parse('image_src: x'));
        $this->assertSame('Ligne 2 illisible : « a1: x ».', $this->message("---\na1: x"));
        $this->assertSame('Ligne 2 illisible : « a-b: x ».', $this->message("---\na-b: x"));
        $this->assertSame('Ligne 2 illisible : « title:x ».', $this->message("---\ntitle:x"));
        $this->assertSame('Ligne 2 illisible : « x title: x ».', $this->message("---\nx title: x"));
        $this->assertSame('Ligne 2 illisible : «  title: x ».', $this->message("---\n title: x"), 'une clé ne s\'indente pas hors d\'un bloc ouvert');
    }

    public function test_values_keep_their_inner_colons_spaces_and_quotes(): void
    {
        $data = (new JournalFrontMatter)->parse("a: x: y\nb:    espacé   \nc: \"guillemets \"intérieurs\" ok\"\nd: 'a'\"\ne: \"mal fermé'\nf: \"\"\ng: '\nh: x\"");

        $this->assertSame('x: y', $data['a']);
        $this->assertSame('espacé', $data['b']);
        $this->assertSame('guillemets "intérieurs" ok', $data['c']);
        $this->assertSame('\'a\'"', $data['d']);
        $this->assertSame('"mal fermé\'', $data['e']);
        $this->assertSame([], $data['f'], 'une valeur entre guillemets vides ouvre une liste vide');
        $this->assertSame("'", $data['g']);
        $this->assertSame('x"', $data['h']);
    }

    public function test_a_two_character_quoted_value_is_unwrapped(): void
    {
        $this->assertSame(['a' => [], 'b' => 'x'], (new JournalFrontMatter)->parse("a: ''\nb: \"x\""));
    }

    public function test_lists_keep_order_and_scalars_are_unquoted_inside(): void
    {
        $data = (new JournalFrontMatter)->parse("lines:\n  - 1\n    -   '2'\n  - \"3\"\nafter: z");
        $this->assertSame(['lines' => ['1', '2', '3'], 'after' => 'z'], $data);
    }

    public function test_maps_unquote_their_values_and_a_new_key_closes_the_block(): void
    {
        $data = (new JournalFrontMatter)->parse("image:\n  src: 'https://x.org/a.jpg'\n  author:   Nom  \nnext:\n  - 1");
        $this->assertSame(
            ['image' => ['src' => 'https://x.org/a.jpg', 'author' => 'Nom'], 'next' => ['1']],
            $data,
        );
    }

    public function test_an_indented_line_after_a_key_with_a_value_is_refused(): void
    {
        $this->assertSame('Ligne 3 illisible : «   - 1 ».', $this->message("---\ntitle: A\n  - 1"));
        $this->assertSame('Ligne 3 illisible : «   src: x ».', $this->message("---\ntitle: A\n  src: x"));
    }

    public function test_a_list_item_or_map_entry_needs_its_value(): void
    {
        $this->assertSame('Ligne 3 illisible : «   - ».', $this->message("---\nlines:\n  - "));
        $this->assertSame('Ligne 3 illisible : «   src: ».', $this->message("---\nimage:\n  src: "));
        $this->assertSame('Ligne 3 illisible : « - 1 ».', $this->message("---\nlines:\n- 1"), 'un élément doit être indenté');
        $this->assertSame('Ligne 3 illisible : «   Src: 1 ».', $this->message("---\nimage:\n  Src: 1"));
    }

    public function test_the_shape_resets_for_each_open_key(): void
    {
        $data = (new JournalFrontMatter)->parse("a:\n  - 1\nb:\n  x: 2\nc:\n  - 3");
        $this->assertSame(['a' => ['1'], 'b' => ['x' => '2'], 'c' => ['3']], $data);
    }

    public function test_an_empty_header_is_an_empty_array(): void
    {
        $this->assertSame([], (new JournalFrontMatter)->parse(''));
        $this->assertSame([], (new JournalFrontMatter)->parse("---\n"));
        $this->assertSame([], (new JournalFrontMatter)->parse("---\n# seulement un commentaire\n\n"));
    }

    public function test_free_text_with_a_colon_after_an_open_key_is_not_a_map_entry(): void
    {
        $this->assertSame('Ligne 3 illisible : « note à moi: oui ».', $this->message("---\nimage:\nnote à moi: oui"));
    }
}
