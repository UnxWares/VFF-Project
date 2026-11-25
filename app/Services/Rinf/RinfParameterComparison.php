<?php

namespace App\Services\Rinf;

use App\Models\Era;
use App\Models\Line;
use App\Services\LineStations;
use App\Services\Signalling\SignallingRanges;
use App\Services\Speed\LineSpeeds;
use Illuminate\Support\Facades\DB;

/**
 * Paramètres d'une ligne déclarés au RINF (vff:import-rinf : rinf_sections, rinf_tracks, rinf_platforms), par PK,
 * côte à côte avec ce que VFF tient d'une autre source, et l'accord ou le désaccord des deux. Aucune donnée de VFF
 * n'est remplacée : la comparaison est servie telle quelle (REST `GET /api/v1/lines/{line}/rinf-parameters`,
 * GraphQL `Line.rinf_parameters`, MCP `line_rinf_parameters`, rapport vff:compare-rinf-parameters).
 *
 * Paramètres (PARAMETERS) et source confrontée (COMPARED_WITH, données de SNCF Réseau, ODbL) :
 * - `electrification` : ligne de contact, tension et fréquence du RINF ; jeu des lignes électrifiées de SNCF Réseau
 *   (sncf_line_electrification). Une portion d'une ligne que SNCF Réseau décrit, absente de ce jeu, vaut « aucune »
 *   (`none`), comme sur la carte de VFF ;
 * - `max_speed` : vitesse maximale des voies d'une section ; vitesses de SNCF Réseau de l'ère courante. Le RINF
 *   donne une vitesse par voie et par section (entre deux points d'exploitation) : elle est comparée à la plus forte
 *   vitesse de SNCF Réseau sur la section (bords de SPEED_EDGE_M m écartés), règle la plus juste mesurée sur la France
 *   entière (25/11/2026 : 94 % de la longueur comparable d'accord, contre 74 % avec la plus faible) ;
 * - `train_protection` : contrôle de vitesse et protection hérités, niveau ETCS ; signalisation de SNCF Réseau
 *   (line_signalling, ère courante). Seuls les systèmes que les deux savent nommer sont confrontés
 *   (RinfParameters::COMPARED_PROTECTION : KVB, KVBP compté comme KVB, TVM 300, TVM 430, ETCS niveau 1 et 2) ;
 *   le Crocodile, le DAAT, le KCVB ou le KCVP restent servis dans `values` du RINF ;
 * - `radio` : GSM-R ; jeu des lignes équipées de radio sol-train de SNCF Réseau (line_radio_portions) ;
 * - `gauging`, `load_capability`, `track_gauge` : gabarits, catégorie de charge EN 15528 et classe nationale,
 *   écartement : VFF n'en a pas d'autre source (`compared_with` nul, portions `rinf_only`).
 *
 * Les voies d'une section sont réunies (voies principales 1, 2 et unique seules quand la section en a) : plus forte vitesse ; union des systèmes, des gabarits et des catégories ;
 * GSM-R si une voie l'a ; électrification d'accord seulement si toutes les voies disent la même. Statuts : `agree`,
 * `disagree`, `rinf_only` (le RINF seul dit quelque chose), `vff_only` (l'autre source seule). Portions voisines de
 * mêmes valeurs et même statut réunies. Quais : rapprochés gare par gare (même gare de VFF, même nom de quai),
 * longueurs d'accord à PLATFORM_TOLERANCE_M m près. Tables absentes ou colonnes des paramètres pas encore créées :
 * réponse vide, jamais d'erreur.
 */
final class RinfParameterComparison
{
    public const PARAMETERS = ['electrification', 'max_speed', 'train_protection', 'radio', 'gauging', 'load_capability', 'track_gauge'];

    /** @var array<string, ?string> paramètre => fournisseur de la source confrontée */
    public const COMPARED_WITH = [
        'electrification' => 'sncf', 'max_speed' => 'sncf', 'train_protection' => 'sncf', 'radio' => 'sncf',
        'gauging' => null, 'load_capability' => null, 'track_gauge' => null,
    ];

    public const STATUSES = ['agree', 'disagree', 'rinf_only', 'vff_only'];

    /** Écart de longueur de quai toléré (m) pour dire deux longueurs d'accord. */
    public const PLATFORM_TOLERANCE_M = 5;

    /** Bords d'une section écartés (m) pour y chercher la plus forte vitesse de SNCF Réseau. */
    public const SPEED_EDGE_M = 20;

    /** Rattachements des points d'exploitation aux gares de VFF assez sûrs pour rapprocher les quais. */
    private const SURE_MATCHES = ['name_distance', 'name'];

    /** Jeux de SNCF Réseau Open Data confrontés. */
    public const SNCF_DATASETS = 'liste-des-lignes-electrifiees, vitesse-maximale-nominale-sur-ligne, mode-de-cantonnement-des-lignes, '
        .'lignes-equipees-de-kvb, lignes-equipees-de-liaison-radio-sol-train, liste-des-quais';

    public const SNCF_ATTRIBUTION = 'Données SNCF Réseau (SNCF Open Data), sous licence ODbL 1.0 : mention « SNCF Réseau » et partage à '
        .'l\'identique des bases dérivées diffusées.';

    /** Fiche de l'autre source (même forme que RinfDataset::sourceMeta). */
    public const SNCF_SOURCE = ['key' => 'sncf', 'label' => 'SNCF Réseau', 'licence' => 'ODbL 1.0', 'dataset' => self::SNCF_DATASETS, 'fetched_at' => null,
        'url' => 'https://ressources.data.sncf.com/', 'attribution' => self::SNCF_ATTRIBUTION, 'licence_url' => 'https://opendatacommons.org/licenses/odbl/1-0/'];

    /** Valeur de travail : champs servis, plus `_labels` (libellés de la source) et `_compared` (systèmes confrontés). */
    private const VALUE = ['key' => '', 'values' => [], 'voltage_v' => null, 'frequency_hz' => null, 'speed_kmh' => null, 'tracks' => [], '_labels' => []];

    /**
     * Paramètres comparés tronçon par tronçon (rang du tronçon de SNCF Réseau = suffixe de la ligne nationale du RINF,
     * « 890000-2 » : deux tronçons d'une ligne peuvent avoir des PK communs) ; les autres le sont sur toute la ligne.
     */
    private const BY_PART = ['electrification', 'radio', 'gauging', 'load_capability', 'track_gauge'];

    private static ?bool $ready = null;

    public function __construct(private readonly LineSpeeds $speeds, private readonly SignallingRanges $signalling) {}

    /** Tables du RINF et colonnes des paramètres présentes (migrations passées). Un constat négatif est refait. */
    public static function ready(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        $ready = (bool) DB::scalar("SELECT to_regclass('rinf_sections') IS NOT NULL AND to_regclass('rinf_platforms') IS NOT NULL
            AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'rinf_tracks' AND column_name = 'contact_line')");

        if ($ready) {
            self::$ready = true;
        }

        return $ready;
    }

    /**
     * Comparaison complète d'une ligne.
     *
     * @return array{sections: list<array<string, mixed>>, parameters: list<array<string, mixed>>, platforms: array{summary: array<string, int>, items: list<array<string, mixed>>}, sources: list<array<string, mixed>>}
     */
    public function forLine(Line $line): array
    {
        $code = (string) ($line->code ?? '');
        $rows = $code === '' || ! self::ready() ? [] : DB::table('rinf_sections as s')->join('rinf_tracks as t', 't.section_uri', '=', 's.uri')
            ->where('s.line_code', $code)->where('s.nature', 'regular')->whereNotNull('s.pk_start_m')->whereNotNull('s.pk_end_m')
            ->whereColumn('s.pk_start_m', '<>', 's.pk_end_m')->whereRaw("upper(t.ref) <> 'VLINK'")
            ->orderBy('s.national_line')->orderBy('s.pk_start_m')->orderBy('s.uri')->orderBy('t.ref')
            ->get(['s.uri', 's.national_line', 's.pk_start_m', 's.pk_end_m', 's.op_start_uopid', 's.op_end_uopid', 's.dataset', 's.fetched_at', 't.ref',
                't.max_speed_kmh', 't.contact_line', 't.voltage_v', 't.frequency_hz', 't.train_protection', 't.etcs_level', 't.gsm_r',
                't.gsm_r_version', 't.gauging_profiles', 't.load_category', 't.national_load_class', 't.track_gauge_mm'])->all();
        if ($rows === []) {
            return ['sections' => [], 'parameters' => array_map(fn (string $p): array => self::parameter($p, []), self::PARAMETERS),
                'platforms' => ['summary' => self::platformSummary([]), 'items' => []], 'sources' => []];
        }

        $sections = $this->sections($rows);
        // Voies principales (1, 2, unique) seules quand la section en a : les voies de base travaux ou de dépôt que
        // certains gestionnaires déclarent sur toute la section (« Voie 7V de Plibou », LGV SEA) ne comptent pas.
        $withMain = [];
        foreach ($rows as $r) {
            if (RinfParameters::isMainTrack((string) $r->ref)) {
                $withMain[$r->uri] = true;
            }
        }
        $rinf = array_fill_keys(self::PARAMETERS, []);
        foreach ($rows as $r) {
            if (isset($withMain[$r->uri]) && ! RinfParameters::isMainTrack((string) $r->ref)) {
                continue;
            }
            $a = (int) min($r->pk_start_m, $r->pk_end_m);
            $b = (int) max($r->pk_start_m, $r->pk_end_m);
            foreach (self::PARAMETERS as $p) {
                $v = self::rinfValue($p, $r);
                if ($v !== null) {
                    $rinf[$p][] = ['a' => $a, 'b' => $b, 'v' => $v, 'part' => self::part((string) $r->national_line)];
                }
            }
        }

        $eraId = Era::query()->where('is_current', true)->value('id');
        $vff = $this->vffItems($line, $eraId === null ? null : (int) $eraId);
        $describes = $vff['max_speed'] !== [] || $vff['train_protection'] !== [] || $vff['radio'] !== [] || $vff['electrification'] !== [];
        $parameters = [];
        foreach (self::PARAMETERS as $p) {
            $gap = $p === 'electrification' && $describes ? ['key' => 'none'] + self::VALUE : null;
            if (! in_array($p, self::BY_PART, true)) {
                // Vitesses et signalisation : VFF les tient par ligne, tous tronçons confondus.
                $lists = [$rinf[$p], $vff[$p]];
                if ($p === 'max_speed') {
                    $lists = [$rinf[$p], $this->sectionSpeeds($sections, $vff[$p]), $vff[$p]];
                }
                $parameters[] = self::parameter($p, array_map(fn (array $x): array => ['rg_troncon' => null] + $x, $this->compare($p, $lists, $gap)));

                continue;
            }
            $parts = array_values(array_unique([...array_column($rinf[$p], 'part'), ...array_column($vff[$p] ?? [], 'part')]));
            sort($parts);
            $portions = [];
            foreach ($parts as $part) {
                $of = fn (array $items): array => array_values(array_filter($items, fn (array $i): bool => $i['part'] === $part));
                foreach ($this->compare($p, [$of($rinf[$p]), $of($vff[$p] ?? [])], $gap) as $x) {
                    $portions[] = ['rg_troncon' => $part] + $x;
                }
            }
            $parameters[] = self::parameter($p, $portions);
        }

        $datasets = array_values(array_unique(array_map(fn (object $r): string => (string) $r->dataset, $rows)));
        $fetched = min(array_map(fn (object $r): string => (string) $r->fetched_at, $rows));
        $sources = [RinfDataset::sourceMeta(implode(' ; ', $datasets), $fetched === '' ? null : substr($fetched, 0, 10))];
        if ($describes) {
            $sources[] = self::SNCF_SOURCE;
        }

        return ['sections' => array_values(array_map(fn (array $s): array => array_diff_key($s, ['a' => 1, 'b' => 1]), $sections)),
            'parameters' => $parameters, 'platforms' => $this->platforms($line, $sections), 'sources' => $sources];
    }

    /**
     * Sections du RINF de la ligne, dans l'ordre des PK, avec leurs extrémités et leurs voies.
     *
     * @param  list<object>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function sections(array $rows): array
    {
        $names = DB::table('rinf_operational_points')
            ->whereIn('uopid', array_values(array_unique([...array_column($rows, 'op_start_uopid'), ...array_column($rows, 'op_end_uopid')])))
            ->pluck('name', 'uopid')->all();
        $out = [];
        foreach ($rows as $r) {
            $ref = RinfDataset::shortTrackRef((string) $r->ref);
            if (isset($out[$r->uri])) {
                $out[$r->uri]['tracks'] = array_values(array_unique([...$out[$r->uri]['tracks'], $ref]));

                continue;
            }
            $out[$r->uri] = [
                'uri' => (string) $r->uri, 'national_line' => (string) $r->national_line, 'rg_troncon' => self::part((string) $r->national_line),
                'pk_start_m' => (int) $r->pk_start_m, 'pk_end_m' => (int) $r->pk_end_m,
                'pk_start_label' => (string) LineStations::pkLabel((int) $r->pk_start_m), 'pk_end_label' => (string) LineStations::pkLabel((int) $r->pk_end_m),
                'length_m' => abs((int) $r->pk_end_m - (int) $r->pk_start_m),
                'from' => ['uopid' => (string) $r->op_start_uopid, 'name' => $names[$r->op_start_uopid] ?? null],
                'to' => ['uopid' => (string) $r->op_end_uopid, 'name' => $names[$r->op_end_uopid] ?? null],
                'tracks' => [$ref],
                'a' => (int) min($r->pk_start_m, $r->pk_end_m), 'b' => (int) max($r->pk_start_m, $r->pk_end_m),
            ];
        }
        uasort($out, fn (array $x, array $y): int => [$x['rg_troncon'], $x['a'], $x['b']] <=> [$y['rg_troncon'], $y['a'], $y['b']]);

        return $out;
    }

    /** Rang du tronçon d'une ligne nationale du RINF (« 890000-2 » : 2), 1 par défaut. */
    private static function part(string $nationalLine): int
    {
        return preg_match('/-(\d+)$/', $nationalLine, $m) === 1 ? (int) $m[1] : 1;
    }

    /**
     * Valeur d'un paramètre pour une voie du RINF, null quand le RINF ne dit rien.
     *
     * @return array<string, mixed>|null
     */
    private static function rinfValue(string $parameter, object $r): ?array
    {
        $track = ['tracks' => [RinfDataset::shortTrackRef((string) $r->ref)]];
        $list = fn (mixed $json): ?array => $json === null ? null : array_values(array_map('strval', (array) json_decode((string) $json, true)));

        switch ($parameter) {
            case 'electrification':
                if ($r->contact_line === null) {
                    return null;
                }
                $on = $r->contact_line !== 'none';
                $voltage = $r->voltage_v === null ? null : (int) $r->voltage_v;
                $frequency = $r->frequency_hz === null ? null : (float) $r->frequency_hz;

                return ['key' => RinfParameters::electrificationKey($on, $voltage, $frequency), 'values' => [(string) $r->contact_line],
                    'voltage_v' => $on ? $voltage : null, 'frequency_hz' => $on ? $frequency : null] + $track + self::VALUE;
            case 'max_speed':
                return $r->max_speed_kmh === null ? null
                    : ['key' => (string) $r->max_speed_kmh, 'values' => [(string) $r->max_speed_kmh], 'speed_kmh' => (int) $r->max_speed_kmh] + $track + self::VALUE;
            case 'train_protection':
                $protection = $list($r->train_protection);
                if ($protection === null && $r->etcs_level === null) {
                    return null;
                }
                $etcs = match ($r->etcs_level) {
                    '1' => ['ETCS L1'], '2' => ['ETCS L2'], default => [],
                };
                $compared = RinfParameters::comparedFromRinf($protection ?? [], $r->etcs_level);

                return ['key' => $compared === [] ? 'none' : implode('+', $compared), 'values' => RinfParameters::sortProtection([...$etcs, ...($protection ?? [])]),
                    '_compared' => $compared] + $track + self::VALUE;
            case 'radio':
                if ($r->gsm_r === null) {
                    return null;
                }

                return ['key' => $r->gsm_r ? 'gsm-r' : 'none', 'values' => $r->gsm_r_version === null ? [] : [(string) $r->gsm_r_version]] + $track + self::VALUE;
            case 'gauging':
                $profiles = $list($r->gauging_profiles);

                return $profiles === null ? null : ['key' => implode('+', $profiles), 'values' => $profiles] + $track + self::VALUE;
            case 'load_capability':
                if ($r->load_category === null && $r->national_load_class === null) {
                    return null;
                }

                return ['key' => (string) ($r->load_category ?? ''), 'values' => $r->national_load_class === null ? [] : [(string) $r->national_load_class]] + $track + self::VALUE;
            default:
                return $r->track_gauge_mm === null ? null : ['key' => (string) $r->track_gauge_mm, 'values' => []] + $track + self::VALUE;
        }
    }

    /**
     * Portions de l'autre source, par paramètre (ère courante pour les vitesses et la signalisation).
     *
     * @return array<string, list<array{a: int, b: int, v: array<string, mixed>, part?: int}>>
     */
    private function vffItems(Line $line, ?int $eraId): array
    {
        $out = ['electrification' => [], 'max_speed' => [], 'train_protection' => [], 'radio' => []];
        foreach (DB::table('sncf_line_electrification')->where('code_ligne', (string) $line->code)->whereNotNull('pkd_m')->whereNotNull('pkf_m')
            ->orderBy('pkd_m')->get(['rg_troncon', 'pkd_m', 'pkf_m', 'elect', 'voltage_v', 'frequency_hz', 'third_rail']) as $e) {
            $out['electrification'][] = ['a' => (int) min($e->pkd_m, $e->pkf_m), 'b' => (int) max($e->pkd_m, $e->pkf_m), 'part' => (int) ($e->rg_troncon ?? 1), 'v' => [
                'key' => RinfParameters::electrificationKey(true, $e->voltage_v === null ? null : (int) $e->voltage_v, $e->frequency_hz === null ? null : (float) $e->frequency_hz),
                'values' => [$e->third_rail ? 'third_rail' : 'overhead'], 'voltage_v' => $e->voltage_v === null ? null : (int) $e->voltage_v,
                'frequency_hz' => $e->frequency_hz === null ? null : (float) $e->frequency_hz, '_labels' => [(string) $e->elect]] + self::VALUE];
        }
        if ($eraId !== null) {
            foreach ($this->speeds->ranges((int) $line->id, $eraId) as $s) {
                $out['max_speed'][] = ['a' => $s['pk_start_m'], 'b' => $s['pk_end_m'], 'v' => ['key' => (string) $s['max_speed_kmh'],
                    'values' => [(string) $s['max_speed_kmh']], 'speed_kmh' => $s['max_speed_kmh']] + self::VALUE];
            }
            foreach ($this->signalling->forLine((int) $line->id, $eraId) as $s) {
                $compared = RinfParameters::comparedFromVff($s['train_protection'], $s['signalling_systems']);
                $out['train_protection'][] = ['a' => min($s['pk_start_m'], $s['pk_end_m']), 'b' => max($s['pk_start_m'], $s['pk_end_m']), 'v' => [
                    'key' => $compared === [] ? 'none' : implode('+', $compared), '_compared' => $compared,
                    'values' => RinfParameters::sortProtection(array_values(array_unique([...$s['signalling_systems'], ...$s['train_protection']]))),
                    '_labels' => $s['source_label'] === null ? [] : [(string) $s['source_label']]] + self::VALUE];
            }
        }
        foreach (DB::table('line_radio_portions')->where('line_id', $line->id)->whereNotNull('pk_start_m')->whereNotNull('pk_end_m')
            ->orderBy('pk_start_m')->get(['rg_troncon', 'pk_start_m', 'pk_end_m', 'system', 'label']) as $p) {
            $out['radio'][] = ['a' => (int) min($p->pk_start_m, $p->pk_end_m), 'b' => (int) max($p->pk_start_m, $p->pk_end_m), 'part' => (int) ($p->rg_troncon ?? 1), 'v' => [
                'key' => $p->system === 'gsm-r' ? 'gsm-r' : 'none', 'values' => [(string) $p->system], '_labels' => [(string) $p->label]] + self::VALUE];
        }

        return array_map(fn (array $items): array => array_values(array_filter($items, fn (array $i): bool => $i['b'] > $i['a'])), $out);
    }

    /**
     * Plus forte vitesse de SNCF Réseau sur chaque section du RINF (bords écartés), pour la confronter à celle du RINF.
     *
     * @param  array<string, array<string, mixed>>  $sections
     * @param  list<array{a: int, b: int, v: array<string, mixed>}>  $speeds
     * @return list<array{a: int, b: int, v: array<string, mixed>}>
     */
    private function sectionSpeeds(array $sections, array $speeds): array
    {
        $out = [];
        foreach ($sections as $s) {
            $edge = min(self::SPEED_EDGE_M, intdiv($s['b'] - $s['a'], 4));
            $inside = array_column(array_filter($speeds, fn (array $i): bool => $i['a'] < $s['b'] - $edge && $i['b'] > $s['a'] + $edge), 'v');
            $merged = self::merge('max_speed', $inside);
            if ($merged !== null) {
                $out[] = ['a' => $s['a'], 'b' => $s['b'], 'v' => $merged];
            }
        }

        return $out;
    }

    /**
     * Balayage par PK : intervalles élémentaires entre toutes les bornes, valeur réunie de chaque côté, statut,
     * portions voisines identiques réunies. `$lists` : RINF, autre source, puis une liste de repli de l'autre source,
     * servie seulement hors des sections du RINF (vitesses : la plus forte de la section dans la section, la vitesse
     * de SNCF Réseau telle quelle ailleurs).
     *
     * @param  list<list<array{a: int, b: int, v: array<string, mixed>}>>  $lists
     * @param  array<string, mixed>|null  $gap  valeur de l'autre source là où le RINF dit quelque chose et elle rien
     * @return list<array<string, mixed>>
     */
    private function compare(string $parameter, array $lists, ?array $gap): array
    {
        $bounds = [];
        foreach ($lists as $list) {
            foreach ($list as $i) {
                $bounds[$i['a']] = true;
                $bounds[$i['b']] = true;
            }
        }
        $bounds = array_keys($bounds);
        sort($bounds);
        $sorted = array_map(function (array $list): array {
            usort($list, fn (array $x, array $y): int => $x['a'] <=> $y['a']);

            return $list;
        }, $lists);
        $next = array_fill(0, count($lists), 0);
        $active = array_fill(0, count($lists), []);

        $out = [];
        for ($k = 0; $k + 1 < count($bounds); $k++) {
            [$x, $y] = [$bounds[$k], $bounds[$k + 1]];
            $values = [];
            foreach ($sorted as $n => $list) {
                while ($next[$n] < count($list) && $list[$next[$n]]['a'] <= $x) {
                    $active[$n][] = $list[$next[$n]++];
                }
                $active[$n] = array_values(array_filter($active[$n], fn (array $i): bool => $i['b'] > $x));
                $values[$n] = self::merge($parameter, array_column($active[$n], 'v'));
            }
            $rinf = $values[0];
            $vff = $values[1] ?? null;
            if ($vff === null && $rinf === null) {
                $vff = $values[2] ?? null;
            }
            if ($vff === null && $rinf !== null) {
                $vff = $gap;
            }
            if ($rinf === null && $vff === null) {
                continue;
            }
            $status = match (true) {
                $rinf === null => 'vff_only',
                $vff === null => 'rinf_only',
                $rinf['key'] === $vff['key'] => 'agree',
                default => 'disagree',
            };
            $last = array_key_last($out);
            if ($last !== null && $out[$last]['pk_end_m'] === $x && $out[$last]['status'] === $status
                && ($out[$last]['rinf']['key'] ?? null) === ($rinf['key'] ?? null) && ($out[$last]['vff']['key'] ?? null) === ($vff['key'] ?? null)) {
                $out[$last]['pk_end_m'] = $y;
                $out[$last]['rinf'] = $rinf === null ? null : self::merge($parameter, [$out[$last]['rinf'], $rinf]);
                $out[$last]['vff'] = $vff === null ? null : self::merge($parameter, [$out[$last]['vff'], $vff]);

                continue;
            }
            $out[] = ['pk_start_m' => $x, 'pk_end_m' => $y, 'status' => $status, 'rinf' => $rinf, 'vff' => $vff];
        }

        return array_map(fn (array $p): array => [
            'pk_start_m' => $p['pk_start_m'], 'pk_end_m' => $p['pk_end_m'],
            'pk_start_label' => (string) LineStations::pkLabel($p['pk_start_m']), 'pk_end_label' => (string) LineStations::pkLabel($p['pk_end_m']),
            'length_m' => $p['pk_end_m'] - $p['pk_start_m'], 'status' => $p['status'],
            'rinf' => self::shape($p['rinf']), 'vff' => self::shape($p['vff']),
        ], $out);
    }

    /**
     * Valeurs de plusieurs voies ou portions réunies en une (règles en tête de classe), null sans valeur.
     *
     * @param  list<array<string, mixed>>  $values
     * @return array<string, mixed>|null
     */
    private static function merge(string $parameter, array $values): ?array
    {
        if ($values === []) {
            return null;
        }
        if (count($values) === 1) {
            return $values[0];
        }
        $union = fn (string $field): array => array_values(array_unique(array_merge(...array_map(fn (array $v): array => (array) $v[$field], $values))));
        $keys = array_values(array_unique(array_column($values, 'key')));
        $out = ['values' => $union('values'), 'tracks' => $union('tracks'), '_labels' => $union('_labels')] + self::VALUE;

        switch ($parameter) {
            case 'max_speed':
                $speed = max(array_column($values, 'speed_kmh'));
                $speeds = array_map('intval', $out['values']);
                rsort($speeds);

                return ['key' => (string) $speed, 'speed_kmh' => $speed, 'values' => array_map('strval', array_values(array_unique($speeds)))] + $out;
            case 'train_protection':
                $compared = $union('_compared');
                sort($compared);

                return ['key' => $compared === [] ? 'none' : implode('+', $compared), '_compared' => $compared,
                    'values' => RinfParameters::sortProtection($out['values'])] + $out;
            case 'radio':
                return ['key' => in_array('gsm-r', $keys, true) ? 'gsm-r' : 'none'] + $out;
            case 'gauging':
                $profiles = RinfParameters::sortGaugings($out['values']);

                return ['key' => implode('+', $profiles), 'values' => $profiles] + $out;
            case 'load_capability':
                $categories = array_values(array_filter($keys, fn (string $k): bool => $k !== ''));
                sort($categories);

                return ['key' => implode('+', $categories)] + $out;
            default:
                // Électrification, écartement : d'accord seulement si toutes les voies disent la même chose.
                sort($keys);
                $one = count($keys) === 1;

                return ['key' => implode('|', $keys), 'voltage_v' => $one ? $values[0]['voltage_v'] : null,
                    'frequency_hz' => $one ? $values[0]['frequency_hz'] : null] + $out;
        }
    }

    /**
     * Valeur servie : champs fixes, clés de travail retirées.
     *
     * @param  array<string, mixed>|null  $v
     * @return array{key: string, values: list<string>, voltage_v: ?int, frequency_hz: ?float, speed_kmh: ?int, tracks: list<string>, source_label: ?string}|null
     */
    private static function shape(?array $v): ?array
    {
        if ($v === null) {
            return null;
        }
        $tracks = array_values(array_map('strval', (array) $v['tracks']));
        sort($tracks, SORT_NATURAL);

        return [
            'key' => (string) $v['key'], 'values' => array_values(array_map('strval', (array) $v['values'])),
            'voltage_v' => $v['voltage_v'] === null ? null : (int) $v['voltage_v'],
            'frequency_hz' => $v['frequency_hz'] === null ? null : (float) $v['frequency_hz'],
            'speed_kmh' => $v['speed_kmh'] === null ? null : (int) $v['speed_kmh'],
            'tracks' => $tracks, 'source_label' => implode(' ; ', array_map('strval', (array) $v['_labels'])) ?: null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $portions
     * @return array{key: string, compared_with: ?string, summary: array<string, int>, portions: list<array<string, mixed>>}
     */
    private static function parameter(string $key, array $portions): array
    {
        $summary = ['rinf_m' => 0, 'vff_m' => 0, 'agree_m' => 0, 'disagree_m' => 0, 'rinf_only_m' => 0, 'vff_only_m' => 0];
        foreach ($portions as $p) {
            $summary[$p['status'].'_m'] += $p['length_m'];
            $summary['rinf_m'] += $p['rinf'] !== null ? $p['length_m'] : 0;
            $summary['vff_m'] += $p['vff'] !== null ? $p['length_m'] : 0;
        }

        return ['key' => $key, 'compared_with' => self::COMPARED_WITH[$key], 'summary' => $summary, 'portions' => $portions];
    }

    /**
     * Quais des points d'exploitation de la ligne (RINF) et quais de SNCF Réseau de la ligne, rapprochés gare par gare.
     *
     * @param  array<string, array<string, mixed>>  $sections
     * @return array{summary: array<string, int>, items: list<array<string, mixed>>}
     */
    private function platforms(Line $line, array $sections): array
    {
        $opPk = [];
        foreach ($sections as $s) {
            $opPk[$s['from']['uopid']] ??= $s['pk_start_m'];
            $opPk[$s['to']['uopid']] ??= $s['pk_end_m'];
        }
        $rinf = DB::table('rinf_platforms as p')->join('rinf_operational_points as o', 'o.uopid', '=', 'p.op_uopid')
            ->leftJoin('rinf_tracks as t', 't.uri', '=', 'p.track_uri')->leftJoin('stations as st', function ($j): void {
                $j->on('st.id', '=', 'o.station_id')->whereIn('o.station_match', self::SURE_MATCHES);
            })
            ->whereIn('p.op_uopid', array_keys($opPk))->orderBy('p.op_uopid')->orderBy('p.ref')->orderBy('p.uri')
            ->get(['p.ref', 'p.length_m', 'p.height_mm', 'p.op_uopid', 'o.name as op_name', 't.ref as track', 'st.id as station_id', 'st.slug', 'st.name as station_name'])->all();
        $stationIds = array_values(array_unique(array_filter(array_map(fn (object $p): ?int => $p->station_id === null ? null : (int) $p->station_id, $rinf))));
        $sncf = DB::table('station_platforms as sp')->leftJoin('stations as st', 'st.id', '=', 'sp.station_id')
            ->where(fn ($q) => $q->where('sp.code_ligne', (string) $line->code)->orWhereIn('sp.station_id', $stationIds))
            ->orderBy('sp.pk_start_m')->orderBy('sp.id')
            ->get(['sp.id', 'sp.station_id', 'sp.code_ligne', 'sp.label', 'sp.length_m', 'sp.pk_start_m', 'sp.pk_end_m', 'st.slug', 'st.name as station_name'])->all();

        $byKey = [];
        foreach ($sncf as $v) {
            if ($v->station_id !== null) {
                $byKey[$v->station_id.'|'.self::platformKey((string) $v->label)][] = $v;
            }
        }
        $used = [];
        $items = [];
        foreach ($rinf as $r) {
            $match = null;
            $candidates = $r->station_id === null ? [] : ($byKey[$r->station_id.'|'.self::platformKey((string) $r->ref)] ?? []);
            foreach ($candidates as $v) {
                if (! isset($used[$v->id]) && ($match === null || abs((int) $v->length_m - (int) $r->length_m) < abs((int) $match->length_m - (int) $r->length_m))) {
                    $match = $v;
                }
            }
            if ($match !== null) {
                $used[$match->id] = true;
            }
            $status = match (true) {
                $match === null => 'rinf_only',
                $r->length_m === null || $match->length_m === null || abs((int) $r->length_m - (int) $match->length_m) <= self::PLATFORM_TOLERANCE_M => 'agree',
                default => 'disagree',
            };
            $items[] = [
                'pk_m' => $match?->pk_start_m !== null ? (int) $match->pk_start_m : ($opPk[$r->op_uopid] ?? null),
                'station' => $r->slug === null ? null : ['slug' => (string) $r->slug, 'name' => (string) $r->station_name],
                'operational_point' => ['uopid' => (string) $r->op_uopid, 'name' => (string) $r->op_name],
                'ref' => (string) $r->ref, 'track' => $r->track === null ? null : RinfDataset::shortTrackRef((string) $r->track), 'status' => $status,
                'rinf' => ['length_m' => $r->length_m === null ? null : (int) $r->length_m, 'height_mm' => $r->height_mm === null ? null : (int) $r->height_mm],
                'vff' => $match === null ? null : self::sncfPlatform($match),
            ];
        }
        foreach ($sncf as $v) {
            if (! isset($used[$v->id]) && $v->code_ligne === (string) $line->code) {
                $items[] = [
                    'pk_m' => $v->pk_start_m === null ? null : (int) $v->pk_start_m,
                    'station' => $v->slug === null ? null : ['slug' => (string) $v->slug, 'name' => (string) $v->station_name],
                    'operational_point' => null, 'ref' => (string) $v->label, 'track' => null, 'status' => 'vff_only', 'rinf' => null, 'vff' => self::sncfPlatform($v),
                ];
            }
        }
        usort($items, fn (array $x, array $y): int => [$x['pk_m'] ?? PHP_INT_MAX, $x['station']['name'] ?? '', $x['ref']] <=> [$y['pk_m'] ?? PHP_INT_MAX, $y['station']['name'] ?? '', $y['ref']]);

        return ['summary' => self::platformSummary($items), 'items' => $items];
    }

    /** @return array{label: string, length_m: ?int, pk_start_m: ?int, pk_end_m: ?int} */
    private static function sncfPlatform(object $v): array
    {
        return ['label' => (string) $v->label, 'length_m' => $v->length_m === null ? null : (int) $v->length_m,
            'pk_start_m' => $v->pk_start_m === null ? null : (int) $v->pk_start_m, 'pk_end_m' => $v->pk_end_m === null ? null : (int) $v->pk_end_m];
    }

    /** Nom de quai comparable : « Quai  VE » et « quai ve » se valent. */
    public static function platformKey(string $label): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($label)));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{rinf: int, vff: int, agree: int, disagree: int, rinf_only: int, vff_only: int, tolerance_m: int}
     */
    private static function platformSummary(array $items): array
    {
        $count = fn (string $status): int => count(array_filter($items, fn (array $i): bool => $i['status'] === $status));

        return ['rinf' => count(array_filter($items, fn (array $i): bool => $i['rinf'] !== null)), 'vff' => count(array_filter($items, fn (array $i): bool => $i['vff'] !== null)),
            'agree' => $count('agree'), 'disagree' => $count('disagree'), 'rinf_only' => $count('rinf_only'), 'vff_only' => $count('vff_only'),
            'tolerance_m' => self::PLATFORM_TOLERANCE_M];
    }
}
