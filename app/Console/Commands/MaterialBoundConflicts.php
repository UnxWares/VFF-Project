<?php

namespace App\Console\Commands;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Services\Materials\ElectrificationBound;
use App\Services\Materials\LineMaterialFacts;
use Illuminate\Console\Command;

/**
 * Séries électriques dont une période de circulation SOURCÉE commence avant la première électrification connue de
 * la ligne (ElectrificationBound) : la période garde sa date (une date sourcée ne se déplace jamais), la fiche
 * signale le conflit, et c'est une électrification plus ancienne qui manque probablement dans `line_events`. La
 * liste sert à compléter les électrifications. Lecture seule.
 *
 *   php artisan vff:material-bound-conflicts
 *   php artisan vff:material-bound-conflicts --line=909000
 *   php artisan vff:material-bound-conflicts --csv=storage/app/reports/materiel-borne-conflits.csv
 */
class MaterialBoundConflicts extends Command
{
    protected $signature = 'vff:material-bound-conflicts
        {--line= : code SNCF Réseau ou slug d\'une seule ligne}
        {--csv= : écrit aussi la liste dans ce fichier CSV}';

    protected $description = 'Liste les périodes sourcées de matériel électrique plus anciennes que la première électrification connue de la ligne.';

    public function handle(): int
    {
        $lineIds = LineMaterial::query()->whereHas('shownPeriods')->distinct()->pluck('line_id');
        $lines = Line::query()->whereIn('id', $lineIds)
            ->when($this->option('line') !== null, fn ($q) => $q->where(fn ($w) => $w->where('code', $this->option('line'))->orWhere('slug', $this->option('line'))))
            ->orderBy('code')->get();

        $rows = [];
        foreach ($lines as $line) {
            $links = LineMaterial::query()->where('line_id', $line->id)->with(['material', 'shownPeriods'])->get();
            $facts = LineMaterialFacts::forLine($line, $links);
            foreach ($facts->conflicts() as $materialId) {
                $bound = $facts->earliest($materialId);
                $material = $links->firstWhere('material_id', $materialId)?->material;
                foreach ($facts->periods($materialId) as $period) {
                    if ($bound !== null && ElectrificationBound::startsBefore($period, $bound)) {
                        $rows[] = [
                            'line' => (string) $line->code,
                            'line_name' => (string) $line->name,
                            'material' => (string) $material?->name,
                            'period_start' => $period['start_date'].' ('.$period['start_precision'].')',
                            'section' => trim(($period['section_from'] ?? '').' - '.($period['section_to'] ?? ''), ' -'),
                            'bound' => $bound['date'].' ('.$bound['precision'].')',
                            'bound_section' => (string) $bound['section'],
                            'source_url' => (string) ($period['source_url'] ?? ''),
                        ];
                    }
                }
            }
        }

        $this->table(['Ligne', 'Nom', 'Série', 'Début sourcé', 'Portion', 'Électrification connue', 'Section', 'Source'], $rows);
        $this->info(sprintf('%d période(s) sourcée(s) avant la première électrification connue, sur %d ligne(s).', count($rows), count(array_unique(array_column($rows, 'line')))));

        $csv = $this->option('csv');
        if (is_string($csv) && $csv !== '') {
            $out = fopen($csv, 'w');
            if ($out === false) {
                $this->error("Impossible d'écrire {$csv}.");

                return self::FAILURE;
            }
            fputcsv($out, array_keys($rows[0] ?? ['line' => 0, 'line_name' => 0, 'material' => 0, 'period_start' => 0, 'section' => 0, 'bound' => 0, 'bound_section' => 0, 'source_url' => 0]), escape: '');
            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '');
            }
            fclose($out);
        }

        return self::SUCCESS;
    }
}
