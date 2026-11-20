<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\DB;

/**
 * Règle d'électrification des liens ligne × matériel : une série électrique dont les systèmes
 * d'alimentation sont connus (`materials.electrification`, déclarations Wikidata « 1500 V continu »,
 * « 25 kV 50 Hz »…) ne se rattache pas à une ligne qui n'a jamais été électrifiée dans un système
 * compatible, d'après l'électrification de VFF par tronçon et par ère (`line_segments.electrified`,
 * `voltage_v`, posés par vff:enrich-era-segments).
 *
 * Mesure : pour chaque ère, part de la longueur connue de la ligne sous un courant compatible ; la
 * ligne est jugée sur sa meilleure ère. Une série reste reliée dès qu'une ère atteint MIN_SHARE.
 *   - Tronçon partiel : 785000 (Saint-Germain-des-Fossés à Darsac) n'est électrifiée qu'entre
 *     Saint-Germain et Vichy (8 % de sa longueur) et les BB 26000 des Paris - Clermont y roulent bien.
 *     Le seuil reste donc bas : il écarte une ligne dont seul le bout d'une grande gare est sous
 *     caténaire (Valence - Moirans : 1 500 V sur 2,2 km, 3 % de 79 km).
 *   - Ligne électrifiée tard : seules comptent les ères où la série était en service
 *     (`materials.in_service_from` / `in_service_to`, quand ils sont connus). L'ère Y décrit l'état au
 *     31/12/Y (2030 : aujourd'hui) ; elle est retenue si Y ≥ début de service et si Y - 10 < fin de service.
 *   - Bicourant, tricourant : un seul système compatible suffit.
 *   - Tension compatible : écart d'au plus TOLERANCE (20 %), ce qui réunit 20 et 25 kV 50 Hz, 12 et 15 kV
 *     16,7 Hz, 600 à 850 V continu, et sépare 1 500 V de 3 000 V ou de 25 kV. Un tronçon électrifié de
 *     tension inconnue compte comme compatible.
 *
 * Pas de jugement :
 *   - série bimode ou thermique (type `bimode`, `autorail`, `locomotive_diesel`…), ou série sans système
 *     connu : le type seul ne suffit pas (« automotrice » range aussi l'AGC, le Minuetto, les rames
 *     réversibles), et une rame TGV a pu être remorquée sur une ligne non électrifiée ;
 *   - ligne sans électrification connue pour les ères retenues : les tronçons « none » (aucune source),
 *     « unknown » (électrifiée aujourd'hui, date inconnue) et « status » (voie déposée) ne comptent pas.
 *
 * Sauf source explicite :
 *   - une phrase de preuve sans doute (`doubt`) qui nomme une tension de la série (« 1 500 V »,
 *     « 25 kV », « 750 volts », en anglais « 1,500 V ») : la source a vu la question et y répond ;
 *   - une exception relue à la main, avec sa source, dans database/data/line_material_traction_exceptions.php
 *     (ligne électrifiée dont VFF ne connaît pas encore l'électrification, par exemple).
 */
final class MaterialTractionRule
{
    /** Part minimale de la longueur connue de la ligne, à une ère au moins, sous un courant compatible. */
    public const MIN_SHARE = 0.05;

    /** Écart relatif maximal entre deux tensions jugées compatibles. */
    public const TOLERANCE = 0.2;

    /** Types de matériel qui roulent sans caténaire : jamais jugés. */
    private const NOT_JUDGED_TYPES = ['bimode', 'autorail', 'locomotive_diesel', 'locomotive_vapeur', 'voiture', 'wagon'];

    /** Sources d'électrification qui ne disent rien de l'état à l'ère (voir la migration de line_segments). */
    private const UNKNOWN_SOURCES = ['none', 'unknown', 'status'];

    /** Clé des tronçons électrifiés de tension inconnue dans un profil. */
    private const UNKNOWN_VOLTAGE = 0;

    /**
     * @param  array<int, array<int, array{total: float, volts: array<int, float>}>>  $profiles  ligne → ère (année) → longueur connue et longueur électrifiée par tension (0 : inconnue)
     * @param  array<int, array{volts: list<int>, from: ?int, to: ?int}>  $materials  série → tensions et années de service
     * @param  array<string, string>  $exceptions  « ligne:série » → source de l'exception
     * @param  list<string>  $unresolved  exceptions sans effet dans cette base (LineMaterialExclusions::unresolved)
     */
    public function __construct(
        private readonly array $profiles,
        private readonly array $materials,
        private readonly array $exceptions = [],
        private readonly array $unresolved = [],
    ) {}

    /** @return list<string> exceptions du fichier sans effet dans cette base, à signaler */
    public function unresolvedEntries(): array
    {
        return $this->unresolved;
    }

    /**
     * Règle chargée depuis la base : électrification des lignes par ère, systèmes des séries, exceptions.
     *
     * @param  list<array{line: string, material: string, source: string, reason: string}>|null  $exceptions  null : le fichier de données
     * @param  int|null  $lineId  une seule ligne (contrôle d'une proposition), null : toutes
     * @param  list<int>|null  $lineIds  quelques lignes (liens d'une série à corriger), null : toutes
     */
    public static function fromDatabase(?array $exceptions = null, ?int $lineId = null, ?array $lineIds = null): self
    {
        /** @var array<int, array<int, array{total: float, volts: array<int, float>}>> $profiles */
        $profiles = [];
        $rows = DB::table('line_segments')
            ->join('eras', 'eras.id', '=', 'line_segments.era_id')
            ->when($lineId !== null, fn ($q) => $q->where('line_segments.line_id', $lineId))
            ->when($lineIds !== null, fn ($q) => $q->whereIn('line_segments.line_id', $lineIds))
            ->whereNotNull('line_segments.electrification_source')
            ->whereNotIn('line_segments.electrification_source', self::UNKNOWN_SOURCES)
            ->groupBy('line_segments.line_id', 'eras.year', 'line_segments.electrified', 'line_segments.voltage_v')
            ->selectRaw('line_segments.line_id, eras.year, line_segments.electrified, line_segments.voltage_v, SUM(COALESCE(line_segments.length_meters, ST_Length(line_segments.geom::geography))) AS meters')
            ->get();
        foreach ($rows as $row) {
            [$line, $year, $meters] = [(int) $row->line_id, (int) $row->year, (float) $row->meters];
            $profile = $profiles[$line][$year] ?? ['total' => 0.0, 'volts' => []];
            $profile['total'] += $meters;
            if ((bool) $row->electrified) {
                $volt = $row->voltage_v === null ? self::UNKNOWN_VOLTAGE : (int) $row->voltage_v;
                $profile['volts'][$volt] = ($profile['volts'][$volt] ?? 0.0) + $meters;
            }
            $profiles[$line][$year] = $profile;
        }

        $materials = [];
        $rows = DB::table('materials')->whereNotNull('electrification')->whereNotIn('type', self::NOT_JUDGED_TYPES)
            ->get(['id', 'type', 'electrification', 'in_service_from', 'in_service_to']);
        foreach ($rows as $row) {
            $systems = json_decode((string) $row->electrification, true);
            $entry = self::entry(is_array($systems) ? $systems : null, (string) $row->type,
                $row->in_service_from !== null ? (int) $row->in_service_from : null,
                $row->in_service_to !== null ? (int) $row->in_service_to : null);
            if ($entry !== null) {
                $materials[(int) $row->id] = $entry;
            }
        }

        $exceptions ??= require database_path('data/line_material_traction_exceptions.php');

        return new self($profiles, $materials, LineMaterialExclusions::keyed($exceptions),
            LineMaterialExclusions::unresolved($exceptions, 'line_material_traction_exceptions.php'));
    }

    /**
     * La même règle, avec d'autres valeurs pour une série (systèmes, catégorie, années de service) :
     * sert à mesurer ce que changerait la correction d'une série avant de la publier.
     *
     * @param  array<int, mixed>|null  $systems  systèmes d'électrification (libellés Wikidata)
     */
    public function withMaterial(int $materialId, ?array $systems, ?string $type, ?int $from, ?int $to): self
    {
        $materials = $this->materials;
        unset($materials[$materialId]);
        $entry = self::entry($systems, $type, $from, $to);
        if ($entry !== null) {
            $materials[$materialId] = $entry;
        }

        return new self($this->profiles, $materials, $this->exceptions, $this->unresolved);
    }

    /**
     * Ce que la règle retient d'une série, ou null si elle n'est pas jugée (catégorie qui roule sans
     * caténaire, aucun système de tension connue).
     *
     * @param  array<int, mixed>|null  $systems
     * @return array{volts: list<int>, from: ?int, to: ?int}|null
     */
    private static function entry(?array $systems, ?string $type, ?int $from, ?int $to): ?array
    {
        if ($type === null || in_array($type, self::NOT_JUDGED_TYPES, true)) {
            return null;
        }
        $volts = self::voltages($systems ?? []);

        return $volts !== [] ? ['volts' => $volts, 'from' => $from, 'to' => $to] : null;
    }

    /**
     * Tensions (V) d'une liste de systèmes Wikidata (« électrification ferroviaire 15 kV 16,7 Hz »).
     *
     * @param  array<int, mixed>  $systems
     * @return list<int>
     */
    public static function voltages(array $systems): array
    {
        $volts = [];
        foreach ($systems as $system) {
            $label = is_array($system) ? (string) ($system['label'] ?? '') : '';
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kV|V)\b/u', $label, $m) === 1) {
                $value = (float) str_replace(',', '.', $m[1]);
                $volts[] = (int) round($m[2] === 'kV' ? $value * 1000 : $value);
            }
        }

        return array_values(array_unique($volts));
    }

    /** Deux tensions sont-elles compatibles (écart d'au plus TOLERANCE) ? */
    public static function compatible(int $a, int $b): bool
    {
        return abs($a - $b) <= self::TOLERANCE * max($a, $b);
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
        $systems = implode(', ', array_map(self::format(...), $details['volts']));

        return $details['partial']
            ? sprintf('série à %s, ligne au plus à %d %% de sa longueur sous un courant compatible', $systems, $details['share'])
            : sprintf('série à %s, ligne jamais électrifiée dans un système compatible', $systems);
    }

    /**
     * Le conflit en chiffres, ou null si le lien est compatible (ou non jugé) : tensions de la série (V),
     * meilleure part de la ligne sous un courant compatible (pour cent, arrondi) et `partial`, vrai si une
     * partie de la ligne l'a été (même moins de 1 %). Sert au motif de conflict() et aux messages traduits
     * du formulaire de contribution.
     *
     * @param  list<array<string, mixed>>  $evidence  preuves du lien (phrases citées)
     * @return array{volts: list<int>, share: int, partial: bool}|null
     */
    public function details(int $lineId, int $materialId, array $evidence = []): ?array
    {
        $material = $this->materials[$materialId] ?? null;
        if ($material === null || isset($this->exceptions["{$lineId}:{$materialId}"])) {
            return null;
        }
        $best = null;
        foreach ($this->profiles[$lineId] ?? [] as $year => $era) {
            if ($era['total'] <= 0 || ($material['from'] !== null && $year < $material['from']) || ($material['to'] !== null && $year - 10 >= $material['to'])) {
                continue;
            }
            $compatible = 0.0;
            foreach ($era['volts'] as $volt => $meters) {
                if ($volt === self::UNKNOWN_VOLTAGE || array_filter($material['volts'], fn (int $v): bool => self::compatible($v, $volt)) !== []) {
                    $compatible += $meters;
                }
            }
            $best = max($best ?? 0.0, $compatible / $era['total']);
        }
        if ($best === null || $best >= self::MIN_SHARE) {
            return null;
        }
        foreach ($evidence as $item) {
            if (is_string($item['quote'] ?? null) && ! is_string($item['doubt'] ?? null) && self::namesVoltage($item['quote'], $material['volts'])) {
                return null;
            }
        }

        return ['volts' => $material['volts'], 'share' => (int) round($best * 100), 'partial' => $best > 0];
    }

    /**
     * La phrase nomme-t-elle une de ces tensions (« 1 500 V », « 1500 volts », « 25 kV », « 25 000 V ») ?
     *
     * @param  list<int>  $volts
     */
    public static function namesVoltage(string $text, array $volts): bool
    {
        return self::voltageMentions($text, $volts) !== [];
    }

    /**
     * Endroits de la phrase qui nomment une de ces tensions : position (en octets) et longueur de
     * chaque mention, pour juger les mots qui l'entourent (une négation, par exemple). Lit les
     * écritures française, anglaise et allemande : « 1 500 V », « 1,500 V » et « 1.500 V » (milliers),
     * « 1,5 kV » et « 1.5 kV » (décimales), « 750 volts », « 15 Kilovolt ».
     *
     * @param  list<int>  $volts
     * @return list<array{0: int, 1: int}>
     */
    public static function voltageMentions(string $text, array $volts): array
    {
        $out = [];
        // Milliers (groupes de trois chiffres après un séparateur) d'abord, puis nombre décimal.
        preg_match_all('/(?<![\d,.])(?:(\d{1,3}(?:[\s\x{00A0}\x{202F}.,]\d{3})+)|(\d+)(?:[.,](\d+))?)\s*(kV|kilovolts?|V|volts?)\b/iu', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $m) {
            $kilo = str_starts_with(strtolower((string) $m[4][0]), 'k');
            $value = match (true) {
                // En kilovolts, une virgule ou un point sépare les décimales (« 1,005 kV », « 1.5 kV »).
                $m[1][0] !== null && $kilo => (float) str_replace(',', '.', (string) preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $m[1][0])),
                $m[1][0] !== null => (float) preg_replace('/\D/u', '', $m[1][0]),
                default => (float) ($m[2][0].($m[3][0] !== null ? '.'.$m[3][0] : '')),
            };
            $named = (int) round($kilo ? $value * 1000 : $value);
            foreach ($volts as $volt) {
                if ($named > 0 && self::compatible($volt, $named)) {
                    $out[] = [(int) $m[0][1], strlen((string) $m[0][0])];

                    break;
                }
            }
        }

        return $out;
    }

    /** « 1 500 V », « 25 kV ». */
    private static function format(int $volt): string
    {
        return $volt >= 10_000 ? rtrim(rtrim(number_format($volt / 1000, 1, ',', ' '), '0'), ',').' kV' : number_format($volt, 0, ',', ' ').' V';
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
            ->whereIn('line_materials.material_id', array_keys($this->materials))
            ->orderBy('line_materials.id')
            ->get(['line_materials.id', 'line_materials.line_id', 'line_materials.material_id', 'line_materials.evidence', 'lines.code', 'lines.name AS line_name', 'materials.name']);
        foreach ($rows as $row) {
            $evidence = is_string($row->evidence) ? json_decode($row->evidence, true) : null;
            $reason = $this->conflict((int) $row->line_id, (int) $row->material_id, is_array($evidence) ? $evidence : []);
            if ($reason !== null) {
                $out[] = ['id' => (int) $row->id, 'line' => (string) ($row->code ?? $row->line_name), 'material' => (string) $row->name, 'reason' => $reason];
            }
        }

        return $out;
    }
}
