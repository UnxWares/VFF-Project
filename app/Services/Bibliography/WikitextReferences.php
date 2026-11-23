<?php

namespace App\Services\Bibliography;

use Illuminate\Support\Str;

/**
 * Références bibliographiques d'un article de Wikipédia (wikitexte) : les modèles {{Ouvrage}} et {{Article}} et les
 * lignes de listes de la section « Bibliographie » (auteur, titre en italique, éditeur, année, ISBN).
 *
 * Chaque référence porte son emplacement : `bibliography` (section « Bibliographie » ou voisine : l'auteur de l'article
 * la recommande) ou `reference` (citée dans une note ou ailleurs dans l'article). Seules les données de la référence
 * sont reprises (faits bibliographiques) ; les liens externes de l'article ne le sont pas. Une ligne qui n'a ni titre
 * en italique, ni modèle, n'est pas lue : mieux vaut une référence de moins qu'une référence mal découpée.
 */
final class WikitextReferences
{
    public const BIBLIOGRAPHY = 'bibliography';

    public const REFERENCE = 'reference';

    /** Titres de section qui annoncent une bibliographie. */
    private const HEADINGS = '/^(bibliographie|bibliographies|lectures? compl[ée]mentaires?|pour approfondir|pour en savoir plus|ouvrages|sources imprim[ée]es)\b/u';

    /** @return list<array{record: BibliographyRecord, where: string}> */
    public function extract(string $wikitext, string $originUrl): array
    {
        $text = (string) preg_replace('/<!--.*?-->/su', '', $wikitext);
        $spans = $this->bibliographySpans($text);
        $refs = [];
        if (preg_match_all('/<ref\b[^>\/]*>.*?<\/ref>/su', $text, $m, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($m[0] as [$whole, $offset]) {
                $refs[] = [$offset, $offset + strlen($whole)];
            }
        }
        $within = fn (array $ranges, int $pos): bool => array_filter($ranges, fn (array $r): bool => $pos >= $r[0] && $pos < $r[1]) !== [];

        $found = [];
        $templateSpans = [];
        foreach ($this->templates($text) as ['name' => $name, 'params' => $params, 'start' => $start, 'end' => $end]) {
            $templateSpans[] = [$start, $end];
            $record = match (Str::ascii(mb_strtolower($name))) {
                'ouvrage' => $this->fromOuvrage($params, $originUrl),
                'article' => $this->fromArticle($params, $originUrl),
                default => null,
            };
            if ($record === null) {
                continue;
            }
            $inBibliography = $within($spans, $start);
            $inRef = $within($refs, $start);
            // Dans une note à l'intérieur de la bibliographie : la section prime seulement si ce n'est pas une note.
            $where = $inBibliography && ! $inRef ? self::BIBLIOGRAPHY : self::REFERENCE;
            $this->keep($found, $record, $where);
        }

        foreach ($spans as [$from, $to]) {
            $offset = $from;
            foreach (preg_split('/\R/u', substr($text, $from, $to - $from)) ?: [] as $line) {
                $lineStart = $offset;
                $offset += strlen($line) + 1;
                if (preg_match('/^\s*[*#]+\s*(.+)$/u', $line, $mm) !== 1) {
                    continue;
                }
                // Ligne déjà lue par un modèle {{Ouvrage}} ou {{Article}}.
                if ($within($templateSpans, $lineStart + strlen($line) - strlen($mm[1]))) {
                    continue;
                }
                if (($record = $this->fromBullet($mm[1], $originUrl)) !== null) {
                    $this->keep($found, $record, self::BIBLIOGRAPHY);
                }
            }
        }

        return array_values($found);
    }

    /** @param  array<string, array{record: BibliographyRecord, where: string}>  $found */
    private function keep(array &$found, BibliographyRecord $record, string $where): void
    {
        $key = $record->dedupKey();
        if (! isset($found[$key]) || ($where === self::BIBLIOGRAPHY && $found[$key]['where'] !== self::BIBLIOGRAPHY)) {
            $found[$key] = ['record' => $record, 'where' => $where];
        }
    }

    /**
     * Intervalles [début, fin) des sections de bibliographie (sous-sections comprises).
     *
     * @return list<array{0: int, 1: int}>
     */
    private function bibliographySpans(string $text): array
    {
        if (preg_match_all('/^(={2,6})[ \t]*(.+?)[ \t]*\1[ \t]*$/mu', $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) < 1) {
            return [];
        }
        $spans = [];
        foreach ($m as $i => $h) {
            $title = mb_strtolower(trim(self::plain($h[2][0])));
            if (preg_match(self::HEADINGS, $title) !== 1) {
                continue;
            }
            $level = strlen($h[1][0]);
            $from = $h[0][1] + strlen($h[0][0]);
            $to = strlen($text);
            for ($j = $i + 1; $j < count($m); $j++) {
                if (strlen($m[$j][1][0]) <= $level) {
                    $to = $m[$j][0][1];
                    break;
                }
            }
            $spans[] = [$from, $to];
        }

        return $spans;
    }

    /**
     * Modèles du texte, avec leurs paramètres (nom → valeur brute) et leur position, imbrications comprises.
     *
     * @return list<array{name: string, params: array<string, string>, start: int, end: int}>
     */
    public function templates(string $text): array
    {
        $out = [];
        $len = strlen($text);
        $i = 0;
        while (($i = strpos($text, '{{', $i)) !== false) {
            $depth = 0;
            $j = $i;
            while ($j < $len - 1) {
                $pair = $text[$j].$text[$j + 1];
                if ($pair === '{{') {
                    $depth++;
                    $j += 2;
                } elseif ($pair === '}}') {
                    $depth--;
                    $j += 2;
                    if ($depth === 0) {
                        break;
                    }
                } else {
                    $j++;
                }
            }
            if ($depth !== 0) {
                break;
            }
            $body = substr($text, $i + 2, $j - $i - 4);
            $parts = $this->splitParams($body);
            $name = trim(array_shift($parts) ?? '');
            $params = [];
            $n = 0;
            foreach ($parts as $part) {
                if (preg_match('/^\s*([^=|{}\[\]]{1,40}?)\s*=(.*)$/su', $part, $kv) === 1) {
                    $params[mb_strtolower(trim($kv[1]))] = trim($kv[2]);
                } else {
                    $params[(string) (++$n)] = trim($part);
                }
            }
            $out[] = ['name' => $name, 'params' => $params, 'start' => $i, 'end' => $j];
            // Les modèles imbriqués (dans une note, dans un autre modèle) sont lus à leur tour.
            $i += 2;
        }

        return $out;
    }

    /** @return list<string> */
    private function splitParams(string $body): array
    {
        $parts = [];
        $buf = '';
        $depth = 0;
        $len = strlen($body);
        for ($k = 0; $k < $len; $k++) {
            $two = substr($body, $k, 2);
            if ($two === '{{' || $two === '[[') {
                $depth++;
                $buf .= $two;
                $k++;
            } elseif ($two === '}}' || $two === ']]') {
                $depth--;
                $buf .= $two;
                $k++;
            } elseif ($body[$k] === '|' && $depth === 0) {
                $parts[] = $buf;
                $buf = '';
            } else {
                $buf .= $body[$k];
            }
        }
        $parts[] = $buf;

        return $parts;
    }

    /** @param  array<string, string>  $p */
    private function fromOuvrage(array $p, string $origin): ?BibliographyRecord
    {
        return $this->build(BibliographyRecord::BOOK, $p, $origin, null);
    }

    /** @param  array<string, string>  $p */
    private function fromArticle(array $p, string $origin): ?BibliographyRecord
    {
        $journal = self::plain($p['périodique'] ?? $p['revue'] ?? $p['journal'] ?? $p['magazine'] ?? '');

        return $this->build(BibliographyRecord::ARTICLE, $p, $origin, $journal === '' ? null : mb_substr($journal, 0, 255));
    }

    /** @param  array<string, string>  $p */
    private function build(string $kind, array $p, string $origin, ?string $journal): ?BibliographyRecord
    {
        $title = self::plain($p['titre'] ?? '');
        $sub = self::plain($p['sous-titre'] ?? '');
        if (mb_strlen($title) < 4) {
            return null;
        }
        if ($sub !== '') {
            $title .= ' : '.$sub;
        }
        $authors = [];
        for ($n = 1; $n <= 9; $n++) {
            $suffix = $n === 1 ? ['', '1'] : [(string) $n];
            foreach ($suffix as $s) {
                $full = self::plain($p['auteur'.$s] ?? '');
                $last = self::plain($p['nom'.$s] ?? '');
                $first = self::plain($p['prénom'.$s] ?? $p['prenom'.$s] ?? '');
                $name = $last !== '' ? trim($first.' '.$last) : $full;
                if ($name !== '' && ! in_array($name, $authors, true) && preg_match('/^(et al\.?|collectif|ouvrage collectif)$/iu', $name) !== 1) {
                    $authors[] = mb_substr($name, 0, 120);
                }
            }
        }
        foreach (self::plain($p['auteurs'] ?? '') === '' ? [] : preg_split('/\s*[;,]\s*/u', self::plain($p['auteurs'])) as $name) {
            if ($name !== '' && ! in_array($name, $authors, true)) {
                $authors[] = mb_substr($name, 0, 120);
            }
        }
        $date = self::plain($p['année'] ?? $p['annee'] ?? $p['date'] ?? $p['année d\'édition'] ?? '');
        $year = preg_match('/(?<!\d)(1[5-9]\d\d|20\d\d)(?!\d)/', $date, $m) === 1 ? (int) $m[1] : null;

        return new BibliographyRecord(
            kind: $kind,
            title: mb_substr($title, 0, 1000),
            authors: array_slice($authors, 0, 4),
            publisher: ($v = self::plain($p['éditeur'] ?? $p['editeur'] ?? '')) === '' ? null : mb_substr($v, 0, 255),
            place: ($v = self::plain($p['lieu'] ?? '')) === '' ? null : mb_substr($v, 0, 120),
            year: $year,
            isbn: Isbn::normalize($p['isbn'] ?? $p['id'] ?? null),
            journal: $journal,
            provider: 'wikipedia',
            url: $origin,
        );
    }

    /** Ligne de liste libre : « Auteur, ''Titre'', Éditeur, année, ISBN … ». Null quand elle ne se découpe pas sûrement. */
    private function fromBullet(string $line, string $origin): ?BibliographyRecord
    {
        $line = (string) preg_replace('/\{\{\s*ISBN\s*\|([^}]*)\}\}/iu', ' ISBN $1 ', $line);
        $line = (string) preg_replace('/<ref\b[^>]*\/>|<ref\b[^>]*>.*?<\/ref>/su', ' ', $line);
        if (preg_match("/^(.*?)(?<!')''(?!')(.+?)(?<!')''(?!')(.*)$/su", $line, $m) !== 1) {
            return null;
        }
        $title = self::plain($m[2]);
        $before = trim(self::plain($m[1]), " \t,:;-");
        $after = self::plain($m[3]);
        if (mb_strlen($title) < 4 || mb_strlen($title) > 300) {
            return null;
        }
        $author = trim((string) preg_replace('/\s*\(?\b(dir|collectif|et al)\.?\)?\s*$/iu', '', $before), " \t,:;-");
        if (mb_strlen($author) > 120 || preg_match('/\d{3}|\bp\.|\bpp\./u', $author) === 1 || ! self::looksLikeAuthors($author)) {
            return null;
        }
        $year = preg_match('/(?<!\d)(1[5-9]\d\d|20\d\d)(?!\d)/', $after, $y) === 1 ? (int) $y[1] : null;
        $segments = [];
        foreach (preg_split('/\s*[,;]\s*/u', $after) ?: [] as $seg) {
            $seg = trim($seg, " \t.:()[]");
            if ($seg === '' || mb_strlen($seg) > 60 || preg_match('/\d|ISBN|^p\.|pages?\b|^coll|^collection|^vol|^tome|^t\.|r[ée]impr|r[ée][ée]d|pr[ée]face|illustr|^ill\b|cartes?\b|^n[°o]/iu', $seg) === 1) {
                continue;
            }
            $segments[] = $seg;
        }
        $place = count($segments) >= 2 ? $segments[0] : null;
        $publisher = count($segments) >= 2 ? $segments[1] : ($segments[0] ?? null);
        $isbn = Isbn::normalize($after);
        // Sans année, éditeur ni ISBN, rien ne dit que l'italique est un titre d'ouvrage (« le viaduc de ''Vecchio'' »).
        if ($year === null && $publisher === null && $isbn === null) {
            return null;
        }

        return new BibliographyRecord(
            kind: BibliographyRecord::BOOK,
            title: $title,
            authors: $author === '' ? [] : [$author],
            publisher: $publisher === null ? null : mb_substr($publisher, 0, 255),
            place: $place === null ? null : mb_substr($place, 0, 120),
            year: $year,
            isbn: $isbn,
            journal: null,
            provider: 'wikipedia',
            url: $origin,
        );
    }

    /**
     * Le texte qui précède l'italique est-il une liste d'auteurs (« Jean Dupont et Marie Martin », « J.-M. Biscarat ») ?
     * Vide (titre en tête de ligne) : oui ; sinon sept mots au plus, chacun capitalisé, une particule ou une initiale.
     */
    private static function looksLikeAuthors(string $text): bool
    {
        if ($text === '') {
            return true;
        }
        $words = preg_split('/[\s,;]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > 9) {
            return false;
        }
        foreach ($words as $w) {
            if (preg_match('/^(?:\p{Lu}[\p{L}\'’.-]*|(?:de|du|des|la|le|les|van|von|der|di|da|d[\'’]\p{L}*|et|&|and|dir\.?|coll\.?|ed\.?|éd\.?))$/u', $w) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** Texte brut d'une valeur de wikitexte : liens, italiques, notes, modèles de mise en forme retirés. */
    public static function plain(string $wikitext): string
    {
        $t = $wikitext;
        $t = (string) preg_replace('/<ref\b[^>]*\/>|<ref\b[^>]*>.*?<\/ref>/su', ' ', $t);
        $t = (string) preg_replace('/\{\{\s*(?:lang|nobr|langue)\s*\|(?:[^|{}]*\|)?([^{}]*)\}\}/iu', '$1', $t);
        $t = (string) preg_replace('/\{\{[^{}]*\}\}/u', ' ', $t);
        $t = (string) preg_replace('/\[\[(?:[^|\]]*\|)?([^\]]*)\]\]/u', '$1', $t);
        $t = (string) preg_replace('/\[https?:\/\/\S+\s+([^\]]*)\]|\[https?:\/\/\S+\]/u', '$1', $t);
        $t = (string) preg_replace('/<[^>]+>/u', ' ', $t);
        $t = str_replace(["'''", "''"], '', $t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $t));
    }
}
