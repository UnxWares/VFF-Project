<?php

namespace App\Console\Commands;

use App\Services\SectionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Calcule line_events.section_geom (portion de ligne concernée) pour les
 * événements en attente, TOUTES SOURCES (wikipedia, lignes-oubliees, ohm…).
 *
 * Pourquoi : le moteur par décennie découpe chaque ligne selon ses
 * événements ; il lui faut la géométrie de chaque portion, pas seulement
 * « de X à Y » ou « PK 54,320 à 64,100 ». Méthodes et garde-fous :
 * cf. App\Services\SectionResolver (PK calés sur gares + passages à niveau,
 * lieux géocodés, toute la ligne, échec motivé).
 *
 * Prérequis conseillé : vff:import-sncf-level-crossings (points de contrôle PK).
 *
 *   php artisan vff:resolve-event-sections                       # en attente (NULL / pending)
 *   php artisan vff:resolve-event-sections --line=956000 --all   # tout recalculer pour une ligne
 *   php artisan vff:resolve-event-sections --retry               # + réessayer les 'unresolved'
 *   php artisan vff:resolve-event-sections --recheck-whole       # revérifier les « toute la ligne »
 *   php artisan vff:resolve-event-sections --shard=0/4 &  (… --shard=3/4)  # 4 processus en parallèle
 *   php artisan vff:resolve-event-sections --retry --dry-run --report=storage/app/reports/resolve.csv
 *       # à blanc (chaque ligne dans une transaction annulée) : CSV des événements dont la résolution changerait
 */
class ResolveEventSections extends Command
{
    protected $signature = 'vff:resolve-event-sections
        {--line= : Code SNCF, slug ou id d\'une ligne}
        {--source= : Seulement les événements de cette source (wikipedia, lignes-oubliees…)}
        {--retry : Réessaie aussi les événements « unresolved » (tracés / gares enrichis depuis)}
        {--all : Recalcule tous les événements (sauf portions déclarées inconnues par la source)}
        {--recheck-whole : Revérifie les événements « whole_line » (garde-fous réouverture / portion citée)}
        {--trace=* : Ids de source_traces à ajouter au tracé porteur (tests, rattachement manuel)}
        {--shard= : Partition « k/n » des lignes (line_id % n = k) pour paralléliser sur plusieurs processus}
        {--dry-run : Calcule sans rien enregistrer (transaction annulée par ligne)}
        {--report= : Fichier CSV des événements dont la résolution ou la note change (avant / après)}';

    protected $description = 'Résout la portion géographique (section_geom) des événements de lignes.';

    public function handle(SectionResolver $resolver): int
    {
        $scope = $this->option('all') ? 'all' : ($this->option('retry') ? 'retry' : ($this->option('recheck-whole') ? 'whole' : 'pending'));
        $resolver->extraTraceIds = array_map('intval', array_filter(explode(',', implode(',', $this->option('trace')))));
        $q = DB::table('line_events')->select('line_id')->distinct();
        $src = $this->option('source');
        if ($src) {
            $q->where('source', $src);
        }
        if ($scope === 'pending') {
            $q->where(fn ($w) => $w->whereNull('section_resolution')->orWhere('section_resolution', 'pending'));
        } elseif ($scope === 'whole') {
            $q->where('section_resolution', 'whole_line');
        } elseif ($scope === 'all') {
            $q->where(fn ($w) => $w->whereNull('section_resolution')->orWhere('section_resolution', '<>', 'trace'));
        } elseif ($scope === 'retry') {
            $q->where(fn ($w) => $w->whereNull('section_resolution')->orWhereIn('section_resolution', ['pending', 'unresolved']));
        }
        if ($ref = $this->option('line')) {
            $ids = DB::table('lines')->where('code', $ref)->orWhere('slug', $ref)
                ->orWhere('id', ctype_digit($ref) && strlen($ref) < 6 ? (int) $ref : 0)->pluck('id');
            $q->whereIn('line_id', $ids);
        }
        if (preg_match('#^(\d+)/(\d+)$#', (string) $this->option('shard'), $sh) && (int) $sh[2] > 0) {
            $q->whereRaw('line_id % ? = ?', [(int) $sh[2], (int) $sh[1]]);
        }
        $lineIds = $q->orderBy('line_id')->pluck('line_id');
        $this->info("Lignes à traiter : {$lineIds->count()} (portée : {$scope})");

        $dry = (bool) $this->option('dry-run');
        $report = null;
        if ($path = $this->option('report')) {
            $path = str_starts_with($path, '/') ? $path : base_path($path);
            @mkdir(dirname($path), 0775, true);
            $report = fopen($path, 'w');
            fputcsv($report, ['id', 'line_id', 'source', 'type', 'event_date', 'section_from', 'section_to', 'pk_start_m', 'pk_end_m',
                'before', 'after', 'length_m', 'note_before', 'note_after', 'quote']);
        }
        if ($dry) {
            $resolver->prepare();
        } // tables de noms hors transaction

        $total = [];
        $bar = $this->output->createProgressBar($lineIds->count());
        foreach ($lineIds as $id) {
            $before = $report ? DB::table('line_events')->where('line_id', $id)->pluck('section_resolution_note', 'id')->all() : [];
            $beforeRes = $report ? DB::table('line_events')->where('line_id', $id)->pluck('section_resolution', 'id')->all() : [];
            if ($dry) {
                DB::beginTransaction();
            }
            try {
                foreach ($resolver->resolveLine((int) $id, $src ?: null, $scope) as $k => $n) {
                    $total[$k] = ($total[$k] ?? 0) + $n;
                }
                if ($report) {
                    foreach (DB::table('line_events')->where('line_id', $id)->orderBy('id')->get() as $e) {
                        if (($beforeRes[$e->id] ?? null) === $e->section_resolution && ($before[$e->id] ?? null) === $e->section_resolution_note) {
                            continue;
                        }
                        fputcsv($report, [$e->id, $e->line_id, $e->source, $e->type, $e->event_date, $e->section_from, $e->section_to,
                            $e->section_pk_start_m, $e->section_pk_end_m, $beforeRes[$e->id] ?? null, $e->section_resolution, $e->section_length_m,
                            $before[$e->id] ?? null, $e->section_resolution_note, mb_substr((string) $e->source_quote, 0, 300)]);
                    }
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("Ligne {$id} : ".mb_substr($e->getMessage(), 0, 300));
            } finally {
                if ($dry) {
                    DB::rollBack();
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        if ($report) {
            fclose($report);
        }
        if ($dry) {
            $this->warn('À blanc : rien n\'a été enregistré.');
        }

        arsort($total);
        $sum = array_sum($total) ?: 1;
        $this->table(['résolution', 'événements', '%'], array_map(
            fn ($k, $v) => [$k, $v, number_format(100 * $v / $sum, 1, ',', '')], array_keys($total), $total,
        ));

        return self::SUCCESS;
    }
}
