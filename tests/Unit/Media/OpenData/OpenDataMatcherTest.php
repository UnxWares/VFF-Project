<?php

namespace Tests\Unit\Media\OpenData;

use App\Services\Media\OpenData\OpenDataMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement des vues aux gares (sans base : gares injectées) : nom, nom et commune, point précis, gare seule,
 * gare d'une commune voisine, alias, noms de rues, funiculaires, distances. Les cas viennent de lignes réelles des
 * jeux (Albert-Kahn, cartes postales des Hauts-de-Seine, Évry-Courcouronnes).
 */
class OpenDataMatcherTest extends TestCase
{
    private OpenDataMatcher $m;

    protected function setUp(): void
    {
        $this->m = new OpenDataMatcher(false);
        $this->m->add(1, 'Chaville-Vélizy', 'Viroflay', 'gare', 'active', 48.80842, 2.18915);
        $this->m->add(2, 'Chaville-Rive-Gauche', 'Chaville', 'gare', 'active', 48.80806, 2.18874);
        $this->m->add(3, 'Chaville-Rive-Droite', 'Chaville', 'gare', 'active', 48.80500, 2.19700);
        $this->m->add(4, 'Clamart', 'Clamart', 'gare', 'active', 48.81435, 2.27500);
        $this->m->add(5, 'La Garenne-Colombes', 'Colombes', 'gare', 'active', 48.90900, 2.24000);
        $this->m->add(6, 'Colombes', 'Colombes', 'gare', 'active', 48.92300, 2.25200);
        $this->m->add(7, 'Paris-Est', 'Paris 10e Arrondissement', 'gare', 'active', 48.87650, 2.35900);
        $this->m->add(8, 'Garches-Marnes-la-Coquette', 'Garches', 'gare', 'active', 48.84500, 2.18000);
        $this->m->add(9, 'Evry-Val de Seine', 'Évry-Courcouronnes', 'gare', 'active', 48.62600, 2.44000);
        $this->m->add(10, 'Évry-Courcouronnes', 'Évry-Courcouronnes', 'gare', 'active', 48.63000, 2.44800);
        $this->m->add(11, 'Meudon', 'Meudon', 'gare', 'active', 48.81300, 2.23500);
        $this->m->add(12, 'Le Bas-Meudon', 'Meudon', 'gare', 'declassified', 48.82200, 2.23200);
        $this->m->add(13, 'Alpha', 'Testville', 'gare', 'active', 45.00000, 3.00000);
        $this->m->add(14, 'Beta', 'Testville', 'halte', 'active', 45.00540, 3.00000);
        $this->m->add(15, 'Gamma', 'Pairville', 'gare', 'active', 46.00000, 3.00000);
        $this->m->add(16, 'Delta', 'Pairville', 'halte', 'active', 46.00050, 3.00000);
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private static function c(string $commune, ?string $title, array $over = []): array
    {
        return $over + ['commune' => $commune, 'title' => $title, 'title_alt' => null, 'station_hint' => null, 'caption' => null,
            'themes' => null, 'subjects' => null, 'lat' => null, 'lng' => null, 'point_precise' => false];
    }

    public function test_a_station_named_in_the_title_is_found_even_in_the_neighbouring_commune(): void
    {
        $r = $this->m->matchStation(self::c('Chaville', 'Ligne des Invalides. - Gare de Chaville velizy', ['lat' => 48.8085584, 'lng' => 2.1886406, 'point_precise' => true]));

        $this->assertSame(['station', 1, 'nom'], [$r['kind'], $r['id'], $r['method']]);
        $this->assertSame('haute', $r['confidence']);
    }

    public function test_a_station_named_after_the_commune_but_located_in_the_next_one_is_found_by_the_point(): void
    {
        $r = $this->m->matchStation(self::c('La Garenne-Colombes', 'Vue sur la Gare', ['lat' => 48.9095, 'lng' => 2.24, 'point_precise' => true]));

        $this->assertSame([5, 'nom-commune'], [$r['id'], $r['method']]);
    }

    public function test_a_generic_title_takes_the_station_named_like_the_commune(): void
    {
        $r = $this->m->matchStation(self::c('Clamart', 'La Gare de Clamart'));

        $this->assertSame([4, 'nom'], [$r['id'], $r['method']]);
        $this->assertNull($r['distance_m'], 'le point d\'un jeu non précis ne donne aucune distance');
    }

    public function test_paris_arrondissements_share_one_commune(): void
    {
        $r = $this->m->matchStation(self::c('Paris', "Travaux d'agrandissement de la gare de l'Est", ['themes' => 'Gare']));

        // « est » se lit dans le titre, « paris » dans le nom de la commune.
        $this->assertSame([7, 'nom-commune'], [$r['id'], $r['method']]);
    }

    public function test_the_precise_point_chooses_among_several_stations_only_when_clearly_nearest(): void
    {
        $near = $this->m->matchStation(self::c('Testville', 'La Gare', ['themes' => 'Gare', 'lat' => 44.99990, 'lng' => 3.00000, 'point_precise' => true]));
        $this->assertSame([13, 'point'], [$near['id'], $near['method']]);

        // Entre Gamma et Delta (55 m l'une de l'autre), à 28 m de chacune : aucun choix.
        $between = $this->m->matchStation(self::c('Pairville', 'La Gare', ['themes' => 'Gare', 'lat' => 46.00025, 'lng' => 3.00000, 'point_precise' => true]));
        $this->assertSame(['kind' => null, 'reason' => 'gares_multiples'], $between);
    }

    public function test_a_commune_with_one_stop_gets_the_unnamed_view_unless_the_point_is_far(): void
    {
        $this->assertSame('commune-unique', $this->m->matchStation(self::c('Garches', 'Place de la Gare'))['method']);

        $far = $this->m->matchStation(self::c('Garches', 'Place de la Gare', ['lat' => 48.8500, 'lng' => 2.1900, 'point_precise' => true]));
        $this->assertSame(['kind' => null, 'reason' => 'gare_trop_loin'], $far);
    }

    public function test_street_and_business_names_and_funicular_stations_are_not_stations(): void
    {
        foreach (['Rue de la Gare et Aubriet', 'Café-Restaurant de la Gare', 'Bas-Meudon - Gare du Funiculaire', 'Boulevard des Trois-Gares'] as $title) {
            $r = $this->m->matchStation(self::c('Meudon', $title));
            $this->assertSame(['kind' => null, 'reason' => 'sans_mot_gare'], $r, $title);
        }
    }

    public function test_a_tramway_station_is_not_a_railway_station(): void
    {
        $this->assertSame('sans_mot_gare', $this->m->matchStation(self::c('Meudon', 'Station du tramway'))['reason']);
    }

    public function test_a_station_alias_names_a_former_station_name(): void
    {
        // Le jeu écrit « Evry-Petit-Bourg » ; les gares de VFF sont dans la ville d'Évry-Courcouronnes.
        $c = self::c('Evry-Petit-Bourg', 'Evry Petit Bourg - La gare', ['pool_commune' => 'Évry-Courcouronnes']);

        $this->assertSame(9, $this->m->matchStation($c + ['station_aliases' => ['Evry-Val de Seine' => ['Evry Petit Bourg']]])['id']);
        // Sans l'alias, deux gares restent possibles : rien n'est rattaché.
        $this->assertSame('gares_multiples', $this->m->matchStation($c)['reason']);
    }

    public function test_a_commune_without_station_is_reported(): void
    {
        $this->assertSame(['kind' => null, 'reason' => 'commune_sans_gare'], $this->m->matchStation(self::c('Thélus', 'La gare')));
        $this->assertSame(['kind' => null, 'reason' => 'sans_commune'], $this->m->matchStation(['commune' => null] + self::c('x', 'La gare')));
    }

    public function test_two_stations_of_the_same_name_are_ambiguous_without_a_precise_point(): void
    {
        $this->m->add(20, 'Meudon', 'Meudon', 'gare', 'declassified', 48.8100, 2.2400);

        $this->assertSame('noms_ambigus', $this->m->matchStation(self::c('Meudon', 'La Gare de Meudon'))['reason']);
    }

    public function test_distance_is_haversine_in_metres(): void
    {
        $this->assertEqualsWithDelta(111195, OpenDataMatcher::distance(48.0, 2.0, 49.0, 2.0), 150);
        $this->assertSame(0.0, OpenDataMatcher::distance(48.0, 2.0, 48.0, 2.0));
    }

    public function test_commune_key_ignores_the_district(): void
    {
        $this->assertSame('paris', OpenDataMatcher::communeKey('Paris 15e Arrondissement'));
        $this->assertSame('lyon', OpenDataMatcher::communeKey('Lyon 2e Arrondissement'));
        $this->assertSame('saint cloud', OpenDataMatcher::communeKey('Saint-Cloud'));
    }

    public function test_a_gare_in_a_street_name_is_not_rescued_by_the_dataset_theme(): void
    {
        // « rue de la Gare » (Armentières, Albert-Kahn) : le sujet « Gare » du jeu ne change rien.
        $r = $this->m->matchStation(self::c('Clamart', 'Maisons reconstruites rue de la Gare', ['subjects' => 'Gare, Maison']));
        $this->assertSame(['kind' => null, 'reason' => 'sans_mot_gare'], $r);

        // Sans nom de rue, le même sujet suffit.
        $this->assertSame(4, $this->m->matchStation(self::c('Clamart', 'La gare vue du pont', ['subjects' => 'Gare, Pont']))['id']);
        $this->assertSame(4, $this->m->matchStation(self::c('Clamart', 'Escalier du quai', ['themes' => 'Gare']))['id']);
    }

    public function test_a_tramway_station_is_refused_even_under_the_gare_theme(): void
    {
        $r = $this->m->matchStation(self::c('Clamart', 'Porte Maillot, la station des Tramways', ['themes' => 'Gare']));
        $this->assertSame('sans_mot_gare', $r['reason']);

        // Un hôtel de gare est un commerce (Albert-Kahn, IF48472).
        $h = $this->m->matchStation(self::c('Clamart', 'Le Grand Hôtel Terminus fermé à l\'occasion du 1er mai, rue Saint-Lazare', ['subjects' => 'Gare, Hôtel']));
        $this->assertSame('sans_mot_gare', $h['reason']);
    }
}
