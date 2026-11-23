<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectMergedStationSlug;
use App\Mcp\Support\Lookup;
use App\Models\Accident;
use App\Models\Line;
use App\Models\Media;
use App\Models\Station;
use App\Services\StationCorrections;
use App\Services\StationMerger;
use App\Support\StationNames;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Fusion de deux fiches d'une même gare (action « merge » de gares_corrections.php), appliquée par
 * vff:consolidate-stations : cas de Pleine-Fougères (fiche SNCF avec l'UIC à 164 m des coordonnées du tableau
 * des gares, ancienne gare OSM à 6 m, gardées distinctes par la règle de l'ancien bâtiment à plus de 150 m).
 */
class StationMergeTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const SNCF = [48.5308625, -1.5646822];

    private const OSM = [48.5297585, -1.5660572];

    /** @param  array<string, mixed>  $attributes */
    private function source(string $source, string $ref, string $name, array $latLng, array $attributes = []): int
    {
        return DB::table('station_sources')->insertGetId($attributes + [
            'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'active', 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $latLng[1], $latLng[0])),
        ]);
    }

    /** @return array<string, mixed> */
    private function merge(string $position = 'absorbed', array $override = []): array
    {
        return $override + [
            'station' => ['name' => 'Pleine-Fougères', 'uic' => '87478255'],
            'action' => 'merge',
            'absorbed' => ['name' => 'Pleine-Fougères', 'osm' => 'n2514130142'],
            'position' => $position,
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Rennes&oldid=237081548',
            'reason' => 'une seule gare au schéma de la ligne',
        ];
    }

    /** @param  list<array<string, mixed>>  $corrections */
    private function consolidate(array $corrections): void
    {
        $this->app->instance(StationCorrections::class, new StationCorrections($corrections));
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
    }

    /**
     * Deux fiches consolidées sans fusion, avec ce qui pointe vers la fiche OSM.
     *
     * @return array{0: Station, 1: Station}
     */
    private function twoStations(): array
    {
        $this->source('sncf', '87478255', 'Pleine-Fougères', self::SNCF, ['uic_code' => '87478255', 'freight' => true]);
        $this->source('osm', 'n2514130142', 'Pleine-Fougères', self::OSM, ['lifecycle' => 'razed']);
        $this->source('argentanwebferro', 'awf-1', 'Pleine-Fougères', self::OSM, ['lifecycle' => 'unknown']);
        $this->consolidate([]);
        $kept = Station::where('uic_code', '87478255')->sole();
        $absorbed = Station::whereKeyNot($kept->id)->sole();
        $this->assertSame(['pleine-fougeres', 2], [$kept->slug, Station::count()], 'sans fusion : deux fiches');

        return [$kept, $absorbed];
    }

    public function test_merge_keeps_one_station_with_the_kept_address_and_the_sourced_position(): void
    {
        [$kept, $absorbed] = $this->twoStations();

        $this->consolidate([$this->merge()]);

        $this->assertSame(1, Station::count());
        $st = DB::table('stations')->selectRaw('id, slug, uic_code, osm_node_id, position_source, position_flagged, sources_count, ST_X(geom) AS lng, ST_Y(geom) AS lat')->sole();
        $this->assertSame([$kept->id, 'pleine-fougeres', '87478255', 2514130142, 'osm', false, 3],
            [$st->id, $st->slug, $st->uic_code, $st->osm_node_id, $st->position_source, $st->position_flagged, $st->sources_count]);
        $this->assertEqualsWithDelta(self::OSM[1], (float) $st->lng, 1e-5, 'position de la fiche absorbée (6 m du tableau des gares)');
        $this->assertEqualsWithDelta(self::OSM[0], (float) $st->lat, 1e-5);
        $this->assertSame([$kept->id], DB::table('station_sources')->distinct()->pluck('station_id')->all());
        $this->assertSame($kept->id, DB::table('station_redirects')->where('old_slug', $absorbed->slug)->value('station_id'));

        // Deuxième passage : rien ne bouge (idempotent).
        $this->consolidate([$this->merge()]);
        $this->assertSame([[$kept->id, 'pleine-fougeres']], Station::query()->get(['id', 'slug'])->map(fn ($s) => [$s->id, $s->slug])->all());
    }

    public function test_merge_with_the_kept_position_keeps_the_sncf_point(): void
    {
        [$kept] = $this->twoStations();

        $this->consolidate([$this->merge('kept')]);

        $st = DB::table('stations')->selectRaw('id, position_source, ST_X(geom) AS lng')->sole();
        $this->assertSame([$kept->id, 'sncf'], [$st->id, $st->position_source]);
        $this->assertEqualsWithDelta(self::SNCF[1], (float) $st->lng, 1e-5);
    }

    public function test_merge_moves_media_lines_events_accidents_and_redirects_to_the_kept_station(): void
    {
        [$kept, $absorbed] = $this->twoStations();
        $line = DB::table('lines')->insertGetId(['slug' => 'rfn-415000', 'name' => 'Caen - Rennes', 'code' => '415000', 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('lines')->insertGetId(['slug' => 'rfn-999000', 'name' => 'Autre', 'code' => '999000', 'created_at' => now(), 'updated_at' => now()]);
        $link = fn (int $sid, int $lid, int $pk) => DB::table('station_lines')->insert(['station_id' => $sid, 'line_id' => $lid, 'method' => 'segment', 'pk_m' => $pk, 'created_at' => now(), 'updated_at' => now()]);
        $link($kept->id, $line, 121099);
        $link($absorbed->id, $line, 121000);
        $link($absorbed->id, $other, 500);
        $photo = Media::factory()->create(['mediable_type' => Station::class, 'mediable_id' => $absorbed->id, 'provider' => 'commons', 'external_id' => 'A.jpg']);
        Media::factory()->create(['mediable_type' => Station::class, 'mediable_id' => $kept->id, 'provider' => 'commons', 'external_id' => 'B.jpg']);
        $twin = Media::factory()->create(['mediable_type' => Station::class, 'mediable_id' => $absorbed->id, 'provider' => 'commons', 'external_id' => 'B.jpg']);
        $accident = Accident::factory()->create(['nearest_station_id' => $absorbed->id]);
        DB::table('station_events')->insert(['station_id' => $absorbed->id, 'type' => 'closure', 'event_date' => '1937-01-01', 'date_precision' => 'year',
            'source' => 'wikipedia', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_redirects')->insert(['old_slug' => 'osm-2514130142-pleine-fougeres', 'station_id' => $absorbed->id]);

        $this->consolidate([$this->merge()]);

        $this->assertSame([$line => 121099, $other => 500], DB::table('station_lines')->where('station_id', $kept->id)->orderBy('line_id')->pluck('pk_m', 'line_id')->all(),
            'rattachement de la fiche gardée sur la ligne commune, celui de l\'autre ligne repris');
        $this->assertSame(0, DB::table('station_lines')->where('station_id', '!=', $kept->id)->count());
        $this->assertSame($kept->id, $photo->fresh()?->mediable_id);
        $this->assertNull($twin->fresh(), 'même image déjà sur la fiche gardée : doublon retiré');
        $this->assertSame(2, Media::where('mediable_id', $kept->id)->count());
        $this->assertSame($kept->id, $accident->fresh()?->nearest_station_id);
        $this->assertSame(['1937-01-01'], DB::table('station_events')->where('station_id', $kept->id)->where('source', 'wikipedia')->pluck('event_date')->all());
        $this->assertSame([$absorbed->slug => $kept->id, 'osm-2514130142-pleine-fougeres' => $kept->id],
            DB::table('station_redirects')->orderByDesc('old_slug')->pluck('station_id', 'old_slug')->all());
    }

    public function test_merger_counts_what_it_moves_and_only_conflicts_with_the_same_kind_of_row(): void
    {
        $kept = Station::factory()->create();
        $absorbed = Station::factory()->create();
        $third = Station::factory()->create();
        // Même image sur une LIGNE dont l'id vaut celui de la fiche gardée : pas un doublon de la gare.
        Media::factory()->create(['mediable_type' => Line::class, 'mediable_id' => $kept->id, 'provider' => 'commons', 'external_id' => 'C.jpg']);
        $photo = Media::factory()->create(['mediable_type' => Station::class, 'mediable_id' => $absorbed->id, 'provider' => 'commons', 'external_id' => 'C.jpg']);
        // Adresse de la fiche absorbée déjà redirigée ailleurs (ancien rattachement) : elle mène désormais à la fiche gardée.
        DB::table('station_redirects')->insert(['old_slug' => $absorbed->slug, 'station_id' => $third->id]);

        $moved = app(StationMerger::class)->merge($absorbed->id, $kept->id);

        $this->assertSame($kept->id, $photo->fresh()?->mediable_id);
        $this->assertSame($kept->id, DB::table('station_redirects')->where('old_slug', $absorbed->slug)->value('station_id'));
        $this->assertSame(1, $moved['media.mediable_id']);
        $this->assertSame(0, $moved['media doublons']);
        $this->assertSame(0, $moved['accidents.nearest_station_id']);
        $this->assertCount(24, $moved);
        $this->assertSame([], app(StationMerger::class)->merge($kept->id, $kept->id));
    }

    public function test_old_address_of_the_absorbed_station_redirects_everywhere(): void
    {
        [$kept, $absorbed] = $this->twoStations();
        $this->consolidate([$this->merge()]);

        $this->get('/stations/'.$absorbed->slug)->assertStatus(301)->assertRedirect(route('stations.show', $kept->slug));
        $this->get('/api/v1/stations/'.$absorbed->slug.'?lang=en')->assertStatus(301)
            ->assertRedirect(route('api.stations.show', $kept->slug).'?lang=en');
        $this->get('/api/v1/stations/'.$absorbed->slug.'/media')->assertStatus(301)->assertRedirect(route('api.stations.media', $kept->slug));
        $this->post('/api/v1/stations/'.$absorbed->slug)->assertStatus(405);
        $this->get('/api/v1/stations/inconnue')->assertNotFound();
        $this->graphQL('query ($slug: String!) { station(slug: $slug) { slug } }', ['slug' => $absorbed->slug])
            ->assertJsonPath('data.station.slug', $kept->slug);
        $this->graphQL('query { station(slug: "inconnue") { slug } }')->assertJsonPath('data.station', null);
        $this->assertSame($kept->id, Lookup::station($absorbed->slug)?->id);
        $this->assertNull(Lookup::station('inconnue'));
        $this->assertNull(Station::redirectSlugFor($kept->slug), 'une adresse vivante ne redirige pas');
    }

    /** Réponse de RedirectMergedStationSlug pour une requête sur une route `{station}/{part}` (nommée ou non). */
    private function missing(string $method, string $slug, ?string $name, string $model = Station::class): ?string
    {
        $route = new Route(['GET', 'HEAD', 'POST'], 'essai/{station}/{part}', fn () => null);
        if ($name !== null) {
            app('router')->getRoutes()->add($route->name($name));
            app('router')->getRoutes()->refreshNameLookups();
        }
        $request = Request::create("/essai/{$slug}/plan", $method, ['z' => '1']);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $e = new NotFoundHttpException('', (new ModelNotFoundException)->setModel($model));

        return RedirectMergedStationSlug::fromMissingStation($e, $request)?->getTargetUrl();
    }

    public function test_redirect_keeps_the_other_parameters_and_only_answers_safe_named_station_routes(): void
    {
        [$kept, $absorbed] = $this->twoStations();
        $this->consolidate([$this->merge()]);

        $this->assertSame(url("/essai/{$kept->slug}/plan").'?z=1', $this->missing('GET', $absorbed->slug, 'essai.fusion'));
        $this->assertNull($this->missing('POST', $absorbed->slug, 'essai.fusion'), 'un envoi n\'est jamais rejoué ailleurs');
        $this->assertNull($this->missing('GET', $absorbed->slug, null), 'route sans nom');
        $this->assertNull($this->missing('GET', $absorbed->slug, 'essai.fusion', Line::class), 'autre modèle introuvable');
        $this->assertNull($this->missing('GET', 'inconnue', 'essai.fusion'));
    }

    public function test_merge_of_unknown_or_already_joined_stations_changes_nothing(): void
    {
        [$kept, $absorbed] = $this->twoStations();

        $this->app->instance(StationCorrections::class, new StationCorrections([$this->merge('absorbed', ['absorbed' => ['name' => 'Pleine-Fougères', 'osm' => 'n1']])]));
        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('fiche absorbée introuvable')->assertSuccessful();
        $this->assertSame(2, Station::count());

        // Nom de contrôle faux : l'objet désigné ne porte pas ce nom.
        $this->app->instance(StationCorrections::class, new StationCorrections([$this->merge('absorbed', ['station' => ['name' => 'Dol', 'uic' => '87478255']])]));
        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('fiche gardée introuvable')->assertSuccessful();
        $this->assertEqualsCanonicalizing([$kept->id, $absorbed->id], Station::pluck('id')->all());

        $this->consolidate([$this->merge()]);
        $this->app->instance(StationCorrections::class, new StationCorrections([$this->merge(), $this->merge()]));
        $this->artisan('vff:consolidate-stations')->expectsOutputToContain('déjà une seule gare')->assertSuccessful();
    }

    public function test_dry_run_merges_nothing(): void
    {
        $this->twoStations();
        $this->app->instance(StationCorrections::class, new StationCorrections([$this->merge()]));
        $this->artisan('vff:consolidate-stations', ['--dry-run' => true])->expectsOutputToContain('réunies (position de la fiche absorbée)')->assertSuccessful();
        $this->assertSame(2, Station::count());
        $this->assertSame(0, DB::table('station_redirects')->count());
    }

    public function test_merge_corrections_are_validated(): void
    {
        $this->assertSame([], StationCorrections::validate($this->merge()));
        $this->assertSame(['merge : position kept ou absorbed'], StationCorrections::validate($this->merge('best')));
        $this->assertSame(['merge : station et absorbed visées par name + uic, wikidata ou osm'],
            StationCorrections::validate($this->merge('kept', ['absorbed' => ['name' => 'Pleine-Fougères', 'near' => [-1.56, 48.53, 1]]])));
        $this->assertSame(['merge : station et absorbed visées par name + uic, wikidata ou osm'],
            StationCorrections::validate($this->merge('kept', ['absorbed' => ['osm' => 'n2514130142']])));
        $this->assertSame(['merge : station et absorbed désignent la même fiche'],
            StationCorrections::validate($this->merge('kept', ['absorbed' => ['name' => 'Pleine-Fougères', 'uic' => '87478255']])));
        $this->assertSame(['source_url manquante', 'merge : station et absorbed visées par name + uic, wikidata ou osm', 'merge : position kept ou absorbed'],
            StationCorrections::validate($this->merge('best', ['absorbed' => ['name' => 'Pleine-Fougères'], 'source_url' => 'ftp://x'])));
        $this->assertSame([], StationCorrections::validate($this->merge('kept', ['absorbed' => ['name' => 'Pleine-Fougères', 'osm' => '87478255']])),
            'même valeur sous deux clés différentes : deux fiches');
        $this->assertSame([[
            'kept' => ['name' => 'Pleine-Fougères', 'uic' => '87478255'],
            'absorbed' => ['name' => 'Pleine-Fougères', 'osm' => 'n2514130142'],
            'position' => 'kept',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Rennes&oldid=237081548',
        ]], (new StationCorrections([$this->merge('kept', ['station' => ['name' => 'Pleine-Fougères', 'uic' => 87478255, 'note' => 'SNCF']])]))->merges(),
            'identifiants en chaînes, clés inconnues écartées');
        $merges = (new StationCorrections([$this->merge(), $this->merge('best')]))->merges();
        $this->assertSame([[
            'kept' => ['name' => 'Pleine-Fougères', 'uic' => '87478255'],
            'absorbed' => ['name' => 'Pleine-Fougères', 'osm' => 'n2514130142'],
            'position' => 'absorbed',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Caen_%C3%A0_Rennes&oldid=237081548',
        ]], $merges, 'seules les fusions valides');
    }

    public function test_project_merges_name_two_distinct_stations_with_a_cited_source(): void
    {
        $merges = (new StationCorrections)->merges();
        $this->assertNotEmpty($merges);
        foreach ($merges as $m) {
            $this->assertNotSame('', trim($m['kept']['name']));
            $this->assertNotSame('', trim($m['absorbed']['name']));
            $this->assertMatchesRegularExpression('#oldid=\d+|wikidata\.org/wiki/Q\d+|openstreetmap\.org/(node|way)/\d+#', $m['source_url']);
        }
    }
}
