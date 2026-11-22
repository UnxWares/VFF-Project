<?php

namespace App\Services;

use App\Models\Era;
use App\Models\Line;
use App\Support\FrenchDepartments;
use App\Support\Search\SearchFields;
use App\Support\Search\SearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Annuaire des lignes : filtres, compteurs et état de chaque ligne à une ère, communs à la page
 * /lines (LineController::index), à l'API REST (GET /api/v1/lines), à GraphQL (`lines`) et au
 * serveur MCP. Lecture seule.
 *
 * Filtres (déjà validés) :
 *   - `q` : nom, autres noms, code ou compagnie d'origine (recherche commune App\Support\Search : tirets, accents,
 *     abréviations et petits mots indifférents, mots dans n'importe quel ordre, noms approchés si rien ne
 *     correspond mot pour mot) ;
 *   - `network` : principal, secondaire, militaire, particulier, ou `none` (non renseigné) ;
 *   - `traffic` : passenger, mixed, freight, unknown (trafic des portions en service à l'ère) ;
 *   - `status` : `open` (au moins une portion en service à l'ère), `closed` (tracé connu à l'ère,
 *     plus aucune portion en service) ; une ligne sans tracé à l'ère n'est ni l'une ni l'autre ;
 *   - `heritage` : au moins une portion associative (train touristique, vélorail), toutes ères ;
 *   - `department` : code INSEE, lignes dont la fiche cite le département ou qui y desservent une gare ;
 *   - `region` : code INSEE de la région, lignes d'au moins un de ses départements (même règle).
 */
final class LineDirectory
{
    /**
     * Compteurs par état déjà calculés pour une requête (stateCounts).
     *
     * @var array<string, array{total: int, open: int, closed: int, unknown: int}>
     */
    private array $stateCounts = [];

    /**
     * Recherches sans correspondance mot pour mot (approximate).
     *
     * @var array<string, bool>
     */
    private array $approximate = [];

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
            $search = SearchQuery::parse($q);
            $search->where($builder, SearchFields::keys('line', 'lines.'), self::codeMatch($search), $this->approximate($q));
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
        if (filled($filters['department'] ?? null) && ! in_array('department', $skip, true)) {
            $builder->whereIn('lines.id', self::idsInDepartment((string) $filters['department']));
        }
        if (filled($filters['region'] ?? null)) {
            $builder->whereIn('lines.id', self::idsInDepartments(FrenchDepartments::inRegion((string) $filters['region'])));
        }

        return $builder;
    }

    /**
     * La recherche ne trouve-t-elle aucune ligne mot pour mot (nom, autres noms, compagnie, code) ? Les listes
     * montrent alors les lignes au nom approché (fautes de frappe). Mémorisé par requête.
     */
    public function approximate(string $q): bool
    {
        $q = trim($q);
        if ($q === '') {
            return false;
        }
        if (! isset($this->approximate[$q])) {
            $search = SearchQuery::parse($q);
            $this->approximate[$q] = $search->canBeFuzzy()
                && ! $search->where(Line::query(), SearchFields::keys('line', 'lines.'), self::codeMatch($search))->exists();
        }

        return $this->approximate[$q];
    }

    /**
     * Tri par pertinence de la recherche : code exact, rang du nom (SearchQuery), lignes en service à l'ère.
     *
     * @template TBuilder of Builder<Line>
     *
     * @param  TBuilder  $builder
     * @return TBuilder
     */
    public function orderByRelevance(Builder $builder, string $q, ?int $eraId): Builder
    {
        $search = SearchQuery::parse($q);
        [$nameKey, $allKey] = SearchFields::keys('line', 'lines.');
        if ($search->digits() !== null) {
            $builder->orderByRaw("CASE WHEN replace(COALESCE(lines.code, ''), ' ', '') = ? THEN 0 ELSE 1 END", [$search->digits()]);
        }
        $search->orderByRelevance($builder, $nameKey, $allKey, $this->approximate($q));

        return $builder->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM line_segments s WHERE s.line_id = lines.id AND s.era_id = ?
            AND s.status IN '.self::ACTIVE_STATUSES.') THEN 0 ELSE 1 END', [$eraId]);
    }

    /**
     * Code de ligne, espaces ignorées (« 570 000 », « 570000 », « 5700 » : début du code), 3 chiffres au moins.
     *
     * @return array{0: string, 1: list<string>}|null
     */
    private static function codeMatch(SearchQuery $search): ?array
    {
        $digits = $search->digits();

        return $digits !== null && strlen($digits) >= 3 ? ["replace(COALESCE(lines.code, ''), ' ', '') LIKE ?", [$digits.'%']] : null;
    }

    /**
     * Compteurs de l'annuaire filtré : lignes ouvertes, fermées, sans tracé à l'ère, par réseau,
     * par trafic, avec une portion associative. Chaque facette est comptée sans son propre filtre,
     * pour garder visibles les autres choix.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: int, open: int, closed: int, unknown: int, networks: array<string, int>, traffics: array<string, int>, heritage: int, departments: array<string, int>}
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
            // Par département (sans le filtre `department`) : une ligne compte dans chacun de ses départements.
            'departments' => app(Territories::class)->lineCounts(
                $this->apply(Line::query()->select('lines.id'), $filters, $eraId, ['department'])->toBase(),
            ),
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
        return self::idsInDepartments([$code]);
    }

    /**
     * Sous-requête : identifiants des lignes d'au moins un de ces départements (même règle
     * qu'idsInDepartment). Liste vide : aucune ligne.
     *
     * @param  list<string>  $codes  codes INSEE
     */
    public static function idsInDepartments(array $codes): QueryBuilder
    {
        $names = array_map(fn (string $code): string => FrenchDepartments::find($code)[0] ?? $code, $codes);

        return DB::table('lines')->select('id')
            ->where(function (QueryBuilder $w) use ($names): void {
                $w->whereRaw('false');
                foreach ($names as $name) {
                    $w->orWhereJsonContains('departements', $name);
                }
            })
            ->union(
                DB::table('station_lines')->select('station_lines.line_id')
                    ->join('stations', 'stations.id', '=', 'station_lines.station_id')
                    ->whereIn('stations.department_code', $codes),
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
        // Annuaire sans filtre : l'aperçu (overview) et les compteurs (facets) posent exactement la même
        // requête. Elle n'est faite qu'une fois par objet (15 ms de moins sur /lines).
        $memo = $builder->toSql().'|'.serialize($builder->getBindings()).'|'.$eraId;

        return $this->stateCounts[$memo] ??= $this->countStates($builder, $eraId);
    }

    /**
     * @param  Builder<Line>  $builder
     * @return array{total: int, open: int, closed: int, unknown: int}
     */
    private function countStates(Builder $builder, ?int $eraId): array
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
