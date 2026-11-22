<?php

namespace App\Events;

use App\Broadcasting\ContributorChannel;
use App\Broadcasting\ReviewQueueChannel;
use App\Enums\ContributionRealtimeEvent;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\Contributions\ContributionReview;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\DB;

/**
 * Changement d'état d'une contribution, diffusé en temps réel (BroadcastContributionChanges) :
 *
 *   - canal privé `review-queue` (validateurs et administrateurs) : tous les événements, pour tenir
 *     la file de validation à jour sans recharger la page ;
 *   - canal privé `users.{id}.contributions` : les contributions de ce compte ;
 *   - canal public `contributions` : les publications (`contribution.applied`) seulement.
 *
 * La charge utile est la même sur les trois canaux et ne porte aucune donnée personnelle : ni auteur,
 * ni validateur, ni motif de refus, ni contenu de la proposition. Elle est calculée à la création de
 * l'événement (tableau, pas de modèle à relire dans la file d'attente).
 */
final class ContributionChanged implements ShouldBroadcast
{
    /** Canal public des publications. */
    public const PUBLIC_CHANNEL = 'contributions';

    /**
     * @param  array{event: string, contribution: array{id: int, type: string, status: string, approvals: int, approvals_required: int}, target: array{resource: string, slug: string, url: string}|null, occurred_at: string}  $payload
     */
    public function __construct(
        public readonly ContributionRealtimeEvent $kind,
        public readonly array $payload,
        public readonly ?int $authorId,
    ) {}

    public static function for(Contribution $contribution, ContributionRealtimeEvent $kind): self
    {
        $approvals = DB::table('contribution_approvals')->where('contribution_id', $contribution->id)->count();

        return new self($kind, [
            'event' => $kind->value,
            'contribution' => [
                'id' => (int) $contribution->id,
                'type' => (string) $contribution->type,
                'status' => $contribution->status->value,
                'approvals' => $approvals,
                'approvals_required' => ContributionReview::APPROVAL_THRESHOLD,
            ],
            'target' => self::target($contribution),
            'occurred_at' => now()->toIso8601String(),
        ], $contribution->user_id !== null ? (int) $contribution->user_id : null);
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return array_values(array_filter([
            new PrivateChannel(ReviewQueueChannel::NAME),
            $this->authorId !== null ? new PrivateChannel(ContributorChannel::name($this->authorId)) : null,
            $this->kind->isPublic() ? new Channel(self::PUBLIC_CHANNEL) : null,
        ]));
    }

    public function broadcastAs(): string
    {
        return $this->kind->value;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    /**
     * Fiche visée, par son identifiant public et l'adresse de l'API, ou null (nouvelle ligne ou
     * nouvelle série pas encore publiée, fiche disparue).
     *
     * @return array{resource: string, slug: string, url: string}|null
     */
    private static function target(Contribution $contribution): ?array
    {
        if ($contribution->target_id === null) {
            return null;
        }
        [$resource, $model, $route] = match ($contribution->target_type) {
            Line::class => ['line', Line::class, 'api.lines.show'],
            Station::class => ['station', Station::class, 'api.stations.show'],
            Material::class => ['material', Material::class, 'api.materials.show'],
            default => [null, null, null],
        };
        if ($resource === null) {
            return null;
        }
        $slug = $model::query()->whereKey($contribution->target_id)->value('slug');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return ['resource' => $resource, 'slug' => $slug, 'url' => route($route, [$resource => $slug])];
    }
}
