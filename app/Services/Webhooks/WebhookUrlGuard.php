<?php

namespace App\Services\Webhooks;

/**
 * Protection SSRF des webhooks : une adresse n'est acceptée que si elle mène, en HTTPS, à une
 * adresse IP publique de l'Internet (IpAddressPolicy).
 *
 * La vérification a lieu à la création de l'abonnement ET avant chaque envoi : le nom est résolu
 * de nouveau, TOUTES ses adresses (A et AAAA) doivent être publiques, et la connexion est épinglée
 * à l'une d'elles (WebhookTarget::curlResolve). Les redirections ne sont jamais suivies
 * (WebhookSender). Refusés aussi : identifiants dans l'adresse, port autre que 443, noms locaux
 * (localhost, .local, .internal, .home.arpa, nom sans domaine) et écritures numériques déguisées
 * d'une adresse IP (« 2130706433 », « 0x7f.1 »), qu'un client HTTP lirait comme une IP.
 */
final readonly class WebhookUrlGuard
{
    public const MAX_LENGTH = 2048;

    /** Suffixes de noms réservés aux réseaux locaux ou internes. */
    private const LOCAL_SUFFIXES = ['localhost', 'local', 'internal', 'intranet', 'lan', 'localdomain', 'home.arpa', 'arpa', 'test', 'invalid', 'example'];

    public function __construct(private HostResolver $resolver) {}

    /**
     * Vérifie l'adresse et choisit l'adresse IP de la connexion.
     *
     * @throws WebhookUrlRefused
     */
    public function target(string $url): WebhookTarget
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > self::MAX_LENGTH || preg_match('/[\s\x00-\x1F\x7F\\\\]/', $url) === 1) {
            throw WebhookUrlRefused::because(WebhookUrlRefused::INVALID);
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw WebhookUrlRefused::because(WebhookUrlRefused::INVALID);
        }
        if (strtolower($parts['scheme']) !== 'https') {
            throw WebhookUrlRefused::because(WebhookUrlRefused::NOT_HTTPS);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw WebhookUrlRefused::because(WebhookUrlRefused::CREDENTIALS);
        }

        $port = $parts['port'] ?? 443;
        if (! in_array($port, array_map('intval', (array) config('webhooks.allowed_ports', [443])), true)) {
            throw WebhookUrlRefused::because(WebhookUrlRefused::PORT);
        }

        $host = rtrim(strtolower($parts['host']), '.');
        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        $rest = $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
        $authority = static fn (string $h): string => $port === 443 ? $h : "{$h}:{$port}";

        // Adresse IP écrite dans l'URL (IPv6 entre crochets).
        $literal = str_starts_with($host, '[') && str_ends_with($host, ']') ? substr($host, 1, -1) : $host;
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            if (! IpAddressPolicy::isPublic($literal)) {
                throw WebhookUrlRefused::because(WebhookUrlRefused::PRIVATE_ADDRESS);
            }
            $written = str_contains($literal, ':') ? '['.$literal.']' : $literal;

            return new WebhookTarget('https://'.$authority($written).$rest, $literal, $port, $literal, literal: true);
        }

        $host = $this->asciiHost($host);
        $ips = $this->resolver->resolve($host);
        if ($ips === []) {
            throw WebhookUrlRefused::because(WebhookUrlRefused::UNRESOLVED);
        }
        foreach ($ips as $ip) {
            if (! IpAddressPolicy::isPublic($ip)) {
                throw WebhookUrlRefused::because(WebhookUrlRefused::PRIVATE_ADDRESS);
            }
        }

        return new WebhookTarget('https://'.$authority($host).$rest, $host, $port, $ips[0]);
    }

    /**
     * Nom d'hôte publiable, en ASCII (IDN converti) : au moins deux étiquettes, domaine de premier
     * niveau alphabétique (ce qui écarte les écritures numériques d'une IP), aucun suffixe local.
     *
     * @throws WebhookUrlRefused
     */
    private function asciiHost(string $host): string
    {
        if (preg_match('/[^\x20-\x7E]/', $host) === 1) {
            $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
            if ($ascii === false) {
                throw WebhookUrlRefused::because(WebhookUrlRefused::INVALID);
            }
            $host = strtolower($ascii);
        }

        $labels = explode('.', $host);
        if (strlen($host) > 253 || count($labels) < 2) {
            throw WebhookUrlRefused::because(WebhookUrlRefused::HOST);
        }
        foreach ($labels as $label) {
            if (preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $label) !== 1) {
                throw WebhookUrlRefused::because(WebhookUrlRefused::INVALID);
            }
        }
        if (preg_match('/^([a-z]{2,63}|xn--[a-z0-9-]{1,59})$/', end($labels)) !== 1) {
            throw WebhookUrlRefused::because(WebhookUrlRefused::HOST);
        }
        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                throw WebhookUrlRefused::because(WebhookUrlRefused::HOST);
            }
        }

        return $host;
    }
}
