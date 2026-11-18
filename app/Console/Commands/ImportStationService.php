<?php

namespace App\Console\Commands;

use App\Enums\StationServiceClass;
use App\Services\StationService\FeedCatalog;
use App\Services\StationService\GtfsFeedStore;
use App\Services\StationService\StationServiceImporter;
use App\Services\StationService\StationServiceReport;
use App\Services\StationService\Ymd;
use Illuminate\Console\Command;

/**
 * Desserte voyageurs des gares aujourd'hui, d'après les horaires ouverts (GTFS) :
 * SNCF (et sa version de mi-juin pour les dessertes d'été), Transilien et RER,
 * trains régionaux ZOU ! et Chemins de fer de Provence, Transdev Rail Sud,
 * Chemins de fer de la Corse, Eurostar, Renfe, Trenitalia, Panoramique des Dômes
 * (database/data/station_service_feeds.php). Trains seulement : les cars TER et de
 * substitution comptent comme cars. Classes : App\Enums\StationServiceClass.
 *
 *   php artisan vff:import-station-service                     # télécharge, classe, range
 *   php artisan vff:import-station-service --from-cache --no-store --report=storage/app/reports/agents/gares-desserte
 *
 * Écrit seulement dans station_services : le statut des gares n'est jamais modifié.
 */
class ImportStationService extends Command
{
    protected $signature = 'vff:import-station-service
        {--feed=* : Flux à lire (clés de database/data/station_service_feeds.php) ; défaut : tous sauf ceux lus sur demande}
        {--from-cache : Réutilise les flux déjà téléchargés (storage/app/data/gtfs)}
        {--no-store : Mesure seule : rapports et résumé, rien n\'est rangé en base}
        {--report= : Dossier où écrire gares.csv, ecarts.csv et arrets-non-rapproches.csv}
        {--today= : Date du jour (AAAA-MM-JJ) ; défaut : aujourd\'hui}';

    protected $description = 'Desserte voyageurs des gares aujourd\'hui d\'après les horaires GTFS ouverts (table station_services)';

    private const FREQUENTATION_URL = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/frequentation-gares/exports/csv?delimiter=%3B';

    public function handle(StationServiceImporter $importer, StationServiceReport $report): int
    {
        ini_set('memory_limit', '2G');
        $today = Ymd::parse((string) ($this->option('today') ?: now()->toDateString()));
        $catalog = FeedCatalog::fromFile(database_path('data/station_service_feeds.php'));
        $store = app()->makeWith(GtfsFeedStore::class, ['root' => storage_path('app/data/gtfs')]);
        $fromCache = (bool) $this->option('from-cache');

        $feeds = [];
        foreach ($catalog->select(array_values((array) $this->option('feed'))) as $feed) {
            $feeds[] = [$store->fetch($feed, $fromCache, $today), $feed];
        }
        $frequentation = StationServiceImporter::readFrequentation($store->fetchFile(self::FREQUENTATION_URL, 'sncf-frequentation-gares.csv', $fromCache));

        $result = $importer->analyse($feeds, $today, $catalog->restrictedDepartments(), $frequentation, fn (string $line) => $this->line('  '.$line));

        $rows = [];
        foreach ($report->crossTab($result) as $class => $byStatus) {
            $rows[] = [StationServiceClass::from($class)->label(), array_sum($byStatus), $byStatus['active'] ?? 0, $byStatus['tourist'] ?? 0,
                $byStatus['no_commercial'] ?? 0, $byStatus['declassified'] ?? 0];
        }
        $this->table(['classe', 'gares', 'VFF active', 'VFF touristique', 'VFF fret seul', 'VFF fermée'], $rows);
        $this->line(count($result->unmatched).' arrêts ferroviaires sans gare de VFF');

        $dir = (string) $this->option('report');
        if ($dir !== '') {
            foreach ($report->write($result, str_starts_with($dir, '/') ? $dir : base_path($dir)) as $file) {
                $this->line("Rapport : {$file}");
            }
        }
        if (! $this->option('no-store')) {
            $this->info($importer->store($result, now()).' gares rangées dans station_services');
        }

        return self::SUCCESS;
    }
}
