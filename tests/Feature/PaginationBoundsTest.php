<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

/**
 * Numéro de page des listes du site (`?page=`, paginate()) : un très grand nombre ne fait plus déborder le
 * décalage SQL ((page - 1) × taille de page, plus grand qu'un entier : erreur 500). La page est bornée à
 * AppServiceProvider::MAX_PAGE et répond comme toute page au-delà de la dernière : vide.
 */
class PaginationBoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_huge_page_number_on_the_line_directory_is_an_empty_page_not_an_error(): void
    {
        Line::factory()->create();

        $this->get('/lines?page=922337203685477580')->assertOk();
    }

    public function test_the_current_page_is_clamped_and_invalid_values_fall_back_to_the_first_page(): void
    {
        foreach (['922337203685477580' => AppServiceProvider::MAX_PAGE, '3' => 3, '0' => 1, '-4' => 1, 'abc' => 1, '1e999' => 1] as $raw => $expected) {
            $this->app['request']->query->set('page', $raw);
            $this->assertSame($expected, Paginator::resolveCurrentPage(), "page={$raw}");
        }
    }
}
