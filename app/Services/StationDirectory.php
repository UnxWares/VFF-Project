<?php

namespace App\Services;

use App\Models\Era;
use App\Services\Accessibility\StationAccessibility;
use App\Services\Heritage\StationHeritage;
use App\Support\FrenchDepartments;
use App\Support\Search\SearchFields;
use App\Support\Search\SearchQuery;
use App\Support\Search\SearchText;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Annuaire des gares : filtres, statut affiché et compteurs, communs à la page /stations
 * (StationController::index), à l'API REST (GET /api/v1/stations), à GraphQL et au serveur MCP.
 * Lecture seule.
 *
 * Filtres (déjà validés) : `q` (nom, commune, autre nom, début du code UIC ; recherche commune App\Support\Search :
 * tirets, accents, abréviations et petits mots indifférents, mots dans n'importe quel ordre, noms approchés si
 * rien ne correspond mot pour mot), `department` (code
 * INSEE), `region` (code INSEE de la région : gares de ses départements), `era` (année d'une ère : statut de la gare à cette ère, gares absentes de l'ère exclues),
 * `flagged` (position à vérifier), `heritage` (bâtiment ou ouvrage protégé : monument historique ou label ACR), `accessible` (gare
 * accessible au moins en partie aux personnes à mobilité réduite d'après SNCF Gares & Connexions : station_accessibility), `status` (statut affiché, cf. STATUSES).
 *
 * Les points techniques (postes, garages, points d'une LGV) et les fiches exclues du réseau ferré
 * par une correction sourcée (tramway, petit train d'un zoo) ne sont jamais des gares de l'annuaire.
 */
final class StationDirectory
{
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
     * @param  bool|null  $approximate  recherche approchée (null : décidée par approximate())
     */
    public function base(array $filters, ?Era $era, ?bool $approximate = null): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $search = $q !== '' ? SearchQuery::parse($q) : null;
        $dept = trim((string) ($filters['department'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));

        return DB::table('stations as s')
            ->when($era, fn ($b) => $b->join('station_eras as se', fn ($j) => $j->on('se.station_id', '=', 's.id')->where('se.era_id', $era->id)))
            ->when($search, fn ($b) => $search->where($b, self::searchKeys(), self::uicMatch($search), $approximate ?? $this->approximate($filters, $era)))
            ->when($dept !== '', fn ($b) => $b->where('s.department_code', $dept))
            ->when($region !== '', fn ($b) => $b->whereIn('s.department_code', FrenchDepartments::inRegion($region)))
            ->when(filter_var($filters['flagged'] ?? false, FILTER_VALIDATE_BOOL), fn ($b) => $b->where('s.position_flagged', true))
            ->when(filter_var($filters['heritage'] ?? false, FILTER_VALIDATE_BOOL), fn ($b) => StationHeritage::ready()
                ? $b->whereExists(fn ($e) => $e->selectRaw('1')->from('station_heritage as sh')->whereColumn('sh.station_id', 's.id')->whereIn('sh.protection', ['classified', 'registered', 'acr']))
                : $b->whereRaw('false'))
            ->when(filter_var($filters['accessible'] ?? false, FILTER_VALIDATE_BOOL), fn ($b) => StationAccessibility::ready()
                ? $b->whereExists(fn ($e) => $e->selectRaw('1')->from('station_accessibility as sa')->whereColumn('sa.station_id', 's.id')->where('sa.accessible', true))
                : $b->whereRaw('false'))
            ->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')");
    }

    /**
     * La recherche `q` ne trouve-t-elle rien mot pour mot (autres filtres compris) ? La liste montre alors les
     * gares au nom approché (fautes de frappe).
     *
     * @param  array<string, mixed>  $filters
     */
    public function approximate(array $filters, ?Era $era): bool
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return $q !== '' && SearchQuery::parse($q)->canBeFuzzy() && ! $this->base($filters, $era, false)->exists();
    }

    /**
     * Tri par pertinence de la recherche `q`, comme la recherche de l'en-tête (CatalogSearch) : rang du nom
     * (SearchQuery), la gare ouverte aux voyageurs de la commune cherchée au rang d'un nom exact, gares ouvertes aux
     * voyageurs, les plus fréquentées, les plus desservies, puis ordre alphabétique.
     */
    public function orderByRelevance(Builder $builder, string $q, bool $approximate): Builder
    {
        [$nameKey, $allKey] = self::searchKeys();
        $search = SearchQuery::parse($q);
        if ($search->digits() !== null) {
            $builder->orderByRaw('CASE WHEN s.uic_code = ? THEN 0 ELSE 1 END', [$search->digits()]);
        }
        if (! $approximate && ! $search->isEmpty()) {
            [$city, $cityBind] = $search->citySql($nameKey, SearchText::sqlKeyFormula('s.city', 'unaccent'));
            [$rank, $rankBind] = $search->rankSql($nameKey, $allKey);
            $builder->orderByRaw("CASE WHEN s.status = 'active' AND {$city} THEN 0 ELSE {$rank} END", [...$cityBind, ...$rankBind]);
        } else {
            $search->orderByRelevance($builder, $nameKey, $allKey, $approximate);
        }

        return $builder
            ->orderByRaw("CASE WHEN s.status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw(SearchFields::stationAttendance('s.').' DESC NULLS LAST')
            ->orderByRaw('(SELECT COUNT(*) FROM station_lines sl WHERE sl.station_id = s.id) DESC');
    }

    /** @return array{0: string, 1: string} */
    private static function searchKeys(): array
    {
        return SearchFields::keys('station', 's.');
    }

    /**
     * Début du code UIC (4 chiffres au moins).
     *
     * @return array{0: string, 1: list<string>}|null
     */
    private static function uicMatch(SearchQuery $search): ?array
    {
        $digits = $search->digits();

        return $digits !== null && strlen($digits) >= 4 ? ['s.uic_code LIKE ?', [$digits.'%']] : null;
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
