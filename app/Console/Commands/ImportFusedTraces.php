<?php

namespace App\Console\Commands;

use App\Services\Geometry\FusedTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

/**
 * Verse les morceaux de tracé fusionnés (chantier fusion-sources) dans `source_traces`, source `fusion`, couche
 * `precis` (erreur estimée sous 3 m) ou `estime`. Le moteur les lit ensuite comme les autres sources.
 *
 * Sans `--write`, la commande ne fait que lire et compter (aucune écriture). Avec `--write`, chaque morceau est
 * inséré ou remplacé (clé : source, source_ref) ; les morceaux `fusion` des lignes du fichier qui n'y sont plus
 * sont retirés, pour qu'un nouveau calcul remplace l'ancien sans doublon.
 *
 *   php artisan vff:import-fused-traces storage/app/reports/agents/aprem-2111/fusion-sources/proto/3181/fusion.geojson
 *   php artisan vff:import-fused-traces <fichier> --write
 */
class ImportFusedTraces extends Command
{
    protected $signature = 'vff:import-fused-traces
        {files* : fichiers GeoJSON produits par ml/fusion-sources}
        {--write : écrire dans source_traces (sinon lecture et comptage seulement)}';

    protected $description = 'Verse les morceaux de tracé fusionnés (fusion des sources, erreur estimée) dans source_traces.';

    public function handle(): int
    {
        $pieces = [];
        foreach ((array) $this->argument('files') as $file) {
            try {
                $pieces = [...$pieces, ...FusedTrace::fromFile((string) $file)->rows()];
            } catch (InvalidArgumentException|JsonException $e) {
                $this->error("{$file} : {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $lines = array_values(array_unique(array_column($pieces, 'line_id')));
        $precise = count(array_filter($pieces, fn (array $p): bool => $p['layer'] === FusedTrace::PRECISE));
        $this->info(sprintf('%d morceaux sur %d lignes : %d précis, %d estimés.', count($pieces), count($lines), $precise,
            count($pieces) - $precise));

        if (! $this->option('write')) {
            $this->line('Lecture seule (ajouter --write pour écrire).');

            return self::SUCCESS;
        }

        $known = DB::table('lines')->whereIn('id', $lines)->pluck('id')->all();
        $missing = array_diff($lines, $known);
        if ($missing !== []) {
            $this->error('Lignes inconnues : '.implode(', ', $missing));

            return self::FAILURE;
        }

        DB::transaction(function () use ($pieces, $lines): void {
            // Ligne par ligne : quelques dizaines de morceaux par ligne, jamais des dizaines de milliers de
            // paramètres liés dans une seule requête (limite de PostgreSQL) lors d'un import national.
            foreach ($lines as $line) {
                $refs = array_column(array_filter($pieces, fn (array $p): bool => $p['line_id'] === $line), 'source_ref');
                DB::table('source_traces')->where('source', FusedTrace::SOURCE)->where('line_id', $line)
                    ->whereNotIn('source_ref', $refs)->delete();
            }
            foreach ($pieces as $p) {
                DB::statement(<<<'SQL'
                    INSERT INTO source_traces (source, source_ref, layer, line_id, properties, length_m, seen_at, created_at, updated_at, geom)
                    SELECT ?, ?, ?, ?, CAST(? AS jsonb), ROUND(ST_Length(g::geography)), NOW(), NOW(), NOW(), g
                    FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g) t
                    ON CONFLICT (source, source_ref) DO UPDATE SET layer = EXCLUDED.layer, line_id = EXCLUDED.line_id,
                        properties = EXCLUDED.properties, length_m = EXCLUDED.length_m, seen_at = EXCLUDED.seen_at,
                        updated_at = EXCLUDED.updated_at, geom = EXCLUDED.geom
                    SQL, [$p['source'], $p['source_ref'], $p['layer'], $p['line_id'], $p['properties'], $p['geojson']]);
            }
        });
        $this->info('Écrit.');

        return self::SUCCESS;
    }
}
