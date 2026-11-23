<?php

namespace App\Services\StationNames;

/**
 * Noms d'une gare dans Wikidata (CC0) : nom officiel (P1448) et nom (P2561), avec début (P580) et fin (P582)
 * quand les déclarations en ont. Seuls les noms en français ou en allemand sont repris, les déclarations
 * dépréciées sont écartées ; un nom sans date n'est repris que s'il diffère du nom de la fiche.
 */
final class WikidataNames
{
    public const SOURCE = 'wikidata_names';

    private const PROPERTIES = ['P1448', 'P2561'];

    private const LANGUAGES = ['fr', 'de'];

    /**
     * @param  array<string, mixed>  $claims  déclarations de l'élément
     * @return list<array<string, mixed>>
     */
    public static function parse(string $qid, array $claims, string $ownName): array
    {
        $own = RenameExtractor::key($ownName);
        $out = [];
        foreach (self::PROPERTIES as $property) {
            foreach ((array) ($claims[$property] ?? []) as $st) {
                $value = is_array($st) ? ($st['mainsnak']['datavalue']['value'] ?? null) : null;
                if (! is_array($st) || ($st['rank'] ?? 'normal') === 'deprecated' || ! is_array($value) || ! is_string($value['text'] ?? null)
                    || ! in_array($value['language'] ?? '', self::LANGUAGES, true)) {
                    continue;
                }
                $name = trim(preg_replace('/^(?:la\s+)?(?:gare|station|halte)\s+(?:de\s+la\s+|de\s+l\'|de\s+|d\'|des\s+)/iu', '', $value['text']) ?? $value['text']);
                if ($name === '' || RenameExtractor::key($name) === $own || RenameExtractor::key($name) === '') {
                    continue;
                }
                $from = self::time($st['qualifiers']['P580'][0] ?? null);
                $to = self::time($st['qualifiers']['P582'][0] ?? null);
                $entry = ['name' => $name, 'from' => $from['year'] ?? null, 'to' => $to['year'] ?? null];
                if ($from !== null) {
                    $entry += ['from_date' => $from['date'], 'from_precision' => $from['precision']];
                }
                if ($to !== null) {
                    $entry += ['to_date' => $to['date'], 'to_precision' => $to['precision']];
                }
                $out[] = $entry + ['lang' => $value['language'], 'source' => self::SOURCE, 'source_url' => "https://www.wikidata.org/wiki/{$qid}#{$property}"];
            }
        }

        return $out;
    }

    /** @return ?array{year: int, date: string, precision: string} */
    private static function time(mixed $snak): ?array
    {
        $v = is_array($snak) ? ($snak['datavalue']['value'] ?? null) : null;
        if (! is_array($v) || ! is_string($v['time'] ?? null) || preg_match('/^\+(\d{4})-(\d\d)-(\d\d)/', $v['time'], $m) !== 1 || (int) $m[1] < 1500) {
            return null;
        }
        $precision = (int) ($v['precision'] ?? 9);
        if ($precision >= 11 && $m[2] !== '00' && $m[3] !== '00') {
            return ['year' => (int) $m[1], 'date' => "{$m[1]}-{$m[2]}-{$m[3]}", 'precision' => 'day'];
        }
        if ($precision === 10 && $m[2] !== '00') {
            return ['year' => (int) $m[1], 'date' => "{$m[1]}-{$m[2]}", 'precision' => 'month'];
        }

        return ['year' => (int) $m[1], 'date' => $m[1], 'precision' => $precision === 8 ? 'decade' : 'year'];
    }
}
