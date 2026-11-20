<?php

namespace App\Services\Schematic;

/**
 * Côté où se pose le nom de chaque gare du plan schématique : à droite, à gauche, au-dessus,
 * au-dessous ou en coin de son point, là où il recouvre le moins d'axes. MapLibre écarte une
 * étiquette des autres étiquettes, jamais des traits : sans ce choix, le nom d'une gare
 * intermédiaire d'un axe horizontal se posait sur l'axe.
 *
 * La place est mesurée au zoom où l'étiquette apparaît (le plus serré : en zoomant, sa boîte se
 * resserre vers son point pendant que les axes s'écartent) et un zoom et demi plus près ; pour un
 * nœud de ville, aussi aux zooms où il porte le nom de sa gare, plus long. Chaque zoom compte la
 * part du nom posée sur un axe, sur une boîte estimée avec la largeur moyenne des caractères de Noto
 * Sans. Le navigateur essaie les côtés dans cet ordre (les quatre meilleurs, `label_anchors`, puis
 * les autres dans l'ordre habituel) et passe au suivant quand une autre étiquette y est déjà : une
 * étiquette a toujours autant de places possibles qu'avec l'ordre habituel seul.
 */
final class SchematicLabels
{
    /**
     * Ancrages MapLibre dans l'ordre habituel (le côté du texte posé sur le point : `left` met le nom
     * à droite du point), avec leur décalage en em. Mêmes valeurs que lib/schematicMap.js
     * (LABEL_ANCHORS).
     *
     * @var array<string, array{0: float, 1: float}>
     */
    public const ANCHORS = [
        'left' => [0.7, 0.0],
        'right' => [-0.7, 0.0],
        'top' => [0.0, 0.6],
        'bottom' => [0.0, -0.6],
        'top-left' => [0.5, 0.4],
        'top-right' => [-0.5, 0.4],
        'bottom-left' => [0.5, -0.4],
        'bottom-right' => [-0.5, -0.4],
    ];

    /**
     * Zoom auquel chaque niveau d'étiquette (`tier`) apparaît : lib/schematicMap.js (LABEL_MIN_ZOOM),
     * la France entière d'un écran d'ordinateur (zoom 5 environ) pour le niveau 0.
     */
    public const ZOOMS = [5.0, 6.3, 7.6, 9.2];

    /** Zoom à partir duquel un nœud de ville porte le nom de sa gare plutôt que celui de la ville. */
    public const STATION_NAMES_ZOOM = 7.6;

    /** Taille des noms (px) : gras pour les niveaux 0 et 1, comme lib/schematicMap.js. */
    private const SIZE_BOLD = 14.0;

    private const SIZE_REGULAR = 13.0;

    /** Largeur de ligne des noms (em, `text-max-width`) et hauteur d'une ligne (em). */
    private const MAX_WIDTH_EM = 12.0;

    private const LINE_HEIGHT_EM = 1.2;

    /** Mètres Web Mercator par pixel au zoom 0 (tuiles de 512 px). */
    private const METERS_PER_PX_Z0 = 2 * M_PI * Mercator::RADIUS / 512;

    /** Côté des cases de l'index des segments (m). */
    private const BUCKET_M = 10000.0;

    /** @var list<array{0: float, 1: float, 2: float, 3: float}> segments des axes (x1, y1, x2, y2) */
    private array $segments = [];

    /** @var array<int, array<int, list<int>>> segments de chaque case */
    private array $buckets = [];

    /**
     * Côtés de chaque étiquette, du meilleur (le moins d'axe sous le nom) au moins bon.
     *
     * @param  list<array{line: ?int, category: string, coords: list<array{0: float, 1: float}>}>  $pieces  axes du plan (mètres Web Mercator)
     * @param  list<array{x: float, y: float, tier: int, name: string, city: ?string}>  $labels  point, niveau, nom de la gare et, pour un nœud de ville, nom de la ville
     * @return list<list<string>> ancrages (clés de ANCHORS), dans l'ordre de `$labels`
     */
    public function rankings(array $pieces, array $labels): array
    {
        $this->useAxes($pieces);
        $out = [];
        foreach ($labels as $label) {
            $scores = [];
            $rank = 0;
            foreach (array_keys(self::ANCHORS) as $anchor) {
                // Part du nom posée sur un axe, zoom par zoom ; à égalité, l'ordre habituel.
                $score = 0.02 * $rank++;
                foreach (self::zooms($label) as $z) {
                    $score += $this->axisPixels($label, $anchor, $z) / max(1.0, self::width($label, $z));
                }
                $scores[$anchor] = $score;
            }
            asort($scores);
            $out[] = array_keys($scores);
        }

        return $out;
    }

    /**
     * Zooms où le côté d'une étiquette est mesuré : celui où elle apparaît et un zoom et demi plus
     * près ; pour un nœud de ville (nom de la ville d'abord), aussi ceux où il porte le nom de sa gare.
     *
     * @param  array{tier: int, city: ?string}  $label
     * @return list<float>
     */
    private static function zooms(array $label): array
    {
        $zoom = self::ZOOMS[min($label['tier'], count(self::ZOOMS) - 1)];
        $zooms = [$zoom, $zoom + 1.5];
        if ($label['city'] !== null && $zoom < self::STATION_NAMES_ZOOM) {
            array_push($zooms, self::STATION_NAMES_ZOOM, self::STATION_NAMES_ZOOM + 1.5);
        }

        return $zooms;
    }

    /**
     * Texte affiché au zoom `$zoom` : nom de la ville sur un nœud de ville, puis nom de la gare.
     *
     * @param  array{name: string, city: ?string}  $label
     */
    private static function text(array $label, float $zoom): string
    {
        return $label['city'] !== null && $zoom < self::STATION_NAMES_ZOOM ? $label['city'] : $label['name'];
    }

    /**
     * Largeur du nom (px) au zoom `$zoom`.
     *
     * @param  array{tier: int, name: string, city: ?string}  $label
     */
    private static function width(array $label, float $zoom): float
    {
        $size = $label['tier'] < 2 ? self::SIZE_BOLD : self::SIZE_REGULAR;
        [$x0, , $x1] = self::box(self::text($label, $zoom), $size, $label['tier'] < 2, 'left');

        return $x1 - $x0;
    }

    /**
     * Longueur d'axe (en pixels à l'écran) sous le nom posé du côté `$anchor`, au zoom `$zoom`, parmi
     * les axes donnés à `useAxes`.
     *
     * @param  array{x: float, y: float, tier: int, name: string, city: ?string}  $label
     */
    public function axisPixels(array $label, string $anchor, float $zoom): float
    {
        $size = $label['tier'] < 2 ? self::SIZE_BOLD : self::SIZE_REGULAR;
        [$x0, $y0, $x1, $y1] = self::box(self::text($label, $zoom), $size, $label['tier'] < 2, $anchor);
        $mpp = self::METERS_PER_PX_Z0 / 2 ** $zoom;
        // Boîte en mètres : y de l'écran vers le bas, y du plan vers le nord.
        $box = [$label['x'] + $x0 * $mpp, $label['y'] - $y1 * $mpp, $label['x'] + $x1 * $mpp, $label['y'] - $y0 * $mpp];

        return $this->lengthIn($box) / $mpp;
    }

    /**
     * Boîte d'un nom autour de son point, en pixels (y vers le bas), selon l'ancrage.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function box(string $text, float $size, bool $bold, string $anchor): array
    {
        $em = self::textEm($text) * ($bold ? 1.06 : 1.0);
        $lines = max(1, (int) ceil($em / self::MAX_WIDTH_EM));
        $w = min($em, self::MAX_WIDTH_EM) * $size;
        $h = $lines * self::LINE_HEIGHT_EM * $size;
        [$ox, $oy] = self::ANCHORS[$anchor];
        $x0 = match (true) {
            str_ends_with($anchor, 'left') => 0.0,
            str_ends_with($anchor, 'right') => -$w,
            default => -$w / 2,
        };
        $y0 = match (true) {
            str_starts_with($anchor, 'top') => 0.0,
            str_starts_with($anchor, 'bottom') => -$h,
            default => -$h / 2,
        };
        $x0 += $ox * $size;
        $y0 += $oy * $size;

        return [$x0, $y0, $x0 + $w, $y0 + $h];
    }

    /** Largeur d'un texte en em (largeurs moyennes des caractères de Noto Sans). */
    public static function textEm(string $text): float
    {
        $em = 0.0;
        foreach (mb_str_split($text) as $ch) {
            $em += match (true) {
                $ch === ' ' => 0.26,
                str_contains("iljtfr'.,:;!|()-", $ch) => 0.3,
                str_contains('mwMW', $ch) => 0.86,
                mb_strtoupper($ch) === $ch && mb_strtolower($ch) !== $ch => 0.66,
                default => 0.56,
            };
        }

        return $em;
    }

    /**
     * Axes du plan sur lesquels les étiquettes sont mesurées (indexés par cases de BUCKET_M).
     *
     * @param  list<array{line: ?int, category: string, coords: list<array{0: float, 1: float}>}>  $pieces
     */
    public function useAxes(array $pieces): void
    {
        $this->segments = [];
        $this->buckets = [];
        foreach ($pieces as $piece) {
            $n = count($piece['coords']);
            for ($k = 1; $k < $n; $k++) {
                [$ax, $ay] = $piece['coords'][$k - 1];
                [$bx, $by] = $piece['coords'][$k];
                $id = count($this->segments);
                $this->segments[] = [$ax, $ay, $bx, $by];
                for ($i = (int) floor(min($ax, $bx) / self::BUCKET_M); $i <= (int) floor(max($ax, $bx) / self::BUCKET_M); $i++) {
                    for ($j = (int) floor(min($ay, $by) / self::BUCKET_M); $j <= (int) floor(max($ay, $by) / self::BUCKET_M); $j++) {
                        $this->buckets[$i][$j][] = $id;
                    }
                }
            }
        }
    }

    /**
     * Longueur des segments d'axe dans une boîte (mètres).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $box
     */
    private function lengthIn(array $box): float
    {
        [$x0, $y0, $x1, $y1] = $box;
        $seen = [];
        $total = 0.0;
        for ($i = (int) floor($x0 / self::BUCKET_M); $i <= (int) floor($x1 / self::BUCKET_M); $i++) {
            for ($j = (int) floor($y0 / self::BUCKET_M); $j <= (int) floor($y1 / self::BUCKET_M); $j++) {
                foreach ($this->buckets[$i][$j] ?? [] as $id) {
                    if (! isset($seen[$id])) {
                        $seen[$id] = true;
                        $total += self::clip($this->segments[$id], $box);
                    }
                }
            }
        }

        return $total;
    }

    /**
     * Longueur d'un segment dans une boîte (Liang-Barsky).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $segment
     * @param  array{0: float, 1: float, 2: float, 3: float}  $box
     */
    private static function clip(array $segment, array $box): float
    {
        [$ax, $ay, $bx, $by] = $segment;
        [$x0, $y0, $x1, $y1] = $box;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $t0 = 0.0;
        $t1 = 1.0;
        foreach ([[-$dx, $ax - $x0], [$dx, $x1 - $ax], [-$dy, $ay - $y0], [$dy, $y1 - $ay]] as [$p, $q]) {
            if (abs($p) < 1e-12) {
                if ($q < 0) {
                    return 0.0;
                }

                continue;
            }
            $r = $q / $p;
            if ($p < 0) {
                $t0 = max($t0, $r);
            } else {
                $t1 = min($t1, $r);
            }
            if ($t0 >= $t1) {
                return 0.0;
            }
        }

        return ($t1 - $t0) * hypot($dx, $dy);
    }
}
