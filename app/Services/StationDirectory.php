<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Models\Era;
use App\Support\FrenchDepartments;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Annuaire des gares : filtres, statut affiché et compteurs, communs à la page /stations
 * (StationController::index), à l'API REST (GET /api/v1/stations), à GraphQL et au serveur MCP.
 * Lecture seule.
 *
 * Filtres (déjà validés) : `q` (nom, commune, autre nom, début du code UIC), `department` (code
 * INSEE), `region` (code INSEE de la région : gares de ses départements), `era` (année d'une ère : statut de la gare à cette ère, gares absentes de l'ère exclues),
 * `flagged` (position à vérifier), `status` (statut affiché, cf. STATUSES).
 *
 * Les points techniques (postes, garages, points d'une LGV) et les fiches exclues du réseau ferré
 * par une correction sourcée (tramway, petit train d'un zoo) ne sont jamais des gares de l'annuaire.
 */
final class StationDirectory
{
    use ReadsListFilters;

    /**
     * Statuts affichés : `active` (voyageurs), `tourist` (train touristique), `no_commercial` (plus de
     * voyageurs), `declassified` (fermée), `gone` (disparue selon ses sources), `planned` (projetée,
     * jamais ouverte). À une ère, seuls les quatre premiers existent.
     */
    public const STATUSES = ['active', 'tourist', 'no_commercial', 'declassified', 'gone', 'planned'];

    /** Ère d'une année (null : pas de filtre d'ère, ou année sans ère). */
    public function era(?int $year): ?Era
    {
        return $year !== null ? Era::where('year', $year)->first(['id', 'year', 'is_current']) : null;
    }

    /**
     * Requête de base sur `stations as s` (jointure `station_eras as se` à l'ère demandée), tous
     * filtres appliqués sauf le statut.
     *
     * @param  array<string, mixed>  $filters
     */
    public function base(array $filters, ?Era $era): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $dept = trim((string) ($filters['department'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));

        return DB::table('stations as s')
            ->when($era, fn ($b) => $b->join('station_eras as se', fn ($j) => $j->on('se.station_id', '=', 's.id')->where('se.era_id', $era->id)))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('s.name', 'ilike', $this->containsPattern($q))
                ->orWhere('s.city', 'ilike', $this->containsPattern($q))->orWhere('s.uic_code', 'like', $this->prefixPattern($q))
                ->orWhereRaw('s.alt_names::text ILIKE ?', [$this->containsPattern($q)])))
            ->when($dept !== '', fn ($b) => $b->where('s.department_code', $dept))
            ->when($region !== '', fn ($b) => $b->whereIn('s.department_code', FrenchDepartments::inRegion($region)))
            ->when(filter_var($filters['flagged'] ?? false, FILTER_VALIDATE_BOOL), fn ($b) => $b->where('s.position_flagged', true))
            ->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')");
    }

    /**
     * Expression SQL du statut affiché : celui de l'ère demandée, sinon le statut actuel (gare
     * projetée jamais ouverte : « planned », jamais « fermée » ni « disparue »).
     */
    public function statusExpression(?Era $era): string
    {
        return $era ? 'se.status' : "CASE WHEN s.provenance->>'kind' = 'projected' THEN 'planned' WHEN s.source_status = 'gone' THEN 'gone' ELSE s.status END";
    }

    /** Filtre de statut (statut inconnu : sans effet). */
    public function whereStatus(Builder $builder, ?string $status, ?Era $era): Builder
    {
        return $builder->when(in_array($status, self::STATUSES, true),
            fn ($b) => $b->whereRaw($this->statusExpression($era).' = ?', [$status]));
    }

    /**
     * Nombre de gares par statut affiché, sur la requête de base (sans filtre de statut).
     *
     * @return array<string, int>
     */
    public function counts(Builder $base, ?Era $era): array
    {
        $expr = $this->statusExpression($era);

        return array_map('intval', (clone $base)->selectRaw("{$expr} AS st, COUNT(*) AS n")->groupByRaw($expr)->pluck('n', 'st')->all());
    }

    /** Nombre total de gares de l'annuaire, sans filtre. */
    public function overall(): int
    {
        return DB::table('stations as s')->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')")->count();
    }

    /**
     * Lignes desservant chaque gare (codes en priorité), `limit` au plus par gare : de quoi distinguer
     * deux fiches de même nom dans la même commune.
     *
     * @param  array<int>  $ids
     * @return array<int, array{items: list<array{slug: string, code: ?string, name: string}>, more: int}>
     */
    public function lines(array $ids, int $limit = 3): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = DB::table('station_lines as sl')
            ->join('lines as l', 'l.id', '=', 'sl.line_id')
            ->whereIn('sl.station_id', $ids)
            ->orderByRaw('l.code IS NULL, l.code, l.name')
            ->get(['sl.station_id', 'l.slug', 'l.code', 'l.name']);

        $out = [];
        foreach ($rows->groupBy('station_id') as $stationId => $group) {
            $out[(int) $stationId] = [
                'items' => $group->take($limit)->map(fn ($r) => ['slug' => $r->slug, 'code' => $r->code, 'name' => $r->name])->values()->all(),
                'more' => max(0, $group->count() - $limit),
            ];
        }

        return $out;
    }
}
