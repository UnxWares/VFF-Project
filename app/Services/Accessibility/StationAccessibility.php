<?php

namespace App\Services\Accessibility;

use App\Services\Api\Provenance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Accessibilité et services d'une gare d'aujourd'hui d'après SNCF Gares & Connexions (ODbL), table `station_accessibility`
 * (vff:import-station-accessibility). Même contenu pour la fiche de la gare (lu par l'API au chargement de la section), l'API
 * REST (`GET /api/v1/stations/{station}/accessibility`), GraphQL (`Station.accessibility`) et le serveur MCP
 * (`station_accessibility`).
 *
 * Tant que la migration n'est pas appliquée (ou si la lecture échoue), tout est vide : aucune erreur, la fiche s'affiche.
 */
final class StationAccessibility
{
    public const SOURCE = 'sncf';

    public const ATTRIBUTION = 'Données SNCF Gares & Connexions et SNCF Voyageurs (SNCF Open Data, jeux « Accessibilité des gares » au '
        .'format NeTEx, « Equipements d\'accessibilité en gare », « Données du service d\'Assistance Voyageur Handicapé en gare », '
        .'« Etat de fonctionnement des équipements d\'élévatique en gare », « Points de vente SNCF » et « Stationnement vélo sécurisé '
        .'en gare »), sous licence ODbL 1.0 : mention de la source et partage à l\'identique des bases dérivées diffusées.';

    public const METHOD = 'Ce que SNCF publie, gare par gare. L\'absence d\'un équipement dans les jeux ne prouve pas qu\'il n\'existe pas '
        .'(SNCF précise que la liste des équipements n\'est pas encore complète) ; l\'état des ascenseurs est celui du moment de la lecture '
        .'(`state_at`), pas celui d\'aujourd\'hui, et SNCF écrit qu\'il n\'est pas contractuel ; les jeux ne se recoupent pas toujours (une gare peut avoir '
        .'des ascenseurs sans être déclarée accessible) ; les points de vente ne comptent pas '
        .'Transilien ; le stationnement vélo est celui de fin 2024.';

    /** Équipements et services recensés (clés de `amenities`), dans l'ordre d'affichage. */
    public const AMENITIES = ['step_free_entrance', 'boarding_assistance', 'induction_loop', 'tactile_strips', 'audio_information',
        'information_screens', 'wheelchair_loan', 'staff', 'toilets', 'toilets_accessible'];

    private const READY_CACHE = 'station-accessibility:table-ready';

    private static ?bool $ready = null;

    public function __construct(private readonly Provenance $provenance) {}

    /** La table existe (migration appliquée). Un constat positif est gardé en cache, un négatif est refait. */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            if (Cache::get(self::READY_CACHE) === true) {
                return self::$ready = true;
            }
            $ready = (bool) DB::scalar("SELECT to_regclass('station_accessibility') IS NOT NULL");
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
     * - `uic_code` ;
     * - `access` : `mobility` (`partial` : accessible en partie aux personnes à mobilité réduite, `none` : non déclarée accessible par SNCF, `null` :
     *   le jeu NeTEx n'a pas la gare), `accessible` (`mobility` vaut `partial`), `step_free` (cheminement sans marche), `tactile_guidance`,
     *   `quays` (`total`, `step_free`), `comment` (texte de SNCF) ;
     * - `elevators` : `count` (ascenseurs recensés), `escalators`, `travelators`, `status` (état publié : `state_at`, `ok`, `ko`,
     *   `unknown` (ascenseurs seulement), `items[]` avec `label`, `kind`, `location`, `state`) ou null ;
     * - `amenities` : équipements recensés (`step_free_entrance`, `boarding_assistance`, `induction_loop`, `tactile_strips`,
     *   `audio_information`, `information_screens`, `wheelchair_loan`, `staff`, `toilets`, `toilets_accessible`) ;
     * - `assistance` : `mode`, `free`, `meeting_point`, `first_to_last_train`, `hours` (par jour), `since` ;
     * - `sales` : `counter` (guichet), `machines[]`, `assisted` ; `bike_parking` : `places`, `as_of`, `lom`, `lom_target` ;
     * - `datasets[]` : jeux lus (titre, éditeur, licence, date de modification, page) ; `attribution`, `method`, `provenance`.
     *
     * @param  list<int>  $stationIds
     * @return array<int, array<string, mixed>> seules les gares qui ont des informations
     */
    public function forStations(array $stationIds): array
    {
        if ($stationIds === [] || ! self::ready()) {
            return [];
        }
        try {
            $rows = DB::table('station_accessibility')->whereIn('station_id', $stationIds)->get();
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $amenities = json_decode((string) $r->amenities, true) ?: [];
            $out[(int) $r->station_id] = [
                'uic_code' => (string) $r->uic_code,
                'access' => [
                    'mobility' => $r->mobility,
                    'accessible' => (bool) $r->accessible,
                    'step_free' => $r->step_free !== null ? (bool) $r->step_free : null,
                    'tactile_guidance' => $r->tactile_guidance !== null ? (bool) $r->tactile_guidance : null,
                    'quays' => $r->quays_total !== null ? ['total' => (int) $r->quays_total, 'step_free' => (int) $r->quays_step_free] : null,
                    'comment' => $r->netex_comment,
                ],
                'elevators' => [
                    'count' => $r->lifts !== null ? (int) $r->lifts : null,
                    'escalators' => $r->escalators !== null ? (int) $r->escalators : null,
                    'travelators' => $r->travelators !== null ? (int) $r->travelators : null,
                    'status' => $r->elevators !== null ? self::status(json_decode((string) $r->elevators, true)) : null,
                ],
                'amenities' => array_values(array_filter(self::AMENITIES, fn (string $k): bool => in_array($k, $amenities, true))),
                'assistance' => $r->assistance !== null ? self::assistance(json_decode((string) $r->assistance, true)) : null,
                'sales' => $r->sales !== null ? self::sales(json_decode((string) $r->sales, true)) : null,
                'bike_parking' => $r->bike_parking !== null ? self::bikes(json_decode((string) $r->bike_parking, true)) : null,
                'datasets' => json_decode((string) $r->datasets, true) ?: [],
                'attribution' => self::ATTRIBUTION,
                'method' => self::METHOD,
                'provenance' => $this->provenance(),
            ];
        }

        return $out;
    }

    /**
     * Les clés d'un bloc dans l'ordre documenté (jsonb range les clés par longueur).
     *
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private static function status(array $status): array
    {
        return ['state_at' => $status['state_at'] ?? null, 'ok' => (int) ($status['ok'] ?? 0), 'ko' => (int) ($status['ko'] ?? 0),
            'unknown' => (int) ($status['unknown'] ?? 0),
            'items' => array_map(fn (array $i): array => ['label' => $i['label'] ?? '', 'kind' => $i['kind'] ?? 'lift', 'location' => $i['location'] ?? null, 'state' => $i['state'] ?? 'unknown'], $status['items'] ?? [])];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @return array<string, mixed>
     */
    private static function sales(array $sales): array
    {
        return ['counter' => (bool) ($sales['counter'] ?? false), 'machines' => array_values($sales['machines'] ?? []), 'assisted' => (bool) ($sales['assisted'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $bikes
     * @return array<string, mixed>
     */
    private static function bikes(array $bikes): array
    {
        return ['places' => (int) ($bikes['places'] ?? 0), 'as_of' => $bikes['as_of'] ?? null, 'lom' => (bool) ($bikes['lom'] ?? false), 'lom_target' => $bikes['lom_target'] ?? null];
    }

    /**
     * Assistance avec ses horaires dans l'ordre de la semaine (jsonb range les clés par longueur).
     *
     * @param  array<string, mixed>  $assistance
     * @return array<string, mixed>
     */
    private static function assistance(array $assistance): array
    {
        $hours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $hours[$day] = $assistance['hours'][$day] ?? null;
        }

        return ['mode' => $assistance['mode'] ?? null, 'free' => $assistance['free'] ?? null, 'meeting_point' => $assistance['meeting_point'] ?? null,
            'first_to_last_train' => (bool) ($assistance['first_to_last_train'] ?? false), 'hours' => $hours, 'since' => $assistance['since'] ?? null];
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->provenance->for('station_accessibility', self::SOURCE);
    }
}
