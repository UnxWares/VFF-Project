<?php

namespace App\GraphQL\Resolvers;

use App\Services\Territories;
use App\Support\FrenchDepartments;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Requêtes `departments`, `department`, `regions` et `region` : la liste de référence des
 * territoires de `GET /api/v1/departments` et `GET /api/v1/regions` (même service, mêmes compteurs).
 */
final class TerritoryResolver
{
    public function __construct(private readonly Territories $territories) {}

    /**
     * @param  array{region?: ?string}  $args
     * @return list<array<string, mixed>>
     */
    public function departments(mixed $root, array $args): array
    {
        $input = Validator::make($args, [
            'region' => ['nullable', Rule::in(array_keys(FrenchDepartments::REGIONS))],
        ])->validate();

        return $this->territories->departments(isset($input['region']) ? (string) $input['region'] : null);
    }

    /**
     * @param  array{code: string}  $args
     * @return array<string, mixed>|null
     */
    public function department(mixed $root, array $args): ?array
    {
        $code = strtoupper(trim($args['code']));

        foreach ($this->territories->departments() as $department) {
            if ($department['code'] === $code) {
                return $department;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function regions(): array
    {
        return $this->territories->regions();
    }

    /**
     * @param  array{code: string}  $args
     * @return array<string, mixed>|null
     */
    public function region(mixed $root, array $args): ?array
    {
        $code = trim($args['code']);

        foreach ($this->territories->regions() as $region) {
            if ($region['code'] === $code) {
                return $region;
            }
        }

        return null;
    }
}
