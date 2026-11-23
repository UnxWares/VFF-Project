<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Services\LineFacts;
use App\Support\FrenchDepartments;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Bloc « Fiche » de la fiche ligne (LineFacts, contrôle qualité du 2026-10-20) : sur la 752000
 * (711 km de Combs-la-Ville à Marseille), la fiche donnait la longueur, la vitesse et les régions de
 * l'élément Wikidata « LGV Sud-Est » (la seule LN1 : 409 km, 300 km/h, 3 régions) alors que
 * l'en-tête disait 320 km/h. Chaque valeur dit désormais à quoi elle se rapporte et de quand elle date.
 */
class LineFactsTest extends TestCase
{
    use RefreshDatabase;

    private function encyclopedia(array $values = []): object
    {
        return (object) ($values + [
            'length_official_m' => null, 'gauge_mm_default' => null, 'nb_voies_default' => null, 'max_speed_kmh_official' => null,
            'concession_year' => null, 'wikipedia_title' => null, 'regions' => null, 'departements' => null,
        ]);
    }

    /** @return array<string, array<string, mixed>> clé:portée => fait */
    private function byKey(array $facts): array
    {
        return collect($facts)->keyBy(fn ($f) => $f['key'].':'.$f['scope'])->all();
    }

    public function test_wikidata_values_of_a_part_of_the_line_are_attributed_to_their_item(): void
    {
        $line = Line::factory()->create();
        $facts = $this->byKey(LineFacts::build($line, $this->encyclopedia([
            'length_official_m' => 409_000, 'max_speed_kmh_official' => 300, 'wikipedia_title' => 'LGV Sud-Est', 'nb_voies_default' => 2,
        ]), ['length_km' => 711.3, 'max_speed_kmh' => 320, 'gauge_mm' => 1435], 2030, true));

        $this->assertSame(['km' => 409.0, 'source' => 'wikidata', 'scope' => 'item', 'item' => 'LGV Sud-Est'],
            array_diff_key($facts['length:item'], ['key' => 1, 'label' => 1, 'note' => 1]));
        // Libellé et précision rédigés : la valeur d'une partie de la ligne porte son nom dans le libellé.
        $this->assertSame(['Longueur officielle (LGV Sud-Est)', 'd’après Wikidata, pour cette partie de la ligne seulement'],
            [$facts['length:item']['label'], $facts['length:item']['note']]);
        // Vitesse de l'en-tête (SNCF, aujourd'hui) pour la ligne ; celle de Wikidata, différente, pour son élément.
        $this->assertSame([320, 'sncf', 'today'], [$facts['max_speed:line']['kmh'], $facts['max_speed:line']['source'], $facts['max_speed:line']['as_of']]);
        $this->assertSame([300, 'LGV Sud-Est'], [$facts['max_speed:item']['kmh'], $facts['max_speed:item']['item']]);
        // Écartement des tronçons de la ligne, pas de l'élément.
        $this->assertSame([1435, 'vff', 'line'], [$facts['gauge:line']['mm'], $facts['gauge:line']['source'], $facts['gauge:line']['scope']]);
        $this->assertSame('item', $facts['tracks:item']['scope']);
    }

    public function test_wikidata_values_close_to_the_line_describe_the_whole_line(): void
    {
        $line = Line::factory()->create();
        $facts = $this->byKey(LineFacts::build($line, $this->encyclopedia([
            'length_official_m' => 66_000, 'max_speed_kmh_official' => 160, 'wikipedia_title' => 'Ligne de Limoges à Angoulême',
            'gauge_mm_default' => 1435, 'concession_year' => 1868,
        ]), ['length_km' => 62.4, 'max_speed_kmh' => 160], 1990, false));

        $this->assertSame(['line', null], [$facts['length:line']['scope'], $facts['length:line']['item']]);
        $this->assertSame(1990, $facts['max_speed:line']['as_of'], 'vitesse de l\'ère affichée');
        $this->assertArrayNotHasKey('max_speed:item', $facts, 'même vitesse : pas de doublon');
        $this->assertSame([1435, 'wikidata'], [$facts['gauge:line']['mm'], $facts['gauge:line']['source']]);
        $this->assertSame(1868, $facts['concession:line']['year']);
    }

    public function test_regions_and_departments_come_from_the_whole_track(): void
    {
        $era = Era::factory()->current()->create(['year' => 2030]);
        $line = Line::factory()->create();
        // Tracé de 2,00 à 2,30° E : 2/3 en Saône-et-Loire, 1/3 dans le Rhône, 100 m dans l'Ain (sous le seuil).
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $era->id,
            'geom' => LineString::make([Point::makeGeodetic(46.50, 2.00), Point::makeGeodetic(46.50, 2.30)])]);
        foreach ([['71001', '71', 1.99, 2.20], ['69001', '69', 2.20, 2.2987], ['01001', '01', 2.2987, 2.31]] as [$insee, $dep, $w, $e]) {
            DB::table('commune_contours')->insert(['code_insee' => $insee, 'nom' => "Commune {$insee}", 'departement' => $dep,
                'geom' => DB::raw(sprintf('ST_Multi(ST_MakeEnvelope(%F, 46.4, %F, 46.6, 4326))', $w, $e)), 'created_at' => now(), 'updated_at' => now()]);
        }
        Cache::flush();

        $facts = $this->byKey(LineFacts::build($line, $this->encyclopedia([
            'length_official_m' => 10_000, 'wikipedia_title' => 'Élément partiel', 'regions' => json_encode([['name' => 'Bretagne']]),
        ]), ['length_km' => 23.0], 2030, true));

        $this->assertSame(['Auvergne-Rhône-Alpes', 'Bourgogne-Franche-Comté'], $facts['regions:line']['list']);
        $this->assertSame([['code' => '69', 'name' => 'Rhône'], ['code' => '71', 'name' => 'Saône-et-Loire']], $facts['departements:line']['list']);
        $this->assertArrayNotHasKey('regions:item', $facts, 'les régions de l\'élément ne remplacent pas celles du tracé');
        $this->assertSame([71, 69], array_map(fn ($d) => (int) $d['code'], LineFacts::departments($line->id)), 'du plus long parcours au plus court');
    }

    public function test_departments_are_measured_track_by_track_with_the_same_lengths(): void
    {
        $era = Era::factory()->current()->create(['year' => 2030]);
        $line = Line::factory()->create();
        // Deux tracés bout à bout (2,00 à 2,15° E puis 2,15 à 2,30° E), la limite de département à 2,20° E.
        foreach ([[2.00, 2.15], [2.15, 2.30]] as [$from, $to]) {
            LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $era->id,
                'geom' => LineString::make([Point::makeGeodetic(46.50, $from), Point::makeGeodetic(46.50, $to)])]);
        }
        foreach ([['71001', '71', 1.99, 2.20], ['69001', '69', 2.20, 2.31]] as [$insee, $dep, $w, $e]) {
            DB::table('commune_contours')->insert(['code_insee' => $insee, 'nom' => "Commune {$insee}", 'departement' => $dep,
                'geom' => DB::raw(sprintf('ST_Multi(ST_MakeEnvelope(%F, 46.4, %F, 46.6, 4326))', $w, $e)), 'created_at' => now(), 'updated_at' => now()]);
        }
        Cache::flush();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $departments = LineFacts::departments($line->id);

        // 0,20° et 0,10° de longitude à 46,5° N : 15 353 m et 7 676 m (géodésique), comme sur la ligne fusionnée.
        $this->assertSame([['71', 15353], ['69', 7676]], array_map(fn ($d) => [$d['code'], $d['m']], $departments));
        // La ligne n'est plus fusionnée avant l'intersection (2,7 s sur Paris - Marseille) : chaque tracé est croisé
        // avec les seuls contours proches.
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'commune_contours')
            && str_contains($sql, 'ST_Collect'))));
    }

    public function test_an_invalid_commune_contour_is_repaired_instead_of_failing(): void
    {
        $era = Era::factory()->current()->create(['year' => 2030]);
        $line = Line::factory()->create();
        LineSegment::factory()->create(['line_id' => $line->id, 'era_id' => $era->id,
            'geom' => LineString::make([Point::makeGeodetic(46.50, 2.00), Point::makeGeodetic(46.50, 2.30)])]);
        // Contour en nœud papillon (anneau qui se recoupe), comme les 18 contours invalides de l'import : ST_Intersection
        // levait une GEOS TopologyException et la fiche de la ligne 750000 répondait 500.
        DB::table('commune_contours')->insert(['code_insee' => '42001', 'nom' => 'Commune 42001', 'departement' => '42',
            'geom' => DB::raw("ST_Multi(ST_GeomFromText('POLYGON((1.99 46.4, 2.31 46.6, 2.31 46.4, 1.99 46.6, 1.99 46.4))', 4326))"),
            'created_at' => now(), 'updated_at' => now()]);
        Cache::flush();
        $this->assertFalse((bool) DB::selectOne("SELECT ST_IsValid(geom) AS v FROM commune_contours WHERE code_insee = '42001'")->v);

        $this->assertSame(['42'], array_column(LineFacts::departments($line->id), 'code'));
    }

    public function test_without_track_regions_of_the_item_are_attributed_to_it(): void
    {
        $line = Line::factory()->create();
        $facts = $this->byKey(LineFacts::build($line, $this->encyclopedia([
            'length_official_m' => 10_000, 'wikipedia_title' => 'Élément partiel', 'regions' => json_encode([['name' => 'Bretagne']]),
            'departements' => json_encode(['Finistère']),
        ]), ['length_km' => 40.0], null, false));

        $this->assertSame([['Bretagne'], 'Élément partiel'], [$facts['regions:item']['list'], $facts['regions:item']['item']]);
        $this->assertSame([['code' => null, 'name' => 'Finistère']], $facts['departements:item']['list']);
    }

    public function test_french_departments_and_regions(): void
    {
        $this->assertSame(['Saône-et-Loire', 'Bourgogne-Franche-Comté'], FrenchDepartments::find('71'));
        $this->assertSame(['Ain', 'Auvergne-Rhône-Alpes'], FrenchDepartments::find('1'));
        $this->assertSame('Corse', FrenchDepartments::find('2a')[1]);
        $this->assertSame('Provence-Alpes-Côte d\'Azur', FrenchDepartments::find('13')[1]);
        $this->assertSame('Île-de-France', FrenchDepartments::find('75')[1]);
        $this->assertSame('La Réunion', FrenchDepartments::find('974')[0]);
        $this->assertNull(FrenchDepartments::find('99'));
    }

    public function test_the_line_page_sends_the_facts(): void
    {
        Era::factory()->current()->create(['year' => 2030]);
        $line = Line::factory()->create();
        DB::table('lines')->where('id', $line->id)->update(['length_official_m' => 409_000, 'wikipedia_title' => 'LGV Sud-Est']);

        $this->get('/lines/'.$line->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('line.facts', fn ($facts) => collect($facts)->contains(fn ($f) => $f['key'] === 'length' && (int) $f['km'] === 409))
            ->etc());
    }
}
