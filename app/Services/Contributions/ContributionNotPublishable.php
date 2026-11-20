<?php

namespace App\Services\Contributions;

/**
 * Échec métier de la publication d'une contribution, avec sa raison stable (PublicationFailure). Le
 * message, technique et en français, ne va qu'au journal ; l'appelant montre la raison et, s'il y en a,
 * les précisions `details`, écrites pour le validateur (champs changés depuis l'envoi, liens qu'une
 * correction rendrait contraires aux règles) et sans rien de technique.
 */
final class ContributionNotPublishable extends \RuntimeException
{
    /** @param list<string> $details */
    public function __construct(public readonly PublicationFailure $failure, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
