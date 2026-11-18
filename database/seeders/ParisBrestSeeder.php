<?php

namespace Database\Seeders;

use App\Enums\LineEventType;
use App\Enums\LineType;
use App\Enums\MaterialType;
use App\Enums\SegmentStatus;
use App\Enums\StationType;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;

/**
 * Seed de la ligne emblématique Paris-Brest (ligne 420 000, ~615 km).
 *
 * Objectif : démontrer le pipeline PostGIS complet de bout en bout avec des
 * données historiques réalistes. Sert de référence pour les tests, la carte
 * MVP (Phase 2) et l'éventuel import OSM.
 *
 * Données simplifiées (5 points de tracé) - l'import OSM réel viendra raffiner
 * la géométrie avec des centaines de points par segment.
 */
class ParisBrestSeeder extends Seeder
{
    public function run(): void
    {
        // ── La ligne ─────────────────────────────────────────────────────────
        $line = Line::updateOrCreate(
            ['slug' => 'paris-brest'],
            [
                'name' => 'Paris-Brest',
                'alt_names' => ['Ligne de Paris-Montparnasse à Brest'],
                'code' => '420 000',
                'type' => LineType::Mixed->value,
                'historical_operator' => "Compagnie de l'Ouest (1851-1909), État (1909-1937), SNCF (1938-)",
                'description' => 'Ligne historique reliant Paris-Montparnasse à Brest, ~615 km, traversant le Maine, la Bretagne et le Finistère. Inaugurée par tronçons entre 1840 et 1865, électrifiée Paris-Le Mans en 1937, mise en service du TGV Atlantique en 1989.',
            ],
        );

        // ── Tracé géoréférencé (5 points clés) ────────────────────────────────
        // Paris-Montparnasse → Le Mans → Rennes → Saint-Brieuc → Brest
        $coords = [
            Point::makeGeodetic(48.8412, 2.3203),  // Paris-Montparnasse
            Point::makeGeodetic(47.9956, 0.1934),  // Le Mans
            Point::makeGeodetic(48.1034, -1.6722), // Rennes
            Point::makeGeodetic(48.5066, -2.7558), // Saint-Brieuc
            Point::makeGeodetic(48.3905, -4.4860), // Brest
        ];
        $geom = LineString::make($coords);

        // ── Un segment par époque (statut en service pour chaque) ─────────────
        $eras = Era::orderBy('year')->get();
        foreach ($eras as $era) {
            $line->segments()->updateOrCreate(
                ['era_id' => $era->id],
                [
                    'geom' => $geom,
                    'status' => SegmentStatus::InService->value,
                    'max_speed_kmh' => match (true) {
                        $era->year >= 2000 => 320, // TGV Atlantique
                        $era->year >= 1990 => 200,
                        default => 140,
                    },
                    'electrified' => $era->year >= 1990,
                    'voltage_v' => $era->year >= 1990 ? 25000 : null,
                    'gauge_mm' => 1435,
                ],
            );
        }

        // ── Gares principales ─────────────────────────────────────────────────
        $stations = [
            ['slug' => 'paris-montparnasse', 'name' => 'Paris-Montparnasse', 'city' => 'Paris', 'department_code' => '75', 'lat' => 48.8412, 'lng' => 2.3203, 'opened_at' => '1840-09-10'],
            ['slug' => 'le-mans', 'name' => 'Le Mans', 'city' => 'Le Mans', 'department_code' => '72', 'lat' => 47.9956, 'lng' => 0.1934, 'opened_at' => '1854-04-30'],
            ['slug' => 'rennes', 'name' => 'Rennes', 'city' => 'Rennes', 'department_code' => '35', 'lat' => 48.1034, 'lng' => -1.6722, 'opened_at' => '1857-04-26'],
            ['slug' => 'saint-brieuc', 'name' => 'Saint-Brieuc', 'city' => 'Saint-Brieuc', 'department_code' => '22', 'lat' => 48.5066, 'lng' => -2.7558, 'opened_at' => '1863-07-31'],
            ['slug' => 'brest', 'name' => 'Brest', 'city' => 'Brest', 'department_code' => '29', 'lat' => 48.3905, 'lng' => -4.4860, 'opened_at' => '1865-04-26'],
        ];
        foreach ($stations as $s) {
            Station::updateOrCreate(
                ['slug' => $s['slug']],
                [
                    'name' => $s['name'],
                    'type' => StationType::Gare->value,
                    'geom' => Point::makeGeodetic($s['lat'], $s['lng']),
                    'city' => $s['city'],
                    'department_code' => $s['department_code'],
                    'opened_at' => $s['opened_at'],
                ],
            );
        }

        // ── Matériels historiques associés ────────────────────────────────────
        $materials = [
            ['slug' => '141-r', 'name' => '141 R', 'type' => MaterialType::LocomotiveVapeur, 'builder' => 'Baldwin / ALCO / Lima', 'from' => 1945, 'to' => 1974],
            ['slug' => 'bb-67000', 'name' => 'BB 67000', 'type' => MaterialType::LocomotiveDiesel, 'builder' => 'Brissonneau et Lotz', 'from' => 1963, 'to' => null],
            ['slug' => 'bb-22200', 'name' => 'BB 22200', 'type' => MaterialType::LocomotiveElectrique, 'builder' => 'Alstom', 'from' => 1976, 'to' => null],
            ['slug' => 'tgv-atlantique', 'name' => 'TGV Atlantique', 'type' => MaterialType::RameTgv, 'builder' => 'Alstom / GEC Alsthom', 'from' => 1989, 'to' => null],
            ['slug' => 'tgv-reseau', 'name' => 'TGV Réseau', 'type' => MaterialType::RameTgv, 'builder' => 'GEC Alsthom', 'from' => 1993, 'to' => null],
        ];
        foreach ($materials as $m) {
            $material = Material::updateOrCreate(
                ['slug' => $m['slug']],
                [
                    'name' => $m['name'],
                    'type' => $m['type']->value,
                    'builder' => $m['builder'],
                    'in_service_from' => $m['from'],
                    'in_service_to' => $m['to'],
                ],
            );

            // Associer le matériel à la ligne (pivot line_materials, sans époque
            // spécifique pour faire simple - l'enrichissement par époque viendra)
            $line->materials()->syncWithoutDetaching([$material->id => []]);
        }

        // ── Événements historiques ────────────────────────────────────────────
        $events = [
            [LineEventType::Opening, '1840-09-10', 'Inauguration Paris-Montparnasse-Versailles-Chantiers (premier tronçon de la future ligne)'],
            [LineEventType::Opening, '1854-04-30', 'Mise en service Versailles-Le Mans, exploitée par la Compagnie de l\'Ouest'],
            [LineEventType::Opening, '1857-04-26', 'Mise en service Le Mans-Rennes'],
            [LineEventType::Opening, '1865-04-26', 'Inauguration officielle Saint-Brieuc-Brest, ligne complète Paris-Brest'],
            [LineEventType::Electrification, '1937-04-22', 'Électrification Paris-Le Mans (1500 V continu, repassé en 25 kV plus tard)'],
            [LineEventType::Modernization, '1989-09-24', 'Mise en service de la LGV Atlantique entre Paris et Le Mans → TGV Atlantique Paris-Brest en 4 h'],
            [LineEventType::Modernization, '2017-07-02', 'Mise en service BPL (Bretagne-Pays de la Loire) : gain de 37 minutes Paris-Brest'],
        ];
        foreach ($events as [$type, $date, $description]) {
            LineEvent::updateOrCreate(
                ['line_id' => $line->id, 'event_date' => $date, 'type' => $type->value],
                [
                    'description' => $description,
                    'source' => 'Wikipédia / SNCF Réseau',
                ],
            );
        }

        // optional() : seeder aussi lancé hors commande Artisan ((new …)->run()), sans console.
        optional($this->command)->info("✓ Paris-Brest seedée : {$line->segments()->count()} segments, "
            ."{$line->events()->count()} événements, "
            .Station::count().' gares (totalisées sur toutes les lignes), '
            .$line->materials()->count().' matériels associés.');
    }
}
