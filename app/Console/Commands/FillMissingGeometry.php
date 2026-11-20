<?php

namespace App\Console\Commands;

use App\Enums\SegmentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comble les lignes RFN dont l'import OSM strict n'a capté AUCUN tracé pour
 * l'ère cible, en y injectant la géométrie officielle SNCF (sncf_line_geometries).
 * Évite les « lignes pas dessinées entièrement / du tout » sur la carte.
 *
 * N'ajoute que pour les lignes SANS aucun segment de l'ère (ne double pas les
 * lignes déjà couvertes par OSM, dont la résolution est supérieure). Le
 * traffic_type est laissé à 'unknown' → relancer vff:classify-lines ensuite.
 */
class FillMissingGeometry extends Command
{
    protected $signature = 'vff:fill-missing-geometry {--era=} {--dry-run}';

    protected $description = 'Comble les lignes sans tracé OSM avec la géométrie officielle SNCF.';

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

        // Lignes ayant une géométrie SNCF mais AUCUN segment dans l'ère.
        $targets = DB::select(
            'SELECT DISTINCT l.id, l.code
             FROM lines l
             JOIN sncf_line_geometries sg ON sg.code_ligne = l.code
             WHERE NOT EXISTS (SELECT 1 FROM line_segments ls WHERE ls.line_id = l.id AND ls.era_id = ?)',
            [$eraId],
        );

        $this->info(count($targets)." lignes sans tracé à combler depuis SNCF (era_id={$eraId})");
        if ($this->option('dry-run')) {
            foreach (array_slice($targets, 0, 30) as $t) {
                $name = DB::table('lines')->where('id', $t->id)->value('name');
                $this->line("  [{$t->code}] {$name}");
            }

            return self::SUCCESS;
        }

        // Mapping libellé→status construit depuis l'enum (source unique).
        $libelles = DB::table('sncf_line_geometries')->select('libelle')->distinct()->pluck('libelle')->filter();
        $whens = $libelles
            ->map(fn ($lib) => 'WHEN sg.libelle = '.DB::getPdo()->quote($lib)
                .' THEN '.DB::getPdo()->quote(SegmentStatus::fromSncfLibelle($lib)->value))
            ->implode(' ');
        $statusCase = $whens ? "CASE {$whens} ELSE 'in_service' END" : "'in_service'";

        $ids = array_map(fn ($t) => $t->id, $targets);
        $inserted = 0;
        foreach (array_chunk($ids, 100) as $chunk) {
            $in = implode(',', $chunk);
            $inserted += DB::insert("
                INSERT INTO line_segments (line_id, era_id, geom, status, traffic_type, length_meters, electrified, gauge_mm, created_at, updated_at)
                SELECT l.id, ?, sg.geom, {$statusCase}, 'unknown',
                    ROUND(ST_Length(sg.geom::geography))::int, false, 1435, NOW(), NOW()
                FROM sncf_line_geometries sg JOIN lines l ON l.code = sg.code_ligne
                WHERE l.id IN ({$in})
            ", [$eraId]);
        }

        $count = DB::selectOne('SELECT COUNT(*) n FROM line_segments WHERE era_id=? AND line_id IN ('.implode(',', $ids ?: [0]).')', [$eraId])->n;
        $this->info("✓ {$count} segments SNCF insérés pour ".count($targets).' lignes. Relancer vff:classify-lines + signalisation.');

        return self::SUCCESS;
    }
}
