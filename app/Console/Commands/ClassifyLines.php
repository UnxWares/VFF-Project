<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\LineClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Classe le trafic (voyageurs/fret/mixte/unknown) de chaque ligne et segment
 * d'une ère via fusion des sources SNCF officielles (liste-des-gares, types,
 * LGV, statut des tronçons), avec règles dures raccordement/LGV. Le trafic se
 * décide portion par portion : mixte entre les gares voyageurs desservies,
 * fret seul au-delà (cf. App\Services\PassengerPortions). Remplace vff:derive-traffic.
 *
 * Prérequis : `php artisan vff:import-sncf-refs` (peuple sncf_gares/types/catlig).
 */
class ClassifyLines extends Command
{
    protected $signature = 'vff:classify-lines {--era=} {--dry-run : calcule et affiche sans écrire}
        {--show=10 : lignes mixtes en partie à afficher}';

    protected $description = 'Classe le trafic des lignes/segments (SNCF gares + types + LGV, règles raccordement/LGV).';

    public function handle(LineClassifier $classifier): int
    {
        $era = $this->option('era')
            ? Era::where('year', $this->option('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $dry = (bool) $this->option('dry-run');
        $this->info("Classification trafic - ère {$era->year} (id={$era->id})".($dry ? ' [DRY-RUN]' : ''));

        $s = $classifier->classifyEra($era->id, $dry);

        $this->newLine();
        $this->line("  Lignes traitées : {$s['lines']}");
        $this->line("  passenger : {$s['passenger']}");
        $this->line("  mixed     : {$s['mixed']}");
        $this->line("  freight   : {$s['freight']}");
        $this->line("  unknown   : {$s['unknown']}");
        $this->line("  (dont LGV : {$s['lgv']}, raccordements hérités : {$s['raccordements']})");

        // Trafic par portion : lignes mixtes sur une partie seulement (fret ou moteur ailleurs).
        $p = $s['portions'] ?? null;
        if ($p) {
            $this->line(sprintf('  Portions : %d lignes mixtes en partie (%s km hors portion voyageurs), %d tronçons coupés aux gares, %d gares voyageurs périmées écartées',
                $p['lines'], number_format($p['outside_km'], 1, ',', ' '), $p['split'], $p['stale_stations']));
            foreach (array_slice($p['report'], 0, (int) $this->option('show')) as $r) {
                $this->line(sprintf('    [%s] %s : %s km mixte, %s km hors portion%s', $r['code'], $r['name'],
                    number_format($r['mixed_km'], 1, ',', ' '), number_format($r['outside_km'], 1, ',', ' '),
                    $r['stale'] ? ' ; périmées : '.implode(', ', array_slice($r['stale'], 0, 3)) : ''));
            }
        }

        $report = $s['report'] ?? [];
        if ($report) {
            $this->warn('  '.count($report).' lignes à curer (fret par défaut en confiance faible, ou sans signal SNCF), ex. :');
            foreach (array_slice($report, 0, 10) as $r) {
                $this->line("    [{$r['code']}] {$r['name']}");
            }
        }

        if ($dry) {
            $this->comment('  (dry-run : aucune écriture)');
        } else {
            Cache::forget('home_stats');
        }

        return self::SUCCESS;
    }
}
