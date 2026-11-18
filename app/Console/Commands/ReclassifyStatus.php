<?php

namespace App\Console\Commands;

use App\Enums\SegmentStatus;
use App\Models\Era;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Superpose le STATUT officiel SNCF (par tronçon) sur les segments OSM : un
 * segment encore `in_service` mais dont le tronçon SNCF de même code_ligne le
 * plus proche est mort (Fermée/Neutralisée/Déposée/Déclassée…) est reclassé
 * (abandoned/dismantled). Corrige les « lignes mortes affichées en service »
 * (Coulée verte, voies vertes, raccordements neutralisés…).
 *
 * Prudence :
 *   - on ne touche QUE les segments actifs (in_service/single_track) ;
 *   - on ne reclasse PAS si un tronçon SNCF « Exploitée » est aussi proche
 *     (<50 m) → évite de tuer une section réellement exploitée d'un code mixte ;
 *   - on ne « ressuscite » jamais un segment mort (pas de dead→in_service).
 */
class ReclassifyStatus extends Command
{
    protected $signature = 'vff:reclassify-status {--era=} {--dry-run}';

    protected $description = 'Reclasse le statut des segments actifs selon le statut SNCF officiel du tronçon le plus proche.';

    public function handle(): int
    {
        $era = $this->option('era')
            ? Era::where('year', $this->option('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();
        $dry = (bool) $this->option('dry-run');

        // Mapping libellé→status (uniquement les libellés MORTS, depuis l'enum)
        $libelles = DB::table('sncf_line_geometries')->select('libelle')->distinct()->pluck('libelle')->filter();
        $deadMap = [];
        foreach ($libelles as $lib) {
            $st = SegmentStatus::fromSncfLibelle($lib);
            if (! $st->isActive()) {
                $deadMap[$lib] = $st->value;
            }
        }
        if (! $deadMap) {
            $this->warn('Aucun libellé mort.');

            return self::SUCCESS;
        }

        // VALUES (libelle, status) pour jointure SQL
        $values = collect($deadMap)
            ->map(fn ($status, $lib) => '('.DB::getPdo()->quote($lib).','.DB::getPdo()->quote($status).')')
            ->implode(',');

        // Pour chaque segment actif : statut du tronçon SNCF mort le plus proche
        // (même code, <50 m), à condition qu'aucun tronçon Exploitée ne soit aussi proche.
        $sql = <<<SQL
            WITH dead_map(libelle, status) AS (VALUES {$values}),
            seg AS (
                SELECT ls.id, l.code, ls.geom
                FROM line_segments ls JOIN lines l ON l.id = ls.line_id
                WHERE ls.era_id = :era AND ls.status IN ('in_service','single_track')
            ),
            target AS (
                SELECT seg.id, dm.status AS new_status
                FROM seg
                JOIN LATERAL (
                    SELECT sg.libelle
                    FROM sncf_line_geometries sg
                    WHERE sg.code_ligne = seg.code
                      AND ST_DWithin(sg.geom::geography, seg.geom::geography, 50)
                    ORDER BY sg.geom <-> seg.geom
                    LIMIT 1
                ) nearest ON true
                JOIN dead_map dm ON dm.libelle = nearest.libelle
                WHERE NOT EXISTS (
                    SELECT 1 FROM sncf_line_geometries sx
                    WHERE sx.code_ligne = seg.code AND sx.libelle = 'Exploitée'
                      AND ST_DWithin(sx.geom::geography, seg.geom::geography, 50)
                )
            )
            SQL;

        if ($dry) {
            $rows = DB::select($sql."\nSELECT new_status, COUNT(*) n FROM target GROUP BY new_status ORDER BY n DESC", ['era' => $era->id]);
            $this->info("[DRY-RUN] ère {$era->year} - segments à reclasser :");
            $tot = 0;
            foreach ($rows as $r) {
                $this->line("  → {$r->new_status}: {$r->n}");
                $tot += $r->n;
            }
            $this->line("  total : {$tot}");

            return self::SUCCESS;
        }

        $affected = DB::update(
            $sql."\nUPDATE line_segments t SET status = target.new_status, traffic_type='unknown', updated_at=NOW() FROM target WHERE t.id = target.id",
            ['era' => $era->id],
        );

        $this->info("ère {$era->year} - {$affected} segments reclassés (in_service → statut SNCF mort).");
        foreach (DB::select('SELECT status, COUNT(*) n FROM line_segments WHERE era_id=? GROUP BY status ORDER BY n DESC', [$era->id]) as $r) {
            $this->line("  {$r->status}: {$r->n}");
        }

        return self::SUCCESS;
    }
}
