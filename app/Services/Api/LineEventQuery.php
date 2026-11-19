<?php

namespace App\Services\Api;

use App\Enums\LineEventType;
use App\Models\Line;
use App\Models\LineEvent;
use App\Rules\PartialDate;
use App\Services\LineDirectory;
use App\Support\FrenchDepartments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Filtres de la liste des événements de lignes, communs à l'API REST (`GET /api/v1/events`) et à
 * GraphQL (`lineEvents`) : mêmes règles de validation, même construction de la requête.
 */
final class LineEventQuery
{
    /** Colonnes lues : sans la géométrie de portion, lourde et hors contrat de l'API. */
    public const COLUMNS = [
        'id', 'line_id', 'type', 'event_date', 'date_precision', 'description', 'source', 'source_url', 'source_quote',
        'confidence', 'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'section_length_m',
        'section_resolution', 'reuse', 'heritage_kind',
    ];

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'line' => ['nullable', 'string', 'max:255', 'exists:lines,slug'],
            'type' => ['nullable', Rule::enum(LineEventType::class)],
            'source' => ['nullable', 'string', 'max:64'],
            'department' => ['nullable', Rule::in(array_keys(FrenchDepartments::ALL))],
            'from' => ['nullable', new PartialDate],
            'to' => ['nullable', new PartialDate],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Met les filtres sous leur forme canonique (code de département en majuscules : `2a` = `2A`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function normalize(array $filters): array
    {
        if (isset($filters['department']) && is_string($filters['department'])) {
            $filters['department'] = strtoupper(trim($filters['department']));
        }

        return $filters;
    }

    /**
     * Validation des filtres venant de GraphQL (l'API REST passe par son Form Request).
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

    /**
     * Applique des filtres déjà validés : ordre chronologique (puis identifiant, pour un curseur stable).
     *
     * `department` : lignes dont la fiche cite ce département, ou qui y desservent une gare ; l'événement
     * est rattaché à la ligne entière, pas à la seule portion qui traverse le département.
     *
     * @template TBuilder of Builder<LineEvent>
     *
     * @param  TBuilder  $query
     * @param  array<string, mixed>  $filters
     * @return TBuilder
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->select(self::COLUMNS);

        if (filled($filters['line'] ?? null)) {
            $query->whereIn('line_events.line_id', Line::query()->select('id')->where('slug', $filters['line']));
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('line_events.type', $filters['type']);
        }
        if (filled($filters['source'] ?? null)) {
            $query->where('line_events.source', $filters['source']);
        }
        if (filled($filters['from'] ?? null)) {
            $query->where('line_events.event_date', '>=', PartialDate::bound($filters['from'], false));
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('line_events.event_date', '<=', PartialDate::bound($filters['to'], true));
        }
        if (filled($filters['department'] ?? null)) {
            $query->whereIn('line_events.line_id', LineDirectory::idsInDepartment((string) $filters['department']));
        }

        $direction = ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('line_events.event_date', $direction)->orderBy('line_events.id', $direction);
    }
}
