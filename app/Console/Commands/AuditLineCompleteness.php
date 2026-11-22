<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Compare la longueur VFF de chaque ligne à la longueur officielle SNCF
 * (depuis `formes-des-lignes-du-rfn`) et liste les lignes mutilées
 * (longueur VFF < 30% de la longueur SNCF).
 *
 * Pas de modification : juste un rapport.
 */
class AuditLineCompleteness extends Command
{
    protected $signature = 'vff:audit-line-completeness {--era=} {--threshold=30 : Seuil de complétion (%)}';

    protected $description = 'Audit complétion des lignes vs SNCF formes-des-lignes-du-rfn.';

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
        $threshold = (float) $this->option('threshold');

        $this->info("Audit complétion lignes (era_id={$eraId}, seuil {$threshold}%)…");

        // 1. Récupère longueur officielle SNCF par code_ligne
        $this->info('  → Fetch SNCF formes-des-lignes-du-rfn…');
        $sncfLengths = [];
        $offset = 0;
        $limit = 100;
        do {
            $response = Http::timeout(60)
                ->get('https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/formes-des-lignes-du-rfn/records',
                    ['limit' => $limit, 'offset' => $offset, 'select' => 'code_ligne,pk_debut_r,pk_fin_r']);
            // Référentiel indisponible : l'audit serait faux (« 0 ligne en déficit »).
            if (! $response->successful()) {
                $this->error("SNCF HTTP {$response->status()} : audit impossible.");

                return self::FAILURE;
            }
            $records = $response->json('results') ?? [];
            if (! $records) {
                break;
            }
            foreach ($records as $r) {
                $code = $r['code_ligne'] ?? null;
                if (! $code) {
                    continue;
                }
                $a = $this->parsePk($r['pk_debut_r'] ?? null);
                $b = $this->parsePk($r['pk_fin_r'] ?? null);
                if ($a === null || $b === null) {
                    continue;
                }
                $sncfLengths[$code] = ($sncfLengths[$code] ?? 0) + abs($b - $a);
            }
            $offset += $limit;
        } while (count($records) === $limit && $offset < 5000);
        $this->info('    '.count($sncfLengths).' lignes SNCF référencées');

        // 2. Calcule longueur VFF par ligne
        $this->info('  → Compute longueurs VFF…');
        $vffRows = DB::select(
            'SELECT l.code, l.slug, l.name, SUM(ST_Length(ls.geom::geography)) AS vff_m
             FROM line_segments ls JOIN lines l ON l.id=ls.line_id
             WHERE ls.era_id=? AND l.code IS NOT NULL
             GROUP BY l.id, l.code, l.slug, l.name',
            [$eraId],
        );

        // 3. Compare et liste les déficitaires
        $deficient = [];
        $missing = [];
        foreach ($vffRows as $v) {
            $sncf = $sncfLengths[$v->code] ?? null;
            if ($sncf === null) {
                $missing[] = $v;

                continue;
            }
            $vff = (float) $v->vff_m;
            if ($sncf < 100) {
                continue;
            } // trop court côté SNCF, pas pertinent
            $pct = $vff * 100 / $sncf;
            if ($pct < $threshold) {
                $deficient[] = (object) [
                    'slug' => $v->slug, 'code' => $v->code, 'name' => $v->name,
                    'vff_m' => $vff, 'sncf_m' => $sncf, 'pct' => $pct,
                ];
            }
        }

        // Tri par pct croissant (les plus mutilées d'abord)
        usort($deficient, fn ($a, $b) => $a->pct <=> $b->pct);

        $this->info(PHP_EOL.count($deficient).' lignes en déficit (< '.$threshold.'% de la longueur SNCF) :');
        $this->line(str_repeat('─', 90));
        foreach (array_slice($deficient, 0, 50) as $d) {
            $this->line(sprintf(
                '  %-12s %5.1f%% | VFF %6.1f km / SNCF %6.1f km │ %s',
                $d->slug,
                $d->pct,
                $d->vff_m / 1000,
                $d->sncf_m / 1000,
                mb_substr($d->name, 0, 50),
            ));
        }
        if (count($deficient) > 50) {
            $this->line('  … '.(count($deficient) - 50).' autres lignes en déficit');
        }
        // Lignes VFF à code RFN absentes du référentiel SNCF (calculées mais jamais affichées avant).
        $this->info(count($missing).' lignes VFF absentes du référentiel SNCF'.($missing ? ' : '.implode(', ', array_map(fn ($m) => $m->code, array_slice($missing, 0, 20))) : '.'));

        return self::SUCCESS;
    }

    private function parsePk(?string $pk): ?int
    {
        if (! $pk) {
            return null;
        }
        // Format "016+953", "001-250" (km moins mètres) ou "-005+200"
        if (preg_match('/^(-?)(\d+)([+\-])(\d{1,3})$/', trim($pk), $m)) {
            $value = (int) $m[2] * 1000 + ($m[3] === '-' ? -(int) $m[4] : (int) $m[4]);

            return $m[1] === '-' ? -$value : $value;
        }

        return null;
    }
}
