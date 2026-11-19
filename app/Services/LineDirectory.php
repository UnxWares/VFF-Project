<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Era;
use App\Models\Line;
use App\Support\FrenchDepartments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Annuaire des lignes : filtres, compteurs et état de chaque ligne à une ère, communs à la page
 * /lines (LineController::index), à l'API REST (GET /api/v1/lines), à GraphQL (`lines`) et au
 * serveur MCP. Lecture seule.
 *
 * Filtres (déjà validés) :
 *   - `q` : nom, code ou compagnie d'origine (recherche littérale, casse ignorée) ;
 *   - `network` : principal, secondaire, militaire, particulier, ou `none` (non renseigné) ;
 *   - `traffic` : passenger, mixed, freight, unknown (trafic des portions en service à l'ère) ;
 *   - `status` : `open` (au moins une portion en service à l'ère), `closed` (tracé connu à l'ère,
 *     plus aucune portion en service) ; une ligne sans tracé à l'ère n'est ni l'une ni l'autre ;
 *   - `heritage` : au moins une portion associative (train touristique, vélorail), toutes ères ;
 *   - `department` : code INSEE, lignes dont la fiche cite le département ou qui y desservent une gare.
 */
final class LineDirectory
{
    use ReadsListFilters;

    /** Catégories de réseau (contrat `lines.network`). */
    public const NETWORKS = ['principal', 'secondaire', 'militaire', 'particulier'];

    /** Types de trafic d'une portion (`line_segments.traffic_type`). */
    public const TRAFFICS = ['passenger', 'mixed', 'freight', 'unknown'];

    /** Statuts portant du trafic (cf. SegmentStatus::isActive / NetworkStats). */
    private const ACTIVE_STATUSES = "('in_service','single_track')";

    /** Ère de référence : celle de l'année demandée, sinon l'ère courante (aujourd'hui). */
    public function eraId(?int $year = null): ?int
    {
        if ($year !== null) {
            return Era::where('year', $year)->value('id');
        }

        return Era::where('is_current', true)->value('id') ?? Era::orderByDesc('year')->value('id');
    }

    /**
     * Applique les filtres à une requête sur `lines`.
     *
     * @template TBuilder of Builder<Line>
     *
     * @param  TBuilder  $builder
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $skip  filtres à ignorer (compteurs d'une facette calculés sans son propre filtre)
     * @return TBuilder
     */
    public function apply(Builder $builder, array $filters, ?int $eraId, array $skip = []): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = $this->containsPattern($q);
            $builder->where(fn ($w) => $w->where('lines.name', 'ilike', $like)
                ->orWhere('lines.code', 'ilike', $like)
                ->orWhere('lines.historical_operator', 'ilike', $like));
        }
        $network = (string) ($filters['network'] ?? '');
        if ($network !== '' && ! in_array('network', $skip, true)) {
            $network === 'none' ? $builder->whereNull('lines.network') : $builder->where('lines.network', $network);
        }
        $traffic = (string) ($filters['traffic'] ?? '');
        if ($traffic !== '' && ! in_array('traffic', $skip, true)) {
            $builder->whereExists(fn ($s) => $this->activeSegments($s, $eraId)->where('s.traffic_type', $traffic));
        }
        match ($filters['status'] ?? '') {
            'open' => $builder->whereExists(fn ($s) => $this->activeSegments($s, $eraId)),
            'closed' => $this->closed($builder, $eraId),
            default => null,
        };
        if (filter_var($filters['heritage'] ?? false, FILTER_VALIDATE_BOOL)) {
            $builder->whereHas('segments', fn ($s) => $s->where('is_heritage', true));
        }
        if (filled($filters['department'] ?? null)) {
            $builder->whereIn('lines.id', self::idsInDepartment((string) $filters['department']));
        }

        return $builder;
    }

    /**
     * Compteurs de l'annuaire filtré : lignes ouvertes, fermées, sans tracé à l'ère, par réseau,
     * par trafic, avec une portion associative. Chaque facette est comptée sans son propre filtre,
     * pour garder visibles les autres choix.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: int, open: int, closed: int, unknown: int, networks: array<string, int>, traffics: array<string, int>, heritage: int}
     */
    public function facets(array $filters, ?int $eraId): array
    {
        $byNetwork = $this->apply(Line::query(), $filters, $eraId, ['network'])
            ->selectRaw("COALESCE(network, 'none') AS k, COUNT(*) AS n")
            ->groupByRaw("COALESCE(network, 'none')")
            ->pluck('n', 'k');
        $byTraffic = DB::table('line_segments')
            ->where('era_id', $eraId)
            ->whereIn('status', ['in_service', 'single_track'])
            ->whereIn('line_id', $this->apply(Line::query()->select('lines.id'), $filters, $eraId, ['traffic']))
            ->selectRaw("COALESCE(traffic_type, 'unknown') AS k, COUNT(DISTINCT line_id) AS n")
            ->groupByRaw("COALESCE(traffic_type, 'unknown')")
            ->pluck('n', 'k');
        $states = $this->stateCounts($this->apply(Line::query(), $filters, $eraId), $eraId);

        $networks = [];
        foreach ([...self::NETWORKS, 'none'] as $k) {
            $networks[$k] = (int) ($byNetwork[$k] ?? 0);
        }
        $traffics = [];
        foreach (self::TRAFFICS as $k) {
            $traffics[$k] = (int) ($byTraffic[$k] ?? 0);
        }

        return [
            ...$states,
            'networks' => $networks,
            'traffics' => $traffics,
            'heritage' => $this->apply(Line::query(), $filters, $eraId)
                ->whereHas('segments', fn ($s) => $s->where('is_heritage', true))->count(),
        ];
    }

    /**
     * Aperçu de tout l'annuaire, sans filtre : nombre de lignes ouvertes, fermées et sans tracé à
     * l'ère, en une seule requête (mêmes valeurs que les mêmes clés de facets([], $eraId)).
     *
     * @return array{total: int, open: int, closed: int, unknown: int}
     */
    public function overview(?int $eraId): array
    {
        return $this->stateCounts(Line::query(), $eraId);
    }

    /**
     * Pour un lot de lignes : état à l'ère (open = au moins une portion en service, closed = tracé
     * connu mais plus aucune portion en service) et trafic dominant pondéré par la longueur des
     * portions en service. Une ligne absente du résultat n'a aucun tracé à cette ère.
     *
     * @param  array<int>  $lineIds
     * @return array<int, array{state: string, traffic: ?string, heritage: bool}>
     */
    public function summaries(array $lineIds, ?int $eraId): array
    {
        if ($lineIds === [] || $eraId === null) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
        $rows = DB::select(
            "WITH s AS (
                SELECT line_id, status, COALESCE(traffic_type, 'unknown') AS traffic_type,
                       COALESCE(is_heritage, false) AS is_heritage,
                       COALESCE(length_meters, ST_Length(geom::geography)) AS len,
                       status IN ".self::ACTIVE_STATUSES." AS active
                FROM line_segments
                WHERE era_id = ? AND line_id IN ({$placeholders})
            ),
            t AS (
                SELECT DISTINCT ON (line_id) line_id, traffic_type
                FROM (SELECT line_id, traffic_type, SUM(len) AS km FROM s WHERE active
                      GROUP BY line_id, traffic_type) x
                ORDER BY line_id, km DESC
            )
            SELECT s.line_id, BOOL_OR(s.active) AS is_open, MAX(t.traffic_type) AS traffic, BOOL_OR(s.is_heritage) AS heritage
            FROM s LEFT JOIN t ON t.line_id = s.line_id
            GROUP BY s.line_id",
            [$eraId, ...$lineIds],
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id] = [
                'state' => $r->is_open ? 'open' : 'closed',
                'traffic' => $r->traffic,
                'heritage' => (bool) $r->heritage,
            ];
        }

        return $out;
    }

    /**
     * Sous-requête : identifiants des lignes d'un département (fiche de la ligne qui le cite, ou
     * gare desservie dans ce département).
     */
    public static function idsInDepartment(string $code): QueryBuilder
    {
        $name = FrenchDepartments::find($code)[0] ?? $code;

        return DB::table('lines')->select('id')->whereJsonContains('departements', $name)
            ->union(
                DB::table('station_lines')->select('station_lines.line_id')
                    ->join('stations', 'stations.id', '=', 'station_lines.station_id')
                    ->where('stations.department_code', $code),
            );
    }

    /**
     * Lignes de la requête par état à l'ère, en un seul passage (COUNT … FILTER) : ouvertes et
     * fermées selon les mêmes sous-requêtes que les filtres `status=open` et `status=closed`.
     *
     * @param  Builder<Line>  $builder
     * @return array{total: int, open: int, closed: int, unknown: int}
     */
    private function stateCounts(Builder $builder, ?int $eraId): array
    {
        $active = $this->activeSegments(DB::query(), $eraId);
        $traced = $this->segmentsAtEra(DB::query(), $eraId);
        $row = $builder->toBase()->selectRaw(
            "COUNT(*) AS total, COUNT(*) FILTER (WHERE EXISTS ({$active->toSql()})) AS open, "
            ."COUNT(*) FILTER (WHERE EXISTS ({$traced->toSql()}) AND NOT EXISTS ({$active->toSql()})) AS closed",
            [...$active->getBindings(), ...$traced->getBindings(), ...$active->getBindings()],
        )->first();
        $total = (int) $row->total;
        $open = (int) $row->open;
        $closed = (int) $row->closed;

        return ['total' => $total, 'open' => $open, 'closed' => $closed, 'unknown' => max(0, $total - $open - $closed)];
    }

    /** Portions de la ligne courante à l'ère, quel que soit leur statut (sous-requête EXISTS). */
    private function segmentsAtEra(QueryBuilder $s, ?int $eraId): QueryBuilder
    {
        return $s->select(DB::raw(1))->from('line_segments as s')
            ->whereColumn('s.line_id', 'lines.id')
            ->where('s.era_id', $eraId);
    }

    /** Portions en service de la ligne courante à l'ère (sous-requête EXISTS). */
    private function activeSegments(QueryBuilder $s, ?int $eraId): QueryBuilder
    {
        return $this->segmentsAtEra($s, $eraId)->whereIn('s.status', ['in_service', 'single_track']);
    }

    /**
     * « Fermée » = tracé connu à l'ère mais plus aucune portion en service (une ligne sans géométrie
     * n'est ni ouverte ni fermée : inconnue).
     *
     * @template TBuilder of Builder<Line>
     *
     * @param  TBuilder  $builder
     * @return TBuilder
     */
    private function closed(Builder $builder, ?int $eraId): Builder
    {
        return $builder
            ->whereExists(fn ($s) => $this->segmentsAtEra($s, $eraId))
            ->whereNotExists(fn ($s) => $this->activeSegments($s, $eraId));
    }
}
