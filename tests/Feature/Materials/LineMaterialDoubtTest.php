<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Services\DataInvariants;
use App\Services\Materials\LineMaterialLinker;
use App\Services\Materials\WikidataMaterialRules;
use App\Services\Materials\WikidataMaterialSource;
use App\Services\Materials\WikipediaArticleSource;
use App\Services\Materials\WikipediaMaterialSections;
use App\Services\Media\CommonsClient;
use App\Services\WikimediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\InMemorySourceCache;
use Tests\TestCase;

/**
 * Doute sur les liens ligne × matériel (audit du 19/11/2026) : une preuve qui parle d'une commande, d'un
 * essai, d'un acheminement… garde le lien mais le range en `low` ; une seule preuve franche suffit à le
 * garder sûr ; un article cité pour ce qui dérive de la série n'est pas un lien. Phrases réelles de
 * l'article de la ligne de Saint-Gervais à Vallorcine (révision 240212251) et de l'article « 030 T Ouest
 * 3531 à 3602 » (révision 195379106).
 */
class LineMaterialDoubtTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $montBlanc;

    private Line $ceinture;

    private Material $z890;

    private Material $z800;

    private Material $ouest030;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montBlanc = Line::factory()->create(['slug' => 'rfn-896000', 'code' => '896000', 'name' => 'Ligne de St-Gervais-les-Bains-Le Fayet à Vallorcine', 'wikipedia_title' => 'Ligne de Saint-Gervais-les-Bains-Le Fayet à Vallorcine (frontière)']);
        $this->ceinture = Line::factory()->create(['slug' => 'rfn-955000', 'code' => '955000', 'name' => 'Ligne de La Râpée à Batignolles', 'wikipedia_title' => 'Ligne de Petite Ceinture']);
        $this->z890 = Material::factory()->create(['slug' => 'z-890', 'name' => 'Z 890', 'type' => 'automotrice', 'wikidata_id' => 'Q136218907', 'wikipedia_fr' => 'Z 890']);
        $this->z800 = Material::factory()->create(['slug' => 'z-800', 'name' => 'Z 800', 'type' => 'automotrice', 'wikidata_id' => 'Q3572450', 'wikipedia_fr' => 'Z 800']);
        $this->ouest030 = Material::factory()->create(['slug' => '030-t-ouest-3531', 'name' => '030 T Ouest 3531 à 3602', 'type' => 'locomotive_vapeur', 'wikidata_id' => 'Q2814270', 'wikipedia_fr' => '030 T Ouest 3531 à 3602']);
    }

    private function linker(): LineMaterialLinker
    {
        $cache = new InMemorySourceCache;
        $wikidata = new class(new CommonsClient(0), $cache, WikidataMaterialRules::default(), 0) extends WikidataMaterialSource
        {
            public function vehicleUsage(bool $refresh = false): array
            {
                return [];
            }
        };
        $wikipedia = new class(app(WikimediaClient::class), $cache, 0) extends WikipediaArticleSource
        {
            public function sections(string $cacheName, array $titles, string $pattern, bool $refresh = false): array
            {
                $pages = [
                    'wikipedia_lines' => [
                        'Ligne de Saint-Gervais-les-Bains-Le Fayet à Vallorcine (frontière)' => ['title' => 'Ligne de Saint-Gervais-les-Bains-Le Fayet à Vallorcine (frontière)', 'revid' => 240212251, 'wikitext' => <<<'WIKI'
                            == Matériel roulant ==
                            === Matériel Z 800 ===
                            [[Fichier:Le Buet Triebzug01 2000-03-17.jpg|thumb|Rame [[Z 800]] à l'arrêt du Buet.]]
                            === Matériel Z 890 ===
                            Le {{date|14 juin 2019}}, les TMR et la SNCF ont signé avec Stadler un contrat portant sur l’acquisition de 7 nouvelles rames, immatriculées [[Z 890]] et destinées à remplacer les Z 800.
                            WIKI],
                        'Ligne de Petite Ceinture' => null,
                    ],
                    'wikipedia_materials' => [
                        '030 T Ouest 3531 à 3602' => ['title' => '030 T Ouest 3531 à 3602', 'revid' => 195379106, 'wikitext' => <<<'WIKI'
                            == Utilisation et service ==
                            Elles ont été surnommées ''[[Boër (locomotive)|Boer]]'', du fait que les locomotives [[Ligne de Petite Ceinture|Ceinture]] n° '''21 à 35''' qui en sont dérivées, ont été construites en [[1899 dans les chemins de fer|1899]] pendant la [[guerre des Boers]].
                            WIKI],
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
                return array_fill_keys($titles, []);
            }
        };

        return new LineMaterialLinker($wikidata, $wikipedia);
    }

    /** @return array{count: int, examples: list<string>} */
    private function invariant(): array
    {
        $result = (new DataInvariants(['line_materials.doubtful_confidence']))->run();
        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('error', $result[0]);

        return ['count' => $result[0]['count'], 'examples' => $result[0]['examples']];
    }

    public function test_a_purchase_contract_keeps_the_link_as_unconfirmed(): void
    {
        $links = $this->linker()->collect();

        $z890 = $links["{$this->montBlanc->id}:{$this->z890->id}"]['evidence'];
        $this->assertSame('order', $z890[0]['doubt'], '« un contrat portant sur l’acquisition de 7 nouvelles rames »');
        $this->assertSame('low', LineMaterialLinker::attributes($z890)['confidence'], 'gardé, mais à confirmer');

        $z800 = $links["{$this->montBlanc->id}:{$this->z800->id}"]['evidence'];
        $this->assertArrayNotHasKey('doubt', $z800[0], 'la photo d\'une rame à l\'arrêt du Buet ne met rien en doute');
        $this->assertSame('high', LineMaterialLinker::attributes($z800)['confidence']);
    }

    public function test_a_line_cited_for_derived_locomotives_is_not_linked(): void
    {
        $links = $this->linker()->collect();

        $this->assertArrayNotHasKey("{$this->ceinture->id}:{$this->ouest030->id}", $links, '« les locomotives Ceinture n° 21 à 35 qui en sont dérivées »');
    }

    public function test_one_plain_evidence_is_enough_to_stay_confident(): void
    {
        $doubtful = ['kind' => LineMaterialLinker::KIND_LINE_ARTICLE, 'section' => 'Matériels roulants utilisés', 'quote' => '30 rames tricourants Duplex nouvelle génération (TGV 2N2) ont été commandées par la SNCF pour l\'exploitation de la ligne dès sa mise en service.', 'doubt' => 'order'];
        $plain = ['kind' => LineMaterialLinker::KIND_LINE_ARTICLE, 'section' => 'Matériels roulants utilisés', 'quote' => 'Les rames TGV Duplex, TGV Reseau et TGV 2N2 assurent les relations Paris-Lyon - Mulhouse.'];

        $this->assertSame('low', LineMaterialLinker::attributes([$doubtful])['confidence']);
        $both = LineMaterialLinker::attributes([$doubtful, $plain]);
        $this->assertSame('high', $both['confidence']);
        $this->assertSame($plain['quote'], $both['evidence'][0]['quote'], 'la preuve franche d\'abord');
    }

    public function test_writing_keeps_the_invariant_and_a_confident_doubtful_link_is_reported(): void
    {
        $linker = $this->linker();
        $links = $linker->collect();
        $this->assertSame(['created' => 2, 'updated' => 0, 'removed' => 0], $linker->write($links));
        $this->assertSame(['count' => 0, 'examples' => []], $this->invariant());

        $z890 = LineMaterial::query()->where('material_id', $this->z890->id)->sole();
        $this->assertSame('low', $z890->confidence);
        $z890->update(['confidence' => 'high']);
        $this->assertSame([
            'count' => 1,
            'examples' => ["896000 × Z 890 (lien {$z890->id}, high) : order"],
        ], $this->invariant());
    }

    public function test_the_api_gives_the_reason_for_doubt(): void
    {
        $linker = $this->linker();
        $linker->write($linker->collect());

        /** @var list<array{material: array{slug: string}, confidence: string, evidence: list<array<string, mixed>>}> $data */
        $data = $this->getJson('/api/v1/lines/rfn-896000/materials')->assertOk()->json('data');
        $materials = array_column(array_map(fn (array $l): array => $l + ['slug' => $l['material']['slug']], $data), null, 'slug');
        $this->assertSame('low', $materials['z-890']['confidence']);
        $this->assertSame('order', $materials['z-890']['evidence'][0]['doubt']);
        $this->assertArrayNotHasKey('doubt', $materials['z-800']['evidence'][0]);

        $links = $this->graphQL('{ line(slug: "rfn-896000") { material_links { confidence material { slug } evidence { doubt } } } }')
            ->assertGraphQLErrorFree()->json('data.line.material_links');
        $this->assertContains(['confidence' => 'low', 'material' => ['slug' => 'z-890'], 'evidence' => [['doubt' => 'order']]], $links);
        $this->assertContains(['confidence' => 'high', 'material' => ['slug' => 'z-800'], 'evidence' => [['doubt' => null]]], $links);
    }
}
