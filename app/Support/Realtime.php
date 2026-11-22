<?php

namespace App\Support;

/**
 * État du temps réel (config/realtime.php) : actif seulement si REALTIME_ENABLED est vrai ET si la
 * diffusion vise un serveur WebSocket (Reverb, ou serveur compatible Pusher auto-hébergé). Tant que
 * ce n'est pas le cas, aucun événement ne part, les abonnements GraphQL sont refusés et le site ne
 * charge pas Laravel Echo.
 */
final class Realtime
{
    /** Connexions de config/broadcasting.php qui parlent le protocole Pusher (WebSocket). */
    public const WEBSOCKET_CONNECTIONS = ['reverb', 'pusher'];

    public static function enabled(): bool
    {
        return (bool) config('realtime.enabled')
            && in_array(config('broadcasting.default'), self::WEBSOCKET_CONNECTIONS, true);
    }

    /**
     * Réglages de Laravel Echo transmis à la page (clé publique et adresse publique du serveur, jamais
     * le secret), ou null si le temps réel est désactivé ou incomplet.
     *
     * @return array{key: string, host: string, port: int, scheme: string, auth_endpoint: string}|null
     */
    public static function clientConfig(): ?array
    {
        $key = config('realtime.client.key');
        $host = config('realtime.client.host');
        if (! self::enabled() || ! is_string($key) || $key === '' || ! is_string($host) || $host === '') {
            return null;
        }
        $scheme = config('realtime.client.scheme') === 'http' ? 'http' : 'https';

        return [
            'key' => $key,
            'host' => $host,
            'port' => (int) config('realtime.client.port', 443),
            'scheme' => $scheme,
            'auth_endpoint' => '/broadcasting/auth',
        ];
    }

    /** Origine WebSocket à admettre dans la CSP (connect-src), ou null. */
    public static function websocketOrigin(): ?string
    {
        $client = self::clientConfig();
        if ($client === null) {
            return null;
        }
        $default = $client['scheme'] === 'https' ? 443 : 80;
        $port = $client['port'] === $default ? '' : ':'.$client['port'];

        return ($client['scheme'] === 'https' ? 'wss' : 'ws').'://'.$client['host'].$port;
    }
}
