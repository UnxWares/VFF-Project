<?php

namespace Tests\Feature\Quiz;

use App\Enums\QuizGame;
use App\Enums\QuizLevel;
use App\Models\Accident;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\NetworkChronology;
use App\Services\Quiz\FactQuiz;
use App\Services\Quiz\QuizLabel;
use App\Services\Quiz\QuizPick;
use App\Services\Quiz\QuizSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Familles de questions tirées des données du jour (DailyFactPool) : ouverture d'une grande gare, système de cabine
 * d'une LGV, date d'électrification au jour, série de matériel d'une ligne, année d'un accident relu. Chaque
 * mauvaise réponse doit être fausse d'après les données, jamais de victimes, jamais de réponse avant le choix.
 */
class DailyFactQuizTest extends TestCase
{
    use RefreshDatabase;

    private Line $lgv;

    /** @var list<Material> */
    private array $locos = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->lgv = Line::factory()->create(['name' => 'Ligne de Test (LGV)', 'is_lgv' => true]);
        $this->seedStations();
        $this->seedSignalling();
        $this->seedMaterials();
        $this->seedAccidents();
        $this->seedChronology();
        Cache::forget(FactQuiz::POOL_KEY);
    }

    private function station(string $name, int $passengers): Station
    {
        $station = Station::factory()->create(['name' => $name]);
        DB::table('station_attendances')->insert([
            'station_id' => $station->id, 'year' => 2024, 'passengers' => $passengers, 'footfall' => $passengers, 'series' => '2015-2024', 'uic_code' => '8'.random_int(1000000, 9999999),
            'match_method' => 'uic', 'source' => 'sncf', 'dataset' => 'frequentation-gares-archive', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $station;
    }

    private function stationEvent(Station $station, string $date, string $source = 'correction', string $precision = 'day', string $article = 'Gare_de_Test'): void
    {
        DB::table('station_events')->insert([
            'station_id' => $station->id, 'type' => 'opening', 'event_date' => $date, 'date_precision' => $precision, 'source' => $source,
            'source_url' => "https://fr.wikipedia.org/w/index.php?title={$article}&oldid=1", 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedStations(): void
    {
        $this->stationEvent($this->station('Gare Alpha', 5_000_000), '1856-05-29');
        // Autre année dans un autre événement : écartée.
        $beta = $this->station('Gare Beta', 4_000_000);
        $this->stationEvent($beta, '1860-01-02');
        $this->stationEvent($beta, '1865-01-01', 'wikidata', 'year');
        // Même année dans les deux événements : gardée.
        $gamma = $this->station('Gare Gamma', 3_000_000);
        $this->stationEvent($gamma, '1870-03-04');
        $this->stationEvent($gamma, '1870-01-01', 'wikidata', 'year');
        // Station de tramway (article hors « Gare ») : écartée.
        $this->stationEvent($this->station('Arrêt Zeta', 2_500_000), '1900-01-01', 'correction', 'day', 'Zeta_(tramway_d%27Ile-de-France)');
        // Pas une grande gare, ou date non corrigée au jour : écartées.
        $this->stationEvent($this->station('Halte Delta', 90_000), '1880-01-01');
        $this->stationEvent($this->station('Gare Epsilon', 2_000_000), '1890-01-01', 'wikidata', 'year');
    }

    private function signalling(Line $line, string $system, string $date = '1993-05-23', string $precision = 'day', ?string $verification = 'official'): void
    {
        DB::table('line_signalling_events')->insert([
            'line_id' => $line->id, 'system' => $system, 'role' => 'cab', 'event_date' => $date, 'date_precision' => $precision, 'scope' => 'whole_line',
            'confidence' => 'high', 'origin' => 'document', 'source' => 'manual', 'source_title' => 'Bilan LOTI de test', 'source_url' => 'https://example.org/loti.pdf',
            'source_excerpt' => 'Extrait', 'fingerprint' => substr(md5($line->id.$system.$date.$verification), 0, 40), 'verification' => $verification,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedSignalling(): void
    {
        $this->signalling($this->lgv, 'TVM-430');
        // Deux systèmes, système générique, raccordement, sans document au jour : écartées.
        $two = Line::factory()->create(['name' => 'LGV Deux', 'is_lgv' => true]);
        $this->signalling($two, 'TVM-300');
        $this->signalling($two, 'ETCS_L2');
        $generic = Line::factory()->create(['name' => 'LGV Générique', 'is_lgv' => true]);
        $this->signalling($generic, 'TVM');
        $link = Line::factory()->create(['name' => 'Raccordement de Test Fret', 'is_lgv' => true]);
        $this->signalling($link, 'ETCS_L1');
        $loose = Line::factory()->create(['name' => 'LGV Sans Document', 'is_lgv' => true]);
        $this->signalling($loose, 'TVM-300', '1989-01-01', 'year', null);
        $classic = Line::factory()->create(['name' => 'Ligne classique', 'is_lgv' => false]);
        $this->signalling($classic, 'ETCS_L1');
    }

    private function link(Line $line, Material $material, string $confidence = 'medium', array $evidence = [['kind' => 'wikipedia_line_article']]): void
    {
        DB::table('line_materials')->insert([
            'line_id' => $line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Test&oldid=2',
            'confidence' => $confidence, 'evidence' => json_encode($evidence), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedMaterials(): void
    {
        foreach (['BB 100', 'BB 200', 'BB 300', 'BB 400', 'BB 500', 'BB 600'] as $i => $name) {
            $this->locos[] = Material::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'type' => 'locomotive_electrique', 'in_service_from' => 1950 + $i * 5, 'scope' => 'france']);
        }
        $this->link($this->lgv, $this->locos[0], 'high');
        // Série douteuse (essais), série de confiance faible : jamais « la bonne », et jamais une mauvaise réponse.
        $this->link($this->lgv, $this->locos[1], 'medium', [['kind' => 'wikipedia_line_article', 'doubt' => 'trial']]);
        $this->link($this->lgv, $this->locos[2], 'low');
    }

    private function seedAccidents(): void
    {
        $make = fn (string $slug, string $date, string $kind, string $commune, array $extra = []): Accident => Accident::factory()->on($date)->create([
            'slug' => $slug, 'kind' => $kind, 'commune_name' => $commune, 'deaths' => 37,
        ] + $extra);
        $good = $make('1880-02-03-levallois', '1880-02-03', 'collision', 'Levallois-Perret');
        $make('1881-01-01-a-revoir', '1881-01-01', 'derailment', 'Ville A revoir', ['review_status' => 'to_review']);
        $make('2020-01-01-recent', now()->subYears(3)->toDateString(), 'derailment', 'Ville Récente');
        $make('1900-01-01-double-1', '1900-01-01', 'derailment', 'Ville Double');
        $make('1950-01-01-double-2', '1950-01-01', 'derailment', 'Ville Double');
        $make('1947-12-03-attentat', '1947-12-03', 'attack', 'Ville Attentat');
        $alias = $make('1880-02-03-alias', '1880-02-03', 'fire', 'Ville Alias', ['alias_of_id' => $good->id]);
        foreach ([$good, $alias] as $a) {
            DB::table('accident_sources')->insert([
                'accident_id' => $a->id, 'provider' => 'wikipedia', 'role' => 'article', 'title' => 'Collision de test', 'url' => 'https://fr.wikipedia.org/w/index.php?title=Collision_de_Test&oldid=3',
                'licence' => 'CC BY-SA 4.0', 'retrieved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Les autres fiches n'ont aucune source citable de plus : seule la première est interrogeable de toute façon.
        foreach (Accident::query()->whereNotIn('id', [$good->id, $alias->id])->get() as $a) {
            DB::table('accident_sources')->insert([
                'accident_id' => $a->id, 'provider' => 'wikipedia', 'role' => 'article', 'title' => 'Test', 'url' => 'https://fr.wikipedia.org/w/index.php?title=T'.$a->id.'&oldid=4',
                'licence' => 'CC BY-SA 4.0', 'retrieved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedChronology(): void
    {
        $fact = fn (Line $line, string $date, string $precision): array => [
            'id' => 'e'.$line->id.$date, 'kind' => 'electrification', 'traffic' => null, 'year' => (int) substr($date, 0, 4), 'date' => $date, 'precision' => $precision,
            'line' => ['slug' => $line->slug, 'name' => $line->name], 'sections' => [], 'km' => null, 'path' => '/lines/'.$line->slug,
            'regions' => [], 'sources' => [['provider' => 'wikipedia', 'url' => 'https://fr.wikipedia.org/wiki/Test']], 'shown' => true,
        ];
        $other = Line::factory()->create(['name' => 'Ligne électrifiée à l\'année']);
        Cache::forever(NetworkChronology::CACHE_KEY.':meta', ['first_year' => 1827]);
        Cache::forever(NetworkChronology::CACHE_KEY.':decade:1960', [$fact($this->lgv, '1962-05-12', 'day'), $fact($other, '1965-01-01', 'year')]);
    }

    /** @return array<string, array<string, mixed>> */
    private function pool(): array
    {
        return array_column(app(FactQuiz::class)->pool(), null, 'id');
    }

    /** @return list<array<string, mixed>> */
    private function topic(string $topic): array
    {
        return array_values(array_filter($this->pool(), fn (array $s): bool => $s['topic'] === $topic));
    }

    public function test_station_openings_need_a_big_station_and_a_corrected_day_with_one_single_year(): void
    {
        $labels = array_column($this->topic('station_opening_year'), 'label', 'title');
        ksort($labels);

        $this->assertSame(['Gare Alpha' => '1856', 'Gare Gamma' => '1870'], $labels);
        $alpha = array_values(array_filter($this->topic('station_opening_year'), fn (array $s): bool => $s['title'] === 'Gare Alpha'))[0];
        $this->assertSame('1856-05-29', $alpha['data']['date']);
        $this->assertStringContainsString('wikipedia.org', $alpha['sources'][0]['url']);
        $wrong = $this->quiz()->candidates($alpha, $this->quiz()->pool(), QuizLevel::Hard);
        $this->assertNotContains('1856', array_column($wrong, 'label'));
    }

    public function test_a_station_with_a_namesake_is_left_out_and_namesakes_are_counted_once(): void
    {
        // Une autre gare du même nom (casse et espaces au bord mis à part) : la question ne saurait pas laquelle.
        Station::factory()->create(['name' => ' GARE GAMMA']);
        DB::enableQueryLog();

        $labels = array_column($this->topic('station_opening_year'), 'label', 'title');

        $this->assertSame(['Gare Alpha' => '1856'], $labels);
        // Homonymes comptés en une passe sur toutes les gares, et non par gare candidate (1,1 s sur la base complète).
        $read = array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql): bool => str_contains($sql, 'station_attendances a ON')));
        $this->assertCount(1, $read);
        $this->assertStringContainsString('COUNT(*) OVER (PARTITION BY lower(btrim(name)))', $read[0]);
        $this->assertStringNotContainsString('FROM stations t WHERE', $read[0]);
    }

    public function test_cab_signalling_keeps_one_precise_system_documented_at_the_day(): void
    {
        $subjects = $this->topic('cab_signalling');

        $this->assertCount(1, $subjects);
        $this->assertSame('TVM-430', $subjects[0]['label']);
        $this->assertSame('1993-05-23', $subjects[0]['data']['since']);
        $this->assertSame('Bilan LOTI de test', $subjects[0]['sources'][0]['name']);
        $wrong = $this->quiz()->candidates($subjects[0], $this->quiz()->pool(), QuizLevel::Hard);
        $this->assertSame(['TVM-300', 'ETCS_L1', 'ETCS_L2'], array_column($wrong, 'label'));
        $this->assertSame('TVM 300', QuizLabel::of($wrong[0], 'fr'));
        $this->assertSame('ETCS Level 2', QuizLabel::of($wrong[2], 'en'));
    }

    public function test_electrification_with_a_day_asks_for_the_date_and_the_wrong_dates_are_other_dates(): void
    {
        $this->assertCount(1, $this->topic('electrification_date'));
        $subject = $this->topic('electrification_date')[0];
        $this->assertSame('1962-05-12', $subject['label']);
        // Une électrification connue à l'année reste une question d'année.
        $this->assertCount(1, $this->topic('electrification_year'));

        // Degré 3 : pas au niveau facile.
        $this->assertSame([], $this->quiz()->candidates($subject, $this->quiz()->pool(), QuizLevel::Easy));
        $wrong = $this->quiz()->candidates($subject, $this->quiz()->pool(), QuizLevel::Hard);
        $this->assertGreaterThanOrEqual(3, count($wrong));
        foreach ($wrong as $o) {
            $this->assertNotSame('1962-05-12', $o['label']);
            $this->assertLessThanOrEqual(6, (int) abs(Carbon::parse($o['date'])->diffInMonths(Carbon::parse('1962-05-12'))), $o['label']);
            $this->assertSame($o['label'], $o['date']);
        }
        $this->assertSame('12 mai 1962', QuizLabel::date('1962-05-12', 'fr'));
        $this->assertSame('1er mars 1962', QuizLabel::date('1962-03-01', 'fr'));
        $this->assertSame('12 May 1962', QuizLabel::date('1962-05-12', 'en'));
        $this->assertSame('12. Mai 1962', QuizLabel::date('1962-05-12', 'de'));
    }

    public function test_line_materials_are_cited_series_and_wrong_ones_have_no_link_with_the_line(): void
    {
        $subjects = $this->topic('line_material');

        $this->assertCount(1, $subjects);
        $this->assertSame('BB 100', $subjects[0]['label']);
        $others = $subjects[0]['data']['others'];
        // Ni la série douteuse ni la série de confiance faible ne sont proposées : l'une et l'autre sont liées à la ligne.
        $this->assertSame(['BB 400', 'BB 500', 'BB 600'], $others);
        $wrong = $this->quiz()->candidates($subjects[0], $this->quiz()->pool(), QuizLevel::Hard);
        $this->assertSame($others, array_column($wrong, 'label'));
        $this->assertSame('/materials/bb-100', $subjects[0]['url']);
    }

    public function test_accidents_are_proofread_published_old_unique_and_never_name_victims(): void
    {
        $subjects = $this->topic('accident_year');

        $this->assertSame(['Levallois-Perret'], array_column($subjects, 'title'));
        $subject = $subjects[0];
        $this->assertSame('1880', $subject['label']);
        $this->assertSame('/accidents/1880-02-03-levallois', $subject['url']);
        $this->assertArrayNotHasKey('deaths', $subject['data']);
        $this->assertStringNotContainsString('37', (string) json_encode($subject));
        $wrong = $this->quiz()->candidates($subject, $this->quiz()->pool(), QuizLevel::Hard);
        $this->assertNotContains('1880', array_column($wrong, 'label'));
    }

    public function test_answers_cite_sources_link_the_page_and_exist_in_every_language(): void
    {
        $topics = ['station_opening_year', 'cab_signalling', 'electrification_date', 'line_material', 'accident_year'];
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            foreach ($topics as $topic) {
                foreach ($this->topic($topic) as $subject) {
                    $quiz = $this->quiz();
                    $options = [$subject, ...array_slice($quiz->candidates($subject, $quiz->pool(), QuizLevel::Hard), 0, 3)];
                    $pick = new QuizPick(0, $subject, $options);
                    $question = $quiz->question($pick);
                    $reveal = $quiz->revealPick($pick);

                    $this->assertStringNotContainsString('quiz.facts', $question['prompt'], "{$locale} {$topic}");
                    $this->assertStringNotContainsString('quiz.facts', $reveal['fact']['text'], "{$locale} {$topic}");
                    $this->assertStringNotContainsString('accidents.names', (string) $question['subject'], "{$locale} {$topic}");
                    $this->assertNotEmpty($reveal['fact']['sources'], $topic);
                    $this->assertNotEmpty($reveal['answer']['url'], $topic);
                    $this->assertNotSame('', $reveal['answer']['label']);
                    foreach ($pick->publicOptions() as $o) {
                        $this->assertNotSame('', $o['label']);
                        $this->assertStringNotContainsString('quiz.facts', $o['label']);
                    }
                }
            }
        }
        app()->setLocale('en');
        $accident = $this->topic('accident_year')[0];
        $text = $this->quiz()->revealPick(new QuizPick(0, $accident, [$accident]))['fact']['text'];
        $this->assertSame('Collision at Levallois-Perret, on 3 February 1880.', $text);
    }

    public function test_a_series_never_reveals_the_answer_and_every_new_wrong_option_is_false(): void
    {
        $series = app(QuizSeries::class);
        $seen = [];
        foreach (range(1, 12) as $n) {
            foreach ([QuizLevel::Easy, QuizLevel::Hard] as $level) {
                foreach ($series->picks(QuizGame::Facts, $level, 'seed'.$n) as $p) {
                    $topic = $p->subject['topic'];
                    $seen[$topic] = true;
                    $this->assertCount(QuizSeries::OPTIONS, array_unique($p->optionKeys()));
                    $json = (string) json_encode($series->questions(QuizGame::Facts, $level, 'seed'.$n, [$p]));
                    $this->assertStringNotContainsString('wikipedia', $json);
                    $this->assertStringNotContainsString('/accidents/', $json);
                    $this->assertStringNotContainsString('"correct"', $json);
                    if ($topic === 'line_material') {
                        $linked = array_map(fn (Material $m): string => $m->name, array_slice($this->locos, 0, 3));
                        foreach ($p->options as $o) {
                            $this->assertSame($o['key'] === $p->subject['key'], in_array($o['label'], $linked, true) && $o['label'] === 'BB 100');
                            $this->assertNotContains($o['label'], ['BB 200', 'BB 300']);
                        }
                    }
                }
            }
        }
        $this->assertArrayHasKey('station_opening_year', $seen);
    }

    public function test_missing_daily_tables_leave_out_their_questions_and_the_page_still_answers(): void
    {
        DB::statement('DROP TABLE station_events, line_signalling_events, accident_sources CASCADE');
        Cache::forget(FactQuiz::POOL_KEY);

        $topics = array_unique(array_column($this->quiz()->pool(), 'topic'));

        foreach (['station_opening_year', 'cab_signalling', 'accident_year'] as $gone) {
            $this->assertNotContains($gone, $topics);
        }
        $this->assertContains('line_material', $topics);
        $this->get('/quiz?game=facts&level=hard&seed=abcd1234&v=x')->assertOk();

        // Sans lien ligne x matériel (le jeu des séries en a besoin aussi : on ne regarde que le vivier de culture).
        DB::statement('DROP TABLE line_materials CASCADE');
        Cache::forget(FactQuiz::POOL_KEY);
        $topics = array_unique(array_column($this->quiz()->pool(), 'topic'));
        $this->assertNotContains('line_material', $topics);
        $this->assertContains('attendance', $topics);
    }

    public function test_the_page_and_the_pool_stay_within_their_query_budget(): void
    {
        DB::enableQueryLog();
        $this->quiz()->pool();
        $build = count(DB::getQueryLog());
        // Tables facultatives (une lecture du catalogue chacune) + une requête par famille : le vivier ne grossit pas avec les données.
        $this->assertLessThanOrEqual(26, $build, 'vivier');

        DB::flushQueryLog();
        $v = QuizSeries::version(app(QuizSeries::class)->picks(QuizGame::Facts, QuizLevel::Hard, 'abcd1234'));
        $this->get("/quiz?game=facts&level=hard&seed=abcd1234&v={$v}")->assertOk();
        $page = count(DB::getQueryLog());
        // Vivier en cache : seuls les indices (départements d'une ligne) lisent encore la base (7 requêtes mesurées).
        $this->assertLessThanOrEqual(12, $page, 'page');
    }

    private function quiz(): FactQuiz
    {
        return app(FactQuiz::class);
    }
}
