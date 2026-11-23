<?php

namespace App\Mcp\Support;

use App\Models\Line;
use App\Models\Station;
use App\Services\PathfindingService;
use Illuminate\Support\Facades\DB;

/**
 * Identification des objets demandés par les outils MCP : ligne (slug, code à 6 chiffres avec ou
 * sans espace), gare (slug) et ère (année quelconque ramenée à l'ère de la carte, même règle que
 * l'itinéraire : PathfindingService::eraYearFor).
 */
final class Lookup
{
    /**
     * Ligne par slug (« rfn-420000 »), ancien slug d'une ligne renommée (la ligne revient sous son slug actuel),
     * code (« 420000 », « 420 000 ») ou slug du code.
     */
    public static function line(string $query): ?Line
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }
        $line = Line::where('slug', $q)->first();
        if ($line !== null) {
            return $line;
        }
        $current = Line::redirectSlugFor($q);
        if ($current !== null) {
            return Line::where('slug', $current)->first();
        }
        $digits = str_replace(' ', '', $q);
        if (preg_match('/^\d{6}$/', $digits) === 1) {
            return Line::where('slug', 'rfn-'.$digits)->first()
                ?? Line::whereRaw("replace(code, ' ', '') = ?", [$digits])->orderBy('id')->first();
        }

        return null;
    }

    /** Gare par slug, ou ancien slug (fiche fusionnée dans une autre : la gare revient sous son slug actuel). */
    public static function station(string $query): ?Station
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        return Station::where('slug', $q)->first()
            ?? (($current = Station::redirectSlugFor($q)) !== null ? Station::where('slug', $current)->first() : null);
    }

    /**
     * Ère de la carte pour une année (null : ère courante). null si aucune ère ne la couvre.
     *
     * @return object{id: int, year: int, is_current: bool}|null
     */
    public static function era(?int $year): ?object
    {
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
        $current = $eras->firstWhere('is_current', true) ?? $eras->last();
        if ($current === null) {
            return null;
        }
        $eraYear = $year === null ? (int) $current->year : PathfindingService::eraYearFor(
            $eras->pluck('year')->map(fn ($y) => (int) $y)->all(), $year, (int) $current->year, (int) date('Y'),
        );
        $era = $eraYear === null ? null : $eras->firstWhere('year', $eraYear);

        return $era === null ? null : (object) ['id' => (int) $era->id, 'year' => (int) $era->year, 'is_current' => (bool) $era->is_current];
    }

    /** @return list<int> années des ères disponibles */
    public static function eraYears(): array
    {
        return DB::table('eras')->orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all();
    }
}
