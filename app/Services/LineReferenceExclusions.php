<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Retraits faits à la main dans les tracés de référence (database/data/line_reference_exclusions.php), lus par
 * vff:build-line-references à chaque reconstruction d'une ligne : un recalcul du moteur ne les fait pas revenir.
 *
 * Deux sortes d'entrées :
 *   - « trace » : une voie source (source_name + source_ref, clés stables d'un import à l'autre) n'entre pas dans le
 *     tracé de la ligne : en entier, ou seulement sa portion à moins de `radius_m` mètres du point `near` (lon, lat),
 *     pour retirer un bout de voie (crochet d'aiguillage) sans perdre le reste. Elle n'est ni prise comme tracé
 *     rattaché, ni proposée à cette ligne par le recalage et la continuité (pool de géométries modifié le temps de la
 *     ligne, remis ensuite) : le moteur refait le tracé et ses raccords sans elle. Les autres lignes la gardent ;
 *   - « fragment » : à la fin de la construction (après le nettoyage final, avant l'arrondi), la polyligne du tracé de
 *     la ligne qui passe à moins de FRAGMENT_TOL_M du point `at` (lon, lat) et mesure au plus `max_m` mètres est
 *     retirée. Désignée par sa position, pas par un identifiant de line_reference_parts (qui change à chaque calcul).
 *     Le retrait est inscrit dans line_reference_duplicates (action « removed », cause « retrait à la main »).
 *
 * Champs communs : line (code ou slug de la ligne), reason (pourquoi, faits seulement), source (lien https ou
 * rapport de relecture qui le montre), reviewed_at (AAAA-MM-JJ). Une entrée mal formée, en double ou dont la ligne
 * ou la voie n'existe pas n'est jamais appliquée : la commande la signale et le test du fichier versionné échoue.
 */
final class LineReferenceExclusions
{
    /** Distance maximale (m) entre le point d'une entrée « fragment » et la polyligne visée. */
    public const FRAGMENT_TOL_M = 5.0;

    private const COMMON = ['kind', 'line', 'reason', 'source', 'reviewed_at'];

    private const KEYS = ['trace' => ['source_name', 'source_ref', 'near', 'radius_m'], 'fragment' => ['at', 'max_m']];

    /** @var list<array<string, mixed>> */
    private array $entries;

    /** @var array<int, array{traces: list<array{id: int, lon: ?float, lat: ?float, radius_m: ?float}>, fragments: list<array{lon: float, lat: float, max_m: float, entry: int}>}>|null */
    private ?array $byLine = null;

    /** @var list<string> */
    private array $errors = [];

    /** @param  list<array<string, mixed>>|null  $entries  défaut : database/data/line_reference_exclusions.php */
    public function __construct(?array $entries = null)
    {
        $this->entries = $entries ?? (is_file($f = database_path('data/line_reference_exclusions.php')) ? require $f : []);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Erreurs de forme d'une entrée (vide si valide).
     *
     * @param  array<string, mixed>  $e
     * @return list<string>
     */
    public static function validate(array $e): array
    {
        $kind = $e['kind'] ?? null;
        if (! is_string($kind) || ! isset(self::KEYS[$kind])) {
            return ['kind : « trace » ou « fragment »'];
        }
        $errors = [];
        foreach (['line', 'reason', 'source', 'reviewed_at'] as $k) {
            if (! is_string($e[$k] ?? null) || trim($e[$k]) === '') {
                $errors[] = "{$k} manquant";
            } elseif ($e[$k] !== trim($e[$k])) {
                $errors[] = "{$k} : espaces autour";
            } elseif (str_contains($e[$k], "\u{2014}") || str_contains($e[$k], "\u{2013}") || str_contains($e[$k], '--')) {
                $errors[] = "{$k} : tiret long";
            }
        }
        if (is_string($e['reviewed_at'] ?? null) && (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $e['reviewed_at'], $d) || ! checkdate((int) $d[2], (int) $d[3], (int) $d[1]))) {
            $errors[] = 'reviewed_at : date AAAA-MM-JJ';
        }
        if ($kind === 'trace') {
            foreach (['source_name', 'source_ref'] as $k) {
                if (! is_string($e[$k] ?? null) || trim($e[$k]) === '') {
                    $errors[] = "{$k} manquant";
                }
            }
            if (array_key_exists('near', $e) || array_key_exists('radius_m', $e)) {
                if (! self::isPoint($e['near'] ?? null)) {
                    $errors[] = 'near : [lon, lat]';
                }
                if (! self::isLength($e['radius_m'] ?? null)) {
                    $errors[] = 'radius_m : longueur de 1 à 500 m';
                }
            }
        } else {
            if (! self::isPoint($e['at'] ?? null)) {
                $errors[] = 'at : [lon, lat]';
            }
            if (! self::isLength($e['max_m'] ?? null)) {
                $errors[] = 'max_m : longueur de 1 à 500 m';
            }
        }
        $unknown = array_diff(array_keys($e), [...self::COMMON, ...self::KEYS[$kind]]);
        if ($unknown !== []) {
            $errors[] = 'clés inconnues : '.implode(', ', $unknown);
        }

        return $errors;
    }

    private static function isPoint(mixed $p): bool
    {
        return is_array($p) && array_is_list($p) && count($p) === 2 && (is_int($p[0]) || is_float($p[0])) && (is_int($p[1]) || is_float($p[1]))
            && abs($p[0]) <= 180 && abs($p[1]) <= 90;
    }

    private static function isLength(mixed $m): bool
    {
        return (is_int($m) || is_float($m)) && $m >= 1 && $m <= 500;
    }

    /**
     * Entrées résolues dans la base, par id de ligne : ids des voies exclues, fragments à retirer. Les entrées
     * invalides, introuvables, ambiguës ou en double sont rendues par errors() et ne sont pas appliquées.
     *
     * @return array<int, array{traces: list<array{id: int, lon: ?float, lat: ?float, radius_m: ?float}>, fragments: list<array{lon: float, lat: float, max_m: float, entry: int}>}>
     */
    public function resolve(): array
    {
        if ($this->byLine !== null) {
            return $this->byLine;
        }
        $this->byLine = [];
        $this->errors = [];
        $seen = [];
        foreach ($this->entries as $i => $e) {
            $label = 'retrait n° '.$i.' ('.(is_string($e['line'] ?? null) ? $e['line'] : '?').')';
            if (($formErrors = self::validate($e)) !== []) {
                $this->errors[] = $label.' : '.implode(' ; ', $formErrors);

                continue;
            }
            $lines = DB::table('lines')->where(fn ($q) => $q->where('slug', $e['line'])->orWhere('code', $e['line']))->pluck('id');
            if ($lines->count() !== 1) {
                $this->errors[] = $label.' : ligne '.($lines->isEmpty() ? 'introuvable' : 'ambiguë');

                continue;
            }
            $lineId = (int) $lines->first();
            if ($e['kind'] === 'trace') {
                $trace = DB::table('source_traces')->where('source', $e['source_name'])->where('source_ref', $e['source_ref'])->value('id');
                if ($trace === null) {
                    $this->errors[] = $label.' : voie '.$e['source_name'].' '.$e['source_ref'].' introuvable';

                    continue;
                }
                $key = 't|'.$lineId.'|'.$trace.(isset($e['near']) ? '|'.round((float) $e['near'][0], 5).'|'.round((float) $e['near'][1], 5) : '');
            } else {
                $key = 'f|'.$lineId.'|'.round((float) $e['at'][0], 5).'|'.round((float) $e['at'][1], 5);
            }
            if (isset($seen[$key])) {
                $this->errors[] = $label.' : doublon du retrait n° '.$seen[$key];

                continue;
            }
            $seen[$key] = $i;
            $this->byLine[$lineId] ??= ['traces' => [], 'fragments' => []];
            if ($e['kind'] === 'trace') {
                $this->byLine[$lineId]['traces'][] = ['id' => (int) $trace, 'lon' => isset($e['near']) ? (float) $e['near'][0] : null,
                    'lat' => isset($e['near']) ? (float) $e['near'][1] : null, 'radius_m' => isset($e['radius_m']) ? (float) $e['radius_m'] : null];
            } else {
                $this->byLine[$lineId]['fragments'][] = ['lon' => (float) $e['at'][0], 'lat' => (float) $e['at'][1], 'max_m' => (float) $e['max_m'], 'entry' => $i];
            }
        }

        return $this->byLine;
    }

    /** @return list<string> */
    public function errors(): array
    {
        $this->resolve();

        return $this->errors;
    }

    /** @return list<array{id: int, lon: ?float, lat: ?float, radius_m: ?float}> voies (source_traces) exclues du tracé de la ligne, en entier (radius_m null) ou autour d'un point */
    public function tracesFor(int $lineId): array
    {
        return $this->resolve()[$lineId]['traces'] ?? [];
    }

    /**
     * Retire les fragments listés pour la ligne de ses morceaux (line_reference_parts). Une entrée qui ne trouve
     * aucune polyligne (déjà partie, ou plus longue que max_m) ne retire rien et est comptée à part.
     *
     * @return array{removed: int, removed_m: float, missing: list<int>}
     */
    public function removeFragments(int $lineId): array
    {
        $out = ['removed' => 0, 'removed_m' => 0.0, 'missing' => []];
        foreach ($this->resolve()[$lineId]['fragments'] ?? [] as $f) {
            $hits = DB::select(<<<'SQL'
                WITH pt AS (SELECT ST_Transform(ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 2154) AS p)
                SELECT p.id, d.path[1] AS k, ST_Length(ST_Transform(d.geom, 2154)) AS len
                FROM line_reference_parts p, LATERAL ST_Dump(p.geom) d, pt
                WHERE p.line_id = :line AND p.geom && ST_Expand(ST_Transform(pt.p, 4326), 0.001)
                  AND ST_DWithin(ST_Transform(d.geom, 2154), pt.p, :tol)
                  AND ST_Length(ST_Transform(d.geom, 2154)) <= :max
                SQL, ['lon' => $f['lon'], 'lat' => $f['lat'], 'line' => $lineId, 'tol' => self::FRAGMENT_TOL_M, 'max' => $f['max_m']]);
            if ($hits === []) {
                $out['missing'][] = $f['entry'];

                continue;
            }
            foreach ($hits as $h) {
                DB::insert(<<<'SQL'
                    INSERT INTO line_reference_duplicates (line_id, action, reason, source, matched_source, trace_ids, length_m, gap_median_m, geom, created_at, updated_at)
                    SELECT p.line_id, 'removed', 'retrait à la main', p.source, p.matched_source,
                           CASE WHEN p.matched_source IN ('contrib', 'bdtopo', 'osm', 'ign-historique') THEN p.matched_trace_ids ELSE p.source_trace_ids END,
                           ROUND(:len)::int, 0, ST_GeometryN(p.geom, :k), NOW(), NOW()
                    FROM line_reference_parts p WHERE p.id = :id
                    SQL, ['len' => $h->len, 'k' => $h->k, 'id' => $h->id]);
                $out['removed']++;
                $out['removed_m'] += (float) $h->len;
            }
            // Morceau entièrement visé : supprimé ; sinon réécrit sans les polylignes visées (en une fois : indices d'origine).
            foreach (collect($hits)->groupBy('id') as $id => $rows) {
                DB::delete(<<<'SQL'
                    DELETE FROM line_reference_parts p WHERE p.id = :id
                      AND NOT EXISTS (SELECT 1 FROM ST_Dump(p.geom) d WHERE NOT (d.path[1] = ANY(CAST(:ks AS int[]))))
                    SQL, ['id' => $id, 'ks' => '{'.$rows->pluck('k')->implode(',').'}']);
                DB::update(<<<'SQL'
                    UPDATE line_reference_parts p SET geom = r.g, length_m = ROUND(ST_Length(ST_Transform(r.g, 2154)))::int, updated_at = NOW()
                    FROM (SELECT ST_Multi(ST_Collect(d.geom ORDER BY d.path[1])) AS g
                          FROM line_reference_parts q, LATERAL ST_Dump(q.geom) d
                          WHERE q.id = :id AND NOT (d.path[1] = ANY(CAST(:ks AS int[])))) r
                    WHERE p.id = :id2 AND r.g IS NOT NULL
                    SQL, ['id' => $id, 'id2' => $id, 'ks' => '{'.$rows->pluck('k')->implode(',').'}']);
            }
        }

        return $out;
    }
}
