<?php

namespace App\Console\Commands;

use App\Services\StationArticleCache;
use App\Services\StationClosureParser;
use App\Services\WikimediaClient;
use App\Support\StationNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dates de fermeture (et réouvertures) des gares, lues dans des sources qui
 * parlent de LA gare → station_events (is_inferred = false).
 *
 * Pourquoi : une halte fermée sur une ligne toujours exploitée n'a aucune
 * fermeture déduite de sa ligne ; sans date propre, la chronologie la
 * laissait ouverte jusqu'à l'ère précédente (1 128 gares « ouvertes » en 2020
 * puis fermées aujourd'hui, mesure du 2026-10-19).
 *
 * Sources, par ordre de confiance :
 *   1. wikidata:qualifiers : déclarations Wikidata que vff:import-station-sources
 *      ne lit pas (P582 « date de fin », P31 « nature » avec qualificatif
 *      P582, P5817 « état d'utilisation » fermé avec P580, P793 « événement
 *      clé » fermeture avec P585, P3999 / P576 à la décennie près) - cache
 *      storage/app/sources/stations/wikidata, aucun appel réseau ;
 *   2. wikipedia (article de la GARE) : infobox « fermeture » et phrases dont
 *      la gare est le sujet (« Elle est fermée le 6 mars 1972 ») ;
 *   3. wikipedia (article de la LIGNE) : phrases, listes, tableaux et
 *      commentaires du schéma de ligne qui nomment des gares fermées
 *      (« En 1960, les haltes de Tourgéville et de Gonneville - Saint-Vaast
 *      […] sont fermées »). Le nom est rapproché (StationNames::normalize)
 *      des gares rattachées à la ligne (station_lines) ;
 *   4. osm:note : description / note OSM (« Gare non desservie depuis 2016 »).
 * Règles d'extraction : App\Services\StationClosureParser.
 *
 * Fermeture imprécise : « années 1980 » → précision « decade » ; « fermée
 * dans la deuxième moitié du XXe siècle » → précision « after » (date
 * minimale : la gare était encore ouverte à cette date, fermeture inconnue).
 *
 * Licence : Wikipédia CC BY-SA → source_url = permalien (oldid) de la
 * révision lue, note = courte citation (≤ 300 caractères).
 *
 * Idempotente : les événements des sources ci-dessus sont remplacés à
 * chaque exécution complète (vff:consolidate-stations ne les efface pas).
 * Articles mis en cache dans storage/app/sources/stations/wikipedia
 * (--from-cache rejoue sans réseau, --refresh re-télécharge).
 *
 * À lancer après vff:attach-stations (rattachement gare ↔ ligne), avant
 * vff:build-station-eras (étape de vff:stations-pipeline) :
 *
 *   php artisan vff:import-station-closures
 *   php artisan vff:import-station-closures --from-cache --dry-run --station=gare-de-lugny
 */
class ImportStationClosures extends Command
{
    protected $signature = 'vff:import-station-closures
        {--source=* : wikidata | wikipedia-stations | wikipedia-lines | osm (défaut : toutes)}
        {--from-cache : Rejoue les articles déjà téléchargés, sans requête réseau}
        {--refresh : Re-télécharge les articles en cache}
        {--station= : Une gare (id ou slug), pour vérifier}
        {--dry-run : Affiche les événements sans écrire}';

    protected $description = 'Dates de fermeture des gares (Wikidata, articles Wikipédia des gares et des lignes, OSM) → station_events';

    /** Sources écrites par cette commande (préservées par vff:consolidate-stations). */
    public const SOURCES = ['wikipedia', 'wikidata:qualifiers', 'osm:note'];

    private const ALL = ['wikidata', 'wikipedia-stations', 'wikipedia-lines', 'osm'];

    /** @var array<string, int> */
    private array $stats = [];

    /** @var list<array<string, mixed>> */
    private array $unmatched = [];

    public function __construct(private StationArticleCache $articles, private StationClosureParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ini_set('memory_limit', '1G');
        $sources = (array) $this->option('source') ?: self::ALL;
        if ($bad = array_diff($sources, self::ALL)) {
            $this->error('Source inconnue : '.implode(', ', $bad));

            return self::FAILURE;
        }
        $only = null;
        if ($ref = $this->option('station')) {
            $only = DB::table('stations')->where('slug', $ref)->orWhere('id', ctype_digit((string) $ref) ? (int) $ref : 0)->value('id');
            if (! $only) {
                $this->error("Gare introuvable : {$ref}");

                return self::FAILURE;
            }
        }

        $events = [];
        foreach ($sources as $source) {
            $this->info("── {$source}");
            $found = match ($source) {
                'wikidata' => $this->fromWikidata($only),
                'wikipedia-stations' => $this->fromStationArticles($only),
                'wikipedia-lines' => $this->fromLineArticles($only),
                'osm' => $this->fromOsm($only),
            };
            $this->line(sprintf('  %d événements', count($found)));
            array_push($events, ...$found);
        }

        // Gares fermées aujourd'hui (ou réduites au fret) seulement : une fermeture sans
        // réouverture connue fermerait à tort une gare en service (élément Wikidata
        // secondaire d'une autre gare rapproché par erreur, homonyme…). Gare fret seul
        // aujourd'hui : « fermée » veut dire fermée aux voyageurs.
        $closed = $this->closedToday();
        // Ouverture connue de la gare (source ou déduite) : une fermeture antérieure est une
        // erreur de la source (« Fermeture : 8 février 1848 » pour des haltes ouvertes en 1886).
        $opened = DB::table('station_events')->where('type', 'opening')->groupBy('station_id')
            ->selectRaw('station_id, MIN(EXTRACT(YEAR FROM event_date))::int AS y')->pluck('y', 'station_id')->all();
        foreach (DB::table('stations')->whereNotNull('opened_at')->selectRaw('id, EXTRACT(YEAR FROM opened_at)::int AS y')->get() as $o) {
            $opened[$o->id] = min($opened[$o->id] ?? PHP_INT_MAX, (int) $o->y);
        }
        $kept = [];
        foreach ($events as $e) {
            $state = $closed[$e['station_id']] ?? null;
            if ($state === null) {
                $this->stats['écartés : gare en service aujourd\'hui'] = ($this->stats['écartés : gare en service aujourd\'hui'] ?? 0) + 1;

                continue;
            }
            $year = (int) substr($e['event_date'], 0, 4) + ($e['date_precision'] === 'decade' ? 9 : 0);
            if (isset($opened[$e['station_id']]) && $year < $opened[$e['station_id']] && $e['date_precision'] !== 'after') {
                $this->stats['écartés : fermeture avant l\'ouverture'] = ($this->stats['écartés : fermeture avant l\'ouverture'] ?? 0) + 1;

                continue;
            }
            if ($state === 'no_commercial' && in_array($e['type'], ['closure', 'demolition'], true)) {
                $e['type'] = 'closure_passenger';
            }
            // Fin du fret datée d'une gare « fret seul » selon liste-des-gares : gardée. Le drapeau fret SNCF
            // est souvent périmé (Fontenay-le-Comte, désaffectée du trafic marchandises en 2007 ; Bry-sur-Marne,
            // Leffrinckoucke…) ; StationTimeline::currentStatus applique la fermeture (plus exploitée = fermée).
            $kept[] = $e;
        }
        $events = $kept;

        // Une même fermeture lue deux fois (même type, même date, même source) : une seule.
        $rows = [];
        foreach ($events as $e) {
            $rows[$e['station_id'].'|'.$e['type'].'|'.$e['event_date'].'|'.$e['source']] ??= $e;
        }
        $rows = array_values($rows);
        foreach ($rows as $r) {
            $k = $r['source'].' · '.$r['type'].($r['date_precision'] === 'after' || $r['date_precision'] === 'decade' || $r['date_precision'] === 'before' ? " ({$r['date_precision']})" : '');
            $this->stats[$k] = ($this->stats[$k] ?? 0) + 1;
        }

        if ($this->option('dry-run')) {
            $names = DB::table('stations')->whereIn('id', array_unique(array_column($rows, 'station_id')) ?: [0])->pluck('name', 'id');
            foreach ($rows as $r) {
                $this->line(sprintf('  %-32s %-17s %s %-6s %-20s %s', mb_strimwidth((string) ($names[$r['station_id']] ?? $r['station_id']), 0, 32),
                    $r['type'], $r['event_date'], $r['date_precision'], $r['source'], mb_strimwidth((string) $r['note'], 0, 140)));
            }
            $this->report($rows);

            return self::SUCCESS;
        }

        $sourceNames = array_map(fn ($s) => match ($s) {
            'wikidata' => 'wikidata:qualifiers', 'osm' => 'osm:note', default => 'wikipedia',
        }, $sources);
        DB::transaction(function () use ($rows, $sourceNames, $sources, $only) {
            $del = DB::table('station_events')->where('is_inferred', false)
                ->when($only, fn ($q) => $q->where('station_id', $only));
            // Les deux familles Wikipédia partagent la source « wikipedia » : on ne
            // supprime que celle qui a été relue (source_ref « gare: » / « ligne: »).
            if (in_array('wikipedia', $sourceNames, true) && ! (in_array('wikipedia-stations', $sources, true) && in_array('wikipedia-lines', $sources, true))) {
                $prefix = in_array('wikipedia-stations', $sources, true) ? 'gare:%' : 'ligne:%';
                $del->where(fn ($w) => $w->whereIn('source', array_diff($sourceNames, ['wikipedia']))
                    ->orWhere(fn ($x) => $x->where('source', 'wikipedia')->where('source_ref', 'like', $prefix)));
            } else {
                $del->whereIn('source', array_unique($sourceNames));
            }
            $deleted = $del->delete();
            $now = now();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('station_events')->insertOrIgnore(array_map(fn ($r) => $r + [
                    'is_inferred' => false, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
            $this->line("  {$deleted} anciens événements remplacés");

            // Fiche de la gare : date de fermeture retenue (fermeture totale datée, à défaut
            // fermeture aux voyageurs) si elle n'en a pas, ou seulement une déduite des lignes.
            $sources = "'".implode("', '", self::SOURCES)."'";
            // Date posée par une exécution précédente : recalculée (la source a pu être écartée depuis).
            DB::table('stations')->whereRaw("provenance->>'closed_at' IN ({$sources})")
                ->when($only, fn ($q) => $q->where('id', $only))
                ->update(['closed_at' => null, 'closed_precision' => null, 'provenance' => DB::raw("provenance - 'closed_at'")]);
            $updated = DB::affectingStatement(<<<SQL
                UPDATE stations s SET closed_at = e.event_date, closed_precision = e.date_precision,
                       provenance = COALESCE(s.provenance, '{}'::jsonb) || jsonb_build_object('closed_at', e.source)
                FROM (SELECT DISTINCT ON (station_id) station_id, event_date, date_precision, source
                      FROM station_events
                      WHERE source IN ({$sources}) AND date_precision IN ('day', 'month', 'year')
                        AND type IN ('closure', 'demolition', 'closure_passenger')
                      ORDER BY station_id, (type = 'closure_passenger'), event_date DESC) e
                WHERE e.station_id = s.id AND COALESCE(s.source_status, s.status) IN ('declassified', 'gone')
                  AND (s.closed_at IS NULL OR s.provenance->>'closed_at' LIKE 'inferred:%')
            SQL);
            $this->line("  {$updated} dates de fermeture de fiche renseignées");
        });
        $this->report($rows);

        return self::SUCCESS;
    }

    // ── Wikidata (cache de vff:import-station-sources) ─────────────────────

    /** @return list<array<string, mixed>> */
    private function fromWikidata(?int $only): array
    {
        $members = DB::table('station_sources as ss')->join('stations as s', 's.id', '=', 'ss.station_id')
            ->where('ss.source', 'wikidata')
            ->whereRaw('COALESCE(s.source_status, s.status) IN (\'declassified\', \'no_commercial\', \'gone\')')
            ->when($only, fn ($q) => $q->where('ss.station_id', $only))
            ->get(['ss.station_id', 'ss.source_ref', 's.wikidata_id'])->groupBy('source_ref');
        if ($members->isEmpty()) {
            return [];
        }
        $out = [];
        foreach (glob(storage_path('app/sources/stations/wikidata/entities-*.json')) ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            foreach ($data['entities'] ?? [] as $qid => $ent) {
                if (! isset($members[$qid])) {
                    continue;
                }
                foreach ($members[$qid] as $m) {
                    // Élément secondaire de la gare : vff:consolidate-stations ne garde pas ses
                    // P3999 / P576, on les relit ici.
                    foreach (StationClosureParser::fromWikidata($ent, $m->wikidata_id !== $qid) as $e) {
                        $out[] = $this->row((int) $m->station_id, $e, 'wikidata:qualifiers', $qid.'#'.$e['prop'],
                            'https://www.wikidata.org/wiki/'.$qid);
                    }
                }
            }
        }

        return $out;
    }

    // ── Articles Wikipédia des gares ────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function fromStationArticles(?int $only): array
    {
        // Gares fermées ou réduites au fret aujourd'hui (ou disparues), avec un
        // article frwiki (lien de site Wikidata, sinon tag OSM wikipedia=fr:…).
        $rows = DB::select(<<<'SQL'
            SELECT DISTINCT ON (s.id, t.title) s.id, s.name, t.title
            FROM stations s
            JOIN LATERAL (
                SELECT ss.properties->>'frwiki' AS title FROM station_sources ss
                WHERE ss.station_id = s.id AND ss.source = 'wikidata' AND ss.properties->>'frwiki' IS NOT NULL
                UNION
                SELECT SUBSTRING(ss.properties->>'wikipedia' FROM 4) FROM station_sources ss
                WHERE ss.station_id = s.id AND ss.source = 'osm' AND ss.properties->>'wikipedia' LIKE 'fr:%'
            ) t ON t.title IS NOT NULL
            WHERE COALESCE(s.source_status, s.status) IN ('declassified', 'no_commercial', 'gone')
              AND (CAST(:only AS bigint) IS NULL OR s.id = CAST(:only AS bigint))
        SQL, ['only' => $only]);
        $pages = $this->pages(array_values(array_unique(array_column($rows, 'title'))));
        $out = [];
        foreach ($rows as $r) {
            $page = $pages[$r->title] ?? null;
            if (! $page) {
                continue;
            }
            $this->stats['articles de gare lus'] = ($this->stats['articles de gare lus'] ?? 0) + 1;
            $url = WikimediaClient::permalink($page['title'], $page['revid']);
            foreach ($this->parser->fromStationArticle($page['content'], $page['title']) as $e) {
                $out[] = $this->row((int) $r->id, $e, 'wikipedia', 'gare:'.$page['title'].'#'.$e['origin'], $url);
            }
        }

        return $out;
    }

    // ── Articles Wikipédia des lignes ───────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function fromLineArticles(?int $only): array
    {
        // Gares candidates : rattachées à une ligne qui a un article.
        // Toutes les gares de la ligne servent au rapprochement des noms (une gare
        // ouverte homonyme bloque un rapprochement par préfixe) ; seules celles
        // fermées aujourd'hui reçoivent un événement (cf. row()).
        $cands = DB::select(<<<'SQL'
            SELECT l.wikipedia_title AS title, s.id, s.name, s.alt_names, s.type
            FROM station_lines sl
            JOIN lines l ON l.id = sl.line_id AND l.wikipedia_title IS NOT NULL
            JOIN stations s ON s.id = sl.station_id
            WHERE CAST(:only AS bigint) IS NULL OR s.id = CAST(:only AS bigint)
        SQL, ['only' => $only]);
        $byTitle = [];
        foreach ($cands as $c) {
            $names = [$c->name];
            foreach (json_decode((string) $c->alt_names, true) ?: [] as $a) {
                if (! empty($a['name'])) {
                    $names[] = $a['name'];
                }
            }
            foreach ($names as $n) {
                if ($k = StationNames::normalize($n)) {
                    $byTitle[$c->title][$k][(int) $c->id] = true;
                }
            }
        }
        $pages = $this->pages(array_keys($byTitle));
        $out = [];
        foreach ($byTitle as $title => $index) {
            $page = $pages[$title] ?? null;
            if (! $page) {
                continue;
            }
            $this->stats['articles de ligne lus'] = ($this->stats['articles de ligne lus'] ?? 0) + 1;
            $url = WikimediaClient::permalink($page['title'], $page['revid']);
            foreach ($this->parser->fromLineArticle($page['content'], $page['title']) as $e) {
                foreach ($e['names'] as $name) {
                    $ids = $this->match($name, $index);
                    if ($ids === []) {
                        $this->unmatched[] = ['article' => $page['title'], 'name' => $name, 'type' => $e['type'], 'date' => $e['date']];

                        continue;
                    }
                    foreach ($ids as $sid) {
                        $out[] = $this->row($sid, $e, 'wikipedia', 'ligne:'.$page['title'].'#'.$e['origin'], $url);
                    }
                }
            }
        }
        $this->stats['noms de gare non rapprochés (articles de ligne)'] = count($this->unmatched);

        return $out;
    }

    /**
     * Gares de la ligne dont le nom normalisé est celui de la source, sinon
     * l'unique gare dont le nom commence (mots entiers) par ce nom
     * (« Gonneville » → « Gonneville-Saint-Vaast »). Jamais l'inverse : « Caen
     * - Dozulé » (une portion) n'est pas la gare de Caen.
     *
     * @param  array<string, array<int, true>>  $index  nom normalisé → ids
     * @return list<int>
     */
    private function match(string $name, array $index): array
    {
        $k = StationNames::normalize($name);
        if ($k === null) {
            return [];
        }
        if (isset($index[$k])) {
            return array_keys($index[$k]);
        }
        $hits = [];
        foreach ($index as $n => $ids) {
            if (str_starts_with($n.' ', $k.' ')) {
                $hits += $ids;
            }
        }

        return count($hits) === 1 ? array_keys($hits) : [];
    }

    // ── OSM (description / note) ────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function fromOsm(?int $only): array
    {
        $rows = DB::table('station_sources')->whereIn('source', ['osm', 'ohm'])->whereNotNull('station_id')
            ->whereIn('station_id', array_keys($this->closedToday()))
            ->when($only, fn ($q) => $q->where('station_id', $only))
            ->where(fn ($w) => $w->whereRaw("jsonb_exists(properties, 'description')")->orWhereRaw("jsonb_exists(properties, 'note')"))
            ->get(['station_id', 'source', 'source_ref', 'properties']);
        $out = [];
        foreach ($rows as $r) {
            $tags = json_decode((string) $r->properties, true) ?: [];
            foreach (['description', 'note'] as $tag) {
                foreach (StationClosureParser::fromFreeText((string) ($tags[$tag] ?? '')) as $e) {
                    $type = ['n' => 'node', 'w' => 'way', 'r' => 'relation'][$r->source_ref[0]] ?? 'node';
                    $host = $r->source === 'ohm' ? 'www.openhistoricalmap.org' : 'www.openstreetmap.org';
                    $out[] = $this->row((int) $r->station_id, $e + ['origin' => $tag], 'osm:note', $r->source.':'.$r->source_ref.'#'.$tag,
                        "https://{$host}/{$type}/".substr($r->source_ref, 1));
                }
            }
        }

        return $out;
    }

    // ── Outils ──────────────────────────────────────────────────────────────

    /** @var array<int, string>|null */
    private ?array $closedToday = null;

    /**
     * Gares fermées, disparues ou réduites au fret aujourd'hui selon leurs
     * sources : id → statut source.
     *
     * @return array<int, string>
     */
    private function closedToday(): array
    {
        return $this->closedToday ??= DB::table('stations')
            ->selectRaw('id, COALESCE(source_status, status) AS st')
            ->whereRaw("COALESCE(source_status, status) IN ('declassified', 'no_commercial', 'gone')")
            ->pluck('st', 'id')->map(fn ($v) => (string) $v)->all();
    }

    /**
     * @param  array{type: string, date: string, precision: string, quote?: string, origin?: string}  $e
     * @return array<string, mixed>
     */
    private function row(int $stationId, array $e, string $source, string $ref, ?string $url): array
    {
        return [
            'station_id' => $stationId,
            'type' => $e['type'],
            'event_date' => $e['date'],
            'date_precision' => $e['precision'],
            'source' => $source,
            'source_ref' => mb_substr($ref, 0, 160),
            'source_url' => $url ? mb_substr($url, 0, 500) : null,
            'note' => isset($e['quote']) && $e['quote'] !== '' ? '« '.mb_strimwidth($e['quote'], 0, 300, '…').' »' : null,
        ];
    }

    /**
     * Wikitexte des articles (cache disque, 50 titres par requête via WikimediaClient).
     *
     * @param  list<string>  $titles
     * @return array<string, array{title: string, revid: int, content: string}>
     */
    private function pages(array $titles): array
    {
        $res = $this->articles->pages($titles, (bool) $this->option('from-cache'), (bool) $this->option('refresh'));
        if ($res['uncached'] > 0) {
            $this->warn("  {$res['uncached']} articles absents du cache (--from-cache)");
        }
        if ($res['fetched'] > 0) {
            $this->line("  {$res['fetched']} articles téléchargés");
        }

        return $res['pages'];
    }

    /** @param list<array<string, mixed>> $rows */
    private function report(array $rows): void
    {
        ksort($this->stats);
        $this->table(['compteur', 'n'], array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), $this->stats));
        $this->info(sprintf('%d événements pour %d gares', count($rows), count(array_unique(array_column($rows, 'station_id')))));
        if ($this->output->isVerbose()) {
            foreach (array_slice($this->unmatched, 0, 200) as $u) {
                $this->line("  non rapprochée : {$u['name']} ({$u['type']} {$u['date']}) · {$u['article']}");
            }
        }
    }
}
