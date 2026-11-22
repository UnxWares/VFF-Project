<?php

namespace App\Console\Commands;

use App\Services\Sncf\RadioSystems;
use App\Services\Sncf\SncfOpenDataCache;
use App\Services\Sncf\TrackRegime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import du jeu SNCF Open Data « Lignes équipées de liaison radio sol-train » (SNCF Réseau, ODbL) : système radio
 * sol-train par portion de PK (GSM-R, GSM en groupe fermé d'utilisateurs, radio sol-train analogique), dans
 * `line_radio_portions`. Chaque tronçon du jeu est rattaché aux lignes de VFF de même code ; un libellé inconnu
 * est écarté et signalé. Toutes les portions `sncf` sont remplacées d'un coup, dans une transaction ; aucune autre
 * table n'est écrite.
 *
 * Le jeu décrit le réseau actuel : aucune ère. Il est téléchargé une fois (licence et éditeur vérifiés, cache
 * storage/app/sources/sncf-radio/, dossier `.nobackup`) ; --refresh le télécharge de nouveau.
 *
 *   php artisan vff:import-sncf-radio --dry-run             # mesure, aucune écriture
 *   php artisan vff:import-sncf-radio --dry-run --csv=/chemin/rapport.csv
 *   php artisan vff:import-sncf-radio                       # écrit
 */
class ImportSncfRadio extends Command
{
    protected $signature = 'vff:import-sncf-radio
        {--dry-run : rapport seulement, aucune écriture en base}
        {--refresh : télécharge de nouveau le jeu au lieu du cache local}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-radio)}
        {--csv= : rapport détaillé par ligne, au format CSV}';

    protected $description = 'Importe la liaison radio sol-train SNCF Réseau (GSM-R et autres systèmes) par portion de PK';

    private const FIELDS = ['code_ligne', 'lib_ligne', 'rst', 'rg_troncon', 'pkd', 'pkf'];

    public function handle(SncfOpenDataCache $cache): int
    {
        try {
            $data = $cache->load(RadioSystems::DATASET, self::FIELDS,
                (string) ($this->option('cache-dir') ?: storage_path('app/sources/sncf-radio')), (bool) $this->option('refresh'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $records = $data['records'];
        $summary = RadioSystems::summarize($records);
        $this->info(sprintf('%d enregistrements, %d codes de ligne (jeu modifié le %s, licence %s).',
            count($records), count($summary), $data['modified'] ?? '?', $data['license']));

        $unknown = array_count_values(array_map(fn ($r) => (string) $r['rst'],
            array_filter($records, fn ($r) => RadioSystems::system($r['rst'] ?? null) === null)));
        $lines = DB::table('lines')->whereIn('code', array_keys($summary))->get(['id', 'slug', 'code'])->groupBy('code');

        $now = now();
        $portions = [];
        foreach ($records as $r) {
            $code = trim((string) ($r['code_ligne'] ?? ''));
            $system = RadioSystems::system($r['rst'] ?? null);
            if ($system === null || ! isset($lines[$code])) {
                continue;
            }
            foreach ($lines[$code] as $line) {
                $portions[] = [
                    'line_id' => $line->id, 'code_ligne' => $code,
                    'rg_troncon' => isset($r['rg_troncon']) ? (int) $r['rg_troncon'] : null,
                    'pk_start_m' => TrackRegime::pkMeters($r['pkd'] ?? null), 'pk_end_m' => TrackRegime::pkMeters($r['pkf'] ?? null),
                    'system' => $system, 'label' => mb_substr(trim((string) $r['rst']), 0, 120), 'source' => 'sncf',
                    'dataset_modified' => $data['modified'], 'fetched_at' => $data['fetched_at'] ?? $now,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        $byLine = [];
        $kmBySystem = array_fill_keys(RadioSystems::SYSTEMS, 0.0);
        $rows = [];
        foreach ($lines as $code => $group) {
            foreach ($group as $line) {
                $byLine[(int) $line->id] = $summary[$code];
                $rows[] = [$line->slug, $code, ...array_map(fn ($s) => $summary[$code][$s] ?? '', RadioSystems::SYSTEMS)];
            }
            foreach ($summary[$code] as $s => $km) {
                $kmBySystem[$s] += $km;
            }
        }
        $withGsmR = count(array_filter($byLine, fn ($s) => isset($s['gsm-r'])));
        $partialGsmR = count(array_filter($byLine, fn ($s) => isset($s['gsm-r']) && count($s) > 1));
        // Table absente tant que sa migration n'est pas appliquée : la passe à blanc la mesure quand même.
        $already = Schema::hasTable('line_radio_portions') ? (int) DB::table('line_radio_portions')->where('source', 'sncf')->count() : 0;
        $unmatched = array_values(array_diff(array_keys($summary), $lines->keys()->all()));

        $this->line('');
        $this->line(sprintf('Lignes touchées : %d (codes du jeu sans ligne dans VFF : %d)', count($byLine), count($unmatched)));
        $this->line(sprintf('Portions écrites dans line_radio_portions : %d (aujourd\'hui en base : %d)', count($portions), $already));
        $this->line(sprintf('Lignes équipées du GSM-R : %d, dont %d en partie seulement (autre système sur le reste)', $withGsmR, $partialGsmR));
        $this->line('Longueur par système (km, codes rattachés) : '.implode(', ', array_map(fn ($s, $km) => "{$s} {$km}",
            array_keys($kmBySystem), array_map(fn ($k) => round($k, 1), $kmBySystem))));
        foreach ($unknown as $label => $n) {
            $this->warn("  libellé inconnu, écarté : « {$label} » ({$n})");
        }

        if ($csv = $this->option('csv')) {
            $h = fopen((string) $csv, 'w');
            fputcsv($h, ['slug', 'code', ...array_map(fn ($s) => "km_{$s}", RadioSystems::SYSTEMS)], ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($h, $r, ',', '"', '');
            }
            fclose($h);
            $this->line("Rapport : {$csv}");
        }

        if ($this->option('dry-run')) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($portions) {
            DB::table('line_radio_portions')->where('source', 'sncf')->delete();
            foreach (array_chunk($portions, 500) as $chunk) {
                DB::table('line_radio_portions')->insert($chunk);
            }
        });
        $this->info('Écrit : '.count($portions).' portions.');

        return self::SUCCESS;
    }
}
