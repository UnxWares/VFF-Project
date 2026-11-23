<?php

namespace App\Services;

use App\Models\Station;
use Illuminate\Support\Facades\DB;

/**
 * Fusion de deux fiches de gares qui désignent la même gare (action « merge » de
 * database/data/gares_corrections.php), appelée par vff:consolidate-stations avant la suppression des
 * gares orphelines : ce qui pointe vers la fiche absorbée passe à la fiche gardée, puis l'adresse de la
 * fiche absorbée redirige vers la fiche gardée (table station_redirects : 301 du site, de l'API, de
 * GraphQL et du serveur MCP).
 *
 * Les sources, les noms, les identifiants et la position sont recalculés par la consolidation (les objets
 * sources de la fiche absorbée rejoignent la fiche gardée) ; ici, les données qui ne viennent pas des
 * sources : médias, contributions, signalements, événements lus dans les articles et corrections,
 * rattachements de lignes, accidents, bibliographie, photos, fréquentation, patrimoine, accessibilité.
 * Une ligne déjà présente sur la fiche gardée (même clé unique : même ligne, même année, même image…)
 * l'emporte ; le doublon de la fiche absorbée part avec elle (clé étrangère en cascade), un doublon de
 * média (table polymorphe, sans clé étrangère) est retiré.
 *
 * Les tables calculées à chaque passage (station_eras, line_stops, station_edges, network_nodes) ne sont
 * pas reprises : la chaîne des gares les reconstruit pour la fiche gardée.
 */
final class StationMerger
{
    /**
     * Colonnes reprises : [table, colonne, clé unique qui accompagne la colonne (null : pas de clé unique,
     * [] : une seule ligne par gare), condition supplémentaire (SQL littéral, sans paramètre)].
     *
     * @var list<array{0: string, 1: string, 2: list<string>|null, 3: string}>
     */
    private const REFERENCES = [
        // Événements datés hors sources (articles, qualificatifs Wikidata, corrections) : les événements des
        // sources sont réécrits par la consolidation, les événements déduits par vff:build-station-eras.
        ['station_events', 'station_id', ['type', 'event_date', 'source'], 'is_inferred = FALSE'],
        ['station_lines', 'station_id', ['line_id'], ''],
        ['media', 'mediable_id', ['provider', 'external_id', 'mediable_type'], "mediable_type = 'App\\Models\\Station'"],
        ['contributions', 'target_id', null, "target_type = 'App\\Models\\Station'"],
        ['error_reports', 'subject_id', null, "subject_type = 'App\\Models\\Station'"],
        ['accidents', 'nearest_station_id', null, ''],
        ['accident_lines', 'section_from_station_id', null, ''],
        ['accident_lines', 'section_to_station_id', null, ''],
        ['line_signalling_events', 'from_station_id', null, ''],
        ['line_signalling_events', 'to_station_id', null, ''],
        ['line_material_periods', 'from_station_id', null, ''],
        ['line_material_periods', 'to_station_id', null, ''],
        ['bibliography_links', 'station_id', ['entry_id'], ''],
        ['argentanwebferro_photos', 'station_id', null, ''],
        ['station_heritage', 'station_id', ['ref'], ''],
        ['station_attendances', 'station_id', ['year'], ''],
        ['station_platforms', 'station_id', null, ''],
        ['station_date_candidates', 'station_id', ['kind', 'source', 'source_ref'], ''],
        ['station_accessibility', 'station_id', [], ''],
        ['station_services', 'station_id', [], ''],
        ['station_timetables', 'station_id', [], ''],
        ['station_audits', 'station_id', [], ''],
        ['station_redirects', 'station_id', null, ''],
    ];

    /**
     * Fond la fiche $absorbedId dans la fiche $keptId (même transaction que l'appelant). La fiche absorbée
     * n'est pas supprimée ici : elle l'est ensuite comme gare orpheline (plus aucune source).
     *
     * @return array<string, int> lignes reprises par « table.colonne », doublons de médias retirés (« media doublons »)
     */
    public function merge(int $absorbedId, int $keptId): array
    {
        if ($absorbedId === $keptId) {
            return [];
        }
        $slug = DB::table('stations')->where('id', $absorbedId)->value('slug');
        $moved = [];
        foreach (self::REFERENCES as [$table, $column, $unique, $where]) {
            $moved["{$table}.{$column}"] = $this->repoint($table, $column, $unique, $where, $absorbedId, $keptId);
        }
        // Image déjà sur la fiche gardée : le doublon de la fiche absorbée n'a plus de fiche.
        $moved['media doublons'] = DB::table('media')->where('mediable_type', Station::class)->where('mediable_id', $absorbedId)->delete();
        if (is_string($slug)) {
            DB::table('station_redirects')->upsert(
                [['old_slug' => $slug, 'station_id' => $keptId, 'created_at' => now(), 'updated_at' => now()]],
                ['old_slug'], ['station_id', 'updated_at'],
            );
        }

        return $moved;
    }

    /** @param  list<string>|null  $unique */
    private function repoint(string $table, string $column, ?array $unique, string $where, int $from, int $to): int
    {
        $and = $where === '' ? '' : " AND {$where}";
        $conflict = '';
        if ($unique !== null) {
            $same = implode('', array_map(fn (string $c) => " AND y.{$c} IS NOT DISTINCT FROM x.{$c}", $unique));
            // Toute ligne de même clé unique sur la fiche gardée bloque (la condition $where n'y est pas reprise :
            // l'index unique porte sur toutes les lignes de la table).
            $conflict = " AND NOT EXISTS (SELECT 1 FROM {$table} y WHERE y.{$column} = ?{$same})";
        }
        $bindings = $unique === null ? [$to, $from] : [$to, $from, $to];

        return DB::update("UPDATE {$table} x SET {$column} = ? WHERE x.{$column} = ?{$and}{$conflict}", $bindings);
    }
}
