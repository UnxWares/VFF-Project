<?php

namespace Tests\Feature\Advocacy;

use App\Models\Line;
use App\Services\LineChronicle;
use App\Support\Advocacy;
use App\Support\EventText;
use Database\Seeders\AdvocacySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Soutien de VFF à la ligne 405000 (Argentan - Granville), menacée selon le courrier du président de la
 * Région Normandie du 9 juillet 2026 : les pourcentages du message sont ceux du relevé SNCF
 * (database/data/frequentation_gares_405000.php), dans les trois langues ; l'encart liste ses sources
 * avec leur date dans la langue de la page ; la chronologie garde le fait daté, neutre, sans changer
 * l'état de la carte.
 */
class ArgentanGranvilleAdvocacyTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    /** Gares nommées dans le message : desservies par cette seule ligne. */
    private const STATIONS = ['Briouze', 'Flers', 'Vire', 'Villedieu-les-Poêles'];

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->line = Line::factory()->create(['code' => '405000', 'name' => 'Ligne d\'Argentan à Granville', 'slug' => 'rfn-405000']);
    }

    /** Montées et descentes des gares nommées, par année (jeu 2015-2024). */
    private function total(int $year): int
    {
        $data = require database_path('data/frequentation_gares_405000.php');
        $stations = array_filter($data['stations'], fn (array $s) => in_array($s['name'], self::STATIONS, true));
        $this->assertCount(count(self::STATIONS), $stations);
        foreach ($stations as $s) {
            $this->assertTrue($s['only_this_line'], $s['name'].' : gare desservie par cette seule ligne');
        }

        return array_sum(array_map(fn (array $s) => $s['travellers'][$year], $stations));
    }

    private function percent(int $from, int $to): int
    {
        return (int) round(abs(100 * ($this->total($to) / $this->total($from) - 1)));
    }

    public function test_the_message_quotes_the_sncf_figures_in_every_language(): void
    {
        $this->seed(AdvocacySeeder::class);
        $advocacy = $this->line->fresh()->advocacy;

        $since2019 = $this->percent(2019, 2024);
        $since2021 = $this->percent(2021, 2024);
        $lastYear = $this->percent(2023, 2024);
        $this->assertSame([20, 63, 4], [$since2019, $since2021, $lastYear]);
        $this->assertLessThan($this->total(2023), $this->total(2024), 'la baisse de 2024 est dite dans le message');

        $messages = ['fr' => $advocacy['message'], 'en' => $advocacy['translations']['en']['message'], 'de' => $advocacy['translations']['de']['message']];
        // Espace insécable avant « % » en français et en allemand (pas de « % » seul en début de ligne).
        $format = ['fr' => "%d\u{00A0}%%", 'en' => '%d%%', 'de' => "%d\u{00A0}%%"];
        foreach ($messages as $locale => $message) {
            foreach ([$since2019, $since2021, $lastYear] as $n) {
                $this->assertStringContainsString(sprintf($format[$locale], $n), $message, $locale);
            }
            foreach (self::STATIONS as $station) {
                $this->assertStringContainsString($station, $message, $locale);
            }
            $this->assertStringNotContainsString('—', $message);
            $this->assertStringNotContainsString('–', $message);
        }
        // Position de l'éditeur dite en toutes lettres, séparée de la fiche.
        $this->assertStringEndsWith('VFF soutient le maintien de la ligne.', $advocacy['message']);
        $this->assertStringEndsWith('VFF supports keeping the line open.', $messages['en']);
        $this->assertStringEndsWith('VFF setzt sich für den Erhalt der Strecke ein.', $messages['de']);
        // Rien de non sourcé : ni « constante augmentation », ni fermeture décidée par la Région.
        $this->assertStringNotContainsString('constante', $advocacy['message']);
    }

    public function test_the_line_is_flagged_and_lists_its_dated_sources(): void
    {
        $this->seed(AdvocacySeeder::class);
        $line = $this->line->fresh();

        $this->assertTrue($line->is_threatened);
        $this->assertSame('threatened', $line->advocacy['kind']);
        $this->assertNull($line->advocacy['cta_url']);
        $this->assertCount(4, $line->advocacy['sources']);
        $this->assertSame(['label' => 'France 3 Normandie', 'date' => '2026-07-23',
            'url' => 'https://france3-regions.franceinfo.fr/normandie/1-milliard-d-euros-de-travaux-trop-vetuste-la-ligne-sncf-paris-granville-pourrait-elle-vraiment-disparaitre-3390607.html'],
            $line->advocacy['sources'][0]);

        $this->assertSame('23 juillet 2026', Advocacy::localized($line->advocacy, 'fr')['sources'][0]['date']);
        $this->assertSame('23 July 2026', Advocacy::localized($line->advocacy, 'en')['sources'][0]['date']);
        $this->assertSame('23. Juli 2026', Advocacy::localized($line->advocacy, 'de')['sources'][0]['date']);

        // Requêtes de test en anglais : texte traduit, sources datées en anglais.
        $this->get('/lines/rfn-405000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('line.advocacy.title', 'The Argentan - Granville line must stay open')
            ->where('line.advocacy.lang.message', 'en')
            ->where('line.advocacy.sources.1.label', 'ICI Normandie')
            ->where('line.advocacy.sources.1.date', '19 January 2026'));
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('threatened.0.slug', 'rfn-405000'));
    }

    public function test_apis_flag_the_line_without_publishing_the_message(): void
    {
        $this->seed(AdvocacySeeder::class);

        $rest = $this->getJson('/api/v1/lines/rfn-405000')->assertOk()->assertJsonPath('data.is_threatened', true);
        $this->assertStringNotContainsString('doit rester ouverte', (string) $rest->getContent());

        $mcp = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'get_line', 'arguments' => ['line' => 'rfn-405000']]])
            ->assertOk()->getContent();
        $this->assertStringContainsString('\\"is_threatened\\":true', (string) $mcp);
        $this->assertStringNotContainsString('rester ouverte', (string) $mcp);
    }

    public function test_a_badly_written_source_is_refused(): void
    {
        $this->artisan('vff:flag-threatened', ['code' => '405000', '--source' => ['France 3|23/07/2026|https://exemple.test']])
            ->expectsOutputToContain('source invalide')->assertFailed();
        $this->assertNull($this->line->fresh()->advocacy);

        $this->expectException(InvalidArgumentException::class);
        Advocacy::parseSource('France 3|2026-07-23|http://exemple.test');
    }

    public function test_the_chronology_keeps_the_dated_warning_without_changing_the_map(): void
    {
        $this->sandboxStorage();
        File::ensureDirectoryExists($this->sandbox.'/database/data');
        File::copy(database_path('data/line_event_corrections_actualite.php'), $this->sandbox.'/database/data/line_event_corrections_actualite.php');
        $original = $this->app->databasePath();
        $this->app->useDatabasePath($this->sandbox.'/database');
        $this->beforeApplicationDestroyed(fn () => $this->app->useDatabasePath($original));

        $this->artisan('vff:apply-event-corrections')->assertSuccessful();
        $this->artisan('vff:apply-event-corrections')->assertSuccessful();

        $events = DB::table('line_events')->where('line_id', $this->line->id)->get();
        $this->assertCount(1, $events, 'idempotent');
        $event = $events[0];
        $this->assertSame('other', $event->type);
        $this->assertNotContains(LineChronicle::family($event->type), LineChronicle::STATE_FAMILIES, 'contexte, pas un état de la carte');
        $this->assertSame('2026-07-09', substr((string) $event->event_date, 0, 10));
        $this->assertStringStartsWith('Alerte sur l\'avenir de la ligne (correction sourcée : le président de la Région Normandie', $event->description);

        $this->assertSame(['Alerte sur l\'avenir de la ligne', 'Le président de la Région Normandie écrit au Premier ministre que, sans financement de l\'État pour la remise en état de la ligne Paris - Granville (près d\'un milliard d\'euros d\'ici 2040), sa fermeture pourrait être envisagée d\'ici trois ans ; la Région demande à l\'État de reprendre ces coûts'],
            EventText::splitCorrection($event->description));
        $this->assertStringStartsWith('Warning about the future of the line', (string) EventText::describe('manual', $event->description, 'en')['text']);
        $this->assertStringStartsWith('Warnung zur Zukunft der Strecke', (string) EventText::describe('manual', $event->description, 'de')['text']);
    }
}
