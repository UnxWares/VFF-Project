<?php

namespace Database\Seeders;

use App\Models\Era;
use Illuminate\Database\Seeder;

class EraSeeder extends Seeder
{
    public function run(): void
    {
        $eras = [
            [
                'year' => 1990,
                'label' => "L'apogée du maillage rural",
                'description' => "Le réseau secondaire est encore largement vivant. De nombreuses lignes métriques et étroites résistent.",
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2000,
                'label' => "L'âge d'or du TGV",
                'description' => "Les LGV s'étendent. Le réseau régional connaît ses premières fermetures massives. Voies vertes en projet.",
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2010,
                'label' => 'Régionalisation et fragilité',
                'description' => "Les TER prennent le relais sur les lignes secondaires. Beaucoup ferment faute d'entretien. RER étendus.",
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2020,
                'label' => 'Le retour du fret et des trains de nuit',
                'description' => 'Réouvertures emblématiques. Plan ferroviaire national. Trains de nuit relancés. Renouveau des petites lignes.',
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2030,
                'label' => "L'horizon en construction",
                'description' => "Snapshot vivant — toutes les contributions actuelles s'y ajoutent jusqu'au gel de 2030. Votre présent fait l'archive de demain.",
                'is_current' => true,
                'editable_until' => '2029-12-31',
            ],
        ];

        foreach ($eras as $era) {
            Era::updateOrCreate(['year' => $era['year']], $era);
        }
    }
}
