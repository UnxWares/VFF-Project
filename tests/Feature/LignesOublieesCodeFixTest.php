<?php

namespace Tests\Feature;

use App\Console\Commands\ImportLignesOubliees;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:import-lignes-oubliees : numéro de ligne mal recopié dans un PDF
 * (fermetures.pdf, « Voie du port de Saint-Malo » : 422 500 pour 442 500),
 * corrigé d'après le référentiel SNCF et les autres lignes du document, puis
 * fusion de la ligne créée sous le mauvais code (cas réel 2026-10-19).
 */
class LignesOublieesCodeFixTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $ligne, string $code, int $a, int $b, string $file = 'fermetures'): array
    {
        return ['file' => $file, 'type' => 'closure', 'kind' => null, 'ligne' => $ligne, 'section' => 'Section', 'code' => $code,
            'pk_start' => $a, 'pk_end' => $b, 'date' => '2024-07-10', 'obs' => '', 'quote' => "{$ligne} | {$code}"];
    }

    public function test_one_digit_typo_is_corrected_only_with_referential_proof(): void
    {
        DB::table('sncf_line_types')->insert([
            ['code_ligne' => '442500', 'type_ligne' => 'Vport', 'pkd_m' => 453324, 'pkf_m' => 454914],
            ['code_ligne' => '123000', 'type_ligne' => 'Ligne', 'pkd_m' => 0, 'pkf_m' => 10000],
        ]);
        $rows = app(ImportLignesOubliees::class)->fixPdfCodes([
            $this->row('Voie du port de Saint-Malo', '442500', 454505, 454914),
            $this->row('Voie du port de Saint-Malo', '422500', 453940, 454505),   // coquille d'un chiffre → 442500
            $this->row('Voie du port de Saint-Malo', '422501', 453940, 454505),   // deux chiffres d'écart : inchangé
            $this->row('Ligne de Alpha à Bravo', '124000', 50000, 52000),
            $this->row('Ligne de Alpha à Bravo', '123000', 1000, 2000),           // PK hors de la plage SNCF de 123000
            $this->row('Ligne de Charlie à Delta', '476000', 1000, 2000),         // code historique seul : inchangé
            $this->row('Voie du port de Saint-Malo', '422500', 453940, 454505, 'declassements'), // autre document : inchangé
        ]);

        $this->assertSame(['442500', '442500', '422501', '124000', '123000', '476000', '422500'], array_column($rows, 'code'));
        $this->assertSame('422500', $rows[1]['code_cited']);
        $this->assertArrayNotHasKey('code_cited', $rows[2]);
    }

    public function test_code_known_elsewhere_is_never_corrected(): void
    {
        DB::table('sncf_line_types')->insert(['code_ligne' => '442500', 'type_ligne' => 'Vport', 'pkd_m' => 453324, 'pkf_m' => 454914]);
        Line::factory()->create(['code' => '422500', 'slug' => 'ligne-422500']); // code porté par une ligne VFF (autre source)

        $rows = app(ImportLignesOubliees::class)->fixPdfCodes([
            $this->row('Voie du port de Saint-Malo', '442500', 454505, 454914),
            $this->row('Voie du port de Saint-Malo', '422500', 453940, 454505),
        ]);

        $this->assertSame(['442500', '422500'], array_column($rows, 'code'));
    }

    public function test_line_created_under_the_wrong_code_is_merged_with_proof(): void
    {
        DB::table('sncf_line_types')->insert(['code_ligne' => '442500', 'type_ligne' => 'Vport', 'pkd_m' => 453324, 'pkf_m' => 454914]);
        $good = Line::factory()->create(['name' => 'Voie du port de Saint-Malo', 'code' => '442500', 'slug' => 'lo-442500', 'alt_names' => ['name_source' => 'lignes-oubliees']]);
        $bad = Line::factory()->create(['name' => 'Voie du port de Saint-Malo', 'code' => '422500', 'slug' => 'lo-422500']);
        DB::table('line_events')->insert(['line_id' => $bad->id, 'type' => 'closure', 'event_date' => '2024-07-10', 'description' => 'Fermeture',
            'source' => 'lignes-oubliees', 'section_pk_start_m' => 453940, 'section_pk_end_m' => 454505, 'created_at' => now(), 'updated_at' => now()]);

        $cmd = app(ImportLignesOubliees::class);
        $cmd->fixPdfCodes([
            $this->row('Voie du port de Saint-Malo', '442500', 454505, 454914),
            $this->row('Voie du port de Saint-Malo', '422500', 453940, 454505),
        ]);
        $cmd->applyCodeCorrections(['442500' => $good->id]);
        $cmd->applyCodeCorrections(['442500' => $good->id]); // rejouable : rien de plus

        $this->assertFalse(DB::table('lines')->where('id', $bad->id)->exists());
        $this->assertSame(1, DB::table('line_events')->where('line_id', $good->id)->count());
        $alt = json_decode(DB::table('lines')->where('id', $good->id)->value('alt_names'), true);
        $this->assertSame('422500', $alt['code_corrections'][0]['cited']);
        $this->assertStringContainsString('442500 au référentiel SNCF', $alt['code_corrections'][0]['proof']);
        $this->assertStringNotContainsString('—', $alt['code_corrections'][0]['proof']);
        $this->assertSame(['lo-422500'], array_column($alt['merged'], 'slug'));
        $this->assertSame('lignes-oubliees', $alt['name_source']);
    }
}
