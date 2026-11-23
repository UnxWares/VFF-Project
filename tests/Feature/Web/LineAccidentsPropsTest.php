<?php

namespace Tests\Feature\Web;

use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fiche ligne (/lines/{slug}) : propriété `accidents` de la section « Accidents » (date et
 * précision, nature, lieu, bilan, confiance du rattachement à cette ligne), dans l'ordre des
 * dates, avec le nom dans la langue de la page ; liste vide pour une ligne sans accident.
 */
class LineAccidentsPropsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean']);

        $bretigny = Accident::factory()->on('2013-07-12')->toReview()->create([
            'slug' => '2013-07-12-bretigny-sur-orge', 'kind' => 'derailment',
            'name' => ['fr' => 'Accident ferroviaire de Brétigny-sur-Orge', 'en' => 'Brétigny-sur-Orge train crash', 'de' => 'Eisenbahnunfall von Brétigny-sur-Orge'],
            'commune_name' => 'Brétigny-sur-Orge', 'department_code' => '91', 'deaths' => 7, 'injuries' => 192,
        ]);
        $etampes = Accident::factory()->on('1908-06-01')->create([
            'slug' => '1908-06-01-etampes', 'kind' => 'collision', 'name' => ['fr' => 'Collision à Étampes'],
            'commune_name' => 'Étampes', 'department_code' => '91', 'deaths' => 3, 'injuries' => null,
        ]);
        $this->link($bretigny, 'high', true);
        $this->link($etampes, 'low', false);

        // Accident d'une autre ligne : absent de la fiche.
        $other = Line::factory()->create(['slug' => 'rfn-830000']);
        $this->link(Accident::factory()->create(['slug' => '1950-01-01-ailleurs']), 'high', true, $other);
    }

    private function link(Accident $accident, string $confidence, bool $primary, ?Line $line = null): void
    {
        AccidentLine::query()->forceCreate([
            'accident_id' => $accident->id, 'line_id' => ($line ?? $this->line)->id, 'is_primary' => $primary,
            'method' => 'proximity', 'confidence' => $confidence, 'score' => 0.5,
        ]);
    }

    public function test_line_sheet_lists_its_accidents_in_date_order(): void
    {
        $this->getWithDeferredProps('/lines/rfn-570000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Show')
                ->has('accidents', 2)
                ->where('accidents.0', [
                    'slug' => '1908-06-01-etampes', 'name' => 'Collision à Étampes', 'date' => '1908-06-01',
                    'date_precision' => 'day', 'kind' => 'collision', 'place' => 'Étampes', 'department' => '91',
                    'deaths' => 3, 'injuries' => null, 'confidence' => 'low', 'is_primary' => false, 'to_review' => false,
                ])
                ->where('accidents.1.slug', '2013-07-12-bretigny-sur-orge')
                ->where('accidents.1.confidence', 'high')
                ->where('accidents.1.to_review', true)
                ->where('accidents.1.injuries', 192));
    }

    public function test_accident_names_follow_the_page_language_with_a_french_fallback(): void
    {
        $this->withCookie('locale', 'en')
            ->getWithDeferredProps('/lines/rfn-570000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accidents.0.name', 'Collision à Étampes')
                ->where('accidents.1.name', 'Brétigny-sur-Orge train crash'));
    }

    public function test_line_without_accident_gets_an_empty_list(): void
    {
        Line::factory()->create(['slug' => 'rfn-999000']);

        $this->getWithDeferredProps('/lines/rfn-999000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('accidents', []));
    }
}
