<?php

namespace App\Services\Webhooks;

/** Résultat d'un essai d'envoi (WebhookSender). `error` : code court gardé dans le journal. */
final readonly class WebhookAttempt
{
    public const TIMEOUT = 'timeout';

    public const CONNECTION_FAILED = 'connection_failed';

    public const HTTP_STATUS = 'http_status';

    public const REDIRECT = 'redirect';

    public const ADDRESS_REFUSED = 'address_refused';

    public const DNS_FAILED = 'dns_failed';

    /** Erreur imprévue du côté de VFF pendant l'envoi. */
    public const INTERNAL = 'internal';

    private function __construct(
        public bool $succeeded,
        public ?int $responseStatus,
        public ?string $error,
        public bool $retryable,
        public int $durationMs,
    ) {}

    public static function success(int $status, int $durationMs): self
    {
        return new self(true, $status, null, false, $durationMs);
    }

    public static function failure(string $error, bool $retryable, int $durationMs = 0, ?int $status = null): self
    {
        return new self(false, $status, $error, $retryable, $durationMs);
    }
}
