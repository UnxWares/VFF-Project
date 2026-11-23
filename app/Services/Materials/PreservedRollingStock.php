<?php

namespace App\Services\Materials;

use App\Models\Material;
use App\Services\TouristRailways;
use Illuminate\Support\Str;
use Throwable;

/**
 * Matériel roulant préservé (database/data/preserved_rolling_stock.php) : les exemplaires conservés d'une série
 * (numéro, lieu où on peut le voir, état, protection au titre des monuments historiques) et les lieux de
 * conservation avec leurs pièces. Partagé par la fiche du matériel, la page `/materials/preserved` et l'API REST.
 *
 * Rien n'est écrit en base : les données sont un fichier versionné, chaque fait porte sa source (Wikipédia,
 * base Palissy, Wikidata). Le lien vers une exploitation touristique n'est posé que si elle figure bien dans
 * la page `/tourist-railways` (le nom du catalogue est relu à chaque appel, jamais l'adresse d'une fiche inventée).
 */
final class PreservedRollingStock
{
    /** États que les sources disent (jamais déduits). */
    public const STATES = ['running', 'exposed', 'restoration', 'stored'];

    /** Types de lieux, dans l'ordre d'affichage. */
    public const VENUE_TYPES = ['museum', 'association', 'tourist_railway', 'site'];

    /** @var array<string, array{label: string, licence: string, url: string|null}> */
    public const SOURCES = [
        'wikipedia' => ['label' => 'Wikipédia en français', 'licence' => 'CC BY-SA 4.0', 'url' => 'https://fr.wikipedia.org/'],
        'palissy' => ['label' => 'Base Palissy, Plateforme ouverte du patrimoine (ministère de la Culture)', 'licence' => 'Licence Ouverte', 'url' => 'https://www.pop.culture.gouv.fr/'],
        'wikidata' => ['label' => 'Wikidata', 'licence' => 'CC0', 'url' => 'https://www.wikidata.org/'],
    ];

    /** @var array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>} */
    private readonly array $data;

    /** @var array<string, string>|null nom du catalogue touristique -> identifiant de la ligne de la page /tourist-railways */
    private ?array $tourist = null;

    /**
     * @param  array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>}|null  $data
     */
    public function __construct(private readonly ?TouristRailways $touristRailways, ?array $data = null)
    {
        /** @var array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>} $loaded */
        $loaded = $data ?? require database_path('data/preserved_rolling_stock.php');
        $this->data = $loaded;
    }

    /**
     * Exemplaires préservés d'une série.
     *
     * @return list<array<string, mixed>>
     */
    public function forMaterial(string $slug): array
    {
        return array_map(fn (array $unit): array => $this->unit($unit), $this->data['units'][$slug] ?? []);
    }

    public function countFor(string $slug): int
    {
        return count($this->data['units'][$slug] ?? []);
    }

    /**
     * Lieux de conservation avec leurs pièces : un lieu par entrée, du plus riche au moins riche, les types
     * dans l'ordre de VENUE_TYPES.
     *
     * @return list<array<string, mixed>>
     */
    public function venues(): array
    {
        $pieces = [];
        foreach ($this->data['units'] as $slug => $units) {
            foreach ($units as $unit) {
                if (($unit['venue'] ?? null) === null) {
                    continue;
                }
                $key = $unit['venue'].'|'.preg_replace('/\W+/u', '', (string) $unit['number']);
                // Une pièce rattachée à deux séries (série large et série d'un seul numéro) n'est comptée qu'une fois par lieu.
                $pieces[$unit['venue']][$key]['unit'] = $unit;
                $pieces[$unit['venue']][$key]['series'][] = $slug;
            }
        }
        $names = Material::query()->whereIn('slug', array_keys($this->data['units']))->pluck('name', 'slug')->all();
        $others = [];
        foreach ($this->data['others'] as $other) {
            if (($other['venue'] ?? null) !== null) {
                $others[$other['venue']][] = $other;
            }
        }

        $out = [];
        foreach ($this->data['venues'] as $key => $venue) {
            $list = [];
            foreach ($pieces[$key] ?? [] as $piece) {
                $slugs = array_values(array_unique($piece['series']));
                $row = $this->unit($piece['unit']);
                $row['series'] = array_map(fn (string $s): array => ['slug' => $s, 'name' => $names[$s] ?? $s], $slugs);
                unset($row['venue']);
                $list[] = $row;
            }
            foreach ($others[$key] ?? [] as $other) {
                $list[] = [
                    'number' => $other['number'], 'title' => $other['title'], 'series' => [], 'where' => null, 'state' => null,
                    'protection' => $this->protection($other['mh'] ?? null), 'sources' => $this->sources($other['src'] ?? []),
                ];
            }
            if ($list === []) {
                continue;
            }
            usort($list, fn (array $a, array $b): int => strnatcasecmp((string) ($a['number'] ?? $a['title'] ?? ''), (string) ($b['number'] ?? $b['title'] ?? '')));
            $out[] = $this->venue($key, $venue) + ['count' => count($list), 'pieces' => $list];
        }
        usort($out, function (array $a, array $b): int {
            return [array_search($a['type'], self::VENUE_TYPES, true), -$a['count'], $a['name']]
                <=> [array_search($b['type'], self::VENUE_TYPES, true), -$b['count'], $b['name']];
        });

        return $out;
    }

    /**
     * Chiffres de la couverture : exemplaires rattachés à une série, lieux, séries concernées, protégés.
     *
     * @return array{units: int, series: int, venues: int, protected: int, with_place: int, others: int}
     */
    public function figures(): array
    {
        $unique = [];
        $protected = [];
        $withPlace = [];
        foreach ($this->data['units'] as $units) {
            foreach ($units as $unit) {
                $id = preg_replace('/\W+/u', '', (string) $unit['number']);
                $unique[$id] = true;
                if (($unit['mh'] ?? null) !== null && ($unit['mh']['kind'] ?? null) !== 'declassified') {
                    $protected[$id] = true;
                }
                if (($unit['venue'] ?? null) !== null) {
                    $withPlace[$id] = true;
                }
            }
        }

        return [
            'units' => count($unique),
            'series' => count(array_filter($this->data['units'])),
            'venues' => count($this->venues()),
            'protected' => count($protected),
            'with_place' => count($withPlace),
            'others' => count($this->data['others']),
        ];
    }

    /** @param array<string, mixed> $unit @return array<string, mixed> */
    private function unit(array $unit): array
    {
        $venue = $unit['venue'] ?? null;
        $state = $unit['state'] ?? null;

        return [
            'number' => $unit['number'],
            'venue' => is_string($venue) && isset($this->data['venues'][$venue]) ? $this->venue($venue, $this->data['venues'][$venue]) : null,
            'where' => $unit['where'] ?? null,
            'state' => in_array($state, self::STATES, true) ? $state : null,
            'protection' => $this->protection($unit['mh'] ?? null),
            'sources' => $this->sources($unit['src'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $venue
     * @return array{slug: string, name: string, type: string, commune: string|null, tourist_railway: array{name: string, url: string}|null}
     */
    private function venue(string $key, array $venue): array
    {
        $tourist = null;
        $name = $venue['tourist_railway'] ?? null;
        if (is_string($name) && ($id = $this->touristIds()[self::norm($name)] ?? $this->touristIds()[self::norm(preg_replace('/\s*\([^)]*\)\s*$/u', '', $name) ?? $name)] ?? null) !== null) {
            $tourist = ['name' => $name, 'url' => '/tourist-railways#'.$id];
        }

        return [
            'slug' => $key,
            'name' => (string) $venue['name'],
            'type' => (string) $venue['type'],
            'commune' => $venue['commune'] ?? null,
            'tourist_railway' => $tourist,
        ];
    }

    /**
     * Protection au titre des monuments historiques : nature, date de la notice Palissy (jour, ou année seule quand
     * seul Wikipédia la donne), référence et adresse de la notice.
     *
     * @param  array<string, mixed>|null  $mh
     * @return array{kind: string|null, date: string|null, year: int|null, ref: string|null, url: string|null}|null
     */
    private function protection(?array $mh): ?array
    {
        if ($mh === null) {
            return null;
        }
        $ref = $mh['ref'] ?? null;

        return [
            'kind' => $mh['kind'] ?? null,
            'date' => $mh['date'] ?? null,
            'year' => $mh['year'] ?? null,
            'ref' => $ref,
            'url' => is_string($ref) && preg_match('/^P[AM]\d{8}$/', $ref) === 1 ? 'https://www.pop.culture.gouv.fr/notice/palissy/'.$ref : null,
        ];
    }

    /**
     * @param  list<array<int, mixed>>  $src
     * @return list<array{key: string, label: string, licence: string, ref: string|null, url: string|null}>
     */
    private function sources(array $src): array
    {
        $out = [];
        foreach ($src as $s) {
            $key = (string) $s[0];
            $base = self::SOURCES[$key] ?? null;
            if ($base === null) {
                continue;
            }
            $ref = isset($s[1]) ? (string) $s[1] : null;
            $url = match ($key) {
                'wikipedia' => $ref !== null && isset($s[2]) ? 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $ref)).'&oldid='.(int) $s[2] : null,
                'palissy' => $ref !== null ? 'https://www.pop.culture.gouv.fr/notice/palissy/'.rawurlencode($ref) : null,
                'wikidata' => $ref !== null ? 'https://www.wikidata.org/wiki/'.rawurlencode($ref) : null,
                default => null,
            };
            $out[] = ['key' => $key, 'label' => $base['label'], 'licence' => $base['licence'], 'ref' => $ref, 'url' => $url];
        }

        return $out;
    }

    /** Nom sans accent, capitale ni ponctuation : le catalogue et la page des lignes touristiques n'écrivent pas toujours un nom pareil. */
    private static function norm(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($name))) ?? '';
    }

    /** @return array<string, string> nom normalisé (norm) -> identifiant de la ligne de /tourist-railways */
    private function touristIds(): array
    {
        if ($this->tourist !== null) {
            return $this->tourist;
        }
        $this->tourist = [];
        if ($this->touristRailways === null) {
            return $this->tourist;
        }
        try {
            foreach ($this->touristRailways->get()['railways'] as $railway) {
                $this->tourist[self::norm((string) $railway['name'])] ??= (string) $railway['id'];
                $this->tourist[self::norm(preg_replace('/\s*\([^)]*\)\s*$/u', '', (string) $railway['name']) ?? (string) $railway['name'])] ??= (string) $railway['id'];
            }
        } catch (Throwable) {
            // La page des lignes touristiques dépend de la base : sans elle, les lieux s'affichent sans lien.
        }

        return $this->tourist;
    }
}
