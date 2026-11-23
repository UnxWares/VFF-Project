<?php

namespace Tests\Feature\Figures;

use App\Models\Line;
use App\Models\LineEvent;
use App\Services\Companies\CompanyFigures;
use App\Services\CoverageFigures;
use App\Services\Departments\DepartmentFigures;
use App\Services\FiguresVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Chiffres rangés sans expiration (départements, compagnies, couverture) : suivent les données sans planification
 * manuelle. Un changement de données les rend périmés, la dernière valeur reste lue en attendant, et
 * `vff:refresh-stale-figures` ne recalcule que ce qui est périmé.
 */
class StaleFiguresTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        CompanyFigures::forget();
        $this->buildRailNetwork();
    }

    private function changeData(): void
    {
        LineEvent::factory()->create(['line_id' => Line::query()->value('id'), 'type' => 'closure', 'event_date' => '1990-05-01']);
    }

    public function test_figures_never_computed_are_stale(): void
    {
        $this->assertTrue(app(CoverageFigures::class)->isStale());
        $this->assertTrue(app(CompanyFigures::class)->isStale());
        $this->assertTrue(app(DepartmentFigures::class)->isStale());
    }

    public function test_a_refresh_makes_figures_fresh_and_a_data_change_makes_them_stale_again(): void
    {
        $coverage = app(CoverageFigures::class);
        $coverage->refresh();
        $this->assertFalse($coverage->isStale());

        $this->changeData();

        $this->assertTrue($coverage->isStale());
    }

    public function test_the_last_value_is_still_served_while_stale(): void
    {
        $coverage = app(CoverageFigures::class);
        $computedAt = $coverage->refresh()['computed_at'];
        $this->changeData();

        $this->assertTrue($coverage->isStale());
        $this->assertSame($computedAt, $coverage->get()['computed_at']);
    }

    public function test_a_partial_department_computation_does_not_mark_them_fresh(): void
    {
        $departments = app(DepartmentFigures::class);
        $departments->refresh(['75']);

        $this->assertTrue($departments->isStale());
    }

    public function test_the_command_recomputes_only_what_is_stale(): void
    {
        $coverage = app(CoverageFigures::class);
        $coverage->refresh();
        $before = $coverage->get()['computed_at'];
        $this->artisan('vff:refresh-stale-figures', ['--now' => true])->assertSuccessful();
        // Compagnies et départements jamais calculés : recalculés ; couverture à jour : intacte.
        $this->assertFalse(app(CompanyFigures::class)->isStale());
        $this->assertFalse(app(DepartmentFigures::class)->isStale());
        $this->assertNotNull(app(DepartmentFigures::class)->index());
        $this->assertSame($before, $coverage->get()['computed_at']);

        $this->artisan('vff:refresh-stale-figures', ['--now' => true])->expectsOutput('Chiffres à jour.')->assertSuccessful();

        $this->travel(2)->seconds();
        $this->changeData();
        $this->artisan('vff:refresh-stale-figures', ['--now' => true])->assertSuccessful();
        $this->assertFalse($coverage->isStale());
        $this->assertNotSame($before, $coverage->get()['computed_at']);
    }

    public function test_the_command_waits_for_the_data_to_settle_unless_forced(): void
    {
        $this->artisan('vff:refresh-stale-figures')->assertSuccessful();
        $this->assertTrue(app(CoverageFigures::class)->isStale(), 'données vues pour la première fois : on attend');

        $this->travel(11)->minutes();
        $this->artisan('vff:refresh-stale-figures')->assertSuccessful();
        $this->assertFalse(app(CoverageFigures::class)->isStale());
    }

    public function test_dry_run_computes_nothing(): void
    {
        $this->artisan('vff:refresh-stale-figures', ['--dry-run' => true, '--now' => true])->assertSuccessful();

        $this->assertNull(app(DepartmentFigures::class)->index());
        $this->assertNull(app(FiguresVersion::class)->recorded(CoverageFigures::FRESHNESS_KEY));
    }
}
