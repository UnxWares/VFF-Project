<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:crawl-archeo : plan d'accueil d'archeoferroviaire (lignes disparues),
 * articles des rubriques régionales métropolitaines seulement, rapprochement
 * prudent des noms « Ville-A Ville-B » avec les lignes VFF. Seuls le nom de
 * l'article, sa rubrique et son URL sont gardés (accord : tracés seulement,
 * aucun texte des fiches).
 */
class ArcheoCrawlCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const HOME = 'archeoferroviaire.free.fr/v31/spip.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
    }

    private function homePage(): string
    {
        return <<<'HTML'
            <html><body><ul>
            <li>FRANCE CARTES GENERALES<ul><li><a href="spip.php?article1">Carte générale du Nord</a></li></ul></li>
            <li>FRANCE NORD<ul>
              <li><a href="spip.php?article10">Mézidon Dives</a></li>
              <li><a href="spip.php?article11" class="x"><b>Carhaix&nbsp;Morlaix</b></a></li>
              <li><a href="spip.php?article12">Plan n°2</a></li>
              <li><a href="spip.php?article10">Mézidon Dives (doublon)</a></li>
            </ul></li>
            <li>FRANCE SUD-OUEST<ul><li><a href="spip.php?article20">Saint-Girons Aulus</a></li><li><a href="spip.php?article21">Brive Inconnue</a></li></ul></li>
            <li>FRANCE D'OUTRE-MER<ul><li><a href="spip.php?article30">Saint-Denis Saint-Pierre</a></li></ul></li>
            </ul></body></html>
            HTML;
    }

    public function test_regional_articles_are_stored_and_matched_to_lines(): void
    {
        $mezidon = $this->rfnLine('396000', 'Ligne de Mézidon à Dives-sur-Mer');
        $carhaix = $this->rfnLine('482000', 'Ligne de Carhaix à Morlaix');
        $this->rfnLine('580000', 'Ligne de Brive à Tulle');
        $this->rfnLine('590000', 'Ligne de Brive à Aurillac');
        Http::fake([self::HOME => Http::response($this->homePage())]);

        $this->artisan('vff:crawl-archeo')
            ->expectsOutputToContain('4 article(s) régionaux retenus')
            ->expectsOutputToContain('OK - 4 article(s) en base, 2 matché(s) à une ligne VFF.')
            ->assertSuccessful();

        $rows = DB::table('archeo_lines')->orderBy('article_id')->get(['article_id', 'name', 'rubrique', 'url', 'matched_line_id']);
        $this->assertEquals([
            [10, 'Mézidon Dives', 'FRANCE NORD', 'http://archeoferroviaire.free.fr/v31/spip.php?article10', $mezidon->id],
            [11, 'Carhaix Morlaix', 'FRANCE NORD', 'http://archeoferroviaire.free.fr/v31/spip.php?article11', $carhaix->id],
            [20, 'Saint-Girons Aulus', 'FRANCE SUD-OUEST', 'http://archeoferroviaire.free.fr/v31/spip.php?article20', null],
            [21, 'Brive Inconnue', 'FRANCE SUD-OUEST', 'http://archeoferroviaire.free.fr/v31/spip.php?article21', null],
        ], $rows->map(fn ($r) => [$r->article_id, $r->name, $r->rubrique, $r->url, $r->matched_line_id])->all(),
            'cartes générales, « n° », outre-mer et doublons écartés ; « Brive » seul est ambigu');

        Http::assertSent(fn (Request $r) => str_contains($r->header('User-Agent')[0] ?? '', 'Mozilla')
            && $r->header('Accept-Language')[0] === 'fr-FR,fr;q=0.9');
    }

    public function test_crawl_is_idempotent_and_keeps_the_creation_date(): void
    {
        Http::fake([self::HOME => Http::response($this->homePage())]);
        $this->artisan('vff:crawl-archeo')->assertSuccessful();
        DB::table('archeo_lines')->update(['created_at' => '2026-01-01 00:00:00']);

        $this->artisan('vff:crawl-archeo')->assertSuccessful();

        $this->assertSame(4, DB::table('archeo_lines')->count());
        $this->assertSame(['2026-01-01 00:00:00'], DB::table('archeo_lines')->distinct()->pluck('created_at')->all());
    }

    public function test_limit_option(): void
    {
        Http::fake([self::HOME => Http::response($this->homePage())]);

        $this->artisan('vff:crawl-archeo', ['--limit' => 1])->expectsOutputToContain('limité à 1 article(s) (--limit)')->assertSuccessful();

        $this->assertSame([10], DB::table('archeo_lines')->pluck('article_id')->all());
    }

    public function test_server_refusals_are_retried_then_reported(): void
    {
        Http::fake([self::HOME => Http::sequence()->push('', 503)->push('', 503)->push($this->homePage())]);
        $this->artisan('vff:crawl-archeo')->assertSuccessful();
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(6)->seconds()]);
    }

    public function test_unreachable_home_page_fails_without_writing(): void
    {
        Http::fake([self::HOME => Http::response('', 404)]);

        $this->artisan('vff:crawl-archeo')->expectsOutputToContain('Plan d\'accueil inaccessible (HTTP).')->assertFailed();
        $this->assertSame(0, DB::table('archeo_lines')->count());
    }
}
