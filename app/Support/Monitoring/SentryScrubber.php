<?php

namespace App\Support\Monitoring;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Retire d'un événement d'erreur tout ce qui pourrait désigner un visiteur avant l'envoi à l'outil
 * de suivi : compte, adresse IP, cookies, en-têtes, corps et paramètres de la requête, nom de la
 * machine, contexte. Restent le type de l'erreur, son message, la pile d'appels, la méthode et le
 * chemin de la page (sans requête), la version et l'environnement.
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
        $url = is_string($request['url'] ?? null) ? parse_url($request['url']) : false;
        $event->setRequest(array_filter([
            'method' => $request['method'] ?? null,
            'url' => is_array($url) && isset($url['scheme'], $url['host']) ? $url['scheme'].'://'.$url['host'].($url['path'] ?? '') : null,
        ]));

        // Le contexte d'exécution (machine, système) n'a pas d'intérêt pour retrouver une erreur.
        $event->setRuntimeContext(null);
        $event->setOsContext(null);

        // Le message d'une erreur de base de données répète la requête SQL avec ses valeurs
        // (adresse électronique, jeton) : on ne garde que le début, avant la requête.
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::message($exception->getValue()));
        }

        return $event;
    }

    /** Message sans la requête SQL ni les valeurs liées, borné à 500 caractères. */
    public static function message(string $message): string
    {
        $cut = preg_split('/\s*\((?:Connection|SQL):/', $message, 2);

        return mb_substr(is_array($cut) ? $cut[0] : $message, 0, 500);
    }
}
