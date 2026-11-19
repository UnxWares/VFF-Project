<?php

namespace App\Services\Accidents;

/**
 * Une source consultée pour un accident, telle qu'elle sera écrite dans accident_sources.
 * `provider` est une clé de database/data/source_rights.php.
 */
final readonly class AccidentSourceRef
{
    /** @param array<string, mixed> $facts faits lus dans cette source (pour le recoupement) */
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $role,
        public string $title,
        public string $url,
        public string $licence,
        public ?string $licenceUrl = null,
        public ?int $revision = null,
        public array $facts = [],
    ) {}
}
