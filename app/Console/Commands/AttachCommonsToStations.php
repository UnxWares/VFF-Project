<?php

namespace App\Console\Commands;

use App\Models\Station;
use App\Services\Media\Sources\CommonsStationSource;
use App\Services\Media\Sources\MediaWriter;
use Illuminate\Console\Command;

/**
 * Rattache aux gares les fichiers Commons déjà en base pour les lignes (aucune requête réseau) : règles de
 * CommonsStationRules, écriture par MediaWriter (idempotente). `--dry-run` compte sans écrire ;
 * `--sample` tire des rattachements au hasard pour la relecture à la main, `--csv` les écrit tous.
 *
 *   vff:attach-commons-stations --dry-run --sample=30 --seed=7
 *   vff:attach-commons-stations --dry-run --station=genlis
 *   vff:attach-commons-stations
 */
class AttachCommonsToStations extends Command
{
    protected $signature = 'vff:attach-commons-stations
        {--dry-run : N\'écrit rien en base : compte les gares et les fichiers}
        {--station= : Une seule gare (slug)}
        {--limit= : Nombre maximal de fichiers Commons lus (essais)}
        {--sample=0 : Nombre de rattachements tirés au hasard et affichés}
        {--seed=1 : Graine du tirage}
        {--csv= : Fichier CSV où écrire tous les rattachements retenus}';

    protected $description = 'Rattache aux gares les fichiers Commons déjà en base (catégorie P373, nom de la gare, position, date), sans réseau';

    public function handle(MediaWriter $writer): int
    {
        $source = app(CommonsStationSource::class);
        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $slug = $this->option('station') !== null ? (string) $this->option('station') : null;

        $report = $writer->write($source->records($limit, $slug), $dry);

        $byVia = [];
        foreach ($source->accepted as $a) {
            $byVia[$a['via']] = ($byVia[$a['via']] ?? 0) + 1;
        }
        $this->info(($dry ? '[à blanc] ' : '').sprintf(
            '%d rattachements (%d nouveaux, %d déjà là), %d gares, dont %d sans aucun média avant',
            $report->inserted + $report->updated, $report->inserted, $report->updated,
            $report->touchedCount(Station::class), $report->gainedCount(Station::class),
        ));
        $this->table(['Méthode', 'Rattachements'], collect($byVia)->map(fn (int $n, string $v): array => [$v, $n])->values()->all());
        $rejected = [...$source->rejected, ...array_combine(array_map(fn (string $k): string => "invalide:{$k}", array_keys($report->refused)), $report->refused)];
        if ($rejected !== []) {
            arsort($rejected);
            $this->table(['Écarté', 'Nombre'], collect($rejected)->map(fn (int $n, string $r): array => [$r, $n])->values()->all());
        }

        if ($this->option('csv') !== null) {
            $this->writeCsv((string) $this->option('csv'), $source->accepted);
        }
        $sample = (int) $this->option('sample');
        if ($sample > 0 && $source->accepted !== []) {
            mt_srand((int) $this->option('seed'));
            $keys = array_rand($source->accepted, min($sample, count($source->accepted)));
            $rows = array_map(fn (int $k): array => $source->accepted[$k], (array) $keys);
            $this->table(['Gare', 'Fichier', 'Méthode', 'Correspondance', 'Distance (m)', 'Année', 'Page'],
                array_map(fn (array $a): array => [$a['station'], $a['file'], $a['via'], $a['match'], $a['distance_m'] ?? '', $a['year'] ?? '', $a['source_url']], $rows));
        }

        return self::SUCCESS;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function writeCsv(string $path, array $rows): void
    {
        $h = fopen($path, 'wb');
        if ($h === false) {
            $this->error("Impossible d'écrire {$path}");

            return;
        }
        fputcsv($h, ['station_id', 'slug', 'station', 'file', 'via', 'match', 'distance_m', 'served', 'year', 'source_url', 'thumb_url'], escape: '\\');
        foreach ($rows as $r) {
            fputcsv($h, [$r['station_id'], $r['slug'], $r['station'], $r['file'], $r['via'], $r['match'], $r['distance_m'], $r['served'] ? 1 : 0, $r['year'], $r['source_url'], $r['thumb_url']], escape: '\\');
        }
        fclose($h);
        $this->line("CSV : {$path} (".count($rows).' lignes)');
    }
}
