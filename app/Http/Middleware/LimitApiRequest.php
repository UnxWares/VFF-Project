<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Taille du corps et lots de requêtes de GraphQL et du serveur MCP.
 *
 *   - Corps de plus de `api.max_body_bytes` (2 Mio par défaut) : 413, avant tout traitement. La plus grosse
 *     requête légitime (une mutation de contribution avec un tracé) en fait moins de 200 Kio.
 *   - GraphQL : un lot (corps JSON qui est une liste de requêtes, « batching ») est limité à
 *     `api.graphql_max_batch` opérations (5), au-delà : 400. Chaque requête du lot passe seule sous le plafond
 *     de complexité, mais le lot entier ne comptait que pour un appel du limiteur : 40 `mediaFacets` d'un coup
 *     occupaient le serveur 8 secondes, et rien ne bornait la taille d'un lot. Les mutations d'un lot sont déjà
 *     comptées une à une par le limiteur (ContributionMutationsTest).
 *     (MCP refuse déjà les lots : « The request must be a JSON object ».)
 */
class LimitApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $max = max(1024, (int) config('api.max_body_bytes', 2 * 1024 * 1024));
        $declared = (int) $request->headers->get('Content-Length', '0');
        $content = $declared > $max ? '' : $request->getContent();
        if ($declared > $max || strlen($content) > $max) {
            return $this->refuse(413, 'body_too_large', "The request body is larger than {$max} bytes.", ['max_body_bytes' => $max], $request);
        }

        if ($request->is('graphql') && $this->batchSize($content) > max(1, (int) config('api.graphql_max_batch', 5))) {
            $maxBatch = max(1, (int) config('api.graphql_max_batch', 5));

            return $this->refuse(400, 'batch_too_large', "A batch holds at most {$maxBatch} operations: split it into several requests.", ['max_batch' => $maxBatch], $request);
        }

        return $next($request);
    }

    /** Nombre d'opérations d'un corps JSON dont la racine est une liste (1 sinon). */
    private function batchSize(string $content): int
    {
        if (! str_starts_with(ltrim($content), '[')) {
            return 1;
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? count($decoded) : 1;
    }

    /** @param  array<string, mixed>  $extensions */
    private function refuse(int $status, string $code, string $message, array $extensions, Request $request): JsonResponse
    {
        $error = ['message' => $message, 'extensions' => ['code' => $code, ...$extensions]];

        return $request->is('mcp')
            ? response()->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => $message]], $status)
            : response()->json(['errors' => [$error]], $status);
    }
}
