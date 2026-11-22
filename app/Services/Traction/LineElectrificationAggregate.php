<?php

namespace App\Services\Traction;

use App\Services\LineStats;
use Illuminate\Support\Facades\DB;

/**
 * Drapeau `lines.electrified` (et tension `lines.voltage_v`) déduit des tronçons de l'ère courante.
 *
 * Règle : une ligne est électrifiée si les tronçons EN SERVICE électrifiés couvrent au moins
 * LineStats::MARGINAL_ELECTRIFIED_PCT % de sa longueur en service. En deçà (voies d'une gare,
 * entrée de gare, raccordement : 609 m sur les 125 km de Limoges - Angoulême, 279 m sur les 117 km
 * d'Andelot - La Cluse), la ligne n'est pas électrifiée : c'est le même seuil que l'état « marginal »
 * de la fiche (LineStats).
 *
 * Avant cette règle, un seul tronçon électrifié, même de quelques mètres, suffisait
 * (BOOL_OR du classement SNCF) et le drapeau ne repassait jamais à faux (`electrified OR ?`).
 *
 * Le drapeau n'est décidé que si les tronçons de l'ère courante sont renseignés (source SNCF, OSM,
 * événement ou curation : pas « none », « unknown », « status » ni « rule ») ET si la ligne a de la
 * longueur en service. Sinon (ligne fermée, tronçons muets), la valeur existante (Wikidata, saisie)
 * est conservée : tronçons muets ≠ « non électrifiée ».
 */
final class LineElectrificationAggregate
{
    /**
     * Décision pure : vrai / faux, ou null pour conserver la valeur actuelle.
     */
    public static function decide(float $activeM, float $electrifiedActiveM, bool $informed): ?bool
    {
        if (! $informed || $activeM <= 0.0) {
            return null;
        }

        return $electrifiedActiveM / $activeM * 100 >= LineStats::MARGINAL_ELECTRIFIED_PCT;
    }

    /**
     * Recalcule le drapeau de toutes les lignes ayant des tronçons à l'ère donnée.
     *
     * @return array{lines: int, to_true: list<string>, to_false: list<string>, unchanged: int, voltage_changed: int}
     */
    public function apply(int $eraId, bool $write = true): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT l.id, l.code, l.name, l.electrified, l.voltage_v,
                   COALESCE(SUM(CASE WHEN ls.status IN ('in_service','single_track') THEN ST_Length(ls.geom::geography) END), 0) AS active_m,
                   COALESCE(SUM(CASE WHEN ls.electrified AND ls.status IN ('in_service','single_track') THEN ST_Length(ls.geom::geography) END), 0) AS elec_m,
                   COALESCE(BOOL_OR(ls.electrification_source IS NOT NULL
                       AND ls.electrification_source NOT IN ('none','unknown','status','rule')), false) AS informed,
                   MODE() WITHIN GROUP (ORDER BY ls.voltage_v)
                       FILTER (WHERE ls.electrified AND ls.voltage_v IS NOT NULL AND ls.status IN ('in_service','single_track')) AS volt
            FROM lines l
            JOIN line_segments ls ON ls.line_id = l.id AND ls.era_id = ?
            GROUP BY l.id, l.code, l.name, l.electrified, l.voltage_v
            SQL,
            [$eraId],
        );

        $result = ['lines' => count($rows), 'to_true' => [], 'to_false' => [], 'unchanged' => 0, 'voltage_changed' => 0];
        foreach ($rows as $r) {
            $decision = self::decide((float) $r->active_m, (float) $r->elec_m, (bool) $r->informed);
            if ($decision === null) {
                $result['unchanged']++;

                continue;
            }
            $voltage = $decision ? ($r->volt !== null ? (int) $r->volt : $r->voltage_v) : null;
            $label = trim(($r->code ?? '').' '.$r->name);
            if ($decision !== (bool) $r->electrified) {
                $result[$decision ? 'to_true' : 'to_false'][] = $label;
            } elseif ($voltage === $r->voltage_v) {
                $result['unchanged']++;

                continue;
            } else {
                $result['voltage_changed']++;
            }
            if ($write) {
                DB::update('UPDATE lines SET electrified = ?, voltage_v = ?, updated_at = NOW() WHERE id = ?', [$decision, $voltage, $r->id]);
            }
        }

        return $result;
    }
}
