<?php

namespace App\Services\StationAudit;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gares visées par une commande d'audit : `--station` (slug ou identifiant), `--line` (code à six
 * chiffres, slug ou identifiant ; gares de station_lines) et `--shard=i/n` (gares dont
 * l'identifiant modulo n vaut i, pour couper une passe nationale en morceaux).
 */
final class StationScope
{
    /**
     * @param  list<string>  $stations
     * @param  list<string>  $lines
     * @return list<int> identifiants triés
     */
    public static function resolve(array $stations, array $lines, ?string $shard): array
    {
        $query = DB::table('stations as s')->select('s.id')->orderBy('s.id');
        if ($stations !== []) {
            $ids = array_map('intval', array_filter($stations, 'ctype_digit'));
            $slugs = array_values(array_filter($stations, fn (string $v): bool => ! ctype_digit($v)));
            $query->where(fn ($q) => $q->whereIn('s.id', $ids)->orWhereIn('s.slug', $slugs));
        }
        if ($lines !== []) {
            $ids = array_map('intval', array_filter($lines, fn (string $v): bool => ctype_digit($v) && strlen($v) < 6));
            $codes = array_values(array_filter($lines, fn (string $v): bool => ctype_digit($v) && strlen($v) === 6));
            $slugs = array_values(array_filter($lines, fn (string $v): bool => ! ctype_digit($v)));
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('station_lines as sl')->join('lines as l', 'l.id', '=', 'sl.line_id')
                ->whereColumn('sl.station_id', 's.id')
                ->where(fn ($w) => $w->whereIn('l.id', $ids)->orWhereIn('l.code', $codes)->orWhereIn('l.slug', $slugs)));
        }
        if ($shard !== null && $shard !== '') {
            if (! preg_match('#^(\d+)/(\d+)$#', $shard, $m) || (int) $m[2] < 1 || (int) $m[1] >= (int) $m[2]) {
                throw new InvalidArgumentException("--shard attend i/n avec 0 ≤ i < n (reçu : {$shard})");
            }
            $query->whereRaw('s.id % ? = ?', [(int) $m[2], (int) $m[1]]);
        }

        return $query->pluck('s.id')->map(fn ($id): int => (int) $id)->all();
    }
}
