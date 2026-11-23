<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use App\Services\StationTerminusOverrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * --dry-run n'écrit RIEN : pour chaque commande qui déclare l'option, on la
 * lance sur un mini-réseau où elle aurait du travail (gare à slug osm-*, tracé
 * daté par son nom, tronçon SNCF fermé, ligne sans tracé, sources de gares…)
 * et on compare le contenu des tables métier avant / après.
 *
 * Les sources externes répondent vide (Http::fake) : aucune requête réelle.
 * Pour les commandes dont l'effet est simple, un test témoin vérifie qu'en
 * vrai la même commande écrit bien (sinon le dry-run ne prouverait rien).
 */
class DryRunCommandsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    /** Commandes à --dry-run (cf. CommandContractTest : la liste doit rester à jour). */
    public const COVERED = [
        'vff:aggregate-line-electrification', 'vff:apply-argentanwebferro', 'vff:apply-event-corrections', 'vff:attach-commons-stations', 'vff:attach-stations', 'vff:audit-and-fix-lines', 'vff:audit-segments', 'vff:audit-stations', 'vff:build-commune-rail', 'vff:canonicalize-station-slugs', 'vff:classify-lines', 'vff:commons-allowlist', 'vff:compute-company-figures', 'vff:compute-coverage-figures', 'vff:compute-department-figures', 'vff:compute-line-profiles', 'vff:compute-network-chronology', 'vff:compute-network-figures',
        'vff:consolidate-stations', 'vff:dates-from-trace-names', 'vff:detect-present-states', 'vff:enrich-accidents', 'vff:fill-missing-geometry',
        'vff:import-accidents', 'vff:import-argentanwebferro', 'vff:import-bibliography', 'vff:import-commons-candidates', 'vff:import-commons-media', 'vff:import-gallica', 'vff:import-heritage-lines', 'vff:import-lignes-oubliees',
        'vff:import-line-descriptions', 'vff:import-line-names', 'vff:import-line-speeds', 'vff:import-material-periods', 'vff:import-materials', 'vff:import-open-data-media', 'vff:import-panoramax-media', 'vff:import-secondary-network-dates', 'vff:import-signalling-history', 'vff:import-signalling-sources', 'vff:import-sncf-archives', 'vff:import-sncf-attendance', 'vff:import-sncf-grades', 'vff:import-sncf-platforms', 'vff:import-sncf-radio', 'vff:import-sncf-track-regime',
        'vff:import-station-accessibility', 'vff:import-station-closures', 'vff:import-station-heritage', 'vff:import-station-names', 'vff:import-station-passenger-ends', 'vff:import-station-sources', 'vff:import-station-wikidata-dates', 'vff:import-structures', 'vff:import-wikidata-lines', 'vff:import-wikipedia-chronology',
        'vff:import-wikipedia-line', 'vff:link-greenways', 'vff:link-traces', 'vff:match-current-network', 'vff:merge-split-lines', 'vff:neutralize-line-slugs', 'vff:ohm-events',
        'vff:place-listed-stations', 'vff:probe-aerial-coverage', 'vff:prune-inactive-accounts', 'vff:recalculate-reputation', 'vff:reclassify-status', 'vff:refresh-current-era', 'vff:refresh-stale-figures', 'vff:remind-expiring-api-tokens',
        'vff:resolve-event-sections', 'vff:seed-off-network-lines', 'vff:select-home-images', 'vff:snap-stations', 'vff:verify-accidents', 'vff:warm-tiles',
    ];

    /** Arguments supplémentaires nécessaires à certaines commandes. */
    private const ARGS = [
        'vff:import-wikipedia-line' => ['url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Alpha_%C3%A0_Bravo'],
        'vff:import-lignes-oubliees' => ['--from-cache' => true],
        'vff:import-heritage-lines' => ['--from-cache' => true],
        'vff:import-secondary-network-dates' => ['--from-cache' => true],
        'vff:import-wikidata-lines' => ['--from-cache' => true],
        'vff:import-station-closures' => ['--from-cache' => true],
        'vff:import-station-passenger-ends' => ['--from-cache' => true],
        'vff:import-line-descriptions' => ['--from-cache' => true],
        // Caches du bac à sable vides : aucune requête réseau.
        'vff:import-accidents' => ['--offline' => true],
        'vff:import-argentanwebferro' => ['--offline' => true],
        'vff:verify-accidents' => ['--offline' => true],
        'vff:enrich-accidents' => ['--offline' => true],
        'vff:place-listed-stations' => ['--no-places' => true],
        'vff:import-panoramax-media' => ['--cache-only' => true],
        // Fichier de candidats d'une gare absente de la base : lu, rien à écrire.
        'vff:import-commons-candidates' => ['file' => 'tests/Fixtures/Commons/candidates-extrait.jsonl'],
        'vff:import-signalling-history' => ['--offline' => true],
        'vff:import-line-speeds' => ['--offline' => true],
        'vff:import-material-periods' => ['--offline' => true],
        'vff:import-gallica' => ['--offline' => true],
        // Caches du bac à sable vides : le jeu est ignoré, aucune requête réseau.
        'vff:import-open-data-media' => ['--offline' => true],
        'vff:import-sncf-archives' => ['--file' => 'tests/Fixtures/Sncf/archives-sncf-sardo-extrait.json'],
        'vff:import-sncf-attendance' => ['--cache-dir' => 'tests/Fixtures/SncfAttendance'],
        'vff:import-station-accessibility' => ['--cache-dir' => 'tests/Fixtures/SncfAccessibility'],
        'vff:import-station-heritage' => ['--cache-dir' => 'tests/Fixtures/Heritage'],
        'vff:import-station-names' => ['--from-cache' => true],
        // Caches du bac à sable vides : aucune requête réseau (BnF, Wikidata, Wikipédia).
        'vff:import-bibliography' => ['--offline' => true],
        'vff:import-sncf-grades' => ['--cache-dir' => 'tests/Fixtures/Sncf'],
        'vff:import-sncf-platforms' => ['--cache-dir' => 'tests/Fixtures/Sncf'],
        'vff:import-sncf-radio' => ['--cache-dir' => 'tests/Fixtures/Sncf'],
        'vff:import-sncf-track-regime' => ['--cache-dir' => 'tests/Fixtures/Sncf'],
        'vff:audit-stations' => ['--no-bdtopo' => true],
        'vff:import-station-wikidata-dates' => ['--from-cache' => true],
    ];

    /** Tables des sources, des comptes, du matériel et des accidents : hors du moteur, mais qu'un --dry-run ne doit pas toucher non plus. */
    private const OTHER_TABLES = [
        'users', 'contributions', 'contribution_approvals', 'audit_events', 'materials', 'line_materials', 'line_material_periods', 'line_greenways', 'line_signalling_events', 'line_signalling_history_map', 'accidents', 'accident_lines', 'accident_sources', 'accident_references', 'accident_context_entries', 'accident_images',
        'argentanwebferro_articles', 'argentanwebferro_facts', 'argentanwebferro_photos', 'line_archive_documents', 'line_archive_records', 'bibliography_entries', 'bibliography_links',
        'station_audits', 'station_date_candidates', 'segment_audits', 'station_platforms', 'line_track_portions', 'line_radio_portions', 'station_attendances', 'station_accessibility', 'station_heritage',
    ];

    private Era $past;

    private Era $current;

    private Line $alpha;

    private Line $anonymous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        Notification::fake();
        $this->useCorrections([]);
        $this->buildDataset();

        // Toute source externe répond « rien de neuf » (et jamais par le réseau réel).
        Http::fake([
            'query.wikidata.org/*' => Http::response(['head' => ['vars' => []], 'results' => ['bindings' => []]]),
            '*wikipedia.org/*' => Http::response(['batchcomplete' => '', 'query' => ['pages' => []], 'parse' => ['title' => 'Ligne de Alpha à Bravo', 'wikitext' => "{{Infobox Ligne ferroviaire\n| nomligne = Alpha à Bravo\n| surtitre = Ligne de\n| numéro = 100000\n}}\nLa '''ligne de Alpha à Bravo''' est une ligne ferroviaire française longue de dix-sept kilomètres.\n== Histoire ==\nLa ligne est mise en service le 1er juin 1855."]]),
            '*' => Http::response(['results' => [], 'total_count' => 0, 'elements' => [], 'features' => []]),
        ]);
    }

    /** @return iterable<string, array{string}> */
    public static function dryRunCommands(): iterable
    {
        foreach (self::COVERED as $name) {
            yield $name => [$name];
        }
    }

    /**
     * Commandes en cours d'écriture par un autre chantier, qui échouent ici :
     * testées à part (groupe known-bug), à retirer de la liste une fois corrigées.
     */
    private const KNOWN_BUGS = [
        // (vide) vff:import-station-closures corrigée le 2026-10-19 : jsonb_exists() au lieu de l'opérateur « ? ».
    ];

    #[DataProvider('dryRunCommands')]
    public function test_dry_run_writes_nothing(string $name): void
    {
        if (in_array($name, self::KNOWN_BUGS, true)) {
            $this->markTestSkipped("{$name} : cf. test known-bug test_dry_run_of_commands_with_known_bugs");
        }
        $before = $this->snapshotAll();

        $this->artisan($name, ['--dry-run' => true] + (self::ARGS[$name] ?? []))->run();

        $this->assertSame($before, $this->snapshotAll(), "{$name} --dry-run a modifié la base");
    }

    #[Group('known-bug')]
    public function test_dry_run_of_commands_with_known_bugs(): void
    {
        if (self::KNOWN_BUGS === []) {
            $this->markTestSkipped('Aucune commande --dry-run en bug connu.');
        }
        foreach (self::KNOWN_BUGS as $name) {
            $before = $this->snapshotAll();
            $this->artisan($name, ['--dry-run' => true] + (self::ARGS[$name] ?? []))->run();
            $this->assertSame($before, $this->snapshotAll(), "{$name} --dry-run a modifié la base");
        }
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<string>}> */
    public static function controls(): iterable
    {
        yield 'slugs osm-*' => ['vff:canonicalize-station-slugs', [], ['stations', 'station_redirects']];
        yield 'dates des noms de tracés' => ['vff:dates-from-trace-names', [], ['line_events']];
        yield 'statut SNCF mort' => ['vff:reclassify-status', [], ['line_segments']];
        yield 'tracé SNCF manquant' => ['vff:fill-missing-geometry', [], ['line_segments']];
        yield 'classification' => ['vff:classify-lines', [], ['line_segments', 'line_sources']];
        yield 'dédoublonnage des gares' => ['vff:consolidate-stations', [], ['stations', 'station_sources']];
        yield 'rattachement des gares' => ['vff:attach-stations', [], ['station_lines']];
        yield 'audit des gares' => ['vff:audit-stations', ['--no-bdtopo' => true], ['station_audits']];
        yield 'quais SNCF' => ['vff:import-sncf-platforms', ['--cache-dir' => 'tests/Fixtures/Sncf'], ['station_platforms']];
        yield 'fréquentation des gares SNCF' => ['vff:import-sncf-attendance', ['--cache-dir' => 'tests/Fixtures/SncfAttendance'], ['station_attendances']];
        yield 'radio sol-train SNCF' => ['vff:import-sncf-radio', ['--cache-dir' => 'tests/Fixtures/Sncf'], ['line_radio_portions']];
        yield 'régime d\'exploitation SNCF' => ['vff:import-sncf-track-regime', ['--cache-dir' => 'tests/Fixtures/Sncf'], ['line_track_portions', 'lines', 'line_sources']];
        yield 'comptes inutilisés' => ['vff:prune-inactive-accounts', [], ['users', 'audit_events']];
        yield 'réputation des comptes' => ['vff:recalculate-reputation', [], ['users']];
    }

    /** Témoin : sans --dry-run, la commande a bien du travail sur ce jeu de données. */
    #[DataProvider('controls')]
    public function test_without_dry_run_the_same_command_writes(string $name, array $args, array $tables): void
    {
        $before = $this->snapshot($tables);

        $this->artisan($name, $args)->assertSuccessful();

        $this->assertNotSame($before, $this->snapshot($tables), "{$name} n'a rien écrit : le test --dry-run ne prouve rien");
    }

    public function test_apply_event_corrections_dry_run_then_real_run(): void
    {
        $this->useCorrections([[
            'line' => '100000', 'action' => 'add', 'type' => 'reopening', 'date' => '2010-12-01', 'precision' => 'month',
            'from' => 'Alpha', 'to' => 'Bravo', 'source_url' => 'https://example.org/source', 'quote' => 'Rouverte en 2010.',
            'reason' => 'réouverture attestée',
        ]]);
        $before = $this->snapshotAll();

        $this->artisan('vff:apply-event-corrections', ['--dry-run' => true])->expectsOutputToContain('à ajouter')->assertSuccessful();
        $this->assertSame($before, $this->snapshotAll());

        $this->artisan('vff:apply-event-corrections')->expectsOutputToContain('ajouté')->assertSuccessful();
        $this->assertSame(1, DB::table('line_events')->where('source', 'manual')->count());
    }

    /** Terminus relus à la main (vff:attach-stations --years-only) : rien d'écrit à blanc, le rattachement ensuite. */
    public function test_attach_stations_reviewed_termini_dry_run_then_real_run(): void
    {
        $this->app->instance(StationTerminusOverrides::class, new StationTerminusOverrides([[
            'line' => $this->alpha->slug, 'terminus' => 'Bravo', 'decision' => 'attach', 'station' => 'bravo',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Bravo&oldid=1', 'reason' => 'gare d\'extrémité',
        ]]));
        $before = $this->snapshotAll();

        $this->artisan('vff:attach-stations', ['--years-only' => true, '--dry-run' => true])->expectsOutputToContain('terminus relus : 1 ajoutés')->assertSuccessful();
        $this->assertSame($before, $this->snapshotAll());

        $this->artisan('vff:attach-stations', ['--years-only' => true])->assertSuccessful();
        $this->assertSame('terminus_reviewed', DB::table('station_lines')->where('line_id', $this->alpha->id)
            ->where('station_id', DB::table('stations')->where('slug', 'bravo')->value('id'))->value('method'));
    }

    /** @return array<string, array{rows: int, md5: string|null}> */
    private function snapshotAll(): array
    {
        return $this->snapshot([...self::SNAPSHOT_TABLES, ...self::OTHER_TABLES]);
    }

    // ─── Jeu de données ────────────────────────────────────────────────

    private function buildDataset(): void
    {
        $this->past = $this->era(1930);
        $this->current = $this->era(2030, current: true);

        $this->alpha = $this->rfnLine('100000', 'Ligne de Alpha à Bravo', ['wikipedia_title' => 'Ligne de Alpha à Bravo']);
        foreach ([$this->past, $this->current] as $era) {
            $this->track($this->alpha, $era, [[48.80, 2.30], [48.85, 2.35], [48.90, 2.40]]);
        }
        $this->anonymous = $this->rfnLine('200000', 'Ligne 200000');

        // SNCF : le tronçon de 100000 est fermé et déposé ; 200000 n'a qu'un tracé SNCF.
        DB::insert("INSERT INTO sncf_line_geometries (code_ligne, libelle, geom, created_at, updated_at) VALUES
            ('100000', 'Fermée et déposée (Plus utilisable)', ST_GeomFromText('LINESTRING(2.30 48.80, 2.35 48.85, 2.40 48.90)', 4326), NOW(), NOW()),
            ('200000', 'Exploitée', ST_GeomFromText('LINESTRING(3.00 47.00, 3.10 47.10)', 4326), NOW(), NOW())");
        DB::table('sncf_gares')->insert([
            ['code_ligne' => '100000', 'libelle' => 'ALPHA', 'voyageurs' => true, 'fret' => true, 'pk_m' => 0, 'lat' => 48.80, 'lon' => 2.30],
            ['code_ligne' => '100000', 'libelle' => 'BRAVO', 'voyageurs' => true, 'fret' => false, 'pk_m' => 17_000, 'lat' => 48.90, 'lon' => 2.40],
        ]);

        // Gare à slug technique (osm-*), gare voisine du tracé.
        $this->station('osm-123-gare-d-alpha', "Gare d'Alpha", 48.8002, 2.3003, ['osm_node_id' => 123]);
        $this->station('bravo', 'Bravo', 48.9003, 2.4004);

        // Tracé archeoferroviaire daté par son nom, et événement à portion à résoudre.
        DB::insert("INSERT INTO source_traces (source, source_ref, layer, name, line_id, length_m, seen_at, created_at, updated_at, geom) VALUES
            ('archeoferroviaire', 'archeo-1', 'disparue', 'Tracé initial (jusqu''en 1876)', ?, 17000, NOW(), NOW(), NOW(),
             ST_GeomFromText('LINESTRING(2.30 48.80, 2.35 48.85, 2.40 48.90)', 4326))", [$this->alpha->id]);
        DB::table('line_events')->insert([
            'line_id' => $this->alpha->id, 'type' => 'opening', 'event_date' => '1855-06-01', 'date_precision' => 'day',
            'description' => "Ouverture d'Alpha à Bravo", 'source' => 'wikipedia', 'section_from' => 'Alpha', 'section_to' => 'Bravo',
            'section_resolution' => 'pending', 'fingerprint' => 'wikipedia:test:1', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Compte inutilisé depuis plus de trois ans : à avertir (ou à supprimer une fois averti).
        DB::table('users')->insert([
            'name' => 'Ancien membre', 'email' => 'ancien@example.org', 'password' => 'x',
            'created_at' => now()->subYears(4), 'updated_at' => now()->subYears(4), 'last_seen_at' => now()->subYears(4),
        ]);

        // Membre dont une contribution est appliquée alors que ses compteurs de réputation sont à zéro :
        // à recalculer (vue récemment, hors de portée de la suppression des comptes inutilisés).
        $author = DB::table('users')->insertGetId([
            'name' => 'Auteur', 'email' => 'auteur@example.org', 'password' => 'x', 'last_seen_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('contributions')->insert([
            'user_id' => $author, 'type' => 'line_segment', 'action' => 'update', 'status' => 'applied',
            'payload' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Sources de gares : SNCF + OSM au même endroit (même gare), à dédoublonner.
        DB::insert("INSERT INTO station_sources (source, source_ref, name, name_norm, kind, lifecycle, uic_code, passenger, seen_at, created_at, updated_at, geom) VALUES
            ('sncf', '87000001', 'Alpha', 'alpha', 'station', 'active', '87000001', true, NOW(), NOW(), NOW(), ST_SetSRID(ST_MakePoint(2.3001, 48.8001), 4326)),
            ('osm', 'node/123', 'Gare d''Alpha', 'alpha', 'station', 'active', NULL, NULL, NOW(), NOW(), NOW(), ST_SetSRID(ST_MakePoint(2.3004, 48.8003), 4326))");
    }

    /** @param  list<array<string, mixed>>  $corrections */
    private function useCorrections(array $corrections): void
    {
        $this->useDataFile('line_event_corrections.php', $corrections);
    }
}
