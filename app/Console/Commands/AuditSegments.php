<?php

namespace App\Console\Commands;

use App\Enums\AuditGrade;
use App\Services\SegmentAudit\AuditGrader;
use App\Services\SegmentAudit\SegmentAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Passe automatique de l'audit des tronçons (docs/AUDIT-TRONCONS.md) : quatre notes par tronçon
 * et par ère (classement, placement, exactitude, tracé), avec leurs preuves, rangées dans
 * `segment_audits`. Lecture seule sur les tables du moteur et les sources ; aucun appel réseau.
 * Règles et seuils : App\Services\SegmentAudit\AuditGrader.
 *
 *   php artisan vff:audit-segments --line=405000 --line=371000          # lignes témoins
 *   php artisan vff:audit-segments --era=2030 --dry-run                 # mesure sans rien écrire
 *   php artisan vff:audit-segments --shard=1/4 &  (… --shard=4/4)       # tout le réseau, 4 processus
 */
class AuditSegments extends Command
{
    protected $signature = 'vff:audit-segments
        {--line=* : Ligne à auditer : identifiant, code à 6 chiffres ou slug (répétable ; par défaut toutes les lignes qui ont des tronçons)}
        {--era=* : Année d\'une ère à auditer (répétable ; par défaut toutes les ères où la ligne a des tronçons)}
        {--shard= : Morceau i/n des lignes (identifiant modulo n égal à i - 1), pour plusieurs processus en parallèle}
        {--dry-run : Calcule et affiche les notes sans rien écrire}';

    protected $description = 'Audit des tronçons : classement, placement, exactitude et tracé de chaque tronçon, avec leurs preuves (lecture seule sur le moteur).';

    public function handle(SegmentAuditor $auditor): int
    {
        $shard = $this->shard();
        if ($shard === false) {
            $this->error('--shard attend « i/n » avec 1 ≤ i ≤ n (ex. 2/4).');

            return self::INVALID;
        }
        $eras = array_map('intval', (array) $this->option('era'));
        if ($eras !== [] && ($unknown = array_diff($eras, DB::table('eras')->pluck('year')->map(fn ($y): int => (int) $y)->all()))) {
            $this->error('Ère inconnue : '.implode(', ', $unknown).'.');

            return self::INVALID;
        }
        $lineIds = $this->lineIds($shard);
        if ($lineIds === null) {
            return self::INVALID;
        }

        $dry = (bool) $this->option('dry-run');
        $this->info(sprintf('%d ligne(s) à auditer%s (règles %s)', count($lineIds), $dry ? ', à blanc' : '', AuditGrader::RULE_VERSION));
        $started = microtime(true);
        $totals = ['segments' => 0, 'rows' => 0];
        $byGrade = [];
        $bar = count($lineIds) > 20 ? $this->output->createProgressBar(count($lineIds)) : null;
        foreach ($lineIds as $lineId) {
            $audit = $auditor->auditLine($lineId, $eras !== [] ? $eras : null);
            foreach ($audit as $era) {
                foreach ($era['segments'] as $s) {
                    $totals['segments']++;
                    foreach (['classification', 'placement', 'accuracy', 'trace'] as $check) {
                        $byGrade[$check][$s[$check.'_grade']] = ($byGrade[$check][$s[$check.'_grade']] ?? 0) + 1;
                    }
                }
                if ($bar === null) {
                    $this->line(sprintf('  %s, %d : %d tronçon(s), classement %s, placement %s, exactitude %s, tracé %s, score %d',
                        $this->lineLabel($lineId), $era['year'], count($era['segments']), $era['line']['classification_grade'],
                        $era['line']['placement_grade'], $era['line']['accuracy_grade'], $era['line']['trace_grade'], $era['line']['score']));
                }
            }
            if (! $dry && $audit !== []) {
                $totals['rows'] += $auditor->write($lineId, $audit);
            }
            $bar?->advance();
        }
        $bar?->finish();
        $this->newLine();

        $this->table(['Contrôle', ...AuditGrade::values()], array_map(fn (string $check): array => [
            $check, ...array_map(fn (string $g): int => $byGrade[$check][$g] ?? 0, AuditGrade::values()),
        ], ['classification', 'placement', 'accuracy', 'trace']));
        $this->info(sprintf('%d tronçon(s) audité(s)%s en %.1f s.', $totals['segments'],
            $dry ? ' (rien d\'écrit)' : ', '.$totals['rows'].' ligne(s) d\'audit écrites', microtime(true) - $started));

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int}|false|null [i, n], null sans --shard, false si invalide */
    private function shard(): array|false|null
    {
        $raw = $this->option('shard');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! preg_match('#^(\d+)/(\d+)$#', (string) $raw, $m) || (int) $m[2] < 1 || (int) $m[1] < 1 || (int) $m[1] > (int) $m[2]) {
            return false;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /**
     * @param  array{0: int, 1: int}|null  $shard
     * @return list<int>|null
     */
    private function lineIds(?array $shard): ?array
    {
        $query = DB::table('lines')->whereExists(fn ($q) => $q->selectRaw('1')->from('line_segments')->whereColumn('line_segments.line_id', 'lines.id'));
        $wanted = array_map('strval', (array) $this->option('line'));
        if ($wanted !== []) {
            $ids = [];
            foreach ($wanted as $w) {
                $id = DB::table('lines')->where('slug', $w)->orWhere('code', $w)
                    ->when(ctype_digit($w) && strlen($w) < 6, fn ($q) => $q->orWhere('id', (int) $w))
                    ->orderBy('id')->value('id');
                if ($id === null) {
                    $this->error("Ligne inconnue : {$w}.");

                    return null;
                }
                $ids[] = (int) $id;
            }
            $query->whereIn('id', $ids);
        }
        if ($shard !== null) {
            $query->whereRaw('id % ? = ?', [$shard[1], $shard[0] - 1]);
        }

        return $query->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function lineLabel(int $lineId): string
    {
        $line = DB::table('lines')->where('id', $lineId)->first(['code', 'slug']);

        return (string) ($line->code ?? $line->slug ?? $lineId);
    }
}
