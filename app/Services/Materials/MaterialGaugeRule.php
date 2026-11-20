<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\DB;

/**
 * Règle d'écartement des liens ligne × matériel : une série dont l'écartement est connu
 * (`materials.gauge_mm`) ne se rattache pas à une ligne où cet écartement couvre moins du quart
 * de la longueur (MIN_SHARE), toutes ères confondues (`line_segments.gauge_mm`, longueur des tronçons).
 *
 * Pourquoi le quart, et pas l'écartement dominant seul :
 *   - un réseau métrique a souvent quelques raccordements à voie normale (600000, ligne de Salbris
 *     au Blanc : 60 km à 1 435 mm sur 1 239 km de tronçons, toutes ères, soit 5 %) : cela ne suffit pas
 *     à y faire rouler un autorail à voie normale ;
 *   - une ligne mise à voie normale au cours de son histoire (485000, Guingamp à Carhaix : métrique
 *     jusqu'en 1960, voie normale ensuite, 50 % / 50 %) a bien porté les deux familles de matériel.
 * Une ligne sans écartement connu, ou une série sans écartement, ne sont pas jugées.
 *
 * Sauf source explicite :
 *   - une phrase de preuve qui nomme l'écartement de la série, en français, en anglais ou en allemand
 *     (« sur la section à voie normale », « standard gauge », « Meterspur », « 1 435 mm ») : la source a
 *     vu la différence et l'explique ;
 *   - une exception relue à la main, avec sa source, dans database/data/line_material_gauge_exceptions.php
 *     (ligne par code RFN ou par slug, comme les autres fichiers relus : LineMaterialExclusions::keyed).
 */
final class MaterialGaugeRule
{
    /** Part minimale de la longueur de la ligne à l'écartement de la série. */
    public const MIN_SHARE = 0.25;

    /**
     * Nom de l'écartement en toutes lettres, par largeur (mm), en français, en anglais et en allemand
     * (une source citée par un contributeur peut être dans l'une des trois langues du site).
     */
    private const GAUGE_NAMES = [
        1435 => 'voie\s+normale|écartement\s+(?:standard|normal)|standard[\s-]+gauge|normalspur\w*|regelspur\w*',
        1000 => 'voie\s+métrique|écartement\s+métrique|met(?:re|er)[\s-]+gauge|meterspur\w*',
        600 => 'voie\s+de\s+(?:60|0,60)\b',
        1668 => 'voie\s+ibérique|écartement\s+ibérique|iberian[\s-]+gauge|iberische[nr]?\s+spur\w*',
        1520 => 'voie\s+russe|écartement\s+russe|russian[\s-]+gauge|russische[nr]?\s+spur\w*',
    ];

    /**
     * @param  array<int, array<int, float>>  $profiles  ligne → [écartement (mm) → longueur (m)]
     * @param  array<int, int>  $materialGauges  série → écartement (mm)
     * @param  array<string, string>  $exceptions  « ligne:série » → source de l'exception
     * @param  list<string>  $unresolved  exceptions sans effet dans cette base (LineMaterialExclusions::unresolved)
     */
    public function __construct(
        private readonly array $profiles,
        private readonly array $materialGauges,
        private readonly array $exceptions = [],
        private readonly array $unresolved = [],
    ) {}

    /** @return list<string> exceptions du fichier sans effet dans cette base, à signaler */
    public function unresolvedEntries(): array
    {
        return $this->unresolved;
    }

    /**
     * Règle chargée depuis la base : profils d'écartement des lignes, écartement des séries, exceptions.
     *
     * @param  list<array{line: string, material: string, source: string, reason: string}>|null  $exceptions  null : le fichier de données
     * @param  int|null  $lineId  une seule ligne (contrôle d'une proposition), null : toutes
     * @param  list<int>|null  $lineIds  quelques lignes (liens d'une série à corriger), null : toutes
     */
    public static function fromDatabase(?array $exceptions = null, ?int $lineId = null, ?array $lineIds = null): self
    {
        $profiles = [];
        $rows = DB::table('line_segments')->whereNotNull('gauge_mm')
            ->when($lineId !== null, fn ($q) => $q->where('line_id', $lineId))
            ->when($lineIds !== null, fn ($q) => $q->whereIn('line_id', $lineIds))
            ->groupBy('line_id', 'gauge_mm')
            ->selectRaw('line_id, gauge_mm, SUM(COALESCE(length_meters, ST_Length(geom::geography))) AS meters')
            ->get();
        foreach ($rows as $row) {
            $profiles[(int) $row->line_id][(int) $row->gauge_mm] = (float) $row->meters;
        }
        $gauges = DB::table('materials')->whereNotNull('gauge_mm')->pluck('gauge_mm', 'id')->map(fn ($g): int => (int) $g)->all();

        $exceptions ??= require database_path('data/line_material_gauge_exceptions.php');

        return new self($profiles, $gauges, LineMaterialExclusions::keyed($exceptions),
            LineMaterialExclusions::unresolved($exceptions, 'line_material_gauge_exceptions.php'));
    }

    /**
     * La même règle, avec un autre écartement pour une série (null : écartement inconnu, non jugé) :
     * sert à mesurer ce que changerait la correction d'une série avant de la publier.
     */
    public function withMaterialGauge(int $materialId, ?int $gauge): self
    {
        $gauges = $this->materialGauges;
        unset($gauges[$materialId]);
        if ($gauge !== null) {
            $gauges[$materialId] = $gauge;
        }

        return new self($this->profiles, $gauges, $this->exceptions, $this->unresolved);
    }

    /**
     * Motif du refus, ou null si le lien est compatible (ou non jugé).
     *
     * @param  list<array<string, mixed>>  $evidence  preuves du lien (phrases citées)
     */
    public function conflict(int $lineId, int $materialId, array $evidence = []): ?string
    {
        $details = $this->details($lineId, $materialId, $evidence);
        if ($details === null) {
            return null;
        }
        $mm = fn (int $width): string => number_format($width, 0, ',', ' ').' mm';

        return sprintf('série à %s, ligne surtout à %s (%d %% de sa longueur à %s)', $mm($details['series_mm']), $mm($details['line_mm']), $details['share'], $mm($details['series_mm']));
    }

    /**
     * Le conflit en chiffres, ou null si le lien est compatible (ou non jugé) : écartement de la série,
     * écartement principal de la ligne, part de la ligne à l'écartement de la série (pour cent, arrondi).
     * Sert au motif de conflict() et aux messages traduits du formulaire de contribution.
     *
     * @param  list<array<string, mixed>>  $evidence  preuves du lien (phrases citées)
     * @return array{series_mm: int, line_mm: int, share: int}|null
     */
    public function details(int $lineId, int $materialId, array $evidence = []): ?array
    {
        $gauge = $this->materialGauges[$materialId] ?? null;
        $profile = $this->profiles[$lineId] ?? [];
        $total = array_sum($profile);
        if ($gauge === null || $total <= 0) {
            return null;
        }
        $share = ($profile[$gauge] ?? 0.0) / $total;
        if ($share >= self::MIN_SHARE || isset($this->exceptions["{$lineId}:{$materialId}"])) {
            return null;
        }
        foreach ($evidence as $item) {
            if (is_string($item['quote'] ?? null) && self::namesGauge($item['quote'], $gauge)) {
                return null;
            }
        }
        arsort($profile);

        return ['series_mm' => $gauge, 'line_mm' => (int) array_key_first($profile), 'share' => (int) round($share * 100)];
    }

    /** La phrase nomme-t-elle cet écartement (« voie normale », « standard gauge », « 1 435 mm ») ? */
    public static function namesGauge(string $text, int $gauge): bool
    {
        return self::gaugeMentions($text, $gauge) !== [];
    }

    /**
     * Endroits de la phrase qui nomment cet écartement : position (en octets) et longueur de chaque
     * mention, pour juger les mots qui l'entourent (une négation, par exemple).
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function gaugeMentions(string $text, int $gauge): array
    {
        $digits = (string) $gauge;
        // « 1435 mm », « 1 435 mm », « 1.435 mm », « 1,435 mm » (espace fine ou insécable comprise).
        $millimetres = strlen($digits) === 4 ? $digits[0].'[\s\x{00A0}\x{202F}.,]?'.substr($digits, 1) : $digits;
        $pattern = '(?<!\d)'.$millimetres.'\s*mm\b'.(isset(self::GAUGE_NAMES[$gauge]) ? '|'.self::GAUGE_NAMES[$gauge] : '');
        preg_match_all('/'.$pattern.'/iu', $text, $matches, PREG_OFFSET_CAPTURE);

        return array_map(fn (array $m): array => [(int) $m[1], strlen((string) $m[0])], $matches[0]);
    }

    /**
     * Liens enregistrés qui contredisent la règle (invariant de vff:check-invariants).
     *
     * @return list<array{id: int, line: string, material: string, reason: string}>
     */
    public function storedConflicts(): array
    {
        $out = [];
        $rows = DB::table('line_materials')
            ->join('lines', 'lines.id', '=', 'line_materials.line_id')
            ->join('materials', 'materials.id', '=', 'line_materials.material_id')
            ->whereNotNull('materials.gauge_mm')
            ->orderBy('line_materials.id')
            ->get(['line_materials.id', 'line_materials.line_id', 'line_materials.material_id', 'line_materials.evidence', 'lines.code', 'materials.name']);
        foreach ($rows as $row) {
            $evidence = is_string($row->evidence) ? json_decode($row->evidence, true) : null;
            $reason = $this->conflict((int) $row->line_id, (int) $row->material_id, is_array($evidence) ? $evidence : []);
            if ($reason !== null) {
                $out[] = ['id' => (int) $row->id, 'line' => (string) $row->code, 'material' => (string) $row->name, 'reason' => $reason];
            }
        }

        return $out;
    }
}
