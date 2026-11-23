<?php

namespace App\Services\Bibliography;

use App\Services\Archives\TitlePlaces;
use Illuminate\Support\Facades\DB;

/**
 * Noms d'usage des lignes qui ne sont pas un trajet « de A à B » : « ligne des Causses », « ligne de Cerdagne »
 * (nom actuel, anciens noms de Wikidata ou de lignes-oubliees). Un titre qui les écrit en entier désigne la ligne
 * sans nommer ses extrémités. Garde-fous : le reste du nom (« Causses ») tient en 1 à 3 mots, ne contient ni
 * « à » ni chiffre, n'est pas un nom de commune ou de gare connu (« ligne de Lyon » ne désigne aucune ligne en
 * particulier), et le nom n'est porté que par deux lignes au plus.
 */
final class LineAliases
{
    /** Nom d'usage : « ligne », « chemin de fer », « train », « voie ferrée », suivi d'un article et du nom propre. */
    private const PATTERN = '/^(?:ligne|chemin de fer|chemins de fer|voie ferree|ligne de chemin de fer|train)\s+(?:du|de la|de l|des|de|d)\s+(.+)$/';

    /** @var array<int, list<array{full: list<string>, key: list<string>}>> */
    private array $byLine = [];

    /** @var array<string, list<int>> phrase complète → lignes */
    private array $byPhrase = [];

    /**
     * @param  iterable<array{id: int, name: string, alt_names: mixed}>  $rows
     * @param  array<string, mixed>  $placeKeys  clés des lieux connus (LinePlaceIndex::keys())
     */
    public function __construct(iterable $rows, array $placeKeys)
    {
        $candidates = [];
        foreach ($rows as $row) {
            foreach (self::names($row) as $name) {
                $alias = self::parse($name, $placeKeys);
                if ($alias !== null) {
                    $candidates[implode(' ', $alias['full'])][$row['id']] = $alias;
                }
            }
        }
        foreach ($candidates as $phrase => $byLine) {
            if (count($byLine) > 2) {
                continue;
            }
            foreach ($byLine as $id => $alias) {
                $this->byLine[$id][] = $alias;
                $this->byPhrase[$phrase][] = $id;
            }
        }
    }

    /** @param  array<string, mixed>  $placeKeys  clés des lieux connus (LinePlaceIndex::keys()) */
    public static function load(array $placeKeys): self
    {
        $rows = [];
        foreach (DB::select('SELECT id, name, alt_names FROM lines ORDER BY id') as $r) {
            $rows[] = ['id' => (int) $r->id, 'name' => (string) $r->name, 'alt_names' => is_string($r->alt_names) ? json_decode($r->alt_names, true) : null];
        }

        return new self($rows, $placeKeys);
    }

    /** @return list<array{full: list<string>, key: list<string>}> */
    public function for(int $lineId): array
    {
        return $this->byLine[$lineId] ?? [];
    }

    /**
     * Lignes dont un nom d'usage est écrit en entier dans les mots du titre.
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @return list<int>
     */
    public function linesNamedIn(array $tokens): array
    {
        $words = array_column($tokens, 'lower');
        $ids = [];
        foreach ($this->byPhrase as $phrase => $lines) {
            if (self::contains($words, explode(' ', (string) $phrase))) {
                array_push($ids, ...$lines);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $haystack
     * @param  list<string>  $needle
     */
    public static function contains(array $haystack, array $needle): bool
    {
        $n = count($needle);
        for ($i = 0; $i + $n <= count($haystack); $i++) {
            if (array_slice($haystack, $i, $n) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{name: string, alt_names: mixed}  $row
     * @return list<string>
     */
    private static function names(array $row): array
    {
        $names = [$row['name']];
        $alt = is_array($row['alt_names']) ? $row['alt_names'] : [];
        foreach (is_array($alt['names'] ?? null) ? $alt['names'] : [] as $n) {
            if (is_array($n) && is_string($n['name'] ?? null)) {
                $names[] = $n['name'];
            }
        }
        if (is_array($alt['lignes_oubliees'] ?? null) && is_string($alt['lignes_oubliees']['name'] ?? null)) {
            $names[] = $alt['lignes_oubliees']['name'];
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $placeKeys
     * @return array{full: list<string>, key: list<string>}|null
     */
    private static function parse(string $name, array $placeKeys): ?array
    {
        $words = array_column(TitlePlaces::tokens($name), 'lower');
        $flat = implode(' ', $words);
        if (preg_match(self::PATTERN, $flat, $m) !== 1) {
            return null;
        }
        $key = explode(' ', $m[1]);
        if (count($key) > 3 || array_intersect($key, ['a', 'au', 'aux', 'et', 'via', 'par']) !== [] || preg_match('/\d/', $m[1]) === 1) {
            return null;
        }
        if (isset($placeKeys[$m[1]]) || strlen($m[1]) < 4) {
            return null;
        }

        return ['full' => $words, 'key' => $key];
    }
}
