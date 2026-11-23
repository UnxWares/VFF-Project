<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\DB;

/**
 * Liens ligne × matériel à ne pas créer (database/data/line_material_exclusions.php) : faux positifs
 * relus à la main qu'aucune règle générale sûre ne couvre (essai, matériel restreint à une autre
 * portion, wagon en restauration…).
 *
 * Une exclusion vaut :
 *   - pour une phrase, quand elle donne `quote` : seules les preuves dont la citation contient ce texte
 *     sont retirées, et le lien reste si une autre source le prouve ;
 *   - pour le couple ligne × série tout entier, sans `quote` (série qui ne peut pas rouler sur la ligne,
 *     établi avec la source).
 * Une entrée sans source, ou dont la ligne ou la série n'existe pas dans VFF, ne vaut rien.
 */
final class LineMaterialExclusions
{
    /**
     * @param  array<string, list<array{quote: ?string, reason: string}>>  $byKey  « ligne:série » → exclusions
     * @param  list<string>  $unresolved  entrées sans effet dans cette base (unresolved())
     */
    public function __construct(private readonly array $byKey = [], private readonly array $unresolved = []) {}

    /** @return list<string> entrées du fichier sans effet dans cette base, à signaler (unresolved()) */
    public function unresolvedEntries(): array
    {
        return $this->unresolved;
    }

    /**
     * Exclusions chargées depuis le fichier de données (ou la liste donnée), rattachées aux ids de la base.
     *
     * @param  list<array{line: string, material: string, quote?: string, source: string, reason: string}>|null  $entries  null : le fichier de données
     */
    public static function fromDatabase(?array $entries = null): self
    {
        $entries ??= require database_path('data/line_material_exclusions.php');
        $byKey = [];
        foreach (self::resolve($entries) as $key => $list) {
            foreach ($list as $e) {
                $quote = isset($e['quote']) && trim($e['quote']) !== '' ? $e['quote'] : null;
                $byKey[$key][] = ['quote' => $quote, 'reason' => $e['reason']];
            }
        }

        return new self($byKey, self::unresolved($entries, 'line_material_exclusions.php'));
    }

    /**
     * Exceptions sourcées (écartement, électrification) par couple « ligne:série » → source.
     *
     * @param  list<array{line: string, material: string, source: string, reason: string}>  $entries
     * @return array<string, string>
     */
    public static function keyed(array $entries): array
    {
        return array_map(fn (array $list): string => $list[0]['source'], self::resolve($entries));
    }

    /**
     * Entrées sourcées rattachées aux ids : ligne par code RFN ou par slug, série par QID Wikidata.
     *
     * @param  list<array{line: string, material: string, quote?: string, source: string, reason: string}>  $entries
     * @return array<string, non-empty-list<array{line: string, material: string, quote?: string, source: string, reason: string}>>
     */
    private static function resolve(array $entries): array
    {
        if ($entries === []) {
            return [];
        }
        [$lines, $materials] = self::lookup($entries);
        $out = [];
        foreach ($entries as $e) {
            if (isset($lines[$e['line']], $materials[$e['material']]) && trim($e['source']) !== '') {
                $out[$lines[$e['line']].':'.$materials[$e['material']]][] = $e;
            }
        }

        return $out;
    }

    /**
     * Entrées sans effet dans cette base, une phrase par entrée : ligne introuvable (ni lines.code ni
     * lines.slug), série introuvable (materials.wikidata_id) ou source vide. L'import les affiche : une
     * exception mal rattachée ne doit pas être ignorée sans message (revue des PR #19 et #20).
     *
     * @param  list<array{line: string, material: string, quote?: string, source: string, reason: string}>  $entries
     * @param  string  $file  fichier de données, nommé dans le message
     * @return list<string>
     */
    public static function unresolved(array $entries, string $file): array
    {
        if ($entries === []) {
            return [];
        }
        [$lines, $materials] = self::lookup($entries);
        $out = [];
        foreach ($entries as $i => $e) {
            $problems = array_values(array_filter([
                isset($lines[$e['line']]) ? null : 'ligne introuvable (ni lines.code ni lines.slug)',
                isset($materials[$e['material']]) ? null : 'série introuvable (materials.wikidata_id)',
                trim($e['source']) !== '' ? null : 'source vide',
            ]));
            if ($problems !== []) {
                $out[] = "{$file} n° {$i} (ligne « {$e['line']} », série {$e['material']}) : ".implode(' ; ', $problems);
            }
        }

        return $out;
    }

    /**
     * Identifiants des lignes (par code RFN et par slug) et des séries (par QID) cités par les entrées.
     *
     * @param  list<array{line: string, material: string, quote?: string, source: string, reason: string}>  $entries
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    public static function lookup(array $entries): array
    {
        $refs = array_column($entries, 'line');
        $lines = [];
        foreach (DB::table('lines')->whereIn('code', $refs)->orWhereIn('slug', $refs)->get(['id', 'code', 'slug']) as $row) {
            foreach ([$row->code, $row->slug] as $ref) {
                if ($ref !== null) {
                    $lines[(string) $ref] = (int) $row->id;
                }
            }
        }
        $materials = DB::table('materials')->whereIn('wikidata_id', array_column($entries, 'material'))->pluck('id', 'wikidata_id')
            ->map(fn ($id): int => (int) $id)->all();

        return [$lines, $materials];
    }

    /**
     * Motif si ce lien est exclu en entier, ou null. Retire d'abord de `$evidence` les preuves exclues
     * phrase par phrase : le lien est exclu quand il ne lui reste aucune preuve.
     *
     * @param  list<array<string, mixed>>  $evidence
     */
    public function apply(int $lineId, int $materialId, array &$evidence): ?string
    {
        $reasons = [];
        foreach ($this->byKey["{$lineId}:{$materialId}"] ?? [] as $exclusion) {
            if ($exclusion['quote'] === null) {
                return 'exclusion relue : '.$exclusion['reason'];
            }
            $kept = array_values(array_filter($evidence, fn (array $item): bool => ! is_string($item['quote'] ?? null) || mb_stripos($item['quote'], $exclusion['quote']) === false));
            if (count($kept) < count($evidence)) {
                $reasons[] = $exclusion['reason'];
                $evidence = $kept;
            }
        }

        return $evidence === [] && $reasons !== [] ? 'exclusion relue : '.implode(' ; ', $reasons) : null;
    }
}
