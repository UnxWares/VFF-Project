<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

class LineStopsResolver
{
    use FindsEra;

    /**
     * @param  array{era?: ?int}  $args
     */
    public function __invoke(Line $line, array $args): array
    {
        $era = self::findEra($args['era'] ?? null);

        $rows = DB::select(
            'SELECT ls.position, ls.pk_m, ls.effective_status, ls.is_terminal, ls.station_id
             FROM line_stops ls
             WHERE ls.line_id = ? AND ls.era_id = ?
             ORDER BY ls.position',
            [$line->id, $era->id],
        );

        if (! $rows) {
            return [];
        }

        $stations = Station::whereIn('id', array_column($rows, 'station_id'))
            ->get()
            ->keyBy('id');

        return array_map(fn ($r) => [
            'position' => (int) $r->position,
            'pk_m' => (int) $r->pk_m,
            'effective_status' => $r->effective_status,
            'is_terminal' => (bool) $r->is_terminal,
            'station' => $stations[$r->station_id] ?? null,
        ], $rows);
    }
}
