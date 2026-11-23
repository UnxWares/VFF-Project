<?php

namespace Tests\Unit\Lines;

use App\Services\Lines\LineSlugFileRewriter;
use App\Services\Lines\LineSlugRedirects;
use App\Services\Lines\NeutralLineSlugs;
use PHPUnit\Framework\TestCase;

/**
 * Slugs neutres des lignes (préfixe de source retiré), correspondances des anciens slugs et réécriture des fichiers.
 * Slugs d'essai inventés : aucun n'est dans database/data/line_slug_redirects.php.
 */
class NeutralLineSlugsTest extends TestCase
{
    public function test_the_neutral_slug_drops_the_prefix_and_keeps_existing_suffixes(): void
    {
        $this->assertSame('ligne-de-essai-a-test', NeutralLineSlugs::stripped('archeo-ligne-de-essai-a-test'));
        $this->assertSame('voie-de-essai-2', NeutralLineSlugs::stripped('archeo-voie-de-essai-2'));
        $this->assertSame('ligne', NeutralLineSlugs::stripped('archeo-'));
        $this->assertSame('ligne-de-essai', NeutralLineSlugs::stripped('ligne-de-essai'));
        $this->assertSame('ligne-dessai-a-test', NeutralLineSlugs::fromName('Ligne d’Essai à Test'));
        $this->assertSame('ligne', NeutralLineSlugs::fromName('…'));
    }

    public function test_free_takes_the_first_unused_suffix(): void
    {
        $taken = ['a' => true, 'a-2' => true];

        $this->assertSame('a-3', NeutralLineSlugs::free('a', fn (string $s): bool => isset($taken[$s])));
        $this->assertSame('b', NeutralLineSlugs::free('b', fn (string $s): bool => isset($taken[$s])));
    }

    public function test_the_plan_renames_without_collision_and_suffixes_on_collision(): void
    {
        $plan = NeutralLineSlugs::plan(
            [['id' => 1, 'slug' => 'archeo-ligne-de-essai'], ['id' => 2, 'slug' => 'archeo-ligne-de-test']],
            [],
            ['archeo-ligne-de-essai', 'archeo-ligne-de-test', 'ligne-de-test', 'rfn-420000'],
            [],
        );

        $this->assertSame([
            ['line_id' => 1, 'from' => 'archeo-ligne-de-essai', 'to' => 'ligne-de-essai'],
            ['line_id' => 2, 'from' => 'archeo-ligne-de-test', 'to' => 'ligne-de-test-2'],
        ], $plan['renames']);
        $this->assertSame([['from' => 'archeo-ligne-de-test', 'wanted' => 'ligne-de-test', 'to' => 'ligne-de-test-2']], $plan['collisions']);
    }

    public function test_the_plan_keeps_known_pairs_and_never_reuses_a_retired_or_given_slug(): void
    {
        $known = ['archeo-ligne-de-essai' => 'ligne-de-essai-9', 'archeo-ancienne' => 'ligne-de-test'];
        $plan = NeutralLineSlugs::plan(
            [['id' => 1, 'slug' => 'archeo-ligne-de-essai'], ['id' => 2, 'slug' => 'archeo-ligne-de-test']],
            [],
            ['archeo-ligne-de-essai', 'archeo-ligne-de-test'],
            $known,
        );

        // Couple déjà retenu : même slug (idempotent) ; « ligne-de-test » est déjà donné : suffixe.
        $this->assertSame('ligne-de-essai-9', $plan['renames'][0]['to']);
        $this->assertSame('ligne-de-test-2', $plan['renames'][1]['to']);
    }

    public function test_a_known_pair_whose_target_is_now_held_by_another_line_is_recomputed(): void
    {
        $plan = NeutralLineSlugs::plan(
            [['id' => 1, 'slug' => 'archeo-ligne-de-essai']],
            [],
            ['archeo-ligne-de-essai', 'ligne-de-essai'],
            ['archeo-ligne-de-essai' => 'ligne-de-essai'],
        );

        $this->assertSame('ligne-de-essai-2', $plan['renames'][0]['to']);
    }

    public function test_merged_slugs_lead_to_the_new_slug_of_the_line_that_received_them(): void
    {
        $plan = NeutralLineSlugs::plan(
            [['id' => 7, 'slug' => 'archeo-ligne-mere']],
            [
                ['from' => 'archeo-section-b', 'line_id' => 3, 'line_slug' => 'rfn-420000'],
                ['from' => 'archeo-section-a', 'line_id' => 7, 'line_slug' => 'archeo-ligne-mere'],
                ['from' => 'archeo-deja', 'line_id' => 3, 'line_slug' => 'rfn-420000'],
            ],
            ['archeo-ligne-mere', 'rfn-420000'],
            ['archeo-deja' => 'rfn-420000'],
        );

        $this->assertSame([
            ['line_id' => 7, 'from' => 'archeo-section-a', 'to' => 'ligne-mere'],
            ['line_id' => 3, 'from' => 'archeo-section-b', 'to' => 'rfn-420000'],
        ], $plan['merged']);
    }

    public function test_redirects_follow_successive_renames_and_stop_on_a_loop(): void
    {
        $redirects = new LineSlugRedirects(['a' => 'b', 'b' => 'c', 'x' => 'y', 'y' => 'x']);

        $this->assertSame('c', $redirects->target('a'));
        $this->assertSame('c', $redirects->target('b'));
        $this->assertNull($redirects->target('c'));
        $this->assertNull($redirects->target('x'));
        $this->assertTrue($redirects->isRetired('a'));
        $this->assertFalse($redirects->isRetired('c'));
        $this->assertSame([], LineSlugRedirects::read('/chemin/absent.php'));
    }

    public function test_the_versioned_file_only_maps_prefixed_slugs_to_neutral_ones(): void
    {
        foreach ((new LineSlugRedirects)->all() as $from => $to) {
            $this->assertStringStartsWith(NeutralLineSlugs::PREFIX, $from);
            $this->assertStringStartsNotWith(NeutralLineSlugs::PREFIX, $to, $from);
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $to);
        }
    }

    public function test_the_rewriter_only_replaces_whole_known_slugs(): void
    {
        $rewriter = new LineSlugFileRewriter(new LineSlugRedirects(['archeo-x' => 'x', 'archeo-x-2' => 'x-2']));

        $text = "'archeo-x' /lines/archeo-x#a 'archeo-x-2' 'archeo-xy' archeo-name:12 'archeo-%' 'archeo-x-3'";
        $this->assertSame(
            ['content' => "'x' /lines/x#a 'x-2' 'archeo-xy' archeo-name:12 'archeo-%' 'archeo-x-3'", 'count' => 3],
            $rewriter->rewrite($text),
        );
    }

    public function test_the_rewriter_walks_the_project_folders(): void
    {
        $base = sys_get_temp_dir().'/vff-slugs-'.bin2hex(random_bytes(4));
        mkdir($base.'/database/data', 0777, true);
        mkdir($base.'/vendor', 0777, true);
        file_put_contents($base.'/database/data/essai.php', "<?php return ['line' => 'archeo-x'];\n");
        file_put_contents($base.'/database/data/image.png', 'archeo-x');
        file_put_contents($base.'/vendor/autre.php', 'archeo-x');
        $rewriter = new LineSlugFileRewriter(new LineSlugRedirects(['archeo-x' => 'x']));

        $this->assertSame(['database/data/essai.php' => 1], $rewriter->rewriteTree($base, true));
        $this->assertStringContainsString('archeo-x', (string) file_get_contents($base.'/database/data/essai.php'));
        $this->assertSame(['database/data/essai.php' => 1], $rewriter->rewriteTree($base, false));
        $this->assertSame("<?php return ['line' => 'x'];\n", file_get_contents($base.'/database/data/essai.php'));
        $this->assertSame([], $rewriter->rewriteTree($base, false));
        $this->assertSame('archeo-x', file_get_contents($base.'/vendor/autre.php'));

        foreach (['database/data/essai.php', 'database/data/image.png', 'vendor/autre.php'] as $file) {
            unlink($base.'/'.$file);
        }
        foreach (['database/data', 'database', 'vendor', ''] as $dir) {
            rmdir(rtrim($base.'/'.$dir, '/'));
        }
    }
}
