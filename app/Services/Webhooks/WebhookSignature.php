<?php

namespace App\Services\Webhooks;

/**
 * Signature d'une livraison, dans l'en-tête `VFF-Signature: t=<horodatage>,v1=<signature>` :
 *
 *   - `t` : secondes depuis l'époque Unix au moment de l'envoi (chaque essai a le sien) ;
 *   - `v1` : HMAC-SHA256 en hexadécimal, avec le secret de l'abonnement, de la chaîne
 *     « <t>.<corps brut de la requête> ».
 *
 * Le destinataire recalcule la signature sur le corps reçu, la compare en temps constant et refuse
 * un horodatage trop éloigné de son horloge (5 minutes, config/webhooks.php `tolerance`) : un
 * message intercepté ne peut pas être rejoué plus tard. L'identifiant `VFF-Delivery`, le même pour
 * tous les essais d'une livraison, permet en plus d'ignorer un doublon.
 */
final class WebhookSignature
{
    public const HEADER = 'VFF-Signature';

    public const SECRET_PREFIX = 'whsec_';

    /** Nouveau secret : préfixe reconnaissable, puis 32 octets aléatoires en hexadécimal. */
    public static function newSecret(): string
    {
        return self::SECRET_PREFIX.bin2hex(random_bytes(32));
    }

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function header(string $secret, int $timestamp, string $body): string
    {
        return 't='.$timestamp.',v1='.self::sign($secret, $timestamp, $body);
    }

    /**
     * Vérification, telle que la fait un destinataire (exemples de la documentation, tests) : en-tête
     * bien formé, horodatage à moins de `$tolerance` secondes de `$now`, signature égale en temps constant.
     */
    public static function verify(string $secret, string $header, string $body, int $now, int $tolerance = 300): bool
    {
        $fields = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $fields[$key] = $value;
        }
        $timestamp = $fields['t'] ?? '';
        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1 || abs($now - (int) $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(self::sign($secret, (int) $timestamp, $body), $fields['v1'] ?? '');
    }
}
