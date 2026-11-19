<?php

namespace App\Services\Api;

use App\Enums\AccidentKind;
use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
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
            'to' => ['nullable', 'integer', 'between:1800,2100', 'gte:from'],
            'line' => ['nullable', 'string', 'max:255', 'exists:lines,slug'],
            'department' => ['nullable', 'string', 'regex:/^(?:\d{2,3}|2[ABab])$/'],
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
            $query->where(fn (Builder $q) => $q
                ->whereRaw('unaccent(accidents.name::text) ILIKE unaccent(?)', [$like])
                ->orWhereRaw("unaccent(COALESCE(accidents.commune_name, '')) ILIKE unaccent(?)", [$like]));
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
}
