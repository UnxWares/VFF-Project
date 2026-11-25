<?php

namespace App\Console\Commands;

use App\Models\Line;
use App\Services\Rinf\RinfParameterComparison;
use App\Services\Rinf\RinfParameters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapport des écarts entre les paramètres déclarés au RINF et les autres sources de VFF (RinfParameterComparison),
 * ligne par ligne : kilomètres d'accord, de désaccord et d'absence de chaque côté, par paramètre, et quais
 * rapprochés gare par gare. LECTURE SEULE : aucune écriture en base (pas de --dry-run à prévoir).
 *
 *   php artisan vff:compare-rinf-parameters                       # France entière, résumé
 *   php artisan vff:compare-rinf-parameters --line=570000 --csv=/tmp/ecarts.csv
 *
 * Le CSV a une ligne par portion (paramètre, PK, statut, valeurs des deux côtés) ; les quais vont dans un second
 * fichier, même nom suivi de « -quais ».
 */
class CompareRinfParameters extends Command
{
    protected $signature = 'vff:compare-rinf-parameters
        {--line= : une seule ligne (code SNCF Réseau à 6 chiffres)}
        {--csv= : portions comparées, au format CSV (et quais dans <nom>-quais.csv)}';

    protected $description = 'Compare les paramètres du RINF (électrification, vitesse, contrôle de vitesse, GSM-R, quais) aux autres sources de VFF, par PK (lecture seule)';

    public function handle(RinfParameterComparison $comparison): int
    {
        if (! RinfParameterComparison::ready()) {
            $this->error('Tables du RINF ou colonnes des paramètres absentes : migrez puis lancez vff:import-rinf.');

            return self::FAILURE;
        }
        $codes = DB::table('rinf_sections')->select('line_code')->distinct()->orderBy('line_code')->pluck('line_code')->all();
        if ($this->option('line') !== null) {
            $codes = array_values(array_intersect($codes, [trim((string) $this->option('line'))]));
        }
        $lines = Line::query()->whereIn('code', $codes)->orderBy('code')->orderBy('id')->get()->unique('code');

        $csv = $this->option('csv') ? (string) $this->option('csv') : null;
        $out = $csv !== null ? fopen($csv, 'w') : false;
        $quais = $csv !== null ? fopen((string) preg_replace('/(\.csv)?$/', '-quais.csv', $csv, 1), 'w') : false;
        if ($out !== false) {
            fputcsv($out, ['code', 'line', 'parameter', 'rg_troncon', 'pk_start_m', 'pk_end_m', 'pk_start', 'pk_end', 'length_m', 'status',
                'rinf_key', 'vff_key', 'rinf_values', 'vff_values', 'rinf_tracks', 'vff_label'], ',', '"', '');
        }
        if ($quais !== false) {
            fputcsv($quais, ['code', 'line', 'pk_m', 'station', 'uopid', 'ref', 'track', 'status', 'rinf_length_m', 'rinf_height_mm',
                'vff_label', 'vff_length_m', 'vff_pk_start_m'], ',', '"', '');
        }

        $total = [];
        $platforms = ['rinf' => 0, 'vff' => 0, 'agree' => 0, 'disagree' => 0, 'rinf_only' => 0, 'vff_only' => 0, RinfParameterComparison::PLATFORM_LENGTH_UNKNOWN => 0];
        $worst = [];
        /** @var array<string, array<string, int>> $systems système => '' (aucun des deux), r, v, rv => mètres */
        $systems = [];
        $seen = [];
        $bar = $this->output->createProgressBar($lines->count());
        foreach ($lines as $line) {
            $result = $comparison->forLine($line);
            foreach ($result['parameters'] as $p) {
                foreach ($p['summary'] as $k => $m) {
                    $total[$p['key']][$k] = ($total[$p['key']][$k] ?? 0) + $m;
                }
                foreach ($p['portions'] as $portion) {
                    if ($out !== false) {
                        fputcsv($out, [$line->code, $line->slug, $p['key'], $portion['rg_troncon'] ?? '', $portion['pk_start_m'], $portion['pk_end_m'], $portion['pk_start_label'], $portion['pk_end_label'],
                            $portion['length_m'], $portion['status'], $portion['rinf']['key'] ?? '', $portion['vff']['key'] ?? '',
                            implode(' ', $portion['rinf']['values'] ?? []), implode(' ', $portion['vff']['values'] ?? []),
                            implode(' ', $portion['rinf']['tracks'] ?? []), $portion['vff']['source_label'] ?? ''], ',', '"', '');
                    }
                    if ($p['key'] === 'train_protection' && $portion['rinf'] !== null && $portion['vff'] !== null) {
                        $r = $portion['rinf']['key'] === 'none' ? [] : explode('+', $portion['rinf']['key']);
                        $v = $portion['vff']['key'] === 'none' ? [] : explode('+', $portion['vff']['key']);
                        foreach (RinfParameters::COMPARED_PROTECTION as $system) {
                            $side = (in_array($system, $r, true) ? 'r' : '').(in_array($system, $v, true) ? 'v' : '');
                            $systems[$system][$side] = ($systems[$system][$side] ?? 0) + $portion['length_m'];
                        }
                    }
                    if ($portion['status'] === 'disagree') {
                        $worst[$p['key']][] = [$portion['length_m'], $line->code.' PK '.$portion['pk_start_label'].' à '.$portion['pk_end_label']
                            .' : RINF '.$portion['rinf']['key'].', SNCF '.$portion['vff']['key']];
                    }
                }
            }
            // Quais : une gare de bifurcation est servie par chacune de ses lignes ; comptée une fois.
            foreach ($result['platforms']['items'] as $q) {
                $id = ($q['operational_point']['uopid'] ?? '').'|'.($q['station']['slug'] ?? '').'|'.$q['ref'].'|'.($q['vff']['pk_start_m'] ?? '').'|'.($q['vff']['label'] ?? '');
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $platforms[$q['status']]++;
                    $platforms['rinf'] += $q['rinf'] !== null ? 1 : 0;
                    $platforms['vff'] += $q['vff'] !== null ? 1 : 0;
                }
            }
            if ($quais !== false) {
                foreach ($result['platforms']['items'] as $q) {
                    fputcsv($quais, [$line->code, $line->slug, $q['pk_m'], $q['station']['name'] ?? '', $q['operational_point']['uopid'] ?? '', $q['ref'], $q['track'] ?? '',
                        $q['status'], $q['rinf']['length_m'] ?? '', $q['rinf']['height_mm'] ?? '', $q['vff']['label'] ?? '', $q['vff']['length_m'] ?? '',
                        $q['vff']['pk_start_m'] ?? ''], ',', '"', '');
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        if ($out !== false) {
            fclose($out);
        }
        if ($quais !== false) {
            fclose($quais);
        }

        $km = fn (int $m): string => number_format($m / 1000, 0, ',', ' ');
        $this->line(sprintf('%d lignes comparées.', $lines->count()));
        $this->table(['paramètre', 'source', 'RINF km', 'autre km', 'accord', 'désaccord', 'RINF seul', 'autre seule', 'accord / comparable'],
            array_map(fn (string $key): array => [
                $key, RinfParameterComparison::COMPARED_WITH[$key] ?? '-', $km($total[$key]['rinf_m'] ?? 0), $km($total[$key]['vff_m'] ?? 0),
                $km($total[$key]['agree_m'] ?? 0), $km($total[$key]['disagree_m'] ?? 0), $km($total[$key]['rinf_only_m'] ?? 0), $km($total[$key]['vff_only_m'] ?? 0),
                ($total[$key]['agree_m'] ?? 0) + ($total[$key]['disagree_m'] ?? 0) > 0
                    ? number_format(100 * ($total[$key]['agree_m'] ?? 0) / (($total[$key]['agree_m'] ?? 0) + ($total[$key]['disagree_m'] ?? 0)), 1, ',', ' ').' %' : '-',
            ], RinfParameterComparison::PARAMETERS));
        $this->line(sprintf('Quais : RINF %d, SNCF Réseau %d ; d\'accord (longueur à %d m près) %d, en désaccord %d, longueur absente d\'un côté %d, RINF seul %d, SNCF seul %d.',
            $platforms['rinf'], $platforms['vff'], RinfParameterComparison::PLATFORM_TOLERANCE_M, $platforms['agree'], $platforms['disagree'],
            $platforms[RinfParameterComparison::PLATFORM_LENGTH_UNKNOWN], $platforms['rinf_only'], $platforms['vff_only']));
        $this->line('Contrôle de vitesse, système par système, là où les deux sources disent quelque chose (km) :');
        $this->table(['système', 'les deux', 'RINF seul', 'SNCF seule', 'aucun des deux'], array_map(fn (string $system): array => [$system,
            $km($systems[$system]['rv'] ?? 0), $km($systems[$system]['r'] ?? 0), $km($systems[$system]['v'] ?? 0), $km($systems[$system][''] ?? 0)],
            RinfParameters::COMPARED_PROTECTION));
        foreach ($worst as $key => $list) {
            usort($list, fn (array $a, array $b): int => $b[0] <=> $a[0]);
            $this->line("Plus longs désaccords ({$key}) :");
            foreach (array_slice($list, 0, 5) as [$m, $text]) {
                $this->line(sprintf('  %s km  %s', number_format($m / 1000, 1, ',', ' '), $text));
            }
        }
        if ($csv !== null) {
            $this->line("Rapport : {$csv}");
        }

        return self::SUCCESS;
    }
}
