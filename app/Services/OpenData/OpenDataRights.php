<?php

namespace App\Services\OpenData;

use App\Support\SourceRights;

/**
 * Règle des droits des jeux de données ouverts (vff:export-open-data), lue dans le registre
 * database/data/source_rights.php.
 *
 * Les jeux sont publiés sous ODbL 1.0 et déclarés réutilisables à des fins commerciales. Une donnée
 * n'y entre que si son fournisseur :
 *   - a une licence ouverte écrite et prouvée (statut « sur », SourceRights::allowsRedistribution) ;
 *   - n'interdit pas l'usage commercial (licence « non commerciale » ou « usage personnel ») ;
 * une source absente du registre n'entre jamais. Tout ce qui est écarté est compté (OpenDataLedger)
 * sans nommer la source dans les fichiers publiés.
 *
 * Contrôle final : assertAllowed() lève OpenDataLicenceViolation si un fournisseur non autorisé
 * figure dans un jeu ; la commande échoue alors sans publier le catalogue.
 */
final class OpenDataRights
{
    /** Licence de tous les jeux (partage à l'identique imposé par OpenStreetMap et SNCF Open Data). */
    public const LICENCE = 'ODbL-1.0';

    public const LICENCE_NAME = 'Open Database License (ODbL) 1.0';

    public const LICENCE_URL = 'https://opendatacommons.org/licenses/odbl/1-0/';

    /** Raisons d'un retrait, reprises dans le LISEZMOI et la page « Données ouvertes ». */
    public const NOT_OPEN = 'licence_not_open';

    public const NON_COMMERCIAL = 'non_commercial';

    public const UNKNOWN_SOURCE = 'unknown_source';

    /** Licence qui exclut la réutilisation commerciale (texte du registre). */
    private const NON_COMMERCIAL_PATTERN = '/non[\s-]commercia|usage personnel|personal use|non-commercial/iu';

    public function __construct(private readonly SourceRights $rights, private readonly bool $commercial = true) {}

    public function registry(): SourceRights
    {
        return $this->rights;
    }

    /** Les jeux sont-ils déclarés réutilisables à des fins commerciales ? */
    public function commercial(): bool
    {
        return $this->commercial;
    }

    /** Fournisseur d'une valeur de provenance d'une table du registre (null : valeur inconnue). */
    public function provider(string $table, ?string $source, ?string $url = null): ?string
    {
        return $this->rights->providerFor($table, $source, $url);
    }

    /** La licence de ce fournisseur exclut-elle l'usage commercial ? */
    public function nonCommercial(string $provider): bool
    {
        $licence = (string) ($this->rights->provider($provider)['licence'] ?? '');

        return preg_match(self::NON_COMMERCIAL_PATTERN, $licence) === 1;
    }

    /** Raison pour laquelle ce fournisseur n'entre pas dans un jeu, ou null s'il y entre. */
    public function refusal(?string $provider): ?string
    {
        if ($provider === null || $this->rights->provider($provider) === null) {
            return self::UNKNOWN_SOURCE;
        }
        if (! $this->rights->allowsRedistribution($provider)) {
            return self::NOT_OPEN;
        }
        if ($this->commercial && $this->nonCommercial($provider)) {
            return self::NON_COMMERCIAL;
        }

        return null;
    }

    public function allows(?string $provider): bool
    {
        return $this->refusal($provider) === null;
    }

    /** Une valeur de provenance (table du registre, lien cité pour les sources composites) entre-t-elle ? */
    public function allowsSource(string $table, ?string $source, ?string $url = null): bool
    {
        return $this->allows($this->provider($table, $source, $url));
    }

    /**
     * Valeurs directes de la colonne de provenance d'une table dont le fournisseur entre dans un jeu.
     *
     * @return list<string>
     */
    public function allowedSources(string $table): array
    {
        $out = [];
        foreach ($this->rights->sources()[$table] ?? [] as $source => $provider) {
            if ($this->allows($provider)) {
                $out[] = (string) $source;
            }
        }

        return $out;
    }

    /** Condition SQL « valeur de `$column` autorisée » (valeurs directes de la table, échappées). */
    public function sqlAllowed(string $table, string $column): string
    {
        $values = $this->allowedSources($table);

        return $values === []
            ? 'FALSE'
            : "COALESCE({$column} IN (".implode(', ', array_map(fn (string $v): string => "'".str_replace("'", "''", $v)."'", $values)).'), FALSE)';
    }

    /**
     * Contrôle final d'un jeu : chaque fournisseur cité doit être autorisé.
     *
     * @param  iterable<string>  $providers
     *
     * @throws OpenDataLicenceViolation
     */
    public function assertAllowed(string $dataset, iterable $providers): void
    {
        $refused = [];
        foreach ($providers as $provider) {
            if (! $this->allows($provider)) {
                $refused[] = $provider;
            }
        }
        if ($refused !== []) {
            throw new OpenDataLicenceViolation($dataset, $refused);
        }
    }

    /**
     * Crédits d'un jeu : nom, licence courte et lien de preuve de chaque fournisseur cité, dans l'ordre du registre.
     *
     * @param  iterable<string>  $providers
     * @return list<array{key: string, name: string, licence: string, url: ?string}>
     */
    public function attribution(iterable $providers): array
    {
        $wanted = [];
        foreach ($providers as $p) {
            $wanted[$p] = true;
        }
        $out = [];
        foreach ($this->rights->providers() as $key => $info) {
            if (! isset($wanted[$key])) {
                continue;
            }
            $proof = is_array($info['preuve'] ?? null) ? ($info['preuve'][0] ?? null) : null;
            $out[] = [
                'key' => (string) $key,
                'name' => (string) ($info['nom'] ?? $key),
                'licence' => self::shortLicence((string) $key, (string) ($info['licence'] ?? '')),
                'url' => is_string($proof) ? $proof : null,
            ];
        }

        return $out;
    }

    /** Nom court de la licence d'un fournisseur (« ODbL 1.0 », « Licence Ouverte 2.0 », « CC0 1.0 »…). */
    public static function shortLicence(string $provider, string $licence): string
    {
        // Le travail propre de VFF entre dans la base sous la licence de la base.
        if ($provider === 'vff') {
            return 'ODbL 1.0';
        }
        foreach ([
            '/\bODbL(?: 1\.0)?/u' => 'ODbL 1.0',
            '/Licence Ouverte(?: \(Etalab\))? ?2\.0|Licence Ouverte 2\.0/u' => 'Licence Ouverte 2.0',
            '/Licence Ouverte/u' => 'Licence Ouverte',
            '/\bCC0 1\.0/u' => 'CC0 1.0',
            '/\bCC BY-SA 4\.0/u' => 'CC BY-SA 4.0',
        ] as $pattern => $label) {
            if (preg_match($pattern, $licence) === 1) {
                return $label;
            }
        }

        return $licence;
    }
}
