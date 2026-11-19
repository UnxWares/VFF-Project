<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookSubscriptionRequest;
use App\Services\Webhooks\WebhookSubscriptionRefused;
use App\Services\Webhooks\WebhookSubscriptions;
use App\Services\Webhooks\WebhookUrlRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Abonnements webhook, depuis le profil (section « Webhooks »). Le secret de signature de
 * l'abonnement créé passe une seule fois par la session (donnée flash `webhook_created`), lue par
 * ProfileController::show puis oubliée.
 */
class WebhookSubscriptionController extends Controller
{
    public const FLASH = 'webhook_created';

    public function __construct(private readonly WebhookSubscriptions $subscriptions) {}

    public function store(StoreWebhookSubscriptionRequest $request): RedirectResponse
    {
        try {
            $subscription = $this->subscriptions->create($request->user(), (string) $request->validated('webhook_url'), $request->events());
        } catch (WebhookUrlRefused $refused) {
            return back()->withErrors(['webhook_url' => $refused->getMessage()])->withInput();
        } catch (WebhookSubscriptionRefused $refused) {
            return back()->withErrors(['webhook_url' => $refused->getMessage()]);
        }

        return back()
            ->with('success', __('webhooks.flash.created'))
            ->with(self::FLASH, ['id' => $subscription->id, 'secret' => $subscription->secret]);
    }

    public function destroy(Request $request, int $subscription): RedirectResponse
    {
        abort_unless($this->subscriptions->delete($request->user(), $subscription), 404);

        return back()->with('success', __('webhooks.flash.deleted'));
    }

    public function enable(Request $request, int $subscription): RedirectResponse
    {
        abort_unless($this->subscriptions->enable($request->user(), $subscription), 404);

        return back()->with('success', __('webhooks.flash.enabled'));
    }
}
