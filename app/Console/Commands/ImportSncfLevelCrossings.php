<?php

namespace App\Console\Commands;

use App\Services\LevelCrossings\LevelCrossingTiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importe les passages à niveau du RFN (SNCF Open Data,
 * « liste-des-passages-a-niveau », ODbL) dans sncf_level_crossings.
 *
 * Pourquoi : chaque PN a un PK officiel ET une position précise. Avec les
 * gares (sncf_gares), ce sont les points de contrôle qui permettent au
 * résolveur de sections (vff:resolve-event-sections) de caler un PK sur le
 * tracé par interpolation - le PK n'est PAS la distance géométrique.
 *
 * Idempotent : upsert sur id_if (identifiant d'installation fixe SNCF).
 *
 *   php artisan vff:import-sncf-level-crossings
 */
class ImportSncfLevelCrossings extends Command
{
    protected $signature = 'vff:import-sncf-level-crossings';

    protected $description = 'Importe les passages à niveau SNCF (PK + coordonnées), points de contrôle PK.';

    private const URL = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/liste-des-passages-a-niveau/exports/json';

    private const UA = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public function handle(): int
    {
        $this->info('→ liste-des-passages-a-niveau…');
        $select = 'id_if,libelle,mnemo,obstacle,code_ligne,rg_troncon,pk,x_wgs84,y_wgs84,saco_libelle_commune';
        $res = Http::withUserAgent(self::UA)->timeout(300)
            ->get(self::URL.'?limit=-1&select='.rawurlencode($select));
        if (! $res->ok()) {
            $this->error("SNCF HTTP {$res->status()}");

            return self::FAILURE;
        }

        $rows = [];
        $skipped = 0;
        $now = now();
        foreach ($res->json() ?? [] as $r) {
            $lon = $r['x_wgs84'] ?? null;
            $lat = $r['y_wgs84'] ?? null;
            if (empty($r['id_if']) || empty($r['code_ligne']) || $lon === null || $lat === null) {
                $skipped++;

                continue;
            }
            // ~70 PN apparaissent deux fois (une ligne par voie, PK à quelques
            // mètres près) : on garde la première occurrence.
            if (isset($rows[(string) $r['id_if']])) {
                $skipped++;

                continue;
            }
            $rows[(string) $r['id_if']] = [
                'id_if' => (string) $r['id_if'],
                'libelle' => mb_substr((string) ($r['libelle'] ?? ''), 0, 120) ?: null,
                'mnemo' => mb_substr((string) ($r['mnemo'] ?? ''), 0, 40) ?: null,
                'obstacle' => mb_substr((string) ($r['obstacle'] ?? ''), 0, 160) ?: null,
                'code_ligne' => (string) $r['code_ligne'],
                'rg_troncon' => $r['rg_troncon'] ?? null,
                'pk_m' => $this->parsePk($r['pk'] ?? null),
                'commune' => mb_substr(preg_replace('/\s*\(\d+[AB]?\d*\)$/', '', (string) ($r['saco_libelle_commune'] ?? '')), 0, 120) ?: null,
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            $values = [];
            $bindings = [];
            foreach ($chunk as $row) {
                $values[] = '(?,?,?,?,?,?,?,?,?,?,ST_SetSRID(ST_MakePoint(?,?),4326),?,?)';
                array_push($bindings, $row['id_if'], $row['libelle'], $row['mnemo'], $row['obstacle'],
                    $row['code_ligne'], $row['rg_troncon'], $row['pk_m'], $row['commune'],
                    $row['lat'], $row['lon'], $row['lon'], $row['lat'], $row['created_at'], $row['updated_at']);
            }
            DB::statement(
                'INSERT INTO sncf_level_crossings (id_if, libelle, mnemo, obstacle, code_ligne, rg_troncon, pk_m, commune, lat, lon, geom, created_at, updated_at)
                 VALUES '.implode(',', $values).'
                 ON CONFLICT (id_if) DO UPDATE SET libelle = EXCLUDED.libelle, mnemo = EXCLUDED.mnemo,
                    obstacle = EXCLUDED.obstacle, code_ligne = EXCLUDED.code_ligne, rg_troncon = EXCLUDED.rg_troncon,
                    pk_m = EXCLUDED.pk_m, commune = EXCLUDED.commune, lat = EXCLUDED.lat, lon = EXCLUDED.lon,
                    geom = EXCLUDED.geom, updated_at = EXCLUDED.updated_at',
                $bindings,
            );
        }

        // Les tuiles de la carte ne sont plus celles d'avant l'import.
        app(LevelCrossingTiles::class)->invalidate();

        $stats = DB::selectOne('SELECT COUNT(*) n, COUNT(DISTINCT code_ligne) lignes, COUNT(pk_m) avec_pk FROM sncf_level_crossings');
        $this->info("✓ {$stats->n} PN ({$stats->avec_pk} avec PK) sur {$stats->lignes} lignes · {$skipped} ignorés (sans coordonnées ou doublons)");
        // Les passages à niveau calent les PK de la signalisation sur la carte.
        $this->line('Pour recaler la signalisation sur ces PN : php artisan vff:refresh-signalling-map');

        return self::SUCCESS;
    }

    /**
     * PK SNCF « 019+663 » → 19663 m, « 000-771 » → -771 m.
     */
    private function parsePk(?string $raw): ?int
    {
        if (! $raw || ! preg_match('/^(\d+)([+\-])(\d+)/', trim($raw), $m)) {
            return null;
        }
        $base = (int) $m[1] * 1000;
        $off = (int) $m[3];

        return $m[2] === '-' ? $base - $off : $base + $off;
    }
}
