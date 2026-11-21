<?php

namespace App\Services\StationAudit;

/** Résultat de DateAuditor::audit(). */
final readonly class DatesResult
{
    /**
     * @param  array{passenger: array<string, mixed>, freight: array<string, mixed>}  $services
     * @param  list<array{code: string, detail: string}>  $checks
     */
    public function __construct(
        public DateGrade $opening,
        public DateGrade $closure,
        public array $services,
        public array $checks,
    ) {}
}
