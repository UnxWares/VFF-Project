<?php

namespace App\Console\Commands;

use App\Services\Greenways\GreenwayLinker;
use App\Services\Greenways\GreenwayTiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattache les voies vertes OSM aux lignes disparues ou fermées dont elles reprennent l'emprise
 * (table `line_greenways`). Lit `present_osm_ways` (`vff:import-osm-greenways`), `present_states`
 * (`vff:detect-present-states`) et l'axe de référence des lignes ; n'écrit que `line_greenways`.
 * Seuils et méthode : voir App\Services\Greenways\GreenwayLinker.
 *
 *   php artisan vff:link-greenways
 *   php artisan vff:link-greenways --line=436000 --line=rfn-460000 --dry-run
 */
class LinkGreenways extends Command
{
    protected $signature = 'vff:link-greenways
        {--line=* : limite le calcul à ces lignes (code, slug ou identifiant), pour essayer}
        {--dry-run : calcule et affiche le bilan sans rien écrire}';

    protected $description = 'Rattache les voies vertes OSM aux lignes disparues ou fermées dont elles reprennent l\'emprise.';

    public function handle(GreenwayLinker $linker, GreenwayTiles $tiles): int
    {
        $lineIds = null;
        if ($this->option('line')) {
            $lineIds = $this->lineIds((array) $this->option('line'));
            if ($lineIds === []) {
                $this->error('Aucune ligne ne correspond à --line.');

                return self::FAILURE;
            }
        }

        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $result = $linker->link($lineIds, $dry);
        if (! $dry) {
            $tiles->invalidate();
        }

        $this->table(['Rattachements (ligne, way)', 'Lignes', 'Longueur commune (km)', 'Rattachements retirés'], [
            [$result['links'], $result['lines'], $result['shared_km'], $result['removed']],
        ]);
        $this->info(sprintf('%s en %.1f s.', $dry ? 'Calculé, rien écrit (--dry-run)' : 'Terminé', microtime(true) - $started));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $refs
     * @return list<int>
     */
    private function lineIds(array $refs): array
    {
        return DB::table('lines')
            ->whereIn('code', $refs)->orWhereIn('slug', $refs)
            ->orWhereIn('id', array_map('intval', array_filter($refs, fn (string $r): bool => ctype_digit($r) && strlen($r) < 6)))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
