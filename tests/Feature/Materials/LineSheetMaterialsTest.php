<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ReadsInertiaProps;
use Tests\TestCase;

/**
 * Section « Matériel roulant » de la fiche de ligne : séries citées sans service attesté, séries électriques
 * bornées par l'électrification de la ligne, famille non listée à côté de ses versions.
 */
class LineSheetMaterialsTest extends TestCase
{
    use ReadsInertiaProps;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['slug' => 'rfn-366000', 'code' => '366000', 'name' => 'Ligne de Mantes-la-Jolie à Cherbourg', 'length_official_m' => 313000]);
    }

    /** @param  list<array<string, mixed>>  $evidence */
    private function link(Material $m, string $confidence, array $evidence): LineMaterial
    {
        return LineMaterial::create(['line_id' => $this->line->id, 'material_id' => $m->id, 'source' => 'wikipedia', 'confidence' => $confidence, 'evidence' => $evidence]);
    }

    /** @return Collection<string, array<string, mixed>> */
    private function sheetMaterials(): Collection
    {
        $rows = new Collection;
        $this->getWithDeferredProps('/lines/rfn-366000')->assertOk()->assertInertia(function (Assert $page) use (&$rows): void {
            $page->where('materials', function ($materials) use (&$rows): bool {
                $rows = $this->rows($materials);

                return true;
            });
        });

        /** @var Collection<string, array<string, mixed>> $bySlug */
        $bySlug = $rows->keyBy('slug');

        return $bySlug;
    }

    public function test_a_series_cited_only_for_a_trial_or_an_order_is_flagged_with_the_reason(): void
    {
        $served = Material::factory()->create(['slug' => 'bb-67400', 'name' => 'BB 67400', 'type' => 'locomotive_diesel']);
        $trial = Material::factory()->create(['slug' => 'bb-26000', 'name' => 'BB 26000', 'type' => 'locomotive_electrique']);
        $order = Material::factory()->create(['slug' => 'z-99', 'name' => 'Z 99', 'type' => 'automotrice']);
        $mixed = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique']);
        $shared = Material::factory()->create(['slug' => 'x-2800', 'name' => 'X 2800', 'type' => 'autorail']);
        $this->link($served, 'high', [['kind' => 'wikipedia_line_article', 'quote' => 'Les BB 67400 assurent les trains.']]);
        $this->link($trial, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Des essais ont eu lieu.', 'doubt' => 'trial']]);
        $this->link($order, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Commande de rames.', 'doubt' => 'order'], ['kind' => 'wikipedia_line_article', 'quote' => 'Présentation.', 'doubt' => 'one_off']]);
        $this->link($mixed, 'medium', [['kind' => 'wikipedia_line_article', 'quote' => 'Essais.', 'doubt' => 'trial'], ['kind' => 'wikipedia_line_article', 'quote' => 'Elles assurent les trains.']]);
        $this->link($shared, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Article de plusieurs lignes.', 'shared_article' => true]]);

        $rows = $this->sheetMaterials();

        $this->assertNull($rows['bb-67400']['link_doubt']);
        $this->assertSame('trial', $rows['bb-26000']['link_doubt']);
        $this->assertSame('one_off', $rows['z-99']['link_doubt'], 'la présentation est plus parlante que la commande');
        $this->assertNull($rows['bb-15000']['link_doubt'], 'une preuve sans doute suffit');
        $this->assertNull($rows['x-2800']['link_doubt'], 'article partagé : pas un doute sur la nature de la circulation');
    }

    public function test_a_series_with_dated_periods_is_never_flagged(): void
    {
        $m = Material::factory()->create(['slug' => 'bb-26000', 'name' => 'BB 26000', 'type' => 'locomotive_electrique']);
        $link = $this->link($m, 'low', [['kind' => 'wikipedia_line_article', 'quote' => 'Essais.', 'doubt' => 'trial']]);
        LineMaterialPeriod::create(['line_material_id' => $link->id, 'start_date' => '2001-01-01', 'start_precision' => 'year', 'confidence' => 'high', 'origin' => 'line_article', 'fingerprint' => md5((string) microtime()), 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1']);

        $this->assertNull($this->sheetMaterials()['bb-26000']['link_doubt']);
    }
}
