<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Services\DataInvariants;
use App\Services\LineClassifier;
use App\Services\PassengerPortions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * vff:classify-lines : gares de voyageurs SNCF posées sur le tracé d'une ligne quel que soit leur
 * code, réouvertures aux voyageurs datées (App\Services\PassengerPortions::adopt(),
 * reopenedPassengerSql(), reopenedAfterEraSql()), et l'invariant
 * segments.current_freight_on_reopened_or_station.
 *
 * Cas réel : 944000 Cannes-la Bocca - Grasse, rouverte aux voyageurs le 26/03/2005, était « fret »
 * en 2030 : liste-des-gares rattache Grasse au code 930000 (coordonnées de Marseille) et ignore
 * Mouans-Sartoux, Ranguin, Le Bosquet.
 *
 * Lignes est-ouest de BuildsRailNetwork (portionLine), espacées de 0,1°.
 */
class PassengerStationOnTrackTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->current = Era::factory()->current()->create(['year' => 2030]);
    }

    public function test_passenger_stations_of_the_sncf_station_list_on_the_track_make_the_line_mixed(): void
    {
        $lat = 46.10;
        $line = $this->portionLine($this->current->id, '944000', $lat, traffic: 'freight', name: 'Ligne de Cannes-la Bocca à Grasse');
        // liste-des-gares : Grasse sous un autre code, aux coordonnées de Marseille.
        DB::table('sncf_gares')->insert(['code_uic' => '87757724', 'code_ligne' => '930000', 'voyageurs' => true, 'fret' => false,
            'libelle' => 'Grasse', 'lat' => 43.3028, 'lon' => 5.3806]);
        $this->voyageursStation('Ranguin', $lat, 2.003);
        $this->voyageursStation('Mouans-Sartoux', $lat, 2.3);
        $this->voyageursStation('Grasse', $lat, 2.597);

        $this->classify();

        foreach ([2.1, 2.45] as $lon) {
            $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, $lon), "lon {$lon}");
        }
        $this->assertSame('mixed', $line->fresh()->type?->value);
        $source = $this->source($line);
        $this->assertSame('sncf-liste-des-gares', $source['signal']);
        $this->assertSame(['Ranguin', 'Mouans-Sartoux', 'Grasse'], $source['extra']['gares_sur_trace']);
    }

    public function test_a_freight_line_parallel_to_a_passenger_line_or_branching_at_its_station_stays_freight(): void
    {
        // Ligne voyageurs et ligne fret parallèle à 44 m : la gare (sur la voie voyageurs) n'est pas adoptée par la ligne fret.
        // Tronçons courts autour des gares : un long tronçon est-ouest s'écarte du parallèle (arc de grand cercle, 44 m sur 0,6°).
        $short = [[2.0, 2.28], [2.28, 2.32], [2.32, 2.6]];
        $lat = 46.20;
        $passenger = $this->portionLine($this->current->id, '944100', $lat, pieces: $short, traffic: 'freight');
        $parallel = $this->portionLine($this->current->id, '944110', $lat + 0.0004, pieces: [[2.25, 2.35]], traffic: 'freight',
            status: [[2.25, 2.35, 'Exploitée']]);
        $this->voyageursStation('Halte voyageurs', $lat, 2.3);
        // Branche fret qui part d'une gare voyageurs de bifurcation (aussi près des deux lignes) : pas adoptée.
        $lat2 = 46.30;
        $branch = $this->portionLine($this->current->id, '944200', $lat2, pieces: [[2.0, 2.3]], traffic: 'freight', status: [[2.0, 2.3, 'Exploitée']]);
        $main = $this->portionLine($this->current->id, '944300', $lat2, pieces: [[2.3, 2.6]], traffic: 'freight', status: [[2.3, 2.6, 'Exploitée']]);
        $this->portionStation('944300', $lat2, 2.45, voyageurs: true, fret: false);
        $this->portionStation('944300', $lat2, 2.59, voyageurs: true, fret: false);
        $this->voyageursStation('Bifurcation', $lat2, 2.3);
        // Gare voyageurs à 150 m du tracé (au-delà de ADOPT_M) : pas adoptée.
        $lat3 = 46.40;
        $far = $this->portionLine($this->current->id, '944400', $lat3, pieces: $short, traffic: 'freight');
        $this->voyageursStation('Trop loin', $lat3 + 0.00135, 2.3);

        $this->classify();

        $this->assertSame('mixed', $this->portionTrafficAt($passenger, $this->current->id, $lat, 2.3));
        $this->assertSame('freight', $this->portionTrafficAt($parallel, $this->current->id, $lat + 0.0004, 2.3), 'ligne fret parallèle');
        $this->assertSame('freight', $this->portionTrafficAt($branch, $this->current->id, $lat2, 2.15), 'branche fret à la gare de bifurcation');
        $this->assertSame('mixed', $this->portionTrafficAt($main, $this->current->id, $lat2, 2.5));
        $this->assertSame('freight', $this->portionTrafficAt($far, $this->current->id, $lat3, 2.3), 'gare à 150 m');
    }

    public function test_a_dated_passenger_reopening_without_later_closure_is_mixed(): void
    {
        $lat = 46.50;
        // Tronçons du moteur coupés au bout de la portion rouverte (le milieu de chaque tronçon décide).
        $line = $this->portionLine($this->current->id, '944500', $lat, pieces: [[2.0, 2.3], [2.3, 2.6]], traffic: 'freight');
        $this->portionStation('944500', $lat, 2.55, voyageurs: false, fret: true);
        $this->portionEvent($line, 'closure_passenger', '1938-10-02', $lat, 2.0, 2.6, 'high');
        $this->portionEvent($line, 'reopening_passenger', '2005-03-26', $lat, 2.0, 2.3, 'high');
        // Réouverture puis refermeture : fret. Réouverture sans portion résolue : ne vaut pas toute la ligne.
        $lat2 = 46.60;
        $closedAgain = $this->portionLine($this->current->id, '944600', $lat2, traffic: 'freight');
        $this->portionEvent($closedAgain, 'reopening_passenger', '1978-05-27', $lat2, 2.0, 2.6, 'high');
        $this->portionEvent($closedAgain, 'closure_passenger', '1995-11-26', $lat2, 2.0, 2.6, 'high');
        $lat3 = 46.70;
        $unresolved = $this->portionLine($this->current->id, '944700', $lat3, traffic: 'freight');
        $this->portionEvent($unresolved, 'reopening_passenger', '1981-12-18', $lat3);

        $this->classify();

        $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, 2.1), 'portion rouverte');
        $this->assertSame('freight', $this->portionTrafficAt($line, $this->current->id, $lat, 2.45), 'hors portion rouverte');
        $this->assertSame('mixed', $line->fresh()->type?->value);
        $this->assertSame('reouverture-voyageurs-datee-portions', $this->source($line)['signal']);
        $this->assertSame('freight', $this->portionTrafficAt($closedAgain, $this->current->id, $lat2, 2.3), 'refermée en 1995');
        $this->assertSame('freight', $this->portionTrafficAt($unresolved, $this->current->id, $lat3, 2.3), 'portion non résolue');
    }

    public function test_past_era_station_reopened_after_the_era_is_not_served(): void
    {
        // Rive droite du Rhône : fermée aux voyageurs en 1973, rouverte le 29/08/2022 ; liste-des-gares (aujourd'hui) la dit voyageurs.
        $past = Era::factory()->create(['year' => 2020]);
        $lat = 46.80;
        $line = $this->portionLine($this->current->id, '944800', $lat, traffic: 'freight');
        $this->portionSegment($line, $past->id, $lat, 2.0, 2.6, 'mixed');
        $this->portionStation('944800', $lat, 2.1, voyageurs: true, fret: false);
        $this->portionStation('944800', $lat, 2.3, voyageurs: false, fret: true);
        $this->portionStation('944800', $lat, 2.5, voyageurs: true, fret: false);
        $this->portionEvent($line, 'closure_passenger', '1973-01-01', $lat, 2.0, 2.6, 'high');
        $this->portionEvent($line, 'reopening_passenger', '2022-08-29', $lat, 2.0, 2.6, 'high');
        // Ligne fermée (forme SNCF « Fermée ») après 2020 : pas « hors RFN, autre opérateur » en 2020.
        $closedSince = $this->portionLine($past->id, '944900', 46.90, traffic: 'mixed', status: [[2.0, 2.6, 'Fermée']]);
        $this->portionStation('944900', 46.90, 2.3, voyageurs: false, fret: true);
        $this->portionEvent($closedSince, 'closure', '2024-07-10', 46.90, 2.0, 2.6, 'high');

        app(LineClassifier::class)->classifyEra($past->id);
        $this->assertSame('freight', $this->portionTrafficAt($line, $past->id, $lat, 2.3), '2020 : pas encore rouverte');
        $this->assertSame('freight', $this->portionTrafficAt($closedSince, $past->id, 46.90, 2.3), '2020 : fermée en 2024 seulement');

        $this->classify();
        $this->assertSame('mixed', $this->portionTrafficAt($line, $this->current->id, $lat, 2.3), '2030 : rouverte');
    }

    public function test_invariant_freight_segment_reopened_or_on_a_passenger_station_of_any_code(): void
    {
        $lat = 46.10;
        $station = $this->portionLine($this->current->id, '945000', $lat, traffic: 'freight', name: 'Ligne Gare');
        $this->voyageursStation('Grasse', $lat, 2.3);
        $reopened = $this->portionLine($this->current->id, '945100', 46.20, traffic: 'freight', name: 'Ligne Rouverte');
        $this->portionEvent($reopened, 'reopening_passenger', '2005-03-26', 46.20, 2.0, 2.6, 'high');
        // Conformes : gare de voyageurs au bout du tronçon fret (bifurcation), réouverture refermée, ligne mixte.
        $end = $this->portionLine($this->current->id, '945200', 46.30, pieces: [[2.0, 2.3]], traffic: 'freight', status: [[2.0, 2.3, 'Exploitée']]);
        $this->voyageursStation('Bout', 46.30, 2.3);
        $closed = $this->portionLine($this->current->id, '945300', 46.40, traffic: 'freight');
        $this->portionEvent($closed, 'reopening_passenger', '1978-05-27', 46.40, 2.0, 2.6, 'high');
        $this->portionEvent($closed, 'closure_passenger', '1995-11-26', 46.40, 2.0, 2.6, 'high');
        $mixed = $this->portionLine($this->current->id, '945400', 46.50, traffic: 'mixed');
        $this->voyageursStation('Mixte', 46.50, 2.3);

        [$r] = (new DataInvariants(['segments.current_freight_on_reopened_or_station']))->run();

        $this->assertSame(2, $r['count'], implode("\n", $r['examples']));
        $examples = implode("\n", $r['examples']);
        $this->assertStringContainsString('945000 Ligne Gare : ', $examples);
        $this->assertStringContainsString('(gare voyageurs Grasse)', $examples);
        $this->assertStringContainsString('945100 Ligne Rouverte : ', $examples);
        $this->assertStringContainsString('(réouverture aux voyageurs datée)', $examples);
        $this->assertSame(PassengerPortions::ADOPT_M, 100);
    }

    private function classify(): array
    {
        return app(LineClassifier::class)->classifyEra($this->current->id);
    }

    /** Gare du jeu « gares de voyageurs » SNCF (station_sources sncf_voyageurs), recalée sur la voie. */
    private function voyageursStation(string $name, float $lat, float $lon): void
    {
        static $n = 0;
        $n++;
        $id = DB::table('stations')->insertGetId(['slug' => "gare-voyageurs-{$n}", 'name' => $name,
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lon, $lat)), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('station_sources')->insert(['source' => 'sncf_voyageurs', 'source_ref' => sprintf('8799%04d', $n), 'station_id' => $id,
            'name' => $name, 'lifecycle' => 'active', 'passenger' => true, 'seen_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lon, $lat))]);
    }

    /** @return array<string, mixed> source « sncf-classification » de la ligne */
    private function source(Line $line): array
    {
        $fields = json_decode((string) DB::table('line_sources')->where('line_id', $line->id)
            ->where('source_type', 'sncf-classification')->value('fields_provided'), true) ?? [];

        return ['signal' => $fields['signal'] ?? null, 'extra' => $fields];
    }
}
