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
        $this->assertSame(['images' => 3, 'documents' => 1], app(EventStories::class)->available($this->line)[$this->opening->id]);
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
            ->where('story_events', [$this->opening->id]));
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
