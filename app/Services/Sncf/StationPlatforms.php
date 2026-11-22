<?php

namespace App\Services\Sncf;

use App\Services\Api\Provenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quais des gares de voyageurs, d'après le jeu SNCF Open Data « Liste des quais » (SNCF Réseau, ODbL).
 *
 * Le jeu donne un enregistrement par voie bordée : un quai central entre deux voies (« Quai 20/ 21 » à
 * Paris-Austerlitz) y figure deux fois, avec la même longueur et les mêmes PK. Un quai est donc un groupe
 * d'enregistrements de la même gare, de la même ligne et du même libellé dont les PK se chevauchent (à 50 m
 * près) : sa longueur est la plus grande des longueurs du groupe, ses PK les bornes extrêmes. Deux « Quai »
 * sans numéro aux PK éloignés restent deux quais.
 *
 * Les fonctions statiques sont pures (aucun accès réseau ni base) ; `forStations` lit la table
 * `station_platforms` (vff:import-sncf-platforms) pour l'API REST, GraphQL et MCP.
 */
final class StationPlatforms
{
    public const DATASET = 'liste-des-quais';

    public const LICENSE = 'ODbL 1.0';

    public const ATTRIBUTION = 'SNCF Réseau';

    public const SOURCE = 'sncf';

    /** Écart de PK (m) en deçà duquel deux enregistrements d'un même libellé décrivent le même quai. */
    public const SAME_PLATFORM_GAP_M = 50;

    /** Champs lus dans le jeu (export JSON, sans les tracés). */
    public const FIELDS = ['code_ligne', 'code_voie', 'pkd', 'pkf', 'libelle', 'longueur', 'rg_troncon', 'lib_gare',
        'x_d_wgs84', 'y_d_wgs84', 'x_f_wgs84', 'y_f_wgs84'];

    /** Mention à reproduire avec les données. */
    public const MENTION = 'Quais : SNCF Réseau, jeu « Liste des quais » (SNCF Open Data), licence ODbL 1.0.';

    /** La table existe (migration appliquée), constaté une fois par instance. */
    private ?bool $ready = null;

    public function __construct(private readonly Provenance $provenance) {}

    /** PK SNCF (« 017+250 ») en mètres, null s'il est illisible. */
    public static function pkMetres(?string $pk): ?int
    {
        $km = TrackRegime::pkKm($pk);

        return $km === null ? null : (int) round($km * 1000);
    }

    /** Étendue (en mètres, PK ou coordonnées) d'un quai au plus égale à ce multiple de sa longueur, plus la marge. */
    public const PLAUSIBLE_FACTOR = 3;

    public const PLAUSIBLE_MARGIN_M = 200;

    /**
     * Une étendue (écart des PK, ou distance entre les deux bouts) compatible avec la longueur du quai : le jeu
     * contient des enregistrements dont un bout est faux (PK « 010+299 » pour 425 m, bout à 8 km). Sans longueur
     * connue, rien ne permet de juger : plausible.
     */
    public static function plausibleExtent(?int $lengthM, int $extentM): bool
    {
        return $lengthM === null || $extentM <= self::PLAUSIBLE_FACTOR * $lengthM + self::PLAUSIBLE_MARGIN_M;
    }

    /**
     * Bornes PK extrêmes d'une liste de quais regroupés, null si aucun PK n'est connu.
     *
     * @param  list<array{pk_start_m: ?int, pk_end_m: ?int}>  $platforms
     * @return array{0: int, 1: int}|null
     */
    public static function pkExtent(array $platforms): ?array
    {
        $lo = $hi = null;
        foreach ($platforms as $p) {
            if ($p['pk_start_m'] === null || $p['pk_end_m'] === null) {
                continue;
            }
            $lo = $lo === null ? $p['pk_start_m'] : min($lo, $p['pk_start_m']);
            $hi = $hi === null ? $p['pk_end_m'] : max($hi, $p['pk_end_m']);
        }

        return $lo === null ? null : [$lo, $hi];
    }

    /**
     * Deux groupes de quais d'une même ligne sont-ils au même endroit ? Leurs bornes PK se recoupent à 50 m près, et
     * ni l'un ni l'autre n'est plus long que 3 000 m (un « quai » de 10 km est une erreur de PK, pas un site).
     *
     * @param  array{0: int, 1: int}  $a
     * @param  array{0: int, 1: int}|null  $b
     */
    public static function sameSite(array $a, ?array $b): bool
    {
        return $b !== null && $a[1] - $a[0] <= 3000 && $b[1] - $b[0] <= 3000
            && $a[0] <= $b[1] + self::SAME_PLATFORM_GAP_M && $a[1] >= $b[0] - self::SAME_PLATFORM_GAP_M;
    }

    /** Clé d'un libellé de quai : casse, espaces et barre oblique ignorés (« Quai 20/ 21 » = « quai 20/21 »). */
    public static function labelKey(string $label): string
    {
        return (string) preg_replace('/\s+/', '', mb_strtolower(trim($label)));
    }

    /**
     * Regroupe les enregistrements d'une gare en quais (voir la classe).
     *
     * @param  iterable<array{label: string, code_ligne: string, length_m: ?int, pk_start_m: ?int, pk_end_m: ?int}>  $records
     * @return list<array{label: string, code_ligne: string, length_m: ?int, pk_start_m: ?int, pk_end_m: ?int, records: int}>
     */
    public static function group(iterable $records): array
    {
        $groups = [];
        foreach ($records as $r) {
            $key = $r['code_ligne'].'|'.self::labelKey($r['label']);
            $lo = $r['pk_start_m'] !== null && $r['pk_end_m'] !== null ? min($r['pk_start_m'], $r['pk_end_m']) : null;
            $hi = $r['pk_start_m'] !== null && $r['pk_end_m'] !== null ? max($r['pk_start_m'], $r['pk_end_m']) : null;
            $found = null;
            foreach ($groups[$key] ?? [] as $i => $g) {
                // PK inconnus d'un côté ou de l'autre : même libellé sur la même ligne, même quai.
                if ($lo === null || $g['pk_start_m'] === null
                    || ($lo <= $g['pk_end_m'] + self::SAME_PLATFORM_GAP_M && $hi >= $g['pk_start_m'] - self::SAME_PLATFORM_GAP_M)) {
                    $found = $i;
                    break;
                }
            }
            if ($found === null) {
                $groups[$key][] = ['label' => trim($r['label']), 'code_ligne' => $r['code_ligne'], 'length_m' => $r['length_m'],
                    'pk_start_m' => $lo, 'pk_end_m' => $hi, 'records' => 1];

                continue;
            }
            $g = &$groups[$key][$found];
            $g['length_m'] = $r['length_m'] === null ? $g['length_m'] : max($g['length_m'] ?? 0, $r['length_m']);
            if ($lo !== null) {
                $g['pk_start_m'] = $g['pk_start_m'] === null ? $lo : min($g['pk_start_m'], $lo);
                $g['pk_end_m'] = $g['pk_end_m'] === null ? $hi : max($g['pk_end_m'], $hi);
            }
            $g['records']++;
            unset($g);
        }

        $out = array_merge(...array_values($groups ?: [[]]));
        usort($out, fn (array $a, array $b) => [$a['code_ligne'], $a['pk_start_m'] ?? PHP_INT_MAX, $a['label']]
            <=> [$b['code_ligne'], $b['pk_start_m'] ?? PHP_INT_MAX, $b['label']]);

        return $out;
    }

    /**
     * Résumé des quais d'une gare : nombre, plus long, longueur cumulée.
     *
     * @param  list<array{length_m: ?int}>  $platforms  quais regroupés (group)
     * @return array{count: int, longest_m: ?int, total_length_m: int}
     */
    public static function summary(array $platforms): array
    {
        $lengths = array_filter(array_column($platforms, 'length_m'), fn ($l) => $l !== null);

        return ['count' => count($platforms), 'longest_m' => $lengths === [] ? null : (int) max($lengths),
            'total_length_m' => (int) array_sum($lengths)];
    }

    /**
     * Bloc `platforms` de l'API pour chaque gare demandée (absente du résultat si aucun quai n'est connu).
     *
     * @param  list<int>  $stationIds
     * @return array<int, array{count: int, longest_m: ?int, total_length_m: int, items: list<array{label: string, code_ligne: string, length_m: ?int, pk_start_m: ?int, pk_end_m: ?int}>, dataset_modified: ?string, attribution: string, provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}}>
     */
    public function forStations(array $stationIds): array
    {
        // Table absente tant que la migration n'est pas appliquée : aucune donnée, jamais une erreur 500.
        if ($stationIds === [] || ! ($this->ready ??= Schema::hasTable('station_platforms'))) {
            return [];
        }
        $rows = DB::table('station_platforms')->whereIn('station_id', $stationIds)->where('source', self::SOURCE)
            ->orderBy('id')->get(['station_id', 'label', 'code_ligne', 'length_m', 'pk_start_m', 'pk_end_m', 'dataset_modified']);
        $out = [];
        foreach ($rows->groupBy('station_id') as $sid => $list) {
            $platforms = self::group($list->map(fn ($r) => [
                'label' => (string) $r->label, 'code_ligne' => (string) $r->code_ligne,
                'length_m' => $r->length_m !== null ? (int) $r->length_m : null,
                'pk_start_m' => $r->pk_start_m !== null ? (int) $r->pk_start_m : null,
                'pk_end_m' => $r->pk_end_m !== null ? (int) $r->pk_end_m : null,
            ])->all());
            $out[(int) $sid] = self::summary($platforms) + [
                'items' => array_map(fn (array $p) => array_diff_key($p, ['records' => true]), $platforms),
                'dataset_modified' => $list->max('dataset_modified') !== null ? substr((string) $list->max('dataset_modified'), 0, 10) : null,
                'attribution' => self::MENTION,
                'provenance' => $this->provenance->for('station_platforms', self::SOURCE),
            ];
        }

        return $out;
    }

    /** @return array{count: int, longest_m: ?int, total_length_m: int, items: list<array{label: string, code_ligne: string, length_m: ?int, pk_start_m: ?int, pk_end_m: ?int}>, dataset_modified: ?string, attribution: string, provenance: array{provider: ?string, name: ?string, license: ?string, license_status: ?string}}|null bloc `platforms` d'une gare, null si aucun quai n'est connu */
    public function forStation(int $stationId): ?array
    {
        return $this->forStations([$stationId])[$stationId] ?? null;
    }
}
