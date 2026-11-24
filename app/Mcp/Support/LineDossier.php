<?php

namespace App\Mcp\Support;

use App\Models\Line;
use App\Services\LineFacts;
use App\Services\LinePhases;
use App\Services\LineSheet;
use App\Services\LineStations;
use App\Services\LineStats;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingDating;
use App\Services\StationServiceDates;
use App\Support\EventText;
use App\Support\Lgv;
use Illuminate\Support\Facades\DB;

/**
 * Fiche d'une ligne pour le serveur MCP (outil get_line et ressource Markdown), composée avec les
 * services de la fiche web : LinePhases (phases datées, état décennie par décennie, longueur du
 * tracé), LineStats (électrification, vitesse, signalisation de l'ère affichée), LineFacts
 * (faits encyclopédiques rapportés à leur source), les gares (LineStations, avec leurs dates de
 * desserte) et les événements datés (LineEvents).
 *
 * Longueurs en kilomètres (une décimale), dates ISO AAAA-MM-JJ à lire avec leur précision.
 */
final class LineDossier
{
    public function __construct(
        private readonly LineStats $stats,
        private readonly LineEvents $events,
        private readonly Attribution $attribution,
        private readonly LineSignallingHistory $signallingHistory,
        private readonly LineStations $stations,
        private readonly ServedEventDates $served,
        private readonly SignallingDating $signallingDating,
    ) {}

    /**
     * @return array{line: array<string, mixed>, providers: list<?string>}
     */
    public function build(Line $line, string $lang, bool $withEvents = true, int $maxEvents = 100): array
    {
        $era = DB::selectOne(
            'SELECT e.id, e.year, e.is_current FROM eras e
             WHERE EXISTS (SELECT 1 FROM line_segments s WHERE s.line_id = ? AND s.era_id = e.id)
             ORDER BY e.is_current DESC, e.year DESC LIMIT 1',
            [$line->id],
        );
        $segmentIds = $era ? $line->segments()->where('era_id', $era->id)->pluck('id')->all() : [];
        // Événements servis (sans ceux d'une source non commerciale, LineEvents) : le compte est celui de la liste.
        $eventRows = $this->events->query($line->id)->get();
        $eventCount = $eventRows->count();
        $stats = $this->stats->forEra($line, $segmentIds, $era?->id, $eventCount, offSite: true);

        $phases = LinePhases::forLine($line);
        $lengthM = LineSheet::lineLengthM($phases);
        $today = collect($phases['decades'])->firstWhere('is_current', true) ?? collect($phases['decades'])->last();
        $activeTodayM = $today !== null ? array_sum(array_intersect_key($today['length_by_category'], array_flip(LinePhases::ACTIVE))) : 0;
        if ($lengthM > 0) {
            $stats['length_km'] = round($lengthM / 1000, 1);
            $stats['active_length_km'] = round($activeTodayM / 1000, 1);
        }
        $stats['length_greatest_km'] = null;

        $encyclopedia = DB::table('lines')->where('id', $line->id)->first([
            'length_official_m', 'gauge_mm_default', 'nb_voies_default', 'max_speed_kmh_official',
            'concession_year', 'wikipedia_title', 'regions', 'departements', 'network',
        ]);
        $facts = $encyclopedia !== null
            ? LineFacts::build($line, $encyclopedia, $stats, $era ? (int) $era->year : null, (bool) ($era->is_current ?? false))
            : [];

        $byId = $eventRows->keyBy('id');
        $stationRows = $this->stations->forLine((int) $line->id)['stations'];
        // Borne de desserte dont l'événement vient d'une source non commerciale : remplacée ou nulle (ServedEventDates).
        $requests = [];
        foreach ($stationRows as $i => $st) {
            $requests["{$i}:start"] = ['bound' => $st['service_start'], 'role' => 'start', 'station_id' => (int) $st['id']];
            $requests["{$i}:end"] = ['bound' => $st['service_end'], 'role' => 'end', 'station_id' => (int) $st['id']];
        }
        $servedBounds = $this->served->bounds($requests);
        $providers = DB::table('line_segments')->where('line_id', $line->id)->distinct()->pluck('source')
            ->map(fn ($s) => $this->attribution->providerFor('line_segments', $s))->all();
        foreach ($eventRows as $e) {
            $providers[] = $this->attribution->providerFor('line_events', $e->source, $e->source_url);
        }
        // Sans la phrase source : aucun extrait de Wikipédia dans le MCP (décision Q22), les liens suffisent.
        $signallingHistory = $this->signallingHistory->forSheet($line);
        if ($signallingHistory !== []) {
            $providers[] = 'wikipedia';
        }
        foreach ($facts as $f) {
            $providers[] = match ($f['source'] ?? null) {
                'wikidata' => 'wikidata',
                'sncf' => 'sncf',
                default => null,
            };
        }

        $category = fn (string $c) => ['code' => $c, 'label' => (string) trans('lines.categories.'.$c, [], $lang)];
        $out = [
            'slug' => $line->slug,
            'code' => $line->code,
            'name' => $line->name,
            'network' => $encyclopedia->network ?? null,
            'is_lgv' => (bool) ($line->is_lgv ?? false) || Lgv::nameIsLgv((string) $line->name),
            // Ligne menacée de fermeture et soutenue par VFF (halo rouge de la carte), comme `is_threatened`
            // de l'API REST ; le message de soutien lui-même n'est jamais publié (AdvocacyNotPublishedTest).
            'is_threatened' => (bool) ($line->is_threatened ?? false),
            'today' => $today === null ? null : [
                'state' => $activeTodayM > 0 ? 'open' : 'closed_or_gone',
                'by_category_km' => self::categoriesKm($today['length_by_category'], $lang),
            ],
            'length' => [
                'trace_km' => $lengthM > 0 ? round($lengthM / 1000, 1) : null,
                'in_service_today_km' => $lengthM > 0 ? round($activeTodayM / 1000, 1) : null,
                'sncf_km' => $stats['length_sncf_km'] ?? null,
            ],
            'stats_era' => $era ? ['year' => (int) $era->year, 'is_current' => (bool) $era->is_current] : null,
            'max_speed_kmh' => isset($stats['max_speed_kmh']) ? (int) $stats['max_speed_kmh'] : null,
            'gauge_mm' => isset($stats['gauge_mm']) ? (int) $stats['gauge_mm'] : null,
            'electrification' => isset($stats['electrification']) ? $stats['electrification'] + [
                'state_label' => (string) trans('mcp.electrification.'.$stats['electrification']['state'], [], $lang),
            ] : null,
            'signalling' => [
                'block_systems_km' => $stats['signalling_coverage'] ?? [],
                'train_protection_km' => $stats['train_protection_coverage'] ?? [],
                'kvb' => (bool) ($stats['kvb_equipped'] ?? false),
                'source' => 'sncf',
                // Historique daté (Wikipédia), à part de l'état actuel ci-dessus ; faits sûrs seulement, liste vide sinon.
                'history' => $signallingHistory,
                // Date connue de la mise en service du cantonnement, calculée (status, checked_at, messages fr/en/de).
                'dating' => $this->signallingDating->forLine($line),
            ],
            'facts' => array_map(fn (array $f) => array_diff_key($f, array_flip(['scope'])), $facts),
            // Date dont le seul événement vient d'une source non commerciale : non datée ici (ServedEventDates).
            'phases' => array_map(function (array $ph) use ($category, $byId, $lang) {
                return [
                    'date' => $ph['precision'] === 'range' ? null : $ph['date'],
                    'precision' => $ph['precision'],
                    'when' => $ph['precision'] === 'range'
                        ? (string) trans('mcp.between', ['from' => $ph['from_year'] ?? '?', 'to' => $ph['to_year']], $lang)
                        : EventText::isoDate((string) $ph['date'], (string) $ph['precision'], $lang),
                    'kind' => $ph['kind'],
                    'changes' => array_map(fn (array $c) => [
                        'from' => $category($c['from']),
                        'to' => $category($c['to']),
                        'length_km' => round($c['length_m'] / 1000, 1),
                        'event_id' => $c['event_id'],
                        'source' => isset($c['event_id'], $byId[$c['event_id']]) ? $byId[$c['event_id']]->source : null,
                        'source_url' => isset($c['event_id'], $byId[$c['event_id']]) ? $byId[$c['event_id']]->source_url : null,
                    ], $ph['changes']),
                    'in_service_after_km' => round($ph['active_m'] / 1000, 1),
                ];
            }, $this->served->phases($phases['phases'], $phases['portions'])),
            'best_phase' => $phases['best'],
            'decades' => array_map(fn (array $d) => [
                'year' => $d['year'],
                'in_service_km' => round($d['active_m'] / 1000, 1),
                'by_category_km' => self::categoriesKm($d['length_by_category'], null),
            ], $phases['decades']),
            // Gares de la ligne dans l'ordre du tracé, avec leur période de desserte par cette ligne (années,
            // et date sûre avec l'événement qui la donne : StationServiceDates).
            'stations' => array_map(fn (array $st, int|string $i) => [
                'slug' => $st['slug'],
                'name' => $st['name'],
                'pk_km' => $st['pk_m'] !== null ? round($st['pk_m'] / 1000, 3) : null,
                'is_terminal' => $st['is_terminal'],
                'served_from' => $st['served_from'],
                'served_until' => $st['served_until'],
                'served_until_kind' => $st['served_until_kind'],
                'years_source' => $st['years_source'],
                'service_start' => StationServiceDates::written($servedBounds["{$i}:start"], $lang),
                'service_end' => StationServiceDates::written($servedBounds["{$i}:end"], $lang),
                'status_today' => $st['status_today'],
            ], $stationRows, array_keys($stationRows)),
            'event_count' => $eventCount,
        ];
        if ($withEvents) {
            $out['events'] = $eventRows->take($maxEvents)->map(fn ($e) => $this->events->format($e, $lang))->values()->all();
            if ($eventCount > $maxEvents) {
                $out['events_omitted'] = $eventCount - $maxEvents;
            }
        }

        return ['line' => $out, 'providers' => array_values(array_unique(array_filter($providers)))];
    }

    /**
     * @param  array<string, int>  $byCategory  mètres par catégorie
     * @return array<string, float>|list<array{code: string, label: string, km: float}>
     */
    private static function categoriesKm(array $byCategory, ?string $lang): array
    {
        arsort($byCategory);
        if ($lang === null) {
            return array_map(fn ($m) => round($m / 1000, 1), $byCategory);
        }

        return array_map(fn ($c, $m) => [
            'code' => (string) $c, 'label' => (string) trans('lines.categories.'.$c, [], $lang), 'km' => round($m / 1000, 1),
        ], array_keys($byCategory), $byCategory);
    }
}
