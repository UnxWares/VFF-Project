<?php

namespace App\Services\NetworkGraph;

/**
 * Export CSV d'une liste de diagnostics du graphe (/validate/graph/{year}/diagnostics/{check}.csv),
 * pour un traitement en masse : toute la liste (après le filtre des jonctions), écrite ligne par
 * ligne dans le flux de la réponse.
 *
 * Format : UTF-8 avec BOM (lu tel quel par les tableurs), virgule entre les champs, point décimal,
 * en-têtes en français quelle que soit la langue de l'interface. Les types, défauts et couches sont
 * écrits en clair, en français. Distances en mètres, coordonnées en degrés WGS 84. Un texte qui
 * commence par =, +, -, @ ou une tabulation est précédé d'une apostrophe (pas de formule exécutée
 * par le tableur).
 */
final class GraphDiagnosticsCsv
{
    /** Nom de chaque liste dans le nom du fichier. */
    private const FILE_NAMES = [
        'islands' => 'iles-topologie',
        'station_islands' => 'iles-gares',
        'isolated_nodes' => 'noeuds-sans-arete',
        'stations_without_edge' => 'gares-sans-arete',
        'edges' => 'aretes-anormales',
        'junctions' => 'jonctions',
        'fragmented_lines' => 'lignes-en-morceaux',
    ];

    /** Nom des types de jonctions dans le nom du fichier. */
    private const TYPE_NAMES = ['spread' => 'ecartees', 'connector' => 'raccords', 'jump' => 'sauts'];

    private const BBOX = ['Ouest', 'Sud', 'Est', 'Nord'];

    /** En-têtes de chaque liste. */
    private const HEADERS = [
        'islands' => ['Identifiant', 'Nœuds', 'Arêtes', 'Longueur (m)', 'Gares', 'Lignes', ...self::BBOX],
        'station_islands' => ['Identifiant', 'Gares', 'Arêtes', 'Longueur (m)', 'Exemples de gares', ...self::BBOX],
        'isolated_nodes' => ['Identifiant', 'Type de nœud', 'Gare (identifiant)', 'Longitude', 'Latitude'],
        'stations_without_edge' => ['Identifiant', 'Gare', 'Slug', 'Défaut', 'Longitude', 'Latitude'],
        'edges' => ['Couche', 'Identifiant', 'Défaut', 'Longueur enregistrée (m)', 'Longueur mesurée (m)', 'Raccord', 'Ligne', 'Numéro de ligne', 'Longitude', 'Latitude'],
        'junctions' => ['Type', 'Identifiant', 'Écart (m)', 'Type de nœud', 'Gare (identifiant)', 'Extrémités', 'Nombre de lignes', 'Lignes', 'Longitude', 'Latitude'],
        'fragmented_lines' => ['Ligne', 'Numéro de ligne', 'Slug', 'Morceaux', 'Arêtes', 'Longueur (m)', ...self::BBOX],
    ];

    public function __construct(private readonly GraphDiagnostics $diagnostics) {}

    /** Nom du fichier : époque, liste, et filtres des jonctions (« graphe-1930-jonctions-sauts-rfn-420000.csv »). */
    public static function fileName(int $year, string $check, DiagnosticsFilter $filter, ?string $lineSlug = null): string
    {
        $parts = ['graphe', (string) $year, self::FILE_NAMES[$check] ?? $check];
        if ($filter->active() && $filter->type !== null) {
            $parts[] = self::TYPE_NAMES[$filter->type] ?? $filter->type;
        }
        if ($filter->active() && $lineSlug !== null) {
            $parts[] = $lineSlug;
        }

        return implode('-', $parts).'.csv';
    }

    /** @return list<string> */
    public static function headers(string $check): array
    {
        return self::HEADERS[$check] ?? [];
    }

    /**
     * Écrit la liste dans `$handle`, en-têtes compris.
     *
     * @param  resource  $handle
     * @param  array{lists: array<string, array{total: int, items: list<array<string, mixed>>}>}  $report
     */
    public function write($handle, array $report, string $check, DiagnosticsFilter $filter): void
    {
        $items = GraphDiagnostics::items($report, $check, $filter);
        $lineIds = [];
        if ($check === 'junctions') {
            foreach ($items as $item) {
                foreach ((array) ($item['line_ids'] ?? []) as $id) {
                    $lineIds[(int) $id] = true;
                }
            }
        }
        $lines = $lineIds === [] ? [] : $this->diagnostics->lineRefs(array_keys($lineIds));

        fwrite($handle, "\u{FEFF}");
        $this->put($handle, self::headers($check));
        foreach ($items as $item) {
            $this->put($handle, $this->row($check, $item, $lines));
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, array{id: int, slug: string, name: string, code: string|null}>  $lines
     * @return list<scalar|null>
     */
    private function row(string $check, array $item, array $lines): array
    {
        $bbox = array_pad((array) ($item['bbox'] ?? []), 4, null);

        return match ($check) {
            'islands' => [
                $item['id'], $item['nodes'], $item['edges'], $item['length_m'], $item['stations'],
                self::lineNames((array) ($item['lines'] ?? [])), ...$bbox,
            ],
            'station_islands' => [
                $item['id'], $item['stations'], $item['edges'], $item['length_m'],
                implode(' ; ', array_map(fn (array $s): string => (string) $s['name'], (array) ($item['sample'] ?? []))), ...$bbox,
            ],
            'isolated_nodes' => [$item['id'], self::label('node', $item['kind']), $item['station_id'], $item['lng'], $item['lat']],
            'stations_without_edge' => [
                $item['id'], $item['name'], $item['slug'], self::label('checks.reasons', $item['reason']), $item['lng'], $item['lat'],
            ],
            'edges' => [
                self::label('layers', $item['layer']), $item['id'], self::label('checks.reasons', $item['reason']),
                $item['length_m'], $item['measured_m'], $item['connector'] ? 'oui' : 'non',
                $item['line']['name'] ?? null, $item['line']['code'] ?? null, $item['lng'], $item['lat'],
            ],
            'junctions' => [
                self::label('checks.types', $item['type']), $item['id'], $item['distance_m'],
                $item['kind'] !== null ? self::label('node', $item['kind']) : null, $item['station_id'], $item['ends'], $item['lines'],
                self::lineNames(array_values(array_filter(array_map(fn (int $id): ?array => $lines[$id] ?? null, (array) ($item['line_ids'] ?? []))))),
                $item['lng'], $item['lat'],
            ],
            'fragmented_lines' => [
                $item['line']['name'] ?? null, $item['line']['code'] ?? null, $item['line']['slug'] ?? null,
                $item['pieces'], $item['edges'], $item['length_m'], ...$bbox,
            ],
            default => [],
        };
    }

    /** @param  list<array{name: string, code: string|null}|array<string, mixed>>  $lines */
    private static function lineNames(array $lines): string
    {
        return implode(' ; ', array_map(
            fn (array $l): string => ($l['code'] ?? null) !== null ? "{$l['name']} ({$l['code']})" : (string) $l['name'],
            $lines,
        ));
    }

    /** Libellé français d'un code (type, défaut, couche, type de nœud). */
    private static function label(string $group, mixed $code): string
    {
        $key = "graph_admin.{$group}.{$code}";
        $label = __($key, [], 'fr');

        return is_string($label) && $label !== $key ? $label : (string) $code;
    }

    /**
     * @param  resource  $handle
     * @param  list<scalar|null>  $row
     */
    private function put($handle, array $row): void
    {
        fputcsv($handle, array_map(self::cell(...), $row), ',', '"', '');
    }

    /** Valeur d'une cellule : point décimal, et apostrophe devant un texte qui serait lu comme une formule. */
    public static function cell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'oui' : 'non',
            is_int($value) => (string) $value,
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            default => preg_match('/^[=+\-@\t\r]/', (string) $value) === 1 ? "'".$value : (string) $value,
        };
    }
}
