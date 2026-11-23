<?php

namespace App\Services\Accidents;

use App\Enums\AccidentLinkConfidence;
use App\Enums\AccidentLocationPrecision;
use Illuminate\Support\Facades\DB;

/**
 * Rattachement d'un accident aux lignes VFF, sur le tracé de l'ÉPOQUE de l'accident.
 *
 * Les ères sont des instantanés au 31/12 des années rondes : un accident de 1933 se cherche sur
 * les tronçons en service des ères 1930 et 1940 (la ligne pouvait ouvrir pendant la décennie).
 * Lignes candidates : tronçons en service à moins du rayon fixé par la précision du lieu
 * (AccidentLocationPrecision::matchRadiusM). Une ligne désignée par une source (ligne Wikidata
 * P81, article « Ligne de … » cité par Wikipédia, code de ligne du passage à niveau) est
 * renforcée, et cherchée plus loin si besoin.
 *
 * Pour chaque ligne retenue : la portion (gares de part et d'autre du lieu, parmi les gares de
 * la ligne à cette date), le PK (celui du passage à niveau, sinon interpolé entre les PK des deux
 * gares), la méthode et la confiance (AccidentLinkConfidence).
 */
class AccidentLineMatcher
{
    /** Statuts de tronçon où circulaient des trains. */
    private const RUNNING = ['in_service', 'single_track', 'service_track'];

    /** Distance au-delà de laquelle une ligne désignée par une source n'est plus crue. */
    private const HINT_MAX_M = 15000;

    /** Une deuxième ligne est gardée (tronc commun, jonction) si elle est aussi proche, à 50 m près. */
    private const SHARED_MARGIN_M = 50;

    /** Gare à moins de 300 m : l'accident a eu lieu en gare. */
    private const AT_STATION_M = 300;

    /**
     * @return list<array{
     *   line_id: int, is_primary: bool, era_year: int, distance_m: int, pk_m: ?int,
     *   section_from_station_id: ?int, section_to_station_id: ?int, method: string,
     *   confidence: AccidentLinkConfidence, score: float
     * }>
     */
    public function match(AccidentCandidate $c): array
    {
        if ($c->lat === null || $c->lng === null || $c->locationPrecision === null || $c->year() === null) {
            return [];
        }
        $eras = $this->eraYears((int) $c->year());
        $radius = $c->locationPrecision->matchRadiusM();
        $hints = $this->hintedLines($c);

        $found = $this->nearbyLines((float) $c->lat, (float) $c->lng, $eras, $radius);
        foreach (array_diff(array_keys($hints), array_keys($found)) as $lineId) {
            $far = $this->nearbyLines((float) $c->lat, (float) $c->lng, $eras, self::HINT_MAX_M, $lineId);
            if (isset($far[$lineId])) {
                $found[$lineId] = $far[$lineId];
                $c->note('hinted_line_far', "ligne {$lineId} à ".round($far[$lineId]['distance_m'] / 1000, 1).' km du lieu indiqué');
            }
        }
        if ($found === []) {
            return [];
        }

        $links = [];
        foreach ($found as $lineId => $f) {
            $hinted = $hints[$lineId] ?? [];
            $closeness = max(0.0, 1 - $f['distance_m'] / max($radius, $f['distance_m'], 1));
            $links[] = [
                'line_id' => $lineId,
                'era_year' => $f['era_year'],
                'distance_m' => (int) round($f['distance_m']),
                'hinted' => $hinted,
                'score' => round(min(1.0, 0.6 * $closeness + ($hinted !== [] ? 0.4 : 0.0)), 3),
            ];
        }
        usort($links, fn ($a, $b) => [$b['score'], $a['distance_m']] <=> [$a['score'], $b['distance_m']]);

        $primary = $links[0];
        $precise = $c->locationPrecision !== AccidentLocationPrecision::Commune;
        $kept = [$primary];
        foreach (array_slice($links, 1) as $l) {
            $shared = $precise && $l['distance_m'] <= $primary['distance_m'] + self::SHARED_MARGIN_M;
            if (($shared || $l['hinted'] !== []) && count($kept) < 4) {
                $kept[] = $l;
            }
        }
        if (! $precise && $primary['hinted'] === [] && count($links) > 1) {
            $c->note('line_uncertain', count($links).' lignes à moins de '.round($radius / 1000).' km du centre de la commune');
        }

        $out = [];
        foreach ($kept as $i => $l) {
            $unique = count(array_filter($links, fn ($o) => $o['distance_m'] <= $l['distance_m'] + 100)) === 1;
            // Commune seule : ni portion ni PK (ils feraient passer le centre de la commune pour le lieu).
            $section = $precise
                ? $this->section($l['line_id'], $l['era_year'], (int) $c->year(), (float) $c->lat, (float) $c->lng)
                : ['from' => null, 'to' => null, 'pk_m' => null];
            $pk = $c->levelCrossingPk !== null && in_array('level_crossing', $l['hinted'], true) ? $c->levelCrossingPk : $section['pk_m'];
            $out[] = [
                'line_id' => $l['line_id'],
                'is_primary' => $i === 0,
                'era_year' => $l['era_year'],
                'distance_m' => $l['distance_m'],
                'pk_m' => $pk,
                'section_from_station_id' => $section['from'],
                'section_to_station_id' => $section['to'],
                'method' => implode('+', array_values(array_unique(['proximity', ...$l['hinted']]))),
                'confidence' => $this->confidence($c->locationPrecision, $l['distance_m'], $l['hinted'] !== [], $unique, $radius),
                'score' => $l['score'],
            ];
        }

        return $out;
    }

    /**
     * Confiance d'un rattachement (règles de AccidentLinkConfidence).
     */
    public function confidence(AccidentLocationPrecision $precision, int $distanceM, bool $hinted, bool $unique, int $radius): AccidentLinkConfidence
    {
        if ($precision === AccidentLocationPrecision::Commune) {
            return $hinted ? AccidentLinkConfidence::Medium : AccidentLinkConfidence::Low;
        }
        if ($distanceM > $radius) {
            return AccidentLinkConfidence::Low;
        }
        if ($hinted || ($distanceM <= 100 && $unique)) {
            return AccidentLinkConfidence::High;
        }

        return $distanceM <= 400 ? AccidentLinkConfidence::Medium : AccidentLinkConfidence::Low;
    }

    /**
     * Ères qui encadrent une année : la décennie précédente et la suivante (1933 → 1930, 1940 ;
     * à partir de 2020, l'ère courante 2030 = réseau d'aujourd'hui).
     *
     * @return list<int>
     */
    public function eraYears(int $year): array
    {
        $floor = max(1830, min(2030, intdiv($year, 10) * 10));

        return array_values(array_unique([$floor, min(2030, $floor + 10)]));
    }

    /**
     * Gare VFF la plus proche du lieu, ouverte à la date quand ses dates sont connues (10 km au
     * plus). Aucune gare quand la position n'est que le centre d'une commune (ou de précision
     * inconnue) : un lieu connu seulement à la commune près n'est « à X m » d'aucune gare.
     *
     * @return array{id: int, distance_m: int}|null
     */
    public function nearestStation(AccidentCandidate $c): ?array
    {
        if ($c->lat === null || $c->lng === null || $c->date === null
            || $c->locationPrecision === null || ! $c->locationPrecision->pinpointsStation()) {
            return null;
        }
        $row = DB::selectOne(
            'WITH pt AS (SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS p)
             SELECT s.id, ST_Distance(s.geom::geography, pt.p::geography) AS d
             FROM stations s, pt
             WHERE s.geom && ST_Expand(pt.p, 0.15)
               AND (s.opened_at IS NULL OR s.opened_at <= ?::date)
               AND (s.closed_at IS NULL OR s.closed_at >= ?::date)
             ORDER BY d LIMIT 1',
            [$c->lng, $c->lat, $c->date, $c->date],
        );
        if ($row === null || (float) $row->d > 10000) {
            return null;
        }

        return ['id' => (int) $row->id, 'distance_m' => (int) round((float) $row->d)];
    }

    /**
     * Lignes désignées par les sources → méthodes qui les désignent.
     *
     * @return array<int, list<string>>
     */
    private function hintedLines(AccidentCandidate $c): array
    {
        $hints = [];
        if ($c->lineItems !== []) {
            foreach (DB::table('wikidata_lines')->whereIn('qid', $c->lineItems)->whereNotNull('line_id')->pluck('line_id') as $id) {
                $hints[(int) $id][] = 'wikidata';
            }
        }
        if ($c->lineArticles !== []) {
            $titles = array_map(fn ($t) => str_replace('_', ' ', $t), $c->lineArticles);
            $ids = DB::table('lines')->whereIn('wikipedia_title', $titles)->pluck('id')
                ->merge(DB::table('wikidata_lines')->whereIn('frwiki_title', $titles)->whereNotNull('line_id')->pluck('line_id'));
            foreach ($ids->unique() as $id) {
                $hints[(int) $id][] = 'wikipedia';
            }
        }
        if ($c->lineCodes !== []) {
            foreach (DB::table('lines')->whereIn('code', $c->lineCodes)->pluck('id') as $id) {
                $hints[(int) $id][] = 'level_crossing';
            }
        }

        return array_map(fn ($m) => array_values(array_unique($m)), $hints);
    }

    /**
     * Distance du lieu à chaque ligne en service aux ères données (la plus courte des ères).
     *
     * @param  list<int>  $eras
     * @return array<int, array{distance_m: float, era_year: int}>
     */
    private function nearbyLines(float $lat, float $lng, array $eras, int $radius, ?int $onlyLine = null): array
    {
        $deg = $radius / 70000; // borne en degrés (marge généreuse en longitude à 50° N)
        $rows = DB::select(
            'WITH pt AS (SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS p)
             SELECT s.line_id, e.year AS era_year, MIN(ST_Distance(s.geom::geography, pt.p::geography)) AS d
             FROM line_segments s JOIN eras e ON e.id = s.era_id, pt
             WHERE e.year IN ('.implode(',', array_map('intval', $eras)).')
               AND s.status IN (\''.implode("','", self::RUNNING).'\')
               AND s.geom && ST_Expand(pt.p, ?)
               AND ST_DWithin(s.geom::geography, pt.p::geography, ?)
               AND (?::bigint IS NULL OR s.line_id = ?)
             GROUP BY s.line_id, e.year',
            [$lng, $lat, $deg, $radius, $onlyLine, $onlyLine],
        );
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->line_id;
            $d = (float) $r->d;
            // Égalité : l'ère d'avant l'accident l'emporte.
            if (! isset($out[$id]) || $d < $out[$id]['distance_m'] - 0.5
                || (abs($d - $out[$id]['distance_m']) <= 0.5 && (int) $r->era_year < $out[$id]['era_year'])) {
                $out[$id] = ['distance_m' => $d, 'era_year' => (int) $r->era_year];
            }
        }

        return $out;
    }

    /**
     * Portion de la ligne où se trouve le lieu : gares de part et d'autre, le long du tracé de
     * l'ère (partie continue la plus proche du lieu), parmi les gares desservies l'année de
     * l'accident ; PK interpolé entre les PK de ces deux gares quand ils sont connus.
     *
     * @return array{from: ?int, to: ?int, pk_m: ?int}
     */
    private function section(int $lineId, int $eraYear, int $year, float $lat, float $lng): array
    {
        $rows = DB::select(
            'WITH pt AS (SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS p),
             merged AS (
                SELECT ST_LineMerge(ST_Collect(s.geom)) AS g FROM line_segments s JOIN eras e ON e.id = s.era_id
                WHERE s.line_id = ? AND e.year = ?
             ),
             parts AS (SELECT (ST_Dump(g)).geom AS g FROM merged),
             best AS (SELECT parts.g FROM parts, pt ORDER BY ST_Distance(parts.g::geography, pt.p::geography) LIMIT 1)
             SELECT NULL::bigint AS station_id, NULL::integer AS pk_m, ST_LineLocatePoint(best.g, pt.p) AS f, 0::float AS d
             FROM best, pt
             UNION ALL
             SELECT st.id, sl.pk_m, ST_LineLocatePoint(best.g, st.geom), ST_Distance(best.g::geography, st.geom::geography)
             FROM station_lines sl JOIN stations st ON st.id = sl.station_id, best
             WHERE sl.line_id = ? AND (sl.first_year IS NULL OR sl.first_year <= ?) AND (sl.last_year IS NULL OR sl.last_year >= ?)
               AND st.geom && ST_Expand(best.g, 0.02)',
            [$lng, $lat, $lineId, $eraYear, $lineId, $year, $year],
        );
        if ($rows === [] || $rows[0]->station_id !== null) {
            return ['from' => null, 'to' => null, 'pk_m' => null];
        }
        $f = (float) $rows[0]->f;
        $before = null;
        $after = null;
        foreach (array_slice($rows, 1) as $s) {
            if ((float) $s->d > 1000) {
                continue; // gare trop loin du tracé pour le jalonner
            }
            if ((float) $s->f <= $f && ($before === null || (float) $s->f > (float) $before->f)) {
                $before = $s;
            }
            if ((float) $s->f >= $f && ($after === null || (float) $s->f < (float) $after->f)) {
                $after = $s;
            }
        }
        $atStation = $this->stationAt($lat, $lng, array_filter([$before, $after]));
        if ($atStation !== null) {
            return ['from' => (int) $atStation->station_id, 'to' => null, 'pk_m' => $atStation->pk_m !== null ? (int) $atStation->pk_m : null];
        }
        $pk = null;
        if ($before !== null && $after !== null && $before->pk_m !== null && $after->pk_m !== null && (float) $after->f > (float) $before->f) {
            $t = ($f - (float) $before->f) / ((float) $after->f - (float) $before->f);
            $pk = (int) round((int) $before->pk_m + $t * ((int) $after->pk_m - (int) $before->pk_m));
        }

        return [
            'from' => $before !== null ? (int) $before->station_id : null,
            'to' => $after !== null ? (int) $after->station_id : null,
            'pk_m' => $pk,
        ];
    }

    /**
     * Gare (parmi les deux qui encadrent le lieu) à moins de 300 m : accident en gare.
     *
     * @param  array<int, object>  $stations
     */
    private function stationAt(float $lat, float $lng, array $stations): ?object
    {
        foreach ($stations as $s) {
            $d = DB::scalar(
                'SELECT ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) FROM stations WHERE id = ?',
                [$lng, $lat, $s->station_id],
            );
            if ($d !== null && (float) $d <= self::AT_STATION_M) {
                return $s;
            }
        }

        return null;
    }
}
