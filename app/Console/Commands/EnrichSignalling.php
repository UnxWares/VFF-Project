<?php

namespace App\Console\Commands;

use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrichit la signalisation et le contrôle de vitesse du réseau ferroviaire.
 *
 * VOCABULAIRE STRICT :
 *
 *   `signalling` = système de **cantonnement** :
 *     - BAL : Block Automatique Lumineux
 *     - BAPR : Block Automatique à Permissivité Restreinte
 *     - BAPR-DV : BAPR à Double Voie
 *     - BM : Block Manuel
 *     - BMVU : Block Manuel à Voie Unique
 *     - CAPI : Cantonnement Assisté par Informatique
 *     - cantonnement_telephonique : pour les très petites lignes
 *
 *   `train_protection` (array JSON) = systèmes de **contrôle de vitesse / protection** :
 *     - KVB : Contrôle de Vitesse par Balises (système français classique)
 *     - KVBP : KVB Évolutif
 *     - TVM-300 : Transmission Voie-Machine 300 km/h (LGV Sud-Est, Atlantique)
 *     - TVM-430 : Transmission Voie-Machine 430 km/h (LGV Nord, Méd., …)
 *     - ETCS_L1 / ETCS_L2 : European Train Control System (composants d'ERTMS)
 *     - ERTMS_L1 / ERTMS_L2 : European Rail Traffic Management System (ETCS + GSM-R)
 *     - RPS : Répétition Ponctuelle des Signaux (ancien système, encore utilisé)
 *     - ASFA : protection de signaux espagnole (cas frontaliers)
 *     - GSM-R : Global System for Mobile Communications - Railway
 *
 *   KVB N'EST JAMAIS un `signalling`. C'est un contrôle de vitesse séparé.
 *
 * Workflow :
 *   1. CURATION précise des LGV par slug RFN (signalisation + protection exacte)
 *   2. FALLBACK par règles vitesse + électrification pour le reste
 *   3. AGRÉGATION en tranches PK dans la table `line_signalling`
 */
class EnrichSignalling extends Command
{
    protected $signature = 'vff:enrich-signalling {--era=}';

    protected $description = 'Enrichit signalisation + protection par tranche PK.';

    /**
     * Curation des LGV françaises.
     *
     * Sur LGV : **signalisation en cabine**, pas de BAL au sol.
     * Le `signalling` est le système de cabine (TVM-300, TVM-430,
     * ETCS_L1 ou ETCS_L2), qui assure à la fois le cantonnement et
     * la transmission au train.
     *
     * `train_protection` complète :
     *   - KVB sur les zones d'engagement vers lignes classiques
     *   - ERTMS_L1/L2 quand ETCS est présent (ETCS est une composante d'ERTMS)
     *
     * Format : slug RFN => [signalling, [train_protection_array], label].
     */
    // Sur LGV, la signalisation de cabine (TVM ou ETCS) assure À LA FOIS le
    // cantonnement ET la protection : PAS de KVB (le KVB équipe le réseau
    // classique sous BAL/BAPR). La protection = le système de cabine lui-même.
    private const LGV_CURATION = [
        // ─── LGV en TVM ────────────────────────────────────────────────
        'rfn-752000' => ['TVM-300', ['TVM-300'], 'LGV Sud-Est (Combs-la-Ville → Saint-Louis, 1981)'],
        'rfn-431000' => ['TVM-300', ['TVM-300'], 'LGV Atlantique (Paris-Montparnasse → Monts, 1989)'],
        'rfn-216000' => ['TVM-430', ['TVM-430'], 'LGV Nord (Fretin → Fréthun, 1993)'],
        'rfn-226000' => ['TVM-430', ['TVM-430'], 'LGV Nord (Gonesse → Lille-Frontière)'],
        'rfn-226310' => ['TVM-430', ['TVM-430'], 'Raccordement interconnexion Nord-Sud'],
        'rfn-834000' => ['TVM-430', ['TVM-430'], 'LGV Méditerranée (Angles → Lattes, 2001)'],
        'rfn-834310' => ['TVM-430', ['TVM-430'], 'LGV Méditerranée'],
        'rfn-834311' => ['TVM-430', ['TVM-430'], 'LGV Méditerranée'],
        'rfn-429000' => ['TVM-430', ['TVM-430'], 'LGV Atlantique (Courtalain → Connerré)'],
        'rfn-431320' => ['TVM-430', ['TVM-430'], 'Raccordement de Montlouis'],

        // ─── LGV en ETCS / ERTMS (récentes) ───────────────────────────
        'rfn-834340' => ['ETCS_L1', ['ERTMS_L1'], 'LGV CNM (Nîmes → Montpellier, 2018)'],
        'rfn-005000' => ['ETCS_L2', ['ERTMS_L2'], 'LGV Est européenne (Paris → Strasbourg, 2007)'],
        'rfn-014000' => ['ETCS_L2', ['ERTMS_L2'], 'LGV Rhin-Rhône (Mulhouse → Dijon, 2011)'],
        'rfn-566000' => ['ETCS_L2', ['ERTMS_L2'], 'LGV SEA (Tours → Bordeaux, 2017)'],
        'rfn-408000' => ['ETCS_L2', ['ERTMS_L2'], 'LGV BPL (Le Mans → Rennes, 2017)'],
    ];

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

        $this->info("Enrichissement signalisation (era_id={$eraId})");

        // ────────────────────────────────────────────────────────────────
        // PHASE 1 - Curation LGV (par slug RFN)
        // ────────────────────────────────────────────────────────────────
        $this->info('  ► [1/3] Curation LGV…');
        $curated = 0;
        foreach (self::LGV_CURATION as $slug => [$sig, $prot, $label]) {
            $lineId = DB::table('lines')->where('slug', $slug)->value('id');
            if (! $lineId) {
                $this->warn("    ⚠ Slug {$slug} introuvable : {$label}");

                continue;
            }
            $n = DB::table('line_segments')
                ->where('era_id', $eraId)
                ->where('line_id', $lineId)
                ->update([
                    'signalling' => $sig,
                    'train_protection' => implode(',', $prot),
                ]);
            if ($n > 0) {
                $this->line("    ✓ {$label} → {$n} segments");
                $curated += $n;
            }
        }
        $this->info("    Total LGV : {$curated} segments curés");

        // ────────────────────────────────────────────────────────────────
        // PHASE 2 - Fallback par règles (segments non-LGV uniquement)
        //
        // Règles ordonnées par priorité décroissante :
        //   ≥ 270 km/h          → TVM-430 (signalisation cabine, pas de BAL)
        //   ≥ 220 km/h          → TVM-300 (signalisation cabine)
        //   électrifié, ≥120    → BAL + KVB (ligne classique rapide)
        //   ≥ 60 km/h           → BAPR + KVB (ligne classique secondaire)
        //   < 60 km/h ou NULL   → BMVU (cantonnement manuel à voie unique)
        // ────────────────────────────────────────────────────────────────
        $this->info('  ► [2/3] Fallback par règles…');
        $rules = [
            // LGV non identifiée par slug : on infère TVM par défaut. Signalisation en
            // cabine : la TVM assure AUSSI le contrôle de vitesse (jamais de KVB sur LGV).
            // Seuil LGV commun au projet (Lgv::MIN_SPEED_KMH, 250 km/h) : une ligne
            // classique monte jusqu'à 220 km/h, en BAL + KVB.
            ['TVM-430', 'TVM-430', 'max_speed_kmh >= 270'],
            ['TVM-300', 'TVM-300', 'max_speed_kmh >= '.Lgv::MIN_SPEED_KMH],
            // Lignes classiques électrifiées rapides
            ['BAL',     'KVB',     'electrified = TRUE AND max_speed_kmh >= 120'],
            // Lignes principales (électrifiées < 120 km/h ou non électrifiées ≥ 60 km/h)
            ['BAPR',    'KVB',     'max_speed_kmh >= 60'],
            // Petites lignes / industrielles
            ['BMVU',    null,      'max_speed_kmh IS NULL OR max_speed_kmh < 60'],
        ];
        foreach ($rules as [$sig, $prot, $cond]) {
            $protSql = $prot === null ? 'NULL' : "'{$prot}'";
            $n = DB::update(
                "UPDATE line_segments SET signalling=?, train_protection={$protSql}, updated_at=NOW()
                 WHERE era_id=? AND ({$cond}) AND signalling IS NULL",
                [$sig, $eraId],
            );
            if ($n > 0) {
                $label = $prot ? "{$sig} + {$prot}" : $sig;
                $this->line("    ✓ {$label} [{$cond}] → {$n} segments");
            }
        }

        // ────────────────────────────────────────────────────────────────
        // PHASE 3 - Agrégation par tranches PK dans line_signalling
        // ────────────────────────────────────────────────────────────────
        $this->info('  ► [3/3] Agrégation en tranches PK…');
        DB::table('line_signalling')->where('era_id', $eraId)->delete();

        $lineIds = DB::table('line_segments')
            ->where('era_id', $eraId)
            ->whereNotNull('signalling')
            ->distinct()
            ->pluck('line_id');

        $bar = $this->output->createProgressBar($lineIds->count());
        $totalRanges = 0;
        foreach ($lineIds as $lineId) {
            $totalRanges += $this->aggregateLineSignalling($lineId, $eraId);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("    ✓ {$totalRanges} tranches créées dans line_signalling");

        // Stats finales
        $st = DB::selectOne('
            SELECT
                (SELECT COUNT(*) FROM line_segments WHERE era_id = :era_a AND signalling IS NOT NULL) AS seg_sig,
                (SELECT COUNT(*) FROM line_segments WHERE era_id = :era_b) AS seg_total,
                (SELECT COUNT(DISTINCT line_id) FROM line_signalling WHERE era_id = :era_c) AS lines_sig
        ', ['era_a' => $eraId, 'era_b' => $eraId, 'era_c' => $eraId]);
        $this->info("État final : {$st->seg_sig}/{$st->seg_total} segments avec signalling, {$st->lines_sig} lignes dans line_signalling");

        return self::SUCCESS;
    }

    /**
     * Calcule les PK robustes (offset cumulé sur les sous-LineStrings) et
     * agrège les segments consécutifs partageant la même signalisation
     * en tranches dans `line_signalling`.
     */
    private function aggregateLineSignalling(int $lineId, int $eraId): int
    {
        // Calcul PK robuste : ST_Dump par parts + offset cumulé.
        $segments = DB::select(<<<'SQL'
            WITH merged AS (
                SELECT ST_LineMerge(ST_Collect(geom))::geometry AS g
                FROM line_segments
                WHERE line_id = :line_id_a AND era_id = :era_id_a
                  AND status IN ('in_service', 'single_track')
            ),
            parts AS (
                SELECT (ST_Dump(g)).path[1] AS rank,
                       (ST_Dump(g)).geom AS part_geom,
                       ST_Length((ST_Dump(g)).geom::geography) AS part_len
                FROM merged
            ),
            parts_with_offset AS (
                SELECT rank, part_geom, part_len,
                       COALESCE(SUM(part_len) OVER (ORDER BY rank
                                ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING), 0) AS offset_m
                FROM parts
            ),
            seg_with_part AS (
                SELECT DISTINCT ON (ls.id) ls.id, ls.signalling, ls.train_protection,
                       p.offset_m, p.part_len, p.part_geom, ls.geom
                FROM line_segments ls
                CROSS JOIN parts_with_offset p
                WHERE ls.line_id = :line_id_b AND ls.era_id = :era_id_b
                  AND ls.status IN ('in_service', 'single_track')
                  AND ls.signalling IS NOT NULL
                  AND ST_DWithin(ls.geom::geography, p.part_geom::geography, 50)
                ORDER BY ls.id, ST_Distance(ls.geom::geography, p.part_geom::geography) ASC
            )
            SELECT id, signalling, train_protection,
                   offset_m + ST_LineLocatePoint(part_geom, ST_StartPoint(geom::geometry)) * part_len AS pk_a,
                   offset_m + ST_LineLocatePoint(part_geom, ST_EndPoint(geom::geometry)) * part_len AS pk_b
            FROM seg_with_part
            SQL, [
            'line_id_a' => $lineId, 'era_id_a' => $eraId,
            'line_id_b' => $lineId, 'era_id_b' => $eraId,
        ]);

        if (empty($segments)) {
            return 0;
        }

        // Normaliser PK (start ≤ end) et trier
        foreach ($segments as $s) {
            $s->pk_start = (float) min($s->pk_a, $s->pk_b);
            $s->pk_end = (float) max($s->pk_a, $s->pk_b);
        }
        usort($segments, fn ($a, $b) => $a->pk_start <=> $b->pk_start);

        // Agrégation : fusionner segments consécutifs avec même (sig, prot)
        // Tolérance 500m sur le gap entre fin du précédent et début du suivant.
        $ranges = [];
        $cur = null;
        foreach ($segments as $s) {
            $key = $s->signalling.'|'.($s->train_protection ?? '');
            if ($cur && $cur['key'] === $key && $s->pk_start <= $cur['pk_end'] + 500) {
                $cur['pk_end'] = max($cur['pk_end'], $s->pk_end);
            } else {
                if ($cur) {
                    $ranges[] = $cur;
                }
                $cur = [
                    'key' => $key,
                    'signalling' => $s->signalling,
                    'train_protection' => $s->train_protection,
                    'pk_start' => $s->pk_start,
                    'pk_end' => $s->pk_end,
                ];
            }
        }
        if ($cur) {
            $ranges[] = $cur;
        }

        // Insertion
        $inserts = array_map(function ($r) use ($lineId, $eraId) {
            $protArr = $r['train_protection']
                ? array_map('trim', explode(',', $r['train_protection']))
                : [];
            // KVB / KVBP → flag rapide
            $kvb = (bool) array_intersect(['KVB', 'KVBP'], $protArr);

            return [
                'line_id' => $lineId,
                'era_id' => $eraId,
                'pk_start_m' => (int) round($r['pk_start']),
                'pk_end_m' => (int) round($r['pk_end']),
                'signalling' => $r['signalling'],
                'train_protection' => $protArr ? json_encode($protArr) : null,
                'kvb_equipped' => $kvb,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $ranges);

        if ($inserts) {
            DB::table('line_signalling')->insert($inserts);
        }

        return count($inserts);
    }
}
