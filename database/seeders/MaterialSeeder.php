<?php

namespace Database\Seeders;

use App\Enums\MaterialType;
use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            // ===== Locomotives vapeur =====
            [
                'slug' => '141-r',
                'name' => '141 R',
                'type' => MaterialType::LocomotiveVapeur,
                'builder' => 'Baldwin, ALCO, Lima, Canadian Locomotive',
                'in_service_from' => 1945,
                'in_service_to' => 1974,
                'description' => 'Locomotive Mikado importée des États-Unis et du Canada après-guerre pour reconstruire la traction vapeur en France.',
            ],
            [
                'slug' => '230-g',
                'name' => '230 G',
                'type' => MaterialType::LocomotiveVapeur,
                'builder' => 'SACM, Schneider',
                'in_service_from' => 1914,
                'in_service_to' => 1968,
                'description' => 'Locomotive Ten-Wheel emblématique du PLM, longuement utilisée sur les trains de voyageurs régionaux.',
            ],
            [
                'slug' => '241-p',
                'name' => 'Mountain 241 P',
                'type' => MaterialType::LocomotiveVapeur,
                'builder' => 'Schneider Le Creusot',
                'in_service_from' => 1948,
                'in_service_to' => 1969,
                'description' => 'Dernière grande série de locomotives Mountain de la SNCF, reine des rapides Paris-Marseille et Paris-Mulhouse.',
            ],
            [
                'slug' => '231-k',
                'name' => 'Pacific 231 K',
                'type' => MaterialType::LocomotiveVapeur,
                'builder' => 'SACM, Fives-Lille',
                'in_service_from' => 1917,
                'in_service_to' => 1969,
                'description' => 'Pacific Nord modernisée, symbole de la traction vapeur sur les grandes lignes du Nord et de l\'Est.',
            ],

            // ===== Locomotives diesel =====
            [
                'slug' => 'bb-63000',
                'name' => 'BB 63000',
                'type' => MaterialType::LocomotiveDiesel,
                'builder' => 'Brissonneau et Lotz',
                'in_service_from' => 1953,
                'in_service_to' => 2015,
                'description' => 'Locomotive de manœuvre et de ligne légère, omniprésente dans les triages et embranchements particuliers.',
            ],
            [
                'slug' => 'bb-67000',
                'name' => 'BB 67000',
                'type' => MaterialType::LocomotiveDiesel,
                'builder' => 'Brissonneau et Lotz, MTE',
                'in_service_from' => 1963,
                'in_service_to' => 2010,
                'description' => 'Première série des BB 67000, traction de tous types sur lignes non électrifiées.',
            ],
            [
                'slug' => 'bb-60000',
                'name' => 'BB 60000',
                'type' => MaterialType::LocomotiveDiesel,
                'builder' => 'Alstom (reconstruction)',
                'in_service_from' => 2006,
                'in_service_to' => null,
                'description' => 'BB 63500 reconstruites pour le fret et linfrastructure, motorisation moderne et cabines rénovées.',
            ],
            [
                'slug' => 'cc-65000',
                'name' => 'CC 65000',
                'type' => MaterialType::LocomotiveDiesel,
                'builder' => 'SACM, Fives-Lille',
                'in_service_from' => 1956,
                'in_service_to' => 1988,
                'description' => 'Locomotive diesel de ligne à six essieux, surnommée « Sous-Marin » pour sa silhouette massive.',
            ],

            // ===== Locomotives électriques =====
            [
                'slug' => 'cc-7100',
                'name' => 'CC 7100',
                'type' => MaterialType::LocomotiveElectrique,
                'builder' => 'Alstom, MTE',
                'in_service_from' => 1952,
                'in_service_to' => 2001,
                'description' => 'Détentrice du record du monde de vitesse à 331 km/h en 1955 (CC 7107). Locomotive de prestige du sud-est.',
            ],
            [
                'slug' => 'cc-6500',
                'name' => 'CC 6500',
                'type' => MaterialType::LocomotiveElectrique,
                'builder' => 'Alsthom',
                'in_service_from' => 1969,
                'in_service_to' => 2007,
                'description' => 'Locomotive « nez cassé » dessinée par Paul Arzens, reine des grandes lignes du sud-est et des Pyrénées.',
            ],
            [
                'slug' => 'bb-7200',
                'name' => 'BB 7200',
                'type' => MaterialType::LocomotiveElectrique,
                'builder' => 'Alsthom, MTE',
                'in_service_from' => 1976,
                'in_service_to' => null,
                'description' => 'Locomotive 1,5 kV continu polyvalente, déclinée des BB 22200 sans bi-courant.',
            ],
            [
                'slug' => 'bb-22200',
                'name' => 'BB 22200',
                'type' => MaterialType::LocomotiveElectrique,
                'builder' => 'Alsthom, MTE',
                'in_service_from' => 1976,
                'in_service_to' => null,
                'description' => 'Locomotive bi-courant 1,5 kV / 25 kV, omniprésente sur lensemble du réseau pour voyageurs et fret.',
            ],
            [
                'slug' => 'bb-25500',
                'name' => 'BB 25500',
                'type' => MaterialType::LocomotiveElectrique,
                'builder' => 'MTE, Jeumont-Schneider',
                'in_service_from' => 1964,
                'in_service_to' => 2017,
                'description' => 'Locomotive bi-courant de la famille « Danseuses », utilisée sur les TER et trains de marchandises.',
            ],
            [
                'slug' => 'bb-26000-sybic',
                'name' => 'BB 26000 « Sybic »',
                'type' => MaterialType::LocomotiveElectrique,
                'builder' => 'Alsthom',
                'in_service_from' => 1988,
                'in_service_to' => null,
                'description' => 'Locomotive bi-courant synchrone, à la pointe de la traction des Corail Intercités et du fret lourd.',
            ],

            // ===== Automotrices / Autorails =====
            [
                'slug' => 'agc-b81500',
                'name' => 'AGC (B 81500)',
                'type' => MaterialType::Automotrice,
                'builder' => 'Bombardier',
                'in_service_from' => 2004,
                'in_service_to' => null,
                'description' => 'Autorail Grande Capacité bimode bi-courant, colonne vertébrale du TER moderne.',
            ],
            [
                'slug' => 'z-21500-ter-2n',
                'name' => 'Z 21500 « TER 2N »',
                'type' => MaterialType::Automotrice,
                'builder' => 'Alstom, Bombardier',
                'in_service_from' => 1997,
                'in_service_to' => null,
                'description' => 'Automotrice à deux niveaux pour TER, dérivée du Z 23500 francilien.',
            ],
            [
                'slug' => 'z-7300',
                'name' => 'Z 7300 / Z 7500',
                'type' => MaterialType::Automotrice,
                'builder' => 'Alsthom, Francorail',
                'in_service_from' => 1980,
                'in_service_to' => 2018,
                'description' => 'Automotrices « Z2 » mono-courant 1,5 kV, longtemps reines des TER du sud de la France.',
            ],
            [
                'slug' => 'x-73500-ater',
                'name' => 'X 73500 « ATER »',
                'type' => MaterialType::Autorail,
                'builder' => 'De Dietrich Ferroviaire',
                'in_service_from' => 1999,
                'in_service_to' => null,
                'description' => 'Autorail Transport Express Régional, monocaisse diesel utilisé sur les petites lignes.',
            ],
            [
                'slug' => 'regiolis-z-51500',
                'name' => 'Régiolis (Z 51500)',
                'type' => MaterialType::Automotrice,
                'builder' => 'Alstom',
                'in_service_from' => 2014,
                'in_service_to' => null,
                'description' => 'Famille Coradia Polyvalent d\'Alstom, déclinée en versions électriques, bimodes et hybrides.',
            ],
            [
                'slug' => 'regio-2n',
                'name' => 'Régio 2N (Z 55500)',
                'type' => MaterialType::Automotrice,
                'builder' => 'Bombardier',
                'in_service_from' => 2014,
                'in_service_to' => null,
                'description' => 'Automotrice à deux niveaux à plancher bas, déclinée en plusieurs longueurs pour TER et Transilien.',
            ],

            // ===== Rames TGV =====
            [
                'slug' => 'tgv-sud-est',
                'name' => 'TGV Sud-Est',
                'type' => MaterialType::RameTgv,
                'builder' => 'Alsthom, Francorail-MTE',
                'in_service_from' => 1981,
                'in_service_to' => 2019,
                'description' => 'Première génération de TGV commerciale, ouverture de la LGV Paris-Lyon le 27 septembre 1981.',
            ],
            [
                'slug' => 'tgv-atlantique',
                'name' => 'TGV Atlantique',
                'type' => MaterialType::RameTgv,
                'builder' => 'GEC-Alsthom',
                'in_service_from' => 1989,
                'in_service_to' => null,
                'description' => 'TGV à dix remorques, détenteur du record du monde de vitesse à 515,3 km/h en 1990.',
            ],
            [
                'slug' => 'tgv-reseau',
                'name' => 'TGV Réseau',
                'type' => MaterialType::RameTgv,
                'builder' => 'GEC-Alsthom',
                'in_service_from' => 1992,
                'in_service_to' => null,
                'description' => 'TGV bi-courant et tri-courant pour le réseau LGV interconnecté, dérivé de l\'Atlantique.',
            ],
            [
                'slug' => 'tgv-duplex',
                'name' => 'TGV Duplex',
                'type' => MaterialType::RameTgv,
                'builder' => 'GEC-Alsthom / Alstom',
                'in_service_from' => 1996,
                'in_service_to' => null,
                'description' => 'TGV à deux niveaux pour augmenter la capacité sur les LGV saturées, notamment Paris-Lyon-Méditerranée.',
            ],
            [
                'slug' => 'tgv-pos',
                'name' => 'TGV POS',
                'type' => MaterialType::RameTgv,
                'builder' => 'Alstom',
                'in_service_from' => 2006,
                'in_service_to' => null,
                'description' => 'TGV Paris-Ostfrankreich-Süddeutschland conçu pour la LGV Est et l\'Allemagne.',
            ],
            [
                'slug' => 'euroduplex',
                'name' => 'Euroduplex',
                'type' => MaterialType::RameTgv,
                'builder' => 'Alstom',
                'in_service_from' => 2011,
                'in_service_to' => null,
                'description' => 'Troisième génération de TGV Duplex, interopérable Suisse, Allemagne, Luxembourg, Espagne.',
            ],
            [
                'slug' => 'tgv-m-avelia-horizon',
                'name' => 'TGV M / Avelia Horizon',
                'type' => MaterialType::RameTgv,
                'builder' => 'Alstom',
                'in_service_from' => 2025,
                'in_service_to' => null,
                'description' => 'Nouvelle génération de TGV modulaire, plus capacitaire et économe, mise en service progressive à partir de 2025.',
            ],
            [
                'slug' => 'eurostar-e320',
                'name' => 'Eurostar e320',
                'type' => MaterialType::RameTgv,
                'builder' => 'Siemens',
                'in_service_from' => 2015,
                'in_service_to' => null,
                'description' => 'Rame Siemens Velaro e320 pour Eurostar, 320 km/h sous la Manche et au-delà.',
            ],
            [
                'slug' => 'thalys-pbka',
                'name' => 'Thalys PBKA',
                'type' => MaterialType::RameTgv,
                'builder' => 'GEC-Alsthom, Bombardier',
                'in_service_from' => 1996,
                'in_service_to' => null,
                'description' => 'Rame quadri-courant Paris-Bruxelles-Köln-Amsterdam, désormais exploitée sous la marque Eurostar.',
            ],

            // ===== Voitures / Intercités =====
            [
                'slug' => 'voiture-corail',
                'name' => 'Voiture Corail',
                'type' => MaterialType::Voiture,
                'builder' => 'ANF, Carel Fouché',
                'in_service_from' => 1975,
                'in_service_to' => null,
                'description' => 'Voiture grande ligne climatisée standardisée, épine dorsale des Intercités et anciens trains rapides.',
            ],
            [
                'slug' => 'vr2n',
                'name' => 'VR2N',
                'type' => MaterialType::Voiture,
                'builder' => 'ANF, Bombardier',
                'in_service_from' => 1994,
                'in_service_to' => null,
                'description' => 'Voiture à deux niveaux pour grandes lignes et Transilien, utilisée notamment sur Paris-Lille et Paris-Le Havre.',
            ],

            // ===== Fret =====
            [
                'slug' => 'bb-37000',
                'name' => 'BB 37000 (Prima)',
                'type' => MaterialType::LocomotiveElectrique,
                'builder' => 'Alstom',
                'in_service_from' => 2001,
                'in_service_to' => null,
                'description' => 'Locomotive de fret tri-tension de la famille Prima, exploitée par Akiem et nombreux opérateurs privés.',
            ],
            [
                'slug' => 'class-66',
                'name' => 'Class 66',
                'type' => MaterialType::LocomotiveDiesel,
                'builder' => 'Electro-Motive Diesel (EMD)',
                'in_service_from' => 2000,
                'in_service_to' => null,
                'description' => 'Locomotive diesel de fret omniprésente chez les opérateurs privés (Captrain, ECR, Europorte) en France.',
            ],
        ];

        foreach ($materials as $material) {
            Material::updateOrCreate(
                ['slug' => $material['slug']],
                $material
            );
        }
    }
}
