<?php

namespace App\Console\Commands;

use App\Services\NetworkChronology;
use Illuminate\Console\Command;

/**
 * Calcule la « Chronologie du rail en France » (page /chronology, `GET /api/v1/chronology`) et la range dans le
 * cache, sans expiration, avec sa date de calcul. Lecture seule en base (quelques secondes) ; à lancer à la main
 * après un import d'événements, d'accidents ou de matériel : aucune planification.
 *
 *   php artisan vff:compute-network-chronology
 *   php artisan vff:compute-network-chronology --dry-run   (calcule et affiche, ne touche pas au cache)
 */
class ComputeNetworkChronology extends Command
{
    protected $signature = 'vff:compute-network-chronology {--dry-run : calcule et affiche sans ranger dans le cache}';

    protected $description = 'Calcule la chronologie nationale du rail (ouvertures, fermetures, électrifications, signalisation, accidents, matériel) et la range dans le cache.';

    public function handle(NetworkChronology $chronology): int
    {
        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $result = $dry ? $chronology->compute() : $chronology->refresh();

        $rows = [];
        foreach ($result['decades'] as $decade => $facts) {
            $row = ['decade' => $decade];
            foreach (NetworkChronology::KINDS as $kind) {
                $shown = 0;
                $hidden = 0;
                foreach ($result['meta']['counts'] as $year => $byKind) {
                    if (intdiv((int) $year, 10) * 10 === $decade && isset($byKind[$kind]['all'])) {
                        [$s, $h] = $byKind[$kind]['all'];
                        $shown += $s;
                        $hidden += $h;
                    }
                }
                $row[$kind] = $hidden > 0 ? "{$shown} (+{$hidden})" : (string) $shown;
            }
            $rows[] = $row;
        }
        $this->table(array_merge(['Décennie'], NetworkChronology::KINDS), $rows);
        $this->line('Entre parenthèses : faits comptés mais non détaillés (aucune source publiable).');
        $this->info(sprintf('Chronologie calculée le %s en %.1f s%s.', $result['meta']['computed_at'], microtime(true) - $started,
            $dry ? ' [DRY-RUN, cache inchangé]' : ' et rangée dans le cache'));

        return self::SUCCESS;
    }
}
