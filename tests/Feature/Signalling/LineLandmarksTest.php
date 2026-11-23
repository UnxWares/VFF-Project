<?php

namespace Tests\Feature\Signalling;

use App\Models\Line;
use App\Models\Station;
use App\Services\Signalling\SignallingSectionLocator;
use App\Services\Signalling\SignallingSourcesFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Portions nommées par des points remarquables (bifurcations, raccordements, origines, fins, frontières :
 * `database/data/line_landmarks.php`) : les faits réels des LGV du fichier des documents, la priorité des gares,
 * le calage du tracé sur les points.
 */
class LineLandmarksTest extends TestCase
{
    use RefreshDatabase;

    private ?string $file = null;

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->file !== null && is_file($this->file)) {
                unlink($this->file);
            }
        }
    }

    public function test_the_lgv_facts_named_by_junctions_are_placed_on_their_landmarks(): void
    {
        $lines = [];
        foreach (['005000', '014000', '226000', '431000', '752000'] as $code) {
            $lines[$code] = Line::factory()->create(['slug' => "rfn-{$code}", 'code' => $code, 'is_lgv' => true]);
        }
        // Seule gare de ces lieux sur les lignes : Vendenheim, PK interpolé entre deux gares de PK connu.
        $vendenheim = $this->station('Vendenheim', $lines['005000'], 406_015, 'projected');
        ['facts' => $facts, 'errors' => $errors] = SignallingSourcesFile::parse(require database_path('data/signalling_history_sources.php'));
        $this->assertSame([], $errors);

        $locator = app(SignallingSectionLocator::class);
        $placed = [];
        foreach ($facts as $f) {
            if (! isset($lines[$f['line']]) || $f['scope'] !== 'section') {
                continue;
            }
            $place = $locator->locate($lines[$f['line']]->id, 'section', $f['section_from'], $f['section_to'], $f['pk_start_m'], $f['pk_end_m']);
            $placed["{$f['line']} {$f['date']} {$f['system']}"] = [$place['location'], $place['pk_start_m'], $place['pk_end_m']];
        }

        $this->assertSame([
            '005000 2007-06-10 TVM-430' => ['landmarks', 0, 299_784],
            '005000 2013-12-17 ETCS_L2' => ['landmarks', 0, 299_784],
            '005000 2016-07-03 TVM-430' => ['landmarks', 299_784, 406_015],
            '005000 2016-07-03 ETCS_L2' => ['landmarks', 299_784, 406_015],
            '014000 2011-12-11 TVM-430' => ['landmarks', 423, 137_899],
            '752000 1981-09-27 TVM' => ['landmarks', 117_186, 380_500],
            '752000 1983-09-25 TVM' => ['landmarks', 168, 117_186],
            '752000 1992-12-13 TVM-300' => ['landmarks', 380_500, 416_648],
            '752000 1994-07-03 TVM-430' => ['landmarks', 416_648, 493_427],
            '752000 2001-06-10 TVM-430' => ['landmarks', 493_427, 711_163],
            '431000 1989-09-24 TVM-300' => ['unlocated', null, null],
            '431000 1990-09-30 TVM-300' => ['landmarks', 130_190, 214_699],
            '226000 1993-05-23 TVM-430' => ['landmarks', 0, 144_077],
            '226000 1993-09-26 TVM-430' => ['landmarks', 144_077, 194_643],
            '226000 1996-06-02 TVM-430' => ['landmarks', 194_643, 210_580],
        ], $placed, 'Montrouge, début de la LGV Atlantique, n’a pas de PK sûr : la portion n’est pas placée');

        $phase2 = $locator->locate($lines['005000']->id, 'section', 'Baudrecourt', 'Vendenheim', null, null);
        $this->assertSame([null, $vendenheim->id], [$phase2['from_station_id'], $phase2['to_station_id']], 'la gare de l’autre extrémité est gardée');
    }

    public function test_a_station_comes_first_and_a_landmark_needs_its_exact_name(): void
    {
        $line = Line::factory()->create(['slug' => 'rfn-974000', 'code' => '974000']);
        $this->station('Alphaville', $line, 12_000, 'sncf');
        $this->landmarks([
            ['name' => 'Bifurcation d\'Alphaville', 'aliases' => ['Alphaville'], 'pk_m' => 11_500],
            ['name' => 'Bifurcation de Gammaville', 'aliases' => ['Gammaville'], 'pk_m' => 19_000],
        ]);
        $locator = new SignallingSectionLocator($this->file);

        $this->assertSame(['stations', 12_000, 18_000], $this->place($locator, $line, 'Alphaville', 'Betaville', 18_000));
        $this->assertSame(['landmarks', 12_000, 19_000], $this->place($locator, $line, 'Alphaville', 'Gammaville'));
        $this->assertSame(['landmarks', 11_500, 19_000], $this->place($locator, $line, 'Bifurcation d’Alphaville', 'Bifurcation de Gammaville'));
        $this->assertSame(['unlocated', null, null], $this->place($locator, $line, 'Alphaville', 'Gamma'), 'jamais un début de nom');
        $other = Line::factory()->create(['slug' => 'rfn-975000', 'code' => '975000']);
        $this->assertSame(['unlocated', null, null], $this->place($locator, $other, 'Gammaville', 'Bifurcation d\'Alphaville'), 'points d’une autre ligne');
    }

    public function test_the_drawn_section_is_calibrated_on_the_landmarks(): void
    {
        // Forme du PK 10+000 au PK 21+000 (11,1 km) ; le point de PK 18+000 est à mi-chemin : la numérotation n’est
        // pas proportionnelle à la longueur, comme sur les LGV sans passage à niveau.
        DB::insert("INSERT INTO sncf_line_geometries (code_ligne, libelle, pk_debut_m, pk_fin_m, geom, created_at, updated_at)
            VALUES ('974000', 'Ligne de test', 10000, 21000, ST_GeomFromText('LINESTRING(2.0 48.0, 2.0 48.1)', 4326), now(), now())");
        $this->landmarks([['name' => 'Bifurcation de Gammaville', 'pk_m' => 18_000, 'lat' => 48.05]]);

        $length = fn (?string $wkb): float => (float) DB::scalar('SELECT ST_Length(ST_GeomFromEWKB(decode(?, \'hex\'))::geography)', [$wkb]);
        $this->assertEqualsWithDelta(5_560, $length((new SignallingSectionLocator($this->file))->geometry('974000', 10_000, 18_000)), 30);
        $this->assertEqualsWithDelta(8_090, $length((new SignallingSectionLocator(database_path('data/line_landmarks.php')))->geometry('974000', 10_000, 18_000)), 30,
            'sans point sur la ligne, interpolation entre les extrémités de la forme');
    }

    /** @return array{?string, ?int, ?int} */
    private function place(SignallingSectionLocator $locator, Line $line, string $from, string $to, ?int $betaville = null): array
    {
        if ($betaville !== null) {
            $this->station('Betaville', $line, $betaville, 'sncf');
        }
        $place = $locator->locate($line->id, 'section', $from, $to, null, null);

        return [$place['location'], $place['pk_start_m'], $place['pk_end_m']];
    }

    /** @param  list<array<string, mixed>>  $points */
    private function landmarks(array $points): void
    {
        $this->file = sys_get_temp_dir().'/vff-landmarks-'.bin2hex(random_bytes(4)).'.php';
        $entries = array_map(fn (array $p): array => $p + [
            'line' => '974000', 'aliases' => [], 'kind' => 'junction', 'lat' => 48.04, 'lon' => 2.0, 'method' => 'sncf_calibrated',
            'sources' => [['publisher' => 'SNCF Réseau', 'title' => 'Formes des lignes du RFN', 'url' => 'https://ressources.data.sncf.com/explore/dataset/formes-des-lignes-du-rfn/']],
        ], $points);
        file_put_contents($this->file, '<?php return '.var_export($entries, true).';');
    }

    private function station(string $name, Line $line, int $pk, string $source): Station
    {
        $station = Station::factory()->create(['name' => $name, 'slug' => 'gare-'.strtolower($name).'-'.$pk]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
            'pk_m' => $pk, 'pk_source' => $source, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }
}
