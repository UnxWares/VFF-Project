<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:apply-event-corrections : corrections sourcées des événements de lignes
 * (database/data/line_event_corrections.php, remplacé ici par un fichier du
 * bac à sable). Ajout, neutralisation d'une fausse fermeture, déplacement de
 * portion, nettoyage des ajouts retirés, validation, rejouabilité.
 */
class EventCorrectionsCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        $this->era(2030, current: true);
        $this->line = $this->rfnLine('473000', "Ligne d'Auray à Quiberon");
    }

    /** @return array<string, mixed> */
    private function add(array $overrides = []): array
    {
        return $overrides + [
            'line' => '473000', 'action' => 'add', 'type' => 'reopening', 'date' => '1985-06-29', 'precision' => 'month',
            'from' => 'Auray', 'to' => 'Quiberon', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1',
            'quote' => 'Le « Tire-Bouchon » circule l’été depuis 1985 — chaque année.', 'reason' => 'desserte estivale -- relancée',
        ];
    }

    private function sourceEvent(string $type, string $date, array $attributes = []): int
    {
        return DB::table('line_events')->insertGetId($attributes + [
            'line_id' => $this->line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day',
            'description' => 'Fermeture aux voyageurs', 'source' => 'lignes-oubliees', 'section_resolution' => 'resolved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_add_creates_a_manual_event_once_and_removes_it_when_dropped_from_the_file(): void
    {
        $this->useDataFile('line_event_corrections.php', [$this->add()]);

        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('1 correction(s) traitée(s).')->assertSuccessful();

        $event = DB::table('line_events')->where('source', 'manual')->first();
        $this->assertSame([$this->line->id, 'reopening', '1985-06-29', 'month', 'high', 'pending', 'Auray', 'Quiberon'],
            [$event->line_id, $event->type, $event->event_date, $event->date_precision, $event->confidence, $event->section_resolution, $event->section_from, $event->section_to]);
        // Élision devant voyelle (« d'Auray »), tirets longs et doubles remplacés.
        $this->assertSame("Réouverture d'Auray à Quiberon (correction sourcée : desserte estivale - relancée)", $event->description);
        $this->assertSame('Le « Tire-Bouchon » circule l’été depuis 1985 - chaque année.', $event->source_quote);
        $this->assertStringStartsWith('correction:', $event->fingerprint);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?oldid=1', $event->source_url);

        // Rejouable : même état.
        $snapshot = $this->snapshot(['line_events']);
        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('à jour')->assertSuccessful();
        $this->assertSame($snapshot, $this->snapshot(['line_events']));

        // Correction retirée du fichier : l'ajout disparaît ; les autres événements restent.
        $other = $this->sourceEvent('opening', '1882-07-24');
        $this->useDataFile('line_event_corrections.php', []);
        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('1 ajout(s) retiré(s) du fichier')->assertSuccessful();
        $this->assertSame([$other], DB::table('line_events')->pluck('id')->all());
    }

    public function test_changing_the_portion_of_an_added_event_resets_its_resolution(): void
    {
        $this->useDataFile('line_event_corrections.php', [$this->add()]);
        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        DB::table('line_events')->update(['section_resolution' => 'resolved', 'section_length_m' => 27_000]);

        // Même fingerprint (type, date, portion) : la mise à jour garde la résolution…
        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        $this->assertSame('resolved', DB::table('line_events')->value('section_resolution'));

        // …une autre portion est un AUTRE ajout : l'ancien est supprimé, le nouveau est à résoudre.
        $this->useDataFile('line_event_corrections.php', [$this->add(['to' => 'Plouharnel'])]);
        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        $rows = DB::table('line_events')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(['Plouharnel', 'pending', null], [$rows[0]->section_to, $rows[0]->section_resolution, $rows[0]->section_length_m]);
    }

    public function test_neutralize_turns_a_false_closure_into_an_other_event(): void
    {
        $closure = $this->sourceEvent('closure_passenger', '1972-03-06', ['source_quote' => 'Fermeture au service omnibus.']);
        $sameDayOtherSource = $this->sourceEvent('closure_passenger', '1972-03-06', ['source' => 'wikipedia']);
        $this->useDataFile('line_event_corrections.php', [[
            'line' => '473000', 'action' => 'neutralize',
            'match' => ['source' => 'lignes-oubliees', 'type' => 'closure_passenger', 'date' => '1972-03-06'],
            'source_url' => 'https://example.org/auray', 'reason' => 'fin du service omnibus, trains d’été maintenus',
        ]]);

        $this->artisan('vff:apply-event-corrections', ['--dry-run' => true])->expectsOutputToContain('à neutraliser (1)')->assertSuccessful();
        $this->assertSame('closure_passenger', DB::table('line_events')->where('id', $closure)->value('type'));

        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('neutralisé (1)')->assertSuccessful();
        $event = DB::table('line_events')->find($closure);
        $this->assertSame('other', $event->type);
        $this->assertSame('Réduction de la desserte voyageurs (correction sourcée : fin du service omnibus, trains d’été maintenus)', $event->description);
        $this->assertSame('Fermeture au service omnibus.', $event->source_quote);
        $this->assertSame('closure_passenger', DB::table('line_events')->where('id', $sameDayOtherSource)->value('type'));

        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('déjà appliqué')->assertSuccessful();
    }

    public function test_relocate_moves_the_event_portion_and_tolerates_a_missing_target(): void
    {
        $id = $this->sourceEvent('closure', '1990-05-27', ['section_from' => 'Lille', 'section_to' => 'Calais', 'section_length_m' => 100_000]);
        $correction = [
            'line' => '473000', 'action' => 'relocate',
            'match' => ['source' => 'lignes-oubliees', 'type' => 'closure', 'date' => '1990-05-27', 'from' => 'Lille'],
            'from' => 'Calais', 'to' => 'Marquise', 'pk_start' => 280_000, 'pk_end' => 296_000,
            'source_url' => 'https://example.org/calais', 'reason' => 'seule la section Calais — Marquise ferme',
        ];
        $this->useDataFile('line_event_corrections.php', [$correction]);

        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('déplacé (1)')->assertSuccessful();
        $event = DB::table('line_events')->find($id);
        $this->assertSame(['Calais', 'Marquise', 280_000, 296_000, 'pending', null],
            [$event->section_from, $event->section_to, $event->section_pk_start_m, $event->section_pk_end_m, $event->section_resolution, $event->section_length_m]);
        $this->assertSame('Portion corrigée : seule la section Calais - Marquise ferme', $event->section_resolution_note);

        // Deuxième passage : l'événement (désormais au départ de Calais) est retrouvé, rien à faire.
        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('déjà appliqué')->assertSuccessful();

        // Réimport de la source (800000, 2022) : portion gardée, mais note « Portion non précisée » et
        // « unresolved » remis, que le résolveur ne retente jamais : la correction est rejouée.
        DB::table('line_events')->where('id', $id)->update(['section_resolution' => 'unresolved',
            'section_resolution_note' => 'Portion non précisée par la source (réouverture sans portion explicite)']);
        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('déplacé (1)')->assertSuccessful();
        $this->assertSame(['pending', 'Portion corrigée : seule la section Calais - Marquise ferme'],
            [DB::table('line_events')->where('id', $id)->value('section_resolution'), DB::table('line_events')->where('id', $id)->value('section_resolution_note')]);

        // Événement visé disparu de la source : avertissement, pas d'échec.
        DB::table('line_events')->delete();
        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('introuvable')->assertSuccessful();
    }

    public function test_line_filter_applies_only_that_line_but_keeps_other_additions(): void
    {
        $other = $this->rfnLine('884000', 'Ligne de Bourg-en-Bresse à Bellegarde');
        $this->useDataFile('line_event_corrections.php', [
            $this->add(),
            $this->add(['line' => '884000', 'from' => 'La Cluse', 'to' => 'Bellegarde', 'date' => '2010-12-01']),
        ]);
        $this->artisan('vff:apply-event-corrections', ['--line' => '884000'])->assertSuccessful();
        $this->assertSame([$other->id], DB::table('line_events')->pluck('line_id')->all());

        // Ligne désignée par son id (moins de 6 chiffres).
        $this->artisan('vff:apply-event-corrections', ['--line' => (string) $this->line->id])->assertSuccessful();
        $this->assertSame(2, DB::table('line_events')->count());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalid(): iterable
    {
        yield 'ligne inconnue' => [['line' => '999999'], 'ligne inconnue « 999999 »'];
        yield 'action inconnue' => [['action' => 'delete'], 'action inconnue « delete »'];
        yield 'type inconnu' => [['type' => 'teleportation'], 'type inconnu « teleportation »'];
        yield 'date impossible' => [['date' => '1985-02-30'], 'date invalide'];
        yield 'date partielle' => [['date' => '1985-06'], 'date invalide'];
        yield 'sans source' => [['source_url' => ''], 'source_url et reason obligatoires'];
        yield 'neutralisation incomplète' => [['action' => 'neutralize', 'match' => ['source' => 'wikipedia']], 'match incomplet (source, type, date)'];
    }

    #[DataProvider('invalid')]
    public function test_invalid_corrections_are_reported_and_make_the_run_fail(array $overrides, string $problem): void
    {
        $this->useDataFile('line_event_corrections.php', [$this->add(['date' => '1990-01-01']), $this->add($overrides)]);

        $this->artisan('vff:apply-event-corrections')
            ->expectsOutputToContain("Correction line_event_corrections n° 1 : {$problem}")
            ->expectsOutputToContain('1 invalide(s)')
            ->assertFailed();

        // La correction valide est appliquée malgré tout.
        $this->assertSame(['1990-01-01'], DB::table('line_events')->pluck('event_date')->all());
    }
}
