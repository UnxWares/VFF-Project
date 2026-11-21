<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Cas témoin de database/data/line_event_corrections_donnees.php relevé par l'audit des tronçons (21/11/2026) :
 * l'article Wikipédia de Culoz - Modane date du 19/09/1871 la fermeture du chemin de fer du Mont-Cenis (ligne
 * provisoire par le col), lue comme une fermeture de toute la ligne 900000. Le vrai fichier, appliqué à la ligne
 * reconstituée, neutralise cette seule fermeture : l'ouverture de 1871 et les fermetures des anciens tracés
 * restent.
 */
class CulozModaneCorrectionTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_donnees.php';

    private const URL = 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Culoz_%C3%A0_Modane_%28fronti%C3%A8re%29&oldid=238204405';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    /** Événement tel qu'importé (source, type, date, toute la ligne ou ancien tracé). */
    private function event(Line $line, string $source, string $type, string $date, string $resolution = 'whole_line'): int
    {
        return DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => str_ends_with($date, '-01-01') ? 'year' : 'day',
            'description' => 'Import', 'source' => $source, 'source_url' => $source === 'wikipedia' ? self::URL : null, 'confidence' => 'medium',
            'section_resolution' => $resolution, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_mont_cenis_closure_of_1871_is_neutralized_on_culoz_modane(): void
    {
        $corrections = array_values(array_filter(require database_path('data/'.self::FILE), fn (array $c) => $c['line'] === '900000'));
        $this->assertCount(1, $corrections);
        $this->assertSame(self::URL, $corrections[0]['source_url'], 'même permalien que l\'événement importé');
        $line = $this->rfnLine('900000', 'Ligne de Culoz à Modane (frontière)');
        $closure = $this->event($line, 'wikipedia', 'closure', '1871-09-19');
        $kept = [
            $this->event($line, 'wikipedia', 'opening', '1871-01-01'),
            $this->event($line, 'archeoferroviaire', 'closure', '1866-01-01', 'trace'),
            $this->event($line, 'archeoferroviaire', 'closure', '1876-01-01', 'trace'),
        ];
        $this->useDataFile(self::FILE, $corrections);

        $this->artisan('vff:apply-event-corrections', ['--line' => '900000'])->expectsOutputToContain('neutralisé (1)')->assertSuccessful();

        $this->assertSame('other', DB::table('line_events')->where('id', $closure)->value('type'));
        $description = (string) DB::table('line_events')->where('id', $closure)->value('description');
        $this->assertStringStartsWith('Fermeture du chemin de fer du Mont-Cenis', $description);
        $this->assertStringNotContainsString("\u{2014}", $description);
        $this->assertSame(['opening', 'closure', 'closure'], DB::table('line_events')->whereIn('id', $kept)->orderBy('id')->pluck('type')->all());

        // Rejouable : un second passage ne change rien.
        $this->artisan('vff:apply-event-corrections', ['--line' => '900000'])->expectsOutputToContain('déjà appliqué')->assertSuccessful();
    }
}
