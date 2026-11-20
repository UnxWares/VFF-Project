<?php

namespace App\Services\Api;

use App\Enums\ApiAccessProfile;
use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Jetons d'API personnels (Sanctum) d'un compte : création, révocation, liste du profil.
 *
 * Un jeton donne la lecture de l'API publique (capacité `read`) et relève la limite de débit de
 * son compte (ApiAccessProfile). Coché à la création, il reçoit aussi la capacité `contribute`
 * (CONTRIBUTE) : les mutations GraphQL de contribution et de validation, avec les droits du compte.
 * Son secret n'est montré qu'une fois, à la création : la base n'en garde que l'empreinte SHA-256.
 * La création, le renouvellement et la révocation sont consignés dans le journal d'audit
 * (identifiant du jeton et capacité de contribution, jamais son nom ni son secret).
 *
 * Un jeton expire un an après sa création (lifetimeDays()). Son titulaire reçoit un rappel par
 * courriel 30 jours avant (vff:remind-expiring-api-tokens) et le renouvelle d'un clic depuis son
 * profil : un an de plus à partir du jour du renouvellement, même secret.
 */
final readonly class ApiTokens
{
    /** Capacité facultative des mutations GraphQL (submitContribution, validateContribution). */
    public const CONTRIBUTE = 'contribute';

    public function __construct(private AuditLog $audit) {}

    /**
     * Capacités d'un nouveau jeton : `read` toujours, plus les capacités facultatives demandées
     * qui figurent dans config/api.php (les autres sont ignorées).
     *
     * @param  list<string>  $optional
     * @return list<string>
     */
    public static function abilities(array $optional = []): array
    {
        $allowed = (array) config('api.tokens.optional_abilities', [self::CONTRIBUTE]);

        return array_values(array_unique([
            ...(array) config('api.tokens.abilities', ['read']),
            ...array_values(array_intersect($optional, $allowed)),
        ]));
    }

    public static function maxPerAccount(): int
    {
        return max(1, (int) config('api.tokens.max_per_account', 10));
    }

    /** Durée de validité d'un jeton, en jours (config/api.php). */
    public static function lifetimeDays(): int
    {
        return max(1, (int) config('api.tokens.lifetime_days', 365));
    }

    /** Délai du rappel avant l'échéance, en jours (config/api.php). */
    public static function reminderDays(): int
    {
        return max(1, (int) config('api.tokens.reminder_days', 30));
    }

    /** Échéance d'un jeton créé ou renouvelé maintenant. */
    public static function expiryFromNow(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays(self::lifetimeDays());
    }

    /** Le compte peut-il créer un jeton maintenant ? */
    public function canCreate(User $user): bool
    {
        return ! $user->isSuspended() && $user->tokens()->count() < self::maxPerAccount();
    }

    /**
     * Crée un jeton : lecture, et contribution si `$contribute`. Les refus (compte suspendu,
     * nombre maximal atteint) sont vérifiés par l'appelant (StoreApiTokenRequest, middleware not.suspended) ; ce service les
     * vérifie de nouveau, sous verrou du compte, pour qu'un double envoi ne dépasse pas le nombre maximal.
     *
     * @throws ApiTokenRefused
     */
    public function create(User $user, string $name, bool $contribute = false): NewAccessToken
    {
        return DB::transaction(function () use ($user, $name, $contribute): NewAccessToken {
            // Suspension relue sur la ligne verrouillée : une suspension décidée pendant la requête compte.
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->isSuspended()) {
                throw ApiTokenRefused::suspended();
            }
            if ($user->tokens()->count() >= self::maxPerAccount()) {
                throw ApiTokenRefused::tooMany(self::maxPerAccount());
            }

            $token = $user->createToken(trim($name), self::abilities($contribute ? [self::CONTRIBUTE] : []), self::expiryFromNow());
            // Capacité de contribution notée seulement quand elle est accordée.
            $this->audit->record(AuditAction::ApiTokenCreated, $user, context: [
                'token_id' => $token->accessToken->id,
                ...($contribute ? ['contribute' => true] : []),
            ]);

            return $token;
        });
    }

    /**
     * Renouvelle un jeton du compte : échéance repoussée à un an à partir d'aujourd'hui, rappel remis
     * à zéro, même secret (les programmes qui l'utilisent n'ont rien à changer). Un jeton déjà échu se
     * renouvelle aussi. Faux si le jeton n'existe pas, appartient à un autre compte, ou si le compte est
     * suspendu.
     */
    public function renew(User $user, int $tokenId): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return DB::transaction(function () use ($user, $tokenId): bool {
            $renewed = $user->tokens()->whereKey($tokenId)->update(['expires_at' => self::expiryFromNow(), 'expiry_reminded_at' => null]) > 0;
            if ($renewed) {
                $this->audit->record(AuditAction::ApiTokenRenewed, $user, context: ['token_id' => $tokenId]);
            }

            return $renewed;
        });
    }

    /** Révoque (supprime) un jeton du compte ; faux si le jeton n'existe pas ou appartient à un autre compte. */
    public function revoke(User $user, int $tokenId): bool
    {
        return DB::transaction(function () use ($user, $tokenId): bool {
            $deleted = $user->tokens()->whereKey($tokenId)->delete() > 0;
            if ($deleted) {
                $this->audit->record(AuditAction::ApiTokenRevoked, $user, context: ['token_id' => $tokenId]);
            }

            return $deleted;
        });
    }

    /**
     * Ce que montre la section « Jetons d'accès à l'API » du profil : jetons (sans secret), quota du
     * compte, nombre maximal, et le jeton tout juste créé (secret en clair, une seule fois).
     *
     * @return array{tokens: list<array{id: int, name: string, contribute: bool, created_at: ?string, last_used_at: ?string, expires_at: ?string, expired: bool, expires_soon: bool}>, profile: string, per_minute: int, anonymous_per_minute: int, max_tokens: int, lifetime_days: int, can_create: bool, suspended: bool, new_token: ?array{id: int, plain: string}}
     */
    public function forProfile(User $user, mixed $justCreated = null): array
    {
        $tokens = $user->tokens()->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (PersonalAccessToken $t): array => [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
                'contribute' => $t->can(self::CONTRIBUTE),
                'created_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
                'expired' => $t->expires_at !== null && $t->expires_at->isPast(),
                'expires_soon' => $t->expires_at !== null && ! $t->expires_at->isPast() && $t->expires_at->lte(now()->addDays(self::reminderDays())),
            ])->values()->all();

        $profile = ApiAccessProfile::for($user);
        $new = is_array($justCreated) && is_int($justCreated['id'] ?? null) && is_string($justCreated['plain'] ?? null)
            && in_array($justCreated['id'], array_column($tokens, 'id'), true)
            ? ['id' => $justCreated['id'], 'plain' => $justCreated['plain']]
            : null;

        return [
            'tokens' => $tokens,
            'profile' => $profile->value,
            'per_minute' => $profile->perMinute(),
            'anonymous_per_minute' => ApiAccessProfile::Anonymous->perMinute(),
            'max_tokens' => self::maxPerAccount(),
            'lifetime_days' => self::lifetimeDays(),
            'can_create' => ! $user->isSuspended() && count($tokens) < self::maxPerAccount(),
            'suspended' => $user->isSuspended(),
            'new_token' => $new,
        ];
    }
}
