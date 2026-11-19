<?php

namespace Database\Factories;

use App\Enums\MaterialType;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'CC 7100', 'CC 72000', 'BB 67000', 'BB 22200', 'BB 9200',
            'X 2700', 'X 73500', 'Z 2', 'Z 7300',
            'TGV Atlantique', 'TGV Sud-Est', 'TGV Réseau', 'TGV Duplex',
            '141 R', '231 K', '232 U',
        ]).' '.fake()->unique()->numberBetween(1, 999);

        $from = fake()->numberBetween(1900, 2020);

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'type' => fake()->randomElement(MaterialType::cases())->value,
            'builder' => fake()->randomElement(['Alstom', 'ANF', 'Schneider', 'Siemens', 'Bombardier']),
            'in_service_from' => $from,
            'in_service_to' => fake()->boolean(50) ? fake()->numberBetween($from + 10, 2030) : null,
            'description' => fake()->paragraph(1),
        ];
    }

    /** Fiche importée de Wikidata, avec ses caractéristiques, son image Commons et sa provenance par champ. */
    public function fromWikidata(): static
    {
        // Champs dérivés du nom et du constructeur : fermetures évaluées après les valeurs passées à create().
        return $this->state(function (): array {
            $qid = 'Q'.fake()->unique()->numberBetween(100_000, 9_999_999);
            $ref = fn (string $property): array => ['source' => 'wikidata', 'ref' => "{$qid}#{$property}"];
            $file = 'Locomotive '.fake()->numberBetween(1, 9999).'.jpg';

            return [
                'wikidata_id' => $qid,
                'source' => 'wikidata',
                'designations' => fn (array $a): array => ['SNCF '.$a['name']],
                'fleet_numbers' => '15001 - 15065',
                'builders' => fn (array $a): array => [['name' => $a['builder'] ?? 'Alstom', 'wikidata_id' => 'Q309084', 'units' => null]],
                'operators' => [['name' => 'SNCF', 'wikidata_id' => 'Q13646', 'from_year' => null, 'to_year' => null, 'units' => null]],
                'units_built' => 65,
                'max_speed_kmh' => 160,
                'power_kw' => 4620,
                'electrification' => [['label' => '25 kV 50 Hz', 'wikidata_id' => 'Q106530439']],
                'gauge_mm' => 1435,
                'gauges' => [['label' => 'écartement standard', 'mm' => 1435, 'wikidata_id' => 'Q1999572']],
                'wheel_arrangement' => 'BB',
                'image' => [
                    'provider' => 'commons',
                    'file' => 'File:'.$file,
                    'thumb_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/'.rawurlencode($file).'/500px-'.rawurlencode($file),
                    'display_url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/'.rawurlencode($file),
                    'source_url' => 'https://commons.wikimedia.org/wiki/File:'.rawurlencode($file),
                    'width' => 4000, 'height' => 3000,
                    'author' => 'Jean Dupont',
                    'license' => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0',
                    'rights_status' => 'open_license',
                    'commercial_ok' => true,
                    'share_alike' => true,
                    'attribution' => 'Jean Dupont, CC BY-SA 4.0, via Wikimedia Commons',
                ],
                'commons_category' => fn (array $a): string => 'SNCF Class '.$a['name'],
                'wikipedia_fr' => fn (array $a): string => $a['name'],
                'wikipedia_en' => fn (array $a): string => 'SNCF Class '.$a['name'],
                'provenance' => [
                    'name' => $ref('sitelink:frwiki'),
                    'type' => $ref('P279'),
                    'builder' => $ref('P176'),
                    'in_service_from' => $ref('P729'),
                    'units_built' => $ref('P1092'),
                    'max_speed_kmh' => $ref('P2052'),
                    'image' => ['source' => 'commons', 'ref' => 'File:'.$file],
                ],
                'fetched_at' => now(),
            ];
        });
    }
}
