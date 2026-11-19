<?php

namespace Tests\Support;

use App\Enums\LineType;
use App\Enums\SegmentStatus;
use App\Enums\StationType;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\LineSegment;
use App\Models\Material;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;

/**
 * Mini-réseau déterministe pour les tests Feature (base vff_test) :
 *
 *   - 2 ères : 1960 (passée) et 2030 (courante)
 *   - 1 ligne « Paris-Saint-Lazare - Versailles-Rive-Droite » (code 999 000)
 *     avec un tronçon par ère (même tracé), un événement, un matériel
 *   - 2 gares aux extrémités, reliées par line_stops / line_signalling (2030)
 *
 * Coordonnées réelles autour de Paris → la tuile MVT 8/129/88 couvre le tracé.
 */
trait BuildsRailNetwork
{
    protected Era $pastEra;

    protected Era $currentEra;

    protected Line $line;

    protected Station $origin;

    protected Station $destination;

    protected Material $material;

    protected function buildRailNetwork(): void
    {
        $this->pastEra = Era::factory()->create(['year' => 1960, 'label' => 'Années 1960']);
        $this->currentEra = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);

        $this->line = Line::factory()->create([
            'slug' => 'paris-saint-lazare-versailles-rd',
            'name' => 'Paris-Saint-Lazare – Versailles-Rive-Droite',
            'code' => '999 000',
            'type' => LineType::Passengers->value,
            'historical_operator' => 'Compagnie de l\'Ouest',
        ]);

        $geom = LineString::make([
            Point::makeGeodetic(48.8763, 2.3253),  // Paris-Saint-Lazare
            Point::makeGeodetic(48.8530, 2.2400),
            Point::makeGeodetic(48.8091, 2.1347),  // Versailles-Rive-Droite
        ]);

        foreach ([$this->pastEra, $this->currentEra] as $era) {
            LineSegment::factory()->create([
                'line_id' => $this->line->id,
                'era_id' => $era->id,
                'geom' => $geom,
                'status' => SegmentStatus::InService->value,
                'traffic_type' => 'passenger',
                'electrified' => true,
                'voltage_v' => 1500,
                'max_speed_kmh' => 120,
                'is_heritage' => false,
                // Source à licence ouverte : la géométrie sort des exports (règle des droits des tracés).
                'source' => 'osm',
            ]);
        }

        $this->origin = Station::factory()->create([
            'slug' => 'paris-saint-lazare',
            'name' => 'Paris-Saint-Lazare',
            'type' => StationType::Gare->value,
            'geom' => Point::makeGeodetic(48.8763, 2.3253),
            'city' => 'Paris',
            'opened_at' => '1837-08-26',
            'closed_at' => null,
            'status' => 'active',
        ]);
        $this->destination = Station::factory()->create([
            'slug' => 'versailles-rive-droite',
            'name' => 'Versailles-Rive-Droite',
            'type' => StationType::Gare->value,
            'geom' => Point::makeGeodetic(48.8091, 2.1347),
            'city' => 'Versailles',
            'opened_at' => '1839-08-02',
            'closed_at' => null,
            'status' => 'active',
        ]);

        LineEvent::factory()->create([
            'line_id' => $this->line->id,
            'type' => 'opening',
            'event_date' => '1839-08-02',
            'description' => 'Ouverture de la ligne.',
        ]);

        // Type fixé : tiré au hasard, il tombait parfois sur « locomotive électrique » et faussait les filtres par type.
        $this->material = Material::factory()->create(['slug' => 'z-6400-test', 'name' => 'Z 6400', 'type' => 'automotrice']);
        $this->line->materials()->attach($this->material->id);

        $now = now();
        DB::table('line_stops')->insert([
            [
                'line_id' => $this->line->id, 'era_id' => $this->currentEra->id, 'station_id' => $this->origin->id,
                'position' => 1, 'pk_m' => 0, 'effective_status' => 'active', 'is_terminal' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'line_id' => $this->line->id, 'era_id' => $this->currentEra->id, 'station_id' => $this->destination->id,
                'position' => 2, 'pk_m' => 17_250, 'effective_status' => 'active', 'is_terminal' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        DB::table('line_signalling')->insert([
            'line_id' => $this->line->id, 'era_id' => $this->currentEra->id,
            'pk_start_m' => 0, 'pk_end_m' => 17_250, 'signalling' => 'BAL',
            'train_protection' => json_encode(['KVB']), 'kvb_equipped' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    // ─── Trafic par portion (vff:classify-lines) ─────────────────────────
    //
    // Ligne SNCF fabriquée est-ouest à une latitude donnée, de 2,0° à 2,6° de longitude
    // (≈ 45 km), avec sa forme SNCF au même endroit : PK = (longitude - 2,0) × PORTION_PK_PER_DEG.
    // Les gares de liste-des-gares sont posées par leur longitude.

    /** PK (m) par degré de longitude des lignes fabriquées. */
    protected const PORTION_PK_PER_DEG = 75_000;

    /**
     * @param  list<array{0: float, 1: float}>  $pieces  tronçons du moteur [lon début, lon fin]
     * @param  list<array{0: float, 1: float, 2: string}>  $status  formes SNCF [lon début, lon fin, libellé]
     */
    protected function portionLine(int $eraId, string $code, float $lat, array $pieces = [[2.0, 2.6]], string $traffic = 'mixed',
        array $status = [[2.0, 2.6, 'Exploitée']], ?string $name = null, string $type = 'Ligne'): Line
    {
        $line = Line::factory()->create(['name' => $name ?? "Ligne {$code}", 'code' => $code, 'type' => null, 'network' => 'principal', 'is_lgv' => false]);
        foreach ($pieces as [$lon1, $lon2]) {
            $this->portionSegment($line, $eraId, $lat, $lon1, $lon2, $traffic);
        }
        foreach ($status as [$lon1, $lon2, $libelle]) {
            DB::table('sncf_line_geometries')->insert(['code_ligne' => $code, 'libelle' => $libelle,
                'pk_debut_m' => $this->portionPk($lon1), 'pk_fin_m' => $this->portionPk($lon2),
                'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon1, $lat, $lon2, $lat)),
                'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('sncf_line_types')->insert(['code_ligne' => $code, 'type_ligne' => $type]);

        return $line;
    }

    protected function portionSegment(Line $line, int $eraId, float $lat, float $lon1, float $lon2, string $traffic = 'mixed', string $status = 'in_service'): void
    {
        DB::table('line_segments')->insert(['line_id' => $line->id, 'era_id' => $eraId, 'status' => $status, 'traffic_type' => $traffic,
            'geom' => DB::raw(sprintf("ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326)", $lon1, $lat, $lon2, $lat)),
            'created_at' => now(), 'updated_at' => now()]);
    }

    /** Gare de liste-des-gares sur la ligne fabriquée $code, à la longitude $lon. */
    protected function portionStation(string $code, float $lat, float $lon, bool $voyageurs, bool $fret, ?string $name = null): void
    {
        static $n = 0;
        $n++;
        DB::table('sncf_gares')->insert(['code_uic' => sprintf('88%06d', $n), 'code_ligne' => $code, 'voyageurs' => $voyageurs, 'fret' => $fret,
            'libelle' => $name ?? "Gare {$code}-{$n}", 'lat' => $lat, 'lon' => $lon, 'pk_m' => $this->portionPk($lon)]);
    }

    /** Événement daté sur la portion [lon1, lon2] de la ligne (section_geom résolue), ou sans portion. */
    protected function portionEvent(Line $line, string $type, string $date, float $lat, ?float $lon1 = null, ?float $lon2 = null, string $confidence = 'medium'): void
    {
        DB::table('line_events')->insert(['line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'description' => "{$type} {$date}",
            'source' => 'wikipedia', 'confidence' => $confidence, 'section_resolution' => $lon1 === null ? 'unresolved' : 'pk',
            'section_geom' => $lon1 === null ? null : DB::raw(sprintf("ST_Multi(ST_GeomFromText('LINESTRING(%F %F, %F %F)', 4326))", $lon1, $lat, $lon2, $lat)),
            'created_at' => now(), 'updated_at' => now()]);
    }

    /** Trafic du tronçon actif de la ligne qui passe à la longitude $lon. */
    protected function portionTrafficAt(Line $line, int $eraId, float $lat, float $lon): ?string
    {
        return DB::table('line_segments')->where('line_id', $line->id)->where('era_id', $eraId)->whereIn('status', ['in_service', 'single_track'])
            // En degrés : un tronçon est-ouest est un parallèle (en géographie, un arc de grand cercle s'en écarte).
            ->whereRaw('ST_DWithin(geom, ST_SetSRID(ST_MakePoint(?, ?), 4326), 0.00005)', [$lon, $lat])
            ->orderByRaw('ST_Distance(ST_StartPoint(geom), ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$lon, $lat])
            ->value('traffic_type');
    }

    private function portionPk(float $lon): int
    {
        return (int) round(($lon - 2.0) * self::PORTION_PK_PER_DEG);
    }
}
