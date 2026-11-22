<?php

namespace App\Console\Commands;

use App\Support\ApiContract;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/**
 * Met à jour l'instantané du contrat public de l'API (`tests/Fixtures/api-contract.json`, App\Support\ApiContract).
 *
 * Les ajouts sont inscrits sans question. Un champ, un paramètre ou une valeur qui disparaît sans avoir été
 * marqué déprécié (`deprecated` dans la spécification, `@deprecated` en GraphQL) bloque la mise à jour, sauf
 * avec `--accept-removals` : à n'utiliser que pour un retrait annoncé de longue date et noté dans
 * `docs/API-CHANGELOG.md`. Écrire dans le fichier est la seule action : la base n'est pas modifiée.
 */
class ApiContractSnapshot extends Command
{
    protected $signature = 'vff:api-contract {--accept-removals : accepte les retraits et changements de type non annoncés}';

    protected $description = "Met à jour l'instantané du contrat de l'API publique (REST, GraphQL, MCP)";

    public function handle(Kernel $kernel): int
    {
        $current = ApiContract::build(
            $this->json($kernel, Request::create('/docs/api.json', 'GET')),
            app(SchemaBuilder::class)->schema(),
            (array) ($this->mcp($kernel, 'tools/list')['result']['tools'] ?? []),
            (array) ($this->mcp($kernel, 'prompts/list')['result']['prompts'] ?? []),
        );
        if (count($current) < 500) {
            $this->error('Contrat anormalement court ('.count($current).' entrées) : spécification ou schéma illisible, rien écrit.');

            return self::FAILURE;
        }

        $path = base_path(ApiContract::FILE);
        $recorded = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $breaking = ApiContract::breaking($recorded, $current);
        if (($breaking['removed'] !== [] || $breaking['changed'] !== []) && ! $this->option('accept-removals')) {
            foreach ($breaking['removed'] as $key) {
                $this->line("  retiré sans dépréciation : {$key}");
            }
            foreach ($breaking['changed'] as $line) {
                $this->line("  type changé : {$line}");
            }
            $this->error('Changements cassants : rétablissez la compatibilité (champ gardé et déprécié), ou relancez avec --accept-removals si le retrait est voulu et annoncé.');

            return self::FAILURE;
        }

        file_put_contents($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->info(count($current).' entrées écrites dans '.ApiContract::FILE.'.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function json(Kernel $kernel, Request $request): array
    {
        $request->headers->set('Accept', 'application/json');

        return (array) json_decode((string) $kernel->handle($request)->getContent(), true);
    }

    /** @return array<string, mixed> */
    private function mcp(Kernel $kernel, string $method): array
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-06-18',
        ], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) []], JSON_THROW_ON_ERROR));

        return (array) json_decode((string) $kernel->handle($request)->getContent(), true);
    }
}
