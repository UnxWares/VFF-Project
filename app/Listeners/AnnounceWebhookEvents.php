<?php

namespace App\Listeners;

use App\Enums\AccidentKind;
use App\Enums\ContributionStatus;
use App\Enums\WebhookEvent;
use App\Events\CurrentEraRefreshed;
use App\Models\Accident;
use App\Models\Contribution;
use App\Models\Line;
use App\Models\Material;
use App\Models\Station;
use App\Services\Webhooks\WebhookDispatcher;

/**
 * Traduit les changements de l'encyclopédie en événements webhook (WebhookDispatcher), branché
 * par WebhookServiceProvider :
 *
 *   - contribution passée à l'état « appliquée » (quel que soit le chemin : approbation, tracé) :
 *     `contribution.applied`, avec la ligne ou la gare visée ;
 *   - ère courante recalculée (CurrentEraRefreshed) : `current_era.refreshed` ;
 *   - fiche d'accident publiée créée par un import (pas les natures cachées ni les alias,
 *     PublicAccidents) : `accident.published` ;
 *   - fiche de matériel créée par un import : `material.published`.
 */
final readonly class AnnounceWebhookEvents
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    public function contributionUpdated(Contribution $contribution): void
    {
        if (! $contribution->wasChanged('status') || $contribution->status !== ContributionStatus::Applied || $contribution->target_id === null) {
            return;
        }

        [$resource, $slug, $route] = match ($contribution->target_type) {
            Line::class => ['line', Line::query()->whereKey($contribution->target_id)->value('slug'), 'api.lines.show'],
            Station::class => ['station', Station::query()->whereKey($contribution->target_id)->value('slug'), 'api.stations.show'],
            default => [null, null, null],
        };
        if ($resource === null || ! is_string($slug) || $slug === '') {
            return;
        }

        $this->dispatcher->dispatch(WebhookEvent::ContributionApplied, $resource, $slug, route($route, [$resource => $slug]));
    }

    public function currentEraRefreshed(CurrentEraRefreshed $event): void
    {
        $this->dispatcher->dispatch(WebhookEvent::CurrentEraRefreshed, 'era', $event->year, route('api.eras.show', ['year' => $event->year]), [
            'as_of' => $event->asOf,
            'lines_changed' => $event->linesCount,
            'stations_changed' => $event->stationsCount,
        ]);
    }

    public function accidentCreated(Accident $accident): void
    {
        if (in_array($accident->kind->value, AccidentKind::HIDDEN, true) || $accident->alias_of_id !== null) {
            return;
        }

        $this->dispatcher->dispatch(WebhookEvent::AccidentPublished, 'accident', $accident->slug, route('api.accidents.show', ['accident' => $accident->slug]));
    }

    public function materialCreated(Material $material): void
    {
        $this->dispatcher->dispatch(WebhookEvent::MaterialPublished, 'material', $material->slug, route('api.materials.show', ['material' => $material->slug]));
    }
}
