<?php

namespace App\Mcp\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dates d'ouverture et de fermeture d'une gare (`stations.opened_at`, `closed_at`) revues pour le serveur MCP. La
 * fiche de la gare ne garde pas la source de ces dates : une date n'est pas servie quand TOUS les événements de la
 * gare qui la donnent (même nature, même jour) viennent d'une source non commerciale, y compris une date déduite de
 * l'événement de ligne d'une telle source (NonCommercialSources::sqlKeptStationEvent ; tracés militaires de la Somme
 * datés de 1914 par la seule source non commerciale). Une date qu'aucun événement ne donne reste servie.
 *
 * Une requête pour toutes les gares d'une réponse. Le site, l'API REST et GraphQL gardent ces dates avec leur source.
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

    public function __construct(private readonly NonCommercialSources $nonCommercial) {}

    /**
     * Lit les dates non servies des gares données (slugs). À appeler avant served().
     *
     * @param  list<?string>  $slugs
     */
    public function load(array $slugs): self
    {
        $this->withheld = [];
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
            ->selectRaw("stations.slug, station_events.type, CAST(station_events.event_date AS date)::text AS day, bool_or({$kept}) AS kept")
            ->groupBy('stations.slug', 'station_events.type', 'day')
            ->get();
        $served = [];
        foreach ($rows as $r) {
            $key = $r->slug.'|'.$kindOf[$r->type].'|'.$r->day;
            $served[$key] = ($served[$key] ?? false) || (bool) $r->kept;
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
     * Gare au format plat (`opened_at`, `opened_precision`, `opened_after`, `closed_at`…) dont les dates non servies
     * sont mises à null, avec leur précision et leur borne.
     *
     * @param  array<string, mixed>  $station
     * @return array<string, mixed>
     */
    public function station(array $station): array
    {
        foreach (array_keys(self::KINDS) as $kind) {
            if (! $this->served($station['slug'] ?? null, $kind, $station[$kind.'_at'] ?? null)) {
                foreach (['_at', '_precision', '_after'] as $suffix) {
                    if (array_key_exists($kind.$suffix, $station)) {
                        $station[$kind.$suffix] = null;
                    }
                }
            }
        }

        return $station;
    }
}
