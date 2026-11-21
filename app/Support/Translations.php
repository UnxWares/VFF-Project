<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Aplatit toutes les chaînes de traduction d'une locale en une map plate
 * (`['home.title' => 'Bienvenue', 'common.save' => 'Enregistrer']`). Résultat mis en cache par locale.
 *
 * Le navigateur les reçoit dans un fichier JSON par langue (payload(), servi par TranslationsController à
 * une adresse qui porte l'empreinte du contenu, url()) : gardé en cache par le navigateur et le service
 * worker, il n'est plus recopié dans chaque page HTML (344 Ko sur 385 Ko pour l'accueil, mesure du 21/11/2026).
 */
class Translations
{
    public const CANONICAL = 'fr';

    /**
     * Fichiers de `lang/<locale>/` lus uniquement par le serveur (messages de
     * validation, statuts du broker de mots de passe, pagination, messages
     * HTTP). Le front ne les utilise pas : les erreurs arrivent déjà traduites
     * dans les props Inertia (`errors`). Les exclure évite d'alourdir chaque page.
     * `mcp` : réponses du serveur MCP (app/Mcp), jamais affichées par les pages.
     */
    public const SERVER_ONLY = ['validation', 'passwords', 'pagination', 'messages', 'mcp'];

    /**
     * Charge la map plate des traductions pour `$locale`, avec fallback sur
     * la locale canonique (`fr`) pour les clés manquantes.
     *
     * @return array<string, string>
     */
    public static function load(string $locale): array
    {
        if (config('app.debug')) {
            return self::merged($locale);
        }

        return Cache::rememberForever(
            "translations.{$locale}",
            fn () => self::merged($locale),
        );
    }

    /**
     * Dictionnaire d'une locale tel que le navigateur le reçoit : JSON (UTF-8 brut) et son empreinte
     * (12 caractères hexadécimaux, tirés du contenu). Mis en cache comme load().
     *
     * @return array{json: string, version: string}
     */
    public static function payload(string $locale): array
    {
        $build = function () use ($locale): array {
            $json = json_encode(self::load($locale), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            return ['json' => $json, 'version' => substr(md5($json), 0, 12)];
        };

        if (config('app.debug')) {
            return $build();
        }

        return Cache::rememberForever("translations.payload.{$locale}", $build);
    }

    /** Le même JSON compressé (gzip), pour les clients qui l'acceptent. Mis en cache comme load(). */
    public static function gzipped(string $locale): string
    {
        $build = fn (): string => (string) gzencode(self::payload($locale)['json'], 9);

        if (config('app.debug')) {
            return $build();
        }

        return Cache::rememberForever("translations.gzip.{$locale}", $build);
    }

    /** Adresse du dictionnaire d'une locale, avec son empreinte (elle change dès qu'une chaîne change). */
    public static function url(string $locale): string
    {
        return route('i18n.translations', ['locale' => $locale, 'version' => self::payload($locale)['version']], false);
    }

    /**
     * @return array<string, string>
     */
    protected static function merged(string $locale): array
    {
        $canonical = self::build(self::CANONICAL);

        if ($locale === self::CANONICAL) {
            return $canonical;
        }

        return array_merge($canonical, self::build($locale));
    }

    /**
     * @return array<string, string>
     */
    protected static function build(string $locale): array
    {
        $path = lang_path($locale);

        if (! is_dir($path)) {
            return [];
        }

        $namespaces = [];
        foreach (glob($path.'/*.php') ?: [] as $file) {
            $namespace = basename($file, '.php');
            if (in_array($namespace, self::SERVER_ONLY, true)) {
                continue;
            }
            /** @var array<string, mixed> $contents */
            $contents = require $file;
            $namespaces[$namespace] = $contents;
        }

        return self::flatten($namespaces);
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, string>
     */
    protected static function flatten(array $items, string $prefix = ''): array
    {
        $flat = [];

        foreach ($items as $key => $value) {
            $compoundKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat = array_merge($flat, self::flatten($value, $compoundKey));
            } else {
                $flat[$compoundKey] = (string) $value;
            }
        }

        return $flat;
    }
}
