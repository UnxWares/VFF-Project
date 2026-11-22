<?php

namespace App\Services\Stories;

use App\Http\Resources\LineEventResource;
use App\Http\Resources\MediaResource;
use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Models\LineEvent;
use App\Models\Media;
use App\Services\Archives\LineArchives;
use App\Services\EraTimeline;
use App\Services\LineChronicle;
use App\Support\EventText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * « Histoire » d'un événement de ligne (ROADMAP : stories d'archives) : l'événement tel que la base le
 * rapporte (texte, citation, source), les images publiées de la ligne datées à quelques années près, et
 * les documents de Gallica de la même période. Rien n'est rédigé ici : tout vient de `line_events`,
 * `media` et `line_archive_documents`.
 *
 * Règles (mesurées sur la base de développement le 20/11/2026, voir le rapport de l'agent) :
 *   - événements concernés : ceux qui changent la ligne sur le terrain (EVENT_TYPES) ; les actes
 *     administratifs (concession, fin de concession) et `other` n'ont pas d'histoire ;
 *   - image retenue : média de la ligne dont TOUTES les années possibles sont à WINDOW_YEARS ans au plus de
 *     l'année de l'événement (imageInterval() : l'année exacte, la décennie, la fourchette, ou l'année
 *     « vers » à CIRCA_MARGIN ans près ; une borne seule « avant 1900 » ou « après 1900 » n'est jamais
 *     retenue), aux droits connus (RIGHTS) ; plus les images rattachées à l'événement lui-même, quelle que
 *     soit leur date ;
 *   - document retenu : notice de Gallica rattachée à la ligne dont la date (ou l'intervalle) touche la
 *     même fenêtre, datée à l'année près au moins (DOCUMENT_PRECISIONS) ;
 *   - seuil : au moins MIN_ITEMS images et documents, dont MIN_IMAGES images. En dessous, pas d'histoire
 *     (pas de page vide) ;
 *   - carrousel : les MAX_IMAGES images les plus proches de la date, remises dans l'ordre chronologique ;
 *     une même image (même fichier du fournisseur) n'y figure qu'une fois, de préférence rattachée à
 *     l'événement ;
 *   - un même fait rapporté par plusieurs sources (événements de même type, même date et même précision,
 *     même portion : toute la ligne, ou les deux mêmes lieux nommés) n'a qu'une histoire (facts()). Elle cite
 *     toutes ses sources ; son adresse est celle de l'événement de la source la plus sûre (canonical()), les
 *     adresses des autres événements y renvoient.
 *
 * Chaque image et chaque document porte son écart signé en années (`gap_years` : 4 = quatre ans après,
 * -2 = deux ans avant, null = image de l'événement sans date). Une image datée moins précisément que l'année
 * porte aussi l'intervalle de ses écarts possibles (`gap_years_min`, `gap_years_max`) et `gap_exact` = false :
 * l'écart se dit alors « environ cinq ans avant » ou « de trois ans avant à deux ans après ».
 *
 * L'API (REST, GraphQL, serveur MCP) ne diffuse que les images du domaine public ou sous licence ouverte
 * (API_RIGHTS) : une image montrée sur le site avec une simple autorisation de son auteur (`permission`)
 * n'est pas redistribuée.
 */
final readonly class EventStories
{
    /** Écart maximal, en années, entre l'événement et une image ou un document. */
    public const WINDOW_YEARS = 5;

    /** Contenu minimal d'une histoire (images et documents). */
    public const MIN_ITEMS = 3;

    /** Images minimales d'une histoire : un carrousel d'une seule image n'en est pas un. */
    public const MIN_IMAGES = 2;

    /** Images au plus dans le carrousel (les plus proches de la date). */
    public const MAX_IMAGES = 24;

    /** Événements qui peuvent avoir une histoire (changements sur le terrain). */
    public const EVENT_TYPES = [
        'opening', 'opening_passenger', 'opening_freight',
        'reopening', 'reopening_passenger', 'reopening_freight',
        'closure', 'closure_passenger', 'closure_freight', 'declassification',
        'dismantling', 'conversion', 'electrification', 'modernization',
        'heritage_start', 'heritage_end', 'accident',
    ];

    /**
     * Précisions de date d'une image dont l'intervalle possible est borné (MediaResource::date_precision) ;
     * `before`, `after` et `category` ne le sont pas. Une image sans précision est datée à l'année.
     */
    public const MEDIA_PRECISIONS = ['day', 'month', 'year', 'circa', 'decade', 'range'];

    /** Incertitude d'une année « vers » (`circa`), en années de part et d'autre. */
    public const CIRCA_MARGIN = 2;

    /** Précisions de date d'un document de Gallica retenues (LineArchiveDocument::date_precision). */
    public const DOCUMENT_PRECISIONS = ['day', 'month', 'year', 'circa', 'range'];

    /** Droits d'une image montrée dans une histoire : jamais une image aux droits inconnus. */
    public const RIGHTS = [Media::RIGHTS_PUBLIC_DOMAIN, Media::RIGHTS_OPEN_LICENSE, Media::RIGHTS_PERMISSION];

    /** Droits d'une image diffusée par l'API : jamais une image montrée avec une simple autorisation. */
    public const API_RIGHTS = [Media::RIGHTS_PUBLIC_DOMAIN, Media::RIGHTS_OPEN_LICENSE];

    /** Rang de confiance d'un événement (plus petit = plus sûr), comme LinePhases. */
    private const CONFIDENCE_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    /** Rang de précision d'une date (plus petit = plus précis). */
    private const PRECISION_RANK = ['day' => 0, 'month' => 1, 'year' => 2];

    /** Colonnes d'un événement lues pour regrouper les faits (facts()). */
    private const FACT_COLUMNS = 'id, type, event_date, date_precision, source, source_url, confidence, section_from, section_to, section_resolution, section_pk_start_m';

    /**
     * Intervalle possible de l'année représentée d'une image (SQL, table `media`) : colonnes `lo` et `hi`,
     * nulles quand il n'est pas borné. Même règle que imageInterval().
     */
    private const SQL_INTERVAL = <<<'SQL'
        CASE COALESCE(provider_meta->>'date_precision', 'year')
            WHEN 'circa' THEN depicted_year - %1$d
            WHEN 'decade' THEN depicted_year - depicted_year %% 10
            WHEN 'range' THEN depicted_year
            WHEN 'day' THEN depicted_year WHEN 'month' THEN depicted_year WHEN 'year' THEN depicted_year
        END AS lo,
        CASE COALESCE(provider_meta->>'date_precision', 'year')
            WHEN 'circa' THEN depicted_year + %1$d
            WHEN 'decade' THEN depicted_year - depicted_year %% 10 + 9
            WHEN 'range' THEN CASE WHEN provider_meta->>'year_to' ~ '^[0-9]{3,4}$' THEN (provider_meta->>'year_to')::int END
            WHEN 'day' THEN depicted_year WHEN 'month' THEN depicted_year
            WHEN 'year' THEN GREATEST(depicted_year, CASE WHEN provider_meta->>'year_to' ~ '^[0-9]{3,4}$' THEN (provider_meta->>'year_to')::int ELSE depicted_year END)
        END AS hi
        SQL;

    public function __construct(private LineArchives $archives) {}

    /**
     * Histoires de la ligne, en une requête : identifiant de l'événement qui donne son adresse à l'histoire →
     * nombre d'images et de documents retenus (avant la limite du carrousel) et `event_ids`, les événements du
     * même fait (celui de l'adresse d'abord, puis les autres sources de la plus sûre à la moins sûre), dans
     * l'ordre des dates. `$api` : pour l'API, sans les images montrées avec une simple autorisation
     * (API_RIGHTS).
     *
     * @return array<int, array{images: int, documents: int, event_ids: list<int>}>
     */
    public function available(Line $line, bool $api = false): array
    {
        $rows = $this->counts($line->id, $api ? self::API_RIGHTS : self::RIGHTS);
        $facts = self::facts(array_values(array_map(fn (array $r): array => $r['event'], $rows)));
        $out = [];
        foreach ($rows as $id => $r) {
            $members = $facts[$id] ?? null;
            if ($members === null) {
                continue;
            }
            // Images rattachées à l'un des événements du fait : comptées une fois par fichier.
            $attached = [];
            foreach ($members as $m) {
                foreach ($rows[$m]['attached'] ?? [] as $key) {
                    $attached[$key] = true;
                }
            }
            $images = $r['window'] + count($attached);
            if (self::meetsThreshold($images, $r['documents'])) {
                $out[$id] = ['images' => $images, 'documents' => $r['documents'], 'event_ids' => $members];
            }
        }

        return $out;
    }

    /**
     * Faits de la ligne : événement de l'adresse → événements du même fait, celui de l'adresse d'abord, puis
     * les autres de la source la plus sûre à la moins sûre (canonical()). Même fait : même type, même date et
     * même précision, même portion (toute la ligne, ou les deux mêmes lieux nommés quel que soit leur ordre,
     * écrits à la casse, aux accents et à « St- / Saint- » près, comme la chronologie). Un événement dont la
     * portion n'est pas désignée par deux lieux (un seul lieu, tracé sans nom, portion non localisée) reste
     * un fait à lui seul : rien ne dit que deux sources parlent du même tronçon. Calcul pur.
     *
     * @param  list<array{id: int, type: string, date: string, precision: string, source: ?string, source_url: ?string,
     *     confidence: ?string, section_from: ?string, section_to: ?string, section_resolution: ?string, section_pk_start_m: ?int}>  $events
     * @return array<int, list<int>>
     */
    public static function facts(array $events): array
    {
        $byKey = [];
        foreach ($events as $e) {
            $portion = self::portion($e);
            $key = $portion === null ? '#'.$e['id'] : implode('#', [$e['type'], substr($e['date'], 0, 10), $e['precision'], $portion]);
            $byKey[$key][] = $e;
        }
        $out = [];
        foreach ($byKey as $group) {
            usort($group, fn (array $a, array $b): int => self::rank($a) <=> self::rank($b));
            $out[(int) $group[0]['id']] = array_map(fn (array $e): int => (int) $e['id'], $group);
        }

        return $out;
    }

    /**
     * Portion d'un événement pour facts() : `whole` (toute la ligne : aucune portion citée, ou résolue « toute
     * la ligne », comme LinePhases::events), les deux lieux triés, ou null.
     *
     * @param  array{section_from: ?string, section_to: ?string, section_resolution: ?string, section_pk_start_m: ?int}  $e
     */
    private static function portion(array $e): ?string
    {
        if ($e['section_resolution'] === 'whole_line'
            || ($e['section_resolution'] === null && $e['section_from'] === null && $e['section_to'] === null && $e['section_pk_start_m'] === null)) {
            return 'whole';
        }
        $places = array_values(array_unique(array_filter([LineChronicle::placeKey($e['section_from']), LineChronicle::placeKey($e['section_to'])])));
        if (count($places) !== 2) {
            return null;
        }
        sort($places);

        return implode('|', $places);
    }

    /**
     * Rang d'une source pour l'adresse d'une histoire (plus petit = plus sûr) : fiabilité de la source
     * (EraTimeline::SOURCE_PRIORITY ; les tableaux PDF de lignes-oubliees sont des décisions administratives),
     * confiance de l'événement, précision de sa date, puis le plus ancien identifiant.
     *
     * @param  array{id: int, precision: string, source: ?string, source_url: ?string, confidence: ?string}  $e
     * @return list<int>
     */
    private static function rank(array $e): array
    {
        $source = (string) EraTimeline::rankedSource($e['source'], $e['source_url'], $e['confidence'] ?? null);

        return [
            EraTimeline::SOURCE_PRIORITY[$source] ?? 10,
            self::CONFIDENCE_RANK[$e['confidence'] ?? 'medium'] ?? 1,
            self::PRECISION_RANK[$e['precision']] ?? 0,
            (int) $e['id'],
        ];
    }

    /**
     * Événements du même fait que `$event` (facts()), celui de l'adresse de l'histoire d'abord.
     *
     * @return list<int>
     */
    public function factOf(LineEvent $event): array
    {
        $rows = DB::select('SELECT '.self::FACT_COLUMNS.' FROM line_events WHERE line_id = ? AND event_date = ? AND type = ? ORDER BY id',
            [$event->line_id, $event->event_date->format('Y-m-d'), $event->getRawOriginal('type')]);
        foreach (self::facts(array_map(self::factRow(...), $rows)) as $members) {
            if (in_array((int) $event->id, $members, true)) {
                return $members;
            }
        }

        return [(int) $event->id];
    }

    /**
     * @return array{id: int, type: string, date: string, precision: string, source: ?string, source_url: ?string,
     *     confidence: ?string, section_from: ?string, section_to: ?string, section_resolution: ?string, section_pk_start_m: ?int}
     */
    private static function factRow(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'type' => (string) $r->type,
            'date' => substr((string) $r->event_date, 0, 10),
            'precision' => (string) $r->date_precision,
            'source' => $r->source,
            'source_url' => $r->source_url,
            'confidence' => $r->confidence,
            'section_from' => $r->section_from,
            'section_to' => $r->section_to,
            'section_resolution' => $r->section_resolution,
            'section_pk_start_m' => $r->section_pk_start_m === null ? null : (int) $r->section_pk_start_m,
        ];
    }

    /** Seuil d'une histoire (règle unique, aussi lue par les tests). */
    public static function meetsThreshold(int $images, int $documents): bool
    {
        return $images >= self::MIN_IMAGES && $images + $documents >= self::MIN_ITEMS;
    }

    /**
     * Histoire du fait de l'événement (facts()), ou null quand il n'en a pas (type sans histoire, événement
     * d'une autre ligne, contenu sous le seuil). `event` est l'événement de l'adresse de l'histoire, qui peut
     * être un autre que `$event` (une autre source du même fait) ; `sources` donne tous les événements du fait,
     * celui de l'adresse d'abord. `$api` : comme available().
     *
     * @return array{event: array<string, mixed>, sources: list<array<string, mixed>>, line: array{slug: string, code: ?string, name: string},
     *     window: array{years: int, from: int, to: int}, images: list<array<string, mixed>>, images_total: int,
     *     documents: list<array<string, mixed>>, stations: list<array{name: string, slug: string}>}|null
     */
    public function story(Line $line, LineEvent $event, bool $api = false): ?array
    {
        if ((int) $event->line_id !== (int) $line->id || ! in_array($event->getRawOriginal('type'), self::EVENT_TYPES, true)) {
            return null;
        }
        $year = (int) $event->event_date->format('Y');
        $rights = $api ? self::API_RIGHTS : self::RIGHTS;
        $memberIds = $this->factOf($event);
        $members = LineEvent::query()->whereIn('id', $memberIds)->get()->keyBy('id');
        $canonical = $members->get($memberIds[0]) ?? $event;

        // Une même image (même fichier du fournisseur) une seule fois : rattachée à l'événement d'abord.
        $attachedByFile = $this->attachedImages($memberIds, $year, $rights);
        $windowImages = array_values(array_diff_key($this->windowImages($line->id, $year, $rights), $attachedByFile));
        $attached = array_values($attachedByFile);
        $documents = $this->documents($line, $year);
        if (! self::meetsThreshold(count($windowImages) + count($attached), count($documents))) {
            return null;
        }

        // Les plus proches de la date (milieu de l'intervalle possible ; à écart égal : la plus ancienne
        // d'abord), puis l'ordre chronologique.
        $byDistance = $windowImages;
        $mid = fn (array $i): float => ($i['gap_years_min'] + $i['gap_years_max']) / 2;
        usort($byDistance, fn (array $a, array $b): int => [abs($mid($a)), $mid($a), $a['id']] <=> [abs($mid($b)), $mid($b), $b['id']]);
        $kept = array_slice([...$attached, ...$byDistance], 0, self::MAX_IMAGES);
        usort($kept, fn (array $a, array $b): int => [$a['depicted_year'] === null ? 1 : 0, $a['depicted_year'] ?? 0, $a['id']]
            <=> [$b['depicted_year'] === null ? 1 : 0, $b['depicted_year'] ?? 0, $b['id']]);

        return [
            'event' => LineEventResource::make($canonical)->resolve(),
            'sources' => array_values(array_filter(array_map(
                fn (int $id): ?array => ($m = $members->get($id)) instanceof LineEvent ? LineEventResource::make($m)->resolve() : null,
                $memberIds,
            ))),
            'line' => ['slug' => (string) $line->slug, 'code' => $line->code, 'name' => (string) $line->name],
            'window' => ['years' => self::WINDOW_YEARS, 'from' => $year - self::WINDOW_YEARS, 'to' => $year + self::WINDOW_YEARS],
            'images' => $kept,
            'images_total' => count($windowImages) + count($attached),
            'documents' => $documents,
            'stations' => $this->stations($line, $canonical),
        ];
    }

    /**
     * Texte de l'événement pour la page, dans la langue demandée, comme la chronologie de la fiche ligne
     * (App\Services\LineChronicle) : description des sources mise en forme, lieux écrits comme les gares
     * de la ligne, citation, portion en toutes lettres. Aucun texte nouveau.
     *
     * @param  list<array{name: string, slug: string}>  $stations  gares de la portion (story())
     * @return array{description: ?string, description_lang: ?string, correction: ?string, quote: ?string,
     *     quote_lang: ?string, quote_kind: ?string, section_text: ?string}
     */
    public function text(LineEvent $event, array $stations, string $locale): array
    {
        // Description obligatoire en base (line_events.description NOT NULL).
        [$raw, $correction] = EventText::splitCorrection(LineChronicle::dashes($event->description));
        $d = EventText::describe($event->source, $raw, $locale, $event->section_from, $event->section_to);
        $names = [];
        foreach ($stations as $s) {
            $names[(string) LineChronicle::placeKey($s['name'])] = $s['name'];
        }
        $from = LineChronicle::stationName($event->section_from, $names);
        $to = LineChronicle::stationName($event->section_to, $names);
        $q = EventText::quoteDetails($event->source, $event->source_quote === null ? null : LineChronicle::dashes($event->source_quote), $locale);

        return [
            'description' => LineChronicle::renamePlaces($d['text'], [(string) $event->section_from => $from, (string) $event->section_to => $to]),
            'description_lang' => $d['lang'],
            'correction' => $correction,
            'quote' => $q['kind'] === 'citation' && $q['text'] !== null ? Str::limit($q['text'], 600) : $q['text'],
            'quote_lang' => $q['lang'],
            'quote_kind' => $q['kind'],
            'section_text' => LineChronicle::placeText($from, $to, $locale),
        ];
    }

    /**
     * Pour chaque événement concerné de la ligne, en une requête : ses colonnes de fait (facts()), le nombre
     * d'images de la ligne dans sa fenêtre (`window`), les fichiers des images rattachées à lui qui ne sont pas
     * déjà parmi elles (`attached`, clés de fileKey()) et le nombre de documents (`documents`), dans l'ordre
     * des dates.
     *
     * @param  list<string>  $allowedRights
     * @return array<int, array{event: array<string, mixed>, window: int, attached: list<string>, documents: int}>
     */
    private function counts(int $lineId, array $allowedRights): array
    {
        $types = implode(',', array_fill(0, count(self::EVENT_TYPES), '?'));
        $rights = implode(',', array_fill(0, count($allowedRights), '?'));
        $mediaPrec = implode(',', array_fill(0, count(self::MEDIA_PRECISIONS), '?'));
        $docPrec = implode(',', array_fill(0, count(self::DOCUMENT_PRECISIONS), '?'));
        $w = self::WINDOW_YEARS;
        $interval = sprintf(self::SQL_INTERVAL, self::CIRCA_MARGIN);

        // `m` est matérialisée : sans cela, PostgreSQL l'intègre à la sous-requête corrélée et relit les
        // images de la ligne pour chaque événement (58 ms au lieu de 11 ms pour rfn-956000, 20/11/2026).
        // `a` : fichiers rattachés aux événements, sauf ceux que la fenêtre compte déjà (même fichier de la ligne).
        $rows = DB::select(
            'WITH e AS (
                SELECT '.self::FACT_COLUMNS.", EXTRACT(YEAR FROM event_date)::int AS y FROM line_events
                WHERE line_id = ? AND type IN ({$types})
             ), m AS MATERIALIZED (
                SELECT * FROM (
                    SELECT provider, external_id, {$interval} FROM media
                    WHERE mediable_type = ? AND mediable_id = ? AND depicted_year IS NOT NULL AND rights_status IN ({$rights})
                      AND ".Media::displayableSql('media')."
                      AND COALESCE(provider_meta->>'date_precision', 'year') IN ({$mediaPrec})
                ) i WHERE lo IS NOT NULL AND hi IS NOT NULL
             ), d AS (
                SELECT year AS y, COALESCE(year_to, year) AS y2 FROM line_archive_documents
                WHERE line_id = ? AND year IS NOT NULL AND date_precision IN ({$docPrec})
             ), a AS (
                SELECT am.mediable_id AS id,
                    json_agg(DISTINCT am.provider || ':' || COALESCE(NULLIF(am.external_id, ''), '#' || am.id::text)) AS files
                FROM media am JOIN e ON e.id = am.mediable_id
                WHERE am.mediable_type = ? AND am.rights_status IN ({$rights}) AND ".Media::displayableSql('am')."
                  AND NOT EXISTS (SELECT 1 FROM m WHERE m.provider = am.provider AND m.external_id = am.external_id
                      AND m.lo >= e.y - {$w} AND m.hi <= e.y + {$w})
                GROUP BY am.mediable_id
             )
             SELECT e.*,
                (SELECT COUNT(*) FROM m WHERE m.lo >= e.y - {$w} AND m.hi <= e.y + {$w}) AS window_images,
                (SELECT COUNT(*) FROM d WHERE d.y <= e.y + {$w} AND d.y2 >= e.y - {$w}) AS documents,
                a.files
             FROM e LEFT JOIN a ON a.id = e.id
             ORDER BY e.y, e.id",
            [$lineId, ...self::EVENT_TYPES, Line::class, $lineId, ...$allowedRights, ...self::MEDIA_PRECISIONS,
                $lineId, ...self::DOCUMENT_PRECISIONS, LineEvent::class, ...$allowedRights],
        );

        $out = [];
        foreach ($rows as $r) {
            $files = is_string($r->files) ? json_decode($r->files, true) : [];
            $out[(int) $r->id] = [
                'event' => self::factRow($r),
                'window' => (int) $r->window_images,
                'attached' => is_array($files) ? array_values(array_map('strval', $files)) : [],
                'documents' => (int) $r->documents,
            ];
        }

        return $out;
    }

    /**
     * Intervalle possible de l'année représentée d'une image, [première année, dernière année], ou null quand
     * il n'est pas borné (« avant 1900 », « après 1900 », année tirée d'une catégorie, fourchette sans fin).
     * Même règle que SQL_INTERVAL.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function imageInterval(?int $year, ?string $precision, mixed $yearTo = null): ?array
    {
        if ($year === null) {
            return null;
        }
        $to = is_numeric($yearTo) && preg_match('/^[0-9]{3,4}$/', (string) $yearTo) === 1 ? (int) $yearTo : null;

        return match ($precision ?? 'year') {
            'day', 'month' => [$year, $year],
            'year' => [$year, max($year, $to ?? $year)],
            'circa' => [$year - self::CIRCA_MARGIN, $year + self::CIRCA_MARGIN],
            'decade' => [$year - $year % 10, $year - $year % 10 + 9],
            'range' => $to === null ? null : [$year, $to],
            default => null,
        };
    }

    /**
     * Images de la ligne dont tout l'intervalle possible tombe dans la fenêtre, avec leur écart, par fichier
     * (fileKey()).
     *
     * @param  list<string>  $rights
     * @return array<string, array<string, mixed>>
     */
    private function windowImages(int $lineId, int $year, array $rights): array
    {
        $w = self::WINDOW_YEARS;
        $interval = sprintf(self::SQL_INTERVAL, self::CIRCA_MARGIN);

        return $this->published(Media::query()
            ->where('mediable_type', Line::class)
            ->where('mediable_id', $lineId)
            ->whereNotNull('depicted_year')
            // Préfiltre sur l'index (une décennie commence au plus 9 ans avant), puis la règle exacte.
            ->whereBetween('depicted_year', [$year - $w - 9, $year + $w + self::CIRCA_MARGIN])
            ->whereRaw("COALESCE(provider_meta->>'date_precision', 'year') IN (".implode(',', array_fill(0, count(self::MEDIA_PRECISIONS), '?')).')', self::MEDIA_PRECISIONS)
            ->whereRaw("EXISTS (SELECT 1 FROM (SELECT {$interval}) i WHERE i.lo >= ? AND i.hi <= ?)", [$year - $w, $year + $w]),
            $year, false, $rights);
    }

    /**
     * Images rattachées aux événements du fait (proposées puis validées pour eux), quelle que soit leur date,
     * par fichier (fileKey()) : un même fichier proposé pour deux sources du fait n'est gardé qu'une fois.
     *
     * @param  list<int>  $eventIds
     * @param  list<string>  $rights
     * @return array<string, array<string, mixed>>
     */
    private function attachedImages(array $eventIds, int $year, array $rights): array
    {
        return $this->published(Media::query()->where('mediable_type', LineEvent::class)->whereIn('mediable_id', $eventIds), $year, true, $rights);
    }

    /**
     * Images publiées avec leur écart, par fichier (fileKey()), la plus ancienne ligne de `media` d'abord.
     *
     * @param  Builder<Media>  $query
     * @param  list<string>  $rights
     * @return array<string, array<string, mixed>>
     */
    private function published(Builder $query, int $year, bool $attached, array $rights): array
    {
        $out = [];
        foreach ($query->whereIn('rights_status', $rights)->displayable()->orderBy('id')->get() as $m) {
            $meta = is_array($m->provider_meta) ? $m->provider_meta : [];
            $precision = isset($meta['date_precision']) ? (string) $meta['date_precision'] : null;
            $span = self::imageInterval($m->depicted_year === null ? null : (int) $m->depicted_year, $precision, $meta['year_to'] ?? null);
            $out[self::fileKey($m)] ??= MediaResource::make($m)->resolve() + [
                'gap_years' => $m->depicted_year === null ? null : (int) $m->depicted_year - $year,
                'gap_years_min' => $span === null ? null : $span[0] - $year,
                'gap_years_max' => $span === null ? null : $span[1] - $year,
                'gap_exact' => $span !== null && $span[0] === $span[1],
                'attached' => $attached,
            ];
        }

        return $out;
    }

    /**
     * Fichier d'une image : fournisseur et identifiant chez lui (un même fichier de Wikimedia Commons peut
     * avoir deux lignes dans `media`, l'une rattachée à la ligne, l'autre à l'événement), sinon la ligne de
     * `media` (fichier déposé). Même clé que la requête de counts().
     */
    private static function fileKey(Media $m): string
    {
        return $m->external_id === null || $m->external_id === '' ? $m->provider.':#'.$m->id : $m->provider.':'.$m->external_id;
    }

    /**
     * Documents de Gallica de la ligne dans la fenêtre (même forme que l'API des archives), avec leur écart :
     * 0 quand l'intervalle d'un document contient l'année de l'événement.
     *
     * @return list<array<string, mixed>>
     */
    private function documents(Line $line, int $year): array
    {
        $out = [];
        foreach ($this->archives->documents($line) as $d) {
            /** @var LineArchiveDocument $d */
            if ($d->year === null || ! in_array($d->date_precision, self::DOCUMENT_PRECISIONS, true)) {
                continue;
            }
            $from = (int) $d->year;
            $to = (int) ($d->year_to ?? $d->year);
            if ($from > $year + self::WINDOW_YEARS || $to < $year - self::WINDOW_YEARS) {
                continue;
            }
            $out[] = $this->archives->toApi($d) + ['gap_years' => match (true) {
                $from > $year => $from - $year,
                $to < $year => $to - $year,
                default => 0,
            }];
        }

        return $out;
    }

    /**
     * Gares de la ligne nommées par la portion de l'événement (début, fin), écrites et liées comme dans la
     * liste des gares de la fiche ; un lieu qui n'est pas une gare de la ligne n'y figure pas.
     *
     * @return list<array{name: string, slug: string}>
     */
    private function stations(Line $line, LineEvent $event): array
    {
        $places = array_values(array_filter([$event->section_from, $event->section_to], fn (?string $p): bool => $p !== null && trim($p) !== ''));
        if ($places === []) {
            return [];
        }
        $byKey = [];
        $names = [];
        foreach (DB::select('SELECT s.name, s.slug FROM station_lines sl JOIN stations s ON s.id = sl.station_id
                WHERE sl.line_id = ? AND s.slug IS NOT NULL ORDER BY sl.pk_m NULLS LAST, s.id', [$line->id]) as $s) {
            $key = LineChronicle::placeKey($s->name);
            if ($key !== null && ! isset($byKey[$key])) {
                $byKey[$key] = ['name' => (string) $s->name, 'slug' => (string) $s->slug];
                $names[$key] = (string) $s->name;
            }
        }
        $out = [];
        foreach ($places as $place) {
            $key = LineChronicle::placeKey(LineChronicle::stationName($place, $names));
            if ($key !== null && isset($byKey[$key]) && ! in_array($byKey[$key], $out, true)) {
                $out[] = $byKey[$key];
            }
        }

        return $out;
    }
}
