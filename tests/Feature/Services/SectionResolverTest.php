<?php

namespace Tests\Feature\Services;

use App\Models\Line;
use App\Services\SectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Résolution de la PORTION de ligne de chaque événement daté (SectionResolver),
 * sur un tracé de référence déterministe : ligne est-ouest à 47° N, de
 * Alphaville (2,0° E, PK 0) à Gammaville (2,4° E, PK 30,395) par Betaville
 * (2,2° E, PK 15,197). Longueurs mesurées en Lambert-93 : 30 395 m au total,
 * 15 197 m d'Alphaville à Betaville, 7 599 m par 0,1° de longitude.
 *
 * Méthodes vérifiées : pk (calage sur les gares SNCF), places (gares, communes,
 * lieux-dits IGN simulés, variantes de nom), trace, whole_line, « reste de la
 * ligne » après des fermetures partielles, et les refus motivés (unresolved).
 */
class SectionResolverTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = '901000';

    private const TOTAL_M = 30395;

    private const HALF_M = 15197;

    private Line $line;

    /** @var list<array<string, mixed>> réponse simulée du géocodage IGN (index POI) */
    private array $poi = [];

    /** @var list<array<string, mixed>> réponse simulée de geo.api.gouv.fr/communes */
    private array $communesApi = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Mémos de processus (lieux-dits, communes par API) : un test n'hérite pas du précédent.
        foreach (['poiMemo', 'communeMemo'] as $memo) {
            (new \ReflectionProperty(SectionResolver::class, $memo))->setValue(null, []);
        }
        // Lieux-dits (IGN) et communes (geo.api.gouv.fr) : réponses fournies par chaque test
        // (rien par défaut). Un seul faux : les suivants ne remplaceraient pas le premier.
        Http::fake([
            'data.geopf.fr/*' => fn () => Http::response(['type' => 'FeatureCollection', 'features' => $this->poi]),
            'geo.api.gouv.fr/*' => fn () => Http::response($this->communesApi),
        ]);

        $this->line = Line::factory()->create(['name' => 'Ligne de Alphaville à Gammaville', 'code' => self::CODE]);
        DB::statement(
            "INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
             VALUES (?, ?, '[\"test\"]', ST_GeomFromText('MULTILINESTRING((2.0 47.0, 2.1 47.0, 2.2 47.0, 2.3 47.0, 2.4 47.0))', 4326), now(), now())",
            [$this->line->id, self::TOTAL_M],
        );
        $this->gare('Alphaville', 2.0, 0);
        $this->gare('Betaville', 2.2, self::HALF_M);
        $this->gare('Gammaville', 2.4, self::TOTAL_M);
    }

    private function gare(string $name, float $lon, ?int $pk, string $code = self::CODE, float $lat = 47.0): void
    {
        DB::table('sncf_gares')->insert([
            'code_ligne' => $code, 'libelle' => $name, 'commune' => $name, 'pk_m' => $pk,
            'lon' => $lon, 'lat' => $lat, 'voyageurs' => true, 'fret' => true,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function event(array $attributes): int
    {
        return DB::table('line_events')->insertGetId($attributes + [
            'line_id' => $this->line->id,
            'type' => 'closure_passenger',
            'event_date' => '1950-01-01',
            'description' => 'Événement de test',
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, int> */
    private function resolve(string $scope = 'pending', ?Line $line = null): array
    {
        return app(SectionResolver::class)->resolveLine(($line ?? $this->line)->id, null, $scope);
    }

    private function row(int $id): object
    {
        return DB::table('line_events')->where('id', $id)
            ->selectRaw('section_resolution AS res, section_resolution_note AS note, section_length_m AS len, ST_GeometryType(section_geom) AS gtype,
                         ST_XMin(section_geom) AS xmin, ST_XMax(section_geom) AS xmax')
            ->first();
    }

    private function assertLength(int $expected, ?int $actual, float $tolerance = 0.01): void
    {
        $this->assertNotNull($actual, 'section_length_m renseignée');
        $this->assertEqualsWithDelta($expected, $actual, max(30, $expected * $tolerance));
    }

    // ─── PK ──────────────────────────────────────────────────────────────

    public function test_pk_section_is_calibrated_on_sncf_stations(): void
    {
        $id = $this->event(['section_pk_start_m' => 7599, 'section_pk_end_m' => 22796]);

        $this->assertSame(['pk' => 1], $this->resolve());

        $r = $this->row($id);
        $this->assertSame('pk', $r->res);
        $this->assertSame('ST_MultiLineString', $r->gtype);
        $this->assertLength(15197, $r->len);
        $this->assertEqualsWithDelta(2.1, $r->xmin, 0.002);
        $this->assertEqualsWithDelta(2.3, $r->xmax, 0.002);
        $this->assertStringContainsString('PK 7,599 à 22,796 calés sur 3 points de contrôle (3 gare)', $r->note);
    }

    public function test_pk_bounds_are_ordered_and_truncated_to_the_known_trace(): void
    {
        // Bornes inversées, la seconde au-delà du tracé (PK 40) : tronquée à Gammaville.
        $id = $this->event(['section_pk_start_m' => 40000, 'section_pk_end_m' => 22796]);

        $this->resolve();

        $r = $this->row($id);
        $this->assertSame('pk', $r->res);
        $this->assertLength(7599, $r->len);
        $this->assertStringContainsString('m hors du tracé connu, tronqués', $r->note);
        $this->assertStringContainsString('borne à', $r->note, 'PK 40 à plus de 3 km du dernier point de contrôle');
    }

    public function test_pk_section_entirely_outside_the_trace_is_unresolved(): void
    {
        $id = $this->event(['section_pk_start_m' => 50000, 'section_pk_end_m' => 60000]);

        $this->assertSame(['unresolved' => 1], $this->resolve());

        $r = $this->row($id);
        $this->assertNull($r->gtype);
        $this->assertNull($r->len);
        $this->assertSame('PK 50,000 à 60,000 hors du tracé connu (tracé calé de PK 0,000 à PK 30,395)', $r->note);
    }

    public function test_pk_without_two_control_points_is_unresolved(): void
    {
        DB::table('sncf_gares')->where('libelle', '<>', 'Alphaville')->delete();
        $id = $this->event(['section_pk_start_m' => 1000, 'section_pk_end_m' => 5000]);

        $this->resolve();

        $this->assertSame(
            'PK non calables : moins de deux points de contrôle (gares / PN / tronçons SNCF / lieux à PK connu)',
            $this->row($id)->note,
        );
    }

    public function test_incoherent_control_point_is_dropped_by_the_monotonic_filter(): void
    {
        // Gare « Faussville » au PK 2 000 mais placée à 2,3° E (devrait être au PK ~22 800).
        $this->gare('Faussville', 2.3, 2000);
        $id = $this->event(['section_pk_start_m' => 0, 'section_pk_end_m' => self::HALF_M]);

        $this->resolve();

        $r = $this->row($id);
        $this->assertSame('pk', $r->res);
        $this->assertLength(self::HALF_M, $r->len);
        $this->assertStringContainsString('calés sur 3 points de contrôle', $r->note);
    }

    // ─── Lieux ───────────────────────────────────────────────────────────

    public function test_places_section_between_two_sncf_stations(): void
    {
        $id = $this->event(['section_from' => 'Alphaville', 'section_to' => 'Betaville']);

        $this->assertSame(['places' => 1], $this->resolve());

        $r = $this->row($id);
        $this->assertSame('places', $r->res);
        $this->assertLength(self::HALF_M, $r->len);
        $this->assertEqualsWithDelta(2.0, $r->xmin, 0.002);
        $this->assertEqualsWithDelta(2.2, $r->xmax, 0.002);
        $this->assertStringStartsWith('« Alphaville » → gare SNCF « Alphaville » de la ligne (0 m du tracé)', $r->note);
    }

    public function test_place_name_variants_and_typographic_apostrophes(): void
    {
        // « Gare de … », suffixe « -Ville », département entre parenthèses.
        $id = $this->event(['section_from' => 'Gare de Betaville (Cher)', 'section_to' => 'Gammaville-Ville']);
        // Nom ancien inconnu mais casse, accents et tirets différents : même gare.
        $id2 = $this->event(['section_from' => 'ALPHAVILLE', 'section_to' => 'bétaville', 'type' => 'closure_freight']);

        $this->resolve();

        $this->assertSame('places', $this->row($id)->res);
        $this->assertLength(self::HALF_M, $this->row($id)->len);
        $this->assertSame('places', $this->row($id2)->res);
        $this->assertLength(self::HALF_M, $this->row($id2)->len);
    }

    public function test_consolidated_station_and_commune_are_geocoded_near_the_trace(): void
    {
        // Gare consolidée (stations) sans gare SNCF homonyme, à 2,1° E.
        DB::statement("INSERT INTO stations (slug, name, type, status, geom, created_at, updated_at)
            VALUES ('deltaville', 'Deltaville', 'gare', 'closed', ST_SetSRID(ST_MakePoint(2.1, 47.0005), 4326), now(), now())");
        // Commune à 2,3° E, centre à ~2,2 km au nord du tracé (< 6 km).
        DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, geom, created_at, updated_at)
            VALUES ('99999', 'commune-actuelle', 'Epsilonbourg', 'epsilonbourg', ST_SetSRID(ST_MakePoint(2.3, 47.02), 4326), now(), now())");
        $id = $this->event(['section_from' => 'Deltaville', 'section_to' => 'Epsilonbourg']);

        $this->resolve();

        $r = $this->row($id);
        $this->assertSame('places', $r->res);
        $this->assertLength(15197, $r->len, 0.02);
        $this->assertStringContainsString('gare « Deltaville »', $r->note);
        $this->assertStringContainsString('commune « Epsilonbourg » (centre à', $r->note);
    }

    /**
     * Tonnerre (830000) : le point de la liste des gares SNCF est à 136 m de la gare consolidée ; la portion
     * Paris - Tonnerre s'arrêtait avant la gare montrée sur la carte. La gare consolidée de la ligne, au même
     * nom exact, l'emporte sur le point SNCF ; sans rattachement à la ligne, le point SNCF reste choisi.
     */
    public function test_consolidated_station_of_the_line_wins_over_the_sncf_point_of_the_same_name(): void
    {
        $id = DB::table('stations')->insertGetId(['slug' => 'betaville', 'name' => 'Betaville', 'type' => 'gare', 'status' => 'open',
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.2015, 47.0), 4326)'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_lines')->insert(['station_id' => $id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => self::HALF_M,
            'created_at' => now(), 'updated_at' => now()]);
        $event = $this->event(['section_from' => 'Alphaville', 'section_to' => 'Betaville']);

        $this->resolve();

        $r = $this->row($event);
        $this->assertSame('places', $r->res);
        $this->assertEqualsWithDelta(2.2015, $r->xmax, 0.0003);
        $this->assertStringContainsString('« Betaville » → gare « Betaville » de la ligne', $r->note);
    }

    public function test_sncf_point_is_kept_when_the_consolidated_station_is_not_linked_to_the_line(): void
    {
        DB::table('stations')->insert(['slug' => 'betaville', 'name' => 'Betaville', 'type' => 'gare', 'status' => 'open',
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.2015, 47.0), 4326)'), 'created_at' => now(), 'updated_at' => now()]);
        $event = $this->event(['section_from' => 'Alphaville', 'section_to' => 'Betaville']);

        $this->resolve();

        $r = $this->row($event);
        $this->assertEqualsWithDelta(2.2, $r->xmax, 0.0003);
        $this->assertStringContainsString('« Betaville » → gare SNCF « Betaville » de la ligne', $r->note);
    }

    public function test_hamlet_from_the_ign_geocoder_is_used_when_no_station_or_commune_matches(): void
    {
        $this->poi = [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [2.15, 47.002]],
            'properties' => ['toponym' => 'Moulin des Prés', 'category' => ['lieu-dit habité'], 'city' => ['Betaville']],
        ], [
            // Même nom mais « non habité » : ignoré.
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [2.35, 47.002]],
            'properties' => ['toponym' => 'Moulin des Prés', 'category' => ['lieu-dit non habité']],
        ]];
        $id = $this->event(['section_from' => 'Moulin des Prés', 'section_to' => 'Gammaville']);

        $this->resolve();

        $r = $this->row($id);
        $this->assertSame('places', $r->res, (string) $r->note);
        $this->assertLength(18998, $r->len, 0.02);
        $this->assertStringContainsString('lieu-dit « Moulin des Prés » à Betaville (BD TOPO', $r->note);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://data.geopf.fr/geocodage/search')
            && $request['q'] === 'Moulin des Prés' && $request['index'] === 'poi');
    }

    public function test_commune_api_fallback_when_the_communes_table_is_empty(): void
    {
        $this->communesApi = [
            ['nom' => 'Quincampoix-sur-Loire', 'centre' => ['type' => 'Point', 'coordinates' => [2.3, 47.01]]],
            ['nom' => 'Quincampoix-Lointain', 'centre' => ['type' => 'Point', 'coordinates' => [5.0, 45.0]]],
        ];
        $id = $this->event(['section_from' => 'Quincampoix', 'section_to' => 'Alphaville']);

        $this->resolve();

        $r = $this->row($id);
        $this->assertSame('places', $r->res);
        $this->assertLength(22796, $r->len, 0.02);
        $this->assertStringContainsString('commune « Quincampoix-sur-Loire »', $r->note);
    }

    public function test_unknown_place_is_unresolved_with_the_reason(): void
    {
        $id = $this->event(['section_from' => 'Zorglubville', 'section_to' => 'Betaville']);

        $this->assertSame(['unresolved' => 1], $this->resolve());

        $this->assertSame('lieu introuvable près du tracé : « Zorglubville » (inconnu)', $this->row($id)->note);
    }

    public function test_place_far_from_the_trace_is_reported_with_its_distance(): void
    {
        // Gare homonyme d'une autre ligne, à ~111 km au sud : pas sur ce tracé.
        $this->gare('Lointainville', 2.2, 5000, '999000', 46.0);
        $id = $this->event(['section_from' => 'Lointainville', 'section_to' => 'Betaville']);

        $this->resolve();

        $this->assertMatchesRegularExpression(
            '/^lieu introuvable près du tracé : « Lointainville » \(gare à 11\d\.\d km du tracé\)$/u',
            $this->row($id)->note,
        );
    }

    public function test_place_beyond_a_free_end_of_the_trace_stops_the_section_at_that_end(): void
    {
        // Omegaville, 15 km à l'est de Gammaville (au-delà de l'extrémité libre).
        $this->gare('Omegaville', 2.6, null, '999001');
        $id = $this->event(['section_from' => 'Betaville', 'section_to' => 'Omegaville']);

        $this->resolve();

        $r = $this->row($id);
        $this->assertSame('places', $r->res);
        $this->assertLength(self::HALF_M, $r->len);
        $this->assertStringContainsString('au-delà de l\'extrémité du tracé', $r->note);
        $this->assertStringContainsString('portion arrêtée au tracé connu', $r->note);
    }

    public function test_foreign_place_next_to_the_trace_ends_the_section_at_the_border(): void
    {
        // Ligne frontalière qui longe Irun (lieu étranger, cf. FOREIGN_PLACES) à ~0,5 km :
        // « Hendaye-Test - Irun » s'arrête au point du tracé le plus proche d'Irun ;
        // Genève, à des centaines de km, reste introuvable.
        $border = Line::factory()->create(['name' => 'Ligne de Hendaye-Test à la frontière', 'code' => '902000']);
        DB::statement(
            "INSERT INTO line_reference_geometries (line_id, length_m, sources, geom, created_at, updated_at)
             VALUES (?, 8000, '[\"test\"]', ST_GeomFromText('MULTILINESTRING((-1.70 43.36, -1.79 43.344, -1.80 43.345))', 4326), now(), now())",
            [$border->id],
        );
        $this->gare('Hendaye-Test', -1.70, 0, '902000', 43.36);
        $near = $this->event(['line_id' => $border->id, 'type' => 'opening', 'section_from' => 'Hendaye-Test', 'section_to' => 'Irun']);
        $far = $this->event(['line_id' => $border->id, 'type' => 'opening', 'section_from' => 'Hendaye-Test', 'section_to' => 'Genève']);

        $this->resolve(line: $border);

        $r = $this->row($near);
        $this->assertSame('places', $r->res);
        $this->assertStringContainsString('lieu étranger « Irun » à 0.', $r->note);
        $this->assertStringContainsString('(frontière)', $r->note);
        $this->assertEqualsWithDelta(-1.792, $r->xmin, 0.003); // pas jusqu'au bout du tracé (-1.80)
        $this->assertEqualsWithDelta(-1.70, $r->xmax, 0.001);
        $this->assertSame('unresolved', $this->row($far)->res);
    }

    public function test_end_points_too_close_are_refused(): void
    {
        // Gare de la même ligne à ~150 m : le nom exact l'emporte sur « Alphaville ».
        $this->gare('Alphaville-Est', 2.002, null);
        $id = $this->event(['section_from' => 'Alphaville', 'section_to' => 'Alphaville-Est']);

        $this->resolve();

        $this->assertMatchesRegularExpression('/^extrémités trop proches sur le tracé \(15\d m entre « Alphaville » et « Alphaville-Est »\) : portion douteuse$/u', $this->row($id)->note);
    }

    public function test_a_single_end_point_is_not_enough(): void
    {
        $id = $this->event(['section_from' => 'Alphaville', 'section_to' => null]);

        $this->resolve();

        $this->assertSame('unresolved', $this->row($id)->res);
        $this->assertSame('une seule extrémité citée (« Alphaville »)', $this->row($id)->note);
    }

    // ─── Tracé source, toute la ligne, reste de la ligne ─────────────────

    public function test_event_attached_to_a_source_trace_uses_that_trace(): void
    {
        $traceId = DB::table('source_traces')->insertGetId([
            'source' => 'osm', 'source_ref' => 'way/1', 'seen_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('MULTILINESTRING((2.0 47.0, 2.1 47.0))', 4326)"),
        ]);
        $id = $this->event(['source_trace_id' => $traceId]);

        $this->assertSame(['trace' => 1], $this->resolve());

        $r = $this->row($id);
        $this->assertSame('trace', $r->res);
        $this->assertSame("Tracé source n° {$traceId} (osm)", $r->note);
        $this->assertEqualsWithDelta(7592, $r->len, 40, 'longueur géodésique du tracé source');
    }

    public function test_event_without_portion_applies_to_the_whole_line(): void
    {
        $id = $this->event(['type' => 'opening', 'event_date' => '1880-06-01']);

        $this->assertSame(['whole_line' => 1], $this->resolve());

        $r = $this->row($id);
        $this->assertSame('whole_line', $r->res);
        $this->assertLength(self::TOTAL_M, $r->len);
        $this->assertSame('Aucune portion décrite : toute la ligne (tracé référence)', $r->note);
    }

    public function test_reopening_without_portion_is_never_applied_to_the_whole_line(): void
    {
        $id = $this->event(['type' => 'reopening', 'event_date' => '2005-12-01']);
        $explicit = $this->event(['type' => 'reopening', 'event_date' => '2006-01-01', 'description' => 'Réouverture de toute la ligne']);

        $this->resolve();

        $this->assertSame('Réouverture sans portion explicite : non appliquée à toute la ligne (à préciser)', $this->row($id)->note);
        $this->assertSame('whole_line', $this->row($explicit)->res, '« toute la ligne » lève l\'objection');
    }

    public function test_heritage_operation_without_observed_or_named_portion_is_never_the_whole_line(): void
    {
        // 174000 : « Chemin de fer touristique de la vallée de la Canner » sans portion → 53 km « patrimoine ».
        $heritage = $this->event(['type' => 'heritage_start', 'event_date' => '1966-01-01', 'source' => 'heritage', 'heritage_kind' => 'tourist_train',
            'description' => 'Exploitation associative : Chemin de fer touristique de la vallée de la Canner, train touristique, depuis 1966']);
        $manual = $this->event(['type' => 'heritage_start', 'event_date' => '1971-07-04', 'source' => 'manual', 'heritage_kind' => 'tourist_train']);
        $closure = $this->event(['type' => 'closure', 'event_date' => '1960-01-01', 'source' => 'heritage']);

        $this->resolve();

        $this->assertSame('unresolved', $this->row($heritage)->res);
        $this->assertSame('Exploitation associative sans portion observée ni nommée : jamais appliquée à toute la ligne', $this->row($heritage)->note);
        $this->assertSame('whole_line', $this->row($manual)->res, 'une ligne hors RFN déclarée à la main reste entière');
        $this->assertSame('whole_line', $this->row($closure)->res, 'seuls les événements d\'exploitation associative');
    }

    public function test_quote_naming_a_section_is_never_applied_to_the_whole_line(): void
    {
        $id = $this->event(['type' => 'closure', 'source_quote' => 'La section est fermée au trafic.']);
        $between = $this->event(['type' => 'closure', 'source_quote' => 'Fermeture entre Betaville et le port.']);
        $infobox = $this->event(['type' => 'closure', 'event_date' => '1951-01-01', 'source_quote' => 'Infobox : fermeture = 1951 (section)']);

        $this->resolve();

        $this->assertSame('Portion citée par la source mais non identifiée : jamais appliqué à toute la ligne', $this->row($id)->note);
        $this->assertSame('Portion citée par la source mais non identifiée : jamais appliqué à toute la ligne', $this->row($between)->note);
        $this->assertSame('whole_line', $this->row($infobox)->res, 'les citations d\'infobox ne sont pas des phrases');
    }

    public function test_line_closure_after_partial_closures_takes_the_remainder(): void
    {
        $partial = $this->event(['type' => 'closure', 'event_date' => '1950-01-01', 'section_from' => 'Alphaville', 'section_to' => 'Betaville']);
        $rest = $this->event(['type' => 'closure', 'event_date' => '1970-06-01', 'description' => 'La ligne est fermée.']);

        $this->assertSame(['places' => 2], $this->resolve());

        $this->assertLength(self::HALF_M, $this->row($partial)->len);
        $r = $this->row($rest);
        $this->assertSame('places', $r->res);
        $this->assertLength(self::HALF_M, $r->len, 0.02);
        $this->assertEqualsWithDelta(2.2, $r->xmin, 0.003, 'le reste commence à Betaville');
        $this->assertEqualsWithDelta(2.4, $r->xmax, 0.002);
        $this->assertStringStartsWith('Reste de la ligne : 15.', $r->note);
        $this->assertStringContainsString("non couverts par les fermetures antérieures ({$partial})", $r->note);
    }

    public function test_remainder_is_refused_when_a_later_reopening_exists(): void
    {
        $this->event(['type' => 'closure', 'event_date' => '1950-01-01', 'section_from' => 'Alphaville', 'section_to' => 'Betaville']);
        $rest = $this->event(['type' => 'closure', 'event_date' => '1970-06-01', 'description' => 'La ligne est fermée.']);
        $this->event(['type' => 'reopening', 'event_date' => '1990-01-01', 'section_from' => 'Betaville', 'section_to' => 'Gammaville']);

        $this->resolve();

        $this->assertSame('unresolved', $this->row($rest)->res);
        $this->assertSame(
            'Fermeture sans portion postérieure à une fermeture partielle (1950-01-01) : portion à préciser ; reste de la ligne non appliqué : ouverture ou réouverture postérieure',
            $this->row($rest)->note,
        );
    }

    public function test_remainder_is_refused_in_a_works_context_or_without_explicit_text(): void
    {
        $this->event(['type' => 'closure', 'event_date' => '1950-01-01', 'section_from' => 'Alphaville', 'section_to' => 'Betaville']);
        $works = $this->event(['type' => 'closure', 'event_date' => '1970-06-01', 'description' => 'La ligne est fermée pour travaux.']);
        $vague = $this->event(['type' => 'closure', 'event_date' => '1971-06-01', 'description' => 'Fin du service.']);

        $this->resolve();

        $this->assertStringEndsWith('reste de la ligne non appliqué (contexte de travaux, de réouverture ou fermeture partielle)', $this->row($works)->note);
        $this->assertSame('Fermeture sans portion postérieure à une fermeture partielle (1950-01-01) : portion à préciser', $this->row($vague)->note);
    }

    public function test_remainder_already_covered_is_reported_empty(): void
    {
        $partial = $this->event(['type' => 'closure', 'event_date' => '1950-01-01', 'section_from' => 'Alphaville', 'section_to' => 'Gammaville']);
        $rest = $this->event(['type' => 'closure', 'event_date' => '1970-06-01', 'description' => 'La ligne est fermée.']);

        $this->resolve();

        $this->assertSame("Fermeture sans portion postérieure à une fermeture partielle (1950-01-01) : portion à préciser ; reste de la ligne vide : déjà couverte par les fermetures antérieures ({$partial})", $this->row($rest)->note);
    }

    // ─── Portées, absence de tracé ───────────────────────────────────────

    public function test_line_without_any_trace_leaves_events_unresolved(): void
    {
        $bare = Line::factory()->create(['name' => 'Ligne sans tracé', 'code' => null]);
        $id = DB::table('line_events')->insertGetId(['line_id' => $bare->id, 'type' => 'opening', 'event_date' => '1900-01-01',
            'description' => 'Ouverture', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(['unresolved' => 1], $this->resolve(line: $bare));
        $this->assertSame('Aucun tracé pour cette ligne (ni tracé de référence, ni tracé source rattaché, ni géométrie SNCF)', $this->row($id)->note);
    }

    public function test_sncf_geometry_is_the_carrier_without_reference_trace(): void
    {
        DB::table('line_reference_geometries')->delete();
        DB::statement("INSERT INTO sncf_line_geometries (code_ligne, libelle, pk_debut_m, pk_fin_m, geom, created_at, updated_at)
            VALUES (?, 'Exploitée', 0, ?, ST_GeomFromText('LINESTRING(2.0 47.0, 2.4 47.0)', 4326), now(), now())", [self::CODE, self::TOTAL_M]);
        $id = $this->event(['type' => 'opening']);

        $this->resolve();

        $this->assertSame('Aucune portion décrite : toute la ligne (tracé SNCF)', $this->row($id)->note);
        $this->assertLength(self::TOTAL_M, $this->row($id)->len);
    }

    public function test_scopes_select_which_events_are_recomputed(): void
    {
        $resolved = $this->event(['section_from' => 'Alphaville', 'section_to' => 'Betaville']);
        $failed = $this->event(['section_from' => 'Zorglubville', 'section_to' => 'Betaville']);
        $unknown = $this->event(['type' => 'closure_freight', 'section_resolution' => 'unresolved',
            'section_resolution_note' => 'Portion non précisée par la source']);
        $this->resolve();

        // pending : plus rien à faire ; retry : seulement l'échec (pas la portion inconnue par la source).
        $this->assertSame([], $this->resolve('pending'));
        $this->assertSame(['unresolved' => 1], $this->resolve('retry'));
        $this->assertSame('Portion non précisée par la source', $this->row($unknown)->note);

        DB::table('sncf_gares')->insert(['code_ligne' => self::CODE, 'libelle' => 'Zorglubville', 'pk_m' => 22796,
            'lon' => 2.3, 'lat' => 47.0, 'voyageurs' => true, 'fret' => false]);
        $this->assertSame(['places' => 2], $this->resolve('all'));
        $this->assertSame('places', $this->row($failed)->res);
        $this->assertSame('places', $this->row($resolved)->res);
        $this->assertSame('unresolved', $this->row($unknown)->res, 'portion inconnue par la source : jamais recalculée');
    }

    public function test_geocode_is_public_and_cached_per_name(): void
    {
        $resolver = app(SectionResolver::class);
        $this->event(['type' => 'opening']);
        $resolver->resolveLine($this->line->id);

        $g = $resolver->geocode('Betaville');
        $this->assertNotNull($g);
        $this->assertEqualsWithDelta(0.5, $g['loc']['f'] ?? null, 0.5, 'position relative sur une pièce du tracé');
        $this->assertLessThan(1, $g['loc']['d']);
        $this->assertSame('gare SNCF « Betaville » de la ligne (0 m du tracé)', $g['label']);
        $this->assertNull($resolver->geocode('Zorglubville'));
    }
}
