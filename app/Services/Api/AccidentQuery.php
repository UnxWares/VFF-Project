<?php

namespace App\Services\Api;

use App\Enums\AccidentKind;
use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
use App\Support\FrenchDepartments;
use App\Support\RangeRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Filtres et tris de la liste des accidents, communs à l'API REST (`GET /api/v1/accidents`), à
 * GraphQL (`accidents(filter: …)`) et à la page /accidents : mêmes règles, même requête.
 */
final class AccidentQuery
{
    /** Tris proposés : clé publique => [expression, sens]. Bilan inconnu toujours en dernier. */
    public const SORTS = [
        'date' => ['accidents.occurred_on', 'asc'],
        '-date' => ['accidents.occurred_on', 'desc'],
        'deaths' => ['accidents.deaths', 'asc'],
        '-deaths' => ['accidents.deaths', 'desc'],
    ];

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'integer', 'between:1800,2100'],
            'to' => ['nullable', 'integer', 'between:1800,2100', RangeRule::atLeast('from')],
            'line' => ['nullable', 'string', 'max:255', 'exists:lines,slug'],
            'department' => ['nullable', 'string', 'regex:/^(?:\d{2,3}|2[ABab])$/'],
            'region' => ['nullable', Rule::in(array_keys(FrenchDepartments::REGIONS))],
            'station' => ['nullable', 'string', 'max:255', 'exists:stations,slug'],
            'kind' => ['nullable', Rule::enum(AccidentKind::class)],
            'review' => ['nullable', Rule::enum(AccidentReviewStatus::class)],
            'min_deaths' => ['nullable', 'integer', 'min:0'],
            'has_line' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ];
    }

    /**
     * Validation des filtres venant de GraphQL ou de la page (l'API REST passe par son Form Request).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function validate(array $filters): array
    {
        return Validator::make($filters, self::rules())->validate();
    }

    /**
     * @param  Builder<Accident>  $query
     * @param  array<string, mixed>  $filters  filtres validés
     * @return Builder<Accident>
     */
    public function apply(Builder $query, array $filters): Builder
    {
        if (filled($filters['q'] ?? null)) {
            $like = '%'.addcslashes(trim((string) $filters['q']), '\\%_').'%';
            // Nom dans l'une des trois langues (pas le texte du JSON : « fr » y est une clé), commune, ou
            // identifiant, qui commence par la date (« 1891 », « 2013-07 » trouvent leurs accidents).
            $query->where(fn (Builder $q) => $q
                ->whereRaw("unaccent(COALESCE(accidents.name->>'fr', '')) ILIKE unaccent(?)", [$like])
                ->orWhereRaw("unaccent(COALESCE(accidents.name->>'en', '')) ILIKE unaccent(?)", [$like])
                ->orWhereRaw("unaccent(COALESCE(accidents.name->>'de', '')) ILIKE unaccent(?)", [$like])
                ->orWhereRaw("unaccent(COALESCE(accidents.commune_name, '')) ILIKE unaccent(?)", [$like])
                ->orWhereRaw('unaccent(accidents.slug) ILIKE unaccent(?)', [$like]));
        }
        $from = isset($filters['from']) ? (int) $filters['from'] : null;
        $to = isset($filters['to']) ? (int) $filters['to'] : null;
        $query->betweenYears($from, $to);
        if (filled($filters['line'] ?? null)) {
            $query->onLine((int) DB::table('lines')->where('slug', $filters['line'])->value('id'));
        }
        if (filled($filters['department'] ?? null)) {
            $query->where('accidents.department_code', strtoupper((string) $filters['department']));
        }
        if (filled($filters['region'] ?? null)) {
            $query->whereIn('accidents.department_code', FrenchDepartments::inRegion((string) $filters['region']));
        }
        if (filled($filters['station'] ?? null)) {
            // Accidents dont cette gare est la gare la plus proche du lieu (10 km au plus, vff:import-accidents).
            $query->where('accidents.nearest_station_id', DB::table('stations')->where('slug', $filters['station'])->value('id'));
        }
        if (filled($filters['kind'] ?? null)) {
            $query->where('accidents.kind', $filters['kind']);
        }
        if (filled($filters['review'] ?? null)) {
            $query->where('accidents.review_status', $filters['review']);
        }
        if (isset($filters['min_deaths'])) {
            $query->where('accidents.deaths', '>=', (int) $filters['min_deaths']);
        }
        if (isset($filters['has_line'])) {
            $exists = fn ($q) => $q->selectRaw('1')->from('accident_lines')->whereColumn('accident_lines.accident_id', 'accidents.id');
            filter_var($filters['has_line'], FILTER_VALIDATE_BOOLEAN) ? $query->whereExists($exists) : $query->whereNotExists($exists);
        }

        [$column, $direction] = self::SORTS[$filters['sort'] ?? 'date'];

        return $query->orderByRaw("{$column} {$direction} NULLS LAST")
            ->orderBy('accidents.occurred_on')
            ->orderBy('accidents.id');
    }

    /** Colonnes que porte un curseur de la liste des accidents (contrôle PaginationCursor). */
    public const CURSOR_COLUMNS = ['sort_null', 'sort_key', 'occurred_on', 'id'];

    /**
     * Liste filtrée et triée, prête pour `cursorPaginate` (serveur MCP) : même ordre que apply()
     * (bilan inconnu en dernier, puis date, puis identifiant), exprimé par des colonnes calculées
     * (CURSOR_COLUMNS) que le curseur compare comme de simples colonnes. Les lat/lng sont ajoutées.
     *
     * @param  array<string, mixed>  $filters  filtres validés
     * @return Builder<Accident>
     */
    public function cursorQuery(array $filters): Builder
    {
        $sort = (string) ($filters['sort'] ?? 'date');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        [$key, $null] = str_ends_with($sort, 'deaths')
            ? ['COALESCE(a.deaths, 0)', '(a.deaths IS NULL)::int']
            : ['a.occurred_on', '0'];

        $filtered = $this->apply(Accident::query(), $filters)->reorder()->toBase();
        $keyed = DB::query()->fromSub($filtered, 'a')->select('a.*')
            ->selectRaw("{$null} AS sort_null, {$key} AS sort_key");

        return Accident::query()->fromSub($keyed, 'accidents')->withCoordinates()
            ->orderBy('sort_null')
            ->orderBy('sort_key', $direction)
            ->orderBy('occurred_on')
            ->orderBy('id');
    }
}
