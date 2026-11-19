<?php

namespace App\Support;

/**
 * Droits de réutilisation de chaque source externe (registre versionné
 * database/data/source_rights.php) : fournisseur, licence, preuve écrite, statut.
 *
 * Statuts :
 *   - « sur »       : licence ouverte écrite (ou acte officiel non protégé), prouvée par un lien ;
 *   - « incertain » : accord oral, pas de licence, conditions floues ou contradictoires ;
 *   - « interdit »  : réutilisation exclue par les conditions de la source.
 *
 * Chaque table qui porte une colonne de provenance (line_events.source, source_traces.source,
 * station_sources.source, media.provider…) a sa table de correspondance « valeur → fournisseur ».
 * Sources composites (corrections manuelles, patrimoine) : le domaine du `source_url` désigne le
 * fournisseur quand il est déclaré (« manual » qui cite lignes-oubliees = lignes-oubliees),
 * sinon le fournisseur par défaut de la valeur.
 *
 * Lecture seule : ce registre ne retire ni ne filtre aucune donnée ; il sert aux mesures
 * (vff:source-rights --impact) et au contrôle qu'aucune source n'entre sans droits connus
 * (vff:source-rights --check).
 */
final class SourceRights
{
    public const SAFE = 'sur';

    public const UNCERTAIN = 'incertain';

    public const FORBIDDEN = 'interdit';

    public const STATUSES = [self::SAFE, self::UNCERTAIN, self::FORBIDDEN];

    /** @var array{providers: array<string, array<string, mixed>>, sources: array<string, array<string, string>>, composites: array<string, array<string, string>>} */
    private array $registry;

    /** @param array<string, mixed>|null $registry registre (tests) ; par défaut database/data/source_rights.php */
    public function __construct(?array $registry = null)
    {
        /** @var array{providers: array<string, array<string, mixed>>, sources: array<string, array<string, string>>, composites: array<string, array<string, string>>} $loaded */
        $loaded = $registry ?? require database_path('data/source_rights.php');
        $this->registry = $loaded + ['providers' => [], 'sources' => [], 'composites' => []];
    }

    /** @return array<string, array<string, mixed>> */
    public function providers(): array
    {
        return $this->registry['providers'];
    }

    /** @return array<string, mixed>|null */
    public function provider(string $key): ?array
    {
        return $this->registry['providers'][$key] ?? null;
    }

    public function status(string $provider): ?string
    {
        return $this->registry['providers'][$provider]['statut'] ?? null;
    }

    /**
     * Tables (et colonnes) couvertes par le registre : « table » => [valeur => fournisseur].
     *
     * @return array<string, array<string, string>>
     */
    public function sources(): array
    {
        return $this->registry['sources'];
    }

    /** @return array<string, array<string, string>> */
    public function composites(): array
    {
        return $this->registry['composites'];
    }

    /** La valeur `source` de cette table est-elle déclarée (directe ou composite) ? */
    public function knows(string $table, string $source): bool
    {
        return isset($this->registry['sources'][$table][$source]) || isset($this->registry['composites'][$table][$source]);
    }

    /**
     * Fournisseur d'une donnée : valeur de la colonne de provenance, et lien cité pour les
     * sources composites. null si la valeur n'est pas déclarée.
     */
    public function providerFor(string $table, ?string $source, ?string $url = null): ?string
    {
        if ($source === null) {
            return null;
        }
        if (isset($this->registry['composites'][$table][$source])) {
            return $this->providerForUrl($url) ?? $this->registry['composites'][$table][$source];
        }

        return $this->registry['sources'][$table][$source] ?? null;
    }

    /** Fournisseur dont un domaine déclaré héberge ce lien (domaine exact ou sous-domaine). */
    public function providerForUrl(?string $url): ?string
    {
        $host = $url !== null ? strtolower((string) parse_url($url, PHP_URL_HOST)) : '';
        if ($host === '') {
            return null;
        }
        foreach ($this->registry['providers'] as $key => $p) {
            foreach ($p['domaines'] ?? [] as $domain) {
                $domain = strtolower($domain);
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * La donnée de ce fournisseur peut-elle être redistribuée (exports GPX, KML et GeoJSON) ?
     * Seule une licence ouverte écrite et prouvée (statut « sur ») l'autorise : une source dont
     * l'usage est non commercial, l'accord oral ou le statut incertain, ou qui n'est pas déclarée
     * (fournisseur null), n'est jamais redistribuée.
     */
    public function allowsRedistribution(?string $provider): bool
    {
        return $provider !== null && $this->status($provider) === self::SAFE;
    }

    /** Statut d'une donnée (cf. providerFor) ; null si la source n'est pas déclarée. */
    public function statusFor(string $table, ?string $source, ?string $url = null): ?string
    {
        $provider = $this->providerFor($table, $source, $url);

        return $provider !== null ? $this->status($provider) : null;
    }

    /** @return list<string> fournisseurs de ce statut */
    public function providersWithStatus(string $status): array
    {
        return array_keys(array_filter($this->registry['providers'], fn (array $p) => ($p['statut'] ?? null) === $status));
    }

    /** @return list<string> valeurs directes de `source` de la table dont le fournisseur a ce statut */
    public function sourcesWithStatus(string $table, string $status): array
    {
        $out = [];
        foreach ($this->registry['sources'][$table] ?? [] as $source => $provider) {
            if ($this->status($provider) === $status) {
                $out[] = (string) $source;
            }
        }

        return $out;
    }

    /** @return list<string> domaines déclarés des fournisseurs de ce statut */
    public function domainsWithStatus(string $status): array
    {
        $out = [];
        foreach ($this->providersWithStatus($status) as $key) {
            foreach ($this->registry['providers'][$key]['domaines'] ?? [] as $d) {
                $out[] = strtolower($d);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Condition SQL (PostgreSQL) « la ligne vient d'une source de ce statut » pour une table
     * à colonne de provenance `$col` et lien `$urlCol` (sources composites). Valeurs échappées.
     */
    public function sqlCondition(string $table, string $status, string $col = 'source', ?string $urlCol = 'source_url'): string
    {
        $quote = fn (string $v) => "'".str_replace("'", "''", $v)."'";
        $parts = [];
        $direct = $this->sourcesWithStatus($table, $status);
        if ($direct) {
            $parts[] = "{$col} IN (".implode(', ', array_map($quote, $direct)).')';
        }
        $composites = $this->registry['composites'][$table] ?? [];
        $domains = $this->domainsWithStatus($status);
        // Lien d'un domaine déclaré avec ce statut, ou défaut de ce statut sans lien d'un fournisseur connu.
        $defaults = array_keys(array_filter($composites, fn ($p) => $this->status($p) === $status));
        if ($composites && $urlCol !== null && ($domains || $defaults)) {
            $in = implode(', ', array_map($quote, array_map('strval', array_keys($composites))));
            $byUrl = $domains
                ? "{$urlCol} ~* ".$quote('^https?://([^/]*\.)?('.implode('|', array_map(fn ($d) => preg_quote($d, null), $domains)).')(:[0-9]+)?(/|$)')
                : 'FALSE';
            $allDomains = [];
            foreach ($this->registry['providers'] as $p) {
                foreach ($p['domaines'] ?? [] as $d) {
                    $allDomains[] = strtolower($d);
                }
            }
            $known = $allDomains
                ? "{$urlCol} ~* ".$quote('^https?://([^/]*\.)?('.implode('|', array_map(fn ($d) => preg_quote($d, null), array_unique($allDomains))).')(:[0-9]+)?(/|$)')
                : 'FALSE';
            $default = $defaults
                ? "({$col} IN (".implode(', ', array_map($quote, array_map('strval', $defaults))).") AND NOT COALESCE({$known}, FALSE))"
                : 'FALSE';
            $parts[] = "({$col} IN ({$in}) AND (COALESCE({$byUrl}, FALSE) OR {$default}))";
        }

        return $parts ? '('.implode(' OR ', $parts).')' : 'FALSE';
    }
}
