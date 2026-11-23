<?php

namespace App\Console\Commands;

use App\Services\Structures\StructureImporter;
use Illuminate\Console\Command;

/**
 * Ouvrages d'art des lignes (tunnels, viaducs, grands ponts) : tables `structures` et `line_structures`.
 * Lit le cache de tuiles de la BD TOPO du profil en long (complété au besoin), les constructions de la
 * BD TOPO, Wikidata et Commons ; lit `line_segments`, `eras` et `line_profile_points` ; n'écrit que les
 * deux tables des ouvrages (rien avec --dry-run). Méthode et seuils : App\Services\Structures\StructureImporter.
 *
 *   php artisan vff:import-structures --dry-run
 *   php artisan vff:import-structures --offline       (caches seulement, aucun appel réseau)
 *   php artisan vff:import-structures --refresh-wikidata
 */
class ImportStructures extends Command
{
    protected $signature = 'vff:import-structures
        {--dry-run : calcule et affiche le bilan sans rien écrire}
        {--offline : n\'utilise que les caches (tuiles absentes ignorées, Wikidata et Commons non relus)}
        {--refresh-wikidata : relit Wikidata au lieu du cache storage/app/data/ouvrages/wikidata.json}';

    protected $description = 'Importe les ouvrages d\'art des lignes (BD TOPO de l\'IGN, Wikidata) et les rattache aux lignes avec leurs PK.';

    public function handle(StructureImporter $importer): int
    {
        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $stats = $importer->run($dry, (bool) $this->option('offline'), (bool) $this->option('refresh-wikidata'), fn (string $m) => $this->line($m));

        $kinds = $stats['by_kind'];
        $this->table(['Ouvrages', 'Tunnels', 'Viaducs', 'Ponts', 'Nommés', 'Datés', 'Rattachements', 'Lignes', 'Avec PK', 'Retirés'], [[
            $stats['structures'], $kinds['tunnel'] ?? 0, $kinds['viaduct'] ?? 0, $kinds['bridge'] ?? 0,
            $stats['named'], $stats['dated'], $stats['links'], $stats['lines'], $stats['with_pk'], $stats['removed'],
        ]]);
        $this->info(sprintf(
            '%s en %.1f s (%d appels réseau à l\'IGN).',
            $dry ? 'Calculé, rien écrit (--dry-run)' : 'Terminé',
            microtime(true) - $started,
            $stats['network_calls'],
        ));

        return self::SUCCESS;
    }
}
