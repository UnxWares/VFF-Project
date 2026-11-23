<?php

namespace App\Mcp\Support;

use App\Support\SourceRights;

/**
 * Sources dont l'usage est réservé à VFF, sans usage commercial (archeoferroviaire : mentions légales du site,
 * accord oral pour le site seulement) : leurs données ne sortent jamais du serveur MCP, dont les réponses
 * peuvent être reprises par une IA ou une application commerciale. Les faits que VFF en a tirés par calcul
 * (statut d'un tronçon à une ère, longueur, distance arrondie) restent servis, sans géométrie, sans texte ni
 * lien de la source.
 *
 * Une donnée est écartée quand le fournisseur de sa provenance (registre database/data/source_rights.php,
 * SourceRights::providerFor, y compris par le lien cité d'une source composite) est l'un de PROVIDERS.
 */
final class NonCommercialSources
{
    /** Fournisseurs à usage non commercial, jamais servis par le MCP. */
    public const PROVIDERS = ['archeoferroviaire'];

    public function __construct(private readonly SourceRights $rights) {}

    /** Vrai si la donnée (table du registre, valeur de provenance, lien cité) vient d'une source non commerciale. */
    public function excludes(string $table, ?string $source, ?string $url = null): bool
    {
        return in_array($this->rights->providerFor($table, $source, $url), self::PROVIDERS, true)
            || in_array($this->rights->providerForUrl($url), self::PROVIDERS, true);
    }

    /**
     * Condition SQL (PostgreSQL) « la ligne ne vient pas d'une source non commerciale » : ni une valeur de
     * provenance de ces fournisseurs, ni un lien vers l'un de leurs domaines. Valeurs échappées.
     */
    public function sqlKept(string $table, string $column = 'source', ?string $urlColumn = 'source_url'): string
    {
        $quote = fn (string $v): string => "'".str_replace("'", "''", $v)."'";
        $values = [];
        foreach ($this->rights->sources()[$table] ?? [] as $value => $provider) {
            if (in_array($provider, self::PROVIDERS, true)) {
                $values[] = (string) $value;
            }
        }
        $domains = [];
        foreach (self::PROVIDERS as $key) {
            foreach ($this->rights->provider($key)['domaines'] ?? [] as $domain) {
                $domains[] = preg_quote(strtolower((string) $domain), null);
            }
        }
        $parts = [];
        if ($values !== []) {
            $parts[] = "COALESCE({$column}, '') NOT IN (".implode(', ', array_map($quote, $values)).')';
        }
        if ($urlColumn !== null && $domains !== []) {
            $parts[] = "COALESCE({$urlColumn}, '') !~* ".$quote('^https?://([^/]*\.)?('.implode('|', $domains).')(:[0-9]+)?(/|$)');
        }

        return $parts === [] ? 'TRUE' : '('.implode(' AND ', $parts).')';
    }
}
