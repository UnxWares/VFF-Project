<?php

namespace App\Services\LevelCrossings;

use App\Enums\LevelCrossingGroup;
use App\Models\LevelCrossing;
use App\Models\Line;
use Illuminate\Support\Collection;

/**
 * Passages à niveau d'une ligne, dans l'ordre des points kilométriques, avec leur nombre par
 * groupe : section « Passages à niveau » de la fiche ligne, `GET /api/v1/lines/{line}/level-crossings`
 * et champ GraphQL `Line.level_crossings`. Rattachement par le code de ligne SNCF Réseau.
 */
final class LineLevelCrossings
{
    /**
     * PN de la ligne (vide pour une ligne sans code SNCF, ou dont une autre fiche porte le même code :
     * voir LevelCrossing::ownerLineIdSql), PK inconnus en dernier.
     *
     * @return Collection<int, LevelCrossing>
     */
    public function list(Line $line): Collection
    {
        if ($line->code === null || $line->code === '') {
            return new Collection;
        }

        // La ligne est connue : elle sert de relation `line` à chaque PN (pas de requête par PN).
        return LevelCrossing::query()
            ->ofLine($line->id, $line->code)
            ->orderByRaw('pk_m IS NULL, pk_m, id')
            ->get(['id', 'id_if', 'libelle', 'mnemo', 'obstacle', 'code_ligne', 'rg_troncon', 'pk_m', 'commune', 'lat', 'lon'])
            ->each(fn (LevelCrossing $c) => $c->setRelation('line', $line));
    }

    /**
     * Nombre de PN par groupe, dans l'ordre d'affichage ; groupes absents omis. `unknown` : classe
     * illisible ou absente de la liste officielle.
     *
     * @param  Collection<int, LevelCrossing>  $crossings
     * @return list<array{group: string, count: int}>
     */
    public function summary(Collection $crossings): array
    {
        $counts = $crossings->countBy(fn (LevelCrossing $c): string => $c->group->value ?? 'unknown');

        return collect([...LevelCrossingGroup::ORDER, 'unknown'])
            ->filter(fn (string $g): bool => $counts->has($g))
            ->map(fn (string $g): array => ['group' => $g, 'count' => (int) $counts[$g]])
            ->values()
            ->all();
    }

    /**
     * Propriétés de la fiche ligne : résumé et liste compacte (le libellé de la classe est traduit
     * par la page).
     *
     * @return array{total: int, summary: list<array{group: string, count: int}>,
     *     items: list<array{id: string, label: string|null, class: int|null, group: string|null,
     *     pk: string|null, commune: string|null, crossed_way: string|null}>}
     */
    public function forSheet(Line $line): array
    {
        $crossings = $this->list($line);

        return [
            'total' => $crossings->count(),
            'summary' => $this->summary($crossings),
            'items' => $crossings->map(fn (LevelCrossing $c): array => [
                'id' => $c->id_if,
                'label' => $c->libelle,
                'class' => $c->class,
                'group' => $c->group?->value,
                'pk' => LevelCrossing::pkLabel($c->pk_m),
                'commune' => $c->commune,
                'crossed_way' => $c->obstacle,
            ])->values()->all(),
        ];
    }
}
