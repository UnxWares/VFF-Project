<?php

namespace App\Console\Commands;

use App\Services\Profiles\ProfileContinuity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lignes dont le tracé de référence n'est pas continu sous le profil en long : vrais trous du tracé
 * (ProfileContinuity, trous conventionnels des embranchements exclus), avec leur nombre, leur longueur
 * à vol d'oiseau et la part du parcours qu'ils représentent, plus les lignes sans profil du tout.
 * Lecture seule : rien n'est écrit en base.
 *
 *   php artisan vff:profile-trace-holes                      # bilan et les 30 lignes aux plus grands trous
 *   php artisan vff:profile-trace-holes --csv=trous.csv      # liste complète, un trou par ligne du CSV
 */
class ProfileTraceHoles extends Command
{
    protected $signature = 'vff:profile-trace-holes
        {--min-m=0 : ne compter que les trous d\'au moins cette longueur (mètres)}
        {--top=30 : nombre de lignes affichées, par longueur de trous décroissante}
        {--csv= : liste complète au format CSV (un trou par ligne)}';

    protected $description = 'Lignes dont le tracé de référence a des trous sous le profil en long (nombre, longueur, part), et lignes sans profil';

    public function handle(): int
    {
        $min = max(0, (int) $this->option('min-m'));
        $rows = DB::table('line_profiles as p')->join('lines as l', 'l.id', '=', 'p.line_id')
            ->orderBy('l.slug')
            ->get(['l.slug', 'l.name', 'p.length_m', 'p.covered_m', 'p.gaps', 'p.branches', 'p.max_grade_withheld']);

        $lines = [];
        foreach ($rows as $r) {
            $summary = ProfileContinuity::summary(
                (array) json_decode((string) $r->gaps, true),
                (array) json_decode((string) $r->branches, true),
                $r->covered_m === null ? null : (int) $r->covered_m,
                $min,
            );
            if ($summary['count'] > 0) {
                $lines[] = ['slug' => (string) $r->slug, 'name' => (string) $r->name, 'holes' => $summary['ranges'],
                    'length_m' => $summary['length_m'], 'share' => $summary['share'], 'withheld' => $r->max_grade_withheld];
            }
        }
        $missing = DB::table('lines as l')->leftJoin('line_profiles as p', 'p.line_id', '=', 'l.id')->whereNull('p.id')->count();

        $count = array_sum(array_map(fn (array $l): int => count($l['holes']), $lines));
        $km = array_sum(array_column($lines, 'length_m')) / 1000;
        $over5 = array_sum(array_map(fn (array $l): int => count(array_filter($l['holes'], fn (array $h): bool => $h['to_m'] - $h['from_m'] > 5000)), $lines));
        $large = count(array_filter($lines, fn (array $l): bool => ($l['share'] ?? 0) >= 0.25));
        $this->table(['', 'Valeur'], [
            ['Profils', count($rows)],
            ['Lignes à trous', count($lines)],
            ['Trous', $count],
            ['km de trous (à vol d\'oiseau)', number_format($km, 0, ',', ' ')],
            ['Trous de plus de 5 km', $over5],
            ['Lignes dont les trous font 25 % du parcours ou plus', $large],
            ['Lignes sans profil', $missing],
        ]);

        usort($lines, fn (array $a, array $b): int => $b['length_m'] <=> $a['length_m'] ?: strcmp($a['slug'], $b['slug']));
        $this->table(['Ligne', 'Nom', 'Trous', 'km', 'Part'], array_map(fn (array $l): array => [
            $l['slug'], mb_strimwidth($l['name'], 0, 60, '…'), count($l['holes']), number_format($l['length_m'] / 1000, 1, ',', ''),
            $l['share'] === null ? '-' : number_format($l['share'] * 100, 1, ',', '').' %',
        ], array_slice($lines, 0, max(0, (int) $this->option('top')))));

        if ($this->option('csv')) {
            $handle = fopen((string) $this->option('csv'), 'w');
            if ($handle === false) {
                $this->warn($this->option('csv').' : écriture impossible.');

                return self::FAILURE;
            }
            fputcsv($handle, ['slug', 'name', 'holes', 'holes_length_m', 'holes_share', 'max_grade_withheld', 'hole_from_m', 'hole_to_m'], escape: '');
            foreach ($lines as $l) {
                foreach ($l['holes'] as $h) {
                    fputcsv($handle, [$l['slug'], $l['name'], count($l['holes']), $l['length_m'], $l['share'], $l['withheld'], $h['from_m'], $h['to_m']], escape: '');
                }
            }
            fclose($handle);
            $this->info($this->option('csv').' écrit.');
        }

        return self::SUCCESS;
    }
}
