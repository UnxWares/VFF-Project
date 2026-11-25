<?php

namespace App\Console\Commands;

use App\Services\Signalling\KvbPieces;
use App\Services\Signalling\SignallingMap;
use App\Services\Signalling\SignallingRegimeRules;
use App\Services\Signalling\SncfBlockLabels;
use App\Services\Sncf\SncfPk;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

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
 *     KVB n'est ajouté que sur les lignes classiques (Phase 2), jamais sur LGV, et seulement
 *     entre ses PK : une tranche couverte en partie est coupée aux PK du KVB (KvbPieces).
 *   - plus aucune curation ETCS hardcodée : l'ETCS est dans le dataset SNCF.
 */
class ImportSncfSignalling extends Command
{
    protected $signature = 'vff:import-sncf-signalling {--era=}
        {--from-cache : jeux SNCF du cache local s\'il existe (téléchargés et mis en cache sinon) : étape hors ligne de vff:rebuild-history}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-signalling)}
        {--dry-run : calcule et compte sans rien écrire (transaction annulée, carte non recalculée)}';

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

    /** Colonne `rg_troncon` présente (migration 2026_12_21_000000 passée) : le tronçon est écrit et le KVB rapproché par tronçon. */
    private bool $withPart = false;

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
        $this->withPart = Schema::hasColumn('line_signalling', 'rg_troncon');

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

        DB::beginTransaction();
        try {
            DB::table('line_signalling')->where('era_id', $eraId)->delete();

            $this->info('  ► [1/2] Cantonnement (mode-de-cantonnement-des-lignes)…');
            $totalCant = $this->importCantonnement($eraId, $cantonnement);
            $this->info("    ✓ {$totalCant} tranches insérées (dont {$this->regimeDeduced} déduites du régime d'exploitation)");
            if ($this->unknownLabels !== []) {
                $this->warn('    Libellés inconnus, tranches ignorées : '.implode(' ; ', array_keys($this->unknownLabels)));
            }

            $this->info('  ► [2/2] KVB (lignes-equipees-de-kvb, hors LGV)…');
            [$totalKvb, $split] = $this->importKvb($eraId, $kvb);
            $this->info("    ✓ {$totalKvb} tranches équipées KVB ({$split} découpées aux PK du KVB)");

            $st = DB::selectOne('
                SELECT COUNT(*) AS total, COUNT(DISTINCT line_id) AS lines,
                       SUM(CASE WHEN kvb_equipped THEN 1 ELSE 0 END) AS kvb_count
                FROM line_signalling WHERE era_id = ?
            ', [$eraId]);
            $this->info("État final : {$st->total} tranches sur {$st->lines} lignes ({$st->kvb_count} avec KVB)");
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
        if ($this->option('dry-run')) {
            // À blanc : tout est annulé, la signalisation de l'ère et la carte restent telles quelles.
            DB::rollBack();
            $this->info('À blanc : rien écrit.');

            return self::SUCCESS;
        }
        DB::commit();

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

                $part = self::part($r);
                foreach ($parts as [$from, $to, $sys, $note]) {
                    $prot = SncfBlockLabels::protection($sys);
                    $inserts[] = ($this->withPart ? ['rg_troncon' => $part] : []) + [
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

    /**
     * KVB du jeu `lignes-equipees-de-kvb` posé sur les tranches de cantonnement de sa ligne et de son tronçon, hors LGV et
     * hors cabine. Une tranche que le KVB ne couvre qu'en partie est coupée aux PK du KVB (KvbPieces) : le morceau couvert
     * porte le KVB, le reste non (avant, toute la tranche le portait dès qu'une portion la touchait).
     *
     * @param  list<array<string, mixed>>  $records
     * @return array{0: int, 1: int} tranches équipées KVB écrites, tranches découpées
     */
    private function importKvb(int $eraId, array $records): array
    {
        $lineIdCache = [];
        /** @var array<int, list<array{part: ?int, from: int, to: int}>> $portions */
        $portions = [];

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
            $portions[$lineId][] = ['part' => self::part($r), 'from' => min($pkStart, $pkEnd), 'to' => max($pkStart, $pkEnd)];
        }

        $equipped = 0;
        $split = 0;
        foreach ($portions as $lineId => $list) {
            $rows = DB::table('line_signalling')->where('era_id', $eraId)->where('line_id', $lineId)->orderBy('id')->get();
            foreach ($rows as $row) {
                // Ne pas ajouter KVB sur une tranche en cabine (TVM/ETCS).
                if (SncfBlockLabels::isCab((string) $row->signalling)) {
                    continue;
                }
                // Même tronçon seulement : deux tronçons d'une ligne qui partagent des PK sont deux endroits (890000 : le
                // KVB du tronçon 1, de 8+120 à 152+328, ne vaut pas pour le tronçon 2 de 60+215 à 75+559).
                $rowPart = $this->withPart && $row->rg_troncon !== null ? (int) $row->rg_troncon : null;
                $own = [];
                foreach ($list as $p) {
                    if ($rowPart === null || $p['part'] === null || $p['part'] === $rowPart) {
                        $own[] = [$p['from'], $p['to']];
                    }
                }
                $pieces = KvbPieces::split((int) $row->pk_start_m, (int) $row->pk_end_m, $own);
                if ($pieces === []) {
                    continue;
                }

                $prot = $row->train_protection ? json_decode((string) $row->train_protection, true) : [];
                if (! in_array('KVB', $prot, true)) {
                    $prot[] = 'KVB';
                }
                $cut = count($pieces) > 1;
                $split += $cut ? 1 : 0;
                $note = $cut ? trim(((string) $row->note).' ; découpée aux PK du KVB (SNCF Open Data, lignes-equipees-de-kvb)', ' ;') : $row->note;
                foreach ($pieces as $i => $piece) {
                    $values = [
                        'pk_start_m' => $piece['from'],
                        'pk_end_m' => $piece['to'],
                        'train_protection' => $piece['kvb'] ? json_encode($prot) : $row->train_protection,
                        'kvb_equipped' => $piece['kvb'],
                        'note' => $note,
                        'updated_at' => now(),
                    ];
                    if ($i === 0) {
                        DB::table('line_signalling')->where('id', $row->id)->update($values);
                    } else {
                        $copy = (array) $row;
                        unset($copy['id']);
                        DB::table('line_signalling')->insert(array_merge($copy, $values, ['created_at' => now()]));
                    }
                    $equipped += $piece['kvb'] ? 1 : 0;
                }
            }
        }

        return [$equipped, $split];
    }

    /**
     * Rang du tronçon de SNCF Réseau d'un enregistrement (`rg_troncon`), null s'il manque.
     *
     * @param  array<string, mixed>  $record
     */
    private static function part(array $record): ?int
    {
        $rank = $record['rg_troncon'] ?? null;

        return is_numeric($rank) ? (int) $rank : null;
    }

    /** "016+953" → 16953 m ; "000-168" → -168 m ; "D+841" → 3841 m (RER C, cf. SncfPk). */
    private function parsePk(?string $pk): ?int
    {
        return SncfPk::meters($pk);
    }
}
