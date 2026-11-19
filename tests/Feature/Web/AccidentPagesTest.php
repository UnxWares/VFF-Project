<?php

namespace Tests\Feature\Web;

use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pages /accidents (liste chronologique filtrable) et /accidents/{slug} (fiche) : propriétés
 * Inertia, filtres lus sans jamais d'erreur, nom dans la langue de la page, fiche inconnue.
 */
class AccidentPagesTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = Line::factory()->create(['slug' => 'rfn-679000', 'code' => '679000', 'name' => 'Ligne de Perpignan à Villefranche-Vernet-les-Bains']);
        $millas = Accident::factory()->on('2017-12-14')->create([
            'slug' => '2017-12-14-millas', 'kind' => 'level_crossing', 'department_code' => '66', 'commune_name' => 'Millas',
            'name' => ['fr' => 'Accident de Millas', 'en' => 'Millas train-bus collision', 'de' => 'Busunfall von Millas'],
            'deaths' => 6, 'injuries' => 14,
        ]);
        AccidentLine::query()->forceCreate(['accident_id' => $millas->id, 'line_id' => $this->line->id, 'is_primary' => true,
            'method' => 'proximity', 'confidence' => 'high', 'score' => 0.9]);
        Accident::factory()->on('1891-07-26')->create(['slug' => '1891-07-26-saint-mande', 'kind' => 'collision', 'department_code' => '94']);
    }

    public function test_list_with_filters_and_facets(): void
    {
        $this->withHeader('Accept-Language', 'fr')->get('/accidents?decade=2010&dept=66&kind=level_crossing&line=rfn-679000&sort=-date')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accidents/Index')
                ->where('accidents.total', 1)
                ->where('accidents.data.0.slug', '2017-12-14-millas')
                ->where('accidents.data.0.name', 'Accident de Millas')
                ->where('accidents.data.0.line.code', '679000')
                ->where('accidents.data.0.line.confidence', 'high')
                ->where('filters', ['q' => '', 'decade' => 2010, 'kind' => 'level_crossing', 'dept' => '66', 'line' => 'rfn-679000', 'sort' => '-date'])
                ->where('facets.decades', [['decade' => 1890, 'count' => 1], ['decade' => 2010, 'count' => 1]])
                ->where('facets.kinds.collision', 1)
                ->where('facets.departments.0', ['code' => '66', 'name' => 'Pyrénées-Orientales', 'count' => 1])
                ->where('facets.lines.0.slug', 'rfn-679000'));
    }

    public function test_records_to_review_stay_in_the_public_list_with_their_status(): void
    {
        Accident::factory()->on('1950-01-02')->toReview('location_commune_only', 'Millas')->create(['slug' => '1950-01-02-millas', 'kind' => 'derailment']);

        $this->get('/accidents')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('accidents.total', 3)
            ->where('accidents.data.1.slug', '1950-01-02-millas')
            ->where('accidents.data.1.review_status', 'to_review'));
        $this->get('/accidents/1950-01-02-millas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('accident.review.status', 'to_review')
            ->where('accident.review.notes.0.code', 'location_commune_only'));
    }

    public function test_unknown_filter_values_are_ignored_never_an_error(): void
    {
        $this->get('/accidents?decade=1234&kind=meteor&dept=zz&line=nope&sort=bad&q[]=x&page=abc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accidents.total', 2)
                ->where('filters', ['q' => '', 'decade' => null, 'kind' => null, 'dept' => null, 'line' => null, 'sort' => 'date'])
                ->where('accidents.data.0.slug', '1891-07-26-saint-mande'));
    }

    public function test_record_page_gets_the_api_representation(): void
    {
        $this->get('/accidents/2017-12-14-millas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accidents/Show')
                ->where('accident.slug', '2017-12-14-millas')
                ->where('accident.name.en', 'Millas train-bus collision')
                ->where('accident.lines.0.line.slug', 'rfn-679000')
                ->has('accident.sources')
                ->has('accident.review.notes')
                ->has('accident.place.lat'));
    }

    public function test_unknown_record_is_404(): void
    {
        $this->get('/accidents/1900-01-01-nulle-part')->assertNotFound();
    }

    public function test_english_page_lists_english_names(): void
    {
        $this->withCookie('locale', 'en')->get('/accidents?q=millas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('accidents.data.0.name', 'Millas train-bus collision'));
    }
}
