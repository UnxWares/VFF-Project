<?php

namespace Tests\Feature\Lines;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Page plein écran du schéma des voies (`/lines/{slug}/voies`, LineTrackLayoutPageController) : la page ne porte que
 * l'identité de la ligne, le schéma est lu par l'API track-layout ; une ligne inconnue rend 404.
 */
class LineTrackLayoutPageTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_page_renders_the_line_identity_only(): void
    {
        $this->get('/lines/'.$this->line->slug.'/voies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Tracks')
                ->where('line.slug', $this->line->slug)
                ->where('line.code', $this->line->code)
                ->where('line.name', $this->line->name)
                ->missing('layout')
            );
    }

    public function test_route_is_named_and_unknown_line_is_not_found(): void
    {
        $this->assertSame(url('/lines/'.$this->line->slug.'/voies'), route('lines.tracks', $this->line->slug));
        $this->get('/lines/ligne-inexistante/voies')->assertNotFound();
    }

    public function test_page_stays_within_its_query_budget(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->get('/lines/'.$this->line->slug.'/voies')->assertOk();

        // La ligne (liaison de la route) et la session : aucune lecture des voies, faite par l'API à la demande.
        $this->assertLessThanOrEqual(3, count($queries), count($queries)." requêtes\n".implode("\n", $queries));
        $this->assertEmpty(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'rinf_')));
    }
}
