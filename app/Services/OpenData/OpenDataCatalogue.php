<?php

namespace App\Services\OpenData;

use RuntimeException;

/**
 * Catalogue des jeux de données ouverts (catalogue.json à la racine du dossier des jeux, public/downloads/open-data
 * par défaut) : écrit par vff:export-open-data, lu par la page « Données ouvertes ». Les fichiers sont
 * servis en statique sous /downloads/open-data/.
 */
final class OpenDataCatalogue
{
    public const FILE = 'catalogue.json';

    /** Dossier public des jeux et adresse sous laquelle ils sont servis. */
    public const PUBLIC_DIR = 'downloads/open-data';

    /** Ordre des jeux thématiques, avant les réseaux par ère. */
    public const KINDS = ['network', 'events', 'accidents', 'materials'];

    public static function publicRoot(): string
    {
        // Dossier lu par la page ; les tests le remplacent par config('open_data.root').
        $root = config('open_data.root');

        return is_string($root) && $root !== '' ? $root : public_path(self::PUBLIC_DIR);
    }

    /** @return list<array<string, mixed>> jeux du catalogue (vide si aucun export n'a encore été fait) */
    public static function read(?string $root = null): array
    {
        $path = ($root ?? self::publicRoot()).'/'.self::FILE;
        $json = is_file($path) ? file_get_contents($path) : false;
        $data = $json !== false ? json_decode($json, true) : null;

        return is_array($data) && is_array($data['datasets'] ?? null) ? array_values(array_filter($data['datasets'], 'is_array')) : [];
    }

    /**
     * Remplace les entrées de ces jeux dans le catalogue et l'écrit (fichier temporaire puis renommage).
     *
     * @param  list<array<string, mixed>>  $entries
     */
    public static function merge(string $root, array $entries): void
    {
        $byId = [];
        foreach ([...self::read($root), ...$entries] as $entry) {
            $byId[(string) $entry['id']] = $entry;
        }
        $datasets = array_values($byId);
        usort($datasets, fn (array $a, array $b): int => [self::rank($a), (int) ($a['era'] ?? 0), $a['id']] <=> [self::rank($b), (int) ($b['era'] ?? 0), $b['id']]);

        $json = json_encode(['format' => 1, 'licence' => OpenDataRights::LICENCE, 'datasets' => $datasets],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $tmp = "{$root}/.".self::FILE.'.tmp';
        if (file_put_contents($tmp, $json) === false || ! rename($tmp, "{$root}/".self::FILE)) {
            throw new RuntimeException("Impossible d'écrire {$root}/".self::FILE.'.');
        }
    }

    /** @param  array<string, mixed>  $entry */
    private static function rank(array $entry): int
    {
        // Jeux thématiques d'abord (événements, accidents, matériel), puis le réseau par ère.
        $kind = (string) ($entry['kind'] ?? '');

        return $kind === 'network' ? 10 : (int) array_search($kind, self::KINDS, true);
    }
}
