<?php

namespace App\Providers;

use App\Events\CurrentEraRefreshed;
use App\Listeners\AnnounceWebhookEvents;
use App\Models\Contribution;
use App\Models\Material;
use App\Services\Webhooks\DnsHostResolver;
use App\Services\Webhooks\HostResolver;
use App\Support\ApiWebhookDocumentation;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Webhooks de l'API : résolveur DNS de la protection SSRF et branchement des événements de
 * l'encyclopédie sur AnnounceWebhookEvents.
 */
final class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
    }

    public function boot(): void
    {
        Contribution::updated(fn (Contribution $c) => app(AnnounceWebhookEvents::class)->contributionUpdated($c));
        // accident.published part de vff:verify-accidents (AccidentVerifier), une fois la fiche vérifiée.
        Material::created(fn (Material $m) => app(AnnounceWebhookEvents::class)->materialCreated($m));
        Event::listen(CurrentEraRefreshed::class, [AnnounceWebhookEvents::class, 'currentEraRefreshed']);

        // Section « Webhooks » de la spécification OpenAPI (valeurs de config/webhooks.php, schéma WebhookEvent).
        Scramble::configure()->withDocumentTransformers(ApiWebhookDocumentation::class);
    }
}
