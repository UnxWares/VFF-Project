<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Cas témoins de database/data/line_event_corrections_jamais_ouvertes.php (relecture du 19/11/2026 au soir) :
 * deux lignes jamais ouvertes des Chemins de fer départementaux de Rhône et Loire, Pontcharra - Sainte-Foy-
 * l'Argentière (3943, inachevée) et Feurs - Panissières (3637, jamais mise en service), portaient les années
 * de leur réseau (1914-1935) estimées par vff:import-secondary-network-dates. Le vrai fichier, appliqué à ces
 * deux lignes reconstituées, ne leur laisse aucune date d'exploitation et ne touche à rien d'autre.
 */
class NeverOpenedLinesCorrectionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const FILE = 'line_event_corrections_jamais_ouvertes.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
    }

    /** Estimation telle qu'écrite par vff:import-secondary-network-dates (source, confiance, toute la ligne). */
    private function estimate(Line $line, string $type, string $date): int
    {
        return DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'year',
            'description' => 'Estimation (date non sourcée pour cette ligne)', 'source' => 'estimation-reseau', 'confidence' => 'low',
            'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_estimated_dates_of_never_opened_lines_are_neutralized(): void
    {
        $corrections = require database_path('data/'.self::FILE);
        $pontcharra = $this->rfnLine('', 'Ligne de Pontcharra à Sainte-Foy-l’Argentière', ['id' => 3943, 'code' => null, 'network' => 'secondaire']);
        $feurs = $this->rfnLine('', 'Ligne de Feurs à Panissières', ['id' => 3637, 'code' => null, 'network' => 'secondaire']);
        // Témoin : la ligne réellement ouverte du réseau garde ses dates.
        $messimy = $this->rfnLine('', 'Ligne de Messimy à Saint-Symphorien-sur-Coise', ['code' => null, 'network' => 'secondaire']);
        $ids = [];
        foreach ([$pontcharra, $feurs, $messimy] as $line) {
            $ids[$line->id] = [$this->estimate($line, 'opening', '1914-01-01'), $this->estimate($line, 'closure', '1935-01-01')];
        }
        $this->useDataFile(self::FILE, $corrections);

        $this->artisan('vff:apply-event-corrections', ['--dry-run' => true])->expectsOutputToContain('4 correction(s) traitée(s)')->assertSuccessful();
        $this->assertSame(6, DB::table('line_events')->whereIn('type', ['opening', 'closure'])->count(), '--dry-run : rien d\'écrit');

        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('neutralisé (1)')->assertSuccessful();

        foreach ([$pontcharra, $feurs] as $line) {
            $events = DB::table('line_events')->whereIn('id', $ids[$line->id])->orderBy('event_date')->get(['type', 'description']);
            $this->assertSame(['other', 'other'], $events->pluck('type')->all(), $line->name);
            $this->assertStringStartsWith('Estimation d\'après le réseau, sans objet', $events[0]->description);
            $this->assertStringNotContainsString("\u{2014}", $events[0]->description);
        }
        $this->assertStringContainsString('ligne inachevée', DB::table('line_events')->where('id', $ids[3943][0])->value('description'));
        $this->assertSame(0, DB::table('line_events')->whereIn('line_id', [3943, 3637])->whereNot('type', 'other')->count());
        $this->assertSame(['opening', 'closure'], DB::table('line_events')->whereIn('id', $ids[$messimy->id])->orderBy('event_date')->pluck('type')->all());

        // Rejouable : un second passage ne change rien.
        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('déjà appliqué')->doesntExpectOutputToContain('neutralisé (')->assertSuccessful();
    }
}
