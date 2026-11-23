<?php

namespace App\Services\Sncf;

/**
 * Rattache une ligne du jeu « Fréquentation en gares » (SNCF Gares & Connexions) à une gare de VFF.
 *
 * Ordre des règles, la première qui désigne UNE gare l'emporte :
 *  1. `uic` : code UIC à 8 chiffres identique ;
 *  2. `uic-department` : plusieurs gares de VFF portent ce code (doublons de fiches : « Grasse », « Virville-
 *     Manneville ») : celle du département du code postal du jeu ;
 *  3. `uic-status` : encore plusieurs : la seule qui n'est pas déclassée ;
 *  4. `alias` : correspondance établie à la main (ALIASES) pour un code UIC que VFF ne porte pas ;
 *  5. `name-department` : aucun code connu, mais un nom identique (accents, tirets et casse ignorés) dans le même
 *     département, une seule gare.
 * Sinon : `ambiguous` (plusieurs gares) ou `unmatched` (aucune). Une même commune compte souvent plusieurs gares
 * (Paris, Lyon, Marseille, Nice…) : jamais de rattachement par la commune seule.
 */
final class StationAttendanceMatcher
{
    /**
     * Code UIC du jeu => slug de la gare de VFF, vérifié à la main (la gare TGV de Roissy est fusionnée dans VFF avec
     * la gare RER, qui porte l'autre code).
     */
    public const ALIASES = [
        '87271494' => 'roissy-aeroport-charles-de-gaulle-2-tgv-rer',
    ];

    /** @var array<string, list<object>> */
    private array $byUic = [];

    /** @var array<string, list<object>> */
    private array $byName = [];

    /** @var array<string, object> */
    private array $bySlug = [];

    /** @param  iterable<object{id: int, slug: string, name: string, uic_code: ?string, status: string, department_code: ?string}>  $stations */
    public function __construct(iterable $stations)
    {
        foreach ($stations as $s) {
            $this->bySlug[$s->slug] = $s;
            if ($s->uic_code !== null && $s->uic_code !== '') {
                $this->byUic[$s->uic_code][] = $s;
            }
            // Un nom sans lettre latine ni chiffre (« 東京 », « ??? ») se réduit à '' : jamais une clé de rattachement.
            $key = self::normalize($s->name);
            if ($key !== '') {
                $this->byName[$key][] = $s;
            }
        }
    }

    /**
     * @return array{station: ?object, method: string} `method` : règle appliquée, ou `ambiguous` / `unmatched`
     */
    public function match(string $uic, string $name, ?string $postalCode): array
    {
        $department = self::department($postalCode);
        $candidates = $this->byUic[$uic] ?? [];

        if (count($candidates) === 1) {
            return ['station' => $candidates[0], 'method' => 'uic'];
        }
        if (count($candidates) > 1) {
            $here = array_values(array_filter($candidates, fn ($s) => self::sameDepartment($s->department_code, $department)));
            if (count($here) === 1) {
                return ['station' => $here[0], 'method' => 'uic-department'];
            }
            $alive = array_values(array_filter($here ?: $candidates, fn ($s) => $s->status !== 'declassified'));
            if (count($alive) === 1) {
                return ['station' => $alive[0], 'method' => 'uic-status'];
            }

            return ['station' => null, 'method' => 'ambiguous'];
        }
        if (isset(self::ALIASES[$uic], $this->bySlug[self::ALIASES[$uic]])) {
            return ['station' => $this->bySlug[self::ALIASES[$uic]], 'method' => 'alias'];
        }
        $key = self::normalize($name);
        $named = array_values(array_filter($key === '' ? [] : ($this->byName[$key] ?? []),
            fn ($s) => $department !== null && self::sameDepartment($s->department_code, $department)));
        if (count($named) === 1) {
            return ['station' => $named[0], 'method' => 'name-department'];
        }

        return ['station' => null, 'method' => count($named) > 1 ? 'ambiguous' : 'unmatched'];
    }

    /** Nom sans accents, tirets, apostrophes ni casse : « Nice Pont Michel » = « Nice-Pont-Michel ». */
    public static function normalize(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($name)) ?: mb_strtolower($name);

        return (string) preg_replace('/[^a-z0-9]+/', '', $ascii);
    }

    /** Département d'un code postal (« 6340 » est « 06340 » : le zéro de tête manque dans le jeu) ; 20xxx est la Corse. */
    public static function department(?string $postalCode): ?string
    {
        $postalCode = trim((string) $postalCode);
        if (! preg_match('/^\d{4,5}$/', $postalCode)) {
            return null;
        }
        $postalCode = str_pad($postalCode, 5, '0', STR_PAD_LEFT);
        $dep = substr($postalCode, 0, str_starts_with($postalCode, '97') ? 3 : 2);

        return $dep === '20' ? '20' : $dep;
    }

    private static function sameDepartment(?string $stationDepartment, ?string $department): bool
    {
        if ($stationDepartment === null || $department === null) {
            return false;
        }

        return $department === '20' ? in_array($stationDepartment, ['2A', '2B', '20'], true) : $stationDepartment === $department;
    }
}
