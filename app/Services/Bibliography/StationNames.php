<?php

namespace App\Services\Bibliography;

use App\Services\Archives\TitlePlaces;
use Illuminate\Support\Facades\DB;

/**
 * Noms sous lesquels un titre peut désigner une gare : le nom entier (« Limoges-Bénédictins ») et, quand il
 * commence par la commune (« Paris-Austerlitz »), la partie après le trait d'union (« Austerlitz »). Un nom porté
 * par plusieurs gares est écarté (« Lyon » ne désigne pas une gare en particulier) : mieux vaut une gare sans
 * bibliographie qu'un ouvrage sur la mauvaise gare.
 */
final class StationNames
{
    /** @var array<int, list<list<string>>> */
    private array $variants = [];

    /** @var array<int, string> id → nom */
    private array $names = [];

    /** @param  iterable<array{id: int, name: string, city: ?string}>  $rows */
    public function __construct(iterable $rows)
    {
        $count = [];
        $candidates = [];
        foreach ($rows as $row) {
            $this->names[$row['id']] = $row['name'];
            $name = (string) preg_replace("/^(?:gare|halte|arr[êe]t)\s+(?:de |du |des |d['’]|de la |de l['’])?/iu", '', $row['name']);
            $full = array_column(TitlePlaces::tokens($name), 'lower');
            if ($full === []) {
                continue;
            }
            $list = [$full];
            $city = $row['city'] === null ? [] : array_column(TitlePlaces::tokens($row['city']), 'lower');
            if ($city !== [] && count($full) > count($city) && array_slice($full, 0, count($city)) === $city) {
                $list[] = array_slice($full, count($city));
            }
            foreach ($list as $v) {
                $key = implode(' ', $v);
                if (strlen($key) < 4 || TitlePlaces::key($key) === null) {
                    continue;
                }
                $count[$key] = ($count[$key] ?? 0) + 1;
                $candidates[$row['id']][$key] = $v;
            }
        }
        foreach ($candidates as $id => $byKey) {
            foreach ($byKey as $key => $v) {
                if ($count[$key] === 1) {
                    $this->variants[$id][] = $v;
                }
            }
        }
    }

    public static function load(): self
    {
        $rows = [];
        foreach (DB::select('SELECT id, name, city FROM stations ORDER BY id') as $r) {
            $rows[] = ['id' => (int) $r->id, 'name' => (string) $r->name, 'city' => $r->city];
        }

        return new self($rows);
    }

    public function name(int $id): ?string
    {
        return $this->names[$id] ?? null;
    }

    /** @return list<list<string>> */
    public function variants(int $id): array
    {
        return $this->variants[$id] ?? [];
    }

    /** Terme d'une requête au catalogue (« Limoges Bénédictins »), ou null si la gare n'a pas de nom sûr. */
    public function queryTerm(int $id): ?string
    {
        $v = $this->variants($id)[0] ?? null;

        return $v === null ? null : implode(' ', $v);
    }

    /**
     * Gares nommées par un titre : « gare » (ou « gares ») juste avant le nom, avec au plus un mot de liaison
     * (« la gare de Limoges-Bénédictins », « les gares d'Austerlitz »).
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @return list<int>
     */
    public function stationsNamedIn(array $tokens): array
    {
        $words = array_column($tokens, 'lower');
        $links = ['de', 'du', 'des', 'd', 'la', 'le', 'l'];
        $ids = [];
        foreach ($this->variants as $id => $list) {
            foreach ($list as $v) {
                $n = count($v);
                for ($i = 0; $i + $n <= count($words); $i++) {
                    if (array_slice($words, $i, $n) !== $v) {
                        continue;
                    }
                    $prev = $words[$i - 1] ?? '';
                    $prev2 = $words[$i - 2] ?? '';
                    $prev3 = $words[$i - 3] ?? '';
                    $after = ($words[$i - 1] ?? '') === 'gare' || ($words[$i - 1] ?? '') === 'gares'
                        || (in_array($prev, $links, true) && in_array($prev2, ['gare', 'gares'], true))
                        || (in_array($prev, $links, true) && in_array($prev2, $links, true) && in_array($prev3, ['gare', 'gares'], true));
                    if ($after) {
                        $ids[$id] = true;
                    }
                }
            }
        }

        return array_keys($ids);
    }
}
