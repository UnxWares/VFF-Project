<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Instance fraîchement migrée, sans aucune donnée importée (cas d'un
 * contributeur qui vient de cloner le dépôt) : aucune page ne doit planter.
 */
class EmptyDatabaseSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function pages(): array
    {
        return [
            'accueil' => ['/'],
            'lignes' => ['/lines'],
            'matériels' => ['/materials'],
            'crédits médias' => ['/credits/media'],
            'recherche API' => ['/api/v1/search?q=paris'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders_without_data(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    public function test_maps_without_eras_falls_back_to_2030(): void
    {
        $this->get('/maps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Maps')
                ->where('eras', [])
                ->where('currentEraYear', 2030)
                ->where('dataVersion', 'v0')
            );
    }
}
