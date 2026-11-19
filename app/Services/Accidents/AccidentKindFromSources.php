<?php

namespace App\Services\Accidents;

use App\Enums\AccidentKind;
use App\Models\Accident;
use App\Models\AccidentSource;

/**
 * Nature d'une fiche classée « autre », quand une de ses sources la dit :
 *
 *   - type de l'infobox de l'article de Wikipédia (« Collision », « Déraillement ») ;
 *   - verbe du récit d'une puce de liste (« percute », « se renversent », « s'effondre »), relu dans le
 *     cache des pages (WikipediaListReader) : le texte n'est jamais gardé, seule la nature l'est.
 *
 * Une source qui ne dit rien de net laisse la fiche « autre » : aucune nature n'est devinée.
 */
final class AccidentKindFromSources
{
    public function __construct(
        private readonly WikipediaListCollector $list,
        private readonly AccidentKindClassifier $classifier,
    ) {}

    /**
     * Nature lue par chaque puce de liste, d'après le cache des pages.
     *
     * @return array<string, AccidentKind> identifiant de la source (accident_sources.external_id) → nature
     */
    public function listKinds(AccidentSourceClient $client): array
    {
        $kinds = [];
        foreach ($this->list->collect($client) as $candidate) {
            if ($candidate->kind !== null && $candidate->kind !== AccidentKind::Other) {
                $kinds[substr($candidate->key, strlen('wikipedia-list:'))] = $candidate->kind;
            }
        }

        return $kinds;
    }

    /**
     * @param  array<string, AccidentKind>  $listKinds
     * @return array{kind: AccidentKind, source: AccidentSource}|null
     */
    public function read(Accident $accident, array $listKinds): ?array
    {
        foreach ($accident->sources as $source) {
            $kind = match ($source->role) {
                'article' => $this->classifier->fromFrenchText((string) ($source->facts['type'] ?? '')),
                'list' => $listKinds[(string) $source->external_id] ?? null,
                default => null,
            };
            if ($kind !== null && $kind !== AccidentKind::Other) {
                return ['kind' => $kind, 'source' => $source];
            }
        }

        return null;
    }
}
