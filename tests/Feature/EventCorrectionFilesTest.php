<?php

namespace Tests\Feature;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:apply-event-corrections lit le fichier principal ET les fichiers thématiques
 * (database/data/line_event_corrections_*.php, ex. les fins d'exploitation non datées
 * de l'invariant lines.km_drop_without_event) ; un ajout porte son type de patrimoine
 * ou de reconversion ; un ajout retiré de n'importe quel fichier disparaît de la base.
 */
class EventCorrectionFilesTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        File::ensureDirectoryExists($this->sandbox.'/database/data');
        $original = $this->app->databasePath();
        $this->app->useDatabasePath($this->sandbox.'/database');
        $this->beforeApplicationDestroyed(fn () => $this->app->useDatabasePath($original));
        Line::factory()->create(['code' => '709000', 'name' => 'Ligne de Lapeyrouse à Volvic']);
    }

    private function write(string $file, array $corrections): void
    {
        File::put($this->sandbox.'/database/data/'.$file, '<?php return '.var_export($corrections, true).';');
    }

    private function add(string $type, string $date, array $more = []): array
    {
        return $more + ['line' => '709000', 'action' => 'add', 'type' => $type, 'date' => $date, 'precision' => 'day',
            'from' => 'Lapeyrouse', 'to' => 'Volvic', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1',
            'quote' => 'Citation.', 'reason' => 'fin du trafic'];
    }

    public function test_thematic_files_are_applied_with_the_main_file(): void
    {
        $this->write('line_event_corrections.php', [$this->add('closure_passenger', '2007-12-08')]);
        $this->write('line_event_corrections_fins.php', [
            $this->add('closure_freight', '2007-12-08'),
            $this->add('heritage_start', '2015-07-01', ['heritage_kind' => 'velorail', 'from' => 'Les Ancizes', 'to' => 'Volvic']),
        ]);

        $this->artisan('vff:apply-event-corrections')->assertSuccessful();

        $events = DB::table('line_events')->where('source', 'manual')->orderBy('event_date')->orderBy('type')->get();
        $this->assertSame(['closure_freight', 'closure_passenger', 'heritage_start'], $events->pluck('type')->all());
        $this->assertSame('velorail', $events[2]->heritage_kind);
        $this->assertStringStartsWith("Début de l'exploitation patrimoniale de Les Ancizes à Volvic", $events[2]->description);

        // Ajout retiré du fichier thématique : supprimé ; ceux du fichier principal restent.
        $this->write('line_event_corrections_fins.php', [$this->add('closure_freight', '2007-12-08')]);
        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        $this->assertSame(['closure_freight', 'closure_passenger'], DB::table('line_events')->where('source', 'manual')->orderBy('type')->pluck('type')->all());
    }

    public function test_unknown_heritage_kind_is_refused(): void
    {
        $this->write('line_event_corrections.php', []);
        $this->write('line_event_corrections_fins.php', [$this->add('heritage_start', '2015-07-01', ['heritage_kind' => 'draisine'])]);

        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('heritage_kind inconnu')->assertFailed();
        $this->assertSame(0, DB::table('line_events')->where('source', 'manual')->count());
    }
}
