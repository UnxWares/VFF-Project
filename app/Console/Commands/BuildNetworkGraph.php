<?php

namespace App\Console\Commands;

use App\Services\NetworkGraph\EraGraphBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Graphe ferroviaire PAR DÉCENNIE, à partir des portions en service de chaque
 * ère (line_segments du moteur décennal) et des gares ouvertes à cette ère
 * (station_eras) : topologie (network_nodes / network_edges) et graphe
 * gare-à-gare (station_edges) utilisé par /api/v1/route. Détail de la
 * méthode : App\Services\NetworkGraph\EraGraphBuilder.
 *
 * Rejouable (le graphe d'une ère est remplacé en une transaction) et
 * parallélisable par ère :
 *
 *   php artisan vff:build-network-graph                    # toutes les ères
 *   php artisan vff:build-network-graph --era=1930,1980
 *   php artisan vff:build-network-graph --shard=2/6        # ères de rang % 6 = 1
 *
 * À relancer après vff:build-era-segments puis vff:stations-pipeline. Les
 * arrêts par ligne (line_stops) ne sont plus construits ici mais par
 * vff:build-station-eras.
 */
class BuildNetworkGraph extends Command
{
    protected $signature = 'vff:build-network-graph
        {--era= : Ère(s) : année ou liste « 1930,1980 » (défaut : toutes)}
        {--shard= : Traitement parallèle « i/n » : ères (ordre chronologique) de rang % n = i-1}
        {--snap-m= : Fusion des extrémités et jonctions en T (m, défaut 15)}
        {--gap-any-m= : Raccord de lacune vers une autre ligne (m, défaut 60)}
        {--gap-same-m= : Raccord de lacune sur la même ligne (m, défaut 300)}
        {--attach-near-m= : Gare rattachée à toute voie non LGV à moins de (m, défaut 30)}
        {--attach-line-m= : Gare rattachée aux voies de ses lignes à moins de (m, défaut 1000)}
        {--attach-fallback-m= : Gare sans autre rattachement : voie la plus proche à moins de (m, défaut 300)}
        {--non-passenger-factor= : Pénalité des voies sans voyageurs entre deux gares (défaut 4)}';

    protected $description = 'Graphe ferroviaire par décennie : topologie + gare-à-gare (station_edges) + mesures.';

    public function handle(): int
    {
        ini_set('memory_limit', '2G');
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year']);
        if ($this->option('era') !== null) {
            $years = array_map('intval', array_filter(explode(',', (string) $this->option('era'))));
            $unknown = array_diff($years, $eras->pluck('year')->all());
            if ($unknown) {
                $this->error('Ère inconnue : '.implode(', ', $unknown));

                return self::INVALID;
            }
            $eras = $eras->whereIn('year', $years)->values();
        }
        if ($shard = $this->option('shard')) {
            [$i, $n] = array_map('intval', explode('/', $shard) + [1 => 0]);
            if ($n < 1 || $i < 1 || $i > $n) {
                $this->error("Shard invalide : {$shard} (attendu i/n, 1 ≤ i ≤ n)");

                return self::INVALID;
            }
            $eras = $eras->filter(fn ($e, $k) => $k % $n === $i - 1)->values();
        }

        $builder = new EraGraphBuilder(array_filter([
            'snap_m' => $this->option('snap-m'),
            'gap_any_m' => $this->option('gap-any-m'),
            'gap_same_m' => $this->option('gap-same-m'),
            'attach_near_m' => $this->option('attach-near-m'),
            'attach_line_m' => $this->option('attach-line-m'),
            'attach_fallback_m' => $this->option('attach-fallback-m'),
            'non_passenger_factor' => $this->option('non-passenger-factor'),
        ], fn ($v) => $v !== null));

        $rows = [];
        foreach ($eras as $era) {
            $s = $builder->build((int) $era->id, (int) $era->year);
            $rows[] = [
                $era->year, $s['segments'], $s['nodes'], $s['edges'], $s['station_nodes'].' / '.$s['open_stations'],
                $s['station_edges'], $s['components'], $s['largest_component'], $s['isolated_stations'],
                $s['unattached_stations'], $s['gap_joins'], $s['stations_source'] === 'station_eras' ? '' : 'dates des gares',
                sprintf('%.1f s', $s['duration_ms'] / 1000),
            ];
            $this->line(sprintf('  %d : %d nœuds, %d arêtes, %d arêtes gare-à-gare, %d composantes (%.1f s)',
                $era->year, $s['nodes'], $s['edges'], $s['station_edges'], $s['components'], $s['duration_ms'] / 1000));
        }

        $this->table(
            ['ère', 'tronçons', 'nœuds', 'arêtes', 'gares reliées / ouvertes', 'arêtes gare-à-gare', 'composantes',
                'plus grande', 'gares isolées', 'gares non reliées', 'raccords de lacunes', 'gares selon', 'durée'],
            $rows,
        );
        $this->info(sprintf('Graphe construit pour %d ère(s).', count($rows)));

        return self::SUCCESS;
    }
}
