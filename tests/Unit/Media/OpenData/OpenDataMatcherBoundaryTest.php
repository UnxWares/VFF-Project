<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OpenDataMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Seuils de distance et de score du rattachement aux gares, mesurés au mètre près (les gares sont sur un même
 * méridien : un mètre vaut 1/111194,93 degré de latitude).
 */
class OpenDataMatcherBoundaryTest extends TestCase
{
    private const M = 111194.9266;

    private OpenDataMatcher $m;

    protected function setUp(): void
    {
        $this->m = new OpenDataMatcher(false);
        $this->m->add(1, 'Solo', 'Soloville', 'gare', 'active', 47.0, 3.0);
        $this->m->add(2, 'Zenith', 'Zenville', 'gare', 'active', 46.0, 3.0);
        $this->m->add(3, 'Dua', 'Duoville', 'gare', 'active', 44.0, 3.0);
        $this->m->add(4, 'Dub', 'Duoville', 'halte', 'active', 44.05, 3.0);
        $this->m->add(5, 'Twin', 'Twinville', 'gare', 'active', 43.0, 3.0);
        $this->m->add(6, 'Twin', 'Twinville', 'gare', 'active', 43.02, 3.0);
        $this->m->add(7, 'Tria', 'Triville', 'gare', 'active', 42.0, 3.0);
        $this->m->add(8, 'Trib', 'Triville', 'halte', 'active', 42.0, 3.0 + 0.0001);
    }

    /** Point à `$meters` mètres au sud de `$lat`. */
    private static function south(float $lat, float $meters): float
    {
        return $lat - $meters / self::M;
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private static function c(string $commune, string $title, float $lat, array $over = []): array
    {
        return $over + ['commune' => $commune, 'title' => $title, 'title_alt' => null, 'station_hint' => null, 'caption' => null,
            'themes' => 'Gare', 'subjects' => null, 'lat' => $lat, 'lng' => 3.0, 'point_precise' => true];
    }

    public function test_the_only_stop_of_a_commune_is_kept_up_to_350_metres(): void
    {
        $ok = $this->m->matchStation(self::c('Soloville', 'Place de la gare', self::south(47.0, 349.6)));
        $this->assertSame([1, 'commune-unique', 350], [$ok['id'], $ok['method'], $ok['distance_m']]);
        $far = $this->m->matchStation(self::c('Soloville', 'Place de la gare', self::south(47.0, 350.6)));
        $this->assertSame(['kind' => null, 'reason' => 'gare_trop_loin'], $far);
    }

    public function test_a_station_named_in_the_text_is_kept_up_to_1500_metres_a_commune_name_only_up_to_350(): void
    {
        $ok = $this->m->matchStation(self::c('Zenville', 'Gare de Zenith', self::south(46.0, 1499.6)));
        $this->assertSame([2, 'nom', 1500], [$ok['id'], $ok['method'], $ok['distance_m']]);
        $far = $this->m->matchStation(self::c('Zenville', 'Gare de Zenith', self::south(46.0, 1500.6)));
        $this->assertSame('gare_trop_loin', $far['reason']);
    }

    public function test_the_named_by_commune_limit_is_350_metres(): void
    {
        // « Zenith » n'est lu que dans la commune (titre sans le nom) : « nom-commune », limite de 350 m.
        $ok = $this->m->matchStation(self::c('Zenith', 'La gare', self::south(46.0, 349.6)));
        $this->assertSame([2, 'nom-commune'], [$ok['id'], $ok['method']]);
        $far = $this->m->matchStation(self::c('Zenith', 'La gare', self::south(46.0, 350.6)));
        $this->assertSame('gare_trop_loin', $far['reason']);
    }

    public function test_an_unnamed_view_is_attached_by_the_point_within_300_metres_only(): void
    {
        $near = $this->m->matchStation(self::c('Duoville', 'La gare', self::south(44.0, 299.5)));
        $this->assertSame([3, 'point'], [$near['id'], $near['method']]);
        $far = $this->m->matchStation(self::c('Duoville', 'La gare', self::south(44.0, 300.5)));
        $this->assertSame(['kind' => null, 'reason' => 'gares_multiples'], $far);
    }

    public function test_the_nearest_stop_must_lead_the_next_by_150_metres(): void
    {
        // Tria et Trib sont à 8 m l'une de l'autre : aucune ne l'emporte de 150 m.
        $r = $this->m->matchStation(self::c('Triville', 'La gare', self::south(42.0, 50)));
        $this->assertSame('gares_multiples', $r['reason']);
    }

    public function test_the_margin_is_measured_between_the_two_nearest_stops(): void
    {
        $m = new OpenDataMatcher(false);
        $m->add(1, 'Aa', 'Ville', 'gare', 'active', 40.0, 3.0);
        $m->add(2, 'Bb', 'Ville', 'gare', 'active', 40.0 + 149 / self::M, 3.0);
        $m->add(3, 'Cc', 'Ville', 'gare', 'active', 41.0, 3.0);
        // Point au sud de Aa : Bb est 149 m plus loin que Aa, moins que les 150 m exigés.
        $this->assertSame('gares_multiples', $m->matchStation(self::c('Ville', 'La gare', 40.0 - 100 / self::M))['reason']);
        $m2 = new OpenDataMatcher(false);
        $m2->add(1, 'Aa', 'Ville', 'gare', 'active', 40.0, 3.0);
        $m2->add(2, 'Bb', 'Ville', 'gare', 'active', 40.0 + 152 / self::M, 3.0);
        $m2->add(3, 'Cc', 'Ville', 'gare', 'active', 41.0, 3.0);
        $ok = $m2->matchStation(self::c('Ville', 'La gare', 40.0 - 100 / self::M));
        $this->assertSame([1, 'point'], [$ok['id'], $ok['method']], 'Bb à 152 m derrière : Aa l’emporte');
    }

    public function test_two_stations_with_the_same_name_are_split_by_the_point_only_when_clearly_nearest(): void
    {
        $near = $this->m->matchStation(self::c('Twinville', 'Gare de Twin', self::south(43.0, 399.0)));
        $this->assertSame(5, $near['id']);
        $this->assertSame('noms_ambigus', $this->m->matchStation(self::c('Twinville', 'Gare de Twin', self::south(43.0, 401.0)))['reason']);
        $this->assertSame('noms_ambigus', $this->m->matchStation(self::c('Twinville', 'Gare de Twin', 43.01))['reason'], 'à mi-chemin : ambigu');
        $this->assertSame('noms_ambigus', $this->m->matchStation(self::c('Twinville', 'Gare de Twin', 43.0, ['point_precise' => false]))['reason'], 'sans point précis : ambigu');
    }

    public function test_a_longer_name_beats_a_shorter_one(): void
    {
        $m = new OpenDataMatcher(false);
        $m->add(1, 'Saint-Lazare', 'Ville', 'gare', 'active', 40.0, 3.0);
        $m->add(2, 'Lazare', 'Ville', 'gare', 'active', 40.0, 3.0);
        $r = $m->matchStation(self::c('Ville', 'Gare Saint-Lazare', 40.0, ['point_precise' => false]));
        $this->assertSame([1, 'nom'], [$r['id'], $r['method']], 'deux mots lus battent un seul');
        $r = $m->matchStation(self::c('Ville', 'Gare Lazare', 40.0, ['point_precise' => false]));
        $this->assertSame(2, $r['id']);
    }

    public function test_one_letter_and_two_letter_names_are_not_read_in_the_text(): void
    {
        $m = new OpenDataMatcher(false);
        $m->add(1, 'Ax', 'Ville', 'gare', 'active', 40.0, 3.0);
        $m->add(2, 'Axe', 'Ville', 'gare', 'active', 40.0, 3.0);
        // « Ax » (2 lettres, mot seul) est ignoré ; « Axe » (3 lettres) est lu.
        $r = $m->matchStation(self::c('Ville', 'Gare de Axe', 40.0, ['point_precise' => false]));
        $this->assertSame(2, $r['id']);
        $r = $m->matchStation(self::c('Ville', 'Gare de Ax', 40.0, ['point_precise' => false]));
        $this->assertSame('gares_multiples', $r['reason'], 'nom de 2 lettres : pas lu, deux gares possibles');
    }

    public function test_empty_and_odd_inputs_do_not_attach(): void
    {
        $this->assertSame('sans_commune', $this->m->matchStation(['commune' => null] + self::c('x', 'La gare', 47.0, ['lat' => null, 'lng' => null, 'point_precise' => false]))['reason']);
        $this->assertSame('commune_sans_gare', $this->m->matchStation(self::c('Nullepart', 'La gare', 10.0, ['point_precise' => false]))['reason']);
        $this->assertSame('sans_mot_gare', $this->m->matchStation(self::c('Soloville', '', 47.0, ['themes' => null]))['reason']);
        $this->assertSame('sans_mot_gare', $this->m->matchStation(self::c('Soloville', str_repeat('x ', 5000), 47.0, ['themes' => null]))['reason']);
    }

    public function test_stations_of_a_neighbouring_commune_join_the_pool_inside_the_commune_radius(): void
    {
        $m = new OpenDataMatcher(false);
        $m->add(1, 'Voisine', 'Ailleurs', 'gare', 'active', 40.0, 3.0);
        $inside = $m->matchStation(self::c('Maville', 'Gare de Voisine', self::south(40.0, 1499.0)));
        $this->assertSame(1, $inside['id']);
        $outside = $m->matchStation(self::c('Maville', 'Gare de Voisine', self::south(40.0, 1501.0)));
        $this->assertSame('commune_sans_gare', $outside['reason']);
        $edge = $m->matchStation(self::c('Maville', 'Gare de Voisine', self::south(40.0, 1500.4)));
        $this->assertSame('commune_sans_gare', $edge['reason'], 'à 1500,4 m : hors du rayon de 1500 m');
    }

    public function test_without_a_precise_point_the_centroid_radius_is_4_kilometres(): void
    {
        $m = new OpenDataMatcher(false);
        $m->add(1, 'Voisine', 'Ailleurs', 'gare', 'active', 40.0, 3.0);
        $this->assertSame(1, $m->matchStation(self::c('Maville', 'Gare de Voisine', self::south(40.0, 3999.0), ['point_precise' => false]))['id'], 'rayon de 4 km, aussi dans le sens nord-sud');
        $this->assertSame('commune_sans_gare', $m->matchStation(self::c('Maville', 'Gare de Voisine', self::south(40.0, 4000.4), ['point_precise' => false]))['reason']);
        $this->assertSame('commune_sans_gare', $m->matchStation(self::c('Maville', 'Gare de Voisine', self::south(40.0, 4001.0), ['point_precise' => false]))['reason']);
    }

    public function test_the_first_name_of_equal_length_decides_whether_the_text_names_the_station(): void
    {
        $m = new OpenDataMatcher(false);
        $m->add(1, 'Aaa Bbb', 'Bbb', 'gare', 'active', 40.0, 3.0, [['ccc', 'ddd']]);
        // Nom : « aaa » dans le texte, « bbb » dans la commune seulement ; autre nom de même longueur : lu dans le texte.
        $r = $m->matchStation(self::c('Bbb', 'Gare Aaa ccc ddd', 40.0, ['point_precise' => false]));
        $this->assertSame([1, 'nom-commune'], [$r['id'], $r['method']]);
    }
}
