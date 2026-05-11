<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EraSeeder::class,
            // ParisBrestSeeder::class,   // à venir : 1 ligne complète seed pour tests
            // OsmImportSeeder::class,    // à venir : import bulk OSM
        ]);
    }
}
