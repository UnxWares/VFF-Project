<?php

namespace App\Services\Webhooks;

/**
 * Résolution par le résolveur du système : enregistrements A et AAAA (les CNAME sont suivis par le
 * résolveur). Toutes les adresses sont rendues, pour que WebhookUrlGuard les vérifie toutes.
 */
final class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $ips = [];
        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $field) {
            $records = @dns_get_record($host, $type);
            foreach (is_array($records) ? $records : [] as $record) {
                if (is_string($record[$field] ?? null)) {
                    $ips[] = $record[$field];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
