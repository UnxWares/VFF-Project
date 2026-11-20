<?php

namespace App\Console\Commands;

use App\Enums\SegmentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Audit ET fix complet des tracés VFF en utilisant SNCF Réseau Open Data
 * comme source de vérité.
 *
 * Workflow :
 *   1. Création des lignes manquantes (codes_ligne SNCF absents de VFF)
 *   2. Renommage des lignes mal nommées (lib_ligne SNCF officielle)
 *   3. Ré-attribution des segments mal classés (sur la mauvaise ligne)
 *   4. Mise à jour status segments depuis SNCF libelle (Exploitée/Neutralisée/Déposée)
 *   5. Rapport final
 *
 * Pré-requis : sncf_line_geometries doit être peuplé via
 *   `vff:import-sncf-line-geometries`.
 */
class AuditAndFixLines extends Command
{
    protected $signature = 'vff:audit-and-fix-lines
                            {--era=}
                            {--dry-run : Affiche les corrections sans les appliquer}
                            {--buffer-m=200 : Buffer ST_DWithin pour ré-attribution (m)}';

    protected $description = 'Audit et fix complet des tracés en utilisant SNCF comme vérité.';

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
        $dryRun = $this->option('dry-run');
        $buffer = (int) $this->option('buffer-m');

        $totalSncf = DB::table('sncf_line_geometries')->count();
        if ($totalSncf === 0) {
            $this->error('sncf_line_geometries est vide. Lance d\'abord vff:import-sncf-line-geometries.');

            return self::FAILURE;
        }

        $this->info("Audit & fix VFF (era_id={$eraId}, ".($dryRun ? 'DRY-RUN' : 'APPLY').')');
        $this->info("Référentiel SNCF : {$totalSncf} géométries.".PHP_EOL);

        // ============================================================
        // ÉTAPE 1 - Créer les lignes manquantes
        // ============================================================
        $this->info('► [1/5] Création lignes manquantes…');
        $missingCodes = DB::select('
            SELECT DISTINCT sg.code_ligne
            FROM sncf_line_geometries sg
            WHERE NOT EXISTS (SELECT 1 FROM lines l WHERE l.code = sg.code_ligne)
        ');
        $this->info('  '.count($missingCodes).' codes_ligne absents de VFF');

        // Fetch lib_ligne mapping (depuis cantonnement, plus complet)
        $libMapping = $this->fetchLibMapping();

        $createdLines = 0;
        foreach ($missingCodes as $r) {
            $code = $r->code_ligne;
            $name = $libMapping[$code] ?? "Ligne {$code}";
            if (! $dryRun) {
                DB::table('lines')->insertOrIgnore([
                    'slug' => 'rfn-'.strtolower($code),
                    'code' => $code,
                    'name' => $name,
                    // Trafic inconnu à la création (« voyageurs seuls » réservé aux LGV) :
                    // vff:classify-lines le renseigne ensuite.
                    'type' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $createdLines++;
        }
        $this->info("  → {$createdLines} lignes créées");

        // ============================================================
        // ÉTAPE 2 - Renommer les lignes anonymes (refresh)
        // ============================================================
        $this->info(PHP_EOL.'► [2/5] Backfill noms anonymes…');
        $renamed = 0;
        $anonLines = DB::table('lines')
            ->whereRaw("name ~ '^Ligne [0-9]+$'")
            ->orWhereRaw('name = code')
            ->orWhereRaw("name ~ '^rfn-'")
            ->get(['id', 'code']);
        foreach ($anonLines as $l) {
            if (! $l->code || ! isset($libMapping[$l->code])) {
                continue;
            }
            if (! $dryRun) {
                DB::table('lines')->where('id', $l->id)
                    ->update(['name' => $libMapping[$l->code], 'updated_at' => now()]);
            }
            $renamed++;
        }
        $this->info("  → {$renamed} noms backfillés");

        // ============================================================
        // ÉTAPE 3 - Ré-attribution des segments mal classés
        // ============================================================
        $this->info(PHP_EOL.'► [3/5] Ré-attribution segments mal classés…');
        // Pour chaque segment dont la ligne courante ne couvre PAS sa
        // géométrie via SNCF, chercher un autre code_ligne SNCF qui le couvre
        // dans le buffer.
        if (! $dryRun) {
            // DB::update renvoie le nombre de lignes touchées (l'ancien « SELECT changes() »
            // n'existe qu'en SQLite : la commande plantait sous PostgreSQL).
            $reattributed = DB::update('
                UPDATE line_segments ls SET
                    line_id = best.new_line_id,
                    updated_at = NOW()
                FROM (
                    SELECT ls2.id AS seg_id, l2.id AS new_line_id
                    FROM line_segments ls2
                    JOIN lines l_cur ON l_cur.id = ls2.line_id
                    CROSS JOIN LATERAL (
                        SELECT sg.code_ligne
                        FROM sncf_line_geometries sg
                        WHERE ST_DWithin(ls2.geom::geography, sg.geom::geography, ?)
                        ORDER BY ST_Distance(ls2.geom::geography, sg.geom::geography) ASC
                        LIMIT 1
                    ) sg_match
                    JOIN lines l2 ON l2.code = sg_match.code_ligne
                    WHERE ls2.era_id = ?
                      AND l_cur.code IS NOT NULL
                      AND l_cur.code != sg_match.code_ligne
                      AND NOT EXISTS (
                          SELECT 1 FROM sncf_line_geometries sg_cur
                          WHERE sg_cur.code_ligne = l_cur.code
                            AND ST_DWithin(ls2.geom::geography, sg_cur.geom::geography, ?)
                      )
                ) best
                WHERE ls.id = best.seg_id
            ', [$buffer, $eraId, $buffer]);
        } else {
            $count = DB::selectOne('
                SELECT COUNT(*) AS n FROM line_segments ls2
                JOIN lines l_cur ON l_cur.id = ls2.line_id
                CROSS JOIN LATERAL (
                    SELECT sg.code_ligne FROM sncf_line_geometries sg
                    WHERE ST_DWithin(ls2.geom::geography, sg.geom::geography, ?)
                    ORDER BY ST_Distance(ls2.geom::geography, sg.geom::geography) ASC LIMIT 1
                ) sg_match
                WHERE ls2.era_id = ? AND l_cur.code IS NOT NULL
                  AND l_cur.code != sg_match.code_ligne
                  AND NOT EXISTS (
                      SELECT 1 FROM sncf_line_geometries sg_cur
                      WHERE sg_cur.code_ligne = l_cur.code
                        AND ST_DWithin(ls2.geom::geography, sg_cur.geom::geography, ?)
                  )
            ', [$buffer, $eraId, $buffer])->n;
            $reattributed = "{$count} (dry-run)";
        }
        $this->info("  → {$reattributed} segments ré-attribués");

        // ============================================================
        // ÉTAPE 4 - Status segments depuis SNCF libelle
        // ============================================================
        $this->info(PHP_EOL.'► [4/5] Mise à jour status segments depuis SNCF…');
        // Libellé SNCF → statut : mapping UNIQUE de l'enum (« Fermée et déposée » →
        // dismantled, « Neutralisée » → abandoned…), sur les libellés présents.
        $statusMap = DB::table('sncf_line_geometries')->distinct()->pluck('libelle')->filter()
            ->mapWithKeys(fn ($libelle) => [$libelle => SegmentStatus::fromSncfLibelle($libelle)->value])
            ->all();
        $statusUpdated = 0;
        foreach ($statusMap as $libelle => $vffStatus) {
            if (! $dryRun) {
                // Tronçon devenu inactif : plus de trafic (invariant segments.inactive_with_traffic).
                $traffic = SegmentStatus::from($vffStatus)->isActive() ? 'ls.traffic_type' : "'unknown'";
                $n = DB::update("
                    UPDATE line_segments ls SET status = ?, traffic_type = {$traffic}, updated_at = NOW()
                    FROM lines l, sncf_line_geometries sg
                    WHERE ls.line_id = l.id
                      AND ls.era_id = ?
                      AND l.code = sg.code_ligne
                      AND sg.libelle = ?
                      AND ST_DWithin(ls.geom::geography, sg.geom::geography, ?)
                      AND ls.status != ?
                ", [$vffStatus, $eraId, $libelle, $buffer, $vffStatus]);
                $statusUpdated += $n;
                if ($n > 0) {
                    $this->line("    ✓ {$libelle} → {$vffStatus} : {$n} segments");
                }
            } else {
                $n = DB::selectOne('
                    SELECT COUNT(*) AS n FROM line_segments ls, lines l, sncf_line_geometries sg
                    WHERE ls.line_id = l.id AND ls.era_id = ? AND l.code = sg.code_ligne
                      AND sg.libelle = ? AND ST_DWithin(ls.geom::geography, sg.geom::geography, ?)
                      AND ls.status != ?
                ', [$eraId, $libelle, $buffer, $vffStatus])->n;
                $statusUpdated += $n;
                if ($n > 0) {
                    $this->line("    [dry] {$libelle} → {$vffStatus} : {$n} segments");
                }
            }
        }
        $this->info("  → {$statusUpdated} segments mis à jour");

        // ============================================================
        // ÉTAPE 5 - Rapport final
        // ============================================================
        $this->info(PHP_EOL.'► [5/5] Rapport final');
        $stats = DB::selectOne('
            SELECT
                (SELECT COUNT(*) FROM lines) AS lines_total,
                (SELECT COUNT(*) FROM lines WHERE name ~ \'^Ligne [0-9]+$\') AS anon,
                (SELECT COUNT(*) FROM line_segments WHERE era_id = :era_a) AS segs_total,
                (SELECT COUNT(*) FROM line_segments WHERE era_id = :era_b AND status = \'in_service\') AS in_service,
                (SELECT COUNT(*) FROM line_segments WHERE era_id = :era_c AND status = \'abandoned\') AS abandoned
        ', ['era_a' => $eraId, 'era_b' => $eraId, 'era_c' => $eraId]);
        $this->line("    Total lignes:           {$stats->lines_total}");
        $this->line("    Lignes anonymes:        {$stats->anon}");
        $this->line("    Segments total:         {$stats->segs_total}");
        $this->line("    Segments in_service:    {$stats->in_service}");
        $this->line("    Segments abandoned:     {$stats->abandoned}");

        return self::SUCCESS;
    }

    private function fetchLibMapping(): array
    {
        $mapping = [];
        $offset = 0;
        $limit = 100;
        do {
            $res = Http::timeout(60)
                ->get('https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/mode-de-cantonnement-des-lignes/records',
                    ['limit' => $limit, 'offset' => $offset, 'select' => 'code_ligne,lib_ligne'])
                ->json();
            $records = $res['results'] ?? [];
            if (! $records) {
                break;
            }
            foreach ($records as $r) {
                $code = $r['code_ligne'] ?? null;
                $lib = $r['lib_ligne'] ?? null;
                if ($code && $lib && $lib !== $code) {
                    $mapping[$code] = $lib;
                }
            }
            $offset += $limit;
        } while (count($records) === $limit && $offset < 5000);

        return $mapping;
    }
}
