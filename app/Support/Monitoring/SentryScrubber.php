<?php

namespace App\Support\Monitoring;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\Stacktrace;

/**
 * Retire d'un événement d'erreur tout ce qui pourrait désigner un visiteur avant l'envoi à l'outil
 * de suivi : compte, adresse IP, cookies, en-têtes, corps et paramètres de la requête, nom de la
 * machine, contexte, arguments des fonctions de la pile d'appels (un mot de passe passé à
 * `Auth::attempt()` y figurerait en clair quand `zend.exception_ignore_args` est désactivé) et jetons
 * dans le chemin (RedactedUrl). Restent le type de l'erreur, son message, la pile d'appels (fichiers,
 * lignes, fonctions, code source), la méthode et le chemin de la page (sans requête), la version et
 * l'environnement.
 */
final class SentryScrubber
{
    public static function scrub(Event $event, ?EventHint $hint = null): Event
    {
        $event->setUser(null);
        $event->setServerName(null);
        $event->setBreadcrumb([]);
        $event->setExtra([]);
        $event->setTags([]);

        $request = $event->getRequest();
        $event->setRequest(array_filter([
            'method' => $request['method'] ?? null,
            'url' => is_string($request['url'] ?? null) ? RedactedUrl::of($request['url']) : null,
        ]));

        // Le contexte d'exécution (machine, système) n'a pas d'intérêt pour retrouver une erreur.
        $event->setRuntimeContext(null);
        $event->setOsContext(null);

        // Le message d'une erreur de base de données répète la requête SQL avec ses valeurs
        // (adresse électronique, jeton) : on ne garde que le début, avant la requête.
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::message($exception->getValue()));
            self::withoutArguments($exception->getStacktrace());
        }
        self::withoutArguments($event->getStacktrace());

        return $event;
    }

    /** Arguments des fonctions de la pile (`vars` de chaque cadre) retirés : ils peuvent porter un mot de passe, une adresse. */
    private static function withoutArguments(?Stacktrace $stacktrace): void
    {
        foreach ($stacktrace?->getFrames() ?? [] as $frame) {
            $frame->setVars([]);
        }
    }

    /** Message sans la requête SQL ni les valeurs liées, borné à 500 caractères. */
    public static function message(string $message): string
    {
        $cut = preg_split('/\s*\((?:Connection|SQL):/', $message, 2);

        return mb_substr(is_array($cut) ? $cut[0] : $message, 0, 500);
    }
}
