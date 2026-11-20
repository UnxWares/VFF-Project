<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sections nommées (commerciales/historiques) des grandes lignes
 * françaises. Données curées à la main depuis Wikipedia.
 */
class LineSectionsSeeder extends Seeder
{
    public function run(): void
    {
        // LGV PLM (Combs-la-Ville → Saint-Louis-les-Aygalades) = rfn-752000
        $this->seedFor('rfn-752000', [
            [
                'name' => 'LGV Sud-Est',
                'alt_names' => ['LN1', 'LGV Paris-Lyon'],
                'pk_start_m' => 0, 'pk_end_m' => 409000,
                'opening_year_from' => 1981, 'opening_year_to' => 1983,
                'description' => 'Première LGV française, de Combs-la-Ville (Île-de-France) à la bifurcation de Montanay au nord de Lyon. Signalisation TVM-300.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Sud-Est',
            ],
            [
                'name' => 'LGV Rhône-Alpes',
                'alt_names' => ['LN4'],
                'pk_start_m' => 409000, 'pk_end_m' => 498000,
                'opening_year_from' => 1992, 'opening_year_to' => 1994,
                'description' => 'Contournement de l\'agglomération lyonnaise, de Montanay à la bifurcation de Saint-Marcel-lès-Valence. Signalisation TVM-430.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_Rh%C3%B4ne-Alpes',
            ],
            [
                'name' => 'LGV Méditerranée',
                'alt_names' => ['LN5'],
                'pk_start_m' => 498000, 'pk_end_m' => 711000,
                'opening_year_from' => 2001, 'opening_year_to' => 2001,
                'description' => 'De Saint-Marcel-lès-Valence à Saint-Louis-les-Aygalades (Marseille). Signalisation TVM-430.',
                'source_url' => 'https://fr.wikipedia.org/wiki/LGV_M%C3%A9diterran%C3%A9e',
            ],
        ]);

        // LGV Atlantique (rfn-431000) - Paris-Montparnasse → Monts
        $this->seedFor('rfn-431000', [
            [
                'name' => 'LGV Atlantique branche Ouest',
                'alt_names' => ['LN2 Ouest'],
                'pk_start_m' => 0, 'pk_end_m' => 130000,
                'opening_year_from' => 1989, 'opening_year_to' => 1989,
                'description' => 'De Paris-Montparnasse à Courtalain (vers la Bretagne). TVM-300.',
            ],
            [
                'name' => 'LGV Atlantique branche Sud-Ouest',
                'alt_names' => ['LN2 Sud-Ouest'],
                'pk_start_m' => 130000, 'pk_end_m' => 282000,
                'opening_year_from' => 1990, 'opening_year_to' => 1990,
                'description' => 'De Courtalain à Monts (vers Tours et le Sud-Ouest). TVM-300.',
            ],
        ]);

        // LGV Est européenne (rfn-005000)
        $this->seedFor('rfn-005000', [
            [
                'name' => 'LGV Est phase 1',
                'pk_start_m' => 0, 'pk_end_m' => 300000,
                'opening_year_from' => 2007, 'opening_year_to' => 2007,
                'description' => 'De Vaires-Torcy (Île-de-France) à Baudrecourt (Moselle). ETCS Niveau 2 + ERTMS.',
            ],
            [
                'name' => 'LGV Est phase 2',
                'pk_start_m' => 300000, 'pk_end_m' => 406000,
                'opening_year_from' => 2016, 'opening_year_to' => 2016,
                'description' => 'De Baudrecourt à Vendenheim (Bas-Rhin), prolongement jusqu\'à Strasbourg.',
            ],
        ]);
    }

    private function seedFor(string $slug, array $sections): void
    {
        $lineId = DB::table('lines')->where('slug', $slug)->value('id');
        if (! $lineId) {
            $this->command?->warn("Slug {$slug} introuvable, sections ignorées");

            return;
        }
        DB::table('line_sections')->where('line_id', $lineId)->delete();
        foreach ($sections as $s) {
            $s['line_id'] = $lineId;
            $s['alt_names'] = isset($s['alt_names']) ? json_encode($s['alt_names']) : null;
            $s['created_at'] = now();
            $s['updated_at'] = now();
            DB::table('line_sections')->insert($s);
        }
        $this->command?->info("  ✓ {$slug} : ".count($sections).' sections');
    }
}
