<?php

namespace Tests\Feature\Contributions;

use App\Models\Contribution;
use App\Models\User;
use App\Services\Api\ApiTokens;
use App\Services\Contributions\ContributionLimits;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsRoles;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Plafond quotidien des propositions (ContributionLimits::DAILY, 30 par jour civil et par compte,
 * site et API GraphQL ensemble) : message clair et traduit, rien d'enregistré au-delà, validateurs et
 * administrateurs exemptés, compteur remis à zéro le lendemain.
 */
class DailyContributionLimitTest extends TestCase
{
    use ActsAsRoles;
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const SUBMIT = 'mutation ($input: ContributionInput!) { submitContribution(input: $input) { id status } }';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        config(['api.rate_limits.member' => 1200]);
    }

    /** @return array<string, mixed> */
    private function event(): array
    {
        return [
            'line_slug' => $this->line->slug,
            'type' => 'electrification',
            'precision' => 'year',
            'date' => '1976',
            'portion' => 'whole',
            'source_kind' => 'print',
            'source_title' => 'La Vie du Rail, n° 1550, p. 12',
        ];
    }

    private function alreadySent(User $user, int $count, ?DateTimeInterface $at = null): void
    {
        Contribution::factory()->count($count)->create(['user_id' => $user->id, 'created_at' => $at ?? now()]);
    }

    public function test_the_31st_proposal_of_the_day_is_refused_with_a_translated_message(): void
    {
        $member = $this->member();
        $this->alreadySent($member, ContributionLimits::DAILY - 1);

        $this->actingAs($member)->post('/contribute/event', $this->event())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ContributionLimits::DAILY, Contribution::query()->where('user_id', $member->id)->count());

        $this->withCookie('locale', 'fr')->actingAs($member)->post('/contribute/event', $this->event())
            ->assertSessionHasErrors(['daily_limit' => 'Vous avez envoyé 30 propositions aujourd’hui, le maximum par jour. Vous pourrez en envoyer d’autres demain : les validateurs ont ainsi le temps de toutes les relire.']);
        $this->withCookie('locale', 'en')->actingAs($member)->post('/contribute/event', $this->event())
            ->assertSessionHasErrors(['daily_limit' => 'You have sent 30 proposals today, the daily maximum. You can send more tomorrow, which gives validators time to review them all.']);
        $this->assertSame(ContributionLimits::DAILY, Contribution::query()->where('user_id', $member->id)->count(), 'rien de plus enregistré');
    }

    public function test_yesterday_proposals_do_not_count(): void
    {
        $member = $this->member();
        $this->alreadySent($member, ContributionLimits::DAILY, now()->subDay()->endOfDay());

        $this->actingAs($member)->post('/contribute/event', $this->event())->assertRedirect()->assertSessionHasNoErrors();
    }

    /** @return array<string, array{0: string}> */
    public static function reviewers(): array
    {
        return ['validateur' => ['validator'], 'administrateur' => ['admin']];
    }

    #[DataProvider('reviewers')]
    public function test_validators_and_administrators_are_exempt(string $role): void
    {
        $reviewer = $this->member($role);
        $this->alreadySent($reviewer, ContributionLimits::DAILY);

        $this->assertFalse(ContributionLimits::dailyLimitReached($reviewer));
        $this->actingAs($reviewer)->post('/contribute/event', $this->event())->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_the_graphql_mutation_shares_the_same_daily_counter(): void
    {
        $member = $this->member();
        $token = app(ApiTokens::class)->create($member, 'Script', true)->plainTextToken;
        $this->alreadySent($member, ContributionLimits::DAILY - 1);

        $this->withToken($token)->graphQL(self::SUBMIT, ['input' => ['event' => $this->event()]])
            ->assertJsonPath('data.submitContribution.status', 'PENDING');
        $this->withToken($token)->graphQL(self::SUBMIT, ['input' => ['event' => $this->event()]])
            ->assertJsonPath('errors.0.extensions.code', 'daily_limit_reached')
            ->assertJsonPath('errors.0.extensions.limit', ContributionLimits::DAILY);
        $this->assertSame(ContributionLimits::DAILY, Contribution::query()->where('user_id', $member->id)->count());

        // Le site voit le même compteur.
        $this->actingAs($member)->post('/contribute/event', $this->event())->assertSessionHasErrors('daily_limit');
    }
}
