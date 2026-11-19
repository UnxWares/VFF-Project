<?php

namespace App\Services\Contributions;

/**
 * Échec métier de la publication d'une contribution, avec sa raison stable (PublicationFailure). Le
 * message, technique et en français, ne va qu'au journal ; l'appelant ne montre que la raison.
 */
final class ContributionNotPublishable extends \RuntimeException
{
    public function __construct(public readonly PublicationFailure $failure, string $message)
    {
        parent::__construct($message);
    }
}
