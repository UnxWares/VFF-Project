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
                'label' => 'Les débuts du TGV',
                'description' => "La LGV Sud-Est (1981-1983) puis la LGV Atlantique (1989-1990) ouvrent l'ère de la grande vitesse, pendant que les fermetures de petites lignes continuent.",
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2000,
                'label' => "L'extension de la grande vitesse",
                'description' => 'LGV Nord, Rhône-Alpes et Interconnexion, tunnel sous la Manche (1994). Réseau ferré de France est créé en 1997 et les régions commencent à organiser leurs TER.',
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2010,
                'label' => 'Les régions et le TER',
                'description' => "Les régions deviennent autorités organisatrices des TER (2002). LGV Méditerranée (2001) et LGV Est (2007), premiers tram-trains, mais des petites lignes se dégradent faute d'entretien.",
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2020,
                'label' => 'Petites lignes en sursis',
                'description' => "LGV Rhin-Rhône (2011), Bretagne - Pays de la Loire et Sud Europe Atlantique (2017). Plusieurs petites lignes sont suspendues pour cause de voie dégradée, d'autres sont sauvées par les régions.",
                'is_current' => false,
                'editable_until' => null,
            ],
            [
                'year' => 2030,
                'label' => 'Le réseau aujourd\'hui',
                'description' => "État actuel du réseau, tenu à jour jusqu'à fin 2030 : réouvertures, retour des trains de nuit, régénération ou fermeture de petites lignes. Vos contributions d'aujourd'hui font l'archive de demain.",
                'is_current' => true,
                // Carte de l'état ACTUEL, tenue à jour jusqu'à fin 2030 ; ensuite : ère 2040.
                'editable_until' => '2030-12-31',
            ],
        ];

        foreach ($eras as $era) {
            Era::updateOrCreate(['year' => $era['year']], $era);
        }
    }
}
