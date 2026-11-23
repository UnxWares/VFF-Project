<?php

namespace App\Services\OpenData;

/**
 * Registre de fabrication d'un jeu de données ouvert : fournisseurs cités (crédits et contrôle des
 * droits), lignes écrites par fichier, et tout ce qui a été écarté ou remplacé faute de licence
 * ouverte, compté par élément (ex. `sections.geometry`), par action (`removed`, `replaced`) et par
 * raison (OpenDataRights::NOT_OPEN…). Les sources écartées ne sont jamais nommées ici.
 */
final class OpenDataLedger
{
    public const REMOVED = 'removed';

    public const REPLACED = 'replaced';

    /** @var array<string, true> */
    private array $providers = [];

    /** @var array<string, int> */
    private array $rows = [];

    /** @var array<string, array<string, array<string, int>>> */
    private array $withheld = [];

    public function cite(?string $provider): void
    {
        if ($provider !== null && $provider !== '') {
            $this->providers[$provider] = true;
        }
    }

    public function count(string $file, int $rows): void
    {
        $this->rows[$file] = $rows;
    }

    public function withhold(string $item, string $reason, string $action = self::REMOVED, int $n = 1): void
    {
        $this->withheld[$item][$action][$reason] = ($this->withheld[$item][$action][$reason] ?? 0) + $n;
    }

    /** @return list<string> fournisseurs cités, triés */
    public function providers(): array
    {
        $out = array_keys($this->providers);
        sort($out);

        return $out;
    }

    /** @return array<string, int> */
    public function rows(): array
    {
        ksort($this->rows);

        return $this->rows;
    }

    /** @return array<string, array<string, array<string, int>>> élément => action => raison => nombre, triés */
    public function withheld(): array
    {
        $out = $this->withheld;
        ksort($out);
        foreach ($out as &$actions) {
            ksort($actions);
            foreach ($actions as &$reasons) {
                ksort($reasons);
            }
        }

        return $out;
    }

    /** Total des éléments écartés ou remplacés pour une action. */
    public function total(string $action): int
    {
        $n = 0;
        foreach ($this->withheld as $actions) {
            $n += array_sum($actions[$action] ?? []);
        }

        return $n;
    }
}
