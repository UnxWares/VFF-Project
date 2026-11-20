<?php

namespace App\Services\Webhooks;

/**
 * Destination vérifiée d'un envoi : adresse normalisée (hôte en minuscules, sans point final ni
 * fragment), nom d'hôte, port et adresse IP publique à laquelle la connexion est épinglée
 * (CURLOPT_RESOLVE). Le nom, résolu juste avant l'envoi, ne peut donc pas changer d'adresse entre
 * la vérification et la connexion (DNS rebinding).
 */
final readonly class WebhookTarget
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $ip,
        public bool $literal = false,
    ) {}

    /**
     * Entrée de CURLOPT_RESOLVE (« hôte:port:adresse », l'adresse IPv6 entre crochets), ou null pour
     * une adresse IP écrite dans l'URL : elle est déjà la destination, sans DNS.
     */
    public function curlResolve(): ?string
    {
        if ($this->literal) {
            return null;
        }
        $ip = str_contains($this->ip, ':') ? '['.$this->ip.']' : $this->ip;

        return "{$this->host}:{$this->port}:{$ip}";
    }
}
