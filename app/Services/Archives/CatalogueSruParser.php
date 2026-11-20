<?php

namespace App\Services\Archives;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Lecture d'une réponse `searchRetrieve` de l'API SRU du catalogue général de la BnF
 * (https://catalogue.bnf.fr/api/SRU, notices en UNIMARC, schéma `unimarcXchange`).
 *
 * Zones lues : guide (type de notice en position 6, niveau bibliographique en position 7), 001 et 003
 * (ark de la notice), 100 (dates codées), 200 (titre propre $a, compléments $e, parties $h $i),
 * 210 / 214 $d (date imprimée), 700 à 702 et 710 à 712 (auteurs, collectivités), 856 $u (document numérisé
 * dans Gallica), 930 $a (cote de l'original ; les cotes « NUMM-… » et « IFN-… » des documents numérisés
 * sont écartées). Une notice sans lien vers Gallica est ignorée : ce n'est pas un document de Gallica.
 */
final class CatalogueSruParser
{
    private const NS_SRW = 'http://www.loc.gov/zing/srw/';

    private const NS_MXC = 'info:lc/xmlns/marcxchange-v2';

    private const NS_IXM = 'http://catalogue.bnf.fr/namespaces/InterXMarc';

    /**
     * Type de document selon la position 6 du guide UNIMARC : a texte imprimé, b texte manuscrit,
     * e document cartographique imprimé, f document cartographique manuscrit, k image fixe. Les autres
     * (musique, son, vidéo, objets, ressources électroniques) ne sont pas retenus.
     */
    private const TYPES = ['a' => 'monograph', 'b' => 'manuscript', 'e' => 'map', 'f' => 'map', 'k' => 'image'];

    /** Caractères de non-classement des titres UNIMARC (début et fin de l'article ignoré au tri). */
    private const NON_SORT = ["\u{0088}", "\u{0089}", "\u{0098}", "\u{009C}"];

    /** Nombre maximal d'auteurs gardés par document. */
    private const MAX_AUTHORS = 4;

    /**
     * @return array{total: int, records: list<ArchiveRecord>}
     *
     * @throws RuntimeException réponse illisible, diagnostic SRU (requête refusée) ou XML qui n'est pas une réponse
     *                          SRU (page de maintenance servie avec le code 200 : pas de `srw:numberOfRecords`)
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
        $xp->registerNamespace('ixm', self::NS_IXM);

        $diagnostic = trim((string) $xp->evaluate('string(//srw:diagnostics//*[local-name()="message"])'));
        if ($diagnostic !== '') {
            throw new RuntimeException('Diagnostic SRU : '.$diagnostic);
        }
        $total = trim((string) $xp->evaluate('string(/srw:searchRetrieveResponse/srw:numberOfRecords)'));
        if (! ctype_digit($total)) {
            throw new RuntimeException('Réponse qui n\'est pas une réponse SRU (nombre de notices absent).');
        }

        $records = [];
        foreach ($xp->query('//srw:records/srw:record') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $record = $this->record($xp, $node);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return ['total' => (int) $total, 'records' => $records];
    }

    private function record(DOMXPath $xp, DOMElement $node): ?ArchiveRecord
    {
        $marc = self::first($xp, './/mxc:record', $node);
        if ($marc === null) {
            return null;
        }
        $gallica = null;
        foreach ($this->subfields($xp, $marc, '856', 'u') as $url) {
            if (preg_match('#gallica\.bnf\.fr/(ark:/12148/[a-z0-9]+)#i', $url, $m) === 1) {
                $gallica = $m[1];
                break;
            }
        }
        if ($gallica === null) {
            return null;
        }

        $leader = (string) $xp->evaluate('string(mxc:leader)', $marc);
        $type = strlen($leader) > 7 && $leader[7] === 'm' ? (self::TYPES[$leader[6]] ?? null) : null;

        $catalogueArk = null;
        $ctl = (string) $xp->evaluate('string(mxc:controlfield[@tag="003"])', $marc);
        if (preg_match('#(ark:/12148/[a-z0-9]+)#i', $ctl !== '' ? $ctl : (string) $marc->getAttribute('id'), $m) === 1) {
            $catalogueArk = $m[1];
        }

        $titleField = self::first($xp, 'mxc:datafield[@tag="200"]', $marc);
        $main = $complements = [];
        if ($titleField !== null) {
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
        if ($complements !== []) {
            $title .= ' : '.$complements[0];
        }

        $label = $this->subfields($xp, $marc, '210', 'd')[0] ?? $this->subfields($xp, $marc, '214', 'd')[0] ?? null;
        // Zone codée : lue telle quelle (les positions comptent, blancs compris).
        $coded = (string) $xp->evaluate('string(mxc:datafield[@tag="100"]/mxc:subfield[@code="a"])', $marc);
        $date = ArchiveDate::fromUnimarc($coded !== '' ? $coded : null, $label);

        $updated = (string) $xp->evaluate('string(srw:extraRecordData/ixm:attr[@name="LastModificationDate"])', $node);

        return new ArchiveRecord(
            gallicaArk: $gallica,
            catalogueArk: $catalogueArk,
            title: mb_substr(rtrim($title, ' /:;,'), 0, 1000),
            // Parties séparées comme dans le titre affiché : « Strasbourg à Bâle : Partie… » ne fait pas un nom composé.
            matchText: implode(' : ', [...$main, ...$complements]),
            docType: $type,
            authors: $this->authors($xp, $marc),
            date: $date,
            shelfmark: $this->shelfmark($xp, $marc),
            recordUpdatedOn: preg_match('/^(\d{4})(\d{2})(\d{2})$/', $updated, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? "{$m[1]}-{$m[2]}-{$m[3]}" : null,
        );
    }

    /**
     * Auteurs et collectivités, tels que la notice les écrit : « Villemandy, Alfred de » (7X0 à 7X2).
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
            $a = self::clean((string) $xp->evaluate('string(mxc:subfield[@code="a"])', $field));
            $b = self::clean((string) $xp->evaluate('string(mxc:subfield[@code="b"])', $field));
            if ($a === '') {
                continue;
            }
            $name = str_starts_with($field->getAttribute('tag'), '70') && $b !== '' ? "{$a}, {$b}" : ($b !== '' ? "{$a}. {$b}" : $a);
            if (! in_array($name, $names, true)) {
                $names[] = $name;
            }
            if (count($names) >= self::MAX_AUTHORS) {
                break;
            }
        }

        return $names;
    }

    /** Cote de l'original à la BnF : première cote 930 $a qui n'est pas celle du document numérisé (NUMM-, IFN-). */
    private function shelfmark(DOMXPath $xp, DOMElement $marc): ?string
    {
        foreach ($xp->query('mxc:datafield[@tag="930"]', $marc) ?: [] as $field) {
            if (! $field instanceof DOMElement) {
                continue;
            }
            $cote = self::clean((string) $xp->evaluate('string(mxc:subfield[@code="a"])', $field));
            $place = (string) $xp->evaluate('string(mxc:subfield[@code="c"])', $field);
            if ($cote !== '' && preg_match('/^(NUMM|IFN)-/', $cote) !== 1 && stripos($place, 'numérisé') === false) {
                return mb_substr($cote, 0, 120);
            }
        }

        return null;
    }

    /** @return list<string> valeurs de la sous-zone `$code` des zones `$tag`, dans l'ordre */
    private function subfields(DOMXPath $xp, DOMElement $marc, string $tag, string $code): array
    {
        $out = [];
        foreach ($xp->query("mxc:datafield[@tag=\"{$tag}\"]/mxc:subfield[@code=\"{$code}\"]", $marc) ?: [] as $sf) {
            $out[] = self::clean($sf->textContent);
        }

        return $out;
    }

    private static function first(DOMXPath $xp, string $expression, DOMElement $context): ?DOMElement
    {
        $nodes = $xp->query($expression, $context);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /** Sans caractères de non-classement ni blancs répétés. */
    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(self::NON_SORT, '', $value)));
    }
}
