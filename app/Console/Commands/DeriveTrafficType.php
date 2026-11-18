<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DeriveTrafficType extends Command
{
    protected $signature = 'vff:derive-traffic {--era=}';

    protected $description = 'Dérive le traffic_type des segments depuis la fréquentation voyageurs SNCF';

    public function handle(): int
    {
        $year = $this->option('era');
        $era = $year
            ? Era::where('year', $year)->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $this->info("Dérivation traffic_type, ère={$era->year}");
        $this->line('→ Téléchargement fréquentation-gares SNCF…');

        // Http (et non file_get_contents) : délai borné, erreurs HTTP détectées, et
        // source simulable dans les tests (aucun appel réseau réel).
        $url = 'https://data.sncf.com/api/explore/v2.1/catalog/datasets/frequentation-gares/exports/json';
        try {
            $response = Http::timeout(120)->get($url, ['limit' => -1]);
        } catch (ConnectionException $e) {
            $response = null;
        }
        $stations = $response?->successful() ? $response->json() : null;
        if (! is_array($stations)) {
            $this->error('Échec téléchargement fréquentation-gares');

            return self::FAILURE;
        }
        $this->line('→ '.count($stations).' gares fréquentées récupérées.');

        // 1. Trouve les noms de gares avec > 0 voyageurs en 2024 (ou 2023 fallback)
        $passengerNames = [];
        foreach ($stations as $s) {
            $count = (int) ($s['total_voyageurs_2024'] ?? $s['total_voyageurs_2023'] ?? 0);
            if ($count > 0 && isset($s['nom_gare'])) {
                $passengerNames[mb_strtolower($s['nom_gare'])] = $count;
            }
        }
        $this->line('→ '.count($passengerNames).' noms de gares voyageurs.');

        // Stratégie LIGNE entière : si AU MOINS UNE gare voyageurs est à <1km
        // d'AU MOINS UN segment de la ligne → toute la ligne porte des voyageurs.
        // Sinon tous les segments = 'freight'. Évite le faux-fret entre les gares.

        // 1) Trouve les line_ids ayant au moins 1 gare voyageurs proche
        $paxLineIds = $passengerNames === [] ? [] : array_column(DB::select(
            'SELECT DISTINCT ls.line_id
             FROM line_segments ls
             JOIN stations s ON
                 LOWER(s.name) IN ('.implode(',', array_fill(0, count($passengerNames), '?')).')
                 AND s.geom && ST_Expand(ls.geom, 0.01)
                 AND ST_DWithin(s.geom::geography, ls.geom::geography, 1000)
             WHERE ls.era_id = ?',
            [...array_map('strval', array_keys($passengerNames)), $era->id],
        ), 'line_id');
        $this->line('→ Lines avec gares voyageurs : '.count($paxLineIds));

        // 2) Tronçons actifs → 'freight' par défaut ; un tronçon fermé ou déposé ne
        //    porte pas de trafic (invariant segments.inactive_with_traffic).
        DB::update(
            "UPDATE line_segments SET traffic_type = CASE WHEN status IN ('in_service', 'single_track') THEN 'freight' ELSE 'unknown' END
             WHERE era_id = ?",
            [$era->id],
        );

        // 3) Lignes avec gares voyageurs : voyageurs ⇒ mixte, sauf LGV (voyageurs seuls).
        $upgraded = 0;
        if (! empty($paxLineIds)) {
            $upgraded = DB::update(
                'UPDATE line_segments ls SET traffic_type = CASE WHEN '.Lgv::sql('l', 'ls')." THEN 'passenger' ELSE 'mixed' END
                 FROM lines l
                 WHERE l.id = ls.line_id AND ls.era_id = ? AND ls.status IN ('in_service', 'single_track')
                   AND ls.line_id IN (".implode(',', array_map('intval', $paxLineIds)).')',
                [$era->id],
            );
        }

        $this->info("✓ {$upgraded} segments → voyageurs (mixte, ou voyageurs seuls sur LGV).");

        // Stats
        $counts = DB::select(
            'SELECT traffic_type, COUNT(*) c FROM line_segments WHERE era_id = ? GROUP BY traffic_type ORDER BY c DESC',
            [$era->id],
        );
        foreach ($counts as $r) {
            $this->line("  - {$r->traffic_type}: {$r->c}");
        }

        return self::SUCCESS;
    }
}
