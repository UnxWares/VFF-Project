<?php

namespace App\Console\Commands;

use App\Services\Heritage\MerimeeSource;
use App\Services\Heritage\StationHeritageBuilder;
use App\Services\Heritage\WikidataStationHeritage;
use App\Services\Media\CommonsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import du patrimoine des gares : bâtiments voyageurs et ouvrages de gare protégés (monuments historiques classés ou
 * inscrits), labellisés (Architecture contemporaine remarquable) ou recensés à l'Inventaire général.
 *
 * Sources : base Mérimée du ministère de la Culture (POP, Licence Ouverte 2.0 : arrêté, date, architecte, datation)
 * et Wikidata (CC0 : P1435 statut, P380 référence, P84 architecte, P571 construction, P149 style, P18 image libre).
 * Rattachement aux gares (StationHeritageBuilder) : par Wikidata (P380, P1435), à défaut par le nom et la commune, puis
 * par la proximité des coordonnées de la notice. Les lignes `source` `merimee` ou `wikidata` de `station_heritage` sont
 * remplacées d'un coup, dans une transaction ; aucune autre table n'est écrite.
 *
 * Les sources sont téléchargées une fois (licence et éditeur vérifiés à chaque téléchargement, cache
 * storage/app/sources/patrimoine/, dossier `.nobackup`) ; --refresh les télécharge de nouveau. Le débit est poli
 * (une requête par seconde sur Wikidata et Commons, User-Agent de VFF).
 *
 *   php artisan vff:import-station-heritage --dry-run --show=limoges-benedictins,metz-ville
 *   php artisan vff:import-station-heritage --dry-run --csv=/chemin/
 *   php artisan vff:import-station-heritage
 */
class ImportStationHeritage extends Command
{
    protected $signature = 'vff:import-station-heritage
        {--dry-run : rapport seulement, aucune écriture en base}
        {--refresh : télécharge de nouveau les sources au lieu du cache local}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/patrimoine)}
        {--show= : gares (slugs séparés par une virgule) dont les lignes sont affichées}
        {--csv= : dossier où écrire les rapports détaillés au format CSV}';

    protected $description = 'Importe le patrimoine des gares (monuments historiques, label ACR, Inventaire) depuis Mérimée et Wikidata';

    public function handle(MerimeeSource $merimee): int
    {
        $dir = (string) ($this->option('cache-dir') ?: storage_path('app/sources/patrimoine'));
        $refresh = (bool) $this->option('refresh');
        try {
            $source = $merimee->load($dir, $refresh);
            $records = $merimee->railwayRecords($source['path']);
            $this->info(sprintf('Mérimée : %d notices de gare ou d\'ouvrage de gare protégées ou labellisées (fichier du %s, licence %s).',
                count($records), $source['modified'] ?? '?', $source['license']));

            $stations = DB::table('stations')->selectRaw('id, slug, name, city, department_code, status, wikidata_id, ST_Y(geom) AS lat, ST_X(geom) AS lng')->get();
            $qids = $stations->pluck('wikidata_id')->filter(fn ($q) => is_string($q) && preg_match('/^Q\d+$/', $q) === 1)->values()->all();
            $wikidata = (new WikidataStationHeritage(new CommonsClient))->fetch($qids, $dir, $refresh);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(sprintf('Wikidata : %d gares sur %d ont un statut de patrimoine ou une référence Mérimée.', count($wikidata), count($qids)));

        $result = (new StationHeritageBuilder($stations))->build($records, $wikidata, $source['modified']);
        $rows = $result['rows'];
        $this->report($stations, $rows, $result);

        if ($this->option('dry-run')) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }
        if (! Schema::hasTable('station_heritage')) {
            $this->error('La table station_heritage n\'existe pas : appliquer la migration 2026_12_15_000000_create_station_heritage_table avant l\'import.');

            return self::FAILURE;
        }

        $now = now();
        $insert = array_map(function (array $r) use ($now): array {
            foreach (['designations', 'architects'] as $json) {
                $r[$json] = $r[$json] === [] || $r[$json] === null ? null : json_encode($r[$json], JSON_UNESCAPED_UNICODE);
            }

            return $r + ['created_at' => $now, 'updated_at' => $now];
        }, $rows);
        DB::transaction(function () use ($insert): void {
            DB::table('station_heritage')->whereIn('source', ['merimee', 'wikidata'])->delete();
            foreach (array_chunk($insert, 200) as $chunk) {
                DB::table('station_heritage')->insert($chunk);
            }
        });
        $this->info('Écrit : '.count($insert).' lignes.');

        return self::SUCCESS;
    }

    /**
     * @param  iterable<object>  $stations
     * @param  list<array<string, mixed>>  $rows
     * @param  array{rows: list<array<string, mixed>>, unmatched: list<array<string, mixed>>, methods: array<string, int>, scored?: list<array<string, mixed>>}  $result
     */
    private function report(iterable $stations, array $rows, array $result): void
    {
        $bySlug = [];
        $byId = [];
        foreach ($stations as $s) {
            $bySlug[$s->slug] = $s;
            $byId[$s->id] = $s;
        }
        $this->line('');
        $this->line('Rattachement : '.implode(', ', array_map(fn ($m, $n) => "{$m} {$n}", array_keys($result['methods']), $result['methods'])));
        $covered = [];
        $byProtection = [];
        foreach ($rows as $r) {
            if ($r['element'] === 'building') {
                $covered[$r['station_id']] = true;
            }
            $byProtection[$r['protection'].'/'.$r['element']] = ($byProtection[$r['protection'].'/'.$r['element']] ?? 0) + 1;
        }
        ksort($byProtection);
        $this->line(sprintf('Lignes à écrire dans station_heritage : %d, pour %d gares avec bâtiment voyageurs (aujourd\'hui en base : %d).', count($rows), count($covered),
            Schema::hasTable('station_heritage') ? (int) DB::table('station_heritage')->count() : 0));
        $this->line('Par protection et élément : '.implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($byProtection), $byProtection)));

        $protected = [];
        foreach ($rows as $r) {
            if (in_array($r['protection'], ['classified', 'registered'], true) && $r['element'] === 'building') {
                $protected[$r['station_id']] = true;
            }
        }
        $perStatus = [];
        foreach ($byId as $id => $s) {
            $perStatus[$s->status][0] = ($perStatus[$s->status][0] ?? 0) + 1;
            $perStatus[$s->status][1] = ($perStatus[$s->status][1] ?? 0) + (isset($protected[$id]) ? 1 : 0);
        }
        $this->line('Couverture (gares dont le bâtiment est classé ou inscrit) :');
        foreach ($perStatus as $status => [$total, $with]) {
            $this->line(sprintf('  %-13s %5d gares, %4d protégées', $status, $total, $with));
        }
        foreach ($result['scored'] ?? [] as $sc) {
            $this->line(sprintf('  par points : %s (%s, %s) -> %s, %d points : %s', $sc['record']['title'], $sc['record']['commune'], $sc['record']['ref'], $sc['station']->slug, $sc['score'], implode(', ', $sc['reasons'])));
        }
        $this->line(sprintf('Notices Mérimée sans gare de VFF : %d.', count($result['unmatched'])));
        foreach ($result['unmatched'] as $u) {
            $this->warn(sprintf('  %s : %s (%s, %s) [%s]', $u['why'], $u['title'], $u['commune'], $u['ref'], $u['element']));
        }

        if ($show = trim((string) $this->option('show'))) {
            $this->line('');
            foreach (explode(',', $show) as $slug) {
                $slug = trim($slug);
                $station = $bySlug[$slug] ?? null;
                $mine = $station === null ? [] : array_values(array_filter($rows, fn (array $r): bool => $r['station_id'] === (int) $station->id));
                $this->line($slug.' : '.($station === null ? 'gare inconnue' : ($mine === [] ? 'aucun patrimoine' : '')));
                foreach ($mine as $r) {
                    $this->line(sprintf('  [%s] %s %s%s ref %s, %s, architectes : %s, construit : %s%s, style : %s, image : %s, rattachement : %s%s',
                        $r['element'], $r['protection'], $r['protection_date'] ?? 'sans date', $r['scope'] === 'partial' ? ' (partielle)' : '', $r['ref'], $r['title'] ?? '-',
                        implode(', ', array_column($r['architects'] ?? [], 'name')) ?: '-', $r['built_from'] ?? $r['built_period'] ?? '-', $r['built_to'] ? '-'.$r['built_to'] : '',
                        $r['style'] ?? '-', $r['image_file'] ?? '-', $r['match_method'], $r['match_distance_m'] !== null ? " ({$r['match_distance_m']} m)" : ''));
                }
            }
        }

        if ($dir = rtrim((string) $this->option('csv'), '/')) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $this->csv($dir.'/rapport.csv', ['gare', 'element', 'protection', 'date', 'partielle', 'reference', 'titre', 'architectes', 'construction', 'style', 'image', 'methode', 'distance_m'],
                array_map(fn (array $r) => [$byId[$r['station_id']]->slug, $r['element'], $r['protection'], $r['protection_date'], $r['scope'], $r['ref'], $r['title'],
                    implode('; ', array_column($r['architects'] ?? [], 'name')), $r['built_from'], $r['style'], $r['image_file'], $r['match_method'], $r['match_distance_m']], $rows));
            $this->csv($dir.'/non-rattachees.csv', ['reference', 'titre', 'commune', 'departement', 'element', 'raison'],
                array_map(fn (array $u) => [$u['ref'], $u['title'], $u['commune'], $u['department'], $u['element'], $u['why']], $result['unmatched']));
            $this->csv($dir.'/rattachees-par-points.csv', ['reference', 'titre', 'commune', 'element', 'gare', 'points', 'explication'],
                array_map(fn (array $sc) => [$sc['record']['ref'], $sc['record']['title'], $sc['record']['commune'], $sc['record']['element'], $sc['station']->slug, $sc['score'], implode(', ', $sc['reasons'])], $result['scored'] ?? []));
            $this->line("Rapports : {$dir}");
        }
    }

    /** @param  list<list<mixed>>  $rows */
    private function csv(string $path, array $header, array $rows): void
    {
        $f = fopen($path, 'wb');
        fputcsv($f, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($f, $row, ',', '"', '');
        }
        fclose($f);
    }
}
