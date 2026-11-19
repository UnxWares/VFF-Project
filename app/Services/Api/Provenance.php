<?php

namespace App\Services\Api;

use App\Support\SourceRights;

/**
 * Provenance et licence d'une donnée de l'API publique, d'après le registre des droits
 * (database/data/source_rights.php, voir SourceRights).
 *
 * Chaque événement porte la valeur de sa colonne `source` : on en tire le fournisseur (nom
 * lisible, licence) pour que le réutilisateur sache sous quelles conditions il peut s'en servir.
 * `license_status` : `verified` (licence ouverte écrite, prouvée), `uncertain` (autorisation
 * particulière ou conditions floues : lire `license` et citer la source), `forbidden`.
 */
final class Provenance
{
    private const STATUSES = [
        SourceRights::SAFE => 'verified',
        SourceRights::UNCERTAIN => 'uncertain',
        SourceRights::FORBIDDEN => 'forbidden',
    ];

    public function __construct(private readonly SourceRights $rights) {}

    /**
     * @param  string  $table  table du registre (`line_events`, `station_events`…)
     * @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string}
     */
    public function for(string $table, ?string $source, ?string $url = null): array
    {
        $key = $this->rights->providerFor($table, $source, $url);
        $provider = $key !== null ? $this->rights->provider($key) : null;

        return [
            'provider' => $key,
            'name' => $provider['nom'] ?? null,
            'license' => $provider['licence'] ?? null,
            'license_status' => isset($provider['statut']) ? (self::STATUSES[$provider['statut']] ?? null) : null,
        ];
    }
}
