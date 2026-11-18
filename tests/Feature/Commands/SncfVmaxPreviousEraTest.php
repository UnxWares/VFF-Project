<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-sncf-vmax --previous : le jeu SNCF du jour vaut pour l'ère précédente (2020), SAUF sur
 * les portions modifiées depuis le 31/12/2020 (ouverture, réouverture, fermeture, électrification,
 * relèvement de vitesse datés). Règle validée par l'utilisateur le 20/10/2026.
 */
class SncfVmaxPreviousEraTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $current;

    private Era $previous;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = $this->era(2020);
        $this->current = $this->era(2030, current: true);
        $this->sandboxStorage();
        $this->cacheDir = $this->sandbox.'/app/sources/sncf-vmax';
        Http::preventStrayRequests();
        @mkdir($this->cacheDir, 0775, true);
        file_put_contents("{$this->cacheDir}/vitesse-maximale-nominale-sur-ligne.json", json_encode(['records' => [
            // Forme SNCF orientée nord → sud (début au PK 0 au nord) : le placement par PK suit c_geo_d.
            ['code_ligne' => '600000', 'v_max' => '160', 'pkd' => '000+000', 'pkf' => '022+240', 'c_geo_d' => ['lon' => 4.00, 'lat' => 47.20],
                'geo_shape' => ['geometry' => ['type' => 'LineString', 'coordinates' => [[4.00, 47.20], [4.00, 47.00]]]]],
        ]]));
    }

    /** Deux tronçons de 11 km par ère (nord et sud) ; renvoie [ère => [sud, nord]]. */
    private function twoPieces(Line $line): array
    {
        $out = [];
        foreach ([$this->previous, $this->current] as $era) {
            $out[$era->year] = [
                $this->track($line, $era, [[47.00, 4.0003], [47.10, 4.0003]], ['max_speed_kmh' => null]),
                $this->track($line, $era, [[47.10, 4.0003], [47.20, 4.0003]], ['max_speed_kmh' => null]),
            ];
        }

        return $out;
    }

    private function event(Line $line, string $type, string $date, ?array $portion, string $text = 'Événement', array $attributes = []): void
    {
        DB::table('line_events')->insert($attributes + [
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'description' => $text, 'source' => 'manual',
            'section_resolution' => $portion === null ? 'whole_line' : 'places',
            'section_geom' => $portion === null ? null : DB::raw(sprintf("ST_Multi(ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326))",
                $portion[0][1], $portion[0][0], $portion[1][1], $portion[1][0])),
        ]);
    }

    private function run2020(Line $line): void
    {
        $this->artisan('vff:import-sncf-vmax', ['--line' => $line->code, '--cache-only' => true, '--previous' => true, '--cache-dir' => $this->cacheDir])
            ->assertSuccessful();
    }

    private function speed(object $seg): ?int
    {
        return DB::table('line_segments')->where('id', $seg->id)->value('max_speed_kmh');
    }

    public function test_unchanged_portions_take_todays_speed_in_2020(): void
    {
        $line = $this->rfnLine('600000', 'Ligne de Alpha à Bravo');
        $s = $this->twoPieces($line);
        // Électrification ANTÉRIEURE au 31/12/2020 : sans effet.
        $this->event($line, 'electrification', '2019-06-01', [[47.00, 4.0003], [47.10, 4.0003]]);

        $this->run2020($line);

        foreach ([2020, 2030] as $y) {
            $this->assertSame(160, $this->speed($s[$y][0]));
            $this->assertSame(160, $this->speed($s[$y][1]));
        }
    }

    public function test_portion_reopened_after_2020_gets_no_2020_speed(): void
    {
        $line = $this->rfnLine('600000', 'Ligne de Alpha à Bravo');
        $s = $this->twoPieces($line);
        $this->event($line, 'reopening_passenger', '2022-07-03', [[47.00, 4.0003], [47.10, 4.0003]]);

        $this->run2020($line);

        $this->assertNull($this->speed($s[2020][0]), 'rouverte en 2022 : pas de vitesse 2020 tirée du jeu actuel');
        $this->assertSame(160, $this->speed($s[2020][1]), 'portion voisine inchangée');
        $this->assertSame(160, $this->speed($s[2030][0]), 'ère courante : toujours la vitesse du jour');
    }

    public function test_every_change_type_counts_and_the_speed_already_set_is_cleared(): void
    {
        foreach (['opening', 'reopening', 'closure', 'closure_freight', 'closure_passenger', 'electrification'] as $i => $type) {
            $line = $this->rfnLine('60000'.$i, "Ligne {$type}");
            $seg = $this->track($line, $this->previous, [[47.00, 4.0003], [47.10, 4.0003]], ['max_speed_kmh' => 140]);
            $this->event($line, $type, '2024-01-15', null);
            $this->artisan('vff:import-sncf-vmax', ['--line' => $line->code, '--cache-only' => true, '--previous' => true, '--cache-dir' => $this->cacheDir])
                ->assertSuccessful();
            $this->assertNull($this->speed($seg), "{$type} après 2020 (toute la ligne) : vitesse 2020 effacée");
        }
    }

    public function test_speed_upgrade_counts_but_other_modernisations_and_future_events_do_not(): void
    {
        $line = $this->rfnLine('600000', 'Ligne de Alpha à Bravo');
        $s = $this->twoPieces($line);
        $this->event($line, 'modernization', '2023-05-01', [[47.00, 4.0003], [47.10, 4.0003]], 'Relèvement de la vitesse à 160 km/h');
        $this->event($line, 'modernization', '2023-05-01', [[47.10, 4.0003], [47.20, 4.0003]], 'Quatrième voie mise en service');
        // Réouverture annoncée (date future) : la portion n'est pas encore modifiée.
        $this->event($line, 'reopening', now()->addYear()->toDateString(), [[47.10, 4.0003], [47.20, 4.0003]]);

        $this->run2020($line);

        $this->assertNull($this->speed($s[2020][0]));
        $this->assertSame(160, $this->speed($s[2020][1]));
    }

    public function test_unresolved_portion_is_placed_by_pk_on_sncf_records_and_an_unplaceable_named_one_is_ignored(): void
    {
        $line = $this->rfnLine('600000', 'Ligne de Alpha à Bravo');
        $s = $this->twoPieces($line);
        // Fermeture SNCF non calée sur le tracé (370000, 990000) : PK 0 à 10 km = moitié NORD (forme SNCF inversée).
        $this->event($line, 'closure', '2021-11-30', null, 'Fermeture', ['section_resolution' => 'unresolved',
            'section_from' => 'Louviers', 'section_to' => 'Saint-Germain', 'section_pk_start_m' => 0, 'section_pk_end_m' => 10_000]);
        // Électrification d'une portion nommée absente du tracé (338000 : « Gisors - Serqueux ») : écartée.
        $this->event($line, 'electrification', '2021-03-01', null, 'Électrification', ['section_resolution' => 'unresolved',
            'section_from' => 'Gisors', 'section_to' => 'Serqueux']);

        $this->run2020($line);

        $this->assertSame(160, $this->speed($s[2020][0]), 'moitié sud : hors des PK fermés');
        $this->assertNull($this->speed($s[2020][1]), 'moitié nord : PK 0 à 10 km');
    }

    public function test_unresolved_event_without_any_portion_counts_for_the_whole_line(): void
    {
        $line = $this->rfnLine('600000', 'Ligne de Alpha à Bravo');
        $s = $this->twoPieces($line);
        // 631000 : « La ligne est rouverte le 22 mars 2026. » (aucune portion citée).
        $this->event($line, 'reopening', '2026-03-22', null, 'Réouverture', ['section_resolution' => 'unresolved']);

        $this->run2020($line);

        $this->assertNull($this->speed($s[2020][0]));
        $this->assertNull($this->speed($s[2020][1]));
        $this->assertSame(160, $this->speed($s[2030][1]));
    }
}
