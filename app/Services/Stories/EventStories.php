<?php

namespace App\Services\Stories;

use App\Http\Resources\LineEventResource;
use App\Http\Resources\MediaResource;
use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Models\LineEvent;
use App\Models\Media;
use App\Services\Archives\LineArchives;
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
 *   - carrousel : les MAX_IMAGES images les plus proches de la date, remises dans l'ordre chronologique.
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
     * Événements de la ligne qui ont une histoire, en une requête : identifiant → nombre d'images et de
     * documents retenus (avant la limite du carrousel), dans l'ordre des dates. `$api` : pour l'API, sans
     * les images montrées avec une simple autorisation (API_RIGHTS).
     *
     * @return array<int, array{images: int, documents: int}>
     */
    public function available(Line $line, bool $api = false): array
    {
        $out = [];
        foreach ($this->counts($line->id, $api ? self::API_RIGHTS : self::RIGHTS) as $id => $c) {
            if (self::meetsThreshold($c['images'], $c['documents'])) {
                $out[$id] = $c;
            }
        }

        return $out;
    }

    /** Seuil d'une histoire (règle unique, aussi lue par les tests). */
    public static function meetsThreshold(int $images, int $documents): bool
    {
        return $images >= self::MIN_IMAGES && $images + $documents >= self::MIN_ITEMS;
    }

    /**
     * Histoire d'un événement de la ligne, ou null quand il n'en a pas (type sans histoire, événement d'une
     * autre ligne, contenu sous le seuil). `$api` : comme available().
     *
     * @return array{event: array<string, mixed>, line: array{slug: string, code: ?string, name: string},
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

        $windowImages = $this->windowImages($line->id, $year, $rights);
        $attached = $this->attachedImages($event->id, $year, $rights);
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
            'event' => LineEventResource::make($event)->resolve(),
            'line' => ['slug' => (string) $line->slug, 'code' => $line->code, 'name' => (string) $line->name],
            'window' => ['years' => self::WINDOW_YEARS, 'from' => $year - self::WINDOW_YEARS, 'to' => $year + self::WINDOW_YEARS],
            'images' => $kept,
            'images_total' => count($windowImages) + count($attached),
            'documents' => $documents,
            'stations' => $this->stations($line, $event),
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
     * Nombre d'images (fenêtre et images de l'événement) et de documents de chaque événement concerné de la
     * ligne, en une requête.
     *
     * @return array<int, array{images: int, documents: int}>
     */
    /**
     * @param  list<string>  $allowedRights
     * @return array<int, array{images: int, documents: int}>
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
        $rows = DB::select(
            "WITH e AS (
                SELECT id, EXTRACT(YEAR FROM event_date)::int AS y FROM line_events
                WHERE line_id = ? AND type IN ({$types})
             ), m AS MATERIALIZED (
                SELECT * FROM (
                    SELECT {$interval} FROM media
                    WHERE mediable_type = ? AND mediable_id = ? AND depicted_year IS NOT NULL AND rights_status IN ({$rights})
                      AND COALESCE(provider_meta->>'date_precision', 'year') IN ({$mediaPrec})
                ) i WHERE lo IS NOT NULL AND hi IS NOT NULL
             ), d AS (
                SELECT year AS y, COALESCE(year_to, year) AS y2 FROM line_archive_documents
                WHERE line_id = ? AND year IS NOT NULL AND date_precision IN ({$docPrec})
             ), a AS (
                SELECT mediable_id AS id, COUNT(*) AS n FROM media
                WHERE mediable_type = ? AND rights_status IN ({$rights})
                  AND mediable_id IN (SELECT id FROM e)
                GROUP BY mediable_id
             )
             SELECT e.id,
                (SELECT COUNT(*) FROM m WHERE m.lo >= e.y - {$w} AND m.hi <= e.y + {$w}) + COALESCE(a.n, 0) AS images,
                (SELECT COUNT(*) FROM d WHERE d.y <= e.y + {$w} AND d.y2 >= e.y - {$w}) AS documents
             FROM e LEFT JOIN a ON a.id = e.id
             ORDER BY e.y, e.id",
            [$lineId, ...self::EVENT_TYPES, Line::class, $lineId, ...$allowedRights, ...self::MEDIA_PRECISIONS,
                $lineId, ...self::DOCUMENT_PRECISIONS, LineEvent::class, ...$allowedRights],
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = ['images' => (int) $r->images, 'documents' => (int) $r->documents];
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
     * Images de la ligne dont tout l'intervalle possible tombe dans la fenêtre, avec leur écart.
     *
     * @param  list<string>  $rights
     * @return list<array<string, mixed>>
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
     * Images rattachées à l'événement lui-même (proposées puis validées pour lui), quelle que soit leur date.
     *
     * @param  list<string>  $rights
     * @return list<array<string, mixed>>
     */
    private function attachedImages(int $eventId, int $year, array $rights): array
    {
        return $this->published(Media::query()->where('mediable_type', LineEvent::class)->where('mediable_id', $eventId), $year, true, $rights);
    }

    /**
     * @param  Builder<Media>  $query
     * @param  list<string>  $rights
     * @return list<array<string, mixed>>
     */
    private function published(Builder $query, int $year, bool $attached, array $rights): array
    {
        return $query->whereIn('rights_status', $rights)
            ->orderBy('id')
            ->get()
            ->map(function (Media $m) use ($year, $attached): array {
                $meta = is_array($m->provider_meta) ? $m->provider_meta : [];
                $precision = isset($meta['date_precision']) ? (string) $meta['date_precision'] : null;
                $span = self::imageInterval($m->depicted_year === null ? null : (int) $m->depicted_year, $precision, $meta['year_to'] ?? null);

                return MediaResource::make($m)->resolve() + [
                    'gap_years' => $m->depicted_year === null ? null : (int) $m->depicted_year - $year,
                    'gap_years_min' => $span === null ? null : $span[0] - $year,
                    'gap_years_max' => $span === null ? null : $span[1] - $year,
                    'gap_exact' => $span !== null && $span[0] === $span[1],
                    'attached' => $attached,
                ];
            })
            ->values()
            ->all();
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
