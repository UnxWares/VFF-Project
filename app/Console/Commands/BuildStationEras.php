<?php

namespace App\Console\Commands;

use App\Services\StationCorrections;
use App\Services\StationLineCoverage;
use App\Services\StationTimeline;
use App\Support\GeneratedText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Matérialise la présence et le statut de chaque gare par décennie
 * (`station_eras`), le statut affiché (`stations.status`) et les arrêts par
 * ligne et par ère (`line_stops`).
 *
 * À RELANCER après chaque régénération de line_segments (vff:build-era-segments,
 * vff:reclassify-status, vff:classify-lines) :
 *
 *   php artisan vff:build-station-eras               # toutes les ères, un processus
 *   php artisan vff:build-station-eras --parallel=4  # une ère par processus, 4 à la fois
 *
 * 1. Dates déduites (station_events.is_inferred) pour les gares sans date
 *    propre : ouverture / fermeture (aux voyageurs ou totale) des lignes
 *    rattachées dont la section couvre le PK de la gare (un événement daté
 *    pour une section précise passe avant un événement « toute la ligne »),
 *    sinon tracés datés (OHM…) passant à < 30 m.
 * 2. Statut par ère = chronologie de la gare (StationTimeline : ouverture ≤
 *    année < fermeture), PLAFONNÉ par les tronçons de l'ère qui la desservent
 *    (tronçons des lignes rattachées à < 500 m, à défaut tout tronçon à
 *    < 100 m / 300 m selon la précision de la position ; à défaut encore,
 *    état d'ensemble des lignes rattachées si elles figurent dans l'ère) :
 *      - aucun tronçon en service (abandoned, dismantled, razed, ou absent)
 *        → gare fermée, quels que soient les drapeaux SNCF voyageurs/fret
 *        (liste-des-gares recense aussi des gares de lignes fermées) ;
 *      - fret seul / voie de service → au mieux « fret seul » ;
 *      - voyageurs ou mixte (ou trafic inconnu) → pas de plafond.
 *    Une ère sans AUCUN tronçon (moteur décennal pas encore passé) n'est pas
 *    plafonnée : on garde la seule chronologie, signalé en sortie.
 *    Exceptions (App\Services\StationLineCoverage, partagé avec
 *    vff:check-consistency) : réseau non modélisé ; et, à une ère historique,
 *    gare dont TOUTES les lignes sont non datées (absentes de toutes les ères
 *    historiques faute d'ouverture) → statut de ses propres sources, marqué
 *    « incertain » (station_eras.uncertain). Dès qu'une de ses lignes est
 *    datée, plafond strict.
 * 3. Gare ouverte mais ISOLÉE à l'ère (aucun tronçon, même fermé, à moins de
 *    300 m : elle flotterait sans voie) → jamais montrée ouverte
 *    (StationLineCoverage::isolationVerdict) : fermée si sa voie a disparu
 *    après fermeture, absente si son existence n'est que déduite d'une ligne
 *    qui n'arrive là que plus tard, « incertaine » sinon.
 * 4. Fermeture non datée (StationTimeline::unconfirmedNote, 2026-10-19) : gare
 *    fermée aujourd'hui (ou réduite au fret) dont la fermeture n'est pas
 *    datée → au-delà de la dernière preuve d'ouverture (ouverture, fermeture
 *    datée, « fermée après 1950 »…), un statut ouvert n'est jamais affirmé :
 *    ère « incertaine », note « Fermeture non datée » (ou « … dans les années
 *    1980 », « … aux voyageurs non datée »). Dates lues par
 *    vff:import-station-closures (Wikipédia, Wikidata, OSM).
 * 5. line_stops : rattachements (station_lines) dont la période couvre l'ère,
 *    gare présente à l'ère, ligne en service près de la gare si l'ère a des
 *    tronçons ; ordre = PK.
 *
 * --parallel=N : les dates déduites sont calculées une fois, puis chaque ère
 * (station_eras + line_stops) est traitée par un processus enfant
 * (--era=… --skip-infer), N à la fois. Les ères sont indépendantes.
 */
class BuildStationEras extends Command
{
    protected $signature = 'vff:build-station-eras
        {--era= : Une seule ère (année)}
        {--no-stops : Ne reconstruit pas line_stops}
        {--parallel=1 : Processus parallèles, une ère par processus (4 au plus conseillé)}
        {--today= : Date du jour (AAAA-MM-JJ) de l\'ère courante, défaut : aujourd\'hui}
        {--skip-infer : (interne) dates déduites déjà recalculées par le processus parent}';

    protected $description = 'Gares par décennie (station_eras) + statut plafonné par l\'état des lignes de l\'ère + line_stops';

    private const SUMMARY_HEADERS = ['ère', 'gares', 'voyageurs', 'touristiques', 'fret seul', 'fermées', 'plafonnées par les lignes',
        'incertaines', 'isolées → fermées', 'isolées → retirées', 'fermeture non datée → incertaines'];

    public function handle(): int
    {
        ini_set('memory_limit', '1G');
        $corrections = app(StationCorrections::class);
        if (! $this->option('skip-infer')) {
            // Corrections sourcées (database/data/gares_corrections.php), réappliquées à chaque passage.
            $applied = $corrections->apply();
            $this->line(sprintf('Corrections de gares : %d appliquées, %d écartées', $applied['applied'], count($applied['skipped'])));
            foreach ($applied['skipped'] as $msg) {
                $this->warn('  '.$msg);
            }
            $this->applyCorrectedOpenings();
            $this->classifyKinds($applied['kinds']);
            $this->inferEvents(array_keys(array_filter($applied['current'], fn (string $s) => $s === StationTimeline::CLOSED)));
        }
        // Trains d'attraction ou touristiques sans ligne VFF (correction « tourist »), aussi dans les processus enfants.
        $forcedTourist = $corrections->touristStations();
        // État du jour imposé par une correction sourcée (action « current » : horaires publiés, arrêt supprimé).
        $forcedCurrent = $corrections->currentStatuses();

        $eras = DB::table('eras')->orderBy('year')
            ->when($this->option('era'), fn ($q, $y) => $q->where('year', (int) $y))
            ->get(['id', 'year', 'is_current']);

        $parallel = max(1, (int) $this->option('parallel'));
        if ($parallel > 1 && $eras->count() > 1) {
            return $this->runInParallel($eras->pluck('year')->all(), $parallel);
        }

        // Chronologie de chaque gare.
        $stations = DB::table('stations')->get(['id', 'source_status', 'status', DB::raw("provenance->>'status' AS status_source"),
            DB::raw("provenance->>'kind' AS kind")])->keyBy('id');
        $events = [];
        foreach (DB::table('station_events')->orderBy('event_date')->orderBy('id')->get(['station_id', 'type', 'event_date', 'date_precision', 'is_inferred', 'source']) as $e) {
            $events[$e->station_id][] = ['type' => $e->type, 'date' => $e->event_date, 'inferred' => $e->is_inferred, 'precision' => $e->date_precision,
                'source' => $e->source];
        }
        // Date saisie à la main sur la fiche (stations.closed_at sans provenance de source) :
        // une fermeture comme les autres (les dates des sources sont déjà dans station_events).
        foreach (DB::table('stations')->whereNotNull('closed_at')
            ->where(fn ($q) => $q->whereRaw("provenance->>'closed_at' IS NULL")->orWhereRaw("provenance->>'closed_at' IN ('manual', 'contribution')"))
            ->get(['id', 'closed_at', 'closed_precision']) as $m) {
            $events[$m->id][] = ['type' => 'closure', 'date' => $m->closed_at, 'inferred' => false,
                'precision' => in_array($m->closed_precision, ['day', 'month', 'year'], true) ? $m->closed_precision : 'day', 'source' => 'manual'];
        }
        // Toutes les ères historiques (un processus enfant ne traite qu'une ère).
        $eraYears = DB::table('eras')->where('is_current', false)->orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all();
        // État du jour (ère courante = aujourd'hui) : sources corrigées par « plus exploitée = fermée »
        // et par les fermetures datées (StationTimeline::currentStatus). Calculé dans chaque processus :
        // il sert aussi aux ères historiques (fermeture non datée → incertaine).
        $today = $this->today();
        $evidence = $this->currentEvidence();
        $tourist = DB::table('line_segments')->exists() ? (new StationLineCoverage)->touristStations() : [];
        $todayStatus = [];
        // Indices pour la chronologie (canonicalEvents) : fret seul aujourd'hui, gare desservie selon gares-de-voyageurs.
        $hints = [];
        foreach ($stations as $sid => $s) {
            $hints[$sid] = ['freight_today' => ($s->source_status ?? $s->status) === StationTimeline::FREIGHT || ($evidence[$sid]['sncf_freight'] ?? false),
                'served_seen' => ($evidence[$sid]['voyageurs'] ?? false) ? ($evidence[$sid]['voyageurs_seen'] ?? null) : null];
            $todayStatus[$sid] = isset($forcedCurrent[$sid])
                ? StationTimeline::forcedCurrentStatus($forcedCurrent[$sid])
                : StationTimeline::currentStatus($s->source_status ?? $s->status,
                    $this->canonicalEvents($events[$sid] ?? [], $hints[$sid]), $today, ($evidence[$sid] ?? []) + ['tourist' => isset($tourist[$sid]) || isset($forcedTourist[$sid])]);
        }

        // Gares sans aucune ligne rattachée, datées par leurs propres sources (StationLineCoverage::exemption).
        $ownChronology = array_fill_keys(array_map('intval', DB::table('stations as s')
            ->whereNotExists(fn ($q) => $q->from('station_lines as sl')->whereColumn('sl.station_id', 's.id'))
            ->whereExists(fn ($q) => $q->from('station_events as e')->whereColumn('e.station_id', 's.id')->where('e.is_inferred', false)->where('e.type', 'opening'))
            ->whereExists(fn ($q) => $q->from('station_events as e')->whereColumn('e.station_id', 's.id')->where('e.is_inferred', false)
                ->whereIn('e.type', ['closure', 'closure_passenger', 'demolition']))
            ->pluck('s.id')->all()), true);

        $summary = [];
        // Desserte par les lignes : logique partagée avec vff:check-consistency (même plafond R2).
        $coverage = new StationLineCoverage;
        foreach ($eras as $era) {
            $hasSegments = DB::table('line_segments')->where('era_id', $era->id)->exists();
            $caps = $hasSegments ? $coverage->levels($era->id) : [];
            // Gares dont au moins une ligne rattachée figure dans les tronçons de l'ère.
            $modeled = $hasSegments ? $coverage->modeled($era->id) : [];
            $undatedOnly = $hasSegments && ! $era->is_current ? $coverage->undatedOnly() : [];
            // Lignes de la gare disparues (présentes à une ère antérieure), voies actuelles hors VFF,
            // gares touristiques : cf. StationLineCoverage (réseau non modélisé ou lignes disparues).
            $linesGone = $hasSegments ? $coverage->linesGone((int) $era->year) : [];
            $live = $hasSegments ? $coverage->liveUnmodeledTracks() : [];
            $tourist = $hasSegments ? $coverage->touristStations() : [];
            $entries = [];
            $capped = 0;
            foreach ($stations as $sid => $s) {
                // Point technique ou gare projetée (classifyKinds) : jamais une gare de l'ère.
                if ($s->kind !== null) {
                    continue;
                }
                $source = $s->source_status ?? $s->status;
                $existsToday = $source !== 'gone';
                $evs = $this->canonicalEvents($events[$sid] ?? [], $hints[$sid]);
                [$current, $currentNote] = $todayStatus[$sid];
                $status = $era->is_current
                    ? $current
                    : StationTimeline::statusFromEvents(StationTimeline::forTimeline($evs), $era->year, $existsToday);
                if ($status === null) {
                    continue;
                }
                $note = $era->is_current ? $currentNote : null;
                $uncertain = false;
                // Lignes en service près de la gare toutes touristiques (plafond R2).
                $heritageLines = false;
                // Réseau non modélisé dans line_segments (CFC, Chemins de fer de
                // Provence, lignes touristiques…) : ni tronçon proche ni ligne
                // rattachée connue à cette ère, et un statut attesté par OSM /
                // Wikidata / gares-de-voyageurs → pas de plafond (on ne ferme pas
                // une gare faute de tracé). Les drapeaux de liste-des-gares seuls
                // ne suffisent pas : ils recensent aussi des gares de lignes fermées.
                // Lignes de la gare toutes non datées (absentes de toutes les ères
                // historiques faute de date d'ouverture) : « absente » n'est pas
                // « fermée » → statut de ses propres sources, marqué incertain.
                $exemption = $hasSegments ? StationLineCoverage::exemption((bool) $era->is_current, isset($caps[$sid]),
                    isset($modeled[$sid]), $s->status_source, isset($undatedOnly[$sid]), isset($linesGone[$sid]), isset($live[$sid]),
                    isset($ownChronology[$sid])) : null;
                $isTourist = ($live[$sid] ?? false) || isset($tourist[$sid]) || isset($forcedTourist[$sid]);
                $attested = $era->is_current && StationLineCoverage::attestedToday($s->status_source, $source);
                if ($exemption !== null) {
                    $note = $exemption === StationLineCoverage::NOTE_UNMODELED && $isTourist ? StationLineCoverage::NOTE_UNMODELED_TOURIST : $exemption;
                    // Statut non plafonné faute de ligne connue : jamais montré ouvert ; une gare de
                    // chemin de fer touristique absent de VFF n'est jamais « active ».
                    $status = StationLineCoverage::touristCap($status, $isTourist);
                    // … sauf à l'ère courante, quand une source relevée aujourd'hui (OSM, Wikidata,
                    // gares-de-voyageurs) atteste la gare en service : rien d'incertain (trains de parcs).
                    $uncertain = $status !== StationTimeline::CLOSED && ! $attested;
                } elseif ($hasSegments) {
                    [$lines, $heritageOnly] = $caps[$sid] ?? [StationTimeline::LINES_CLOSED, false];
                    $heritageLines = $heritageOnly;
                    $new = StationTimeline::capByLines($status, $lines, $heritageOnly);
                    if ($new !== $status) {
                        $capped++;
                        $note = match ($lines) {
                            StationTimeline::LINES_CLOSED => 'Plus aucune ligne en service à cette époque',
                            StationTimeline::LINES_FREIGHT => 'Lignes ouvertes au seul fret à cette époque',
                            default => null,
                        };
                    }
                    $status = $new;
                }
                // Fermeture non datée : statut ouvert non couvert par une source → incertaine.
                // État d'aujourd'hui = celui de l'ère courante (une gare plus exploitée est fermée).
                $todayHint = $source === 'gone' ? StationTimeline::CLOSED : match ($current) {
                    StationTimeline::CLOSED, null => StationTimeline::CLOSED,
                    StationTimeline::FREIGHT => StationTimeline::FREIGHT,
                    default => null,
                };
                $undatedNote = $era->is_current ? null : StationTimeline::unconfirmedNote($evs, $eraYears, (int) $era->year, $status, $todayHint);
                if ($undatedNote !== null) {
                    $uncertain = true;
                }
                // Fret seul non confirmé sur une ligne seulement touristique (train touristique, vélorail) :
                // plus de fret après le déclassement, la gare est fermée (Lapouyade, Toucy, Manot).
                if (StationTimeline::heritageLineEndsFreight($status, $heritageLines, $uncertain, (bool) $era->is_current)) {
                    [$status, $uncertain, $undatedNote, $note] = [StationTimeline::CLOSED, false, null, StationTimeline::NOTE_HERITAGE_NO_FREIGHT];
                }
                $entries[$sid] = ['status' => $status, 'note' => $note, 'uncertain' => $uncertain, 'undated_note' => $undatedNote,
                    // Aucune ouverture propre : l'existence de la gare n'est déduite que de ses lignes.
                    'tourist' => $isTourist, 'attested' => $attested,
                    'inferred_only' => ! collect($evs)->contains(fn ($e) => $e['type'] === 'opening' && ! ($e['inferred'] ?? false)),
                    'inferred' => collect($evs)->contains(fn ($e) => $e['inferred'] ?? false)];
            }

            // Gares ouvertes sans aucune voie à moins de 300 m à cette ère.
            [$isoClosed, $isoAbsent] = [0, 0];
            if ($hasSegments) {
                $open = array_keys(array_filter($entries, fn ($x) => $x['status'] !== StationTimeline::CLOSED));
                $isolated = array_keys($coverage->isolated($era->id, $open));
                $history = $coverage->trackHistory($isolated);
                foreach ($isolated as $sid) {
                    $verdict = StationLineCoverage::isolationVerdict($history[$sid] ?? [], (int) $era->year, (bool) $era->is_current,
                        $entries[$sid]['inferred_only']);
                    if ($verdict === StationLineCoverage::ISOLATED_ABSENT) {
                        // Gare datée d'après sa ligne, qui n'arrive là que plus tard : pas encore là.
                        unset($entries[$sid]);
                        $isoAbsent++;
                    } elseif ($verdict === StationLineCoverage::ISOLATED_CLOSED) {
                        $entries[$sid]['status'] = StationTimeline::CLOSED;
                        $entries[$sid]['uncertain'] = false;
                        $entries[$sid]['undated_note'] = null;
                        $entries[$sid]['note'] = StationLineCoverage::NOTE_TRACK_GONE;
                        $isoClosed++;
                    } else {
                        // Sans voie VFF, mais en service aujourd'hui selon une source relevée ce jour : certaine.
                        $entries[$sid]['uncertain'] = ! $entries[$sid]['attested'];
                        $entries[$sid]['note'] ??= StationLineCoverage::NOTE_NO_TRACK;
                        $entries[$sid]['status'] = StationLineCoverage::touristCap($entries[$sid]['status'], $entries[$sid]['tourist']);
                    }
                }
            }

            $rows = [];
            foreach ($entries as $sid => $x) {
                $note = $x['note'];
                if (! $era->is_current && $x['inferred']) {
                    $note = trim(($note ? $note.' · ' : '').'Dates déduites des lignes');
                }
                if (! $era->is_current && ($x['undated_note'] ?? null) !== null) {
                    $note = trim(($note ? $note.' · ' : '').$x['undated_note']);
                }
                $rows[] = ['station_id' => $sid, 'era_id' => $era->id, 'status' => $x['status'], 'uncertain' => $x['uncertain'],
                    'note' => $note, 'created_at' => now(), 'updated_at' => now()];
            }

            DB::transaction(function () use ($era, $rows) {
                DB::table('station_eras')->where('era_id', $era->id)->delete();
                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table('station_eras')->insert($chunk);
                }
                if ($era->is_current) {
                    // Statut affiché = statut de l'ère courante ; absente = fermée / disparue.
                    DB::statement(<<<'SQL'
                        UPDATE stations s SET status = COALESCE(se.status, 'declassified')
                        FROM stations s2 LEFT JOIN station_eras se ON se.station_id = s2.id AND se.era_id = ?
                        WHERE s2.id = s.id AND s.status IS DISTINCT FROM COALESCE(se.status, 'declassified')
                    SQL, [$era->id]);
                }
            });

            $counts = collect($rows)->countBy('status');
            $summary[] = [$era->year, count($rows), $counts['active'] ?? 0, $counts['tourist'] ?? 0, $counts['no_commercial'] ?? 0,
                $counts['declassified'] ?? 0, $hasSegments ? $capped : 'pas de tronçons',
                collect($rows)->where('uncertain', true)->count(), $isoClosed, $isoAbsent,
                // Après la règle des gares isolées (une gare isolée peut être fermée ou retirée).
                collect($entries)->whereNotNull('undated_note')->count()];
        }

        $stops = null;
        if (! $this->option('no-stops')) {
            $stops = $this->buildLineStops($eras->pluck('id')->all());
        }

        if ($this->option('skip-infer')) {
            // Processus enfant de --parallel : résumé lisible par le parent.
            $this->line('@summary '.json_encode(['rows' => $summary, 'stops' => $stops]));
        } else {
            $this->table(self::SUMMARY_HEADERS, $summary);
            if ($stops !== null) {
                $this->info("line_stops : {$stops} arrêts");
            }
            $this->resolveClosures();
            $this->alignErasWithClosure();
            $this->reconcileServiceYears();
        }

        return self::SUCCESS;
    }

    /**
     * Une ère par processus enfant (--era=… --skip-infer), $parallel à la fois.
     *
     * @param  list<int>  $years
     */
    private function runInParallel(array $years, int $parallel): int
    {
        $queue = $years;
        $running = [];
        $summary = [];
        $stops = 0;
        $failed = [];
        // Une reprise par ère : un conflit de verrou avec un autre traitement
        // (moteur décennal, ALTER TABLE…) ne doit pas laisser une ère vide.
        $retried = [];
        $started = microtime(true);
        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $parallel) {
                $year = array_shift($queue);
                $cmd = [PHP_BINARY, base_path('artisan'), 'vff:build-station-eras', "--era={$year}", '--skip-infer', '--today='.$this->today()];
                if ($this->option('no-stops')) {
                    $cmd[] = '--no-stops';
                }
                $process = new Process($cmd, base_path(), timeout: null);
                $process->start();
                $running[$year] = $process;
            }
            foreach ($running as $year => $process) {
                if ($process->isRunning()) {
                    continue;
                }
                unset($running[$year]);
                if (! $process->isSuccessful() && ! isset($retried[$year])) {
                    $retried[$year] = true;
                    $queue[] = $year;
                    $this->warn("  ère {$year} : échec, nouvel essai (".mb_strimwidth(trim($process->getErrorOutput() ?: $process->getOutput()), 0, 200).')');

                    continue;
                }
                if (! $process->isSuccessful()) {
                    $failed[] = $year;
                    $this->error("Ère {$year} : échec\n".mb_strimwidth($process->getErrorOutput() ?: $process->getOutput(), 0, 1500));

                    continue;
                }
                foreach (explode("\n", $process->getOutput()) as $line) {
                    if (str_starts_with($line, '@summary ')) {
                        $data = json_decode(substr($line, 9), true);
                        array_push($summary, ...$data['rows']);
                        $stops += (int) ($data['stops'] ?? 0);
                    }
                }
                $this->line(sprintf('  ère %d traitée (%.0f s)', $year, microtime(true) - $started));
            }
            usleep(200_000);
        }
        usort($summary, fn ($a, $b) => $a[0] <=> $b[0]);
        $this->table(self::SUMMARY_HEADERS, $summary);
        if (! $this->option('no-stops')) {
            $this->info("line_stops : {$stops} arrêts (toutes ères)");
        }
        if ($failed === []) {
            $this->resolveClosures();
            $this->alignErasWithClosure();
            $this->reconcileServiceYears();
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Ouverture corrigée (correction sourcée « add opening », StationCorrections) : c'est la date de la
     * fiche gare (stations.opened_at), pas celle de la source neutralisée que vff:consolidate-stations y
     * avait mise (Mâcon-Loché-TGV : Wikidata 19/09/1981, l'article de la gare et la LGV 27/09/1981).
     */
    private function applyCorrectedOpenings(): void
    {
        DB::statement(<<<'SQL'
            UPDATE stations s SET opened_at = e.event_date, opened_precision = e.date_precision,
                   provenance = COALESCE(s.provenance, '{}'::jsonb) || jsonb_build_object('opened_at', 'correction')
            FROM (SELECT DISTINCT ON (station_id) station_id, event_date, date_precision FROM station_events
                  WHERE source = 'correction' AND type = 'opening' AND NOT is_inferred ORDER BY station_id, event_date) e
            WHERE e.station_id = s.id AND (s.opened_at IS DISTINCT FROM e.event_date OR s.provenance->>'opened_at' IS DISTINCT FROM 'correction')
        SQL);
    }

    /**
     * Frise d'une gare fermée à une date retenue (resolveClosures) : une ère postérieure encore montrée
     * ouverte « non confirmée » (voyageurs ou fret seul) contredisait cette date (rapport fiche-ligne du
     * 19/11/2026 : Cabessut et les gares de Cahors - Capdenac, fermée le 09/06/2011, « fret seul ? » en 2020 ;
     * Bruay-en-Artois et trois voisines, 21/10/2018 ; Tournon-St-Martin, 29/10/1970). Elle devient fermée.
     * Restent telles quelles : une ère confirmée par une source, une ère touristique (train touristique
     * après la fermeture : Chambray-Fontaine-sous-Jouy, Villebois), l'ère courante, une fermeture connue
     * seulement par un intervalle, une gare rouverte après la date. Une ère est l'état au 31/12 de son année.
     */
    private function alignErasWithClosure(): void
    {
        $n = DB::affectingStatement(<<<'SQL'
            UPDATE station_eras se
            SET status = 'declassified', uncertain = FALSE,
                note = CASE WHEN s.provenance->>'closed_at' LIKE 'inferred:%' THEN 'Dates déduites des lignes' END,
                updated_at = NOW()
            FROM stations s, eras e
            WHERE s.id = se.station_id AND e.id = se.era_id AND NOT e.is_current
              AND s.closed_at IS NOT NULL AND s.closed_at <= CURRENT_DATE AND s.closed_precision IN ('day', 'month', 'year')
              AND e.year >= EXTRACT(YEAR FROM s.closed_at)
              AND se.uncertain AND se.status IN ('active', 'no_commercial')
              AND NOT EXISTS (SELECT 1 FROM station_events r WHERE r.station_id = s.id AND r.type = 'reopening' AND r.event_date > s.closed_at)
        SQL);
        $this->info("Frise alignée sur la date de fermeture retenue : {$n} couples gare × ère non confirmés deviennent fermés");
    }

    /**
     * Condition SQL « la date A tombe strictement avant la date B », jugée à la précision commune : au jour
     * si les deux sont connues au jour, au mois si l'une ne l'est qu'au mois (septembre avant novembre), et
     * jamais vraie autrement (année, intervalle, précision inconnue) : deux dates de la même année à l'année
     * près ne disent pas laquelle précède l'autre. Les dates d'une précision au mois sont au premier du mois.
     */
    private static function strictlyBefore(string $a, string $aPrecision, string $b, string $bPrecision): string
    {
        return "({$a} IS NOT NULL AND {$b} IS NOT NULL AND CASE
            WHEN {$aPrecision} = 'day' AND {$bPrecision} = 'day' THEN {$a} < {$b}
            WHEN {$aPrecision} IN ('day', 'month') AND {$bPrecision} IN ('day', 'month')
                THEN date_trunc('month', {$a}) < date_trunc('month', {$b})
            ELSE FALSE END)";
    }

    /**
     * « Ligne desservie de … à … » (station_lines.first_year / last_year) bornée par la vie de la gare,
     * une fois ses dates retenues (resolveClosures) : une ligne ne dessert pas une gare avant son
     * ouverture ni après sa fermeture. Une seule source de vérité pour la fiche gare (lignes
     * desservies), la fiche ligne (dates des gares) et la carte : les arrêts (line_stops) hors de cette
     * période sont retirés, avec les mêmes bornes que buildLineStops (contrôle qualité du 2026-10-20 :
     * Briouze et Lonlay-le-Tesson « 434000 desservie de 1869 à 2006 », Lonlay fermée le 26/05/1991).
     * Période vide (fin avant le début) : years_source = never_served, « jamais desservie par cette
     * ligne » sur la fiche gare, absente de la liste des gares de la ligne (LineStations). Les deux années
     * égales, les dates des événements de ligne (station_lines.first_event_date / last_event_date, posées par
     * vff:attach-stations) et celles de la gare tranchent au jour ou au mois (strictlyBefore) ; une date
     * de gare déduite des lignes (provenance inferred:…) ne tranche rien, une fermeture suivie d'une
     * réouverture non plus. À l'année près, la période reste « 1857-1857 ».
     * Contrôle : invariant stations.line_service_outside_station_life (DataInvariants).
     */
    private function reconcileServiceYears(): void
    {
        $first = DB::affectingStatement(<<<'SQL'
            UPDATE station_lines sl SET first_year = EXTRACT(YEAR FROM s.opened_at)::int, years_source = COALESCE(sl.years_source, 'station')
            FROM stations s
            WHERE s.id = sl.station_id AND s.opened_at IS NOT NULL
              AND (sl.first_year IS NULL OR sl.first_year < EXTRACT(YEAR FROM s.opened_at))
        SQL);
        $last = DB::affectingStatement(<<<'SQL'
            UPDATE station_lines sl SET last_year = EXTRACT(YEAR FROM s.closed_at)::int, years_source = COALESCE(sl.years_source, 'station')
            FROM stations s
            WHERE s.id = sl.station_id AND s.closed_at IS NOT NULL AND s.closed_at <= CURRENT_DATE
              AND (sl.last_year IS NULL OR sl.last_year > EXTRACT(YEAR FROM s.closed_at))
        SQL);
        // Ligne fermée avant l'ouverture de la gare, ou gare fermée avant l'ouverture de la ligne (Lille-Europe,
        // 1994, sur le tracé du tramway de Lille à Leers fermé en 1956) : la ligne ne l'a jamais desservie.
        // La même année, les dates le montrent quand elles sont connues au jour ou au mois (La Fouillouse sur
        // 4014 : section fermée le 26/09/1857, gare ouverte le 24/11/1857) ; voir strictlyBefore.
        $lineClosedFirst = self::strictlyBefore('sl.last_event_date', 'sl.last_event_precision', 's.opened_at', 's.opened_precision');
        $stationClosedFirst = self::strictlyBefore('s.closed_at', 's.closed_precision', 'sl.first_event_date', 'sl.first_event_precision');
        $never = DB::affectingStatement(<<<SQL
            UPDATE station_lines sl SET years_source = 'never_served'
            FROM stations s
            WHERE s.id = sl.station_id AND sl.years_source IS DISTINCT FROM 'never_served'
              AND ((sl.last_year < sl.first_year
                    AND ((s.opened_at IS NOT NULL AND sl.first_year = EXTRACT(YEAR FROM s.opened_at))
                      OR (s.closed_at IS NOT NULL AND sl.last_year = EXTRACT(YEAR FROM s.closed_at))))
                OR (s.opened_at IS NOT NULL AND COALESCE(s.provenance->>'opened_at', '') NOT LIKE 'inferred:%'
                    AND {$lineClosedFirst})
                OR (s.closed_at IS NOT NULL AND s.closed_at <= CURRENT_DATE AND COALESCE(s.provenance->>'closed_at', '') NOT LIKE 'inferred:%'
                    AND NOT EXISTS (SELECT 1 FROM station_events r WHERE r.station_id = s.id AND r.type = 'reopening' AND r.event_date > s.closed_at)
                    AND {$stationClosedFirst}))
        SQL);
        $stops = DB::affectingStatement(<<<'SQL'
            DELETE FROM line_stops ls USING station_lines sl, eras e
            WHERE sl.line_id = ls.line_id AND sl.station_id = ls.station_id AND e.id = ls.era_id
              AND ((sl.last_year IS NOT NULL AND sl.last_year <= e.year)
                   OR (sl.first_year IS NOT NULL AND sl.first_year > e.year AND NOT e.is_current))
        SQL);
        if ($stops > 0) {
            // Arrêts restants renumérotés et extrémités recalculées, comme buildLineStops : sinon l'API exposait
            // des positions à trous et une ligne sans gare d'extrémité quand l'arrêt retiré en était une.
            DB::statement(<<<'SQL'
                UPDATE line_stops ls SET position = r.position, is_terminal = r.is_terminal
                FROM (SELECT id, ROW_NUMBER() OVER w - 1 AS position,
                             COUNT(*) OVER (PARTITION BY line_id, era_id) > 1
                             AND (ROW_NUMBER() OVER w = 1 OR ROW_NUMBER() OVER w = COUNT(*) OVER (PARTITION BY line_id, era_id)) AS is_terminal
                      FROM line_stops WINDOW w AS (PARTITION BY line_id, era_id ORDER BY pk_m, station_id)) r
                WHERE r.id = ls.id AND (ls.position IS DISTINCT FROM r.position OR ls.is_terminal IS DISTINCT FROM r.is_terminal)
            SQL);
        }
        $this->info("Années de desserte bornées par les dates des gares : {$first} débuts, {$last} fins, {$never} jamais desservies ; {$stops} arrêts hors période retirés");
    }

    /**
     * Événements retenus pour la chronologie : ceux des sources quand il y en
     * a, sinon les déduits ; ouverture = la meilleure des ouvertures (une
     * seule), les autres événements sont gardés tels quels.
     *
     * @param  array<int, array<string, mixed>>  $evs
     * @param  array{freight_today?: bool, served_seen?: ?string}  $hints
     * @return array<int, array<string, mixed>>
     */
    private function canonicalEvents(array $evs, array $hints = []): array
    {
        // Fermetures des sources en masse contredites par la desserte actuelle, puis « fermeture »
        // Wikidata d'une gare fret seul = fin des voyageurs (StationTimeline, règles du 2026-10-20).
        $evs = StationTimeline::dropContradictedClosures($evs, $hints['served_seen'] ?? null);
        $evs = StationTimeline::massClosureAsPassenger($evs, (bool) ($hints['freight_today'] ?? false));
        $explicit = array_values(array_filter($evs, fn ($e) => ! $e['inferred']));
        // « Fermée après 1950 » (précision after) n'est pas une date : la fermeture
        // déduite des lignes reste utilisée à côté (la note d'incertitude s'en sert).
        $has = fn (array $list, array $types) => (bool) array_filter($list, fn ($e) => in_array($e['type'], $types, true)
            && ($e['precision'] ?? 'day') !== StationTimeline::AFTER);
        $out = $explicit;
        foreach ([['opening'], ['closure', 'demolition'], ['closure_passenger'], ['reopening']] as $types) {
            if (! $has($explicit, $types)) {
                $out = array_merge($out, array_values(array_filter($evs, fn ($e) => $e['inferred'] && in_array($e['type'], $types, true))));
            }
        }
        // Plusieurs sources pour l'ouverture : on garde la plus ancienne
        // (les autres décrivent souvent la même ouverture à une autre précision).
        $openings = array_filter($out, fn ($e) => $e['type'] === 'opening');
        if (count($openings) > 1) {
            $first = min(array_column($openings, 'date'));
            $out = array_values(array_filter($out, fn ($e) => $e['type'] !== 'opening' || $e['date'] === $first));
            $seen = false;
            $out = array_values(array_filter($out, function ($e) use (&$seen) {
                if ($e['type'] !== 'opening') {
                    return true;
                }
                if ($seen) {
                    return false;
                }

                return $seen = true;
            }));
        }
        // Même fermeture vue par plusieurs sources (ou gares homonymes fusionnées) : la source la plus sûre.
        usort($out, [StationTimeline::class, 'byDate']);
        $out = StationTimeline::dedupeClosures($out);
        // Fermetures en double (même année, sources différentes) : une seule.
        $seenClose = [];
        $out = array_values(array_filter($out, function ($e) use (&$seenClose) {
            if (! in_array($e['type'], ['closure', 'closure_passenger', 'demolition'], true)) {
                return true;
            }
            $k = $e['type'].substr($e['date'], 0, 4);
            if (isset($seenClose[$k])) {
                return false;
            }

            return $seenClose[$k] = true;
        }));
        usort($out, [StationTimeline::class, 'byDate']);

        return $out;
    }

    /**
     * Dates déduites pour les gares sans date propre.
     */
    /** @param  list<int>  $notServedToday  gares « plus desservies aujourd'hui » (correction « current ») : jamais rouvertes par leur ligne */
    private function inferEvents(array $notServedToday = []): void
    {
        DB::table('station_events')->where('is_inferred', true)->delete();
        $cover = AttachStations::EVENT_COVERS_STATION;
        $coverSl2 = str_replace('sl.pk_m', 'sl2.pk_m', $cover);
        // Nom de ligne dans la phrase (gabarits lang/fr/generated.php, station.*) : « ligne de X à Y »,
        // jamais « la ligne Ligne de X à Y » ; traduit à l'affichage par App\Support\GeneratedText.
        $line = GeneratedText::lineInSentenceSql('l.name');
        $xLine = GeneratedText::lineInSentenceSql('x.lname');
        // Une fermeture « après 1950 » (date_precision = after) n'est pas une date : elle ne bloque pas la déduction.
        $explicit = fn (string $types) => "NOT EXISTS (SELECT 1 FROM station_events x WHERE x.station_id = s.id AND NOT x.is_inferred AND x.type IN ({$types}) AND x.date_precision <> 'after')";
        // Deux événements de ligne à la même date : départage fixe (avant, l'ordre physique des lignes
        // décidait, et la date d'une gare changeait d'un passage à l'autre). D'abord la précision la MOINS
        // fine : une source « au jour » qui tombe sur le 1er du mois d'une autre source « au mois » ne fait
        // souvent que le reprendre (Ernée : « mars 1987 » chez Wikipédia et lignes-oubliées, « 01/03/1987 »
        // chez OHM) ; puis la fermeture totale avant les fermetures partielles ; puis le premier enregistré.
        $precisionRank = "CASE le.date_precision WHEN 'year' THEN 0 WHEN 'month' THEN 1 WHEN 'day' THEN 2 ELSE 3 END";
        $typeRank = "CASE le.type WHEN 'closure' THEN 0 WHEN 'closure_freight' THEN 1 WHEN 'closure_passenger' THEN 2 ELSE 3 END";
        $sameDay = "{$precisionRank}, {$typeRank}, le.id";

        // a. Ouverture de la ligne (section couvrant la gare). Un événement daté
        //    pour une section précise (PK ou géométrie) passe avant un événement
        //    « toute la ligne », qui donne souvent la 1re ouverture d'une ligne
        //    ouverte par sections (ex. Paris - Brest : 1840 pour toutes les gares).
        $a = DB::affectingStatement(<<<SQL
            INSERT INTO station_events (station_id, type, event_date, date_precision, source, source_ref, source_url, is_inferred, note, created_at, updated_at)
            SELECT DISTINCT ON (s.id) s.id, 'opening', le.event_date, le.date_precision, 'inferred:line_event', 'line_event:'||le.id,
                   le.source_url, TRUE, 'Ouverture de la '||{$line}, NOW(), NOW()
            FROM stations s
            JOIN station_lines sl ON sl.station_id = s.id
            JOIN lines l ON l.id = sl.line_id
            JOIN line_events le ON le.line_id = sl.line_id AND le.type IN ('opening', 'opening_passenger') AND {$cover}
            WHERE {$explicit("'opening'")}
            ORDER BY s.id, (le.section_pk_start_m IS NULL AND le.section_geom IS NULL), le.event_date, {$precisionRank}, le.id
        SQL);
        // b. À défaut, tracés datés passant à < 30 m (OHM : start_date).
        $b = DB::affectingStatement(<<<SQL
            INSERT INTO station_events (station_id, type, event_date, date_precision, source, source_ref, is_inferred, note, created_at, updated_at)
            SELECT s.id, 'opening', MAKE_DATE(MIN(st.opened_year), 1, 1), 'year', 'inferred:trace',
                   MIN(st.source||':'||st.source_ref), TRUE, 'Ouverture du tracé desservant la gare', NOW(), NOW()
            FROM stations s
            JOIN source_traces st ON st.geom && ST_Expand(s.geom, 0.001) AND ST_DWithin(st.geom::geography, s.geom::geography, 30)
             AND st.opened_year BETWEEN 1827 AND 2035
            WHERE {$explicit("'opening'")}
              AND NOT EXISTS (SELECT 1 FROM station_events y WHERE y.station_id = s.id AND y.type = 'opening')
            GROUP BY s.id
        SQL);
        // c. Fermeture totale des gares disparues : fin de desserte de chacune de leurs
        //    lignes au PK de la gare (fermeture, fin du fret ou fermeture aux voyageurs ;
        //    un événement daté pour la section passe avant un événement « toute la
        //    ligne » ; déclassement / dépose seulement à défaut, ce sont des bornes
        //    tardives), puis la plus tardive des lignes (la gare n'existe plus : toutes
        //    ses lignes sont fermées). Précision de l'événement conservée.
        $c = DB::affectingStatement(<<<SQL
            INSERT INTO station_events (station_id, type, event_date, date_precision, source, source_ref, source_url, is_inferred, note, created_at, updated_at)
            SELECT DISTINCT ON (x.station_id) x.station_id, 'closure', x.event_date, x.date_precision, 'inferred:line_event',
                   'line_event:'||x.id, x.source_url, TRUE, 'Fin de desserte de la '||{$xLine}, NOW(), NOW()
            FROM (
                SELECT DISTINCT ON (s.id, sl.line_id) s.id AS station_id, le.id, le.event_date, le.date_precision, le.source_url, l.name AS lname,
                       {$typeRank} AS type_rank, {$precisionRank} AS precision_rank
                FROM stations s
                JOIN station_lines sl ON sl.station_id = s.id
                JOIN lines l ON l.id = sl.line_id
                JOIN line_events le ON le.line_id = sl.line_id
                 AND le.type IN ('closure', 'closure_freight', 'closure_passenger', 'declassification', 'dismantling') AND {$cover}
                WHERE s.source_status = 'gone' AND {$explicit("'closure', 'demolition'")}
                ORDER BY s.id, sl.line_id, (le.type IN ('declassification', 'dismantling')),
                         (le.section_pk_start_m IS NULL AND le.section_geom IS NULL), le.event_date DESC, {$sameDay}
            ) x
            ORDER BY x.station_id, x.event_date DESC, x.precision_rank, x.type_rank, x.id
        SQL);
        // d. … à défaut, dernière fermeture des tracés datés à < 30 m.
        $d = DB::affectingStatement(<<<SQL
            INSERT INTO station_events (station_id, type, event_date, date_precision, source, is_inferred, note, created_at, updated_at)
            SELECT s.id, 'closure', MAKE_DATE(MAX(st.closed_year), 1, 1), 'year', 'inferred:trace', TRUE, 'Fermeture du tracé desservant la gare', NOW(), NOW()
            FROM stations s
            JOIN source_traces st ON st.geom && ST_Expand(s.geom, 0.001) AND ST_DWithin(st.geom::geography, s.geom::geography, 30)
             AND st.opened_year IS NOT NULL
            WHERE s.source_status = 'gone' AND {$explicit("'closure', 'demolition'")}
              AND NOT EXISTS (SELECT 1 FROM station_events y WHERE y.station_id = s.id AND y.type IN ('closure', 'demolition') AND y.date_precision <> 'after')
            GROUP BY s.id
            HAVING MAX(st.closed_year) BETWEEN 1827 AND 2035
        SQL);
        // e. Fermeture aux voyageurs : toutes les lignes de la gare fermées aux voyageurs
        //    (section datée d'abord, puis « toute la ligne ») ; la plus tardive des lignes,
        //    avec sa précision. Une ligne rattachée seulement par son nom ou par un terminus
        //    relu (terminus_name, terminus_reviewed : tramway, réseau secondaire, rarement daté)
        //    n'est pas exigée : sans fermeture datée,
        //    elle effaçait la date des autres lignes (Gex, 31/05/1980 ; Mondoubleau, 26/10/1971).
        $e = DB::affectingStatement(<<<SQL
            INSERT INTO station_events (station_id, type, event_date, date_precision, source, source_ref, is_inferred, note, created_at, updated_at)
            SELECT DISTINCT ON (x.station_id) x.station_id, 'closure_passenger', x.event_date, x.date_precision, 'inferred:line_event',
                   'line_event:'||x.id, TRUE, 'Fermeture aux voyageurs des lignes desservant la gare', NOW(), NOW()
            FROM (
                SELECT DISTINCT ON (s.id, sl.line_id) s.id AS station_id, le.id, le.event_date, le.date_precision
                FROM stations s
                JOIN station_lines sl ON sl.station_id = s.id
                JOIN line_events le ON le.line_id = sl.line_id AND le.type = 'closure_passenger' AND {$cover}
                WHERE COALESCE(s.source_status, s.status) IN ('no_commercial', 'declassified', 'gone')
                  AND {$explicit("'closure_passenger', 'closure'")}
                ORDER BY s.id, sl.line_id, (le.section_pk_start_m IS NULL AND le.section_geom IS NULL), le.event_date DESC, {$precisionRank}, le.id
            ) x
            WHERE (SELECT COUNT(*) FROM station_lines a WHERE a.station_id = x.station_id AND a.method NOT IN ('terminus_name', 'terminus_reviewed'))
                = (SELECT COUNT(DISTINCT sl2.line_id) FROM station_lines sl2 JOIN stations s ON s.id = sl2.station_id
                   JOIN line_events le ON le.line_id = sl2.line_id AND le.type = 'closure_passenger'
                   WHERE sl2.station_id = x.station_id AND sl2.method NOT IN ('terminus_name', 'terminus_reviewed') AND {$coverSl2})
            ORDER BY x.station_id, x.event_date DESC, x.id
        SQL);
        // g. Gare rouverte (réouverture connue) sans fermeture antérieure connue :
        //    dernière fermeture de ses lignes (aux voyageurs ou totale) avant la réouverture.
        $g = DB::affectingStatement(<<<SQL
            INSERT INTO station_events (station_id, type, event_date, date_precision, source, source_ref, source_url, is_inferred, note, created_at, updated_at)
            SELECT DISTINCT ON (s.id) s.id, 'closure_passenger', le.event_date, le.date_precision, 'inferred:line_event', 'line_event:'||le.id,
                   le.source_url, TRUE, 'Fermeture aux voyageurs de la '||{$line}, NOW(), NOW()
            FROM stations s
            JOIN station_events r ON r.station_id = s.id AND r.type = 'reopening' AND NOT r.is_inferred
            JOIN station_lines sl ON sl.station_id = s.id
            JOIN lines l ON l.id = sl.line_id
            JOIN line_events le ON le.line_id = sl.line_id AND le.type IN ('closure_passenger', 'closure')
             AND le.event_date < r.event_date AND {$cover}
            WHERE NOT EXISTS (SELECT 1 FROM station_events x WHERE x.station_id = s.id
                              AND x.type IN ('closure', 'closure_passenger', 'demolition') AND x.event_date < r.event_date)
            ORDER BY s.id, le.event_date DESC, {$sameDay}
            ON CONFLICT DO NOTHING
        SQL);
        // f. Réouverture aux voyageurs d'une ligne après la fermeture (gare voyageurs aujourd'hui), sauf
        //    gare dont une correction sourcée dit l'arrêt supprimé (Villeneuve-lès-Avignon : ligne rouverte
        //    aux voyageurs le 29/08/2022, gare restée fermée).
        $notServed = $notServedToday === [] ? '' : 'AND s.id NOT IN ('.implode(',', array_map('intval', $notServedToday)).')';
        $f = DB::affectingStatement(<<<SQL
            INSERT INTO station_events (station_id, type, event_date, date_precision, source, source_ref, is_inferred, note, created_at, updated_at)
            SELECT DISTINCT ON (s.id) s.id, 'reopening', le.event_date, le.date_precision, 'inferred:line_event', 'line_event:'||le.id, TRUE,
                   'Réouverture aux voyageurs de la '||{$line}, NOW(), NOW()
            FROM stations s
            JOIN station_events cp ON cp.station_id = s.id AND cp.type IN ('closure_passenger', 'closure')
            JOIN station_lines sl ON sl.station_id = s.id
            JOIN lines l ON l.id = sl.line_id
            JOIN line_events le ON le.line_id = sl.line_id AND le.type IN ('reopening', 'reopening_passenger')
             AND le.event_date > cp.event_date AND {$cover}
            WHERE COALESCE(s.source_status, s.status) IN ('active', 'tourist') AND {$explicit("'reopening'")} {$notServed}
              -- Une réouverture de LIGNE ne rouvre pas une gare que ses propres sources disent désaffectée
              -- (OSM disused…, Wikidata fermée) sans aucun indice de desserte actuelle (gares-de-voyageurs,
              -- OSM ou Wikidata en service) : Arches - Saint-Dié rouverte le 12/12/2021 avec 3 gares seulement,
              -- Niversac - Agen « rouverte » le 22/03/2026 après une coupure (Mauzens-Miremont fermée en 2020).
              AND (NOT EXISTS (SELECT 1 FROM station_sources p WHERE p.station_id = s.id
                       AND ((p.source = 'osm' AND p.lifecycle IN ('disused', 'abandoned', 'razed', 'historic'))
                         OR (p.source = 'wikidata' AND p.lifecycle NOT IN ('active', 'unknown', 'planned'))))
                   OR EXISTS (SELECT 1 FROM station_sources p WHERE p.station_id = s.id
                       AND (p.source = 'sncf_voyageurs' OR (p.source IN ('osm', 'wikidata') AND p.lifecycle = 'active'))))
            ORDER BY s.id, le.event_date DESC, {$sameDay}
            ON CONFLICT DO NOTHING
        SQL);
        $this->line("Dates déduites : ouverture ligne {$a}, ouverture tracé {$b}, fermeture lignes {$c}, fermeture tracé {$d}, fermeture voyageurs {$e}, fermeture avant réouverture {$g}, réouverture {$f}");

        // Dates retenues sur la fiche quand la gare n'en a pas de propre.
        DB::statement(<<<'SQL'
            UPDATE stations s SET opened_at = e.event_date, opened_precision = e.date_precision,
                   provenance = COALESCE(s.provenance, '{}'::jsonb) || jsonb_build_object('opened_at', e.source)
            FROM (SELECT DISTINCT ON (station_id) station_id, event_date, date_precision, source
                  FROM station_events WHERE is_inferred AND type = 'opening' ORDER BY station_id, event_date) e
            WHERE e.station_id = s.id AND (s.opened_at IS NULL OR s.provenance->>'opened_at' LIKE 'inferred:%')
        SQL);
        // Points techniques et gares projetées : ni date déduite, ni ouverture déduite sur la fiche.
        DB::statement("DELETE FROM station_events WHERE is_inferred AND station_id IN (SELECT id FROM stations WHERE jsonb_exists(provenance, 'kind'))");
        DB::statement(<<<'SQL'
            UPDATE stations SET opened_at = NULL, opened_precision = NULL, provenance = provenance - 'opened_at'
            WHERE jsonb_exists(provenance, 'kind') AND provenance->>'opened_at' LIKE 'inferred:%'
        SQL);
        // stations.closed_at : écrite à la fin par resolveClosures() (date unique carte / fiches).
    }

    /**
     * Nature des points (StationTimeline::kind) → provenance.kind : « technical »
     * (point sans preuve d'ouverture au public : poste, garage, bifurcation,
     * point d'une LGV), « projected » (gare projetée jamais ouverte) ; absente
     * pour une gare. Ces points n'ont ni ère (carte, line_stops, fiche ligne),
     * ni date déduite, ni date de fermeture.
     *
     * @param  array<int, string>  $forced  nature imposée par une correction sourcée (StationCorrections)
     */
    private function classifyKinds(array $forced = []): void
    {
        $kinds = [StationTimeline::KIND_TECHNICAL => [], StationTimeline::KIND_PROJECTED => [], StationTimeline::KIND_EXCLUDED => []];
        foreach (DB::select(<<<'SQL'
            SELECT s.id, s.name,
                   COALESCE(BOOL_OR((ss.source = 'sncf' AND (ss.passenger OR ss.freight)) OR ss.source = 'sncf_voyageurs'
                       OR (ss.source IN ('osm', 'ohm', 'wikidata', 'lignes-oubliees', 'wikipedia_schema') AND ss.lifecycle <> 'planned')), FALSE)
                   OR EXISTS (SELECT 1 FROM station_events e WHERE e.station_id = s.id AND NOT e.is_inferred AND e.event_date <= ?) AS proof,
                   COALESCE(BOOL_OR(ss.lifecycle = 'planned'), FALSE) OR s.lifecycle = 'planned' AS planned,
                   COALESCE((SELECT BOOL_AND(l.is_lgv) FROM station_lines sl JOIN lines l ON l.id = sl.line_id WHERE sl.station_id = s.id), FALSE) AS lgv_only
            FROM stations s LEFT JOIN station_sources ss ON ss.station_id = s.id
            GROUP BY s.id
        SQL, [$this->today()]) as $r) {
            // Nature imposée par une correction sourcée (hors réseau ferré, gare projetée).
            $kind = $forced[(int) $r->id] ?? StationTimeline::kind((string) $r->name, (bool) $r->proof, (bool) $r->lgv_only, (bool) $r->planned);
            if ($kind !== StationTimeline::KIND_STATION) {
                $kinds[$kind][] = (int) $r->id;
            }
        }
        DB::transaction(function () use ($kinds) {
            DB::statement("UPDATE stations SET provenance = provenance - 'kind' WHERE jsonb_exists(provenance, 'kind')");
            foreach ($kinds as $kind => $ids) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    DB::table('stations')->whereIn('id', $chunk)
                        ->update(['provenance' => DB::raw("COALESCE(provenance, '{}'::jsonb) || jsonb_build_object('kind', '{$kind}')")]);
                }
            }
        });
        $this->line(sprintf('Points qui ne sont pas des gares : %d points techniques, %d gares projetées, %d hors du réseau ferré',
            count($kinds[StationTimeline::KIND_TECHNICAL]), count($kinds[StationTimeline::KIND_PROJECTED]), count($kinds[StationTimeline::KIND_EXCLUDED])));
    }

    /** Date du jour de l'ère courante (--today, sinon aujourd'hui). */
    private function today(): string
    {
        $t = $this->option('today');

        return is_string($t) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) === 1 ? $t : now()->toDateString();
    }

    /**
     * Indices de desserte actuelle par gare (StationTimeline::currentStatus) :
     * jeu gares-de-voyageurs (et date du relevé), arrêt en service selon OSM /
     * Wikidata, drapeau fret de liste-des-gares, source du statut.
     *
     * @return array<int, array<string, mixed>>
     */
    private function currentEvidence(): array
    {
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT s.id, s.provenance->>'status' AS status_source,
                   COALESCE(BOOL_OR(ss.source = 'sncf_voyageurs'), FALSE) AS voyageurs,
                   MAX(ss.seen_at) FILTER (WHERE ss.source = 'sncf_voyageurs') AS voyageurs_seen,
                   COALESCE(BOOL_OR(ss.source = 'osm' AND ss.lifecycle = 'active'), FALSE) AS osm_active,
                   COALESCE(BOOL_OR(ss.source = 'wikidata' AND ss.lifecycle = 'active'), FALSE) AS wikidata_active,
                   COALESCE(BOOL_OR(ss.source = 'sncf' AND ss.freight), FALSE) AS sncf_freight,
                   COALESCE(BOOL_OR((ss.source = 'osm' AND ss.lifecycle IN ('disused', 'abandoned', 'razed', 'historic'))
                       OR (ss.source = 'wikidata' AND ss.lifecycle NOT IN ('active', 'unknown', 'planned'))), FALSE) AS closure_proof,
                   EXISTS (SELECT 1 FROM station_events r WHERE r.station_id = s.id AND r.type = 'reopening') AS reopened
            FROM stations s LEFT JOIN station_sources ss ON ss.station_id = s.id
            GROUP BY s.id
        SQL) as $r) {
            $out[(int) $r->id] = ['status_source' => $r->status_source, 'voyageurs' => (bool) $r->voyageurs,
                'voyageurs_seen' => $r->voyageurs_seen, 'osm_active' => (bool) $r->osm_active,
                'wikidata_active' => (bool) $r->wikidata_active, 'sncf_freight' => (bool) $r->sncf_freight,
                'closure_proof' => (bool) $r->closure_proof, 'reopened' => (bool) $r->reopened];
        }

        return $out;
    }

    /**
     * Date de fermeture retenue (stations.closed_at, closed_precision,
     * provenance.closed_at / closed_after / closed_conflict) : la SEULE date de
     * fermeture affichée par la carte, la fiche gare et la fiche ligne. Choisie
     * dans l'intervalle où station_eras place la fermeture
     * (StationTimeline::resolveClosure) ; toutes les ères sont relues en base
     * (après un --era seul aussi, ex. vff:refresh-current-era).
     */
    private function resolveClosures(): void
    {
        $current = DB::table('eras')->where('is_current', true)->value('year');
        if ($current === null) {
            return;
        }
        $today = $this->today();
        $eras = [];
        foreach (DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')
            ->get(['se.station_id', 'e.year', 'se.status', 'se.uncertain']) as $r) {
            $eras[(int) $r->station_id][(int) $r->year] = ['status' => $r->status, 'uncertain' => (bool) $r->uncertain];
        }
        $types = ['closure', 'closure_passenger', 'closure_freight', 'demolition'];
        $cands = [];
        foreach (DB::table('station_events')->whereIn('type', $types)
            ->get(['station_id', 'type', 'event_date', 'date_precision', 'source', 'is_inferred']) as $e) {
            $cands[(int) $e->station_id][] = ['type' => $e->type, 'date' => $e->event_date, 'precision' => $e->date_precision,
                'source' => $e->source, 'inferred' => (bool) $e->is_inferred, 'line' => false];
        }
        $cover = AttachStations::EVENT_COVERS_STATION;
        foreach (DB::select(<<<SQL
            SELECT sl.station_id, le.type, le.event_date, le.date_precision, 'inferred:line_event' AS source
            FROM station_lines sl
            JOIN stations s ON s.id = sl.station_id
            JOIN line_events le ON le.line_id = sl.line_id
             AND le.type IN ('closure', 'closure_freight', 'closure_passenger', 'declassification', 'dismantling') AND {$cover}
        SQL) as $e) {
            $cands[(int) $e->station_id][] = ['type' => $e->type, 'date' => $e->event_date, 'precision' => $e->date_precision,
                'source' => $e->source, 'inferred' => true, 'line' => true];
        }
        // Dernière ère où la gare est montrée ouverte (non confirmée) sur une de ses lignes en service
        // (hors patrimoine) à moins de 500 m : la fin de desserte d'une ligne datée avant n'est pas la
        // fermeture de la gare (StationTimeline::dropLineClosuresBeforeService).
        $servedUntil = [];
        foreach (DB::select(<<<'SQL'
            SELECT se.station_id, MAX(e.year) AS y
            FROM station_eras se
            JOIN eras e ON e.id = se.era_id AND NOT e.is_current
            JOIN stations s ON s.id = se.station_id
            WHERE se.uncertain AND se.status IN ('active', 'tourist', 'no_commercial')
              AND EXISTS (SELECT 1 FROM station_lines sl
                          JOIN line_segments ls ON ls.line_id = sl.line_id AND ls.era_id = se.era_id
                           AND ls.status IN ('in_service', 'single_track') AND NOT ls.is_heritage
                           AND ls.geom && ST_Expand(s.geom, 0.007) AND ST_DWithin(ls.geom::geography, s.geom::geography, 500)
                          WHERE sl.station_id = s.id)
            GROUP BY se.station_id
        SQL) as $r) {
            $servedUntil[(int) $r->station_id] = (int) $r->y;
        }
        $stations = DB::table('stations')->get(['id', 'closed_at', 'closed_precision', DB::raw("provenance->>'kind' AS kind"),
            DB::raw("provenance->>'closed_at' AS src"), DB::raw("provenance->>'closed_after' AS after"), 'source_status', 'status']);
        // Mêmes règles que la chronologie (canonicalEvents) : fermeture en masse contredite par la desserte
        // actuelle, « fermeture » Wikidata d'une gare fret seul = fin des voyageurs (Layrac 1970).
        $evidence = $this->currentEvidence();
        $manual = fn ($st) => $st->closed_at !== null && ($st->src === null || in_array($st->src, ['manual', 'contribution'], true));
        foreach ($stations as $st) {
            if ($manual($st)) {
                $cands[(int) $st->id][] = ['type' => 'closure', 'date' => $st->closed_at, 'source' => 'manual', 'inferred' => false, 'line' => false,
                    'precision' => in_array($st->closed_precision, ['day', 'month', 'year'], true) ? $st->closed_precision : 'day'];
            }
        }

        $rows = [];
        $stats = ['fermées' => 0, 'date de la gare' => 0, 'fin de desserte des lignes' => 0, 'intervalle' => 0, 'conflits' => 0, 'modifiées' => 0];
        foreach ($stations as $st) {
            $sid = (int) $st->id;
            if ($st->kind !== null) {
                // Point technique / gare projetée : jamais « fermée ».
                if ($st->closed_at !== null && ! $manual($st)) {
                    $rows[] = [$sid, null, null, json_encode(['closed_at' => null])];
                }

                continue;
            }
            if (! isset($eras[$sid])) {
                continue; // jamais montrée : date des sources gardée
            }
            $ev = $evidence[$sid] ?? [];
            $c = StationTimeline::dropContradictedClosures($cands[$sid] ?? [], ($ev['voyageurs'] ?? false) ? ($ev['voyageurs_seen'] ?? null) : null);
            $c = StationTimeline::massClosureAsPassenger($c, ($st->source_status ?? $st->status) === StationTimeline::FREIGHT || ($ev['sncf_freight'] ?? false));
            $c = StationTimeline::dropLineClosuresBeforeService($c, $servedUntil[$sid] ?? null);
            $c = StationTimeline::roundYearAsDecade($c);
            $r = StationTimeline::resolveClosure($eras[$sid], (int) $current, $today, $c);
            if ($r === null) {
                // Ouverte aujourd'hui : pas de date de fermeture, sauf une fermeture à venir ou saisie à la main.
                if ($st->closed_at !== null && $st->closed_at <= $today && ! $manual($st)) {
                    $rows[] = [$sid, null, null, json_encode(['closed_at' => null])];
                }

                continue;
            }
            $stats['fermées']++;
            $kind = match (true) {
                $r['precision'] === StationTimeline::RANGE && $r['source'] === 'vff:eras' => 'intervalle',
                str_starts_with($r['source'], 'inferred:') => 'fin de desserte des lignes',
                default => 'date de la gare',
            };
            $stats[$kind]++;
            if ($r['conflict'] !== null) {
                $stats['conflits']++;
            }
            $prov = ['closed_at' => $r['source'], 'closed_after' => $r['after'], 'closed_conflict' => $r['conflict']];
            if ($st->closed_at === $r['date'] && $st->closed_precision === $r['precision'] && $st->src === $r['source']
                && (string) $st->after === (string) $r['after'] && $r['conflict'] === null) {
                continue;
            }
            $rows[] = [$sid, $r['date'], $r['precision'], json_encode($prov)];
        }
        $stats['modifiées'] = count($rows);
        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                $values = implode(', ', array_fill(0, count($chunk), '(?::bigint, ?::date, ?::varchar, ?::jsonb)'));
                DB::update(<<<SQL
                    UPDATE stations s SET closed_at = v.d, closed_precision = v.p,
                           provenance = jsonb_strip_nulls((COALESCE(s.provenance, '{}'::jsonb) - 'closed_at' - 'closed_after' - 'closed_conflict') || v.prov)
                    FROM (VALUES {$values}) AS v(id, d, p, prov)
                    WHERE s.id = v.id
                SQL, array_merge(...$chunk));
            }
        });
        $this->line('Dates de fermeture retenues (carte, fiche gare, fiche ligne) : '
            .collect($stats)->map(fn ($n, $k) => "{$k} {$n}")->implode(', '));
    }

    /**
     * line_stops de chaque ère, ordonnés par PK.
     *
     * @param  array<int, int>  $eraIds
     * @return int nombre d'arrêts écrits
     */
    private function buildLineStops(array $eraIds): int
    {
        $total = 0;
        foreach ($eraIds as $eraId) {
            $hasSegments = DB::table('line_segments')->where('era_id', $eraId)->exists();
            $lineInService = $hasSegments ? <<<'SQL'
                AND EXISTS (SELECT 1 FROM line_segments ls WHERE ls.era_id = e.id AND ls.line_id = sl.line_id
                            AND ls.status IN ('in_service', 'single_track', 'service_track')
                            AND ls.geom && ST_Expand(s.geom, 0.007) AND ST_DWithin(ls.geom::geography, s.geom::geography, 500))
                SQL : '';
            DB::transaction(function () use ($eraId, $lineInService, &$total) {
                DB::table('line_stops')->where('era_id', $eraId)->delete();
                $total += DB::affectingStatement(<<<SQL
                    INSERT INTO line_stops (line_id, era_id, station_id, position, pk_m, effective_status, is_terminal, created_at, updated_at)
                    SELECT sl.line_id, e.id, sl.station_id,
                           ROW_NUMBER() OVER (PARTITION BY sl.line_id ORDER BY sl.pk_m, sl.station_id) - 1,
                           sl.pk_m, se.status, FALSE, NOW(), NOW()
                    FROM station_lines sl
                    JOIN stations s ON s.id = sl.station_id
                    JOIN eras e ON e.id = :era
                    JOIN station_eras se ON se.station_id = sl.station_id AND se.era_id = e.id
                    WHERE sl.pk_m IS NOT NULL
                      AND (sl.first_year IS NULL OR sl.first_year <= e.year OR e.is_current)
                      AND (sl.last_year IS NULL OR sl.last_year > e.year)
                      {$lineInService}
                SQL, ['era' => $eraId]);
                DB::statement(<<<'SQL'
                    UPDATE line_stops ls SET is_terminal = TRUE
                    FROM (SELECT line_id, MIN(position) AS lo, MAX(position) AS hi FROM line_stops WHERE era_id = ? GROUP BY line_id HAVING COUNT(*) > 1) t
                    WHERE ls.era_id = ? AND ls.line_id = t.line_id AND ls.position IN (t.lo, t.hi)
                SQL, [$eraId, $eraId]);
            });
        }

        return $total;
    }
}
