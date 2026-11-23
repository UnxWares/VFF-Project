<?php

namespace App\Services;

use App\Support\Glossary;
use App\Support\Search\SearchQuery;
use App\Support\Search\SearchText;
use Illuminate\Container\Attributes\Singleton;

/**
 * Termes du glossaire (/glossary) trouvés par la recherche du site : même lecture de la requête et même pertinence
 * que les fiches en base (SearchQuery::rankKeys), sur le nom du terme en français, en anglais et en allemand, son
 * sigle, son développé et sa clé (« TVM 430 », « tvm430 », « block automatique lumineux », « overhead line »,
 * « Fahrleitung »). Les textes viennent des fichiers de traduction : aucune requête SQL.
 *
 * À pertinence égale, ordre alphabétique du nom français. Index des termes construit une fois par processus.
 */
#[Singleton]
final class GlossarySearch
{
    private const LOCALES = ['fr', 'en', 'de'];

    /** @var array<string, array{names: array<string, string>, abbr: ?string, name_keys: list<string>, all_key: string}>|null */
    private ?array $index = null;

    /**
     * @return list<array{type: 'glossary', slug: string, name: string, names: array<string, string>, abbr: ?string}>
     */
    public function search(string $q, int $limit = 5): array
    {
        if ($limit <= 0 || mb_strlen($q) < CatalogSearch::MIN_LENGTH) {
            return [];
        }
        $search = SearchQuery::parse($q);
        if ($search->isEmpty()) {
            return [];
        }
        $found = [];
        foreach ($this->index() as $slug => $term) {
            $rank = $search->rankKeys($term['name_keys'], $term['all_key']);
            if ($rank !== null) {
                $found[] = [$rank, $term['names']['fr'], $slug];
            }
        }
        usort($found, fn (array $a, array $b): int => [$a[0], SearchText::key($a[1])] <=> [$b[0], SearchText::key($b[1])]);

        return array_map(function (array $hit): array {
            $term = $this->index()[$hit[2]];

            return [
                'type' => 'glossary',
                'slug' => $hit[2],
                'name' => $term['names']['fr'],
                'names' => $term['names'],
                'abbr' => $term['abbr'],
            ];
        }, array_slice($found, 0, $limit));
    }

    /** @return array<string, array{names: array<string, string>, abbr: ?string, name_keys: list<string>, all_key: string}> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }
        $index = [];
        foreach (self::LOCALES as $locale) {
            foreach (Glossary::all($locale) as $term) {
                $entry = $index[$term['slug']] ?? ['names' => [], 'abbr' => $term['abbr'], 'texts' => [str_replace('-', ' ', $term['slug']), (string) $term['abbr']]];
                $entry['names'][$locale] = $term['name'];
                $entry['texts'][] = $term['name'];
                $entry['texts'][] = (string) $term['full'];
                $index[$term['slug']] = $entry;
            }
        }

        return $this->index = array_map(fn (array $e): array => [
            'names' => $e['names'],
            'abbr' => $e['abbr'],
            'name_keys' => array_values(array_unique(array_map(fn (string $n) => SearchText::key($n), $e['names']))),
            'all_key' => SearchText::key(implode(' ', array_unique($e['texts']))),
        ], $index);
    }
}
