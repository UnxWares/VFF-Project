<?php

namespace App\Support;

/**
 * Adresse venue d'une source extérieure (Wikimedia Commons, Wikidata, rapports…) et rendue en lien
 * (`href`) ou en image : seules les adresses absolues en `http` ou `https` sont gardées, ainsi que les
 * adresses sans schéma (« //upload.wikimedia.org/… », qui reprennent celui de la page).
 *
 * Commons tire par exemple `LicenseUrl` du HTML de la page du fichier, que n'importe quel compte peut
 * modifier : une adresse `javascript:` ou `data:` y est possible, et Svelte n'assainit pas les `href`.
 * Les espaces et caractères de contrôle de tête et de fin sont retirés (le navigateur les ignore aussi) ;
 * toute autre adresse devient nulle, et le texte qui l'accompagnait (nom de la licence) reste affiché
 * sans lien.
 */
final class WebUrl
{
    public static function safe(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }
        // Le navigateur retire de même les caractères de contrôle et les espaces autour d'une adresse.
        $url = trim($url, "\x00..\x20");
        // Schéma http(s) ou aucun, un nom d'hôte, aucun caractère de contrôle (les accents restent permis).
        if (preg_match('#^(?:https?:)?//[^\s/?\#@]#i', $url) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $url) === 1) {
            return null;
        }

        return $url;
    }
}
