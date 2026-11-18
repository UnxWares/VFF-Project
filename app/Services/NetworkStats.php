<?php

namespace App\Services;

use App\Models\Era;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

/**
 * Centralise les calculs d'agrégats du réseau ferré (km de route, lignes
 * actives, gares, répartition par type de trafic) jusque-là dispersés dans
 * HomeController et LineController.
 *
 * Toutes les métriques sont scopées sur le réseau RÉEL :
 *   - km de ligne en route-km (centerline officielle SNCF, pas le track-km doublé)
 *   - lignes actives = au moins un segment in_service/single_track à l'ère donnée
 *   - gares = stations non déclassées
 *
 * Service additif et en lecture seule : aucune écriture, aucune dépendance sur
 * la classification (LineClassifier/SegmentStatus). Les requêtes reprennent à
 * l'identique celles préexistantes pour ne PAS changer les valeurs affichées.
 */
class NetworkStats
{
    /**
     * Statuts de segment considérés comme « actifs » (porteurs de trafic).
     *
     * @var list<string>
     */
    public const ACTIVE_STATUSES = ['in_service', 'single_track'];

    /**
     * Longueur du réseau exploité en route-km (centerline SNCF officielle).
     *
     * Reprend telle quelle la requête de HomeController : on somme la longueur
     * géographique des géométries SNCF dont le libellé vaut « Exploitée », en
     * kilomètres. Le paramètre $eraId est accepté pour homogénéité de signature
     * mais n'a pas d'effet ici (les géométries SNCF ne sont pas era-scopées).
     */
    public function totalRouteKm(?int $eraId = null): float
    {
        return (float) (DB::selectOne(
            "SELECT COALESCE(SUM(ST_Length(geom::geography)) / 1000, 0) AS km
             FROM sncf_line_geometries WHERE libelle = 'Exploitée'",
        )?->km ?? 0);
    }

    /**
     * Nombre de lignes distinctes ayant au moins un segment actif
     * (in_service / single_track) à l'ère donnée.
     *
     * @param  int|null  $eraId  Ère ciblée ; null → ère courante (is_current).
     */
    public function activeLinesCount(?int $eraId = null): int
    {
        $eraId ??= $this->currentEraId();

        if ($eraId === null) {
            return 0;
        }

        return (int) DB::selectOne(
            "SELECT COUNT(DISTINCT line_id) AS n FROM line_segments
             WHERE era_id = ? AND status IN ('in_service','single_track')",
            [$eraId],
        )->n;
    }

    /**
     * Nombre de gares hors gares déclassées.
     *
     * Non era-scopé : les gares ne portent pas d'era_id dans le modèle actuel.
     */
    public function stationsCount(): int
    {
        return (int) Station::where('status', '!=', 'declassified')->count();
    }

    /**
     * Répartition du nombre de lignes distinctes par type de trafic, sur les
     * segments actifs de l'ère donnée.
     *
     * Une même ligne peut compter dans plusieurs types si ses segments diffèrent.
     * Les valeurs nulles sont regroupées sous la clé 'unknown'.
     *
     * @param  int|null  $eraId  Ère ciblée ; null → ère courante (is_current).
     * @return array<string, int> ex. ['passenger' => 1200, 'freight' => 300, ...]
     */
    public function byTrafficType(?int $eraId = null): array
    {
        $eraId ??= $this->currentEraId();

        if ($eraId === null) {
            return [];
        }

        $rows = DB::select(
            "SELECT COALESCE(traffic_type, 'unknown') AS traffic_type,
                    COUNT(DISTINCT line_id) AS n
             FROM line_segments
             WHERE era_id = ? AND status IN ('in_service','single_track')
             GROUP BY COALESCE(traffic_type, 'unknown')",
            [$eraId],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->traffic_type] = (int) $row->n;
        }

        return $out;
    }

    /**
     * Nombre d'ères (décennies) ayant au moins un tronçon cartographié.
     *
     * Remplace `Era::count()` sur la home : les ères sont toutes seedées
     * (1900 → 2030) mais tant que le moteur décennal ne les a pas peuplées,
     * annoncer « 14 époques explorables » est trompeur (carte vide).
     */
    public function mappedErasCount(): int
    {
        return (int) (DB::selectOne(
            'SELECT COUNT(*) AS n FROM eras e
             WHERE EXISTS (SELECT 1 FROM line_segments ls WHERE ls.era_id = e.id)',
        )?->n ?? 0);
    }

    /**
     * Empreinte légère des données réseau : change dès qu'un import/moteur
     * écrit des lignes, segments ou gares. Sert de clé de cache aux stats
     * (évite les compteurs figés à 0 après une recréation de la base).
     */
    public function dataFingerprint(): string
    {
        $row = DB::selectOne(
            'SELECT (SELECT COUNT(*) FROM lines) AS l,
                    (SELECT COUNT(*) FROM line_segments) AS s,
                    (SELECT MAX(updated_at) FROM line_segments) AS su,
                    (SELECT COUNT(*) FROM stations) AS st,
                    (SELECT COUNT(*) FROM materials) AS m',
        );

        return md5(json_encode($row));
    }

    /**
     * Id de l'ère courante (is_current = true), ou null si aucune.
     */
    private function currentEraId(): ?int
    {
        return Era::where('is_current', true)->value('id');
    }
}
