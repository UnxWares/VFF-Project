<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\DB;

/**
 * Familles de séries qui partagent un article Wikipédia : l'article d'une série renvoie vers celui d'une autre,
 * la famille (« Z 56600 » et « Omneo Premium » vers « Regio 2N », « Coradia Liner » vers « Régiolis »).
 *
 * Une phrase qui nomme une version de la famille parle de cette version, même quand son lien pointe vers
 * l'article commun : « ces rames [[Regio 2N|Omneo Premium]] (Z 56600) » relie la ligne aux Z 56600, pas au
 * Regio 2N. Une version se reconnaît à son nom (« Z 56600 ») ou à l'une de ses désignations Wikidata
 * (« Omneo Premium »), sauf celles qui contiennent le nom de la famille (« Regio 2N Z 56600 » ne distingue rien).
 * Règle partagée par LineMaterialLinker (liens) et MaterialPeriodCollector (périodes).
 */
final class MaterialFamilies
{
    /** Désignation trop courte pour être reconnue sans ambiguïté. */
    private const MIN_TERM_LENGTH = 4;

    /**
     * @param  array<int, list<int>>  $members  famille → ses versions
     * @param  array<int, list<string>>  $terms  version → motifs qui la nomment
     */
    private function __construct(
        private readonly array $members,
        private readonly array $terms,
    ) {}

    /**
     * @param  array<int, array{name: string, title: ?string, designations?: list<string>|null}>  $materials  série → nom, article Wikipédia en français, désignations
     * @param  array<string, string>  $finalTitles  article demandé → article final (redirection suivie)
     */
    public static function from(array $materials, array $finalTitles): self
    {
        $byTitle = [];
        foreach ($materials as $id => $m) {
            if (is_string($m['title'] ?? null) && $m['title'] !== '') {
                $byTitle[WikipediaMaterialSections::normalizeTitle($m['title'])][] = (int) $id;
            }
        }
        $members = [];
        $terms = [];
        foreach ($materials as $id => $m) {
            $title = is_string($m['title'] ?? null) ? WikipediaMaterialSections::normalizeTitle($m['title']) : '';
            $final = WikipediaMaterialSections::normalizeTitle($finalTitles[$title] ?? $title);
            if ($title === '' || $final === $title) {
                continue;
            }
            // L'article final est celui d'une seule autre série : la famille.
            $families = array_values(array_diff($byTitle[$final] ?? [], [(int) $id]));
            if (count($families) !== 1) {
                continue;
            }
            $family = $families[0];
            $familyPattern = self::pattern((string) $materials[$family]['name']);
            $own = [];
            foreach ([(string) $m['name'], ...($m['designations'] ?? [])] as $term) {
                $term = trim((string) $term);
                if (mb_strlen($term) >= self::MIN_TERM_LENGTH && preg_match($familyPattern, self::fold($term)) !== 1) {
                    $own[self::pattern($term)] = true;
                }
            }
            if ($own !== []) {
                $members[$family][] = (int) $id;
                $terms[(int) $id] = array_keys($own);
            }
        }

        return new self($members, $terms);
    }

    /**
     * Familles d'après la base et les articles des séries mis en cache par l'import (titre final de chaque article).
     *
     * @param  array<string, mixed>  $pages  cache `wikipedia_materials` : article demandé → page (`title`) ou null
     */
    public static function fromDatabase(array $pages): self
    {
        $finalTitles = [];
        foreach ($pages as $requested => $page) {
            if (is_array($page) && is_string($page['title'] ?? null)) {
                $finalTitles[WikipediaMaterialSections::normalizeTitle((string) $requested)] = (string) $page['title'];
            }
        }
        $materials = [];
        foreach (DB::table('materials')->whereNotNull('wikipedia_fr')->get(['id', 'name', 'wikipedia_fr', 'designations']) as $row) {
            $designations = is_string($row->designations) ? json_decode($row->designations, true) : null;
            $materials[(int) $row->id] = [
                'name' => (string) $row->name,
                'title' => (string) $row->wikipedia_fr,
                'designations' => is_array($designations) ? array_values(array_filter($designations, 'is_string')) : null,
            ];
        }

        return self::from($materials, $finalTitles);
    }

    /**
     * Versions de la famille que la phrase nomme (aucune si la série n'est pas une famille).
     *
     * @return list<int>
     */
    public function named(int $familyId, string $quote): array
    {
        $folded = self::fold($quote);

        return array_values(array_filter($this->members[$familyId] ?? [], fn (int $id): bool => $this->names($id, $folded)));
    }

    /**
     * Séries auxquelles revient une phrase liée à ces séries : une famille cède la place aux versions que la
     * phrase nomme ; sinon la série reste elle-même.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function attribute(array $ids, string $quote): array
    {
        $out = [];
        foreach ($ids as $id) {
            $named = $this->named((int) $id, $quote);
            array_push($out, ...($named === [] ? [(int) $id] : $named));
        }

        return array_values(array_unique($out));
    }

    /**
     * La phrase d'un article de série vaut-elle pour la série demandée ? Son propre article : oui, sauf si la
     * phrase nomme l'une de ses versions (elle revient alors à la version). Article d'une autre série (redirection) :
     * seulement si la phrase la nomme, par son nom ou l'une de ses désignations.
     */
    public function speaksOf(int $materialId, string $requested, string $finalTitle, string $name, string $quote): bool
    {
        if (WikipediaMaterialSections::normalizeTitle($requested) === WikipediaMaterialSections::normalizeTitle($finalTitle)) {
            return $this->named($materialId, $quote) === [];
        }

        return WikipediaMaterialSections::speaksOf($requested, $finalTitle, $name, $quote) || $this->names($materialId, self::fold($quote));
    }

    private function names(int $id, string $foldedQuote): bool
    {
        foreach ($this->terms[$id] ?? [] as $pattern) {
            if (preg_match($pattern, $foldedQuote) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Motif d'un nom sur un texte replié : espaces facultatifs entre mots et entre lettres et chiffres (« Regio2N »). */
    private static function pattern(string $term): string
    {
        $parts = preg_split('/[\s\x{00A0}\x{202F}]+|(?<=\p{L})(?=\d)/u', self::fold($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return '/(?<![\p{L}\d])'.implode('[\s\x{00A0}\x{202F}]*', array_map(fn (string $p): string => preg_quote($p, '/'), $parts)).'(?![\p{L}\d])/u';
    }

    /** Minuscules sans accents (« Régio 2N » et « Regio 2N » se valent). */
    private static function fold(string $text): string
    {
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);

        return mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', $decomposed === false ? $text : $decomposed));
    }
}
