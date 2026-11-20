<?php

namespace App\Services\Quiz;

use App\Enums\QuizLevel;
use App\Services\LineFacts;
use App\Services\TraceExport\LineTraceExport;
use App\Support\WebUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Jeu « Reconnaître la ligne » : le tracé d'une ligne, seul, sans nom ni fond de carte.
 *
 * Vivier : lignes dont le texte « À propos » vient de Wikipédia (phrase montrée après la réponse, avec
 * l'attribution de Wikipédia) et dont le tracé peut être redistribué. Un texte rédigé à la main dans
 * `database/data/lignes_hors_rfn.php` remplace celui de Wikipédia à chaque passage de
 * vff:seed-off-network-lines, même si la ligne garde sa source `wikipedia-intro` : ces lignes sont
 * écartées (handWrittenSlugs()), pour ne jamais attribuer à Wikipédia un texte qui n'en vient pas.
 *
 * Même règle des droits que les exports (LineTraceExport, `api.withhold_unlicensed_geometry`) : l'ère retenue est celle où le tracé de la ligne est le plus
 * long parmi les ères dont TOUS les tronçons ont une source à licence ouverte vérifiée, et ce tracé doit
 * couvrir au moins COVERAGE de la plus grande longueur connue de la ligne (pas de fragment trompeur).
 *
 * Le tracé envoyé est projeté en Lambert-93 (formes sans déformation), simplifié, puis ramené à un
 * cadre de 1 000 unités : ni coordonnées ni position sur la carte, seulement la forme et l'échelle.
 */
final class LineQuiz implements QuizSource
{
    /** Tracé le plus court retenu (m) : en dessous, la forme ne dit rien. */
    public const MIN_LENGTH_M = 5000;

    /** Part minimale de la plus grande longueur de la ligne que doit couvrir le tracé redistribuable. */
    public const COVERAGE = 0.9;

    /** Côté du cadre du tracé envoyé à la page (unités du `viewBox`). */
    public const FRAME = 1000;

    /** Simplification : tolérance égale au plus grand côté du tracé divisé par cette valeur. */
    public const SIMPLIFY_DIVISOR = 500;

    /** Départements cités en indice, au plus. */
    private const MAX_DEPARTMENTS = 4;

    /** Statuts d'un tronçon qui porte du trafic (comme LineDirectory). */
    private const ACTIVE_STATUSES = "('in_service','single_track')";

    /** Fiabilités des événements retenues pour les années (celles que montre la fiche). */
    private const SHOWN_CONFIDENCES = "('high','medium')";

    private const WIKIPEDIA_LICENSE = 'CC BY-SA 4.0';

    private const WIKIPEDIA_LICENSE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/';

    public function __construct(private readonly LineTraceExport $rights) {}

    public function pool(): array
    {
        $ok = $this->rights->sqlRedistributable('ls.source');
        $handWritten = '{'.implode(',', array_map(fn (string $slug): string => '"'.addcslashes($slug, '"\\').'"', self::handWrittenSlugs())).'}';

        return Cache::remember('quiz:lines-pool:v2:'.md5($ok.$handWritten), now()->addHour(), function () use ($ok, $handWritten): array {
            $rows = DB::select(<<<SQL
                WITH per AS (
                    SELECT ls.line_id, ls.era_id,
                           SUM(COALESCE(ls.length_meters, ST_Length(ls.geom::geography))) AS len,
                           BOOL_AND(COALESCE({$ok}, FALSE)) AS ok
                    FROM line_segments ls
                    GROUP BY ls.line_id, ls.era_id
                ), best AS (
                    SELECT DISTINCT ON (p.line_id) p.line_id, p.era_id, p.len, e.year
                    FROM per p JOIN eras e ON e.id = p.era_id
                    WHERE p.ok
                    ORDER BY p.line_id, p.len DESC, e.year DESC
                ), longest AS (
                    SELECT line_id, MAX(len) AS len FROM per GROUP BY line_id
                )
                SELECT l.id, l.slug, l.name, b.era_id, b.year, b.len, ST_X(c.pt) AS cx, ST_Y(c.pt) AS cy
                FROM best b
                JOIN longest m ON m.line_id = b.line_id
                JOIN lines l ON l.id = b.line_id
                CROSS JOIN LATERAL (
                    SELECT ST_Centroid(ST_Transform(ST_Collect(s.geom), 2154)) AS pt
                    FROM line_segments s WHERE s.line_id = b.line_id AND s.era_id = b.era_id
                ) c
                WHERE b.len >= ? AND b.len >= ? * m.len
                  AND l.description IS NOT NULL AND btrim(l.description) <> ''
                  AND EXISTS (SELECT 1 FROM line_sources src WHERE src.line_id = l.id AND src.source_type = 'wikipedia-intro')
                  AND l.slug <> ALL (?::text[])
                ORDER BY l.id
            SQL, [self::MIN_LENGTH_M, self::COVERAGE, $handWritten]);

            return array_map(fn (object $r): array => [
                'id' => (int) $r->id,
                'key' => (string) $r->slug,
                'label' => (string) $r->name,
                'era_id' => (int) $r->era_id,
                'era_year' => (int) $r->year,
                'length_m' => (int) round((float) $r->len),
                'cx' => (float) $r->cx,
                'cy' => (float) $r->cy,
            ], $rows);
        });
    }

    /**
     * Lignes dont la description est rédigée à la main (database/data/lignes_hors_rfn.php, réécrite par
     * vff:seed-off-network-lines) : leur texte ne vient pas de Wikipédia.
     *
     * @return list<string>
     */
    public static function handWrittenSlugs(): array
    {
        $entries = require database_path('data/lignes_hors_rfn.php');
        $slugs = [];
        foreach (is_array($entries) ? $entries : [] as $e) {
            if (is_array($e) && is_string($e['slug'] ?? null) && trim((string) ($e['description'] ?? '')) !== '') {
                $slugs[] = $e['slug'];
            }
        }

        return $slugs;
    }

    /** Lignes du vivier de la plus proche à la plus lointaine (distance entre les centres des tracés). */
    public function candidates(array $subject, array $pool, QuizLevel $level): array
    {
        $others = array_values(array_filter($pool, fn (array $l): bool => $l['id'] !== $subject['id']));
        $distance = fn (array $l): float => ($l['cx'] - $subject['cx']) ** 2 + ($l['cy'] - $subject['cy']) ** 2;
        usort($others, fn (array $a, array $b): int => [$distance($a), $a['id']] <=> [$distance($b), $b['id']]);

        return $others;
    }

    public function question(QuizPick $pick): array
    {
        $line = $pick->subject;

        return [
            'trace' => $this->trace((int) $line['id'], (int) $line['era_id']),
            'hints' => $this->hints($line),
        ];
    }

    public function reveal(array $subject): array
    {
        $line = DB::table('lines')->where('id', $subject['id'])->first(['slug', 'name', 'code', 'description', 'wikipedia_title']);
        $source = DB::table('line_sources')->where('line_id', $subject['id'])->where('source_type', 'wikipedia-intro')
            ->first(['source_url', 'fields_provided']);
        $meta = $source !== null ? (json_decode((string) $source->fields_provided, true) ?: []) : [];
        $title = $meta['title'] ?? $line?->wikipedia_title;

        return [
            'answer' => [
                'key' => (string) $line?->slug,
                'label' => (string) $line?->name,
                'code' => $line?->code,
                'url' => '/lines/'.$line?->slug,
            ],
            'fact' => [
                'text' => self::firstSentence((string) $line?->description),
                'lang' => 'fr',
                'source' => [
                    'name' => 'Wikipédia',
                    'title' => $title,
                    'url' => WebUrl::safe($source?->source_url),
                    'license' => self::WIKIPEDIA_LICENSE,
                    'license_url' => self::WIKIPEDIA_LICENSE_URL,
                ],
            ],
        ];
    }

    /**
     * Première phrase d'un texte (introduction Wikipédia) ; une phrase trop courte pour se suffire
     * (« Elle est fermée. ») est complétée par la suivante.
     */
    public static function firstSentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}«"])/u', $text) ?: [$text];
        $out = '';
        foreach ($parts as $part) {
            $out = $out === '' ? $part : $out.' '.$part;
            if (mb_strlen($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * Tracé simplifié, projeté en Lambert-93 et ramené au cadre : lignes brisées de points entiers
     * (y vers le bas, comme en SVG), taille du cadre et mètres par unité (barre d'échelle).
     *
     * @return array{paths: list<list<array{0: int, 1: int}>>, width: int, height: int, m_per_unit: float}|null
     */
    private function trace(int $lineId, int $eraId): ?array
    {
        $ok = $this->rights->sqlRedistributable('source');
        $row = DB::selectOne(<<<SQL
            WITH u AS (
                SELECT ST_Transform(ST_LineMerge(ST_Collect(geom)), 2154) AS g
                FROM line_segments WHERE line_id = ? AND era_id = ? AND COALESCE({$ok}, FALSE)
            ), e AS (
                SELECT g, GREATEST(ST_XMax(g) - ST_XMin(g), ST_YMax(g) - ST_YMin(g), 1) AS span FROM u WHERE g IS NOT NULL
            )
            SELECT ST_AsGeoJSON(ST_SimplifyPreserveTopology(g, span / ?), 1) AS geojson,
                   ST_XMin(g) AS x0, ST_YMin(g) AS y0, ST_XMax(g) AS x1, ST_YMax(g) AS y1, span
            FROM e
        SQL, [$lineId, $eraId, self::SIMPLIFY_DIVISOR]);
        if ($row === null) {
            return null;
        }
        $geometry = json_decode((string) $row->geojson, true);
        $lines = match ($geometry['type'] ?? null) {
            'LineString' => [$geometry['coordinates']],
            'MultiLineString' => $geometry['coordinates'],
            default => [],
        };

        return self::frame($lines, (float) $row->x0, (float) $row->y0, (float) $row->x1, (float) $row->y1);
    }

    /**
     * Ramène des lignes brisées en mètres (Lambert-93) au cadre de FRAME unités : origine en haut à
     * gauche, y vers le bas ; points répétés après arrondi retirés.
     *
     * @param  array<int, array<int, array<int, float|int>>>  $lines
     * @return array{paths: list<list<array{0: int, 1: int}>>, width: int, height: int, m_per_unit: float}
     */
    public static function frame(array $lines, float $x0, float $y0, float $x1, float $y1): array
    {
        $span = max($x1 - $x0, $y1 - $y0, 1.0);
        $k = self::FRAME / $span;
        $paths = [];
        foreach ($lines as $line) {
            $path = [];
            foreach ($line as $c) {
                $p = [(int) round(((float) $c[0] - $x0) * $k), (int) round(($y1 - (float) $c[1]) * $k)];
                if ($path === [] || $path[count($path) - 1] !== $p) {
                    $path[] = $p;
                }
            }
            if (count($path) >= 2) {
                $paths[] = $path;
            }
        }

        return [
            'paths' => $paths,
            'width' => max(1, (int) round(($x1 - $x0) * $k)),
            'height' => max(1, (int) round(($y1 - $y0) * $k)),
            'm_per_unit' => round($span / self::FRAME, 3),
        ];
    }

    /**
     * Indices en texte (version accessible de la question) : longueur du tracé montré, ère du tracé,
     * première ouverture datée, dernière fermeture datée si plus rien n'est en service, départements.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function hints(array $line): array
    {
        $years = DB::selectOne(
            "SELECT MIN(EXTRACT(YEAR FROM event_date)) FILTER (WHERE type = 'opening') AS opened,
                    MAX(EXTRACT(YEAR FROM event_date)) FILTER (WHERE type IN ('closure', 'closure_passenger', 'closure_freight')) AS closed
             FROM line_events WHERE line_id = ? AND confidence IN ".self::SHOWN_CONFIDENCES,
            [$line['id']],
        );
        $current = DB::selectOne(
            'SELECT COUNT(*) AS n, COUNT(*) FILTER (WHERE s.status IN '.self::ACTIVE_STATUSES.') AS active
             FROM line_segments s JOIN eras e ON e.id = s.era_id AND e.is_current WHERE s.line_id = ?',
            [$line['id']],
        );
        $inService = $current !== null && (int) $current->n > 0 ? (int) $current->active > 0 : null;
        $currentYear = DB::table('eras')->where('is_current', true)->value('year');

        return [
            'length_km' => round($line['length_m'] / 1000, 1),
            'era_year' => $line['era_year'],
            'era_is_current' => $currentYear !== null && (int) $currentYear === $line['era_year'],
            'opened' => $years?->opened !== null ? (int) $years->opened : null,
            'in_service' => $inService,
            // Une date de fermeture n'est dite que pour une ligne sans plus aucune portion en service.
            'closed' => $inService === false && $years?->closed !== null ? (int) $years->closed : null,
            'departments' => array_slice(array_column(LineFacts::departments((int) $line['id']), 'name'), 0, self::MAX_DEPARTMENTS),
        ];
    }
}
