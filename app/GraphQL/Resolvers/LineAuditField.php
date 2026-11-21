<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\SegmentAudit\SegmentAudits;
use GraphQL\Error\Error;

/**
 * Champ GraphQL `Line.audit` : même contenu que `GET /api/v1/lines/{line}/audit` ; la liste des
 * tronçons se filtre par `LineAudit.segments(grade:)`.
 */
final readonly class LineAuditField
{
    public function __construct(private SegmentAudits $audits) {}

    /**
     * @param  array{era?: int|null}  $args
     * @return array<string, mixed>|null
     */
    public function __invoke(Line $line, array $args): ?array
    {
        $year = $args['era'] ?? 2030;
        if ($this->audits->eraId($year) === null) {
            throw new Error("Ère inconnue : {$year}.");
        }

        return $this->audits->forLine($line, $year);
    }

    /**
     * @param  array<string, mixed>  $audit
     * @param  array{grade?: string|null}  $args
     * @return list<array<string, mixed>>
     */
    public function segments(array $audit, array $args): array
    {
        $grade = $args['grade'] ?? null;

        return array_values(array_filter($audit['segments'], fn (array $s): bool => $grade === null || $s['worst_grade'] === $grade));
    }
}
