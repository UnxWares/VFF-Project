<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importe les datasets SNCF discriminants pour la classification trafic/LGV
 * (consommés par vff:classify-lines) :
 *   - liste-des-gares             → sncf_gares (fret/voyageurs par code_ligne)
 *   - lignes-par-type             → sncf_line_types (Ligne/Rac/Vmère/Vport)
 *   - lignes-lgv-et-par-ecartement → sncf_line_catlig (LGV)
 *
 * Le libellé SNCF (statut) reste fourni par sncf_line_geometries - pas re-fetché ici.
 */
class ImportSncfRefs extends Command
{
    protected $signature = 'vff:import-sncf-refs {--only= : un seul dataset (gares|types|catlig)}';

    protected $description = 'Importe les datasets SNCF de référence (gares fret/voyageurs, types de ligne, LGV).';

    private const BASE = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets';

    private const UA = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public function handle(): int
    {
        $only = $this->option('only');
        if ($only && ! in_array($only, ['gares', 'types', 'catlig'], true)) {
            $this->error("--only inconnu : {$only} (gares, types ou catlig)");

            return self::FAILURE;
        }

        // Un jeu en panne (HTTP, réponse vide) n'arrête pas les autres, mais la
        // commande échoue et la table concernée garde ses données.
        $ok = true;
        foreach (['gares' => 'importGares', 'types' => 'importTypes', 'catlig' => 'importCatlig'] as $dataset => $method) {
            if ($only && $only !== $dataset) {
                continue;
            }
            try {
                $this->{$method}();
            } catch (\RuntimeException $e) {
                $this->error('    '.$e->getMessage().' : table conservée.');
                $ok = false;
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function importGares(): void
    {
        $this->info('→ liste-des-gares…');
        $rows = $this->fetchAll('liste-des-gares', 'code_uic,code_ligne,fret,voyageurs,libelle,rg_troncon,pk,commune,departemen,x_wgs84,y_wgs84');

        $batch = [];
        foreach ($rows as $r) {
            if (empty($r['code_ligne'])) {
                continue;
            }
            $batch[] = [
                'code_uic' => $r['code_uic'] ?? null,
                'code_ligne' => (string) $r['code_ligne'],
                'fret' => ($r['fret'] ?? 'N') === 'O',
                'voyageurs' => ($r['voyageurs'] ?? 'N') === 'O',
                'libelle' => $r['libelle'] ?? null,
                'rg_troncon' => $r['rg_troncon'] ?? null,
                'pk_m' => $this->parsePk($r['pk'] ?? null),
                'commune' => $r['commune'] ?? null,
                'department' => $r['departemen'] ?? null,
                'lat' => $r['y_wgs84'] ?? null,
                'lon' => $r['x_wgs84'] ?? null,
            ];
        }
        $this->insertChunks('sncf_gares', $batch);

        $fretOnly = DB::selectOne('
            SELECT COUNT(*) n FROM (
                SELECT code_ligne FROM sncf_gares GROUP BY code_ligne
                HAVING BOOL_AND(NOT voyageurs) AND BOOL_OR(fret)
            ) t');
        $pax = DB::selectOne('SELECT COUNT(DISTINCT code_ligne) n FROM sncf_gares WHERE voyageurs = true');
        $this->line('    ✓ '.count($batch)." gares · {$fretOnly->n} lignes fret-only · {$pax->n} lignes voyageurs");
    }

    private function importTypes(): void
    {
        $this->info('→ lignes-par-type…');
        $rows = $this->fetchAll('lignes-par-type', 'code_ligne,rg_troncon,pkd,pkf,type_ligne');

        $batch = [];
        foreach ($rows as $r) {
            if (empty($r['code_ligne']) || empty($r['type_ligne'])) {
                continue;
            }
            $batch[] = [
                'code_ligne' => (string) $r['code_ligne'],
                'rg_troncon' => $r['rg_troncon'] ?? null,
                'pkd_m' => $this->parsePk($r['pkd'] ?? null),
                'pkf_m' => $this->parsePk($r['pkf'] ?? null),
                'type_ligne' => (string) $r['type_ligne'],
            ];
        }
        $this->insertChunks('sncf_line_types', $batch);

        foreach (DB::select('SELECT type_ligne, COUNT(DISTINCT code_ligne) n FROM sncf_line_types GROUP BY type_ligne ORDER BY n DESC') as $t) {
            $this->line("    {$t->type_ligne}: {$t->n} codes");
        }
    }

    private function importCatlig(): void
    {
        $this->info('→ lignes-lgv-et-par-ecartement…');
        $rows = $this->fetchAll('lignes-lgv-et-par-ecartement', 'code_ligne,rg_troncon,pkd,pkf,catlig');

        $batch = [];
        foreach ($rows as $r) {
            if (empty($r['code_ligne']) || empty($r['catlig'])) {
                continue;
            }
            $batch[] = [
                'code_ligne' => (string) $r['code_ligne'],
                'rg_troncon' => $r['rg_troncon'] ?? null,
                'pkd_m' => $this->parsePk($r['pkd'] ?? null),
                'pkf_m' => $this->parsePk($r['pkf'] ?? null),
                'catlig' => (string) $r['catlig'],
            ];
        }
        $this->insertChunks('sncf_line_catlig', $batch);

        $lgv = DB::selectOne("SELECT COUNT(DISTINCT code_ligne) n FROM sncf_line_catlig WHERE catlig ILIKE '%grande vitesse%'");
        $this->line('    ✓ '.count($batch)." tronçons · {$lgv->n} codes LGV");
    }

    /**
     * Récupère tous les enregistrements d'un dataset via l'endpoint exports/json.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $dataset, string $select): array
    {
        // select= pour EXCLURE geo_shape (géométrie complète par ligne) qui ferait
        // exploser la mémoire sur un export full.
        $url = self::BASE."/{$dataset}/exports/json?limit=-1&select=".rawurlencode($select);
        try {
            $res = Http::withUserAgent(self::UA)->timeout(180)->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException("SNCF {$dataset} injoignable");
        }
        if (! $res->ok()) {
            throw new \RuntimeException("SNCF {$dataset} HTTP {$res->status()}");
        }

        return is_array($res->json()) ? $res->json() : [];
    }

    /** Remplacement atomique de la table ; jamais par une liste vide (réponse SNCF anormale). */
    private function insertChunks(string $table, array $rows): void
    {
        if ($rows === []) {
            throw new \RuntimeException("SNCF : aucun enregistrement exploitable pour {$table}");
        }
        DB::transaction(function () use ($table, $rows) {
            DB::table($table)->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        });
    }

    /**
     * Parse un PK SNCF "057+727" → 57727 m, "000-771" → -771 m.
     */
    private function parsePk(?string $raw): ?int
    {
        if (! $raw || ! preg_match('/^(\d+)([+\-])(\d+)$/', trim($raw), $m)) {
            return null;
        }
        $base = (int) $m[1] * 1000;
        $off = (int) $m[3];

        return $m[2] === '-' ? $base - $off : $base + $off;
    }
}
