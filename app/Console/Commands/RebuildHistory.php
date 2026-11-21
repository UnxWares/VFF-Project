<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Reconstruit TOUTE la carte historique (ères 1900 … 2030) à partir des données
 * déjà importées, dans le bon ordre. Idempotent, relançable sans risque.
 *
 *   0. lignes exploitées hors RFN déclarées à la main (vff:seed-off-network-lines) ;
 *   1. rattachement des tracés aux lignes, fusion des sections coupées ;
 *   2. tracé de référence par ligne (sources fusionnées par précision) ;
 *   3. dates : noms des tracés, OpenHistoricalMap, résolution des portions ;
 *   4. état actuel de l'emprise (voie en place, déposée, voie verte…) ;
 *   5. segments de chaque décennie (moteur EraTimeline, en N processus) ;
 *   5 bis. électrification et écartement (vff:enrich-era-segments), vitesses SNCF (cache) ;
 *   6. trafic officiel de l'ère courante, gares par ère, contrôles de cohérence ;
 *   6 bis. fiche ligne : signalisation SNCF (cache), noms historiques, introductions Wikipédia (cache) ;
 *   7. voies partagées entre lignes (vff:build-shared-track : la plus active dessinée) ;
 *   8. graphe ferroviaire de chaque ère pour les itinéraires (vff:build-network-graph) ;
 *   9. état des lieux des invariants des données (vff:check-invariants, lecture seule, non bloquant).
 *
 * Les IMPORTS (réseau, sources externes) ne sont relancés qu'avec --with-imports :
 * ils sont longs et sollicitent des services tiers (Overpass, Wikidata…).
 *
 *   php artisan vff:rebuild-history
 *   php artisan vff:rebuild-history --with-imports --shards=4
 *   php artisan vff:rebuild-history --from=build-era-segments
 */
class RebuildHistory extends Command
{
    protected $signature = 'vff:rebuild-history
        {--with-imports : relance aussi les imports de sources externes}
        {--shards=8 : processus parallèles pour les étapes longues (16 threads, en gardant une marge pour le bureau)}
        {--from= : reprendre à partir d\'une étape (nom de commande sans « vff: »)}';

    protected $description = 'Reconstruit toute la carte historique (tracés, dates, état actuel, décennies, gares, contrôles).';

    /** Imports de sources externes (ordre important). */
    private const IMPORTS = [
        ['vff:import-sncf', ['--all-statuses' => true]],
        ['vff:import-sncf-line-geometries', []],
        ['vff:import-sncf-refs', []],
        ['vff:import-sncf-electrification', []],
        ['vff:name-lines', []],
        ['vff:import-communes', []],
        ['vff:import-archeo-map', []],
        ['vff:import-ohm', []],
        ['vff:import-bdtopo-rail', []],
        ['vff:import-osm-railways', []],
        ['vff:import-osm-greenways', []],
        ['vff:import-sncf-level-crossings', []],
        ['vff:import-lignes-oubliees', ['--from-cache' => true]],
        ['vff:import-wikidata-lines', []],
        ['vff:import-wikipedia-chronology', []],
        // Réseaux secondaires (articles par réseau) : seulement les lignes encore sans ouverture.
        ['vff:import-secondary-network-dates', []],
        ['vff:import-heritage-lines', []],
        ['vff:import-station-sources', []],
        // Photos et archives (Wikimedia Commons) des lignes qui n'en ont pas encore.
        ['vff:import-commons-media', ['--only-missing' => true, '--no-geosearch' => true, '--depth' => 1]],
    ];

    /** Dérivations, à partir des données importées. */
    private const DERIVATIONS = [
        // Lignes exploitées hors RFN absentes des sources de tracés (RER de la RATP, tunnel sous la
        // Manche, CFL, trains touristiques) : lignes, formes déclarées et dates sourcées
        // (database/data/lignes_hors_rfn.php), avant le rattachement des tracés.
        ['vff:seed-off-network-lines', []],
        ['vff:link-traces', ['--source' => 'sncf']],
        ['vff:link-traces', []],
        ['vff:link-traces', ['--source' => 'ohm']],
        ['vff:match-current-network', []],
        // Sections créées à part, lignes en double (même article, couverture), événements d'articles homonymes.
        ['vff:merge-split-lines', []],
        ['vff:build-line-references', []],
        ['vff:dates-from-trace-names', []],
        ['vff:ohm-events', []],
        // Faits relus d'Argentan Web Ferroviaire (table de source, hors ligne) : événements et arrêts,
        // avant les corrections sourcées qui peuvent en neutraliser.
        ['vff:apply-argentanwebferro', []],
        // Corrections sourcées (database/data/line_event_corrections.php) : réappliquées après les
        // imports qui réécrivent leurs événements, avant la résolution des portions.
        ['vff:apply-event-corrections', []],
        // Re-résolution complète des portions sur le tracé de référence à jour, source par
        // source (les événements posés directement sur un tracé - OHM, voies vertes, noms
        // archeo, associations - sont déjà localisés et ne doivent pas être recalculés).
        ['vff:resolve-event-sections', ['--all' => true, '--source' => 'lignes-oubliees']],
        ['vff:resolve-event-sections', ['--all' => true, '--source' => 'wikipedia']],
        ['vff:resolve-event-sections', ['--all' => true, '--source' => 'wikidata']],
        ['vff:resolve-event-sections', ['--all' => true, '--source' => 'wikipedia-reseaux']],
        ['vff:resolve-event-sections', ['--all' => true, '--source' => 'argentanwebferro']],
        ['vff:resolve-event-sections', ['--retry' => true]],
        ['vff:detect-present-states', []],
        ['vff:build-era-segments', []], // parallélisé, cf. runSharded()
        // Électrification (tension) et écartement de chaque portion à chaque ère (étape séparée du moteur).
        ['vff:enrich-era-segments', []], // parallélisé, cf. runSharded()
        // Vitesses nominales SNCF (ère courante et décennie écoulée), après l'électrification qui
        // découpe des tronçons : le moteur les laisse vides. Cache local (--from-cache), hors ligne.
        ['vff:import-sncf-vmax', ['--from-cache' => true, '--previous' => true]],
        ['vff:classify-lines', []],
        // Fiche ligne : signalisation SNCF par PK (cache local, cf. --from-cache : la table était
        // restée vide après la recréation de la base), noms historiques et sections nommées
        // (Wikidata en cache + database/data/lignes_noms_historiques.php), texte « À propos »
        // (introduction Wikipédia en cache, attribuée CC BY-SA). Hors ligne quand les caches existent.
        ['vff:import-sncf-signalling', ['--from-cache' => true]],
        ['vff:import-line-names', []],
        ['vff:import-line-descriptions', ['--from-cache' => true]],
        // Gares des listes par ligne (schémas Wikipédia, lignes-oubliées) posées sur les tracés de
        // référence à jour (base et caches, hors ligne), puis dédoublonnage, rattachement et ères.
        ['vff:import-station-sources', ['--source' => ['pk']]],
        ['vff:consolidate-stations', []],
        ['vff:stations-pipeline', ['--from-attach' => true, '--from-cache' => true]],
        ['vff:check-consistency', ['--fix' => true]],
        // Après le --fix R1 (trafic) : voies partagées entre lignes, la plus active seule dessinée.
        ['vff:build-shared-track', []], // parallélisé par ère, cf. runSharded()
        // Graphe par ère (itinéraires historiques) : après les tronçons ET les gares de chaque ère.
        ['vff:build-network-graph', []], // parallélisé par ère, cf. runSharded()
        // État des lieux : invariants des données (lecture seule), signalés sans bloquer.
        ['vff:check-invariants', []],
    ];

    /** Contrôles qui sortent en 1 quand ils trouvent des anomalies : signalés, la chaîne continue. */
    private const NON_BLOCKING = ['vff:check-consistency', 'vff:check-invariants',
        // Sans cache ni réseau, la signalisation garde l'état existant et sort en échec : signalé, la chaîne continue.
        'vff:import-sncf-signalling', 'vff:import-sncf-vmax'];

    public function handle(): int
    {
        $steps = $this->option('with-imports') ? array_merge(self::IMPORTS, self::DERIVATIONS) : self::DERIVATIONS;

        if ($from = $this->option('from')) {
            $index = collect($steps)->search(fn ($s) => $s[0] === 'vff:'.ltrim($from, ':'));
            if ($index === false) {
                $this->error("Étape inconnue : {$from}");

                return self::FAILURE;
            }
            $steps = array_slice($steps, $index);
        }

        foreach ($steps as [$command, $args]) {
            $this->newLine();
            // Option en liste (« --source=pk ») : sans implode, « Array to string conversion » arrêtait la chaîne.
            $this->info("▶ {$command} ".collect($args)->map(fn ($v, $k) => $v === true ? $k : $k.'='.(is_array($v) ? implode(',', $v) : $v))->implode(' '));
            $started = microtime(true);

            $code = in_array($command, self::SHARDED, true)
                ? $this->runSharded($command, (int) $this->option('shards'))
                : $this->call($command, $args);

            // check-consistency et check-invariants sortent en 1 s'il reste des anomalies : signalées sans bloquer.
            if ($code !== self::SUCCESS && ! in_array($command, self::NON_BLOCKING, true)) {
                $this->error("✗ Échec à l'étape {$command} : chaîne interrompue (reprendre avec --from=".substr($command, 4).').');

                return self::FAILURE;
            }
            $this->line(sprintf('  (%.0f s)', microtime(true) - $started));
        }

        $this->call('db:seed', ['--class' => 'AdvocacySeeder', '--force' => true]);
        Cache::forget('home_stats');
        $this->newLine();
        $this->info('✓ Carte historique reconstruite.');

        return self::SUCCESS;
    }

    /** Étapes longues lancées en N processus parallèles (--shard=i/N). */
    private const SHARDED = ['vff:build-line-references', 'vff:build-era-segments', 'vff:enrich-era-segments', 'vff:build-shared-track', 'vff:build-network-graph'];

    private function runSharded(string $command, int $shards): int
    {
        $shards = max(1, $shards);
        $processes = [];
        for ($i = 1; $i <= $shards; $i++) {
            $process = new Process([PHP_BINARY, base_path('artisan'), $command, "--shard={$i}/{$shards}"], base_path(), timeout: null);
            $process->start();
            $processes[$i] = $process;
        }

        $failed = false;
        foreach ($processes as $i => $process) {
            $process->wait();
            $summary = collect(explode("\n", $process->getOutput()))->filter(fn ($l) => trim($l) !== '')->last();
            $this->line("  shard {$i}/{$shards} : ".trim((string) $summary));
            if (! $process->isSuccessful()) {
                $failed = true;
                $this->error(mb_strimwidth($process->getErrorOutput() ?: $process->getOutput(), 0, 600));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
