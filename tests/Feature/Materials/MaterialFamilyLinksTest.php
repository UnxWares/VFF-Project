<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\Materials\LineMaterialLinker;
use App\Services\Materials\LineMaterialSupplements;
use App\Services\Materials\MaterialPeriodCollector;
use App\Services\Materials\MaterialPeriodExtractor;
use App\Services\Materials\SourceCache;
use App\Services\Materials\WikidataMaterialRules;
use App\Services\Materials\WikidataMaterialSource;
use App\Services\Materials\WikipediaArticleSource;
use App\Services\Materials\WikipediaMaterialSections;
use App\Services\Media\CommonsClient;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InMemorySourceCache;
use Tests\TestCase;

/**
 * Regio 2N et Omneo Premium (23/11/2026) : l'article de la ligne Mantes - Cherbourg écrit
 * « ces rames [[Regio 2N|Omneo Premium]] (Z 56600) », lien vers l'article commun de la famille. La ligne était
 * reliée au Regio 2N ; elle l'est désormais aux Z 56600, la version nommée. Phrases relues à la main
 * (database/data/line_material_supplements.php) : preuves, périodes, période contredite retirée, écriture limitée
 * à quelques séries.
 */
class MaterialFamilyLinksTest extends TestCase
{
    use RefreshDatabase;

    private const CHERBOURG = 'Ligne de Mantes-la-Jolie à Cherbourg';

    private const DREUX = 'Ligne de Saint-Cyr à Surdon';

    private InMemorySourceCache $cache;

    private Line $cherbourg;

    private Line $dreux;

    private Material $regio2n;

    private Material $omneo;

    private Material $bb26000;

    private Material $corail;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->cherbourg = Line::factory()->create(['slug' => 'rfn-366000', 'code' => '366000', 'name' => 'Ligne de Mantes-la-Jolie à Cherbourg', 'wikipedia_title' => self::CHERBOURG]);
        $this->dreux = Line::factory()->create(['slug' => 'rfn-395000', 'code' => '395000', 'name' => 'Ligne de St-Cyr à Surdon', 'wikipedia_title' => self::DREUX]);
        $this->regio2n = Material::factory()->create(['slug' => 'regio-2n', 'name' => 'Regio 2N', 'type' => 'automotrice', 'wikidata_id' => 'Q572856', 'wikipedia_fr' => 'Regio 2N',
            'designations' => ['Bombardier Omneo', 'Régio 2N', 'Z 57000'], 'in_service_from' => 2014, 'in_service_to' => null]);
        $this->omneo = Material::factory()->create(['slug' => 'z-56600', 'name' => 'Z 56600', 'type' => 'automotrice', 'wikidata_id' => 'Q120253257', 'wikipedia_fr' => 'Z 56600',
            'designations' => ['Regio 2N Z 56600', 'Omneo Premium', 'Bombardier Regio 2N Omneo Premium'], 'in_service_from' => 2020, 'in_service_to' => null]);
        $this->bb26000 = Material::factory()->create(['slug' => 'bb-26000', 'name' => 'BB 26000', 'type' => 'locomotive_diesel', 'wikidata_id' => 'Q604865', 'wikipedia_fr' => 'BB 26000', 'in_service_from' => 1988, 'in_service_to' => null]);
        $this->corail = Material::factory()->create(['slug' => 'voiture-corail', 'name' => 'Voiture Corail', 'type' => 'voiture', 'wikidata_id' => 'Q1131858', 'wikipedia_fr' => 'Voiture Corail', 'in_service_from' => 1975, 'in_service_to' => null]);

        $this->cache = new InMemorySourceCache;
        $this->cache->tables['wikipedia_lines'] = [
            self::CHERBOURG => ['title' => self::CHERBOURG, 'revid' => 241344631, 'sections' => WikipediaMaterialSections::sections(
                "== Desserte ==\n=== Liaisons Krono+ (grandes lignes) ===\n"
                ."Depuis 1996, ces deux liaisons étaient exclusivement assurées par des rames [[Voiture Corail|Corail]] composées de dix voitures.\n\n"
                ."La traction des rames était assurée par des [[BB 26000]].\n\n"
                ."Commandées à 40 exemplaires, ces rames [[Regio 2N|Omneo Premium]] (Z 56600) permettent d'atteindre une vitesse commerciale de 200 km/h.\n",
                WikipediaMaterialSections::LINE_SECTIONS)],
            self::DREUX => ['title' => self::DREUX, 'revid' => 2, 'sections' => WikipediaMaterialSections::sections(
                "== Exploitation ==\n=== Matériel roulant ===\nElles ont depuis 2021 été remplacées par des rames [[Regio 2N]].\n",
                WikipediaMaterialSections::LINE_SECTIONS)],
        ];
        // Z 56600 renvoie vers l'article de la famille.
        $this->cache->tables['wikipedia_materials'] = [
            'Regio 2N' => ['title' => 'Regio 2N', 'revid' => 241123224, 'sections' => []],
            'Z 56600' => ['title' => 'Regio 2N', 'revid' => 241123224, 'sections' => []],
            'BB 26000' => ['title' => 'BB 26000', 'revid' => 3, 'sections' => []],
            'Voiture Corail' => ['title' => 'Voiture Corail', 'revid' => 4, 'sections' => []],
        ];
        $this->cache->tables['redirects_materials'] = ['Regio 2N' => ['Z 56600', 'Omneo Premium', 'Régio 2N'], 'Z 56600' => [], 'BB 26000' => [], 'Voiture Corail' => []];
    }

    /** @return array{links: list<array<string, mixed>>, dropped_periods: list<array<string, mixed>>} */
    private function supplementData(): array
    {
        return [
            'links' => [
                ['line' => '366000', 'material' => 'Q120253257', 'kind' => 'wikipedia_material_article', 'article' => 'Regio 2N', 'revid' => 241123224, 'section' => 'Dessertes prévues et commandes',
                    'quote' => 'Les Z 56600 sont progressivement mises en service, depuis le 17 février 2020, sur les lignes Paris - Caen - Cherbourg', 'from' => ['date' => '2020-02-17', 'precision' => 'day']],
                ['line' => '366000', 'material' => 'Q604865', 'kind' => 'wikipedia_line_article', 'article' => self::CHERBOURG, 'revid' => 241344631, 'section' => 'Desserte > Liaisons Krono+ (grandes lignes)',
                    'quote' => 'La dernière circulation des BB 26000 a eu lieu le 2 février 2021.', 'to' => ['date' => '2021-02-02', 'precision' => 'day']],
                ['line' => '999999', 'material' => 'Q604865', 'kind' => 'wikipedia_line_article', 'article' => 'Ligne inconnue', 'revid' => 1, 'section' => 'Desserte', 'quote' => 'Phrase.'],
            ],
            'dropped_periods' => [
                ['line' => '366000', 'material' => 'Q1131858', 'start' => '1996-01-01', 'end' => null, 'source' => 'https://fr.wikipedia.org/w/index.php?title=BB_67400&oldid=240659828', 'reason' => 'début de l\'exclusivité'],
            ],
        ];
    }

    private function linker(): LineMaterialLinker
    {
        $cache = $this->cache;
        $wikidata = new class(new CommonsClient(0), $cache, WikidataMaterialRules::default(), 0) extends WikidataMaterialSource
        {
            public function vehicleUsage(bool $refresh = false): array
            {
                return [];
            }
        };
        $wikipedia = new class(app(WikimediaClient::class), $cache, 0) extends WikipediaArticleSource
        {
            public InMemorySourceCache $tables;

            public function sections(string $cacheName, array $titles, string $pattern, bool $refresh = false): array
            {
                $pages = $this->tables->load($cacheName);

                return array_combine($titles, array_map(fn (string $t): ?array => $pages[$t] ?? null, $titles));
            }

            public function redirects(string $cacheName, array $titles, bool $refresh = false): array
            {
                $redirects = $this->tables->load($cacheName);

                return array_combine($titles, array_map(fn (string $t): array => $redirects[$t] ?? [], $titles));
            }
        };

        $wikipedia->tables = $cache;

        return new LineMaterialLinker($wikidata, $wikipedia, supplements: LineMaterialSupplements::fromDatabase($this->supplementData()));
    }

    public function test_a_line_sentence_naming_the_omneo_premium_links_the_line_to_its_series_not_to_the_regio_2n(): void
    {
        $links = $this->linker()->collect();

        $this->assertArrayHasKey("{$this->cherbourg->id}:{$this->omneo->id}", $links, '« [[Regio 2N|Omneo Premium]] (Z 56600) » : les Z 56600');
        $this->assertArrayNotHasKey("{$this->cherbourg->id}:{$this->regio2n->id}", $links, 'la famille Regio 2N ne roule pas sur Mantes - Cherbourg à ce titre');
        $this->assertArrayHasKey("{$this->dreux->id}:{$this->regio2n->id}", $links, '« rames [[Regio 2N]] » sans version nommée : la famille');
        $this->assertArrayNotHasKey("{$this->dreux->id}:{$this->omneo->id}", $links);
    }

    public function test_reviewed_sentences_add_their_evidence_and_unknown_entries_are_reported(): void
    {
        $linker = $this->linker();
        $links = $linker->collect();

        $omneo = $links["{$this->cherbourg->id}:{$this->omneo->id}"]['evidence'];
        $this->assertSame(['wikipedia_line_article', 'wikipedia_material_article'], array_column($omneo, 'kind'));
        $this->assertTrue($omneo[1]['reviewed']);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Regio_2N&oldid=241123224', $omneo[1]['url']);
        $this->assertCount(2, $links["{$this->cherbourg->id}:{$this->bb26000->id}"]['evidence'], 'phrase de l\'article et phrase relue');
        $ours = array_values(array_filter($linker->unresolvedEntries(), fn (string $e): bool => str_starts_with($e, 'line_material_supplements.php')));
        $this->assertSame(['line_material_supplements.php n° 2 (ligne « 999999 », série Q604865) : ligne introuvable (ni lines.code ni lines.slug)'], $ours);
    }

    public function test_writing_can_be_limited_to_some_series(): void
    {
        $other = Material::factory()->create(['slug' => 'x-4750', 'name' => 'X 4750', 'type' => 'autorail', 'wikidata_id' => 'Q9']);
        $kept = LineMaterial::create(['line_id' => $this->cherbourg->id, 'material_id' => $other->id, 'source' => 'wikipedia', 'confidence' => 'medium', 'evidence' => [['kind' => 'wikipedia_line_article', 'quote' => 'Phrase.']]]);
        $stale = LineMaterial::create(['line_id' => $this->cherbourg->id, 'material_id' => $this->regio2n->id, 'source' => 'wikipedia', 'confidence' => 'medium', 'evidence' => [['kind' => 'wikipedia_line_article', 'quote' => 'Omneo Premium (Z 56600).']]]);
        $linker = $this->linker();
        $links = $linker->collect();
        $only = [$this->regio2n->id, $this->omneo->id];

        $diff = LineMaterialLinker::diff($links, $only);
        $this->assertSame(["{$this->cherbourg->id}:{$this->regio2n->id}"], $diff['removed'], 'le lien X 4750, absent des sources, n\'est pas concerné');
        $this->assertNotContains("{$this->cherbourg->id}:{$this->bb26000->id}", $diff['created']);

        $stats = $linker->write($links, $only);

        $this->assertSame(1, $stats['removed']);
        $this->assertModelMissing($stale);
        $this->assertModelExists($kept);
        $this->assertTrue(LineMaterial::query()->where('line_id', $this->cherbourg->id)->where('material_id', $this->omneo->id)->exists());
        $this->assertFalse(LineMaterial::query()->where('material_id', $this->bb26000->id)->exists(), 'BB 26000 hors de la liste : pas écrite');
    }

    public function test_periods_follow_the_named_version_and_reviewed_sentences_date_or_drop_them(): void
    {
        $this->linker()->write($this->linker()->collect());
        $collector = new MaterialPeriodCollector($this->cache, app(MaterialPeriodExtractor::class), supplements: LineMaterialSupplements::fromDatabase($this->supplementData()));

        ['periods' => $periods, 'stats' => $stats] = $collector->collect(offline: true);
        $byLink = [];
        foreach ($periods as $p) {
            $link = LineMaterial::query()->findOrFail($p['line_material_id']);
            $byLink[$link->material_id][] = [$p['start_date'], $p['end_date'], $p['confidence']];
        }

        $this->assertSame([['2020-02-17', null, 'high']], $byLink[$this->omneo->id], 'mise en service relue : pas abaissée parce qu\'elle tombe l\'année de la série');
        $this->assertContains([null, '2021-02-02', 'high'], $byLink[$this->bb26000->id]);
        $this->assertArrayNotHasKey($this->corail->id, $byLink, '« Depuis 1996 … exclusivement » : début d\'exclusivité retiré');
        $this->assertSame(1, $stats['dropped']);
        $this->assertSame([['2021-01-01', null, 'medium']], $byLink[$this->regio2n->id], 'la famille garde la période de la ligne qui la nomme (Saint-Cyr - Surdon)');
    }

    public function test_the_period_import_refuses_an_unknown_series_in_its_list(): void
    {
        $this->app->instance(SourceCache::class, $this->cache);

        $this->artisan('vff:import-material-periods', ['--offline' => true, '--dry-run' => true, '--materials' => 'Q120253257,Q404'])
            ->expectsOutputToContain('--materials : série(s) absente(s) de la base : Q404')
            ->assertFailed();
        $this->artisan('vff:import-material-periods', ['--offline' => true, '--dry-run' => true, '--materials' => 'Q120253257'])
            ->expectsOutputToContain('Écriture limitée à 1 série(s) : Q120253257.')
            ->assertSuccessful();
    }
}
