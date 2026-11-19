<?php

namespace App\Services\LevelCrossings;

use App\Enums\LevelCrossingGroup;
use App\Models\LevelCrossing;
use App\Models\Line;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Filtres et ordre des passages à niveau, partagés par l'API REST (`GET /api/v1/level-crossings`)
 * et le serveur MCP (outil `level_crossings`).
 */
final class LevelCrossingQuery
{
    /** Colonnes de l'ordre (code de ligne, PK, identifiant), comparées par le curseur du serveur MCP. */
    public const CURSOR_COLUMNS = ['code_ligne', 'pk_sort', 'id'];

    /** Emprise « ouest,sud,est,nord » en degrés WGS 84 (nombres décimaux, point comme séparateur). */
    private const BBOX_PATTERN = '/^-?\d{1,3}(\.\d+)?,-?\d{1,2}(\.\d+)?,-?\d{1,3}(\.\d+)?,-?\d{1,2}(\.\d+)?$/';

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'line' => ['nullable', 'string', 'max:255', 'exists:lines,slug'],
            'group' => ['nullable', Rule::enum(LevelCrossingGroup::class)],
            'class' => ['nullable', 'integer', Rule::in(LevelCrossingGroup::CLASSES)],
            'commune' => ['nullable', 'string', 'min:2', 'max:100'],
            'bbox' => ['bail', 'nullable', 'string', 'regex:'.self::BBOX_PATTERN, function (string $attribute, mixed $value, \Closure $fail): void {
                [$west, $south, $east, $north] = array_map('floatval', explode(',', (string) $value));
                if ($west >= $east || $south >= $north || $west < -180 || $east > 180 || $south < -90 || $north > 90) {
                    $fail(__('validation.custom.bbox.invalid'));
                }
            }],
        ];
    }

    /**
     * Validation des filtres venant de GraphQL (l'API REST passe par son Form Request).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function validate(array $filters): array
    {
        return Validator::make($filters, self::rules())->validate();
    }

    /**
     * Requête filtrée (avec la ligne), dans l'ordre de la ligne : code, PK (inconnus en dernier),
     * identifiant. `pk_sort` est une vraie colonne (sous-requête), que le curseur peut comparer.
     *
     * @param  array<string, mixed>  $filters  filtres validés
     * @return Builder<LevelCrossing>
     */
    public function query(array $filters): Builder
    {
        $inner = $this->apply(LevelCrossing::query(), $filters)
            ->select('sncf_level_crossings.*')
            ->selectRaw('COALESCE(pk_m, 2147483647) AS pk_sort');

        return LevelCrossing::query()
            ->fromSub($inner, 'sncf_level_crossings')
            ->with('line:id,slug,code,name')
            ->orderBy('code_ligne')->orderBy('pk_sort')->orderBy('id');
    }

    /**
     * Filtres seuls, sans ordre (requête GraphQL `levelCrossings`, qui trie à part).
     *
     * @param  Builder<LevelCrossing>  $query
     * @param  array<string, mixed>  $filters  filtres validés
     * @return Builder<LevelCrossing>
     */
    public function apply(Builder $query, array $filters): Builder
    {
        if (filled($filters['line'] ?? null)) {
            $line = Line::where('slug', $filters['line'])->first(['id', 'code']);
            // Ligne inconnue (rejetée en amont par la validation) : aucun PN.
            $line === null ? $query->whereRaw('1 = 0') : $query->ofLine($line->id, $line->code);
        }
        if (filled($filters['group'] ?? null)) {
            $classes = LevelCrossingGroup::from((string) $filters['group'])->classes();
            $query->whereIn('mnemo', array_map(self::mnemo(...), $classes));
        }
        if (isset($filters['class']) && $filters['class'] !== '') {
            $query->where('mnemo', self::mnemo((int) $filters['class']));
        }
        if (filled($filters['commune'] ?? null)) {
            $like = '%'.addcslashes(trim((string) $filters['commune']), '\\%_').'%';
            $query->whereRaw('unaccent(commune) ILIKE unaccent(?)', [$like]);
        }
        if (filled($filters['bbox'] ?? null)) {
            [$west, $south, $east, $north] = array_map('floatval', explode(',', (string) $filters['bbox']));
            $query->whereBetween('lon', [$west, $east])->whereBetween('lat', [$south, $north]);
        }

        return $query;
    }

    /** Libellé SNCF d'une classe (17 → « CLASSE 17 », 0 → « CLASSE 00 »). */
    private static function mnemo(int $class): string
    {
        return sprintf('CLASSE %02d', $class);
    }
}
