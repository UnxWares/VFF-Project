<?php

namespace App\Support\Monitoring;

/**
 * Adresse d'une page réduite pour un journal d'erreurs ou l'outil de suivi : origine et chemin, sans
 * requête ni fragment (donc sans `?signature=` d'une URL signée), et sans secret dans le chemin. Un segment
 * de 32 caractères ou plus (lettres, chiffres, `-`, `_`) qui ressemble à un jeton devient `[token]` : le
 * jeton de `/reset-password/{token}` (64 caractères) ne doit pas sortir avec l'erreur d'une page qui le
 * porte. Un slug de mots en minuscules séparés par des tirets (`archeo-ligne-de-boulogne-ville-a-bonningues`)
 * reste lisible ; un UUID ou une suite hexadécimale est un jeton.
 */
final class RedactedUrl
{
    public static function of(string $url): ?string
    {
        $p = parse_url($url);
        if (! is_array($p) || ! isset($p['scheme'], $p['host'])) {
            return null;
        }
        $path = preg_replace_callback(
            '#(?<=/)[A-Za-z0-9_-]{32,}(?=/|$)#',
            static fn (array $m): string => self::isWordSlug($m[0]) ? $m[0] : '[token]',
            $p['path'] ?? '',
        ) ?? '';

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').$path;
    }

    /**
     * Slug de mots : au moins trois groupes de minuscules et chiffres séparés par un seul tiret, dont un
     * contient une lettre hors de a-f (un UUID ou de l'hexadécimal n'est pas un slug), sans majuscule
     * ni `_`.
     */
    private static function isWordSlug(string $segment): bool
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+){2,}$/', $segment) !== 1) {
            return false;
        }
        foreach (explode('-', $segment) as $word) {
            if (preg_match('/[g-z]/', $word) === 1) {
                return true;
            }
        }

        return false;
    }
}
