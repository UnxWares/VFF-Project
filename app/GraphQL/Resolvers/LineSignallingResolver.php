<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Resolvers\Concerns\FindsEra;
use App\Models\Line;
use Illuminate\Support\Facades\DB;

class LineSignallingResolver
{
    use FindsEra;

    /**
     * @param  array{era?: ?int}  $args
     */
    public function __invoke(Line $line, array $args): array
    {
        $era = self::findEra($args['era'] ?? null);

        $rows = DB::table('line_signalling')
            ->where('line_id', $line->id)
            ->where('era_id', $era->id)
            ->orderBy('pk_start_m')
            ->get(['pk_start_m', 'pk_end_m', 'signalling', 'train_protection', 'kvb_equipped', 'note']);

        return $rows->map(fn ($r) => [
            'pk_start_m' => (int) $r->pk_start_m,
            'pk_end_m' => (int) $r->pk_end_m,
            'length_m' => (int) ($r->pk_end_m - $r->pk_start_m),
            'signalling' => $r->signalling,
            'train_protection' => $r->train_protection ? json_decode($r->train_protection, true) : [],
            'kvb_equipped' => (bool) $r->kvb_equipped,
            'note' => $r->note,
        ])->all();
    }
}
