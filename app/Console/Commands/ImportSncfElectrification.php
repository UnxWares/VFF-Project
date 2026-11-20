<?php

namespace App\Console\Commands;

use App\Services\Traction\ElectrificationText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importe le jeu SNCF Réseau Open Data « Liste des lignes électrifiées »
 * (liste-des-lignes-electrifiees) : électrification ACTUELLE par tronçon
 * (code ligne + PK) avec sa tension et sa forme généralisée.
 * Consommé par vff:enrich-era-segments (état 2030 de chaque portion).
 *
 * Remplacement complet par DELETE (pas de TRUNCATE) dans une transaction.
 *
 *   php artisan vff:import-sncf-electrification
 */
class ImportSncfElectrification extends Command
{
    protected $signature = 'vff:import-sncf-electrification';

    protected $description = 'Importe l\'électrification actuelle SNCF (tension par tronçon, liste-des-lignes-electrifiees).';

    private const URL = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/liste-des-lignes-electrifiees/exports/geojson';

    private const UA = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public function handle(): int
    {
        $this->info('→ liste-des-lignes-electrifiees…');
        $res = Http::withUserAgent(self::UA)->timeout(180)->get(self::URL);
        if (! $res->ok()) {
            $this->error('SNCF HTTP '.$res->status());

            return self::FAILURE;
        }
        $features = $res->json('features') ?? [];
        if (count($features) < 100) {
            $this->error('Réponse SNCF suspecte ('.count($features).' tronçons) : import annulé.');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($features as $f) {
            $p = $f['properties'] ?? [];
            if (empty($p['code_ligne']) || empty($p['elect']) || empty($f['geometry'])) {
                continue;
            }
            [$v, $hz, $third] = ElectrificationText::fromSncfLabel((string) $p['elect']);
            $rows[] = [
                'code_ligne' => (string) $p['code_ligne'],
                'rg_troncon' => $p['rg_troncon'] ?? null,
                'pkd_m' => $this->parsePk($p['pkd'] ?? null),
                'pkf_m' => $this->parsePk($p['pkf'] ?? null),
                'elect' => mb_substr((string) $p['elect'], 0, 80),
                'voltage_v' => $v,
                'frequency_hz' => $hz,
                'third_rail' => $third,
                'idgaia' => $p['idgaia'] ?? null,
                'geom' => json_encode($f['geometry']),
            ];
        }

        DB::transaction(function () use ($rows) {
            DB::delete('DELETE FROM sncf_line_electrification');
            foreach (array_chunk($rows, 300) as $chunk) {
                $values = [];
                $bindings = [];
                foreach ($chunk as $r) {
                    $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ST_Multi(ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))), NOW(), NOW())';
                    array_push($bindings, $r['code_ligne'], $r['rg_troncon'], $r['pkd_m'], $r['pkf_m'], $r['elect'],
                        $r['voltage_v'], $r['frequency_hz'], $r['third_rail'], $r['idgaia'], $r['geom']);
                }
                DB::insert('INSERT INTO sncf_line_electrification (code_ligne, rg_troncon, pkd_m, pkf_m, elect, voltage_v, frequency_hz, third_rail, idgaia, geom, created_at, updated_at) VALUES '
                    .implode(',', $values), $bindings);
            }
        });

        foreach (DB::select('SELECT elect, COUNT(*) n, ROUND(SUM(ST_Length(geom::geography)) / 1000) km FROM sncf_line_electrification GROUP BY elect ORDER BY km DESC') as $r) {
            $this->line(sprintf('    %-40s %5d tronçons  %6d km', $r->elect, $r->n, $r->km));
        }
        $this->info('✓ '.count($rows).' tronçons électrifiés importés.');

        return self::SUCCESS;
    }

    /** PK SNCF « 057+727 » → 57727 m. */
    private function parsePk(?string $raw): ?int
    {
        if (! $raw || ! preg_match('/^(\d+)([+\-])(\d+)$/', trim($raw), $m)) {
            return null;
        }
        $base = (int) $m[1] * 1000;

        return $m[2] === '-' ? $base - (int) $m[3] : $base + (int) $m[3];
    }
}
