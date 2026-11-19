<?php

namespace App\GraphQL\Builders;

use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Models\LineSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Argument `era` de `Line.segments` : les tronçons d'une seule ère, dans l'ordre des points
 * kilométriques (même liste que `GET /api/v1/lines/{line}/segments?era=`).
 */
final class LineSegmentBuilder
{
    use FindsEra;

    /**
     * @param  Builder<LineSegment>|Relation<LineSegment, *, *>  $builder
     * @return Builder<LineSegment>|Relation<LineSegment, *, *>
     */
    public function era(Builder|Relation $builder, ?int $year): Builder|Relation
    {
        if ($year === null) {
            return $builder;
        }

        return $builder->where('line_segments.era_id', self::findEra($year)->id)
            ->orderByRaw('line_segments.pk_start_m NULLS LAST, line_segments.id');
    }
}
