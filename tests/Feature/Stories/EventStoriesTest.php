<?php

namespace Tests\Feature\Stories;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Models\LineEvent;
use App\Models\Media;
use App\Models\Station;
use App\Services\Archives\LineArchives;
use App\Services\Stories\EventStories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Histoires d'événements (ROADMAP : stories d'archives) : sélection des images par date, seuil, crédits,
 * droits, événement sans histoire, et les mêmes histoires partout (page, lien de la chronologie, API REST,
 * GraphQL, outil MCP `line_stories`).
 */
class EventStoriesTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $line;

    private LineEvent $opening;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-340000', 'code' => '340000', 'name' => 'Ligne de Paris-St-Lazare au Havre']);

        foreach (['Vernon-Giverny' => 'vernon-giverny', 'Port-Villez' => 'port-villez'] as $name => $slug) {
            $station = Station::factory()->create(['name' => $name, 'slug' => $slug]);
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'manual', 'pk_m' => 70000]);
        }

        $this->opening = $this->event('opening', '1843-05-03', ['section_from' => 'Vernon Giverny', 'section_to' => 'Port-Villez',
            'description' => 'Ouverture de la portion Vernon-Giverny - Port-Villez (4,8 km).', 'source' => 'ohm',
            'source_url' => 'https://www.openhistoricalmap.org/way/200903673']);

        // Dans la fenêtre (1838 à 1848) : 1843, 1847, 1839 ; une décennie et des droits inconnus sont écartés.
        $this->image(1843, ['author' => 'Johann Jacob Weber', 'license' => 'Public domain', 'rights_status' => 'public_domain', 'caption' => 'Karte der Eisenbahn von Paris nach Rouen.']);
        $this->image(1847, ['author' => 'Auguste Mouchot', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'share_alike' => true]);
        $this->image(1839);
        $this->image(1840, ['provider_meta' => ['date_precision' => 'decade']]);
        $this->image(1844, ['rights_status' => 'unknown', 'license' => null]);
        // Hors de la fenêtre.
        $this->image(1850);
        $this->image(2015);

        $this->document('ark:/12148/btv1b53035187p', 1838, 'year');
        $this->document('ark:/12148/btv1b00000002', 1849, 'year');
        $this->document('ark:/12148/btv1b00000003', null, 'unknown');
    }

    /** @param array<string, mixed> $extra */
    private function event(string $type, string $date, array $extra = []): LineEvent
    {
        return LineEvent::factory()->create(['line_id' => $this->line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day', ...$extra]);
    }

    /** @param array<string, mixed> $extra */
    private function image(int $year, array $extra = [], ?LineEvent $event = null): Media
    {
        return Media::factory()->create([
            'mediable_type' => $event === null ? Line::class : LineEvent::class,
            'mediable_id' => $event === null ? $this->line->id : $event->id,
            'type' => 'photo', 'provider' => 'commons', 'external_id' => 'File:'.$year.'-'.uniqid().'.jpg', 'file_path' => null,
            'thumb_url' => 'https://upload.wikimedia.org/thumb/'.$year.'.jpg', 'display_url' => 'https://upload.wikimedia.org/'.$year.'.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:'.$year.'.jpg',
            'depicted_year' => $year, 'provider_meta' => ['date_precision' => 'year'],
            'author' => 'Auteur '.$year, 'license' => 'CC BY 4.0', 'rights_status' => 'open_license',
            ...$extra,
        ]);
    }

    private function document(string $ark, ?int $year, string $precision): void
    {
        (new LineArchiveDocument)->forceFill([
            'line_id' => $this->line->id, 'source' => 'gallica', 'gallica_ark' => $ark, 'title' => 'Chemin de fer de Paris au Havre '.$year,
            'doc_type' => 'map', 'authors' => ['Delarue'], 'date_label' => $year === null ? null : (string) $year, 'year' => $year,
            'date_precision' => $precision, 'score' => 11, 'score_reasons' => [['code' => 'rail_route', 'points' => 4]],
            'matched_places' => ['Paris', 'Le Havre'], 'fetched_at' => '2026-11-20 10:00:00',
        ])->save();
    }

    public function test_images_are_chosen_by_date_rights_and_precision_with_their_gap(): void
    {
        $story = app(EventStories::class)->story($this->line, $this->opening);

        $this->assertNotNull($story);
        $this->assertSame(['years' => 5, 'from' => 1838, 'to' => 1848], $story['window']);
        // Ordre chronologique ; ni la décennie, ni les droits inconnus, ni 1850 ou 2015.
        $this->assertSame([1839, 1843, 1847], array_column($story['images'], 'depicted_year'));
        $this->assertSame([-4, 0, 4], array_column($story['images'], 'gap_years'));
        $this->assertSame(3, $story['images_total']);
        // Documents : 1838 (cinq ans avant) ; 1849 est hors de la fenêtre, le document sans date aussi.
        $this->assertSame([-5], array_column($story['documents'], 'gap_years'));
        $this->assertSame('https://gallica.bnf.fr/ark:/12148/btv1b53035187p', $story['documents'][0]['gallica_url']);
        // Gares de la portion, écrites comme la liste des gares (« Vernon Giverny » de la source).
        $this->assertSame([['name' => 'Vernon-Giverny', 'slug' => 'vernon-giverny'], ['name' => 'Port-Villez', 'slug' => 'port-villez']], $story['stations']);
    }

    public function test_each_image_keeps_its_full_credit(): void
    {
        $story = app(EventStories::class)->story($this->line, $this->opening);
        $sa = collect($story['images'])->firstWhere('depicted_year', 1847);

        $this->assertSame('Auguste Mouchot', $sa['author']);
        $this->assertSame('CC BY-SA 4.0', $sa['license']);
        $this->assertSame('https://creativecommons.org/licenses/by-sa/4.0/', $sa['license_url']);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:1847.jpg', $sa['source_url']);
        $this->assertSame('Wikimedia Commons', $sa['provider_label']);
        $this->assertTrue($sa['share_alike']);
        $this->assertSame('public_domain', collect($story['images'])->firstWhere('depicted_year', 1843)['rights_status']);
    }

    public function test_the_threshold_needs_three_items_including_two_images(): void
    {
        $this->assertTrue(EventStories::meetsThreshold(2, 1));
        $this->assertTrue(EventStories::meetsThreshold(3, 0));
        $this->assertFalse(EventStories::meetsThreshold(2, 0));
        $this->assertFalse(EventStories::meetsThreshold(1, 5));

        // 1900 : aucune image à cinq ans près, deux documents ne suffisent pas.
        $late = $this->event('closure', '1900-01-01');
        $this->document('ark:/12148/btv1b00000010', 1899, 'year');
        $this->document('ark:/12148/btv1b00000011', 1901, 'circa');
        $this->assertNull(app(EventStories::class)->story($this->line, $late));
        $this->assertArrayNotHasKey($late->id, app(EventStories::class)->available($this->line));
        $this->assertSame(['images' => 3, 'documents' => 1, 'event_ids' => [$this->opening->id]], app(EventStories::class)->available($this->line)[$this->opening->id]);
    }

    public function test_images_attached_to_the_event_count_whatever_their_date(): void
    {
        $electrification = $this->event('electrification', '1967-06-01');
        $this->image(1966);
        $this->image(1968);
        // Deux images seulement, sans document : sous le seuil.
        $before = app(EventStories::class)->story($this->line, $electrification);
        $this->assertNull($before);

        $this->image(1930, ['rights_status' => 'permission'], $electrification);
        $story = app(EventStories::class)->story($this->line, $electrification);

        $this->assertNotNull($story);
        $this->assertSame([1930, 1966, 1968], array_column($story['images'], 'depicted_year'));
        $this->assertSame([true, false, false], array_column($story['images'], 'attached'));
        $this->assertSame([-37, -1, 1], array_column($story['images'], 'gap_years'));
    }

    public function test_administrative_events_and_events_of_another_line_have_no_story(): void
    {
        $concession = $this->event('concession', '1843-01-01');
        $this->assertNull(app(EventStories::class)->story($this->line, $concession));
        $this->assertArrayNotHasKey($concession->id, app(EventStories::class)->available($this->line));

        $other = Line::factory()->create(['slug' => 'rfn-1000']);
        $this->assertNull(app(EventStories::class)->story($other, $this->opening));
    }

    public function test_the_page_shows_the_story_and_returns_404_without_one(): void
    {
        $this->get('/lines/rfn-340000/stories/'.$this->opening->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Story')
            ->where('story.line.slug', 'rfn-340000')
            ->where('story.event.id', $this->opening->id)
            ->where('story.text.section_text', 'from Vernon-Giverny to Port-Villez')
            ->has('story.images', 3)
            ->has('story.documents', 1)
            ->where('story.attribution', LineArchives::ATTRIBUTION));

        $concession = $this->event('concession', '1843-01-01');
        $this->get('/lines/rfn-340000/stories/'.$concession->id)->assertNotFound();
        $this->get('/lines/rfn-340000/stories/99999999')->assertNotFound();
        Line::factory()->create(['slug' => 'rfn-1000']);
        $this->get('/lines/rfn-1000/stories/'.$this->opening->id)->assertNotFound();
    }

    public function test_the_page_writes_share_tags_for_robots(): void
    {
        $html = (string) $this->get('/lines/rfn-340000/stories/'.$this->opening->id)->assertOk()->getContent();

        $this->assertStringContainsString('/lines/rfn-340000/stories/'.$this->opening->id, $html);
        $this->assertStringContainsString('https://upload.wikimedia.org/1839.jpg', $html);
    }

    public function test_the_line_page_lists_the_events_that_have_a_story(): void
    {
        $this->event('concession', '1843-01-01');

        $this->get('/lines/rfn-340000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Show')
            ->where('story_events', [[$this->opening->id]]));
    }

    public function test_a_fact_reported_by_several_sources_has_one_story_at_the_address_of_the_surest(): void
    {
        // Même ouverture, même jour, même portion (lieux dans l'autre sens, « St » pour « Saint ») : trois sources.
        $wikipedia = $this->event('opening', '1843-05-03', ['section_from' => 'Port Villez', 'section_to' => 'Vernon-Giverny',
            'source' => 'wikipedia', 'confidence' => 'high', 'description' => 'Ouverture de Vernon à Port-Villez.']);
        $sure = $this->event('opening', '1843-05-03', ['section_from' => 'vernon giverny', 'section_to' => 'Port-Villez',
            'source' => 'lignes-oubliees', 'confidence' => 'high', 'source_url' => 'https://www.lignes-oubliees.com/ligne/340000']);
        // Autres faits : autre date, autre type, un seul lieu nommé (rien ne dit que c'est la même portion).
        $otherDay = $this->event('opening', '1843-05-04', ['section_from' => 'Vernon-Giverny', 'section_to' => 'Port-Villez', 'source' => 'wikidata']);
        $otherType = $this->event('opening_passenger', '1843-05-03', ['section_from' => 'Vernon-Giverny', 'section_to' => 'Port-Villez', 'source' => 'wikidata']);
        $near = $this->event('opening', '1843-05-03', ['section_from' => 'Vernon-Giverny', 'section_resolution' => 'unresolved', 'source' => 'wikidata']);
        $near2 = $this->event('opening', '1843-05-03', ['section_from' => 'Vernon-Giverny', 'section_resolution' => 'unresolved', 'source' => 'ohm']);
        $stories = app(EventStories::class);

        $available = $stories->available($this->line);
        $this->assertSame([$sure->id, $wikipedia->id, $this->opening->id], $available[$sure->id]['event_ids']);
        foreach ([$wikipedia, $this->opening] as $merged) {
            $this->assertArrayNotHasKey($merged->id, $available);
        }
        foreach ([$otherDay, $otherType, $near, $near2] as $alone) {
            $this->assertSame([$alone->id], $available[$alone->id]['event_ids']);
        }

        // Même histoire depuis chacune des sources : adresse et texte de la plus sûre, toutes les sources citées.
        foreach ([$sure, $wikipedia, $this->opening] as $event) {
            $story = $stories->story($this->line, $event);
            $this->assertSame($sure->id, $story['event']['id']);
            $this->assertSame([$sure->id, $wikipedia->id, $this->opening->id], array_column($story['sources'], 'id'));
            $this->assertSame(['lignes-oubliees', 'wikipedia', 'ohm'], array_column(array_column($story['sources'], 'provenance'), 'provider'));
        }

        // Les autres adresses redirigent (301) vers celle de l'histoire, sur le site et dans l'API.
        $this->get('/lines/rfn-340000/stories/'.$this->opening->id)->assertStatus(301)
            ->assertRedirect(route('lines.stories.show', ['line' => 'rfn-340000', 'event' => $sure->id]));
        $this->get('/lines/rfn-340000/stories/'.$sure->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('story.event.id', $sure->id)->has('story.sources', 3));
        $this->getJson('/api/v1/lines/rfn-340000/stories/'.$wikipedia->id)->assertStatus(301)
            ->assertRedirect(route('api.lines.stories.show', ['line' => 'rfn-340000', 'event' => $sure->id]));
        $show = $this->getJson('/api/v1/lines/rfn-340000/stories/'.$sure->id)->assertOk();
        $this->assertSame([$sure->id, $wikipedia->id, $this->opening->id], array_column($show->json('sources'), 'id'));
        $list = $this->getJson('/api/v1/lines/rfn-340000/stories')->assertOk();
        /** @var list<array{event: array{id: int}, source_event_ids: list<int>}> $items */
        $items = $list->json('stories');
        $entry = array_values(array_filter($items, fn (array $i): bool => $i['event']['id'] === $sure->id))[0];
        $this->assertSame([$sure->id, $wikipedia->id, $this->opening->id], $entry['source_event_ids']);
        $this->assertNotContains($this->opening->id, array_column(array_column($list->json('stories'), 'event'), 'id'));

        // GraphQL et MCP : la même histoire pour chaque source, avec l'adresse de la plus sûre.
        $gql = $this->graphQL('query($id: ID!) { lineEvent(id: $id) { story { event_id sources { id source } } } }', ['id' => $this->opening->id]);
        $this->assertSame((string) $sure->id, $gql->json('data.lineEvent.story.event_id'));
        $this->assertSame([(string) $sure->id, (string) $wikipedia->id, (string) $this->opening->id], array_column($gql->json('data.lineEvent.story.sources'), 'id'));
        [$mcp] = $this->tool('line_stories', ['line' => 'rfn-340000', 'event' => $this->opening->id]);
        $this->assertSame($sure->id, $mcp['event']['id']);
        $this->assertCount(3, $mcp['sources']);
        [$mcpList] = $this->tool('line_stories', ['line' => 'rfn-340000']);
        $this->assertNotContains($this->opening->id, array_column(array_column($mcpList['stories'], 'event'), 'id'));

        // La fiche : un fait par liste, l'adresse d'abord (un seul lien « Voir l'histoire »).
        $this->get('/lines/rfn-340000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('story_events', function (Collection $facts) use ($sure, $wikipedia): bool {
                /** @var list<list<int>> $all */
                $all = $facts->all();
                $ids = array_merge(...$all);

                return in_array([$sure->id, $wikipedia->id, $this->opening->id], $all, true) && count($ids) === count(array_unique($ids));
            }));
    }

    public function test_the_surest_source_is_ranked_by_source_then_confidence_then_date_precision(): void
    {
        $row = fn (int $id, ?string $source, string $confidence = 'medium', string $precision = 'day', ?string $url = null): array => [
            'id' => $id, 'type' => 'closure', 'date' => '1938-01-01', 'precision' => $precision, 'source' => $source, 'source_url' => $url,
            'confidence' => $confidence, 'section_from' => null, 'section_to' => null, 'section_resolution' => 'whole_line', 'section_pk_start_m' => null,
        ];
        // Toute la ligne : un seul fait. Tableau PDF de lignes-oubliees (décision administrative) après Wikipédia.
        $this->assertSame([5 => [5, 3, 4, 2, 1, 6]], EventStories::facts([
            $row(1, 'wikidata'), $row(2, 'lignes-oubliees', url: 'https://www.lignes-oubliees.com/IMG/pdf/x.pdf'),
            $row(3, 'wikipedia', 'high'), $row(4, 'wikipedia', 'low'), $row(5, 'lignes-oubliees'), $row(6, null),
        ]));
        // Même date et même source, précisions différentes : jamais le même fait (la précision fait partie de la clé).
        $this->assertCount(2, EventStories::facts([$row(1, 'wikipedia', precision: 'year'), $row(2, 'wikipedia')]));
    }

    public function test_the_same_commons_file_is_shown_once_in_a_story(): void
    {
        // Un même fichier de Commons rattaché à la ligne (dans la fenêtre) et à deux sources du même fait.
        $wikipedia = $this->event('opening', '1843-05-03', ['section_from' => 'Vernon-Giverny', 'section_to' => 'Port-Villez', 'source' => 'wikipedia']);
        $file = ['external_id' => 'File:Rouen 1843.jpg', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Rouen_1843.jpg'];
        $this->image(1843, $file);
        $this->image(1843, $file, $this->opening);
        $this->image(1843, $file, $wikipedia);
        $stories = app(EventStories::class);

        foreach ([false, true] as $api) {
            $story = $stories->story($this->line, $this->opening, $api);
            $this->assertSame(1, count(array_keys(array_column($story['images'], 'source_url'), $file['source_url'])), 'api='.($api ? '1' : '0'));
            $this->assertSame(4, $story['images_total']);
            // Gardée comme image de l'événement, et comptée une fois sur la fiche.
            $this->assertCount(1, array_filter($story['images'], fn (array $i): bool => $i['attached']));
            $this->assertSame(4, $stories->available($this->line, $api)[$story['event']['id']]['images']);
        }
    }

    public function test_the_rest_api_lists_and_serves_stories(): void
    {
        $list = $this->getJson('/api/v1/lines/rfn-340000/stories')->assertOk()->assertHeader('Cache-Control');
        $this->assertSame(1, $list->json('total'));
        $this->assertSame($this->opening->id, $list->json('stories.0.event.id'));
        $this->assertSame(3, $list->json('stories.0.images'));
        $this->assertSame('ohm', $list->json('stories.0.event.provenance.provider'));
        $this->assertSame(['window_years' => 5, 'min_items' => 3, 'min_images' => 2, 'max_images' => 24], $list->json('rules'));

        $show = $this->getJson('/api/v1/lines/rfn-340000/stories/'.$this->opening->id)->assertOk();
        $this->assertSame([-4, 0, 4], array_column($show->json('images'), 'gap_years'));
        $this->assertSame(LineArchives::ATTRIBUTION, $show->json('attribution'));
        $this->assertStringEndsWith('/lines/rfn-340000/stories/'.$this->opening->id, $show->json('url'));
        // Jamais d'image de Gallica.
        $this->assertStringNotContainsString('iiif', (string) $show->getContent());

        $concession = $this->event('concession', '1843-01-01');
        $this->getJson('/api/v1/lines/rfn-340000/stories/'.$concession->id)->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/v1/lines/rfn-999999/stories')->assertNotFound();
        Line::factory()->create(['slug' => 'rfn-1000']);
        $this->getJson('/api/v1/lines/rfn-1000/stories')->assertOk()->assertJson(['total' => 0, 'stories' => []]);
        $this->getJson('/api/v1/lines/rfn-1000/stories/'.$this->opening->id)->assertNotFound();
    }

    public function test_graphql_gives_the_story_of_an_event_or_null(): void
    {
        $concession = $this->event('concession', '1843-01-01');
        $res = $this->graphQL('query($a: ID!, $b: ID!) {
            a: lineEvent(id: $a) { story { window_from window_to images_total images { gap_years attached media { author license } } documents { gap_years document { gallica_url } } stations { name slug } } }
            b: lineEvent(id: $b) { story { images_total } }
        }', ['a' => $this->opening->id, 'b' => $concession->id]);

        $res->assertJsonMissingPath('errors');
        $this->assertSame(1838, $res->json('data.a.story.window_from'));
        $this->assertSame([-4, 0, 4], array_column($res->json('data.a.story.images'), 'gap_years'));
        $this->assertSame('Johann Jacob Weber', $res->json('data.a.story.images.1.media.author'));
        $this->assertSame(-5, $res->json('data.a.story.documents.0.gap_years'));
        $this->assertSame('vernon-giverny', $res->json('data.a.story.stations.0.slug'));
        $this->assertNull($res->json('data.b.story'));
    }

    public function test_an_imprecise_date_is_kept_only_when_its_whole_interval_is_in_the_window(): void
    {
        // Précisions réellement présentes dans `media` sur vff (20/11/2026) ; fenêtre 1838 à 1848.
        $kept = [
            $this->image(1840, ['provider_meta' => ['date_precision' => 'circa']])->id => [-5, -1],
            $this->image(1840, ['provider_meta' => ['date_precision' => 'range', 'year_to' => 1845]])->id => [-3, 2],
            $this->image(1844, ['provider_meta' => ['date_precision' => 'year', 'year_to' => 1845]])->id => [1, 2],
            $this->image(1845, ['provider_meta' => ['date_precision' => 'month']])->id => [2, 2],
            $this->image(1846, ['provider_meta' => ['date_precision' => 'day']])->id => [3, 3],
        ];
        // « Vers 1838 » peut être 1836 : hors de la fenêtre, comme une borne seule, une décennie qui déborde,
        // une fourchette trop large ou sans fin, une année tirée d'une catégorie ou d'un titre.
        foreach ([
            [1838, ['date_precision' => 'circa']],
            [1847, ['date_precision' => 'circa']],
            [1835, ['date_precision' => 'range', 'year_to' => 1845]],
            [1840, ['date_precision' => 'range']],
            [1846, ['date_precision' => 'before']],
            [1840, ['date_precision' => 'after']],
            [1830, ['date_precision' => 'decade']],
            [1843, ['date_precision' => 'category']],
            [1843, ['date_precision' => 'title']],
        ] as [$year, $meta]) {
            $this->image($year, ['provider_meta' => $meta]);
        }

        $story = app(EventStories::class)->story($this->line, $this->opening);
        $this->assertNotNull($story);
        $byId = collect($story['images'])->keyBy('id');
        $this->assertSame(3 + count($kept), $story['images_total']);
        foreach ($kept as $id => [$min, $max]) {
            $this->assertTrue($byId->has($id), "image {$id}");
            $this->assertSame([$min, $max], [$byId[$id]['gap_years_min'], $byId[$id]['gap_years_max']]);
            $this->assertSame($min === $max, $byId[$id]['gap_exact']);
        }
        $circa = $byId[array_key_first($kept)];
        $this->assertSame(-3, $circa['gap_years']);
        $this->assertSame('circa', $circa['date_precision']);
        // Le décompte de la fiche ligne suit la même règle que l'histoire.
        $this->assertSame($story['images_total'], app(EventStories::class)->available($this->line)[$this->opening->id]['images']);
    }

    public function test_the_interval_rule_covers_every_precision(): void
    {
        $this->assertSame([1843, 1843], EventStories::imageInterval(1843, 'day'));
        $this->assertSame([1843, 1843], EventStories::imageInterval(1843, null));
        $this->assertSame([1843, 1845], EventStories::imageInterval(1843, 'year', '1845'));
        $this->assertSame([1841, 1845], EventStories::imageInterval(1843, 'circa'));
        $this->assertSame([1840, 1849], EventStories::imageInterval(1843, 'decade'));
        $this->assertSame([1840, 1850], EventStories::imageInterval(1840, 'range', 1850));
        foreach (['before', 'after', 'category', 'title'] as $precision) {
            $this->assertNull(EventStories::imageInterval(1843, $precision), $precision);
        }
        $this->assertNull(EventStories::imageInterval(1843, 'range'));
        $this->assertNull(EventStories::imageInterval(null, 'day'));
    }

    public function test_the_line_page_count_matches_every_story(): void
    {
        $electrification = $this->event('electrification', '1967-06-01');
        $this->image(1966, ['provider_meta' => ['date_precision' => 'circa']]);
        $this->image(1960, ['provider_meta' => ['date_precision' => 'decade']]);
        $this->image(1968, ['rights_status' => 'permission']);
        $this->image(1930, [], $electrification);
        $this->event('closure', '1849-01-01');
        $this->event('opening', '1965-01-01');

        $stories = app(EventStories::class);
        foreach ([false, true] as $api) {
            $available = $stories->available($this->line, $api);
            foreach (LineEvent::query()->where('line_id', $this->line->id)->get() as $event) {
                $story = $stories->story($this->line, $event, $api);
                $this->assertSame(isset($available[$event->id]), $story !== null, "{$event->id} api={$api}");
                if ($story !== null) {
                    $this->assertSame($available[$event->id]['images'], $story['images_total']);
                }
            }
        }
    }

    public function test_images_shown_with_a_mere_permission_stay_off_the_api(): void
    {
        $permission = $this->image(1842, ['rights_status' => 'permission', 'license' => null]);

        $page = app(EventStories::class)->story($this->line, $this->opening);
        $this->assertContains($permission->id, array_column($page['images'], 'id'));

        $rest = $this->getJson('/api/v1/lines/rfn-340000/stories/'.$this->opening->id)->assertOk();
        $this->assertNotContains($permission->id, array_column($rest->json('images'), 'id'));
        $this->assertSame(3, $rest->json('images_total'));
        $this->assertSame(3, $this->getJson('/api/v1/lines/rfn-340000/stories')->json('stories.0.images'));

        $gql = $this->graphQL('query($id: ID!) { lineEvent(id: $id) { story { images { media { id } } } } }', ['id' => $this->opening->id]);
        $this->assertNotContains((string) $permission->id, array_column(array_column($gql->json('data.lineEvent.story.images'), 'media'), 'id'));

        [$mcp] = $this->tool('line_stories', ['line' => 'rfn-340000', 'event' => $this->opening->id]);
        $this->assertNotContains($permission->id, array_column($mcp['images'], 'id'));
    }

    public function test_graphql_refuses_stories_inside_a_list_and_caps_them_per_request(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->event('closure', '184'.($i % 9).'-01-01');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $res = $this->graphQL('{ line(slug: "rfn-340000") { events { id story { images_total } } } }');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 21 événements : aucune histoire calculée, une erreur par événement, une poignée de requêtes SQL.
        $res->assertOk();
        $this->assertCount(21, $res->json('data.line.events'));
        $this->assertSame([], array_filter(array_column($res->json('data.line.events'), 'story')));
        $this->assertStringContainsString('not inside a list', (string) $res->json('errors.0.message'));
        $this->assertLessThan(10, $queries);

        // Alias : trois histoires au plus par requête.
        $ids = LineEvent::query()->where('line_id', $this->line->id)->orderBy('id')->limit(4)->pluck('id')->all();
        $aliased = $this->graphQL('{ '.implode(' ', array_map(fn (int $id, int $i): string => "e{$i}: lineEvent(id: {$id}) { id story { images_total } }", $ids, array_keys($ids))).' }');
        $this->assertCount(1, $aliased->json('errors'));
        $this->assertStringContainsString('at most per request', (string) $aliased->json('errors.0.message'));
        $this->assertSame(['e3', 'story'], $aliased->json('errors.0.path'));
    }

    public function test_the_carousel_buttons_name_contains_their_visible_text(): void
    {
        // WCAG 2.5.3 : le nom accessible d'un bouton contient le texte affiché, dans chaque langue.
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['carousel.previous', 'carousel.next', 'link'] as $key) {
                $visible = (string) __("stories.{$key}", [], $locale);
                $name = (string) __("stories.{$key}_label", [], $locale);
                $this->assertStringContainsStringIgnoringCase($visible, $name, "{$locale} {$key}");
            }
        }
    }

    public function test_the_mcp_tool_gives_the_same_content_and_clear_errors(): void
    {
        $rest = $this->getJson('/api/v1/lines/rfn-340000/stories/'.$this->opening->id)->json();
        [$data, $error] = $this->tool('line_stories', ['line' => '340000', 'event' => $this->opening->id]);

        $this->assertFalse($error);
        $this->assertSame($rest['images'], $data['images']);
        $this->assertSame($rest['documents'], $data['documents']);
        $this->assertStringEndsWith('/lines/rfn-340000/stories/'.$this->opening->id, $data['vff_url']);
        $this->assertContains('gallica', array_column($data['attribution']['sources'], 'provider'));

        [$list] = $this->tool('line_stories', ['line' => 'rfn-340000']);
        $this->assertSame(1, $list['total']);

        $concession = $this->event('concession', '1843-01-01');
        [$none, $noneError] = $this->tool('line_stories', ['line' => 'rfn-340000', 'event' => $concession->id]);
        $this->assertTrue($noneError);
        $this->assertSame('no_story', $none['error']);

        Line::factory()->create(['slug' => 'rfn-1000']);
        [$elsewhere, $elsewhereError] = $this->tool('line_stories', ['line' => 'rfn-1000', 'event' => $this->opening->id]);
        $this->assertTrue($elsewhereError);
        $this->assertSame('event_not_on_line', $elsewhere['error']);
    }

    /** L'outil MCP ne sert jamais un fait d'une source non commerciale : ni comme événement, ni parmi les sources. */
    public function test_the_mcp_tool_never_serves_a_non_commercial_source(): void
    {
        // Même ouverture rapportée aussi par la source non commerciale, et une fermeture rapportée par elle seule.
        $same = $this->event('opening', '1843-05-03', ['section_from' => 'Vernon-Giverny', 'section_to' => 'Port-Villez',
            'source' => 'archeoferroviaire', 'confidence' => 'high', 'source_url' => 'http://archeoferroviaire.free.fr/v31/spip.php?article1']);
        $alone = $this->event('closure', '1845-06-01', ['source' => 'manual', 'source_url' => 'http://archeoferroviaire.free.fr/v31/spip.php?article2']);
        $this->assertNotNull(app(EventStories::class)->story($this->line, $alone), 'la fermeture a une histoire sur le site');

        foreach ([$this->opening, $same] as $asked) {
            [$story, $error] = $this->tool('line_stories', ['line' => 'rfn-340000', 'event' => $asked->id]);
            $this->assertFalse($error);
            $this->assertSame($this->opening->id, $story['event']['id']);
            $this->assertSame([$this->opening->id], array_column($story['sources'], 'id'));
        }

        [$none, $noneError] = $this->tool('line_stories', ['line' => 'rfn-340000', 'event' => $alone->id]);
        $this->assertTrue($noneError);
        $this->assertSame('no_story', $none['error']);

        [$list] = $this->tool('line_stories', ['line' => 'rfn-340000']);
        $ids = array_merge(array_column(array_column($list['stories'], 'event'), 'id'), ...array_column($list['stories'], 'source_event_ids'));
        $this->assertContains($this->opening->id, $ids);
        $this->assertNotContains($same->id, $ids);
        $this->assertNotContains($alone->id, $ids);
        $this->assertSame(1, $list['total']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) ($result['isError'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    private function rpc(string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
    }
}
