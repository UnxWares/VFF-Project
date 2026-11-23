<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache des données de la carte /maps qui ne changent qu'avec un recalcul : tuiles des tracés d'une ère,
 * tuiles de comparaison de deux ères, gares d'une ère (GeoJSON). Avec leurs en-têtes HTTP.
 *
 * Mesuré le 20/11/2026 (serveur de développement, rapport matin/carte-performance.md) : la tuile du zoom 4
 * qui couvre la France pèse 830 Ko et demande 1,4 s à PostgreSQL, les gares de l'ère actuelle 2,4 Mo et
 * 0,7 s. À chaque visite et à chaque changement d'époque, tout était recalculé.
 *
 * - **Serveur** : la réponse est gardée compressée (gzip) dans le cache (Valkey) pendant TTL, sous une clé
 *   qui contient la version des données (version()). Un recalcul change la version : les entrées
 *   périmées ne sont plus lues et expirent seules. Les tuiles ne sont gardées que jusqu'au zoom
 *   CACHED_MAX_ZOOM : plus fines, elles se calculent en moins de 100 ms et seraient trop nombreuses.
 * - **Navigateur** : ETag tiré de la version (le serveur répond 304 sans rien recalculer), corps envoyé
 *   compressé quand le client l'accepte, Cache-Control long (un jour) quand l'adresse porte la version
 *   des données de la page (`?v=`), une minute sinon.
 */
final class MapDataCache
{
    /** Zoom le plus fin dont les tuiles sont gardées en cache. */
    public const CACHED_MAX_ZOOM = 10;

    /** Durée de vie d'une entrée (s). */
    public const TTL = 6 * 3600;

    /** Durée de vie d'une entrée réchauffée (s) : un jour, pour couvrir la journée qui suit l'application du moteur. */
    public const WARM_TTL = 24 * 3600;

    /** Durée pendant laquelle la version des données est réutilisée sans être relue (s). */
    public const VERSION_TTL = 60;

    /** Cache-Control d'une adresse versionnée (`?v=`) : elle change quand les données changent. */
    public const VERSIONED_MAX_AGE = 86_400;

    /** Cache-Control d'une adresse sans version (clients de l'API). */
    public const PLAIN_MAX_AGE = 60;

    private const VERSION_KEY = 'map-data:version';

    /**
     * Version des données de la carte : dernières modifications des tracés, des recouvrements de voies
     * partagées, des lignes, des gares et de leur état par ère, et nombre de tracés (une suppression seule
     * ne change aucune date). Relue au plus une fois par VERSION_TTL.
     */
    public function version(): string
    {
        return Cache::remember(self::VERSION_KEY, self::VERSION_TTL, function (): string {
            $row = DB::selectOne(<<<'SQL'
                SELECT concat_ws('|',
                    (SELECT max(updated_at) FROM line_segments),
                    (SELECT count(*) FROM line_segments),
                    (SELECT max(updated_at) FROM line_segment_covers),
                    (SELECT max(updated_at) FROM lines),
                    (SELECT max(updated_at) FROM stations),
                    (SELECT max(updated_at) FROM station_eras)) AS v
            SQL);

            return substr(md5((string) ($row->v ?? '')), 0, 16);
        });
    }

    /**
     * Réponse d'une tuile MVT. `$key` désigne la tuile (couche, ères, z/x/y) ; `$compute` la calcule.
     *
     * @param  Closure(): string  $compute
     */
    public function tile(Request $request, string $key, int $z, Closure $compute): Response
    {
        return $this->respond($request, "tile:{$key}", $z <= self::CACHED_MAX_ZOOM, $compute, [
            'Content-Type' => 'application/x-protobuf',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Remplit le cache d'une tuile sans requête HTTP (réchauffage, vff:warm-tiles), sous la même clé que
     * tile(). Renvoie null si l'entrée existe déjà (sauf `$refresh`), sinon sa taille en octets (gzip).
     *
     * @param  Closure(): string  $compute
     */
    public function warmTile(string $key, Closure $compute, bool $refresh = false): ?int
    {
        $cacheKey = "map-data:{$this->version()}:tile:{$key}";
        if (! $refresh && Cache::has($cacheKey)) {
            return null;
        }
        $packed = self::pack($compute());
        Cache::put($cacheKey, $packed, self::WARM_TTL);

        return strlen($packed);
    }

    /** Une tuile de cette clé est-elle déjà gardée (pour le dry-run du réchauffage) ? */
    public function hasTile(string $key): bool
    {
        return Cache::has("map-data:{$this->version()}:tile:{$key}");
    }

    /**
     * Réponse JSON (GeoJSON des gares d'une ère). `$compute` renvoie le tableau à encoder.
     *
     * @param  Closure(): array<string, mixed>  $compute
     */
    public function json(Request $request, string $key, Closure $compute): Response
    {
        return $this->respond(
            $request,
            "json:{$key}",
            true,
            fn (): string => json_encode($compute(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * @param  Closure(): string  $compute
     * @param  array<string, string>  $headers
     */
    private function respond(Request $request, string $key, bool $store, Closure $compute, array $headers): Response
    {
        $version = $this->version();
        $gzip = str_contains(strtolower((string) $request->header('Accept-Encoding', '')), 'gzip');
        $maxAge = $request->query->has('v') ? self::VERSIONED_MAX_AGE : self::PLAIN_MAX_AGE;

        $response = new Response('', 200, $headers + [
            'Cache-Control' => "public, max-age={$maxAge}",
            'Vary' => 'Accept-Encoding',
        ]);
        // Le corps compressé et le corps brut ne sont pas les mêmes octets : deux ETag.
        $response->setEtag(substr(md5("{$key}|{$version}"), 0, 20).($gzip ? '-gz' : ''));
        if ($response->isNotModified($request)) {
            return $response;
        }

        $packed = $store
            ? Cache::remember("map-data:{$version}:{$key}", self::TTL, fn (): string => self::pack($compute()))
            : self::pack($compute());

        if ($packed === '') {
            return $response->setContent('');
        }
        if ($gzip) {
            $response->headers->set('Content-Encoding', 'gzip');

            return $response->setContent($packed);
        }

        return $response->setContent((string) gzdecode($packed));
    }

    /** Corps compressé (gzip), ou chaîne vide pour une réponse vide (tuile sans tracé). */
    private static function pack(string $body): string
    {
        return $body === '' ? '' : (string) gzencode($body, 6);
    }
}
