<?php

namespace App\Services\Sncf;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Téléchargement d'un jeu SNCF Open Data (portail data.sncf.com, API Opendatasoft v2.1) avec cache local.
 *
 * Les métadonnées sont lues d'abord : la licence (ODbL) et l'éditeur (SNCF Réseau) sont vérifiés à chaque
 * téléchargement, et rien n'est gardé s'ils changent. L'export JSON (sans les tracés) est ensuite écrit dans
 * `<dossier>/<jeu>.json`, avec un fichier `.nobackup` dans le dossier (cache régénérable, hors sauvegarde).
 * Un cache présent et non vide est relu tel quel, sauf avec `$refresh`.
 */
final class SncfOpenDataCache
{
    public const API = 'https://data.sncf.com/api/explore/v2.1/catalog/datasets/';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    /**
     * @param  list<string>  $fields  champs exportés (`select` de l'API)
     * @return array{records: list<array<string, mixed>>, license: string, modified: ?string, fetched_at: ?string}
     *
     * @throws \RuntimeException jeu injoignable, en erreur, vide, ou licence et éditeur inattendus
     */
    public function load(string $dataset, array $fields, string $dir, bool $refresh = false, string $publisher = 'SNCF Réseau'): array
    {
        $dir = rtrim($dir, '/');
        $cache = $dir.'/'.$dataset.'.json';
        if (! $refresh && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached) && is_array($cached['records'] ?? null) && $cached['records'] !== []) {
                return ['records' => array_values($cached['records']), 'license' => (string) ($cached['license'] ?? '?'),
                    'modified' => $cached['modified'] ?? null, 'fetched_at' => $cached['fetched_at'] ?? null];
            }
        }

        $http = Http::withUserAgent(self::USER_AGENT)->timeout(120);
        try {
            $meta = $http->get(self::API.$dataset);
            $export = $meta->ok() ? $http->get(self::API.$dataset.'/exports/json', ['select' => implode(',', $fields), 'limit' => -1]) : null;
        } catch (ConnectionException) {
            throw new \RuntimeException("SNCF Open Data injoignable ({$dataset}) : rien lu.");
        }
        if (! $meta->ok() || ! $export?->ok()) {
            throw new \RuntimeException("SNCF Open Data a répondu une erreur ({$dataset}) : rien lu.");
        }
        $default = (array) ($meta->json('metas.default') ?? []);
        $license = (string) ($default['license'] ?? '');
        if (! str_contains($license, 'ODbL') || ($default['publisher'] ?? '') !== $publisher) {
            throw new \RuntimeException("Licence ou éditeur inattendus pour {$dataset} (« {$license} », « ".($default['publisher'] ?? '').' ») : rien lu.');
        }
        $records = $export->json();
        if (! is_array($records) || $records === []) {
            throw new \RuntimeException("Jeu {$dataset} vide : rien lu.");
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Cache régénérable : hors sauvegarde (convention .nobackup du poste de développement).
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }
        $modified = isset($default['modified']) ? substr((string) $default['modified'], 0, 10) : null;
        $fetchedAt = now()->toDateTimeString();
        file_put_contents($cache, json_encode(['dataset' => $dataset, 'fetched_at' => $fetchedAt, 'license' => $license,
            'modified' => $modified, 'records' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['records' => array_values($records), 'license' => $license, 'modified' => $modified, 'fetched_at' => $fetchedAt];
    }
}
