<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\Materials\LineMaterialLinker;
use App\Services\Materials\WikidataMaterialRules;
use App\Services\Materials\WikidataMaterialSource;
use App\Services\Materials\WikipediaArticleSource;
use App\Services\Materials\WikipediaMaterialSections;
use App\Services\Media\CommonsClient;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InMemorySourceCache;
use Tests\TestCase;

/**
 * Liens matériel ↔ ligne : seulement quand une source le dit (Wikidata P3438, liens posés dans
 * les sections utiles des articles Wikipédia), preuves réunies par couple, fiabilité, écriture
 * idempotente qui ne touche jamais un lien saisi dans VFF.
 */
class LineMaterialLinkerTest extends TestCase
{
    use RefreshDatabase;

    private Line $luneville;

    private Line $lgvEst;

    private Material $bb15000;

    private Material $x73500;

    private Material $tgv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->luneville = Line::factory()->create(['slug' => 'rfn-67000', 'name' => 'Ligne de Lunéville à St-Dié', 'wikipedia_title' => 'Ligne de Lunéville à Saint-Dié']);
        $this->lgvEst = Line::factory()->create(['slug' => 'rfn-5000', 'name' => 'LGV Est européenne', 'wikipedia_title' => 'LGV Est européenne', 'is_lgv' => true]);
        $this->bb15000 = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'wikidata_id' => 'Q746572', 'wikipedia_fr' => 'BB 15000', 'max_speed_kmh' => 160]);
        $this->x73500 = Material::factory()->create(['slug' => 'x-73500', 'name' => 'X 73500', 'type' => 'autorail', 'wikidata_id' => 'Q2784315', 'wikipedia_fr' => 'X 73500', 'max_speed_kmh' => 140]);
        $this->tgv = Material::factory()->create(['slug' => 'tgv-pos', 'name' => 'TGV POS', 'type' => 'rame_tgv', 'wikidata_id' => 'Q1', 'wikipedia_fr' => 'TGV POS', 'max_speed_kmh' => 320]);
        DB::table('wikidata_lines')->insert(['qid' => 'Q3240003', 'category' => 'line', 'line_id' => $this->luneville->id, 'fetched_at' => now()]);
    }

    private function linker(): LineMaterialLinker
    {
        $cache = new InMemorySourceCache;
        $wikidata = new class(new CommonsClient(0), $cache, WikidataMaterialRules::default(), 0) extends WikidataMaterialSource
        {
            public function vehicleUsage(bool $refresh = false): array
            {
                return [['subject' => 'Q3240003', 'material' => 'Q2784315', 'from' => '2001-01-01', 'to' => null]];
            }
        };
        $wikipedia = new class(app(WikimediaClient::class), $cache, 0) extends WikipediaArticleSource
        {
            public function sections(string $cacheName, array $titles, string $pattern, bool $refresh = false): array
            {
                $pages = [
                    'wikipedia_lines' => [
                        'Ligne de Lunéville à Saint-Dié' => ['title' => 'Ligne de Lunéville à Saint-Dié', 'revid' => 239088070, 'wikitext' => "== Trafic ==\n=== Matériel roulant ===\nRames Corail tractées par des [[BB15000|BB 15000]].\n* [[X 73500]]\n=== Projets ===\n[[TGV POS]]"],
                        'LGV Est européenne' => ['title' => 'LGV Est européenne', 'revid' => 1, 'wikitext' => "== Exploitation ==\nLes [[TGV POS]] circulent sur la ligne."],
                    ],
                    'wikipedia_materials' => [
                        'BB 15000' => ['title' => 'BB 15000', 'revid' => 2, 'wikitext' => "== Services effectués ==\nLa mise en service de la [[LGV Est européenne]] libère les BB 15000.\nElles circulent sur la [[ligne de Lunéville à Saint-Dié]]."],
                    ],
                ][$cacheName];
                $out = [];
                foreach ($titles as $title) {
                    $page = $pages[$title] ?? null;
                    $out[$title] = $page === null ? null : ['title' => $page['title'], 'revid' => $page['revid'], 'sections' => WikipediaMaterialSections::sections($page['wikitext'], $pattern)];
                }

                return $out;
            }

            public function redirects(string $cacheName, array $titles, bool $refresh = false): array
            {
                return ($cacheName === 'redirects_materials' ? ['BB 15000' => ['BB15000']] : []) + array_fill_keys($titles, []);
            }
        };

        return new LineMaterialLinker($wikidata, $wikipedia);
    }

    public function test_links_come_only_from_sources_with_their_evidence(): void
    {
        $links = $this->linker()->collect();

        $this->assertEqualsCanonicalizing([
            "{$this->luneville->id}:{$this->bb15000->id}",
            "{$this->luneville->id}:{$this->x73500->id}",
            "{$this->lgvEst->id}:{$this->tgv->id}",
        ], array_keys($links), 'ni le projet (TGV POS à Lunéville), ni la BB 15000 citée par le contexte de la LGV');

        $bb = $links["{$this->luneville->id}:{$this->bb15000->id}"]['evidence'];
        $this->assertSame(['wikipedia_line_article', 'wikipedia_material_article'], array_column($bb, 'kind'), 'redirection BB15000 suivie, les deux articles réunis');
        $this->assertSame('Trafic > Matériel roulant', $bb[0]['section']);
        $this->assertSame('Rames Corail tractées par des BB 15000.', $bb[0]['quote']);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Ligne_de_Lun%C3%A9ville_%C3%A0_Saint-Di%C3%A9&oldid=239088070', $bb[0]['url']);

        $x = $links["{$this->luneville->id}:{$this->x73500->id}"]['evidence'];
        $this->assertContains(['kind' => 'wikidata', 'property' => 'P3438', 'subject' => 'Q3240003', 'from_year' => 2001, 'to_year' => null,
            'url' => 'https://www.wikidata.org/wiki/Q3240003#P3438'], $x);
    }

    public function test_confidence_reflects_the_strongest_evidence(): void
    {
        $links = $this->linker()->collect();

        $x = LineMaterialLinker::attributes($links["{$this->luneville->id}:{$this->x73500->id}"]['evidence']);
        $this->assertSame(['wikidata', 'high'], [$x['source'], $x['confidence']]);
        $this->assertSame('wikidata', $x['evidence'][0]['kind'], 'preuve la plus forte d\'abord');

        $tgv = LineMaterialLinker::attributes($links["{$this->lgvEst->id}:{$this->tgv->id}"]['evidence']);
        $this->assertSame(['wikipedia', 'medium'], [$tgv['source'], $tgv['confidence']], 'section Exploitation');

        $shared = LineMaterialLinker::attributes([['kind' => 'wikipedia_line_article', 'section' => 'Matériel roulant', 'shared_article' => true, 'url' => 'u']]);
        $this->assertSame('low', $shared['confidence'], 'article qui décrit plusieurs lignes');
    }

    public function test_writing_is_idempotent_and_never_touches_manual_links(): void
    {
        $manual = LineMaterial::create(['line_id' => $this->lgvEst->id, 'material_id' => $this->bb15000->id, 'notes' => 'Essais 1974']);
        $stale = LineMaterial::create(['line_id' => $this->lgvEst->id, 'material_id' => $this->x73500->id, 'source' => 'wikipedia', 'evidence' => []]);
        $linker = $this->linker();
        $links = $linker->collect();

        $this->assertSame(['created' => 3, 'updated' => 0, 'removed' => 1], $linker->write($links));
        $this->assertSame(['created' => 0, 'updated' => 0, 'removed' => 0], $linker->write($links));

        $this->assertModelExists($manual);
        $this->assertModelMissing($stale);
        $this->assertSame(4, LineMaterial::count());
        $this->assertSame([$this->luneville->slug], $this->x73500->lines()->pluck('slug')->all());
        $this->assertSame('high', $this->luneville->materialLinks()->where('material_id', $this->bb15000->id)->value('confidence'));
    }
}
