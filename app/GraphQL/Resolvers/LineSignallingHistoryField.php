<?php

namespace App\GraphQL\Resolvers;

use App\Models\Era;
use App\Models\Line;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingHistoryState;
use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Historique de la signalisation d'une ligne en GraphQL : champ `Line.signalling_history(era:)`, mêmes faits
 * que l'API REST (`GET /api/v1/lines/{line}/signalling-history`, et `in_force` avec `era`).
 *
 * Chargement groupé : pour une liste de lignes (`lines(first: 100) { signalling_history { … } }`), les faits
 * de toutes les lignes de la page sont lus en une requête (SignallingHistoryBatch), pas une par ligne.
 */
final class LineSignallingHistoryField
{
    public function __construct(private readonly LineSignallingHistory $history) {}

    /**
     * @param  array{era?: int|null}  $args
     */
    public function forLine(Line $line, array $args, GraphQLContext $context, ResolveInfo $info): SyncPromise
    {
        /** @var SignallingHistoryBatch $batch */
        $batch = BatchLoaderRegistry::instance($info->path, fn (): SignallingHistoryBatch => new SignallingHistoryBatch($this->history));
        $until = null;
        if (isset($args['era'])) {
            $era = Era::where('year', (int) $args['era'])->first();
            if ($era === null) {
                return new Deferred(fn (): array => []);
            }
            $until = SignallingHistoryState::until((int) $era->year, (bool) $era->is_current);
        }

        return $batch->load($line)->then(fn (array $facts): array => $until === null ? $facts : SignallingHistoryState::inForce($facts, $until));
    }

    public function attribution(): string
    {
        return LineSignallingHistory::ATTRIBUTION;
    }

    /** @return array{provider: ?string, name: ?string, license: ?string, license_status: ?string} */
    public function provenance(): array
    {
        return $this->history->provenance();
    }
}
