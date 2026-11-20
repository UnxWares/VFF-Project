<?php

namespace App\Console\Commands;

use App\Enums\SegmentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset complet : remplace tous les segments d'une ère par les géométries
 * officielles SNCF (sncf_line_geometries).
 *
 * Une ligne SNCF (code_ligne) = N records dans sncf_line_geometries qui
 * deviennent N segments dans line_segments avec la bonne attribution.
 *
 * - Le `status` est dérivé du `libelle` SNCF via le mapping UNIQUE
 *   SegmentStatus::fromSncfLibelle() (source de vérité partagée).
 * - Le `traffic_type` est laissé à 'unknown' : le libellé SNCF ne porte PAS
 *   l'info voyageurs/fret (c'est un STATUT). Le typage voyageurs/fret est posé
 *   ensuite par `vff:classify-lines` (liste-des-gares + GTFS + Wikipedia).
 */
class ImportSegmentsFromSncf extends Command
{
    protected $signature = 'vff:import-segments-sncf {--era=}';

    protected $description = 'Importe les segments depuis sncf_line_geometries dans line_segments.';

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

        $this->info("Import segments SNCF → line_segments (era_id={$eraId})…");
        DB::table('line_segments')->where('era_id', $eraId)->delete();

        // CASE status construit à partir de l'enum (source de vérité unique) sur
        // les libellés réellement présents → reste un INSERT...SELECT en masse.
        $libelles = DB::table('sncf_line_geometries')
            ->select('libelle')->distinct()->pluck('libelle')
            ->filter()->values();

        $whens = $libelles
            ->map(fn ($lib) => 'WHEN sg.libelle = '.DB::getPdo()->quote($lib)
                .' THEN '.DB::getPdo()->quote(SegmentStatus::fromSncfLibelle($lib)->value))
            ->implode("\n                    ");

        $statusCase = $whens
            ? "CASE\n                    {$whens}\n                    ELSE 'in_service'\n                END"
            : "'in_service'";

        DB::insert("
            INSERT INTO line_segments (line_id, era_id, geom, status, traffic_type, length_meters, electrified, gauge_mm, created_at, updated_at)
            SELECT l.id, ?, sg.geom,
                {$statusCase},
                'unknown',
                ROUND(ST_Length(sg.geom::geography))::int,
                false, 1435,
                NOW(), NOW()
            FROM sncf_line_geometries sg
            JOIN lines l ON l.code = sg.code_ligne
        ", [$eraId]);

        $count = DB::table('line_segments')->where('era_id', $eraId)->count();
        $this->info("    ✓ {$count} segments insérés (traffic_type=unknown → lancer vff:classify-lines)");

        // Stats par status
        $statuses = DB::select(
            'SELECT status, COUNT(*) AS n FROM line_segments WHERE era_id=? GROUP BY status ORDER BY n DESC',
            [$eraId],
        );
        foreach ($statuses as $s) {
            $this->line("    {$s->status}: {$s->n}");
        }

        return self::SUCCESS;
    }
}
