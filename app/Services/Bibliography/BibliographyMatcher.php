<?php

namespace App\Services\Bibliography;

use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\LinePlaceIndex;
use App\Services\Archives\LinePlaces;
use App\Services\Archives\TitlePlaces;

/**
 * Rattachement prudent d'une référence bibliographique à une ligne ou à une gare. Jamais un ouvrage général sur
 * « les chemins de fer » : un titre qui ne nomme aucun lieu ni nom d'usage de la ligne n'est rattaché à rien.
 *
 * Notices de la BnF (jugées sur le titre seul) :
 *   - `title` : le score d'ArchiveMatcher, le même que pour les documents de Gallica (deux lieux de la ligne dont un
 *     trajet ou les extrémités, un mot du rail, une date compatible), au même seuil ;
 *   - `title_alias` : le titre écrit en entier un nom d'usage de la ligne (« la ligne des Causses ») avec un mot du rail.
 * Références d'un article de Wikipédia (l'article de la ligne ou de la gare les cite) :
 *   - `article_bibliography` : référence de la section « Bibliographie » ; le titre nomme au moins deux lieux de la
 *     ligne, ou un lieu ou un nom d'usage de la ligne sans nommer aucun lieu étranger à la ligne (« Les chemins de fer de
 *     Paris à Rouen » n'est pas une référence de Paris - Lyon) ;
 *   - `article_reference` : référence d'une note, mêmes conditions et le titre parle en outre de chemin de fer ;
 *   - pour une gare : le titre nomme la gare (« la gare de X ») et, d'une note, parle aussi de chemin de fer ou de gare.
 */
final class BibliographyMatcher
{
    public const BASIS_TITLE = 'title';

    public const BASIS_ALIAS = 'title_alias';

    public const BASIS_BIBLIOGRAPHY = 'article_bibliography';

    public const BASIS_REFERENCE = 'article_reference';

    public const BASIS_WIKIDATA = 'wikidata';

    private const RAIL = '/\b(chemins? de fer|voies? ferrees?|railways?|tramways?|ferroviaires?|ligne|lignes|train|trains|gare|gares|c de f)\b/';

    private const STRONG_RAIL = '/\b(chemins? de fer|voies? ferrees?|railways?|tramways?|ferroviaires?|ligne|lignes|train|trains|c de f)\b/';

    /** Textes officiels cités comme sources (Bulletin des lois, Journal officiel) : des références de l'article, pas des lectures. */
    private const OFFICIAL = '/^\W*(?:(?:n[°o]\s*)?\d+\s*[-\x{2013}\x{2014}]|d[ée]crets?\b|lois?\b|ordonnances?\b|arr[êe]t[ée]s?\b|circulaires?\b|d[ée]lib[ée]rations?\b|conventions?\b|cahiers? des charges\b|journal officiel|bulletin des lois|rapports?\b|(?:projet|proposition) de loi|p[ée]titions?\b|chambre des (?:pairs|d[ée]put[ée]s)|s[ée]nat\b|s[ée]ance du|assembl[ée]e (?:nationale|l[ée]gislative))/iu';

    /** Revues dont un article d'une note vaut une lecture : revues ferroviaires, d'histoire, sociétés savantes, cahiers et bulletins. */
    private const PERIODICAL = '/\b(rail|chemins? de fer|train|trains|revue|bulletin|annales|cahiers?|soci[ée]t[ée]|histoire|historail|connaissance|m[ée]moires?|historique|voie libre|vie du rail|arch[ée]ologie)\b/iu';

    public function __construct(private readonly ArchiveMatcher $archive = new ArchiveMatcher) {}

    /**
     * Lignes d'une notice de la BnF : celles que le score retient, puis celles dont un nom d'usage est écrit en entier.
     *
     * @return list<array{line_id: int, basis: string, score: ?int, reasons: list<array{code: string, points: int}>}>
     */
    public function bnfLines(BibliographyRecord $record, LinePlaceIndex $index, LineAliases $aliases, int $threshold = ArchiveMatcher::THRESHOLD): array
    {
        if (self::isOfficialText($record->title)) {
            return [];
        }
        $archiveRecord = $record->asArchiveRecord();
        $winners = $this->archive->winners($this->archive->candidates($archiveRecord, $index), $threshold);
        $out = [];
        $tokens = TitlePlaces::tokens($record->matchText());
        $found = TitlePlaces::find($tokens, $index->keys(), $index->maxWords(), TitlePlaces::allCaps($record->matchText()));
        foreach ($winners as $m) {
            $line = $index->line($m->lineId);
            // « de Paris à Dijon par Troyes » : un itinéraire par un lieu que la ligne ne dessert pas est une autre ligne.
            if ($line !== null && self::viaForeignPlace($tokens, $found, $line)) {
                continue;
            }
            $out[$m->lineId] = ['line_id' => $m->lineId, 'basis' => self::BASIS_TITLE, 'score' => $m->score, 'reasons' => $m->reasons];
        }
        $text = implode(' ', array_column($tokens, 'lower'));
        if (preg_match(self::STRONG_RAIL, $text) === 1) {
            foreach ($aliases->linesNamedIn($tokens) as $id) {
                $line = $index->line($id);
                if ($line !== null && ! isset($out[$id]) && $this->dateFits($record, $line)) {
                    $out[$id] = ['line_id' => $id, 'basis' => self::BASIS_ALIAS, 'score' => null, 'reasons' => [['code' => 'line_name', 'points' => 0]]];
                }
            }
        }

        return array_values($out);
    }

    /**
     * Le titre fait-il passer l'itinéraire « par » un lieu que la ligne ne dessert pas ?
     *
     * @param  list<array{lower: string, upper: bool, sep: string}>  $tokens
     * @param  list<array{key: string, start: int, end: int}>  $found
     */
    private static function viaForeignPlace(array $tokens, array $found, LinePlaces $line): bool
    {
        foreach ($found as $f) {
            $before = $tokens[$f['start'] - 1]['lower'] ?? '';
            $beforeThat = $tokens[$f['start'] - 2]['lower'] ?? '';
            $via = $before === 'par' || (in_array($before, ['la', 'le', 'les', 'l'], true) && $beforeThat === 'par');
            if ($via && ! isset($line->places[$f['key']])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Une référence de l'article d'une ligne : rattachement et raisons, ou null.
     *
     * @param  list<array{full: list<string>, key: list<string>}>  $aliases  noms d'usage de la ligne
     * @return array{basis: string, reasons: list<array{code: string, points: int}>}|null
     */
    public function articleLine(BibliographyRecord $record, string $where, LinePlaces $line, LinePlaceIndex $index, array $aliases): ?array
    {
        if (! self::readable($record, $where)) {
            return null;
        }
        $tokens = TitlePlaces::tokens($record->matchText());
        $found = TitlePlaces::find($tokens, $index->keys(), $index->maxWords(), TitlePlaces::allCaps($record->matchText()));
        $keys = array_values(array_unique(array_column($found, 'key')));
        $mine = array_values(array_filter($keys, fn (string $k): bool => isset($line->places[$k])));
        $foreign = array_diff($keys, $mine);
        $words = array_column($tokens, 'lower');
        $named = false;
        foreach ($aliases as $alias) {
            $named = $named || LineAliases::contains($words, $alias['key']);
        }
        $text = implode(' ', $words);
        $rail = preg_match(self::STRONG_RAIL, $text) === 1;

        $placed = count($mine) >= 2 || (count($mine) === 1 && $foreign === []) || $named;
        if (! $placed || ($where !== WikitextReferences::BIBLIOGRAPHY && ! $rail)) {
            return null;
        }
        $reasons = [['code' => $where === WikitextReferences::BIBLIOGRAPHY ? 'article_bibliography' : 'article_reference', 'points' => 0]];
        if ($mine !== []) {
            $reasons[] = ['code' => 'places:'.count($mine), 'points' => 0];
        }
        if ($named) {
            $reasons[] = ['code' => 'line_name', 'points' => 0];
        }
        if ($rail) {
            $reasons[] = ['code' => 'rail_word', 'points' => 0];
        }

        return ['basis' => $where === WikitextReferences::BIBLIOGRAPHY ? self::BASIS_BIBLIOGRAPHY : self::BASIS_REFERENCE, 'reasons' => $reasons];
    }

    /**
     * Une référence de l'article d'une gare : le titre nomme cette gare ; d'une note, il parle aussi de chemin de fer
     * ou de gare.
     *
     * @return array{basis: string, reasons: list<array{code: string, points: int}>}|null
     */
    public function articleStation(BibliographyRecord $record, string $where, int $stationId, StationNames $stations): ?array
    {
        if (! self::readable($record, $where)) {
            return null;
        }
        $tokens = TitlePlaces::tokens($record->matchText());
        if (! in_array($stationId, $stations->stationsNamedIn($tokens), true)) {
            return null;
        }
        $text = implode(' ', array_column($tokens, 'lower'));
        if ($where !== WikitextReferences::BIBLIOGRAPHY && preg_match(self::RAIL, $text) !== 1) {
            return null;
        }
        // Une note d'article de gare cite surtout des tracts, des communiqués et la presse locale : seuls y sont retenus un livre
        // à ISBN et un article de revue (readable() a déjà écarté les articles d'autres journaux).
        if ($where !== WikitextReferences::BIBLIOGRAPHY && $record->kind === BibliographyRecord::BOOK && $record->isbn === null) {
            return null;
        }

        return [
            'basis' => $where === WikitextReferences::BIBLIOGRAPHY ? self::BASIS_BIBLIOGRAPHY : self::BASIS_REFERENCE,
            'reasons' => [['code' => $where === WikitextReferences::BIBLIOGRAPHY ? 'article_bibliography' : 'article_reference', 'points' => 0], ['code' => 'station_name', 'points' => 0]],
        ];
    }

    /** Texte officiel (loi, décret, rapport parlementaire, pétition) : une source primaire, pas une lecture. */
    public static function isOfficialText(string $title): bool
    {
        return preg_match(self::OFFICIAL, $title) === 1;
    }

    /**
     * Le titre de la notice trouvée par ISBN est-il celui de la référence ? Au moins la moitié des mots de quatre lettres du plus
     * court des deux titres figure dans l'autre : un ISBN mal recopié dans l'article ne fait pas changer d'ouvrage.
     */
    public static function titlesAgree(string $a, string $b): bool
    {
        $words = fn (string $t): array => array_values(array_unique(array_filter(explode(' ', BibliographyRecord::norm($t)), fn (string $w): bool => strlen($w) >= 4)));
        [$wa, $wb] = [$words($a), $words($b)];
        if ($wa === [] || $wb === []) {
            return false;
        }
        [$short, $long] = count($wa) <= count($wb) ? [$wa, $wb] : [$wb, $wa];

        return count(array_intersect($short, $long)) / count($short) >= 0.5;
    }

    /**
     * Une référence d'article est-elle une lecture ? Ni un texte officiel (décret, loi, Bulletin des lois : une source de
     * l'article), ni, quand elle vient d'une note, un article de presse : un article de note n'est retenu que d'une revue.
     */
    public static function readable(BibliographyRecord $record, string $where): bool
    {
        if (self::isOfficialText($record->title)) {
            return false;
        }

        if ($record->journal !== null && preg_match('/bulletin des lois|journal officiel/iu', $record->journal) === 1) {
            return false;
        }

        return $record->kind !== BibliographyRecord::ARTICLE || $where === WikitextReferences::BIBLIOGRAPHY
            || ($record->journal !== null && preg_match(self::PERIODICAL, $record->journal) === 1);
    }

    /**
     * Gares d'une notice de la BnF : « la gare de X » dans le titre.
     *
     * @return list<int>
     */
    public function bnfStations(BibliographyRecord $record, StationNames $stations): array
    {
        if (self::isOfficialText($record->title)) {
            return [];
        }

        return $stations->stationsNamedIn(TitlePlaces::tokens($record->matchText()));
    }

    /** L'année de publication n'est pas antérieure à la ligne (à 40 ans près, projets compris). */
    private function dateFits(BibliographyRecord $record, LinePlaces $line): bool
    {
        if ($record->year === null) {
            return true;
        }

        return $record->year >= ArchiveMatcher::EARLIEST_YEAR && ($line->firstYear === null || $record->year >= $line->firstYear - ArchiveMatcher::PROJECT_YEARS);
    }
}
