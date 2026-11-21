<?php

namespace App\GraphQL\Resolvers;

use App\Models\Line;
use App\Services\SegmentAudit\AuditPage;
use App\Services\SegmentAudit\SegmentAudits;
use GraphQL\Error\Error;

/**
 * Champ GraphQL `Line.audit` : même contenu que `GET /api/v1/lines/{line}/audit` ; la liste des
 * tronçons se filtre et se pagine par `LineAudit.segments(grade:, limit:, offset:)`.
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
     * @param  array{grade?: string|null, limit?: int|null, offset?: int|null}  $args
     * @return list<array<string, mixed>>
     */
    public function segments(array $audit, array $args): array
    {
        return AuditPage::apply($audit, $args['grade'] ?? null, $args['limit'] ?? null, $args['offset'] ?? null)['segments'];
    }

    /**
     * @param  array<string, mixed>  $audit
     * @param  array{grade?: string|null}  $args
     */
    public function segmentsTotal(array $audit, array $args): int
    {
        return count(AuditPage::filter($audit['segments'], $args['grade'] ?? null));
    }
}
