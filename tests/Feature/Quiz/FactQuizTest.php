<?php

namespace Tests\Feature\Quiz;

use App\Enums\QuizGame;
use App\Enums\QuizLevel;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\Companies\CompanyFigures;
use App\Services\NetworkChronology;
use App\Services\Quiz\FactPool;
use App\Services\Quiz\FactQuiz;
use App\Services\Quiz\QuizSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jeu « Culture du rail » (QuizGame::Facts) : un sujet par question possible, des mauvaises réponses qui sont
 * vraiment fausses d'après les données, des questions de degré 3 absentes du niveau facile, jamais de réponse
 * avant le choix.
 */
class FactQuizTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Line> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        for ($i = 0; $i < 7; $i++) {
            $this->lines[] = Line::factory()->create(['name' => "Ligne de Test {$i}"]);
        }
        $this->seedChronology();
        $this->seedCompanies();
        $this->seedAttendances();
        $this->seedStructures();
        $this->seedHeritage();
        $this->seedMaterials();
        Cache::forget(FactQuiz::POOL_KEY);
    }

    private function fact(Line $line, string $kind, int $year, array $sections = []): array
    {
        return [
            'id' => "e{$line->id}{$kind}{$year}", 'kind' => $kind, 'traffic' => null, 'year' => $year, 'date' => "{$year}-01-01", 'precision' => 'year',
            'line' => ['slug' => $line->slug, 'name' => $line->name], 'sections' => $sections, 'km' => null, 'path' => '/lines/'.$line->slug,
            'regions' => [], 'sources' => [['provider' => 'wikipedia', 'url' => 'https://fr.wikipedia.org/wiki/Test']], 'shown' => true,
        ];
    }

    private function seedChronology(): void
    {
        $l = $this->lines;
        $facts = [
            $this->fact($l[0], 'opening', 1862), $this->fact($l[1], 'opening', 1865), $this->fact($l[2], 'opening', 1868),
            // Ligne ouverte par portions : pas de question sur « son » ouverture.
            $this->fact($l[3], 'opening', 1864, [['A', 'B', null]]),
            // Ligne dont une autre source donne une autre année : écartée.
            $this->fact($l[4], 'opening', 1866),
        ];
        DB::table('line_events')->insert([
            ['line_id' => $l[4]->id, 'type' => 'opening', 'event_date' => '1867-01-01', 'description' => 'Ouverture', 'source' => 'ohm', 'confidence' => 'medium', 'date_precision' => 'year', 'created_at' => now(), 'updated_at' => now()],
            ['line_id' => $l[4]->id, 'type' => 'opening', 'event_date' => '1866-01-01', 'description' => 'Ouverture', 'source' => 'wikipedia', 'confidence' => 'high', 'date_precision' => 'day', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Cache::forever(NetworkChronology::CACHE_KEY.':meta', ['first_year' => 1827]);
        Cache::forever(NetworkChronology::CACHE_KEY.':decade:1860', $facts);
        Cache::forever(NetworkChronology::CACHE_KEY.':decade:1920', [$this->fact($l[0], 'electrification', 1925)]);
    }

    private function seedCompanies(): void
    {
        $company = fn (string $slug, string $short, string $kind, string $founded, ?string $ended): array => [
            'slug' => $slug, 'short' => $short, 'kind' => $kind, 'founded' => ['date' => $founded], 'ended' => $ended === null ? null : ['date' => $ended],
        ];
        Cache::forever(CompanyFigures::INDEX_KEY, ['companies' => [
            $company('nord', 'Nord', 'grande-compagnie', '1845-09-20', '1937-12-31'),
            $company('est', 'Est', 'grande-compagnie', '1845-01-01', '1937-12-31'),
            $company('midi', 'Midi', 'grande-compagnie', '1852-11-06', '1937-12-31'),
            $company('plm', 'PLM', 'grande-compagnie', '1857-07-03', '1937-12-31'),
            $company('etat', 'Réseau de l’État', 'etat', '1878-05-25', '1937-12-31'),
        ]]);
        // Ligne 0 : Nord de 1860 à 1937 (une seule compagnie) ; ligne 1 : deux compagnies en même temps, écartée.
        Cache::forever(CompanyFigures::BY_LINE_KEY, [
            $this->lines[0]->id => [['slug' => 'nord', 'name' => 'Nord', 'from_year' => 1860, 'to_year' => 1937, 'to_basis' => 'event']],
            $this->lines[1]->id => [
                ['slug' => 'nord', 'name' => 'Nord', 'from_year' => 1860, 'to_year' => 1937, 'to_basis' => 'event'],
                ['slug' => 'est', 'name' => 'Est', 'from_year' => 1860, 'to_year' => 1937, 'to_basis' => 'event'],
            ],
        ]);
        DB::table('line_events')->insert(['line_id' => $this->lines[0]->id, 'type' => 'concession', 'event_date' => '1860-01-01', 'description' => 'Concession à Nord (infobox Wikipédia)', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/Test', 'confidence' => 'high', 'date_precision' => 'year', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function seedAttendances(): void
    {
        foreach ([5_000_000, 3_000_000, 1_000_000, 900_000, 800_000, 700_000, 600_000, 15_000] as $i => $n) {
            $station = Station::factory()->create(['name' => "Gare numéro {$i}"]);
            DB::table('station_attendances')->insert([
                'station_id' => $station->id, 'year' => 2024, 'passengers' => $n, 'footfall' => $n, 'series' => '2015-2024', 'uic_code' => '8700'.$i,
                'match_method' => 'uic', 'source' => 'sncf', 'dataset' => 'frequentation-gares-archive', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedStructures(): void
    {
        foreach ([13_657, 8_191, 4_000, 1_500, 900] as $i => $length) {
            $id = DB::table('structures')->insertGetId([
                'kind' => 'tunnel', 'name' => "Tunnel de Test {$i}", 'name_source' => 'wikidata', 'length_m' => $length, 'length_source' => 'bdtopo', 'lat' => 45.0, 'lon' => 3.0,
                'sources' => '[]', 'match_key' => "k{$i}", 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('line_structures')->insert(['line_id' => $this->lines[0]->id, 'structure_id' => $id, 'offset_m' => 0, 'era_years' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function seedHeritage(): void
    {
        foreach ([['Hittorff', 'Jacques Ignace Hittorff', 1975], ['Lisch', 'Juste LISCH', 1984], ['Nicolas', 'Jean-Pierre Nicolas', 1990], ['Toudoire', 'Marius Toudoire', 1984], ['Compagnie', 'Compagnie du chemin de fer de Paris à Orléans', 1984]] as $i => [$n, $architect, $year]) {
            $station = Station::factory()->create(['name' => "Gare patrimoniale {$n}"]);
            DB::table('station_heritage')->insert([
                'station_id' => $station->id, 'element' => 'building', 'protection' => 'registered', 'protection_date' => "{$year}-01-15",
                'designations' => json_encode([['date' => "{$year}-01-15", 'kind' => 'registered']]),
                'architects' => json_encode([['name' => $architect, 'role' => 'architecte']]), 'built_from' => 1850 + $i * 10, 'ref' => "PA0000000{$i}", 'ref_kind' => 'PA',
                'ref_url' => "https://www.pop.culture.gouv.fr/notice/merimee/PA0000000{$i}", 'source' => 'merimee', 'match_method' => 'wikidata', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedMaterials(): void
    {
        foreach ([['BB A', 1950], ['BB B', 1960], ['BB C', 1975], ['BB D', 1990], ['BB E', 1991]] as [$name, $from]) {
            Material::factory()->fromWikidata()->create([
                'name' => $name, 'slug' => str($name)->slug()->toString(), 'type' => 'locomotive_electrique', 'in_service_from' => $from, 'scope' => 'france',
                'provenance' => ['in_service_from' => ['source' => 'wikidata', 'ref' => 'Q1#P729']],
            ]);
        }
        // Année de livraison, pas de mise en service : jamais posée.
        Material::factory()->fromWikidata()->create([
            'name' => 'BB F', 'slug' => 'bb-f', 'type' => 'locomotive_electrique', 'in_service_from' => 1900, 'scope' => 'france',
            'provenance' => ['in_service_from' => ['source' => 'wikipedia', 'basis' => 'livraison de la première unité', 'revid' => 1]],
        ]);
    }

    /** @return array<string, array<string, mixed>> sujets du vivier par identifiant */
    private function pool(): array
    {
        return array_column(app(FactQuiz::class)->pool(), null, 'id');
    }

    private function quiz(): FactQuiz
    {
        return app(FactQuiz::class);
    }

    public function test_line_years_keep_only_unambiguous_facts(): void
    {
        $pool = $this->pool();
        $slug = fn (int $i): string => $this->lines[$i]->slug;

        $this->assertArrayHasKey('opening_year:'.$slug(0), $pool);
        $this->assertSame('1862', $pool['opening_year:'.$slug(0)]['label']);
        $this->assertArrayHasKey('electrification_year:'.$slug(0), $pool);
        // Ouverte par portions, ou dont une autre source donne une autre année : aucune question.
        $this->assertArrayNotHasKey('opening_year:'.$slug(3), $pool);
        $this->assertArrayNotHasKey('opening_year:'.$slug(4), $pool);
        foreach ($pool as $s) {
            $this->assertNotSame([], $s['sources'], $s['id']);
        }
    }

    public function test_wrong_years_are_never_the_right_year_and_follow_the_level(): void
    {
        $subject = $this->pool()['opening_year:'.$this->lines[0]->slug];
        foreach ([QuizLevel::Easy, QuizLevel::Hard] as $level) {
            $options = $this->quiz()->candidates($subject, $this->quiz()->pool(), $level);
            $this->assertNotEmpty($options);
            foreach ($options as $o) {
                $gap = abs((int) $o['label'] - 1862);
                $this->assertNotSame(0, $gap);
                $this->assertNotSame($subject['key'], $o['key']);
                $this->assertTrue($level === QuizLevel::Easy ? $gap >= 6 : $gap <= 4, "{$level->value} : {$o['label']}");
            }
        }
    }

    public function test_the_easy_level_leaves_out_the_hardest_topics(): void
    {
        $pool = $this->quiz()->pool();
        $electrification = $this->pool()['electrification_year:'.$this->lines[0]->slug];

        $this->assertSame([], $this->quiz()->candidates($electrification, $pool, QuizLevel::Easy));
        $this->assertNotEmpty($this->quiz()->candidates($electrification, $pool, QuizLevel::Hard));
    }

    public function test_companies_are_attached_once_and_wrong_ones_never_ran_the_line(): void
    {
        $pool = $this->pool();
        $subjects = array_filter($pool, fn (array $s): bool => $s['topic'] === 'company');

        $this->assertCount(1, $subjects);
        $subject = array_values($subjects)[0];
        $this->assertSame('Nord', $subject['label']);
        $this->assertSame('https://fr.wikipedia.org/wiki/Test', $subject['sources'][0]['url']);
        $labels = array_column($this->quiz()->candidates($subject, array_values($pool), QuizLevel::Hard), 'label');
        $this->assertNotContains('Nord', $labels);
        $this->assertNotEmpty($labels);
        $this->assertSame([], array_diff($labels, ['Est', 'Midi', 'PLM', 'Réseau de l’État']));
    }

    public function test_longest_and_busiest_have_a_margin_over_every_wrong_answer(): void
    {
        $pool = $this->quiz()->pool();
        foreach (['longest_tunnel' => 'length_m', 'attendance' => 'passengers'] as $topic => $field) {
            foreach (array_filter($pool, fn (array $s): bool => $s['topic'] === $topic) as $s) {
                foreach (['easy' => FactQuiz::EASY_RATIO, 'hard' => FactQuiz::HARD_RATIO] as $level => $ratio) {
                    foreach ($this->quiz()->candidates($s, $pool, QuizLevel::from($level)) as $o) {
                        $this->assertGreaterThanOrEqual($o['data'][$field] * $ratio, $s['data'][$field], "{$topic} {$s['label']} contre {$o['label']}");
                    }
                }
            }
        }
        // La gare de 15 000 voyageurs est sous le seuil du vivier.
        $this->assertArrayNotHasKey('attendance:'.Station::query()->where('name', 'Gare numéro 7')->value('slug'), $this->pool());
    }

    public function test_first_series_uses_service_years_only_and_wrong_ones_are_later(): void
    {
        $pool = $this->quiz()->pool();
        $series = array_filter($pool, fn (array $s): bool => $s['topic'] === 'first_series');

        $this->assertCount(5, $series);
        $byId = $this->pool();
        $this->assertArrayNotHasKey('first_series:bb-f', $byId);
        $first = $byId['first_series:bb-a'];
        foreach ($this->quiz()->candidates($first, $pool, QuizLevel::Hard) as $o) {
            $this->assertGreaterThanOrEqual(1950 + FactQuiz::HARD_GAP, $o['data']['from']);
        }
        // La dernière série n'a personne après elle : pas de question.
        $this->assertSame([], $this->quiz()->candidates($byId['first_series:bb-e'], $pool, QuizLevel::Hard));
    }

    public function test_protection_years_need_one_single_dated_protection(): void
    {
        $years = array_column(array_filter($this->quiz()->pool(), fn (array $s): bool => $s['topic'] === 'heritage_year'), 'label');
        sort($years);
        $this->assertSame(['1975', '1984', '1984', '1984', '1990'], $years);

        // Inscription puis classement : deux dates, aucune question sur « la » protection.
        DB::table('station_heritage')->where('ref', 'PA00000001')->update(['designations' => json_encode([['date' => '1984-01-15', 'kind' => 'registered'], ['date' => '1999-01-01', 'kind' => 'classified']])]);
        Cache::forget(FactQuiz::POOL_KEY);
        $this->assertCount(4, array_filter($this->quiz()->pool(), fn (array $s): bool => $s['topic'] === 'heritage_year'));
    }

    public function test_architects_are_people_with_clean_names(): void
    {
        $labels = array_column(array_filter($this->quiz()->pool(), fn (array $s): bool => $s['topic'] === 'architect'), 'label');
        sort($labels);

        $this->assertSame(['Jacques Ignace Hittorff', 'Jean-Pierre Nicolas', 'Juste Lisch', 'Marius Toudoire'], $labels);
        $this->assertSame('Juste Lisch', FactPool::personName('Juste LISCH'));
        $this->assertNull(FactPool::personName('Jacobsthal'));
        $this->assertNull(FactPool::personName('Houssin F.'));
        $this->assertNull(FactPool::personName('Compagnie des chemins de fer du Nord'));
    }

    public function test_a_series_mixes_topics_and_every_wrong_option_is_false(): void
    {
        $series = app(QuizSeries::class);
        $byKey = [];
        foreach ($this->quiz()->pool() as $s) {
            $byKey[$s['key']] = $s;
        }
        foreach ([QuizLevel::Easy, QuizLevel::Hard] as $level) {
            $picks = $series->picks(QuizGame::Facts, $level, 'abcd1234');
            $this->assertNotEmpty($picks);
            $topics = array_map(fn ($p): string => $p->subject['topic'], $picks);
            // Les sujets passent à tour de rôle : une série ne pose pas dix fois la même sorte de question.
            $this->assertGreaterThanOrEqual(4, count(array_unique($topics)));
            foreach ($picks as $p) {
                $this->assertCount(QuizSeries::OPTIONS, $p->options);
                $this->assertCount(QuizSeries::OPTIONS, array_unique(array_column($p->options, 'label')));
                $this->assertContains($p->subject['key'], $p->optionKeys());
                $d = $p->subject['data'];
                foreach ($p->options as $o) {
                    if ($o['key'] === $p->subject['key']) {
                        continue;
                    }
                    $other = $byKey[$o['key']] ?? null;
                    match ($p->subject['topic']) {
                        'longest_tunnel' => $this->assertLessThan($d['length_m'], $other['data']['length_m']),
                        'attendance' => $this->assertLessThan($d['passengers'], $other['data']['passengers']),
                        'first_series' => $this->assertGreaterThan($d['from'], $other['data']['from']),
                        'opening_year', 'electrification_year', 'heritage_year' => $this->assertNotSame((string) $d['year'], $o['label']),
                        default => $this->assertNotSame($p->subject['label'], $o['label']),
                    };
                }
            }
        }
    }

    public function test_the_page_never_reveals_the_answer_and_the_answer_cites_its_source(): void
    {
        $this->get('/quiz?game=facts&level=hard&seed=abcd1234&v=x')->assertOk();
        $v = QuizSeries::version(app(QuizSeries::class)->picks(QuizGame::Facts, QuizLevel::Hard, 'abcd1234'));
        $response = $this->get("/quiz?game=facts&level=hard&seed=abcd1234&v={$v}")->assertOk();
        $questions = $response->viewData('page')['props']['questions'];
        $json = (string) json_encode($questions);

        foreach (['correct', 'fact', 'source', '/lines/', '/stations/', '/materials/', 'merimee', 'oldid'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, $needle);
        }
        foreach ($questions as $q) {
            $this->assertSame(['index', 'options', 'topic', 'difficulty', 'prompt', 'subject', 'hints'], array_keys($q));
        }

        foreach ($questions as $q) {
            $pick = app(QuizSeries::class)->pick(QuizGame::Facts, QuizLevel::Hard, 'abcd1234', $q['index']);
            $answer = $this->getJson("/quiz/answer?game=facts&level=hard&seed=abcd1234&v={$v}&q={$q['index']}&choice={$pick->subject['key']}")
                ->assertOk()
                ->assertJsonPath('correct', true)
                ->assertJsonPath('answer.label', $pick->subject['label']);
            $this->assertNotSame('', $answer->json('fact.text'));
            $this->assertNotEmpty($answer->json('fact.sources'));
            foreach ($answer->json('fact.sources') as $source) {
                $this->assertNotSame('', $source['name']);
            }
            $this->assertNotEmpty($answer->json('answer.url'));
        }
    }

    public function test_reveal_texts_exist_in_every_language(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $picks = app(QuizSeries::class)->picks(QuizGame::Facts, QuizLevel::Hard, 'abcd1234');
            foreach ($picks as $p) {
                $text = app(QuizSeries::class)->reveal(QuizGame::Facts, $p, $p->subject['key'])['fact']['text'];
                $this->assertStringNotContainsString('quiz.facts', $text, "{$locale} {$p->subject['topic']}");
                $q = app(QuizSeries::class)->source(QuizGame::Facts)->question($p);
                $this->assertStringNotContainsString('quiz.facts', $q['prompt'], "{$locale} {$p->subject['topic']}");
            }
        }
    }

    public function test_missing_optional_tables_leave_out_their_questions_and_the_page_still_answers(): void
    {
        DB::statement('DROP TABLE station_attendances, station_heritage, line_structures, structures CASCADE');
        Cache::forget(FactQuiz::POOL_KEY);

        $topics = array_unique(array_column($this->quiz()->pool(), 'topic'));

        $this->assertNotContains('attendance', $topics);
        $this->assertNotContains('architect', $topics);
        $this->assertNotContains('longest_tunnel', $topics);
        $this->assertContains('opening_year', $topics);
        $this->get('/quiz?game=facts&level=hard&seed=abcd1234&v=x')->assertOk();
    }

    public function test_a_missing_chronology_cache_gives_a_short_lived_pool_without_line_years(): void
    {
        Cache::forget(NetworkChronology::CACHE_KEY.':meta');
        Cache::forget(FactQuiz::POOL_KEY);

        $topics = array_unique(array_column($this->quiz()->pool(), 'topic'));

        $this->assertNotContains('opening_year', $topics);
        $this->assertContains('attendance', $topics);
    }
}
