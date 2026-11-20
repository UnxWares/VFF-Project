<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EraSeeder::class,
            HistoricalErasSeeder::class,
            // ParisBrestSeeder retiré : tracé de démonstration à 5 points (lignes
            // droites entre villes), remplacé par les vrais tracés importés.
            // AdvocacySeeder se lance APRÈS les imports (il lui faut les lignes).
        ]);
    }
}
