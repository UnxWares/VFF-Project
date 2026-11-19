<?php

namespace Tests\Feature\Web;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Annuaire /materials : recherche côté serveur (autres noms compris), catégorie, tri, pagination
 * et vignettes ; une valeur inconnue dans l'adresse est ignorée.
 */
class MaterialDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Material::query()->delete();
    }

    public function test_search_finds_a_class_by_one_of_its_other_names(): void
    {
        Material::factory()->create(['slug' => 'x-3800', 'name' => 'X 3800', 'type' => 'autorail', 'designations' => ['Autorail Picasso', 'Picasso']]);
        Material::factory()->create(['slug' => 'x-2800', 'name' => 'X 2800', 'type' => 'autorail', 'designations' => ['Mobylette']]);

        $this->get('/materials?q=picasso')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Materials/Index')
                ->where('materials.total', 1)
                ->where('materials.data.0.slug', 'x-3800')
                ->where('materials.data.0.designations', ['Autorail Picasso', 'Picasso'])
                ->where('filters.q', 'picasso')
                ->where('counts', [['type' => 'autorail', 'count' => 1]])
                ->where('overall', 2));
    }

    public function test_search_ignores_accents_and_case(): void
    {
        Material::factory()->create(['slug' => 'z-1', 'name' => 'Z 1', 'type' => 'automotrice', 'designations' => ['Élisabeth']]);

        $this->get('/materials?q=ELISABETH')
            ->assertInertia(fn (Assert $page) => $page->where('materials.total', 1));
    }

    public function test_category_filter_keeps_counts_of_every_category(): void
    {
        Material::factory()->create(['slug' => 't-1', 'name' => 'Citadis', 'type' => 'tramway']);
        Material::factory()->create(['slug' => 's-1', 'name' => 'Draisine', 'type' => 'engin_service']);
        Material::factory()->create(['slug' => 's-2', 'name' => 'Chasse-neige', 'type' => 'engin_service']);

        $this->get('/materials?type=engin_service')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'engin_service')
                ->where('materials.total', 2)
                ->where('counts', [['type' => 'tramway', 'count' => 1], ['type' => 'engin_service', 'count' => 2]]));
    }

    public function test_sort_by_entry_into_service_puts_unknown_years_last(): void
    {
        Material::factory()->create(['slug' => 'a', 'name' => 'A', 'in_service_from' => null, 'in_service_to' => null]);
        Material::factory()->create(['slug' => 'b', 'name' => 'B', 'in_service_from' => 1970]);
        Material::factory()->create(['slug' => 'c', 'name' => 'C', 'in_service_from' => 1930]);

        $this->get('/materials?sort=in_service_from')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'in_service_from')
                ->where('materials.data', fn (Collection $rows) => $rows->pluck('slug')->all() === ['c', 'b', 'a']));
    }

    public function test_unknown_values_fall_back_to_defaults(): void
    {
        Material::factory()->create(['slug' => 'a', 'name' => 'A']);

        $this->get('/materials?type=fusee&sort=drop%20table&page=999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['q' => '', 'type' => '', 'sort' => 'name'])
                ->where('materials.total', 1));
    }

    public function test_rows_carry_a_light_thumbnail_and_the_number_of_lines(): void
    {
        $material = Material::factory()->fromWikidata()->create(['slug' => 'bb-15000', 'name' => 'BB 15000']);
        LineMaterial::create(['line_id' => Line::factory()->create()->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'high']);

        $this->get('/materials')
            ->assertInertia(fn (Assert $page) => $page
                ->where('materials.data.0.lines_count', 1)
                ->where('materials.data.0.thumb.url', fn (string $url) => str_contains($url, '/250px-'))
                ->where('materials.data.0.units_built', 65));
    }

    public function test_the_directory_is_paginated(): void
    {
        Material::factory()->count(50)->create();

        $this->get('/materials?page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('materials.current_page', 2)
                ->where('materials.last_page', 2)
                ->has('materials.data', 2));
    }
}
