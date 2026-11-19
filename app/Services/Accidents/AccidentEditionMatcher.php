<?php

namespace App\Services\Accidents;

use App\Models\Accident;
use App\Services\PlaceNames;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapproche une entrée d'une liste de Wikipédia en anglais ou en allemand (AccidentEditionEntry) de la fiche
 * du même accident : même JOUR (date au jour des deux côtés, jamais à un jour près : la vérification
 * conclurait à une contradiction) et une commune de la fiche nommée par l'entrée comme lieu.
 *
 * Un nom de lieu ne désigne une commune que s'il n'en désigne qu'une (dans le département quand le
 * qualificatif en est un) ; un nom ambigu ne rapproche rien. Quand deux fiches du jour conviennent, aucune
 * n'est choisie.
 */
final class AccidentEditionMatcher
{
    public const MATCHED = 'matched';

    public const UNMATCHED = 'unmatched';

    public const AMBIGUOUS = 'ambiguous';

    /** @var array<string, ?string> */
    private array $communes = [];

    public function __construct(private readonly WikipediaListReader $departments) {}

    /**
     * @return array{0: string, 1: ?Accident, 2: ?string} statut, fiche, nom du lieu cité qui a rapproché
     */
    public function match(AccidentEditionEntry $entry): array
    {
        if ($entry->places === []) {
            return [self::UNMATCHED, null, null];
        }
        $named = [];
        foreach ($entry->places as $place) {
            $insee = $this->commune($place['name'], $place['qualifier']);
            if ($insee !== null) {
                $named[$insee] = $place['name'];
            }
        }
        if ($named === []) {
            return [self::UNMATCHED, null, null];
        }
        /** @var Collection<int, Accident> $found */
        $found = Accident::query()->withoutGlobalScopes()->whereNull('alias_of_id')
            ->whereDate('occurred_on', $entry->date)->where('date_precision', 'day')
            ->whereIn('commune_insee', array_keys($named))->orderBy('id')->get();
        if ($found->count() > 1) {
            return [self::AMBIGUOUS, null, null];
        }
        $accident = $found->first();

        return $accident === null ? [self::UNMATCHED, null, null] : [self::MATCHED, $accident, $named[$accident->commune_insee]];
    }

    /** Code INSEE de la seule commune de ce nom (dans le département du qualificatif quand il en est un), sinon null. */
    private function commune(string $name, ?string $qualifier): ?string
    {
        $department = $qualifier !== null ? $this->departments->department($qualifier) : null;
        $key = PlaceNames::normalize($name).'|'.$department;
        if (! array_key_exists($key, $this->communes)) {
            $rows = DB::select(
                'SELECT DISTINCT code_insee FROM communes WHERE nom_norm = ? AND (?::text IS NULL OR departement = ?)',
                [PlaceNames::normalize($name), $department, $department],
            );
            $this->communes[$key] = count($rows) === 1 ? $rows[0]->code_insee : null;
        }

        return $this->communes[$key];
    }
}
