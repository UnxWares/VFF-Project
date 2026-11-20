<?php

namespace App\Services\Api;

use App\Mcp\Support\Attribution;
use App\Support\SourceRights;

/**
 * Registre public des sources et de leurs licences (database/data/source_rights.php), pour l'API
 * REST (`/api/v1/sources`) et GraphQL (`sources`). Le serveur MCP publie la même fiche par
 * fournisseur (Attribution::publicProvider, outil sources_and_licences).
 *
 * Publié : nom, licence, solidité de la licence, mention à reproduire, phrase exacte de la licence,
 * pages qui l'établissent et date de lecture, domaines des liens, valeurs de provenance (`source`)
 * qui renvoient au fournisseur. Jamais publié : les notes internes du registre (accords, risques,
 * obligations rédigées pour l'équipe, remarques de travail).
 */
final class SourceRegistry
{
    /** Licence de la base VFF elle-même. */
    public const DATABASE_LICENCE = [
        'name' => 'Open Database License (ODbL) 1.0',
        'url' => 'https://opendatacommons.org/licenses/odbl/1-0/',
        'attribution' => 'VFF - Voies Ferrées de France (https://vff-project.org)',
    ];

    private const STATUSES = [
        SourceRights::SAFE => 'verified',
        SourceRights::UNCERTAIN => 'uncertain',
        SourceRights::FORBIDDEN => 'forbidden',
    ];

    public function __construct(private readonly SourceRights $rights, private readonly Attribution $attribution) {}

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->rights->providers());
    }

    /** @return list<array<string, mixed>> */
    public function all(string $lang = 'fr'): array
    {
        return array_values(array_filter(array_map(fn (string $k) => $this->find($k, $lang), $this->keys())));
    }

    /** @return array<string, mixed>|null */
    public function find(string $key, string $lang = 'fr'): ?array
    {
        $p = $this->rights->provider($key);
        if ($p === null) {
            return null;
        }
        $public = $this->attribution->publicProvider($key, $lang);

        return [
            'provider' => $key,
            'name' => (string) ($p['nom'] ?? $key),
            'licence' => (string) ($p['licence'] ?? ''),
            'license_status' => self::STATUSES[$p['statut'] ?? ''] ?? null,
            'open_licence' => (bool) ($public['open_licence'] ?? false),
            'mention' => $public['mention'] ?? null,
            'citation' => isset($p['citation']) ? (string) $p['citation'] : null,
            'proof' => array_values(array_map('strval', (array) ($p['preuve'] ?? []))),
            'checked_on' => isset($p['lu_le']) ? (string) $p['lu_le'] : null,
            'domains' => array_values(array_map('strval', (array) ($p['domaines'] ?? []))),
            'source_values' => $this->valuesOf($key),
        ];
    }

    /**
     * Valeurs de provenance qui désignent ce fournisseur, par table (`line_events` => [`wikipedia`…]),
     * y compris les valeurs composites dont il est le fournisseur par défaut.
     *
     * @return array<string, list<string>>
     */
    private function valuesOf(string $key): array
    {
        $out = [];
        foreach ([$this->rights->sources(), $this->rights->composites()] as $mapping) {
            foreach ($mapping as $table => $values) {
                foreach ($values as $value => $provider) {
                    if ($provider === $key) {
                        $out[$table][] = (string) $value;
                    }
                }
            }
        }
        ksort($out);

        return array_map(fn (array $v) => array_values(array_unique($v)), $out);
    }
}
