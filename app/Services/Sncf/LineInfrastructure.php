<?php

namespace App\Services\Sncf;

use App\Services\Api\Provenance;
use App\Services\LineStations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Infrastructure actuelle d'une ligne d'après SNCF Réseau (ODbL) : nombre de voies par portion de PK
 * (`line_track_portions`, vff:import-sncf-track-regime) et liaison radio sol-train (`line_radio_portions`,
 * vff:import-sncf-radio). Même contenu pour l'API REST (`GET /api/v1/lines/{line}/infrastructure`), GraphQL
 * (`Line.infrastructure`), le serveur MCP (`line_infrastructure`) et le fait « Nombre de voies » de la fiche.
 *
 * Tant que la migration des deux tables n'est pas appliquée, tout est vide (aucune erreur).
 */
final class LineInfrastructure
{
    public const ATTRIBUTION = 'Données SNCF Réseau (SNCF Open Data, jeux « regime-dexploitation-des-lignes » et '
        .'« lignes-equipees-de-liaison-radio-sol-train »), sous licence ODbL 1.0 : mention « SNCF Réseau » et partage à '
        .'l\'identique des bases dérivées diffusées.';

    /** Clé du cache qui retient que les tables existent (une fois créées, elles restent). */
    private const READY_CACHE = 'sncf-infrastructure:tables-ready';

    private static ?bool $ready = null;

    public function __construct(private readonly Provenance $provenance) {}

    /**
     * Les deux tables existent (migration appliquée). Un constat positif est gardé en cache (aucune requête aux
     * visites suivantes) ; un constat négatif est refait à chaque requête, pour voir la migration dès qu'elle passe.
     */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        if (Cache::get(self::READY_CACHE) === true) {
            return self::$ready = true;
        }
        $ready = (bool) DB::scalar("SELECT to_regclass('line_track_portions') IS NOT NULL AND to_regclass('line_radio_portions') IS NOT NULL");
        if ($ready) {
            Cache::put(self::READY_CACHE, true, now()->addDay());
        }

        return self::$ready = $ready;
    }

    /**
     * Bloc complet d'une ligne.
     *
     * @return array{tracks: array<string, mixed>, radio: array<string, mixed>}
     */
    public function forLine(int $lineId): array
    {
        return $this->forLines([$lineId])[$lineId];
    }

    /**
     * Blocs de plusieurs lignes en deux requêtes (GraphQL).
     *
     * @param  list<int>  $lineIds
     * @return array<int, array{tracks: array<string, mixed>, radio: array<string, mixed>}>
     */
    public function forLines(array $lineIds, bool $withRadio = true): array
    {
        $tracks = array_fill_keys($lineIds, []);
        $radio = array_fill_keys($lineIds, []);
        if (self::ready() && $lineIds !== []) {
            foreach (DB::table('line_track_portions')->whereIn('line_id', $lineIds)->orderBy('line_id')->orderBy('pk_start_m')->orderBy('rg_troncon')
                ->get(['line_id', 'pk_start_m', 'pk_end_m', 'regime', 'tracks', 'dataset_modified']) as $p) {
                $tracks[(int) $p->line_id][] = $p;
            }
            foreach (! $withRadio ? [] : DB::table('line_radio_portions')->whereIn('line_id', $lineIds)->orderBy('line_id')->orderBy('pk_start_m')->orderBy('rg_troncon')
                ->get(['line_id', 'pk_start_m', 'pk_end_m', 'system', 'label', 'dataset_modified']) as $p) {
                $radio[(int) $p->line_id][] = $p;
            }
        }

        $out = [];
        foreach ($lineIds as $id) {
            $out[$id] = ['tracks' => $this->tracksBlock($tracks[$id]), 'radio' => $this->radioBlock($radio[$id])];
        }

        return $out;
    }

    /**
     * Fait « Nombre de voies » de la fiche (LineFacts) d'après le régime SNCF : nombre dominant en longueur,
     * avec la longueur concernée quand le régime est partagé. Null sans portion au nombre établi, ou quand la
     * fiche porte un autre nombre venu d'une autre source (il reste affiché, avec sa source).
     *
     * @return array<string, mixed>|null
     */
    public function trackFact(int $lineId, ?int $known): ?array
    {
        // Une seule lecture (voies) : le fait est calculé à chaque visite de la fiche.
        $summary = $this->forLines([$lineId], withRadio: false)[$lineId]['tracks']['summary'];
        if ($summary === null || ($known !== null && $known !== $summary['tracks'])) {
            return null;
        }

        return ['key' => 'tracks', 'n' => $summary['tracks'], 'source' => 'sncf', 'scope' => 'line', 'item' => null, 'as_of' => 'today',
            'km' => $summary['uniform'] ? null : round($summary['dominant_length_m'] / 1000, 1)];
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('line_track_portions', 'sncf');
    }

    /**
     * @param  list<object>  $rows
     * @return array{summary: ?array<string, mixed>, portions: list<array<string, mixed>>, dataset_modified: ?string}
     */
    private function tracksBlock(array $rows): array
    {
        $byTracks = [];
        $banalised = 0;
        $portions = [];
        foreach ($rows as $r) {
            $length = self::length($r);
            $tracks = $r->tracks !== null ? (int) $r->tracks : null;
            if ($tracks !== null) {
                $byTracks[$tracks] = ($byTracks[$tracks] ?? 0) + $length;
            } elseif (TrackRegime::isBanalised($r->regime)) {
                $banalised += $length;
            }
            $portions[] = self::pk($r) + ['tracks' => $tracks, 'regime' => (string) $r->regime, 'banalised' => TrackRegime::isBanalised($r->regime)];
        }
        $summary = null;
        if ($byTracks !== []) {
            arsort($byTracks);
            $dominant = (int) array_key_first($byTracks);
            $total = array_sum($byTracks);
            $share = $total > 0 ? $byTracks[$dominant] / $total : 1.0;
            ksort($byTracks);
            $summary = [
                'tracks' => $dominant,
                'uniform' => $share >= TrackRegime::UNIFORM_SHARE,
                'dominant_share' => round($share, 3),
                'dominant_length_m' => $byTracks[$dominant],
                'length_m_by_tracks' => array_map(fn ($t, $m) => ['tracks' => $t, 'length_m' => $m], array_keys($byTracks), $byTracks),
                'banalised_length_m' => $banalised,
            ];
        }

        return ['summary' => $summary, 'portions' => $portions, 'dataset_modified' => self::modified($rows)];
    }

    /**
     * @param  list<object>  $rows
     * @return array{systems: list<array{system: string, length_m: int}>, gsm_r: bool, gsm_r_length_m: int, portions: list<array<string, mixed>>, dataset_modified: ?string}
     */
    private function radioBlock(array $rows): array
    {
        $bySystem = [];
        $portions = [];
        foreach ($rows as $r) {
            $bySystem[$r->system] = ($bySystem[$r->system] ?? 0) + self::length($r);
            $portions[] = self::pk($r) + ['system' => (string) $r->system, 'label' => (string) $r->label];
        }
        $systems = [];
        foreach (RadioSystems::SYSTEMS as $s) {
            if (isset($bySystem[$s])) {
                $systems[] = ['system' => $s, 'length_m' => $bySystem[$s]];
            }
        }

        return ['systems' => $systems, 'gsm_r' => isset($bySystem['gsm-r']), 'gsm_r_length_m' => $bySystem['gsm-r'] ?? 0,
            'portions' => $portions, 'dataset_modified' => self::modified($rows)];
    }

    /** @return array{pk_start_m: ?int, pk_end_m: ?int, pk_start_label: ?string, pk_end_label: ?string, length_m: ?int} */
    private static function pk(object $r): array
    {
        $from = $r->pk_start_m !== null ? (int) $r->pk_start_m : null;
        $to = $r->pk_end_m !== null ? (int) $r->pk_end_m : null;

        return ['pk_start_m' => $from, 'pk_end_m' => $to, 'pk_start_label' => LineStations::pkLabel($from),
            'pk_end_label' => LineStations::pkLabel($to), 'length_m' => $from !== null && $to !== null ? abs($to - $from) : null];
    }

    /** Longueur d'une portion (m) ; un PK illisible pèse 1 km, comme à l'import (TrackRegime::summarize). */
    private static function length(object $r): int
    {
        return $r->pk_start_m !== null && $r->pk_end_m !== null ? abs((int) $r->pk_end_m - (int) $r->pk_start_m) : 1000;
    }

    /** @param  list<object>  $rows */
    private static function modified(array $rows): ?string
    {
        $dates = array_filter(array_map(fn ($r) => $r->dataset_modified !== null ? substr((string) $r->dataset_modified, 0, 10) : null, $rows));

        return $dates === [] ? null : max($dates);
    }
}
