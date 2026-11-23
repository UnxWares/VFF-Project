<?php

namespace App\Services\Accessibility;

/**
 * Assemble, gare par gare, ce que disent les jeux de SNCF Gares & Connexions sur l'accessibilité et les services (fonction pure :
 * aucune lecture, aucune écriture ; les jeux sont donnés tels que l'API les rend). Clé commune : l'identifiant de la gare dans le
 * référentiel « Gares de voyageurs » ; les jeux qui ne portent qu'un code UIC ou un trigramme y sont rattachés par ce référentiel.
 *
 * Une absence n'est jamais une négation : seuls les faits écrits par SNCF sont repris, sauf le NeTEx qui répond oui ou non.
 */
final class StationAccessibilityBuilder
{
    /** Libellé du jeu « Equipements d'accessibilité en gare » => clé stable de l'API. */
    public const AMENITY_KEYS = [
        "Accès par ascenseur, rampe ou de plain-pied, depuis l'entrée" => 'step_free_entrance',
        'Assistance proposée pour accéder aux quais et monter / descendre du train' => 'boarding_assistance',
        'Boucle à induction magnétique' => 'induction_loop',
        "Bande d'éveil de vigilance sur les quais" => 'tactile_strips',
        'Information sonore en gare et/ou sur les quais' => 'audio_information',
        "Écrans d'information en gare et/ou sur les quais" => 'information_screens',
        'Fauteuil roulant à disposition' => 'wheelchair_loan',
        'Présence du personnel' => 'staff',
        'Toilettes' => 'toilets',
        'Toilettes adaptées aux personnes en fauteuil roulant' => 'toilets_accessible',
    ];

    private const DAYS = ['monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi', 'thursday' => 'jeudi',
        'friday' => 'vendredi', 'saturday' => 'samedi', 'sunday' => 'dimanche'];

    private const SALES_MACHINES = ['Automates TER' => 'ter', 'Automates TGV-Intercités' => 'tgv_intercites'];

    /** @var array<string, string> code UIC => identifiant de gare */
    private array $byUic = [];

    /** @var array<string, string> trigramme => identifiant de gare */
    private array $byTrigram = [];

    /** @var array<string, list<string>> identifiant de gare => codes UIC */
    private array $uics = [];

    /** @var array<string, int> libellés d'équipement inconnus, avec leur nombre */
    public array $unknownAmenities = [];

    /**
     * @param  list<array<string, mixed>>  $gares  jeu « Gares de voyageurs » : `id`, `codes_uic` (« 87…;87… »), `libellecourt`
     */
    public function __construct(array $gares)
    {
        foreach ($gares as $g) {
            $id = strtolower(trim((string) ($g['id'] ?? '')));
            if ($id === '') {
                continue;
            }
            foreach (array_filter(array_map('trim', explode(';', (string) ($g['codes_uic'] ?? '')))) as $uic) {
                $uic = self::uic($uic);
                $this->byUic[$uic] ??= $id;
                $this->uics[$id][] = $uic;
            }
            $trigram = strtoupper(trim((string) ($g['libellecourt'] ?? '')));
            if ($trigram !== '') {
                $this->byTrigram[$trigram] ??= $id;
            }
        }
    }

    /** Codes UIC d'une gare du référentiel. @return list<string> */
    public function uicsOf(string $gareId): array
    {
        return $this->uics[$gareId] ?? [];
    }

    /** Code UIC de 8 chiffres (certains jeux écrivent un zéro de tête : « 0087763425 »). */
    public static function uic(string $code): string
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';

        return strlen($code) === 10 && str_starts_with($code, '00') ? substr($code, 2) : $code;
    }

    /**
     * @param  array<string, array<string, mixed>>  $netex  AccessibilityNetex::stations()
     * @param  list<array<string, mixed>>  $equipments
     * @param  list<array<string, mixed>>  $assistance
     * @param  list<array<string, mixed>>  $elevators
     * @param  list<array<string, mixed>>  $sales
     * @param  list<array<string, mixed>>  $bikes
     * @param  string|null  $elevatorsAt  date et heure de lecture de l'état des ascenseurs (ISO 8601)
     * @return array<string, array<string, mixed>> par identifiant de gare ; seules les gares qui ont au moins une information
     */
    public function build(array $netex, array $equipments, array $assistance, array $elevators, array $sales, array $bikes, ?string $elevatorsAt): array
    {
        $rows = [];
        foreach ($netex as $id => $n) {
            $rows[$id] = [...$this->empty($id),
                'mobility' => $n['mobility'], 'accessible' => $n['mobility'] === 'partial', 'step_free' => $n['step_free'],
                'tactile_guidance' => $n['tactile'], 'quays_total' => $n['quays_total'] ?: null,
                'quays_step_free' => $n['quays_total'] ? $n['quays_step_free'] : null,
                'lifts' => count($n['lifts']), 'escalators' => $n['escalators'], 'travelators' => $n['travelators'],
                'netex_comment' => $n['comment']];
        }

        $amenities = [];
        foreach ($equipments as $r) {
            $id = $this->byUic[self::uic((string) ($r['uic'] ?? ''))] ?? null;
            $label = trim((string) ($r['accessibilite'] ?? ''));
            if ($id === null || $label === '') {
                continue;
            }
            $key = self::AMENITY_KEYS[$label] ?? null;
            if ($key === null) {
                $this->unknownAmenities[$label] = ($this->unknownAmenities[$label] ?? 0) + 1;

                continue;
            }
            $amenities[$id][$key] = true;
        }
        foreach ($amenities as $id => $keys) {
            $rows[$id] ??= $this->empty($id);
            $rows[$id]['amenities'] = array_values(array_filter(array_keys(array_flip(self::AMENITY_KEYS)), fn (string $k): bool => isset($keys[$k])));
        }

        foreach ($assistance as $r) {
            $id = strtolower(trim((string) ($r['id_gare'] ?? ''))) ?: ($this->byUic[self::uic((string) ($r['code_uic'] ?? ''))] ?? null);
            if ($id === null || $id === '') {
                continue;
            }
            $rows[$id] ??= $this->empty($id);
            $rows[$id]['assistance'] = self::assistance($r);
        }

        $stateByGare = [];
        foreach ($elevators as $r) {
            $id = strtolower(trim((string) ($r['gareid'] ?? '')));
            if ($id === '') {
                continue;
            }
            $label = trim((string) ($r['libelle'] ?? ''));
            $stateByGare[$id][] = ['label' => $label, 'kind' => self::kind($label), 'location' => ($l = trim((string) ($r['localisationdescriptive'] ?? ''))) !== '' ? $l : null,
                'state' => match (strtoupper(trim((string) ($r['etat_de_fonctionnement'] ?? '')))) {
                    'OK' => 'ok', 'KO' => 'ko', default => 'unknown'
                }];
        }
        foreach ($stateByGare as $id => $items) {
            usort($items, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
            $rows[$id] ??= $this->empty($id);
            // Les nombres ne comptent que les ascenseurs ; la liste garde aussi les escaliers mécaniques et les trottoirs roulants.
            $lifts = array_filter($items, fn (array $i): bool => $i['kind'] === 'lift');
            $rows[$id]['elevators'] = ['state_at' => $elevatorsAt,
                'ok' => count(array_filter($lifts, fn (array $i): bool => $i['state'] === 'ok')),
                'ko' => count(array_filter($lifts, fn (array $i): bool => $i['state'] === 'ko')),
                'unknown' => count(array_filter($lifts, fn (array $i): bool => $i['state'] === 'unknown')),
                'items' => $items];
        }

        /** @var array<string, array{counter: bool, machines: array<string, string>, assisted: bool}> $salesByGare */
        $salesByGare = [];
        foreach ($sales as $r) {
            $id = $this->byUic[self::uic((string) ($r['gare_code_uic'] ?? ''))] ?? null;
            if ($id === null) {
                continue;
            }
            $type = trim((string) ($r['type_de_point_de_vente'] ?? ''));
            $salesByGare[$id] ??= ['counter' => false, 'machines' => [], 'assisted' => false];
            if ($type === 'Poste de vente guichet') {
                $salesByGare[$id]['counter'] = true;
            } elseif ($type === 'Libre-Service Assisté') {
                $salesByGare[$id]['assisted'] = true;
            } elseif (isset(self::SALES_MACHINES[$type])) {
                $salesByGare[$id]['machines'][self::SALES_MACHINES[$type]] = self::SALES_MACHINES[$type];
            }
        }
        foreach ($salesByGare as $id => $s) {
            $rows[$id] ??= $this->empty($id);
            $s['machines'] = array_values($s['machines']);
            sort($s['machines']);
            $rows[$id]['sales'] = $s;
        }

        foreach ($bikes as $r) {
            $id = $this->byTrigram[strtoupper(trim((string) ($r['code_gare'] ?? '')))] ?? null;
            $places = $r['nombre_places_au_12_24'] ?? $r['nombre_places_au_06_24'] ?? null;
            if ($id === null || $places === null) {
                continue;
            }
            $target = trim((string) ($r['objectif_decret_lom'] ?? ''));
            $rows[$id] ??= $this->empty($id);
            $rows[$id]['bike_parking'] = ['places' => (int) $places, 'as_of' => $r['nombre_places_au_12_24'] !== null ? '2024-12-30' : '2024-06-30',
                'lom' => strtoupper(trim((string) ($r['gare_lom_hors_lom'] ?? ''))) === 'LOM',
                'lom_target' => ctype_digit($target) ? (int) $target : null];
        }

        // Une gare du référentiel sans aucun code UIC n'est jamais rattachée à une gare de VFF.
        return array_filter($rows, fn (array $row, string $id): bool => isset($this->uics[$id]), ARRAY_FILTER_USE_BOTH);
    }

    /** Nature d'un équipement d'élévatique d'après son libellé (« Esc mec - … », « Trottoir roulant … », sinon ascenseur). */
    public static function kind(string $label): string
    {
        $label = mb_strtolower($label);

        return match (true) {
            str_starts_with($label, 'esc') => 'escalator',
            str_starts_with($label, 'trottoir') => 'travelator',
            default => 'lift',
        };
    }

    /** @return array<string, mixed> */
    private function empty(string $gareId): array
    {
        return ['sncf_station_id' => $gareId, 'mobility' => null, 'accessible' => false, 'step_free' => null, 'tactile_guidance' => null,
            'quays_total' => null, 'quays_step_free' => null, 'lifts' => null, 'escalators' => null, 'travelators' => null,
            'netex_comment' => null, 'elevators' => null, 'amenities' => [], 'assistance' => null, 'sales' => null, 'bike_parking' => null];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private static function assistance(array $r): array
    {
        $hours = [];
        foreach (self::DAYS as $en => $fr) {
            $value = trim((string) ($r['horaires_jour_nominal_'.$fr] ?? ''));
            $hours[$en] = $value !== '' ? $value : null;
        }
        $at = fn (string $k): ?string => ($v = trim((string) ($r[$k] ?? ''))) !== '' ? $v : null;
        $since = $at('datestatut');

        return [
            'mode' => match (strtoupper((string) $at('typepriseencharge'))) {
                'RESERVE ET SPONTANE' => 'reserved_or_walk_in', 'RESERVE' => 'reserved', 'SPONTANE' => 'walk_in', default => null
            },
            'free' => $at('typetarification') !== null ? strtoupper((string) $at('typetarification')) === 'GRATUIT' : null,
            'meeting_point' => ($place = $at('lieurendezvousgare')) !== null ? (rtrim($place, ' .') ?: null) : null,
            'first_to_last_train' => (string) ($r['premierauderniertrain'] ?? '') === '1',
            'hours' => $hours,
            'since' => $since !== null ? substr($since, 0, 10) : null,
        ];
    }
}
