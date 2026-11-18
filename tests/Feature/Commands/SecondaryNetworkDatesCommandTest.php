<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\ImportSecondaryNetworkDates;
use App\Models\Line;
use App\Services\PlaceNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-secondary-network-dates : articles Wikipédia des réseaux
 * secondaires (cache local, aucun appel réseau) → événements datés des
 * lignes VFF du réseau secondaire, rapports, estimations par réseau.
 *
 * Repères : à 50° N, 0,1° de latitude ≈ 11,1 km.
 */
class SecondaryNetworkDatesCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const SOMME = <<<'WT'
        Les '''chemins de fer départementaux de la Somme''' formaient un réseau de voies métriques du département de la Somme.

        == Les lignes ==
        * [[Albert (Somme)|Albert]] - [[Montdidier (Somme)|Montdidier]], 28 km, ouverte le 1er mai 1889, fermée le 1er mars 1949.
        * Doullens - Beauval, 11 km, ouverte le 10 mars 1889, fermée le 15 juin 1938.

        [[Catégorie:Ancien chemin de fer secondaire en France]]
        WT;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
        foreach ([['80016', 'Albert', 2.0, 50.0], ['80561', 'Montdidier', 2.0, 49.75], ['80253', 'Doullens', 3.0, 50.0],
            ['80070', 'Beauval', 3.0, 49.9], ['80021', 'Amiens', 2.3, 49.9], ['80001', 'Abbeville', 1.8, 50.1]] as [$insee, $nom, $lng, $lat]) {
            DB::insert("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, population, geom, created_at, updated_at)
                VALUES (?, 'commune-actuelle', ?, ?, '80', 5000, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())",
                [$insee, $nom, PlaceNames::normalize($nom), $lng, $lat]);
        }
    }

    // ─── utilitaires ─────────────────────────────────────────────────────

    /** @param  array<string, array{title: string, revid: int, content: string}>  $pages */
    private function cache(array $pages): void
    {
        $dir = storage_path('app/sources/secondary-networks');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/pages.json', json_encode($pages, JSON_UNESCAPED_UNICODE));
    }

    private function page(string $title, string $content, int $revid = 1000): array
    {
        return [$title => ['title' => $title, 'revid' => $revid, 'content' => $content]];
    }

    private function line(string $name, string $network, string $wkt, ?string $code = null): Line
    {
        $line = Line::factory()->create(['name' => $name, 'slug' => 'archeo-'.Str::slug($name), 'network' => $network,
            'code' => $code, 'type' => null]);
        DB::insert("INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
            VALUES (?, ROUND(ST_Length(ST_GeomFromText(?, 4326)::geography)), '[]'::jsonb, ST_Multi(ST_GeomFromText(?, 4326)), now(), now())",
            [$line->id, $wkt, $wkt]);

        return $line;
    }

    /** @return list<array<string, mixed>> */
    private function events(Line $line, ?string $source = null): array
    {
        return DB::table('line_events')->where('line_id', $line->id)
            ->when($source, fn ($q) => $q->where('source', $source))
            ->orderBy('event_date')->get()->map(fn ($e) => (array) $e)->all();
    }

    /** @return list<array<string, string>> */
    private function report(string $name): array
    {
        $file = storage_path("app/reports/reseaux-secondaires-{$name}.csv");
        $fh = fopen($file, 'r');
        $head = fgetcsv($fh, escape: '');
        $rows = [];
        while ($head && ($r = fgetcsv($fh, escape: '')) !== false) {
            $rows[] = array_combine($head, $r);
        }
        fclose($fh);

        return $rows;
    }

    // ─── scénario ────────────────────────────────────────────────────────

    public function test_records_date_the_undated_secondary_lines(): void
    {
        $this->cache($this->page('Chemins de fer départementaux de la Somme', self::SOMME, 4242));
        $albert = $this->line('Ligne d\'Albert à Montdidier', 'secondaire', 'LINESTRING(2.0 50.0, 2.0 49.75)');
        $doullens = $this->line('Ligne de Doullens à Beauval', 'secondaire', 'LINESTRING(3.0 50.0, 3.0 49.9)');
        // Déjà datée par une autre source (ouverture seule) : seules les fins sont écrites.
        DB::table('line_events')->insert(['line_id' => $doullens->id, 'type' => 'opening', 'event_date' => '1889-03-10', 'description' => 'x',
            'source' => 'lignes-oubliees', 'confidence' => 'high', 'date_precision' => 'day', 'section_resolution' => 'whole_line',
            'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true])
            ->expectsOutputToContain('Articles : 1')
            ->expectsOutputToContain('  2 fiches, dont 2 datées (1 articles)')
            ->expectsOutputToContain('  Lignes VFF avec tracé de référence : 2 (dont 1 sans ouverture exploitable)')
            ->expectsOutputToContain('✓ 2 lignes datées (39 km) à partir de 1 articles · 3 événements écrits · 0 obsolètes supprimés')
            ->expectsTable(['type', 'événements'], [['closure', '2'], ['opening', '1']])
            ->assertSuccessful();

        $url = 'https://fr.wikipedia.org/w/index.php?title=Chemins_de_fer_d%C3%A9partementaux_de_la_Somme&oldid=4242';
        $e = $this->events($albert);
        $this->assertSame([['opening', '1889-05-01', 'day', 'high'], ['closure', '1949-03-01', 'day', 'high']],
            array_map(fn ($x) => [$x['type'], $x['event_date'], $x['date_precision'], $x['confidence']], $e));
        foreach ($e as $x) {
            $this->assertSame(['wikipedia-reseaux', $url, 'pending', null, null], [$x['source'], $x['source_url'], $x['section_resolution'], $x['section_from'], $x['section_to']]);
            $this->assertSame('Albert - Montdidier, 28 km, ouverte le 1er mai 1889, fermée le 1er mars 1949.', $x['source_quote']);
            $this->assertStringStartsWith('wpres:', $x['fingerprint']);
        }
        $this->assertSame('Ouverture sur toute la ligne (Albert - Montdidier, article Wikipédia « Chemins de fer départementaux de la Somme »)', $e[0]['description']);

        $d = $this->events($doullens);
        $this->assertSame([['opening', '1889-03-10', 'lignes-oubliees'], ['closure', '1938-06-15', 'wikipedia-reseaux']],
            array_map(fn ($x) => [$x['type'], $x['event_date'], $x['source']], $d), 'ligne déjà ouverte ailleurs : sa fin seulement');

        $rows = $this->report('evenements');
        $this->assertCount(3, $rows);
        $this->assertSame(['write', 'toute la ligne', 'Chemins de fer départementaux de la Somme'], [$rows[0]['statut'], $rows[0]['portion'], $rows[0]['article']]);
        $this->assertSame([], $this->report('a-verifier'));
        $json = json_decode(File::get(storage_path('app/reports/reseaux-secondaires-import.json')), true);
        $this->assertSame([3, 2, false, ['closure' => 2, 'opening' => 1]], [$json['events_written'], $json['lines_written'], $json['dry_run'], $json['by_type']]);
        $this->assertCount(2, json_decode(File::get(storage_path('app/sources/secondary-networks/records.json')), true));
        Http::assertNothingSent();
    }

    private const SOMME_FULL = <<<'WT'
        Les '''chemins de fer départementaux de la Somme''' formaient un réseau de voies métriques du département de la Somme, exploité de 1889 à 1955.

        == Les lignes ==
        * [[Albert (Somme)|Albert]] - [[Montdidier (Somme)|Montdidier]], 28 km, ouverte le 1er mai 1889, fermée le 1er mars 1949.
        * Amiens - Doullens, ouverte le 3 juin 1891.
        * Beauval - Abbeville, ouverte en 1850, fermée en 1900.

        == Fermeture ==
        Toutes les lignes du réseau sont fermées le 1er mars 1955.

        [[Catégorie:Ancien chemin de fer secondaire en France]]
        WT;

    /** @return array<string, Line> */
    private function fullScenario(): array
    {
        $this->cache($this->page('Chemins de fer départementaux de la Somme', self::SOMME_FULL, 4242)
            + $this->page('Tramways de Genève', "Les tramways de Genève.\n* Albert - Montdidier, ouverte en 1900, fermée en 1950.\n[[Catégorie:Tramway en Suisse]]"));

        return [
            'section' => $this->line('Ligne d\'Abbeville à Montdidier', 'secondaire', 'LINESTRING(1.8 50.1, 2.0 50.0, 2.0 49.75)'),
            'rfn' => $this->line('Ligne d\'Amiens à Doullens', 'principal', 'LINESTRING(2.3 49.9, 3.0 50.0)', '311000'),
            'early' => $this->line('Ligne de Beauval à Abbeville', 'secondaire', 'LINESTRING(3.0 49.9, 1.8 50.1)'),
            'estimated' => $this->line('Ligne d\'Amiens à Beauval', 'secondaire', 'LINESTRING(2.3 49.9, 3.0 49.9)'),
        ];
    }

    public function test_sections_guards_and_network_estimates(): void
    {
        $l = $this->fullScenario();

        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true])
            ->expectsOutputToContain('Articles : 2')
            ->expectsOutputToContain('  3 fiches, dont 3 datées (1 articles)')
            ->expectsOutputToContain('✓ 2 lignes datées (96 km) à partir de 1 articles · 5 événements écrits · 0 obsolètes supprimés')
            ->expectsOutputToContain('  dont estimations par réseau (estimation-reseau, confiance low) : 1 lignes entièrement estimées (50 km), 0 complétées par estimation (0 km)')
            ->expectsOutputToContain('  Lignes rapprochées non écrites : 1 · fiches datées non rattachées : 1')
            ->assertSuccessful();

        // Fiche = portion de la ligne : section à résoudre, fin du réseau pour le reste.
        $this->assertSame([
            ['opening', '1889-05-01', 'high', 'Albert', 'Montdidier'],
            ['closure', '1949-03-01', 'high', 'Albert', 'Montdidier'],
            ['closure', '1955-03-01', 'low', null, null],
        ], array_map(fn ($x) => [$x['type'], $x['event_date'], $x['confidence'], $x['section_from'], $x['section_to']], $this->events($l['section'])));
        $this->assertSame('Ouverture de la section Albert - Montdidier (Albert - Montdidier, article Wikipédia « Chemins de fer départementaux de la Somme »)',
            $this->events($l['section'])[0]['description']);
        $this->assertSame('Fermeture (tout trafic) au plus tard à la fin de l\'exploitation du réseau, sur toute la ligne (article Wikipédia « Chemins de fer départementaux de la Somme »)',
            $this->events($l['section'])[2]['description']);

        // Ligne du RFN : jamais datée par un article de réseau secondaire.
        $this->assertSame([], $this->events($l['rfn']));
        $review = $this->report('a-verifier');
        $this->assertSame(['Amiens - Doullens', 'opening 1891-06-03'], [$review[0]['fiche'], $review[0]['evenements']]);
        $this->assertStringContainsString('ligne du RFN (code SNCF)', $review[0]['raison']);

        // Ouverture avant 1865 : à vérifier, rien d'écrit, pas d'estimation non plus.
        $this->assertSame([], $this->events($l['early']));
        $skipped = array_values(array_filter($this->report('evenements'), fn ($r) => $r['line_id'] === (string) $l['early']->id));
        $this->assertSame(['skip', 'ouverture avant 1865 pour une ligne du réseau secondaire (antérieure à la loi sur l\'intérêt local) : à vérifier'],
            [$skipped[0]['statut'], $skipped[0]['raison']]);

        // Estimation par réseau : dates du réseau, à l'année, toute la ligne, confiance faible.
        $est = $this->events($l['estimated']);
        $this->assertSame([['opening', '1889-01-01', 'year', 'low', 'whole_line'], ['closure', '1955-01-01', 'year', 'low', 'whole_line']],
            array_map(fn ($x) => [$x['type'], $x['event_date'], $x['date_precision'], $x['confidence'], $x['section_resolution']], $est));
        foreach ($est as $x) {
            $this->assertSame(['estimation-reseau', 'estimation par réseau (date non sourcée pour cette ligne)'], [$x['source'], $x['section_resolution_note']]);
            $this->assertStringStartsWith('estres:', $x['fingerprint']);
            $this->assertStringContainsString('date à confirmer, jamais une date sûre', $x['description']);
        }
        $estimates = $this->report('estimations');
        $this->assertSame(['non estimée', 'ouverture avant 1865 à vérifier : pas d\'estimation'], [$estimates[0]['statut'], $estimates[0]['raison']]);

        $json = json_decode(File::get(storage_path('app/reports/reseaux-secondaires-import.json')), true);
        $this->assertSame(1, $json['articles_foreign_skipped'], 'article suisse écarté');
    }

    public function test_no_estimate_option(): void
    {
        $l = $this->fullScenario();

        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true, '--no-estimate' => true])->assertSuccessful();

        $this->assertSame([], $this->events($l['estimated']));
        $this->assertFileDoesNotExist(storage_path('app/reports/reseaux-secondaires-estimations.csv'));
    }

    public function test_dry_run_writes_reports_only(): void
    {
        $l = $this->fullScenario();
        $before = $this->snapshot(['line_events']);

        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] 2 lignes datées (96 km) à partir de 1 articles · 5 événements')
            ->doesntExpectOutputToContain('écrits ·')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['line_events']));
        $this->assertCount(7, $this->report('evenements'));
        $this->assertTrue(json_decode(File::get(storage_path('app/reports/reseaux-secondaires-import.json')), true)['dry_run']);
        unset($l);
    }

    public function test_rerun_is_stable_and_drops_stale_events(): void
    {
        $l = $this->fullScenario();
        $this->assertSame(0, Artisan::call('vff:import-secondary-network-dates', ['--from-cache' => true]));
        $before = $this->snapshot(['line_events'], ['id']);

        // 2e passage dans le même processus : mêmes événements, même bilan, rapport non doublé.
        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true])
            ->expectsOutputToContain('5 événements écrits · 0 obsolètes supprimés')
            ->expectsOutputToContain('  Lignes rapprochées non écrites : 1 · fiches datées non rattachées : 1')
            ->assertSuccessful();
        $this->assertSame($before, $this->snapshot(['line_events'], ['id']));
        $this->assertCount(1, $this->report('a-verifier'));

        // L'article ne cite plus Albert - Montdidier : passe partielle (--article) sans nettoyage, passe complète avec.
        $this->cache($this->page('Chemins de fer départementaux de la Somme', str_replace('* [[Albert (Somme)|Albert]] - [[Montdidier (Somme)|Montdidier]], 28 km, ouverte le 1er mai 1889, fermée le 1er mars 1949.', '', self::SOMME_FULL), 4243));
        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true, '--article' => ['Chemins de fer départementaux de la Somme']])
            ->expectsOutputToContain('Articles : 1')
            ->assertSuccessful();
        $this->assertSame(2, DB::table('line_events')->where('line_id', $l['section']->id)->where('section_from', 'Albert')->count());

        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('line_events')->where('line_id', $l['section']->id)->where('section_from', 'Albert')->count());
        $this->assertGreaterThan(0, json_decode(File::get(storage_path('app/reports/reseaux-secondaires-import.json')), true)['deleted']);
    }

    public function test_from_cache_without_cache(): void
    {
        $this->artisan('vff:import-secondary-network-dates', ['--from-cache' => true])
            ->expectsOutputToContain('Pas de cache : ')
            ->expectsOutputToContain('Articles : 0')
            ->assertSuccessful();
        Http::assertNothingSent();
    }

    // ─── découverte (--refresh, --discover-undated) ──────────────────────

    /** Wikipédia simulé : catégories, liens des pages-listes, recherches, contenus. */
    private function fakeWikipedia(array &$searches): void
    {
        $pages = [
            'Chemins de fer départementaux de la Somme' => self::SOMME,
            'Tramway de Doullens à Beauval' => "Le tramway.\n[[Catégorie:Ancien tramway en France]]",
            "Tramways de l'Ain" => "Les tramways de l'Ain.\n[[Catégorie:Ancien tramway en France]]",
            'Tramway d\'Amiens à Beauval' => "Le '''tramway d'Amiens à Beauval''' est ouvert le 2 juin 1892 et fermé le 1er janvier 1930.\n[[Catégorie:Ancien tramway en France]]",
        ];
        Http::fake(function ($request) use ($pages, &$searches) {
            $q = $request->method() === 'GET' ? $request->data() : $request->data();
            if (($q['list'] ?? null) === 'categorymembers') {
                return Http::response(['query' => ['categorymembers' => match ($q['cmtitle']) {
                    'Catégorie:Ancienne compagnie de chemin de fer secondaire en France' => [
                        ['ns' => 0, 'title' => 'Chemins de fer départementaux de la Somme'],
                        ['ns' => 0, 'title' => 'Gare d\'Albert'],
                        ['ns' => 14, 'title' => 'Catégorie:Tramways de la Somme'],
                    ],
                    'Catégorie:Tramways de la Somme' => [['ns' => 0, 'title' => 'Tramway de Doullens à Beauval'], ['ns' => 0, 'title' => 'Musée des tramways']],
                    default => [],
                }]]);
            }
            if (($q['prop'] ?? null) === 'links') {
                return Http::response(['query' => ['pages' => [['title' => $q['titles'], 'links' => $q['titles'] === 'Liste des tramways en France' ? [['ns' => 0, 'title' => "Tramways de l'Ain"]] : []]]]]);
            }
            if (($q['list'] ?? null) === 'search') {
                $searches[] = $q['srsearch'];

                return Http::response(['query' => ['search' => str_contains($q['srsearch'], '"Amiens" "Beauval"')
                    ? [['title' => 'Tramway d\'Amiens à Beauval'], ['title' => 'Autocars de la Somme']] : []]]);
            }
            if (($q['prop'] ?? null) === 'revisions') {
                $out = [];
                foreach (explode('|', $q['titles']) as $i => $t) {
                    $out[] = isset($pages[$t])
                        ? ['title' => $t, 'revisions' => [['revid' => 100 + $i, 'slots' => ['main' => ['content' => $pages[$t]]]]]]
                        : ['title' => $t, 'missing' => true];
                }

                return Http::response(['query' => ['pages' => $out]]);
            }

            return Http::response(['error' => ['code' => 'unexpected']], 400);
        });
    }

    public function test_refresh_discovers_downloads_and_caches_articles(): void
    {
        $searches = [];
        $this->fakeWikipedia($searches);
        Sleep::fake();
        $albert = $this->line('Ligne d\'Albert à Montdidier', 'secondaire', 'LINESTRING(2.0 50.0, 2.0 49.75)');
        $amiens = $this->line('Ligne d\'Amiens à Beauval', 'secondaire', 'LINESTRING(2.3 49.9, 3.0 49.9)');

        $this->artisan('vff:import-secondary-network-dates', ['--refresh' => true, '--discover-undated' => true, '--no-estimate' => true])
            ->expectsOutputToContain('  Titres retenus : 8')
            ->expectsOutputToContain('  Articles téléchargés : 3')
            ->expectsOutputToContain('  Recherche ciblée : 2 lignes sans ouverture, 1 nouveaux articles candidats')
            ->expectsOutputToContain('  Articles en cache : 4')
            ->expectsOutputToContain('Articles : 4')
            ->assertSuccessful();

        $dir = storage_path('app/sources/secondary-networks');
        $this->assertSame(['Chemins de fer départementaux de la Somme', "Liste d'anciennes lignes de chemin de fer et de tramway dans le Pas-de-Calais",
            'Liste des chemins de fer à voie métrique de France', 'Liste des lignes ferroviaires de Meurthe-et-Moselle', 'Liste des tramways en France',
            'Liste des voies ferrées de la Manche', 'Tramway de Doullens à Beauval', "Tramways de l'Ain"],
            json_decode(File::get($dir.'/titles.json'), true), 'gares et musées écartés, pages-listes gardées');
        $this->assertSame(['Tramway d\'Amiens à Beauval'], json_decode(File::get($dir.'/titles-undated.json'), true), 'autocars écartés');
        $this->assertCount(4, json_decode(File::get($dir.'/pages.json'), true));
        $this->assertContains('"Amiens" "Beauval" tramway OR "chemin de fer"', $searches);
        Sleep::assertSleptTimes(2);

        $this->assertSame([['opening', '1889-05-01'], ['closure', '1949-03-01']],
            array_map(fn ($x) => [$x['type'], $x['event_date']], $this->events($albert)));
        $this->assertSame([['opening', '1892-06-02'], ['closure', '1930-01-01']],
            array_map(fn ($x) => [$x['type'], $x['event_date']], $this->events($amiens)), 'article trouvé par la recherche ciblée');
        $this->assertStringContainsString('Tramway_d%27Amiens_%C3%A0_Beauval&oldid=100', $this->events($amiens)[0]['source_url']);
    }

    public function test_rail_titles(): void
    {
        foreach (['Tramway d\'Annecy à Thônes', 'Chemins de fer départementaux de la Somme', 'Réseau breton', 'Ligne de Saint-Brieuc à Moncontour', 'Compagnie des tramways de l\'Ain'] as $t) {
            $this->assertTrue(ImportSecondaryNetworkDates::isRailTitle($t), $t);
        }
        foreach (['Gare de Lyon', 'Autocars de la Somme', 'Ligne 5 du tramway', 'Liste des tramways en France', 'Chronologie des chemins de fer',
            'Musée des tramways à vapeur', 'Tramway touristique de la vallée', 'Réseau de bus de Lille', 'Tramways de Genève'] as $t) {
            $this->assertFalse(ImportSecondaryNetworkDates::isRailTitle($t), $t);
        }
    }
}
