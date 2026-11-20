<?php

namespace App\Services\Accidents;

use App\Services\PlaceNames;

/**
 * Table de correspondance de lieux des puces de la liste de Wikipédia dont le lieu n'est pas un lien
 * lisible (database/data/accident_places.php). Une ligne ne vaut que pour la puce du jour dont le texte
 * contient encore son extrait : une puce corrigée sur Wikipédia ne reçoit jamais un lieu qui n'est plus le sien.
 */
final class AccidentPlaceTable
{
    /** @var list<array{date: string, needle: string, commune: string, department: string, basis: string}> */
    private array $rows;

    /** @param ?list<array{date: string, needle: string, commune: string, department: string, basis: string}> $rows lignes (par défaut database/data/accident_places.php) */
    public function __construct(?array $rows = null)
    {
        $rows ??= require dirname(__DIR__, 3).'/database/data/accident_places.php';
        $this->rows = array_map(fn (array $r): array => [...$r, 'needle' => PlaceNames::normalize($r['needle'])], $rows);
    }

    /**
     * Lieu de la puce de cette date dont le texte (déjà débarrassé des notes et des modèles, liens remplacés
     * par leur libellé) est $plainText ; null si aucune ligne ne convient ou si deux lignes de communes
     * différentes conviennent (jamais un choix au hasard).
     *
     * @return array{name: string, department: string, basis: string}|null
     */
    public function find(string $date, string $plainText): ?array
    {
        $text = PlaceNames::normalize($plainText);
        $found = array_values(array_filter($this->rows, fn (array $r): bool => $r['date'] === $date && str_contains($text, $r['needle'])));
        $places = array_unique(array_map(fn (array $r): string => $r['commune'].'|'.$r['department'], $found));
        if (count($places) !== 1) {
            return null;
        }

        return ['name' => $found[0]['commune'], 'department' => $found[0]['department'], 'basis' => $found[0]['basis']];
    }

    /** @return list<array{date: string, needle: string, commune: string, department: string, basis: string}> */
    public function rows(): array
    {
        return $this->rows;
    }
}
