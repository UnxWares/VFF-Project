<?php

namespace App\Services\Accidents;

use DOMDocument;
use DOMElement;

/**
 * Lecture du « Bilan général des enquêtes » du BEA-TT (tableau PDF publié sous Licence Ouverte
 * 2.0), converti en XML positionné par pdftohtml (`pdftohtml -xml -i`).
 *
 * Chaque rangée du tableau : date de l'accident, intitulé de l'enquête, lien vers le rapport (ou
 * la note d'étape), mode (F ferroviaire, PN passage à niveau, TG transports guidés, R routier,
 * RM remontées mécaniques, NI navigation intérieure), nombre de tués. Les morceaux d'une rangée
 * partagent la même ordonnée (à 3 points près) ; un intitulé ou un lien renvoyé à la ligne
 * suivante est rattaché à la rangée précédente.
 */
final class BeaTtInventoryParser
{
    private const MODES = ['F', 'PN', 'TG', 'R', 'RM', 'NI'];

    /** Abscisse (en points) où commence la colonne des liens : l'intitulé est à gauche. */
    private const LINK_COLUMN = 700;

    /**
     * @return list<array{date: string, title: string, mode: string, deaths: ?int, url: ?string}>
     */
    public function parse(string $xml): array
    {
        $doc = new DOMDocument;
        if ($xml === '' || ! @$doc->loadXML($xml, LIBXML_NONET)) {
            return [];
        }
        $rows = [];
        foreach ($doc->getElementsByTagName('page') as $page) {
            array_push($rows, ...$this->pageRows($page));
        }

        return $rows;
    }

    /** @return list<array{date: string, title: string, mode: string, deaths: ?int, url: ?string}> */
    private function pageRows(DOMElement $page): array
    {
        // Morceaux de texte groupés par ordonnée (tolérance de 3 points).
        $lines = [];
        foreach ($page->getElementsByTagName('text') as $t) {
            $top = (int) $t->getAttribute('top');
            $key = null;
            foreach (array_keys($lines) as $k) {
                if (abs($k - $top) <= 3) {
                    $key = $k;
                    break;
                }
            }
            $link = $t->getElementsByTagName('a')->item(0);
            $lines[$key ?? $top][] = [
                'left' => (int) $t->getAttribute('left'),
                'width' => (int) $t->getAttribute('width'),
                'text' => $t->textContent,
                'href' => $link instanceof DOMElement ? $link->getAttribute('href') : null,
            ];
        }
        ksort($lines);

        $rows = [];
        $lastTop = null;
        foreach ($lines as $top => $parts) {
            usort($parts, fn ($a, $b) => $a['left'] <=> $b['left']);
            $row = $this->row($parts);
            if ($row !== null) {
                $rows[] = $row;
                $lastTop = $top;

                continue;
            }
            // Suite de la rangée précédente (intitulé sur deux lignes, lien décalé).
            if ($rows !== [] && $lastTop !== null && $top - $lastTop <= 20) {
                $i = count($rows) - 1;
                foreach ($parts as $p) {
                    if ($p['href'] !== null && $rows[$i]['url'] === null) {
                        $rows[$i]['url'] = $p['href'];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Rangée d'accident : commence par une date jj/mm/aaaa, porte un mode connu.
     *
     * @param  list<array{left: int, width: int, text: string, href: ?string}>  $parts
     * @return array{date: string, title: string, mode: string, deaths: ?int, url: ?string}|null
     */
    private function row(array $parts): ?array
    {
        if (! preg_match('#^\s*(\d{2})/(\d{2})/(\d{4})\s*(.*)$#u', $parts[0]['text'], $m)) {
            return null;
        }
        $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        if (! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }
        $title = trim($m[4]);
        $titleEnd = $parts[0]['left'] + $parts[0]['width'];
        $mode = null;
        $deaths = null;
        $url = null;
        foreach (array_slice($parts, 1) as $p) {
            $text = trim($p['text']);
            if ($p['href'] !== null) {
                $url ??= $p['href'];
            } elseif ($mode === null && in_array($text, self::MODES, true)) {
                $mode = $text;
            } elseif ($mode !== null && $deaths === null && preg_match('/^\d+$/', $text)) {
                $deaths = (int) $text;
            } elseif ($mode === null && $url === null && $p['left'] < self::LINK_COLUMN) {
                // Morceau d'intitulé : collé au précédent s'il le touche (exposant « 8e »).
                $glue = ($title === '' || $p['left'] - $titleEnd > 2) ? ' ' : '';
                $title = trim($title.$glue.$p['text']);
                $titleEnd = $p['left'] + $p['width'];
            }
        }
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        if ($mode === null || $title === '') {
            return null;
        }

        return ['date' => $date, 'title' => $title, 'mode' => $mode, 'deaths' => $deaths, 'url' => $url];
    }
}
