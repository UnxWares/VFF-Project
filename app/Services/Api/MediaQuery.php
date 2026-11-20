<?php

namespace App\Services\Api;

use App\Enums\MediaType;
use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Media;
use App\Models\Station;
use App\Support\QueryBoolean;
use App\Support\RangeRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Filtres de la liste des médias, communs à l'API REST (`GET /api/v1/media`) et à GraphQL (`media`).
 */
final class MediaQuery
{
    /** Tranches d'époque proposées comme filtres (bornes incluses), aussi pour `lines/{line}/media`. */
    public const PERIODS = [
        'pre1900' => [null, 1899],
        '1900_1944' => [1900, 1944],
        '1945_1969' => [1945, 1969],
        '1970_1999' => [1970, 1999],
        'since2000' => [2000, null],
    ];

    public const RIGHTS = [Media::RIGHTS_PUBLIC_DOMAIN, Media::RIGHTS_OPEN_LICENSE, Media::RIGHTS_PERMISSION, Media::RIGHTS_UNKNOWN];

    /** Position du média (`lat`, `lng`), à sélectionner avec `media.*`. */
    public const POSITION = 'ST_Y(media.location) AS lat, ST_X(media.location) AS lng';

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'line' => ['nullable', 'string', 'max:255', 'exists:lines,slug', 'prohibits:station,material,event'],
            'station' => ['nullable', 'string', 'max:255', 'exists:stations,slug', 'prohibits:line,material,event'],
            'material' => ['nullable', 'string', 'max:255', 'exists:materials,slug', 'prohibits:line,station,event'],
            'event' => ['nullable', 'integer', 'min:1', 'max:999999999999999999', 'exists:line_events,id', 'prohibits:line,station,material'],
            'type' => ['nullable', Rule::enum(MediaType::class)],
            'provider' => ['nullable', 'string', 'max:20'],
            'license' => ['nullable', 'string', 'max:191'],
            'rights_status' => ['nullable', Rule::in(self::RIGHTS)],
            'commercial_ok' => ['nullable', Rule::in([true, false, 1, 0, '1', '0'])],
            'period' => ['nullable', Rule::in([...array_keys(self::PERIODS), 'undated'])],
            'from_year' => ['nullable', 'integer', 'min:1', 'max:2100'],
            'to_year' => ['nullable', 'integer', 'min:1', 'max:2100', RangeRule::atLeast('from_year')],
            'featured' => ['nullable', 'boolean'],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Forme canonique : `true` et `false` en chaînes deviennent des booléens.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function normalize(array $filters): array
    {
        if (isset($filters['commercial_ok']) && is_string($filters['commercial_ok'])) {
            $filters['commercial_ok'] = match (strtolower($filters['commercial_ok'])) {
                'true' => true,
                'false' => false,
                default => $filters['commercial_ok'],
            };
        }

        return QueryBoolean::castKeys($filters, ['featured']);
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
     * Applique des filtres déjà validés ; ordre des identifiants (curseur stable, la date représentée
     * étant souvent absente). Ajoute la position `lat` / `lng` du média.
     *
     * @param  Builder<Media>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Media>
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $query->select('media.*')->selectRaw(self::POSITION);

        if (filled($filters['line'] ?? null)) {
            $query->where('media.mediable_type', Line::class)
                ->whereIn('media.mediable_id', Line::query()->select('id')->where('slug', $filters['line']));
        }
        if (filled($filters['station'] ?? null)) {
            $query->where('media.mediable_type', Station::class)
                ->whereIn('media.mediable_id', Station::query()->select('id')->where('slug', $filters['station']));
        }
        if (filled($filters['material'] ?? null)) {
            $query->where('media.mediable_type', Material::class)
                ->whereIn('media.mediable_id', Material::query()->select('id')->where('slug', $filters['material']));
        }
        if (filled($filters['event'] ?? null)) {
            $query->where('media.mediable_type', LineEvent::class)->where('media.mediable_id', (int) $filters['event']);
        }
        foreach (['type', 'provider', 'rights_status'] as $column) {
            if (filled($filters[$column] ?? null)) {
                $query->where("media.{$column}", $filters[$column]);
            }
        }
        if (filled($filters['license'] ?? null)) {
            $query->whereRaw('lower(media.license) = ?', [mb_strtolower((string) $filters['license'])]);
        }
        if (isset($filters['commercial_ok'])) {
            $query->where('media.commercial_ok', filter_var($filters['commercial_ok'], FILTER_VALIDATE_BOOLEAN));
        }
        if (filled($filters['period'] ?? null)) {
            $this->wherePeriod($query, (string) $filters['period']);
        }
        if (filled($filters['from_year'] ?? null)) {
            $query->where('media.depicted_year', '>=', (int) $filters['from_year']);
        }
        if (filled($filters['to_year'] ?? null)) {
            $query->where('media.depicted_year', '<=', (int) $filters['to_year']);
        }
        if (isset($filters['featured'])) {
            $query->where('media.is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        $direction = ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('media.id', $direction);
    }

    /** Auteurs donnés par les compteurs (les plus représentés), comme la page /credits/media. */
    public const FACET_AUTHORS = 30;

    /**
     * Compteurs des médias filtrés (ceux de la page /credits/media) : `total`, `types` (nombre par
     * nature), `licenses` (nombre par licence déclarée, `null` = inconnue, les plus fréquentes
     * d'abord) et `authors` (les FACET_AUTHORS auteurs les plus représentés).
     *
     * @param  array<string, mixed>  $filters  filtres validés
     * @return array{total: int, types: array<string, int>, licenses: list<array{license: ?string, count: int}>, authors: list<array{author: string, count: int}>}
     */
    public function facets(array $filters): array
    {
        $base = fn (): QueryBuilder => $this->apply(Media::query(), $filters)->reorder()->toBase();

        return [
            'total' => (int) $base()->select(DB::raw('COUNT(*) AS n'))->value('n'),
            'types' => array_map('intval', $base()->select(DB::raw('media.type AS k, COUNT(*) AS n'))
                ->groupBy('media.type')->orderBy('media.type')->pluck('n', 'k')->all()),
            'licenses' => $base()->select(DB::raw('media.license AS license, COUNT(*) AS n'))
                ->groupBy('media.license')->orderByDesc('n')->orderBy('media.license')->get()
                ->map(fn (object $r): array => ['license' => $r->license, 'count' => (int) $r->n])->all(),
            'authors' => $base()->select(DB::raw('media.author AS author, COUNT(*) AS n'))->whereNotNull('media.author')
                ->groupBy('media.author')->orderByDesc('n')->orderBy('media.author')->limit(self::FACET_AUTHORS)->get()
                ->map(fn (object $r): array => ['author' => (string) $r->author, 'count' => (int) $r->n])->all(),
        ];
    }

    /**
     * Médias d'une ligne (galerie de la fiche, `GET /api/v1/lines/{line}/media`, images des aperçus de
     * partage), sans ordre : à compléter par des filtres puis par galleryOrder().
     *
     * @return Builder<Media>
     */
    public function forLine(int $lineId): Builder
    {
        return Media::query()
            ->where('mediable_type', Line::class)
            ->where('mediable_id', $lineId);
    }

    /**
     * Médias d'une fiche quelconque (ligne, gare, série de matériel, événement), sans ordre : base de
     * MediaGallery, à compléter comme forLine().
     *
     * @return Builder<Media>
     */
    public function forSubject(Model $subject): Builder
    {
        return Media::query()
            ->where('mediable_type', $subject::class)
            ->where('mediable_id', $subject->getKey());
    }

    /**
     * Ordre de la galerie d'une ligne : `oldest` (époque représentée la plus ancienne d'abord, défaut),
     * `newest` ou `featured` (mis en avant d'abord) ; médias non datés en dernier, puis par identifiant
     * (ordre stable d'une page à l'autre).
     *
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public function galleryOrder(Builder $query, string $sort = 'oldest'): Builder
    {
        match ($sort) {
            'newest' => $query->orderByRaw('depicted_year DESC NULLS LAST'),
            'featured' => $query->orderByDesc('is_featured')->orderByRaw('depicted_year ASC NULLS LAST'),
            default => $query->orderByRaw('depicted_year ASC NULLS LAST'),
        };

        return $query->orderBy('id');
    }

    /**
     * Restreint à une tranche d'époque représentée (`undated` : année inconnue).
     *
     * @param  Builder<Media>  $query
     */
    public function wherePeriod(Builder $query, string $period): void
    {
        if ($period === 'undated') {
            $query->whereNull('media.depicted_year');

            return;
        }
        [$from, $to] = self::PERIODS[$period];
        $query->whereNotNull('media.depicted_year');
        if ($from !== null) {
            $query->where('media.depicted_year', '>=', $from);
        }
        if ($to !== null) {
            $query->where('media.depicted_year', '<=', $to);
        }
    }
}
