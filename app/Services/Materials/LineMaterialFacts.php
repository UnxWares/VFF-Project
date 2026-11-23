<?php

namespace App\Services\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use Illuminate\Support\Collection;

/**
 * Ce que la fiche de ligne dit de chaque série liée à la ligne, calculé une fois pour la fiche, l'API REST
 * (`GET /api/v1/lines/{line}/materials`), GraphQL (`LineMaterialLink`) et l'outil MCP `line_materials` :
 *
 * - `periods` : périodes sûres de tous les liens de la série, de la plus ancienne à la plus récente, au format
 *   de la fiche (LineMaterialPeriod::toSheet), bornées par l'électrification quand elles ne sont pas sourcées
 *   (ElectrificationBound::clampPeriods : une date sourcée ne se déplace jamais) ;
 * - `earliest_on_line` : borne « pas avant l'électrification » (`reason`, `date`, `precision`, `section`,
 *   `conflict` : une période sourcée commence avant la borne), nulle sans borne ;
 * - `link_doubt` : raison du doute quand toutes les preuves en portent un (LineMaterialDoubt), nulle pour une
 *   série qui a circulé ;
 * - `family` : famille (slug, nom) quand la série en est une version et que la famille est aussi liée à la ligne ;
 * - `folded` : famille que la fiche ne montre pas, parce qu'une de ses versions liées a circulé (une famille
 *   dont les versions ne sont que douteuses, ou qui porte des périodes que ses versions n'ont pas, reste montrée).
 */
final readonly class LineMaterialFacts
{
    /**
     * @param  array<int, list<array<string, mixed>>>  $periods  material_id → périodes
     * @param  array<int, array{reason: string, date: string, precision: string, section: string|null, conflict: bool}|null>  $earliest  material_id → borne
     * @param  array<int, string|null>  $doubts  material_id → raison du doute
     * @param  array<int, array{slug: string, name: string}|null>  $families  material_id → famille
     * @param  array<int, true>  $folded  material_id des familles repliées dans leurs versions
     */
    private function __construct(
        private array $periods,
        private array $earliest,
        private array $doubts,
        private array $families,
        private array $folded,
    ) {}

    /**
     * @param  Collection<int, LineMaterial>|null  $links  liens de la ligne avec `material` et `shownPeriods` déjà
     *                                                     chargés (tous les liens, sans filtre), sinon lus ici
     */
    public static function forLine(Line $line, ?Collection $links = null): self
    {
        $links ??= LineMaterial::query()->where('line_id', $line->id)->with(['material', 'shownPeriods'])->get();
        $byMaterial = $links->groupBy('material_id');
        /** @var Collection<int, Material> $materials */
        $materials = $links->map(fn (LineMaterial $l): ?Material => $l->material)->filter()->unique('id')->keyBy('id');

        $periods = $byMaterial->map(fn (Collection $group): array => $group->flatMap(fn (LineMaterial $l) => $l->shownPeriods)
            ->sortBy(fn (LineMaterialPeriod $p): array => [($p->start_date ?? $p->end_date)?->toDateString() ?? '9999', $p->end_date?->toDateString() ?? '9999'])
            ->map(fn (LineMaterialPeriod $p): array => $p->toSheet())->values()->all())->all();

        // Électricité sans caténaire : une série électrique n'a pas circulé sur la ligne avant son électrification.
        $bound = $materials->contains(fn (Material $m): bool => ElectrificationBound::isElectric($m)) ? ElectrificationBound::lineDate($line) : null;
        $earliest = [];
        foreach ($materials as $id => $m) {
            $earliest[$id] = null;
            if ($bound !== null && ElectrificationBound::appliesTo($m, $bound['date'])) {
                $periods[$id] = ElectrificationBound::clampPeriods($periods[$id] ?? [], $bound);
                $earliest[$id] = ['reason' => ElectrificationBound::REASON] + $bound + ['conflict' => ElectrificationBound::conflicts($periods[$id], $bound)];
            }
        }

        $doubts = [];
        foreach ($materials as $id => $m) {
            $doubts[$id] = ($periods[$id] ?? []) === [] ? LineMaterialDoubt::reason($byMaterial[$id] ?? []) : null;
        }

        // Familles liées à la ligne et leurs versions liées.
        $bySlug = $materials->keyBy('slug');
        $families = [];
        $versionRan = [];
        foreach ($materials as $id => $m) {
            $family = MaterialFamilyMembers::familyOf($m->slug);
            $families[$id] = null;
            if ($family !== null && $bySlug->has($family)) {
                $families[$id] = ['slug' => $family, 'name' => (string) $bySlug[$family]->name];
                $versionRan[$family] = ($versionRan[$family] ?? false) || $doubts[$id] === null;
            }
        }
        $folded = [];
        foreach ($versionRan as $family => $ran) {
            $id = $bySlug[$family]->id;
            // La famille garde sa tuile si elle porte des périodes que ses versions n'ont pas, ou si elle a
            // circulé alors que ses versions liées ne sont que douteuses (elle ne passe pas dans le groupe des doutes).
            if (($periods[$id] ?? []) === [] && ($ran || $doubts[$id] !== null)) {
                $folded[$id] = true;
            }
        }

        return new self($periods, $earliest, $doubts, $families, $folded);
    }

    /** @return list<array<string, mixed>> */
    public function periods(int $materialId): array
    {
        return $this->periods[$materialId] ?? [];
    }

    /** @return array{reason: string, date: string, precision: string, section: string|null, conflict: bool}|null */
    public function earliest(int $materialId): ?array
    {
        return $this->earliest[$materialId] ?? null;
    }

    public function doubt(int $materialId): ?string
    {
        return $this->doubts[$materialId] ?? null;
    }

    /** @return array{slug: string, name: string}|null */
    public function family(int $materialId): ?array
    {
        return $this->families[$materialId] ?? null;
    }

    public function folded(int $materialId): bool
    {
        return isset($this->folded[$materialId]);
    }

    /**
     * Séries dont une période sourcée commence avant la première électrification connue de la ligne.
     *
     * @return list<int>
     */
    public function conflicts(): array
    {
        return array_keys(array_filter($this->earliest, fn (?array $e): bool => ($e['conflict'] ?? false) === true));
    }
}
