<?php

namespace Database\Seeders;

use App\Models\Line;
use App\Models\Material;
use Illuminate\Database\Seeder;

/**
 * Associe matériels roulants ↔ lignes connues, pour enrichir les fiches.
 * Curation manuelle, non exhaustive. Les contributions communautaires
 * (Phase 5) compléteront le tableau.
 */
class LineMaterialsSeeder extends Seeder
{
    public function run(): void
    {
        // Mapping : code RFN → slugs matériels présents
        $associations = [
            '420000' => ['141-r', 'bb-67000', 'bb-22200', 'tgv-atlantique', 'agc-b81500', 'regiolis-z-51500', 'voiture-corail'],
            '752000' => ['tgv-sud-est', 'tgv-atlantique', 'tgv-reseau', 'tgv-duplex', 'tgv-pos', 'euroduplex'],
            '750000' => ['tgv-sud-est', 'bb-22200', 'bb-7200', 'voiture-corail'],
            '570000' => ['bb-7200', 'bb-22200', 'voiture-corail', 'agc-b81500', 'regio-2n'],
            '590000' => ['bb-7200', 'voiture-corail', 'agc-b81500'],
            '830000' => ['bb-22200', 'bb-7200', 'voiture-corail', 'regio-2n', 'tgv-atlantique'],
            '272000' => ['tgv-reseau', 'tgv-duplex', 'eurostar-e320', 'thalys-pbka', 'bb-22200'],
            '70000' => ['tgv-pos', 'euroduplex', 'bb-26000-sybic', 'voiture-corail'],
            '366000' => ['bb-22200', 'agc-b81500', 'regio-2n', 'voiture-corail'],
            '930000' => ['bb-22200', 'bb-26000-sybic', 'tgv-duplex'],
            '1000' => ['bb-26000-sybic', 'tgv-pos', 'voiture-corail'],
            '640000' => ['bb-7200', 'bb-22200', 'voiture-corail', 'regio-2n'],
            '905000' => ['bb-22200', 'voiture-corail', 'regio-2n'],
            '340000' => ['bb-22200', 'regio-2n', 'agc-b81500'],
            '461000' => ['tgv-atlantique', 'euroduplex'],
            '784000' => ['regio-2n', 'agc-b81500', 'x-73500-ater'],
            '785000' => ['bb-67000', 'agc-b81500', 'x-73500-ater'],
            '720000' => ['agc-b81500', 'x-73500-ater', 'bb-67000'],
            '696000' => ['agc-b81500', 'x-73500-ater'],
            '883000' => ['regio-2n', 'agc-b81500'],
        ];

        $created = 0;
        foreach ($associations as $lineCode => $materialSlugs) {
            $line = Line::query()
                ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [preg_replace('/\s+/', '', $lineCode)])
                ->first();

            if (! $line) {
                continue;
            }

            foreach ($materialSlugs as $slug) {
                $material = Material::where('slug', $slug)->first();
                if (! $material) {
                    continue;
                }
                $line->materials()->syncWithoutDetaching([$material->id => []]);
                $created++;
            }
        }

        // optional() : seeder aussi lancé hors commande Artisan ((new …)->run()), sans console.
        optional($this->command)->info("✓ {$created} associations line ↔ matériel créées.");
    }
}
