<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Chaîne complète « gares à travers le temps », dans l'ordre :
 *
 *   1. vff:import-station-sources  (SNCF, OSM, OHM, Wikidata, gares par PK)
 *   2. vff:consolidate-stations    (dédoublonnage → stations + station_events)
 *   3. vff:place-listed-stations   (gares des listes par ligne posées par leur PK :
 *                                   calage robuste « PK = distance » sur les gares
 *                                   de position fiable et les lieux-dits)
 *      vff:attach-stations         (gares ↔ lignes → station_lines)
 *   4. vff:snap-stations           (gares décalées recalées sur la voie de
 *                                   leurs lignes, meilleure source de position)
 *   5. vff:import-station-closures (dates de fermeture des gares : articles
 *                                   Wikipédia des gares et des lignes,
 *                                   qualificatifs Wikidata, notes OSM ; après
 *                                   le rattachement, qui sert à reconnaître
 *                                   les gares nommées par l'article d'une ligne)
 *   6. vff:build-station-eras      (dates déduites, station_eras plafonnées
 *                                   par line_segments de l'ère, gares isolées
 *                                   et fermetures non datées « incertaines »,
 *                                   line_stops ; une ère par processus,
 *                                   --parallel à la fois)
 *
 * Après une régénération des tronçons par ère (vff:build-era-segments), seule
 * l'étape 6 est nécessaire (--only-eras) ; après de nouveaux rattachements de
 * tracés / événements de ligne, les étapes 3 à 6 (--from-attach).
 */
class StationsPipeline extends Command
{
    protected $signature = 'vff:stations-pipeline
        {--from-cache : Rejoue les sources téléchargées, sans réseau}
        {--from-attach : Reprend au rattachement (étapes 3-6)}
        {--only-eras : Étape 6 seule (après vff:build-era-segments)}
        {--parallel=4 : Processus parallèles pour vff:build-station-eras (une ère par processus)}';

    protected $description = 'Gares : import multi-sources → dédoublonnage → rattachement aux lignes → présence par décennie';

    public function handle(): int
    {
        $steps = [];
        if (! $this->option('only-eras') && ! $this->option('from-attach')) {
            $steps[] = ['vff:import-station-sources', $this->option('from-cache') ? ['--from-cache' => true] : []];
            $steps[] = ['vff:consolidate-stations', []];
        }
        if (! $this->option('only-eras')) {
            $steps[] = ['vff:place-listed-stations', []];
            $steps[] = ['vff:attach-stations', []];
            $steps[] = ['vff:snap-stations', []];
            $steps[] = ['vff:import-station-closures', $this->option('from-cache') ? ['--from-cache' => true] : []];
        }
        $steps[] = ['vff:build-station-eras', ['--parallel' => max(1, (int) $this->option('parallel'))]];

        foreach ($steps as [$cmd, $args]) {
            $this->info("▶ {$cmd}");
            if ($this->call($cmd, $args) !== self::SUCCESS) {
                $this->error("Échec : {$cmd}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
