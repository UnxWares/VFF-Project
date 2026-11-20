<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importe les géométries officielles SNCF des lignes depuis
 * `formes-des-lignes-du-rfn` dans la table `sncf_line_geometries`.
 *
 * Source de vérité pour :
 *   - audit des lignes mal cataloguées dans VFF (trop longues / courtes)
 *   - ré-attribution des segments aux bonnes lignes
 *   - status officiel (Exploitée / Neutralisée / Déposée)
 */
class ImportSncfLineGeometries extends Command
{
    protected $signature = 'vff:import-sncf-line-geometries';

    protected $description = 'Importe la géométrie officielle SNCF des lignes du RFN.';

    public function handle(): int
    {
        $this->info('Import SNCF formes-des-lignes-du-rfn…');

        // 1. Téléchargement COMPLET avant toute écriture : une panne SNCF (HTTP 5xx,
        //    réponse vide) ne doit pas vider le référentiel (avant : TRUNCATE d'abord).
        $offset = 0;
        $limit = 100;
        $skipped = 0;
        $batch = [];
        do {
            $response = Http::timeout(120)
                ->get('https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/formes-des-lignes-du-rfn/records',
                    ['limit' => $limit, 'offset' => $offset]);
            if (! $response->successful()) {
                $this->error("SNCF HTTP {$response->status()} (offset {$offset}) : référentiel conservé.");

                return self::FAILURE;
            }
            $records = $response->json('results') ?? [];
            foreach ($records as $r) {
                $code = $r['code_ligne'] ?? null;
                $shape = $r['geo_shape']['geometry'] ?? null;
                if (! $code || ! $shape || ($shape['type'] ?? '') !== 'LineString') {
                    $skipped++;

                    continue;
                }
                $batch[] = [
                    $code, $r['libelle'] ?? 'Inconnue', $r['mnemo'] ?? null,
                    $this->parsePk($r['pk_debut_r'] ?? null), $this->parsePk($r['pk_fin_r'] ?? null),
                    $r['rg_troncon'] ?? null, $r['idgaia'] ?? null, json_encode($shape),
                ];
            }
            $offset += $limit;
        } while (count($records) === $limit && $offset < 5000);

        if ($batch === []) {
            $this->error('Réponse SNCF sans aucune géométrie : référentiel conservé.');

            return self::FAILURE;
        }

        // 2. Remplacement atomique.
        $inserted = 0;
        DB::transaction(function () use ($batch, &$inserted) {
            DB::table('sncf_line_geometries')->delete();
            foreach ($batch as $b) {
                $inserted += DB::affectingStatement(
                    'INSERT INTO sncf_line_geometries
                        (code_ligne, libelle, mnemo, pk_debut_m, pk_fin_m, rg_troncon, idgaia, geom, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ST_GeomFromGeoJSON(?), NOW(), NOW())
                     ON CONFLICT (idgaia) DO NOTHING',
                    $b,
                );
            }
        });
        $this->info("  {$inserted} tronçons insérés, {$skipped} ignorés (sans code ou non LineString), ".(count($batch) - $inserted).' doublons idgaia');

        // Stats
        $st = DB::selectOne('
            SELECT COUNT(*) AS total, COUNT(DISTINCT code_ligne) AS distinct_codes,
                   COUNT(DISTINCT libelle) AS distinct_libelles
            FROM sncf_line_geometries
        ');
        $this->info("Total: {$st->total} géométries, {$st->distinct_codes} codes_ligne distincts");

        $libs = DB::select('SELECT libelle, COUNT(*) AS n FROM sncf_line_geometries GROUP BY libelle ORDER BY n DESC');
        $this->info('Distribution statuts SNCF:');
        foreach ($libs as $l) {
            $this->line("  {$l->libelle}: {$l->n}");
        }

        return self::SUCCESS;
    }

    /**
     * PK SNCF « 057+727 » → 57 727 m ; « 001-250 » → 750 m (km moins mètres, comme
     * les autres importeurs SNCF) ; « -001+200 » → -1 200 m.
     */
    private function parsePk(?string $pk): ?int
    {
        if (! $pk || ! preg_match('/^(-?)(\d+)([+\-])(\d{1,3})$/', trim($pk), $m)) {
            return null;
        }
        $value = (int) $m[2] * 1000 + ($m[3] === '-' ? -(int) $m[4] : (int) $m[4]);

        return $m[1] === '-' ? -$value : $value;
    }
}
