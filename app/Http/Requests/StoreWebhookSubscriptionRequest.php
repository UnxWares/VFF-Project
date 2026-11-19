<?php

namespace App\Http\Requests;

use App\Enums\WebhookEvent;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un abonnement webhook depuis le profil : adresse HTTPS et événements suivis. La
 * protection SSRF (WebhookUrlGuard) passe ensuite, dans WebhookSubscriptions::create.
 */
class StoreWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'webhook_url' => ['required', 'string', 'max:'.WebhookUrlGuard::MAX_LENGTH],
            'webhook_events' => ['required', 'array', 'min:1'],
            'webhook_events.*' => ['string', Rule::enum(WebhookEvent::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'webhook_url.required' => __('webhooks.errors.url_required'),
            'webhook_url.string' => __('webhooks.errors.url_required'),
            'webhook_url.max' => __('webhooks.errors.url.invalid'),
            'webhook_events.required' => __('webhooks.errors.events_required'),
            'webhook_events.array' => __('webhooks.errors.events_required'),
            'webhook_events.min' => __('webhooks.errors.events_required'),
            'webhook_events.*' => __('webhooks.errors.events_required'),
        ];
    }

    /** @return list<WebhookEvent> */
    public function events(): array
    {
        return array_values(array_map(fn (string $e): WebhookEvent => WebhookEvent::from($e), (array) $this->validated('webhook_events')));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('webhook_url'))) {
            $this->merge(['webhook_url' => trim($this->input('webhook_url'))]);
        }
    }
}
