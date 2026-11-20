<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\Schematic\SchematicMap;
use Illuminate\Console\Command;

/**
 * Plans schématiques (vue ferroviaire de /maps) calculés d'avance et rangés dans le cache, ère par
 * ère : le premier visiteur d'une ère n'attend pas le calcul (de l'ordre d'une à deux secondes).
 * Lecture seule en base ; seul le cache de l'application est écrit. Détail de la méthode :
 * App\Services\Schematic\SchematicLayout.
 *
 *   php artisan vff:build-schematic-maps                 # toutes les ères qui ont un graphe
 *   php artisan vff:build-schematic-maps --era=1930,2030
 *   php artisan vff:build-schematic-maps --era=1930 --output=storage/app/plan-1930.geojson
 *
 * À relancer après vff:build-network-graph (sinon le plan d'une ère est recalculé à sa première
 * demande, la clé du cache suivant la date de construction du graphe).
 */
class BuildSchematicMaps extends Command
{
    protected $signature = 'vff:build-schematic-maps
        {--era= : Ère(s) : année ou liste « 1930,1980 » (défaut : toutes celles qui ont un graphe)}
        {--output= : Fichier où écrire aussi le plan GeoJSON (une seule ère)}';

    protected $description = 'Plans schématiques (vue ferroviaire) de chaque ère, calculés et gardés en cache.';

    public function handle(SchematicMap $schematic): int
    {
        ini_set('memory_limit', '1G');
        $eras = Era::orderBy('year')->get();
        if ($this->option('era') !== null) {
            $years = array_map('intval', array_filter(explode(',', (string) $this->option('era'))));
            $unknown = array_diff($years, $eras->pluck('year')->map(fn ($y): int => (int) $y)->all());
            if ($unknown !== []) {
                $this->error('Ère inconnue : '.implode(', ', $unknown));

                return self::INVALID;
            }
            $eras = $eras->filter(fn (Era $e): bool => in_array((int) $e->year, $years, true))->values();
        }
        $output = $this->option('output');
        if ($output !== null && $eras->count() !== 1) {
            $this->error('--output demande une seule ère (--era=1930).');

            return self::INVALID;
        }

        $rows = [];
        foreach ($eras as $era) {
            $started = microtime(true);
            $payload = $schematic->rebuild($era);
            if ($payload === null) {
                $rows[] = [$era->year, 'pas de graphe', '', '', ''];

                continue;
            }
            $rows[] = [
                $era->year,
                $payload['summary']['axes'],
                $payload['summary']['stations'],
                $payload['summary']['lines'],
                round(microtime(true) - $started, 1).' s',
            ];
            if ($output !== null) {
                file_put_contents($output, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $this->info("Plan écrit dans {$output}");
            }
        }
        $this->table(['Ère', 'Axes', 'Gares', 'Lignes', 'Durée'], $rows);

        return self::SUCCESS;
    }
}
