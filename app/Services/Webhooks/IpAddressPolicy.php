<?php

namespace App\Services\Webhooks;

/**
 * Adresses IP vers lesquelles un webhook peut partir : seulement les adresses publiques de
 * l'Internet (protection SSRF).
 *
 * Refusées en IPv4 : réseaux privés (RFC 1918), partage d'opérateur (100.64.0.0/10, où se trouve
 * aussi l'adresse de métadonnées d'Alibaba Cloud), bouclage, lien local (169.254.0.0/16 : métadonnées
 * des clouds), « ce réseau », documentation, tests de performance, relais 6to4, multidiffusion et
 * réservées. En IPv6, seule la plage mondiale 2000::/3 est admise, moins la documentation, Teredo
 * et les blocs de protocoles de l'IETF (2001::/23) et 6to4 (2002::/16) ; tout le reste est refusé :
 * bouclage, adresses locales uniques (fc00::/7, dont fd00:ec2::254 d'AWS), lien local, multidiffusion,
 * adresses IPv4 transposées (::ffff:0:0/96) et NAT64 (64:ff9b::/96), qui mèneraient à une adresse
 * IPv4 interne.
 */
final class IpAddressPolicy
{
    /** @var list<string> */
    private const IPV4_BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.31.196.0/24', '192.52.193.0/24', '192.88.99.0/24',
        '192.168.0.0/16', '192.175.48.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
        '224.0.0.0/4', '240.0.0.0/4',
    ];

    /** Seule plage IPv6 admise (adresses mondiales). */
    private const IPV6_ALLOWED = '2000::/3';

    /** @var list<string> */
    private const IPV6_BLOCKED = ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];

    public static function isPublic(string $ip): bool
    {
        $binary = @inet_pton($ip);
        if ($binary === false) {
            return false;
        }

        if (strlen($binary) === 4) {
            foreach (self::IPV4_BLOCKED as $cidr) {
                if (self::inRange($binary, $cidr)) {
                    return false;
                }
            }

            return true;
        }

        if (! self::inRange($binary, self::IPV6_ALLOWED)) {
            return false;
        }
        foreach (self::IPV6_BLOCKED as $cidr) {
            if (self::inRange($binary, $cidr)) {
                return false;
            }
        }

        return true;
    }

    /** L'adresse (forme binaire de inet_pton) appartient-elle au bloc CIDR, de la même famille ? */
    private static function inRange(string $binary, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $net = (string) inet_pton($network);
        if (strlen($net) !== strlen($binary)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($binary, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($binary[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
