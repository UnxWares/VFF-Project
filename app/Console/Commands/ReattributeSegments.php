<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ré-attribue les segments VFF mal classés à la bonne ligne via SNCF.
 *
 * Stratégie optimisée :
 *   1. Pré-filtre bbox via l'index GIST (geom && ST_Expand(sncf_geom, 0.003)
 *      ≈ 300m en degrés) - ultra rapide
 *   2. Puis ST_DWithin en geography pour précision métrique
 *   3. Pour chaque segment, garde le code SNCF le plus proche
 *   4. Réaffecte uniquement si le code SNCF best != code actuel ET que la
 *      ligne actuelle ne couvre pas le segment (sinon = correctement classé)
 */
class ReattributeSegments extends Command
{
    protected $signature = 'vff:reattribute-segments
                            {--era=}
                            {--buffer-m=200 : buffer ST_DWithin précis}
                            {--bbox-deg=0.003 : pré-filtre bbox (degrés)}';

    protected $description = 'Ré-attribue les segments VFF aux bonnes lignes via SNCF Réseau.';

    public function handle(): int
    {
        $eraId = $this->option('era')
            ? DB::table('eras')->where('year', $this->option('era'))->value('id')
            : DB::table('eras')->where('is_current', true)->value('id');

        // Ère inconnue (--era=1855) ou aucune ère courante : erreur propre, aucune écriture.
        if (! $eraId) {
            $this->error('Ère introuvable (option --era ou ère courante).');

            return self::FAILURE;
        }
        $bufferM = (int) $this->option('buffer-m');
        $bboxDeg = (float) $this->option('bbox-deg');

        $this->info("Ré-attribution segments (era_id={$eraId}, buffer {$bufferM}m, bbox ±{$bboxDeg}°)");

        // 1. Build CTE matérialisé : pour chaque segment, le code SNCF le
        //    plus proche dans le buffer. Cette table temporaire est rapide
        //    grâce au pré-filtre bbox (index GIST).
        $this->info('  ► Construction table temporaire des candidats…');
        $start = microtime(true);
        DB::statement('DROP TABLE IF EXISTS tmp_seg_sncf');
        DB::statement('CREATE TEMP TABLE tmp_seg_sncf (
            seg_id BIGINT PRIMARY KEY,
            current_code VARCHAR(20),
            best_sncf_code VARCHAR(20),
            best_dist_m DOUBLE PRECISION,
            current_dist_m DOUBLE PRECISION
        )');

        // Pour chaque segment, candidates pré-filtrées par bbox.
        DB::statement('
            INSERT INTO tmp_seg_sncf (seg_id, current_code, best_sncf_code, best_dist_m, current_dist_m)
            SELECT DISTINCT ON (ls.id)
                ls.id,
                l.code AS current_code,
                sg.code_ligne AS best_sncf_code,
                ST_Distance(ls.geom::geography, sg.geom::geography) AS best_dist_m,
                NULL::double precision AS current_dist_m
            FROM line_segments ls
            JOIN lines l ON l.id = ls.line_id
            JOIN sncf_line_geometries sg
                ON ls.geom && ST_Expand(sg.geom, ?)
            WHERE ls.era_id = ?
              AND l.code IS NOT NULL
              AND ST_DWithin(ls.geom::geography, sg.geom::geography, ?)
            ORDER BY ls.id, ST_Distance(ls.geom::geography, sg.geom::geography) ASC
        ', [$bboxDeg, $eraId, $bufferM]);
        $candidates = DB::selectOne('SELECT COUNT(*) AS n FROM tmp_seg_sncf')->n;
        $this->info("    {$candidates} segments avec ≥1 candidate SNCF (".round(microtime(true) - $start, 1).'s)');

        // 2. Pour chaque segment, calculer la distance à la ligne actuelle (current_code)
        $this->info('  ► Calcul distance à la ligne actuelle…');
        $start = microtime(true);
        DB::statement('
            UPDATE tmp_seg_sncf t SET current_dist_m = (
                SELECT MIN(ST_Distance(ls.geom::geography, sg.geom::geography))
                FROM line_segments ls
                JOIN sncf_line_geometries sg ON sg.code_ligne = t.current_code
                WHERE ls.id = t.seg_id
                  AND ls.geom && ST_Expand(sg.geom, ?)
            )
        ', [$bboxDeg]);
        $this->info('    OK ('.round(microtime(true) - $start, 1).'s)');

        // 3. Sélection des segments à réattribuer :
        //    - le code SNCF best est différent du current
        //    - ET soit current_dist_m est NULL (pas dans le buffer de la ligne actuelle)
        //      soit best_dist_m << current_dist_m (significativement plus proche)
        $toReassign = DB::selectOne('
            SELECT COUNT(*) AS n FROM tmp_seg_sncf
            WHERE current_code != best_sncf_code
              AND (current_dist_m IS NULL OR best_dist_m * 2 < current_dist_m)
        ')->n;
        $this->info("  ► Candidats à réattribuer : {$toReassign}");

        // 4. Apply : update line_id
        $this->info('  ► Application…');
        $start = microtime(true);
        $applied = DB::update('
            UPDATE line_segments ls
            SET line_id = l_new.id, updated_at = NOW()
            FROM tmp_seg_sncf t
            JOIN lines l_new ON l_new.code = t.best_sncf_code
            WHERE ls.id = t.seg_id
              AND t.current_code != t.best_sncf_code
              AND (t.current_dist_m IS NULL OR t.best_dist_m * 2 < t.current_dist_m)
        ');
        $this->info("    ✓ {$applied} segments réattribués (".round(microtime(true) - $start, 1).'s)');

        // 5. Stats par ligne impactée
        $impacted = DB::select('
            SELECT l.slug, l.name, COUNT(*) AS lost
            FROM tmp_seg_sncf t
            JOIN lines l ON l.code = t.current_code
            WHERE t.current_code != t.best_sncf_code
              AND (t.current_dist_m IS NULL OR t.best_dist_m * 2 < t.current_dist_m)
            GROUP BY l.id, l.slug, l.name
            ORDER BY lost DESC LIMIT 10
        ');
        if ($impacted) {
            $this->info(PHP_EOL.'Top 10 lignes ayant perdu le plus de segments :');
            foreach ($impacted as $i) {
                $this->line(sprintf('  %-15s %5d segments → %s', $i->slug, $i->lost, mb_substr($i->name, 0, 50)));
            }
        }

        DB::statement('DROP TABLE IF EXISTS tmp_seg_sncf');

        return self::SUCCESS;
    }
}
