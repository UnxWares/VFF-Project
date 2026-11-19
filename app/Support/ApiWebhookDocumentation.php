<?php

namespace App\Support;

use App\Enums\WebhookEvent;
use App\Services\Webhooks\WebhookSubscriptions;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Section « Webhooks » de la spécification OpenAPI (config/scramble.php) : valeurs réelles de
 * config/webhooks.php posées dans le texte, et schéma `WebhookEvent` de la charge utile (onglet
 * « Models » de Scalar).
 */
final class ApiWebhookDocumentation implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->info->description = strtr($document->info->description, [
            '{webhooks.max}' => (string) WebhookSubscriptions::maxPerAccount(),
            '{webhooks.tolerance}' => (string) (int) config('webhooks.tolerance', 300),
            '{webhooks.timeout}' => (string) (int) config('webhooks.timeout', 5),
            '{webhooks.attempts}' => (string) max(1, (int) config('webhooks.attempts', 5)),
            '{webhooks.disable_after}' => (string) WebhookSubscriptions::disableAfter(),
        ]);

        $data = new ObjectType;
        $data->addProperty('resource', (new StringType)->enum(['line', 'station', 'era', 'accident', 'material'])
            ->setDescription('Sorte de ressource concernée.')->example('line'));
        $data->addProperty('id', (new MixedType)
            ->setDescription('Slug de la ressource (chaîne), ou année pour une ère (entier).')->example('rfn-420000'));
        $data->addProperty('url', (new StringType)->format('uri')
            ->setDescription('Adresse de la ressource dans l\'API REST.')->example('https://vff-project.org/api/v1/lines/rfn-420000'));
        $data->addProperty('as_of', (new StringType)->format('date')
            ->setDescription('`current_era.refreshed` seulement : date du recalcul.')->example('2026-11-19'));
        $data->addProperty('lines_changed', (new IntegerType)->setDescription('`current_era.refreshed` seulement : lignes recalculées.')->example(3));
        $data->addProperty('stations_changed', (new IntegerType)->setDescription('`current_era.refreshed` seulement : gares recalculées.')->example(1));
        $data->setRequired(['resource', 'id', 'url']);

        $event = new ObjectType;
        $event->addProperty('id', (new StringType)->format('uuid')
            ->setDescription('Identifiant de l\'événement, repris dans l\'en-tête `VFF-Delivery` et identique à chaque nouvel essai.')
            ->example('6f1c2e0a-8d4b-4c1e-9a7f-2b5d3e4c1a90'));
        $event->addProperty('type', (new StringType)->enum(WebhookEvent::values())
            ->setDescription('Type de l\'événement, repris dans l\'en-tête `VFF-Event`.')->example('contribution.applied'));
        $event->addProperty('created_at', (new StringType)->format('date-time')
            ->setDescription('Date de l\'événement (ISO 8601).')->example('2026-11-19T14:02:11+00:00'));
        $event->addProperty('data', $data);
        $event->setRequired(['id', 'type', 'created_at', 'data']);
        $event->setDescription('Corps JSON d\'un message de webhook (voir la section « Webhooks »). Signature : en-tête `VFF-Signature: t=<horodatage>,v1=<HMAC-SHA256 hexadécimal de « <t>.<corps brut> »>`.');

        $document->components->addSchema('WebhookEvent', Schema::fromType($event));
    }
}
