<?php

namespace App\Services\OpenData;

use RuntimeException;

/** Un fournisseur non autorisé par le registre des droits figure dans un jeu de données ouvert. */
final class OpenDataLicenceViolation extends RuntimeException
{
    /** @param  list<string>  $providers */
    public function __construct(public readonly string $dataset, public readonly array $providers)
    {
        parent::__construct(sprintf('Jeu %s : source(s) non autorisée(s) par le registre des droits : %s.', $dataset, implode(', ', $providers)));
    }
}
