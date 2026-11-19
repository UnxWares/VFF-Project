<?php

namespace App\Services\Webhooks;

/** Résolution DNS d'un nom d'hôte (remplacée dans les tests : aucune requête réelle). */
interface HostResolver
{
    /**
     * Adresses IPv4 et IPv6 du nom, liste vide s'il ne se résout pas.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
