<?php

namespace Tests\Feature\Contributions;

use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\User;
use App\Services\Api\ApiTokens;
use App\Services\Contributions\EventContributions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Correction d'un événement existant depuis la chronologie de la fiche (/contribute/event?line=…&event=…) :
 * formulaire prérempli, événement visé gardé dans la charge utile, raison obligatoire, relecture à
 * deux, puis publication de l'événement corrigé et neutralisation de l'événement visé (comme les
 * fichiers de corrections sourcées), rejouée par vff:apply-event-corrections après un import.
 * Aussi : citation courte de la source (source_excerpt), publiée à la place de la référence.
 */
class EventCorrectionContributionTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use CommandSandbox;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private int $wrongId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->withCookie('locale', 'fr');
        $this->wrongId = (int) DB::table('line_events')->insertGetId([
            'line_id' => $this->line->id, 'type' => 'closure_passenger', 'event_date' => '1969-01-01', 'date_precision' => 'year',
            'description' => 'Fermeture aux voyageurs', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/Ligne_test',
            'section_from' => 'Saint-Cloud', 'section_to' => 'Versailles-Rive-Droite', 'section_resolution' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function correction(array $overrides = []): array
    {
        return [
            'line_slug' => $this->line->slug,
            'corrects_event_id' => $this->wrongId,
            'correction_reason' => 'La source date le report sur la route, la fin des voyageurs est de 1972.',
            'type' => 'closure_passenger',
            'precision' => 'day',
            'date' => '1972-03-06',
            'portion' => 'places',
            'from' => 'Saint-Cloud',
            'to' => 'Versailles-Rive-Droite',
            'source_kind' => 'web',
            'source_title' => 'Gare de Saint-Cloud, Wikipédia',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Saint-Cloud&oldid=1',
            'source_excerpt' => 'fermée au service des voyageurs le 6 mars 1972',
            ...$overrides,
        ];
    }

    private function submit(User $author, array $overrides = []): Contribution
    {
        $this->actingAs($author)->post('/contribute/event', $this->correction($overrides))->assertRedirect();

        return Contribution::query()->latest('id')->firstOrFail();
    }

    private function approveTwice(Contribution $contribution): void
    {
        foreach ([$this->member('validator'), $this->member('validator')] as $validator) {
            $this->actingAs($validator)->post("/validate/{$contribution->id}/approve")->assertSessionHas('success');
        }
    }

    // ─── Formulaire ─────────────────────────────────────────────────────────

    public function test_the_form_is_prefilled_with_the_event_to_correct(): void
    {
        $this->actingAs($this->member())
            ->get("/contribute/event?line={$this->line->slug}&event={$this->wrongId}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contribute/ProposeEvent')
                ->where('preselectedLine.slug', $this->line->slug)
                ->where('correctedEvent.event_id', $this->wrongId)
                ->where('correctedEvent.type', 'closure_passenger')
                ->where('correctedEvent.date', '1969-01-01')
                ->where('correctedEvent.precision', 'year')
                ->where('correctedEvent.from', 'Saint-Cloud')
                ->where('correctedEvent.source', 'wikipedia')
                ->where('excerptMax', EventContributions::EXCERPT_MAX)
                ->where('reasonLength', [EventContributions::REASON_MIN, EventContributions::REASON_MAX]));
    }

    /** @return array<string, array{0: string}> */
    public static function uncorrectableEvents(): array
    {
        return ['autre ligne' => ['other_line'], 'accident' => ['accident'], 'déjà neutralisé' => ['neutralized'], 'identifiant illisible' => ['garbage']];
    }

    #[DataProvider('uncorrectableEvents')]
    public function test_an_uncorrectable_event_opens_the_new_event_form(string $case): void
    {
        $id = match ($case) {
            'other_line' => (int) DB::table('line_events')->insertGetId(['line_id' => Line::factory()->create()->id, 'type' => 'opening', 'event_date' => '1900-01-01', 'description' => 'Ouverture', 'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]),
            'accident' => (int) DB::table('line_events')->insertGetId(['line_id' => $this->line->id, 'type' => 'accident', 'event_date' => '1933-12-23', 'description' => 'Accident', 'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]),
            'neutralized' => (int) DB::table('line_events')->insertGetId(['line_id' => $this->line->id, 'type' => 'other', 'event_date' => '1950-01-01', 'source' => 'wikipedia',
                'description' => 'Report partiel (correction sourcée : desserte réduite)', 'created_at' => now(), 'updated_at' => now()]),
            default => 'abc',
        };

        $this->actingAs($this->member())
            ->get("/contribute/event?line={$this->line->slug}&event={$id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('preselectedLine.slug', $this->line->slug)->where('correctedEvent', null));

        if (is_int($id)) {
            $this->actingAs($this->member())->from('/contribute/event')
                ->post('/contribute/event', $this->correction(['corrects_event_id' => $id]))
                ->assertSessionHasErrors('corrects_event_id');
        }
    }

    // ─── Envoi ──────────────────────────────────────────────────────────────

    public function test_a_correction_needs_a_reason(): void
    {
        foreach (['', 'Faux.'] as $reason) {
            $this->actingAs($this->member())->from('/contribute/event')
                ->post('/contribute/event', $this->correction(['correction_reason' => $reason]))
                ->assertSessionHasErrors('correction_reason');
        }
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_too_long_excerpt_is_refused(): void
    {
        $this->actingAs($this->member())->from('/contribute/event')
            ->post('/contribute/event', $this->correction(['source_excerpt' => str_repeat('a', EventContributions::EXCERPT_MAX + 1)]))
            ->assertSessionHasErrors('source_excerpt');
    }

    public function test_a_correction_keeps_the_event_as_it_was_and_publishes_nothing_before_review(): void
    {
        $contribution = $this->submit($this->member());

        $this->assertSame('update', $contribution->action->value);
        $p = $contribution->payload;
        $this->assertEquals(['event_id' => $this->wrongId, 'source' => 'wikipedia', 'type' => 'closure_passenger', 'date' => '1969-01-01', 'precision' => 'year'],
            array_intersect_key($p['corrects'], array_flip(['event_id', 'source', 'type', 'date', 'precision'])));
        $this->assertSame('La source date le report sur la route, la fin des voyageurs est de 1972.', $p['correction_reason']);
        $this->assertSame('fermée au service des voyageurs le 6 mars 1972', $p['source_excerpt']);
        $this->assertSame('closure_passenger', DB::table('line_events')->where('id', $this->wrongId)->value('type'), 'rien de neutralisé avant la relecture');
        $this->assertSame(0, LineEvent::query()->where('source', EventContributions::SOURCE)->count());

        $this->actingAs($this->member('validator'))->get('/validate')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contributions.0.id', $contribution->id)
                ->where('contributions.0.corrected_event.event_id', $this->wrongId));
    }

    public function test_a_new_event_ignores_a_stray_reason(): void
    {
        $contribution = $this->submit($this->member(), ['corrects_event_id' => null]);

        $this->assertSame('create', $contribution->action->value);
        $this->assertNull($contribution->payload['corrects']);
        $this->assertNull($contribution->payload['correction_reason']);
    }

    // ─── Publication ────────────────────────────────────────────────────────

    public function test_two_approvals_publish_the_correction_and_neutralize_the_wrong_event(): void
    {
        $contribution = $this->submit($this->member());

        $this->approveTwice($contribution);

        $contribution->refresh();
        $this->assertSame('applied', $contribution->status->value);
        $this->assertSame([$this->wrongId], $contribution->payload['applied']['corrected_event_ids']);
        $new = LineEvent::query()->where('fingerprint', 'contribution:'.$contribution->id)->firstOrFail();
        $this->assertSame('1972-03-06', $new->event_date->toDateString());
        $this->assertSame('fermée au service des voyageurs le 6 mars 1972', $new->source_quote, 'la citation courte remplace la référence');
        $this->assertStringEndsWith("(contribution n° {$contribution->id}, source : Gare de Saint-Cloud, Wikipédia)", $new->description);
        $wrong = DB::table('line_events')->where('id', $this->wrongId)->first();
        $this->assertSame('other', $wrong->type);
        $this->assertSame("Fermeture aux voyageurs, fait corrigé par la contribution n° {$contribution->id} (correction sourcée : La source date le report sur la route, la fin des voyageurs est de 1972.)", $wrong->description);
        $this->assertNull(EventContributions::correctable($this->line->id, $this->wrongId), 'un fait neutralisé ne se corrige plus');
    }

    public function test_a_reimported_event_is_neutralized_again_by_the_corrections_command(): void
    {
        $contribution = $this->submit($this->member());
        $this->approveTwice($contribution);
        // L'import Wikipédia rejoué efface ses événements et les réinsère : nouvel identifiant, type d'origine.
        DB::table('line_events')->where('id', $this->wrongId)->delete();
        $reimported = (int) DB::table('line_events')->insertGetId([
            'line_id' => $this->line->id, 'type' => 'closure_passenger', 'event_date' => '1969-01-01', 'date_precision' => 'year',
            'description' => 'Fermeture aux voyageurs', 'source' => 'wikipedia', 'section_from' => 'Saint-Cloud', 'section_to' => 'Versailles-Rive-Droite',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unrelated = (int) DB::table('line_events')->insertGetId([
            'line_id' => $this->line->id, 'type' => 'closure_passenger', 'event_date' => '1969-01-01', 'description' => 'Fermeture', 'source' => 'lignes-oubliees',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Fichiers de corrections vides : seules les contributions publiées sont rejouées.
        $this->sandboxStorage();
        File::ensureDirectoryExists($this->sandbox.'/database/data');
        $original = $this->app->databasePath();
        $this->app->useDatabasePath($this->sandbox.'/database');
        $this->beforeApplicationDestroyed(fn () => $this->app->useDatabasePath($original));
        $this->artisan('vff:apply-event-corrections', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('closure_passenger', DB::table('line_events')->where('id', $reimported)->value('type'), 'rien écrit à blanc');

        $this->artisan('vff:apply-event-corrections', ['--line' => $this->line->code])->assertSuccessful();

        $this->assertSame('other', DB::table('line_events')->where('id', $reimported)->value('type'));
        $this->assertSame('closure_passenger', DB::table('line_events')->where('id', $unrelated)->value('type'), 'une autre source garde son fait');
        $this->assertSame('closure_passenger', DB::table('line_events')->where('fingerprint', 'contribution:'.$contribution->id)->value('type'), 'l’événement corrigé n’est jamais neutralisé');
    }

    public function test_replaying_a_correction_never_rewrites_another_other_event_of_the_same_day(): void
    {
        $contribution = $this->submit($this->member());
        $this->approveTwice($contribution);
        // Import rejoué : l'événement visé revient sous un nouvel identifiant, à côté de deux faits `other` de la même
        // source, du même jour et du même départ (un fait neutre et un fait neutralisé par un fichier de corrections).
        DB::table('line_events')->where('id', $this->wrongId)->delete();
        $row = ['line_id' => $this->line->id, 'event_date' => '1969-01-01', 'date_precision' => 'year', 'source' => 'wikipedia',
            'section_from' => 'Saint-Cloud', 'section_to' => 'Versailles-Rive-Droite', 'created_at' => now(), 'updated_at' => now()];
        $reimported = (int) DB::table('line_events')->insertGetId([...$row, 'type' => 'closure_passenger', 'description' => 'Fermeture aux voyageurs']);
        $neutral = (int) DB::table('line_events')->insertGetId([...$row, 'type' => 'other', 'description' => 'Remplacement par des autocars']);
        $byFile = (int) DB::table('line_events')->insertGetId([...$row, 'type' => 'other', 'description' => 'Report partiel (correction sourcée : desserte réduite)']);

        $out = app(EventContributions::class)->replayCorrections($this->line->id);

        $this->assertSame(1, $out['neutralized']);
        $this->assertSame('other', DB::table('line_events')->where('id', $reimported)->value('type'));
        $this->assertSame('Remplacement par des autocars', DB::table('line_events')->where('id', $neutral)->value('description'));
        $this->assertSame('Report partiel (correction sourcée : desserte réduite)', DB::table('line_events')->where('id', $byFile)->value('description'));
        // Rejoué une seconde fois : l'événement déjà neutralisé par la contribution est reconnu, rien d'autre.
        $this->assertSame([$reimported], app(EventContributions::class)->neutralizeCorrected($contribution->refresh()));
    }

    // ─── API GraphQL ────────────────────────────────────────────────────────

    public function test_the_mutation_accepts_a_correction(): void
    {
        $token = app(ApiTokens::class)->create($this->member(), 'Script', true)->plainTextToken;
        $input = $this->correction();
        unset($input['from'], $input['to']);
        $input['portion'] = 'whole';

        $this->withToken($token)->graphQL('mutation ($input: ContributionInput!) { submitContribution(input: $input) { id action payload } }', ['input' => ['event' => $input]])
            ->assertJsonPath('data.submitContribution.action', 'UPDATE')
            ->assertJsonPath('data.submitContribution.payload.corrects.event_id', $this->wrongId)
            ->assertJsonPath('data.submitContribution.payload.source_excerpt', 'fermée au service des voyageurs le 6 mars 1972');

        $this->withToken($token)->graphQL('mutation ($input: ContributionInput!) { submitContribution(input: $input) { id } }', ['input' => ['event' => [...$input, 'correction_reason' => null]]])
            ->assertJsonPath('errors.0.extensions.validation', fn (array $v) => array_key_exists('input.event.correction_reason', $v));
    }
}
