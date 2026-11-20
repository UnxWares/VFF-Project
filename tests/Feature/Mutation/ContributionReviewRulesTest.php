<?php

namespace Tests\Feature\Mutation;

use App\Enums\AuditAction;
use App\Enums\ContributionAction;
use App\Enums\ContributionStatus;
use App\Models\AuditEvent;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\User;
use App\Services\Contributions\ContributionNotPublishable;
use App\Services\Contributions\ContributionReview;
use App\Services\Contributions\ContributionSubmissions;
use App\Services\Contributions\PublicationFailure;
use App\Services\Contributions\ReviewOutcome;
use App\Services\Contributions\ReviewResult;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Validation à deux des contributions (ContributionReview), appelée directement : journal d'audit de
 * chaque décision, publication d'une modification par PK à la deuxième approbation, contribution
 * fermée par un autre validateur entre la lecture et le verrou. Écrit pour les tests de mutation
 * (tests/Mutation/Critical).
 */
class ContributionReviewRulesTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use RefreshDatabase;

    private ContributionReview $review;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->review = app(ContributionReview::class);
        $this->author = $this->member('contributor');
    }

    /** Fermeture de la ligne témoin entre deux PK (toute la ligne par défaut) dans l'ère courante. */
    private function segmentContribution(?Line $line = null, int $from = 0, int $to = 50_000): Contribution
    {
        $line ??= $this->line;

        return Contribution::create([
            'user_id' => $this->author->id,
            'type' => 'line_segment',
            'action' => ContributionAction::Update->value,
            'target_type' => Line::class,
            'target_id' => $line->id,
            'status' => ContributionStatus::Pending->value,
            'payload' => [
                'line_code' => $line->code,
                'era_year' => 2030,
                'pk_start_m' => $from,
                'pk_end_m' => $to,
                'changes' => ['status' => 'abandoned', 'max_speed_kmh' => 40, 'name' => 'colonne hors liste'],
                'sources' => ['Arrêté du 12/03/1998'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function auditContext(AuditAction $action): array
    {
        return AuditEvent::query()->where('action', $action->value)->sole()->context;
    }

    public function test_two_approvals_publish_a_change_by_kilometre_point(): void
    {
        $contribution = $this->segmentContribution();
        [$first, $second] = [$this->member('validator'), $this->member('validator')];

        $approved = $this->review->approve($first, $contribution->id);
        $this->assertSame([ReviewOutcome::Approved, 1, 2], [$approved->outcome, $approved->approvals, $approved->threshold]);
        $this->assertEquals(['type' => 'line_segment', 'approvals' => 1, 'threshold' => 2], $this->auditContext(AuditAction::ContributionApproved));
        $this->assertSame(ContributionStatus::Approved, $contribution->fresh()?->status);

        $applied = $this->review->approve($second, $contribution->id);
        $this->assertSame([ReviewOutcome::Applied, 2, 2], [$applied->outcome, $applied->approvals, $applied->threshold]);
        $this->assertEquals(['type' => 'line_segment', 'approvals' => 2, 'threshold' => 2], $this->auditContext(AuditAction::ContributionApplied));

        $fresh = $contribution->refresh();
        $this->assertSame(ContributionStatus::Applied, $fresh->status);
        $this->assertSame($second->id, $fresh->reviewed_by);
        // Seules les colonnes de la liste blanche changent, dans l'ère visée seulement.
        $segments = DB::table('line_segments')->where('line_id', $this->line->id)->orderBy('era_id')->get(['era_id', 'status', 'max_speed_kmh']);
        $this->assertSame([
            [$this->pastEra->id, 'in_service', 120],
            [$this->currentEra->id, 'abandoned', 40],
        ], $segments->map(fn (object $s): array => [(int) $s->era_id, $s->status, (int) $s->max_speed_kmh])->all());
    }

    public function test_the_same_reviewer_cannot_approve_twice(): void
    {
        $contribution = $this->segmentContribution();
        $reviewer = $this->member('validator');

        $this->review->approve($reviewer, $contribution->id);
        $again = $this->review->approve($reviewer, $contribution->id);

        $this->assertSame([ReviewOutcome::AlreadyApproved, 1], [$again->outcome, $again->approvals]);
        $this->assertSame(ContributionStatus::Approved, $contribution->fresh()?->status);
    }

    public function test_a_rejection_is_audited_without_its_reason(): void
    {
        $contribution = $this->segmentContribution();
        $this->review->approve($this->member('validator'), $contribution->id);

        $rejected = $this->review->reject($this->member('validator'), $contribution->id, 'Source introuvable');

        $this->assertSame([ReviewOutcome::Rejected, 1], [$rejected->outcome, $rejected->approvals]);
        $this->assertEquals(['type' => 'line_segment', 'previous_status' => 'approved', 'with_reason' => true],
            $this->auditContext(AuditAction::ContributionRejected));
        $this->assertSame('Source introuvable', $contribution->fresh()?->review_notes);
    }

    public function test_a_contribution_closed_after_it_was_read_is_not_approved(): void
    {
        $contribution = $this->segmentContribution();
        // Un autre validateur refuse la contribution juste après sa première lecture (avant le verrou).
        $closed = false;
        Contribution::retrieved(function (Contribution $read) use (&$closed): void {
            if (! $closed) {
                $closed = true;
                DB::table('contributions')->where('id', $read->id)->update(['status' => ContributionStatus::Rejected->value]);
            }
        });

        $result = $this->review->approve($this->member('validator'), $contribution->id);

        $this->assertSame(ReviewOutcome::Closed, $result->outcome);
        $this->assertSame(ContributionStatus::Rejected, $result->contribution?->status);
        $this->assertSame(0, $this->review->approvalCount($contribution->id));
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_a_closed_contribution_is_neither_approved_nor_rejected(): void
    {
        $contribution = $this->segmentContribution();
        $contribution->update(['status' => ContributionStatus::Applied->value]);

        $this->assertSame(ReviewOutcome::Closed, $this->review->approve($this->member('validator'), $contribution->id)->outcome);
        $this->assertSame(ReviewOutcome::Closed, $this->review->reject($this->member('validator'), $contribution->id, 'Trop tard')->outcome);
        $this->assertSame(ReviewOutcome::Own, $this->review->reject($this->author, $contribution->id, 'Ma propre contribution')->outcome);
        $this->assertSame(0, $this->review->approvalCount($contribution->id));
    }

    // ─── Publication ────────────────────────────────────────────────────────

    /** Approuvée par deux validateurs. */
    private function approveTwice(Contribution $contribution): ReviewResult
    {
        $this->review->approve($this->member('validator'), $contribution->id);

        return $this->review->approve($this->member('validator'), $contribution->id);
    }

    /** Ligne de deux tronçons dans l'ère courante, chacun de 3,7 km environ (0,05° de longitude à 48° N). */
    private function twoSectionLine(): Line
    {
        $line = Line::factory()->create(['code' => '900100', 'slug' => 'rfn-900100', 'name' => 'Ligne témoin en deux tronçons']);
        foreach ([[2.00, 2.05], [2.05, 2.10]] as [$from, $to]) {
            LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $this->currentEra->id, 'status' => 'in_service', 'max_speed_kmh' => 120,
                'geom' => LineString::make([Point::makeGeodetic(48.0, $from), Point::makeGeodetic(48.0, $to)])]);
        }

        return $line;
    }

    /** @return list<string> état des deux tronçons, dans l'ordre */
    private function sectionStatuses(Line $line): array
    {
        return DB::table('line_segments')->where('line_id', $line->id)->orderBy('id')->pluck('status')->all();
    }

    /** @return iterable<string, array{int, int, list<string>}> */
    public static function kilometrePoints(): iterable
    {
        yield 'premier tronçon' => [0, 3000, ['abandoned', 'in_service']];
        yield 'second tronçon' => [4500, 7000, ['in_service', 'abandoned']];
        yield 'PK au-delà de la ligne, ramenés à ses extrémités' => [-500, 20_000, ['abandoned', 'abandoned']];
        yield 'PK avant son origine, ramenés au point de départ' => [-500, -100, ['abandoned', 'in_service']];
        yield 'PK après sa fin, ramenés au point d\'arrivée' => [20_000, 30_000, ['in_service', 'abandoned']];
    }

    /** @param  list<string>  $statuses */
    #[DataProvider('kilometrePoints')]
    public function test_only_the_sections_between_the_kilometre_points_change(int $from, int $to, array $statuses): void
    {
        $line = $this->twoSectionLine();

        $result = $this->approveTwice($this->segmentContribution($line, $from, $to));

        $this->assertSame(ReviewOutcome::Applied, $result->outcome);
        $this->assertSame($statuses, $this->sectionStatuses($line));
    }

    public function test_a_failed_publication_is_reported_and_writes_nothing(): void
    {
        Exceptions::fake();
        $bare = Line::factory()->create(['code' => '900200', 'slug' => 'rfn-900200', 'name' => 'Ligne sans tracé']);
        $contribution = $this->segmentContribution($bare);

        $result = $this->approveTwice($contribution);

        $this->assertSame([ReviewOutcome::Failed, PublicationFailure::NoGeometry], [$result->outcome, $result->failure]);
        $this->assertSame(ContributionStatus::Approved, $contribution->fresh()?->status);
        $this->assertSame(1, $this->review->approvalCount($contribution->id));
        Exceptions::assertReported(ContributionNotPublishable::class);
    }

    public function test_a_published_trace_targets_its_new_line_and_dates_the_publication(): void
    {
        $this->travelTo('2026-11-20 10:00:00');
        $contribution = app(ContributionSubmissions::class)->submitTrace($this->author, [
            'kind' => 'new_line',
            'new_line_name' => 'Ligne de Saint-Sever à Hagetmau',
            'network' => 'secondaire',
            'trace_source' => 'IGN, carte 1950 (SCAN 50 historique)',
            'portions' => [[
                'key' => 'p1',
                'label' => 'Saint-Sever - Audignon',
                'coordinates' => [[-0.5687, 43.7570], [-0.5900, 43.7300], [-0.6012, 43.7125]],
                'events' => [['type' => 'opening', 'precision' => 'day', 'date' => '1890-06-15', 'source' => 'Journal officiel du 16/06/1890']],
            ]],
        ]);

        $result = $this->approveTwice($contribution);

        $fresh = $contribution->fresh();
        $this->assertSame(ReviewOutcome::Applied, $result->outcome);
        $this->assertSame([Line::class, (int) $fresh?->payload['applied']['line_id']], [$fresh?->target_type, (int) $fresh?->target_id]);
        $this->assertSame(now()->toIso8601String(), $fresh?->payload['applied']['at'] ?? null);
        $this->assertSame('Saint-Sever - Audignon', $fresh?->payload['portions'][0]['label'] ?? null, 'le reste de la proposition est gardé');
    }

    public function test_rejection_reason_rules_and_messages_carry_their_bounds(): void
    {
        $this->assertSame(['required', 'string', 'min:5', 'max:1000'], ContributionReview::reasonRules());
        $messages = ContributionReview::reasonMessages('reason');
        $this->assertStringContainsString('5', $messages['reason.min']);
        $this->assertStringContainsString('5', $messages['reason.required']);
        $this->assertStringContainsString('1000', $messages['reason.max']);
    }
}
