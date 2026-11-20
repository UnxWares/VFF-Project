<?php

namespace App\Services;

/**
 * Gares des schémas de ligne Wikipédia ({{BS…}}) d'un article, schéma par
 * schéma : nom, PK (s'il est donné) et icône, dans l'ordre du schéma.
 *
 * Complète WikipediaLineParser::parseRouteDiagram() (gares à PK de l'article
 * d'UNE ligne, points de calage du résolveur de portions) pour les articles
 * de réseaux secondaires, qui dessinent plusieurs lignes : chaque schéma est
 * découpé en séries de PK cohérentes (une série s'arrête quand les PK
 * repartent dans l'autre sens : embranchement à origine propre), et les
 * gares sans PK sont gardées (placées ensuite par leur nom).
 *
 * Gare = icône BHF (gare) ou HST (halte), y compris fermée (ex), en
 * construction (u), en bout de ligne (K), etc. ; pas les postes (DST), les
 * bifurcations ni les gares des lignes touristiques dessinées à part (f).
 */
final class RouteDiagramStations
{
    /** Icône de gare ou de halte (mêmes préfixes que WikipediaLineParser::parseRouteDiagram). */
    public const STATION_ICON = '/(?:^|[\s\-])(?:ex|u|ut|t|e|d|l|m|v|h|um|umv|x|ux)*[KT]?(BHF|HST)(?:[a-z@+\-~]|$|\s)/u';

    /** Début d'un schéma. */
    private const TABLE_START = '/\{\{\s*(?:BS-début|BS-table|BS-header|BS-map|BS-begin)\b/iu';

    /**
     * Libellés qui ne sont pas des gares (direction, ouvrage, installation) : le mot seul ou suivi
     * d'une espace ou d'un numéro (« Bifurcation de Beaune », « Bifurcation 2 », « Vers Lyon »),
     * jamais un nom de lieu composé (« Pont-l'Abbé », « Pont-de-Dore », « Poste-Lafayette »).
     */
    public const NOT_A_STATION = '/^(?:vers|ligne|bif\.?|bifurcation|tunnel|viaduc|raccordement|poste|dépôt|atelier|embranchement|ite|carrière|frontière|limite)(?:\s|\d|$)/iu';

    /** Écart de PK en sens inverse au-delà duquel une nouvelle série commence (m). */
    private const RUN_BREAK_M = 500;

    public function __construct(private WikipediaLineParser $text) {}

    /**
     * @return list<array{table: int, run: int, stations: list<array{name: string, pk_m: ?int, icon: string, row: int}>}>
     */
    public function parse(string $wikitext): array
    {
        $wikitext = preg_replace('/<!--.*?-->/su', '', $wikitext);
        $length = self::lineLengthM($wikitext);
        $tables = [];
        $table = 0;
        foreach (preg_split('/\R/u', $wikitext) as $i => $line) {
            $line = trim($line);
            if (preg_match(self::TABLE_START, $line)) {
                $table++;
            }
            if (! preg_match('/^\{\{\s*BS(\d*)(?:e?bis|-?)\s*\|/u', $line, $m)) {
                continue;
            }
            $n = $m[1] === '' ? 1 : (int) $m[1];
            $params = $this->params(substr($line, strlen($m[0])));
            $pos = array_values(array_filter($params, fn ($p) => ! preg_match('/^\s*[A-Za-z]\w*\s*=/u', $p)));
            if (count($pos) < $n + 2) {
                continue;
            }
            $icons = trim(implode(' ', array_slice($pos, 0, $n)));
            if (! preg_match(self::STATION_ICON, $icons)) {
                continue;
            }
            $name = trim(preg_replace('/\s+/u', ' ', $this->text->toPlainText($pos[$n + 1])));
            $name = trim(preg_replace('/\s*\(.*?\)\s*$/u', '', $name), " \t'\"");
            if ($name === '' || mb_strlen($name) > 80 || preg_match(self::NOT_A_STATION, $name)) {
                continue;
            }
            $tables[$table][] = ['name' => $name, 'pk_m' => self::pk($pos[$n], $length), 'icon' => mb_substr($icons, 0, 60), 'row' => $i];
        }

        $out = [];
        foreach ($tables as $t => $rows) {
            foreach (self::runs($rows) as $r => $stations) {
                $out[] = ['table' => $t, 'run' => $r, 'stations' => $stations];
            }
        }

        return $out;
    }

    /**
     * PK d'une cellule de schéma, en mètres : « 12,8 », « 393,21 -00,0 » (PK sur la ligne
     * d'origine puis sur celle-ci : le dernier), « {{BSkm|102,468|0,000}} » (cf. bskmChoice()).
     */
    public static function pk(string $cell, ?int $lineLengthM = null): ?int
    {
        if (preg_match('/\{\{\s*BSkm\s*\|([^}]*)\}\}/u', $cell, $k)) {
            $values = array_values(array_filter(array_map(fn ($v) => self::pk($v), explode('|', $k[1])), fn ($v) => $v !== null));

            return $values === [] ? null : self::bskmChoice($values, $lineLengthM);
        }
        $cell = preg_replace("/'{2,}|<[^>]+>|\{\{[^}]*\}\}/u", ' ', $cell);
        if (! preg_match_all('/(?<![\d,.])(-?\d{1,4})(?:[,.](\d{1,3}))?(?![\d,.])/u', $cell, $mm, PREG_SET_ORDER)) {
            return null;
        }
        $m = end($mm);
        $km = (int) $m[1];
        $frac = isset($m[2]) ? (int) str_pad($m[2], 3, '0') : 0;

        return $km * 1000 + ($km < 0 || str_starts_with($m[1], '-') ? -$frac : $frac);
    }

    /**
     * Valeur retenue d'un « {{BSkm|a|b}} » (deux PK pour une même gare) : d'ordinaire le PK
     * sur la ligne d'origine puis celui sur cette ligne, donc le dernier
     * (« {{BSkm|56,770|0,000}} », Montsecret - Vassy). Mais au terminus d'une ligne qui
     * rejoint une autre ligne, le schéma donne parfois l'inverse : le PK sur cette ligne puis
     * celui sur l'autre (« {{BSkm|19,6xx|17,525}} », Les Maures : 19,6 km, longueur de la
     * ligne de Montsecret - Vassy aux Maures dans l'infobox ; 17,525 est son PK sur la ligne de
     * Vire à Romagny). Une valeur égale à la longueur de la ligne (à 2 % près, 300 m au moins)
     * l'emporte alors sur la dernière.
     *
     * @param  non-empty-list<int>  $values
     */
    public static function bskmChoice(array $values, ?int $lineLengthM): int
    {
        $last = $values[count($values) - 1];
        if ($lineLengthM === null || $lineLengthM <= 0) {
            return $last;
        }
        $tol = max(300, (int) round(0.02 * $lineLengthM));
        if (abs($last - $lineLengthM) <= $tol) {
            return $last;
        }
        foreach ($values as $v) {
            if (abs($v - $lineLengthM) <= $tol) {
                return $v;
            }
        }

        return $last;
    }

    /**
     * Longueur de la ligne donnée par l'infobox (« | longueur = 19.6 », en km), en mètres.
     */
    public static function lineLengthM(string $wikitext): ?int
    {
        if (! preg_match('/\|\s*longueur\s*=\s*(\d{1,4}(?:[.,]\d{1,3})?)\s*(?:km)?\s*(?:\n|\||<|$)/u', $wikitext, $m)) {
            return null;
        }
        $km = (float) str_replace(',', '.', $m[1]);

        return $km > 0 ? (int) round($km * 1000) : null;
    }

    /**
     * Article qui décrit plusieurs lignes du RFN (infobox « numéro = 433000 de Couterne à
     * La Ferté-Macé <br> 434000 de Briouze à La Ferté-Macé », article « Ligne de Briouze à
     * Couterne ») : gares du schéma qui appartiennent à la ligne de code $code, de l'une à
     * l'autre des extrémités que l'infobox donne à ce code (ordre du schéma, extrémités
     * comprises). Liste inchangée si l'infobox ne découpe pas l'article par code ou si une
     * extrémité manque au schéma.
     *
     * @template T of array{name: string}
     *
     * @param  list<T>  $rows  gares du schéma, dans l'ordre (WikipediaLineParser::parseRouteDiagram)
     * @return list<T>
     */
    public static function forCode(array $rows, string $wikitext, ?string $code, WikipediaLineParser $text): array
    {
        if ($code === null || ! preg_match('/\|\s*numéro\s*=([^\n]*(?:\n(?!\s*\|)[^\n]*)*)/u', $wikitext, $m)) {
            return $rows;
        }
        $v = preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?<\/ref>/su', ' ', $m[1]);
        $parts = preg_split('/(?<![\d.])(\d{3})[\s\x{00A0}\x{202F}.]?(\d{3})(?![\d.])/u', $v, -1, PREG_SPLIT_DELIM_CAPTURE);
        $sections = [];
        for ($i = 1; $i + 2 < count($parts); $i += 3) {
            $label = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $parts[$i + 2])));
            $sections[$parts[$i].$parts[$i + 1]] = $label;
        }
        if (count($sections) < 2 || ! isset($sections[$code]) || ! preg_match('/^(?:de |du |des |d[\'’])/u', $sections[$code])) {
            return $rows;
        }
        $ends = array_map(fn ($e) => $text->norm($e), $text->lineEnds('Ligne '.$sections[$code]));
        if (count($ends) !== 2) {
            return $rows;
        }
        $at = function (string $end) use ($rows, $text): ?int {
            foreach ($rows as $i => $r) {
                $n = $text->norm($r['name']);
                if ($n === $end || str_starts_with($n, $end.' ') || str_starts_with($end, $n.' ')) {
                    return $i;
                }
            }

            return null;
        };
        [$a, $b] = [$at($ends[0]), $at($ends[1])];
        if ($a === null || $b === null || $a === $b) {
            return $rows;
        }

        return array_slice($rows, min($a, $b), abs($a - $b) + 1);
    }

    /**
     * Séries de PK cohérentes : une série s'arrête quand les PK repartent dans
     * l'autre sens de plus de RUN_BREAK_M (embranchement à origine propre).
     *
     * @param  list<array{name: string, pk_m: ?int, icon: string, row: int}>  $rows
     * @return list<list<array{name: string, pk_m: ?int, icon: string, row: int}>>
     */
    public static function runs(array $rows): array
    {
        $runs = [[]];
        $last = null;
        $dir = 0;
        foreach ($rows as $row) {
            if ($row['pk_m'] !== null && $last !== null) {
                $d = $row['pk_m'] - $last;
                if ($dir === 0 && abs($d) > self::RUN_BREAK_M) {
                    $dir = $d <=> 0;
                } elseif ($dir !== 0 && $d * $dir < -self::RUN_BREAK_M) {
                    $runs[] = [];
                    $dir = 0;
                }
            }
            $runs[count($runs) - 1][] = $row;
            if ($row['pk_m'] !== null) {
                $last = $row['pk_m'];
            }
        }

        return array_values(array_filter($runs));
    }

    /**
     * Paramètres de premier niveau d'un modèle (après « {{nom| »).
     *
     * @return list<string>
     */
    private function params(string $s): array
    {
        $params = [];
        $cur = '';
        $depthT = 0;
        $depthL = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $two = substr($s, $i, 2);
            if ($two === '{{' || $two === '[[') {
                $two === '{{' ? $depthT++ : $depthL++;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($two === ']]') {
                $depthL--;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($two === '}}') {
                if ($depthT === 0) {
                    $params[] = $cur;

                    return $params;
                }
                $depthT--;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($s[$i] === '|' && $depthT === 0 && $depthL === 0) {
                $params[] = $cur;
                $cur = '';

                continue;
            }
            $cur .= $s[$i];
        }
        $params[] = $cur;

        return $params;
    }
}
