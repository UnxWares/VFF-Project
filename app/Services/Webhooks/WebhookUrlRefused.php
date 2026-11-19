<?php

namespace App\Services\Webhooks;

use RuntimeException;

/**
 * Adresse de webhook refusée par WebhookUrlGuard. `reason` est un code stable (journal des
 * livraisons, tests) ; le message est traduit dans la langue de la requête (lang/<langue>/webhooks.php).
 */
final class WebhookUrlRefused extends RuntimeException
{
    /** Adresse illisible ou trop longue. */
    public const INVALID = 'invalid';

    /** Protocole autre que HTTPS. */
    public const NOT_HTTPS = 'not_https';

    /** Identifiant ou mot de passe dans l'adresse. */
    public const CREDENTIALS = 'credentials';

    /** Port autre que ceux de config/webhooks.php. */
    public const PORT = 'port';

    /** Nom d'hôte local ou interne (localhost, .local, .internal, nom sans domaine). */
    public const HOST = 'host';

    /** Adresse IP (écrite ou obtenue par le DNS) privée, locale, de bouclage ou de métadonnées. */
    public const PRIVATE_ADDRESS = 'private_address';

    /** Le nom ne se résout pas. */
    public const UNRESOLVED = 'unresolved';

    private function __construct(public readonly string $reason)
    {
        parent::__construct(__('webhooks.errors.url.'.$reason));
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
