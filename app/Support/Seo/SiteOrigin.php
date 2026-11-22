<?php

namespace App\Support\Seo;

/**
 * Origine publique du site (schéma, hôte et port de `app.url`), sans barre finale. Les adresses absolues
 * des flux, des plans du site, du JSON-LD et de l'API du journal en viennent, et non de l'en-tête `Host`
 * de la requête : ces réponses sont publiques et mises en cache, et GraphQL et le MCP font déjà de même.
 */
final class SiteOrigin
{
    public static function get(): string
    {
        $url = (string) config('app.url');
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return rtrim($url, '/');
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
