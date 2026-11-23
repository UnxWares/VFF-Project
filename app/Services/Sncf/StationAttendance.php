<?php

namespace App\Services\Sncf;

use App\Services\Api\Provenance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fréquentation annuelle d'une gare d'après SNCF Gares & Connexions (ODbL), table `station_attendances`
 * (vff:import-sncf-attendance). Même contenu pour la fiche de la gare, l'API REST
 * (`GET /api/v1/stations/{station}/attendance`), GraphQL (`StationSheet.attendance`) et le serveur MCP
 * (`station_attendance`).
 *
 * Tant que la migration n'est pas appliquée, tout est vide (aucune erreur).
 */
final class StationAttendance
{
    public const ATTRIBUTION = 'Données SNCF Gares & Connexions (SNCF Open Data, jeux « frequentation-gares » et '
        .'« frequentation-gares-archive »), sous licence ODbL 1.0 : mention « SNCF Gares & Connexions » et partage à '
        .'l\'identique des bases dérivées diffusées.';

    public const SOURCE = 'sncf';

    private const READY_CACHE = 'station-attendance:table-ready';

    private static ?bool $ready = null;

    public function __construct(private readonly Provenance $provenance) {}

    /** La table existe (migration appliquée). Un constat positif est gardé en cache, un négatif est refait. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        if (Cache::get(self::READY_CACHE) === true) {
            return self::$ready = true;
        }
        $ready = (bool) DB::scalar("SELECT to_regclass('station_attendances') IS NOT NULL");
        if ($ready) {
            Cache::put(self::READY_CACHE, true, now()->addDay());
            self::$ready = true;
        }

        return $ready;
    }

    /**
     * Fréquentation d'une gare : toutes les années publiées, de la plus ancienne à la plus récente, ou null quand
     * les jeux n'en donnent aucune (gare fermée avant 2015, absente des jeux, rattachement impossible).
     *
     * @return array<string, mixed>|null
     */
    public function forStation(int $stationId): ?array
    {
        return $this->forStations([$stationId])[$stationId] ?? null;
    }

    /**
     * Blocs de plusieurs gares en une requête (GraphQL). Un bloc :
     *
     * - `uic_code` : code UIC du jeu ;
     * - `years[]` : `year`, `passengers` (voyageurs), `footfall` (voyageurs et non-voyageurs), `non_passengers`
     *   (la différence, nulle si l'un des deux manque), `series` (`2015-2024` ou `2025`) ;
     * - `latest` : la dernière année, avec `published_year` (année de mise en ligne du jeu qui la porte) ;
     * - `series_break` : vrai quand la gare a des années des deux séries, dont les méthodes diffèrent ;
     * - `sources[]` : jeu, série, date de dernière modification ; `attribution` et `provenance`.
     *
     * @param  list<int>  $stationIds
     * @return array<int, array<string, mixed>> seules les gares qui ont des chiffres
     */
    public function forStations(array $stationIds): array
    {
        if ($stationIds === [] || ! self::ready()) {
            return [];
        }
        $byStation = [];
        foreach (DB::table('station_attendances')->whereIn('station_id', $stationIds)->orderBy('station_id')->orderBy('year')
            ->get(['station_id', 'year', 'passengers', 'footfall', 'series', 'dataset', 'dataset_modified', 'uic_code']) as $r) {
            $byStation[(int) $r->station_id][] = $r;
        }

        $out = [];
        foreach ($byStation as $id => $rows) {
            $years = [];
            $sources = [];
            foreach ($rows as $r) {
                $passengers = $r->passengers !== null ? (int) $r->passengers : null;
                $footfall = $r->footfall !== null ? (int) $r->footfall : null;
                $years[] = ['year' => (int) $r->year, 'passengers' => $passengers, 'footfall' => $footfall,
                    'non_passengers' => $passengers !== null && $footfall !== null ? max(0, $footfall - $passengers) : null,
                    'series' => (string) $r->series];
                $sources[$r->series] ??= ['series' => (string) $r->series, 'dataset' => (string) $r->dataset,
                    'dataset_modified' => $r->dataset_modified !== null ? substr((string) $r->dataset_modified, 0, 10) : null];
            }
            $last = end($rows);
            $latest = end($years);
            unset($latest['non_passengers']);
            $latest['published_year'] = $last->dataset_modified !== null ? (int) substr((string) $last->dataset_modified, 0, 4) : null;

            $out[$id] = ['uic_code' => (string) $rows[0]->uic_code, 'years' => $years, 'latest' => $latest,
                'series_break' => count($sources) > 1, 'sources' => array_values($sources),
                'attribution' => self::ATTRIBUTION, 'provenance' => $this->provenance()];
        }

        return $out;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('station_attendances', 'sncf');
    }
}
