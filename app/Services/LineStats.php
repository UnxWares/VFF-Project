<?php

namespace App\Services;

use App\Models\Line;
use App\Support\LineDataSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Chiffres d'une ligne à une ère (en-tête de la fiche ligne, serveur MCP) : longueurs, vitesse
 * maximale, électrification (part et tensions avec leur longueur), signalisation et contrôle de
 * vitesse (depuis line_signalling, SNCF Réseau), écartement dominant, dates de dépose.
 * Lecture seule.
 */
final class LineStats
{
    /** Part électrifiée (%) sous laquelle une ligne n'est électrifiée qu'à la marge (voies d'une gare, raccordement). */
    public const MARGINAL_ELECTRIFIED_PCT = 5;

    /** Durée de vie des longueurs gardées par forEra() (s) : la signature des tronçons les invalide avant. */
    public const CACHE_TTL = 86_400;

    /**
     * Lectures géographiques de forEra() sur les tronçons d'une ligne à une ère, telles que PostgreSQL les
     * renvoie (mêmes requêtes qu'avant leur mise en cache) : totaux, longueur par tension, longueur à la
     * vitesse maximale.
     *
     * @return array{stats: array<string, mixed>, voltages: list<array{v: mixed, m: mixed}>, max_speed_m: mixed}
     */
    private static function segmentFigures(int $lineId, int $eraId): array
    {
        $stats = DB::selectOne(
            <<<'SQL'
            SELECT
                SUM(ST_Length(geom::geography)) AS total_len_m,
                SUM(CASE WHEN status IN ('in_service','single_track') THEN ST_Length(geom::geography) ELSE 0 END) AS active_len_m,
                SUM(CASE WHEN electrified THEN ST_Length(geom::geography) ELSE 0 END) AS elec_len_m,
                MAX(max_speed_kmh) AS max_speed,
                BOOL_OR(electrified) AS electrified,
                MODE() WITHIN GROUP (ORDER BY gauge_mm) AS gauge_mm,
                MIN(dismantled_at) AS dismantled_at_min,
                MAX(dismantled_at) AS dismantled_at_max
            FROM line_segments
            WHERE line_id = ? AND era_id = ?
            SQL,
            [$lineId, $eraId],
        );
        $voltages = array_map(fn ($r): array => ['v' => $r->v, 'm' => $r->m], DB::select(
            'SELECT voltage_v AS v, SUM(ST_Length(geom::geography)) AS m FROM line_segments
             WHERE line_id = ? AND era_id = ? AND electrified AND voltage_v IS NOT NULL
             GROUP BY voltage_v ORDER BY m DESC',
            [$lineId, $eraId],
        ));
        $maxSpeedM = $stats?->max_speed ? DB::selectOne(
            'SELECT SUM(ST_Length(geom::geography)) AS m FROM line_segments WHERE line_id = ? AND era_id = ? AND max_speed_kmh = ?',
            [$lineId, $eraId, $stats->max_speed],
        )->m ?? null : null;

        return ['stats' => (array) $stats, 'voltages' => $voltages, 'max_speed_m' => $maxSpeedM];
    }

    /**
     * @param  list<int>  $segmentIds  tronçons de l'ère
     * @return array<string, mixed>
     */
    public function forEra(Line $line, array $segmentIds, ?int $eraId, int $eventCount = 0): array
    {
        if (! $segmentIds || ! $eraId) {
            return [
                'length_km' => 0.0,
                'length_sncf_km' => null,
                'segment_count' => 0,
                'event_count' => $eventCount,
                'max_speed_kmh' => null,
                'electrified' => false,
            ];
        }

        // Longueurs géodésiques des tronçons de l'ère (60 à 90 ms sur une LGV) : gardées en cache tant que les
        // tronçons de la ligne ne changent pas (LineDataSignature). Les autres lectures restent faites à chaque fois.
        $geo = Cache::remember(
            "line-stats:{$line->id}:{$eraId}:".LineDataSignature::segments((int) $line->id),
            self::CACHE_TTL,
            fn (): array => self::segmentFigures((int) $line->id, $eraId),
        );
        $stats = (object) $geo['stats'];

        // Longueur de LIGNE (route-km) depuis la géométrie officielle SNCF
        // (centerline simple) plutôt que la somme des voies OSM (track-km, ~×2
        // en double voie). Fallback OSM pour les lignes hors référentiel SNCF.
        $sncfKm = $line->code
            ? (float) (DB::selectOne(
                'SELECT SUM(ST_Length(geom::geography)) / 1000.0 AS km FROM sncf_line_geometries WHERE code_ligne = ?',
                [$line->code],
            )->km ?? 0)
            : 0.0;
        $totalLenM = (float) ($stats->total_len_m ?? 0);
        $routeKm = $sncfKm > 0 ? $sncfKm : $totalLenM / 1000.0;

        // Tension dominante (MODE) parmi les segments électrifiés - évite qu'une
        // valeur OSM aberrante isolée (MAX) fausse l'affichage.
        $voltageV = DB::selectOne(
            'SELECT MODE() WITHIN GROUP (ORDER BY voltage_v) AS v
             FROM line_segments WHERE line_id = ? AND era_id = ? AND electrified AND voltage_v IS NOT NULL',
            [$line->id, $eraId],
        )?->v;

        $electrifiedPct = $totalLenM > 0 ? (int) round((float) $stats->elec_len_m / $totalLenM * 100) : 0;

        // Signalisation : agrégation depuis line_signalling (vérité officielle
        // SNCF Réseau Open Data), pas depuis line_segments.signalling qui
        // n'est qu'une vue de l'ancien fallback approximatif. Chaque mode avec la
        // longueur qu'il couvre, du plus étendu au moins étendu (sur une LGV, la TVM
        // avant le BAL des raccordements) ; contrôle de vitesse sans la signalisation
        // en cabine déjà citée (la TVM est les deux : une seule mention). Une tranche à deux
        // systèmes (ETCS niveau 2 et TVM, `signalling_systems`) compte pour chacun des deux.
        $sigAgg = DB::selectOne(
            'SELECT BOOL_OR(kvb_equipped) AS any_kvb FROM line_signalling WHERE line_id = ? AND era_id = ?',
            [$line->id, $eraId],
        );
        $signallingCoverage = array_map(fn ($r) => ['code' => $r->code, 'km' => round((float) $r->m / 1000, 1)], DB::select(
            'SELECT s.code, SUM(GREATEST(ls.pk_end_m - ls.pk_start_m, 0)) AS m
             FROM line_signalling ls
             CROSS JOIN LATERAL jsonb_array_elements_text(CASE WHEN jsonb_array_length(ls.signalling_systems) > 0
                 THEN ls.signalling_systems ELSE jsonb_build_array(ls.signalling) END) AS s(code)
             WHERE ls.line_id = ? AND ls.era_id = ? AND ls.signalling IS NOT NULL
             GROUP BY s.code ORDER BY m DESC, s.code',
            [$line->id, $eraId],
        ));
        $cabCodes = array_values(array_filter(array_column($signallingCoverage, 'code'), fn ($c) => (bool) preg_match('/^(TVM-|ETCS_|ERTMS_)/', (string) $c)));
        $protectionCoverage = array_values(array_filter(array_map(fn ($r) => ['code' => $r->code, 'km' => round((float) $r->m / 1000, 1)], DB::select(
            'SELECT p.code, SUM(GREATEST(ls.pk_end_m - ls.pk_start_m, 0)) AS m
             FROM line_signalling ls, jsonb_array_elements_text(ls.train_protection::jsonb) AS p(code)
             WHERE ls.line_id = ? AND ls.era_id = ? AND ls.train_protection IS NOT NULL
             GROUP BY p.code ORDER BY m DESC, p.code',
            [$line->id, $eraId],
        )), fn ($p) => ! in_array($p['code'], $cabCodes, true)));
        $trainProtection = array_column($protectionCoverage, 'code');

        // Électrification : part du tracé de l'ère et tensions, chacune avec sa longueur (Paris - Brest :
        // 1,5 kV jusqu'au Mans, 25 kV au-delà ; une seule tension « dominante » cachait l'autre).
        // state : none, marginal (moins de MARGINAL_ELECTRIFIED_PCT % : voies d'une gare, raccordement),
        // partial, full (95 % et plus).
        $voltages = array_map(fn ($r) => ['voltage_v' => (int) $r['v'], 'km' => round((float) $r['m'] / 1000, 1)], $geo['voltages']);
        $elecKm = round((float) $stats->elec_len_m / 1000, 1);
        $speedKm = $stats->max_speed ? round((float) ($geo['max_speed_m'] ?? 0) / 1000, 1) : null;

        return [
            'signalling_coverage' => $signallingCoverage,
            'train_protection_coverage' => $protectionCoverage,
            'electrification' => [
                'state' => match (true) {
                    $elecKm <= 0 => 'none',
                    $electrifiedPct < self::MARGINAL_ELECTRIFIED_PCT => 'marginal',
                    $electrifiedPct >= 95 => 'full',
                    default => 'partial',
                },
                'km' => $elecKm,
                'pct' => $electrifiedPct,
                'voltages' => $voltages,
            ],
            // Longueur (km) des tronçons à la vitesse maximale : « 160 km/h » sur 9 km d'une ligne de 145 km.
            'max_speed_km' => $speedKm,
            'length_km' => round($routeKm, 1),                              // remplacée dans show() par la longueur du tracé
            'length_sncf_km' => $sncfKm > 0 ? round($sncfKm, 1) : null,     // formes des lignes SNCF (généralisées)
            'length_track_km' => round($totalLenM / 1000.0, 1),             // track-km (voies OSM cumulées)
            'active_length_km' => round((float) $stats->active_len_m / 1000.0, 1),
            'segment_count' => count($segmentIds),
            'event_count' => $eventCount,
            'max_speed_kmh' => $stats->max_speed,
            'electrified' => $electrifiedPct > 0,
            'electrified_pct' => $electrifiedPct,
            'voltage_v' => $voltageV,
            'gauge_mm' => $stats->gauge_mm,
            'signalling' => $signallingCoverage ? implode(', ', array_column($signallingCoverage, 'code')) : null,
            'train_protection' => $trainProtection ? implode(', ', $trainProtection) : null,
            'kvb_equipped' => (bool) ($sigAgg->any_kvb ?? false),
            'dismantled_at_min' => $stats->dismantled_at_min,
            'dismantled_at_max' => $stats->dismantled_at_max,
        ];
    }
}
