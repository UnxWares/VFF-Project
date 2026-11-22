<?php

namespace Database\Seeders;

use App\Models\Era;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Jeu de données minimal et déterministe de la suite de fumée Playwright (tests/e2e, job `e2e` de la CI).
 *
 * Sur une base vide migrée : les ères, la ligne d'Andelot-en-Montagne à La Cluse (rfn-878000) avec trois
 * gares (dont `andelot`), ses portions, ses arrêts et ses états de gare par ère, un accident
 * (`1890-09-16-andelot-en-montagne`) rattaché à la ligne et à la gare, et une seconde ligne pour que la
 * liste ne soit pas réduite à une fiche. Ce sont des faits publics (noms, communes, dates) : aucune donnée
 * personnelle, aucun compte. L'article du journal vient des fichiers versionnés `resources/content/journal`.
 *
 * Idempotent : relancer le seeder ne duplique rien (clés naturelles : slug, ère + ligne + gare).
 * Garde-fou : refuse toute base dont le nom ne finit pas par `_test` (jamais la base de développement `vff`).
 */
class E2eSeeder extends Seeder
{
    public const string LINE = 'rfn-878000';

    public const string STATION = 'andelot';

    public const string ACCIDENT = '1890-09-16-andelot-en-montagne';

    /** Première ère où la ligne figure (ouverture de la ligne en 1862 à 1883). */
    private const int FIRST_ERA = 1870;

    public function run(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        if (! str_ends_with($database, '_test')) {
            throw new \RuntimeException("E2eSeeder refuse la base « {$database} » : seule une base dont le nom finit par _test est acceptée.");
        }

        $this->call([EraSeeder::class, HistoricalErasSeeder::class]);

        DB::transaction(function (): void {
            $lineId = $this->line(self::LINE, "Ligne d'Andelot-en-Montagne à La Cluse", '878000', 'mixed', 'principal');
            $otherId = $this->line('rfn-830000', 'Ligne de Paris-Lyon à Marseille-Saint-Charles (ligne de démonstration)', '830000', 'mixed', 'principal');

            $stations = [
                ['slug' => self::STATION, 'name' => 'Andelot', 'city' => 'Andelot-en-Montagne', 'dep' => '39', 'lon' => 5.9236614, 'lat' => 46.8593999, 'opened' => '1862-01-01', 'pk' => 0],
                ['slug' => 'champagnole', 'name' => 'Champagnole', 'city' => 'Champagnole', 'dep' => '39', 'lon' => 5.9086, 'lat' => 46.7457, 'opened' => '1864-01-01', 'pk' => 13723],
                ['slug' => 'la-cluse', 'name' => 'La Cluse', 'city' => 'Les Neyrolles', 'dep' => '01', 'lon' => 5.6603, 'lat' => 46.2085, 'opened' => '1882-01-01', 'pk' => 123000],
            ];
            $stationIds = [];
            foreach ($stations as $s) {
                $stationIds[$s['slug']] = $this->station($s);
            }

            $path = 'LINESTRING(5.9236614 46.8593999, 5.9086 46.7457, 5.7800 46.4300, 5.6603 46.2085)';
            foreach (Era::query()->where('year', '>=', self::FIRST_ERA)->orderBy('year')->get() as $era) {
                $this->segment($lineId, $era->id, $path);
                $position = 0;
                foreach ($stations as $s) {
                    $this->stop($lineId, $era->id, $stationIds[$s['slug']], $position++, $s['pk']);
                    $this->stationEra($stationIds[$s['slug']], $era->id);
                }
            }

            $this->segment($otherId, Era::query()->where('year', 2030)->value('id'), 'LINESTRING(2.3730 48.8443, 4.8357 45.7606, 5.3800 43.3030)');

            $this->accident($stationIds[self::STATION], $lineId);
        });
    }

    private function line(string $slug, string $name, string $code, string $type, string $network): int
    {
        $now = now();
        DB::table('lines')->updateOrInsert(['slug' => $slug], [
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'network' => $network,
            'description' => "{$name} est une ligne de chemin de fer à voie unique du Jura français. Jeu de données de test : texte court et sans valeur encyclopédique.",
            'length_official_m' => 123000,
            'gauge_mm_default' => 1435,
            'max_speed_kmh' => 110,
            'electrified' => false,
            'regions' => json_encode(['Bourgogne-Franche-Comté']),
            'departements' => json_encode(['Jura']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('lines')->where('slug', $slug)->value('id');
    }

    /** @param array{slug: string, name: string, city: string, dep: string, lon: float, lat: float, opened: string, pk: int} $s */
    private function station(array $s): int
    {
        $now = now();
        DB::table('stations')->updateOrInsert(['slug' => $s['slug']], [
            'name' => $s['name'],
            'type' => 'gare',
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $s['lon'], $s['lat'])),
            'city' => $s['city'],
            'department_code' => $s['dep'],
            'opened_at' => $s['opened'],
            'status' => 'active',
            'lifecycle' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('stations')->where('slug', $s['slug'])->value('id');
    }

    private function segment(int $lineId, int $eraId, string $wkt): void
    {
        $now = now();
        DB::table('line_segments')->updateOrInsert(['line_id' => $lineId, 'era_id' => $eraId], [
            'geom' => DB::raw(sprintf("ST_SetSRID(ST_GeomFromText('%s'), 4326)", $wkt)),
            'status' => 'in_service',
            'max_speed_kmh' => 110,
            'electrified' => false,
            'gauge_mm' => 1435,
            'traffic_type' => 'mixed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function stop(int $lineId, int $eraId, int $stationId, int $position, int $pk): void
    {
        $now = now();
        DB::table('line_stops')->updateOrInsert(['line_id' => $lineId, 'era_id' => $eraId, 'station_id' => $stationId], [
            'position' => $position,
            'pk_m' => $pk,
            'effective_status' => 'active',
            'is_terminal' => $position === 0 || $pk === 123000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function stationEra(int $stationId, int $eraId): void
    {
        $now = now();
        DB::table('station_eras')->updateOrInsert(['station_id' => $stationId, 'era_id' => $eraId], [
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function accident(int $stationId, int $lineId): void
    {
        $now = now();
        DB::table('accidents')->updateOrInsert(['slug' => self::ACCIDENT], [
            'occurred_on' => '1890-09-16',
            'date_precision' => 'day',
            'kind' => 'collision',
            'name' => json_encode([
                'fr' => 'Collision à Andelot-en-Montagne',
                'en' => 'Collision at Andelot-en-Montagne',
                'de' => 'Zusammenstoß in Andelot-en-Montagne',
            ]),
            'commune_insee' => '39009',
            'commune_name' => 'Andelot-en-Montagne',
            'department_code' => '39',
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(5.9236614, 46.8593999), 4326)'),
            'location_precision' => 'station',
            'location_source' => 'station',
            'nearest_station_id' => $stationId,
            'nearest_station_distance_m' => 0,
            'deaths' => 2,
            'injuries' => 9,
            'toll_source' => 'wikipedia',
            'summary' => json_encode([
                'fr' => 'Le 16 septembre 1890, une collision ferroviaire a lieu à Andelot-en-Montagne (Jura). Bilan : 2 morts et 9 blessés.',
                'en' => 'On 16 September 1890, a railway collision occurred at Andelot-en-Montagne (Jura). Toll: 2 dead and 9 injured.',
                'de' => 'Am 16. September 1890 ereignete sich in Andelot-en-Montagne (Jura) ein Zusammenstoß im Bahnverkehr. Bilanz: 2 Tote und 9 Verletzte.',
            ]),
            'review_status' => 'ok',
            'provenance' => json_encode(['date' => 'wikipedia', 'kind' => 'wikipedia', 'name' => 'vff', 'deaths' => 'wikipedia', 'location' => 'station']),
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $accidentId = (int) DB::table('accidents')->where('slug', self::ACCIDENT)->value('id');

        DB::table('accident_lines')->updateOrInsert(['accident_id' => $accidentId, 'line_id' => $lineId], [
            'is_primary' => true,
            'era_year' => 1890,
            'distance_m' => 31,
            'pk_m' => 0,
            'method' => 'proximity+wikipedia',
            'confidence' => 'high',
            'score' => 0.987,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
