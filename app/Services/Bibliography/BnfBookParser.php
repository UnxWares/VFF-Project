<?php

namespace App\Services\Bibliography;

use App\Services\Archives\ArchiveDate;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Lecture d'une réponse `searchRetrieve` de l'API SRU du catalogue général de la BnF (notices UNIMARC) pour la
 * bibliographie : les livres (texte imprimé, monographie), avec leurs auteurs, leur éditeur, leur année et leur ISBN.
 *
 * Zones lues : guide (position 6 « a » : texte imprimé ; position 7 « m » : monographie), 003 (ark de la notice),
 * 010 $a (ISBN), 100 et 210 / 214 $d (année), 200 (titre propre $a, compléments $e, parties $h $i), 210 / 214
 * $a $c (lieu et éditeur), 700 à 702 et 710 à 712 (auteurs, directeurs, collectivités ; les préfaciers, illustrateurs
 * et photographes sont laissés de côté). Seules les métadonnées descriptives sont reprises (Licence Ouverte).
 */
final class BnfBookParser
{
    private const NS_SRW = 'http://www.loc.gov/zing/srw/';

    private const NS_MXC = 'info:lc/xmlns/marcxchange-v2';

    private const NON_SORT = ["\u{0088}", "\u{0089}", "\u{0098}", "\u{009C}"];

    private const MAX_AUTHORS = 4;

    /** Codes de fonction UNIMARC retenus : auteur, directeur de publication, éditeur scientifique, collectivité auteur. */
    private const AUTHOR_ROLES = ['070', '651', '340', '090', '020'];

    /**
     * @return array{total: int, records: list<BibliographyRecord>}
     *
     * @throws RuntimeException réponse illisible, diagnostic SRU ou XML qui n'est pas une réponse SRU
     */
    public function parse(string $xml): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $xml !== '' && $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new RuntimeException('Réponse SRU illisible (XML invalide).');
        }
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('srw', self::NS_SRW);
        $xp->registerNamespace('mxc', self::NS_MXC);

        $diagnostic = trim((string) $xp->evaluate('string(//srw:diagnostics//*[local-name()="message"])'));
        if ($diagnostic !== '') {
            throw new RuntimeException('Diagnostic SRU : '.$diagnostic);
        }
        $total = trim((string) $xp->evaluate('string(/srw:searchRetrieveResponse/srw:numberOfRecords)'));
        if (! ctype_digit($total)) {
            throw new RuntimeException('Réponse qui n\'est pas une réponse SRU (nombre de notices absent).');
        }

        $records = [];
        foreach ($xp->query('//srw:records/srw:record//mxc:record') ?: [] as $marc) {
            if ($marc instanceof DOMElement && ($record = $this->record($xp, $marc)) !== null) {
                $records[] = $record;
            }
        }

        return ['total' => (int) $total, 'records' => $records];
    }

    private function record(DOMXPath $xp, DOMElement $marc): ?BibliographyRecord
    {
        $leader = (string) $xp->evaluate('string(mxc:leader)', $marc);
        if (strlen($leader) < 8 || $leader[6] !== 'a' || $leader[7] !== 'm') {
            return null;
        }
        $ark = null;
        $ctl = (string) $xp->evaluate('string(mxc:controlfield[@tag="003"])', $marc);
        if (preg_match('#(ark:/12148/cb[a-z0-9]+)#i', $ctl !== '' ? $ctl : (string) $marc->getAttribute('id'), $m) === 1) {
            $ark = $m[1];
        }

        $main = $complements = [];
        $titleField = $xp->query('mxc:datafield[@tag="200"]', $marc)->item(0);
        if ($titleField instanceof DOMElement) {
            foreach ($xp->query('mxc:subfield', $titleField) ?: [] as $sf) {
                if (! $sf instanceof DOMElement) {
                    continue;
                }
                $value = self::clean($sf->textContent);
                match ($sf->getAttribute('code')) {
                    'a' => $main[] = $value,
                    'e', 'h', 'i' => $complements[] = $value,
                    default => null,
                };
            }
        }
        $main = array_values(array_filter($main, fn (string $v): bool => $v !== ''));
        $complements = array_values(array_filter($complements, fn (string $v): bool => $v !== ''));
        if ($main === []) {
            return null;
        }
        $title = implode('. ', $main);
        // Premier complément (sous-titre), puis le tome ou la partie : « Titre : sous-titre. Tome 1 ».
        $subtitle = $this->subfield($xp, $marc, '200', 'e');
        $part = array_values(array_filter($complements, fn (string $c): bool => $c !== $subtitle));
        if ($subtitle !== null && $subtitle !== '') {
            $title .= ' : '.$subtitle;
        }
        if ($part !== []) {
            $title .= '. '.implode('. ', $part);
        }

        $imprint = $xp->query('mxc:datafield[@tag="210"]', $marc)->item(0) ?? $xp->query('mxc:datafield[@tag="214"]', $marc)->item(0);
        $label = $imprint instanceof DOMElement ? self::clean((string) $xp->evaluate('string(mxc:subfield[@code="d"])', $imprint)) : '';
        $coded = (string) $xp->evaluate('string(mxc:datafield[@tag="100"]/mxc:subfield[@code="a"])', $marc);
        $date = ArchiveDate::fromUnimarc($coded !== '' ? $coded : null, $label !== '' ? $label : null);

        return new BibliographyRecord(
            kind: BibliographyRecord::BOOK,
            title: mb_substr(rtrim($title, ' /:;,'), 0, 1000),
            authors: $this->authors($xp, $marc),
            publisher: $imprint instanceof DOMElement ? self::publisher((string) $xp->evaluate('string(mxc:subfield[@code="c"])', $imprint)) : null,
            place: $imprint instanceof DOMElement ? self::place((string) $xp->evaluate('string(mxc:subfield[@code="a"])', $imprint)) : null,
            year: $date->year,
            isbn: ($isbns = $this->isbns($xp, $marc))[0] ?? null,
            journal: null,
            provider: 'bnf',
            url: $ark === null ? null : 'https://catalogue.bnf.fr/'.$ark,
            bnfArk: $ark,
            isbns: $isbns,
        );
    }

    /** @return list<string> */
    private function isbns(DOMXPath $xp, DOMElement $marc): array
    {
        $out = [];
        foreach ($xp->query('mxc:datafield[@tag="010"]/mxc:subfield[@code="a"]', $marc) ?: [] as $sf) {
            $isbn = Isbn::normalize(self::clean($sf->textContent));
            if ($isbn !== null && ! in_array($isbn, $out, true)) {
                $out[] = $isbn;
            }
        }

        return $out;
    }

    /**
     * Auteurs en « Prénom Nom » (la notice donne « Nom, Prénom »), collectivités telles quelles.
     *
     * @return list<string>
     */
    private function authors(DOMXPath $xp, DOMElement $marc): array
    {
        $names = [];
        foreach ($xp->query('mxc:datafield[@tag="700" or @tag="701" or @tag="702" or @tag="710" or @tag="711" or @tag="712"]', $marc) ?: [] as $field) {
            if (! $field instanceof DOMElement) {
                continue;
            }
            $roles = [];
            foreach ($xp->query('mxc:subfield[@code="4"]', $field) ?: [] as $r) {
                $roles[] = trim($r->textContent);
            }
            if ($roles !== [] && array_intersect($roles, self::AUTHOR_ROLES) === []) {
                continue;
            }
            $a = self::clean((string) $xp->evaluate('string(mxc:subfield[@code="a"])', $field));
            $b = self::clean((string) $xp->evaluate('string(mxc:subfield[@code="b"])', $field));
            if ($a === '') {
                continue;
            }
            $name = str_starts_with($field->getAttribute('tag'), '70')
                ? ($b !== '' ? "{$b} {$a}" : $a)
                : ($b !== '' ? "{$a}. {$b}" : $a);
            if (! in_array($name, $names, true)) {
                $names[] = $name;
            }
            if (count($names) >= self::MAX_AUTHORS) {
                break;
            }
        }

        return $names;
    }

    private function subfield(DOMXPath $xp, DOMElement $marc, string $tag, string $code): ?string
    {
        $value = self::clean((string) $xp->evaluate("string(mxc:datafield[@tag=\"{$tag}\"]/mxc:subfield[@code=\"{$code}\"])", $marc));

        return $value === '' ? null : $value;
    }

    private static function publisher(string $raw): ?string
    {
        $v = trim(self::clean($raw), ' []');

        return $v === '' || preg_match('/^s\.?\s?n\.?$/i', $v) === 1 ? null : mb_substr($v, 0, 255);
    }

    private static function place(string $raw): ?string
    {
        $v = trim(self::clean($raw), ' []');

        return $v === '' || preg_match('/^s\.?\s?l\.?$/i', $v) === 1 ? null : mb_substr($v, 0, 120);
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(self::NON_SORT, '', $value)));
    }
}
