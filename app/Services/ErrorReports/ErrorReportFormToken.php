<?php

namespace App\Services\ErrorReports;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Jeton d'ouverture du formulaire de signalement : l'heure d'ouverture, chiffrée et signée par la
 * clé de l'application. Il sert de durée minimale de remplissage (un robot qui poste dans la
 * seconde est écarté) sans cookie supplémentaire, sans service externe et sans rien garder côté
 * serveur. Il expire au bout de deux heures.
 */
final readonly class ErrorReportFormToken
{
    /** Durée minimale entre l'ouverture du formulaire et l'envoi, en secondes. */
    public const MIN_SECONDS = 3;

    /** Durée de validité d'un jeton, en secondes. */
    public const MAX_SECONDS = 7200;

    private const PREFIX = 'vff-report:';

    public function __construct(private Encrypter $encrypter) {}

    public function issue(): string
    {
        return $this->encrypter->encryptString(self::PREFIX.now()->getTimestamp());
    }

    /** Le jeton est-il authentique, assez ancien pour un humain et pas encore expiré ? */
    public function accepts(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        try {
            $plain = $this->encrypter->decryptString($token);
        } catch (DecryptException) {
            return false;
        }
        if (! str_starts_with($plain, self::PREFIX) || ! ctype_digit($issued = substr($plain, strlen(self::PREFIX)))) {
            return false;
        }
        $age = now()->getTimestamp() - (int) $issued;

        return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }
}
