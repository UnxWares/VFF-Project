<?php

namespace App\Support\Monitoring;

use RuntimeException;
use Throwable;

/**
 * Erreur levée à la place d'une autre quand la requête porte la position d'un internaute (page « Autour de moi »,
 * `GET /api/v1/what-was-here?summary=1`). Le journal de Laravel (`storage/logs/laravel.log`, gardé 365 jours au
 * plus) écrit le message et la pile d'une erreur : une erreur de base de données y recopierait la requête SQL avec
 * ses valeurs, donc la latitude et la longitude. Ici ne restent que la classe de l'erreur d'origine, son fichier,
 * sa ligne et son message sans la requête SQL ni aucun nombre décimal. Pas d'erreur précédente (`previous`) : le
 * journal l'écrirait en entier, avec sa pile.
 */
final class PositionlessError extends RuntimeException
{
    public static function from(Throwable $error): self
    {
        return new self(sprintf(
            '%s in %s:%d: %s',
            $error::class,
            basename($error->getFile()),
            $error->getLine(),
            self::message($error->getMessage()),
        ));
    }

    /** Message sans la requête SQL (SentryScrubber::message) et sans nombre décimal (une coordonnée). */
    public static function message(string $message): string
    {
        return preg_replace('/\d+[.,]\d+/', '[n]', SentryScrubber::message($message)) ?? '';
    }
}
