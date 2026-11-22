<?php

namespace App\Support;

use Illuminate\Foundation\Vite;
use Throwable;

/**
 * Adresses (empreinte Vite comprise) des fichiers de police auto-hébergés à précharger dans l'en-tête des
 * pages : ceux que le premier écran utilise presque toujours. Sans préchargement, le navigateur ne découvre
 * une police qu'après avoir lu la feuille de style puis posé le texte, et le texte change d'aspect en cours
 * de route (décalage de mise en page). Les polices de repli ajustées de resources/css/general.css réduisent
 * ce décalage pour les fichiers qui ne sont pas préchargés.
 *
 * La liste est volontairement courte : un préchargement trop large retarde le CSS et le JavaScript.
 */
final class FontPreloads
{
    /** Noms des fichiers WOFF2 (@fontsource latin, et sous-ensemble d'icônes du site), sans l'empreinte ni l'extension. */
    public const FILES = [
        'lato-latin-400-normal',
        'lato-latin-300-italic',
        'gabarito-latin-700-normal',
        'inter-latin-400-normal',
        'inter-latin-600-normal',
        'bootstrap-icons-subset',
    ];

    /**
     * @return list<string> adresses absolues ; vide en développement (Vite sert les fichiers à la volée) ou sans manifeste
     */
    public static function urls(string $buildDirectory = 'build'): array
    {
        $vite = app(Vite::class);
        if ($vite->isRunningHot()) {
            return [];
        }

        // Manifeste lu directement dans le dossier de build (Vite::manifest() est protégée) ; absent ou illisible : rien à précharger.
        try {
            $path = public_path($buildDirectory.'/manifest.json');
            $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (Throwable) {
            return [];
        }
        if (! is_array($manifest)) {
            return [];
        }

        $urls = [];
        foreach (self::FILES as $name) {
            foreach (array_keys($manifest) as $key) {
                if (str_ends_with((string) $key, '/'.$name.'.woff2')) {
                    $urls[] = $vite->asset((string) $key, $buildDirectory);
                    break;
                }
            }
        }

        return $urls;
    }
}
