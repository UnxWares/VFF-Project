<?php

namespace Tests\Feature\Materials;

use App\Models\Material;
use App\Services\Materials\MaterialImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Écriture idempotente des fiches : création, relance sans changement, respect des valeurs
 * saisies dans VFF, champ que la source ne donne plus, slugs homonymes.
 */
class MaterialImporterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function record(array $overrides = []): array
    {
        $ref = fn (string $p): array => ['source' => 'wikidata', 'ref' => "Q746572#{$p}"];

        return $overrides + [
            'wikidata_id' => 'Q746572',
            'name' => 'BB 15000',
            'type' => 'locomotive_electrique',
            'designations' => ['SNCF BB 15000'],
            'description' => 'série de 65 locomotives électriques de la SNCF',
            'builder' => 'Alstom',
            'builders' => [['name' => 'Alstom', 'wikidata_id' => 'Q309084', 'units' => null]],
            'operators' => [['name' => 'SNCF', 'wikidata_id' => 'Q13646', 'from_year' => null, 'to_year' => null, 'units' => null]],
            'in_service_from' => 1971,
            'in_service_to' => null,
            'units_built' => 65,
            'max_speed_kmh' => 160,
            'power_kw' => null,
            'electrification' => null,
            'gauges' => [['label' => 'écartement standard', 'mm' => 1435, 'wikidata_id' => 'Q1999572']],
            'gauge_mm' => 1435,
            'wheel_arrangement' => "B'B'",
            'fleet_numbers' => '15001 - 15065',
            'image' => null,
            'commons_category' => 'SNCF Class BB 15000',
            'wikipedia_fr' => 'BB 15000',
            'wikipedia_en' => null,
            'provenance' => [
                'name' => $ref('sitelink:frwiki'), 'type' => $ref('P279:Q625151'), 'designations' => $ref('labels'),
                'description' => $ref('description:fr'), 'builder' => $ref('P176'), 'builders' => $ref('P176'),
                'operators' => $ref('P137'), 'in_service_from' => $ref('P729'), 'units_built' => $ref('P1092'),
                'max_speed_kmh' => $ref('P2052'), 'gauges' => $ref('P1064'), 'gauge_mm' => $ref('P1064'),
                'wheel_arrangement' => $ref('P2978'), 'fleet_numbers' => $ref('P2802'), 'commons_category' => $ref('P373'),
                'wikipedia_fr' => $ref('sitelink:frwiki'),
            ],
        ];
    }

    public function test_a_new_series_is_created_with_its_slug_and_provenance(): void
    {
        $stats = (new MaterialImporter)->import([$this->record()]);

        $this->assertSame(['created' => 1, 'updated' => 0, 'unchanged' => 0], $stats);
        $material = Material::firstOrFail();
        $this->assertSame('bb-15000', $material->slug);
        $this->assertSame('wikidata', $material->source);
        $this->assertSame(65, $material->units_built);
        $this->assertSame('Q746572#P729', $material->provenance['in_service_from']['ref']);
        $this->assertNotNull($material->fetched_at);
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $importer = new MaterialImporter;
        $importer->import([$this->record()]);

        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 1], $importer->import([$this->record()]));
    }

    public function test_a_value_entered_in_vff_is_never_overwritten(): void
    {
        $importer = new MaterialImporter;
        $importer->import([$this->record()]);
        $material = Material::firstOrFail();
        // Correction saisie dans VFF : la provenance du champ n'est plus Wikidata.
        $provenance = $material->provenance;
        $provenance['units_built'] = ['source' => 'vff', 'ref' => 'contribution 12'];
        $material->update(['units_built' => 66, 'provenance' => $provenance, 'slug' => 'bb-15000-sncf']);

        $importer->import([$this->record(['units_built' => 65, 'max_speed_kmh' => 165])]);

        $material->refresh();
        $this->assertSame(66, $material->units_built);
        $this->assertSame('vff', $material->provenance['units_built']['source']);
        $this->assertSame(165, $material->max_speed_kmh, 'champ importé : mis à jour');
        $this->assertSame('bb-15000-sncf', $material->slug, 'le slug d\'une fiche existante ne change jamais');
    }

    public function test_an_imported_value_the_source_no_longer_gives_is_cleared(): void
    {
        $importer = new MaterialImporter;
        $importer->import([$this->record()]);

        $record = $this->record(['units_built' => null]);
        unset($record['provenance']['units_built']);
        $this->assertSame(['created' => 0, 'updated' => 1, 'unchanged' => 0], $importer->import([$record]));

        $material = Material::firstOrFail();
        $this->assertNull($material->units_built);
        $this->assertArrayNotHasKey('units_built', $material->provenance);
    }

    public function test_an_empty_field_of_a_manual_sheet_is_filled_but_its_values_are_kept(): void
    {
        Material::factory()->create(['wikidata_id' => 'Q746572', 'slug' => 'bb-15000', 'name' => 'BB 15000 (saisie)', 'builder' => 'MTE', 'units_built' => null, 'provenance' => null]);

        (new MaterialImporter)->import([$this->record()]);

        $material = Material::firstOrFail();
        $this->assertSame('BB 15000 (saisie)', $material->name);
        $this->assertSame('MTE', $material->builder);
        $this->assertSame(65, $material->units_built);
    }

    public function test_homonyms_get_a_slug_suffixed_with_their_wikidata_id(): void
    {
        $importer = new MaterialImporter;
        $importer->import([
            $this->record(['wikidata_id' => 'Q1', 'name' => '150 X']),
            $this->record(['wikidata_id' => 'Q117767232', 'name' => '150 X']),
        ]);

        $this->assertSame(['150-x', '150-x-q117767232'], Material::orderBy('id')->pluck('slug')->all());
    }
}
