<?php

namespace Tests\Feature\Search;

use App\Support\Search\SearchText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Une seule normalisation : la clé PHP (SearchText::key), la fonction SQL `vff_search_key` et la formule en ligne
 * (base sans la migration) donnent la même chaîne, et les colonnes calculées suivent les écritures.
 */
class SearchTextSqlParityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function texts(): array
    {
        return array_map(fn (string $t) => [$t], [
            'chaleons' => 'St-Hilaire-de-Chaléons',
            'sous' => 'Fontenay-s/s-Bois',
            'sur' => 'Ste-Colombe-s/-Seine',
            'notre-dame' => 'N.-D. de Riez',
            'apostrophe' => 'Noisy-le-Grand - Mont d’Est',
            'ligature' => 'Wœlfling-lès-Sarreguemines',
            'materiel' => 'BB7600 / 2D2 5100',
            'cedille' => 'Mâcon Ville, Besançon-Viotte',
            'arrondissement' => 'Paris 10e Arrondissement',
            'eszett' => 'Straße',
            'allemand' => 'Eisenbahnunfall von Brétigny',
            'chevrons' => '« Pointu » (TGV)',
            'petits mots au bout' => 'Ligne de Paris à',
            'que des petits mots' => 'de la',
            'vide' => ' - ',
        ]);
    }

    #[DataProvider('texts')]
    public function test_php_and_sql_keys_are_identical(string $text): void
    {
        $php = SearchText::key($text);
        $this->assertSame($php, DB::selectOne('SELECT vff_search_key(?) AS k', [$text])->k);
        $this->assertSame($php, DB::selectOne('SELECT '.SearchText::sqlKeyFormula('?::text', 'unaccent').' AS k', [$text])->k);
        $this->assertSame(SearchText::core($php), DB::selectOne('SELECT '.SearchText::sqlCore('?::text').' AS k', [$php])->k);
    }

    public function test_generated_columns_follow_writes(): void
    {
        $this->assertTrue(SearchText::sqlReady());
        $id = DB::table('stations')->insertGetId([
            'slug' => 'st-hilaire-de-chaleons', 'name' => 'St-Hilaire-de-Chaléons', 'city' => 'Saint-Hilaire-de-Chaléons',
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(-1.87, 47.1), 4326)'),
            'alt_names' => json_encode([['name' => 'Chaléons', 'source' => 'wikidata']]),
        ]);
        $row = DB::table('stations')->where('id', $id)->first(['search_name', 'search_all']);
        $this->assertSame(' saint hilaire de chaleons', $row->search_name);
        $this->assertStringContainsString(' chaleons', substr($row->search_all, strlen(' saint hilaire de chaleons saint hilaire de chaleons')));
        DB::table('stations')->where('id', $id)->update(['name' => 'Ste-Pazanne']);
        $this->assertSame(' sainte pazanne', DB::table('stations')->where('id', $id)->value('search_name'));
    }
}
