<?php

namespace App\Services\StationService;

use App\Enums\ServicePattern;
use App\Enums\StationServiceClass;

/**
 * Desserte voyageurs d'une gare aujourd'hui, première règle qui s'applique :
 *
 *   1. trains (ou tram-trains) d'un flux à jour :
 *        aucun train avant quatre semaines, puis des trains (motif Starts) : périodique si la
 *        gare était desservie au printemps ou l'été (saison, travaux), sinon aucune desserte
 *        (ouverture ou réouverture à venir, pas encore effective) ;
 *        régulière si presque toutes les semaines de l'horizon, ou des quatre prochaines
 *        semaines, sont desservies ; sinon périodique ;
 *   2. trains de la belle saison seulement (version de mi-juin, finis avant aujourd'hui et
 *      avant le 15 octobre ; au printemps, aucun train ou seulement certains jours, jamais
 *      tous les jours ni toute la semaine) : périodique, saisonnière ;
 *   3. horaires d'un train touristique : touristique ;
 *   4. gare de vélorail (OSM) : vélorail ; gare touristique (OSM) ou ligne touristique en
 *      service : touristique ; vélorail sur une ligne de la gare : vélorail ;
 *   5. cars seulement : car ou bus ;
 *   6. trains d'un flux périmé seulement, ou région sans flux à jour : inconnu ;
 *   7. sinon : aucune desserte.
 * Hors règle 1, la note donne le dernier train vu au printemps ou l'été.
 */
final class StationServiceClassifier
{
    /** Fin de la saison d'été (MMJJ) : un train plus tard n'est plus une desserte d'été. */
    public const SUMMER_END = 1015;

    public function classify(StationEvidence $e, int $today): StationServiceVerdict
    {
        $trains = $e->rail ?? $e->tram;
        if ($trains !== null) {
            return $this->served($trains, $e->rail !== null ? $e->railSoon : $trains, $e->rail === null, $e->summer !== null || $e->spring !== null);
        }
        if ($this->isSeasonal($e, $today)) {
            return new StationServiceVerdict(StationServiceClass::Periodic, ServicePattern::Seasonal, $this->lastSeen($e));
        }
        $h = $e->heritage;

        return match (true) {
            $e->touristTrains !== null => new StationServiceVerdict(StationServiceClass::Tourist, $e->touristTrains->pattern,
                'Horaires publiés par l\'exploitant touristique'),
            $h->velorailStation => new StationServiceVerdict(StationServiceClass::Velorail, null, $this->lastSeen($e)),
            $h->touristStation, $h->touristLine => new StationServiceVerdict(StationServiceClass::Tourist, null, $this->lastSeen($e)),
            $h->velorailLine => new StationServiceVerdict(StationServiceClass::Velorail, null, $this->lastSeen($e)),
            $e->bus !== null => new StationServiceVerdict(StationServiceClass::BusOnly, $e->bus->pattern, $this->lastSeen($e)),
            $e->stale !== null => new StationServiceVerdict(StationServiceClass::Unknown, null,
                sprintf('Trains du %s au %s selon un flux périmé', Ymd::iso($e->stale->first), Ymd::iso($e->stale->last))),
            ! $e->covered => new StationServiceVerdict(StationServiceClass::Unknown, null, 'Aucun flux à jour pour cette région'),
            default => new StationServiceVerdict(StationServiceClass::None, null, $this->lastSeen($e)),
        };
    }

    /**
     * Desserte de la belle saison : trains de l'été finis avant aujourd'hui et avant le 15 octobre,
     * et au printemps aucun train, ou certains jours seulement (Trouville - Dives-Cabourg : les
     * week-ends prolongés). Desservie tous les jours au printemps : pas une saison (Poitiers - Limoges).
     */
    public function isSeasonal(StationEvidence $e, int $today): bool
    {
        $summer = $e->summer;
        $springEveryWeek = $e->spring !== null && in_array($e->spring->pattern, [ServicePattern::Daily, ServicePattern::Weekdays], true);

        return $summer !== null && ! $springEveryWeek && $summer->last < $today && $summer->last % 10000 <= self::SUMMER_END;
    }

    private function served(ServiceProfile $trains, ?ServiceProfile $soon, bool $tramOnly, bool $servedEarlier): StationServiceVerdict
    {
        $notes = $tramOnly ? ['Tram-train seulement'] : [];
        if ($soon === null && $trains->pattern === ServicePattern::Starts) {
            $notes[] = $servedEarlier ? 'Pas de train avant le '.Ymd::iso($trains->first).' (desservie plus tôt dans l\'année)'
                : 'Trains prévus à partir du '.Ymd::iso($trains->first).' (ouverture ou réouverture à venir)';

            return new StationServiceVerdict($servedEarlier ? StationServiceClass::Periodic : StationServiceClass::None,
                ServicePattern::Starts, implode(' ; ', $notes));
        }
        $regular = $trains->pattern->isRegular() || ($soon !== null && $soon->pattern->isRegular());
        if ($regular && $trains->pattern === ServicePattern::Ends) {
            $notes[] = 'Dernier train prévu le '.Ymd::iso($trains->last);
        }

        return new StationServiceVerdict($regular ? StationServiceClass::Regular : StationServiceClass::Periodic,
            $trains->pattern, $notes === [] ? null : implode(' ; ', $notes));
    }

    /** Dernier train vu dans les versions archivées (printemps, été), s'il y en a. */
    private function lastSeen(StationEvidence $e): ?string
    {
        $seen = array_filter([$e->spring, $e->summer]);
        if ($seen === []) {
            return null;
        }
        $first = min(array_map(fn (ServiceProfile $p): int => $p->first, $seen));
        $last = max(array_map(fn (ServiceProfile $p): int => $p->last, $seen));

        return sprintf('Trains du %s au %s selon les versions archivées du GTFS SNCF (printemps, été)', Ymd::iso($first), Ymd::iso($last));
    }
}
