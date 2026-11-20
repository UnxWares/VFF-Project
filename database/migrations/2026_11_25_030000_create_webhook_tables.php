<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhooks de l'API (config/webhooks.php) :
 *
 *   - `webhook_subscriptions` : adresse HTTPS d'un membre, événements suivis, secret de signature
 *     (chiffré par la clé de l'application : il sert à signer, il ne peut donc pas être haché),
 *     échecs d'affilée et désactivation. Supprimés avec le compte (clé étrangère en cascade) ;
 *   - `webhook_deliveries` : journal des dernières livraisons d'un abonnement (événement, état,
 *     essais, code HTTP reçu), supprimé avec lui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->jsonb('events');
            $table->text('secret');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason', 32)->nullable();
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_subscription_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('event_type', 64);
            $table->jsonb('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error', 32)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['webhook_subscription_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
    }
};
