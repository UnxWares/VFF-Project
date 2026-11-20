<?php

namespace Tests\Feature\Services;

use App\Services\SecondaryPlaceGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Géocodage hors ligne des toponymes des réseaux secondaires
 * (SecondaryPlaceGeocoder::geocode) sur de vraies communes : nom exact,
 * préfixe (« Carhaix » → Carhaix-Plouguer), graphie (« Plouézoch » →
 * Plouezoc'h), article (« Grand-Lucé » → Le Grand-Lucé), cible de lien wiki,
 * homonymes départagés par le département ou le barycentre de l'article, et
 * refus (ambigu, trop vague, trop loin du contexte).
 */
class SecondaryPlaceGeocoderTest extends TestCase
{
    use RefreshDatabase;

    private SecondaryPlaceGeocoder $geo;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['01073', 'Cerdon', '01', 5.4706, 46.0868],
            ['45061', 'Cerdon', '45', 2.3619, 47.6353],
            ['29024', 'Carhaix-Plouguer', '29', -3.5728, 48.2758],
            ['29151', 'Morlaix', '29', -3.8276, 48.5776],
            ['29198', "Plouezoc'h", '29', -3.8217, 48.6378],
            ['72143', 'Le Grand-Lucé', '72', 0.4686, 47.8619],
        ] as $i => [$insee, $nom, $dep, $lon, $lat]) {
            DB::statement("INSERT INTO communes (code_insee, type, nom, nom_norm, departement, population, geom, created_at, updated_at)
                VALUES (?, 'commune-actuelle', ?, lower(?), ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), now(), now())",
                [$insee, $nom, $nom, $dep, 1000 + $i, $lon, $lat]);
        }
        DB::statement("INSERT INTO stations (slug, name, type, status, department_code, geom, created_at, updated_at)
            VALUES ('la-brohiniere', 'La Brohinière', 'gare', 'closed', '35', ST_SetSRID(ST_MakePoint(-2.0389, 48.2160), 4326), now(), now())");
        $this->geo = new SecondaryPlaceGeocoder;
    }

    public function test_unique_commune_by_exact_name(): void
    {
        $g = $this->geo->geocode('Morlaix', null, null);

        $this->assertSame(['lon' => -3.8276, 'lat' => 48.5776, 'dep' => '29', 'name' => 'Morlaix', 'kind' => 'commune', 'rank' => 2], $g);
    }

    public function test_abbreviated_name_matches_the_commune_starting_with_it(): void
    {
        $g = $this->geo->geocode('Carhaix', null, null);

        $this->assertSame('Carhaix-Plouguer', $g['name']);
        $this->assertSame('commune (préfixe)', $g['kind']);
    }

    public function test_spelling_and_article_variants(): void
    {
        $this->assertSame("Plouezoc'h", $this->geo->geocode('Plouézoch', null, null)['name']);
        $this->assertSame('commune (graphie)', $this->geo->geocode('Plouézoch', null, null)['kind']);

        $g = $this->geo->geocode('Grand-Lucé', null, null);
        $this->assertSame('Le Grand-Lucé', $g['name']);
        $this->assertSame('commune (article)', $g['kind']);
    }

    public function test_station_suffixes_are_removed_from_the_label(): void
    {
        $g = $this->geo->geocode('Morlaix-Ville', null, null);
        $this->assertSame('Morlaix', $g['name']);

        $this->assertSame(['Morlaix PO', 'Morlaix'], $this->geo->variants('Morlaix PO (ancienne gare)'));
        $this->assertSame(['Gare de Morlaix', 'Morlaix'], $this->geo->variants('Gare de Morlaix'));
    }

    public function test_homonyms_without_context_are_ambiguous(): void
    {
        $this->assertNull($this->geo->geocode('Cerdon', null, null));
    }

    public function test_homonyms_are_resolved_by_the_article_departement(): void
    {
        $this->assertSame('01', $this->geo->geocode('Cerdon', null, null, ['01'])['dep']);
        $this->assertSame('45', $this->geo->geocode('Cerdon', null, null, ['45', '72'])['dep']);
    }

    public function test_homonyms_are_resolved_by_the_article_barycentre(): void
    {
        // Barycentre près d'Orléans : Cerdon (Loiret) à ~40 km, Cerdon (Ain) à ~290 km.
        $this->assertSame('45', $this->geo->geocode('Cerdon', null, ['lon' => 2.0, 'lat' => 47.9])['dep']);
        // À mi-chemin des deux : aucun choix arbitraire.
        $this->assertNull($this->geo->geocode('Cerdon', null, ['lon' => 3.9, 'lat' => 46.85]));
    }

    public function test_wiki_link_target_wins_over_the_label(): void
    {
        $g = $this->geo->geocode('Pont-de-Préau', 'Cerdon (Ain)', null);
        $this->assertSame('01', $g['dep']);
        $this->assertSame('commune (lien)', $g['kind']);

        $station = $this->geo->geocode('La Brohinière', 'Gare de La Brohinière', null);
        $this->assertSame('La Brohinière', $station['name']);
        $this->assertSame('gare (lien)', $station['kind']);
    }

    public function test_unique_place_far_from_the_article_context_is_refused(): void
    {
        // Morlaix (Finistère) pour un article centré sur Lyon : > 150 km.
        $this->assertNull($this->geo->geocode('Morlaix', null, ['lon' => 4.83, 'lat' => 45.76]));
        $this->assertSame('Morlaix', $this->geo->geocode('Morlaix', null, ['lon' => -3.6, 'lat' => 48.4])['name']);
    }

    public function test_vague_or_too_short_labels_are_not_geocoded(): void
    {
        foreach (['La gare', 'Port', 'la Mer', 'X', ' '] as $label) {
            $this->assertNull($this->geo->geocode($label, null, null), $label);
        }
        $this->assertNull($this->geo->geocode('Zorglub', null, null));
    }

    public function test_departement_centroid_is_the_mean_of_current_communes(): void
    {
        $c = $this->geo->departementCentroid('29');

        $this->assertEqualsWithDelta((-3.5728 - 3.8276 - 3.8217) / 3, $c[0], 1e-9);
        $this->assertEqualsWithDelta((48.2758 + 48.5776 + 48.6378) / 3, $c[1], 1e-9);
        $this->assertNull($this->geo->departementCentroid('2A'));
    }
}
