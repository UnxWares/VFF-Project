<?php

namespace Tests\Feature\Materials;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineSegment;
use App\Models\Material;
use App\Services\Materials\LineMaterialExclusions;
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

    private function linker(?LineMaterialExclusions $exclusions = null): LineMaterialLinker
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

        return new LineMaterialLinker($wikidata, $wikipedia, exclusions: $exclusions);
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

    public function test_a_train_cited_as_the_replacement_of_another_is_not_a_direct_source(): void
    {
        // Épinal à Bussang : « Ce train a été remplacé par le TGV » parle du train Corail, pas du TGV.
        $epinal = Line::factory()->create(['slug' => 'rfn-60000', 'name' => "Ligne d'Épinal à Bussang", 'wikipedia_title' => "Ligne d'Épinal à Bussang"]);
        $corail = Material::factory()->create(['slug' => 'voiture-corail', 'name' => 'Voiture Corail', 'type' => 'voiture', 'wikidata_id' => 'Q2', 'wikipedia_fr' => 'Voiture Corail']);
        $tgv = Material::factory()->create(['slug' => 'tgv', 'name' => 'TGV', 'type' => 'rame_tgv', 'wikidata_id' => 'Q3', 'wikipedia_fr' => 'TGV']);
        $z = Material::factory()->create(['slug' => 'z-27500', 'name' => 'Z 27500', 'type' => 'automotrice', 'wikidata_id' => 'Q4', 'wikipedia_fr' => 'Z 27500']);

        $wikipedia = new class(app(WikimediaClient::class), new InMemorySourceCache, 0) extends WikipediaArticleSource
        {
            public function sections(string $cacheName, array $titles, string $pattern, bool $refresh = false): array
            {
                $wikitext = "== Exploitation ==\n=== Matériels engagés ===\nLes [[Z 27500]] assurent les TER.\nIl existait un train [[Voiture Corail|Corail]] jusqu'à Remiremont. Ce train a été remplacé par le [[TGV]] après la mise en service de la LGV Est.";

                return $cacheName === 'wikipedia_lines' && isset($titles[0])
                    ? ["Ligne d'Épinal à Bussang" => ['title' => "Ligne d'Épinal à Bussang", 'revid' => 7, 'sections' => WikipediaMaterialSections::sections($wikitext, $pattern)]] + array_fill_keys($titles, null)
                    : array_fill_keys($titles, null);
            }

            public function redirects(string $cacheName, array $titles, bool $refresh = false): array
            {
                return array_fill_keys($titles, []);
            }
        };
        $linker = new LineMaterialLinker(
            new class(new CommonsClient(0), new InMemorySourceCache, WikidataMaterialRules::default(), 0) extends WikidataMaterialSource
            {
                public function vehicleUsage(bool $refresh = false): array
                {
                    return [];
                }
            },
            $wikipedia,
        );

        $links = $linker->collect();
        $confidence = fn (Material $m): string => LineMaterialLinker::attributes($links["{$epinal->id}:{$m->id}"]['evidence'])['confidence'];

        $this->assertSame('high', $confidence($z), 'sujet de sa phrase, section « Matériels engagés »');
        $this->assertSame('high', $confidence($corail));
        $this->assertSame('medium', $confidence($tgv), 'cité comme remplaçant du train Corail : jamais « source directe »');
        $this->assertStringStartsWith("Il existait un train Corail jusqu'à Remiremont. Ce train a été remplacé par le TGV", $links["{$epinal->id}:{$tgv->id}"]['evidence'][0]['quote']);
        $this->assertTrue($links["{$epinal->id}:{$tgv->id}"]['evidence'][0]['refers_back']);
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

    public function test_a_series_of_another_gauge_is_rejected_unless_a_source_names_the_gauge(): void
    {
        // Lunéville à Saint-Dié rendue métrique pour le test : 95 % à 1 000 mm, un raccordement à voie normale.
        $era = Era::factory()->create(['year' => 2030]);
        LineSegment::factory()->create(['line_id' => $this->luneville->id, 'era_id' => $era->id, 'gauge_mm' => 1000, 'length_meters' => 19_000]);
        LineSegment::factory()->create(['line_id' => $this->luneville->id, 'era_id' => $era->id, 'gauge_mm' => 1435, 'length_meters' => 1_000]);
        $this->x73500->update(['gauge_mm' => 1435]);
        $this->bb15000->update(['gauge_mm' => 1000]);

        $linker = $this->linker();
        $links = $linker->collect();

        $this->assertArrayNotHasKey("{$this->luneville->id}:{$this->x73500->id}", $links, 'Wikidata seul ne nomme pas l\'écartement');
        $this->assertArrayHasKey("{$this->luneville->id}:{$this->bb15000->id}", $links, 'série au bon écartement');
        $this->assertSame(
            ["{$this->luneville->id}:{$this->x73500->id}" => 'série à 1 435 mm, ligne surtout à 1 000 mm (5 % de sa longueur à 1 435 mm)'],
            array_map(fn (array $l): string => $l['reason'], $linker->rejected()),
        );
    }

    public function test_an_electric_series_is_rejected_on_a_line_never_electrified_in_its_system(): void
    {
        // Lunéville à Saint-Dié rendue non électrifiée pour le test (état connu par la source SNCF).
        $era = Era::factory()->create(['year' => 2030]);
        LineSegment::factory()->create(['line_id' => $this->luneville->id, 'era_id' => $era->id, 'electrified' => false, 'voltage_v' => null, 'electrification_source' => 'sncf', 'length_meters' => 50_000]);
        $this->bb15000->update(['electrification' => [['label' => 'électrification ferroviaire 25 kV 50 Hz', 'wikidata_id' => 'Q106530439']], 'in_service_from' => 1971, 'in_service_to' => null]);

        $linker = $this->linker();
        $links = $linker->collect();

        $this->assertArrayNotHasKey("{$this->luneville->id}:{$this->bb15000->id}", $links);
        $this->assertArrayHasKey("{$this->luneville->id}:{$this->x73500->id}", $links, 'autorail : pas de jugement');
        $this->assertSame(
            ["{$this->luneville->id}:{$this->bb15000->id}" => 'série à 25 kV, ligne jamais électrifiée dans un système compatible'],
            array_map(fn (array $l): string => $l['reason'], $linker->rejected()),
        );
    }

    public function test_a_reviewed_exclusion_removes_its_sentence_or_the_whole_link(): void
    {
        $linker = $this->linker(LineMaterialExclusions::fromDatabase([
            ['line' => 'rfn-67000', 'material' => 'Q746572', 'quote' => 'Rames Corail tractées par des', 'source' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070', 'reason' => 'test'],
            ['line' => 'rfn-67000', 'material' => 'Q2784315', 'source' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070', 'reason' => 'couple entier'],
        ]));
        $links = $linker->collect();

        $bb15000 = $links["{$this->luneville->id}:{$this->bb15000->id}"] ?? null;
        $this->assertNotNull($bb15000, 'l\'article de la série prouve encore le lien');
        $this->assertSame(['wikipedia_material_article'], array_column($bb15000['evidence'], 'kind'));
        $this->assertArrayNotHasKey("{$this->luneville->id}:{$this->x73500->id}", $links);
        $this->assertSame('exclusion relue : couple entier', $linker->rejected()["{$this->luneville->id}:{$this->x73500->id}"]['reason']);
    }

    public function test_diff_lists_what_writing_would_change_without_writing(): void
    {
        $stale = LineMaterial::create(['line_id' => $this->lgvEst->id, 'material_id' => $this->x73500->id, 'source' => 'wikipedia', 'evidence' => []]);
        $links = $this->linker()->collect();

        $diff = LineMaterialLinker::diff($links);

        $this->assertEqualsCanonicalizing(array_keys($links), $diff['created']);
        $this->assertSame([], $diff['updated']);
        $this->assertSame(["{$this->lgvEst->id}:{$this->x73500->id}"], $diff['removed']);
        $this->assertSame(1, LineMaterial::count(), 'diff() n\'écrit rien');
        $this->assertModelExists($stale);
    }
}
