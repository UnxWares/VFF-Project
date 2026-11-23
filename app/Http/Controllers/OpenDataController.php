<?php

namespace App\Http\Controllers;

use App\Services\OpenData\OpenDataCatalogue;
use App\Services\OpenData\OpenDataRights;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Données ouvertes » (/open-data) : jeux produits à la main par vff:export-open-data, lus dans le
 * catalogue (public/downloads/open-data/catalogue.json) ; les fichiers sont servis en statique.
 */
class OpenDataController extends Controller
{
    public function __invoke(): Response
    {
        $base = '/'.OpenDataCatalogue::PUBLIC_DIR;
        $datasets = [];
        $credits = [];
        $withheld = [];
        foreach (OpenDataCatalogue::read() as $d) {
            $id = (string) $d['id'];
            $files = [];
            foreach (is_array($d['files'] ?? null) ? $d['files'] : [] as $f) {
                $files[] = ['name' => (string) $f['name'], 'url' => "{$base}/{$id}/".rawurlencode((string) $f['name']),
                    'bytes' => (int) ($f['bytes'] ?? 0), 'rows' => isset($f['rows']) ? (int) $f['rows'] : null];
            }
            foreach (is_array($d['credits'] ?? null) ? $d['credits'] : [] as $c) {
                $credits[(string) $c['key']] ??= ['key' => (string) $c['key'], 'name' => (string) $c['name'],
                    'licence' => (string) $c['licence'], 'url' => isset($c['url']) ? (string) $c['url'] : null];
            }
            foreach (is_array($d['withheld'] ?? null) ? $d['withheld'] : [] as $item => $actions) {
                foreach ((array) $actions as $action => $reasons) {
                    $withheld[$item][$action] = ($withheld[$item][$action] ?? 0) + array_sum(array_map('intval', (array) $reasons));
                }
            }
            $datasets[] = [
                'id' => $id,
                'kind' => (string) ($d['kind'] ?? ''),
                'era' => isset($d['era']) ? (int) $d['era'] : null,
                'generated_on' => (string) ($d['generated_on'] ?? ''),
                'archive' => ['url' => "{$base}/".rawurlencode((string) ($d['archive']['name'] ?? "{$id}.zip")),
                    'bytes' => (int) ($d['archive']['bytes'] ?? 0)],
                'files' => $files,
                'removed' => (int) ($d['removed'] ?? 0),
                'replaced' => (int) ($d['replaced'] ?? 0),
            ];
        }
        ksort($withheld);

        return Inertia::render('OpenData', [
            'datasets' => $datasets,
            'credits' => array_values($credits),
            'withheld' => array_map(fn (array $a): array => ['removed' => $a['removed'] ?? 0, 'replaced' => $a['replaced'] ?? 0], $withheld),
            'licence' => ['code' => OpenDataRights::LICENCE, 'name' => OpenDataRights::LICENCE_NAME, 'url' => OpenDataRights::LICENCE_URL],
        ]);
    }
}
