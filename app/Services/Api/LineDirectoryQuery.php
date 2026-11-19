<?php

namespace App\Services\Api;

use App\Models\Line;
use App\Services\LineDirectory;
use App\Support\FrenchDepartments;
use App\Support\QueryBoolean;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Annuaire des lignes de l'API publique, commun au REST (`GET /api/v1/lines`), à GraphQL
 * (`lineDirectory`) et au serveur MCP (`list_lines`) : mêmes règles de validation, mêmes filtres
 * (LineDirectory, ceux de la page /lines), même tri paginable par curseur (SortedCursor).
 */
final class LineDirectoryQuery
{
    /**
     * Tris : `name` (ordre alphabétique français), `code` (code SNCF Réseau ; lignes sans code en
     * dernier), `slug`.
     */
    public const SORTS = ['name', 'code', 'slug'];

    private const SORT_KEYS = [
        'name' => ['lines.name COLLATE "fr-x-icu"', '0'],
        'code' => ['COALESCE(lines.code, \'\') COLLATE "C"', '(lines.code IS NULL)::int'],
        'slug' => ['lines.slug COLLATE "C"', '0'],
    ];

    public function __construct(private readonly LineDirectory $directory) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'network' => ['nullable', Rule::in([...LineDirectory::NETWORKS, 'none'])],
            'traffic' => ['nullable', Rule::in(LineDirectory::TRAFFICS)],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'heritage' => ['nullable', 'boolean'],
            'department' => ['nullable', Rule::in(array_keys(FrenchDepartments::ALL))],
            'region' => ['nullable', Rule::in(array_keys(FrenchDepartments::REGIONS))],
            'era' => ['nullable', 'integer', 'exists:eras,year'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Forme canonique : département en majuscules (`2a` = `2A`), booléen écrit `true`/`false`.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function normalize(array $filters): array
    {
        if (isset($filters['department']) && is_string($filters['department'])) {
            $filters['department'] = strtoupper(trim($filters['department']));
        }

        return QueryBoolean::castKeys($filters, ['heritage']);
    }

    /**
     * Validation des filtres venant de GraphQL ou du serveur MCP (le REST passe par son Form Request).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $filters): array
    {
        return Validator::make(self::normalize($filters), self::rules())->validate();
    }

    /** Identifiant de l'ère de référence des filtres `status` et `traffic` et de l'état renvoyé. */
    public function eraId(array $filters): ?int
    {
        return $this->directory->eraId(isset($filters['era']) ? (int) $filters['era'] : null);
    }

    /**
     * Requête triée, prête pour `cursorPaginate` (colonnes SortedCursor::COLUMNS).
     *
     * @param  array<string, mixed>  $filters  filtres validés
     */
    public function query(array $filters): Builder
    {
        [$key, $null] = self::SORT_KEYS[$filters['sort'] ?? 'name'] ?? self::SORT_KEYS['name'];
        $inner = $this->directory->apply(Line::query(), $filters, $this->eraId($filters))
            ->select(['lines.id', 'lines.slug', 'lines.code', 'lines.name', 'lines.network', 'lines.historical_operator',
                'lines.regions', 'lines.departements'])
            ->selectRaw("{$null} AS sort_null, {$key} AS sort_key")
            ->toBase();

        return SortedCursor::wrap($inner, (string) ($filters['order'] ?? 'asc'));
    }

    /**
     * Lignes d'une page avec leur état à l'ère de référence.
     *
     * @param  iterable<object>  $rows  lignes brutes de query()
     * @return list<array<string, mixed>>
     */
    public function present(iterable $rows, array $filters): array
    {
        $rows = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);
        $ids = array_map(fn (object $r): int => (int) $r->id, $rows);
        $eraId = $this->eraId($filters);
        $summaries = $this->directory->summaries($ids, $eraId);

        return array_map(fn (object $l): array => [
            'slug' => $l->slug,
            'code' => $l->code,
            'name' => $l->name,
            'network' => $l->network,
            'historical_operator' => $l->historical_operator,
            'regions' => self::jsonList($l->regions),
            'departements' => self::jsonList($l->departements),
            'state' => $summaries[(int) $l->id]['state'] ?? 'unknown',
            'traffic' => $summaries[(int) $l->id]['traffic'] ?? null,
            'heritage' => $summaries[(int) $l->id]['heritage'] ?? false,
        ], $rows);
    }

    /**
     * Compteurs de l'annuaire filtré (LineDirectory::facets).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function facets(array $filters): array
    {
        $facets = $this->directory->facets($filters, $this->eraId($filters));
        // Objet JSON même vide (`{}`), comme les autres dictionnaires de compteurs.
        $facets['departments'] = (object) $facets['departments'];

        return $facets;
    }

    /** @return list<string> */
    private static function jsonList(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
