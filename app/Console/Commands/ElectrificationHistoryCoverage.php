<?php

namespace App\Console\Commands;

use App\Models\Line;
use App\Services\Traction\LineElectrificationHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Couverture de l'histoire de l'électrification (lecture seule) : combien de lignes électrifiées aujourd'hui
 * ont au moins une date sûre, combien de faits sont écartés et pourquoi. Voir LineElectrificationHistory.
 */
class ElectrificationHistoryCoverage extends Command
{
    protected $signature = 'vff:electrification-history-coverage {--line= : code ou slug d\'une ligne : détail de ses faits} {--missing=0 : liste N lignes électrifiées sans date}';

    protected $description = 'Mesure (lecture seule) la couverture de l\'histoire de l\'électrification : lignes électrifiées avec une date sûre.';

    public function handle(LineElectrificationHistory $history): int
    {
        DB::statement("SET statement_timeout = '30s'");
        if ($one = $this->option('line')) {
            $line = Line::where('code', $one)->orWhere('slug', $one)->firstOrFail();
            $this->line($line->code.' '.$line->name.' : '.($line->electrified ? 'électrifiée' : 'non électrifiée').' '.$line->voltage_v);
            foreach ($history->ranges((string) $line->code) as $r) {
                $this->line(sprintf('  SNCF %s à %s  %s V  %s', $r['pk_start_label'], $r['pk_end_label'], $r['voltage_v'], $r['system']));
            }
            foreach ($history->history($line) as $f) {
                $s = $f['section'];
                $this->line(sprintf('  %s %-5s %-17s %-6s %s %s  %s', $f['date'], $f['date_precision'], $f['kind'], $f['voltage_v'] ?? '-', $f['reliability'],
                    $s === null ? $f['scope'] : trim(($s['from'] ?? '?').' - '.($s['to'] ?? '?').' PK '.($s['pk_start_m'] ?? '?').'-'.($s['pk_end_m'] ?? '?')), (string) $f['source']['excerpt']));
            }

            return self::SUCCESS;
        }
        $lines = Line::where('electrified', true)->orderBy('code')->get(['id', 'code', 'slug', 'name', 'electrified', 'voltage_v']);
        $withDate = $withRanges = $both = $withFact = $mainLines = $mainDated = 0;
        $kinds = ['electrification' => 0, 'conversion' => 0, 'deelectrification' => 0];
        $missing = [];
        foreach ($lines as $line) {
            $facts = $history->history($line);
            $dated = array_filter($facts, fn (array $f): bool => $f['kind'] !== 'deelectrification');
            $ranges = $history->ranges((string) $line->code) !== [];
            $withDate += $dated !== [] ? 1 : 0;
            $withRanges += $ranges ? 1 : 0;
            $both += $dated !== [] && $ranges ? 1 : 0;
            $withFact += $facts !== [] ? 1 : 0;
            // Raccordements, voies-mères, sauts-de-mouton, boucles : liaisons électrifiées avec la ligne qu'elles servent.
            $link = (bool) preg_match('/^(?:raccordement|voie|saut|boucle|bifurcation|shunt|contournement)/iu', (string) $line->name);
            $mainLines += $link ? 0 : 1;
            $mainDated += ! $link && $dated !== [] ? 1 : 0;
            if ($dated === [] && ! $link) {
                $missing[] = $line->code.' '.$line->name;
            }
        }
        // Faits rendus sur toutes les lignes (une ligne désélectrifiée n'est plus « électrifiée » aujourd'hui).
        foreach (Line::whereIn('id', DB::table('line_events')->where('type', 'electrification')->select('line_id'))->get(['id', 'code', 'electrified', 'voltage_v']) as $line) {
            foreach ($history->history($line) as $f) {
                $kinds[$f['kind']]++;
            }
        }
        $this->info(sprintf('Hors raccordements, voies-mères et boucles : %d lignes électrifiées, %d avec une date sûre (%.1f %%).',
            $mainLines, $mainDated, $mainLines ? 100 * $mainDated / $mainLines : 0));
        $raw = DB::table('line_events')->where('type', 'electrification')->count();
        $all = array_sum($kinds);
        $this->info(sprintf('Lignes électrifiées : %d ; avec au moins une date de mise sous tension sûre : %d (%.1f %%) ; avec tranches SNCF : %d ; les deux : %d.',
            $lines->count(), $withDate, $lines->count() ? 100 * $withDate / $lines->count() : 0, $withRanges, $both));
        $this->line(sprintf('Faits rendus : %d (mises sous tension %d, conversions %d, désélectrifications %d) sur %d événements bruts ; %d écartés.',
            $all, $kinds['electrification'], $kinds['conversion'], $kinds['deelectrification'], $raw, $raw - $all));
        foreach (array_slice($missing, 0, (int) $this->option('missing')) as $m) {
            $this->line('  sans date : '.$m);
        }

        return self::SUCCESS;
    }
}
