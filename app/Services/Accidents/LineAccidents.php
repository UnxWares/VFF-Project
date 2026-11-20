<?php

namespace App\Services\Accidents;

use App\Enums\AccidentLinkConfidence;
use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
use App\Models\Line;

/**
 * Accidents rattachés à une ligne, pour la section « Accidents » de sa fiche (/lines/{slug}) :
 * date et sa précision, nature, lieu, bilan, et la confiance du rattachement à CETTE ligne (une
 * commune seule donne un rattachement « à vérifier »). Mêmes données que
 * `GET /api/v1/accidents?line={slug}` et le champ GraphQL `Line.accidents`.
 */
final class LineAccidents
{
    /**
     * @return list<array{slug: string, name: string, date: string, date_precision: string, kind: string,
     *     place: string|null, department: string|null, deaths: int|null, injuries: int|null,
     *     confidence: string, is_primary: bool, to_review: bool}>
     */
    public function forLine(Line $line, string $locale): array
    {
        return Accident::query()
            ->join('accident_lines', 'accident_lines.accident_id', '=', 'accidents.id')
            ->where('accident_lines.line_id', $line->id)
            ->orderBy('accidents.occurred_on')
            ->orderBy('accidents.id')
            ->get([
                'accidents.slug', 'accidents.name', 'accidents.occurred_on', 'accidents.date_precision',
                'accidents.kind', 'accidents.commune_name', 'accidents.department_code', 'accidents.deaths',
                'accidents.injuries', 'accidents.review_status', 'accident_lines.confidence', 'accident_lines.is_primary',
            ])
            ->map(fn (Accident $a): array => [
                'slug' => $a->slug,
                'name' => $a->nameIn($locale),
                'date' => $a->occurred_on->toDateString(),
                'date_precision' => $a->date_precision->value,
                'kind' => $a->kind->value,
                'place' => $a->commune_name,
                'department' => $a->department_code,
                'deaths' => $a->deaths,
                'injuries' => $a->injuries,
                'confidence' => AccidentLinkConfidence::from((string) $a->getAttribute('confidence'))->value,
                'is_primary' => (bool) $a->getAttribute('is_primary'),
                'to_review' => $a->review_status === AccidentReviewStatus::ToReview,
            ])
            ->values()
            ->all();
    }
}
