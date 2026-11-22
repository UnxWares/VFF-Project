<?php

namespace App\Support\Monitoring;

/**
 * Adresse d'une page réduite pour un journal d'erreurs ou l'outil de suivi : origine et chemin, sans
 * requête ni fragment, et sans secret dans le chemin. Un segment de 32 caractères ou plus (lettres,
 * chiffres, `-`, `_`) devient `[token]` : le jeton de `/reset-password/{token}` (64 caractères) ne
 * doit pas sortir avec l'erreur d'une page qui le porte.
 */
final class RedactedUrl
{
    public static function of(string $url): ?string
    {
        $p = parse_url($url);
        if (! is_array($p) || ! isset($p['scheme'], $p['host'])) {
            return null;
        }
        $path = preg_replace('#(?<=/)[A-Za-z0-9_-]{32,}(?=/|$)#', '[token]', $p['path'] ?? '') ?? '';

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').$path;
    }
}
