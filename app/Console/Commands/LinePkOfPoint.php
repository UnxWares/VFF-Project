<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Console\Command;

/**
 * Commande de test pour `BuildLineGeometry::pkOfPoint()`.
 *
 * Projette un point lon/lat sur la géométrie fusionnée d'une ligne et
 * affiche le PK (point kilométrique) par projection.
 *
 * Exemple :
 *   php artisan vff:line-pk-of-point --line=752000 --lon=2.5 --lat=48.6
 */
class LinePkOfPoint extends Command
{
    protected $signature = 'vff:line-pk-of-point
                            {--line= : Code ou slug de la ligne.}
                            {--lon= : Longitude du point.}
                            {--lat= : Latitude du point.}
                            {--era= : Année. Défaut: ère courante.}';

    protected $description = 'Affiche le PK par projection d\'un point sur une ligne (test pkOfPoint).';

    public function handle(): int
    {
        $lineRef = (string) $this->option('line');
        $lon = (float) $this->option('lon');
        $lat = (float) $this->option('lat');

        // Présence testée sur null / '' : la longitude 0 (méridien de Greenwich, qui
        // traverse la France) est une valeur valide.
        if ($lineRef === '' || in_array($this->option('lon'), [null, ''], true) || in_array($this->option('lat'), [null, ''], true)) {
            $this->error('Options --line, --lon et --lat requises.');

            return self::INVALID;
        }

        $line = Line::where('code', $lineRef)->orWhere('slug', $lineRef)->first();
        if (! $line) {
            $this->error("Ligne introuvable : {$lineRef}");

            return self::FAILURE;
        }

        $eraId = $this->option('era')
            ? (int) Era::where('year', (int) $this->option('era'))->value('id')
            : (int) Era::where('is_current', true)->value('id');

        $pk = BuildLineGeometry::pkOfPoint($line->id, $lon, $lat, $eraId);

        if ($pk === null) {
            $this->warn("Aucune géométrie pour la ligne {$line->code} à cette ère (lancez vff:build-line-geometry).");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Ligne [%s] %s, point (%.5f, %.5f) → PK %s km (%d m)',
            $line->code, $line->name, $lon, $lat,
            number_format($pk / 1000, 3, ',', ' '), (int) round($pk),
        ));

        return self::SUCCESS;
    }
}
