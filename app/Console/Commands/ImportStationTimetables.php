<?php

namespace App\Console\Commands;

use App\Services\StationService\FeedCatalog;
use App\Services\StationService\GtfsFeedStore;
use App\Services\StationService\Ymd;
use App\Services\Timetable\TimetableImporter;
use App\Services\Timetable\TimetableProfile;
use App\Services\Timetable\TimetableResult;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desserte d'un jour ouvrable type des gares et des lignes, d'après les horaires ouverts : GTFS SNCF (TER, Intercités,
 * TGV) et Transilien (Transilien et RER), un même mardi d'une semaine sans jour férié (la médiane des mardis des douze
 * prochaines semaines). Flux lus par StationService\GtfsFeedStore (cache storage/app/data/gtfs, .nobackup) ; licences
 * ODbL lues dans database/data/station_service_feeds.php.
 *
 *   php artisan vff:import-station-timetables --no-store --from-cache --report=storage/app/reports/agents/aprem-2111/dessertes-actuelles
 *   php artisan vff:import-station-timetables      # télécharge, calcule, range dans station_timetables et line_timetables
 *
 * Avec --no-store, rien n'est écrit en base : seulement des lectures et les rapports.
 */
class ImportStationTimetables extends Command
{
    /** Flux régionaux lus en plus (type TER), s'ils couvrent le jour type. */
    private const REGIONAL = ['zou-trains', 'zou-express', 'transdev-rail-sud', 'cfc'];

    private const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    protected $signature = 'vff:import-station-timetables
        {--no-store : Mesure seule : rapports et résumé, rien n\'est rangé en base}
        {--from-cache : Réutilise les flux déjà téléchargés (storage/app/data/gtfs)}
        {--date= : Jour à lire (AAAA-MM-JJ) ; défaut : mardi type choisi dans les horaires du flux SNCF}
        {--today= : Date du jour (AAAA-MM-JJ) ; défaut : aujourd\'hui}
        {--report= : Dossier où écrire couverture.csv et gares-sans-desserte.csv}';

    protected $description = 'Desserte d\'un jour ouvrable type des gares et des lignes d\'après les horaires GTFS SNCF et Transilien';

    public function handle(TimetableImporter $importer): int
    {
        ini_set('memory_limit', '2G');
        $today = Ymd::parse((string) ($this->option('today') ?: now()->toDateString()));
        $date = $this->option('date') ? Ymd::parse((string) $this->option('date')) : null;
        $catalog = FeedCatalog::fromFile(database_path('data/station_service_feeds.php'));
        $http = new HttpFactory;
        $http->globalOptions(['headers' => ['User-Agent' => self::USER_AGENT]]);
        $store = new GtfsFeedStore($http, storage_path('app/data/gtfs'));
        $fromCache = (bool) $this->option('from-cache');

        $feeds = [];
        $profiles = ['sncf' => TimetableProfile::sncf(), 'transilien' => TimetableProfile::transilien()];
        foreach (self::REGIONAL as $key) {
            $profiles[$key] = TimetableProfile::regional($key);
        }
        foreach ($profiles as $key => $profile) {
            [$definition] = $catalog->select([$key]);
            $feeds[] = [$store->fetch($definition, $fromCache, $today), $profile, $definition];
        }

        $result = $importer->analyse($feeds, $today, $date, fn (string $line) => $this->line('  '.$line));
        $this->coverage($result);

        $dir = (string) $this->option('report');
        if ($dir !== '') {
            $this->report($result, str_starts_with($dir, '/') ? $dir : base_path($dir));
        }
        if ($this->option('no-store')) {
            $this->info('Simulation : rien n\'est rangé en base.');

            return self::SUCCESS;
        }
        $n = $importer->store($result, now());
        $this->info("{$n['stations']} gares et {$n['lines']} lignes rangées dans station_timetables et line_timetables");

        return self::SUCCESS;
    }

    private function coverage(TimetableResult $result): void
    {
        $this->table(['classe', 'gares', 'avec desserte'], array_map(
            fn (array $c): array => [$c['label'], $c['total'], $c['with']],
            $this->coverageRows($result),
        ));
        $this->line(count($result->stations).' gares et '.count($result->lines).' lignes avec des trains ; '.count($result->unmatched).' zones d\'arrêt sans gare de VFF');
    }

    /**
     * Gares par classe de desserte (station_services, calculée par vff:import-station-service), avec ou sans trains au jour type.
     * Sans station_services (migration non appliquée), une seule ligne : toutes les gares.
     *
     * @return list<array{label: string, total: int, with: int}>
     */
    private function coverageRows(TimetableResult $result): array
    {
        if (! Schema::hasTable('station_services')) {
            return [['label' => 'toutes les gares', 'total' => (int) DB::table('stations')->count(), 'with' => count($result->stations)]];
        }
        $rows = [];
        foreach (DB::table('station_services')->selectRaw('service_class, array_agg(station_id) AS ids')->groupBy('service_class')->get() as $r) {
            $ids = array_map('intval', explode(',', trim((string) $r->ids, '{}')));
            $rows[] = ['label' => (string) $r->service_class, 'total' => count($ids), 'with' => count(array_filter($ids, fn (int $id): bool => isset($result->stations[$id])))];
        }

        return $rows;
    }

    private function report(TimetableResult $result, string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Dossier impossible à créer : {$dir}");

            return;
        }
        $csv = fopen("{$dir}/couverture.csv", 'wb');
        fputcsv($csv, ['classe', 'gares', 'avec_desserte'], ',', '"', '');
        foreach ($this->coverageRows($result) as $c) {
            fputcsv($csv, [$c['label'], $c['total'], $c['with']], ',', '"', '');
        }
        fclose($csv);

        if (Schema::hasTable('station_services')) {
            $csv = fopen("{$dir}/gares-sans-desserte.csv", 'wb');
            fputcsv($csv, ['station_id', 'slug', 'nom', 'departement', 'classe'], ',', '"', '');
            foreach (DB::table('station_services AS ss')->join('stations AS s', 's.id', '=', 'ss.station_id')
                ->whereIn('ss.service_class', ['regular', 'periodic'])->orderBy('s.name')
                ->get(['s.id', 's.slug', 's.name', 's.department_code', 'ss.service_class']) as $r) {
                if (! isset($result->stations[(int) $r->id])) {
                    fputcsv($csv, [$r->id, $r->slug, $r->name, $r->department_code, $r->service_class], ',', '"', '');
                }
            }
            fclose($csv);
        }
        file_put_contents("{$dir}/flux.json", json_encode($result->feeds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line("Rapports : {$dir}");
    }
}
