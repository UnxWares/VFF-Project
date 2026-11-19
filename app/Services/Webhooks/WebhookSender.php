<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Un essai d'envoi d'une livraison : POST JSON signé vers l'adresse de l'abonnement.
 *
 *   - l'adresse est vérifiée et résolue de nouveau (WebhookUrlGuard), la connexion épinglée à
 *     l'adresse IP vérifiée, en HTTPS seulement ;
 *   - aucune redirection n'est suivie : une réponse 3xx est un échec ;
 *   - délais courts (config/webhooks.php) ; le corps de la réponse n'est jamais lu : le transfert
 *     s'arrête dès les en-têtes reçus, quelle que soit leur taille annoncée ;
 *   - 2xx : livrée ; 408, 429, 5xx, délai dépassé, connexion ou DNS en échec : nouvel essai plus
 *     tard ; autre code, redirection ou adresse refusée : échec sans nouvel essai.
 */
final readonly class WebhookSender
{
    public function __construct(private WebhookUrlGuard $guard) {}

    public function send(WebhookSubscription $subscription, WebhookDelivery $delivery): WebhookAttempt
    {
        try {
            $target = $this->guard->target($subscription->url);
        } catch (WebhookUrlRefused $refused) {
            return $refused->reason === WebhookUrlRefused::UNRESOLVED
                ? WebhookAttempt::failure(WebhookAttempt::DNS_FAILED, retryable: true)
                : WebhookAttempt::failure(WebhookAttempt::ADDRESS_REFUSED, retryable: false);
        }

        $body = $delivery->body();
        $timestamp = now()->getTimestamp();
        $status = null;
        $started = hrtime(true);

        try {
            $response = Http::withOptions($this->options($target, $status))
                ->withHeaders([
                    'User-Agent' => (string) config('webhooks.user_agent'),
                    'VFF-Event' => $delivery->event_type,
                    'VFF-Delivery' => $delivery->event_id,
                    WebhookSignature::HEADER => WebhookSignature::header($subscription->secret, $timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->post($target->url);
            $status = $response->status();
        } catch (Throwable $e) {
            // Arrêt volontaire après les en-têtes (on_headers) : le code reçu suffit.
            if ($status === null) {
                $elapsed = self::elapsed($started);
                $timedOut = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains($e->getMessage(), 'cURL error 28');

                return WebhookAttempt::failure($timedOut ? WebhookAttempt::TIMEOUT : WebhookAttempt::CONNECTION_FAILED, true, $elapsed);
            }
        }

        $elapsed = self::elapsed($started);

        return match (true) {
            $status >= 200 && $status < 300 => WebhookAttempt::success($status, $elapsed),
            $status >= 300 && $status < 400 => WebhookAttempt::failure(WebhookAttempt::REDIRECT, false, $elapsed, $status),
            $status === 408, $status === 429, $status >= 500 => WebhookAttempt::failure(WebhookAttempt::HTTP_STATUS, true, $elapsed, $status),
            default => WebhookAttempt::failure(WebhookAttempt::HTTP_STATUS, false, $elapsed, $status),
        };
    }

    /**
     * Options Guzzle de l'envoi. `$status` reçoit le code HTTP dès les en-têtes, puis le transfert
     * est interrompu (le corps n'est pas téléchargé).
     *
     * @return array<string, mixed>
     */
    public function options(WebhookTarget $target, ?int &$status): array
    {
        $curl = [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ];
        if (($resolve = $target->curlResolve()) !== null) {
            $curl[CURLOPT_RESOLVE] = [$resolve];
        }

        return [
            'allow_redirects' => false,
            'connect_timeout' => (float) config('webhooks.connect_timeout', 3),
            'timeout' => (float) config('webhooks.timeout', 5),
            'verify' => true,
            // Aucun mandataire : la connexion part directement vers l'adresse vérifiée.
            'proxy' => '',
            'http_errors' => false,
            'curl' => $curl,
            'on_headers' => static function (ResponseInterface $response) use (&$status): void {
                $status = $response->getStatusCode();

                throw new RuntimeException('Corps de la réponse ignoré.');
            },
        ];
    }

    private static function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
