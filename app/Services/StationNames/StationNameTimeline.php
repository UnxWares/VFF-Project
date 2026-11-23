<?php

namespace App\Services\StationNames;

/**
 * Noms successifs d'une gare à partir de ses renommages lus (RenameExtractor) : pour chaque renommage
 * « X → Y en N », X porte `to` = N et Y porte `from` = N ; la fin de Y est l'année d'un renommage ultérieur
 * dont Y est l'ancien nom. Le nom actuel de la fiche n'est jamais écrit (c'est le nom de la gare), ni une
 * variante d'écriture de ce nom.
 *
 * Un nom annoncé comme nom d'origine (« d'abord baptisée X ») commence à l'ouverture de la gare, quand elle est connue.
 */
final class StationNameTimeline
{
    public const SOURCE = 'wikipedia_history';

    /**
     * @param  list<array{old: ?string, new: string, year: int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}>  $events
     * @return list<array<string, mixed>> entrées d'alt_names (name, from, to, from_date, from_precision, to_date, to_precision, lang, source, source_url, quote)
     */
    public static function fromEvents(array $events, string $ownName, ?int $openedYear, string $sourceUrl): array
    {
        $own = RenameExtractor::key($ownName);
        $events = self::connected($events, $own);
        $names = [];
        foreach ($events as $e) {
            if ($e['old'] !== null && RenameExtractor::key($e['old']) !== $own) {
                $names[RenameExtractor::key($e['old'])] ??= self::blank($e['old'], $e['quote']);
                $names[RenameExtractor::key($e['old'])]['to'] = $e;
                if ($e['initial'] && $openedYear !== null && $openedYear <= $e['year']) {
                    $names[RenameExtractor::key($e['old'])]['opened'] = $openedYear;
                }
            }
            if ($e['new'] !== '' && RenameExtractor::key($e['new']) === $own && $e['strong'] && ($openedYear === null || $e['year'] > $openedYear)) {
                // « Elle est renommée « X » en 1914 » : X est le nom de la fiche, la date du renommage est gardée.
                $names['@own'] ??= self::blank($ownName, $e['quote']) + ['renamed' => true];
                $names['@own']['from'] ??= $e;
            }
            if ($e['new'] !== '' && RenameExtractor::key($e['new']) !== $own) {
                $names[RenameExtractor::key($e['new'])] ??= self::blank($e['new'], $e['quote']);
                $names[RenameExtractor::key($e['new'])]['from'] ??= $e;
                // Période écrite après le nom (« du 1er juillet 1940 à la Libération (1944) »).
                if ($e['to_year'] !== null && $e['to_year'] >= $e['year']) {
                    $names[RenameExtractor::key($e['new'])]['to_year'] = $e['to_year'];
                }
            }
        }
        $out = [];
        foreach ($names as $n) {
            $entry = ['name' => $n['name'], 'from' => null, 'to' => null];
            if (isset($n['from'])) {
                $entry += ['from_date' => $n['from']['date'], 'from_precision' => $n['from']['precision']];
                $entry['from'] = $n['from']['year'];
            } elseif (isset($n['opened'])) {
                $entry += ['from_date' => (string) $n['opened'], 'from_precision' => 'year'];
                $entry['from'] = $n['opened'];
            }
            if (isset($n['to'])) {
                $entry += ['to_date' => $n['to']['date'], 'to_precision' => $n['to']['precision']];
                $entry['to'] = $n['to']['year'];
            } elseif (isset($n['to_year'])) {
                $entry += ['to_date' => (string) $n['to_year'], 'to_precision' => 'year'];
                $entry['to'] = $n['to_year'];
            }
            // Une période à l'envers (renommage lu deux fois, ou lu dans le désordre) n'est pas écrite.
            if ($entry['from'] !== null && $entry['to'] !== null && $entry['to'] < $entry['from']) {
                continue;
            }
            if ($entry['from'] === null && $entry['to'] === null) {
                continue;
            }
            if (isset($n['renamed'])) {
                $entry['renamed'] = true;
            }
            $out[] = $entry + ['lang' => self::lang($n['quote']), 'source' => self::SOURCE, 'source_url' => $sourceUrl, 'quote' => $n['quote']];
        }

        return $out;
    }

    /**
     * Garde les renommages qui concernent cette gare : ceux dont un nom est (ou s'enchaîne jusqu'à) le nom de la fiche ;
     * un ancien nom annoncé comme celui d'origine (« d'abord baptisée X ») est celui de cette gare ; sans ancien nom, le nouveau doit contenir le nom de la fiche ou l'inverse (« Vallon » → « Vallon-en-Sully »).
     * Écarte la gare voisine dont l'article parle (« Gières-Campus rebaptisée Grenoble-Universités-Gières »).
     *
     * @param  list<array{old: ?string, new: string, year: int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}>  $events
     * @return list<array{old: ?string, new: string, year: int, date: string, precision: string, initial: bool, quote: string, to_year: ?int, strong: bool}>
     */
    private static function connected(array $events, string $own): array
    {
        $keys = [$own => true];
        do {
            $changed = false;
            foreach ($events as $e) {
                if ($e['old'] === null || $e['new'] === '') {
                    continue;
                }
                $a = RenameExtractor::key($e['old']);
                $b = RenameExtractor::key($e['new']);
                if (isset($keys[$a]) !== isset($keys[$b])) {
                    $keys[$a] = $keys[$b] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        return array_values(array_filter($events, function (array $e) use ($keys, $own): bool {
            $new = $e['new'] === '' ? null : RenameExtractor::key($e['new']);
            if ($e['old'] !== null) {
                return $new === null || $e['initial'] || (isset($keys[RenameExtractor::key($e['old'])]) && isset($keys[$new]));
            }

            return $new !== null && $own !== '' && $new !== '' && (isset($keys[$new]) || str_contains($new, $own) || str_contains($own, $new) || self::lang($e['quote']) === 'de');
        }));
    }

    /** @return array<string, mixed> */
    private static function blank(string $name, string $quote): array
    {
        return ['name' => $name, 'quote' => $quote];
    }

    /** Langue du nom : allemand quand la phrase parle d'annexion, d'allemand ou de Reichsbahn, sinon français. */
    private static function lang(string $quote): string
    {
        return preg_match('/allemand|germanis|Reichsbahn|annexion|Reichsland/iu', $quote) === 1 ? 'de' : 'fr';
    }
}
