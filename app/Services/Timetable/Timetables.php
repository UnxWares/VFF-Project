<?php

namespace App\Services\Timetable;

use App\Services\Api\Provenance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Desserte d'un jour ouvrable type, lue dans station_timetables et line_timetables (vff:import-station-timetables) :
 * même contenu pour la fiche de la gare, l'API REST (`GET /api/v1/stations/{station}/timetable`), GraphQL
 * (`Station.timetable`, `LineSheet.timetable`) et le serveur MCP (`station_timetable`, `line_timetable`).
 *
 * Tant que la migration n'est pas appliquée (ou si la lecture échoue), tout est vide : aucune erreur, la fiche s'affiche.
 */
final class Timetables
{
    public const SOURCE = 'sncf';

    public const ATTRIBUTION = 'Horaires théoriques ouverts de SNCF Voyageurs (TER, Intercités, TGV INOUI, OUIGO), de Transilien et, pour '
        .'quelques gares du Sud, des réseaux régionaux ZOU !, publiés sur transport.data.gouv.fr et SNCF Open Data sous licence ODbL 1.0 : '
        .'mention de la source et partage à l\'identique des bases dérivées diffusées.';

    public const METHOD = 'Un mardi type : le mardi, d\'une semaine sans jour férié, dont le nombre de trains est la médiane des mardis '
        .'proches que couvrent tous les horaires lus. Trains de voyageurs seulement (les cars sont écartés), comptés une fois par gare, '
        .'d\'après les horaires théoriques : les suppressions, travaux et retards du jour ne sont pas connus.';

    private const READY_CACHE = 'timetables:tables-ready';

    private static ?bool $ready = null;

    public function __construct(private readonly Provenance $provenance) {}

    /** Les tables existent (migration appliquée). Un constat positif est gardé en cache, un négatif est refait. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            if (Cache::get(self::READY_CACHE) === true) {
                return self::$ready = true;
            }
            $ready = (bool) DB::scalar("SELECT to_regclass('station_timetables') IS NOT NULL AND to_regclass('line_timetables') IS NOT NULL");
            if ($ready) {
                Cache::put(self::READY_CACHE, true, now()->addDay());
                self::$ready = true;
            }

            return $ready;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forStation(int $stationId): ?array
    {
        return $this->forStations([$stationId])[$stationId] ?? null;
    }

    /**
     * Blocs de plusieurs gares en une requête (GraphQL). Un bloc :
     *
     * - `service_date` (`AAAA-MM-JJ`) et `weekday` (`tuesday`) : le jour type ;
     * - `trains` : trains qui s'arrêtent dans la gare ce jour-là, comptés une fois ; `by_type` : `ter`, `intercites`, `tgv`,
     *   `transilien`, `other` ; `first` et `last` : premières et dernières heures (`HH:MM`) ;
     * - `directions[]` : les deux sens (axe principal des trains de la gare) : `heading` (`n`, `ne`, `e`, `se`, `s`, `sw`, `w`, `nw`),
     *   `trains`, `by_type`, `first`, `last`, `destinations[]` (`name`, `slug`, `trains`) et `origins[]` quand aucun train ne repart ;
     * - `destinations[]` : principales destinations directes (terminus des trains qui repartent) ;
     * - `feeds[]` : horaires utilisés (nom, `version`, `date`, `valid_to`, licence, page) ; `attribution`, `method`, `provenance`.
     *
     * @param  list<int>  $stationIds
     * @return array<int, array<string, mixed>> seules les gares qui ont des trains ce jour-là
     */
    public function forStations(array $stationIds): array
    {
        if ($stationIds === [] || ! self::ready()) {
            return [];
        }
        try {
            $rows = DB::table('station_timetables')->whereIn('station_id', $stationIds)->get();
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->station_id] = [
                'service_date' => substr((string) $r->service_date, 0, 10),
                'weekday' => 'tuesday',
                'trains' => (int) $r->trains,
                'by_type' => self::byType(json_decode((string) $r->by_type, true)),
                'first' => $r->first_time,
                'last' => $r->last_time,
                'directions' => array_map(fn (array $d): array => ['by_type' => self::byType($d['by_type'] ?? [])] + $d, json_decode((string) $r->directions, true) ?: []),
                'destinations' => json_decode((string) $r->destinations, true) ?: [],
                'origins' => json_decode((string) $r->origins, true) ?: [],
                'feeds' => json_decode((string) $r->feeds, true) ?: [],
                'attribution' => self::ATTRIBUTION,
                'method' => self::METHOD,
                'provenance' => $this->provenance(),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function forLine(int $lineId): ?array
    {
        return $this->forLines([$lineId])[$lineId] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function forLineSlug(string $slug): ?array
    {
        if (! self::ready()) {
            return null;
        }
        try {
            $id = DB::table('lines')->where('slug', $slug)->value('id');
        } catch (Throwable) {
            return null;
        }

        return $id === null ? null : $this->forLine((int) $id);
    }

    /**
     * Trains par jour sur chaque ligne (un mardi type) : `service_date`, `weekday`, `trains` (courses qui parcourent au moins deux
     * gares consécutives de la ligne, comptées une fois), `by_type`, `attribution`, `method`, `provenance`. Seules les lignes
     * parcourues par des trains de voyageurs ce jour-là.
     *
     * @param  list<int>  $lineIds
     * @return array<int, array<string, mixed>>
     */
    public function forLines(array $lineIds): array
    {
        if ($lineIds === [] || ! self::ready()) {
            return [];
        }
        try {
            $rows = DB::table('line_timetables')->whereIn('line_id', $lineIds)->get();
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->line_id] = [
                'service_date' => substr((string) $r->service_date, 0, 10),
                'weekday' => 'tuesday',
                'trains' => (int) $r->trains,
                'by_type' => self::byType(json_decode((string) $r->by_type, true)),
                'attribution' => self::ATTRIBUTION,
                'method' => self::METHOD,
                'provenance' => $this->provenance(),
            ];
        }

        return $out;
    }

    /**
     * Trains par type dans l'ordre fixe TGV, Intercités, TER, Transilien, autres (jsonb range les clés par longueur).
     *
     * @param  array<string, int>|null  $byType
     */
    private static function byType(?array $byType): object
    {
        $out = [];
        foreach (['tgv', 'intercites', 'ter', 'transilien', 'other'] as $type) {
            if (isset($byType[$type])) {
                $out[$type] = (int) $byType[$type];
            }
        }

        return (object) $out;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('station_timetables', self::SOURCE);
    }
}
