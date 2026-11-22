<?php

namespace App\Support\Security;

use App\Support\Media\CommonsImagePath;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

/**
 * Politique de sécurité du contenu (CSP) d'une page du site, d'après config/csp.php.
 *
 *   - scripts : ceux du site et les scripts en ligne qui portent le nonce de la requête (Vite le
 *     pose sur ses balises, les vues Blade sur leurs scripts en ligne). La référence GraphQL
 *     (Magidoc, pages statiques générées) n'a plus de script en ligne (sortis en fichiers par
 *     resources/docs/graphql/postbuild.mjs) : 'self' seul sur /docs/graphql ;
 *   - styles : ceux du site et les styles en ligne (attributs `style` de Svelte, MapLibre, Scalar,
 *     Swagger UI). Sur /docs/graphql, 'self' seul, plus les attributs `style` du rendu serveur
 *     admis par empreinte (style-src-attr 'unsafe-hashes', liste de
 *     resources/docs/graphql/csp-style-hashes.json) ;
 *   - images et connexions : le site, plus les origines de config/csp.php (fond de carte,
 *     Wikimedia Commons, Gallica) ; `data:` et `blob:` pour les images générées par la carte. Relais des
 *     vignettes de Commons activé (config commons_proxy) : upload.wikimedia.org et thumb.wikimedia.org
 *     sortent des images, VFF les sert lui-même ;
 *   - temps réel activé (App\Support\Realtime) : le serveur WebSocket public admis en connexion ;
 *   - MapLibre crée ses workers depuis un `blob:` ;
 *   - aucune intégration d'objet, formulaires et <base> limités au site, pages intégrables
 *     seulement par le site (sauf /embed/*, intégrable partout) ;
 *   - en développement (environnement `local`, Vite lancé : fichier public/hot), le serveur Vite et
 *     sa connexion WebSocket sont admis pour les scripts, les styles et les connexions.
 */
final class ContentSecurityPolicy
{
    /** Adresse qui reçoit les rapports de violation (CspReportController). */
    public const REPORT_PATH = '/api/csp-report';

    /** Nom du point de réception dans l'en-tête Reporting-Endpoints (directive report-to). */
    public const REPORT_GROUP = 'csp';

    /** En-tête à poser : bloquant ou en observation seule (config csp.enforce). */
    public static function headerName(): string
    {
        return config('csp.enforce') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
    }

    public static function build(Request $request, string $nonce): string
    {
        $dev = self::viteOrigins();
        $sources = (array) config('csp.sources', []);
        $magidoc = $request->is('docs/graphql', 'docs/graphql/*');

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", ...($magidoc ? [] : ["'nonce-{$nonce}'"]), ...$dev],
            'style-src' => ["'self'", ...($magidoc ? [] : ["'unsafe-inline'"]), ...$dev],
            ...($magidoc ? ['style-src-attr' => ["'unsafe-hashes'", ...self::magidocStyleHashes()]] : []),
            'img-src' => ["'self'", 'data:', 'blob:', ...self::imageSources((array) ($sources['img-src'] ?? []))],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", ...($sources['connect-src'] ?? []), ...$dev, ...self::websockets($dev), ...array_filter([Realtime::websocketOrigin()])],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
            'frame-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => [$request->routeIs('embed.*') ? '*' : "'self'"],
        ];
        if (config('csp.report')) {
            $directives['report-uri'] = [self::REPORT_PATH];
            $directives['report-to'] = [self::REPORT_GROUP];
        }

        return implode('; ', array_map(
            fn (string $name, array $values): string => $name.' '.implode(' ', array_values(array_unique($values))),
            array_keys($directives),
            $directives,
        ));
    }

    /**
     * @param  array<int, string>  $sources  origines d'images de config/csp.php
     * @return list<string> les mêmes, sans les hôtes de Wikimedia que le relais remplace quand il est activé
     */
    public static function imageSources(array $sources): array
    {
        if (! config('commons_proxy.enabled')) {
            return array_values($sources);
        }
        $relayed = array_map(fn (string $host): string => 'https://'.$host, CommonsImagePath::HOSTS);

        return array_values(array_diff($sources, $relayed));
    }

    /** @return list<string> empreintes `'sha256-…'` des attributs `style` des pages Magidoc (fichier écrit par postbuild.mjs) */
    public static function magidocStyleHashes(): array
    {
        $file = resource_path('docs/graphql/csp-style-hashes.json');
        $hashes = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return array_map(fn (string $h): string => "'{$h}'", is_array($hashes) ? array_values($hashes) : []);
    }

    /** Valeur de l'en-tête Reporting-Endpoints (API Reporting, qui remplace report-uri). */
    public static function reportingEndpoints(Request $request): string
    {
        return self::REPORT_GROUP.'="'.$request->getSchemeAndHttpHost().self::REPORT_PATH.'"';
    }

    /** @return list<string> origine du serveur Vite de développement, s'il tourne (environnement local) */
    private static function viteOrigins(): array
    {
        $hot = (string) (config('csp.hot_file') ?? Vite::hotFile());
        if (! app()->isLocal() || ! is_file($hot)) {
            return [];
        }
        $url = trim((string) file_get_contents($hot));
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            return [];
        }

        // La syntaxe CSP n'admet pas d'adresse IPv6 (« [::1] ») : le schéma seul la remplace (développement).
        if (str_starts_with($parts['host'], '[')) {
            return [$parts['scheme'].':'];
        }

        return [$parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')];
    }

    /**
     * @param  list<string>  $origins
     * @return list<string> mêmes origines en WebSocket (rechargement à chaud de Vite)
     */
    private static function websockets(array $origins): array
    {
        return array_map(fn (string $o): string => (string) preg_replace('/^http/', 'ws', $o), $origins);
    }
}
