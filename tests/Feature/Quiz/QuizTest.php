<?php

namespace Tests\Feature\Quiz;

use App\Enums\QuizGame;
use App\Enums\QuizLevel;
use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use App\Services\Quiz\LineQuiz;
use App\Services\Quiz\QuizPick;
use App\Services\Quiz\QuizSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Quiz du rail (/quiz) : tirage par graine, règle des droits des tracés, réponse jamais donnée avant
 * le choix, propositions distinctes et plausibles.
 */
class QuizTest extends TestCase
{
    use RefreshDatabase;

    /** Lignes du vivier (tracé OpenStreetMap, texte Wikipédia), sur une grille de 4 × 3. */
    private const GRID_LINES = 12;

    private Era $current;

    private Era $past;

    /** @var list<Line> */
    private array $lines = [];

    private Line $withheld;

    private Line $mixed;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->current = Era::factory()->current()->create(['year' => 2030]);
        $this->past = Era::factory()->create(['year' => 1930]);

        for ($i = 0; $i < self::GRID_LINES; $i++) {
            $lng = 0.5 + ($i % 4) * 1.5;
            $lat = 44.0 + intdiv($i, 4) * 1.5;
            $line = $this->line("Ligne numéro {$i}", "La ligne numéro {$i} est une ligne de test. Elle relie deux villes.");
            $this->segment($line, $this->current, 'osm', [[$lng, $lat], [$lng + 0.2, $lat + 0.1]], 20000);
            $this->lines[] = $line;
        }
        DB::table('line_events')->insert([
            'line_id' => $this->lines[0]->id, 'type' => 'opening', 'event_date' => '1872-05-01', 'description' => 'Ouverture',
            'confidence' => 'high', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Tracé connu seulement par une source sans licence ouverte : jamais dans le vivier.
        $this->withheld = $this->line('Ligne retenue', 'La ligne retenue a un tracé sans licence ouverte vérifiée.');
        $this->segment($this->withheld, $this->current, 'archeoferroviaire', [[3.0, 46.0], [3.3, 46.2]], 30000);

        // En 2030, un tronçon retenu loin du reste ; en 1930, le tracé complet d'OpenStreetMap.
        $this->mixed = $this->line('Ligne mêlée', 'La ligne mêlée a deux sources selon les ères, dont une retenue.');
        $this->segment($this->mixed, $this->past, 'osm', [[4.0, 45.0], [4.2, 45.1]], 20000);
        $this->segment($this->mixed, $this->current, 'osm', [[4.0, 45.0], [4.2, 45.1]], 20000);
        $this->segment($this->mixed, $this->current, 'archeoferroviaire', [[6.0, 48.0], [6.01, 48.01]], 2000);
    }

    private function line(string $name, string $description): Line
    {
        $line = Line::factory()->create(['name' => $name, 'description' => $description]);
        DB::table('line_sources')->insert([
            'line_id' => $line->id, 'source_type' => 'wikipedia-intro',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode($name).'&oldid=1',
            'fetched_at' => now(), 'fields_provided' => json_encode(['fields' => ['description'], 'title' => $name]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $line;
    }

    /** @param  list<array{0: float, 1: float}>  $points */
    private function segment(Line $line, Era $era, string $source, array $points, int $length): void
    {
        $wkt = 'LINESTRING('.implode(', ', array_map(fn (array $p): string => "{$p[0]} {$p[1]}", $points)).')';
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $era->id, 'status' => 'in_service', 'source' => $source,
            'length_meters' => $length, 'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Série de matériel : image Commons, description Wikidata, lien `$confidence` vers la première ligne. */
    private function material(string $name, string $type, ?int $from, string $confidence = 'high', bool $image = true): Material
    {
        $m = Material::factory()->fromWikidata()->create([
            'name' => $name, 'slug' => str($name)->slug()->toString(), 'type' => $type, 'in_service_from' => $from,
            'description' => "série de locomotives {$name} de la SNCF",
            'provenance' => ['description' => ['source' => 'wikidata', 'ref' => 'Q1#description:fr']],
        ] + ($image ? [] : ['image' => null]));
        DB::table('line_materials')->insert([
            'line_id' => $this->lines[0]->id, 'material_id' => $m->id, 'confidence' => $confidence, 'source' => 'wikidata',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $m;
    }

    private function seedMaterials(): void
    {
        foreach ([['BB 9200', 1957], ['BB 15000', 1971], ['BB 7200', 1976], ['BB 22200', 1976], ['BB 36000', 1996]] as [$name, $from]) {
            $this->material($name, 'locomotive_electrique', $from);
        }
        $this->material('BB 8100', 'locomotive_electrique', 1949, 'low');
        $this->material('BB 25500', 'locomotive_electrique', 1964, 'high', false);
        // Trois autorails : moins de quatre séries du type, pas de question possible.
        foreach (['X 2100', 'X 2800', 'X 4300'] as $name) {
            $this->material($name, 'autorail', 1960);
        }
    }

    private function series(): QuizSeries
    {
        return app(QuizSeries::class);
    }

    /** Version actuelle d'une série (paramètre `v` de l'adresse). */
    private function v(string $game, string $level = 'easy', string $seed = 'abcd1234'): string
    {
        return QuizSeries::version($this->series()->picks(QuizGame::from($game), QuizLevel::from($level), $seed));
    }

    /** Ajoute au vivier une ligne de plus, voisine de la ligne n° `$near` de la grille, et vide le cache. */
    private function addPoolLine(string $name, int $near = 0): Line
    {
        $lng = 0.5 + ($near % 4) * 1.5 + 0.05;
        $lat = 44.0 + intdiv($near, 4) * 1.5 + 0.05;
        $line = $this->line($name, "La {$name} est une ligne de test ajoutée au vivier.");
        $this->segment($line, $this->current, 'osm', [[$lng, $lat], [$lng + 0.2, $lat + 0.1]], 20000);
        Cache::flush();

        return $line;
    }

    public function test_line_pool_keeps_only_redistributable_traces(): void
    {
        $pool = collect(app(LineQuiz::class)->pool());

        $this->assertCount(self::GRID_LINES + 1, $pool);
        $this->assertFalse($pool->contains('id', $this->withheld->id));
        // La ligne mêlée est prise à l'ère 1930, la seule dont tous les tronçons sont redistribuables.
        $this->assertSame(1930, $pool->firstWhere('id', $this->mixed->id)['era_year']);
    }

    public function test_line_pool_follows_the_rights_switch(): void
    {
        config(['api.withhold_unlicensed_geometry' => false]);

        $this->assertTrue(collect(app(LineQuiz::class)->pool())->contains('id', $this->withheld->id));
    }

    public function test_no_withheld_geometry_leaves_in_questions(): void
    {
        $picks = $this->series()->picks(QuizGame::Lines, QuizLevel::Easy, 'abcd1234');
        $questions = $this->series()->questions(QuizGame::Lines, QuizLevel::Easy, 'abcd1234', $picks);

        $this->assertCount(QuizSeries::LENGTH, $questions);
        foreach ($questions as $q) {
            // Le tronçon retenu de la ligne mêlée est à 300 km : s'il sortait, le cadre ferait plus de 300 km.
            $this->assertLessThan(30_000, $q['trace']['m_per_unit'] * LineQuiz::FRAME);
            $this->assertNotEmpty($q['trace']['paths']);
            foreach ($q['trace']['paths'] as $path) {
                foreach ($path as [$x, $y]) {
                    $this->assertGreaterThanOrEqual(0, $x);
                    $this->assertLessThanOrEqual(LineQuiz::FRAME, $x);
                    $this->assertGreaterThanOrEqual(0, $y);
                    $this->assertLessThanOrEqual(LineQuiz::FRAME, $y);
                }
            }
        }
    }

    public function test_same_seed_gives_the_same_series_and_another_seed_differs(): void
    {
        $keys = fn (string $seed): array => array_map(
            fn (QuizPick $p): array => [$p->subject['key'], $p->optionKeys()],
            $this->series()->picks(QuizGame::Lines, QuizLevel::Easy, $seed),
        );

        $first = $keys('abcd1234');
        $this->assertCount(QuizSeries::LENGTH, $first);
        $this->assertEquals($first, $keys('abcd1234'));
        // Vivier recalculé (cache vidé) : même série.
        Cache::flush();
        $this->assertEquals($first, $keys('abcd1234'));
        $this->assertNotEquals($first, $keys('zz99yy88'));
    }

    public function test_options_are_four_distinct_choices_including_the_subject(): void
    {
        $this->seedMaterials();
        foreach ([QuizGame::Lines, QuizGame::Materials] as $game) {
            foreach (QuizLevel::cases() as $level) {
                $positions = [];
                foreach ($this->series()->picks($game, $level, 'seed42') as $pick) {
                    $keys = $pick->optionKeys();
                    $this->assertCount(QuizSeries::OPTIONS, $keys);
                    $this->assertCount(QuizSeries::OPTIONS, array_unique($keys));
                    $this->assertCount(QuizSeries::OPTIONS, array_unique(array_column($pick->options, 'label')));
                    $this->assertContains($pick->subject['key'], $keys);
                    $positions[] = array_search($pick->subject['key'], $keys, true);
                }
                // La bonne réponse ne tient pas toujours la même place.
                $this->assertGreaterThan(1, count(array_unique($positions)), "{$game->value} {$level->value}");
            }
        }
    }

    public function test_hard_level_offers_the_nearest_lines(): void
    {
        $pool = app(LineQuiz::class)->pool();
        foreach ($this->series()->picks(QuizGame::Lines, QuizLevel::Hard, 'abcd1234') as $pick) {
            $expected = array_slice(array_column(app(LineQuiz::class)->candidates($pick->subject, $pool, QuizLevel::Hard), 'key'), 0, 3);
            $others = array_values(array_diff($pick->optionKeys(), [$pick->subject['key']]));
            sort($expected);
            sort($others);
            $this->assertSame($expected, $others);
        }
    }

    public function test_material_quiz_keeps_reliable_series_with_an_image_and_same_type_options(): void
    {
        $this->seedMaterials();
        $picks = $this->series()->picks(QuizGame::Materials, QuizLevel::Hard, 'abcd1234');

        $this->assertCount(5, $picks);
        foreach ($picks as $pick) {
            $types = array_unique(array_column($pick->options, 'type'));
            $this->assertSame(['locomotive_electrique'], $types);
            $this->assertNotContains($pick->subject['label'], ['BB 8100', 'BB 25500', 'X 2100']);
            foreach ($pick->options as $o) {
                $this->assertNotContains($o['label'], ['BB 8100', 'BB 25500']);
            }
        }
    }

    public function test_page_redirects_to_a_full_shareable_address(): void
    {
        $response = $this->get('/quiz?game=lines');

        $response->assertRedirect();
        $this->assertMatchesRegularExpression('#/quiz\?game=lines&level=easy&seed=[a-f0-9]{8}&v=[a-f0-9]{8}$#', (string) $response->headers->get('Location'));
        $this->get('/quiz?game=lines&level=hard&seed=BAD!')->assertRedirectContains('level=hard');
        // Adresse sans version (lien antérieur à la version) : la version actuelle est ajoutée.
        $this->get('/quiz?game=lines&level=easy&seed=abcd1234')
            ->assertRedirect('/quiz?game=lines&level=easy&seed=abcd1234&v='.$this->v('lines'));
    }

    public function test_page_never_reveals_the_answer_before_the_choice(): void
    {
        $this->seedMaterials();
        foreach (['lines', 'materials'] as $game) {
            $response = $this->get("/quiz?game={$game}&level=easy&seed=abcd1234&v=".$this->v($game))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Quiz')->where('game', $game)->has('questions'));
            $questions = $response->viewData('page')['props']['questions'];
            $json = (string) json_encode($questions);

            foreach (['correct', 'answer', 'fact', 'subject', 'source_url', 'wikimedia.org', '/lines/', '/materials/', 'oldid'] as $needle) {
                $this->assertStringNotContainsString($needle, $json, "{$game} : {$needle}");
            }
            foreach ($questions as $q) {
                $this->assertSame(['index', 'options', ...($game === 'lines' ? ['trace', 'hints'] : ['photo', 'hints'])], array_keys($q));
                foreach ($q['options'] as $o) {
                    $this->assertSame(['key', 'label'], array_keys($o));
                }
            }
        }
    }

    public function test_answer_is_given_only_with_a_choice(): void
    {
        $pick = $this->series()->pick(QuizGame::Lines, QuizLevel::Easy, 'abcd1234', 2);
        $this->assertNotNull($pick);
        $wrong = collect($pick->optionKeys())->first(fn (string $k): bool => $k !== $pick->subject['key']);
        $base = '/quiz/answer?game=lines&level=easy&seed=abcd1234&v='.$this->v('lines').'&q=2';

        $this->getJson($base)->assertUnprocessable()->assertJsonValidationErrors('choice');
        $this->getJson("{$base}&choice=not-an-option")->assertUnprocessable();
        $this->getJson("{$base}&choice={$wrong}")
            ->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('choice', $wrong)
            ->assertJsonPath('answer.key', $pick->subject['key'])
            ->assertJsonPath('answer.url', '/lines/'.$pick->subject['key'])
            ->assertJsonPath('fact.source.name', 'Wikipédia')
            ->assertJsonPath('fact.source.license', 'CC BY-SA 4.0');
        $this->assertStringContainsString('no-store', (string) $this->getJson("{$base}&choice={$wrong}")->headers->get('Cache-Control'));
        $this->getJson("{$base}&choice={$pick->subject['key']}")->assertOk()->assertJsonPath('correct', true);
        // Phrase tirée de la base : première phrase de l'introduction Wikipédia.
        $this->assertStringStartsWith('La ligne', (string) $this->getJson("{$base}&choice={$wrong}")->json('fact.text'));
    }

    public function test_answer_rejects_unknown_questions(): void
    {
        $this->seedMaterials();
        $vm = $this->v('materials');
        $vl = $this->v('lines');
        $this->getJson("/quiz/answer?game=materials&level=easy&seed=abcd1234&v={$vm}&q=7&choice=x")->assertNotFound();
        $this->getJson("/quiz/answer?game=lines&level=easy&seed=abcd1234&v={$vl}&q=10&choice=x")->assertUnprocessable();
        $this->getJson("/quiz/answer?game=trains&level=easy&seed=abcd1234&v={$vl}&q=0&choice=x")->assertUnprocessable();
        // Sans version : erreur de validation.
        $this->getJson('/quiz/answer?game=lines&level=easy&seed=abcd1234&q=0&choice=x')->assertJsonValidationErrors('v');
    }

    public function test_material_photo_goes_through_a_redirect(): void
    {
        $this->seedMaterials();
        $pick = $this->series()->pick(QuizGame::Materials, QuizLevel::Easy, 'abcd1234', 0);
        $this->assertNotNull($pick);

        $v = $this->v('materials');
        $this->get("/quiz/photo?game=materials&level=easy&seed=abcd1234&v={$v}&q=0")
            ->assertRedirect($pick->subject['photo']['url']);
        $this->get('/quiz/photo?game=lines&level=easy&seed=abcd1234&v='.$this->v('lines').'&q=0')->assertNotFound();
        $this->getJson("/quiz/answer?game=materials&level=easy&seed=abcd1234&v={$v}&q=0&choice={$pick->subject['key']}")
            ->assertOk()
            ->assertJsonPath('fact.source.name', 'Wikidata')
            ->assertJsonPath('photo_source_url', $pick->subject['photo']['source_url']);
    }

    public function test_answers_are_rate_limited(): void
    {
        $pick = $this->series()->pick(QuizGame::Lines, QuizLevel::Easy, 'abcd1234', 0);
        $this->assertNotNull($pick);
        $url = '/quiz/answer?game=lines&level=easy&seed=abcd1234&v='.$this->v('lines')."&q=0&choice={$pick->subject['key']}";
        for ($i = 0; $i < 60; $i++) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson($url)->assertTooManyRequests();
    }

    public function test_page_without_enough_data_offers_no_game(): void
    {
        DB::table('line_segments')->delete();

        $this->get('/quiz?game=lines')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Quiz')
                ->where('game', null)
                ->where('available.lines', false)
                ->where('available.materials', false));
    }

    public function test_parameters_passed_as_arrays_never_give_a_server_error(): void
    {
        $this->seedMaterials();
        $v = $this->v('lines');
        // Page : valeur par défaut (choix du jeu, ou redirection vers une adresse complète).
        $this->get('/quiz?game[]=lines')->assertOk()->assertInertia(fn (Assert $page) => $page->where('game', null));
        $this->get('/quiz?game=lines&level[]=easy&seed=abcd1234')->assertRedirectContains('level=easy');
        $this->get('/quiz?game=lines&level=easy&seed[]=abcd1234')->assertRedirect();
        $this->get('/quiz?game=lines&level=easy&seed=abcd1234&v[]=x')->assertRedirect("/quiz?game=lines&level=easy&seed=abcd1234&v={$v}");
        // Réponse et photo : erreur de validation.
        $base = ['game' => 'lines', 'level' => 'easy', 'seed' => 'abcd1234', 'v' => $v, 'q' => '0', 'choice' => 'x'];
        foreach (['game', 'level', 'seed', 'v', 'q', 'choice'] as $key) {
            $query = http_build_query([$key => [$base[$key]]] + $base);
            $this->getJson("/quiz/answer?{$query}")->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $photo = ['game' => 'materials', 'level' => 'easy', 'seed' => 'abcd1234', 'v' => $this->v('materials'), 'q' => '0'];
        foreach (['game', 'level', 'seed', 'v', 'q', 'index'] as $key) {
            $query = http_build_query([$key => [$photo[$key] ?? '0']] + $photo);
            $status = $this->get("/quiz/photo?{$query}")->getStatusCode();
            $this->assertLessThan(500, $status, $key);
        }
    }

    public function test_a_shared_address_survives_a_pool_change_that_does_not_touch_its_questions(): void
    {
        $picks = fn (): array => array_map(
            fn (QuizPick $p): array => [$p->subject['key'], $p->optionKeys()],
            $this->series()->picks(QuizGame::Lines, QuizLevel::Hard, 'abcd1234'),
        );
        $before = $picks();
        $this->assertCount(QuizSeries::LENGTH, $before);
        $askedKeys = array_column($before, 0);

        // Une ligne entre dans le vivier, voisine d'une ligne qui n'est pas dans la série s'il y en a une.
        $outside = collect($this->lines)->first(fn (Line $l): bool => ! in_array($l->slug, $askedKeys, true));
        $this->addPoolLine('Ligne ajoutée', $outside === null ? 0 : array_search($outside, $this->lines, true));
        $after = $picks();

        // Chaque sujet garde son rang : les sujets d'avant restent dans le même ordre.
        $kept = array_values(array_filter(array_column($after, 0), fn (string $k): bool => in_array($k, $askedKeys, true)));
        $this->assertSame(array_values(array_intersect($askedKeys, $kept)), $kept);
        $this->assertGreaterThanOrEqual(QuizSeries::LENGTH - 1, count($kept));
    }

    public function test_a_changed_series_is_never_judged_and_the_page_says_so(): void
    {
        $old = $this->v('lines', 'hard');
        // Première question dont le sujet est une ligne de la grille.
        $slugs = array_map(fn (Line $l): string => $l->slug, $this->lines);
        $pick = collect($this->series()->picks(QuizGame::Lines, QuizLevel::Hard, 'abcd1234'))
            ->first(fn (QuizPick $p): bool => in_array($p->subject['key'], $slugs, true));
        $this->assertNotNull($pick);

        // La ligne ajoutée est la plus proche de ce sujet : ses propositions changent.
        $this->addPoolLine('Ligne ajoutée', (int) array_search($pick->subject['key'], $slugs, true));
        $this->assertNotSame($old, $this->v('lines', 'hard'));

        // Réponse à une question de l'ancienne série : jamais jugée, même avec l'ancienne bonne réponse.
        $this->getJson("/quiz/answer?game=lines&level=hard&seed=abcd1234&v={$old}&q={$pick->index}&choice={$pick->subject['key']}")
            ->assertStatus(409)
            ->assertJsonPath('stale', true)
            ->assertJsonMissingPath('correct')
            ->assertJsonMissingPath('answer');
        // Page : message et nouvelle série proposée, aucune question.
        $this->get("/quiz?game=lines&level=hard&seed=abcd1234&v={$old}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Quiz')
                ->where('stale', true)
                ->where('game', 'lines')
                ->where('level', 'hard')
                ->missing('questions'));
        // La version actuelle, elle, est jouée normalement.
        $this->get('/quiz?game=lines&level=hard&seed=abcd1234&v='.$this->v('lines', 'hard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('questions', QuizSeries::LENGTH));
    }

    public function test_a_hand_written_text_is_never_attributed_to_wikipedia(): void
    {
        // Ligne déclarée avec un texte rédigé à la main (database/data/lignes_hors_rfn.php) qui a gardé
        // sa source Wikipédia : hors du vivier.
        $slug = LineQuiz::handWrittenSlugs()[0];
        $line = $this->line('Ligne au texte rédigé', 'Texte rédigé à la main pour cette ligne de test, sans Wikipédia.');
        $line->update(['slug' => $slug]);
        $this->segment($line, $this->current, 'osm', [[1.0, 49.0], [1.2, 49.1]], 20000);
        Cache::flush();

        $this->assertFalse(collect(app(LineQuiz::class)->pool())->contains('id', $line->id));
    }

    public function test_first_sentence_and_frame(): void
    {
        $this->assertSame('La ligne de A à B est une ligne française.', LineQuiz::firstSentence('La ligne de A à B est une ligne française. Elle est fermée.'));
        $this->assertSame('Ligne courte. Elle relie A à B par la vallée.', LineQuiz::firstSentence('Ligne courte. Elle relie A à B par la vallée. Fin.'));

        $frame = LineQuiz::frame([[[0, 0], [1000, 500], [1000.2, 500.1], [2000, 0]]], 0, 0, 2000, 500);
        $this->assertSame([[[0, 250], [500, 0], [1000, 250]]], $frame['paths']);
        $this->assertSame(1000, $frame['width']);
        $this->assertSame(250, $frame['height']);
        $this->assertSame(2.0, $frame['m_per_unit']);
    }
}
