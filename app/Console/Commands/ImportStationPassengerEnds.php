<?php

namespace App\Console\Commands;

use App\Services\StationArticleCache;
use App\Services\StationClosureParser;
use App\Services\WikimediaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fin du service voyageurs des gares, lue dans l'infobox de l'article
 * Wikipédia de LA gare (« Fermeture : 27 septembre 1969 (voyageurs) ») →
 * station_events (type closure_passenger, source wikipedia, is_inferred = false).
 *
 * Pourquoi (audit des gares du 21/11/2026, G4) : 552 gares passent de
 * « voyageurs » à « fret seul » sur la frise sans date ; l'infobox en date
 * une partie. vff:build-station-eras lit ces événements au prochain rejeu :
 * plus de voyageurs à partir de l'ère de la date, et une fermeture Wikidata
 * de la même année sur une gare fret seul devient la fin des voyageurs
 * (StationTimeline::massClosureAsPassenger).
 *
 * Seulement ce qui est explicite : un champ fermeture de l'infobox, une date
 * au jour, au mois, à l'année ou à la décennie, qualifiée « voyageurs ». Ni
 * les phrases de l'article (vff:import-station-closures --source=wikipedia-stations
 * les lit), ni une fin du fret, ni une date sans qualificatif, ni une date que
 * la source donne pour incertaine, ni « avant 1950 ».
 *
 * Rattachement par lien, jamais par nom : article frwiki d'un élément Wikidata
 * de la gare, sinon tag OSM wikipedia=fr:… Gares retenues : fermées, disparues
 * ou fret seul aujourd'hui (une fin des voyageurs sans réouverture connue
 * fermerait une gare en service). Écartés, et comptés :
 *   - article lié à plusieurs gares (homonymes, doublons) : gardé pour la seule
 *     gare dont c'est l'élément Wikidata principal (stations.wikidata_id),
 *     sinon pour aucune ;
 *   - plusieurs fins des voyageurs différentes pour une gare (deux articles ou
 *     une infobox qui en donne deux) : relecture à la main ;
 *   - date antérieure à l'ouverture connue de la gare.
 * Une halte (service voyageurs seul) fermée aux voyageurs est fermée tout
 * court (closure), comme dans vff:import-station-closures.
 *
 * Écrit seulement ses propres lignes (source_ref « gare:<titre>#infobox-voyageurs »),
 * remplacées à chaque exécution. vff:import-station-closures, qui relit aussi
 * l'infobox, remplace la source « wikipedia » des articles de gare en entier :
 * ses lignes « #infobox » couvrent celles-ci (même type, même date). Ni
 * stations, ni station_eras, ni aucune table du moteur ne sont modifiées.
 *
 * Licence : Wikipédia CC BY-SA ; seule la date est reprise, avec le permalien
 * (oldid) de la révision lue et une citation courte du champ (note).
 *
 *   php artisan vff:import-station-passenger-ends --dry-run --csv=storage/app/data/gares-infobox/bilan.csv
 *   php artisan vff:import-station-passenger-ends --from-cache
 */
class ImportStationPassengerEnds extends Command
{
    protected $signature = 'vff:import-station-passenger-ends
        {--from-cache : Rejoue les articles déjà téléchargés, sans requête réseau}
        {--refresh : Re-télécharge les articles en cache}
        {--station= : Une gare (id ou slug)}
        {--csv= : Écrit le bilan (une ligne par date, avec les dates déjà connues et la frise) dans ce fichier}
        {--dry-run : Lit et compare sans rien écrire dans la base}';

    protected $description = 'Fins du service voyageurs des gares lues dans les infobox Wikipédia → station_events (closure_passenger)';

    /** Suffixe des source_ref écrites par cette commande (et seulement elles). */
    public const REF_SUFFIX = '#infobox-voyageurs';

    private const PRECISIONS = ['day', 'month', 'year', 'decade'];

    /** @var array<string, int> */
    private array $stats = [];

    public function __construct(private StationArticleCache $articles, private StationClosureParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $only = null;
        if (($ref = $this->option('station')) !== null) {
            $only = DB::table('stations')->where('slug', $ref)->orWhere('id', ctype_digit((string) $ref) ? (int) $ref : 0)->value('id');
            if (! $only) {
                $this->error("Gare introuvable : {$ref}");

                return self::FAILURE;
            }
        }

        $byTitle = $this->links($only === null ? null : (int) $only);
        $res = $this->articles->pages(array_keys($byTitle), (bool) $this->option('from-cache'), (bool) $this->option('refresh'));
        if ($res['uncached'] > 0) {
            $this->warn("{$res['uncached']} articles absents du cache (--from-cache)");
        }
        $this->line(sprintf('%d articles liés à des gares fermées ou fret seul ; %d téléchargés', count($byTitle), $res['fetched']));

        $found = $this->read($byTitle, $res['pages']);
        [$rows, $held] = $this->filter($found);
        $review = $this->review([...$rows, ...$held]);

        if ($this->option('csv')) {
            $this->writeCsv((string) $this->option('csv'), $review);
        }
        $this->summary($review, count($rows));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run : rien n\'est écrit dans station_events');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows, $only) {
            $deleted = DB::table('station_events')->where('source', 'wikipedia')->where('is_inferred', false)
                ->where('source_ref', 'like', 'gare:%'.self::REF_SUFFIX)
                ->when($only, fn ($q) => $q->where('station_id', $only))->delete();
            $now = now();
            $inserted = 0;
            foreach (array_chunk($rows, 500) as $chunk) {
                $inserted += DB::table('station_events')->insertOrIgnore(array_map(fn ($r) => [
                    'station_id' => $r['station_id'], 'type' => $r['type'], 'event_date' => $r['date'], 'date_precision' => $r['precision'],
                    'source' => 'wikipedia', 'source_ref' => $r['source_ref'], 'source_url' => $r['source_url'], 'note' => $r['note'],
                    'is_inferred' => false, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
            $this->info("{$inserted} fins des voyageurs écrites ({$deleted} anciennes remplacées)");
        });

        return self::SUCCESS;
    }

    /**
     * Articles liés aux gares fermées ou fret seul aujourd'hui : titre → gares.
     * Un article lié à plusieurs gares n'est gardé que pour celle dont c'est
     * l'élément Wikidata principal.
     *
     * @return array<string, list<array{id: int, name: string}>>
     */
    private function links(?int $only): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT s.id, s.name, t.title, bool_or(t.main) AS main
            FROM stations s
            JOIN LATERAL (
                SELECT ss.properties->>'frwiki' AS title, ss.source_ref = s.wikidata_id AS main FROM station_sources ss
                WHERE ss.station_id = s.id AND ss.source = 'wikidata' AND ss.properties->>'frwiki' IS NOT NULL
                UNION ALL
                SELECT SUBSTRING(ss.properties->>'wikipedia' FROM 4), false FROM station_sources ss
                WHERE ss.station_id = s.id AND ss.source = 'osm' AND ss.properties->>'wikipedia' LIKE 'fr:%'
            ) t ON t.title IS NOT NULL AND t.title <> ''
            WHERE COALESCE(s.source_status, s.status) IN ('declassified', 'no_commercial', 'gone')
              AND (CAST(:only AS bigint) IS NULL OR s.id = CAST(:only AS bigint))
            GROUP BY s.id, s.name, t.title
            ORDER BY t.title, s.id
        SQL, ['only' => $only]);
        $byTitle = [];
        foreach ($rows as $r) {
            $byTitle[$r->title][] = ['id' => (int) $r->id, 'name' => (string) $r->name, 'main' => (bool) $r->main];
        }
        foreach ($byTitle as $title => $stations) {
            if (count($stations) > 1) {
                $main = array_values(array_filter($stations, fn ($s) => $s['main']));
                if (count($main) !== 1) {
                    $this->count('écartés : article lié à plusieurs gares');
                    unset($byTitle[$title]);

                    continue;
                }
                $this->count('article lié à plusieurs gares, gardé pour son élément Wikidata principal');
                $stations = $main;
            }
            $byTitle[$title] = array_map(fn ($s) => ['id' => $s['id'], 'name' => $s['name']], $stations);
        }

        return $byTitle;
    }

    /**
     * Fins des voyageurs explicites de chaque infobox, par gare.
     *
     * @param  array<string, list<array{id: int, name: string}>>  $byTitle
     * @param  array<string, array{title: string, revid: int, content: string}>  $pages
     * @return array<int, list<array<string, mixed>>> gare → dates trouvées
     */
    private function read(array $byTitle, array $pages): array
    {
        $found = [];
        foreach ($byTitle as $title => $stations) {
            $page = $pages[$title] ?? null;
            if ($page === null) {
                continue;
            }
            $this->count('articles lus');
            $events = $this->parser->fromStationInfobox($page['content'], $page['title']);
            if ($events === []) {
                continue;
            }
            $this->count('infobox avec une fermeture');
            $halt = StationClosureParser::isHalt($page['content'], $page['title']);
            foreach ($events as $e) {
                if ($e['type'] !== 'closure_passenger') {
                    $this->count('ignorés : fermeture '.match ($e['type']) {
                        'closure_freight' => 'du fret seul', 'closure' => 'sans qualificatif ou à tout trafic', default => $e['type'],
                    });

                    continue;
                }
                if (! in_array($e['precision'], self::PRECISIONS, true)) {
                    $this->count('ignorés : fin des voyageurs « avant » ou « après »');

                    continue;
                }
                if (str_contains((string) ($e['quote'] ?? ''), '(date incertaine selon la source)')) {
                    $this->count('ignorés : date incertaine selon la source');

                    continue;
                }
                $prefix = 'gare:';
                $ref = $prefix.mb_substr($page['title'], 0, 160 - mb_strlen($prefix.self::REF_SUFFIX)).self::REF_SUFFIX;
                foreach ($stations as $s) {
                    $found[$s['id']][] = [
                        'station_id' => $s['id'], 'name' => $s['name'], 'article' => $page['title'], 'revid' => $page['revid'],
                        'type' => $halt ? 'closure' : 'closure_passenger', 'halt' => $halt,
                        'date' => $e['date'], 'precision' => $e['precision'],
                        'source_ref' => $ref, 'source_url' => mb_substr(WikimediaClient::permalink($page['title'], $page['revid']), 0, 500),
                        'note' => '« '.mb_strimwidth((string) $e['quote'], 0, 300, '…').' »',
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * Une seule fin des voyageurs par gare, postérieure à son ouverture connue.
     * Les autres (« held ») ne sont pas écrites ; elles vont au bilan, avec la raison.
     *
     * @param  array<int, list<array<string, mixed>>>  $found
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} dates gardées, dates écartées
     */
    private function filter(array $found): array
    {
        if ($found === []) {
            return [[], []];
        }
        $ids = array_keys($found);
        $opened = [];
        foreach (DB::table('station_events')->where('type', 'opening')->whereIn('station_id', $ids)->groupBy('station_id')
            ->selectRaw('station_id, MIN(EXTRACT(YEAR FROM event_date))::int AS y')->get() as $o) {
            $opened[(int) $o->station_id] = (int) $o->y;
        }
        foreach (DB::table('stations')->whereIn('id', $ids)->whereNotNull('opened_at')->selectRaw('id, EXTRACT(YEAR FROM opened_at)::int AS y')->get() as $o) {
            $opened[(int) $o->id] = min($opened[(int) $o->id] ?? PHP_INT_MAX, (int) $o->y);
        }
        $rows = [];
        $held = [];
        foreach ($found as $sid => $dates) {
            $distinct = array_unique(array_map(fn ($d) => $d['date'].'|'.$d['precision'], $dates));
            if (count($distinct) > 1) {
                $this->count('écartés : plusieurs fins des voyageurs pour une gare (relecture)');
                foreach ($dates as $d) {
                    $held[] = $d + ['held' => 'écartée : plusieurs fins des voyageurs pour la gare'];
                }

                continue;
            }
            $d = $dates[0];
            $year = (int) substr($d['date'], 0, 4) + ($d['precision'] === 'decade' ? 9 : 0);
            if (isset($opened[$sid]) && $year < $opened[$sid]) {
                $this->count('écartés : fin des voyageurs avant l\'ouverture');
                $held[] = $d + ['held' => "écartée : avant l'ouverture connue ({$opened[$sid]})"];

                continue;
            }
            $rows[] = $d;
        }

        return [$rows, $held];
    }

    /**
     * Dates déjà connues de la gare et frise actuelle, pour la relecture.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function review(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_column($rows, 'station_id');
        $known = [];
        foreach (DB::table('station_events')->whereIn('station_id', $ids)->where('is_inferred', false)
            ->whereIn('type', ['closure', 'closure_passenger', 'demolition', 'reopening'])
            ->where(fn ($q) => $q->where('source_ref', 'not like', 'gare:%'.self::REF_SUFFIX)->orWhereNull('source_ref'))
            ->orderBy('event_date')->get(['station_id', 'type', 'event_date', 'date_precision', 'source']) as $e) {
            $known[(int) $e->station_id][] = $e;
        }
        $eras = [];
        foreach (DB::table('station_eras as se')->join('eras as e', 'e.id', '=', 'se.era_id')->whereIn('se.station_id', $ids)
            ->orderBy('e.year')->get(['se.station_id', 'e.year', 'se.status', 'se.uncertain']) as $e) {
            $eras[(int) $e->station_id][] = $e;
        }
        $status = DB::table('stations')->whereIn('id', $ids)->selectRaw('id, COALESCE(source_status, status) AS st')->pluck('st', 'id');

        $out = [];
        foreach ($rows as $r) {
            $sid = $r['station_id'];
            $year = (int) substr($r['date'], 0, 4);
            $verdict = $r['held'] ?? $this->verdict($r, $known[$sid] ?? [], (string) ($status[$sid] ?? ''));
            $passengerAfter = array_values(array_map(fn ($e) => (int) $e->year,
                array_filter($eras[$sid] ?? [], fn ($e) => (int) $e->year >= $year && in_array($e->status, ['active', 'tourist'], true))));
            $uncertainBefore = array_values(array_map(fn ($e) => (int) $e->year,
                array_filter($eras[$sid] ?? [], fn ($e) => (int) $e->year < $year && $e->uncertain)));
            $lastPassenger = array_values(array_filter($eras[$sid] ?? [], fn ($e) => in_array($e->status, ['active', 'tourist'], true)));
            $out[] = $r + [
                'status' => (string) ($status[$sid] ?? ''),
                'known' => implode(' ; ', array_map(fn ($e) => "{$e->type} {$e->event_date} {$e->date_precision} ({$e->source})", $known[$sid] ?? [])),
                'verdict' => $verdict,
                'eras' => implode(' ', array_map(fn ($e) => $e->year.':'.$e->status.($e->uncertain ? '?' : ''), $eras[$sid] ?? [])),
                'last_passenger_era' => $lastPassenger === [] ? null : (int) end($lastPassenger)->year,
                'passenger_eras_after' => $passengerAfter,
                'uncertain_eras_before' => $uncertainBefore,
            ];
        }

        return $out;
    }

    /**
     * Comparaison avec les fins de service déjà sourcées de la gare.
     *
     * @param  array<string, mixed>  $r
     * @param  list<object>  $known
     * @param  string  $status  statut d'aujourd'hui (COALESCE(source_status, status))
     */
    private function verdict(array $r, array $known, string $status): string
    {
        $year = (int) substr((string) $r['date'], 0, 4);
        $passenger = array_values(array_filter($known, fn ($e) => $e->type === 'closure_passenger'));
        $total = array_values(array_filter($known, fn ($e) => in_array($e->type, ['closure', 'demolition'], true) && $e->date_precision !== 'after'));
        foreach ($passenger as $e) {
            if ($e->event_date === $r['date']) {
                return 'déjà connue';
            }
        }
        if ($passenger !== []) {
            return abs((int) substr($passenger[0]->event_date, 0, 4) - $year) <= 1 ? 'même année qu\'une autre source' : 'conflit : autre fin des voyageurs';
        }
        $reopened = array_filter($known, fn ($e) => $e->type === 'reopening' && (int) substr($e->event_date, 0, 4) >= $year) !== [];
        foreach ($total as $e) {
            $y = (int) substr($e->event_date, 0, 4);
            if (abs($y - $year) <= 1) {
                // StationTimeline::massClosureAsPassenger : seulement sur une gare fret seul aujourd'hui.
                return $status === 'no_commercial' && in_array($e->source, ['wikidata', 'osm', 'osm:note'], true)
                    ? 'fermeture de la même année, retypée en fin des voyageurs (gare fret seul)'
                    : 'fermeture totale de la même année, gardée';
            }
            if ($y < $year && ! $reopened) {
                return 'conflit : fermeture totale plus ancienne';
            }
        }

        return $total === [] ? 'nouvelle date' : 'nouvelle date, fermeture totale plus tardive';
    }

    /** @param list<array<string, mixed>> $review */
    private function summary(array $review, int $kept): void
    {
        foreach ($review as $r) {
            if (isset($r['held'])) {
                continue;
            }
            $this->count('verdict : '.$r['verdict']);
            if ($r['passenger_eras_after'] !== []) {
                $this->count('frise : gares encore « voyageurs » à l\'ère de la date ou après');
            }
            if ($r['uncertain_eras_before'] !== []) {
                $this->count('frise : gares avec une ère incertaine avant la date');
            }
            if ($r['halt']) {
                $this->count('haltes (écrites en fermeture)');
            }
        }
        ksort($this->stats);
        $this->table(['compteur', 'n'], array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), $this->stats));
        if ($this->output->isVerbose()) {
            foreach ($review as $r) {
                $this->line(sprintf('  %-32s %-17s %s %-6s %s', mb_strimwidth($r['name'], 0, 32), $r['type'], $r['date'], $r['precision'], $r['verdict']));
            }
        }
        $this->info(sprintf('%d fins des voyageurs à écrire (une par gare) ; %d dates écartées au bilan', $kept, count($review) - $kept));
    }

    /** @param list<array<string, mixed>> $review */
    private function writeCsv(string $path, array $review): void
    {
        $path = str_starts_with($path, '/') ? $path : base_path($path);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $fh = fopen($path, 'w');
        if ($fh === false) {
            $this->error("Impossible d'écrire {$path}");

            return;
        }
        $cols = ['station_id', 'name', 'status', 'type', 'date', 'precision', 'verdict', 'known', 'last_passenger_era', 'passenger_eras_after', 'uncertain_eras_before', 'eras', 'article', 'revid', 'source_url', 'note'];
        fputcsv($fh, $cols, escape: '');
        foreach ($review as $r) {
            $r['passenger_eras_after'] = implode(' ', $r['passenger_eras_after'] ?? []);
            $r['uncertain_eras_before'] = implode(' ', $r['uncertain_eras_before'] ?? []);
            fputcsv($fh, array_map(fn ($c) => $r[$c] ?? '', $cols), escape: '');
        }
        fclose($fh);
        $this->line("Bilan : {$path}");
    }

    private function count(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }
}
