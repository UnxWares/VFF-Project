<?php

namespace App\Mcp\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dates d'ouverture et de fermeture d'une gare (`stations.opened_at`, `closed_at`) revues pour le serveur MCP. La
 * fiche de la gare ne garde pas la source de ces dates : une date n'est pas servie quand TOUS les événements de la
 * gare qui la donnent (même nature, même jour) viennent d'une source non commerciale, y compris une date déduite de
 * l'événement de ligne d'une telle source (NonCommercialSources::sqlKeptStationEvent ; tracés militaires de la Somme
 * datés de 1914 par la seule source non commerciale). Une date qu'aucun événement ne donne reste servie. Une date
 * non servie prend la date et la précision d'un événement servi de même nature et de même année (le plus précis),
 * sinon elle est nulle, avec sa précision et sa borne d'intervalle.
 *
 * Une requête pour toutes les gares d'une réponse. Règle des dates servies hors du site (App\Support\RedistributableDates :
 * API REST, GraphQL, MCP, données ouvertes) ; le site garde ces dates avec leur source.
 */
final class ServedStationDates
{
    /** Types d'événement de gare qui donnent chaque nature de date. */
    public const KINDS = [
        'opened' => ['opening'],
        'closed' => ['closure', 'closure_passenger', 'closure_freight'],
    ];

    /** @var array<string, true> dates non servies, par « slug|nature|AAAA-MM-JJ » */
    private array $withheld = [];

    /** @var array<string, array{date: string, precision: string}> date servie la plus précise, par « slug|nature|AAAA » */
    private array $sameYear = [];

    public function __construct(private readonly NonCommercialSources $nonCommercial) {}

    /**
     * Lit les dates non servies des gares données (slugs). À appeler avant served().
     *
     * @param  list<?string>  $slugs
     */
    public function load(array $slugs): self
    {
        $this->withheld = [];
        $this->sameYear = [];
        $slugs = array_values(array_unique(array_filter($slugs, fn (?string $s): bool => $s !== null && $s !== '')));
        if ($slugs === []) {
            return $this;
        }
        $kindOf = [];
        foreach (self::KINDS as $kind => $types) {
            foreach ($types as $type) {
                $kindOf[$type] = $kind;
            }
        }
        $kept = $this->nonCommercial->sqlKeptStationEvent('station_events');
        $rows = DB::table('station_events')->join('stations', 'stations.id', '=', 'station_events.station_id')
            ->whereIn('stations.slug', $slugs)->whereIn('station_events.type', array_keys($kindOf))->whereNotNull('station_events.event_date')
            ->selectRaw("stations.slug, station_events.type, CAST(station_events.event_date AS date)::text AS day, bool_or({$kept}) AS kept,
                MIN(CASE WHEN {$kept} THEN CASE station_events.date_precision WHEN 'day' THEN 0 WHEN 'month' THEN 1 ELSE 2 END END) AS rank")
            ->groupBy('stations.slug', 'station_events.type', 'day')
            ->orderBy('day')
            ->get();
        $served = [];
        $rankOf = [];
        foreach ($rows as $r) {
            $key = $r->slug.'|'.$kindOf[$r->type].'|'.$r->day;
            $served[$key] = ($served[$key] ?? false) || (bool) $r->kept;
            $year = $r->slug.'|'.$kindOf[$r->type].'|'.substr((string) $r->day, 0, 4);
            if ($r->rank !== null && (int) $r->rank < ($rankOf[$year] ?? 3)) {
                $rankOf[$year] = (int) $r->rank;
                $this->sameYear[$year] = ['date' => (string) $r->day, 'precision' => ['day', 'month', 'year'][(int) $r->rank]];
            }
        }
        foreach ($served as $key => $kept) {
            if (! $kept) {
                $this->withheld[$key] = true;
            }
        }

        return $this;
    }

    /** Vrai si la date (nature de KINDS) de la gare peut être servie. */
    public function served(?string $slug, string $kind, mixed $date): bool
    {
        if ($slug === null || $date === null || $date === '') {
            return true;
        }

        return ! isset($this->withheld[$slug.'|'.$kind.'|'.substr((string) $date, 0, 10)]);
    }

    /**
     * Gare au format plat (`slug`, `opened_at`, `opened_precision`, `opened_after`, `closed_at`…) dont les dates non
     * servies prennent celle d'un événement servi de même nature et de même année, ou sont mises à null avec leur
     * précision ; la borne d'intervalle (`*_after`) devient nulle.
     *
     * @param  array<string, mixed>  $station
     * @return array<string, mixed>
     */
    public function station(array $station): array
    {
        foreach (array_keys(self::KINDS) as $kind) {
            $date = $station[$kind.'_at'] ?? null;
            if ($date instanceof \DateTimeInterface) {
                $date = $date->format('Y-m-d');
            }
            if (! $this->served($station['slug'] ?? null, $kind, $date)) {
                $same = $this->sameYear[$station['slug'].'|'.$kind.'|'.substr((string) $date, 0, 4)] ?? null;
                foreach (['_at' => $same['date'] ?? null, '_precision' => $same['precision'] ?? null, '_after' => null] as $suffix => $value) {
                    if (array_key_exists($kind.$suffix, $station)) {
                        $station[$kind.$suffix] = $value;
                    }
                }
            }
        }

        return $station;
    }

    /**
     * Expression SQL de la date servie d'une gare (alias de stations, nature de KINDS), pour trier comme on sert :
     * la date de la gare, celle d'un événement servi de même année quand elle n'est pas servie, sinon NULL.
     */
    public function sql(string $alias, string $kind): string
    {
        $column = "{$alias}.{$kind}_at";
        $types = "ARRAY['".implode("', '", self::KINDS[$kind])."']";
        $kept = $this->nonCommercial->sqlKeptStationEvent('ssd_e');

        return "(CASE WHEN {$column} IS NULL
                OR NOT EXISTS (SELECT 1 FROM station_events ssd_e WHERE ssd_e.station_id = {$alias}.id AND ssd_e.type = ANY({$types})
                    AND ssd_e.event_date = {$column} AND NOT {$kept})
                OR EXISTS (SELECT 1 FROM station_events ssd_e WHERE ssd_e.station_id = {$alias}.id AND ssd_e.type = ANY({$types})
                    AND ssd_e.event_date = {$column} AND {$kept})
            THEN {$column}
            ELSE (SELECT ssd_e.event_date FROM station_events ssd_e WHERE ssd_e.station_id = {$alias}.id AND ssd_e.type = ANY({$types})
                    AND date_part('year', ssd_e.event_date) = date_part('year', {$column}) AND {$kept}
                  ORDER BY CASE ssd_e.date_precision WHEN 'day' THEN 0 WHEN 'month' THEN 1 ELSE 2 END, ssd_e.event_date LIMIT 1)
            END)";
    }
}
