<?php

namespace App\Services\Signalling;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Place la portion d'une mise en service de signalisation sur la ligne, sans rien inventer :
 *   - l'article donne les PK (« du PK 12,3 au PK 20,1 ») : ils sont gardés (`article_pk`) ;
 *   - l'article nomme deux lieux : chacun doit être UNE gare de la ligne (`station_lines`) dont le PK est
 *     calé (PK officiel de SNCF Réseau, PK d'une liste ou d'un schéma recalé, PK interpolé entre deux gares
 *     de PK connu ; jamais l'abscisse brute d'un tracé, `projected_raw`). Les PK des deux gares bornent la
 *     portion (`stations`) ;
 *   - un lieu qui n'est pas une gare de la ligne peut être un point remarquable de la ligne (bifurcation,
 *     raccordement, origine ou fin, frontière : `database/data/line_landmarks.php`, LineLandmarksFile), au
 *     PK relevé dans les sources ouvertes ; la gare passe toujours en premier. Une portion bornée par au
 *     moins un point est `landmarks` (gare retenue à l'autre bout s'il y en a une) ;
 *   - sinon (un seul lieu, lieu introuvable, homonymes) : `unlocated`, la portion reste celle écrite.
 *
 * La portion placée est ensuite tracée sur les formes SNCF Réseau de la ligne, avec le même calage des PK
 * que le calque de l'état actuel (`line_signalling_map` : interpolation entre les passages à niveau de PK
 * connu, à 34 m près en médiane ; les points remarquables de la ligne servent aussi de repères, ce qui cale
 * les LGV, sans passage à niveau). Sans forme SNCF Réseau (ligne fermée, sans code), pas de tracé.
 */
final class SignallingSectionLocator
{
    /** Origines de PK acceptées (station_lines.pk_source) : tout sauf l'abscisse brute d'un tracé. */
    private const CALIBRATED = ['sncf', 'projected', 'lignes-oubliees', 'wikipedia_schema', 'correction'];

    /** Écart maximal (m) entre les PK de gares candidates pour un même lieu. */
    private const SAME_PLACE_M = 1000;

    /** @var array<int, list<array{id: int, name: string, key: string, pk_m: int}>> gares par ligne */
    private array $stations = [];

    /** @var array<int|string, list<array{name: string, keys: list<string>, kind: string, pk_m: int, lat: float, lon: float, method: string}>>|null points remarquables par code de ligne (clé entière : PHP range ainsi un code tout en chiffres) */
    private ?array $landmarks = null;

    /** @var array<int, ?string> code SNCF Réseau par ligne */
    private array $codes = [];

    /** @param  ?string  $landmarksPath  fichier des points remarquables (défaut : database/data/line_landmarks.php) */
    public function __construct(private readonly ?string $landmarksPath = null) {}

    /**
     * @return array{location: ?string, from_station_id: ?int, to_station_id: ?int, pk_start_m: ?int, pk_end_m: ?int}
     */
    public function locate(int $lineId, string $scope, ?string $from, ?string $to, ?int $pkStart, ?int $pkEnd): array
    {
        $none = ['location' => null, 'from_station_id' => null, 'to_station_id' => null, 'pk_start_m' => null, 'pk_end_m' => null];
        if ($scope !== 'section') {
            return $none;
        }
        if ($pkStart !== null && $pkEnd !== null && $pkStart !== $pkEnd) {
            return ['location' => 'article_pk', 'pk_start_m' => min($pkStart, $pkEnd), 'pk_end_m' => max($pkStart, $pkEnd)] + $none;
        }
        $a = $from !== null ? ($this->station($lineId, $from) ?? $this->landmark($lineId, $from)) : null;
        $b = $to !== null ? ($this->station($lineId, $to) ?? $this->landmark($lineId, $to)) : null;
        if ($a === null || $b === null || $a['pk_m'] === $b['pk_m']) {
            return ['location' => 'unlocated'] + $none;
        }

        return [
            'location' => $a['id'] === null || $b['id'] === null ? 'landmarks' : 'stations',
            'from_station_id' => $a['id'],
            'to_station_id' => $b['id'],
            'pk_start_m' => min($a['pk_m'], $b['pk_m']),
            'pk_end_m' => max($a['pk_m'], $b['pk_m']),
        ];
    }

    /**
     * Le point remarquable de la ligne qui porte ce nom ou cet alias (nom identique, sans accents ni casse ;
     * jamais un début de nom), ou null.
     *
     * @return array{id: null, name: string, pk_m: int}|null
     */
    public function landmark(int $lineId, string $name): ?array
    {
        $key = self::key($name);
        foreach ($this->landmarksOf($this->code($lineId)) as $point) {
            if (in_array($key, $point['keys'], true)) {
                return ['id' => null, 'name' => $point['name'], 'pk_m' => $point['pk_m']];
            }
        }

        return null;
    }

    /**
     * La gare de la ligne qui porte ce nom : nom identique (sans accents ni casse, « Saint » = « St »), sinon
     * le seul nom qui commence par celui-ci (« Saint-Nom-la-Bretèche » → « Saint-Nom-la-Bretèche - Forêt-de-Marly »).
     * Plusieurs candidats à plus d'un kilomètre l'un de l'autre (« Lyon » : Perrache, Part-Dieu…) : aucune, la
     * portion n'est pas placée.
     *
     * @return array{id: int, name: string, key: string, pk_m: int}|null
     */
    public function station(int $lineId, string $name): ?array
    {
        $key = self::key($name);
        if ($key === '') {
            return null;
        }
        $stations = $this->stations($lineId);
        foreach ([fn (array $s): bool => $s['key'] === $key, fn (array $s): bool => str_starts_with($s['key'], $key.' ')] as $test) {
            $found = array_values(array_filter($stations, $test));
            if ($found === []) {
                continue;
            }
            // Plusieurs gares du même nom au même endroit (« … Forêt-de-Marly » et « … Forêt-de-Marly TT ») :
            // le PK est sûr, la gare au nom le plus court est gardée. Éloignées : homonymes, rien n'est placé.
            $pks = array_column($found, 'pk_m');
            if (max($pks) - min($pks) > self::SAME_PLACE_M) {
                return null;
            }
            usort($found, fn (array $x, array $y): int => [mb_strlen($x['name']), $x['id']] <=> [mb_strlen($y['name']), $y['id']]);

            return $found[0];
        }

        return null;
    }

    /**
     * Gares de la ligne dont le PK est calé (voir CALIBRATED), dans l'ordre des PK.
     *
     * @return list<array{id: int, name: string, key: string, pk_m: int}>
     */
    public function stations(int $lineId): array
    {
        return $this->stations[$lineId] ??= $this->load($lineId);
    }

    /**
     * Portion [PK de début, PK de fin] tracée sur les formes SNCF Réseau de la ligne, en WKB hexadécimal
     * (MultiLineString, WGS 84), ou null si la ligne n'a pas de forme ou si la portion tombe hors des formes.
     */
    public function geometry(?string $code, int $pkStart, int $pkEnd): ?string
    {
        if ($code === null || $code === '' || $pkEnd <= $pkStart) {
            return null;
        }
        $row = DB::selectOne(<<<'SQL'
            WITH troncons AS (
                SELECT g.id,
                       LEAST(g.pk_debut_m, g.pk_fin_m) AS d, GREATEST(g.pk_debut_m, g.pk_fin_m) AS f,
                       CASE WHEN g.pk_debut_m <= g.pk_fin_m THEN g.geom ELSE ST_Reverse(g.geom) END AS geom
                FROM sncf_line_geometries g
                WHERE g.code_ligne = :code AND g.pk_debut_m IS NOT NULL AND g.pk_fin_m IS NOT NULL AND g.pk_debut_m <> g.pk_fin_m
            ),
            landmarks AS (
                SELECT pk, ST_SetSRID(ST_MakePoint(lon, lat), 4326) AS geom
                FROM jsonb_to_recordset(CAST(:landmarks AS jsonb)) AS p(pk integer, lat float8, lon float8)
            ),
            raw_controls AS (
                SELECT t.id AS troncon_id, pn.pk_m AS pk, ST_LineLocatePoint(t.geom, pn.geom) AS frac
                FROM troncons t
                JOIN sncf_level_crossings pn ON pn.code_ligne = :code2 AND pn.pk_m > t.d AND pn.pk_m < t.f
                WHERE ST_DWithin(t.geom::geography, pn.geom::geography, 30)
                UNION ALL
                SELECT t.id, p.pk, ST_LineLocatePoint(t.geom, p.geom)
                FROM troncons t JOIN landmarks p ON p.pk > t.d AND p.pk < t.f
                WHERE ST_DWithin(t.geom::geography, p.geom::geography, 30)
                UNION ALL SELECT id, d, 0 FROM troncons
                UNION ALL SELECT id, f, 1 FROM troncons
            ),
            controls AS (
                SELECT troncon_id, pk, frac FROM (
                    SELECT c.*, MAX(frac) OVER (PARTITION BY troncon_id ORDER BY pk, frac ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING) AS before
                    FROM raw_controls c
                ) x WHERE before IS NULL OR frac >= before
            ),
            pieces AS (
                SELECT t.id AS troncon_id, t.geom, GREATEST(:pa, t.d) AS pa, LEAST(:pb, t.f) AS pb
                FROM troncons t WHERE :pa2 < t.f AND :pb2 > t.d
            ),
            cut AS (
                SELECT ST_LineSubstring(p.geom, LEAST(fa.v, fb.v), GREATEST(fa.v, fb.v)) AS g
                FROM pieces p
                CROSS JOIN LATERAL (
                    SELECT CASE WHEN hi.pk = lo.pk THEN lo.frac ELSE lo.frac + (hi.frac - lo.frac) * (p.pa - lo.pk)::float8 / (hi.pk - lo.pk) END AS v
                    FROM (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk <= p.pa ORDER BY pk DESC LIMIT 1) lo,
                         (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk >= p.pa ORDER BY pk LIMIT 1) hi
                ) fa
                CROSS JOIN LATERAL (
                    SELECT CASE WHEN hi.pk = lo.pk THEN lo.frac ELSE lo.frac + (hi.frac - lo.frac) * (p.pb - lo.pk)::float8 / (hi.pk - lo.pk) END AS v
                    FROM (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk <= p.pb ORDER BY pk DESC LIMIT 1) lo,
                         (SELECT pk, frac FROM controls c WHERE c.troncon_id = p.troncon_id AND c.pk >= p.pb ORDER BY pk LIMIT 1) hi
                ) fb
            )
            SELECT encode(ST_AsEWKB(ST_Multi(ST_CollectionExtract(ST_Collect(g), 2))), 'hex') AS geom
            FROM cut WHERE GeometryType(g) = 'LINESTRING'
            HAVING COUNT(*) > 0
            SQL, ['code' => $code, 'code2' => $code, 'pa' => $pkStart, 'pb' => $pkEnd, 'pa2' => $pkStart, 'pb2' => $pkEnd,
            'landmarks' => json_encode(array_map(fn (array $p): array => ['pk' => $p['pk_m'], 'lat' => $p['lat'], 'lon' => $p['lon']], $this->landmarksOf($code)), JSON_THROW_ON_ERROR)]);

        return $row?->geom ?: null;
    }

    /** Nom comparable : minuscules sans accents, « saint » → « st », ponctuation en espaces, sans article initial. */
    public static function key(string $name): string
    {
        $s = Str::ascii(mb_strtolower($name));
        $s = (string) preg_replace(['/\bsainte\b/', '/\bsaint\b/', '/[^a-z0-9]+/'], ['ste', 'st', ' '], $s);
        $s = (string) preg_replace('/^(?:gare de |gare d |le |la |les |l )/', '', trim($s));

        return trim($s);
    }

    /** @return list<array{name: string, keys: list<string>, kind: string, pk_m: int, lat: float, lon: float, method: string}> */
    private function landmarksOf(?string $code): array
    {
        $this->landmarks ??= LineLandmarksFile::load($this->landmarksPath);

        return $code === null ? [] : $this->landmarks[$code] ?? [];
    }

    private function code(int $lineId): ?string
    {
        if (! array_key_exists($lineId, $this->codes)) {
            $code = DB::table('lines')->where('id', $lineId)->value('code');
            $this->codes[$lineId] = is_string($code) && $code !== '' ? $code : null;
        }

        return $this->codes[$lineId];
    }

    /** @return list<array{id: int, name: string, key: string, pk_m: int}> */
    private function load(int $lineId): array
    {
        return DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')
            ->where('sl.line_id', $lineId)->whereNotNull('sl.pk_m')->whereIn('sl.pk_source', self::CALIBRATED)
            ->orderBy('sl.pk_m')->get(['s.id', 's.name', 'sl.pk_m'])
            ->map(fn (object $r): array => ['id' => (int) $r->id, 'name' => (string) $r->name, 'key' => self::key((string) $r->name), 'pk_m' => (int) $r->pk_m])
            ->all();
    }
}
