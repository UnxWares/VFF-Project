<?php

namespace App\Console\Commands;

use App\Services\Signalling\SignallingMap;
use App\Services\Signalling\SignallingRegimeRules;
use App\Services\Signalling\SncfBlockLabels;
use App\Services\Sncf\SncfPk;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Source UNIQUE et autoritaire de la signalisation : importe la donnée
 * officielle SNCF Réseau Open Data PAR PK dans `line_signalling`.
 *
 * Sources :
 *   - `mode-de-cantonnement-des-lignes` : BAL/BAPR/BM/CAPI/CT/TVM-300/TVM-430/
 *     ETCS L1/L2 avec PK exacts (≈1663 tronçons).
 *   - `lignes-equipees-de-kvb` : portions équipées KVB avec PK.
 *
 * Principe (cf. directive « tout en DB, calculé, jamais hardcodé ») :
 *   - le mode de cantonnement vient INTÉGRALEMENT du dataset SNCF par PK
 *     (donc TVM-300 ET TVM-430 sur une même LGV apparaissent bien) ; un libellé double
 *     (« ETCS 2/TVM 300 ») garde ses deux systèmes (SncfBlockLabels, `signalling_systems`) ;
 *   - les LGV sont identifiées via sncf_line_catlig (pas de liste codée) ;
 *   - la protection est DÉRIVÉE du cantonnement : TVM/ETCS = cabine (pas de KVB) ;
 *     KVB n'est ajouté que sur les lignes classiques (Phase 2), jamais sur LGV.
 *   - plus aucune curation ETCS hardcodée : l'ETCS est dans le dataset SNCF.
 */
class ImportSncfSignalling extends Command
{
    protected $signature = 'vff:import-sncf-signalling {--era=}
        {--from-cache : jeux SNCF du cache local s\'il existe (téléchargés et mis en cache sinon) : étape hors ligne de vff:rebuild-history}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-signalling)}';

    protected $description = 'Importe la signalisation officielle SNCF Réseau par PK (cantonnement + KVB), source unique.';

    private const SNCF_API_BASE = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets';

    /** @var array<string, true> libellés SNCF inconnus (tranches ignorées, signalées en fin d'import) */
    private array $unknownLabels = [];

    /** @var array<string, list<array{from: int, to: int, regime: string}>> portions de régime d'exploitation par ligne */
    private array $regimes = [];

    /** Tranches « _Autre » dont le système est déduit du régime d'exploitation (portions écrites). */
    private int $regimeDeduced = 0;

    /** @var array<string,bool> codes LGV (depuis sncf_line_catlig) */
    private array $lgvCodes = [];

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

        $this->info("Import SNCF Réseau signalisation par PK (era_id={$eraId})");

        // LGV depuis la donnée SNCF (plus de liste hardcodée)
        $this->lgvCodes = array_fill_keys(
            DB::table('sncf_line_catlig')->where('catlig', 'ILIKE', '%grande vitesse%')->distinct()->pluck('code_ligne')->all(),
            true,
        );
        $this->line('  '.count($this->lgvCodes).' codes LGV depuis sncf_line_catlig');

        // Téléchargement des deux jeux AVANT d'effacer la signalisation de l'ère :
        // une panne SNCF laisse la base intacte (avant : tout était effacé d'abord).
        try {
            $cantonnement = $this->fetchAllRecords('mode-de-cantonnement-des-lignes');
            $kvb = $this->fetchAllRecords('lignes-equipees-de-kvb');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage().' : signalisation conservée.');

            return self::FAILURE;
        }
        if ($cantonnement === []) {
            $this->error('Cantonnement SNCF vide : signalisation conservée.');

            return self::FAILURE;
        }

        // Régimes d'exploitation lus seulement si une tranche « _Autre » en a besoin : aucun téléchargement inutile.
        if (array_any($cantonnement, fn (array $r): bool => ($r['libelle'] ?? null) === SignallingRegimeRules::OTHER_LABEL)) {
            $this->loadRegimes();
        }

        [$totalCant, $totalKvb] = DB::transaction(function () use ($eraId, $cantonnement, $kvb) {
            DB::table('line_signalling')->where('era_id', $eraId)->delete();

            $this->info('  ► [1/2] Cantonnement (mode-de-cantonnement-des-lignes)…');
            $totalCant = $this->importCantonnement($eraId, $cantonnement);
            $this->info("    ✓ {$totalCant} tranches insérées (dont {$this->regimeDeduced} déduites du régime d'exploitation)");
            if ($this->unknownLabels !== []) {
                $this->warn('    Libellés inconnus, tranches ignorées : '.implode(' ; ', array_keys($this->unknownLabels)));
            }

            $this->info('  ► [2/2] KVB (lignes-equipees-de-kvb, hors LGV)…');
            $totalKvb = $this->importKvb($eraId, $kvb);
            $this->info("    ✓ {$totalKvb} portions KVB rapprochées");

            return [$totalCant, $totalKvb];
        });

        $st = DB::selectOne('
            SELECT COUNT(*) AS total, COUNT(DISTINCT line_id) AS lines,
                   SUM(CASE WHEN kvb_equipped THEN 1 ELSE 0 END) AS kvb_count
            FROM line_signalling WHERE era_id = ?
        ', [$eraId]);
        $this->info("État final : {$st->total} tranches sur {$st->lines} lignes ({$st->kvb_count} avec KVB)");

        // Calque « Signalisation » de la carte : tracé des tranches recalculé (vue matérialisée).
        $pieces = app(SignallingMap::class)->refresh();
        $this->info("Carte de la signalisation : {$pieces} morceaux de tracé recalculés");

        return self::SUCCESS;
    }

    /**
     * Portions de régime d'exploitation (jeu `regime-dexploitation-des-lignes`), par code de ligne, pour les tranches
     * « _Autre ». Cache du pipeline (storage/app/sources/sncf-regime) s'il existe, sinon téléchargement ; une panne laisse
     * ces tranches sans système (rien n'est deviné).
     */
    private function loadRegimes(): void
    {
        $records = [];
        $file = storage_path('app/sources/sncf-regime/regime-dexploitation-des-lignes.json');
        try {
            if (! app()->runningUnitTests() && is_file($file)) {
                $cached = json_decode((string) file_get_contents($file), true);
                $records = is_array($cached['records'] ?? null) ? $cached['records'] : [];
            }
            if ($records === []) {
                $records = $this->fetchAllRecords('regime-dexploitation-des-lignes');
            }
        } catch (\RuntimeException $e) {
            $this->warn('  Régimes d\'exploitation indisponibles ('.$e->getMessage().') : tranches « _Autre » ignorées.');
        }
        foreach ($records as $r) {
            $from = $this->parsePk($r['pkd'] ?? null);
            $to = $this->parsePk($r['pkf'] ?? null);
            if ($from === null || $to === null || ! isset($r['code_ligne'], $r['exploitati'])) {
                continue;
            }
            $this->regimes[(string) $r['code_ligne']][] = ['from' => $from, 'to' => $to, 'regime' => (string) $r['exploitati']];
        }
    }

    /**
     * Tous les enregistrements d'un jeu SNCF (pagination records). Chaque téléchargement
     * complet est mis en cache (storage/app/sources/sncf-signalling/<jeu>.json) ; avec
     * --from-cache, le cache sert s'il existe : la signalisation se reconstitue sans réseau
     * après une recréation de la base (elle avait disparu ainsi le 15/10/2026).
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAllRecords(string $dataset): array
    {
        // Tests : jamais le vrai cache (des données factices y serviraient ensuite au pipeline).
        $dir = $this->option('cache-dir') ?: (app()->runningUnitTests() ? null : storage_path('app/sources/sncf-signalling'));
        if ($dir === null) {
            return $this->downloadAllRecords($dataset);
        }
        $dir = rtrim($dir, '/');
        $cache = "{$dir}/{$dataset}.json";
        if ($this->option('from-cache') && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached['records'] ?? null) && $cached['records'] !== []) {
                $this->line("  {$dataset} : cache du ".($cached['fetched_at'] ?? '?').' ('.count($cached['records']).' enregistrements)');

                return $cached['records'];
            }
        }
        $all = $this->downloadAllRecords($dataset);
        if ($all !== []) {
            @mkdir($dir, 0775, true);
            file_put_contents($cache, json_encode(['dataset' => $dataset, 'fetched_at' => now()->toDateTimeString(), 'records' => $all],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $all;
    }

    /** @return list<array<string, mixed>> */
    private function downloadAllRecords(string $dataset): array
    {
        $limit = 100;
        $offset = 0;
        $all = [];
        do {
            try {
                $response = Http::withUserAgent('VFF-Bot/1.0 (+https://vff-project.org)')->timeout(60)
                    ->get(self::SNCF_API_BASE."/{$dataset}/records", ['limit' => $limit, 'offset' => $offset]);
            } catch (ConnectionException $e) {
                throw new \RuntimeException("SNCF {$dataset} injoignable");
            }
            if (! $response->successful()) {
                throw new \RuntimeException("SNCF {$dataset} HTTP {$response->status()}");
            }
            $records = $response->json('results') ?? [];
            array_push($all, ...$records);
            $offset += $limit;
        } while (count($records) === $limit && $offset < 10000);

        return $all;
    }

    /** @param  list<array<string, mixed>>  $records */
    private function importCantonnement(int $eraId, array $records): int
    {
        $inserted = 0;
        $lineIdCache = [];

        foreach (array_chunk($records, 100) as $batch) {
            $inserts = [];
            foreach ($batch as $r) {
                // Deux systèmes installés ensemble (« ETCS 2/TVM 300 ») : les deux sont gardés.
                $label = (string) ($r['libelle'] ?? '');
                $systems = SncfBlockLabels::systems($label);
                if ($systems === null) {
                    $this->unknownLabels[$label] = true;

                    continue;
                }
                $isOther = $label === SignallingRegimeRules::OTHER_LABEL;
                if ($systems === [] && ! $isOther) {
                    continue;
                }

                $code = $r['code_ligne'] ?? null;
                if (! $code) {
                    continue;
                }
                $lineId = $lineIdCache[$code] ??= (DB::table('lines')->where('code', $code)->value('id') ?: 0);
                if (! $lineId) {
                    continue;
                }

                $pkStart = $this->parsePk($r['pkd'] ?? null);
                $pkEnd = $this->parsePk($r['pkf'] ?? null);
                if ($pkStart === null || $pkEnd === null) {
                    continue;
                }
                if ($pkStart > $pkEnd) {
                    [$pkStart, $pkEnd] = [$pkEnd, $pkStart];
                }

                // Tranche « _Autre » : le jeu ne dit pas le système ; le régime d'exploitation le donne pour certaines
                // portions (règles sourcées de database/data/signalling_regime_systems.php), les autres restent sans système.
                $parts = [[$pkStart, $pkEnd, $systems, 'Source : SNCF Open Data, mode-de-cantonnement-des-lignes']];
                if ($isOther) {
                    $parts = [];
                    foreach (SignallingRegimeRules::pieces($pkStart, $pkEnd, $this->regimes[$code] ?? []) as $p) {
                        $parts[] = [$p['from'], $p['to'], [$p['system']],
                            'Déduit du régime d\'exploitation (SNCF Open Data, regime-dexploitation-des-lignes) : '.$p['source']];
                        $this->regimeDeduced++;
                    }
                }

                foreach ($parts as [$from, $to, $sys, $note]) {
                    $prot = SncfBlockLabels::protection($sys);
                    $inserts[] = [
                        'line_id' => $lineId,
                        'era_id' => $eraId,
                        'pk_start_m' => $from,
                        'pk_end_m' => $to,
                        'signalling' => $sys[0],
                        'signalling_systems' => json_encode($sys),
                        'source_label' => mb_substr($label, 0, 160),
                        'train_protection' => $prot ? json_encode($prot) : null,
                        'kvb_equipped' => false,
                        'note' => $note,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if ($inserts) {
                DB::table('line_signalling')->insert($inserts);
                $inserted += count($inserts);
            }
        }

        return $inserted;
    }

    /** @param  list<array<string, mixed>>  $records */
    private function importKvb(int $eraId, array $records): int
    {
        $applied = 0;
        $lineIdCache = [];

        foreach ($records as $r) {
            $code = $r['code_ligne'] ?? null;
            if (! $code) {
                continue;
            }
            // KVB non pertinent sur LGV (cabine TVM/ETCS) - exclusion via sncf_line_catlig.
            if (isset($this->lgvCodes[$code])) {
                continue;
            }

            $lineId = $lineIdCache[$code] ??= (DB::table('lines')->where('code', $code)->value('id') ?: 0);
            if (! $lineId) {
                continue;
            }

            $pkStart = $this->parsePk($r['pkd'] ?? null);
            $pkEnd = $this->parsePk($r['pkf'] ?? null);
            if ($pkStart === null || $pkEnd === null) {
                continue;
            }
            if ($pkStart > $pkEnd) {
                [$pkStart, $pkEnd] = [$pkEnd, $pkStart];
            }

            $rows = DB::table('line_signalling')
                ->where('era_id', $eraId)
                ->where('line_id', $lineId)
                ->where(function ($q) use ($pkStart, $pkEnd) {
                    $q->whereBetween('pk_start_m', [$pkStart, $pkEnd])
                        ->orWhereBetween('pk_end_m', [$pkStart, $pkEnd])
                        ->orWhere(function ($q2) use ($pkStart, $pkEnd) {
                            $q2->where('pk_start_m', '<=', $pkStart)->where('pk_end_m', '>=', $pkEnd);
                        });
                })
                ->get(['id', 'train_protection', 'signalling']);

            foreach ($rows as $row) {
                // Ne pas ajouter KVB sur une tranche en cabine (TVM/ETCS).
                if (SncfBlockLabels::isCab((string) $row->signalling)) {
                    continue;
                }
                $prot = $row->train_protection ? json_decode($row->train_protection, true) : [];
                if (! in_array('KVB', $prot, true)) {
                    $prot[] = 'KVB';
                }
                DB::table('line_signalling')->where('id', $row->id)->update([
                    'train_protection' => json_encode($prot),
                    'kvb_equipped' => true,
                    'updated_at' => now(),
                ]);
                $applied++;
            }
        }

        return $applied;
    }

    /** "016+953" → 16953 m ; "000-168" → -168 m ; "D+841" → 3841 m (RER C, cf. SncfPk). */
    private function parsePk(?string $pk): ?int
    {
        return SncfPk::meters($pk);
    }
}
