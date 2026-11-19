<?php

namespace Tests\Support;

use App\Services\Webhooks\HostResolver;

/** Résolveur DNS des tests : réponses fixées par nom, aucune requête réelle ; compte les résolutions. */
final class FakeHostResolver implements HostResolver
{
    /** @var array<string, int> */
    public array $lookups = [];

    /** @param  array<string, list<string>>  $answers */
    public function __construct(public array $answers = []) {}

    public function resolve(string $host): array
    {
        $this->lookups[$host] = ($this->lookups[$host] ?? 0) + 1;

        return $this->answers[$host] ?? [];
    }
}
