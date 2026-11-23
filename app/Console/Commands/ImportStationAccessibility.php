<?php

namespace App\Console\Commands;

use App\Services\Accessibility\AccessibilityDatasets as Datasets;
use App\Services\Accessibility\AccessibilityNetex;
use App\Services\Accessibility\StationAccessibilityBuilder;
use App\Services\Sncf\SncfOpenDataCache;
use App\Services\Sncf\StationAttendanceMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import de l'accessibilité et des services des gares d'aujourd'hui, d'après sept jeux ouverts de SNCF Gares & Connexions
 * (ODbL, éditeur et licence vérifiés à chaque téléchargement) : « Accessibilité des gares » (NeTEx : accès des personnes à
 * mobilité réduite, quais, ascenseurs), « Equipements d'accessibilité en gare » (boucle magnétique, bandes d'éveil, toilettes…),
 * assistance en gare, état de fonctionnement des ascenseurs, points de vente, stationnement vélo sécurisé, et le référentiel
 * « Gares de voyageurs » qui relie les codes UIC et les trigrammes à l'identifiant commun. Chaque gare est rattachée à une gare de
 * VFF par son code UIC (StationAttendanceMatcher). Toutes les lignes `sncf` de `station_accessibility` sont remplacées d'un coup,
 * dans une transaction ; aucune autre table n'est écrite, le statut des gares n'est jamais modifié.
 *
 * Les jeux sont téléchargés une fois (cache storage/app/sources/sncf-accessibility/, dossier `.nobackup`) ; --refresh les
 * télécharge de nouveau (à faire pour lire l'état des ascenseurs du jour).
 *
 *   php artisan vff:import-station-accessibility --dry-run --refresh   # mesure, aucune écriture
 *   php artisan vff:import-station-accessibility --refresh             # écrit
 */
class ImportStationAccessibility extends Command
{
    protected $signature = 'vff:import-station-accessibility
        {--dry-run : rapport seulement, aucune écriture en base}
        {--refresh : télécharge de nouveau les jeux au lieu du cache local}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-accessibility)}
        {--csv= : dossier où écrire les rapports détaillés au format CSV}';

    protected $description = 'Importe l\'accessibilité et les services des gares d\'aujourd\'hui (SNCF Gares & Connexions) et les rattache aux gares par code UIC';

    public function handle(SncfOpenDataCache $cache, AccessibilityNetex $netex): int
    {
        $dir = (string) ($this->option('cache-dir') ?: storage_path('app/sources/sncf-accessibility'));
        $refresh = (bool) $this->option('refresh');
        $data = [];
        $meta = [];
        try {
            foreach (Datasets::PUBLISHERS as $name => $publisher) {
                $data[$name] = $cache->load($name, [], $dir, $refresh, $publisher);
                $this->info(sprintf('%s : %d lignes (jeu modifié le %s, licence %s).', $name, count($data[$name]['records']), $data[$name]['modified'] ?? '?', $data[$name]['license']));
            }
            $file = $netex->fetch($dir, $refresh);
            $stops = $netex->stations($file['path']);
            $this->info(sprintf('%s : %d gares (jeu modifié le %s, licence %s).', AccessibilityNetex::DATASET, count($stops), $file['modified'] ?? '?', $file['license']));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $builder = new StationAccessibilityBuilder($data[Datasets::GARES]['records']);
        $elevatorsAt = $data[Datasets::ELEVATORS]['fetched_at'] ?? null;
        $built = $builder->build($stops, $data[Datasets::EQUIPMENTS]['records'], $data[Datasets::ASSISTANCE]['records'],
            $data[Datasets::ELEVATORS]['records'], $data[Datasets::SALES]['records'], $data[Datasets::BIKES]['records'],
            $elevatorsAt !== null ? CarbonImmutable::parse($elevatorsAt)->toIso8601String() : null);
        foreach ($builder->unknownAmenities as $label => $n) {
            $this->warn("  équipement inconnu ignoré : {$label} ({$n})");
        }

        $datasets = [];
        foreach (Datasets::TITLES as $name => $title) {
            $info = $name === AccessibilityNetex::DATASET ? $file : $data[$name];
            $datasets[$name] = ['dataset' => $name, 'title' => $title, 'publisher' => Datasets::PUBLISHERS[$name] ?? AccessibilityNetex::PUBLISHER,
                'license' => 'ODbL', 'modified' => $info['modified'], 'page' => Datasets::page($name)];
        }

        $stations = DB::table('stations')->get(['id', 'slug', 'name', 'uic_code', 'status', 'department_code', 'city']);
        $matcher = new StationAttendanceMatcher($stations);
        $now = now();
        /** @var array<int, array<string, mixed>> $rows */
        $rows = [];
        $unmatched = [];
        $duplicates = [];
        foreach ($built as $gareId => $row) {
            // Une gare du référentiel peut porter plusieurs codes UIC (Paris-Nord : trois) : chaque fiche de VFF reconnue reçoit les informations.
            $targets = [];
            foreach ($builder->uicsOf($gareId) as $code) {
                $found = $matcher->match($code, '', null);
                if ($found['station'] !== null) {
                    $targets[$found['station']->id] ??= [$found['station'], $code];
                }
            }
            if ($targets === []) {
                $unmatched[] = [$gareId, implode(';', $builder->uicsOf($gareId)), $stops[$gareId]['name'] ?? ''];

                continue;
            }
            $present = [
                AccessibilityNetex::DATASET => $row['mobility'] !== null,
                Datasets::EQUIPMENTS => $row['amenities'] !== [],
                Datasets::ASSISTANCE => $row['assistance'] !== null,
                Datasets::ELEVATORS => $row['elevators'] !== null,
                Datasets::SALES => $row['sales'] !== null,
                Datasets::BIKES => $row['bike_parking'] !== null,
            ];
            $used = [];
            foreach ($present as $name => $has) {
                if ($has) {
                    $used[] = $datasets[$name];
                }
            }
            foreach ($targets as [$station, $uic]) {
                if (isset($rows[$station->id])) {
                    $duplicates[] = [$gareId, $uic, $station->slug];

                    continue;
                }
                $rows[$station->id] = [
                    'station_id' => $station->id, 'uic_code' => $uic, ...$row,
                    'amenities' => json_encode($row['amenities']),
                    'elevators' => $row['elevators'] !== null ? json_encode($row['elevators'], JSON_UNESCAPED_UNICODE) : null,
                    'assistance' => $row['assistance'] !== null ? json_encode($row['assistance'], JSON_UNESCAPED_UNICODE) : null,
                    'sales' => $row['sales'] !== null ? json_encode($row['sales']) : null,
                    'bike_parking' => $row['bike_parking'] !== null ? json_encode($row['bike_parking']) : null,
                    'datasets' => json_encode($used),
                    'source' => 'sncf', 'computed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row;
        }
        $rows = $list;

        $this->report($stations, $rows, $unmatched, $duplicates);

        if ($this->option('dry-run')) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            DB::table('station_accessibility')->where('source', 'sncf')->delete();
            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table('station_accessibility')->insert($chunk);
            }
        });
        $this->info('Écrit : '.count($rows).' gares.');

        return self::SUCCESS;
    }

    /**
     * @param  iterable<object>  $stations
     * @param  list<array<string, mixed>>  $rows
     * @param  list<list<mixed>>  $unmatched
     * @param  list<list<mixed>>  $duplicates
     */
    private function report(iterable $stations, array $rows, array $unmatched, array $duplicates): void
    {
        $byStation = [];
        foreach ($rows as $r) {
            $byStation[$r['station_id']] = $r;
        }
        $count = fn (callable $has): int => count(array_filter($rows, $has));
        $this->line('');
        $this->line(sprintf('Lignes à écrire dans station_accessibility : %d (aujourd\'hui en base : %d).', count($rows),
            Schema::hasTable('station_accessibility') ? (int) DB::table('station_accessibility')->where('source', 'sncf')->count() : 0));
        $this->line(sprintf('  accès pour les personnes à mobilité réduite : %d accessibles en partie, %d non déclarées accessibles',
            $count(fn ($r) => $r['mobility'] === 'partial'), $count(fn ($r) => $r['mobility'] === 'none')));
        $this->line(sprintf('  ascenseurs recensés : %d gares ; état publié : %d gares ; assistance : %d ; points de vente : %d ; vélos (au moins une place) : %d ; équipements : %d',
            $count(fn ($r) => ($r['lifts'] ?? 0) > 0), $count(fn ($r) => $r['elevators'] !== null), $count(fn ($r) => $r['assistance'] !== null),
            $count(fn ($r) => $r['sales'] !== null), $count(fn ($r) => $r['bike_parking'] !== null && str_contains((string) $r['bike_parking'], '"places":0') === false), $count(fn ($r) => $r['amenities'] !== '[]')));
        $this->line(sprintf('Gares du jeu sans gare de VFF : %d (voir non-rattachees.csv) ; doublons écartés : %d.', count($unmatched), count($duplicates)));

        $open = ['active' => [0, 0], 'tourist' => [0, 0], 'no_commercial' => [0, 0], 'declassified' => [0, 0]];
        $missing = [];
        $total = 0;
        foreach ($stations as $s) {
            $total++;
            $has = isset($byStation[$s->id]);
            if (isset($open[$s->status])) {
                $open[$s->status][0]++;
                $open[$s->status][1] += $has ? 1 : 0;
            }
            if (! $has && in_array($s->status, ['active', 'tourist'], true)) {
                $missing[] = [$s->slug, $s->name, $s->status, (string) $s->uic_code, (string) $s->department_code, (string) $s->city];
            }
        }
        $this->line('');
        $this->line("Couverture (gares de VFF, {$total} fiches) :");
        foreach ($open as $status => [$all, $with]) {
            $this->line(sprintf('  %-13s %5d gares, %5d avec des informations (%.1f %%)', $status, $all, $with, $all > 0 ? 100 * $with / $all : 0));
        }
        $this->line(sprintf('Gares ouvertes (active, tourist) sans information : %d (voir sans-information.csv)', count($missing)));

        if ($dir = rtrim((string) $this->option('csv'), '/')) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $this->csv($dir.'/non-rattachees.csv', ['id_gare', 'codes_uic', 'nom'], $unmatched);
            $this->csv($dir.'/doublons.csv', ['id_gare', 'uic', 'gare_vff'], $duplicates);
            $this->csv($dir.'/sans-information.csv', ['slug', 'nom', 'statut', 'uic', 'departement', 'commune'], $missing);
            $this->line("Rapports : {$dir}");
        }
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     */
    private function csv(string $path, array $header, array $rows): void
    {
        $h = fopen($path, 'w');
        fputcsv($h, $header, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($h, $r, ',', '"', '');
        }
        fclose($h);
    }
}
