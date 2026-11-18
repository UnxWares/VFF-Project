<?php

namespace Tests\Support;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Outils des tests de commandes Artisan (tests/Feature/Commands) :
 *
 *   - stockage isolé : storage_path() et les disques local / public pointent
 *     vers un dossier temporaire, supprimé après le test. Les commandes écrivent
 *     des caches (storage/app/sources) et des rapports (storage/app/reports) :
 *     jamais dans ceux du poste de dev ;
 *   - fabriques compactes (ère, ligne, tronçon, gare) aux valeurs explicites ;
 *   - instantané des tables (nombre de lignes + empreinte du contenu), pour
 *     prouver qu'un --dry-run n'écrit rien et qu'une 2e exécution ne change rien.
 */
trait CommandSandbox
{
    protected string $sandbox;

    /** Tables métier comparées par les instantanés. */
    protected const SNAPSHOT_TABLES = [
        'lines', 'line_segments', 'line_events', 'line_sections', 'line_geometries', 'line_stops',
        'line_signalling', 'line_sources', 'stations', 'station_eras', 'station_lines', 'station_events',
        'station_sources', 'station_redirects', 'source_traces', 'source_stations', 'media',
        'present_states', 'archeo_lines', 'wikidata_lines', 'communes', 'sncf_gares', 'sncf_line_geometries',
    ];

    protected function sandboxStorage(): void
    {
        $this->sandbox = sys_get_temp_dir().'/vff-cmd-test-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->sandbox.'/app/public');
        File::ensureDirectoryExists($this->sandbox.'/app/reports');
        File::ensureDirectoryExists($this->sandbox.'/app/sources');

        $this->app->useStoragePath($this->sandbox);
        config([
            'filesystems.disks.local.root' => $this->sandbox.'/app',
            'filesystems.disks.public.root' => $this->sandbox.'/app/public',
        ]);
        Storage::forgetDisk(['local', 'public']);

        $sandbox = $this->sandbox;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($sandbox));
    }

    /**
     * Remplace un fichier de database/data (ex. line_event_corrections.php) par un
     * fichier du bac à sable : la commande ne lit jamais les vraies données de
     * curation. Les autres fichiers de données restent ceux du projet.
     *
     * @param  array<mixed>  $content
     */
    protected function useDataFile(string $name, array $content): void
    {
        $dir = $this->sandbox.'/database/data';
        if (! is_dir($dir)) {
            File::copyDirectory($this->app->databasePath('data'), $dir);
            $original = $this->app->databasePath();
            $this->app->useDatabasePath($this->sandbox.'/database');
            $this->beforeApplicationDestroyed(fn () => $this->app->useDatabasePath($original));
        }
        // Corrections : la commande lit TOUS les line_event_corrections*.php ; seuls ceux du test restent.
        if (str_starts_with($name, 'line_event_corrections')) {
            foreach (glob($dir.'/line_event_corrections*.php') ?: [] as $file) {
                File::delete($file);
            }
        }
        File::put($dir.'/'.$name, '<?php return '.var_export($content, true).';');
    }

    /** Chemin d'une fixture de tests/Fixtures. */
    protected function fixturePath(string $relative): string
    {
        return dirname(__DIR__).'/Fixtures/'.$relative;
    }

    protected function fixture(string $relative): string
    {
        return (string) file_get_contents($this->fixturePath($relative));
    }

    /** @return array<string, mixed> */
    protected function jsonFixture(string $relative): array
    {
        return json_decode($this->fixture($relative), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function era(int $year, bool $current = false): Era
    {
        return Era::factory()->create(['year' => $year, 'label' => "Années {$year}", 'is_current' => $current]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function rfnLine(string $code, string $name, array $attributes = []): Line
    {
        return Line::factory()->create($attributes + [
            'code' => $code,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$code,
            'type' => null,
            'network' => 'principal',
        ]);
    }

    /**
     * Tronçon droit entre deux points [lat, lng] (ou une polyligne).
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @param  array<string, mixed>  $attributes
     */
    protected function track(Line $line, Era $era, array $points, array $attributes = []): LineSegment
    {
        return LineSegment::factory()->create($attributes + [
            'line_id' => $line->id,
            'era_id' => $era->id,
            'geom' => LineString::make(array_map(fn ($p) => Point::makeGeodetic($p[0], $p[1]), $points)),
            'status' => 'in_service',
            'traffic_type' => 'mixed',
            'is_heritage' => false,
            'electrified' => false,
            'voltage_v' => null,
            // Valeur fixe : la factory tire une vitesse au hasard (jusqu'à 320 km/h,
            // signal LGV) qui rendrait les tests aléatoires.
            'max_speed_kmh' => 120,
            'gauge_mm' => 1435,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function station(string $slug, string $name, float $lat, float $lng, array $attributes = []): Station
    {
        return Station::factory()->create($attributes + [
            'slug' => $slug,
            'name' => $name,
            'type' => 'gare',
            'geom' => Point::makeGeodetic($lat, $lng),
            'city' => $name,
            'department_code' => '75',
            'opened_at' => null,
            'closed_at' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Nombre de lignes et empreinte md5 du contenu de chaque table (hors
     * horodatages, qu'un UPDATE « à l'identique » toucherait sans rien changer).
     *
     * $except : colonnes ignorées en plus (ex. ['id'] pour une table remplacée à
     * chaque import, dont les identifiants changent sans que le contenu change).
     *
     * @param  list<string>|null  $tables
     * @param  list<string>  $except
     * @return array<string, array{rows: int, md5: string|null}>
     */
    protected function snapshot(?array $tables = null, array $except = []): array
    {
        $strip = implode('', array_map(fn ($c) => " - '{$c}'", ['created_at', 'updated_at', ...$except]));
        $out = [];
        foreach ($tables ?? self::SNAPSHOT_TABLES as $table) {
            $out[$table] = [
                'rows' => (int) DB::table($table)->count(),
                'md5' => DB::selectOne(
                    "SELECT md5(string_agg((to_jsonb(t){$strip})::text, '|' ORDER BY (to_jsonb(t){$strip})::text)) AS h FROM {$table} t"
                )->h,
            ];
        }

        return $out;
    }
}
