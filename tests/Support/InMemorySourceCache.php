<?php

namespace Tests\Support;

use App\Services\Materials\SourceCache;

/**
 * Cache de l'import du matériel tenu en mémoire : les tests n'écrivent rien dans storage/.
 */
class InMemorySourceCache extends SourceCache
{
    /** @var array<string, array<string, mixed>> */
    public array $tables = [];

    public function __construct()
    {
        parent::__construct('');
    }

    public function load(string $name): array
    {
        return $this->tables[$name] ?? [];
    }

    public function save(string $name, array $data): void
    {
        $this->tables[$name] = $data;
    }
}
