<?php

namespace App\Console\Commands;

use App\Services\Companies\CompanyFigures;
use Illuminate\Console\Command;

/**
 * Calcule les fiches des compagnies de chemins de fer (/companies, /companies/{slug}, `GET /api/v1/companies`,
 * liens des fiches de lignes) et les range dans le cache, sans expiration. Lecture seule en base (quelques dizaines de
 * secondes, requêtes de 30 secondes au plus) ; à lancer à la main après un import d'événements de concession ou une
 * modification de `database/data/companies.php` : aucune planification.
 *
 *   php artisan vff:compute-company-figures
 *   php artisan vff:compute-company-figures --dry-run      (calcule et affiche, ne touche pas au cache)
 */
class ComputeCompanyFigures extends Command
{
    protected $signature = 'vff:compute-company-figures
        {--dry-run : calcule et affiche sans ranger dans le cache}';

    protected $description = 'Calcule les fiches des compagnies de chemins de fer (lignes rattachées par événement daté, réseau par décennie, couverture) et les range dans le cache.';

    public function handle(CompanyFigures $figures): int
    {
        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $figures->onProgress(fn (string $line) => $this->line($line));
        $result = $dry ? $figures->compute() : $figures->refresh();

        $this->table(
            ['Compagnie', 'Créée', 'Fin', 'Lignes', 'dont comptées', 'Sommet km', 'Année', 'Km aujourd\'hui'],
            array_map(fn (array $c): array => [
                $c['slug'], $c['founded']['date'], $c['ended']['date'] ?? '-', $c['lines'], $c['lines_counted'],
                $c['km_peak'], $c['peak_year'] ?? '-', $c['km_today'],
            ], $result['index']['companies']),
        );
        $this->table(
            ['Ère', 'Lignes ouvertes', 'Rattachées', 'Km ouverts', 'Km rattachés', 'Part'],
            array_map(fn (array $e): array => [
                $e['year'], $e['lines_open'], $e['lines_attached'], $e['km_open'], $e['km_attached'], $e['share'] === null ? '-' : round($e['share'] * 100, 1).' %',
            ], $result['index']['coverage']),
        );
        $q = $result['index']['quality'];
        $this->line(sprintf(
            'Événements lus : %d, rattachés : %d (avant la création : %d, après la fin : %d, doublons : %d, libellés sans compagnie : %d). Fins : %d datées, %d par successeur, %d par fin de compagnie, %d ouvertes, %d inconnues.',
            $q['events_read'], $q['attached'], $q['before_founding'], $q['after_end'], $q['duplicates'], $q['unmatched_total'],
            $q['end_event'], $q['end_successor'], $q['end_company'], $q['end_open'], $q['end_unknown'],
        ));
        $this->info(sprintf('%d compagnies calculées en %.1f s%s.', count($result['sheets']), microtime(true) - $started, $dry ? ' [DRY-RUN, cache inchangé]' : ' et rangées dans le cache'));

        return self::SUCCESS;
    }
}
