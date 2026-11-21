<?php

namespace App\Console\Commands;

use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * GtfsTrafficResolver - desserte voyageurs AVÉRÉE via GTFS SNCF (tâche #117).
 *
 * Le LineClassifier (App\Services\LineClassifier) déduit le trafic des sources
 * SNCF Réseau statiques (liste-des-gares = gare voyageurs O/N par code_ligne).
 * Cette commande apporte une preuve *positive et dynamique* : un train voyageurs
 * dessert RÉELLEMENT la gare dans le GTFS du plan de transport en cours.
 *
 * Chaîne GTFS (https://eu.ftp.opendatasoft.com/sncf/plandata/) :
 *   routes.txt  → on garde route_type=2 (rail), on EXCLUT route_type=3 (bus de
 *                 substitution / car TER - ce n'est PAS une desserte ferroviaire) ;
 *   trips.txt   → trip_id → route_id (ne garde que les trips d'une route rail) ;
 *   stop_times.txt (~68 Mo) → stop_id réellement desservis par ces trips -
 *                 LU EN STREAMING (fgetcsv ligne par ligne) pour la mémoire ;
 *   stops.txt   → stop_id → UIC (suffixe numérique 8 chiffres du stop_id,
 *                 ex 'StopPoint:OCETrain TER-87391003' → '87391003').
 *
 * Jointure UIC → sncf_gares.code_uic → code_ligne → ensemble des lignes ayant
 * une desserte voyageurs avérée. Sur les segments ACTIFS de l'ère cible :
 *   - traffic_type 'unknown'  → 'mixed'      (preuve voyageurs ; règle « si y'a du
 *                                 passager, y'a du fret » : 'passenger' réservé aux LGV)
 *   - traffic_type 'freight'  → 'mixed'      (la desserte voyageurs avérée prime)
 *   - 'passenger' / 'mixed'   → inchangés    (JAMAIS de downgrade)
 *
 * Trace : une line_sources (source_type='gtfs-voyageurs', confidence='high') par
 * ligne touchée, avec l'URL du GTFS source.
 *
 * Lancer avec une limite mémoire généreuse pour le streaming :
 *   php -d memory_limit=512M artisan vff:import-gtfs-voyageurs --era=2020
 */
class ImportGtfsVoyageurs extends Command
{
    protected $signature = 'vff:import-gtfs-voyageurs {--era=} {--dir=/tmp/gtfs_sncf}';

    protected $description = 'Désigne les lignes à desserte voyageurs AVÉRÉE via le GTFS SNCF (tâche #117).';

    private const SOURCE_URL = 'https://eu.ftp.opendatasoft.com/sncf/plandata/Export_OpenData_SNCF_GTFS_NewTripId.zip';

    public function handle(): int
    {
        $eraId = $this->option('era')
            ? DB::table('eras')->where('year', $this->option('era'))->value('id')
            : DB::table('eras')->where('is_current', true)->value('id');

        if (! $eraId) {
            $this->error('Ère introuvable (option --era ou ère courante).');

            return self::FAILURE;
        }

        $dir = rtrim((string) $this->option('dir'), '/');
        foreach (['routes.txt', 'trips.txt', 'stop_times.txt', 'stops.txt'] as $f) {
            if (! is_readable("$dir/$f")) {
                $this->error("Fichier GTFS manquant : $dir/$f");

                return self::FAILURE;
            }
        }

        $this->info("GtfsTrafficResolver (era_id={$eraId}), répertoire {$dir}");

        // 1) routes.txt : route_id des routes ferroviaires (route_type=2),
        //    en excluant explicitement les bus de substitution (route_type=3).
        $railRoutes = [];
        $kept = $excluded = 0;
        foreach ($this->csv("$dir/routes.txt") as $row) {
            $type = $row['route_type'] ?? '';
            $id = $row['route_id'] ?? '';
            if ($id === '') {
                continue;
            }
            if ($type === '2') {
                $railRoutes[$id] = true;
                $kept++;
            } elseif ($type === '3') {
                $excluded++;
            }
        }
        $this->line("  routes rail (type=2) : {$kept} · bus exclus (type=3) : {$excluded}");

        // 2) trips.txt : trip_id appartenant à une route ferroviaire.
        $railTrips = [];
        foreach ($this->csv("$dir/trips.txt") as $row) {
            $rid = $row['route_id'] ?? '';
            $tid = $row['trip_id'] ?? '';
            if ($tid !== '' && isset($railRoutes[$rid])) {
                $railTrips[$tid] = true;
            }
        }
        $this->line('  trips rail : '.count($railTrips));
        unset($railRoutes);

        // 3) stop_times.txt (~68 Mo) : stop_id desservis par ces trips.
        //    STREAMING fgetcsv ligne par ligne - jamais file_get_contents.
        $railStopIds = [];
        $n = 0;
        foreach ($this->csv("$dir/stop_times.txt") as $row) {
            $n++;
            $tid = $row['trip_id'] ?? '';
            if ($tid !== '' && isset($railTrips[$tid])) {
                $sid = $row['stop_id'] ?? '';
                if ($sid !== '') {
                    $railStopIds[$sid] = true;
                }
            }
        }
        $this->line('  stop_times lus : '.number_format($n, 0, '.', ' ').' · stop_id rail distincts : '.count($railStopIds));
        unset($railTrips);

        // 4) stops.txt : stop_id → UIC (suffixe numérique du stop_id).
        $railUics = [];
        foreach ($this->csv("$dir/stops.txt") as $row) {
            $sid = $row['stop_id'] ?? '';
            if ($sid === '' || ! isset($railStopIds[$sid])) {
                continue;
            }
            $uic = $this->uicFromStopId($sid);
            if ($uic !== null) {
                $railUics[$uic] = true;
            }
        }
        unset($railStopIds);
        $railUics = array_keys($railUics);
        $this->line('  UIC voyageurs (rail) : '.count($railUics));

        if (! $railUics) {
            $this->warn('Aucun UIC voyageurs extrait : rien à faire.');

            return self::SUCCESS;
        }

        // 5) Jointure UIC → sncf_gares.code_uic → code_ligne.
        $codeLignes = DB::table('sncf_gares')
            ->whereIn('code_uic', $railUics)
            ->distinct()
            ->pluck('code_ligne')
            ->filter(fn ($c) => $c !== null && $c !== '')
            ->values()
            ->all();
        $this->line('  code_ligne à desserte voyageurs avérée : '.count($codeLignes));

        // → lignes (lines.code) concernées, de l'ère cible.
        $lineIds = DB::table('lines')
            ->whereIn('code', $codeLignes)
            ->whereExists(function ($q) use ($eraId) {
                $q->select(DB::raw(1))->from('line_segments')
                    ->whereColumn('line_segments.line_id', 'lines.id')
                    ->where('line_segments.era_id', $eraId);
            })
            ->pluck('id')
            ->all();
        $this->line('  lignes (lines) correspondantes (era) : '.count($lineIds));

        if (! $lineIds) {
            $this->warn('Aucune ligne de l’ère ne correspond : rien à mettre à jour.');

            return self::SUCCESS;
        }

        // 6) Mise à jour des segments actifs : unknown→mixed (passenger sur LGV), freight→mixed.
        //    JAMAIS de downgrade (passenger/mixed restent intacts).
        $updated = 0;
        $upgUnknown = 0;
        $upgFreight = 0;
        DB::transaction(function () use ($eraId, $lineIds, &$updated, &$upgUnknown, &$upgFreight) {
            $upgUnknown = DB::update(
                'UPDATE line_segments SET updated_at=NOW(),
                        traffic_type = CASE WHEN EXISTS (SELECT 1 FROM lines l WHERE l.id = line_segments.line_id AND '.Lgv::sql('l', 'line_segments').")
                                            THEN 'passenger' ELSE 'mixed' END
                 WHERE era_id=? AND line_id IN (".$this->ph($lineIds).")
                   AND status IN ('in_service','single_track')
                   AND traffic_type='unknown'",
                array_merge([$eraId], $lineIds),
            );
            $upgFreight = DB::update(
                "UPDATE line_segments SET traffic_type='mixed', updated_at=NOW()
                 WHERE era_id=? AND line_id IN (".$this->ph($lineIds).")
                   AND status IN ('in_service','single_track')
                   AND traffic_type='freight'",
                array_merge([$eraId], $lineIds),
            );
            $updated = $upgUnknown + $upgFreight;

            // 7) Trace line_sources (high confidence) sur chaque ligne touchée.
            $now = now();
            foreach ($lineIds as $lineId) {
                DB::table('line_sources')->updateOrInsert(
                    ['line_id' => $lineId, 'source_type' => 'gtfs-voyageurs'],
                    [
                        'source_url' => self::SOURCE_URL,
                        'fields_provided' => json_encode(['traffic_type' => 'passenger-service-confirmed']),
                        'confidence' => 'high',
                        'fetched_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        });

        $this->newLine();
        $this->info('✓ Desserte voyageurs avérée appliquée :');
        $this->line('    lignes voyageurs détectées (lines) : '.count($lineIds));
        $this->line("    segments unknown → mixte (LGV : voyageurs) : {$upgUnknown}");
        $this->line("    segments freight  → mixed           : {$upgFreight}");
        $this->line("    segments mis à jour (total)         : {$updated}");
        $this->line('    line_sources gtfs-voyageurs écrites : '.count($lineIds));

        return self::SUCCESS;
    }

    /**
     * Extrait le code UIC (suffixe numérique) d'un stop_id GTFS SNCF.
     * Ex : 'StopPoint:OCETrain TER-87391003' → '87391003'
     *      'StopArea:OCE71043075'            → '71043075' (gare étrangère, ne joindra pas)
     * Retourne null si pas de suffixe numérique.
     */
    private function uicFromStopId(string $stopId): ?string
    {
        if (preg_match('/(\d+)\s*$/', $stopId, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Générateur CSV en streaming : ouvre le fichier, lit l'en-tête, puis
     * yield chaque ligne en tableau associatif. fgetcsv ligne par ligne →
     * mémoire constante même sur stop_times.txt (~68 Mo).
     *
     * @return \Generator<int,array<string,string>>
     */
    private function csv(string $path): \Generator
    {
        $h = fopen($path, 'rb');
        if ($h === false) {
            return;
        }
        try {
            $header = fgetcsv($h, 0, ',', '"', '\\');
            if ($header === false) {
                return;
            }
            // Nettoie un éventuel BOM UTF-8 sur la première colonne.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $cols = count($header);

            while (($row = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $assoc = [];
                for ($i = 0; $i < $cols; $i++) {
                    $assoc[$header[$i]] = $row[$i] ?? '';
                }
                yield $assoc;
            }
        } finally {
            fclose($h);
        }
    }

    /** Placeholders SQL "?,?,?" pour un IN (...) paramétré. */
    private function ph(array $items): string
    {
        return implode(',', array_fill(0, count($items), '?'));
    }
}
