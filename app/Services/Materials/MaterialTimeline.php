<?php

namespace App\Services\Materials;

use App\Models\Material;
use Illuminate\Support\Facades\Cache;

/**
 * Frise du matériel roulant : une entrée par série, avec ses deux années de service, l'état déclaré par une
 * source et son nombre d'exemplaires, regroupées par traction. Rien n'est estimé : une année absente reste
 * nulle (la série est alors listée « sans date », jamais placée au hasard sur la frise).
 *
 * Une seule lecture de la table, gardée en cache (la liste des séries ne change qu'à l'import).
 */
final class MaterialTimeline
{
    /**
     * Traction => catégories de la base, dans l'ordre d'affichage de la frise. Les rames automotrices et
     * bimodes sont réunies (« automoteur »), les voitures et les wagons aussi.
     */
    public const GROUPS = [
        'vapeur' => ['locomotive_vapeur'],
        'electrique' => ['locomotive_electrique'],
        'diesel' => ['locomotive_diesel'],
        'autorail' => ['autorail'],
        'automoteur' => ['automotrice', 'bimode'],
        'tgv' => ['rame_tgv'],
        'tramway' => ['tramway'],
        'voitures_wagons' => ['voiture', 'wagon'],
        'service' => ['engin_service'],
    ];

    public const CACHE_TTL = 600;

    /** Traction d'une catégorie de la base (`locomotive_vapeur` donne `vapeur`). */
    public static function groupOf(string $type): string
    {
        foreach (self::GROUPS as $group => $types) {
            if (in_array($type, $types, true)) {
                return $group;
            }
        }

        return 'service';
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function all(): array
    {
        $state = Material::query()->toBase()->selectRaw('count(*) AS n, max(updated_at) AS u')->first();

        return Cache::remember('material-timeline:'.md5($state->n.'|'.$state->u.'|'.now()->year), self::CACHE_TTL, fn (): array => $this->build());
    }

    /**
     * @param  array{group?: string|null, builder?: string|null, in_service_at?: int|null}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function filtered(array $filters): array
    {
        $all = $this->all();
        $group = $filters['group'] ?? null;
        $builder = self::fold((string) ($filters['builder'] ?? ''));
        $year = isset($filters['in_service_at']) ? (int) $filters['in_service_at'] : null;

        $rows = array_values(array_filter($all['data'], static function (array $row) use ($group, $builder, $year): bool {
            if ($group && $row['group'] !== $group) {
                return false;
            }
            if ($builder !== '' && ! str_contains(self::fold(implode(' ', $row['builders'])), $builder)) {
                return false;
            }

            return $year === null || in_array(self::stateIn($row, $year), ['yes', 'maybe'], true);
        }));
        if ($year !== null) {
            $rows = array_map(static fn (array $row): array => $row + ['in_service_state' => self::stateIn($row, $year)], $rows);
        }

        return ['data' => $rows, 'meta' => $this->meta($rows, $all['meta']['current_year'])];
    }

    /**
     * La série circulait-elle cette année-là ? `yes` : dates connues qui l'encadrent, ou mise en service
     * passée d'une série déclarée en service ; `maybe` : mise en service passée mais fin non renseignée
     * (aucune source ne dit si elle circulait encore) ; `no` : avant la mise en service, après le retrait,
     * ou sans date de mise en service.
     *
     * @param  array<string, mixed>  $row
     */
    public static function stateIn(array $row, int $year): string
    {
        $from = $row['in_service_from'];
        $to = $row['in_service_to'];
        if ($from === null || $year < $from) {
            return 'no';
        }
        if ($to !== null) {
            return $year <= $to ? 'yes' : 'no';
        }

        return $row['service_status'] === 'in_service' ? 'yes' : 'maybe';
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    private function build(): array
    {
        $rows = Material::query()
            ->select(['slug', 'name', 'type', 'builder', 'builders', 'in_service_from', 'in_service_to', 'service_status', 'units_built'])
            ->orderBy('name')
            ->get()
            ->map(function (Material $m): array {
                $builders = $m->builderNames();
                if ($builders === [] && filled($m->builder)) {
                    $builders = [(string) $m->builder];
                }

                return [
                    'slug' => $m->slug,
                    'name' => $m->name,
                    'type' => $m->getRawOriginal('type'),
                    'group' => self::groupOf((string) $m->getRawOriginal('type')),
                    'builders' => $builders,
                    'in_service_from' => $m->in_service_from,
                    'in_service_to' => $m->in_service_to,
                    'service_status' => $m->service_status,
                    'units_built' => $m->units_built,
                ];
            })
            ->all();

        return ['data' => $rows, 'meta' => $this->meta($rows, now()->year)];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function meta(array $rows, int $currentYear): array
    {
        $dated = array_values(array_filter($rows, static fn (array $r): bool => $r['in_service_from'] !== null));

        return [
            'total' => count($rows),
            'dated' => count($dated),
            'undated' => count($rows) - count($dated),
            'first_year' => $dated === [] ? null : min(array_column($dated, 'in_service_from')),
            'current_year' => $currentYear,
        ];
    }

    private static function fold(string $text): string
    {
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);

        return mb_strtolower(trim((string) preg_replace('/\p{Mn}+/u', '', $decomposed === false ? $text : $decomposed)));
    }
}
