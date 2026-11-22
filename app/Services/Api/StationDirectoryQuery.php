<?php

namespace App\Services\Api;

use App\Models\Era;
use App\Services\StationDirectory;
use App\Services\Territories;
use App\Support\FrenchDepartments;
use App\Support\QueryBoolean;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Annuaire des gares de l'API publique, commun au REST (`GET /api/v1/stations`), à GraphQL
 * (`stationDirectory`) et au serveur MCP (`list_stations`) : mêmes règles, mêmes filtres
 * (StationDirectory, ceux de la page /stations), même tri paginable par curseur (SortedCursor).
 */
final class StationDirectoryQuery
{
    /**
     * Tris : `name` (alphabétique français), `city` (commune), `opened` (date d'ouverture), `closed`
     * (date de fermeture). Les gares sans valeur pour la clé de tri viennent toujours en dernier.
     */
    public const SORTS = ['name', 'city', 'opened', 'closed'];

    private const SORT_KEYS = [
        'name' => ['s.name COLLATE "fr-x-icu"', '0'],
        'city' => ['COALESCE(s.city, \'\') COLLATE "fr-x-icu"', '(s.city IS NULL)::int'],
        'opened' => ['COALESCE(s.opened_at, DATE \'0001-01-01\')', '(s.opened_at IS NULL)::int'],
        'closed' => ['COALESCE(s.closed_at, DATE \'0001-01-01\')', '(s.closed_at IS NULL)::int'],
    ];

    /** Lignes données par gare dans la liste (le reste est compté dans `lines_more`). */
    public const LINES_PER_STATION = 10;

    public function __construct(private readonly StationDirectory $directory) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(StationDirectory::STATUSES)],
            'department' => ['nullable', Rule::in(array_keys(FrenchDepartments::ALL))],
            'region' => ['nullable', Rule::in(array_keys(FrenchDepartments::REGIONS))],
            'era' => ['nullable', 'integer', 'exists:eras,year'],
            'flagged' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function normalize(array $filters): array
    {
        if (isset($filters['department']) && is_string($filters['department'])) {
            $filters['department'] = strtoupper(trim($filters['department']));
        }

        return QueryBoolean::castKeys($filters, ['flagged']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $filters): array
    {
        return Validator::make(self::normalize($filters), self::rules())->validate();
    }

    /** @param  array<string, mixed>  $filters */
    public function era(array $filters): ?Era
    {
        return $this->directory->era(isset($filters['era']) ? (int) $filters['era'] : null);
    }

    /**
     * Requête triée, prête pour `cursorPaginate` (colonnes SortedCursor::COLUMNS).
     *
     * @param  array<string, mixed>  $filters  filtres validés
     */
    public function query(array $filters): Builder
    {
        $era = $this->era($filters);
        [$key, $null] = self::SORT_KEYS[$filters['sort'] ?? 'name'] ?? self::SORT_KEYS['name'];
        $status = $this->directory->statusExpression($era);
        $inner = $this->directory->whereStatus($this->directory->base($filters, $era), $filters['status'] ?? null, $era)
            ->selectRaw(<<<SQL
                s.id, s.slug, s.name, s.type, s.city, s.department_code, s.uic_code, s.wikidata_id, s.sources_count,
                s.position_flagged, {$status} AS status, s.opened_at, s.opened_precision, (s.provenance->>'opened_after')::int AS opened_after, s.closed_at, s.closed_precision,
                (s.provenance->>'closed_after')::int AS closed_after, ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat,
                {$null} AS sort_null, {$key} AS sort_key
            SQL);

        return SortedCursor::wrap($inner, (string) ($filters['order'] ?? 'asc'));
    }

    /**
     * Gares d'une page, avec les lignes qui les desservent.
     *
     * @param  iterable<object>  $rows  lignes brutes de query()
     * @return list<array<string, mixed>>
     */
    public function present(iterable $rows): array
    {
        $rows = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);
        $ids = array_map(fn (object $r): int => (int) $r->id, $rows);
        $lines = $this->directory->lines($ids, self::LINES_PER_STATION);

        return array_map(fn (object $s): array => [
            'slug' => $s->slug,
            'name' => $s->name,
            'type' => $s->type,
            'status' => $s->status,
            'city' => $s->city,
            'department_code' => $s->department_code,
            'uic_code' => $s->uic_code,
            'wikidata_id' => $s->wikidata_id,
            'opened_at' => $s->opened_at !== null ? substr((string) $s->opened_at, 0, 10) : null,
            'opened_precision' => $s->opened_at !== null ? ($s->opened_precision ?? 'day') : null,
            'opened_after' => $s->opened_at !== null && $s->opened_precision === 'range' && $s->opened_after !== null ? (int) $s->opened_after : null,
            'closed_at' => $s->closed_at !== null ? substr((string) $s->closed_at, 0, 10) : null,
            'closed_precision' => $s->closed_at !== null ? ($s->closed_precision ?? 'day') : null,
            'closed_after' => $s->closed_after !== null ? (int) $s->closed_after : null,
            'lat' => round((float) $s->lat, 7),
            'lng' => round((float) $s->lng, 7),
            'position_flagged' => (bool) $s->position_flagged,
            'sources_count' => (int) $s->sources_count,
            'lines' => $lines[(int) $s->id]['items'] ?? [],
            'lines_more' => $lines[(int) $s->id]['more'] ?? 0,
        ], $rows);
    }

    /**
     * Nombre de gares par statut affiché (filtres appliqués, sauf le statut).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function facets(array $filters): array
    {
        $era = $this->era($filters);

        return $this->directory->counts($this->directory->base($filters, $era), $era);
    }

    /**
     * Nombre de gares par département (filtres appliqués, statut compris, sauf le département),
     * départements sans gare absents.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function departmentFacets(array $filters): array
    {
        $era = $this->era($filters);
        $base = $this->directory->base(array_diff_key($filters, ['department' => true]), $era);

        return app(Territories::class)->stationCounts($this->directory->whereStatus($base, $filters['status'] ?? null, $era));
    }
}
