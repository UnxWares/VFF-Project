<?php

namespace App\Services\Speed;

use App\Services\LineStations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vitesses maximales d'une ligne : tranches de PK de l'ère (`line_speed_ranges`, SNCF Réseau) et
 * changements datés (`line_speed_events`, articles Wikipédia). Même lecture pour la fiche ligne,
 * l'API REST, GraphQL et le serveur MCP.
 *
 * Les tranches SNCF voisines de même vitesse (moins de MERGE_GAP_M d'écart) sont réunies : le jeu découpe
 * une ligne en tronçons de gestion qui ne changent pas toujours la vitesse. Seuls les faits datés sûrs
 * (fiabilité high ou medium) sont rendus.
 */
final class LineSpeeds
{
    /** Écart (m) au-delà duquel deux tranches de même vitesse restent séparées (trou du jeu). */
    public const MERGE_GAP_M = 50;

    public const DATASET = 'vitesse-maximale-nominale-sur-ligne';

    public const DATASET_URL = 'https://ressources.data.sncf.com/explore/dataset/vitesse-maximale-nominale-sur-ligne/';

    /** Tables créées (migration du 13/12/2026 appliquée) : sinon tout est vide, sans erreur. */
    public static function available(): bool
    {
        return Schema::hasTable('line_speed_ranges') && Schema::hasTable('line_speed_events');
    }

    /**
     * @return list<array{pk_start_m: int, pk_end_m: int, pk_start_label: string, pk_end_label: string,
     *     length_m: int, max_speed_kmh: int}>
     */
    public function ranges(int $lineId, int $eraId): array
    {
        if (! self::available()) {
            return [];
        }
        $rows = DB::table('line_speed_ranges')->where('line_id', $lineId)->where('era_id', $eraId)
            ->orderBy('pk_start_m')->orderBy('pk_end_m')->get(['pk_start_m', 'pk_end_m', 'max_speed_kmh'])
            ->map(fn (object $r): array => ['from' => (int) $r->pk_start_m, 'to' => (int) $r->pk_end_m, 'kmh' => (int) $r->max_speed_kmh])
            ->all();

        return array_map(fn (array $r): array => [
            'pk_start_m' => $r['from'],
            'pk_end_m' => $r['to'],
            'pk_start_label' => (string) LineStations::pkLabel($r['from']),
            'pk_end_label' => (string) LineStations::pkLabel($r['to']),
            'length_m' => $r['to'] - $r['from'],
            'max_speed_kmh' => $r['kmh'],
        ], self::merge($rows));
    }

    /**
     * Tranches voisines de même vitesse réunies (entrée triée par PK de début).
     *
     * @param  list<array{from: int, to: int, kmh: int}>  $rows
     * @return list<array{from: int, to: int, kmh: int}>
     */
    public static function merge(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $last = $out === [] ? null : $out[array_key_last($out)];
            if ($last !== null && $last['kmh'] === $r['kmh'] && $r['from'] - $last['to'] <= self::MERGE_GAP_M && $r['to'] >= $last['from']) {
                $out[array_key_last($out)]['to'] = max($last['to'], $r['to']);

                continue;
            }
            $out[] = $r;
        }

        return $out;
    }

    /**
     * Longueur couverte et répartition par vitesse (km), vitesse la plus haute d'abord.
     *
     * @param  list<array{length_m: int, max_speed_kmh: int}>  $ranges
     * @return array{max_speed_kmh: ?int, min_speed_kmh: ?int, covered_m: int, by_speed: list<array{max_speed_kmh: int, length_m: int}>}
     */
    public static function summary(array $ranges): array
    {
        $by = [];
        foreach ($ranges as $r) {
            $by[$r['max_speed_kmh']] = ($by[$r['max_speed_kmh']] ?? 0) + $r['length_m'];
        }
        krsort($by);

        return [
            'max_speed_kmh' => $by === [] ? null : (int) array_key_first($by),
            'min_speed_kmh' => $by === [] ? null : (int) array_key_last($by),
            'covered_m' => array_sum($by),
            'by_speed' => array_map(fn (int $k, int $m): array => ['max_speed_kmh' => $k, 'length_m' => $m], array_keys($by), array_values($by)),
        ];
    }

    /**
     * Changements de vitesse datés et sûrs, du plus ancien au plus récent.
     *
     * @return list<array{kind: string, speed_kmh: int, previous_speed_kmh: ?int, date: string, date_precision: string,
     *     reliability: string, scope: string,
     *     section: array{from: ?string, to: ?string, pk_start_m: ?int, pk_end_m: ?int}|null,
     *     source: array{provider: string, title: string, url: string, revision: int, excerpt: ?string}}>
     */
    public function history(int $lineId, bool $withExcerpt = true): array
    {
        if (! self::available()) {
            return [];
        }

        return DB::table('line_speed_events')->where('line_id', $lineId)->whereIn('confidence', ['high', 'medium'])
            ->orderBy('event_date')->orderBy('id')->get()
            ->map(fn (object $e): array => [
                'kind' => (string) $e->kind,
                'speed_kmh' => (int) $e->speed_kmh,
                'previous_speed_kmh' => $e->previous_speed_kmh === null ? null : (int) $e->previous_speed_kmh,
                'date' => (string) $e->event_date,
                'date_precision' => (string) $e->date_precision,
                'reliability' => (string) $e->confidence,
                'scope' => (string) $e->scope,
                'section' => $e->scope === 'section' ? [
                    'from' => $e->section_from, 'to' => $e->section_to,
                    'pk_start_m' => $e->pk_start_m === null ? null : (int) $e->pk_start_m,
                    'pk_end_m' => $e->pk_end_m === null ? null : (int) $e->pk_end_m,
                ] : null,
                'source' => [
                    'provider' => (string) $e->source, 'title' => (string) $e->source_title, 'url' => (string) $e->source_url,
                    'revision' => (int) $e->source_revision, 'excerpt' => $withExcerpt ? (string) $e->source_excerpt : null,
                ],
            ])->values()->all();
    }

    /**
     * Charge complète d'une ligne à une ère : tranches, résumé, historique, provenance.
     *
     * @return array{era_year: int, ranges: list<array<string, mixed>>, summary: array<string, mixed>,
     *     history: list<array<string, mixed>>, provenance: array<string, mixed>}
     */
    public function forLine(int $lineId, int $eraId, int $eraYear, bool $withExcerpt = true): array
    {
        $ranges = $this->ranges($lineId, $eraId);

        return [
            'era_year' => $eraYear,
            'ranges' => $ranges,
            'summary' => self::summary($ranges),
            'history' => $this->history($lineId, $withExcerpt),
            'provenance' => self::provenance(),
        ];
    }

    /** @return array{ranges: array{provider: string, dataset: string, url: string, license: string, mention: string}, history: array{provider: string, license: string, mention: string}} */
    public static function provenance(): array
    {
        return [
            'ranges' => [
                'provider' => 'SNCF Réseau', 'dataset' => self::DATASET, 'url' => self::DATASET_URL, 'license' => 'ODbL-1.0',
                'mention' => 'SNCF Réseau, Open Data (ODbL)',
            ],
            'history' => [
                'provider' => 'Wikipédia', 'license' => 'CC BY-SA 4.0',
                'mention' => 'Faits relevés dans les articles de Wikipédia (CC BY-SA 4.0), lien vers la révision lue',
            ],
        ];
    }
}
